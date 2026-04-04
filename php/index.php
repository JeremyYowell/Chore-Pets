<?php
/**
 * Chore Pets – Alexa Skill Webhook
 * ─────────────────────────────────────────────────────────────────────────────
 * This is the single HTTPS endpoint you register in the Alexa Developer Console
 * under "Endpoint → HTTPS". Dreamhost must serve this over HTTPS.
 *
 * Request flow:
 *   Alexa → POST /chores/index.php → JSON response
 *
 * Onboarding architecture (DB-first):
 *   ProvideChildNameIntent  → create/find child in DB immediately; store child_id in session
 *   AddChoreIntent          → write each chore to DB immediately; no session array accumulation
 *   DoneAddingChoresIntent  → validate DB has ≥1 chore, then show pet select
 *   SelectPetIntent         → save pet type to DB; ask "add another?"
 *   AMAZON.YesIntent        → reset session for next child; re-prompt for name
 *   AMAZON.NoIntent         → mark setup done; celebratory speech; show home screen
 */

// ── Output hardening ──────────────────────────────────────────────────────────
// Must come before any require so PHP notices/warnings never pollute JSON output.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);               // still log everything…
ini_set('log_errors', '1');           // …to the server error log, not stdout
ob_start();                           // buffer any stray output

// Global exception handler – always return valid Alexa JSON on crash
set_exception_handler(function (Throwable $e) {
    ob_end_clean();
    error_log('[ChorePets] Uncaught exception: ' . $e->getMessage() .
              ' in ' . $e->getFile() . ':' . $e->getLine());
    header('Content-Type: application/json');
    http_response_code(200); // Alexa requires 200 even for error responses
    echo json_encode([
        'version'  => '1.0',
        'response' => [
            'outputSpeech'     => ['type' => 'PlainText', 'text' => 'Sorry, something went wrong. Please try again.'],
            'shouldEndSession' => true,
        ],
    ]);
    exit;
});

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/PetEngine.php';
require_once __DIR__ . '/lib/ChoreManager.php';
require_once __DIR__ . '/lib/AlexaResponse.php';

// ── Parse incoming request ────────────────────────────────────────────────────

$body = file_get_contents('php://input');
$req  = json_decode($body, true);

if (!$req) {
    ob_end_clean();
    http_response_code(400);
    exit('Bad Request');
}

// Verify skill ID matches (basic guard)
$skillId = $req['context']['System']['application']['applicationId'] ?? '';
if (VERIFY_SIGNATURES && $skillId !== ALEXA_SKILL_ID) {
    ob_end_clean();
    http_response_code(403);
    exit('Forbidden');
}

// ── APL support detection ─────────────────────────────────────────────────────
// Only render APL directives when the device has a screen that supports it.
// The Alexa Developer Console test simulator requires APL to be enabled under
// Build → Interfaces → Alexa Presentation Language before APL will work.
$aplSupported = isset(
    $req['context']['System']['device']['supportedInterfaces']['Alexa.Presentation.APL']
);

// DEBUG: log APL support status and interfaces to Dreamhost error log.
// Check with: tail -f ~/logs/chores.space-monkey.org/http/error.log
// Remove this block once APL is confirmed working.
error_log('[ChorePets] requestType=' . ($req['request']['type'] ?? 'unknown') .
          ' aplSupported=' . ($aplSupported ? 'YES' : 'NO') .
          ' interfaces=' . json_encode(
              array_keys($req['context']['System']['device']['supportedInterfaces'] ?? [])
          ));

// ── Extract common context ────────────────────────────────────────────────────

$alexaUserId  = $req['context']['System']['user']['userId'] ?? '';
$requestType  = $req['request']['type'] ?? '';
$session      = $req['session'] ?? [];
$sessionAttrs = $session['attributes'] ?? [];

// Ensure this household exists
$household = ChoreManager::getOrCreateHousehold($alexaUserId);

// ── Route by request type ─────────────────────────────────────────────────────

switch ($requestType) {

    case 'LaunchRequest':
        handleLaunch($household, $sessionAttrs);
        break;

    case 'IntentRequest':
        handleIntent($req['request']['intent'], $household, $sessionAttrs);
        break;

    case 'Alexa.Presentation.APL.UserEvent':
        handleAplEvent($req['request']['arguments'] ?? [], $household, $sessionAttrs);
        break;

    case 'SessionEndedRequest':
        // Nothing to do – Alexa doesn't expect a response
        ob_end_clean();
        echo json_encode(['version' => '1.0']);
        break;

    default:
        (new AlexaResponse())
            ->speak("I'm not sure how to handle that. Try saying 'open Chore Pets'.")
            ->send();
}


// ── Handlers ──────────────────────────────────────────────────────────────────

function handleLaunch(array $household, array $attrs): never {
    $children = ChoreManager::getChildren($household['id']);

    // No children yet → start onboarding
    if (empty($children)) {
        showOnboarding($household, $attrs);
    }

    // Children exist but setup was never marked done (e.g. crash mid-onboarding)
    if (!$household['setup_done']) {
        ChoreManager::markSetupDone($household['id']);
    }

    showHome($children);
}

function handleIntent(array $intent, array $household, array $attrs): never {
    // ── CRITICAL: declare globals so these are accessible inside this function
    global $aplSupported, $sessionAttrs;

    $name  = $intent['name'] ?? '';
    $slots = $intent['slots'] ?? [];

    $get  = fn(string $slot) => strtolower(trim($slots[$slot]['value'] ?? ''));
    $step = $attrs['onboarding_step'] ?? '';

    switch ($name) {

        // ── Onboarding: child name ────────────────────────────────────────────

        case 'ProvideChildNameIntent':
            $childName = $get('childName');
            if (!$childName) {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("I didn't catch that name. What's your child's name?")
                    ->reprompt("What's your child's name?")
                    ->keepSessionOpen()->send();
            }

            // DB-first: create or find the child immediately (prevents duplicates)
            $existingChild = ChoreManager::findChildByName($household['id'], $childName);
            $childId = $existingChild
                ? $existingChild['id']
                : ChoreManager::addChild($household['id'], $childName);

            // Store only child ID + name in session; no chore array needed
            $r = (new AlexaResponse())
                ->preserveSession($sessionAttrs)
                ->speak(sprintf(PROMPT_ADD_CHORES, ucfirst($childName)))
                ->reprompt("Tell me a chore for " . ucfirst($childName) . ", or say done.")
                ->setSessionAttr('onboarding_step',       'adding_chores')
                ->setSessionAttr('onboarding_child_id',   $childId)
                ->setSessionAttr('onboarding_child_name', $childName)
                ->keepSessionOpen();

            if ($aplSupported) {
                // Show the child's name and an empty chore list — will fill as chores are added
                $completedChildren = buildCompletedChildrenList($household['id'], $childId);
                $r->renderApl('onboarding', AlexaResponse::loadApl('onboarding'),
                    AlexaResponse::buildOnboardingDatasource('adding_chores', $childName, [], $completedChildren));
            }
            $r->send();

        // ── Onboarding: write each chore directly to DB ───────────────────────

        case 'AddChoreIntent':
            $choreName = $get('choreName');
            if (!$choreName) {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("I didn't catch the chore name. What chore would you like to add?")
                    ->reprompt("What chore would you like to add?")
                    ->keepSessionOpen()->send();
            }

            // Write to DB immediately — no session array, nothing gets lost
            $childId   = (int)($attrs['onboarding_child_id'] ?? 0);
            $childName = $attrs['onboarding_child_name'] ?? 'your child';
            if ($childId && $choreName) {
                ChoreManager::addChore($childId, $choreName);
            }

            // Read back all chores from DB so the screen shows the full live list
            $savedChores = $childId ? array_column(ChoreManager::getChores($childId), 'name') : [];
            $choreCount  = count($savedChores);
            $speech      = "Got it! Added " . ucfirst($choreName) . ". " .
                           ($choreCount === 1
                               ? "That's 1 chore so far."
                               : "That's {$choreCount} chores so far.") .
                           " Say another chore, or say done when finished.";

            $r = (new AlexaResponse())
                ->preserveSession($sessionAttrs)
                ->speak($speech)
                ->reprompt("Say another chore, or say done.")
                ->keepSessionOpen();

            if ($aplSupported) {
                $completedChildren = buildCompletedChildrenList($household['id'], $childId);
                $r->renderApl('onboarding', AlexaResponse::loadApl('onboarding'),
                    AlexaResponse::buildOnboardingDatasource('adding_chores', $childName, $savedChores, $completedChildren));
            }
            $r->send();

        // ── Onboarding: done adding chores → validate DB, show pet select ─────

        case 'DoneAddingChoresIntent':
            $childId   = (int)($attrs['onboarding_child_id'] ?? 0);
            $childName = $attrs['onboarding_child_name'] ?? '';

            if (!$childId || !$childName) {
                showOnboarding($household, $attrs);
            }

            // Validate at least one chore was saved to the DB
            $savedChores = ChoreManager::getChores($childId);
            if (empty($savedChores)) {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("Please add at least one chore for " . ucfirst($childName) . " before we continue. What's their first chore?")
                    ->reprompt("Tell me a chore for " . ucfirst($childName) . ".")
                    ->keepSessionOpen()->send();
            }

            showPetSelect($childId, $childName, $aplSupported, $sessionAttrs);

        // ── AMAZON.NoIntent: context-dependent ───────────────────────────────

        case 'AMAZON.NoIntent':
            if ($step === 'adding_chores') {
                // "No" means the same as "done" here — validate and move on
                $childId   = (int)($attrs['onboarding_child_id'] ?? 0);
                $childName = $attrs['onboarding_child_name'] ?? '';

                if (!$childId || !$childName) {
                    showOnboarding($household, $attrs);
                }

                $savedChores = $childId ? ChoreManager::getChores($childId) : [];
                if (empty($savedChores)) {
                    (new AlexaResponse())
                        ->preserveSession($sessionAttrs)
                        ->speak("Please add at least one chore for " . ucfirst($childName) . " before we continue. What's their first chore?")
                        ->reprompt("Tell me a chore for " . ucfirst($childName) . ".")
                        ->keepSessionOpen()->send();
                }

                showPetSelect($childId, $childName, $aplSupported, $sessionAttrs);

            } elseif ($step === 'add_another_child') {
                // Done! Mark setup complete and show home with options
                ChoreManager::markSetupDone($household['id']);
                $children = ChoreManager::getChildren($household['id']);
                $count    = count($children);
                $names    = implode(' and ', array_column($children, 'name'));
                $speech   = "You're all set! " .
                            ($count === 1
                                ? "I've got {$names} ready to go."
                                : "I've got {$names} all ready to go.") .
                            " Tap a name to manage their chores today. " .
                            "You can also say 'add a child' to add more kids anytime.";
                $r = (new AlexaResponse())
                    ->speak($speech)
                    ->reprompt("Tap a child's name to see their chores, or say 'add a child' to add more.")
                    ->keepSessionOpen();
                if ($aplSupported) {
                    $r->renderApl(
                        'home',
                        AlexaResponse::loadApl('home'),
                        AlexaResponse::buildHomeDatasource($children)
                    );
                }
                $r->send();
            }

            // Outside onboarding context — generic re-prompt
            (new AlexaResponse())
                ->preserveSession($sessionAttrs)
                ->speak("I'm not sure what you mean. Say a child's name to see their chores.")
                ->reprompt("Say a child's name.")
                ->keepSessionOpen()->send();

        // ── Onboarding: pick pet ──────────────────────────────────────────────

        case 'SelectPetIntent':
            $petType = $get('petType');
            $childId = (int)($attrs['onboarding_child_id'] ?? 0);
            if (!in_array($petType, PET_TYPES)) {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("I didn't catch that. Please say cat, dog, hamster, or panda.")
                    ->reprompt("Say cat, dog, hamster, or panda.")
                    ->keepSessionOpen()->send();
            }
            if ($childId) ChoreManager::setPetType($childId, $petType);
            // Ask whether there are more children to add
            (new AlexaResponse())
                ->preserveSession($sessionAttrs)
                ->speak(ucfirst($petType) . " it is! " . PROMPT_ADD_ANOTHER_CHILD)
                ->reprompt("Do you want to add another child?")
                ->setSessionAttr('onboarding_step', 'add_another_child')
                ->keepSessionOpen()->send();

        // ── Onboarding: yes, add another child ───────────────────────────────

        case 'AMAZON.YesIntent':
            // Continuing onboarding – reset child context for the next child
            (new AlexaResponse())
                ->preserveSession($sessionAttrs)
                ->speak("Great! What's the name of your next child?")
                ->reprompt("What's your next child's name?")
                ->setSessionAttr('onboarding_step',       'child_name')
                ->setSessionAttr('onboarding_child_id',   0)
                ->setSessionAttr('onboarding_child_name', '')
                ->keepSessionOpen()->send();

        // ── Fallback: re-prompt gracefully based on current onboarding step ──

        case 'AMAZON.FallbackIntent':
            if ($step === 'adding_chores') {
                $childName = $attrs['onboarding_child_name'] ?? 'your child';
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("I didn't get that. Tell me a chore for " . ucfirst($childName) . ", like 'make your bed'. Or say done.")
                    ->reprompt("What chore would you like to add? Or say done.")
                    ->keepSessionOpen()->send();
            } elseif ($step === 'add_another_child') {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("Do you want to add another child? Say yes or no.")
                    ->reprompt("Say yes to add another child, or no if you're all set.")
                    ->keepSessionOpen()->send();
            } elseif ($step === 'child_name') {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("I didn't catch that. What's your child's name?")
                    ->reprompt("What's your child's name?")
                    ->keepSessionOpen()->send();
            } elseif ($step === 'picking_pet') {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("Please say cat, dog, hamster, or panda.")
                    ->reprompt("Say cat, dog, hamster, or panda.")
                    ->keepSessionOpen()->send();
            }
            // No onboarding step — regular fallback
            (new AlexaResponse())
                ->preserveSession($sessionAttrs)
                ->speak("I'm not sure how to help with that. Say a child's name to see their chores.")
                ->reprompt("Say a child's name to see their chores.")
                ->keepSessionOpen()->send();

        // ── Daily use: show child's chore list ───────────────────────────────

        case 'SelectChildIntent':
            $childName = $get('childName');
            $child = ChoreManager::findChildByName($household['id'], $childName);
            if (!$child) {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("I couldn't find a child named {$childName}. Try saying their name again.")
                    ->reprompt("Which child's chores would you like to see?")
                    ->keepSessionOpen()->send();
            }
            showChildView($child);

        case 'NamePetIntent':
            $childName = $get('childName');
            $petName   = $get('petName');
            $child = ChoreManager::findChildByName($household['id'], $childName);
            if (!$child) {
                (new AlexaResponse())->speak("I couldn't find that child.")->send();
            }
            if (ChoreManager::setPetName($child['id'], $petName)) {
                (new AlexaResponse())
                    ->speak("Wonderful! {$childName}'s pet is now named {$petName}!")
                    ->send();
            } else {
                (new AlexaResponse())
                    ->speak("{$childName} hasn't unlocked pet naming yet. Keep completing chores every day for a week!")
                    ->send();
            }

        case 'CheckPetIntent':
            $childName = $get('childName');
            $child = ChoreManager::findChildByName($household['id'], $childName);
            if (!$child) {
                (new AlexaResponse())->speak("I couldn't find that child.")->send();
            }
            $state  = PetEngine::getState($child['id']);
            $speech = PetEngine::getStateSpeech($child['name'], $child['pet_name'] ?? '', $state);
            (new AlexaResponse())->speak($speech)->send();

        case 'MarkChoreIntent':
            // ── Intercept during onboarding: NLU sometimes routes chore phrases here ──
            if ($step === 'adding_chores') {
                $choreName = $get('choreName') ?: strtolower(trim($slots['childName']['value'] ?? ''));
                $childId   = (int)($attrs['onboarding_child_id'] ?? 0);
                $childName = $attrs['onboarding_child_name'] ?? 'your child';
                if ($childId && $choreName) {
                    ChoreManager::addChore($childId, $choreName);
                }
                $savedChores = $childId ? array_column(ChoreManager::getChores($childId), 'name') : [];
                $choreCount  = count($savedChores);
                $speech      = "Got it! Added " . ucfirst($choreName ?: 'that chore') . ". " .
                               ($choreCount === 1 ? "That's 1 chore so far." : "That's {$choreCount} chores so far.") .
                               " Say another chore, or say done when finished.";
                $r = (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak($speech)
                    ->reprompt("Say another chore, or say done.")
                    ->keepSessionOpen();
                if ($aplSupported) {
                    $completedChildren = buildCompletedChildrenList($household['id'], $childId);
                    $r->renderApl('onboarding', AlexaResponse::loadApl('onboarding'),
                        AlexaResponse::buildOnboardingDatasource('adding_chores', $childName, $savedChores, $completedChildren));
                }
                $r->send();
            }

            $childName = $get('childName');
            $choreName = trim($slots['choreName']['value'] ?? ''); // preserve original case
            // If no child named, fall back to session context if available
            $child = $childName
                ? ChoreManager::findChildByName($household['id'], $childName)
                : (isset($attrs['active_child_id'])
                    ? ChoreManager::getChild((int)$attrs['active_child_id'])
                    : null);
            if (!$child) {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("I'm not sure whose chores to update. Say a child's name first.")
                    ->reprompt("Say a child's name to get started.")
                    ->keepSessionOpen()->send();
            }
            $chores = ChoreManager::getChoresWithStatus($child['id']);
            // Find the best matching chore by partial name (case-insensitive)
            $matched = null;
            $search  = strtolower($choreName);
            foreach ($chores as $chore) {
                if (str_contains(strtolower($chore['name']), $search) ||
                    str_contains($search, strtolower($chore['name']))) {
                    $matched = $chore;
                    break;
                }
            }
            if (!$matched) {
                $choreList = implode(', ', array_column($chores, 'name'));
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("I couldn't find a chore called {$choreName} for {$child['name']}. Their chores are: {$choreList}.")
                    ->reprompt("Which chore did they finish?")
                    ->keepSessionOpen()->send();
            }
            ChoreManager::toggleChore($matched['id'], $child['id'], true); // force-mark done
            $progress = PetEngine::todayProgress($child['id']);
            $speech   = "Marked {$matched['name']} done for {$child['name']}. ";
            if ($progress['done'] >= $progress['total'] && $progress['total'] > 0) {
                $petName = $child['pet_name'] ?? 'their pet';
                $speech .= "All chores finished! {$petName} is so happy!";
            } else {
                $remaining = $progress['total'] - $progress['done'];
                $speech .= "{$remaining} chore" . ($remaining !== 1 ? 's' : '') . " left today.";
            }
            (new AlexaResponse())
                ->speak($speech)
                ->setSessionAttr('active_child_id', $child['id'])
                ->keepSessionOpen()->send();

        // ── Pet interactions: Feed / Water / Play ─────────────────────────────
        // All three resolve the child from session context (active_child_id) or
        // from an optional childName slot, then delegate to handlePetInteraction().

        case 'FeedPetIntent':
            $childName = $get('childName');
            $child = $childName
                ? ChoreManager::findChildByName($household['id'], $childName)
                : (isset($attrs['active_child_id'])
                    ? ChoreManager::getChild((int)$attrs['active_child_id'])
                    : null);
            if (!$child) {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("I'm not sure whose pet to feed. Say a child's name first.")
                    ->reprompt("Say a child's name.")
                    ->keepSessionOpen()->send();
            }
            handlePetInteraction('feed', $child);

        case 'WaterPetIntent':
            $childName = $get('childName');
            $child = $childName
                ? ChoreManager::findChildByName($household['id'], $childName)
                : (isset($attrs['active_child_id'])
                    ? ChoreManager::getChild((int)$attrs['active_child_id'])
                    : null);
            if (!$child) {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("I'm not sure whose pet to water. Say a child's name first.")
                    ->reprompt("Say a child's name.")
                    ->keepSessionOpen()->send();
            }
            handlePetInteraction('water', $child);

        case 'PlayWithPetIntent':
            $childName = $get('childName');
            $child = $childName
                ? ChoreManager::findChildByName($household['id'], $childName)
                : (isset($attrs['active_child_id'])
                    ? ChoreManager::getChild((int)$attrs['active_child_id'])
                    : null);
            if (!$child) {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("I'm not sure whose pet to play with. Say a child's name first.")
                    ->reprompt("Say a child's name.")
                    ->keepSessionOpen()->send();
            }
            handlePetInteraction('play', $child);

        case 'ListChoresIntent':
            $childName = $get('childName');
            $child = $childName
                ? ChoreManager::findChildByName($household['id'], $childName)
                : (isset($attrs['active_child_id'])
                    ? ChoreManager::getChild((int)$attrs['active_child_id'])
                    : null);
            if (!$child) {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("Which child's chores would you like to hear?")
                    ->reprompt("Say a child's name.")
                    ->keepSessionOpen()->send();
            }
            $chores   = ChoreManager::getChoresWithStatus($child['id']);
            $progress = PetEngine::todayProgress($child['id']);
            $pending  = array_filter($chores, fn($c) => !$c['completed_today']);
            $speech   = "{$child['name']} has {$progress['done']} of {$progress['total']} chores done today. ";
            if (!empty($pending)) {
                $pendingNames = implode(', ', array_column($pending, 'name'));
                $speech .= "Still to do: {$pendingNames}.";
            } else {
                $speech .= "All chores are complete!";
            }
            (new AlexaResponse())
                ->speak($speech)
                ->setSessionAttr('active_child_id', $child['id'])
                ->keepSessionOpen()->send();

        // ── System intents ────────────────────────────────────────────────────

        case 'AddChildIntent':
            // Post-setup: user says "add a child" or "add another child"
            (new AlexaResponse())
                ->preserveSession($sessionAttrs)
                ->speak("Sure! What's the name of the child you'd like to add?")
                ->reprompt("What's your child's name?")
                ->setSessionAttr('onboarding_step',       'child_name')
                ->setSessionAttr('onboarding_child_id',   0)
                ->setSessionAttr('onboarding_child_name', '')
                ->keepSessionOpen()->send();

        case 'AMAZON.HelpIntent':
            (new AlexaResponse())
                ->speak("Say a child's name to see their chores. Tap any chore to mark it done. Say 'how is the pet for' followed by a name to check on their pet. Say 'add a child' to add a new family member.")
                ->reprompt("Say a child's name to get started.")
                ->keepSessionOpen()->send();

        case 'AMAZON.CancelIntent':
        case 'AMAZON.StopIntent':
            (new AlexaResponse())->speak("Goodbye! Keep up the great work!")->send();

        default:
            // During onboarding, give step-specific re-prompts instead of a generic error
            if ($step === 'adding_chores') {
                $childName = $attrs['onboarding_child_name'] ?? 'your child';
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("I didn't catch that chore. Tell me a chore for " . ucfirst($childName) . ", or say done.")
                    ->reprompt("What chore would you like to add? Or say done.")
                    ->keepSessionOpen()->send();
            } elseif ($step === 'add_another_child') {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("Do you want to add another child? Say yes or no.")
                    ->reprompt("Say yes or no.")
                    ->keepSessionOpen()->send();
            } elseif ($step === 'child_name' || $step === 'start') {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("I didn't catch that. What's your child's name?")
                    ->reprompt("What's your child's name?")
                    ->keepSessionOpen()->send();
            } elseif ($step === 'picking_pet') {
                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak("Please say cat, dog, hamster, or panda.")
                    ->reprompt("Say cat, dog, hamster, or panda.")
                    ->keepSessionOpen()->send();
            }
            (new AlexaResponse())
                ->speak("I'm not sure how to help with that. Say a child's name to see their chores.")
                ->reprompt("Say a child's name to see their chores.")
                ->keepSessionOpen()->send();
    }
}

/**
 * Handle a Feed / Water / Play pet interaction.
 *
 * Called from both voice intents (FeedPetIntent etc.) and APL touch events
 * (feedPet / waterPet / playPet). The child row must already be loaded.
 *
 * If the pet is not Thriving, or the interaction was already used today,
 * explains why and refreshes the child view unchanged.
 */
function handlePetInteraction(string $type, array $child): never {
    global $aplSupported, $sessionAttrs;

    $labels  = ['feed' => 'fed', 'water' => 'watered', 'play' => 'played with'];
    $success = PetEngine::recordInteraction($child['id'], $type);

    if ($success) {
        $petName = $child['pet_name'] ?: ($child['name'] . "'s pet");
        $speech  = "You {$labels[$type]} {$petName}! They love that! 🐾";
    } else {
        $state = PetEngine::getState($child['id']);
        if ($state !== 'thriving') {
            $speech = "{$child['name']}'s pet needs to reach Thriving before you can unlock rewards. "
                    . "Keep completing chores to get there!";
        } else {
            $speech = "You've already done that today. Come back tomorrow for more!";
        }
    }

    $chores = ChoreManager::getChoresWithStatus($child['id']);
    $data   = AlexaResponse::buildChildDatasource($child, $chores);

    $r = (new AlexaResponse())
        ->speak($speech)
        ->setSessionAttr('active_child_id', $child['id'])
        ->keepSessionOpen();
    if ($aplSupported) {
        $r->renderApl('childView', AlexaResponse::loadApl('child-view'), $data);
    }
    $r->send();
}

/**
 * Show the pet selection screen (or prompt via voice if no screen).
 * Called from DoneAddingChoresIntent and from AMAZON.NoIntent when step === 'adding_chores'.
 * The child and their chores must already be committed to the DB before calling this.
 */
function showPetSelect(int $childId, string $childName, bool $aplSupported, array $sessionAttrs): never {
    $r = (new AlexaResponse())
        ->preserveSession($sessionAttrs)
        ->speak(sprintf(PROMPT_PICK_PET, ucfirst($childName)))
        ->reprompt("Say cat, dog, hamster, or panda.")
        ->setSessionAttr('onboarding_step',       'picking_pet')
        ->setSessionAttr('onboarding_child_id',   $childId)
        ->setSessionAttr('onboarding_child_name', $childName)
        ->keepSessionOpen();

    if ($aplSupported) {
        $r->renderApl(
            'petSelect',
            AlexaResponse::loadApl('pet-select'),
            AlexaResponse::buildPetSelectDatasource(ucfirst($childName))
        );
    }

    $r->send();
}

function handleAplEvent(array $args, array $household, array $attrs): never {
    global $aplSupported, $sessionAttrs;
    $action  = $args[0] ?? '';
    $childId = (int)($args[1] ?? 0);

    switch ($action) {

        case 'selectChild':
            $child = ChoreManager::getChild($childId);
            if ($child) showChildView($child);
            showHome(ChoreManager::getChildren($household['id']));

        case 'toggleChore':
            $choreId = (int)($args[2] ?? 0);
            $child  = ChoreManager::getChild($childId);
            if (!$child) showHome(ChoreManager::getChildren($household['id']));
            $done   = ChoreManager::toggleChore($choreId, $childId);
            $chores = ChoreManager::getChoresWithStatus($childId);
            $data   = AlexaResponse::buildChildDatasource($child, $chores);

            // Check if all chores now done → celebratory speech
            $progress = PetEngine::todayProgress($childId);
            $speech   = $done ? "Nice work!" : "Okay, unmarked.";
            if ($progress['done'] >= $progress['total'] && $progress['total'] > 0) {
                $petName = $child['pet_name'] ?? 'your pet';
                $speech  = "You finished all your chores! {$petName} is so happy!";
            }

            $r = (new AlexaResponse())
                ->preserveSession($sessionAttrs)
                ->speak($speech)
                ->keepSessionOpen();
            if ($aplSupported) {
                $r->renderApl('childView', AlexaResponse::loadApl('child-view'), $data);
            }
            $r->send();

        case 'goHome':
            showHome(ChoreManager::getChildren($household['id']));

        case 'feedPet':
        case 'waterPet':
        case 'playPet':
            // Tapped a Feed/Water/Play button on the child view
            $typeMap = ['feedPet' => 'feed', 'waterPet' => 'water', 'playPet' => 'play'];
            $child   = ChoreManager::getChild($childId);
            if (!$child) showHome(ChoreManager::getChildren($household['id']));
            // Temporarily inject active_child_id so handlePetInteraction can
            // rebuild the datasource with the correct session context.
            $sessionAttrs['active_child_id'] = $childId;
            handlePetInteraction($typeMap[$action], $child);

        case 'selectPet':
            // This fires when the user TAPS a pet card during onboarding
            $petType = $args[2] ?? '';
            if (!in_array($petType, PET_TYPES)) {
                showHome(ChoreManager::getChildren($household['id']));
            }

            $step = $attrs['onboarding_step'] ?? '';
            if ($step === 'picking_pet') {
                // During onboarding: use the child ID stored in the session
                $onboardingChildId = (int)($attrs['onboarding_child_id'] ?? 0);
                if ($onboardingChildId) ChoreManager::setPetType($onboardingChildId, $petType);

                (new AlexaResponse())
                    ->preserveSession($sessionAttrs)
                    ->speak(ucfirst($petType) . " it is! " . PROMPT_ADD_ANOTHER_CHILD)
                    ->reprompt("Do you want to add another child?")
                    ->setSessionAttr('onboarding_step', 'add_another_child')
                    ->keepSessionOpen()->send();
            }

            // Post-setup pet change: use childId from APL event argument
            if ($childId) ChoreManager::setPetType($childId, $petType);
            showHome(ChoreManager::getChildren($household['id']));

        default:
            showHome(ChoreManager::getChildren($household['id']));
    }
}


// ── Screen builders ───────────────────────────────────────────────────────────

/**
 * Returns a list of already-completed children (those with chores AND a non-default pet)
 * for display in the onboarding sidebar. Excludes the child currently being set up.
 */
function buildCompletedChildrenList(int $householdId, int $currentChildId): array {
    $all = ChoreManager::getChildren($householdId);
    $result = [];
    foreach ($all as $child) {
        if ($child['id'] === $currentChildId) continue;
        $chores = ChoreManager::getChores($child['id']);
        if (!empty($chores)) {
            $result[] = [
                'name'    => ucfirst($child['name']),
                'petType' => $child['pet_type'],
            ];
        }
    }
    return $result;
}

function showHome(array $children): never {
    global $aplSupported;
    if (empty($children)) {
        showOnboarding([], []);
    }
    $names = implode(' and ', array_column($children, 'name'));
    $r = (new AlexaResponse())
        ->speak("Welcome back! Whose chores would you like to see? {$names}?")
        ->reprompt("Say a child's name.")
        ->keepSessionOpen();
    if ($aplSupported) {
        $r->renderApl(
            'home',
            AlexaResponse::loadApl('home'),
            AlexaResponse::buildHomeDatasource($children)
        );
    }
    $r->send();
}

function showChildView(array $child): never {
    global $aplSupported;
    $chores   = ChoreManager::getChoresWithStatus($child['id']);
    $progress = PetEngine::todayProgress($child['id']);

    $speech = "{$child['name']}'s chores. {$progress['done']} of {$progress['total']} done today.";

    $r = (new AlexaResponse())
        ->speak($speech)
        ->reprompt("Tap a chore to mark it complete, or say 'go back' to go home.")
        ->keepSessionOpen();
    if ($aplSupported) {
        $data   = AlexaResponse::buildChildDatasource($child, $chores);
        if ($data['childData']['namingUnlocked']) {
            $r->speak($speech . " You've earned the right to name your pet! Say 'name my pet' followed by a name!");
        }
        $r->renderApl('childView', AlexaResponse::loadApl('child-view'), $data);
    }
    $r->send();
}

function showOnboarding(array $household, array $attrs): never {
    global $aplSupported;
    $r = (new AlexaResponse())
        ->speak(PROMPT_WELCOME_NEW)
        ->reprompt("What's your first child's name?")
        ->setSessionAttr('onboarding_step', 'child_name')
        ->keepSessionOpen();
    if ($aplSupported) {
        $r->renderApl('onboarding', AlexaResponse::loadApl('onboarding'), [
            'onboardingData' => [
                'step'      => 'start',
                'assetsUrl' => ASSETS_URL,
            ]
        ]);
    }
    $r->send();
}

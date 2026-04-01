<?php
/**
 * AlexaResponse – Builds valid Alexa JSON response objects.
 *
 * Usage:
 *   $r = new AlexaResponse();
 *   $r->speak("Hello!")
 *     ->reprompt("What would you like to do?")
 *     ->renderApl('home', $aplDoc, $datasources)
 *     ->keepSessionOpen()
 *     ->send();
 */
class AlexaResponse {

    private array $response = [
        'version'    => '1.0',
        'sessionAttributes' => [],
        'response'   => [
            'directives'       => [],
            'shouldEndSession' => true,
        ],
    ];

    // ── Speech ─────────────────────────────────────────────────────────────────

    public function speak(string $text): self {
        // Wrap plain text in SSML
        $ssml = strpos($text, '<speak>') === 0 ? $text : "<speak>{$text}</speak>";
        $this->response['response']['outputSpeech'] = [
            'type' => 'SSML',
            'ssml' => $ssml,
        ];
        return $this;
    }

    public function reprompt(string $text): self {
        $ssml = strpos($text, '<speak>') === 0 ? $text : "<speak>{$text}</speak>";
        $this->response['response']['reprompt'] = [
            'outputSpeech' => ['type' => 'SSML', 'ssml' => $ssml],
        ];
        return $this;
    }

    // ── Session ────────────────────────────────────────────────────────────────

    public function keepSessionOpen(): self {
        $this->response['response']['shouldEndSession'] = false;
        return $this;
    }

    public function endSession(): self {
        $this->response['response']['shouldEndSession'] = true;
        return $this;
    }

    public function setSessionAttr(string $key, mixed $value): self {
        $this->response['sessionAttributes'][$key] = $value;
        return $this;
    }

    /**
     * Carry forward ALL existing session attributes that haven't been explicitly overwritten.
     * Call this on every response to prevent Alexa from wiping session state between turns.
     *
     * @param array $existingAttrs The full $sessionAttrs array from the incoming request
     */
    public function preserveSession(array $existingAttrs): self {
        foreach ($existingAttrs as $k => $v) {
            if (!array_key_exists($k, $this->response['sessionAttributes'])) {
                $this->response['sessionAttributes'][$k] = $v;
            }
        }
        return $this;
    }

    // ── APL ────────────────────────────────────────────────────────────────────

    /**
     * Render an APL document loaded from the apl/ directory.
     *
     * @param string $token       Unique token (e.g. 'home', 'childView')
     * @param array  $document    Decoded APL document JSON
     * @param array  $datasources Data injected into the APL template
     */
    public function renderApl(string $token, array $document, array $datasources = []): self {
        $this->response['response']['directives'][] = [
            'type'        => 'Alexa.Presentation.APL.RenderDocument',
            'token'       => $token,
            'document'    => $document,
            'datasources' => $datasources,
        ];
        return $this;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** Load APL JSON from the apl/ directory adjacent to this file. */
    public static function loadApl(string $name): array {
        $path = __DIR__ . "/../apl/{$name}.json";
        if (!file_exists($path)) {
            throw new RuntimeException("APL document not found: {$name}");
        }
        return json_decode(file_get_contents($path), true);
    }

    // ── Build datasource helpers ───────────────────────────────────────────────

    /**
     * Build the datasource for the home screen (list of children + their progress).
     */
    public static function buildHomeDatasource(array $children): array {
        $cards = [];
        foreach ($children as $child) {
            $state    = PetEngine::getState($child['id']);
            $progress = PetEngine::todayProgress($child['id']);
            $cards[]  = [
                'childId'   => $child['id'],
                'name'      => $child['name'],
                'petType'   => $child['pet_type'],
                'petName'   => $child['pet_name'] ?? '',
                'petImage'  => PetEngine::getImageUrl($child['pet_type'], $state),
                'petState'  => $state,
                'stateLabel'=> PetEngine::getStateLabel($state),
                'doneCount' => $progress['done'],
                'totalCount'=> $progress['total'],
                'allDone'   => $progress['done'] >= $progress['total'] && $progress['total'] > 0,
            ];
        }
        return ['homeData' => ['children' => $cards]];
    }

    /**
     * Build the datasource for the child view (chore list + pet).
     */
    public static function buildChildDatasource(array $child, array $chores): array {
        $state     = PetEngine::getState($child['id']);
        $progress  = PetEngine::todayProgress($child['id']);
        $naming    = PetEngine::checkNamingEligibility($child['id']);

        $choreList = [];
        foreach ($chores as $chore) {
            $choreList[] = [
                'id'        => $chore['id'],
                'name'      => $chore['name'],
                'done'      => (bool)$chore['completed_today'],
            ];
        }

        return [
            'childData' => [
                'childId'        => $child['id'],
                'name'           => $child['name'],
                'petType'        => $child['pet_type'],
                'petName'        => $child['pet_name'] ?? '',
                'petImage'       => PetEngine::getImageUrl($child['pet_type'], $state),
                'petState'       => $state,
                'stateLabel'     => PetEngine::getStateLabel($state),
                'namingUnlocked' => $naming && empty($child['pet_name']),
                'doneCount'      => $progress['done'],
                'totalCount'     => $progress['total'],
                'chores'         => $choreList,
            ],
        ];
    }

    /**
     * Build the datasource for the dynamic onboarding screen.
     *
     * @param string $step             Current onboarding step ('start' | 'adding_chores')
     * @param string $childName        Name of the child currently being set up
     * @param array  $chores           Chore names saved so far for this child
     * @param array  $childrenComplete Already-finished children: [['name'=>..,'petType'=>..], ...]
     */
    public static function buildOnboardingDatasource(
        string $step,
        string $childName = '',
        array  $chores = [],
        array  $childrenComplete = []
    ): array {
        return [
            'onboardingData' => [
                'step'             => $step,
                'assetsUrl'        => ASSETS_URL,
                'childName'        => ucfirst($childName),
                'chores'           => array_map('ucfirst', $chores),
                'choreCount'       => count($chores),
                'childrenComplete' => $childrenComplete,
            ],
        ];
    }

    /**
     * Build the datasource for the pet selection screen.
     */
    public static function buildPetSelectDatasource(string $childName): array {
        $pets = [];
        foreach (PET_TYPES as $type) {
            $pets[] = [
                'type'  => $type,
                'label' => ucfirst($type),
                'image' => PetEngine::getImageUrl($type, 'happy'),
            ];
        }
        return ['petSelectData' => ['childName' => $childName, 'pets' => $pets]];
    }

    // ── Output ─────────────────────────────────────────────────────────────────

    public function send(): never {
        ob_end_clean(); // discard any stray output (PHP notices, warnings, etc.)
        header('Content-Type: application/json');
        echo json_encode($this->response);
        exit;
    }

    public function toArray(): array {
        return $this->response;
    }
}

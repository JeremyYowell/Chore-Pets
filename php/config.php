<?php
/**
 * Commit-safe configuration shim.
 *
 * `config.local.php` should return an associative array of real values and is
 * intentionally left out of version control. Returning early here preserves the
 * legacy content below without evaluating the hardcoded values.
 */
$localConfigPath = __DIR__ . '/config.local.php';
$localConfig = [];

if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (!is_array($localConfig)) {
        throw new RuntimeException('config.local.php must return an array.');
    }
}

define('ALEXA_SKILL_ID', $localConfig['ALEXA_SKILL_ID'] ?? 'amzn1.ask.skill.REPLACE-ME');
define('VERIFY_SIGNATURES', $localConfig['VERIFY_SIGNATURES'] ?? false);
define('DB_HOST', $localConfig['DB_HOST'] ?? 'mysql.yourdomain.com');
define('DB_NAME', $localConfig['DB_NAME'] ?? 'chore_champion');
define('DB_USER', $localConfig['DB_USER'] ?? 'your_db_user');
define('DB_PASS', $localConfig['DB_PASS'] ?? 'your_db_password');
define('ASSETS_URL', $localConfig['ASSETS_URL'] ?? 'https://yourdomain.com/chores/assets');

define('PET_TYPES', ['cat', 'dog', 'hamster', 'panda']);

// ── Pet Interactions ───────────────────────────────────────────────────────────
// The three daily interactions available when the pet is in 'thriving' state.
define('PET_INTERACTION_TYPES', ['feed', 'water', 'play']);

define('PET_STATE_THRESHOLDS', [
    'thriving' => 90,
    'happy' => 70,
    'neutral' => 50,
    'sad' => 25,
    'sick' => 0,
]);
define('NAMING_STREAK_DAYS', 7);

define(
    'PROMPT_WELCOME_NEW',
    "Welcome to Chore Pets! Let's get your family set up. First, what's the name of your first child?"
);
define(
    'PROMPT_ADD_CHORES',
    "Great! Now tell me a chore for %s. Say something like, 'Make your bed' or 'Brush your teeth'. Say 'done' when you're finished."
);
define(
    'PROMPT_PICK_PET',
    "Awesome! Now %s needs to pick a starting pet. Say cat, dog, hamster, or panda."
);
define(
    'PROMPT_ADD_ANOTHER_CHILD',
    "Do you want to add another child? Say yes to add one, or no if you're all set."
);

$missingConfigIssues = [];

if (!is_file($localConfigPath)) {
    $missingConfigIssues[] = 'config.local.php is missing';
}

if (ALEXA_SKILL_ID === 'amzn1.ask.skill.REPLACE-ME') {
    $missingConfigIssues[] = 'ALEXA_SKILL_ID is not configured';
}

if (DB_HOST === 'mysql.yourdomain.com') {
    $missingConfigIssues[] = 'DB_HOST is still using the placeholder value';
}

if (DB_NAME === 'chore_champion') {
    $missingConfigIssues[] = 'DB_NAME is still using the placeholder value';
}

if (DB_USER === 'your_db_user') {
    $missingConfigIssues[] = 'DB_USER is still using the placeholder value';
}

if (DB_PASS === 'your_db_password') {
    $missingConfigIssues[] = 'DB_PASS is still using the placeholder value';
}

if (ASSETS_URL === 'https://yourdomain.com/chores/assets') {
    $missingConfigIssues[] = 'ASSETS_URL is still using the placeholder value';
}

if ($missingConfigIssues) {
    throw new RuntimeException('Missing runtime configuration: ' . implode('; ', $missingConfigIssues));
}

__halt_compiler();
/*
/**
 * Chore Pets – Configuration
 * ─────────────────────────────────────────────────────────────────────────────
 * Fill in your values before deploying to Dreamhost.
 * Keep this file outside your web root if possible, or restrict it in .htaccess.
 */

// ── Alexa Skill Settings ──────────────────────────────────────────────────────
// Your Skill ID from the Alexa Developer Console (starts with amzn1.ask.skill.)
define('ALEXA_SKILL_ID', 'amzn1.ask.skill.REPLACE-ME');

// Set to true in production to verify Alexa request signatures.
// Leave false during development/testing.
define('VERIFY_SIGNATURES', false);

// ── Database ──────────────────────────────────────────────────────────────────
define('DB_HOST', 'mysql.yourdomain.com');
define('DB_NAME', 'chore_champion');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// ── Asset Base URL ────────────────────────────────────────────────────────────
// The public HTTPS URL where your assets folder is served from.
// Alexa requires HTTPS for all image URLs in APL.
define('ASSETS_URL', 'https://yourdomain.com/chores/assets');

// ── Pet Types ─────────────────────────────────────────────────────────────────
define('PET_TYPES', ['cat', 'dog', 'hamster', 'panda']);

// ── Pet States ────────────────────────────────────────────────────────────────
// Thresholds: average completion % over last 3 days → pet state
define('PET_STATE_THRESHOLDS', [
    'thriving' => 90,   // 90-100%
    'happy'    => 70,   // 70-89%
    'neutral'  => 50,   // 50-69%
    'sad'      => 25,   // 25-49%
    'sick'     => 0,    // 0-24%
]);

// ── Naming Mechanic ───────────────────────────────────────────────────────────
// Number of consecutive 100%-complete days required to unlock pet naming.
define('NAMING_STREAK_DAYS', 7);

// ── Onboarding Voice Prompts ──────────────────────────────────────────────────
define('PROMPT_WELCOME_NEW',
    "Welcome to Chore Pets! Let's get your family set up. " .
    "First, what's the name of your first child?");

define('PROMPT_ADD_CHORES',
    "Great! Now tell me a chore for %s. Say something like, " .
    "'Make your bed' or 'Brush your teeth'. Say 'done' when you're finished.");

define('PROMPT_PICK_PET',
    "Awesome! Now %s needs to pick a starting pet. " .
    "Say cat, dog, hamster, or panda.");

define('PROMPT_ADD_ANOTHER_CHILD',
    "Do you want to add another child? Say yes to add one, or no if you're all set.");
*/

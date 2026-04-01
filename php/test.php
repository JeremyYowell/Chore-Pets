<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Minimal diagnostic – delete this file after debugging
header('Content-Type: application/json');

$result = [
    'php_version'    => PHP_VERSION,
    'sapi'           => PHP_SAPI,
    'display_errors' => ini_get('display_errors'),
];

// Test 1: Can we load config?
try {
    require_once __DIR__ . '/config.php';
    $result['config'] = 'ok';
} catch (Throwable $e) {
    $result['config'] = $e->getMessage();
}

// Test 2: Can we connect to the DB?
try {
    require_once __DIR__ . '/lib/Database.php';
    $db = Database::get();
    $result['db'] = 'connected';
} catch (Throwable $e) {
    $result['db'] = $e->getMessage();
}

// Test 3: Can we load all lib files?
try {
    require_once __DIR__ . '/lib/PetEngine.php';
    require_once __DIR__ . '/lib/ChoreManager.php';
    require_once __DIR__ . '/lib/AlexaResponse.php';
    $result['libs'] = 'ok';
} catch (Throwable $e) {
    $result['libs'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);

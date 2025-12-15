<?php
// MRS Central Router (v3 - Corrected)

define('MRS_ENTRY', true);

require_once __DIR__ . '/bootstrap.php';

// Routing Logic
$action = $_GET['action'] ?? null;

if ($action === null) {
    // Determine default action based on the entry script path
    $script_path = $_SERVER['SCRIPT_NAME'];
    // Backend path contains '/be/', frontend does not
    if (strpos($script_path, '/be/') !== false) {
        $action = 'dashboard'; // Backend default
    } else {
        $action = 'quick_receipt'; // Frontend default
    }
}

$action = basename($action);

// Build a whitelist directly from the actions directory to avoid missing registrations
$allowed_actions = array_map(
    function ($file_path) {
        return pathinfo($file_path, PATHINFO_FILENAME);
    },
    glob(MRS_ACTION_PATH . '/*.php') ?: []
);

$action_file = MRS_ACTION_PATH . '/' . $action . '.php';
$api_file = MRS_API_PATH . '/' . $action . '.php';

// Dynamic routing for backend_ API calls (prefer API file if it exists)
if (strpos($action, 'backend_') === 0 && file_exists($api_file)) {
    require_once $api_file;
    exit;
}

// Check whitelist for regular actions
if (!in_array($action, $allowed_actions, true)) {
    mrs_log("Disallowed action requested: {$action}", 'WARNING');
    $action = 'dashboard'; // Default to a safe page
    $action_file = MRS_ACTION_PATH . '/dashboard.php';
}

if (file_exists($action_file)) {
    require_once $action_file;
} else {
    header("HTTP/1.0 404 Not Found");
    mrs_log("Action file not found: {$action_file}", 'ERROR');
    die("Error 404: Action '{$action}' not found.");
}

<?php
/**
 * MRS System Status API
 * Route: api.php?route=backend_system_status
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// Require Admin Login
require_login();

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = get_db_connection();
    $issues = [];
    $migration_needed = false;

    // Check 001_add_input_sku_name_to_raw_record
    $checkSql001 = "SHOW COLUMNS FROM mrs_batch_raw_record LIKE 'input_sku_name'";
    $stmt001 = $pdo->query($checkSql001);
    if ($stmt001->rowCount() === 0) {
        $issues[] = "Database schema outdated: Missing 'input_sku_name' column.";
        $migration_needed = true;
    }

    // Check 002_create_outbound_tables
    $checkSql002 = "SHOW TABLES LIKE 'mrs_outbound_order'";
    $stmt002 = $pdo->query($checkSql002);
    if ($stmt002->rowCount() === 0) {
        $issues[] = "Database schema outdated: Missing 'mrs_outbound_order' table.";
        $migration_needed = true;
    }

    // Check 003_add_physical_box_count_to_raw_record
    $checkSql003 = "SHOW COLUMNS FROM mrs_batch_raw_record LIKE 'physical_box_count'";
    $stmt003 = $pdo->query($checkSql003);
    if ($stmt003->rowCount() === 0) {
        $issues[] = "Database schema outdated: Missing 'physical_box_count' column.";
        $migration_needed = true;
    }

    json_response(true, [
        'healthy' => empty($issues),
        'migration_needed' => $migration_needed,
        'issues' => $issues
    ], 'Status checked');

} catch (Exception $e) {
    json_response(false, null, $e->getMessage());
}

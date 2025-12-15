<?php
/**
 * MRS System Fix API
 * Route: api.php?route=backend_system_fix
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST allowed');
    }

    $pdo = get_db_connection();
    $messages = [];

    // Define migrations
    $migrations = [
        '001' => [
            'check' => "SHOW COLUMNS FROM mrs_batch_raw_record LIKE 'input_sku_name'",
            'file' => '001_add_input_sku_name_to_raw_record.sql'
        ],
        '002' => [
            'check' => "SHOW TABLES LIKE 'mrs_outbound_order'",
            'file' => '002_create_outbound_tables.sql'
        ],
        '003' => [
            'check' => "SHOW COLUMNS FROM mrs_batch_raw_record LIKE 'physical_box_count'",
            'file' => '003_add_physical_box_count_to_raw_record.sql'
        ]
    ];

    foreach ($migrations as $key => $migration) {
        $checkStmt = $pdo->query($migration['check']);
        if ($checkStmt->rowCount() === 0) {
            $migrationFile = MRS_APP_PATH . '/../docs/migrations/' . $migration['file'];

            if (file_exists($migrationFile)) {
                $sqlContent = file_get_contents($migrationFile);
                // Simple splitter, caution with ; in strings
                $statements = explode(';', $sqlContent);

                foreach ($statements as $sql) {
                    $sql = trim($sql);
                    if (empty($sql) || strpos($sql, '--') === 0) continue;
                    try {
                        $pdo->exec($sql);
                    } catch (Exception $e) {
                        mrs_log("Migration {$key} partial error: " . $e->getMessage(), 'WARNING');
                    }
                }
                $messages[] = "Applied migration: {$key}";
            } else {
                $messages[] = "Migration file {$key} missing";
            }
        } else {
            $messages[] = "Migration {$key} already applied";
        }
    }

    json_response(true, ['messages' => $messages], 'System fix applied successfully');

} catch (Exception $e) {
    mrs_log('System fix failed: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, $e->getMessage());
}

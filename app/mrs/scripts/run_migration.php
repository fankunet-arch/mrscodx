<?php
// app/mrs/scripts/run_migration.php
define('MRS_ENTRY', true);
require_once __DIR__ . '/../config_mrs/env_mrs.php';

echo "Running migration...\n";

try {
    $pdo = get_db_connection();
    $sql = file_get_contents(__DIR__ . '/../migrations/002_create_outbound_tables.sql');

    // Split by semicolon (simple approach, watch out for semicolons in strings if any)
    // The migration file only contains table creations which are safe.
    // However, CREATE TRIGGER or PROCEDURE might have semicolons.
    // For this simple migration, explode is fine.

    $statements = explode(';', $sql);

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) continue;

        echo "Executing: " . substr($stmt, 0, 50) . "...\n";
        $pdo->exec($stmt);
    }

    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

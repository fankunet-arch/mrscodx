<?php
// app/mrs/api/process_confirmed_item.php

// Define entry point constant for security
define('MRS_ENTRY', true);

// Load MRS configuration and libraries
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

header('Content-Type: application/json');

// Helper function for sending JSON responses
function send_json_response($success, $message, $data = null)
{
    echo json_encode(['success' => (bool)$success, 'message' => $message, 'data' => $data]);
    exit;
}

// Check for required POST variables
$action = $_POST['action'] ?? null;
$batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
$sku_id = filter_input(INPUT_POST, 'sku_id', FILTER_VALIDATE_INT);

if (!$action || !$batch_id || !$sku_id) {
    send_json_response(false, 'Invalid input parameters.');
}

$pdo = get_db_connection();

try {
    $pdo->beginTransaction();

    // Fetch SKU conversion info first
    $sku_sql = "SELECT case_to_standard_qty FROM mrs_sku WHERE sku_id = :sku_id";
    $sku_stmt = $pdo->prepare($sku_sql);
    $sku_stmt->execute([':sku_id' => $sku_id]);
    $sku_info = $sku_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sku_info) {
        throw new Exception('SKU not found.');
    }
    $case_conversion_rate = $sku_info['case_to_standard_qty'];


    if ($action === 'confirm') {
        $case_qty = filter_input(INPUT_POST, 'case_qty', FILTER_VALIDATE_INT);
        $single_qty = filter_input(INPUT_POST, 'single_qty', FILTER_VALIDATE_INT);

        if ($case_qty === false || $case_qty < 0 || $single_qty === false || $single_qty < 0) {
            throw new Exception('Invalid quantity values.');
        }

        $total_standard_qty = ($case_qty * $case_conversion_rate) + $single_qty;

        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle existing confirmed items
        $confirm_sql = "
            INSERT INTO mrs_batch_confirmed_item
                (batch_id, sku_id, confirmed_case_qty, confirmed_single_qty, total_standard_qty, created_at, updated_at)
            VALUES
                (:batch_id, :sku_id, :case_qty, :single_qty, :total_qty, NOW(6), NOW(6))
            ON DUPLICATE KEY UPDATE
                confirmed_case_qty = VALUES(confirmed_case_qty),
                confirmed_single_qty = VALUES(confirmed_single_qty),
                total_standard_qty = VALUES(total_standard_qty),
                updated_at = NOW(6)
        ";
        $confirm_stmt = $pdo->prepare($confirm_sql);
        $confirm_stmt->execute([
            ':batch_id' => $batch_id,
            ':sku_id' => $sku_id,
            ':case_qty' => $case_qty,
            ':single_qty' => $single_qty,
            ':total_qty' => $total_standard_qty
        ]);

    } elseif ($action !== 'delete') {
        throw new Exception('Invalid action specified.');
    }

    // For both 'confirm' and 'delete', remove the raw records for this SKU in this batch
    $delete_raw_sql = "DELETE FROM mrs_batch_raw_record WHERE batch_id = :batch_id AND sku_id = :sku_id";
    $delete_raw_stmt = $pdo->prepare($delete_raw_sql);
    $delete_raw_stmt->execute([':batch_id' => $batch_id, ':sku_id' => $sku_id]);


    // Check if any raw records are left for this batch
    $check_sql = "SELECT COUNT(*) FROM mrs_batch_raw_record WHERE batch_id = :batch_id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':batch_id' => $batch_id]);
    $remaining_raw_count = $check_stmt->fetchColumn();

    $batch_status_updated = false;
    if ($remaining_raw_count == 0) {
        // All items processed, update batch status to 'confirmed'
        $update_batch_sql = "UPDATE mrs_batch SET batch_status = 'confirmed' WHERE batch_id = :batch_id";
        $update_batch_stmt = $pdo->prepare($update_batch_sql);
        $update_batch_stmt->execute([':batch_id' => $batch_id]);
        $batch_status_updated = true;
    }

    $pdo->commit();

    send_json_response(true, 'Action completed successfully.', ['batch_status_updated' => $batch_status_updated]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    send_json_response(false, 'An error occurred: ' . $e->getMessage());
}

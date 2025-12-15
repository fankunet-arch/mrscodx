<?php
/**
 * MRS Backend API: Process Confirmed Item (Confirm or Delete)
 * File: app/mrs/api/backend_process_confirmed_item.php
 *
 * IMPORTANT CHANGES:
 * - Records are MARKED (not deleted) with processing_status
 * - Inventory transactions are recorded for traceability
 * - Supports both 'confirm' and 'delete' actions
 */

// Define entry point constant for security
if (!defined('MRS_ENTRY')) {
    define('MRS_ENTRY', true);
}

// Load MRS configuration and libraries
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';
require_once MRS_LIB_PATH . '/inventory_lib.php';

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

if (!in_array($action, ['confirm', 'delete'])) {
    send_json_response(false, 'Invalid action specified.');
}

$pdo = get_db_connection();

try {
    $pdo->beginTransaction();

    // Fetch SKU info
    $sku_sql = "SELECT sku_id, sku_name, standard_unit, case_to_standard_qty FROM mrs_sku WHERE sku_id = :sku_id";
    $sku_stmt = $pdo->prepare($sku_sql);
    $sku_stmt->execute([':sku_id' => $sku_id]);
    $sku_info = $sku_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sku_info) {
        throw new Exception('SKU not found.');
    }

    $case_conversion_rate = floatval($sku_info['case_to_standard_qty'] ?? 0);
    $standard_unit = $sku_info['standard_unit'];

    // [FIX] Dynamic Spec Logic
    // Query pending raw records to check for physical box count override
    // This allows confirmation to use the actual repacked specification instead of factory spec
    // MUST calculate standardized total quantity to handle mixed units (e.g. input in Boxes vs Pieces)
    $dynamic_check_sql = "SELECT
                            SUM(
                                CASE
                                    WHEN r.unit_name = s.case_unit_name AND s.case_to_standard_qty > 0
                                    THEN r.qty * s.case_to_standard_qty
                                    ELSE r.qty
                                END
                            ) as total_qty,
                            SUM(r.physical_box_count) as total_boxes
                          FROM mrs_batch_raw_record r
                          LEFT JOIN mrs_sku s ON r.sku_id = s.sku_id
                          WHERE r.batch_id = :batch_id AND r.sku_id = :sku_id AND r.processing_status = 'pending'";
    $dynamic_stmt = $pdo->prepare($dynamic_check_sql);
    $dynamic_stmt->execute([':batch_id' => $batch_id, ':sku_id' => $sku_id]);
    $dynamic_row = $dynamic_stmt->fetch(PDO::FETCH_ASSOC);

    if ($dynamic_row) {
        $pending_total_qty = floatval($dynamic_row['total_qty']);
        $pending_total_boxes = floatval($dynamic_row['total_boxes']);

        if ($pending_total_boxes > 0 && $pending_total_qty > 0) {
             // Calculate dynamic coefficient (e.g., 1000 pcs / 5 boxes = 200/box)
             $case_conversion_rate = $pending_total_qty / $pending_total_boxes;
             mrs_log("Using dynamic spec for confirmation: Batch={$batch_id}, SKU={$sku_id}, Spec={$case_conversion_rate} (Total={$pending_total_qty}/Boxes={$pending_total_boxes})", 'INFO');
        }
    }

    // ============================================================
    // Action: CONFIRM
    // ============================================================
    if ($action === 'confirm') {
        // [FIX] Support float/decimal values for box counts (e.g. 1.5 boxes)
        $case_qty = filter_input(INPUT_POST, 'case_qty', FILTER_VALIDATE_FLOAT);
        $single_qty = filter_input(INPUT_POST, 'single_qty', FILTER_VALIDATE_FLOAT);

        if ($case_qty === false || $case_qty < 0 || $single_qty === false || $single_qty < 0) {
            throw new Exception('Invalid quantity values.');
        }

        $total_standard_qty = ($case_qty * $case_conversion_rate) + $single_qty;

        // Insert or accumulate confirmed quantities so multiple receipts in the same batch remain independent
        $confirm_sql = "
            INSERT INTO mrs_batch_confirmed_item
                (batch_id, sku_id, confirmed_case_qty, confirmed_single_qty, total_standard_qty, created_at, updated_at)
            VALUES
                (:batch_id, :sku_id, :case_qty, :single_qty, :total_qty, NOW(6), NOW(6))
            ON DUPLICATE KEY UPDATE
                confirmed_case_qty = COALESCE(confirmed_case_qty, 0) + VALUES(confirmed_case_qty),
                confirmed_single_qty = COALESCE(confirmed_single_qty, 0) + VALUES(confirmed_single_qty),
                total_standard_qty = COALESCE(total_standard_qty, 0) + VALUES(total_standard_qty),
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

        // Mark raw records as 'confirmed' (NOT DELETE)
        $update_status_sql = "UPDATE mrs_batch_raw_record
                              SET processing_status = 'confirmed', updated_at = NOW(6)
                              WHERE batch_id = :batch_id AND sku_id = :sku_id AND processing_status = 'pending'";
        $update_status_stmt = $pdo->prepare($update_status_sql);
        $update_status_stmt->execute([':batch_id' => $batch_id, ':sku_id' => $sku_id]);

        // Record inventory transaction (inbound)
        $operator_name = $_SESSION['user_display_name'] ?? 'System';
        $remark = "批次 #{$batch_id} 确认入库";

        record_inventory_transaction(
            $pdo,
            $sku_id,
            'inbound',
            'batch_receipt',
            $total_standard_qty,
            $standard_unit,
            $operator_name,
            ['batch_id' => $batch_id],
            $remark
        );

        mrs_log("Receipt confirmed: Batch={$batch_id}, SKU={$sku_id}, Qty={$total_standard_qty}", 'INFO');
    }

    // ============================================================
    // Action: DELETE
    // ============================================================
    elseif ($action === 'delete') {
        // Mark raw records as 'deleted' (NOT DELETE)
        $update_status_sql = "UPDATE mrs_batch_raw_record
                              SET processing_status = 'deleted', updated_at = NOW(6)
                              WHERE batch_id = :batch_id AND sku_id = :sku_id AND processing_status = 'pending'";
        $update_status_stmt = $pdo->prepare($update_status_sql);
        $update_status_stmt->execute([':batch_id' => $batch_id, ':sku_id' => $sku_id]);

        // Remove confirmed item if exists (since user chose to delete instead of confirm)
        $delete_confirmed_sql = "DELETE FROM mrs_batch_confirmed_item WHERE batch_id = :batch_id AND sku_id = :sku_id";
        $delete_confirmed_stmt = $pdo->prepare($delete_confirmed_sql);
        $delete_confirmed_stmt->execute([':batch_id' => $batch_id, ':sku_id' => $sku_id]);

        mrs_log("Receipt deleted: Batch={$batch_id}, SKU={$sku_id}", 'INFO');
    }

    // ============================================================
    // Check if all raw records are processed
    // ============================================================
    $check_sql = "SELECT COUNT(*) FROM mrs_batch_raw_record
                  WHERE batch_id = :batch_id AND processing_status = 'pending'";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':batch_id' => $batch_id]);
    $remaining_pending_count = $check_stmt->fetchColumn();

    $batch_status_updated = false;
    if ($remaining_pending_count == 0) {
        // All items processed, update batch status to 'confirmed'
        $update_batch_sql = "UPDATE mrs_batch SET batch_status = 'confirmed', updated_at = NOW(6) WHERE batch_id = :batch_id";
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
    mrs_log('Process confirmed item failed: ' . $e->getMessage(), 'ERROR');
    send_json_response(false, 'An error occurred: ' . $e->getMessage());
}

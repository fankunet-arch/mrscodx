<?php
/**
 * MRS Outbound Management - Confirm Outbound Order
 * Route: api.php?route=backend_confirm_outbound
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// Require Login
require_login();

try {
    $input = get_json_input();
    $orderId = $input['order_id'] ?? null;

    if (!$orderId) {
        json_response(false, null, 'Missing order_id');
    }

    $pdo = get_db_connection();

    // Start Transaction
    $pdo->beginTransaction();

    // Check Status
    $sql = "SELECT status FROM mrs_outbound_order WHERE outbound_order_id = :order_id FOR UPDATE";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
    $status = $stmt->fetchColumn();

    if ($status !== 'draft') {
        $pdo->rollBack();
        json_response(false, null, 'Order is not in draft status');
    }

    // Validate Inventory (Optional but good practice)
    // For now, Phase 2 Requirements do not strictly forbid negative inventory ("allow imperfect"),
    // but we should at least Calculate it.
    // The requirement says: "Confirm... then only produce inventory deduction effect".
    // It doesn't explicitly say "Block if insufficient inventory".
    // I will proceed with confirmation.

    $updateSql = "UPDATE mrs_outbound_order SET status = 'confirmed', updated_at = NOW(6) WHERE outbound_order_id = :order_id";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $updateStmt->execute();

    $pdo->commit();

    mrs_log("Outbound order confirmed: {$orderId}", 'INFO');

    json_response(true, null, 'Order confirmed successfully');

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mrs_log('Confirm outbound failed: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, $e->getMessage());
}

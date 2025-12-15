<?php
/**
 * MRS Outbound Management - Get Outbound Order Detail
 * Route: api.php?route=backend_outbound_detail
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// Require Login
require_login();

try {
    $orderId = $_GET['order_id'] ?? null;
    if (!$orderId) {
        json_response(false, null, 'Missing order_id');
    }

    $pdo = get_db_connection();

    // Header
    $sql = "SELECT * FROM mrs_outbound_order WHERE outbound_order_id = :order_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch();

    if (!$order) {
        json_response(false, null, 'Order not found');
    }

    // Items
    $sqlItems = "SELECT * FROM mrs_outbound_order_item WHERE outbound_order_id = :order_id ORDER BY outbound_order_item_id ASC";
    $stmtItems = $pdo->prepare($sqlItems);
    $stmtItems->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmtItems->execute();
    $items = $stmtItems->fetchAll();

    $order['items'] = $items;

    json_response(true, $order);

} catch (Exception $e) {
    mrs_log('Get outbound detail failed: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, $e->getMessage());
}

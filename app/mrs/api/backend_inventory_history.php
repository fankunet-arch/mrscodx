<?php
/**
 * MRS Backend API: 获取物料历史进出库记录
 * Route: backend_inventory_history
 *
 * UPDATED: Now uses mrs_inventory_transaction table for complete traceability
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// Require Login
require_login();

try {
    $pdo = get_db_connection();
    $sku_id = $_GET['sku_id'] ?? null;

    if (!$sku_id) {
        json_response(false, null, 'SKU ID缺失');
        exit;
    }

    // Check if mrs_inventory_transaction table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'mrs_inventory_transaction'")->fetchAll();
    $history = [];

    if (count($table_check) > 0) {
        // NEW LOGIC: Use mrs_inventory_transaction table for complete history
        $stmt = $pdo->prepare("
            SELECT
                DATE_FORMAT(t.transaction_date, '%Y-%m-%d %H:%i:%s') as date,
                CASE
                    WHEN t.transaction_type = 'inbound' AND t.transaction_subtype = 'batch_receipt' THEN '入库'
                    WHEN t.transaction_type = 'outbound' AND t.transaction_subtype = 'picking' THEN '出库'
                    WHEN t.transaction_type = 'adjustment' AND t.transaction_subtype = 'surplus' THEN '盘点-盘盈'
                    WHEN t.transaction_type = 'adjustment' AND t.transaction_subtype = 'deficit' THEN '盘点-盘亏'
                    WHEN t.transaction_type = 'inbound' THEN '入库'
                    WHEN t.transaction_type = 'outbound' THEN '出库'
                    ELSE '调整'
                END as type,
                CASE
                    WHEN t.batch_id IS NOT NULL THEN (SELECT batch_code FROM mrs_batch WHERE batch_id = t.batch_id)
                    WHEN t.outbound_order_id IS NOT NULL THEN (SELECT outbound_code FROM mrs_outbound_order WHERE outbound_order_id = t.outbound_order_id)
                    ELSE NULL
                END as code,
                t.quantity_change as qty,
                t.quantity_after as qty_after,
                CASE
                    WHEN t.batch_id IS NOT NULL THEN (SELECT location_name FROM mrs_batch WHERE batch_id = t.batch_id)
                    WHEN t.outbound_order_id IS NOT NULL THEN (SELECT location_name FROM mrs_outbound_order WHERE outbound_order_id = t.outbound_order_id)
                    ELSE NULL
                END as location,
                t.remark,
                t.operator_name,
                t.transaction_id as source_id
            FROM mrs_inventory_transaction t
            WHERE t.sku_id = ?
            ORDER BY t.transaction_date DESC, t.transaction_id DESC
            LIMIT 200
        ");
        $stmt->execute([$sku_id]);
        $new_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If new table exists but is empty, also query old tables for backward compatibility
        if (count($new_transactions) === 0) {
            // Fall through to old logic below
        } else {
            $history = $new_transactions;
        }
    }

    // If new table doesn't exist OR new table is empty, use old logic
    if (count($history) === 0) {
        // FALLBACK: Old logic for backwards compatibility (if migration not run)
        $history = [];

        // 1. 获取入库记录
        $stmt = $pdo->prepare("
            SELECT
                b.batch_date as date,
                '入库' as type,
                b.batch_code as code,
                ci.total_standard_qty as qty,
                NULL as qty_after,
                b.location_name as location,
                b.remark,
                NULL as operator_name
            FROM mrs_batch_confirmed_item ci
            INNER JOIN mrs_batch b ON ci.batch_id = b.batch_id
            WHERE ci.sku_id = ? AND b.batch_status IN ('confirmed', 'posted')
            ORDER BY b.batch_date DESC, b.created_at DESC
        ");
        $stmt->execute([$sku_id]);
        $inbound = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($inbound as $record) {
            $history[] = $record;
        }

        // 2. 获取出库记录
        $stmt = $pdo->prepare("
            SELECT
                o.outbound_date as date,
                '出库' as type,
                o.outbound_code as code,
                -i.total_standard_qty as qty,
                NULL as qty_after,
                o.location_name as location,
                COALESCE(i.remark, o.remark) as remark,
                NULL as operator_name
            FROM mrs_outbound_order_item i
            INNER JOIN mrs_outbound_order o ON i.outbound_order_id = o.outbound_order_id
            WHERE i.sku_id = ? AND o.status = 'confirmed'
            ORDER BY o.outbound_date DESC, o.created_at DESC
        ");
        $stmt->execute([$sku_id]);
        $outbound = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($outbound as $record) {
            $history[] = $record;
        }

        // 3. 获取库存调整记录
        $stmt = $pdo->prepare("
            SELECT
                DATE(created_at) as date,
                IF(delta_qty > 0, '盘点-盘盈', '盘点-盘亏') as type,
                NULL as code,
                delta_qty as qty,
                NULL as qty_after,
                NULL as location,
                reason as remark,
                NULL as operator_name
            FROM mrs_inventory_adjustment
            WHERE sku_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$sku_id]);
        $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($adjustments as $record) {
            $history[] = $record;
        }

        // 按日期排序
        usort($history, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
    }

    json_response(true, $history);

} catch (PDOException $e) {
    mrs_log('获取历史记录失败: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('获取历史记录异常: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

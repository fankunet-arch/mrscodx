<?php
/**
 * MRS Inventory Query
 * Route: api.php?route=backend_inventory_query
 * Logic: Total Inbound (Confirmed) - Total Outbound (Confirmed)
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// Require Login
require_login();

try {
    $skuId = $_GET['sku_id'] ?? null;
    if (!$skuId) {
        json_response(false, null, 'Missing sku_id');
    }

    $pdo = get_db_connection();

    // 1. Calculate Total Inbound (from mrs_batch_confirmed_item)
    // Assuming 'total_standard_qty' exists in mrs_batch_confirmed_item.
    // If not, I assume I should check the table schema, but based on naming conventions and Phase 1 completion, it should be there.

    $inboundSql = "SELECT SUM(total_standard_qty) FROM mrs_batch_confirmed_item WHERE sku_id = :sku_id";
    $inStmt = $pdo->prepare($inboundSql);
    $inStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
    $inStmt->execute();
    $totalInbound = (int)$inStmt->fetchColumn(); // Returns 0 if null usually, or null

    // 2. Calculate Total Outbound (from mrs_outbound_order_item WHERE order status confirmed)
    $outboundSql = "SELECT SUM(i.total_standard_qty)
                    FROM mrs_outbound_order_item i
                    JOIN mrs_outbound_order o ON i.outbound_order_id = o.outbound_order_id
                    WHERE i.sku_id = :sku_id AND o.status = 'confirmed'";
    $outStmt = $pdo->prepare($outboundSql);
    $outStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
    $outStmt->execute();
    $totalOutbound = (int)$outStmt->fetchColumn();

    // 3. Calculate Total Adjustment (from mrs_inventory_adjustment)
    $adjustmentSql = "SELECT COALESCE(SUM(delta_qty), 0) as total
                      FROM mrs_inventory_adjustment
                      WHERE sku_id = :sku_id";
    $adjStmt = $pdo->prepare($adjustmentSql);
    $adjStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
    $adjStmt->execute();
    $totalAdjustment = floatval($adjStmt->fetchColumn());

    // Updated Formula: Inventory = Inbound - Outbound + Adjustment
    $currentInventory = $totalInbound - $totalOutbound + $totalAdjustment;

    // Fetch SKU info for unit display
    $sku = get_sku_by_id($skuId);
    $unit = $sku['standard_unit'] ?? '';
    $caseSpec = $sku['case_to_standard_qty'] ?? 1;
    $caseUnit = $sku['case_unit_name'] ?? 'Box';

    // Format display string
    if ($caseSpec > 1 && $currentInventory > 0) {
        $cases = floor($currentInventory / $caseSpec);
        $singles = $currentInventory % $caseSpec;
        $display = "{$cases}{$caseUnit} {$singles}{$unit}";
        if ($cases == 0) $display = "{$singles}{$unit}";
        if ($singles == 0) $display = "{$cases}{$caseUnit}";
    } else {
        $display = "{$currentInventory}{$unit}";
    }

    json_response(true, [
        'sku_id' => $skuId,
        'total_inbound' => $totalInbound,
        'total_outbound' => $totalOutbound,
        'total_adjustment' => $totalAdjustment,
        'current_inventory' => $currentInventory,
        'display_text' => $display
    ]);

} catch (Exception $e) {
    mrs_log('Inventory query failed: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, $e->getMessage());
}

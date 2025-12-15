<?php
/**
 * MRS Outbound Management - Save Outbound Order
 * Route: api.php?route=backend_save_outbound
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
    if (!$input) {
        json_response(false, null, 'Invalid input');
    }

    $pdo = get_db_connection();

    // Start Transaction
    $pdo->beginTransaction();

    // 1. Handle Order Header
    $orderId = $input['outbound_order_id'] ?? null;
    $outboundCode = $input['outbound_code'] ?? null;
    $outboundDate = $input['outbound_date'] ?? date('Y-m-d');
    $outboundType = $input['outbound_type'] ?? 1; // Default to Picking
    $locationName = $input['location_name'] ?? ''; // Source/Dest
    $remark = $input['remark'] ?? '';
    $items = $input['items'] ?? [];

    // [FIX SECURITY] 检查并限制状态流转
    // Save 接口只能保存草稿状态，确认操作必须通过 backend_confirm_outbound.php
    if ($orderId) {
        // 检查当前订单状态
        $checkSql = "SELECT status FROM mrs_outbound_order WHERE outbound_order_id = :order_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $checkStmt->execute();
        $currentOrder = $checkStmt->fetch();

        if (!$currentOrder) {
            json_response(false, null, '出库单不存在');
        }

        // [FIX] 只有草稿状态的单据才能通过此接口修改
        // 已确认或已取消的单据不允许修改
        if ($currentOrder['status'] !== 'draft') {
            json_response(false, null, '只能修改草稿状态的出库单，当前状态: ' . $currentOrder['status']);
        }

        // [FIX] 强制保持草稿状态，状态变更必须通过确认接口
        $status = 'draft';

        // Update Order
        $sql = "UPDATE mrs_outbound_order SET
                    outbound_date = :outbound_date,
                    outbound_type = :outbound_type,
                    location_name = :location_name,
                    status = :status,
                    remark = :remark,
                    updated_at = NOW(6)
                WHERE outbound_order_id = :order_id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':outbound_date', $outboundDate);
        $stmt->bindValue(':outbound_type', $outboundType, PDO::PARAM_INT);
        $stmt->bindValue(':location_name', $locationName);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':remark', $remark);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        // Create Order
        if (empty($outboundCode)) {
            $outboundCode = generate_outbound_code($outboundDate);
        }

        // [FIX] 新建订单强制为草稿状态
        $status = 'draft';

        $sql = "INSERT INTO mrs_outbound_order (
                    outbound_code,
                    outbound_date,
                    outbound_type,
                    location_name,
                    status,
                    remark,
                    created_at,
                    updated_at
                ) VALUES (
                    :outbound_code,
                    :outbound_date,
                    :outbound_type,
                    :location_name,
                    :status,
                    :remark,
                    NOW(6),
                    NOW(6)
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':outbound_code', $outboundCode);
        $stmt->bindValue(':outbound_date', $outboundDate);
        $stmt->bindValue(':outbound_type', $outboundType, PDO::PARAM_INT);
        $stmt->bindValue(':location_name', $locationName);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':remark', $remark);
        $stmt->execute();

        $orderId = $pdo->lastInsertId();
    }

    // 2. Handle Items (Full Replacement Strategy for simplicity if items provided)
    // If items array is not null (even if empty), we replace.
    // If items is null (not provided), we skip item update (header only update).
    if (isset($input['items']) && is_array($input['items'])) {

        // Delete existing items
        $delSql = "DELETE FROM mrs_outbound_order_item WHERE outbound_order_id = :order_id";
        $delStmt = $pdo->prepare($delSql);
        $delStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $delStmt->execute();

        // Insert new items
        $insertSql = "INSERT INTO mrs_outbound_order_item (
                        outbound_order_id,
                        sku_id,
                        sku_name,
                        unit_name,
                        case_unit_name,
                        case_to_standard_qty,
                        outbound_case_qty,
                        outbound_single_qty,
                        total_standard_qty,
                        remark,
                        created_at,
                        updated_at
                    ) VALUES (
                        :order_id,
                        :sku_id,
                        :sku_name,
                        :unit_name,
                        :case_unit_name,
                        :case_spec,
                        :case_qty,
                        :single_qty,
                        :total_qty,
                        :remark,
                        NOW(6),
                        NOW(6)
                    )";

        $insertStmt = $pdo->prepare($insertSql);

        foreach ($items as $item) {
            $skuId = $item['sku_id'];

            // Fetch SKU details for snapshot if not provided (safety)
            $sku = get_sku_by_id($skuId);
            if (!$sku) continue;

            $caseSpec = floatval($sku['case_to_standard_qty'] ?: 1);
            $caseQty = floatval($item['outbound_case_qty'] ?? 0);
            $singleQty = floatval($item['outbound_single_qty'] ?? 0);

            // Normalize Logic
            $totalStandardQty = normalize_quantity_to_storage($caseQty, $singleQty, $caseSpec);

            // Recalculate display values if needed?
            // Requirement says: "Backend must convert to ... and calculate total_standard_qty".
            // We store the original input case/single qty for reference, but the core logic relies on total_standard_qty.
            // However, to be consistent with "Auto-Normalization", we might want to store the "Normalized" case/single split.
            // e.g. Input 2.5 cases (spec 10) -> Total 25.
            // Storing 2.5 cases and 0 singles is fine as input record.
            // Storing 2 cases and 5 singles is "normalized" record.
            // The requirement Phase 1 usually kept input as is for "record" purposes but Phase 2 task says:
            // "If frontend submits 2.5 cases, backend must convert ... and store".
            // Let's store the normalized split if the user wants "Storage" view, but here we likely want to preserve what user typed if possible?
            // Actually, "Automatic Normalization" implies we clean up the data.
            // If I input 2.5 cases, it is 25 units.
            // Normalized: 2 cases (20 units) + 5 singles.
            // So let's store normalized values.

            if ($caseSpec > 1) {
                $normCaseQty = floor($totalStandardQty / $caseSpec); // integer division
                $normSingleQty = $totalStandardQty % $caseSpec;     // modulo
            } else {
                $normCaseQty = 0;
                $normSingleQty = $totalStandardQty;
            }

            $insertStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
            $insertStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
            $insertStmt->bindValue(':sku_name', $sku['sku_name']);
            $insertStmt->bindValue(':unit_name', $sku['standard_unit']);
            $insertStmt->bindValue(':case_unit_name', $sku['case_unit_name']);
            $insertStmt->bindValue(':case_spec', $caseSpec);
            // We store normalized values to ensure consistency
            $insertStmt->bindValue(':case_qty', $normCaseQty);
            $insertStmt->bindValue(':single_qty', $normSingleQty);
            $insertStmt->bindValue(':total_qty', $totalStandardQty, PDO::PARAM_INT);
            $insertStmt->bindValue(':remark', $item['remark'] ?? '');

            $insertStmt->execute();
        }
    }

    $pdo->commit();

    json_response(true, ['outbound_order_id' => $orderId, 'outbound_code' => $outboundCode], 'Outbound order saved successfully');

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mrs_log('Save outbound failed: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, $e->getMessage());
}

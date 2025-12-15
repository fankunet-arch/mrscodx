<?php
/**
 * MRS 物料收发管理系统 - 后台API: 极速出库
 * 文件路径: app/mrs/api/backend_quick_outbound.php
 * 说明: 从库存列表直接执行出库操作，无需制单流程
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 需要登录
require_login();

try {
    // 获取POST数据
    $input = get_json_input();

    if (!$input || empty($input['sku_id']) || empty($input['qty'])) {
        json_response(false, null, '缺少必要参数');
    }

    $skuId = intval($input['sku_id']);
    $qty = floatval($input['qty']); // 出库数量（标准单位）
    $locationName = $input['location_name'] ?? '门店出库';
    $outboundDate = $input['outbound_date'] ?? date('Y-m-d');
    $outboundType = $input['outbound_type'] ?? 1; // 1: Picking
    $remark = $input['remark'] ?? '极速出库';

    // 验证数量必须 > 0
    if ($qty <= 0) {
        json_response(false, null, '出库数量必须大于0');
    }

    // 获取数据库连接
    $pdo = get_db_connection();

    // 开启事务
    $pdo->beginTransaction();

    try {
        // [FIX CONCURRENCY] 使用悲观锁防止并发出库导致超卖
        // 锁定 SKU 记录，确保同一个 SKU 的并发出库操作串行化
        $lockSql = "SELECT sku_id, sku_name FROM mrs_sku WHERE sku_id = :sku_id FOR UPDATE";
        $lockStmt = $pdo->prepare($lockSql);
        $lockStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $lockStmt->execute();
        $lockedSku = $lockStmt->fetch();

        // 1. 查询SKU是否存在
        $sku = get_sku_by_id($skuId);
        if (!$sku || !$lockedSku) {
            $pdo->rollBack();
            json_response(false, null, 'SKU不存在');
        }

        // 2. [FIX] 在锁保护下检查库存是否足够
        // 入库总量
        $inboundSql = "SELECT COALESCE(SUM(total_standard_qty), 0) as total
                       FROM mrs_batch_confirmed_item
                       WHERE sku_id = :sku_id";
        $inStmt = $pdo->prepare($inboundSql);
        $inStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $inStmt->execute();
        $totalInbound = (int)$inStmt->fetchColumn();

        // 出库总量
        $outboundSql = "SELECT COALESCE(SUM(i.total_standard_qty), 0) as total
                        FROM mrs_outbound_order_item i
                        JOIN mrs_outbound_order o ON i.outbound_order_id = o.outbound_order_id
                        WHERE i.sku_id = :sku_id AND o.status = 'confirmed'";
        $outStmt = $pdo->prepare($outboundSql);
        $outStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $outStmt->execute();
        $totalOutbound = (int)$outStmt->fetchColumn();

        // 调整总量
        $adjustmentSql = "SELECT COALESCE(SUM(delta_qty), 0) as total
                          FROM mrs_inventory_adjustment
                          WHERE sku_id = :sku_id";
        $adjStmt = $pdo->prepare($adjustmentSql);
        $adjStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $adjStmt->execute();
        $totalAdjustment = floatval($adjStmt->fetchColumn());

        // 当前库存 = 入库 - 出库 + 调整
        $currentInventory = $totalInbound - $totalOutbound + $totalAdjustment;

        // [FIX] 记录详细的库存检查日志，用于并发问题排查
        mrs_log("极速出库库存检查: sku_id={$skuId}, 当前库存={$currentInventory}, 出库数量={$qty}, 入库={$totalInbound}, 出库={$totalOutbound}, 调整={$totalAdjustment}", 'INFO');

        // 检查库存是否足够
        if ($currentInventory < $qty) {
            $pdo->rollBack();
            json_response(false, null, "库存不足，当前库存：{$currentInventory}，需要：{$qty}");
        }

        // 3. 生成出库单号
        $outboundCode = generate_outbound_code($outboundDate);

        // 4. 创建出库单（状态直接为 confirmed）
        $insertOrderSql = "INSERT INTO mrs_outbound_order (
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
                            'confirmed',
                            :remark,
                            NOW(6),
                            NOW(6)
                        )";

        $orderStmt = $pdo->prepare($insertOrderSql);
        $orderStmt->bindValue(':outbound_code', $outboundCode);
        $orderStmt->bindValue(':outbound_date', $outboundDate);
        $orderStmt->bindValue(':outbound_type', $outboundType, PDO::PARAM_INT);
        $orderStmt->bindValue(':location_name', $locationName);
        $orderStmt->bindValue(':remark', $remark);
        $orderStmt->execute();

        $orderId = $pdo->lastInsertId();

        // 5. 创建出库明细
        // 获取SKU信息快照
        $caseSpec = floatval($sku['case_to_standard_qty'] ?: 1);

        // 将出库数量归一化为箱+散装
        $totalStandardQty = (int)$qty;
        if ($caseSpec > 1) {
            $caseQty = floor($totalStandardQty / $caseSpec);
            $singleQty = $totalStandardQty % $caseSpec;
        } else {
            $caseQty = 0;
            $singleQty = $totalStandardQty;
        }

        $insertItemSql = "INSERT INTO mrs_outbound_order_item (
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

        $itemStmt = $pdo->prepare($insertItemSql);
        $itemStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $itemStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $itemStmt->bindValue(':sku_name', $sku['sku_name']);
        $itemStmt->bindValue(':unit_name', $sku['standard_unit']);
        $itemStmt->bindValue(':case_unit_name', $sku['case_unit_name']);
        $itemStmt->bindValue(':case_spec', $caseSpec);
        $itemStmt->bindValue(':case_qty', $caseQty);
        $itemStmt->bindValue(':single_qty', $singleQty);
        $itemStmt->bindValue(':total_qty', $totalStandardQty, PDO::PARAM_INT);
        $itemStmt->bindValue(':remark', $remark);
        $itemStmt->execute();

        // 提交事务
        $pdo->commit();

        mrs_log("极速出库成功: sku_id={$skuId}, qty={$qty}, order_id={$orderId}, code={$outboundCode}", 'INFO');

        json_response(true, [
            'outbound_order_id' => $orderId,
            'outbound_code' => $outboundCode,
            'qty' => $qty
        ], '出库成功');

    } catch (Exception $e) {
        // 回滚事务
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

} catch (PDOException $e) {
    mrs_log('极速出库失败: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('极速出库异常: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

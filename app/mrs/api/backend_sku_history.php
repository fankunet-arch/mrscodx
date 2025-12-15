<?php
/**
 * MRS 物料收发管理系统 - 后台API: SKU履历追溯
 * 文件路径: app/mrs/api/backend_sku_history.php
 * 说明: 查询SKU的完整历史记录（入库、出库、调整）
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
    // 获取参数
    $skuId = $_GET['sku_id'] ?? null;

    if (!$skuId) {
        json_response(false, null, '缺少SKU ID');
    }

    $skuId = intval($skuId);

    // 验证SKU是否存在
    $sku = get_sku_by_id($skuId);
    if (!$sku) {
        json_response(false, null, 'SKU不存在');
    }

    // 获取数据库连接
    $pdo = get_db_connection();

    // 1. 查询入库记录
    // [FIX] 使用不同的参数名避免 UNION 中参数绑定问题
    $inboundSql = "(SELECT
                    b.batch_date as date,
                    b.batch_code as code,
                    '入库' as type,
                    ci.total_standard_qty as qty,
                    CONCAT('+', ci.total_standard_qty) as qty_display,
                    b.location_name as location,
                    b.remark as remark,
                    b.created_at as created_at
                FROM mrs_batch_confirmed_item ci
                JOIN mrs_batch b ON ci.batch_id = b.batch_id
                WHERE ci.sku_id = :sku_id1
                AND b.batch_status IN ('confirmed', 'posted'))
                UNION ALL
                (SELECT
                    b.batch_date as date,
                    b.batch_code as code,
                    '入库' as type,
                    rr.qty as qty,
                    CONCAT('+', rr.qty) as qty_display,
                    b.location_name as location,
                    b.remark as remark,
                    b.created_at as created_at
                FROM mrs_batch_raw_record rr
                JOIN mrs_batch b ON rr.batch_id = b.batch_id
                WHERE rr.sku_id = :sku_id2
                AND b.batch_status NOT IN ('confirmed', 'posted'))
                ORDER BY date DESC, created_at DESC";

    $inStmt = $pdo->prepare($inboundSql);
    $inStmt->bindValue(':sku_id1', $skuId, PDO::PARAM_INT);
    $inStmt->bindValue(':sku_id2', $skuId, PDO::PARAM_INT);
    $inStmt->execute();
    $inboundRecords = $inStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. 查询出库记录
    $outboundSql = "SELECT
                    o.outbound_date as date,
                    o.outbound_code as code,
                    '出库' as type,
                    i.total_standard_qty as qty,
                    CONCAT('-', i.total_standard_qty) as qty_display,
                    o.location_name as location,
                    o.remark as remark,
                    o.created_at as created_at
                FROM mrs_outbound_order_item i
                JOIN mrs_outbound_order o ON i.outbound_order_id = o.outbound_order_id
                WHERE i.sku_id = :sku_id
                AND o.status = 'confirmed'
                ORDER BY o.outbound_date DESC, o.created_at DESC";

    $outStmt = $pdo->prepare($outboundSql);
    $outStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
    $outStmt->execute();
    $outboundRecords = $outStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. 查询调整记录
    $adjustmentSql = "SELECT
                    DATE(created_at) as date,
                    CONCAT('ADJ-', adjustment_id) as code,
                    '盘点调整' as type,
                    delta_qty as qty,
                    CASE
                        WHEN delta_qty > 0 THEN CONCAT('+', delta_qty)
                        ELSE CAST(delta_qty AS CHAR)
                    END as qty_display,
                    reason as location,
                    operator_name as remark,
                    created_at as created_at
                FROM mrs_inventory_adjustment
                WHERE sku_id = :sku_id
                ORDER BY created_at DESC";

    $adjStmt = $pdo->prepare($adjustmentSql);
    $adjStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
    $adjStmt->execute();
    $adjustmentRecords = $adjStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. 合并所有记录并按时间排序
    $allRecords = array_merge($inboundRecords, $outboundRecords, $adjustmentRecords);

    // 按创建时间降序排序
    usort($allRecords, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // 5. 格式化输出
    $history = array_map(function($record) {
        return [
            'date' => $record['date'],
            'code' => $record['code'],
            'type' => $record['type'],
            'qty' => floatval($record['qty']),
            'qty_display' => $record['qty_display'],
            'location' => $record['location'] ?: '-',
            'remark' => $record['remark'] ?: '-',
            'created_at' => $record['created_at']
        ];
    }, $allRecords);

    mrs_log("查询SKU履历成功: sku_id={$skuId}, records=" . count($history), 'INFO');

    json_response(true, [
        'sku_id' => $skuId,
        'sku_name' => $sku['sku_name'],
        'history' => $history
    ]);

} catch (PDOException $e) {
    mrs_log('查询SKU履历失败: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('查询SKU履历异常: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

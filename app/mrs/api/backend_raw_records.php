<?php
/**
 * MRS 物料收发管理系统 - 后台API: 获取原始记录明细
 * 文件路径: app/mrs/api/backend_raw_records.php
 * 说明: 获取特定SKU在特定批次中的原始收货记录
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置和库文件
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

try {
    // 获取查询参数
    $batch_id = $_GET['batch_id'] ?? null;
    $sku_id = $_GET['sku_id'] ?? null;

    // 验证参数
    if ($batch_id === null || !is_numeric($batch_id)) {
        json_response(false, null, '无效的批次ID');
    }
    if ($sku_id === null || !is_numeric($sku_id)) {
        json_response(false, null, '无效的SKU ID');
    }

    $batch_id = (int)$batch_id;
    $sku_id = (int)$sku_id;

    // 验证批次是否存在
    $batch = get_batch_by_id($batch_id);
    if (!$batch) {
        json_response(false, null, '批次不存在');
    }

    // 获取数据库连接
    $pdo = get_db_connection();

    // 获取该SKU在该批次中的原始记录
    $sql = "SELECT
                r.raw_record_id,
                r.batch_id,
                r.sku_id,
                r.input_sku_name,
                r.qty,
                r.physical_box_count,
                r.unit_name,
                r.operator_name,
                r.recorded_at,
                r.note,
                COALESCE(r.input_sku_name, s.sku_name) AS sku_name,
                s.brand_name,
                s.standard_unit,
                s.case_unit_name,
                s.case_to_standard_qty
            FROM mrs_batch_raw_record r
            LEFT JOIN mrs_sku s ON r.sku_id = s.sku_id
            WHERE r.batch_id = :batch_id AND r.sku_id = :sku_id
            ORDER BY r.recorded_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':batch_id', $batch_id, PDO::PARAM_INT);
    $stmt->bindValue(':sku_id', $sku_id, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 返回成功响应
    json_response(true, [
        'batch' => $batch,
        'records' => $records
    ], '获取成功');

} catch (Exception $e) {
    mrs_log('原始记录API错误: ' . $e->getMessage(), 'ERROR', [
        'batch_id' => $batch_id ?? null,
        'sku_id' => $sku_id ?? null
    ]);
    json_response(false, null, '服务器错误');
}

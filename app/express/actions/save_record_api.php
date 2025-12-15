<?php
/**
 * API: Save Operation Record
 * 文件路径: app/express/actions/save_record_api.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

// 获取POST数据
$input = express_get_json_input();

$batch_id = $input['batch_id'] ?? 0;
$tracking_number = $input['tracking_number'] ?? '';
$operation_type = $input['operation_type'] ?? '';
$content_note = $input['content_note'] ?? null;  // 保留向后兼容
$expiry_date = $input['expiry_date'] ?? null;  // 保留向后兼容
$quantity = $input['quantity'] ?? null;  // 保留向后兼容
$products = $input['products'] ?? null;  // 新增:多产品数据数组
$adjustment_note = $input['adjustment_note'] ?? null;
$operator = $input['operator'] ?? 'system';

// 验证必填参数
if (empty($batch_id) || empty($tracking_number) || empty($operation_type)) {
    express_json_response(false, null, '参数错误');
}

// 验证操作类型
if (!in_array($operation_type, ['verify', 'count', 'adjust'])) {
    express_json_response(false, null, '无效的操作类型');
}

// 处理包裹操作
$result = express_process_package(
    $pdo,
    $batch_id,
    $tracking_number,
    $operation_type,
    $operator,
    $content_note,
    $adjustment_note,
    $expiry_date,
    $quantity,
    $products  // 传递多产品数据
);

// 同时返回更新后的批次统计
if ($result['success']) {
    $batch = express_get_batch_by_id($pdo, $batch_id);
    $result['batch'] = $batch;
}

express_json_response($result['success'], $result, $result['message']);

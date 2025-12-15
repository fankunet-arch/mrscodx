<?php
// Action: save_receipt_api.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, '无效的请求方法。');
    exit;
}
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    json_response(false, null, '无效的 JSON 数据。');
    exit;
}

// 校验必填字段
$required_fields = ['batch_id', 'qty', 'unit_name', 'operator_name', 'physical_box_count'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || $data[$field] === '') {
        json_response(false, null, "缺少必填字段: {$field}");
    }
}

// 数值校验
if (!is_numeric($data['qty'])) {
    json_response(false, null, '数量必须为数字');
}

if (!is_numeric($data['physical_box_count']) || floatval($data['physical_box_count']) <= 0) {
    json_response(false, null, '物理箱数必须为大于0的数字');
}
$record_id = save_raw_record($data);
if ($record_id) {
    if (!empty($data['batch_id'])) {
        $batch = get_batch_by_id($data['batch_id']);
        if ($batch && $batch['batch_status'] === 'draft') {
            global $pdo;
            $stmt = $pdo->prepare("UPDATE mrs_batch SET batch_status = 'receiving' WHERE batch_id = ?");
            $stmt->execute([$data['batch_id']]);
        }
    }
    json_response(true, ['raw_record_id' => $record_id], '记录保存成功。');
} else {
    json_response(false, null, '保存记录失败，请检查日志。');
}

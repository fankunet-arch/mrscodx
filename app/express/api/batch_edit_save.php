<?php
/**
 * 保存批次编辑
 * 文件路径: app/express/api/batch_edit_save.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$batch_id = (int)($input['batch_id'] ?? 0);
$batch_name = trim($input['batch_name'] ?? '');
$status = $input['status'] ?? 'active';
$notes = isset($input['notes']) ? trim($input['notes']) : null;

if ($batch_id <= 0 || $batch_name === '') {
    express_json_response(false, null, '批次ID和名称不能为空');
}

$result = express_update_batch($pdo, $batch_id, $batch_name, $status, $notes);

express_json_response($result['success'], ['batch_id' => $batch_id], $result['message']);

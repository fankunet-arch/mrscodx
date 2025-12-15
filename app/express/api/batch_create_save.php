<?php
/**
 * API: Save New Batch
 * 文件路径: app/express/api/batch_create_save.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$input = express_get_json_input();

$batch_name = $input['batch_name'] ?? '';
$notes = $input['notes'] ?? null;
$created_by = $input['created_by'] ?? $_SESSION['user_login'] ?? 'admin';

if (empty($batch_name)) {
    express_json_response(false, null, '批次名称不能为空');
}

$batch_id = express_create_batch($pdo, $batch_name, $created_by, $notes);

if ($batch_id) {
    express_json_response(true, ['batch_id' => $batch_id], '批次创建成功');
} else {
    express_json_response(false, null, '批次创建失败，可能批次名称已存在');
}

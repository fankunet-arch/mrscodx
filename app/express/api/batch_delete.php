<?php
/**
 * API: Delete Batch
 * 文件路径: app/express/api/batch_delete.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$input = express_get_json_input();
$batch_id = isset($input['batch_id']) ? (int)$input['batch_id'] : (int)($_POST['batch_id'] ?? 0);

if ($batch_id <= 0) {
    express_json_response(false, null, '批次ID不能为空');
}

$result = express_delete_batch($pdo, $batch_id);

express_json_response($result['success'], null, $result['message']);

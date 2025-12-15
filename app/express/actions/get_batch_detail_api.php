<?php
/**
 * API: Get Batch Detail
 * 文件路径: app/express/actions/get_batch_detail_api.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$batch_id = $_GET['batch_id'] ?? 0;

if (empty($batch_id)) {
    express_json_response(false, null, '批次ID不能为空');
}

$batch = express_get_batch_by_id($pdo, $batch_id);

if (!$batch) {
    express_json_response(false, null, '批次不存在');
}

express_json_response(true, $batch);

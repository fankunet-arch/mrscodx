<?php
/**
 * API: Get Packages by Batch
 * 文件路径: app/express/actions/get_packages_api.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$batch_id = $_GET['batch_id'] ?? 0;
$status = $_GET['status'] ?? 'all';

if (empty($batch_id)) {
    express_json_response(false, null, '批次ID不能为空');
}

$packages = express_get_packages_by_batch($pdo, $batch_id, $status);
express_json_response(true, $packages);

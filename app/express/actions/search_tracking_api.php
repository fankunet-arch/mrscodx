<?php
/**
 * API: Search Tracking Number
 * 文件路径: app/express/actions/search_tracking_api.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$batch_id = $_GET['batch_id'] ?? 0;
$keyword = $_GET['keyword'] ?? '';

if (empty($batch_id) || empty($keyword)) {
    express_json_response(false, null, '参数错误');
}

$results = express_search_tracking($pdo, $batch_id, $keyword, 20);
express_json_response(true, $results);

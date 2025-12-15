<?php
/**
 * API: Search Packages by Content Note
 * 文件路径: app/express/api/content_search_api.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$keyword = $_GET['keyword'] ?? '';

if (empty(trim($keyword))) {
    express_json_response(false, null, '请输入要搜索的物品内容');
}

$results = express_search_content_note($pdo, $keyword, 100);
express_json_response(true, $results);

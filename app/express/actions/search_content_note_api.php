<?php
/**
 * API: Search Content Note
 * 文件路径: app/express/actions/search_content_note_api.php
 * 说明: 搜索历史内容备注（跨批次，去重）
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$keyword = $_GET['keyword'] ?? '';

if (empty($keyword)) {
    express_json_response(false, null, '请输入搜索关键词');
}

// 调用后端函数搜索内容备注
$results = express_search_content_note($pdo, $keyword, 50);

// 对结果进行去重（只保留唯一的 content_note）
$unique_notes = [];
$seen = [];

foreach ($results as $item) {
    $note = trim($item['content_note']);
    if (!empty($note) && !isset($seen[$note])) {
        $seen[$note] = true;
        $unique_notes[] = [
            'content_note' => $note,
            'tracking_number' => $item['tracking_number'],
            'batch_name' => $item['batch_name'],
            'counted_at' => $item['counted_at'] ?? $item['created_at']
        ];
    }
}

express_json_response(true, $unique_notes);

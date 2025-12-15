<?php
/**
 * API: Bulk Import Tracking Numbers
 * 文件路径: app/express/api/bulk_import_save.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$input = express_get_json_input();

$batch_id = $input['batch_id'] ?? 0;
$tracking_numbers_text = $input['tracking_numbers'] ?? '';

if (empty($batch_id)) {
    express_json_response(false, null, '批次ID不能为空');
}

if (empty($tracking_numbers_text)) {
    express_json_response(false, null, '请输入快递单号');
}

// 将文本按行分割成数组
$tracking_numbers = array_filter(
    array_map('trim', explode("\n", $tracking_numbers_text)),
    function($line) {
        return !empty($line);
    }
);

if (empty($tracking_numbers)) {
    express_json_response(false, null, '没有有效的快递单号');
}

$result = express_bulk_import($pdo, $batch_id, $tracking_numbers);

if ($result['success']) {
    express_json_response(true, $result, '批量导入完成');
} else {
    express_json_response(false, null, $result['message']);
}

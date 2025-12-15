<?php
// Action: get_batch_records_api.php
$batch_id = $_GET['batch_id'] ?? null;
if (empty($batch_id)) {
    json_response(false, null, '未提供批次 ID。');
    exit;
}
$records = get_batch_raw_records((int)$batch_id);
json_response(true, $records, '记录获取成功。');

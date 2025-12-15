<?php
// Action: get_batch_list_api.php
$batches = get_batch_list(50, null);
json_response(true, $batches, '批次列表获取成功。');

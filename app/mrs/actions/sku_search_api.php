<?php
// Action: sku_search_api.php
$keyword = $_GET['keyword'] ?? '';
if (empty($keyword)) {
    json_response(true, [], '请输入关键词。');
    exit;
}
$skus = search_sku($keyword, 20);
json_response(true, $skus, 'SKU 搜索成功。');

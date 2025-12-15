<?php
// Action: sku_list.php

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

$keyword = $_GET['search'] ?? '';
$skus = search_sku($keyword, 1000);

$page_title = "物料管理 (SKU)";
$action = 'sku_list'; // For sidebar highlighting

require_once MRS_VIEW_PATH . '/sku_list.php';

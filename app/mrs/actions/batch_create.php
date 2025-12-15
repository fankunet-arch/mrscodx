<?php
// Action: batch_create.php - 新建批次页面

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

$page_title = "新建批次";
$action = 'batch_create';

require_once MRS_VIEW_PATH . '/batch_create.php';

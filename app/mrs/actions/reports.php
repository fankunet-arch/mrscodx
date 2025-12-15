<?php
// Action: reports.php - 报表页面

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

$page_title = "数据报表";
$action = 'reports';

require_once MRS_VIEW_PATH . '/reports.php';

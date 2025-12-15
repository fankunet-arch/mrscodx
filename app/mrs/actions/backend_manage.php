<?php
/**
 * MRS 后台管理中心
 * 完整的SPA管理界面，包含所有功能模块
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 设置后台标记
define('MRS_BACKEND', true);

// 要求登录
require_login();

// 获取当前用户信息
$current_user = $_SESSION['user_display_name'] ?? '管理员';

// 加载后台管理视图
require_once MRS_VIEW_PATH . '/backend_manage.php';

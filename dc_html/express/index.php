<?php
/**
 * Express Package Management System - Frontend Router
 * 文件路径: dc_html/express/index.php
 * 说明: 前台中央路由入口（网络可访问）
 */

// 定义系统入口标识
define('EXPRESS_ENTRY', true);

// 定义项目根目录（dc_html的上级目录）
define('PROJECT_ROOT', dirname(dirname(__DIR__)));

// 加载bootstrap（在app目录中）
// 使用mock bootstrap进行测试
if (file_exists(PROJECT_ROOT . '/app/express/bootstrap_mock.php')) {
    require_once PROJECT_ROOT . '/app/express/bootstrap_mock.php';
} else {
    require_once PROJECT_ROOT . '/app/express/bootstrap.php';
}

// 获取action参数
$action = $_GET['action'] ?? 'quick_ops';
$action = basename($action); // 防止路径遍历

// 前台允许的action列表
$allowed_actions = [
    'quick_ops',                // 前台操作页面
    'get_batches_api',          // 获取批次列表API
    'search_tracking_api',      // 搜索快递单号API
    'save_record_api',          // 保存操作记录API
    'get_packages_api',         // 获取包裹列表API
    'get_batch_detail_api',     // 获取批次详情API
    'get_recent_operations_api', // 获取最近操作记录API（按类型过滤+去重）
    'get_product_expiry_api'    // 获取产品保质期建议API
];

// 验证action是否允许
if (!in_array($action, $allowed_actions)) {
    http_response_code(404);
    die('Invalid action');
}

// 路由到对应的action文件（在app目录中）
$action_file = EXPRESS_ACTION_PATH . '/' . $action . '.php';

if (file_exists($action_file)) {
    require_once $action_file;
} else {
    http_response_code(404);
    die('Action not found');
}

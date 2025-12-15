<?php
/**
 * Express Package Management System - Backend Router
 * 文件路径: dc_html/express/exp/index.php
 * 说明: 后台管理中央路由入口（网络可访问）
 */

// 定义系统入口标识
define('EXPRESS_ENTRY', true);

// 定义项目根目录（dc_html的上级的上级目录）
define('PROJECT_ROOT', dirname(dirname(dirname(__DIR__))));

// 加载bootstrap（在app目录中）
// 使用mock bootstrap进行测试
if (file_exists(PROJECT_ROOT . '/app/express/bootstrap_mock.php')) {
    require_once PROJECT_ROOT . '/app/express/bootstrap_mock.php';
} else {
    require_once PROJECT_ROOT . '/app/express/bootstrap.php';
}

// 获取action参数
$action = $_GET['action'] ?? 'batch_list';
$action = basename($action); // 防止路径遍历

// 身份验证：所有非登录操作必须经过MRS一致的会话校验
if ($action !== 'login' && $action !== 'do_login') {
    express_require_login();
}

// 后台允许的action列表
$allowed_actions = [
    'login',                    // 登录页面
    'do_login',                 // 处理登录
    'logout',                   // 登出
    'batch_list',               // 批次列表
    'batch_detail',             // 批次详情
    'batch_print',              // 批次打印预览
    'batch_create',             // 创建批次页面
    'batch_edit',               // 编辑批次页面
    'batch_create_save',        // 保存新批次
    'batch_edit_save',          // 保存批次编辑
    'bulk_import',              // 批量导入页面
    'bulk_import_save',         // 保存批量导入
    'create_custom_packages',   // 创建自定义包裹
    'content_search',           // 内容备注搜索页面
    'content_search_api',       // 内容备注搜索API
    'update_content_note',      // 更新内容备注API
    'get_package_items',        // 获取包裹产品明细API
    'get_product_expiry'        // 获取产品保质期建议API
];

// 验证action是否允许
if (!in_array($action, $allowed_actions)) {
    $accepts_json = isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($accepts_json || $is_ajax) {
        express_json_response(false, null, 'Invalid action');
    }

    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404 - Page Not Found</title>';
    echo '<style>body{font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;margin:0;padding:40px;}';
    echo '.card{max-width:520px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,0.08);}';
    echo '.card h1{margin-top:0;font-size:22px;color:#c62828;} .card p{color:#444;line-height:1.6;} .card a{color:#1565c0;text-decoration:none;font-weight:600;}</style>';
    echo '</head><body><div class="card"><h1>404 - 无效的后台入口</h1><p>请求的操作未被允许或链接已失效。</p>';
    echo '<p><a href="/express/exp/index.php?action=batch_list">返回后台首页</a></p></div></body></html>';
    exit;
}

// API action（返回JSON）
$api_actions = [
    'do_login',
    'batch_create_save',
    'batch_edit_save',
    'bulk_import_save',
    'create_custom_packages',
    'logout',
    'content_search_api',
    'update_content_note',
    'get_package_items',
    'get_product_expiry'
];

// 路由到对应的action或API文件（在app目录中）
if (in_array($action, $api_actions)) {
    // API路由
    $api_file = EXPRESS_API_PATH . '/' . $action . '.php';
    if (file_exists($api_file)) {
        require_once $api_file;
    } else {
        express_json_response(false, null, 'API not found');
    }
} else {
    // 页面路由
    $view_file = EXPRESS_VIEW_PATH . '/' . $action . '.php';
    if (file_exists($view_file)) {
        require_once $view_file;
    } else {
        http_response_code(404);
        die('Page not found');
    }
}

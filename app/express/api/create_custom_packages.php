<?php
/**
 * API: Create Custom Packages
 * 文件路径: app/express/api/create_custom_packages.php
 * 说明: 为批次创建自定义包裹（用于拆分快递箱的场景）
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$input = express_get_json_input();

$batch_id = $input['batch_id'] ?? 0;
$count = $input['count'] ?? 0;

if (empty($batch_id)) {
    express_json_response(false, null, '批次ID不能为空');
}

if ($count <= 0 || $count > 100) {
    express_json_response(false, null, '数量必须在1-100之间');
}

// 获取当前操作用户
$operator = $_SESSION['user_login'] ?? '';

$result = express_create_custom_packages($pdo, $batch_id, $count, $operator);

if ($result['success']) {
    express_json_response(true, $result['data'], $result['message']);
} else {
    express_json_response(false, null, $result['message']);
}

<?php
/**
 * API: Update package content note
 * 文件路径: app/express/api/update_content_note.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$input = express_get_json_input();
$package_id = $input['package_id'] ?? 0;
$content_note = isset($input['content_note']) ? trim($input['content_note']) : null;
$expiry_date = $input['expiry_date'] ?? null;
$quantity = $input['quantity'] ?? null;
$items = $input['items'] ?? null;  // 新增：产品明细数组
$operator = $_SESSION['user_display_name'] ?? $_SESSION['user_login'] ?? 'system';

if (empty($package_id)) {
    express_json_response(false, null, '缺少包裹ID');
}

$result = express_update_content_note($pdo, $package_id, $operator, $content_note, $expiry_date, $quantity, $items);

if ($result['success']) {
    express_json_response(true, $result['package'], $result['message']);
}

express_json_response(false, null, $result['message'] ?? '更新失败');

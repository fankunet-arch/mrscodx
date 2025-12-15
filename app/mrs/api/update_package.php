<?php
/**
 * API: Update Package Info
 * 文件路径: app/mrs/api/update_package.php
 * 说明: 更新包裹信息(规格、有效期、数量)
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mrs_json_response(false, null, '非法请求方式');
}

$input = mrs_get_json_input();
if (!$input) {
    $input = $_POST;
}

$ledger_id = (int)($input['ledger_id'] ?? 0);
$spec_info = trim($input['spec_info'] ?? '');
$expiry_date = trim($input['expiry_date'] ?? '');
$quantity = trim($input['quantity'] ?? '');
$items = $input['items'] ?? null;  // 新增：产品明细数组

if ($ledger_id <= 0) {
    mrs_json_response(false, null, '台账ID无效');
}

// 处理空值
$expiry_date = $expiry_date === '' ? null : $expiry_date;
$quantity = $quantity === '' ? null : $quantity;

// 获取操作员
$operator = $_SESSION['user_login'] ?? 'system';

// 执行更新
$result = mrs_update_package_info($pdo, $ledger_id, $spec_info, $expiry_date, $quantity, $operator, $items);

if ($result['success']) {
    mrs_json_response(true, null, $result['message']);
} else {
    mrs_json_response(false, null, $result['message']);
}

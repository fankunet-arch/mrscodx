<?php
/**
 * API: Get Package Items (Product Details)
 * 文件路径: app/express/api/get_package_items.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$package_id = (int)($_GET['package_id'] ?? 0);

if ($package_id <= 0) {
    express_json_response(false, null, '包裹ID无效');
}

try {
    $stmt = $pdo->prepare("
        SELECT * FROM express_package_items
        WHERE package_id = :package_id
        ORDER BY sort_order ASC
    ");
    $stmt->execute(['package_id' => $package_id]);
    $items = $stmt->fetchAll();

    express_json_response(true, $items);
} catch (PDOException $e) {
    express_log('Failed to get package items: ' . $e->getMessage(), 'ERROR');
    express_json_response(false, null, '获取产品明细失败');
}

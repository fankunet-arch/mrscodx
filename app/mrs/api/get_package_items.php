<?php
/**
 * API: Get Package Items
 * 文件路径: app/mrs/api/get_package_items.php
 * 说明: 获取包裹的产品明细数据
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

$ledger_id = (int)($_GET['ledger_id'] ?? 0);

if ($ledger_id <= 0) {
    mrs_json_response(false, null, '台账ID无效');
}

try {
    // 获取产品明细
    $stmt = $pdo->prepare("
        SELECT * FROM mrs_package_items
        WHERE ledger_id = :ledger_id
        ORDER BY sort_order ASC
    ");
    $stmt->execute(['ledger_id' => $ledger_id]);
    $items = $stmt->fetchAll();

    mrs_json_response(true, $items);

} catch (PDOException $e) {
    mrs_log('Failed to get package items: ' . $e->getMessage(), 'ERROR');
    mrs_json_response(false, null, '获取失败: ' . $e->getMessage());
}

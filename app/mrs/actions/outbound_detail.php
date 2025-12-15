<?php
// Action: outbound_detail.php - 出库单详情页面

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

$outbound_order_id = $_GET['id'] ?? null;

if (!$outbound_order_id) {
    $_SESSION['error_message'] = '出库单ID缺失';
    header('Location: /mrs/be/index.php?action=outbound_list');
    exit;
}

try {
    $pdo = get_db_connection();

    // 获取出库单信息
    $stmt = $pdo->prepare("SELECT * FROM mrs_outbound_order WHERE outbound_order_id = ?");
    $stmt->execute([$outbound_order_id]);
    $outbound = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$outbound) {
        $_SESSION['error_message'] = '出库单不存在';
        header('Location: /mrs/be/index.php?action=outbound_list');
        exit;
    }

    // 获取出库明细
    $stmt = $pdo->prepare("
        SELECT * FROM mrs_outbound_order_item
        WHERE outbound_order_id = ?
        ORDER BY outbound_order_item_id
    ");
    $stmt->execute([$outbound_order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    mrs_log("Failed to load outbound detail: " . $e->getMessage(), 'ERROR');
    $_SESSION['error_message'] = '加载出库单详情失败';
    header('Location: /mrs/be/index.php?action=outbound_list');
    exit;
}

$page_title = "出库单详情 - " . $outbound['outbound_code'];
$action = 'outbound_detail';

require_once MRS_VIEW_PATH . '/outbound_detail.php';

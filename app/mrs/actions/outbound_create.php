<?php
// Action: outbound_create.php - 创建/编辑出库单页面

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

$outbound_order_id = $_GET['id'] ?? null;
$preselected_sku_id = $_GET['sku_id'] ?? null;
$outbound = null;
$items = [];
$skus = [];

// 获取所有SKU供选择
try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("
        SELECT s.sku_id, s.sku_name, s.brand_name, s.standard_unit,
               s.case_unit_name, s.case_to_standard_qty,
               c.category_name
        FROM mrs_sku s
        LEFT JOIN mrs_category c ON s.category_id = c.category_id
        ORDER BY s.sku_name
    ");
    $skus = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    mrs_log("Failed to load SKUs: " . $e->getMessage(), 'ERROR');
}

// 如果是编辑模式，加载出库单数据
if ($outbound_order_id) {
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

        // 只能编辑草稿状态的出库单
        if ($outbound['status'] !== 'draft') {
            $_SESSION['error_message'] = '只能编辑草稿状态的出库单';
            header('Location: /mrs/be/index.php?action=outbound_detail&id=' . $outbound_order_id);
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
        mrs_log("Failed to load outbound order: " . $e->getMessage(), 'ERROR');
        $_SESSION['error_message'] = '加载出库单失败';
        header('Location: /mrs/be/index.php?action=outbound_list');
        exit;
    }
}

$page_title = $outbound ? "编辑出库单" : "新建出库单";
$action = 'outbound_create';

require_once MRS_VIEW_PATH . '/outbound_create.php';

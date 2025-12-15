<?php
// Action: sku_edit.php - SKU编辑/新建页面

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

$sku_id = $_GET['id'] ?? null;
$sku = null;
$categories = [];

// 获取所有品类
try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT category_id, category_name FROM mrs_category ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    mrs_log("Failed to load categories: " . $e->getMessage(), 'ERROR');
}

// 如果是编辑模式，加载SKU数据
if ($sku_id) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("
            SELECT s.*, c.category_name
            FROM mrs_sku s
            LEFT JOIN mrs_category c ON s.category_id = c.category_id
            WHERE s.sku_id = ?
        ");
        $stmt->execute([$sku_id]);
        $sku = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sku) {
            $_SESSION['error_message'] = 'SKU不存在';
            header('Location: /mrs/be/index.php?action=sku_list');
            exit;
        }
    } catch (PDOException $e) {
        mrs_log("Failed to load SKU: " . $e->getMessage(), 'ERROR');
        $_SESSION['error_message'] = '加载SKU失败';
        header('Location: /mrs/be/index.php?action=sku_list');
        exit;
    }
}

$page_title = $sku ? "编辑SKU" : "新建SKU";
$action = 'sku_edit';

require_once MRS_VIEW_PATH . '/sku_edit.php';

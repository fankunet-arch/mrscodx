<?php
// Action: category_list.php - 品类列表页面

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

// 获取搜索关键词
$search = $_GET['search'] ?? '';

// 查询品类
try {
    $pdo = get_db_connection();

    if (!empty($search)) {
        $stmt = $pdo->prepare("SELECT * FROM mrs_category WHERE category_name LIKE ? OR category_code LIKE ? ORDER BY category_name");
        $search_term = "%{$search}%";
        $stmt->execute([$search_term, $search_term]);
    } else {
        $stmt = $pdo->query("SELECT * FROM mrs_category ORDER BY category_name");
    }

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    mrs_log("Failed to load categories: " . $e->getMessage(), 'ERROR');
    $categories = [];
}

$page_title = "品类管理";
$action = 'category_list';

require_once MRS_VIEW_PATH . '/category_list.php';

<?php
// Action: category_edit.php - 品类编辑/新建页面

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

$category_id = $_GET['id'] ?? null;
$category = null;

// 如果是编辑模式，加载品类数据
if ($category_id) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT * FROM mrs_category WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            $_SESSION['error_message'] = '品类不存在';
            header('Location: /mrs/be/index.php?action=category_list');
            exit;
        }
    } catch (PDOException $e) {
        mrs_log("Failed to load category: " . $e->getMessage(), 'ERROR');
        $_SESSION['error_message'] = '加载品类失败';
        header('Location: /mrs/be/index.php?action=category_list');
        exit;
    }
}

$page_title = $category ? "编辑品类" : "新建品类";
$action = 'category_edit';

require_once MRS_VIEW_PATH . '/category_edit.php';

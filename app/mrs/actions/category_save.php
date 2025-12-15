<?php
// Action: category_save.php - 保存品类

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mrs/be/index.php?action=category_list');
    exit;
}

$category_id = $_POST['category_id'] ?? null;
$category_name = trim($_POST['category_name'] ?? '');
$category_code = trim($_POST['category_code'] ?? '');

// 验证必填字段
if (empty($category_name)) {
    $_SESSION['error_message'] = '品类名称不能为空';
    if ($category_id) {
        header('Location: /mrs/be/index.php?action=category_edit&id=' . $category_id);
    } else {
        header('Location: /mrs/be/index.php?action=category_edit');
    }
    exit;
}

try {
    $pdo = get_db_connection();

    // 检查品类名称唯一性
    if ($category_id) {
        $stmt = $pdo->prepare("SELECT category_id FROM mrs_category WHERE category_name = ? AND category_id != ?");
        $stmt->execute([$category_name, $category_id]);
    } else {
        $stmt = $pdo->prepare("SELECT category_id FROM mrs_category WHERE category_name = ?");
        $stmt->execute([$category_name]);
    }

    if ($stmt->fetch()) {
        $_SESSION['error_message'] = '品类名称已存在';
        if ($category_id) {
            header('Location: /mrs/be/index.php?action=category_edit&id=' . $category_id);
        } else {
            header('Location: /mrs/be/index.php?action=category_edit');
        }
        exit;
    }

    if ($category_id) {
        // 更新现有品类
        $stmt = $pdo->prepare("
            UPDATE mrs_category SET
                category_name = ?,
                category_code = ?,
                updated_at = NOW()
            WHERE category_id = ?
        ");
        $stmt->execute([$category_name, $category_code ?: null, $category_id]);
        $_SESSION['success_message'] = '品类更新成功';
    } else {
        // 创建新品类
        $stmt = $pdo->prepare("
            INSERT INTO mrs_category (category_name, category_code, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
        ");
        $stmt->execute([$category_name, $category_code ?: null]);
        $_SESSION['success_message'] = '品类创建成功';
    }

    header('Location: /mrs/be/index.php?action=category_list');
    exit;

} catch (PDOException $e) {
    mrs_log("Failed to save category: " . $e->getMessage(), 'ERROR');
    $_SESSION['error_message'] = '保存失败：' . $e->getMessage();

    if ($category_id) {
        header('Location: /mrs/be/index.php?action=category_edit&id=' . $category_id);
    } else {
        header('Location: /mrs/be/index.php?action=category_edit');
    }
    exit;
}

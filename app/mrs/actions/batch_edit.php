<?php
// Action: batch_edit.php - 批次编辑页面

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

$batch_id = $_GET['id'] ?? null;

if (!$batch_id) {
    $_SESSION['error_message'] = '批次ID缺失';
    header('Location: /mrs/be/index.php?action=batch_list');
    exit;
}

try {
    $pdo = get_db_connection();

    // 获取批次信息
    $stmt = $pdo->prepare("SELECT * FROM mrs_batch WHERE batch_id = ?");
    $stmt->execute([$batch_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        $_SESSION['error_message'] = '批次不存在';
        header('Location: /mrs/be/index.php?action=batch_list');
        exit;
    }

    // 只能编辑草稿和收货中状态的批次
    if (!in_array($batch['batch_status'], ['draft', 'receiving'])) {
        $_SESSION['error_message'] = '只能编辑草稿或收货中状态的批次';
        header('Location: /mrs/be/index.php?action=batch_detail&id=' . $batch_id);
        exit;
    }

} catch (PDOException $e) {
    mrs_log("Failed to load batch: " . $e->getMessage(), 'ERROR');
    $_SESSION['error_message'] = '加载批次失败';
    header('Location: /mrs/be/index.php?action=batch_list');
    exit;
}

$page_title = "编辑批次";
$action = 'batch_edit';

require_once MRS_VIEW_PATH . '/batch_edit.php';

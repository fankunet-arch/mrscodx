<?php
// Action: batch_save.php - 保存批次信息

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mrs/be/index.php?action=batch_list');
    exit;
}

$batch_id = $_POST['batch_id'] ?? null;
$batch_date = $_POST['batch_date'] ?? '';
$location_name = trim($_POST['location_name'] ?? '');
$remark = trim($_POST['remark'] ?? '');

if (!$batch_id || !$batch_date || !$location_name) {
    $_SESSION['error_message'] = '请填写所有必填字段';
    header('Location: /mrs/be/index.php?action=batch_edit&id=' . $batch_id);
    exit;
}

try {
    $pdo = get_db_connection();

    // 检查批次是否存在且可编辑
    $stmt = $pdo->prepare("SELECT batch_status FROM mrs_batch WHERE batch_id = ?");
    $stmt->execute([$batch_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        $_SESSION['error_message'] = '批次不存在';
        header('Location: /mrs/be/index.php?action=batch_list');
        exit;
    }

    if (!in_array($batch['batch_status'], ['draft', 'receiving'])) {
        $_SESSION['error_message'] = '只能编辑草稿或收货中状态的批次';
        header('Location: /mrs/be/index.php?action=batch_detail&id=' . $batch_id);
        exit;
    }

    // 更新批次信息
    $stmt = $pdo->prepare("
        UPDATE mrs_batch SET
            batch_date = ?,
            location_name = ?,
            remark = ?,
            updated_at = NOW()
        WHERE batch_id = ?
    ");

    $stmt->execute([$batch_date, $location_name, $remark, $batch_id]);

    $_SESSION['success_message'] = '批次信息更新成功';
    header('Location: /mrs/be/index.php?action=batch_detail&id=' . $batch_id);
    exit;

} catch (PDOException $e) {
    mrs_log("Failed to save batch: " . $e->getMessage(), 'ERROR');
    $_SESSION['error_message'] = '保存失败：' . $e->getMessage();
    header('Location: /mrs/be/index.php?action=batch_edit&id=' . $batch_id);
    exit;
}

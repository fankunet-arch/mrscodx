<?php
// Action: batch_create_save.php - 保存新建批次

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mrs/be/index.php?action=batch_list');
    exit;
}

$batch_date = trim($_POST['batch_date'] ?? '');
$location_name = trim($_POST['location_name'] ?? '');
$remark = trim($_POST['remark'] ?? '');

// 验证必填字段
if (empty($batch_date) || empty($location_name)) {
    $_SESSION['error_message'] = '收货日期和收货地点为必填项';
    header('Location: /mrs/be/index.php?action=batch_create');
    exit;
}

try {
    $pdo = get_db_connection();

    // 生成批次编号（格式：IB-YYYYMMDD-NNNN）
    $date_prefix = 'IB-' . date('Ymd', strtotime($batch_date));

    // 查找当天最大序号
    $stmt = $pdo->prepare("
        SELECT batch_code
        FROM mrs_batch
        WHERE batch_code LIKE ?
        ORDER BY batch_code DESC
        LIMIT 1
    ");
    $stmt->execute([$date_prefix . '%']);
    $last_batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($last_batch) {
        // 提取序号并加1
        $last_seq = intval(substr($last_batch['batch_code'], -4));
        $new_seq = $last_seq + 1;
    } else {
        $new_seq = 1;
    }

    $batch_code = $date_prefix . '-' . str_pad($new_seq, 4, '0', STR_PAD_LEFT);

    // 插入新批次
    $stmt = $pdo->prepare("
        INSERT INTO mrs_batch (
            batch_code, batch_date, location_name, batch_status, remark, created_at, updated_at
        ) VALUES (?, ?, ?, 'draft', ?, NOW(), NOW())
    ");

    $stmt->execute([
        $batch_code,
        $batch_date,
        $location_name,
        $remark
    ]);

    $batch_id = $pdo->lastInsertId();

    mrs_log("Created new batch: {$batch_code}", 'INFO');
    $_SESSION['success_message'] = '批次创建成功';

    // 跳转到批次详情页面
    header('Location: /mrs/be/index.php?action=batch_detail&id=' . $batch_id);
    exit;

} catch (PDOException $e) {
    mrs_log("Failed to create batch: " . $e->getMessage(), 'ERROR');
    $_SESSION['error_message'] = '创建批次失败：' . $e->getMessage();
    header('Location: /mrs/be/index.php?action=batch_create');
    exit;
}

<?php
// Action: outbound_save.php - 保存出库记录

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mrs/be/index.php?action=outbound_list');
    exit;
}

$outbound_date = trim($_POST['outbound_date'] ?? '');
$record_date = trim($_POST['record_date'] ?? '');
$sku_id = intval($_POST['sku_id'] ?? 0);
$case_qty = floatval($_POST['case_qty'] ?? 0);
$single_qty = floatval($_POST['single_qty'] ?? 0);
$destination = trim($_POST['destination'] ?? '');
$remark = trim($_POST['remark'] ?? '');

// 验证必填字段
if (empty($outbound_date) || $sku_id <= 0 || empty($destination)) {
    $_SESSION['error_message'] = '请填写所有必填字段';
    header('Location: /mrs/be/index.php?action=outbound_create');
    exit;
}

// 验证数量
if ($case_qty <= 0 && $single_qty <= 0) {
    $_SESSION['error_message'] = '请至少填写箱数或散件数';
    header('Location: /mrs/be/index.php?action=outbound_create');
    exit;
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // 获取SKU信息
    $stmt = $pdo->prepare("
        SELECT sku_name, standard_unit, case_unit_name, case_to_standard_qty
        FROM mrs_sku
        WHERE sku_id = ?
    ");
    $stmt->execute([$sku_id]);
    $sku = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sku) {
        $pdo->rollBack();
        $_SESSION['error_message'] = '物料不存在';
        header('Location: /mrs/be/index.php?action=outbound_create');
        exit;
    }

    // 计算总标准数量
    $case_spec = floatval($sku['case_to_standard_qty'] ?? 0);
    $total_qty = ($case_qty * $case_spec) + $single_qty;

    // 生成出库单号（格式：OUT-YYYYMMDD-NNN）
    $date_prefix = 'OUT-' . date('Ymd', strtotime($outbound_date));

    $stmt = $pdo->prepare("
        SELECT outbound_code
        FROM mrs_outbound_order
        WHERE outbound_code LIKE ?
        ORDER BY outbound_code DESC
        LIMIT 1
    ");
    $stmt->execute([$date_prefix . '%']);
    $last_outbound = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($last_outbound) {
        $last_seq = intval(substr($last_outbound['outbound_code'], -3));
        $new_seq = $last_seq + 1;
    } else {
        $new_seq = 1;
    }

    $outbound_code = $date_prefix . '-' . str_pad($new_seq, 3, '0', STR_PAD_LEFT);

    // 插入出库单主表（直接确认状态）
    $stmt = $pdo->prepare("
        INSERT INTO mrs_outbound_order (
            outbound_code, outbound_date, outbound_type, status,
            location_name, remark, created_at, updated_at
        ) VALUES (?, ?, 1, 'confirmed', ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
        $outbound_code,
        $outbound_date,
        $destination,
        $remark
    ]);

    $outbound_order_id = $pdo->lastInsertId();

    // 插入出库明细
    $stmt = $pdo->prepare("
        INSERT INTO mrs_outbound_order_item (
            outbound_order_id, sku_id, sku_name, unit_name, case_unit_name,
            case_to_standard_qty, outbound_case_qty, outbound_single_qty,
            total_standard_qty, remark, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    $stmt->execute([
        $outbound_order_id,
        $sku_id,
        $sku['sku_name'],
        $sku['standard_unit'],
        $sku['case_unit_name'],
        $case_spec,
        $case_qty,
        $single_qty,
        $total_qty,
        $remark
    ]);

    $pdo->commit();

    mrs_log("Created outbound order: {$outbound_code}", 'INFO');
    $_SESSION['success_message'] = '出库记录保存成功';

    header('Location: /mrs/be/index.php?action=outbound_list');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mrs_log("Failed to save outbound: " . $e->getMessage(), 'ERROR');
    $_SESSION['error_message'] = '保存失败：' . $e->getMessage();
    header('Location: /mrs/be/index.php?action=outbound_create');
    exit;
}

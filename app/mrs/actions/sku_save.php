<?php
// Action: sku_save.php - 保存SKU

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mrs/be/index.php?action=sku_list');
    exit;
}

$sku_id = $_POST['sku_id'] ?? null;
$sku_name = trim($_POST['sku_name'] ?? '');
$sku_code = trim($_POST['sku_code'] ?? '');
$category_id = $_POST['category_id'] ?? null;
$brand_name = trim($_POST['brand_name'] ?? '');
$is_precise_item = $_POST['is_precise_item'] ?? 1;
$standard_unit = trim($_POST['standard_unit'] ?? '');
$case_unit_name = trim($_POST['case_unit_name'] ?? '');
$case_to_standard_qty = $_POST['case_to_standard_qty'] ?? null;
$note = trim($_POST['note'] ?? '');

// 验证必填字段
if (empty($sku_name) || empty($sku_code) || empty($category_id) || empty($brand_name) || empty($standard_unit)) {
    $_SESSION['error_message'] = '请填写所有必填字段';
    if ($sku_id) {
        header('Location: /mrs/be/index.php?action=sku_edit&id=' . $sku_id);
    } else {
        header('Location: /mrs/be/index.php?action=sku_edit');
    }
    exit;
}

try {
    $pdo = get_db_connection();

    if ($sku_id) {
        // 更新现有SKU
        $stmt = $pdo->prepare("
            UPDATE mrs_sku SET
                sku_name = ?,
                sku_code = ?,
                category_id = ?,
                brand_name = ?,
                is_precise_item = ?,
                standard_unit = ?,
                case_unit_name = ?,
                case_to_standard_qty = ?,
                note = ?,
                updated_at = NOW()
            WHERE sku_id = ?
        ");

        $stmt->execute([
            $sku_name,
            $sku_code,
            $category_id,
            $brand_name,
            $is_precise_item,
            $standard_unit,
            $case_unit_name ?: null,
            $case_to_standard_qty ?: null,
            $note ?: null,
            $sku_id
        ]);

        $_SESSION['success_message'] = 'SKU更新成功';
    } else {
        // 创建新SKU
        $stmt = $pdo->prepare("
            INSERT INTO mrs_sku (
                sku_name, sku_code, category_id, brand_name,
                is_precise_item, standard_unit, case_unit_name,
                case_to_standard_qty, note, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $sku_name,
            $sku_code,
            $category_id,
            $brand_name,
            $is_precise_item,
            $standard_unit,
            $case_unit_name ?: null,
            $case_to_standard_qty ?: null,
            $note ?: null
        ]);

        $_SESSION['success_message'] = 'SKU创建成功';
    }

    header('Location: /mrs/be/index.php?action=sku_list');
    exit;

} catch (PDOException $e) {
    mrs_log("Failed to save SKU: " . $e->getMessage(), 'ERROR');
    $_SESSION['error_message'] = '保存失败：' . $e->getMessage();

    if ($sku_id) {
        header('Location: /mrs/be/index.php?action=sku_edit&id=' . $sku_id);
    } else {
        header('Location: /mrs/be/index.php?action=sku_edit');
    }
    exit;
}

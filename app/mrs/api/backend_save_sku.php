<?php
/**
 * MRS 物料收发管理系统 - 后台API: 保存SKU
 * 文件路径: app/mrs/api/backend_save_sku.php
 * 说明: 创建或更新品牌SKU
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

try {
    // 获取POST数据
    $input = get_json_input();

    if (!$input) {
        json_response(false, null, '无效的请求数据');
    }

    // 获取数据库连接
    $pdo = get_db_connection();

    // 判断是新建还是更新
    $skuId = $input['sku_id'] ?? null;

    // 如果是仅更新状态
    if ($skuId && isset($input['status']) && count($input) === 2) {
        $updateStatusSql = "UPDATE mrs_sku SET status = :status, updated_at = NOW(6) WHERE sku_id = :sku_id";
        $updateStatusStmt = $pdo->prepare($updateStatusSql);
        $updateStatusStmt->bindValue(':status', $input['status']);
        $updateStatusStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $updateStatusStmt->execute();

        mrs_log("SKU状态更新成功: sku_id={$skuId}, status={$input['status']}", 'INFO');
        json_response(true, ['sku_id' => $skuId], 'SKU状态更新成功');
        exit;
    }

    // 验证必填字段
    $required = ['sku_name', 'category_id', 'brand_name', 'sku_code', 'standard_unit'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            json_response(false, null, "缺少必填字段: {$field}");
        }
    }

    // [FIX] 验证箱规数据有效性
    if (isset($input['case_to_standard_qty']) && $input['case_to_standard_qty'] !== '') {
        if (!is_numeric($input['case_to_standard_qty']) || floatval($input['case_to_standard_qty']) <= 0) {
            json_response(false, null, '箱规换算数量必须为大于0的数字');
        }
    }

    if ($skuId) {
        // [FIX] 更新时检查SKU编码是否与其他记录重复
        $checkSql = "SELECT sku_id FROM mrs_sku WHERE sku_code = :sku_code AND sku_id != :sku_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindValue(':sku_code', $input['sku_code']);
        $checkStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $checkStmt->execute();

        if ($checkStmt->fetch()) {
            json_response(false, null, 'SKU编码已被其他记录使用');
        }

        // [FIX] 检查SKU名称是否与其他记录重复（同品牌下）
        // 这可能导致批量导入时匹配歧义
        $checkNameSql = "SELECT sku_id, brand_name FROM mrs_sku
                         WHERE sku_name = :sku_name
                         AND brand_name = :brand_name
                         AND sku_id != :sku_id";
        $checkNameStmt = $pdo->prepare($checkNameSql);
        $checkNameStmt->bindValue(':sku_name', $input['sku_name']);
        $checkNameStmt->bindValue(':brand_name', $input['brand_name']);
        $checkNameStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $checkNameStmt->execute();

        if ($checkNameStmt->fetch()) {
            json_response(false, null, '同品牌下已存在相同名称的SKU，这可能导致批量导入时出现匹配歧义');
        }

        // 更新现有SKU
        $sql = "UPDATE mrs_sku SET
                    category_id = :category_id,
                    brand_name = :brand_name,
                    sku_name = :sku_name,
                    sku_code = :sku_code,
                    is_precise_item = :is_precise_item,
                    standard_unit = :standard_unit,
                    case_unit_name = :case_unit_name,
                    case_to_standard_qty = :case_to_standard_qty,
                    pack_unit_name = :pack_unit_name,
                    pack_to_standard_qty = :pack_to_standard_qty,
                    note = :note,
                    updated_at = NOW(6)
                WHERE sku_id = :sku_id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':category_id', $input['category_id'], PDO::PARAM_INT);
        $stmt->bindValue(':brand_name', $input['brand_name']);
        $stmt->bindValue(':sku_name', $input['sku_name']);
        $stmt->bindValue(':sku_code', $input['sku_code']);
        $stmt->bindValue(':is_precise_item', $input['is_precise_item'] ?? 1, PDO::PARAM_INT);
        $stmt->bindValue(':standard_unit', $input['standard_unit']);
        $stmt->bindValue(':case_unit_name', $input['case_unit_name'] ?? null);
        $stmt->bindValue(':case_to_standard_qty', $input['case_to_standard_qty'] ?? null);
        $stmt->bindValue(':pack_unit_name', $input['pack_unit_name'] ?? null);
        $stmt->bindValue(':pack_to_standard_qty', $input['pack_to_standard_qty'] ?? null);
        $stmt->bindValue(':note', $input['note'] ?? '');
        $stmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $stmt->execute();

        mrs_log("SKU更新成功: sku_id={$skuId}", 'INFO', $input);

        json_response(true, ['sku_id' => $skuId], 'SKU更新成功');

    } else {
        // 检查SKU编码是否已存在
        $checkSql = "SELECT sku_id FROM mrs_sku WHERE sku_code = :sku_code";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindValue(':sku_code', $input['sku_code']);
        $checkStmt->execute();

        if ($checkStmt->fetch()) {
            json_response(false, null, 'SKU编码已存在');
        }

        // [FIX] 检查SKU名称是否已存在（同品牌下）
        // 防止批量导入时出现匹配歧义
        $checkNameSql = "SELECT sku_id, brand_name FROM mrs_sku
                         WHERE sku_name = :sku_name
                         AND brand_name = :brand_name";
        $checkNameStmt = $pdo->prepare($checkNameSql);
        $checkNameStmt->bindValue(':sku_name', $input['sku_name']);
        $checkNameStmt->bindValue(':brand_name', $input['brand_name']);
        $checkNameStmt->execute();

        if ($checkNameStmt->fetch()) {
            json_response(false, null, '同品牌下已存在相同名称的SKU，这可能导致批量导入时出现匹配歧义');
        }

        // 创建新SKU
        $sql = "INSERT INTO mrs_sku (
                    category_id,
                    brand_name,
                    sku_name,
                    sku_code,
                    is_precise_item,
                    standard_unit,
                    case_unit_name,
                    case_to_standard_qty,
                    pack_unit_name,
                    pack_to_standard_qty,
                    note,
                    created_at,
                    updated_at
                ) VALUES (
                    :category_id,
                    :brand_name,
                    :sku_name,
                    :sku_code,
                    :is_precise_item,
                    :standard_unit,
                    :case_unit_name,
                    :case_to_standard_qty,
                    :pack_unit_name,
                    :pack_to_standard_qty,
                    :note,
                    NOW(6),
                    NOW(6)
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':category_id', $input['category_id'], PDO::PARAM_INT);
        $stmt->bindValue(':brand_name', $input['brand_name']);
        $stmt->bindValue(':sku_name', $input['sku_name']);
        $stmt->bindValue(':sku_code', $input['sku_code']);
        $stmt->bindValue(':is_precise_item', $input['is_precise_item'] ?? 1, PDO::PARAM_INT);
        $stmt->bindValue(':standard_unit', $input['standard_unit']);
        $stmt->bindValue(':case_unit_name', $input['case_unit_name'] ?? null);
        $stmt->bindValue(':case_to_standard_qty', $input['case_to_standard_qty'] ?? null);
        $stmt->bindValue(':pack_unit_name', $input['pack_unit_name'] ?? null);
        $stmt->bindValue(':pack_to_standard_qty', $input['pack_to_standard_qty'] ?? null);
        $stmt->bindValue(':note', $input['note'] ?? '');
        $stmt->execute();

        $newSkuId = $pdo->lastInsertId();

        mrs_log("新SKU创建成功: sku_id={$newSkuId}", 'INFO', $input);

        json_response(true, ['sku_id' => $newSkuId], 'SKU创建成功');
    }

} catch (PDOException $e) {
    mrs_log('保存SKU失败: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('保存SKU异常: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

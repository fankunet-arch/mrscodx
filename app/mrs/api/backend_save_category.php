<?php
/**
 * MRS 物料收发管理系统 - 后台API: 保存品类
 * 文件路径: app/mrs/api/backend_save_category.php
 * 说明: 创建或更新品类
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

    // 验证必填字段
    if (empty($input['category_name'])) {
        json_response(false, null, '缺少品类名称');
    }

    // 获取数据库连接
    $pdo = get_db_connection();

    // 判断是新建还是更新
    $categoryId = $input['category_id'] ?? null;

    if ($categoryId) {
        // [FIX] 检查品类名称是否与其他记录重复
        $checkSql = "SELECT category_id FROM mrs_category WHERE category_name = :category_name AND category_id != :category_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindValue(':category_name', $input['category_name']);
        $checkStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $checkStmt->execute();

        if ($checkStmt->fetch()) {
            json_response(false, null, '品类名称已存在');
        }

        // 更新现有品类
        $sql = "UPDATE mrs_category SET
                    category_name = :category_name,
                    category_code = :category_code,
                    updated_at = NOW(6)
                WHERE category_id = :category_id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':category_name', $input['category_name']);
        $stmt->bindValue(':category_code', $input['category_code'] ?? null);
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->execute();

        mrs_log("品类更新成功: category_id={$categoryId}", 'INFO', $input);

        json_response(true, ['category_id' => $categoryId], '品类更新成功');

    } else {
        // 检查品类名称是否已存在
        $checkSql = "SELECT category_id FROM mrs_category WHERE category_name = :category_name";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindValue(':category_name', $input['category_name']);
        $checkStmt->execute();

        if ($checkStmt->fetch()) {
            json_response(false, null, '品类名称已存在');
        }

        // 创建新品类
        $sql = "INSERT INTO mrs_category (
                    category_name,
                    category_code,
                    created_at,
                    updated_at
                ) VALUES (
                    :category_name,
                    :category_code,
                    NOW(6),
                    NOW(6)
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':category_name', $input['category_name']);
        $stmt->bindValue(':category_code', $input['category_code'] ?? null);
        $stmt->execute();

        $newCategoryId = $pdo->lastInsertId();

        mrs_log("新品类创建成功: category_id={$newCategoryId}", 'INFO', $input);

        json_response(true, ['category_id' => $newCategoryId], '品类创建成功');
    }

} catch (PDOException $e) {
    mrs_log('保存品类失败: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('保存品类异常: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

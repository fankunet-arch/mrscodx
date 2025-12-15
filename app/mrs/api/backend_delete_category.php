<?php
/**
 * MRS 物料收发管理系统 - 后台API: 删除品类
 * 文件路径: app/mrs/api/backend_delete_category.php
 * 说明: 删除品类
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

    if (!$input || empty($input['category_id'])) {
        json_response(false, null, '缺少品类ID');
    }

    $categoryId = intval($input['category_id']);

    // 获取数据库连接
    $pdo = get_db_connection();

    // 检查品类是否被使用
    $checkSql = "SELECT COUNT(*) FROM mrs_sku WHERE category_id = :category_id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    $checkStmt->execute();
    $count = $checkStmt->fetchColumn();

    if ($count > 0) {
        json_response(false, null, '该品类下有SKU,不能删除');
    }

    // 删除品类
    $deleteSql = "DELETE FROM mrs_category WHERE category_id = :category_id";
    $deleteStmt = $pdo->prepare($deleteSql);
    $deleteStmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    $deleteStmt->execute();

    if ($deleteStmt->rowCount() === 0) {
        json_response(false, null, '品类不存在');
    }

    mrs_log("品类删除成功: category_id={$categoryId}", 'INFO');

    json_response(true, null, '品类删除成功');

} catch (PDOException $e) {
    mrs_log('删除品类失败: ' . $e->getMessage(), 'ERROR', ['category_id' => $categoryId ?? null]);
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('删除品类异常: ' . $e->getMessage(), 'ERROR', ['category_id' => $categoryId ?? null]);
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

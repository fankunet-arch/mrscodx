<?php
/**
 * MRS 物料收发管理系统 - 后台API: 删除SKU
 * 文件路径: app/mrs/api/backend_delete_sku.php
 * 说明: 删除品牌SKU
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

    if (!$input || empty($input['sku_id'])) {
        json_response(false, null, '缺少SKU ID');
    }

    $skuId = intval($input['sku_id']);

    // 获取数据库连接
    $pdo = get_db_connection();

    // 检查SKU是否被使用
    $checkSql = "SELECT COUNT(*) FROM mrs_batch_raw_record WHERE sku_id = :sku_id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
    $checkStmt->execute();
    $count = $checkStmt->fetchColumn();

    if ($count > 0) {
        json_response(false, null, '该SKU已被使用,不能删除');
    }

    // 删除SKU
    $deleteSql = "DELETE FROM mrs_sku WHERE sku_id = :sku_id";
    $deleteStmt = $pdo->prepare($deleteSql);
    $deleteStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
    $deleteStmt->execute();

    if ($deleteStmt->rowCount() === 0) {
        json_response(false, null, 'SKU不存在');
    }

    mrs_log("SKU删除成功: sku_id={$skuId}", 'INFO');

    json_response(true, null, 'SKU删除成功');

} catch (PDOException $e) {
    mrs_log('删除SKU失败: ' . $e->getMessage(), 'ERROR', ['sku_id' => $skuId ?? null]);
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('删除SKU异常: ' . $e->getMessage(), 'ERROR', ['sku_id' => $skuId ?? null]);
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

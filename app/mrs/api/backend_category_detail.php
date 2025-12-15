<?php
/**
 * MRS 物料收发管理系统 - 后台API: 获取品类详情
 * 文件路径: app/mrs/api/backend_category_detail.php
 * 说明: 根据ID获取品类详细信息
 */

// 定义API入口
if (!defined('MRS_ENTRY')) {
    define('MRS_ENTRY', true);
}

// 加载配置 (如果尚未加载)
if (!defined('MRS_LIB_PATH')) {
    require_once __DIR__ . '/../config_mrs/env_mrs.php';
}
require_once MRS_LIB_PATH . '/mrs_lib.php';

try {
    // 获取参数
    $categoryId = $_GET['category_id'] ?? null;

    if (!$categoryId) {
        json_response(false, null, '缺少参数: category_id');
    }

    // 获取数据库连接
    $pdo = get_db_connection();

    $sql = "SELECT
                category_id,
                category_name,
                category_code,
                created_at,
                updated_at
            FROM mrs_category
            WHERE category_id = :category_id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
    $stmt->execute();

    $category = $stmt->fetch();

    if ($category) {
        json_response(true, $category);
    } else {
        json_response(false, null, '未找到指定的品类');
    }

} catch (Exception $e) {
    mrs_log('获取品类详情异常: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '系统错误');
}

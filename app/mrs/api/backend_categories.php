<?php
/**
 * MRS 物料收发管理系统 - 后台API: 获取品类列表
 * 文件路径: app/mrs/api/backend_categories.php
 * 说明: 获取品类列表
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

try {
    // 获取数据库连接
    $pdo = get_db_connection();

    // 获取筛选参数
    $search = $_GET['search'] ?? '';

    // 构建SQL查询
    $sql = "SELECT * FROM mrs_category WHERE 1=1";
    $params = [];

    if ($search) {
        // [FIX] Use unique parameter names to avoid PDO invalid parameter number error
        $sql .= " AND (category_name LIKE :search1 OR category_code LIKE :search2)";
        $params['search1'] = '%' . $search . '%';
        $params['search2'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY category_name ASC";

    // 准备和执行查询
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();

    $categories = $stmt->fetchAll();

    json_response(true, ['categories' => $categories]);

} catch (PDOException $e) {
    mrs_log('获取品类列表失败: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('获取品类列表异常: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

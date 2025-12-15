<?php
/**
 * MRS 物料收发管理系统 - 后台API: 获取SKU列表
 * 文件路径: app/mrs/api/backend_skus.php
 * 说明: 获取品牌SKU列表,支持筛选和分页
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
    $categoryId = $_GET['category_id'] ?? '';
    $isPrecise = $_GET['is_precise_item'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $pageSize = max(1, min(100, intval($_GET['page_size'] ?? 50)));
    $offset = ($page - 1) * $pageSize;

    // 构建SQL查询
    $sql = "SELECT
                s.*,
                c.category_name
            FROM mrs_sku s
            LEFT JOIN mrs_category c ON s.category_id = c.category_id
            WHERE 1=1";
    $params = [];

    if ($search) {
        $sql .= " AND (s.sku_name LIKE :search1 OR s.brand_name LIKE :search2 OR s.sku_code LIKE :search3)";
    }

    if ($categoryId !== '') {
        $sql .= " AND s.category_id = :category_id";
        $params['category_id'] = $categoryId;
    }

    if ($isPrecise !== '') {
        $sql .= " AND s.is_precise_item = :is_precise";
        $params['is_precise'] = $isPrecise;
    }

    // 排序
    $sql .= " ORDER BY s.created_at DESC";

    // 分页
    $sql .= " LIMIT :limit OFFSET :offset";

    // 准备和执行查询
    $stmt = $pdo->prepare($sql);

    if ($search) {
        $searchTerm = '%' . $search . '%';
        $stmt->bindValue(':search1', $searchTerm);
        $stmt->bindValue(':search2', $searchTerm);
        $stmt->bindValue(':search3', $searchTerm);
    }

    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $skus = $stmt->fetchAll();

    json_response(true, ['skus' => $skus]);

} catch (PDOException $e) {
    mrs_log('获取SKU列表失败: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('获取SKU列表异常: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

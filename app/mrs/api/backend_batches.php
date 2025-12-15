<?php
/**
 * MRS 物料收发管理系统 - 后台API: 获取批次列表
 * 文件路径: app/mrs/api/backend_batches.php
 * 说明: 获取收货批次列表,支持筛选和分页
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
    $status = $_GET['status'] ?? '';
    $dateStart = $_GET['date_start'] ?? '';
    $dateEnd = $_GET['date_end'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $pageSize = max(1, min(100, intval($_GET['page_size'] ?? 20)));
    $offset = ($page - 1) * $pageSize;

    // 构建SQL查询
    $sql = "SELECT * FROM mrs_batch WHERE 1=1";
    $params = [];

    if ($search) {
        // [FIX] Use unique parameter names to avoid PDO reuse issues on some drivers
        $sql .= " AND (batch_code LIKE :search1 OR location_name LIKE :search2 OR remark LIKE :search3)";
        $searchTerm = '%' . $search . '%';
        $params['search1'] = $searchTerm;
        $params['search2'] = $searchTerm;
        $params['search3'] = $searchTerm;
    }

    if ($status) {
        $sql .= " AND batch_status = :status";
        $params['status'] = $status;
    }

    if ($dateStart) {
        $sql .= " AND batch_date >= :date_start";
        $params['date_start'] = $dateStart;
    }

    if ($dateEnd) {
        $sql .= " AND batch_date <= :date_end";
        $params['date_end'] = $dateEnd;
    }

    // 排序
    $sql .= " ORDER BY batch_date DESC, created_at DESC";

    // 分页
    $sql .= " LIMIT :limit OFFSET :offset";

    // 准备和执行查询
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $batches = $stmt->fetchAll();

    // 获取总数
    $countSql = "SELECT COUNT(*) FROM mrs_batch WHERE 1=1";
    if ($search) {
        $countSql .= " AND (batch_code LIKE :search1 OR location_name LIKE :search2 OR remark LIKE :search3)";
    }
    if ($status) {
        $countSql .= " AND batch_status = :status";
    }
    if ($dateStart) {
        $countSql .= " AND batch_date >= :date_start";
    }
    if ($dateEnd) {
        $countSql .= " AND batch_date <= :date_end";
    }

    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        if ($key !== 'limit' && $key !== 'offset') {
            $countStmt->bindValue(':' . $key, $value);
        }
    }
    $countStmt->execute();
    $total = $countStmt->fetchColumn();

    // 返回成功响应
    json_response(true, [
        'batches' => $batches,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => intval($total),
            'total_pages' => ceil($total / $pageSize)
        ]
    ]);

} catch (PDOException $e) {
    mrs_log('获取批次列表失败: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('获取批次列表异常: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

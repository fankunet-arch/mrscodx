<?php
/**
 * API: Usage Statistics (用量统计)
 * 文件路径: app/mrs/api/usage_statistics.php
 * 说明: 统计商品出货情况（整箱+拆零），用于门店订货参考
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    mrs_json_response(false, null, '非法请求方式');
}

// 接收参数
$product_name = trim($_GET['product_name'] ?? '');
$destination = trim($_GET['destination'] ?? '');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');
$group_by = trim($_GET['group_by'] ?? 'destination'); // destination, product, both

try {
    // 构建基础查询
    $where_conditions = [];
    $params = [];

    if (!empty($product_name)) {
        $where_conditions[] = "product_name LIKE :product_name";
        $params['product_name'] = "%{$product_name}%";
    }

    if (!empty($destination)) {
        $where_conditions[] = "destination LIKE :destination";
        $params['destination'] = "%{$destination}%";
    }

    if (!empty($start_date)) {
        $where_conditions[] = "created_at >= :start_date";
        $params['start_date'] = $start_date . ' 00:00:00';
    }

    if (!empty($end_date)) {
        $where_conditions[] = "created_at <= :end_date";
        $params['end_date'] = $end_date . ' 23:59:59';
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // 根据分组方式构建查询
    switch ($group_by) {
        case 'product':
            // 按商品分组
            $sql = "
                SELECT
                    product_name,
                    COUNT(CASE WHEN outbound_type='whole' THEN 1 END) as box_count,
                    SUM(CASE WHEN outbound_type='partial' THEN deduct_qty ELSE 0 END) as partial_qty,
                    SUM(deduct_qty) as total_qty,
                    COUNT(*) as transaction_count
                FROM mrs_usage_log
                {$where_clause}
                GROUP BY product_name
                ORDER BY total_qty DESC
            ";
            break;

        case 'both':
            // 按商品和门店分组
            $sql = "
                SELECT
                    product_name,
                    destination,
                    COUNT(CASE WHEN outbound_type='whole' THEN 1 END) as box_count,
                    SUM(CASE WHEN outbound_type='partial' THEN deduct_qty ELSE 0 END) as partial_qty,
                    SUM(deduct_qty) as total_qty,
                    COUNT(*) as transaction_count
                FROM mrs_usage_log
                {$where_clause}
                GROUP BY product_name, destination
                ORDER BY product_name, total_qty DESC
            ";
            break;

        case 'destination':
        default:
            // 按门店分组（默认）
            $sql = "
                SELECT
                    destination,
                    product_name,
                    COUNT(CASE WHEN outbound_type='whole' THEN 1 END) as box_count,
                    SUM(CASE WHEN outbound_type='partial' THEN deduct_qty ELSE 0 END) as partial_qty,
                    SUM(deduct_qty) as total_qty,
                    COUNT(*) as transaction_count
                FROM mrs_usage_log
                {$where_clause}
                GROUP BY destination, product_name
                ORDER BY destination, total_qty DESC
            ";
            break;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $statistics = $stmt->fetchAll();

    // 计算总计
    $total_summary = [
        'total_box_count' => 0,
        'total_partial_qty' => 0,
        'total_qty' => 0,
        'total_transactions' => 0
    ];

    foreach ($statistics as $row) {
        $total_summary['total_box_count'] += intval($row['box_count']);
        $total_summary['total_partial_qty'] += floatval($row['partial_qty']);
        $total_summary['total_qty'] += floatval($row['total_qty']);
        $total_summary['total_transactions'] += intval($row['transaction_count']);
    }

    // 返回结果
    mrs_json_response(true, [
        'statistics' => $statistics,
        'summary' => $total_summary,
        'filters' => [
            'product_name' => $product_name,
            'destination' => $destination,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'group_by' => $group_by
        ]
    ], '统计查询成功');

} catch (PDOException $e) {
    mrs_log('Usage statistics error: ' . $e->getMessage(), 'ERROR');
    mrs_json_response(false, null, '统计查询失败：' . $e->getMessage());
} catch (Exception $e) {
    mrs_json_response(false, null, '统计查询失败：' . $e->getMessage());
}

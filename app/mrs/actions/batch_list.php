<?php
// app/mrs/actions/batch_list.php

// Whitelist sortable columns to prevent SQL injection
$allowed_sort_columns = [
    'batch_code',
    'batch_status',
    'created_at',
    'updated_at',
    'total_package_count',
    'verified_package_count',
    'counted_package_count'
];
$sort_column = $_GET['sort'] ?? 'created_at';
if (!in_array($sort_column, $allowed_sort_columns)) {
    $sort_column = 'created_at';
}

$sort_order = strtoupper($_GET['order'] ?? 'DESC');
if ($sort_order !== 'ASC' && $sort_order !== 'DESC') {
    $sort_order = 'DESC';
}


// 获取批次列表，带上包裹清点相关统计
$pdo = get_db_connection();
$sql = "
    SELECT
        b.*,
        COALESCE(rr.total_package_count, 0) AS total_package_count,
        COALESCE(rr.verified_package_count, 0) AS verified_package_count,
        COALESCE(ci.counted_package_count, 0) AS counted_package_count,
        0 AS adjusted_package_count
    FROM mrs_batch b
    LEFT JOIN (
        SELECT
            batch_id,
            COUNT(*) AS total_package_count,
            SUM(CASE WHEN processing_status = 'confirmed' THEN 1 ELSE 0 END) AS verified_package_count
        FROM mrs_batch_raw_record
        GROUP BY batch_id
    ) rr ON b.batch_id = rr.batch_id
    LEFT JOIN (
        SELECT batch_id, COUNT(*) AS counted_package_count
        FROM mrs_batch_confirmed_item
        GROUP BY batch_id
    ) ci ON b.batch_id = ci.batch_id
    ORDER BY {$sort_column} {$sort_order}
";
$stmt = $pdo->query($sql);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 为SPA和传统视图设置变量
$is_spa = false;
$page_title = "批次列表";
$action = 'batch_list';

require_once MRS_VIEW_PATH . '/batch_list.php';

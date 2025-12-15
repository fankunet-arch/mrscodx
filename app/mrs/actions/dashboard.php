<?php
// Action: dashboard.php

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

$page_title = "仪表盘"; // Used for the template
$action = 'dashboard'; // For highlighting the active menu item

$stats = [
    'sku_count' => 0,
    'category_count' => 0,
    'batch_count' => 0,
    'outbound_count' => 0,
    'inventory_records' => 0,
];

$recent_batches = [];
$recent_outbounds = [];
$low_inventory = [];
$local_now = new DateTime('now', new DateTimeZone('Europe/Madrid'));
$current_local_date = $local_now->format('Y-m-d');
$last_refresh_time = $local_now->format('H:i');

try {
    $pdo = get_db_connection();

    $stat_queries = [
        'sku_count' => 'SELECT COUNT(*) FROM mrs_sku',
        'category_count' => 'SELECT COUNT(*) FROM mrs_category',
        'batch_count' => 'SELECT COUNT(*) FROM mrs_batch',
        'outbound_count' => 'SELECT COUNT(*) FROM mrs_outbound_order',
        'inventory_records' => 'SELECT COUNT(*) FROM mrs_inventory',
    ];

    foreach ($stat_queries as $key => $sql) {
        $stmt = $pdo->query($sql);
        $stats[$key] = (int) $stmt->fetchColumn();
    }

    $batch_stmt = $pdo->query(
        "SELECT batch_id, batch_code, batch_date, location_name, batch_status, remark\n" .
        "FROM mrs_batch\n" .
        "ORDER BY batch_date DESC, batch_id DESC\n" .
        "LIMIT 5"
    );
    $recent_batches = $batch_stmt->fetchAll();

    $outbound_stmt = $pdo->query(
        "SELECT outbound_order_id, outbound_code, outbound_date, status, outbound_type, location_name\n" .
        "FROM mrs_outbound_order\n" .
        "ORDER BY outbound_date DESC, outbound_order_id DESC\n" .
        "LIMIT 5"
    );
    $recent_outbounds = $outbound_stmt->fetchAll();

    $inventory_stmt = $pdo->query(
        "SELECT i.inventory_id, i.current_qty, s.sku_name, s.brand_name, s.standard_unit, c.category_name\n" .
        "FROM mrs_inventory i\n" .
        "JOIN mrs_sku s ON i.sku_id = s.sku_id\n" .
        "LEFT JOIN mrs_category c ON s.category_id = c.category_id\n" .
        "ORDER BY i.current_qty ASC, i.inventory_id ASC\n" .
        "LIMIT 5"
    );
    $low_inventory = $inventory_stmt->fetchAll();
} catch (PDOException $e) {
    mrs_log('加载仪表盘数据失败: ' . $e->getMessage(), 'ERROR');
}

require_once MRS_VIEW_PATH . '/dashboard.php';

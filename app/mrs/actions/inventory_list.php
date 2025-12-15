<?php
// Action: inventory_list.php - 库存列表页面

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

// 获取筛选参数
$search = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? '';

// 获取排序参数
$sort_key = $_GET['sort'] ?? 'sku_name';
$sort_dir = strtolower($_GET['dir'] ?? 'asc');

$valid_sorts = [
    'sku_name' => 's.sku_name',
    'category' => 'c.category_name',
    'brand' => 's.brand_name',
    'current_inventory' => 'current_inventory'
];

if (!array_key_exists($sort_key, $valid_sorts)) {
    $sort_key = 'sku_name';
}

if (!in_array($sort_dir, ['asc', 'desc'])) {
    $sort_dir = 'asc';
}

try {
    $pdo = get_db_connection();

    // 获取品类列表供筛选
    $categories = $pdo->query("SELECT * FROM mrs_category ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);

    // 构建库存查询（使用子查询优化）
    $where = ['1=1'];
    $params = [];

    if (!empty($search)) {
        $where[] = "(s.sku_name LIKE ? OR s.brand_name LIKE ?)";
        $search_term = "%{$search}%";
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if (!empty($category_id)) {
        $where[] = "s.category_id = ?";
        $params[] = $category_id;
    }

    // 检查流水表/库存表是否存在，避免历史环境报错
    $hasLedgerTable = $pdo->query("SHOW TABLES LIKE 'mrs_inventory_transaction'")->rowCount() > 0;
    $hasInventoryTable = $pdo->query("SHOW TABLES LIKE 'mrs_inventory'")->rowCount() > 0;

    $currentQtyExpr = "(COALESCE(inbound.total_inbound, 0) - COALESCE(outbound.total_outbound, 0) + COALESCE(adjustment.total_adjustment, 0))";
    $inventoryJoin = '';
    $latestTxJoin = '';

    if ($hasInventoryTable) {
        $inventoryJoin = "LEFT JOIN mrs_inventory inv ON s.sku_id = inv.sku_id";
        $currentQtyExpr = "COALESCE(inv.current_qty, {$currentQtyExpr})";
    }

    if ($hasLedgerTable) {
        $latestTxJoin = "LEFT JOIN (
            SELECT t1.sku_id, t1.quantity_after
            FROM mrs_inventory_transaction t1
            INNER JOIN (
                SELECT sku_id, MAX(transaction_id) as max_id
                FROM mrs_inventory_transaction
                GROUP BY sku_id
            ) t2 ON t1.sku_id = t2.sku_id AND t1.transaction_id = t2.max_id
        ) latest_tx ON s.sku_id = latest_tx.sku_id";
        $currentQtyExpr = "COALESCE(" . ($hasInventoryTable ? 'inv.current_qty, ' : '') . "latest_tx.quantity_after, (COALESCE(inbound.total_inbound, 0) - COALESCE(outbound.total_outbound, 0) + COALESCE(adjustment.total_adjustment, 0)))";
    }

    $order_by = $valid_sorts[$sort_key] . ' ' . strtoupper($sort_dir);

    $sql = "
        SELECT
            s.sku_id,
            s.sku_name,
            s.brand_name,
            s.standard_unit,
            s.case_unit_name,
            s.case_to_standard_qty,
            c.category_name,
            COALESCE(inbound.total_inbound, 0) as total_inbound,
            COALESCE(outbound.total_outbound, 0) as total_outbound,
            COALESCE(adjustment.total_adjustment, 0) as total_adjustment,
            {$currentQtyExpr} as current_inventory
        FROM mrs_sku s
        LEFT JOIN mrs_category c ON s.category_id = c.category_id
        LEFT JOIN (
            SELECT sku_id, SUM(total_standard_qty) as total_inbound
            FROM mrs_batch_confirmed_item
            GROUP BY sku_id
        ) inbound ON s.sku_id = inbound.sku_id
        LEFT JOIN (
            SELECT i.sku_id, SUM(i.total_standard_qty) as total_outbound
            FROM mrs_outbound_order_item i
            INNER JOIN mrs_outbound_order o ON i.outbound_order_id = o.outbound_order_id
            WHERE o.status = 'confirmed'
            GROUP BY i.sku_id
        ) outbound ON s.sku_id = outbound.sku_id
        LEFT JOIN (
            SELECT sku_id, SUM(delta_qty) as total_adjustment
            FROM mrs_inventory_adjustment
            GROUP BY sku_id
        ) adjustment ON s.sku_id = adjustment.sku_id
        {$inventoryJoin}
        {$latestTxJoin}
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$order_by}, s.sku_id
        LIMIT 100
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    mrs_log("Failed to load inventory: " . $e->getMessage(), 'ERROR');
    $inventory = [];
    $categories = [];
}

$page_title = "库存管理";
$action = 'inventory_list';
$current_sort_key = $sort_key;
$current_sort_dir = $sort_dir;

require_once MRS_VIEW_PATH . '/inventory_list.php';

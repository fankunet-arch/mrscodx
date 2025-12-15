<?php
// Action: outbound_list.php - 出库单列表页面

if (!is_user_logged_in()) {
    header('Location: /mrs/be/index.php?action=login');
    exit;
}

// 获取筛选参数
$search = $_GET['search'] ?? '';
$date_start = $_GET['date_start'] ?? '';
$date_end = $_GET['date_end'] ?? '';
$status = $_GET['status'] ?? '';
$outbound_type = $_GET['outbound_type'] ?? '';

// 构建查询
try {
    $pdo = get_db_connection();

    $where = ['1=1'];
    $params = [];

    if (!empty($search)) {
        $where[] = "(outbound_code LIKE ? OR location_name LIKE ? OR remark LIKE ?)";
        $search_term = "%{$search}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if (!empty($date_start)) {
        $where[] = "outbound_date >= ?";
        $params[] = $date_start;
    }

    if (!empty($date_end)) {
        $where[] = "outbound_date <= ?";
        $params[] = $date_end;
    }

    if (!empty($status)) {
        $where[] = "status = ?";
        $params[] = $status;
    }

    if (!empty($outbound_type)) {
        $where[] = "outbound_type = ?";
        $params[] = $outbound_type;
    }

    $sql = "SELECT * FROM mrs_outbound_order WHERE " . implode(' AND ', $where) . " ORDER BY outbound_date DESC, outbound_order_id DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $outbounds = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    mrs_log("Failed to load outbound orders: " . $e->getMessage(), 'ERROR');
    $outbounds = [];
}

$page_title = "出库管理";
$action = 'outbound_list';

require_once MRS_VIEW_PATH . '/outbound_list.php';

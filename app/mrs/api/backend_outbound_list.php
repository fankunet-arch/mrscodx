<?php
/**
 * MRS Outbound Management - List Outbound Orders
 * Route: api.php?route=backend_outbound_list
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// Require Login
require_login();

try {
    $pdo = get_db_connection();

    // Pagination and Filtering
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;

    $status = $_GET['status'] ?? null;
    $type = $_GET['type'] ?? null;
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;

    $sql = "SELECT
                o.outbound_order_id,
                o.outbound_code,
                o.outbound_type,
                o.outbound_date,
                o.status,
                o.location_name,
                o.remark,
                o.created_at,
                COUNT(i.outbound_order_item_id) as item_count,
                SUM(i.total_standard_qty) as total_qty
            FROM mrs_outbound_order o
            LEFT JOIN mrs_outbound_order_item i ON o.outbound_order_id = i.outbound_order_id
            WHERE 1=1";

    $params = [];

    if ($status) {
        $sql .= " AND o.status = :status";
        $params[':status'] = $status;
    }

    if ($type) {
        $sql .= " AND o.outbound_type = :type";
        $params[':type'] = $type;
    }

    if ($startDate) {
        $sql .= " AND o.outbound_date >= :start_date";
        $params[':start_date'] = $startDate;
    }

    if ($endDate) {
        $sql .= " AND o.outbound_date <= :end_date";
        $params[':end_date'] = $endDate;
    }

    $sql .= " GROUP BY o.outbound_order_id ORDER BY o.outbound_date DESC, o.created_at DESC";
    $sql .= " LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }

    // [SELF-HEALING] Check if table exists, if not try to migrate
    try {
        $stmt->execute();
        $list = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Error 1146: Table doesn't exist
        if ($e->getCode() == '42S02' || strpos($e->getMessage(), '1146') !== false) {
            mrs_log('Missing outbound tables detected. Attempting self-healing...', 'WARNING');

            // Try to run migration 002
            $migrationFile = MRS_APP_PATH . '/../docs/migrations/002_create_outbound_tables.sql';
            if (file_exists($migrationFile)) {
                $sqlContent = file_get_contents($migrationFile);
                $statements = explode(';', $sqlContent);
                foreach ($statements as $sql) {
                    $sql = trim($sql);
                    if (empty($sql) || strpos($sql, '--') === 0) continue;
                    try {
                        $pdo->exec($sql);
                    } catch (Exception $em) {
                        mrs_log('Self-healing partial error: ' . $em->getMessage(), 'WARNING');
                    }
                }
                // Retry query
                $stmt->execute();
                $list = $stmt->fetchAll();
            } else {
                throw $e; // Re-throw if migration file missing
            }
        } else {
            throw $e; // Re-throw other errors
        }
    }

    // Total count for pagination
    $countSql = "SELECT COUNT(*) FROM mrs_outbound_order o WHERE 1=1";
    // Re-apply filters
    if ($status) $countSql .= " AND o.status = " . $pdo->quote($status);
    if ($type) $countSql .= " AND o.outbound_type = " . $pdo->quote($type);
    if ($startDate) $countSql .= " AND o.outbound_date >= " . $pdo->quote($startDate);
    if ($endDate) $countSql .= " AND o.outbound_date <= " . $pdo->quote($endDate);

    $total = $pdo->query($countSql)->fetchColumn();

    json_response(true, [
        'list' => $list,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    ]);

} catch (Exception $e) {
    mrs_log('Get outbound list failed: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, $e->getMessage());
}

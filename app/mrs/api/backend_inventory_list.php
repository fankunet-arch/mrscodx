<?php
/**
 * MRS 物料收发管理系统 - 后台API: 库存列表查询
 * 文件路径: app/mrs/api/backend_inventory_list.php
 * 说明: 获取所有有库存记录的SKU列表（包括库存为0的）
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 需要登录
require_login();

try {
    // 获取筛选参数
    $search = $_GET['search'] ?? '';
    $categoryId = $_GET['category_id'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1)); // 当前页码，默认第1页
    $limit = max(1, min(100, intval($_GET['limit'] ?? 20))); // 每页记录数，默认20，最大100
    $offset = ($page - 1) * $limit;

    // 获取数据库连接
    $pdo = get_db_connection();

    // 构建查询条件
    $whereConditions = "WHERE s.status = 'active'";
    $params = [];

    // 添加搜索条件
    if (!empty($search)) {
        $whereConditions .= " AND (s.sku_name LIKE :search OR s.brand_name LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    // 添加品类筛选
    if (!empty($categoryId)) {
        $whereConditions .= " AND s.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    // [FIX] 使用子查询优化库存计算，避免N+1查询问题
    // 一次性获取所有SKU的库存数据
    $sql = "SELECT DISTINCT
                s.sku_id,
                s.sku_name,
                s.brand_name,
                s.category_id,
                c.category_name,
                s.standard_unit,
                s.case_unit_name,
                s.case_to_standard_qty,
                s.status,
                COALESCE(inbound.total_inbound, 0) as total_inbound,
                COALESCE(outbound.total_outbound, 0) as total_outbound,
                COALESCE(adjustment.total_adjustment, 0) as total_adjustment,
                (COALESCE(inbound.total_inbound, 0) - COALESCE(outbound.total_outbound, 0) + COALESCE(adjustment.total_adjustment, 0)) as current_inventory
            FROM mrs_sku s
            LEFT JOIN mrs_category c ON s.category_id = c.category_id
            -- 入库总量子查询
            LEFT JOIN (
                SELECT sku_id, SUM(total_standard_qty) as total_inbound
                FROM mrs_batch_confirmed_item
                GROUP BY sku_id
            ) inbound ON s.sku_id = inbound.sku_id
            -- 出库总量子查询
            LEFT JOIN (
                SELECT i.sku_id, SUM(i.total_standard_qty) as total_outbound
                FROM mrs_outbound_order_item i
                JOIN mrs_outbound_order o ON i.outbound_order_id = o.outbound_order_id
                WHERE o.status = 'confirmed'
                GROUP BY i.sku_id
            ) outbound ON s.sku_id = outbound.sku_id
            -- 调整总量子查询
            LEFT JOIN (
                SELECT sku_id, SUM(delta_qty) as total_adjustment
                FROM mrs_inventory_adjustment
                GROUP BY sku_id
            ) adjustment ON s.sku_id = adjustment.sku_id
            {$whereConditions}
            -- 只显示有入库记录的SKU
            AND inbound.total_inbound IS NOT NULL
            ORDER BY s.sku_name ASC
            LIMIT :limit OFFSET :offset";

    // 计算总记录数（用于分页）
    $countSql = "SELECT COUNT(DISTINCT s.sku_id)
                 FROM mrs_sku s
                 LEFT JOIN mrs_category c ON s.category_id = c.category_id
                 INNER JOIN mrs_batch_confirmed_item ci ON s.sku_id = ci.sku_id
                 {$whereConditions}";

    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetchColumn();

    // 执行主查询
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $skus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 格式化库存数据
    $inventoryList = [];
    foreach ($skus as $sku) {
        $currentInventory = floatval($sku['current_inventory']);
        $caseSpec = floatval($sku['case_to_standard_qty'] ?? 1);
        $unit = $sku['standard_unit'];
        $caseUnit = $sku['case_unit_name'] ?? 'Box';

        // 格式化显示
        if ($caseSpec > 1 && $currentInventory > 0) {
            $cases = floor($currentInventory / $caseSpec);
            $singles = $currentInventory % $caseSpec;
            $display = "{$cases}{$caseUnit} {$singles}{$unit}";
            if ($cases == 0) $display = "{$singles}{$unit}";
            if ($singles == 0) $display = "{$cases}{$caseUnit}";
        } else {
            $display = "{$currentInventory}{$unit}";
        }

        $inventoryList[] = [
            'sku_id' => $sku['sku_id'],
            'sku_name' => $sku['sku_name'],
            'brand_name' => $sku['brand_name'],
            'category_id' => $sku['category_id'],
            'category_name' => $sku['category_name'] ?? '-',
            'standard_unit' => $unit,
            'case_unit_name' => $caseUnit,
            'case_to_standard_qty' => $caseSpec,
            'total_inbound' => floatval($sku['total_inbound']),
            'total_outbound' => floatval($sku['total_outbound']),
            'total_adjustment' => floatval($sku['total_adjustment']),
            'current_inventory' => $currentInventory,
            'display_text' => $display,
            'status' => $sku['status']
        ];
    }

    $totalPages = ceil($totalRecords / $limit);

    mrs_log("查询库存列表成功, 页码: {$page}/{$totalPages}, 记录数: " . count($inventoryList), 'INFO');

    json_response(true, [
        'inventory' => $inventoryList,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total_records' => intval($totalRecords),
            'total_pages' => $totalPages
        ]
    ]);

} catch (PDOException $e) {
    mrs_log('查询库存列表失败: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('查询库存列表异常: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

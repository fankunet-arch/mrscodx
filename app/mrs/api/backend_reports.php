<?php
/**
 * MRS 物料收发管理系统 - 后台API: 统计报表
 * 文件路径: app/mrs/api/backend_reports.php
 * 说明: 生成各类统计报表
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

try {
    // 获取报表类型和参数
    $reportType = $_GET['type'] ?? 'daily';
    $dateStart = $_GET['date_start'] ?? date('Y-m-01');
    $dateEnd = $_GET['date_end'] ?? date('Y-m-d');

    // 获取数据库连接
    $pdo = get_db_connection();

    $data = [];

    switch ($reportType) {
        case 'daily':
            // 每日收货统计
            $sql = "SELECT
                        DATE(batch_date) as date,
                        COUNT(DISTINCT batch_id) as batch_count,
                        batch_status,
                        location_name
                    FROM mrs_batch
                    WHERE batch_date BETWEEN :date_start AND :date_end
                    GROUP BY DATE(batch_date), batch_status, location_name
                    ORDER BY date DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':date_start', $dateStart);
            $stmt->bindValue(':date_end', $dateEnd);
            $stmt->execute();
            $data = $stmt->fetchAll();
            break;

        case 'monthly':
            // 月度收货统计
            $sql = "SELECT
                        DATE_FORMAT(batch_date, '%Y-%m') as month,
                        COUNT(DISTINCT batch_id) as batch_count,
                        COUNT(DISTINCT location_name) as location_count
                    FROM mrs_batch
                    WHERE batch_date BETWEEN :date_start AND :date_end
                    AND batch_status IN ('confirmed', 'posted')
                    GROUP BY month
                    ORDER BY month DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':date_start', $dateStart);
            $stmt->bindValue(':date_end', $dateEnd);
            $stmt->execute();
            $data = $stmt->fetchAll();
            break;

        case 'sku':
            // SKU收货汇总
            $sql = "SELECT
                        s.sku_name,
                        s.brand_name,
                        c.category_name,
                        SUM(ci.total_standard_qty) as total_qty,
                        s.standard_unit,
                        COUNT(DISTINCT ci.batch_id) as batch_count
                    FROM mrs_batch_confirmed_item ci
                    INNER JOIN mrs_sku s ON ci.sku_id = s.sku_id
                    LEFT JOIN mrs_category c ON s.category_id = c.category_id
                    INNER JOIN mrs_batch b ON ci.batch_id = b.batch_id
                    WHERE b.batch_date BETWEEN :date_start AND :date_end
                    AND b.batch_status IN ('confirmed', 'posted')
                    GROUP BY ci.sku_id
                    ORDER BY total_qty DESC
                    LIMIT 100";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':date_start', $dateStart);
            $stmt->bindValue(':date_end', $dateEnd);
            $stmt->execute();
            $data = $stmt->fetchAll();
            break;

        case 'category':
            // 品类收货汇总
            $sql = "SELECT
                        c.category_name,
                        COUNT(DISTINCT ci.sku_id) as sku_count,
                        SUM(ci.total_standard_qty) as total_qty,
                        COUNT(DISTINCT ci.batch_id) as batch_count
                    FROM mrs_batch_confirmed_item ci
                    INNER JOIN mrs_sku s ON ci.sku_id = s.sku_id
                    LEFT JOIN mrs_category c ON s.category_id = c.category_id
                    INNER JOIN mrs_batch b ON ci.batch_id = b.batch_id
                    WHERE b.batch_date BETWEEN :date_start AND :date_end
                    AND b.batch_status IN ('confirmed', 'posted')
                    GROUP BY c.category_id
                    ORDER BY total_qty DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':date_start', $dateStart);
            $stmt->bindValue(':date_end', $dateEnd);
            $stmt->execute();
            $data = $stmt->fetchAll();
            break;

        case 'sku_shipment':
            // SKU出库汇总
            $sql = "SELECT
                        s.sku_name,
                        s.brand_name,
                        c.category_name,
                        SUM(oi.total_standard_qty) as total_qty,
                        s.standard_unit,
                        COUNT(DISTINCT oi.outbound_order_id) as order_count
                    FROM mrs_outbound_order_item oi
                    INNER JOIN mrs_sku s ON oi.sku_id = s.sku_id
                    LEFT JOIN mrs_category c ON s.category_id = c.category_id
                    INNER JOIN mrs_outbound_order o ON oi.outbound_order_id = o.outbound_order_id
                    WHERE o.outbound_date BETWEEN :date_start AND :date_end
                    AND o.status IN ('confirmed', 'posted')
                    GROUP BY oi.sku_id
                    ORDER BY total_qty DESC
                    LIMIT 100";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':date_start', $dateStart);
            $stmt->bindValue(':date_end', $dateEnd);
            $stmt->execute();
            $data = $stmt->fetchAll();
            break;

        case 'category_shipment':
            // 品类出库汇总
            $sql = "SELECT
                        c.category_name,
                        COUNT(DISTINCT oi.sku_id) as sku_count,
                        SUM(oi.total_standard_qty) as total_qty,
                        COUNT(DISTINCT oi.outbound_order_id) as order_count
                    FROM mrs_outbound_order_item oi
                    INNER JOIN mrs_sku s ON oi.sku_id = s.sku_id
                    LEFT JOIN mrs_category c ON s.category_id = c.category_id
                    INNER JOIN mrs_outbound_order o ON oi.outbound_order_id = o.outbound_order_id
                    WHERE o.outbound_date BETWEEN :date_start AND :date_end
                    AND o.status IN ('confirmed', 'posted')
                    GROUP BY c.category_id
                    ORDER BY total_qty DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':date_start', $dateStart);
            $stmt->bindValue(':date_end', $dateEnd);
            $stmt->execute();
            $data = $stmt->fetchAll();
            break;

        case 'daily_shipment':
            // 每日出库统计
            $sql = "SELECT
                        DATE(outbound_date) as date,
                        COUNT(DISTINCT outbound_order_id) as order_count,
                        status,
                        location_name
                    FROM mrs_outbound_order
                    WHERE outbound_date BETWEEN :date_start AND :date_end
                    GROUP BY DATE(outbound_date), status, location_name
                    ORDER BY date DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':date_start', $dateStart);
            $stmt->bindValue(':date_end', $dateEnd);
            $stmt->execute();
            $data = $stmt->fetchAll();
            break;

        default:
            json_response(false, null, '不支持的报表类型');
    }

    json_response(true, [
        'type' => $reportType,
        'date_start' => $dateStart,
        'date_end' => $dateEnd,
        'data' => $data
    ]);

} catch (PDOException $e) {
    mrs_log('生成报表失败: ' . $e->getMessage(), 'ERROR', ['type' => $reportType ?? null]);
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('生成报表异常: ' . $e->getMessage(), 'ERROR', ['type' => $reportType ?? null]);
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

<?php
/**
 * API: Get Product Expiry Dates in Current Batch (Frontend)
 * 文件路径: app/express/actions/get_product_expiry_api.php
 *
 * 查询本批次中指定产品名称已有的保质期信息（前端API）
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$batch_id = (int)($_GET['batch_id'] ?? 0);
$product_name = trim($_GET['product_name'] ?? '');

if ($batch_id <= 0) {
    express_json_response(false, null, '批次ID无效');
}

if (empty($product_name)) {
    express_json_response(false, null, '产品名称不能为空');
}

try {
    // 查询本批次中该产品的所有保质期（按使用频率降序，取最常用的一个）
    $stmt = $pdo->prepare("
        SELECT
            expiry_date,
            COUNT(*) as usage_count
        FROM express_package_items
        WHERE package_id IN (
            SELECT package_id
            FROM express_package
            WHERE batch_id = :batch_id
        )
        AND product_name = :product_name
        AND expiry_date IS NOT NULL
        GROUP BY expiry_date
        ORDER BY usage_count DESC, expiry_date DESC
        LIMIT 1
    ");

    $stmt->execute([
        'batch_id' => $batch_id,
        'product_name' => $product_name
    ]);

    $result = $stmt->fetch();

    if ($result && !empty($result['expiry_date'])) {
        express_json_response(true, [
            'expiry_date' => $result['expiry_date'],
            'usage_count' => $result['usage_count']
        ]);
    } else {
        express_json_response(false, null, '未找到该产品的保质期信息');
    }

} catch (PDOException $e) {
    express_log('Failed to get product expiry: ' . $e->getMessage(), 'ERROR');
    express_json_response(false, null, '查询保质期失败');
}

<?php
/**
 * MRS 物料收发管理系统 - 后台API: 获取批次详情
 * 文件路径: app/mrs/api/backend_batch_detail.php
 * 说明: 获取单个收货批次的详细信息
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

try {
    // 获取批次ID
    $batchId = intval($_GET['batch_id'] ?? 0);

    if (!$batchId) {
        json_response(false, null, '缺少批次ID');
    }

    // 获取数据库连接
    $pdo = get_db_connection();

    // 获取批次基本信息
    $batchSql = "SELECT * FROM mrs_batch WHERE batch_id = :batch_id";
    $batchStmt = $pdo->prepare($batchSql);
    $batchStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
    $batchStmt->execute();
    $batch = $batchStmt->fetch();

    if (!$batch) {
        json_response(false, null, '批次不存在');
    }

    // 获取原始记录统计
    $rawCountSql = "SELECT COUNT(*) as count FROM mrs_batch_raw_record WHERE batch_id = :batch_id";
    $rawCountStmt = $pdo->prepare($rawCountSql);
    $rawCountStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
    $rawCountStmt->execute();
    $rawCount = $rawCountStmt->fetchColumn();

    // 获取预计清单统计
    $expectedCountSql = "SELECT COUNT(*) as count FROM mrs_batch_expected_item WHERE batch_id = :batch_id";
    $expectedCountStmt = $pdo->prepare($expectedCountSql);
    $expectedCountStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
    $expectedCountStmt->execute();
    $expectedCount = $expectedCountStmt->fetchColumn();

    // 获取确认记录统计
    $confirmedCountSql = "SELECT COUNT(*) as count FROM mrs_batch_confirmed_item WHERE batch_id = :batch_id";
    $confirmedCountStmt = $pdo->prepare($confirmedCountSql);
    $confirmedCountStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
    $confirmedCountStmt->execute();
    $confirmedCount = $confirmedCountStmt->fetchColumn();

    // 组装响应数据
    $data = [
        'batch' => $batch,
        'stats' => [
            'raw_records_count' => intval($rawCount),
            'expected_items_count' => intval($expectedCount),
            'confirmed_items_count' => intval($confirmedCount)
        ]
    ];

    json_response(true, $data);

} catch (PDOException $e) {
    mrs_log('获取批次详情失败: ' . $e->getMessage(), 'ERROR', ['batch_id' => $batchId ?? null]);
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('获取批次详情异常: ' . $e->getMessage(), 'ERROR', ['batch_id' => $batchId ?? null]);
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

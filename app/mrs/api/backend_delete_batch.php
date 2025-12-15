<?php
/**
 * MRS 物料收发管理系统 - 后台API: 删除批次
 * 文件路径: app/mrs/api/backend_delete_batch.php
 * 说明: 删除收货批次及其相关数据
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

try {
    // 获取POST数据
    $input = get_json_input();

    if (!$input || empty($input['batch_id'])) {
        json_response(false, null, '缺少批次ID');
    }

    $batchId = intval($input['batch_id']);

    // 获取数据库连接
    $pdo = get_db_connection();

    // 开启事务
    $pdo->beginTransaction();

    try {
        // 检查批次状态,已确认或已过账的批次不能删除
        $checkSql = "SELECT batch_status FROM mrs_batch WHERE batch_id = :batch_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $checkStmt->execute();
        $batch = $checkStmt->fetch();

        if (!$batch) {
            // 即使是不存在，事务已开启，最好也回滚（虽然空事务影响小，但为了严谨）
            $pdo->rollBack();
            json_response(false, null, '批次不存在');
        }

        // [LOCK] 强化入库锁定：已确认或过账的批次绝对禁止删除
        if (in_array($batch['batch_status'], ['confirmed', 'posted'])) {
            // [FIX] 显式回滚事务，防止连接未释放
            $pdo->rollBack();
            json_response(false, null, '批次已锁定，禁止删除');
        }

        // 删除批次的原始记录
        $deleteRawSql = "DELETE FROM mrs_batch_raw_record WHERE batch_id = :batch_id";
        $deleteRawStmt = $pdo->prepare($deleteRawSql);
        $deleteRawStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $deleteRawStmt->execute();

        // 删除批次的预计清单
        $deleteExpectedSql = "DELETE FROM mrs_batch_expected_item WHERE batch_id = :batch_id";
        $deleteExpectedStmt = $pdo->prepare($deleteExpectedSql);
        $deleteExpectedStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $deleteExpectedStmt->execute();

        // 删除批次的确认记录
        $deleteConfirmedSql = "DELETE FROM mrs_batch_confirmed_item WHERE batch_id = :batch_id";
        $deleteConfirmedStmt = $pdo->prepare($deleteConfirmedSql);
        $deleteConfirmedStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $deleteConfirmedStmt->execute();

        // 删除批次主记录
        $deleteBatchSql = "DELETE FROM mrs_batch WHERE batch_id = :batch_id";
        $deleteBatchStmt = $pdo->prepare($deleteBatchSql);
        $deleteBatchStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $deleteBatchStmt->execute();

        // 提交事务
        $pdo->commit();

        mrs_log("批次删除成功: batch_id={$batchId}", 'INFO');

        json_response(true, null, '批次删除成功');

    } catch (Exception $e) {
        // 回滚事务
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

} catch (PDOException $e) {
    mrs_log('删除批次失败: ' . $e->getMessage(), 'ERROR', ['batch_id' => $batchId ?? null]);
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('删除批次异常: ' . $e->getMessage(), 'ERROR', ['batch_id' => $batchId ?? null]);
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

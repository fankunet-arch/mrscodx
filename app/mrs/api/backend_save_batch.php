<?php
/**
 * MRS 物料收发管理系统 - 后台API: 保存批次
 * 文件路径: app/mrs/api/backend_save_batch.php
 * 说明: 创建或更新收货批次
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

    if (!$input) {
        json_response(false, null, '无效的请求数据');
    }

    // [FIX] 验证必填字段
    // batch_code 不再是必须由前端提供的
    $required = ['batch_date', 'location_name'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            json_response(false, null, "缺少必填字段: {$field}");
        }
    }

    // 获取数据库连接
    $pdo = get_db_connection();

    // 判断是新建还是更新
    $batchId = $input['batch_id'] ?? null;

    if ($batchId) {
        // 更新现有批次时，batch_code 仍然是必须的（通常不允许修改为空）
        if (empty($input['batch_code'])) {
             json_response(false, null, "缺少必填字段: batch_code");
        }

        // 更新现有批次
        $sql = "UPDATE mrs_batch SET
                    batch_code = :batch_code,
                    batch_date = :batch_date,
                    location_name = :location_name,
                    remark = :remark,
                    batch_status = :batch_status,
                    updated_at = NOW(6)
                WHERE batch_id = :batch_id";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':batch_code', $input['batch_code']);
        $stmt->bindValue(':batch_date', $input['batch_date']);
        $stmt->bindValue(':location_name', $input['location_name']);
        $stmt->bindValue(':remark', $input['remark'] ?? '');
        $stmt->bindValue(':batch_status', $input['batch_status'] ?? 'draft');
        $stmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $stmt->execute();

        mrs_log("批次更新成功: batch_id={$batchId}", 'INFO', $input);

        json_response(true, ['batch_id' => $batchId], '批次更新成功');

    } else {
        // [SECURITY FIX] 创建新批次逻辑重构

        $batchCode = $input['batch_code'] ?? '';

        // 如果前端未提供批次号（或为空），则由后端自动生成
        if (empty($batchCode)) {
             $batchCode = generate_batch_code($input['batch_date']);
        }

        // 检查批次编号是否已存在 (防御性检查)
        $checkSql = "SELECT batch_id FROM mrs_batch WHERE batch_code = :batch_code";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindValue(':batch_code', $batchCode);
        $checkStmt->execute();

        if ($checkStmt->fetch()) {
            // 如果自动生成的也冲突（极低概率，因为generate_batch_code有序列号），或者用户输入的冲突
            json_response(false, null, '批次编号已存在，请重试');
        }

        // 创建新批次
        $sql = "INSERT INTO mrs_batch (
                    batch_code,
                    batch_date,
                    location_name,
                    remark,
                    batch_status,
                    created_at,
                    updated_at
                ) VALUES (
                    :batch_code,
                    :batch_date,
                    :location_name,
                    :remark,
                    :batch_status,
                    NOW(6),
                    NOW(6)
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':batch_code', $batchCode);
        $stmt->bindValue(':batch_date', $input['batch_date']);
        $stmt->bindValue(':location_name', $input['location_name']);
        $stmt->bindValue(':remark', $input['remark'] ?? '');
        $stmt->bindValue(':batch_status', $input['batch_status'] ?? 'draft');
        $stmt->execute();

        $newBatchId = $pdo->lastInsertId();

        mrs_log("新批次创建成功: batch_id={$newBatchId}, code={$batchCode}", 'INFO', $input);

        json_response(true, ['batch_id' => $newBatchId, 'batch_code' => $batchCode], '批次创建成功');
    }

} catch (PDOException $e) {
    mrs_log('保存批次失败: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('保存批次异常: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

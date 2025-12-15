<?php
/**
 * MRS 物料收发管理系统 - 后台API: 重写批次原始收货记录
 * 文件路径: app/mrs/api/backend_rewrite_raw_records.php
 * 说明: 基于总数量和总箱数直接重写指定SKU在某批次下的原始记录
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(false, null, '仅支持POST请求');
    }

    $input = get_json_input();
    if ($input === null) {
        json_response(false, null, '无效的JSON数据');
    }

    $required_fields = ['batch_id', 'sku_id', 'total_qty', 'physical_box_count'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            json_response(false, null, "缺少必填字段: {$field}");
        }
    }

    $batchId = intval($input['batch_id']);
    $skuId = intval($input['sku_id']);
    $totalQty = floatval($input['total_qty']);
    $boxCount = floatval($input['physical_box_count']);
    $note = $input['note'] ?? '';

    if ($batchId <= 0 || $skuId <= 0) {
        json_response(false, null, '批次或SKU参数无效');
    }

    if (!is_numeric($totalQty) || $totalQty <= 0) {
        json_response(false, null, '总数量必须为大于0的数字');
    }

    if (!is_numeric($boxCount) || $boxCount <= 0) {
        json_response(false, null, '总箱数必须为大于0的数字');
    }

    $batch = get_batch_by_id($batchId);
    if (!$batch) {
        json_response(false, null, '批次不存在');
    }

    $sku = get_sku_by_id($skuId);
    if (!$sku) {
        json_response(false, null, 'SKU不存在');
    }

    $pdo = get_db_connection();
    $pdo->beginTransaction();

    try {
        $unitName = $sku['standard_unit'] ?? '';
        if (!$unitName) {
            $unitName = $sku['case_unit_name'] ?: '件';
        }

        $operatorName = $_SESSION['user_display_name'] ?? '系统修正';
        $recordedAt = date('Y-m-d H:i:s');

        // 聚合现有总数，定位最新一条记录
        $fetchSql = "SELECT raw_record_id, qty, physical_box_count, note
                     FROM mrs_batch_raw_record
                     WHERE batch_id = :batch_id AND sku_id = :sku_id
                     ORDER BY recorded_at DESC, raw_record_id DESC";
        $fetchStmt = $pdo->prepare($fetchSql);
        $fetchStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $fetchStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
        $fetchStmt->execute();
        $records = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);

        $currentTotalQty = 0;
        $currentTotalBoxes = 0;
        foreach ($records as $row) {
            $currentTotalQty += floatval($row['qty']);
            $currentTotalBoxes += floatval($row['physical_box_count']);
        }

        $diffQty = $totalQty - $currentTotalQty;
        $diffBoxes = $boxCount - $currentTotalBoxes;

        // 若无记录，直接插入一条聚合值
        if (!$records) {
            $insertSql = "INSERT INTO mrs_batch_raw_record (
                            batch_id,
                            sku_id,
                            qty,
                            physical_box_count,
                            unit_name,
                            operator_name,
                            recorded_at,
                            note,
                            created_at,
                            updated_at
                        ) VALUES (
                            :batch_id,
                            :sku_id,
                            :qty,
                            :physical_box_count,
                            :unit_name,
                            :operator_name,
                            :recorded_at,
                            :note,
                            NOW(6),
                            NOW(6)
                        )";

            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
            $insertStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
            $insertStmt->bindValue(':qty', $totalQty, PDO::PARAM_STR);
            $insertStmt->bindValue(':physical_box_count', $boxCount, PDO::PARAM_STR);
            $insertStmt->bindValue(':unit_name', $unitName, PDO::PARAM_STR);
            $insertStmt->bindValue(':operator_name', $operatorName, PDO::PARAM_STR);
            $insertStmt->bindValue(':recorded_at', $recordedAt, PDO::PARAM_STR);
            $insertStmt->bindValue(':note', $note, PDO::PARAM_STR);
            $insertStmt->execute();
        } else {
            $latest = $records[0];
            $newQty = floatval($latest['qty']) + $diffQty;
            $newBoxes = floatval($latest['physical_box_count']) + $diffBoxes;

            if (abs($diffQty) < 0.0001 && abs($diffBoxes) < 0.0001) {
                $pdo->commit();
                json_response(true, null, '无需调整，数据已对齐');
            }

            if ($newQty > 0 && $newBoxes > 0) {
                // 直接在最新记录上分摊差值
                $updateSql = "UPDATE mrs_batch_raw_record
                              SET qty = :qty,
                                  physical_box_count = :physical_box_count,
                                  note = :note,
                                  updated_at = NOW(6)
                              WHERE raw_record_id = :raw_record_id";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->bindValue(':qty', $newQty, PDO::PARAM_STR);
                $updateStmt->bindValue(':physical_box_count', $newBoxes, PDO::PARAM_STR);
                $baseNote = $latest['note'] ?? '';
                $mergedNote = $note ? ($baseNote ? ($baseNote . ' | 调整：' . $note) : ('调整：' . $note)) : ($baseNote ?: '系统调整');
                $updateStmt->bindValue(':note', $mergedNote, PDO::PARAM_STR);
                $updateStmt->bindValue(':raw_record_id', $latest['raw_record_id'], PDO::PARAM_INT);
                $updateStmt->execute();
            } else {
                // 创建一条差异调整记录，保持历史记录不被清空
                $insertDiffSql = "INSERT INTO mrs_batch_raw_record (
                                    batch_id,
                                    sku_id,
                                    qty,
                                    physical_box_count,
                                    unit_name,
                                    operator_name,
                                    recorded_at,
                                    note,
                                    created_at,
                                    updated_at
                                ) VALUES (
                                    :batch_id,
                                    :sku_id,
                                    :qty,
                                    :physical_box_count,
                                    :unit_name,
                                    :operator_name,
                                    :recorded_at,
                                    :note,
                                    NOW(6),
                                    NOW(6)
                                )";
                $insertDiffStmt = $pdo->prepare($insertDiffSql);
                $insertDiffStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
                $insertDiffStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
                $insertDiffStmt->bindValue(':qty', $diffQty, PDO::PARAM_STR);
                $insertDiffStmt->bindValue(':physical_box_count', $diffBoxes, PDO::PARAM_STR);
                $insertDiffStmt->bindValue(':unit_name', $unitName, PDO::PARAM_STR);
                $insertDiffStmt->bindValue(':operator_name', $operatorName, PDO::PARAM_STR);
                $insertDiffStmt->bindValue(':recorded_at', $recordedAt, PDO::PARAM_STR);
                $notePrefix = '系统调整差异';
                $insertDiffStmt->bindValue(':note', $note ? ($notePrefix . ' - ' . $note) : $notePrefix, PDO::PARAM_STR);
                $insertDiffStmt->execute();
            }
        }

        $pdo->commit();

        mrs_log('批次原始记录已重写', 'INFO', [
            'batch_id' => $batchId,
            'sku_id' => $skuId,
            'total_qty' => $totalQty,
            'physical_box_count' => $boxCount,
            'diff_qty' => $diffQty,
            'diff_boxes' => $diffBoxes
        ]);

        json_response(true, null, '原始记录已重写');
    } catch (Exception $inner) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $inner;
    }
} catch (Exception $e) {
    mrs_log('重写原始记录API错误: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

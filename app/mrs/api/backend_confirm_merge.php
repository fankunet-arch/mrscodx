<?php
/**
 * MRS 物料收发管理系统 - 后台API: 确认批次合并
 * 文件路径: app/mrs/api/backend_confirm_merge.php
 * 说明: 确认批次合并并生成确认入库记录 (支持单项和批量)
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

    if (!$input || empty($input['batch_id']) || empty($input['items'])) {
        json_response(false, null, '缺少必要参数');
    }

    $batchId = intval($input['batch_id']);
    $items = $input['items'];

    // 获取数据库连接
    $pdo = get_db_connection();

    // 开启事务
    $pdo->beginTransaction();

    try {
        // 检查批次状态
        $checkSql = "SELECT batch_status FROM mrs_batch WHERE batch_id = :batch_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $checkStmt->execute();
        $batch = $checkStmt->fetch();

        if (!$batch) {
            $pdo->rollBack();
            json_response(false, null, '批次不存在');
        }

        // [LOCK] 强化入库锁定：已确认或过账的批次绝对禁止修改
        if ($batch['batch_status'] === 'confirmed' || $batch['batch_status'] === 'posted') {
            $pdo->rollBack();
            json_response(false, null, '批次已锁定，禁止修改');
        }

        // [FIX] 不再删除所有旧记录，而是针对本次提交的Items进行Upsert操作（先删后增）
        $deleteOldSql = "DELETE FROM mrs_batch_confirmed_item WHERE batch_id = :batch_id AND sku_id = :sku_id";
        $deleteOldStmt = $pdo->prepare($deleteOldSql);

        // 插入新的确认记录
        $insertSql = "INSERT INTO mrs_batch_confirmed_item (
                        batch_id,
                        sku_id,
                        total_standard_qty,
                        confirmed_case_qty,
                        confirmed_single_qty,
                        diff_against_expected,
                        is_over_received,
                        is_under_received,
                        created_at,
                        updated_at
                    ) VALUES (
                        :batch_id,
                        :sku_id,
                        :total_standard_qty,
                        :confirmed_case_qty,
                        :confirmed_single_qty,
                        :diff_against_expected,
                        :is_over_received,
                        :is_under_received,
                        NOW(6),
                        NOW(6)
                    )";

        $insertStmt = $pdo->prepare($insertSql);

        foreach ($items as $item) {
            $skuId = intval($item['sku_id']);

            // 1. 删除该SKU的旧记录 (防止重复)
            $deleteOldStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
            $deleteOldStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
            $deleteOldStmt->execute();

            // [SECURITY FIX] 绝不信任前端传入的换算率，必须反查数据库
            $skuInfo = get_sku_by_id($skuId);

            if (!$skuInfo) {
                // 如果SKU不存在，回滚事务并报错
                $pdo->rollBack();
                json_response(false, null, "SKU ID {$skuId} 不存在");
            }

            // [FIX START] 动态系数优先逻辑（必须基于“标准单位总数 / 实际箱数”）
            // 重新拉取原始记录，按标准单位重新累加，避免继续沿用出厂换算率导致误判
            $dynamicSpecSql = "SELECT qty, unit_name, physical_box_count
                               FROM mrs_batch_raw_record
                               WHERE batch_id = :bid AND sku_id = :sid";
            $specStmt = $pdo->prepare($dynamicSpecSql);
            $specStmt->execute([':bid' => $batchId, ':sid' => $skuId]);
            $rawRows = $specStmt->fetchAll(PDO::FETCH_ASSOC);

            $batchTotalQty = 0.0;   // 以标准单位计
            $batchTotalBoxes = 0.0; // 实际物理箱数

            foreach ($rawRows as $row) {
                $rowQty = floatval($row['qty']);
                $rowBoxes = floatval($row['physical_box_count']);
                $unitName = $row['unit_name'];

                // 如果未录入物理箱数但单位为“箱”，则默认按录入箱数计为物理箱数
                if ($rowBoxes <= 0 && $unitName === ($skuInfo['case_unit_name'] ?? '')) {
                    $rowBoxes = $rowQty;
                }

                // 如果录入单位是“箱”，先按出厂换算率折算成标准单位
                if ($unitName === ($skuInfo['case_unit_name'] ?? '') && floatval($skuInfo['case_to_standard_qty']) > 0) {
                    $rowQty *= floatval($skuInfo['case_to_standard_qty']);
                }

                $batchTotalQty += $rowQty;
                $batchTotalBoxes += $rowBoxes > 0 ? $rowBoxes : 0; // 空值按0处理
            }

            $effectiveSpec = 0;

            // 如果存在有效的物理箱数，且总数也存在，则计算动态系数
            if ($batchTotalBoxes > 0 && $batchTotalQty > 0) {
                $effectiveSpec = $batchTotalQty / $batchTotalBoxes;
            }

            // 2. 决策：如果动态系数有效，使用它；否则回退到出厂规格
            if ($effectiveSpec > 0) {
                $caseToStandard = $effectiveSpec;
            } else {
                // 使用数据库中的真实换算率
                $caseToStandard = floatval($skuInfo['case_to_standard_qty'] ?? 0);
            }
            // [FIX END]

            // 计算总标准数量
            $caseQty = floatval($item['case_qty'] ?? 0);
            $singleQty = floatval($item['single_qty'] ?? 0);

            // [FIX] 强制整数规则：计算结果必须为标准单位的整数
            // 1. 计算理论浮点值
            $rawTotal = ($caseQty * $caseToStandard) + $singleQty;
            // 2. 四舍五入取整，防止浮点精度问题导致的小数（如 29.99999 -> 30）
            // 需求文档明确：系统不允许以“6.5 箱”这种形式直接作为最终库存记账单位
            $totalStandard = round($rawTotal, 0);

            // [PATCH] 归一化逻辑：仅当箱规为整数并且 >0 时做归一化
            // 利用 mrs_lib 中的逻辑，或者直接这里实现 (为了减少依赖，这里直接保留逻辑，但需保证正确)
            if ($caseToStandard > 0 && fmod($caseToStandard, 1.0) == 0.0) {
                $caseSize = (int)$caseToStandard;
                $total    = (int)$totalStandard;

                $normalizedCaseQty   = intdiv($total, $caseSize);
                $normalizedSingleQty = $total % $caseSize;

                $caseQty   = $normalizedCaseQty; // Update for binding
                $singleQty = $normalizedSingleQty; // Update for binding
            }

            // 计算差异
            $expectedQty = floatval($item['expected_qty'] ?? 0);
            $diff = $totalStandard - $expectedQty;

            // 判断超收/少收
            $isOver = ($diff > 0) ? 1 : 0;
            $isUnder = ($diff < 0) ? 1 : 0;

            // 插入记录
            $insertStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
            $insertStmt->bindValue(':sku_id', $skuId, PDO::PARAM_INT);
            $insertStmt->bindValue(':total_standard_qty', $totalStandard); // 存入取整后的值
            $insertStmt->bindValue(':confirmed_case_qty', $caseQty);
            $insertStmt->bindValue(':confirmed_single_qty', $singleQty);
            $insertStmt->bindValue(':diff_against_expected', $diff);
            $insertStmt->bindValue(':is_over_received', $isOver, PDO::PARAM_INT);
            $insertStmt->bindValue(':is_under_received', $isUnder, PDO::PARAM_INT);
            $insertStmt->execute();
        }

        // 批次状态流转逻辑
        // 如果是"Confirm All"（怎么判断？可能是通过业务逻辑），通常全部确认后批次应标记为 confirmed
        // 但这里支持Partial Update。
        // 我们只在明确需要结束批次时才 Update Batch Status。
        // 目前前端行为：Confirm All 调用此接口。Confirm Single 也调用此接口。
        // 如果是 Confirm Single，不应该结束批次。
        // 如何区分？
        // 简单逻辑：如果确认的 Item 数量 == 预计的 Item 数量？ 不可靠。
        // 保持 `receiving` 状态，除非显式调用 "Finish Batch"？
        // 原始代码是直接 Update Status to Confirmed。这会导致 Confirm Single 后批次变成 Confirmed，无法再编辑。
        // Issue 1 asks for Draft -> Receiving.
        // Issue 2/3 asks for Confirm functionality.
        // If I confirm an item, it should NOT close the batch yet, allowing adjustments.
        // The user says "Adjusting confirmed items... shows dev". Meaning they want to be able to adjust.
        // So, `backend_confirm_merge.php` should NOT automatically close the batch to `confirmed` unless explicitly requested.
        // BUT, the original code DID close it.
        // I will removing the automatic batch status update to `confirmed` here,
        // OR only do it if a flag `close_batch` is passed.
        // Since I control frontend, I can pass `close_batch: true` for "Confirm All".
        // For "Confirm Single", `close_batch: false`.

        $closeBatch = $input['close_batch'] ?? false;

        if ($closeBatch) {
            // 更新批次状态为已确认
            $updateBatchSql = "UPDATE mrs_batch SET
                                batch_status = 'confirmed',
                                updated_at = NOW(6)
                            WHERE batch_id = :batch_id";
            $updateBatchStmt = $pdo->prepare($updateBatchSql);
            $updateBatchStmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
            $updateBatchStmt->execute();
        } else {
             // 确保状态至少是 receiving (或者是 pending_merge?)
             // 如果还在 draft, 已经在 save_record 变为 receiving 了。
             // 这里不需要强制变更状态，除非我们想引入 `pending_merge` 状态。
             // 保持现状即可。
        }

        // 提交事务
        $pdo->commit();

        mrs_log("批次合并确认成功: batch_id={$batchId}, items_count=" . count($items), 'INFO');

        json_response(true, null, '确认成功');

    } catch (Exception $e) {
        // 回滚事务
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

} catch (PDOException $e) {
    mrs_log('确认批次合并失败: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '数据库错误: ' . $e->getMessage());
} catch (Exception $e) {
    mrs_log('确认批次合并异常: ' . $e->getMessage(), 'ERROR', $input ?? []);
    json_response(false, null, '系统错误: ' . $e->getMessage());
}

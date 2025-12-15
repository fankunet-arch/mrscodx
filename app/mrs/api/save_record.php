<?php
/**
 * MRS 物料收发管理系统 - API: 保存收货记录
 * 文件路径: app/mrs/api/save_record.php
 * 说明: 保存前台收货原始记录
 */

// 防止直接访问 (适配 Gateway 模式)
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 加载配置和库文件
require_once __DIR__ . '/../config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

try {
    // 只接受 POST 请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(false, null, '仅支持POST请求');
    }

    // 获取 POST JSON 数据
    $input = get_json_input();

    if ($input === null) {
        json_response(false, null, '无效的JSON数据');
    }

    // 验证必填字段
    $required_fields = ['batch_id', 'qty', 'unit_name', 'operator_name', 'physical_box_count'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            json_response(false, null, "缺少必填字段: {$field}");
        }
    }

    // [FIX] 验证数量必须为数字
    if (!is_numeric($input['qty'])) {
        json_response(false, null, '数量必须为有效数字');
    }

    // 验证物理箱数
    if (!is_numeric($input['physical_box_count']) || floatval($input['physical_box_count']) <= 0) {
        json_response(false, null, '物理箱数必须为大于0的数字');
    }

    // [SECURITY FIX] 验证单位合法性 (白名单)
    // 只有当提供了 sku_id 时才能校验，但在严格模式下，我们假设收货记录应当关联SKU
    if (isset($input['sku_id']) && !empty($input['sku_id'])) {
        $sku = get_sku_by_id($input['sku_id']);
        if (!$sku) {
            json_response(false, null, 'SKU不存在');
        }

        $allowedUnits = [
            $sku['standard_unit'],
            $sku['case_unit_name']
        ];
        // 过滤掉空值（有些SKU可能没有箱规单位）
        $allowedUnits = array_filter($allowedUnits);

        if (!in_array($input['unit_name'], $allowedUnits)) {
            json_response(false, null, "非法单位: {$input['unit_name']}。仅允许: " . implode(', ', $allowedUnits));
        }
    }

    // 验证批次是否存在
    $batch = get_batch_by_id($input['batch_id']);
    if (!$batch) {
        json_response(false, null, '批次不存在');
    }

    // [MODIFIED] 状态流转逻辑
    $current_status = $batch['batch_status'];
    $new_status = null;

    // 如果是 Draft，自动转为 Receiving
    if ($current_status === 'draft') {
        $new_status = 'receiving';
    }
    // 如果是 Confirmed，自动转回 Pending Merge 以便重新确认
    elseif ($current_status === 'confirmed') {
        $new_status = 'pending_merge';
    }
    // Posted 状态的批次是最终锁定的，不允许再添加
    elseif ($current_status === 'posted') {
        json_response(false, null, '批次已过账锁定，不允许添加记录');
    }

    // 如果需要更新状态，则执行数据库操作
    if ($new_status !== null) {
        try {
            $pdo_status_update = get_db_connection();
            $upSql = "UPDATE mrs_batch SET batch_status = :new_status, updated_at = NOW(6) WHERE batch_id = :bid";
            $upStmt = $pdo_status_update->prepare($upSql);
            $upStmt->bindValue(':new_status', $new_status, PDO::PARAM_STR);
            $upStmt->bindValue(':bid', $input['batch_id'], PDO::PARAM_INT);
            $upStmt->execute();
            mrs_log("批次 {$input['batch_id']} 状态自动从 {$current_status} 流转到 {$new_status}", 'INFO');
        } catch (Exception $e) {
            // 状态更新失败是一个需要关注的问题，但为了保证数据录入，我们只记录日志
            mrs_log('Auto status update failed: ' . $e->getMessage(), 'WARNING');
        }
    }

    // 准备数据
    $record_data = [
        'batch_id' => $input['batch_id'],
        'sku_id' => $input['sku_id'] ?? null,
        'input_sku_name' => $input['sku_name'] ?? null, // [FIX] 保存手动输入的物料名称
        'qty' => $input['qty'],
        'physical_box_count' => $input['physical_box_count'],
        'unit_name' => $input['unit_name'],
        'operator_name' => $input['operator_name'],
        'recorded_at' => date('Y-m-d H:i:s.u'),
        'note' => $input['note'] ?? ''
    ];

    // 保存记录
    $record_id = save_raw_record($record_data);

    if ($record_id === false) {
        json_response(false, null, '保存失败');
    }

    // 记录日志
    mrs_log('保存收货记录成功', 'INFO', [
        'record_id' => $record_id,
        'batch_id' => $input['batch_id'],
        'sku_id' => $input['sku_id'] ?? null,
        'qty' => $input['qty'],
        'unit_name' => $input['unit_name']
    ]);

    // 返回成功响应
    json_response(true, ['record_id' => $record_id], '保存成功');

} catch (Exception $e) {
    mrs_log('保存记录API错误: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '服务器错误');
}

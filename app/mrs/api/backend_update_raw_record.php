<?php
/**
 * MRS 物料收发管理系统 - 后台API: 更新原始收货记录
 * 文件路径: app/mrs/api/backend_update_raw_record.php
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

    $required_fields = ['raw_record_id', 'qty', 'physical_box_count'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            json_response(false, null, "缺少必填字段: {$field}");
        }
    }

    if (!is_numeric($input['qty']) || floatval($input['qty']) <= 0) {
        json_response(false, null, '数量必须为大于0的数字');
    }

    if (!is_numeric($input['physical_box_count']) || floatval($input['physical_box_count']) <= 0) {
        json_response(false, null, '物理箱数必须为大于0的数字');
    }

    $update_data = [
        'raw_record_id' => intval($input['raw_record_id']),
        'qty' => $input['qty'],
        'physical_box_count' => $input['physical_box_count'],
        'note' => $input['note'] ?? ''
    ];

    $result = update_raw_record($update_data);
    if (!$result) {
        json_response(false, null, '更新失败，请检查日志');
    }

    json_response(true, null, '更新成功');
} catch (Exception $e) {
    mrs_log('更新原始记录API错误: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '服务器错误');
}

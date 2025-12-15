<?php
/**
 * MRS 物料收发管理系统 - API: 批次记录
 * 文件路径: app/mrs/api/batch_records.php
 * 说明: 获取批次的原始收货记录
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
    // 获取查询参数
    $batch_id = $_GET['batch_id'] ?? null;

    // 验证参数
    if ($batch_id === null || !is_numeric($batch_id)) {
        json_response(false, null, '无效的批次ID');
    }

    $batch_id = (int)$batch_id;

    // 验证批次是否存在
    $batch = get_batch_by_id($batch_id);
    if (!$batch) {
        json_response(false, null, '批次不存在');
    }

    // 获取批次记录
    $records = get_batch_raw_records($batch_id);

    // 返回成功响应
    json_response(true, $records, '获取成功');

} catch (Exception $e) {
    mrs_log('批次记录API错误: ' . $e->getMessage(), 'ERROR', ['batch_id' => $batch_id ?? null]);
    json_response(false, null, '服务器错误');
}

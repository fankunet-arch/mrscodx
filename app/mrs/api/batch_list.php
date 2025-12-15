<?php
/**
 * MRS 物料收发管理系统 - API: 批次列表
 * 文件路径: app/mrs/api/batch_list.php
 * 说明: 获取收货批次列表
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
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $status = $_GET['status'] ?? null;

    // 验证参数
    if ($limit < 1 || $limit > 100) {
        $limit = 20;
    }

    // 获取批次列表
    $batches = get_batch_list($limit, $status);

    // 返回成功响应
    json_response(true, $batches, '获取成功');

} catch (Exception $e) {
    mrs_log('批次列表API错误: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '服务器错误');
}

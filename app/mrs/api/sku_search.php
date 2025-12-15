<?php
/**
 * MRS 物料收发管理系统 - API: SKU搜索
 * 文件路径: app/mrs/api/sku_search.php
 * 说明: 根据关键词搜索SKU
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
    $keyword = $_GET['keyword'] ?? '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

    // 验证参数
    if (empty($keyword)) {
        json_response(true, [], '关键词为空');
    }

    if ($limit < 1 || $limit > 100) {
        $limit = 20;
    }

    // 搜索SKU
    $results = search_sku($keyword, $limit);

    // 返回成功响应
    json_response(true, $results, '搜索成功');

} catch (Exception $e) {
    mrs_log('SKU搜索API错误: ' . $e->getMessage(), 'ERROR', ['keyword' => $keyword ?? '']);
    json_response(false, null, '服务器错误');
}

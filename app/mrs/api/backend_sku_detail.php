<?php
/**
 * MRS 物料收发管理系统 - 后台API: 获取SKU详情
 * 文件路径: app/mrs/api/backend_sku_detail.php
 * 说明: 根据ID获取SKU详细信息
 */

// 定义API入口
if (!defined('MRS_ENTRY')) {
    define('MRS_ENTRY', true);
}

// 加载配置 (如果尚未加载)
if (!defined('MRS_LIB_PATH')) {
    require_once __DIR__ . '/../config_mrs/env_mrs.php';
}
require_once MRS_LIB_PATH . '/mrs_lib.php';

try {
    // 获取参数
    $skuId = $_GET['sku_id'] ?? null;

    if (!$skuId) {
        json_response(false, null, '缺少参数: sku_id');
    }

    // 获取SKU信息
    $sku = get_sku_by_id($skuId);

    if ($sku) {
        json_response(true, $sku);
    } else {
        json_response(false, null, '未找到指定的SKU');
    }

} catch (Exception $e) {
    mrs_log('获取SKU详情异常: ' . $e->getMessage(), 'ERROR');
    json_response(false, null, '系统错误');
}

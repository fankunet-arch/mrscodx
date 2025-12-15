<?php
// Action: save_receipt.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // 仅允许 POST 请求
    header('Location: /mrs/index.php?action=quick_receipt');
    exit;
}

// 收集表单数据
$receipt_data = [
    // 注意：在实际应用中，batch_code 需要转换为 batch_id
    // 这里我们做一个简化，假设用户直接输入了 batch_id 或者我们需要根据 code 查询
    // 为了验证，我们先假设 batch_id = 1
    'batch_id' => 1, // $_POST['batch_code'] 应该被查询转换为 ID
    'qty' => $_POST['quantity'] ?? 0,
    'unit_name' => $_POST['unit'] ?? '',
    'input_sku_name' => $_POST['sku_search'] ?? '', // 用户输入的物料名
    'operator_name' => '现场操作员', // 暂时硬编码
];

// 验证必要数据
if (empty($receipt_data['batch_id']) || empty($receipt_data['qty']) || empty($receipt_data['unit_name'])) {
    die("错误：批次、数量和单位为必填项。");
}

// 调用库函数保存数据
$result_id = save_raw_record($receipt_data);

if ($result_id) {
    // 保存成功，跳转回收货页面并给出提示
    // 在真实场景中，这里通常是一个AJAX调用，所以会返回JSON
    // 但按照MPA模式，我们重定向
    header('Location: /mrs/index.php?action=quick_receipt&success=1');
    exit;
} else {
    // 保存失败
    die("错误：保存收货记录失败，请检查日志。");
}

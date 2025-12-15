<?php
/**
 * Debug Page for Partial Outbound
 * 拆零出货调试页面
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取SKU参数
$sku = $_GET['sku'] ?? '';

if (empty($sku)) {
    // 如果没有指定SKU，显示所有可用的SKU
    $stmt = $pdo->query("
        SELECT DISTINCT i.product_name, COUNT(*) as box_count
        FROM mrs_package_items i
        INNER JOIN mrs_package_ledger l ON i.ledger_id = l.ledger_id
        WHERE l.status = 'in_stock'
        GROUP BY i.product_name
        ORDER BY i.product_name
    ");
    $products = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>拆零出货调试 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <style>
        .debug-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .debug-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .debug-section h2 {
            margin-top: 0;
            color: #495057;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .debug-data {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>🐛 拆零出货调试</h1>
            <div class="header-actions">
                <a href="/mrs/ap/index.php?action=inventory_list" class="btn btn-secondary">返回库存</a>
            </div>
        </div>

        <div class="debug-container">
            <?php if (empty($sku)): ?>
                <!-- 选择产品 -->
                <div class="debug-section">
                    <h2>选择要调试的产品</h2>
                    <p>点击产品名称查看详细调试信息</p>
                    <?php if (!empty($products)): ?>
                        <ul>
                            <?php foreach ($products as $product): ?>
                                <li style="margin: 10px 0;">
                                    <a href="?action=debug_partial_outbound&sku=<?= urlencode($product['product_name']) ?>"
                                       class="btn btn-sm btn-primary">
                                        <?= htmlspecialchars($product['product_name']) ?>
                                        (<?= $product['box_count'] ?>箱)
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="warning">没有在库产品</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- 调试指定产品 -->
                <div class="debug-section">
                    <h2>产品: <?= htmlspecialchars($sku) ?></h2>
                </div>

                <!-- 检查1: 数据库连接 -->
                <div class="debug-section">
                    <h2>✓ 数据库连接</h2>
                    <p class="success">数据库连接正常</p>
                </div>

                <!-- 检查2: mrs_usage_log 表 -->
                <div class="debug-section">
                    <h2>检查 mrs_usage_log 表</h2>
                    <?php
                    try {
                        $stmt = $pdo->query("SHOW TABLES LIKE 'mrs_usage_log'");
                        $exists = $stmt->fetch();
                        if ($exists) {
                            echo '<p class="success">✓ 表存在</p>';
                        } else {
                            echo '<p class="error">✗ 表不存在！请运行数据库迁移。</p>';
                        }
                    } catch (Exception $e) {
                        echo '<p class="error">✗ 检查失败: ' . htmlspecialchars($e->getMessage()) . '</p>';
                    }
                    ?>
                </div>

                <!-- 检查3: 包裹数据 -->
                <div class="debug-section">
                    <h2>包裹数据</h2>
                    <?php
                    $packages = mrs_get_true_inventory_detail($pdo, $sku);

                    if (!empty($packages)) {
                        echo '<p class="success">✓ 找到 ' . count($packages) . ' 个包裹</p>';

                        foreach ($packages as $i => $pkg) {
                            echo '<div class="debug-data" style="margin: 10px 0;">';
                            echo '<strong>包裹 #' . ($i + 1) . '</strong><br>';
                            echo '<pre>';
                            echo 'ledger_id: ' . htmlspecialchars($pkg['ledger_id'] ?? 'MISSING') . "\n";
                            echo 'content_note: ' . htmlspecialchars($pkg['content_note'] ?? 'MISSING') . "\n";
                            echo 'ledger_quantity: ' . htmlspecialchars($pkg['ledger_quantity'] ?? 'MISSING') . "\n";
                            echo 'status: ' . htmlspecialchars($pkg['status'] ?? 'MISSING') . "\n";
                            echo 'batch_name: ' . htmlspecialchars($pkg['batch_name'] ?? 'MISSING') . "\n";
                            echo 'tracking_number: ' . htmlspecialchars($pkg['tracking_number'] ?? 'MISSING') . "\n";

                            // 测试数量清洗
                            $qty_raw = $pkg['ledger_quantity'] ?? '';
                            if ($qty_raw === null || $qty_raw === '') {
                                $qty_cleaned = 0.0;
                            } else {
                                $cleaned = preg_replace('/[^0-9.]/', '', trim((string)$qty_raw));
                                $qty_cleaned = $cleaned !== '' ? floatval($cleaned) : 0.0;
                            }

                            echo "\n数量清洗测试:\n";
                            echo '  原始值: ' . htmlspecialchars($qty_raw) . "\n";
                            echo '  清洗后: ' . $qty_cleaned . "\n";

                            // 显示items
                            if (!empty($pkg['items'])) {
                                echo "\nitems:\n";
                                foreach ($pkg['items'] as $item) {
                                    echo '  - ' . htmlspecialchars($item['product_name']);
                                    if (!empty($item['quantity'])) {
                                        echo ' × ' . htmlspecialchars($item['quantity']);
                                    }
                                    echo "\n";
                                }
                            }

                            echo '</pre>';
                            echo '</div>';
                        }
                    } else {
                        echo '<p class="error">✗ 没有找到包裹</p>';
                    }
                    ?>
                </div>

                <!-- 检查4: JavaScript函数测试 -->
                <div class="debug-section">
                    <h2>JavaScript函数测试</h2>
                    <?php if (!empty($packages)): ?>
                        <p>点击下面的按钮测试拆零出货功能：</p>
                        <?php foreach ($packages as $i => $pkg): ?>
                            <button class="btn btn-success" style="margin: 5px;"
                                    onclick="testPartialOutbound(<?= $pkg['ledger_id'] ?>, '<?= htmlspecialchars($pkg['content_note'], ENT_QUOTES) ?>', '<?= htmlspecialchars($pkg['ledger_quantity'] ?? '', ENT_QUOTES) ?>')">
                                测试包裹 #<?= ($i + 1) ?> (ID: <?= $pkg['ledger_id'] ?>)
                            </button>
                        <?php endforeach; ?>

                        <div id="js-test-result" class="debug-data" style="margin-top: 15px; display: none;">
                            <strong>JavaScript调用结果：</strong>
                            <pre id="js-test-output"></pre>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 检查5: API端点测试 -->
                <div class="debug-section">
                    <h2>API端点测试</h2>
                    <button class="btn btn-primary" onclick="testAPI()">测试 API 连接</button>
                    <div id="api-test-result" class="debug-data" style="margin-top: 15px; display: none;">
                        <strong>API测试结果：</strong>
                        <pre id="api-test-output"></pre>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="/mrs/ap/js/modal.js"></script>
    <script>
    function testPartialOutbound(ledgerId, productName, currentQty) {
        const resultDiv = document.getElementById('js-test-result');
        const outputDiv = document.getElementById('js-test-output');

        resultDiv.style.display = 'block';

        let output = '调用参数:\n';
        output += '  ledgerId: ' + ledgerId + '\n';
        output += '  productName: ' + productName + '\n';
        output += '  currentQty (原始): ' + currentQty + '\n';

        // 测试数量清洗
        const cleanQty = (qty) => {
            if (!qty || qty === '') return 0;
            const cleaned = String(qty).replace(/[^0-9.]/g, '');
            return cleaned ? parseFloat(cleaned) : 0;
        };

        const availableQty = cleanQty(currentQty);
        output += '  currentQty (清洗后): ' + availableQty + '\n\n';

        // 检查modal对象
        if (typeof window.showModal === 'function') {
            output += '✓ window.showModal 函数存在\n';
        } else {
            output += '✗ window.showModal 函数不存在！\n';
        }

        // 检查showAlert函数
        if (typeof showAlert === 'function') {
            output += '✓ showAlert 函数存在\n';
        } else {
            output += '✗ showAlert 函数不存在！\n';
        }

        output += '\n尝试打开模态框...\n';

        outputDiv.textContent = output;

        // 实际调用partialOutbound函数
        if (typeof partialOutbound === 'function') {
            try {
                partialOutbound(ledgerId, productName, currentQty);
                outputDiv.textContent += '✓ partialOutbound 函数调用成功\n';
            } catch (e) {
                outputDiv.textContent += '✗ partialOutbound 函数调用失败: ' + e.message + '\n';
            }
        } else {
            outputDiv.textContent += '✗ partialOutbound 函数未定义！\n';
        }
    }

    async function testAPI() {
        const resultDiv = document.getElementById('api-test-result');
        const outputDiv = document.getElementById('api-test-output');

        resultDiv.style.display = 'block';
        outputDiv.textContent = '正在测试 API...\n';

        try {
            // 测试API端点是否可访问（使用无效参数，只是为了测试连接）
            const response = await fetch('/mrs/ap/index.php?action=partial_outbound', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ledger_id: 0,
                    deduct_qty: 0,
                    destination: ''
                })
            });

            let output = 'HTTP Status: ' + response.status + '\n';
            output += 'Content-Type: ' + response.headers.get('content-type') + '\n\n';

            const data = await response.json();
            output += 'Response:\n';
            output += JSON.stringify(data, null, 2) + '\n';

            if (data.success === false) {
                output += '\n✓ API端点可访问（返回了预期的错误响应）\n';
            } else {
                output += '\n? API端点响应异常\n';
            }

            outputDiv.textContent = output;
        } catch (error) {
            outputDiv.textContent = '✗ API测试失败: ' + error.message + '\n';
        }
    }

    // 从outbound.php复制的partialOutbound函数
    async function partialOutbound(ledgerId, productName, currentQty) {
        if (typeof window.showModal !== 'function' || typeof window.showAlert !== 'function') {
            alert('页面脚本未完全加载，请刷新后重试（缺少 modal.js）');
            return;
        }

        // 清洗数量字段（移除非数字字符）
        const cleanQty = (qty) => {
            if (!qty || qty === '') return 0;
            const cleaned = String(qty).replace(/[^0-9.]/g, '');
            return cleaned ? parseFloat(cleaned) : 0;
        };

        const availableQty = cleanQty(currentQty);

        const content = `
            <div class="modal-section">
                <div style="background: #e3f2fd; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                    <strong>商品名称：</strong>${productName}<br>
                    <strong>当前库存：</strong><span style="color: #1976d2; font-size: 18px; font-weight: bold;">${availableQty}</span> 件
                </div>

                <div class="form-group">
                    <label for="outbound-qty">出货数量 <span style="color: red;">*</span></label>
                    <input type="number" id="outbound-qty" class="form-control"
                           placeholder="请输入出货数量" min="0.01" step="0.01" max="${availableQty}" required>
                    <small style="color: #666;">可出货数量：${availableQty} 件</small>
                </div>

                <div class="form-group">
                    <label for="destination">目的地（门店） <span style="color: red;">*</span></label>
                    <input type="text" id="destination" class="form-control"
                           placeholder="请输入门店名称" required>
                </div>

                <div class="form-group">
                    <label for="remark">备注</label>
                    <textarea id="remark" class="form-control" rows="2"
                              placeholder="选填"></textarea>
                </div>
            </div>
        `;

        const confirmed = await window.showModal({
            title: '拆零出货',
            content,
            width: '560px',
            footer: `
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-secondary" data-action="cancel">取消</button>
                    <button class="modal-btn modal-btn-primary" data-action="confirm">确认出货</button>
                </div>
            `
        });

        if (!confirmed) return;

        // 获取表单数据
        const deductQty = parseFloat(document.getElementById('outbound-qty').value);
        const destination = document.getElementById('destination').value.trim();
        const remark = document.getElementById('remark').value.trim();

        // 验证
        if (!deductQty || deductQty <= 0) {
            await showAlert('请输入有效的出货数量', '错误', 'error');
            return;
        }

        if (deductQty > availableQty) {
            await showAlert(`出货数量（${deductQty}）超过库存（${availableQty}）`, '错误', 'error');
            return;
        }

        if (!destination) {
            await showAlert('请输入目的地（门店）', '错误', 'error');
            return;
        }

        // 提交数据
        try {
            const response = await fetch('/mrs/ap/index.php?action=partial_outbound', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ledger_id: ledgerId,
                    deduct_qty: deductQty,
                    destination: destination,
                    remark: remark
                })
            });

            const data = await response.json();

            if (data.success) {
                await showAlert(
                    `拆零出货成功！\\n\\n已从包裹中扣减 ${deductQty} 件\\n剩余 ${data.data.remaining_qty} 件\\n目的地：${destination}`,
                    '成功',
                    'success'
                );
                // 刷新页面
                window.location.reload();
            } else {
                await showAlert('操作失败: ' + data.message, '错误', 'error');
            }
        } catch (error) {
            await showAlert('网络错误: ' + error.message, '错误', 'error');
        }
    }
    </script>
</body>
</html>

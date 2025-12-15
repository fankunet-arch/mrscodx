<?php
/**
 * Backend Batch Detail Page
 * 文件路径: app/express/views/batch_detail.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$batch_id = $_GET['batch_id'] ?? 0;

if (empty($batch_id)) {
    die('批次ID不能为空');
}

$batch = express_get_batch_by_id($pdo, $batch_id);

if (!$batch) {
    die('批次不存在');
}

$render_batch_status = function (array $batch): array {
    $status = $batch['status'] ?? 'inactive';

    if ($status !== 'active') {
        return ['label' => '已关闭', 'class' => 'secondary'];
    }

    $total_count = (int) ($batch['total_count'] ?? 0);
    $verified_count = (int) ($batch['verified_count'] ?? 0);
    $counted_count = (int) ($batch['counted_count'] ?? 0);
    $adjusted_count = (int) ($batch['adjusted_count'] ?? 0);

    if ($total_count === 0) {
        return ['label' => '等待录入', 'class' => 'secondary'];
    }

    if ($verified_count === 0 && $counted_count === 0 && $adjusted_count === 0) {
        return ['label' => '等待中', 'class' => 'waiting'];
    }

    if ($total_count === $counted_count) {
        return ['label' => '清点完成', 'class' => 'info'];
    }

    if ($total_count === $verified_count && $verified_count !== $counted_count) {
        return ['label' => '待清点', 'class' => 'info'];
    }

    if ($total_count > 0 && $total_count > $verified_count) {
        return ['label' => '进行中', 'class' => 'success'];
    }

    return ['label' => '进行中', 'class' => 'success'];
};

$status_info = $render_batch_status($batch);
$packages = express_get_packages_by_batch($pdo, $batch_id, 'all');
$content_summary = express_get_content_summary($pdo, $batch_id);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批次详情 - <?= htmlspecialchars($batch['batch_name']) ?></title>
    <link rel="stylesheet" href="../css/backend.css">
    <link rel="stylesheet" href="css/modal.css">
    <style>
        /* [UX优化] 让日期输入框点击任意位置都能弹出选择面板 */
        /* 通过将选择器指示器(小图标)透明并铺满整个输入框来实现 */
        input[type="date"] {
            position: relative;
        }
        input[type="date"]::-webkit-calendar-picker-indicator {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include EXPRESS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <h1>批次详情: <?= htmlspecialchars($batch['batch_name']) ?></h1>
            <div class="header-actions">
                <a href="/express/exp/index.php?action=batch_edit&batch_id=<?= $batch_id ?>" class="btn btn-primary">编辑批次</a>
                <a href="/express/exp/index.php?action=batch_list" class="btn btn-secondary">返回列表</a>
            </div>
        </header>

        <div class="content-wrapper">
            <div class="info-card">
                <h2>批次信息</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">批次ID:</span>
                        <span class="info-value"><?= $batch['batch_id'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">状态:</span>
                        <span class="info-value">
                            <span class="badge badge-<?= htmlspecialchars($status_info['class']) ?>">
                                <?= htmlspecialchars($status_info['label']) ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">创建时间:</span>
                        <span class="info-value"><?= $batch['created_at'] ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">创建人:</span>
                        <span class="info-value"><?= htmlspecialchars($batch['created_by'] ?? '-') ?></span>
                    </div>
                </div>

                <?php if ($batch['notes']): ?>
                    <div class="info-notes">
                        <strong>备注:</strong>
                        <p><?= nl2br(htmlspecialchars($batch['notes'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $batch['total_count'] ?></div>
                    <div class="stat-label">总包裹数</div>
                </div>
                <div class="stat-card stat-verified">
                    <div class="stat-number"><?= $batch['verified_count'] ?></div>
                    <div class="stat-label">已核实</div>
                </div>
                <div class="stat-card stat-counted">
                    <div class="stat-number"><?= $batch['counted_count'] ?></div>
                    <div class="stat-label">已清点</div>
                </div>
                <div class="stat-card stat-adjusted">
                    <div class="stat-number"><?= $batch['adjusted_count'] ?></div>
                    <div class="stat-label">已调整</div>
                </div>
            </div>

            <div class="bulk-import-section">
                <h2>批量导入快递单号</h2>
                <form id="bulk-import-form">
                    <div class="form-group">
                        <label for="tracking_numbers">快递单号列表（每行一个）:</label>
                        <textarea id="tracking_numbers" class="form-control" rows="10"
                                  placeholder="111111&#10;222222|2025-12-31|50&#10;333333||30&#10;444444|2026-01-15"></textarea>
                        <small class="form-text" style="line-height: 1.6;">
                            <strong>支持两种导入格式：</strong><br>
                            1️⃣ <strong>仅单号</strong>：111111<br>
                            2️⃣ <strong>含附加信息</strong>：单号|有效期|数量（用 | 分隔）<br>
                            &nbsp;&nbsp;&nbsp;• 完整示例：222222|2025-12-31|50<br>
                            &nbsp;&nbsp;&nbsp;• 仅有效期：333333|2025-12-31|<br>
                            &nbsp;&nbsp;&nbsp;• 仅数量：444444||30<br>
                            📌 <strong>说明</strong>：有效期格式为 YYYY-MM-DD，数量为参考数据（不影响系统计数）
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary">批量导入</button>
                </form>
                <div id="import-message" class="message" style="display: none; margin-top: 15px;"></div>
            </div>

            <div class="bulk-import-section" style="margin-top: 30px; background-color: #f8f9fa; padding: 20px; border-radius: 5px; border: 2px dashed #28a745;">
                <h2 style="color: #28a745;">📦 添加自定义包裹（拆分箱子功能）</h2>
                <p class="form-text" style="margin-bottom: 15px; color: #666;">
                    用于添加拆分后的箱子。系统会自动生成虚拟快递单号（格式：CUSTOM-批次ID-序号），您可以打印标签并贴在箱子上。
                </p>
                <form id="custom-package-form">
                    <div class="form-group">
                        <label for="custom_count">要添加的箱子数量:</label>
                        <input type="number" id="custom_count" class="form-control"
                               min="1" max="100" value="1" style="width: 200px;">
                        <small class="form-text">
                            一次最多添加100个自定义包裹
                        </small>
                    </div>
                    <button type="submit" class="btn btn-success">添加自定义包裹</button>
                </form>
                <div id="custom-message" class="message" style="display: none; margin-top: 15px;"></div>
            </div>

            <div class="packages-section">
                <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h2 style="margin: 0;">包裹列表 (共 <?= count($packages) ?> 个)</h2>
                    <button id="toggle-time-columns" class="btn btn-sm btn-secondary" onclick="toggleTimeColumns()">
                        <span id="toggle-time-text">显示更多时间</span>
                    </button>
                </div>
                <div id="update-message" class="message" style="display: none;"></div>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>包裹ID</th>
                                <th>快递单号</th>
                                <th>状态</th>
                                <th>内容备注</th>
                                <th>保质期</th>
                                <th>数量</th>
                                <th>调整备注</th>
                                <th class="time-col-default">创建时间</th>
                                <th class="time-col-extra" style="display: none;">核实时间</th>
                                <th class="time-col-default">清点时间</th>
                                <th class="time-col-extra" style="display: none;">调整时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($packages)): ?>
                                <tr>
                                    <td colspan="12" class="text-center">暂无包裹数据</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($packages as $package): ?>
                                    <tr>
                                        <td><?= $package['package_id'] ?></td>
                                        <td>
                                            <?php
                                            $tracking = htmlspecialchars($package['tracking_number']);
                                            if (mb_strlen($tracking) >= 4) {
                                                $prefix = mb_substr($tracking, 0, -4);
                                                $suffix = mb_substr($tracking, -4);
                                                echo $prefix . '<span style="color: #dc3545; font-weight: bold;">' . $suffix . '</span>';
                                            } else {
                                                echo $tracking;
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $package['package_status'] ?>">
                                                <?php
                                                $status_map = [
                                                    'pending' => '待处理',
                                                    'verified' => '已核实',
                                                    'counted' => '已清点',
                                                    'adjusted' => '已调整'
                                                ];
                                                echo $status_map[$package['package_status']] ?? $package['package_status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($package['items']) && is_array($package['items'])): ?>
                                                <?php foreach ($package['items'] as $index => $item): ?>
                                                    <?php if ($index > 0) echo '<br>'; ?>
                                                    <span style="color: #333;">
                                                        <?= htmlspecialchars($item['product_name'] ?? '') ?>
                                                        <?php if (!empty($item['quantity'])): ?>
                                                            <small style="color: #666;"> ×<?= htmlspecialchars($item['quantity']) ?></small>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($package['content_note'] ?? '-') ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($package['items']) && is_array($package['items'])): ?>
                                                <?php foreach ($package['items'] as $index => $item): ?>
                                                    <?php if ($index > 0) echo '<br>'; ?>
                                                    <?= !empty($item['expiry_date']) ? date('Y-m-d', strtotime($item['expiry_date'])) : '-' ?>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <?= $package['expiry_date'] ? date('Y-m-d', strtotime($package['expiry_date'])) : '-' ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($package['items']) && is_array($package['items'])): ?>
                                                <?php foreach ($package['items'] as $index => $item): ?>
                                                    <?php if ($index > 0) echo '<br>'; ?>
                                                    <?= $item['quantity'] ?? '-' ?>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <?= $package['quantity'] ?? '-' ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($package['adjustment_note'] ?? '-') ?></td>
                                        <td class="time-col-default"><?= $package['created_at'] ? date('m-d H:i', strtotime($package['created_at'])) : '-' ?></td>
                                        <td class="time-col-extra" style="display: none;"><?= $package['verified_at'] ? date('m-d H:i', strtotime($package['verified_at'])) : '-' ?></td>
                                        <td class="time-col-default"><?= $package['counted_at'] ? date('m-d H:i', strtotime($package['counted_at'])) : '-' ?></td>
                                        <td class="time-col-extra" style="display: none;"><?= $package['adjusted_at'] ? date('m-d H:i', strtotime($package['adjusted_at'])) : '-' ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary btn-edit-content"
                                                    data-package-id="<?= $package['package_id'] ?>"
                                                    data-tracking-number="<?= htmlspecialchars($package['tracking_number'], ENT_QUOTES) ?>"
                                                    data-current-note="<?= htmlspecialchars($package['content_note'] ?? '', ENT_QUOTES) ?>"
                                                    data-expiry-date="<?= htmlspecialchars($package['expiry_date'] ?? '', ENT_QUOTES) ?>"
                                                    data-quantity="<?= htmlspecialchars($package['quantity'] ?? '', ENT_QUOTES) ?>">
                                                修改内容
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="packages-section" style="margin-top: 20px;">
                <div class="section-header">
                    <h2>批次内物品内容统计</h2>
                    <a href="/express/exp/index.php?action=batch_print&batch_id=<?= $batch_id ?>" target="_blank" class="btn btn-highlight">打印标签预览</a>
                </div>
                <table class="data-table">
                    <thead>
                    <tr>
                        <th style="width: 70%;">内容备注</th>
                        <th style="width: 30%;">数量（单）</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($content_summary)): ?>
                        <tr>
                            <td colspan="2" class="text-center">暂无内容备注数据</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($content_summary as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['content_note']) ?></td>
                                <td><?= $item['package_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // 批量导入快递单号
        document.getElementById('bulk-import-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const trackingNumbers = document.getElementById('tracking_numbers').value;
            const messageDiv = document.getElementById('import-message');

            if (!trackingNumbers.trim()) {
                messageDiv.className = 'message error';
                messageDiv.textContent = '请输入至少一个快递单号';
                messageDiv.style.display = 'block';
                return;
            }

            try {
                const response = await fetch('/express/exp/index.php?action=bulk_import_save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        batch_id: <?= $batch_id ?>,
                        tracking_numbers: trackingNumbers
                    })
                });

                const data = await response.json();

                if (data.success) {
                    let msg = `导入成功！导入: ${data.data.imported} 个，重复: ${data.data.duplicates} 个`;
                    if (data.data.errors.length > 0) {
                        msg += `，失败: ${data.data.errors.length} 个`;
                    }

                    messageDiv.className = 'message success';
                    messageDiv.textContent = msg;
                    messageDiv.style.display = 'block';

                    document.getElementById('tracking_numbers').value = '';

                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = data.message || '导入失败';
                    messageDiv.style.display = 'block';
                }
            } catch (error) {
                messageDiv.className = 'message error';
                messageDiv.textContent = '网络错误：' + error.message;
                messageDiv.style.display = 'block';
            }
        });

        // 添加自定义包裹
        document.getElementById('custom-package-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const count = parseInt(document.getElementById('custom_count').value);
            const messageDiv = document.getElementById('custom-message');

            if (!count || count < 1 || count > 100) {
                messageDiv.className = 'message error';
                messageDiv.textContent = '数量必须在1-100之间';
                messageDiv.style.display = 'block';
                return;
            }

            // 确认操作
            const confirmed = await showConfirm(
                `确定要添加 ${count} 个自定义包裹吗？\n系统将自动生成虚拟快递单号。`,
                '确认添加',
                {
                    confirmText: '确认',
                    cancelText: '取消'
                }
            );
            if (!confirmed) {
                return;
            }

            try {
                const response = await fetch('/express/exp/index.php?action=create_custom_packages', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        batch_id: <?= $batch_id ?>,
                        count: count
                    })
                });

                const data = await response.json();

                if (data.success) {
                    let msg = `成功添加 ${data.data.created.length} 个自定义包裹！`;
                    if (data.data.errors.length > 0) {
                        msg += ` 失败: ${data.data.errors.length} 个`;
                    }

                    messageDiv.className = 'message success';
                    messageDiv.textContent = msg;
                    messageDiv.style.display = 'block';

                    // 显示生成的编号
                    if (data.data.created.length > 0) {
                        const numbers = data.data.created.map(p => p.tracking_number).join(', ');
                        const detailDiv = document.createElement('div');
                        detailDiv.style.marginTop = '10px';
                        detailDiv.innerHTML = `<strong>生成的编号:</strong><br>${numbers}`;
                        messageDiv.appendChild(detailDiv);
                    }

                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = data.message || '添加失败';
                    messageDiv.style.display = 'block';
                }
            } catch (error) {
                messageDiv.className = 'message error';
                messageDiv.textContent = '网络错误：' + error.message;
                messageDiv.style.display = 'block';
            }
        });

        // 修改内容备注
        document.querySelectorAll('.btn-edit-content').forEach(button => {
            button.addEventListener('click', async () => {
                const packageId = button.getAttribute('data-package-id');
                const trackingNumber = button.getAttribute('data-tracking-number') || '';
                const currentNote = button.getAttribute('data-current-note') || '';
                const currentExpiry = button.getAttribute('data-expiry-date') || '';
                const currentQuantity = button.getAttribute('data-quantity') || '';

                // 获取现有的产品明细
                let existingItems = [];
                try {
                    const resp = await fetch(`/express/exp/index.php?action=get_package_items&package_id=${packageId}`);
                    const data = await resp.json();
                    if (data.success && data.data && data.data.length > 0) {
                        existingItems = data.data;
                    }
                } catch (error) {
                    console.error('获取产品明细失败:', error);
                }

                // 如果没有产品明细数据（旧数据），从主表字段初始化第一个产品
                if (existingItems.length === 0 && currentNote) {
                    existingItems = [{
                        product_name: currentNote,
                        quantity: currentQuantity || '',
                        expiry_date: currentExpiry || ''
                    }];
                }

                // 使用模态框输入 - 多产品支持
                const formHtml = `
                    <form id="contentNoteForm" style="padding: 20px;">
                        <div style="background: #f0f4ff; border: 1px solid #d0ddff; border-radius: 6px; padding: 12px; margin-bottom: 16px;">
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; font-size: 13px;">
                                <span style="color: #666; font-weight: 500;">包裹ID:</span>
                                <span style="color: #333; font-weight: 600;">#${packageId}</span>
                                <span style="color: #666; font-weight: 500;">快递单号:</span>
                                <span style="color: #333; font-weight: 600;">${trackingNumber || '-'}</span>
                            </div>
                        </div>
                        <div class="modal-form-group">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <label class="modal-form-label" style="margin: 0;">产品信息</label>
                                <button type="button" class="modal-btn modal-btn-secondary"
                                        onclick="addProductItem()" style="padding: 4px 12px; font-size: 13px;">
                                    + 添加产品
                                </button>
                            </div>
                            <div id="products-container" style="max-height: 400px; overflow-y: auto;">
                                <!-- 产品项将动态添加到这里 -->
                            </div>
                        </div>
                    </form>
                `;

                // [FIX] 改为使用 showDrawer，且移除了不支持的 width 参数
                showDrawer({
                    title: `修改内容信息 - ${trackingNumber || '包裹#' + packageId}`,
                    content: formHtml,
                    footer: `
                        <div class="modal-footer">
                            <button class="modal-btn modal-btn-secondary" data-action="cancel">取消</button>
                            <button class="modal-btn modal-btn-primary" onclick="submitContentNote(${packageId})">保存</button>
                        </div>
                    `
                });

                // 等待DOM渲染完成后，渲染现有产品或添加空白产品
                setTimeout(() => {
                    if (existingItems.length > 0) {
                        existingItems.forEach(item => {
                            addProductItem(item);
                        });
                    } else {
                        // 如果完全没有数据，添加一个空白产品
                        addProductItem();
                    }
                }, 50);
            });
        });

        // 切换时间列显示
        function toggleTimeColumns() {
            const extraCols = document.querySelectorAll('.time-col-extra');
            const toggleText = document.getElementById('toggle-time-text');
            const isHidden = extraCols[0].style.display === 'none';

            extraCols.forEach(col => {
                col.style.display = isHidden ? '' : 'none';
            });

            toggleText.textContent = isHidden ? '隐藏额外时间' : '显示更多时间';
        }
    </script>

    <script src="js/modal.js"></script>
    <script>
    let productItemCounter = 0;

    // 添加产品项
    function addProductItem(existingItem = null) {
        const container = document.getElementById('products-container');
        const itemId = productItemCounter++;
        const productName = existingItem?.product_name || '';
        const quantity = existingItem?.quantity || '';
        const expiryDate = existingItem?.expiry_date || '';

        const itemHtml = `
            <div class="product-item" data-item-id="${itemId}" style="border: 1px solid #ddd; padding: 12px; margin-bottom: 10px; border-radius: 4px; background: #f9f9f9;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <strong style="font-size: 14px;">产品 #<span class="product-number">${container.children.length + 1}</span></strong>
                    <button type="button" class="modal-btn modal-btn-secondary"
                            onclick="removeProductItem(${itemId})" style="padding: 2px 8px; font-size: 12px;">
                        删除
                    </button>
                </div>
                <div style="margin-bottom: 8px;">
                    <label style="display: block; margin-bottom: 4px; font-size: 13px; color: #666;">产品名称 *</label>
                    <input type="text" class="modal-form-control product-name"
                           value="${productName}" placeholder="如：香蕉、苹果等" style="width: 100%;">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <div>
                        <label style="display: block; margin-bottom: 4px; font-size: 13px; color: #666;">数量（选填）</label>
                        <input type="number" class="modal-form-control product-quantity"
                               value="${quantity}" placeholder="数量" min="1" step="1">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 4px; font-size: 13px; color: #666;">保质期（选填）</label>
                        <input type="date" class="modal-form-control product-expiry"
                               value="${expiryDate}">
                    </div>
                </div>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', itemHtml);
    }

    // 删除产品项
    function removeProductItem(itemId) {
        const container = document.getElementById('products-container');
        const item = container.querySelector(`[data-item-id="${itemId}"]`);

        // 如果只剩一个产品，不允许删除
        if (container.children.length <= 1) {
            showAlert('至少需要保留一个产品', '提示', 'warning');
            return;
        }

        if (item) {
            item.remove();
            // 重新编号
            renumberProductItems();
        }
    }

    // 重新编号产品
    function renumberProductItems() {
        const container = document.getElementById('products-container');
        Array.from(container.children).forEach((item, index) => {
            const numberSpan = item.querySelector('.product-number');
            if (numberSpan) {
                numberSpan.textContent = index + 1;
            }
        });
    }

    // 收集产品数据
    function collectProductItems() {
        const container = document.getElementById('products-container');
        const items = [];

        Array.from(container.children).forEach(item => {
            const productName = item.querySelector('.product-name').value.trim();
            const quantity = item.querySelector('.product-quantity').value.trim();
            const expiryDate = item.querySelector('.product-expiry').value.trim();

            if (productName) {  // 只收集有产品名称的项
                items.push({
                    product_name: productName,
                    quantity: quantity || null,
                    expiry_date: expiryDate || null
                });
            }
        });

        return items;
    }

    async function submitContentNote(packageId) {
        const messageDiv = document.getElementById('update-message');

        try {
            // 收集产品数据
            const items = collectProductItems();

            if (items.length === 0) {
                await showAlert('请至少填写一个产品信息', '提示', 'warning');
                return;
            }

            const payload = {
                package_id: packageId,
                items: items
            };

            const resp = await fetch('/express/exp/index.php?action=update_content_note', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await resp.json();

            if (!data.success) {
                await showAlert(data.message || '更新失败', '错误', 'error');
                return;
            }

            await showAlert(data.message, '成功', 'success');
            window.modal.close(true);
            setTimeout(() => window.location.reload(), 800);
        } catch (error) {
            await showAlert('网络错误：' + error.message, '错误', 'error');
        }
    }
    </script>
</body>
</html>
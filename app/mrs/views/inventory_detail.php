<?php
/**
 * Inventory Detail Page
 * 文件路径: app/mrs/views/inventory_detail.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

$product_name = $_GET['sku'] ?? ''; // sku参数现在表示产品名称，不再是content_note组合
$order_by = $_GET['order_by'] ?? 'fifo';

if (empty($product_name)) {
    header('Location: /mrs/ap/index.php?action=inventory_list');
    exit;
}

// 获取库存明细（使用真正的多产品查询）
$packages = mrs_get_true_inventory_detail($pdo, $product_name, $order_by);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>库存明细 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <link rel="stylesheet" href="/mrs/ap/css/modal.css">
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>库存明细: <?= htmlspecialchars($product_name) ?></h1>
            <div class="header-actions">
                <a href="/mrs/ap/index.php?action=inventory_list" class="btn btn-secondary">返回</a>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="info-box" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong>当前在库数量:</strong> <?= count($packages) ?> 箱
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="sort-select" style="margin: 0; font-weight: 500;">排序方式:</label>
                    <select id="sort-select" class="form-control" style="width: auto; min-width: 180px;" onchange="changeSortOrder(this.value)">
                        <option value="fifo" <?= $order_by === 'fifo' ? 'selected' : '' ?>>入库时间↑ (先进先出)</option>
                        <option value="inbound_time_desc" <?= $order_by === 'inbound_time_desc' ? 'selected' : '' ?>>入库时间↓ (后进先出)</option>
                        <option value="expiry_date_asc" <?= $order_by === 'expiry_date_asc' ? 'selected' : '' ?>>有效期↑ (最早到期)</option>
                        <option value="expiry_date_desc" <?= $order_by === 'expiry_date_desc' ? 'selected' : '' ?>>有效期↓ (最晚到期)</option>
                        <option value="days_in_stock_asc" <?= $order_by === 'days_in_stock_asc' ? 'selected' : '' ?>>库存天数↑ (库龄最短)</option>
                        <option value="days_in_stock_desc" <?= $order_by === 'days_in_stock_desc' ? 'selected' : '' ?>>库存天数↓ (库龄最长)</option>
                    </select>
                </div>
            </div>

            <?php if (empty($packages)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <div class="empty-state-text">该物料暂无库存</div>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>批次名称</th>
                            <th>快递单号</th>
                            <th>箱号</th>
                            <th>规格</th>
                            <th>产品明细</th>
                            <th>入库时间</th>
                            <th>库存天数</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($packages as $pkg): ?>
                            <tr>
                                <td><?= htmlspecialchars($pkg['batch_name']) ?></td>
                                <td>
                                    <?php
                                    $tracking = htmlspecialchars($pkg['tracking_number']);
                                    if (mb_strlen($tracking) >= 4) {
                                        $prefix = mb_substr($tracking, 0, -4);
                                        $suffix = mb_substr($tracking, -4);
                                        echo $prefix . '<span style="color: #dc3545; font-weight: bold;">' . $suffix . '</span>';
                                    } else {
                                        echo $tracking;
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($pkg['box_number']) ?></td>
                                <td><?= htmlspecialchars($pkg['spec_info']) ?></td>
                                <td>
                                    <?php if (!empty($pkg['items']) && is_array($pkg['items'])): ?>
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <?php foreach ($pkg['items'] as $item): ?>
                                                <div style="padding: 4px 8px; background: #f8f9fa; border-radius: 4px; font-size: 13px;">
                                                    <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                                    <?php if (!empty($item['quantity'])): ?>
                                                        × <?= htmlspecialchars($item['quantity']) ?>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['expiry_date'])): ?>
                                                        <span style="color: #666; margin-left: 8px;">
                                                            <?= htmlspecialchars($item['expiry_date']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($pkg['inbound_time'])) ?></td>
                                <td><?= $pkg['days_in_stock'] ?> 天</td>
                                <td><span class="badge badge-in-stock">在库</span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-success"
                                            onclick="partialOutbound(<?= $pkg['ledger_id'] ?>, '<?= htmlspecialchars($pkg['content_note'], ENT_QUOTES) ?>', '<?= htmlspecialchars($pkg['ledger_quantity'] ?? '', ENT_QUOTES) ?>')">拆零出货</button>
                                    <button class="btn btn-sm btn-primary"
                                            onclick="editPackage(<?= $pkg['ledger_id'] ?>, '<?= htmlspecialchars($pkg['tracking_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($pkg['box_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($pkg['spec_info'], ENT_QUOTES) ?>', '<?= htmlspecialchars($pkg['content_note'], ENT_QUOTES) ?>', '<?= $pkg['ledger_expiry_date'] ?? '' ?>', '<?= htmlspecialchars($pkg['ledger_quantity'] ?? '', ENT_QUOTES) ?>')">修改</button>
                                    <button class="btn btn-sm btn-danger"
                                            onclick="markVoid(<?= $pkg['ledger_id'] ?>)">标记损耗</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script src="/mrs/ap/js/modal.js"></script>
    <script>
    // 改变排序方式
    function changeSortOrder(orderBy) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('order_by', orderBy);
        window.location.search = urlParams.toString();
    }

    // 产品项计数器（用于生成唯一ID）
    let productItemCounter = 0;

    // 修改包裹信息（支持多产品）
    async function editPackage(ledgerId, trackingNumber, boxNumber, specInfo, contentNote, expiryDate, quantity) {
        // 重置计数器
        productItemCounter = 0;

        // 先获取现有的产品明细
        let items = [];
        try {
            const response = await fetch(`/mrs/ap/index.php?action=get_package_items&ledger_id=${ledgerId}`);
            const data = await response.json();
            if (data.success && Array.isArray(data.data) && data.data.length > 0) {
                items = data.data;
            }
        } catch (error) {
            console.error('Failed to load items:', error);
        }

        // 如果没有产品明细数据（旧数据），从主表字段初始化第一个产品
        if (items.length === 0 && contentNote) {
            items = [{
                product_name: contentNote,
                quantity: quantity || '',
                expiry_date: expiryDate || ''
            }];
        } else if (items.length === 0) {
            // 完全空白的新项
            items = [{product_name: '', quantity: '', expiry_date: ''}];
        }

        const formHtml = `
            <form id="editPackageForm" style="padding: 20px; max-height: 70vh; overflow-y: auto;">
                <div style="background: #f0f4ff; border: 1px solid #d0ddff; border-radius: 6px; padding: 12px; margin-bottom: 16px;">
                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; font-size: 13px;">
                        <span style="color: #666; font-weight: 500;">快递单号:</span>
                        <span style="color: #333; font-weight: 600;">${trackingNumber || '-'}</span>
                        <span style="color: #666; font-weight: 500;">箱号:</span>
                        <span style="color: #333; font-weight: 600;">${boxNumber || '-'}</span>
                    </div>
                </div>
                <div class="modal-form-group">
                    <label class="modal-form-label">规格</label>
                    <input type="text" name="spec_info" class="modal-form-control"
                           value="${specInfo || ''}" placeholder="请输入规格信息">
                </div>
                <div class="modal-form-group" style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <label class="modal-form-label" style="margin: 0;">产品信息</label>
                        <button type="button" class="modal-btn modal-btn-success" onclick="addProductItem()"
                                style="padding: 5px 12px; font-size: 13px;">+ 添加产品</button>
                    </div>
                    <div id="products-container"></div>
                </div>
            </form>
        `;

        showModal({
            title: `修改包裹信息 - ${boxNumber || trackingNumber || '包裹'}`,
            content: formHtml,
            footer: `
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-secondary" data-action="cancel">取消</button>
                    <button class="modal-btn modal-btn-primary" onclick="submitEdit(${ledgerId})">保存</button>
                </div>
            `
        });

        // 等待DOM渲染完成后，立即渲染产品列表
        setTimeout(() => {
            renderProductItems(items);
        }, 50);
    }

    // 渲染产品列表
    function renderProductItems(items) {
        const container = document.getElementById('products-container');
        if (!container) return;

        container.innerHTML = items.map((item, index) => {
            const itemId = productItemCounter++;
            return `
            <div class="product-item-box" data-item-id="${itemId}" style="border: 1px solid #ddd; padding: 12px; margin-bottom: 10px; border-radius: 4px; background: #f9f9f9;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <strong style="color: #667eea;">产品 <span class="product-number">${index + 1}</span></strong>
                    <button type="button" onclick="removeProductItem(${itemId})"
                            style="background: #dc3545; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 16px;">×</button>
                </div>
                <div class="modal-form-group" style="margin-bottom: 8px;">
                    <label style="font-size: 13px; color: #555;">产品名称/内容</label>
                    <input type="text" class="modal-form-control product-name"
                           value="${item.product_name || ''}" placeholder="例如：番茄酱">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="modal-form-group" style="margin-bottom: 0;">
                        <label style="font-size: 13px; color: #555;">数量</label>
                        <input type="text" class="modal-form-control product-quantity"
                               value="${item.quantity || ''}" placeholder="数量">
                    </div>
                    <div class="modal-form-group" style="margin-bottom: 0;">
                        <label style="font-size: 13px; color: #555;">保质期</label>
                        <input type="date" class="modal-form-control product-expiry"
                               value="${item.expiry_date || ''}">
                    </div>
                </div>
            </div>
        `;
        }).join('');
    }

    // 添加产品项
    function addProductItem() {
        const container = document.getElementById('products-container');
        if (!container) return;

        const existingItems = container.querySelectorAll('.product-item-box');
        const itemId = productItemCounter++;
        const displayNumber = existingItems.length + 1;

        const itemHtml = `
            <div class="product-item-box" data-item-id="${itemId}" style="border: 1px solid #ddd; padding: 12px; margin-bottom: 10px; border-radius: 4px; background: #f9f9f9;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <strong style="color: #667eea;">产品 <span class="product-number">${displayNumber}</span></strong>
                    <button type="button" onclick="removeProductItem(${itemId})"
                            style="background: #dc3545; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 16px;">×</button>
                </div>
                <div class="modal-form-group" style="margin-bottom: 8px;">
                    <label style="font-size: 13px; color: #555;">产品名称/内容</label>
                    <input type="text" class="modal-form-control product-name"
                           placeholder="例如：番茄酱">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="modal-form-group" style="margin-bottom: 0;">
                        <label style="font-size: 13px; color: #555;">数量</label>
                        <input type="text" class="modal-form-control product-quantity" placeholder="数量">
                    </div>
                    <div class="modal-form-group" style="margin-bottom: 0;">
                        <label style="font-size: 13px; color: #555;">保质期</label>
                        <input type="date" class="modal-form-control product-expiry">
                    </div>
                </div>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', itemHtml);
        renumberProductItems();
    }

    // 删除产品项
    function removeProductItem(itemId) {
        const container = document.getElementById('products-container');
        if (!container) return;

        const items = container.querySelectorAll('.product-item-box');
        if (items.length <= 1) {
            showAlert('至少需要保留一个产品项', '提示', 'warning');
            return;
        }

        const item = container.querySelector(`.product-item-box[data-item-id="${itemId}"]`);
        if (item) {
            item.remove();
            renumberProductItems();
        }
    }

    // 重新编号产品项
    function renumberProductItems() {
        const container = document.getElementById('products-container');
        if (!container) return;

        const items = container.querySelectorAll('.product-item-box');
        items.forEach((item, index) => {
            const numberSpan = item.querySelector('.product-number');
            if (numberSpan) {
                numberSpan.textContent = index + 1;
            }
        });
    }

    // 收集产品数据
    function collectProductItems() {
        const container = document.getElementById('products-container');
        if (!container) return [];

        const items = container.querySelectorAll('.product-item-box');
        const products = [];

        items.forEach((item, index) => {
            const name = item.querySelector('.product-name').value.trim();
            const quantity = item.querySelector('.product-quantity').value.trim();
            const expiry = item.querySelector('.product-expiry').value.trim();

            if (name || quantity || expiry) {
                products.push({
                    product_name: name || null,
                    quantity: quantity || null,
                    expiry_date: expiry || null,
                    sort_order: index
                });
            }
        });

        return products;
    }

    async function submitEdit(ledgerId) {
        const form = document.getElementById('editPackageForm');
        const specInfo = form.querySelector('[name="spec_info"]').value.trim();

        // 收集产品数据
        const items = collectProductItems();

        if (items.length === 0) {
            await showAlert('请至少填写一个产品信息', '错误', 'error');
            return;
        }

        try {
            const response = await fetch('/mrs/ap/index.php?action=update_package', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ledger_id: ledgerId,
                    spec_info: specInfo,
                    expiry_date: null,  // 向后兼容
                    quantity: null,  // 向后兼容
                    items: items  // 多产品数据
                })
            });

            const data = await response.json();

            if (data.success) {
                await showAlert('修改成功', '成功', 'success');
                location.reload();
            } else {
                await showAlert('修改失败: ' + data.message, '错误', 'error');
            }
        } catch (error) {
            await showAlert('网络错误: ' + error.message, '错误', 'error');
        }

        // 关闭模态框
        window.modal.close(true);
    }

    async function markVoid(ledgerId) {
        const confirmed = await showConfirm(
            '确定要将此包裹标记为损耗/作废吗?',
            '确认标记损耗',
            {
                type: 'warning',
                confirmText: '确认',
                cancelText: '取消'
            }
        );

        if (!confirmed) return;

        // 显示输入框让用户输入损耗原因
        const formHtml = `
            <form id="voidReasonForm" style="padding: 20px;">
                <div class="modal-form-group">
                    <label class="modal-form-label">损耗原因 *</label>
                    <textarea name="reason" class="modal-form-control" rows="3"
                              placeholder="请描述损耗原因..." required></textarea>
                </div>
            </form>
        `;

        const reasonConfirmed = await showModal({
            title: '输入损耗原因',
            content: formHtml,
            footer: `
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-secondary" data-action="cancel">取消</button>
                    <button class="modal-btn modal-btn-primary" onclick="submitVoid(${ledgerId})">提交</button>
                </div>
            `
        });
    }

    async function submitVoid(ledgerId) {
        const form = document.getElementById('voidReasonForm');
        const reason = form.querySelector('[name="reason"]').value.trim();

        if (!reason) {
            await showAlert('请输入损耗原因', '提示', 'warning');
            return;
        }

        try {
            const response = await fetch('/mrs/ap/index.php?action=status_change', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ledger_id: ledgerId,
                    new_status: 'void',
                    reason: reason
                })
            });

            const data = await response.json();

            if (data.success) {
                await showAlert('操作成功', '成功', 'success');
                location.reload();
            } else {
                await showAlert('操作失败: ' + data.message, '错误', 'error');
            }
        } catch (error) {
            await showAlert('网络错误: ' + error.message, '错误', 'error');
        }

        // 关闭模态框
        window.modal.close(true);
    }

    // ==========================================
    // 拆零出货功能
    // ==========================================
    async function partialOutbound(ledgerId, productName, currentQty) {
        // 兜底：如果 modal.js 未加载，给出提示避免点击无响应
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

        const today = new Date().toISOString().split('T')[0];

        const content = `
            <div class="modal-section">
                <div style="background: #e3f2fd; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                    <strong>商品名称：</strong>${productName}<br>
                    <strong>当前库存：</strong><span style="color: #1976d2; font-size: 18px; font-weight: bold;">${availableQty}</span> 件
                </div>

                <div class="form-group">
                    <label for="outbound-date">出库日期 <span style="color: red;">*</span></label>
                    <input type="date" id="outbound-date" class="form-control" value="${today}" required>
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
        const outboundDate = document.getElementById('outbound-date').value;

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

        if (!outboundDate) {
            await showAlert('请选择出库日期', '错误', 'error');
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
                    remark: remark,
                    outbound_date: outboundDate
                })
            });

            const data = await response.json();

            if (data.success) {
                await showAlert(
                    `拆零出货成功！\n\n已从包裹中扣减 ${deductQty} 件\n剩余 ${data.data.remaining_qty} 件\n目的地：${destination}`,
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

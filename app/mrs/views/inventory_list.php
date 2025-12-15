<?php
/**
 * Inventory List Page
 * 文件路径: app/mrs/views/inventory_list.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取库存汇总（使用真正的多产品统计）
$inventory = mrs_get_true_inventory_summary($pdo);
$total_boxes = array_sum(array_column($inventory, 'total_boxes'));
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>库存总览 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <link rel="stylesheet" href="/mrs/ap/css/modal.css">
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>库存总览</h1>
            <div class="header-actions">
                <a href="/mrs/ap/index.php?action=batch_print" class="btn btn-secondary">箱贴打印</a>
                <a href="/mrs/ap/index.php?action=inbound" class="btn btn-primary">入库录入</a>
                <a href="/mrs/ap/index.php?action=outbound" class="btn btn-success">出库核销</a>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_boxes ?></div>
                    <div class="stat-label">在库包裹总数</div>
                </div>
                <div class="stat-card stat-success">
                    <div class="stat-number"><?= count($inventory) ?></div>
                    <div class="stat-label">物料种类</div>
                </div>
            </div>

            <h2 style="margin-bottom: 15px;">库存汇总</h2>

            <?php if (empty($inventory)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <div class="empty-state-text">暂无库存数据</div>
                    <a href="/mrs/ap/index.php?action=inbound" class="btn btn-primary">立即入库</a>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>物料名称</th>
                            <th class="text-center">在库数量</th>
                            <th class="text-center">数量</th>
                            <th class="text-center">最近到期</th>
                            <th class="text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['sku_name']) ?></td>
                                <td class="text-center"><strong><?= $item['total_boxes'] ?></strong> 箱</td>
                                <td class="text-center">
                                    <?php if ($item['total_quantity'] > 0): ?>
                                        约:<strong><?= number_format($item['total_quantity']) ?></strong>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($item['nearest_expiry_date'])): ?>
                                        <?php
                                        $expiry = new DateTime($item['nearest_expiry_date']);
                                        $today = new DateTime();
                                        $diff = $today->diff($expiry);
                                        $days_to_expiry = (int)$today->diff($expiry)->format('%r%a');

                                        // 根据到期天数显示不同颜色
                                        $color_class = '';
                                        if ($days_to_expiry < 0) {
                                            $color_class = 'style="color: #999; text-decoration: line-through;"'; // 已过期：灰色删除线
                                        } elseif ($days_to_expiry <= 7) {
                                            $color_class = 'style="color: #dc3545; font-weight: bold;"'; // 7天内：红色加粗
                                        } elseif ($days_to_expiry <= 30) {
                                            $color_class = 'style="color: #ff9800; font-weight: bold;"'; // 30天内：橙色加粗
                                        } elseif ($days_to_expiry <= 90) {
                                            $color_class = 'style="color: #ffc107;"'; // 90天内：黄色
                                        }
                                        ?>
                                        <span <?= $color_class ?>>
                                            <?= $expiry->format('Y-m-d') ?>
                                            <?php if ($days_to_expiry >= 0): ?>
                                                <small>(<?= $days_to_expiry ?>天)</small>
                                            <?php else: ?>
                                                <small>(已过期)</small>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-success"
                                            onclick="openPartialOutbound('<?= htmlspecialchars($item['sku_name'], ENT_QUOTES) ?>')">拆零出货</button>
                                    <a href="/mrs/ap/index.php?action=inventory_detail&sku=<?= urlencode($item['sku_name']) ?>"
                                       class="btn btn-sm btn-secondary">查看明细</a>
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
    function cleanQty(rawQty) {
        if (!rawQty) return 0;
        const cleaned = String(rawQty).replace(/[^0-9.]/g, '');
        return cleaned ? parseFloat(cleaned) : 0;
    }

    async function openPartialOutbound(skuName) {
        // 兜底：当 modal.js 未加载时给出提示，避免按钮点击无反应
        if (typeof window.showModal !== 'function' || typeof window.showAlert !== 'function') {
            alert('页面脚本未完全加载，请刷新后重试（缺少 modal.js）');
            return;
        }

        try {
            const response = await fetch(`/mrs/ap/index.php?action=outbound&sku=${encodeURIComponent(skuName)}&order_by=fifo&format=json`);
            const data = await response.json();

            if (!data.success || !Array.isArray(data.data.packages) || data.data.packages.length === 0) {
                await showAlert('该物料暂无可出库的在库包裹', '提示', 'warning');
                return;
            }

            const packages = data.data.packages;
            const today = new Date().toISOString().split('T')[0];
            const firstQty = cleanQty(packages[0]?.ledger_quantity ?? packages[0]?.quantity ?? '');

            const optionsHtml = packages.map(pkg => {
                const qty = cleanQty(pkg.ledger_quantity ?? pkg.quantity ?? '');
                const label = `${pkg.batch_name || '-'} / 箱号：${pkg.box_number || '-'} / 库存：${qty}件`;
                return `<option value="${pkg.ledger_id}" data-qty="${qty}">${label}</option>`;
            }).join('');

            const content = `
                <div class="modal-section">
                    <div class="form-group">
                        <label for="package-select">选择出库包裹 <span style="color: red;">*</span></label>
                        <select id="package-select" class="form-control">${optionsHtml}</select>
                    </div>

                    <div class="form-group">
                        <label for="outbound-date">出库日期 <span style="color: red;">*</span></label>
                        <input type="date" id="outbound-date" class="form-control" value="${today}" required>
                    </div>

                    <div class="form-group">
                        <label for="outbound-qty">出货数量 <span style="color: red;">*</span></label>
                        <input type="number" id="outbound-qty" class="form-control" min="0.01" step="0.01" max="${firstQty}" required>
                        <small id="available-tip" style="color: #666;">可出货数量：${firstQty} 件</small>
                    </div>

                    <div class="form-group">
                        <label for="destination">目的地（门店） <span style="color: red;">*</span></label>
                        <input type="text" id="destination" class="form-control" placeholder="请输入门店名称" required>
                    </div>

                    <div class="form-group">
                        <label for="remark">备注</label>
                        <textarea id="remark" class="form-control" rows="2" placeholder="选填"></textarea>
                    </div>
                </div>
            `;

            // 使用自定义模态框
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

            const packageSelect = document.getElementById('package-select');
            if (!packageSelect || !packageSelect.selectedOptions || packageSelect.selectedOptions.length === 0) {
                await showAlert('请选择要出库的包裹', '错误', 'error');
                return;
            }
            const selectedOption = packageSelect.selectedOptions[0];
            const ledgerId = parseInt(selectedOption.value, 10);
            const availableQty = parseFloat(selectedOption.dataset.qty || '0');

            const outboundQty = parseFloat(document.getElementById('outbound-qty').value);
            const outboundDate = document.getElementById('outbound-date').value;
            const destination = document.getElementById('destination').value.trim();
            const remark = document.getElementById('remark').value.trim();

            if (!ledgerId) {
                await showAlert('请选择要出库的包裹', '提示', 'warning');
                return;
            }

            if (!outboundQty || outboundQty <= 0) {
                await showAlert('请输入有效的出货数量', '错误', 'error');
                return;
            }

            if (outboundQty > availableQty) {
                await showAlert(`出货数量（${outboundQty}）超过库存（${availableQty}）`, '错误', 'error');
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

            const responseSave = await fetch('/mrs/ap/index.php?action=partial_outbound', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ledger_id: ledgerId,
                    deduct_qty: outboundQty,
                    destination: destination,
                    remark: remark,
                    outbound_date: outboundDate
                })
            });

            const result = await responseSave.json();

            if (result.success) {
                await showAlert(`拆零出货成功！\n\n已扣减 ${outboundQty} 件\n剩余 ${result.data.remaining_qty} 件`, '成功', 'success');
                window.location.reload();
            } else {
                await showAlert('操作失败: ' + result.message, '错误', 'error');
            }
        } catch (error) {
            await showAlert('网络错误: ' + error.message, '错误', 'error');
        }
    }

    // 根据选择的包裹更新提示
    document.addEventListener('change', function(event) {
        if (event.target && event.target.id === 'package-select') {
            const option = (event.target.selectedOptions && event.target.selectedOptions.length > 0)
                ? event.target.selectedOptions[0]
                : null;
            const qty = option ? parseFloat(option.dataset.qty || '0') : 0;
            const tip = document.getElementById('available-tip');
            if (tip) {
                tip.textContent = `可出货数量：${qty} 件`;
            }
            const qtyInput = document.getElementById('outbound-qty');
            if (qtyInput) {
                qtyInput.max = qty;
                qtyInput.value = '';
            }
        }
    });

    </script>
</body>
</html>

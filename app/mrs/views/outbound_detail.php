<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?> - MRS</title>
    <link rel="stylesheet" href="/mrs/css/backend.css">
</head>
<body>
    <header>
        <div class="title"><?php echo htmlspecialchars($page_title); ?></div>
        <div class="user">
            欢迎, <?php echo htmlspecialchars($_SESSION['user_display_name'] ?? '用户'); ?> | <a href="/mrs/be/index.php?action=logout">登出</a>
        </div>
    </header>
    <div class="layout">
        <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>
        <main class="content">
            <div class="card">
                <div class="flex-between">
                    <h2>出库单详情</h2>
                    <div>
                        <?php if ($outbound['status'] === 'draft'): ?>
                            <a href="/mrs/be/index.php?action=outbound_create&id=<?php echo $outbound['outbound_order_id']; ?>">
                                <button class="secondary">编辑</button>
                            </a>
                            <button class="primary" onclick="confirmOutbound()">确认出库</button>
                        <?php endif; ?>
                        <a href="/mrs/be/index.php?action=outbound_list">
                            <button class="text">返回列表</button>
                        </a>
                    </div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>

                <?php
                $type_map = [1 => '领料', 2 => '调拨', 3 => '退货', 4 => '报废'];
                $status_map = ['draft' => '草稿', 'confirmed' => '已确认', 'cancelled' => '已取消'];
                $status_class = ['draft' => 'badge-warning', 'confirmed' => 'badge-success', 'cancelled' => 'badge-secondary'];
                ?>

                <!-- 出库单基本信息 -->
                <div class="info-grid mt-3">
                    <div class="info-item">
                        <label>出库单号</label>
                        <div><strong><?php echo htmlspecialchars($outbound['outbound_code']); ?></strong></div>
                    </div>
                    <div class="info-item">
                        <label>出库日期</label>
                        <div><?php echo htmlspecialchars($outbound['outbound_date']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>出库类型</label>
                        <div><?php echo $type_map[$outbound['outbound_type']] ?? '未知'; ?></div>
                    </div>
                    <div class="info-item">
                        <label>去向</label>
                        <div><?php echo htmlspecialchars($outbound['location_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>状态</label>
                        <div><span class="badge <?php echo $status_class[$outbound['status']] ?? ''; ?>"><?php echo $status_map[$outbound['status']] ?? $outbound['status']; ?></span></div>
                    </div>
                    <div class="info-item">
                        <label>创建时间</label>
                        <div><?php echo date('Y-m-d H:i', strtotime($outbound['created_at'])); ?></div>
                    </div>
                    <?php if ($outbound['remark']): ?>
                        <div class="info-item" style="grid-column: span 2;">
                            <label>备注</label>
                            <div><?php echo htmlspecialchars($outbound['remark']); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 出库明细 -->
            <div class="card">
                <h3>出库明细</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>物料名称</th>
                                <th>箱数</th>
                                <th>散件数</th>
                                <th>总标准单位</th>
                                <th>备注</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($items)): ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($item['sku_name']); ?></strong></td>
                                        <td>
                                            <?php
                                            if ($item['outbound_case_qty'] > 0) {
                                                echo number_format($item['outbound_case_qty'], 2);
                                                if ($item['case_unit_name']) {
                                                    echo ' ' . htmlspecialchars($item['case_unit_name']);
                                                }
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($item['outbound_single_qty'] > 0) {
                                                echo number_format($item['outbound_single_qty'], 2);
                                                if ($item['unit_name']) {
                                                    echo ' ' . htmlspecialchars($item['unit_name']);
                                                }
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <strong><?php echo number_format($item['total_standard_qty'], 2); ?></strong>
                                            <?php if ($item['unit_name']): ?>
                                                <?php echo htmlspecialchars($item['unit_name']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['remark'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center muted">无出库明细</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
    async function confirmOutbound() {
        if (!confirm('确定要确认此出库单吗？确认后将无法修改。')) {
            return;
        }

        try {
            const response = await fetch('/mrs/be/index.php?action=backend_confirm_outbound', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    outbound_order_id: <?php echo $outbound['outbound_order_id']; ?>
                })
            });

            const result = await response.json();

            if (result.success) {
                alert('出库单确认成功！');
                window.location.reload();
            } else {
                alert('确认失败：' + (result.message || '未知错误'));
            }
        } catch (error) {
            alert('网络错误：' + error.message);
        }
    }
    </script>
</body>
</html>

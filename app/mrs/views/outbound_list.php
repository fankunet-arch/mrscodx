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
                <div class="card-header card-header-clean">
                    <div>
                        <h2>出库管理</h2>
                        <p class="card-subtitle">通过组合筛选快速定位需要的出库单。</p>
                    </div>
                    <a href="/mrs/be/index.php?action=outbound_create" class="primary">新建出库单</a>
                </div>

                <!-- 筛选区 -->
                <form action="/mrs/be/index.php" method="get" class="mb-3">
                    <input type="hidden" name="action" value="outbound_list">
                    <div class="filter-panel">
                        <div class="filter-row">
                            <div class="search-box">
                                <span class="search-icon" aria-hidden="true">🔍</span>
                                <input type="text" name="search" placeholder="搜索出库单号、去向或备注..." value="<?php echo htmlspecialchars($search); ?>" aria-label="搜索出库单号、去向或备注">
                            </div>
                            <div class="filter-row-actions">
                                <button type="submit" class="primary">搜索</button>
                                <a href="/mrs/be/index.php?action=outbound_list" class="text">重置</a>
                            </div>
                        </div>

                        <div class="filter-grid">
                            <div class="filter-field">
                                <label for="date_start">开始日期</label>
                                <input id="date_start" type="date" name="date_start" value="<?php echo htmlspecialchars($date_start); ?>" placeholder="开始日期">
                            </div>
                            <div class="filter-field">
                                <label for="date_end">结束日期</label>
                                <input id="date_end" type="date" name="date_end" value="<?php echo htmlspecialchars($date_end); ?>" placeholder="结束日期">
                            </div>
                            <div class="filter-field">
                                <label for="outbound_type">出库类型</label>
                                <select id="outbound_type" name="outbound_type">
                                    <option value="">全部类型</option>
                                    <option value="1" <?php echo $outbound_type === '1' ? 'selected' : ''; ?>>领料</option>
                                    <option value="2" <?php echo $outbound_type === '2' ? 'selected' : ''; ?>>调拨</option>
                                    <option value="3" <?php echo $outbound_type === '3' ? 'selected' : ''; ?>>退货</option>
                                    <option value="4" <?php echo $outbound_type === '4' ? 'selected' : ''; ?>>报废</option>
                                </select>
                            </div>
                            <div class="filter-field">
                                <label for="status">状态</label>
                                <select id="status" name="status">
                                    <option value="">全部状态</option>
                                    <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>草稿</option>
                                    <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>已确认</option>
                                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>已取消</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 出库单列表 -->
            <div class="card">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>出库单号</th>
                                <th>出库日期</th>
                                <th>类型</th>
                                <th>去向</th>
                                <th>状态</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($outbounds)): ?>
                                <?php
                                $type_map = [1 => '领料', 2 => '调拨', 3 => '退货', 4 => '报废'];
                                $status_map = ['draft' => '草稿', 'confirmed' => '已确认', 'cancelled' => '已取消'];
                                $status_class = ['draft' => 'badge-warning', 'confirmed' => 'badge-success', 'cancelled' => 'badge-secondary'];
                                ?>
                                <?php foreach ($outbounds as $outbound): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($outbound['outbound_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($outbound['outbound_date']); ?></td>
                                        <td><?php echo $type_map[$outbound['outbound_type']] ?? '未知'; ?></td>
                                        <td><?php echo htmlspecialchars($outbound['location_name'] ?? '-'); ?></td>
                                        <td><span class="badge <?php echo $status_class[$outbound['status']] ?? ''; ?>"><?php echo $status_map[$outbound['status']] ?? $outbound['status']; ?></span></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($outbound['created_at'])); ?></td>
                                        <td>
                                            <a href="/mrs/be/index.php?action=outbound_detail&id=<?php echo $outbound['outbound_order_id']; ?>"><button class="secondary small">查看</button></a>
                                            <?php if ($outbound['status'] === 'draft'): ?>
                                                <a href="/mrs/be/index.php?action=outbound_create&id=<?php echo $outbound['outbound_order_id']; ?>"><button class="text small">编辑</button></a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center muted">暂无出库单</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>

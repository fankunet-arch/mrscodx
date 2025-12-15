<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>MRS 管理系统 - <?php echo htmlspecialchars($page_title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/mrs/css/backend.css">
    <style>
        .batch-table thead th.header-cell {
            background: #f8fafc;
            vertical-align: middle;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 700;
            color: #1f2937;
        }

        .batch-table .th-main {
            display: block;
            line-height: 1.4;
        }

        .batch-table .header-hint {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            color: #6b7280;
            font-weight: 400;
        }

        .batch-table .sort-link {
            color: inherit;
            text-decoration: none;
        }

        .batch-table .sort-link:hover {
            color: #2563eb;
        }
    </style>
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

<?php
/**
 * Generates table header with sorting links.
 * @param string $column_name The database column name.
 * @param string $display_name The name to display.
 * @param string $current_sort The current sorting column.
 * @param string $current_order The current sorting order.
 * @return string HTML for the table header.
 */
function sortable_th($column_name, $display_name, $hint, $current_sort, $current_order)
{
    $order = ($current_sort === $column_name && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $icon = '';
    if ($current_sort === $column_name) {
        $icon = $current_order === 'ASC' ? ' ▲' : ' ▼';
    }

    return '<th class="header-cell">'
        . '<span class="th-main">'
        . '<a class="sort-link" href="?action=batch_list&sort=' . $column_name . '&order=' . $order . '">'
        . $display_name . $icon . '</a>'
        . '</span>'
        . '<span class="header-hint">' . htmlspecialchars($hint) . '</span>'
        . '</th>';
}

/**
 * Determines the display properties for a batch based on its state.
 * @param array $batch The batch data array.
 * @return array An array containing class, badge_class, text, and button info.
 */
function get_batch_display_properties($batch)
{
    $status = $batch['batch_status'];

    // 用于批次清点状态的指标，默认值为0以避免未设置时的警告
    $total_packages = (int)($batch['total_package_count'] ?? 0);
    $verified_packages = (int)($batch['verified_package_count'] ?? 0);
    $counted_packages = (int)($batch['counted_package_count'] ?? 0);
    $adjusted_packages = (int)($batch['adjusted_package_count'] ?? 0);

    $properties = [
        'row_class' => '',
        'badge_class' => 'badge-secondary',
        'status_text' => '未知',
        'button_text' => '查看',
        'button_class' => 'btn-secondary',
        'action' => 'batch_detail'
    ];

    // 状态规则（防止所有批次都显示为绿色“进行中”）
    if ($total_packages === 0) {
        $properties['badge_class'] = 'badge-secondary';
        $properties['status_text'] = '等待录入';
        $properties['button_text'] = '';
        $properties['button_class'] = '';
        $properties['action'] = '';
    } elseif ($verified_packages === 0 && $counted_packages === 0 && $adjusted_packages === 0) {
        $properties['badge_class'] = 'badge-warning text-dark';
        $properties['status_text'] = '等待中';
    } elseif ($total_packages === $verified_packages && $verified_packages !== $counted_packages) {
        $properties['badge_class'] = 'badge-info';
        $properties['status_text'] = '待清点';
    } elseif ($total_packages > 0 && $total_packages === $counted_packages) {
        $properties['badge_class'] = 'badge-info';
        $properties['status_text'] = '清点完成';
    } elseif ($total_packages > 0 && $total_packages > $verified_packages) {
        $properties['badge_class'] = 'badge-success';
        $properties['status_text'] = '进行中';
    } else {
        // 兜底展示数据库状态，避免出现空白
        $properties['status_text'] = ucfirst($status);
    }

    return $properties;
}

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">收货批次列表</h4>
                    <a href="?action=batch_create" class="btn btn-primary btn-sm float-right">创建新批次</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover batch-table">
                            <thead>
                                <tr>
                                    <th class="header-cell">
                                        <span class="th-main">ID</span>
                                        <span class="header-hint">批次编号</span>
                                    </th>
                                    <?php echo sortable_th('batch_code', '参考号', '批次号', $sort_column, $sort_order); ?>
                                    <?php echo sortable_th('batch_status', '状态', '当前阶段', $sort_column, $sort_order); ?>
                                    <?php echo sortable_th('total_package_count', '总包裹数', '记录总数', $sort_column, $sort_order); ?>
                                    <?php echo sortable_th('verified_package_count', '已核实', '已核实包裹', $sort_column, $sort_order); ?>
                                    <?php echo sortable_th('counted_package_count', '已清点', '清点完成包裹', $sort_column, $sort_order); ?>
                                    <th class="header-cell">
                                        <span class="th-main">已调整</span>
                                        <span class="header-hint">库存调整数</span>
                                    </th>
                                    <?php echo sortable_th('created_at', '创建时间', '批次建立时间', $sort_column, $sort_order); ?>
                                    <?php echo sortable_th('updated_at', '更新时间', '最近操作时间', $sort_column, $sort_order); ?>
                                    <th class="header-cell">
                                        <span class="th-main">操作</span>
                                        <span class="header-hint">进入或确认</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($batches)) : ?>
                                    <tr>
                                        <td colspan="7" class="text-center">没有找到任何批次。</td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($batches as $batch) :
                                        $props = get_batch_display_properties($batch);
                                    ?>
                                        <tr class="<?php echo $props['row_class']; ?>">
                                            <td><?php echo $batch['batch_id']; ?></td>
                                            <td><?php echo htmlspecialchars($batch['batch_code']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $props['badge_class']; ?>">
                                                    <?php echo $props['status_text']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $batch['total_package_count']; ?></td>
                                            <td><?php echo $batch['verified_package_count']; ?></td>
                                            <td><?php echo $batch['counted_package_count']; ?></td>
                                            <td><?php echo $batch['adjusted_package_count']; ?></td>
                                            <td><?php echo $batch['created_at']; ?></td>
                                            <td><?php echo $batch['updated_at']; ?></td>
                                            <td>
                                                <?php if (!empty($props['button_text'])): ?>
                                                    <a href="?action=<?php echo $props['action']; ?>&id=<?php echo $batch['batch_id']; ?>" class="btn <?php echo $props['button_class']; ?> btn-sm">
                                                        <?php echo $props['button_text']; ?>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

        </main>
    </div>
</body>
</html>
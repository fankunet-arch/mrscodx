<?php
/**
 * Backend Batch List Page
 * 文件路径: app/express/views/batch_list.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$batches = express_get_batches($pdo, 'all', 100);

function render_batch_status(array $batch): array
{
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
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批次列表 - Express Backend</title>
    <link rel="stylesheet" href="../css/backend.css">
</head>
<body>
    <?php include EXPRESS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <h1>批次列表</h1>
            <div class="header-actions">
                <a href="/express/exp/index.php?action=batch_create" class="btn btn-primary">创建新批次</a>
            </div>
        </header>

        <div class="content-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>批次ID</th>
                        <th>批次名称</th>
                        <th>状态</th>
                        <th>总包裹数</th>
                        <th>已核实</th>
                        <th>已清点</th>
                        <th>已调整</th>
                        <th>创建时间</th>
                        <th>创建人</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                        <tr>
                            <td colspan="10" class="text-center">暂无批次数据</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($batches as $batch): ?>
                            <?php $status_info = render_batch_status($batch); ?>
                            <tr>
                                <td><?= $batch['batch_id'] ?></td>
                                <td><?= htmlspecialchars($batch['batch_name']) ?></td>
                                <td>
                                    <span class="badge badge-<?= htmlspecialchars($status_info['class']) ?>">
                                        <?= htmlspecialchars($status_info['label']) ?>
                                    </span>
                                </td>
                                <td><?= $batch['total_count'] ?></td>
                                <td><?= $batch['verified_count'] ?></td>
                                <td><?= $batch['counted_count'] ?></td>
                                <td><?= $batch['adjusted_count'] ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($batch['created_at'])) ?></td>
                                <td><?= htmlspecialchars($batch['created_by'] ?? '-') ?></td>
                                <td>
                                    <a href="/express/exp/index.php?action=batch_edit&batch_id=<?= $batch['batch_id'] ?>"
                                       class="btn btn-sm btn-secondary" style="margin-right: 6px;">编辑</a>
                                    <a href="/express/exp/index.php?action=batch_detail&batch_id=<?= $batch['batch_id'] ?>"
                                       class="btn btn-sm btn-info">详情</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

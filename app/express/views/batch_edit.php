<?php
/**
 * Backend Edit Batch Page
 * 文件路径: app/express/views/batch_edit.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}

$batch_id = (int)($_GET['batch_id'] ?? 0);

if ($batch_id <= 0) {
    die('批次ID不能为空');
}

$batch = express_get_batch_by_id($pdo, $batch_id);

if (!$batch) {
    die('批次不存在');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑批次 - <?= htmlspecialchars($batch['batch_name']) ?></title>
    <link rel="stylesheet" href="../css/backend.css">
</head>
<body>
    <?php include EXPRESS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <h1>编辑批次</h1>
            <div class="header-actions">
                <a href="/express/exp/index.php?action=batch_detail&batch_id=<?= $batch_id ?>" class="btn btn-secondary">返回详情</a>
            </div>
        </header>

        <div class="content-wrapper">
            <form id="batch-edit-form" class="form-horizontal">
                <input type="hidden" id="batch_id" value="<?= $batch_id ?>">

                <div class="form-group">
                    <label for="batch_name">批次名称: <span class="required">*</span></label>
                    <input type="text" id="batch_name" name="batch_name" class="form-control"
                           value="<?= htmlspecialchars($batch['batch_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="status">状态:</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" <?= $batch['status'] === 'active' ? 'selected' : '' ?>>进行中</option>
                        <option value="closed" <?= $batch['status'] === 'closed' ? 'selected' : '' ?>>已关闭</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="notes">备注:</label>
                    <textarea id="notes" name="notes" class="form-control" rows="4" placeholder="批次备注信息"><?=
                        htmlspecialchars($batch['notes'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">保存修改</button>
                    <a href="/express/exp/index.php?action=batch_detail&batch_id=<?= $batch_id ?>" class="btn btn-secondary">取消</a>
                </div>
            </form>

            <div id="message" class="message" style="display: none;"></div>
        </div>
    </div>

    <script>
        document.getElementById('batch-edit-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const batchId = document.getElementById('batch_id').value;
            const batchName = document.getElementById('batch_name').value.trim();
            const status = document.getElementById('status').value;
            const notes = document.getElementById('notes').value.trim();
            const messageDiv = document.getElementById('message');

            if (!batchName) {
                messageDiv.className = 'message error';
                messageDiv.textContent = '批次名称不能为空';
                messageDiv.style.display = 'block';
                return;
            }

            try {
                const response = await fetch('/express/exp/index.php?action=batch_edit_save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        batch_id: batchId,
                        batch_name: batchName,
                        status: status,
                        notes: notes
                    })
                });

                const data = await response.json();

                if (data.success) {
                    messageDiv.className = 'message success';
                    messageDiv.textContent = data.message || '保存成功';
                    messageDiv.style.display = 'block';
                    setTimeout(() => {
                        window.location.href = '/express/exp/index.php?action=batch_detail&batch_id=' + batchId;
                    }, 800);
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = data.message || '保存失败';
                    messageDiv.style.display = 'block';
                }
            } catch (error) {
                messageDiv.className = 'message error';
                messageDiv.textContent = '网络错误：' + error.message;
                messageDiv.style.display = 'block';
            }
        });
    </script>
</body>
</html>

<?php
/**
 * Backend Create Batch Page
 * 文件路径: app/express/views/batch_create.php
 */

if (!defined('EXPRESS_ENTRY')) {
    die('Access denied');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建批次 - Express Backend</title>
    <link rel="stylesheet" href="../css/backend.css">
</head>
<body>
    <?php include EXPRESS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <h1>创建新批次</h1>
        </header>

        <div class="content-wrapper">
            <form id="batch-create-form" class="form-horizontal">
                <div class="form-group">
                    <label for="batch_name">批次名称: <span class="required">*</span></label>
                    <input type="text" id="batch_name" name="batch_name" class="form-control"
                           placeholder="例如：2024-11-28批次" required>
                    <small class="form-text">批次名称必须唯一</small>
                </div>

                <div class="form-group">
                    <label for="notes">备注:</label>
                    <textarea id="notes" name="notes" class="form-control" rows="4"
                              placeholder="批次备注信息"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">创建批次</button>
                    <a href="/express/exp/index.php?action=batch_list" class="btn btn-secondary">返回列表</a>
                </div>
            </form>

            <div id="message" class="message" style="display: none;"></div>
        </div>
    </div>

    <script>
        document.getElementById('batch-create-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const batch_name = document.getElementById('batch_name').value.trim();
            const notes = document.getElementById('notes').value.trim();
            const messageDiv = document.getElementById('message');

            if (!batch_name) {
                messageDiv.className = 'message error';
                messageDiv.textContent = '批次名称不能为空';
                messageDiv.style.display = 'block';
                return;
            }

            try {
                const response = await fetch('/express/exp/index.php?action=batch_create_save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        batch_name: batch_name,
                        notes: notes,
                        created_by: '<?= $_SESSION['user_login'] ?? 'admin' ?>'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    messageDiv.className = 'message success';
                    messageDiv.textContent = '批次创建成功！正在跳转...';
                    messageDiv.style.display = 'block';

                    setTimeout(() => {
                        window.location.href = '/express/exp/index.php?action=batch_detail&batch_id=' + data.data.batch_id;
                    }, 1000);
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = data.message || '创建失败';
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

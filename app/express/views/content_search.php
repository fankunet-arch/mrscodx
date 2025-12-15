<?php
/**
 * Backend Content Search Page
 * 文件路径: app/express/views/content_search.php
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
    <title>物品内容搜索 - Express Backend</title>
    <link rel="stylesheet" href="../css/backend.css">
</head>
<body>
<?php include EXPRESS_VIEW_PATH . '/shared/sidebar.php'; ?>

<div class="main-content">
    <header class="page-header">
        <h1>物品内容搜索</h1>
    </header>

    <div class="content-wrapper">
        <form id="content-search-form" class="form-inline" style="margin-bottom: 15px;">
            <div class="form-group" style="flex: 1;">
                <label for="keyword" style="margin-right: 8px;">内容备注关键词:</label>
                <input type="text" id="keyword" class="form-control" placeholder="例如：杯子、吸管" style="width: 100%;">
            </div>
            <button type="submit" class="btn btn-primary" style="margin-left: 10px;">搜索</button>
        </form>

        <div id="search-message" class="message" style="display: none;"></div>

        <table class="data-table" id="search-result-table" style="display: none;">
            <thead>
            <tr>
                <th>内容备注</th>
                <th>快递单号</th>
                <th>批次</th>
                <th>状态</th>
                <th>时间</th>
            </tr>
            </thead>
            <tbody id="search-result-body"></tbody>
        </table>
    </div>
</div>

<script>
    const form = document.getElementById('content-search-form');
    const keywordInput = document.getElementById('keyword');
    const messageBox = document.getElementById('search-message');
    const resultTable = document.getElementById('search-result-table');
    const resultBody = document.getElementById('search-result-body');

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const keyword = keywordInput.value.trim();

        messageBox.style.display = 'none';
        resultTable.style.display = 'none';
        resultBody.innerHTML = '';

        if (!keyword) {
            messageBox.className = 'message error';
            messageBox.textContent = '请输入要搜索的内容备注关键词';
            messageBox.style.display = 'block';
            return;
        }

        try {
            const resp = await fetch(`/express/exp/index.php?action=content_search_api&keyword=${encodeURIComponent(keyword)}`);
            const data = await resp.json();

            if (!data.success) {
                messageBox.className = 'message error';
                messageBox.textContent = data.message || '搜索失败';
                messageBox.style.display = 'block';
                return;
            }

            if (!data.data || data.data.length === 0) {
                messageBox.className = 'message';
                messageBox.textContent = '没有找到匹配的记录';
                messageBox.style.display = 'block';
                return;
            }

            data.data.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(item.content_note || '')}</td>
                    <td>${escapeHtml(item.tracking_number)}</td>
                    <td>${escapeHtml(item.batch_name)} (ID: ${item.batch_id})</td>
                    <td>${renderStatus(item.package_status)}</td>
                    <td>${formatTime(item.counted_at || item.created_at)}</td>
                `;
                resultBody.appendChild(tr);
            });

            resultTable.style.display = 'table';
        } catch (error) {
            messageBox.className = 'message error';
            messageBox.textContent = '网络错误：' + error.message;
            messageBox.style.display = 'block';
        }
    });

    function formatTime(value) {
        if (!value) return '-';
        const dt = new Date(value);
        return isNaN(dt.getTime()) ? value : dt.toLocaleString();
    }

    function renderStatus(status) {
        const map = {
            pending: '待处理',
            verified: '已核实',
            counted: '已清点',
            adjusted: '已调整'
        };
        const label = map[status] || status;
        return `<span class="badge badge-${status}">${label}</span>`;
    }

    function escapeHtml(str) {
        return (str || '').replace(/[&<>'"]/g, (c) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[c]);
    }
</script>
</body>
</html>

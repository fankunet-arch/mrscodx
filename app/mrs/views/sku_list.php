<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>MRS 管理系统 - <?php echo htmlspecialchars($page_title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
                    <form action="/mrs/be/index.php" method="get">
                        <input type="hidden" name="action" value="sku_list">
                        <input type="text" name="search" placeholder="搜索..." value="<?php echo htmlspecialchars($keyword); ?>">
                        <button type="submit" class="secondary">搜索</button>
                    </form>
                    <div>
                        <button class="secondary" onclick="showImportModal()">📋 批量导入</button>
                        <a href="/mrs/be/index.php?action=sku_edit"><button class="primary">新建 SKU</button></a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>物料名称</th>
                                <th>编码</th>
                                <th>品牌</th>
                                <th>分类</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($skus)): ?>
                                <tr><td colspan="6" class="empty">未找到物料。</td></tr>
                            <?php else: ?>
                                <?php foreach ($skus as $sku): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sku['sku_id']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($sku['sku_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($sku['sku_code']); ?></td>
                                    <td><?php echo htmlspecialchars($sku['brand_name']); ?></td>
                                    <td><?php echo htmlspecialchars($sku['category_name']); ?></td>
                                    <td>
                                        <a href="/mrs/be/index.php?action=sku_edit&id=<?php echo $sku['sku_id']; ?>">
                                            <button class="text info">编辑</button>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- 批量导入SKU模态框 -->
    <div class="modal-backdrop" id="modal-import-sku" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3>批量导入 SKU</h3>
                <button class="text" onclick="closeModal('modal-import-sku')">×</button>
            </div>
            <div class="modal-body">
                <p class="muted small mb-2">请粘贴 AI 识别后的文本。格式：[品名] | [箱规] | [单位] | [品类]</p>
                <textarea id="import-sku-text" rows="10" placeholder="90-700注塑细磨砂杯 | 500 | 箱 | 包材&#10;茉莉银毫 | 500g/30包 | 箱 | 茶叶" style="width: 100%; font-family: monospace;"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="light-success" style="margin-right: auto;" onclick="showAiPromptModal()">💡 获取 AI 提示词</button>
                <button type="button" class="text" onclick="closeModal('modal-import-sku')">取消</button>
                <button class="primary" onclick="importSkus()">开始导入</button>
            </div>
        </div>
    </div>

    <!-- AI提示词模态框 -->
    <div class="modal-backdrop" id="modal-ai-prompt" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3>AI 提示词模板</h3>
                <button class="text" onclick="closeModal('modal-ai-prompt')">×</button>
            </div>
            <div class="modal-body">
                <textarea id="ai-prompt-text" rows="12" readonly style="width: 100%; font-family: monospace; background: #f9fafb;">请帮我识别以下图片中的物料信息，并按照指定格式输出。

要求：
1. 识别每一行物料的：品名、箱规、单位、品类
2. 输出格式：[品名] | [箱规] | [单位] | [品类]
3. 每个物料一行
4. 如果某些信息缺失，请用"未知"标注

示例输出：
90-700注塑细磨砂杯 | 500 | 箱 | 包材
茉莉银毫 | 500g/30包 | 箱 | 茶叶
</textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="text" onclick="closeModal('modal-ai-prompt')">返回</button>
                <button type="button" class="success" onclick="copyAiPrompt()">复制提示词</button>
            </div>
        </div>
    </div>

    <script>
    function showImportModal() {
        document.getElementById('modal-import-sku').style.display = 'flex';
    }

    function showAiPromptModal() {
        document.getElementById('modal-ai-prompt').style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function copyAiPrompt() {
        const promptText = document.getElementById('ai-prompt-text');
        promptText.select();
        document.execCommand('copy');
        alert('提示词已复制到剪贴板！');
    }

    async function importSkus() {
        const text = document.getElementById('import-sku-text').value.trim();
        if (!text) {
            alert('请输入要导入的内容');
            return;
        }

        if (!confirm('确定要导入这些SKU吗？')) {
            return;
        }

        try {
            const response = await fetch('/mrs/be/index.php?action=backend_import_skus_text', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text: text })
            });

            const result = await response.json();
            if (result.success) {
                alert(`导入成功！共导入 ${result.data.success_count} 个SKU。`);
                closeModal('modal-import-sku');
                window.location.reload();
            } else {
                alert('导入失败：' + (result.message || '未知错误'));
            }
        } catch (error) {
            alert('网络错误：' + error.message);
        }
    }

    // 点击模态框背景关闭
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>

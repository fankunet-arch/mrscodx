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
                    <form action="/mrs/be/index.php" method="get">
                        <input type="hidden" name="action" value="category_list">
                        <input type="text" name="search" placeholder="搜索品类..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="secondary">搜索</button>
                    </form>
                    <a href="/mrs/be/index.php?action=category_edit"><button class="primary">新建品类</button></a>
                </div>
            </div>

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
                                <th>品类名称</th>
                                <th>品类编码</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($category['category_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($category['category_code'] ?? '-'); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($category['created_at'])); ?></td>
                                        <td>
                                            <a href="/mrs/be/index.php?action=category_edit&id=<?php echo $category['category_id']; ?>"><button class="secondary small">编辑</button></a>
                                            <button class="danger small" onclick="deleteCategory(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars($category['category_name'], ENT_QUOTES); ?>')">删除</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center muted">暂无品类</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
    async function deleteCategory(categoryId, categoryName) {
        if (!confirm(`确定要删除品类"${categoryName}"吗？\n注意：如果该品类下有SKU，将无法删除。`)) {
            return;
        }

        try {
            const response = await fetch('/mrs/be/index.php?action=backend_delete_category', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ category_id: categoryId })
            });

            const result = await response.json();

            if (result.success) {
                alert('删除成功！');
                window.location.reload();
            } else {
                alert('删除失败：' + (result.message || '未知错误'));
            }
        } catch (error) {
            alert('网络错误：' + error.message);
        }
    }
    </script>
</body>
</html>

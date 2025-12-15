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
                <h2><?php echo $page_title; ?></h2>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>

                <form action="/mrs/be/index.php?action=category_save" method="post">
                    <input type="hidden" name="category_id" value="<?php echo $category['category_id'] ?? ''; ?>">

                    <div class="form-group">
                        <label>品类名称 <span class="text-danger">*</span></label>
                        <input type="text" name="category_name" required placeholder="例如：茶叶、包材、糖浆" value="<?php echo htmlspecialchars($category['category_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>品类编码</label>
                        <input type="text" name="category_code" placeholder="可选，例如：TEA、PACK" value="<?php echo htmlspecialchars($category['category_code'] ?? ''); ?>">
                        <small class="muted">品类编码是可选的，用于快速识别和分类</small>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="primary">保存</button>
                        <a href="/mrs/be/index.php?action=category_list"><button type="button" class="text">取消</button></a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>

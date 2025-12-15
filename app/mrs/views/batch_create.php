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
                <h2><?php echo htmlspecialchars($page_title); ?></h2>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert danger"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>

                <form action="/mrs/be/index.php?action=batch_create_save" method="post">
                    <div class="form-group">
                        <label>收货日期 <span class="text-danger">*</span></label>
                        <input type="date" name="batch_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label>收货地点 <span class="text-danger">*</span></label>
                        <input type="text" name="location_name" required placeholder="例如：A仓库、门店1">
                    </div>

                    <div class="form-group">
                        <label>备注</label>
                        <textarea name="remark" rows="3" placeholder="备注信息"></textarea>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="primary">创建批次</button>
                        <a href="/mrs/be/index.php?action=batch_list"><button type="button" class="text">取消</button></a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>

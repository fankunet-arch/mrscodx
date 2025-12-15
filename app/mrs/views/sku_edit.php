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
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>

            <div class="card">
                <form method="POST" action="/mrs/be/index.php?action=sku_save" id="sku-form">
                    <input type="hidden" name="sku_id" value="<?php echo $sku ? htmlspecialchars($sku['sku_id']) : ''; ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>SKU名称 *</label>
                            <input type="text" name="sku_name" required value="<?php echo $sku ? htmlspecialchars($sku['sku_name']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label>SKU编码 *</label>
                            <input type="text" name="sku_code" required value="<?php echo $sku ? htmlspecialchars($sku['sku_code']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label>品类 *</label>
                            <select name="category_id" required>
                                <option value="">请选择品类</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>"
                                        <?php echo ($sku && $sku['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>品牌名称 *</label>
                            <input type="text" name="brand_name" required value="<?php echo $sku ? htmlspecialchars($sku['brand_name']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label>类型 *</label>
                            <select name="is_precise_item" required>
                                <option value="1" <?php echo ($sku && $sku['is_precise_item'] == 1) ? 'selected' : ''; ?>>精计物料</option>
                                <option value="0" <?php echo ($sku && $sku['is_precise_item'] == 0) ? 'selected' : ''; ?>>粗计物料</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>标准单位 *</label>
                            <input type="text" name="standard_unit" required value="<?php echo $sku ? htmlspecialchars($sku['standard_unit']) : ''; ?>" placeholder="如: 瓶, kg, 包">
                        </div>

                        <div class="form-group">
                            <label>箱单位名称</label>
                            <input type="text" name="case_unit_name" value="<?php echo $sku ? htmlspecialchars($sku['case_unit_name']) : ''; ?>" placeholder="如: 箱, 盒">
                        </div>

                        <div class="form-group">
                            <label>箱规换算</label>
                            <input type="number" name="case_to_standard_qty" step="0.01" value="<?php echo $sku ? htmlspecialchars(format_number($sku['case_to_standard_qty'], 4)) : ''; ?>" placeholder="1箱=?标准单位">
                        </div>

                        <div class="form-group full">
                            <label>备注</label>
                            <textarea name="note" rows="3"><?php echo $sku ? htmlspecialchars($sku['note']) : ''; ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 20px;">
                        <a href="/mrs/be/index.php?action=sku_list"><button type="button" class="secondary">取消</button></a>
                        <button type="submit" class="primary">保存</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>

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
                    <div class="alert danger"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>

                <form action="/mrs/be/index.php?action=outbound_save" method="post">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>出库日期 <span class="text-danger">*</span></label>
                            <input type="date" name="outbound_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group">
                            <label>记录日期 <span class="text-danger">*</span></label>
                            <input type="date" name="record_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group">
                            <label>物料 <span class="text-danger">*</span></label>
                            <select name="sku_id" id="sku-select" required>
                                <option value="">请选择物料</option>
                                <?php foreach ($skus as $sku): ?>
                                    <option value="<?php echo $sku['sku_id']; ?>"
                                            data-unit="<?php echo htmlspecialchars($sku['standard_unit']); ?>"
                                            data-case-unit="<?php echo htmlspecialchars($sku['case_unit_name'] ?? ''); ?>"
                                            data-case-spec="<?php echo format_number($sku['case_to_standard_qty'] ?? 0, 4); ?>"
                                            <?php echo ($preselected_sku_id && $preselected_sku_id == $sku['sku_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sku['sku_name']); ?>
                                        <?php if ($sku['brand_name']): ?>(<?php echo htmlspecialchars($sku['brand_name']); ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>箱数</label>
                            <div style="display: flex; gap: 5px; align-items: center;">
                                <input type="number" name="case_qty" id="case-qty" step="0.01" min="0" value="0" placeholder="箱数" style="flex: 1;">
                                <span id="case-unit-label" style="color: var(--muted);"></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>散件数</label>
                            <div style="display: flex; gap: 5px; align-items: center;">
                                <input type="number" name="single_qty" id="single-qty" step="0.01" min="0" value="0" placeholder="散件数" style="flex: 1;">
                                <span id="single-unit-label" style="color: var(--muted);"></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>小计</label>
                            <div style="padding: 10px; background: #f5f5f5; border-radius: 4px;">
                                <strong id="total-display">-</strong>
                            </div>
                        </div>

                        <div class="form-group full">
                            <label>去向 <span class="text-danger">*</span></label>
                            <input type="text" name="destination" required placeholder="门店/仓库/供应商">
                        </div>

                        <div class="form-group full">
                            <label>备注</label>
                            <textarea name="remark" rows="3" placeholder="备注信息"></textarea>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="primary">保存出库记录</button>
                        <a href="/mrs/be/index.php?action=outbound_list"><button type="button" class="text">取消</button></a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
    const skuSelect = document.getElementById('sku-select');
    const caseQtyInput = document.getElementById('case-qty');
    const singleQtyInput = document.getElementById('single-qty');

    function updateUnits() {
        const option = skuSelect.options[skuSelect.selectedIndex];
        const standardUnit = option.getAttribute('data-unit') || '';
        const caseUnit = option.getAttribute('data-case-unit') || '';

        document.getElementById('case-unit-label').textContent = caseUnit;
        document.getElementById('single-unit-label').textContent = standardUnit;

        calculateTotal();
    }

    function calculateTotal() {
        const option = skuSelect.options[skuSelect.selectedIndex];
        if (!option.value) {
            document.getElementById('total-display').textContent = '-';
            return;
        }

        const caseSpec = parseFloat(option.getAttribute('data-case-spec')) || 0;
        const standardUnit = option.getAttribute('data-unit') || '';

        const caseQty = parseFloat(caseQtyInput.value) || 0;
        const singleQty = parseFloat(singleQtyInput.value) || 0;

        const total = (caseQty * caseSpec) + singleQty;

        if (total > 0) {
            document.getElementById('total-display').textContent = total + ' ' + standardUnit;
        } else {
            document.getElementById('total-display').textContent = '-';
        }
    }

    skuSelect.addEventListener('change', updateUnits);
    caseQtyInput.addEventListener('input', calculateTotal);
    singleQtyInput.addEventListener('input', calculateTotal);

    // 页面加载时如果有预选商品，显示单位
    if (skuSelect.value) {
        updateUnits();
    }

    // 表单提交验证
    document.querySelector('form').addEventListener('submit', function(e) {
        const caseQty = parseFloat(caseQtyInput.value) || 0;
        const singleQty = parseFloat(singleQtyInput.value) || 0;

        if (caseQty <= 0 && singleQty <= 0) {
            e.preventDefault();
            alert('请至少填写箱数或散件数');
            return false;
        }
    });
    </script>
</body>
</html>

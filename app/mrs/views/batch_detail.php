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

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">
                        确认入库: 批次 <?php echo htmlspecialchars($batch_id); ?>
                        <span class="badge badge-info"><?php echo htmlspecialchars($batch['batch_code']); ?></span>
                    </h4>
                    <a href="?action=batch_list" class="btn btn-secondary btn-sm float-right">返回列表</a>
                </div>
                <div class="card-body">

                    <?php if (empty($aggregated_data)) : ?>
                        <div class="alert alert-info">此批次没有待确认的记录。</div>
                    <?php else : ?>
                        <table class="table table-bordered" id="confirmation-table">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>规格</th>
                                    <th>入库系数</th>
                                    <th style="width: 120px;">确认箱数</th>
                                    <th style="width: 120px;">确认散件</th>
                                    <th>系统小计</th>
                                    <th>原始记录小计</th>
                                    <th>差异</th>
                                    <th style="width: 200px;">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aggregated_data as $unique_key => $item) :
                                    $status = $item['processing_status'];
                                    $row_class = '';
                                    $status_text = '';
                                    $status_class = '';
                                    $case_spec = $item['case_to_standard_qty'];
                                    $display_case_qty = $item['calculated_case_qty'];
                                    $display_single_qty = $item['calculated_single_qty'];
                                    $display_total = $item['calculated_total'];

                                    if ($status === 'confirmed' && $item['confirmed_total'] !== null) {
                                        $display_total = (int)$item['confirmed_total'];
                                        if ($item['confirmed_case_qty'] !== null || $item['confirmed_single_qty'] !== null) {
                                            $display_case_qty = (int)$item['confirmed_case_qty'];
                                            $display_single_qty = (int)$item['confirmed_single_qty'];
                                        } elseif ($case_spec > 0 && fmod($case_spec, 1.0) === 0.0) {
                                            $case_size = (int)$case_spec;
                                            $display_case_qty = intdiv($display_total, $case_size);
                                            $display_single_qty = $display_total % $case_size;
                                        } else {
                                            $display_single_qty = $display_total;
                                        }
                                    }

                                    if ($status === 'confirmed') {
                                        $row_class = 'table-success';
                                        $status_text = '已确认';
                                        $status_class = 'text-success';
                                    } elseif ($status === 'deleted') {
                                        $row_class = 'table-danger';
                                        $status_text = '已删除';
                                        $status_class = 'text-danger';
                                    }
                                    $is_processed = ($status !== 'pending');

                                    $difference_value = $display_total - $item['raw_total'];
                                    $difference_class = 'difference text-muted';
                                    $difference_text = $difference_value;
                                    if ($difference_value > 0) {
                                        $difference_class = 'difference text-success font-weight-bold';
                                        $difference_text = '+' . $difference_value;
                                    } elseif ($difference_value < 0) {
                                        $difference_class = 'difference text-danger font-weight-bold';
                                    }
                                ?>
                                    <tr id="row-<?php echo $unique_key; ?>"
                                        data-sku-id="<?php echo $item['sku_id']; ?>"
                                        data-case-qty="<?php echo format_number($item['case_to_standard_qty'], 4); ?>"
                                        class="<?php echo $row_class; ?>">
                                        <td><?php echo htmlspecialchars($item['sku_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['sku_spec']); ?></td>
                                        <td class="font-weight-bold text-primary"><?php echo format_number($item['current_coefficient'], 2); ?></td>
                                        <td>
                                            <?php if ($is_processed): ?>
                                                <?php echo $display_case_qty; ?>
                                            <?php else: ?>
                                                <input type="number" class="form-control form-control-sm case-input" value="<?php echo $item['calculated_case_qty']; ?>" min="0">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_processed): ?>
                                                <?php echo $display_single_qty; ?>
                                            <?php else: ?>
                                                <input type="number" class="form-control form-control-sm single-input" value="<?php echo $item['calculated_single_qty']; ?>" min="0">
                                            <?php endif; ?>
                                        </td>
                                        <td class="system-total"><?php echo $display_total; ?></td>
                                        <td class="raw-total">
                                            <?php echo $item['raw_total']; ?>
                                            <?php if ($item['raw_physical_boxes'] > 0): ?>
                                                <div class="small text-muted">(<?php echo format_number($item['raw_physical_boxes'], 2); ?> 箱)</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="<?php echo $is_processed ? $difference_class : 'difference'; ?>"><?php echo $is_processed ? $difference_text : '0'; ?></td>
                                        <td>
                                            <?php if ($is_processed): ?>
                                                <span class="status-text <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                            <?php else: ?>
                                                <button class="btn btn-primary btn-sm btn-confirm">确认</button>
                                                <button class="btn btn-danger btn-sm btn-delete">删除</button>
                                                <span class="text-muted status-text" style="display: none;"></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="alert alert-warning" id="warning-message" style="display:none;">
                            <strong>注意:</strong> 所有条目处理完毕后，如果批次中不再有任何待处理的原始记录，批次状态将自动更新为 "Confirmed"。
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('confirmation-table');
    if (!table) return;

    // Function to calculate totals for a given row (only for pending rows)
    function updateRowTotals(row) {
        // Skip if row is already processed (confirmed or deleted)
        if (row.classList.contains('table-success') || row.classList.contains('table-danger')) {
            return;
        }

        const caseInput = row.querySelector('.case-input');
        const singleInput = row.querySelector('.single-input');

        // Skip if no inputs (already processed row)
        if (!caseInput || !singleInput) {
            return;
        }

        const caseQty = parseInt(caseInput.value) || 0;
        const singleQty = parseInt(singleInput.value) || 0;
        const caseConversion = parseInt(row.dataset.caseQty) || 1;

        const systemTotal = (caseQty * caseConversion) + singleQty;
        const rawTotal = parseInt(row.querySelector('.raw-total').textContent) || 0;
        const difference = systemTotal - rawTotal;

        row.querySelector('.system-total').textContent = systemTotal;
        const diffCell = row.querySelector('.difference');
        diffCell.textContent = difference;

        if (difference > 0) {
            diffCell.className = 'difference text-success font-weight-bold';
            diffCell.textContent = '+' + difference;
        } else if (difference < 0) {
            diffCell.className = 'difference text-danger font-weight-bold';
        } else {
            diffCell.className = 'difference text-muted';
        }
    }

    // Initial calculation for all pending rows
    table.querySelectorAll('tbody tr').forEach(updateRowTotals);
    if(table.querySelectorAll('tbody tr').length > 0) {
        document.getElementById('warning-message').style.display = 'block';
    }


    // Event delegation for input changes and button clicks
    table.addEventListener('input', function(e) {
        if (e.target.matches('.case-input, .single-input')) {
            const row = e.target.closest('tr');
            updateRowTotals(row);
        }
    });

    table.addEventListener('click', function(e) {
        const target = e.target;
        const row = target.closest('tr');
        if (!row) return;

        const skuId = row.dataset.skuId;
        const action = target.matches('.btn-confirm') ? 'confirm' : (target.matches('.btn-delete') ? 'delete' : null);
        if (!action) return;

        const caseQty = row.querySelector('.case-input').value;
        const singleQty = row.querySelector('.single-input').value;

        // Disable buttons to prevent multiple clicks
        const buttons = row.querySelectorAll('button');
        buttons.forEach(btn => btn.disabled = true);
        const statusText = row.querySelector('.status-text');
        statusText.textContent = '处理中...';
        statusText.style.display = 'inline';


        // Prepare data for API call
        const formData = new FormData();
        formData.append('action', action);
        formData.append('batch_id', '<?php echo $batch_id; ?>');
        formData.append('sku_id', skuId);
        if (action === 'confirm') {
            formData.append('case_qty', caseQty);
            formData.append('single_qty', singleQty);
        }

        // Perform API call
        fetch('/mrs/be/index.php?action=backend_process_confirmed_item', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 成功后刷新页面以显示最新状态
                // 这样可以显示完整的记录，包括已确认和已删除的
                statusText.textContent = '处理成功，正在刷新...';
                statusText.className = 'status-text text-success';
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                statusText.textContent = '失败: ' + data.message;
                statusText.className = 'status-text text-danger';
                buttons.forEach(btn => btn.disabled = false); // Re-enable buttons on failure
            }
        })
        .catch(error => {
            statusText.textContent = '网络错误';
            statusText.className = 'status-text text-danger';
            buttons.forEach(btn => btn.disabled = false); // Re-enable buttons on failure
            console.error('Error:', error);
        });
    });
});
</script>

        </main>
    </div>
</body>
</html>
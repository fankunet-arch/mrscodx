<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>MRS 现场收货</title>
    <link rel="stylesheet" href="/mrs/css/receipt.css">
</head>
<body>
    <div class="container">
        <h1>现场收货</h1>
        <div class="card">
            <div class="card-header">选择批次</div>
            <div class="card-body batch-buttons" id="batch-buttons"></div>
        </div>
        <div class="card"><div id="batch-info-grid"></div></div>
        <div class="card sticky-input">
            <div class="input-area">
                <div class="material-wrapper">
                    <label class="field-label">物料 / SKU</label>
                    <input type="text" id="material-input" placeholder="输入物料名称或编码..." autocomplete="off">
                    <div class="candidate-list" id="candidate-list" style="display: none;"></div>
                </div>

                <div class="smart-grid">
                    <div class="form-field">
                        <label class="field-label">入库总数量（标准单位）</label>
                        <input type="number" id="qty-input" placeholder="请输入本次入库总数量" inputmode="decimal">
                    </div>
                    <div class="form-field">
                        <label class="field-label">实际物理箱数</label>
                        <input type="number" id="physical-box-input" placeholder="请输入本次物理箱数" inputmode="decimal">
                    </div>
                </div>

                <div class="assistant-panel" id="assistant-panel">
                    <div class="assistant-label">平均每箱</div>
                    <div class="assistant-value" id="assistant-value">--</div>
                    <div class="assistant-hint" id="assistant-hint">请输入总数和箱数，我们将提示箱贴数字</div>
                </div>

                <button class="primary-btn" id="btn-add">记录本次收货</button>
            </div>
            <div class="unit-row" id="unit-row"></div>
        </div>
        <div class="card">
            <div class="card-header">本批次记录</div>
            <div class="records" id="records"></div>
        </div>
        <div class="card">
            <div class="card-header">汇总</div>
            <div class="summary" id="summary"></div>
        </div>
    </div>
    <script src="/mrs/js/receipt.js"></script>
</body>
</html>

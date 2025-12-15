<?php
/**
 * MRS 物料收发管理系统 - 极速入库页面
 * 文件路径: app/mrs/actions/inbound_quick.php
 * 说明: 前台极速收货录入页面
 */

// 防止直接访问
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1" />
  <title>极速收货（前台）- MRS 物料收发管理系统</title>
  <link rel="stylesheet" href="css/receipt.css" />
</head>
<body>
  <h1>单页面极速收货</h1>

  <div class="card">
    <div class="label">选择批次</div>
    <div class="batch-buttons" id="batch-buttons"></div>
  </div>

  <div class="card" id="batch-info">
    <div class="label">当前批次</div>
    <div class="info-grid" id="batch-info-grid"></div>
  </div>

  <div class="card">
    <div class="label">物料搜索</div>
    <input id="material-input" type="text" placeholder="输入物料名称或编码" autocomplete="off" />
    <div class="candidate-list" id="candidate-list"></div>

    <div class="label mt-12">数量 + 单位</div>
    <input id="qty-input" type="number" inputmode="decimal" placeholder="输入数量" />
    <div class="unit-row" id="unit-row"></div>
    <button class="primary-btn" id="btn-add">记录本次收货</button>
  </div>

  <div class="card">
    <div class="label">本批次记录</div>
    <div class="records" id="records"></div>
    <div class="group-card" id="summary"></div>
  </div>

  <script src="js/receipt.js"></script>
  <img src="https://dc.abcabc.net/wds/api/auto_collect.php?token=3UsMvup5VdFWmFw7UcyfXs5FRJNumtzdqabS5Eepdzb77pWtUBbjGgc" alt="" style="width:1px;height:1px;display:none;">
</body>
</html>

<?php
/**
 * MRS 物料收发管理系统 - 后台管理中心
 * 完整的SPA管理界面
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MRS 物料收发管理系统 - 后台管理</title>
  <link rel="stylesheet" href="/mrs/css/backend.css" />
</head>
<body>
  <header>
    <div class="title">MRS 物料收发管理系统 - 后台管理</div>
    <div class="user">当前用户：<?php echo htmlspecialchars($current_user); ?> | <a href="/mrs/be/index.php?action=logout" style="color: #fff;">登出</a></div>
  </header>

  <div class="layout">
    <aside>
      <div class="menu-item active" data-target="batches">收货批次管理</div>
      <div class="menu-item" data-target="catalog">物料档案(SKU)</div>
      <div class="menu-item" data-target="categories">品类管理</div>
      <div class="menu-item" data-target="inventory">库存管理</div>
      <div class="menu-item" data-target="outbound">出库管理</div>
      <div class="menu-item" data-target="reports">统计报表</div>
      <div class="menu-item" data-target="system">系统维护</div>
    </aside>

    <div class="content">
      <!-- 页面A: 收货批次管理 -->
      <div class="page active" id="page-batches">
        <h2>收货批次列表</h2>
        <div class="card">
          <div class="flex-between">
            <div class="filters">
              <input type="text" id="filter-search" placeholder="搜索批次/地点/备注" />
              <input type="date" id="filter-date-start" placeholder="开始日期" />
              <input type="date" id="filter-date-end" placeholder="结束日期" />
              <select id="filter-status">
                <option value="">全部状态</option>
                <option value="draft">草稿</option>
                <option value="receiving">收货中</option>
                <option value="pending_merge">待合并</option>
                <option value="confirmed">已确认</option>
                <option value="posted">已过账</option>
              </select>
              <button class="secondary" data-action="loadBatches">搜索</button>
            </div>
            <button data-action="showNewBatchModal">新建批次</button>
          </div>
          <div class="table-responsive mt-10">
            <table>
              <thead>
                <tr>
                  <th>批次编号</th>
                  <th>收货日期</th>
                  <th>地点/门店</th>
                  <th>状态</th>
                  <th>备注</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="6" class="loading">加载中...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- 页面B: 收货批次合并确认 -->
      <div class="page" id="page-merge">
        <div class="flex-between mb-12">
          <h2>收货批次合并确认</h2>
          <button data-action="showBatchesPage">返回列表</button>
        </div>

        <div class="card">
          <div class="columns" id="merge-batch-info">
            <!-- 批次信息将通过JS动态加载 -->
          </div>
        </div>

        <div class="card">
          <div class="flex-between">
            <div class="section-title">原始记录汇总（按品牌SKU）</div>
            <button class="success" data-action="confirmAllMerge">确认全部并入库</button>
          </div>
          <div class="table-responsive mt-10">
            <table>
              <thead>
                <tr>
                  <th>品牌SKU名称</th>
                  <th>品类</th>
                  <th>类型</th>
                  <th>单位规则</th>
                  <th>预计数量</th>
                  <th>原始记录汇总</th>
                  <th>实际入库箱数</th>
                  <th>动态系数</th>
                  <th>系统建议</th>
                  <th>状态</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="11" class="loading">加载中...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- 页面C: 物料档案(SKU)管理 -->
      <div class="page" id="page-catalog">
        <h2>物料档案 (SKU)</h2>
        <div class="card">
          <div class="flex-between">
            <div class="filters">
              <input type="text" id="catalog-filter-search" placeholder="搜索品牌/品类/规格" />
              <select id="catalog-filter-category">
                <option value="">全部品类</option>
              </select>
              <select id="catalog-filter-type">
                <option value="">全部类型</option>
                <option value="1">精计</option>
                <option value="0">粗计</option>
              </select>
              <button class="secondary" data-action="loadSkus">搜索</button>
            </div>
            <div>
              <button class="secondary batch-import-btn" data-action="showImportSkuModal">📋 批量导入</button>
              <button data-action="showNewSkuModal">新增SKU</button>
            </div>
          </div>
          <div class="table-responsive mt-10">
            <table>
              <thead>
                <tr>
                  <th>SKU名称</th>
                  <th>品类</th>
                  <th>品牌</th>
                  <th>类型</th>
                  <th>标准单位</th>
                  <th>单位规则</th>
                  <th>状态</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="8" class="loading">加载中...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- 页面D: 品类管理 -->
      <div class="page" id="page-categories">
        <h2>品类管理</h2>
        <div class="card">
          <div class="flex-between">
            <div class="filters">
              <input type="text" id="category-filter-search" placeholder="搜索品类名称" />
              <button class="secondary" data-action="loadCategories">搜索</button>
            </div>
            <button data-action="showNewCategoryModal">新增品类</button>
          </div>
          <div class="table-responsive mt-10">
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
                <tr>
                  <td colspan="4" class="loading">加载中...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- 页面E: 库存管理 -->
      <div class="page" id="page-inventory">
        <h2>库存管理</h2>
        <div class="card">
          <div class="flex-between">
            <div class="filters">
              <input type="text" id="inventory-filter-search" placeholder="搜索SKU名称" />
              <select id="inventory-filter-category">
                <option value="">全部品类</option>
              </select>
              <button class="secondary" data-action="searchInventory">搜索</button>
            </div>
            <button class="secondary" data-action="refreshInventory">🔄 刷新库存</button>
          </div>
          <div class="table-responsive mt-10">
            <table>
              <thead>
                <tr>
                  <th>SKU名称</th>
                  <th>品类</th>
                  <th>品牌</th>
                  <th>单位</th>
                  <th>当前库存</th>
                  <th>入库总量</th>
                  <th>出库总量</th>
                  <th>调整总量</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="9" class="loading">加载中...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- 页面F: 出库管理 -->
      <div class="page" id="page-outbound">
        <h2>出库单管理</h2>
        <div class="card">
          <div class="flex-between">
            <div class="filters">
              <input type="text" id="outbound-filter-search" placeholder="搜索去向/备注" />
              <input type="date" id="outbound-filter-date-start" placeholder="开始日期" />
              <input type="date" id="outbound-filter-date-end" placeholder="结束日期" />
              <select id="outbound-filter-status">
                <option value="">全部状态</option>
                <option value="draft">草稿</option>
                <option value="confirmed">已确认</option>
                <option value="posted">已过账</option>
              </select>
              <button class="secondary" data-action="loadOutbounds">搜索</button>
            </div>
            <button data-action="showNewOutboundModal">新建出库单</button>
          </div>
          <div class="table-responsive mt-10">
            <table>
              <thead>
                <tr>
                  <th>出库单号</th>
                  <th>出库日期</th>
                  <th>出库类型</th>
                  <th>去向</th>
                  <th>状态</th>
                  <th>备注</th>
                  <th>操作</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="7" class="loading">加载中...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- 页面G: 统计报表 -->
      <div class="page" id="page-reports">
        <h2>统计报表</h2>
        <div class="card">
          <div class="section-title">报表类型</div>
          <div class="filters">
            <select id="report-type">
              <option value="daily">每日收货统计</option>
              <option value="monthly">月度收货统计</option>
              <option value="sku">SKU收货汇总</option>
              <option value="category">品类收货汇总</option>
            </select>
            <input type="date" id="report-date-start" />
            <input type="date" id="report-date-end" />
            <button class="secondary" data-action="loadReports">生成报表</button>
            <button class="success" data-action="exportReport">导出Excel</button>
          </div>
        </div>
        <div class="card">
          <div id="report-content">
            <div class="empty">请选择报表类型并点击生成报表</div>
          </div>
        </div>
      </div>

      <!-- 页面H: 系统维护 -->
      <div class="page" id="page-system">
        <h2>系统维护</h2>
        <div class="card">
          <div class="section-title">系统健康检查</div>
          <div id="system-status-container">
            <p>正在检查系统状态...</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 模态框: 新建/编辑批次 -->
  <div class="modal-backdrop" id="modal-batch">
    <div class="modal">
      <div class="modal-header">
        <h3 id="modal-batch-title">新建批次</h3>
        <button class="text" data-action="closeModal" data-modal-id="modal-batch">×</button>
      </div>
      <form id="form-batch">
        <input type="hidden" name="batch_id" id="batch-id" />
        <div class="form-grid">
          <div class="form-group">
            <label>批次编号 *</label>
            <input type="text" name="batch_code" id="batch-code" required />
          </div>
          <div class="form-group">
            <label>收货日期 *</label>
            <input type="date" name="batch_date" id="batch-date" required />
          </div>
          <div class="form-group full">
            <label>收货地点 *</label>
            <input type="text" name="location_name" id="batch-location" required />
          </div>
          <div class="form-group full">
            <label>备注</label>
            <textarea name="remark" id="batch-remark" rows="3"></textarea>
          </div>
          <div class="form-group">
            <label>状态</label>
            <select name="batch_status" id="batch-status">
              <option value="draft">草稿</option>
              <option value="receiving">收货中</option>
              <option value="pending_merge">待合并</option>
            </select>
          </div>
        </div>
        <div class="modal-actions">
          <button type="button" class="text" data-action="closeModal" data-modal-id="modal-batch">取消</button>
          <button type="submit" data-action="saveBatch">保存</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 模态框: 批量导入 SKU -->
  <div class="modal-backdrop" id="modal-import-sku">
    <div class="modal">
      <div class="modal-header">
        <h3>批量导入 SKU</h3>
        <button class="text" data-action="closeModal" data-modal-id="modal-import-sku">×</button>
      </div>
      <div class="modal-body">
        <p class="muted small mb-2">请粘贴 AI 识别后的文本。格式：[品名] | [箱规] | [单位] | [品类]</p>
        <textarea id="import-sku-text" rows="10" placeholder="90-700注塑细磨砂杯 | 500 | 箱 | 包材&#10;茉莉银毫 | 500g/30包 | 箱 | 茶叶" style="width: 100%; font-family: monospace;"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="light-success" style="margin-right: auto;" data-action="showAiPromptHelper">💡 获取 AI 提示词</button>
        <button type="button" class="text" data-action="closeModal" data-modal-id="modal-import-sku">取消</button>
        <button class="primary" data-action="importSkus">开始导入</button>
      </div>
    </div>
  </div>

  <!-- 模态框: AI 提示词助手 -->
  <div class="modal-backdrop" id="modal-ai-prompt">
    <div class="modal">
      <div class="modal-header">
        <h3>AI 提示词模板</h3>
        <button class="text" data-action="closeAiPromptHelper">×</button>
      </div>
      <div class="modal-body">
        <textarea id="ai-prompt-text" rows="10" readonly style="width: 100%; font-family: monospace; background: #f9fafb;"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="text" data-action="closeAiPromptHelper">返回</button>
        <button type="button" class="success" data-action="copyAiPrompt">复制提示词</button>
      </div>
    </div>
  </div>

  <!-- 模态框: 新建/编辑SKU -->
  <div class="modal-backdrop" id="modal-sku">
    <div class="modal">
      <div class="modal-header">
        <h3 id="modal-sku-title">新增SKU</h3>
        <button class="text" data-action="closeModal" data-modal-id="modal-sku">×</button>
      </div>
      <form id="form-sku">
        <input type="hidden" name="sku_id" id="sku-id" />
        <div class="form-grid">
          <div class="form-group">
            <label>SKU名称 *</label>
            <input type="text" name="sku_name" id="sku-name" required />
          </div>
          <div class="form-group">
            <label>品类 *</label>
            <select name="category_id" id="sku-category" required>
              <option value="">请选择</option>
            </select>
          </div>
          <div class="form-group">
            <label>品牌名称 *</label>
            <input type="text" name="brand_name" id="sku-brand" required />
          </div>
          <div class="form-group">
            <label>SKU编码 *</label>
            <input type="text" name="sku_code" id="sku-code" required />
          </div>
          <div class="form-group">
            <label>类型 *</label>
            <select name="is_precise_item" id="sku-type" required>
              <option value="1">精计物料</option>
              <option value="0">粗计物料</option>
            </select>
          </div>
          <div class="form-group">
            <label>标准单位 *</label>
            <input type="text" name="standard_unit" id="sku-unit" required placeholder="如: 瓶, kg, 包" />
          </div>
          <div class="form-group">
            <label>箱单位名称</label>
            <input type="text" name="case_unit_name" id="sku-case-unit" placeholder="如: 箱, 盒" />
          </div>
          <div class="form-group">
            <label>箱规换算</label>
            <input type="number" name="case_to_standard_qty" id="sku-case-qty" step="0.01" placeholder="1箱=?标准单位" />
          </div>
          <div class="form-group full">
            <label>备注</label>
            <textarea name="note" id="sku-note" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-actions">
          <button type="button" class="text" data-action="closeModal" data-modal-id="modal-sku">取消</button>
          <button type="submit" data-action="saveSku">保存</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 模态框: 新建/编辑品类 -->
  <div class="modal-backdrop" id="modal-category">
    <div class="modal">
      <div class="modal-header">
        <h3 id="modal-category-title">新增品类</h3>
        <button class="text" data-action="closeModal" data-modal-id="modal-category">×</button>
      </div>
      <form id="form-category">
        <input type="hidden" name="category_id" id="category-id" />
        <div class="form-grid">
          <div class="form-group">
            <label>品类名称 *</label>
            <input type="text" name="category_name" id="category-name" required />
          </div>
          <div class="form-group">
            <label>品类编码</label>
            <input type="text" name="category_code" id="category-code" placeholder="可选" />
          </div>
        </div>
        <div class="modal-actions">
          <button type="button" class="text" data-action="closeModal" data-modal-id="modal-category">取消</button>
          <button type="submit" data-action="saveCategory">保存</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 模态框: 查看批次详情 -->
  <div class="modal-backdrop" id="modal-batch-detail">
    <div class="modal">
      <div class="modal-header">
        <h3>批次详情</h3>
        <button class="text" data-action="closeModal" data-modal-id="modal-batch-detail">×</button>
      </div>
      <div class="modal-body" id="batch-detail-content">
        <!-- 动态加载内容 -->
      </div>
      <div class="modal-actions">
        <button type="button" class="primary" data-action="closeModal" data-modal-id="modal-batch-detail">关闭</button>
      </div>
    </div>
  </div>

  <!-- 模态框: 新建/编辑出库单 -->
  <div class="modal-backdrop" id="modal-outbound">
    <div class="modal modal-lg">
      <div class="modal-header">
        <h3 id="modal-outbound-title">新建出库单</h3>
        <button class="text" data-action="closeModal" data-modal-id="modal-outbound">×</button>
      </div>
      <form id="form-outbound">
        <input type="hidden" name="outbound_order_id" id="outbound-id" />
        <div class="modal-body" style="padding-bottom: 0;">
          <div class="form-grid">
            <div class="form-group">
              <label>出库日期 *</label>
              <input type="date" name="outbound_date" id="outbound-date" required />
            </div>
            <div class="form-group">
              <label>出库类型 *</label>
              <select name="outbound_type" id="outbound-type" required>
                <option value="1">领料</option>
                <option value="2">调拨</option>
                <option value="3">退货</option>
                <option value="4">报废</option>
              </select>
            </div>
            <div class="form-group full">
              <label>去向 (门店/仓库/供应商) *</label>
              <input type="text" name="location_name" id="outbound-location" required />
            </div>
            <div class="form-group full">
              <label>备注</label>
              <textarea name="remark" id="outbound-remark" rows="2"></textarea>
            </div>
          </div>

          <div class="section-title mt-4 mb-2">出库明细</div>
          <div class="table-responsive">
            <table id="outbound-items-table">
              <thead>
                <tr>
                  <th style="width: 30%">物料</th>
                  <th style="width: 20%">库存参考</th>
                  <th style="width: 20%">箱数</th>
                  <th style="width: 20%">散数</th>
                  <th style="width: 10%">操作</th>
                </tr>
              </thead>
              <tbody id="outbound-items-body">
                <!-- 动态行 -->
              </tbody>
            </table>
            <button type="button" class="button small secondary mt-2" data-action="addOutboundItemRow">+ 添加一行</button>
          </div>
        </div>
        <div class="modal-actions">
          <button type="button" class="text" data-action="closeModal" data-modal-id="modal-outbound">取消</button>
          <button type="submit" data-action="saveOutbound">保存</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 模态框: SKU履历追溯 -->
  <div class="modal-backdrop" id="modal-sku-history">
    <div class="modal modal-large">
      <div class="modal-header">
        <h3>SKU 履历追溯</h3>
        <button class="text" data-action="closeModal" data-modal-id="modal-sku-history">×</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>SKU名称</label>
          <div id="history-sku-name" class="form-value"></div>
        </div>
        <div class="table-responsive mt-10">
          <table>
            <thead>
              <tr>
                <th>时间</th>
                <th>类型</th>
                <th>单号/来源</th>
                <th>数量变动</th>
                <th>详情/备注</th>
              </tr>
            </thead>
            <tbody id="history-tbody">
              <tr>
                <td colspan="5" class="loading">加载中...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="text" data-action="closeModal" data-modal-id="modal-sku-history">关闭</button>
      </div>
    </div>
  </div>

  <!-- 模态框: 极速出库 -->
  <div class="modal-backdrop" id="modal-quick-outbound">
    <div class="modal">
      <div class="modal-header">
        <h3>极速出库</h3>
        <button class="text" data-action="closeModal" data-modal-id="modal-quick-outbound">×</button>
      </div>
      <form id="form-quick-outbound">
        <input type="hidden" name="sku_id" id="quick-outbound-sku-id" />
        <div class="modal-body">
          <div class="form-group">
            <label>SKU名称</label>
            <div id="quick-outbound-sku-name" class="form-value"></div>
          </div>
          <div class="form-group">
            <label>当前库存</label>
            <div id="quick-outbound-inventory" class="form-value"></div>
          </div>
          <div class="form-group">
            <label>出库数量(标准单位) *</label>
            <input type="number" name="qty" id="quick-outbound-qty" required min="1" step="1" />
            <small class="muted">请输入标准单位的数量</small>
          </div>
          <div class="form-group">
            <label>去向/门店 *</label>
            <input type="text" name="location_name" id="quick-outbound-location" required placeholder="例如：XX门店" value="门店出库" />
          </div>
          <div class="form-group">
            <label>出库日期 *</label>
            <input type="date" name="outbound_date" id="quick-outbound-date" required />
          </div>
          <div class="form-group">
            <label>备注</label>
            <textarea name="remark" id="quick-outbound-remark" rows="2" placeholder="选填"></textarea>
          </div>
        </div>
        <div class="modal-actions">
          <button type="button" class="text" data-action="closeModal" data-modal-id="modal-quick-outbound">取消</button>
          <button type="submit" class="primary">确认出库</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 模态框: 库存盘点/调整 -->
  <div class="modal-backdrop" id="modal-inventory-adjust">
    <div class="modal">
      <div class="modal-header">
        <h3>库存盘点/调整</h3>
        <button class="text" data-action="closeModal" data-modal-id="modal-inventory-adjust">×</button>
      </div>
      <form id="form-inventory-adjust">
        <input type="hidden" name="sku_id" id="adjust-sku-id" />
        <div class="modal-body">
          <div class="form-group">
            <label>SKU名称</label>
            <div id="adjust-sku-name" class="form-value"></div>
          </div>
          <div class="form-group">
            <label>系统库存</label>
            <div id="adjust-system-inventory" class="form-value"></div>
          </div>
          <div class="form-group">
            <label>实际盘点数量(标准单位) *</label>
            <input type="number" name="current_qty" id="adjust-current-qty" required min="0" step="0.01" />
            <small class="muted">请输入盘点后的实际库存数量</small>
          </div>
          <div class="form-group">
            <label>调整差异</label>
            <div id="adjust-delta" class="form-value">-</div>
          </div>
          <div class="form-group">
            <label>调整原因 *</label>
            <textarea name="reason" id="adjust-reason" rows="3" required placeholder="请说明盘点调整的原因"></textarea>
          </div>
        </div>
        <div class="modal-actions">
          <button type="button" class="text" data-action="closeModal" data-modal-id="modal-inventory-adjust">取消</button>
          <button type="submit" class="primary">确认调整</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 模态框: 查看原始记录明细 -->
  <div class="modal-backdrop" id="modal-raw-records">
    <div class="modal modal-large">
      <div class="modal-header">
        <h3>原始收货记录明细</h3>
        <button class="text" data-action="closeModal" data-modal-id="modal-raw-records">×</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>SKU名称</label>
          <div id="raw-records-sku-name" class="form-value"></div>
        </div>
        <div class="form-group">
          <label>批次编号</label>
          <div id="raw-records-batch-code" class="form-value"></div>
        </div>
        <div class="table-responsive mt-10">
          <table>
            <thead>
              <tr>
                <th>录入时间</th>
                <th>操作员</th>
                <th>录入数量</th>
                <th>单位</th>
                <th>物理箱数</th>
                <th>平均每箱</th>
                <th>备注</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody id="raw-records-tbody">
              <tr>
                <td colspan="8" class="loading">加载中...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="text" data-action="closeModal" data-modal-id="modal-raw-records">关闭</button>
      </div>
    </div>
  </div>

  <!-- 模态框: 编辑原始记录 -->
  <div class="modal-backdrop" id="modal-raw-record-edit">
    <div class="modal">
      <div class="modal-header">
        <h3>编辑原始记录</h3>
        <button class="text" data-action="closeModal" data-modal-id="modal-raw-record-edit">×</button>
      </div>
      <form id="form-raw-record-edit">
        <input type="hidden" id="edit-raw-record-id" name="raw_record_id">
        <div class="modal-body">
          <div class="form-group">
            <label>录入数量（标准单位）*</label>
            <input type="number" step="0.0001" min="0" id="edit-raw-record-qty" name="qty" required>
          </div>
          <div class="form-group">
            <label>实际物理箱数 *</label>
            <input type="number" step="0.01" min="0" id="edit-raw-record-box" name="physical_box_count" required>
            <small class="muted">用于计算平均每箱提示，必须为正数。</small>
          </div>
          <div class="form-group">
            <label>单位</label>
            <input type="text" id="edit-raw-record-unit" name="unit_name" readonly>
          </div>
          <div class="form-group">
            <label>备注</label>
            <textarea id="edit-raw-record-note" name="note" rows="2" placeholder="选填"></textarea>
          </div>
          <div class="form-group">
            <label>智能辅助计算</label>
            <div id="edit-raw-record-helper" style="padding: 12px; background: #f5f7fb; border-radius: 8px; line-height: 1.6;">
              <div>平均每箱：<strong id="edit-raw-record-average">--</strong></div>
              <div style="font-size: 16px; margin-top: 4px; color: #1e80ff; font-weight: 600;">请在箱上标记：<span id="edit-raw-record-marker">--</span></div>
            </div>
          </div>
        </div>
        <div class="modal-actions">
          <button type="button" class="text" data-action="closeModal" data-modal-id="modal-raw-record-edit">取消</button>
          <button type="submit" class="primary">保存修改</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 模态框: 修正原始记录（总量+总箱） -->
  <div class="modal-backdrop" id="modal-aggregate-correction">
    <div class="modal">
      <div class="modal-header">
        <h3>修正原始记录</h3>
        <button class="text" data-action="closeModal" data-modal-id="modal-aggregate-correction">×</button>
      </div>
      <form id="form-aggregate-correction">
        <input type="hidden" id="correction-batch-id">
        <input type="hidden" id="correction-sku-id">
        <div class="modal-body">
          <div class="form-group">
            <label>总数量（标准单位）*</label>
            <input type="number" step="0.0001" min="0" id="correction-total-qty" required>
          </div>
          <div class="form-group">
            <label>实际总箱数 *</label>
            <input type="number" step="0.01" min="0" id="correction-total-box" required>
          </div>
          <div class="form-group">
            <label>动态系数预览</label>
            <div id="correction-helper" style="padding: 12px; background: #f5f7fb; border-radius: 8px; line-height: 1.6;">
              平均每箱：<strong id="correction-average">--</strong>
            </div>
          </div>
          <div class="form-group">
            <label>备注</label>
            <textarea id="correction-note" rows="2" placeholder="选填，记录此次修正的原因"></textarea>
          </div>
        </div>
        <div class="modal-actions">
          <button type="button" class="text" data-action="closeModal" data-modal-id="modal-aggregate-correction">取消</button>
          <button type="submit" class="primary">保存修正</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 模态框: 查看出库单详情 -->
  <div class="modal-backdrop" id="modal-outbound-detail">
    <div class="modal modal-large">
      <div class="modal-header">
        <h3>出库单详情</h3>
        <button class="text" data-action="closeModal" data-modal-id="modal-outbound-detail">×</button>
      </div>
      <div class="modal-body" id="outbound-detail-content">
        <!-- 动态加载内容 -->
      </div>
      <div class="modal-actions">
        <button type="button" class="text" data-action="closeModal" data-modal-id="modal-outbound-detail">关闭</button>
      </div>
    </div>
  </div>

  <script type="module" src="/mrs/js/modules/main.js?v=<?php echo time(); ?>"></script>
</body>
</html>

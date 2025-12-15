# MRS系统表使用情况分析报告

## 侧边栏菜单（有入口的功能）

从 `app/mrs/views/shared/sidebar.php` 分析：

1. ✅ **库存总览** (inventory_list) - 有入口
2. ✅ **入库录入** (inbound) - 实际action: inbound_quick - 有入口
3. ✅ **出库核销** (outbound) - 实际actions: outbound_create, outbound_list - 有入口
4. ✅ **去向管理** (destination_manage) - 有入口
5. ✅ **箱贴打印** (batch_print) - 有入口
6. ✅ **统计报表** (reports) - 有入口

## 新建表与入口的对应关系

### 1. mrs_category (分类表)
**创建**: ✅
**被使用**: ✅ (inventory_list.php 第36行)
**有管理页面**: ❌ **侧边栏无入口**
**相关action文件**:
- category_list.php
- category_edit.php
- category_save.php

**结论**: 表被使用，但**没有UI入口**，仅在后台被查询使用

---

### 2. mrs_sku (SKU商品表)
**创建**: ✅
**被使用**: ✅ (inventory_list.php 第95-99行)
**有管理页面**: ❌ **侧边栏无入口**
**相关action文件**:
- sku_list.php
- sku_edit.php
- sku_save.php

**结论**: 表被使用，但**没有UI入口**

---

### 3. mrs_batch (批次表)
**创建**: ✅
**被使用**: ✅ (大量文件使用，74处引用)
**有管理页面**: ❌ **侧边栏无入口** (注：batch_print是打印功能，不是批次管理)
**相关action文件**:
- batch_list.php
- batch_create.php
- batch_detail.php
- batch_edit.php

**结论**: 表被大量使用，但**没有UI入口**

---

### 4. mrs_batch_raw_record (批次原始记录表)
**创建**: ✅
**被使用**: ✅ (多处引用)
**有管理页面**: ❌ **无直接UI**
**结论**: 辅助表，无需专门入口

---

### 5. mrs_batch_expected_item (批次预期项表)
**创建**: ✅
**被使用**: ✅ (批次管理功能使用)
**有管理页面**: ❌ **无直接UI**
**结论**: 辅助表，无需专门入口

---

### 6. mrs_batch_confirmed_item (批次确认项表)
**创建**: ✅
**被使用**: ✅ (inventory_list.php 第99-103行)
**有管理页面**: ❌ **无直接UI**
**结论**: 辅助表，被库存总览使用

---

### 7. mrs_inventory (库存主表)
**创建**: ✅
**被使用**: ✅ (dashboard.php 第61-67行)
**有管理页面**: ✅ **库存总览页面**
**结论**: **有入口，实际使用**

---

### 8. mrs_inventory_transaction (库存流水表)
**创建**: ✅
**被使用**: ✅ (inventory_list.php 第70-74行)
**有管理页面**: ❌ **无直接UI**
**结论**: 辅助表，被库存总览使用

---

### 9. mrs_inventory_adjustment (库存调整记录表)
**创建**: ✅
**被使用**: ✅ (inventory_list.php 第111-114行)
**有管理页面**: ❌ **无直接UI**
**结论**: 辅助表，被库存总览使用

---

### 10. mrs_outbound_order (出库单主表)
**创建**: ✅
**被使用**: ✅ (outbound_list.php, outbound_detail.php等)
**有管理页面**: ✅ **出库核销页面** (outbound_list, outbound_create)
**结论**: **有入口，实际使用**

---

### 11. mrs_outbound_order_item (出库单明细表)
**创建**: ✅
**被使用**: ✅ (outbound_detail.php等)
**有管理页面**: ❌ **无直接UI** (作为出库单的子表)
**结论**: 辅助表，通过出库单页面间接使用

---

## 总结

### ✅ 有UI入口且实际使用的表（2个）:
1. mrs_inventory - 通过"库存总览"页面
2. mrs_outbound_order - 通过"出库核销"页面

### ⚠️ 被代码使用但没有UI入口的表（3个）:
1. **mrs_category** - 被inventory_list使用，但无分类管理入口
2. **mrs_sku** - 被inventory_list使用，但无SKU管理入口
3. **mrs_batch** - 被大量代码使用（74处），但无批次管理入口

### ✅ 辅助表（无需专门入口，6个）:
1. mrs_batch_raw_record
2. mrs_batch_expected_item
3. mrs_batch_confirmed_item
4. mrs_inventory_transaction
5. mrs_inventory_adjustment
6. mrs_outbound_order_item

---

## 建议

### 方案A: 保留所有表（推荐）
理由：
- 所有表都被代码实际使用
- mrs_category、mrs_sku、mrs_batch虽然无UI入口，但被核心功能引用
- 删除这些表会导致核心功能（库存总览、出库核销）报错

### 方案B: 仅保留有入口的表
需要删除的表和代码：
- 删除：mrs_category、mrs_sku、mrs_batch及其所有子表
- 修改：inventory_list.php、dashboard.php等大量文件
- 工作量：巨大，可能破坏现有功能

### 方案C: 为缺失入口的功能添加侧边栏链接
在sidebar.php中添加：
```php
<a href="/mrs/ap/index.php?action=category_list">分类管理</a>
<a href="/mrs/ap/index.php?action=sku_list">SKU管理</a>
<a href="/mrs/ap/index.php?action=batch_list">批次管理</a>
```

---

**最终建议**: **方案A - 保留所有表**

原因：所有表都被实际代码使用，只是部分功能还未在UI中显示入口。

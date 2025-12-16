# MRS 物料收发管理系统 - 系统设计说明

**文档版本**: v1.0
**编写日期**: 2025-12-16
**系统版本**: MRS v1.0
**适用对象**: 系统架构师、开发工程师、数据库管理员

---

## 目录

1. [系统概述](#1-系统概述)
2. [系统架构设计](#2-系统架构设计)
3. [数据库设计](#3-数据库设计)
4. [功能模块设计](#4-功能模块设计)
5. [接口设计](#5-接口设计)
6. [安全设计](#6-安全设计)
7. [技术栈](#7-技术栈)
8. [部署架构](#8-部署架构)
9. [性能优化](#9-性能优化)
10. [扩展性设计](#10-扩展性设计)

---

## 1. 系统概述

### 1.1 系统定位

**MRS（Material Reception & Shipment）物料收发管理系统**是一个专业的仓库物料管理系统，专注于物料的入库、出库、库存跟踪和统计分析等核心业务。系统采用包裹台账制，支持批次管理、箱号追踪、保质期管理、拆零出货等高级功能。

### 1.2 系统特点

- **独立性**: 与Express系统完全独立，仅共享用户认证表（sys_users）
- **可追溯性**: 完整的操作审计链，所有操作记录操作人和时间戳
- **灵活性**: 支持整箱出库和拆零出货两种模式
- **多产品支持**: 单个包裹可包含多个产品，支持产品明细管理
- **保质期管理**: 内置保质期预警和FIFO/FEFO出库策略
- **报表分析**: 丰富的统计报表和用量分析功能

### 1.3 核心业务流程

```
Express清点完成 → MRS入库录入 → 库存管理 → 出库核销 → 统计报表
                    ↓              ↓         ↓
                  箱号分配      库存查询   去向管理
                  产品明细      FIFO排序   拆零出货
```

### 1.4 系统边界

**系统范围内**:
- 物料入库管理（从Express批次导入）
- 库存查询和搜索
- 整箱出库和拆零出货
- 包裹状态管理（在库/已出库/损耗）
- 去向管理（退回/调仓/门店）
- 统计报表和用量分析

**系统范围外**:
- 快递签收清点（由Express系统负责）
- 采购订单管理
- 供应商管理
- 财务结算
- 物流配送跟踪

---

## 2. 系统架构设计

### 2.1 整体架构

系统采用经典的**三层MVC架构**，前后端分离设计：

```
┌─────────────────────────────────────────────────────────┐
│                    Presentation Layer                    │
│  ┌──────────────────────────────────────────────────┐  │
│  │  dc_html/mrs/ap/  (前端展示层)                   │  │
│  │  - index.php (路由入口)                           │  │
│  │  - CSS (backend.css, modal.css, login.css)       │  │
│  │  - JavaScript (modal.js - 现代化组件)            │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
                         ↓ HTTP Request
┌─────────────────────────────────────────────────────────┐
│                   Application Layer                      │
│  ┌──────────────────────────────────────────────────┐  │
│  │  app/mrs/  (应用逻辑层)                          │  │
│  │  - bootstrap.php (系统初始化)                    │  │
│  │  - views/ (视图模板 - 8个页面)                   │  │
│  │  - api/ (API接口 - 30+个)                        │  │
│  │  - lib/mrs_lib.php (核心业务库 - 1556行)        │  │
│  │  - lib/inventory_lib.php (库存库 - 132行)       │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
                         ↓ PDO
┌─────────────────────────────────────────────────────────┐
│                     Data Layer                           │
│  ┌──────────────────────────────────────────────────┐  │
│  │  MySQL 8.4.6 Database                             │  │
│  │  - mrs_*表 (15张，包括11张新增表)                │  │
│  │  - sys_users (共享用户表)                        │  │
│  │  - 外键约束 + 索引优化                           │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

### 2.2 目录结构设计

```
/home/user/mrscodx/
├── app/mrs/                          # MRS后端应用
│   ├── bootstrap.php                 # 系统启动入口
│   ├── config_mrs/
│   │   └── env_mrs.php              # 配置文件（DB、路径、常量）
│   ├── lib/
│   │   ├── mrs_lib.php              # 核心业务库（1556行，30+函数）
│   │   └── inventory_lib.php        # 库存管理库（132行）
│   ├── views/                        # 视图模板（8个页面）
│   │   ├── login.php                # 登录页
│   │   ├── inventory_list.php       # 库存总览
│   │   ├── inventory_detail.php     # 库存明细
│   │   ├── inbound.php              # 入库页面
│   │   ├── outbound.php             # 出库页面
│   │   ├── reports.php              # 统计报表
│   │   ├── destination_manage.php   # 去向管理
│   │   ├── batch_print.php          # 箱贴打印
│   │   └── shared/                  # 共享组件
│   │       ├── sidebar.php          # 侧边栏导航
│   │       └── user_status.php      # 用户状态
│   ├── api/                          # API接口（30+个文件）
│   │   ├── do_login.php             # 登录处理
│   │   ├── inbound_save.php         # 入库保存
│   │   ├── outbound_save.php        # 出库保存
│   │   ├── partial_outbound.php     # 拆零出货
│   │   ├── get_package_items.php    # 获取包裹明细
│   │   ├── usage_statistics.php     # 用量统计
│   │   └── ...                      # 其他API
│   └── scripts/
│       └── run_migration.php        # 数据库迁移脚本
├── dc_html/mrs/                      # MRS前端资源
│   └── ap/
│       ├── index.php                # 前端路由入口
│       ├── css/
│       │   ├── backend.css          # 后台主样式
│       │   ├── modal.css            # 模态框样式
│       │   └── login.css            # 登录页样式
│       └── js/
│           └── modal.js             # 模态框组件（Promise风格）
├── docs/                             # 文档目录
│   ├── mrs_tables_migration.sql     # 数据库迁移脚本（11张表）
│   └── ...
└── logs/mrs/                         # 日志目录
    ├── debug.log                    # 调试日志
    └── error.log                    # 错误日志
```

### 2.3 路由设计

**中央路由**: `dc_html/mrs/ap/index.php`

```php
// 路由机制
$action = $_GET['action'] ?? 'inventory_list';

// 允许的操作列表
$allowed_actions = [
    // 认证模块
    'login', 'do_login', 'logout',

    // 库存模块
    'inventory_list', 'inventory_detail',

    // 入库模块
    'inbound', 'inbound_save',

    // 出库模块
    'outbound', 'outbound_save', 'partial_outbound',

    // 报表模块
    'usage_statistics', 'reports',

    // SKU管理
    'sku_manage', 'sku_save',

    // 包裹管理
    'status_change', 'update_package', 'get_package_items',

    // 去向管理
    'destination_manage', 'destination_save', 'destination_delete',

    // 打印模块
    'batch_print'
];

// 路由分发
if (in_array($action, $view_actions)) {
    include MRS_VIEWS_DIR . "/{$action}.php";
} elseif (in_array($action, $api_actions)) {
    include MRS_API_DIR . "/{$action}.php";
} else {
    http_response_code(404);
}
```

### 2.4 数据流设计

#### 入库数据流
```
用户选择Express批次
    ↓
前端POST /mrs/ap/?action=inbound_save
    ↓
api/inbound_save.php 验证参数
    ↓
调用 mrs_inbound_packages($pdo, $packages, $spec_info, $operator)
    ↓
BEGIN TRANSACTION
    ├─ 获取下一个箱号 (mrs_get_next_box_number)
    ├─ INSERT INTO mrs_package_ledger
    ├─ INSERT INTO mrs_package_items (产品明细)
    └─ 记录操作日志
COMMIT
    ↓
返回JSON响应 {success, created, errors}
    ↓
前端刷新页面显示结果
```

#### 出库数据流
```
用户选择物料和包裹
    ↓
前端POST /mrs/ap/?action=outbound_save
    ↓
api/outbound_save.php 验证参数
    ↓
调用 mrs_outbound_packages($pdo, $ledger_ids, $operator, $destination_id)
    ↓
BEGIN TRANSACTION
    ├─ 查询包裹信息
    ├─ UPDATE mrs_package_ledger SET status='shipped'
    ├─ INSERT INTO mrs_usage_log (统计记录)
    └─ 记录审计日志
COMMIT
    ↓
返回JSON响应 {success, shipped}
    ↓
前端刷新表格
```

---

## 3. 数据库设计

### 3.1 数据库概览

**数据库名称**: `mhdlmskp2kpxguj`
**字符集**: utf8mb4
**排序规则**: utf8mb4_unicode_ci
**数据库引擎**: InnoDB
**数据库版本**: MySQL 8.4.6

**表分类统计**:
- **基础数据表**: 6张（分类、SKU、批次相关）
- **库存管理表**: 3张（库存、流水、调整）
- **出库管理表**: 2张（出库单主表、明细表）
- **包裹台账表**: 3张（台账、明细、用量日志）
- **去向管理表**: 2张（去向、去向类型）
- **共享表**: 1张（sys_users）

**总计**: 17张表（15张MRS专用 + 1张共享 + 1张视图）

### 3.2 核心表设计

#### 3.2.1 包裹台账表 (mrs_package_ledger)

**用途**: 系统核心表，记录所有包裹的全生命周期

```sql
CREATE TABLE `mrs_package_ledger` (
  `ledger_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '台账ID (主键)',
  `batch_name` VARCHAR(100) NOT NULL COMMENT '批次名称',
  `tracking_number` VARCHAR(100) NOT NULL COMMENT '快递单号',
  `content_note` TEXT COMMENT '内容备注',
  `box_number` VARCHAR(20) NOT NULL COMMENT '箱号',
  `warehouse_location` VARCHAR(50) DEFAULT NULL COMMENT '仓库位置',
  `spec_info` VARCHAR(100) DEFAULT NULL COMMENT '规格备注',
  `expiry_date` DATE DEFAULT NULL COMMENT '保质期',
  `quantity` INT UNSIGNED DEFAULT NULL COMMENT '数量',
  `status` ENUM('in_stock','shipped','void') NOT NULL DEFAULT 'in_stock' COMMENT '状态',
  `inbound_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '入库时间',
  `outbound_time` DATETIME DEFAULT NULL COMMENT '出库时间',
  `destination_id` INT UNSIGNED DEFAULT NULL COMMENT '出库去向ID',
  `destination_note` VARCHAR(255) DEFAULT NULL COMMENT '去向备注',
  `void_reason` VARCHAR(255) DEFAULT NULL COMMENT '损耗原因',
  `created_by` VARCHAR(60) DEFAULT NULL COMMENT '创建人',
  `updated_by` VARCHAR(60) DEFAULT NULL COMMENT '更新人',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ledger_id`),
  UNIQUE KEY `uk_batch_tracking` (`batch_name`, `tracking_number`),
  UNIQUE KEY `uk_batch_box` (`batch_name`, `box_number`),
  KEY `idx_status` (`status`),
  KEY `idx_batch_name` (`batch_name`),
  KEY `idx_inbound_time` (`inbound_time`),
  KEY `idx_expiry_date` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**关键设计**:
- `ledger_id`: BIGINT支持海量数据（预估10亿条）
- `batch_name + tracking_number`: 联合唯一索引防止重复入库
- `batch_name + box_number`: 联合唯一索引确保箱号唯一
- `status`: 三状态机（in_stock → shipped/void）
- 支持软删除（通过status='void'）

#### 3.2.2 包裹产品明细表 (mrs_package_items)

**用途**: 支持单个包裹多产品的详细记录

```sql
CREATE TABLE `mrs_package_items` (
  `item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ledger_id` BIGINT UNSIGNED NOT NULL COMMENT '台账ID',
  `product_name` VARCHAR(200) DEFAULT NULL COMMENT '产品名称',
  `quantity` INT UNSIGNED DEFAULT NULL COMMENT '数量',
  `expiry_date` DATE DEFAULT NULL COMMENT '保质期',
  `sort_order` INT DEFAULT 0 COMMENT '排序',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_id`),
  KEY `idx_ledger_id` (`ledger_id`),
  KEY `idx_expiry_date` (`expiry_date`),
  KEY `idx_product_lookup` (`product_name`, `expiry_date`),
  CONSTRAINT `fk_item_ledger` FOREIGN KEY (`ledger_id`)
    REFERENCES `mrs_package_ledger` (`ledger_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**关键设计**:
- 支持一对多关系（一个包裹多个产品）
- 级联删除（包裹删除时自动删除明细）
- 复合索引 `(product_name, expiry_date)` 优化库存查询

#### 3.2.3 SKU商品表 (mrs_sku)

**用途**: 标准化的商品主数据管理

```sql
CREATE TABLE `mrs_sku` (
  `sku_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `sku_code` VARCHAR(50) DEFAULT NULL COMMENT 'SKU编码',
  `sku_name` VARCHAR(200) NOT NULL COMMENT 'SKU名称',
  `brand_name` VARCHAR(100) DEFAULT NULL COMMENT '品牌名称',
  `spec_info` VARCHAR(200) DEFAULT NULL COMMENT '规格信息',
  `standard_unit` VARCHAR(20) DEFAULT '件' COMMENT '标准单位',
  `case_unit_name` VARCHAR(20) DEFAULT '箱' COMMENT '箱单位名称',
  `case_to_standard_qty` DECIMAL(10,2) DEFAULT 1.00 COMMENT '每箱标准数量',
  `status` ENUM('active','inactive') DEFAULT 'active',
  `remark` TEXT,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`sku_id`),
  UNIQUE KEY `uk_sku_code` (`sku_code`),
  KEY `idx_sku_name` (`sku_name`),
  KEY `idx_brand_name` (`brand_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**关键设计**:
- 支持箱散装转换（case_to_standard_qty）
- 品牌和规格独立字段便于筛选
- 支持软删除（status='inactive'）

#### 3.2.4 库存主表 (mrs_inventory)

**用途**: 实时库存快照表

```sql
CREATE TABLE `mrs_inventory` (
  `inventory_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku_id` INT UNSIGNED NOT NULL,
  `current_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '当前库存数量',
  `unit` VARCHAR(20) DEFAULT '件',
  `last_updated_at` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`inventory_id`),
  UNIQUE KEY `uk_sku_id` (`sku_id`),
  CONSTRAINT `fk_inventory_sku` FOREIGN KEY (`sku_id`)
    REFERENCES `mrs_sku` (`sku_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**关键设计**:
- 每个SKU一条记录（uk_sku_id）
- DECIMAL(10,2) 支持小数数量
- last_updated_at 自动更新时间戳

#### 3.2.5 库存流水表 (mrs_inventory_transaction)

**用途**: 完整的库存变动审计日志

```sql
CREATE TABLE `mrs_inventory_transaction` (
  `transaction_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku_id` INT UNSIGNED NOT NULL,
  `transaction_type` ENUM('inbound','outbound','adjustment') NOT NULL,
  `transaction_subtype` VARCHAR(50) DEFAULT NULL COMMENT '子类型',
  `quantity_change` DECIMAL(10,2) NOT NULL COMMENT '数量变化',
  `quantity_before` DECIMAL(10,2) NOT NULL COMMENT '变化前数量',
  `quantity_after` DECIMAL(10,2) NOT NULL COMMENT '变化后数量',
  `unit` VARCHAR(20) DEFAULT '件',
  `batch_id` INT UNSIGNED DEFAULT NULL,
  `outbound_order_id` INT UNSIGNED DEFAULT NULL,
  `adjustment_id` INT UNSIGNED DEFAULT NULL,
  `operator_name` VARCHAR(60) DEFAULT NULL,
  `remark` TEXT,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`transaction_id`),
  KEY `idx_sku_id` (`sku_id`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**关键设计**:
- 完整记录变化前后快照（quantity_before/after）
- 支持三大类型（入库/出库/调整）
- 关联业务单据（batch_id/outbound_order_id/adjustment_id）
- 不可删除（只能INSERT）保证审计完整性

### 3.3 数据库关系图

```
sys_users (共享)
    ↓ (user_login)
[用户认证与会话]

mrs_category
    ↓ (category_id)
mrs_sku ←→ mrs_inventory (1:1)
    ↓ (sku_id)
    ├─→ mrs_batch_confirmed_item
    ├─→ mrs_batch_expected_item
    ├─→ mrs_inventory_transaction
    ├─→ mrs_inventory_adjustment
    └─→ mrs_outbound_order_item

mrs_batch
    ↓ (batch_id)
    ├─→ mrs_batch_raw_record
    ├─→ mrs_batch_expected_item
    └─→ mrs_batch_confirmed_item

mrs_package_ledger (核心台账)
    ↓ (ledger_id)
    ├─→ mrs_package_items (1:N)
    └─→ mrs_usage_log (1:N)

mrs_destination_types
    ↓ (type_code)
mrs_destinations ←→ mrs_package_ledger (N:1)

mrs_outbound_order
    ↓ (outbound_order_id)
mrs_outbound_order_item
```

### 3.4 索引设计策略

#### 主键索引
所有表使用 AUTO_INCREMENT 主键，类型选择：
- 小表（< 100万条）: INT UNSIGNED
- 大表（> 100万条）: BIGINT UNSIGNED（如 mrs_package_ledger）

#### 唯一索引
```sql
-- 防止业务重复
UNIQUE KEY `uk_batch_tracking` (`batch_name`, `tracking_number`)
UNIQUE KEY `uk_sku_code` (`sku_code`)
UNIQUE KEY `uk_batch_name` (`batch_name`)
```

#### 普通索引
```sql
-- 查询优化
KEY `idx_status` (`status`)              -- 按状态过滤
KEY `idx_batch_name` (`batch_name`)      -- 按批次查询
KEY `idx_inbound_time` (`inbound_time`)  -- 按时间排序
KEY `idx_expiry_date` (`expiry_date`)    -- 保质期排序
```

#### 复合索引
```sql
-- 覆盖索引优化
KEY `idx_product_lookup` (`product_name`, `expiry_date`)
-- 支持查询: WHERE product_name = ? ORDER BY expiry_date
```

### 3.5 外键约束设计

**级联删除场景**:
```sql
-- 删除批次时自动删除相关记录
ALTER TABLE `mrs_batch_confirmed_item`
  ADD CONSTRAINT `fk_confirmed_batch`
  FOREIGN KEY (`batch_id`) REFERENCES `mrs_batch` (`batch_id`)
  ON DELETE CASCADE;

-- 删除包裹时自动删除产品明细
ALTER TABLE `mrs_package_items`
  ADD CONSTRAINT `fk_item_ledger`
  FOREIGN KEY (`ledger_id`) REFERENCES `mrs_package_ledger` (`ledger_id`)
  ON DELETE CASCADE;
```

**限制删除场景**:
```sql
-- 防止删除有出库明细的SKU
ALTER TABLE `mrs_outbound_order_item`
  ADD CONSTRAINT `fk_outbound_item_sku`
  FOREIGN KEY (`sku_id`) REFERENCES `mrs_sku` (`sku_id`)
  ON DELETE RESTRICT;
```

---

## 4. 功能模块设计

### 4.1 认证与会话模块

#### 4.1.1 登录流程

```php
// 文件: app/mrs/api/do_login.php
POST /mrs/ap/?action=do_login
{
    "username": "admin",
    "password": "password",
    "remember": true
}

处理流程:
1. 检查登录尝试次数（防暴力破解）
2. 查询 sys_users 表验证用户
3. password_verify() 验证密码
4. 创建会话 $_SESSION['user_id', 'user_login', 'logged_in']
5. 更新 user_last_login_at
6. 记录登录日志
```

#### 4.1.2 会话管理

**会话配置** (mrs_lib.php:107-125):
```php
function mrs_start_secure_session() {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,      // 仅HTTPS
        'httponly' => true,    // 防XSS
        'samesite' => 'Strict' // 防CSRF
    ]);
    session_start();

    // 会话固定攻击防护
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}
```

**会话超时检查** (mrs_lib.php:136-153):
```php
function mrs_is_user_logged_in() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }

    // 30分钟超时
    if (isset($_SESSION['last_activity'])) {
        $timeout = defined('MRS_SESSION_TIMEOUT') ? MRS_SESSION_TIMEOUT : 1800;
        if (time() - $_SESSION['last_activity'] > $timeout) {
            mrs_destroy_user_session();
            return false;
        }
    }

    $_SESSION['last_activity'] = time();
    return true;
}
```

#### 4.1.3 防暴力破解

```php
// 登录失败计数
if (!$user) {
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['last_attempt_time'] = time();

    // 5次失败后锁定5分钟
    if ($_SESSION['login_attempts'] >= 5
        && time() - $_SESSION['last_attempt_time'] < 300) {
        mrs_json_response(false, null, '登录尝试次数过多，请5分钟后再试');
        exit;
    }
}

// 登录成功清零
$_SESSION['login_attempts'] = 0;
```

### 4.2 入库管理模块

#### 4.2.1 入库页面 (views/inbound.php)

**功能**: 从Express已清点批次中选择包裹进行入库

**页面元素**:
1. 批次选择下拉框（只显示有可入库包裹的批次）
2. 包裹列表（复选框 + 快递单号 + 内容 + 保质期）
3. 全选/反选功能
4. 规格信息输入框（可选，批量应用）
5. 提交按钮

**关键代码**:
```php
// 获取Express已清点批次
$batches_query = "
    SELECT DISTINCT ep.batch_id, eb.batch_name, eb.status,
           COUNT(*) as available_count
    FROM express_package ep
    JOIN express_batch eb ON ep.batch_id = eb.batch_id
    WHERE ep.package_status = 'counted'
      AND ep.tracking_number NOT IN (
          SELECT tracking_number FROM mrs_package_ledger
      )
    GROUP BY ep.batch_id
    ORDER BY eb.created_at DESC
";

// 获取批次内可入库包裹
$packages_query = "
    SELECT package_id, tracking_number, content_note,
           expiry_date, quantity
    FROM express_package
    WHERE batch_id = :batch_id
      AND package_status = 'counted'
      AND tracking_number NOT IN (
          SELECT tracking_number FROM mrs_package_ledger
      )
";
```

#### 4.2.2 入库保存API (api/inbound_save.php)

**请求参数**:
```json
{
    "packages": [
        {
            "package_id": 123,
            "tracking_number": "SF1234567890",
            "content_note": "番茄酱,芝麻油",
            "expiry_date": "2026-06-15",
            "quantity": 100,
            "items": [
                {"product_name": "番茄酱", "quantity": 50, "expiry_date": "2026-06-15"},
                {"product_name": "芝麻油", "quantity": 50, "expiry_date": "2026-06-15"}
            ]
        }
    ],
    "spec_info": "规格备注",
    "batch_name": "批次A"
}
```

**核心函数** (mrs_lib.php:287-385):
```php
function mrs_inbound_packages($pdo, $packages, $spec_info = '', $operator = '') {
    $created = 0;
    $errors = [];

    try {
        $pdo->beginTransaction();

        foreach ($packages as $pkg) {
            // 1. 获取下一个箱号
            $box_number = mrs_get_next_box_number($pdo, $pkg['batch_name']);

            // 2. 插入台账记录
            $stmt = $pdo->prepare("
                INSERT INTO mrs_package_ledger
                (batch_name, tracking_number, content_note, box_number,
                 spec_info, expiry_date, quantity, status, inbound_time,
                 created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'in_stock', NOW(), ?)
            ");
            $stmt->execute([
                $pkg['batch_name'],
                $pkg['tracking_number'],
                $pkg['content_note'],
                $box_number,
                $spec_info,
                $pkg['expiry_date'],
                $pkg['quantity'],
                $operator
            ]);

            $ledger_id = $pdo->lastInsertId();

            // 3. 保存产品明细
            if (isset($pkg['items']) && is_array($pkg['items'])) {
                $item_stmt = $pdo->prepare("
                    INSERT INTO mrs_package_items
                    (ledger_id, product_name, quantity, expiry_date, sort_order)
                    VALUES (?, ?, ?, ?, ?)
                ");

                foreach ($pkg['items'] as $idx => $item) {
                    $item_stmt->execute([
                        $ledger_id,
                        $item['product_name'],
                        $item['quantity'],
                        $item['expiry_date'],
                        $idx
                    ]);
                }
            }

            $created++;
        }

        $pdo->commit();
        return ['success' => true, 'created' => $created, 'errors' => $errors];

    } catch (PDOException $e) {
        $pdo->rollBack();
        mrs_log('ERROR', "入库失败: " . $e->getMessage());
        return ['success' => false, 'message' => '入库失败'];
    }
}
```

**箱号生成规则** (mrs_lib.php:252-277):
```php
function mrs_get_next_box_number($pdo, $batch_name) {
    // 查询该批次最大箱号
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING(box_number, LENGTH(:prefix) + 1) AS UNSIGNED)) as max_num
        FROM mrs_package_ledger
        WHERE batch_name = :batch_name
          AND box_number LIKE CONCAT(:prefix, '%')
    ");

    $prefix = substr($batch_name, 0, 3);
    $stmt->execute([
        'prefix' => $prefix,
        'batch_name' => $batch_name
    ]);

    $max_num = $stmt->fetchColumn() ?? 0;
    $next_num = $max_num + 1;

    // 格式: 批次前缀 + 4位数字（如 BAT0001）
    return $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}
```

### 4.3 库存查询模块

#### 4.3.1 库存总览 (views/inventory_list.php)

**功能**: 按产品汇总库存，显示箱数、总数量、最近到期日期

**统计卡片**:
```php
// 在库包裹总数
SELECT COUNT(*) FROM mrs_package_ledger WHERE status = 'in_stock'

// 物料种类数（基于产品明细）
SELECT COUNT(DISTINCT product_name) FROM mrs_package_items
WHERE ledger_id IN (SELECT ledger_id FROM mrs_package_ledger WHERE status = 'in_stock')
```

**库存汇总查询** (mrs_lib.php:1356-1404):
```php
function mrs_get_true_inventory_summary($pdo, $product_name = '') {
    $sql = "
        SELECT
            i.product_name AS sku_name,
            COUNT(DISTINCT l.ledger_id) as total_boxes,
            SUM(CASE WHEN i.quantity IS NOT NULL
                     THEN CAST(i.quantity AS UNSIGNED)
                     ELSE 0 END) as total_quantity,
            MIN(CASE WHEN i.expiry_date IS NOT NULL
                     THEN i.expiry_date
                     ELSE NULL END) as nearest_expiry_date
        FROM mrs_package_items i
        INNER JOIN mrs_package_ledger l ON i.ledger_id = l.ledger_id
        WHERE l.status = 'in_stock'
    ";

    if ($product_name) {
        $sql .= " AND i.product_name = :product_name";
    }

    $sql .= " GROUP BY i.product_name ORDER BY i.product_name ASC";

    $stmt = $pdo->prepare($sql);
    if ($product_name) {
        $stmt->bindValue(':product_name', $product_name, PDO::PARAM_STR);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

**保质期颜色标记**:
```php
<?php
$today = new DateTime();
$expiry = new DateTime($row['nearest_expiry_date']);
$diff_days = $today->diff($expiry)->days;

if ($expiry < $today) {
    $color_class = 'expired';      // 灰色 - 已过期
} elseif ($diff_days <= 7) {
    $color_class = 'critical';     // 红色 - 7天内
} elseif ($diff_days <= 30) {
    $color_class = 'warning';      // 橙色 - 30天内
} elseif ($diff_days <= 90) {
    $color_class = 'caution';      // 黄色 - 90天内
} else {
    $color_class = 'normal';       // 绿色 - 正常
}
?>
<td class="<?= $color_class ?>"><?= htmlspecialchars($row['nearest_expiry_date']) ?></td>
```

#### 4.3.2 库存明细 (views/inventory_detail.php)

**功能**: 显示某个产品的所有在库包裹，支持拆零出货

**明细查询** (mrs_lib.php:1406-1467):
```php
function mrs_get_true_inventory_detail($pdo, $product_name, $order_by = 'fifo') {
    $order_clause = match($order_by) {
        'fifo' => 'l.inbound_time ASC',
        'batch' => 'l.batch_name ASC, l.inbound_time ASC',
        'expiry_date_asc' => 'i.expiry_date ASC NULLS LAST',
        'expiry_date_desc' => 'i.expiry_date DESC NULLS LAST',
        'inbound_time_asc' => 'l.inbound_time ASC',
        'inbound_time_desc' => 'l.inbound_time DESC',
        'days_in_stock_asc' => 'l.inbound_time DESC',
        'days_in_stock_desc' => 'l.inbound_time ASC',
        default => 'l.inbound_time ASC'
    };

    $sql = "
        SELECT
            l.ledger_id,
            l.batch_name,
            l.tracking_number,
            l.box_number,
            l.spec_info,
            l.inbound_time,
            l.warehouse_location,
            i.product_name,
            i.quantity,
            i.expiry_date,
            DATEDIFF(NOW(), l.inbound_time) as days_in_stock
        FROM mrs_package_items i
        INNER JOIN mrs_package_ledger l ON i.ledger_id = l.ledger_id
        WHERE l.status = 'in_stock'
          AND i.product_name = :product_name
        ORDER BY {$order_clause}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':product_name', $product_name, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

**搜索功能** (mrs_lib.php:1205-1268):
```php
function mrs_search_instock_packages($pdo, $search_type, $search_value, $order_by = 'fifo') {
    $where_clause = match($search_type) {
        'content_note' => "l.content_note LIKE :search_value",
        'box_number' => "l.box_number = :search_value",
        'tracking_tail' => "l.tracking_number LIKE :search_value",
        'batch_name' => "l.batch_name LIKE :search_value",
        default => "1=1"
    };

    $sql = "
        SELECT l.*,
               GROUP_CONCAT(i.product_name ORDER BY i.sort_order SEPARATOR ', ') as products
        FROM mrs_package_ledger l
        LEFT JOIN mrs_package_items i ON l.ledger_id = i.ledger_id
        WHERE l.status = 'in_stock' AND {$where_clause}
        GROUP BY l.ledger_id
        ORDER BY {$order_clause}
    ";

    $stmt = $pdo->prepare($sql);
    $search_pattern = ($search_type === 'tracking_tail')
        ? "%{$search_value}"
        : "%{$search_value}%";
    $stmt->bindValue(':search_value', $search_pattern, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

### 4.4 出库管理模块

#### 4.4.1 整箱出库 (api/outbound_save.php)

**请求参数**:
```json
{
    "ledger_ids": [123, 124, 125],
    "destination_id": 5,
    "destination_note": "蔬菜部"
}
```

**核心函数** (mrs_lib.php:592-692):
```php
function mrs_outbound_packages($pdo, $ledger_ids, $operator = '',
                               $destination_id = null, $destination_note = '') {
    try {
        $pdo->beginTransaction();

        // 1. 获取包裹信息用于统计
        $placeholders = str_repeat('?,', count($ledger_ids) - 1) . '?';
        $fetch_stmt = $pdo->prepare("
            SELECT ledger_id, content_note, quantity
            FROM mrs_package_ledger
            WHERE ledger_id IN ($placeholders) AND status = 'in_stock'
        ");
        $fetch_stmt->execute($ledger_ids);
        $packages = $fetch_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($packages)) {
            $pdo->rollBack();
            return ['success' => false, 'message' => '没有找到可出库的包裹'];
        }

        // 2. 更新包裹状态为已出库
        $stmt = $pdo->prepare("
            UPDATE mrs_package_ledger
            SET status = 'shipped',
                outbound_time = NOW(),
                destination_id = ?,
                destination_note = ?,
                updated_by = ?
            WHERE ledger_id IN ($placeholders)
        ");

        $params = array_merge(
            [$destination_id, $destination_note, $operator],
            $ledger_ids
        );
        $stmt->execute($params);
        $shipped = $stmt->rowCount();

        // 3. 记录到统计表
        $usage_stmt = $pdo->prepare("
            INSERT INTO mrs_usage_log
            (ledger_id, product_name, outbound_type, deduct_qty,
             destination, operator, created_at)
            VALUES (?, ?, 'whole', ?, ?, ?, NOW())
        ");

        foreach ($packages as $pkg) {
            $usage_stmt->execute([
                $pkg['ledger_id'],
                $pkg['content_note'],
                $pkg['quantity'] ?? 1,
                $destination_note,
                $operator
            ]);
        }

        $pdo->commit();

        mrs_log('INFO', "整箱出库成功: {$shipped}个包裹, 操作员: {$operator}");

        return [
            'success' => true,
            'shipped' => $shipped,
            'message' => "成功出库 {$shipped} 个包裹"
        ];

    } catch (PDOException $e) {
        $pdo->rollBack();
        mrs_log('ERROR', "出库失败: " . $e->getMessage());
        return ['success' => false, 'message' => '出库失败'];
    }
}
```

#### 4.4.2 拆零出货 (api/partial_outbound.php)

**请求参数**:
```json
{
    "ledger_id": 123,
    "deduct_qty": 5,
    "destination": "门店A",
    "outbound_date": "2025-12-16",
    "remark": "日常出货"
}
```

**处理逻辑**:
```php
// 1. 验证包裹状态
$package = mrs_get_package_by_id($pdo, $ledger_id);
if (!$package || $package['status'] !== 'in_stock') {
    mrs_json_response(false, null, '包裹不存在或已出库');
    exit;
}

// 2. 清洗数量（支持 "10kg" → 10）
$deduct_qty = preg_replace('/[^0-9.]/', '', $deduct_qty);

// 3. 仅记录到usage_log，包裹保持in_stock状态
$stmt = $pdo->prepare("
    INSERT INTO mrs_usage_log
    (ledger_id, product_name, outbound_type, deduct_qty,
     destination, operator, created_at, remark)
    VALUES (?, ?, 'partial', ?, ?, ?, ?, ?)
");

$stmt->execute([
    $ledger_id,
    $package['content_note'],
    $deduct_qty,
    $destination,
    $operator,
    $outbound_date,
    $remark
]);

// 4. 返回剩余数量（计算累计出货量）
$total_used = $pdo->prepare("
    SELECT SUM(deduct_qty) FROM mrs_usage_log
    WHERE ledger_id = ?
")->execute([$ledger_id])->fetchColumn();

$remaining_qty = $package['quantity'] - $total_used;

mrs_json_response(true, ['remaining_qty' => $remaining_qty], '拆零出货成功');
```

**特点**:
- 包裹保持 `in_stock` 状态，支持多次拆零
- 仅在 `mrs_usage_log` 记录使用量
- 支持自定义出库日期（可补录历史数据）

### 4.5 去向管理模块

#### 4.5.1 去向类型设计

**去向类型表** (mrs_destination_types):
```sql
type_code | type_name      | is_enabled
----------|----------------|------------
return    | 退回           | 1
warehouse | 仓库调仓       | 1
store     | 发往门店       | 1
```

#### 4.5.2 去向管理函数

**获取去向列表** (mrs_lib.php:793-820):
```php
function mrs_get_destinations($pdo, $type_code = null) {
    $sql = "
        SELECT d.*, dt.type_name
        FROM mrs_destinations d
        LEFT JOIN mrs_destination_types dt ON d.type_code = dt.type_code
        WHERE d.is_active = 1
    ";

    if ($type_code) {
        $sql .= " AND d.type_code = :type_code";
    }

    $sql .= " ORDER BY d.sort_order ASC, d.destination_name ASC";

    $stmt = $pdo->prepare($sql);
    if ($type_code) {
        $stmt->bindValue(':type_code', $type_code, PDO::PARAM_STR);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

**创建去向** (mrs_lib.php:845-911):
```php
function mrs_create_destination($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO mrs_destinations
            (type_code, destination_name, destination_code,
             contact_person, contact_phone, address, remark,
             is_active, sort_order, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['type_code'],
            $data['destination_name'],
            $data['destination_code'] ?? null,
            $data['contact_person'] ?? null,
            $data['contact_phone'] ?? null,
            $data['address'] ?? null,
            $data['remark'] ?? null,
            $data['is_active'] ?? 1,
            $data['sort_order'] ?? 0,
            $data['created_by'] ?? null
        ]);

        $destination_id = $pdo->lastInsertId();

        return [
            'success' => true,
            'data' => ['destination_id' => $destination_id],
            'message' => '去向创建成功'
        ];

    } catch (PDOException $e) {
        mrs_log('ERROR', "创建去向失败: " . $e->getMessage());
        return ['success' => false, 'message' => '创建去向失败'];
    }
}
```

### 4.6 报表统计模块

#### 4.6.1 月度统计 (views/reports.php)

**月度入库统计** (mrs_lib.php:1086-1126):
```php
function mrs_get_monthly_inbound($pdo, $month) {
    $sql = "
        SELECT
            l.batch_name,
            i.product_name,
            COUNT(DISTINCT l.ledger_id) as package_count,
            SUM(CAST(i.quantity AS UNSIGNED)) as total_quantity,
            MIN(l.inbound_time) as first_inbound,
            MAX(l.inbound_time) as last_inbound
        FROM mrs_package_ledger l
        INNER JOIN mrs_package_items i ON l.ledger_id = i.ledger_id
        WHERE DATE_FORMAT(l.inbound_time, '%Y-%m') = :month
        GROUP BY l.batch_name, i.product_name
        ORDER BY l.batch_name, i.product_name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':month', $month, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

**月度出库统计** (mrs_lib.php:1128-1168):
```php
function mrs_get_monthly_outbound($pdo, $month) {
    $sql = "
        SELECT
            log.product_name,
            log.destination,
            log.outbound_type,
            COUNT(*) as outbound_count,
            SUM(log.deduct_qty) as total_quantity,
            MIN(log.created_at) as first_outbound,
            MAX(log.created_at) as last_outbound
        FROM mrs_usage_log log
        WHERE DATE_FORMAT(log.created_at, '%Y-%m') = :month
        GROUP BY log.product_name, log.destination, log.outbound_type
        ORDER BY log.product_name, log.destination
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':month', $month, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

**月度汇总** (mrs_lib.php:1170-1203):
```php
function mrs_get_monthly_summary($pdo, $month) {
    // 入库汇总
    $inbound_stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT ledger_id) as total_packages,
            COUNT(DISTINCT batch_name) as total_batches
        FROM mrs_package_ledger
        WHERE DATE_FORMAT(inbound_time, '%Y-%m') = :month
    ");
    $inbound_stmt->execute(['month' => $month]);
    $inbound = $inbound_stmt->fetch(PDO::FETCH_ASSOC);

    // 出库汇总
    $outbound_stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_outbound_records,
            SUM(deduct_qty) as total_outbound_quantity
        FROM mrs_usage_log
        WHERE DATE_FORMAT(created_at, '%Y-%m') = :month
    ");
    $outbound_stmt->execute(['month' => $month]);
    $outbound = $outbound_stmt->fetch(PDO::FETCH_ASSOC);

    // 当前库存
    $current_stmt = $pdo->query("
        SELECT COUNT(*) as current_stock_packages
        FROM mrs_package_ledger
        WHERE status = 'in_stock'
    ");
    $current = $current_stmt->fetch(PDO::FETCH_ASSOC);

    return array_merge($inbound, $outbound, $current);
}
```

---

## 5. 接口设计

### 5.1 API响应格式

**标准JSON响应** (mrs_lib.php:85-95):
```php
function mrs_json_response($success, $data, $message = '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
```

**响应示例**:
```json
// 成功响应
{
    "success": true,
    "data": {
        "user_id": 1,
        "user_login": "admin",
        "user_display_name": "管理员"
    },
    "message": "登录成功"
}

// 失败响应
{
    "success": false,
    "data": null,
    "message": "用户名或密码错误"
}
```

### 5.2 API接口清单

#### 认证接口
```
POST   /mrs/ap/?action=do_login         用户登录
GET    /mrs/ap/?action=logout           用户登出
```

#### 入库接口
```
GET    /mrs/ap/?action=inbound          入库页面
POST   /mrs/ap/?action=inbound_save     保存入库
```

#### 出库接口
```
GET    /mrs/ap/?action=outbound         出库页面
POST   /mrs/ap/?action=outbound_save    整箱出库
POST   /mrs/ap/?action=partial_outbound 拆零出货
```

#### 库存接口
```
GET    /mrs/ap/?action=inventory_list         库存总览
GET    /mrs/ap/?action=inventory_detail&sku=  库存明细
GET    /mrs/ap/?action=get_package_items&ledger_id=  获取包裹产品明细
```

#### 包裹管理接口
```
POST   /mrs/ap/?action=status_change    状态变更（损耗/作废）
POST   /mrs/ap/?action=update_package   更新包裹信息
```

#### 报表接口
```
GET    /mrs/ap/?action=reports&month=YYYY-MM  月度报表
GET    /mrs/ap/?action=usage_statistics       用量统计
```

#### 去向管理接口
```
GET    /mrs/ap/?action=destination_manage     去向列表
POST   /mrs/ap/?action=destination_save       保存去向
POST   /mrs/ap/?action=destination_delete     删除去向
```

### 5.3 请求参数处理

**支持JSON和表单两种格式** (mrs_lib.php:97-105):
```php
function mrs_get_json_input() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }

    return $data;
}

// 在API中使用
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $input = mrs_get_json_input();
} else {
    $input = $_POST;
}
```

---

## 6. 安全设计

### 6.1 SQL注入防护

**全面使用PDO预处理语句**:
```php
// ✅ 正确示例（所有查询均采用此方式）
$stmt = $pdo->prepare("
    SELECT * FROM mrs_package_ledger
    WHERE batch_name = ? AND status = ?
");
$stmt->execute([$batch_name, $status]);

// ❌ 禁止使用（系统中无此类代码）
$sql = "SELECT * FROM table WHERE id = {$_GET['id']}";
```

### 6.2 XSS防护

**输出转义**:
```php
// 所有用户输入在输出时转义
<?= htmlspecialchars($data, ENT_QUOTES, 'UTF-8') ?>

// JavaScript中的数据
<script>
const data = <?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
```

**模态框自动转义** (dc_html/mrs/ap/js/modal.js):
```javascript
_escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
```

### 6.3 CSRF防护

**会话Cookie配置**:
```php
session_set_cookie_params([
    'samesite' => 'Strict'  // 严格同站策略
]);
```

### 6.4 认证安全

**密码哈希**:
```php
// 存储（在用户创建时）
$hash = password_hash($password, PASSWORD_DEFAULT);

// 验证（在登录时）
if (password_verify($password, $user['user_secret_hash'])) {
    // 登录成功
}
```

**防暴力破解**:
```php
// 5次失败锁定5分钟
if ($_SESSION['login_attempts'] >= 5
    && time() - $_SESSION['last_attempt_time'] < 300) {
    mrs_json_response(false, null, '登录尝试次数过多');
    exit;
}
```

### 6.5 日志审计

**操作日志记录** (mrs_lib.php:59-83):
```php
function mrs_log($level, $message) {
    $log_dir = __DIR__ . '/../../logs/mrs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $log_file = $log_dir . '/' . strtolower($level) . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $user = $_SESSION['user_login'] ?? 'GUEST';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    $log_entry = "[{$timestamp}] [{$level}] [User: {$user}] [IP: {$ip}] {$message}\n";

    file_put_contents($log_file, $log_entry, FILE_APPEND);
}
```

**关键操作记录**:
```php
// 登录
mrs_log('INFO', "用户登录成功: {$username}");

// 入库
mrs_log('INFO', "入库成功: {$created}个包裹, 批次: {$batch_name}");

// 出库
mrs_log('INFO', "出库成功: {$shipped}个包裹, 去向: {$destination_note}");

// 错误
mrs_log('ERROR', "数据库错误: " . $e->getMessage());
```

---

## 7. 技术栈

### 7.1 后端技术

| 技术 | 版本 | 用途 |
|------|------|------|
| PHP | 7.4+ | 后端开发语言 |
| MySQL | 8.4.6 | 数据库服务器 |
| PDO | - | 数据库访问层 |
| Session | - | 会话管理 |

### 7.2 前端技术

| 技术 | 用途 |
|------|------|
| HTML5 | 页面结构 |
| CSS3 | 样式设计（Grid、Flexbox） |
| JavaScript ES6 | 前端交互逻辑 |
| Fetch API | AJAX请求 |
| Promise | 异步处理 |

### 7.3 开发规范

**PHP代码规范**:
- PSR-12 代码风格
- 函数命名: `mrs_` 前缀 + 下划线命名法
- 常量命名: `MRS_` 前缀 + 全大写
- 类文件: 未使用（函数式编程）

**JavaScript代码规范**:
- ES6+ 语法
- async/await 异步处理
- 类定义使用 class 关键字
- 驼峰命名法

**数据库命名规范**:
- 表名: `mrs_` 前缀 + 下划线命名法
- 字段名: 下划线命名法
- 索引: `idx_` 前缀（普通）、`uk_` 前缀（唯一）
- 外键: `fk_` 前缀

---

## 8. 部署架构

### 8.1 服务器要求

**最低配置**:
- CPU: 2核
- 内存: 4GB
- 硬盘: 50GB SSD
- 带宽: 10Mbps

**推荐配置**:
- CPU: 4核
- 内存: 8GB
- 硬盘: 100GB SSD
- 带宽: 100Mbps

### 8.2 软件环境

```
操作系统: Linux (Ubuntu 20.04+ / CentOS 8+)
Web服务器: Nginx 1.18+ / Apache 2.4+
PHP: 7.4+ (推荐 PHP 8.0+)
MySQL: 8.0+ (当前使用 8.4.6)
SSL证书: Let's Encrypt
```

### 8.3 目录权限

```bash
# 应用目录（只读）
chmod -R 755 /home/user/mrscodx/app
chmod -R 755 /home/user/mrscodx/dc_html

# 日志目录（可写）
chmod -R 775 /home/user/mrscodx/logs
chown -R www-data:www-data /home/user/mrscodx/logs

# 配置文件（只读，敏感）
chmod 600 /home/user/mrscodx/app/mrs/config_mrs/env_mrs.php
```

### 8.4 Nginx配置示例

```nginx
server {
    listen 80;
    server_name mrs.example.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name mrs.example.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    root /home/user/mrscodx/dc_html;
    index index.php index.html;

    location /mrs/ap/ {
        try_files $uri $uri/ /mrs/ap/index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 禁止访问敏感文件
    location ~ /(config|logs|docs) {
        deny all;
    }
}
```

### 8.5 数据库部署

**连接配置** (config_mrs/env_mrs.php):
```php
define('MRS_DB_HOST', 'mhdlmskp2kpxguj.mysql.db');
define('MRS_DB_NAME', 'mhdlmskp2kpxguj');
define('MRS_DB_USER', 'mhdlmskp2kpxguj');
define('MRS_DB_PASS', 'BWNrmksqMEqgbX37r3QNDJLGRrUka');
define('MRS_DB_CHARSET', 'utf8mb4');
```

**迁移脚本执行**:
```bash
# 执行数据库迁移
mysql -h mhdlmskp2kpxguj.mysql.db \
      -u mhdlmskp2kpxguj \
      -p < docs/mrs_tables_migration.sql

# 验证表创建
mysql -h mhdlmskp2kpxguj.mysql.db \
      -u mhdlmskp2kpxguj \
      -p -e "SHOW TABLES LIKE 'mrs_%';" mhdlmskp2kpxguj
```

---

## 9. 性能优化

### 9.1 数据库优化

**索引策略**:
```sql
-- 高频查询字段建立索引
CREATE INDEX idx_status ON mrs_package_ledger(status);
CREATE INDEX idx_batch_name ON mrs_package_ledger(batch_name);
CREATE INDEX idx_inbound_time ON mrs_package_ledger(inbound_time);

-- 复合索引优化范围查询
CREATE INDEX idx_product_lookup ON mrs_package_items(product_name, expiry_date);
```

**查询优化**:
```php
// 使用 JOIN 替代子查询
// ❌ 慢查询
$packages = $pdo->query("
    SELECT * FROM mrs_package_ledger
    WHERE ledger_id IN (
        SELECT ledger_id FROM mrs_package_items WHERE product_name = 'XX'
    )
")->fetchAll();

// ✅ 优化后
$packages = $pdo->prepare("
    SELECT DISTINCT l.*
    FROM mrs_package_ledger l
    INNER JOIN mrs_package_items i ON l.ledger_id = i.ledger_id
    WHERE i.product_name = ?
")->execute(['XX'])->fetchAll();
```

**连接池**:
```php
// 单例模式保持连接
function mrs_get_pdo() {
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=" . MRS_DB_HOST . ";dbname=" . MRS_DB_NAME,
            MRS_DB_USER,
            MRS_DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => true  // 持久连接
            ]
        );
    }

    return $pdo;
}
```

### 9.2 前端优化

**资源压缩**:
```bash
# CSS/JS压缩
npm install -g csso-cli uglify-js
csso backend.css -o backend.min.css
uglifyjs modal.js -c -m -o modal.min.js
```

**缓存策略** (Nginx):
```nginx
location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg)$ {
    expires 30d;
    add_header Cache-Control "public, immutable";
}
```

### 9.3 会话优化

**Redis会话存储**（可选）:
```php
// php.ini 配置
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379"
```

---

## 10. 扩展性设计

### 10.1 模块化扩展

**添加新功能模块**:
```
1. 在 app/mrs/views/ 添加页面文件
2. 在 app/mrs/api/ 添加API处理文件
3. 在 app/mrs/lib/mrs_lib.php 添加业务函数
4. 在 dc_html/mrs/ap/index.php 的 $allowed_actions 添加路由
5. 在 views/shared/sidebar.php 添加菜单项
```

### 10.2 权限扩展

**添加角色权限控制**:
```sql
-- 添加角色表
CREATE TABLE mrs_roles (
    role_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    permissions JSON COMMENT '权限列表'
);

-- 用户角色关联表
CREATE TABLE mrs_user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id)
);
```

**权限检查函数**:
```php
function mrs_check_permission($permission) {
    $user_roles = mrs_get_user_roles($_SESSION['user_id']);

    foreach ($user_roles as $role) {
        if (in_array($permission, $role['permissions'])) {
            return true;
        }
    }

    return false;
}
```

### 10.3 API版本化

**建议采用路径版本化**:
```
/mrs/api/v1/inbound
/mrs/api/v2/inbound
```

### 10.4 微服务拆分

**未来可拆分的服务**:
- 认证服务（统一MRS和Express）
- 库存服务（独立的库存计算引擎）
- 报表服务（大数据分析）
- 通知服务（保质期提醒、库存预警）

---

## 附录

### A. 核心函数速查表

| 函数名 | 用途 | 文件位置 |
|--------|------|----------|
| mrs_authenticate_user() | 用户认证 | mrs_lib.php:155 |
| mrs_inbound_packages() | 批量入库 | mrs_lib.php:287 |
| mrs_outbound_packages() | 整箱出库 | mrs_lib.php:592 |
| mrs_get_true_inventory_summary() | 库存汇总 | mrs_lib.php:1356 |
| mrs_get_true_inventory_detail() | 库存明细 | mrs_lib.php:1406 |
| mrs_get_next_box_number() | 获取下一个箱号 | mrs_lib.php:252 |
| mrs_search_instock_packages() | 搜索在库包裹 | mrs_lib.php:1205 |
| mrs_get_destinations() | 获取去向列表 | mrs_lib.php:793 |
| mrs_get_monthly_summary() | 月度统计 | mrs_lib.php:1170 |

### B. 数据库表速查表

| 表名 | 用途 | 记录数预估 |
|------|------|-----------|
| mrs_package_ledger | 包裹台账 | 百万级 |
| mrs_package_items | 产品明细 | 百万级 |
| mrs_sku | SKU商品 | 千级 |
| mrs_inventory | 库存快照 | 千级 |
| mrs_inventory_transaction | 库存流水 | 百万级 |
| mrs_usage_log | 用量日志 | 百万级 |
| mrs_destinations | 去向管理 | 百级 |

### C. 常见问题排查

**Q: 入库失败，提示"快递单号已存在"**
A: 检查 `mrs_package_ledger` 表的 `uk_batch_tracking` 唯一索引

**Q: 库存查询很慢**
A: 检查 `mrs_package_items` 表的 `idx_product_lookup` 索引是否存在

**Q: 会话超时时间如何修改**
A: 修改 `config_mrs/env_mrs.php` 中的 `MRS_SESSION_TIMEOUT` 常量

**Q: 如何查看操作日志**
A: 查看 `logs/mrs/debug.log` 和 `logs/mrs/error.log`

---

**文档结束**

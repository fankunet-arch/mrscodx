-- MRS System Database Tables Migration
-- 此脚本创建MRS系统所需的所有数据库表
-- 执行前请确保已连接到正确的数据库
-- 创建日期: 2025-12-15
-- 用途: 补齐MRS系统缺失的数据库表

USE `mhdlmskp2kpxguj`;

-- ========================================
-- 1. 基础数据表
-- ========================================

-- 分类表
DROP TABLE IF EXISTS `mrs_category`;
CREATE TABLE `mrs_category` (
  `category_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `category_name` VARCHAR(100) NOT NULL COMMENT '分类名称',
  `category_code` VARCHAR(50) DEFAULT NULL COMMENT '分类编码',
  `description` TEXT COMMENT '分类描述',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT '是否有效',
  `sort_order` INT DEFAULT 0 COMMENT '排序',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间',
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uk_category_name` (`category_name`),
  KEY `idx_category_code` (`category_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-分类表';

-- SKU商品表
DROP TABLE IF EXISTS `mrs_sku`;
CREATE TABLE `mrs_sku` (
  `sku_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'SKU ID',
  `category_id` INT UNSIGNED DEFAULT NULL COMMENT '分类ID',
  `sku_code` VARCHAR(50) DEFAULT NULL COMMENT 'SKU编码',
  `sku_name` VARCHAR(200) NOT NULL COMMENT 'SKU名称',
  `brand_name` VARCHAR(100) DEFAULT NULL COMMENT '品牌名称',
  `spec_info` VARCHAR(200) DEFAULT NULL COMMENT '规格信息',
  `standard_unit` VARCHAR(20) DEFAULT '件' COMMENT '标准单位（件、个、瓶等）',
  `case_unit_name` VARCHAR(20) DEFAULT '箱' COMMENT '箱单位名称',
  `case_to_standard_qty` DECIMAL(10,2) DEFAULT 1.00 COMMENT '每箱标准数量',
  `status` ENUM('active','inactive') DEFAULT 'active' COMMENT '状态',
  `remark` TEXT COMMENT '备注',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间',
  PRIMARY KEY (`sku_id`),
  UNIQUE KEY `uk_sku_code` (`sku_code`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_sku_name` (`sku_name`),
  KEY `idx_brand_name` (`brand_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-SKU商品表';

-- 批次表
DROP TABLE IF EXISTS `mrs_batch`;
CREATE TABLE `mrs_batch` (
  `batch_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '批次ID',
  `batch_code` VARCHAR(50) NOT NULL COMMENT '批次编号',
  `batch_name` VARCHAR(100) DEFAULT NULL COMMENT '批次名称',
  `batch_date` DATE DEFAULT NULL COMMENT '批次日期',
  `batch_status` ENUM('draft','receiving','pending_merge','confirmed','closed') DEFAULT 'draft' COMMENT '批次状态',
  `location_name` VARCHAR(100) DEFAULT NULL COMMENT '位置名称',
  `supplier_name` VARCHAR(200) DEFAULT NULL COMMENT '供应商名称',
  `remark` TEXT COMMENT '备注',
  `created_by` VARCHAR(60) DEFAULT NULL COMMENT '创建人',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间',
  PRIMARY KEY (`batch_id`),
  UNIQUE KEY `uk_batch_code` (`batch_code`),
  KEY `idx_batch_status` (`batch_status`),
  KEY `idx_batch_date` (`batch_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-批次表';

-- 批次原始记录表
DROP TABLE IF EXISTS `mrs_batch_raw_record`;
CREATE TABLE `mrs_batch_raw_record` (
  `raw_record_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '原始记录ID',
  `batch_id` INT UNSIGNED NOT NULL COMMENT '批次ID',
  `input_sku_name` VARCHAR(200) DEFAULT NULL COMMENT '输入的SKU名称',
  `input_case_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '输入箱数',
  `input_single_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '输入散装数',
  `physical_box_count` INT DEFAULT NULL COMMENT '实际箱数',
  `status` ENUM('pending','matched','confirmed') DEFAULT 'pending' COMMENT '状态',
  `matched_sku_id` INT UNSIGNED DEFAULT NULL COMMENT '匹配的SKU ID',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间',
  PRIMARY KEY (`raw_record_id`),
  KEY `idx_batch_id` (`batch_id`),
  KEY `idx_matched_sku_id` (`matched_sku_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-批次原始记录表';

-- 批次预期项表
DROP TABLE IF EXISTS `mrs_batch_expected_item`;
CREATE TABLE `mrs_batch_expected_item` (
  `expected_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '预期项ID',
  `batch_id` INT UNSIGNED NOT NULL COMMENT '批次ID',
  `sku_id` INT UNSIGNED NOT NULL COMMENT 'SKU ID',
  `expected_case_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '预期箱数',
  `expected_single_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '预期散装数',
  `total_standard_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '总标准数量',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间',
  PRIMARY KEY (`expected_item_id`),
  KEY `idx_batch_id` (`batch_id`),
  KEY `idx_sku_id` (`sku_id`),
  UNIQUE KEY `uk_batch_sku` (`batch_id`, `sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-批次预期项表';

-- 批次确认项表
DROP TABLE IF EXISTS `mrs_batch_confirmed_item`;
CREATE TABLE `mrs_batch_confirmed_item` (
  `confirmed_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '确认项ID',
  `batch_id` INT UNSIGNED NOT NULL COMMENT '批次ID',
  `sku_id` INT UNSIGNED NOT NULL COMMENT 'SKU ID',
  `confirmed_case_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '确认箱数',
  `confirmed_single_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '确认散装数',
  `total_standard_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '总标准数量',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间',
  PRIMARY KEY (`confirmed_item_id`),
  KEY `idx_batch_id` (`batch_id`),
  KEY `idx_sku_id` (`sku_id`),
  UNIQUE KEY `uk_batch_sku` (`batch_id`, `sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-批次确认项表';

-- ========================================
-- 2. 库存相关表
-- ========================================

-- 库存主表
DROP TABLE IF EXISTS `mrs_inventory`;
CREATE TABLE `mrs_inventory` (
  `inventory_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '库存ID',
  `sku_id` INT UNSIGNED NOT NULL COMMENT 'SKU ID',
  `current_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '当前库存数量',
  `unit` VARCHAR(20) DEFAULT '件' COMMENT '单位',
  `last_updated_at` DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '最后更新时间',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  PRIMARY KEY (`inventory_id`),
  UNIQUE KEY `uk_sku_id` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-库存主表';

-- 库存流水表
DROP TABLE IF EXISTS `mrs_inventory_transaction`;
CREATE TABLE `mrs_inventory_transaction` (
  `transaction_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '流水ID',
  `sku_id` INT UNSIGNED NOT NULL COMMENT 'SKU ID',
  `transaction_type` ENUM('inbound','outbound','adjustment') NOT NULL COMMENT '交易类型',
  `transaction_subtype` VARCHAR(50) DEFAULT NULL COMMENT '交易子类型（surplus盘盈/deficit盘亏等）',
  `quantity_change` DECIMAL(10,2) NOT NULL COMMENT '数量变化（正数为增加，负数为减少）',
  `quantity_before` DECIMAL(10,2) NOT NULL COMMENT '变化前数量',
  `quantity_after` DECIMAL(10,2) NOT NULL COMMENT '变化后数量',
  `unit` VARCHAR(20) DEFAULT '件' COMMENT '单位',
  `batch_id` INT UNSIGNED DEFAULT NULL COMMENT '关联批次ID',
  `outbound_order_id` INT UNSIGNED DEFAULT NULL COMMENT '关联出库单ID',
  `adjustment_id` INT UNSIGNED DEFAULT NULL COMMENT '关联调整记录ID',
  `operator_name` VARCHAR(60) DEFAULT NULL COMMENT '操作人',
  `remark` TEXT COMMENT '备注',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  PRIMARY KEY (`transaction_id`),
  KEY `idx_sku_id` (`sku_id`),
  KEY `idx_transaction_type` (`transaction_type`),
  KEY `idx_batch_id` (`batch_id`),
  KEY `idx_outbound_order_id` (`outbound_order_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-库存流水表';

-- 库存调整记录表
DROP TABLE IF EXISTS `mrs_inventory_adjustment`;
CREATE TABLE `mrs_inventory_adjustment` (
  `adjustment_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '调整记录ID',
  `sku_id` INT UNSIGNED NOT NULL COMMENT 'SKU ID',
  `delta_qty` DECIMAL(10,2) NOT NULL COMMENT '调整数量（正数为盘盈，负数为盘亏）',
  `reason` VARCHAR(255) DEFAULT NULL COMMENT '调整原因',
  `operator_name` VARCHAR(60) DEFAULT NULL COMMENT '操作人',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  PRIMARY KEY (`adjustment_id`),
  KEY `idx_sku_id` (`sku_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-库存调整记录表';

-- ========================================
-- 3. 出库相关表
-- ========================================

-- 出库单主表
DROP TABLE IF EXISTS `mrs_outbound_order`;
CREATE TABLE `mrs_outbound_order` (
  `outbound_order_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '出库单ID',
  `outbound_code` VARCHAR(50) NOT NULL COMMENT '出库单号',
  `outbound_date` DATE NOT NULL COMMENT '出库日期',
  `outbound_type` TINYINT DEFAULT 1 COMMENT '出库类型（1=销售出库，2=调拨出库，3=退货出库等）',
  `location_name` VARCHAR(100) DEFAULT NULL COMMENT '目的地位置',
  `recipient_name` VARCHAR(100) DEFAULT NULL COMMENT '收货人',
  `recipient_phone` VARCHAR(20) DEFAULT NULL COMMENT '收货电话',
  `recipient_address` TEXT COMMENT '收货地址',
  `status` ENUM('draft','confirmed','shipped','completed','cancelled') DEFAULT 'draft' COMMENT '状态',
  `remark` TEXT COMMENT '备注',
  `created_by` VARCHAR(60) DEFAULT NULL COMMENT '创建人',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间',
  PRIMARY KEY (`outbound_order_id`),
  UNIQUE KEY `uk_outbound_code` (`outbound_code`),
  KEY `idx_outbound_date` (`outbound_date`),
  KEY `idx_status` (`status`),
  KEY `idx_outbound_type` (`outbound_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-出库单主表';

-- 出库单明细表
DROP TABLE IF EXISTS `mrs_outbound_order_item`;
CREATE TABLE `mrs_outbound_order_item` (
  `outbound_order_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '出库单明细ID',
  `outbound_order_id` INT UNSIGNED NOT NULL COMMENT '出库单ID',
  `sku_id` INT UNSIGNED NOT NULL COMMENT 'SKU ID',
  `sku_name` VARCHAR(200) DEFAULT NULL COMMENT 'SKU名称（冗余字段）',
  `unit_name` VARCHAR(20) DEFAULT '件' COMMENT '单位名称',
  `case_unit_name` VARCHAR(20) DEFAULT '箱' COMMENT '箱单位名称',
  `case_to_standard_qty` DECIMAL(10,2) DEFAULT 1.00 COMMENT '每箱标准数量',
  `outbound_case_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '出库箱数',
  `outbound_single_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '出库散装数',
  `total_standard_qty` DECIMAL(10,2) DEFAULT 0 COMMENT '总标准数量',
  `remark` TEXT COMMENT '备注',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '创建时间',
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新时间',
  PRIMARY KEY (`outbound_order_item_id`),
  KEY `idx_outbound_order_id` (`outbound_order_id`),
  KEY `idx_sku_id` (`sku_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS-出库单明细表';

-- ========================================
-- 外键约束
-- ========================================

-- mrs_sku 外键
ALTER TABLE `mrs_sku`
  ADD CONSTRAINT `fk_sku_category` FOREIGN KEY (`category_id`) REFERENCES `mrs_category` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- mrs_batch_raw_record 外键
ALTER TABLE `mrs_batch_raw_record`
  ADD CONSTRAINT `fk_raw_batch` FOREIGN KEY (`batch_id`) REFERENCES `mrs_batch` (`batch_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_raw_sku` FOREIGN KEY (`matched_sku_id`) REFERENCES `mrs_sku` (`sku_id`) ON DELETE SET NULL;

-- mrs_batch_expected_item 外键
ALTER TABLE `mrs_batch_expected_item`
  ADD CONSTRAINT `fk_expected_batch` FOREIGN KEY (`batch_id`) REFERENCES `mrs_batch` (`batch_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_expected_sku` FOREIGN KEY (`sku_id`) REFERENCES `mrs_sku` (`sku_id`) ON DELETE CASCADE;

-- mrs_batch_confirmed_item 外键
ALTER TABLE `mrs_batch_confirmed_item`
  ADD CONSTRAINT `fk_confirmed_batch` FOREIGN KEY (`batch_id`) REFERENCES `mrs_batch` (`batch_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_confirmed_sku` FOREIGN KEY (`sku_id`) REFERENCES `mrs_sku` (`sku_id`) ON DELETE CASCADE;

-- mrs_inventory 外键
ALTER TABLE `mrs_inventory`
  ADD CONSTRAINT `fk_inventory_sku` FOREIGN KEY (`sku_id`) REFERENCES `mrs_sku` (`sku_id`) ON DELETE CASCADE;

-- mrs_inventory_transaction 外键
ALTER TABLE `mrs_inventory_transaction`
  ADD CONSTRAINT `fk_transaction_sku` FOREIGN KEY (`sku_id`) REFERENCES `mrs_sku` (`sku_id`) ON DELETE CASCADE;

-- mrs_inventory_adjustment 外键
ALTER TABLE `mrs_inventory_adjustment`
  ADD CONSTRAINT `fk_adjustment_sku` FOREIGN KEY (`sku_id`) REFERENCES `mrs_sku` (`sku_id`) ON DELETE CASCADE;

-- mrs_outbound_order_item 外键
ALTER TABLE `mrs_outbound_order_item`
  ADD CONSTRAINT `fk_outbound_item_order` FOREIGN KEY (`outbound_order_id`) REFERENCES `mrs_outbound_order` (`outbound_order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_outbound_item_sku` FOREIGN KEY (`sku_id`) REFERENCES `mrs_sku` (`sku_id`) ON DELETE RESTRICT;

COMMIT;

-- 迁移完成
-- 所有MRS系统所需的数据库表已创建完毕

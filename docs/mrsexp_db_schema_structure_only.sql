-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- 主机： mhdlmskp2kpxguj.mysql.db
-- 生成日期： 2025-12-15 13:58:05
-- 服务器版本： 8.4.6-6
-- PHP 版本： 8.1.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `mhdlmskp2kpxguj`
--
CREATE DATABASE IF NOT EXISTS `mhdlmskp2kpxguj` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `mhdlmskp2kpxguj`;

-- --------------------------------------------------------

--
-- 表的结构 `express_batch`
--

DROP TABLE IF EXISTS `express_batch`;
CREATE TABLE `express_batch` (
  `batch_id` int UNSIGNED NOT NULL COMMENT '批次ID',
  `batch_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '批次名称（手工录入）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '创建人',
  `status` enum('active','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT '批次状态',
  `total_count` int UNSIGNED NOT NULL DEFAULT '0' COMMENT '总包裹数',
  `verified_count` int UNSIGNED NOT NULL DEFAULT '0' COMMENT '已核实数',
  `counted_count` int UNSIGNED NOT NULL DEFAULT '0' COMMENT '已清点数',
  `adjusted_count` int UNSIGNED NOT NULL DEFAULT '0' COMMENT '已调整数',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='快递批次表';

-- --------------------------------------------------------

--
-- 表的结构 `express_operation_log`
--

DROP TABLE IF EXISTS `express_operation_log`;
CREATE TABLE `express_operation_log` (
  `log_id` int UNSIGNED NOT NULL COMMENT '日志ID',
  `package_id` int UNSIGNED NOT NULL COMMENT '包裹ID',
  `operation_type` enum('verify','count','adjust') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作类型',
  `operation_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间',
  `operator` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作人',
  `old_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '旧状态',
  `new_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '新状态',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

-- --------------------------------------------------------

--
-- 表的结构 `express_package`
--

DROP TABLE IF EXISTS `express_package`;
CREATE TABLE `express_package` (
  `package_id` int UNSIGNED NOT NULL COMMENT '包裹ID',
  `batch_id` int UNSIGNED NOT NULL COMMENT '批次ID',
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '快递单号',
  `package_status` enum('pending','verified','counted','adjusted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '包裹状态',
  `content_note` text COLLATE utf8mb4_unicode_ci COMMENT '内容备注（清点时填写）',
  `expiry_date` date DEFAULT NULL COMMENT '保质期（非生产日期，选填）',
  `quantity` int UNSIGNED DEFAULT NULL COMMENT '数量（选填）',
  `adjustment_note` text COLLATE utf8mb4_unicode_ci COMMENT '调整备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `verified_at` datetime DEFAULT NULL COMMENT '核实时间',
  `counted_at` datetime DEFAULT NULL COMMENT '清点时间',
  `adjusted_at` datetime DEFAULT NULL COMMENT '调整时间',
  `verified_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '核实人',
  `counted_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '清点人',
  `adjusted_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '调整人'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='快递包裹表';

-- --------------------------------------------------------

--
-- 表的结构 `express_package_items`
--

DROP TABLE IF EXISTS `express_package_items`;
CREATE TABLE `express_package_items` (
  `item_id` int UNSIGNED NOT NULL COMMENT '明细ID',
  `package_id` int UNSIGNED NOT NULL COMMENT '包裹ID',
  `product_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '产品名称/内容备注',
  `quantity` int UNSIGNED DEFAULT NULL COMMENT '数量',
  `expiry_date` date DEFAULT NULL COMMENT '保质期',
  `sort_order` int DEFAULT '0' COMMENT '排序',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='快递包裹产品明细表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_destinations`
--

DROP TABLE IF EXISTS `mrs_destinations`;
CREATE TABLE `mrs_destinations` (
  `destination_id` int UNSIGNED NOT NULL COMMENT '去向ID',
  `type_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '去向类型代码',
  `destination_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '去向名称',
  `destination_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '去向编码（可选）',
  `contact_person` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系人',
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `address` text COLLATE utf8mb4_unicode_ci COMMENT '地址',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `is_active` tinyint(1) DEFAULT '1' COMMENT '是否有效',
  `sort_order` int DEFAULT '0' COMMENT '排序',
  `created_by` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '创建人',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='去向管理表';

-- --------------------------------------------------------

--
-- 替换视图以便查看 `mrs_destination_stats`
-- （参见下面的实际视图）
--
DROP VIEW IF EXISTS `mrs_destination_stats`;
CREATE TABLE `mrs_destination_stats` (
`days_used` bigint
,`destination_id` int unsigned
,`destination_name` varchar(100)
,`last_used_time` datetime
,`total_shipments` bigint
,`type_name` varchar(50)
);

-- --------------------------------------------------------

--
-- 表的结构 `mrs_destination_types`
--

DROP TABLE IF EXISTS `mrs_destination_types`;
CREATE TABLE `mrs_destination_types` (
  `type_id` int UNSIGNED NOT NULL COMMENT '类型ID',
  `type_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '类型代码 (return, warehouse, store)',
  `type_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '类型名称 (退回、仓库调仓、发往门店)',
  `is_enabled` tinyint(1) DEFAULT '1' COMMENT '是否启用',
  `sort_order` int DEFAULT '0' COMMENT '排序',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='去向类型配置表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_package_items`
--

DROP TABLE IF EXISTS `mrs_package_items`;
CREATE TABLE `mrs_package_items` (
  `item_id` int UNSIGNED NOT NULL COMMENT '明细ID',
  `ledger_id` bigint UNSIGNED NOT NULL COMMENT '台账ID',
  `product_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '产品名称/内容备注',
  `quantity` int UNSIGNED DEFAULT NULL COMMENT '数量',
  `expiry_date` date DEFAULT NULL COMMENT '保质期',
  `sort_order` int DEFAULT '0' COMMENT '排序',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS台账产品明细表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_package_ledger`
--

DROP TABLE IF EXISTS `mrs_package_ledger`;
CREATE TABLE `mrs_package_ledger` (
  `ledger_id` bigint UNSIGNED NOT NULL COMMENT '台账ID (主键)',
  `batch_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '批次名称',
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '快递单号',
  `content_note` text COLLATE utf8mb4_unicode_ci COMMENT '内容备注',
  `box_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '箱号',
  `warehouse_location` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '仓库位置',
  `spec_info` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '规格备注',
  `expiry_date` date DEFAULT NULL COMMENT '保质期（非生产日期，选填）',
  `quantity` int UNSIGNED DEFAULT NULL COMMENT '数量（选填，参考用途）',
  `status` enum('in_stock','shipped','void') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_stock' COMMENT '状态',
  `inbound_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '入库时间',
  `outbound_time` datetime DEFAULT NULL COMMENT '出库时间',
  `destination_id` int UNSIGNED DEFAULT NULL COMMENT '出库去向ID',
  `destination_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '去向备注',
  `void_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '损耗原因',
  `created_by` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '创建人',
  `updated_by` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '更新人',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MRS 包裹台账表';

-- --------------------------------------------------------

--
-- 表的结构 `mrs_usage_log`
--

DROP TABLE IF EXISTS `mrs_usage_log`;
CREATE TABLE `mrs_usage_log` (
  `id` int UNSIGNED NOT NULL COMMENT '记录ID',
  `ledger_id` int UNSIGNED DEFAULT NULL COMMENT '包裹台账ID（关联 mrs_package_ledger）',
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '商品名称',
  `outbound_type` enum('partial','whole') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'partial' COMMENT '出货类型：partial=拆零出货, whole=整箱出货',
  `deduct_qty` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '出货数量（标准单位件数）',
  `destination` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '目的地（门店名称）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '出货时间',
  `operator` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作员',
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '备注'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='统一出货记录表（拆零+整箱）';

-- --------------------------------------------------------

--
-- 表的结构 `sys_users`
--

DROP TABLE IF EXISTS `sys_users`;
CREATE TABLE `sys_users` (
  `user_id` bigint UNSIGNED NOT NULL COMMENT '用户唯一ID (主键)',
  `user_login` varchar(60) COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT '用户登录名 (不可变, 用于登录)',
  `user_secret_hash` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT '用户密码的哈希值 (用于验证)',
  `user_email` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT '用户电子邮箱 (可用于通知和找回密码)',
  `user_display_name` varchar(250) COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT '用户显示名称 (在界面上展示的名字)',
  `user_status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'pending' COMMENT '用户账户状态 (例如: active, suspended, pending, deleted)',
  `user_registered_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '用户注册时间 (UTC)',
  `user_last_login_at` datetime(6) DEFAULT NULL COMMENT '用户最后登录时间 (UTC)',
  `user_updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '记录最后更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci COMMENT='系统用户表';

--
-- 转储表的索引
--

--
-- 表的索引 `express_batch`
--
ALTER TABLE `express_batch`
  ADD PRIMARY KEY (`batch_id`),
  ADD UNIQUE KEY `uk_batch_name` (`batch_name`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_status` (`status`);

--
-- 表的索引 `express_operation_log`
--
ALTER TABLE `express_operation_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_package_id` (`package_id`),
  ADD KEY `idx_operation_type` (`operation_type`),
  ADD KEY `idx_operation_time` (`operation_time`);

--
-- 表的索引 `express_package`
--
ALTER TABLE `express_package`
  ADD PRIMARY KEY (`package_id`),
  ADD UNIQUE KEY `uk_tracking_batch` (`tracking_number`,`batch_id`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_tracking_number` (`tracking_number`),
  ADD KEY `idx_package_status` (`package_status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_expiry_date` (`expiry_date`);

--
-- 表的索引 `express_package_items`
--
ALTER TABLE `express_package_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_package_id` (`package_id`),
  ADD KEY `idx_expiry_date` (`expiry_date`);

--
-- 表的索引 `mrs_destinations`
--
ALTER TABLE `mrs_destinations`
  ADD PRIMARY KEY (`destination_id`),
  ADD KEY `idx_type_code` (`type_code`),
  ADD KEY `idx_active` (`is_active`);

--
-- 表的索引 `mrs_destination_types`
--
ALTER TABLE `mrs_destination_types`
  ADD PRIMARY KEY (`type_id`),
  ADD UNIQUE KEY `uk_type_code` (`type_code`);

--
-- 表的索引 `mrs_package_items`
--
ALTER TABLE `mrs_package_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_ledger_id` (`ledger_id`),
  ADD KEY `idx_expiry_date` (`expiry_date`),
  ADD KEY `idx_product_lookup` (`product_name`,`expiry_date`);

--
-- 表的索引 `mrs_package_ledger`
--
ALTER TABLE `mrs_package_ledger`
  ADD PRIMARY KEY (`ledger_id`),
  ADD UNIQUE KEY `uk_batch_tracking` (`batch_name`,`tracking_number`),
  ADD UNIQUE KEY `uk_batch_box` (`batch_name`,`box_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_content_note` (`content_note`(50)),
  ADD KEY `idx_batch_name` (`batch_name`),
  ADD KEY `idx_inbound_time` (`inbound_time`),
  ADD KEY `idx_outbound_time` (`outbound_time`),
  ADD KEY `idx_destination` (`destination_id`),
  ADD KEY `idx_expiry_date` (`expiry_date`),
  ADD KEY `idx_product_status` (`status`);

--
-- 表的索引 `mrs_usage_log`
--
ALTER TABLE `mrs_usage_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_name`),
  ADD KEY `idx_destination` (`destination`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_ledger_id` (`ledger_id`);

--
-- 表的索引 `sys_users`
--
ALTER TABLE `sys_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uk_user_login` (`user_login`),
  ADD UNIQUE KEY `uk_user_email` (`user_email`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `express_batch`
--
ALTER TABLE `express_batch`
  MODIFY `batch_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '批次ID';

--
-- 使用表AUTO_INCREMENT `express_operation_log`
--
ALTER TABLE `express_operation_log`
  MODIFY `log_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '日志ID';

--
-- 使用表AUTO_INCREMENT `express_package`
--
ALTER TABLE `express_package`
  MODIFY `package_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '包裹ID';

--
-- 使用表AUTO_INCREMENT `express_package_items`
--
ALTER TABLE `express_package_items`
  MODIFY `item_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '明细ID';

--
-- 使用表AUTO_INCREMENT `mrs_destinations`
--
ALTER TABLE `mrs_destinations`
  MODIFY `destination_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '去向ID';

--
-- 使用表AUTO_INCREMENT `mrs_destination_types`
--
ALTER TABLE `mrs_destination_types`
  MODIFY `type_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '类型ID';

--
-- 使用表AUTO_INCREMENT `mrs_package_items`
--
ALTER TABLE `mrs_package_items`
  MODIFY `item_id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '明细ID';

--
-- 使用表AUTO_INCREMENT `mrs_package_ledger`
--
ALTER TABLE `mrs_package_ledger`
  MODIFY `ledger_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '台账ID (主键)';

--
-- 使用表AUTO_INCREMENT `mrs_usage_log`
--
ALTER TABLE `mrs_usage_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID';

--
-- 使用表AUTO_INCREMENT `sys_users`
--
ALTER TABLE `sys_users`
  MODIFY `user_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户唯一ID (主键)';

-- --------------------------------------------------------

--
-- 视图结构 `mrs_destination_stats`
--
DROP TABLE IF EXISTS `mrs_destination_stats`;

DROP VIEW IF EXISTS `mrs_destination_stats`;
CREATE ALGORITHM=UNDEFINED DEFINER=`mhdlmskp2kpxguj`@`%` SQL SECURITY DEFINER VIEW `mrs_destination_stats`  AS SELECT `d`.`destination_id` AS `destination_id`, `d`.`destination_name` AS `destination_name`, `dt`.`type_name` AS `type_name`, count(`l`.`ledger_id`) AS `total_shipments`, count(distinct cast(`l`.`outbound_time` as date)) AS `days_used`, max(`l`.`outbound_time`) AS `last_used_time` FROM ((`mrs_destinations` `d` left join `mrs_destination_types` `dt` on((`d`.`type_code` = `dt`.`type_code`))) left join `mrs_package_ledger` `l` on(((`d`.`destination_id` = `l`.`destination_id`) and (`l`.`status` = 'shipped')))) WHERE (`d`.`is_active` = 1) GROUP BY `d`.`destination_id`, `d`.`destination_name`, `dt`.`type_name` ORDER BY `total_shipments` DESC ;

--
-- 限制导出的表
--

--
-- 限制表 `express_operation_log`
--
ALTER TABLE `express_operation_log`
  ADD CONSTRAINT `fk_log_package` FOREIGN KEY (`package_id`) REFERENCES `express_package` (`package_id`) ON DELETE CASCADE;

--
-- 限制表 `express_package`
--
ALTER TABLE `express_package`
  ADD CONSTRAINT `fk_package_batch` FOREIGN KEY (`batch_id`) REFERENCES `express_batch` (`batch_id`) ON DELETE CASCADE;

--
-- 限制表 `express_package_items`
--
ALTER TABLE `express_package_items`
  ADD CONSTRAINT `fk_item_package` FOREIGN KEY (`package_id`) REFERENCES `express_package` (`package_id`) ON DELETE CASCADE;

--
-- 限制表 `mrs_destinations`
--
ALTER TABLE `mrs_destinations`
  ADD CONSTRAINT `fk_destination_type` FOREIGN KEY (`type_code`) REFERENCES `mrs_destination_types` (`type_code`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- 限制表 `mrs_package_items`
--
ALTER TABLE `mrs_package_items`
  ADD CONSTRAINT `fk_item_ledger` FOREIGN KEY (`ledger_id`) REFERENCES `mrs_package_ledger` (`ledger_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

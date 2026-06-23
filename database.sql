-- OneSystem Business Management System
-- Database Schema
-- Charset: utf8mb4 | Collation: utf8mb4_unicode_ci

SET NAMES utf8mb4;
SET time_zone = '+03:00';
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Zones
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `zones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `manager_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Branches
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `branches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `zone_id` INT DEFAULT NULL,
  `manager_id` INT DEFAULT NULL,
  `address` TEXT,
  `phone` VARCHAR(20),
  `email` VARCHAR(100),
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_zone` (`zone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20),
  `role` ENUM('super_admin','zone_manager','branch_manager','cashier','stock_controller') NOT NULL DEFAULT 'cashier',
  `branch_id` INT DEFAULT NULL,
  `zone_id` INT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `last_login` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_branch` (`branch_id`),
  KEY `idx_zone` (`zone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add cross-reference foreign keys after both tables exist
ALTER TABLE `zones` ADD CONSTRAINT `fk_zone_manager` FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;
ALTER TABLE `branches` ADD CONSTRAINT `fk_branch_zone` FOREIGN KEY (`zone_id`) REFERENCES `zones`(`id`) ON DELETE SET NULL;
ALTER TABLE `branches` ADD CONSTRAINT `fk_branch_manager` FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;
ALTER TABLE `users` ADD CONSTRAINT `fk_user_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL;

-- --------------------------------------------------------
-- Products
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sku` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(200) NOT NULL,
  `barcode` VARCHAR(100),
  `description` TEXT,
  `cost_price` DECIMAL(15,2) DEFAULT 0.00,
  `wholesale_price` DECIMAL(15,2) DEFAULT 0.00,
  `retail_price` DECIMAL(15,2) DEFAULT 0.00,
  `min_stock_alert` INT DEFAULT 5,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FULLTEXT KEY `ft_product_search` (`name`, `sku`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Stock (per branch)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `branch_id` INT NOT NULL,
  `quantity` INT DEFAULT 0,
  `cost_price_override` DECIMAL(15,2) DEFAULT NULL,
  `wholesale_price_override` DECIMAL(15,2) DEFAULT NULL,
  `retail_price_override` DECIMAL(15,2) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_product_branch` (`product_id`, `branch_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Stock Adjustments History
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `branch_id` INT NOT NULL,
  `adjustment_type` ENUM('add','subtract','set','transfer_in','transfer_out') NOT NULL,
  `quantity_before` INT NOT NULL,
  `quantity_changed` INT NOT NULL,
  `quantity_after` INT NOT NULL,
  `reason` VARCHAR(255),
  `reference` VARCHAR(100),
  `user_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_product_branch` (`product_id`, `branch_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Customers
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20),
  `email` VARCHAR(100),
  `type` ENUM('retail','wholesale','regular') DEFAULT 'retail',
  `total_purchases` DECIMAL(15,2) DEFAULT 0.00,
  `last_purchase_date` DATE DEFAULT NULL,
  `notes` TEXT,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Sales
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
  `branch_id` INT NOT NULL,
  `customer_id` INT DEFAULT NULL,
  `customer_name` VARCHAR(100),
  `customer_type` ENUM('retail','wholesale') DEFAULT 'retail',
  `subtotal` DECIMAL(15,2) DEFAULT 0.00,
  `discount` DECIMAL(15,2) DEFAULT 0.00,
  `tax` DECIMAL(15,2) DEFAULT 0.00,
  `total` DECIMAL(15,2) DEFAULT 0.00,
  `payment_method` ENUM('cash','card','mobile_money','credit') DEFAULT 'cash',
  `payment_status` ENUM('paid','pending','partial') DEFAULT 'paid',
  `amount_paid` DECIMAL(15,2) DEFAULT 0.00,
  `notes` TEXT,
  `cashier_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_branch_date` (`branch_id`, `created_at`),
  KEY `idx_customer` (`customer_id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Sale Items
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(15,2) NOT NULL,
  `price_type` ENUM('retail','wholesale') DEFAULT 'retail',
  `total_price` DECIMAL(15,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Suppliers
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `company_name` VARCHAR(150),
  `contact_person` VARCHAR(100),
  `phone` VARCHAR(20),
  `email` VARCHAR(100),
  `address` TEXT,
  `tax_id` VARCHAR(50),
  `payment_terms` VARCHAR(100),
  `status` ENUM('active','inactive') DEFAULT 'active',
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Product-Supplier Mapping
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `supplier_id` INT NOT NULL,
  `supplier_sku` VARCHAR(50),
  `cost_price` DECIMAL(15,2),
  `lead_time_days` INT DEFAULT 0,
  `is_preferred` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_product_supplier` (`product_id`, `supplier_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Purchase Orders
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `po_number` VARCHAR(50) NOT NULL UNIQUE,
  `supplier_id` INT NOT NULL,
  `branch_id` INT NOT NULL,
  `status` ENUM('draft','pending','approved','ordered','received','cancelled') DEFAULT 'draft',
  `subtotal` DECIMAL(15,2) DEFAULT 0.00,
  `tax` DECIMAL(15,2) DEFAULT 0.00,
  `shipping` DECIMAL(15,2) DEFAULT 0.00,
  `discount` DECIMAL(15,2) DEFAULT 0.00,
  `total` DECIMAL(15,2) DEFAULT 0.00,
  `notes` TEXT,
  `expected_date` DATE DEFAULT NULL,
  `received_date` DATE DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `approved_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_status` (`status`),
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Purchase Order Items
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `po_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `product_name` VARCHAR(200) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `quantity_ordered` INT NOT NULL,
  `quantity_received` INT DEFAULT 0,
  `unit_cost` DECIMAL(15,2) NOT NULL,
  `total_cost` DECIMAL(15,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- PO Receiving Log
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `po_receiving_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `po_id` INT NOT NULL,
  `po_item_id` INT NOT NULL,
  `quantity_received` INT NOT NULL,
  `received_by` INT DEFAULT NULL,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`po_item_id`) REFERENCES `purchase_order_items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Activity Logs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Failed Logins
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `failed_logins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ip` (`ip_address`),
  KEY `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Rate Limits
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `key_name` VARCHAR(150) NOT NULL PRIMARY KEY,
  `attempts` INT DEFAULT 1,
  `last_attempt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Currency Settings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `currency_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `currency_code` VARCHAR(10) NOT NULL DEFAULT 'TSh',
  `currency_symbol` VARCHAR(10) NOT NULL DEFAULT 'TSh',
  `currency_name` VARCHAR(50) NOT NULL DEFAULT 'Tanzanian Shilling',
  `decimal_places` INT DEFAULT 0,
  `thousands_separator` VARCHAR(5) DEFAULT ',',
  `decimal_separator` VARCHAR(5) DEFAULT '.',
  `symbol_position` ENUM('before','after') DEFAULT 'before',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Default Data
-- --------------------------------------------------------
INSERT IGNORE INTO `currency_settings`
  (`currency_code`,`currency_symbol`,`currency_name`,`decimal_places`,`thousands_separator`,`decimal_separator`,`symbol_position`)
VALUES
  ('TSh','TSh','Tanzanian Shilling',0,',','.','before');

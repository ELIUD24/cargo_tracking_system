-- ============================================
-- CargoTrack Database Schema
-- Complete schema for cargo tracking system
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `cargo_db` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `cargo_db`;

-- ============================================
-- TABLE: users
-- Stores all system users (admin, staff, customer)
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `role` ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'customer',
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: customers
-- Extended customer profile information
-- ============================================
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `company_name` VARCHAR(150) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT 'Kenya',
  `postal_code` VARCHAR(20) DEFAULT NULL,
  `id_number` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  CONSTRAINT `fk_customer_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: drivers
-- Driver information for shipments
-- ============================================
CREATE TABLE IF NOT EXISTS `drivers` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `license_number` VARCHAR(50) NOT NULL UNIQUE,
  `license_expiry` DATE DEFAULT NULL,
  `id_number` VARCHAR(50) NOT NULL UNIQUE,
  `status` ENUM('available', 'assigned', 'inactive') NOT NULL DEFAULT 'available',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: vehicles
-- Vehicle fleet information
-- ============================================
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `registration_number` VARCHAR(20) NOT NULL UNIQUE,
  `type` ENUM('truck', 'van', 'motorcycle', 'container') NOT NULL,
  `capacity_kg` DECIMAL(10,2) DEFAULT NULL,
  `model` VARCHAR(50) DEFAULT NULL,
  `year` YEAR DEFAULT NULL,
  `color` VARCHAR(30) DEFAULT NULL,
  `status` ENUM('available', 'in_use', 'maintenance', 'inactive') NOT NULL DEFAULT 'available',
  `insurance_expiry` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_registration` (`registration_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: shipments
-- Main shipment records
-- ============================================
CREATE TABLE IF NOT EXISTS `shipments` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tracking_number` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` INT(11) UNSIGNED NOT NULL,
  `shipment_type` ENUM('import', 'export', 'local') NOT NULL,
  `transport_mode` ENUM('air', 'water', 'train', 'road') NOT NULL,
  `goods_category` VARCHAR(50) DEFAULT NULL,
  `goods_type` VARCHAR(100) DEFAULT NULL,
  `goods_description` TEXT DEFAULT NULL,
  `weight` DECIMAL(10,2) DEFAULT NULL,
  `value` DECIMAL(15,2) DEFAULT NULL,
  `sender_name` VARCHAR(150) DEFAULT NULL,
  `sender_phone` VARCHAR(20) DEFAULT NULL,
  `sender_address` TEXT DEFAULT NULL,
  `receiver_name` VARCHAR(150) DEFAULT NULL,
  `receiver_phone` VARCHAR(20) DEFAULT NULL,
  `receiver_address` TEXT DEFAULT NULL,
  `pickup_location` TEXT DEFAULT NULL,
  `delivery_location` TEXT DEFAULT NULL,
  `pickup_deadline` DATETIME DEFAULT NULL,
  `delivery_deadline` DATETIME DEFAULT NULL,
  `estimated_delivery` DATE DEFAULT NULL,
  `service_type` ENUM('standard', 'express', 'same_day', 'international') DEFAULT 'standard',
  `price` DECIMAL(10,2) DEFAULT NULL,
  `payment_status` ENUM('unpaid', 'paid', 'partial') DEFAULT 'unpaid',
  `status` ENUM('pending', 'approved', 'rejected', 'in_transit', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
  `admin_notes` TEXT DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tracking_number` (`tracking_number`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_shipment_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: shipment_tracking
-- Tracking history and location updates for shipments
-- ============================================
CREATE TABLE IF NOT EXISTS `shipment_tracking` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `shipment_id` INT(11) UNSIGNED NOT NULL,
  `status` ENUM('pending', 'approved', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'exception') NOT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `latitude` DECIMAL(10,8) DEFAULT NULL,
  `longitude` DECIMAL(11,8) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `updated_by` INT(11) UNSIGNED DEFAULT NULL,
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shipment_id` (`shipment_id`),
  KEY `idx_timestamp` (`timestamp`),
  CONSTRAINT `fk_tracking_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: clearances
-- Customs clearance requests and approvals
-- ============================================
CREATE TABLE IF NOT EXISTS `clearances` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `shipment_id` INT(11) UNSIGNED NOT NULL,
  `tracking_number` VARCHAR(50) NOT NULL,
  `staff_id` INT(11) UNSIGNED NOT NULL,
  `driver_id` INT(11) UNSIGNED DEFAULT NULL,
  `vehicle_id` INT(11) UNSIGNED DEFAULT NULL,
  `clearance_type` ENUM('import', 'export', 'transit') NOT NULL,
  `clearance_status` ENUM('pending', 'approved', 'rejected', 'processing') NOT NULL DEFAULT 'pending',
  `admin_id` INT(11) UNSIGNED DEFAULT NULL,
  `documents` JSON DEFAULT NULL,
  `customs_reference` VARCHAR(100) DEFAULT NULL,
  `approved_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shipment_id` (`shipment_id`),
  KEY `idx_clearance_status` (`clearance_status`),
  KEY `idx_tracking_number` (`tracking_number`),
  CONSTRAINT `fk_clearance_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_clearance_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_clearance_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_clearance_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: clearance_requests
-- Legacy clearance requests table (for backward compatibility)
-- ============================================
CREATE TABLE IF NOT EXISTS `clearance_requests` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT(11) UNSIGNED NOT NULL,
  `shipment_id` INT(11) UNSIGNED DEFAULT NULL,
  `tracking_number` VARCHAR(50) NOT NULL,
  `goods_description` TEXT DEFAULT NULL,
  `origin` VARCHAR(255) DEFAULT NULL,
  `destination` VARCHAR(255) DEFAULT NULL,
  `weight` DECIMAL(10,2) DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'processing') NOT NULL DEFAULT 'pending',
  `admin_notes` TEXT DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_clearance_req_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: notifications
-- User notifications system
-- ============================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'success', 'warning', 'error') NOT NULL DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: message_logs
-- SMS and WhatsApp message logging
-- ============================================
CREATE TABLE IF NOT EXISTS `message_logs` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `recipient` VARCHAR(20) NOT NULL,
  `message_type` ENUM('sms', 'whatsapp', 'email') NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('pending', 'sent', 'delivered', 'failed') NOT NULL DEFAULT 'pending',
  `provider_message_id` VARCHAR(100) DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_recipient` (`recipient`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: payments
-- Payment records for shipments
-- ============================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `shipment_id` INT(11) UNSIGNED NOT NULL,
  `customer_id` INT(11) UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('mpesa', 'bank', 'card', 'cash') DEFAULT NULL,
  `transaction_reference` VARCHAR(100) DEFAULT NULL,
  `payment_status` ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  `paid_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shipment_id` (`shipment_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_transaction_ref` (`transaction_reference`),
  CONSTRAINT `fk_payment_shipment` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: audit_logs
-- System activity logging
-- ============================================
CREATE TABLE `audit_logs` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) DEFAULT NULL,
  `entity_id` INT(11) DEFAULT NULL,
  `old_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DEFAULT DATA: Users
-- ============================================

-- Admin user (password: admin123)
INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`, `status`) VALUES
('Admin User', 'admin@cargotrack.co.ke', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+254700000001', 'admin', 'active');

-- Staff user (password: staff123)
INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`, `status`) VALUES
('Staff User', 'staff@cargotrack.co.ke', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+254700000002', 'staff', 'active');

-- Demo customer (password: customer123)
INSERT INTO `users` (`name`, `email`, `password`, `phone`, `role`, `status`) VALUES
('John Doe', 'customer@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+254700000003', 'customer', 'active');

-- ============================================
-- DEFAULT DATA: Drivers
-- ============================================
INSERT INTO `drivers` (`name`, `phone`, `license_number`, `id_number`, `status`) VALUES
('James Kamau', '+254701000001', 'DL-001234', 'ID-12345678', 'available'),
('Peter Ochieng', '+254701000002', 'DL-001235', 'ID-23456789', 'available'),
('Mary Wanjiku', '+254701000003', 'DL-001236', 'ID-34567890', 'available');

-- ============================================
-- DEFAULT DATA: Vehicles
-- ============================================
INSERT INTO `vehicles` (`registration_number`, `type`, `capacity_kg`, `model`, `year`, `color`, `status`) VALUES
('KCA-001A', 'truck', 5000.00, 'Isuzu NPR', 2020, 'White', 'available'),
('KCA-002B', 'van', 1500.00, 'Toyota Hiace', 2019, 'Silver', 'available'),
('KCA-003C', 'motorcycle', 200.00, 'Bajaj Boxer', 2021, 'Blue', 'available'),
('KCA-004D', 'container', 20000.00, 'Scania', 2018, 'Red', 'available');

COMMIT;

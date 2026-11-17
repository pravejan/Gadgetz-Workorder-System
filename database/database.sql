-- Create Database if not exists
CREATE DATABASE IF NOT EXISTS `computer_repair_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `computer_repair_db`;

-- 1. Users Table (for Authentication)
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'technician') NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Customers Table
CREATE TABLE IF NOT EXISTS `customers` (
    `customer_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Work Orders Table
CREATE TABLE IF NOT EXISTS `work_orders` (
    `work_order_id` INT AUTO_INCREMENT PRIMARY KEY,
    `work_order_no` VARCHAR(50) UNIQUE NOT NULL,
    `customer_id` INT NOT NULL,
    `device_type` VARCHAR(50) NOT NULL, -- Laptop, Desktop, Printer, Phone, CCTV Camera, DVR, Monitor, Other
    `brand` VARCHAR(100) NOT NULL,
    `model` VARCHAR(100) NOT NULL,
    `serial_number` VARCHAR(100) DEFAULT NULL,
    `colour` VARCHAR(50) DEFAULT NULL,
    `complaint` TEXT NOT NULL,
    `device_note` TEXT DEFAULT NULL,
    `accessories` TEXT DEFAULT NULL, -- Stored as JSON string
    `physical_condition` TEXT DEFAULT NULL, -- Stored as JSON string
    `estimated_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `actual_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('Received', 'Diagnosing', 'Waiting for Parts', 'Repairing', 'Completed', 'Collected') NOT NULL DEFAULT 'Received',
    `received_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completion_date` DATE DEFAULT NULL,
    `completed_date` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`customer_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Images Table
CREATE TABLE IF NOT EXISTS `images` (
    `image_id` INT AUTO_INCREMENT PRIMARY KEY,
    `work_order_id` INT NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`work_order_id`) REFERENCES `work_orders`(`work_order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Technician Notes Table
CREATE TABLE IF NOT EXISTS `technician_notes` (
    `note_id` INT AUTO_INCREMENT PRIMARY KEY,
    `work_order_id` INT NOT NULL,
    `notes` TEXT NOT NULL,
    `parts_used` TEXT DEFAULT NULL,
    `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`work_order_id`) REFERENCES `work_orders`(`work_order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Default Users
-- admin / admin123
-- tech / tech123
INSERT INTO `users` (`username`, `password`, `role`, `full_name`) VALUES
('admin', '$2y$10$rwG4MqBEJjqXBD2MZ9wpsO1a5GWm2k4yecLveyXcSAJlmNIojkP1i', 'admin', 'System Administrator'),
('tech', '$2y$10$pfd.LwvTaqk54SWIYv23UuV3VBSBrnSu/vd5zISYWKv7UdYOY7JOK', 'technician', 'Senior Technician')
ON DUPLICATE KEY UPDATE `username`=`username`;

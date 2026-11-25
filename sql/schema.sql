-- =======================================================
-- Database: ParkingDB
-- DBMS PBL: Campus Parking Management System
-- Schema Definition with Foreign Keys & Constraints
-- =======================================================

CREATE DATABASE IF NOT EXISTS `ParkingDB` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ParkingDB`;

-- Disable foreign key checks during schema creation
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `Parking_Payment`;
DROP TABLE IF EXISTS `Parking_Record`;
DROP TABLE IF EXISTS `Parking_Slot`;
DROP TABLE IF EXISTS `Vehicle`;
DROP TABLE IF EXISTS `Parking_Zone`;

-- 1. Parking_Zone Table
CREATE TABLE `Parking_Zone` (
    `zone_id` INT AUTO_INCREMENT PRIMARY KEY,
    `zone_name` VARCHAR(50) NOT NULL UNIQUE,
    `location` VARCHAR(50) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Vehicle Table
CREATE TABLE `Vehicle` (
    `vehicle_id` INT AUTO_INCREMENT PRIMARY KEY,
    `owner_name` VARCHAR(100) NOT NULL,
    `vehicle_number` VARCHAR(20) NOT NULL UNIQUE,
    `vehicle_type` VARCHAR(20) NULL,
    `registration_year` INT NULL,
    `zone_id` INT NULL,
    CONSTRAINT `fk_vehicle_zone` FOREIGN KEY (`zone_id`) 
        REFERENCES `Parking_Zone` (`zone_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Parking_Slot Table
CREATE TABLE `Parking_Slot` (
    `slot_id` VARCHAR(10) PRIMARY KEY,
    `slot_name` VARCHAR(100) NOT NULL,
    `hourly_rate` INT NOT NULL DEFAULT 20,
    `zone_id` INT NULL,
    CONSTRAINT `fk_slot_zone` FOREIGN KEY (`zone_id`) 
        REFERENCES `Parking_Zone` (`zone_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Parking_Record Table
CREATE TABLE `Parking_Record` (
    `record_id` INT AUTO_INCREMENT PRIMARY KEY,
    `vehicle_id` INT NOT NULL,
    `slot_id` VARCHAR(10) NOT NULL,
    `parking_date` DATE NOT NULL,
    `hours_parked` DECIMAL(5,2) NOT NULL,
    `stay_category` VARCHAR(10) NOT NULL,
    CONSTRAINT `chk_hours_parked` CHECK (`hours_parked` >= 0 AND `hours_parked` <= 24),
    CONSTRAINT `uq_vehicle_slot_date` UNIQUE (`vehicle_id`, `slot_id`, `parking_date`),
    CONSTRAINT `fk_record_vehicle` FOREIGN KEY (`vehicle_id`) 
        REFERENCES `Vehicle` (`vehicle_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_record_slot` FOREIGN KEY (`slot_id`) 
        REFERENCES `Parking_Slot` (`slot_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Parking_Payment Table
CREATE TABLE `Parking_Payment` (
    `payment_id` INT AUTO_INCREMENT PRIMARY KEY,
    `vehicle_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_date` DATE NOT NULL,
    `payment_status` VARCHAR(20) NOT NULL DEFAULT 'Paid',
    CONSTRAINT `fk_payment_vehicle` FOREIGN KEY (`vehicle_id`) 
        REFERENCES `Vehicle` (`vehicle_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

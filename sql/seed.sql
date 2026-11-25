-- =======================================================
-- Database: ParkingDB
-- Sample Seed Data
-- Inserted in Foreign-Key Dependency Order
-- =======================================================

USE `ParkingDB`;

-- 1. Insert Zones
INSERT INTO `Parking_Zone` (`zone_id`, `zone_name`, `location`) VALUES
(1, 'Zone A - Main Gate', 'North Campus Entrance Gate 1'),
(2, 'Zone B - Library Block', 'Central Academic Complex'),
(3, 'Zone C - Hostel Side', 'South Student Housing Wing'),
(4, 'Zone D - Sports Complex', 'East Athletic Arena');

-- 2. Insert Slots (References Parking_Zone)
INSERT INTO `Parking_Slot` (`slot_id`, `slot_name`, `hourly_rate`, `zone_id`) VALUES
('S-A01', 'North Gate Premium Car Bay', 30, 1),
('S-A02', 'North Gate Two-Wheeler Bay', 15, 1),
('S-B01', 'Library Fast EV Charging Slot', 40, 2),
('S-B02', 'Library Faculty & Visitor Slot', 25, 2),
('S-C01', 'Hostel Overnight Parking Bay', 20, 3);

-- 3. Insert Vehicles (References Parking_Zone)
INSERT INTO `Vehicle` (`vehicle_id`, `owner_name`, `vehicle_number`, `vehicle_type`, `registration_year`, `zone_id`) VALUES
(1, 'Rahul Sharma', 'MH-12-AB-1001', 'Car (Sedan)', 2021, 1),
(2, 'Priya Patel', 'MH-12-CD-2002', 'Two-Wheeler', 2022, 1),
(3, 'Dr. Amit Kumar', 'DL-01-EF-3003', 'SUV', 2020, 2),
(4, 'Prof. Sneha Verma', 'KA-05-GH-4004', 'Electric Car', 2023, 2),
(5, 'Vikram Singh', 'MH-14-JK-5005', 'Two-Wheeler', 2019, 3),
(6, 'Ananya Roy', 'WB-02-LM-6006', 'Car (Hatchback)', 2022, 3);

-- 4. Insert Parking Records (References Vehicle and Parking_Slot)
-- Stay classification:
-- Short: < 4 hours
-- Medium: 4 hours up to (not including) 8 hours
-- Long: 8 hours or more
INSERT INTO `Parking_Record` (`record_id`, `vehicle_id`, `slot_id`, `parking_date`, `hours_parked`, `stay_category`) VALUES
(1, 1, 'S-A01', '2026-11-20', 2.50, 'Short'),
(2, 2, 'S-A02', '2026-11-20', 5.00, 'Medium'),
(3, 3, 'S-B01', '2026-11-21', 9.50, 'Long'),
(4, 4, 'S-B02', '2026-11-21', 4.00, 'Medium'),
(5, 5, 'S-C01', '2026-11-22', 12.00, 'Long'),
(6, 6, 'S-C01', '2026-11-23', 1.75, 'Short'),
(7, 1, 'S-A01', '2026-11-24', 6.50, 'Medium'),
(8, 3, 'S-B01', '2026-11-25', 8.00, 'Long'),
(9, 2, 'S-A02', '2026-11-25', 3.25, 'Short');

-- 5. Insert Parking Payments (References Vehicle)
INSERT INTO `Parking_Payment` (`payment_id`, `vehicle_id`, `amount`, `payment_date`, `payment_status`) VALUES
(1, 1, 195.00, '2026-11-20', 'Paid'),
(2, 2, 50.00, '2026-11-20', 'Partial'),
(3, 3, 380.00, '2026-11-21', 'Paid'),
(4, 4, 100.00, '2026-11-21', 'Paid'),
(5, 5, 120.00, '2026-11-22', 'Partial'),
(6, 6, 35.00, '2026-11-23', 'Paid');

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 06, 2025 at 12:52 PM
-- Server version: 10.3.39-MariaDB-0ubuntu0.20.04.2
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `core1_movers`
--

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `zip` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('online','busy','offline') DEFAULT 'offline',
  `latitude` decimal(10,8) DEFAULT NULL COMMENT 'Customer''s current latitude',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT 'Customer''s current longitude',
  `location_updated_at` timestamp NULL DEFAULT NULL COMMENT 'When the location was last updated'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Customer information with reference to core2_movers.users table (referential integrity enforced at application level)';

--
-- Dumping data for table `customers`
--

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `driver_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `license_expiry` date NOT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `status` enum('available','busy','offline') NOT NULL DEFAULT 'offline',
  `latitude` decimal(10,8) DEFAULT NULL COMMENT 'Driver''s current latitude',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT 'Driver''s current longitude',
  `location_updated_at` timestamp NULL DEFAULT NULL COMMENT 'When the location was last updated',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores driver information linked to user accounts in core2_movers';

--

-- --------------------------------------------------------

--
-- Table structure for table `driver_performance`
--

CREATE TABLE `driver_performance` (
  `performance_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `total_bookings` int(11) NOT NULL DEFAULT 0,
  `completed_bookings` int(11) NOT NULL DEFAULT 0,
  `cancelled_bookings` int(11) NOT NULL DEFAULT 0,
  `total_revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_distance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `avg_rating` decimal(3,2) DEFAULT NULL,
  `total_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `driver_performance`
--

-- --------------------------------------------------------

--
-- Table structure for table `driver_vehicle_history`
--

CREATE TABLE `driver_vehicle_history` (
  `history_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `assigned_date` datetime NOT NULL,
  `unassigned_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fuel_records`
--

CREATE TABLE `fuel_records` (
  `fuel_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `fuel_amount` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `odometer_reading` int(11) NOT NULL,
  `fill_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fuel_records`
--

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_records`
--

CREATE TABLE `maintenance_records` (
  `maintenance_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `cost` decimal(10,2) NOT NULL,
  `service_date` date NOT NULL,
  `next_service_date` date DEFAULT NULL,
  `odometer_reading` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_records`
--

-- --------------------------------------------------------

--
-- Table structure for table `popular_routes`
--

CREATE TABLE `popular_routes` (
  `route_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `from_location` varchar(255) NOT NULL,
  `to_location` varchar(255) NOT NULL,
  `total_bookings` int(11) NOT NULL DEFAULT 0,
  `avg_fare` decimal(10,2) NOT NULL DEFAULT 0.00,
  `avg_distance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `avg_duration` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `popular_routes`
--

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `vehicle_id` int(11) NOT NULL,
  `vin` varchar(17) NOT NULL COMMENT 'Vehicle Identification Number',
  `plate_number` varchar(20) NOT NULL,
  `model` varchar(50) NOT NULL,
  `year` int(4) NOT NULL,
  `capacity` int(11) NOT NULL,
  `status` enum('active','maintenance','inactive') NOT NULL DEFAULT 'active',
  `fuel_type` varchar(20) NOT NULL,
  `vehicle_image` varchar(255) DEFAULT NULL COMMENT 'Path to vehicle image',
  `assigned_driver_id` int(11) DEFAULT NULL COMMENT 'Currently assigned driver',
  `last_maintenance` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--


-- --------------------------------------------------------

--
-- Table structure for table `vehicle_assignment_history`
--

CREATE TABLE `vehicle_assignment_history` (
  `assignment_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `assigned_date` datetime NOT NULL,
  `unassigned_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_assignment_history`
--
-- --------------------------------------------------------

--
-- Table structure for table `vehicle_performance`
--

CREATE TABLE `vehicle_performance` (
  `performance_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `total_distance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fuel_consumed` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fuel_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `maintenance_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_bookings` int(11) NOT NULL DEFAULT 0,
  `revenue_generated` decimal(12,2) NOT NULL DEFAULT 0.00,
  `operating_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `idle_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_performance`
--


--
-- Indexes for dumped tables
--

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_customer_location` (`latitude`,`longitude`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`driver_id`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD KEY `idx_driver_location` (`latitude`,`longitude`);

--
-- Indexes for table `driver_performance`
--
ALTER TABLE `driver_performance`
  ADD PRIMARY KEY (`performance_id`),
  ADD UNIQUE KEY `driver_date` (`driver_id`,`report_date`);

--
-- Indexes for table `driver_vehicle_history`
--
ALTER TABLE `driver_vehicle_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `driver_id` (`driver_id`),
  ADD KEY `vehicle_id` (`vehicle_id`);

--
-- Indexes for table `fuel_records`
--
ALTER TABLE `fuel_records`
  ADD PRIMARY KEY (`fuel_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `maintenance_records`
--
ALTER TABLE `maintenance_records`
  ADD PRIMARY KEY (`maintenance_id`),
  ADD KEY `vehicle_id` (`vehicle_id`);

--
-- Indexes for table `popular_routes`
--
ALTER TABLE `popular_routes`
  ADD PRIMARY KEY (`route_id`),
  ADD UNIQUE KEY `date_route` (`report_date`,`from_location`,`to_location`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`),
  ADD UNIQUE KEY `vin` (`vin`),
  ADD KEY `vehicles_ibfk_1` (`assigned_driver_id`);

--
-- Indexes for table `vehicle_assignment_history`
--
ALTER TABLE `vehicle_assignment_history`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `vehicle_performance`
--
ALTER TABLE `vehicle_performance`
  ADD PRIMARY KEY (`performance_id`),
  ADD UNIQUE KEY `vehicle_date` (`vehicle_id`,`report_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `driver_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `driver_performance`
--
ALTER TABLE `driver_performance`
  MODIFY `performance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `driver_vehicle_history`
--
ALTER TABLE `driver_vehicle_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fuel_records`
--
ALTER TABLE `fuel_records`
  MODIFY `fuel_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `maintenance_records`
--
ALTER TABLE `maintenance_records`
  MODIFY `maintenance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `popular_routes`
--
ALTER TABLE `popular_routes`
  MODIFY `route_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `vehicle_assignment_history`
--
ALTER TABLE `vehicle_assignment_history`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `vehicle_performance`
--
ALTER TABLE `vehicle_performance`
  MODIFY `performance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `driver_performance`
--
ALTER TABLE `driver_performance`
  ADD CONSTRAINT `driver_performance_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`);

--
-- Constraints for table `driver_vehicle_history`
--
ALTER TABLE `driver_vehicle_history`
  ADD CONSTRAINT `dvh_driver_fk` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dvh_vehicle_fk` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE;

--
-- Constraints for table `fuel_records`
--
ALTER TABLE `fuel_records`
  ADD CONSTRAINT `fuel_records_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`),
  ADD CONSTRAINT `fuel_records_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`);

--
-- Constraints for table `maintenance_records`
--
ALTER TABLE `maintenance_records`
  ADD CONSTRAINT `maintenance_records_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`);

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`assigned_driver_id`) REFERENCES `drivers` (`driver_id`) ON DELETE SET NULL;

--
-- Constraints for table `vehicle_assignment_history`
--
ALTER TABLE `vehicle_assignment_history`
  ADD CONSTRAINT `vehicle_assignment_history_driver_fk` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `vehicle_assignment_history_vehicle_fk` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vehicle_performance`
--
ALTER TABLE `vehicle_performance`
  ADD CONSTRAINT `vehicle_performance_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

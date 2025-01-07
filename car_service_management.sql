-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 11, 2024 at 08:35 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `car_service_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `appointment_date` datetime NOT NULL,
  `status` enum('pending','confirmed','in-progress','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `package_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `user_id`, `vehicle_id`, `service_id`, `technician_id`, `appointment_date`, `status`, `notes`, `created_at`, `updated_at`, `package_id`) VALUES
(26, 14, 7, 3, 13, '2024-12-19 03:35:00', 'completed', '\n\n\n\n\n\n\n\n\n\n\nBe ready\nNot\n', '2024-12-01 19:35:23', '2024-12-08 15:13:31', NULL),
(31, 14, 5, 3, NULL, '2024-12-13 20:15:00', 'cancelled', '', '2024-12-09 10:16:04', '2024-12-09 10:17:11', NULL),
(32, 11, 9, NULL, 17, '2024-12-30 20:35:00', 'completed', '\n\n', '2024-12-09 12:35:13', '2024-12-11 07:33:59', 1),
(33, 11, 9, 1, NULL, '2024-12-14 14:30:00', 'pending', '', '2024-12-11 06:31:07', '2024-12-11 06:31:07', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `appointment_logs`
--

CREATE TABLE `appointment_logs` (
  `log_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `status_from` varchar(50) DEFAULT NULL,
  `status_to` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_logs`
--

INSERT INTO `appointment_logs` (`log_id`, `appointment_id`, `status_from`, `status_to`, `notes`, `created_by`, `created_at`) VALUES
(37, 26, 'pending', 'confirmed', '', 6, '2024-12-01 19:46:24'),
(38, 26, '', 'confirmed', '', 6, '2024-12-01 19:57:04'),
(39, 26, '', 'confirmed', '', 6, '2024-12-01 19:57:13'),
(40, 26, '', 'confirmed', '', 6, '2024-12-01 19:57:21'),
(41, 26, '', 'confirmed', '', 6, '2024-12-01 20:06:07'),
(42, 26, '', 'confirmed', '', 6, '2024-12-01 20:06:11'),
(43, 26, '', 'confirmed', '', 6, '2024-12-05 02:15:31'),
(44, 26, '', 'completed', '', 6, '2024-12-05 02:15:55'),
(45, 26, 'completed', 'cancelled', '', 6, '2024-12-05 02:16:14'),
(46, 26, 'cancelled', 'pending', '', 6, '2024-12-05 02:16:29'),
(47, 26, 'pending', 'confirmed', 'Be ready', 6, '2024-12-06 15:05:24'),
(48, 26, '', 'in-progress', 'Not', 6, '2024-12-06 15:06:14'),
(54, 32, 'pending', 'confirmed', '', 6, '2024-12-09 12:54:35'),
(55, 32, 'confirmed', 'completed', '', 6, '2024-12-11 07:33:59');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_status_history`
--

CREATE TABLE `appointment_status_history` (
  `history_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_reminders`
--

CREATE TABLE `maintenance_reminders` (
  `reminder_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','completed','overdue') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `type` enum('email','sms') NOT NULL,
  `sent_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `appointment_id`, `message`, `type`, `sent_at`) VALUES
(64, 6, 26, 'New appointment booking #26', '', '2024-12-02 03:35:23'),
(65, 14, 26, 'Your service appointment for Check Engine has been updated to: confirmed', '', '2024-12-02 03:46:24'),
(66, 13, 26, 'You have been assigned to appointment #26', '', '2024-12-02 03:46:24'),
(67, 14, 26, 'Your service appointment for Check Engine has been updated to: confirmed', '', '2024-12-02 03:57:04'),
(68, 14, 26, 'Your service appointment for Check Engine has been updated to: confirmed', '', '2024-12-02 03:57:13'),
(69, 14, 26, 'Your service appointment for Check Engine has been updated to: confirmed', '', '2024-12-02 03:57:21'),
(70, 14, 26, 'Your service appointment for Check Engine has been updated to: confirmed', '', '2024-12-02 04:06:07'),
(71, 14, 26, 'Your service appointment for Check Engine has been updated to: confirmed', '', '2024-12-02 04:06:11'),
(72, 14, 26, 'Your service appointment for Check Engine has been updated to: confirmed', '', '2024-12-05 10:15:31'),
(73, 14, 26, 'Your service appointment for Check Engine has been updated to: completed', '', '2024-12-05 10:15:55'),
(74, 14, 26, 'Your service appointment for Check Engine has been updated to: cancelled', '', '2024-12-05 10:16:14'),
(75, 14, 26, 'Your service appointment for Check Engine has been updated to: pending', '', '2024-12-05 10:16:29'),
(78, 14, 26, 'Your service appointment for Check Engine has been updated to: confirmed', '', '2024-12-06 23:05:24'),
(79, 14, 26, 'Your service appointment for Check Engine has been updated to: in-progress', '', '2024-12-06 23:06:14'),
(85, 14, 26, 'Your appointment for Check Engine has been updated to: Completed', '', '2024-12-08 23:13:31'),
(86, 6, 26, 'Appointment #26 status updated to: Completed', '', '2024-12-08 23:13:31'),
(94, 6, 31, 'New appointment booking #31', '', '2024-12-09 18:16:04'),
(95, 14, 31, 'Your appointment has been cancelled by admin', '', '2024-12-09 18:17:11'),
(98, 6, 32, 'New appointment booking #32', '', '2024-12-09 20:35:13'),
(99, 11, 32, 'Your service package appointment for Basic Maintenance has been updated to: confirmed', '', '2024-12-09 20:54:35'),
(100, 17, 32, 'You have been assigned to appointment #32', '', '2024-12-09 20:54:35'),
(101, 6, 33, 'New appointment booking #33', '', '2024-12-11 14:31:07'),
(102, 11, 32, 'Your service package appointment for Basic Maintenance has been updated to: completed', '', '2024-12-11 15:33:59');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_minutes` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `name`, `description`, `price`, `duration_minutes`, `created_at`, `updated_at`) VALUES
(1, 'Oil Change', 'I-change ang oil ng kutse', 20.00, 0, '2024-11-28 21:02:02', '2024-12-11 04:25:29'),
(2, 'Tire Change', 'I-change ang ligid', 40.00, 0, '2024-11-28 21:02:53', '2024-12-11 04:25:36'),
(3, 'Check Engine', 'Lantawun ang makina', 30.00, 0, '2024-12-01 16:49:37', '2024-12-11 04:25:43'),
(4, 'Replace Coolant', 'Coolant ireplace', 50.00, 0, '2024-12-08 07:28:28', '2024-12-11 04:25:54');

-- --------------------------------------------------------

--
-- Table structure for table `service_packages`
--

CREATE TABLE `service_packages` (
  `package_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_minutes` int(11) NOT NULL,
  `included_services` text DEFAULT NULL,
  `discount_percentage` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_packages`
--

INSERT INTO `service_packages` (`package_id`, `name`, `description`, `price`, `duration_minutes`, `included_services`, `discount_percentage`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Basic Maintenance', 'Oil change, filter replacement, and basic inspection', 89.99, 60, 'Oil Change,Filter Replacement,Basic Inspection,Fluid Check', 0, 1, '2024-11-30 03:17:33', '2024-11-30 03:17:33'),
(2, 'Premium Service', 'Comprehensive maintenance including brake check and wheel alignment', 199.99, 120, 'Oil Change,Filter Replacement,Brake Inspection,Wheel Alignment,Fluid Top-up,Battery Check', 10, 1, '2024-11-30 03:17:33', '2024-11-30 03:17:33'),
(3, 'Complete Care', 'Full vehicle service with detailed inspection and maintenance', 299.99, 180, 'Oil Change,Filter Replacement,Brake Service,Wheel Alignment,Tire Rotation,Battery Service,AC Check,Engine Diagnostic', 15, 1, '2024-11-30 03:17:33', '2024-11-30 03:17:33'),
(4, 'Quick Service', 'Express oil change and basic check-up', 49.99, 30, 'Oil Change,Basic Inspection,Fluid Check', 0, 1, '2024-11-30 03:17:33', '2024-11-30 03:17:33'),
(5, 'Seasonal Package', 'onsa?', 149.99, 90, 'Oil Change,Brake Check,Tire Inspection,Battery Test,Coolant Check', 5, 0, '2024-11-30 03:17:33', '2024-12-01 17:17:17');

-- --------------------------------------------------------

--
-- Table structure for table `service_reminders`
--

CREATE TABLE `service_reminders` (
  `reminder_id` int(11) NOT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `reminder_date` date DEFAULT NULL,
  `reminder_type` varchar(50) DEFAULT NULL,
  `status` enum('pending','sent','completed') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `technician_schedules`
--

CREATE TABLE `technician_schedules` (
  `schedule_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('available','booked','off') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `role` enum('admin','technician','customer') NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone_number` varchar(15) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `loyalty_points` int(11) DEFAULT 0,
  `notification_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_preferences`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `role`, `first_name`, `last_name`, `email`, `password`, `phone_number`, `created_at`, `updated_at`, `loyalty_points`, `notification_preferences`) VALUES
(6, 'admin', 'Admin', 'User', 'admin@autobots.com', '$2y$10$cpPoWtHFLBJY6QolJg5UFuJ20s.SkgP2PrG0B0uqI/ifTjY/Ec6Ja', '1234567890', '2024-11-28 20:12:35', '2024-11-28 20:36:04', 0, NULL),
(11, 'customer', 'jm', 'user', 'jm@example.com', '$2y$10$Ndq8RSlufuizYxtN7u8MbeqTkSWc7W6EjmHROcy5deNFviSQxdQsi', '09534234891', '2024-11-28 20:45:28', '2024-11-28 20:45:28', 0, NULL),
(13, 'technician', 'Tech', 'Expert', 'tech@autobots.com', '$2y$10$rQCZrNyq3tX.zLqfPTMkUezjviwm9ePj0o0N.OcQECkK2CyfxnZHe', '0987654321', '2024-11-28 20:47:51', '2024-11-28 20:47:51', 0, NULL),
(14, 'customer', 'wen', 'wen', 'wen@example.com', '$2y$10$q4CEklILyGjrdxY5oWYT/uEk5IUa1.lYsc3xM8aAmlhneyl3tecLu', '09451992710', '2024-11-30 14:51:49', '2024-11-30 14:51:49', 0, NULL),
(17, 'technician', 'Techy', 'Savyy', 'techy@autobots.com', '$2y$10$eWQ6Go4ARrFyW1SsMEbhfOwkzlNiL.toPj/NbmnuZYFrf8enriaCS', '09343258492', '2024-12-09 12:54:20', '2024-12-09 12:59:18', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `vehicle_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `make` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `year` year(4) NOT NULL,
  `license_plate` varchar(20) NOT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mileage` int(11) DEFAULT NULL,
  `last_service_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`vehicle_id`, `user_id`, `make`, `model`, `year`, `license_plate`, `photo_url`, `created_at`, `updated_at`, `mileage`, `last_service_date`) VALUES
(5, 14, 'BMW', 'M3', '2020', 'JVT 201', NULL, '2024-11-30 14:52:48', '2024-12-06 14:49:05', NULL, NULL),
(6, 11, 'Bugatti', 'Veyron', '1999', 'BEN 100', NULL, '2024-12-01 19:06:07', '2024-12-07 16:17:30', NULL, NULL),
(7, 14, 'Pagani', 'Zonda', '2016', 'TGL 153', NULL, '2024-12-01 19:34:56', '2024-12-06 15:45:19', NULL, NULL),
(8, 11, 'Mclaren', 'P1', '2016', 'PDO 120', NULL, '2024-12-05 02:18:01', '2024-12-05 02:18:01', NULL, NULL),
(9, 11, 'Lamborghini', 'Aventador', '2022', 'LRT 323', NULL, '2024-12-09 12:27:36', '2024-12-09 12:27:36', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `technician_id` (`technician_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_appointment_date` (`appointment_date`),
  ADD KEY `fk_package` (`package_id`);

--
-- Indexes for table `appointment_logs`
--
ALTER TABLE `appointment_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_appointment_id` (`appointment_id`);

--
-- Indexes for table `appointment_status_history`
--
ALTER TABLE `appointment_status_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `maintenance_reminders`
--
ALTER TABLE `maintenance_reminders`
  ADD PRIMARY KEY (`reminder_id`),
  ADD KEY `vehicle_id` (`vehicle_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`);

--
-- Indexes for table `service_packages`
--
ALTER TABLE `service_packages`
  ADD PRIMARY KEY (`package_id`);

--
-- Indexes for table `service_reminders`
--
ALTER TABLE `service_reminders`
  ADD PRIMARY KEY (`reminder_id`),
  ADD KEY `vehicle_id` (`vehicle_id`);

--
-- Indexes for table `technician_schedules`
--
ALTER TABLE `technician_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `appointment_logs`
--
ALTER TABLE `appointment_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `appointment_status_history`
--
ALTER TABLE `appointment_status_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance_reminders`
--
ALTER TABLE `maintenance_reminders`
  MODIFY `reminder_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `service_packages`
--
ALTER TABLE `service_packages`
  MODIFY `package_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `service_reminders`
--
ALTER TABLE `service_reminders`
  MODIFY `reminder_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `technician_schedules`
--
ALTER TABLE `technician_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_4` FOREIGN KEY (`technician_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_appointments_packages` FOREIGN KEY (`package_id`) REFERENCES `service_packages` (`package_id`),
  ADD CONSTRAINT `fk_package` FOREIGN KEY (`package_id`) REFERENCES `service_packages` (`package_id`) ON DELETE SET NULL;

--
-- Constraints for table `appointment_logs`
--
ALTER TABLE `appointment_logs`
  ADD CONSTRAINT `appointment_logs_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`),
  ADD CONSTRAINT `appointment_logs_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `appointment_status_history`
--
ALTER TABLE `appointment_status_history`
  ADD CONSTRAINT `appointment_status_history_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`);

--
-- Constraints for table `maintenance_reminders`
--
ALTER TABLE `maintenance_reminders`
  ADD CONSTRAINT `maintenance_reminders_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`);

--
-- Constraints for table `service_reminders`
--
ALTER TABLE `service_reminders`
  ADD CONSTRAINT `service_reminders_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`);

--
-- Constraints for table `technician_schedules`
--
ALTER TABLE `technician_schedules`
  ADD CONSTRAINT `technician_schedules_ibfk_1` FOREIGN KEY (`technician_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

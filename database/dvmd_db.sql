-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 02, 2026 at 03:58 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dvmd_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_announcements`
--

CREATE TABLE `tbl_announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `village_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_announcements`
--

INSERT INTO `tbl_announcements` (`id`, `title`, `message`, `type`, `village_id`, `created_at`) VALUES
(1, 'Flood Warning Issued', 'Heavy rainfall has been recorded in surrounding areas. Residents are advised to stay alert and prepare for possible evacuation.', 'Emergency', NULL, '2025-12-30 00:08:15'),
(2, 'Strong Winds Expected', 'Weather forecast indicates strong winds and thunderstorms later this evening. Secure loose objects around your home.', 'Weather', NULL, '2025-12-30 00:08:15'),
(3, 'Village Clean-Up Campaign', 'A village clean-up campaign will be held this Saturday at 8:00 AM. All residents are encouraged to participate.', 'Event', NULL, '2025-12-30 00:08:15'),
(4, 'Temporary Water Supply Interruption', 'Water supply will be temporarily interrupted tomorrow from 10:00 AM to 4:00 PM due to maintenance works.', 'Info', NULL, '2025-12-30 00:08:15'),
(5, 'Heat Advisory Notice', 'High temperatures are expected over the next few days. Please stay hydrated and avoid outdoor activities during peak hours.', 'Weather', NULL, '2025-12-30 00:08:15'),
(6, 'Emergency Response Drill', 'An emergency response drill will be conducted this Friday at the community hall. This is for preparedness purposes.', 'Event', NULL, '2025-12-30 00:08:15'),
(8, 'test', 'test', 'emergency', NULL, '2025-12-30 15:41:46'),
(9, 'test', 'test', 'emergency', NULL, '2025-12-30 15:41:54'),
(10, 'test', 'test', 'emergency', NULL, '2025-12-30 15:42:10'),
(11, 'test', 'test', 'emergency', NULL, '2025-12-30 15:42:27'),
(12, 'test', 'test', 'emergency', NULL, '2025-12-30 15:42:45'),
(13, 'test', 'test', 'emergency', NULL, '2025-12-30 15:42:56'),
(14, 'test', 'test', 'emergency', NULL, '2025-12-30 15:43:00'),
(15, 'test', 'test', 'emergency', NULL, '2025-12-30 15:43:02'),
(16, 'test', 'test', 'emergency', NULL, '2025-12-30 15:43:05'),
(17, 'test', 'test', 'emergency', NULL, '2025-12-31 00:03:35'),
(18, 'test', 'test', 'emergency', NULL, '2025-12-31 00:04:13'),
(19, 'test', 'test', 'emergency', NULL, '2025-12-31 00:05:10'),
(20, 'test', 'test', 'emergency', NULL, '2025-12-31 00:05:12'),
(21, 'test', 'test', 'emergency', NULL, '2025-12-31 00:05:13'),
(22, 'test', 'test', 'weather', NULL, '2025-12-31 10:11:26'),
(23, 'test', 'test', 'weather', 3, '2026-01-02 05:01:01'),
(24, 'test', 'test', 'event', 1, '2026-01-02 09:20:15'),
(25, 'test', 'test', 'event', 3, '2026-01-02 09:20:15');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_audit_log`
--

CREATE TABLE `tbl_audit_log` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `performed_by` varchar(100) DEFAULT NULL,
  `performed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_districts`
--

CREATE TABLE `tbl_districts` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `latitude` double NOT NULL,
  `longitude` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_districts`
--

INSERT INTO `tbl_districts` (`id`, `name`, `latitude`, `longitude`) VALUES
(1, 'Kubang Pasu', 6.4256, 100.4313),
(2, 'Kota Setar', 6.122603, 100.3690317);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_households`
--

CREATE TABLE `tbl_households` (
  `id` int(11) NOT NULL,
  `villager_id` int(11) NOT NULL,
  `family_group` varchar(10) NOT NULL,
  `family_member` int(11) NOT NULL,
  `sara` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_households`
--

INSERT INTO `tbl_households` (`id`, `villager_id`, `family_group`, `family_member`, `sara`) VALUES
(4, 6, 'B40', 4, 'IDK');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_incidents`
--

CREATE TABLE `tbl_incidents` (
  `id` int(11) NOT NULL,
  `villager_id` int(11) NOT NULL,
  `village_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `latitude` double NOT NULL,
  `longitude` double NOT NULL,
  `image` text NOT NULL,
  `urgency_level` varchar(50) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_incidents`
--

INSERT INTO `tbl_incidents` (`id`, `villager_id`, `village_id`, `type`, `description`, `latitude`, `longitude`, `image`, `urgency_level`, `date_created`, `status`) VALUES
(8, 1, 3, 'Landslide', 'Road completely blocked', 6.2762198, 100.4165733, 'incident_8.png', 'High', '2026-01-01 18:45:29', 'Resolved');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sos`
--

CREATE TABLE `tbl_sos` (
  `id` int(11) NOT NULL,
  `villager_id` int(11) NOT NULL,
  `village_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `latitude` double NOT NULL,
  `longitude` double NOT NULL,
  `image` text NOT NULL,
  `urgency_level` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_sos`
--

INSERT INTO `tbl_sos` (`id`, `villager_id`, `village_id`, `type`, `description`, `latitude`, `longitude`, `image`, `urgency_level`, `status`, `created_at`) VALUES
(1, 1, 1, 'Flood', 'Severe flood reported near river', 3.139, 101.6869, 'incident_10.png', 'Critical', 'Reject', '2025-12-29 22:22:53');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_subdistricts`
--

CREATE TABLE `tbl_subdistricts` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `latitude` double NOT NULL,
  `longitude` double NOT NULL,
  `district_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_subdistricts`
--

INSERT INTO `tbl_subdistricts` (`id`, `name`, `latitude`, `longitude`, `district_id`) VALUES
(1, 'Jitra', 6.26812, 100.42167, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_users`
--

CREATE TABLE `tbl_users` (
  `id` int(11) NOT NULL,
  `role` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `email` varchar(200) NOT NULL,
  `phone` varchar(100) NOT NULL,
  `village_id` int(11) DEFAULT NULL,
  `subdistrict_id` int(11) DEFAULT NULL,
  `district_id` int(11) DEFAULT NULL,
  `password` text NOT NULL,
  `regdate` datetime NOT NULL DEFAULT current_timestamp(),
  `failed_attempts` int(11) DEFAULT 0,
  `lock_until` datetime DEFAULT NULL,
  `otp` varchar(255) DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_users`
--

INSERT INTO `tbl_users` (`id`, `role`, `name`, `email`, `phone`, `village_id`, `subdistrict_id`, `district_id`, `password`, `regdate`, `failed_attempts`, `lock_until`, `otp`, `expired_at`) VALUES
(8, '2', 'Tan Yee Kien', 'yeekientanpro@gmail.com', '0108300288', NULL, NULL, 1, '$2y$10$nctHrZZXbVb7d62xndPEUelWO8t6Y9eDwgWql5oqqd3uvguLMx7RS', '2025-12-22 19:43:46', 0, NULL, '', '0000-00-00 00:00:00'),
(9, '0', 'Eric', 'eric@gmail.com', '0123456789', 3, NULL, NULL, '$2y$10$pYXfZzRpm10s7pBk7U0G7OdsaQQQ50uef42obWPZihGtdD7SuusZ.', '2025-12-28 14:32:40', 0, NULL, '', '2025-12-28 14:32:40'),
(11, '1', 'Lim Jia Ching', 'lim@gmail.com', '0321456987', NULL, 1, NULL, '$2y$10$VDGPwCe/EWhg3eIARy9kFOnzYKziIVg9hqV4AzfIkj0Vea.3g6Hca', '2025-12-31 09:56:22', 0, NULL, NULL, '2025-12-31 09:56:22'),
(12, '2', 'Wee Jun Jeang', 'wee@gmail.com', '0369852147', NULL, NULL, 2, '$2y$10$oDki.2VVqrIG9RVrLRXDqO2C/jYNpCH..WdnavNwE6MO2Ep74e.F2', '2025-12-31 09:59:22', 0, NULL, NULL, '2025-12-31 09:59:22');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_villagers`
--

CREATE TABLE `tbl_villagers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `village_id` int(11) DEFAULT NULL,
  `address` text NOT NULL,
  `password` text NOT NULL,
  `regdate` datetime NOT NULL DEFAULT current_timestamp(),
  `household_id` int(11) DEFAULT NULL,
  `failed_attempts` int(11) DEFAULT NULL,
  `lock_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_villagers`
--

INSERT INTO `tbl_villagers` (`id`, `name`, `email`, `phone`, `village_id`, `address`, `password`, `regdate`, `household_id`, `failed_attempts`, `lock_until`) VALUES
(1, 't', 't@gmail.com', '1', 1, '', '$2y$10$8HHVNHSM1LiUwIqSJGj72OsyhHNHODqnBrai2Avvhrc3XvpcXXueK', '2025-12-28 20:57:46', NULL, NULL, NULL),
(3, 'Bakkien', 'bakkien@gmail.com', '0108300289', 1, '', '$2y$10$fdCd6t9jNs/0OaZpYA5KcuB30H7GpEXnxRlgJGtQayZbmglIDJqgu', '2025-12-30 23:06:11', NULL, NULL, NULL),
(6, 'asd', 'asd@gmail.com', '0147852369', 3, '123, Taman Pasu, 06000, Jitra, Kedah', '$2y$10$CPNnwXe83yh5swIlFDY8COyEwcJ9PcgC7Hc/nyt8kOThe6HkaQOsm', '2026-01-02 06:01:48', 4, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_villages`
--

CREATE TABLE `tbl_villages` (
  `id` int(11) NOT NULL,
  `village_name` varchar(100) NOT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `subdistrict_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_villages`
--

INSERT INTO `tbl_villages` (`id`, `village_name`, `latitude`, `longitude`, `subdistrict_id`) VALUES
(1, 'Taman Bunga', 3.10591, 101.68683, 1),
(3, 'Taman Pasu', 6.2762198, 100.4165733, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_announcements`
--
ALTER TABLE `tbl_announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_announcements_village` (`village_id`);

--
-- Indexes for table `tbl_audit_log`
--
ALTER TABLE `tbl_audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_districts`
--
ALTER TABLE `tbl_districts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_households`
--
ALTER TABLE `tbl_households`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_incidents`
--
ALTER TABLE `tbl_incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_incidents_village` (`village_id`);

--
-- Indexes for table `tbl_sos`
--
ALTER TABLE `tbl_sos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sos_village` (`village_id`);

--
-- Indexes for table `tbl_subdistricts`
--
ALTER TABLE `tbl_subdistricts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_subdistricts_district` (`district_id`);

--
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_users_village` (`village_id`),
  ADD KEY `fk_users_subdistrict` (`subdistrict_id`),
  ADD KEY `fk_users_district` (`district_id`);

--
-- Indexes for table `tbl_villagers`
--
ALTER TABLE `tbl_villagers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_villagers_village` (`village_id`);

--
-- Indexes for table `tbl_villages`
--
ALTER TABLE `tbl_villages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_villages_subdistrict` (`subdistrict_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_announcements`
--
ALTER TABLE `tbl_announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `tbl_audit_log`
--
ALTER TABLE `tbl_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_districts`
--
ALTER TABLE `tbl_districts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_households`
--
ALTER TABLE `tbl_households`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_incidents`
--
ALTER TABLE `tbl_incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tbl_sos`
--
ALTER TABLE `tbl_sos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_subdistricts`
--
ALTER TABLE `tbl_subdistricts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_users`
--
ALTER TABLE `tbl_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tbl_villagers`
--
ALTER TABLE `tbl_villagers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_villages`
--
ALTER TABLE `tbl_villages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_announcements`
--
ALTER TABLE `tbl_announcements`
  ADD CONSTRAINT `fk_announcements_village` FOREIGN KEY (`village_id`) REFERENCES `tbl_villages` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_incidents`
--
ALTER TABLE `tbl_incidents`
  ADD CONSTRAINT `fk_incidents_village` FOREIGN KEY (`village_id`) REFERENCES `tbl_villages` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_sos`
--
ALTER TABLE `tbl_sos`
  ADD CONSTRAINT `fk_sos_village` FOREIGN KEY (`village_id`) REFERENCES `tbl_villages` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_subdistricts`
--
ALTER TABLE `tbl_subdistricts`
  ADD CONSTRAINT `fk_subdistricts_district` FOREIGN KEY (`district_id`) REFERENCES `tbl_districts` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD CONSTRAINT `fk_users_district` FOREIGN KEY (`district_id`) REFERENCES `tbl_districts` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_users_subdistrict` FOREIGN KEY (`subdistrict_id`) REFERENCES `tbl_subdistricts` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_users_village` FOREIGN KEY (`village_id`) REFERENCES `tbl_villages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_villagers`
--
ALTER TABLE `tbl_villagers`
  ADD CONSTRAINT `fk_villagers_village` FOREIGN KEY (`village_id`) REFERENCES `tbl_villages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_villages`
--
ALTER TABLE `tbl_villages`
  ADD CONSTRAINT `fk_villages_subdistrict` FOREIGN KEY (`subdistrict_id`) REFERENCES `tbl_subdistricts` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

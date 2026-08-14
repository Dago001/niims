-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 25, 2025 at 02:26 PM
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
-- Database: `personnel_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `personnel`
--

CREATE TABLE `personnel` (
  `service_number` varchar(20) NOT NULL,
  `surname` varchar(50) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `rank` varchar(20) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `personnel`
--

INSERT INTO `personnel` (`service_number`, `surname`, `firstname`, `middlename`, `rank`, `date_of_birth`, `gender`) VALUES
('28822', 'Success', 'goodness', 'Francis', NULL, '2013-01-15', 'Male'),
('35562', 'DAGOGO', 'GIFT', 'WILFRED', NULL, '1995-07-17', 'Male'),
('66244', 'GRACE', 'HOPE', 'EMMA', 'ACG', '2005-02-08', 'Female'),
('GRACE', 'EZE', 'GRACE', 'MARY', 'ACI', '2005-06-07', 'Male');

-- --------------------------------------------------------

--
-- Table structure for table `postings`
--

CREATE TABLE `postings` (
  `id` int(11) NOT NULL,
  `service_number` varchar(20) NOT NULL,
  `posting_type` enum('Previous','Current') NOT NULL,
  `location` varchar(100) NOT NULL,
  `date_of_posting` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `postings`
--

INSERT INTO `postings` (`id`, `service_number`, `posting_type`, `location`, `date_of_posting`) VALUES
(1, '35562', 'Previous', 'RIVERS COMMAND', '2018-04-04'),
(2, '35562', 'Previous', 'NITSA', '2018-11-24'),
(3, '35562', 'Current', 'SHQ', '2019-02-07'),
(4, '28822', 'Current', 'SERVICE HEADQUATER', '2025-06-09'),
(5, '66244', 'Previous', 'ZONE B', '2011-02-22'),
(6, '66244', 'Previous', 'ZONE H', '2025-06-24'),
(7, '66244', 'Current', 'ZONE F', '2018-10-24'),
(8, 'GRACE', 'Previous', 'ZONE E', '2008-06-10'),
(9, 'GRACE', 'Previous', 'ZONE F', '2024-12-03'),
(10, 'GRACE', 'Current', 'ZONE B', '2025-06-24');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `created_at`) VALUES
(2, 'admin', '$2y$10$WjxN.D2v77xN6ZHDe2M9X.2ZEWTcvsXaF67TXcV29wBGIqRP1/yGq', '2025-06-23 22:51:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `personnel`
--
ALTER TABLE `personnel`
  ADD PRIMARY KEY (`service_number`);

--
-- Indexes for table `postings`
--
ALTER TABLE `postings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_number` (`service_number`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `postings`
--
ALTER TABLE `postings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `postings`
--
ALTER TABLE `postings`
  ADD CONSTRAINT `postings_ibfk_1` FOREIGN KEY (`service_number`) REFERENCES `personnel` (`service_number`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

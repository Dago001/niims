-- Table to track printed ID cards
CREATE TABLE IF NOT EXISTS `tbl_printed_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `serviceNo` varchar(50) NOT NULL,
  `surname` varchar(100) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `middleName` varchar(100) DEFAULT NULL,
  `currentRank` varchar(100) NOT NULL,
  `printed_by` varchar(100) NOT NULL,
  `printed_by_role` varchar(50) NOT NULL,
  `print_date` datetime NOT NULL,
  `print_method` enum('single','bulk') NOT NULL DEFAULT 'single',
  `batch_id` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  PRIMARY KEY (`id`),
  KEY `serviceNo` (`serviceNo`),
  KEY `print_date` (`print_date`),
  KEY `printed_by` (`printed_by`),
  KEY `batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for search performance
ALTER TABLE `tbl_printed_cards` ADD FULLTEXT INDEX `search_idx` (`serviceNo`, `surname`, `firstName`, `middleName`, `currentRank`, `printed_by`);
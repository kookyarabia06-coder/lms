-- PM Training Request Management Tables

-- Create PM Training Requests table
CREATE TABLE IF NOT EXISTS `pm_training_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` varchar(255) NOT NULL,
  `venue` varchar(255) NOT NULL,
  `date_start` date NOT NULL,
  `time_start` time DEFAULT NULL,
  `date_end` date NOT NULL,
  `time_end` time DEFAULT NULL,
  `hospital_order_no` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT 0.00,
  `late_filing` tinyint(1) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `requester_id` int(11) NOT NULL,
  `ptr_file` varchar(255) DEFAULT NULL,
  `attendance_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','complete') DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`requester_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create PM Training Attendance table
CREATE TABLE IF NOT EXISTS `pm_training_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `pm_training_request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `attended` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_attendance` (`pm_training_request_id`, `user_id`),
  FOREIGN KEY (`pm_training_request_id`) REFERENCES `pm_training_requests`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

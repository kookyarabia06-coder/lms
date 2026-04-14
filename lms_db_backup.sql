-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 23, 2026 at 06:52 AM
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
-- Database: `lms_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `last_message_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `proponent_id` int(11) NOT NULL,
  `file_pdf` varchar(255) DEFAULT NULL,
  `file_video` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `summary` varchar(2500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `description`, `thumbnail`, `proponent_id`, `file_pdf`, `file_video`, `created_at`, `expires_at`, `is_active`, `summary`) VALUES
(20, 'Course', 'Lorem Ipsum', NULL, 5, '073dab716b0f7dd3.pdf', NULL, '2026-02-13 05:25:40', '2026-02-13', 1, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris tortor mauris, suscipit id dictum eget, tristique vel purus. Nulla et tortor eleifend, condimentum nulla sit amet, convallis justo. Vivamus lacinia semper nisl, id tincidunt enim faucibus non. Sed diam arcu, lobortis vel rutrum non, finibus fringilla neque. In vulputate mauris nec sapien egestas, ut ullamcorper neque porttitor. Vestibulum rutrum lorem sit amet metus luctus, nec malesuada arcu lacinia. Maecenas at est vitae ante interdum ornare in quis ex. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Morbi tristique pulvinar massa, in iaculis mauris cursus sed. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Nulla vestibulum maximus lacinia.\r\n\r\nEtiam quis pretium est. Mauris eget augue congue, volutpat dui eget, bibendum leo. Vestibulum non mattis enim, non scelerisque ex. In ultrices, urna et vestibulum luctus, magna lorem finibus nunc, non accumsan dui purus ut quam. Fusce vitae molestie tellus, ut varius quam. Nam dignissim elementum tristique. Proin sed est ut risus vehicula dictum a sed enim. Integer tempus dui quis interdum varius.'),
(21, 'Lorem Ipsum', 'Lorem Ipsum', NULL, 5, '953ca1ae64427da4.pdf', '31ad844a6a1d0ccc.mp4', '2026-02-13 05:26:34', '2026-02-28', 1, '\r\nfreestar\r\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris tortor mauris, suscipit id dictum eget, tristique vel purus. Nulla et tortor eleifend, condimentum nulla sit amet, convallis justo. Vivamus lacinia semper nisl, id tincidunt enim faucibus non. Sed diam arcu, lobortis vel rutrum non, finibus fringilla neque. In vulputate mauris nec sapien egestas, ut ullamcorper neque porttitor. Vestibulum rutrum lorem sit amet metus luctus, nec malesuada arcu lacinia. Maecenas at est vitae ante interdum ornare in quis ex. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Morbi tristique pulvinar massa, in iaculis mauris cursus sed. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Nulla vestibulum maximus lacinia.\r\n\r\nEtiam quis pretium est. Mauris eget augue congue, volutpat dui eget, bibendum leo. Vestibulum non mattis enim, non scelerisque ex. In ultrices, urna et vestibulum luctus, magna lorem finibus nunc, non accumsan dui purus ut quam. Fusce vitae molestie tellus, ut varius quam. Nam dignissim elementum tristique. Proin sed est ut risus vehicula dictum a sed enim. Integer tempus dui quis interdum varius.'),
(22, 'proponent', 'proponent', NULL, 8, NULL, 'd5c2cb58d9546ec2.mp4', '2026-02-13 05:27:15', '2026-02-28', 1, '\r\nfreestar\r\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris tortor mauris, suscipit id dictum eget, tristique vel purus. Nulla et tortor eleifend, condimentum nulla sit amet, convallis justo. Vivamus lacinia semper nisl, id tincidunt enim faucibus non. Sed diam arcu, lobortis vel rutrum non, finibus fringilla neque. In vulputate mauris nec sapien egestas, ut ullamcorper neque porttitor. Vestibulum rutrum lorem sit amet metus luctus, nec malesuada arcu lacinia. Maecenas at est vitae ante interdum ornare in quis ex. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Morbi tristique pulvinar massa, in iaculis mauris cursus sed. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Nulla vestibulum maximus lacinia.\r\n\r\nEtiam quis pretium est. Mauris eget augue congue, volutpat dui eget, bibendum leo. Vestibulum non mattis enim, non scelerisque ex. In ultrices, urna et vestibulum luctus, magna lorem finibus nunc, non accumsan dui purus ut quam. Fusce vitae molestie tellus, ut varius quam. Nam dignissim elementum tristique. Proin sed est ut risus vehicula dictum a sed enim. Integer tempus dui quis interdum varius.'),
(23, 'Lorem ipsum', 'Lorem Ipsum', NULL, 8, NULL, '600b74d379fbb888.mp4', '2026-02-13 05:28:11', '2026-02-13', 1, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris tortor mauris, suscipit id dictum eget, tristique vel purus. Nulla et tortor eleifend, condimentum nulla sit amet, convallis justo. Vivamus lacinia semper nisl, id tincidunt enim faucibus non. Sed diam arcu, lobortis vel rutrum non, finibus fringilla neque. In vulputate mauris nec sapien egestas, ut ullamcorper neque porttitor. Vestibulum rutrum lorem sit amet metus luctus, nec malesuada arcu lacinia. Maecenas at est vitae ante interdum ornare in quis ex. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Morbi tristique pulvinar massa, in iaculis mauris cursus sed. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Nulla vestibulum maximus lacinia.\r\n\r\nEtiam quis pretium est. Mauris eget augue congue, volutpat dui eget, bibendum leo. Vestibulum non mattis enim, non scelerisque ex. In ultrices, urna et vestibulum luctus, magna lorem finibus nunc, non accumsan dui purus ut quam. Fusce vitae molestie tellus, ut varius quam. Nam dignissim elementum tristique. Proin sed est ut risus vehicula dictum a sed enim. Integer tempus dui quis interdum varius.'),
(24, 'Lorem Ipsum pro max', 'Lorem Ipsum', NULL, 8, 'c08d5db5752bd128.pdf', NULL, '2026-02-13 05:28:49', '2026-02-28', 1, '\r\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris tortor mauris, suscipit id dictum eget, tristique vel purus. Nulla et tortor eleifend, condimentum nulla sit amet, convallis justo. Vivamus lacinia semper nisl, id tincidunt enim faucibus non. Sed diam arcu, lobortis vel rutrum non, finibus fringilla neque. In vulputate mauris nec sapien egestas, ut ullamcorper neque porttitor. Vestibulum rutrum lorem sit amet metus luctus, nec malesuada arcu lacinia. Maecenas at est vitae ante interdum ornare in quis ex. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Morbi tristique pulvinar massa, in iaculis mauris cursus sed. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Nulla vestibulum maximus lacinia.\r\n\r\nEtiam quis pretium est. Mauris eget augue congue, volutpat dui eget, bibendum leo. Vestibulum non mattis enim, non scelerisque ex. In ultrices, urna et vestibulum luctus, magna lorem finibus nunc, non accumsan dui purus ut quam. Fusce vitae molestie tellus, ut varius quam. Nam dignissim elementum tristique. Proin sed est ut risus vehicula dictum a sed enim. Integer tempus dui quis interdum varius.'),
(25, 'module one', 'uno', NULL, 22, NULL, NULL, '2026-02-18 07:46:38', NULL, 1, 'sinauna'),
(26, 'dataset', 'dataset', NULL, 24, NULL, NULL, '2026-02-20 03:28:06', NULL, 1, 'dataset');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `created_at`) VALUES
(1, 'Anesthetics', '2026-02-19 03:06:57'),
(2, 'Breast Screening', '2026-02-19 03:06:57'),
(3, 'Cardiology', '2026-02-19 03:06:57'),
(4, 'Ear, Nose and Throat (ENT)', '2026-02-19 03:06:57'),
(5, 'Elderly Services Department', '2026-02-19 03:06:57'),
(6, 'Gastroenterology', '2026-02-19 03:06:57'),
(7, 'General Surgery', '2026-02-19 03:06:57'),
(8, 'Gynecology', '2026-02-19 03:06:57');

-- --------------------------------------------------------

--
-- Table structure for table `edit`
--

CREATE TABLE `edit` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `proponent_id` int(11) NOT NULL,
  `file_pdf` varchar(255) DEFAULT NULL,
  `file_video` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `thumbnail` varchar(255) DEFAULT NULL,
  `summary` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `expired_at` date DEFAULT NULL,
  `progress` decimal(5,2) DEFAULT 0.00,
  `status` enum('ongoing','completed','expired') DEFAULT 'ongoing',
  `total_time_seconds` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `user_id`, `course_id`, `enrolled_at`, `completed_at`, `expired_at`, `progress`, `status`, `total_time_seconds`) VALUES
(18, 14, 24, '2026-02-13 05:45:54', NULL, NULL, 294.00, 'ongoing', 294),
(19, 15, 24, '2026-02-13 05:54:18', NULL, NULL, 1.00, 'ongoing', 1),
(20, 16, 22, '2026-02-13 05:54:38', NULL, NULL, 132.00, 'ongoing', 132),
(21, 14, 23, '2026-02-13 06:30:20', NULL, NULL, 5.00, 'ongoing', 5),
(22, 19, 23, '2026-02-13 07:50:55', NULL, NULL, 2.00, 'ongoing', 2),
(23, 17, 22, '2026-02-13 07:55:38', NULL, NULL, 2.00, 'ongoing', 2);

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `file_pdf` varchar(255) DEFAULT NULL,
  `file_video` varchar(255) DEFAULT NULL,
  `ord` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lesson_progress`
--

CREATE TABLE `lesson_progress` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_deleted_sender` tinyint(1) DEFAULT 0,
  `is_deleted_receiver` tinyint(1) DEFAULT 0,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_attachments`
--

CREATE TABLE `message_attachments` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `filepath` varchar(500) NOT NULL,
  `filesize` int(11) NOT NULL,
  `filetype` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_published` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `news`
--

INSERT INTO `news` (`id`, `title`, `body`, `created_by`, `created_at`, `is_published`) VALUES
(1, 'This is News', 'is this new?', 1, '2026-02-03 23:41:35', 1),
(3, 'asdasdad', 'asdasda', 5, '2026-02-12 19:50:05', 1),
(4, 'jakshdkjashfkhakshfkjaksdhjasdhkasdha', 'jdskalhdkhaskhd', 5, '2026-02-12 23:23:28', 1);

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

CREATE TABLE `otp_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `otp` varchar(10) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + interval 10 minute)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `time_logs`
--

CREATE TABLE `time_logs` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `start_ts` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `end_ts` timestamp NOT NULL DEFAULT current_timestamp(),
  `seconds` int(11) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fname` varchar(100) DEFAULT NULL,
  `lname` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `role` enum('admin','proponent','user','superadmin') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `status` enum('pending','confirmed') DEFAULT 'confirmed',
  `message_notifications` tinyint(1) DEFAULT 1,
  `email_notifications` tinyint(1) DEFAULT 1,
  `departments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `fname`, `lname`, `email`, `role`, `created_at`, `updated_at`, `is_verified`, `otp_code`, `otp_expires_at`, `status`, `message_notifications`, `email_notifications`, `departments`) VALUES
(1, 'kooky', '$2y$10$wjiEABSo/nLus0Nno0Y/dODGX6ZmNR6xkvmPGWndxaP4yZgBqpWja', 'Kooky', 'Arabia', 'Kookyarabia07@gmail.com', 'admin', '2026-01-30 01:25:37', NULL, 0, '842486', '2026-02-04 12:00:52', 'confirmed', 1, 1, NULL),
(5, 'alvin', '$2y$10$p.0kdoyIco1ye14NdYTqY.FgdL3UF7Vpd/fo3rcEQEQo/qEhTATlO', 'Alvin', 'Lopez', 'traxcie21@gmail.com', 'admin', '2026-02-04 06:32:23', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(8, 'proponent', '$2y$10$YuqFFhnfrrPuXceFDMaVZugK18qFizJyOxrzKgrQX8nSkWRU4BLgW', 'Mr.', 'Proponent', 'proponent@gmail.com', 'proponent', '2026-02-11 00:43:36', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(14, 'user', '$2y$10$eFkQsneyDNipGemuZI9qpO4HhBC/.Y1XyOKDOSvUn0T6pURNbTize', 'user', 'user', 'user@gmail.com', 'user', '2026-02-13 05:30:06', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(15, 'user1', '$2y$10$GmQcyRr/zPXarLQicX2ifuuZQlw840EoV5nWrac35SRxX6M/LRcnu', 'user1', 'user1', 'user1@gmail.com', 'user', '2026-02-13 05:30:44', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(16, 'user2', '$2y$10$7x9Qw.DMEjDoYiTCwdPBXe1gqXk81rduhSv.3tF7721h3UFdow4km', 'user2', 'user2', 'user2@gmail.com', 'user', '2026-02-13 05:31:07', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(17, 'user4', '$2y$10$eZYCf/oBNINjucMXqZKrBOPfPgFkUB4iEvB8dg8XIHVco7hAByVRO', 'user4', 'user4', 'user4@gmail.com', 'user', '2026-02-13 05:31:26', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(18, 'github', '$2y$10$Q52DI9vwE6ZqrVtdJlbnmevPcBsGJPsC8K5iW5W0YUCCUxewCWVM6', 'F', 'Github', 'fgithub455@gmail.com', 'user', '2026-02-13 05:33:49', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(19, 'user3', '$2y$10$Q0P.dZvXhbXZLaAP/DaF5.BYhIEuycpmzx9TZrx2turG27kRHM2ui', 'user3', 'user3', 'user3@gmail.com', 'user', '2026-02-13 07:41:56', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(22, 'pro', '$2y$10$gpII/P2uzcDch.35MinEve7EO4uQD05eaIkHpTshPmszmeMPArXaO', 'pro', 'pro', 'pro@gmail.com', 'proponent', '2026-02-16 05:15:51', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(24, 'admin', '$2y$10$hG0raIRisWillWRvFq4y9uRtS5yj5q0OF8iv1yVL1cO.TfZiWzGKK', 'admin', 'admin', 'admin@gmail.com', 'admin', '2026-02-16 05:16:40', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(26, 'ako', '$2y$10$bEZ2j/gY4he1JYKK51LkKOpsoXxPxGrfR/f62oPgExoMQp4aTBMjG', 'ako', 'ako', 'ako@gmail.com', 'user', '2026-02-16 07:17:16', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(27, 'aoisuhdasihdashd', '$2y$10$lFcXCZ86cK9tsVQBOZ06uef5hc3RYcgUy339RXU2u8w2ZEklzgYAO', 'kjhsakjhdkasjhdkajsdh', 'kjhaskjdhakdhjk', 'asdjkhasdjkhak@gmail.com', 'user', '2026-02-16 07:20:56', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(31, 'sdasdasdasdasd', '$2y$10$0xMIFwxhImAGl3Brq3vawOb2uSYvBpMLmeHBQSTR5r5HJRz/WAmTm', 'dasdasdasdas', 'dasdasdasd', 'mikaellayap23@gmail.com', 'user', '2026-02-16 08:05:57', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(33, 'superadmin', '$2y$10$9L1NSLJ8xbyIVLvRHRuv7e/jNYOQVvVowcDRH7q07EyYD1FPIgzuq', 'superadmin', 'superadmin', 'superadmin@gmail.com', 'superadmin', '2026-02-16 08:33:09', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(36, 'departexample', '$2y$10$s7/MswvP3azi8xK/J/7LterE.6NYvCw89WSj9Id28yFMYZBK8qAra', 'departexample', 'departexample', 'departexample@gmail.com', 'user', '2026-02-19 00:54:03', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(37, 'dasdhasdkljhasdkjhasdkj', '$2y$10$2NiStqWVhFIGb5hoEFruS.IjfawWKCIhFQjFGcIJlH.Z1PSW2paqm', 'khklasjhdakshdaskdhaskh', 'khdaskdhaskdhasdkjhaskhddkjsahdaskhd', 'asdlkjashdkjashdkjashdjhd@gmail.com', 'user', '2026-02-19 01:07:35', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(40, 'sadasdasdasdaskjdhakjdhaskjdhaskjdh', '$2y$10$B0ocvZq7p/VwTbSokFWkp.bTxz2meTwtiyFOi3T4Mqn70h4UOaxrK', 'khsakhdaksjdhaskjdhkasjdhkj', 'khadkhaskdjhasjkdhaskdh', 'askdjhakdhadshaskj@gmail.com', 'user', '2026-02-19 01:49:34', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(41, 'ooooooooooooooooooooooooooooo', '$2y$10$h/vdSEHpiBfXLe4cjWC44uXFhbxTvEDcubJqolHFCD.muTOB57Hs.', 'oooooooooooooooo', 'ooooooooooooo', 'oooooooooooooo@gmail.com', 'user', '2026-02-19 01:51:22', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(42, 'mmmmmmmmmmmmmmmmm', '$2y$10$H/n3NHJ827wP030Rr5pB1.tWtpMI6RODnAiENeJeI7dBG3D9yOz2S', 'mmmmmmmmmmmmmmmmmmmmmmmmmmmmmmm', 'mmmmmmmmmmmmmmmmm', 'mmmmm@gmail.com', 'user', '2026-02-19 02:44:05', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(43, 'sadasdasdaddasasdsadasd', '$2y$10$LKTbl5/hjoQ4wSaLXpzfdOjXaWA7edzzWlugJj1PTwmkJ2ROKChOG', 'sadasdasdaddasasdsadasd', 'sadasdasdaddasasdsadasd', 'sadlkjsadlksadlkjas@gmail.com', 'user', '2026-02-19 03:58:48', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(44, 'asdasdasdasdasdasdasd', '$2y$10$UGSxa.qcq9Xrl6dd6BnTQ.3FuaPh8vO4.VYSn9dGeF6CaNlRxt1z6', 'dasdasdasdasdasdasdasdasdasd', 'sssssssssssssssssssssssssssssssss', 'dasdasd@gmail.com', 'user', '2026-02-19 23:42:53', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(45, 'oooooooooooooooooooooooooooooooooooooooooooooooo', '$2y$10$1ccOmfld79d89kKj89TuSe416cFQHX1SYPPfqaZLgWTOXA3gTiJPa', 'ooooooooooooooooo', 'oooooooooooooooooooooooooooo', 'ooooooooooooooooooooooooooooooooooooo@gmail.com', 'user', '2026-02-19 23:54:12', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(46, 'mmmmmmmmmmmmmmmmmmmmm', '$2y$10$4pVqUvpFZJzuXyqTUMhZvePWlI7l41oOBfN2nHFj8rHNliegYQkie', 'mmmmmmmmmmmmmmmmmm', 'mmmmmmmmmmmmmm', 'mmmmmmmmmmmmmmmmmm@gmail.com', 'user', '2026-02-19 23:55:49', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(47, 'nnnnnnnnnnnnnn', '$2y$10$2lTtYPJmz0.B32fishReAuZZo2C6oBpD/Mkm4DZVUWXhpgJKyHlqq', 'nnnnnnnnnnnn', 'nnnnnnnnn', 'nnnnnnnnnnnnn@gmail.com', 'user', '2026-02-19 23:57:31', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(48, 'sssssssssssssssssss', '$2y$10$7n9VY0jw/3cLGqdu8y7DteDV.mIcgLa7iMvq/BzaAZXNpr/zlqfny', 'sssssssssssssss', 'ssssssssssssssss', 'ssssss@gmail.com', 'user', '2026-02-19 23:57:51', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(49, 'pppppppppppppppppp', '$2y$10$MmJXtTGj2rKwnr8uJbzNJuWvaBkEKJQIs4rzcKqRfE/.uEvwHpPv6', 'ppppppppppppppppp', 'pppppppppp', 'pppppppp@gmail.com', 'user', '2026-02-20 01:24:23', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(50, 'xxxxxxxxxxxxxxxxxxxx', '$2y$10$RwIHtfcGQLiy/WoZtf/kYeFrVmqWd/cSmVKNWAEuwmcohHzv1Y01a', 'xxxxxxxxxxxxxxxxxxxxx', 'xxxxxxxxxxxxxxxxxxxx', 'xxxxxxxxx@gmail.com', 'user', '2026-02-20 01:26:19', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(51, 'lllllllllllllllllllllll', '$2y$10$bF46YIrnudYSh2pBW46dkeUwbx68/ZuVBVP4V1oA9sP.4jQNwRDOO', 'lllllllllllllllllllll', 'lllllllllllllllllllll', 'lllllllll@gmail.com', 'user', '2026-02-20 01:29:40', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(52, 'jhhhhhhhhhhhhhhhgjhgjhgjhgjhgjh', '$2y$10$3/dZiD8GRJNbc/L.AZ4INeEo87D75LwRm7aF86YGf/Q5f1mDRdogS', 'lkjfdsljfsdlkfjlk', 'klfjdslkfjsdlkfj', 'klsdjfljksdflkj@gmail.com', 'user', '2026-02-20 02:26:46', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(53, 'asdkj ashdaskhdakshdakshdashkdk', '$2y$10$VlIeIOjqzjARuchaaPLcZOznWMNT1uFvjVfFhobplVQirEf5zM6oi', 'khaskhdkashdakshdaskjdhaskdjh', 'dashkasdhkasdhkj', 'hdlaksjdhalshdkajsdhaksjdh@gmail.com', 'user', '2026-02-20 02:34:08', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(54, 'dpttttttt', '$2y$10$IXUQ4yvLvsdBQy2Z2peRMOHU3/gb1gFfeMruSS4Gf5QZ58dOq0pz.', 'dpttttttt', 'dpttttttt', 'dpttttttt@gmail.com', 'user', '2026-02-20 03:01:39', NULL, 0, NULL, NULL, 'confirmed', 1, 1, '8'),
(55, 'sssssssssssssssssssssssssssssss', '$2y$10$JcnRbXjL2Vy6KPC0REKwbud1IDkQz148y2MwBuz.j9V8XEtcJABBW', 'sssssssssssss', 'sssssssssssssssssssss', 'sssssssssssssssss@gmail.com', 'user', '2026-02-20 03:02:34', NULL, 0, NULL, NULL, 'confirmed', 1, 1, ''),
(56, 'zssssssssssssssssssss', '$2y$10$a4V87ZtfEDO0Lq7X8Jbxku4pmUN/AYzUHgZHI/F8FqhfqwfSWUEK2', 'sssssssssssssssssssssssssss', 'ssssssssssssssssssss', 'ssssssssssscccccc@gmail.com', 'user', '2026-02-20 03:13:54', NULL, 0, NULL, NULL, 'confirmed', 1, 1, '2'),
(57, 'asdasdasdad', '$2y$10$yVrIrlM/nktvgy/gmDjDYuDZOXX/3Q1wy3vclx9yN2l1st.7FCege', 'asdasdasdasdasd', 'asdasdasdasd', 'gplankton1@gmail.com', 'user', '2026-02-20 05:22:25', NULL, 0, NULL, NULL, 'confirmed', 1, 1, '7'),
(58, 'test', '$2y$10$xBE0X7W4Gr1BvpsQXfoQYe5Kyz8fl.syAbdKHrSyBg68pWuTNgtJO', 'Gerry', 'Tigolo', 'gerrytigolo101@gmail.com', 'user', '2026-02-20 07:47:30', NULL, 0, NULL, NULL, 'confirmed', 1, 1, '1');

-- --------------------------------------------------------

--
-- Table structure for table `user_departments`
--

CREATE TABLE `user_departments` (
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_conversation` (`user1_id`,`user2_id`),
  ADD KEY `user2_id` (`user2_id`),
  ADD KEY `last_message_id` (`last_message_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proponent_id` (`proponent_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `edit`
--
ALTER TABLE `edit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proponent_id` (`proponent_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_user_course` (`user_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `lesson_progress`
--
ALTER TABLE `lesson_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_enroll_lesson` (`enrollment_id`,`lesson_id`),
  ADD KEY `lesson_id` (`lesson_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_receiver` (`receiver_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `message_attachments`
--
ALTER TABLE `message_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `message_id` (`message_id`);

--
-- Indexes for table `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `time_logs`
--
ALTER TABLE `time_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enrollment_id` (`enrollment_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_departments`
--
ALTER TABLE `user_departments`
  ADD PRIMARY KEY (`user_id`,`department_id`),
  ADD KEY `department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `edit`
--
ALTER TABLE `edit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lesson_progress`
--
ALTER TABLE `lesson_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_attachments`
--
ALTER TABLE `message_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `time_logs`
--
ALTER TABLE `time_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_3` FOREIGN KEY (`last_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`proponent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `edit`
--
ALTER TABLE `edit`
  ADD CONSTRAINT `edit_ibfk_1` FOREIGN KEY (`proponent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lesson_progress`
--
ALTER TABLE `lesson_progress`
  ADD CONSTRAINT `lesson_progress_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lesson_progress_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`parent_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_attachments`
--
ALTER TABLE `message_attachments`
  ADD CONSTRAINT `message_attachments_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `time_logs`
--
ALTER TABLE `time_logs`
  ADD CONSTRAINT `time_logs_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_departments`
--
ALTER TABLE `user_departments`
  ADD CONSTRAINT `user_departments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_departments_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

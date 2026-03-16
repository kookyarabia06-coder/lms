-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 13, 2026 at 03:04 AM
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
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `passing_score` int(11) DEFAULT 70,
  `time_limit` int(11) DEFAULT NULL COMMENT 'Time limit in minutes',
  `attempts_allowed` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessments`
--

INSERT INTO `assessments` (`id`, `course_id`, `title`, `description`, `passing_score`, `time_limit`, `attempts_allowed`, `created_at`, `updated_at`) VALUES
(6, 44, 'Powerpoint', 'PowerpointPowerpoint', 70, 0, 0, '2026-03-12 02:44:29', '2026-03-13 00:28:06'),
(8, 47, 'MS WORD', 'MS WORDMS WORDMS WORDMS WORD', 70, 0, 1, '2026-03-12 03:17:14', NULL),
(9, 46, 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', 70, 0, 0, '2026-03-12 03:17:52', '2026-03-12 05:12:30');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_answers`
--

CREATE TABLE `assessment_answers` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option_id` int(11) DEFAULT NULL,
  `essay_answer` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `points_earned` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assessment_attempts`
--

CREATE TABLE `assessment_attempts` (
  `id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `status` enum('in_progress','completed') DEFAULT 'in_progress',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `passed` tinyint(1) DEFAULT 0,
  `answers` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assessment_options`
--

CREATE TABLE `assessment_options` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `order_number` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessment_options`
--

INSERT INTO `assessment_options` (`id`, `question_id`, `option_text`, `is_correct`, `order_number`) VALUES
(39, 11, 'MS WORD', 0, 0),
(40, 11, 'MS WORDMS WORD', 1, 1),
(41, 11, 'MS WORDMS WORDMS WORD', 0, 2),
(42, 11, 'MS WORDMS WORDMS WORDMS WORD', 0, 3),
(43, 12, 'MS WORDMS WORDMS WORDMS WORDMS WORD', 0, 0),
(44, 12, 'MS WORDMS WORDMS WORDMS WORD', 0, 1),
(45, 12, 'MS WORDMS WORDMS WORD', 1, 2),
(46, 12, 'MS WORD', 0, 3),
(63, 17, 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', 0, 0),
(64, 17, 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', 0, 1),
(65, 17, 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', 0, 2),
(66, 17, 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', 1, 3),
(79, 21, 'Powerpoint', 0, 0),
(80, 21, 'PowerpointPowerpoint', 0, 1),
(81, 21, 'PowerpointPowerpointPowerpoint', 1, 2),
(82, 21, 'PowerpointPowerpointPowerpointPowerpoint', 0, 3),
(83, 22, 'PowerpointPowerpoint', 1, 0),
(84, 22, 'Powerpoint', 0, 1),
(85, 22, 'PowerpointPowerpointPowerpoint', 0, 2),
(86, 22, 'PowerpointPowerpointPowerpointPowerpoint', 0, 3),
(87, 23, 'PowerpointPowerpointPowerpoint', 0, 0),
(88, 23, 'Powerpoint', 0, 1),
(89, 23, 'PowerpointPowerpoint', 0, 2),
(90, 23, 'Regine Velasquez', 1, 3);

-- --------------------------------------------------------

--
-- Table structure for table `assessment_questions`
--

CREATE TABLE `assessment_questions` (
  `id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','true_false','essay') DEFAULT 'multiple_choice',
  `points` int(11) DEFAULT 1,
  `order_number` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessment_questions`
--

INSERT INTO `assessment_questions` (`id`, `assessment_id`, `question_text`, `question_type`, `points`, `order_number`, `created_at`) VALUES
(11, 8, 'MS WORDMS WORD', 'multiple_choice', 1, 0, '2026-03-12 03:17:14'),
(12, 8, 'MS WORDMS WORDMS WORD', 'multiple_choice', 1, 1, '2026-03-12 03:17:14'),
(17, 9, 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', 'multiple_choice', 1, 0, '2026-03-12 05:12:30'),
(21, 6, 'PowerpointPowerpointPowerpoint', 'multiple_choice', 1, 0, '2026-03-13 00:28:06'),
(22, 6, 'PowerpointPowerpoint', 'multiple_choice', 1, 1, '2026-03-13 00:28:06'),
(23, 6, 'Powerpoint', 'multiple_choice', 1, 2, '2026-03-13 00:28:06');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `table_name` varchar(255) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `old_data` longtext DEFAULT NULL,
  `new_data` longtext DEFAULT NULL,
  `changed_fields` longtext DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `table_name`, `record_id`, `action`, `old_data`, `new_data`, `changed_fields`, `user_id`, `created_at`) VALUES
(1, 'courses', 35, 'UPDATE', '{\"title\": \"pisonet\", \"description\": \"pisonet\", \"summary\": \"pisonet\", \"thumbnail\": null, \"file_pdf\": \"dd92aa009d315982.pdf\", \"file_video\": \"2448566f2638d230.mp4\", \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"pizat\", \"description\": \"pisonet\", \"summary\": \"pisonet\", \"thumbnail\": null, \"file_pdf\": \"dd92aa009d315982.pdf\", \"file_video\": \"2448566f2638d230.mp4\", \"expires_at\": null, \"is_active\": 1}', '[\"title\"]', NULL, '2026-03-04 08:52:00'),
(2, 'courses', 35, 'UPDATE', '{\"title\": \"pizat\", \"description\": \"pisonet\", \"summary\": \"pisonet\", \"thumbnail\": null, \"file_pdf\": \"dd92aa009d315982.pdf\", \"file_video\": \"2448566f2638d230.mp4\", \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"kuyarenzamoymanok\", \"description\": \"kuyarenzamoymanok\", \"summary\": \"kuyarenzamoymanok\", \"thumbnail\": null, \"file_pdf\": \"dd92aa009d315982.pdf\", \"file_video\": \"2448566f2638d230.mp4\", \"expires_at\": null, \"is_active\": 1}', '[\"title\", \"description\", \"summary\"]', NULL, '2026-03-05 00:25:38'),
(3, 'courses', 35, 'UPDATE', '{\"title\": \"kuyarenzamoymanok\", \"description\": \"kuyarenzamoymanok\", \"summary\": \"kuyarenzamoymanok\", \"thumbnail\": null, \"file_pdf\": \"dd92aa009d315982.pdf\", \"file_video\": \"2448566f2638d230.mp4\", \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"kuyarenzamoybisaya\", \"description\": \"kuyarenzamoybisaya\", \"summary\": \"kuyarenzamoybisaya\", \"thumbnail\": null, \"file_pdf\": \"dd92aa009d315982.pdf\", \"file_video\": \"2448566f2638d230.mp4\", \"expires_at\": null, \"is_active\": 1}', '[\"title\", \"description\", \"summary\"]', NULL, '2026-03-05 00:29:34'),
(4, 'courses', 36, 'INSERT', NULL, '{\"title\": \"test audit\", \"description\": \"test audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audit\", \"summary\": \"test audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audit\", \"thumbnail\": \"cd235f4e00d2d19f.png\", \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 33, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, '2026-03-05 01:36:31'),
(5, 'courses', 37, 'INSERT', NULL, '{\"title\": \"test course\", \"description\": \"test coursetest coursetest coursetest course\", \"summary\": \"test coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest course\", \"thumbnail\": \"e1aeeab4c88d3b0b.png\", \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 22, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, '2026-03-05 01:38:46'),
(6, 'courses', 25, 'UPDATE', '{\"title\": \"module one\", \"description\": \"uno\", \"summary\": \"sinauna\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"maosdnasbfewdas\", \"description\": \"maosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdas\", \"summary\": \"sinaunamaosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdas\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '[\"title\", \"description\", \"summary\"]', NULL, '2026-03-05 01:44:28'),
(7, 'courses', 37, 'UPDATE', '{\"title\": \"test course\", \"description\": \"test coursetest coursetest coursetest course\", \"summary\": \"test coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest coursetest course\", \"thumbnail\": \"e1aeeab4c88d3b0b.png\", \"file_pdf\": null, \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"PLSSSSWORK\", \"description\": \"PLSSSSWORKPLSSSSWORK\", \"summary\": \"PLSSSSWORKPLSSSSWORKPLSSSSWORKPLSSSSWORKPLSSSSWORKPLSSSSWORKPLSSSSWORKPLSSSSWORK\", \"thumbnail\": \"e1aeeab4c88d3b0b.png\", \"file_pdf\": null, \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '[\"title\", \"description\", \"summary\"]', NULL, '2026-03-05 01:45:28'),
(8, 'courses', 37, 'UPDATE', '{\"title\": \"PLSSSSWORK\", \"description\": \"PLSSSSWORKPLSSSSWORK\", \"summary\": \"PLSSSSWORKPLSSSSWORKPLSSSSWORKPLSSSSWORKPLSSSSWORKPLSSSSWORKPLSSSSWORKPLSSSSWORK\", \"thumbnail\": \"e1aeeab4c88d3b0b.png\", \"file_pdf\": null, \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"PLSSSSWORK\", \"description\": \"PLSSSSWORK\", \"summary\": \"PLSSSSWORK\", \"thumbnail\": \"e1aeeab4c88d3b0b.png\", \"file_pdf\": null, \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '[\"description\", \"summary\"]', NULL, '2026-03-05 02:34:11'),
(9, 'courses', 37, 'UPDATE', '{\"title\": \"PLSSSSWORK\", \"description\": \"PLSSSSWORK\", \"summary\": \"PLSSSSWORK\", \"thumbnail\": \"e1aeeab4c88d3b0b.png\", \"file_pdf\": null, \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"PLSSSSWORK\", \"description\": \"PLSSSSWORK\", \"summary\": \"PLSSSSWORK\", \"thumbnail\": \"50098616162a197f.png\", \"file_pdf\": \"b7574f35ec2852a7.pdf\", \"file_video\": \"6fd0a1ee54dbbf55.mp4\", \"expires_at\": null, \"is_active\": 1}', '[\"thumbnail\", \"file_pdf\", \"file_video\"]', NULL, '2026-03-05 02:41:38'),
(10, 'courses', 37, 'DELETE', '{\"title\": \"PLSSSSWORK\", \"description\": \"PLSSSSWORK\", \"summary\": \"PLSSSSWORK\", \"thumbnail\": \"50098616162a197f.png\", \"file_pdf\": \"b7574f35ec2852a7.pdf\", \"file_video\": \"6fd0a1ee54dbbf55.mp4\", \"proponent_id\": 22, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:12:35'),
(11, 'courses', 25, 'DELETE', '{\"title\": \"maosdnasbfewdas\", \"description\": \"maosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdas\", \"summary\": \"sinaunamaosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdasmaosdnasbfewdas\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 22, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:12:45'),
(12, 'courses', 35, 'DELETE', '{\"title\": \"kuyarenzamoybisaya\", \"description\": \"kuyarenzamoybisaya\", \"summary\": \"kuyarenzamoybisaya\", \"thumbnail\": null, \"file_pdf\": \"dd92aa009d315982.pdf\", \"file_video\": \"2448566f2638d230.mp4\", \"proponent_id\": 67, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:13:56'),
(13, 'courses', 30, 'DELETE', '{\"title\": \"Course\", \"description\": \"Lorem Ipsum\", \"summary\": \"ggggggggggggggggggggg\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 33, \"expires_at\": \"2026-02-13\", \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:14:10'),
(14, 'courses', 31, 'DELETE', '{\"title\": \"Course\", \"description\": \"Lorem Ipsum\", \"summary\": \"ggggggggggggggggggggg\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 24, \"expires_at\": \"2026-02-13\", \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:14:19'),
(15, 'courses', 27, 'DELETE', '{\"title\": \"sdadadadasdsadsassssssssssssssssssssssssssssssssssssssssssssss\", \"description\": \"sdadadadasdsadsassssssssssssssssssssssssssssssssssssssssssssss\", \"summary\": \"nakopo\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 33, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:14:21'),
(16, 'courses', 36, 'DELETE', '{\"title\": \"test audit\", \"description\": \"test audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audit\", \"summary\": \"test audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audittest audit\", \"thumbnail\": \"cd235f4e00d2d19f.png\", \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 33, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:14:23'),
(17, 'courses', 34, 'DELETE', '{\"title\": \"today\", \"description\": \"tommy\", \"summary\": \"cok\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 24, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:14:26'),
(18, 'courses', 33, 'DELETE', '{\"title\": \"PLSSSSWORK\", \"description\": \"PLSSSSWORK\", \"summary\": \"PLSSSSWORK\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 22, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:14:28'),
(19, 'courses', 32, 'DELETE', '{\"title\": \"main admin\", \"description\": \"main admin\", \"summary\": \"main admin\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 24, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:14:29'),
(20, 'courses', 29, 'DELETE', '{\"title\": \"ccccccccccccccccccc\", \"description\": \"cccccccccccccccccccccc\", \"summary\": \"ssssssssssssssssssssssss\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 33, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:14:31'),
(21, 'courses', 28, 'DELETE', '{\"title\": \"trailtrial\", \"description\": \"trailtrial\", \"summary\": \"trailtrialtrailtrialtrailtrialtrailtrialtrailtrialtrailtrialtrailtrial\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 33, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:14:33'),
(22, 'courses', 26, 'DELETE', '{\"title\": \"dataset\", \"description\": \"dataset\", \"summary\": \"dataset\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 24, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:14:35'),
(23, 'courses', 24, 'DELETE', '{\"title\": \"Lorem Ipsum pro max\", \"description\": \"Lorem Ipsum\", \"summary\": \"\\r\\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris tortor mauris, suscipit id dictum eget, tristique vel purus. Nulla et tortor eleifend, condimentum nulla sit amet, convallis justo. Vivamus lacinia semper nisl, id tincidunt enim faucibus non. Sed diam arcu, lobortis vel rutrum non, finibus fringilla neque. In vulputate mauris nec sapien egestas, ut ullamcorper neque porttitor. Vestibulum rutrum lorem sit amet metus luctus, nec malesuada arcu lacinia. Maecenas at est vitae ante interdum ornare in quis ex. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Morbi tristique pulvinar massa, in iaculis mauris cursus sed. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Nulla vestibulum maximus lacinia.\\r\\n\\r\\nEtiam quis pretium est. Mauris eget augue congue, volutpat dui eget, bibendum leo. Vestibulum non mattis enim, non scelerisque ex. In ultrices, urna et vestibulum luctus, magna lorem finibus nunc, non accumsan dui purus ut quam. Fusce vitae molestie tellus, ut varius quam. Nam dignissim elementum tristique. Proin sed est ut risus vehicula dictum a sed enim. Integer tempus dui quis interdum varius.\", \"thumbnail\": null, \"file_pdf\": \"c08d5db5752bd128.pdf\", \"file_video\": null, \"proponent_id\": 8, \"expires_at\": \"2026-02-28\", \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:14:38'),
(24, 'courses', 23, 'DELETE', '{\"title\": \"Lorem ipsum\", \"description\": \"Lorem Ipsum\", \"summary\": \"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris tortor mauris, suscipit id dictum eget, tristique vel purus. Nulla et tortor eleifend, condimentum nulla sit amet, convallis justo. Vivamus lacinia semper nisl, id tincidunt enim faucibus non. Sed diam arcu, lobortis vel rutrum non, finibus fringilla neque. In vulputate mauris nec sapien egestas, ut ullamcorper neque porttitor. Vestibulum rutrum lorem sit amet metus luctus, nec malesuada arcu lacinia. Maecenas at est vitae ante interdum ornare in quis ex. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Morbi tristique pulvinar massa, in iaculis mauris cursus sed. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Nulla vestibulum maximus lacinia.\\r\\n\\r\\nEtiam quis pretium est. Mauris eget augue congue, volutpat dui eget, bibendum leo. Vestibulum non mattis enim, non scelerisque ex. In ultrices, urna et vestibulum luctus, magna lorem finibus nunc, non accumsan dui purus ut quam. Fusce vitae molestie tellus, ut varius quam. Nam dignissim elementum tristique. Proin sed est ut risus vehicula dictum a sed enim. Integer tempus dui quis interdum varius.\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": \"600b74d379fbb888.mp4\", \"proponent_id\": 8, \"expires_at\": \"2026-02-13\", \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:14:39'),
(25, 'courses', 22, 'DELETE', '{\"title\": \"proponent\", \"description\": \"proponent\", \"summary\": \"\\r\\nfreestar\\r\\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris tortor mauris, suscipit id dictum eget, tristique vel purus. Nulla et tortor eleifend, condimentum nulla sit amet, convallis justo. Vivamus lacinia semper nisl, id tincidunt enim faucibus non. Sed diam arcu, lobortis vel rutrum non, finibus fringilla neque. In vulputate mauris nec sapien egestas, ut ullamcorper neque porttitor. Vestibulum rutrum lorem sit amet metus luctus, nec malesuada arcu lacinia. Maecenas at est vitae ante interdum ornare in quis ex. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Morbi tristique pulvinar massa, in iaculis mauris cursus sed. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Nulla vestibulum maximus lacinia.\\r\\n\\r\\nEtiam quis pretium est. Mauris eget augue congue, volutpat dui eget, bibendum leo. Vestibulum non mattis enim, non scelerisque ex. In ultrices, urna et vestibulum luctus, magna lorem finibus nunc, non accumsan dui purus ut quam. Fusce vitae molestie tellus, ut varius quam. Nam dignissim elementum tristique. Proin sed est ut risus vehicula dictum a sed enim. Integer tempus dui quis interdum varius.\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": \"d5c2cb58d9546ec2.mp4\", \"proponent_id\": 8, \"expires_at\": \"2026-02-28\", \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:14:39'),
(26, 'courses', 21, 'DELETE', '{\"title\": \"Lorem Ipsum\", \"description\": \"Lorem Ipsum\", \"summary\": \"\\r\\nfreestar\\r\\nLorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris tortor mauris, suscipit id dictum eget, tristique vel purus. Nulla et tortor eleifend, condimentum nulla sit amet, convallis justo. Vivamus lacinia semper nisl, id tincidunt enim faucibus non. Sed diam arcu, lobortis vel rutrum non, finibus fringilla neque. In vulputate mauris nec sapien egestas, ut ullamcorper neque porttitor. Vestibulum rutrum lorem sit amet metus luctus, nec malesuada arcu lacinia. Maecenas at est vitae ante interdum ornare in quis ex. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Morbi tristique pulvinar massa, in iaculis mauris cursus sed. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Nulla vestibulum maximus lacinia.\\r\\n\\r\\nEtiam quis pretium est. Mauris eget augue congue, volutpat dui eget, bibendum leo. Vestibulum non mattis enim, non scelerisque ex. In ultrices, urna et vestibulum luctus, magna lorem finibus nunc, non accumsan dui purus ut quam. Fusce vitae molestie tellus, ut varius quam. Nam dignissim elementum tristique. Proin sed est ut risus vehicula dictum a sed enim. Integer tempus dui quis interdum varius.\", \"thumbnail\": null, \"file_pdf\": \"953ca1ae64427da4.pdf\", \"file_video\": \"31ad844a6a1d0ccc.mp4\", \"proponent_id\": 5, \"expires_at\": \"2026-02-28\", \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:14:40'),
(27, 'courses', 20, 'DELETE', '{\"title\": \"Course\", \"description\": \"Lorem Ipsum\", \"summary\": \"ggggggggggggggggggggg\", \"thumbnail\": null, \"file_pdf\": \"073dab716b0f7dd3.pdf\", \"file_video\": null, \"proponent_id\": 5, \"expires_at\": \"2026-02-13\", \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 03:14:40'),
(28, 'courses', 38, 'INSERT', NULL, '{\"title\": \"EXAMPLE\", \"description\": \"EXAMPLE\", \"summary\": \"EXAMPLEEXAMPLEEXAMPLE\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 33, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, '2026-03-05 03:14:56'),
(29, 'courses', 39, 'INSERT', NULL, '{\"title\": \"example2\", \"description\": \"example2\", \"summary\": \"example2\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 33, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, '2026-03-05 03:15:16'),
(30, 'courses', 39, 'DELETE', '{\"title\": \"example2\", \"description\": \"example2\", \"summary\": \"example2\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 33, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-05 07:19:21'),
(31, 'courses', 40, 'INSERT', NULL, '{\"title\": \"pogina kurso\", \"description\": \"pogina kurso\", \"summary\": \"pogina kurso\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 22, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, '2026-03-05 07:20:09'),
(32, 'courses', 40, 'UPDATE', '{\"title\": \"pogina kurso\", \"description\": \"pogina kurso\", \"summary\": \"pogina kurso\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"\", \"description\": null, \"summary\": null, \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '[\"title\", \"description\", \"summary\"]', NULL, '2026-03-05 07:29:40'),
(33, 'courses', 41, 'INSERT', NULL, '{\"title\": \"sadasdasdasd\", \"description\": \"asdasdasdasdas\", \"summary\": \"dasdasdasd\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 33, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, '2026-03-05 07:32:37'),
(34, 'courses', 42, 'INSERT', NULL, '{\"title\": \"qweqweqwe\", \"description\": \"qweqweqwe\", \"summary\": \"qweqweqweqweqweqweqweqweqweqweqweqweqweqweqwe\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 5, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, '2026-03-05 08:30:40'),
(35, 'courses', 40, 'UPDATE', '{\"title\": \"\", \"description\": null, \"summary\": null, \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"Microsoft Powerpoint\", \"description\": \"for creating presentation\", \"summary\": \"Microsoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft Powerpoint\", \"thumbnail\": \"5ad2543daa240b1a.png\", \"file_pdf\": \"914521970748b621.pdf\", \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '[\"title\", \"description\", \"summary\", \"thumbnail\", \"file_pdf\"]', NULL, '2026-03-11 01:39:27'),
(36, 'courses', 43, 'INSERT', NULL, '{\"title\": \"MS Word\", \"description\": \"MS WordMS Word\", \"summary\": \"MS WordMS WordMS Word\", \"thumbnail\": \"feefeaf3fbd48e1b.png\", \"file_pdf\": \"72d755939d0b9f3f.pdf\", \"file_video\": null, \"proponent_id\": 24, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, '2026-03-11 01:58:07'),
(37, 'courses', 44, 'INSERT', NULL, '{\"title\": \"MS Powerpoint\", \"description\": \"MS PowerpointMS Powerpoint\", \"summary\": \"MS PowerpointMS PowerpointMS Powerpoint\", \"thumbnail\": \"787649e329f7066e.png\", \"file_pdf\": \"2511cc375026c19b.pdf\", \"file_video\": null, \"proponent_id\": 22, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, '2026-03-11 01:59:03'),
(38, 'courses', 40, 'UPDATE', '{\"title\": \"Microsoft Powerpoint\", \"description\": \"for creating presentation\", \"summary\": \"Microsoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft Powerpoint\", \"thumbnail\": \"5ad2543daa240b1a.png\", \"file_pdf\": \"914521970748b621.pdf\", \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"Microsoft Powerpointsssssssss\", \"description\": \"for creating presentation\", \"summary\": \"Microsoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft Powerpoint\", \"thumbnail\": \"5ad2543daa240b1a.png\", \"file_pdf\": \"914521970748b621.pdf\", \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '[\"title\"]', NULL, '2026-03-11 01:59:57'),
(39, 'courses', 45, 'INSERT', NULL, '{\"title\": \"Republic Act No. 11313\", \"description\": \"Republic Act No. 11313Republic Act No. 11313\", \"summary\": \"Republic Act No. 11313Republic Act No. 11313Republic Act No. 11313Republic Act No. 11313\", \"thumbnail\": null, \"file_pdf\": \"2d522d1e8f7e388d.pdf\", \"file_video\": null, \"proponent_id\": 33, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, '2026-03-11 02:01:33'),
(40, 'courses', 40, 'UPDATE', '{\"title\": \"Microsoft Powerpointsssssssss\", \"description\": \"for creating presentation\", \"summary\": \"Microsoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft Powerpoint\", \"thumbnail\": \"5ad2543daa240b1a.png\", \"file_pdf\": \"914521970748b621.pdf\", \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"Microsoft Powerpointsssssssssssssssssssssssssssssssssssss\", \"description\": \"for creating presentation\", \"summary\": \"Microsoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft Powerpoint\", \"thumbnail\": \"5ad2543daa240b1a.png\", \"file_pdf\": \"914521970748b621.pdf\", \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '[\"title\"]', NULL, '2026-03-11 02:20:08'),
(41, 'courses', 38, 'DELETE', '{\"title\": \"EXAMPLE\", \"description\": \"EXAMPLE\", \"summary\": \"EXAMPLEEXAMPLEEXAMPLE\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 33, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-11 05:36:21'),
(42, 'courses', 42, 'DELETE', '{\"title\": \"qweqweqwe\", \"description\": \"qweqweqwe\", \"summary\": \"qweqweqweqweqweqweqweqweqweqweqweqweqweqweqwe\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 5, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-11 05:36:25'),
(43, 'courses', 41, 'DELETE', '{\"title\": \"sadasdasdasd\", \"description\": \"asdasdasdasdas\", \"summary\": \"dasdasdasd\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 33, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-11 05:36:28'),
(44, 'courses', 40, 'UPDATE', '{\"title\": \"Microsoft Powerpointsssssssssssssssssssssssssssssssssssss\", \"description\": \"for creating presentation\", \"summary\": \"Microsoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft Powerpoint\", \"thumbnail\": \"5ad2543daa240b1a.png\", \"file_pdf\": \"914521970748b621.pdf\", \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"Microsoft Powerpoints\", \"description\": \"for creating presentation\", \"summary\": \"Microsoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft Powerpoint\", \"thumbnail\": \"5ad2543daa240b1a.png\", \"file_pdf\": \"914521970748b621.pdf\", \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '[\"title\"]', NULL, '2026-03-11 06:03:56'),
(45, 'courses', 40, 'UPDATE', '{\"title\": \"Microsoft Powerpoints\", \"description\": \"for creating presentation\", \"summary\": \"Microsoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft Powerpoint\", \"thumbnail\": \"5ad2543daa240b1a.png\", \"file_pdf\": \"914521970748b621.pdf\", \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"Microsoft Powerpoints\", \"description\": \"for creating presentation\", \"summary\": \"Microsoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft Powerpoint\", \"thumbnail\": \"5ad2543daa240b1a.png\", \"file_pdf\": \"914521970748b621.pdf\", \"file_video\": \"a38542228f6286fd.mp4\", \"expires_at\": null, \"is_active\": 1}', '[\"file_video\"]', NULL, '2026-03-11 06:19:17'),
(46, 'courses', 40, 'DELETE', '{\"title\": \"Microsoft Powerpoints\", \"description\": \"for creating presentation\", \"summary\": \"Microsoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft PowerpointMicrosoft Powerpoint\", \"thumbnail\": \"5ad2543daa240b1a.png\", \"file_pdf\": \"914521970748b621.pdf\", \"file_video\": \"a38542228f6286fd.mp4\", \"proponent_id\": 22, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-12 02:43:28'),
(47, 'courses', 46, 'INSERT', NULL, '{\"title\": \"BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING\", \"description\": \"BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING\", \"summary\": \"BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING\", \"thumbnail\": null, \"file_pdf\": \"553d0b74f1e03290.pdf\", \"file_video\": null, \"proponent_id\": 5, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, '2026-03-12 03:15:47'),
(48, 'courses', 47, 'INSERT', NULL, '{\"title\": \"MS WORD\", \"description\": \"MS WORDMS WORD\", \"summary\": \"MS WORDMS WORDMS WORDMS WORDMS WORDMS WORD\", \"thumbnail\": null, \"file_pdf\": \"0c8a2d5f9d024c5f.pdf\", \"file_video\": null, \"proponent_id\": 5, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, '2026-03-12 03:16:20'),
(49, 'courses', 44, 'UPDATE', '{\"title\": \"MS Powerpoint\", \"description\": \"MS PowerpointMS Powerpoint\", \"summary\": \"MS PowerpointMS PowerpointMS Powerpoint\", \"thumbnail\": \"787649e329f7066e.png\", \"file_pdf\": \"2511cc375026c19b.pdf\", \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"MS Powerpoint\", \"description\": \"MS PowerpointMS Powerpoint\", \"summary\": \"MS PowerpointMS PowerpointMS Powerpoint\", \"thumbnail\": \"787649e329f7066e.png\", \"file_pdf\": \"2511cc375026c19b.pdf\", \"file_video\": \"3734cd9963722805.mp4\", \"expires_at\": null, \"is_active\": 1}', '[\"file_video\"]', NULL, '2026-03-13 01:04:28'),
(50, 'courses', 47, 'UPDATE', '{\"title\": \"MS WORD\", \"description\": \"MS WORDMS WORD\", \"summary\": \"MS WORDMS WORDMS WORDMS WORDMS WORDMS WORD\", \"thumbnail\": null, \"file_pdf\": \"0c8a2d5f9d024c5f.pdf\", \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"MS WORD\", \"description\": \"MS WORDMS WORD\", \"summary\": \"MS WORDMS WORDMS WORDMS WORDMS WORDMS WORD\", \"thumbnail\": null, \"file_pdf\": \"0c8a2d5f9d024c5f.pdf\", \"file_video\": \"e8483caa3dd5f146.mp4\", \"expires_at\": null, \"is_active\": 1}', '[\"file_video\"]', NULL, '2026-03-13 01:05:44');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_replied` tinyint(1) DEFAULT 0,
  `admin_notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `is_read`, `is_replied`, `admin_notes`, `ip_address`, `user_agent`, `created_at`) VALUES
(2, 'aslkdhashdashdsajh', 'dlskhfsldakjfsdjkfsdlkjflk@gmail.com', 'aslkdjsalkjdaslkdjsakdlj', 'lkjlaskjdlaskjdlkasdlkasjdlkasjdlkasjdlkasjdlkasjdlkasjdlkasjdlkasjdlksajdlkjasdlkjasdlkjasdkljasdkljsaldkjaskldjaslkjdsa', 0, 0, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 05:06:49');

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
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `summary` varchar(2500) DEFAULT NULL,
  `edited_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `description`, `thumbnail`, `proponent_id`, `file_pdf`, `file_video`, `created_at`, `updated_at`, `expires_at`, `is_active`, `summary`, `edited_at`) VALUES
(44, 'MS Powerpoint', 'MS PowerpointMS Powerpoint', '787649e329f7066e.png', 22, '2511cc375026c19b.pdf', '3734cd9963722805.mp4', '2026-03-11 01:59:03', '2026-03-13 01:04:28', NULL, 1, 'MS PowerpointMS PowerpointMS Powerpoint', NULL),
(46, 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', NULL, 5, '553d0b74f1e03290.pdf', NULL, '2026-03-12 03:15:47', NULL, NULL, 1, 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', NULL),
(47, 'MS WORD', 'MS WORDMS WORD', NULL, 5, '0c8a2d5f9d024c5f.pdf', 'e8483caa3dd5f146.mp4', '2026-03-12 03:16:20', '2026-03-13 01:05:44', NULL, 1, 'MS WORDMS WORDMS WORDMS WORDMS WORDMS WORD', NULL);

--
-- Triggers `courses`
--
DELIMITER $$
CREATE TRIGGER `courses_after_delete` AFTER DELETE ON `courses` FOR EACH ROW BEGIN
    INSERT INTO audit_log (table_name, record_id, action, old_data, user_id, created_at)
    VALUES (
        'courses', 
        OLD.id, 
        'DELETE', 
        JSON_OBJECT(
            'title', OLD.title,
            'description', OLD.description,
            'summary', OLD.summary,
            'thumbnail', OLD.thumbnail,
            'file_pdf', OLD.file_pdf,
            'file_video', OLD.file_video,
            'proponent_id', OLD.proponent_id,
            'expires_at', OLD.expires_at,
            'is_active', OLD.is_active
        ),
        @current_user_id,
        NOW()
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `courses_after_insert` AFTER INSERT ON `courses` FOR EACH ROW BEGIN
    INSERT INTO audit_log (table_name, record_id, action, new_data, user_id, created_at)
    VALUES (
        'courses', 
        NEW.id, 
        'INSERT', 
        JSON_OBJECT(
            'title', NEW.title,
            'description', NEW.description,
            'summary', NEW.summary,
            'thumbnail', NEW.thumbnail,
            'file_pdf', NEW.file_pdf,
            'file_video', NEW.file_video,
            'proponent_id', NEW.proponent_id,
            'expires_at', NEW.expires_at,
            'is_active', NEW.is_active
        ),
        @current_user_id,
        NOW()
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `courses_after_update` AFTER UPDATE ON `courses` FOR EACH ROW BEGIN
    DECLARE changed_fields JSON;
    DECLARE old_data JSON;
    DECLARE new_data JSON;
    
    -- Create JSON objects for old and new data
    SET old_data = JSON_OBJECT(
        'title', OLD.title,
        'description', OLD.description,
        'summary', OLD.summary,
        'thumbnail', OLD.thumbnail,
        'file_pdf', OLD.file_pdf,
        'file_video', OLD.file_video,
        'expires_at', OLD.expires_at,
        'is_active', OLD.is_active
    );
    
    SET new_data = JSON_OBJECT(
        'title', NEW.title,
        'description', NEW.description,
        'summary', NEW.summary,
        'thumbnail', NEW.thumbnail,
        'file_pdf', NEW.file_pdf,
        'file_video', NEW.file_video,
        'expires_at', NEW.expires_at,
        'is_active', NEW.is_active
    );
    
    -- Create array of changed fields
    SET changed_fields = JSON_ARRAY();
    
    IF OLD.title != NEW.title OR (OLD.title IS NULL AND NEW.title IS NOT NULL) OR (OLD.title IS NOT NULL AND NEW.title IS NULL) THEN
        SET changed_fields = JSON_ARRAY_APPEND(changed_fields, '$', 'title');
    END IF;
    
    IF OLD.description != NEW.description OR (OLD.description IS NULL AND NEW.description IS NOT NULL) OR (OLD.description IS NOT NULL AND NEW.description IS NULL) THEN
        SET changed_fields = JSON_ARRAY_APPEND(changed_fields, '$', 'description');
    END IF;
    
    IF OLD.summary != NEW.summary OR (OLD.summary IS NULL AND NEW.summary IS NOT NULL) OR (OLD.summary IS NOT NULL AND NEW.summary IS NULL) THEN
        SET changed_fields = JSON_ARRAY_APPEND(changed_fields, '$', 'summary');
    END IF;
    
    IF OLD.thumbnail != NEW.thumbnail OR (OLD.thumbnail IS NULL AND NEW.thumbnail IS NOT NULL) OR (OLD.thumbnail IS NOT NULL AND NEW.thumbnail IS NULL) THEN
        SET changed_fields = JSON_ARRAY_APPEND(changed_fields, '$', 'thumbnail');
    END IF;
    
    IF OLD.file_pdf != NEW.file_pdf OR (OLD.file_pdf IS NULL AND NEW.file_pdf IS NOT NULL) OR (OLD.file_pdf IS NOT NULL AND NEW.file_pdf IS NULL) THEN
        SET changed_fields = JSON_ARRAY_APPEND(changed_fields, '$', 'file_pdf');
    END IF;
    
    IF OLD.file_video != NEW.file_video OR (OLD.file_video IS NULL AND NEW.file_video IS NOT NULL) OR (OLD.file_video IS NOT NULL AND NEW.file_video IS NULL) THEN
        SET changed_fields = JSON_ARRAY_APPEND(changed_fields, '$', 'file_video');
    END IF;
    
    IF OLD.expires_at != NEW.expires_at OR (OLD.expires_at IS NULL AND NEW.expires_at IS NOT NULL) OR (OLD.expires_at IS NOT NULL AND NEW.expires_at IS NULL) THEN
        SET changed_fields = JSON_ARRAY_APPEND(changed_fields, '$', 'expires_at');
    END IF;
    
    IF OLD.is_active != NEW.is_active THEN
        SET changed_fields = JSON_ARRAY_APPEND(changed_fields, '$', 'is_active');
    END IF;
    
    -- Only insert if there were changes
    IF JSON_LENGTH(changed_fields) > 0 THEN
        INSERT INTO audit_log (table_name, record_id, action, old_data, new_data, changed_fields, user_id, created_at)
        VALUES ('courses', NEW.id, 'UPDATE', old_data, new_data, changed_fields, @current_user_id, NOW());
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `course_departments`
--

CREATE TABLE `course_departments` (
  `course_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_departments`
--

INSERT INTO `course_departments` (`course_id`, `department_id`) VALUES
(44, 9),
(46, 3),
(47, 6);

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
(5, 'Elderly Services', '2026-02-19 03:06:57'),
(6, 'Gastroenterology', '2026-02-19 03:06:57'),
(7, 'General Surgery', '2026-02-19 03:06:57'),
(8, 'Gynecology', '2026-02-19 03:06:57'),
(9, 'iMISS', '2026-03-13 00:28:54'),
(10, 'Human Resources', '2026-03-13 00:29:43'),
(11, 'Dentistry', '2026-03-13 00:30:13');

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
  `video_progress` int(11) DEFAULT 0,
  `pdf_progress` int(11) DEFAULT 0,
  `video_completed` tinyint(4) DEFAULT 0,
  `pdf_completed` tinyint(4) DEFAULT 0,
  `pdf_current_page` int(11) DEFAULT 0,
  `pdf_total_pages` int(11) DEFAULT 0,
  `status` enum('ongoing','completed','expired') DEFAULT 'ongoing',
  `total_time_seconds` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `user_id`, `course_id`, `enrolled_at`, `completed_at`, `expired_at`, `progress`, `video_progress`, `pdf_progress`, `video_completed`, `pdf_completed`, `pdf_current_page`, `pdf_total_pages`, `status`, `total_time_seconds`) VALUES
(56, 76, 47, '2026-03-13 01:08:15', NULL, NULL, 100.00, 100, 0, 1, 0, 0, 0, 'ongoing', 0),
(57, 76, 44, '2026-03-13 01:37:13', NULL, NULL, 30.00, 100, 0, 1, 0, 0, 0, 'ongoing', 0);

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
(4, 'jakshdkjashfkhakshfkjaksdhjasdhkasdha', 'jdskalhdkhaskhd', 5, '2026-02-12 23:23:28', 1),
(6, 'sadasdasdad', 'sadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdadsadasdasdad', 72, '2026-03-12 17:32:44', 1);

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
-- Table structure for table `pdf_progress`
--

CREATE TABLE `pdf_progress` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `page_number` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pdf_progress`
--

INSERT INTO `pdf_progress` (`id`, `enrollment_id`, `page_number`, `viewed_at`) VALUES
(0, 53, 1, '2026-03-12 07:24:54'),
(0, 53, 2, '2026-03-12 07:25:15'),
(0, 53, 3, '2026-03-12 07:25:15'),
(0, 53, 4, '2026-03-12 07:25:16'),
(0, 53, 5, '2026-03-12 07:25:16'),
(0, 53, 6, '2026-03-12 07:25:17'),
(0, 53, 7, '2026-03-12 07:25:17'),
(0, 53, 8, '2026-03-12 07:25:17'),
(0, 53, 9, '2026-03-12 07:25:18'),
(0, 53, 10, '2026-03-12 07:25:19'),
(0, 53, 11, '2026-03-12 07:25:19'),
(0, 53, 12, '2026-03-12 07:25:20'),
(0, 53, 13, '2026-03-12 07:25:20'),
(0, 53, 14, '2026-03-12 07:25:20'),
(0, 53, 15, '2026-03-12 07:25:21'),
(0, 53, 16, '2026-03-12 07:25:21'),
(0, 53, 17, '2026-03-12 07:25:21'),
(0, 53, 18, '2026-03-12 07:25:22'),
(0, 53, 19, '2026-03-12 07:25:22'),
(0, 53, 20, '2026-03-12 07:25:23'),
(0, 53, 21, '2026-03-12 07:25:23'),
(0, 53, 22, '2026-03-12 07:25:23'),
(0, 53, 23, '2026-03-12 07:25:24'),
(0, 53, 24, '2026-03-12 07:25:24'),
(0, 53, 25, '2026-03-12 07:25:24'),
(0, 53, 26, '2026-03-12 07:25:25'),
(0, 53, 27, '2026-03-12 07:25:25'),
(0, 53, 28, '2026-03-12 07:25:25'),
(0, 53, 29, '2026-03-12 07:25:26'),
(0, 53, 30, '2026-03-12 07:25:26'),
(0, 53, 31, '2026-03-12 07:25:26'),
(0, 53, 32, '2026-03-12 07:25:27'),
(0, 53, 33, '2026-03-12 07:25:27'),
(0, 53, 34, '2026-03-12 07:25:28'),
(0, 53, 35, '2026-03-12 07:25:28'),
(0, 53, 36, '2026-03-12 07:25:28'),
(0, 53, 37, '2026-03-12 07:25:28'),
(0, 53, 38, '2026-03-12 07:25:29'),
(0, 53, 39, '2026-03-12 07:25:29'),
(0, 53, 40, '2026-03-12 07:25:34'),
(0, 53, 41, '2026-03-12 07:25:37'),
(0, 53, 42, '2026-03-12 07:25:45'),
(0, 53, 43, '2026-03-12 07:25:45'),
(0, 53, 44, '2026-03-12 07:25:45'),
(0, 53, 45, '2026-03-12 07:25:46'),
(0, 53, 46, '2026-03-12 07:25:46'),
(0, 53, 47, '2026-03-12 07:25:46'),
(0, 53, 48, '2026-03-12 07:25:46'),
(0, 53, 49, '2026-03-12 07:25:46'),
(0, 53, 50, '2026-03-12 07:25:46'),
(0, 53, 51, '2026-03-12 07:25:46'),
(0, 53, 52, '2026-03-12 07:25:47'),
(0, 53, 53, '2026-03-12 07:25:47'),
(0, 53, 54, '2026-03-12 07:25:47'),
(0, 53, 55, '2026-03-12 07:25:47'),
(0, 53, 56, '2026-03-12 07:25:48'),
(0, 53, 57, '2026-03-12 07:25:48'),
(0, 53, 58, '2026-03-12 07:25:48'),
(0, 53, 59, '2026-03-12 07:25:48'),
(0, 53, 60, '2026-03-12 07:25:48'),
(0, 53, 61, '2026-03-12 07:25:48'),
(0, 53, 62, '2026-03-12 07:25:48'),
(0, 53, 63, '2026-03-12 07:25:49'),
(0, 53, 64, '2026-03-12 07:25:49'),
(0, 53, 65, '2026-03-12 07:25:49'),
(0, 53, 66, '2026-03-12 07:25:49'),
(0, 55, 1, '2026-03-12 07:34:31'),
(0, 55, 2, '2026-03-12 07:46:26'),
(0, 55, 3, '2026-03-12 07:46:26'),
(0, 55, 4, '2026-03-12 07:46:27'),
(0, 55, 5, '2026-03-12 07:46:27'),
(0, 55, 6, '2026-03-12 07:46:27'),
(0, 55, 7, '2026-03-12 07:46:28'),
(0, 55, 8, '2026-03-12 07:46:28'),
(0, 55, 9, '2026-03-12 07:46:29'),
(0, 55, 10, '2026-03-12 07:46:29'),
(0, 55, 11, '2026-03-12 07:46:30'),
(0, 55, 12, '2026-03-12 07:46:30'),
(0, 55, 13, '2026-03-12 07:46:30'),
(0, 55, 14, '2026-03-12 07:46:31'),
(0, 55, 15, '2026-03-12 07:46:31'),
(0, 55, 16, '2026-03-12 07:46:32'),
(0, 55, 17, '2026-03-12 07:46:32'),
(0, 55, 18, '2026-03-12 07:46:32'),
(0, 55, 19, '2026-03-12 07:46:33'),
(0, 55, 20, '2026-03-12 07:46:33'),
(0, 55, 21, '2026-03-12 07:46:33'),
(0, 55, 22, '2026-03-12 07:46:34'),
(0, 55, 23, '2026-03-12 07:46:34'),
(0, 55, 24, '2026-03-12 07:46:34'),
(0, 55, 25, '2026-03-12 07:46:35'),
(0, 55, 26, '2026-03-12 07:46:35'),
(0, 55, 27, '2026-03-12 07:46:35'),
(0, 55, 28, '2026-03-12 07:46:36'),
(0, 55, 29, '2026-03-12 07:46:36'),
(0, 55, 30, '2026-03-12 07:46:36'),
(0, 55, 31, '2026-03-12 07:46:37'),
(0, 55, 32, '2026-03-12 07:46:37'),
(0, 55, 33, '2026-03-12 07:46:37'),
(0, 55, 34, '2026-03-12 07:46:38'),
(0, 55, 35, '2026-03-12 07:46:38'),
(0, 55, 36, '2026-03-12 07:46:39'),
(0, 55, 37, '2026-03-12 07:46:39'),
(0, 55, 38, '2026-03-12 07:46:39'),
(0, 55, 39, '2026-03-12 07:46:40'),
(0, 55, 40, '2026-03-12 07:46:40'),
(0, 55, 41, '2026-03-12 07:46:40'),
(0, 55, 42, '2026-03-12 07:46:41'),
(0, 55, 43, '2026-03-12 07:46:41'),
(0, 55, 44, '2026-03-12 07:46:41'),
(0, 55, 45, '2026-03-12 07:46:42'),
(0, 55, 46, '2026-03-12 07:46:42'),
(0, 55, 47, '2026-03-12 07:46:43'),
(0, 55, 48, '2026-03-12 07:46:43'),
(0, 55, 49, '2026-03-12 07:46:43'),
(0, 55, 50, '2026-03-12 07:46:44'),
(0, 55, 51, '2026-03-12 07:46:44'),
(0, 55, 52, '2026-03-12 07:46:44'),
(0, 55, 53, '2026-03-12 07:46:45'),
(0, 55, 54, '2026-03-12 07:46:45'),
(0, 55, 55, '2026-03-12 07:46:45'),
(0, 55, 56, '2026-03-12 07:46:46'),
(0, 55, 57, '2026-03-12 07:46:46'),
(0, 55, 58, '2026-03-12 07:46:46'),
(0, 55, 59, '2026-03-12 07:46:47'),
(0, 55, 60, '2026-03-12 07:46:47'),
(0, 55, 61, '2026-03-12 07:46:47'),
(0, 55, 62, '2026-03-12 07:46:48'),
(0, 56, 1, '2026-03-13 01:08:17'),
(0, 56, 2, '2026-03-13 01:11:27'),
(0, 56, 3, '2026-03-13 01:11:51'),
(0, 56, 4, '2026-03-13 01:11:51'),
(0, 56, 5, '2026-03-13 01:12:38'),
(0, 56, 6, '2026-03-13 01:12:38'),
(0, 56, 7, '2026-03-13 01:12:39'),
(0, 56, 8, '2026-03-13 01:12:39'),
(0, 56, 9, '2026-03-13 01:12:40'),
(0, 56, 10, '2026-03-13 01:12:40'),
(0, 56, 11, '2026-03-13 01:12:40'),
(0, 56, 12, '2026-03-13 01:12:41'),
(0, 56, 13, '2026-03-13 01:12:41'),
(0, 56, 14, '2026-03-13 01:12:41'),
(0, 56, 15, '2026-03-13 01:12:42'),
(0, 56, 16, '2026-03-13 01:12:42'),
(0, 56, 17, '2026-03-13 01:12:43'),
(0, 56, 18, '2026-03-13 01:12:43'),
(0, 56, 19, '2026-03-13 01:12:43'),
(0, 56, 20, '2026-03-13 01:12:44'),
(0, 56, 21, '2026-03-13 01:12:44'),
(0, 56, 22, '2026-03-13 01:12:44'),
(0, 56, 23, '2026-03-13 01:12:45'),
(0, 56, 24, '2026-03-13 01:12:45'),
(0, 56, 25, '2026-03-13 01:12:45'),
(0, 56, 26, '2026-03-13 01:12:46'),
(0, 56, 27, '2026-03-13 01:12:46'),
(0, 56, 28, '2026-03-13 01:12:46'),
(0, 56, 29, '2026-03-13 01:12:47'),
(0, 56, 30, '2026-03-13 01:12:47'),
(0, 56, 31, '2026-03-13 01:12:47'),
(0, 56, 32, '2026-03-13 01:12:48'),
(0, 56, 33, '2026-03-13 01:12:48'),
(0, 56, 34, '2026-03-13 01:12:48'),
(0, 56, 35, '2026-03-13 01:12:49'),
(0, 56, 36, '2026-03-13 01:12:49'),
(0, 56, 37, '2026-03-13 01:12:49'),
(0, 56, 38, '2026-03-13 01:12:50'),
(0, 56, 39, '2026-03-13 01:12:50'),
(0, 56, 40, '2026-03-13 01:12:50'),
(0, 56, 41, '2026-03-13 01:12:51'),
(0, 56, 42, '2026-03-13 01:12:51'),
(0, 56, 43, '2026-03-13 01:12:51'),
(0, 56, 44, '2026-03-13 01:12:52'),
(0, 56, 45, '2026-03-13 01:12:52'),
(0, 56, 46, '2026-03-13 01:12:52'),
(0, 56, 47, '2026-03-13 01:12:53'),
(0, 56, 48, '2026-03-13 01:12:53'),
(0, 56, 49, '2026-03-13 01:12:53'),
(0, 56, 50, '2026-03-13 01:12:54'),
(0, 56, 51, '2026-03-13 01:12:54'),
(0, 56, 52, '2026-03-13 01:12:54'),
(0, 56, 53, '2026-03-13 01:12:54'),
(0, 56, 54, '2026-03-13 01:12:55'),
(0, 56, 55, '2026-03-13 01:12:55'),
(0, 56, 56, '2026-03-13 01:12:56'),
(0, 56, 57, '2026-03-13 01:12:56'),
(0, 56, 58, '2026-03-13 01:12:56'),
(0, 56, 59, '2026-03-13 01:12:57'),
(0, 56, 60, '2026-03-13 01:12:57'),
(0, 56, 61, '2026-03-13 01:12:57'),
(0, 56, 62, '2026-03-13 01:12:58');

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
(22, 'pro', '$2y$10$gpII/P2uzcDch.35MinEve7EO4uQD05eaIkHpTshPmszmeMPArXaO', 'pro', 'pro', 'pro@gmail.com', 'proponent', '2026-02-16 05:15:51', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(72, 'superadmin', '$2y$10$L.sWs03m7FGhvcGQdRZ1JOditv/DeBO.OGiY.jIRfehV.bQLmnl5K', 'superadmin', 'superadmin', 'superadmin@mail.com', 'superadmin', '2026-03-12 03:04:11', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(76, 'user', '$2y$10$KwCQY6078.5GGWaXOWv3B.kxmomQZJYosIEpeN8YVJu8Yy.6Amysm', 'user', 'user', 'user', 'user', '2026-03-12 03:13:47', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(77, 'user1', '$2y$10$a1qcZxaMZ1Zq.miP/VB/luw5ia1i/rdDALUXHrtitWvsEJIbGiLL.', 'user1', 'user1', 'user1', 'user', '2026-03-12 03:14:38', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(80, 'user3', '$2y$10$51PKxLrYdyhsJeGEBcOsgOTWSxABDvFXFwFHZ1QB4ZEn1rGn6Nnnm', 'user3', 'user3', 'user3@mail.com', 'user', '2026-03-12 05:34:35', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(81, 'user4', '$2y$10$D0anqyyNSuFksYUpxdm6O.RwJa2f6WkRwxh.IGyffilA7e52l3MYu', 'user4', 'user4', 'user4@mail.zxc', 'user', '2026-03-12 05:34:59', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(82, 'user5', '$2y$10$byqELjtKf2ytKo0myGF.M.Y9RXN4G5sHGxHKRM/jwtMEvfHI7Gifi', 'user5', 'user5', 'user5@gmail.com', 'user', '2026-03-12 05:41:04', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_departments`
--

CREATE TABLE `user_departments` (
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_departments`
--

INSERT INTO `user_departments` (`user_id`, `department_id`) VALUES
(5, 9);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assessments`
--
ALTER TABLE `assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attempt_id` (`attempt_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `selected_option_id` (`selected_option_id`);

--
-- Indexes for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assessment_id` (`assessment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_assessment_user_status_completed` (`assessment_id`,`user_id`,`status`,`completed_at`);

--
-- Indexes for table `assessment_options`
--
ALTER TABLE `assessment_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assessment_id` (`assessment_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `table_name` (`table_name`,`record_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_read` (`is_read`),
  ADD KEY `idx_created` (`created_at`);

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
-- Indexes for table `course_departments`
--
ALTER TABLE `course_departments`
  ADD PRIMARY KEY (`course_id`,`department_id`),
  ADD KEY `department_id` (`department_id`);

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
-- Indexes for table `pdf_progress`
--
ALTER TABLE `pdf_progress`
  ADD KEY `idx_enrollment_page` (`enrollment_id`,`page_number`),
  ADD KEY `idx_page` (`page_number`);

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
-- AUTO_INCREMENT for table `assessments`
--
ALTER TABLE `assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assessment_options`
--
ALTER TABLE `assessment_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `edit`
--
ALTER TABLE `edit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assessments`
--
ALTER TABLE `assessments`
  ADD CONSTRAINT `assessments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  ADD CONSTRAINT `assessment_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `assessment_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_answers_ibfk_3` FOREIGN KEY (`selected_option_id`) REFERENCES `assessment_options` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_options`
--
ALTER TABLE `assessment_options`
  ADD CONSTRAINT `assessment_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD CONSTRAINT `assessment_questions_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `course_departments`
--
ALTER TABLE `course_departments`
  ADD CONSTRAINT `course_departments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_departments_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

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

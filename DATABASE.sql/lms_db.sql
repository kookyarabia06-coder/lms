-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 24, 2026 at 09:52 AM
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
(6, 44, 'Powerpoint', 'PowerpointPowerpoint', 70, 0, 0, '2026-03-12 02:44:29', '2026-03-16 05:32:06'),
(8, 47, 'MS WORD', 'MS WORDMS WORDMS WORDMS WORD', 70, 0, 0, '2026-03-12 03:17:14', '2026-03-16 05:32:26'),
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

--
-- Dumping data for table `assessment_answers`
--

INSERT INTO `assessment_answers` (`id`, `attempt_id`, `question_id`, `selected_option_id`, `essay_answer`, `is_correct`, `points_earned`) VALUES
(1, 3, 30, 117, NULL, 1, 1),
(2, 3, 31, 121, NULL, 0, 0),
(3, 3, 32, 126, NULL, 1, 1),
(4, 4, 30, 115, NULL, 0, 0),
(5, 4, 31, 120, NULL, 0, 0),
(6, 4, 32, 125, NULL, 0, 0),
(7, 5, 30, 117, NULL, 1, 1),
(8, 5, 31, 119, NULL, 1, 1),
(9, 5, 32, 124, NULL, 0, 0),
(10, 6, 30, 117, NULL, 1, 1),
(11, 6, 31, 119, NULL, 1, 1),
(12, 6, 32, 126, NULL, 1, 1),
(13, 7, 17, 64, NULL, 0, 0),
(14, 8, 17, 66, NULL, 1, 1);

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

--
-- Dumping data for table `assessment_attempts`
--

INSERT INTO `assessment_attempts` (`id`, `assessment_id`, `user_id`, `score`, `status`, `started_at`, `completed_at`, `passed`, `answers`) VALUES
(3, 6, 76, 66.67, 'completed', '2026-03-16 16:36:52', '2026-03-16 16:39:05', 0, NULL),
(4, 6, 76, 0.00, 'completed', '2026-03-16 16:39:18', '2026-03-16 16:39:28', 0, NULL),
(5, 6, 76, 66.67, 'completed', '2026-03-16 16:40:06', '2026-03-16 16:40:16', 0, NULL),
(6, 6, 76, 100.00, 'completed', '2026-03-16 16:40:26', '2026-03-16 16:40:35', 1, NULL),
(7, 9, 76, 0.00, 'completed', '2026-03-17 14:08:08', '2026-03-17 14:08:13', 0, NULL),
(8, 9, 76, 100.00, 'completed', '2026-03-17 14:08:17', '2026-03-17 14:08:20', 1, NULL);

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
(63, 17, 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', 0, 0),
(64, 17, 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', 0, 1),
(65, 17, 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', 0, 2),
(66, 17, 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', 1, 3),
(115, 30, 'Powerpoint', 0, 0),
(116, 30, 'PowerpointPowerpoint', 0, 1),
(117, 30, 'PowerpointPowerpointPowerpoint', 1, 2),
(118, 30, 'PowerpointPowerpointPowerpointPowerpoint', 0, 3),
(119, 31, 'PowerpointPowerpoint', 1, 0),
(120, 31, 'Powerpoint', 0, 1),
(121, 31, 'PowerpointPowerpointPowerpoint', 0, 2),
(122, 31, 'PowerpointPowerpointPowerpointPowerpoint', 0, 3),
(123, 32, 'PowerpointPowerpointPowerpoint', 0, 0),
(124, 32, 'Powerpoint', 0, 1),
(125, 32, 'PowerpointPowerpoint', 0, 2),
(126, 32, 'Regine Velasquez', 1, 3),
(127, 33, 'MS WORD', 0, 0),
(128, 33, 'MS WORDMS WORD', 1, 1),
(129, 33, 'MS WORDMS WORDMS WORD', 0, 2),
(130, 33, 'MS WORDMS WORDMS WORDMS WORD', 0, 3),
(131, 34, 'MS WORDMS WORDMS WORDMS WORDMS WORD', 0, 0),
(132, 34, 'MS WORDMS WORDMS WORDMS WORD', 0, 1),
(133, 34, 'MS WORDMS WORDMS WORD', 1, 2),
(134, 34, 'MS WORD', 0, 3),
(135, 35, 'Powerpoint', 1, 0),
(136, 35, 'Powerpointdddsd', 0, 1),
(137, 35, 'asdas', 0, 2),
(138, 35, 'asdasd', 0, 3);

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
(17, 9, 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', 'multiple_choice', 1, 0, '2026-03-12 05:12:30'),
(30, 6, 'PowerpointPowerpointPowerpoint', 'multiple_choice', 1, 0, '2026-03-16 05:32:06'),
(31, 6, 'PowerpointPowerpoint', 'multiple_choice', 1, 1, '2026-03-16 05:32:06'),
(32, 6, 'Powerpoint', 'multiple_choice', 1, 2, '2026-03-16 05:32:06'),
(33, 8, 'MS WORDMS WORD', 'multiple_choice', 1, 0, '2026-03-16 05:32:26'),
(34, 8, 'MS WORDMS WORDMS WORD', 'multiple_choice', 1, 1, '2026-03-16 05:32:26'),
(35, 8, 'Powerpoint', 'multiple_choice', 1, 2, '2026-03-16 05:32:26');

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
(50, 'courses', 47, 'UPDATE', '{\"title\": \"MS WORD\", \"description\": \"MS WORDMS WORD\", \"summary\": \"MS WORDMS WORDMS WORDMS WORDMS WORDMS WORD\", \"thumbnail\": null, \"file_pdf\": \"0c8a2d5f9d024c5f.pdf\", \"file_video\": null, \"expires_at\": null, \"is_active\": 1}', '{\"title\": \"MS WORD\", \"description\": \"MS WORDMS WORD\", \"summary\": \"MS WORDMS WORDMS WORDMS WORDMS WORDMS WORD\", \"thumbnail\": null, \"file_pdf\": \"0c8a2d5f9d024c5f.pdf\", \"file_video\": \"e8483caa3dd5f146.mp4\", \"expires_at\": null, \"is_active\": 1}', '[\"file_video\"]', NULL, '2026-03-13 01:05:44'),
(51, 'courses', 48, 'INSERT', NULL, '{\"title\": \"Tester\", \"description\": \"TesterTester\", \"summary\": \"TesterTesterTesterTesterTesterTester\", \"thumbnail\": null, \"file_pdf\": \"60457b0ca7bb3a8c.pdf\", \"file_video\": null, \"proponent_id\": 22, \"expires_at\": \"2026-03-28\", \"is_active\": 1}', NULL, NULL, '2026-03-13 08:01:14'),
(52, 'courses', 49, 'INSERT', NULL, '{\"title\": \"adsasdasd\", \"description\": \"adsasdasd\", \"summary\": \"adsasdasdadsasdasdadsasdasdadsasdasd\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 5, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, '2026-03-13 08:13:17'),
(53, 'courses', 50, 'INSERT', NULL, '{\"title\": \"Module\", \"description\": \"module description\", \"summary\": \"Module summary\", \"thumbnail\": \"bb8dae1ebab304a6.png\", \"file_pdf\": \"87f0387375dbeacd.pdf\", \"file_video\": null, \"proponent_id\": 72, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, '2026-03-18 07:48:04'),
(54, 'courses', 50, 'DELETE', '{\"title\": \"Module\", \"description\": \"module description\", \"summary\": \"Module summary\", \"thumbnail\": \"bb8dae1ebab304a6.png\", \"file_pdf\": \"87f0387375dbeacd.pdf\", \"file_video\": null, \"proponent_id\": 72, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-24 06:31:32'),
(55, 'courses', 49, 'DELETE', '{\"title\": \"adsasdasd\", \"description\": \"adsasdasd\", \"summary\": \"adsasdasdadsasdasdadsasdasdadsasdasd\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 5, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, NULL, '2026-03-24 06:31:37'),
(56, 'courses', 48, 'DELETE', '{\"title\": \"Tester\", \"description\": \"TesterTester\", \"summary\": \"TesterTesterTesterTesterTesterTester\", \"thumbnail\": null, \"file_pdf\": \"60457b0ca7bb3a8c.pdf\", \"file_video\": null, \"proponent_id\": 22, \"expires_at\": \"2026-03-28\", \"is_active\": 1}', NULL, NULL, NULL, '2026-03-24 06:31:39');

-- --------------------------------------------------------

--
-- Table structure for table `committees`
--

CREATE TABLE `committees` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `committees`
--

INSERT INTO `committees` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'SMOKE-FREE TASKFORCE', 'SMOKE-FREE TASKFORCE\r\n', '2026-03-16 19:52:45', '2026-03-18 21:34:24'),
(2, 'COMMITTEE ON ANTI-RED TAPE (CART)', 'COMMITTEE ON ANTI-RED TAPE (CART)', '2026-03-17 16:23:28', '2026-03-18 21:34:14'),
(3, 'ANTIMICROBIAL STEWARDSHIP COMMITTEE', 'ANTIMICROBIAL STEWARDSHIP COMMITTEE', '2026-03-17 16:25:36', '2026-03-18 21:33:59'),
(4, 'BASIC EMERGENCY OBSTETRICS AND NEWBORN CARE (BEMONC) COMMITTEE', 'BASIC EMERGENCY OBSTETRICS AND NEWBORN CARE (BEMONC) COMMITTEE\r\n', '2026-03-18 21:34:30', NULL),
(5, 'BIDS AND AWARDS COMMITTEE', 'BIDS AND AWARDS COMMITTEE\r\n', '2026-03-18 21:34:37', NULL),
(6, 'BREASTFEEDING COMMITTEE', 'BREASTFEEDING COMMITTEE\r\n', '2026-03-18 21:34:43', NULL),
(7, 'CONSIGNMENT PROGRAM COMMITTEE', 'CONSIGNMENT PROGRAM COMMITTEE\r\n', '2026-03-18 21:37:04', NULL),
(8, 'CONTINUING PROFESSIONAL DEVELOPMENT (CPD) COMMITTEE', 'CONTINUING PROFESSIONAL DEVELOPMENT (CPD) COMMITTEE\r\n', '2026-03-18 21:37:09', NULL),
(9, 'COMMITTEE ON EMERGING AND RE-EMERGING INFECTIOUS DISEASES', 'COMMITTEE ON EMERGING AND RE-EMERGING INFECTIOUS DISEASES\r\n', '2026-03-18 21:37:14', NULL),
(10, 'COMMITTEE ON DECORUM AND INVESTIGATION OF SEXUAL HARASSMENT', 'COMMITTEE ON DECORUM AND INVESTIGATION OF SEXUAL HARASSMENT\r\n', '2026-03-18 21:37:20', NULL),
(11, 'CREDENTIAL COMMITTEE', 'CREDENTIAL COMMITTEE\r\n', '2026-03-18 21:37:29', NULL),
(12, 'CULTURAL AND SPORTS COMMITTEE', 'CULTURAL AND SPORTS COMMITTEE\r\n', '2026-03-18 21:37:35', NULL),
(13, 'DATA PRIVACY COMMITTEE', 'DATA PRIVACY COMMITTEE\r\n', '2026-03-18 21:37:40', NULL),
(14, 'DESIGN AND BUILD COMMITTEE', 'DESIGN AND BUILD COMMITTEE\r\n', '2026-03-18 21:37:45', NULL),
(15, 'DISPOSAL COMMITTEE', 'DISPOSAL COMMITTEE\r\n', '2026-03-18 21:37:49', NULL),
(16, 'APPRAISAL COMMITTEE', 'APPRAISAL COMMITTEE\r\n', '2026-03-18 21:37:54', NULL),
(17, 'GENDER AND DEVELOPMENT COMMITTEE', 'GENDER AND DEVELOPMENT COMMITTEE\r\n', '2026-03-18 21:37:58', NULL),
(18, 'GREEN AND HEALTHY HOSPITAL COMMITTEE', 'GREEN AND HEALTHY HOSPITAL COMMITTEE\r\n', '2026-03-18 21:38:04', NULL),
(19, 'HEALTH TECHNOLOGY ASSESSMENT COMMITTEE', 'HEALTH TECHNOLOGY ASSESSMENT COMMITTEE\r\n', '2026-03-18 21:38:08', NULL),
(20, 'HIV-AIDS CORE TEAM (HACT)', 'HIV-AIDS CORE TEAM (HACT)\r\n', '2026-03-18 21:38:14', NULL),
(21, 'HOSPITAL BLOOD TRANSFUSION COMMITTEE', 'HOSPITAL BLOOD TRANSFUSION COMMITTEE\r\n', '2026-03-18 21:39:31', NULL),
(22, 'HOSPITAL COMMITTEE ON AFFILIATION AND TRAINING OF STUDENTS (HCATS)', 'HOSPITAL COMMITTEE ON AFFILIATION AND TRAINING OF STUDENTS (HCATS)\r\n', '2026-03-18 21:39:36', NULL),
(23, 'HOSPITAL COSTING AND RATE SETTING COMMITTEE', 'HOSPITAL COSTING AND RATE SETTING COMMITTEE\r\n', '2026-03-18 21:39:41', NULL),
(24, 'INTERNAL GRIEVANCE/ ETHICS COMMITTEE', 'INTERNAL GRIEVANCE/ ETHICS COMMITTEE\r\n', '2026-03-18 21:39:45', NULL),
(25, 'HOSPITAL POISON CONTROL COMMITTEE', 'HOSPITAL POISON CONTROL COMMITTEE\r\n', '2026-03-18 21:39:49', NULL),
(26, 'HOSPITAL TB COMMITTEE', 'HOSPITAL TB COMMITTEE\r\n', '2026-03-18 21:39:54', NULL),
(27, 'HOSPITAL WASTE MANAGEMENT COMMITTEE', 'HOSPITAL WASTE MANAGEMENT COMMITTEE\r\n', '2026-03-18 21:39:58', NULL),
(28, 'HUMAN RESOURCE DEVELOPMENT COMMITTEE (HRDC)', 'HUMAN RESOURCE DEVELOPMENT COMMITTEE (HRDC)\r\n', '2026-03-18 21:40:03', NULL),
(29, 'HUMAN RESOURCE MERIT, PROMOTION AND SELECTION BOARD (HRMPSB)', 'HUMAN RESOURCE MERIT, PROMOTION AND SELECTION BOARD (HRMPSB)\r\n', '2026-03-18 21:40:08', NULL),
(30, 'INFECTIONPREVENTIONAND CONTROLCOMMITTEE', 'INFECTIONPREVENTIONAND CONTROLCOMMITTEE\r\n', '2026-03-18 21:40:13', NULL),
(31, 'INSPECTION COMMITTEE', 'INSPECTION COMMITTEE\r\n', '2026-03-18 21:40:17', NULL),
(32, 'INTEGRITY MANAGEMENT COMMITTEE', 'INTEGRITY MANAGEMENT COMMITTEE\r\n', '2026-03-18 21:40:21', NULL),
(33, 'INTENSIVE CARE COMMITTEE', 'INTENSIVE CARE COMMITTEE\r\n', '2026-03-18 21:40:25', NULL),
(34, 'INVENTORY COMMITTEE', 'INVENTORY COMMITTEE\r\n', '2026-03-18 21:48:08', NULL),
(35, 'LUPON NG KORESPONDENSYA OPISYAL', 'LUPON NG KORESPONDENSYA OPISYAL\r\n', '2026-03-18 21:48:12', NULL),
(36, 'MEDICAL OUTREACH COMMITTEE', 'MEDICAL OUTREACH COMMITTEE\r\n', '2026-03-18 21:48:16', NULL),
(37, 'MULTI DISCIPLINARY TEAM FOR ONCOLOGY', 'MULTI DISCIPLINARY TEAM FOR ONCOLOGY\r\n', '2026-03-18 21:48:21', NULL),
(38, 'MULTI-SPECIALTY CENTER COMMITTEE', 'MULTI-SPECIALTY CENTER COMMITTEE\r\n', '2026-03-18 21:48:25', NULL),
(39, 'MULTIMEDIA COMMITTEE', 'MULTIMEDIA COMMITTEE\r\n', '2026-03-18 21:48:29', NULL),
(40, 'NUTRITION COMMITTEE', 'NUTRITION COMMITTEE\r\n', '2026-03-18 21:48:34', NULL),
(41, 'OCCUPATIONAL SAFETY AND HEALTH COMMITTEE', 'OCCUPATIONAL SAFETY AND HEALTH COMMITTEE\r\n', '2026-03-18 21:48:38', NULL),
(42, 'OPERATING ROOM MANAGEMENT COMMITTEE (ORMAC)', 'OPERATING ROOM MANAGEMENT COMMITTEE (ORMAC)\r\n', '2026-03-18 21:48:43', NULL),
(43, 'ORGAN DONATION COMMITTEE', 'ORGAN DONATION COMMITTEE\r\n', '2026-03-18 21:48:47', NULL),
(44, 'PATIENT HEALTH RECORDS COMMITTEE', 'PATIENT HEALTH RECORDS COMMITTEE\r\n', '2026-03-18 21:48:51', NULL),
(45, 'PATIENT SAFETY COMMITTEE', 'PATIENT SAFETY COMMITTEE\r\n', '2026-03-18 21:48:55', NULL),
(46, 'PERFORMANCE MANAGEMENT TEAM COMMITTEE', 'PERFORMANCE MANAGEMENT TEAM COMMITTEE\r\n', '2026-03-18 21:48:59', NULL),
(47, 'PHILHEALTH COMPLIANCE COMMITTEE', 'PHILHEALTH COMPLIANCE COMMITTEE\r\n', '2026-03-18 21:49:03', NULL),
(48, 'PLANNING COMMITTEE', 'PLANNING COMMITTEE\r\n', '2026-03-18 21:49:07', NULL),
(49, 'POLLUTION CONTROL COMMITTEE', 'POLLUTION CONTROL COMMITTEE\r\n', '2026-03-18 21:49:13', NULL),
(50, 'PROGRAM ON AWARDSAND INCENTIVES FOR SERVICE EXCELLENCE (PRAISE) COMMITTEE', 'PROGRAM ON AWARDSAND INCENTIVES FOR SERVICE EXCELLENCE (PRAISE) COMMITTEE\r\n', '2026-03-18 21:49:17', NULL),
(51, 'PUBLIC-PRIVATE PARTNERSHIP COMMITTEE', 'PUBLIC-PRIVATE PARTNERSHIP COMMITTEE\r\n', '2026-03-18 21:49:21', NULL),
(52, 'QUALITY MANAGEMENT SYSTEM (QMS) WORKING COMMITTEE', 'QUALITY MANAGEMENT SYSTEM (QMS) WORKING COMMITTEE\r\n', '2026-03-18 21:49:25', NULL),
(53, 'SALN REVIEW AND COMPLIANCE COMMITTEE', 'SALN REVIEW AND COMPLIANCE COMMITTEE\r\n', '2026-03-18 21:49:34', NULL),
(54, 'THERAPEUTICS COMMITTEE', 'THERAPEUTICS COMMITTEE\r\n', '2026-03-18 21:49:46', NULL),
(55, 'UNIFORM COMMITTEE', 'UNIFORM COMMITTEE\r\n', '2026-03-18 21:49:51', NULL),
(56, 'WOMEN AND CHILDREN PROTECTION COMMITTEE', 'WOMEN AND CHILDREN PROTECTION COMMITTEE\r\n', '2026-03-18 21:49:57', NULL);

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
(2, 'aslkdhashdashdsajh', 'dlskhfsldakjfsdjkfsdlkjflk@gmail.com', 'aslkdjsalkjdaslkdjsakdlj', 'lkjlaskjdlaskjdlkasdlkasjdlkasjdlkasjdlkasjdlkasjdlkasjdlkasjdlkasjdlksajdlkjasdlkjasdlkjasdkljasdkljsaldkjaskldjaslkjdsa', 1, 0, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 05:06:49');

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
  `edited_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','reject','approve') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `description`, `thumbnail`, `proponent_id`, `file_pdf`, `file_video`, `created_at`, `updated_at`, `expires_at`, `is_active`, `summary`, `edited_at`, `status`) VALUES
(44, 'MS Powerpoint', 'MS PowerpointMS Powerpoint', '787649e329f7066e.png', 22, '2511cc375026c19b.pdf', '3734cd9963722805.mp4', '2026-03-11 01:59:03', '2026-03-19 07:02:50', NULL, 1, 'MS PowerpointMS PowerpointMS Powerpoint', NULL, 'pending'),
(46, 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', NULL, 5, '553d0b74f1e03290.pdf', NULL, '2026-03-12 03:15:47', NULL, NULL, 1, 'BASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTINGBASIC DESKTOP COMPUTER HARDWARE TROUBLESHOOTING', NULL, 'pending'),
(47, 'MS WORD', 'MS WORDMS WORD', NULL, 5, '0c8a2d5f9d024c5f.pdf', 'e8483caa3dd5f146.mp4', '2026-03-12 03:16:20', '2026-03-13 07:49:30', NULL, 1, 'MS WORDMS WORDMS WORDMS WORDMS WORDMS WORD', NULL, 'pending');

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
  `committee_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_departments`
--

INSERT INTO `course_departments` (`course_id`, `committee_id`) VALUES
(44, 4);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `created_at`, `description`) VALUES
(1, 'NURSING SERVICE', '2026-02-19 03:06:57', NULL),
(2, 'MEDICAL SERVICE', '2026-02-19 03:06:57', NULL),
(3, 'HOPSS (HOSPITAL OPERATIONS AND PATIENT SUPPORT SERVICE)', '2026-02-19 03:06:57', NULL),
(4, 'ALLIED HEALTH PROFESSIONAL SERVICE', '2026-02-19 03:06:57', NULL),
(5, 'FINANCES', '2026-02-19 03:06:57', 'Division finance descriptopn');

-- --------------------------------------------------------

--
-- Table structure for table `depts`
--

CREATE TABLE `depts` (
  `department_id` int(11) NOT NULL,
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `depts`
--

INSERT INTO `depts` (`department_id`, `id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(3, 5, 'iMISS', 'it here', '2026-03-19 01:44:20', NULL),
(3, 6, 'Human Resource Management', '', '2026-03-19 05:23:04', NULL),
(2, 7, 'Internal Medicine', '', '2026-03-19 05:32:32', NULL),
(2, 8, 'Surgery', '', '2026-03-19 05:33:18', NULL),
(2, 9, 'OB-Gynecology', '', '2026-03-19 05:33:50', NULL),
(2, 10, 'Pediatrics', '', '2026-03-19 05:34:18', NULL),
(2, 11, 'Anesthesia', '', '2026-03-19 05:34:48', NULL),
(2, 12, 'ENT-HNS', '', '2026-03-19 05:35:23', NULL),
(2, 13, 'Opthalmology', '', '2026-03-19 05:35:42', NULL),
(2, 14, 'Psychiatry', '', '2026-03-19 05:36:01', NULL),
(2, 15, 'Radiology', '', '2026-03-19 05:36:10', NULL),
(2, 16, 'Pathology', '', '2026-03-19 05:36:24', NULL),
(2, 17, 'Rehabilitation Medicine', '', '2026-03-19 05:36:40', NULL),
(2, 18, 'Out-Patient', '', '2026-03-19 05:36:57', NULL),
(2, 19, 'Emergency Medicine', '', '2026-03-19 05:37:09', NULL),
(2, 20, 'Family and Community Medicine', '', '2026-03-19 05:37:31', NULL),
(2, 21, 'Dental Services', '', '2026-03-19 05:37:42', NULL),
(2, 22, 'Operating Room Complex', '', '2026-03-19 05:42:01', NULL),
(2, 23, 'Obstretics Complex - Medical', '', '2026-03-19 05:42:31', '2026-03-19 05:59:10'),
(2, 24, 'Post Anesthesia Care Unit - Medical', '', '2026-03-19 05:42:52', '2026-03-19 05:58:55'),
(2, 25, 'Dialysis Unit', '', '2026-03-19 05:43:54', NULL),
(2, 26, 'Diagnostic Unit', '', '2026-03-19 05:44:11', NULL),
(2, 27, 'Pulmonary', '', '2026-03-19 05:44:22', NULL),
(2, 28, 'Gastroenterology', '', '2026-03-19 05:44:36', NULL),
(2, 29, 'Cardiology', '', '2026-03-19 05:44:51', NULL),
(2, 30, 'ICU', '', '2026-03-19 05:45:03', NULL),
(2, 31, 'SICU - Medical', '', '2026-03-19 05:45:13', '2026-03-19 05:58:09'),
(2, 32, 'MICU - Medical', '', '2026-03-19 05:45:21', '2026-03-19 05:58:24'),
(2, 33, 'ACTU', '', '2026-03-19 05:45:40', NULL),
(2, 34, 'NTP DOTS', '', '2026-03-19 05:45:50', NULL),
(1, 35, 'Medicine', '', '2026-03-19 05:46:30', NULL),
(1, 36, 'Surgery', '', '2026-03-19 05:46:40', NULL),
(1, 37, 'OB-Gynecology', '', '2026-03-19 05:46:57', NULL),
(1, 38, 'Pediatrics', '', '2026-03-19 05:47:18', NULL),
(1, 39, 'Out-Patient', '', '2026-03-19 05:47:40', NULL),
(1, 40, 'Emergency Medicine', '', '2026-03-19 05:47:57', NULL),
(1, 41, 'Operating Room Complex - Nursing', '', '2026-03-19 05:48:10', '2026-03-19 05:56:45'),
(1, 42, 'Obstretics Complex  - Nursing', '', '2026-03-19 05:48:23', '2026-03-19 05:56:56'),
(1, 43, 'Post Anesthesia Care Unit  - Nursing', '', '2026-03-19 05:48:44', '2026-03-19 05:57:07'),
(1, 44, 'PICU', '', '2026-03-19 05:48:52', NULL),
(1, 45, 'SICU - Nursing', '', '2026-03-19 05:49:01', '2026-03-19 05:55:56'),
(1, 46, 'MICU  - Nursing', '', '2026-03-19 05:49:16', '2026-03-19 05:57:22'),
(1, 47, 'Central Supply and Sterilization', '', '2026-03-19 05:49:44', NULL),
(3, 48, 'Procurement', '', '2026-03-19 05:50:18', NULL),
(3, 49, 'Materials Management', '', '2026-03-19 05:50:34', NULL),
(3, 50, 'EFM - Housekeeping', '', '2026-03-19 05:51:19', NULL),
(3, 51, 'EFM - Linen/Laundry', '', '2026-03-19 05:51:41', NULL),
(3, 52, 'EFM - Security', '', '2026-03-19 05:51:59', NULL),
(3, 53, 'EFM - Design and Construction', '', '2026-03-19 05:52:17', NULL),
(3, 54, 'EFM - Motorpol', '', '2026-03-19 05:52:41', NULL),
(3, 55, 'EFM - Bio-Medical', '', '2026-03-19 05:52:59', NULL),
(3, 56, 'EFM - Electro Mechanical', '', '2026-03-19 05:53:16', NULL),
(4, 57, 'Admiting and Information', '', '2026-03-19 05:53:42', NULL),
(4, 58, 'Medical Social Work', '', '2026-03-19 05:53:55', NULL),
(4, 59, 'Nutrition and Dietetics', '', '2026-03-19 05:54:18', NULL),
(4, 60, 'Pharmacy', '', '2026-03-19 05:54:30', NULL),
(4, 61, 'OPD Records', '', '2026-03-19 05:54:47', NULL),
(5, 62, 'Accounting', '', '2026-03-19 05:55:00', NULL),
(5, 63, 'Budget', '', '2026-03-19 05:55:06', NULL),
(5, 64, 'Cash Operations', '', '2026-03-19 05:55:16', NULL),
(5, 65, 'Billing and Claims', '', '2026-03-19 05:55:28', NULL);

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
(68, 77, 47, '2026-03-16 07:20:17', NULL, NULL, 1.00, 0, 2, 0, 0, 0, 0, 'ongoing', 0),
(69, 77, 44, '2026-03-16 07:20:23', NULL, NULL, 55.00, 10, 100, 1, 1, 0, 0, 'ongoing', 0),
(70, 76, 44, '2026-03-16 08:21:31', '2026-03-16 08:49:51', NULL, 0.00, 0, 0, 1, 1, 39, 39, 'completed', 0),
(71, 76, 46, '2026-03-17 06:07:19', NULL, NULL, 0.00, 0, 0, 0, 1, 66, 66, 'ongoing', 0);

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
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `file_pdf` varchar(255) DEFAULT NULL,
  `file_video` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `committee_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `modules`
--

INSERT INTO `modules` (`id`, `title`, `description`, `thumbnail`, `file_pdf`, `file_video`, `created_by`, `committee_id`, `created_at`, `updated_at`) VALUES
(1, 'asdasdasd', 'asdasdasdasdsd', 'add8bc215232155c.png', '25eac17bd6d6f6eb.pdf', NULL, 5, 18, '2026-03-24 03:32:30', '2026-03-24 05:23:39'),
(2, 'asdasd', 'asdasdasd', 'e8df8437fd798f7c.jpg', '75244f619ebd3b5c.pdf', NULL, 72, 21, '2026-03-24 05:41:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `module_pdf_progress`
--

CREATE TABLE `module_pdf_progress` (
  `id` int(11) NOT NULL,
  `progress_id` int(11) NOT NULL,
  `page_number` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `module_pdf_progress`
--

INSERT INTO `module_pdf_progress` (`id`, `progress_id`, `page_number`, `viewed_at`) VALUES
(190, 5, 1, '2026-03-24 06:42:16'),
(191, 5, 2, '2026-03-24 06:42:17'),
(192, 5, 3, '2026-03-24 06:42:18'),
(193, 5, 4, '2026-03-24 06:42:18'),
(194, 5, 5, '2026-03-24 06:42:26'),
(195, 5, 6, '2026-03-24 06:42:27'),
(196, 5, 7, '2026-03-24 06:42:27'),
(197, 5, 8, '2026-03-24 06:42:27'),
(198, 5, 9, '2026-03-24 06:42:27'),
(199, 5, 10, '2026-03-24 06:42:28'),
(200, 5, 11, '2026-03-24 06:42:28'),
(201, 5, 12, '2026-03-24 06:42:28'),
(202, 5, 13, '2026-03-24 06:42:29'),
(203, 5, 14, '2026-03-24 06:42:29'),
(204, 5, 15, '2026-03-24 06:42:29'),
(205, 5, 16, '2026-03-24 06:42:30'),
(206, 5, 17, '2026-03-24 06:42:30'),
(207, 5, 18, '2026-03-24 06:42:30'),
(208, 5, 19, '2026-03-24 06:42:41'),
(209, 5, 20, '2026-03-24 06:42:42'),
(210, 5, 21, '2026-03-24 06:42:42'),
(211, 5, 22, '2026-03-24 06:42:42'),
(212, 5, 23, '2026-03-24 06:42:43'),
(213, 5, 24, '2026-03-24 06:42:43'),
(214, 5, 25, '2026-03-24 06:42:43'),
(215, 5, 26, '2026-03-24 06:42:44'),
(216, 6, 1, '2026-03-24 07:02:29'),
(217, 6, 2, '2026-03-24 07:02:30'),
(218, 6, 3, '2026-03-24 07:02:30'),
(219, 6, 4, '2026-03-24 07:02:31'),
(220, 6, 5, '2026-03-24 07:02:31'),
(221, 6, 6, '2026-03-24 07:02:32'),
(222, 6, 7, '2026-03-24 07:02:32'),
(223, 6, 8, '2026-03-24 07:02:32'),
(224, 6, 9, '2026-03-24 07:02:33'),
(225, 6, 10, '2026-03-24 07:02:33'),
(226, 6, 11, '2026-03-24 07:02:34'),
(227, 6, 12, '2026-03-24 07:02:34'),
(228, 6, 13, '2026-03-24 07:02:34'),
(229, 6, 14, '2026-03-24 07:02:35'),
(230, 6, 15, '2026-03-24 07:02:35');

-- --------------------------------------------------------

--
-- Table structure for table `module_progress`
--

CREATE TABLE `module_progress` (
  `id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pdf_completed` tinyint(1) DEFAULT 0,
  `video_completed` tinyint(1) DEFAULT 0,
  `pdf_progress` int(11) DEFAULT 0,
  `video_progress` int(11) DEFAULT 0,
  `pdf_total_pages` int(11) DEFAULT 0,
  `video_position` int(11) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `last_accessed` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `module_progress`
--

INSERT INTO `module_progress` (`id`, `module_id`, `user_id`, `pdf_completed`, `video_completed`, `pdf_progress`, `video_progress`, `pdf_total_pages`, `video_position`, `completed_at`, `last_accessed`) VALUES
(1, 1, 72, 0, 0, 0, 0, 0, 0, NULL, '2026-03-24 05:18:00'),
(2, 2, 72, 0, 0, 0, 0, 0, 0, NULL, '2026-03-24 05:41:03'),
(5, 2, 76, 1, 0, 100, 0, 26, 0, NULL, '2026-03-24 06:42:44'),
(6, 1, 76, 0, 0, 33, 0, 45, 0, NULL, '2026-03-24 07:02:35');

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
(7, 'adasdadasdasd ', 'adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd adasdadasdasd ', 72, '2026-03-12 19:28:22', 1);

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
(0, 56, 62, '2026-03-13 01:12:58'),
(0, 60, 1, '2026-03-13 07:07:27'),
(0, 60, 2, '2026-03-13 07:07:27'),
(0, 60, 3, '2026-03-13 07:07:27'),
(0, 60, 4, '2026-03-13 07:07:27'),
(0, 60, 6, '2026-03-13 07:07:27'),
(0, 60, 7, '2026-03-13 07:07:27'),
(0, 60, 8, '2026-03-13 07:07:27'),
(0, 60, 9, '2026-03-13 07:07:27'),
(0, 60, 10, '2026-03-13 07:07:27'),
(0, 60, 11, '2026-03-13 07:07:27'),
(0, 60, 12, '2026-03-13 07:07:28'),
(0, 60, 5, '2026-03-13 07:07:29'),
(0, 60, 13, '2026-03-13 07:07:31'),
(0, 60, 14, '2026-03-13 07:07:31'),
(0, 60, 15, '2026-03-13 07:07:31'),
(0, 60, 16, '2026-03-13 07:07:31'),
(0, 60, 17, '2026-03-13 07:07:31'),
(0, 60, 18, '2026-03-13 07:07:31'),
(0, 60, 19, '2026-03-13 07:07:31'),
(0, 60, 20, '2026-03-13 07:07:31'),
(0, 60, 21, '2026-03-13 07:07:31'),
(0, 60, 22, '2026-03-13 07:07:32'),
(0, 60, 23, '2026-03-13 07:07:32'),
(0, 60, 25, '2026-03-13 07:07:32'),
(0, 60, 26, '2026-03-13 07:07:32'),
(0, 60, 27, '2026-03-13 07:07:32'),
(0, 60, 28, '2026-03-13 07:07:32'),
(0, 60, 29, '2026-03-13 07:07:32'),
(0, 60, 30, '2026-03-13 07:07:32'),
(0, 60, 34, '2026-03-13 07:07:32'),
(0, 60, 35, '2026-03-13 07:07:32'),
(0, 60, 36, '2026-03-13 07:07:32'),
(0, 60, 37, '2026-03-13 07:07:32'),
(0, 60, 38, '2026-03-13 07:07:32'),
(0, 60, 39, '2026-03-13 07:07:32'),
(0, 60, 40, '2026-03-13 07:07:32'),
(0, 60, 41, '2026-03-13 07:07:32'),
(0, 60, 42, '2026-03-13 07:07:33'),
(0, 60, 43, '2026-03-13 07:07:33'),
(0, 60, 44, '2026-03-13 07:07:33'),
(0, 60, 45, '2026-03-13 07:07:33'),
(0, 60, 46, '2026-03-13 07:07:33'),
(0, 60, 47, '2026-03-13 07:07:33'),
(0, 60, 48, '2026-03-13 07:07:33'),
(0, 60, 49, '2026-03-13 07:07:33'),
(0, 60, 50, '2026-03-13 07:07:33'),
(0, 60, 51, '2026-03-13 07:07:33'),
(0, 60, 52, '2026-03-13 07:07:33'),
(0, 60, 53, '2026-03-13 07:07:33'),
(0, 60, 54, '2026-03-13 07:07:33'),
(0, 60, 55, '2026-03-13 07:07:33'),
(0, 60, 56, '2026-03-13 07:07:33'),
(0, 60, 57, '2026-03-13 07:07:33'),
(0, 60, 58, '2026-03-13 07:07:33'),
(0, 60, 59, '2026-03-13 07:07:33'),
(0, 60, 60, '2026-03-13 07:07:34'),
(0, 60, 61, '2026-03-13 07:07:34'),
(0, 60, 62, '2026-03-13 07:07:34'),
(0, 60, 63, '2026-03-13 07:07:34'),
(0, 60, 64, '2026-03-13 07:07:34'),
(0, 60, 65, '2026-03-13 07:07:34'),
(0, 60, 66, '2026-03-13 07:07:34'),
(0, 60, 33, '2026-03-13 07:07:37'),
(0, 60, 32, '2026-03-13 07:07:37'),
(0, 60, 31, '2026-03-13 07:07:38'),
(0, 60, 24, '2026-03-13 07:07:38'),
(0, 61, 1, '2026-03-13 07:50:19'),
(0, 61, 4, '2026-03-13 07:50:19'),
(0, 61, 5, '2026-03-13 07:50:19'),
(0, 61, 6, '2026-03-13 07:50:19'),
(0, 61, 8, '2026-03-13 07:50:19'),
(0, 61, 11, '2026-03-13 07:50:19'),
(0, 61, 16, '2026-03-13 07:50:19'),
(0, 61, 22, '2026-03-13 07:50:19'),
(0, 61, 32, '2026-03-13 07:50:19'),
(0, 61, 36, '2026-03-13 07:50:19'),
(0, 61, 37, '2026-03-13 07:50:19'),
(0, 61, 42, '2026-03-13 07:50:19'),
(0, 61, 49, '2026-03-13 07:50:19'),
(0, 61, 50, '2026-03-13 07:50:19'),
(0, 61, 51, '2026-03-13 07:50:19'),
(0, 61, 52, '2026-03-13 07:50:19'),
(0, 61, 54, '2026-03-13 07:50:20'),
(0, 61, 57, '2026-03-13 07:50:20'),
(0, 61, 58, '2026-03-13 07:50:20'),
(0, 61, 59, '2026-03-13 07:50:20'),
(0, 61, 60, '2026-03-13 07:50:20'),
(0, 61, 61, '2026-03-13 07:50:20'),
(0, 61, 56, '2026-03-13 07:50:22'),
(0, 61, 45, '2026-03-13 07:50:22'),
(0, 61, 44, '2026-03-13 07:50:22'),
(0, 61, 38, '2026-03-13 07:50:22'),
(0, 61, 34, '2026-03-13 07:50:22'),
(0, 61, 30, '2026-03-13 07:50:22'),
(0, 61, 29, '2026-03-13 07:50:22'),
(0, 61, 28, '2026-03-13 07:50:22'),
(0, 61, 27, '2026-03-13 07:50:22'),
(0, 61, 21, '2026-03-13 07:50:22'),
(0, 61, 19, '2026-03-13 07:50:22'),
(0, 61, 17, '2026-03-13 07:50:23'),
(0, 61, 15, '2026-03-13 07:50:23'),
(0, 61, 14, '2026-03-13 07:50:23'),
(0, 61, 12, '2026-03-13 07:50:23'),
(0, 61, 9, '2026-03-13 07:50:23'),
(0, 61, 7, '2026-03-13 07:50:23'),
(0, 61, 3, '2026-03-13 07:50:23'),
(0, 62, 1, '2026-03-13 07:51:21'),
(0, 62, 2, '2026-03-13 07:51:21'),
(0, 62, 3, '2026-03-13 07:51:21'),
(0, 62, 4, '2026-03-13 07:51:29'),
(0, 62, 5, '2026-03-13 07:51:35'),
(0, 62, 6, '2026-03-13 07:51:36'),
(0, 62, 7, '2026-03-13 07:51:37'),
(0, 62, 8, '2026-03-13 07:51:37'),
(0, 62, 9, '2026-03-13 07:51:38'),
(0, 62, 10, '2026-03-13 07:51:38'),
(0, 62, 11, '2026-03-13 07:51:39'),
(0, 62, 12, '2026-03-13 07:51:40'),
(0, 62, 13, '2026-03-13 07:51:40'),
(0, 62, 14, '2026-03-13 07:51:41'),
(0, 62, 15, '2026-03-13 07:51:41'),
(0, 62, 16, '2026-03-13 07:51:42'),
(0, 62, 17, '2026-03-13 07:51:42'),
(0, 62, 18, '2026-03-13 07:51:43'),
(0, 62, 19, '2026-03-13 07:51:43'),
(0, 62, 20, '2026-03-13 07:51:44'),
(0, 62, 21, '2026-03-13 07:51:44'),
(0, 62, 22, '2026-03-13 07:51:46'),
(0, 62, 25, '2026-03-13 07:51:55'),
(0, 62, 26, '2026-03-13 07:51:55'),
(0, 62, 29, '2026-03-13 07:51:55'),
(0, 62, 35, '2026-03-13 07:51:55'),
(0, 62, 37, '2026-03-13 07:51:56'),
(0, 62, 40, '2026-03-13 07:51:56'),
(0, 62, 43, '2026-03-13 07:51:56'),
(0, 62, 44, '2026-03-13 07:51:56'),
(0, 62, 45, '2026-03-13 07:51:56'),
(0, 62, 47, '2026-03-13 07:51:56'),
(0, 62, 48, '2026-03-13 07:51:56'),
(0, 62, 50, '2026-03-13 07:51:56'),
(0, 62, 51, '2026-03-13 07:51:56'),
(0, 62, 52, '2026-03-13 07:51:56'),
(0, 62, 53, '2026-03-13 07:51:56'),
(0, 62, 54, '2026-03-13 07:51:56'),
(0, 62, 55, '2026-03-13 07:51:56'),
(0, 62, 56, '2026-03-13 07:51:57'),
(0, 62, 57, '2026-03-13 07:51:58'),
(0, 62, 58, '2026-03-13 07:51:58'),
(0, 62, 59, '2026-03-13 07:51:58'),
(0, 62, 60, '2026-03-13 07:51:59'),
(0, 62, 61, '2026-03-13 07:51:59'),
(0, 62, 62, '2026-03-13 07:51:59'),
(0, 62, 49, '2026-03-13 07:52:03'),
(0, 62, 46, '2026-03-13 07:52:03'),
(0, 62, 42, '2026-03-13 07:52:04'),
(0, 62, 41, '2026-03-13 07:52:04'),
(0, 62, 39, '2026-03-13 07:52:04'),
(0, 62, 38, '2026-03-13 07:52:05'),
(0, 62, 36, '2026-03-13 07:52:05'),
(0, 62, 34, '2026-03-13 07:52:05'),
(0, 62, 33, '2026-03-13 07:52:05'),
(0, 62, 32, '2026-03-13 07:52:05'),
(0, 62, 31, '2026-03-13 07:52:05'),
(0, 62, 30, '2026-03-13 07:52:05'),
(0, 62, 28, '2026-03-13 07:52:06'),
(0, 62, 27, '2026-03-13 07:52:06'),
(0, 62, 24, '2026-03-13 07:52:07'),
(0, 62, 23, '2026-03-13 07:52:07'),
(0, 63, 1, '2026-03-16 05:30:35'),
(0, 63, 2, '2026-03-16 05:30:35'),
(0, 63, 3, '2026-03-16 05:30:36'),
(0, 63, 4, '2026-03-16 05:30:36'),
(0, 63, 5, '2026-03-16 05:30:36'),
(0, 63, 7, '2026-03-16 05:30:36'),
(0, 63, 8, '2026-03-16 05:30:36'),
(0, 63, 9, '2026-03-16 05:30:36'),
(0, 63, 10, '2026-03-16 05:30:36'),
(0, 63, 11, '2026-03-16 05:30:36'),
(0, 63, 12, '2026-03-16 05:30:36'),
(0, 63, 13, '2026-03-16 05:30:36'),
(0, 63, 14, '2026-03-16 05:30:36'),
(0, 63, 15, '2026-03-16 05:30:36'),
(0, 63, 16, '2026-03-16 05:30:36'),
(0, 63, 17, '2026-03-16 05:30:36'),
(0, 63, 18, '2026-03-16 05:30:36'),
(0, 63, 19, '2026-03-16 05:30:36'),
(0, 63, 20, '2026-03-16 05:30:36'),
(0, 63, 21, '2026-03-16 05:30:36'),
(0, 63, 22, '2026-03-16 05:30:36'),
(0, 63, 23, '2026-03-16 05:30:36'),
(0, 63, 6, '2026-03-16 05:30:40'),
(0, 63, 24, '2026-03-16 05:30:43'),
(0, 63, 25, '2026-03-16 05:30:43'),
(0, 63, 26, '2026-03-16 05:30:43'),
(0, 63, 27, '2026-03-16 05:30:43'),
(0, 63, 28, '2026-03-16 05:30:44'),
(0, 63, 29, '2026-03-16 05:30:44'),
(0, 63, 30, '2026-03-16 05:30:44'),
(0, 63, 31, '2026-03-16 05:30:44'),
(0, 63, 32, '2026-03-16 05:30:44'),
(0, 63, 33, '2026-03-16 05:30:44'),
(0, 63, 34, '2026-03-16 05:30:44'),
(0, 63, 35, '2026-03-16 05:30:44'),
(0, 63, 36, '2026-03-16 05:30:45'),
(0, 63, 37, '2026-03-16 05:30:45'),
(0, 63, 38, '2026-03-16 05:30:45'),
(0, 63, 39, '2026-03-16 05:30:45'),
(0, 63, 40, '2026-03-16 05:30:45'),
(0, 63, 41, '2026-03-16 05:30:45'),
(0, 63, 42, '2026-03-16 05:30:45'),
(0, 63, 43, '2026-03-16 05:30:46'),
(0, 63, 44, '2026-03-16 05:30:46'),
(0, 63, 45, '2026-03-16 05:30:46'),
(0, 63, 46, '2026-03-16 05:30:46'),
(0, 63, 47, '2026-03-16 05:30:47'),
(0, 63, 48, '2026-03-16 05:30:47'),
(0, 63, 49, '2026-03-16 05:30:47'),
(0, 63, 50, '2026-03-16 05:30:47'),
(0, 63, 51, '2026-03-16 05:30:48'),
(0, 63, 52, '2026-03-16 05:30:48'),
(0, 63, 53, '2026-03-16 05:30:48'),
(0, 63, 54, '2026-03-16 05:30:48'),
(0, 63, 55, '2026-03-16 05:30:48'),
(0, 63, 56, '2026-03-16 05:30:49'),
(0, 63, 57, '2026-03-16 05:30:49'),
(0, 63, 58, '2026-03-16 05:30:49'),
(0, 63, 59, '2026-03-16 05:30:49'),
(0, 63, 60, '2026-03-16 05:30:49'),
(0, 63, 61, '2026-03-16 05:30:50'),
(0, 63, 62, '2026-03-16 05:30:50'),
(0, 64, 1, '2026-03-16 05:32:59'),
(0, 64, 2, '2026-03-16 05:33:00'),
(0, 64, 3, '2026-03-16 05:33:00'),
(0, 64, 4, '2026-03-16 05:33:00'),
(0, 64, 5, '2026-03-16 05:33:01'),
(0, 64, 6, '2026-03-16 05:33:01'),
(0, 64, 7, '2026-03-16 05:33:01'),
(0, 64, 8, '2026-03-16 05:33:02'),
(0, 64, 9, '2026-03-16 05:33:02'),
(0, 64, 10, '2026-03-16 05:33:02'),
(0, 64, 11, '2026-03-16 05:33:02'),
(0, 64, 12, '2026-03-16 05:33:03'),
(0, 64, 13, '2026-03-16 05:33:03'),
(0, 64, 14, '2026-03-16 05:33:04'),
(0, 64, 15, '2026-03-16 05:33:04'),
(0, 64, 16, '2026-03-16 05:33:04'),
(0, 64, 17, '2026-03-16 05:33:05'),
(0, 64, 18, '2026-03-16 05:33:05'),
(0, 64, 19, '2026-03-16 05:33:05'),
(0, 64, 20, '2026-03-16 05:33:06'),
(0, 64, 21, '2026-03-16 05:33:06'),
(0, 64, 22, '2026-03-16 05:33:07'),
(0, 64, 23, '2026-03-16 05:33:07'),
(0, 64, 24, '2026-03-16 05:33:07'),
(0, 64, 25, '2026-03-16 05:33:08'),
(0, 64, 26, '2026-03-16 05:33:08'),
(0, 64, 27, '2026-03-16 05:33:08'),
(0, 64, 28, '2026-03-16 05:33:08'),
(0, 64, 29, '2026-03-16 05:33:09'),
(0, 64, 30, '2026-03-16 05:33:09'),
(0, 64, 31, '2026-03-16 05:33:09'),
(0, 64, 32, '2026-03-16 05:33:10'),
(0, 64, 33, '2026-03-16 05:33:10'),
(0, 64, 34, '2026-03-16 05:33:10'),
(0, 64, 35, '2026-03-16 05:33:11'),
(0, 64, 36, '2026-03-16 05:33:11'),
(0, 64, 37, '2026-03-16 05:33:11'),
(0, 64, 38, '2026-03-16 05:33:11'),
(0, 64, 39, '2026-03-16 05:33:12'),
(0, 65, 1, '2026-03-16 06:23:54'),
(0, 65, 2, '2026-03-16 06:23:55'),
(0, 65, 3, '2026-03-16 06:23:55'),
(0, 65, 4, '2026-03-16 06:23:55'),
(0, 65, 5, '2026-03-16 06:23:56'),
(0, 65, 6, '2026-03-16 06:23:56'),
(0, 65, 7, '2026-03-16 06:23:56'),
(0, 65, 8, '2026-03-16 06:23:57'),
(0, 65, 9, '2026-03-16 06:23:57'),
(0, 65, 10, '2026-03-16 06:23:57'),
(0, 65, 11, '2026-03-16 06:23:57'),
(0, 65, 12, '2026-03-16 06:23:58'),
(0, 65, 13, '2026-03-16 06:23:58'),
(0, 65, 14, '2026-03-16 06:23:58'),
(0, 65, 15, '2026-03-16 06:23:58'),
(0, 65, 16, '2026-03-16 06:23:59'),
(0, 65, 17, '2026-03-16 06:23:59'),
(0, 65, 18, '2026-03-16 06:23:59'),
(0, 65, 19, '2026-03-16 06:24:00'),
(0, 65, 20, '2026-03-16 06:24:00'),
(0, 65, 21, '2026-03-16 06:24:00'),
(0, 65, 22, '2026-03-16 06:24:01'),
(0, 65, 23, '2026-03-16 06:24:01'),
(0, 65, 24, '2026-03-16 06:24:01'),
(0, 65, 25, '2026-03-16 06:24:01'),
(0, 65, 26, '2026-03-16 06:24:02'),
(0, 65, 27, '2026-03-16 06:24:02'),
(0, 65, 28, '2026-03-16 06:24:02'),
(0, 65, 29, '2026-03-16 06:24:03'),
(0, 65, 30, '2026-03-16 06:24:03'),
(0, 65, 31, '2026-03-16 06:24:03'),
(0, 65, 32, '2026-03-16 06:24:03'),
(0, 65, 33, '2026-03-16 06:24:04'),
(0, 65, 34, '2026-03-16 06:24:04'),
(0, 65, 35, '2026-03-16 06:24:04'),
(0, 65, 36, '2026-03-16 06:24:04'),
(0, 65, 37, '2026-03-16 06:24:05'),
(0, 65, 38, '2026-03-16 06:24:05'),
(0, 65, 39, '2026-03-16 06:24:06'),
(0, 65, 40, '2026-03-16 06:24:06'),
(0, 65, 41, '2026-03-16 06:24:06'),
(0, 65, 42, '2026-03-16 06:24:06'),
(0, 65, 43, '2026-03-16 06:24:06'),
(0, 65, 44, '2026-03-16 06:24:07'),
(0, 65, 45, '2026-03-16 06:24:07'),
(0, 65, 46, '2026-03-16 06:24:07'),
(0, 65, 47, '2026-03-16 06:24:08'),
(0, 65, 48, '2026-03-16 06:24:08'),
(0, 65, 49, '2026-03-16 06:24:08'),
(0, 65, 50, '2026-03-16 06:24:08'),
(0, 65, 51, '2026-03-16 06:24:09'),
(0, 65, 52, '2026-03-16 06:24:09'),
(0, 65, 53, '2026-03-16 06:24:09'),
(0, 65, 54, '2026-03-16 06:24:10'),
(0, 65, 55, '2026-03-16 06:24:10'),
(0, 65, 56, '2026-03-16 06:24:10'),
(0, 65, 57, '2026-03-16 06:24:10'),
(0, 65, 58, '2026-03-16 06:24:11'),
(0, 65, 59, '2026-03-16 06:24:11'),
(0, 65, 60, '2026-03-16 06:24:11'),
(0, 65, 61, '2026-03-16 06:24:11'),
(0, 65, 62, '2026-03-16 06:24:12'),
(0, 66, 1, '2026-03-16 06:39:24'),
(0, 66, 2, '2026-03-16 06:39:26'),
(0, 66, 3, '2026-03-16 06:39:26'),
(0, 66, 4, '2026-03-16 06:39:27'),
(0, 66, 5, '2026-03-16 06:39:27'),
(0, 66, 6, '2026-03-16 06:39:27'),
(0, 66, 7, '2026-03-16 06:39:29'),
(0, 66, 8, '2026-03-16 06:39:29'),
(0, 66, 9, '2026-03-16 06:39:29'),
(0, 66, 10, '2026-03-16 06:39:29'),
(0, 66, 11, '2026-03-16 06:39:29'),
(0, 66, 12, '2026-03-16 06:39:29'),
(0, 66, 13, '2026-03-16 06:39:29'),
(0, 66, 14, '2026-03-16 06:39:29'),
(0, 66, 15, '2026-03-16 06:39:29'),
(0, 66, 16, '2026-03-16 06:39:29'),
(0, 66, 17, '2026-03-16 06:39:29'),
(0, 66, 18, '2026-03-16 06:39:29'),
(0, 66, 19, '2026-03-16 06:39:29'),
(0, 66, 20, '2026-03-16 06:39:29'),
(0, 66, 21, '2026-03-16 06:39:29'),
(0, 66, 22, '2026-03-16 06:39:30'),
(0, 66, 23, '2026-03-16 06:39:30'),
(0, 66, 24, '2026-03-16 06:39:30'),
(0, 66, 25, '2026-03-16 06:39:30'),
(0, 66, 26, '2026-03-16 06:39:30'),
(0, 66, 27, '2026-03-16 06:39:30'),
(0, 66, 28, '2026-03-16 06:39:30'),
(0, 66, 29, '2026-03-16 06:39:30'),
(0, 66, 30, '2026-03-16 06:39:30'),
(0, 66, 31, '2026-03-16 06:39:31'),
(0, 66, 32, '2026-03-16 06:39:31'),
(0, 66, 33, '2026-03-16 06:39:31'),
(0, 66, 34, '2026-03-16 06:39:31'),
(0, 66, 35, '2026-03-16 06:39:31'),
(0, 66, 36, '2026-03-16 06:39:32'),
(0, 66, 37, '2026-03-16 06:39:32'),
(0, 66, 38, '2026-03-16 06:39:32'),
(0, 66, 39, '2026-03-16 06:39:32'),
(0, 68, 1, '2026-03-16 07:20:18'),
(0, 69, 1, '2026-03-16 07:20:25'),
(0, 69, 2, '2026-03-16 07:20:26'),
(0, 69, 3, '2026-03-16 07:20:26'),
(0, 69, 4, '2026-03-16 07:20:27'),
(0, 69, 5, '2026-03-16 07:20:27'),
(0, 69, 6, '2026-03-16 07:20:27'),
(0, 69, 7, '2026-03-16 07:20:27'),
(0, 69, 8, '2026-03-16 07:20:27'),
(0, 69, 9, '2026-03-16 07:20:27'),
(0, 69, 11, '2026-03-16 07:20:27'),
(0, 69, 12, '2026-03-16 07:20:27'),
(0, 69, 13, '2026-03-16 07:20:27'),
(0, 69, 14, '2026-03-16 07:20:27'),
(0, 69, 15, '2026-03-16 07:20:28'),
(0, 69, 16, '2026-03-16 07:20:28'),
(0, 69, 17, '2026-03-16 07:20:28'),
(0, 69, 18, '2026-03-16 07:20:28'),
(0, 69, 19, '2026-03-16 07:20:28'),
(0, 69, 20, '2026-03-16 07:20:29'),
(0, 69, 21, '2026-03-16 07:20:29'),
(0, 69, 22, '2026-03-16 07:20:30'),
(0, 69, 23, '2026-03-16 07:20:30'),
(0, 69, 24, '2026-03-16 07:20:30'),
(0, 69, 26, '2026-03-16 07:20:30'),
(0, 69, 28, '2026-03-16 07:20:30'),
(0, 69, 29, '2026-03-16 07:20:30'),
(0, 69, 30, '2026-03-16 07:20:30'),
(0, 69, 31, '2026-03-16 07:20:31'),
(0, 69, 32, '2026-03-16 07:20:31'),
(0, 69, 33, '2026-03-16 07:20:31'),
(0, 69, 34, '2026-03-16 07:20:31'),
(0, 69, 35, '2026-03-16 07:20:32'),
(0, 69, 36, '2026-03-16 07:20:33'),
(0, 69, 37, '2026-03-16 07:20:33'),
(0, 69, 38, '2026-03-16 07:20:33'),
(0, 69, 39, '2026-03-16 07:20:33'),
(0, 69, 27, '2026-03-16 07:20:34'),
(0, 69, 10, '2026-03-16 07:20:38'),
(0, 69, 25, '2026-03-16 07:20:41'),
(0, 70, 1, '2026-03-16 08:21:33'),
(0, 70, 2, '2026-03-16 08:21:34'),
(0, 70, 3, '2026-03-16 08:21:34'),
(0, 70, 4, '2026-03-16 08:21:34'),
(0, 70, 5, '2026-03-16 08:21:35'),
(0, 70, 6, '2026-03-16 08:21:35'),
(0, 70, 7, '2026-03-16 08:21:35'),
(0, 70, 8, '2026-03-16 08:21:35'),
(0, 70, 9, '2026-03-16 08:21:35'),
(0, 70, 10, '2026-03-16 08:21:40'),
(0, 70, 11, '2026-03-16 08:21:40'),
(0, 70, 12, '2026-03-16 08:21:40'),
(0, 70, 13, '2026-03-16 08:21:40'),
(0, 70, 14, '2026-03-16 08:21:41'),
(0, 70, 15, '2026-03-16 08:21:41'),
(0, 70, 16, '2026-03-16 08:21:41'),
(0, 70, 17, '2026-03-16 08:21:45'),
(0, 70, 18, '2026-03-16 08:21:46'),
(0, 70, 19, '2026-03-16 08:21:46'),
(0, 70, 20, '2026-03-16 08:21:46'),
(0, 70, 21, '2026-03-16 08:21:46'),
(0, 70, 22, '2026-03-16 08:21:46'),
(0, 70, 23, '2026-03-16 08:21:46'),
(0, 70, 24, '2026-03-16 08:21:46'),
(0, 70, 25, '2026-03-16 08:21:46'),
(0, 70, 26, '2026-03-16 08:21:46'),
(0, 70, 27, '2026-03-16 08:21:47'),
(0, 70, 28, '2026-03-16 08:21:47'),
(0, 70, 29, '2026-03-16 08:21:47'),
(0, 70, 30, '2026-03-16 08:21:47'),
(0, 70, 31, '2026-03-16 08:21:47'),
(0, 70, 32, '2026-03-16 08:21:47'),
(0, 70, 33, '2026-03-16 08:21:47'),
(0, 70, 34, '2026-03-16 08:21:47'),
(0, 70, 35, '2026-03-16 08:21:47'),
(0, 70, 36, '2026-03-16 08:21:47'),
(0, 70, 37, '2026-03-16 08:21:47'),
(0, 70, 38, '2026-03-16 08:21:47'),
(0, 70, 39, '2026-03-16 08:21:47'),
(0, 71, 1, '2026-03-17 06:07:20'),
(0, 71, 2, '2026-03-17 06:07:24'),
(0, 71, 3, '2026-03-17 06:07:27'),
(0, 71, 4, '2026-03-17 06:07:27'),
(0, 71, 5, '2026-03-17 06:07:28'),
(0, 71, 6, '2026-03-17 06:07:28'),
(0, 71, 7, '2026-03-17 06:07:29'),
(0, 71, 8, '2026-03-17 06:07:29'),
(0, 71, 9, '2026-03-17 06:07:29'),
(0, 71, 10, '2026-03-17 06:07:30'),
(0, 71, 11, '2026-03-17 06:07:30'),
(0, 71, 12, '2026-03-17 06:07:31'),
(0, 71, 13, '2026-03-17 06:07:32'),
(0, 71, 14, '2026-03-17 06:07:32'),
(0, 71, 15, '2026-03-17 06:07:33'),
(0, 71, 16, '2026-03-17 06:07:33'),
(0, 71, 17, '2026-03-17 06:07:34'),
(0, 71, 18, '2026-03-17 06:07:34'),
(0, 71, 19, '2026-03-17 06:07:35'),
(0, 71, 20, '2026-03-17 06:07:35'),
(0, 71, 21, '2026-03-17 06:07:36'),
(0, 71, 22, '2026-03-17 06:07:36'),
(0, 71, 23, '2026-03-17 06:07:36'),
(0, 71, 24, '2026-03-17 06:07:37'),
(0, 71, 25, '2026-03-17 06:07:37'),
(0, 71, 26, '2026-03-17 06:07:38'),
(0, 71, 27, '2026-03-17 06:07:38'),
(0, 71, 28, '2026-03-17 06:07:39'),
(0, 71, 29, '2026-03-17 06:07:41'),
(0, 71, 30, '2026-03-17 06:07:42'),
(0, 71, 31, '2026-03-17 06:07:42'),
(0, 71, 32, '2026-03-17 06:07:44'),
(0, 71, 33, '2026-03-17 06:07:44'),
(0, 71, 34, '2026-03-17 06:07:45'),
(0, 71, 35, '2026-03-17 06:07:45'),
(0, 71, 36, '2026-03-17 06:07:46'),
(0, 71, 37, '2026-03-17 06:07:46'),
(0, 71, 38, '2026-03-17 06:07:47'),
(0, 71, 39, '2026-03-17 06:07:47'),
(0, 71, 40, '2026-03-17 06:07:47'),
(0, 71, 41, '2026-03-17 06:07:48'),
(0, 71, 42, '2026-03-17 06:07:48'),
(0, 71, 43, '2026-03-17 06:07:49'),
(0, 71, 44, '2026-03-17 06:07:49'),
(0, 71, 45, '2026-03-17 06:07:50'),
(0, 71, 46, '2026-03-17 06:07:50'),
(0, 71, 47, '2026-03-17 06:07:50'),
(0, 71, 48, '2026-03-17 06:07:51'),
(0, 71, 49, '2026-03-17 06:07:51'),
(0, 71, 50, '2026-03-17 06:07:52'),
(0, 71, 51, '2026-03-17 06:07:52'),
(0, 71, 52, '2026-03-17 06:07:52'),
(0, 71, 53, '2026-03-17 06:07:53'),
(0, 71, 54, '2026-03-17 06:07:53'),
(0, 71, 55, '2026-03-17 06:07:54'),
(0, 71, 56, '2026-03-17 06:07:54'),
(0, 71, 57, '2026-03-17 06:07:55'),
(0, 71, 58, '2026-03-17 06:07:55'),
(0, 71, 59, '2026-03-17 06:07:55'),
(0, 71, 60, '2026-03-17 06:07:56'),
(0, 71, 61, '2026-03-17 06:07:56'),
(0, 71, 62, '2026-03-17 06:07:56'),
(0, 71, 63, '2026-03-17 06:07:57'),
(0, 71, 64, '2026-03-17 06:07:57'),
(0, 71, 65, '2026-03-17 06:07:59'),
(0, 71, 66, '2026-03-17 06:07:59'),
(0, 72, 1, '2026-03-18 07:48:13'),
(0, 72, 2, '2026-03-18 07:49:14'),
(0, 72, 3, '2026-03-18 07:49:16'),
(0, 72, 4, '2026-03-18 07:49:16'),
(0, 72, 5, '2026-03-18 07:49:17'),
(0, 72, 6, '2026-03-18 07:49:17'),
(0, 72, 7, '2026-03-18 07:49:18'),
(0, 72, 8, '2026-03-18 07:49:18'),
(0, 72, 9, '2026-03-18 07:49:18'),
(0, 72, 10, '2026-03-18 07:49:19'),
(0, 72, 11, '2026-03-18 07:49:19'),
(0, 72, 12, '2026-03-18 07:49:20'),
(0, 72, 13, '2026-03-18 07:49:21');

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
-- Table structure for table `training_requests`
--

CREATE TABLE `training_requests` (
  `id` int(11) NOT NULL,
  `training_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `date_start` date NOT NULL,
  `date_end` date NOT NULL,
  `location_type` varchar(50) DEFAULT NULL,
  `hospital_order_no` varchar(100) NOT NULL,
  `amount` decimal(10,2) DEFAULT 0.00,
  `late_filing` tinyint(1) DEFAULT 0,
  `official_business` tinyint(1) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `requester_id` int(11) DEFAULT NULL,
  `resched_reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `training_requests`
--

INSERT INTO `training_requests` (`id`, `training_type`, `title`, `date_start`, `date_end`, `location_type`, `hospital_order_no`, `amount`, `late_filing`, `official_business`, `remarks`, `requester_id`, `resched_reason`, `status`, `created_at`, `updated_at`) VALUES
(1, 'External', 'asdasdas', '2026-03-25', '2026-03-28', 'international', '', 0.00, 0, 1, '0', 97, NULL, 'pending', '2026-03-24 16:50:49', NULL),
(2, 'External', 'asdasdasd', '2026-03-26', '2026-04-08', 'international', '', 0.00, 0, 0, '0', 97, NULL, 'pending', '2026-03-24 16:51:14', NULL);

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
(76, 'user', '$2y$10$KwCQY6078.5GGWaXOWv3B.kxmomQZJYosIEpeN8YVJu8Yy.6Amysm', 'User', 'User', 'user@gmail.com', 'user', '2026-03-12 03:13:47', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(77, 'user1', '$2y$10$a1qcZxaMZ1Zq.miP/VB/luw5ia1i/rdDALUXHrtitWvsEJIbGiLL.', 'user1', 'user1', 'user1', 'user', '2026-03-12 03:14:38', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(80, 'user3', '$2y$10$51PKxLrYdyhsJeGEBcOsgOTWSxABDvFXFwFHZ1QB4ZEn1rGn6Nnnm', 'user3', 'user3', 'user3@mail.com', 'user', '2026-03-12 05:34:35', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(81, 'user4', '$2y$10$D0anqyyNSuFksYUpxdm6O.RwJa2f6WkRwxh.IGyffilA7e52l3MYu', 'user4', 'user4', 'user4@mail.zxc', 'user', '2026-03-12 05:34:59', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(82, 'user5', '$2y$10$byqELjtKf2ytKo0myGF.M.Y9RXN4G5sHGxHKRM/jwtMEvfHI7Gifi', 'user5', 'user5', 'user5@gmail.com', 'user', '2026-03-12 05:41:04', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(85, 'asdasdasda', '$2y$10$E/p8NTIRzUvqgjAjnYzyX.27oRr9OXe.pND4u.ECrFXVf03kvSQzO', 'sdadasd', 'dasdasdasd', 'asdasdasd@gmail.com', 'admin', '2026-03-18 01:44:00', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(86, 'deivision example and department example', '$2y$10$OLWD5sySoLGZiZxEh3LrE.tRb4ietZYSGUnuqpLCH5iZtSUKk18pe', 'deivision example and department example', 'deivision example and department example', 'adjashdaskhdkja@gmail.com', 'user', '2026-03-18 01:57:11', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(87, 'awitawitawitawitawit', '$2y$10$jxa6pJ/KMxhivOpCz.kHXOXvU0g2M.7mVQeByryeJaAgQ0OG4vQyC', 'awitawitawitawitawit', 'awitawitawitawitawit', 'awitawitawitawitawit@adasd.copm', 'proponent', '2026-03-18 03:15:58', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(88, 'exampleof commitehere', '$2y$10$5Xlj7p8Y9ca1IswXbi2FNunGW6rqeKhwjN5hdVBfg2EMhP8aVi3va', 'exampleof commitehere', 'exampleof commitehere', 'exampleofcommitehere@gmail.com', 'proponent', '2026-03-18 03:48:57', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(89, 'exampleuser', '$2y$10$5MR1AJsZvsCUIxYHfFiHquxH7BaZj7FpDTVjmI.FlH7omtQ.g9MHy', 'exampleuser', 'exampleuser', 'exampleuse@gmail.com', 'user', '2026-03-18 03:52:48', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(94, 'bbbbbbbbbbbbbbbb', '$2y$10$OI8ivIehU1f7nLxRFNv1FeHkJb1knarWVvQs4TrNhNzsboRk9kwGC', 'bbbbbbbbbbbbbbbbbbbbb', 'bbbbbbbbbbb', 'bbbbbbbbbbb@gmail.com', 'proponent', '2026-03-18 05:22:26', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(95, 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', '$2y$10$x1T8MnpAVkOUiGNffYUtpeMv0G4LjZtZJKnAnUwOAKenldLQqPNcO', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'xxxxxxxxxxxxxxxxxxx', 'xxxxxxxxxxxxxxxxxxxxxxxxxx@gmail.com', 'proponent', '2026-03-18 05:22:51', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(96, 'asdasdasdasdasdasda', '$2y$10$N4QD0RZiWcqELYfJgMLDH.0EtfY72GQC0.Uwrw1XKWnjs4ezUMyUy', 'asdasdasdsa', 'dasdasdasdasd', 'asdasdasdassd@gmail.com', 'user', '2026-03-18 05:23:15', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(97, 'admin', '$2y$10$NCSfBuMOdDJF7qNc/uAufeWhvEUBmSKZEm41.F7ahR4NJgj/KbMbO', 'Johnemmanulle', 'DL', 'gfaith209@gmail.com', 'user', '2026-03-18 08:31:32', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(98, 'asdasd', '$2y$10$544HNO.Fq0MC1dDz94QbfuSJCHspZpj/RcdD3kwfYJ7g2izNM0OFW', 'asdasdasdasd', 'asdasdasda', 'sdadasda@gm.com', 'proponent', '2026-03-19 00:53:28', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(99, 'ssssssssssssssssss', '$2y$10$wiSbAzOHstws/tgCcI3vbej3E4ULql9jeCjfvuarZyJJh5UJ1IVKO', 'ssssssssssssssssss', 'ssssssssssssssssss', 'ssssssssssssssssss@gm.com', 'proponent', '2026-03-19 00:53:47', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(101, 'imman', '$2y$10$QV8OYKGPAauLwB/b4tXEPedtMbWNAnl7jR8pwXS0cFqhJOKiQkkd6', 'imman', 'imman', 'imman@pogidawsya.com', 'user', '2026-03-19 01:44:53', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(102, 'testdepartments', '$2y$10$koNC7D8un48YIl0hV9eaGOneqNTwNBjv4jmKne/f7c7OfvcHmbUiS', 'test', 'departments', 'gplankton1@gmail.com', 'user', '2026-03-19 03:56:36', NULL, 0, NULL, NULL, 'pending', 1, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_departments`
--

CREATE TABLE `user_departments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `committee_id` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_departments`
--

INSERT INTO `user_departments` (`id`, `user_id`, `dept_id`, `committee_id`, `assigned_at`) VALUES
(2, 101, 5, NULL, '2026-03-19 02:31:41'),
(7, 76, 5, NULL, '2026-03-19 03:26:31'),
(8, 102, 5, NULL, '2026-03-19 03:56:36'),
(9, 5, NULL, 13, '2026-03-19 06:06:07'),
(10, 1, NULL, 10, '2026-03-19 06:07:29'),
(11, 1, NULL, 18, '2026-03-19 06:07:29'),
(12, 22, NULL, 3, '2026-03-19 07:02:07'),
(13, 22, NULL, 4, '2026-03-19 07:02:07'),
(14, 22, NULL, 16, '2026-03-19 07:02:07');

-- --------------------------------------------------------

--
-- Table structure for table `video_progress`
--

CREATE TABLE `video_progress` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `video_position` int(11) DEFAULT 0,
  `completed` tinyint(4) DEFAULT 0,
  `last_watched` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `video_progress`
--

INSERT INTO `video_progress` (`id`, `enrollment_id`, `video_position`, `completed`, `last_watched`) VALUES
(1, 69, 39, 1, '2026-03-16 15:48:52'),
(2, 70, 39, 1, '2026-03-16 16:22:31');

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
-- Indexes for table `committees`
--
ALTER TABLE `committees`
  ADD PRIMARY KEY (`id`);

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
  ADD PRIMARY KEY (`course_id`,`committee_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `depts`
--
ALTER TABLE `depts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_department_id` (`department_id`);

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
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `committee_id` (`committee_id`);

--
-- Indexes for table `module_pdf_progress`
--
ALTER TABLE `module_pdf_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_progress_page` (`progress_id`,`page_number`),
  ADD KEY `progress_id` (`progress_id`);

--
-- Indexes for table `module_progress`
--
ALTER TABLE `module_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_module_user` (`module_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

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
-- Indexes for table `training_requests`
--
ALTER TABLE `training_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requester_id` (`requester_id`);

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
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `dept_id` (`dept_id`),
  ADD KEY `committee_id` (`committee_id`);

--
-- Indexes for table `video_progress`
--
ALTER TABLE `video_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`enrollment_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `assessment_options`
--
ALTER TABLE `assessment_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `committees`
--
ALTER TABLE `committees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `depts`
--
ALTER TABLE `depts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `edit`
--
ALTER TABLE `edit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

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
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `module_pdf_progress`
--
ALTER TABLE `module_pdf_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=231;

--
-- AUTO_INCREMENT for table `module_progress`
--
ALTER TABLE `module_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
-- AUTO_INCREMENT for table `training_requests`
--
ALTER TABLE `training_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `user_departments`
--
ALTER TABLE `user_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `video_progress`
--
ALTER TABLE `video_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  ADD CONSTRAINT `course_departments_committee_fk` FOREIGN KEY (`committee_id`) REFERENCES `committees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_departments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `depts`
--
ALTER TABLE `depts`
  ADD CONSTRAINT `fk_depts_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
-- Constraints for table `modules`
--
ALTER TABLE `modules`
  ADD CONSTRAINT `modules_committee_fk` FOREIGN KEY (`committee_id`) REFERENCES `committees` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `module_pdf_progress`
--
ALTER TABLE `module_pdf_progress`
  ADD CONSTRAINT `module_pdf_progress_ibfk_1` FOREIGN KEY (`progress_id`) REFERENCES `module_progress` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `module_progress`
--
ALTER TABLE `module_progress`
  ADD CONSTRAINT `module_progress_ibfk_1` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `module_progress_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `user_departments_ibfk_2` FOREIGN KEY (`dept_id`) REFERENCES `depts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_departments_ibfk_3` FOREIGN KEY (`committee_id`) REFERENCES `committees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `video_progress`
--
ALTER TABLE `video_progress`
  ADD CONSTRAINT `video_progress_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

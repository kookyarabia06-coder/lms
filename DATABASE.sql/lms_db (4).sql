-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 05, 2026 at 09:43 AM
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
(3, 42, '', '', 70, 0, 1, '2026-03-05 08:30:40', NULL);

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
  `score` int(11) DEFAULT 0,
  `passed` tinyint(1) DEFAULT 0,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `time_taken` int(11) DEFAULT NULL COMMENT 'Time taken in seconds',
  `attempt_number` int(11) DEFAULT 1
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
(34, 'courses', 42, 'INSERT', NULL, '{\"title\": \"qweqweqwe\", \"description\": \"qweqweqwe\", \"summary\": \"qweqweqweqweqweqweqweqweqweqweqweqweqweqweqwe\", \"thumbnail\": null, \"file_pdf\": null, \"file_video\": null, \"proponent_id\": 5, \"expires_at\": null, \"is_active\": 1}', NULL, NULL, '2026-03-05 08:30:40');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `user_role` enum('admin','proponent','user','superadmin') NOT NULL,
  `action` enum('ADD','EDIT','DELETE','VIEW','ENROLL','COMPLETE','LOGIN','LOGOUT') NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `course_id`, `username`, `user_role`, `action`, `table_name`, `record_id`, `old_data`, `new_data`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 5, NULL, 'alvin', 'admin', 'ADD', 'courses', 20, NULL, '{\"id\":20,\"title\":\"Course\"}', 'Added course: Course', NULL, NULL, '2026-02-13 05:25:40'),
(2, 5, NULL, 'alvin', 'admin', 'ADD', 'courses', 21, NULL, '{\"id\":21,\"title\":\"Lorem Ipsum\"}', 'Added course: Lorem Ipsum', NULL, NULL, '2026-02-13 05:26:34'),
(3, 8, NULL, 'proponent', 'proponent', 'ADD', 'courses', 22, NULL, '{\"id\":22,\"title\":\"proponent\"}', 'Added course: proponent', NULL, NULL, '2026-02-13 05:27:15'),
(4, 8, NULL, 'proponent', 'proponent', 'ADD', 'courses', 23, NULL, '{\"id\":23,\"title\":\"Lorem ipsum\"}', 'Added course: Lorem ipsum', NULL, NULL, '2026-02-13 05:28:11'),
(5, 8, NULL, 'proponent', 'proponent', 'ADD', 'courses', 24, NULL, '{\"id\":24,\"title\":\"Lorem Ipsum pro max\"}', 'Added course: Lorem Ipsum pro max', NULL, NULL, '2026-02-13 05:28:49'),
(6, 22, NULL, 'pro', 'proponent', 'ADD', 'courses', 25, NULL, '{\"id\":25,\"title\":\"module one\"}', 'Added course: module one', NULL, NULL, '2026-02-18 07:46:38'),
(7, 24, NULL, 'admin', 'admin', 'ADD', 'courses', 26, NULL, '{\"id\":26,\"title\":\"dataset\"}', 'Added course: dataset', NULL, NULL, '2026-02-20 03:28:06'),
(8, 33, NULL, 'superadmin', 'superadmin', 'ADD', 'courses', 27, NULL, '{\"id\":27,\"title\":\"sdadadadasdsadsassssssssssssssssssssssssssssssssssssssssssssss\"}', 'Added course: sdadadadasdsadsassssssssssssssssssssssssssssssssssssssssssssss', NULL, NULL, '2026-02-21 06:32:22');

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
(38, 'EXAMPLE', 'EXAMPLE', NULL, 33, NULL, NULL, '2026-03-05 03:14:56', NULL, NULL, 1, 'EXAMPLEEXAMPLEEXAMPLE', NULL),
(40, '', NULL, NULL, 22, NULL, NULL, '2026-03-05 07:20:09', '2026-03-05 07:29:45', NULL, 1, NULL, NULL),
(41, 'sadasdasdasd', 'asdasdasdasdas', NULL, 33, NULL, NULL, '2026-03-05 07:32:37', NULL, NULL, 1, 'dasdasdasd', NULL),
(42, 'qweqweqwe', 'qweqweqwe', NULL, 5, NULL, NULL, '2026-03-05 08:30:40', NULL, NULL, 1, 'qweqweqweqweqweqweqweqweqweqweqweqweqweqweqwe', NULL);

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
(38, 2);

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
(26, 14, 42, '2026-03-05 08:36:47', '2026-03-05 08:37:22', NULL, 50.00, 0, 0, 0, 0, 0, 0, 'completed', 65);

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
(58, 'test', '$2y$10$xBE0X7W4Gr1BvpsQXfoQYe5Kyz8fl.syAbdKHrSyBg68pWuTNgtJO', 'Gerry', 'Tigolo', 'gerrytigolo101@gmail.com', 'user', '2026-02-20 07:47:30', NULL, 0, NULL, NULL, 'confirmed', 1, 1, '1'),
(61, 'departs', '$2y$10$WxEspGCQ0PSoyysgaGQg4esHXfTPiUbf5rkIUec.Z0mmftwI5WoHC', 'departs', 'departs', 'departs@gmail.com', 'user', '2026-02-21 03:32:33', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(62, 'llllllllllllll', '$2y$10$xL5.khhMuHNUfUJkLqWknebpdYaoVtaHvoYsmC3JUPJ/wGQxNAvjq', 'llllllllllllllllllllll', 'lllllllllllllllllllllll', 'lllllllllllllllll@gmail.com', 'user', '2026-02-21 22:27:51', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(63, 'try', '$2y$10$NR3QJ7ABB/QXRABqV17RFO/Jf6CVnfzniDG4dUBI4Tl6FlFV4qlaO', 'try', 'try', 'trydept@gmail.com', 'user', '2026-02-21 22:29:44', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(64, 'asdpijasdlihsadlksahdlh', '$2y$10$vPSrfXYi2ct8R7ddSdmDrebIx0HhK3h7rGqLr3LcAUN9ZHmzEHMB.', 'LKJSALJDLASKJDLAKSJLASKJD', 'LJLKASJDLASJDLKASJDLJK', 'LKASJDALSKDJLAKSDJLKJ@GMAI.COM', 'user', '2026-02-23 05:07:35', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(67, 'prop', '$2y$10$d45oGnjPOA9RF0Qqiulsk..dsD1wm16I2dCoCnAJjeKnOXAgsZarO', 'prop', 'prop', 'prop@gmail.com', 'proponent', '2026-03-04 07:24:26', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(68, 'propuser', '$2y$10$j6VGYkLnhNWEhg9l9N8Y6O37atuA6DsleRnPzIoPDP6DPw/170bKS', 'propuser', 'propuser', 'propuser@gmail.com', 'user', '2026-03-04 07:49:53', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(69, 'proppuser', '$2y$10$zTHLjO5O/qve4P1NZbuPxebZEwFsaviH6r3SXju3hxMxmyh4NlmL.', 'proppuser', 'proppuser', 'proppuser@gmail.com', 'user', '2026-03-05 00:18:46', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL);

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
(61, 1),
(61, 2),
(61, 3),
(61, 4),
(61, 5),
(62, 1),
(62, 3),
(62, 5),
(62, 6),
(63, 2),
(64, 5),
(64, 6),
(64, 7),
(67, 1),
(67, 3),
(67, 5),
(67, 7);

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
  ADD KEY `user_id` (`user_id`);

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
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_course` (`course_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_table` (`table_name`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_record` (`record_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

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
-- Constraints for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  ADD CONSTRAINT `assessment_attempts_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_attempts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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

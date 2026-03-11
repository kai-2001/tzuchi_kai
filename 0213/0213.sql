-- --------------------------------------------------------
-- 主機:                           127.0.0.1
-- 伺服器版本:                        10.11.15-MariaDB - MariaDB Server
-- 伺服器作業系統:                      Win64
-- HeidiSQL 版本:                  12.11.0.7065
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- 傾印 portal_db 的資料庫結構
CREATE DATABASE IF NOT EXISTS `portal_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `portal_db`;

-- 傾印  資料表 portal_db.cohort_dimensions 結構
CREATE TABLE IF NOT EXISTS `cohort_dimensions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dimension_type_id` int(11) NOT NULL COMMENT '關聯 dimension_types.id',
  `moodle_cohort_id` int(11) NOT NULL COMMENT 'Moodle 的 cohort.id',
  `display_name` varchar(100) DEFAULT NULL COMMENT '顯示名稱（可選）',
  `parent_category_id` int(11) DEFAULT NULL,
  `parent_cohort_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_dim_cohort` (`dimension_type_id`,`moodle_cohort_id`),
  KEY `idx_parent_cohort` (`parent_cohort_id`),
  CONSTRAINT `cohort_dimensions_ibfk_1` FOREIGN KEY (`dimension_type_id`) REFERENCES `dimension_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='群組維度對照';

-- 正在傾印表格  portal_db.cohort_dimensions 的資料：~27 rows (近似值)
INSERT INTO `cohort_dimensions` (`id`, `dimension_type_id`, `moodle_cohort_id`, `display_name`, `parent_category_id`, `parent_cohort_id`, `sort_order`, `created_at`) VALUES
	(1, 1, 22, '助產職類', NULL, 1, 0, '2026-01-27 01:18:13'),
	(23, 1, 11, '呼吸治療', NULL, 1, 0, '2026-01-27 01:22:39'),
	(24, 1, 18, '營養職類', NULL, 1, 0, '2026-01-27 01:22:39'),
	(25, 1, 17, '職能治療', NULL, 1, 0, '2026-01-27 01:22:40'),
	(26, 1, 19, '物理治療', NULL, 1, 0, '2026-01-27 01:22:40'),
	(27, 1, 21, '牙體技術', NULL, 1, 0, '2026-01-27 01:22:41'),
	(28, 1, 12, '聽力職類', NULL, 1, 0, '2026-01-27 01:22:41'),
	(29, 1, 13, '臨床心理', NULL, 1, 0, '2026-01-27 01:22:42'),
	(30, 1, 14, '藥事職類', NULL, 1, 0, '2026-01-27 01:22:42'),
	(31, 1, 10, '護理職類', NULL, 1, 0, '2026-01-27 01:22:42'),
	(32, 1, 23, '諮商心理', NULL, 1, 0, '2026-01-27 01:22:43'),
	(33, 1, 16, '語言治療', NULL, 1, 0, '2026-01-27 01:22:43'),
	(34, 1, 20, '醫事放射', NULL, 1, 0, '2026-01-27 01:22:43'),
	(35, 1, 15, '醫事檢驗', NULL, 1, 0, '2026-01-27 01:22:44'),
	(36, 1, 24, '內科', NULL, 10, 0, '2026-01-27 02:29:01'),
	(44, 4, 1, '台北慈濟', NULL, NULL, 0, '2026-01-29 05:06:37'),
	(45, 22, 30, '大林慈濟', NULL, NULL, 0, '2026-01-30 03:23:43'),
	(46, 23, 32, '花蓮慈濟', NULL, NULL, 0, '2026-01-30 03:23:43'),
	(47, 1, 33, '中醫職類', 34, 1, 0, '2026-01-30 08:02:30'),
	(48, 1, 34, '西醫職類', 35, 1, 0, '2026-01-30 08:02:30'),
	(49, 1, 35, '行政職類', 36, 1, 0, '2026-01-30 08:02:30'),
	(50, 3, 25, '行政人員', NULL, 1, 0, '2026-01-30 08:58:46'),
	(51, 1, 36, '教學部', 36, 35, 0, '2026-01-30 09:00:03'),
	(52, 2, 39, '教學研發組', NULL, 1, 0, '2026-01-30 09:12:47'),
	(53, 3, 41, '程式人員', NULL, 1, 0, '2026-02-02 00:55:50'),
	(55, 1, 52, '行政部', NULL, 35, 0, '2026-02-02 06:52:47'),
	(56, 3, 53, '主管', NULL, 1, 0, '2026-02-05 03:56:52');

-- 傾印  資料表 portal_db.course_tags 結構
CREATE TABLE IF NOT EXISTS `course_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL COMMENT 'Moodle course ID',
  `tag_id` int(11) NOT NULL COMMENT '標籤 ID',
  `institution` varchar(50) NOT NULL COMMENT '院區',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_course_tag` (`course_id`,`tag_id`),
  KEY `idx_course` (`course_id`),
  KEY `idx_tag` (`tag_id`),
  KEY `idx_institution` (`institution`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='課程-標籤關聯';

-- 正在傾印表格  portal_db.course_tags 的資料：~0 rows (近似值)

-- 傾印  資料表 portal_db.course_visibility 結構
CREATE TABLE IF NOT EXISTS `course_visibility` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_course_user` (`course_id`,`user_id`),
  KEY `idx_course` (`course_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 正在傾印表格  portal_db.course_visibility 的資料：~20 rows (近似值)
INSERT INTO `course_visibility` (`id`, `course_id`, `user_id`, `created_at`) VALUES
	(2, 30, 27, '2026-02-04 00:55:43'),
	(3, 30, 32, '2026-02-04 00:55:43'),
	(7, 30, 26, '2026-02-04 02:06:03'),
	(10, 31, 26, '2026-02-04 02:07:43'),
	(11, 31, 27, '2026-02-04 02:07:43'),
	(12, 31, 32, '2026-02-04 02:07:43'),
	(13, 32, 26, '2026-02-09 01:32:46'),
	(14, 32, 27, '2026-02-09 01:32:46'),
	(15, 32, 32, '2026-02-09 01:32:46'),
	(16, 36, 33, '2026-02-10 02:32:39'),
	(17, 36, 34, '2026-02-10 02:32:39'),
	(18, 37, 32, '2026-02-10 02:33:16'),
	(19, 38, 32, '2026-02-10 02:33:37'),
	(20, 39, 32, '2026-02-10 02:34:31'),
	(21, 40, 32, '2026-02-10 02:35:15'),
	(22, 42, 32, '2026-02-10 07:29:39'),
	(23, 43, 32, '2026-02-10 07:31:31'),
	(24, 44, 32, '2026-02-10 07:31:51'),
	(25, 45, 32, '2026-02-10 07:32:15'),
	(26, 46, 32, '2026-02-10 07:32:32');

-- 傾印  資料表 portal_db.dimension_types 結構
CREATE TABLE IF NOT EXISTS `dimension_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `institution_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(50) NOT NULL COMMENT '維度名稱：職類、層級、年份、屬性',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `is_protected` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_inst_dim` (`institution_id`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='群組維度類型';

-- 正在傾印表格  portal_db.dimension_types 的資料：~26 rows (近似值)
INSERT INTO `dimension_types` (`id`, `institution_id`, `parent_id`, `name`, `sort_order`, `created_at`, `is_protected`) VALUES
	(1, 1, NULL, '職類', 0, '2026-01-27 01:01:35', 1),
	(2, 1, NULL, '所屬', 0, '2026-01-27 01:23:16', 0),
	(3, 1, NULL, '屬性', 0, '2026-01-27 01:23:48', 0),
	(4, 1, NULL, '主群組', 0, '2026-01-27 06:28:23', 1),
	(5, 1, 1, '醫事檢驗', 0, '2026-01-27 07:34:54', 0),
	(6, 1, 1, '語言治療', 0, '2026-01-27 07:34:54', 0),
	(7, 1, 1, '職能治療', 0, '2026-01-27 07:34:54', 0),
	(8, 1, 1, '營養職類', 0, '2026-01-27 07:34:54', 0),
	(9, 1, 1, '物理治療', 0, '2026-01-27 07:34:54', 0),
	(10, 1, 1, '醫事放射', 0, '2026-01-27 07:34:54', 0),
	(11, 1, 1, '牙體技術', 0, '2026-01-27 07:34:54', 0),
	(12, 1, 1, '助產職類', 0, '2026-01-27 07:34:54', 0),
	(13, 1, 1, '諮商心理', 0, '2026-01-27 07:34:54', 0),
	(14, 1, 1, '護理職類', 0, '2026-01-27 07:34:54', 0),
	(15, 1, 1, '臨床心理', 0, '2026-01-27 07:34:54', 0),
	(16, 1, 1, '呼吸治療', 0, '2026-01-27 07:34:54', 0),
	(17, 1, 1, '聽力職類', 0, '2026-01-27 07:34:54', 0),
	(18, 1, 1, '藥事職類', 0, '2026-01-27 07:34:54', 0),
	(19, 3, NULL, '職類', 0, '2026-01-30 02:34:45', 0),
	(20, 3, NULL, '所屬', 0, '2026-01-30 02:34:50', 0),
	(21, 3, NULL, '屬性', 0, '2026-01-30 02:34:54', 0),
	(22, 3, NULL, '主群組', 0, '2026-01-30 03:23:43', 1),
	(23, 4, NULL, '主群組', 0, '2026-01-30 03:23:43', 1),
	(24, 1, 1, '行政職類', 0, '2026-02-06 02:47:39', 0),
	(25, 1, 1, '中醫職類', 0, '2026-02-06 02:47:58', 0),
	(26, 1, 1, '西醫職類', 0, '2026-02-06 02:48:13', 0);

-- 傾印  資料表 portal_db.institutions 結構
CREATE TABLE IF NOT EXISTS `institutions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `cohort_idnumber` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `management_category_id` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  portal_db.institutions 的資料：~4 rows (近似值)
INSERT INTO `institutions` (`id`, `name`, `cohort_idnumber`, `created_at`, `management_category_id`) VALUES
	(1, '台北', 'cohort_taipei', '2026-01-19 06:52:13', 6),
	(2, '嘉義慈濟', 'cohort_chiayi', '2026-01-19 06:52:13', 0),
	(3, '大林慈濟', 'cohort_dalin', '2026-01-19 06:52:13', 32),
	(4, '花蓮慈濟', 'cohort_hualien', '2026-01-19 06:52:13', 33);

-- 傾印  資料表 portal_db.portal_category_requirements 結構
CREATE TABLE IF NOT EXISTS `portal_category_requirements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `moodle_category_id` int(11) NOT NULL COMMENT '類別 ID',
  `user_id` int(11) NOT NULL COMMENT 'Portal 使用者 ID',
  `moodle_user_id` int(11) DEFAULT NULL COMMENT 'Moodle 使用者 ID',
  `required_pass_count` int(11) DEFAULT 1 COMMENT '需通過堂數',
  `deadline` date DEFAULT NULL COMMENT '完成期限',
  `filter_snapshot` text DEFAULT NULL COMMENT '篩選條件快照 (JSON)',
  `created_by` varchar(100) DEFAULT NULL COMMENT '建立者',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cat_user` (`moodle_category_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  portal_db.portal_category_requirements 的資料：~7 rows (近似值)
INSERT INTO `portal_category_requirements` (`id`, `moodle_category_id`, `user_id`, `moodle_user_id`, `required_pass_count`, `deadline`, `filter_snapshot`, `created_by`, `created_at`) VALUES
	(1, 37, 26, 26, 0, NULL, '[{"category":"36","location":"","attribute":""}]', 'test_admin_taipei', '2026-02-05 00:53:41'),
	(2, 37, 27, 27, 0, NULL, '[{"category":"36","location":"","attribute":""}]', 'test_admin_taipei', '2026-02-05 00:53:41'),
	(3, 37, 32, 32, 0, NULL, '[{"category":"36","location":"","attribute":""}]', 'test_admin_taipei', '2026-02-05 00:53:41'),
	(4, 38, 26, 26, 0, NULL, '[{"category":"36","location":"","attribute":""}]', 'test_admin_taipei', '2026-02-05 02:23:38'),
	(5, 38, 27, 27, 0, NULL, '[{"category":"36","location":"","attribute":""}]', 'test_admin_taipei', '2026-02-05 02:23:38'),
	(6, 38, 32, 32, 0, NULL, '[{"category":"36","location":"","attribute":""}]', 'test_admin_taipei', '2026-02-05 02:23:38'),
	(10, 39, 32, 32, 2, NULL, '[{"category":"36","location":"39","attribute":"41"}]', 'test_admin_taipei', '2026-02-11 08:49:01');

-- 傾印  資料表 portal_db.portal_category_settings 結構
CREATE TABLE IF NOT EXISTS `portal_category_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `moodle_category_id` int(11) NOT NULL COMMENT '對應 Moodle 類別 ID',
  `is_mandatory_category` tinyint(4) DEFAULT 0 COMMENT '是否必修類別（1=首頁強制顯示）',
  `required_pass_count` int(11) DEFAULT 0 COMMENT '需通過堂數（0=動態燈）',
  `period_months` int(11) DEFAULT 0 COMMENT '期限（月），0=無限制',
  `require_order` tinyint(4) DEFAULT 0 COMMENT '是否要求順序完成',
  `visibility` varchar(20) NOT NULL DEFAULT 'all',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `moodle_category_id` (`moodle_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  portal_db.portal_category_settings 的資料：~5 rows (近似值)
INSERT INTO `portal_category_settings` (`id`, `moodle_category_id`, `is_mandatory_category`, `required_pass_count`, `period_months`, `require_order`, `visibility`, `created_at`, `updated_at`) VALUES
	(1, 37, 0, 0, 0, 0, 'all', '2026-02-05 00:53:40', '2026-02-10 00:54:56'),
	(3, 38, 1, 0, 0, 0, 'mandatory_only', '2026-02-05 02:23:38', '2026-02-10 00:54:02'),
	(9, 39, 1, 2, 0, 0, 'mandatory_only', '2026-02-10 06:13:11', '2026-02-11 08:49:01'),
	(13, 40, 1, 0, 0, 0, 'all', '2026-02-12 01:40:16', '2026-02-12 01:40:16'),
	(14, 1, 0, 0, 0, 0, 'all', '2026-02-13 01:29:41', '2026-02-13 02:12:04');

-- 傾印  資料表 portal_db.portal_mandatory_courses 結構
CREATE TABLE IF NOT EXISTS `portal_mandatory_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `moodle_course_id` int(11) NOT NULL COMMENT '課程 ID',
  `moodle_category_id` int(11) NOT NULL COMMENT '所屬類別 ID',
  `is_mandatory` tinyint(4) DEFAULT 0 COMMENT '是否必修課',
  `display_order` int(11) DEFAULT 0 COMMENT '順序（若啟用順序要求）',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_course_cat` (`moodle_course_id`,`moodle_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  portal_db.portal_mandatory_courses 的資料：~4 rows (近似值)
INSERT INTO `portal_mandatory_courses` (`id`, `moodle_course_id`, `moodle_category_id`, `is_mandatory`, `display_order`, `created_at`) VALUES
	(21, 32, 38, 0, 0, '2026-02-10 01:04:52'),
	(22, 33, 39, 0, 0, '2026-02-10 06:13:23'),
	(25, 44, 39, 0, 0, '2026-02-11 08:49:21'),
	(27, 48, 2, 0, 0, '2026-02-13 02:03:10');

-- 傾印  資料表 portal_db.portal_tags 結構
CREATE TABLE IF NOT EXISTS `portal_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `color` varchar(7) DEFAULT '#6b7280',
  `institution_code` varchar(50) DEFAULT NULL COMMENT 'NULL = 全域模板',
  `is_template` tinyint(1) DEFAULT 0 COMMENT '是否為系統模板',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_institution` (`institution_code`),
  KEY `idx_active` (`is_active`),
  KEY `idx_template` (`is_template`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  portal_db.portal_tags 的資料：~7 rows (近似值)
INSERT INTO `portal_tags` (`id`, `name`, `description`, `color`, `institution_code`, `is_template`, `is_active`, `sort_order`, `created_by`, `created_at`, `updated_at`) VALUES
	(1, '急救認證', NULL, '#3b82f6', NULL, 1, 1, 0, NULL, '2026-02-06 09:12:35', '2026-02-06 09:12:35'),
	(2, '講師資格', NULL, '#3b82f6', NULL, 1, 1, 0, NULL, '2026-02-06 09:12:46', '2026-02-06 09:12:46'),
	(3, 'PGY1', NULL, '#3b82f6', NULL, 1, 1, 0, NULL, '2026-02-06 09:12:52', '2026-02-06 09:12:52'),
	(4, '急救組', NULL, '#fa1100', '台北', 0, 1, 0, 0, '2026-02-09 01:01:33', '2026-02-09 01:01:33'),
	(5, '2025', NULL, '#10b981', '台北', 0, 1, 0, NULL, '2026-02-09 06:44:13', '2026-02-09 06:44:13'),
	(6, '2026', NULL, '#10b981', '台北', 0, 1, 0, NULL, '2026-02-09 06:44:13', '2026-02-09 06:44:13'),
	(7, 'PGY', NULL, '#8b5cf6', '台北', 0, 1, 0, NULL, '2026-02-09 06:44:13', '2026-02-09 06:44:13');

-- 傾印  資料表 portal_db.users 結構
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL INVISIBLE AUTO_INCREMENT,
  `username` varchar(100) DEFAULT NULL COMMENT '帳號',
  `password` varchar(255) DEFAULT NULL,
  `fullname` varchar(100) DEFAULT NULL COMMENT '全名',
  `email` varchar(100) DEFAULT NULL,
  `remember_token` varchar(64) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'user',
  `institution` varchar(50) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 正在傾印表格  portal_db.users 的資料：~38 rows (近似值)
INSERT INTO `users` (`username`, `password`, `fullname`, `email`, `remember_token`, `role`, `institution`) VALUES
	('admin', '$2y$10$k3vWobsgvCXCqv.kPgtZTuwd9TiqlFHCmqz5ZumsBnntDs0jFS2wu', 'Admin', 'qoo311226qoo@gmail.com', NULL, 'admin', ''),
	('student1', '$2y$10$HxpOD.fR2nph/wA199KVmer8VkeFbH25u0OVAz2XbfC6Ju3eZsoK6', '賴小名', 'o0o1234@gmail.com', NULL, 'student', ''),
	('student2', '$2y$10$OJAdZW/D9BOL1MuvzeH87.J8f7z4Y25r9wOkACnDYjyfuO8sSBNbK', '蔡小名', 'o0o5678@gmail.com', NULL, 'user', ''),
	('student3', '$2y$10$NycY2y3Qs2thoKkXiMkxUesI.f5/G9ZQhtLHhKpN5c/.w8lllTF9G', '劉管理', 'dldl@gmail.com', NULL, 'user', ''),
	('student4', '$2y$10$ZKJIbaC.16jsa2GUR6.P1OqculW5d6cYb9tJyDaHbEFjFJBGbrPUC', '鄭小名', 'test1@gmail.com', NULL, 'user', ''),
	('student5', '$2y$10$KUsd9jfwpE99PzU26H3lYOThpUQJTm4Uqq8sze3HCcN5jZ8NuOROK', '吳小名', 'test1210@gmail.com', NULL, 'user', ''),
	('teacher1', '$2y$10$OnGhJEcLGkxzZ.izxJSbJOrxAedVISYoHs9S1kBpCC5oWTyA2n7kS', '陳老師', 'tea1234@gmail.com', NULL, 'coursecreator', ''),
	('teacher2', '$2y$10$9fcaFbENtWeuT0Iy1rUlzOSnVA0ABaI2BWGIpa4wo6hOUmtggh02W', '王教師', 'tea2222@gmail.com', NULL, 'editingteacher', ''),
	('studen6', '$2y$10$cjIrV18O0neGrEMDHALK1.aI8bDrgtjoctDatLGuxCNClezmbzwsy', '王大名', 'dalin@gmail.com', NULL, 'student', ''),
	('student6', '$2y$10$dSmIl1rAFP9tTciTcL/jLO7uaUpL3w2PUKsoBThKNnXts926IVbdy', '王中明', 'sttttttt@gmail.com', NULL, 'student', ''),
	('teacher1', '$2y$10$SHdIxNfBYB5Pek0enP0w2urP0rF42hfv0.2iQuQ3/NKXLCHtPtbSK', '王老師', 'wang@gmail.com', 'beb8fe8b14494c29b2058afc6ee59dc4a743fee8085d875471e65f2b9c779a2b', 'coursecreator', ''),
	('h125518958', '$2y$10$DRU3jkfbmSNyLdw2xUSBw.M3anzoqhyp.PMVkzNQf9AwFTss6f75y', '陳楷傑', 'h125518958@example.com', NULL, 'student', ''),
	('test_admin_taipei', '$2y$10$NqNZH04sTD7q8TSoZ5IWT.uxniNQk4yBIrb8LTVMuzHFC2ayUFyNi', '台北測試管理員', 'testadmin@taipei.com', NULL, 'hospital_admin', '台北'),
	('student7', '$2y$10$7cNPj/HIxUHTB7nJ8FnCTuh2XY/gfhzOv19cIcleZ0M9p9pvnQf4C', '王大明', 'tt@gmail.com', NULL, 'coursecreator', '台北'),
	('student_tp1', '$2y$10$oyKXsvqrnfR8q8oSTIq8ROzxSmA0u5L8ZKfx2avMyFnjrh4XXXVkG', '北學生33', 'taipei@gmail.com', NULL, 'student', '台北'),
	('teacher3', '$2y$10$v8nY3LKxMZDn5psIigMJk.VoWxf4LJHmh/wQxqmrGicqZp/xaUb2K', '北老師', 'sttttttt@gmail.com', NULL, 'coursecreator', '台北'),
	('student100', '$2y$10$Yy/crDl11fHd/QoIJcXGf.R5FzLRutglftCI2XP.m1RdjAb5SkZhW', '李小名', 'o0o0120@gmail.com', NULL, 'student', '台北'),
	('o0o0120', '$2y$10$J7CBdfiLgqOg8wBCtw/TpeSWLWA/VJuKONfsrMcT1IsXYx.RGNZ5.', '李曉明', 'o0o0120@gmail.com', NULL, 'student', '大林'),
	('taipeics1', '$2y$10$O/503HvyJsoOcacFZgERaeEax241OFR8y9oDi8yoQtmxH7orbAKSi', '北小兒', 'o0o01021@gmail.com', NULL, 'student', '台北'),
	('taipeics2', '$2y$10$SzAQYdT7SulCGuyEhK49p.rB94tslxVuLHD/ysBcNi8MA33WwLqwe', '北二兒', 'o0o0122@gmail.com', NULL, 'student', '台北'),
	('taipeit2', '$2y$10$Yt84b2uSs0bFzFrW14w/y.x/Mq8qlqIGMYHeNh0iVYtUTsI9emyPK', '北二師', 'o0otaipeit2@gmail.com', NULL, 'coursecreator', '台北'),
	('taipei11', '$2y$10$SVqS8QzTX45OVC2b/F1m/eZIBSr..uTIx3RiBk9HcFmh6gZmRsg2O', '北急名', 'o0otaipeii11@gmail.com', NULL, 'student', '台北'),
	('taipei51', '$2y$10$DxtDidMxBaHU6XQY.p2KWeOkIwQR8XV2mNFCYk1de0.G2ac9TwRwa', '北急師', 'o0otpt@gmail.com', NULL, 'coursecreator', '台北'),
	('tps10', '$2y$10$DLtRj62x3zDuz1PeQkWgZedD4sU/TTYK43lPvi7Q99TXbNH3nDrxO', '北十明', 'tps10@gmail.com', NULL, 'student', '台北'),
	('tps11', '$2y$10$wgZE1CW/hJFzwXrsUX7jKuvNAtW0krgm/F7ymxQffCLjezNNdRFMK', '北十一明', 'tps11@gmail.com', NULL, 'student', '台北'),
	('tps12', '$2y$10$SUAnSmnxsTzsZ5lA.6LZb.kKa8L2zkS5iEJ89rGxo/6QIRiYIvyT6', '北十二明', 'tps12@gmail.com', NULL, 'student', '台北'),
	('tps13', '$2y$10$nthLp0UeRlfuzo0Q2yrzz..r3x/PCDN.misFkV5R3PvFkLOrXUU6e', '北十三明', 'tps13@gmail.com', NULL, 'coursecreator', '台北'),
	('dalinadmin', '$2y$10$UQHifTaDEb81S9nDoaVZYO8XIeUF6P4CdDj2Nzui.hWtVly506h4G', '大林慈濟管理員', 'dalinadmin@gmail.com', NULL, 'hospital_admin', '大林'),
	('huaadmin', '$2y$10$uxlzvIr9YN4.z8bek/zLFulZyymPnnIY/zsvtkOM6e5eQpc0QTSBC', '花蓮測試管理員', 'huaadmin@gmail.com', NULL, 'hospital_admin', '花蓮慈濟'),
	('tpc1', '$2y$10$tWOdKUDl6mLWG3TOoZ4Cuud1uqiU6zCDYjwfmH4n/OkqUKK3sy7v.', '北程明', 'o0otpc1@gmail.com', NULL, 'student', '台北'),
	('tpc2', '$2y$10$J11wOd70QQIZkkCiBGeZouv89zXI7tfTwSyuW6GXEWYAdqtrCUxJy', '北程明二', 'tpc2@gmail.com', NULL, 'student', '台北'),
	('tpc3', '$2y$10$4JvxynjSwn9hdKKvdev/u.KY9hvf35lPqyJzFJI.4QqaMGuL0YujO', '北程明3', 'tpc3@gmail.com', NULL, 'student', '台北'),
	('tpc4', '$2y$10$jJbzHvJjjwPdviSGGS6hA.gNal85NjhJN/w93ho6DLZ39YmizYmR.', '北行明', 'tps1@gmail.com', NULL, 'student', '台北'),
	('tpz2', '$2y$10$4alVYmh2pxwGYI32Nt.YTehAEg9Bd.T19ZNdway3cJcStsSwJE0vS', '北行明2', 'tpz2@gmail.com', NULL, 'student', '台北'),
	('test_lc_1770886832', '$2y$10$PyswfY3Z0tG6ryLSTvj7N.9SIpIl12zhNNaaxNF5eUNuYY1IEPL96', 'Lifecycle Test', 'test_lc_1770886832@test.example.com', NULL, 'student', '大林慈濟醫院'),
	('test_lc_1770886854', '$2y$10$1nHaPaRlHT6tho9PyLYB1uwEmm0fK3DRb/PrJvOJ/Tol/kFrAaEmG', 'Lifecycle Test', 'test_lc_1770886854@test.example.com', NULL, 'student', '大林慈濟醫院'),
	('test_user_021532', '$2y$10$sz7YBvotAqjKgayNOATz2uwT0NqbpofAzn8tKRc.Mwu5fiQCOgSze', 'Test User 021532', 'test_021532@example.com', NULL, 'student', '大林慈濟醫院'),
	('test_user_manual123', '$2y$10$kBDRzAlDdI5DymCMxiMbQ.iLdG4OnKQKEdq10wb2.C2WoHMUK64Zm', 'Test User manual123', 'test_manual123@example.com', NULL, 'student', '大林慈濟醫院');

-- 傾印  資料表 portal_db.user_tags 結構
CREATE TABLE IF NOT EXISTS `user_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'Moodle user ID',
  `tag_id` int(11) NOT NULL COMMENT '標籤 ID',
  `institution` varchar(50) NOT NULL COMMENT '院區',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_tag` (`user_id`,`tag_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_tag` (`tag_id`),
  KEY `idx_institution` (`institution`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='使用者-標籤關聯';

-- 正在傾印表格  portal_db.user_tags 的資料：~0 rows (近似值)

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

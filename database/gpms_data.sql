-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               9.4.0 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.11.0.7065
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table glee_live.actions
CREATE TABLE IF NOT EXISTS `actions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_action_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=14112 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.actions: ~16 rows (approximately)
INSERT IGNORE INTO `actions` (`id`, `name`) VALUES
	(1, 'view'),
	(2, 'create'),
	(3, 'update'),
	(4, 'delete'),
	(5, 'approve'),
	(6, 'reject'),
	(7, 'check_in'),
	(8, 'check_out'),
	(9, 'export'),
	(10, 'access'),
	(16, 'checkin'),
	(17, 'checkout'),
	(18, 'print'),
	(22, 'blacklist'),
	(26, 'disable'),
	(28, 'assign');

-- Dumping structure for table glee_live.audit_logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `message` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_tenant` (`tenant_id`),
  KEY `idx_audit_tenant_time` (`tenant_id`,`created_at`),
  KEY `idx_audit_entity` (`tenant_id`,`entity_type`,`entity_id`),
  KEY `fk_audit_user` (`user_id`,`tenant_id`),
  CONSTRAINT `fk_audit_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.audit_logs: ~105 rows (approximately)
INSERT IGNORE INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `metadata`, `ip_address`, `created_at`, `message`) VALUES
	(1, 1, 1, 'user.login', 'user', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/login", "method": "POST"}}', '::1', '2026-04-10 20:34:55', 'Albert Masinde logged in'),
	(2, 1, 1, 'dashboard.viewed', 'dashboard', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/dashboard", "method": "GET"}}', '::1', '2026-04-10 20:36:13', 'Albert Masinde viewed the dashboard'),
	(3, 1, 1, 'dashboard.viewed', 'dashboard', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/dashboard", "method": "GET"}}', '::1', '2026-04-10 20:36:13', 'Albert Masinde viewed the dashboard'),
	(4, 1, 1, 'dashboard.viewed', 'dashboard', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/dashboard", "method": "GET"}}', '::1', '2026-04-10 20:50:08', 'Albert Masinde viewed the dashboard'),
	(5, 1, 1, 'gatepass.list.viewed', 'gatepass_list', NULL, '{"email": "alumasinde@gmail.com", "user_id": 1, "_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-10 20:50:10', 'Albert Masinde gatepass.list.viewed'),
	(6, 1, 1, 'gatepass.list_viewed', 'gatepass_list', NULL, '{"email": "alumasinde@gmail.com", "user_id": 1, "_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-10 20:50:58', 'Albert Masinde viewed the gatepass list'),
	(7, 1, 1, 'gatepass.viewed', 'gatepass', 5, '{"email": "alumasinde@gmail.com", "_request": {"url": "/gatepasses/5", "method": "GET"}}', '::1', '2026-04-10 20:52:09', 'Albert Masinde viewed gatepass '),
	(8, 1, 1, 'gatepass.viewed', 'gatepass', 5, '{"email": "alumasinde@gmail.com", "_request": {"url": "/gatepasses/5", "method": "GET"}, "gatepass_number": "GPN-2026-0003"}', '::1', '2026-04-10 20:55:37', 'Albert Masinde viewed gatepass '),
	(9, 1, 1, 'gatepass.viewed', 'gatepass', 5, '{"email": "alumasinde@gmail.com", "_request": {"url": "/gatepasses/5", "method": "GET"}, "gatepass_number": "GPN-2026-0003"}', '::1', '2026-04-10 20:59:08', 'Albert Masinde viewed gatepass '),
	(10, 1, 1, 'gatepass.list_viewed', 'gatepass_list', NULL, '{"email": "alumasinde@gmail.com", "user_id": 1, "_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-10 20:59:10', 'Albert Masinde viewed the gatepass list'),
	(11, 1, 1, 'gatepass.viewed', 'gatepass', 4, '{"email": "alumasinde@gmail.com", "_request": {"url": "/gatepasses/4", "method": "GET"}, "gatepass_number": "0003"}', '::1', '2026-04-10 20:59:11', 'Albert Masinde viewed gatepass '),
	(12, 1, 1, 'gatepass.viewed', 'gatepass', 4, '{"email": "alumasinde@gmail.com", "_request": {"url": "/gatepasses/4", "method": "GET"}, "gatepass_number": "0003"}', '::1', '2026-04-10 21:14:06', 'Albert Masinde viewed gatepass '),
	(13, 1, 1, 'user.login', 'user', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/login", "method": "POST"}}', '::1', '2026-04-11 15:25:16', 'Albert Masinde logged in'),
	(14, 1, 1, 'dashboard.viewed', 'dashboard', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/dashboard", "method": "GET"}}', '::1', '2026-04-11 15:25:17', 'Albert Masinde viewed the dashboard'),
	(15, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 15:25:18', 'Albert Masinde viewed the gatepass list'),
	(16, 1, 1, 'gatepass.viewed', 'gatepass', 5, '{"_request": {"url": "/gatepasses/5", "method": "GET"}, "target_label": "GPN-2026-0003"}', '::1', '2026-04-11 15:25:23', 'Albert Masinde viewed gatepass GPN-2026-0003'),
	(17, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 15:25:26', 'Albert Masinde viewed the gatepass list'),
	(18, 1, 1, 'gatepass.created', 'gatepass', 6, '{"_request": {"url": "/gatepasses", "method": "POST"}, "needs_approval": true, "gatepass_number": "GPN-2026-0004"}', '::1', '2026-04-11 15:26:22', 'Albert Masinde created gatepass '),
	(19, 1, 1, 'gatepass.created', 'gatepass', 6, '{"_request": {"url": "/gatepasses", "method": "POST"}, "target_label": "Gatepass #6"}', '::1', '2026-04-11 15:26:22', 'Albert Masinde created gatepass Gatepass #6'),
	(20, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 15:26:22', 'Albert Masinde viewed the gatepass list'),
	(21, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 15:35:53', 'Albert Masinde viewed the gatepass list'),
	(22, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 15:41:26', 'Albert Masinde viewed the gatepass list'),
	(23, 1, 1, 'gatepass.viewed', 'gatepass', 6, '{"_request": {"url": "/gatepasses/6", "method": "GET"}, "target_label": "GPN-2026-0004"}', '::1', '2026-04-11 15:41:28', 'Albert Masinde viewed gatepass GPN-2026-0004'),
	(24, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 15:41:30', 'Albert Masinde viewed the gatepass list'),
	(25, 1, 1, 'gatepass.viewed', 'gatepass', 6, '{"_request": {"url": "/gatepasses/6", "method": "GET"}, "target_label": "GPN-2026-0004"}', '::1', '2026-04-11 15:43:07', 'Albert Masinde viewed gatepass GPN-2026-0004'),
	(26, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 15:43:08', 'Albert Masinde viewed the gatepass list'),
	(27, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 15:43:11', 'Albert Masinde viewed the gatepass list'),
	(28, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN", "method": "GET"}}', '::1', '2026-04-11 15:45:12', 'Albert Masinde viewed the gatepass list'),
	(29, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN", "method": "GET"}}', '::1', '2026-04-11 15:45:15', 'Albert Masinde viewed the gatepass list'),
	(30, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0004", "method": "GET"}}', '::1', '2026-04-11 15:45:23', 'Albert Masinde viewed the gatepass list'),
	(31, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0004", "method": "GET"}}', '::1', '2026-04-11 15:45:25', 'Albert Masinde viewed the gatepass list'),
	(32, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 15:45:26', 'Albert Masinde viewed the gatepass list'),
	(33, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 15:45:36', 'Albert Masinde viewed the gatepass list'),
	(34, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=Pen", "method": "GET"}}', '::1', '2026-04-11 15:45:40', 'Albert Masinde viewed the gatepass list'),
	(35, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 15:45:44', 'Albert Masinde viewed the gatepass list'),
	(36, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 16:05:40', 'Albert Masinde viewed the gatepass list'),
	(37, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=pending", "method": "GET"}}', '::1', '2026-04-11 16:05:48', 'Albert Masinde viewed the gatepass list'),
	(38, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=pending", "method": "GET"}}', '::1', '2026-04-11 16:05:49', 'Albert Masinde viewed the gatepass list'),
	(39, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?", "method": "GET"}}', '::1', '2026-04-11 16:05:51', 'Albert Masinde viewed the gatepass list'),
	(40, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?", "method": "GET"}}', '::1', '2026-04-11 16:07:43', 'Albert Masinde viewed the gatepass list'),
	(41, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=%090003", "method": "GET"}}', '::1', '2026-04-11 16:07:51', 'Albert Masinde viewed the gatepass list'),
	(42, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?", "method": "GET"}}', '::1', '2026-04-11 16:07:53', 'Albert Masinde viewed the gatepass list'),
	(43, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0004", "method": "GET"}}', '::1', '2026-04-11 16:08:05', 'Albert Masinde viewed the gatepass list'),
	(44, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?", "method": "GET"}}', '::1', '2026-04-11 16:08:07', 'Albert Masinde viewed the gatepass list'),
	(45, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 16:08:34', 'Albert Masinde viewed the gatepass list'),
	(46, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 16:19:17', 'Albert Masinde viewed the gatepass list'),
	(47, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=Pendin", "method": "GET"}}', '::1', '2026-04-11 16:19:22', 'Albert Masinde viewed the gatepass list'),
	(48, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=Pending", "method": "GET"}}', '::1', '2026-04-11 16:19:27', 'Albert Masinde viewed the gatepass list'),
	(49, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=Pending", "method": "GET"}}', '::1', '2026-04-11 16:23:18', 'Albert Masinde viewed the gatepass list'),
	(50, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=Pending", "method": "GET"}}', '::1', '2026-04-11 16:23:26', 'Albert Masinde viewed the gatepass list'),
	(51, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0004", "method": "GET"}}', '::1', '2026-04-11 16:23:32', 'Albert Masinde viewed the gatepass list'),
	(52, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0004", "method": "GET"}}', '::1', '2026-04-11 16:23:34', 'Albert Masinde viewed the gatepass list'),
	(53, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?", "method": "GET"}}', '::1', '2026-04-11 16:23:35', 'Albert Masinde viewed the gatepass list'),
	(54, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?", "method": "GET"}}', '::1', '2026-04-11 16:23:50', 'Albert Masinde viewed the gatepass list'),
	(55, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0004", "method": "GET"}}', '::1', '2026-04-11 16:23:53', 'Albert Masinde viewed the gatepass list'),
	(56, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0004", "method": "GET"}}', '::1', '2026-04-11 16:29:43', 'Albert Masinde viewed the gatepass list'),
	(57, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0004", "method": "GET"}}', '::1', '2026-04-11 16:29:48', 'Albert Masinde viewed the gatepass list'),
	(58, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?", "method": "GET"}}', '::1', '2026-04-11 16:29:49', 'Albert Masinde viewed the gatepass list'),
	(59, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?", "method": "GET"}}', '::1', '2026-04-11 16:43:09', 'Albert Masinde viewed the gatepass list'),
	(60, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=", "method": "GET"}}', '::1', '2026-04-11 16:43:14', 'Albert Masinde viewed the gatepass list'),
	(61, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=kljbl", "method": "GET"}}', '::1', '2026-04-11 16:43:16', 'Albert Masinde viewed the gatepass list'),
	(62, 1, 1, 'visitor.created', 'visitor', 2, '{"name": "Christina Maumo", "_request": {"url": "/visitors", "method": "POST"}, "id_number": "2864946209", "tenant_id": 1}', '::1', '2026-04-11 16:43:52', 'Albert Masinde visitor.created'),
	(63, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 16:54:29', 'Albert Masinde viewed the gatepass list'),
	(64, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0004", "method": "GET"}}', '::1', '2026-04-11 16:54:34', 'Albert Masinde viewed the gatepass list'),
	(65, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?", "method": "GET"}}', '::1', '2026-04-11 16:54:37', 'Albert Masinde viewed the gatepass list'),
	(66, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 16:54:45', 'Albert Masinde viewed the gatepass list'),
	(67, 1, 1, 'user.login', 'user', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/login", "method": "POST"}}', '::1', '2026-04-11 16:58:42', 'Albert Masinde logged in'),
	(68, 1, 1, 'dashboard.viewed', 'dashboard', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/dashboard", "method": "GET"}}', '::1', '2026-04-11 16:58:42', 'Albert Masinde viewed the dashboard'),
	(69, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 16:59:03', 'Albert Masinde viewed the gatepass list'),
	(70, 1, 1, 'gatepass.viewed', 'gatepass', 6, '{"_request": {"url": "/gatepasses/6", "method": "GET"}, "target_label": "GPN-2026-0004"}', '::1', '2026-04-11 16:59:06', 'Albert Masinde viewed gatepass GPN-2026-0004'),
	(71, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 16:59:07', 'Albert Masinde viewed the gatepass list'),
	(72, 1, 1, 'dashboard.viewed', 'dashboard', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/dashboard", "method": "GET"}}', '::1', '2026-04-11 17:18:46', 'Albert Masinde viewed the dashboard'),
	(73, 1, 1, 'dashboard.viewed', 'dashboard', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/dashboard", "method": "GET"}}', '::1', '2026-04-11 17:52:51', 'Albert Masinde viewed the dashboard'),
	(74, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 17:55:39', 'Albert Masinde viewed the gatepass list'),
	(75, 1, 1, 'dashboard.viewed', 'dashboard', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/dashboard", "method": "GET"}}', '::1', '2026-04-11 18:02:07', 'Albert Masinde viewed the dashboard'),
	(76, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 18:02:08', 'Albert Masinde viewed the gatepass list'),
	(77, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 18:02:11', 'Albert Masinde viewed the gatepass list'),
	(78, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 18:02:11', 'Albert Masinde viewed the gatepass list'),
	(79, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=pending", "method": "GET"}}', '::1', '2026-04-11 18:02:20', 'Albert Masinde viewed the gatepass list'),
	(80, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0003", "method": "GET"}}', '::1', '2026-04-11 18:02:27', 'Albert Masinde viewed the gatepass list'),
	(81, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0003", "method": "GET"}}', '::1', '2026-04-11 18:05:35', 'Albert Masinde viewed the gatepass list'),
	(82, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0003", "method": "GET"}}', '::1', '2026-04-11 18:05:38', 'Albert Masinde viewed the gatepass list'),
	(83, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 18:07:15', 'Albert Masinde viewed the gatepass list'),
	(84, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0004", "method": "GET"}}', '::1', '2026-04-11 18:07:20', 'Albert Masinde viewed the gatepass list'),
	(85, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0004", "method": "GET"}}', '::1', '2026-04-11 19:00:00', 'Albert Masinde viewed the gatepass list'),
	(86, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0004", "method": "GET"}}', '::1', '2026-04-11 19:00:03', 'Albert Masinde viewed the gatepass list'),
	(87, 1, 1, 'user.logout', 'user', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/logout", "method": "POST"}}', '::1', '2026-04-11 19:00:09', 'Albert Masinde logged out'),
	(88, 1, 1, 'user.login', 'user', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/login", "method": "POST"}}', '::1', '2026-04-11 19:00:15', 'Albert Masinde logged in'),
	(89, 1, 1, 'dashboard.viewed', 'dashboard', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/dashboard", "method": "GET"}}', '::1', '2026-04-11 19:00:15', 'Albert Masinde viewed the dashboard'),
	(90, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 19:00:17', 'Albert Masinde viewed the gatepass list'),
	(91, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 19:00:21', 'Albert Masinde viewed the gatepass list'),
	(92, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 19:00:27', 'Albert Masinde viewed the gatepass list'),
	(93, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=cn", "method": "GET"}}', '::1', '2026-04-11 19:00:28', 'Albert Masinde viewed the gatepass list'),
	(94, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=GPN-2026-0004", "method": "GET"}}', '::1', '2026-04-11 19:00:35', 'Albert Masinde viewed the gatepass list'),
	(95, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 19:08:00', 'Albert Masinde viewed the gatepass list'),
	(96, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=j", "method": "GET"}}', '::1', '2026-04-11 19:08:03', 'Albert Masinde viewed the gatepass list'),
	(97, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 19:08:06', 'Albert Masinde viewed the gatepass list'),
	(98, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 19:20:37', 'Albert Masinde viewed the gatepass list'),
	(99, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 19:20:38', 'Albert Masinde viewed the gatepass list'),
	(100, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 19:20:38', 'Albert Masinde viewed the gatepass list'),
	(101, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 19:20:39', 'Albert Masinde viewed the gatepass list'),
	(102, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 19:20:39', 'Albert Masinde viewed the gatepass list'),
	(103, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 19:20:39', 'Albert Masinde viewed the gatepass list'),
	(104, 1, 1, 'gatepass.viewed', 'gatepass', 6, '{"_request": {"url": "/gatepasses/6", "method": "GET"}, "target_label": "GPN-2026-0004"}', '::1', '2026-04-11 19:20:42', 'Albert Masinde viewed gatepass GPN-2026-0004'),
	(105, 1, 1, 'dashboard.viewed', 'dashboard', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/dashboard", "method": "GET"}}', '::1', '2026-04-11 19:20:44', 'Albert Masinde viewed the dashboard'),
	(106, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses", "method": "GET"}}', '::1', '2026-04-11 19:20:45', 'Albert Masinde viewed the gatepass list'),
	(107, 1, 1, 'gatepass.list_viewed', 'gatepass', NULL, '{"_request": {"url": "/gatepasses?q=d", "method": "GET"}}', '::1', '2026-04-11 19:20:48', 'Albert Masinde viewed the gatepass list'),
	(108, 1, 1, 'user.logout', 'user', 1, '{"email": "alumasinde@gmail.com", "_request": {"url": "/logout", "method": "POST"}}', '::1', '2026-04-11 19:49:22', 'Albert Masinde logged out');

-- Dumping structure for table glee_live.departments
CREATE TABLE IF NOT EXISTS `departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dept_tenant_code` (`tenant_id`,`code`),
  UNIQUE KEY `uk_dept_tenant_id` (`id`,`tenant_id`),
  KEY `idx_depts_tenant` (`tenant_id`),
  KEY `idx_depts_tenant_active` (`tenant_id`,`is_active`),
  CONSTRAINT `fk_depts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.departments: ~6 rows (approximately)
INSERT IGNORE INTO `departments` (`id`, `tenant_id`, `name`, `code`, `is_active`, `created_at`) VALUES
	(1, 1, 'Reception', 'REC', 1, '2026-04-08 19:29:16'),
	(2, 1, 'Information Technology', 'IT', 1, '2026-04-08 19:29:16'),
	(3, 1, 'Finance', 'FIN', 1, '2026-04-08 19:29:16'),
	(4, 1, 'Human Resources', 'HR', 1, '2026-04-08 19:29:16'),
	(5, 1, 'Security', 'SEC', 1, '2026-04-08 19:29:16'),
	(6, 1, 'Operations', 'OPS', 1, '2026-04-08 19:29:16');

-- Dumping structure for table glee_live.gatepasses
CREATE TABLE IF NOT EXISTS `gatepasses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `visit_id` bigint unsigned DEFAULT NULL,
  `gatepass_type_id` bigint unsigned DEFAULT NULL,
  `gatepass_number` varchar(100) NOT NULL,
  `status_id` bigint unsigned DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `checked_in_by` bigint unsigned DEFAULT NULL,
  `checked_out_by` bigint unsigned DEFAULT NULL,
  `actual_in` datetime DEFAULT NULL,
  `actual_out` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `purpose` varchar(250) DEFAULT NULL,
  `is_returnable` tinyint(1) NOT NULL DEFAULT '0',
  `expected_return_date` datetime DEFAULT NULL,
  `actual_return_date` datetime DEFAULT NULL,
  `is_fully_returned` tinyint(1) NOT NULL DEFAULT '0',
  `needs_approval` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gatepass_tenant_number` (`tenant_id`,`gatepass_number`),
  UNIQUE KEY `uk_gatepass_tenant_id` (`id`,`tenant_id`),
  KEY `idx_gatepass_status` (`tenant_id`,`status_id`),
  KEY `idx_gatepass_type` (`tenant_id`,`gatepass_type_id`),
  KEY `idx_gatepasses_department` (`tenant_id`,`department_id`),
  KEY `idx_gatepass_created_by` (`tenant_id`,`created_by`),
  KEY `idx_gatepass_list` (`tenant_id`,`status_id`,`created_at` DESC),
  KEY `idx_gatepass_returnable` (`tenant_id`,`is_returnable`,`is_fully_returned`),
  KEY `fk_gatepass_visit` (`visit_id`,`tenant_id`),
  KEY `fk_gatepass_type` (`gatepass_type_id`,`tenant_id`),
  KEY `fk_gatepass_status` (`status_id`,`tenant_id`),
  KEY `fk_gatepass_department` (`department_id`,`tenant_id`),
  KEY `fk_gatepass_created_by` (`created_by`,`tenant_id`),
  KEY `fk_gatepass_checked_in_by` (`checked_in_by`),
  KEY `fk_gatepass_checked_out_by` (`checked_out_by`),
  CONSTRAINT `fk_gatepass_checked_in_by` FOREIGN KEY (`checked_in_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gatepass_checked_out_by` FOREIGN KEY (`checked_out_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gatepass_created_by` FOREIGN KEY (`created_by`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gatepass_department` FOREIGN KEY (`department_id`, `tenant_id`) REFERENCES `departments` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gatepass_status` FOREIGN KEY (`status_id`, `tenant_id`) REFERENCES `gatepass_statuses` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gatepass_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gatepass_type` FOREIGN KEY (`gatepass_type_id`, `tenant_id`) REFERENCES `gatepass_types` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gatepass_visit` FOREIGN KEY (`visit_id`, `tenant_id`) REFERENCES `visits` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.gatepasses: ~5 rows (approximately)
INSERT IGNORE INTO `gatepasses` (`id`, `tenant_id`, `visit_id`, `gatepass_type_id`, `gatepass_number`, `status_id`, `department_id`, `checked_in_by`, `checked_out_by`, `actual_in`, `actual_out`, `created_by`, `created_at`, `purpose`, `is_returnable`, `expected_return_date`, `actual_return_date`, `is_fully_returned`, `needs_approval`) VALUES
	(1, 1, NULL, 3, '0000', 5, 2, 1, 1, '2026-04-09 16:45:29', '2026-04-09 16:45:32', 1, '2026-04-08 20:38:22', 'Test Gate Pass', 0, '2026-04-10 00:00:00', NULL, 0, 0),
	(2, 1, NULL, 4, '0001', 5, 2, 1, 1, '2026-04-09 16:45:26', '2026-04-09 16:45:31', 1, '2026-04-08 22:04:35', 'Test', 1, '2026-04-10 00:00:00', NULL, 0, 0),
	(3, 1, NULL, 4, '0002', 5, 2, 1, 1, '2026-04-09 20:30:32', '2026-04-09 20:31:19', 1, '2026-04-08 22:13:52', 'Test Update', 1, '2026-04-10 00:00:00', NULL, 0, 1),
	(4, 1, NULL, 4, '0003', 5, 2, 1, 1, '2026-04-09 20:30:31', '2026-04-09 20:31:18', 1, '2026-04-08 22:23:48', 'Test', 1, '2026-04-10 00:00:00', NULL, 0, 1),
	(5, 1, NULL, 4, 'GPN-2026-0003', 5, 2, 1, 1, '2026-04-09 20:30:29', '2026-04-09 20:31:16', 1, '2026-04-08 22:28:54', 'Test Latest Changes', 1, '2026-04-10 00:00:00', NULL, 0, 1),
	(6, 1, 1, 4, 'GPN-2026-0004', 1, 2, NULL, NULL, NULL, NULL, 1, '2026-04-11 15:26:22', 'Test Changes', 0, '2026-04-14 00:00:00', NULL, 0, 1);

-- Dumping structure for table glee_live.gatepass_approvals
CREATE TABLE IF NOT EXISTS `gatepass_approvals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `workflow_instance_id` bigint unsigned NOT NULL,
  `workflow_step_id` bigint unsigned NOT NULL,
  `approver_user_id` bigint unsigned NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `comments` text,
  `acted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ga_tenant` (`tenant_id`),
  KEY `idx_ga_status` (`tenant_id`,`status`),
  KEY `idx_ga_user_pending` (`approver_user_id`,`status`),
  KEY `idx_ga_lookup` (`tenant_id`,`approver_user_id`,`status`),
  KEY `idx_ga_instance_status` (`tenant_id`,`workflow_instance_id`,`status`),
  KEY `fk_ga_instance` (`workflow_instance_id`,`tenant_id`),
  KEY `fk_ga_step` (`workflow_step_id`,`tenant_id`),
  KEY `fk_ga_user` (`approver_user_id`,`tenant_id`),
  CONSTRAINT `fk_ga_instance` FOREIGN KEY (`workflow_instance_id`, `tenant_id`) REFERENCES `gatepass_workflow_instances` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ga_step` FOREIGN KEY (`workflow_step_id`, `tenant_id`) REFERENCES `workflow_steps` (`id`, `tenant_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ga_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ga_user` FOREIGN KEY (`approver_user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.gatepass_approvals: ~9 rows (approximately)
INSERT IGNORE INTO `gatepass_approvals` (`id`, `tenant_id`, `workflow_instance_id`, `workflow_step_id`, `approver_user_id`, `status`, `comments`, `acted_at`, `created_at`) VALUES
	(1, 1, 1, 1, 4, 'approved', NULL, '2026-04-09 20:23:58', '2026-04-08 22:13:52'),
	(2, 1, 2, 1, 4, 'approved', NULL, '2026-04-09 20:23:53', '2026-04-08 22:23:48'),
	(3, 1, 3, 1, 4, 'approved', NULL, '2026-04-09 20:23:49', '2026-04-08 22:28:54'),
	(4, 1, 3, 2, 2, 'approved', NULL, '2026-04-09 20:27:11', '2026-04-09 20:23:49'),
	(5, 1, 2, 2, 2, 'approved', NULL, '2026-04-09 20:27:15', '2026-04-09 20:23:53'),
	(6, 1, 1, 2, 2, 'approved', NULL, '2026-04-09 20:27:17', '2026-04-09 20:23:58'),
	(7, 1, 3, 3, 1, 'approved', NULL, '2026-04-09 20:29:21', '2026-04-09 20:27:11'),
	(8, 1, 2, 3, 1, 'approved', NULL, '2026-04-09 20:29:39', '2026-04-09 20:27:15'),
	(9, 1, 1, 3, 1, 'approved', NULL, '2026-04-09 20:29:41', '2026-04-09 20:27:17'),
	(10, 1, 4, 1, 4, 'pending', NULL, NULL, '2026-04-11 15:26:22');

-- Dumping structure for table glee_live.gatepass_items
CREATE TABLE IF NOT EXISTS `gatepass_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `gatepass_id` bigint unsigned NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text,
  `quantity` int NOT NULL DEFAULT '1',
  `serial_number` varchar(255) DEFAULT NULL,
  `is_returnable` tinyint(1) NOT NULL DEFAULT '0',
  `returned_quantity` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gi_gatepass` (`gatepass_id`),
  KEY `fk_gi_tenant` (`tenant_id`),
  KEY `fk_gi_gatepass` (`gatepass_id`,`tenant_id`),
  KEY `idx_gi_serial` (`tenant_id`,`serial_number`),
  CONSTRAINT `fk_gi_gatepass` FOREIGN KEY (`gatepass_id`, `tenant_id`) REFERENCES `gatepasses` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gi_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.gatepass_items: ~4 rows (approximately)
INSERT IGNORE INTO `gatepass_items` (`id`, `tenant_id`, `gatepass_id`, `item_name`, `description`, `quantity`, `serial_number`, `is_returnable`, `returned_quantity`, `created_at`) VALUES
	(1, 1, 1, 'HP Laptop', 'HP Laptop Test', 1, 'DDPE84NFI4', 0, 0, '2026-04-08 17:38:22'),
	(2, 1, 2, 'Kyocera Printer', 'Kyocera Printer going for repair', 1, 'DJ349DSN4', 0, 0, '2026-04-08 19:04:35'),
	(3, 1, 3, 'Dell Laptop', 'Going to work at home', 1, 'FKJE9RHKNO', 0, 0, '2026-04-08 19:13:52'),
	(4, 1, 4, 'Samsung 85 inch TV', 'Going for Out event', 1, 'LJDEN083403UJ', 0, 0, '2026-04-08 19:23:48'),
	(5, 1, 5, 'Samsung Phone', 'Samsung phone going for screen repair', 1, 'RJ0354RDF0EN', 0, 0, '2026-04-08 19:28:54'),
	(6, 1, 6, 'Dell Laptop', 'Laptop Test', 1, 'FKJE9RHKNO', 0, 0, '2026-04-11 12:26:22');

-- Dumping structure for table glee_live.gatepass_statuses
CREATE TABLE IF NOT EXISTS `gatepass_statuses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gs_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_gs_tenant_code` (`tenant_id`,`code`),
  UNIQUE KEY `uk_gs_tenant_id` (`id`,`tenant_id`),
  KEY `idx_gs_tenant` (`tenant_id`),
  CONSTRAINT `fk_gs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.gatepass_statuses: ~7 rows (approximately)
INSERT IGNORE INTO `gatepass_statuses` (`id`, `tenant_id`, `name`, `code`) VALUES
	(1, 1, 'Pending', 'pending'),
	(2, 1, 'Approved', 'approved'),
	(3, 1, 'Rejected', 'rejected'),
	(4, 1, 'Checked In', 'checked_in'),
	(5, 1, 'Checked Out', 'checked_out'),
	(6, 1, 'Cancelled', 'cancelled'),
	(7, 1, 'Expired', 'expired');

-- Dumping structure for table glee_live.gatepass_types
CREATE TABLE IF NOT EXISTS `gatepass_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `type_code` varchar(20) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `workflow_id` bigint unsigned DEFAULT NULL,
  `allowed_actions` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gpt_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_gpt_tenant_id` (`id`,`tenant_id`),
  KEY `idx_gpt_tenant` (`tenant_id`),
  KEY `idx_gpt_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_gpt_workflow` (`workflow_id`),
  KEY `fk_gpt_workflow` (`workflow_id`,`tenant_id`),
  CONSTRAINT `fk_gpt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gpt_workflow` FOREIGN KEY (`workflow_id`, `tenant_id`) REFERENCES `workflows` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.gatepass_types: ~6 rows (approximately)
INSERT IGNORE INTO `gatepass_types` (`id`, `tenant_id`, `name`, `type_code`, `description`, `is_active`, `created_at`, `workflow_id`, `allowed_actions`) VALUES
	(1, 1, 'Visitor Pass', 'VISITOR', 'Standard visitor entry pass', 1, '2026-04-08 19:29:16', NULL, NULL),
	(2, 1, 'Contractor Pass', 'CONTRACTOR', 'Pass for contracted workers', 1, '2026-04-08 19:29:16', 1, NULL),
	(3, 1, 'Delivery Pass', 'DELIVERY', 'Inbound/outbound delivery pass', 1, '2026-04-08 19:29:16', NULL, NULL),
	(4, 1, 'Equipment Out', 'EQUIP_OUT', 'Pass for equipment leaving premises', 1, '2026-04-08 19:29:16', 1, NULL),
	(5, 1, 'Vehicle Pass', 'VEHICLE', 'Vehicle entry/exit pass', 1, '2026-04-08 19:29:16', NULL, NULL),
	(6, 1, 'Temporary Staff', 'TEMP_STAFF', 'Temporary staff access pass', 1, '2026-04-08 19:29:16', 1, NULL);

-- Dumping structure for table glee_live.gatepass_workflow_instances
CREATE TABLE IF NOT EXISTS `gatepass_workflow_instances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `gatepass_id` bigint unsigned NOT NULL,
  `workflow_id` bigint unsigned NOT NULL,
  `current_step_order` int NOT NULL DEFAULT '1',
  `status` enum('in_progress','approved','rejected') NOT NULL DEFAULT 'in_progress',
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gwi_gatepass` (`gatepass_id`),
  UNIQUE KEY `uk_gwi_tenant_id` (`id`,`tenant_id`),
  KEY `idx_gwi_tenant` (`tenant_id`),
  KEY `idx_gwi_status` (`tenant_id`,`status`),
  KEY `idx_gwi_lookup` (`tenant_id`,`status`,`gatepass_id`),
  KEY `fk_gwi_gatepass` (`gatepass_id`,`tenant_id`),
  KEY `fk_gwi_workflow` (`workflow_id`,`tenant_id`),
  CONSTRAINT `fk_gwi_gatepass` FOREIGN KEY (`gatepass_id`, `tenant_id`) REFERENCES `gatepasses` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gwi_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gwi_workflow` FOREIGN KEY (`workflow_id`, `tenant_id`) REFERENCES `workflows` (`id`, `tenant_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.gatepass_workflow_instances: ~3 rows (approximately)
INSERT IGNORE INTO `gatepass_workflow_instances` (`id`, `tenant_id`, `gatepass_id`, `workflow_id`, `current_step_order`, `status`, `started_at`, `completed_at`) VALUES
	(1, 1, 3, 1, 3, 'approved', '2026-04-08 22:13:52', '2026-04-09 20:29:41'),
	(2, 1, 4, 1, 3, 'approved', '2026-04-08 22:23:48', '2026-04-09 20:29:39'),
	(3, 1, 5, 1, 3, 'approved', '2026-04-08 22:28:54', '2026-04-09 20:29:21'),
	(4, 1, 6, 1, 1, 'in_progress', '2026-04-11 15:26:22', NULL);

-- Dumping structure for table glee_live.identification_types
CREATE TABLE IF NOT EXISTS `identification_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_idtype_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_idtype_tenant_id` (`id`,`tenant_id`),
  KEY `idx_idtypes_tenant` (`tenant_id`),
  CONSTRAINT `fk_idtypes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.identification_types: ~5 rows (approximately)
INSERT IGNORE INTO `identification_types` (`id`, `tenant_id`, `name`) VALUES
	(1, 1, 'National ID'),
	(2, 1, 'Passport'),
	(3, 1, 'Driving Licence'),
	(4, 1, 'Company ID'),
	(5, 1, 'Military ID');

-- Dumping structure for table glee_live.modules
CREATE TABLE IF NOT EXISTS `modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4498 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.modules: ~15 rows (approximately)
INSERT IGNORE INTO `modules` (`id`, `name`) VALUES
	(1, 'gatepasses'),
	(2, 'visits'),
	(3, 'visitors'),
	(4, 'users'),
	(5, 'departments'),
	(6, 'workflows'),
	(7, 'approval'),
	(8, 'reports'),
	(9, 'tenant'),
	(10, 'audit_logs'),
	(11, 'dashboard'),
	(12, 'gatepass'),
	(15, 'roles'),
	(16, 'settings'),
	(17, 'audit');

-- Dumping structure for table glee_live.permissions
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `action_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_perm_action_module` (`action_id`,`module_id`),
  KEY `idx_perm_module` (`module_id`),
  CONSTRAINT `fk_perm_action` FOREIGN KEY (`action_id`) REFERENCES `actions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_perm_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14230 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.permissions: ~106 rows (approximately)
INSERT IGNORE INTO `permissions` (`id`, `action_id`, `module_id`) VALUES
	(1, 1, 7),
	(2, 3, 7),
	(3, 6, 7),
	(4, 9, 7),
	(5, 4, 7),
	(6, 2, 7),
	(7, 8, 7),
	(8, 7, 7),
	(9, 5, 7),
	(10, 1, 10),
	(11, 3, 10),
	(12, 6, 10),
	(13, 9, 10),
	(14, 4, 10),
	(15, 2, 10),
	(16, 8, 10),
	(17, 7, 10),
	(18, 5, 10),
	(19, 1, 5),
	(20, 3, 5),
	(21, 6, 5),
	(22, 9, 5),
	(23, 4, 5),
	(24, 2, 5),
	(25, 8, 5),
	(26, 7, 5),
	(27, 5, 5),
	(28, 1, 1),
	(29, 3, 1),
	(30, 6, 1),
	(31, 9, 1),
	(32, 4, 1),
	(33, 2, 1),
	(34, 8, 1),
	(35, 7, 1),
	(36, 5, 1),
	(37, 1, 8),
	(38, 3, 8),
	(39, 6, 8),
	(40, 9, 8),
	(41, 4, 8),
	(42, 2, 8),
	(43, 8, 8),
	(44, 7, 8),
	(45, 5, 8),
	(46, 1, 9),
	(47, 3, 9),
	(48, 6, 9),
	(49, 9, 9),
	(50, 4, 9),
	(51, 2, 9),
	(52, 8, 9),
	(53, 7, 9),
	(54, 5, 9),
	(55, 1, 4),
	(56, 3, 4),
	(57, 6, 4),
	(58, 9, 4),
	(59, 4, 4),
	(60, 2, 4),
	(61, 8, 4),
	(62, 7, 4),
	(63, 5, 4),
	(64, 1, 3),
	(65, 3, 3),
	(66, 6, 3),
	(67, 9, 3),
	(68, 4, 3),
	(69, 2, 3),
	(70, 8, 3),
	(71, 7, 3),
	(72, 5, 3),
	(73, 1, 2),
	(74, 3, 2),
	(75, 6, 2),
	(76, 9, 2),
	(77, 4, 2),
	(78, 2, 2),
	(79, 8, 2),
	(80, 7, 2),
	(81, 5, 2),
	(82, 1, 6),
	(83, 3, 6),
	(84, 6, 6),
	(85, 9, 6),
	(86, 4, 6),
	(87, 2, 6),
	(88, 8, 6),
	(89, 7, 6),
	(90, 5, 6),
	(128, 10, 11),
	(129, 2, 12),
	(130, 1, 12),
	(131, 3, 12),
	(132, 4, 12),
	(133, 5, 12),
	(134, 16, 12),
	(135, 17, 12),
	(136, 18, 12),
	(140, 22, 3),
	(144, 26, 4),
	(145, 2, 15),
	(146, 28, 15),
	(147, 3, 15),
	(148, 3, 16),
	(149, 1, 17);

-- Dumping structure for table glee_live.roles
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_role_tenant_id` (`id`,`tenant_id`),
  KEY `idx_roles_tenant` (`tenant_id`),
  CONSTRAINT `fk_roles_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.roles: ~5 rows (approximately)
INSERT IGNORE INTO `roles` (`id`, `tenant_id`, `name`) VALUES
	(1, 1, 'Super Admin'),
	(2, 1, 'Security Officer'),
	(3, 1, 'Receptionist'),
	(4, 1, 'Department Head'),
	(5, 1, 'Approver');

-- Dumping structure for table glee_live.role_permissions
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` bigint unsigned NOT NULL,
  `tenant_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `idx_rp_permission` (`permission_id`),
  KEY `idx_rp_tenant` (`tenant_id`),
  KEY `idx_rp_tenant_role` (`tenant_id`,`role_id`,`permission_id`),
  KEY `fk_rp_role` (`role_id`,`tenant_id`),
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.role_permissions: ~227 rows (approximately)
INSERT IGNORE INTO `role_permissions` (`role_id`, `tenant_id`, `permission_id`) VALUES
	(1, 1, 1),
	(1, 1, 2),
	(1, 1, 3),
	(1, 1, 4),
	(1, 1, 5),
	(1, 1, 6),
	(1, 1, 7),
	(1, 1, 8),
	(1, 1, 9),
	(1, 1, 10),
	(1, 1, 11),
	(1, 1, 12),
	(1, 1, 13),
	(1, 1, 14),
	(1, 1, 15),
	(1, 1, 16),
	(1, 1, 17),
	(1, 1, 18),
	(1, 1, 19),
	(1, 1, 20),
	(1, 1, 21),
	(1, 1, 22),
	(1, 1, 23),
	(1, 1, 24),
	(1, 1, 25),
	(1, 1, 26),
	(1, 1, 27),
	(1, 1, 28),
	(1, 1, 29),
	(1, 1, 30),
	(1, 1, 31),
	(1, 1, 32),
	(1, 1, 33),
	(1, 1, 34),
	(1, 1, 35),
	(1, 1, 36),
	(1, 1, 37),
	(1, 1, 38),
	(1, 1, 39),
	(1, 1, 40),
	(1, 1, 41),
	(1, 1, 42),
	(1, 1, 43),
	(1, 1, 44),
	(1, 1, 45),
	(1, 1, 46),
	(1, 1, 47),
	(1, 1, 48),
	(1, 1, 49),
	(1, 1, 50),
	(1, 1, 51),
	(1, 1, 52),
	(1, 1, 53),
	(1, 1, 54),
	(1, 1, 55),
	(1, 1, 56),
	(1, 1, 57),
	(1, 1, 58),
	(1, 1, 59),
	(1, 1, 60),
	(1, 1, 61),
	(1, 1, 62),
	(1, 1, 63),
	(1, 1, 64),
	(1, 1, 65),
	(1, 1, 66),
	(1, 1, 67),
	(1, 1, 68),
	(1, 1, 69),
	(1, 1, 70),
	(1, 1, 71),
	(1, 1, 72),
	(1, 1, 73),
	(1, 1, 74),
	(1, 1, 75),
	(1, 1, 76),
	(1, 1, 77),
	(1, 1, 78),
	(1, 1, 79),
	(1, 1, 80),
	(1, 1, 81),
	(1, 1, 82),
	(1, 1, 83),
	(1, 1, 84),
	(1, 1, 85),
	(1, 1, 86),
	(1, 1, 87),
	(1, 1, 88),
	(1, 1, 89),
	(1, 1, 90),
	(1, 1, 128),
	(1, 1, 129),
	(1, 1, 130),
	(1, 1, 131),
	(1, 1, 132),
	(1, 1, 133),
	(1, 1, 134),
	(1, 1, 135),
	(1, 1, 136),
	(1, 1, 140),
	(1, 1, 145),
	(1, 1, 146),
	(1, 1, 147),
	(1, 1, 148),
	(1, 1, 149),
	(2, 1, 1),
	(2, 1, 2),
	(2, 1, 3),
	(2, 1, 7),
	(2, 1, 8),
	(2, 1, 9),
	(2, 1, 10),
	(2, 1, 16),
	(2, 1, 17),
	(2, 1, 19),
	(2, 1, 25),
	(2, 1, 26),
	(2, 1, 28),
	(2, 1, 29),
	(2, 1, 30),
	(2, 1, 33),
	(2, 1, 34),
	(2, 1, 35),
	(2, 1, 36),
	(2, 1, 37),
	(2, 1, 43),
	(2, 1, 44),
	(2, 1, 46),
	(2, 1, 52),
	(2, 1, 53),
	(2, 1, 55),
	(2, 1, 61),
	(2, 1, 62),
	(2, 1, 64),
	(2, 1, 70),
	(2, 1, 71),
	(2, 1, 73),
	(2, 1, 79),
	(2, 1, 80),
	(2, 1, 82),
	(2, 1, 88),
	(2, 1, 89),
	(2, 1, 128),
	(2, 1, 129),
	(2, 1, 130),
	(2, 1, 131),
	(2, 1, 136),
	(3, 1, 1),
	(3, 1, 6),
	(3, 1, 10),
	(3, 1, 15),
	(3, 1, 19),
	(3, 1, 24),
	(3, 1, 28),
	(3, 1, 33),
	(3, 1, 37),
	(3, 1, 42),
	(3, 1, 46),
	(3, 1, 51),
	(3, 1, 55),
	(3, 1, 60),
	(3, 1, 64),
	(3, 1, 69),
	(3, 1, 73),
	(3, 1, 78),
	(3, 1, 82),
	(3, 1, 87),
	(4, 1, 1),
	(4, 1, 3),
	(4, 1, 9),
	(4, 1, 10),
	(4, 1, 12),
	(4, 1, 18),
	(4, 1, 19),
	(4, 1, 21),
	(4, 1, 27),
	(4, 1, 28),
	(4, 1, 30),
	(4, 1, 36),
	(4, 1, 37),
	(4, 1, 39),
	(4, 1, 45),
	(4, 1, 46),
	(4, 1, 48),
	(4, 1, 54),
	(4, 1, 55),
	(4, 1, 57),
	(4, 1, 63),
	(4, 1, 64),
	(4, 1, 66),
	(4, 1, 72),
	(4, 1, 73),
	(4, 1, 75),
	(4, 1, 81),
	(4, 1, 82),
	(4, 1, 84),
	(4, 1, 90),
	(5, 1, 1),
	(5, 1, 3),
	(5, 1, 9),
	(5, 1, 10),
	(5, 1, 12),
	(5, 1, 18),
	(5, 1, 19),
	(5, 1, 21),
	(5, 1, 27),
	(5, 1, 28),
	(5, 1, 30),
	(5, 1, 36),
	(5, 1, 37),
	(5, 1, 39),
	(5, 1, 45),
	(5, 1, 46),
	(5, 1, 48),
	(5, 1, 54),
	(5, 1, 55),
	(5, 1, 57),
	(5, 1, 63),
	(5, 1, 64),
	(5, 1, 66),
	(5, 1, 72),
	(5, 1, 73),
	(5, 1, 75),
	(5, 1, 81),
	(5, 1, 82),
	(5, 1, 84),
	(5, 1, 90);

-- Dumping structure for table glee_live.sessions
CREATE TABLE IF NOT EXISTS `sessions` (
  `token` char(43) NOT NULL,
  `data` blob NOT NULL,
  `expiry` timestamp(6) NOT NULL,
  PRIMARY KEY (`token`),
  KEY `expiry_idx` (`expiry`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.sessions: ~0 rows (approximately)

-- Dumping structure for table glee_live.tenants
CREATE TABLE IF NOT EXISTS `tenants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(120) NOT NULL,
  `logo` varchar(120) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `country` varchar(120) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.tenants: ~1 rows (approximately)
INSERT IGNORE INTO `tenants` (`id`, `name`, `code`, `logo`, `email`, `phone`, `country`, `is_active`, `created_at`, `updated_at`) VALUES
	(1, 'Glee Demo', 'glee', 'acme-logo.png', 'admin@glee.com', '+254700000000', 'Kenya', 1, '2026-04-08 19:29:15', '2026-04-09 22:20:05');

-- Dumping structure for table glee_live.tenant_settings
CREATE TABLE IF NOT EXISTS `tenant_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `setting_key` varchar(150) NOT NULL,
  `config_json` json NOT NULL,
  `setting_value` json DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ts_tenant_key` (`tenant_id`,`setting_key`),
  KEY `idx_ts_tenant` (`tenant_id`),
  CONSTRAINT `fk_ts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.tenant_settings: ~3 rows (approximately)
INSERT IGNORE INTO `tenant_settings` (`id`, `tenant_id`, `setting_key`, `config_json`, `setting_value`, `updated_at`) VALUES
	(1, 1, 'gatepass_numbering', '{"prefix": "GPN", "padding": 4, "sequence": 5, "current_year": "2026", "include_year": true, "reset_yearly": true, "include_month": false}', '{"last_seq": 0, "sequence": 4}', '2026-04-08 19:22:51'),
	(2, 1, 'badge_expiry_hours', '{"max": 72, "min": 1, "type": "number"}', '{"value": 8}', NULL),
	(3, 1, 'auto_checkout_hours', '{"max": 24, "min": 1, "type": "number"}', '{"value": 12}', NULL);

-- Dumping structure for table glee_live.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `username` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_tenant_email` (`tenant_id`,`email`),
  UNIQUE KEY `uk_user_tenant_username` (`tenant_id`,`username`),
  UNIQUE KEY `uk_user_tenant_id` (`id`,`tenant_id`),
  KEY `idx_users_tenant` (`tenant_id`),
  KEY `idx_users_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_users_dept` (`department_id`),
  KEY `fk_users_dept` (`department_id`,`tenant_id`),
  KEY `idx_users_reset_token` (`reset_token`),
  KEY `idx_users_reset_expires` (`reset_expires`),
  KEY `idx_users_dept_tenant` (`tenant_id`,`department_id`,`is_active`),
  CONSTRAINT `fk_users_dept` FOREIGN KEY (`department_id`, `tenant_id`) REFERENCES `departments` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.users: ~6 rows (approximately)
INSERT IGNORE INTO `users` (`id`, `tenant_id`, `email`, `password_hash`, `first_name`, `last_name`, `username`, `is_active`, `is_admin`, `created_at`, `updated_at`, `reset_token`, `reset_expires`, `department_id`) VALUES
	(1, 1, 'alumasinde@gmail.com', '$2y$10$9dbXPBzS8s4.cix8jf9Zruq8YlgWotbSP14VdwWoJF3IOaEv28V3e', 'Albert', 'Masinde', 'alumasinde', 1, 1, '2026-04-08 19:29:16', '2026-04-09 16:55:12', NULL, NULL, 2),
	(2, 1, 'security@gmail.com', '$2y$10$PHduosarn3mz9uzWRH4bNuUgFjWRyBCpgL9RwVVoPAGzBw03FFNT2', 'John', 'Guard', 'jguard', 1, 0, '2026-04-08 19:29:16', '2026-04-09 20:25:28', NULL, NULL, 5),
	(3, 1, 'reception@acme.com', '$2a$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'Jane', 'Desk', 'jdesk', 1, 0, '2026-04-08 19:29:16', NULL, NULL, NULL, 1),
	(4, 1, 'hod@gmail.com', '$2y$10$bZ/nVOar2FMmkxlCzCKT9OA40Hgi8C9J5N2MO8uia8Nqaa/yDI4i6', 'Alice', 'Anyango', 'atech', 1, 0, '2026-04-08 19:29:16', '2026-04-09 20:23:30', NULL, NULL, 2),
	(5, 1, 'finance.head@acme.com', '$2a$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'Bob', 'Funds', 'bfunds', 1, 0, '2026-04-08 19:29:16', NULL, NULL, NULL, 3),
	(6, 1, 'hr.head@acme.com', '$2a$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'Carol', 'People', 'cpeople', 1, 0, '2026-04-08 19:29:16', NULL, NULL, NULL, 4);

-- Dumping structure for table glee_live.user_otps
CREATE TABLE IF NOT EXISTS `user_otps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `purpose` varchar(50) NOT NULL DEFAULT 'login',
  `is_used` tinyint(1) NOT NULL DEFAULT '0',
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_lookup` (`tenant_id`,`user_id`,`purpose`,`is_used`,`expires_at`),
  KEY `idx_otp_user` (`user_id`,`tenant_id`),
  CONSTRAINT `fk_otp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.user_otps: ~0 rows (approximately)

-- Dumping structure for table glee_live.user_roles
CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `tenant_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `idx_ur_role` (`role_id`),
  KEY `idx_ur_tenant` (`tenant_id`),
  KEY `fk_ur_user` (`user_id`,`tenant_id`),
  KEY `fk_ur_role` (`role_id`,`tenant_id`),
  KEY `idx_ur_tenant_user` (`tenant_id`,`user_id`,`role_id`),
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`, `tenant_id`) REFERENCES `roles` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.user_roles: ~8 rows (approximately)
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`, `tenant_id`) VALUES
	(1, 1, 1),
	(2, 2, 1),
	(3, 3, 1),
	(4, 4, 1),
	(4, 5, 1),
	(5, 4, 1),
	(5, 5, 1),
	(6, 4, 1);

-- Dumping structure for table glee_live.visitors
CREATE TABLE IF NOT EXISTS `visitors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `id_type_id` bigint unsigned DEFAULT NULL,
  `id_number` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `risk_score` int NOT NULL DEFAULT '0',
  `is_blacklisted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint unsigned NOT NULL DEFAULT (0),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_visitor_tenant_id` (`id`,`tenant_id`),
  KEY `idx_visitors_tenant` (`tenant_id`),
  KEY `idx_visitor_phone` (`tenant_id`,`phone`),
  KEY `idx_visitors_name` (`tenant_id`,`last_name`,`first_name`),
  KEY `idx_visitor_id_lookup` (`tenant_id`,`id_type_id`,`id_number`),
  KEY `idx_visitor_blacklist` (`tenant_id`,`is_blacklisted`),
  KEY `fk_visitors_id_type` (`id_type_id`,`tenant_id`),
  KEY `fk_visitors_company` (`company_id`,`tenant_id`),
  CONSTRAINT `fk_visitors_company` FOREIGN KEY (`company_id`, `tenant_id`) REFERENCES `visitor_companies` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visitors_id_type` FOREIGN KEY (`id_type_id`, `tenant_id`) REFERENCES `identification_types` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visitors_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.visitors: ~0 rows (approximately)
INSERT IGNORE INTO `visitors` (`id`, `tenant_id`, `first_name`, `last_name`, `id_type_id`, `id_number`, `phone`, `email`, `company_id`, `risk_score`, `is_blacklisted`, `created_at`, `created_by`) VALUES
	(1, 1, 'Amos', 'Barasa', 1, '404147595', '0758465475', 'amosbarasa@gmail.com', 1, 10, 0, '2026-04-09 16:46:46', 0),
	(2, 1, 'Christina', 'Maumo', 1, '2864946209', '07473096493', 'cmaumo@gmail.com', 1, 10, 0, '2026-04-11 16:43:52', 0);

-- Dumping structure for table glee_live.visitor_companies
CREATE TABLE IF NOT EXISTS `visitor_companies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vc_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_vc_tenant_id` (`id`,`tenant_id`),
  KEY `idx_vc_tenant` (`tenant_id`),
  CONSTRAINT `fk_vc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.visitor_companies: ~0 rows (approximately)
INSERT IGNORE INTO `visitor_companies` (`id`, `tenant_id`, `name`, `created_at`, `updated_at`) VALUES
	(1, 1, 'AmoHub Limited', '2026-04-09 16:46:46', NULL);

-- Dumping structure for table glee_live.visitor_watchlist
CREATE TABLE IF NOT EXISTS `visitor_watchlist` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `visitor_id` bigint unsigned NOT NULL,
  `severity` varchar(50) DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vw_tenant` (`tenant_id`),
  KEY `idx_vw_visitor` (`visitor_id`),
  KEY `fk_vw_visitor` (`visitor_id`,`tenant_id`),
  KEY `idx_vw_tenant_severity` (`tenant_id`,`severity`),
  CONSTRAINT `fk_vw_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vw_visitor` FOREIGN KEY (`visitor_id`, `tenant_id`) REFERENCES `visitors` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.visitor_watchlist: ~0 rows (approximately)

-- Dumping structure for table glee_live.visits
CREATE TABLE IF NOT EXISTS `visits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `department_id` bigint unsigned NOT NULL,
  `visitor_id` bigint unsigned NOT NULL,
  `host_user_id` bigint unsigned DEFAULT NULL,
  `visit_type_id` bigint unsigned DEFAULT NULL,
  `visit_status_id` bigint unsigned DEFAULT NULL,
  `purpose` text,
  `expected_in` datetime DEFAULT NULL,
  `expected_out` datetime DEFAULT NULL,
  `checkin_time` datetime DEFAULT NULL,
  `checkout_time` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT (now()),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_visit_tenant_id` (`id`,`tenant_id`),
  KEY `idx_visits_tenant` (`tenant_id`),
  KEY `idx_visit_lookup` (`tenant_id`,`visitor_id`),
  KEY `idx_visits_dept` (`tenant_id`,`department_id`),
  KEY `idx_visits_active` (`tenant_id`,`visit_status_id`,`checkin_time`),
  KEY `idx_visits_tenant_time` (`tenant_id`,`created_at` DESC),
  KEY `fk_visits_dept` (`department_id`,`tenant_id`),
  KEY `fk_visits_visitor` (`visitor_id`,`tenant_id`),
  KEY `fk_visits_host_user` (`host_user_id`,`tenant_id`),
  KEY `fk_visits_type` (`visit_type_id`,`tenant_id`),
  KEY `fk_visits_status` (`visit_status_id`,`tenant_id`),
  KEY `fk_visits_created_by` (`created_by`,`tenant_id`),
  CONSTRAINT `fk_visits_created_by` FOREIGN KEY (`created_by`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visits_dept` FOREIGN KEY (`department_id`, `tenant_id`) REFERENCES `departments` (`id`, `tenant_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_visits_host_user` FOREIGN KEY (`host_user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visits_status` FOREIGN KEY (`visit_status_id`, `tenant_id`) REFERENCES `visit_statuses` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visits_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visits_type` FOREIGN KEY (`visit_type_id`, `tenant_id`) REFERENCES `visit_types` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visits_visitor` FOREIGN KEY (`visitor_id`, `tenant_id`) REFERENCES `visitors` (`id`, `tenant_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.visits: ~1 rows (approximately)
INSERT IGNORE INTO `visits` (`id`, `tenant_id`, `department_id`, `visitor_id`, `host_user_id`, `visit_type_id`, `visit_status_id`, `purpose`, `expected_in`, `expected_out`, `checkin_time`, `checkout_time`, `created_by`, `created_at`, `updated_at`) VALUES
	(1, 1, 2, 1, 1, 1, 2, 'Test Visit', '2026-04-09 17:00:00', '2026-04-09 19:30:00', '2026-04-09 16:48:20', NULL, 1, '2026-04-09 16:47:46', '2026-04-09 16:48:20');

-- Dumping structure for table glee_live.visit_badges
CREATE TABLE IF NOT EXISTS `visit_badges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `visit_id` bigint unsigned NOT NULL,
  `badge_code` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `printed_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vb_tenant_code` (`tenant_id`,`badge_code`),
  KEY `idx_vb_visit` (`visit_id`),
  KEY `fk_vb_visit` (`visit_id`,`tenant_id`),
  KEY `idx_vb_tenant_active` (`tenant_id`,`is_active`),
  CONSTRAINT `fk_vb_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vb_visit` FOREIGN KEY (`visit_id`, `tenant_id`) REFERENCES `visits` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.visit_badges: ~0 rows (approximately)
INSERT IGNORE INTO `visit_badges` (`id`, `tenant_id`, `visit_id`, `badge_code`, `is_active`, `printed_at`, `returned_at`) VALUES
	(1, 1, 1, 'BDG-B07D702C', 1, '2026-04-09 16:48:25', NULL);

-- Dumping structure for table glee_live.visit_statuses
CREATE TABLE IF NOT EXISTS `visit_statuses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vs_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_vs_tenant_code` (`tenant_id`,`code`),
  UNIQUE KEY `uk_vs_tenant_id` (`id`,`tenant_id`),
  KEY `idx_vs_tenant` (`tenant_id`),
  CONSTRAINT `fk_vs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.visit_statuses: ~5 rows (approximately)
INSERT IGNORE INTO `visit_statuses` (`id`, `tenant_id`, `name`, `code`) VALUES
	(1, 1, 'Scheduled', 'scheduled'),
	(2, 1, 'Checked In', 'checked_in'),
	(3, 1, 'Checked Out', 'checked_out'),
	(4, 1, 'No Show', 'no_show'),
	(5, 1, 'Cancelled', 'cancelled');

-- Dumping structure for table glee_live.visit_types
CREATE TABLE IF NOT EXISTS `visit_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vt_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_vt_tenant_id` (`id`,`tenant_id`),
  KEY `idx_vt_tenant` (`tenant_id`),
  CONSTRAINT `fk_vt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.visit_types: ~6 rows (approximately)
INSERT IGNORE INTO `visit_types` (`id`, `tenant_id`, `name`) VALUES
	(1, 1, 'Business Meeting'),
	(2, 1, 'Interview'),
	(3, 1, 'Delivery'),
	(4, 1, 'Maintenance'),
	(5, 1, 'Personal'),
	(6, 1, 'Contractor Work');

-- Dumping structure for table glee_live.workflows
CREATE TABLE IF NOT EXISTS `workflows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` varchar(250) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_wf_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_wf_tenant_id` (`id`,`tenant_id`),
  KEY `idx_wf_tenant` (`tenant_id`),
  KEY `idx_wf_tenant_active` (`tenant_id`,`is_active`),
  CONSTRAINT `fk_wf_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.workflows: ~0 rows (approximately)
INSERT IGNORE INTO `workflows` (`id`, `tenant_id`, `name`, `description`, `is_active`, `created_at`) VALUES
	(1, 1, 'Standard Approval', 'Two-step: Department Head then Security Officer', 1, '2026-04-08 19:29:16');

-- Dumping structure for table glee_live.workflow_gatepass_type
CREATE TABLE IF NOT EXISTS `workflow_gatepass_type` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `workflow_id` bigint unsigned NOT NULL,
  `gatepass_type_id` bigint unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_workflow_type` (`tenant_id`,`workflow_id`,`gatepass_type_id`),
  UNIQUE KEY `uk_wgt_tenant_id` (`id`,`tenant_id`),
  KEY `fk_wgt_workflow` (`workflow_id`,`tenant_id`),
  KEY `fk_wgt_gatepass_type` (`gatepass_type_id`,`tenant_id`),
  CONSTRAINT `fk_wgt_gatepass_type` FOREIGN KEY (`gatepass_type_id`, `tenant_id`) REFERENCES `gatepass_types` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wgt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wgt_workflow` FOREIGN KEY (`workflow_id`, `tenant_id`) REFERENCES `workflows` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.workflow_gatepass_type: ~0 rows (approximately)
INSERT IGNORE INTO `workflow_gatepass_type` (`id`, `tenant_id`, `workflow_id`, `gatepass_type_id`, `created_at`) VALUES
	(1, 1, 1, 4, '2026-04-08 22:02:00');

-- Dumping structure for table glee_live.workflow_steps
CREATE TABLE IF NOT EXISTS `workflow_steps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `workflow_id` bigint unsigned NOT NULL,
  `name` varchar(150) NOT NULL DEFAULT '',
  `role_id` bigint unsigned NOT NULL,
  `step_order` int NOT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT '1',
  `department_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ws_wf_order` (`workflow_id`,`step_order`),
  UNIQUE KEY `uk_ws_tenant_id` (`id`,`tenant_id`),
  KEY `idx_ws_tenant` (`tenant_id`),
  KEY `idx_ws_role` (`role_id`),
  KEY `idx_ws_dept` (`department_id`),
  KEY `fk_ws_workflow` (`workflow_id`,`tenant_id`),
  KEY `fk_ws_role` (`role_id`,`tenant_id`),
  KEY `fk_ws_dept` (`department_id`,`tenant_id`),
  CONSTRAINT `fk_ws_dept` FOREIGN KEY (`department_id`, `tenant_id`) REFERENCES `departments` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ws_role` FOREIGN KEY (`role_id`, `tenant_id`) REFERENCES `roles` (`id`, `tenant_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ws_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ws_workflow` FOREIGN KEY (`workflow_id`, `tenant_id`) REFERENCES `workflows` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glee_live.workflow_steps: ~3 rows (approximately)
INSERT IGNORE INTO `workflow_steps` (`id`, `tenant_id`, `workflow_id`, `name`, `role_id`, `step_order`, `is_mandatory`, `department_id`) VALUES
	(1, 1, 1, 'Department Head', 4, 1, 1, NULL),
	(2, 1, 1, 'Security Clearance', 2, 2, 1, NULL),
	(3, 1, 1, 'Admin Sign-off', 1, 3, 0, NULL);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

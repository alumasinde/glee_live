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

-- Dumping data for table tupass_demo.actions: ~13 rows (approximately)
INSERT INTO `actions` (`id`, `name`) VALUES
	(1, 'view'),
	(2, 'create'),
	(3, 'edit'),
	(4, 'delete'),
	(5, 'approve'),
	(6, 'access'),
	(9, 'update'),
	(12, 'checkin'),
	(13, 'checkout'),
	(14, 'print'),
	(18, 'blacklist'),
	(22, 'disable'),
	(24, 'assign');

-- Dumping data for table tupass_demo.audit_logs: ~54 rows (approximately)
INSERT INTO `audit_logs` (`id`, `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `metadata`, `ip_address`, `created_at`) VALUES
	(1, 1, 1, 'gatepass.created', 'gatepass', 3, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses", "method": "POST", "timestamp": "2026-02-22 20:44:54"}, "needs_approval": false, "gatepass_number": "DM-2026-0005"}', '127.0.0.1', '2026-02-22 20:44:54'),
	(2, 1, 1, 'gatepass.created', 'gatepass', 4, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses", "method": "POST", "timestamp": "2026-02-22 21:22:47"}, "needs_approval": false, "gatepass_number": "DM-2026-0006"}', '127.0.0.1', '2026-02-22 21:22:47'),
	(3, 1, 1, 'gatepass.created', 'gatepass', 5, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses", "method": "POST", "timestamp": "2026-02-22 21:49:51"}, "needs_approval": false, "gatepass_number": "DM-2026-0007"}', '127.0.0.1', '2026-02-22 21:49:51'),
	(4, 1, 1, 'gatepass.created', 'gatepass', 6, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses", "method": "POST", "timestamp": "2026-02-22 21:54:11"}, "needs_approval": false, "gatepass_number": "DM-2026-0008"}', '127.0.0.1', '2026-02-22 21:54:11'),
	(5, 1, 1, 'visitor.created', 'visitor', 2, '{"name": "Amos Masinde", "_context": {"ip": "127.0.0.1", "url": "/visitors", "method": "POST", "timestamp": "2026-02-23 18:23:02"}, "id_number": "23438903", "tenant_id": 1}', '127.0.0.1', '2026-02-23 18:23:02'),
	(6, 1, 1, 'visitor.created', 'visitor', 3, '{"name": "Jane Maina", "_context": {"ip": "127.0.0.1", "url": "/visitors", "method": "POST", "timestamp": "2026-02-23 18:36:50"}, "id_number": "42334872", "tenant_id": 1}', '127.0.0.1', '2026-02-23 18:36:50'),
	(7, 1, 1, 'visit.badge_issued', 'visit', 1, '{"_context": {"ip": "127.0.0.1", "url": "/visitors/issue-badge", "method": "POST", "timestamp": "2026-02-23 18:38:03"}, "issued_by": 1, "tenant_id": 1, "badge_code": "BDG-6B8E88B6"}', '127.0.0.1', '2026-02-23 18:38:03'),
	(8, 1, 1, 'visitor.unblacklisted', 'visitor', 1, '{"_context": {"ip": "127.0.0.1", "url": "/visitors/1/unblacklist", "method": "POST", "timestamp": "2026-02-24 17:29:55"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 17:29:55'),
	(9, 1, 1, 'visitor.blacklisted', 'visitor', 3, '{"_context": {"ip": "127.0.0.1", "url": "/visitors/3/blacklist", "method": "POST", "timestamp": "2026-02-24 17:30:02"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 17:30:02'),
	(10, 1, 1, 'visitor.blacklisted', 'visitor', 2, '{"_context": {"ip": "127.0.0.1", "url": "/visitors/2/blacklist", "method": "POST", "timestamp": "2026-02-24 17:30:05"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 17:30:05'),
	(11, 1, 1, 'visitor.unblacklisted', 'visitor', 3, '{"_context": {"ip": "127.0.0.1", "url": "/visitors/3/unblacklist", "method": "POST", "timestamp": "2026-02-24 17:30:07"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 17:30:07'),
	(12, 1, 1, 'visitor.unblacklisted', 'visitor', 2, '{"_context": {"ip": "127.0.0.1", "url": "/visitors/2/unblacklist", "method": "POST", "timestamp": "2026-02-24 17:30:09"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 17:30:09'),
	(13, 1, 1, 'visit.created', 'visit', 2, '{"_context": {"ip": "127.0.0.1", "url": "/visits", "method": "POST", "timestamp": "2026-02-24 20:02:56"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 20:02:56'),
	(14, 1, 1, 'visit.badge_issued', 'visit', 1, '{"_context": {"ip": "127.0.0.1", "url": "/badges/1/issue", "method": "POST", "timestamp": "2026-02-24 20:38:57"}, "tenant_id": 1, "badge_code": "BDG-6A9D3057"}', '127.0.0.1', '2026-02-24 20:38:57'),
	(15, 1, 1, 'visit.badge_returned', 'visit', 1, '{"_context": {"ip": "127.0.0.1", "url": "/badges/1/return", "method": "POST", "timestamp": "2026-02-24 20:57:12"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 20:57:12'),
	(16, 1, 1, 'visit.checkout', 'visit', 1, '{"_context": {"ip": "127.0.0.1", "url": "/visits/1/checkout", "method": "POST", "timestamp": "2026-02-24 20:57:37"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 20:57:37'),
	(17, 1, 1, 'visit.checkin', 'visit', 2, '{"_context": {"ip": "127.0.0.1", "url": "/visits/2/checkin", "method": "POST", "timestamp": "2026-02-24 20:57:40"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 20:57:40'),
	(18, 1, 1, 'visit.badge_issued', 'visit', 2, '{"_context": {"ip": "127.0.0.1", "url": "/badges/2/issue", "method": "POST", "timestamp": "2026-02-24 21:03:33"}, "tenant_id": 1, "badge_code": "BDG-E67DFDA1"}', '127.0.0.1', '2026-02-24 21:03:33'),
	(19, 1, 1, 'visit.badge_returned', 'visit', 2, '{"_context": {"ip": "127.0.0.1", "url": "/badges/2/return", "method": "POST", "timestamp": "2026-02-24 21:03:39"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 21:03:39'),
	(20, 1, 1, 'visit.badge_issued', 'visit', 2, '{"_context": {"ip": "127.0.0.1", "url": "/badges/2/issue", "method": "POST", "timestamp": "2026-02-24 21:03:40"}, "tenant_id": 1, "badge_code": "BDG-FBDF51D0"}', '127.0.0.1', '2026-02-24 21:03:40'),
	(21, 1, 1, 'visit.badge_returned', 'visit', 2, '{"_context": {"ip": "127.0.0.1", "url": "/badges/2/return", "method": "POST", "timestamp": "2026-02-24 21:03:42"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 21:03:42'),
	(22, 1, 1, 'visit.checkout', 'visit', 2, '{"_context": {"ip": "127.0.0.1", "url": "/visits/2/checkout", "method": "POST", "timestamp": "2026-02-24 21:09:16"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 21:09:16'),
	(23, 1, 1, 'visit.created', 'visit', 3, '{"_context": {"ip": "127.0.0.1", "url": "/visits", "method": "POST", "timestamp": "2026-02-24 21:45:07"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 21:45:07'),
	(24, 1, 1, 'visit.checkin', 'visit', 3, '{"_context": {"ip": "127.0.0.1", "url": "/visits/3/checkin", "method": "POST", "timestamp": "2026-02-24 21:45:12"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 21:45:12'),
	(25, 1, 1, 'visit.badge_issued', 'visit', 3, '{"_context": {"ip": "127.0.0.1", "url": "/badges/3/issue", "method": "POST", "timestamp": "2026-02-24 21:45:16"}, "tenant_id": 1, "badge_code": "BDG-8C4F9892"}', '127.0.0.1', '2026-02-24 21:45:16'),
	(26, 1, 1, 'visit.badge_returned', 'visit', 3, '{"_context": {"ip": "127.0.0.1", "url": "/badges/3/return", "method": "POST", "timestamp": "2026-02-24 21:45:19"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 21:45:19'),
	(27, 1, 1, 'visit.checkout', 'visit', 3, '{"_context": {"ip": "127.0.0.1", "url": "/visits/3/checkout", "method": "POST", "timestamp": "2026-02-24 21:45:47"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 21:45:47'),
	(28, 1, 1, 'visitor.updated', 'visitor', 3, '{"_context": {"ip": "127.0.0.1", "url": "/visitors/3/update", "method": "POST", "timestamp": "2026-02-24 22:31:33"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 22:31:33'),
	(29, 1, 1, 'visitor.updated', 'visitor', 3, '{"_context": {"ip": "127.0.0.1", "url": "/visitors/3/update", "method": "POST", "timestamp": "2026-02-24 22:31:56"}, "tenant_id": 1}', '127.0.0.1', '2026-02-24 22:31:56'),
	(30, 1, 1, 'visit.created', 'visit', 4, '{"_context": {"ip": "127.0.0.1", "url": "/visits", "method": "POST", "timestamp": "2026-02-25 19:48:06"}, "tenant_id": 1}', '127.0.0.1', '2026-02-25 19:48:06'),
	(31, 1, 1, 'visit.checkin', 'visit', 4, '{"_context": {"ip": "127.0.0.1", "url": "/visits/4/checkin", "method": "POST", "timestamp": "2026-02-25 19:48:19"}, "tenant_id": 1}', '127.0.0.1', '2026-02-25 19:48:19'),
	(32, 1, 1, 'visit.badge_issued', 'visit', 4, '{"_context": {"ip": "127.0.0.1", "url": "/badges/4/issue", "method": "POST", "timestamp": "2026-02-25 19:48:27"}, "tenant_id": 1, "badge_code": "BDG-3D3964D7"}', '127.0.0.1', '2026-02-25 19:48:27'),
	(33, 1, 1, 'visit.badge_returned', 'visit', 4, '{"_context": {"ip": "127.0.0.1", "url": "/badges/4/return", "method": "POST", "timestamp": "2026-02-25 19:48:31"}, "tenant_id": 1}', '127.0.0.1', '2026-02-25 19:48:31'),
	(34, 1, 1, 'visit.checkout', 'visit', 4, '{"_context": {"ip": "127.0.0.1", "url": "/visits/4/checkout", "method": "POST", "timestamp": "2026-02-25 19:48:33"}, "tenant_id": 1}', '127.0.0.1', '2026-02-25 19:48:33'),
	(35, 1, 1, 'visit.created', 'visit', 5, '{"_context": {"ip": "127.0.0.1", "url": "/visits", "method": "POST", "timestamp": "2026-02-27 17:28:10"}, "tenant_id": 1}', '127.0.0.1', '2026-02-27 17:28:10'),
	(36, 1, 1, 'visit.checkin', 'visit', 5, '{"_context": {"ip": "127.0.0.1", "url": "/visits/5/checkin", "method": "POST", "timestamp": "2026-02-27 17:28:23"}, "tenant_id": 1}', '127.0.0.1', '2026-02-27 17:28:23'),
	(37, 1, 1, 'gatepass.created', 'gatepass', 7, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses", "method": "POST", "timestamp": "2026-02-27 17:29:07"}, "needs_approval": false, "gatepass_number": "DM-2026-0009"}', '127.0.0.1', '2026-02-27 17:29:07'),
	(38, 1, 3, 'gatepass.created', 'gatepass', 8, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses", "method": "POST", "timestamp": "2026-02-27 21:37:38"}, "needs_approval": true, "gatepass_number": "DM-2026-0010"}', '127.0.0.1', '2026-02-27 21:37:38'),
	(39, 1, 3, 'gatepass.checked_in', 'gatepass', 7, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses/7/checkin", "method": "POST", "timestamp": "2026-02-27 21:37:53"}, "timestamp": "2026-02-27 21:37:53"}', '127.0.0.1', '2026-02-27 21:37:53'),
	(40, 1, 2, 'visit.badge_issued', 'visit', 5, '{"_context": {"ip": "127.0.0.1", "url": "/badges/5/issue", "method": "POST", "timestamp": "2026-02-27 23:07:48"}, "tenant_id": 1, "badge_code": "BDG-6BFF614E"}', '127.0.0.1', '2026-02-27 23:07:48'),
	(41, 1, 1, 'gatepass.created', 'gatepass', 9, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses", "method": "POST", "timestamp": "2026-02-28 16:22:56"}, "needs_approval": true, "gatepass_number": "DM-2026-0011"}', '127.0.0.1', '2026-02-28 16:22:56'),
	(42, 1, 1, 'gatepass.checked_out', 'gatepass', 6, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses/6/checkout", "method": "POST", "timestamp": "2026-02-28 21:32:47"}, "timestamp": "2026-02-28 21:32:47"}', '127.0.0.1', '2026-02-28 21:32:47'),
	(43, 1, 1, 'visit.badge_returned', 'visit', 5, '{"_context": {"ip": "127.0.0.1", "url": "/badges/5/return", "method": "POST", "timestamp": "2026-03-01 00:31:20"}, "tenant_id": 1}', '127.0.0.1', '2026-03-01 00:31:20'),
	(44, 1, 1, 'visit.checkout', 'visit', 5, '{"_context": {"ip": "127.0.0.1", "url": "/visits/5/checkout", "method": "POST", "timestamp": "2026-03-01 00:31:23"}, "tenant_id": 1}', '127.0.0.1', '2026-03-01 00:31:23'),
	(45, 1, 1, 'visit.created', 'visit', 6, '{"_context": {"ip": "127.0.0.1", "url": "/visits", "method": "POST", "timestamp": "2026-03-01 00:31:52"}, "tenant_id": 1}', '127.0.0.1', '2026-03-01 00:31:52'),
	(46, 1, 1, 'visit.checkin', 'visit', 6, '{"_context": {"ip": "127.0.0.1", "url": "/visits/6/checkin", "method": "POST", "timestamp": "2026-03-01 00:32:07"}, "tenant_id": 1}', '127.0.0.1', '2026-03-01 00:32:07'),
	(47, 1, 1, 'gatepass.created', 'gatepass', 11, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses", "method": "POST", "timestamp": "2026-03-01 00:35:21"}, "needs_approval": true, "gatepass_number": "DM-2026-0012"}', '127.0.0.1', '2026-03-01 00:35:21'),
	(48, 1, 1, 'gatepass.checked_out', 'gatepass', 7, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses/7/checkout", "method": "POST", "timestamp": "2026-03-04 19:34:06"}, "timestamp": "2026-03-04 19:34:06"}', '127.0.0.1', '2026-03-04 19:34:06'),
	(49, 1, 1, 'gatepass.checked_out', 'gatepass', 4, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses/4/checkout", "method": "POST", "timestamp": "2026-03-04 19:34:32"}, "timestamp": "2026-03-04 19:34:32"}', '127.0.0.1', '2026-03-04 19:34:32'),
	(50, 1, 1, 'gatepass.checked_out', 'gatepass', 3, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses/3/checkout", "method": "POST", "timestamp": "2026-03-04 19:34:41"}, "timestamp": "2026-03-04 19:34:41"}', '127.0.0.1', '2026-03-04 19:34:41'),
	(51, 1, 1, 'gatepass.checked_out', 'gatepass', 9, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses/9/checkout", "method": "POST", "timestamp": "2026-03-04 19:48:37"}, "timestamp": "2026-03-04 19:48:37"}', '127.0.0.1', '2026-03-04 19:48:37'),
	(52, 1, 1, 'gatepass.checked_out', 'gatepass', 8, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses/8/checkout", "method": "POST", "timestamp": "2026-03-04 19:48:39"}, "timestamp": "2026-03-04 19:48:39"}', '127.0.0.1', '2026-03-04 19:48:39'),
	(53, 1, 1, 'gatepass.checked_in', 'gatepass', 8, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses/8/checkin", "method": "POST", "timestamp": "2026-03-04 19:48:49"}, "timestamp": "2026-03-04 19:48:49"}', '127.0.0.1', '2026-03-04 19:48:49'),
	(54, 1, 1, 'visit.badge_issued', 'visit', 6, '{"_context": {"ip": "127.0.0.1", "url": "/badges/6/issue", "method": "POST", "timestamp": "2026-03-04 20:00:45"}, "tenant_id": 1, "badge_code": "BDG-34C20133"}', '127.0.0.1', '2026-03-04 20:00:45'),
	(55, 1, 1, 'gatepass.created', 'gatepass', 12, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses", "method": "POST", "timestamp": "2026-03-04 20:01:23"}, "needs_approval": false, "gatepass_number": "DM-2026-0013"}', '127.0.0.1', '2026-03-04 20:01:23'),
	(56, 1, 1, 'gatepass.checked_out', 'gatepass', 12, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses/12/checkout", "method": "POST", "timestamp": "2026-03-04 20:04:08"}, "timestamp": "2026-03-04 20:04:08"}', '127.0.0.1', '2026-03-04 20:04:08'),
	(57, 1, 1, 'gatepass.checked_in', 'gatepass', 12, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses/12/checkin", "method": "POST", "timestamp": "2026-03-04 20:04:16"}, "timestamp": "2026-03-04 20:04:16"}', '127.0.0.1', '2026-03-04 20:04:16'),
	(58, 1, 1, 'gatepass.created', 'gatepass', 13, '{"_context": {"ip": "127.0.0.1", "url": "/gatepasses", "method": "POST", "timestamp": "2026-03-04 20:18:31"}, "needs_approval": false, "gatepass_number": "DM-2026-0014"}', '127.0.0.1', '2026-03-04 20:18:31'),
	(59, 1, 1, 'gatepass.created', 'gatepass', 3, '{"_context": {"ip": "::1", "url": "/gatepasses", "method": "POST", "timestamp": "2026-03-17 17:15:49"}, "needs_approval": true, "gatepass_number": "DM-2026-0015"}', '::1', '2026-03-17 17:15:49');

-- Dumping data for table tupass_demo.departments: ~6 rows (approximately)
INSERT INTO `departments` (`id`, `tenant_id`, `name`, `code`, `is_active`, `created_at`) VALUES
	(1, 1, 'IT Department', 'IT', 1, '2026-02-22 12:53:23'),
	(2, 1, 'Security', 'SEC', 1, '2026-02-22 12:53:23'),
	(3, 1, 'Human Resource', 'HR', 1, '2026-02-22 12:53:23'),
	(4, 1, 'Administrations', 'ADM', 1, '2026-02-22 12:53:23'),
	(5, 1, 'Finance', 'FIN', 1, '2026-02-22 12:53:23'),
	(6, 1, 'Operations', 'OPS', 1, '2026-02-22 12:53:23');

-- Dumping data for table tupass_demo.gatepasses: ~2 rows (approximately)
INSERT INTO `gatepasses` (`id`, `tenant_id`, `visit_id`, `gatepass_type_id`, `gatepass_number`, `status_id`, `department_id`, `checked_in_by`, `checked_out_by`, `actual_in`, `actual_out`, `created_by`, `created_at`, `purpose`, `is_returnable`, `expected_return_date`, `actual_return_date`, `is_fully_returned`, `needs_approval`) VALUES
	(1, 1, 1, 2, 'GPN-2026-0007', 2, 1, 3, 3, '2026-03-14 05:23:55', '2026-03-14 05:23:41', 3, '2026-03-13 14:28:52', 'Test gatepass out, and Approval', 1, '2026-03-17 23:00:00', NULL, 0, 1),
	(2, 1, 1, 1, 'GPN-2026-0008', 2, 1, 3, NULL, '2026-03-14 05:13:43', '2026-03-13 15:23:04', 3, '2026-03-13 14:58:17', 'Test gatepass In', 0, NULL, NULL, 0, 0),
	(3, 1, NULL, 2, 'DM-2026-0015', 1, 1, NULL, NULL, NULL, NULL, 1, '2026-03-17 17:15:49', 'Test Gatepass Out', 1, '2026-03-20 00:00:00', NULL, 0, 1);

-- Dumping data for table tupass_demo.gatepass_approvals: ~3 rows (approximately)
INSERT INTO `gatepass_approvals` (`id`, `tenant_id`, `workflow_instance_id`, `workflow_step_id`, `approver_user_id`, `status`, `comments`, `acted_at`, `created_at`) VALUES
	(1, 1, 1, 1, 2, 'approved', 'Test approval by HOD', '2026-03-13 11:40:44', '2026-03-13 14:28:52'),
	(2, 1, 1, 2, 4, 'approved', 'Test approval by Security Manager', '2026-03-13 11:41:40', '2026-03-13 14:40:44'),
	(3, 1, 1, 3, 5, 'approved', 'Final approval by GM', '2026-03-13 11:42:00', '2026-03-13 14:41:40'),
	(4, 1, 2, 1, 2, 'pending', NULL, NULL, '2026-03-17 17:15:49');

-- Dumping data for table tupass_demo.gatepass_items: ~2 rows (approximately)
INSERT INTO `gatepass_items` (`id`, `tenant_id`, `gatepass_id`, `item_name`, `description`, `quantity`, `serial_number`, `is_returnable`, `returned_quantity`, `created_at`) VALUES
	(1, 1, 1, 'HP LAPTOP', 'HP Laptop going out for repair', 1, '32847N48R', 0, 0, '2026-03-13 11:28:52'),
	(2, 1, 2, 'Kyocera Scanner', 'Kyocera scanner', 1, 'FJH3984N29', 0, 0, '2026-03-13 11:58:17'),
	(3, 1, 3, 'HP Laptop', 'HP Laptop Test', 1, 'DDPE84NFI4', 0, 0, '2026-03-17 14:15:49');

-- Dumping data for table tupass_demo.gatepass_statuses: ~6 rows (approximately)
INSERT INTO `gatepass_statuses` (`id`, `tenant_id`, `name`, `code`) VALUES
	(1, 1, 'Pending', 'PENDING'),
	(2, 1, 'Checked In', 'CHECKED_IN'),
	(3, 1, 'Checked Out', 'CHECKED_OUT'),
	(4, 1, 'Returned', 'RETURNED'),
	(5, 1, 'Approved', 'APPROVED'),
	(6, 1, 'Rejected', 'REJECTED'),
	(8, 1, 'Cancelled', 'CANCELLED');

-- Dumping data for table tupass_demo.gatepass_types: ~6 rows (approximately)
INSERT INTO `gatepass_types` (`id`, `tenant_id`, `name`, `type_code`, `description`, `is_active`, `created_at`, `workflow_id`, `allowed_actions`) VALUES
	(1, 1, 'Gatepass In', 'IN', 'Temporary movement of items out of premises', 1, '2026-02-20 10:07:05', 2, '["checkin", "checkout"]'),
	(2, 1, 'Gatepass Out', 'OUT', 'Permanent movement of items out of premises', 1, '2026-02-20 10:07:05', 1, '["checkin", "checkout"]'),
	(3, 1, 'Visitor', 'VISITOR', 'Visitor-related item movement', 1, '2026-02-20 10:07:05', 1, '["checkin", "checkout"]'),
	(4, 1, 'Delivery', 'DELIVERY', 'Movement between branches or departments', 1, '2026-02-20 10:07:05', 1, '["checkin", "checkout"]'),
	(5, 1, 'Repair', 'REPAIR', 'Items sent out for repair and expected to return', 1, '2026-02-20 10:07:05', 1, '["checkin", "checkout"]'),
	(6, 1, 'Contractor', 'CONTRACTOR', 'Contractor tools entering or leaving site', 1, '2026-02-20 10:07:05', 1, '["checkin", "checkout"]');

-- Dumping data for table tupass_demo.gatepass_workflow_instances: ~1 rows (approximately)
INSERT INTO `gatepass_workflow_instances` (`id`, `tenant_id`, `gatepass_id`, `workflow_id`, `current_step_order`, `status`, `started_at`, `completed_at`) VALUES
	(1, 1, 1, 1, 3, 'approved', '2026-03-13 14:28:52', '2026-03-13 11:42:00'),
	(2, 1, 3, 1, 1, 'in_progress', '2026-03-17 17:15:49', NULL);

-- Dumping data for table tupass_demo.identification_types: ~2 rows (approximately)
INSERT INTO `identification_types` (`id`, `tenant_id`, `name`) VALUES
	(1, 1, 'National ID'),
	(2, 1, 'Passport');

-- Dumping data for table tupass_demo.modules: ~9 rows (approximately)
INSERT INTO `modules` (`id`, `name`) VALUES
	(1, 'dashboard'),
	(2, 'Gatepass'),
	(3, 'Visitors'),
	(4, 'Users'),
	(5, 'Reports'),
	(10, 'roles'),
	(11, 'settings'),
	(12, 'audit'),
	(13, 'Approval');

-- Dumping data for table tupass_demo.permissions: ~25 rows (approximately)
INSERT INTO `permissions` (`id`, `action_id`, `module_id`) VALUES
	(1, 1, 1),
	(2, 1, 2),
	(3, 2, 2),
	(4, 5, 2),
	(5, 6, 1),
	(8, 9, 2),
	(9, 4, 2),
	(11, 12, 2),
	(12, 13, 2),
	(13, 14, 2),
	(14, 2, 3),
	(15, 1, 3),
	(16, 9, 3),
	(17, 18, 3),
	(18, 2, 4),
	(19, 1, 4),
	(20, 9, 4),
	(21, 22, 4),
	(22, 2, 10),
	(23, 24, 10),
	(24, 9, 10),
	(25, 9, 11),
	(26, 1, 12),
	(27, 5, 13),
	(28, 1, 13);

-- Dumping data for table tupass_demo.roles: ~6 rows (approximately)
INSERT INTO `roles` (`id`, `tenant_id`, `name`) VALUES
	(1, 1, 'admin'),
	(2, 1, 'Gate Man'),
	(3, 1, 'Head of Department'),
	(4, 1, 'Security Manager'),
	(5, 1, 'General Manager'),
	(6, 1, 'Staff');

-- Dumping data for table tupass_demo.role_permissions: ~70 rows (approximately)
INSERT INTO `role_permissions` (`role_id`, `tenant_id`, `permission_id`) VALUES
	(1, 1, 1),
	(1, 1, 2),
	(1, 1, 3),
	(1, 1, 4),
	(1, 1, 5),
	(1, 1, 8),
	(1, 1, 9),
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
	(2, 1, 2),
	(2, 1, 11),
	(2, 1, 12),
	(3, 1, 1),
	(3, 1, 2),
	(3, 1, 3),
	(3, 1, 5),
	(3, 1, 8),
	(3, 1, 13),
	(3, 1, 27),
	(3, 1, 28),
	(4, 1, 1),
	(4, 1, 2),
	(4, 1, 3),
	(4, 1, 4),
	(4, 1, 5),
	(4, 1, 8),
	(4, 1, 13),
	(4, 1, 14),
	(4, 1, 15),
	(4, 1, 16),
	(4, 1, 17),
	(4, 1, 27),
	(4, 1, 28),
	(5, 1, 1),
	(5, 1, 2),
	(5, 1, 3),
	(5, 1, 4),
	(5, 1, 5),
	(5, 1, 8),
	(5, 1, 9),
	(5, 1, 11),
	(5, 1, 12),
	(5, 1, 13),
	(5, 1, 14),
	(5, 1, 15),
	(5, 1, 16),
	(5, 1, 17),
	(5, 1, 25),
	(5, 1, 27),
	(5, 1, 28),
	(6, 1, 1),
	(6, 1, 2),
	(6, 1, 3),
	(6, 1, 5),
	(6, 1, 14),
	(6, 1, 15);

-- Dumping data for table tupass_demo.sessions: ~0 rows (approximately)

-- Dumping data for table tupass_demo.tenants: ~1 rows (approximately)
INSERT INTO `tenants` (`id`, `name`, `code`, `logo`, `email`, `phone`, `country`, `is_active`, `created_at`, `updated_at`) VALUES
	(1, 'Glee Hotel Limited', 'glee', '', 'admin@albatechsolutions.com', '+254700000000', 'Kenya', 1, '2026-02-20 07:45:46', '2026-03-19 17:38:20');

-- Dumping data for table tupass_demo.tenant_settings: ~2 rows (approximately)
INSERT INTO `tenant_settings` (`id`, `tenant_id`, `setting_key`, `config_json`, `setting_value`, `updated_at`) VALUES
	(1, 1, 'gatepass_numbering', '{"prefix": "GPN", "padding": 4, "sequence": 9, "current_year": 2026, "include_year": true, "reset_yearly": true, "include_month": false}', '{"prefix": "DM", "padding": 4, "sequence": 16, "current_year": "2026", "include_year": true, "reset_yearly": true, "include_month": false}', NULL),
	(3, 1, 'company_profile', 'null', '{"email": "", "phone": "", "country": "", "company_name": ""}', NULL);

-- Dumping data for table tupass_demo.users: ~5 rows (approximately)
INSERT INTO `users` (`id`, `tenant_id`, `email`, `password_hash`, `first_name`, `last_name`, `username`, `is_active`, `is_admin`, `created_at`, `updated_at`, `reset_token`, `reset_expires`, `department_id`) VALUES
	(1, 1, 'alumasinde@gmail.com', '$2y$10$dfXSjtgSckWSD3quSYxCze0g47LUsidkbL/Tf5epvQ9tZjUAMvw02', 'Albert', 'Masinde', 'alumasinde', 1, 1, '2026-02-20 07:45:46', '2026-03-17 17:14:55', NULL, NULL, 1),
	(2, 1, 'hod@gmail.com', '$2y$10$HrqooVslqGc91w0FbUnf8OzAJvqUvZyFMbHAFMg5cLhbvZw1JV6xO', 'Paul', 'Mwangi', 'pmwangi', 1, 0, '2026-02-25 22:17:21', '2026-02-27 21:39:07', NULL, NULL, 1),
	(3, 1, 'user@gmail.com', '$2y$10$nu/3/KH1BUo4XaLBLSnDn.rw5HeHBX0ixEVNY3emBhpKoOeatMVDa', 'Sandra', 'Atieno', 'satieno', 1, 0, '2026-02-25 22:17:21', '2026-02-27 21:36:37', NULL, NULL, 1),
	(4, 1, 'secmanager@gmail.com', '$2y$10$sZXiDJbrFVXUuM2vRwri4.0ZBDUT1gIJ5rXGoFQ/hocvzmIoG6r9u', 'George', 'Okoth', 'gokoth', 1, 0, '2026-02-25 22:17:21', '2026-02-28 21:40:43', NULL, NULL, 2),
	(5, 1, 'gm@gmail.com', '$2y$10$k8Rjvq62rOUqjm1w1vvYDetQwSRLRo0.f2yc9B32OFEoQLwa5Mi66', 'Mercy', 'Wambui', 'mwambui', 1, 0, '2026-02-25 22:17:21', '2026-02-28 21:57:05', NULL, NULL, 4),
	(6, 1, 'pkemboi@gmail.com', '$2a$10$DtdjAWe21FaztGpT4kzuoeZq95P/O5oGBFyVUcXEfGOfAfO7Y02.m', 'Paul', 'Kemboi', 'pkemboi', 1, 0, '2026-03-15 15:36:20', NULL, NULL, NULL, NULL);

-- Dumping data for table tupass_demo.user_roles: ~6 rows (approximately)
INSERT INTO `user_roles` (`user_id`, `role_id`, `tenant_id`) VALUES
	(1, 1, 1),
	(2, 3, 1),
	(3, 6, 1),
	(4, 2, 1),
	(4, 4, 1),
	(5, 5, 1);

-- Dumping data for table tupass_demo.visitors: ~3 rows (approximately)
INSERT INTO `visitors` (`id`, `tenant_id`, `first_name`, `last_name`, `id_type_id`, `id_number`, `phone`, `email`, `company_id`, `risk_score`, `is_blacklisted`, `created_at`, `created_by`) VALUES
	(1, 1, 'John', 'Doe', 1, '12345678', '+254711111111', 'johndoe@gmail.com', 1, 0, 0, '2026-02-20 07:45:46', 1),
	(2, 1, 'Amos', 'Masinde', 1, '23438903', '0733908379', 'amosbarasa@gmail.com', 2, 0, 0, '2026-02-23 18:23:02', 1),
	(3, 1, 'Jane', 'Maina', 1, '42334872', '0725064005', 'jmaina@outlook.com', 2, 0, 0, '2026-02-23 18:36:50', 1);

-- Dumping data for table tupass_demo.visitor_companies: ~2 rows (approximately)
INSERT INTO `visitor_companies` (`id`, `tenant_id`, `name`, `created_at`, `updated_at`) VALUES
	(1, 1, 'Tech Supplies Ltd', '2026-02-20 07:45:46', NULL),
	(2, 1, 'Glee Hotel Ltd', '2026-02-23 18:36:50', '2026-03-06 19:42:54');

-- Dumping data for table tupass_demo.visitor_watchlist: ~0 rows (approximately)

-- Dumping data for table tupass_demo.visits: ~6 rows (approximately)
INSERT INTO `visits` (`id`, `tenant_id`, `department_id`, `visitor_id`, `host_user_id`, `visit_type_id`, `visit_status_id`, `purpose`, `expected_in`, `expected_out`, `checkin_time`, `checkout_time`, `created_by`, `created_at`, `updated_at`) VALUES
	(1, 1, 1, 1, 1, 1, 3, 'Deliver equipment', NULL, NULL, '2026-02-24 18:27:23', '2026-02-24 20:57:37', 1, '2026-02-20 07:45:46', '2026-02-24 20:57:37'),
	(2, 1, 1, 2, 1, 1, 3, 'Test Visit', '2026-02-24 20:04:00', '2026-02-24 21:02:00', '2026-02-24 20:57:40', '2026-02-24 21:09:16', 1, '2026-02-24 20:02:56', '2026-02-24 21:09:16'),
	(3, 1, 1, 2, 1, 2, 3, 'Test Meeting', '2026-02-24 21:45:00', '2026-02-24 22:30:00', '2026-02-24 21:45:12', '2026-02-24 21:45:47', 1, '2026-02-24 21:45:07', '2026-02-24 21:45:47'),
	(4, 1, 1, 3, 1, 2, 3, 'Test Meeting', '2026-02-25 20:00:00', '2026-02-25 20:45:00', '2026-02-25 19:48:19', '2026-02-25 19:48:33', 1, '2026-02-25 19:48:06', '2026-02-25 19:48:33'),
	(5, 1, 1, 3, 3, 1, 3, 'For a Business Meeting', '2026-02-27 18:30:00', '2026-02-27 18:30:00', '2026-02-27 17:28:23', '2026-03-01 00:31:23', 1, '2026-02-27 17:28:10', '2026-03-01 00:31:23'),
	(6, 1, 4, 1, 1, 2, 3, 'Test', '2026-03-01 00:31:00', '2026-03-01 03:31:00', '2026-03-01 00:32:07', '2026-03-13 09:10:15', 1, '2026-03-01 00:31:52', '2026-03-01 00:32:07');

-- Dumping data for table tupass_demo.visit_badges: ~6 rows (approximately)
INSERT INTO `visit_badges` (`id`, `tenant_id`, `visit_id`, `badge_code`, `is_active`, `printed_at`, `returned_at`) VALUES
	(1, 1, 1, 'BDG-6B8E88B6', 0, '2026-02-23 18:38:03', '2026-02-24 20:38:57'),
	(2, 1, 1, 'BDG-6A9D3057', 0, '2026-02-24 20:38:57', '2026-02-24 20:57:12'),
	(3, 1, 2, 'BDG-E67DFDA1', 1, '2026-02-24 21:03:33', '2026-02-24 21:03:39'),
	(4, 1, 2, 'BDG-FBDF51D0', 0, '2026-02-24 21:03:40', '2026-02-24 21:03:42'),
	(5, 1, 3, 'BDG-8C4F9892', 0, '2026-02-24 21:45:16', '2026-02-24 21:45:19'),
	(6, 1, 4, 'BDG-3D3964D7', 0, '2026-02-25 19:48:27', '2026-02-25 19:48:31'),
	(7, 1, 5, 'BDG-6BFF614E', 0, '2026-02-27 23:07:48', '2026-03-01 00:31:19'),
	(8, 1, 6, 'BDG-34C20133', 1, '2026-03-04 20:00:45', NULL);

-- Dumping data for table tupass_demo.visit_statuses: ~3 rows (approximately)
INSERT INTO `visit_statuses` (`id`, `tenant_id`, `name`, `code`) VALUES
	(1, 1, 'Scheduled', 'scheduled'),
	(2, 1, 'Checked In', 'checked_in'),
	(3, 1, 'Completed', 'checked_out');

-- Dumping data for table tupass_demo.visit_types: ~0 rows (approximately)
INSERT INTO `visit_types` (`id`, `tenant_id`, `name`) VALUES
	(1, 1, 'Business'),
	(2, 1, 'Meeting');

-- Dumping data for table tupass_demo.workflows: ~2 rows (approximately)
INSERT INTO `workflows` (`id`, `tenant_id`, `name`, `description`, `is_active`, `created_at`) VALUES
	(1, 1, 'Default Gatepass Workflow', 'Default Gatepass Workflow Configured', 1, '2026-02-20 07:45:47'),
	(2, 1, 'Multi-Step', 'Multi Step Approval', 1, '2026-02-21 15:33:34');

-- Dumping data for table tupass_demo.workflow_gatepass_type: ~2 rows (approximately)
INSERT INTO `workflow_gatepass_type` (`id`, `tenant_id`, `workflow_id`, `gatepass_type_id`, `created_at`) VALUES
	(1, 1, 1, 1, '2026-03-01 00:29:37'),
	(2, 1, 1, 2, '2026-03-01 00:29:42');

-- Dumping data for table tupass_demo.workflow_steps: ~3 rows (approximately)
INSERT INTO `workflow_steps` (`id`, `tenant_id`, `workflow_id`, `name`, `role_id`, `step_order`, `is_mandatory`, `department_id`) VALUES
	(1, 1, 1, 'HOD', 3, 1, 1, NULL),
	(2, 1, 1, 'Security Manager', 4, 2, 1, NULL),
	(3, 1, 1, 'General Manager', 5, 3, 0, NULL);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

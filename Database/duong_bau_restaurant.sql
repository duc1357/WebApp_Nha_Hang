-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 03, 2026 at 08:31 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `duong_bau_restaurant`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int NOT NULL,
  `table_id` int DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `guests` int NOT NULL,
  `status` enum('pending','awaiting_payment','confirmed','arrived','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `floor` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `table_number` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `has_preorder` tinyint(1) DEFAULT '0',
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `deposit_amount` decimal(10,2) DEFAULT '0.00',
  `payment_status` enum('pending','paid','partial') COLLATE utf8mb4_unicode_ci DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `table_id`, `name`, `phone`, `date`, `time`, `guests`, `status`, `created_at`, `floor`, `table_number`, `user_id`, `has_preorder`, `total_amount`, `deposit_amount`, `payment_status`) VALUES
(1, 1, 'Hoai Nam', '0799412960', '2026-01-17', '01:00:00', 2, 'confirmed', '2026-01-15 17:57:38', 'Sảnh Tulip', 0, NULL, 0, 0.00, 0.00, 'pending'),
(2, 13, 'Hoai Nam', '0799412960', '2026-01-18', '02:00:00', 2, 'confirmed', '2026-01-15 18:14:08', 'Sảnh Rose', 13, NULL, 0, 0.00, 0.00, 'pending'),
(3, 14, 'Hoai Nam', '0799412960', '2026-01-18', '13:16:00', 3, 'confirmed', '2026-01-15 18:14:32', 'Sảnh Rose', 14, NULL, 0, 0.00, 0.00, 'pending'),
(4, 14, 'Hoai Nam', '0941123354', '2026-01-25', '02:00:00', 2, 'confirmed', '2026-01-15 18:17:13', 'Sảnh Rose', 14, NULL, 0, 0.00, 0.00, 'pending'),
(5, 14, 'Hoai Nam', '0916952202', '2026-01-18', '02:00:00', 2, 'confirmed', '2026-01-15 18:20:49', 'Sảnh Rose', 14, NULL, 0, 0.00, 0.00, 'pending'),
(6, 15, 'Hoai Nam', '0916952202', '2026-01-18', '02:00:00', 2, 'confirmed', '2026-01-15 18:36:47', 'Sảnh Rose', 15, NULL, 0, 0.00, 0.00, 'pending'),
(7, 12, 'Lê Minh Đức', '0799412960', '2026-01-17', '03:00:00', 2, 'confirmed', '2026-01-15 19:51:19', 'Sảnh Rose', 12, 3, 0, 0.00, 0.00, 'pending'),
(8, 12, 'Hoai Nam', '0916952202', '2026-01-18', '03:00:00', 2, 'confirmed', '2026-01-15 19:52:07', 'Sảnh Rose', 12, NULL, 0, 0.00, 0.00, 'pending'),
(9, 12, 'Lê Minh Đức', '0799412960', '2026-01-16', '04:00:00', 2, 'confirmed', '2026-01-15 20:35:42', 'Sảnh Rose', 12, 3, 0, 0.00, 0.00, 'pending'),
(10, 13, 'Lê Minh Đức', '0799412960', '2026-01-16', '04:00:00', 2, 'confirmed', '2026-01-15 20:48:27', 'Sảnh Rose', 13, 3, 0, 0.00, 0.00, 'pending'),
(11, 11, 'MINH DUC', '0916952202', '2026-01-16', '11:00:00', 2, 'pending', '2026-01-16 03:48:54', 'Sảnh Rose', 11, NULL, 0, 0.00, 0.00, 'pending'),
(12, 13, 'minh duc', '0941123354', '2026-01-19', '01:00:00', 2, 'pending', '2026-01-18 17:42:38', 'Sảnh Rose', 13, 4, 0, 0.00, 0.00, 'pending'),
(13, 13, 'minh duc', '0941123354', '2026-01-20', '00:00:00', 2, 'pending', '2026-01-19 16:03:49', 'Sảnh Rose', 13, NULL, 0, 0.00, 0.00, 'pending'),
(14, 13, 'Lê Minh Đức', '0799412960', '2026-01-27', '15:00:00', 2, 'pending', '2026-01-27 07:26:45', 'Sảnh Rose', 13, 3, 0, 0.00, 0.00, 'pending'),
(15, 12, 'Lê Minh Đức', '0799412960', '2026-01-31', '23:00:00', 2, 'confirmed', '2026-01-31 15:20:35', NULL, NULL, NULL, 0, 0.00, 0.00, 'pending'),
(16, 13, 'Lê Minh Đức', '0799412960', '2026-01-31', '23:00:00', 2, 'confirmed', '2026-01-31 15:23:07', NULL, NULL, NULL, 0, 0.00, 0.00, 'pending'),
(17, 13, 'Lê Minh Đức', '0799412960', '2026-01-31', '23:00:00', 2, 'confirmed', '2026-01-31 15:23:20', NULL, NULL, NULL, 0, 0.00, 0.00, 'pending'),
(18, 14, 'Lê Minh Đức', '0799412960', '2026-01-31', '23:00:00', 2, 'pending', '2026-01-31 15:24:29', NULL, NULL, NULL, 0, 0.00, 0.00, 'pending'),
(19, 16, 'Lê Minh Đức', '0799412960', '2026-03-17', '00:00:00', 2, 'confirmed', '2026-03-16 15:23:40', NULL, NULL, NULL, 0, 0.00, 0.00, 'pending'),
(20, 15, 'Lê Minh Đức', '0799412960', '2026-03-17', '01:00:00', 2, 'confirmed', '2026-03-16 15:35:47', NULL, NULL, NULL, 0, 0.00, 0.00, 'pending'),
(21, 1, 'Test User', '0123456789', '2026-03-17', '19:00:00', 2, 'confirmed', '2026-03-16 15:42:09', 'rose', 1, 4, 0, 0.00, 0.00, 'pending'),
(22, 1, 'Test User', '0123456789', '2026-03-17', '19:00:00', 2, 'confirmed', '2026-03-16 15:42:14', 'rose', 1, 4, 0, 0.00, 0.00, 'pending'),
(23, 16, 'Lê Minh Đức', '0799412960', '2026-03-19', '23:00:00', 2, 'confirmed', '2026-03-16 15:51:49', NULL, NULL, NULL, 0, 0.00, 0.00, 'pending'),
(24, 16, 'Lê Minh Đức', '0799412960', '2026-03-19', '19:00:00', 2, 'confirmed', '2026-03-16 15:54:25', 'Sảnh Rose', 16, 1, 0, 0.00, 0.00, 'pending'),
(25, 16, 'Lê Minh Đức', '0799412960', '2026-04-05', '23:00:00', 2, 'confirmed', '2026-03-16 15:55:35', 'Sảnh Rose', 16, 1, 0, 0.00, 0.00, 'pending'),
(26, 16, 'Lê Minh Đức', '0799412960', '2026-04-03', '00:00:00', 2, 'cancelled', '2026-03-16 16:07:35', 'Sảnh Rose', 16, 3, 0, 0.00, 0.00, 'pending'),
(30, 12, 'Lê Minh Đức', '0799412960', '2026-03-26', '14:00:00', 2, 'confirmed', '2026-03-26 06:53:17', 'Sảnh Rose', 12, 3, 0, 0.00, 0.00, 'pending'),
(31, 13, 'Lê Minh Đức', '0799412960', '2026-03-26', '22:00:00', 2, 'awaiting_payment', '2026-03-26 08:39:39', 'Sảnh Rose', 13, 1, 1, 7000.00, 2100.00, 'pending'),
(32, 14, 'Lê Minh Đức', '0799412960', '2026-03-26', '22:00:00', 2, 'confirmed', '2026-03-26 08:42:25', 'Sảnh Rose', 14, 1, 1, 7000.00, 2100.00, 'partial');

-- --------------------------------------------------------

--
-- Table structure for table `booking_items`
--

CREATE TABLE `booking_items` (
  `id` int NOT NULL,
  `booking_id` int NOT NULL,
  `menu_item_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_items`
--

INSERT INTO `booking_items` (`id`, `booking_id`, `menu_item_id`, `quantity`, `unit_price`) VALUES
(1, 31, 26, 7, 1000.00),
(2, 32, 26, 7, 1000.00);

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` int NOT NULL,
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `name`, `description`, `price`, `image_url`, `is_active`, `created_at`, `deleted_at`) VALUES
(1, 'Phở Bò', NULL, 80000, 'photo/hinhpho.jfif', 1, '2026-01-15 17:38:58', NULL),
(2, 'Bún Chả', NULL, 70000, 'photo/buncha.jfif', 1, '2026-01-15 17:38:58', NULL),
(3, 'Cơm Tấm', NULL, 65000, 'photo/comtam.jfif', 1, '2026-01-15 17:38:58', NULL),
(4, 'Bánh Xèo', 'Bánh xèo giòn rụm với tôm và thịt', 60000, 'photo/banhxeo.jfif', 1, '2025-11-21 11:42:14', NULL),
(5, 'Gỏi Cuốn', 'Gỏi cuốn tôm thịt với nước chấm đậu phộng', 45000, 'photo/goicuon.jfif', 1, '2025-11-21 11:42:14', NULL),
(6, 'Bún Bò Huế', 'Bún bò Huế cay nồng đặc trưng', 85000, 'photo/bunbo.jfif', 1, '2025-11-21 11:42:14', NULL),
(7, 'Cao Lầu', 'Món cao lầu Hội An với sợi mì độc đáo', 70000, 'photo/caolau.jfif', 1, '2025-11-21 11:42:14', NULL),
(8, 'Mì Quảng', 'Mì Quảng với tôm, thịt và đậu phộng rang', 65000, 'photo/miquang.jfif', 1, '2025-11-21 11:42:14', NULL),
(9, 'Bánh Mì', 'Bánh mì pate với rau củ tươi ngon', 35000, 'photo/banhmi.jfif', 1, '2025-11-21 11:42:14', NULL),
(10, 'Hủ Tiếu Nam Vang', 'Hủ tiếu Nam Vang với tôm và thịt bằm', 75000, 'photo/hutieu.jfif', 1, '2025-11-21 11:42:14', NULL),
(11, 'Chả Cá Lã Vọng', 'Chả cá nướng ăn kèm bún và rau thơm', 120000, 'photo/chacalavong.jfif', 1, '2025-11-21 11:42:14', NULL),
(12, 'Canh Chua', 'Canh chua cá lóc với vị chua thanh mát', 90000, 'photo/canhchua.jfif', 1, '2025-11-21 11:42:14', NULL),
(13, 'Gà Nướng Mật Ong', 'Đùi gà nướng mật ong vàng ươm', 95000, 'photo/ganuongmatong.jfif', 1, '2025-11-21 11:42:14', NULL),
(14, 'Bò Kho', 'Bò kho đậm đà ăn kèm bánh mì hoặc bún', 85000, 'photo/bokho.jfif', 1, '2025-11-21 11:42:14', NULL),
(15, 'Lẩu Thái', 'Lẩu Thái chua cay với hải sản và rau', 150000, 'photo/lauthai.jfif', 1, '2025-11-21 11:42:14', NULL),
(16, 'Nem Chua Rán', 'Nem chua rán giòn tan, ăn kèm tương ớt', 50000, 'photo/nemchuaran.jfif', 1, '2025-11-21 11:42:14', NULL),
(17, 'Bánh Cuốn', 'Bánh cuốn nhân thịt bằm và mộc nhĩ', 55000, 'photo/banhcuon.jfif', 1, '2025-11-21 11:42:14', NULL),
(18, 'Cơm Gà Hội An', 'Cơm gà Hội An với gà xé và rau thơm', 70000, 'photo/comgahoan.jfif', 1, '2025-11-21 11:42:14', NULL),
(19, 'Bò Lúc Lắc', 'Bò lúc lắc xào ớt chuông, ăn kèm khoai tây', 110000, 'photo/boluclac.jfif', 1, '2025-11-21 11:42:14', NULL),
(20, 'Chè Ba Màu', 'Chè ba màu ngọt mát với đậu xanh và nước cốt dừa', 30000, 'photo/chebamau.jfif', 1, '2025-11-21 11:42:14', NULL),
(21, 'Nước Sâm Bổ Lượng', 'Thức uống bổ dưỡng với nhãn và hạt sen', 35000, 'photo/nuocsamboluong.jfif', 1, '2025-11-21 11:42:14', NULL),
(22, 'Cá Kho Tộ', 'Cá kho tộ đậm đà với nước mắm và tiêu', 100000, 'photo/cakhoto.jfif', 1, '2025-11-21 11:42:14', NULL),
(23, 'Gỏi Gà Xé Phay', 'Gỏi gà xé phay với hành tây và rau răm', 80000, 'photo/goigaxephay.jfif', 1, '2025-11-21 11:42:14', NULL),
(26, 'test', 'sss', 1000, 'photo/menu/menu_1773679261_542.jpg', 1, '2026-03-16 16:41:01', NULL),
(27, 'test', 'sss', 1000, 'photo/menu/menu_1773679261_553.jpg', 0, '2026-03-16 16:41:01', '2026-03-16 23:57:43'),
(28, 'test', 'sss', 1000, 'photo/menu/menu_1773679262_616.jpg', 0, '2026-03-16 16:41:02', '2026-03-16 23:57:40'),
(29, 'test', 'sss', 1000, 'photo/menu/menu_1773679262_156.jpg', 0, '2026-03-16 16:41:02', '2026-03-16 23:57:37'),
(30, 'test', 'sss', 1000, 'photo/menu/menu_1773679262_723.jpg', 0, '2026-03-16 16:41:02', '2026-03-16 23:57:34'),
(31, 'test', 'sss', 1000, 'photo/menu/menu_1773679263_425.jpg', 0, '2026-03-16 16:41:03', '2026-03-16 23:57:31'),
(32, 'test', 'sss', 1000, 'photo/menu/menu_1773679263_963.jpg', 0, '2026-03-16 16:41:03', '2026-03-16 23:57:28'),
(33, 'test', 'sss', 1000, 'photo/menu/menu_1773679263_167.jpg', 0, '2026-03-16 16:41:03', '2026-03-16 23:57:26'),
(34, 'test', 'sss', 1000, 'photo/menu/menu_1773679263_946.jpg', 0, '2026-03-16 16:41:03', '2026-03-16 23:57:22'),
(35, 'test', 'sss', 1000, 'photo/menu/menu_1773679263_213.jpg', 0, '2026-03-16 16:41:03', '2026-03-16 23:57:20'),
(36, 'test', 'sfsđá', 1000, 'photo/menu/menu_1773679289_535.jpg', 0, '2026-03-16 16:41:29', '2026-03-16 23:57:17'),
(37, 'test', 'sfsđá', 1000, 'photo/menu/menu_1773679290_678.jpg', 0, '2026-03-16 16:41:30', '2026-03-16 23:57:14'),
(38, 'test', 'sfsđá', 1000, 'photo/menu/menu_1773679290_761.jpg', 0, '2026-03-16 16:41:30', '2026-03-16 23:57:12'),
(39, 'test', 'sfsđá', 1000, 'photo/menu/menu_1773679290_497.jpg', 0, '2026-03-16 16:41:30', '2026-03-16 23:57:09'),
(40, 'test', 'sfsđá', 1000, 'photo/menu/menu_1773679290_468.jpg', 0, '2026-03-16 16:41:30', '2026-03-16 23:57:04'),
(41, 'test', 'sfsđá', 1000, 'photo/menu/menu_1773679290_675.jpg', 0, '2026-03-16 16:41:30', '2026-03-16 23:57:02'),
(42, 'test', 'sfsđá', 1000, 'photo/menu/menu_1773679291_474.jpg', 0, '2026-03-16 16:41:31', '2026-03-16 23:56:59');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `total_amount` int NOT NULL,
  `discount_amount` decimal(15,2) DEFAULT '0.00',
  `final_total` decimal(15,2) DEFAULT '0.00',
  `payment_method` enum('cash','bank_transfer') COLLATE utf8mb4_unicode_ci DEFAULT 'cash',
  `address` text COLLATE utf8mb4_unicode_ci,
  `table_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `voucher_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','paid','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total_amount`, `discount_amount`, `final_total`, `payment_method`, `address`, `table_id`, `note`, `voucher_code`, `status`, `created_at`) VALUES
(1, NULL, 80000, 0.00, 0.00, 'cash', NULL, NULL, NULL, NULL, 'paid', '2026-01-15 18:17:32'),
(2, 3, 70000, 0.00, 0.00, 'cash', NULL, NULL, NULL, NULL, 'paid', '2026-01-15 19:51:08'),
(3, 3, 70000, 0.00, 0.00, 'cash', NULL, NULL, NULL, NULL, 'paid', '2026-01-15 20:08:16'),
(4, NULL, 65000, 0.00, 0.00, 'cash', NULL, NULL, NULL, NULL, 'paid', '2026-01-16 03:43:03'),
(5, 3, 65000, 0.00, 0.00, 'cash', NULL, NULL, NULL, NULL, 'paid', '2026-01-16 03:43:09'),
(6, NULL, 260000, 0.00, 0.00, 'cash', NULL, NULL, NULL, NULL, 'paid', '2026-01-16 03:48:37'),
(7, 4, 260000, 0.00, 0.00, 'bank_transfer', NULL, NULL, NULL, NULL, 'pending', '2026-01-18 17:03:51'),
(8, 4, 65000, 0.00, 0.00, 'bank_transfer', NULL, NULL, NULL, NULL, 'pending', '2026-01-18 17:04:09'),
(9, 4, 60000, 0.00, 0.00, 'bank_transfer', NULL, NULL, NULL, NULL, 'pending', '2026-01-18 17:06:28'),
(10, 4, 70000, 0.00, 0.00, 'bank_transfer', NULL, NULL, NULL, NULL, 'pending', '2026-01-18 17:21:51'),
(11, 4, 70000, 0.00, 0.00, 'bank_transfer', NULL, NULL, NULL, NULL, 'pending', '2026-01-18 17:34:39'),
(12, 4, 30000, 0.00, 0.00, 'bank_transfer', NULL, NULL, NULL, NULL, 'pending', '2026-01-18 17:34:50'),
(13, 4, 30000, 0.00, 0.00, 'bank_transfer', NULL, NULL, NULL, NULL, 'paid', '2026-01-18 17:40:11'),
(14, 4, 65000, 0.00, 0.00, 'cash', NULL, NULL, NULL, NULL, 'paid', '2026-01-18 17:40:53'),
(15, 4, 65000, 0.00, 0.00, 'cash', NULL, NULL, NULL, NULL, 'paid', '2026-01-18 17:49:12'),
(16, 4, 65000, 0.00, 0.00, 'cash', NULL, NULL, NULL, NULL, 'paid', '2026-01-19 16:01:56'),
(17, 4, 30000, 0.00, 0.00, 'bank_transfer', NULL, NULL, NULL, NULL, 'paid', '2026-01-19 16:02:03'),
(18, 4, 30000, 0.00, 0.00, 'cash', NULL, NULL, NULL, NULL, 'paid', '2026-01-19 16:02:28'),
(19, 1, 30000, 0.00, 0.00, 'cash', NULL, NULL, NULL, NULL, 'paid', '2026-01-27 07:07:56'),
(20, 1, 30000, 0.00, 30000.00, 'cash', NULL, NULL, NULL, '', 'paid', '2026-01-27 07:12:22'),
(21, 3, 30000, 0.00, 30000.00, 'cash', NULL, NULL, NULL, '', 'paid', '2026-01-27 07:13:41'),
(22, 1, 30000, 27000.00, 3000.00, 'bank_transfer', NULL, NULL, NULL, 'SALE80', 'paid', '2026-01-27 07:33:37'),
(23, 1, 30000, 27000.00, 3000.00, 'bank_transfer', NULL, NULL, NULL, 'SALE80', 'paid', '2026-01-27 07:38:36'),
(24, 1, 30000, 0.00, 30000.00, 'cash', NULL, NULL, NULL, '', 'paid', '2026-01-27 10:58:34'),
(25, 1, 60000, 0.00, 60000.00, 'cash', NULL, NULL, NULL, '', 'paid', '2026-01-27 11:10:04'),
(26, 3, 30000, 0.00, 30000.00, 'cash', NULL, NULL, NULL, '', 'paid', '2026-01-27 11:11:40'),
(27, 3, 30000, 0.00, 30000.00, 'cash', NULL, NULL, NULL, '', 'paid', '2026-01-27 11:14:20'),
(28, 3, 65000, 0.00, 65000.00, 'cash', NULL, NULL, NULL, '', 'paid', '2026-01-27 11:14:41'),
(29, 1, 35000, 0.00, 35000.00, 'cash', '', 'R2', '', '', 'paid', '2026-01-31 14:20:05'),
(30, 1, 70000, 0.00, 70000.00, 'cash', '', 'R2', '', '', 'paid', '2026-01-31 15:25:18'),
(31, 3, 1000, 0.00, 1000.00, 'bank_transfer', '', 'r4', '', '', 'pending', '2026-03-16 16:58:07'),
(32, 3, 2000, 0.00, 2000.00, 'bank_transfer', '', 'r4', '', '', 'paid', '2026-03-16 16:58:37'),
(33, NULL, 0, 0.00, 0.00, 'cash', NULL, '12', '', NULL, 'paid', '2026-03-16 18:39:25'),
(34, NULL, 0, 0.00, 0.00, 'cash', NULL, '14', '', NULL, 'paid', '2026-03-16 18:39:29'),
(35, 3, 1000, 0.00, 1000.00, 'cash', '', 'R2', '', '', 'pending', '2026-03-26 06:54:03'),
(36, 3, 1000, 0.00, 1000.00, 'bank_transfer', '', 'R2', '', '', 'pending', '2026-03-26 06:54:19'),
(37, 3, 2000, 0.00, 2000.00, 'bank_transfer', '', 'R2', '', '', 'pending', '2026-03-26 06:54:47'),
(38, 3, 2000, 0.00, 2000.00, 'bank_transfer', '', 'R2', '', '', 'paid', '2026-03-26 06:58:57'),
(39, 3, 2000, 0.00, 2000.00, 'bank_transfer', '', 'R2', '', '', 'paid', '2026-03-26 07:06:55'),
(40, NULL, 0, 0.00, 0.00, 'cash', NULL, '20', '', NULL, 'paid', '2026-03-26 07:08:22'),
(41, NULL, 0, 0.00, 0.00, 'cash', NULL, '20', '', NULL, 'cancelled', '2026-03-26 07:09:32');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `menu_item_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_item_id`, `quantity`, `unit_price`) VALUES
(1, 1, 23, 1, 80000),
(2, 2, 18, 1, 70000),
(3, 3, 18, 1, 70000),
(4, 4, 3, 1, 65000),
(5, 5, 3, 1, 65000),
(6, 6, 3, 4, 65000),
(7, 7, 3, 4, 65000),
(8, 8, 3, 1, 65000),
(9, 9, 4, 1, 60000),
(10, 10, 7, 1, 70000),
(11, 11, 7, 1, 70000),
(12, 12, 20, 1, 30000),
(13, 13, 20, 1, 30000),
(14, 14, 3, 1, 65000),
(15, 15, 3, 1, 65000),
(16, 16, 3, 1, 65000),
(17, 17, 20, 1, 30000),
(18, 18, 20, 1, 30000),
(19, 19, 20, 1, 30000),
(20, 20, 20, 1, 30000),
(21, 21, 20, 1, 30000),
(22, 22, 20, 1, 30000),
(23, 23, 20, 1, 30000),
(24, 24, 20, 1, 30000),
(25, 25, 20, 2, 30000),
(26, 26, 20, 1, 30000),
(27, 27, 20, 1, 30000),
(28, 28, 20, 1, 30000),
(29, 28, 21, 1, 35000),
(30, 29, 21, 1, 35000),
(31, 30, 21, 2, 35000),
(32, 31, 26, 1, 1000),
(33, 32, 26, 2, 1000),
(34, 35, 26, 1, 1000),
(35, 36, 26, 1, 1000),
(36, 37, 26, 2, 1000),
(37, 38, 26, 2, 1000),
(38, 39, 26, 2, 1000);

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `user_id` int NOT NULL,
  `rating` int NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `tables`
--

CREATE TABLE `tables` (
  `id` int NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `floor` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capacity` int DEFAULT '4',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tables`
--

INSERT INTO `tables` (`id`, `name`, `floor`, `capacity`, `status`) VALUES
(1, 'Bàn T1', 'Sảnh Tulip', 4, 'available'),
(2, 'Bàn T2', 'Sảnh Tulip', 4, 'available'),
(3, 'Bàn T3', 'Sảnh Tulip', 4, 'available'),
(4, 'Bàn T4', 'Sảnh Tulip', 2, 'available'),
(5, 'Bàn T5', 'Sảnh Tulip', 6, 'available'),
(6, 'Bàn T6', 'Sảnh Tulip', 4, 'available'),
(7, 'Bàn T7', 'Sảnh Tulip', 4, 'available'),
(8, 'Bàn T8', 'Sảnh Tulip', 8, 'available'),
(9, 'Bàn T9', 'Sảnh Tulip', 4, 'available'),
(10, 'Bàn T10', 'Sảnh Tulip', 4, 'available'),
(11, 'Bàn R1', 'Sảnh Rose', 4, 'available'),
(12, 'Bàn R2', 'Sảnh Rose', 2, 'available'),
(13, 'Bàn R3', 'Sảnh Rose', 4, 'available'),
(14, 'Bàn R4', 'Sảnh Rose', 4, 'available'),
(15, 'Bàn R5', 'Sảnh Rose', 10, 'available'),
(16, 'Bàn R6', 'Sảnh Rose', 4, 'available'),
(17, 'Bàn R7', 'Sảnh Rose', 4, 'available'),
(18, 'Bàn R8', 'Sảnh Rose', 4, 'available'),
(19, 'Bàn R9', 'Sảnh Rose', 2, 'available'),
(20, 'Bàn R10', 'Sảnh Rose', 4, 'available');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('customer','admin') COLLATE utf8mb4_unicode_ci DEFAULT 'customer',
  `otp_code` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `phone`, `email`, `password`, `role`, `otp_code`, `otp_expiry`, `created_at`, `avatar`, `deleted_at`) VALUES
(1, 'Admin', '0123456789', 'admin@example.com', '$2y$10$a7J7SGjxm0thAUYV4t1ugOsnGRujUHjfpVRNBJJajHrzGy4c.IyeW', 'admin', NULL, NULL, '2025-11-21 12:38:21', NULL, NULL),
(3, 'Lê Minh Đức', '0799412960', 'leminhducphale@gmail.com', '$2y$10$jAOScwjRFcYXRNDnM50UcezJoO3AGHljmGhzIk2vNC1w35007ExXm', 'customer', NULL, NULL, '2026-01-15 19:25:01', NULL, NULL),
(4, 'minh duc', '0941123354', 'leminhducphale1@gmail.com', '$2y$10$4tyZoqW8uzr3jqAiYdBbte/sC2pEfmDtNdLWWyMEhQU8DIrXUyXqu', 'customer', NULL, NULL, '2026-01-16 03:52:24', 'photo/avatars/ua_4_1768839398.png', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `discount_type` enum('percent','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `discount_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `min_order_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `expire_date` datetime NOT NULL,
  `usage_limit` int NOT NULL DEFAULT '100',
  `used_count` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vouchers`
--

INSERT INTO `vouchers` (`id`, `code`, `description`, `discount_type`, `discount_value`, `min_order_value`, `expire_date`, `usage_limit`, `used_count`, `is_active`, `created_at`) VALUES
(1, 'WELCOME', 'Giß║úm 10% cho ─æãín h├áng ─æß║ºu ti├¬n', 'percent', 10.00, 50000.00, '2026-12-31 23:59:59', 1000, 0, 1, '2026-01-27 07:12:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_date_time` (`date`,`time`),
  ADD KEY `fk_bookings_table` (`table_id`);

--
-- Indexes for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_items_order` (`order_id`),
  ADD KEY `fk_items_menu` (`menu_item_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_order_review` (`order_id`),
  ADD KEY `fk_reviews_user` (`user_id`);

--
-- Indexes for table `tables`
--
ALTER TABLE `tables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_floor` (`floor`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `booking_items`
--
ALTER TABLE `booking_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tables`
--
ALTER TABLE `tables`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_bookings_table` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`),
  ADD CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD CONSTRAINT `booking_items_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_items_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_items_menu` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`),
  ADD CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

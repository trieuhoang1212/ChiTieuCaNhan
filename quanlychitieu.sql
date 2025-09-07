-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 06, 2025 at 11:23 PM
-- Server version: 5.7.43-log
-- PHP Version: 8.3.15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sql_nhom11_itimi`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `ten_dang_nhap` varchar(50) NOT NULL,
  `mat_khau` varchar(255) NOT NULL,
  `ho_ten` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `quyen` enum('super_admin','admin') DEFAULT 'admin',
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `ten_dang_nhap`, `mat_khau`, `ho_ten`, `email`, `quyen`, `ngay_tao`) VALUES
(1, 'admin', 'e10adc3949ba59abbe56e057f20f883e', 'Quản trị viên', 'admin@quanlychitieu.com', 'super_admin', '2025-09-05 06:45:52');

-- --------------------------------------------------------

--
-- Table structure for table `chitieu`
--

CREATE TABLE `chitieu` (
  `id` int(11) NOT NULL,
  `danh_muc` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `so_tien` int(11) NOT NULL,
  `ngay` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `danhba`
--

CREATE TABLE `danhba` (
  `id` int(11) NOT NULL,
  `ho_ten` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `so_tai_khoan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ngan_hang` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `danhba`
--

INSERT INTO `danhba` (`id`, `ho_ten`, `so_tai_khoan`, `ngan_hang`, `ngay_tao`) VALUES
(1, 'Nguyễn Hoàng Triều', '121201012005', 'MB Bank', '2025-08-29 08:42:50');

-- --------------------------------------------------------

--
-- Table structure for table `gioihan`
--

CREATE TABLE `gioihan` (
  `id` int(11) NOT NULL,
  `so_tien` int(11) NOT NULL,
  `thang_nam` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ngay` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gioihan`
--

INSERT INTO `gioihan` (`id`, `so_tien`, `thang_nam`, `ngay`) VALUES
(3, 0, '2025-08', '2025-08-29 08:50:31');

-- --------------------------------------------------------

--
-- Table structure for table `lichtragop`
--

CREATE TABLE `lichtragop` (
  `id` int(11) NOT NULL,
  `vayno_id` int(11) NOT NULL,
  `ky_thu` int(11) NOT NULL,
  `ngay_den_han` date NOT NULL,
  `so_tien_tra` bigint(20) NOT NULL,
  `da_tra` tinyint(1) DEFAULT '0',
  `ngay_tra_thuc_te` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `naprut`
--

CREATE TABLE `naprut` (
  `id` int(11) NOT NULL,
  `ngay` datetime NOT NULL,
  `loai` enum('Nạp','Rút') COLLATE utf8mb4_unicode_ci NOT NULL,
  `mo_ta` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `so_tien` int(11) NOT NULL,
  `so_du_sau` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `naprut`
--

INSERT INTO `naprut` (`id`, `ngay`, `loai`, `mo_ta`, `so_tien`, `so_du_sau`) VALUES
(1, '2025-08-28 16:15:36', 'Nạp', 'Nạp tiền ví', 1000000, 1000000),
(5, '2025-08-28 16:42:31', 'Nạp', 'Nạp tiền ví', 1000000, 2000000),
(6, '2025-08-28 16:42:33', 'Nạp', 'Nạp tiền ví', 1000000, 3000000),
(7, '2025-08-28 16:42:34', 'Nạp', 'Nạp tiền ví', 1000000, 4000000),
(8, '2025-08-28 16:42:47', 'Nạp', 'Nạp tiền ví', 1000000, 5000000),
(9, '2025-08-28 16:42:48', 'Nạp', 'Nạp tiền ví', 1000000, 6000000),
(10, '2025-08-28 16:43:02', 'Rút', 'Nạp tiền ví', 1000000, 5000000),
(11, '2025-08-28 16:45:32', 'Nạp', 'đi học', 1000, 5001000),
(12, '2025-08-28 16:45:55', 'Rút', 'trả tiền xe', 1000, 5000000),
(13, '2025-08-28 17:54:56', 'Nạp', 'Tiền Lương tháng này', 1000000, 6000000),
(14, '2025-08-29 03:51:37', '', 'Đặt giới hạn tháng', 222, 5999778),
(15, '2025-08-29 03:52:07', '', 'Bỏ giới hạn tháng', 222, 6000000),
(16, '2025-08-29 03:53:57', '', 'Đặt giới hạn tháng', 1000000, 5000000),
(17, '2025-08-29 03:54:01', '', 'Bỏ giới hạn tháng', 1000000, 6000000),
(18, '2025-08-29 03:54:08', '', 'Đặt giới hạn tháng', 10000, 5990000),
(19, '2025-08-29 03:54:28', '', 'Bỏ giới hạn tháng', 10000, 6000000),
(20, '2025-08-29 03:54:47', '', 'Đặt giới hạn tháng', 100000, 5900000),
(21, '2025-08-29 04:00:43', '', 'Bỏ giới hạn tháng', 100000, 6000000),
(22, '2025-08-29 04:03:14', '', 'Đặt giới hạn tháng', 10000, 5990000),
(23, '2025-08-29 04:04:16', '', 'Sức khỏe', 1, 5989999),
(24, '2025-08-29 04:04:41', '', 'Bỏ giới hạn tháng', 9999, 5999998),
(25, '2025-08-29 04:05:07', 'Nạp', '', 2, 6000000),
(26, '2025-08-29 04:05:28', '', 'Đặt giới hạn tháng', 3666666, 2333334),
(27, '2025-08-29 04:05:47', '', 'Hóa đơn điện nước', 300000, 2033334),
(28, '2025-08-29 04:06:29', '', 'Bỏ giới hạn tháng', 3366666, 5400000),
(29, '2025-08-29 04:06:59', '', 'Đặt giới hạn tháng', 400000, 5000000),
(30, '2025-08-29 04:07:11', '', 'Du lịch', 2000, 4998000),
(31, '2025-08-29 04:07:20', '', 'Bỏ giới hạn tháng', 398000, 5396000),
(32, '2025-08-29 04:07:40', 'Rút', '', 96, 5395904),
(33, '2025-08-29 04:08:14', 'Rút', 'Tiền Lương tháng này', 95904, 5300000),
(34, '2025-08-29 04:08:30', 'Nạp', 'Tiền Lương tháng này', 700000, 6000000),
(35, '2025-08-29 04:08:41', 'Nạp', 'Tiền Lương tháng này', 1000000000, 1006000000),
(36, '2025-08-29 04:09:06', 'Rút', 'Tiền Lương tháng này', 1006000000, 0),
(37, '2025-08-29 04:09:16', 'Nạp', 'Test', 2147483647, 2147483647),
(38, '2025-08-29 04:09:25', 'Nạp', 'Test', 2147483647, 2147483647),
(39, '2025-08-29 04:11:31', 'Rút', 'test', 999999999, 1147483648),
(40, '2025-08-29 04:20:41', '', 'Bỏ giới hạn tháng', 100000, 1147583648),
(41, '2025-08-29 04:20:49', '', 'Đặt giới hạn tháng', 10000000, 1137583648),
(42, '2025-08-29 04:20:54', '', 'Bỏ giới hạn tháng', 10000000, 1147583648),
(43, '2025-08-29 04:21:08', '', 'Đặt giới hạn tháng', 2000000, 1145583648),
(44, '2025-08-29 04:24:03', '', 'Bỏ giới hạn tháng', 1990000, 1147573648),
(45, '2025-08-29 04:24:09', '', 'Đặt giới hạn tháng', 200000, 1147373648),
(46, '2025-08-29 04:26:23', '', 'Bỏ giới hạn tháng', 512000, 1147885648),
(47, '2025-08-29 04:30:47', 'Rút', '', 1147885648, 0),
(48, '2025-08-29 04:31:04', 'Nạp', 'Tiền Lương tháng này', 1000000, 1000000),
(49, '2025-08-29 04:31:07', 'Nạp', 'Tiền Lương tháng này', 1000000, 2000000),
(50, '2025-08-29 04:31:09', 'Nạp', 'Tiền Lương tháng này', 1000000, 3000000),
(51, '2025-08-29 04:31:11', 'Nạp', 'Tiền Lương tháng này', 1000000, 4000000),
(52, '2025-08-29 04:31:12', 'Nạp', 'Tiền Lương tháng này', 1000000, 5000000),
(53, '2025-08-29 04:31:14', 'Nạp', 'Tiền Lương tháng này', 1000000, 6000000),
(54, '2025-08-29 04:31:16', 'Nạp', 'Tiền Lương tháng này', 1000000, 7000000),
(55, '2025-08-29 04:31:18', 'Nạp', 'Tiền Lương tháng này', 1000000, 8000000),
(56, '2025-08-29 04:31:19', 'Nạp', 'Tiền Lương tháng này', 1000000, 9000000),
(57, '2025-08-29 04:31:21', 'Nạp', 'Tiền Lương tháng này', 1000000, 10000000),
(58, '2025-08-29 04:31:41', '', 'Đặt giới hạn tháng', 5000000, 5000000),
(59, '2025-08-29 04:32:41', '', 'Bỏ giới hạn tháng', 5000000, 10000000),
(60, '2025-08-29 07:53:28', '', 'Đặt giới hạn tháng', 10000, 9990000),
(61, '2025-08-29 07:53:39', '', 'Bỏ giới hạn tháng', 10000, 10000000),
(62, '2025-08-29 08:53:01', 'Rút', 'Đóng góp tích luỹ: Mua Xe', 10000, 9990000),
(63, '2025-08-29 08:55:11', 'Rút', 'Đóng góp tích luỹ: Mua Xe', 100000, 9890000),
(64, '2025-08-29 08:55:15', 'Nạp', 'Hoàn trả tích luỹ khi xoá kế hoạch #0: Mua Xe', 100000, 9990000),
(65, '2025-08-29 08:55:51', 'Rút', 'Đóng góp tích luỹ: Mua Xe', 1000, 9989000),
(66, '2025-08-29 10:38:51', 'Nạp', 'Tiền Cơm - Nguyễn Hoàng Triều', 10000, 9999000),
(67, '2025-08-29 10:42:24', 'Rút', 'Tiền Cơm - Nguyễn Hoàng Triều', 10000, 9989000),
(68, '2025-08-29 10:42:50', 'Rút', 'Tiền Cơm - Nguyễn Hoàng Triều', 1000000, 8989000),
(69, '2025-08-29 10:45:21', 'Nạp', 'Tiền Cơm - Nguyễn Hoàng Triều', 10000, 8999000),
(70, '2025-08-29 11:04:37', 'Rút', 'Tiền Cơm - Nguyễn Hoàng Triều', 10000, 8989000),
(71, '2025-08-29 12:31:06', 'Nạp', 'Tiền Lương tháng này', 1000000, 9989000),
(72, '2025-08-29 12:55:10', 'Nạp', 'Cộng tiền vay', 100000, 10089000),
(73, '2025-08-29 12:56:32', 'Nạp', 'Cộng tiền vay', 10000, 10099000),
(74, '2025-08-29 13:00:34', 'Nạp', 'Cộng tiền vay', 10000, 10109000),
(75, '2025-08-29 13:00:42', 'Nạp', 'Cộng tiền vay', 12121212, 22230212),
(76, '2025-08-29 13:15:28', 'Rút', 'hello', 22230212, 0),
(77, '2025-08-29 13:15:42', 'Nạp', 'hello', 2000000, 2000000),
(78, '2025-08-29 13:15:50', 'Nạp', 'hello', 10000000, 12000000),
(79, '2025-08-29 13:37:26', 'Rút', 'Gửi tiết kiệm: Mua Nhà', 10000000, 2000000),
(80, '2025-08-29 13:45:17', 'Nạp', 'Tất toán sổ tiết kiệm: Mua Nhà', 10000000, 12000000),
(81, '2025-08-29 13:45:26', 'Rút', 'Gửi tiết kiệm: để dành tiền mua nhà', 12000000, 0),
(82, '2025-08-29 13:45:33', 'Nạp', 'Tất toán sổ tiết kiệm: để dành tiền mua nhà', 12000000, 12000000),
(83, '2025-08-29 13:45:51', 'Rút', 'Gửi tiết kiệm: để dành tiền mua nhà', 12000000, 0),
(84, '2025-08-29 13:51:31', 'Nạp', 'Tất toán sổ tiết kiệm: để dành tiền mua nhà', 12000000, 12000000),
(85, '2025-08-29 13:57:49', 'Rút', 'Gửi tiết kiệm: Mua Nhà', 12000, 11988000),
(86, '2025-08-29 13:57:54', 'Nạp', 'Tất toán sổ tiết kiệm: Mua Nhà', 12000, 12000000),
(87, '2025-08-29 14:06:45', 'Rút', 'Gửi tiết kiệm: Mua Nhà', 12000000, 0),
(88, '2025-09-06 23:03:36', 'Nạp', 'Tiền Lương tháng này', 1000, 1000),
(89, '2025-09-06 23:20:54', 'Nạp', 'Tiền Lương tháng này', 10000000, 10001000);

-- --------------------------------------------------------

--
-- Table structure for table `nguoi_dung`
--

CREATE TABLE `nguoi_dung` (
  `id` int(11) NOT NULL,
  `ten_dang_nhap` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mat_khau` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ho_ten` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sodu`
--

CREATE TABLE `sodu` (
  `id` int(11) NOT NULL,
  `so_tien` bigint(20) NOT NULL,
  `ngay` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `sodu`
--

INSERT INTO `sodu` (`id`, `so_tien`, `ngay`) VALUES
(1, 1000000, '2025-08-28 21:15:46'),
(4, 1010000, '2025-08-29 08:46:17'),
(5, 10000, '2025-08-29 08:47:02'),
(6, -90000, '2025-08-29 08:47:31'),
(7, -2312222, '2025-08-29 08:47:55'),
(8, -2412222, '2025-08-29 08:50:31'),
(9, -2422222, '2025-08-29 09:11:17'),
(10, -2522222, '2025-08-29 09:11:39'),
(11, -2512222, '2025-08-29 13:53:05');

-- --------------------------------------------------------

--
-- Table structure for table `sotietkiem`
--

CREATE TABLE `sotietkiem` (
  `ma_so` int(11) NOT NULL,
  `ten_so` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `so_tien` bigint(20) NOT NULL,
  `ky_han` int(11) NOT NULL DEFAULT '0',
  `lai_suat` decimal(5,2) NOT NULL,
  `ngay_gui` date NOT NULL,
  `ngay_dao_han` date DEFAULT NULL,
  `trang_thai` enum('dang_gui','tat_toan') COLLATE utf8mb4_unicode_ci DEFAULT 'dang_gui',
  `ngay_tao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sotietkiem`
--

INSERT INTO `sotietkiem` (`ma_so`, `ten_so`, `so_tien`, `ky_han`, `lai_suat`, `ngay_gui`, `ngay_dao_han`, `trang_thai`, `ngay_tao`) VALUES
(5, 'Mua Nhà', 12000000, 3, 4.00, '2025-08-29', '2025-11-29', 'dang_gui', '2025-08-29 12:06:45');

-- --------------------------------------------------------

--
-- Table structure for table `thuchi`
--

CREATE TABLE `thuchi` (
  `id` int(11) NOT NULL,
  `loai` enum('Thu','Chi') COLLATE utf8mb4_unicode_ci NOT NULL,
  `so_tien` bigint(20) NOT NULL,
  `ngay` date NOT NULL,
  `mo_ta` text COLLATE utf8mb4_unicode_ci,
  `nguoi_giao_dich_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `thuchi`
--

INSERT INTO `thuchi` (`id`, `loai`, `so_tien`, `ngay`, `mo_ta`, `nguoi_giao_dich_id`) VALUES
(5, 'Chi', 10000, '2025-08-29', 'Tiền Cơm', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tichluy`
--

CREATE TABLE `tichluy` (
  `id` int(11) NOT NULL,
  `ten_muc_tieu` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `so_tien_muc_tieu` bigint(20) NOT NULL,
  `ngay_bat_dau` date NOT NULL,
  `ngay_ket_thuc` date NOT NULL,
  `so_ngay` int(11) NOT NULL,
  `so_tien_trung_binh_ngay` bigint(20) NOT NULL,
  `so_tien_da_tich_luy` bigint(20) NOT NULL DEFAULT '0',
  `trang_thai` enum('dang_tich_luy','hoan_thanh','huy') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dang_tich_luy',
  `ngay_tao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ngay_cap_nhat` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tichluy`
--

INSERT INTO `tichluy` (`id`, `ten_muc_tieu`, `so_tien_muc_tieu`, `ngay_bat_dau`, `ngay_ket_thuc`, `so_ngay`, `so_tien_trung_binh_ngay`, `so_tien_da_tich_luy`, `trang_thai`, `ngay_tao`, `ngay_cap_nhat`) VALUES
(0, 'Mua Xe', 100000000000000000, '2025-08-29', '2028-10-29', 1157, 86430423509076, 1000, 'dang_tich_luy', '2025-08-29 06:55:41', '2025-08-29 06:55:51');

-- --------------------------------------------------------

--
-- Table structure for table `vayno`
--

CREATE TABLE `vayno` (
  `id` int(11) NOT NULL,
  `ten_khoan_vay` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `so_tien` bigint(20) NOT NULL,
  `so_thang` int(11) NOT NULL,
  `tien_tra_moi_thang` bigint(20) NOT NULL,
  `ngay_bat_dau` date NOT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ten_dang_nhap` (`ten_dang_nhap`);

--
-- Indexes for table `chitieu`
--
ALTER TABLE `chitieu`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `danhba`
--
ALTER TABLE `danhba`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gioihan`
--
ALTER TABLE `gioihan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lichtragop`
--
ALTER TABLE `lichtragop`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vayno_id` (`vayno_id`);

--
-- Indexes for table `naprut`
--
ALTER TABLE `naprut`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ten_dang_nhap` (`ten_dang_nhap`);

--
-- Indexes for table `sodu`
--
ALTER TABLE `sodu`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sotietkiem`
--
ALTER TABLE `sotietkiem`
  ADD PRIMARY KEY (`ma_so`);

--
-- Indexes for table `thuchi`
--
ALTER TABLE `thuchi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `nguoi_giao_dich_id` (`nguoi_giao_dich_id`);

--
-- Indexes for table `vayno`
--
ALTER TABLE `vayno`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `chitieu`
--
ALTER TABLE `chitieu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `danhba`
--
ALTER TABLE `danhba`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `gioihan`
--
ALTER TABLE `gioihan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lichtragop`
--
ALTER TABLE `lichtragop`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `naprut`
--
ALTER TABLE `naprut`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sodu`
--
ALTER TABLE `sodu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sotietkiem`
--
ALTER TABLE `sotietkiem`
  MODIFY `ma_so` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `thuchi`
--
ALTER TABLE `thuchi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vayno`
--
ALTER TABLE `vayno`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `lichtragop`
--
ALTER TABLE `lichtragop`
  ADD CONSTRAINT `lichtragop_ibfk_1` FOREIGN KEY (`vayno_id`) REFERENCES `vayno` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `thuchi`
--
ALTER TABLE `thuchi`
  ADD CONSTRAINT `thuchi_ibfk_1` FOREIGN KEY (`nguoi_giao_dich_id`) REFERENCES `danhba` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

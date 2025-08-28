-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th8 28, 2025 lúc 05:04 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `quanlychitieu`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chitieu`
--

CREATE TABLE `chitieu` (
  `id` int(11) NOT NULL,
  `ngay` datetime NOT NULL,
  `danh_muc` enum('','') NOT NULL,
  `so_tien` int(11) NOT NULL,
  `so_tien_chi` int(11) NOT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `gioi_han`
--

CREATE TABLE `gioi_han` (
  `id` int(11) NOT NULL,
  `nam` year(4) NOT NULL,
  `thang` tinyint(4) NOT NULL,
  `so_tien_gioi_han` bigint(20) NOT NULL,
  `gioi_han_con_lai` bigint(20) NOT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `naprut`
--

CREATE TABLE `naprut` (
  `id` int(11) NOT NULL,
  `ngay` datetime NOT NULL,
  `loai` enum('Nạp','Rút') NOT NULL,
  `mo_ta` varchar(255) DEFAULT NULL,
  `so_tien` int(11) NOT NULL,
  `so_du_sau` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `naprut`
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
(12, '2025-08-28 16:45:55', 'Rút', 'trả tiền xe', 1000, 5000000);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nguoi_dung`
--

CREATE TABLE `nguoi_dung` (
  `id` int(11) NOT NULL,
  `ten_dang_nhap` varchar(50) NOT NULL,
  `mat_khau` varchar(255) NOT NULL,
  `ho_ten` varchar(100) DEFAULT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `sodu`
--

CREATE TABLE `sodu` (
  `id` int(11) NOT NULL,
  `so_tien` bigint(20) NOT NULL,
  `ngay` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `sodu`
--

INSERT INTO `sodu` (`id`, `so_tien`, `ngay`) VALUES
(1, 1000000, '2025-08-28 21:15:46');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `chitieu`
--
ALTER TABLE `chitieu`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `gioi_han`
--
ALTER TABLE `gioi_han`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_nguoi_dung` (`id_nguoi_dung`,`nam`,`thang`);

--
-- Chỉ mục cho bảng `naprut`
--
ALTER TABLE `naprut`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ten_dang_nhap` (`ten_dang_nhap`);

--
-- Chỉ mục cho bảng `sodu`
--
ALTER TABLE `sodu`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `chitieu`
--
ALTER TABLE `chitieu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `gioi_han`
--
ALTER TABLE `gioi_han`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `naprut`
--
ALTER TABLE `naprut`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT cho bảng `nguoi_dung`
--
ALTER TABLE `nguoi_dung`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `sodu`
--
ALTER TABLE `sodu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `gioi_han`
--
ALTER TABLE `gioi_han`
  ADD CONSTRAINT `gioi_han_ibfk_1` FOREIGN KEY (`id_nguoi_dung`) REFERENCES `nguoi_dung` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

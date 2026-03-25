-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 25 مارس 2026 الساعة 11:14
-- إصدار الخادم: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tailoring_db`
--

-- --------------------------------------------------------

--
-- بنية الجدول `customer`
--

CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `customer`
--

INSERT INTO `customer` (`customer_id`, `user_id`) VALUES
(1, 1),
(2, 3),
(3, 5),
(4, 7),
(5, 9),
(6, 10),
(7, 11),
(8, 12),
(9, 13),
(10, 15),
(11, 17),
(12, 18),
(13, 20),
(14, 22),
(15, 23);

-- --------------------------------------------------------

--
-- بنية الجدول `measurement`
--

CREATE TABLE `measurement` (
  `measurement_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `chest` decimal(5,2) DEFAULT NULL,
  `waist` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `measurement`
--

INSERT INTO `measurement` (`measurement_id`, `customer_id`, `chest`, `waist`, `height`) VALUES
(1, 1, 95.50, 80.00, 175.00),
(2, 2, 90.00, 70.00, 165.00);

-- --------------------------------------------------------

--
-- بنية الجدول `measurements`
--

CREATE TABLE `measurements` (
  `measurement_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `measurement_type` varchar(50) DEFAULT NULL,
  `size` varchar(10) DEFAULT NULL,
  `chest` decimal(5,2) DEFAULT NULL,
  `waist` decimal(5,2) DEFAULT NULL,
  `hips` decimal(5,2) DEFAULT NULL,
  `length` decimal(5,2) DEFAULT NULL,
  `shoulder` decimal(5,2) DEFAULT NULL,
  `sleeve` decimal(5,2) DEFAULT NULL,
  `neck` decimal(5,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `measurements`
--

INSERT INTO `measurements` (`measurement_id`, `order_id`, `measurement_type`, `size`, `chest`, `waist`, `hips`, `length`, `shoulder`, `sleeve`, `neck`, `notes`, `created_at`) VALUES
(1, 10, 'أخرى', 'XS', NULL, NULL, NULL, NULL, NULL, NULL, 22.00, '2222222222', '2026-03-18 20:40:44'),
(2, 11, 'قندورة', 'XS', 22.00, NULL, NULL, 22.00, NULL, NULL, NULL, '..........00000000', '2026-03-18 20:42:50');

-- --------------------------------------------------------

--
-- بنية الجدول `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 6, 'order', '📦 طلب تصميم جديد', 'لديك طلب تصميم جديد من Test User', 'tailor/view_requests.php', 0, '2026-03-19 17:00:49'),
(2, 7, 'price', '💰 تم تحديد السعر', 'قام الخياط بتحديد سعر 20 د.ج لطلبك #12', 'customer/my_orders.php', 1, '2026-03-19 17:01:12'),
(3, 6, 'payment', '💰 تم الدفع', 'قام الزبون بدفع الطلب #12', 'tailor/my_orders.php', 0, '2026-03-19 17:01:34'),
(4, 7, 'status', '🔄 تحديث حالة الطلب', 'تم تحديث حالة طلبك #12 إلى قيد التنفيذ', 'customer/my_orders.php', 1, '2026-03-19 17:02:38'),
(5, 7, 'status', '🔄 تحديث حالة الطلب', 'تم تحديث حالة طلبك #12 إلى مكتمل', 'customer/my_orders.php', 1, '2026-03-19 17:02:48');

-- --------------------------------------------------------

--
-- بنية الجدول `order`
--

CREATE TABLE `order` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `tailor_id` int(11) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivery_date` date DEFAULT NULL,
  `design_image` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `tailor_price` decimal(10,2) DEFAULT NULL,
  `price_status` enum('pending','quoted','accepted') DEFAULT 'pending',
  `status` varchar(50) DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `order`
--

INSERT INTO `order` (`order_id`, `customer_id`, `tailor_id`, `order_date`, `delivery_date`, `design_image`, `description`, `total_price`, `tailor_price`, `price_status`, `status`) VALUES
(1, 1, 1, '2026-03-17 07:02:07', '2026-03-24', 'design1.jpg', 'Wedding dress with embroidery', 1500.00, NULL, 'pending', 'pending'),
(2, 2, 2, '2026-03-17 07:02:07', '2026-03-22', 'design2.jpg', 'Formal suit', 800.00, NULL, 'pending', 'in_progress'),
(3, 4, 3, '2026-03-17 07:37:39', '2026-05-02', '', 'رلتنمنكط', 500.00, NULL, 'pending', 'completed'),
(4, 4, 3, '2026-03-17 07:38:12', '2026-05-02', '', 'رلتنمنكط', 500.00, 2000.00, 'quoted', 'completed'),
(5, 4, 3, '2026-03-17 08:01:45', '2026-03-24', 'uploads/69b90a696ecd8_1773734505.jpg', 'بلاتنم', 500.00, 200000.00, 'quoted', 'completed'),
(6, 4, 3, '2026-03-17 14:20:09', '2027-05-02', '', 'لباتنم', 20000.00, NULL, 'pending', 'pending'),
(7, 4, 2, '2026-03-17 19:26:44', '2027-05-02', '', 'لاتانم', 2000.00, NULL, 'pending', 'pending'),
(8, 4, 3, '2026-03-18 19:13:36', '2027-05-02', 'uploads/69baf96018f03_1773861216.jpg', 'kiyujtyrgtefrzerztyrdfuy', 99999999.99, 3000.00, 'quoted', 'completed'),
(10, 4, 3, '2026-03-18 20:40:44', '2028-08-03', '', 'لباتنمك', 20000.00, NULL, 'pending', 'pending'),
(11, 4, 3, '2026-03-18 20:42:50', '2028-05-02', '', 'يبلاتمهنمكططمنتنعاغلفق', 200000.00, 200222.00, 'quoted', 'completed'),
(12, 4, 3, '2026-03-19 17:00:49', '2027-03-02', '', 'بيلغتهعخنعمتبفقايبلسيغهتخنهتعنغ', 2000000.00, 20.00, 'quoted', 'completed');

-- --------------------------------------------------------

--
-- بنية الجدول `payment`
--

CREATE TABLE `payment` (
  `payment_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'unpaid',
  `payment_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `payment`
--

INSERT INTO `payment` (`payment_id`, `order_id`, `payment_method`, `amount`, `status`, `payment_date`) VALUES
(1, 1, 'Credit Card', 500.00, 'unpaid', NULL),
(2, 2, 'Cash', 800.00, 'paid', '2026-03-17 07:02:07'),
(3, 6, 'تحويل بنكي', 20000.00, 'paid', '2026-03-17 14:20:20'),
(4, 5, 'بطاقة بنكية', 200000.00, 'paid', '2026-03-17 19:40:20'),
(5, 8, 'بطاقة بنكية', 3000.00, 'paid', '2026-03-18 19:16:36'),
(6, 11, 'بطاقة بنكية', 200222.00, 'paid', '2026-03-18 20:47:45'),
(7, 4, 'تحويل بنكي', 2000.00, 'paid', '2026-03-18 21:02:53'),
(8, 12, 'تحويل بنكي', 20.00, 'paid', '2026-03-19 17:01:33');

-- --------------------------------------------------------

--
-- بنية الجدول `review`
--

CREATE TABLE `review` (
  `review_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `tailor_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `review_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `review`
--

INSERT INTO `review` (`review_id`, `order_id`, `customer_id`, `tailor_id`, `rating`, `comment`, `review_date`) VALUES
(1, 2, 2, 2, 4, 'Excellent work, delivered on time!', '2026-03-17 07:02:07'),
(2, 3, 4, 3, 5, 'لباتنمك', '2026-03-17 08:01:57'),
(3, 8, 4, 3, 4, '22222222222222', '2026-03-18 19:19:18'),
(4, 11, 4, 3, 1, '', '2026-03-18 20:49:02');

-- --------------------------------------------------------

--
-- بنية الجدول `tailor`
--

CREATE TABLE `tailor` (
  `tailor_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `category` varchar(50) DEFAULT 'عام',
  `registration_year` int(11) DEFAULT NULL,
  `rating` float DEFAULT 0,
  `experience_points` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `tailor`
--

INSERT INTO `tailor` (`tailor_id`, `user_id`, `specialization`, `category`, `registration_year`, `rating`, `experience_points`) VALUES
(1, 2, 'Wedding Dresses', 'عام', 5, 4.5, 0),
(2, 4, 'Men Suits', 'عام', 3, 4, 0),
(3, 6, 'اطفال', 'عام', 2, 3.33333, 60),
(4, 8, 'خياطة مدرسية', 'عام', 2026, 0, 0),
(5, 14, 'ستائر و مفروشات', 'عام', 2026, 0, 0),
(6, 16, 'ستائر و مفروشات', 'عام', 2026, 0, 0),
(7, 19, 'تطريز يدوي', 'عام', 2026, 0, 0),
(8, 21, 'ستائر و مفروشات', 'عام', 2026, 0, 0),
(9, 24, 'بلوزات', 'عام', 2026, 0, 0);

-- --------------------------------------------------------

--
-- بنية الجدول `tailor_portfolio`
--

CREATE TABLE `tailor_portfolio` (
  `portfolio_id` int(11) NOT NULL,
  `tailor_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `tailor_portfolio`
--

INSERT INTO `tailor_portfolio` (`portfolio_id`, `tailor_id`, `image_path`, `description`, `price`, `created_at`) VALUES
(3, 3, 'uploads/portfolio/portfolio_3_1773752733.jpg', 'يبغلعلنتعحغهفعققغفهعغخعحهخعهغعفعفغخعه', 99999999.99, '2026-03-17 13:05:33'),
(4, 3, 'uploads/portfolio/portfolio_3_1773861604.jpg', '0000000000000000000000000', 99999999.99, '2026-03-18 19:20:04');

-- --------------------------------------------------------

--
-- بنية الجدول `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('customer','tailor') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `user`
--

INSERT INTO `user` (`user_id`, `full_name`, `email`, `password`, `phone`, `address`, `role`, `created_at`) VALUES
(1, 'Ahmed Mohammed', 'ahmed@example.com', 'e10adc3949ba59abbe56e057f20f883e', '0501234567', 'Riyadh', 'customer', '2026-03-17 07:02:06'),
(2, 'Fatima Ali', 'fatima@example.com', 'e10adc3949ba59abbe56e057f20f883e', '0557654321', 'Jeddah', 'tailor', '2026-03-17 07:02:06'),
(3, 'Mohamed Abdullah', 'mohamed@example.com', 'e10adc3949ba59abbe56e057f20f883e', '0569876543', 'Dammam', 'customer', '2026-03-17 07:02:06'),
(4, 'Sara Khalid', 'sara@example.com', 'e10adc3949ba59abbe56e057f20f883e', '0543216789', 'Makkah', 'tailor', '2026-03-17 07:02:06'),
(5, 'ةىلا', 'karima.benaldjia.dz@gmail.com', '$2y$10$3jJzX6yDuXeODLNttDYx3On6V0fpRRkDIoQGjJaNBUb6xXeepYr8e', '0671443827', 'تنممننت', 'customer', '2026-03-17 07:11:08'),
(6, 'Karima BENALDJIA', 'karima.bena55ldjia.dz@gmail.com', '$2y$10$8wUj.Dt1dBbsVTK02fNN0.JSC8.4ZsQM3o6k2F7GbqyWpRTzW83Oy', '02559885965', 'بالتن', 'tailor', '2026-03-17 07:16:15'),
(7, 'Test User', 'test@example.com', '$2y$10$f/4X7kmQhx1nGVEEBI/XNewcbI73lAZyRmXUmQXCGfu4x4ZBdnyQW', '02559885965555', 'بلفتان', 'customer', '2026-03-17 07:30:49'),
(8, 'Youness BENALDJIA', 'benaldjia.youness.2018@gmail.com', '$2y$10$d9OighY2fvlqnMCgyOIQdOcGHrInKoTwVe3QfCkWME.rFSuL1qvve', '022222222', 'باتنة - باتنة', 'tailor', '2026-03-18 16:34:31'),
(9, 'Youness BENALDJIA', 'benaldjia.youness.20181@gmail.com', '$2y$10$z8HRpm6.YXDD2tDeIOFK0OZUcTkxVwmkfdF7GL/qubh1J6HJTunke', '022222222', 'باتنة - باتنة', 'customer', '2026-03-18 16:35:00'),
(10, 'Youness BENALDJIA', 'b1enaldjia.youness.20181@gmail.com', '$2y$10$..87v5gWYUtt4Ci55P3m/ujcP/mAoHgQmj4PN8RO49vz7KckSlDjS', '022222222', 'باتنة - باتنة', 'customer', '2026-03-18 16:35:26'),
(11, '22', 'koko@k', '$2y$10$gbMssaKgWZy7nPos6BOKqeQLDuCb3JveZrHjOXVcVUsqQmqCK1RDK', '02222222222', 'باتنة - باتنة', 'customer', '2026-03-18 16:35:59'),
(12, 'm;md', 'kkoko@k', '$2y$10$iBuhDgtzso0W8EDS3Kl81enMBx99nU7U/VWoGLVhWSMIrQRx8lYra', '03333333333', 'المغير', 'customer', '2026-03-18 18:36:35'),
(13, 'ghjklm', 'koko@kl', '$2y$10$gdzkM9mBRRZWc.6Dus7rtu7wqdR7nKrRJGwTUKjK/MT0YpE3kUi2S', '0245654', 'المسيلة', 'customer', '2026-03-18 18:40:41'),
(14, 'lo', 'mmmkoko@k', '$2y$10$xTZ6e4HtbpA10s.muRN7oOUN5xBNtgDFOBCoReefaaGNsuTYqoCg6', '0458726', 'باتنة', 'tailor', '2026-03-18 18:41:44'),
(15, 'xgcvfgjkj', 'jhdfvlksmmxq@gf', '$2y$10$A1emdGfGin/acvyRWwkdhOWeg2Zj.FM7SOMp5NGGOy6m37L15NOuS', '02222222555', 'باتنة', 'customer', '2026-03-18 18:43:31'),
(16, 'بيلاتنمكطءيبلاتنمكطمكنتنلبيسيبلاتنمك', 'koko@kkkkkkk', '$2y$10$yeIYwylCIyPhjXoojiurtO/uR9rTu.PE07d3LI4Ez1HC2K2f2/Au6', '8520', 'أولاد جلال', 'tailor', '2026-03-18 18:52:08'),
(17, 'يبلتانمكمكمنمتنرؤتن', 'koko@k2klm', '$2y$10$VpLjPussoqjDlCaOVwOUIuChuysfkiG9bp37BodKifnTuTn0UCSVS', '25858657865', 'المنيعة', 'customer', '2026-03-18 19:03:46'),
(18, 'Youness BENALDJIA', '1222koko@k', '$2y$10$WHpXFOTZwrXX1tfpKSSebO4YeBgLfrU1IC2FnmzsqZNs8eOQSbIze', '0000000000000', 'النعامة', 'customer', '2026-03-24 16:33:56'),
(19, 'سيبلاتنمك', '2222222koko@k', '$2y$10$j.trH3QCafkf1QXObq8ljOMtHO.odptnf3auVBps9PLCIQTaRbtHq', '', 'المنيعة', 'tailor', '2026-03-24 16:34:38'),
(20, 'سبيلفتهمحطكجحمطكهعتغقفيفق', 'kok5555555555o@k', '$2y$10$QW6MHEn7sivIR3SLYLAp8Otk3IRFHTTB0CVRj3sKhKSXKOZ6dNw3u', '253621', 'الوادي', 'customer', '2026-03-24 16:36:44'),
(21, 'يبلتنمكنتلبللتنة', 'koko@k256488', '$2y$10$HlQmeCz1XVxQrlqvrX9Sbeongve1STnv1JEhdzWWoCnvOkIVCp2Ia', '', 'أولاد جلال', 'tailor', '2026-03-24 18:24:30'),
(22, '2222222', 'kok555555555555o@k', '$2y$10$i1dlgIt5X2cc7BsB4uI.huXoHGt6hrVQQMQB/X2K8/x4FuhHKx6By', '', 'النعامة', 'customer', '2026-03-24 18:25:30'),
(23, 'يبلتغنهممكخط', 'koko789524@k', '$2y$10$7bZ..uqAiAT1K94Ox0a51uc/OD8ZV3YcplduSjdR8rfLVoJiieICi', '33', 'الوادي', 'customer', '2026-03-24 18:35:29'),
(24, 'يبلغتعغهمخنكحنهكعتتعف', 'koko55555555@k55555555555555555555', '$2y$10$aYDxRq1r7SdBW1Vae9tDt.utB2KwOubYBtmNzSoouAhmsWM25BA1a', '0222', 'النعامة - ى', 'tailor', '2026-03-24 18:36:28');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `measurement`
--
ALTER TABLE `measurement`
  ADD PRIMARY KEY (`measurement_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `measurements`
--
ALTER TABLE `measurements`
  ADD PRIMARY KEY (`measurement_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order`
--
ALTER TABLE `order`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `tailor_id` (`tailor_id`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `review`
--
ALTER TABLE `review`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `order_id` (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `tailor_id` (`tailor_id`);

--
-- Indexes for table `tailor`
--
ALTER TABLE `tailor`
  ADD PRIMARY KEY (`tailor_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `tailor_portfolio`
--
ALTER TABLE `tailor_portfolio`
  ADD PRIMARY KEY (`portfolio_id`),
  ADD KEY `tailor_id` (`tailor_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `measurement`
--
ALTER TABLE `measurement`
  MODIFY `measurement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `measurements`
--
ALTER TABLE `measurements`
  MODIFY `measurement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `order`
--
ALTER TABLE `order`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `review`
--
ALTER TABLE `review`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tailor`
--
ALTER TABLE `tailor`
  MODIFY `tailor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tailor_portfolio`
--
ALTER TABLE `tailor_portfolio`
  MODIFY `portfolio_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- قيود الجداول المُلقاة.
--

--
-- قيود الجداول `customer`
--
ALTER TABLE `customer`
  ADD CONSTRAINT `customer_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- قيود الجداول `measurement`
--
ALTER TABLE `measurement`
  ADD CONSTRAINT `measurement_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE;

--
-- قيود الجداول `measurements`
--
ALTER TABLE `measurements`
  ADD CONSTRAINT `measurements_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE;

--
-- قيود الجداول `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- قيود الجداول `order`
--
ALTER TABLE `order`
  ADD CONSTRAINT `order_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_ibfk_2` FOREIGN KEY (`tailor_id`) REFERENCES `tailor` (`tailor_id`) ON DELETE CASCADE;

--
-- قيود الجداول `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE;

--
-- قيود الجداول `review`
--
ALTER TABLE `review`
  ADD CONSTRAINT `review_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_ibfk_3` FOREIGN KEY (`tailor_id`) REFERENCES `tailor` (`tailor_id`) ON DELETE CASCADE;

--
-- قيود الجداول `tailor`
--
ALTER TABLE `tailor`
  ADD CONSTRAINT `tailor_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- قيود الجداول `tailor_portfolio`
--
ALTER TABLE `tailor_portfolio`
  ADD CONSTRAINT `tailor_portfolio_ibfk_1` FOREIGN KEY (`tailor_id`) REFERENCES `tailor` (`tailor_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

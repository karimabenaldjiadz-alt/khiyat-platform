-- ============================================
-- قاعدة بيانات منصة خياط - نسخة كاملة مع بيانات منطقية
-- ============================================

-- إنشاء قاعدة البيانات
CREATE DATABASE IF NOT EXISTS tailoring_db;
USE tailoring_db;

-- --------------------------------------------------------
-- جدول المستخدمين
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` enum('customer','tailor') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- جدول الزبائن
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `customer_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- جدول الخياطين
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tailor` (
  `tailor_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `category` varchar(50) DEFAULT 'عام',
  `registration_year` int(11) DEFAULT NULL,
  `experience_points` int(11) DEFAULT 0,
  `rating` float DEFAULT 0,
  PRIMARY KEY (`tailor_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `tailor_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- جدول الطلبات
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `tailor_id` int(11) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivery_date` date DEFAULT NULL,
  `design_image` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `tailor_price` decimal(10,2) DEFAULT NULL,
  `price_status` enum('pending','quoted','accepted') DEFAULT 'pending',
  `status` varchar(50) DEFAULT 'pending',
  PRIMARY KEY (`order_id`),
  KEY `customer_id` (`customer_id`),
  KEY `tailor_id` (`tailor_id`),
  CONSTRAINT `order_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `order_ibfk_2` FOREIGN KEY (`tailor_id`) REFERENCES `tailor` (`tailor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- جدول المقاسات
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `measurements` (
  `measurement_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`measurement_id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `measurements_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- جدول الدفع
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'unpaid',
  `payment_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- جدول التقييمات
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `review` (
  `review_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `tailor_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `review_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`review_id`),
  UNIQUE KEY `order_id` (`order_id`),
  KEY `customer_id` (`customer_id`),
  KEY `tailor_id` (`tailor_id`),
  CONSTRAINT `review_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `order` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `review_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  CONSTRAINT `review_ibfk_3` FOREIGN KEY (`tailor_id`) REFERENCES `tailor` (`tailor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- جدول معرض الأعمال
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tailor_portfolio` (
  `portfolio_id` int(11) NOT NULL AUTO_INCREMENT,
  `tailor_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`portfolio_id`),
  KEY `tailor_id` (`tailor_id`),
  CONSTRAINT `tailor_portfolio_ibfk_1` FOREIGN KEY (`tailor_id`) REFERENCES `tailor` (`tailor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- جدول الإشعارات
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- إضافة بيانات تجريبية منطقية
-- ============================================

-- --------------------------------------------------------
-- 1. إضافة المستخدمين
-- --------------------------------------------------------

-- الزبائن
INSERT INTO `user` (`user_id`, `full_name`, `email`, `password`, `phone`, `address`, `role`, `created_at`) VALUES
(1, 'أحمد محمد', 'ahmed@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0555123456', 'الجزائر العاصمة', 'customer', '2026-01-15 10:00:00'),
(2, 'سارة بن علي', 'sara@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0555234567', 'وهران', 'customer', '2026-01-20 11:30:00'),
(3, 'محمد خالد', 'mohamed@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0555345678', 'قسنطينة', 'customer', '2026-02-01 09:15:00');

-- الخياطين
INSERT INTO `user` (`user_id`, `full_name`, `email`, `password`, `phone`, `address`, `role`, `created_at`) VALUES
(4, 'فاطمة الزهراء', 'fatima@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0555456789', 'الجزائر العاصمة', 'tailor', '2025-11-10 14:20:00'),
(5, 'نور الدين', 'nour@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0555567890', 'وهران', 'tailor', '2025-12-05 16:45:00'),
(6, 'حليمة السعدية', 'halima@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0555678901', 'عنابة', 'tailor', '2026-01-25 08:30:00');

-- --------------------------------------------------------
-- 2. إضافة بيانات الزبائن
-- --------------------------------------------------------
INSERT INTO `customer` (`customer_id`, `user_id`) VALUES
(1, 1),
(2, 2),
(3, 3);

-- --------------------------------------------------------
-- 3. إضافة بيانات الخياطين
-- --------------------------------------------------------
INSERT INTO `tailor` (`tailor_id`, `user_id`, `specialization`, `category`, `registration_year`, `experience_points`, `rating`) VALUES
(1, 4, 'فساتين أعراس و سهرات', 'نساء', 2025, 85, 4.8),
(2, 5, 'خياطة رجالي و بدلات', 'رجال', 2025, 42, 4.5),
(3, 6, 'خياطة تقليدية و قنادر', 'نساء', 2026, 28, 4.2);

-- --------------------------------------------------------
-- 4. إضافة الطلبات
-- --------------------------------------------------------
INSERT INTO `order` (`order_id`, `customer_id`, `tailor_id`, `order_date`, `delivery_date`, `design_image`, `description`, `total_price`, `tailor_price`, `price_status`, `status`) VALUES
(1, 1, 1, '2026-02-10 09:30:00', '2026-03-10', 'uploads/wedding_dress1.jpg', 'فستان زفاف بتطريز يدوي وأكمام طويلة، لون أبيض مع حبات اللؤلؤ', 15000, 18000, 'quoted', 'completed'),
(2, 1, 2, '2026-02-15 11:00:00', '2026-02-28', 'uploads/suit1.jpg', 'بدلة رجالية لون كحلي بقصة عصرية مناسبة لحفل زفاف', 8000, 9500, 'quoted', 'completed'),
(3, 2, 3, '2026-02-20 14:15:00', '2026-03-15', 'uploads/kandoura1.jpg', 'قندورة تقليدية بتطريز ذهبي، قماش حريري فاخر', 12000, 14000, 'quoted', 'in_progress'),
(4, 3, 1, '2026-02-25 16:45:00', '2026-03-20', 'uploads/evening_dress1.jpg', 'فستان سهرة لون أزرق داكن بقصة مميزة', 10000, NULL, 'pending', 'pending'),
(5, 2, 2, '2026-03-01 10:20:00', '2026-03-18', 'uploads/jacket1.jpg', 'جاكيت رجالي شتوي بقصة أنيقة', 5000, 6500, 'quoted', 'in_progress'),
(6, 1, 3, '2026-03-05 09:00:00', '2026-03-25', 'uploads/caftan1.jpg', 'قفطان مغربي بتطريز فاخر ومناسبات رسمية', 18000, 22000, 'quoted', 'pending');

-- --------------------------------------------------------
-- 5. إضافة المقاسات
-- --------------------------------------------------------
INSERT INTO `measurements` (`measurement_id`, `order_id`, `measurement_type`, `size`, `chest`, `waist`, `hips`, `length`, `shoulder`, `sleeve`, `neck`, `notes`) VALUES
(1, 1, 'فستان', 'M', 92.00, 70.00, 96.00, 165.00, 40.00, 58.00, 35.00, 'تطريز على منطقة الصدر'),
(2, 2, 'بدلة', 'L', 98.00, 82.00, 100.00, 172.00, 45.00, 62.00, 38.00, 'مقاس واسع قليلاً عند الكتفين'),
(3, 3, 'قندورة', 'M', 90.00, 68.00, 94.00, 160.00, 38.00, 55.00, 34.00, 'تطريز ذهبي على الأكمام'),
(4, 4, 'فستان', 'S', 86.00, 64.00, 90.00, 158.00, 36.00, 54.00, 32.00, NULL),
(5, 5, 'جاكيت', 'XL', 102.00, 88.00, 104.00, 175.00, 48.00, 65.00, 40.00, 'مفضل قماش صوفي'),
(6, 6, 'قفطان', 'L', 96.00, 74.00, 100.00, 168.00, 42.00, 60.00, 36.00, 'تطريز على الأطراف');

-- --------------------------------------------------------
-- 6. إضافة بيانات الدفع
-- --------------------------------------------------------
INSERT INTO `payment` (`payment_id`, `order_id`, `payment_method`, `amount`, `status`, `payment_date`) VALUES
(1, 1, 'بطاقة بنكية', 18000, 'paid', '2026-02-15 15:30:00'),
(2, 2, 'نقدي عند الاستلام', 9500, 'paid', '2026-02-28 18:00:00'),
(3, 3, 'تحويل بنكي', 14000, 'paid', '2026-02-22 12:45:00'),
(5, 5, 'بطاقة بنكية', 6500, 'paid', '2026-03-03 14:20:00');

-- --------------------------------------------------------
-- 7. إضافة التقييمات
-- --------------------------------------------------------
INSERT INTO `review` (`review_id`, `order_id`, `customer_id`, `tailor_id`, `rating`, `comment`, `review_date`) VALUES
(1, 1, 1, 1, 5, 'فستان رائع جداً، تفاصيل التطريز كانت دقيقة، التسليم في الوقت المحدد، شكراً جزيلاً', '2026-03-12 10:00:00'),
(2, 2, 1, 2, 4, 'بدلة جيدة وجودة ممتازة، لكن الكم كان طويل قليلاً', '2026-03-01 16:30:00');

-- --------------------------------------------------------
-- 8. إضافة أعمال إلى المعرض
-- --------------------------------------------------------
INSERT INTO `tailor_portfolio` (`portfolio_id`, `tailor_id`, `image_path`, `description`, `price`, `created_at`) VALUES
(1, 1, 'uploads/portfolio/wedding_dress1.jpg', 'فستان زفاف كلاسيك بتطريز يدوي', 18000, '2026-01-10 09:00:00'),
(2, 1, 'uploads/portfolio/evening_dress2.jpg', 'فستان سهرة لون أحمر بقصة مميزة', 15000, '2026-01-20 11:30:00'),
(3, 2, 'uploads/portfolio/suit2.jpg', 'بدلة رسمية لون رمادي فاتح', 9500, '2026-01-15 14:00:00'),
(4, 2, 'uploads/portfolio/jacket2.jpg', 'جاكيت رجالي شتوي', 6500, '2026-02-01 10:15:00'),
(5, 3, 'uploads/portfolio/kandoura2.jpg', 'قندورة تقليدية بتطريز ذهبي', 14000, '2026-02-10 08:45:00');

-- --------------------------------------------------------
-- 9. إضافة الإشعارات
-- --------------------------------------------------------
INSERT INTO `notifications` (`notification_id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 4, 'order', '📦 طلب تصميم جديد', 'لديك طلب تصميم جديد من أحمد محمد', 'tailor/view_requests.php', 0, '2026-02-10 09:35:00'),
(2, 1, 'price', '💰 تم تحديد السعر', 'قامت فاطمة الزهراء بتحديد سعر 18000 دج لطلبك #1', 'customer/my_orders.php', 1, '2026-02-12 14:20:00'),
(3, 4, 'payment', '💰 تم الدفع', 'قام الزبون أحمد محمد بدفع الطلب #1', 'tailor/my_orders.php', 1, '2026-02-15 15:35:00'),
(4, 1, 'status', '🔄 تحديث حالة الطلب', 'تم تحديث حالة طلبك #1 إلى مكتمل', 'customer/my_orders.php', 1, '2026-03-10 16:00:00'),
(5, 1, 'review', '⭐ تقييم جديد', 'قام الزبون بتقييمك على الطلب #1 ب 5 نجوم', 'tailor/my_portfolio.php', 0, '2026-03-12 10:05:00'),
(6, 5, 'order', '📦 طلب تصميم جديد', 'لديك طلب تصميم جديد من سارة بن علي', 'tailor/view_requests.php', 0, '2026-02-20 14:20:00'),
(7, 2, 'price', '💰 تم تحديد السعر', 'قام نور الدين بتحديد سعر 14000 دج لطلبك #3', 'customer/my_orders.php', 1, '2026-02-21 09:45:00'),
(8, 5, 'payment', '💰 تم الدفع', 'قام الزبون سارة بن علي بدفع الطلب #3', 'tailor/my_orders.php', 0, '2026-02-22 12:50:00');

-- --------------------------------------------------------
-- عرض إحصائيات قاعدة البيانات
-- --------------------------------------------------------
SELECT 'user' AS `الجدول`, COUNT(*) AS `عدد السجلات` FROM user
UNION ALL SELECT 'customer', COUNT(*) FROM customer
UNION ALL SELECT 'tailor', COUNT(*) FROM tailor
UNION ALL SELECT 'order', COUNT(*) FROM `order`
UNION ALL SELECT 'measurements', COUNT(*) FROM measurements
UNION ALL SELECT 'payment', COUNT(*) FROM payment
UNION ALL SELECT 'review', COUNT(*) FROM review
UNION ALL SELECT 'tailor_portfolio', COUNT(*) FROM tailor_portfolio
UNION ALL SELECT 'notifications', COUNT(*) FROM notifications;

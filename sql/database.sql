-- إنشاء قاعدة البيانات
CREATE DATABASE IF NOT EXISTS tailoring_db;
USE tailoring_db;

-- --------------------------------------------------------
-- جدول المستخدم (user)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS user (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('customer', 'tailor') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- جدول الخياط (tailor)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS tailor (
    tailor_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    specialization VARCHAR(100),
    category VARCHAR(50) DEFAULT 'عام',
    registration_year INT,
    experience_points INT DEFAULT 0,
    rating FLOAT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- جدول الزبون (customer)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS customer (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- جدول المقاسات (measurements)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS measurements (
    measurement_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    measurement_type VARCHAR(50),
    size VARCHAR(10),
    chest DECIMAL(5,2),
    waist DECIMAL(5,2),
    hips DECIMAL(5,2),
    length DECIMAL(5,2),
    shoulder DECIMAL(5,2),
    sleeve DECIMAL(5,2),
    neck DECIMAL(5,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES `order`(order_id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- جدول الطلب (order)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order` (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    tailor_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivery_date DATE,
    design_image TEXT,
    description TEXT,
    total_price DECIMAL(10,2),
    tailor_price DECIMAL(10,2) NULL,
    price_status ENUM('pending', 'quoted', 'accepted') DEFAULT 'pending',
    status VARCHAR(50) DEFAULT 'pending',
    FOREIGN KEY (customer_id) REFERENCES customer(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (tailor_id) REFERENCES tailor(tailor_id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- جدول الدفع (payment)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS payment (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method VARCHAR(50),
    amount DECIMAL(10,2),
    status VARCHAR(50) DEFAULT 'unpaid',
    payment_date TIMESTAMP NULL,
    FOREIGN KEY (order_id) REFERENCES `order`(order_id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- جدول التقييم (review)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS review (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    tailor_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    review_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES `order`(order_id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customer(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (tailor_id) REFERENCES tailor(tailor_id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- جدول معرض أعمال الخياط (tailor_portfolio)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS tailor_portfolio (
    portfolio_id INT AUTO_INCREMENT PRIMARY KEY,
    tailor_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tailor_id) REFERENCES tailor(tailor_id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- جدول الإشعارات (notifications)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255),
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE
);

-- --------------------------------------------------------
-- إضافة بعض البيانات التجريبية
-- --------------------------------------------------------

-- إضافة مستخدمين تجريبيين (كلمة المرور: 123456)
INSERT INTO user (full_name, email, password, phone, address, role) VALUES
('أحمد محمد', 'ahmed@example.com', '$2y$10$YourHashedPasswordHere', '0501234567', 'الجزائر', 'customer'),
('فاطمة علي', 'fatima@example.com', '$2y$10$YourHashedPasswordHere', '0557654321', 'وهران', 'tailor'),
('محمد عبدالله', 'mohamed@example.com', '$2y$10$YourHashedPasswordHere', '0569876543', 'قسنطينة', 'customer'),
('سارة خالد', 'sara@example.com', '$2y$10$YourHashedPasswordHere', '0543216789', 'عنابة', 'tailor');

-- إضافة بيانات الزبائن
INSERT INTO customer (user_id) VALUES (1), (3);

-- إضافة بيانات الخياطين
INSERT INTO tailor (user_id, specialization, registration_year, experience_points, rating) VALUES
(2, 'فساتين أعراس', 2022, 50, 4.5),
(4, 'خياطة رجالي', 2023, 30, 4.0);

-- إضافة مقاسات للزبائن
INSERT INTO measurements (order_id, measurement_type, size, chest, waist, length) VALUES
(1, 'قميص', 'L', 95, 80, 70),
(2, 'بنطال', 'M', NULL, 75, 105);

-- إضافة طلبات تجريبية
INSERT INTO `order` (customer_id, tailor_id, description, total_price, tailor_price, status, delivery_date) VALUES
(1, 1, 'فستان زفاف مع تطريز', 15000, 18000, 'completed', DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
(2, 2, 'بدلة رسمية', 8000, 8500, 'in_progress', DATE_ADD(CURDATE(), INTERVAL 5 DAY));

-- إضافة مدفوعات
INSERT INTO payment (order_id, payment_method, amount, status, payment_date) VALUES
(1, 'بطاقة بنكية', 18000, 'paid', NOW()),
(2, 'نقدي', 8500, 'unpaid', NULL);

-- إضافة تقييمات
INSERT INTO review (order_id, customer_id, tailor_id, rating, comment) VALUES
(1, 1, 1, 5, 'عمل رائع وتفاصيل دقيقة');

-- إضافة أعمال للمعرض
INSERT INTO tailor_portfolio (tailor_id, image_path, description, price) VALUES
(1, 'uploads/portfolio/fashion1.jpg', 'فستان سهرة مع تطريز', 15000),
(1, 'uploads/portfolio/fashion2.jpg', 'بدلة رسمية', 8000),
(2, 'uploads/portfolio/suit1.jpg', 'بدلة رجالية', 9000);

-- إضافة إشعارات تجريبية
INSERT INTO notifications (user_id, type, title, message, link, is_read) VALUES
(1, 'order', 'تم تحديد السعر', 'قام الخياط بتحديد سعر لطلبك', 'customer/my_orders.php', 0),
(2, 'payment', 'تم الدفع', 'قام الزبون بدفع الطلب', 'tailor/my_orders.php', 0);
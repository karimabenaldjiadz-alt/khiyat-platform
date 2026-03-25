<?php
// pages/customer/tailor_profile.php
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/notifications.php';

if (!isLoggedIn() || !hasRole('customer')) {
    redirect('../login.php');
}

$tailor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// جلب معلومات الخياط مع تاريخ التسجيل
$stmt = $pdo->prepare("
    SELECT t.*, u.full_name, u.phone, u.address, u.email, u.created_at as registration_date,
           (SELECT COUNT(*) FROM `order` WHERE tailor_id = t.tailor_id) as total_orders,
           (SELECT COUNT(*) FROM `order` WHERE tailor_id = t.tailor_id AND status = 'completed') as completed_orders,
           (SELECT COUNT(*) FROM review WHERE tailor_id = t.tailor_id) as total_reviews,
           (SELECT AVG(rating) FROM review WHERE tailor_id = t.tailor_id) as avg_rating,
           (SELECT COUNT(*) FROM tailor_portfolio WHERE tailor_id = t.tailor_id) as total_portfolio
    FROM tailor t
    JOIN user u ON t.user_id = u.user_id
    WHERE t.tailor_id = ?
");
$stmt->execute([$tailor_id]);
$tailor = $stmt->fetch();

if (!$tailor) {
    $_SESSION['error'] = 'الخياط غير موجود';
    redirect('browse_tailors.php');
}

$registration_date = new DateTime($tailor['registration_date']);
$registration_date_formatted = $registration_date->format('Y-m-d');

// جلب أعمال الخياط
$portfolio = $pdo->prepare("SELECT * FROM tailor_portfolio WHERE tailor_id = ? ORDER BY created_at DESC");
$portfolio->execute([$tailor_id]);
$portfolio = $portfolio->fetchAll();

// جلب التقييمات
$reviews = $pdo->prepare("
    SELECT r.*, u.full_name as customer_name, o.description as order_description,
           DATE_FORMAT(r.review_date, '%Y-%m-%d') as review_date_formatted
    FROM review r
    JOIN customer c ON r.customer_id = c.customer_id
    JOIN user u ON c.user_id = u.user_id
    LEFT JOIN `order` o ON r.order_id = o.order_id
    WHERE r.tailor_id = ?
    ORDER BY r.review_date DESC
");
$reviews->execute([$tailor_id]);
$reviews = $reviews->fetchAll();

// توزيع التقييمات
$rating_distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($reviews as $review) {
    $rating_distribution[$review['rating']]++;
}

$unread_count = getUnreadNotificationsCount($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ملف <?php echo htmlspecialchars($tailor['full_name']); ?> - خياط</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .dashboard { display: flex; min-height: 100vh; position: relative; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #2c3e50 0%, #1e2b38 100%); color: white; position: fixed; height: 100vh; overflow-y: auto; right: 0; top: 0; box-shadow: -5px 0 20px rgba(0,0,0,0.2); z-index: 1000; }
        .main-content { flex: 1; margin-right: 280px; padding: 30px; background: #f5f5f5; }
        
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-track { background: #34495e; }
        .sidebar::-webkit-scrollbar-thumb { background: #667eea; border-radius: 3px; }
        
        .user-info { text-align: center; padding: 30px 20px; background: rgba(0,0,0,0.2); border-bottom: 1px solid #34495e; }
        .user-info h3 { color: white; margin-bottom: 5px; }
        .user-info .role { color: #bdc3c7; background: rgba(255,255,255,0.1); display: inline-block; padding: 5px 15px; border-radius: 20px; margin-top: 10px; }
        
        .nav-menu { list-style: none; padding: 20px 0; }
        .nav-menu li { margin-bottom: 5px; }
        .nav-menu a { display: flex; align-items: center; gap: 12px; padding: 15px 25px; color: #ecf0f1; text-decoration: none; transition: all 0.3s ease; border-right: 4px solid transparent; }
        .nav-menu a:hover { background: rgba(102,126,234,0.2); border-right-color: #667eea; padding-right: 35px; }
        .nav-menu a.active { background: linear-gradient(90deg, #667eea 0%, transparent 100%); border-right-color: #ffc107; font-weight: bold; }
        
        /* قسم البروفايل */
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(102,126,234,0.3);
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5em;
            border: 4px solid white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
            z-index: 1;
        }
        
        .profile-name {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .profile-badges {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(5px);
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 30px;
            position: relative;
            z-index: 1;
        }
        
        .stat-item {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .profile-contact {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .contact-item {
            background: rgba(255,255,255,0.1);
            padding: 10px 20px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95em;
        }
        
        /* قسم التصنيفات */
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .section-title h2 {
            color: #333;
            font-size: 1.8em;
        }
        
        .section-title span {
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 25px;
            font-size: 0.9em;
        }
        
        /* شبكة الأعمال */
        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .portfolio-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .portfolio-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(102,126,234,0.2);
        }
        
        .portfolio-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .portfolio-image:hover {
            transform: scale(1.05);
        }
        
        .portfolio-info {
            padding: 20px;
        }
        
        .portfolio-description {
            color: #555;
            margin: 10px 0;
            line-height: 1.6;
        }
        
        .portfolio-price {
            font-size: 1.5em;
            color: #667eea;
            font-weight: bold;
            margin: 15px 0;
        }
        
        .portfolio-date {
            color: #999;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* قسم التقييمات */
        .reviews-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        
        .rating-summary {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
        }
        
        .rating-average {
            text-align: center;
            padding: 20px;
        }
        
        .average-number {
            font-size: 4em;
            font-weight: bold;
            color: #667eea;
            line-height: 1;
        }
        
        .average-stars {
            color: #ffc107;
            font-size: 1.5em;
            margin: 10px 0;
            letter-spacing: 3px;
        }
        
        .average-total {
            color: #666;
        }
        
        .rating-bars {
            padding: 20px;
        }
        
        .rating-bar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .rating-bar-label {
            min-width: 60px;
            color: #666;
        }
        
        .rating-bar-progress {
            flex: 1;
            height: 10px;
            background: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .rating-bar-fill {
            height: 100%;
            background: #ffc107;
            border-radius: 5px;
        }
        
        .rating-bar-count {
            min-width: 40px;
            color: #666;
            text-align: left;
        }
        
        /* بطاقة التقييم */
        .review-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border-right: 4px solid #ffc107;
            transition: transform 0.3s ease;
        }
        
        .review-card:hover {
            transform: translateX(-5px);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .reviewer-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5em;
        }
        
        .reviewer-details h4 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .reviewer-details p {
            color: #666;
            font-size: 0.9em;
        }
        
        .review-rating {
            color: #ffc107;
            font-size: 1.2em;
            letter-spacing: 2px;
        }
        
        .review-order {
            background: #e9ecef;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            color: #495057;
            display: inline-block;
            margin: 10px 0;
        }
        
        .review-comment {
            color: #555;
            margin: 15px 0;
            line-height: 1.8;
            font-size: 1.05em;
            background: white;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }
        
        .review-date {
            color: #999;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* زر الطلب */
        .order-btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.2em;
            margin-top: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
            border: 1px solid transparent;
        }
        
        .order-btn:hover {
            background: white;
            color: #667eea;
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102,126,234,0.3);
        }
        
        /* نافذة عرض الصور */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
        }
        
        .modal-content {
            max-width: 90%;
            max-height: 90%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 10px;
        }
        
        .close-modal {
            position: absolute;
            top: 20px;
            left: 30px;
            color: white;
            font-size: 40px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-right: 0; }
            .dashboard { flex-direction: column; }
            .profile-stats { grid-template-columns: repeat(2, 1fr); }
            .rating-summary { grid-template-columns: 1fr; }
            .profile-avatar { width: 100px; height: 100px; font-size: 3em; }
            .profile-name { font-size: 2em; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- الشريط الجانبي -->
        <div class="sidebar">
            <div class="user-info">
                <div style="width:80px;height:80px;background:rgba(255,255,255,0.1);border-radius:50%;margin:0 auto 15px;display:flex;align-items:center;justify-content:center;font-size:2.5em;">👤</div>
                <h3><?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
                <div style="background:rgba(255,255,255,0.1);padding:5px 10px;border-radius:15px;font-size:0.8em;margin:5px 0;">ID: <?php echo $_SESSION['user_id']; ?></div>
                <p class="role">👤 زبون</p>
                
                <!-- أيقونة الإشعارات -->
                <div style="position: relative; margin-top: 15px;">
                    <a href="../notifications.php" style="color: white; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 5px;">
                        <span style="font-size: 1.5em;">🔔</span>
                        <?php if ($unread_count > 0): ?>
                            <span style="background: #dc3545; color: white; border-radius: 50%; padding: 2px 8px; font-size: 0.8em; position: absolute; top: -5px; left: 30px;"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
            <ul class="nav-menu">
                <li><a href="../dashboard.php">🏠 الرئيسية</a></li>
                <li><a href="browse_tailors.php">🔍 البحث عن خياط</a></li>
                <li><a href="my_orders.php">📦 طلباتي</a></li>
                <li><a href="place_order.php">➕ طلب جديد</a></li>

                <li><a href="../logout.php" class="logout-link">🚪 تسجيل خروج</a></li>
            </ul>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="main-content">
            <!-- قسم البروفايل -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo mb_substr($tailor['full_name'], 0, 1); ?>
                </div>
                
                <div class="profile-name">
                    <?php echo htmlspecialchars($tailor['full_name']); ?>
                </div>
                
                <div class="profile-badges">
                    <div class="badge">
                        <span>📅</span>
                        <span>عضو منذ <?php echo $registration_date_formatted; ?></span>
                    </div>
                    <div class="badge">
                        <span>💎</span>
                        <span><?php echo $tailor['experience_points']; ?> نقطة خبرة</span>
                    </div>
                    <div class="badge">
                        <span>🔨</span>
                        <span><?php echo htmlspecialchars($tailor['specialization']); ?></span>
                    </div>
                </div>
                
                <div class="profile-contact">
                    <?php if (!empty($tailor['email'])): ?>
                        <div class="contact-item">
                            <span>📧</span>
                            <span><?php echo htmlspecialchars($tailor['email']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($tailor['phone'])): ?>
                        <div class="contact-item">
                            <span>📞</span>
                            <span><?php echo htmlspecialchars($tailor['phone']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($tailor['address'])): ?>
                        <div class="contact-item">
                            <span>📍</span>
                            <span><?php echo htmlspecialchars($tailor['address']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $tailor['completed_orders']; ?></div>
                        <div class="stat-label">طلبات مكتملة</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $tailor['total_reviews']; ?></div>
                        <div class="stat-label">تقييم</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $tailor['total_portfolio']; ?></div>
                        <div class="stat-label">عمل في المعرض</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo number_format($tailor['avg_rating'] ?? 0, 1); ?></div>
                        <div class="stat-label">متوسط التقييم</div>
                    </div>
                </div>
            </div>
            
            <!-- زر طلب تصميم -->
            <div style="text-align: center; margin-bottom: 30px;">
                <a href="place_order.php?tailor_id=<?php echo $tailor_id; ?>" class="order-btn">
                    ✨ طلب تصميم من <?php echo htmlspecialchars($tailor['full_name']); ?>
                </a>
            </div>
            
            <!-- معرض الأعمال -->
            <?php if (count($portfolio) > 0): ?>
                <div class="section-title">
                    <h2>📸 معرض الأعمال</h2>
                    <span><?php echo count($portfolio); ?> عمل</span>
                </div>
                
                <div class="portfolio-grid">
                    <?php foreach ($portfolio as $item): ?>
                        <div class="portfolio-card">
                            <img src="../../<?php echo $item['image_path']; ?>" 
                                 class="portfolio-image" 
                                 onclick="openModal('../../<?php echo $item['image_path']; ?>')"
                                 title="اضغط للتكبير">
                            <div class="portfolio-info">
                                <div class="portfolio-description">
                                    <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                                </div>
                                <div class="portfolio-price">
                                    💰 <?php echo number_format($item['price'], 0); ?> د.ج
                                </div>
                                <div class="portfolio-date">
                                    <span>📅</span>
                                    <span><?php echo date('Y-m-d', strtotime($item['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="section-title">
                    <h2>📸 معرض الأعمال</h2>
                </div>
                <div style="background: white; padding: 40px; text-align: center; border-radius: 15px; margin-bottom: 30px;">
                    <p style="color: #666;">لا توجد أعمال في المعرض بعد</p>
                </div>
            <?php endif; ?>
            
            <!-- التقييمات -->
            <?php if (count($reviews) > 0): ?>
                <div class="reviews-section">
                    <div class="section-title">
                        <h2>📝 التقييمات</h2>
                        <span><?php echo count($reviews); ?> تقييم</span>
                    </div>
                    
                    <!-- ملخص التقييمات -->
                    <div class="rating-summary">
                        <div class="rating-average">
                            <div class="average-number"><?php echo number_format($tailor['avg_rating'] ?? 0, 1); ?></div>
                            <div class="average-stars">
                                <?php 
                                    $full_stars = floor($tailor['avg_rating'] ?? 0);
                                    for($i = 1; $i <= 5; $i++) {
                                        if($i <= $full_stars) echo '★';
                                        elseif($i - 0.5 <= ($tailor['avg_rating'] ?? 0)) echo '½';
                                        else echo '☆';
                                    }
                                ?>
                            </div>
                            <div class="average-total">من <?php echo count($reviews); ?> تقييم</div>
                        </div>
                        
                        <div class="rating-bars">
                            <?php for($i = 5; $i >= 1; $i--): ?>
                                <?php 
                                    $count = $rating_distribution[$i] ?? 0;
                                    $percentage = count($reviews) > 0 ? ($count / count($reviews)) * 100 : 0;
                                ?>
                                <div class="rating-bar-item">
                                    <div class="rating-bar-label"><?php echo $i; ?> ★</div>
                                    <div class="rating-bar-progress">
                                        <div class="rating-bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <div class="rating-bar-count"><?php echo $count; ?></div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <!-- قائمة التقييمات -->
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div class="reviewer-info">
                                    <div class="reviewer-avatar">
                                        <?php echo mb_substr($review['customer_name'], 0, 1); ?>
                                    </div>
                                    <div class="reviewer-details">
                                        <h4><?php echo htmlspecialchars($review['customer_name']); ?></h4>
                                        <p>زبون</p>
                                    </div>
                                </div>
                                <div class="review-rating">
                                    <?php echo str_repeat('★', $review['rating']); ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($review['order_description'])): ?>
                                <div class="review-order">
                                    📋 طلب: <?php echo htmlspecialchars(substr($review['order_description'], 0, 70)); ?>...
                                </div>
                            <?php endif; ?>
                            
                            <div class="review-comment">
                                <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                            </div>
                            
                            <div class="review-date">
                                <span>📅</span>
                                <span><?php echo $review['review_date_formatted']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="reviews-section">
                    <div class="section-title">
                        <h2>📝 التقييمات</h2>
                    </div>
                    <div style="background: white; padding: 40px; text-align: center; border-radius: 15px;">
                        <p style="color: #666;">لا توجد تقييمات بعد</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- نافذة عرض الصور -->
    <div id="imageModal" class="modal" onclick="closeModal()">
        <span class="close-modal">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>
    
    <script src="../../assets/js/script.js"></script>
    <script>
        function openModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.getElementById('imageModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
    </script>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
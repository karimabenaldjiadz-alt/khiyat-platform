<?php
// pages/tailor/my_portfolio.php
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/notifications.php';

if (!isLoggedIn() || !hasRole('tailor')) {
    redirect('../login.php');
}

$unread_count = getUnreadNotificationsCount($_SESSION['user_id']);

// جلب معرف الخياط
$stmt = $pdo->prepare("SELECT tailor_id FROM tailor WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$tailor_id = $stmt->fetch()['tailor_id'];

// حذف عمل
if (isset($_GET['delete'])) {
    $portfolio_id = intval($_GET['delete']);
    
    // جلب مسار الصورة لحذفها
    $stmt = $pdo->prepare("SELECT image_path FROM tailor_portfolio WHERE portfolio_id = ? AND tailor_id = ?");
    $stmt->execute([$portfolio_id, $tailor_id]);
    $item = $stmt->fetch();
    
    if ($item) {
        // حذف الصورة من المجلد
        $file_path = '../../' . $item['image_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // حذف من قاعدة البيانات
        $stmt = $pdo->prepare("DELETE FROM tailor_portfolio WHERE portfolio_id = ?");
        $stmt->execute([$portfolio_id]);
        
        $_SESSION['success'] = 'تم حذف العمل بنجاح';
    }
    redirect('my_portfolio.php');
}

// جلب كل الأعمال
$stmt = $pdo->prepare("
    SELECT * FROM tailor_portfolio 
    WHERE tailor_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$tailor_id]);
$portfolio = $stmt->fetchAll();

// جلب إحصائيات التقييمات
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_reviews,
        AVG(rating) as avg_rating,
        MAX(review_date) as last_review
    FROM review 
    WHERE tailor_id = ?
");
$stmt->execute([$tailor_id]);
$review_stats = $stmt->fetch();

// جلب آخر 5 تقييمات
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name as customer_name, o.description as order_description
    FROM review r
    JOIN customer c ON r.customer_id = c.customer_id
    JOIN user u ON c.user_id = u.user_id
    JOIN `order` o ON r.order_id = o.order_id
    WHERE r.tailor_id = ?
    ORDER BY r.review_date DESC
    LIMIT 5
");
$stmt->execute([$tailor_id]);
$recent_reviews = $stmt->fetchAll();

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>معرض أعمالي - خياط</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .dashboard { display: flex; min-height: 100vh; position: relative; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #2c3e50 0%, #1e2b38 100%); color: white; position: fixed; height: 100vh; overflow-y: auto; right: 0; top: 0; box-shadow: -5px 0 20px rgba(0,0,0,0.2); z-index: 1000; }
        .main-content { flex: 1; margin-right: 280px; padding: 30px; background: #f5f5f5; min-height: 100vh; }
        
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-track { background: #34495e; }
        .sidebar::-webkit-scrollbar-thumb { background: #667eea; border-radius: 3px; }
        
        .user-info { text-align: center; padding: 30px 20px; background: rgba(0,0,0,0.2); border-bottom: 1px solid #34495e; }
        .user-info h3 { color: white; margin-bottom: 5px; font-size: 1.3em; }
        .user-info .role { color: #bdc3c7; font-size: 0.95em; background: rgba(255,255,255,0.1); display: inline-block; padding: 5px 15px; border-radius: 20px; margin-top: 10px; }
        
        .nav-menu { list-style: none; padding: 20px 0; }
        .nav-menu li { margin-bottom: 5px; }
        .nav-menu a { display: flex; align-items: center; gap: 12px; padding: 15px 25px; color: #ecf0f1; text-decoration: none; transition: all 0.3s ease; border-right: 4px solid transparent; }
        .nav-menu a:hover { background: rgba(102,126,234,0.2); border-right-color: #667eea; padding-right: 35px; }
        .nav-menu a.active { background: linear-gradient(90deg, #667eea 0%, transparent 100%); border-right-color: #ffc107; font-weight: bold; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .page-title {
            color: #333;
            font-size: 2em;
        }
        
        .add-btn {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            padding: 12px 25px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 1em;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
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
            box-shadow: 0 8px 25px rgba(102,126,234,0.2);
        }
        
        .portfolio-image {
            width: 100%;
            height: 280px;
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
            color: #333;
            margin: 15px 0;
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
            margin: 10px 0;
        }
        
        .control-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.95em;
            transition: all 0.3s ease;
        }
        
        .delete-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .view-btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.95em;
            transition: all 0.3s ease;
        }
        
        .view-btn:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        
        .reviews-section {
            margin-top: 40px;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .reviews-section h2 {
            color: #333;
            margin-bottom: 25px;
            font-size: 1.5em;
        }
        
        .review-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border-right: 4px solid #ffc107;
            transition: transform 0.3s ease;
        }
        
        .review-item:hover {
            transform: translateX(-5px);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .reviewer-name {
            font-size: 1.1em;
            font-weight: bold;
            color: #333;
        }
        
        .review-stars {
            color: #ffc107;
            font-size: 1.3em;
            letter-spacing: 2px;
        }
        
        .review-order {
            background: #e9ecef;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            color: #495057;
            display: inline-block;
            margin: 8px 0;
        }
        
        .review-comment {
            color: #555;
            margin: 15px 0;
            line-height: 1.6;
            font-size: 1.05em;
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .review-date {
            color: #999;
            font-size: 0.85em;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .empty-state p {
            color: #666;
            font-size: 1.2em;
            margin: 20px 0;
        }
        
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
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-right: 0; }
            .dashboard { flex-direction: column; }
            .portfolio-grid {
                grid-template-columns: 1fr;
            }
            .review-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .control-buttons {
                flex-direction: column;
            }
            .control-buttons a {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- الشريط الجانبي -->
        <div class="sidebar">
            <div class="user-info">
                <div style="width:80px;height:80px;background:rgba(255,255,255,0.1);border-radius:50%;margin:0 auto 15px;display:flex;align-items:center;justify-content:center;font-size:2.5em;">✂️</div>
                <h3><?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
                <div style="background:rgba(255,255,255,0.1);padding:5px 10px;border-radius:15px;font-size:0.8em;margin:5px 0;">ID: <?php echo $_SESSION['user_id']; ?></div>
                <p class="role">✂️ خياط</p>
                
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
                <li><a href="view_requests.php">📋 طلبات جديدة</a></li>
                <li><a href="my_orders.php">📦 طلباتي</a></li>
                <li><a href="add_portfolio.php">➕ إضافة عمل</a></li>
                <li><a href="my_portfolio.php" class="active">📸 معرض أعمالي</a></li>
   
                <li><a href="../logout.php" class="logout-link">🚪 تسجيل خروج</a></li>
            </ul>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">📸 معرض أعمالي</h1>
                <a href="add_portfolio.php" class="add-btn">
                    <span>➕</span> إضافة عمل جديد
                </a>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- إحصائيات سريعة -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>📷 إجمالي الأعمال</h3>
                    <div class="stat-number"><?php echo count($portfolio); ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>⭐ عدد التقييمات</h3>
                    <div class="stat-number"><?php echo $review_stats['total_reviews'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>📊 متوسط التقييم</h3>
                    <div class="stat-number">
                        <?php echo $review_stats['avg_rating'] ? number_format($review_stats['avg_rating'], 1) : '0.0'; ?>
                    </div>
                </div>
            </div>
            
            <!-- عرض الأعمال -->
            <?php if (count($portfolio) > 0): ?>
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
                                    📅 <?php echo date('Y-m-d', strtotime($item['created_at'])); ?>
                                </div>
                                
                                <div class="control-buttons">
                                    <a href="?delete=<?php echo $item['portfolio_id']; ?>" 
                                       class="delete-btn"
                                       onclick="return confirm('هل أنت متأكد من حذف هذا العمل؟')">
                                        🗑️ حذف
                                    </a>
                                    
                                    <a href="#" class="view-btn" onclick="openModal('../../<?php echo $item['image_path']; ?>'); return false;">
                                        🔍 عرض
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>📷 لا توجد أعمال في معرضك بعد</p>
                    <p style="color: #666;">أضف أعمالك الأولى ليراها الزبائن</p>
                    <a href="add_portfolio.php" class="btn btn-primary" style="margin-top: 20px; display:inline-block; padding:12px 30px; background:#667eea; color:white; text-decoration:none; border-radius:25px;">
                        ➕ إضافة أول عمل
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- قسم التقييمات -->
            <?php if (count($recent_reviews) > 0): ?>
                <div class="reviews-section">
                    <h2>📝 آخر التقييمات</h2>
                    
                    <?php foreach ($recent_reviews as $review): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <span class="reviewer-name">
                                    👤 <?php echo htmlspecialchars($review['customer_name']); ?>
                                </span>
                                <span class="review-stars">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $review['rating']) {
                                            echo '★';
                                        } else {
                                            echo '☆';
                                        }
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <div class="review-order">
                                📋 الطلب: <?php echo htmlspecialchars(substr($review['order_description'], 0, 70)); ?>...
                            </div>
                            
                            <div class="review-comment">
                                "<?php echo nl2br(htmlspecialchars($review['comment'])); ?>"
                            </div>
                            
                            <div class="review-date">
                                📅 <?php echo date('Y-m-d', strtotime($review['review_date'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
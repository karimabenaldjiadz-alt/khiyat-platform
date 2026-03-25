<?php
// pages/customer/review_order.php
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/notifications.php';

if (!isLoggedIn() || !hasRole('customer')) {
    redirect('../login.php');
}

$unread_count = getUnreadNotificationsCount($_SESSION['user_id']);

$error = '';
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

$stmt = $pdo->prepare("SELECT customer_id FROM customer WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customer_id = $stmt->fetch()['customer_id'];

$stmt = $pdo->prepare("
    SELECT o.*, t.tailor_id, u.full_name as tailor_name, t.user_id as tailor_user_id
    FROM `order` o
    JOIN tailor t ON o.tailor_id = t.tailor_id
    JOIN user u ON t.user_id = u.user_id
    WHERE o.order_id = ? AND o.customer_id = ? AND o.status = 'completed'
    AND o.order_id NOT IN (SELECT order_id FROM review)
");
$stmt->execute([$order_id, $customer_id]);
$order = $stmt->fetch();

if (!$order) { 
    $_SESSION['error'] = 'هذا الطلب غير متاح للتقييم'; 
    redirect('my_orders.php'); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating']);
    $comment = sanitize($_POST['comment']);
    
    if ($rating < 1 || $rating > 5) { 
        $error = 'التقييم يجب أن يكون بين 1 و 5'; 
    } else {
        try {
            $pdo->beginTransaction();
            
            // إضافة التقييم
            $stmt = $pdo->prepare("INSERT INTO review (order_id, customer_id, tailor_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$order_id, $customer_id, $order['tailor_id'], $rating, $comment]);
            
            // تحديث متوسط تقييم الخياط
            $stmt = $pdo->prepare("
                UPDATE tailor t
                SET t.rating = (
                    SELECT AVG(rating) 
                    FROM review 
                    WHERE tailor_id = ?
                )
                WHERE t.tailor_id = ?
            ");
            $stmt->execute([$order['tailor_id'], $order['tailor_id']]);
            
            // إضافة إشعار للخياط
            addNotification(
                $order['tailor_user_id'],
                'review',
                '⭐ تقييم جديد',
                'قام الزبون بتقييمك على الطلب #' . $order_id . ' ب ' . $rating . ' نجوم',
                'tailor/my_portfolio.php'
            );
            
            $pdo->commit();
            
            $_SESSION['success'] = 'شكراً لك! تم إضافة تقييمك بنجاح';
            redirect('my_orders.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقييم الخياط - منصة خياط</title>
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
        
        .review-container {
            max-width: 600px;
            margin: 30px auto;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .tailor-info {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .tailor-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5em;
            color: white;
            margin: 0 auto 15px;
        }
        
        .tailor-info h2 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .rating-stars {
            text-align: center;
            margin: 30px 0;
        }
        
        .star {
            font-size: 3em;
            color: #ddd;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-block;
            margin: 0 5px;
        }
        
        .star:hover,
        .star.active,
        .star.selected {
            color: #ffc107;
            transform: scale(1.1);
        }
        
        .rating-text {
            text-align: center;
            margin: 20px 0;
            font-size: 1.2em;
            color: #666;
        }
        
        .comment-box {
            margin: 30px 0;
        }
        
        .comment-box textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            font-size: 1em;
            resize: vertical;
            min-height: 120px;
            transition: border-color 0.3s ease;
        }
        
        .comment-box textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 1.2em;
            border-radius: 15px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
        }
        
        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .order-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
        }
        
        .order-summary h3 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-right: 0; }
            .dashboard { flex-direction: column; }
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
            <div class="review-container">
                <h1 style="text-align: center; color: #333; margin-bottom: 30px;">⭐ تقييم الخياط</h1>
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- معلومات الخياط -->
                <div class="tailor-info">
                    <div class="tailor-avatar"><?php echo mb_substr($order['tailor_name'], 0, 1); ?></div>
                    <h2><?php echo htmlspecialchars($order['tailor_name']); ?></h2>
                    <p style="color: #666;">تقييمك يساعد الخياط على تحسين خدماته</p>
                </div>
                
                <!-- ملخص الطلب -->
                <div class="order-summary">
                    <h3>تفاصيل الطلب #<?php echo $order['order_id']; ?></h3>
                    <p><strong>الوصف:</strong> <?php echo htmlspecialchars(substr($order['description'], 0, 100)); ?>...</p>
                    <p><strong>السعر:</strong> <?php echo number_format($order['tailor_price'] ?? $order['total_price'], 0); ?> د.ج</p>
                </div>
                
                <form method="POST" id="reviewForm">
                    <!-- التقييم بالنجوم -->
                    <div class="rating-stars">
                        <span class="star" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                        <input type="hidden" name="rating" id="ratingValue" required>
                    </div>
                    
                    <div class="rating-text" id="ratingText">
                        اختر تقييمك
                    </div>
                    
                    <!-- التعليق -->
                    <div class="comment-box">
                        <label style="display: block; margin-bottom: 10px; font-weight: bold;">
                            أضف تعليقاً <span style="color: #999; font-weight: normal;">(اختياري)</span>
                        </label>
                        <textarea name="comment" placeholder="اكتب رأيك في تجربتك مع هذا الخياط..."></textarea>
                    </div>
                    
                    <button type="submit" class="submit-btn" id="submitBtn" disabled>
                        إرسال التقييم
                    </button>
                    
                    <p style="text-align: center; margin-top: 20px;">
                        <a href="my_orders.php" style="color: #667eea;">← العودة للطلبات</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
    

    <script>
        const stars = document.querySelectorAll('.star');
        const ratingInput = document.getElementById('ratingValue');
        const ratingText = document.getElementById('ratingText');
        const submitBtn = document.getElementById('submitBtn');
        
        const ratingMessages = {
            1: 'سيء جداً 😞',
            2: 'سيء 😕',
            3: 'مقبول 😐',
            4: 'جيد 😊',
            5: 'ممتاز 🤩'
        };
        
        stars.forEach(star => {
            star.addEventListener('mouseover', function() {
                const rating = this.dataset.rating;
                highlightStars(rating);
            });
            
            star.addEventListener('mouseout', function() {
                const currentRating = ratingInput.value;
                if (currentRating) {
                    highlightStars(currentRating);
                } else {
                    resetStars();
                }
            });
            
            star.addEventListener('click', function() {
                const rating = this.dataset.rating;
                ratingInput.value = rating;
                highlightStars(rating);
                ratingText.textContent = ratingMessages[rating];
                submitBtn.disabled = false;
            });
        });
        
        function highlightStars(rating) {
            stars.forEach(star => {
                if (star.dataset.rating <= rating) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
        }
        
        function resetStars() {
            stars.forEach(star => {
                star.classList.remove('active');
            });
            ratingText.textContent = 'اختر تقييمك';
        }
        
        document.getElementById('reviewForm').addEventListener('submit', function(e) {
            if (!ratingInput.value) {
                e.preventDefault();
                alert('الرجاء اختيار تقييم');
            }
        });
    </script>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
<?php
// pages/customer/order_details.php
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/notifications.php';

if (!isLoggedIn() || !hasRole('customer')) {
    redirect('../login.php');
}

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// جلب تفاصيل الطلب مع معلومات الخياط والدفع
$stmt = $pdo->prepare("
    SELECT o.*, 
           u.full_name as tailor_name, 
           t.tailor_id,
           t.rating as tailor_rating,
           t.experience_points,
           t.specialization,
           (SELECT COUNT(*) FROM payment WHERE order_id = o.order_id) as has_payment,
           (SELECT payment_method FROM payment WHERE order_id = o.order_id) as payment_method,
           (SELECT payment_date FROM payment WHERE order_id = o.order_id) as payment_date
    FROM `order` o
    JOIN tailor t ON o.tailor_id = t.tailor_id
    JOIN user u ON t.user_id = u.user_id
    WHERE o.order_id = ? AND o.customer_id = (SELECT customer_id FROM customer WHERE user_id = ?)
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['error'] = 'الطلب غير موجود';
    redirect('my_orders.php');
}

$unread_count = getUnreadNotificationsCount($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الطلب #<?php echo $order['order_id']; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .dashboard { display: flex; min-height: 100vh; position: relative; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #2c3e50 0%, #1e2b38 100%); color: white; position: fixed; height: 100vh; overflow-y: auto; right: 0; top: 0; box-shadow: -5px 0 20px rgba(0,0,0,0.2); z-index: 1000; }
        .main-content { flex: 1; margin-right: 280px; padding: 30px; background: #f5f5f5; min-height: 100vh; }
        
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
        
        /* رأس الصفحة */
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
        
        .back-btn {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        /* حالة الطلب */
        .order-status-badge {
            display: inline-block;
            padding: 8px 25px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1em;
            margin-right: 15px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .status-in_progress {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* بطاقة المعلومات */
        .info-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .info-card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .info-card-title h3 {
            color: #333;
            font-size: 1.3em;
        }
        
        .info-card-title span {
            background: #667eea;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8em;
        }
        
        /* معلومات الخياط */
        .tailor-profile {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
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
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }
        
        .tailor-info h2 {
            color: #333;
            font-size: 1.8em;
            margin-bottom: 5px;
        }
        
        .tailor-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .tailor-meta-item {
            background: #f8f9fa;
            padding: 5px 15px;
            border-radius: 25px;
            color: #666;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* شبكة المعلومات */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        
        .info-icon {
            font-size: 2em;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .info-label {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #333;
            font-size: 1.3em;
            font-weight: bold;
        }
        
        /* وصف التصميم */
        .description-box {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            line-height: 1.8;
            color: #555;
            border-right: 4px solid #667eea;
        }
        
        /* صورة التصميم */
        .image-container {
            text-align: center;
            margin-top: 20px;
        }
        
        .design-image {
            max-width: 100%;
            max-height: 400px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .design-image:hover {
            transform: scale(1.02);
        }
        
        /* معلومات الدفع */
        .payment-info {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            border-radius: 15px;
            padding: 20px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .payment-info.unpaid {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .payment-info.pending {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        }
        
        .payment-details {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .payment-icon {
            font-size: 2.5em;
        }
        
        .payment-text h4 {
            font-size: 1.1em;
            margin-bottom: 5px;
        }
        
        .payment-text p {
            opacity: 0.9;
        }
        
        /* زر الدفع */
        .pay-now-btn {
            background: white;
            color: #28a745;
            padding: 10px 25px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .pay-now-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
        
        /* أزرار الإجراءات */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 12px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-right: 0; }
            .dashboard { flex-direction: column; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .tailor-profile { flex-direction: column; text-align: center; }
            .tailor-meta { justify-content: center; }
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
            <div class="page-header">
                <h1 class="page-title">📋 تفاصيل الطلب #<?php echo $order['order_id']; ?></h1>
                <a href="my_orders.php" class="back-btn">
                    <span>←</span> العودة للطلبات
                </a>
            </div>
            
            <!-- بطاقة حالة الطلب -->
            <div class="info-card">
                <div class="info-card-title">
                    <h3>📊 حالة الطلب</h3>
                </div>
                <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                    <span class="order-status-badge status-<?php echo $order['status']; ?>">
                        <?php 
                        $status_map = [
                            'pending' => '⏳ قيد الانتظار',
                            'in_progress' => '🔨 قيد التنفيذ',
                            'completed' => '✅ مكتمل',
                            'cancelled' => '❌ ملغي'
                        ];
                        echo $status_map[$order['status']] ?? $order['status'];
                        ?>
                    </span>
                    <span style="color: #666;">تاريخ الطلب: <?php echo date('Y-m-d', strtotime($order['order_date'])); ?></span>
                </div>
            </div>
            
            <!-- بطاقة معلومات الخياط -->
            <div class="info-card">
                <div class="info-card-title">
                    <h3>👤 معلومات الخياط</h3>
                </div>
                <div class="tailor-profile">
                    <div class="tailor-avatar">
                        <?php echo mb_substr($order['tailor_name'], 0, 1); ?>
                    </div>
                    <div class="tailor-info">
                        <h2><?php echo htmlspecialchars($order['tailor_name']); ?></h2>
                        <div class="tailor-meta">
                            <span class="tailor-meta-item">⭐ <?php echo number_format($order['tailor_rating'], 1); ?></span>
                            <span class="tailor-meta-item">💎 <?php echo $order['experience_points']; ?> نقطة</span>
                            <span class="tailor-meta-item">🔨 <?php echo htmlspecialchars($order['specialization']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- بطاقة معلومات الطلب -->
            <div class="info-card">
                <div class="info-card-title">
                    <h3>📦 معلومات الطلب</h3>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon">💰</div>
                        <div class="info-label">السعر المقترح</div>
                        <div class="info-value"><?php echo number_format($order['total_price'], 0); ?> د.ج</div>
                    </div>
                    
                    <?php if ($order['tailor_price']): ?>
                    <div class="info-item">
                        <div class="info-icon">💵</div>
                        <div class="info-label">السعر النهائي</div>
                        <div class="info-value"><?php echo number_format($order['tailor_price'], 0); ?> د.ج</div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <div class="info-icon">📅</div>
                        <div class="info-label">تاريخ التسليم</div>
                        <div class="info-value"><?php echo $order['delivery_date']; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- بطاقة وصف التصميم -->
            <div class="info-card">
                <div class="info-card-title">
                    <h3>📝 وصف التصميم</h3>
                </div>
                <div class="description-box">
                    <?php echo nl2br(htmlspecialchars($order['description'])); ?>
                </div>
                
                <?php if ($order['design_image']): ?>
                    <div class="image-container">
                        <h4 style="margin-bottom: 15px; color: #333;">🖼️ صورة التصميم</h4>
                        <img src="../../<?php echo $order['design_image']; ?>" 
                             class="design-image" 
                             onclick="openModal('../../<?php echo $order['design_image']; ?>')"
                             title="اضغط للتكبير">
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- بطاقة معلومات الدفع -->
            <?php if ($order['has_payment']): ?>
                <div class="payment-info">
                    <div class="payment-details">
                        <div class="payment-icon">✅</div>
                        <div class="payment-text">
                            <h4>تم الدفع</h4>
                            <p>طريقة الدفع: <?php echo $order['payment_method']; ?></p>
                            <p>تاريخ الدفع: <?php echo date('Y-m-d', strtotime($order['payment_date'])); ?></p>
                        </div>
                    </div>
                </div>
            <?php elseif ($order['status'] == 'pending' && $order['tailor_price']): ?>
                <div class="payment-info unpaid">
                    <div class="payment-details">
                        <div class="payment-icon">⏳</div>
                        <div class="payment-text">
                            <h4>في انتظار الدفع</h4>
                            <p>المبلغ المطلوب: <?php echo number_format($order['tailor_price'], 0); ?> د.ج</p>
                        </div>
                    </div>
                    <a href="payment.php?order_id=<?php echo $order['order_id']; ?>" class="pay-now-btn">
                        💳 دفع الآن
                    </a>
                </div>
            <?php elseif ($order['status'] == 'pending' && !$order['tailor_price']): ?>
                <div class="payment-info pending">
                    <div class="payment-details">
                        <div class="payment-icon">⏳</div>
                        <div class="payment-text">
                            <h4>في انتظار تحديد السعر</h4>
                            <p>الخياط سيحدد السعر قريباً</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- أزرار الإجراءات -->
            <div class="action-buttons">
                <?php if ($order['status'] == 'pending' && !$order['has_payment'] && $order['tailor_price']): ?>
                    <a href="payment.php?order_id=<?php echo $order['order_id']; ?>" class="action-btn btn-primary">
                        💳 إتمام الدفع
                    </a>
                <?php endif; ?>
                
                <?php if ($order['status'] == 'completed'): ?>
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM review WHERE order_id = ?");
                    $stmt->execute([$order['order_id']]);
                    $review = $stmt->fetch();
                    ?>
                    <?php if (!$review): ?>
                        <a href="review_order.php?order_id=<?php echo $order['order_id']; ?>" class="action-btn btn-primary">
                            ⭐ تقييم الخياط
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                
                <a href="place_order.php?tailor_id=<?php echo $order['tailor_id']; ?>" class="action-btn btn-secondary">
                    🔄 طلب جديد من نفس الخياط
                </a>
            </div>
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
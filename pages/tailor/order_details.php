<?php
// pages/tailor/order_details.php
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/notifications.php';

if (!isLoggedIn() || !hasRole('tailor')) {
    redirect('../login.php');
}

$unread_count = getUnreadNotificationsCount($_SESSION['user_id']);

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// جلب تفاصيل الطلب مع معلومات العميل
$stmt = $pdo->prepare("
    SELECT o.*, 
           u.full_name as customer_name, 
           u.phone as customer_phone, 
           u.address, 
           u.email
    FROM `order` o
    JOIN customer c ON o.customer_id = c.customer_id
    JOIN user u ON c.user_id = u.user_id
    WHERE o.order_id = ? AND o.tailor_id = (
        SELECT tailor_id FROM tailor WHERE user_id = ?
    )
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['error'] = 'الطلب غير موجود';
    redirect('my_orders.php');
}

// جلب معلومات الدفع
$stmt = $pdo->prepare("SELECT * FROM payment WHERE order_id = ?");
$stmt->execute([$order_id]);
$payment = $stmt->fetch();

// جلب المقاسات
$measurements = $pdo->prepare("SELECT * FROM measurements WHERE order_id = ?");
$measurements->execute([$order_id]);
$measurements = $measurements->fetchAll();
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
        
        .customer-profile {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .customer-avatar {
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
        
        .customer-info h2 {
            color: #333;
            font-size: 1.8em;
            margin-bottom: 5px;
        }
        
        .customer-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .customer-meta-item {
            background: #f8f9fa;
            padding: 5px 15px;
            border-radius: 25px;
            color: #666;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
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
        
        .description-box {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            line-height: 1.8;
            color: #555;
            border-right: 4px solid #667eea;
        }
        
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
        
        .measurements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .measurement-item {
            background: white;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #e0e0e0;
        }
        
        .measurement-label {
            color: #667eea;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .measurement-value {
            color: #333;
            font-size: 1.2em;
            font-weight: bold;
        }
        
        .measurement-unit {
            color: #999;
            font-size: 0.8em;
        }
        
        .measurement-notes {
            background: #fff3cd;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            color: #856404;
            border-right: 4px solid #ffc107;
        }
        
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
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
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
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-right: 0; }
            .dashboard { flex-direction: column; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .customer-profile { flex-direction: column; text-align: center; }
            .customer-meta { justify-content: center; }
            .action-buttons { flex-direction: column; }
            .action-buttons a { text-align: center; }
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
                <li><a href="my_portfolio.php">📸 معرض أعمالي</a></li>

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
            
            <!-- بطاقة معلومات العميل -->
            <div class="info-card">
                <div class="info-card-title">
                    <h3>👤 معلومات العميل</h3>
                </div>
                <div class="customer-profile">
                    <div class="customer-avatar">
                        <?php echo mb_substr($order['customer_name'], 0, 1); ?>
                    </div>
                    <div class="customer-info">
                        <h2><?php echo htmlspecialchars($order['customer_name']); ?></h2>
                        <div class="customer-meta">
                            <?php if (!empty($order['customer_phone'])): ?>
                                <span class="customer-meta-item">📞 <?php echo htmlspecialchars($order['customer_phone']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($order['email'])): ?>
                                <span class="customer-meta-item">📧 <?php echo htmlspecialchars($order['email']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($order['address'])): ?>
                                <span class="customer-meta-item">📍 <?php echo htmlspecialchars($order['address']); ?></span>
                            <?php endif; ?>
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
                        <div class="info-label">السعر المقترح من الزبون</div>
                        <div class="info-value"><?php echo number_format($order['total_price'], 0); ?> د.ج</div>
                    </div>
                    
                    <?php if ($order['tailor_price']): ?>
                    <div class="info-item">
                        <div class="info-icon">💵</div>
                        <div class="info-label">السعر الذي حددته</div>
                        <div class="info-value"><?php echo number_format($order['tailor_price'], 0); ?> د.ج</div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <div class="info-icon">📅</div>
                        <div class="info-label">تاريخ التسليم المطلوب</div>
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
            
            <!-- بطاقة المقاسات -->
            <?php if (count($measurements) > 0): ?>
                <div class="info-card">
                    <div class="info-card-title">
                        <h3>📏 المقاسات</h3>
                        <span><?php echo count($measurements); ?></span>
                    </div>
                    
                    <?php foreach ($measurements as $m): ?>
                        <div style="background: #f8f9fa; border-radius: 15px; padding: 20px; margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap;">
                                <h4 style="color: #667eea; margin: 0;"><?php echo htmlspecialchars($m['measurement_type']); ?></h4>
                                <?php if (!empty($m['size'])): ?>
                                    <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 5px 20px; border-radius: 25px; font-weight: bold;"><?php echo $m['size']; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="measurements-grid">
                                <?php if ($m['chest']): ?>
                                <div class="measurement-item">
                                    <div class="measurement-label">الصدر</div>
                                    <div class="measurement-value"><?php echo $m['chest']; ?> <span class="measurement-unit">سم</span></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($m['waist']): ?>
                                <div class="measurement-item">
                                    <div class="measurement-label">الخصر</div>
                                    <div class="measurement-value"><?php echo $m['waist']; ?> <span class="measurement-unit">سم</span></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($m['hips']): ?>
                                <div class="measurement-item">
                                    <div class="measurement-label">الأرداف</div>
                                    <div class="measurement-value"><?php echo $m['hips']; ?> <span class="measurement-unit">سم</span></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($m['length']): ?>
                                <div class="measurement-item">
                                    <div class="measurement-label">الطول</div>
                                    <div class="measurement-value"><?php echo $m['length']; ?> <span class="measurement-unit">سم</span></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($m['shoulder']): ?>
                                <div class="measurement-item">
                                    <div class="measurement-label">الكتف</div>
                                    <div class="measurement-value"><?php echo $m['shoulder']; ?> <span class="measurement-unit">سم</span></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($m['sleeve']): ?>
                                <div class="measurement-item">
                                    <div class="measurement-label">الكم</div>
                                    <div class="measurement-value"><?php echo $m['sleeve']; ?> <span class="measurement-unit">سم</span></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($m['neck']): ?>
                                <div class="measurement-item">
                                    <div class="measurement-label">الرقبة</div>
                                    <div class="measurement-value"><?php echo $m['neck']; ?> <span class="measurement-unit">سم</span></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($m['notes'])): ?>
                                <div class="measurement-notes">
                                    <strong>📝 ملاحظات:</strong> <?php echo nl2br(htmlspecialchars($m['notes'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- بطاقة معلومات الدفع -->
            <?php if ($payment): ?>
                <div class="payment-info">
                    <div class="payment-details">
                        <div class="payment-icon">✅</div>
                        <div class="payment-text">
                            <h4>تم الدفع</h4>
                            <p>طريقة الدفع: <?php echo $payment['payment_method']; ?></p>
                            <p>تاريخ الدفع: <?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></p>
                            <p>المبلغ: <?php echo number_format($payment['amount'], 0); ?> د.ج</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="payment-info unpaid">
                    <div class="payment-details">
                        <div class="payment-icon">⏳</div>
                        <div class="payment-text">
                            <h4>لم يتم الدفع بعد</h4>
                            <p>الطلب في انتظار الدفع من الزبون</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- أزرار الإجراءات -->
            <div class="action-buttons">
                <a href="update_order.php?id=<?php echo $order['order_id']; ?>" class="action-btn btn-primary">
                    🔄 تحديث الحالة
                </a>
                
                <?php if (!empty($order['customer_phone'])): ?>
                    <a href="tel:<?php echo $order['customer_phone']; ?>" class="action-btn btn-success">
                        📞 الاتصال بالعميل
                    </a>
                <?php endif; ?>
                
                <?php if ($order['status'] == 'pending' && !$order['tailor_price']): ?>
                    <a href="view_requests.php" class="action-btn btn-secondary">
                        💰 تحديد السعر
                    </a>
                <?php endif; ?>
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
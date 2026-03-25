<?php
// pages/tailor/view_requests.php
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/notifications.php';

if (!isLoggedIn() || !hasRole('tailor')) {
    redirect('../login.php');
}

$unread_count = getUnreadNotificationsCount($_SESSION['user_id']);

$stmt = $pdo->prepare("SELECT tailor_id FROM tailor WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$tailor_id = $stmt->fetch()['tailor_id'];

// جلب الطلبات المعلقة (التي لم يحدد الخياط سعرها بعد)
$pending_orders = $pdo->prepare("
    SELECT o.*, u.full_name as customer_name, u.phone as customer_phone, c.customer_id
    FROM `order` o
    JOIN customer c ON o.customer_id = c.customer_id
    JOIN user u ON c.user_id = u.user_id
    WHERE o.tailor_id = ? AND o.status = 'pending' AND o.tailor_price IS NULL
    ORDER BY o.order_date DESC
");
$pending_orders->execute([$tailor_id]);
$pending_orders = $pending_orders->fetchAll();

if (isset($_GET['reject'])) {
    $order_id = intval($_GET['reject']);
    $stmt = $pdo->prepare("UPDATE `order` SET status = 'cancelled' WHERE order_id = ? AND tailor_id = ?");
    $stmt->execute([$order_id, $tailor_id]) ? $_SESSION['success'] = 'تم رفض الطلب' : $_SESSION['error'] = 'حدث خطأ';
    redirect('view_requests.php');
}

$success = $_SESSION['success'] ?? ''; 
$error = $_SESSION['error'] ?? ''; 
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الطلبات الجديدة - خياط</title>
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
            font-size: 2.2em;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-title span {
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 0.5em;
        }
        
        .stats-badge {
            background: white;
            padding: 12px 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stats-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }
        
        .stats-text {
            color: #666;
        }
        
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
        }
        
        .request-card {
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
            position: relative;
        }
        
        .request-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(102,126,234,0.2);
            border-color: #667eea;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            color: white;
            position: relative;
        }
        
        .customer-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .customer-avatar {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            border: 2px solid white;
        }
        
        .customer-details h3 {
            font-size: 1.3em;
            margin-bottom: 5px;
        }
        
        .customer-details p {
            opacity: 0.9;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .order-date {
            position: absolute;
            top: 15px;
            left: 20px;
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 0.9em;
            backdrop-filter: blur(5px);
        }
        
        .card-content {
            padding: 25px;
        }
        
        .order-id {
            display: inline-block;
            background: #f0f3ff;
            color: #667eea;
            padding: 5px 15px;
            border-radius: 25px;
            font-size: 0.9em;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .description-box {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            border-right: 4px solid #667eea;
        }
        
        .description-box p {
            color: #555;
            line-height: 1.8;
            margin: 0;
        }
        
        .image-section {
            margin: 20px 0;
            text-align: center;
        }
        
        .image-thumbnail {
            max-width: 100%;
            max-height: 200px;
            border-radius: 15px;
            cursor: pointer;
            transition: transform 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .image-thumbnail:hover {
            transform: scale(1.05);
        }
        
        .no-image {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            color: #999;
            text-align: center;
            font-size: 1.2em;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
        }
        
        .info-label {
            color: #666;
            font-size: 0.85em;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #333;
            font-size: 1.2em;
            font-weight: bold;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn-action {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 15px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-price {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
        }
        
        .btn-price:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40,167,69,0.4);
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(220,53,69,0.3);
        }
        
        .btn-reject:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(220,53,69,0.4);
        }
        
        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }
        
        .btn-view:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.4);
        }
        
        .empty-state {
            text-align: center;
            padding: 80px;
            background: white;
            border-radius: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .empty-icon {
            font-size: 6em;
            color: #667eea;
            margin-bottom: 25px;
            opacity: 0.5;
        }
        
        .empty-title {
            color: #333;
            font-size: 2em;
            margin-bottom: 15px;
        }
        
        .empty-text {
            color: #666;
            font-size: 1.2em;
            margin-bottom: 30px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 30px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-icon {
            font-size: 4em;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .modal h3 {
            color: #333;
            font-size: 1.8em;
            margin-bottom: 15px;
        }
        
        .modal p {
            color: #666;
            margin-bottom: 25px;
        }
        
        .modal-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            font-size: 1.2em;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .modal-input:focus {
            outline: none;
            border-color: #28a745;
        }
        
        .modal-buttons {
            display: flex;
            gap: 15px;
        }
        
        .modal-btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 15px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .modal-btn.confirm {
            background: #28a745;
            color: white;
        }
        
        .modal-btn.confirm:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .modal-btn.cancel {
            background: #6c757d;
            color: white;
        }
        
        .modal-btn.cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .image-modal {
            display: none;
            position: fixed;
            z-index: 2001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            align-items: center;
            justify-content: center;
        }
        
        .image-modal-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 10px;
        }
        
        .close-modal {
            position: absolute;
            top: 20px;
            left: 30px;
            color: white;
            font-size: 50px;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .close-modal:hover {
            color: #667eea;
        }
        
        .alert {
            padding: 20px 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            font-size: 1.1em;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        @media (max-width: 992px) {
            .requests-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-right: 0; }
            .dashboard { flex-direction: column; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .info-grid { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
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
                <li><a href="view_requests.php" class="active">📋 طلبات جديدة</a></li>
                <li><a href="my_orders.php">📦 طلباتي الحالية</a></li>
                <li><a href="add_portfolio.php">➕ إضافة عمل</a></li>
                <li><a href="my_portfolio.php">📸 معرض أعمالي</a></li>

                <li><a href="../logout.php" class="logout-link">🚪 تسجيل خروج</a></li>
            </ul>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <span>📋</span>
                    الطلبات الجديدة
                </div>
                <div class="stats-badge">
                    <span class="stats-number"><?php echo count($pending_orders); ?></span>
                    <span class="stats-text">طلب في انتظار الرد</span>
                </div>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (count($pending_orders) > 0): ?>
                <div class="requests-grid">
                    <?php foreach ($pending_orders as $order): ?>
                        <div class="request-card">
                            <!-- رأس البطاقة -->
                            <div class="card-header">
                                <div class="customer-info">
                                    <div class="customer-avatar">
                                        <?php echo mb_substr($order['customer_name'], 0, 1); ?>
                                    </div>
                                    <div class="customer-details">
                                        <h3><?php echo htmlspecialchars($order['customer_name']); ?></h3>
                                        <p>📞 <?php echo htmlspecialchars($order['customer_phone'] ?? 'غير متوفر'); ?></p>
                                    </div>
                                </div>
                                <div class="order-date">
                                    📅 <?php echo date('Y-m-d', strtotime($order['order_date'])); ?>
                                </div>
                            </div>
                            
                            <!-- محتوى البطاقة -->
                            <div class="card-content">
                                <div class="order-id">طلب #<?php echo $order['order_id']; ?></div>
                                
                                <!-- وصف التصميم -->
                                <div class="description-box">
                                    <p><?php echo nl2br(htmlspecialchars($order['description'])); ?></p>
                                </div>
                                
                                <!-- صورة التصميم -->
                                <?php if ($order['design_image']): ?>
                                    <div class="image-section">
                                        <img src="../../<?php echo $order['design_image']; ?>" 
                                             class="image-thumbnail" 
                                             onclick="openImageModal('../../<?php echo $order['design_image']; ?>')"
                                             title="اضغط للتكبير">
                                    </div>
                                <?php else: ?>
                                    <div class="no-image">
                                        📸 لا توجد صورة مرفقة
                                    </div>
                                <?php endif; ?>
                                
                                <!-- معلومات إضافية -->
                                <div class="info-grid">
                                    <div class="info-item">
                                        <div class="info-label">💰 السعر المقترح</div>
                                        <div class="info-value"><?php echo number_format($order['total_price'], 0); ?> د.ج</div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">📅 التسليم</div>
                                        <div class="info-value"><?php echo $order['delivery_date']; ?></div>
                                    </div>
                                </div>
                                
                                <!-- أزرار الإجراءات -->
                                <div class="action-buttons">
                                    <button onclick="showPriceModal(<?php echo $order['order_id']; ?>)" class="btn-action btn-price">
                                        <span>💰</span> تحديد السعر
                                    </button>
                                    
                                    <a href="?reject=<?php echo $order['order_id']; ?>" 
                                       class="btn-action btn-reject"
                                       onclick="return confirm('هل أنت متأكد من رفض هذا الطلب؟')">
                                        <span>❌</span> رفض
                                    </a>
                                    
                                    <a href="update_order.php?id=<?php echo $order['order_id']; ?>" class="btn-action btn-view">
                                        <span>🔍</span> عرض
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- حالة عدم وجود طلبات -->
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <h2 class="empty-title">لا توجد طلبات جديدة</h2>
                    <p class="empty-text">ستظهر هنا الطلبات الجديدة عندما يرسلها الزبائن</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- نافذة تحديد السعر -->
    <div id="priceModal" class="modal">
        <div class="modal-content">
            <div class="modal-icon">💰</div>
            <h3>تحديد السعر</h3>
            <p>أدخل السعر المناسب لهذا الطلب</p>
            <input type="number" id="priceAmount" class="modal-input" placeholder="السعر (د.ج)" min="0" step="100">
            <div class="modal-buttons">
                <button class="modal-btn confirm" onclick="submitPrice()">تأكيد</button>
                <button class="modal-btn cancel" onclick="closePriceModal()">إلغاء</button>
            </div>
        </div>
    </div>
    
    <!-- نافذة عرض الصور -->
    <div id="imageModal" class="image-modal" onclick="closeImageModal()">
        <span class="close-modal">&times;</span>
        <img class="image-modal-content" id="modalImage">
    </div>

    <script>
        let currentOrderId = 0;
        
        function showPriceModal(orderId) {
            currentOrderId = orderId;
            document.getElementById('priceModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closePriceModal() {
            document.getElementById('priceModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        function submitPrice() {
            const price = document.getElementById('priceAmount').value;
            if (!price || price <= 0) {
                alert('الرجاء إدخال سعر صحيح');
                return;
            }
            window.location.href = `set_price.php?order_id=${currentOrderId}&price=${price}`;
        }
        
        function openImageModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePriceModal();
                closeImageModal();
            }
        });
        
        window.onclick = function(event) {
            const priceModal = document.getElementById('priceModal');
            if (event.target == priceModal) {
                closePriceModal();
            }
        }
    </script>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
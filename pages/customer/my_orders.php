<?php
// pages/customer/my_orders.php
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/notifications.php';

if (!isLoggedIn() || !hasRole('customer')) {
    redirect('../login.php');
}

$unread_count = getUnreadNotificationsCount($_SESSION['user_id']);

$stmt = $pdo->prepare("SELECT customer_id FROM customer WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customer_id = $stmt->fetch()['customer_id'];

// جلب جميع طلبات الزبون مع الترتيب المطلوب
$orders = $pdo->prepare("
    SELECT o.*, u.full_name as tailor_name, t.tailor_id, t.rating as tailor_rating,
           r.review_id, r.rating as review_rating, r.comment as review_comment, r.review_date,
           (SELECT COUNT(*) FROM payment WHERE order_id = o.order_id) as has_payment,
           (SELECT payment_method FROM payment WHERE order_id = o.order_id) as payment_method
    FROM `order` o
    JOIN tailor t ON o.tailor_id = t.tailor_id
    JOIN user u ON t.user_id = u.user_id
    LEFT JOIN review r ON o.order_id = r.order_id
    WHERE o.customer_id = ?
    ORDER BY 
        CASE 
            WHEN o.status = 'pending' THEN 1
            WHEN o.status = 'in_progress' THEN 2
            WHEN o.status = 'completed' THEN 3
            ELSE 4
        END,
        o.order_date DESC
");
$orders->execute([$customer_id]);
$orders = $orders->fetchAll();

// إحصائيات سريعة
$pending_count = 0;
$in_progress_count = 0;
$completed_count = 0;
$total_count = count($orders);

foreach ($orders as $order) {
    if ($order['status'] == 'pending') $pending_count++;
    elseif ($order['status'] == 'in_progress') $in_progress_count++;
    elseif ($order['status'] == 'completed') $completed_count++;
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
    <title>طلباتي - منصة خياط</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tahoma', Arial, sans-serif;
            background: #f5f5f5;
        }
        
        .dashboard { display: flex; min-height: 100vh; position: relative; }
        .sidebar { width: 280px; background: #2c3e50; color: white; position: fixed; height: 100vh; overflow-y: auto; right: 0; top: 0; box-shadow: -5px 0 10px rgba(0,0,0,0.1); z-index: 100; }
        .main-content { flex: 1; margin-right: 280px; padding: 30px; background: #f5f5f5; min-height: 100vh; }
        
        .sidebar::-webkit-scrollbar { width: 6px; }
        .sidebar::-webkit-scrollbar-track { background: #34495e; }
        .sidebar::-webkit-scrollbar-thumb { background: #667eea; border-radius: 3px; }
        
        .user-info { text-align: center; padding: 30px 20px; background: rgba(0,0,0,0.2); border-bottom: 1px solid #34495e; }
        .user-info h3 { color: white; margin-bottom: 5px; font-size: 1.2em; }
        .user-info .role { color: #bdc3c7; font-size: 0.9em; background: rgba(255,255,255,0.1); display: inline-block; padding: 5px 15px; border-radius: 20px; margin-top: 8px; }
        
        .nav-menu { list-style: none; padding: 20px 0; }
        .nav-menu li { margin-bottom: 5px; }
        .nav-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #ecf0f1; text-decoration: none; transition: all 0.3s ease; border-right: 3px solid transparent; font-size: 0.95em; }
        .nav-menu a:hover { background: rgba(102,126,234,0.2); border-right-color: #667eea; padding-right: 25px; }
        .nav-menu a.active { background: linear-gradient(90deg, #667eea 0%, transparent 100%); border-right-color: #ffc107; font-weight: bold; }
        
        /* رأس الصفحة */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-title {
            color: #333;
            font-size: 1.8em;
            font-weight: bold;
        }
        
        .new-order-btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .new-order-btn:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        
        /* إحصائيات */
        .stats-row {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .stat-box {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
            min-width: 100px;
            flex: 1;
        }
        
        .stat-number {
            font-size: 1.8em;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.85em;
            margin-top: 5px;
        }
        
        /* تبويبات */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 10px;
        }
        
        .tab {
            padding: 8px 20px;
            background: #f0f0f0;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9em;
            color: #555;
        }
        
        .tab:hover {
            background: #e0e0e0;
        }
        
        .tab.active {
            background: #667eea;
            color: white;
        }
        
        .tab .count {
            background: rgba(0,0,0,0.1);
            padding: 2px 8px;
            border-radius: 15px;
            margin-right: 5px;
        }
        
        .tab.active .count {
            background: rgba(255,255,255,0.2);
        }
        
        /* قائمة الطلبات */
        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        /* بطاقة الطلب */
        .order-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-right: 4px solid transparent;
        }
        
        .order-item:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateX(-3px);
        }
        
        .order-item.pending {
            border-right-color: #ffc107;
        }
        
        .order-item.in_progress {
            border-right-color: #17a2b8;
        }
        
        .order-item.completed {
            border-right-color: #28a745;
        }
        
        /* رأس البطاقة */
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .order-number {
            background: #f0f0f0;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            color: #667eea;
            font-weight: 500;
        }
        
        .tailor-name {
            font-weight: bold;
            color: #333;
        }
        
        .order-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-in_progress {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        /* محتوى البطاقة */
        .order-details {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .order-image {
            width: 80px;
            height: 80px;
            background: #f5f5f5;
            border-radius: 8px;
            object-fit: cover;
            cursor: pointer;
        }
        
        .order-info {
            flex: 1;
        }
        
        .order-description {
            color: #555;
            margin-bottom: 10px;
            line-height: 1.5;
            font-size: 0.9em;
        }
        
        .order-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 0.85em;
            color: #888;
        }
        
        /* معلومات الدفع */
        .payment-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 0.9em;
        }
        
        .payment-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .payment-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .payment-unpaid {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* التقييم */
        .review-box {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            border-right: 3px solid #ffc107;
        }
        
        .review-stars {
            color: #ffc107;
            margin-bottom: 5px;
        }
        
        .review-comment {
            color: #555;
            font-size: 0.9em;
        }
        
        /* أزرار */
        .order-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 6px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85em;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .btn-view {
            background: #667eea;
            color: white;
        }
        
        .btn-view:hover {
            background: #5a67d8;
        }
        
        .btn-pay {
            background: #28a745;
            color: white;
        }
        
        .btn-pay:hover {
            background: #218838;
        }
        
        .btn-review {
            background: #ffc107;
            color: #333;
        }
        
        .btn-review:hover {
            background: #e0a800;
        }
        
        .btn-reorder {
            background: #6c757d;
            color: white;
        }
        
        .btn-reorder:hover {
            background: #5a6268;
        }
        
        .btn-disabled {
            background: #e0e0e0;
            color: #999;
            cursor: default;
        }
        
        /* حالة فارغة */
        .empty-state {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 12px;
        }
        
        .empty-icon {
            font-size: 4em;
            color: #ccc;
            margin-bottom: 15px;
        }
        
        .empty-title {
            color: #666;
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        
        .empty-btn {
            margin-top: 15px;
            background: #667eea;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-right: 0; }
            .dashboard { flex-direction: column; }
            .stats-row { flex-direction: column; }
            .order-details { flex-direction: column; align-items: center; text-align: center; }
            .order-image { width: 100px; height: 100px; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- الشريط الجانبي -->
        <div class="sidebar">
            <div class="user-info">
                <div style="width:60px;height:60px;background:rgba(255,255,255,0.2);border-radius:50%;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:1.8em;">👤</div>
                <h3><?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
                <p class="role">زبون</p>
                
                <!-- أيقونة الإشعارات -->
                <div style="margin-top: 12px;">
                    <a href="../notifications.php" style="color: white; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 5px;">
                        <span>🔔</span>
                        <?php if ($unread_count > 0): ?>
                            <span style="background: #dc3545; border-radius: 50%; padding: 2px 8px; font-size: 0.7em;"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
            
            <ul class="nav-menu">
                <li><a href="../dashboard.php">🏠 الرئيسية</a></li>
                <li><a href="browse_tailors.php">🔍 البحث عن خياط</a></li>
                <li><a href="my_orders.php" class="active">📦 طلباتي</a></li>
                <li><a href="place_order.php">➕ طلب جديد</a></li>
  
                <li><a href="../logout.php" class="logout-link">🚪 تسجيل خروج</a></li>
            </ul>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">📦 طلباتي</h1>
                <a href="place_order.php" class="new-order-btn">➕ طلب جديد</a>
            </div>
            
            <?php if ($success): ?>
                <div style="background:#d4edda; color:#155724; padding:12px; border-radius:8px; margin-bottom:20px;"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div style="background:#f8d7da; color:#721c24; padding:12px; border-radius:8px; margin-bottom:20px;"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- إحصائيات -->
            <div class="stats-row">
                <div class="stat-box"><div class="stat-number"><?php echo $total_count; ?></div><div class="stat-label">إجمالي الطلبات</div></div>
                <div class="stat-box"><div class="stat-number"><?php echo $pending_count; ?></div><div class="stat-label">⏳ قيد الانتظار</div></div>
                <div class="stat-box"><div class="stat-number"><?php echo $in_progress_count; ?></div><div class="stat-label">🔨 قيد التنفيذ</div></div>
                <div class="stat-box"><div class="stat-number"><?php echo $completed_count; ?></div><div class="stat-label">✅ مكتملة</div></div>
            </div>
            
            <!-- تبويبات -->
            <div class="tabs">
                <div class="tab active" onclick="filterOrders('all')">الكل <span class="count"><?php echo $total_count; ?></span></div>
                <div class="tab" onclick="filterOrders('pending')">⏳ قيد الانتظار <span class="count"><?php echo $pending_count; ?></span></div>
                <div class="tab" onclick="filterOrders('in_progress')">🔨 قيد التنفيذ <span class="count"><?php echo $in_progress_count; ?></span></div>
                <div class="tab" onclick="filterOrders('completed')">✅ مكتملة <span class="count"><?php echo $completed_count; ?></span></div>
            </div>
            
            <!-- قائمة الطلبات -->
            <?php if (count($orders) > 0): ?>
                <div class="orders-list" id="ordersList">
                    <?php foreach ($orders as $order): ?>
                        <div class="order-item <?php echo $order['status']; ?>" data-status="<?php echo $order['status']; ?>">
                            <!-- رأس البطاقة -->
                            <div class="order-header">
                                <span class="order-number">#<?php echo $order['order_id']; ?></span>
                                <span class="tailor-name">👤 <?php echo htmlspecialchars($order['tailor_name']); ?></span>
                                <span class="order-status status-<?php echo $order['status']; ?>">
                                    <?php 
                                    if($order['status'] == 'pending') echo '⏳ قيد الانتظار';
                                    elseif($order['status'] == 'in_progress') echo '🔨 قيد التنفيذ';
                                    elseif($order['status'] == 'completed') echo '✅ مكتمل';
                                    ?>
                                </span>
                            </div>
                            
                            <!-- محتوى الطلب -->
                            <div class="order-details">
                                <?php if ($order['design_image']): ?>
                                    <img src="../../<?php echo $order['design_image']; ?>" class="order-image" onclick="openModal('../../<?php echo $order['design_image']; ?>')">
                                <?php else: ?>
                                    <div class="order-image" style="background:#f0f0f0; display:flex; align-items:center; justify-content:center;">📸</div>
                                <?php endif; ?>
                                
                                <div class="order-info">
                                    <div class="order-description"><?php echo nl2br(htmlspecialchars(mb_substr($order['description'], 0, 120))); ?></div>
                                    <div class="order-meta">
                                        <span>💰 <?php echo number_format($order['total_price'], 0); ?> د.ج</span>
                                        <span>📅 <?php echo date('Y-m-d', strtotime($order['order_date'])); ?></span>
                                        <span>📅 تسليم: <?php echo $order['delivery_date']; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- معلومات الدفع -->
                            <?php if ($order['tailor_price']): ?>
                                <div class="payment-info">
                                    <span>💰 السعر المحدد: <strong><?php echo number_format($order['tailor_price'], 0); ?> د.ج</strong></span>
                                    <?php if ($order['has_payment']): ?>
                                        <span class="payment-badge payment-paid">✅ مدفوع - <?php echo $order['payment_method']; ?></span>
                                    <?php else: ?>
                                        <span class="payment-badge payment-unpaid">⏳ غير مدفوع</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- التقييم للطلبات المكتملة -->
                            <?php if($order['status'] == 'completed'): ?>
                                <div class="review-box">
                                    <?php if($order['review_id']): ?>
                                        <div class="review-stars"><?php echo str_repeat('★', $order['review_rating']); ?></div>
                                        <div class="review-comment">"<?php echo htmlspecialchars($order['review_comment']); ?>"</div>
                                    <?php else: ?>
                                        <a href="review_order.php?order_id=<?php echo $order['order_id']; ?>" class="btn-review btn-sm">⭐ تقييم الخياط</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- أزرار الإجراءات -->
                            <div class="order-actions">
                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn-sm btn-view">🔍 عرض التفاصيل</a>
                                
                                <?php if ($order['status'] == 'pending' && !$order['has_payment'] && $order['tailor_price']): ?>
                                    <a href="payment.php?order_id=<?php echo $order['order_id']; ?>" class="btn-sm btn-pay">💳 دفع الآن</a>
                                <?php endif; ?>
                                
                                <?php if($order['status'] == 'completed'): ?>
                                    <a href="place_order.php?tailor_id=<?php echo $order['tailor_id']; ?>" class="btn-sm btn-reorder">🔄 طلب مرة أخرى</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📦</div>
                    <h3 class="empty-title">لا توجد طلبات بعد</h3>
                    <p>ابدأ بطلب تصميمك الأول</p>
                    <a href="browse_tailors.php" class="empty-btn">🔍 تصفح الخياطين</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- نافذة عرض الصور -->
    <div id="imageModal" class="modal" style="display:none; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.9);" onclick="closeModal()">
        <span style="position:absolute; top:20px; left:30px; color:white; font-size:40px; cursor:pointer;">&times;</span>
        <img style="max-width:90%; max-height:90%; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); border-radius:10px;" id="modalImage">
    </div>
    
    <script src="../../assets/js/script.js"></script>
    <script>
        function filterOrders(status) {
            const cards = document.querySelectorAll('.order-item');
            const tabs = document.querySelectorAll('.tab');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            if (status === 'all') tabs[0].classList.add('active');
            else if (status === 'pending') tabs[1].classList.add('active');
            else if (status === 'in_progress') tabs[2].classList.add('active');
            else if (status === 'completed') tabs[3].classList.add('active');
            
            cards.forEach(card => {
                if (status === 'all' || card.dataset.status === status) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
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
<?php
// pages/tailor/my_orders.php
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

$tailor_stats = $pdo->prepare("SELECT experience_points, rating FROM tailor WHERE tailor_id = ?");
$tailor_stats->execute([$tailor_id]);
$tailor_stats = $tailor_stats->fetch();

// جلب الطلبات التي تم تحديد سعرها أو مدفوعة أو قيد التنفيذ
$orders = $pdo->prepare("
    SELECT o.*, u.full_name as customer_name, u.phone as customer_phone,
           (SELECT COUNT(*) FROM payment WHERE order_id = o.order_id) as has_payment,
           (SELECT payment_method FROM payment WHERE order_id = o.order_id) as payment_method
    FROM `order` o
    JOIN customer c ON o.customer_id = c.customer_id
    JOIN user u ON c.user_id = u.user_id
    WHERE o.tailor_id = ? 
    AND (
        (o.price_status = 'quoted' AND o.status = 'pending') OR
        (o.status IN ('in_progress', 'completed'))
    )
    ORDER BY 
        CASE 
            WHEN o.status = 'in_progress' THEN 1
            WHEN o.status = 'pending' AND (SELECT COUNT(*) FROM payment WHERE order_id = o.order_id) > 0 THEN 2
            WHEN o.status = 'completed' THEN 3
            WHEN o.status = 'pending' THEN 4
            ELSE 5
        END,
        o.order_date DESC
");
$orders->execute([$tailor_id]);
$orders = $orders->fetchAll();

// إحصائيات منفصلة
$pending_count = 0;
$in_progress_count = 0;
$completed_count = 0;

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
    <title>طلباتي - خياط</title>
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
        
        .customer-name {
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
        
        /* معلومات الاتصال */
        .customer-contact {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 0.85em;
            color: #667eea;
        }
        
        /* معلومات السعر */
        .price-info {
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
        
        /* وصف الطلب */
        .order-description {
            color: #555;
            margin-bottom: 15px;
            line-height: 1.5;
            font-size: 0.9em;
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
        }
        
        /* معلومات إضافية */
        .order-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            font-size: 0.85em;
            color: #888;
        }
        
        /* أزرار */
        .order-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 5px;
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
        
        .btn-start {
            background: #28a745;
            color: white;
        }
        
        .btn-start:hover {
            background: #218838;
        }
        
        .btn-update {
            background: #667eea;
            color: white;
        }
        
        .btn-update:hover {
            background: #5a67d8;
        }
        
        .btn-view {
            background: #6c757d;
            color: white;
        }
        
        .btn-view:hover {
            background: #5a6268;
        }
        
        .btn-disabled {
            background: #e0e0e0;
            color: #999;
            cursor: default;
        }
        
        .completed-mark {
            background: #28a745;
            color: white;
            padding: 6px 15px;
            border-radius: 6px;
            font-size: 0.85em;
            display: inline-block;
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
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- الشريط الجانبي -->
        <div class="sidebar">
            <div class="user-info">
                <div style="width:60px;height:60px;background:rgba(255,255,255,0.2);border-radius:50%;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:1.8em;">✂️</div>
                <h3><?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
                <p class="role">خياط</p>
                
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
                <li><a href="view_requests.php">📋 طلبات جديدة</a></li>
                <li><a href="my_orders.php" class="active">📦 طلباتي</a></li>
                <li><a href="add_portfolio.php">➕ إضافة عمل</a></li>
                <li><a href="my_portfolio.php">📸 معرض أعمالي</a></li>
        
                <li><a href="../logout.php" class="logout-link">🚪 تسجيل خروج</a></li>
            </ul>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">📦 طلباتي</h1>
            </div>
            
            <?php if ($success): ?>
                <div style="background:#d4edda; color:#155724; padding:12px; border-radius:8px; margin-bottom:20px;"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div style="background:#f8d7da; color:#721c24; padding:12px; border-radius:8px; margin-bottom:20px;"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- إحصائيات -->
            <div class="stats-row">
                <div class="stat-box"><div class="stat-number"><?php echo $tailor_stats['experience_points'] ?? 0; ?></div><div class="stat-label">💎 نقاط الخبرة</div></div>
                <div class="stat-box"><div class="stat-number"><?php echo number_format($tailor_stats['rating'] ?? 0, 1); ?></div><div class="stat-label">⭐ التقييم العام</div></div>
                <div class="stat-box"><div class="stat-number"><?php echo $in_progress_count; ?></div><div class="stat-label">🔨 قيد التنفيذ</div></div>
                <div class="stat-box"><div class="stat-number"><?php echo $pending_count; ?></div><div class="stat-label">⏳ في انتظار الدفع</div></div>
                <div class="stat-box"><div class="stat-number"><?php echo $completed_count; ?></div><div class="stat-label">✅ مكتملة</div></div>
            </div>
            
            <!-- تبويبات -->
            <div class="tabs">
                <div class="tab active" onclick="filterOrders('all')">الكل <span class="count"><?php echo count($orders); ?></span></div>
                <div class="tab" onclick="filterOrders('pending')">⏳ في انتظار الدفع <span class="count"><?php echo $pending_count; ?></span></div>
                <div class="tab" onclick="filterOrders('in_progress')">🔨 قيد التنفيذ <span class="count"><?php echo $in_progress_count; ?></span></div>
                <div class="tab" onclick="filterOrders('completed')">✅ مكتملة <span class="count"><?php echo $completed_count; ?></span></div>
            </div>
            
            <!-- قائمة الطلبات -->
            <?php if (count($orders) > 0): ?>
                <div class="orders-list" id="ordersList">
                    <?php foreach ($orders as $order): 
                        $stmt = $pdo->prepare("SELECT * FROM payment WHERE order_id = ?");
                        $stmt->execute([$order['order_id']]);
                        $payment = $stmt->fetch();
                    ?>
                        <div class="order-item <?php echo $order['status']; ?>" data-status="<?php echo $order['status']; ?>">
                            <!-- رأس البطاقة -->
                            <div class="order-header">
                                <span class="order-number">#<?php echo $order['order_id']; ?></span>
                                <span class="customer-name">👤 <?php echo htmlspecialchars($order['customer_name']); ?></span>
                                <span class="order-status status-<?php echo $order['status']; ?>">
                                    <?php 
                                    if($order['status']=='pending') echo '⏳ في انتظار الدفع';
                                    elseif($order['status']=='in_progress') echo '🔨 قيد التنفيذ';
                                    elseif($order['status']=='completed') echo '✅ مكتمل';
                                    ?>
                                </span>
                            </div>
                            
                            <!-- معلومات الاتصال -->
                            <div class="customer-contact">
                                📞 <?php echo htmlspecialchars($order['customer_phone'] ?? 'غير متوفر'); ?>
                            </div>
                            
                            <!-- معلومات السعر والدفع -->
                            <div class="price-info">
                                <span>💰 السعر المحدد: <strong><?php echo number_format($order['tailor_price'], 0); ?> د.ج</strong></span>
                                <?php if ($payment): ?>
                                    <span class="payment-badge payment-paid">✅ مدفوع - <?php echo $payment['payment_method']; ?></span>
                                <?php else: ?>
                                    <span class="payment-badge payment-unpaid">⏳ غير مدفوع</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- وصف الطلب -->
                            <div class="order-description">
                                <?php echo nl2br(htmlspecialchars(mb_substr($order['description'], 0, 150))); ?>
                                <?php if (strlen($order['description']) > 150): ?>...<?php endif; ?>
                            </div>
                            
                            <!-- معلومات إضافية -->
                            <div class="order-meta">
                                <span>📅 تاريخ الطلب: <?php echo date('Y-m-d', strtotime($order['order_date'])); ?></span>
                                <span>📅 تاريخ التسليم: <?php echo $order['delivery_date']; ?></span>
                                <?php if ($order['design_image']): ?>
                                    <span><a href="../../<?php echo $order['design_image']; ?>" target="_blank" style="color:#667eea;">🖼️ عرض الصورة</a></span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- أزرار الإجراءات -->
                            <div class="order-actions">
                                <?php if ($order['status'] == 'pending' && $payment): ?>
                                    <a href="update_order.php?id=<?php echo $order['order_id']; ?>" class="btn-sm btn-start">🚀 بدء العمل</a>
                                <?php elseif ($order['status'] == 'in_progress'): ?>
                                    <a href="update_order.php?id=<?php echo $order['order_id']; ?>" class="btn-sm btn-update">🔄 تحديث الحالة</a>
                                <?php elseif ($order['status'] == 'pending' && !$payment): ?>
                                    <span class="btn-sm btn-disabled">⏳ في انتظار الدفع</span>
                                <?php elseif ($order['status'] == 'completed'): ?>
                                    <span class="completed-mark">✅ تم التسليم</span>
                                <?php endif; ?>
                                
                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn-sm btn-view">🔍 تفاصيل</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <h3 class="empty-title">لا توجد طلبات</h3>
                    <p>عندما تقبل طلبات جديدة، ستظهر هنا</p>
                    <a href="view_requests.php" class="empty-btn">📋 عرض الطلبات الجديدة</a>
                </div>
            <?php endif; ?>
        </div>
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
    </script>
        <script src="../../assets/js/script.js"></script>

</body>
</html>
<?php
// pages/tailor/update_order.php
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/notifications.php';

if (!isLoggedIn() || !hasRole('tailor')) {
    redirect('../login.php');
}

$unread_count = getUnreadNotificationsCount($_SESSION['user_id']);

$error = ''; 
$success = '';
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $pdo->prepare("
    SELECT o.*, c.customer_id, t.tailor_id 
    FROM `order` o
    JOIN customer c ON o.customer_id = c.customer_id
    JOIN tailor t ON o.tailor_id = t.tailor_id
    WHERE o.order_id = ? AND t.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['error'] = 'الطلب غير موجود';
    redirect('my_orders.php');
}

// جلب المقاسات
$stmt = $pdo->prepare("SELECT * FROM measurements WHERE order_id = ?");
$stmt->execute([$order_id]);
$measurements = $stmt->fetchAll();

// التحقق من الدفع
$stmt = $pdo->prepare("SELECT COUNT(*) as has_payment FROM payment WHERE order_id = ?");
$stmt->execute([$order_id]);
$has_payment = $stmt->fetch()['has_payment'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = $_POST['status'];
    
    try {
        $pdo->beginTransaction();
        
        // التحقق من الدفع قبل بدء العمل
        if ($new_status === 'in_progress') {
            if (!$has_payment) {
                $_SESSION['error'] = 'لا يمكن بدء العمل قبل الدفع';
                $pdo->rollBack();
                redirect("update_order.php?id=$order_id");
            }
        }
        
        // التحقق من إمكانية التحديث
        $allowed_transitions = [
            'pending' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => []
        ];
        
        if (!in_array($new_status, $allowed_transitions[$order['status']] ?? [])) {
            $_SESSION['error'] = 'لا يمكن تحديث الحالة بهذه الطريقة';
            $pdo->rollBack();
            redirect("update_order.php?id=$order_id");
        }
        
        $stmt = $pdo->prepare("UPDATE `order` SET status = ? WHERE order_id = ?");
        $stmt->execute([$new_status, $order_id]);
        
        if ($new_status === 'completed') {
            $stmt = $pdo->prepare("UPDATE tailor SET experience_points = experience_points + 10 WHERE tailor_id = ?");
            $stmt->execute([$order['tailor_id']]);
            $_SESSION['success'] = "تم إكمال الطلب! +10 نقاط خبرة";
        } else {
            $_SESSION['success'] = "تم تحديث الحالة إلى " . (
                $new_status == 'in_progress' ? 'قيد التنفيذ' : 
                ($new_status == 'cancelled' ? 'ملغي' : $new_status)
            );
        }
        
        // جلب user_id الخاص بالزبون
        $stmt = $pdo->prepare("
            SELECT c.user_id 
            FROM `order` o
            JOIN customer c ON o.customer_id = c.customer_id
            WHERE o.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $customer = $stmt->fetch();
        
        // ترجمة الحالة
        $status_text = '';
        switch ($new_status) {
            case 'in_progress': $status_text = 'قيد التنفيذ'; break;
            case 'completed': $status_text = 'مكتمل'; break;
            case 'cancelled': $status_text = 'ملغي'; break;
        }
        
        // إضافة إشعار للزبون
        addNotification(
            $customer['user_id'],
            'status',
            '🔄 تحديث حالة الطلب',
            'تم تحديث حالة طلبك #' . $order_id . ' إلى ' . $status_text,
            'customer/my_orders.php'
        );
        
        $pdo->commit();
        redirect('my_orders.php');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "حدث خطأ: " . $e->getMessage();
    }
}

// جلب آخر حالة للطلب
$current_order = $pdo->prepare("SELECT * FROM `order` WHERE order_id = ?");
$current_order->execute([$order_id]);
$current_order = $current_order->fetch();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحديث الطلب #<?php echo $order_id; ?></title>
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
        
        .update-container { max-width: 1000px; margin: 0 auto; }
        
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
        
        .order-number {
            background: #667eea;
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 0.6em;
        }
        
        .back-btn {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 12px 25px;
            border-radius: 12px;
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
        
        .info-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .card-title h3 {
            color: #333;
            font-size: 1.4em;
        }
        
        .card-title span {
            background: #667eea;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8em;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
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
        
        .status-badge {
            display: inline-block;
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1.1em;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffeeba;
        }
        
        .status-in_progress {
            background: #cce5ff;
            color: #004085;
            border: 2px solid #b8daff;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .description-box {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            border-right: 5px solid #667eea;
        }
        
        .description-box p {
            color: #555;
            line-height: 1.8;
            font-size: 1.1em;
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
        
        .measurements-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin: 25px 0;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .measurements-section h3 {
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .measurement-card {
            background: white;
            border: 2px solid #f0f0f0;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .measurement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .measurement-type {
            font-size: 1.3em;
            color: #667eea;
            font-weight: bold;
        }
        
        .size-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 25px;
            border-radius: 30px;
            font-weight: bold;
            font-size: 1.2em;
        }
        
        .measurement-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }
        
        .measurement-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }
        
        .measurement-item .label {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 8px;
        }
        
        .measurement-item .value {
            color: #333;
            font-size: 1.5em;
            font-weight: bold;
        }
        
        .measurement-item .unit {
            color: #999;
            font-size: 0.8em;
        }
        
        .measurement-notes {
            background: #fff3cd;
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            color: #856404;
            border-right: 4px solid #ffc107;
        }
        
        .payment-card {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            border-radius: 15px;
            padding: 20px;
            color: white;
            display: flex;
            align-items: center;
            gap: 20px;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
        }
        
        .payment-card.unpaid {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            box-shadow: 0 5px 15px rgba(220,53,69,0.3);
        }
        
        .payment-icon {
            font-size: 3em;
        }
        
        .payment-info h4 {
            font-size: 1.2em;
            margin-bottom: 5px;
        }
        
        .payment-info p {
            opacity: 0.9;
        }
        
        .status-buttons {
            display: flex;
            gap: 20px;
            margin: 30px 0;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .status-btn {
            flex: 1;
            min-width: 200px;
            padding: 20px 30px;
            border: none;
            border-radius: 15px;
            font-size: 1.3em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-start {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
        }
        
        .btn-start:hover:not(:disabled) {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(40,167,69,0.4);
        }
        
        .btn-complete {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }
        
        .btn-complete:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(23,162,184,0.4);
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        
        .btn-cancel:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(220,53,69,0.4);
        }
        
        .btn-disabled {
            background: #6c757d;
            color: white;
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .btn-disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .alert {
            padding: 20px 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            text-align: center;
            font-size: 1.1em;
            font-weight: 500;
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
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffeeba;
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
            padding: 30px;
            border-radius: 20px;
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        
        .modal-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            justify-content: center;
        }
        
        .modal-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .modal-btn.confirm {
            background: #28a745;
            color: white;
        }
        
        .modal-btn.cancel {
            background: #6c757d;
            color: white;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-right: 0; }
            .dashboard { flex-direction: column; }
            .status-buttons { flex-direction: column; }
            .status-btn { min-width: auto; }
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
            <div class="update-container">
                <div class="page-header">
                    <div class="page-title">
                        <span>🔄</span>
                        تحديث الطلب
                        <span class="order-number">#<?php echo $order_id; ?></span>
                    </div>
                    <a href="my_orders.php" class="back-btn">
                        <span>←</span> العودة للطلبات
                    </a>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <!-- حالة الطلب الحالية -->
                <div class="info-card">
                    <div class="card-title">
                        <h3>📊 الحالة الحالية</h3>
                    </div>
                    <div style="text-align: center; padding: 20px;">
                        <span class="status-badge status-<?php echo $current_order['status']; ?>">
                            <?php 
                            $status_map = [
                                'pending' => '⏳ قيد الانتظار',
                                'in_progress' => '🔨 قيد التنفيذ',
                                'completed' => '✅ مكتمل',
                                'cancelled' => '❌ ملغي'
                            ];
                            echo $status_map[$current_order['status']] ?? $current_order['status'];
                            ?>
                        </span>
                    </div>
                </div>
                
                <!-- معلومات الطلب -->
                <div class="info-card">
                    <div class="card-title">
                        <h3>📦 معلومات الطلب</h3>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon">📅</div>
                            <div class="info-label">تاريخ الطلب</div>
                            <div class="info-value"><?php echo date('Y-m-d', strtotime($current_order['order_date'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">📅</div>
                            <div class="info-label">تاريخ التسليم</div>
                            <div class="info-value"><?php echo $current_order['delivery_date']; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">💰</div>
                            <div class="info-label">السعر المقترح</div>
                            <div class="info-value"><?php echo number_format($current_order['total_price'], 0); ?> د.ج</div>
                        </div>
                        <?php if ($current_order['tailor_price']): ?>
                        <div class="info-item">
                            <div class="info-icon">💰</div>
                            <div class="info-label">السعر المحدد</div>
                            <div class="info-value"><?php echo number_format($current_order['tailor_price'], 0); ?> د.ج</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- حالة الدفع -->
                <div class="info-card">
                    <div class="card-title">
                        <h3>💰 حالة الدفع</h3>
                    </div>
                    
                    <?php if ($has_payment): ?>
                        <?php
                        $stmt = $pdo->prepare("SELECT * FROM payment WHERE order_id = ?");
                        $stmt->execute([$order_id]);
                        $payment = $stmt->fetch();
                        ?>
                        <div class="payment-card">
                            <div class="payment-icon">✅</div>
                            <div class="payment-info">
                                <h4>تم الدفع</h4>
                                <p>طريقة الدفع: <?php echo $payment['payment_method']; ?></p>
                                <p>تاريخ الدفع: <?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="payment-card unpaid">
                            <div class="payment-icon">⏳</div>
                            <div class="payment-info">
                                <h4>لم يتم الدفع بعد</h4>
                                <p>الزبون لم يقم بالدفع بعد</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- وصف التصميم -->
                <div class="info-card">
                    <div class="card-title">
                        <h3>📝 وصف التصميم</h3>
                    </div>
                    
                    <div class="description-box">
                        <p><?php echo nl2br(htmlspecialchars($current_order['description'])); ?></p>
                    </div>
                    
                    <?php if ($current_order['design_image']): ?>
                        <div class="image-container">
                            <h4 style="margin-bottom: 15px;">🖼️ صورة التصميم</h4>
                            <img src="../../<?php echo $current_order['design_image']; ?>" 
                                 class="design-image" 
                                 onclick="window.open('../../<?php echo $current_order['design_image']; ?>', '_blank')"
                                 title="اضغط للتكبير">
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- المقاسات -->
                <?php if (count($measurements) > 0): ?>
                    <div class="measurements-section">
                        <h3>📏 المقاسات</h3>
                        
                        <?php foreach ($measurements as $m): ?>
                            <div class="measurement-card">
                                <div class="measurement-header">
                                    <span class="measurement-type"><?php echo htmlspecialchars($m['measurement_type']); ?></span>
                                    <?php if (!empty($m['size'])): ?>
                                        <span class="size-badge"><?php echo $m['size']; ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="measurement-grid">
                                    <?php if ($m['chest']): ?>
                                    <div class="measurement-item">
                                        <div class="label">الصدر</div>
                                        <div class="value"><?php echo $m['chest']; ?> <span class="unit">سم</span></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($m['waist']): ?>
                                    <div class="measurement-item">
                                        <div class="label">الخصر</div>
                                        <div class="value"><?php echo $m['waist']; ?> <span class="unit">سم</span></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($m['hips']): ?>
                                    <div class="measurement-item">
                                        <div class="label">الأرداف</div>
                                        <div class="value"><?php echo $m['hips']; ?> <span class="unit">سم</span></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($m['length']): ?>
                                    <div class="measurement-item">
                                        <div class="label">الطول</div>
                                        <div class="value"><?php echo $m['length']; ?> <span class="unit">سم</span></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($m['shoulder']): ?>
                                    <div class="measurement-item">
                                        <div class="label">الكتف</div>
                                        <div class="value"><?php echo $m['shoulder']; ?> <span class="unit">سم</span></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($m['sleeve']): ?>
                                    <div class="measurement-item">
                                        <div class="label">الكم</div>
                                        <div class="value"><?php echo $m['sleeve']; ?> <span class="unit">سم</span></div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($m['neck']): ?>
                                    <div class="measurement-item">
                                        <div class="label">الرقبة</div>
                                        <div class="value"><?php echo $m['neck']; ?> <span class="unit">سم</span></div>
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
                
                <!-- أزرار تحديث الحالة (واضحة وكبيرة) -->
                <?php if ($current_order['status'] !== 'completed' && $current_order['status'] !== 'cancelled'): ?>
                    
                    <?php if ($current_order['status'] == 'pending' && !$has_payment): ?>
                        <div class="alert alert-warning" style="margin: 30px 0;">
                            ⚠️ لا يمكن بدء العمل حتى يتم الدفع من الزبون
                        </div>
                    <?php endif; ?>
                    
                    <div class="status-buttons">
                        <?php if ($current_order['status'] == 'pending'): ?>
                            <?php if ($has_payment): ?>
                                <form method="POST" style="flex: 1;" onsubmit="return confirmAction('بدء العمل')">
                                    <input type="hidden" name="status" value="in_progress">
                                    <button type="submit" class="status-btn btn-start">
                                        <span>🚀</span> بدء العمل
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="status-btn btn-disabled" disabled>
                                    <span>🔒</span> في انتظار الدفع
                                </button>
                            <?php endif; ?>
                            
                            <form method="POST" style="flex: 1;" onsubmit="return confirmAction('إلغاء الطلب')">
                                <input type="hidden" name="status" value="cancelled">
                                <button type="submit" class="status-btn btn-cancel">
                                    <span>❌</span> إلغاء الطلب
                                </button>
                            </form>
                            
                        <?php elseif ($current_order['status'] == 'in_progress'): ?>
                            
                            <form method="POST" style="flex: 1;" onsubmit="return confirmAction('إكمال الطلب')">
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" class="status-btn btn-complete">
                                    <span>✅</span> إكمال الطلب (+10 نقاط)
                                </button>
                            </form>
                            
                            <form method="POST" style="flex: 1;" onsubmit="return confirmAction('إلغاء الطلب')">
                                <input type="hidden" name="status" value="cancelled">
                                <button type="submit" class="status-btn btn-cancel">
                                    <span>❌</span> إلغاء الطلب
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                <?php else: ?>
                    <div class="alert alert-info" style="text-align:center; margin: 30px 0; background:#d1ecf1; color:#0c5460;">
                        هذا الطلب <?php echo $current_order['status'] == 'completed' ? 'مكتمل ✓' : 'ملغي ✗'; ?> ولا يمكن تعديله.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- نافذة تأكيد -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px;">⚠️ تأكيد العملية</h3>
            <p id="confirmMessage" style="color: #666; margin-bottom: 25px;">هل أنت متأكد؟</p>
            <div class="modal-buttons">
                <button class="modal-btn confirm" onclick="submitForm()">تأكيد</button>
                <button class="modal-btn cancel" onclick="closeModal()">إلغاء</button>
            </div>
        </div>
    </div>

    
    <script>
        let currentForm = null;
        
        function confirmAction(action) {
            document.getElementById('confirmMessage').innerHTML = `هل أنت متأكد من <strong>${action}</strong>؟`;
            document.getElementById('confirmModal').style.display = 'flex';
            currentForm = event.target.closest('form');
            return false;
        }
        
        function submitForm() {
            if (currentForm) {
                currentForm.submit();
            }
            closeModal();
        }
        
        function closeModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target == document.getElementById('confirmModal')) {
                closeModal();
            }
        }
    </script>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
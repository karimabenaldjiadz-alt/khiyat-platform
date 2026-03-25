<?php
// pages/customer/place_order.php
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/notifications.php';

if (!isLoggedIn() || !hasRole('customer')) {
    redirect('../login.php');
}

$unread_count = getUnreadNotificationsCount($_SESSION['user_id']);

$error = '';
$success = '';

$stmt = $pdo->prepare("SELECT customer_id FROM customer WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customer_id = $stmt->fetch()['customer_id'];

// جلب آخر 4 خياطين تعامل معهم الزبون
$recent_tailors = $pdo->prepare("
    SELECT DISTINCT t.tailor_id, u.full_name, t.specialization, t.rating, t.experience_points,
           (SELECT COUNT(*) FROM review WHERE tailor_id = t.tailor_id) as reviews_count,
           MAX(o.order_date) as last_order_date
    FROM `order` o
    JOIN tailor t ON o.tailor_id = t.tailor_id
    JOIN user u ON t.user_id = u.user_id
    WHERE o.customer_id = ?
    GROUP BY t.tailor_id
    ORDER BY last_order_date DESC
    LIMIT 4
");
$recent_tailors->execute([$customer_id]);
$recent_tailors = $recent_tailors->fetchAll();

// جلب الخياط المحدد (إذا تم تمرير tailor_id)
$selected_tailor_id = isset($_GET['tailor_id']) ? intval($_GET['tailor_id']) : 0;
$selected_tailor = null;

if ($selected_tailor_id > 0) {
    $stmt = $pdo->prepare("
        SELECT t.tailor_id, u.full_name, t.specialization, t.rating, t.experience_points
        FROM tailor t
        JOIN user u ON t.user_id = u.user_id
        WHERE t.tailor_id = ?
    ");
    $stmt->execute([$selected_tailor_id]);
    $selected_tailor = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tailor_id = isset($_POST['tailor_id']) ? intval($_POST['tailor_id']) : 0;
    $description = isset($_POST['description']) ? sanitize($_POST['description']) : '';
    $total_price = isset($_POST['total_price']) ? floatval($_POST['total_price']) : 0;
    $delivery_date = isset($_POST['delivery_date']) ? $_POST['delivery_date'] : '';
    
    if (!$tailor_id) {
        $error = 'الرجاء اختيار خياط';
    } elseif (empty($description)) {
        $error = 'الرجاء كتابة وصف التصميم';
    } elseif ($total_price <= 0) {
        $error = 'الرجاء إدخال سعر صحيح';
    } elseif (empty($delivery_date)) {
        $error = 'الرجاء اختيار تاريخ التسليم';
    } else {
        $design_image = '';
        if (isset($_FILES['design_image']) && $_FILES['design_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file = $_FILES['design_image'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($file_ext, $allowed_exts)) {
                $error = 'نوع الملف غير مسموح';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'حجم الصورة كبير جداً';
            } else {
                $new_file_name = uniqid() . '_' . time() . '.' . $file_ext;
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_file_name)) {
                    $design_image = 'uploads/' . $new_file_name;
                } else {
                    $error = 'فشل في رفع الصورة';
                }
            }
        }
        
        if (empty($error)) {
            try {
                $pdo->beginTransaction();
                
                // إدراج الطلب
                $stmt = $pdo->prepare("INSERT INTO `order` (customer_id, tailor_id, description, total_price, delivery_date, design_image, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$customer_id, $tailor_id, $description, $total_price, $delivery_date, $design_image]);
                $order_id = $pdo->lastInsertId();
                
                // إدراج المقاسات إذا وجدت
                if (isset($_POST['has_measurements']) && $_POST['has_measurements'] == 'yes') {
                    $measurement_type = !empty($_POST['measurement_type']) ? sanitize($_POST['measurement_type']) : 'أخرى';
                    $size = isset($_POST['size']) ? sanitize($_POST['size']) : null;
                    $chest = !empty($_POST['chest']) ? floatval($_POST['chest']) : null;
                    $waist = !empty($_POST['waist']) ? floatval($_POST['waist']) : null;
                    $hips = !empty($_POST['hips']) ? floatval($_POST['hips']) : null;
                    $length = !empty($_POST['length']) ? floatval($_POST['length']) : null;
                    $shoulder = !empty($_POST['shoulder']) ? floatval($_POST['shoulder']) : null;
                    $sleeve = !empty($_POST['sleeve']) ? floatval($_POST['sleeve']) : null;
                    $neck = !empty($_POST['neck']) ? floatval($_POST['neck']) : null;
                    $notes = sanitize($_POST['measurement_notes'] ?? '');
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO measurements 
                        (order_id, measurement_type, size, chest, waist, hips, length, shoulder, sleeve, neck, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$order_id, $measurement_type, $size, $chest, $waist, $hips, $length, $shoulder, $sleeve, $neck, $notes]);
                }
                
                // جلب user_id الخاص بالخياط لإرسال الإشعار
                $stmt = $pdo->prepare("SELECT user_id FROM tailor WHERE tailor_id = ?");
                $stmt->execute([$tailor_id]);
                $tailor_user = $stmt->fetch();
                
                // إضافة إشعار للخياط
                addNotification(
                    $tailor_user['user_id'],
                    'order',
                    '📦 طلب تصميم جديد',
                    'لديك طلب تصميم جديد من ' . $_SESSION['user_name'],
                    'tailor/view_requests.php'
                );
                
                $pdo->commit();
                
                $_SESSION['success'] = 'تم إرسال طلبك بنجاح';
                redirect('my_orders.php');
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "حدث خطأ: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلب تصميم جديد</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .dashboard { display: flex; min-height: 100vh; position: relative; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #2c3e50 0%, #1e2b38 100%); color: white; position: fixed; height: 100vh; overflow-y: auto; right: 0; top: 0; box-shadow: -5px 0 20px rgba(0,0,0,0.2); z-index: 1000; }
        .main-content { flex: 1; margin-right: 280px; padding: 30px; background: #f5f5f5; }
        
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
        
        .search-btn {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 12px 25px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .search-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.2);
        }
        
        .selected-tailor-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
            text-align: center;
            box-shadow: 0 10px 30px rgba(102,126,234,0.3);
        }
        
        .selected-tailor-name {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .selected-tailor-specialization {
            font-size: 1.2em;
            opacity: 0.9;
            margin-bottom: 15px;
        }
        
        .selected-tailor-rating {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            font-size: 1.1em;
        }
        
        .selected-tailor-rating span {
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 30px;
        }
        
        .recent-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .recent-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .recent-title h3 {
            color: #333;
            font-size: 1.3em;
        }
        
        .recent-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        
        .recent-card {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
        }
        
        .recent-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.2);
        }
        
        .recent-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f3ff 0%, #e8e8ff 100%);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }
        
        .recent-card.selected::after {
            content: '✓';
            position: absolute;
            top: -8px;
            left: -8px;
            width: 25px;
            height: 25px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .recent-card input[type="radio"] {
            display: none;
        }
        
        .recent-card .name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .recent-card .specialization {
            font-size: 0.9em;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .recent-card .rating {
            font-size: 0.9em;
            color: #ffc107;
            margin-bottom: 5px;
        }
        
        .recent-card .experience {
            font-size: 0.8em;
            color: #28a745;
            font-weight: 600;
        }
        
        .form-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            color: #333;
            margin-bottom: 25px;
            font-size: 1.3em;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
            background: white;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .upload-area {
            border: 3px dashed #667eea;
            background: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-radius: 15px;
            cursor: pointer;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .upload-area:hover {
            background: #e8e8ff;
            border-color: #764ba2;
        }
        
        .upload-area.dragover {
            background: #e8e8ff;
            border-color: #764ba2;
        }
        
        .upload-icon {
            font-size: 3em;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .upload-text {
            color: #333;
            font-weight: 500;
        }
        
        .upload-hint {
            color: #999;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 300px;
            margin: 20px auto;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            display: block;
        }
        
        .remove-image {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s ease;
        }
        
        .remove-image:hover {
            background: #c82333;
        }
        
        .measurement-toggle {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .measurement-toggle:hover {
            border-color: #667eea;
            background: #f0f3ff;
        }
        
        .measurement-toggle-icon {
            font-size: 2em;
            color: #667eea;
        }
        
        .measurement-toggle-text {
            flex: 1;
        }
        
        .measurement-toggle-text h4 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .measurement-toggle-text p {
            color: #666;
            font-size: 0.9em;
        }
        
        .measurement-toggle-check {
            width: 25px;
            height: 25px;
            border: 2px solid #667eea;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .measurement-toggle.active {
            background: #667eea;
            border-color: #667eea;
        }
        
        .measurement-toggle.active .measurement-toggle-icon,
        .measurement-toggle.active .measurement-toggle-text h4,
        .measurement-toggle.active .measurement-toggle-text p {
            color: white;
        }
        
        .measurement-toggle.active .measurement-toggle-check {
            background: white;
            color: #667eea;
            border-color: white;
        }
        
        .measurement-fields {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-top: 20px;
            border: 2px solid #667eea;
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
        
        .measurement-fields h4 {
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .size-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 15px 0;
        }
        
        .size-option {
            flex: 1;
            min-width: 60px;
        }
        
        .size-option input[type="radio"] {
            display: none;
        }
        
        .size-option label {
            display: block;
            padding: 12px 5px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            color: #333;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .size-option input[type="radio"]:checked + label {
            background: #667eea;
            border-color: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(102,126,234,0.3);
        }
        
        .size-option label:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .optional-badge {
            display: inline-block;
            background: #e9ecef;
            color: #6c757d;
            font-size: 0.7em;
            padding: 2px 8px;
            border-radius: 12px;
            margin-right: 8px;
            font-weight: normal;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 18px 30px;
            font-size: 1.3em;
            font-weight: bold;
            border-radius: 15px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        
        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102,126,234,0.4);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 992px) {
            .recent-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-right: 0; }
            .dashboard { flex-direction: column; }
            .recent-grid {
                grid-template-columns: 1fr;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
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
                <li><a href="place_order.php" class="active">➕ طلب جديد</a></li>

                <li><a href="../logout.php" class="logout-link">🚪 تسجيل خروج</a></li>
            </ul>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">📝 طلب تصميم جديد</h1>
                <a href="browse_tailors.php" class="search-btn">
                    <span>🔍</span> بحث عن خياط
                </a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" id="orderForm">
                
                <!-- الخياط المحدد (إذا تم اختياره من صفحة أخرى) -->
                <?php if ($selected_tailor): ?>
                    <div class="selected-tailor-section">
                        <div class="selected-tailor-name"><?php echo htmlspecialchars($selected_tailor['full_name']); ?></div>
                        <div class="selected-tailor-specialization"><?php echo htmlspecialchars($selected_tailor['specialization']); ?></div>
                        <div class="selected-tailor-rating">
                            <span>⭐ <?php echo number_format($selected_tailor['rating'], 1); ?></span>
                            <span>💎 <?php echo $selected_tailor['experience_points']; ?> نقطة</span>
                        </div>
                        <input type="hidden" name="tailor_id" value="<?php echo $selected_tailor_id; ?>">
                    </div>
                <?php endif; ?>
                
                <!-- آخر خياطين تعاملت معهم (إذا لم يتم اختيار خياط معين) -->
                <?php if (!$selected_tailor && count($recent_tailors) > 0): ?>
                    <div class="recent-section">
                        <div class="recent-title">
                            <h3>🔄 آخر خياطين تعاملت معهم</h3>
                        </div>
                        <div class="recent-grid">
                            <?php foreach ($recent_tailors as $tailor): ?>
                                <div class="recent-card" onclick="selectTailor(this, <?php echo $tailor['tailor_id']; ?>)">
                                    <input type="radio" name="tailor_id" value="<?php echo $tailor['tailor_id']; ?>" style="display: none;">
                                    <div class="name"><?php echo htmlspecialchars($tailor['full_name']); ?></div>
                                    <div class="specialization"><?php echo htmlspecialchars($tailor['specialization']); ?></div>
                                    <div class="rating">⭐ <?php echo number_format($tailor['rating'], 1); ?></div>
                                    <div class="experience">💎 <?php echo $tailor['experience_points'] ?? 0; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- تفاصيل التصميم -->
                <div class="form-section">
                    <h3><span>📋</span> تفاصيل التصميم</h3>
                    
                    <div class="form-group">
                        <label>وصف التصميم</label>
                        <textarea name="description" rows="4" placeholder="اكتب وصفاً تفصيلياً للتصميم (نوع القماش، الألوان، التفاصيل المهمة...)" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>صورة التصميم <span class="optional-badge">اختياري</span></label>
                        <div class="upload-area" id="uploadArea">
                            <div class="upload-icon">📸</div>
                            <div class="upload-text">اضغط هنا لاختيار صورة</div>
                            <div class="upload-hint">أو اسحب وأفلت الصورة هنا</div>
                            <input type="file" name="design_image" id="fileInput" accept="image/*" style="display: none;">
                        </div>
                        <div id="preview"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>السعر المقترح (د.ج)</label>
                        <input type="number" name="total_price" step="100" min="0" placeholder="مثال: 5000" required>
                    </div>
                    
                    <div class="form-group">
                        <label>تاريخ التسليم المطلوب</label>
                        <input type="date" name="delivery_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                    </div>
                </div>
                
                <!-- قسم المقاسات (اختياري بالكامل) -->
                <div class="form-section">
                    <h3><span>📏</span> المقاسات <span class="optional-badge">اختياري</span></h3>
                    
                    <!-- زر اختيار إضافة مقاسات -->
                    <div class="measurement-toggle" id="measurementToggle" onclick="toggleMeasurements()">
                        <div class="measurement-toggle-icon">📏</div>
                        <div class="measurement-toggle-text">
                            <h4>إضافة مقاسات</h4>
                            <p>اضغط هنا لإضافة مقاسات للتصميم (اختياري)</p>
                        </div>
                        <div class="measurement-toggle-check" id="measurementCheck">✓</div>
                        <input type="hidden" name="has_measurements" id="hasMeasurements" value="no">
                    </div>
                    
                    <!-- حقول المقاسات (مخفية في البداية) - كلها اختيارية -->
                    <div id="measurementFields" class="measurement-fields" style="display: none;">
                        <h4><span>📏</span> أدخل المقاسات (جميع الحقول اختيارية)</h4>
                        
                        <div class="form-group">
                            <label>نوع القطعة <span class="optional-badge">اختياري</span></label>
                            <select name="measurement_type">
                                <option value="">-- اختر (اختياري) --</option>
                                <option value="قميص">قميص</option>
                                <option value="بنطال">بنطال</option>
                                <option value="فستان">فستان</option>
                                <option value="بدلة">بدلة</option>
                                <option value="جلابة">جلابة</option>
                                <option value="قندورة">قندورة</option>
                                <option value="كاراكو">كاراكو</option>
                                <option value="بلوزة">بلوزة</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                        
                        <!-- خيارات الحجم -->
                        <div class="form-group">
                            <label>الحجم <span class="optional-badge">اختياري</span></label>
                            <div class="size-options">
                                <div class="size-option">
                                    <input type="radio" name="size" id="size_xs" value="XS">
                                    <label for="size_xs">XS</label>
                                </div>
                                <div class="size-option">
                                    <input type="radio" name="size" id="size_s" value="S">
                                    <label for="size_s">S</label>
                                </div>
                                <div class="size-option">
                                    <input type="radio" name="size" id="size_m" value="M">
                                    <label for="size_m">M</label>
                                </div>
                                <div class="size-option">
                                    <input type="radio" name="size" id="size_l" value="L">
                                    <label for="size_l">L</label>
                                </div>
                                <div class="size-option">
                                    <input type="radio" name="size" id="size_xl" value="XL">
                                    <label for="size_xl">XL</label>
                                </div>
                                <div class="size-option">
                                    <input type="radio" name="size" id="size_xxl" value="XXL">
                                    <label for="size_xxl">XXL</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- حقول المقاسات التفصيلية - كلها اختيارية -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>الصدر (سم) <span class="optional-badge">اختياري</span></label>
                                <input type="number" name="chest" step="0.5" min="0" placeholder="اختياري">
                            </div>
                            <div class="form-group">
                                <label>الخصر (سم) <span class="optional-badge">اختياري</span></label>
                                <input type="number" name="waist" step="0.5" min="0" placeholder="اختياري">
                            </div>
                            <div class="form-group">
                                <label>الأرداف (سم) <span class="optional-badge">اختياري</span></label>
                                <input type="number" name="hips" step="0.5" min="0" placeholder="اختياري">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>الطول (سم) <span class="optional-badge">اختياري</span></label>
                                <input type="number" name="length" step="0.5" min="0" placeholder="اختياري">
                            </div>
                            <div class="form-group">
                                <label>الكتف (سم) <span class="optional-badge">اختياري</span></label>
                                <input type="number" name="shoulder" step="0.5" min="0" placeholder="اختياري">
                            </div>
                            <div class="form-group">
                                <label>الكم (سم) <span class="optional-badge">اختياري</span></label>
                                <input type="number" name="sleeve" step="0.5" min="0" placeholder="اختياري">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>الرقبة (سم) <span class="optional-badge">اختياري</span></label>
                                <input type="number" name="neck" step="0.5" min="0" placeholder="اختياري">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>ملاحظات إضافية <span class="optional-badge">اختياري</span></label>
                            <textarea name="measurement_notes" rows="3" placeholder="أي تفاصيل إضافية عن المقاسات (اختياري)"></textarea>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    إرسال الطلب
                </button>
            </form>
        </div>
    </div>
    

    
    <script src="../../assets/js/script.js"></script>
    <script>
        function selectTailor(element, tailorId) {
            document.querySelectorAll('.recent-card').forEach(card => {
                card.classList.remove('selected');
            });
            element.classList.add('selected');
            const radio = element.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        }
        
        function toggleMeasurements() {
            const toggle = document.getElementById('measurementToggle');
            const fields = document.getElementById('measurementFields');
            const check = document.getElementById('measurementCheck');
            const input = document.getElementById('hasMeasurements');
            
            if (fields.style.display === 'none') {
                fields.style.display = 'block';
                toggle.classList.add('active');
                check.style.background = 'white';
                check.style.color = '#667eea';
                input.value = 'yes';
            } else {
                fields.style.display = 'none';
                toggle.classList.remove('active');
                check.style.background = 'none';
                check.style.color = 'white';
                input.value = 'no';
            }
        }
        
        // رفع الصور
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const preview = document.getElementById('preview');
        
        uploadArea.addEventListener('click', () => fileInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                previewFile(e.dataTransfer.files[0]);
            }
        });
        
        fileInput.addEventListener('change', function() {
            if (this.files[0]) previewFile(this.files[0]);
        });
        
        function previewFile(file) {
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                alert('نوع الملف غير مسموح');
                fileInput.value = '';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert('حجم الصورة كبير جداً');
                fileInput.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" class="preview-image"><button type="button" class="remove-image" onclick="removeImage()">حذف الصورة</button>`;
                uploadArea.style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
        
        window.removeImage = function() {
            fileInput.value = '';
            preview.innerHTML = '';
            uploadArea.style.display = 'block';
        };
    </script>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
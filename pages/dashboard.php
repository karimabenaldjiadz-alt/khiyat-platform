<?php
// pages/dashboard.php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];

$unread_count = getUnreadNotificationsCount($user_id);

// جلب إحصائيات بسيطة حسب الدور
$stats = [];

if ($user_role === 'customer') {
    // جلب معرف الزبون
    $stmt = $pdo->prepare("SELECT customer_id FROM customer WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $customer = $stmt->fetch();
    $customer_id = $customer['customer_id'];
    
    // عدد طلبات الزبون
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM `order` WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $stats['total_orders'] = $stmt->fetch()['total'];
    
    // آخر طلب
    $stmt = $pdo->prepare("SELECT * FROM `order` WHERE customer_id = ? ORDER BY order_date DESC LIMIT 1");
    $stmt->execute([$customer_id]);
    $stats['latest_order'] = $stmt->fetch();
    
} elseif ($user_role === 'tailor') {
    // جلب معرف الخياط
    $stmt = $pdo->prepare("SELECT tailor_id FROM tailor WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $tailor = $stmt->fetch();
    $tailor_id = $tailor['tailor_id'];
    
    // عدد طلبات الخياط
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM `order` WHERE tailor_id = ?");
    $stmt->execute([$tailor_id]);
    $stats['total_orders'] = $stmt->fetch()['total'];
    
    // الطلبات قيد الانتظار
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM `order` WHERE tailor_id = ? AND status = 'pending'");
    $stmt->execute([$tailor_id]);
    $stats['pending_orders'] = $stmt->fetch()['pending'];
    
    // متوسط التقييم
    $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating FROM review WHERE tailor_id = ?");
    $stmt->execute([$tailor_id]);
    $stats['avg_rating'] = $stmt->fetch()['avg_rating'] ?? 0;
    
    // نقاط الخبرة
    $stmt = $pdo->prepare("SELECT experience_points FROM tailor WHERE tailor_id = ?");
    $stmt->execute([$tailor_id]);
    $stats['experience_points'] = $stmt->fetch()['experience_points'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - <?php echo htmlspecialchars($user_name); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
        
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .welcome-section h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.2);
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
        }
        
        .quick-actions {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-top: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .quick-actions h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        .recent-activity {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-top: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            background: #f0f3ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            color: #333;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .activity-time {
            color: #999;
            font-size: 0.85em;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-right: 0; }
            .dashboard { flex-direction: column; }
            .stats-grid { grid-template-columns: 1fr; }
            .actions-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- الشريط الجانبي -->
        <div class="sidebar">
            <div class="user-info">
                <div style="width:80px;height:80px;background:rgba(255,255,255,0.1);border-radius:50%;margin:0 auto 15px;display:flex;align-items:center;justify-content:center;font-size:2.5em;">
                    <?php echo ($user_role === 'customer') ? '👤' : '✂️'; ?>
                </div>
                <h3><?php echo htmlspecialchars($user_name); ?></h3>
                <div style="background:rgba(255,255,255,0.1);padding:5px 10px;border-radius:15px;font-size:0.8em;margin:5px 0;">ID: <?php echo $_SESSION['user_id']; ?></div>
                <p class="role"><?php echo ($user_role === 'customer') ? '👤 زبون' : '✂️ خياط'; ?></p>
                
                <!-- أيقونة الإشعارات -->
                <div style="position: relative; margin-top: 15px;">
                    <a href="notifications.php" style="color: white; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 5px;">
                        <span style="font-size: 1.5em;">🔔</span>
                        <?php if ($unread_count > 0): ?>
                            <span style="background: #dc3545; color: white; border-radius: 50%; padding: 2px 8px; font-size: 0.8em; position: absolute; top: -5px; left: 30px;"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
            
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="active">🏠 الرئيسية</a></li>
                
                <?php if ($user_role === 'customer'): ?>
                    <li><a href="customer/browse_tailors.php">🔍 البحث عن خياط</a></li>
                    <li><a href="customer/my_orders.php">📦 طلباتي</a></li>
                    <li><a href="customer/place_order.php">➕ طلب جديد</a></li>
                    
                <?php elseif ($user_role === 'tailor'): ?>
                    <li><a href="tailor/view_requests.php">📋 طلبات جديدة</a></li>
                    <li><a href="tailor/my_orders.php">📦 طلباتي الحالية</a></li>
                    <li><a href="tailor/add_portfolio.php">➕ إضافة عمل</a></li>
                    <li><a href="tailor/my_portfolio.php">📸 معرض أعمالي</a></li>
                <?php endif; ?>
                
 
                <li><a href="logout.php" class="logout-link">🚪 تسجيل خروج</a></li>
            </ul>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="main-content">
            <!-- قسم الترحيب -->
            <div class="welcome-section">
                <h1>مرحباً بعودتك، <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
                <p>
                    <?php if ($user_role === 'customer'): ?>
                        نتمنى لك تجربة ممتعة في منصة خياط. ابحث عن الخياط المناسب لتصميمك.
                    <?php elseif ($user_role === 'tailor'): ?>
                        يسعدنا رؤيتك مرة أخرى. استعرض الطلبات الجديدة وأضف أعمالك إلى معرضك.
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- إحصائيات سريعة -->
            <h2>📊 نظرة سريعة</h2>
            <div class="stats-grid">
                <?php if ($user_role === 'customer'): ?>
                    <div class="stat-card">
                        <h3>إجمالي طلباتي</h3>
                        <div class="stat-number"><?php echo $stats['total_orders'] ?? 0; ?></div>
                    </div>
                    
                    <?php if ($stats['latest_order']): ?>
                        <div class="stat-card">
                            <h3>آخر طلب</h3>
                            <div class="stat-number">
                                <?php 
                                $status_map = [
                                    'pending' => '⏳',
                                    'in_progress' => '🔨',
                                    'completed' => '✅',
                                    'cancelled' => '❌'
                                ];
                                echo $status_map[$stats['latest_order']['status']] ?? '📦';
                                ?>
                            </div>
                            <div class="stat-label">
                                <?php echo date('Y-m-d', strtotime($stats['latest_order']['order_date'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($user_role === 'tailor'): ?>
                    <div class="stat-card">
                        <h3>إجمالي الطلبات</h3>
                        <div class="stat-number"><?php echo $stats['total_orders'] ?? 0; ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>طلبات معلقة</h3>
                        <div class="stat-number"><?php echo $stats['pending_orders'] ?? 0; ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>التقييم العام</h3>
                        <div class="stat-number">⭐ <?php echo number_format($stats['avg_rating'], 1); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>نقاط الخبرة</h3>
                        <div class="stat-number">💎 <?php echo $stats['experience_points'] ?? 0; ?></div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- إجراءات سريعة -->
            <div class="quick-actions">
                <h2>⚡ إجراءات سريعة</h2>
                <div class="actions-grid">
                    <?php if ($user_role === 'customer'): ?>
                        <a href="customer/place_order.php" class="action-btn"><i>➕</i> طلب جديد</a>
                        <a href="customer/browse_tailors.php" class="action-btn"><i>🔍</i> ابحث عن خياط</a>
                        <a href="customer/my_orders.php" class="action-btn"><i>📦</i> تتبع طلباتي</a>
                        
                    <?php elseif ($user_role === 'tailor'): ?>
                        <a href="tailor/view_requests.php" class="action-btn"><i>📋</i> عرض الطلبات الجديدة</a>
                        <a href="tailor/my_orders.php" class="action-btn"><i>📦</i> طلباتي الحالية</a>
                        <a href="tailor/add_portfolio.php" class="action-btn"><i>➕</i> إضافة عمل جديد</a>
                        <a href="tailor/my_portfolio.php" class="action-btn"><i>📸</i> معرض أعمالي</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- نشاط حديث -->
            <div class="recent-activity">
                <h2>📅 آخر النشاطات</h2>
                <?php if ($user_role === 'customer'): ?>
                    <?php if ($stats['latest_order']): ?>
                        <div class="activity-item">
                            <div class="activity-icon">📦</div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    تم إنشاء طلب جديد #<?php echo $stats['latest_order']['order_id']; ?>
                                </div>
                                <div class="activity-time">
                                    <?php echo date('Y-m-d H:i', strtotime($stats['latest_order']['order_date'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="activity-item">
                            <div class="activity-icon">👋</div>
                            <div class="activity-content">
                                <div class="activity-title">مرحباً بك في منصة خياط!</div>
                                <div class="activity-time">ابدأ بإنشاء طلبك الأول</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($user_role === 'tailor'): ?>
                    <?php if ($stats['pending_orders'] > 0): ?>
                        <div class="activity-item">
                            <div class="activity-icon">📋</div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    لديك <?php echo $stats['pending_orders']; ?> طلبات جديدة في انتظار ردك
                                </div>
                                <div class="activity-time">
                                    <a href="tailor/view_requests.php">عرض الطلبات</a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="activity-item">
                            <div class="activity-icon">✅</div>
                            <div class="activity-content">
                                <div class="activity-title">لا توجد طلبات جديدة حالياً</div>
                                <div class="activity-time">استعد لاستقبال طلبات جديدة قريباً</div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
  <script src="../assets/js/script.js"></script>
</body>
</html>
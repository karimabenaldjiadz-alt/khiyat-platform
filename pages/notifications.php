<?php
// pages/notifications.php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$unread_count = getUnreadNotificationsCount($user_id);

// تحديد كل الإشعارات كمقروءة
if (isset($_GET['mark_all_read'])) {
    markAllNotificationsAsRead($user_id);
    redirect('notifications.php');
}

// تحديد إشعار معين كمقروء
if (isset($_GET['mark_read'])) {
    $notification_id = intval($_GET['mark_read']);
    markNotificationAsRead($notification_id);
    redirect('notifications.php');
}

// جلب الإشعارات
$notifications = getUserNotifications($user_id, 50);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإشعارات - منصة خياط</title>
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
        
        .mark-all-btn {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .mark-all-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        .notifications-list {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .notification-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px 25px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .notification-item:hover {
            background: #f8f9fa;
        }
        
        .notification-item.unread {
            background: #f0f3ff;
            border-right: 4px solid #667eea;
        }
        
        .notification-icon {
            width: 50px;
            height: 50px;
            background: #f0f3ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            color: #667eea;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            color: #333;
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .notification-message {
            color: #666;
            font-size: 0.95em;
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        .notification-time {
            color: #999;
            font-size: 0.8em;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
        }
        
        .notification-link {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 15px;
            border-radius: 20px;
            background: #f0f3ff;
            transition: all 0.3s ease;
        }
        
        .notification-link:hover {
            background: #667eea;
            color: white;
        }
        
        .mark-read-btn {
            color: #28a745;
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 15px;
            border-radius: 20px;
            background: #e8f5e9;
            transition: all 0.3s ease;
        }
        
        .mark-read-btn:hover {
            background: #28a745;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .empty-icon {
            font-size: 5em;
            color: #667eea;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-title {
            color: #333;
            font-size: 1.8em;
            margin-bottom: 10px;
        }
        
        .empty-text {
            color: #666;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-right: 0; }
            .dashboard { flex-direction: column; }
            .notification-item {
                flex-direction: column;
                text-align: center;
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
                <div style="width:80px;height:80px;background:rgba(255,255,255,0.1);border-radius:50%;margin:0 auto 15px;display:flex;align-items:center;justify-content:center;font-size:2.5em;">
                    <?php echo ($user_role == 'customer') ? '👤' : '✂️'; ?>
                </div>
                <h3><?php echo htmlspecialchars($_SESSION['user_name']); ?></h3>
                <div style="background:rgba(255,255,255,0.1);padding:5px 10px;border-radius:15px;font-size:0.8em;margin:5px 0;">ID: <?php echo $_SESSION['user_id']; ?></div>
                <p class="role"><?php echo ($user_role == 'customer') ? '👤 زبون' : '✂️ خياط'; ?></p>
                
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
                <li><a href="dashboard.php">🏠 الرئيسية</a></li>
                <?php if ($user_role == 'customer'): ?>
                    <li><a href="customer/browse_tailors.php">🔍 البحث عن خياط</a></li>
                    <li><a href="customer/my_orders.php">📦 طلباتي</a></li>
                    <li><a href="customer/place_order.php">➕ طلب جديد</a></li>
                <?php else: ?>
                    <li><a href="tailor/view_requests.php">📋 طلبات جديدة</a></li>
                    <li><a href="tailor/my_orders.php">📦 طلباتي الحالية</a></li>
                    <li><a href="tailor/add_portfolio.php">➕ إضافة عمل</a></li>
                    <li><a href="tailor/my_portfolio.php">📸 معرض أعمالي</a></li>
                <?php endif; ?>
                <li><a href="notifications.php" class="active">🔔 الإشعارات</a></li>
                <li><a href="logout.php" class="logout-link">🚪 تسجيل خروج</a></li>
            </ul>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <span>🔔</span>
                    الإشعارات
                </div>
                <?php if (count($notifications) > 0): ?>
                    <a href="?mark_all_read=1" class="mark-all-btn">
                        <span>✓</span> تحديد الكل كمقروء
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if (count($notifications) > 0): ?>
                <div class="notifications-list">
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                            <div class="notification-icon">
                                <?php
                                $icon = '📌';
                                switch ($notif['type']) {
                                    case 'order': $icon = '📦'; break;
                                    case 'payment': $icon = '💰'; break;
                                    case 'review': $icon = '⭐'; break;
                                    case 'price': $icon = '💵'; break;
                                    case 'status': $icon = '🔄'; break;
                                }
                                echo $icon;
                                ?>
                            </div>
                            
                            <div class="notification-content">
                                <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <div class="notification-time">
                                    <span>📅</span>
                                    <?php echo date('Y-m-d H:i', strtotime($notif['created_at'])); ?>
                                </div>
                            </div>
                            
                            <div class="notification-actions">
                                <?php if ($notif['link']): ?>
                                    <a href="<?php echo $notif['link']; ?>" class="notification-link">
                                        <span>🔍</span> عرض
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!$notif['is_read']): ?>
                                    <a href="?mark_read=<?php echo $notif['notification_id']; ?>" class="mark-read-btn">
                                        <span>✓</span> تحديد كمقروء
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">🔔</div>
                    <h2 class="empty-title">لا توجد إشعارات</h2>
                    <p class="empty-text">ستظهر هنا الإشعارات الجديدة عندما يتفاعل الآخرون مع طلباتك</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="../assets/js/script.js"></script>
    
 
</body>
</html>
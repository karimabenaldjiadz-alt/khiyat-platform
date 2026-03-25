<?php
// pages/customer/payment.php
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/notifications.php';

if (!isLoggedIn() || !hasRole('customer')) {
    redirect('../login.php');
}

$unread_count = getUnreadNotificationsCount($_SESSION['user_id']);

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

$stmt = $pdo->prepare("
    SELECT o.*, u.full_name as tailor_name, o.tailor_price
    FROM `order` o
    JOIN tailor t ON o.tailor_id = t.tailor_id
    JOIN user u ON t.user_id = u.user_id
    WHERE o.order_id = ? AND o.customer_id = (SELECT customer_id FROM customer WHERE user_id = ?)
    AND o.tailor_price IS NOT NULL
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['error'] = 'هذا الطلب غير متاح للدفع';
    redirect('my_orders.php');
}

$stmt = $pdo->prepare("SELECT * FROM payment WHERE order_id = ?");
$stmt->execute([$order_id]);
$existing_payment = $stmt->fetch();

if ($existing_payment) {
    $_SESSION['info'] = 'هذا الطلب مدفوع بالفعل';
    redirect('my_orders.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'];
    $amount = $order['tailor_price'];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO payment (order_id, payment_method, amount, status, payment_date) VALUES (?, ?, ?, 'paid', NOW())");
        $stmt->execute([$order_id, $payment_method, $amount]);
        
        // جلب user_id الخاص بالخياط
        $stmt = $pdo->prepare("
            SELECT t.user_id 
            FROM `order` o
            JOIN tailor t ON o.tailor_id = t.tailor_id
            WHERE o.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $tailor = $stmt->fetch();
        
        // إضافة إشعار للخياط
        addNotification(
            $tailor['user_id'],
            'payment',
            '💰 تم الدفع',
            'قام الزبون بدفع الطلب #' . $order_id,
            'tailor/my_orders.php'
        );
        
        $pdo->commit();
        
        $_SESSION['success'] = 'تم الدفع بنجاح';
        redirect('my_orders.php');
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'حدث خطأ في عملية الدفع';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إتمام الدفع - منصة خياط</title>
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
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-title span {
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 0.5em;
        }
        
        .back-btn {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 12px 25px;
            border-radius: 15px;
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
        
        .payment-container {
            max-width: 700px;
            margin: 0 auto;
        }
        
        .order-summary {
            background: white;
            border-radius: 25px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid #f0f0f0;
            position: relative;
            overflow: hidden;
        }
        
        .order-summary::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #28a745, #20c997);
        }
        
        .summary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .summary-header h3 {
            color: #333;
            font-size: 1.4em;
        }
        
        .order-badge {
            background: #e9ecef;
            color: #495057;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 0.9em;
        }
        
        .tailor-info {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        
        .tailor-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            color: white;
        }
        
        .tailor-details h4 {
            color: #333;
            font-size: 1.2em;
            margin-bottom: 5px;
        }
        
        .tailor-details p {
            color: #666;
            font-size: 0.9em;
        }
        
        .description-box {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            border-right: 4px solid #28a745;
        }
        
        .description-box p {
            color: #555;
            line-height: 1.8;
            margin: 0;
        }
        
        .amount-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            color: white;
            margin: 25px 0;
        }
        
        .amount-label {
            font-size: 1em;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .amount-value {
            font-size: 2.2em;
            font-weight: bold;
            line-height: 1;
        }
        
        .amount-currency {
            font-size: 1.2em;
            margin-right: 5px;
        }
        
        .payment-card {
            background: white;
            border-radius: 25px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid #f0f0f0;
        }
        
        .payment-card h3 {
            color: #333;
            margin-bottom: 25px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .payment-method {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-method:hover {
            border-color: #28a745;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(40,167,69,0.1);
        }
        
        .payment-method.selected {
            border-color: #28a745;
            background: #f0fff4;
        }
        
        .payment-method input[type="radio"] {
            display: none;
        }
        
        .payment-icon {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .payment-name {
            font-weight: 600;
            color: #333;
            font-size: 1em;
        }
        
        .action-buttons {
            margin-top: 30px;
        }
        
        .btn-pay {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 1.3em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 25px rgba(40,167,69,0.3);
        }
        
        .btn-pay:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(40,167,69,0.4);
        }
        
        .btn-pay:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 20px 25px;
            border-radius: 15px;
            margin-bottom: 25px;
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
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 2px solid #bee5eb;
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
            max-width: 400px;
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
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-right: 0; }
            .dashboard { flex-direction: column; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .payment-methods {
                grid-template-columns: 1fr;
            }
            .amount-value {
                font-size: 2em;
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
                <li><a href="place_order.php">➕ طلب جديد</a></li>

                <li><a href="../logout.php" class="logout-link">🚪 تسجيل خروج</a></li>
            </ul>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <span>💰</span>
                    إتمام الدفع
                </div>
                <a href="my_orders.php" class="back-btn">
                    <span>←</span> العودة للطلبات
                </a>
            </div>
            
            <div class="payment-container">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['info'])): ?>
                    <div class="alert alert-info"><?php echo $_SESSION['info']; unset($_SESSION['info']); ?></div>
                <?php endif; ?>
                
                <!-- ملخص الطلب -->
                <div class="order-summary">
                    <div class="summary-header">
                        <h3>📋 ملخص الطلب</h3>
                        <span class="order-badge">طلب #<?php echo $order['order_id']; ?></span>
                    </div>
                    
                    <!-- معلومات الخياط -->
                    <div class="tailor-info">
                        <div class="tailor-avatar">
                            <?php echo mb_substr($order['tailor_name'], 0, 1); ?>
                        </div>
                        <div class="tailor-details">
                            <h4><?php echo htmlspecialchars($order['tailor_name']); ?></h4>
                            <p>خياط محترف</p>
                        </div>
                    </div>
                    
                    <!-- وصف الطلب -->
                    <div class="description-box">
                        <p><?php echo nl2br(htmlspecialchars($order['description'])); ?></p>
                    </div>
                    
                    <!-- المبلغ (مصغر) -->
                    <div class="amount-box">
                        <div class="amount-label">المبلغ المطلوب</div>
                        <div class="amount-value">
                            <?php echo number_format($order['tailor_price'], 0); ?>
                            <span class="amount-currency">د.ج</span>
                        </div>
                    </div>
                </div>
                
                <!-- بطاقة الدفع -->
                <div class="payment-card">
                    <h3>
                        <span>💳</span>
                        اختر طريقة الدفع
                    </h3>
                    
                    <form method="POST" id="paymentForm" onsubmit="return confirmPayment()">
                        <div class="payment-methods">
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="بطاقة بنكية" required>
                                <div class="payment-icon">💳</div>
                                <div class="payment-name">بطاقة بنكية</div>
                            </label>
                            
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="نقدي عند الاستلام">
                                <div class="payment-icon">💵</div>
                                <div class="payment-name">نقدي عند الاستلام</div>
                            </label>
                            
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="تحويل بنكي">
                                <div class="payment-icon">🏦</div>
                                <div class="payment-name">تحويل بنكي</div>
                            </label>
                            
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="باريديم">
                                <div class="payment-icon">📱</div>
                                <div class="payment-name">بريديم</div>
                            </label>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="submit" class="btn-pay">
                                <span>✅</span> تأكيد الدفع
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- نافذة تأكيد الدفع -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-icon">💳</div>
            <h3>تأكيد الدفع</h3>
            <p>هل أنت متأكد من إتمام عملية الدفع؟</p>
            <div class="modal-buttons">
                <button class="modal-btn confirm" onclick="submitPayment()">تأكيد</button>
                <button class="modal-btn cancel" onclick="closeModal()">إلغاء</button>
            </div>
        </div>
    </div>
    

    
    <script>
        // تحديد طريقة الدفع
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                document.querySelectorAll('.payment-method').forEach(m => {
                    m.classList.remove('selected');
                });
                this.classList.add('selected');
                this.querySelector('input').checked = true;
            });
        });
        
        // نافذة التأكيد
        let paymentForm = document.getElementById('paymentForm');
        
        function confirmPayment() {
            // التحقق من اختيار طريقة دفع
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!selectedMethod) {
                alert('الرجاء اختيار طريقة الدفع');
                return false;
            }
            
            document.getElementById('confirmModal').style.display = 'flex';
            return false;
        }
        
        function submitPayment() {
            paymentForm.submit();
        }
        
        function closeModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
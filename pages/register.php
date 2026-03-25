<?php
// pages/register.php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/categories.php';
require_once '../includes/notifications.php';

$error = '';
$success = '';
$new_user_id = null;
$new_user_role = null;
$new_user_name = null;
$show_confirm = false; // متغير للتحكم في عرض نافذة التأكيد

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $detailed_address = sanitize($_POST['detailed_address']);
    $role = sanitize($_POST['role']);
    
    if (empty($full_name) || empty($email) || empty($password) || empty($role)) {
        $error = 'جميع الحقول المطلوبة يجب ملؤها';
    } elseif ($password !== $confirm_password) {
        $error = 'كلمة المرور غير متطابقة';
    } elseif (strlen($password) < 6) {
        $error = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'البريد الإلكتروني مستخدم بالفعل';
        } else {
            // تخزين البيانات مؤقتاً في session لعرض نافذة التأكيد
            $_SESSION['temp_registration'] = [
                'full_name' => $full_name,
                'email' => $email,
                'password' => $hashed_password = password_hash($password, PASSWORD_DEFAULT),
                'phone' => $phone,
                'address' => $address,
                'detailed_address' => $detailed_address,
                'role' => $role,
                'specialization' => isset($_POST['specialization']) ? sanitize($_POST['specialization']) : null
            ];
            $show_confirm = true;
        }
    }
}

// معالجة تأكيد التسجيل (عند الضغط على موافق في النافذة)
if (isset($_POST['confirm_registration'])) {
    $temp = $_SESSION['temp_registration'] ?? null;
    
    if ($temp) {
        try {
            $pdo->beginTransaction();
            
            $full_address = $temp['address'];
            if (!empty($temp['detailed_address'])) {
                $full_address .= ' - ' . $temp['detailed_address'];
            }
            
            $stmt = $pdo->prepare("INSERT INTO user (full_name, email, password, phone, address, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$temp['full_name'], $temp['email'], $temp['password'], $temp['phone'], $full_address, $temp['role']]);
            $user_id = $pdo->lastInsertId();
            
            if ($temp['role'] === 'customer') {
                $stmt2 = $pdo->prepare("INSERT INTO customer (user_id) VALUES (?)");
                $stmt2->execute([$user_id]);
            } elseif ($temp['role'] === 'tailor') {
                $stmt2 = $pdo->prepare("INSERT INTO tailor (user_id, specialization, registration_year) VALUES (?, ?, ?)");
                $stmt2->execute([$user_id, $temp['specialization'], date('Y')]);
            }
            
            $pdo->commit();
            
            // تسجيل الدخول التلقائي
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $temp['full_name'];
            $_SESSION['user_role'] = $temp['role'];
            $_SESSION['user_email'] = $temp['email'];
            
            // حذف البيانات المؤقتة
            unset($_SESSION['temp_registration']);
            
            // التوجيه إلى لوحة التحكم
            redirect('dashboard.php');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل جديد - منصة خياط</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tahoma', 'Segoe UI', Arial, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 700px;
            animation: slideUp 0.5s ease;
            position: relative;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* زر العودة للصفحة الرئيسية */
        .back-home {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 1.5em;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            z-index: 10;
        }
        
        .back-home:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-3px);
            border-color: white;
        }
        
        .back-home span {
            transform: rotate(180deg);
            display: inline-block;
        }
        
        .back-home .tooltip {
            position: absolute;
            top: 50%;
            right: 50px;
            transform: translateY(-50%);
            background: white;
            color: #667eea;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            white-space: nowrap;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
        }
        
        .back-home:hover .tooltip {
            opacity: 1;
            visibility: visible;
            right: 55px;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            text-align: center;
            color: white;
            position: relative;
        }
        
        .header h1 {
            font-size: 1.8em;
            margin-bottom: 5px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 0.95em;
        }
        
        .logo {
            font-size: 2.5em;
            margin-bottom: 10px;
            background: rgba(255,255,255,0.2);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            border: 2px solid white;
        }
        
        .form-content {
            padding: 20px 25px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        
        .form-group {
            flex: 1 1 calc(50% - 8px);
            min-width: 200px;
            position: relative;
        }
        
        .form-group.full-width {
            flex: 1 1 100%;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 3px;
            color: #333;
            font-weight: 600;
            font-size: 0.9em;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 35px 10px 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9em;
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
        
        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23667eea' stroke-width='2'><polyline points='6 9 12 15 18 9'/></svg>");
            background-repeat: no-repeat;
            background-position: left 10px center;
            cursor: pointer;
        }
        
        .form-group textarea {
            resize: none;
            min-height: 60px;
        }
        
        .input-icon {
            position: absolute;
            left: 10px;
            top: 32px;
            color: #667eea;
            font-size: 1.1em;
            pointer-events: none;
            z-index: 1;
        }
        
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px;
            font-size: 1em;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
            margin: 15px 0;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.5);
        }
        
        .login-link {
            text-align: center;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
            font-size: 0.95em;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        
        .login-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .alert {
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: 500;
            font-size: 0.95em;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        #tailor-fields {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border: 2px solid #e0e0e0;
        }
        
        .info-box {
            background: #e9ecef;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            color: #495057;
            font-weight: 500;
            font-size: 0.9em;
            margin-top: 10px;
        }
        
        /* نافذة التأكيد */
        .confirm-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }
        
        .confirm-content {
            background: white;
            padding: 40px;
            border-radius: 20px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: scaleIn 0.3s ease;
        }
        
        .confirm-icon {
            font-size: 4em;
            color: #ffc107;
            margin-bottom: 20px;
        }
        
        .confirm-title {
            color: #333;
            font-size: 1.8em;
            margin-bottom: 15px;
        }
        
        .confirm-message {
            color: #666;
            margin-bottom: 25px;
            font-size: 1.1em;
        }
        
        .user-info-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: right;
        }
        
        .user-info-summary p {
            margin: 8px 0;
            color: #555;
        }
        
        .user-info-summary strong {
            color: #333;
        }
        
        .confirm-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .confirm-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .confirm-btn.yes {
            background: #28a745;
            color: white;
        }
        
        .confirm-btn.yes:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .confirm-btn.no {
            background: #dc3545;
            color: white;
        }
        
        .confirm-btn.no:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        @media (max-width: 700px) {
            .back-home .tooltip {
                display: none;
            }
            
            .form-group {
                flex: 1 1 100%;
            }
            
            .header {
                padding: 20px;
            }
            
            .form-content {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- زر العودة للصفحة الرئيسية -->
        <a href="../index.php" class="back-home">
            <span>←</span>
            <span class="tooltip">الرئيسية</span>
        </a>
        
        <div class="header">
            <div class="logo">✂️</div>
            <h1>إنشاء حساب جديد</h1>
            <p>انضم إلى منصة خياط</p>
        </div>
        
        <div class="form-content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!$show_confirm): ?>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>الاسم الكامل</label>
                            <span class="input-icon">👤</span>
                            <input type="text" name="full_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label>البريد الإلكتروني</label>
                            <span class="input-icon">📧</span>
                            <input type="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>كلمة المرور</label>
                            <span class="input-icon">🔒</span>
                            <input type="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>تأكيد كلمة المرور</label>
                            <span class="input-icon">✓</span>
                            <input type="password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>رقم الهاتف</label>
                            <span class="input-icon">📞</span>
                            <input type="text" name="phone">
                        </div>
                        
                        <div class="form-group">
                            <label>الولاية</label>
                            <span class="input-icon">📍</span>
                            <select name="address" required>
                                <option value="">اختر</option>
                                <?php foreach ($algerian_cities as $city): ?>
                                    <option value="<?php echo $city; ?>"><?php echo $city; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>نوع الحساب</label>
                            <span class="input-icon">👥</span>
                            <select name="role" id="role" required>
                                <option value="">اختر</option>
                                <option value="customer" <?php echo (isset($_GET['role']) && $_GET['role'] == 'customer') ? 'selected' : ''; ?>>زبون</option>
                                <option value="tailor" <?php echo (isset($_GET['role']) && $_GET['role'] == 'tailor') ? 'selected' : ''; ?>>خياط</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>العنوان التفصيلي</label>
                            <span class="input-icon">🏠</span>
                            <input type="text" name="detailed_address">
                        </div>
                    </div>
                    
                    <!-- حقول الخياط -->
                    <div id="tailor-fields" style="display: <?php echo (isset($_GET['role']) && $_GET['role'] == 'tailor') ? 'block' : 'none'; ?>;">
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label>التخصص</label>
                                <span class="input-icon">🔨</span>
                                <select name="specialization" <?php echo (isset($_GET['role']) && $_GET['role'] == 'tailor') ? 'required' : ''; ?>>
                                    <option value="">اختر تخصصك</option>
                                    <?php foreach ($specializations as $spec): ?>
                                        <option value="<?php echo $spec; ?>"><?php echo $spec; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="info-box">
                            📅 سنة التسجيل: <?php echo date('Y'); ?> (تلقائي)
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-register">إنشاء حساب</button>
                </form>
                
                <div class="login-link">
                    <p>لديك حساب؟ <a href="login.php">تسجيل الدخول</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- نافذة تأكيد المعلومات -->
    <?php if ($show_confirm && isset($_SESSION['temp_registration'])): 
        $temp = $_SESSION['temp_registration'];
    ?>
        <div class="confirm-modal" id="confirmModal">
            <div class="confirm-content">
                <div class="confirm-icon">✓</div>
                <h2 class="confirm-title">تأكيد المعلومات</h2>
                <p class="confirm-message">هل أنت متأكد من صحة المعلومات التالية؟</p>
                
                <div class="user-info-summary">
                    <p><strong>👤 الاسم:</strong> <?php echo htmlspecialchars($temp['full_name']); ?></p>
                    <p><strong>📧 البريد:</strong> <?php echo htmlspecialchars($temp['email']); ?></p>
                    <p><strong>📞 الهاتف:</strong> <?php echo htmlspecialchars($temp['phone'] ?? 'غير مدخل'); ?></p>
                    <p><strong>📍 العنوان:</strong> <?php echo htmlspecialchars($temp['address']); ?></p>
                    <p><strong>👥 نوع الحساب:</strong> <?php echo $temp['role'] == 'customer' ? 'زبون' : 'خياط'; ?></p>
                    <?php if ($temp['role'] == 'tailor' && $temp['specialization']): ?>
                        <p><strong>🔨 التخصص:</strong> <?php echo htmlspecialchars($temp['specialization']); ?></p>
                    <?php endif; ?>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="confirm_registration" value="1">
                    <div class="confirm-buttons">
                        <button type="submit" class="confirm-btn yes">نعم، تأكيد التسجيل</button>
                        <button type="button" class="confirm-btn no" onclick="location.href='register.php'">تعديل المعلومات</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
    <script>
        document.getElementById('role').addEventListener('change', function() {
            const tailorFields = document.getElementById('tailor-fields');
            const specializationSelect = document.querySelector('select[name="specialization"]');
            
            if (this.value === 'tailor') {
                tailorFields.style.display = 'block';
                specializationSelect.setAttribute('required', 'required');
            } else {
                tailorFields.style.display = 'none';
                specializationSelect.removeAttribute('required');
                specializationSelect.value = '';
            }
        });
        
        <?php if (isset($_GET['role']) && $_GET['role'] == 'tailor'): ?>
            document.getElementById('tailor-fields').style.display = 'block';
        <?php endif; ?>
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
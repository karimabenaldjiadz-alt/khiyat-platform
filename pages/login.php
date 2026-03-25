<?php
// pages/login.php
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'البريد الإلكتروني وكلمة المرور مطلوبان';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            
            redirect('dashboard.php');
        } else {
            $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - منصة خياط</title>
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
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
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
            padding: 40px 30px;
            text-align: center;
            color: white;
            position: relative;
        }
        
        .header h1 {
            font-size: 2.2em;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .logo {
            font-size: 4em;
            margin-bottom: 15px;
            background: rgba(255,255,255,0.2);
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 3px solid white;
        }
        
        .form-content {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 1em;
        }
        
        .form-group .input-icon {
            position: absolute;
            left: 15px;
            top: 42px;
            color: #667eea;
            font-size: 1.2em;
            pointer-events: none;
            z-index: 1;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px 45px 15px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102,126,234,0.1);
            background: white;
        }
        
        .form-group input::placeholder {
            color: #999;
            font-size: 0.95em;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 16px 30px;
            font-size: 1.2em;
            font-weight: bold;
            border-radius: 12px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
            margin-bottom: 25px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.5);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .register-link p {
            color: #666;
            margin-bottom: 10px;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1em;
            transition: color 0.3s ease;
        }
        
        .register-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .features {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
            color: #666;
            font-size: 0.9em;
        }
        
        .feature {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .feature i {
            color: #667eea;
        }
        
        @media (max-width: 480px) {
            .back-home .tooltip {
                display: none;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 1.8em;
            }
            
            .form-content {
                padding: 30px 20px;
            }
            
            .features {
                flex-direction: column;
                gap: 10px;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- زر العودة للصفحة الرئيسية -->
        <a href="../index.php" class="back-home">
            <span>←</span>
            <span class="tooltip">الرئيسية</span>
        </a>
        
        <div class="header">
            <div class="logo">✂️</div>
            <h1>منصة خياط</h1>
            <p>تسجيل الدخول إلى حسابك</p>
        </div>
        
        <div class="form-content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['info'])): ?>
                <div class="alert alert-info"><?php echo $_SESSION['info']; unset($_SESSION['info']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <span class="input-icon">📧</span>
                    <input type="email" name="email" placeholder="أدخل بريدك الإلكتروني" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>كلمة المرور</label>
                    <span class="input-icon">🔒</span>
                    <input type="password" name="password" placeholder="أدخل كلمة المرور" required>
                </div>
                
                <button type="submit" class="btn-login">تسجيل الدخول</button>
            </form>
            
            <div class="register-link">
                <p>ليس لديك حساب؟</p>
                <a href="register.php">إنشاء حساب جديد</a>
            </div>
            
            <div class="features">
                <div class="feature">
                    <i>✓</i> زبون
                </div>
                <div class="feature">
                    <i>✓</i> خياط
                </div>
                <div class="feature">
                    <i>✓</i> آمن وسهل
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/script.js"></script>
</body>
</html>
<?php
// pages/tailor/add_portfolio.php
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/notifications.php';

if (!isLoggedIn() || !hasRole('tailor')) {
    redirect('../login.php');
}

$unread_count = getUnreadNotificationsCount($_SESSION['user_id']);

$error = '';
$success = '';

$stmt = $pdo->prepare("SELECT tailor_id FROM tailor WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$tailor_id = $stmt->fetch()['tailor_id'];

// معالجة رفع الصورة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = sanitize($_POST['description']);
    $price = floatval($_POST['price']);
    
    if (isset($_FILES['portfolio_image']) && $_FILES['portfolio_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/portfolio/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file = $_FILES['portfolio_image'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed_exts)) {
            $new_file_name = 'portfolio_' . $tailor_id . '_' . time() . '.' . $file_ext;
            $file_path = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $image_path = 'uploads/portfolio/' . $new_file_name;
                
                $stmt = $pdo->prepare("INSERT INTO tailor_portfolio (tailor_id, image_path, description, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$tailor_id, $image_path, $description, $price]);
                
                $success = 'تم إضافة العمل إلى معرضك بنجاح';
            } else {
                $error = 'فشل في رفع الصورة';
            }
        } else {
            $error = 'نوع الملف غير مسموح';
        }
    } else {
        $error = 'الرجاء اختيار صورة';
    }
}

// جلب أعمال الخياط الحالية
$stmt = $pdo->prepare("SELECT * FROM tailor_portfolio WHERE tailor_id = ? ORDER BY created_at DESC");
$stmt->execute([$tailor_id]);
$portfolio = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة عمل - خياط</title>
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
        
        .form-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .form-section h2 {
            color: #333;
            margin-bottom: 25px;
            font-size: 1.5em;
            border-right: 5px solid #667eea;
            padding-right: 15px;
        }
        
        .upload-area {
            border: 3px dashed #667eea;
            background: #f8f9fa;
            padding: 50px;
            text-align: center;
            border-radius: 15px;
            cursor: pointer;
            margin-bottom: 25px;
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
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
            font-size: 1.1em;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
            background: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 1.1em;
            border-radius: 50px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            font-weight: bold;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
        }
        
        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin: 30px 0;
        }
        
        .portfolio-item {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .portfolio-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.2);
        }
        
        .portfolio-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            cursor: pointer;
        }
        
        .portfolio-details {
            padding: 20px;
        }
        
        .portfolio-price {
            font-size: 1.5em;
            color: #667eea;
            font-weight: bold;
        }
        
        .portfolio-date {
            color: #999;
            font-size: 0.9em;
            display: block;
            margin-top: 10px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
        }
        
        .modal-content {
            max-width: 90%;
            max-height: 90%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 10px;
        }
        
        .close-modal {
            position: absolute;
            top: 20px;
            left: 30px;
            color: white;
            font-size: 40px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-right: 0; }
            .dashboard { flex-direction: column; }
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
                <li><a href="add_portfolio.php" class="active">➕ إضافة عمل</a></li>
                <li><a href="my_portfolio.php">📸 معرض أعمالي</a></li>
    
                <li><a href="../logout.php" class="logout-link">🚪 تسجيل خروج</a></li>
            </ul>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">📸 إضافة عمل جديد</h1>
                <a href="my_portfolio.php" class="back-btn">
                    <span>←</span> العودة للمعرض
                </a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- إضافة عمل جديد -->
            <div class="form-section">
                <h2>➕ إضافة عمل جديد</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="upload-area" id="uploadArea">
                        <div class="upload-icon">📸</div>
                        <div class="upload-text">اضغط هنا لاختيار صورة العمل</div>
                        <div class="upload-hint">أو اسحب وأفلت الصورة هنا</div>
                        <input type="file" name="portfolio_image" id="fileInput" accept="image/*" style="display: none;" required>
                    </div>
                    
                    <div id="preview"></div>
                    
                    <div class="form-group">
                        <label>وصف العمل:</label>
                        <textarea name="description" rows="4" placeholder="اكتب وصفاً للقطعة: نوع القماش، المناسبة، التفاصيل..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>السعر (د.ج):</label>
                        <input type="number" name="price" step="100" min="0" placeholder="مثال: 5000">
                    </div>
                    
                    <button type="submit" class="btn-primary">إضافة إلى المعرض</button>
                </form>
            </div>
            
            <!-- عرض الأعمال الحالية -->
            <h2 style="margin: 30px 0 20px;">🖼️ أعمالي السابقة</h2>
            
            <?php if (count($portfolio) > 0): ?>
                <div class="portfolio-grid">
                    <?php foreach ($portfolio as $item): ?>
                        <div class="portfolio-item">
                            <img src="../../<?php echo $item['image_path']; ?>" 
                                 class="portfolio-image" 
                                 onclick="openModal('../../<?php echo $item['image_path']; ?>')">
                            <div class="portfolio-details">
                                <p><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                                <p class="portfolio-price">💰 <?php echo number_format($item['price'], 0); ?> د.ج</p>
                                <small class="portfolio-date">📅 <?php echo date('Y-m-d', strtotime($item['created_at'])); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="background: white; padding: 60px; text-align: center; border-radius: 15px;">
                    <p style="color: #666;">📷 لا توجد أعمال في معرضك بعد</p>
                    <p style="color: #999;">أضف أعمالك الأولى باستخدام النموذج أعلاه</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- نافذة عرض الصور -->
    <div id="imageModal" class="modal" onclick="closeModal()">
        <span class="close-modal">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>
    

    
    <script>
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
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                previewFile(files[0]);
            }
        });
        
        fileInput.addEventListener('change', function() {
            if (this.files[0]) {
                previewFile(this.files[0]);
            }
        });
        
        function previewFile(file) {
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                alert('نوع الملف غير مسموح. الرجاء اختيار صورة فقط.');
                fileInput.value = '';
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                alert('حجم الصورة كبير جداً. الحد الأقصى 5 ميجابايت');
                fileInput.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" class="preview-image">`;
            };
            reader.readAsDataURL(file);
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
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
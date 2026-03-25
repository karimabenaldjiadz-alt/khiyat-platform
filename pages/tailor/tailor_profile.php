<?php
// pages/customer/tailor_profile.php
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';

if (!isLoggedIn() || !hasRole('customer')) {
    redirect('../login.php');
}

$tailor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// جلب معلومات الخياط
$stmt = $pdo->prepare("
    SELECT t.*, u.full_name, u.phone, u.address, u.email
    FROM tailor t
    JOIN user u ON t.user_id = u.user_id
    WHERE t.tailor_id = ?
");
$stmt->execute([$tailor_id]);
$tailor = $stmt->fetch();

if (!$tailor) {
    redirect('browse_tailors.php');
}

// جلب أعمال الخياط
$stmt = $pdo->prepare("SELECT * FROM tailor_portfolio WHERE tailor_id = ? ORDER BY created_at DESC");
$stmt->execute([$tailor_id]);
$portfolio = $stmt->fetchAll();

// جلب التقييمات
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name as customer_name 
    FROM review r
    JOIN customer c ON r.customer_id = c.customer_id
    JOIN user u ON c.user_id = u.user_id
    WHERE r.tailor_id = ?
    ORDER BY r.review_date DESC
");
$stmt->execute([$tailor_id]);
$reviews = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ملف <?php echo $tailor['full_name']; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .portfolio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .portfolio-item {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .portfolio-item img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        .portfolio-info {
            padding: 15px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">...</div>
        
        <div class="main-content">
            <div class="profile-header">
                <h1><?php echo $tailor['full_name']; ?></h1>
                <p>⭐ <?php echo number_format($tailor['rating'], 1); ?> / 5</p>
                <p>💎 <?php echo $tailor['experience_points']; ?> نقطة خبرة</p>
                <p>📧 <?php echo $tailor['email']; ?></p>
                <p>📞 <?php echo $tailor['phone']; ?></p>
                <p>📍 <?php echo $tailor['address']; ?></p>
                <a href="place_order.php?tailor_id=<?php echo $tailor_id; ?>" class="btn">طلب تصميم</a>
            </div>
            
            <h2>معرض الأعمال</h2>
            <div class="portfolio-grid">
                <?php foreach ($portfolio as $item): ?>
                    <div class="portfolio-item">
                        <img src="../../<?php echo $item['image_path']; ?>">
                        <div class="portfolio-info">
                            <p><?php echo $item['description']; ?></p>
                            <p><strong><?php echo $item['price']; ?> ريال</strong></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <h2>التقييمات</h2>
            <?php foreach ($reviews as $review): ?>
                <div style="background: white; padding: 15px; margin-bottom: 10px; border-radius: 10px;">
                    <p><strong><?php echo $review['customer_name']; ?></strong> - ⭐ <?php echo $review['rating']; ?></p>
                    <p><?php echo $review['comment']; ?></p>
                    <small><?php echo $review['review_date']; ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
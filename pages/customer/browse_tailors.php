<?php
// pages/customer/browse_tailors.php
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/categories.php';
require_once '../../includes/notifications.php';

if (!isLoggedIn() || !hasRole('customer')) {
    redirect('../login.php');
}

$unread_count = getUnreadNotificationsCount($_SESSION['user_id']);

// جلب قائمة الخياطين
$tailors = $pdo->query("
    SELECT t.tailor_id, u.full_name, u.address as city, t.specialization, t.category,
           t.registration_year, t.experience_points, t.rating,
           COUNT(DISTINCT o.order_id) as completed_orders,
           (SELECT AVG(rating) FROM review WHERE tailor_id = t.tailor_id) as avg_rating,
           (SELECT COUNT(*) FROM review WHERE tailor_id = t.tailor_id) as reviews_count
    FROM tailor t
    JOIN user u ON t.user_id = u.user_id
    LEFT JOIN `order` o ON t.tailor_id = o.tailor_id AND o.status = 'completed'
    GROUP BY t.tailor_id
    ORDER BY avg_rating DESC, t.experience_points DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ابحث عن خياط - منصة خياط</title>
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
            margin-bottom: 30px;
        }
        
        .page-title {
            color: #333;
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .page-subtitle {
            color: #666;
            font-size: 1.1em;
        }
        
        /* قسم البحث */
        .search-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .search-section h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-filters {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        
        .filter-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
            background: white;
        }
        
        .results-count {
            background: white;
            padding: 10px 25px;
            border-radius: 30px;
            display: inline-block;
            margin-bottom: 25px;
            color: #667eea;
            font-weight: 600;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* شبكة الخياطين */
        .tailors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .tailor-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .tailor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(102,126,234,0.2);
            border-color: #667eea;
        }
        
        .tailor-cover {
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
        }
        
        .tailor-avatar {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5em;
            position: absolute;
            bottom: -40px;
            right: 25px;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .tailor-content {
            padding: 50px 25px 25px;
        }
        
        .tailor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .tailor-name {
            font-size: 1.4em;
            font-weight: bold;
            color: #333;
        }
        
        .rating-badge {
            background: #ffc107;
            color: #333;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
        }
        
        .tailor-meta {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .meta-item {
            background: #f8f9fa;
            padding: 5px 12px;
            border-radius: 20px;
            color: #666;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .tailor-specialization {
            background: #f0f3ff;
            color: #667eea;
            padding: 8px 15px;
            border-radius: 25px;
            display: inline-block;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 0.95em;
        }
        
        .rating-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 15px;
            margin: 20px 0;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }
        
        .rating-main {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .rating-number {
            font-size: 2em;
            font-weight: bold;
            line-height: 1;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 1.2em;
            letter-spacing: 2px;
        }
        
        .rating-count {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 25px;
            font-size: 0.9em;
        }
        
        .order-btn {
            display: block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 12px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s ease;
            border: 1px solid transparent;
            cursor: pointer;
        }
        
        .order-btn:hover {
            background: white;
            color: #667eea;
            border-color: #667eea;
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
        
        .no-results {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            grid-column: 1 / -1;
        }
        
        .no-results p {
            color: #666;
            font-size: 1.2em;
            margin-bottom: 20px;
        }
        
        @media (max-width: 992px) {
            .search-filters {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: relative; }
            .main-content { margin-right: 0; }
            .dashboard { flex-direction: column; }
            .search-filters {
                grid-template-columns: 1fr;
            }
            .tailors-grid {
                grid-template-columns: 1fr;
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
                <li><a href="browse_tailors.php" class="active">🔍 البحث عن خياط</a></li>
                <li><a href="my_orders.php">📦 طلباتي</a></li>
                <li><a href="place_order.php">➕ طلب جديد</a></li>

                <li><a href="../logout.php" class="logout-link">🚪 تسجيل خروج</a></li>
            </ul>
        </div>
        
        <!-- المحتوى الرئيسي -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">🔍 ابحث عن خياط</h1>
                <p class="page-subtitle">اختر الخياط المناسب لتصميمك</p>
            </div>
            
            <!-- قسم البحث -->
            <div class="search-section">
                <h3><span>🔎</span> فلترة البحث</h3>
                <div class="search-filters">
                    <input type="text" id="searchName" class="filter-input" placeholder="ابحث باسم الخياط...">
                    <select id="searchCity" class="filter-input">
                        <option value="">جميع الولايات</option>
                        <?php foreach ($algerian_cities as $city): ?>
                            <option value="<?php echo $city; ?>"><?php echo $city; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="searchSpecialization" class="filter-input">
                        <option value="">جميع التخصصات</option>
                        <?php foreach ($specializations as $spec): ?>
                            <option value="<?php echo $spec; ?>"><?php echo $spec; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- عدد النتائج -->
            <div id="resultsCount" class="results-count">📊 تم العثور على <?php echo count($tailors); ?> خياط</div>
            
            <!-- شبكة الخياطين -->
            <?php if (count($tailors) > 0): ?>
                <div class="tailors-grid" id="tailorsGrid">
                    <?php foreach ($tailors as $tailor): 
                        $avg_rating = $tailor['avg_rating'] ? number_format($tailor['avg_rating'], 1) : '0.0';
                    ?>
                        <a href="tailor_profile.php?id=<?php echo $tailor['tailor_id']; ?>" class="tailor-card" 
                           data-name="<?php echo htmlspecialchars($tailor['full_name']); ?>"
                           data-city="<?php echo htmlspecialchars($tailor['city'] ?? ''); ?>"
                           data-specialization="<?php echo htmlspecialchars($tailor['specialization']); ?>">
                            
                            <div class="tailor-cover"></div>
                            
                            <div class="tailor-avatar">
                                <?php echo mb_substr($tailor['full_name'], 0, 1); ?>
                            </div>
                            
                            <div class="tailor-content">
                                <div class="tailor-header">
                                    <div class="tailor-name">
                                        <?php echo htmlspecialchars($tailor['full_name']); ?>
                                    </div>
                                    <div class="rating-badge">⭐ <?php echo $avg_rating; ?></div>
                                </div>
                                
                                <div class="tailor-meta">
                                    <?php if (!empty($tailor['city'])): ?>
                                        <span class="meta-item">📍 <?php echo htmlspecialchars($tailor['city']); ?></span>
                                    <?php endif; ?>
                                    <span class="meta-item">📦 <?php echo $tailor['completed_orders'] ?? 0; ?></span>
                                    <span class="meta-item">⭐ <?php echo $tailor['reviews_count'] ?? 0; ?></span>
                                </div>
                                
                                <div class="tailor-specialization">
                                    <?php echo htmlspecialchars($tailor['specialization']); ?>
                                </div>
                                
                                <div class="rating-stats">
                                    <div class="rating-main">
                                        <div class="rating-number"><?php echo $avg_rating; ?></div>
                                        <div>
                                            <div class="rating-stars">
                                                <?php 
                                                    $full_stars = floor($avg_rating);
                                                    for($i = 1; $i <= 5; $i++) {
                                                        if($i <= $full_stars) echo '★';
                                                        elseif($i - 0.5 <= $avg_rating) echo '½';
                                                        else echo '☆';
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rating-count"><?php echo $tailor['reviews_count'] ?? 0; ?> تقييم</div>
                                </div>
                                
                                <div onclick="event.stopPropagation(); event.preventDefault(); window.location.href='place_order.php?tailor_id=<?php echo $tailor['tailor_id']; ?>';" class="order-btn">
                                    طلب تصميم
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <p>😞 لا يوجد خياطون متاحون حالياً</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- نافذة عرض الصور -->
    <div id="imageModal" class="modal" onclick="closeImageModal()">
        <span class="close-modal">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>
    
   
    
    <script src="../../assets/js/script.js"></script>
    <script>
        // فلترة الخياطين (بحث فوري)
        function filterTailors() {
            const searchName = document.getElementById('searchName').value.toLowerCase().trim();
            const searchCity = document.getElementById('searchCity').value;
            const searchSpecialization = document.getElementById('searchSpecialization').value;
            
            const cards = document.querySelectorAll('.tailor-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                const name = card.dataset.name?.toLowerCase() || '';
                const city = card.dataset.city || '';
                const specialization = card.dataset.specialization || '';
                
                let matches = true;
                if (searchName && !name.includes(searchName)) matches = false;
                if (searchCity && city !== searchCity) matches = false;
                if (searchSpecialization && specialization !== searchSpecialization) matches = false;
                
                card.style.display = matches ? 'block' : 'none';
                if (matches) visibleCount++;
            });
            
            document.getElementById('resultsCount').innerHTML = `📊 تم العثور على ${visibleCount} خياط`;
        }
        
        document.getElementById('searchName').addEventListener('input', filterTailors);
        document.getElementById('searchCity').addEventListener('change', filterTailors);
        document.getElementById('searchSpecialization').addEventListener('change', filterTailors);
        
        document.querySelectorAll('.order-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                window.location.href = this.getAttribute('onclick').match(/'(.*?)'/)[1];
            });
        });
        
        function openImageModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeImageModal();
        });
        
        window.onload = filterTailors;
    </script>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
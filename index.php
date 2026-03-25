<?php
// index.php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('pages/dashboard.php');
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منصة خياط - المنصة الأولى للخياطة في الجزائر</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tahoma', Arial, sans-serif;
        }

        body {
            background: #f5f5f5;
            color: #333;
        }

        /* الشريط العلوي */
        .navbar {
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            padding: 10px 0;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5em;
            font-weight: bold;
            color: #333;
        }

        .logo span {
            color: #667eea;
        }

        .nav-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-login, .btn-register {
            padding: 8px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-login {
            background: white;
            color: #667eea;
            border: 1px solid #667eea;
        }

        .btn-login:hover {
            background: #667eea;
            color: white;
        }

        .btn-register {
            background: #667eea;
            color: white;
        }

        .btn-register:hover {
            background: #5a67d8;
        }

        /* القسم الرئيسي - مع أيقونة المقص في الوسط */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 120px 0 80px;
            margin-top: 60px;
            text-align: center;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .hero-icon {
            font-size: 5em;
            background: rgba(255,255,255,0.2);
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            border: 3px solid white;
            color: white;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .hero-content {
            color: white;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero-content h1 {
            font-size: 2.5em;
            margin-bottom: 20px;
        }

        .hero-content p {
            font-size: 1.1em;
            margin-bottom: 30px;
            opacity: 0.9;
            line-height: 1.6;
        }

        .hero-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-primary, .btn-secondary {
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: white;
            color: #667eea;
        }

        .btn-primary:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: transparent;
            color: white;
            border: 1px solid white;
        }

        .btn-secondary:hover {
            background: white;
            color: #667eea;
            transform: translateY(-2px);
        }

        /* بطاقات المميزات */
        .section {
            padding: 60px 0;
            background: white;
        }

        .section-gray {
            background: #f5f5f5;
        }

        .section-title {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-title h2 {
            color: #333;
            font-size: 2em;
            margin-bottom: 10px;
        }

        .section-title p {
            color: #666;
        }

        .features-grid, .stats-grid, .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }

        .feature-card, .stat-card, .testimonial-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .feature-card:hover, .stat-card:hover, .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.2);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.8em;
            color: white;
        }

        .feature-card h3, .stat-card h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .feature-card p, .stat-card p {
            color: #666;
            line-height: 1.6;
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        /* خطوات العمل */
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .step-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            position: relative;
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-weight: bold;
            font-size: 1.2em;
        }

        .step-card h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .step-card p {
            color: #666;
            line-height: 1.6;
        }

        /* آراء العملاء */
        .testimonial-text {
            color: #555;
            line-height: 1.6;
            margin-bottom: 20px;
            font-style: italic;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }

        .author-avatar {
            width: 40px;
            height: 40px;
            background: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2em;
        }

        .author-info h4 {
            color: #333;
            font-size: 0.95em;
            margin-bottom: 3px;
        }

        .author-info p {
            color: #666;
            font-size: 0.85em;
        }

        /* قسم الدعوة */
        .cta {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 80px 0;
            text-align: center;
            color: white;
        }

        .cta h2 {
            font-size: 2em;
            margin-bottom: 15px;
        }

        .cta p {
            margin-bottom: 30px;
            font-size: 1.1em;
            opacity: 0.9;
        }

        .btn-cta {
            display: inline-block;
            padding: 12px 40px;
            background: white;
            color: #667eea;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            font-size: 1.1em;
            transition: all 0.3s ease;
        }

        .btn-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* زر العودة للأعلى */
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 45px;
            height: 45px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 1.3em;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 999;
        }

        .scroll-top.show {
            opacity: 1;
            visibility: visible;
        }

        .scroll-top:hover {
            background: #5a67d8;
            transform: translateY(-3px);
        }

        /* التذييل */
        .footer {
            background: #2c3e50;
            color: white;
            padding: 40px 0 20px;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin-bottom: 30px;
        }

        .footer-col h4 {
            margin-bottom: 20px;
            font-size: 1.1em;
        }

        .footer-col ul {
            list-style: none;
        }

        .footer-col ul li {
            margin-bottom: 10px;
        }

        .footer-col ul li a {
            color: #bdc3c7;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-col ul li a:hover {
            color: white;
        }

        .social-links {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .social-link {
            width: 35px;
            height: 35px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-link:hover {
            background: #667eea;
            transform: translateY(-2px);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: #bdc3c7;
        }

        /* تجاوب */
        @media (max-width: 992px) {
            .features-grid, .stats-grid, .testimonials-grid, .footer-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .steps-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 2em;
            }

            .nav-container {
                flex-direction: column;
                gap: 10px;
            }

            .features-grid, .stats-grid, .testimonials-grid, .footer-container {
                grid-template-columns: 1fr;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn-primary, .btn-secondary {
                width: 200px;
            }

            .hero-icon {
                width: 100px;
                height: 100px;
                font-size: 4em;
            }
        }
    </style>
</head>
<body>
    <!-- زر العودة للأعلى -->
    <a href="#" class="scroll-top" id="scrollTop">↑</a>

    <!-- الشريط العلوي -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                منصة <span>خياط</span>
            </div>
            <div class="nav-buttons">
                <a href="pages/login.php" class="btn-login">تسجيل الدخول</a>
                <a href="pages/register.php" class="btn-register">إنشاء حساب</a>
            </div>
        </div>
    </nav>

    <!-- القسم الرئيسي مع أيقونة المقص في الوسط -->
    <section class="hero">
        <div class="container">
            <div class="hero-icon">✂️</div>
            <div class="hero-content">
                <h1>منصتك الأولى للخياطة في الجزائر</h1>
                <p>اكتشف أفضل الخياطين المحترفين، اختر التصميم الذي تحلم به، وتابع طلبك خطوة بخطوة</p>
                <div class="hero-buttons">
                    <a href="pages/register.php?role=customer" class="btn-primary">أطلب تصميمك</a>
                    <a href="pages/register.php?role=tailor" class="btn-secondary">انضم كخياط</a>
                </div>
            </div>
        </div>
    </section>

    <!-- المميزات -->
    <section class="section">
        <div class="container">
            <div class="section-title">
                <h2>مميزات منصة خياط</h2>
                <p>لماذا تختارنا؟</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">👔</div>
                    <h3>خياطين محترفين</h3>
                    <p>نخبة من أفضل الخياطين في الجزائر مع خبرة عالية</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🖼️</div>
                    <h3>معرض أعمال</h3>
                    <p>شاهد أعمال الخياطين السابقة واختر الأنسب</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💰</div>
                    <h3>تسعير شفاف</h3>
                    <p>الخياط يحدد السعر وأنت توافق أو ترفض</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3>متابعة لحظية</h3>
                    <p>تابع طلبك من التسليم إلى الإنجاز</p>
                </div>
            </div>
        </div>
    </section>

    <!-- كيف تعمل -->
    <section class="section section-gray">
        <div class="container">
            <div class="section-title">
                <h2>كيف تعمل المنصة؟</h2>
                <p>ثلاث خطوات بسيطة</p>
            </div>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3>اختر الخياط</h3>
                    <p>تصفح ملفات الخياطين واختر الأنسب لتصميمك</p>
                </div>
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3>حدد التصميم</h3>
                    <p>أرسل صورة التصميم ووصف دقيق للقطعة</p>
                </div>
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h3>استلم طلبك</h3>
                    <p>تابع مراحل الإنجاز واستلم طلبك في الوقت المحدد</p>
                </div>
            </div>
        </div>
    </section>

    <!-- الإحصائيات -->
    <section class="section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">+500</div>
                    <p>خياط محترف</p>
                </div>
                <div class="stat-card">
                    <div class="stat-number">+2000</div>
                    <p>طلب مكتمل</p>
                </div>
                <div class="stat-card">
                    <div class="stat-number">+1500</div>
                    <p>زبون سعيد</p>
                </div>
                <div class="stat-card">
                    <div class="stat-number">58</div>
                    <p>ولاية</p>
                </div>
            </div>
        </div>
    </section>

    <!-- آراء العملاء -->
    <section class="section section-gray">
        <div class="container">
            <div class="section-title">
                <h2>آراء العملاء</h2>
                <p>ماذا يقولون عنا</p>
            </div>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-text">"خدمة رائعة وخياطين محترفين. النتيجة كانت أفضل مما توقعت"</div>
                    <div class="testimonial-author">
                        <div class="author-avatar">👩</div>
                        <div class="author-info">
                            <h4>فاطمة</h4>
                            <p>زبونة</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-text">"منصة ممتازة ساعدتني في الحصول على عملاء جدد وتوسيع نشاطي"</div>
                    <div class="testimonial-author">
                        <div class="author-avatar">👨</div>
                        <div class="author-info">
                            <h4>أحمد</h4>
                            <p>خياط</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-text">"سهولة في الاستخدام ومتابعة ممتازة للطلبات. أنصح بها الجميع"</div>
                    <div class="testimonial-author">
                        <div class="author-avatar">👩‍🦰</div>
                        <div class="author-info">
                            <h4>سارة</h4>
                            <p>زبونة</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- دعوة للتسجيل -->
    <section class="cta">
        <div class="container">
            <h2>انضم إلى منصة خياط اليوم</h2>
            <p>سواء كنت تبحث عن خياط أو خياط تبحث عن عملاء، منصة خياط هي المكان المناسب</p>
            <a href="pages/register.php" class="btn-cta">ابدأ الآن مجاناً</a>
        </div>
    </section>

    <!-- التذييل -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-col">
                <h4>منصة خياط</h4>
                <ul>
                    <li><a href="#">من نحن</a></li>
                    <li><a href="#">اتصل بنا</a></li>
                    <li><a href="#">الشروط والأحكام</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>للزبائن</h4>
                <ul>
                    <li><a href="#">كيف تطلب</a></li>
                    <li><a href="#">البحث عن خياط</a></li>
                    <li><a href="#">الأسئلة الشائعة</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>للخياطين</h4>
                <ul>
                    <li><a href="#">كيف تنضم</a></li>
                    <li><a href="#">شروط الخياطين</a></li>
                    <li><a href="#">معرض الأعمال</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>تواصل معنا</h4>
                <ul>
                    <li>📞 0550 12 34 56</li>
                    <li>✉️ contact@khiyat.dz</li>
                </ul>
                <div class="social-links">
                    <a href="#" class="social-link">f</a>
                    <a href="#" class="social-link">📷</a>
                    <a href="#" class="social-link">🐦</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2026 منصة خياط - جميع الحقوق محفوظة</p>
        </div>
    </footer>

    <script>
        // زر العودة للأعلى
        window.addEventListener('scroll', function() {
            const scrollTop = document.getElementById('scrollTop');
            if (window.scrollY > 500) {
                scrollTop.classList.add('show');
            } else {
                scrollTop.classList.remove('show');
            }
        });

        document.getElementById('scrollTop').addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    </script>
</body>
</html>
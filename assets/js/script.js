// assets/js/script.js

// ============================================
// دوال عامة للموقع
// ============================================

// عرض رسالة منبثقة
function showMessage(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.left = '50%';
    alertDiv.style.transform = 'translateX(-50%)';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.padding = '15px 30px';
    alertDiv.style.borderRadius = '10px';
    alertDiv.style.boxShadow = '0 5px 20px rgba(0,0,0,0.2)';
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
}

// تأكيد قبل الحذف
function confirmDelete(message = 'هل أنت متأكد من الحذف؟') {
    return confirm(message);
}

// ============================================
// دوال صفحة place_order.php (طلب جديد)
// ============================================

function initOrderPage() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');
    const previewContainer = document.getElementById('previewContainer');
    const previewImage = document.getElementById('previewImage');
    const removeImage = document.getElementById('removeImage');
    const fileInfo = document.getElementById('fileInfo');
    
    if (!uploadArea) return;
    
    // النقر على منطقة الرفع
    uploadArea.addEventListener('click', () => {
        fileInput.click();
    });
    
    // سحب وإفلات
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
    
    // اختيار ملف
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            previewFile(fileInput.files[0]);
        }
    });
    
    // معاينة الملف
    function previewFile(file) {
        // التحقق من النوع
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            showMessage('نوع الملف غير مسموح', 'error');
            fileInput.value = '';
            return;
        }
        
        // التحقق من الحجم (5MB)
        const maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            showMessage('حجم الصورة كبير جداً (الحد الأقصى 5 ميجابايت)', 'error');
            fileInput.value = '';
            return;
        }
        
        // عرض معلومات الملف
        const fileSizeInMB = (file.size / (1024 * 1024)).toFixed(2);
        if (fileInfo) {
            fileInfo.textContent = `📄 ${file.name} - ${fileSizeInMB} MB`;
        }
        
        // معاينة الصورة
        const reader = new FileReader();
        reader.onload = (e) => {
            if (previewImage) previewImage.src = e.target.result;
            if (uploadArea) uploadArea.style.display = 'none';
            if (previewContainer) previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
    
    // حذف الصورة
    if (removeImage) {
        removeImage.addEventListener('click', () => {
            fileInput.value = '';
            if (previewImage) previewImage.src = '';
            if (uploadArea) uploadArea.style.display = 'flex';
            if (previewContainer) previewContainer.style.display = 'none';
            if (fileInfo) fileInfo.textContent = '';
        });
    }
    
    // اختيار الخياط
    const tailorCards = document.querySelectorAll('.tailor-card');
    tailorCards.forEach(card => {
        card.addEventListener('click', function() {
            tailorCards.forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            const radio = this.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        });
    });
    
    // دعم الكاميرا في الجوال
    if (/Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
        if (fileInput) {
            fileInput.setAttribute('capture', 'environment');
        }
    }
}

// ============================================
// دوال صفحة browse_tailors.php (تصفح الخياطين)
// ============================================

function initBrowsePage() {
    // نوافذ الصور
    window.openImageModal = function(imageSrc) {
        const modal = document.getElementById('imageModal');
        const modalImage = document.getElementById('modalImage');
        if (modal && modalImage) {
            modalImage.src = imageSrc;
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    };
    
    window.closeImageModal = function() {
        const modal = document.getElementById('imageModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    };
    
    window.openPortfolioModal = function(tailorId) {
        const modal = document.getElementById('portfolio-modal-' + tailorId);
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    };
    
    window.closePortfolioModal = function(tailorId) {
        const modal = document.getElementById('portfolio-modal-' + tailorId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    };
    
    // فلترة الخياطين
    const searchName = document.getElementById('searchName');
    const searchCity = document.getElementById('searchCity');
    const searchSpecialization = document.getElementById('searchSpecialization');
    const sortBy = document.getElementById('sortBy');
    
    if (searchName) searchName.addEventListener('input', filterTailors);
    if (searchCity) searchCity.addEventListener('change', filterTailors);
    if (searchSpecialization) searchSpecialization.addEventListener('input', filterTailors);
    if (sortBy) sortBy.addEventListener('change', filterTailors);
}

function filterTailors() {
    const searchName = document.getElementById('searchName')?.value.toLowerCase() || '';
    const searchCity = document.getElementById('searchCity')?.value.toLowerCase() || '';
    const searchSpecialization = document.getElementById('searchSpecialization')?.value.toLowerCase() || '';
    const sortBy = document.getElementById('sortBy')?.value || 'rating';
    
    const cards = document.querySelectorAll('.tailor-card');
    const cardsArray = Array.from(cards);
    
    // فلترة
    cardsArray.forEach(card => {
        const name = card.dataset.name?.toLowerCase() || '';
        const city = (card.dataset.city || '').toLowerCase();
        const specialization = card.dataset.specialization?.toLowerCase() || '';
        
        const matchesName = name.includes(searchName);
        const matchesCity = !searchCity || city.includes(searchCity);
        const matchesSpecialization = specialization.includes(searchSpecialization);
        
        card.style.display = (matchesName && matchesCity && matchesSpecialization) ? 'block' : 'none';
    });
    
    // ترتيب
    const visibleCards = cardsArray.filter(card => card.style.display !== 'none');
    
    visibleCards.sort((a, b) => {
        let aVal, bVal;
        
        switch(sortBy) {
            case 'rating':
                aVal = parseFloat(a.dataset.rating || 0);
                bVal = parseFloat(b.dataset.rating || 0);
                break;
            case 'experience':
                aVal = parseInt(a.dataset.experience || 0);
                bVal = parseInt(b.dataset.experience || 0);
                break;
            case 'orders':
                aVal = parseInt(a.dataset.orders || 0);
                bVal = parseInt(b.dataset.orders || 0);
                break;
            default:
                return 0;
        }
        
        return bVal - aVal;
    });
    
    // إعادة ترتيب
    const container = document.getElementById('tailorsList');
    if (container) {
        visibleCards.forEach(card => container.appendChild(card));
    }
}

// ============================================
// دوال صفحة review_order.php (تقييم الخياط)
// ============================================

function initReviewPage() {
    const stars = document.querySelectorAll('.star');
    const ratingInput = document.getElementById('ratingValue');
    const ratingText = document.getElementById('ratingText');
    const submitBtn = document.getElementById('submitBtn');
    
    if (!stars.length) return;
    
    const ratingMessages = {
        1: 'سيء جداً 😞',
        2: 'سيء 😕',
        3: 'مقبول 😐',
        4: 'جيد 😊',
        5: 'ممتاز 🤩'
    };
    
    stars.forEach(star => {
        // عند المرور بالماوس
        star.addEventListener('mouseover', function() {
            const rating = this.dataset.rating;
            highlightStars(rating);
        });
        
        // عند خروج الماوس
        star.addEventListener('mouseout', function() {
            const currentRating = ratingInput?.value;
            if (currentRating) {
                highlightStars(currentRating);
            } else {
                resetStars();
            }
        });
        
        // عند النقر
        star.addEventListener('click', function() {
            const rating = this.dataset.rating;
            if (ratingInput) ratingInput.value = rating;
            highlightStars(rating);
            if (ratingText) ratingText.textContent = ratingMessages[rating];
            if (submitBtn) submitBtn.disabled = false;
        });
    });
    
    function highlightStars(rating) {
        stars.forEach(star => {
            if (star.dataset.rating <= rating) {
                star.classList.add('active');
            } else {
                star.classList.remove('active');
            }
        });
    }
    
    function resetStars() {
        stars.forEach(star => {
            star.classList.remove('active');
        });
        if (ratingText) ratingText.textContent = 'اختر تقييمك';
    }
    
    // منع إرسال النموذج بدون تقييم
    const reviewForm = document.getElementById('reviewForm');
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            if (!ratingInput?.value) {
                e.preventDefault();
                showMessage('الرجاء اختيار تقييم', 'error');
            }
        });
    }
}

// ============================================
// دوال صفحة my_orders.php (طلباتي)
// ============================================

function initMyOrdersPage() {
    const filterTabs = document.querySelectorAll('.filter-tab');
    
    filterTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const status = this.textContent.includes('الكل') ? 'all' :
                          this.textContent.includes('الانتظار') ? 'pending' :
                          this.textContent.includes('التنفيذ') ? 'in_progress' :
                          this.textContent.includes('مكتملة') ? 'completed' : 'cancelled';
            
            filterOrders(status);
        });
    });
}

function filterOrders(status) {
    const cards = document.querySelectorAll('.order-card');
    const tabs = document.querySelectorAll('.filter-tab');
    
    // تحديث التبويب النشط
    tabs.forEach(tab => {
        tab.classList.remove('active');
        if ((status === 'all' && tab.textContent.includes('الكل')) ||
            (status === 'pending' && tab.textContent.includes('الانتظار')) ||
            (status === 'in_progress' && tab.textContent.includes('التنفيذ')) ||
            (status === 'completed' && tab.textContent.includes('مكتملة')) ||
            (status === 'cancelled' && tab.textContent.includes('ملغية'))) {
            tab.classList.add('active');
        }
    });
    
    // فلترة البطاقات
    cards.forEach(card => {
        if (status === 'all' || card.dataset.status === status) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// ============================================
// تأكيد تسجيل الخروج (نافذة منبثقة)
// ============================================

function confirmLogout(event) {
    event.preventDefault(); // منع الانتقال المباشر
    
    // إنشاء نافذة منبثقة
    const modal = document.createElement('div');
    modal.style.cssText = `
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
        direction: rtl;
        animation: fadeIn 0.3s ease;
    `;
    
    modal.innerHTML = `
        <div style="
            background: white;
            padding: 35px;
            border-radius: 25px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
        ">
            <div style="font-size: 4.5em; margin-bottom: 20px;">👋</div>
            <h3 style="color: #333; margin-bottom: 15px; font-size: 1.6em;">تسجيل الخروج</h3>
            <p style="color: #666; margin-bottom: 30px; font-size: 1.1em; line-height: 1.6;">
                هل أنت متأكد من تسجيل الخروج؟
            </p>
            <div style="display: flex; gap: 15px; justify-content: center;">
                <a href="${event.currentTarget.href}" style="
                    background: #dc3545;
                    color: white;
                    padding: 12px 30px;
                    border-radius: 12px;
                    text-decoration: none;
                    font-weight: bold;
                    font-size: 1.1em;
                    transition: all 0.3s ease;
                    flex: 1;
                    text-align: center;
                " onmouseover="this.style.background='#c82333'" 
                   onmouseout="this.style.background='#dc3545'">نعم، تسجيل خروج</a>
                <button onclick="this.closest('div[style*=\\'z-index: 9999\\']').remove(); document.body.style.overflow='auto';" style="
                    background: #6c757d;
                    color: white;
                    padding: 12px 30px;
                    border: none;
                    border-radius: 12px;
                    font-weight: bold;
                    font-size: 1.1em;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    flex: 1;
                " onmouseover="this.style.background='#5a6268'" 
                   onmouseout="this.style.background='#6c757d'">إلغاء</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
}

// ============================================
// الإغلاق بالـ ESC لجميع النوافذ
// ============================================

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        // إغلاق نافذة الصور
        const imageModal = document.getElementById('imageModal');
        if (imageModal && imageModal.style.display === 'block') {
            imageModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // إغلاق جميع نوافذ المعرض
        document.querySelectorAll('.portfolio-modal').forEach(modal => {
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
        
        // إغلاق نافذة تأكيد تسجيل الخروج
        const logoutModal = document.querySelector('div[style*="z-index: 9999"]');
        if (logoutModal) {
            logoutModal.remove();
            document.body.style.overflow = 'auto';
        }
    }
});

// إضافة حركات CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideUp {
        from {
            transform: translateY(50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);

// ============================================
// التهيئة حسب الصفحة
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // تحديد الصفحة الحالية
    const path = window.location.pathname;
    
    if (path.includes('place_order.php')) {
        initOrderPage();
    }
    else if (path.includes('browse_tailors.php')) {
        initBrowsePage();
    }
    else if (path.includes('review_order.php')) {
        initReviewPage();
    }
    else if (path.includes('my_orders.php')) {
        initMyOrdersPage();
    }
    
    // تفعيل تأكيد تسجيل الخروج
    const logoutLinks = document.querySelectorAll('.logout-link');
    logoutLinks.forEach(link => {
        link.addEventListener('click', confirmLogout);
    });
    
    // إضافة كلاس نشط للرابط الحالي في القائمة الجانبية
    const currentLink = document.querySelector(`.nav-menu a[href="${path.split('/').pop()}"]`);
    if (currentLink) {
        currentLink.classList.add('active');
    }
});
<?php
// includes/notification_icon.php
if (isLoggedIn()):
    $unread_count = getUnreadNotificationsCount($_SESSION['user_id']);
?>
<!-- أيقونة الإشعارات الثابتة -->
<div style="position: fixed; top: 20px; left: 20px; z-index: 9999;">
    <a href="notifications.php" style="text-decoration: none; display: block;">
        <div style="position: relative;">
            <!-- أيقونة الجرس -->
            <div style="
                width: 50px;
                height: 50px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 5px 15px rgba(102,126,234,0.4);
                transition: all 0.3s ease;
                cursor: pointer;
                border: 2px solid white;
            " onmouseover="this.style.transform='scale(1.1)'" 
               onmouseout="this.style.transform='scale(1)'">
                <span style="font-size: 1.5em; color: white;">🔔</span>
            </div>
            
            <!-- عداد الإشعارات -->
            <?php if ($unread_count > 0): ?>
                <div style="
                    position: absolute;
                    top: -5px;
                    right: -5px;
                    background: #dc3545;
                    color: white;
                    border-radius: 50%;
                    width: 22px;
                    height: 22px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 0.7em;
                    font-weight: bold;
                    border: 2px solid white;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                ">
                    <?php echo $unread_count; ?>
                </div>
            <?php endif; ?>
        </div>
    </a>
</div>

<!-- إضافة CSS للتحريك -->
<style>
    @keyframes bellRing {
        0% { transform: rotate(0deg); }
        10% { transform: rotate(15deg); }
        20% { transform: rotate(-15deg); }
        30% { transform: rotate(10deg); }
        40% { transform: rotate(-10deg); }
        50% { transform: rotate(5deg); }
        60% { transform: rotate(-5deg); }
        70% { transform: rotate(2deg); }
        80% { transform: rotate(-2deg); }
        90% { transform: rotate(1deg); }
        100% { transform: rotate(0deg); }
    }
    
    .bell-ring {
        animation: bellRing 0.5s ease-in-out;
    }
</style>

<script>
// إضافة تأثير الرنين عند وجود إشعارات جديدة
<?php if ($unread_count > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
    const bell = document.querySelector('.bell-ring-target');
    if (bell) {
        bell.classList.add('bell-ring');
        setTimeout(() => {
            bell.classList.remove('bell-ring');
        }, 1000);
    }
});
<?php endif; ?>
</script>
<?php endif; ?>
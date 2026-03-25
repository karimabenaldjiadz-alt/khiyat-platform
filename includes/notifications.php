<?php
// includes/notifications.php

/**
 * إضافة إشعار جديد
 */
function addNotification($user_id, $type, $title, $message, $link = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, message, link) 
        VALUES (?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$user_id, $type, $title, $message, $link]);
}

/**
 * جلب إشعارات المستخدم
 */
function getUserNotifications($user_id, $limit = 50) {
    global $pdo;
    
    // استخدام bindValue مع تحديد النوع integer
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC 
        LIMIT :limit
    ");
    
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * جلب عدد الإشعارات غير المقروءة
 */
function getUnreadNotificationsCount($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result ? $result['count'] : 0;
}

/**
 * تحديد إشعار كمقروء
 */
function markNotificationAsRead($notification_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
    return $stmt->execute([$notification_id]);
}

/**
 * تحديد كل الإشعارات كمقروءة
 */
function markAllNotificationsAsRead($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    return $stmt->execute([$user_id]);
}
?>
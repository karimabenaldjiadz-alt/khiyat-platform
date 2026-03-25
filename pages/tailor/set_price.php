<?php
// pages/tailor/set_price.php
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/notifications.php';

if (!isLoggedIn() || !hasRole('tailor')) {
    redirect('../login.php');
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$price = isset($_GET['price']) ? floatval($_GET['price']) : 0;

if ($order_id && $price > 0) {
    // جلب معلومات الطلب
    $stmt = $pdo->prepare("
        SELECT o.*, c.user_id as customer_user_id, u.full_name as customer_name 
        FROM `order` o
        JOIN customer c ON o.customer_id = c.customer_id
        JOIN user u ON c.user_id = u.user_id
        WHERE o.order_id = ? AND o.tailor_id = (SELECT tailor_id FROM tailor WHERE user_id = ?)
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if ($order) {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE `order` 
            SET tailor_price = ?, price_status = 'quoted' 
            WHERE order_id = ?
        ");
        $stmt->execute([$price, $order_id]);
        
        // إضافة إشعار للزبون
        addNotification(
            $order['customer_user_id'],
            'price',
            '💰 تم تحديد السعر',
            'قام الخياط بتحديد سعر ' . number_format($price, 0) . ' د.ج لطلبك #' . $order_id,
            'customer/my_orders.php'
        );
        
        $pdo->commit();
        
        $_SESSION['success'] = 'تم تحديد السعر بنجاح';
    }
}

redirect('view_requests.php');
?>
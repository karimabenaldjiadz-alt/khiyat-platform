<?php
// includes/functions.php

function redirect($url) {
    header('Location: ' . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($role) {
    return (isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role);
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
?>
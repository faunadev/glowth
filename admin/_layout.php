<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/shop.php';

const ORDER_STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
const PRODUCT_SUBS = ['Makeup' => ['Face', 'Lips', 'Eyes'], 'Skincare' => ['Cleanse', 'Treat', 'Protect']];

function require_admin() {
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') {
        redirect('login.php');
    }
    return $user;
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function check_form() {
    if (!csrf_ok($_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('this form expired, go back and reload the page');
    }
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function flash($message = null) {
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return '';
    }
    $text = $_SESSION['flash'] ?? '';
    unset($_SESSION['flash']);
    return $text;
}

// image paths are stored relative to the site root
function asset_url($path) {
    if ($path === '') return '';
    return preg_match('#^https?://#', $path) ? $path : '../' . $path;
}

function badge($status) {
    return '<span class="badge ' . e($status) . '">' . e($status) . '</span>';
}

function admin_head($title, $active) {
    $user = require_admin();
    $links = [
        'index' => 'dashboard', 'orders' => 'orders', 'products' => 'products',
        'coupons' => 'coupons', 'customers' => 'customers', 'settings' => 'settings',
    ];
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . e($title) . ' | glowth admin</title>'
       . '<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@900&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">'
       . '<link rel="stylesheet" href="admin.css"></head><body><div class="shell"><aside class="side">'
       . '<div class="brand">GLOWTH<small>admin</small></div><nav>';
    foreach ($links as $file => $label) {
        echo '<a href="' . $file . '.php"' . ($active === $file ? ' class="on"' : '') . '>' . $label . '</a>';
    }
    echo '</nav><div class="side-foot"><a href="../index.html" target="_blank">view store</a>'
       . '<a href="logout.php">sign out (' . e($user['name']) . ')</a></div></aside><main class="main">';
    $note = flash();
    if ($note !== '') {
        echo '<div class="flash">' . e($note) . '</div>';
    }
}

function admin_foot() {
    echo '</main></div></body></html>';
}

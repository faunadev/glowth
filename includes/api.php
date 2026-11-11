<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/shop.php';

ini_set('display_errors', '0');
set_exception_handler(function ($e) {
    error_log('glowth api: ' . $e->getMessage());
    fail('something went wrong on the server', 500);
});

function json_in() {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function post_only() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail('post only', 405);
    }
    if (!csrf_ok($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        fail('your session expired, please refresh the page', 403);
    }
}

function login_required() {
    $user = current_user();
    if (!$user) {
        fail('please sign in first', 401);
    }
    return $user;
}

function favorite_ids($userId) {
    $st = db()->prepare('select product_id from favorites where user_id = ? order by product_id');
    $st->execute([$userId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function me_payload() {
    $user = current_user();
    $payload = ['user' => null, 'csrf' => csrf_token(), 'coupons' => [], 'favorites' => []];
    if ($user) {
        $payload['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'role' => $user['role'],
        ];
        $payload['coupons'] = usable_coupons($user['id']);
        $payload['favorites'] = favorite_ids($user['id']);
    }
    return $payload;
}

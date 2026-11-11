<?php
require_once __DIR__ . '/../config.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

function db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('could not connect to the database. check config.php and import database/glowth.sql first.');
        }
    }
    return $pdo;
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function peso($amount) {
    $amount = (float) $amount;
    return '₱' . number_format($amount, fmod($amount, 1) == 0 ? 0 : 2);
}

function setting($key, $default = null) {
    static $all = null;
    if ($all === null) {
        $all = [];
        foreach (db()->query('select key_name, setting_value from settings') as $row) {
            $all[$row['key_name']] = $row['setting_value'];
        }
    }
    return $all[$key] ?? $default;
}

function current_user($refresh = false) {
    static $user = false;
    if ($refresh) {
        $user = false;
    }
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $st = db()->prepare('select id, name, email, phone, role, is_active from users where id = ?');
            $st->execute([$_SESSION['user_id']]);
            $row = $st->fetch();
            if ($row && $row['is_active']) {
                $user = $row;
            }
        }
    }
    return $user;
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_ok($token) {
    return is_string($token) && hash_equals(csrf_token(), $token);
}

function json_out($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function fail($message, $status = 422) {
    json_out(['error' => $message], $status);
}

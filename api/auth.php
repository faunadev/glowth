<?php
require __DIR__ . '/../includes/api.php';

$action = $_GET['action'] ?? 'me';

if ($action === 'me') {
    json_out(me_payload());
}

post_only();
$in = json_in();

if ($action === 'register') {
    $name = trim($in['name'] ?? '');
    $email = strtolower(trim($in['email'] ?? ''));
    $phone = trim($in['phone'] ?? '');
    $password = (string) ($in['password'] ?? '');

    if (mb_strlen($name) < 2) fail('please enter your full name');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('please enter a valid email address');
    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) fail('please enter a valid phone number');
    if (strlen($password) < 6) fail('your password needs at least 6 characters');

    $st = db()->prepare('select 1 from users where email = ?');
    $st->execute([$email]);
    if ($st->fetchColumn()) fail('an account with that email already exists');

    $st = db()->prepare('insert into users (name, email, phone, password_hash) values (?, ?, ?, ?)');
    $st->execute([$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int) db()->lastInsertId();
    $welcome = make_welcome_coupon($userId);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    current_user(true);

    $payload = me_payload();
    $payload['welcome_coupon'] = $welcome;
    json_out($payload);
}

if ($action === 'login') {
    $email = strtolower(trim($in['email'] ?? ''));
    $st = db()->prepare('select * from users where email = ?');
    $st->execute([$email]);
    $row = $st->fetch();

    if (!$row || !password_verify((string) ($in['password'] ?? ''), $row['password_hash'])) {
        fail('wrong email or password');
    }
    if (!$row['is_active']) {
        fail('this account has been disabled');
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    current_user(true);
    json_out(me_payload());
}

if ($action === 'logout') {
    unset($_SESSION['user_id']);
    current_user(true);
    json_out(me_payload());
}

fail('unknown action', 404);

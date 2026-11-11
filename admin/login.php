<?php
require_once __DIR__ . '/_layout.php';

$user = current_user();
if ($user && $user['role'] === 'admin') {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form();
    $st = db()->prepare("select * from users where email = ? and role = 'admin' and is_active = 1");
    $st->execute([strtolower(trim($_POST['email'] ?? ''))]);
    $row = $st->fetch();
    if ($row && password_verify($_POST['password'] ?? '', $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $row['id'];
        redirect('index.php');
    }
    $error = 'wrong email or password';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>admin sign in | glowth</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@900&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="admin.css">
</head>
<body class="login-page">
<form class="login-box" method="post">
    <div class="brand dark">GLOWTH<small>admin</small></div>
    <?php if ($error): ?><div class="flash bad"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>email<input type="email" name="email" required autofocus></label>
    <label>password<input type="password" name="password" required></label>
    <button class="btn" type="submit">sign in</button>
</form>
</body>
</html>

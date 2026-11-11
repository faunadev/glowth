<?php
require_once __DIR__ . '/_layout.php';

unset($_SESSION['user_id']);
redirect('login.php');

<?php
// database settings, change these to match your server
if (!defined('DB_DSN')) {
    define('DB_DSN', 'mysql:host=localhost;dbname=glowth;charset=utf8mb4');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

date_default_timezone_set('Asia/Manila');

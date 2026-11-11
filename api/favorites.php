<?php
require __DIR__ . '/../includes/api.php';

post_only();
$user = login_required();
$productId = (int) (json_in()['product_id'] ?? 0);

$st = db()->prepare('select 1 from products where id = ? and is_active = 1');
$st->execute([$productId]);
if (!$st->fetchColumn()) {
    fail('product not found', 404);
}

$st = db()->prepare('select 1 from favorites where user_id = ? and product_id = ?');
$st->execute([$user['id'], $productId]);
if ($st->fetchColumn()) {
    $st = db()->prepare('delete from favorites where user_id = ? and product_id = ?');
} else {
    $st = db()->prepare('insert into favorites (user_id, product_id) values (?, ?)');
}
$st->execute([$user['id'], $productId]);

json_out(['favorites' => favorite_ids($user['id'])]);

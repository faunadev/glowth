<?php
require __DIR__ . '/../includes/api.php';

$products = [];
foreach (db()->query('select * from products where is_active = 1 order by id') as $p) {
    $products[] = [
        'id' => (int) $p['id'],
        'name' => $p['name'],
        'category' => $p['category'],
        'sub' => $p['sub_category'],
        'price' => (float) $p['price'],
        'oldPrice' => $p['old_price'] !== null ? (float) $p['old_price'] : null,
        'image' => $p['image'],
        'description' => $p['description'],
        'rating' => (float) $p['rating'],
        'tag' => $p['tag'],
        'stock' => (int) $p['stock'],
    ];
}

json_out([
    'products' => $products,
    'settings' => [
        'shippingFee' => (float) setting('shipping_fee', 99),
        'freeShippingMin' => (float) setting('free_shipping_min', 1500),
        'welcomePercent' => (float) setting('welcome_percent', 15),
    ],
]);

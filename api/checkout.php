<?php
require __DIR__ . '/../includes/api.php';

post_only();
$in = json_in();
$action = $in['action'] ?? 'quote';
$items = is_array($in['items'] ?? null) ? $in['items'] : [];
$code = (string) ($in['coupon'] ?? '');
$user = current_user();

if ($action === 'quote') {
    json_out(price_cart($items, $code, $user));
}

if ($action !== 'place') {
    fail('unknown action', 404);
}

$user = login_required();
$c = is_array($in['customer'] ?? null) ? $in['customer'] : [];
$name = trim($c['name'] ?? '');
$email = strtolower(trim($c['email'] ?? ''));
$phone = trim($c['phone'] ?? '');
$address = trim($c['address'] ?? '');
$city = trim($c['city'] ?? '');
$postal = trim($c['postal_code'] ?? '');
$payment = $c['payment'] ?? '';

if (mb_strlen($name) < 2) fail('please enter your full name');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('please enter a valid email address');
if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) fail('please enter a valid phone number');
if (mb_strlen($address) < 5) fail('please enter your address');
if (mb_strlen($city) < 2) fail('please enter your city');
if (!preg_match('/^[0-9A-Za-z\- ]{3,10}$/', $postal)) fail('please enter a valid postal code');
if (!in_array($payment, ['Cash on Delivery', 'GCash', 'Credit/Debit Card'], true)) fail('please choose a payment method');

$db = db();
$db->beginTransaction();
try {
    $priced = price_cart($items, $code, $user, true);
    if ($priced['error']) {
        throw new RuntimeException($priced['error']);
    }
    if (trim($code) !== '' && $priced['coupon_error']) {
        throw new RuntimeException($priced['coupon_error']);
    }

    do {
        $orderNo = 'GLW-' . date('ymd') . '-' . random_int(1000, 9999);
        $st = $db->prepare('select 1 from orders where order_no = ?');
        $st->execute([$orderNo]);
    } while ($st->fetchColumn());

    $st = $db->prepare(
        'insert into orders (order_no, user_id, customer_name, email, phone, address, city, postal_code, payment_method,
                             subtotal, discount, shipping_fee, total, coupon_code)
         values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $st->execute([
        $orderNo, $user['id'], $name, $email, $phone, $address, $city, $postal, $payment,
        $priced['subtotal'], $priced['discount'], $priced['shipping'], $priced['total'], $priced['coupon'],
    ]);
    $orderId = (int) $db->lastInsertId();

    $addItem = $db->prepare('insert into order_items (order_id, product_id, product_name, price, quantity) values (?, ?, ?, ?, ?)');
    $takeStock = $db->prepare('update products set stock = stock - ? where id = ? and stock >= ?');
    foreach ($priced['lines'] as $line) {
        $addItem->execute([$orderId, $line['id'], $line['name'], $line['price'], $line['qty']]);
        $takeStock->execute([$line['qty'], $line['id'], $line['qty']]);
        if ($takeStock->rowCount() !== 1) {
            throw new RuntimeException('sorry, ' . $line['name'] . ' just sold out');
        }
    }

    if ($priced['coupon_id']) {
        $db->prepare('update coupons set used_count = used_count + 1 where id = ?')->execute([$priced['coupon_id']]);
        $db->prepare('insert into coupon_redemptions (coupon_id, user_id, order_id) values (?, ?, ?)')
           ->execute([$priced['coupon_id'], $user['id'], $orderId]);
    }

    $db->commit();
} catch (RuntimeException $e) {
    $db->rollBack();
    fail($e->getMessage());
}

json_out(['order' => [
    'order_no' => $orderNo,
    'customer_name' => $name,
    'email' => $email,
    'payment_method' => $payment,
    'address' => "$address, $city $postal",
    'status' => 'pending',
    'coupon_code' => $priced['coupon'],
    'subtotal' => $priced['subtotal'],
    'discount' => $priced['discount'],
    'shipping_fee' => $priced['shipping'],
    'total' => $priced['total'],
    'items' => array_map(fn($l) => ['name' => $l['name'], 'price' => $l['price'], 'quantity' => $l['qty']], $priced['lines']),
]]);

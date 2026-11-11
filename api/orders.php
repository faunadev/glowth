<?php
require __DIR__ . '/../includes/api.php';

$action = $_GET['action'] ?? 'mine';

function load_orders($where, $params) {
    $st = db()->prepare("select * from orders where $where order by id desc limit 50");
    $st->execute($params);
    $orders = [];
    foreach ($st->fetchAll() as $o) {
        $items = db()->prepare('select product_name as name, price, quantity from order_items where order_id = ?');
        $items->execute([$o['id']]);
        $orders[] = [
            'order_no' => $o['order_no'],
            'status' => $o['status'],
            'payment_method' => $o['payment_method'],
            'created_at' => $o['created_at'],
            'coupon_code' => $o['coupon_code'],
            'subtotal' => (float) $o['subtotal'],
            'discount' => (float) $o['discount'],
            'shipping_fee' => (float) $o['shipping_fee'],
            'total' => (float) $o['total'],
            'items' => array_map(fn($i) => ['name' => $i['name'], 'price' => (float) $i['price'], 'quantity' => (int) $i['quantity']], $items->fetchAll()),
        ];
    }
    return $orders;
}

if ($action === 'mine') {
    $user = login_required();
    json_out(['orders' => load_orders('user_id = ?', [$user['id']])]);
}

if ($action === 'track') {
    $orderNo = strtoupper(trim($_GET['order_no'] ?? ''));
    $email = strtolower(trim($_GET['email'] ?? ''));
    if ($orderNo === '' || $email === '') {
        fail('enter your order number and the email used for the order');
    }
    $orders = load_orders('order_no = ? and lower(email) = ?', [$orderNo, $email]);
    if (!$orders) {
        fail('we could not find an order with those details', 404);
    }
    json_out(['orders' => $orders]);
}

fail('unknown action', 404);

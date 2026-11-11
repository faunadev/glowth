<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$db = db();

$id = (int) ($_GET['id'] ?? 0);
$st = $db->prepare('select * from orders where id = ?');
$st->execute([$id]);
$order = $st->fetch();
if (!$order) {
    flash('that order does not exist');
    redirect('orders.php');
}
$st = $db->prepare('select * from order_items where order_id = ?');
$st->execute([$id]);
$items = $st->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form();
    $new = $_POST['status'] ?? '';
    if (!in_array($new, ORDER_STATUSES, true) || $new === $order['status']) {
        redirect("order.php?id=$id");
    }
    if ($order['status'] === 'cancelled') {
        flash('a cancelled order cannot be changed');
        redirect("order.php?id=$id");
    }

    $db->beginTransaction();
    if ($new === 'cancelled') {
        // put the stock back and free the coupon so the customer can use it again
        foreach ($items as $item) {
            if ($item['product_id']) {
                $db->prepare('update products set stock = stock + ? where id = ?')->execute([$item['quantity'], $item['product_id']]);
            }
        }
        $used = $db->prepare('select coupon_id from coupon_redemptions where order_id = ?');
        $used->execute([$id]);
        foreach ($used->fetchAll(PDO::FETCH_COLUMN) as $couponId) {
            $db->prepare('update coupons set used_count = used_count - 1 where id = ? and used_count > 0')->execute([$couponId]);
        }
        $db->prepare('delete from coupon_redemptions where order_id = ?')->execute([$id]);
    }
    $db->prepare('update orders set status = ? where id = ?')->execute([$new, $id]);
    $db->commit();

    flash("order marked as $new");
    redirect("order.php?id=$id");
}

admin_head('order ' . $order['order_no'], 'orders');
?>
<div class="head-row">
    <h1>order <?= e($order['order_no']) ?> <?= badge($order['status']) ?></h1>
    <a class="btn light" href="orders.php">back to orders</a>
</div>
<div class="cols">
    <section class="card">
        <h2>customer</h2>
        <p><strong><?= e($order['customer_name']) ?></strong><br>
        <?= e($order['email']) ?><br><?= e($order['phone']) ?></p>
        <h2>ship to</h2>
        <p><?= e($order['address']) ?><br><?= e($order['city']) ?> <?= e($order['postal_code']) ?></p>
        <h2>payment</h2>
        <p><?= e($order['payment_method']) ?><br><small class="muted">placed <?= e(date('M j, Y g:i a', strtotime($order['created_at']))) ?></small></p>
    </section>
    <section class="card">
        <h2>update status</h2>
        <?php if ($order['status'] === 'cancelled'): ?>
            <p class="muted">this order was cancelled, so its status is locked.</p>
        <?php else: ?>
            <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <select name="status">
                    <?php foreach (ORDER_STATUSES as $s): ?><option <?= $order['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
                </select>
                <button class="btn" type="submit">save</button>
            </form>
            <p class="muted small">cancelling puts the items back in stock and frees the coupon.</p>
        <?php endif; ?>
    </section>
</div>
<section class="card">
    <h2>items</h2>
    <table>
        <tr><th>product</th><th>price</th><th>qty</th><th class="right">total</th></tr>
        <?php foreach ($items as $i): ?>
            <tr><td><?= e($i['product_name']) ?></td><td><?= peso($i['price']) ?></td><td><?= (int) $i['quantity'] ?></td><td class="right"><?= peso($i['price'] * $i['quantity']) ?></td></tr>
        <?php endforeach; ?>
        <tr><td colspan="3" class="right">subtotal</td><td class="right"><?= peso($order['subtotal']) ?></td></tr>
        <?php if ($order['discount'] > 0): ?>
            <tr><td colspan="3" class="right">coupon <?= e($order['coupon_code']) ?></td><td class="right">- <?= peso($order['discount']) ?></td></tr>
        <?php endif; ?>
        <tr><td colspan="3" class="right">shipping</td><td class="right"><?= $order['shipping_fee'] > 0 ? peso($order['shipping_fee']) : 'free' ?></td></tr>
        <tr class="grand"><td colspan="3" class="right">total</td><td class="right"><?= peso($order['total']) ?></td></tr>
    </table>
</section>
<?php admin_foot(); ?>

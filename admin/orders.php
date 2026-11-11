<?php
require_once __DIR__ . '/_layout.php';
require_admin();

$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = ['1 = 1'];
$params = [];
if (in_array($status, ORDER_STATUSES, true)) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($search !== '') {
    $where[] = '(order_no like ? or customer_name like ? or email like ?)';
    array_push($params, "%$search%", "%$search%", "%$search%");
}
$st = db()->prepare('select * from orders where ' . implode(' and ', $where) . ' order by id desc limit 200');
$st->execute($params);
$orders = $st->fetchAll();

admin_head('orders', 'orders');
?>
<h1>orders</h1>
<form class="filters" method="get">
    <select name="status">
        <option value="">all statuses</option>
        <?php foreach (ORDER_STATUSES as $s): ?><option <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
    </select>
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="order no, name or email">
    <button class="btn light" type="submit">filter</button>
</form>
<section class="card">
    <table>
        <tr><th>order</th><th>customer</th><th>payment</th><th>coupon</th><th>total</th><th>status</th><th>placed</th></tr>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><a href="order.php?id=<?= (int) $o['id'] ?>"><?= e($o['order_no']) ?></a></td>
                <td><?= e($o['customer_name']) ?><br><small class="muted"><?= e($o['email']) ?></small></td>
                <td><?= e($o['payment_method']) ?></td>
                <td><?= $o['coupon_code'] ? e($o['coupon_code']) : '<span class="muted">none</span>' ?></td>
                <td><?= peso($o['total']) ?></td>
                <td><?= badge($o['status']) ?></td>
                <td><?= e(date('M j, g:i a', strtotime($o['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="7" class="muted">no orders match.</td></tr><?php endif; ?>
    </table>
</section>
<?php admin_foot(); ?>

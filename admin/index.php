<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$db = db();

$totals = $db->query("select count(*) as orders, coalesce(sum(case when status <> 'cancelled' then total else 0 end), 0) as revenue from orders")->fetch();
$pending = $db->query("select count(*) from orders where status = 'pending'")->fetchColumn();
$customers = $db->query("select count(*) from users where role = 'customer'")->fetchColumn();
$activeCoupons = $db->query('select count(*) from coupons where is_active = 1')->fetchColumn();
$lowStock = $db->query('select id, name, stock from products where is_active = 1 and stock <= 5 order by stock, name limit 6')->fetchAll();
$recent = $db->query('select id, order_no, customer_name, total, status, created_at from orders order by id desc limit 6')->fetchAll();

// last seven days of sales
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i days"))] = ['orders' => 0, 'sales' => 0];
}
$st = $db->prepare("select date(created_at) as d, count(*) as c, sum(total) as t from orders where status <> 'cancelled' and created_at >= ? group by date(created_at)");
$st->execute([date('Y-m-d 00:00:00', strtotime('-6 days'))]);
foreach ($st->fetchAll() as $row) {
    if (isset($days[$row['d']])) {
        $days[$row['d']] = ['orders' => (int) $row['c'], 'sales' => (float) $row['t']];
    }
}
$peak = max(1, max(array_column($days, 'sales')));

admin_head('dashboard', 'index');
?>
<h1>dashboard</h1>
<div class="stats">
    <div class="stat"><span>revenue</span><strong><?= peso($totals['revenue']) ?></strong></div>
    <div class="stat"><span>orders</span><strong><?= (int) $totals['orders'] ?></strong></div>
    <div class="stat"><span>pending orders</span><strong><?= (int) $pending ?></strong></div>
    <div class="stat"><span>customers</span><strong><?= (int) $customers ?></strong></div>
    <div class="stat"><span>active coupons</span><strong><?= (int) $activeCoupons ?></strong></div>
</div>

<div class="cols">
    <section class="card">
        <h2>sales, last 7 days</h2>
        <?php foreach ($days as $date => $d): ?>
            <div class="bar-row">
                <span><?= e(date('D j', strtotime($date))) ?></span>
                <div class="bar"><i style="width: <?= round($d['sales'] / $peak * 100) ?>%"></i></div>
                <em><?= peso($d['sales']) ?></em>
            </div>
        <?php endforeach; ?>
    </section>
    <section class="card">
        <h2>low stock</h2>
        <?php if (!$lowStock): ?><p class="muted">everything is well stocked.</p><?php endif; ?>
        <table>
            <?php foreach ($lowStock as $p): ?>
                <tr><td><a href="product_form.php?id=<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a></td><td class="right"><?= (int) $p['stock'] ?> left</td></tr>
            <?php endforeach; ?>
        </table>
    </section>
</div>

<section class="card">
    <h2>recent orders</h2>
    <table>
        <tr><th>order</th><th>customer</th><th>total</th><th>status</th><th>placed</th></tr>
        <?php foreach ($recent as $o): ?>
            <tr>
                <td><a href="order.php?id=<?= (int) $o['id'] ?>"><?= e($o['order_no']) ?></a></td>
                <td><?= e($o['customer_name']) ?></td>
                <td><?= peso($o['total']) ?></td>
                <td><?= badge($o['status']) ?></td>
                <td><?= e(date('M j, g:i a', strtotime($o['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="5" class="muted">no orders yet.</td></tr><?php endif; ?>
    </table>
</section>
<?php admin_foot(); ?>

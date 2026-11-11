<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['do'] ?? '') === 'toggle') {
        $db->prepare('update coupons set is_active = 1 - is_active where id = ?')->execute([$id]);
        flash('coupon updated');
    }
    if (($_POST['do'] ?? '') === 'delete') {
        $db->prepare('delete from coupons where id = ?')->execute([$id]);
        flash('coupon deleted');
    }
    redirect('coupons.php');
}

$tab = $_GET['tab'] ?? 'all';
$where = $tab === 'public' ? 'where c.assigned_user_id is null' : ($tab === 'personal' ? 'where c.assigned_user_id is not null' : '');
$coupons = $db->query(
    "select c.*, u.email as owner from coupons c left join users u on u.id = c.assigned_user_id $where order by c.id desc limit 300"
)->fetchAll();
$now = date('Y-m-d H:i:s');

function coupon_state($c, $now) {
    if (!$c['is_active']) return 'disabled';
    if ($c['expires_at'] && $c['expires_at'] < $now) return 'expired';
    if ($c['starts_at'] && $c['starts_at'] > $now) return 'pending';
    if ($c['max_uses'] !== null && $c['used_count'] >= $c['max_uses']) return 'redeemed';
    return 'active';
}

admin_head('coupons', 'coupons');
?>
<div class="head-row">
    <h1>coupons</h1>
    <a class="btn" href="coupon_form.php">create coupon</a>
</div>
<div class="tabs">
    <?php foreach (['all' => 'all', 'public' => 'for everyone', 'personal' => 'personal'] as $key => $label): ?>
        <a href="?tab=<?= $key ?>" class="<?= $tab === $key ? 'on' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>
<section class="card">
    <table>
        <tr><th>code</th><th>discount</th><th>min spend</th><th>used</th><th>for</th><th>valid</th><th>status</th><th></th></tr>
        <?php foreach ($coupons as $c): $state = coupon_state($c, $now); ?>
            <tr>
                <td><strong class="mono"><?= e($c['code']) ?></strong><br><small class="muted"><?= e($c['description']) ?></small></td>
                <td><?= e(discount_text($c)) ?></td>
                <td><?= $c['min_spend'] > 0 ? peso($c['min_spend']) : '<span class="muted">none</span>' ?></td>
                <td><?= (int) $c['used_count'] ?> / <?= $c['max_uses'] === null ? 'unlimited' : (int) $c['max_uses'] ?></td>
                <td>
                    <?php if ($c['owner']): ?><?= e($c['owner']) ?>
                    <?php elseif ($c['new_user_only']): ?>new customers
                    <?php else: ?>everyone<?php endif; ?>
                </td>
                <td class="small">
                    <?= $c['starts_at'] ? e(date('M j, Y', strtotime($c['starts_at']))) : 'now' ?> to
                    <?= $c['expires_at'] ? e(date('M j, Y', strtotime($c['expires_at']))) : 'no end' ?>
                </td>
                <td><?= badge($state) ?></td>
                <td class="actions">
                    <a href="coupon_ticket.php?id=<?= (int) $c['id'] ?>">ticket</a>
                    <a href="coupon_form.php?id=<?= (int) $c['id'] ?>">edit</a>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="do" value="toggle"><button class="link"><?= $c['is_active'] ? 'disable' : 'enable' ?></button></form>
                    <form method="post" onsubmit="return confirm('delete this coupon?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="do" value="delete"><button class="link danger">delete</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$coupons): ?><tr><td colspan="8" class="muted">no coupons here yet.</td></tr><?php endif; ?>
    </table>
</section>
<?php admin_foot(); ?>

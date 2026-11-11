<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form();
    $db->prepare("update users set is_active = 1 - is_active where id = ? and role = 'customer'")->execute([(int) ($_POST['id'] ?? 0)]);
    flash('account updated');
    redirect('customers.php');
}

$customers = $db->query(
    "select u.*, count(o.id) as orders, coalesce(sum(case when o.status <> 'cancelled' then o.total else 0 end), 0) as spent
     from users u left join orders o on o.user_id = u.id
     where u.role = 'customer' group by u.id order by u.id desc"
)->fetchAll();

admin_head('customers', 'customers');
?>
<h1>customers</h1>
<section class="card">
    <table>
        <tr><th>name</th><th>email</th><th>phone</th><th>orders</th><th>spent</th><th>joined</th><th>status</th><th></th></tr>
        <?php foreach ($customers as $c): ?>
            <tr>
                <td><strong><?= e($c['name']) ?></strong></td>
                <td><?= e($c['email']) ?></td>
                <td><?= e($c['phone']) ?></td>
                <td><?= (int) $c['orders'] ?></td>
                <td><?= peso($c['spent']) ?></td>
                <td><?= e(date('M j, Y', strtotime($c['created_at']))) ?></td>
                <td><?= $c['is_active'] ? badge('active') : badge('disabled') ?></td>
                <td class="actions">
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="link <?= $c['is_active'] ? 'danger' : '' ?>"><?= $c['is_active'] ? 'disable' : 'enable' ?></button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$customers): ?><tr><td colspan="8" class="muted">no customers yet.</td></tr><?php endif; ?>
    </table>
</section>
<?php admin_foot(); ?>

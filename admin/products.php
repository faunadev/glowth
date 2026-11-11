<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['do'] ?? '') === 'toggle') {
        $db->prepare('update products set is_active = 1 - is_active where id = ?')->execute([$id]);
        flash('product visibility updated');
    }
    if (($_POST['do'] ?? '') === 'delete') {
        $db->prepare('delete from products where id = ?')->execute([$id]);
        flash('product deleted. past orders keep their item names.');
    }
    redirect('products.php');
}

$search = trim($_GET['q'] ?? '');
$st = $db->prepare('select * from products where name like ? or category like ? or sub_category like ? order by id desc');
$like = '%' . $search . '%';
$st->execute([$like, $like, $like]);
$products = $st->fetchAll();

admin_head('products', 'products');
?>
<div class="head-row">
    <h1>products</h1>
    <a class="btn" href="product_form.php">add product</a>
</div>
<form class="filters" method="get">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="search by name or category">
    <button class="btn light" type="submit">search</button>
</form>
<section class="card">
    <table>
        <tr><th></th><th>name</th><th>category</th><th>price</th><th>stock</th><th>status</th><th></th></tr>
        <?php foreach ($products as $p): ?>
            <tr>
                <td><?php if ($p['image']): ?><img class="thumb" src="<?= e(asset_url($p['image'])) ?>" alt=""><?php endif; ?></td>
                <td><strong><?= e($p['name']) ?></strong><?= $p['tag'] ? ' <small class="muted">' . e($p['tag']) . '</small>' : '' ?></td>
                <td><?= e($p['category']) ?> / <?= e($p['sub_category']) ?></td>
                <td><?= peso($p['price']) ?><?= $p['old_price'] ? ' <s class="muted">' . peso($p['old_price']) . '</s>' : '' ?></td>
                <td class="<?= $p['stock'] <= 5 ? 'warn' : '' ?>"><?= (int) $p['stock'] ?></td>
                <td><?= $p['is_active'] ? badge('active') : badge('hidden') ?></td>
                <td class="actions">
                    <a href="product_form.php?id=<?= (int) $p['id'] ?>">edit</a>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="do" value="toggle"><button class="link"><?= $p['is_active'] ? 'hide' : 'show' ?></button></form>
                    <form method="post" onsubmit="return confirm('delete this product?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="do" value="delete"><button class="link danger">delete</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?><tr><td colspan="7" class="muted">no products found.</td></tr><?php endif; ?>
    </table>
</section>
<?php admin_foot(); ?>

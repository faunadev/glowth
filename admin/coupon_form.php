<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$db = db();

$id = (int) ($_GET['id'] ?? 0);
$coupon = [
    'code' => '', 'description' => '', 'discount_type' => 'percent', 'discount_value' => '', 'min_spend' => '0',
    'max_uses' => '', 'per_user_limit' => 1, 'new_user_only' => 0, 'starts_at' => '', 'expires_at' => '', 'is_active' => 1,
];
$ownerEmail = '';
if ($id) {
    $st = $db->prepare('select c.*, u.email as owner from coupons c left join users u on u.id = c.assigned_user_id where c.id = ?');
    $st->execute([$id]);
    $found = $st->fetch();
    if (!$found) {
        flash('that coupon does not exist');
        redirect('coupons.php');
    }
    $coupon = $found;
    $ownerEmail = $found['owner'] ?? '';
}

function to_local($value) {
    return $value ? date('Y-m-d\TH:i', strtotime($value)) : '';
}
function from_local($value) {
    $value = trim($value);
    return $value === '' ? null : date('Y-m-d H:i:s', strtotime($value));
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form();
    $ownerEmail = strtolower(trim($_POST['owner_email'] ?? ''));
    $coupon = array_merge($coupon, [
        'code' => strtoupper(trim($_POST['code'] ?? '')),
        'description' => trim($_POST['description'] ?? ''),
        'discount_type' => $_POST['discount_type'] ?? 'percent',
        'discount_value' => trim($_POST['discount_value'] ?? ''),
        'min_spend' => trim($_POST['min_spend'] ?? '') === '' ? '0' : trim($_POST['min_spend']),
        'max_uses' => trim($_POST['max_uses'] ?? ''),
        'per_user_limit' => trim($_POST['per_user_limit'] ?? '1'),
        'new_user_only' => isset($_POST['new_user_only']) ? 1 : 0,
        'starts_at' => from_local($_POST['starts_at'] ?? ''),
        'expires_at' => from_local($_POST['expires_at'] ?? ''),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ]);

    if (!preg_match('/^[A-Z0-9-]{3,40}$/', $coupon['code'])) $errors[] = 'the code needs 3 to 40 letters, numbers or dashes';
    else {
        $st = $db->prepare('select id from coupons where code = ? and id <> ?');
        $st->execute([$coupon['code'], $id]);
        if ($st->fetch()) $errors[] = 'that code is already taken';
    }
    if (!in_array($coupon['discount_type'], ['percent', 'fixed'], true)) $errors[] = 'choose a discount type';
    if (!is_numeric($coupon['discount_value']) || $coupon['discount_value'] <= 0) $errors[] = 'enter a discount above zero';
    elseif ($coupon['discount_type'] === 'percent' && $coupon['discount_value'] > 100) $errors[] = 'a percent discount cannot be more than 100';
    if (!is_numeric($coupon['min_spend']) || $coupon['min_spend'] < 0) $errors[] = 'minimum spend must be zero or more';
    if ($coupon['max_uses'] !== '' && (!ctype_digit($coupon['max_uses']) || (int) $coupon['max_uses'] < 1)) $errors[] = 'total uses must be a whole number, or empty for unlimited';
    if (!ctype_digit((string) $coupon['per_user_limit']) || (int) $coupon['per_user_limit'] < 1) $errors[] = 'uses per customer must be at least 1';
    if ($coupon['starts_at'] && $coupon['expires_at'] && $coupon['expires_at'] <= $coupon['starts_at']) $errors[] = 'the end date must be after the start date';

    $ownerId = null;
    if ($ownerEmail !== '') {
        $st = $db->prepare("select id from users where email = ? and role = 'customer'");
        $st->execute([$ownerEmail]);
        $ownerId = $st->fetchColumn();
        if (!$ownerId) $errors[] = 'no customer has that email';
    }

    if (!$errors) {
        $values = [
            $coupon['code'], $coupon['description'], $coupon['discount_type'], $coupon['discount_value'], $coupon['min_spend'],
            $coupon['max_uses'] === '' ? null : (int) $coupon['max_uses'], (int) $coupon['per_user_limit'], $coupon['new_user_only'],
            $ownerId ?: null, $coupon['starts_at'], $coupon['expires_at'], $coupon['is_active'],
        ];
        if ($id) {
            $values[] = $id;
            $db->prepare('update coupons set code=?, description=?, discount_type=?, discount_value=?, min_spend=?, max_uses=?, per_user_limit=?, new_user_only=?, assigned_user_id=?, starts_at=?, expires_at=?, is_active=? where id=?')->execute($values);
            flash('coupon updated');
            redirect('coupons.php');
        }
        $db->prepare('insert into coupons (code, description, discount_type, discount_value, min_spend, max_uses, per_user_limit, new_user_only, assigned_user_id, starts_at, expires_at, is_active) values (?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values);
        flash('coupon created. you can print its ticket now.');
        redirect('coupon_ticket.php?id=' . $db->lastInsertId());
    }
}

admin_head($id ? 'edit coupon' : 'create coupon', 'coupons');
?>
<h1><?= $id ? 'edit coupon' : 'create coupon' ?></h1>
<?php foreach ($errors as $err): ?><div class="flash bad"><?= e($err) ?></div><?php endforeach; ?>
<form class="card form" method="post">
    <?= csrf_field() ?>
    <label>code
        <span class="inline-form">
            <input name="code" id="code" value="<?= e($coupon['code']) ?>" placeholder="SUMMER20" required style="text-transform:uppercase">
            <button type="button" class="btn light" id="gen">generate</button>
        </span>
    </label>
    <label>description (shown to the customer)<input name="description" value="<?= e($coupon['description']) ?>" maxlength="160"></label>
    <div class="two">
        <label>discount type
            <select name="discount_type">
                <option value="percent" <?= $coupon['discount_type'] === 'percent' ? 'selected' : '' ?>>percent off</option>
                <option value="fixed" <?= $coupon['discount_type'] === 'fixed' ? 'selected' : '' ?>>fixed peso amount off</option>
            </select>
        </label>
        <label>value<input name="discount_value" type="number" step="0.01" min="0" value="<?= e($coupon['discount_value']) ?>" required></label>
    </div>
    <div class="two">
        <label>minimum spend (₱)<input name="min_spend" type="number" step="0.01" min="0" value="<?= e($coupon['min_spend']) ?>"></label>
        <label>total uses (empty for unlimited)<input name="max_uses" type="number" min="1" value="<?= e($coupon['max_uses']) ?>"></label>
    </div>
    <div class="two">
        <label>uses per customer<input name="per_user_limit" type="number" min="1" value="<?= e($coupon['per_user_limit']) ?>"></label>
        <label>only for one customer (email, optional)<input name="owner_email" type="email" value="<?= e($ownerEmail) ?>" placeholder="leave empty for everyone"></label>
    </div>
    <div class="two">
        <label>starts<input name="starts_at" type="datetime-local" value="<?= e(to_local($coupon['starts_at'])) ?>"></label>
        <label>ends<input name="expires_at" type="datetime-local" value="<?= e(to_local($coupon['expires_at'])) ?>"></label>
    </div>
    <label class="check"><input type="checkbox" name="new_user_only" <?= $coupon['new_user_only'] ? 'checked' : '' ?>> new customers only (no earlier orders)</label>
    <label class="check"><input type="checkbox" name="is_active" <?= $coupon['is_active'] ? 'checked' : '' ?>> coupon is active</label>
    <div class="row-end">
        <a class="btn light" href="coupons.php">cancel</a>
        <button class="btn" type="submit">save coupon</button>
    </div>
</form>
<script>
document.getElementById('gen').addEventListener('click', () => {
    const letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = 'GLOW-';
    for (let i = 0; i < 6; i++) code += letters[Math.floor(Math.random() * letters.length)];
    document.getElementById('code').value = code;
});
</script>
<?php admin_foot(); ?>

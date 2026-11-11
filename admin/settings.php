<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$db = db();

$fields = [
    'shipping_fee' => ['shipping fee (₱)', 0],
    'free_shipping_min' => ['free shipping above (₱)', 0],
    'welcome_percent' => ['welcome coupon discount (%)', 1],
    'welcome_days' => ['welcome coupon valid for (days)', 1],
];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form();
    $new = [];
    foreach ($fields as $key => [$label, $min]) {
        $value = trim($_POST[$key] ?? '');
        if (!is_numeric($value) || $value < $min) {
            $errors[] = "$label must be a number" . ($min ? " of at least $min" : '');
        }
        $new[$key] = $value;
    }
    if (($new['welcome_percent'] ?? 0) > 100) $errors[] = 'the welcome discount cannot be over 100%';
    if (!$errors) {
        foreach ($new as $key => $value) {
            $st = $db->prepare('update settings set setting_value = ? where key_name = ?');
            $st->execute([$value, $key]);
            if (!$st->rowCount() && !$db->query("select 1 from settings where key_name = " . $db->quote($key))->fetchColumn()) {
                $db->prepare('insert into settings (key_name, setting_value) values (?, ?)')->execute([$key, $value]);
            }
        }
        flash('settings saved');
        redirect('settings.php');
    }
}

admin_head('settings', 'settings');
?>
<h1>settings</h1>
<?php foreach ($errors as $err): ?><div class="flash bad"><?= e($err) ?></div><?php endforeach; ?>
<form class="card form" method="post">
    <?= csrf_field() ?>
    <?php foreach ($fields as $key => [$label, $min]): ?>
        <label><?= e($label) ?><input name="<?= e($key) ?>" type="number" step="0.01" min="<?= $min ?>" value="<?= e($_POST[$key] ?? setting($key, '')) ?>" required></label>
    <?php endforeach; ?>
    <p class="muted small">the welcome coupon is created automatically for every new account. changes only affect accounts made after saving.</p>
    <div class="row-end"><button class="btn" type="submit">save settings</button></div>
</form>
<?php admin_foot(); ?>

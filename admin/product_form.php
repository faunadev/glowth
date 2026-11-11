<?php
require_once __DIR__ . '/_layout.php';
require_admin();
$db = db();

$id = (int) ($_GET['id'] ?? 0);
$product = [
    'name' => '', 'category' => 'Makeup', 'sub_category' => 'Face', 'price' => '', 'old_price' => '',
    'image' => '', 'description' => '', 'rating' => '4.5', 'tag' => '', 'stock' => 50, 'is_active' => 1,
];
if ($id) {
    $st = $db->prepare('select * from products where id = ?');
    $st->execute([$id]);
    $found = $st->fetch();
    if (!$found) {
        flash('that product does not exist');
        redirect('products.php');
    }
    $product = $found;
}

function save_upload($field) {
    if (empty($_FILES[$field]['name'])) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('the photo could not be uploaded');
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('the photo is bigger than 3 mb');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) {
        throw new RuntimeException('use a jpg, png or webp photo');
    }
    $dir = __DIR__ . '/../uploads/products';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = bin2hex(random_bytes(8)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
        throw new RuntimeException('the photo could not be saved');
    }
    return 'uploads/products/' . $name;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_form();
    $product = array_merge($product, [
        'name' => trim($_POST['name'] ?? ''),
        'category' => $_POST['category'] ?? '',
        'sub_category' => $_POST['sub_category'] ?? '',
        'price' => trim($_POST['price'] ?? ''),
        'old_price' => trim($_POST['old_price'] ?? ''),
        'image' => trim($_POST['image'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'rating' => trim($_POST['rating'] ?? ''),
        'tag' => $_POST['tag'] ?? '',
        'stock' => trim($_POST['stock'] ?? ''),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ]);

    if (mb_strlen($product['name']) < 2) $errors[] = 'enter a product name';
    if (!isset(PRODUCT_SUBS[$product['category']])) $errors[] = 'choose a category';
    elseif (!in_array($product['sub_category'], PRODUCT_SUBS[$product['category']], true)) $errors[] = 'choose a sub-category for that category';
    if (!is_numeric($product['price']) || $product['price'] <= 0) $errors[] = 'enter a price above zero';
    if ($product['old_price'] !== '' && (!is_numeric($product['old_price']) || $product['old_price'] <= (float) $product['price'])) $errors[] = 'the old price must be higher than the price (leave it empty if not on sale)';
    if (!is_numeric($product['rating']) || $product['rating'] < 0 || $product['rating'] > 5) $errors[] = 'rating must be between 0 and 5';
    if (!ctype_digit((string) $product['stock'])) $errors[] = 'stock must be a whole number';
    if (!in_array($product['tag'], ['', 'New', 'Bestseller'], true)) $errors[] = 'choose a valid tag';
    if ($product['description'] === '') $errors[] = 'add a short description';

    try {
        $uploaded = save_upload('photo');
        if ($uploaded) $product['image'] = $uploaded;
    } catch (RuntimeException $e) {
        $errors[] = $e->getMessage();
    }
    if ($product['image'] === '') $errors[] = 'add an image link or upload a photo';

    if (!$errors) {
        $values = [
            $product['name'], $product['category'], $product['sub_category'], $product['price'],
            $product['old_price'] === '' ? null : $product['old_price'], $product['image'], $product['description'],
            $product['rating'], $product['tag'], (int) $product['stock'], $product['is_active'],
        ];
        if ($id) {
            $values[] = $id;
            $db->prepare('update products set name=?, category=?, sub_category=?, price=?, old_price=?, image=?, description=?, rating=?, tag=?, stock=?, is_active=? where id=?')->execute($values);
            flash('product updated');
        } else {
            $db->prepare('insert into products (name, category, sub_category, price, old_price, image, description, rating, tag, stock, is_active) values (?,?,?,?,?,?,?,?,?,?,?)')->execute($values);
            flash('product added');
        }
        redirect('products.php');
    }
}

admin_head($id ? 'edit product' : 'add product', 'products');
?>
<h1><?= $id ? 'edit product' : 'add product' ?></h1>
<?php foreach ($errors as $err): ?><div class="flash bad"><?= e($err) ?></div><?php endforeach; ?>
<form class="card form" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label>name<input name="name" value="<?= e($product['name']) ?>" required></label>
    <div class="two">
        <label>category
            <select name="category" id="category">
                <?php foreach (array_keys(PRODUCT_SUBS) as $cat): ?><option <?= $product['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>sub-category<select name="sub_category" id="sub"></select></label>
    </div>
    <div class="two">
        <label>price (₱)<input name="price" type="number" step="0.01" min="0" value="<?= e($product['price']) ?>" required></label>
        <label>old price (₱, only if on sale)<input name="old_price" type="number" step="0.01" min="0" value="<?= e($product['old_price']) ?>"></label>
    </div>
    <div class="two">
        <label>stock<input name="stock" type="number" min="0" value="<?= e($product['stock']) ?>" required></label>
        <label>rating (0 to 5)<input name="rating" type="number" step="0.1" min="0" max="5" value="<?= e($product['rating']) ?>"></label>
    </div>
    <label>tag
        <select name="tag">
            <?php foreach (['' => 'none', 'New' => 'New', 'Bestseller' => 'Bestseller'] as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= $product['tag'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>description<textarea name="description" rows="4" required><?= e($product['description']) ?></textarea></label>
    <label>image link<input name="image" value="<?= e($product['image']) ?>" placeholder="https://... or uploads/products/photo.jpg"></label>
    <label>or upload a photo (jpg, png, webp, up to 3 mb)<input type="file" name="photo" accept="image/*"></label>
    <?php if ($product['image']): ?><img class="preview" src="<?= e(asset_url($product['image'])) ?>" alt=""><?php endif; ?>
    <label class="check"><input type="checkbox" name="is_active" <?= $product['is_active'] ? 'checked' : '' ?>> show this product in the store</label>
    <div class="row-end">
        <a class="btn light" href="products.php">cancel</a>
        <button class="btn" type="submit">save product</button>
    </div>
</form>
<script>
const subs = <?= json_encode(PRODUCT_SUBS) ?>;
const current = <?= json_encode($product['sub_category']) ?>;
const category = document.getElementById('category');
const sub = document.getElementById('sub');
function fillSubs() {
    sub.innerHTML = subs[category.value].map(s => `<option ${s === current ? 'selected' : ''}>${s}</option>`).join('');
}
category.addEventListener('change', fillSubs);
fillSubs();
</script>
<?php admin_foot(); ?>

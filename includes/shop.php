<?php
// pricing and coupon rules, used by checkout and the account page

function lock_sql() {
    return db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' for update' : '';
}

function discount_text($coupon) {
    if ($coupon['discount_type'] === 'percent') {
        return rtrim(rtrim(number_format((float) $coupon['discount_value'], 2), '0'), '.') . '% off';
    }
    return peso($coupon['discount_value']) . ' off';
}

function random_code($prefix, $length = 6) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = $prefix;
    for ($i = 0; $i < $length; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $code;
}

function coupon_exists($code) {
    $st = db()->prepare('select 1 from coupons where code = ?');
    $st->execute([$code]);
    return (bool) $st->fetchColumn();
}

// every new account gets its own one-time coupon
function make_welcome_coupon($userId) {
    $percent = (float) setting('welcome_percent', 15);
    $days = (int) setting('welcome_days', 30);
    do {
        $code = random_code('GLOW-');
    } while (coupon_exists($code));

    $expires = date('Y-m-d H:i:s', strtotime("+$days days"));
    $st = db()->prepare(
        'insert into coupons (code, description, discount_type, discount_value, min_spend, max_uses, per_user_limit, assigned_user_id, expires_at)
         values (?, ?, ?, ?, 0, 1, 1, ?, ?)'
    );
    $st->execute([$code, 'welcome gift for new members', 'percent', $percent, $userId, $expires]);

    return ['code' => $code, 'text' => rtrim(rtrim(number_format($percent, 2), '0'), '.') . '% off', 'expires_at' => $expires];
}

function usable_coupons($userId) {
    $st = db()->prepare(
        'select * from coupons
         where assigned_user_id = ? and is_active = 1
           and used_count < coalesce(max_uses, 999999)
           and (expires_at is null or expires_at >= ?)
           and (select count(*) from coupon_redemptions r where r.coupon_id = coupons.id and r.user_id = ?) < per_user_limit
         order by id desc'
    );
    $st->execute([$userId, date('Y-m-d H:i:s'), $userId]);
    $list = [];
    foreach ($st->fetchAll() as $c) {
        $list[] = [
            'code' => $c['code'],
            'text' => discount_text($c),
            'description' => $c['description'],
            'min_spend' => (float) $c['min_spend'],
            'expires_at' => $c['expires_at'],
        ];
    }
    return $list;
}

function check_coupon($code, $subtotal, $user, $lock = false) {
    $code = strtoupper(trim($code));
    $st = db()->prepare('select * from coupons where code = ?' . ($lock ? lock_sql() : ''));
    $st->execute([$code]);
    $c = $st->fetch();

    if (!$c || !$c['is_active']) {
        return ['error' => 'that coupon code is not valid'];
    }
    $now = date('Y-m-d H:i:s');
    if ($c['starts_at'] && $c['starts_at'] > $now) {
        return ['error' => 'this coupon is not active yet'];
    }
    if ($c['expires_at'] && $c['expires_at'] < $now) {
        return ['error' => 'this coupon has expired'];
    }
    if (!$user) {
        return ['error' => 'sign in to use a coupon'];
    }
    if ($c['assigned_user_id'] && (int) $c['assigned_user_id'] !== (int) $user['id']) {
        return ['error' => 'that coupon code is not valid'];
    }
    if ($c['max_uses'] !== null && $c['used_count'] >= $c['max_uses']) {
        return ['error' => 'this coupon has been fully redeemed'];
    }

    $st = db()->prepare('select count(*) from coupon_redemptions where coupon_id = ? and user_id = ?');
    $st->execute([$c['id'], $user['id']]);
    if ($st->fetchColumn() >= $c['per_user_limit']) {
        return ['error' => 'you have already used this coupon'];
    }

    if ($c['new_user_only']) {
        $st = db()->prepare("select count(*) from orders where user_id = ? and status <> 'cancelled'");
        $st->execute([$user['id']]);
        if ($st->fetchColumn() > 0) {
            return ['error' => 'this coupon is only for first orders'];
        }
    }
    if ($subtotal < (float) $c['min_spend']) {
        return ['error' => 'spend at least ' . peso($c['min_spend']) . ' to use this coupon'];
    }

    $value = (float) $c['discount_value'];
    $discount = $c['discount_type'] === 'percent' ? round($subtotal * $value / 100, 2) : min($value, $subtotal);
    return ['coupon' => $c, 'discount' => $discount];
}

// prices the bag from the database so the browser can never set its own prices
function price_cart($items, $code, $user, $lock = false) {
    $result = [
        'lines' => [], 'subtotal' => 0, 'discount' => 0, 'shipping' => 0, 'total' => 0,
        'coupon' => null, 'coupon_id' => null, 'coupon_error' => null, 'error' => null,
    ];

    $qtys = [];
    foreach ($items as $item) {
        $id = (int) ($item['id'] ?? 0);
        $qty = (int) ($item['qty'] ?? 0);
        if ($id > 0 && $qty > 0) {
            $qtys[$id] = min(99, ($qtys[$id] ?? 0) + $qty);
        }
    }
    if (!$qtys) {
        $result['error'] = 'your bag is empty';
        return $result;
    }

    $marks = implode(',', array_fill(0, count($qtys), '?'));
    $st = db()->prepare("select * from products where is_active = 1 and id in ($marks)" . ($lock ? lock_sql() : ''));
    $st->execute(array_keys($qtys));
    $found = [];
    foreach ($st->fetchAll() as $p) {
        $found[(int) $p['id']] = $p;
    }

    foreach ($qtys as $id => $qty) {
        if (!isset($found[$id])) {
            $result['error'] = 'an item in your bag is no longer available';
            return $result;
        }
        $p = $found[$id];
        if ((int) $p['stock'] < $qty) {
            $result['error'] = $p['stock'] > 0 ? "only {$p['stock']} left of {$p['name']}" : "{$p['name']} is out of stock";
            return $result;
        }
        $lineTotal = (float) $p['price'] * $qty;
        $result['lines'][] = ['id' => $id, 'name' => $p['name'], 'price' => (float) $p['price'], 'qty' => $qty, 'total' => $lineTotal];
        $result['subtotal'] += $lineTotal;
    }

    $code = strtoupper(trim($code));
    if ($code !== '') {
        $check = check_coupon($code, $result['subtotal'], $user, $lock);
        if (isset($check['error'])) {
            $result['coupon_error'] = $check['error'];
        } else {
            $result['coupon'] = $check['coupon']['code'];
            $result['coupon_id'] = (int) $check['coupon']['id'];
            $result['discount'] = $check['discount'];
        }
    }

    $after = $result['subtotal'] - $result['discount'];
    $result['shipping'] = $after >= (float) setting('free_shipping_min', 1500) ? 0 : (float) setting('shipping_fee', 99);
    $result['total'] = $after + $result['shipping'];
    return $result;
}

<?php
require_once __DIR__ . '/_layout.php';
require_admin();

$st = db()->prepare('select c.*, u.name as owner_name from coupons c left join users u on u.id = c.assigned_user_id where c.id = ?');
$st->execute([(int) ($_GET['id'] ?? 0)]);
$c = $st->fetch();
if (!$c) {
    flash('that coupon does not exist');
    redirect('coupons.php');
}

$big = $c['discount_type'] === 'percent'
    ? rtrim(rtrim(number_format((float) $c['discount_value'], 2), '0'), '.') . '%'
    : '₱' . rtrim(rtrim(number_format((float) $c['discount_value'], 2), '0'), '.');
$lines = [];
if ($c['min_spend'] > 0) $lines[] = 'min. spend ' . peso($c['min_spend']);
if ($c['new_user_only']) $lines[] = 'new customers only';
if ($c['owner_name']) $lines[] = 'for ' . $c['owner_name'];
$lines[] = $c['expires_at'] ? 'valid until ' . date('M j, Y', strtotime($c['expires_at'])) : 'no expiry date';

$ticket = [
    'code' => $c['code'], 'big' => $big, 'title' => $c['description'] ?: 'gift voucher',
    'lines' => $lines, 'uses' => $c['max_uses'] === null ? 'unlimited uses' : $c['max_uses'] . ' use' . ($c['max_uses'] > 1 ? 's' : '') . ' total',
];

admin_head('coupon ticket', 'coupons');
?>
<div class="head-row">
    <h1>ticket for <span class="mono"><?= e($c['code']) ?></span></h1>
    <div class="actions">
        <a class="btn light" href="coupons.php">back</a>
        <button class="btn light" id="save" type="button">download png</button>
        <button class="btn" id="print" type="button">print ticket</button>
    </div>
</div>
<section class="card ticket-wrap">
    <canvas id="ticket" width="1200" height="480"></canvas>
    <p class="muted small">customers type the code at checkout to use it. the barcode is only part of the design.</p>
</section>
<script>
const t = <?= json_encode($ticket, JSON_UNESCAPED_UNICODE) ?>;
const canvas = document.getElementById('ticket');
const ctx = canvas.getContext('2d');
const W = 1200, H = 480, SPLIT = 880, R = 28;

function draw() {
    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, W, H);

    const grad = ctx.createLinearGradient(0, 0, SPLIT, H);
    grad.addColorStop(0, '#fde6ee');
    grad.addColorStop(1, '#f7b9cd');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, SPLIT, H);
    ctx.fillStyle = '#111';
    ctx.fillRect(SPLIT, 0, W - SPLIT, H);

    ctx.fillStyle = '#111';
    ctx.font = '900 44px "Playfair Display", serif';
    ctx.textBaseline = 'alphabetic';
    ctx.fillText('GLOWTH', 60, 90);
    ctx.font = '500 16px Poppins, sans-serif';
    ctx.fillText('MAKEUP & SKINCARE', 62, 118);

    ctx.font = '900 150px "Playfair Display", serif';
    ctx.fillText(t.big, 56, 270);
    const bigWidth = ctx.measureText(t.big).width;
    ctx.font = '700 34px Poppins, sans-serif';
    ctx.fillText('OFF', 70 + bigWidth, 270);

    ctx.font = '500 24px Poppins, sans-serif';
    ctx.fillText(t.title.length > 46 ? t.title.slice(0, 44) + '...' : t.title, 60, 322);
    ctx.font = '400 18px Poppins, sans-serif';
    t.lines.forEach((line, i) => ctx.fillText(line, 60, 362 + i * 28));

    // stub
    ctx.fillStyle = '#f7b9cd';
    ctx.font = '500 15px Poppins, sans-serif';
    ctx.textAlign = 'center';
    const cx = SPLIT + (W - SPLIT) / 2;
    ctx.fillText('YOUR CODE', cx, 90);
    ctx.fillStyle = '#fff';
    ctx.font = '700 ' + (t.code.length > 12 ? 26 : 32) + 'px "Courier New", monospace';
    ctx.fillText(t.code, cx, 140);

    let seed = 0;
    for (const ch of t.code) seed = (seed * 31 + ch.charCodeAt(0)) % 2147483647;
    const rand = () => (seed = (seed * 16807) % 2147483647) / 2147483647;
    ctx.fillStyle = '#fff';
    let x = SPLIT + 40;
    while (x < W - 40) {
        const w = 2 + Math.floor(rand() * 5);
        ctx.fillRect(x, 175, w, 150);
        x += w + 2 + Math.floor(rand() * 4);
    }
    ctx.fillStyle = '#f7b9cd';
    ctx.font = '400 15px Poppins, sans-serif';
    ctx.fillText(t.uses, cx, 370);
    ctx.fillText('enter at checkout', cx, 396);
    ctx.textAlign = 'left';

    // dashed perforation
    ctx.strokeStyle = '#111';
    ctx.lineWidth = 3;
    ctx.setLineDash([10, 10]);
    ctx.beginPath();
    ctx.moveTo(SPLIT, R + 6);
    ctx.lineTo(SPLIT, H - R - 6);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.strokeStyle = '#fff';
    ctx.setLineDash([10, 10]);
    ctx.beginPath();
    ctx.moveTo(SPLIT, R + 6);
    ctx.lineTo(SPLIT, H - R - 6);
    ctx.stroke();
    ctx.setLineDash([]);

    // notches and rounded corners cut out of the ticket
    ctx.globalCompositeOperation = 'destination-out';
    [[SPLIT, 0], [SPLIT, H]].forEach(([nx, ny]) => {
        ctx.beginPath();
        ctx.arc(nx, ny, R, 0, Math.PI * 2);
        ctx.fill();
    });
    [[0, 0], [W, 0], [0, H], [W, H]].forEach(([nx, ny]) => {
        ctx.beginPath();
        ctx.arc(nx, ny, 16, 0, Math.PI * 2);
        ctx.fill();
    });
    ctx.globalCompositeOperation = 'source-over';
}

const ready = document.fonts ? Promise.all([
    document.fonts.load('900 40px "Playfair Display"'), document.fonts.load('500 20px Poppins'), document.fonts.load('700 20px Poppins'),
]).catch(() => {}) : Promise.resolve();
ready.then(draw);
draw();

document.getElementById('print').addEventListener('click', () => window.print());
document.getElementById('save').addEventListener('click', () => {
    const link = document.createElement('a');
    link.download = 'glowth-coupon-' + t.code + '.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
});
</script>
<?php admin_foot(); ?>

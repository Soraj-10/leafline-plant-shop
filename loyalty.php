<?php
// loyalty.php — Customer Loyalty & Rewards Dashboard (NEW FILE, read-only)
session_start();
require_once 'db.php';
require_once 'auth_helpers.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}
$customerId = (int)$_SESSION['customer_id'];

$stmt = $conn->prepare("SELECT COUNT(*) AS order_count, COALESCE(SUM(total_price),0) AS total_spent
                         FROM orders WHERE customer_id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$points = (int)floor($stats['total_spent']); // 1 point per $1 spent

if ($points >= 500)      { $tier = 'Gold';   $tierColor = '#d4af37'; $next = null; }
elseif ($points >= 200)  { $tier = 'Silver'; $tierColor = '#a8a8a8'; $next = 500; }
elseif ($points >= 50)   { $tier = 'Bronze'; $tierColor = '#cd7f32'; $next = 200; }
else                      { $tier = 'Sprout'; $tierColor = 'var(--green-bright)'; $next = 50; }

$progress = $next ? min(100, round(($points / $next) * 100)) : 100;

$stmt = $conn->prepare("SELECT id, total_price, order_date, is_gift FROM orders WHERE customer_id = ? ORDER BY order_date DESC LIMIT 10");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$orders = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rewards — LeafLine</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php renderSiteNav(); ?>

<section style="max-width:760px; margin:40px auto; padding:0 20px;">
    <h1 style="color:var(--green-deep);">🏆 Your Rewards</h1>
    <p style="color:var(--text-mid);">Welcome back, <?= e($_SESSION['customer_name']) ?>!</p>

    <div class="card" style="padding:28px; margin:24px 0; border-top:6px solid <?= $tierColor ?>;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <span style="font-size:13px; color:var(--text-mid);">CURRENT TIER</span>
                <h2 style="margin:2px 0; color:<?= $tierColor ?>;"><?= $tier ?></h2>
            </div>
            <div style="text-align:right;">
                <span style="font-size:13px; color:var(--text-mid);">POINTS</span>
                <h2 style="margin:2px 0; color:var(--green-deep);"><?= $points ?> pts</h2>
            </div>
        </div>

        <?php if ($next): ?>
        <div style="margin-top:16px;">
            <div style="background:var(--green-wash); border-radius:10px; height:14px; overflow:hidden;">
                <div style="width:<?= $progress ?>%; background:<?= $tierColor ?>; height:100%;"></div>
            </div>
            <p style="font-size:13px; color:var(--text-mid); margin-top:6px;">
                <?= $next - $points ?> more points to reach the next tier
            </p>
        </div>
        <?php else: ?>
            <p style="margin-top:16px; color:var(--green-deep);">🌟 You've reached the top tier — thank you for being a loyal customer!</p>
        <?php endif; ?>
    </div>

    <div class="card" style="padding:24px;">
        <h3 style="color:var(--green-deep);">Order History</h3>
        <?php if ($orders->num_rows === 0): ?>
            <p style="color:var(--text-mid);">No orders yet — <a href="shop">start shopping</a> to earn points!</p>
        <?php else: ?>
        <table style="width:100%; border-collapse:collapse; margin-top:12px;">
            <thead><tr style="text-align:left; color:var(--green-deep); border-bottom:2px solid var(--green-wash);">
                <th style="padding:8px;">Order</th><th>Date</th><th>Total</th><th></th>
            </tr></thead>
            <tbody>
            <?php while ($o = $orders->fetch_assoc()): ?>
                <tr style="border-bottom:1px solid var(--green-wash);">
                    <td style="padding:8px;">#<?= e($o['id']) ?> <?= $o['is_gift'] ? '🎁' : '' ?></td>
                    <td><?= e(date('M j, Y', strtotime($o['order_date']))) ?></td>
                    <td><?= formatPrice($o['total_price']) ?></td>
                    <td><a href="invoice.php?order_id=<?= e($o['id']) ?>" class="btn btn-secondary" style="padding:6px 12px; font-size:13px;">Invoice</a></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</section>
</body>
</html>

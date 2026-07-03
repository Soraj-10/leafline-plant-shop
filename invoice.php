<?php
// invoice.php — Printable Order Invoice (NEW FILE, read-only, no existing files edited)
session_start();
require_once 'db.php';

$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) { die("Missing order_id. Use invoice.php?order_id=123"); }

$stmt = $conn->prepare("SELECT o.*, c.name AS customer_name, c.email AS customer_email
                         FROM orders o JOIN customers c ON c.id = o.customer_id
                         WHERE o.id = ?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) { die("Order not found."); }

// Access control: only the owning customer (once logged in) or an admin can view it.
$isOwner = isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] === (int)$order['customer_id'];
if (!$isOwner && !(function_exists('isAdmin') && isAdmin())) {
    die("You don't have permission to view this invoice. <a href='login.php'>Log in</a>.");
}

$stmt = $conn->prepare("SELECT oi.quantity, oi.price_at_purchase, p.name AS plant_name
                         FROM order_items oi JOIN plants p ON p.id = oi.plant_id
                         WHERE oi.order_id = ?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$items = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice #<?= e($orderId) ?> — LeafLine</title>
<link rel="stylesheet" href="style.css">
<style>
  @media print { .no-print { display:none; } }
  .inv-table { width:100%; border-collapse:collapse; margin-top:20px; }
  .inv-table th, .inv-table td { padding:10px; border-bottom:1px solid var(--green-wash); text-align:left; }
  .inv-table th { background:var(--green-wash); color:var(--green-deep); }
</style>
</head>
<body>
<div class="no-print" style="max-width:700px; margin:20px auto 0; text-align:right; padding:0 20px;">
    <button onclick="window.print()" class="btn btn-gold">🖨️ Print / Save as PDF</button>
</div>

<section style="max-width:700px; margin:20px auto 60px; padding:32px; background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,0.08);">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:3px solid var(--green-bright); padding-bottom:16px;">
        <h1 style="color:var(--green-deep); margin:0;">🌿 LeafLine</h1>
        <div style="text-align:right;">
            <h2 style="margin:0; color:var(--green-deep);">INVOICE</h2>
            <p style="margin:0; color:var(--text-mid);">#<?= e($orderId) ?></p>
        </div>
    </div>

    <div style="display:flex; justify-content:space-between; margin-top:24px;">
        <div>
            <strong>Billed To</strong><br>
            <?= e($order['customer_name']) ?><br>
            <?= e($order['customer_email']) ?>
        </div>
        <div style="text-align:right;">
            <strong>Order Date</strong><br>
            <?= e(date('F j, Y', strtotime($order['order_date']))) ?>
        </div>
    </div>

    <?php if ((int)$order['is_gift'] === 1): ?>
    <div style="margin-top:16px; padding:12px; background:var(--green-wash); border-radius:8px;">
        🎁 Gift order for <?= e($order['gift_recipient_name']) ?> (<?= e($order['gift_recipient_email']) ?>)
    </div>
    <?php endif; ?>

    <table class="inv-table">
        <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
        <tbody>
        <?php while ($item = $items->fetch_assoc()): ?>
            <tr>
                <td><?= e($item['plant_name']) ?></td>
                <td><?= e($item['quantity']) ?></td>
                <td><?= formatPrice($item['price_at_purchase']) ?></td>
                <td><?= formatPrice($item['price_at_purchase'] * $item['quantity']) ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <div style="text-align:right; margin-top:20px; font-size:1.3rem; color:var(--green-deep);">
        <strong>Total: <?= formatPrice($order['total_price']) ?></strong>
    </div>

    <p style="margin-top:40px; color:var(--text-mid); font-size:13px; text-align:center;">
        Thank you for shopping with LeafLine 🌿
    </p>
</section>
</body>
</html>

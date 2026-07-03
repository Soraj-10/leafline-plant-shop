<?php
// orders.php — Full Shop Order History
session_start();
require_once 'db.php';

// Get all orders with customer info, sorted latest first
if (isAdmin()) {
    $orders = $conn->query("
        SELECT o.id, o.total_price, o.order_date, o.is_gift, o.gift_recipient_name, o.gift_recipient_email, o.gift_recipient_address, o.gift_note,
               c.name AS customer_name, c.email AS customer_email
        FROM orders o
        JOIN customers c ON c.id = o.customer_id
        ORDER BY o.order_date DESC
    ");
} else {
    $orders = null;
    $viewerMessage = 'Only shop managers can view all order history. Customers can review their purchases on the My History page.';
}

// For each order, we'll fetch items via a separate query in the loop
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders — LeafLine</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
    <ul class="navbar-nav">
        <li><a href="/plantshop/">Home</a></li>
        <li><a href="shop">Shop</a></li>
        <li><a href="orders" class="active">Orders</a></li>
        <li><a href="customer_history">My History</a></li>
        <li><a href="search.php">🔍 Search</a></li>
        <?php if (isset($_SESSION['customer_id'])): ?>
        <li><a href="account.php">🙍 My Account</a></li>
        <li><a href="loyalty.php">🏆 Rewards</a></li>
        <li><a href="logout.php">🚪 Logout</a></li>
        <?php else: ?>
        <li><a href="login.php">👤 Login</a></li>
        <?php endif; ?>
        <?php if (isAdmin()): ?>
        <li><a href="dashboard">📊 Dashboard</a></li>
        <li><a href="admin_reviews">📝 Reviews</a></li>
        <li><a href="admin_wishlist">💚 Wishlists</a></li>
        <li><a href="discount_codes">🎫 Discounts</a></li>
        <li><a href="add_plant">🌱 Manage Plants</a></li>
        <li><a href="admin_change_password.php">🔐 Change Password</a></li>
        <li><a href="add_plant?logout=1" style="color:#e07a5f;">🚪 Admin Logout</a></li>
        <?php else: ?>
        <li><a href="admin_login.php">⚙ Admin</a></li>
        <?php endif; ?>
    </ul>
</nav>


<div class="page-header">
    <div class="container">
        <h1>📦 Order History</h1>
        <p>All shop orders, sorted by most recent first.</p>
    </div>
</div>

<div class="page-wrapper">
    <?php if (!isAdmin()): ?>
    <div class="empty-state">
        <div class="empty-icon">🔒</div>
        <p><?= e($viewerMessage) ?></p>
        <p><a href="customer_history">View your order history</a></p>
    </div>
    <?php elseif ($orders->num_rows === 0): ?>
    <div class="empty-state">
        <div class="empty-icon">📭</div>
        <p>No orders yet. <a href="shop">Start shopping!</a></p>
    </div>
    <?php else: ?>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Items Purchased</th>
                    <th>Gift?</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($order = $orders->fetch_assoc()):
                // Fetch items for this order
                $items = $conn->query("
                    SELECT oi.quantity, oi.price_at_purchase, p.name
                    FROM order_items oi
                    JOIN plants p ON p.id = oi.plant_id
                    WHERE oi.order_id = {$order['id']}
                ");
                $itemList = [];
                while ($item = $items->fetch_assoc()) {
                    $itemList[] = "{$item['name']} x{$item['quantity']}";
                }
            ?>
            <tr>
                <td><strong>#<?= $order['id'] ?></strong></td>
                <td>
                    <div style="font-weight:600;"><?= e($order['customer_name']) ?></div>
                    <div style="font-size:12px; color:var(--text-light);"><?= e($order['customer_email']) ?></div>
                </td>
                <td>
                    <?= date('M j, Y', strtotime($order['order_date'])) ?>
                    <div style="font-size:12px; color:var(--text-light);"><?= date('g:i A', strtotime($order['order_date'])) ?></div>
                </td>
                <td>
                    <?php foreach ($itemList as $item): ?>
                    <span class="badge badge-green" style="margin:2px 2px 2px 0;"><?= e($item) ?></span>
                    <?php endforeach; ?>
                </td>
                <td>
                    <?php if ($order['is_gift']): ?>
                        <div style="font-weight:600; color:#e07a5f;">🎁 Gift</div>
                        <div style="font-size:12px; color:var(--text-mid);">
                            To: <?= e($order['gift_recipient_name']) ?><br>
                            Email: <?= e($order['gift_recipient_email']) ?><br>
                            Address: <?= e($order['gift_recipient_address']) ?><br>
                            <?php if (!empty($order['gift_note'])): ?>
                            Note: <?= e($order['gift_note']) ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <span style="color:var(--text-mid);">No</span>
                    <?php endif; ?>
                </td>
                <td><strong style="color:var(--green-bright);"><?= formatPrice($order['total_price']) ?></strong></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>
</div>

<footer><p>🌿 <strong>LeafLine Plant Shop</strong></p></footer>
</body>
</html>

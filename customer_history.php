<?php
// customer_history.php — Purchase History per Customer
session_start();
require_once 'db.php';

$orders = null;
$customer = null;
$searchEmail = trim($_GET['email'] ?? $_SESSION['customer_email'] ?? '');

if ($searchEmail) {
    // Find customer
    $stmt = $conn->prepare("SELECT * FROM customers WHERE email = ?");
    $stmt->bind_param("s", $searchEmail);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();

    if ($customer) {
        // Get their orders
        $oStmt = $conn->prepare("
            SELECT o.id, o.total_price, o.order_date, o.is_gift, o.gift_recipient_name, o.gift_recipient_email, o.gift_recipient_address, o.gift_note
            FROM orders o
            WHERE o.customer_id = ?
            ORDER BY o.order_date DESC
        ");
        $oStmt->bind_param("i", $customer['id']);
        $oStmt->execute();
        $orders = $oStmt->get_result();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Purchase History — LeafLine</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
    <ul class="navbar-nav">
        <li><a href="/plantshop/">Home</a></li>
        <li><a href="shop">Shop</a></li>
        <li><a href="orders">Orders</a></li>
        <li><a href="customer_history" class="active">My History</a></li>
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
        <h1>🧾 My Purchase History</h1>
        <p>Enter your email to view your past orders.</p>
    </div>
</div>

<div class="page-wrapper">

    <!-- Email Search Form -->
    <div class="form-card" style="margin-bottom:32px;">
        <form method="GET" style="display:flex; gap:12px; align-items:flex-end;">
            <div class="form-group" style="flex:1; margin-bottom:0;">
                <label class="form-label">Your Email Address</label>
                <input type="email" name="email" class="form-control"
                       placeholder="you@email.com"
                       value="<?= e($searchEmail) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">🔍 Find Orders</button>
        </form>
    </div>

    <?php if ($searchEmail && !$customer): ?>
    <div class="alert alert-warning">
        No account found with email <strong><?= e($searchEmail) ?></strong>.
        Have you placed an order yet? <a href="shop">Shop here →</a>
    </div>

    <?php elseif ($customer): ?>

    <!-- Customer Info Card -->
    <div style="background:var(--white); border-radius:var(--radius-lg); padding:20px 24px; box-shadow:var(--shadow-sm); margin-bottom:28px; display:flex; gap:20px; align-items:center;">
        <div style="width:52px; height:52px; border-radius:50%; background:var(--green-bright); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.4rem; font-family:'Playfair Display',serif; font-weight:700;">
            <?= strtoupper(substr($customer['name'], 0, 1)) ?>
        </div>
        <div>
            <div style="font-family:'Playfair Display',serif; font-size:1.2rem; color:var(--green-deep);"><?= e($customer['name']) ?></div>
            <div style="font-size:13px; color:var(--text-mid);"><?= e($customer['email']) ?> <?= $customer['phone'] ? '· ' . e($customer['phone']) : '' ?></div>
            <div style="font-size:12px; color:var(--text-light); margin-top:2px;">Customer since <?= date('M Y', strtotime($customer['created_at'])) ?></div>
        </div>
    </div>

    <?php if ($orders->num_rows === 0): ?>
    <div class="empty-state">
        <div class="empty-icon">📭</div>
        <p>No orders found for this account.</p>
    </div>

    <?php else:
        $totalSpent = 0;
    ?>
    <div style="display:flex; flex-direction:column; gap:16px;">
    <?php while ($order = $orders->fetch_assoc()):
        $totalSpent += $order['total_price'];

        // Fetch items for this order
        $items = $conn->query("
            SELECT oi.quantity, oi.price_at_purchase, p.name, p.image_url
            FROM order_items oi
            JOIN plants p ON p.id = oi.plant_id
            WHERE oi.order_id = {$order['id']}
        ");
    ?>
    <div style="background:var(--white); border-radius:var(--radius-lg); box-shadow:var(--shadow-sm); overflow:hidden;">
        <!-- Order Header -->
        <div style="background:var(--green-wash); padding:14px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <div>
                <strong style="color:var(--green-deep);">Order #<?= $order['id'] ?></strong>
                <span style="font-size:13px; color:var(--text-mid); margin-left:12px;">
                    📅 <?= date('F j, Y', strtotime($order['order_date'])) ?>
                </span>
                <?php if ($order['is_gift']): ?>
                <span style="font-size:13px; color:#e07a5f; margin-left:12px;">
                    🎁 Gift for <?= e($order['gift_recipient_name']) ?>
                </span>
                <?php endif; ?>
            </div>
            <div style="font-family:'Playfair Display',serif; font-size:1.2rem; color:var(--green-bright); font-weight:700;">
                <?= formatPrice($order['total_price']) ?>
            </div>
        </div>

        <!-- Order Items -->
        <div style="padding:16px 20px; display:flex; flex-wrap:wrap; gap:12px;">
        <?php while ($item = $items->fetch_assoc()): ?>
        <div style="display:flex; align-items:center; gap:10px; background:var(--cream); border-radius:var(--radius); padding:10px 14px; min-width:160px;">
            <img src="<?= e($item['image_url']) ?>" alt=""
                 style="width:36px; height:36px; object-fit:cover; border-radius:8px;"
                 onerror="this.style.display='none'">
            <div>
                <div style="font-weight:600; font-size:14px; color:var(--green-deep);"><?= e($item['name']) ?></div>
                <div style="font-size:12px; color:var(--text-mid);">x<?= $item['quantity'] ?> · <?= formatPrice($item['price_at_purchase']) ?> each</div>
            </div>
        </div>
        <?php endwhile; ?>
        </div>
    </div>
    <?php endwhile; ?>
    </div>

    <!-- Summary -->
    <div style="margin-top:24px; text-align:right;">
        <div style="font-size:14px; color:var(--text-mid);">Total spent with LeafLine</div>
        <div style="font-family:'Playfair Display',serif; font-size:2rem; color:var(--green-deep); font-weight:700;"><?= formatPrice($totalSpent) ?></div>
    </div>

    <?php endif; ?>
    <?php endif; ?>

</div>

<footer><p>🌿 <strong>LeafLine Plant Shop</strong></p></footer>
</body>
</html>

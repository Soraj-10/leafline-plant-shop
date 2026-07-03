<?php
session_start();
require_once 'db.php';

$msg = '';
$msgType = '';

// Handle gift order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender   = trim($_POST['sender'] ?? '');
    $receiver = trim($_POST['receiver'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $message  = trim($_POST['message'] ?? '');
    $item     = trim($_POST['item'] ?? '');
    $price    = (float)($_POST['price'] ?? 0);

    if (empty($sender) || empty($receiver) || empty($address) || empty($item)) {
        $msg = '❌ Please fill in all required fields.';
        $msgType = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO gift_orders (sender_name, receiver_name, receiver_address, message, item_name, price) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssd", $sender, $receiver, $address, $message, $item, $price);
        if ($stmt->execute()) {
            $msg = '🎁 Gift sent successfully!';
            $msgType = 'success';
        } else {
            $msg = '❌ Error sending gift. Please try again.';
            $msgType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎁 Gift Shop - LeafLine</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- NAVIGATION -->
<nav class="navbar">
    <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
    <ul class="navbar-nav">
        <li><a href="/plantshop/">Home</a></li>
        <li><a href="shop">Shop</a></li>
        <li><a href="giftshop" class="active">🎁 Gifts</a></li>
        <li><a href="care">Care</a></li>
        <li><a href="orders">Orders</a></li>
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
        <h1>🎁 Send a Gift</h1>
        <p>Send beautiful plants as gifts to your loved ones.</p>
    </div>
</div>

<div class="page-wrapper">
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="plant-grid">

        <!-- Gift Item -->
        <div class="plant-card">
            <img src="https://images.unsplash.com/photo-1524594154908-eddcfb1d8a16" alt="Mini Flower Plant">
            <h3>Mini Flower Plant</h3>
            <p><?= formatPrice(10) ?></p>

            <form method="POST" action="giftshop" class="form-card">
                <div class="form-group">
                    <label class="form-label">Your Name</label>
                    <input type="text" name="sender" class="form-control" placeholder="Your Name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Receiver Name</label>
                    <input type="text" name="receiver" class="form-control" placeholder="Receiver Name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Delivery Address</label>
                    <textarea name="address" class="form-control" placeholder="Delivery Address" required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Gift Message (Optional)</label>
                    <textarea name="message" class="form-control" placeholder="Gift Message"></textarea>
                </div>
                
                <input type="hidden" name="item" value="Mini Flower Plant">
                <input type="hidden" name="price" value="10">

                <button type="submit" class="btn btn-primary" style="width:100%;">🎁 Send Gift</button>
            </form>
        </div>

    </div>
</div>

<style>
.plant-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 24px;
    margin: 24px 0;
}
.plant-card {
    background: var(--white);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.plant-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.plant-card img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}
.plant-card h3 {
    margin: 16px 16px 8px;
    color: var(--green-deep);
}
.plant-card p {
    margin: 0 16px 16px;
    color: var(--green-mid);
    font-size: 18px;
    font-weight: bold;
}
.form-card {
    padding: 20px;
}
</style>

</body>
</html>


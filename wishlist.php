<?php
session_start();
require_once 'db.php';

$msg = '';
$msgType = '';
$sessionKey = session_id();
$customerEmail = trim($_POST['customer_email'] ?? $_GET['email'] ?? $_SESSION['customer_email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove') {
    $wishlistId = (int)($_POST['wishlist_id'] ?? 0);
    $del = $conn->prepare("DELETE FROM wishlists WHERE id = ? AND (session_key = ? OR customer_email = ?)");
    $del->bind_param('iss', $wishlistId, $sessionKey, $customerEmail);
    $del->execute();
    $msg = 'Removed from wishlist.';
    $msgType = 'success';
}

$wishlistItems = [];
if ($customerEmail !== '') {
    $stmt = $conn->prepare("SELECT w.id AS wishlist_id, p.* FROM wishlists w JOIN plants p ON p.id = w.plant_id WHERE w.customer_email = ? ORDER BY w.added_at DESC");
    $stmt->bind_param('s', $customerEmail);
} else {
    $stmt = $conn->prepare("SELECT w.id AS wishlist_id, p.* FROM wishlists w JOIN plants p ON p.id = w.plant_id WHERE w.session_key = ? ORDER BY w.added_at DESC");
    $stmt->bind_param('s', $sessionKey);
}
$stmt->execute();
$wishlistItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Favorites — LeafLine</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
    <ul class="navbar-nav">
        <li><a href="/plantshop/">Home</a></li>
        <li><a href="shop">Shop</a></li>
        <li><a href="wishlist" class="active">Favorites</a></li>
        <li><a href="exotic">Exotic</a></li>
        <li><a href="care">Care</a></li>
        <li><a href="reviews">Reviews</a></li>
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
        <h1>💚 Favorites</h1>
        <p>Save plants for later and manage your wishlist from one place.</p>
    </div>
</div>

<div class="page-wrapper">
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="form-card" style="margin-bottom:24px;">
        <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            <div class="form-group" style="flex:1; min-width:220px; margin-bottom:0;">
                <label class="form-label">Your Email</label>
                <input type="email" name="email" class="form-control" placeholder="you@email.com" value="<?= e($customerEmail) ?>">
            </div>
            <button type="submit" class="btn btn-primary">Show My Wishlist</button>
        </form>
        <p style="margin:16px 0 0; color:var(--text-mid);">
            <?= $customerEmail ? 'Showing wishlist for email: <strong>' . e($customerEmail) . '</strong>.' : 'Your wishlist is stored in this browser session. Enter your email to view your personal wishlist.' ?>
        </p>
    </div>

    <?php if (empty($wishlistItems)): ?>
        <div class="empty-state">
            <div class="empty-icon">🌿</div>
            <p>Your wishlist is empty. Add plants from the shop.</p>
        </div>
    <?php endif; ?>

    <?php if (!empty($wishlistItems)): ?>
        <div class="plant-grid">
            <?php foreach ($wishlistItems as $plant): ?>
                <div class="plant-card">
                    <div class="plant-card-image-wrap">
                        <img src="<?= e($plant['image_url']) ?>" alt="<?= e($plant['name']) ?>"
                             class="plant-card-image"
                             onerror="this.style.display='none'">
                        <span class="plant-badge <?= e($plant['type']) ?>"><?= ucfirst($plant['type']) ?></span>
                    </div>
                    <div class="plant-card-body">
                        <div class="plant-card-name"><?= e($plant['name']) ?></div>
                        <div class="plant-card-desc"><?= e(substr($plant['description'], 0, 90)) ?>...</div>
                        <div class="plant-care-info">
                            <span class="care-tag">☀️ <?= e($plant['sunlight']) ?></span>
                            <span class="care-tag">💧 <?= e($plant['watering_days']) ?>d</span>
                        </div>
                    </div>
                    <div class="plant-card-footer">
                        <div>
                            <div class="plant-price"><?= formatPrice($plant['price']) ?></div>
                        </div>
                        <form method="POST" style="margin-top:12px;">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="wishlist_id" value="<?= $plant['wishlist_id'] ?>">
                            <input type="hidden" name="customer_email" value="<?= e($customerEmail) ?>">
                            <button type="submit" class="btn btn-secondary" style="background:#e07a5f; color:#fff;">Remove</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
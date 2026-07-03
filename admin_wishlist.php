<?php
session_start();
require_once 'db.php';

if (!isAdmin()) {
    header('Location: add_plant');
    exit;
}

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove') {
    $id = (int)($_POST['wishlist_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM wishlists WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $msg = 'Wishlist item removed.';
    $msgType = 'success';
}

$result = $conn->query("SELECT w.id, w.session_key, w.customer_email, w.added_at AS created_at, p.name, p.price, p.image_url, p.type FROM wishlists w JOIN plants p ON p.id = w.plant_id ORDER BY w.added_at DESC");
$items = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Wishlists — LeafLine</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
    <ul class="navbar-nav">
        <li><a href="/plantshop/">Home</a></li>
        <li><a href="dashboard">Dashboard</a></li>
        <li><a href="admin_reviews">Reviews</a></li>
        <li><a href="admin_wishlist" class="active">Wishlists</a></li>
        <li><a href="discount_codes">Discounts</a></li>
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
        <li><a href="add_plant?logout=1" style="color:#e07a5f;">Logout</a></li>
    </ul>
</nav>


<div class="page-header">
    <div class="container">
        <h1>💚 Customer Wishlists</h1>
        <p>See what customers have saved and remove items if needed.</p>
    </div>
</div>

<div class="page-wrapper">
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <div class="empty-state">
            <div class="empty-icon">💤</div>
            <p>No wishlist items found.</p>
        </div>
    <?php else: ?>
        <div class="wishlist-admin-grid">
            <?php foreach ($items as $item): ?>
                <div class="wishlist-card">
                    <div class="wishlist-card-image">
                        <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>"
                             style="width:80px; height:80px; object-fit:cover; border-radius:12px;"
                             onerror="this.style.display='none'">
                    </div>
                    <div class="wishlist-card-info">
                        <h4><?= e($item['name']) ?></h4>
                        <p><strong>Email:</strong> <?= e($item['customer_email'] ?: 'Guest') ?></p>
                        <p><strong>Session:</strong> <?= e(substr($item['session_key'], 0, 8)) ?>...</p>
                        <p><strong>Added:</strong> <?= date('M j, Y', strtotime($item['created_at'])) ?></p>
                        <p><strong>Price:</strong> <?= formatPrice($item['price']) ?></p>
                    </div>
                    <div class="wishlist-card-actions">
                        <form method="POST">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="wishlist_id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn btn-secondary" style="background:#e07a5f; color:#fff;" onclick="return confirm('Remove this item from wishlist?')">Remove</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.wishlist-admin-grid {
    display:grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap:20px;
}
.wishlist-card {
    display:flex;
    flex-direction:column;
    gap:16px;
    padding:20px;
    background:var(--white);
    border:1px solid #eee;
    border-radius:var(--radius);
}
.wishlist-card-info h4 {
    margin:0 0 8px;
    color:var(--green-deep);
}
.wishlist-card-info p {
    margin: 4px 0;
    color:var(--text-mid);
    font-size:14px;
}
.wishlist-card-actions { text-align:right; }
</style>

</body>
</html>
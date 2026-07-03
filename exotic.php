<?php
// exotic.php — Exotic Plants Collection
session_start();
require_once 'db.php';

$msg = '';
$msgType = '';
$sessionKey = session_id();

// ============================================
// Handle Wishlist actions only
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wishlist_action'])) {
    $plantId = (int)$_POST['wishlist_action'];
    $wishlistEmail = trim($_POST['customer_email'] ?? $_SESSION['customer_email'] ?? '');
    if ($plantId > 0) {
        $check = $conn->prepare("SELECT id FROM wishlists WHERE plant_id = ? AND (session_key = ? OR customer_email = ?) LIMIT 1");
        $check->bind_param("iss", $plantId, $sessionKey, $wishlistEmail);
        $check->execute();
        $wishRow = $check->get_result()->fetch_assoc();

        if ($wishRow) {
            $del = $conn->prepare("DELETE FROM wishlists WHERE id = ? AND (session_key = ? OR customer_email = ?)");
            $del->bind_param("iss", $wishRow['id'], $sessionKey, $wishlistEmail);
            $del->execute();
            $msg = '💔 Removed from your wishlist.';
            $msgType = 'info';
        } else {
            $ins = $conn->prepare("INSERT INTO wishlists (plant_id, session_key, customer_email) VALUES (?, ?, ?)");
            $wishlistEmail = $wishlistEmail !== '' ? $wishlistEmail : null;
            $ins->bind_param("iss", $plantId, $sessionKey, $wishlistEmail);
            $ins->execute();
            $msg = '💚 Added to wishlist!';
            $msgType = 'success';
        }
    }
}

// ============================================
// Load EXOTIC Plants only
// ============================================
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM plants WHERE stock > 0 AND type = 'exotic'";
$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND name LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}
$sql .= " ORDER BY name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$plants = $result->fetch_all(MYSQLI_ASSOC);

$reviewsSummary = [];
$reviewRows = $conn->query("SELECT plant_id, COUNT(*) AS review_count, AVG(rating) AS avg_rating FROM reviews WHERE approved = 1 GROUP BY plant_id");
while ($row = $reviewRows->fetch_assoc()) {
    $reviewsSummary[$row['plant_id']] = $row;
}

$wishlistPlants = [];
$wishlistEmail = trim($_SESSION['customer_email'] ?? '');
if ($wishlistEmail !== '') {
    $wishStmt = $conn->prepare("SELECT plant_id FROM wishlists WHERE session_key = ? OR customer_email = ?");
    $wishStmt->bind_param("ss", $sessionKey, $wishlistEmail);
} else {
    $wishStmt = $conn->prepare("SELECT plant_id FROM wishlists WHERE session_key = ?");
    $wishStmt->bind_param("s", $sessionKey);
}
$wishStmt->execute();
$wishResult = $wishStmt->get_result();
while ($row = $wishResult->fetch_assoc()) {
    $wishlistPlants[] = $row['plant_id'];
}

$wishlistItems = [];
if (!empty($wishlistPlants)) {
    $placeholders = implode(',', array_fill(0, count($wishlistPlants), '?'));
    $types = str_repeat('i', count($wishlistPlants));
    $stmt2 = $conn->prepare("SELECT id, name FROM plants WHERE id IN ($placeholders)");
    $stmt2->bind_param($types, ...$wishlistPlants);
    $stmt2->execute();
    $win = $stmt2->get_result();
    while ($row = $win->fetch_assoc()) {
        $wishlistItems[] = $row['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✨ Exotic Plants — LeafLine</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
    <ul class="navbar-nav">
        <li><a href="/plantshop/">Home</a></li>
        <li><a href="shop">Shop</a></li>
        <li><a href="wishlist">Favorites</a></li>
        <li><a href="exotic" class="active">✨ Exotic</a></li>
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


<div class="page-header exotic-header" style="margin-bottom:0; background: linear-gradient(135deg, #40916c 0%, #2d6a4f 100%); color: white;">
    <div class="container">
        <h1>✨ Exotic Plants Collection</h1>
        <p>Discover rare and extraordinary plants from around the world.</p>
    </div>
</div>

<div class="page-wrapper">

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
    <?php endif; ?>

    <!-- SEARCH BAR -->
    <form method="GET" action="exotic" class="search-bar">
        <input type="text" name="search" placeholder="🔍 Search exotic plants..."
               class="search-input" value="<?= e($search) ?>">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if (!empty($search)): ?>
        <a href="exotic" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <div id="shop-tab" class="tab-pane active">
        <div style="max-width:1200px; margin:0 auto;">
            <?php if (count($plants) === 0): ?>
            <div class="empty-state">
                <div class="empty-icon">🌺</div>
                <p>No exotic plants found. Try a different search or check back soon!</p>
            </div>
            <?php else: ?>
            <div class="plant-grid">
                <?php foreach ($plants as $plant):
                    $hasDiscount = !empty($plant['discount_percent']);
                    $currentPrice = $hasDiscount ? discountedPrice($plant['price'], $plant['discount_percent']) : $plant['price'];
                    $reviewCount = $reviewsSummary[$plant['id']]['review_count'] ?? 0;
                    $avgRating = round($reviewsSummary[$plant['id']]['avg_rating'] ?? 0, 1);
                    $inWishlist = in_array($plant['id'], $wishlistPlants, true);
                ?>
                <div class="plant-card">
                    <div class="plant-card-image-wrap">
                        <img src="<?= e($plant['image_url']) ?>" alt="<?= e($plant['name']) ?>"
                             class="plant-card-image"
                             onerror="this.style.display='none'">
                        <span class="plant-badge exotic">✨ Exotic</span>
                        <?php if ($hasDiscount): ?>
                        <span class="plant-discount-badge">Save <?= $plant['discount_percent'] ?>%</span>
                        <?php endif; ?>
                    </div>
                    <div class="plant-card-body">
                        <div class="plant-card-name"><?= e($plant['name']) ?></div>
                        <div class="plant-card-desc"><?= e(substr($plant['description'],0,90)) ?>...</div>
                        <div class="plant-care-info">
                            <span class="care-tag">☀️ <?= e($plant['sunlight']) ?></span>
                            <span class="care-tag">💧 <?= e($plant['watering_days']) ?>d</span>
                        </div>
                    </div>
                    <div class="plant-card-footer">
                        <div>
                            <div class="plant-price"><?= formatPrice($currentPrice) ?>
                                <?php if ($hasDiscount): ?><span class="price-faded"><?= formatPrice($plant['price']) ?></span><?php endif; ?>
                            </div>
                            <?php if ($reviewCount): ?>
                            <div class="rating-row">
                                <?= str_repeat('★', (int)round($avgRating)) ?><?= str_repeat('☆', 5 - (int)round($avgRating)) ?>
                                <span class="rating-text"><?= $avgRating ?>/5 · <?= $reviewCount ?> reviews</span>
                            </div>
                            <?php endif; ?>
                            <div class="stock-label <?= $plant['stock'] <= 5 ? 'stock-low' : '' ?>">
                                <?= $plant['stock'] <= 5 ? "⚠️ Only {$plant['stock']} left" : "In stock: {$plant['stock']}" ?>
                            </div>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:10px; align-items:flex-end;">
                            <a href="shop?add=<?= $plant['id'] ?>&qty=1" class="btn btn-primary btn-sm" style="width:100%;">
                                🛒 Buy Now
                            </a>
                            <form method="POST" style="width:100%;">
                                <input type="hidden" name="customer_email" value="<?= e($_SESSION['customer_email'] ?? '') ?>">
                                <button type="submit" name="wishlist_action" value="<?= $plant['id'] ?>" class="btn btn-secondary btn-sm" style="width:100%;">
                                    <?= $inWishlist ? '💖 Saved' : '💚 Wishlist' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($wishlistItems)): ?>
            <div class="alert alert-info" style="margin-top:32px; font-size:13px;">
                <strong>💚 Your Wishlist (<?= count($wishlistItems) ?> items)</strong><br>
                <?= implode(', ', $wishlistItems) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
.exotic-header {
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}
.plant-badge.exotic {
    background: linear-gradient(135deg, #40916c 0%, #2d6a4f 100%);
}
</style>

</body>
</html>

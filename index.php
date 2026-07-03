<?php
// index.php — Landing Page
session_start();
require_once 'db.php';

// Fetch all plants for the homepage
$plants = $conn->query("SELECT * FROM plants ORDER BY name ASC");
$totalPlants = $conn->query("SELECT COUNT(*) as c FROM plants")->fetch_assoc()['c'];
$totalOrders = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🌿 LeafLine — Smart Plant Shop</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- NAVIGATION -->
<nav class="navbar">
    <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
    <ul class="navbar-nav">
        <li><a href="/plantshop/" class="active">Home</a></li>
        <li><a href="shop">Shop</a></li>
        <li><a href="exotic">Exotic</a></li>
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


<!-- HERO -->
<section class="hero">
    <div class="hero-content">
        <h1>Bring Nature<br>Into Your Home</h1>
        <p>Discover curated plants with built-in care guides, tracking reminders, and an exotic collection unlike any other.</p>
        <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
            <a href="shop" class="btn btn-gold btn-lg">🛒 Shop Plants</a>
            <a href="exotic" class="btn btn-secondary btn-lg" style="background:rgba(255,255,255,0.15); color:#fff;">✨ Exotic Collection</a>
        </div>
        <div style="display:flex; gap:40px; justify-content:center; margin-top:40px; flex-wrap:wrap;">
            <div style="text-align:center;">
                <div style="font-family:'Playfair Display',serif; font-size:2.5rem; color:#fff; font-weight:700;"><?= $totalPlants ?>+</div>
                <div style="color:var(--green-pale); font-size:14px;">Plant Varieties</div>
            </div>
            <div style="text-align:center;">
                <div style="font-family:'Playfair Display',serif; font-size:2.5rem; color:#fff; font-weight:700;"><?= $totalOrders ?>+</div>
                <div style="color:var(--green-pale); font-size:14px;">Happy Orders</div>
            </div>
            <div style="text-align:center;">
                <div style="font-family:'Playfair Display',serif; font-size:2.5rem; color:#fff; font-weight:700;">∞</div>
                <div style="color:var(--green-pale); font-size:14px;">Care Reminders</div>
            </div>
        </div>
    </div>
</section>

<!-- PLANT SHOP -->
<div class="page-wrapper">
    <div style="text-align:center; margin-bottom:32px;">
        <h2 style="font-size:2rem;">Plant Shop</h2>
        <p style="color:var(--text-mid); margin-top:8px;">Discover curated plants with built-in care guides, tracking reminders, and an exotic collection unlike any other.</p>
    </div>

    <div class="plant-grid">
        <?php while($plant = $plants->fetch_assoc()): ?>
        <div class="plant-card">
            <div class="plant-card-image-wrap">
                <img src="<?= e($plant['image_url']) ?>" alt="<?= e($plant['name']) ?>" class="plant-card-image"
                     onerror="this.style.display='none'">
                <span class="plant-badge <?= $plant['type'] ?>"><?= ucfirst($plant['type']) ?></span>
                <?php if (!empty($plant['discount_percent'])): ?>
                <span class="plant-discount-badge">Save <?= $plant['discount_percent'] ?>%</span>
                <?php endif; ?>
            </div>
            <div class="plant-card-body">
                <div class="plant-card-name"><?= e($plant['name']) ?></div>
                <div class="plant-card-desc"><?= e(substr($plant['description'], 0, 80)) ?>...</div>
                <div class="plant-care-info">
                    <span class="care-tag">☀️ <?= e($plant['sunlight']) ?> light</span>
                    <span class="care-tag">💧 Every <?= e($plant['watering_days']) ?> days</span>
                </div>
            </div>
            <div class="plant-card-footer">
                <?php $indexPrice = !empty($plant['discount_percent']) ? discountedPrice($plant['price'], $plant['discount_percent']) : $plant['price']; ?>
                <span class="plant-price"><?= formatPrice($indexPrice) ?>
                    <?php if (!empty($plant['discount_percent'])): ?>
                        <span class="price-faded"><?= formatPrice($plant['price']) ?></span>
                    <?php endif; ?>
                </span>
                <a href="shop?add=<?= $plant['id'] ?>" class="btn btn-primary btn-sm">Buy Now</a>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <div style="text-align:center; margin-top:32px;">
        <a href="exotic" class="btn btn-primary btn-lg">✨ View Exotic Collection →</a>
    </div>

    <!-- FEATURES SECTION -->
    <div class="divider" style="margin:56px 0 40px;"></div>
    <div style="text-align:center; margin-bottom:32px;">
        <h2 style="font-size:2rem;">Why LeafLine?</h2>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:24px; text-align:center;">
        <?php
        $features = [
            ['🌱', 'Smart Care Guides', 'Every plant comes with sunlight & watering info built in.'],
            ['🔔', 'Water Reminders', 'Log when you water and track when plants need attention.'],
            ['💚', 'Wishlist + Reviews', 'Save favorites, read customer feedback, and shop with confidence.'],
            ['🎫', 'Seasonal Discounts', 'Get special offers and product price drops right in the store.'],
        ];
        foreach ($features as $f):
        ?>
        <div style="background:var(--white); border-radius:var(--radius-lg); padding:28px 20px; box-shadow:var(--shadow-sm);">
            <div style="font-size:2.2rem; margin-bottom:12px;"><?= $f[0] ?></div>
            <h3 style="font-size:1.05rem; margin-bottom:8px;"><?= $f[1] ?></h3>
            <p style="font-size:13px; color:var(--text-mid);"><?= $f[2] ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- FOOTER -->
<footer>
    <p>🌿 <strong>LeafLine Plant Shop</strong> — Grow something beautiful today.</p>
    <p style="margin-top:8px; opacity:0.6; font-size:12px;">Built with PHP, MySQL & a love for plants.</p>
</footer>

</body>
</html>

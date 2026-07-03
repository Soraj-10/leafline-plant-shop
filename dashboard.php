<?php
// dashboard.php — Sales Analytics (Admin Only)
session_start();
require_once 'db.php';

// Redirect if not admin
if (!isAdmin()) {
    header('Location: add_plant');
    exit;
}

// ============================================
// ANALYTICS QUERIES
// ============================================

// 1. Total Revenue
$revenue = $conn->query("SELECT SUM(total_price) AS total FROM orders")->fetch_assoc()['total'] ?? 0;

// 2. Total Orders
$totalOrders = $conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'];

// 3. Total Customers
$totalCustomers = $conn->query("SELECT COUNT(*) AS c FROM customers")->fetch_assoc()['c'];

// 4. Total Plants in inventory
$totalPlants = $conn->query("SELECT COUNT(*) AS c FROM plants")->fetch_assoc()['c'];

// 5. Most sold plant
$topPlant = $conn->query("
    SELECT p.name, SUM(oi.quantity) AS total_sold
    FROM order_items oi
    JOIN plants p ON p.id = oi.plant_id
    GROUP BY oi.plant_id
    ORDER BY total_sold DESC
    LIMIT 1
")->fetch_assoc();

// 6. Low stock plants (stock <= 5)
$lowStock = $conn->query("SELECT * FROM plants WHERE stock <= 5 ORDER BY stock ASC");

// 7. Top 5 selling plants
$topSellers = $conn->query("
    SELECT p.name, p.type, SUM(oi.quantity) AS total_sold, SUM(oi.quantity * oi.price_at_purchase) AS revenue
    FROM order_items oi
    JOIN plants p ON p.id = oi.plant_id
    GROUP BY oi.plant_id
    ORDER BY total_sold DESC
    LIMIT 5
");

// 8. Recent 5 orders
$recentOrders = $conn->query("
    SELECT o.id, o.total_price, o.order_date, c.name AS customer_name
    FROM orders o
    JOIN customers c ON c.id = o.customer_id
    ORDER BY o.order_date DESC
    LIMIT 5
");

// 9. Revenue by plant type
$typeRevenue = $conn->query("
    SELECT p.type, SUM(oi.quantity * oi.price_at_purchase) AS revenue
    FROM order_items oi
    JOIN plants p ON p.id = oi.plant_id
    GROUP BY p.type
");
$typeData = [];
while ($r = $typeRevenue->fetch_assoc()) {
    $typeData[$r['type']] = $r['revenue'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — LeafLine</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
    <ul class="navbar-nav">
        <li><a href="/plantshop/">Home</a></li>
        <li><a href="shop">Shop</a></li>
        <li><a href="orders">Orders</a></li>
        <li><a href="dashboard" class="active">Dashboard</a></li>
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
        <li><a href="add_plant?logout=1" style="color:var(--accent-coral);">Logout</a></li>
    </ul>
</nav>


<div class="page-header">
    <div class="container">
        <h1>📊 Sales Dashboard</h1>
        <p>Real-time analytics for LeafLine Plant Shop.</p>
    </div>
</div>

<div class="page-wrapper">

    <!-- STAT CARDS -->
    <div class="stats-grid">
        <div class="stat-card gold">
            <div class="stat-icon">💰</div>
            <div class="stat-value"><?= formatPrice($revenue) ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-value"><?= $totalOrders ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card soft">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?= $totalCustomers ?></div>
            <div class="stat-label">Customers</div>
        </div>
        <div class="stat-card coral">
            <div class="stat-icon">🌱</div>
            <div class="stat-value"><?= $totalPlants ?></div>
            <div class="stat-label">Plant Varieties</div>
        </div>
    </div>

    <!-- TOP PLANT HIGHLIGHT -->
    <?php if ($topPlant): ?>
    <div style="background: linear-gradient(135deg, var(--green-mid), var(--green-bright)); border-radius:var(--radius-lg); padding:28px 32px; margin-bottom:32px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
        <div>
            <div style="color:var(--green-pale); font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px;">🏆 Most Sold Plant</div>
            <div style="font-family:'Playfair Display',serif; font-size:2rem; color:#fff; margin-top:6px;"><?= e($topPlant['name']) ?></div>
            <div style="color:var(--green-pale); margin-top:4px;"><?= $topPlant['total_sold'] ?> units sold</div>
        </div>
        <div style="font-size:4rem;">🌿</div>
    </div>
    <?php endif; ?>

    <!-- LOW STOCK ALERTS -->
    <?php if ($lowStock->num_rows > 0): ?>
    <div class="alert alert-warning" style="margin-bottom:28px;">
        <div>
            <strong>⚠️ Low Stock Alert!</strong>
            <div style="margin-top:8px; display:flex; flex-wrap:wrap; gap:8px;">
            <?php while ($p = $lowStock->fetch_assoc()): ?>
            <span class="badge badge-red"><?= e($p['name']) ?>: <?= $p['stock'] ?> left</span>
            <?php endwhile; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="admin-grid">

        <!-- TOP SELLERS TABLE -->
        <div>
            <h2 class="section-title">🌿 Top Selling Plants</h2>
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Plant</th>
                            <th>Type</th>
                            <th>Units Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $rank = 1; while ($p = $topSellers->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php if ($rank === 1) echo '🥇';
                                  elseif ($rank === 2) echo '🥈';
                                  elseif ($rank === 3) echo '🥉';
                                  else echo "#$rank"; ?>
                        </td>
                        <td><strong><?= e($p['name']) ?></strong></td>
                        <td><span class="badge <?= $p['type']==='exotic' ? 'badge-gold' : 'badge-green' ?>"><?= ucfirst($p['type']) ?></span></td>
                        <td><?= $p['total_sold'] ?></td>
                        <td style="color:var(--green-bright); font-weight:600;"><?= formatPrice($p['revenue']) ?></td>
                    </tr>
                    <?php $rank++; endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RECENT ORDERS -->
        <div>
            <h2 class="section-title">📅 Recent Orders</h2>
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($o = $recentOrders->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= $o['id'] ?></strong></td>
                        <td><?= e($o['customer_name']) ?></td>
                        <td style="font-size:13px; color:var(--text-mid);"><?= date('M j, Y', strtotime($o['order_date'])) ?></td>
                        <td style="color:var(--green-bright); font-weight:600;"><?= formatPrice($o['total_price']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Revenue by type -->
            <div style="background:var(--white); border-radius:var(--radius-lg); padding:24px; box-shadow:var(--shadow-sm); margin-top:20px;">
                <h3 style="font-size:1rem; margin-bottom:16px; color:var(--green-deep);">Revenue by Plant Type</h3>
                <?php foreach ($typeData as $type => $rev):
                    $pct = $revenue > 0 ? ($rev / $revenue * 100) : 0;
                ?>
                <div style="margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; font-size:14px; margin-bottom:6px;">
                        <span style="font-weight:600; text-transform:capitalize;"><?= $type ?></span>
                        <span style="color:var(--green-bright);"><?= formatPrice($rev) ?> (<?= round($pct) ?>%)</span>
                    </div>
                    <div style="height:8px; background:var(--green-wash); border-radius:4px; overflow:hidden;">
                        <div style="height:100%; width:<?= $pct ?>%; background:<?= $type==='exotic' ? 'var(--accent-gold)' : 'var(--green-bright)' ?>; border-radius:4px; transition:width 0.6s;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:16px; display:flex; gap:10px;">
                <a href="orders" class="btn btn-secondary">View All Orders</a>
                <a href="add_plant" class="btn btn-primary">Manage Plants</a>
            </div>
        </div>

    </div>
</div>

<footer><p>🌿 <strong>LeafLine Plant Shop</strong></p></footer>
</body>
</html>

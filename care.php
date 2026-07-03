<?php
// care.php — Plant Care Guide + Watering Tracker
session_start();
require_once 'db.php';

$msg = '';
$msgType = '';
$customer = null;
$careLogs = null;

// Find customer by email
$filterEmail = trim($_GET['email'] ?? '');
if ($filterEmail) {
    $stmt = $conn->prepare("SELECT * FROM customers WHERE email = ?");
    $stmt->bind_param("s", $filterEmail);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
}

// Log watering event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_water'])) {
    $custId  = (int)$_POST['customer_id'];
    $plantId = (int)$_POST['plant_id'];
    $notes   = $conn->real_escape_string(trim($_POST['notes'] ?? ''));
    $today   = date('Y-m-d');

    // Upsert: update if exists, else insert
    $exists = $conn->query("SELECT id FROM care_logs WHERE customer_id=$custId AND plant_id=$plantId");
    if ($exists->num_rows > 0) {
        $conn->query("UPDATE care_logs SET last_watered='$today', notes='$notes' WHERE customer_id=$custId AND plant_id=$plantId");
    } else {
        $conn->query("INSERT INTO care_logs (customer_id, plant_id, last_watered, notes) VALUES ($custId, $plantId, '$today', '$notes')");
    }
    $msg = '💧 Watering logged for today!';
    $msgType = 'success';
    header("Location: care?email=" . urlencode($filterEmail));
    exit;
}

// Load customer's plants (from past orders)
if ($customer) {
    $careLogs = $conn->query(
        "SELECT DISTINCT p.id, p.name, p.description, p.sunlight, p.watering_days, p.image_url,"
      . " cl.last_watered, cl.notes "
      . "FROM order_items oi "
      . "JOIN orders o ON o.id = oi.order_id "
      . "JOIN plants p ON p.id = oi.plant_id "
      . "LEFT JOIN care_logs cl ON cl.customer_id = o.customer_id AND cl.plant_id = p.id "
      . "WHERE o.customer_id = {$customer['id']} "
      . "ORDER BY p.name"
    );
}

// General care guide data
$allPlants = $conn->query("SELECT id, name, type, sunlight, watering_days, description, image_url FROM plants ORDER BY type, name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plant Care — LeafLine</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
    <ul class="navbar-nav">
        <li><a href="/plantshop/">Home</a></li>
        <li><a href="shop">Shop</a></li>
        <li><a href="care" class="active">Care</a></li>
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
        <h1>🌱 Plant Care Center</h1>
        <p>Track your watering, get care reminders, and learn about every plant.</p>
    </div>
</div>

<div class="page-wrapper">

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
    <?php endif; ?>

    <!-- CARE TRACKER -->
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:32px; margin-bottom:48px; align-items:start;">

        <div>
            <h2 class="section-title">💧 My Watering Tracker</h2>
            <!-- Email Search -->
            <div class="form-card" style="margin-bottom:20px;">
                <form method="GET">
                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label">Your Email (to track your plants)</label>
                        <input type="email" name="email" class="form-control"
                               placeholder="you@email.com"
                               value="<?= e($filterEmail) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Find My Plants</button>
                </form>
            </div>

            <?php if ($filterEmail && !$customer): ?>
            <div class="alert alert-warning">No account found. <a href="shop">Buy plants first!</a></div>

            <?php elseif ($customer && $careLogs): ?>
            <p style="margin-bottom:16px; font-size:14px; color:var(--text-mid);">
                Showing plants for <strong><?= e($customer['name']) ?></strong>
            </p>

            <?php if ($careLogs->num_rows === 0): ?>
            <div class="alert alert-info">You haven't ordered any plants yet. <a href="shop">Shop now!</a></div>

            <?php else: while ($log = $careLogs->fetch_assoc()):
                $daysUntil = $log['last_watered']
                    ? getDaysUntilWater($log['last_watered'], $log['watering_days'])
                    : -999;

                if ($daysUntil === -999) {
                    $statusClass = 'care-due';
                    $statusIcon  = '💧';
                    $statusText  = 'Never logged';
                    $cardClass   = 'care-due';
                    $waterClass  = 'water-due';
                } elseif ($daysUntil < 0) {
                    $statusClass = 'care-due';
                    $statusIcon  = '🚨';
                    $statusText  = abs($daysUntil) . ' days overdue!';
                    $cardClass   = 'care-due';
                    $waterClass  = 'water-due';
                } elseif ($daysUntil === 0) {
                    $statusClass = 'care-soon';
                    $statusIcon  = '💧';
                    $statusText  = 'Water today!';
                    $cardClass   = 'care-soon';
                    $waterClass  = 'water-soon';
                } elseif ($daysUntil <= 2) {
                    $statusClass = 'care-soon';
                    $statusIcon  = '⏳';
                    $statusText  = "In $daysUntil day(s)";
                    $cardClass   = 'care-soon';
                    $waterClass  = 'water-soon';
                } else {
                    $statusClass = 'care-ok';
                    $statusIcon  = '✅';
                    $statusText  = "In $daysUntil days";
                    $cardClass   = 'care-ok';
                    $waterClass  = 'water-ok';
                }
            ?>
            <div class="care-card">
                <div class="care-card-icon <?= $cardClass ?>"><?= $statusIcon ?></div>
                <div class="care-card-info">
                    <div class="care-card-name"><?= e($log['name']) ?></div>
                    <div class="care-card-meta">
                        💧 Every <?= $log['watering_days'] ?> days
                        <?php if ($log['last_watered']): ?>
                        · Last: <?= date('M j', strtotime($log['last_watered'])) ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($log['description'])): ?>
                    <div style="font-size:13px; color:var(--text-mid); margin-top:8px; line-height:1.5;"><?= e($log['description']) ?></div>
                    <?php endif; ?>
                    <?php if ($log['notes']): ?>
                    <div style="font-size:12px; color:var(--text-light); margin-top:6px; font-style:italic;">Notes: <?= e($log['notes']) ?></div>
                    <?php endif; ?>
                </div>
                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px;">
                    <span class="water-status <?= $waterClass ?>"><?= $statusText ?></span>
                    <!-- Log Water Button -->
                    <form method="POST">
                        <input type="hidden" name="customer_id" value="<?= $customer['id'] ?>">
                        <input type="hidden" name="plant_id" value="<?= $log['id'] ?>">
                        <input type="hidden" name="log_water" value="1">
                        <input type="hidden" name="notes" value="">
                        <button type="submit" class="btn btn-primary btn-sm">💧 Log Water</button>
                    </form>
                </div>
            </div>
            <?php endwhile; endif; ?>
            <?php endif; ?>
        </div>

        <!-- CARE TIPS -->
        <div>
            <h2 class="section-title">📖 Quick Care Tips</h2>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <?php
                $tips = [
                    ['💡', 'Low Light Plants', 'Snake Plant, Pothos, and ZZ Plant thrive in indirect light — perfect for offices and dark corners.'],
                    ['☀️', 'High Sun Plants', 'Aloe Vera, Bonsai, and Succulents need 4–6 hours of direct sunlight daily.'],
                    ['💧', 'Overwatering Warning', 'Most houseplant deaths are from overwatering, not underwatering. When in doubt, wait!'],
                    ['🌡️', 'Temperature', 'Most tropical plants prefer 15–27°C. Keep away from cold drafts and AC vents.'],
                    ['🪴', 'Repotting', 'Repot when roots escape the drainage hole — usually every 1–2 years.'],
                    ['🌿', 'Exotic Care', 'Exotic plants often need higher humidity. Try a pebble tray with water near the pot.'],
                ];
                foreach ($tips as $tip): ?>
                <div style="background:var(--white); border-radius:var(--radius); padding:16px; box-shadow:var(--shadow-sm); display:flex; gap:12px; align-items:flex-start;">
                    <div style="font-size:1.4rem; flex-shrink:0;"><?= $tip[0] ?></div>
                    <div>
                        <div style="font-weight:600; font-size:14px; color:var(--green-deep); margin-bottom:3px;"><?= $tip[1] ?></div>
                        <div style="font-size:13px; color:var(--text-mid);"><?= $tip[2] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- FULL CARE GUIDE TABLE -->
    <h2 class="section-title" style="margin-bottom:20px;">📋 Complete Plant Care Guide</h2>
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Plant</th>
                    <th>Type</th>
                    <th>Sunlight</th>
                    <th>Water Every</th>
                    <th>Care Level</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($p = $allPlants->fetch_assoc()):
                // Determine care level
                if ($p['watering_days'] >= 14) $careLevel = ['Easy', 'badge-green'];
                elseif ($p['watering_days'] >= 7) $careLevel = ['Medium', 'badge-gold'];
                else $careLevel = ['Attentive', 'badge-red'];
            ?>
            <tr>
                <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <img src="<?= e($p['image_url']) ?>" alt=""
                             style="width:36px; height:36px; object-fit:cover; border-radius:8px;"
                             onerror="this.style.display='none'">
                        <strong><?= e($p['name']) ?></strong>
                    </div>
                </td>
                <td><span class="badge <?= $p['type']==='exotic' ? 'badge-gold' : 'badge-green' ?>"><?= ucfirst($p['type']) ?></span></td>
                <td>
                    <?php
                    $sunIcons = ['Low' => '🌑', 'Medium' => '🌤️', 'High' => '☀️'];
                    echo ($sunIcons[$p['sunlight']] ?? '🌱') . ' ' . e($p['sunlight']);
                    ?>
                </td>
                <td>💧 <?= $p['watering_days'] ?> days</td>
                <td><span class="badge <?= $careLevel[1] ?>"><?= $careLevel[0] ?></span></td>
                <td style="font-size:13px; color:var(--text-mid);"><?= e(substr($p['description'], 0, 60)) ?>...</td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<footer><p>🌿 <strong>LeafLine Plant Shop</strong></p></footer>
</body>
</html>

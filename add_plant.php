<?php
// add_plant.php — Admin Panel (PIN Protected)
session_start();
require_once 'db.php';

$msg = '';
$msgType = '';

// ============================================
// Handle PIN Login / Logout
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'])) {
    if ($_POST['pin'] === ADMIN_PIN) {
        $_SESSION['admin_verified'] = true;
    } else {
        $msg = '❌ Incorrect PIN. Try again.';
        $msgType = 'danger';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_verified']);
    header('Location: add_plant');
    exit;
}

// ============================================
// ADMIN ACTIONS (only if verified)
// ============================================
if (isAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // ADD PLANT
    if (isset($_POST['action']) && $_POST['action'] === 'add_plant') {
        $name            = trim($_POST['name']);
        $type            = $_POST['type'];
        $price           = (float)$_POST['price'];
        $stock           = (int)$_POST['stock'];
        $desc            = trim($_POST['description']);
        $sun             = trim($_POST['sunlight']);
        $days            = (int)$_POST['watering_days'];
        $discount        = min(100, max(0, (int)($_POST['discount_percent'] ?? 0)));
        $imageUrl        = trim($_POST['image_url']);
        $uploadedImage   = '';

        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['image_file'];
            if ($file['error'] === UPLOAD_ERR_OK && strpos($file['type'], 'image/') === 0) {
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0755, true);
                }
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $extension = $extension ? strtolower($extension) : 'jpg';
                $safeName = 'uploads/plant_' . time() . '_' . uniqid() . '.' . $extension;
                if (move_uploaded_file($file['tmp_name'], $safeName)) {
                    $uploadedImage = $safeName;
                }
            }
        }

        if ($uploadedImage !== '') {
            $imageUrl = $uploadedImage;
        }

        if (empty($name) || $price <= 0 || $stock < 0 || $days < 1) {
            $msg = 'Please fill in all required fields correctly.';
            $msgType = 'danger';
        } else {
            $check = $conn->prepare("SELECT id, stock FROM plants WHERE name = ? AND type = ?");
            $check->bind_param("ss", $name, $type);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();

            if ($existing) {
                $newStock = $existing['stock'] + $stock;
                $updateSql = "UPDATE plants SET stock=?, price=?, discount_percent=?, description=?, sunlight=?, watering_days=?, image_url=? WHERE id=?";
                $upd = $conn->prepare($updateSql);
                $upd->bind_param("idissisi", $newStock, $price, $discount, $desc, $sun, $days, $imageUrl, $existing['id']);
                $upd->execute();
                $msg = "✅ Plant '{$name}' already existed — stock merged! New stock: {$newStock}";
                $msgType = 'success';
            } else {
                $sql = "INSERT INTO plants (name, type, price, stock, description, sunlight, watering_days, image_url, discount_percent)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $ins = $conn->prepare($sql);
                $ins->bind_param("ssdiisssi", $name, $type, $price, $stock, $desc, $sun, $days, $imageUrl, $discount);
                if ($ins->execute()) {
                    $msg = "✅ Plant '{$name}' added successfully!";
                    $msgType = 'success';
                } else {
                    $msg = '❌ Error: ' . $conn->error;
                    $msgType = 'danger';
                }
            }
        }
    }

    // DELETE PLANT
    if (isset($_POST['action']) && $_POST['action'] === 'delete_plant') {
        $delId = (int)$_POST['plant_id'];
        $conn->query("DELETE FROM plants WHERE id = $delId");
        $msg = '🗑️ Plant deleted.';
        $msgType = 'success';
    }

    // UPDATE STOCK / PRICE / DISCOUNT
    if (isset($_POST['action']) && $_POST['action'] === 'update_plant') {
        $upId       = (int)$_POST['plant_id'];
        $upStock    = (int)$_POST['stock'];
        $upPrice    = (float)$_POST['price'];
        $upDiscount = min(100, max(0, (int)($_POST['discount_percent'] ?? 0)));
        $safePrice  = (float) number_format($upPrice, 2, '.', '');

        $stmt = $conn->prepare("UPDATE plants SET stock=?, price=?, discount_percent=? WHERE id=?");
        $stmt->bind_param("idii", $upStock, $safePrice, $upDiscount, $upId);
        $stmt->execute();
        $msg = '✅ Plant updated.';
        $msgType = 'success';
    }
}

// Load all plants for the table
$allPlants = $conn->query("SELECT * FROM plants ORDER BY type, name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel — LeafLine</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
    <ul class="navbar-nav">
        <li><a href="/plantshop/">Home</a></li>
        <li><a href="shop">Shop</a></li>
        <li><a href="dashboard">Dashboard</a></li>
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


<!-- PIN GATE -->
<?php if (!isAdmin()): ?>
<div class="page-wrapper">
    <div class="pin-gate">
        <div style="font-size:3rem; margin-bottom:16px;">🔐</div>
        <h2>Admin Access</h2>
        <p>Enter the admin PIN to manage plants, view analytics, and more.</p>

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <input type="password" name="pin" class="form-control"
                       placeholder="Enter PIN" style="text-align:center; font-size:1.5rem; letter-spacing:8px;"
                       maxlength="10" autofocus>
            </div>
            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">Unlock Admin</button>
        </form>
        <p style="margin-top:16px; font-size:12px; color:var(--text-light);">Default PIN: 111</p>
    </div>
</div>

<?php else: // ADMIN IS VERIFIED ?>

<div class="page-header">
    <div class="container">
        <h1>⚙️ Admin Panel</h1>
        <p>Manage your plant inventory — add, update, or remove plants.</p>
    </div>
</div>

<div class="page-wrapper">

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
    <?php endif; ?>

    <div class="admin-grid">

        <!-- ADD PLANT FORM -->
        <div>
            <h2 class="section-title">➕ Add New Plant</h2>
            <div class="form-card">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_plant">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Plant Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Cactus" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Type *</label>
                            <select name="type" class="form-control">
                                <option value="regular">Regular</option>
                                <option value="exotic">Exotic</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Price ($) *</label>
                            <input type="number" name="price" class="form-control" placeholder="19.99" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock *</label>
                            <input type="number" name="stock" class="form-control" placeholder="20" min="0" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Sunlight Need</label>
                            <select name="sunlight" class="form-control">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Water Every (days)</label>
                            <input type="number" name="watering_days" class="form-control" value="7" min="1">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" placeholder="Short description of the plant..."></textarea>
                    </div>

                        <div class="form-group">
                        <label class="form-label">Image URL</label>
                        <input type="url" name="image_url" class="form-control"
                               placeholder="https://images.unsplash.com/...">
                        <small style="color:var(--text-light); font-size:12px;">Or upload a photo of your plant below.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Upload Plant Image</label>
                        <input type="file" name="image_file" accept="image/*" class="form-control" style="padding:10px 12px;">
                        <small style="color:var(--text-light); font-size:12px;">JPEG, PNG or WebP. Uploaded images are saved locally.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Discount (%)</label>
                        <input type="number" name="discount_percent" class="form-control" placeholder="0"
                               min="0" max="100" value="0">
                        <small style="color:var(--text-light); font-size:12px;">Set a promotional discount for this plant.</small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
                        ➕ Add Plant
                    </button>
                    <p style="font-size:12px; color:var(--text-light); margin-top:10px;">
                        💡 If a plant with the same name + type already exists, stock will be merged automatically.
                    </p>
                </form>
            </div>
        </div>

        <!-- PLANT MANAGEMENT TABLE -->
        <div>
            <h2 class="section-title">📋 Manage Plants</h2>
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Plant</th>
                            <th>Type</th>
                            <th>Price</th>
                            <th>Discount</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($p = $allPlants->fetch_assoc()): ?>
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
                            <?= formatPrice($p['price']) ?>
                            <?php if (!empty($p['discount_percent'])): ?>
                                <div style="font-size:12px; color:var(--text-mid);">After discount: <?= formatPrice(discountedPrice($p['price'], $p['discount_percent'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= $p['discount_percent'] ?>%</td>
                        <td class="<?= $p['stock'] <= 3 ? 'stock-low' : '' ?>">
                            <?= $p['stock'] ?><?= $p['stock'] <= 3 ? ' ⚠️' : '' ?>
                        </td>
                        <td>
                            <!-- Quick Update Form -->
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Update this plant?')">
                                <input type="hidden" name="action" value="update_plant">
                                <input type="hidden" name="plant_id" value="<?= $p['id'] ?>">
                                <input type="number" name="price" value="<?= $p['price'] ?>"
                                       style="width:68px; padding:4px 6px; border:1px solid var(--green-pale); border-radius:6px; font-size:13px;"
                                       step="0.01" min="0.01">
                                <input type="number" name="discount_percent" value="<?= $p['discount_percent'] ?? 0 ?>"
                                       style="width:52px; padding:4px 6px; border:1px solid var(--green-pale); border-radius:6px; font-size:13px;"
                                       min="0" max="100" placeholder="%">
                                <input type="number" name="stock" value="<?= $p['stock'] ?>"
                                       style="width:56px; padding:4px 6px; border:1px solid var(--green-pale); border-radius:6px; font-size:13px;"
                                       min="0">
                                <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                            </form>
                            <!-- Delete Form -->
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this plant permanently?')">
                                <input type="hidden" name="action" value="delete_plant">
                                <input type="hidden" name="plant_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:24px;">
                <a href="dashboard" class="btn btn-gold">📊 View Dashboard</a>
                <a href="orders" class="btn btn-secondary" style="margin-left:8px;">📦 View Orders</a>
            </div>
        </div>

    </div>
</div>
<?php endif; ?>

<footer>
    <p>🌿 <strong>LeafLine Plant Shop</strong></p>
</footer>

</body>
</html>

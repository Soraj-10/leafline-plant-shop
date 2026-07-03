<?php
session_start();
require_once 'db.php';

if (!isAdmin()) {
    header('Location: add_plant');
    exit;
}

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_code') {
        $code = trim($_POST['code']);
        $discountPercent = (float)$_POST['discount_percent'];
        $validUntil = $_POST['valid_until'];

        if (empty($code) || $discountPercent <= 0 || $discountPercent > 100 || empty($validUntil)) {
            $msg = 'Please complete all fields correctly.';
            $msgType = 'danger';
        } else {
            $stmt = $conn->prepare("INSERT INTO discount_codes (code, discount_percent, valid_until) VALUES (?, ?, ?)");
            $stmt->bind_param('sds', $code, $discountPercent, $validUntil);
            if ($stmt->execute()) {
                $msg = 'Discount code created successfully.';
                $msgType = 'success';
            } elseif ($conn->errno === 1062) {
                $msg = 'That code already exists. Please choose a different code.';
                $msgType = 'danger';
            } else {
                $msg = 'Unable to save discount code. Please try again.';
                $msgType = 'danger';
            }
        }
    }
    if (isset($_POST['action']) && $_POST['action'] === 'delete_code') {
        $codeId = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM discount_codes WHERE id = ?");
        $stmt->bind_param('i', $codeId);
        $stmt->execute();
        $msg = 'Discount code deleted.';
        $msgType = 'success';
    }
}

$result = $conn->query("SELECT * FROM discount_codes ORDER BY created_at DESC");
$codes = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discounts — LeafLine Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
    <ul class="navbar-nav">
        <li><a href="/plantshop/">Home</a></li>
        <li><a href="dashboard">Dashboard</a></li>
        <li><a href="admin_reviews">Reviews</a></li>
        <li><a href="admin_wishlist">Wishlists</a></li>
        <li><a href="discount_codes" class="active">Discounts</a></li>
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
        <h1>🎫 Discount Codes</h1>
        <p>Create promotional codes for your customers.</p>
    </div>
</div>

<div class="page-wrapper">
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="form-card" style="margin-bottom: 24px;">
        <h3>Add Discount Code</h3>
        <form method="POST" style="display:grid; gap:16px;">
            <input type="hidden" name="action" value="add_code">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Code</label>
                    <input type="text" name="code" class="form-control" placeholder="SPRING20" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Discount %</label>
                    <input type="number" name="discount_percent" class="form-control" min="1" max="100" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Valid Until</label>
                    <input type="date" name="valid_until" class="form-control" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Code</button>
        </form>
    </div>

    <div class="table-card">
        <h3>Active Discount Codes</h3>
        <?php if (empty($codes)): ?>
            <p>No discount codes created yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Discount</th>
                        <th>Valid Until</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($codes as $code): ?>
                    <tr>
                        <td><code><?= e($code['code']) ?></code></td>
                        <td><?= e($code['discount_percent']) ?>%</td>
                        <td><?= e($code['valid_until']) ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete_code">
                                <input type="hidden" name="id" value="<?= $code['id'] ?>">
                                <button type="submit" class="btn btn-secondary" style="background:#e07a5f; color:#fff;" onclick="return confirm('Delete this code?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
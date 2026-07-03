<?php
session_start();
require_once 'db.php';

$msg = '';
$msgType = '';
$customerEmail = trim($_POST['customer_email'] ?? $_GET['email'] ?? $_SESSION['customer_email'] ?? '');
$allowedPlants = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_review') {
    $name = trim($_POST['customer_name']);
    $email = trim($_POST['customer_email']);
    $plantId = (int)($_POST['plant_id'] ?? 0);
    $rating = max(1, min(5, (int)$_POST['rating']));
    $comment = trim($_POST['comment']);

    if (empty($name) || empty($email) || empty($comment) || $plantId < 1) {
        $msg = 'Please complete all fields before submitting.';
        $msgType = 'danger';
    } else {
        $checkPurchase = $conn->prepare(
            "SELECT COUNT(*) AS purchased FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             JOIN customers c ON c.id = o.customer_id
             WHERE c.email = ? AND oi.plant_id = ?"
        );
        $checkPurchase->bind_param('si', $email, $plantId);
        $checkPurchase->execute();
        $purchaseResult = $checkPurchase->get_result()->fetch_assoc();

        if (empty($purchaseResult['purchased'])) {
            $msg = 'You can only review plants you have purchased with this email.';
            $msgType = 'danger';
        } else {
            $stmt = $conn->prepare("INSERT INTO reviews (plant_id, customer_name, customer_email, rating, comment) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('issis', $plantId, $name, $email, $rating, $comment);
            $stmt->execute();
            $_SESSION['customer_email'] = $email;
            $_SESSION['customer_name'] = $name;
            $msg = 'Thank you! Your review has been submitted and will appear once approved.';
            $msgType = 'success';
        }
    }
}

if ($customerEmail) {
    $stmt = $conn->prepare(
        "SELECT DISTINCT p.id, p.name
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         JOIN customers c ON c.id = o.customer_id
         JOIN plants p ON p.id = oi.plant_id
         WHERE c.email = ?
         ORDER BY p.name"
    );
    $stmt->bind_param('s', $customerEmail);
    $stmt->execute();
    $allowedPlants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$plants = $allowedPlants;
$reviews = $conn->query("SELECT r.*, p.name AS plant_name FROM reviews r JOIN plants p ON p.id = r.plant_id WHERE r.approved = 1 ORDER BY r.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$averageRating = 0;
if (count($reviews) > 0) {
    $averageRating = array_sum(array_column($reviews, 'rating')) / count($reviews);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews — LeafLine</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
    <ul class="navbar-nav">
        <li><a href="/plantshop/">Home</a></li>
        <li><a href="shop">Shop</a></li>
        <li><a href="wishlist">Favorites</a></li>
        <li><a href="exotic">Exotic</a></li>
        <li><a href="care">Care</a></li>
        <li><a href="reviews" class="active">Reviews</a></li>
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
        <h1>📝 Customer Reviews</h1>
        <p>Read what our customers are saying and add your own review.</p>
        <?php if (!empty($reviews)): ?>
            <div style="margin-top:16px; color:var(--green-pale); font-size:14px;">
                Average rating: <?= number_format($averageRating, 1) ?>/5 from <?= count($reviews) ?> reviews
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="page-wrapper">
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="form-card" style="margin-bottom:32px;">
        <h3>Share your experience</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_review">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="customer_name" class="form-control" required value="<?= e($_SESSION['customer_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="customer_email" class="form-control" required value="<?= e($customerEmail ?? $_SESSION['customer_email'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Plant</label>
                    <select name="plant_id" class="form-control" required>
                        <?php if (!$customerEmail): ?>
                            <option value="">Enter your email first to load purchased plants</option>
                        <?php elseif (empty($plants)): ?>
                            <option value="">No purchased plants found for <?= e($customerEmail) ?></option>
                        <?php else: ?>
                            <option value="">Select a purchased plant</option>
                            <?php foreach ($plants as $plant): ?>
                                <option value="<?= $plant['id'] ?>"><?= e($plant['name']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if ($customerEmail && empty($plants)): ?>
                        <small style="color:var(--text-mid);">Please place an order with this email before leaving a review.</small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Rating</label>
                    <select name="rating" class="form-control" required>
                        <option value="5">⭐⭐⭐⭐⭐ 5/5</option>
                        <option value="4">⭐⭐⭐⭐ 4/5</option>
                        <option value="3">⭐⭐⭐ 3/5</option>
                        <option value="2">⭐⭐ 2/5</option>
                        <option value="1">⭐ 1/5</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Comment</label>
                <textarea name="comment" rows="4" class="form-control" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Submit Review</button>
        </form>
    </div>

    <?php if (empty($reviews)): ?>
        <div class="empty-state">
            <div class="empty-icon">💬</div>
            <p>No reviews yet. Be the first to share your experience.</p>
        </div>
    <?php else: ?>
        <?php foreach ($reviews as $review): ?>
            <div class="review-card">
                <div class="review-card-header">
                    <div>
                        <strong><?= e($review['plant_name']) ?></strong>
                        <div style="font-size:13px; color:var(--text-mid); margin-top:6px;">by <?= e($review['customer_name']) ?></div>
                        <div style="font-size:12px; color:var(--text-light);"><?= date('M j, Y', strtotime($review['created_at'])) ?></div>
                    </div>
                    <div>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span style="color: <?= $i <= $review['rating'] ? '#ffd700' : '#ddd' ?>;">⭐</span>
                        <?php endfor; ?>
                    </div>
                </div>
                <p style="margin-top:12px; line-height:1.7;"><?= nl2br(e($review['comment'])) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
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
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $msg = 'Review deleted successfully.';
        $msgType = 'success';
    }
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_approve') {
        $id = (int)($_POST['id'] ?? 0);
        $approve = ($_POST['approve'] ?? '0') === '1' ? 1 : 0;
        $stmt = $conn->prepare("UPDATE reviews SET approved = ? WHERE id = ?");
        $stmt->bind_param('ii', $approve, $id);
        $stmt->execute();
        $msg = $approve ? 'Review approved.' : 'Review marked unapproved.';
        $msgType = 'success';
    }
}

$result = $conn->query("SELECT r.*, p.name AS plant_name FROM reviews r JOIN plants p ON p.id = r.plant_id ORDER BY r.approved ASC, r.id DESC");
$reviews = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Reviews — LeafLine</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
    <ul class="navbar-nav">
        <li><a href="/plantshop/">Home</a></li>
        <li><a href="dashboard">Dashboard</a></li>
        <li><a href="admin_reviews" class="active">Reviews</a></li>
        <li><a href="admin_wishlist">Wishlists</a></li>
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
    </ul>
</nav>


<div class="page-header">
    <div class="container">
        <h1>📝 Admin Reviews</h1>
        <p>Manage customer reviews and remove any inappropriate feedback.</p>
    </div>
</div>

<div class="page-wrapper">
    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if (empty($reviews)): ?>
        <div class="empty-state">
            <div class="empty-icon">💤</div>
            <p>No customer reviews available yet.</p>
        </div>
    <?php else: ?>
        <div class="reviews-admin-list">
            <?php foreach ($reviews as $review): ?>
                <div class="review-admin-card">
                    <div class="review-admin-header">
                        <div>
                            <div style="font-size:13px; color:var(--green-mid); font-weight:700; margin-bottom:6px;"><?= e($review['plant_name']) ?></div>
                            <strong><?= e($review['customer_name']) ?> (<?= e($review['customer_email']) ?>)</strong>
                            <div style="font-size:12px; color:var(--text-light); margin-top:4px;"><?= date('M j, Y H:i', strtotime($review['created_at'])) ?></div>
                        </div>
                        <div style="text-align:right;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span style="color: <?= $i <= $review['rating'] ? '#ffd700' : '#ddd' ?>;">⭐</span>
                            <?php endfor; ?>
                            <div style="margin-top:8px;">
                                <?php if ($review['approved']): ?>
                                    <span class="badge badge-green">Approved</span>
                                <?php else: ?>
                                    <span class="badge badge-red">Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="review-admin-content">
                        <p><?= nl2br(e($review['comment'])) ?></p>
                    </div>
                    <div class="review-admin-actions">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_approve">
                            <input type="hidden" name="id" value="<?= $review['id'] ?>">
                            <input type="hidden" name="approve" value="<?= $review['approved'] ? '0' : '1' ?>">
                            <button type="submit" class="btn btn-secondary" style="background: <?= $review['approved'] ? '#e07a5f' : '#40916c' ?>; color:#fff;">
                                <?= $review['approved'] ? 'Unapprove' : 'Approve' ?>
                            </button>
                        </form>
                        <form method="POST" style="display:inline; margin-left:10px;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $review['id'] ?>">
                            <button type="submit" class="btn btn-secondary" style="background:#e07a5f; color:#fff;" onclick="return confirm('Delete this review?')">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.reviews-admin-list { display:flex; flex-direction:column; gap:20px; }
.review-admin-card { background:var(--white); border:1px solid #eee; border-radius:var(--radius); padding:20px; }
.review-admin-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; }
.review-admin-content { margin:16px 0; color:var(--text-mid); line-height:1.8; }
.review-admin-actions { text-align:right; }
</style>

</body>
</html>
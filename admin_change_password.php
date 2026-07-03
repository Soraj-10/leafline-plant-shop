<?php
// admin_change_password.php — Let an admin change their own password (NEW FILE)
session_start();
require_once 'db.php';
require_once 'auth_helpers.php';
ensureAuthSchema($conn);

if (!isAdmin()) {
    header("Location: admin_login.php");
    exit;
}

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $conn->prepare("SELECT password_hash FROM admins WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if (!$admin || !password_verify($current, $admin['password_hash'])) {
        $msg = 'Current password is incorrect.'; $msgType = 'danger';
    } elseif (strlen($new) < 6) {
        $msg = 'New password must be at least 6 characters.'; $msgType = 'danger';
    } elseif ($new !== $confirm) {
        $msg = 'New passwords do not match.'; $msgType = 'danger';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $_SESSION['admin_id']);
        $stmt->execute();
        $msg = '✅ Password updated successfully.'; $msgType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Admin Password — LeafLine</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php renderSiteNav(); ?>

<section style="max-width:440px; margin:60px auto; padding:0 20px;">
    <div class="card" style="padding:32px; border-top:4px solid #d4af37;">
        <h2 style="color:var(--green-deep); margin-bottom:6px;">🔐 Change Admin Password</h2>
        <p style="color:var(--text-mid); margin-bottom:24px;">Logged in as <?= e($_SESSION['admin_name'] ?? 'Admin') ?>.</p>

        <?php if ($msg): ?>
            <div style="padding:10px 14px; border-radius:8px; margin-bottom:16px; background:<?= $msgType==='danger' ? '#ffeaea' : '#d8f3dc' ?>; color:<?= $msgType==='danger' ? '#c0392b' : 'var(--green-deep)' ?>;">
                <?= e($msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Current Password</label>
            <input type="password" name="current_password" class="form-control" required style="margin-bottom:14px;">
            <label>New Password</label>
            <input type="password" name="new_password" class="form-control" required style="margin-bottom:14px;">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required style="margin-bottom:20px;">
            <button type="submit" class="btn btn-gold" style="width:100%;">Update Password</button>
        </form>
        <p style="margin-top:16px; text-align:center;"><a href="dashboard">← Back to Dashboard</a></p>
    </div>
</section>
</body>
</html>

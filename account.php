<?php
// account.php — Customer "My Account" profile page (NEW FILE)
session_start();
require_once 'db.php';
require_once 'auth_helpers.php';
ensureAuthSchema($conn);

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}
$customerId = (int)$_SESSION['customer_id'];
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '') {
        $msg = 'Name cannot be empty.'; $msgType = 'danger';
    } else {
        $stmt = $conn->prepare("UPDATE customers SET name = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $phone, $customerId);
        $stmt->execute();
        $_SESSION['customer_name'] = $name;
        $msg = '✅ Profile updated.'; $msgType = 'success';
    }

    // Optional password change on the same page
    if (!empty($_POST['new_password'])) {
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($new) < 6) {
            $msg = 'New password must be at least 6 characters.'; $msgType = 'danger';
        } elseif ($new !== $confirm) {
            $msg = 'New passwords do not match.'; $msgType = 'danger';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE customers SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $customerId);
            $stmt->execute();
            $msg = '✅ Profile and password updated.'; $msgType = 'success';
        }
    }
}

$stmt = $conn->prepare("SELECT name, email, phone FROM customers WHERE id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Account — LeafLine</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php renderSiteNav(); ?>

<section style="max-width:480px; margin:60px auto; padding:0 20px;">
    <div class="card" style="padding:32px; border-top:4px solid var(--green-bright);">
        <h2 style="color:var(--green-deep); margin-bottom:6px;">🙍 My Account</h2>
        <p style="color:var(--text-mid); margin-bottom:24px;"><?= e($customer['email']) ?></p>

        <?php if ($msg): ?>
            <div style="padding:10px 14px; border-radius:8px; margin-bottom:16px; background:<?= $msgType==='danger' ? '#ffeaea' : '#d8f3dc' ?>; color:<?= $msgType==='danger' ? '#c0392b' : 'var(--green-deep)' ?>;">
                <?= e($msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Full Name</label>
            <input type="text" name="name" class="form-control" value="<?= e($customer['name']) ?>" required style="margin-bottom:14px;">

            <label>Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= e($customer['phone']) ?>" style="margin-bottom:20px;">

            <hr style="border:none; border-top:1px solid var(--green-wash); margin:20px 0;">
            <p style="font-size:14px; color:var(--text-mid); margin-bottom:12px;">Leave blank to keep your current password.</p>

            <label>New Password</label>
            <input type="password" name="new_password" class="form-control" style="margin-bottom:14px;">

            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" style="margin-bottom:20px;">

            <button type="submit" class="btn btn-gold" style="width:100%;">Save Changes</button>
        </form>

        <div style="display:flex; justify-content:space-between; margin-top:20px;">
            <a href="loyalty.php">🏆 View Rewards</a>
            <a href="customer_history">📦 Order History</a>
        </div>
    </div>
</section>
</body>
</html>

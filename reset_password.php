<?php
// reset_password.php — Set a new password using a valid token (NEW FILE)
session_start();
require_once 'db.php';
require_once 'auth_helpers.php';
ensureAuthSchema($conn);

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$msg = ''; $msgType = ''; $valid = false;

if ($token) {
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    $valid = (bool)$reset;
}

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $msg = 'Password must be at least 6 characters.'; $msgType = 'danger';
    } elseif ($password !== $confirm) {
        $msg = 'Passwords do not match.'; $msgType = 'danger';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE customers SET password_hash = ? WHERE email = ?");
        $stmt->bind_param("ss", $hash, $reset['email']);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();

        header("Location: login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — LeafLine</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php renderSiteNav(); ?>

<section style="max-width:440px; margin:60px auto; padding:0 20px;">
    <div class="card" style="padding:32px; border-top:4px solid var(--green-bright);">
        <h2 style="color:var(--green-deep); margin-bottom:6px;">🔒 Reset Password</h2>

        <?php if (!$valid): ?>
            <p style="color:#c0392b;">This reset link is invalid or has expired.</p>
            <p><a href="forgot_password.php">Request a new one</a></p>
        <?php else: ?>
            <p style="color:var(--text-mid); margin-bottom:20px;">Choose a new password for <?= e($reset['email']) ?>.</p>

            <?php if ($msg): ?>
                <div style="padding:10px 14px; border-radius:8px; margin-bottom:16px; background:#ffeaea; color:#c0392b;">
                    <?= e($msg) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <label>New Password</label>
                <input type="password" name="password" class="form-control" required style="margin-bottom:14px;">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required style="margin-bottom:20px;">
                <button type="submit" class="btn btn-gold" style="width:100%;">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
</section>
</body>
</html>

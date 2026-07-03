<?php
// admin_login.php — Real Admin Login (NEW FILE, no existing files edited)
// Sets $_SESSION['admin_verified'] = true — the SAME flag your existing
// isAdmin() function in db.php already checks. So dashboard.php,
// add_plant.php, admin_reviews.php etc. all recognize this login
// automatically, with nothing in those files changed.
session_start();
require_once 'db.php';
require_once 'auth_helpers.php';
ensureAuthSchema($conn);

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, password_hash FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_verified'] = true;   // <-- same flag isAdmin() reads
        $_SESSION['admin_id']       = $admin['id'];
        $_SESSION['admin_name']     = $admin['name'];
        header("Location: dashboard");
        exit;
    } else {
        $msg = 'Incorrect admin email or password.'; $msgType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — LeafLine</title>
<link rel="stylesheet" href="style.css">
</head>
<body style="background:var(--green-deep); min-height:100vh;">

<section style="max-width:420px; margin:0 auto; padding-top:90px;">
    <div class="card" style="padding:32px; border-top:4px solid #d4af37;">
        <h2 style="color:var(--green-deep); margin-bottom:6px;">🔐 Admin Access</h2>
        <p style="color:var(--text-mid); margin-bottom:8px;">Separate, password-protected login for staff only.</p>
        <p style="font-size:13px; color:#999; margin-bottom:24px;">
            First time here? Default login is
            <code>admin@leafline.local</code> / <code>admin123</code> —
            change the password immediately after logging in.
        </p>

        <?php if ($msg): ?>
            <div style="padding:10px 14px; border-radius:8px; margin-bottom:16px; background:#ffeaea; color:#c0392b;">
                <?= e($msg) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Admin Email</label>
            <input type="email" name="email" class="form-control" required style="margin-bottom:14px;">

            <label>Password</label>
            <input type="password" name="password" class="form-control" required style="margin-bottom:20px;">

            <button type="submit" class="btn btn-gold" style="width:100%;">Log In as Admin</button>
        </form>
        <p style="margin-top:16px; text-align:center;"><a href="/plantshop/">← Back to site</a></p>
    </div>
</section>
</body>
</html>

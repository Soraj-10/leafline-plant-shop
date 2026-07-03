<?php
// forgot_password.php — Request a password reset (NEW FILE)
session_start();
require_once 'db.php';
require_once 'auth_helpers.php';
ensureAuthSchema($conn);

$msg = ''; $msgType = ''; $resetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();

    // Always show the same message whether or not the email exists,
    // so people can't use this form to check who has an account.
    $msg = "If that email is registered, a reset link has been generated below.";
    $msgType = 'success';

    if ($customer) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $token, $expires);
        $stmt->execute();

        // In production, email this link with PHPMailer/SMTP instead of showing it.
        $resetLink = "reset_password.php?token=" . urlencode($token);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — LeafLine</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php renderSiteNav(); ?>

<section style="max-width:440px; margin:60px auto; padding:0 20px;">
    <div class="card" style="padding:32px; border-top:4px solid var(--green-bright);">
        <h2 style="color:var(--green-deep); margin-bottom:6px;">🔑 Forgot Password</h2>
        <p style="color:var(--text-mid); margin-bottom:24px;">Enter your account email and we'll help you reset it.</p>

        <?php if ($msg): ?>
            <div style="padding:10px 14px; border-radius:8px; margin-bottom:16px; background:#d8f3dc; color:var(--green-deep);">
                <?= e($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($resetLink): ?>
            <div style="padding:14px; border-radius:8px; margin-bottom:20px; background:var(--green-wash); word-break:break-all;">
                <strong>Dev mode note:</strong> no email server is configured yet, so here's your link directly —
                <br><a href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a>
                <p style="font-size:12px; color:var(--text-mid); margin-top:8px;">
                    In production, wire this up to send by email (e.g. PHPMailer + SMTP) instead of displaying it.
                </p>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required style="margin-bottom:20px;">
            <button type="submit" class="btn btn-gold" style="width:100%;">Send Reset Link</button>
        </form>
        <p style="margin-top:16px; text-align:center;"><a href="login.php">← Back to Login</a></p>
    </div>
</section>
</body>
</html>

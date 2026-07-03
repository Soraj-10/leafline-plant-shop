<?php
// login.php — Login page with "Log In" + "Create New Account" options inside it.
// The navbar only ever links to this ONE page ("login.php") — no separate Register link.
session_start();
require_once 'db.php';
require_once 'auth_helpers.php';
ensureAuthSchema($conn);

$msg = ''; $msgType = ''; $activeTab = 'login';

// ---------- LOG IN ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'login') {
    $activeTab = 'login';
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, email, password_hash FROM customers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();

    if ($customer && $customer['password_hash'] && password_verify($password, $customer['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['customer_id']    = $customer['id'];
        $_SESSION['customer_name']  = $customer['name'];
        $_SESSION['customer_email'] = $customer['email'];
        header("Location: index.php");
        exit;
    } else {
        $msg = 'Incorrect email or password.'; $msgType = 'danger';
    }
}

// ---------- CREATE NEW ACCOUNT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'create_account') {
    $activeTab = 'create_account';
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $msg = 'Name, email and password are required.'; $msgType = 'danger';
    } elseif (strlen($password) < 6) {
        $msg = 'Password must be at least 6 characters.'; $msgType = 'danger';
    } elseif ($password !== $confirm) {
        $msg = 'Passwords do not match.'; $msgType = 'danger';
    } else {
        $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $msg = 'An account with that email already exists.'; $msgType = 'danger';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO customers (name, email, phone, password_hash) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $phone, $hash);
            if ($stmt->execute()) {
                $_SESSION['customer_id']    = $conn->insert_id;
                $_SESSION['customer_name']  = $name;
                $_SESSION['customer_email'] = $email;
                header("Location: index.php");
                exit;
            } else {
                $msg = 'Something went wrong. Please try again.'; $msgType = 'danger';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — LeafLine</title>
<link rel="stylesheet" href="style.css">
<style>
  .auth-hero {
      background: linear-gradient(135deg, var(--green-deep) 0%, var(--green-mid) 55%, var(--green-bright) 100%);
      min-height: calc(100vh - 70px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
      position: relative;
      overflow: hidden;
  }
  .auth-hero::before, .auth-hero::after {
      content: "🌿";
      position: absolute;
      font-size: 120px;
      opacity: 0.08;
  }
  .auth-hero::before { top: -20px; left: -20px; transform: rotate(-15deg); }
  .auth-hero::after { bottom: -30px; right: -20px; transform: rotate(20deg); }

  .auth-card {
      background: #fff;
      border-radius: 20px;
      padding: 36px;
      width: 100%;
      max-width: 440px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.25);
      position: relative;
      z-index: 1;
  }
  .auth-badge {
      display: inline-block;
      background: linear-gradient(90deg, #ffd166, #d4af37);
      color: var(--green-deep);
      font-weight: 700;
      font-size: 12px;
      letter-spacing: 0.5px;
      padding: 6px 14px;
      border-radius: 20px;
      margin-bottom: 14px;
  }

  .tab-toggle {
      display: flex;
      margin-bottom: 26px;
      border-radius: 12px;
      overflow: hidden;
      background: var(--green-wash);
      padding: 4px;
  }
  .tab-toggle button {
      flex: 1;
      padding: 12px;
      border: none;
      border-radius: 9px;
      background: transparent;
      color: var(--green-deep);
      font-weight: 700;
      cursor: pointer;
      transition: all 0.25s ease;
  }
  .tab-toggle button.active {
      background: linear-gradient(90deg, var(--green-bright), var(--green-mid));
      color: #fff;
      box-shadow: 0 4px 14px rgba(64,145,108,0.4);
  }

  .auth-form { display:none; }
  .auth-form.active { display:block; animation: fadeIn 0.3s ease; }
  @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }

  .auth-form label {
      font-weight: 600;
      color: var(--green-deep);
      font-size: 13px;
      display: block;
      margin-bottom: 6px;
  }
  .auth-form .form-control {
      border-radius: 10px;
      padding: 12px 14px;
      border: 1.5px solid var(--green-pale);
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
  }
  .auth-form .form-control:focus {
      border-color: var(--green-bright);
      box-shadow: 0 0 0 4px rgba(64,145,108,0.15);
      outline: none;
  }

  .btn-vivid {
      width: 100%;
      border: none;
      border-radius: 12px;
      padding: 14px;
      font-weight: 700;
      font-size: 15px;
      color: #fff;
      background: linear-gradient(90deg, #40916c, #2d6a4f);
      cursor: pointer;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
      box-shadow: 0 8px 20px rgba(45,106,79,0.35);
  }
  .btn-vivid:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(45,106,79,0.45); }

  .btn-vivid.gold {
      background: linear-gradient(90deg, #f2c14e, #d4af37);
      box-shadow: 0 8px 20px rgba(212,175,55,0.35);
      color: var(--green-deep);
  }
  .btn-vivid.gold:hover { box-shadow: 0 12px 24px rgba(212,175,55,0.45); }
</style>
</head>
<body>
<?php renderSiteNav(); ?>

<div class="auth-hero">
    <div class="auth-card">
        <span class="auth-badge">🌿 LEAFLINE MEMBERS</span>

        <div class="tab-toggle">
            <button type="button" id="tabLoginBtn" class="<?= $activeTab==='login' ? 'active' : '' ?>" onclick="showTab('login')">Log In</button>
            <button type="button" id="tabCreateBtn" class="<?= $activeTab==='create_account' ? 'active' : '' ?>" onclick="showTab('create_account')">Create New Account</button>
        </div>

        <?php if ($msg): ?>
            <div style="padding:12px 14px; border-radius:10px; margin-bottom:18px; background:<?= $msgType==='danger' ? '#ffeaea' : '#d8f3dc' ?>; color:<?= $msgType==='danger' ? '#c0392b' : 'var(--green-deep)' ?>; font-weight:600;">
                <?= e($msg) ?>
            </div>
        <?php endif; ?>

        <!-- LOG IN -->
        <form method="POST" id="loginForm" class="auth-form <?= $activeTab==='login' ? 'active' : '' ?>">
            <input type="hidden" name="form" value="login">
            <h2 style="color:var(--green-deep); margin-bottom:4px;">👋 Welcome Back</h2>
            <p style="color:var(--text-mid); margin-bottom:22px;">Log in using your password.</p>

            <label>Email</label>
            <input type="email" name="email" class="form-control" required style="margin-bottom:16px; width:100%;">

            <label>Password</label>
            <input type="password" name="password" class="form-control" required style="margin-bottom:22px; width:100%;">

            <button type="submit" class="btn-vivid gold">🔓 Log In</button>
            <p style="margin-top:14px; text-align:center; font-size:14px;">
                <a href="forgot_password.php" style="color:var(--green-mid); font-weight:600;">Forgot your password?</a>
            </p>
        </form>

        <!-- CREATE NEW ACCOUNT -->
        <form method="POST" id="createForm" class="auth-form <?= $activeTab==='create_account' ? 'active' : '' ?>">
            <input type="hidden" name="form" value="create_account">
            <h2 style="color:var(--green-deep); margin-bottom:4px;">🌱 Create New Account</h2>
            <p style="color:var(--text-mid); margin-bottom:22px;">Track orders, watering, and rewards in one place.</p>

            <label>Full Name</label>
            <input type="text" name="name" class="form-control" required style="margin-bottom:14px; width:100%;">

            <label>Email</label>
            <input type="email" name="email" class="form-control" required style="margin-bottom:14px; width:100%;">

            <label>Phone (optional)</label>
            <input type="text" name="phone" class="form-control" style="margin-bottom:14px; width:100%;">

            <label>Password</label>
            <input type="password" name="password" class="form-control" required style="margin-bottom:14px; width:100%;">

            <label>Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" required style="margin-bottom:22px; width:100%;">

            <button type="submit" class="btn-vivid">🌿 Create Account</button>
        </form>
    </div>
</div>

<script>
function showTab(tab) {
    document.getElementById('loginForm').classList.toggle('active', tab === 'login');
    document.getElementById('createForm').classList.toggle('active', tab === 'create_account');
    document.getElementById('tabLoginBtn').classList.toggle('active', tab === 'login');
    document.getElementById('tabCreateBtn').classList.toggle('active', tab === 'create_account');
}
</script>
</body>
</html>

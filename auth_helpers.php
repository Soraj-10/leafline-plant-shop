<?php
// ============================================
// auth_helpers.php — Shared login/register helpers
// Include this AFTER db.php. It does NOT modify db.php;
// it just extends the schema itself (adds a password_hash
// column to customers, creates an admins table) the same
// safe "CREATE/ALTER IF NOT EXISTS" way db.php already does.
// ============================================

function ensureAuthSchema($conn) {
    // Add password_hash to customers if missing
    if ($conn->query("SHOW COLUMNS FROM customers LIKE 'password_hash'")->num_rows === 0) {
        $conn->query("ALTER TABLE customers ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL");
    }

    // Separate admins table (kept apart from customers on purpose)
    $conn->query("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(128) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Password reset tokens (customers)
    $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(128) NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used TINYINT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Seed one default admin the first time this ever runs, so you
    // have something to log in with. CHANGE THIS PASSWORD immediately
    // after your first login (there's a form for that on admin_login.php).
    $check = $conn->query("SELECT COUNT(*) c FROM admins")->fetch_assoc();
    if ((int)$check['c'] === 0) {
        $defaultHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)");
        $name = 'Site Admin';
        $email = 'admin@leafline.local';
        $stmt->bind_param("sss", $name, $email, $defaultHash);
        $stmt->execute();
    }
}

// Simple reusable navbar so new pages look native to the site.
// (Purely additive — does not read from or alter any existing file.)
function renderSiteNav($activeIsAuth = false) {
    $adminBadge = (function_exists('isAdmin') && isAdmin())
        ? '<li><a href="dashboard">📊 Dashboard</a></li><li><a href="add_plant?logout=1">🚪 Admin Logout</a></li>'
        : '';
    $custBadge = isset($_SESSION['customer_id'])
        ? '<li><a href="loyalty.php">🏆 Rewards</a></li><li><a href="logout.php">🚪 Logout</a></li>'
        : '<li><a href="login.php">👤 Login</a></li>';
    echo <<<HTML
    <nav class="navbar">
        <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
        <ul class="navbar-nav">
            <li><a href="/plantshop/">Home</a></li>
            <li><a href="shop">Shop</a></li>
            <li><a href="care">Care</a></li>
            <li><a href="search.php">🔍 Search</a></li>
            {$custBadge}
            {$adminBadge}
        </ul>
    </nav>
HTML;
}

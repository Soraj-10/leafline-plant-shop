<?php
// ============================================
// db.php — Database Connection
// Include this file at the top of every page
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Default XAMPP username
define('DB_PASS', '');           // Default XAMPP has no password
define('DB_NAME', 'plant_shop');
define('ADMIN_PIN', '111');      // Change this to your desired PIN

// Create connection using mysqli
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("
        <div style='font-family:sans-serif; padding:40px; color:#c0392b; background:#ffeaea; border-radius:8px; margin:20px;'>
            <h2>⚠️ Database Connection Failed</h2>
            <p><strong>Error:</strong> " . $conn->connect_error . "</p>
            <p>Make sure XAMPP MySQL is running and the database 'plant_shop' exists.</p>
            <p>Run the <code>database.sql</code> file in phpMyAdmin first.</p>
        </div>
    ");
}

// Set charset to support special characters
$conn->set_charset("utf8mb4");

// ============================================
// Core table: plants (must exist before anything else)
// ============================================
$conn->query("CREATE TABLE IF NOT EXISTS plants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    image_url VARCHAR(500),
    type VARCHAR(20) NOT NULL DEFAULT 'regular',
    sunlight VARCHAR(50) NOT NULL DEFAULT 'Medium',
    watering_days INT NOT NULL DEFAULT 7,
    stock INT NOT NULL DEFAULT 0,
    discount_percent TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ============================================
// Database schema updates
// ============================================
if ($conn->query("SHOW TABLES LIKE 'plants'")->num_rows > 0) {
    if ($conn->query("SHOW COLUMNS FROM plants LIKE 'discount_percent'")->num_rows === 0) {
        $conn->query("ALTER TABLE plants ADD COLUMN discount_percent TINYINT NOT NULL DEFAULT 0");
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plant_id INT NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(128) NOT NULL,
    rating TINYINT NOT NULL,
    comment TEXT,
    approved TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

if ($conn->query("SHOW TABLES LIKE 'reviews'")->num_rows > 0) {
    if ($conn->query("SHOW COLUMNS FROM reviews LIKE 'customer_name'")->num_rows === 0) {
        $conn->query("ALTER TABLE reviews ADD COLUMN customer_name VARCHAR(100) NOT NULL AFTER plant_id");
    }
    if ($conn->query("SHOW COLUMNS FROM reviews LIKE 'customer_email'")->num_rows === 0) {
        $conn->query("ALTER TABLE reviews ADD COLUMN customer_email VARCHAR(128) NOT NULL AFTER customer_name");
    }
    if ($conn->query("SHOW COLUMNS FROM reviews LIKE 'rating'")->num_rows === 0) {
        $conn->query("ALTER TABLE reviews ADD COLUMN rating TINYINT NOT NULL DEFAULT 5 AFTER customer_email");
    }
    if ($conn->query("SHOW COLUMNS FROM reviews LIKE 'comment'")->num_rows === 0) {
        $conn->query("ALTER TABLE reviews ADD COLUMN comment TEXT AFTER rating");
    }
    if ($conn->query("SHOW COLUMNS FROM reviews LIKE 'approved'")->num_rows === 0) {
        $conn->query("ALTER TABLE reviews ADD COLUMN approved TINYINT NOT NULL DEFAULT 0");
    }
    if ($conn->query("SHOW COLUMNS FROM reviews LIKE 'created_at'")->num_rows === 0) {
        $conn->query("ALTER TABLE reviews ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plant_id INT NOT NULL,
    session_key VARCHAR(64) NOT NULL,
    customer_email VARCHAR(128) DEFAULT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

if ($conn->query("SHOW TABLES LIKE 'discount_codes'")->num_rows > 0) {
    if ($conn->query("SHOW COLUMNS FROM discount_codes LIKE 'discount_percent'")->num_rows === 0) {
        $conn->query("ALTER TABLE discount_codes ADD COLUMN discount_percent TINYINT NOT NULL DEFAULT 0");
    }
    if ($conn->query("SHOW COLUMNS FROM discount_codes LIKE 'valid_until'")->num_rows === 0) {
        $conn->query("ALTER TABLE discount_codes ADD COLUMN valid_until DATE NOT NULL");
    }
    if ($conn->query("SHOW COLUMNS FROM discount_codes LIKE 'created_at'")->num_rows === 0) {
        $conn->query("ALTER TABLE discount_codes ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS discount_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    discount_percent TINYINT NOT NULL DEFAULT 0,
    valid_until DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(128) NOT NULL UNIQUE,
    phone VARCHAR(32) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$conn->query("CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    plant_id INT NOT NULL,
    quantity INT NOT NULL,
    price_at_purchase DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

if ($conn->query("SHOW TABLES LIKE 'customers'")->num_rows > 0) {
    if ($conn->query("SHOW COLUMNS FROM customers LIKE 'created_at'")->num_rows === 0) {
        $conn->query("ALTER TABLE customers ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
}
if ($conn->query("SHOW TABLES LIKE 'orders'")->num_rows > 0) {
    if ($conn->query("SHOW COLUMNS FROM orders LIKE 'order_date'")->num_rows === 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
    if ($conn->query("SHOW COLUMNS FROM orders LIKE 'is_gift'")->num_rows === 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN is_gift TINYINT NOT NULL DEFAULT 0");
    }
    if ($conn->query("SHOW COLUMNS FROM orders LIKE 'gift_recipient_name'")->num_rows === 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN gift_recipient_name VARCHAR(100)");
    }
    if ($conn->query("SHOW COLUMNS FROM orders LIKE 'gift_recipient_email'")->num_rows === 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN gift_recipient_email VARCHAR(128)");
    }
    if ($conn->query("SHOW COLUMNS FROM orders LIKE 'gift_recipient_address'")->num_rows === 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN gift_recipient_address TEXT");
    }
    if ($conn->query("SHOW COLUMNS FROM orders LIKE 'gift_note'")->num_rows === 0) {
        $conn->query("ALTER TABLE orders ADD COLUMN gift_note TEXT");
    }
}

// ============================================
// Create gift_orders table for gift feature
// ============================================
$conn->query("CREATE TABLE IF NOT EXISTS gift_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_name VARCHAR(100) NOT NULL,
    receiver_name VARCHAR(100) NOT NULL,
    receiver_address TEXT NOT NULL,
    message TEXT,
    item_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ============================================
// Create care_logs table for plant care tracking
// ============================================
$conn->query("CREATE TABLE IF NOT EXISTS care_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    plant_id INT NOT NULL,
    last_watered DATE NOT NULL,
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ============================================
// Helper function: Check admin PIN from session
// ============================================
function isAdmin() {
    return isset($_SESSION['admin_verified']) && $_SESSION['admin_verified'] === true;
}

// ============================================
// Helper function: Safe output (prevent XSS)
// ============================================
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ============================================
// Helper function: Format price
// ============================================
function formatPrice($price) {
    return '$' . number_format($price, 2);
}

function discountedPrice($price, $discountPercent) {
    $discountPercent = max(0, min(100, (int)$discountPercent));
    return $discountPercent > 0 ? $price * (1 - $discountPercent / 100) : $price;
}

// ============================================
// Helper function: Days until next watering
// Returns: negative = overdue, 0 = today, positive = days left
// ============================================
function getDaysUntilWater($lastWatered, $wateringDays) {
    $last = new DateTime($lastWatered);
    $next = clone $last;
    $next->modify("+{$wateringDays} days");
    $today = new DateTime('today');
    $diff = $today->diff($next);
    $days = (int)$diff->days;
    return $next >= $today ? $days : -$days;
}
?>

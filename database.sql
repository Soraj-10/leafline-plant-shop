-- ============================================
-- LeafLine Plant Shop — Full Database Schema
-- Import this once in phpMyAdmin (or via CLI) to
-- set up everything the site needs in one go.
-- Safe to run even though db.php can also create
-- these tables automatically on first page load.
-- ============================================

CREATE DATABASE IF NOT EXISTS plant_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE plant_shop;

-- ------------------------------------------------
-- plants
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS plants (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------
-- customers
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(128) NOT NULL UNIQUE,
    phone VARCHAR(32) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------
-- orders
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_gift TINYINT NOT NULL DEFAULT 0,
    gift_recipient_name VARCHAR(100) DEFAULT NULL,
    gift_recipient_email VARCHAR(128) DEFAULT NULL,
    gift_recipient_address TEXT,
    gift_note TEXT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------
-- order_items
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    plant_id INT NOT NULL,
    quantity INT NOT NULL,
    price_at_purchase DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------
-- reviews
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plant_id INT NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(128) NOT NULL,
    rating TINYINT NOT NULL,
    comment TEXT,
    approved TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------
-- wishlists
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plant_id INT NOT NULL,
    session_key VARCHAR(64) NOT NULL,
    customer_email VARCHAR(128) DEFAULT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------
-- discount_codes
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS discount_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    discount_percent TINYINT NOT NULL DEFAULT 0,
    valid_until DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------
-- gift_orders  (standalone quick-gift form on giftshop.php)
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS gift_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_name VARCHAR(100) NOT NULL,
    receiver_name VARCHAR(100) NOT NULL,
    receiver_address TEXT NOT NULL,
    message TEXT,
    item_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------
-- care_logs  (watering tracker)
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS care_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    plant_id INT NOT NULL,
    last_watered DATE NOT NULL,
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (plant_id) REFERENCES plants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- STARTER SAMPLE DATA (optional but recommended)
-- Only runs if the plants table is currently empty,
-- so it's safe to import this file more than once.
-- ============================================
INSERT INTO plants (name, description, price, image_url, type, sunlight, watering_days, stock, discount_percent)
SELECT * FROM (SELECT
    'Snake Plant' AS name,
    'A hardy, low-maintenance plant that purifies air and thrives in low light.' AS description,
    25.00 AS price,
    'https://images.unsplash.com/photo-1593691509543-c55fb32d8de5?w=400&q=80' AS image_url,
    'regular' AS type, 'Low' AS sunlight, 14 AS watering_days, 50 AS stock, 0 AS discount_percent
UNION ALL SELECT 'Monstera Deliciosa', 'Tropical plant with split leaves, perfect for indoor spaces.', 45.00,
    'https://images.unsplash.com/photo-1545241047-6083a3684587?w=400&q=80', 'regular', 'Medium', 7, 30, 10
UNION ALL SELECT 'Fiddle Leaf Fig', 'Elegant plant with large leaves, a statement piece for any room.', 60.00,
    'https://images.unsplash.com/photo-1586093148909-4e1166c8c4b8?w=400&q=80', 'regular', 'High', 7, 20, 0
UNION ALL SELECT 'Pothos', 'Trailing vine that is almost impossible to kill, great for beginners.', 15.00,
    'https://images.unsplash.com/photo-1614594895304-fe7116a58045?w=400&q=80', 'regular', 'Low', 10, 100, 0
UNION ALL SELECT 'ZZ Plant', 'Drought-tolerant plant that stores water in its roots.', 30.00,
    'https://images.unsplash.com/photo-1632207691143-643e2a9a9361?w=400&q=80', 'regular', 'Low', 21, 40, 5
UNION ALL SELECT 'Rubber Plant', 'Glossy leaves and air-purifying qualities make this a favorite.', 35.00,
    'https://images.unsplash.com/photo-1602923668104-8f9e03d31e18?w=400&q=80', 'regular', 'Medium', 10, 25, 0
UNION ALL SELECT 'Bird of Paradise', 'A striking exotic plant with large, banana-like leaves.', 85.00,
    'https://images.unsplash.com/photo-1596547609652-9cf5d8d76921?w=400&q=80', 'exotic', 'High', 7, 15, 0
UNION ALL SELECT 'Venus Flytrap', 'A rare carnivorous plant that snaps shut to catch insects.', 22.00,
    'https://images.unsplash.com/photo-1616690248363-6e5c3ca23f8a?w=400&q=80', 'exotic', 'High', 5, 20, 0
UNION ALL SELECT 'Blue Star Fern', 'An exotic fern with silvery-blue fronds, loves humidity.', 38.00,
    'https://images.unsplash.com/photo-1614594975525-e45190c55d0b?w=400&q=80', 'exotic', 'Medium', 6, 18, 15
UNION ALL SELECT 'Bonsai Tree', 'A meticulously shaped miniature tree, symbolizing patience.', 95.00,
    'https://images.unsplash.com/photo-1611048267451-e6ed903d4a38?w=400&q=80', 'exotic', 'High', 4, 10, 0
) AS seed_data
WHERE NOT EXISTS (SELECT 1 FROM plants LIMIT 1);

-- A sample discount code so you can test the coupon field on checkout
INSERT INTO discount_codes (code, discount_percent, valid_until)
SELECT * FROM (SELECT 'WELCOME10' AS code, 10 AS discount_percent, '2027-12-31' AS valid_until) AS seed_code
WHERE NOT EXISTS (SELECT 1 FROM discount_codes WHERE code = 'WELCOME10');

<?php
// shop.php — Plant Listing + Cart + Buy System
session_start();
require_once 'db.php';

$msg = '';
$msgType = '';
$couponCode = trim($_POST['discount_code'] ?? $_SESSION['discount_code'] ?? '');
$sessionKey = session_id();

// ============================================
// Handle Wishlist + Review + Checkout actions
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['wishlist_action'])) {
        $plantId = (int)$_POST['wishlist_action'];
        $wishlistEmail = trim($_POST['customer_email'] ?? $_SESSION['customer_email'] ?? '');
        if ($plantId > 0) {
            $check = $conn->prepare("SELECT id FROM wishlists WHERE plant_id = ? AND (session_key = ? OR customer_email = ?) LIMIT 1");
            $check->bind_param("iss", $plantId, $sessionKey, $wishlistEmail);
            $check->execute();
            $wishRow = $check->get_result()->fetch_assoc();

            if ($wishRow) {
                $del = $conn->prepare("DELETE FROM wishlists WHERE id = ? AND (session_key = ? OR customer_email = ?)");
                $del->bind_param("iss", $wishRow['id'], $sessionKey, $wishlistEmail);
                $del->execute();
                $msg = '💔 Removed from your wishlist.';
                $msgType = 'info';
            } else {
                $ins = $conn->prepare("INSERT INTO wishlists (plant_id, session_key, customer_email) VALUES (?, ?, ?)");
                $wishlistEmail = $wishlistEmail !== '' ? $wishlistEmail : null;
                $ins->bind_param("iss", $plantId, $sessionKey, $wishlistEmail);
                $ins->execute();
                $msg = '💚 Added to wishlist!';
                $msgType = 'success';
            }
        }
    } elseif (isset($_POST['submit_review'])) {
        $reviewName  = trim($_POST['review_name'] ?? '');
        $reviewEmail = trim($_POST['review_email'] ?? '');
        $reviewPlant = (int)($_POST['review_plant'] ?? 0);
        $reviewRating = min(5, max(1, (int)($_POST['review_rating'] ?? 0)));
        $reviewComment = trim($_POST['review_comment'] ?? '');

        if (empty($reviewName) || empty($reviewEmail) || $reviewPlant < 1 || $reviewRating < 1) {
            $msg = 'Please provide your name, email, plant, and rating to submit a review.';
            $msgType = 'danger';
        } else {
            $checkPurchase = $conn->prepare(
                "SELECT COUNT(*) AS purchased FROM order_items oi
                 JOIN orders o ON o.id = oi.order_id
                 JOIN customers c ON c.id = o.customer_id
                 WHERE c.email = ? AND oi.plant_id = ?"
            );
            $checkPurchase->bind_param("si", $reviewEmail, $reviewPlant);
            $checkPurchase->execute();
            $purchaseResult = $checkPurchase->get_result()->fetch_assoc();

            if (empty($purchaseResult['purchased'])) {
                $msg = 'You can only review plants you have purchased with this email.';
                $msgType = 'danger';
            } else {
                $ins = $conn->prepare("INSERT INTO reviews (plant_id, customer_name, customer_email, rating, comment) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param("issis", $reviewPlant, $reviewName, $reviewEmail, $reviewRating, $reviewComment);
                if ($ins->execute()) {
                    $_SESSION['customer_email'] = $reviewEmail;
                    $_SESSION['customer_name'] = $reviewName;
                    $msg = '✅ Thank you! Your review is submitted and will appear after approval.';
                    $msgType = 'success';
                } else {
                    $msg = '❌ Unable to save review. Please try again.';
                    $msgType = 'danger';
                }
            }
        }
    } elseif (isset($_POST['checkout'])) {
        $custName  = trim($_POST['customer_name'] ?? '');
        $custEmail = trim($_POST['customer_email'] ?? '');
        $custPhone = trim($_POST['customer_phone'] ?? '');
        $cart      = $_POST['cart'] ?? [];
        $couponPercent = 0;
        $isGift = isset($_POST['is_gift']) && $_POST['is_gift'] == '1';
        $giftRecipientName = trim($_POST['gift_recipient_name'] ?? '');
        $giftRecipientEmail = trim($_POST['gift_recipient_email'] ?? '');
        $giftRecipientAddress = trim($_POST['gift_recipient_address'] ?? '');
        $giftNote = trim($_POST['gift_note'] ?? '');
        $giftValidationFailed = false;

        if (empty($custName) || empty($custEmail)) {
            $msg = 'Please enter your name and email.';
            $msgType = 'danger';
        } elseif (empty($cart)) {
            $msg = 'Your cart is empty!';
            $msgType = 'warning';
        } elseif ($isGift && (empty($giftRecipientName) || empty($giftRecipientEmail) || empty($giftRecipientAddress))) {
            $msg = 'Please fill in all gift recipient details under Gift Options below.';
            $msgType = 'danger';
            $giftValidationFailed = true;
        } else {
            if ($couponCode !== '') {
                $codeStmt = $conn->prepare("SELECT discount_percent, valid_until FROM discount_codes WHERE code = ? LIMIT 1");
                $codeStmt->bind_param('s', $couponCode);
                $codeStmt->execute();
                $codeRow = $codeStmt->get_result()->fetch_assoc();

                if (!$codeRow) {
                    $msg = 'Coupon code not found. Please check and try again.';
                    $msgType = 'danger';
                } elseif ($codeRow['valid_until'] < date('Y-m-d')) {
                    $msg = 'This coupon has expired.';
                    $msgType = 'danger';
                } else {
                    $couponPercent = (int)$codeRow['discount_percent'];
                }
            }

            $validCart = [];
            foreach ($cart as $plantId => $qty) {
                $qty = (int)$qty;
                if ($qty > 0) $validCart[(int)$plantId] = $qty;
            }

            if (empty($validCart)) {
                $msg = 'Please select at least 1 plant and quantity.';
                $msgType = 'warning';
            } elseif ($msgType === 'danger') {
                // coupon validation failed, do not proceed
            } else {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
                    $stmt->bind_param("s", $custEmail);
                    $stmt->execute();
                    $custRow = $stmt->get_result()->fetch_assoc();

                    if ($custRow) {
                        $customerId = $custRow['id'];
                        $upd = $conn->prepare("UPDATE customers SET name=?, phone=? WHERE id=?");
                        $upd->bind_param("ssi", $custName, $custPhone, $customerId);
                        $upd->execute();
                    } else {
                        $ins = $conn->prepare("INSERT INTO customers (name, email, phone) VALUES (?,?,?)");
                        $ins->bind_param("sss", $custName, $custEmail, $custPhone);
                        $ins->execute();
                        $customerId = $conn->insert_id;
                    }

                    $total = 0;
                    $orderItems = [];
                    foreach ($validCart as $plantId => $qty) {
                        $pStmt = $conn->prepare("SELECT * FROM plants WHERE id=? FOR UPDATE");
                        $pStmt->bind_param("i", $plantId);
                        $pStmt->execute();
                        $plant = $pStmt->get_result()->fetch_assoc();

                        if (!$plant) throw new Exception("Plant ID $plantId not found.");
                        if ($plant['stock'] < $qty) {
                            throw new Exception("Sorry, only {$plant['stock']} units of '{$plant['name']}' available.");
                        }

                        $priceAtPurchase = discountedPrice($plant['price'], $plant['discount_percent']);
                        $lineTotal = $priceAtPurchase * $qty;
                        $total += $lineTotal;
                        $orderItems[] = [
                            'plant_id' => $plantId,
                            'qty'      => $qty,
                            'price'    => $priceAtPurchase,
                            'name'     => $plant['name']
                        ];
                    }

                    $discountDescription = '';
                    if (!empty($couponPercent)) {
                        $discountAmount = $total * ($couponPercent / 100);
                        $total = max(0, $total - $discountAmount);
                        $discountDescription = " (Coupon {$couponPercent}% applied)";
                    }

                    $oStmt = $conn->prepare("INSERT INTO orders (customer_id, total_price, is_gift, gift_recipient_name, gift_recipient_email, gift_recipient_address, gift_note) VALUES (?,?,?,?,?,?,?)");
                    $giftFlag = $isGift ? 1 : 0;
                    $oStmt->bind_param("idissss", $customerId, $total, $giftFlag, $giftRecipientName, $giftRecipientEmail, $giftRecipientAddress, $giftNote);
                    $oStmt->execute();
                    $orderId = $conn->insert_id;

                    foreach ($orderItems as $item) {
                        $iStmt = $conn->prepare("INSERT INTO order_items (order_id, plant_id, quantity, price_at_purchase) VALUES (?,?,?,?)");
                        $iStmt->bind_param("iiid", $orderId, $item['plant_id'], $item['qty'], $item['price']);
                        $iStmt->execute();

                        $sStmt = $conn->prepare("UPDATE plants SET stock = stock - ? WHERE id = ?");
                        $sStmt->bind_param("ii", $item['qty'], $item['plant_id']);
                        $sStmt->execute();
                    }

                    $conn->commit();
                    if (!empty($couponCode)) {
                        $_SESSION['discount_code'] = $couponCode;
                        $_SESSION['discount_percent'] = $couponPercent;
                    } else {
                        unset($_SESSION['discount_code'], $_SESSION['discount_percent']);
                    }
                    $_SESSION['customer_email'] = $custEmail;
                    $_SESSION['customer_name'] = $custName;
                    $msg = "✅ Order #$orderId placed successfully! Thank you, $custName! Total: " . formatPrice($total);
                    if ($couponPercent > 0) {
                        $msg .= " (Coupon {$couponPercent}% applied)";
                    }
                    $msgType = 'success';
                } catch (Exception $e) {
                    $conn->rollback();
                    $msg = '❌ ' . $e->getMessage();
                    $msgType = 'danger';
                }
            }
        }
    }
}

$quickAddPlantId = isset($_GET['add']) ? max(0, (int)$_GET['add']) : 0;
$quickAddQty = isset($_GET['qty']) ? max(1, min(10, (int)$_GET['qty'])) : 1;
$quickAddName = '';
if ($quickAddPlantId > 0) {
    $preStmt = $conn->prepare("SELECT name, stock FROM plants WHERE id = ? LIMIT 1");
    $preStmt->bind_param('i', $quickAddPlantId);
    $preStmt->execute();
    $prePlant = $preStmt->get_result()->fetch_assoc();
    if ($prePlant) {
        $quickAddName = $prePlant['name'];
        if ($prePlant['stock'] > 0) {
            $msg = "✅ '$quickAddName' is ready in your cart. Adjust quantity as needed.";
            $msgType = 'success';
        } else {
            $msg = "⚠️ '$quickAddName' is currently out of stock.";
            $msgType = 'warning';
        }
    }
}

// ============================================
// Load Plants (with search/filter)
// ============================================
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$sql = "SELECT * FROM plants WHERE stock > 0";
$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND name LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}
if ($filter === 'regular' || $filter === 'exotic') {
    $sql .= " AND type = ?";
    $params[] = $filter;
    $types .= 's';
}
$sql .= " ORDER BY name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$plants = $result->fetch_all(MYSQLI_ASSOC);

$reviewsSummary = [];
$reviewRows = $conn->query("SELECT plant_id, COUNT(*) AS review_count, AVG(rating) AS avg_rating FROM reviews WHERE approved = 1 GROUP BY plant_id");
while ($row = $reviewRows->fetch_assoc()) {
    $reviewsSummary[$row['plant_id']] = $row;
}

$wishlistPlants = [];
$wishlistEmail = trim($_SESSION['customer_email'] ?? '');
if ($wishlistEmail !== '') {
    $wishStmt = $conn->prepare("SELECT plant_id FROM wishlists WHERE session_key = ? OR customer_email = ?");
    $wishStmt->bind_param("ss", $sessionKey, $wishlistEmail);
} else {
    $wishStmt = $conn->prepare("SELECT plant_id FROM wishlists WHERE session_key = ?");
    $wishStmt->bind_param("s", $sessionKey);
}
$wishStmt->execute();
$wishResult = $wishStmt->get_result();
while ($row = $wishResult->fetch_assoc()) {
    $wishlistPlants[] = $row['plant_id'];
}

$wishlistItems = [];
if (!empty($wishlistPlants)) {
    $placeholders = implode(',', array_fill(0, count($wishlistPlants), '?'));
    $types = str_repeat('i', count($wishlistPlants));
    $stmt2 = $conn->prepare("SELECT id, name FROM plants WHERE id IN ($placeholders)");
    $stmt2->bind_param($types, ...$wishlistPlants);
    $stmt2->execute();
    $win = $stmt2->get_result();
    while ($row = $win->fetch_assoc()) {
        $wishlistItems[] = $row['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop — LeafLine Plant Shop</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
    <ul class="navbar-nav">
        <li><a href="/plantshop/">Home</a></li>
        <li><a href="shop" class="active">Shop</a></li>
        <li><a href="wishlist">Favorites</a></li>
        <li><a href="exotic">Exotic</a></li>
        <li><a href="care">Care</a></li>
        <li><a href="reviews">Reviews</a></li>
        <li><a href="orders">Orders</a></li>
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


<div class="page-header" style="margin-bottom:0;">
    <div class="container">
        <h1>🛒 Plant Shop</h1>
        <p>Add multiple plants to your cart and checkout in one go.</p>
    </div>
</div>

<div class="page-wrapper">

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
    <?php endif; ?>

    <!-- SEARCH BAR -->
    <form method="GET" action="shop" class="search-bar">
        <input type="text" name="search" placeholder="🔍 Search plants..."
               class="search-input" value="<?= e($search) ?>">
        <select name="filter" class="filter-select" onchange="this.form.submit()">
            <option value="all"    <?= $filter==='all'     ? 'selected' : '' ?>>All Plants</option>
            <option value="regular" <?= $filter==='regular' ? 'selected' : '' ?>>Regular</option>
            <option value="exotic"  <?= $filter==='exotic'  ? 'selected' : '' ?>>Exotic</option>
        </select>
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search || $filter !== 'all'): ?>
        <a href="shop" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

<div class="shop-tabs">
        <button type="button" class="tab-btn active" data-tab="shop-tab">Shop</button>
        <button type="button" class="tab-btn" data-tab="reviews-tab">Reviews</button>
    </div>

    <div id="shop-tab" class="tab-pane active">
    <form method="POST" action="shop">
    <div class="shop-layout">

        <!-- PLANT GRID -->
        <div>
            <?php if (count($plants) === 0): ?>
            <div class="empty-state">
                <div class="empty-icon">🌱</div>
                <p>No plants found. Try a different search.</p>
            </div>
            <?php else: ?>
            <div class="plant-grid">
                <?php foreach ($plants as $plant):
                    $hasDiscount = !empty($plant['discount_percent']);
                    $currentPrice = $hasDiscount ? discountedPrice($plant['price'], $plant['discount_percent']) : $plant['price'];
                    $reviewCount = $reviewsSummary[$plant['id']]['review_count'] ?? 0;
                    $avgRating = round($reviewsSummary[$plant['id']]['avg_rating'] ?? 0, 1);
                    $inWishlist = in_array($plant['id'], $wishlistPlants, true);
                ?>
                <div class="plant-card">
                    <div class="plant-card-image-wrap">
                        <img src="<?= e($plant['image_url']) ?>" alt="<?= e($plant['name']) ?>"
                             class="plant-card-image"
                             onerror="this.style.display='none'">
                        <span class="plant-badge <?= $plant['type'] ?>"><?= ucfirst($plant['type']) ?></span>
                        <?php if ($hasDiscount): ?>
                        <span class="plant-discount-badge">Save <?= $plant['discount_percent'] ?>%</span>
                        <?php endif; ?>
                    </div>
                    <div class="plant-card-body">
                        <div class="plant-card-name"><?= e($plant['name']) ?></div>
                        <div class="plant-card-desc"><?= e(mb_substr($plant['description'], 0, 90)) ?><?= mb_strlen($plant['description']) > 90 ? '...' : '' ?></div>
                        <div class="plant-care-info">
                            <span class="care-tag">☀️ <?= e($plant['sunlight']) ?></span>
                            <span class="care-tag">💧 <?= e($plant['watering_days']) ?>d</span>
                        </div>
                    </div>
                    <div class="plant-card-footer">
                        <div>
                            <div class="plant-price"><?= formatPrice($currentPrice) ?>
                                <?php if ($hasDiscount): ?><span class="price-faded"><?= formatPrice($plant['price']) ?></span><?php endif; ?>
                            </div>
                            <?php if ($reviewCount): ?>
                            <div class="rating-row">
                                <?= str_repeat('★', (int)round($avgRating)) ?><?= str_repeat('☆', 5 - (int)round($avgRating)) ?>
                                <span class="rating-text"><?= $avgRating ?>/5 · <?= $reviewCount ?> reviews</span>
                            </div>
                            <?php endif; ?>
                            <div class="stock-label <?= $plant['stock'] <= 5 ? 'stock-low' : '' ?>">
                                <?= $plant['stock'] <= 5 ? "⚠️ Only {$plant['stock']} left" : "In stock: {$plant['stock']}" ?>
                            </div>
                        </div>
                                <div style="display:flex; flex-direction:column; gap:10px; align-items:flex-end;">
                            <input type="number" name="cart[<?= $plant['id'] ?>]"
                                   class="qty-input" data-id="<?= $plant['id'] ?>" data-name="<?= e($plant['name']) ?>" data-price="<?= number_format($currentPrice, 2, '.', '') ?>"
                                   min="0" max="<?= $plant['stock'] ?>"
                                   value="<?= $quickAddPlantId === $plant['id'] ? $quickAddQty : 0 ?>" placeholder="0"
                                   onchange="updateCart(<?= $plant['id'] ?>, '<?= addslashes($plant['name']) ?>', <?= number_format($currentPrice, 2, '.', '') ?>, this.value)">
                            <input type="hidden" name="customer_email" value="<?= e($_SESSION['customer_email'] ?? '') ?>">
                            <button type="submit" name="wishlist_action" value="<?= $plant['id'] ?>" class="btn btn-secondary btn-sm">
                                <?= $inWishlist ? '💖 Saved' : '💚 Wishlist' ?>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- CART SIDEBAR -->
        <div class="cart-sidebar">
            <h3>🛒 Your Cart</h3>
            <?php if (!empty($wishlistItems)): ?>
            <div class="alert alert-info" style="margin-bottom:18px;">
                <strong>💚 Wishlist</strong><br>
                Saved: <?= count($wishlistItems) ?> plant<?= count($wishlistItems) === 1 ? '' : 's' ?>
                <div style="margin-top:8px; font-size:13px; line-height:1.4; color:var(--text-dark);">
                    <?= e(implode(', ', $wishlistItems)) ?>
                </div>
            </div>
            <?php endif; ?>
            <div id="cart-items">
                <div class="empty-state" style="padding:20px;">
                    <div class="empty-icon">🌿</div>
                    <p style="font-size:13px;">Set quantities above to add plants</p>
                </div>
            </div>
            <div class="cart-total" id="cart-total" style="display:none;">
                <span class="cart-total-label">Total</span>
                <span class="cart-total-value" id="cart-total-value">$0.00</span>
            </div>

            <div class="divider"></div>

            <!-- Customer Info -->
            <div style="margin-bottom:16px;">
                <h3 style="font-size:1rem; margin-bottom:14px; color:var(--green-deep);">Your Details</h3>
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="customer_name" class="form-control" placeholder="Aisha Rahman" required value="<?= e($_SESSION['customer_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="customer_email" class="form-control" placeholder="you@email.com" required value="<?= e($_SESSION['customer_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="customer_phone" class="form-control" placeholder="017xx-xxxxxx">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Discount Coupon</label>
                <input type="text" name="discount_code" class="form-control" placeholder="Enter coupon code"
                       value="<?= e($couponCode) ?>">
                <small style="color:var(--text-mid);">Apply a valid discount code at checkout.</small>
            </div>

            <!-- Gift Options -->
            <div style="margin-bottom:16px;">
                <h3 style="font-size:1rem; margin-bottom:14px; color:var(--green-deep);">Gift Options</h3>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="is_gift" value="1" id="is-gift-checkbox" onchange="toggleGiftFields()" <?= isset($isGift) && $isGift ? 'checked' : '' ?>>
                        <span>This is a gift</span>
                    </label>
                    <small style="display:block; color:var(--text-mid); margin-top:6px;">Check the box to reveal gift recipient fields.</small>
                </div>
                <div id="gift-fields" style="display:none;<?= (isset($giftValidationFailed) && $giftValidationFailed) ? ' border:1px solid #e07a5f; padding:16px; border-radius:12px;' : '' ?>">
                    <?php if (isset($giftValidationFailed) && $giftValidationFailed): ?>
                    <div style="color:#e07a5f; font-size:13px; margin-bottom:10px;">
                        Please fill all recipient details: name, email, and address.
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label class="form-label">Recipient Name *</label>
                        <input type="text" name="gift_recipient_name" class="form-control" placeholder="Recipient's full name" value="<?= e($giftRecipientName ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Recipient Email *</label>
                        <input type="email" name="gift_recipient_email" class="form-control" placeholder="recipient@email.com" value="<?= e($giftRecipientEmail ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Recipient Address *</label>
                        <textarea name="gift_recipient_address" class="form-control" rows="3" placeholder="Full delivery address"><?= e($giftRecipientAddress ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gift Note</label>
                        <textarea name="gift_note" class="form-control" rows="3" placeholder="Optional personal message"><?= e($giftNote ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <button type="submit" name="checkout" class="btn btn-primary btn-lg" style="width:100%;">
                ✅ Place Order
            </button>
        </div>
                </div>
        </div>
        </form>
    </div>

    <div id="reviews-tab" class="tab-pane">
        <div class="review-panel">
        <h2>📝 Share a Review</h2>
        <p style="color:var(--text-mid); margin-bottom:20px;">Tell other plant lovers what you enjoyed — reviews are approved by admin before publishing.</p>
        <form method="POST" class="form-card">
            <input type="hidden" name="submit_review" value="1">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Choose a Plant</label>
                    <select name="review_plant" class="form-control" required>
                        <option value="">Select a plant</option>
                        <?php foreach ($plants as $plant): ?>
                        <option value="<?= $plant['id'] ?>"><?= e($plant['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Rating</label>
                    <select name="review_rating" class="form-control" required>
                        <option value="">Rate</option>
                        <?php for ($r = 5; $r >= 1; $r--): ?>
                        <option value="<?= $r ?>"><?= $r ?> star<?= $r === 1 ? '' : 's' ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Your Name</label>
                    <input type="text" name="review_name" class="form-control" required value="<?= e($_SESSION['customer_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Your Email</label>
                    <input type="email" name="review_email" class="form-control" required value="<?= e($_SESSION['customer_email'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Review</label>
                <textarea name="review_comment" class="form-control" rows="4" placeholder="What did you love about the plant?"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-lg">Submit Review</button>
        </form>
    </div>
</div>

<footer>
    <p>🌿 <strong>LeafLine Plant Shop</strong></p>
</footer>

<script>
// Cart state
const cartState = {};

function updateCart(id, name, price, qty) {
    qty = parseInt(qty) || 0;
    if (qty > 0) {
        cartState[id] = { name, price, qty };
    } else {
        delete cartState[id];
    }
    renderCart();
}

function renderCart() {
    const container = document.getElementById('cart-items');
    const totalDiv  = document.getElementById('cart-total');
    const totalVal  = document.getElementById('cart-total-value');
    const ids = Object.keys(cartState);

    if (ids.length === 0) {
        container.innerHTML = `<div class="empty-state" style="padding:20px;">
            <div class="empty-icon">🌿</div>
            <p style="font-size:13px;">Set quantities above to add plants</p>
        </div>`;
        totalDiv.style.display = 'none';
        return;
    }

    let html = '';
    let total = 0;
    for (const id of ids) {
        const item = cartState[id];
        const lineTotal = item.price * item.qty;
        total += lineTotal;
        html += `<div class="cart-item">
            <div>
                <div class="cart-item-name">${item.name}</div>
                <div style="font-size:12px; color:var(--text-light);">x${item.qty} @ $${item.price.toFixed(2)}</div>
            </div>
            <div class="cart-item-price">$${lineTotal.toFixed(2)}</div>
        </div>`;
    }

    container.innerHTML = html;
    totalDiv.style.display = 'flex';
    totalVal.textContent = '$' + total.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.qty-input').forEach(function(input) {
        const qty = parseInt(input.value, 10) || 0;
        if (qty > 0) {
            const id = parseInt(input.dataset.id, 10);
            const name = input.dataset.name || '';
            const price = parseFloat(input.dataset.price) || 0;
            updateCart(id, name, price, qty);
        }
    });
    renderCart();

    document.querySelectorAll('.tab-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(function(btn) {
                btn.classList.remove('active');
            });
            document.querySelectorAll('.tab-pane').forEach(function(tab) {
                tab.classList.remove('active');
            });

            button.classList.add('active');
            document.getElementById(button.getAttribute('data-tab')).classList.add('active');
        });
    });

    toggleGiftFields();

    function toggleGiftFields() {
        const checkbox = document.getElementById('is-gift-checkbox');
        const fields = document.getElementById('gift-fields');
        fields.style.display = checkbox.checked ? 'block' : 'none';
    }

    if (<?php echo isset($giftValidationFailed) && $giftValidationFailed ? 'true' : 'false'; ?>) {
        document.getElementById('gift-fields').scrollIntoView({behavior: 'smooth'});
    }
});
</script>

</body>
</html>

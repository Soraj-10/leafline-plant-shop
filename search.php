<?php
// search.php — Live Product Search (NEW FILE, read-only, no existing files edited)
session_start();
require_once 'db.php';

// AJAX branch: returns JSON, used by the fetch() call below
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    $like = '%' . $q . '%';
    $stmt = $conn->prepare("SELECT id, name, price, discount_percent, image_url, type, sunlight, watering_days
                             FROM plants WHERE name LIKE ? OR description LIKE ? ORDER BY name LIMIT 20");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $row['final_price'] = round(discountedPrice($row['price'], $row['discount_percent']), 2);
        $out[] = $row;
    }
    echo json_encode($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Search — LeafLine</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
    <a href="/plantshop/" class="navbar-brand">🌿 Leaf<span>Line</span></a>
    <ul class="navbar-nav">
        <li><a href="/plantshop/">Home</a></li>
        <li><a href="shop">Shop</a></li>
        <li><a href="exotic">Exotic</a></li>
        <li><a href="care">Care</a></li>
    </ul>
</nav>

<section style="max-width:900px; margin:40px auto; padding:0 20px;">
    <h1 style="color:var(--green-deep);">🔍 Search Plants</h1>
    <input type="text" id="searchBox" placeholder="Search by name or description..." class="form-control" style="margin:16px 0; font-size:1.1rem; padding:14px;">
    <div id="results" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:20px;"></div>
    <p id="empty" style="color:var(--text-mid); display:none;">No plants matched your search.</p>
</section>

<script>
const box = document.getElementById('searchBox');
const results = document.getElementById('results');
const empty = document.getElementById('empty');
let timer = null;

box.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(runSearch, 250); // debounce so we don't hit the server on every keystroke
});

async function runSearch() {
    const q = box.value.trim();
    if (q.length === 0) { results.innerHTML = ''; empty.style.display = 'none'; return; }

    const res = await fetch('search.php?ajax=1&q=' + encodeURIComponent(q));
    const data = await res.json();

    results.innerHTML = '';
    empty.style.display = data.length === 0 ? 'block' : 'none';

    data.forEach(p => {
        const card = document.createElement('div');
        card.className = 'card';
        card.style.padding = '14px';
        card.innerHTML = `
            <img src="${p.image_url || 'https://via.placeholder.com/200'}" style="width:100%; height:140px; object-fit:cover; border-radius:8px;">
            <h3 style="color:var(--green-deep); margin:10px 0 4px;">${escapeHtml(p.name)}</h3>
            <p style="margin:0; color:var(--green-mid); font-weight:600;">$${p.final_price}</p>
            <p style="margin:4px 0 0; font-size:13px; color:var(--text-mid);">💧 every ${p.watering_days}d · ☀️ ${escapeHtml(p.sunlight)}</p>
            <a href="shop#plant-${p.id}" class="btn btn-secondary" style="margin-top:10px; display:block; text-align:center;">View in Shop</a>
        `;
        results.appendChild(card);
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>
</body>
</html>

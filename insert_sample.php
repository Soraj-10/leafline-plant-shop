<?php
require_once 'db.php';

// Prevent inserting duplicates if this script is run more than once
$existing = $conn->query("SELECT COUNT(*) AS c FROM plants")->fetch_assoc()['c'];
if ($existing > 0) {
    die("Sample plants were already inserted before ($existing plants found). Nothing to do. " .
        "If you want to reset the catalog, empty the 'plants' table first.");
}

$plants = [
    [
        'name' => 'Snake Plant',
        'description' => 'A hardy, low-maintenance plant that purifies air and thrives in low light.',
        'price' => 25.00,
        'image_url' => 'https://images.unsplash.com/photo-1593691509543-c55fb32d8de5?w=400&q=80',
        'type' => 'regular',
        'sunlight' => 'Low',
        'watering_days' => 14,
        'stock' => 50,
        'discount_percent' => 0
    ],
    [
        'name' => 'Monstera Deliciosa',
        'description' => 'Tropical plant with split leaves, perfect for indoor spaces.',
        'price' => 45.00,
        'image_url' => 'https://images.unsplash.com/photo-1545241047-6083a3684587?w=400&q=80',
        'type' => 'regular',
        'sunlight' => 'Medium',
        'watering_days' => 7,
        'stock' => 30,
        'discount_percent' => 10
    ],
    [
        'name' => 'Fiddle Leaf Fig',
        'description' => 'Elegant plant with large leaves, a statement piece for any room.',
        'price' => 60.00,
        'image_url' => 'https://images.unsplash.com/photo-1586093148909-4e1166c8c4b8?w=400&q=80',
        'type' => 'regular',
        'sunlight' => 'Bright',
        'watering_days' => 7,
        'stock' => 20,
        'discount_percent' => 0
    ],
    [
        'name' => 'Pothos',
        'description' => 'Trailing vine that\'s almost impossible to kill, great for beginners.',
        'price' => 15.00,
        'image_url' => 'https://images.unsplash.com/photo-1586093148909-4e1166c8c4b8?w=400&q=80',
        'type' => 'regular',
        'sunlight' => 'Low',
        'watering_days' => 10,
        'stock' => 100,
        'discount_percent' => 0
    ],
    [
        'name' => 'ZZ Plant',
        'description' => 'Drought-tolerant plant that stores water in its roots.',
        'price' => 30.00,
        'image_url' => 'https://images.unsplash.com/photo-1593691509543-c55fb32d8de5?w=400&q=80',
        'type' => 'regular',
        'sunlight' => 'Low',
        'watering_days' => 21,
        'stock' => 40,
        'discount_percent' => 5
    ],
    [
        'name' => 'Rubber Plant',
        'description' => 'Glossy leaves and air-purifying qualities make this a favorite.',
        'price' => 35.00,
        'image_url' => 'https://images.unsplash.com/photo-1545241047-6083a3684587?w=400&q=80',
        'type' => 'regular',
        'sunlight' => 'Medium',
        'watering_days' => 10,
        'stock' => 25,
        'discount_percent' => 0
    ],
    [
        'name' => 'Bird of Paradise',
        'description' => 'A striking exotic plant with large, banana-like leaves that bring a tropical feel indoors.',
        'price' => 85.00,
        'image_url' => 'https://images.unsplash.com/photo-1596547609652-9cf5d8d76921?w=400&q=80',
        'type' => 'exotic',
        'sunlight' => 'High',
        'watering_days' => 7,
        'stock' => 15,
        'discount_percent' => 0
    ],
    [
        'name' => 'Venus Flytrap',
        'description' => 'A rare carnivorous plant that snaps shut to catch insects — a true conversation starter.',
        'price' => 22.00,
        'image_url' => 'https://images.unsplash.com/photo-1616690248363-6e5c3ca23f8a?w=400&q=80',
        'type' => 'exotic',
        'sunlight' => 'High',
        'watering_days' => 5,
        'stock' => 20,
        'discount_percent' => 0
    ],
    [
        'name' => 'Blue Star Fern',
        'description' => 'An exotic fern with silvery-blue fronds that thrives in humid indoor environments.',
        'price' => 38.00,
        'image_url' => 'https://images.unsplash.com/photo-1614594975525-e45190c55d0b?w=400&q=80',
        'type' => 'exotic',
        'sunlight' => 'Medium',
        'watering_days' => 6,
        'stock' => 18,
        'discount_percent' => 15
    ],
    [
        'name' => 'Bonsai Tree',
        'description' => 'A meticulously shaped miniature tree, symbolizing patience and harmony.',
        'price' => 95.00,
        'image_url' => 'https://images.unsplash.com/photo-1611048267451-e6ed903d4a38?w=400&q=80',
        'type' => 'exotic',
        'sunlight' => 'High',
        'watering_days' => 4,
        'stock' => 10,
        'discount_percent' => 0
    ]
];

$stmt = $conn->prepare("INSERT INTO plants (name, description, price, image_url, type, sunlight, watering_days, stock, discount_percent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
foreach ($plants as $plant) {
    $stmt->bind_param("ssdsssiii", $plant['name'], $plant['description'], $plant['price'], $plant['image_url'], $plant['type'], $plant['sunlight'], $plant['watering_days'], $plant['stock'], $plant['discount_percent']);
    $stmt->execute();
}

echo "Sample plants inserted!";
?>
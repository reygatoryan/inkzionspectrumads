<?php
/**
 * Dynamic Sitemap Generator
 * Generates XML sitemap for search engines
 */

header('Content-Type: application/xml; charset=utf-8');

require_once 'db-config.php';

$baseUrl = 'https://inkzion.com';
$lastMod = date('Y-m-d');

// Start XML
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

// Homepage
echo '<url>' . "\n";
echo '<loc>' . htmlspecialchars($baseUrl) . '</loc>' . "\n";
echo '<lastmod>' . $lastMod . '</lastmod>' . "\n";
echo '<changefreq>daily</changefreq>' . "\n";
echo '<priority>1.0</priority>' . "\n";
echo '</url>' . "\n";

// Products page
echo '<url>' . "\n";
echo '<loc>' . htmlspecialchars($baseUrl . '/customer/store-product.php') . '</loc>' . "\n";
echo '<lastmod>' . $lastMod . '</lastmod>' . "\n";
echo '<changefreq>daily</changefreq>' . "\n";
echo '<priority>0.9</priority>' . "\n";
echo '</url>' . "\n";

// Services page (index sections)
$services = [
    'Business Cards' => 'business-cards',
    'Marketing Materials' => 'marketing-materials',
    'Large Format & Signage' => 'large-format-signage',
    'Apparel & Sublimation' => 'apparel-sublimation',
    'Custom Merchandise' => 'custom-merchandise',
    'Promotional Items' => 'promotional-items',
    'Specialty Printing' => 'specialty-printing'
];

foreach ($services as $name => $slug) {
    echo '<url>' . "\n";
    echo '<loc>' . htmlspecialchars($baseUrl . '/customer/index.php#' . $slug) . '</loc>' . "\n";
    echo '<lastmod>' . $lastMod . '</lastmod>' . "\n";
    echo '<changefreq>weekly</changefreq>' . "\n";
    echo '<priority>0.8</priority>' . "\n";
    echo '</url>' . "\n";
}

// Get all active products
$products = $conn->query("
    SELECT p.id, p.name, p.image_url, p.updated_at 
    FROM products p 
    WHERE p.stock > 0 
    ORDER BY p.updated_at DESC
");

while ($product = $products->fetch_assoc()) {
    $productUrl = $baseUrl . '/customer/product-details.php?id=' . $product['id'];
    $productLastMod = date('Y-m-d', strtotime($product['updated_at']));
    
    echo '<url>' . "\n";
    echo '<loc>' . htmlspecialchars($productUrl) . '</loc>' . "\n";
    echo '<lastmod>' . $productLastMod . '</lastmod>' . "\n";
    echo '<changefreq>weekly</changefreq>' . "\n";
    echo '<priority>0.7</priority>' . "\n";
    
    // Add image if available
    if (!empty($product['image_url'])) {
        $imageUrl = $baseUrl . '/' . $product['image_url'];
        echo '<image:image>' . "\n";
        echo '<image:loc>' . htmlspecialchars($imageUrl) . '</image:loc>' . "\n";
        echo '<image:title>' . htmlspecialchars($product['name']) . '</image:title>' . "\n";
        echo '</image:image>' . "\n";
    }
    
    echo '</url>' . "\n";
}

// About page
echo '<url>' . "\n";
echo '<loc>' . htmlspecialchars($baseUrl . '/customer/index.php#about') . '</loc>' . "\n";
echo '<lastmod>' . $lastMod . '</lastmod>' . "\n";
echo '<changefreq>monthly</changefreq>' . "\n";
echo '<priority>0.6</priority>' . "\n";
echo '</url>' . "\n";

// Contact page
echo '<url>' . "\n";
echo '<loc>' . htmlspecialchars($baseUrl . '/customer/index.php#contact') . '</loc>' . "\n";
echo '<lastmod>' . $lastMod . '</lastmod>' . "\n";
echo '<changefreq>monthly</changefreq>' . "\n";
echo '<priority>0.6</priority>' . "\n";
echo '</url>' . "\n";

// Custom printing page
echo '<url>' . "\n";
echo '<loc>' . htmlspecialchars($baseUrl . '/customer/custom-printing.php') . '</loc>' . "\n";
echo '<lastmod>' . $lastMod . '</lastmod>' . "\n";
echo '<changefreq>weekly</changefreq>' . "\n";
echo '<priority>0.7</priority>' . "\n";
echo '</url>' . "\n";

echo '</urlset>' . "\n";

$conn->close();
?>

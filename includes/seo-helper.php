<?php
/**
 * SEO Helper Functions
 * Provides meta tags, Open Graph, and structured data
 */

/**
 * Get base URL
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'inkzion.com';
    return $protocol . '://' . $host;
}

/**
 * Generate meta tags
 */
function generateMetaTags($title, $description, $keywords = '', $canonical = '') {
    $baseUrl = getBaseUrl();
    $canonical = $canonical ?: $baseUrl . $_SERVER['REQUEST_URI'];
    
    $tags = [];
    
    // Basic meta tags
    $tags[] = '<title>' . htmlspecialchars($title) . '</title>';
    $tags[] = '<meta name="description" content="' . htmlspecialchars($description) . '">';
    $tags[] = '<meta name="keywords" content="' . htmlspecialchars($keywords) . '">';
    $tags[] = '<link rel="canonical" href="' . htmlspecialchars($canonical) . '">';
    
    // Open Graph tags
    $tags[] = '<meta property="og:title" content="' . htmlspecialchars($title) . '">';
    $tags[] = '<meta property="og:description" content="' . htmlspecialchars($description) . '">';
    $tags[] = '<meta property="og:url" content="' . htmlspecialchars($canonical) . '">';
    $tags[] = '<meta property="og:type" content="website">';
    $tags[] = '<meta property="og:site_name" content="Inkzion Spectrum Ads">';
    $tags[] = '<meta property="og:image" content="' . $baseUrl . '/assets/logo.png">';
    $tags[] = '<meta property="og:image:width" content="1200">';
    $tags[] = '<meta property="og:image:height" content="630">';
    
    // Twitter Card tags
    $tags[] = '<meta name="twitter:card" content="summary_large_image">';
    $tags[] = '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">';
    $tags[] = '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">';
    $tags[] = '<meta name="twitter:image" content="' . $baseUrl . '/assets/logo.png">';
    
    return implode("\n", $tags);
}

/**
 * Generate product meta tags
 */
function generateProductMetaTags($product) {
    $baseUrl = getBaseUrl();
    $title = $product['name'] . ' - Inkzion Spectrum Ads';
    $description = substr(strip_tags($product['description'] ?? ''), 0, 160);
    if (empty($description)) {
        $description = 'Buy ' . $product['name'] . ' at Inkzion Spectrum Ads. High-quality printing services.';
    }
    $canonical = $baseUrl . '/customer/product-details.php?id=' . $product['id'];
    $image = !empty($product['image_url']) ? $baseUrl . '/' . $product['image_url'] : $baseUrl . '/assets/products-demo.jpg';
    
    $tags = [];
    $tags[] = '<title>' . htmlspecialchars($title) . '</title>';
    $tags[] = '<meta name="description" content="' . htmlspecialchars($description) . '">';
    $tags[] = '<link rel="canonical" href="' . htmlspecialchars($canonical) . '">';
    
    // Open Graph
    $tags[] = '<meta property="og:title" content="' . htmlspecialchars($title) . '">';
    $tags[] = '<meta property="og:description" content="' . htmlspecialchars($description) . '">';
    $tags[] = '<meta property="og:url" content="' . htmlspecialchars($canonical) . '">';
    $tags[] = '<meta property="og:type" content="product">';
    $tags[] = '<meta property="og:image" content="' . htmlspecialchars($image) . '">';
    $tags[] = '<meta property="product:price:amount" content="' . number_format($product['price'], 2) . '">';
    $tags[] = '<meta property="product:price:currency" content="PHP">';
    
    // Twitter Card
    $tags[] = '<meta name="twitter:card" content="summary_large_image">';
    $tags[] = '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">';
    $tags[] = '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">';
    $tags[] = '<meta name="twitter:image" content="' . htmlspecialchars($image) . '">';
    
    return implode("\n", $tags);
}

/**
 * Generate Organization schema markup
 */
function generateOrganizationSchema() {
    $baseUrl = getBaseUrl();
    
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Inkzion Spectrum Ads',
        'url' => $baseUrl,
        'logo' => $baseUrl . '/assets/logo.png',
        'description' => 'Your trusted partner for high-quality printing and advertising solutions.',
        'address' => [
            '@type' => 'PostalAddress',
            'addressCountry' => 'Philippines'
        ],
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'contactType' => 'customer service',
            'email' => 'info@inkzion.com',
            'telephone' => '+63-XXX-XXXX-XXX'
        ],
        'sameAs' => [
            'https://www.facebook.com/inkzionspectrumads'
        ]
    ];
    
    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}

/**
 * Generate Product schema markup
 */
function generateProductSchema($product) {
    $baseUrl = getBaseUrl();
    
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product['name'],
        'description' => strip_tags($product['description'] ?? ''),
        'image' => !empty($product['image_url']) ? $baseUrl . '/' . $product['image_url'] : $baseUrl . '/assets/products-demo.jpg',
        'offers' => [
            '@type' => 'Offer',
            'price' => number_format($product['price'], 2),
            'priceCurrency' => 'PHP',
            'availability' => $product['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'seller' => [
                '@type' => 'Organization',
                'name' => 'Inkzion Spectrum Ads'
            ]
        ]
    ];
    
    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}

/**
 * Generate BreadcrumbList schema markup
 */
function generateBreadcrumbSchema($breadcrumbs) {
    $baseUrl = getBaseUrl();
    
    $items = [];
    foreach ($breadcrumbs as $index => $crumb) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $crumb['name'],
            'item' => $baseUrl . $crumb['url']
        ];
    }
    
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items
    ];
    
    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}

/**
 * Generate LocalBusiness schema markup
 */
function generateLocalBusinessSchema() {
    $baseUrl = getBaseUrl();
    
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => 'Inkzion Spectrum Ads',
        'image' => $baseUrl . '/assets/logo.png',
        'description' => 'High-quality printing and advertising solutions',
        'address' => [
            '@type' => 'PostalAddress',
            'addressCountry' => 'Philippines'
        ],
        'priceRange' => '₱₱',
        'openingHoursSpecification' => [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'opens' => '09:00',
            'closes' => '18:00'
        ]
    ];
    
    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}

/**
 * Generate WebSite schema markup
 */
function generateWebSiteSchema() {
    $baseUrl = getBaseUrl();
    
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'Inkzion Spectrum Ads',
        'url' => $baseUrl,
        'description' => 'Your trusted partner for high-quality printing and advertising solutions.',
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => $baseUrl . '/customer/store-product.php?search={search_term_string}',
            'query-input' => 'required name=search_term_string'
        ]
    ];
    
    return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}

/**
 * Output all SEO tags for a page
 */
function outputSEOTags($title, $description, $keywords = '', $canonical = '', $schema = []) {
    echo generateMetaTags($title, $description, $keywords, $canonical);
    echo "\n";
    echo generateOrganizationSchema();
    echo "\n";
    echo generateWebSiteSchema();
    
    if (!empty($schema)) {
        echo "\n";
        foreach ($schema as $schemaTag) {
            echo $schemaTag . "\n";
        }
    }
}
?>
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
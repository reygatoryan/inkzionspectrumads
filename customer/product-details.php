<?php
require_once __DIR__ . '/../includes/session-helper.php';
secureSessionStart();
require_once '../db-config.php';
require_once '../includes/google-config.php';

$loggedIn = !empty($_SESSION['user_id']);
$isSeller = !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$userName = $loggedIn ? trim($_SESSION['user_name'] ?? '') : '';
$userInitials = '';
if ($loggedIn && $userName !== '') {
    $parts = array_filter(preg_split('/\s+/', $userName));
    $userInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
if ($loggedIn && $userInitials === '') {
    $userInitials = 'ME';
}

// Get product from query parameter
$productName = isset($_GET['product']) ? trim((string)$_GET['product']) : '';

$product = null;
if ($productName !== '') {
    $stmt = $conn->prepare("SELECT p.id, p.name, c.name AS category, p.description, p.price, p.image_url FROM products p JOIN categories c ON p.category_id = c.id WHERE p.name = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $productName);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $product = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

// Product data fallback when DB is unavailable or product not yet in database
$fallbackProducts = [
    ['category' => 'Business Cards', 'name' => 'Standard Business Cards', 'description' => '350gsm premium cardstock, 500 pieces', 'price' => '$29', 'image' => 'assets/BUSINESSCARDS/STANDARDCARD.png'],
    ['category' => 'Business Cards', 'name' => 'Premium Embossed Cards', 'description' => 'Raised ink with luxe finish, 500 pieces', 'price' => '$59', 'image' => 'assets/BUSINESSCARDS/EMBOSSEDCARD.png'],
    ['category' => 'Business Cards', 'name' => 'Metal Business Cards', 'description' => 'Stainless steel with engraving, 100 pieces', 'price' => '$149', 'image' => 'assets/BUSINESSCARDS/METALCARD.png'],
    ['category' => 'Business Cards', 'name' => 'Calling Cards', 'description' => 'Elegant calling cards, 1000 pieces', 'price' => '$39', 'image' => 'assets/BUSINESSCARDS/CALLINGCARD.png'],
    ['category' => 'Marketing Materials', 'name' => 'Tri-Fold Brochures', 'description' => 'Full color, double-sided, 500 pieces', 'price' => '$89', 'image' => 'assets/MARKETING_MATERIALS/TRIFOLD.png'],
    ['category' => 'Marketing Materials', 'name' => 'Flyers & Postcards', 'description' => 'Glossy or matte finish, 1000 pieces', 'price' => '$39', 'image' => 'assets/MARKETING_MATERIALS/FLYERS.png'],
    ['category' => 'Marketing Materials', 'name' => 'Calendars', 'description' => 'Custom printed wall or desk calendars', 'price' => '$49', 'image' => 'assets/MARKETING_MATERIALS/CALENDAR.png'],
    ['category' => 'Large Format & Signage', 'name' => 'Vinyl Banners', 'description' => 'Indoor & outdoor durability, custom sizes', 'price' => '$79', 'image' => 'assets/LARGE_FORMAT_AND_SIGNAGE/VINYLBANNER.png'],
    ['category' => 'Large Format & Signage', 'name' => 'Stand Banners', 'description' => 'Retractable banner stands, premium quality', 'price' => '$199', 'image' => 'assets/LARGE_FORMAT_AND_SIGNAGE/STANDBANNER.png'],
    ['category' => 'Large Format & Signage', 'name' => 'Signage', 'description' => 'Custom shop signs and directional signage', 'price' => '$149', 'image' => 'assets/LARGE_FORMAT_AND_SIGNAGE/SIGNAGE.png'],
    ['category' => 'Large Format & Signage', 'name' => 'Tarpaulins & Panaflex', 'description' => 'Heavy-duty tarpaulins and flexible signage', 'price' => '$129', 'image' => 'assets/LARGE_FORMAT_AND_SIGNAGE/TARP.png'],
    ['category' => 'Apparel & Sublimation', 'name' => 'Sublimation Round Neck T-Shirt', 'description' => 'Full color printing, sizes XS-2XL', 'price' => '$15', 'image' => 'assets/APPAREL_AND_SUBLIMATION/T SHIRT BELTECH.png'],
    ['category' => 'Apparel & Sublimation', 'name' => 'Sublimation Polo Shirt', 'description' => 'Zipper or button type, professional look', 'price' => '$25', 'image' => 'https://via.placeholder.com/600x400/00bcd4/ffffff?text=Polo+Shirt'],
    ['category' => 'Apparel & Sublimation', 'name' => 'Sublimation Varsity Jacket', 'description' => 'Premium fabric, custom design', 'price' => '$59', 'image' => 'https://via.placeholder.com/600x400/ffc107/ffffff?text=Varsity+Jacket'],
    ['category' => 'Apparel & Sublimation', 'name' => 'Basketball Jersey & Uniforms', 'description' => 'Chinese collar or basketball styles', 'price' => '$35', 'image' => 'https://via.placeholder.com/600x400/8bc34a/ffffff?text=Basketball'],
    ['category' => 'Custom Merchandise', 'name' => 'Custom Mugs', 'description' => '11oz ceramic with full color wrap', 'price' => '$12', 'image' => 'assets/CUSTOM_MERCHANDISE/MUG.png'],
    ['category' => 'Custom Merchandise', 'name' => 'Tumblers', 'description' => 'Stainless steel, insulated, 20oz', 'price' => '$18', 'image' => 'assets/CUSTOM_MERCHANDISE/TUMBLER.png'],
    ['category' => 'Custom Merchandise', 'name' => 'Custom Caps & Hats', 'description' => 'Embroidered or printed logos', 'price' => '$14', 'image' => 'assets/CUSTOM_MERCHANDISE/CAP.png'],
    ['category' => 'Custom Merchandise', 'name' => 'Mouse Pads', 'description' => 'Non-slip rubber base, custom design', 'price' => '$8', 'image' => 'assets/CUSTOM_MERCHANDISE/MOUSE.png'],
    ['category' => 'Custom Merchandise', 'name' => 'Label Stickers', 'description' => 'Die-cut custom shapes, waterproof', 'price' => '$19', 'image' => 'assets/CUSTOM_MERCHANDISE/LABEL STICKER.png'],
    ['category' => 'Custom Merchandise', 'name' => 'Tote Bags', 'description' => 'Eco-friendly reusable bags with custom print', 'price' => '$15', 'image' => 'assets/CUSTOM_MERCHANDISE/TOTE BAG.png'],
    ['category' => 'Promotional Items & Giveaways', 'name' => 'Lanyards', 'description' => 'Custom printed with logo', 'price' => '$5', 'image' => 'assets/PROMOTIONAL_ITEMS_AND_GIVEAWAYS/LANYARD.png'],
    ['category' => 'Promotional Items & Giveaways', 'name' => 'PVC IDs & Cards', 'description' => 'Professional ID cards, 100 pieces', 'price' => '$29', 'image' => 'assets/PROMOTIONAL_ITEMS_AND_GIVEAWAYS/PVC ID.png'],
    ['category' => 'Promotional Items & Giveaways', 'name' => 'Giveaways & Promotional Items', 'description' => 'Branded merchandise for events', 'price' => '$10+', 'image' => 'assets/PROMOTIONAL_ITEMS_AND_GIVEAWAYS/GIVEAWAYS.png'],
    ['category' => 'Promotional Items & Giveaways', 'name' => 'Custom Umbrellas', 'description' => 'Branded umbrellas with custom print', 'price' => '$25', 'image' => 'assets/PROMOTIONAL_ITEMS_AND_GIVEAWAYS/UMBRELLA.png'],
    ['category' => 'Certificates & Documents', 'name' => 'Certificate Printing', 'description' => 'Professional certificates with borders, 100 pieces', 'price' => '$29', 'image' => 'https://via.placeholder.com/600x400/8bc34a/ffffff?text=Certificates'],
    ['category' => 'Certificates & Documents', 'name' => 'Diploma Printing', 'description' => 'Premium diploma printing with gold seal', 'price' => '$49', 'image' => 'https://via.placeholder.com/600x400/ff9800/ffffff?text=Diploma'],
    ['category' => 'Certificates & Documents', 'name' => 'Award Plaques', 'description' => 'Wooden or acrylic plaques with engraving', 'price' => '$39', 'image' => 'https://via.placeholder.com/600x400/9c27b0/ffffff?text=Plaque'],
    ['category' => 'Certificates & Documents', 'name' => 'Document Binding', 'description' => 'Professional spiral or comb binding', 'price' => '$15', 'image' => 'https://via.placeholder.com/600x400/607d8b/ffffff?text=Binding'],
];

if (!$product) {
    foreach ($fallbackProducts as $p) {
        if ($p['name'] === $productName) {
            $product = $p;
            break;
        }
    }
}

if (!$product) {
    header('Location: store-product.php');
    exit;
}

$displayPrice = $product['price'];
if (is_numeric($displayPrice)) {
    $displayPrice = '₱' . number_format((float)$displayPrice, 2);
} else {
    $displayPrice = str_replace('$', '₱', $displayPrice);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?php echo $product['name']; ?> | Inkzion Spectrum Ads</title>
  <link rel="stylesheet" href="../styles.css?v=2">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <style>
    .g-signin-wrapper { display: flex; align-items: center; }
    .g-signin-wrapper > div > iframe { max-width: 210px !important; }
    .g-signin-wrapper .g_id_signin { display: flex; align-items: center; }
    .compact-login { display: none; font-size: 0.7rem; color: var(--primary); font-weight: 700; white-space: nowrap; text-decoration: none; align-items: center; gap: 0.25rem; }
  </style>
  <style>
    .product-details-section {
      padding: 60px 20px;
      background: #f5f8ff;
      min-height: calc(100vh - 96px);
    }

    .product-details-section .container {
      background: white;
      border-radius: 32px;
      box-shadow: 0 30px 80px rgba(15, 23, 42, 0.08);
      padding: 40px;
    }

    .product-details-container {
      display: grid;
      grid-template-columns: minmax(300px, 1fr) 1.1fr;
      gap: 60px;
      align-items: start;
    }

    .product-image-section {
      position: relative;
    }

    .product-main-image {
      width: 100%;
      border-radius: 28px;
      overflow: hidden;
      background: #f8fafc;
      box-shadow: 0 24px 72px rgba(15, 23, 42, 0.08);
    }

    .product-main-image img {
      width: 100%;
      height: auto;
      display: block;
    }

    .product-info-section h1 {
      font-size: 2.4rem;
      margin-bottom: 12px;
      color: #111827;
      line-height: 1.05;
    }

    .product-category {
      display: inline-block;
      background: linear-gradient(135deg, #2B4C52 0%, #4A7C84 100%);
      color: white;
      padding: 8px 18px;
      border-radius: 999px;
      font-size: 0.85rem;
      font-weight: 700;
      margin-bottom: 20px;
      letter-spacing: 0.02em;
    }

    .product-description {
      font-size: 1rem;
      color: #475569;
      margin-bottom: 28px;
      line-height: 1.75;
      max-width: 640px;
    }

    .product-price {
      font-size: 2.2rem;
      font-weight: 800;
      color: #2B4C52;
      margin-bottom: 30px;
      background: rgba(43, 76, 82, 0.08);
      padding: 16px 18px;
      border-radius: 18px;
      display: inline-flex;
      align-items: center;
    }

    .product-details-features {
      margin-bottom: 40px;
    }

    .product-details-features h3 {
      margin-bottom: 18px;
      color: #111827;
      font-size: 1.15rem;
    }

    .feature-list {
      display: grid;
      gap: 12px;
    }

    .feature-list li {
      display: flex;
      align-items: center;
      color: #475569;
      padding: 14px 18px;
      background: #f8fafc;
      border-radius: 16px;
      border: 1px solid rgba(226, 232, 240, 0.8);
    }

    .feature-list li:before {
      content: "âœ“";
      color: #047857;
      font-weight: 700;
      margin-right: 12px;
    }

    .quantity-section {
      margin-bottom: 30px;
    }

    .quantity-section label {
      display: block;
      margin-bottom: 10px;
      font-weight: 700;
      color: #0f172a;
    }

    .quantity-controls {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .quantity-controls button {
      width: 46px;
      height: 46px;
      border: 1px solid #cbd5e1;
      background: white;
      border-radius: 14px;
      cursor: pointer;
      font-size: 1.3rem;
      transition: all 0.3s ease;
      color: #1e293b;
    }

    .quantity-controls button:hover {
      background: #eef2ff;
      border-color: #a5b4fc;
    }

    .quantity-input {
      width: 80px;
      height: 46px;
      text-align: center;
      border: 1px solid #cbd5e1;
      border-radius: 14px;
      font-size: 1rem;
      color: #0f172a;
      background: #f8fafc;
    }

    .product-actions {
      display: flex;
      gap: 15px;
      margin-bottom: 30px;
      flex-wrap: wrap;
    }

    .btn-customize-page {
      flex: 1;
      padding: 15px 25px;
      background: white;
      color: #2B4C52;
      border: 2px solid #2B4C52;
      border-radius: 8px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }

    .btn-customize-page:hover {
      background: #2B4C52;
      color: white;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: #2B4C52;
      text-decoration: none;
      margin-bottom: 30px;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .back-link:hover {
      gap: 12px;
    }

    .related-products-section {
      margin-top: 100px;
      padding-top: 60px;
      border-top: 2px solid #f0f0f0;
    }

    .related-products-section h3 {
      font-size: 2rem;
      margin-bottom: 30px;
      text-align: center;
      color: #1a1a1a;
    }

    .related-products-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 25px;
    }

    .related-product-card {
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
      height: 100%;
      text-decoration: none;
    }

    .related-product-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(43, 76, 82, 0.15);
    }

    .related-product-image {
      width: 100%;
      height: 180px;
      background: #f5f5f5;
      overflow: hidden;
    }

    .related-product-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.3s ease;
    }

    .related-product-card:hover .related-product-image img {
      transform: scale(1.05);
    }

    .related-product-body {
      padding: 16px;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .related-product-name {
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 8px;
      font-size: 1rem;
    }

    .related-product-price {
      font-size: 1.3rem;
      font-weight: 700;
      color: #2B4C52;
      margin-top: auto;
    }

    .testimonials-section {
      margin-top: 80px;
      padding: 50px;
      background: linear-gradient(135deg, rgba(43, 76, 82, 0.08) 0%, rgba(43, 76, 82, 0.08) 100%);
      border-radius: 12px;
      text-align: center;
    }

    .testimonials-section h3 {
      font-size: 1.8rem;
      margin-bottom: 30px;
      color: #1a1a1a;
    }

    .testimonial {
      max-width: 600px;
      margin: 0 auto 30px;
      padding: 25px;
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .testimonial-text {
      font-style: italic;
      color: #666;
      margin-bottom: 15px;
      line-height: 1.6;
    }

    .testimonial-author {
      font-weight: 600;
      color: #2B4C52;
    }

    @media (max-width: 768px) {
      .product-details-container {
        grid-template-columns: 1fr;
        gap: 30px;
      }

      .product-image-section {
        position: relative;
        top: auto;
      }

      .product-info-section h1 {
        font-size: 2rem;
      }

      .product-actions {
        flex-direction: column;
      }

      .related-products-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
      }

      .testimonials-section {
        padding: 30px 20px;
      }
    }
    @media (max-width: 640px) {
      .product-details-section { padding: 40px 15px; }
      .product-info-section h1 { font-size: 1.75rem; }
      .related-products-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
    }
    @media (max-width: 576px) {
      .product-details-section { padding: 30px 12px; }
      .product-details-section .container { padding: 30px; }
    }
    @media (max-width: 480px) {
      .product-info-section h1 { font-size: 1.5rem; }
      .product-details-container { gap: 20px; }
      .product-info-section .price { font-size: 1.5rem; }
      .related-products-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
      .testimonials-section { padding: 20px 15px; }
    }
    @media (max-width: 400px) {
      .product-details-section { padding: 20px 10px; }
      .product-details-section .container { padding: 20px; border-radius: 20px; }
      .product-details-container { gap: 14px; }
      .product-info-section h1 { font-size: 1.25rem; }
      .product-info-section .price { font-size: 1.2rem; padding: 10px 12px; }
      .product-actions { flex-direction: column; }
      .product-actions .btn { width: 100%; justify-content: center; }
      .feature-list li { padding: 10px 12px; font-size: 0.82rem; }
      .quantity-controls button { width: 38px; height: 38px; }
      .quantity-input { width: 60px; height: 38px; }
      .related-products-grid { grid-template-columns: 1fr; gap: 10px; }
      .related-products-section { margin-top: 40px; padding-top: 24px; }
      .related-products-section h3 { font-size: 1.3rem; }
      .testimonials-section { padding: 20px 12px; margin-top: 30px; }
      .testimonial { padding: 16px; }
      .compact-login { display: inline-flex; }
      .g-signin-wrapper { display: none; }
    }
    @media (max-width: 360px) {
      .product-details-section .container { padding: 14px; border-radius: 16px; }
      .product-details-container { gap: 10px; }
      .product-info-section h1 { font-size: 1.05rem; }
      .product-info-section .price { font-size: 1rem; padding: 8px 10px; }
      .feature-list li { padding: 8px 10px; font-size: 0.72rem; }
      .quantity-controls button { width: 32px; height: 32px; font-size: 0.8rem; }
      .quantity-input { width: 48px; height: 32px; font-size: 0.75rem; }
      .product-actions .btn { font-size: 0.7rem; padding: 0.5rem; }
      .related-products-grid { gap: 6px; }
      .related-products-section { margin-top: 24px; padding-top: 16px; }
      .related-products-section h3 { font-size: 1rem; }
      .testimonials-section { padding: 14px 8px; margin-top: 20px; }
      .testimonial { padding: 12px; font-size: 0.78rem; }
      .compact-login { font-size: 0.55rem; gap: 0.15rem; }
      .compact-login i { font-size: 0.65rem; }
    }
    @media (max-width: 768px) {
      .hamburger-btn, .header-icon-btn { min-width: 44px; min-height: 44px; }
      .modal-close { min-width: 44px; min-height: 44px; }
      .notif-mark-all-btn { min-height: 44px; padding: 0.5rem 1rem; }
    }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="topbar"></div>
    <div class="container header-inner">
      <a href="../index.php" class="brand">
        <img src="../assets/logo.png" alt="Inkzion Spectrum Ads logo" class="site-logo">
        <div>
          <span class="brand-title">INKZION</span>
          <span class="brand-subtitle">SPECTRUM ADS</span>
        </div>
      </a>

      <?php if ($loggedIn): ?>
      <div class="header-action-group">
        <?php if ($isSeller): ?>
        <a href="admin/dashboard.php" class="btn auth-btn" title="Seller Dashboard">
          <i class="fas fa-store"></i> Dashboard
        </a>
        <?php else: ?>
        <?php if (!$isSeller): ?>
        <a href="profile.php" class="btn auth-btn" title="Signed in as <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>">
          <i class="fas fa-user"></i> Profile
        </a>
        <?php endif; ?>
        <?php if (!$isSeller): ?>
        <a href="../index.php" class="btn auth-btn">Home</a>
        <?php endif; ?>
        <?php endif; ?>
        <a href="../logout.php" class="btn auth-btn">Logout</a>
      </div>
      <?php else: ?>
        <a href="../login.php" class="compact-login"><i class="fas fa-sign-in-alt"></i> Login</a>
        <a href="../index.php" class="btn auth-btn">Home</a>
        <div class="g-signin-wrapper">
          <div id="g_id_onload"
               data-client_id="<?php echo GOOGLE_CLIENT_ID; ?>"
               data-callback="handleGoogleCredential"
               data-auto_prompt="false">
          </div>
          <div class="g_id_signin"
               data-type="standard"
               data-shape="pill"
               data-theme="outline"
               data-text="sign_in_with"
               data-size="medium"
               data-logo_alignment="left">
          </div>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <main>
    <section class="product-details-section">
      <div class="container">
        <a href="store-product.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Products</a>
        
        <div class="product-details-container">
          <div class="product-image-section">
            <div class="product-main-image">
              <img src="<?php echo htmlspecialchars($product['image_url'] ?? $product['image'], ENT_QUOTES, 'UTF-8'); ?>" onerror="this.src='assets/products-demo.jpg'" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>

          <div class="product-info-section">
            <span class="product-category"><?php echo $product['category']; ?></span>
            <h1><?php echo $product['name']; ?></h1>
            <p class="product-description"><?php echo $product['description']; ?></p>
            
            <div class="product-price"><?php echo htmlspecialchars($displayPrice, ENT_QUOTES, 'UTF-8'); ?></div>

            <div class="product-details-features">
              <h3>Product Details</h3>
              <ul class="feature-list">
                <li>High-quality materials and craftsmanship</li>
                <li>Customizable design options available</li>
                <li>Fast turnaround time</li>
                <li>Professional finishing</li>
                <li>Competitive pricing</li>
              </ul>
            </div>

            <?php if ($isSeller && !empty($product['id'])): ?>
            <div class="seller-edit-section" style="margin-bottom: 30px;">
              <a href="admin/products.php?action=edit&id=<?php echo $product['id']; ?>" class="btn btn-edit-product" style="display: inline-flex; align-items: center; gap: 10px; padding: 15px 30px; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s ease; text-decoration: none;">
                <i class="fas fa-edit"></i> Edit Product
              </a>
              <p style="margin-top: 10px; color: #64748b; font-size: 0.9rem;"><i class="fas fa-info-circle"></i> Manage this product from your seller dashboard.</p>
            </div>
            <?php else: ?>
            <div class="quantity-section">
              <label for="product-quantity">Quantity:</label>
              <div class="quantity-controls">
                <button id="qty-decrease-page">âˆ’</button>
                <input type="number" id="product-quantity" class="quantity-input" value="1" min="1">
                <button id="qty-increase-page">+</button>
              </div>
            </div>

            <?php if (!$isSeller): ?>
            <div class="product-actions">
              <button type="button" class="btn-customize-page" id="customize-page">
                <i class="fas fa-paint-brush"></i> Request Customization
              </button>
              <a href="chat.php<?= !empty($product['id']) ? '?product_id='.$product['id'] : ''; ?>" class="btn" style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.75rem 1.5rem;border-radius:12px;background:linear-gradient(135deg,#2B4C52,#4A7C84);color:white;text-decoration:none;font-weight:600;font-size:0.9rem;border:none;cursor:pointer;transition:all 0.2s ease;">
                <i class="fas fa-comments"></i> Chat with Admin
              </a>
              <button type="button" class="btn" style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.75rem 1.5rem;border-radius:12px;background:white;color:#0f172a;text-decoration:none;font-weight:600;font-size:0.9rem;border:1px solid #e2e8f0;cursor:pointer;transition:all 0.2s ease;" onclick="showContactInfo()">
                <i class="fas fa-headset" style="color:#2B4C52;"></i> Contact Admin
              </button>
              <div id="contactOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:99999;align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)document.getElementById('contactOverlay').style.display='none'">
                <div style="background:white;border-radius:16px;padding:2rem;max-width:360px;width:100%;text-align:center;box-shadow:0 24px 80px rgba(0,0,0,0.2);animation:modalIn 0.2s ease;">
                  <div style="width:56px;height:56px;border-radius:50%;background:rgba(43, 76, 82,0.1);display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#2B4C52;margin:0 auto 1rem;">
                    <i class="fas fa-headset"></i>
                  </div>
                  <div style="font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:0.3rem;">Contact Admin</div>
                  <div style="font-size:0.82rem;color:#64748b;margin-bottom:1.25rem;">Reach us directly through these channels:</div>
                  <div style="display:flex;flex-direction:column;gap:0.65rem;">
                    <a href="tel:+63912456789" style="display:flex;align-items:center;gap:0.65rem;padding:0.75rem 1rem;background:#f8fafc;border-radius:10px;text-decoration:none;color:#0f172a;font-size:0.85rem;font-weight:500;">
                      <i class="fas fa-phone" style="color:#2B4C52;width:18px;"></i> +63 912 345 6789
                    </a>
                    <a href="mailto:info@inkzionspectrum.com" style="display:flex;align-items:center;gap:0.65rem;padding:0.75rem 1rem;background:#f8fafc;border-radius:10px;text-decoration:none;color:#0f172a;font-size:0.85rem;font-weight:500;">
                      <i class="fas fa-envelope" style="color:#2B4C52;width:18px;"></i> info@inkzionspectrum.com
                    </a>
                    <a href="https://facebook.com/inkzionspectrumads" target="_blank" style="display:flex;align-items:center;gap:0.65rem;padding:0.75rem 1rem;background:#f8fafc;border-radius:10px;text-decoration:none;color:#0f172a;font-size:0.85rem;font-weight:500;">
                      <i class="fab fa-facebook" style="color:#1877F2;width:18px;"></i> Inkzion Spectrum Ads
                    </a>
                  </div>
                  <button onclick="document.getElementById('contactOverlay').style.display='none'" style="margin-top:1.25rem;padding:0.6rem 1.5rem;border-radius:10px;border:none;background:#f1f5f9;color:#475569;font-weight:600;font-size:0.82rem;cursor:pointer;">Close</button>
                </div>
              </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

          </div>
        </div>

        <!-- Related Products Section -->
        <div class="related-products-section">
          <h3>More from <?php echo $product['category']; ?></h3>
          <div class="related-products-grid" id="related-products">
            <!-- Will be populated by JavaScript -->
          </div>
        </div>

        <!-- Testimonials Section -->
        <div class="testimonials-section">
          <h3>What Our Customers Say</h3>
          <div class="testimonial">
            <p class="testimonial-text">"The quality of our printed materials was exceptional. The team was responsive and delivered on time!"</p>
            <p class="testimonial-author">â€” Sarah Johnson, Marketing Manager</p>
          </div>
          <div class="testimonial">
            <p class="testimonial-text">"Best printing service we've used. Competitive pricing and outstanding customer service."</p>
            <p class="testimonial-author">â€” Michael Chen, Business Owner</p>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="footer-top"></div>
    <div class="container footer-grid">
      <div>
        <div class="footer-brand">
          <img src="../assets/logo.png" alt="Inkzion Spectrum Ads Logo" class="footer-logo">
          <div>
            <strong>INKZION</strong>
            <span>SPECTRUM ADS</span>
          </div>
        </div>
        <p>Premium printing and advertising services for businesses and individuals.</p>
      </div>
      <div>
        <h4>Services</h4>
        <ul>
          <li><a href="../index.php#services">Business Cards</a></li>
          <li><a href="../index.php#services">Brochures</a></li>
          <li><a href="../index.php#services">Banners</a></li>
          <li><a href="../index.php#services">Merchandise</a></li>
          <li><a href="../index.php#services">Design Services</a></li>
        </ul>
      </div>
      <div>
        <h4>Company</h4>
        <ul>
          <li><a href="../index.php#about">About Us</a></li>
          <li><a href="../index.php#contact">Contact</a></li>
        </ul>
      </div>
    </div>
    <div class="container footer-bottom">
      <p>Â© <span id="year"></span> Inkzion Spectrum Ads. All rights reserved.</p>
    </div>
  </footer>
  <script src="../script.js"></script>
  <script>
    // Year
    const year = document.getElementById('year');
    if (year) year.textContent = new Date().getFullYear();

    const isLoggedIn = <?php echo $loggedIn ? 'true' : 'false'; ?>;
    const loginButton = document.getElementById('auth-trigger');

    function requireLoginAction(message) {
      if (!isLoggedIn) {
        alert(message || 'Please log in first.');
        if (loginButton) {
          loginButton.focus();
        }
        return false;
      }
      return true;
    }

    // Products data for related products
    const allProducts = [
      { category: 'Business Cards', items: [
        { name: 'Standard Business Cards', price: '$29', image: 'assets/BUSINESSCARDS/STANDARDCARD.png' },
        { name: 'Premium Embossed Cards', price: '$59', image: 'assets/BUSINESSCARDS/EMBOSSEDCARD.png' },
        { name: 'Metal Business Cards', price: '$149', image: 'assets/BUSINESSCARDS/METALCARD.png' },
        { name: 'Calling Cards', price: '$39', image: 'assets/BUSINESSCARDS/CALLINGCARD.png' },
      ]},
      { category: 'Marketing Materials', items: [
        { name: 'Tri-Fold Brochures', price: '$89', image: 'assets/MARKETING_MATERIALS/TRIFOLD.png' },
        { name: 'Flyers & Postcards', price: '$39', image: 'assets/MARKETING_MATERIALS/FLYERS.png' },
        { name: 'Calendars', price: '$49', image: 'assets/MARKETING_MATERIALS/CALENDAR.png' },
      ]},
      { category: 'Large Format & Signage', items: [
        { name: 'Vinyl Banners', price: '$79', image: 'assets/LARGE_FORMAT_AND_SIGNAGE/VINYLBANNER.png' },
        { name: 'Stand Banners', price: '$199', image: 'assets/LARGE_FORMAT_AND_SIGNAGE/STANDBANNER.png' },
        { name: 'Signage', price: '$149', image: 'assets/LARGE_FORMAT_AND_SIGNAGE/SIGNAGE.png' },
        { name: 'Tarpaulins & Panaflex', price: '$129', image: 'assets/LARGE_FORMAT_AND_SIGNAGE/TARP.png' },
      ]},
      { category: 'Apparel & Sublimation', items: [
        { name: 'Sublimation Round Neck T-Shirt', price: '$15', image: 'assets/APPAREL_AND_SUBLIMATION/T SHIRT BELTECH.png' },
        { name: 'Sublimation Polo Shirt', price: '$25', image: 'https://via.placeholder.com/600x400/00bcd4/ffffff?text=Polo+Shirt' },
        { name: 'Sublimation Varsity Jacket', price: '$59', image: 'https://via.placeholder.com/600x400/ffc107/ffffff?text=Varsity+Jacket' },
        { name: 'Basketball Jersey & Uniforms', price: '$35', image: 'https://via.placeholder.com/600x400/8bc34a/ffffff?text=Basketball' },
      ]},
      { category: 'Custom Merchandise', items: [
        { name: 'Custom Mugs', price: '$12', image: 'assets/CUSTOM_MERCHANDISE/MUG.png' },
        { name: 'Tumblers', price: '$18', image: 'assets/CUSTOM_MERCHANDISE/TUMBLER.png' },
        { name: 'Custom Caps & Hats', price: '$14', image: 'assets/CUSTOM_MERCHANDISE/CAP.png' },
        { name: 'Mouse Pads', price: '$8', image: 'assets/CUSTOM_MERCHANDISE/MOUSE.png' },
        { name: 'Label Stickers', price: '$10', image: 'assets/CUSTOM_MERCHANDISE/LABEL STICKER.png' },
        { name: 'Tote Bags', price: '$15', image: 'assets/CUSTOM_MERCHANDISE/TOTE BAG.png' },
      ]},
      { category: 'Promotional Items & Giveaways', items: [
        { name: 'Lanyards', price: '$5', image: 'assets/PROMOTIONAL_ITEMS_AND_GIVEAWAYS/LANYARD.png' },
        { name: 'PVC IDs & Cards', price: '$29', image: 'assets/PROMOTIONAL_ITEMS_AND_GIVEAWAYS/PVC ID.png' },
        { name: 'Giveaways & Promotional Items', price: '$10+', image: 'assets/PROMOTIONAL_ITEMS_AND_GIVEAWAYS/GIVEAWAYS.png' },
        { name: 'Custom Umbrellas', price: '$25', image: 'assets/PROMOTIONAL_ITEMS_AND_GIVEAWAYS/UMBRELLA.png' },
      ]},
      { category: 'Certificates & Documents', items: [
        { name: 'Certificate Printing', price: '$29', image: 'https://via.placeholder.com/600x400/8bc34a/ffffff?text=Certificates' },
        { name: 'Diploma Printing', price: '$49', image: 'https://via.placeholder.com/600x400/ff9800/ffffff?text=Diploma' },
        { name: 'Award Plaques', price: '$39', image: 'https://via.placeholder.com/600x400/9c27b0/ffffff?text=Plaque' },
        { name: 'Document Binding', price: '$15', image: 'https://via.placeholder.com/600x400/607d8b/ffffff?text=Binding' },
      ]},
    ];

    const currentProductName = '<?php echo htmlspecialchars(addslashes($product['name']), ENT_QUOTES, 'UTF-8'); ?>';
    const currentCategory = '<?php echo htmlspecialchars(addslashes($product['category']), ENT_QUOTES, 'UTF-8'); ?>';

    // Populate related products
    function renderRelatedProducts() {
      const categoryData = allProducts.find(cat => cat.category === currentCategory);
      const relatedContainer = document.getElementById('related-products');
      if (!relatedContainer || !categoryData) return;

      const relatedItems = categoryData.items
        .filter(item => item.name !== currentProductName)
        .slice(0, 3);

      relatedContainer.innerHTML = relatedItems.map(item => `
        <a href="product-details.php?product=${encodeURIComponent(item.name)}" class="related-product-card">
          <div class="related-product-image">
            <img src="${item.image}" onerror="this.src='assets/products-demo.jpg'" alt="${item.name}">
          </div>
          <div class="related-product-body">
            <div class="related-product-name">${item.name}</div>
            <div class="related-product-price">${item.price}</div>
          </div>
        </a>
      `).join('');
    }

    renderRelatedProducts();

    // Quantity controls
    const quantityInput = document.getElementById('product-quantity');
    const decreaseBtn = document.getElementById('qty-decrease-page');
    const increaseBtn = document.getElementById('qty-increase-page');

    if (decreaseBtn) {
      decreaseBtn.addEventListener('click', () => {
        const current = parseInt(quantityInput.value) || 1;
        if (current > 1) quantityInput.value = current - 1;
      });
    }

    if (increaseBtn) {
      increaseBtn.addEventListener('click', () => {
        quantityInput.value = (parseInt(quantityInput.value) || 1) + 1;
      });
    }

    // Customize/Request Customization
    const customizeBtn = document.getElementById('customize-page');
    if (customizeBtn) {
      customizeBtn.addEventListener('click', () => {
        if (!requireLoginAction('Please log in before requesting customization.')) {
          return;
        }
        const productName = '<?php echo addslashes($product['name']); ?>';
        const quantity = parseInt(quantityInput.value) || 1;
        window.location.href = `request-customization.php?product=${encodeURIComponent(productName)}&quantity=${quantity}`;
      });
    }

    // Request Custom Quote button
    function showContactInfo() {
      document.getElementById('contactOverlay').style.display = 'flex';
    }

    const quoteBtn = document.getElementById('quote-btn');
    if (quoteBtn) {
      quoteBtn.addEventListener('click', () => {
        const productName = '<?php echo addslashes($product['name']); ?>';
        const quantity = parseInt(quantityInput.value) || 1;
        window.location.href = `request-customization.php?product=${encodeURIComponent(productName)}&quantity=${quantity}`;
      });
    }
  </script>
  <script>
    async function handleGoogleCredential(response) {
      try {
        const res = await fetch('../api/google-auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ credential: response.credential })
        });
        const data = await res.json();
        if (data.ok) {
          window.location.href = data.redirect;
        } else {
          console.error('Google auth error:', data);
          alert(data.error || 'Sign-in failed. Please try again.');
        }
      } catch (e) {
        console.error('Google auth exception:', e);
        alert('Sign-in failed. Please try again.');
      }
    }
  </script>
  <script>navigator.sendBeacon('../api/track-visit.php?url=' + encodeURIComponent(location.pathname + location.search) + '&_=' + Date.now());</script>
</body>
</html>


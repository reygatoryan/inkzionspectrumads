<?php
require_once __DIR__ . '/../includes/session-helper.php';
secureSessionStart();
require_once __DIR__ . '/../includes/csrf-helper.php';
$csrfToken = generateCsrfToken();
require_once '../db-config.php';
require_once dirname(__DIR__) . '/includes/seo-helper.php';

if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header('Location: ../admin/dashboard.php');
    exit;
}

$loggedIn = !empty($_SESSION['user_id']);
$userName = $loggedIn ? trim($_SESSION['user_name'] ?? '') : '';
$userInitials = '';
if ($loggedIn && $userName !== '') {
    $parts = array_filter(preg_split('/\s+/', $userName));
    $userInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
if ($loggedIn && $userInitials === '') {
    $userInitials = 'ME';
}
$userProfilePhoto = $_SESSION['user_profile_photo'] ?? '';

$isSeller = !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Check if Printing Services category is selected
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';
$isPrintingServices = ($selectedCategory === 'Printing Services');

$uploadDir = __DIR__ . '/../uploads/products';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
}

$useDbProducts = false;
$categoryOptions = [];
$productsByCategory = [];
$errors = [];
$action = trim($_GET['action'] ?? '');
$editProduct = null;

$categoryStmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
if ($categoryStmt) {
    $categoryStmt->execute();
    $categoryResult = $categoryStmt->get_result();
    while ($row = $categoryResult->fetch_assoc()) {
        $categoryOptions[$row['name']] = (int)$row['id'];
    }
    $categoryStmt->close();
}

if (!empty($categoryOptions)) {
    $useDbProducts = true;
    $productResult = $conn->query("SELECT p.id, p.name, p.description, p.price, p.image_url, p.stock, c.name AS category FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.name");
    if ($productResult) {
        while ($row = $productResult->fetch_assoc()) {
            $productsByCategory[$row['category']][] = $row;
            if (!isset($categoryOptions[$row['category']])) {
                $categoryOptions[$row['category']] = 0;
            }
        }
    }
}

// Filter products for Printing Services category if selected
if ($isPrintingServices && !empty($productsByCategory)) {
    $printingCategories = ['Business Cards', 'Marketing Materials', 'Certificates & Documents'];
    $filteredProducts = [];
    foreach ($productsByCategory as $category => $products) {
        if (in_array($category, $printingCategories)) {
            $filteredProducts[$category] = $products;
        }
    }
    $productsByCategory = $filteredProducts;
}

if (!$useDbProducts) {
    $categoryOptions = [
        'Business Cards' => 0,
        'Marketing Materials' => 0,
        'Large Format & Signage' => 0,
        'Apparel & Sublimation' => 0,
        'Custom Merchandise' => 0,
        'Promotional Items & Giveaways' => 0,
        'Certificates & Documents' => 0,
    ];

    $productsByCategory = [
        'Business Cards' => [
            ['name' => 'Standard Business Cards', 'description' => '350gsm premium cardstock, 500 pieces', 'price' => '$29', 'image' => 'assets/BUSINESSCARDS/STANDARDCARD.png', 'features' => ['High quality cardstock', 'Fast delivery', 'Free design consultation']],
            ['name' => 'Premium Embossed Cards', 'description' => 'Raised ink with luxe finish, 500 pieces', 'price' => '$59', 'image' => 'assets/BUSINESSCARDS/EMBOSSEDCARD.png', 'features' => ['Luxury embossing', 'Premium feel', 'Professional look']],
            ['name' => 'Metal Business Cards', 'description' => 'Stainless steel with engraving, 100 pieces', 'price' => '$149', 'image' => 'assets/BUSINESSCARDS/METALCARD.png', 'features' => ['Premium metal finish', 'Engraved details', 'Modern look']],
            ['name' => 'Calling Cards', 'description' => 'Elegant calling cards, 1000 pieces', 'price' => '$39', 'image' => 'assets/BUSINESSCARDS/CALLINGCARD.png', 'features' => ['Elegant design', 'Bulk quantity', 'Affordable']],
        ],
        'Marketing Materials' => [
            ['name' => 'Tri-Fold Brochures', 'description' => 'Full color, double-sided, 500 pieces', 'price' => '$89', 'image' => 'assets/MARKETING_MATERIALS/TRIFOLD.png', 'features' => ['Full color printing', 'Professional quality', 'Custom fold']],
            ['name' => 'Flyers & Postcards', 'description' => 'Glossy or matte finish, 1000 pieces', 'price' => '$39', 'image' => 'assets/MARKETING_MATERIALS/FLYERS.png', 'features' => ['High gloss finish', 'Matte option available', 'Fast turnaround']],
            ['name' => 'Calendars', 'description' => 'Custom printed wall or desk calendars', 'price' => '$49', 'image' => 'assets/MARKETING_MATERIALS/CALENDAR.png', 'features' => ['Custom design', 'Spiral bound', 'Premium paper']],
            ['name' => 'Certificate Printing', 'description' => 'Professional certificates with borders, 100 pieces', 'price' => '$29', 'image' => 'https://via.placeholder.com/600x400/8bc34a/ffffff?text=Certificates', 'features' => ['Professional quality', 'Custom borders', 'Certificate paper']],
        ],
        'Large Format & Signage' => [
            ['name' => 'Vinyl Banners', 'description' => 'Indoor & outdoor durability, custom sizes', 'price' => '$79', 'image' => 'assets/LARGE_FORMAT_AND_SIGNAGE/VINYLBANNER.png', 'features' => ['Weather resistant', 'Vibrant colors', 'Custom sizes']],
            ['name' => 'Stand Banners', 'description' => 'Retractable banner stands, premium quality', 'price' => '$199', 'image' => 'assets/LARGE_FORMAT_AND_SIGNAGE/STANDBANNER.png', 'features' => ['Retractable stand', 'Easy setup', 'Portable']],
            ['name' => 'Signage', 'description' => 'Custom shop signs and directional signage', 'price' => '$149', 'image' => 'assets/LARGE_FORMAT_AND_SIGNAGE/SIGNAGE.png', 'features' => ['Durable materials', 'Custom sizes', 'Indoor/outdoor']],
            ['name' => 'Tarpaulins & Panaflex', 'description' => 'Heavy-duty tarpaulins and flexible signage', 'price' => '$129', 'image' => 'assets/LARGE_FORMAT_AND_SIGNAGE/TARP.png', 'features' => ['Heavy duty', 'All-weather', 'Custom designs']],
        ],
        'Apparel & Sublimation' => [
            ['name' => 'Sublimation Round Neck T-Shirt', 'description' => 'Full color printing, sizes XS-2XL', 'price' => '$15', 'image' => 'assets/APPAREL_AND_SUBLIMATION/T SHIRT BELTECH.png', 'features' => ['Full color print', 'Comfort fit', 'Breathable fabric']],
            ['name' => 'Sublimation Polo Shirt', 'description' => 'Zipper or button type, professional look', 'price' => '$25', 'image' => 'https://via.placeholder.com/600x400/00bcd4/ffffff?text=Polo+Shirt', 'features' => ['Professional look', 'Custom design', 'Multiple colors']],
            ['name' => 'Sublimation Varsity Jacket', 'description' => 'Premium fabric, custom design', 'price' => '$59', 'image' => 'https://via.placeholder.com/600x400/ffc107/ffffff?text=Varsity+Jacket', 'features' => ['Premium fabric', 'Custom design', 'Durable stitching']],
            ['name' => 'Basketball Jersey & Uniforms', 'description' => 'Chinese collar or basketball styles', 'price' => '$35', 'image' => 'https://via.placeholder.com/600x400/8bc34a/ffffff?text=Basketball', 'features' => ['Moisture wicking', 'Custom colors', 'Team sets']],
        ],
        'Custom Merchandise' => [
            ['name' => 'Custom Mugs', 'description' => '11oz ceramic with full color wrap', 'price' => '$12', 'image' => 'assets/CUSTOM_MERCHANDISE/MUG.png', 'features' => ['Dishwasher safe', 'Full color', 'Premium ceramic']],
            ['name' => 'Tumblers', 'description' => 'Stainless steel, insulated, 20oz', 'price' => '$18', 'image' => 'assets/CUSTOM_MERCHANDISE/TUMBLER.png', 'features' => ['Insulated', 'Stainless steel', 'Custom design']],
            ['name' => 'Custom Caps & Hats', 'description' => 'Embroidered or printed logos', 'price' => '$14', 'image' => 'assets/CUSTOM_MERCHANDISE/CAP.png', 'features' => ['Embroidered logo', 'Adjustable fit', 'Premium quality']],
            ['name' => 'Mouse Pads', 'description' => 'Non-slip rubber base, custom design', 'price' => '$8', 'image' => 'assets/CUSTOM_MERCHANDISE/MOUSE.png', 'features' => ['Non-slip base', 'Smooth surface', 'Custom print']],
            ['name' => 'Label Stickers', 'description' => 'Die-cut custom shapes, waterproof', 'price' => '$10', 'image' => 'assets/CUSTOM_MERCHANDISE/LABEL STICKER.png', 'features' => ['Waterproof', 'Die-cut shapes', 'Vibrant colors']],
            ['name' => 'Tote Bags', 'description' => 'Eco-friendly reusable bags with custom print', 'price' => '$15', 'image' => 'assets/CUSTOM_MERCHANDISE/TOTE BAG.png', 'features' => ['Eco-friendly', 'Reusable', 'Custom design']],
        ],
        'Promotional Items & Giveaways' => [
            ['name' => 'Lanyards', 'description' => 'Custom printed with logo', 'price' => '$5', 'image' => 'assets/PROMOTIONAL_ITEMS_AND_GIVEAWAYS/LANYARD.png', 'features' => ['Custom print', 'Durable clip', 'Bulk order']],
            ['name' => 'PVC IDs & Cards', 'description' => 'Professional ID cards, 100 pieces', 'price' => '$29', 'image' => 'assets/PROMOTIONAL_ITEMS_AND_GIVEAWAYS/PVC ID.png', 'features' => ['Professional quality', 'Durable PVC', 'Customizable']],
            ['name' => 'Giveaways & Promotional Items', 'description' => 'Branded merchandise for events', 'price' => '$10+', 'image' => 'assets/PROMOTIONAL_ITEMS_AND_GIVEAWAYS/GIVEAWAYS.png', 'features' => ['Custom branding', 'Event-ready', 'Various items']],
            ['name' => 'Custom Umbrellas', 'description' => 'Branded umbrellas with custom print', 'price' => '$25', 'image' => 'assets/PROMOTIONAL_ITEMS_AND_GIVEAWAYS/UMBRELLA.png', 'features' => ['Custom print', 'Durable frame', 'Compact design']],
        ],
        'Certificates & Documents' => [
            ['name' => 'Certificate Printing', 'description' => 'Professional certificates with borders, 100 pieces', 'price' => '$29', 'image' => 'https://via.placeholder.com/600x400/8bc34a/ffffff?text=Certificates', 'features' => ['Professional quality', 'Custom borders', 'Certificate paper']],
            ['name' => 'Diploma Printing', 'description' => 'Premium diploma printing with gold seal', 'price' => '$49', 'image' => 'https://via.placeholder.com/600x400/ff9800/ffffff?text=Diploma', 'features' => ['Premium paper', 'Gold seal', 'Custom text']],
            ['name' => 'Award Plaques', 'description' => 'Wooden or acrylic plaques with engraving', 'price' => '$39', 'image' => 'https://via.placeholder.com/600x400/9c27b0/ffffff?text=Plaque', 'features' => ['Engraved text', 'Premium finish', 'Ready to hang']],
            ['name' => 'Document Binding', 'description' => 'Professional spiral or comb binding', 'price' => '$15', 'image' => 'https://via.placeholder.com/600x400/607d8b/ffffff?text=Binding', 'features' => ['Spiral/comb binding', 'Clear covers', 'Professional look']],
        ],
    ];
}

?>
<?php
$seoTitle = 'Products | Inkzion Spectrum Ads';
$seoDescription = 'Browse our wide range of printing products and services at Inkzion Spectrum Ads. High-quality business cards, marketing materials, signage, and more.';
$seoKeywords = 'products, printing, business cards, flyers, banners, signage, apparel, merchandise';
outputSEOTags($seoTitle, $seoDescription, $seoKeywords);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Products | Inkzion Spectrum Ads</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <style>
    .g-signin-wrapper { display: flex; align-items: center; }
    .g-signin-wrapper > div > iframe { max-width: 210px !important; }
    .g-signin-wrapper .g_id_signin { display: flex; align-items: center; }
    .compact-login { display: none; font-size: 0.7rem; color: var(--primary); font-weight: 700; white-space: nowrap; text-decoration: none; align-items: center; gap: 0.25rem; }
  </style>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --sidebar-bg: #FAF7EE;
      --sidebar-hover: rgba(43, 76, 82, 0.08);
      --sidebar-active: #2B4C52;
      --sidebar-active-bg: rgba(43, 76, 82, 0.12);
      --sidebar-width: 270px;
      --primary: #2B4C52;
      --primary-light: #4A7C84;
      --primary-bg: rgba(43, 76, 82, 0.1);
      --success: #10B981;
      --success-bg: rgba(16, 185, 129, 0.1);
      --warning: #F59E0B;
      --warning-bg: rgba(245, 158, 11, 0.1);
      --danger: #EF4444;
      --danger-bg: rgba(239, 68, 68, 0.1);
      --text-primary: #0F172A;
      --text-secondary: #475569;
      --text-muted: #64748B;
      --border-color: #E2E8F0;
      --border-light: #F1F5F9;
      --card-bg: #FFFFFF;
      --card-radius: 12px;
      --font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    body { font-family: var(--font); background: #F8FAFC; color: var(--text-primary); line-height: 1.6; overflow-x: hidden; }
    .dashboard-wrapper { display: flex; min-height: 100vh; }
    
    /* ========= SIDEBAR ========= */
    .products-sidebar {
      width: var(--sidebar-width);
      background: var(--sidebar-bg);
      border-right: 1px solid rgba(43, 76, 82, 0.1);
      padding: 0;
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      overflow-y: auto;
      z-index: 100;
      display: flex;
      flex-direction: column;
    }
    .products-sidebar::-webkit-scrollbar { width: 3px; }
    .products-sidebar::-webkit-scrollbar-thumb { background: rgba(43, 76, 82, 0.2); border-radius: 4px; }
    
    .sidebar-brand {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 1.25rem 1.25rem 1rem;
      border-bottom: 1px solid rgba(43, 76, 82, 0.12);
      position: sticky;
      top: 0;
      background: var(--sidebar-bg);
      z-index: 2;
    }
    .sidebar-brand-img {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      object-fit: contain;
      background: white;
      padding: 4px;
      box-shadow: 0 2px 6px rgba(43, 76, 82, 0.15);
    }
    .sidebar-brand-text { line-height: 1.2; }
    .sidebar-brand-name { font-size: 0.85rem; font-weight: 800; color: #1a1a2e; letter-spacing: 0.03em; display: block; }
    .sidebar-brand-sub { font-size: 0.6rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; }
    
    .sidebar-profile {
      padding: 0.85rem 1.25rem;
      display: flex;
      align-items: center;
      gap: 0.65rem;
      border-bottom: 1px solid rgba(43, 76, 82, 0.08);
      background: rgba(43, 76, 82, 0.03);
    }
    .sidebar-avatar {
      width: 30px;
      height: 30px;
      border-radius: 8px;
      background: var(--primary);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.65rem;
      flex-shrink: 0;
    }
    .sidebar-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: inherit; }
    .sidebar-profile-info h4 { font-size: 0.75rem; font-weight: 600; color: var(--text-primary); }
    .sidebar-profile-info p { font-size: 0.6rem; color: var(--text-muted); }

    .sidebar-menu { flex: 1; padding: 0.75rem 0; }
    .sidebar-section-title {
      font-size: 0.6rem;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.1em;
      padding: 0.85rem 1.25rem 0.45rem;
    }
    
    .sidebar-menu-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.6rem 1.25rem;
      margin: 0 0.6rem;
      border-radius: 8px;
      font-size: 0.82rem;
      font-weight: 500;
      color: #4a4a5a;
      text-decoration: none;
      transition: var(--transition);
      position: relative;
      border-left: 3px solid transparent;
    }
    .sidebar-menu-item i {
      width: 20px;
      text-align: center;
      font-size: 0.85rem;
      color: #4A7C84;
      transition: var(--transition);
    }
    .sidebar-menu-item:hover {
      background: var(--sidebar-hover);
      color: var(--primary);
      border-left-color: var(--primary);
      transform: translateX(4px);
    }
    .sidebar-menu-item:hover i { color: var(--primary); transform: scale(1.1); }
    .sidebar-menu-item.active {
      background: var(--sidebar-active-bg);
      color: var(--primary);
      font-weight: 600;
      border-left-color: var(--primary);
      box-shadow: 0 2px 8px rgba(43, 76, 82, 0.08);
    }
    .sidebar-menu-item.active i { color: var(--primary); }
    .sidebar-menu-item .badge {
      margin-left: auto;
      padding: 0.15rem 0.5rem;
      border-radius: 999px;
      font-size: 0.6rem;
      font-weight: 700;
      background: var(--primary-bg);
      color: var(--primary);
    }
    .sidebar-menu-item .badge.red { background: var(--danger-bg); color: var(--danger); }
    
    .sidebar-footer {
      padding: 0.75rem 1.25rem;
      border-top: 1px solid rgba(43, 76, 82, 0.1);
    }
    .sidebar-footer-item {
      display: flex;
      align-items: center;
      gap: 0.65rem;
      padding: 0.5rem 0;
      font-size: 0.78rem;
      color: var(--text-muted);
      text-decoration: none;
      transition: var(--transition);
    }
    .sidebar-footer-item:hover { color: var(--primary); }
    .sidebar-footer-item i { width: 18px; font-size: 0.85rem; color: #4A7C84; }
    .sidebar-footer button.sidebar-footer-item { background: none; border: none; cursor: pointer; width: 100%; text-align: left; font: inherit; color: var(--text-muted); display: flex; align-items: center; gap: 0.65rem; padding: 0.5rem 0; font-size: 0.78rem; text-decoration: none; transition: var(--transition); }
    .sidebar-footer button.sidebar-footer-item:hover { color: var(--primary); }

    /* ========= MAIN CONTENT ========= */
    .products-main {
      flex: 1;
      margin-left: var(--sidebar-width);
      min-height: 100vh;
    }
    
    /* === TOP HEADER === */
    .top-header {
      background: white;
      border-bottom: 1px solid var(--border-color);
      padding: 0 2rem;
      position: sticky;
      top: 0;
      z-index: 50;
    }
    
    .top-header-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 72px;
      gap: 1.5rem;
    }
    
    .top-header-left {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    
    .hamburger-btn {
      display: none;
      width: 40px;
      height: 40px;
      border-radius: 10px;
      border: 1px solid var(--border-color);
      background: white;
      color: var(--text-secondary);
      cursor: pointer;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
      transition: var(--transition);
    }
    .hamburger-btn:hover {
      border-color: var(--primary);
      color: var(--primary);
    }
    
    .top-header-title h1 {
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--text-primary);
      line-height: 1.3;
    }
    .top-header-title p {
      font-size: 0.75rem;
      color: var(--text-muted);
      margin-top: 0.05rem;
    }
    
    .top-header-center {
      flex: 1;
      max-width: 520px;
      margin: 0 auto;
    }
    
    .header-search-wrapper {
      position: relative;
      width: 100%;
    }
    .header-search-wrapper i {
      position: absolute;
      left: 1.1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #adb5bd;
      font-size: 0.9rem;
      pointer-events: none;
    }
    .header-search-input {
      width: 100%;
      padding: 0.7rem 1rem 0.7rem 2.85rem;
      border: 1.5px solid var(--border-color);
      border-radius: 50px;
      font-size: 0.85rem;
      font-family: var(--font);
      background: #F8FAFC;
      color: var(--text-primary);
      transition: var(--transition);
    }
    .header-search-input:focus {
      outline: none;
      border-color: var(--primary);
      background: white;
      box-shadow: 0 0 0 4px var(--primary-bg);
    }
    .header-search-input::placeholder {
      color: #adb5bd;
    }
    
    .top-header-right {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      position: relative;
    }
    
    .header-icon-btn {
      width: 42px;
      height: 42px;
      border-radius: 12px;
      border: 1px solid var(--border-color);
      background: white;
      color: var(--text-secondary);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      transition: var(--transition);
      position: relative;
      text-decoration: none;
    }
    .header-icon-btn:hover {
      border-color: var(--primary);
      color: var(--primary);
      background: var(--primary-bg);
      transform: translateY(-1px);
    }
    .header-icon-btn .notif-dot { position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; border-radius: 50%; background: var(--danger); border: 2px solid white; }
    .header-profile-btn {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.35rem 0.75rem 0.35rem 0.35rem;
      border-radius: 50px;
      border: 1px solid var(--border-color);
      background: white;
      cursor: pointer;
      transition: var(--transition);
      text-decoration: none;
      color: inherit;
    }

    .header-profile-dropdown-wrapper {
      position: relative;
    }

    .header-profile-dropdown-menu {
      position: absolute;
      top: calc(100% + 6px);
      right: 0;
      background: white;
      border-radius: 12px;
      border: 1px solid var(--border-color);
      box-shadow: 0 8px 24px rgba(0,0,0,0.1);
      padding: 0.5rem;
      width: 200px;
      z-index: 100;
      opacity: 0;
      visibility: hidden;
      transform: translateY(10px);
      transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s;
    }

    .header-profile-dropdown-menu.active {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
    }

    .header-profile-dropdown-item {
      display: flex;
      align-items: center;
      gap: 0.65rem;
      padding: 0.65rem 0.85rem;
      border-radius: 8px;
      font-size: 0.82rem;
      font-weight: 500;
      color: var(--text-primary);
      text-decoration: none;
      transition: var(--transition);
      cursor: pointer;
      border: none;
      background: none;
      width: 100%;
      font-family: var(--font);
      text-align: left;
    }

    .header-profile-dropdown-item i {
      width: 18px;
      text-align: center;
      font-size: 0.8rem;
      color: #4A7C84;
      transition: var(--transition);
    }

    .header-profile-dropdown-item:hover {
      background: var(--sidebar-hover);
      color: var(--primary);
    }

    .header-profile-dropdown-item:hover i {
      color: var(--primary);
    }

    .header-profile-dropdown-divider {
      height: 1px;
      background: var(--border-color);
      margin: 0.3rem 0;
    }

    .header-profile-dropdown-item.danger {
      color: var(--danger);
    }

    .header-profile-dropdown-item.danger i {
      color: var(--danger);
    }

    .header-profile-dropdown-item.danger:hover {
      background: var(--danger-bg);
      color: var(--danger);
    }

    .header-profile-dropdown-item.danger:hover i {
      color: var(--danger);
    }

    .header-profile-btn:hover {
      border-color: var(--primary);
      background: var(--primary-bg);
    }
    .header-profile-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--primary);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.65rem;
      flex-shrink: 0;
    }
    .header-profile-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: inherit; }
    .header-profile-name {
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--text-primary);
      white-space: nowrap;
    }
    .header-profile-arrow {
      font-size: 0.65rem;
      color: var(--text-muted);
      margin-left: 0.15rem;
    }
    
    /* === CONTENT AREA === */
    .content-area {
      padding: 1.5rem 2rem 2rem;
    }
    
    .products-toolbar {
      display: flex;
      gap: 1rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }
    
    .filter-chips {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    .filter-chip {
      padding: 0.6rem 1.25rem;
      border: 1.5px solid #e2e8f0;
      border-radius: 50px;
      background: white;
      color: #4a4a5a;
      font-size: 0.82rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      white-space: nowrap;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .filter-chip:hover {
      border-color: #2B4C52;
      color: #2B4C52;
      background: rgba(43, 76, 82, 0.06);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(43, 76, 82, 0.15);
    }
    .filter-chip.active {
      background: linear-gradient(135deg, #2B4C52 0%, #4A7C84 100%);
      color: white;
      border-color: transparent;
      box-shadow: 0 4px 14px rgba(43, 76, 82, 0.3);
      transform: translateY(-1px);
    }
    .filter-chip.active i {
      color: white;
    }
    .filter-chip i {
      font-size: 0.75rem;
      color: #4A7C84;
      transition: all 0.3s ease;
    }
    .filter-chip:hover i {
      color: #2B4C52;
    }
    
    /* SKELETON LOADING */
    @keyframes shimmer {
      0% { background-position: -200% 0; }
      100% { background-position: 200% 0; }
    }
    .skeleton {
      background: linear-gradient(90deg, #f0f0f5 25%, #e8e8f0 50%, #f0f0f5 75%);
      background-size: 200% 100%;
      animation: shimmer 1.5s ease-in-out infinite;
      border-radius: 8px;
    }
    .skeleton-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.5rem;
      margin-bottom: 1.5rem;
    }
    .skeleton-card {
      background: white;
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      border: 1px solid #f0f0f5;
    }
    .skeleton-card .skeleton-img {
      width: 100%;
      height: 250px;
    }
    .skeleton-card .skeleton-body {
      padding: 1.15rem;
      display: flex;
      flex-direction: column;
      gap: 0.65rem;
    }
    .skeleton-card .skeleton-line {
      height: 14px;
      width: 100%;
    }
    .skeleton-card .skeleton-line.short { width: 60%; }
    .skeleton-card .skeleton-line.medium { width: 80%; }
    .skeleton-card .skeleton-line.price { width: 30%; height: 20px; }
    .skeleton-card .skeleton-footer {
      padding: 0.75rem 1.15rem 1.15rem;
      border-top: 1px solid #f0f0f5;
      display: flex;
      gap: 0.5rem;
    }
    .skeleton-card .skeleton-btn {
      height: 36px;
      flex: 1;
      border-radius: 10px;
    }

    .products-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.5rem;
    }
    .product-card {
      background: white;
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      border: 1px solid #f0f0f5;
      transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
      display: flex;
      flex-direction: column;
      position: relative;
    }
    .product-card:hover {
      box-shadow: 0 12px 32px rgba(43, 76, 82, 0.12), 0 4px 8px rgba(0,0,0,0.04);
      transform: translateY(-4px);
      border-color: rgba(43, 76, 82, 0.15);
    }
    
    /* IMAGE SECTION */
    .product-card-image {
      width: 100%;
      height: 250px;
      overflow: hidden;
      background: #f8fafc;
      position: relative;
    }
    .product-card-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .product-card:hover .product-card-image img {
      transform: scale(1.08);
    }
    
    /* CATEGORY BADGE */
    .card-category-badge {
      position: absolute;
      top: 12px;
      left: 12px;
      padding: 0.3rem 0.75rem;
      background: rgba(255,255,255,0.92);
      backdrop-filter: blur(8px);
      border-radius: 20px;
      font-size: 0.65rem;
      font-weight: 700;
      color: var(--primary);
      z-index: 2;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    /* FAVORITE BUTTON */
    .card-fav-btn {
      position: absolute;
      top: 12px;
      right: 12px;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: rgba(255,255,255,0.92);
      backdrop-filter: blur(8px);
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #adb5bd;
      font-size: 0.9rem;
      transition: all 0.3s ease;
      z-index: 2;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .card-fav-btn:hover {
      background: var(--danger);
      color: white;
      transform: scale(1.1);
    }
    .card-fav-btn.liked {
      color: var(--danger);
    }
    
    /* QUICK VIEW OVERLAY */
    .card-quick-view {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 0.6rem;
      background: linear-gradient(transparent, rgba(0,0,0,0.6));
      display: flex;
      justify-content: center;
      opacity: 0;
      transform: translateY(10px);
      transition: all 0.35s ease;
      z-index: 2;
    }
    .product-card:hover .card-quick-view {
      opacity: 1;
      transform: translateY(0);
    }
    .card-quick-view-btn {
      padding: 0.45rem 1.25rem;
      background: rgba(255,255,255,0.92);
      backdrop-filter: blur(8px);
      border: none;
      border-radius: 20px;
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--text-primary);
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }
    .card-quick-view-btn:hover {
      background: white;
      color: var(--primary);
      transform: scale(1.05);
    }
    @media (hover: none) {
      .card-quick-view {
        opacity: 1;
        transform: none;
        padding: 0.4rem;
      }
      .card-quick-view-btn {
        padding: 0.4rem 1rem;
        font-size: 0.68rem;
      }
    }
    
    /* BODY */
    .product-card-body {
      padding: 1.15rem 1.15rem 0.85rem;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }
    .product-card-title {
      font-size: 1rem;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 0.3rem;
      line-height: 1.35;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .product-card-desc {
      font-size: 0.8rem;
      color: var(--text-muted);
      margin-bottom: 0.75rem;
      line-height: 1.5;
      flex-grow: 1;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    
    /* PRICE + STOCK ROW */
    .card-price-stock {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.75rem;
    }
    .product-price {
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--primary);
    }
    .card-stock {
      display: flex;
      align-items: center;
      gap: 0.3rem;
      font-size: 0.68rem;
      font-weight: 600;
    }
    .card-stock.in-stock { color: var(--success); }
    .card-stock.low-stock { color: var(--warning); }
    .card-stock.out-of-stock { color: var(--danger); }
    .card-stock .stock-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
    }
    .card-stock.in-stock .stock-dot { background: var(--success); }
    .card-stock.low-stock .stock-dot { background: var(--warning); }
    .card-stock.out-of-stock .stock-dot { background: var(--danger); }
    
    /* CARD ACTIONS FOOTER */
    .product-card-footer {
      display: flex;
      gap: 0.5rem;
      padding: 0.75rem 1.15rem 1.15rem;
      border-top: 1px solid #f0f0f5;
    }
    .product-card-footer .btn {
      flex: 1;
      justify-content: center;
    }
    
    .btn {
      padding: 0.6rem 1rem;
      border-radius: 10px;
      font-size: 0.78rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      border: none;
      font-family: var(--font);
      white-space: nowrap;
    }
    .btn-primary {
      background: linear-gradient(135deg, #2B4C52 0%, #4A7C84 100%);
      color: white;
      box-shadow: 0 3px 10px rgba(43, 76, 82, 0.2);
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(43, 76, 82, 0.3);
    }
    .btn-outline {
      background: white;
      color: var(--primary);
      border: 1.5px solid rgba(43, 76, 82, 0.2);
    }
    .btn-outline:hover {
      background: var(--primary-bg);
      border-color: var(--primary);
      transform: translateY(-2px);
    }
    .btn-sm {
      padding: 0.5rem 0.65rem;
      font-size: 0.72rem;
    }

    /* QUANTITY POPUP */
    .qty-popup-overlay {
      display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(15, 23, 42, 0.5);
      z-index: 1000; align-items: center; justify-content: center; padding: 1rem; overflow-y: auto;
    }
    .qty-popup-overlay.active { display: flex; animation: popupFadeIn 0.2s ease; }
    .qty-popup {
      background: white; border-radius: 20px; padding: 2rem;
      max-width: 380px; width: 100%; margin: auto; overflow-x: hidden; word-break: break-word;
      box-shadow: 0 24px 64px rgba(15, 23, 42, 0.2);
      animation: popupScaleIn 0.25s ease; text-align: center; position: relative;
    }
    .qty-popup-close {
      position: absolute; top: 0.75rem; right: 0.75rem;
      width: 32px; height: 32px; border-radius: 50%; border: none;
      background: rgba(0,0,0,0.05); color: #64748b; cursor: pointer; font-size: 1rem;
      display: flex; align-items: center; justify-content: center;
    }
    .qty-popup-close:hover { background: rgba(239,68,68,0.1); color: #ef4444; }
    .qty-popup-img { width: 100px; height: 100px; border-radius: 14px; object-fit: cover; margin: 0 auto 1rem; display: block; border: 2px solid #f0f0f0; }
    .qty-popup h3 { margin: 0 0 0.5rem; font-size: 1.05rem; color: #111827; }
    .qty-popup-price { font-size: 1.2rem; font-weight: 900; background: linear-gradient(135deg, #2B4C52, #4A7C84); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 1.25rem; }
    .qty-popup-controls { display: flex; align-items: center; justify-content: center; gap: 0.75rem; margin-bottom: 1.5rem; }
    .qty-popup-btn { width: 44px; height: 44px; border-radius: 12px; border: 2px solid #e2e8f0; background: white; cursor: pointer; font-size: 1.3rem; font-weight: 700; color: #334155; display: flex; align-items: center; justify-content: center; }
    .qty-popup-btn:hover { border-color: #2B4C52; color: #2B4C52; background: rgba(43, 76, 82,0.04); }
    .qty-popup-value { font-size: 1.6rem; font-weight: 800; color: #111827; min-width: 48px; text-align: center; }
    .qty-popup-add-btn { width: 100%; padding: 0.85rem; background: linear-gradient(135deg, #2B4C52, #4A7C84); color: white; border: none; border-radius: 12px; font-weight: 700; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 6px 16px rgba(43, 76, 82,0.25); }
    .qty-popup-add-btn:hover { transform: translateY(-1px); box-shadow: 0 10px 24px rgba(43, 76, 82,0.35); }
    @keyframes popupFadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes popupScaleIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
      .products-sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
      .products-sidebar.open { transform: translateX(0); }
      .products-main { margin-left: 0; min-width: 0; }
      .hamburger-btn { display: flex !important; }
      .top-header-inner { gap: 0.75rem; }
      .top-header-center { max-width: none; }
    }
    @media (max-width: 768px) {
      .top-header { padding: 0 1rem; }
      .top-header-inner { flex-wrap: wrap; height: auto; padding: 0.6rem 0; row-gap: 0.5rem; }
      .top-header-left { min-width: 0; }
      .top-header-title { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      .top-header-title h1 { font-size: 1.1rem; }
      .top-header-title p { display: none; }
      .top-header-center { order: 3; flex: 1 1 100%; max-width: 100%; margin: 0; min-width: 0; }
      .top-header-right { margin-left: auto; flex-shrink: 0; }
      .compact-login { display: inline-flex; }
      .g-signin-wrapper { display: none; }
      .content-area { padding: 1rem; }
      .products-grid { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem; }
      .product-card-image { height: 180px; }
      .header-profile-name { display: none; }
      .header-profile-arrow { display: none; }
    }
    @media (max-width: 640px) {
      .content-area { padding: 0.85rem; }
      .top-header-title h1 { font-size: 1.05rem; }
    }
    @media (max-width: 576px) {
      .content-area { padding: 0.75rem; }
      .products-grid { gap: 0.75rem; }
    }
    @media (max-width: 480px) {
      .products-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
      .product-card { min-width: 0; }
      .product-card-footer { flex-direction: column; }
      .skeleton-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .skeleton-card .skeleton-img { height: 130px; }
      .top-header-center { max-width: 100%; }
    }
    @media (max-width: 400px) {
      .content-area { padding: 0.75rem; }
      .top-header-title h1 { font-size: 1rem; }
      .header-icon-btn { width: 38px; height: 38px; }
      .hamburger-btn { width: 38px; height: 38px; }
      .header-profile-name, .header-profile-arrow { display: none; }
      .product-card-body { padding: 0.85rem; }
      .product-price { font-size: 1.1rem; }
      .product-card-image { height: 150px; }
      .header-icon-btn[title="Home"] { display: none; }
      .qv-close { width: 40px; height: 40px; }
      .filter-chips { flex-wrap: nowrap; overflow-x: auto; padding-bottom: 0.5rem; }
      .filter-chip { flex-shrink: 0; }
      .compact-login { display: inline-flex; }
      .g-signin-wrapper { display: none; }
      .top-header-inner { gap: 0.5rem; }
      .top-header-center { min-width: 0; }
      .top-header-title { white-space: nowrap; }
      .header-search-input { padding-left: 1.8rem; padding-right: 0.5rem; font-size: 0.75rem; }
      .qv-overlay { padding: 0.75rem; }
      .qv-gallery { padding: 0.75rem; }
      .qv-info { padding: 0.75rem; }
      .qv-info h2 { font-size: 1rem; }
      .qv-price { font-size: 1.3rem; }
      .qv-spec-grid { grid-template-columns: 1fr; }
      .skeleton-grid { gap: 0.75rem; }
      .top-header-left { gap: 0.5rem; }
      .top-header-title h1 { font-size: 0.9rem; }
      .header-search-input { font-size: 0.7rem; }
      .header-search-wrapper i { left: 0.7rem; font-size: 0.75rem; }
      .product-card-title { font-size: 0.85rem; }
      .product-card-desc { font-size: 0.72rem; }
      .product-price { font-size: 0.95rem; }
      .product-card-image { height: 130px; }
      .product-card-footer { padding: 0.5rem 0.85rem 0.85rem; }
      .product-card-footer .btn { font-size: 0.68rem; padding: 0.4rem 0.5rem; white-space: normal; word-break: break-word; }
      .filter-chip { padding: 0.4rem 0.75rem; font-size: 0.72rem; }
      .printing-banner { padding: 1.25rem !important; }
      .printing-banner h2 { font-size: 1.15rem !important; }
      .printing-banner > div:nth-child(2) > div:last-child { gap: 0.75rem !important; justify-content: center; }
      .printing-banner > div:nth-child(2) > div:last-child > div { padding: 0.5rem 0.7rem !important; }
      .printing-banner > div:nth-child(2) > div:last-child > div > div:first-child { font-size: 1.1rem !important; }
    }
    @media (max-width: 360px) {
      .top-header { padding: 0 0.5rem; }
      .top-header-center { display: none; }
      .top-header-left { gap: 0.35rem; }
      .top-header-title h1 { font-size: 0.8rem; }
      .hamburger-btn { width: 34px; height: 34px; }
      .header-icon-btn { width: 34px; height: 34px; }
      .compact-login { font-size: 0.55rem; gap: 0.15rem; }
      .compact-login i { font-size: 0.65rem; }
      .content-area { padding: 0.5rem; }
      .products-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.5rem; }
      .product-card { min-width: 0; }
      .product-card-body { min-width: 0; padding: 0.6rem 0.7rem; }
      .product-card-title { font-size: 0.8rem; word-break: break-word; }
      .product-card-desc { font-size: 0.68rem; word-break: break-word; }
      .product-price { font-size: 0.85rem; }
      .product-card-image { height: 100px; }
      .product-card-footer { min-width: 0; padding: 0.35rem 0.7rem 0.7rem; }
      .product-card-footer .btn { font-size: 0.6rem; padding: 0.3rem 0.35rem; white-space: normal; word-break: break-word; }
      .card-category-badge { padding: 0.15rem 0.4rem; font-size: 0.5rem; top: 6px; left: 6px; }
      .filter-chip { padding: 0.3rem 0.6rem; font-size: 0.65rem; }
      .skeleton-card .skeleton-img { height: 140px; }
      .skeleton-grid { gap: 0.5rem; }
      .qty-popup { padding: 1.25rem; }
      .printing-banner { padding: 0.85rem !important; }
      .printing-banner h2 { font-size: 0.95rem !important; }
      .printing-banner h2 i { font-size: 0.85rem; }
      .printing-banner > div:nth-child(2) { flex-direction: column; align-items: flex-start; }
      .printing-banner > div:nth-child(2) > div:last-child { gap: 0.5rem !important; justify-content: flex-start; flex-wrap: nowrap; }
      .printing-banner > div:nth-child(2) > div:last-child > div { padding: 0.4rem 0.5rem !important; }
      .printing-banner > div:nth-child(2) > div:last-child > div > div:first-child { font-size: 0.95rem !important; }
      .printing-banner > div:nth-child(2) > div:last-child > div > div:last-child { font-size: 0.6rem !important; }
      .qv-overlay { padding: 0.5rem; }
      .qv-gallery { padding: 0.75rem; }
      .qv-info { padding: 0.75rem; }
      .qv-info h2 { font-size: 0.95rem; }
      .qv-price { font-size: 1.2rem; }
      .qv-spec-grid { grid-template-columns: 1fr; }
      .modal-header { padding: 0.85rem 1rem; }
      .modal-body { padding: 1rem; }
      .modal-body p { font-size: 0.82rem; }
      .modal-contact-item { gap: 0.5rem; }
      .modal-contact-icon { width: 28px; height: 28px; font-size: 0.75rem; }
    }
    
    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 99; }
    .sidebar-overlay.active { display: block; }

    /* ========= QUICK VIEW MODAL ========= */
    .qv-overlay {
      display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(15, 23, 42, 0.6);
      z-index: 9999; align-items: center; justify-content: center;
      padding: 1.5rem; overflow-y: auto;
    }
    .qv-overlay.active { display: flex; }
    .qv-modal {
      background: white; border-radius: 18px;
      max-width: 900px; width: 100%; max-height: 90vh;
      overflow-y: auto; overflow-x: hidden;
      box-shadow: 0 24px 64px rgba(0,0,0,0.25);
      animation: qvSlideIn 0.35s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative; margin: auto;
    }
    @keyframes qvSlideIn {
      from { opacity: 0; transform: scale(0.95) translateY(20px); }
      to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .qv-close {
      position: absolute; top: 1rem; right: 1rem;
      width: 36px; height: 36px; border-radius: 50%;
      background: rgba(0,0,0,0.05); border: none;
      color: #64748b; cursor: pointer; font-size: 1.1rem;
      display: flex; align-items: center; justify-content: center;
      z-index: 10; transition: all 0.2s ease;
    }
    .qv-close:hover { background: rgba(239,68,68,0.1); color: #ef4444; }
    .qv-layout { display: grid; grid-template-columns: 1.3fr 1fr; min-height: 400px; }
    
    .qv-gallery {
      background: linear-gradient(135deg, #f0f4f8 0%, #e8edf2 100%); padding: 1.5rem;
      display: flex; flex-direction: column; gap: 1rem;
      position: sticky; top: 0; align-self: start;
    }
    .qv-main-img {
      width: 100%; aspect-ratio: 1; border-radius: 14px;
      overflow: hidden; background: white; display: flex;
      align-items: center; justify-content: center;
      box-shadow: 0 8px 24px rgba(0,0,0,0.08);
      border: 1px solid rgba(255,255,255,0.8);
      transition: box-shadow 0.3s ease;
    }
    .qv-main-img:hover { box-shadow: 0 12px 32px rgba(0,0,0,0.15); }
    .qv-main-img img { width: 100%; height: 100%; object-fit: contain; }
    
    .qv-info {
      padding: 1.5rem; display: flex; flex-direction: column; gap: 0.85rem;
    }
    .qv-badge {
      display: inline-flex; align-self: flex-start;
      padding: 0.25rem 0.75rem; background: var(--primary-bg);
      color: var(--primary); border-radius: 20px;
      font-size: 0.65rem; font-weight: 700;
    }
    .qv-info h2 { font-size: 1.35rem; font-weight: 800; color: var(--text-primary); line-height: 1.3; word-break: break-word; }
    .qv-price { font-size: 1.8rem; font-weight: 900; color: var(--primary); }
    .qv-desc { font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6; word-break: break-word; }
    .qv-spec-grid {
      display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;
      margin: 0.5rem 0;
    }
    .qv-spec-card {
      display: flex; align-items: center; gap: 0.5rem;
      padding: 0.45rem 0.65rem; background: var(--border-light);
      border-radius: 8px; transition: var(--transition);
    }
    .qv-spec-card:hover { background: var(--primary-bg); transform: translateX(2px); }
    .qv-spec-icon {
      width: 32px; height: 32px; border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      background: white; color: var(--primary); font-size: 0.78rem;
      flex-shrink: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .qv-spec-info { flex: 1; min-width: 0; }
    .qv-spec-label { display: block; font-size: 0.6rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
    .qv-spec-value { display: block; font-size: 0.72rem; font-weight: 600; color: var(--text-primary); margin-top: 0.05rem; word-break: break-word; }







    .float-panel-header {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 0.75rem;
    }
    .float-panel-header h4 { font-size: 0.78rem; font-weight: 700; color: var(--text-primary); }
    .float-panel-close {
      width: 24px; height: 24px; border-radius: 50%; border: none;
      background: rgba(0,0,0,0.05); cursor: pointer; font-size: 0.6rem;
      color: var(--text-muted); display: flex; align-items: center; justify-content: center;
    }
    .float-panel-close:hover { background: rgba(239,68,68,0.1); color: #ef4444; }
    .float-panel-items { display: flex; flex-direction: column; gap: 0.5rem; max-height: 200px; overflow-y: auto; }
    .float-panel-items::-webkit-scrollbar { width: 3px; }
    .float-panel-items::-webkit-scrollbar-thumb { background: rgba(43, 76, 82,0.2); border-radius: 4px; }
    .float-panel-item {
      display: flex; gap: 0.5rem; align-items: center;
      padding: 0.4rem; background: rgba(255,255,255,0.6);
      border-radius: 8px;
    }
    .float-panel-item img { width: 36px; height: 36px; border-radius: 6px; object-fit: cover; }
    .float-panel-item-info { flex: 1; min-width: 0; }
    .float-panel-item-name { font-size: 0.68rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .float-panel-item-qty { font-size: 0.6rem; color: var(--text-muted); }
    .float-panel-item-price { font-size: 0.65rem; font-weight: 700; color: var(--primary); }
    .float-panel-subtotal {
      display: flex; justify-content: space-between; align-items: center;
      padding: 0.65rem 0 0.5rem; margin-top: 0.5rem;
      border-top: 1px solid rgba(0,0,0,0.06);
    }
    .float-panel-subtotal span:first-child { font-size: 0.72rem; color: var(--text-muted); font-weight: 600; }
    .float-panel-subtotal span:last-child { font-size: 0.95rem; font-weight: 800; color: var(--text-primary); }
    .float-panel-checkout {
      width: 100%; padding: 0.7rem; border: none; border-radius: 10px;
      background: linear-gradient(135deg, #2B4C52, #4A7C84);
      color: white; font-weight: 700; font-size: 0.82rem;
      cursor: pointer; transition: var(--transition);
      display: flex; align-items: center; justify-content: center; gap: 0.4rem;
      box-shadow: 0 4px 14px rgba(43, 76, 82,0.25);
    }
    .float-panel-checkout:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(43, 76, 82,0.35); }
    .float-panel-empty { text-align: center; padding: 1rem; font-size: 0.75rem; color: var(--text-muted); }

    /* BOTTOM SECTIONS RESPONSIVE */
    @media (max-width: 768px) {
      .qv-layout { grid-template-columns: 1fr; min-height: 0; }
      .qv-gallery { padding: 0.85rem; position: static; background: linear-gradient(135deg, #f0f4f8 0%, #e8edf2 100%); }
      .qv-main-img { aspect-ratio: 16/10; }
      .qv-info { padding: 0.85rem 1rem; }
      .qv-info h2 { font-size: 1.1rem; }
      .qv-price { font-size: 1.4rem; }
      .qv-spec-grid { grid-template-columns: 1fr 1fr; gap: 0.4rem; }
      .qv-spec-card { padding: 0.45rem 0.6rem; }
      .qv-spec-icon { width: 28px; height: 28px; font-size: 0.7rem; }
    }
    @media (max-width: 576px) {
      .qv-overlay { align-items: flex-end; padding: 0; }
      .qv-modal { border-radius: 18px 18px 0 0; max-height: 88vh; margin: auto auto 0; }
      .qv-main-img { aspect-ratio: 1/1; max-height: 38vh; }
      .qv-close { top: 0.6rem; right: 0.6rem; }
    }
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 99999; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; padding: 1rem; overflow-y: auto; }
    .modal-overlay.open { display: flex; }
    .modal-box { background: white; border-radius: 16px; max-width: 600px; width: 100%; max-height: 85vh; overflow-y: auto; overflow-x: hidden; margin: auto; box-shadow: 0 24px 80px rgba(0,0,0,0.2); animation: modalIn 0.25s ease; }
    @keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; gap: 0.5rem; }
    .modal-header h2 { font-size: 1.1rem; font-weight: 700; color: #1a1a2e; display: flex; align-items: center; gap: 0.5rem; min-width: 0; }
    .modal-header h2 i { color: #2B4C52; }
    .modal-close { width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; transition: all 0.15s ease; }
    .modal-close:hover { border-color: #ef4444; color: #ef4444; }
    .modal-body { padding: 1.5rem; overflow-wrap: break-word; word-break: break-word; }
    .modal-body p { font-size: 0.9rem; color: #475569; line-height: 1.7; margin-bottom: 0.75rem; }
    .modal-body p:last-child { margin-bottom: 0; }
    .modal-contact-item { display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9; }
    .modal-contact-item:last-child { border-bottom: none; }
    .modal-contact-icon { width: 36px; height: 36px; border-radius: 10px; background: rgba(43, 76, 82,0.08); color: #2B4C52; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.9rem; }
    .modal-contact-label { font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
    .modal-contact-value { font-size: 0.9rem; font-weight: 600; color: #1a1a2e; margin-top: 0.1rem; }
    .modal-faq { border: 1px solid #e8ecf1; border-radius: 12px; margin-bottom: 0.75rem; overflow: hidden; }
    .modal-faq summary { padding: 1rem 1.25rem; font-weight: 600; font-size: 0.88rem; color: #1a1a2e; cursor: pointer; display: flex; justify-content: space-between; align-items: center; list-style: none; }
    .modal-faq summary::-webkit-details-marker { display: none; }
    .modal-faq summary i { color: #64748b; font-size: 0.75rem; transition: transform 0.2s; }
    .modal-faq[open] summary i { transform: rotate(180deg); }
    .modal-faq-answer { padding: 0 1.25rem 1rem; font-size: 0.85rem; color: #475569; line-height: 1.7; border-top: 1px solid #f1f5f9; padding-top: 0.75rem; overflow-wrap: break-word; word-break: break-word; }
    @media (max-width: 480px) {
      .modal-overlay { padding: 0.75rem; }
    }
    .sidebar-submenu {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
      opacity: 0;
    }
    .sidebar-submenu.open {
      max-height: 400px;
      opacity: 1;
    }
    .sidebar-submenu-item {
      display: flex;
      align-items: center;
      gap: 0.65rem;
      padding: 0.5rem 1.25rem 0.5rem 2.8rem;
      margin: 0 0.6rem;
      border-radius: 8px;
      font-size: 0.78rem;
      font-weight: 500;
      color: #5a5a6a;
      text-decoration: none;
      transition: var(--transition);
      cursor: pointer;
      border: none;
      background: none;
      width: calc(100% - 1.2rem);
      text-align: left;
      font-family: var(--font);
    }
    .sidebar-submenu-item i {
      width: 16px;
      text-align: center;
      font-size: 0.75rem;
      color: #4A7C84;
      transition: var(--transition);
    }
    .sidebar-submenu-item:hover {
      background: var(--sidebar-hover);
      color: var(--primary);
    }
    .sidebar-submenu-item:hover i { color: var(--primary); }
    .sidebar-submenu-item.active {
      background: var(--sidebar-active-bg);
      color: var(--primary);
      font-weight: 600;
    }
    .sidebar-submenu-item.active i { color: var(--primary); }
    .sidebar-menu-toggle {
      cursor: pointer;
      user-select: none;
    }
    .sidebar-menu-toggle .toggle-arrow {
      float: right;
      font-size: 0.75rem;
      transition: transform 0.3s ease;
    }
    .sidebar-menu-toggle.open .toggle-arrow {
      transform: rotate(180deg);
    }
    .sidebar-badge {
      margin-left: auto;
      background: #ef4444;
      color: white;
      font-size: 0.6rem;
      font-weight: 700;
      min-width: 18px;
      height: 18px;
      border-radius: 9px;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 0 0.3rem;
      line-height: 1;
    }
    .sidebar-badge.show { display: flex; }
    .header-notif-wrapper { position: relative; }
    .notif-bell-dot { position: absolute; top: 5px; right: 5px; width: 8px; height: 8px; border-radius: 50%; background: #ef4444; border: 2px solid white; }
    .notif-dropdown { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.55); z-index: 10000; align-items: center; justify-content: center; padding: 1rem; overflow-y: auto; }
    .notif-dropdown.active { display: flex; animation: notifFadeIn 0.25s ease; }
    .notif-modal-box { background: white; border-radius: 20px; max-width: 480px; width: 100%; max-height: calc(100vh - 2rem); display: flex; flex-direction: column; overflow: hidden; margin: auto; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.2); animation: notifScaleIn 0.25s ease; }
    .notif-dropdown-header { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; font-weight: 700; color: #0f172a; flex-shrink: 0; min-width: 0; }
    .notif-dropdown-header > span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .notif-mark-all-btn { background: none; border: none; color: #2B4C52; font-size: 0.72rem; font-weight: 600; cursor: pointer; padding: 0.2rem 0.5rem; border-radius: 6px; flex-shrink: 0; }
    .notif-mark-all-btn:hover { background: rgba(43,76,82,0.08); }
    .notif-close { width: 32px; height: 32px; border-radius: 50%; border: none; background: rgba(0,0,0,0.05); color: #64748b; cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .notif-close:hover { background: rgba(239,68,68,0.1); color: #ef4444; }
    .notif-dropdown-list { overflow-y: auto; flex: 1; min-height: 0; }
    @keyframes notifFadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes notifScaleIn { from { opacity: 0; transform: scale(0.92); } to { opacity: 1; transform: scale(1); } }
    @media (max-width: 480px) {
      .notif-dropdown { padding: 0.75rem; }
    }
    .notif-item { display: flex; gap: 0.7rem; padding: 0.75rem 1rem; border-bottom: 1px solid #f8fafc; cursor: pointer; transition: background 0.15s; text-decoration: none; color: inherit; align-items: flex-start; }
    .notif-item:hover { background: #f8fafc; }
    .notif-item.unread { background: rgba(43,76,82,0.04); }
    .notif-item.unread:hover { background: rgba(43,76,82,0.08); }
    .notif-item-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0; }
    .notif-item-icon.orange { background: rgba(245,158,11,0.12); color: #d97706; }
    .notif-item-icon.green { background: rgba(16,185,129,0.12); color: #059669; }
    .notif-item-icon.blue { background: rgba(43,76,82,0.12); color: #2B4C52; }
    .notif-item-icon.purple { background: rgba(139,92,246,0.12); color: #7c3aed; }
    .notif-item-icon.red { background: rgba(239,68,68,0.12); color: #dc2626; }
    .notif-item-body { flex: 1; min-width: 0; }
    .notif-item-title { font-size: 0.82rem; font-weight: 600; color: #0f172a; line-height: 1.3; }
    .notif-item.unread .notif-item-title { font-weight: 700; }
    .notif-item-text { font-size: 0.75rem; color: #64748b; margin-top: 0.1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .notif-item-time { font-size: 0.68rem; color: #94a3b8; margin-top: 0.2rem; }
    .notif-unread-dot { width: 8px; height: 8px; border-radius: 50%; background: #2B4C52; flex-shrink: 0; margin-top: 4px; }
    .notif-loading, .notif-empty { text-align: center; padding: 2rem; color: #94a3b8; font-size: 0.82rem; }
    .notif-error { text-align: center; padding: 1rem; color: #dc2626; font-size: 0.78rem; }
    @media (max-width: 768px) {
      .hamburger-btn, .header-icon-btn { min-width: 44px; min-height: 44px; }
      .modal-close { min-width: 44px; min-height: 44px; }
      .sidebar-menu-item { padding: 0.75rem 1.25rem; }
      .notif-mark-all-btn { min-height: 44px; padding: 0.5rem 1rem; }
      .notif-close { min-width: 44px; min-height: 44px; }
    }
  </style>
</head>
<body>
  <div class="dashboard-wrapper">
    <!-- ========= SIDEBAR ========= -->
    <aside class="products-sidebar" id="sidebar">
      <div class="sidebar-brand">
        <img src="../assets/logo.png" alt="Inkzion" class="sidebar-brand-img">
        <div class="sidebar-brand-text">
          <span class="sidebar-brand-name">INKZION</span>
          <span class="sidebar-brand-sub">Spectrum Ads</span>
        </div>
      </div>
      
      <div class="sidebar-profile">
        <div class="sidebar-avatar"><?php if ($loggedIn && $userProfilePhoto): ?><img src="<?php echo htmlspecialchars(profilePhotoUrl($userProfilePhoto)); ?>" alt=""><?php elseif ($loggedIn): ?><?php echo htmlspecialchars($userInitials); ?><?php else: ?><i class="fas fa-user" style="font-size:0.7rem;"></i><?php endif; ?></div>
        <div class="sidebar-profile-info">
          <h4><?php echo $loggedIn ? htmlspecialchars($userName) : 'Guest'; ?></h4>
          <p><?php echo $loggedIn ? ($isSeller ? 'admin' : 'Customer') : 'Not logged in'; ?></p>
        </div>
      </div>
      
      <nav class="sidebar-menu">
        <div class="sidebar-section-title">Shop</div>
        <a href="store-product.php" class="sidebar-menu-item active"><i class="fas fa-box"></i> All Products</a>
        <?php if ($loggedIn): ?>
        
        <a href="messages.php" class="sidebar-menu-item"><i class="fas fa-comments"></i> Messages<span class="sidebar-badge" id="sidebar-msg-badge"></span></a>
        <div class="sidebar-section-title" style="padding-top:0.5rem;">Orders</div>
        <a href="my-orders.php" class="sidebar-menu-item"><i class="fas fa-box"></i> My Orders<span class="sidebar-badge" id="sidebar-orders-badge"></span></a>
        <a href="my-requests.php" class="sidebar-menu-item"><i class="fas fa-clipboard-list"></i> My Requests<span class="sidebar-badge" id="sidebar-requests-badge"></span></a>
        <div class="sidebar-section-title" style="padding-top:0.5rem;">Account</div>
        <div class="sidebar-menu-item sidebar-menu-toggle open" id="accountToggle" onclick="toggleAccountMenu()">
          <i class="fas fa-user-circle"></i> My Profile
          <i class="fas fa-chevron-down toggle-arrow"></i>
        </div>
        <div class="sidebar-submenu open" id="accountSubmenu">
          <a href="profile.php?section=profile" class="sidebar-submenu-item"><i class="fas fa-user-edit"></i> Edit Profile</a>
          <a href="profile.php?section=addresses" class="sidebar-submenu-item"><i class="fas fa-map-marker-alt"></i> My Addresses</a>
        </div>
        <?php endif; ?>
      </nav>
      
      <div class="sidebar-footer">
        <button class="sidebar-footer-item" onclick="openModal('contact')"><i class="fas fa-envelope"></i> Contact</button>
        <button class="sidebar-footer-item" onclick="openModal('help')"><i class="fas fa-question-circle"></i> Help Center</button>
      </div>
    </aside>
    
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- ========= MAIN CONTENT ========= -->
    <main class="products-main">
      <!-- === TOP HEADER === -->
      <header class="top-header">
        <div class="top-header-inner">
          <div class="top-header-left">
            <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
            <?php if (!$loggedIn): ?>
            <a href="../login.php" class="compact-login"><i class="fas fa-sign-in-alt"></i> Login</a>
            <?php endif; ?>
            <div class="top-header-title">
              <h1>Products</h1>
              <p>Premium printing services & products</p>
            </div>
          </div>
          
          <div class="top-header-center">
            <div class="header-search-wrapper">
              <i class="fas fa-search"></i>
              <input type="text" id="product-search" class="header-search-input" placeholder="Search products..." aria-label="Search products">
            </div>
          </div>
          
          <div class="top-header-right">
            <a href="../index.php" class="header-icon-btn" title="Home">
              <i class="fas fa-home"></i>
            </a>
            <?php if ($loggedIn): ?>
            <div class="header-notif-wrapper" id="notifWrapper">
              <button class="header-icon-btn" onclick="toggleNotifDropdown()" title="Notifications" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <span class="notif-bell-dot" id="notifBellDot" style="display:none;"></span>
              </button>
              <div class="notif-dropdown" id="notifDropdown" onclick="if(event.target===this)closeNotifDropdown()">
                <div class="notif-modal-box">
                  <div class="notif-dropdown-header">
                    <span>Notifications</span>
                    <button class="notif-mark-all-btn" id="notifMarkAll" onclick="markAllNotifRead()">Mark all read</button>
                    <button class="notif-close" onclick="closeNotifDropdown()" aria-label="Close notifications"><i class="fas fa-times"></i></button>
                  </div>
                  <div class="notif-dropdown-list" id="notifList">
                    <div class="notif-loading">Loading...</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="header-profile-dropdown-wrapper">
              <button class="header-profile-btn" onclick="toggleProfileDropdown()" aria-label="Account menu">
                <div class="header-profile-avatar"><?php if ($userProfilePhoto): ?><img src="<?php echo htmlspecialchars(profilePhotoUrl($userProfilePhoto)); ?>" alt=""><?php else: ?><?php echo htmlspecialchars($userInitials); ?><?php endif; ?></div>
                <span class="header-profile-name"><?php echo htmlspecialchars($userName); ?></span>
                <i class="fas fa-chevron-down header-profile-arrow"></i>
              </button>
              <div class="header-profile-dropdown-menu" id="profileDropdown">
                <a href="../logout.php" class="header-profile-dropdown-item danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
              </div>
            </div>
            <?php else: ?>
            <div class="g-signin-wrapper">
              <div id="g_id_onload"
                   data-client_id="1061589476506-s82uc7lcqm99jnmq41c8nj278cug5jjp.apps.googleusercontent.com"
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
        </div>
      </header>
      
      <!-- === CONTENT AREA === -->
      <div class="content-area">
        <!-- Printing Services Banner -->
        <?php if ($isPrintingServices): ?>
        <div class="printing-banner" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 1.5rem 2rem; margin-bottom: 1.5rem; color: white; position: relative; overflow: hidden;">
          <div style="position: absolute; top: -50%; right: -10%; width: 300px; height: 300px; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); border-radius: 50%;"></div>
          <div style="position: relative; z-index: 1; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
              <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fas fa-print"></i>
                Printing Services
              </h2>
              <p style="opacity: 0.95; max-width: 700px; font-size: 0.9rem;">
                Professional printing solutions for all your business needs. From business cards to marketing materials, we deliver exceptional quality with fast turnaround times.
              </p>
            </div>
            <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
              <div style="text-align: center; background: rgba(255,255,255,0.15); padding: 0.75rem 1.25rem; border-radius: 12px; backdrop-filter: blur(10px);">
                <div style="font-size: 1.5rem; font-weight: 800;"><?php echo array_sum(array_map('count', $productsByCategory)); ?></div>
                <div style="font-size: 0.75rem; opacity: 0.9;">Products</div>
              </div>
              <div style="text-align: center; background: rgba(255,255,255,0.15); padding: 0.75rem 1.25rem; border-radius: 12px; backdrop-filter: blur(10px);">
                <div style="font-size: 1.5rem; font-weight: 800;">4.8</div>
                <div style="font-size: 0.75rem; opacity: 0.9;">Rating</div>
              </div>
              <div style="text-align: center; background: rgba(255,255,255,0.15); padding: 0.75rem 1.25rem; border-radius: 12px; backdrop-filter: blur(10px);">
                <div style="font-size: 1.5rem; font-weight: 800;">3-5</div>
                <div style="font-size: 0.75rem; opacity: 0.9;">Days Delivery</div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
        
        <div class="products-toolbar">
          <div class="filter-chips" id="filter-section">
            <button class="filter-chip active" data-filter="all"><i class="fas fa-th"></i> All Products</button>
            <?php foreach ($categoryOptions as $catName => $catId): ?>
            <button class="filter-chip" data-filter="<?php echo htmlspecialchars($catName); ?>"><?php echo htmlspecialchars($catName); ?></button>
            <?php endforeach; ?>
          </div>
        </div>
        

        <!-- SKELETON LOADING GRID -->
        <div class="skeleton-grid" id="skeletonGrid">
          <div class="skeleton-card"><div class="skeleton skeleton-img"></div><div class="skeleton-body"><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line short"></div><div class="skeleton skeleton-line medium"></div><div class="skeleton skeleton-line price"></div></div><div class="skeleton-footer"><div class="skeleton skeleton-btn"></div><div class="skeleton skeleton-btn"></div></div></div>
          <div class="skeleton-card"><div class="skeleton skeleton-img"></div><div class="skeleton-body"><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line short"></div><div class="skeleton skeleton-line medium"></div><div class="skeleton skeleton-line price"></div></div><div class="skeleton-footer"><div class="skeleton skeleton-btn"></div><div class="skeleton skeleton-btn"></div></div></div>
          <div class="skeleton-card"><div class="skeleton skeleton-img"></div><div class="skeleton-body"><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line short"></div><div class="skeleton skeleton-line medium"></div><div class="skeleton skeleton-line price"></div></div><div class="skeleton-footer"><div class="skeleton skeleton-btn"></div><div class="skeleton skeleton-btn"></div></div></div>
          <div class="skeleton-card"><div class="skeleton skeleton-img"></div><div class="skeleton-body"><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line short"></div><div class="skeleton skeleton-line medium"></div><div class="skeleton skeleton-line price"></div></div><div class="skeleton-footer"><div class="skeleton skeleton-btn"></div><div class="skeleton skeleton-btn"></div></div></div>
          <div class="skeleton-card"><div class="skeleton skeleton-img"></div><div class="skeleton-body"><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line short"></div><div class="skeleton skeleton-line medium"></div><div class="skeleton skeleton-line price"></div></div><div class="skeleton-footer"><div class="skeleton skeleton-btn"></div><div class="skeleton skeleton-btn"></div></div></div>
          <div class="skeleton-card"><div class="skeleton skeleton-img"></div><div class="skeleton-body"><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line short"></div><div class="skeleton skeleton-line medium"></div><div class="skeleton skeleton-line price"></div></div><div class="skeleton-footer"><div class="skeleton skeleton-btn"></div><div class="skeleton skeleton-btn"></div></div></div>
        </div>

        <div class="products-grid" id="products-container" style="display:none;">
          <?php foreach ($productsByCategory as $category => $products): ?>
            <?php foreach ($products as $product): ?>
            <?php
              $imageSrc = htmlspecialchars($product['image_url'] ?? ($product['image'] ?? 'assets/logo.png'), ENT_QUOTES, 'UTF-8');
              if (strpos($imageSrc, '../../uploads/') === 0) $imageSrc = substr($imageSrc, 3);
            ?>
            <article class="product-card" data-category="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>"
              data-qv-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
              data-qv-desc="<?php echo htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
              data-qv-image="<?php echo $imageSrc; ?>"
              data-qv-id="<?php echo htmlspecialchars($product['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
              <div class="product-card-image">
                <span class="card-category-badge"><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></span>
                <a href="<?php echo $imageSrc; ?>" class="qv-trigger">
                  <img src="<?php echo $imageSrc; ?>" onerror="this.src='assets/products-demo.jpg'" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                </a>
                <div class="card-quick-view">
                  <a href="<?php echo $imageSrc; ?>" class="card-quick-view-btn qv-trigger"><i class="fas fa-eye"></i> Quick View</a>
                </div>
              </div>
              <div class="product-card-body">
                <h3 class="product-card-title"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="product-card-desc"><?php echo htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <span class="card-stock in-stock" style="margin-bottom:0.75rem;display:inline-block;"><span class="stock-dot"></span> In Stock</span>
              </div>
              <div class="product-card-footer">
                <?php if ($isSeller && $useDbProducts && !empty($product['id'])): ?>
                <a href="admin/products.php" class="btn btn-outline btn-sm" style="flex:1;justify-content:center;"><i class="fas fa-edit"></i> Manage</a>
                <?php else: ?>
                <a href="messages.php<?= !empty($product['id']) ? '?product_id='.$product['id'] : ''; ?>" class="btn btn-primary btn-sm" style="flex:1;justify-content:center;" onclick="<?php echo $loggedIn ? '' : 'alert(\'Please login or sign up first.\'); return false;'; ?>"><i class="fas fa-comments"></i> Chat</a>
                <?php endif; ?>
              </div>
            </article>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>

        </div>
    </main>
  </div>

  <!-- PRODUCT QUICK VIEW MODAL -->
  <div class="qv-overlay" id="qvOverlay">
    <div class="qv-modal" onclick="event.stopPropagation()">
      <button class="qv-close" onclick="closeQuickView()" aria-label="Close"><i class="fas fa-times"></i></button>
      <div class="qv-layout">
        <div class="qv-gallery">
          <div class="qv-main-img">
            <img id="qvMainImg" src="" onerror="this.src='assets/products-demo.jpg'" alt="">
          </div>
        </div>
        <div class="qv-info">
          <span class="qv-badge" id="qvBadge"></span>
          <h2 id="qvName"></h2>

          <p class="qv-desc" id="qvDesc"></p>
          <!-- SPECIFICATIONS DASHBOARD CARDS -->
          <div class="qv-spec-grid" id="qvSpecs">
            <div class="qv-spec-card">
              <div class="qv-spec-icon"><i class="fas fa-tag"></i></div>
              <div class="qv-spec-info">
                <span class="qv-spec-label">Category</span>
                <span class="qv-spec-value" id="qvCategory">-</span>
              </div>
            </div>
            <div class="qv-spec-card">
              <div class="qv-spec-icon"><i class="fas fa-ruler"></i></div>
              <div class="qv-spec-info">
                <span class="qv-spec-label">Available Sizes</span>
                <span class="qv-spec-value">Custom (Any Size)</span>
              </div>
            </div>
            <div class="qv-spec-card">
              <div class="qv-spec-icon"><i class="fas fa-palette"></i></div>
              <div class="qv-spec-info">
                <span class="qv-spec-label">Available Colors</span>
                <span class="qv-spec-value">Full CMYK + Custom PMS</span>
              </div>
            </div>
            <div class="qv-spec-card">
              <div class="qv-spec-icon"><i class="fas fa-check-circle"></i></div>
              <div class="qv-spec-info">
                <span class="qv-spec-label">Stock Status</span>
                <span class="qv-spec-value" id="qvStock" style="color:var(--success);">â— In Stock</span>
              </div>
            </div>
          </div>


          <!-- RELATED PRODUCTS SECTION -->


        </div>
      </div>
    </div>
  </div>

  <script>
    const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('open');
      document.getElementById('sidebarOverlay').classList.toggle('active');
    }

    function showToast(msg, type) {
      type = type || 'info';
      var el = document.createElement('div');
      el.textContent = msg;
      el.style.cssText = 'position:fixed;bottom:2rem;left:50%;transform:translateX(-50%);padding:0.85rem 1.8rem;border-radius:12px;font-size:0.9rem;font-weight:500;z-index:9999;box-shadow:0 8px 32px rgba(0,0,0,0.3);color:white;transition:opacity 0.3s,transform 0.3s;max-width:90%;text-align:center;';
      if (type === 'success') el.style.background = 'rgba(5,150,105,0.9)';
      else if (type === 'error') el.style.background = 'rgba(239,68,68,0.9)';
      else el.style.background = 'rgba(59,130,246,0.9)';
      document.body.appendChild(el);
      setTimeout(function(){ el.style.opacity = '0'; el.style.transform = 'translateX(-50%) translateY(20px)'; setTimeout(function(){ el.remove(); }, 300); }, 3000);
    }

    // Search
    const searchInput = document.getElementById('product-search');
    const productCards = document.querySelectorAll('.product-card');
    searchInput.addEventListener('input', function() {
      const term = this.value.toLowerCase();
      productCards.forEach(card => {
        const title = card.querySelector('.product-card-title').textContent.toLowerCase();
        const desc = card.querySelector('.product-card-desc').textContent.toLowerCase();
        card.style.display = (title.includes(term) || desc.includes(term)) ? '' : 'none';
      });
    });

    // Filter
    document.querySelectorAll('.filter-chip').forEach(chip => {
      chip.addEventListener('click', function() {
        document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        const filter = this.dataset.filter;
        productCards.forEach(card => {
          card.style.display = (filter === 'all' || card.dataset.category === filter) ? '' : 'none';
        });
      });
    });

    // Quick View Modal — event delegation
    document.addEventListener('click', function(e) {
      const trigger = e.target.closest('.qv-trigger');
      if (!trigger) return;
      e.preventDefault();
      const card = trigger.closest('.product-card');
      if (!card) return;

      const category = card.dataset.category;
      const name = card.dataset.qvName;
      const desc = card.dataset.qvDesc;
      const image = card.dataset.qvImage;
      const productId = card.dataset.qvId;

      var qvImg = document.getElementById('qvMainImg');
      qvImg.src = image;
      qvImg.alt = name;
      qvImg.onerror = function() { this.src = 'assets/products-demo.jpg'; };
      document.getElementById('qvBadge').textContent = category;
      document.getElementById('qvName').textContent = name;
      document.getElementById('qvDesc').textContent = desc || '';
      document.getElementById('qvCategory').textContent = category;
      document.getElementById('qvStock').textContent = '\u25CF In Stock';
      document.getElementById('qvStock').style.color = 'var(--success)';

      document.getElementById('qvOverlay').classList.add('active');
      document.body.style.overflow = 'hidden';

    });

    // Close quick view — overlay backdrop, close button, or ESC
    function closeQuickView() {
      document.getElementById('qvOverlay').classList.remove('active');
      document.body.style.overflow = '';
    }

    document.getElementById('qvOverlay').addEventListener('click', function(e) {
      if (e.target === this) closeQuickView();
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && document.getElementById('qvOverlay').classList.contains('active')) closeQuickView();
    });

    document.addEventListener('DOMContentLoaded', function() {
      // Skeleton loading simulation
      const skeletonGrid = document.getElementById('skeletonGrid');
      const productsContainer = document.getElementById('products-container');
      
      // Show skeleton for minimum 800ms to demonstrate the loading state
      const minLoadTime = 800;
      const startTime = Date.now();
      
      // Simulate loading delay (in production, this would be an actual API call)
      setTimeout(() => {
        const elapsed = Date.now() - startTime;
        const remainingTime = Math.max(0, minLoadTime - elapsed);
        
        setTimeout(() => {
          // Hide skeleton, show products with animation
          skeletonGrid.style.display = 'none';
          productsContainer.style.display = 'grid';
          productsContainer.style.animation = 'fadeInUp 0.5s ease';
        }, remainingTime);
      }, 100);
      
    });

    // ========= RELATED PRODUCTS SLIDER =========
    const allProductsByCategory = {};
    document.querySelectorAll('.product-card').forEach(card => {
      const cat = card.dataset.category;
      if (!allProductsByCategory[cat]) allProductsByCategory[cat] = [];
      const title = card.querySelector('.product-card-title').textContent;
      const img = card.querySelector('.product-card-image img').src;
      const desc = card.querySelector('.product-card-desc').textContent;
      allProductsByCategory[cat].push({ name: title, image: img, description: desc });
    });



    async function handleGoogleCredential(response) {
      try {
        const res = await fetch('../api/google-auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ credential: response.credential, csrf_token: CSRF_TOKEN })
        });
        const data = await res.json();
        if (data.ok) {
          window.location.href = data.needs_profile ? '../index.php?complete_profile=1' : data.redirect;
        } else {
          console.error('Google auth error:', data);
          showToast(data.error || 'Sign-in failed. Please try again.', 'error');
        }
      } catch (e) {
        console.error('Google auth exception:', e);
        showToast('Sign-in failed. Please try again.', 'error');
      }
    }

    function openModal(type) {
      const overlay = document.getElementById('modalOverlay');
      const title = document.getElementById('modalTitle');
      const body = document.getElementById('modalBody');
      overlay.classList.add('open');
      body.innerHTML = '<div style="text-align:center;padding:2rem;"><i class="fas fa-spinner fa-pulse" style="font-size:1.5rem;color:#2B4C52;"></i><p style="margin-top:0.75rem;color:#64748b;">Loading...</p></div>';
      fetch('../api/get-content.php?section=' + (type === 'contact' ? 'contact_info' : 'help_center'))
        .then(r => r.json())
        .then(json => {
          if (!json.success) { body.innerHTML = '<p style="color:#ef4444;">Failed to load content.</p>'; return; }
          const d = json.data;
          if (type === 'contact') {
            const meta = d.meta || {};
            title.innerHTML = '<i class="fas fa-envelope"></i> ' + (d.title || 'Contact Us');
            body.innerHTML =
              '<p>' + (d.content || '') + '</p>' +
              '<div class="modal-contact-item"><div class="modal-contact-icon"><i class="fas fa-map-marker-alt"></i></div><div><div class="modal-contact-label">Address</div><div class="modal-contact-value">' + (meta.address || 'N/A') + '</div></div></div>' +
              '<div class="modal-contact-item"><div class="modal-contact-icon"><i class="fas fa-phone"></i></div><div><div class="modal-contact-label">Phone</div><div class="modal-contact-value">' + (meta.phone || 'N/A') + '</div></div></div>' +
              '<div class="modal-contact-item"><div class="modal-contact-icon"><i class="fas fa-envelope"></i></div><div><div class="modal-contact-label">Email</div><div class="modal-contact-value">' + (meta.email || 'N/A') + '</div></div></div>';
          } else {
            const faqs = d.meta && d.meta.faqs ? d.meta.faqs : [];
            title.innerHTML = '<i class="fas fa-question-circle"></i> ' + (d.title || 'Help Center');
            let html = d.subtitle ? '<p style="margin-bottom:1.25rem;">' + esc(d.subtitle) + '</p>' : '';
            if (d.content) html += '<div style="margin-bottom:1.25rem;padding:1rem;background:rgba(43, 76, 82,0.04);border-radius:12px;border:1px solid rgba(43, 76, 82,0.08);"><p style="font-size:0.88rem;color:#475569;">' + esc(d.content) + '</p></div>';
            if (faqs.length) {
              faqs.forEach((f, i) => {
                html += '<details class="modal-faq"' + (i === 0 ? ' open' : '') + '><summary>' + esc(f.question || '') + ' <i class="fas fa-chevron-down"></i></summary><div class="modal-faq-answer">' + esc(f.answer || '') + '</div></details>';
              });
            } else {
              html += '<div style="text-align:center;padding:2rem;color:#64748b;"><i class="fas fa-question-circle" style="font-size:2.5rem;display:block;margin-bottom:0.75rem;color:rgba(43, 76, 82,0.15);"></i><p>No FAQs yet. Check back soon.</p></div>';
            }
            body.innerHTML = html;
          }
        })
        .catch(() => { body.innerHTML = '<p style="color:#ef4444;">Failed to load. Please try again.</p>'; });
    }

    function closeModal() {
      document.getElementById('modalOverlay').classList.remove('open');
    }

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') { closeModal(); closeNotifDropdown(); }
    });

    function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    function toggleProfileDropdown() {
      document.getElementById('profileDropdown').classList.toggle('active');
    }

    // Close the profile dropdown if the user clicks outside of it
    window.onclick = function(event) {
      if (!event.target.matches('.header-profile-btn') && !event.target.closest('.header-profile-dropdown-wrapper')) {
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileDropdown && profileDropdown.classList.contains('active')) {
          profileDropdown.classList.remove('active');
        }
      }
    }
    function toggleAccountMenu() {
      const toggle = document.getElementById('accountToggle');
      const submenu = document.getElementById('accountSubmenu');
      if (toggle && submenu) {
        toggle.classList.toggle('open');
        submenu.classList.toggle('open');
      }
    }
    function toggleNotifDropdown() {
      var dd = document.getElementById('notifDropdown');
      if (!dd) return;
      var wasActive = dd.classList.contains('active');
      dd.classList.toggle('active');
      if (!wasActive) loadNotifs();
    }
    function closeNotifDropdown() { var dd = document.getElementById('notifDropdown'); if (dd) dd.classList.remove('active'); }
    var notifFetching = false;
    function loadNotifs() {
      if (notifFetching) return;
      notifFetching = true;
      var list = document.getElementById('notifList');
      if (!list) { notifFetching = false; return; }
      list.innerHTML = '<div class="notif-loading">Loading...</div>';
      fetch('../api/notifications.php?limit=20').then(function(r){return r.json();}).then(function(d){
        notifFetching = false;
        if (!d.success) { list.innerHTML = '<div class="notif-error">' + (d.error ? esc(d.error) : 'Failed to load') + '</div>'; return; }
        var notifs = d.notifications || [];
        if (!notifs.length) { list.innerHTML = '<div class="notif-empty">No notifications yet</div>'; updateNotifBellDot(0); return; }
        var html = '';
        notifs.forEach(function(n){ html += renderNotifItem(n); });
        list.innerHTML = html;
        updateNotifBellDot(d.unread_count);
      }).catch(function(){ notifFetching = false; list.innerHTML = '<div class="notif-error">Connection error. Check console for details.</div>'; });
    }
    function renderNotifItem(n) {
      var icon = notifTypeIcon(n.related_type || n.type);
      var link = notifTypeLinkCustomer(n);
      var timeAgo = notifTimeAgo(n.created_at);
      var unread = !n.is_read;
      return '<a href="'+link+'" class="notif-item'+(unread?' unread':'')+'" onclick="notifItemClick('+n.id+',\''+link+'\')">'+
        '<div class="notif-item-icon '+icon.color+'">'+icon.icon+'</div>'+
        '<div class="notif-item-body"><div class="notif-item-title">'+esc(n.title)+'</div>'+
        '<div class="notif-item-text">'+esc(n.body)+'</div><div class="notif-item-time">'+timeAgo+'</div></div>'+
        (unread?'<div class="notif-unread-dot"></div>':'')+'</a>';
    }
    function notifTypeIcon(t) {
      if (t==='order') return {icon:'<i class="fas fa-shopping-cart"></i>',color:'orange'};
      if (t==='order_proposal') return {icon:'<i class="fas fa-file-invoice"></i>',color:'green'};
      if (t==='custom_request') return {icon:'<i class="fas fa-paint-brush"></i>',color:'purple'};
      if (t==='chat') return {icon:'<i class="fas fa-comment-dots"></i>',color:'blue'};
      return {icon:'<i class="fas fa-bell"></i>',color:'blue'};
    }
    function notifTypeLinkCustomer(n) {
      var rt=n.related_type, ri=n.related_id;
      if (rt==='order'&&ri) return 'order-tracking.php?id='+ri;
      if (rt==='order_proposal'&&ri) return 'order-form.php?id='+ri;
      if (rt==='custom_request'&&ri) return 'my-requests.php';
      if (rt==='chat'&&ri) return 'messages.php?conversation='+ri;
      return '#';
    }
    function notifTimeAgo(ds) {
      var n=new Date(),d=new Date(ds),diff=Math.floor((n-d)/1000);
      if(diff<60)return'Just now';if(diff<3600)return Math.floor(diff/60)+'m ago';
      if(diff<86400)return Math.floor(diff/3600)+'h ago';if(diff<172800)return'Yesterday';
      return d.toLocaleDateString('en-US',{month:'short',day:'numeric'});
    }
    function notifItemClick(id, link) {
      fetch('../api/notifications.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'mark_read',id:id})}).catch(function(){});
      closeNotifDropdown();
      updateSidebarBadges();
    }
    function markAllNotifRead() {
      fetch('../api/notifications.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'mark_read_all'})})
      .then(function(r){return r.json();}).then(function(d){if(d.success){loadNotifs();updateSidebarBadges();}}).catch(function(){});
    }
    function updateNotifBellDot(count) {
      var dot = document.getElementById('notifBellDot');
      if (dot) dot.style.display = count > 0 ? 'block' : 'none';
    }
    function updateSidebarBadges() {
      fetch('../api/notif-counts.php').then(r=>r.json()).then(d=>{
        const sb = (id, c) => { const b = document.getElementById(id); if(b){ b.textContent = c||''; b.classList.toggle('show', c>0); } };
        sb('sidebar-msg-badge', d.chat);
        sb('sidebar-orders-badge', d.order);
        sb('sidebar-requests-badge', d.custom_request);
        var total = (d.chat||0) + (d.order||0) + (d.custom_request||0) + (d.order_proposal||0);
        updateNotifBellDot(total);
      }).catch(()=>{});
    }
    updateSidebarBadges();
    setInterval(updateSidebarBadges, 10000);
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.header-notif-wrapper') && !e.target.closest('.notif-dropdown')) closeNotifDropdown();
    });
  </script>
  <script>navigator.sendBeacon('../api/track-visit.php?url=' + encodeURIComponent(location.pathname + location.search) + '&_=' + Date.now());</script>
  <div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)closeModal()">
    <div class="modal-box">
      <div class="modal-header">
        <h2 id="modalTitle"></h2>
        <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body" id="modalBody"></div>
    </div>
  </div>
</body>
</html>


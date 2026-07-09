<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: ../index.php');
  exit();
}

require_once '../db-config.php';
require_once dirname(__DIR__) . '/includes/seo-helper.php';

$loggedIn = true;
$userName = trim($_SESSION['user_name'] ?? '');
$userInitials = '';
if ($userName !== '') {
    $parts = array_filter(preg_split('/\s+/', $userName));
    $userInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
if ($userInitials === '') { $userInitials = 'U'; }

$isSeller = !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

$profile_saved = false;
$profile_error = null;

$user_id = $_SESSION['user_id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $address = trim($_POST['address'] ?? '');

  if ($name === '' || $email === '') {
    $profile_error = 'Name and email are required.';
  } else {
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $checkStmt->bind_param('si', $email, $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult && $checkResult->num_rows > 0) {
      $profile_error = 'This email address is already used by another account.';
    } else {
      $updateStmt = $conn->prepare("UPDATE users SET name = ?, email = ?, contact_number = ?, address = ?, updated_at = NOW() WHERE id = ?");
      $updateStmt->bind_param('ssssi', $name, $email, $phone, $address, $user_id);
      if ($updateStmt->execute()) {
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_phone'] = $phone;
        $_SESSION['user_address'] = $address;
        $profile_saved = true;
      } else {
        $profile_error = 'Failed to save your changes. Please try again.';
      }
      $updateStmt->close();
    }
    $checkStmt->close();
  }
}

// Fetch user data
$stmt = $conn->prepare("SELECT name, email, contact_number, address FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
  $userRow = $result->fetch_assoc();
  $user_name = $userRow['name'];
  $user_email = $userRow['email'];
  $user_phone = $userRow['contact_number'];
  $user_address = $userRow['address'];
} else {
  header('Location: ../logout.php');
  exit();
}
$stmt->close();

// Fetch user's orders with items
$orders = [];
$orderStmt = $conn->prepare("
  SELECT o.id, o.total_amount, o.status, o.created_at, o.updated_at,
         o.total_weight, o.shipping_fee,
         oi.quantity, oi.unit_price, oi.product_id,
         p.name as product_name, p.image_url
  FROM orders o
  LEFT JOIN order_items oi ON o.id = oi.order_id
  LEFT JOIN products p ON oi.product_id = p.id
  WHERE o.user_id = ?
  ORDER BY o.created_at DESC
");
$orderStmt->bind_param('i', $user_id);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();
if ($orderResult) {
  while ($row = $orderResult->fetch_assoc()) {
    $orders[] = $row;
  }
}
$orderStmt->close();
$conn->close();

// Group orders by ID
$groupedOrders = [];
foreach ($orders as $row) {
  $orderId = $row['id'];
  if (!isset($groupedOrders[$orderId])) {
    $groupedOrders[$orderId] = [
      'id' => $orderId,
      'total_amount' => $row['total_amount'],
      'total_weight' => $row['total_weight'],
      'shipping_fee' => $row['shipping_fee'],
      'status' => $row['status'],
      'created_at' => $row['created_at'],
      'updated_at' => $row['updated_at'],
      'items' => [],
    ];
  }
  if ($row['product_name'] !== null) {
    $groupedOrders[$orderId]['items'][] = [
      'product_name' => $row['product_name'],
      'image_url' => $row['image_url'] ?: 'assets/products-demo.jpg',
      'quantity' => $row['quantity'],
      'unit_price' => $row['unit_price'],
      'product_id' => $row['product_id'],
    ];
  }
}

// Count orders per status tab
$statusCounts = [
  'all' => count($groupedOrders),
  'pending' => 0, 'production' => 0, 'shipping' => 0, 'completed' => 0, 'issues' => 0,
];
foreach ($groupedOrders as $o) {
  switch ($o['status']) {
    case 'pending': case 'confirmed':
      $statusCounts['pending']++; break;
    case 'shipped':
      $statusCounts['shipping']++; break;
    case 'delivered':
    case 'completed':
      $statusCounts['completed']++; break;
    case 'returned': case 'cancelled':
      $statusCounts['issues']++; break;
  }
}

$user_name_esc = htmlspecialchars($user_name ?? 'Customer', ENT_QUOTES, 'UTF-8');
$user_email_esc = htmlspecialchars($user_email ?? '', ENT_QUOTES, 'UTF-8');
$user_phone_esc = htmlspecialchars($user_phone ?? '', ENT_QUOTES, 'UTF-8');
$user_address_esc = htmlspecialchars($user_address ?? '', ENT_QUOTES, 'UTF-8');
?>
<?php
$seoTitle = 'My Account | Inkzion Spectrum Ads';
$seoDescription = 'Manage your account, orders, and profile at Inkzion Spectrum Ads.';
$seoKeywords = 'account, profile, orders, shopping, inkzion';
outputSEOTags($seoTitle, $seoDescription, $seoKeywords);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>My Account | Inkzion Spectrum Ads</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --sidebar-bg: #FFF5F8;
      --sidebar-hover: rgba(233, 30, 140, 0.08);
      --sidebar-active: #e91e8c;
      --sidebar-active-bg: rgba(233, 30, 140, 0.12);
      --sidebar-width: 270px;
      --primary: #e91e8c;
      --primary-light: #9c27b0;
      --primary-bg: rgba(233, 30, 140, 0.1);
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
    
    /* ========= SIDEBAR (identical to products-redesigned) ========= */
    .products-sidebar {
      width: var(--sidebar-width);
      background: var(--sidebar-bg);
      border-right: 1px solid rgba(233, 30, 140, 0.1);
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
    .products-sidebar::-webkit-scrollbar-thumb { background: rgba(233, 30, 140, 0.2); border-radius: 4px; }
    
    .sidebar-brand {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 1.25rem 1.25rem 1rem;
      border-bottom: 1px solid rgba(233, 30, 140, 0.12);
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
      box-shadow: 0 2px 6px rgba(233, 30, 140, 0.15);
    }
    .sidebar-brand-text { line-height: 1.2; }
    .sidebar-brand-name { font-size: 0.85rem; font-weight: 800; color: #1a1a2e; letter-spacing: 0.03em; display: block; }
    .sidebar-brand-sub { font-size: 0.6rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; }
    
    .sidebar-profile {
      padding: 0.85rem 1.25rem;
      display: flex;
      align-items: center;
      gap: 0.65rem;
      border-bottom: 1px solid rgba(233, 30, 140, 0.08);
      background: rgba(233, 30, 140, 0.03);
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
      color: #b06ab3;
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
      box-shadow: 0 2px 8px rgba(233, 30, 140, 0.08);
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
    
    /* ========= COLLAPSIBLE ACCOUNT SUBMENU ========= */
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
      color: #b06ab3;
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
    }
    .sidebar-menu-toggle .toggle-arrow {
      margin-left: auto;
      font-size: 0.6rem;
      color: var(--text-muted);
      transition: transform 0.3s ease;
    }
    .sidebar-menu-toggle.open .toggle-arrow {
      transform: rotate(180deg);
    }
    
    .sidebar-footer {
      padding: 0.75rem 1.25rem;
      border-top: 1px solid rgba(233, 30, 140, 0.1);
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
    .sidebar-footer-item i { width: 18px; font-size: 0.85rem; color: #b06ab3; }

    /* ========= MAIN CONTENT ========= */
    .products-main {
      flex: 1;
      margin-left: var(--sidebar-width);
      min-height: 100vh;
    }
    
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
    .top-header-left { display: flex; align-items: center; gap: 1rem; }
    .hamburger-btn {
      display: none;
      width: 40px; height: 40px; border-radius: 10px;
      border: 1px solid var(--border-color); background: white;
      color: var(--text-secondary); cursor: pointer;
      align-items: center; justify-content: center;
      font-size: 1.1rem; transition: var(--transition);
    }
    .hamburger-btn:hover { border-color: var(--primary); color: var(--primary); }
    .top-header-title h1 { font-size: 1.35rem; font-weight: 800; color: var(--text-primary); line-height: 1.3; }
    .top-header-title p { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.05rem; }
    .top-header-center { flex: 1; max-width: 520px; margin: 0 auto; }
    .header-search-wrapper { position: relative; width: 100%; }
    .header-search-wrapper i {
      position: absolute; left: 1.1rem; top: 50%;
      transform: translateY(-50%); color: #adb5bd; font-size: 0.9rem; pointer-events: none;
    }
    .header-search-input {
      width: 100%; padding: 0.7rem 1rem 0.7rem 2.85rem;
      border: 1.5px solid var(--border-color); border-radius: 50px;
      font-size: 0.85rem; font-family: var(--font); background: #F8FAFC;
      color: var(--text-primary); transition: var(--transition);
    }
    .header-search-input:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 4px var(--primary-bg); }
    .header-search-input::placeholder { color: #adb5bd; }
    .top-header-right { display: flex; align-items: center; gap: 0.5rem; }
    .header-icon-btn {
      width: 42px; height: 42px; border-radius: 12px;
      border: 1px solid var(--border-color); background: white;
      color: var(--text-secondary); cursor: pointer; display: flex;
      align-items: center; justify-content: center; font-size: 1rem;
      transition: var(--transition); position: relative; text-decoration: none;
    }
    .header-icon-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); transform: translateY(-1px); }
    .header-icon-btn .notif-dot { position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; border-radius: 50%; background: var(--danger); border: 2px solid white; }
    .header-profile-btn {
      display: flex; align-items: center; gap: 0.6rem;
      padding: 0.35rem 0.75rem 0.35rem 0.35rem; border-radius: 50px;
      border: 1px solid var(--border-color); background: white; cursor: pointer;
      transition: var(--transition); text-decoration: none; color: inherit;
    }
    .header-profile-btn:hover { border-color: var(--primary); background: var(--primary-bg); }
    .header-profile-avatar {
      width: 32px; height: 32px; border-radius: 50%;
      background: var(--primary); color: white; display: flex;
      align-items: center; justify-content: center; font-weight: 700; font-size: 0.65rem; flex-shrink: 0;
    }
    .header-profile-name { font-size: 0.8rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; }
    .header-profile-arrow { font-size: 0.65rem; color: var(--text-muted); margin-left: 0.15rem; }
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
      font-family: inherit;
      text-align: left;
    }
    .header-profile-dropdown-item i {
      width: 18px;
      text-align: center;
      font-size: 0.8rem;
      color: #b06ab3;
      transition: var(--transition);
    }
    .header-profile-dropdown-item:hover {
      background: var(--sidebar-hover);
      color: var(--primary);
    }
    .header-profile-dropdown-item:hover i {
      color: var(--primary);
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
    
    .content-area { padding: 1.5rem 2rem 2rem; }
    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 99; }
    .sidebar-overlay.active { display: block; }

    /* ========= ACCOUNT CONTENT ========= */
    .account-header-row {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;
    }
    .account-header-left h1 {
      font-size: 1.35rem; font-weight: 800; color: var(--text-primary);
      display: flex; align-items: center; gap: 0.6rem;
    }
    .account-header-left h1 i { color: var(--primary); font-size: 1.2rem; }
    .account-header-left p { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem; }
    .btn-header {
      display: inline-flex; align-items: center; gap: 0.5rem;
      padding: 0.6rem 1.2rem; border-radius: 10px;
      font-size: 0.88rem; font-weight: 600; text-decoration: none;
      transition: all 0.2s ease; border: none; cursor: pointer;
    }
    .btn-header-primary { background: linear-gradient(135deg, #e91e8c, #9c27b0); color: white; }
    .btn-header-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(233,30,142,0.3); }
    .btn-header-ghost { background: white; color: #475569; border: 1px solid #e2e8f0; }
    .btn-header-ghost:hover { background: #f1f5f9; }

    .account-content { min-width: 0; }
    .content-section { display: none; }
    .content-section.active { display: block; }

    /* Order Tabs */
    .order-tabs {
      display: flex; gap: 0.4rem; background: white;
      border: 1px solid var(--border-color); border-radius: 14px;
      padding: 0.4rem; margin-bottom: 1.5rem; overflow-x: auto;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .order-tab {
      flex: 1; display: flex; flex-direction: column; align-items: center;
      gap: 0.2rem; padding: 0.65rem 0.5rem; border-radius: 10px;
      border: none; background: transparent; cursor: pointer;
      font-size: 0.72rem; font-weight: 600; color: #64748b;
      transition: all 0.2s ease; white-space: nowrap; font-family: var(--font);
    }
    .order-tab:hover { background: rgba(233,30,142,0.04); }
    .order-tab.active { background: linear-gradient(135deg, #e91e8c, #9c27b0); color: white; }
    .order-tab .tab-count { font-size: 0.6rem; background: rgba(0,0,0,0.08); padding: 0.1rem 0.45rem; border-radius: 999px; font-weight: 700; }
    .order-tab.active .tab-count { background: rgba(255,255,255,0.25); }

    /* Order Card */
    .order-card {
      background: white; border: 1px solid var(--border-color); border-radius: 16px;
      padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
      transition: box-shadow 0.2s ease;
    }
    .order-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
    .order-header {
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;
      padding-bottom: 1rem; border-bottom: 1px solid var(--border-light);
    }
    .order-header-left { display: flex; align-items: center; gap: 1rem; }
    .order-id { font-weight: 700; font-size: 1rem; color: #111827; }
    .order-date { font-size: 0.82rem; color: #64748b; }
    .order-status-badge {
      display: inline-flex; align-items: center; gap: 0.35rem;
      padding: 0.3rem 0.85rem; border-radius: 999px;
      font-size: 0.78rem; font-weight: 700; text-transform: capitalize;
    }
    .badge-pending { background: rgba(255,193,7,0.12); color: #b8860b; border: 1px solid rgba(255,193,7,0.3); }
    .badge-shipped { background: rgba(59,130,246,0.12); color: #1d4ed8; border: 1px solid rgba(59,130,246,0.3); }
    .badge-completed { background: rgba(5,150,105,0.12); color: #047857; border: 1px solid rgba(5,150,105,0.3); }
    .badge-returned { background: rgba(249,115,22,0.12); color: #c2410c; border: 1px solid rgba(249,115,22,0.3); }
    .badge-cancelled { background: rgba(239,68,68,0.12); color: #dc2626; border: 1px solid rgba(239,68,68,0.3); }

    /* Progress */
    .progress-tracker { display: flex; align-items: center; justify-content: space-between; margin: 1.25rem 0; padding: 0 0.5rem; position: relative; }
    .progress-tracker::before { content: ''; position: absolute; top: 18px; left: 40px; right: 40px; height: 3px; background: #e2e8f0; z-index: 0; }
    .progress-step { display: flex; flex-direction: column; align-items: center; gap: 0.4rem; position: relative; z-index: 1; }
    .step-circle { width: 36px; height: 36px; border-radius: 50%; background: #e2e8f0; color: #94a3b8; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; transition: all 0.3s ease; }
    .step-circle i { font-size: 0.85rem; }
    .step-label { font-size: 0.7rem; font-weight: 600; color: #94a3b8; text-align: center; white-space: nowrap; }
    .progress-step.completed .step-circle { background: linear-gradient(135deg, #047857, #10b981); color: white; }
    .progress-step.completed .step-label { color: #047857; }
    .progress-step.active .step-circle { background: linear-gradient(135deg, #e91e8c, #9c27b0); color: white; box-shadow: 0 0 0 4px rgba(233,30,142,0.15); }
    .progress-step.active .step-label { color: #e91e8c; font-weight: 700; }
    .progress-step.cancelled .step-circle { background: rgba(239,68,68,0.12); color: #dc2626; }
    .progress-step.cancelled .step-label { color: #dc2626; }

    .order-items { display: flex; flex-direction: column; gap: 0.6rem; }
    .order-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem; background: #f8fafc; border-radius: 12px; }
    .order-item:hover { background: #f1f5f9; }
    .order-item-img { width: 52px; height: 52px; border-radius: 10px; object-fit: cover; background: white; border: 1px solid var(--border-light); flex-shrink: 0; }
    .order-item-info { flex: 1; min-width: 0; }
    .order-item-name { font-size: 0.88rem; font-weight: 600; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .order-item-meta { font-size: 0.78rem; color: #64748b; margin-top: 0.15rem; }
    .order-item-price { font-size: 0.9rem; font-weight: 700; color: #e91e8c; white-space: nowrap; }
    .order-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-light); }
    .order-total { font-size: 1rem; color: #111827; }
    .order-total strong { color: #e91e8c; font-size: 1.15rem; }
    .order-actions { display: flex; gap: 0.5rem; }
    .btn-order-action { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.82rem; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s ease; }
    .btn-order-action.primary { background: rgba(233,30,142,0.08); color: #e91e8c; border: 1px solid rgba(233,30,142,0.2); }
    .btn-order-action.primary:hover { background: rgba(233,30,142,0.15); }
    .btn-order-action.danger { background: rgba(239,68,68,0.08); color: #dc2626; border: 1px solid rgba(239,68,68,0.2); }
    .btn-order-action.danger:hover { background: rgba(239,68,68,0.15); }

    .orders-empty { text-align: center; padding: 3rem 2rem; background: white; border: 2px dashed var(--border-color); border-radius: 20px; }
    .orders-empty-icon { font-size: 3rem; color: rgba(233,30,142,0.15); margin-bottom: 1rem; }
    .orders-empty h3 { color: #111827; margin: 0 0 0.5rem; font-size: 1.2rem; }
    .orders-empty p { color: #64748b; margin: 0 0 1.5rem; font-size: 0.92rem; }

    /* Forms */
    .form-card { background: white; border: 1px solid var(--border-color); border-radius: 20px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .form-card h2 { margin: 0 0 0.35rem; font-size: 1.2rem; color: #111827; }
    .form-card .subtitle { color: #64748b; font-size: 0.9rem; margin: 0 0 1.5rem; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-grid .full-width { grid-column: 1 / -1; }
    .form-group { display: flex; flex-direction: column; gap: 0.35rem; }
    .form-group label { font-size: 0.88rem; font-weight: 600; color: #334155; }
    .form-group input, .form-group textarea {
      width: 100%; padding: 0.8rem 1rem; border: 1.5px solid #e2e8f0;
      border-radius: 12px; font-size: 0.95rem; color: #111827;
      background: white; outline: none; transition: border-color 0.2s, box-shadow 0.2s;
      font-family: var(--font); box-sizing: border-box;
    }
    .form-group input:focus, .form-group textarea:focus { border-color: #e91e8c; box-shadow: 0 0 0 3px rgba(233,30,142,0.08); }
    .form-group textarea { min-height: 80px; resize: vertical; }
    .form-hint { font-size: 0.78rem; color: #94a3b8; margin-top: 0.15rem; }
    .form-section-divider { height: 1px; background: var(--border-light); margin: 1.5rem 0; }
    .btn-save {
      display: inline-flex; align-items: center; gap: 0.5rem;
      padding: 0.85rem 2rem; border-radius: 12px; border: none;
      background: linear-gradient(135deg, #e91e8c, #9c27b0); color: white;
      cursor: pointer; font-weight: 700; font-size: 0.95rem;
      transition: all 0.2s ease; box-shadow: 0 6px 16px rgba(233,30,142,0.25);
      font-family: var(--font);
    }
    .btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(233,30,142,0.35); }
    .flash-msg { padding: 0.85rem 1.2rem; border-radius: 12px; margin-bottom: 1.25rem; font-size: 0.9rem; font-weight: 600; }
    .flash-success { background: rgba(5,150,105,0.1); color: #047857; border: 1px solid rgba(5,150,105,0.25); }
    .flash-error { background: rgba(239,68,68,0.1); color: #dc2626; border: 1px solid rgba(239,68,68,0.25); }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
      .products-sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
      .products-sidebar.open { transform: translateX(0); }
      .products-main { margin-left: 0; }
      .hamburger-btn { display: flex !important; }
      .account-layout { grid-template-columns: 1fr; }
      .account-sidebar { position: relative; top: 0; }
    }
    @media (max-width: 768px) {
      .top-header { padding: 0 1rem; }
      .top-header-inner { height: 64px; }
      .top-header-title h1 { font-size: 1.1rem; }
      .top-header-title p { display: none; }
      .content-area { padding: 1rem; }
      .header-profile-name { display: none; }
      .header-profile-arrow { display: none; }
      .form-grid { grid-template-columns: 1fr; }
      .form-grid .full-width { grid-column: 1; }
      .order-tabs { overflow-x: auto; }
      .sidebar-card { flex-direction: row; }
      .sidebar-user { border-bottom: none; border-right: 1px solid var(--border-light); padding: 1rem; min-width: 120px; }
      .sidebar-user-avatar { margin: 0 auto 0.5rem; width: 48px; height: 48px; font-size: 1rem; }
      .side-nav { display: flex; flex-wrap: wrap; gap: 0.25rem; padding: 0.5rem; }
    }
    @media (max-width: 600px) {
      .sidebar-card { flex-direction: column; }
      .sidebar-user { border-right: none; border-bottom: 1px solid var(--border-light); width: 100%; }
      .side-nav { flex-direction: column; }
    }
    @media (max-width: 480px) {
      .top-header-center { display: none; }
    }
  </style>
</head>
<body>
  <div class="dashboard-wrapper">
    <!-- ========= SIDEBAR (same as products-redesigned) ========= -->
    <aside class="products-sidebar" id="sidebar">
      <div class="sidebar-brand">
        <img src="../assets/logo.png" alt="Inkzion" class="sidebar-brand-img">
        <div class="sidebar-brand-text">
          <span class="sidebar-brand-name">INKZION</span>
          <span class="sidebar-brand-sub">Spectrum Ads</span>
        </div>
      </div>
      <div class="sidebar-profile">
        <div class="sidebar-avatar"><?php echo htmlspecialchars($userInitials); ?></div>
        <div class="sidebar-profile-info">
          <h4><?php echo htmlspecialchars($userName); ?></h4>
          <p><?php echo $isSeller ? 'admin' : 'Customer'; ?></p>
        </div>
      </div>
      <nav class="sidebar-menu">
        <div class="sidebar-section-title">Shop</div>
        <a href="store-product.php" class="sidebar-menu-item"><i class="fas fa-box"></i> All Products</a>
        <a href="chat.php" class="sidebar-menu-item"><i class="fas fa-comments"></i> Messages</a>

        
        <!-- ========= COLLAPSIBLE MY ACCOUNT ========= -->
        <div class="sidebar-section-title" style="padding-top:0.5rem;">Account</div>
        <div class="sidebar-menu-item sidebar-menu-toggle" id="accountToggle" onclick="toggleAccountMenu()">
          <i class="fas fa-user-circle"></i> My Account
          <i class="fas fa-chevron-down toggle-arrow"></i>
        </div>
        <div class="sidebar-submenu" id="accountSubmenu">
          <button class="sidebar-submenu-item active" data-section="orders" onclick="switchSection('orders')">
            <i class="fas fa-box"></i> My Orders
          </button>
          <button class="sidebar-submenu-item" data-section="profile" onclick="switchSection('profile')">
            <i class="fas fa-user-edit"></i> Edit Profile
          </button>

          <button class="sidebar-submenu-item" data-section="addresses" onclick="switchSection('addresses')">
            <i class="fas fa-map-marker-alt"></i> My Addresses
          </button>

          <button class="sidebar-submenu-item" data-section="orderforms" onclick="switchSection('orderforms')">
            <i class="fas fa-file-invoice"></i> Order Forms
          </button>
          <button class="sidebar-submenu-item" data-section="notifications" onclick="switchSection('notifications')">
            <i class="fas fa-bell"></i> Notifications
          </button>
        </div>
      </nav>
      <div class="sidebar-footer">
        <a href="contact.php" class="sidebar-footer-item"><i class="fas fa-envelope"></i> Contact</a>
        <a href="help-center.php" class="sidebar-footer-item"><i class="fas fa-question-circle"></i> Help Center</a>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- ========= MAIN CONTENT ========= -->
    <main class="products-main">
      <header class="top-header">
        <div class="top-header-inner">
          <div class="top-header-left">
            <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
            <div class="top-header-title">
              <h1>My Account</h1>
              <p>Manage your orders, profile, and settings</p>
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
            <div class="header-profile-dropdown-wrapper">
              <button class="header-profile-btn" onclick="toggleProfileDropdown()" aria-label="Account menu">
                <div class="header-profile-avatar"><?php echo htmlspecialchars($userInitials); ?></div>
                <span class="header-profile-name"><?php echo htmlspecialchars($userName); ?></span>
                <i class="fas fa-chevron-down header-profile-arrow"></i>
              </button>
              <div class="header-profile-dropdown-menu" id="profileDropdown">
                <a href="../logout.php" class="header-profile-dropdown-item danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
              </div>
            </div>
          </div>
        </div>
      </header>
      
      <div class="content-area">
        <!-- Flashes -->
        <?php if ($profile_saved): ?>
          <div class="flash-msg flash-success"><i class="fas fa-check-circle" style="margin-right:6px;"></i> Your profile has been updated successfully.</div>
        <?php elseif ($profile_error): ?>
          <div class="flash-msg flash-error"><i class="fas fa-exclamation-circle" style="margin-right:6px;"></i> <?= htmlspecialchars($profile_error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="account-content">
          <!-- Section: My Orders -->
            <div id="section-orders" class="content-section active">
              <div class="order-tabs" id="order-tabs">
                <button class="order-tab active" data-filter="all">All<span class="tab-count"><?= $statusCounts['all'] ?></span></button>
                <button class="order-tab" data-filter="pending">Pending<span class="tab-count"><?= $statusCounts['pending'] ?></span></button>
                <button class="order-tab" data-filter="production">Production<span class="tab-count"><?= $statusCounts['production'] ?></span></button>
                <button class="order-tab" data-filter="shipping">Shipping<span class="tab-count"><?= $statusCounts['shipping'] ?></span></button>
                <button class="order-tab" data-filter="completed">Completed<span class="tab-count"><?= $statusCounts['completed'] ?></span></button>
                <button class="order-tab" data-filter="issues">Issues<span class="tab-count"><?= $statusCounts['issues'] ?></span></button>
              </div>
              <div id="orders-list">
                <?php if (empty($groupedOrders)): ?>
                  <div class="orders-empty">
                    <div class="orders-empty-icon"><i class="fas fa-box-open"></i></div>
                    <h3>No orders yet</h3>
                    <p>Start shopping to see your orders here!</p>
                    <a href="store-product.php" class="btn-header btn-header-primary" style="display:inline-flex;"><i class="fas fa-shopping-bag"></i> Browse Products</a>
                  </div>
                <?php else: ?>
                  <?php foreach ($groupedOrders as $order):
                    $tabCategory = 'all';
                    switch ($order['status']) {
                      case 'pending': case 'confirmed': $tabCategory = 'pending'; break;
                      case 'shipped': $tabCategory = 'shipping'; break;
                      case 'delivered': $tabCategory = 'shipping'; break;
                      case 'completed': $tabCategory = 'completed'; break;
                      case 'returned': case 'cancelled': $tabCategory = 'issues'; break;
                    }
                    $progressClass = function($step) use ($order) {
                      $statusMap = ['pending'=>1,'confirmed'=>2,'shipped'=>3,'delivered'=>4,'completed'=>5];
                      $currentStep = $statusMap[$order['status']] ?? 0;
                      if ($order['status'] === 'cancelled' || $order['status'] === 'returned') return 'cancelled';
                      if ($step < $currentStep) return 'completed';
                      if ($step === $currentStep) return 'active';
                      return '';
                    };
                    $statusLabels = ['pending'=>'Pending','confirmed'=>'Confirmed','shipped'=>'Shipped','delivered'=>'Delivered','completed'=>'Completed','returned'=>'Returned','cancelled'=>'Cancelled'];
                    $statusLabel = $statusLabels[$order['status']] ?? ucfirst($order['status']);
                  ?>
                  <div class="order-card" data-tab="<?= $tabCategory ?>">
                    <div class="order-header">
                      <div class="order-header-left">
                        <span class="order-id">Order #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></span>
                        <span class="order-date"><i class="fas fa-calendar-alt" style="margin-right:4px;"></i> <?= date('M d, Y \a\t h:i A', strtotime($order['created_at'])) ?></span>
                      </div>
                      <span class="order-status-badge badge-<?= $order['status'] ?>">
                        <i class="fas fa-<?= $order['status']==='completed'?'check-circle':($order['status']==='cancelled'?'times-circle':($order['status']==='shipped'?'truck':($order['status']==='returned'?'undo':'clock'))) ?>"></i>
                        <?= $statusLabel ?>
                      </span>
                    </div>
                    <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'returned'): ?>
                    <div class="progress-tracker">
                      <div class="progress-step <?= $progressClass(1) ?>"><div class="step-circle"><i class="fas fa-check"></i></div><span class="step-label">Pending</span></div>
                      <div class="progress-step <?= $progressClass(2) ?>"><div class="step-circle"><i class="fas fa-check-double"></i></div><span class="step-label">Confirmed</span></div>
                      <div class="progress-step <?= $progressClass(3) ?>"><div class="step-circle"><i class="fas fa-truck"></i></div><span class="step-label">Shipped</span></div>
                      <div class="progress-step <?= $progressClass(4) ?>"><div class="step-circle"><i class="fas fa-check-circle"></i></div><span class="step-label">Delivered</span></div>
                      <div class="progress-step <?= $progressClass(5) ?>"><div class="step-circle"><i class="fas fa-check-double"></i></div><span class="step-label">Completed</span></div>
                    </div>
                    <?php endif; ?>
                    <div class="order-items">
                      <?php foreach ($order['items'] as $item): ?>
                      <div class="order-item">
                        <img src="<?= htmlspecialchars($item['image_url'] ?? 'assets/products-demo.jpg', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['product_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="order-item-img">
                        <div class="order-item-info">
                          <div class="order-item-name"><?= htmlspecialchars($item['product_name'] ?? 'Product', ENT_QUOTES, 'UTF-8') ?></div>
                          <div class="order-item-meta">Qty: <?= (int)$item['quantity'] ?> × ₱<?= number_format((float)$item['unit_price'], 2) ?></div>
                        </div>
                        <div class="order-item-price">₱<?= number_format((float)$item['unit_price'] * (int)$item['quantity'], 2) ?></div>
                      </div>
                      <?php endforeach; ?>
                    </div>
                    <div class="order-footer">
                      <?php if ($order['total_weight']): ?>
                      <div class="order-total" style="font-size:0.78rem;">Weight: <?= number_format((float)$order['total_weight'], 3) ?> kg</div>
                      <?php endif; ?>
                      <?php if ($order['shipping_fee']): ?>
                      <div class="order-total" style="font-size:0.78rem;">Shipping: <strong>₱<?= number_format((float)$order['shipping_fee'], 2) ?></strong></div>
                      <?php endif; ?>
                      <div class="order-total">Total: <strong>₱<?= number_format((float)$order['total_amount'], 2) ?></strong></div>
                      <div class="order-actions">
                        <a href="order-tracking.php?order_id=<?= $order['id'] ?>" class="btn-order-action primary"><i class="fas fa-eye"></i> View</a>
                        <?php if ($order['status'] === 'pending' || $order['status'] === 'confirmed'): ?>
                        <a href="../api/update-order-status.php?action=cancel&order_id=<?= $order['id'] ?>" class="btn-order-action danger" onclick="return confirm('Cancel this order?')"><i class="fas fa-times"></i> Cancel</a>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <!-- Section: Edit Profile -->
            <div id="section-profile" class="content-section">
              <div class="form-card">
                <h2>Edit Profile</h2>
                <p class="subtitle">Update your personal information and contact details.</p>
                <form method="POST">
                  <input type="hidden" name="action" value="update_profile">
                  <div class="form-grid">
                    <div class="form-group full-width">
                      <label for="name">Full Name</label>
                      <input type="text" id="name" name="name" value="<?= $user_name_esc ?>" required placeholder="Enter your full name">
                    </div>
                    <div class="form-group">
                      <label for="email">Email Address</label>
                      <input type="email" id="email" name="email" value="<?= $user_email_esc ?>" required placeholder="Enter your email">
                    </div>
                    <div class="form-group">
                      <label for="phone">Phone Number</label>
                      <input type="text" id="phone" name="phone" value="<?= $user_phone_esc ?>" placeholder="09XX-XXX-XXXX">
                    </div>
                    <div class="form-group full-width">
                      <label for="address">Delivery Address</label>
                      <textarea id="address" name="address" placeholder="Street, Barangay, City, Province"><?= $user_address_esc ?></textarea>
                    </div>
                  </div>
                  <div style="margin-top:1.5rem;">
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                  </div>
                </form>
              </div>
            </div>


            <!-- Section: My Addresses -->
            <div id="section-addresses" class="content-section">
              <div class="form-card">
                <h2>My Addresses</h2>
                <p class="subtitle">Manage your delivery addresses.</p>
                <p style="color:#64748b;font-size:0.9rem;">Your primary delivery address:</p>
                <div style="padding:1rem;background:#f8fafc;border-radius:12px;margin-top:0.75rem;border:1px solid var(--border-light);">
                  <div style="display:flex;align-items:center;gap:0.75rem;">
                    <div style="width:36px;height:36px;border-radius:10px;background:var(--primary-bg);color:var(--primary);display:flex;align-items:center;justify-content:center;"><i class="fas fa-map-marker-alt"></i></div>
                    <div><strong><?= $user_name_esc ?></strong><br><span style="font-size:0.85rem;color:#64748b;"><?= $user_address_esc ?: 'No address set' ?></span></div>
                  </div>
                </div>
                <p style="margin-top:1rem;font-size:0.85rem;color:#64748b;">To update your address, use the <a href="#" onclick="switchSection('profile');return false;" style="color:var(--primary);">Edit Profile</a> section.</p>
              </div>
            </div>


            <!-- Section: Order Forms -->
            <div id="section-orderforms" class="content-section">
              <div class="form-card">
                <h2>Order Forms</h2>
                <p class="subtitle">View and fill out order forms sent by the admin.</p>
                <div id="order-forms-list">
                  <p style="color:#64748b;text-align:center;padding:2rem;"><i class="fas fa-spinner fa-pulse"></i> Loading...</p>
                </div>
              </div>
            </div>

            <!-- Section: Notifications -->
            <div id="section-notifications" class="content-section">
              <div class="form-card" style="text-align:center;padding:3rem;">
                <div style="font-size:3rem;color:rgba(233,30,142,0.15);margin-bottom:1rem;"><i class="fas fa-bell"></i></div>
                <h2>Notifications</h2>
                <p class="subtitle">Stay updated on your orders.</p>
                <p style="color:#64748b;">No new notifications at this time.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('open');
      document.getElementById('sidebarOverlay').classList.toggle('active');
    }

    // ========= ACCOUNT SUBMENU TOGGLE =========
    function toggleAccountMenu() {
      const toggle = document.getElementById('accountToggle');
      const submenu = document.getElementById('accountSubmenu');
      toggle.classList.toggle('open');
      submenu.classList.toggle('open');
    }

    // ========= SECTION SWITCHING =========
    let currentSection = 'orders';

    function switchSection(section) {
      currentSection = section;
      
      // Update main sidebar submenu items
      document.querySelectorAll('#accountSubmenu .sidebar-submenu-item').forEach(el => {
        el.classList.toggle('active', el.dataset.section === section);
      });
      
      // Update account sidebar items
      document.querySelectorAll('.side-nav .side-nav-item').forEach(el => {
        el.classList.toggle('active', el.dataset.section === section);
      });
      
      // Show/hide content sections
      document.querySelectorAll('.content-section').forEach(el => {
        el.classList.toggle('active', el.id === 'section-' + section);
      });

      // Open the account submenu in main sidebar if not already open
      const submenu = document.getElementById('accountSubmenu');
      if (!submenu.classList.contains('open')) {
        document.getElementById('accountToggle').classList.add('open');
        submenu.classList.add('open');
      }
    }

    // ========= ORDER TABS =========
    document.querySelectorAll('.order-tab').forEach(tab => {
      tab.addEventListener('click', function() {
        document.querySelectorAll('.order-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        const filter = this.dataset.filter;
        document.querySelectorAll('.order-card').forEach(card => {
          card.style.display = (filter === 'all' || card.dataset.tab === filter) ? '' : 'none';
        });
      });
    });

    function toggleProfileDropdown() {
      const dropdown = document.getElementById('profileDropdown');
      if (dropdown) dropdown.classList.toggle('active');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
      if (!event.target.closest('.header-profile-dropdown-wrapper')) {
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileDropdown && profileDropdown.classList.contains('active')) {
          profileDropdown.classList.remove('active');
        }
      }
    });

    // ========= ORDER FORMS =========
    async function loadOrderForms() {
      const container = document.getElementById('order-forms-list');
      if (!container) return;
      try {
        const res = await fetch('../api/order-proposals.php?action=list', { credentials: 'include' });
        const data = await res.json();
        if (!data.success || !data.proposals) {
          container.innerHTML = '<p style="color:#64748b;text-align:center;padding:2rem;">Could not load order forms.</p>';
          return;
        }

        const proposals = data.proposals;

        // Filter: show only sent, filled, rejected (not yet approved/converted)
        const active = proposals.filter(p => p.status === 'sent' || p.status === 'filled' || p.status === 'rejected');
        const converted = proposals.filter(p => p.status === 'converted' || p.status === 'approved');

        if (active.length === 0 && converted.length === 0) {
          container.innerHTML = '<div style="text-align:center;padding:2rem;"><div style="font-size:2.5rem;color:rgba(233,30,142,0.15);margin-bottom:0.75rem;"><i class="fas fa-file-invoice"></i></div><p style="color:#64748b;">No order forms from admin yet.</p></div>';
          return;
        }

        let html = '';

        if (active.length > 0) {
          html += '<h3 style="font-size:1rem;margin-bottom:0.75rem;color:#0f172a;">Pending Forms</h3>';
          html += active.map(p => {
            const items = p.items || [];
            const itemSummary = items.map(i => i.name).join(', ');
            const statusColor = p.status === 'filled' ? '#047857' : (p.status === 'rejected' ? '#dc2626' : '#1d4ed8');
            const statusBg = p.status === 'filled' ? 'rgba(5,150,105,0.1)' : (p.status === 'rejected' ? 'rgba(239,68,68,0.1)' : 'rgba(59,130,246,0.1)');

            return `
              <div style="padding:1rem;background:#f8fafc;border-radius:12px;margin-bottom:0.75rem;border:1px solid #e2e8f0;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.5rem;">
                  <strong style="font-size:0.9rem;">Form #${p.id}</strong>
                  <span style="padding:0.2rem 0.6rem;border-radius:999px;font-size:0.72rem;font-weight:700;background:${statusBg};color:${statusColor};text-transform:capitalize;">${p.status}</span>
                </div>
                <div style="font-size:0.82rem;color:#64748b;margin-bottom:0.5rem;">${itemSummary || 'No items'} — Total: <strong>₱${parseFloat(p.total_amount).toFixed(2)}</strong></div>
                ${p.rejection_reason ? `<div style="font-size:0.78rem;color:#dc2626;margin-bottom:0.5rem;"><i class="fas fa-exclamation-circle"></i> ${escapeHtml(p.rejection_reason)}</div>` : ''}
                <div style="display:flex;gap:0.5rem;">
                  ${p.status === 'sent' || p.status === 'rejected' ? `<a href="order-form.php?id=${p.id}" class="btn-order-action primary"><i class="fas fa-pen"></i> Fill Out Form</a>` : ''}
                  ${p.status === 'filled' ? `<span style="font-size:0.82rem;color:#047857;"><i class="fas fa-check-circle"></i> Awaiting admin approval</span>` : ''}
                </div>
              </div>
            `;
          }).join('');
        }

        if (converted.length > 0) {
          html += '<h3 style="font-size:1rem;margin:1.5rem 0 0.75rem 2;color:#0f172a;">Approved Forms</h3>';
          html += converted.map(p => {
            const orderRef = p.order_reference || `#${p.order_id}`;
            return `
              <div style="padding:1rem;background:#f8fafc;border-radius:12px;margin-bottom:0.75rem;border:1px solid #e2e8f0;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.5rem;">
                  <strong style="font-size:0.9rem;">Form #${p.id}</strong>
                  <span style="padding:0.2rem 0.6rem;border-radius:999px;font-size:0.72rem;font-weight:700;background:rgba(5,150,105,0.1);color:#047857;">Approved</span>
                </div>
                <div style="font-size:0.82rem;color:#64748b;">Converted to order ${orderRef}</div>
                ${p.order_id ? `<a href="order-tracking.php?id=${p.order_id}" class="btn-order-action primary" style="display:inline-flex;margin-top:0.5rem;"><i class="fas fa-eye"></i> Track Order</a>` : ''}
              </div>
            `;
          }).join('');
        }

        container.innerHTML = html;
      } catch (e) {
        container.innerHTML = '<p style="color:#dc2626;text-align:center;padding:2rem;">Failed to load order forms.</p>';
      }
    }

    function escapeHtml(str) {
      if (!str) return '';
      return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Override switchSection to also load order forms when switching to that section
    const origSwitchSection = switchSection;
    switchSection = function(section) {
      origSwitchSection(section);
      if (section === 'orderforms') {
        loadOrderForms();
      }
    };

    // Also auto-load order forms if that section is active on page load (shouldn't be, but just in case)
    if (document.getElementById('section-orderforms')?.classList.contains('active')) {
      loadOrderForms();
    }

    // Search
    const searchInput = document.getElementById('product-search');
    if (searchInput) {
      searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && this.value.trim()) {
          window.location.href = 'store-product.php?search=' + encodeURIComponent(this.value.trim());
        }
      });
    }
  </script>
  <script>navigator.sendBeacon('../api/track-visit.php?url=' + encodeURIComponent(location.pathname + location.search) + '&_=' + Date.now());</script>
</body>
</html>

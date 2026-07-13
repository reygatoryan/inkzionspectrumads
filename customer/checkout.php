<?php
session_start();
if (empty($_SESSION['user_id'])) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please log in to purchase this item.'];
    header('Location: ../index.php');
    exit();
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
// ========== Buy Now URL Validation ==========
$buyProductName = isset($_GET['product']) ? trim($_GET['product']) : '';
$buyProductPrice = isset($_GET['price']) ? trim($_GET['price']) : '';
$buyProductImage = isset($_GET['image']) ? trim($_GET['image']) : '';
$buyProductId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$buyQty = isset($_GET['qty']) ? max(1, (int)$_GET['qty']) : 1;

if ($buyProductName === '' || $buyProductPrice === '' || !is_numeric($buyProductPrice) || (float)$buyProductPrice <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please select a product to purchase.'];
    header('Location: store-product.php');
    exit();
}

require_once __DIR__ . '/../db-config.php';

// Profile completeness check
$pc = $conn->prepare("SELECT contact_number, address FROM users WHERE id = ?");
$pc->bind_param("i", $_SESSION['user_id']);
$pc->execute();
$pcRow = $pc->get_result()->fetch_assoc();
$pc->close();
if (empty($pcRow['contact_number']) || empty($pcRow['address'])) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please complete your profile before making a purchase.'];
    header('Location: complete-profile.php');
    exit();
}

if ($buyProductId > 0) {
    $stockCheck = $conn->prepare("SELECT name, stock FROM products WHERE id = ? LIMIT 1");
    $stockCheck->bind_param('i', $buyProductId);
    $stockCheck->execute();
    $stockRow = $stockCheck->get_result()->fetch_assoc();
    $stockCheck->close();
    if (!$stockRow) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Product not found.'];
        header('Location: store-product.php');
        exit();
    }
    if ((int)$stockRow['stock'] < $buyQty) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Sorry, "' . htmlspecialchars($stockRow['name']) . '" only has ' . (int)$stockRow['stock'] . ' item(s) in stock. Please reduce the quantity.'];
        header('Location: store-product.php');
        exit();
    }
}

$isSeller = !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'seller';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Checkout | Inkzion Spectrum Ads</title>
  <meta name="description" content="Complete your order with secure checkout at Inkzion Spectrum Ads." />
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
    
    /* ========= SIDEBAR ========= */
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
    .sidebar-footer button.sidebar-footer-item { background: none; border: none; cursor: pointer; width: 100%; text-align: left; font: inherit; color: var(--text-muted); display: flex; align-items: center; gap: 0.65rem; padding: 0.5rem 0; font-size: 0.78rem; text-decoration: none; transition: var(--transition); }
    .sidebar-footer button.sidebar-footer-item:hover { color: var(--primary); }

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
    
    .top-header-right {
      display: flex;
      align-items: center;
      gap: 0.5rem;
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
    .header-icon-btn .cart-count {
      position: absolute;
      top: -4px;
      right: -4px;
      min-width: 18px;
      height: 18px;
      border-radius: 9px;
      background: var(--primary);
      color: white;
      font-size: 0.6rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0 4px;
    }
    
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
    
    .content-area {
      padding: 1.5rem 2rem 2rem;
    }

    .checkout-grid { display: grid; grid-template-columns: 1fr 400px; gap: 2rem; align-items: start; margin-top: 2rem; }
    .checkout-form-section { display: flex; flex-direction: column; gap: 1.75rem; }
    .checkout-card { 
      background: #ffffff; 
      border: 1px solid var(--border-color); 
      border-radius: 16px; 
      padding: 1.75rem 2rem; 
      box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05), 0 1px 3px rgba(15, 23, 42, 0.04); 
      transition: var(--transition);
    }
    .checkout-card:hover {
      box-shadow: 0 8px 28px rgba(15, 23, 42, 0.08), 0 2px 6px rgba(15, 23, 42, 0.04);
      border-color: rgba(233, 30, 140, 0.12);
    }
    .checkout-card-header { 
      display: flex; 
      align-items: center; 
      gap: 0.85rem; 
      margin-bottom: 1.25rem; 
      padding-bottom: 1rem; 
      border-bottom: 1px solid var(--border-light); 
    }
    .checkout-card-header .card-header-icon { 
      width: 40px; 
      height: 40px; 
      border-radius: 12px; 
      display: flex; 
      align-items: center; 
      justify-content: center; 
      font-size: 1rem; 
      flex-shrink: 0; 
    }
    .checkout-card-header .card-header-icon.contact { background: rgba(233,30,140,0.1); color: var(--primary); }
    .checkout-card-header .card-header-icon.delivery { background: rgba(16,185,129,0.1); color: var(--success); }
    .checkout-card-header .card-header-icon.payment { background: rgba(245,158,11,0.1); color: var(--warning); }
    .checkout-card-header .card-header-icon.notes { background: rgba(99,102,241,0.1); color: #6366f1; }
    .checkout-card-header .card-header-text h2 { 
      margin: 0; 
      font-size: 1.05rem; 
      font-weight: 700; 
      color: var(--text-primary); 
      line-height: 1.3;
    }
    .checkout-card-header .card-header-text p { 
      margin: 0; 
      font-size: 0.72rem; 
      color: var(--text-muted); 
      font-weight: 400;
      line-height: 1.3;
    }
    .checkout-card-header .step-number { 
      width: 28px; 
      height: 28px; 
      border-radius: 50%; 
      background: linear-gradient(135deg, #e91e8c, #9c27b0); 
      color: white; 
      display: flex; 
      align-items: center; 
      justify-content: center; 
      font-weight: 800; 
      font-size: 0.75rem; 
      flex-shrink: 0; 
      margin-left: auto;
      box-shadow: 0 2px 6px rgba(233,30,140,0.2);
    }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-grid .full-width { grid-column: 1 / -1; }
    .form-group { display: flex; flex-direction: column; gap: 0.35rem; }
    .form-group label { font-size: 0.82rem; font-weight: 700; color: var(--text-primary); letter-spacing: 0.01em; display: flex; align-items: center; gap: 0.25rem; }
    .form-group label .required { color: var(--primary); margin-left: 1px; }
    .form-group input, .form-group select, .form-group textarea { 
      width: 100%; 
      padding: 0.75rem 1rem; 
      border: 1.5px solid var(--border-color); 
      border-radius: 10px; 
      font-size: 0.9rem; 
      color: var(--text-primary); 
      background: #ffffff; 
      outline: none; 
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); 
      box-sizing: border-box; 
      font-family: var(--font);
    }
    .form-group input:hover, .form-group select:hover, .form-group textarea:hover { border-color: rgba(233,30,140,0.25); }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { 
      border-color: var(--primary); 
      box-shadow: 0 0 0 3px rgba(233, 30, 142, 0.1), 0 1px 2px rgba(0,0,0,0.02); 
      background: #fef9fb;
    }
    .form-group input::placeholder, .form-group textarea::placeholder { color: #94a3b8; font-weight: 400; }
    .form-group textarea { resize: vertical; min-height: 90px; line-height: 1.5; }
    .form-group select { 
      appearance: none; 
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14'%3E%3Cpath fill='%2394a3b8' d='M7 10L2 5h10z'/%3E%3C/svg%3E"); 
      background-repeat: no-repeat; 
      background-position: right 1rem center; 
      padding-right: 2.5rem; 
      cursor: pointer; 
      background-size: 14px;
    }
    .form-group select:focus { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14'%3E%3Cpath fill='%23e91e8c' d='M7 10L2 5h10z'/%3E%3C/svg%3E"); }
    .form-hint { font-size: 0.8rem; color: #64748b; margin-top: 0.15rem; }
    .payment-methods { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .payment-method { 
      position: relative; 
      border: 2px solid var(--border-color); 
      border-radius: 14px; 
      padding: 1.25rem 1rem 1rem; 
      cursor: pointer; 
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
      display: flex; 
      flex-direction: column; 
      align-items: center; 
      gap: 0.4rem; 
      text-align: center; 
      background: #ffffff; 
      overflow: hidden;
    }
    .payment-method::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, transparent, transparent);
      transition: all 0.3s ease;
    }
    .payment-method:hover { 
      border-color: rgba(233, 30, 142, 0.35); 
      background: rgba(233, 30, 142, 0.03); 
      transform: translateY(-3px);
      box-shadow: 0 8px 24px rgba(233, 30, 142, 0.08);
    }
    .payment-method:hover::before {
      background: linear-gradient(90deg, rgba(233,30,140,0.3), rgba(233,30,140,0.1));
    }
    .payment-method.selected { 
      border-color: var(--primary); 
      background: rgba(233, 30, 142, 0.05); 
      box-shadow: 0 0 0 3px rgba(233, 30, 142, 0.1), 0 8px 24px rgba(233, 30, 142, 0.12);
      transform: translateY(-3px);
    }
    .payment-method.selected::before {
      background: linear-gradient(90deg, var(--primary), var(--primary-light));
    }
    .payment-method input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
    .payment-method .payment-icon-wrapper {
      width: 52px;
      height: 52px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      transition: all 0.3s ease;
      margin-bottom: 0.25rem;
    }
    .payment-method .payment-icon-wrapper.icon-gcash { background: rgba(0,125,254,0.1); color: #007dfe; }
    .payment-method .payment-icon-wrapper.icon-downpayment { background: rgba(139,92,246,0.1); color: #8b5cf6; }
    .payment-method .payment-icon-wrapper.icon-card { background: rgba(233,30,140,0.1); color: var(--primary); }
    .payment-method:hover .payment-icon-wrapper { transform: scale(1.08) translateY(-1px); }
    .payment-method.selected .payment-icon-wrapper { transform: scale(1.08) translateY(-1px); }
    .payment-method .payment-name { font-weight: 700; font-size: 0.85rem; color: var(--text-primary); }
    .payment-method .payment-desc { font-size: 0.7rem; color: var(--text-muted); line-height: 1.3; }
    .payment-method .check-indicator { 
      position: absolute; 
      top: 0.6rem; 
      right: 0.6rem; 
      width: 22px; 
      height: 22px; 
      border-radius: 50%; 
      background: #e2e8f0; 
      color: white; 
      display: flex; 
      align-items: center; 
      justify-content: center; 
      font-size: 0.6rem; 
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      opacity: 0;
      transform: scale(0.7);
    }
    .payment-method.selected .check-indicator { 
      display: flex; 
      opacity: 1;
      transform: scale(1);
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      box-shadow: 0 2px 8px rgba(233,30,140,0.3);
    }
    .cc-fields { margin-top: 1.25rem; padding: 1.25rem; background: rgba(233, 30, 140, 0.03); border: 1px solid rgba(233, 30, 142, 0.15); border-radius: 14px; display: none; }
    .cc-fields.visible { display: block; animation: fadeIn 0.3s ease; }
    .cc-fields h3 { margin: 0 0 1rem; font-size: 0.95rem; color: #111827; display: flex; align-items: center; gap: 0.5rem; }
    .cc-fields h3 i { color: #e91e8c; }
    .ewallet-info { margin-top: 1.25rem; padding: 1.25rem; background: rgba(0, 125, 254, 0.04); border: 1px solid rgba(0, 125, 254, 0.15); border-radius: 14px; display: none; }
    .ewallet-info.visible { display: block; animation: fadeIn 0.3s ease; }
    .ewallet-info h3 { margin: 0 0 0.75rem; font-size: 0.95rem; color: #111827; display: flex; align-items: center; gap: 0.5rem; }
    .ewallet-info h3 i { color: #007dfe; }
    .ewallet-detail { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: white; border-radius: 10px; margin-bottom: 0.5rem; }
    .ewallet-detail:last-child { margin-bottom: 0; }
    .ewallet-detail .ewallet-label { font-size: 0.82rem; color: #64748b; min-width: 80px; }
    .ewallet-detail .ewallet-value { font-size: 0.9rem; font-weight: 700; color: #111827; }
    .cod-info { margin-top: 1.25rem; padding: 1.25rem; background: rgba(255, 152, 0, 0.05); border: 1px solid rgba(255, 152, 0, 0.2); border-radius: 14px; display: none; }
    .cod-info.visible { display: block; animation: fadeIn 0.3s ease; }
    .cod-info h3 { margin: 0 0 0.75rem; font-size: 0.95rem; color: #111827; display: flex; align-items: center; gap: 0.5rem; }
    .cod-info h3 i { color: #ff9800; }
    .cod-info p { margin: 0; font-size: 0.88rem; color: #475569; line-height: 1.6; }
    .checkout-summary { position: sticky; top: 100px; }
    .checkout-summary-card { 
      background: #ffffff; 
      border: 1px solid var(--border-color); 
      border-radius: 16px; 
      padding: 1.5rem; 
      box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05), 0 1px 3px rgba(15, 23, 42, 0.04); 
      transition: var(--transition);
    }
    .checkout-summary-card:hover {
      box-shadow: 0 8px 28px rgba(15, 23, 42, 0.08), 0 2px 6px rgba(15, 23, 42, 0.04);
    }
    .checkout-summary-card .summary-header { 
      display: flex; 
      align-items: center; 
      gap: 0.75rem; 
      margin-bottom: 1.25rem; 
      padding-bottom: 1rem; 
      border-bottom: 1px solid var(--border-light); 
    }
    .checkout-summary-card .summary-header .summary-header-icon {
      width: 36px; height: 36px; border-radius: 10px;
      background: rgba(233,30,140,0.1); color: var(--primary);
      display: flex; align-items: center; justify-content: center;
      font-size: 0.9rem; flex-shrink: 0;
    }
    .checkout-summary-card .summary-header h2 { 
      margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-primary); 
    }
    .checkout-summary-card .summary-header .summary-count {
      margin-left: auto; font-size: 0.7rem; font-weight: 700;
      background: var(--primary-bg); color: var(--primary);
      padding: 0.15rem 0.55rem; border-radius: 999px;
    }
    .summary-items-list { max-height: 240px; overflow-y: auto; margin-bottom: 0.75rem; padding-right: 0.25rem; }
    .summary-items-list::-webkit-scrollbar { width: 3px; }
    .summary-items-list::-webkit-scrollbar-track { background: transparent; }
    .summary-items-list::-webkit-scrollbar-thumb { background: rgba(233, 30, 142, 0.2); border-radius: 4px; }
    .summary-item { 
      display: flex; gap: 0.65rem; padding: 0.6rem 0; 
      border-bottom: 1px solid var(--border-light); 
      align-items: center;
    }
    .summary-item:last-child { border-bottom: none; }
    .summary-item-img { 
      width: 44px; height: 44px; border-radius: 10px; object-fit: cover; 
      background: var(--border-light); flex-shrink: 0; 
      border: 1px solid var(--border-color);
    }
    .summary-item-info { flex: 1; min-width: 0; }
    .summary-item-name { font-size: 0.82rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 0.1rem; }
    .summary-item-qty { font-size: 0.7rem; color: var(--text-muted); }
    .summary-item-price { font-size: 0.85rem; font-weight: 700; color: var(--primary); white-space: nowrap; align-self: center; }
    .summary-divider { border-top: 1px solid var(--border-light); margin: 0.5rem 0; }
    .summary-row { 
      display: flex; justify-content: space-between; align-items: center; 
      padding: 0.4rem 0; font-size: 0.85rem; 
    }
    .summary-row .summary-label { color: var(--text-muted); display: flex; align-items: center; gap: 0.35rem; }
    .summary-row .summary-label i { font-size: 0.7rem; width: 16px; text-align: center; }
    .summary-row .summary-value { color: var(--text-secondary); font-weight: 600; }
    .summary-row .summary-value.free { color: var(--success); font-weight: 700; }
    .summary-row .summary-value.discount { color: var(--danger); }
    .summary-row .summary-value.voucher { color: #6366f1; }
    .summary-total-row { 
      display: flex; justify-content: space-between; align-items: center; 
      padding: 0.75rem 0 0.5rem; margin-top: 0.25rem; 
      border-top: 2px solid var(--border-color); 
    }
    .summary-total-row .total-label { font-size: 0.95rem; font-weight: 700; color: var(--text-primary); }
    .summary-total-row .total-amount { font-size: 1.25rem; font-weight: 800; color: var(--primary); }
    .summary-delivery { 
      display: flex; align-items: center; gap: 0.5rem; 
      padding: 0.6rem 0.75rem; margin: 0.5rem 0 0.75rem; 
      background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.15); 
      border-radius: 10px; font-size: 0.75rem; color: var(--text-secondary); 
    }
    .summary-delivery i { color: var(--success); font-size: 0.8rem; }
    .summary-delivery strong { color: var(--text-primary); }
    .place-order-btn { 
      width: 100%; padding: 0.9rem; margin-top: 0.75rem; 
      background: linear-gradient(135deg, #e91e8c, #9c27b0); 
      color: white; border: none; border-radius: 12px; 
      font-size: 1rem; font-weight: 700; cursor: pointer; 
      display: flex; align-items: center; justify-content: center; gap: 0.5rem; 
      transition: all 0.3s ease; 
      box-shadow: 0 6px 20px rgba(233, 30, 142, 0.25); 
    }
    .place-order-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(233, 30, 142, 0.35); }
    .place-order-btn:active { transform: translateY(0); }
    .place-order-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }
    .place-order-btn i { font-size: 0.9rem; }
    .security-badge { 
      display: flex; align-items: center; justify-content: center; gap: 0.35rem; 
      margin-top: 0.75rem; font-size: 0.72rem; color: var(--text-muted); 
    }
    .security-badge i { color: var(--success); font-size: 0.7rem; }
    .terms-text { font-size: 0.72rem; color: #94a3b8; text-align: center; margin-top: 0.5rem; line-height: 1.5; }
    .terms-text a { color: var(--primary); text-decoration: none; font-weight: 600; }
    .terms-text a:hover { text-decoration: underline; }
    .checkout-empty { text-align: center; padding: 4rem 2rem; background: #ffffff; border: 2px dashed rgba(226, 232, 240, 0.8); border-radius: 24px; box-shadow: 0 12px 34px rgba(15, 23, 42, 0.04); grid-column: 1 / -1; }
    .checkout-empty-icon { font-size: 4rem; color: rgba(233, 30, 142, 0.2); margin-bottom: 1.5rem; }
    .checkout-empty h2 { color: #111827; font-size: 1.5rem; margin-bottom: 0.75rem; }
    .checkout-empty p { color: #64748b; margin-bottom: 2rem; max-width: 400px; margin-left: auto; margin-right: auto; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(6px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
    .modal-overlay.active { display: flex; animation: fadeIn 0.3s ease; }
    .modal-content { background: white; border-radius: 24px; padding: 2.5rem; max-width: 480px; width: 100%; text-align: center; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.2); animation: scaleIn 0.3s ease; }
    .modal-success-icon { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, #047857, #10b981); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.5rem; }
    .modal-content h2 { color: #111827; font-size: 1.5rem; margin: 0 0 0.75rem; }
    .modal-content p { color: #64748b; font-size: 0.95rem; line-height: 1.6; margin: 0 0 0.5rem; }
    .modal-order-id { display: inline-block; background: rgba(233, 30, 142, 0.08); color: #e91e8c; font-weight: 700; font-size: 1rem; padding: 0.5rem 1.2rem; border-radius: 10px; margin: 1rem 0; }
    .modal-actions { display: flex; gap: 0.75rem; margin-top: 1.5rem; }
    .modal-actions a { flex: 1; padding: 0.85rem 1rem; border-radius: 12px; font-weight: 700; text-decoration: none; text-align: center; transition: all 0.2s ease; }
    .modal-actions .btn-primary-modal { background: linear-gradient(135deg, #e91e8c, #9c27b0); color: white; }
    .modal-actions .btn-primary-modal:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(233, 30, 142, 0.3); }
    .modal-actions .btn-ghost-modal { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .modal-actions .btn-ghost-modal:hover { background: #e2e8f0; }
    .btn-spinner { display: none; width: 20px; height: 20px; border: 2.5px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.7s linear infinite; }
    .place-order-btn.loading .btn-spinner { display: block; }
    .place-order-btn.loading .btn-text { display: none; }
    @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
    @media (max-width: 900px) { .checkout-grid { grid-template-columns: 1fr; } .checkout-summary { position: relative; top: 0; order: -1; } .payment-methods { grid-template-columns: 1fr 1fr; } .form-grid { grid-template-columns: 1fr; } .form-grid .full-width { grid-column: 1; } }
    @media (max-width: 480px) { .payment-methods { grid-template-columns: 1fr; } .checkout-card { padding: 1.25rem; } .checkout-summary-card { padding: 1.25rem; } .modal-actions { flex-direction: column; } }
    .toast-checkout { position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%); padding: 0.85rem 1.8rem; border-radius: 12px; font-size: 0.9rem; font-weight: 500; z-index: 9999; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); backdrop-filter: blur(12px); transition: opacity 0.3s ease, transform 0.3s ease; }
    .toast-checkout:not(.hidden) { animation: toastIn 0.3s ease forwards; }
    .toast-checkout.hidden { opacity: 0; transform: translateX(-50%) translateY(20px); pointer-events: none; }
    .toast-checkout-success { background: rgba(5, 150, 105, 0.9); color: white; border: 1px solid rgba(5, 150, 105, 0.4); }
    .toast-checkout-error { background: rgba(239, 68, 68, 0.9); color: white; border: 1px solid rgba(239, 68, 68, 0.4); }
    .toast-checkout-info { background: rgba(59, 130, 246, 0.9); color: white; border: 1px solid rgba(59, 130, 246, 0.4); }
    @keyframes toastIn { from { opacity: 0; transform: translateX(-50%) translateY(20px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }
    .required-note { font-size: 0.8rem; color: #94a3b8; margin-bottom: 1rem; }
    .required-note .required { color: #e91e8c; }
    .confirm-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(6px); z-index: 2000; align-items: center; justify-content: center; padding: 1rem; }
    .confirm-overlay.active { display: flex; animation: fadeIn 0.3s ease; }
    .confirm-content { background: white; border-radius: 24px; padding: 2rem; max-width: 560px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.2); animation: scaleIn 0.3s ease; }
    .confirm-content h2 { color: #111827; font-size: 1.3rem; margin: 0 0 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
    .confirm-content h2 i { color: #e91e8c; }
    .confirm-section { margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid #e2e8f0; }
    .confirm-section:last-of-type { border-bottom: none; margin-bottom: 0; }
    .confirm-section h3 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin: 0 0 0.5rem; font-weight: 700; }
    .confirm-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: 0.35rem 0; font-size: 0.9rem; }
    .confirm-row .confirm-label { color: #64748b; min-width: 100px; flex-shrink: 0; }
    .confirm-row .confirm-value { color: #111827; font-weight: 600; text-align: right; word-break: break-word; }
    .confirm-items-list { margin: 0.5rem 0; padding: 0; list-style: none; }
    .confirm-items-list li { display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.88rem; }
    .confirm-items-list li:last-child { border-bottom: none; }
    .confirm-total-row { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0 0; margin-top: 0.5rem; border-top: 2px solid #e2e8f0; font-size: 1.05rem; font-weight: 700; }
    .confirm-total-row .total-amount { color: #e91e8c; font-size: 1.3rem; }
    .confirm-actions { display: flex; gap: 0.75rem; margin-top: 1.5rem; }
    .confirm-actions button { flex: 1; padding: 0.85rem 1rem; border-radius: 12px; font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: all 0.2s ease; border: none; }
    .btn-confirm-yes { background: linear-gradient(135deg, #e91e8c, #9c27b0); color: white; box-shadow: 0 4px 12px rgba(233, 30, 140, 0.3); }
    .btn-confirm-yes:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(233, 30, 140, 0.4); }
    .btn-confirm-no { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .btn-confirm-no:hover { background: #e2e8f0; }
    .field-error { border-color: #ef4444 !important; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important; }
    .field-error-msg { font-size: 0.78rem; color: #ef4444; margin-top: 0.2rem; display: none; }
    .field-error-msg.visible { display: block; }
    .confirm-edit-input { padding: 0.4rem 0.6rem; border: 1.5px solid #e91e8c; border-radius: 8px; font-size: 0.9rem; font-weight: 600; color: #111827; background: white; outline: none; width: 100%; box-sizing: border-box; }
    .address-preview { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 1rem; margin-top: 1rem; display: none; }
    .address-preview.visible { display: block; }
    .address-preview h4 { margin: 0 0 0.5rem; font-size: 0.85rem; color: #166534; }
    .address-preview p { margin: 0; font-size: 0.9rem; color: #15803d; font-weight: 600; line-height: 1.5; }
    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 99; }
    .sidebar-overlay.active { display: block; }
    .responsive-break { width: 100%; }
    @media (max-width: 1024px) {
      .products-sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
      .products-sidebar.open { transform: translateX(0); }
      .products-main { margin-left: 0; }
      .hamburger-btn { display: flex !important; }
      .top-header-inner { gap: 0.75rem; }
    }
    @media (max-width: 768px) {
      .top-header { padding: 0 1rem; }
      .top-header-inner { height: 64px; }
      .top-header-title h1 { font-size: 1.1rem; }
      .top-header-title p { display: none; }
      .content-area { padding: 1rem; }
      .header-profile-name { display: none; }
      .header-profile-arrow { display: none; }
    }

    .help-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 1.5rem; }
    .help-modal-overlay.open { display: flex; animation: fadeIn 0.25s ease; }
    .help-modal-box { background: white; border-radius: 24px; max-width: 640px; width: 100%; max-height: 85vh; overflow-y: auto; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.2); animation: scaleIn 0.25s ease; }
    .help-modal-header { position: sticky; top: 0; background: white; display: flex; align-items: center; justify-content: space-between; padding: 1.5rem 1.5rem 1rem; border-bottom: 1px solid #f1f5f9; }
    .help-modal-header h2 { font-size: 1.2rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.5rem; }
    .help-modal-header h2 i { color: var(--primary); }
    .help-modal-close { width: 36px; height: 36px; border-radius: 50%; border: none; background: #f1f5f9; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s; }
    .help-modal-close:hover { background: #e2e8f0; color: #0f172a; }
    .help-modal-body { padding: 1.5rem; }
    .help-modal-body p { font-size: 0.92rem; color: #475569; line-height: 1.7; }
    .help-contact-item { display: flex; gap: 1rem; padding: 1rem; background: #f8fafc; border-radius: 14px; margin-bottom: 0.75rem; align-items: flex-start; }
    .help-contact-icon { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, var(--primary), #9c27b0); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
    .help-contact-label { font-size: 0.78rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.15rem; }
    .help-contact-value { font-size: 0.92rem; font-weight: 500; color: #0f172a; }
    .help-faq { border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 0.6rem; overflow: hidden; }
    .help-faq summary { padding: 1rem 1.25rem; font-size: 0.9rem; font-weight: 600; color: #0f172a; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
    .help-faq summary i { font-size: 0.75rem; color: #94a3b8; transition: transform 0.2s; }
    .help-faq[open] summary { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .help-faq[open] summary i { transform: rotate(180deg); }
    .help-faq-answer { padding: 1rem 1.25rem; font-size: 0.88rem; color: #475569; line-height: 1.7; }
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
        <div class="sidebar-avatar"><?php echo $loggedIn ? htmlspecialchars($userInitials) : '<i class="fas fa-user" style="font-size:0.7rem;"></i>'; ?></div>
        <div class="sidebar-profile-info">
          <h4><?php echo $loggedIn ? htmlspecialchars($userName) : 'Guest'; ?></h4>
          <p><?php echo $loggedIn ? ($isSeller ? 'Seller' : 'Customer') : 'Not logged in'; ?></p>
        </div>
      </div>
      
      <nav class="sidebar-menu">
        <div class="sidebar-section-title">Shop</div>
        <a href="store-product.php" class="sidebar-menu-item"><i class="fas fa-box"></i> All Products</a>
        <?php if ($loggedIn): ?>
        <a href="notifications.php" class="sidebar-menu-item"><i class="fas fa-bell"></i> Notifications</a>
        <a href="chat.php" class="sidebar-menu-item"><i class="fas fa-comments"></i> Messages</a>
        <div class="sidebar-section-title" style="padding-top:0.5rem;">Orders</div>
        <a href="my-orders.php" class="sidebar-menu-item"><i class="fas fa-box"></i> My Orders</a>
        <a href="my-requests.php" class="sidebar-menu-item"><i class="fas fa-clipboard-list"></i> My Requests</a>
        <a href="my-order-forms.php" class="sidebar-menu-item"><i class="fas fa-file-invoice"></i> Order Forms</a>
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
        <button class="sidebar-footer-item" onclick="helpOpenModal('contact')"><i class="fas fa-envelope"></i> Contact</button>
        <button class="sidebar-footer-item" onclick="helpOpenModal('help')"><i class="fas fa-question-circle"></i> Help Center</button>
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
            <div style="display:flex; flex-direction:column;">
              <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                <span style="font-size:0.7rem; color:var(--text-muted); display:flex; align-items:center; gap:0.3rem;">
                  <a href="../index.php" style="color:var(--text-muted); text-decoration:none;">Home</a>
                  <span>›</span>
                  <a href="store-product.php" style="color:var(--text-muted); text-decoration:none;">Products</a>
                  <span>›</span>
                  <span style="color:var(--primary); font-weight:600;">Checkout</span>
                </span>
              </div>
              <div class="top-header-title" style="margin-top:0.1rem;">
                <h1>Checkout</h1>
                <p>Complete your order securely</p>
              </div>
            </div>
          </div>
          
          <!-- Progress Steps -->
          <div style="flex:1; max-width:400px; margin:0 auto;">
            <div style="display:flex; align-items:center; justify-content:center; gap:0.25rem;">
              <div style="display:flex; flex-direction:column; align-items:center; gap:0.2rem;">
                <div style="width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg, #e91e8c, #9c27b0); color:white; display:flex; align-items:center; justify-content:center; font-size:0.7rem; font-weight:800; box-shadow:0 2px 8px rgba(233,30,140,0.3);"><i class="fas fa-shopping-cart"></i></div>
                <span style="font-size:0.55rem; font-weight:700; color:var(--primary); white-space:nowrap;">Checkout</span>
              </div>
              <div style="flex:1; max-width:50px; height:2px; background:var(--border-color);"></div>
              <div style="display:flex; flex-direction:column; align-items:center; gap:0.2rem;">
                <div style="width:32px; height:32px; border-radius:50%; background:var(--border-light); color:var(--text-muted); display:flex; align-items:center; justify-content:center; font-size:0.7rem; font-weight:800; border:1.5px solid var(--border-color);"><i class="fas fa-credit-card"></i></div>
                <span style="font-size:0.55rem; font-weight:600; color:var(--text-muted); white-space:nowrap;">Payment</span>
              </div>
              <div style="flex:1; max-width:50px; height:2px; background:var(--border-color);"></div>
              <div style="display:flex; flex-direction:column; align-items:center; gap:0.2rem;">
                <div style="width:32px; height:32px; border-radius:50%; background:var(--border-light); color:var(--text-muted); display:flex; align-items:center; justify-content:center; font-size:0.7rem; font-weight:800; border:1.5px solid var(--border-color);"><i class="fas fa-check-circle"></i></div>
                <span style="font-size:0.55rem; font-weight:600; color:var(--text-muted); white-space:nowrap;">Confirm</span>
              </div>
            </div>
          </div>
          
          <div class="top-header-right">
            <a href="../index.php" class="header-icon-btn" title="Home">
              <i class="fas fa-home"></i>
            </a>
            <?php if ($loggedIn): ?>
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
            <?php else: ?>
            <a href="login.php" class="header-profile-btn">
              <div class="header-profile-avatar"><i class="fas fa-user" style="font-size:0.7rem;"></i></div>
              <span class="header-profile-name">Login</span>
            </a>
            <?php endif; ?>
          </div>
        </div>
      </header>
      
      <!-- === CONTENT AREA === -->
      <div class="content-area">

      <div id="checkout-content" class="checkout-grid">
        <div class="checkout-form-section">

          <!-- Step 1: Contact Information -->
          <div class="checkout-card">
            <div class="checkout-card-header">
              <div class="card-header-icon contact"><i class="fas fa-user"></i></div>
              <div class="card-header-text">
                <h2>Contact Information</h2>
                <p>Who should we contact about this order?</p>
              </div>
              <span class="step-number">1</span>
            </div>
            <p class="required-note">Fields marked with <span class="required">*</span> are required.</p>
            <div class="form-grid">
              <div class="form-group">
                <label for="first-name">First Name <span class="required">*</span></label>
                <input type="text" id="first-name" name="first_name" placeholder="Juan" required>
              </div>
              <div class="form-group">
                <label for="last-name">Last Name <span class="required">*</span></label>
                <input type="text" id="last-name" name="last_name" placeholder="Dela Cruz" required>
              </div>
              <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" placeholder="juan@example.com" required>
              </div>
              <div class="form-group">
                <label for="phone">Phone Number <span class="required">*</span></label>
                <input type="tel" id="phone" name="phone" placeholder="+63 9XX XXX XXXX" required>
              </div>
              <div class="form-group full-width">
                <label for="company">Company / Business Name <span style="color:#94a3b8; font-weight:400;">(Optional)</span></label>
                <input type="text" id="company" name="company" placeholder="Your company name">
              </div>
            </div>
          </div>

          <!-- Step 2: Delivery Address -->
          <div class="checkout-card">
            <div class="checkout-card-header">
              <div class="card-header-icon delivery"><i class="fas fa-truck"></i></div>
              <div class="card-header-text">
                <h2>Delivery Address</h2>
                <p>Where should we deliver your order?</p>
              </div>
              <span class="step-number">2</span>
            </div>
            <div id="saved-addresses-section" style="margin-bottom:1.25rem; display:none;">
              <label style="font-size:0.88rem; font-weight:600; color:#334155; display:block; margin-bottom:0.5rem;">Select a saved address</label>
              <div id="saved-addresses-list" style="display:flex; flex-direction:column; gap:0.5rem;"></div>
              <button type="button" onclick="showAddressForm()" style="margin-top:0.75rem; font-size:0.85rem; color:#e91e8c; background:none; border:none; cursor:pointer; font-weight:600; padding:0;"><i class="fas fa-plus"></i> Use a different address</button>
            </div>
            <div id="address-form-section">
              <div class="form-grid">
              <div class="form-group">
                <label for="city">City / Municipality <span class="required">*</span></label>
                <input type="text" id="city" name="city" placeholder="City or Municipality" required>
              </div>
              <div class="form-group">
                <label for="state-province">State / Province <span class="required">*</span></label>
                <input type="text" id="state-province" name="province" placeholder="State or Province" required>
              </div>
              <div class="form-group full-width">
                <label for="address">Street / House No. / Unit <span class="required">*</span></label>
                <input type="text" id="address" name="address" placeholder="House No., Street, Sitio, Building" required>
              </div>
              <div class="form-group">
                <label for="zip-code">ZIP Code <span class="required">*</span></label>
                <input type="text" id="zip-code" name="zip_code" placeholder="0000" required>
              </div>
              <div class="form-group">
                <label for="country">Country</label>
                <select id="country" name="country">
                  <option value="Philippines" selected>Philippines</option>
                </select>
              </div>
            <div class="form-group full-width">
              <label for="delivery-notes">Delivery Notes <span style="color:#94a3b8; font-weight:400;">(Optional)</span></label>
              <textarea id="delivery-notes" name="delivery_notes" placeholder="Landmarks, gate code, preferred delivery time, etc."></textarea>
            </div>
            </div>
            <div class="address-preview" id="address-preview">
              <h4><i class="fas fa-check-circle"></i> Complete Address</h4>
              <p id="address-preview-text"></p>
            </div>
          </div>

          <!-- Step 3: Payment Method -->
          <div class="checkout-card">
            <div class="checkout-card-header">
              <div class="card-header-icon payment"><i class="fas fa-credit-card"></i></div>
              <div class="card-header-text">
                <h2>Payment Method</h2>
                <p>Choose how you'd like to pay</p>
              </div>
              <span class="step-number">3</span>
            </div>
            <div class="payment-methods">
              <label class="payment-method selected" data-method="gcash">
                <input type="radio" name="payment_method" value="gcash" checked>
                <span class="check-indicator"><i class="fas fa-check"></i></span>
                <span class="payment-icon-wrapper icon-gcash"><i class="fas fa-wallet"></i></span>
                <span class="payment-name">GCash</span>
                <span class="payment-desc">Pay via GCash mobile wallet</span>
              </label>
              <label class="payment-method" data-method="downpayment">
                <input type="radio" name="payment_method" value="downpayment">
                <span class="check-indicator"><i class="fas fa-check"></i></span>
                <span class="payment-icon-wrapper icon-downpayment"><i class="fas fa-percentage"></i></span>
                <span class="payment-name">Downpayment</span>
                <span class="payment-desc">Pay a partial amount upfront. Balance due before delivery.</span>
              </label>
              <label class="payment-method" data-method="credit-card">
                <input type="radio" name="payment_method" value="credit-card">
                <span class="check-indicator"><i class="fas fa-check"></i></span>
                <span class="payment-icon-wrapper icon-card"><i class="fas fa-credit-card"></i></span>
                <span class="payment-name">Credit / Debit Card</span>
                <span class="payment-desc">Visa, Mastercard, or JCB</span>
              </label>
            </div>
            <div id="gcash-info" class="ewallet-info visible">
              <h3><i class="fas fa-wallet"></i> GCash Payment</h3>
              <p style="font-size:0.88rem; color:#475569; margin:0 0 0.75rem; line-height:1.5;">Scan the QR code below using the GCash app and pay the exact order amount.</p>
              <div style="text-align:center; margin:1rem 0;">
                <img src="../assets/gcash-qr.png" alt="GCash QR Code" style="width:200px; height:200px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.1);">
              </div>
              <div class="ewallet-detail"><span class="ewallet-label">Account:</span><span class="ewallet-value">Inkzion Spectrum Ads</span></div>
              <div class="ewallet-detail"><span class="ewallet-label">GCash Number:</span><span class="ewallet-value">09XX XXX XXXX</span></div>
              <div style="margin-top:1rem; padding:1rem; background:rgba(0,125,254,0.08); border:1px solid rgba(0,125,254,0.2); border-radius:10px;">
                <label style="font-size:0.85rem; font-weight:600; color:#334155; display:block; margin-bottom:0.5rem;">
                  <i class="fas fa-hashtag" style="color:#007dfe;"></i> GCash Reference Number
                </label>
                <input type="text" id="gcash-ref-no" name="gcash_ref_no" placeholder="Enter the reference number from your GCash payment" style="width:100%; padding:0.6rem 0.75rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.85rem;">
                <p style="font-size:0.75rem; color:#64748b; margin-top:0.4rem;">After paying via GCash, copy the reference number from your transaction history and paste it here.</p>
              </div>
              <div style="margin-top:0.75rem; padding:1rem; background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.2); border-radius:10px;">
                <label style="font-size:0.85rem; font-weight:600; color:#334155; display:block; margin-bottom:0.5rem;">
                  <i class="fas fa-upload" style="color:#f59e0b;"></i> Upload Screenshot (Optional)
                </label>
                <input type="file" id="gcash-receipt" accept="image/*" style="width:100%; padding:0.5rem; border:1.5px dashed #e2e8f0; border-radius:8px; font-size:0.85rem;">
                <p style="font-size:0.75rem; color:#64748b; margin-top:0.4rem;">Upload a screenshot of your GCash payment for faster verification.</p>
              </div>
            </div>

            <div id="cc-fields" class="cc-fields">
              <h3><i class="fas fa-lock"></i> Card Details</h3>
              <div class="form-grid">
                <div class="form-group full-width"><label for="cc-name">Name on Card <span class="required">*</span></label><input type="text" id="cc-name" name="cc_name" placeholder="JUAN DELA CRUZ"></div>
                <div class="form-group full-width"><label for="cc-number">Card Number <span class="required">*</span></label><input type="text" id="cc-number" name="cc_number" placeholder="1234 5678 9012 3456" maxlength="19"></div>
                <div class="form-group"><label for="cc-expiry">Expiry Date <span class="required">*</span></label><input type="text" id="cc-expiry" name="cc_expiry" placeholder="MM/YY" maxlength="5"></div>
                <div class="form-group"><label for="cc-cvv">CVV <span class="required">*</span></label><input type="text" id="cc-cvv" name="cc_cvv" placeholder="123" maxlength="4"></div>
              </div>
            </div>

            <div id="downpayment-info" class="ewallet-info">
              <h3><i class="fas fa-percentage"></i> Downpayment (50% Upfront + Balance)</h3>
              <p style="font-size:0.88rem; color:#475569; margin:0 0 0.75rem; line-height:1.5;">Pay 50% upfront to start production. Choose how you'll pay the upfront and the remaining 50% balance.</p>

              <div style="padding:1rem; background:rgba(233,30,140,0.04); border:1px solid rgba(233,30,142,0.15); border-radius:10px;">
                <h4 style="margin:0 0 0.75rem; font-size:0.85rem; color:#111827;"><i class="fas fa-lock" style="color:var(--primary);"></i> Upfront Payment (50%)</h4>

                <div style="display:flex; gap:1rem; margin-bottom:1rem;">
                  <label class="balance-method-option" style="display:flex; align-items:center; gap:0.4rem; padding:0.4rem 0.75rem; border:1.5px solid #e2e8f0; border-radius:8px; cursor:pointer; background:rgba(0,125,254,0.04);">
                    <input type="radio" name="upfront_method" value="gcash" checked> <i class="fas fa-wallet" style="color:#007dfe;"></i> <strong style="font-size:0.85rem;">GCash</strong>
                  </label>
                  <label class="balance-method-option" style="display:flex; align-items:center; gap:0.4rem; padding:0.4rem 0.75rem; border:1.5px solid #e2e8f0; border-radius:8px; cursor:pointer;">
                    <input type="radio" name="upfront_method" value="credit-card"> <i class="fas fa-credit-card" style="color:var(--primary);"></i> <strong style="font-size:0.85rem;">Credit Card</strong>
                  </label>
                </div>

                <div id="dp-upfront-gcash">
                  <div style="text-align:center; margin:0.5rem 0;">
                    <img src="../assets/gcash-qr.png" alt="GCash QR Code" style="width:160px; height:160px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.1);">
                  </div>
                  <div class="ewallet-detail"><span class="ewallet-label">Account:</span><span class="ewallet-value">Inkzion Spectrum Ads</span></div>
                  <div class="ewallet-detail"><span class="ewallet-label">GCash Number:</span><span class="ewallet-value">09XX XXX XXXX</span></div>
                  <div style="margin-top:0.75rem; padding:0.75rem; background:rgba(0,125,254,0.08); border:1px solid rgba(0,125,254,0.2); border-radius:8px;">
                    <label style="font-size:0.8rem; font-weight:600; color:#334155; display:block; margin-bottom:0.3rem;">
                      <i class="fas fa-hashtag" style="color:#007dfe;"></i> GCash Reference Number <span class="required">*</span>
                    </label>
                    <input type="text" id="dp-gcash-ref" placeholder="Enter the reference number from your GCash payment" style="width:100%; padding:0.5rem 0.75rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.85rem;">
                    <p style="font-size:0.7rem; color:#64748b; margin-top:0.3rem;">After paying via GCash, copy the reference number from your transaction history and paste it here.</p>
                  </div>
                  <div style="margin-top:0.5rem; padding:0.75rem; background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.2); border-radius:8px;">
                    <label style="font-size:0.8rem; font-weight:600; color:#334155; display:block; margin-bottom:0.3rem;">
                      <i class="fas fa-upload" style="color:#f59e0b;"></i> Upload Screenshot (Optional)
                    </label>
                    <input type="file" id="dp-gcash-receipt" accept="image/*" style="width:100%; padding:0.4rem; border:1.5px dashed #e2e8f0; border-radius:8px; font-size:0.85rem;">
                    <p style="font-size:0.7rem; color:#64748b; margin-top:0.3rem;">Upload a screenshot of your GCash payment for faster verification.</p>
                  </div>
                </div>

                <div id="dp-upfront-cc" style="display:none;">
                  <div class="form-grid" style="margin-top:0.5rem;">
                    <div class="form-group full-width"><label>Name on Card <span class="required">*</span></label><input type="text" id="dp-cc-name" placeholder="JUAN DELA CRUZ" style="width:100%; padding:0.6rem 0.75rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.85rem;"></div>
                    <div class="form-group full-width"><label>Card Number <span class="required">*</span></label><input type="text" id="dp-cc-number" placeholder="1234 5678 9012 3456" maxlength="19" style="width:100%; padding:0.6rem 0.75rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.85rem;"></div>
                    <div class="form-group"><label>Expiry Date <span class="required">*</span></label><input type="text" id="dp-cc-expiry" placeholder="MM/YY" maxlength="5" style="width:100%; padding:0.6rem 0.75rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.85rem;"></div>
                    <div class="form-group"><label>CVV <span class="required">*</span></label><input type="text" id="dp-cc-cvv" placeholder="123" maxlength="4" style="width:100%; padding:0.6rem 0.75rem; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.85rem;"></div>
                  </div>
                </div>
              </div>

              <div style="margin-top:1rem; padding:1rem; background:rgba(139,92,246,0.04); border:1px solid rgba(139,92,246,0.15); border-radius:10px;">
                <h4 style="margin:0 0 0.75rem; font-size:0.85rem; color:#111827;"><i class="fas fa-balance-scale" style="color:#8b5cf6;"></i> Remaining Balance Method (50%)</h4>
                <div style="display:flex; flex-direction:column; gap:0.6rem;">
                  <label class="balance-method-option" style="display:flex; align-items:center; gap:0.5rem; padding:0.5rem 0.75rem; border:1.5px solid #e2e8f0; border-radius:8px; cursor:pointer;">
                    <input type="radio" name="balance_method" value="cod" checked> <i class="fas fa-truck" style="color:#ff9800; width:20px;"></i> <strong style="font-size:0.85rem;">Cash on Delivery</strong> <span style="font-size:0.75rem; color:#64748b;">— pay remaining to rider</span>
                  </label>
                  <label class="balance-method-option" style="display:flex; align-items:center; gap:0.5rem; padding:0.5rem 0.75rem; border:1.5px solid #e2e8f0; border-radius:8px; cursor:pointer;">
                    <input type="radio" name="balance_method" value="pickup"> <i class="fas fa-store" style="color:#10b981; width:20px;"></i> <strong style="font-size:0.85rem;">Cash on Pickup</strong> <span style="font-size:0.75rem; color:#64748b;">— pay remaining when you pick up</span>
                  </label>

                </div>
              </div>
            </div>
          </div>

          <!-- Step 4: Order Notes -->
          <div class="checkout-card">
            <div class="checkout-card-header">
              <div class="card-header-icon notes"><i class="fas fa-sticky-note"></i></div>
              <div class="card-header-text">
                <h2>Additional Notes</h2>
                <p>Anything else we should know?</p>
              </div>
              <span class="step-number">4</span>
            </div>
            <div class="form-grid">
              <div class="form-group full-width">
                <label for="order-notes">Special Instructions <span style="color:#94a3b8; font-weight:400;">(Optional)</span></label>
                <textarea id="order-notes" name="order_notes" placeholder="Any special requests, color preferences, file upload details, or other instructions for your order..."></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Order Summary Sidebar -->
        <aside class="checkout-summary">
          <div class="checkout-summary-card">
            <div class="summary-header">
              <div class="summary-header-icon"><i class="fas fa-receipt"></i></div>
              <h2>Order Summary</h2>
              <span class="summary-count" id="summary-count">0 items</span>
            </div>
            <div id="summary-items" class="summary-items-list"></div>
            <div class="summary-divider"></div>
            <div class="summary-row">
              <span class="summary-label"><i class="fas fa-shopping-bag"></i> Subtotal</span>
              <span class="summary-value" id="summary-subtotal">â‚±0.00</span>
            </div>
            <div class="summary-row">
              <span class="summary-label"><i class="fas fa-weight"></i> Total Weight</span>
              <span class="summary-value" id="summary-weight">0.00 kg</span>
            </div>
            <div class="summary-row">
              <span class="summary-label"><i class="fas fa-truck"></i> Shipping</span>
              <span class="summary-value" id="summary-shipping">To be calculated</span>
            </div>
            <div class="summary-row" id="discount-row" style="display:none;">
              <span class="summary-label"><i class="fas fa-tag"></i> Discount</span>
              <span class="summary-value discount" id="summary-discount">-â‚±0.00</span>
            </div>
            <div class="summary-row" id="voucher-row" style="display:none;">
              <span class="summary-label"><i class="fas fa-ticket-alt"></i> Voucher</span>
              <span class="summary-value voucher" id="summary-voucher">-â‚±0.00</span>
            </div>
            <div class="summary-total-row">
              <span class="total-label">Grand Total</span>
              <span class="total-amount" id="summary-total">â‚±0.00</span>
            </div>
            <div class="summary-delivery">
              <i class="fas fa-clock"></i>
              <span>Est. delivery: <strong>3-5 business days</strong></span>
            </div>
            <button type="button" id="place-order-btn" class="place-order-btn">
              <span class="btn-text"><i class="fas fa-lock"></i> Place Order</span>
              <span class="btn-spinner"></span>
            </button>
            <div class="security-badge"><i class="fas fa-shield-alt"></i><span>Secured with 256-bit SSL encryption</span></div>
            <p class="terms-text">By placing this order, you agree to our <a href="../index.php#contact">Terms of Service</a> and <a href="../index.php#contact">Privacy Policy</a>.</p>
          </div>
        </aside>
      </div>
    </div>
  </main>

  <!-- Order Finalization Confirmation Modal -->
  <div id="confirm-modal" class="confirm-overlay">
    <div class="confirm-content">
      <h2><i class="fas fa-clipboard-check"></i> Confirm Your Order</h2>
      <p style="font-size:0.9rem; color:#64748b; margin:0 0 1rem;">Please review your order details before confirming.</p>
      <div class="confirm-section">
        <h3>Contact Information</h3>
        <div class="confirm-row"><span class="confirm-label">Name:</span><span class="confirm-value" id="confirm-name"></span></div>
        <div class="confirm-row"><span class="confirm-label">Email:</span><span class="confirm-value" id="confirm-email"></span></div>
        <div class="confirm-row"><span class="confirm-label">Phone:</span><span class="confirm-value" id="confirm-phone"></span></div>
      </div>
      <div class="confirm-section">
        <h3>Delivery Address</h3>
        <div class="confirm-row"><span class="confirm-label">Address:</span><span class="confirm-value" id="confirm-address"></span></div>
        <div class="confirm-row"><span class="confirm-label">Barangay:</span><span class="confirm-value" id="confirm-barangay"></span></div>
        <div class="confirm-row"><span class="confirm-label">City:</span><span class="confirm-value" id="confirm-city"></span></div>
        <div class="confirm-row"><span class="confirm-label">Province:</span><span class="confirm-value" id="confirm-province"></span></div>
        <div class="confirm-row"><span class="confirm-label">ZIP Code:</span><span class="confirm-value" id="confirm-zip"></span></div>
      </div>
      <div class="confirm-section">
        <h3>Payment Method</h3>
        <div class="confirm-row"><span class="confirm-label">Method:</span><span class="confirm-value" id="confirm-payment"></span></div>
      </div>
      <div class="confirm-section">
        <h3>Order Items</h3>
        <ul class="confirm-items-list" id="confirm-items"></ul>
        <div class="confirm-total-row"><span>Total</span><span class="total-amount" id="confirm-total"></span></div>
      </div>
      <div class="confirm-actions">
        <button class="btn-confirm-no" onclick="closeConfirmModal()">Cancel</button>
        <button class="btn-confirm-yes" id="confirm-submit-btn" onclick="submitOrder()">
          <i class="fas fa-check"></i> Confirm & Place Order
        </button>
      </div>
    </div>
  </div>

  <!-- Order Confirmation Modal -->
  <div id="order-modal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-success-icon"><i class="fas fa-check"></i></div>
      <h2>Order Placed Successfully!</h2>
      <p>Thank you for your order. We've sent a confirmation to your email.</p>
      <div class="modal-order-id" id="modal-order-id">Order #INK-000000</div>
      <p id="modal-payment-note" style="font-size:0.88rem;"></p>
      <div class="modal-actions">
        <a href="store-product.php" class="btn-ghost-modal">Continue Shopping</a>
        <a href="profile.php" class="btn-primary-modal">View Orders</a>
      </div>
    </div>
  </div>

  <div id="checkout-toast" class="toast-checkout hidden"></div>

  <script>
    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('open');
      document.getElementById('sidebarOverlay').classList.toggle('active');
    }
    function showToast(msg, type) {
      type = type || 'info';
      var t = document.getElementById('checkout-toast');
      if (t) { t.textContent = msg; t.className = 'toast-checkout toast-checkout-' + type; t.classList.remove('hidden'); clearTimeout(t._timer); t._timer = setTimeout(function(){ t.classList.add('hidden'); }, 3000); }
    }
  (function() {
    let buyNowItem = null;

    function parsePrice(price) { if (!price) return 0; return parseFloat(price.toString().replace(/[^0-9.\-]/g, '')) || 0; }

    async function loadUserSession() {
      try { const r = await fetch('../api/user-session.php', { credentials: 'include' }); if (!r.ok) return null; const d = await r.json(); return d && d.user ? d.user : null; } catch { return null; }
    }

    const checkoutContent = document.getElementById('checkout-content');
    const summaryItems = document.getElementById('summary-items');
    const summarySubtotal = document.getElementById('summary-subtotal');
    const summaryTotal = document.getElementById('summary-total');
    const summaryCount = document.getElementById('summary-count');
    const placeOrderBtn = document.getElementById('place-order-btn');

    function handleBuyNow() {
      const params = new URLSearchParams(window.location.search);
      const productName = params.get('product');
      const productPrice = params.get('price');
      const productImage = params.get('image');
      const productId = params.get('product_id');
      const qtyParam = params.get('qty');
      const weightParam = params.get('weight');
      
      if (productName && productPrice) {
        buyNowItem = {
          name: decodeURIComponent(productName),
          price: productPrice,
          quantity: parseInt(qtyParam) || 1,
          image_url: productImage ? decodeURIComponent(productImage) : 'assets/products-demo.jpg',
          product_id: productId || null,
          weight: parseFloat(weightParam) || 0
        };
        window.history.replaceState({}, document.title, window.location.pathname);
        return true;
      }
      return false;
    }

    function renderCheckout() {
      if (!buyNowItem) {
        checkoutContent.innerHTML = '<div style="text-align:center;padding:4rem 2rem;"><h2>No item selected</h2><p>Please select a product to purchase.</p><a href="store-product.php" class="btn btn-primary" style="display:inline-flex;padding:0.7rem 1.5rem;margin-top:1rem;">Browse Products</a></div>';
        return;
      }
      const item = buyNowItem;
      const price = parsePrice(item.price);
      const subtotal = price * item.quantity;
      const img = item.image_url || 'assets/products-demo.jpg';
      summaryItems.innerHTML = `<div class="summary-item"><img src="${img}" alt="${item.name}" class="summary-item-img" onerror="this.src='../assets/products-demo.jpg'"><div class="summary-item-info"><div class="summary-item-name" title="${item.name}">${item.name}</div><div class="summary-item-qty">Qty: ${item.quantity}</div></div><div class="summary-item-price">&#8369;${subtotal.toFixed(2)}</div></div>`;
      summarySubtotal.textContent = '\u20B1' + subtotal.toFixed(2);
      summaryTotal.textContent = '\u20B1' + subtotal.toFixed(2);
      const weight = parseFloat(item.weight || 0);
      const totalWeight = weight * item.quantity;
      document.getElementById('summary-weight').textContent = totalWeight.toFixed(3) + ' kg';
    }

    function getItemTotal() {
      if (!buyNowItem) return 0;
      return parsePrice(buyNowItem.price) * buyNowItem.quantity;
    }

    const paymentMethods = document.querySelectorAll('.payment-method');
    const gcashInfo = document.getElementById('gcash-info');
    const downpaymentInfo = document.getElementById('downpayment-info');
    const ccFields = document.getElementById('cc-fields');
    function showPaymentDetails(method) {
      gcashInfo.classList.remove('visible'); downpaymentInfo.classList.remove('visible'); ccFields.classList.remove('visible');
      switch(method) { case 'gcash': gcashInfo.classList.add('visible'); break; case 'downpayment': downpaymentInfo.classList.add('visible'); toggleUpfrontMethod(); break; case 'credit-card': ccFields.classList.add('visible'); break; }
    }
    paymentMethods.forEach(method => {
      method.addEventListener('click', () => {
        paymentMethods.forEach(m => m.classList.remove('selected'));
        method.classList.add('selected');
        method.querySelector('input[type="radio"]').checked = true;
        showPaymentDetails(method.dataset.method);
      });
    });

    const ccNumber = document.getElementById('cc-number');
    if (ccNumber) ccNumber.addEventListener('input', (e) => { let val = e.target.value.replace(/\D/g, ''); val = val.replace(/(.{4})/g, '$1 ').trim(); e.target.value = val; });
    const ccExpiry = document.getElementById('cc-expiry');
    if (ccExpiry) ccExpiry.addEventListener('input', (e) => { let val = e.target.value.replace(/\D/g, ''); if (val.length >= 2) val = val.substring(0, 2) + '/' + val.substring(2); e.target.value = val; });
    const dpCcNumber = document.getElementById('dp-cc-number');
    if (dpCcNumber) dpCcNumber.addEventListener('input', (e) => { let val = e.target.value.replace(/\D/g, ''); val = val.replace(/(.{4})/g, '$1 ').trim(); e.target.value = val; });
    const dpCcExpiry = document.getElementById('dp-cc-expiry');
    if (dpCcExpiry) dpCcExpiry.addEventListener('input', (e) => { let val = e.target.value.replace(/\D/g, ''); if (val.length >= 2) val = val.substring(0, 2) + '/' + val.substring(2); e.target.value = val; });

    function toggleUpfrontMethod(el) {
      const method = el ? el.value : document.querySelector('input[name="upfront_method"]:checked').value;
      document.getElementById('dp-upfront-gcash').style.display = method === 'gcash' ? 'block' : 'none';
      document.getElementById('dp-upfront-cc').style.display = method === 'credit-card' ? 'block' : 'none';
    }
    document.querySelectorAll('input[name="upfront_method"]').forEach(radio => {
      radio.addEventListener('change', (e) => toggleUpfrontMethod(e.target));
    });
    toggleUpfrontMethod();

    function showToast(message, type) {
      const toast = document.getElementById('checkout-toast');
      toast.textContent = message;
      toast.className = `toast-checkout toast-checkout-${type}`;
      toast.classList.remove('hidden');
      clearTimeout(toast._timer);
      toast._timer = setTimeout(() => toast.classList.add('hidden'), 3000);
    }

    const cityInput = document.getElementById('city');
    const provinceInput = document.getElementById('state-province');
    const addressPreview = document.getElementById('address-preview');
    const addressPreviewText = document.getElementById('address-preview-text');

    function updateAddressPreview() {
      const street = document.getElementById('address').value.trim();
      const city = cityInput.value.trim();
      const province = provinceInput.value.trim();
      const zip = document.getElementById('zip-code').value.trim();
      if (street && city && province) {
        addressPreviewText.textContent = `${street}, ${city}, ${province}${zip ? ' ' + zip : ''}`;
        addressPreview.classList.add('visible');
      } else {
        addressPreview.classList.remove('visible');
      }
    }

    document.getElementById('address').addEventListener('input', updateAddressPreview);
    document.getElementById('city').addEventListener('input', updateAddressPreview);
    document.getElementById('state-province').addEventListener('input', updateAddressPreview);
    document.getElementById('zip-code').addEventListener('input', updateAddressPreview);

    function showFieldError(fieldId, message) {
      const el = document.getElementById(fieldId);
      if (!el) return;
      el.classList.add('field-error');
      let errorEl = el.parentElement.querySelector('.field-error-msg');
      if (!errorEl) { errorEl = document.createElement('div'); errorEl.className = 'field-error-msg'; el.parentElement.appendChild(errorEl); }
      errorEl.textContent = message;
      errorEl.classList.add('visible');
    }
    function clearFieldError(fieldId) {
      const el = document.getElementById(fieldId);
      if (!el) return;
      el.classList.remove('field-error');
      const errorEl = el.parentElement.querySelector('.field-error-msg');
      if (errorEl) errorEl.classList.remove('visible');
    }
    function clearAllFieldErrors() {
      document.querySelectorAll('.field-error').forEach(el => el.classList.remove('field-error'));
      document.querySelectorAll('.field-error-msg').forEach(el => el.classList.remove('visible'));
    }

    function validatePhilippineAddress() {
      clearAllFieldErrors();
      let isValid = true;

      const firstName = document.getElementById('first-name');
      const lastName = document.getElementById('last-name');
      if (firstName && !/^[a-zA-Z\u00d1\u00f1\s\-\.]{2,}$/.test(firstName.value.trim())) { showFieldError('first-name', 'Please enter a valid first name'); isValid = false; }
      if (lastName && !/^[a-zA-Z\u00d1\u00f1\s\-\.]{2,}$/.test(lastName.value.trim())) { showFieldError('last-name', 'Please enter a valid last name'); isValid = false; }

      const email = document.getElementById('email');
      if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) { showFieldError('email', 'Please enter a valid email address'); isValid = false; }

      const phone = document.getElementById('phone');
      if (phone) {
        const phoneDigits = phone.value.trim().replace(/\D/g, '');
        if (!/^(\+63|0)?9\d{9}$/.test(phoneDigits)) { showFieldError('phone', 'Please enter a valid Philippine mobile number'); isValid = false; }
      }

      if (!provinceInput.value.trim()) { showFieldError('state-province', 'Please enter a province/state'); isValid = false; }
      if (!cityInput.value.trim()) { showFieldError('city', 'Please enter a city/municipality'); isValid = false; }

      const address = document.getElementById('address');
      if (address && !/\d/.test(address.value.trim())) { showFieldError('address', 'Please enter a valid street address including house/building number'); isValid = false; }

      const zipCode = document.getElementById('zip-code');
      if (zipCode && !/^\d{4}$/.test(zipCode.value.trim())) { showFieldError('zip-code', 'Please enter a valid 4-digit Philippine ZIP code'); isValid = false; }

      const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
      if (!paymentMethod) { showToast('Please select a payment method', 'error'); isValid = false; }

      const pm = paymentMethod ? paymentMethod.value : '';
      if (pm === 'credit-card') {
        const ccName = document.getElementById('cc-name');
        const ccNum = document.getElementById('cc-number');
        const ccExp = document.getElementById('cc-expiry');
        const ccCvv = document.getElementById('cc-cvv');
        if (!ccName.value.trim() || !/^[a-zA-Z\s\-\.]{2,}$/.test(ccName.value.trim())) { showFieldError('cc-name', 'Please enter the name on card'); isValid = false; }
        if (ccNum.value.replace(/\D/g, '').length < 13) { showFieldError('cc-number', 'Please enter a valid card number'); isValid = false; }
        if (!ccExp.value.trim() || !/^\d{2}\/\d{2}$/.test(ccExp.value.trim())) { showFieldError('cc-expiry', 'Please enter a valid expiry date (MM/YY)'); isValid = false; }
        if (!ccCvv.value.trim() || !/^\d{3,4}$/.test(ccCvv.value.trim())) { showFieldError('cc-cvv', 'Please enter a valid CVV'); isValid = false; }
      }
      return isValid;
    }

    function showConfirmModal() {
      if (!validatePhilippineAddress()) return;
      if (!buyNowItem) { showToast('No item selected', 'error'); return; }

      document.getElementById('confirm-name').textContent = document.getElementById('first-name').value.trim() + ' ' + document.getElementById('last-name').value.trim();
      document.getElementById('confirm-email').textContent = document.getElementById('email').value.trim();
      document.getElementById('confirm-phone').textContent = document.getElementById('phone').value.trim();
      document.getElementById('confirm-address').textContent = document.getElementById('address').value.trim();
      document.getElementById('confirm-city').textContent = cityInput.value.trim();
      document.getElementById('confirm-province').textContent = provinceInput.value.trim();
      document.getElementById('confirm-zip').textContent = document.getElementById('zip-code').value.trim();

      const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
      const methodLabels = { 'gcash': 'GCash', 'downpayment': 'Downpayment', 'credit-card': 'Credit / Debit Card' };
      document.getElementById('confirm-payment').textContent = methodLabels[paymentMethod.value] || paymentMethod.value;

      const item = buyNowItem;
      const price = parsePrice(item.price);
      const subtotal = price * item.quantity;
      document.getElementById('confirm-items').innerHTML = `<li><span>${item.name} x${item.quantity}</span><span>&#8369;${subtotal.toFixed(2)}</span></li>`;
      document.getElementById('confirm-total').textContent = '\u20B1' + subtotal.toFixed(2);
      document.getElementById('confirm-modal').classList.add('active');
    }

    function closeConfirmModal() { document.getElementById('confirm-modal').classList.remove('active'); }

    async function submitOrder() {
      closeConfirmModal();
      if (!buyNowItem) { showToast('No item selected', 'error'); return; }

      placeOrderBtn.classList.add('loading');
      placeOrderBtn.disabled = true;

      const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
      const subtotal = getItemTotal();
      const itemWeight = parseFloat(buyNowItem.weight || 0);
      const totalWeight = itemWeight * buyNowItem.quantity;
      const finalTotal = subtotal;
      
      const orderData = {
        contact: {
          firstName: document.getElementById('first-name').value.trim(),
          lastName: document.getElementById('last-name').value.trim(),
          email: document.getElementById('email').value.trim(),
          phone: document.getElementById('phone').value.trim(),
          company: document.getElementById('company').value.trim(),
        },
        address: {
          street: document.getElementById('address').value.trim(),
          city: cityInput.value.trim(),
          province: provinceInput.value.trim(),
          zipCode: document.getElementById('zip-code').value.trim(),
          country: document.getElementById('country').value,
          notes: document.getElementById('delivery-notes').value.trim(),
        },
        payment: { 
          method: paymentMethod, 
          upfront_method: paymentMethod === 'downpayment' ? document.querySelector('input[name="upfront_method"]:checked').value : '',
          gcash_ref_no: paymentMethod === 'gcash' ? document.getElementById('gcash-ref-no').value.trim() : (paymentMethod === 'downpayment' && document.querySelector('input[name="upfront_method"]:checked').value === 'gcash' ? document.getElementById('dp-gcash-ref').value.trim() : ''),
          downpayment_cc: paymentMethod === 'downpayment' && document.querySelector('input[name="upfront_method"]:checked').value === 'credit-card' ? { 
            name: document.getElementById('dp-cc-name').value.trim(), 
            number: document.getElementById('dp-cc-number').value.replace(/\s/g, ''), 
            expiry: document.getElementById('dp-cc-expiry').value.trim(), 
            cvv: document.getElementById('dp-cc-cvv').value.trim() 
          } : null,
          balance_method: paymentMethod === 'downpayment' ? document.querySelector('input[name="balance_method"]:checked').value : '',
          creditCard: paymentMethod === 'credit-card' ? { 
            name: document.getElementById('cc-name').value.trim(), 
            number: document.getElementById('cc-number').value.replace(/\s/g, ''), 
            expiry: document.getElementById('cc-expiry').value.trim(), 
            cvv: document.getElementById('cc-cvv').value.trim() 
          } : null,
        },
        items: [{ name: buyNowItem.name, price: buyNowItem.price, quantity: buyNowItem.quantity, product_id: buyNowItem.product_id || null, weight: itemWeight }],
        total: finalTotal,
        orderNotes: document.getElementById('order-notes').value.trim(),
      };

      try {
        const response = await fetch('../api/checkout.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify(orderData),
        });
        const result = await response.json();

        if (result.success) {
          const orderRef = result.order_reference || 'ORD-' + Date.now().toString().slice(-6);
          document.getElementById('modal-order-id').textContent = 'Order #' + orderRef;
          let paymentNote = '';
          switch(paymentMethod) {
            case 'gcash': paymentNote = 'Please pay via GCash using the QR code above and enter your reference number.'; break;
            case 'downpayment': paymentNote = 'We will process your upfront payment. We\'ll contact you for the remaining balance before delivery.'; break;
            case 'credit-card': paymentNote = 'Your card has been charged successfully.'; break;
          }
          document.getElementById('modal-payment-note').textContent = paymentNote;
          document.getElementById('order-modal').classList.add('active');
          showToast('Order placed successfully!', 'success');
        } else {
          showToast(result.message || 'Failed to place order.', 'error');
          placeOrderBtn.classList.remove('loading');
          placeOrderBtn.disabled = false;
        }
      } catch (error) {
        showToast('Network error. Please try again.', 'error');
        placeOrderBtn.classList.remove('loading');
        placeOrderBtn.disabled = false;
      }
    }

    placeOrderBtn.addEventListener('click', () => {
      if (!buyNowItem) { showToast('No item selected', 'error'); return; }
      showConfirmModal();
    });

    document.getElementById('order-modal').addEventListener('click', (e) => { if (e.target === e.currentTarget) e.currentTarget.classList.remove('active'); });
    document.getElementById('confirm-modal').addEventListener('click', (e) => { if (e.target === e.currentTarget) e.currentTarget.classList.remove('active'); });

    document.querySelectorAll('#checkout-content input, #checkout-content select, #checkout-content textarea').forEach(el => {
      el.addEventListener('input', () => clearFieldError(el.id));
      el.addEventListener('change', () => clearFieldError(el.id));
    });

    async function loadSavedAddresses() {
      const section = document.getElementById('saved-addresses-section');
      const list = document.getElementById('saved-addresses-list');
      try {
        const res = await fetch('../api/addresses.php', { credentials: 'include' });
        const data = await res.json();
        if (data.success && data.addresses && data.addresses.length > 0) {
          section.style.display = 'block';
          list.innerHTML = data.addresses.map(addr => `
            <div style="padding:0.85rem; background:white; border:2px solid #e2e8f0; border-radius:12px; cursor:pointer; transition:all 0.2s ease;" 
                 onclick="selectSavedAddress(this, ${addr.id})"
                 data-addr='${JSON.stringify(addr).replace(/'/g, "&#39;")}'>
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.25rem;">
                <strong style="font-size:0.88rem; color:#111827;">${escapeHtml(addr.label)}</strong>
                ${addr.is_default ? '<span style="font-size:0.65rem; background:rgba(233,30,142,0.1); color:#e91e8c; padding:0.1rem 0.4rem; border-radius:999px; font-weight:700;">DEFAULT</span>' : ''}
              </div>
              <div style="font-size:0.82rem; color:#475569; line-height:1.4;">${escapeHtml(addr.first_name + ' ' + addr.last_name)} &bull; ${escapeHtml(addr.phone)}<br>${escapeHtml(addr.street + ', ' + addr.city + ', ' + addr.province + ' ' + addr.zip_code)}</div>
            </div>
          `).join('');
        } else {
          section.style.display = 'none';
        }
      } catch (err) {
        section.style.display = 'none';
      }
    }

    function selectSavedAddress(el, id) {
      document.querySelectorAll('#saved-addresses-list > div').forEach(d => d.style.borderColor = '#e2e8f0');
      el.style.borderColor = '#e91e8c';
      el.style.background = 'rgba(233,30,142,0.03)';
      const addr = JSON.parse(el.dataset.addr);
      document.getElementById('address').value = addr.street;
      document.getElementById('city').value = addr.city;
      document.getElementById('state-province').value = addr.province;
      document.getElementById('zip-code').value = addr.zip_code;
      updateAddressPreview();
      showToast('Address selected', 'info');
    }

    function showAddressForm() {
      document.getElementById('saved-addresses-section').style.display = 'none';
      document.getElementById('address-form-section').style.display = 'block';
    }

    function escapeHtml(str) {
      if (!str) return '';
      return str.replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>').replace(/"/g, '"');
    }

    async function init() {
      const user = await loadUserSession();
      if (user) {
        if (user.name) { const parts = user.name.split(' '); if (parts[0]) document.getElementById('first-name').value = parts[0]; if (parts.slice(1).join(' ')) document.getElementById('last-name').value = parts.slice(1).join(' '); }
        if (user.email) document.getElementById('email').value = user.email;
        if (user.contact_number) document.getElementById('phone').value = user.contact_number;
      }
      handleBuyNow();
      renderCheckout();
      document.getElementById('year').textContent = new Date().getFullYear();
      await loadSavedAddresses();
    }

    init();
  })();

  function toggleProfileDropdown() {
    document.getElementById('profileDropdown').classList.toggle('active');
  }

  window.onclick = function(event) {
    if (!event.target.matches('.header-profile-btn') && !event.target.closest('.header-profile-dropdown-wrapper')) {
      const dd = document.getElementById('profileDropdown');
      if (dd && dd.classList.contains('active')) dd.classList.remove('active');
    }
  }

  function helpOpenModal(type) {
    const overlay = document.getElementById('helpOverlay');
    const title = document.getElementById('helpModalTitle');
    const body = document.getElementById('helpModalBody');
    overlay.classList.add('open');
    body.innerHTML = '<div style="text-align:center;padding:2rem;"><i class="fas fa-spinner fa-pulse" style="font-size:1.5rem;color:#e91e8c;"></i><p style="margin-top:0.75rem;color:#64748b;">Loading...</p></div>';
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
            '<div class="help-contact-item"><div class="help-contact-icon"><i class="fas fa-map-marker-alt"></i></div><div><div class="help-contact-label">Address</div><div class="help-contact-value">' + (meta.address || 'N/A') + '</div></div></div>' +
            '<div class="help-contact-item"><div class="help-contact-icon"><i class="fas fa-phone"></i></div><div><div class="help-contact-label">Phone</div><div class="help-contact-value">' + (meta.phone || 'N/A') + '</div></div></div>' +
            '<div class="help-contact-item"><div class="help-contact-icon"><i class="fas fa-envelope"></i></div><div><div class="help-contact-label">Email</div><div class="help-contact-value">' + (meta.email || 'N/A') + '</div></div></div>';
        } else {
          const faqs = d.meta && d.meta.faqs ? d.meta.faqs : [];
          title.innerHTML = '<i class="fas fa-question-circle"></i> ' + (d.title || 'Help Center');
          let html = d.subtitle ? '<p style="margin-bottom:1.25rem;">' + helpEsc(d.subtitle) + '</p>' : '';
          if (d.content) html += '<div style="margin-bottom:1.25rem;padding:1rem;background:rgba(233,30,140,0.04);border-radius:12px;border:1px solid rgba(233,30,140,0.08);"><p style="font-size:0.88rem;color:#475569;">' + helpEsc(d.content) + '</p></div>';
          if (faqs.length) {
            faqs.forEach((f, i) => {
              html += '<details class="help-faq"' + (i === 0 ? ' open' : '') + '><summary>' + helpEsc(f.question || '') + ' <i class="fas fa-chevron-down"></i></summary><div class="help-faq-answer">' + helpEsc(f.answer || '') + '</div></details>';
            });
          } else {
            html += '<div style="text-align:center;padding:2rem;color:#64748b;"><i class="fas fa-question-circle" style="font-size:2.5rem;display:block;margin-bottom:0.75rem;color:rgba(233,30,140,0.15);"></i><p>No FAQs yet. Check back soon.</p></div>';
          }
          body.innerHTML = html;
        }
      })
      .catch(() => { body.innerHTML = '<p style="color:#ef4444;">Failed to load. Please try again.</p>'; });
  }

  function helpCloseModal() {
    document.getElementById('helpOverlay').classList.remove('open');
  }

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') helpCloseModal();
  });

  function helpEsc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function toggleAccountMenu() {
    const toggle = document.getElementById('accountToggle');
    const submenu = document.getElementById('accountSubmenu');
    if (toggle && submenu) {
      toggle.classList.toggle('open');
      submenu.classList.toggle('open');
    }
  }
  </script>
  <script>navigator.sendBeacon('../api/track-visit.php?url=' + encodeURIComponent(location.pathname + location.search) + '&_=' + Date.now());</script>

  <div class="help-modal-overlay" id="helpOverlay" onclick="if(event.target===this)helpCloseModal()">
    <div class="help-modal-box">
      <div class="help-modal-header">
        <h2 id="helpModalTitle"></h2>
        <button class="help-modal-close" onclick="helpCloseModal()"><i class="fas fa-times"></i></button>
      </div>
      <div class="help-modal-body" id="helpModalBody"></div>
    </div>
  </div>
</body>
</html>
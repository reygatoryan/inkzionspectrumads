<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
$loggedIn = true;
$userName = trim($_SESSION['user_name'] ?? '');
$userInitials = '';
if ($userName !== '') {
    $parts = array_filter(preg_split('/\s+/', $userName));
    $userInitials = strtoupper(substr(reset($parts), 0, 1) . (count($parts) > 1 ? substr(next($parts), 0, 1) : ''));
}
if ($userInitials === '') $userInitials = 'U';
$isSeller = !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

require_once '../db-config.php';

$user_id = $_SESSION['user_id'];

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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>My Orders | Inkzion Spectrum Ads</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
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
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: var(--font); background: #F8FAFC; color: var(--text-primary); line-height: 1.6; overflow-x: hidden; }
    .dashboard-wrapper { display: flex; min-height: 100vh; }

    .products-sidebar {
      width: var(--sidebar-width); background: var(--sidebar-bg);
      border-right: 1px solid rgba(43, 76, 82, 0.1);
      padding: 0; position: fixed; top: 0; left: 0;
      height: 100vh; overflow-y: auto; z-index: 100;
      display: flex; flex-direction: column;
    }
    .products-sidebar::-webkit-scrollbar { width: 3px; }
    .products-sidebar::-webkit-scrollbar-thumb { background: rgba(43, 76, 82, 0.2); border-radius: 4px; }
    .sidebar-brand { display: flex; align-items: center; gap: 0.75rem; padding: 1.25rem 1.25rem 1rem; border-bottom: 1px solid rgba(43, 76, 82, 0.12); position: sticky; top: 0; background: var(--sidebar-bg); z-index: 2; }
    .sidebar-brand-img { width: 38px; height: 38px; border-radius: 10px; object-fit: contain; background: white; padding: 4px; box-shadow: 0 2px 6px rgba(43, 76, 82, 0.15); }
    .sidebar-brand-text { line-height: 1.2; }
    .sidebar-brand-name { font-size: 0.85rem; font-weight: 800; color: #1a1a2e; letter-spacing: 0.03em; display: block; }
    .sidebar-brand-sub { font-size: 0.6rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; }
    .sidebar-profile { padding: 0.85rem 1.25rem; display: flex; align-items: center; gap: 0.65rem; border-bottom: 1px solid rgba(43, 76, 82, 0.08); background: rgba(43, 76, 82, 0.03); }
    .sidebar-avatar { width: 30px; height: 30px; border-radius: 8px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.65rem; flex-shrink: 0; }
    .sidebar-profile-info h4 { font-size: 0.75rem; font-weight: 600; color: var(--text-primary); }
    .sidebar-profile-info p { font-size: 0.6rem; color: var(--text-muted); }
    .sidebar-menu { flex: 1; padding: 0.75rem 0; }
    .sidebar-section-title { font-size: 0.6rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; padding: 0.85rem 1.25rem 0.45rem; }
    .sidebar-menu-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 1.25rem; margin: 0 0.6rem; border-radius: 8px; font-size: 0.82rem; font-weight: 500; color: #4a4a5a; text-decoration: none; transition: var(--transition); position: relative; border-left: 3px solid transparent; }
    .sidebar-menu-item i { width: 20px; text-align: center; font-size: 0.85rem; color: #4A7C84; transition: var(--transition); }
    .sidebar-menu-item:hover { background: var(--sidebar-hover); color: var(--primary); border-left-color: var(--primary); transform: translateX(4px); }
    .sidebar-menu-item:hover i { color: var(--primary); transform: scale(1.1); }
    .sidebar-menu-item.active { background: var(--sidebar-active-bg); color: var(--primary); font-weight: 600; border-left-color: var(--primary); box-shadow: 0 2px 8px rgba(43, 76, 82, 0.08); }
    .sidebar-menu-item.active i { color: var(--primary); }
    .sidebar-footer { padding: 0.75rem 1.25rem; border-top: 1px solid rgba(43, 76, 82, 0.1); }
    .sidebar-footer-item { display: flex; align-items: center; gap: 0.65rem; padding: 0.5rem 0; font-size: 0.78rem; color: var(--text-muted); text-decoration: none; transition: var(--transition); }
    .sidebar-footer-item:hover { color: var(--primary); }
    .sidebar-footer-item i { width: 18px; font-size: 0.85rem; color: #4A7C84; }
    button.sidebar-footer-item { background: none; border: none; cursor: pointer; width: 100%; text-align: left; font: inherit; color: var(--text-muted); display: flex; align-items: center; gap: 0.65rem; padding: 0.5rem 0; font-size: 0.78rem; text-decoration: none; transition: var(--transition); }
    button.sidebar-footer-item:hover { color: var(--primary); }

    .products-main { flex: 1; margin-left: var(--sidebar-width); min-height: 100vh; }
    .top-header { background: white; border-bottom: 1px solid var(--border-color); padding: 0 2rem; position: sticky; top: 0; z-index: 50; }
    .top-header-inner { display: flex; align-items: center; justify-content: space-between; height: 72px; gap: 1.5rem; }
    .top-header-left { display: flex; align-items: center; gap: 1rem; }
    .hamburger-btn { display: none; width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--border-color); background: white; color: var(--text-secondary); cursor: pointer; align-items: center; justify-content: center; font-size: 1.1rem; transition: var(--transition); }
    .hamburger-btn:hover { border-color: var(--primary); color: var(--primary); }
    .top-header-title h1 { font-size: 1.35rem; font-weight: 800; color: var(--text-primary); line-height: 1.3; }
    .top-header-title p { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.05rem; }
    .top-header-right { display: flex; align-items: center; gap: 0.5rem; }
    .header-icon-btn { width: 42px; height: 42px; border-radius: 12px; border: 1px solid var(--border-color); background: white; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: var(--transition); position: relative; text-decoration: none; }
    .header-icon-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); transform: translateY(-1px); }
    .header-profile-btn { display: flex; align-items: center; gap: 0.6rem; padding: 0.35rem 0.75rem 0.35rem 0.35rem; border-radius: 50px; border: 1px solid var(--border-color); background: white; cursor: pointer; transition: var(--transition); text-decoration: none; color: inherit; }
    .header-profile-btn:hover { border-color: var(--primary); background: var(--primary-bg); }
    .header-profile-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.65rem; flex-shrink: 0; }
    .header-profile-name { font-size: 0.8rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; }
    .header-profile-arrow { font-size: 0.65rem; color: var(--text-muted); margin-left: 0.15rem; }
    .header-profile-dropdown-wrapper { position: relative; }
    .header-profile-dropdown-menu { position: absolute; top: calc(100% + 6px); right: 0; background: white; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 8px 24px rgba(0,0,0,0.1); padding: 0.5rem; width: 200px; z-index: 100; opacity: 0; visibility: hidden; transform: translateY(10px); transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s; }
    .header-profile-dropdown-menu.active { opacity: 1; visibility: visible; transform: translateY(0); }
    .header-profile-dropdown-item { display: flex; align-items: center; gap: 0.65rem; padding: 0.65rem 0.85rem; border-radius: 8px; font-size: 0.82rem; font-weight: 500; color: var(--text-primary); text-decoration: none; transition: var(--transition); cursor: pointer; border: none; background: none; width: 100%; font-family: var(--font); text-align: left; }
    .header-profile-dropdown-item i { width: 18px; text-align: center; font-size: 0.8rem; color: #4A7C84; transition: var(--transition); }
    .header-profile-dropdown-item:hover { background: var(--sidebar-hover); color: var(--primary); }
    .header-profile-dropdown-item:hover i { color: var(--primary); }
    .header-profile-dropdown-item.danger { color: var(--danger); }
    .header-profile-dropdown-item.danger i { color: var(--danger); }
    .header-profile-dropdown-item.danger:hover { background: var(--danger-bg); color: var(--danger); }
    .header-profile-dropdown-item.danger:hover i { color: var(--danger); }
    .content-area { padding: 1.5rem 2rem 2rem; }
    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 99; }
    .sidebar-overlay.active { display: block; }

    .page-heading { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
    .page-heading h1 { font-size: 1.35rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 0.6rem; }
    .page-heading h1 i { color: var(--primary); font-size: 1.2rem; }
    .page-heading p { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.15rem; }

    .order-tabs { display: flex; gap: 0.4rem; background: white; border: 1px solid var(--border-color); border-radius: 14px; padding: 0.4rem; margin-bottom: 1.5rem; overflow-x: auto; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .order-tab { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.2rem; padding: 0.65rem 0.5rem; border-radius: 10px; border: none; background: transparent; cursor: pointer; font-size: 0.72rem; font-weight: 600; color: #64748b; transition: all 0.2s ease; white-space: nowrap; font-family: var(--font); }
    .order-tab:hover { background: rgba(43, 76, 82,0.04); }
    .order-tab.active { background: linear-gradient(135deg, #2B4C52, #4A7C84); color: white; }
    .order-tab .tab-count { font-size: 0.6rem; background: rgba(0,0,0,0.08); padding: 0.1rem 0.45rem; border-radius: 999px; font-weight: 700; }
    .order-tab.active .tab-count { background: rgba(255,255,255,0.25); }

    .order-card { background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); transition: box-shadow 0.2s ease; }
    .order-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
    .order-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-light); }
    .order-header-left { display: flex; align-items: center; gap: 1rem; }
    .order-id { font-weight: 700; font-size: 1rem; color: #111827; }
    .order-date { font-size: 0.82rem; color: #64748b; }
    .order-status-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.85rem; border-radius: 999px; font-size: 0.78rem; font-weight: 700; text-transform: capitalize; }
    .badge-pending { background: rgba(255,193,7,0.12); color: #b8860b; border: 1px solid rgba(255,193,7,0.3); }
    .badge-shipped { background: rgba(59,130,246,0.12); color: #1d4ed8; border: 1px solid rgba(59,130,246,0.3); }
    .badge-completed { background: rgba(5,150,105,0.12); color: #047857; border: 1px solid rgba(5,150,105,0.3); }
    .badge-returned { background: rgba(249,115,22,0.12); color: #c2410c; border: 1px solid rgba(249,115,22,0.3); }
    .badge-cancelled { background: rgba(239,68,68,0.12); color: #dc2626; border: 1px solid rgba(239,68,68,0.3); }

    .progress-tracker { display: flex; align-items: center; justify-content: space-between; margin: 1.25rem 0; padding: 0 0.5rem; position: relative; }
    .progress-tracker::before { content: ''; position: absolute; top: 18px; left: 40px; right: 40px; height: 3px; background: #e2e8f0; z-index: 0; }
    .progress-step { display: flex; flex-direction: column; align-items: center; gap: 0.4rem; position: relative; z-index: 1; }
    .step-circle { width: 36px; height: 36px; border-radius: 50%; background: #e2e8f0; color: #94a3b8; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; transition: all 0.3s ease; }
    .step-circle i { font-size: 0.85rem; }
    .step-label { font-size: 0.7rem; font-weight: 600; color: #94a3b8; text-align: center; white-space: nowrap; }
    .progress-step.completed .step-circle { background: linear-gradient(135deg, #047857, #10b981); color: white; }
    .progress-step.completed .step-label { color: #047857; }
    .progress-step.active .step-circle { background: linear-gradient(135deg, #2B4C52, #4A7C84); color: white; box-shadow: 0 0 0 4px rgba(43, 76, 82,0.15); }
    .progress-step.active .step-label { color: #2B4C52; font-weight: 700; }
    .progress-step.cancelled .step-circle { background: rgba(239,68,68,0.12); color: #dc2626; }
    .progress-step.cancelled .step-label { color: #dc2626; }

    .order-items { display: flex; flex-direction: column; gap: 0.6rem; }
    .order-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem; background: #f8fafc; border-radius: 12px; }
    .order-item:hover { background: #f1f5f9; }
    .order-item-img { width: 52px; height: 52px; border-radius: 10px; object-fit: cover; background: white; border: 1px solid var(--border-light); flex-shrink: 0; }
    .order-item-info { flex: 1; min-width: 0; }
    .order-item-name { font-size: 0.88rem; font-weight: 600; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .order-item-meta { font-size: 0.78rem; color: #64748b; margin-top: 0.15rem; }
    .order-item-price { font-size: 0.9rem; font-weight: 700; color: #2B4C52; white-space: nowrap; }
    .order-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-light); flex-wrap: wrap; gap: 0.5rem; }
    .order-total { font-size: 1rem; color: #111827; }
    .order-total strong { color: #2B4C52; font-size: 1.15rem; }
    .order-actions { display: flex; gap: 0.5rem; }
    .btn-order-action { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.82rem; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s ease; }
    .btn-order-action.primary { background: rgba(43, 76, 82,0.08); color: #2B4C52; border: 1px solid rgba(43, 76, 82,0.2); }
    .btn-order-action.primary:hover { background: rgba(43, 76, 82,0.15); }
    .btn-order-action.danger { background: rgba(239,68,68,0.08); color: #dc2626; border: 1px solid rgba(239,68,68,0.2); }
    .btn-order-action.danger:hover { background: rgba(239,68,68,0.15); }

    .orders-empty { text-align: center; padding: 3rem 2rem; background: white; border: 2px dashed var(--border-color); border-radius: 20px; }
    .orders-empty-icon { font-size: 3rem; color: rgba(43, 76, 82,0.15); margin-bottom: 1rem; }
    .orders-empty h3 { color: #111827; margin: 0 0 0.5rem; font-size: 1.2rem; }
    .orders-empty p { color: #64748b; margin: 0 0 1.5rem; font-size: 0.92rem; }

    @media (max-width: 768px) {
      .products-sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
      .products-sidebar.open { transform: translateX(0); }
      .hamburger-btn { display: flex; }
      .products-main { margin-left: 0; }
      .content-area { padding: 1rem; }
      .order-tabs { overflow-x: auto; }
    }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; padding: 1.5rem; }
    .modal-overlay.open { display: flex; animation: fadeIn 0.25s ease; }
    .modal-box { background: white; border-radius: 24px; max-width: 640px; width: 100%; max-height: 85vh; overflow-y: auto; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.2); animation: scaleIn 0.25s ease; }
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
  </style>
</head>
<body>
<div class="dashboard-wrapper">
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
      <a href="notifications.php" class="sidebar-menu-item"><i class="fas fa-bell"></i> Notifications</a>
      <a href="chat.php" class="sidebar-menu-item"><i class="fas fa-comments"></i> Messages</a>
      <div class="sidebar-section-title" style="padding-top:0.5rem;">Orders</div>
      <a href="my-orders.php" class="sidebar-menu-item active"><i class="fas fa-box"></i> My Orders</a>
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
    </nav>
    <div class="sidebar-footer">
      <button class="sidebar-footer-item" onclick="openModal('help')"><i class="fas fa-question-circle"></i> Help Center</button>
    </div>
  </aside>
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

  <main class="products-main">
    <header class="top-header">
      <div class="top-header-inner">
        <div class="top-header-left">
          <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
          <div class="top-header-title">
            <h1>My Orders</h1>
            <p>Track and manage your orders</p>
          </div>
        </div>
        <div class="top-header-right">
          <a href="../index.php" class="header-icon-btn" title="Home"><i class="fas fa-home"></i></a>
          <a href="notifications.php" class="header-icon-btn" title="Notifications" style="position:relative;"><i class="fas fa-bell"></i></a>
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
      <div class="page-heading">
        <h1><i class="fas fa-box"></i> My Orders</h1>
      </div>
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
            <a href="store-product.php" class="btn-order-action primary" style="display:inline-flex;"><i class="fas fa-shopping-bag"></i> Browse Products</a>
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
                <button type="button" class="btn-order-action danger" onclick="cancelOrder(<?= (int)$order['id'] ?>, this)"><i class="fas fa-times"></i> Cancel</button>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)closeModal()">
  <div class="modal-box" style="background:white;border-radius:24px;max-width:640px;width:100%;max-height:85vh;overflow-y:auto;box-shadow:0 24px 80px rgba(15,23,42,0.2);animation:scaleIn 0.25s ease;">
    <div class="modal-header" style="position:sticky;top:0;background:white;display:flex;align-items:center;justify-content:space-between;padding:1.5rem 1.5rem 1rem;border-bottom:1px solid #f1f5f9;">
      <h2 id="modalTitle" style="font-size:1.2rem;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:0.5rem;"></h2>
      <button class="modal-close" onclick="closeModal()" style="width:36px;height:36px;border-radius:50%;border:none;background:#f1f5f9;color:#64748b;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.9rem;"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="modalBody" style="padding:1.5rem;"></div>
  </div>
</div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('active');
}
function toggleProfileDropdown() {
  document.getElementById('profileDropdown').classList.toggle('active');
}
document.addEventListener('click', function(e) {
  const dd = document.getElementById('profileDropdown');
  const btn = document.querySelector('.header-profile-btn');
  if (dd.classList.contains('active') && !dd.contains(e.target) && !btn.contains(e.target)) {
    dd.classList.remove('active');
  }
});

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

function cancelOrder(orderId, button) {
  if (!confirm('Cancel this order?')) return;

  const originalText = button.innerHTML;
  button.disabled = true;
  button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

  fetch('../api/update-order-status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ order_id: orderId, status: 'cancelled' })
  })
    .then(async (response) => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data.success) {
        throw new Error(data.message || 'Unable to cancel the order.');
      }
      window.location.reload();
    })
    .catch((error) => {
      button.disabled = false;
      button.innerHTML = originalText;
      alert(error.message || 'Unable to cancel the order.');
    });
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
          '<div class="modal-contact-item" style="display:flex;gap:1rem;padding:1rem;background:#f8fafc;border-radius:14px;margin-bottom:0.75rem;align-items:flex-start;"><div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#2B4C52,#4A7C84);color:white;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;"><i class="fas fa-map-marker-alt"></i></div><div><div style="font-size:0.78rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.15rem;">Address</div><div style="font-size:0.92rem;font-weight:500;color:#0f172a;">' + (meta.address || 'N/A') + '</div></div></div>' +
          '<div class="modal-contact-item" style="display:flex;gap:1rem;padding:1rem;background:#f8fafc;border-radius:14px;margin-bottom:0.75rem;align-items:flex-start;"><div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#2B4C52,#4A7C84);color:white;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;"><i class="fas fa-phone"></i></div><div><div style="font-size:0.78rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.15rem;">Phone</div><div style="font-size:0.92rem;font-weight:500;color:#0f172a;">' + (meta.phone || 'N/A') + '</div></div></div>' +
          '<div class="modal-contact-item" style="display:flex;gap:1rem;padding:1rem;background:#f8fafc;border-radius:14px;margin-bottom:0.75rem;align-items:flex-start;"><div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#2B4C52,#4A7C84);color:white;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;"><i class="fas fa-envelope"></i></div><div><div style="font-size:0.78rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.15rem;">Email</div><div style="font-size:0.92rem;font-weight:500;color:#0f172a;">' + (meta.email || 'N/A') + '</div></div></div>';
      } else {
        const faqs = d.meta && d.meta.faqs ? d.meta.faqs : [];
        title.innerHTML = '<i class="fas fa-question-circle"></i> ' + (d.title || 'Help Center');
        let html = d.subtitle ? '<p style="margin-bottom:1.25rem;color:#475569;">' + esc(d.subtitle) + '</p>' : '';
        if (d.content) html += '<div style="margin-bottom:1.25rem;padding:1rem;background:rgba(43, 76, 82,0.04);border-radius:12px;border:1px solid rgba(43, 76, 82,0.08);"><p style="font-size:0.88rem;color:#475569;">' + esc(d.content) + '</p></div>';
        if (faqs.length) {
          faqs.forEach((f, i) => {
            html += '<details class="modal-faq" style="border:1px solid #e2e8f0;border-radius:12px;margin-bottom:0.6rem;overflow:hidden;"' + (i === 0 ? ' open' : '') + '><summary style="padding:1rem 1.25rem;font-size:0.9rem;font-weight:600;color:#0f172a;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:0.5rem;">' + esc(f.question || '') + ' <i class="fas fa-chevron-down" style="font-size:0.75rem;color:#94a3b8;transition:transform 0.2s;"></i></summary><div class="modal-faq-answer" style="padding:1rem 1.25rem;font-size:0.88rem;color:#475569;line-height:1.7;">' + esc(f.answer || '') + '</div></details>';
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
  if (e.key === 'Escape') closeModal();
});
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function toggleAccountMenu() {
      const toggle = document.getElementById('accountToggle');
      const submenu = document.getElementById('accountSubmenu');
      if (toggle && submenu) {
        toggle.classList.toggle('open');
        submenu.classList.toggle('open');
      }
    }
  </script>
</body>
</html>
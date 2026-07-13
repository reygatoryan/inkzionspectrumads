<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
$userId = (int)$_SESSION['user_id'];
$userName = trim($_SESSION['user_name'] ?? '');
$userInitials = '';
if ($userName !== '') {
    $parts = array_filter(preg_split('/\s+/', $userName));
    $userInitials = strtoupper(substr(reset($parts), 0, 1) . (count($parts) > 1 ? substr(next($parts), 0, 1) : ''));
}
if ($userInitials === '') $userInitials = 'U';
$isSeller = !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

$requestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$request = null;
if ($requestId) {
    $resp = @file_get_contents('http' . (!empty($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/../api/custom-printing.php?action=get&id=' . $requestId, false, stream_context_create(['http' => ['header' => 'Cookie: ' . $_SERVER['HTTP_COOKIE']]]));
    if ($resp) {
        $data = json_decode($resp, true);
        if ($data['success']) $request = $data['request'];
    }
}
$title = $request ? htmlspecialchars($request['service_type'] ?? 'Custom Request') : 'Custom Request';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Custom Request Form | Inkzion Spectrum Ads</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --sidebar-bg: #FFF5F8; --sidebar-hover: rgba(233,30,140,0.08);
      --sidebar-active: #e91e8c; --sidebar-active-bg: rgba(233,30,140,0.12);
      --sidebar-width: 270px; --primary: #e91e8c; --primary-light: #9c27b0;
      --primary-bg: rgba(233,30,140,0.1); --text-primary: #0F172A;
      --text-secondary: #475569; --text-muted: #64748B;
      --border-color: #E2E8F0; --border-light: #F1F5F9;
      --font: 'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
      --transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: var(--font); background: #F8FAFC; color: var(--text-primary); line-height: 1.6; overflow-x: hidden; }
    .dashboard-wrapper { display: flex; min-height: 100vh; }
    .products-sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); border-right: 1px solid rgba(233,30,140,0.1); padding: 0; position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 100; display: flex; flex-direction: column; }
    .products-sidebar::-webkit-scrollbar { width: 3px; }
    .products-sidebar::-webkit-scrollbar-thumb { background: rgba(233,30,140,0.2); border-radius: 4px; }
    .sidebar-brand { display: flex; align-items: center; gap: 0.75rem; padding: 1.25rem 1.25rem 1rem; border-bottom: 1px solid rgba(233,30,140,0.12); position: sticky; top: 0; background: var(--sidebar-bg); z-index: 2; }
    .sidebar-brand-img { width: 38px; height: 38px; border-radius: 10px; object-fit: contain; background: white; padding: 4px; box-shadow: 0 2px 6px rgba(233,30,140,0.15); }
    .sidebar-brand-text { line-height: 1.2; }
    .sidebar-brand-name { font-size: 0.85rem; font-weight: 800; color: #1a1a2e; display: block; }
    .sidebar-brand-sub { font-size: 0.6rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; }
    .sidebar-profile { padding: 0.85rem 1.25rem; display: flex; align-items: center; gap: 0.65rem; border-bottom: 1px solid rgba(233,30,140,0.08); background: rgba(233,30,140,0.03); }
    .sidebar-avatar { width: 30px; height: 30px; border-radius: 8px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.65rem; flex-shrink: 0; }
    .sidebar-profile-info h4 { font-size: 0.75rem; font-weight: 600; color: var(--text-primary); }
    .sidebar-profile-info p { font-size: 0.6rem; color: var(--text-muted); }
    .sidebar-menu { flex: 1; padding: 0.75rem 0; }
    .sidebar-section-title { font-size: 0.6rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; padding: 0.85rem 1.25rem 0.45rem; }
    .sidebar-menu-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 1.25rem; margin: 0 0.6rem; border-radius: 8px; font-size: 0.82rem; font-weight: 500; color: #4a4a5a; text-decoration: none; transition: var(--transition); border-left: 3px solid transparent; }
    .sidebar-menu-item i { width: 20px; text-align: center; font-size: 0.85rem; color: #b06ab3; transition: var(--transition); }
    .sidebar-menu-item:hover { background: var(--sidebar-hover); color: var(--primary); border-left-color: var(--primary); transform: translateX(4px); }
    .sidebar-menu-item:hover i { color: var(--primary); transform: scale(1.1); }
    .sidebar-menu-item.active { background: var(--sidebar-active-bg); color: var(--primary); font-weight: 600; border-left-color: var(--primary); }
    .sidebar-menu-item.active i { color: var(--primary); }
    .sidebar-footer { padding: 0.75rem 1.25rem; border-top: 1px solid rgba(233,30,140,0.1); }
    .sidebar-footer-item { display: flex; align-items: center; gap: 0.65rem; padding: 0.5rem 0; font-size: 0.78rem; color: var(--text-muted); text-decoration: none; transition: var(--transition); }
    .sidebar-footer-item:hover { color: var(--primary); }
    .sidebar-footer-item i { width: 18px; font-size: 0.85rem; color: #b06ab3; }
    .sidebar-footer button.sidebar-footer-item { background: none; border: none; cursor: pointer; width: 100%; text-align: left; font: inherit; color: var(--text-muted); display: flex; align-items: center; gap: 0.65rem; padding: 0.5rem 0; font-size: 0.78rem; text-decoration: none; transition: var(--transition); }
    .sidebar-footer button.sidebar-footer-item:hover { color: var(--primary); }
    .products-main { flex: 1; margin-left: var(--sidebar-width); min-height: 100vh; }
    .top-header { background: white; border-bottom: 1px solid var(--border-color); padding: 0 2rem; position: sticky; top: 0; z-index: 50; }
    .top-header-inner { display: flex; align-items: center; justify-content: space-between; height: 72px; gap: 1.5rem; }
    .top-header-left { display: flex; align-items: center; gap: 1rem; }
    .hamburger-btn { display: none; width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--border-color); background: white; color: var(--text-secondary); cursor: pointer; font-size: 1.1rem; transition: var(--transition); }
    .hamburger-btn:hover { border-color: var(--primary); color: var(--primary); }
    .top-header-title h1 { font-size: 1.35rem; font-weight: 800; color: var(--text-primary); }
    .top-header-title p { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.05rem; }
    .top-header-right { display: flex; align-items: center; gap: 0.5rem; }
    .header-icon-btn { width: 42px; height: 42px; border-radius: 12px; border: 1px solid var(--border-color); background: white; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: var(--transition); text-decoration: none; }
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
    .header-profile-dropdown-item i { width: 18px; text-align: center; font-size: 0.8rem; color: #b06ab3; }
    .header-profile-dropdown-item:hover { background: var(--sidebar-hover); color: var(--primary); }
    .header-profile-dropdown-item.danger { color: var(--danger); }
    .header-profile-dropdown-item.danger:hover { background: var(--danger-bg); color: var(--danger); }
    .content-area { padding: 1.5rem 2rem 2rem; max-width: 720px; margin: 0 auto; }
    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 99; }
    .sidebar-overlay.active { display: block; }
    .page-heading { margin-bottom: 1.5rem; }
    .page-heading h1 { font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 0.6rem; }
    .page-heading h1 i { color: var(--primary); }
    .page-heading p { color: var(--text-muted); margin-top: 0.15rem; }
    .form-card { background: white; border: 1px solid var(--border-color); border-radius: 20px; padding: 2rem; box-shadow: 0 4px 16px rgba(0,0,0,0.04); margin-bottom: 1.5rem; }
    .form-card h2 { font-size: 1.1rem; font-weight: 700; margin: 0 0 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
    .form-card h2 i { color: var(--primary); }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-grid .full-width { grid-column: 1 / -1; }
    .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
    .form-group label { font-size: 0.85rem; font-weight: 600; color: #334155; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--border-color); border-radius: 10px; font-size: 0.9rem; font-family: inherit; color: var(--text-primary); background: white; outline: none; transition: border-color 0.2s; box-sizing: border-box; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
    .form-group textarea { min-height: 100px; resize: vertical; }
    .item-row { display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem; padding: 0.5rem; background: #f8fafc; border-radius: 10px; border: 1px solid var(--border-color); }
    .item-row input { padding: 0.5rem 0.75rem; border: 1.5px solid var(--border-color); border-radius: 8px; font-size: 0.85rem; outline: none; font-family: inherit; }
    .item-row input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
    .item-row .item-remove { width: 32px; height: 32px; border-radius: 8px; border: none; background: rgba(239,68,68,0.1); color: #ef4444; cursor: pointer; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .item-row .item-remove:hover { background: #ef4444; color: white; }
    .item-image-wrap { flex: 0 0 64px; width: 64px; height: 64px; position: relative; flex-shrink: 0; }
    .item-image-btn { width: 64px; height: 64px; border-radius: 8px; border: 1.5px dashed var(--border-color); background: white; color: #94a3b8; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; transition: var(--transition); }
    .item-image-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); }
    .item-image-preview { width: 64px; height: 64px; border-radius: 8px; object-fit: cover; cursor: pointer; border: 1px solid var(--border-color); display: none; transition: var(--transition); }
    .item-image-preview:hover { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
    .btn-submit { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.85rem 2rem; border: none; border-radius: 12px; font-size: 0.95rem; font-weight: 700; cursor: pointer; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; font-family: inherit; transition: all 0.2s ease; box-shadow: 0 6px 16px rgba(233,30,142,0.25); }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(233,30,142,0.35); }
    .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
    .flash-msg { padding: 0.85rem 1.2rem; border-radius: 12px; font-weight: 600; font-size: 0.9rem; margin-bottom: 1rem; }
    .flash-success { background: rgba(5,150,105,0.1); color: #047857; border: 1px solid rgba(5,150,105,0.2); }
    .flash-error { background: rgba(239,68,68,0.1); color: #dc2626; border: 1px solid rgba(239,68,68,0.2); }
    @keyframes spin { to { transform: rotate(360deg); } }
    @media (max-width: 1024px) {
      .products-sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
      .products-sidebar.open { transform: translateX(0); }
      .products-main { margin-left: 0; }
      .hamburger-btn { display: flex !important; }
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
    }

    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; padding: 1.5rem; }
    .modal-overlay.open { display: flex; animation: fadeIn 0.25s ease; }
    .modal-box { background: white; border-radius: 24px; max-width: 640px; width: 100%; max-height: 85vh; overflow-y: auto; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.2); animation: scaleIn 0.25s ease; }
    .modal-header { position: sticky; top: 0; background: white; display: flex; align-items: center; justify-content: space-between; padding: 1.5rem 1.5rem 1rem; border-bottom: 1px solid #f1f5f9; }
    .modal-header h2 { font-size: 1.2rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.5rem; }
    .modal-header h2 i { color: var(--primary); }
    .modal-close { width: 36px; height: 36px; border-radius: 50%; border: none; background: #f1f5f9; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.2s; }
    .modal-close:hover { background: #e2e8f0; color: #0f172a; }
    .modal-body { padding: 1.5rem; }
    .modal-body p { font-size: 0.92rem; color: #475569; line-height: 1.7; }
    .modal-contact-item { display: flex; gap: 1rem; padding: 1rem; background: #f8fafc; border-radius: 14px; margin-bottom: 0.75rem; align-items: flex-start; }
    .modal-contact-icon { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, var(--primary), #9c27b0); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
    .modal-contact-label { font-size: 0.78rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.15rem; }
    .modal-contact-value { font-size: 0.92rem; font-weight: 500; color: #0f172a; }
    .modal-faq { border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 0.6rem; overflow: hidden; }
    .modal-faq summary { padding: 1rem 1.25rem; font-size: 0.9rem; font-weight: 600; color: #0f172a; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
    .modal-faq summary i { font-size: 0.75rem; color: #94a3b8; transition: transform 0.2s; }
    .modal-faq[open] summary { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .modal-faq[open] summary i { transform: rotate(180deg); }
    .modal-faq-answer { padding: 1rem 1.25rem; font-size: 0.88rem; color: #475569; line-height: 1.7; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes scaleIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
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
      <a href="my-orders.php" class="sidebar-menu-item"><i class="fas fa-box"></i> My Orders</a>
      <a href="my-requests.php" class="sidebar-menu-item active"><i class="fas fa-clipboard-list"></i> My Requests</a>
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
              <h1>Custom Request</h1>
              <p><?php echo $title; ?></p>
            </div>
          </div>
          <div class="top-header-right">
            <a href="../index.php" class="header-icon-btn" title="Home"><i class="fas fa-home"></i></a>
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
          <h1><i class="fas fa-paint-brush"></i> <?php echo $title; ?></h1>
          <p>Fill in the details for your custom request. The admin will review and get back to you.</p>
        </div>
        <?php if ($request && $request['status'] !== 'pending'): ?>
        <div class="flash-msg flash-success"><i class="fas fa-check-circle"></i> This request has been submitted and is <strong><?php echo htmlspecialchars($request['status']); ?></strong>.</div>
        <?php endif; ?>
        <div id="success-msg" class="flash-msg flash-success" style="display:none;"></div>
        <div id="error-msg" class="flash-msg flash-error" style="display:none;"></div>
        <form id="request-form" class="<?php echo ($request && $request['status'] !== 'pending') ? 'hidden' : ''; ?>" style="<?php echo ($request && $request['status'] !== 'pending') ? 'display:none;' : ''; ?>">
          <div class="form-card">
            <h2><i class="fas fa-print"></i> Request Details</h2>
            <div class="form-grid">
              <div class="form-group full-width">
                <label>Service Type</label>
                <input type="text" value="<?php echo htmlspecialchars($request['service_type'] ?? $title); ?>" readonly style="background:#f8fafc;">
              </div>
              <div class="form-group">
                <label for="material">Material <span style="font-weight:400;color:#94a3b8;">(fabric type)</span></label>
                <input type="text" id="material" name="material" placeholder="e.g., Cotton, Polyester, Gildan 5000, etc.">
              </div>
              <div class="form-group full-width">
                <label>Order Items <span style="font-weight:400;color:#94a3b8;">(size, quantity &amp; reference image per item)</span></label>
                <div id="items-container">
                  <div class="item-row">
                    <input type="text" class="item-size" placeholder="Size (e.g., Small, Large, XL, 2XL)" style="flex:2;">
                    <input type="number" class="item-qty" placeholder="Qty" min="1" value="1" style="flex:1;">
                    <div class="item-image-wrap">
                      <input type="file" class="item-image-input" accept="image/*" style="display:none;">
                      <div class="item-image-btn" onclick="this.previousElementSibling.click()" title="Upload reference image">
                        <i class="fas fa-camera"></i>
                      </div>
                      <img class="item-image-preview" style="display:none;" onclick="openItemPreview(this)">
                    </div>
                    <button type="button" class="item-remove" onclick="removeItemRow(this)" style="display:none;" title="Remove">&times;</button>
                  </div>
                </div>
                <button type="button" onclick="addItemRow()" style="margin-top:0.5rem;padding:0.5rem 1rem;border:1.5px dashed var(--border-color);border-radius:10px;background:none;color:var(--primary);font-weight:600;font-size:0.85rem;cursor:pointer;width:100%;"><i class="fas fa-plus"></i> Add Another Size</button>
              </div>
              <div class="form-group">
                <label for="deadline">Preferred Deadline</label>
                <input type="date" id="deadline" name="preferred_deadline">
              </div>
              <div class="form-group full-width">
                <label for="special_requests">Description / Special Instructions</label>
                <textarea id="special_requests" name="special_requests" placeholder="Describe your requirements, preferences, or any special instructions..."></textarea>
              </div>
            </div>
          </div>
          <div style="text-align:center;margin-top:1rem;">
            <button type="submit" class="btn-submit" id="submit-btn">
              <span class="btn-text"><i class="fas fa-paper-plane"></i> Submit Details</span>
              <span class="btn-spinner" style="display:none;width:20px;height:20px;border:2.5px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%;animation:spin 0.7s linear infinite;"></span>
            </button>
          </div>
        </form>
        <?php if ($request && $request['status'] !== 'pending'): ?>
        <div style="text-align:center;margin-top:1.5rem;">
          <a href="chat.php?conversation=<?php echo $request['chat_conversation_id'] ?? ''; ?>" class="btn-submit" style="text-decoration:none;display:inline-flex;"><i class="fas fa-comments"></i> Back to Chat</a>
        </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
  <script>
    function addItemRow() {
      const container = document.getElementById('items-container');
      const first = container.querySelector('.item-row');
      const clone = first.cloneNode(true);
      clone.querySelector('.item-size').value = '';
      clone.querySelector('.item-qty').value = '1';
      clone.querySelector('.item-image-preview').style.display = 'none';
      clone.querySelector('.item-image-btn').style.display = 'flex';
      clone.querySelector('.item-image-input').value = '';
      const removeBtn = clone.querySelector('.item-remove');
      removeBtn.style.display = 'inline-flex';
      container.appendChild(clone);
      attachItemImageHandler(clone.querySelector('.item-image-input'));
    }
    function removeItemRow(btn) {
      const container = document.getElementById('items-container');
      if (container.querySelectorAll('.item-row').length > 1) {
        btn.closest('.item-row').remove();
      }
    }
    function attachItemImageHandler(input) {
      input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        if (file.size > 20 * 1024 * 1024) { alert('Image must be less than 20MB'); return; }
        const reader = new FileReader();
        reader.onload = function(ev) {
          const row = input.closest('.item-row');
          row.querySelector('.item-image-btn').style.display = 'none';
          const preview = row.querySelector('.item-image-preview');
          preview.src = ev.target.result;
          preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
      });
    }
    function openItemPreview(img) {
      const overlay = document.getElementById('imgPreviewOverlay');
      const fullImg = document.getElementById('imgPreviewFull');
      fullImg.src = img.src;
      overlay.classList.add('open');
    }

    // Pre-populate from existing request data
    <?php if ($request && !empty($request['material']) || ($request && !empty($request['items']))): ?>
    (function() {
      <?php if (!empty($request['material'])): ?>
      document.getElementById('material').value = <?php echo json_encode($request['material']); ?>;
      <?php endif; ?>
      <?php if (!empty($request['items'])): ?>
      var existingItems = <?php echo json_encode(is_string($request['items']) ? json_decode($request['items'], true) : $request['items']); ?>;
      if (existingItems && Array.isArray(existingItems) && existingItems.length) {
        var container = document.getElementById('items-container');
        container.innerHTML = '';
        existingItems.forEach(function(it) {
          var row = document.createElement('div');
          row.className = 'item-row';
          var hasImg = it.image && it.image.length > 100;
          row.innerHTML =
            '<input type="text" class="item-size" placeholder="Size (e.g., Small, Large, XL, 2XL)" style="flex:2;" value="' + esc(it.size||'') + '">' +
            '<input type="number" class="item-qty" placeholder="Qty" min="1" value="' + (it.qty||1) + '" style="flex:1;">' +
            '<div class="item-image-wrap">' +
              '<input type="file" class="item-image-input" accept="image/*" style="display:none;">' +
              '<div class="item-image-btn" onclick="this.previousElementSibling.click()" title="Upload reference image"' + (hasImg ? ' style="display:none;"' : '') + '><i class="fas fa-camera"></i></div>' +
              '<img class="item-image-preview" onclick="openItemPreview(this)"' + (hasImg ? ' style="display:block;" src="' + esc(it.image) + '"' : ' style="display:none;"') + '>' +
            '</div>' +
            '<button type="button" class="item-remove" onclick="removeItemRow(this)" title="Remove"' + (existingItems.length < 2 ? ' style="display:none;"' : '') + '>&times;</button>';
          container.appendChild(row);
          var inp = row.querySelector('.item-image-input');
          if (!hasImg) attachItemImageHandler(inp);
        });
      }
      <?php endif; ?>
    })();
    <?php endif; ?>

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
    <?php if ($request && $request['status'] === 'pending'): ?>
    document.getElementById('request-form').addEventListener('submit', async function(e) {
      e.preventDefault();
      const btn = document.getElementById('submit-btn');
      btn.disabled = true;
      btn.querySelector('.btn-text').style.display = 'none';
      btn.querySelector('.btn-spinner').style.display = 'block';
      try {
        const res = await fetch('../api/custom-printing.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            action: 'submit_details',
            request_id: <?php echo $requestId; ?>,
            material: document.getElementById('material').value,
            items: (function(){
              const rows = document.querySelectorAll('#items-container .item-row');
              const arr = [];
              rows.forEach(function(r){
                const size = r.querySelector('.item-size').value.trim();
                const qty = parseInt(r.querySelector('.item-qty').value) || 1;
                const preview = r.querySelector('.item-image-preview');
                const image = (preview && preview.style.display !== 'none' && preview.src) ? preview.src : '';
                if (size) arr.push({size: size, qty: qty, image: image});
              });
              return arr;
            })(),
            special_requests: document.getElementById('special_requests').value,
            preferred_deadline: document.getElementById('deadline').value
          })
        });
        const data = await res.json();
        if (data.success) {
          document.getElementById('success-msg').textContent = 'Request details submitted successfully! The admin will review it shortly.';
          document.getElementById('success-msg').style.display = 'block';
          document.getElementById('error-msg').style.display = 'none';
          document.getElementById('request-form').style.display = 'none';
        } else {
          document.getElementById('error-msg').textContent = data.error || 'Failed to submit';
          document.getElementById('error-msg').style.display = 'block';
        }
      } catch (err) {
        document.getElementById('error-msg').textContent = 'Network error. Please try again.';
        document.getElementById('error-msg').style.display = 'block';
      }
      btn.disabled = false;
      btn.querySelector('.btn-text').style.display = 'inline-flex';
      btn.querySelector('.btn-spinner').style.display = 'none';
    });
    <?php endif; ?>

    function openModal(type) {
      const overlay = document.getElementById('modalOverlay');
      const title = document.getElementById('modalTitle');
      const body = document.getElementById('modalBody');
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
              '<div class="modal-contact-item"><div class="modal-contact-icon"><i class="fas fa-map-marker-alt"></i></div><div><div class="modal-contact-label">Address</div><div class="modal-contact-value">' + (meta.address || 'N/A') + '</div></div></div>' +
              '<div class="modal-contact-item"><div class="modal-contact-icon"><i class="fas fa-phone"></i></div><div><div class="modal-contact-label">Phone</div><div class="modal-contact-value">' + (meta.phone || 'N/A') + '</div></div></div>' +
              '<div class="modal-contact-item"><div class="modal-contact-icon"><i class="fas fa-envelope"></i></div><div><div class="modal-contact-label">Email</div><div class="modal-contact-value">' + (meta.email || 'N/A') + '</div></div></div>';
          } else {
            const faqs = d.meta && d.meta.faqs ? d.meta.faqs : [];
            title.innerHTML = '<i class="fas fa-question-circle"></i> ' + (d.title || 'Help Center');
            let html = d.subtitle ? '<p style="margin-bottom:1.25rem;">' + esc(d.subtitle) + '</p>' : '';
            if (d.content) html += '<div style="margin-bottom:1.25rem;padding:1rem;background:rgba(233,30,140,0.04);border-radius:12px;border:1px solid rgba(233,30,140,0.08);"><p style="font-size:0.88rem;color:#475569;">' + esc(d.content) + '</p></div>';
            if (faqs.length) {
              faqs.forEach((f, i) => {
                html += '<details class="modal-faq"' + (i === 0 ? ' open' : '') + '><summary>' + esc(f.question || '') + ' <i class="fas fa-chevron-down"></i></summary><div class="modal-faq-answer">' + esc(f.answer || '') + '</div></details>';
              });
            } else {
              html += '<div style="text-align:center;padding:2rem;color:#64748b;"><i class="fas fa-question-circle" style="font-size:2.5rem;display:block;margin-bottom:0.75rem;color:rgba(233,30,140,0.15);"></i><p>No FAQs yet. Check back soon.</p></div>';
            }
            body.innerHTML = html;
          }
        })
        .catch(() => { body.innerHTML = '<p style="color:#ef4444;">Failed to load. Please try again.</p>'; });
    }

    function closeModal() {
      document.getElementById('modalOverlay').classList.remove('open');
    }

    function closeImgPreview() {
      document.getElementById('imgPreviewOverlay').classList.remove('open');
    }

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') { closeModal(); closeImgPreview(); }
    });

    function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    // Init: attach image handler to first row
    document.addEventListener('DOMContentLoaded', function() {
      var firstInput = document.querySelector('.item-image-input');
      if (firstInput && !firstInput._attached) { attachItemImageHandler(firstInput); firstInput._attached = true; }
    });
      function toggleAccountMenu() {
      const toggle = document.getElementById('accountToggle');
      const submenu = document.getElementById('accountSubmenu');
      if (toggle && submenu) {
        toggle.classList.toggle('open');
        submenu.classList.toggle('open');
      }
    }
  </script>
  <div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)closeModal()">
    <div class="modal-box">
      <div class="modal-header">
        <h2 id="modalTitle"></h2>
        <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body" id="modalBody"></div>
    </div>
  </div>

  <!-- Image Preview Modal -->
  <div class="modal-overlay" id="imgPreviewOverlay" onclick="if(event.target===this)closeImgPreview()" style="background:rgba(0,0,0,0.85);backdrop-filter:blur(4px);">
    <div style="position:relative;max-width:90vw;max-height:90vh;">
      <button onclick="closeImgPreview()" style="position:absolute;top:-2.5rem;right:0;background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;z-index:10;"><i class="fas fa-times"></i></button>
      <img id="imgPreviewFull" style="max-width:90vw;max-height:90vh;border-radius:12px;box-shadow:0 24px 80px rgba(0,0,0,0.5);display:block;">
    </div>
  </div>
</body>
</html>

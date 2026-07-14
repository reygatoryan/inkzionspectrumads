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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Notifications | Inkzion Spectrum Ads</title>
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
      --warning: #F59E0B;
      --danger: #EF4444;
      --text-primary: #0F172A;
      --text-secondary: #475569;
      --text-muted: #64748B;
      --border-color: #E2E8F0;
      --border-light: #F1F5F9;
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
    .sidebar-footer button.sidebar-footer-item { background: none; border: none; cursor: pointer; width: 100%; text-align: left; font: inherit; color: var(--text-muted); display: flex; align-items: center; gap: 0.65rem; padding: 0.5rem 0; font-size: 0.78rem; text-decoration: none; transition: var(--transition); }
    .sidebar-footer button.sidebar-footer-item:hover { color: var(--primary); }

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

    .page-wrap { max-width: 900px; margin: 0 auto; }
    .page-heading { margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
    .page-heading h1 { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 0.6rem; }
    .page-heading h1 i { color: var(--primary); }
    .page-heading p { color: var(--text-muted); margin-top: 0.15rem; }

    .form-card { background: white; border: 1px solid var(--border-color); border-radius: 20px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .form-card h2 { margin: 0; font-size: 1.15rem; color: #111827; }
    .form-card .subtitle { color: #64748b; font-size: 0.9rem; margin: 0.15rem 0 1.5rem; }

    .notif-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
    .notif-header h2 { margin: 0; font-size: 1.15rem; }
    .notif-header h2 i { color: var(--primary); margin-right: 0.5rem; }

    .btn-order-action { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.82rem; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s ease; }
    .btn-order-action.primary { background: rgba(43, 76, 82,0.08); color: #2B4C52; border: 1px solid rgba(43, 76, 82,0.2); }
    .btn-order-action.primary:hover { background: rgba(43, 76, 82,0.15); }

    .notif-item { display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid var(--border-light); margin-bottom: 0.5rem; transition: all 0.2s; }
    .notif-item:hover { background: #f8fafc; }
    .notif-item.notif-unread { background: var(--primary-bg); border-color: rgba(43, 76, 82,0.15); }
    .notif-icon { width: 36px; height: 36px; border-radius: 10px; background: rgba(43, 76, 82,0.08); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.9rem; }
    .notif-content { flex: 1; min-width: 0; }
    .notif-title { font-size: 0.85rem; font-weight: 600; color: #0f172a; }
    .notif-body { font-size: 0.78rem; color: #64748b; margin-top: 0.15rem; }
    .notif-time { font-size: 0.7rem; color: #94a3b8; margin-top: 0.3rem; }
    .notif-mark-read { background: none; border: none; color: #94a3b8; cursor: pointer; padding: 0.35rem; border-radius: 6px; transition: all 0.2s; flex-shrink: 0; }
    .notif-mark-read:hover { background: rgba(16,185,129,0.1); color: #10b981; }

    .empty-state { text-align: center; padding: 4rem 2rem; }
    .empty-state i { font-size: 3.5rem; color: rgba(43, 76, 82,0.15); margin-bottom: 1rem; }
    .empty-state h3 { font-size: 1.2rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem; }
    .empty-state p { color: var(--text-muted); font-size: 0.9rem; }

    @media (max-width: 768px) {
      .products-sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
      .products-sidebar.open { transform: translateX(0); }
      .hamburger-btn { display: flex; }
      .products-main { margin-left: 0; }
      .content-area { padding: 1rem; }
      .form-card { padding: 1.25rem; }
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
      <a href="notifications.php" class="sidebar-menu-item active"><i class="fas fa-bell"></i> Notifications</a>
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
            <h1>Notifications</h1>
            <p>Stay updated on your orders</p>
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
      <div class="page-wrap">
        <div class="page-heading">
          <h1><i class="fas fa-bell"></i> Notifications</h1>
        </div>
        <div class="form-card">
          <div class="notif-header">
            <h2><i class="fas fa-bell"></i> Notifications</h2>
            <button id="mark-all-read-btn" class="btn-order-action primary" style="display:none;" onclick="markAllRead()">
              <i class="fas fa-check-double"></i> Mark All Read
            </button>
          </div>
          <p class="subtitle">Stay updated on your orders.</p>
          <div id="notifications-list">
            <div style="text-align:center;padding:2rem;color:#64748b;">
              <i class="fas fa-spinner fa-pulse" style="font-size:1.5rem;margin-bottom:1rem;display:block;"></i>
              Loading...
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Help Modal -->
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

// ===== NOTIFICATIONS =====
let notifications = [];

async function loadNotifications() {
  const container = document.getElementById('notifications-list');
  if (!container) return;
  try {
    const res = await fetch('../api/notifications.php?type=all&limit=50', { credentials: 'include' });
    if (!res.ok) {
      container.innerHTML = '<p style="text-align:center;padding:2rem;color:#dc2626;">Server error (' + res.status + '). Please try again.</p>';
      return;
    }
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (parseError) {
      container.innerHTML = '<p style="text-align:center;padding:2rem;color:#dc2626;">Invalid response from server.</p><pre style="max-height:200px;overflow:auto;font-size:0.75rem;background:#f1f5f9;padding:0.75rem;border-radius:8px;margin-top:0.5rem;">' + escapeHtml(text.substring(0, 500)) + '</pre>';
      return;
    }
    if (!data.success) {
      container.innerHTML = '<p style="text-align:center;padding:2rem;color:#64748b;">' + escapeHtml(data.error || 'Could not load notifications.') + '</p>';
      return;
    }
    notifications = data.notifications || [];
    updateNotifBadge(data.unread_count || 0);
    renderNotifications(notifications);
  } catch (e) {
    container.innerHTML = '<p style="text-align:center;padding:2rem;color:#dc2626;">Failed to load notifications: ' + escapeHtml(e.message) + '</p>';
  }
}

function updateNotifBadge(count) {
  const badge = document.getElementById('notif-badge');
  if (!badge) return;
  if (count > 0) {
    badge.style.display = 'inline';
    badge.textContent = count > 99 ? '99+' : count;
  } else {
    badge.style.display = 'none';
  }
  const markAllBtn = document.getElementById('mark-all-read-btn');
  if (markAllBtn) markAllBtn.style.display = count > 0 ? 'inline-flex' : 'none';
}

function renderNotifications(notifs) {
  const container = document.getElementById('notifications-list');
  if (!container) return;
  if (notifs.length === 0) {
    container.innerHTML = '<div class="empty-state"><div style="font-size:2.5rem;color:rgba(43, 76, 82,0.15);margin-bottom:0.75rem;"><i class="fas fa-bell"></i></div><p style="color:#64748b;">No notifications yet.</p></div>';
    return;
  }
  container.innerHTML = notifs.map(n => {
    const isUnread = !n.is_read;
    return '<div class="notif-item ' + (isUnread ? 'notif-unread' : '') + '" data-id="' + n.id + '">' +
      '<div class="notif-icon">' + getNotifIcon(n.type) + '</div>' +
      '<div class="notif-content">' +
      '<div class="notif-title">' + escapeHtml(n.title) + '</div>' +
      '<div class="notif-body">' + escapeHtml(n.body) + '</div>' +
      '<div class="notif-time">' + getTimeAgo(n.created_at) + '</div>' +
      '</div>' +
      (isUnread ? '<button class="notif-mark-read" onclick="markRead(' + n.id + ')" title="Mark as read"><i class="fas fa-check"></i></button>' : '') +
      '</div>';
  }).join('');
}

function getNotifIcon(type) {
  const icons = {
    'order_placed': '<i class="fas fa-shopping-cart"></i>',
    'new_order': '<i class="fas fa-shopping-cart"></i>',
    'order_updated': '<i class="fas fa-sync-alt"></i>',
    'order_shipped': '<i class="fas fa-truck"></i>',
    'order_preparing': '<i class="fas fa-cog"></i>',
    'payment_updated': '<i class="fas fa-credit-card"></i>',
    'chat_message': '<i class="fas fa-comment"></i>',
    'custom_request': '<i class="fas fa-paint-brush"></i>',
    'customization_request': '<i class="fas fa-paint-brush"></i>',
    'proposal_sent': '<i class="fas fa-file-invoice"></i>',
    'proposal_filled': '<i class="fas fa-file-invoice"></i>',
    'order_approved': '<i class="fas fa-check-circle"></i>',
    'proposal_rejected': '<i class="fas fa-times-circle"></i>',
    'system': '<i class="fas fa-info-circle"></i>',
  };
  return icons[type] || '<i class="fas fa-bell"></i>';
}

function getTimeAgo(dateStr) {
  if (!dateStr) return '';
  const now = new Date();
  const date = new Date(dateStr);
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return diffMins + 'm ago';
  const diffHrs = Math.floor(diffMins / 60);
  if (diffHrs < 24) return diffHrs + 'h ago';
  const diffDays = Math.floor(diffHrs / 24);
  if (diffDays < 7) return diffDays + 'd ago';
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

async function markRead(id) {
  try {
    const res = await fetch('../api/notifications.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'mark_read', notification_id: id }),
      credentials: 'include'
    });
    const data = await res.json();
    if (data.success) loadNotifications();
  } catch(e) {}
}

async function markAllRead() {
  try {
    const res = await fetch('../api/notifications.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'mark_all_read' }),
      credentials: 'include'
    });
    const data = await res.json();
    if (data.success) loadNotifications();
  } catch(e) {}
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
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escapeHtml(s) { return esc(s); }

loadNotifications();
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

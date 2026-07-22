<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (empty($_SESSION['user_id']) || empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please log in as admin.'];
    header('Location: ../index.php');
    exit();
}
require_once __DIR__ . '/../../db-config.php';

$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Admin';
$userEmail = $_SESSION['user_email'] ?? '';

$nameParts = array_filter(preg_split('/\s+/', $userName));
$initials = strtoupper(substr(reset($nameParts), 0, 1) . (count($nameParts) > 1 ? substr(next($nameParts), 0, 1) : ''));
if ($initials === '') $initials = 'A';
$userProfilePhoto = $_SESSION['user_profile_photo'] ?? '';

if (empty($_SESSION['last_seen_written']) || $_SESSION['last_seen_written'] < time() - 60) {
    $conn->query("UPDATE users SET last_seen = NOW(), is_online = 1 WHERE id = $userId");
    $_SESSION['last_seen_written'] = time();
}

session_write_close();

$currentPage = basename($_SERVER['PHP_SELF']);
if (!isset($pageTitle)) $pageTitle = 'Dashboard';
if (!isset($pageSubtitle)) $pageSubtitle = '';
$isAddProductPage = ($currentPage === 'product-form.php' && !isset($_GET['id']));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?php echo htmlspecialchars($pageTitle); ?> | Inkzion Admin</title>
  <meta name="description" content="Inkzion Spectrum Ads Admin Panel" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css?v=2">
  <style>.sidebar-avatar img,.header-profile-avatar img{width:100%;height:100%;object-fit:cover;border-radius:inherit}</style>
</head>
<body>
<div class="dashboard-wrapper">
  <aside class="admin-sidebar" id="sidebar">
    <div class="sidebar-brand">
      <img src="../assets/logo.png" alt="Inkzion" class="sidebar-brand-img">
      <div class="sidebar-brand-text">
        <span class="sidebar-brand-name">INKZION</span>
        <span class="sidebar-brand-sub">Spectrum Ads</span>
      </div>
    </div>
    <div class="sidebar-profile">
      <div class="sidebar-avatar"><?php if ($userProfilePhoto): ?><img src="<?php echo htmlspecialchars(profilePhotoUrl($userProfilePhoto)); ?>" alt=""><?php else: ?><?php echo htmlspecialchars($initials); ?><?php endif; ?></div>
      <div class="sidebar-profile-info">
        <h4><?php echo htmlspecialchars($userName); ?></h4>
        <p>Admin</p>
      </div>
    </div>
    <nav class="sidebar-menu">
      <div class="sidebar-section-title">Main</div>
      <a href="dashboard.php" class="sidebar-menu-item <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-chart-pie"></i> Dashboard</a>
      <a href="orders.php" class="sidebar-menu-item <?php echo $currentPage === 'orders.php' ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i> Orders<span class="badge red" id="sidebar-orders-badge"></span></a>
      <a href="custom-requests.php" class="sidebar-menu-item <?php echo $currentPage === 'custom-requests.php' ? 'active' : ''; ?>"><i class="fas fa-paint-brush"></i> Customization Requests<span class="badge red" id="sidebar-requests-badge"></span></a>
      <a href="chat.php" class="sidebar-menu-item <?php echo $currentPage === 'chat.php' ? 'active' : ''; ?>"><i class="fas fa-comments"></i> Messages<span class="badge red" id="sidebar-msg-badge"></span></a>

      <div class="sidebar-section-title" style="padding-top:0.5rem;">Products</div>
      <a href="products.php" class="sidebar-menu-item <?php echo ($currentPage === 'products.php' || ($currentPage === 'product-form.php' && isset($_GET['id']))) ? 'active' : ''; ?>"><i class="fas fa-box"></i> My Products</a>
      <a href="product-form.php" class="sidebar-menu-item <?php echo $isAddProductPage ? 'active' : ''; ?>"><i class="fas fa-plus-circle"></i> Add Product</a>

      <div class="sidebar-section-title" style="padding-top:0.5rem;">Manage</div>
      <a href="content-manager.php" class="sidebar-menu-item <?php echo $currentPage === 'content-manager.php' ? 'active' : ''; ?>"><i class="fas fa-file-alt"></i> Content Manager</a>
      <a href="manage-customers.php" class="sidebar-menu-item <?php echo $currentPage === 'manage-customers.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Manage Customers</a>
      <a href="users.php" class="sidebar-menu-item <?php echo $currentPage === 'users.php' ? 'active' : ''; ?>"><i class="fas fa-user-shield"></i> All Users</a>
      <a href="settings.php" class="sidebar-menu-item <?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> Settings</a>

      <div class="sidebar-section-title" style="padding-top:0.5rem;">Insights</div>
      <a href="reports.php" class="sidebar-menu-item <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Reports</a>
      <a href="activity-logs.php" class="sidebar-menu-item <?php echo $currentPage === 'activity-logs.php' ? 'active' : ''; ?>"><i class="fas fa-history"></i> Activity Logs</a>

      <div class="sidebar-section-title" style="padding-top:0.5rem;">Account</div>
      <a href="account.php" class="sidebar-menu-item <?php echo $currentPage === 'account.php' ? 'active' : ''; ?>"><i class="fas fa-user-cog"></i> My Account</a>
    </nav>
  </aside>
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

  <main class="admin-main">
    <header class="top-header">
      <div class="top-header-inner">
        <div class="top-header-left">
          <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
          <div class="top-header-title">
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
            <?php if ($pageSubtitle): ?><p><?php echo htmlspecialchars($pageSubtitle); ?></p><?php endif; ?>
          </div>
        </div>
        <div class="top-header-right">
          <a href="../index.php" class="header-icon-btn" title="Home"><i class="fas fa-home"></i></a>
          <div class="header-notif-wrapper" id="notifWrapper">
            <button class="header-icon-btn" onclick="toggleNotifDropdown()" title="Notifications" aria-label="Notifications">
              <i class="fas fa-bell"></i>
              <span class="notif-bell-dot" id="notifBellDot" style="display:none;"></span>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
              <div class="notif-dropdown-header">
                <span>Notifications</span>
                <button class="notif-mark-all-btn" id="notifMarkAll" onclick="markAllNotifRead()">Mark all read</button>
              </div>
              <div class="notif-dropdown-list" id="notifList">
                <div class="notif-loading">Loading...</div>
              </div>
            </div>
          </div>
          <div class="header-profile-dropdown-wrapper">
            <button class="header-profile-btn" onclick="toggleProfileDropdown()" aria-label="Account menu">
              <div class="header-profile-avatar"><?php if ($userProfilePhoto): ?><img src="<?php echo htmlspecialchars(profilePhotoUrl($userProfilePhoto)); ?>" alt=""><?php else: ?><?php echo htmlspecialchars($initials); ?><?php endif; ?></div>
              <span class="header-profile-name"><?php echo htmlspecialchars($userName); ?></span>
              <i class="fas fa-chevron-down header-profile-arrow"></i>
            </button>
            <div class="header-profile-dropdown-menu" id="profileDropdown">
              <a href="account.php" class="header-profile-dropdown-item"><i class="fas fa-user-cog"></i> My Account</a>
              <a href="../logout.php" class="header-profile-dropdown-item danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
          </div>
        </div>
      </div>
    </header>
    <div class="content-area">

<?php
// Flash messages
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
if ($flash): ?>
<div class="toast toast-<?php echo $flash['type'] ?? 'info'; ?>" id="flash-toast" style="position:fixed;bottom:2rem;left:50%;transform:translateX(-50%);z-index:9999;box-shadow:0 8px 32px rgba(0,0,0,0.3);color:white;padding:0.85rem 1.8rem;border-radius:12px;font-size:0.9rem;font-weight:500;background:<?php echo $flash['type'] === 'error' ? 'rgba(239,68,68,0.9)' : ($flash['type'] === 'success' ? 'rgba(5,150,105,0.9)' : 'rgba(59,130,246,0.9)'); ?>;">
  <?php echo htmlspecialchars($flash['message']); ?>
</div>
<script>
  setTimeout(function(){ var e=document.getElementById('flash-toast'); if(e){ e.style.opacity='0'; setTimeout(function(){ e.remove(); },300); } }, 4000);
</script>
<?php endif; ?>

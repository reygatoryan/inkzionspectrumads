<?php
require_once __DIR__ . '/../includes/session-helper.php';
secureSessionStart();
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
$userProfilePhoto = $_SESSION['user_profile_photo'] ?? '';

$isSeller = !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

$activeSection = isset($_GET['section']) && in_array($_GET['section'], ['profile','addresses']) ? $_GET['section'] : 'profile';

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
$conn->close();

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
    
    /* ========= SIDEBAR (identical to products-redesigned) ========= */
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
    button.sidebar-footer-item { background: none; border: none; cursor: pointer; width: 100%; text-align: left; font: inherit; color: var(--text-muted); display: flex; align-items: center; gap: 0.65rem; padding: 0.5rem 0; font-size: 0.78rem; text-decoration: none; transition: var(--transition); }
    button.sidebar-footer-item:hover { color: var(--primary); }

    .modal-overlay { display: none; position: fixed; inset: 0; z-index: 99999; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; padding: 1rem; backdrop-filter: blur(4px); }
    .modal-overlay.open { display: flex; }
    .modal-box { background: white; border-radius: 16px; max-width: 600px; width: 100%; max-height: 85vh; overflow-y: auto; box-shadow: 0 24px 80px rgba(0,0,0,0.2); animation: modalIn 0.25s ease; }
    @keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; }
    .modal-header h2 { font-size: 1.1rem; font-weight: 700; color: #1a1a2e; display: flex; align-items: center; gap: 0.5rem; }
    .modal-header h2 i { color: #2B4C52; }
    .modal-close { width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; transition: all 0.15s ease; }
    .modal-close:hover { border-color: #ef4444; color: #ef4444; }
    .modal-body { padding: 1.5rem; }
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
    .modal-faq-answer { padding: 0 1.25rem 1rem; font-size: 0.85rem; color: #475569; line-height: 1.7; border-top: 1px solid #f1f5f9; padding-top: 0.75rem; }

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
    .header-profile-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: inherit; }
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
    
    .content-area { max-width: 900px; margin: 0 auto; padding: 1.5rem 2rem 2rem; }
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
    .account-content { min-width: 0; }
    .content-section { display: none; }
    .content-section.active { display: block; }

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
    .form-group input:focus, .form-group textarea:focus { border-color: #2B4C52; box-shadow: 0 0 0 3px rgba(43, 76, 82,0.08); }
    .form-group textarea { min-height: 80px; resize: vertical; }
    .form-hint { font-size: 0.78rem; color: #94a3b8; margin-top: 0.15rem; }
    .form-section-divider { height: 1px; background: var(--border-light); margin: 1.5rem 0; }
    .btn-save {
      display: inline-flex; align-items: center; gap: 0.5rem;
      padding: 0.85rem 2rem; border-radius: 12px; border: none;
      background: linear-gradient(135deg, #2B4C52, #4A7C84); color: white;
      cursor: pointer; font-weight: 700; font-size: 0.95rem;
      transition: all 0.2s ease; box-shadow: 0 6px 16px rgba(43, 76, 82,0.25);
      font-family: var(--font);
    }
    .btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(43, 76, 82,0.35); }
    .flash-msg { padding: 0.85rem 1.2rem; border-radius: 12px; margin-bottom: 1.25rem; font-size: 0.9rem; font-weight: 600; }
    .flash-success { background: rgba(5,150,105,0.1); color: #047857; border: 1px solid rgba(5,150,105,0.25); }
    .flash-error { background: rgba(239,68,68,0.1); color: #dc2626; border: 1px solid rgba(239,68,68,0.25); }

    /* RESPONSIVE */
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
      .form-grid .full-width { grid-column: 1; }
      .form-card { padding: 1.5rem; }
    }
    @media (max-width: 600px) {
    }
    @media (max-width: 480px) {
      .top-header-center { display: none; }
      .account-header-left h1 { font-size: 1.1rem; }
      .account-header-left p { display: none; }
      .form-card { padding: 1.25rem; }
      .btn-save { width: 100%; justify-content: center; }
      .modal-header { padding: 1rem 1.25rem; }
      .modal-body { padding: 1.25rem; }
    }
    @media (max-width: 400px) {
      .content-area { padding: 0.75rem; }
      .top-header-title h1 { font-size: 1rem; }
      .header-icon-btn { width: 38px; height: 38px; }
      .hamburger-btn { width: 38px; height: 38px; }
      .header-profile-name, .header-profile-arrow { display: none; }
      .notif-dropdown { position: fixed; top: 64px; left: 0.75rem; right: 0.75rem; width: auto; }
      .form-group input, .form-group select { padding: 0.6rem 0.75rem; }
    }
    @media (max-width: 360px) {
      .top-header { padding: 0 0.5rem; }
      .top-header-left { gap: 0.35rem; }
      .top-header-title h1 { font-size: 0.8rem; }
      .hamburger-btn { width: 34px; height: 34px; }
      .header-icon-btn { width: 34px; height: 34px; }
      .content-area { padding: 0.5rem; }
      .form-card { padding: 0.85rem; }
      .notif-dropdown { position: fixed; top: 60px; left: 0.5rem; right: 0.5rem; width: auto; }
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
    .notif-dropdown { position: absolute; top: calc(100% + 8px); right: 0; background: white; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 12px 40px rgba(0,0,0,0.15); width: 360px; max-height: 480px; display: flex; flex-direction: column; z-index: 1000; opacity: 0; visibility: hidden; transform: translateY(10px); transition: opacity 0.2s, transform 0.2s, visibility 0.2s; }
    .notif-dropdown.active { opacity: 1; visibility: visible; transform: translateY(0); }
    .notif-dropdown-header { display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; font-weight: 700; color: #0f172a; flex-shrink: 0; }
    .notif-mark-all-btn { background: none; border: none; color: #2B4C52; font-size: 0.72rem; font-weight: 600; cursor: pointer; padding: 0.2rem 0.5rem; border-radius: 6px; }
    .notif-mark-all-btn:hover { background: rgba(43,76,82,0.08); }
    .notif-dropdown-list { overflow-y: auto; flex: 1; max-height: 400px; }
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
        <div class="sidebar-avatar"><?php if ($userProfilePhoto): ?><img src="<?php echo htmlspecialchars(profilePhotoUrl($userProfilePhoto)); ?>" alt=""><?php else: ?><?php echo htmlspecialchars($userInitials); ?><?php endif; ?></div>
        <div class="sidebar-profile-info">
          <h4><?php echo htmlspecialchars($userName); ?></h4>
          <p><?php echo $isSeller ? 'admin' : 'Customer'; ?></p>
        </div>
      </div>
      <nav class="sidebar-menu">
        <div class="sidebar-section-title">Shop</div>
        <a href="store-product.php" class="sidebar-menu-item"><i class="fas fa-box"></i> All Products</a>
        
        <a href="chat.php" class="sidebar-menu-item"><i class="fas fa-comments"></i> Messages<span class="sidebar-badge" id="sidebar-msg-badge"></span></a>
        <div class="sidebar-section-title" style="padding-top:0.5rem;">Orders</div>
        <a href="my-orders.php" class="sidebar-menu-item"><i class="fas fa-box"></i> My Orders<span class="sidebar-badge" id="sidebar-orders-badge"></span></a>
        <a href="my-requests.php" class="sidebar-menu-item"><i class="fas fa-clipboard-list"></i> My Requests<span class="sidebar-badge" id="sidebar-requests-badge"></span></a>
        <div class="sidebar-section-title" style="padding-top:0.5rem;">Account</div>
        <div class="sidebar-menu-item sidebar-menu-toggle open" id="accountToggle" onclick="toggleAccountMenu()">
          <i class="fas fa-user-circle"></i> My Profile
          <i class="fas fa-chevron-down toggle-arrow"></i>
        </div>
        <div class="sidebar-submenu open" id="accountSubmenu">
          <a href="?section=profile" class="sidebar-submenu-item <?= $activeSection === 'profile' ? 'active' : '' ?>" data-section="profile">
            <i class="fas fa-user-edit"></i> Edit Profile
          </a>
          <a href="?section=addresses" class="sidebar-submenu-item <?= $activeSection === 'addresses' ? 'active' : '' ?>" data-section="addresses">
            <i class="fas fa-map-marker-alt"></i> My Addresses
          </a>
        </div>
      </nav>
      <div class="sidebar-footer">
        <button class="sidebar-footer-item" onclick="openModal('contact')"><i class="fas fa-envelope"></i> Contact</button>
        <button class="sidebar-footer-item" onclick="openModal('help')"><i class="fas fa-question-circle"></i> Help Center</button>
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
                <div class="header-profile-avatar"><?php if ($userProfilePhoto): ?><img src="<?php echo htmlspecialchars(profilePhotoUrl($userProfilePhoto)); ?>" alt=""><?php else: ?><?php echo htmlspecialchars($userInitials); ?><?php endif; ?></div>
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

            <!-- Section: Edit Profile -->
            <div id="section-profile" class="content-section <?= $activeSection === 'profile' ? 'active' : '' ?>">
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
            <div id="section-addresses" class="content-section <?= $activeSection === 'addresses' ? 'active' : '' ?>">
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
                <p style="margin-top:1rem;font-size:0.85rem;color:#64748b;">To update your address, use the <a href="?section=profile" style="color:var(--primary);">Edit Profile</a> section.</p>
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
    let currentSection = '<?= $activeSection ?>';

    function switchSection(section) {
      currentSection = section;
      document.querySelectorAll('#accountSubmenu .sidebar-submenu-item').forEach(el => {
        el.classList.toggle('active', el.dataset.section === section);
      });
      document.querySelectorAll('.content-section').forEach(el => {
        el.classList.toggle('active', el.id === 'section-' + section);
      });
      const submenu = document.getElementById('accountSubmenu');
      if (!submenu.classList.contains('open')) {
        document.getElementById('accountToggle').classList.add('open');
        submenu.classList.add('open');
      }
      // Update URL without reload
      var url = new URL(window.location);
      url.searchParams.set('section', section);
      window.history.replaceState({}, '', url);
    }

    // Intercept sidebar submenu clicks for smooth internal switching
    document.getElementById('accountSubmenu').addEventListener('click', function(e) {
      var link = e.target.closest('.sidebar-submenu-item');
      if (link && link.dataset.section) {
        e.preventDefault();
        switchSection(link.dataset.section);
      }
    });

    // On load, switch to section from URL if present
    (function() {
      var params = new URLSearchParams(window.location.search);
      var section = params.get('section');
      if (section === 'profile' || section === 'addresses') {
        switchSection(section);
      }
    })();

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



    // Search
    const searchInput = document.getElementById('product-search');
    if (searchInput) {
      searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && this.value.trim()) {
          window.location.href = 'store-product.php?search=' + encodeURIComponent(this.value.trim());
        }
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
      if (e.key === 'Escape') closeModal();
    });

    function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
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
      if (rt==='chat'&&ri) return 'chat.php?conversation='+ri;
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

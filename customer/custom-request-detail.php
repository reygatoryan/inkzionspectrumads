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

$requestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$requestId) { header('Location: my-requests.php'); exit(); }

// Fetch request data server-side
$req = null;
$files = [];
$proposals = [];
$userId = (int)$_SESSION['user_id'];
require_once __DIR__ . '/../db-config.php';

$stmt = $conn->prepare("
    SELECT cpr.*, u.name as user_name, u.email as user_email,
           (SELECT COUNT(*) FROM custom_request_files WHERE request_id = cpr.id) as file_count
    FROM custom_printing_requests cpr
    JOIN users u ON cpr.user_id = u.id
    WHERE cpr.id = ? AND cpr.user_id = ?
");
$stmt->bind_param('ii', $requestId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$req = $result->fetch_assoc();
$stmt->close();

$markNotif = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND related_type = 'custom_request' AND related_id = ? AND is_read = 0");
$markNotif->bind_param('ii', $userId, $requestId);
$markNotif->execute();
$markNotif->close();

$markViewed = $conn->prepare("UPDATE custom_printing_requests SET is_viewed = 1 WHERE id = ? AND user_id = ?");
$markViewed->bind_param('ii', $requestId, $userId);
$markViewed->execute();
$markViewed->close();

if (!$req) { echo '<html><body style="font-family:sans-serif;padding:3rem;text-align:center;color:#64748b;"><h2>Request not found</h2><p>This request does not exist or you do not have access.</p><a href="my-requests.php" style="color:#2B4C52;">Back to My Requests</a></body></html>'; $conn->close(); exit(); }

// Fetch files
$stmt = $conn->prepare("
    SELECT * FROM custom_request_files
    WHERE request_id = ?
    ORDER BY sort_order ASC, created_at ASC
");
$stmt->bind_param('i', $requestId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $files[] = $row; }
$stmt->close();

// Fetch proposals
$stmt = $conn->prepare("
    SELECT * FROM order_proposals
    WHERE request_id = ? AND user_id = ?
    ORDER BY created_at ASC
");
$stmt->bind_param('ii', $requestId, $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['items'] = json_decode($row['items'], true) ?: [];
    $proposals[] = $row;
}
$stmt->close();
$conn->close();
$proposal = count($proposals) ? $proposals[count($proposals) - 1] : null;

$statusLabels = [
    'pending' => ['Pending', '#b8860b', 'rgba(255,193,7,0.12)'],
    'in_review' => ['In Review', '#1d4ed8', 'rgba(59,130,246,0.12)'],
    'approved' => ['Approved', '#047857', 'rgba(16,185,129,0.12)'],
    'ready_for_purchase' => ['Ready to Buy', '#3D5C42', 'rgba(43, 76, 82,0.12)'],
    'rejected' => ['Rejected', '#dc2626', 'rgba(239,68,68,0.12)'],
    'completed' => ['Completed', '#475569', 'rgba(100,116,139,0.12)']
];
$st = $statusLabels[$req['status']] ?? [$req['status'], '#64748b', 'rgba(100,116,139,0.12)'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Request #<?php echo $requestId; ?> | Inkzion Spectrum Ads</title>
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

    .detail-page { max-width: 900px; margin: 0 auto; }

    .back-link { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; color: var(--text-muted); text-decoration: none; margin-bottom: 1.5rem; transition: var(--transition); }
    .back-link:hover { color: var(--primary); }

    .detail-header { background: white; border: 1px solid var(--border-color); border-radius: 20px; padding: 1.75rem 2rem; margin-bottom: 1.25rem; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
    .detail-header-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; }
    .detail-header-id { font-size: 1.4rem; font-weight: 800; color: var(--text-primary); }
    .detail-header-date { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem; }
    .detail-header-status { padding: 0.4rem 1rem; border-radius: 999px; font-size: 0.82rem; font-weight: 700; white-space: nowrap; }

    .detail-section { background: white; border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem 2rem; margin-bottom: 1.25rem; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
    .detail-section h2 { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
    .detail-section h2 i { color: var(--primary); }

    .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .detail-field { }
    .detail-field label { display: block; font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.25rem; }
    .detail-field span { font-size: 0.92rem; color: var(--text-primary); font-weight: 500; }
    .detail-field.full { grid-column: 1 / -1; }

    .items-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
    .items-table th { text-align: left; padding: 0.5rem 0.75rem; font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; background: #f8fafc; border-bottom: 1px solid var(--border-color); }
    .items-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--border-light); color: var(--text-secondary); }
    .items-table tr:last-child td { border-bottom: none; }
    .item-ref-img { width: 40px; height: 40px; border-radius: 6px; object-fit: cover; cursor: pointer; border: 1px solid var(--border-color); transition: var(--transition); }
    .item-ref-img:hover { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }

    .files-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 0.5rem; }
    .file-thumb { aspect-ratio: 1; border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); cursor: pointer; }
    .file-thumb img { width: 100%; height: 100%; object-fit: cover; }

    .prop-card { background: #f8fafc; border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem; }
    .prop-status { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.82rem; font-weight: 600; }
    .prop-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 1rem; }
    .prop-detail-item { }
    .prop-detail-item label { display: block; font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.1rem; }
    .prop-detail-item span { font-size: 0.88rem; color: var(--text-primary); }
    .admin-notes-box { background: white; border: 1px solid var(--border-color); border-radius: 10px; padding: 0.85rem 1rem; font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6; }

    .action-buttons { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem; }
    .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.35rem; border-radius: 10px; font-size: 0.85rem; font-weight: 700; text-decoration: none; cursor: pointer; transition: var(--transition); border: none; font-family: var(--font); }
    .btn:hover { transform: translateY(-2px); }
    .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; box-shadow: 0 4px 12px rgba(43, 76, 82,0.25); }
    .btn-outline { background: white; color: var(--text-secondary); border: 1.5px solid var(--border-color); }
    .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
    .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white; box-shadow: 0 4px 12px rgba(16,185,129,0.25); }
    .btn-chat { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; }

    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; padding: 1.5rem; }
    .modal-overlay.open { display: flex; animation: fadeIn 0.25s ease; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    .status-timeline { display: flex; align-items: center; gap: 1rem; margin: 1rem 0 0; padding-top: 1rem; border-top: 1px solid var(--border-color); flex-wrap: wrap; }
    .status-step { display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: var(--text-muted); }
    .status-step i { font-size: 0.7rem; width: 14px; text-align: center; }
    .status-step.past i { color: var(--success); }
    .status-step.past { color: var(--text-primary); font-weight: 600; }
    .status-step.current i { color: var(--primary); }
    .status-step.current { color: var(--primary); font-weight: 700; }
    .status-arrow { color: var(--border-color); font-size: 0.65rem; }

    .rfp-card { display: flex; gap: 1.5rem; align-items: flex-start; flex-wrap: wrap; }
    .rfp-image { width: 200px; height: 200px; border-radius: 16px; object-fit: cover; border: 1px solid var(--border-color); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .rfp-info { flex: 1; min-width: 200px; }
    .rfp-info h3 { font-size: 1.2rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem; }
    .rfp-price { font-size: 1.5rem; font-weight: 800; color: var(--primary); margin-bottom: 0.25rem; }
    .rfp-qty { font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1rem; }
    .rfp-divider { height: 1px; background: var(--border-color); margin: 0.75rem 0; }
    .rfp-subtotal { font-size: 0.9rem; color: var(--text-secondary); }
    .rfp-shipping { font-size: 0.9rem; color: var(--text-secondary); }
    .rfp-total { font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin-top: 0.25rem; }

    @media (max-width: 768px) {
      .products-sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
      .products-sidebar.open { transform: translateX(0); }
      .hamburger-btn { display: flex; }
      .products-main { margin-left: 0; }
      .detail-grid { grid-template-columns: 1fr; }
      .prop-detail-grid { grid-template-columns: 1fr; }
      .detail-header { padding: 1.25rem; }
      .detail-section { padding: 1.25rem; }
      .content-area { padding: 1rem; }
      .status-timeline { gap: 0.5rem; }
    }
    @media (max-width: 480px) {
      .top-header-title p { display: none; }
      .top-header-title h1 { font-size: 1.1rem; }
      .header-profile-name, .header-profile-arrow { display: none; }
      .detail-header { padding: 1rem; }
      .detail-section { padding: 1rem; }
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
      
      <a href="chat.php" class="sidebar-menu-item"><i class="fas fa-comments"></i> Messages<span class="sidebar-badge" id="sidebar-msg-badge"></span></a>
      <div class="sidebar-section-title" style="padding-top:0.5rem;">Orders</div>
      <a href="my-orders.php" class="sidebar-menu-item"><i class="fas fa-box"></i> My Orders<span class="sidebar-badge" id="sidebar-orders-badge"></span></a>
      <a href="my-requests.php" class="sidebar-menu-item active"><i class="fas fa-clipboard-list"></i> My Requests<span class="sidebar-badge" id="sidebar-requests-badge"></span></a>
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
            <h1>Request Details</h1>
            <p>Custom printing request #<?php echo $requestId; ?></p>
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
      <div class="detail-page">
        <a href="my-requests.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to My Requests</a>

        <!-- Header -->
        <div class="detail-header">
          <div class="detail-header-top">
            <div>
              <div class="detail-header-id">Request #<?php echo $requestId; ?>
                <span style="margin-left:0.75rem;font-size:0.85rem;font-weight:500;color:var(--text-muted);"><?php echo htmlspecialchars($req['service_type'] ?? ''); ?></span>
              </div>
              <div class="detail-header-date">Submitted on <?php echo date('F j, Y', strtotime($req['created_at'])); ?></div>
            </div>
            <span class="detail-header-status" style="background:<?php echo $st[2]; ?>;color:<?php echo $st[1]; ?>;"><?php echo $st[0]; ?></span>
          </div>
          <!-- Status Timeline -->
          <?php
          $flow = ['pending' => 'Pending', 'in_review' => 'In Review', 'approved' => 'Approved'];
          $currentIdx = array_search($req['status'], array_keys($flow));
          if ($currentIdx === false) $currentIdx = -1;
          ?>
          <div class="status-timeline">
            <?php $i = 0; foreach ($flow as $key => $label): ?>
              <?php if ($i > 0): ?><span class="status-arrow"><i class="fas fa-chevron-right"></i></span><?php endif; ?>
              <span class="status-step <?php echo $i < $currentIdx ? 'past' : ($i === $currentIdx ? 'current' : ''); ?>">
                <?php if ($i < $currentIdx): ?><i class="fas fa-check-circle"></i>
                <?php elseif ($i === $currentIdx): ?><i class="fas fa-circle"></i>
                <?php else: ?><i class="far fa-circle"></i><?php endif; ?>
                <?php echo $label; ?>
              </span>
            <?php $i++; endforeach; ?>
          </div>
        </div>

        <?php
          $rfpPrice = (float)($req['ready_for_purchase_price'] ?? 0);
          $rfpQty = (int)($req['ready_for_purchase_qty'] ?? 1);
          $rfpShipping = (float)($req['ready_for_purchase_shipping'] ?? 0);
          $rfpSubtotal = $rfpPrice * $rfpQty;
          $rfpTotal = $rfpSubtotal + $rfpShipping;
        ?>

        <!-- Customization Details -->
        <div class="detail-section">
          <h2><i class="fas fa-palette"></i> Customization Details</h2>
          <div class="detail-grid">
            <div class="detail-field">
              <label>Service Type</label>
              <span><?php echo htmlspecialchars($req['service_type'] ?? '--'); ?></span>
            </div>
            <div class="detail-field">
              <label>Material</label>
              <span><?php echo htmlspecialchars($req['material'] ?? '--'); ?></span>
            </div>
            <div class="detail-field">
              <label>Unit Price</label>
              <span><?php echo $rfpPrice > 0 ? '₱'.number_format($rfpPrice, 2) : '--'; ?></span>
            </div>
            <div class="detail-field">
              <label>Preferred Deadline</label>
              <span><?php echo $req['preferred_deadline'] ? date('F j, Y', strtotime($req['preferred_deadline'])) : 'None'; ?></span>
            </div>
            <div class="detail-field full">
              <label>Note</label>
              <span><?php echo htmlspecialchars($req['special_requests'] ?? 'None'); ?></span>
            </div>
          </div>
        </div>

        <!-- Items -->
        <?php
        $parsedItems = [];
        if (!empty($req['items'])) {
            try { $parsedItems = is_string($req['items']) ? json_decode($req['items'], true) : $req['items']; } catch (\Exception $e) {}
        }
        ?>
        <?php if (is_array($parsedItems) && count($parsedItems)): ?>
        <div class="detail-section">
          <h2><i class="fas fa-tshirt"></i> Order Items</h2>
          <table class="items-table">
            <thead><tr><th>Size</th><th>Quantity</th></tr></thead>
            <tbody>
              <?php foreach ($parsedItems as $it): ?>
              <tr>
                <td><?php echo htmlspecialchars($it['size'] ?? '--'); ?></td>
                <td><?php echo (int)($it['qty'] ?? 0); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <!-- Files -->
        <?php if (count($files)): ?>
        <div class="detail-section">
          <h2><i class="fas fa-files"></i> Uploaded Files (<?php echo count($files); ?>)</h2>
          <div class="files-grid">
            <?php foreach ($files as $f): ?>
            <div class="file-thumb" onclick="window.open('<?php echo htmlspecialchars($f['file_url']); ?>')">
              <img src="<?php echo htmlspecialchars($f['file_url']); ?>" alt="<?php echo htmlspecialchars($f['file_name'] ?? 'File'); ?>">
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Admin Notes -->
        <?php if (!empty($req['admin_notes'])): ?>
        <div class="detail-section">
          <h2><i class="fas fa-sticky-note"></i> Admin Notes</h2>
          <div class="admin-notes-box"><?php echo nl2br(htmlspecialchars($req['admin_notes'])); ?></div>
        </div>
        <?php endif; ?>

        <!-- Order Proposal -->
        <?php if ($proposal): ?>
        <div class="detail-section">
          <h2><i class="fas fa-file-invoice"></i> Order Form</h2>
          <?php
          $propStatusLabels = ['sent' => ['Sent', '#1d4ed8', 'rgba(59,130,246,0.12)'], 'filled' => ['Filled', '#b8860b', 'rgba(255,193,7,0.12)'], 'converted' => ['Approved', '#047857', 'rgba(16,185,129,0.12)'], 'rejected' => ['Rejected', '#dc2626', 'rgba(239,68,68,0.12)']];
          $ps = $propStatusLabels[$proposal['status']] ?? [$proposal['status'], '#64748b', 'rgba(100,116,139,0.12)'];
          ?>
          <div class="prop-card">
            <?php if (!empty($req['ready_for_purchase_image'])): ?>
            <div style="display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid var(--border-color);">
              <img src="../<?php echo htmlspecialchars($req['ready_for_purchase_image']); ?>" alt="<?php echo htmlspecialchars($req['ready_for_purchase_name'] ?? 'Product'); ?>" style="width:140px;height:140px;border-radius:12px;object-fit:cover;border:1px solid var(--border-color);">
              <div style="flex:1;min-width:160px;">
                <h3 style="margin:0 0 0.35rem;font-size:1.1rem;"><?php echo htmlspecialchars($req['ready_for_purchase_name'] ?? 'Product'); ?></h3>
                <div style="font-size:1.3rem;font-weight:800;color:var(--primary);">₱<?php echo number_format($rfpPrice, 2); ?></div>
                <div style="font-size:0.85rem;color:var(--text-muted);">Quantity: <?php echo $rfpQty; ?></div>
                <div style="height:1px;background:var(--border-color);margin:0.5rem 0;"></div>
                <div style="font-size:0.88rem;color:var(--text-secondary);">Subtotal (×<?php echo $rfpQty; ?>): ₱<?php echo number_format($rfpSubtotal, 2); ?></div>
                <div style="font-size:0.88rem;color:var(--text-secondary);">Shipping: ₱<?php echo number_format($rfpShipping, 2); ?></div>
                <div style="font-size:1.1rem;font-weight:800;color:var(--text-primary);margin-top:0.25rem;">Total: ₱<?php echo number_format($rfpTotal, 2); ?></div>
              </div>
            </div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
              <span style="font-weight:700;font-size:0.95rem;color:var(--text-primary);">Quote Summary</span>
              <span class="prop-status" style="background:<?php echo $ps[2]; ?>;color:<?php echo $ps[1]; ?>;padding:0.25rem 0.75rem;border-radius:999px;"><?php echo $ps[0]; ?></span>
            </div>

            <?php if (!empty($proposal['admin_notes'])): ?>
            <div style="margin-top:0.75rem;padding:0.75rem;background:white;border-radius:8px;font-size:0.85rem;color:var(--text-secondary);border:1px solid var(--border-color);">
              <strong style="color:var(--text-primary);">Admin Notes:</strong> <?php echo nl2br(htmlspecialchars($proposal['admin_notes'])); ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($proposal['rejection_reason'])): ?>
            <div style="margin-top:0.75rem;padding:0.75rem;background:var(--danger-bg);border-radius:8px;font-size:0.85rem;color:#dc2626;border:1px solid rgba(239,68,68,0.2);">
              <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($proposal['rejection_reason']); ?>
            </div>
            <?php endif; ?>

            <?php if (in_array($proposal['status'], ['filled', 'converted'])): ?>
            <div class="prop-detail-grid">
              <div class="prop-detail-item"><label>Full Name</label><span><?php echo htmlspecialchars($proposal['full_name'] ?? '--'); ?></span></div>
              <div class="prop-detail-item"><label>Email</label><span><?php echo htmlspecialchars($proposal['email'] ?? '--'); ?></span></div>
              <div class="prop-detail-item"><label>Phone</label><span><?php echo htmlspecialchars($proposal['phone'] ?? '--'); ?></span></div>
              <div class="prop-detail-item"><label>Payment Method</label><span><?php echo htmlspecialchars($proposal['payment_method'] ?? '--'); ?></span></div>
              <div class="prop-detail-item"><label>Delivery Address</label><span><?php echo htmlspecialchars($proposal['delivery_address'] ?? '--'); ?></span></div>
              <div class="prop-detail-item"><label>City / Province / ZIP</label><span><?php echo htmlspecialchars(($proposal['city'] ?? '') . ', ' . ($proposal['province'] ?? '') . ' ' . ($proposal['zip'] ?? '')); ?></span></div>
              <div class="prop-detail-item"><label>Landmark</label><span><?php echo htmlspecialchars($proposal['landmark'] ?? '--'); ?></span></div>
              <div class="prop-detail-item"><label>Additional Notes</label><span><?php echo htmlspecialchars($proposal['additional_notes'] ?? '--'); ?></span></div>
            </div>
            <?php endif; ?>

            <?php if ($proposal['status'] === 'sent' || $proposal['status'] === 'rejected'): ?>
            <div style="margin-top:1rem;">
              <a href="order-form.php?id=<?php echo $proposal['id']; ?>" class="btn btn-success"><i class="fas fa-edit"></i> Fill Order Form</a>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="action-buttons">
          <?php if (!empty($req['chat_conversation_id'])): ?>
            <a href="chat.php?conversation=<?php echo (int)$req['chat_conversation_id']; ?>" class="btn btn-primary"><i class="fas fa-comments"></i> Chat with Admin</a>
          <?php endif; ?>
          <a href="my-requests.php" class="btn btn-outline"><i class="fas fa-list"></i> All Requests</a>
        </div>

      </div>
    </div>
  </main>
</div>

<!-- Image Preview Modal -->
<div class="modal-overlay" id="imgPreviewOverlay" onclick="if(event.target===this)closeImgPreview()">
  <div style="position:relative;max-width:90vw;max-height:90vh;">
    <button onclick="closeImgPreview()" style="position:absolute;top:-2.5rem;right:0;background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;z-index:10;"><i class="fas fa-times"></i></button>
    <img id="imgPreviewFull" style="max-width:90vw;max-height:90vh;border-radius:12px;box-shadow:0 24px 80px rgba(0,0,0,0.5);display:block;">
  </div>
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

  function openImgPreview(src) {
    document.getElementById('imgPreviewFull').src = src;
    document.getElementById('imgPreviewOverlay').classList.add('open');
  }
  function closeImgPreview() {
    document.getElementById('imgPreviewOverlay').classList.remove('open');
  }
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeImgPreview(); closeModal(); }
  });

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
              html += '<details class="modal-faq" style="border:1px solid #e2e8f0;border-radius:12px;margin-bottom:0.6rem;overflow:hidden;"' + (i === 0 ? ' open' : '') + '><summary style="padding:1rem 1.25rem;font-size:0.9rem;font-weight:600;color:#0f172a;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:0.5rem;">' + esc(f.question || '') + ' <i class="fas fa-chevron-down" style="font-size:0.75rem;color:#94a3b8;"></i></summary><div class="modal-faq-answer" style="padding:1rem 1.25rem;font-size:0.88rem;color:#475569;line-height:1.7;">' + esc(f.answer || '') + '</div></details>';
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
    function toggleAccountMenu() {
      const toggle = document.getElementById('accountToggle');
      const submenu = document.getElementById('accountSubmenu');
      if (toggle && submenu) {
        toggle.classList.toggle('open');
        submenu.classList.toggle('open');
      }
    }
    function updateSidebarBadges() {
      fetch('../api/notif-counts.php').then(r=>r.json()).then(d=>{
        const sb = (id, c) => { const b = document.getElementById(id); if(b){ b.textContent = c||''; b.classList.toggle('show', c>0); } };
        sb('sidebar-msg-badge', d.chat);
        sb('sidebar-orders-badge', d.order);
        sb('sidebar-requests-badge', d.custom_request);
      }).catch(()=>{});
    }
    updateSidebarBadges();
    setInterval(updateSidebarBadges, 10000);
  </script>
</body>
</html>

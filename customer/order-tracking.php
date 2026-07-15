<?php
session_start();
require_once dirname(__DIR__) . '/db-config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$orderId = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['order_id']) ? intval($_GET['order_id']) : 0);

if (!$orderId) {
    header('Location: ../index.php');
    exit;
}

// Get order details
$orderStmt = $conn->prepare("
    SELECT o.*, 
           u.name, u.email,
           s.name as seller_name, s.email as seller_email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN users s ON o.admin_id = s.id
    WHERE o.id = ? AND o.user_id = ?
");
$orderStmt->bind_param('ii', $orderId, $userId);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    header('Location: ../index.php');
    exit;
}

$markNotif = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND related_type = 'order' AND related_id = ? AND is_read = 0");
$markNotif->bind_param('ii', $userId, $orderId);
$markNotif->execute();
$markNotif->close();

// Get order timeline
$timelineStmt = $conn->prepare("
    SELECT ot.*, u.name, u.role
    FROM order_timeline ot
    LEFT JOIN users u ON ot.changed_by = u.id
    WHERE ot.order_id = ?
    ORDER BY ot.created_at ASC
");
$timelineStmt->bind_param('i', $orderId);
$timelineStmt->execute();
$timeline = $timelineStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$timelineStmt->close();

// Get order items
$itemsStmt = $conn->prepare("
    SELECT oi.product_name, oi.quantity, oi.unit_price,
           COALESCE(p.image_url, 'assets/products-demo.jpg') as image_url
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$itemsStmt->bind_param('i', $orderId);
$itemsStmt->execute();
$orderItems = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();

// Status flow for progress bar
$statusFlow = [
    'pending' => 1,
    'confirmed' => 2,
    'shipped' => 3,
    'delivered' => 4,
    'completed' => 5,
];

$currentStatus = $order['status'];
$currentStep = $statusFlow[$currentStatus] ?? 0;
$totalSteps = 5;
$progressPercent = $currentStep > 0 ? round(($currentStep / $totalSteps) * 100) : 0;

// Status labels
$statusLabels = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'returned' => 'Returned',
];

// Parse JSON fields
$designFiles = json_decode($order['design_files'] ?? '[]', true) ?: [];
$proofImages = json_decode($order['proof_images'] ?? '[]', true) ?: [];
$packagePhotos = json_decode($order['package_photos'] ?? '[]', true) ?: [];

// Get user role
$roleStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->bind_param('i', $userId);
$roleStmt->execute();
$userRole = $roleStmt->get_result()->fetch_assoc();
$roleStmt->close();
$userRole = $userRole['role'] ?? 'customer';

$userName = trim($_SESSION['user_name'] ?? '');
$userInitials = '';
if ($userName !== '') {
    $parts = array_filter(preg_split('/\s+/', $userName));
    $userInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
if ($userInitials === '') { $userInitials = 'U'; }
$isSeller = !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking - Inkzion Spectrum Ads</title>
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
        .sidebar-brand-img { width: 38px; height: 38px; border-radius: 10px; object-fit: contain; background: white; padding: 4px; box-shadow: 0 2px 6px rgba(43, 76, 82, 0.15); }
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
        .sidebar-avatar { width: 30px; height: 30px; border-radius: 8px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.65rem; flex-shrink: 0; }
        .sidebar-profile-info h4 { font-size: 0.75rem; font-weight: 600; color: var(--text-primary); }
        .sidebar-profile-info p { font-size: 0.6rem; color: var(--text-muted); }
        .sidebar-menu { flex: 1; padding: 0.75rem 0; }
        .sidebar-section-title { font-size: 0.6rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; padding: 0.85rem 1.25rem 0.45rem; }
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
        .sidebar-menu-item i { width: 20px; text-align: center; font-size: 0.85rem; color: #4A7C84; transition: var(--transition); }
        .sidebar-menu-item:hover { background: var(--sidebar-hover); color: var(--primary); border-left-color: var(--primary); transform: translateX(4px); }
        .sidebar-menu-item:hover i { color: var(--primary); transform: scale(1.1); }
        .sidebar-menu-item.active { background: var(--sidebar-active-bg); color: var(--primary); font-weight: 600; border-left-color: var(--primary); box-shadow: 0 2px 8px rgba(43, 76, 82, 0.08); }
        .sidebar-menu-item.active i { color: var(--primary); }
        .sidebar-menu-item .badge { margin-left: auto; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.6rem; font-weight: 700; background: var(--primary-bg); color: var(--primary); }
        .sidebar-menu-item .badge.red { background: var(--danger-bg); color: var(--danger); }
        .sidebar-submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
            opacity: 0;
        }
        .sidebar-submenu.open { max-height: 400px; opacity: 1; }
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
        .sidebar-submenu-item i { width: 16px; text-align: center; font-size: 0.75rem; color: #4A7C84; transition: var(--transition); }
        .sidebar-submenu-item:hover { background: var(--sidebar-hover); color: var(--primary); }
        .sidebar-submenu-item:hover i { color: var(--primary); }
        .sidebar-submenu-item.active { background: var(--sidebar-active-bg); color: var(--primary); font-weight: 600; }
        .sidebar-submenu-item.active i { color: var(--primary); }
        .sidebar-menu-toggle { cursor: pointer; }
        .sidebar-menu-toggle .toggle-arrow { margin-left: auto; font-size: 0.6rem; color: var(--text-muted); transition: transform 0.3s ease; }
        .sidebar-menu-toggle.open .toggle-arrow { transform: rotate(180deg); }
        .sidebar-footer { padding: 0.75rem 1.25rem; border-top: 1px solid rgba(43, 76, 82, 0.1); }
        .sidebar-footer-item { display: flex; align-items: center; gap: 0.65rem; padding: 0.5rem 0; font-size: 0.78rem; color: var(--text-muted); text-decoration: none; transition: var(--transition); }
        .sidebar-footer-item:hover { color: var(--primary); }
        .sidebar-footer-item i { width: 18px; font-size: 0.85rem; color: #4A7C84; }
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
        .header-search-wrapper i { position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%); color: #adb5bd; font-size: 0.9rem; pointer-events: none; }
        .header-search-input {
            width: 100%; padding: 0.7rem 1rem 0.7rem 2.85rem;
            border: 1.5px solid var(--border-color); border-radius: 50px;
            font-size: 0.85rem; font-family: var(--font); background: #F8FAFC;
            color: var(--text-primary); transition: var(--transition);
        }
        .header-search-input:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 4px var(--primary-bg); }
        .header-search-input::placeholder { color: #adb5bd; }
        .top-header-right { display: flex; align-items: center; gap: 0.5rem; }
        .header-icon-btn { width: 42px; height: 42px; border-radius: 12px; border: 1px solid var(--border-color); background: white; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: var(--transition); position: relative; text-decoration: none; }
        .header-icon-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); transform: translateY(-1px); }
        .header-profile-btn { display: flex; align-items: center; gap: 0.6rem; padding: 0.35rem 0.75rem 0.35rem 0.35rem; border-radius: 50px; border: 1px solid var(--border-color); background: white; cursor: pointer; transition: var(--transition); text-decoration: none; color: inherit; }
        .header-profile-btn:hover { border-color: var(--primary); background: var(--primary-bg); }
        .header-profile-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.65rem; flex-shrink: 0; }
        .header-profile-name { font-size: 0.8rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; }
        .header-profile-arrow { font-size: 0.65rem; color: var(--text-muted); margin-left: 0.15rem; }
        .header-profile-dropdown-wrapper { position: relative; }
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
        .header-profile-dropdown-menu.active { opacity: 1; visibility: visible; transform: translateY(0); }
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
        }
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

        .tracking-page { width: 100%; }
        .order-header-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .order-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 10px;
        }
        .order-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            color: #666;
            font-size: 14px;
        }
        .order-meta span { display: flex; align-items: center; gap: 5px; }
        .progress-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .progress-bar-container { position: relative; margin: 40px 0 20px; }
        .progress-bar { height: 8px; background: #e0e0e0; border-radius: 4px; position: relative; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #2563eb, #3b82f6); border-radius: 4px; transition: width 0.5s ease; }
        .progress-steps { display: flex; justify-content: space-between; position: relative; margin-top: -20px; }
        .progress-step { display: flex; flex-direction: column; align-items: center; position: relative; z-index: 1; flex: 1; }
        .step-circle {
            width: 40px; height: 40px; border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #999;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }
        .progress-step.completed .step-circle { background: #2563eb; color: white; }
        .progress-step.active .step-circle { background: #3b82f6; color: white; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2); }
        .step-label { font-size: 11px; color: #666; text-align: center; max-width: 80px; line-height: 1.2; }
        .progress-step.completed .step-label,
        .progress-step.active .step-label { color: #1a1a1a; font-weight: 500; }
        .current-status { text-align: center; padding: 20px; background: #f0f9ff; border-radius: 8px; margin-bottom: 20px; }
        .status-badge { display: inline-block; padding: 8px 20px; background: #2563eb; color: white; border-radius: 20px; font-weight: 600; font-size: 14px; margin-bottom: 10px; }
        .estimated-delivery { color: #666; font-size: 14px; }
        .tracking-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px; }
        .info-item { padding: 12px; background: #f8f9fa; border-radius: 8px; }
        .info-label { font-size: 12px; color: #666; margin-bottom: 4px; }
        .info-value { font-weight: 600; color: #1a1a1a; }
        .timeline-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .section-title { font-size: 20px; font-weight: 700; color: #1a1a1a; margin-bottom: 20px; }
        .order-items-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .order-item-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .order-item-row:last-child { border-bottom: none; }
        .order-item-row img {
            width: 48px; height: 48px; border-radius: 8px;
            object-fit: cover; border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        .order-item-details { flex: 1; min-width: 0; }
        .order-item-name { font-size: 0.88rem; font-weight: 600; color: #111827; }
        .order-item-meta { font-size: 0.78rem; color: #64748b; margin-top: 0.15rem; }
        .order-item-total { font-size: 0.9rem; font-weight: 700; color: #2B4C52; white-space: nowrap; }
        .timeline { position: relative; padding-left: 30px; }
        .timeline::before { content: ''; position: absolute; left: 8px; top: 0; bottom: 0; width: 2px; background: #e0e0e0; }
        .timeline-item { position: relative; padding-bottom: 25px; }
        .timeline-item:last-child { padding-bottom: 0; }
        .timeline-dot { position: absolute; left: -26px; top: 0; width: 16px; height: 16px; border-radius: 50%; background: #2563eb; border: 3px solid white; box-shadow: 0 0 0 2px #2563eb; }
        .timeline-content { padding-left: 15px; }
        .timeline-title { font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
        .timeline-time { font-size: 12px; color: #666; margin-bottom: 4px; }
        .timeline-notes { font-size: 14px; color: #666; margin-top: 5px; }
        .media-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-top: 15px; }
        .media-item { position: relative; border-radius: 8px; overflow: hidden; aspect-ratio: 1; cursor: pointer; transition: transform 0.2s; }
        .media-item:hover { transform: scale(1.05); }
        .media-item img { width: 100%; height: 100%; object-fit: cover; }
        .media-item .file-icon { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: #f0f9ff; color: #2563eb; font-size: 40px; }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #f0f9ff;
            color: #2563eb;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            transition: background 0.2s;
        }
        .back-btn:hover { background: #e0f2fe; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        .empty-state svg { width: 64px; height: 64px; margin-bottom: 15px; opacity: 0.5; }

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
            .progress-steps { overflow-x: auto; padding-bottom: 10px; }
            .step-label { font-size: 10px; max-width: 60px; }
            .step-circle { width: 32px; height: 32px; font-size: 12px; }
        }
        @media (max-width: 480px) {
            .top-header-center { display: none; }
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
        .modal-contact-icon { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, var(--primary), #4A7C84); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
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
<?php if (empty($_GET['modal'])): ?>
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
      <a href="my-orders.php" class="sidebar-menu-item active"><i class="fas fa-box"></i> My Orders<span class="sidebar-badge" id="sidebar-orders-badge"></span></a>
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
    </nav>
        <div class="sidebar-footer">
            <button class="sidebar-footer-item" onclick="openModal('contact')"><i class="fas fa-envelope"></i> Contact</button>
            <button class="sidebar-footer-item" onclick="openModal('help')"><i class="fas fa-question-circle"></i> Help Center</button>
        </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<?php endif; ?>

    <main class="products-main"<?php if (!empty($_GET['modal'])) echo ' style="margin-left:0;"'; ?>>
<?php if (empty($_GET['modal'])): ?>
        <header class="top-header">
            <div class="top-header-inner">
                <div class="top-header-left">
                    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
                    <div class="top-header-title">
                        <h1>Order Tracking</h1>
                        <p>Track your order status and timeline</p>
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
<?php endif; ?>

        <div class="content-area">
            <div class="tracking-page">
                <?php if (empty($_GET['modal'])): ?>
                <a href="profile.php" class="back-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Orders
                </a>
                <?php endif; ?>

                <div class="order-header-card">
                    <h1 class="order-title">Order #<?php echo $order['order_reference'] ?? $order['id']; ?></h1>
                    <div class="order-meta">
                        <span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <?php echo date('F j, Y', strtotime($order['created_at'])); ?>
                        </span>
                        <span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <?php echo htmlspecialchars($order['name']); ?>
                        </span>
                    </div>
                </div>

                <div class="order-items-card">
                    <h2 class="section-title"><i class="fas fa-box"></i> Order Items</h2>
                    <?php if (!empty($orderItems)): ?>
                        <?php foreach ($orderItems as $item): ?>
                        <div class="order-item-row">
                            <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                            <div class="order-item-details">
                                <div class="order-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="order-item-meta">Qty: <?php echo (int)$item['quantity']; ?> × ₱<?php echo number_format((float)$item['unit_price'], 2); ?></div>
                            </div>
                            <div class="order-item-total">₱<?php echo number_format((float)$item['unit_price'] * (int)$item['quantity'], 2); ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#64748b;font-size:0.85rem;">No items found.</p>
                    <?php endif; ?>
                </div>

                <div class="progress-section">
                    <div class="current-status">
                        <div class="status-badge"><?php echo $statusLabels[$currentStatus] ?? ucfirst($currentStatus); ?></div>
                        <?php if ($order['estimated_delivery']): ?>
                            <div class="estimated-delivery">
                                Estimated Delivery: <?php echo date('F j, Y', strtotime($order['estimated_delivery'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="progress-bar-container">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progressPercent; ?>%"></div>
                        </div>
                        <div class="progress-steps">
                            <?php
                            $steps = [
                                1 => 'Pending',
                                2 => 'Confirmed',
                                3 => 'Shipped',
                                4 => 'Delivered',
                                5 => 'Completed',
                            ];
                            foreach ($steps as $num => $label):
                                $stepStatus = '';
                                if ($num < $currentStep) $stepStatus = 'completed';
                                elseif ($num == $currentStep) $stepStatus = 'active';
                            ?>
                                <div class="progress-step <?php echo $stepStatus; ?>">
                                    <div class="step-circle"><?php echo $num; ?></div>
                                    <div class="step-label"><?php echo $label; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="tracking-info">
                        <?php if ($order['tracking_number']): ?>
                            <div class="info-item">
                                <div class="info-label">Tracking Number</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['tracking_number']); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($order['courier']): ?>
                            <div class="info-item">
                                <div class="info-label">Courier</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['courier']); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($order['shipping_fee']): ?>
                            <div class="info-item">
                                <div class="info-label">Shipping Fee</div>
                                <div class="info-value" style="color:#2B4C52;font-weight:700;">₱<?php echo number_format((float)$order['shipping_fee'], 2); ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($order['payment_method']): ?>
                            <div class="info-item">
                                <div class="info-label">Payment Method</div>
                                <div class="info-value"><?php echo ucfirst($order['payment_method']); ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <div class="info-label">Payment Status</div>
                            <div class="info-value" style="color: <?php echo $order['payment_status'] === 'paid' ? '#10b981' : '#f59e0b'; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="timeline-section">
                    <h2 class="section-title">Order Timeline</h2>
                    <div class="timeline">
                        <?php if (empty($timeline)): ?>
                            <div class="empty-state">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <p>No timeline updates yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($timeline as $item): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-title">
                                            <?php echo $statusLabels[$item['to_status']] ?? ucfirst($item['to_status']); ?>
                                            <?php if ($item['from_status']): ?>
                                                <span style="color: #999; font-weight: 400;">
                                                    (from <?php echo $statusLabels[$item['from_status']] ?? ucfirst($item['from_status']); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="timeline-time">
                                            <?php echo date('F j, Y g:i A', strtotime($item['created_at'])); ?>
                                            <?php if ($item['name']): ?>
                                                by <?php echo htmlspecialchars($item['name']); ?>
                                                (<?php echo ucfirst($item['role']); ?>)
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($item['notes']): ?>
                                            <div class="timeline-notes"><?php echo htmlspecialchars($item['notes']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($order['seller_notes'])): ?>
                    <div class="media-section">
                        <h2 class="section-title">Seller Notes</h2>
                        <p style="color: #666; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($order['seller_notes'])); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($designFiles)): ?>
                    <div class="media-section">
                        <h2 class="section-title">Design Files</h2>
                        <div class="media-grid">
                            <?php foreach ($designFiles as $file): ?>
                                <div class="media-item" onclick="window.open('<?php echo htmlspecialchars($file); ?>', '_blank')">
                                    <img src="<?php echo htmlspecialchars($file); ?>" alt="Design file">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($proofImages)): ?>
                    <div class="media-section">
                        <h2 class="section-title">Proof Images</h2>
                        <div class="media-grid">
                            <?php foreach ($proofImages as $image): ?>
                                <div class="media-item" onclick="window.open('<?php echo htmlspecialchars($image); ?>', '_blank')">
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="Proof image">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($packagePhotos)): ?>
                    <div class="media-section">
                        <h2 class="section-title">Package Photos</h2>
                        <div class="media-grid">
                            <?php foreach ($packagePhotos as $photo): ?>
                                <div class="media-item" onclick="window.open('<?php echo htmlspecialchars($photo); ?>', '_blank')">
                                    <img src="<?php echo htmlspecialchars($photo); ?>" alt="Package photo">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($order['delivery_address']): ?>
                    <div class="media-section">
                        <h2 class="section-title">Delivery Address</h2>
                        <p style="color: #666; line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?><br>
                            <?php echo htmlspecialchars($order['delivery_city']); ?>, <?php echo htmlspecialchars($order['delivery_province']); ?> <?php echo htmlspecialchars($order['delivery_zip']); ?><br>
                            <?php echo htmlspecialchars($order['delivery_country']); ?>
                        </p>
                        <?php if ($order['contact_phone']): ?>
                            <p style="color: #666; margin-top: 10px;">
                                <strong>Contact:</strong> <?php echo htmlspecialchars($order['contact_name']); ?> - <?php echo htmlspecialchars($order['contact_phone']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('active');
}

function toggleProfileDropdown() {
    const dropdown = document.getElementById('profileDropdown');
    if (dropdown) dropdown.classList.toggle('active');
}

document.addEventListener('click', function(event) {
    if (!event.target.closest('.header-profile-dropdown-wrapper')) {
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileDropdown && profileDropdown.classList.contains('active')) {
            profileDropdown.classList.remove('active');
        }
    }
});

const searchInput = document.getElementById('product-search');
if (searchInput) {
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && this.value.trim()) {
            window.location.href = 'store-product.php?search=' + encodeURIComponent(this.value.trim());
        }
    });
}

(function() {
    const currentStatus = '<?php echo $currentStatus; ?>';
    const orderId = <?php echo $orderId; ?>;
    let pollInterval = null;

    async function checkStatusUpdate() {
        try {
            const res = await fetch('../api/orders.php?action=list&search=<?php echo $order['order_reference'] ?? 'INK-' . str_pad($orderId, 6, '0', STR_PAD_LEFT); ?>', { credentials: 'include' });
            const data = await res.json();
            if (data.success && data.orders && data.orders.length > 0) {
                const order = data.orders[0];
                if (order.status !== currentStatus) {
                    clearInterval(pollInterval);
                    const notif = document.createElement('div');
                    notif.style.cssText = 'position:fixed;top:1rem;right:1rem;background:#10b981;color:white;padding:1rem 1.5rem;border-radius:12px;font-weight:600;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,0.2);animation:slideIn 0.3s ease;';
                    notif.textContent = '\ud83d\udd04 Order status updated to: ' + order.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    document.body.appendChild(notif);
                    setTimeout(() => location.reload(), 2000);
                }
            }
        } catch(e) {}
    }

    if (!['completed', 'cancelled', 'returned'].includes(currentStatus)) {
        pollInterval = setInterval(checkStatusUpdate, 3000);
    }
})();

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
<div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)closeModal()">
  <div class="modal-box">
    <div class="modal-header">
      <h2 id="modalTitle"></h2>
      <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="modalBody"></div>
  </div>
</div>
<script>navigator.sendBeacon('../api/track-visit.php?url=' + encodeURIComponent(location.pathname + location.search) + '&_=' + Date.now());</script>
</body>
</html>
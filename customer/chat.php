<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];
$userName = trim($_SESSION['user_name'] ?? '');
$userRole = $_SESSION['user_role'] ?? 'user';
$loggedIn = true;
$userInitials = '';
if ($userName !== '') {
    $parts = array_filter(preg_split('/\s+/', $userName));
    $userInitials = strtoupper(substr(reset($parts), 0, 1) . (count($parts) > 1 ? substr(next($parts), 0, 1) : ''));
}
if ($userInitials === '') $userInitials = 'U';
$isSeller = $userRole === 'admin';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Messages | Inkzion Spectrum Ads</title>
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
    .sidebar-footer button.sidebar-footer-item { background: none; border: none; cursor: pointer; width: 100%; text-align: left; font: inherit; color: var(--text-muted); display: flex; align-items: center; gap: 0.65rem; padding: 0.5rem 0; font-size: 0.78rem; text-decoration: none; transition: var(--transition); }
    .sidebar-footer button.sidebar-footer-item:hover { color: var(--primary); }
    
    .products-main { flex: 1; margin-left: var(--sidebar-width); min-height: 100vh; display: flex; flex-direction: column; }
    
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
    .header-icon-btn .notif-dot { position: absolute; top: 6px; right: 6px; width: 8px; height: 8px; border-radius: 50%; background: var(--danger); border: 2px solid white; }
    
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
    
    .content-area { padding: 1.5rem 2rem 2rem; flex: 1; display: flex; flex-direction: column; }
    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 99; }
    .sidebar-overlay.active { display: block; }

    /* Chat page-specific styles */
    .toast-container { position: fixed; top: 1.5rem; right: 1.5rem; z-index: 99999; display: flex; flex-direction: column; gap: 0.5rem; }
    .toast-msg { background: #1e293b; color: white; padding: 0.85rem 1.25rem; border-radius: 14px; font-size: 0.85rem; font-weight: 500; box-shadow: 0 8px 24px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 0.6rem; max-width: 380px; animation: toastIn 0.3s ease; cursor: pointer; }
    .toast-msg i { color: var(--primary); font-size: 1rem; }
    @keyframes toastIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    .chat-page { max-width: 1200px; margin: 0 auto; width: 100%; flex: 1; display: flex; flex-direction: column; }
    .page-header { margin-bottom: 1.5rem; }
    .page-header h1 { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 0.6rem; }
    .page-header h1 i { color: var(--primary); }
    .page-header p { color: var(--text-muted); margin-top: 0.15rem; }
    .chat-layout { display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; flex: 1; min-height: 0; }
    .chat-sidebar { background: white; border: 1px solid var(--border-color); border-radius: 20px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.04); display: flex; flex-direction: column; }
    .chat-sidebar-header { padding: 1.25rem; border-bottom: 1px solid var(--border-color); }
    .chat-sidebar-header h3 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-primary); }
    .conversation-list { flex: 1; overflow-y: auto; }
    .conversation-item { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-light); cursor: pointer; transition: all 0.2s ease; display: flex; gap: 0.75rem; align-items: center; }
    .conversation-item:hover { background: #f8fafc; }
    .conversation-item.active { background: var(--primary-bg); border-left: 3px solid var(--primary); }
    .conv-avatar { width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; flex-shrink: 0; }
    .conv-info { flex: 1; min-width: 0; }
    .conv-name { font-weight: 600; color: var(--text-primary); font-size: 0.92rem; margin-bottom: 0.2rem; }
    .conv-last-msg { font-size: 0.82rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .conv-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 0.3rem; }
    .conv-time { font-size: 0.75rem; color: var(--text-muted); }
    .conv-unread { background: var(--primary); color: white; font-size: 0.7rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 999px; min-width: 20px; text-align: center; }
    .online-dot { width: 10px; height: 10px; border-radius: 50%; background: #10b981; border: 2px solid white; position: absolute; bottom: 0; right: 0; }
    .delete-conv-btn { font-size:0.75rem;color:#94a3b8;cursor:pointer;padding:0.15rem 0.3rem;border-radius:4px;transition:all 0.15s ease;opacity:0; }
    .conversation-item:hover .delete-conv-btn, .conv-item:hover .delete-conv-btn { opacity:1; }
    .delete-conv-btn:hover { color:#dc2626;background:#fef2f2; }
    .chat-main { background: white; border: 1px solid var(--border-color); border-radius: 20px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.04); display: flex; flex-direction: column; }
    .chat-header { padding: 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 1rem; }
    .chat-header-info { flex: 1; }
    .chat-header-name { font-weight: 700; color: var(--text-primary); font-size: 1.05rem; }
    .chat-header-status { font-size: 0.82rem; color: var(--text-muted); }
    .chat-header-status.online { color: #10b981; }
    .chat-messages { flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
    .message { display: flex; gap: 0.75rem; max-width: 70%; }
    .message.sent { align-self: flex-end; flex-direction: row-reverse; }
    .message.received { align-self: flex-start; }
    .msg-avatar { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0; }
    .msg-bubble { padding: 0.85rem 1.1rem; border-radius: 16px; font-size: 0.92rem; line-height: 1.5; }
    .message.sent .msg-bubble { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border-bottom-right-radius: 4px; }
    .message.received .msg-bubble { background: var(--border-light); color: var(--text-primary); border-bottom-left-radius: 4px; }
    .msg-time { font-size: 0.72rem; color: var(--text-muted); margin-top: 0.3rem; }
    .message.sent .msg-time { text-align: right; }
    .msg-image { max-width: 250px; border-radius: 12px; cursor: pointer; }
    .msg-file { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem; background: rgba(255,255,255,0.1); border-radius: 10px; }
    .chat-input-area { padding: 1.25rem; border-top: 1px solid var(--border-color); }
    .chat-input-wrapper { display: flex; gap: 0.75rem; align-items: flex-end; }
    .chat-input { flex: 1; padding: 0.85rem 1rem; border: 2px solid var(--border-color); border-radius: 14px; font-size: 0.95rem; resize: none; max-height: 120px; font-family: inherit; }
    .chat-input:focus { outline: none; border-color: var(--primary); }
    .chat-actions { display: flex; gap: 0.5rem; }
    .btn-chat-action { width: 44px; height: 44px; border-radius: 12px; border: 2px solid var(--border-color); background: white; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text-muted); transition: all 0.2s ease; }
    .btn-chat-action:hover { border-color: var(--primary); color: var(--primary); }
    .btn-send { width: 44px; height: 44px; border-radius: 12px; border: none; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; }
    .btn-send:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(233,30,142,0.3); }
    .typing-indicator { font-size: 0.82rem; color: var(--text-muted); font-style: italic; padding: 0.5rem 1.5rem; }
    .no-chat-selected { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted); }
    .no-chat-selected i { font-size: 4rem; opacity: 0.2; margin-bottom: 1rem; }
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
      .chat-layout { grid-template-columns: 1fr; }
      .chat-sidebar { display: none; }
      .chat-sidebar.mobile-show { display: flex; position: fixed; inset: 0; z-index: 1000; }
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
    .modal-contact-icon { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
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
        <a href="chat.php" class="sidebar-menu-item active"><i class="fas fa-comments"></i> Messages</a>
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
              <h1>Messages</h1>
              <p>Chat with sellers</p>
            </div>
          </div>
          <div class="top-header-right">
            <a href="../index.php" class="header-icon-btn" title="Home"><i class="fas fa-home"></i></a>
            <a href="notifications.php" class="header-icon-btn" title="Notifications" style="position:relative;">
              <i class="fas fa-bell"></i>
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
        <!-- Toast container for new message notifications -->
        <div class="toast-container" id="toastContainer"></div>

        <div class="chat-page">
          <div class="page-header">
            <h1><i class="fas fa-comments"></i> Messages</h1>
            <p>Chat with sellers about your orders and custom printing requests.</p>
          </div>

      <div class="chat-layout">
        <!-- Conversations Sidebar -->
        <div class="chat-sidebar">
          <div class="chat-sidebar-header">
            <h3>Conversations</h3>
            <div style="margin-top:0.65rem;position:relative;">
              <input type="text" id="search-input" placeholder="Search conversations..." style="width:100%;padding:0.5rem 0.75rem;border:2px solid var(--border-color);border-radius:10px;font-size:0.82rem;outline:none;font-family:inherit;background:#f8fafc;transition:var(--transition);" oninput="searchConversations(this.value)">
              <i class="fas fa-search" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.8rem;"></i>
            </div>
          </div>
          <div class="conversation-list" id="conversation-list">
            <p style="text-align:center; color:#64748b; padding:2rem;">Loading conversations...</p>
          </div>
        </div>

        <!-- Chat Main Area -->
        <div class="chat-main" id="chat-main">
          <div class="no-chat-selected">
            <i class="fas fa-comments"></i>
            <p>Select a conversation to start messaging</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

  <!-- Hidden file input -->
  <input type="file" id="file-input" style="display:none;" accept="image/*,.pdf,.ai,.psd,.zip">

  <script>
    let currentConversation = null;
    let typingTimeout = null;
    let lastMessageCount = 0;
    let currentConvData = null;
    let isLoadingMore = false;
    let hasMoreMessages = false;
    let messageOffset = 0;
    let selectedFile = null;
    let allConversations = [];
    let autoScrollEnabled = true;
    let unreadTotal = 0;
    let lastMessageId = 0;
    let emptyPolls = 0;
    let pollTimer = null;
    let pollInterval = 2000;
    let isTabVisible = true;
    let isSending = false;
    let loadedMessageIds = new Set();
    let isLoadingConversation = false;

    function escapeHtml(text) {
      if (!text) return '';
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function updateTitleBadge() {
      let title = 'Messages | Inkzion Spectrum Ads';
      document.title = unreadTotal > 0 ? `(${unreadTotal}) ${title}` : title;
    }

    function showToast(message, senderName) {
      const container = document.getElementById('toastContainer');
      const toast = document.createElement('div');
      toast.className = 'toast-msg';
      toast.innerHTML = `<i class="fas fa-comment-dots"></i> <span><strong>${escapeHtml(senderName || 'Admin')}</strong>: ${escapeHtml(message)}</span>`;
      toast.onclick = function() {
        this.remove();
        // Scroll to chat if on mobile
        const chatMain = document.getElementById('chat-main');
        if (chatMain) chatMain.scrollIntoView({ behavior: 'smooth' });
      };
      container.appendChild(toast);
      setTimeout(() => {
        if (toast.parentNode) {
          toast.style.opacity = '0';
          toast.style.transform = 'translateX(100%)';
          toast.style.transition = 'all 0.3s ease';
          setTimeout(() => toast.remove(), 300);
        }
      }, 5000);
    }

    function showInlineNotification(message, type) {
      const container = document.getElementById('toastContainer');
      const toast = document.createElement('div');
      toast.className = 'toast-msg';
      const icon = type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle';
      toast.innerHTML = `<i class="fas fa-${icon}"></i> <span>${escapeHtml(message)}</span>`;
      toast.onclick = function() { this.remove(); };
      container.appendChild(toast);
      setTimeout(() => {
        if (toast.parentNode) {
          toast.style.opacity = '0';
          toast.style.transform = 'translateX(100%)';
          toast.style.transition = 'all 0.3s ease';
          setTimeout(() => toast.remove(), 300);
        }
      }, 4000);
    }

    function showFallbackContact(message, diagnostic) {
      const main = document.getElementById('chat-main');
      main.innerHTML = `
        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1.25rem;padding:3rem 2rem;text-align:center;height:100%;">
          <div style="width:56px;height:56px;border-radius:50%;background:rgba(43,76,82,0.1);display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#2B4C52;">
            <i class="fas fa-headset"></i>
          </div>
          <div style="font-size:0.9rem;font-weight:600;color:#0f172a;">${escapeHtml(message)}</div>
          ${diagnostic ? `<pre style="font-size:0.72rem;color:#dc2626;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:0.75rem;max-width:100%;overflow-x:auto;text-align:left;white-space:pre-wrap;word-break:break-word;">${escapeHtml(diagnostic)}</pre>` : ''}
          <div style="display:flex;flex-direction:column;gap:0.65rem;width:100%;max-width:320px;">
            <a href="tel:+63912456789" style="display:flex;align-items:center;gap:0.65rem;padding:0.75rem 1rem;background:white;border:1px solid #e2e8f0;border-radius:10px;text-decoration:none;color:#0f172a;font-size:0.85rem;font-weight:500;transition:all 0.15s;">
              <i class="fas fa-phone" style="color:#2B4C52;width:18px;"></i> +63 912 345 6789
            </a>
            <a href="mailto:info@inkzionspectrum.com" style="display:flex;align-items:center;gap:0.65rem;padding:0.75rem 1rem;background:white;border:1px solid #e2e8f0;border-radius:10px;text-decoration:none;color:#0f172a;font-size:0.85rem;font-weight:500;transition:all 0.15s;">
              <i class="fas fa-envelope" style="color:#2B4C52;width:18px;"></i> info@inkzionspectrum.com
            </a>
            <a href="https://facebook.com/inkzionspectrumads" target="_blank" style="display:flex;align-items:center;gap:0.65rem;padding:0.75rem 1rem;background:white;border:1px solid #e2e8f0;border-radius:10px;text-decoration:none;color:#0f172a;font-size:0.85rem;font-weight:500;transition:all 0.15s;">
              <i class="fab fa-facebook" style="color:#1877F2;width:18px;"></i> Inkzion Spectrum Ads
            </a>
          </div>
          <button onclick="autoSelectConversation()" style="margin-top:0.5rem;padding:0.6rem 1.5rem;border-radius:10px;border:none;background:linear-gradient(135deg,#2B4C52,var(--primary-light));color:white;font-weight:600;font-size:0.82rem;cursor:pointer;">
            <i class="fas fa-sync-alt"></i> Try Again
          </button>
        </div>
      `;
    }

    function getInitials(name) {
      if (!name) return '?';
      const parts = name.split(' ');
      return parts[0][0].toUpperCase() + (parts[1] ? parts[1][0].toUpperCase() : '');
    }

    function searchConversations(query) {
      const list = document.getElementById('conversation-list');
      const filtered = query.trim()
        ? allConversations.filter(c => {
            const name = (c.user_name || c.seller_name || '').toLowerCase();
            const prod = (c.product_name || '').toLowerCase();
            return name.includes(query.toLowerCase()) || prod.includes(query.toLowerCase());
          })
        : allConversations;
      
      if (filtered.length === 0) {
        list.innerHTML = '<p style="text-align:center; color:#64748b; padding:2rem;">No conversations found.</p>';
        return;
      }
      
      list.innerHTML = filtered.map(conv => renderConversationItem(conv)).join('');
    }

    function renderConversationItem(conv) {
      const name = conv.user_name || conv.seller_name || 'Unknown';
      const isOnline = conv.user_online || conv.seller_online;
      const unread = conv.unread_count || 0;
      const lastMsg = conv.last_message || 'No messages yet';
      const time = conv.last_message_at ? new Date(conv.last_message_at).toLocaleDateString() : '';
      const reqInfo = conv.request_type ? `<span style="font-size:0.7rem;color:#2B4C52;">${escapeHtml(conv.request_type)}</span>` : '';
      
      return `
        <div class="conversation-item ${currentConversation == conv.id ? 'active' : ''}" data-id="${conv.id}" onclick="selectConversation(${conv.id})">
          <div style="position:relative;">
            <div class="conv-avatar">${getInitials(name)}</div>
            ${isOnline ? '<div class="online-dot"></div>' : ''}
          </div>
          <div class="conv-info">
            <div class="conv-name">${escapeHtml(name)} ${reqInfo}</div>
            <div class="conv-last-msg">${escapeHtml(lastMsg)}</div>
          </div>
          <div class="conv-meta">
            <span class="conv-time">${time}</span>
            ${unread > 0 ? `<span class="conv-unread">${unread}</span>` : ''}
            <span class="delete-conv-btn" data-id="${conv.id}" title="Delete conversation">&times;</span>
          </div>
        </div>
      `;
    }

    async function loadConversations() {
      const list = document.getElementById('conversation-list');
      try {
        const res = await fetch('../api/chat.php?action=conversations', { credentials: 'include' });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        
        allConversations = data.conversations || [];
        unreadTotal = allConversations.reduce((sum, c) => sum + (c.unread_count || 0), 0);
        updateTitleBadge();
        
        if (allConversations.length === 0) {
          list.innerHTML = '<p style="text-align:center; color:#64748b; padding:2rem;">No conversations yet.</p>';
          return;
        }
        
        const searchInput = document.getElementById('search-input');
        if (searchInput && searchInput.value.trim()) {
          searchConversations(searchInput.value);
        } else {
          list.innerHTML = allConversations.map(conv => renderConversationItem(conv)).join('');
        }
      } catch (err) {
        list.innerHTML = '<p style="text-align:center; color:#dc2626; padding:2rem;">Failed to load conversations.</p>';
      }
    }

    function getDateLabel(dateStr) {
      const date = new Date(dateStr);
      const today = new Date();
      const yesterday = new Date(today);
      yesterday.setDate(yesterday.getDate() - 1);
      if (date.toDateString() === today.toDateString()) return 'Today';
      if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
      return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined });
    }

    function formatTime(dateStr) {
      return new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function startPolling() {
      stopPolling();
      emptyPolls = 0;
      pollInterval = 2000;
      doPoll();
    }

    function stopPolling() {
      if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
    }

    async function doPoll() {
      if (!currentConversation || !isTabVisible) {
        pollTimer = setTimeout(doPoll, isTabVisible ? pollInterval : 10000);
        return;
      }

      try {
        const url = `../api/chat-poll.php?conversation_id=${currentConversation}&last_message_id=${lastMessageId}&check_typing=1`;
        const res = await fetch(url, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);

        // Handle new messages
        const newMsgs = data.messages || [];
        if (newMsgs.length > 0) {
          lastMessageId = data.last_message_id || lastMessageId;
          emptyPolls = 0;
          pollInterval = 2000; // Fast poll when active
          await appendNewMessages(newMsgs);
        } else {
          emptyPolls++;
          if (emptyPolls > 3) pollInterval = Math.min(pollInterval + 1000, 10000);
        }

        // Handle typing indicator
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
          if (data.typing) {
            typingIndicator.style.display = 'block';
            typingIndicator.textContent = data.typing.user_name + ' is typing...';
          } else {
            typingIndicator.style.display = 'none';
          }
        }

        // Update conversation list for unread changes
        if (newMsgs.length > 0) {
          updateUnreadBadge();
          loadConversations();
        }
      } catch (e) {
        // Silently retry on error
      }

      // Adaptive scheduling: shorter interval if tab focused, longer if not
      const nextInterval = isTabVisible ? pollInterval : Math.max(pollInterval, 8000);
      pollTimer = setTimeout(doPoll, nextInterval);
    }

    async function appendNewMessages(newMsgs) {
      const messagesDiv = document.getElementById('chat-messages');
      if (!messagesDiv) return;

      const prevHeight = messagesDiv.scrollHeight;
      let lastDate = null;

      // Get the last date from current messages
      const dateEls = messagesDiv.querySelectorAll('[data-date-label]');
      if (dateEls.length > 0) {
        lastDate = dateEls[dateEls.length - 1].getAttribute('data-date-label');
      }

      let html = '';
      newMsgs.forEach(msg => {
        if (loadedMessageIds.has(msg.id)) return;
        const msgDate = getDateLabel(msg.created_at);
        if (msgDate !== lastDate) {
          html += `<div style="text-align:center;padding:0.5rem 0;font-size:0.75rem;color:#94a3b8;font-weight:500;" data-date-label="${msgDate}">${msgDate}</div>`;
          lastDate = msgDate;
        }
        html += renderMessage(msg);
        loadedMessageIds.add(msg.id);
      });

      if (!html) return;
      messagesDiv.insertAdjacentHTML('beforeend', html);
      lastMessageCount += newMsgs.length;

      // Auto-scroll only if enabled
      if (autoScrollEnabled) {
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
      }

      // Notifications for new messages from others
      newMsgs.forEach(msg => {
        if (msg.sender_id != <?= $userId ?>) {
          let body = '';
          if (msg.message_type === 'text') body = msg.content || 'Sent a message';
          else if (msg.message_type === 'image') body = 'Sent an image';
          else if (msg.message_type === 'file') body = 'Sent a file';
          else body = 'Sent a ' + (msg.message_type || 'message');

          // Toast
          showToast(body, msg.sender_name || 'Admin');

          // Browser notification (only if tab hidden)
          if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
            new Notification('New Message from ' + (msg.sender_name || 'Admin'), { body, icon: '../assets/logo.png' });
          }
        }
      });
    }

    async function updateUnreadBadge() {
      try {
        const res = await fetch('../api/chat-poll.php', { credentials: 'include' });
        const data = await res.json();
        if (data.success) {
          unreadTotal = data.unread_total || 0;
          updateTitleBadge();
        }
      } catch(e) {}
    }

    async function selectConversation(convId) {
      if (isLoadingConversation) return;
      isLoadingConversation = true;
      stopPolling();
      try {
        currentConversation = convId;
        currentConvData = null;
        messageOffset = 0;
        hasMoreMessages = false;
        lastMessageCount = 0;
        autoScrollEnabled = true;
        lastMessageId = 0;
        loadedMessageIds = new Set();
        
        // Update active state
        document.querySelectorAll('.conversation-item').forEach(item => {
          item.classList.toggle('active', parseInt(item.dataset.id) === convId);
        });
        
        // Fetch fresh conversation data
        try {
          const r = await fetch('../api/chat.php?action=conversations', { credentials: 'include' });
          const d = await r.json();
          if (d.success) {
            currentConvData = (d.conversations || []).find(c => c.id == convId) || null;
          }
        } catch(e) {}
        
        // Load messages
        await loadMessages(convId);
        
        // Start adaptive polling
        startPolling();
      } catch(e) {
        console.error('Failed to load conversation:', e);
      } finally {
        isLoadingConversation = false;
      }
    }

    function parseMsgContent(str) {
      try { return JSON.parse(str); } catch(e) { return null; }
    }

    async function loadMoreMessages() {
      if (isLoadingMore || !hasMoreMessages || !currentConversation) return;
      isLoadingMore = true;
      
      const messagesDiv = document.getElementById('chat-messages');
      const prevHeight = messagesDiv.scrollHeight;
      
      const loadingEl = document.createElement('div');
      loadingEl.id = 'load-more-indicator';
      loadingEl.style.cssText = 'text-align:center;padding:0.75rem;color:#94a3b8;font-size:0.82rem;';
      loadingEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading older messages...';
      messagesDiv.prepend(loadingEl);
      
      try {
        messageOffset += 50;
        const res = await fetch(`../api/chat.php?action=messages&conversation_id=${currentConversation}&limit=50&offset=${messageOffset}`, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        
        hasMoreMessages = data.has_more || false;
        document.getElementById('load-more-indicator')?.remove();
        
        const olderMessages = data.messages || [];
        if (olderMessages.length > 0) {
          let olderHtml = '';
          let lastDate = null;
          olderMessages.forEach(msg => {
            const msgDate = getDateLabel(msg.created_at);
            if (msgDate !== lastDate) {
              olderHtml += `<div style="text-align:center;padding:0.5rem 0;font-size:0.75rem;color:#94a3b8;font-weight:500;">${lastDate === null ? '' : ''}${msgDate}</div>`;
              lastDate = msgDate;
            }
            olderHtml += renderMessage(msg);
          });
          messagesDiv.insertAdjacentHTML('afterbegin', olderHtml);
          const newHeight = messagesDiv.scrollHeight;
          messagesDiv.scrollTop = newHeight - prevHeight;
        }
      } catch (err) {
        document.getElementById('load-more-indicator')?.remove();
        showInlineNotification('Failed to load older messages', 'error');
      }
      isLoadingMore = false;
    }

    function renderMessage(msg) {
      const isSent = msg.sender_id == <?= $userId ?>;
      const time = formatTime(msg.created_at);
      
      let content = '';
      if (msg.message_type === 'text') {
        content = `<div class="msg-bubble">${escapeHtml(msg.content)}</div>`;
      } else if (msg.message_type === 'image') {
        const imgUrl = msg.file_url && (msg.file_url.startsWith('http') || msg.file_url.startsWith('uploads/'))
          ? msg.file_url : (msg.file_url || '');
        content = `<div class="msg-bubble"><img src="${escapeHtml(imgUrl)}" class="msg-image" onclick="window.open('${escapeHtml(imgUrl)}')" alt="Image" loading="lazy"></div>`;
      } else if (msg.message_type === 'file') {
        const fileUrl = msg.file_url || '#';
        content = `<div class="msg-bubble"><div class="msg-file"><i class="fas fa-file"></i><div><div>${escapeHtml(msg.file_name || 'File')}</div><div style="font-size:0.75rem; opacity:0.8;">${escapeHtml(msg.file_type || '')}</div></div></div></div>`;
      } else if (msg.message_type === 'custom_request') {
        const rd = parseMsgContent(msg.content);
        const reqTitle = rd ? (rd.title || 'Custom Request') : 'Custom Request';
        const reqId = rd ? rd.request_id : null;
        const link = reqId ? 'request-form.php?id=' + reqId : '#';
        content = `<div class="msg-bubble"><div style="background:white;border:1px solid #e2e8f0;border-radius:12px;padding:0.85rem;">
          <div style="font-weight:600;font-size:0.85rem;color:#0f172a;margin-bottom:0.3rem;">
            <i class="fas fa-paint-brush" style="color:#2B4C52;margin-right:0.35rem;"></i> ${escapeHtml(reqTitle)}
          </div>
          <a href="${escapeHtml(link)}" style="display:inline-block;padding:0.35rem 0.75rem;border-radius:8px;background:#2B4C52;color:white;font-size:0.75rem;font-weight:600;text-decoration:none;margin-top:0.4rem;">
            <i class="fas fa-external-link-alt"></i> Fill Form
          </a>
        </div></div>`;
      } else if (msg.message_type === 'order_form') {
        const rd = parseMsgContent(msg.content);
        const propTitle = rd ? (rd.title || 'Order Form') : 'Order Form';
        const propId = rd ? rd.proposal_id : null;
        const link = propId ? 'order-form.php?id=' + propId : '#';
        content = `<div class="msg-bubble"><div style="background:white;border:1px solid #e2e8f0;border-radius:12px;padding:0.85rem;">
          <div style="font-weight:600;font-size:0.85rem;color:#0f172a;margin-bottom:0.3rem;">
            <i class="fas fa-file-invoice" style="color:#10b981;margin-right:0.35rem;"></i> ${escapeHtml(propTitle)}
          </div>
          <a href="${escapeHtml(link)}" style="display:inline-block;padding:0.35rem 0.75rem;border-radius:8px;background:#10b981;color:white;font-size:0.75rem;font-weight:600;text-decoration:none;margin-top:0.4rem;">
            <i class="fas fa-external-link-alt"></i> Fill Order Form
          </a>
        </div></div>`;
      }
      
      return `
        <div class="message ${isSent ? 'sent' : 'received'}">
          <div class="msg-avatar">${getInitials(msg.sender_name)}</div>
          <div>
            ${content}
            <div class="msg-time">${time}</div>
          </div>
        </div>
      `;
    }

    async function loadMessages(convId, silent = false) {
      const chatMain = document.getElementById('chat-main');
      const prevMsgCount = lastMessageCount;
      const convData = currentConvData;
      
      if (!silent) {
        let prodBar = '';
        if (convData && convData.product_name) {
          const img = convData.product_image
            ? `<img src="${escapeHtml(convData.product_image)}" alt="" style="width:36px;height:36px;border-radius:8px;object-fit:cover;">`
            : `<div style="width:36px;height:36px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:1rem;color:#2B4C52;"><i class="fas fa-box"></i></div>`;
          prodBar = `<div style="display:flex;align-items:center;gap:0.75rem;padding:0.65rem 1.25rem;background:#E8F1ED;border-bottom:1px solid rgba(43,76,82,0.12);">
            ${img}<div style="font-size:0.82rem;font-weight:600;color:#0f172a;">${escapeHtml(convData.product_name)}<small style="font-weight:400;color:#64748b;"> &middot; Product</small></div>
          </div>`;
        }
        const sellerName = convData ? (convData.seller_name || 'Admin') : 'Admin';
        const isOnline = convData ? (convData.seller_online ? 'online' : '') : '';
        chatMain.innerHTML = prodBar + `
          <div class="chat-header" id="chat-header">
            <div class="conv-avatar" id="chat-avatar">${getInitials(sellerName)}</div>
            <div class="chat-header-info">
              <div class="chat-header-name" id="chat-name">${escapeHtml(sellerName)}</div>
              <div class="chat-header-status ${isOnline}" id="chat-status">${isOnline ? 'Online' : 'Offline'}</div>
            </div>
          </div>
          <div class="chat-messages" id="chat-messages">
            <p style="text-align:center; color:#64748b; padding:2rem;">Loading messages...</p>
          </div>
          <div class="typing-indicator" id="typing-indicator" style="display:none;"></div>
          <div class="chat-input-area">
            <div id="file-preview" style="display:none;margin-bottom:0.5rem;padding:0.5rem;background:#f8fafc;border-radius:8px;border:1px solid var(--border-color);position:relative;">
              <button onclick="clearFilePreview()" style="position:absolute;top:4px;right:4px;width:22px;height:22px;border-radius:50%;border:none;background:#ef4444;color:white;cursor:pointer;font-size:0.65rem;display:flex;align-items:center;justify-content:center;"><i class="fas fa-times"></i></button>
              <div id="file-preview-content" style="display:flex;align-items:center;gap:0.5rem;">
                <i class="fas fa-file" style="color:#2B4C52;font-size:1.2rem;"></i>
                <span id="file-preview-name" style="font-size:0.82rem;color:var(--text-primary);"></span>
              </div>
            </div>
            <div class="chat-input-wrapper">
              <textarea class="chat-input" id="message-input" placeholder="Type a message..." rows="1"></textarea>
              <div class="chat-actions">
                <button class="btn-chat-action" onclick="document.getElementById('file-input').click()" title="Attach file">
                  <i class="fas fa-paperclip"></i>
                </button>
                <button class="btn-send" id="btn-send-msg" title="Send message" type="button">
                  <i class="fas fa-paper-plane"></i>
                </button>
              </div>
            </div>
          </div>
        `;
      }
      
      try {
        const res = await fetch(`../api/chat.php?action=messages&conversation_id=${convId}&limit=50&offset=0`, { credentials: 'include' });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        
        const messages = data.messages || [];
        hasMoreMessages = data.has_more || false;
        const messagesDiv = document.getElementById('chat-messages');
        
        if (messages.length === 0) {
          messagesDiv.innerHTML = '<p style="text-align:center; color:#64748b; padding:2rem;">No messages yet. Start the conversation!</p>';
          lastMessageCount = 0;
          loadedMessageIds = new Set();
        } else {
          let html = '';
          let lastDate = null;
          
          messages.forEach(msg => {
            const msgDate = getDateLabel(msg.created_at);
            if (msgDate !== lastDate) {
              html += `<div style="text-align:center;padding:0.5rem 0;font-size:0.75rem;color:#94a3b8;font-weight:500;">${msgDate}</div>`;
              lastDate = msgDate;
            }
            html += renderMessage(msg);
          });
          
          if (hasMoreMessages) {
            html = `<div style="text-align:center;padding:0.5rem;"><button onclick="loadMoreMessages()" style="background:none;border:1px solid #e2e8f0;border-radius:8px;padding:0.4rem 1rem;color:#2B4C52;font-size:0.78rem;font-weight:500;cursor:pointer;"><i class="fas fa-chevron-up"></i> Load older messages</button></div>` + html;
          }
          
          messagesDiv.innerHTML = html;
          messagesDiv.scrollTop = messagesDiv.scrollHeight;
          lastMessageCount = messages.length;
          loadedMessageIds = new Set(messages.map(m => m.id));
          if (messages.length > 0) {
            lastMessageId = messages[messages.length - 1].id;
          }
        }
        
        // Push notification when tab not focused
        if (silent && prevMsgCount > 0 && messages.length > prevMsgCount && document.hidden) {
          const newMsgs = messages.slice(-(messages.length - prevMsgCount));
          newMsgs.forEach(msg => {
            if (msg.sender_id != <?= $userId ?>) {
              let body = '';
              if (msg.message_type === 'text') body = msg.content;
              else if (msg.message_type === 'image') body = 'Sent an image';
              else if (msg.message_type === 'file') body = 'Sent a file';
              else body = 'Sent a ' + (msg.message_type || 'message');
              if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('New Message from ' + (msg.sender_name || 'Admin'), { body: body, icon: '../assets/logo.png' });
              }
            }
          });
        }
        
        if (silent && prevMsgCount > 0 && messages.length > prevMsgCount) {
          const newMsgs = messages.slice(prevMsgCount);
          newMsgs.forEach(msg => {
            if (msg.sender_id != <?= $userId ?>) {
              let toastMsg = '';
              if (msg.message_type === 'custom_request') toastMsg = 'Sent you a custom request form';
              else if (msg.message_type === 'order_form') toastMsg = 'Sent you an order form';
              else if (msg.message_type === 'text') toastMsg = msg.content;
              else toastMsg = 'Sent you a ' + (msg.message_type || 'message');
              showToast(toastMsg, msg.sender_name || 'Admin');
            }
          });
        }
        
        if (silent && messagesDiv && messages.length > prevMsgCount && autoScrollEnabled) {
          messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
        
        checkTypingStatus();
      } catch (err) {
        if (!silent) {
          document.getElementById('chat-messages').innerHTML = '<p style="text-align:center; color:#dc2626; padding:2rem;">Failed to load messages.</p>';
        }
        throw err;
      }
    }

    // Track scroll position for auto-scroll
    document.addEventListener('scroll', function(e) {
      const messagesDiv = document.getElementById('chat-messages');
      if (!messagesDiv) return;
      const threshold = 30;
      autoScrollEnabled = (messagesDiv.scrollHeight - messagesDiv.scrollTop - messagesDiv.clientHeight) < threshold;
    }, true);

    // Infinite scroll up for older messages
    document.addEventListener('scroll', function(e) {
      const messagesDiv = document.getElementById('chat-messages');
      if (!messagesDiv || !hasMoreMessages || isLoadingMore) return;
      if (messagesDiv.scrollTop < 80) {
        loadMoreMessages();
      }
    }, true);

    async function checkTypingStatus() {
      if (!currentConversation) return;
      try {
        const res = await fetch(`../api/chat.php?action=typing_status&conversation_id=${currentConversation}`, { credentials: 'include' });
        const data = await res.json();
        const indicator = document.getElementById('typing-indicator');
        if (!indicator) return;
        if (data.success && data.is_typing && data.user_id != <?= $userId ?>) {
          indicator.style.display = 'block';
          indicator.textContent = data.user_name + ' is typing...';
        } else {
          indicator.style.display = 'none';
        }
      } catch(e) {}
    }

    async function sendMessage() {
      if (isSending) return;
      const input = document.getElementById('message-input');
      const content = input ? input.value.trim() : '';
      
      if ((!content && !selectedFile) || !currentConversation) return;
      isSending = true;
      
      try {
        if (selectedFile) {
          const formData = new FormData();
          formData.append('action', 'send_message');
          formData.append('conversation_id', currentConversation);
          formData.append('file', selectedFile);
          if (content) formData.append('content', content);
          
          const res = await fetch('../api/chat.php', {
            method: 'POST',
            credentials: 'include',
            body: formData
          });
          const data = await res.json();
          if (data.success) {
            if (input) input.value = '';
            clearFilePreview();
            await loadConversations();
          } else {
            showInlineNotification(data.error || 'Failed to send message', 'error');
          }
        } else if (content) {
          const res = await fetch('../api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
              action: 'send_message',
              conversation_id: currentConversation,
              message_type: 'text',
              content: content
            })
          });
          const data = await res.json();
          if (data.success) {
            if (input) input.value = '';
            await loadConversations();
          } else {
            showInlineNotification(data.error || 'Failed to send message', 'error');
          }
        }
      } catch (err) {
        showInlineNotification('Failed to send message', 'error');
      } finally {
        isSending = false;
      }
    }

    function clearFilePreview() {
      selectedFile = null;
      const preview = document.getElementById('file-preview');
      if (preview) preview.style.display = 'none';
      document.getElementById('file-input').value = '';
    }

    function showFilePreview(file) {
      const preview = document.getElementById('file-preview');
      const content = document.getElementById('file-preview-content');
      const name = document.getElementById('file-preview-name');
      if (!preview || !content || !name) return;
      
      if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
          content.innerHTML = `<img src="${e.target.result}" style="width:48px;height:48px;border-radius:8px;object-fit:cover;flex-shrink:0;"><span style="font-size:0.82rem;color:var(--text-primary);">${escapeHtml(file.name)}</span>`;
        };
        reader.readAsDataURL(file);
      } else {
        const icon = file.type.includes('pdf') ? 'fa-file-pdf' : file.type.includes('zip') ? 'fa-file-archive' : file.type.includes('word') || file.type.includes('document') ? 'fa-file-word' : 'fa-file';
        content.innerHTML = `<i class="fas ${icon}" style="color:#2B4C52;font-size:1.2rem;"></i><span style="font-size:0.82rem;color:var(--text-primary);">${escapeHtml(file.name)} (${(file.size / 1024 / 1024).toFixed(1)} MB)</span>`;
      }
      preview.style.display = 'block';
    }

    // File upload with preview
    document.getElementById('file-input').addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (!file) return;
      if (file.size > 10 * 1024 * 1024) {
        showInlineNotification('File too large. Maximum size is 10MB.', 'error');
        e.target.value = '';
        return;
      }
      if (currentConversation) {
        selectedFile = file;
        showFilePreview(file);
      } else {
        showInlineNotification('Select a conversation first', 'error');
        e.target.value = '';
      }
    });

    // Typing indicator (delegated)
    document.addEventListener('input', function(e) {
      if (e.target.id === 'message-input' && currentConversation) {
        fetch('../api/chat.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ action: 'typing', conversation_id: currentConversation, is_typing: 1 })
        });
        if (typingTimeout) clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
          fetch('../api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ action: 'typing', conversation_id: currentConversation, is_typing: 0 })
          });
        }, 2000);
      }
    });

    // Delete conversation handler (delegated)
    document.addEventListener('click', function(e) {
      var btn=e.target.closest('.delete-conv-btn');
      if(btn){
        e.stopPropagation();
        var convId=parseInt(btn.dataset.id);
        if(!convId)return;
        if(!confirm('Delete this conversation? All messages will be permanently removed.'))return;
        fetch('../api/chat.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify({action:'delete_conversation',conversation_id:convId})})
        .then(function(r){return r.json();})
        .then(function(d){
          if(d.success){
            if(currentConversation===convId){currentConversation=null;document.getElementById('chat-main').innerHTML='<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#94a3b8;font-size:0.95rem;">Select a conversation</div>';}
            loadConversations();
          }else showToast(d.error||'Failed to delete','error');
        })
        .catch(function(){showToast('Failed to delete','error');});
      }
    });

    // Send on button click (delegated)
    document.addEventListener('click', function(e) {
      if (e.target.closest('#btn-send-msg')) {
        sendMessage();
      }
    });

    // Send on Enter (delegated)
    document.addEventListener('keydown', function(e) {
      if (e.target.id === 'message-input' && e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    // Auto-select conversation or start one from URL params
    async function autoSelectConversation() {
      const params = new URLSearchParams(window.location.search);
      const convId = params.get('conversation');
      const productId = params.get('product_id');
      
      if (convId) {
        selectConversation(parseInt(convId));
        return;
      }
      
      if (productId) {
        // Fetch sellers to find an admin to start conversation with
        try {
          const sRes = await fetch('../api/chat.php?action=sellers', { credentials: 'include' });
          if (!sRes.ok) {
            const raw = await sRes.text();
            showFallbackContact('Failed to load sellers.', raw.substring(0, 500));
            return;
          }
          const sData = await sRes.json();
          if (!sData.success || !sData.sellers || !sData.sellers.length) {
            showFallbackContact('No admin sellers found in the system.');
            return;
          }
          const adminId = sData.sellers[0].id;
          
          try {
            const cRes = await fetch('../api/chat.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              credentials: 'include',
              body: JSON.stringify({
                action: 'start_conversation',
                admin_id: adminId,
                product_id: parseInt(productId)
              })
            });
            if (!cRes.ok) {
              const raw = await cRes.text();
              showFallbackContact('Conversation create failed.', raw.substring(0, 500));
              return;
            }
            const cData = await cRes.json();
            if (cData.success && cData.conversation_id) {
              await loadConversations();
              await selectConversation(cData.conversation_id);
            } else {
              showFallbackContact('Chat unavailable. Please contact us directly:', cData.sql_error || cData.error || 'Unknown error');
              console.error('Chat start failed:', cData);
            }
          } catch(e2) {
            console.error('Conversation POST error:', e2);
            let diag = e2.message || 'Unknown error';
            try { const t = await fetch('../api/chat.php?action=sellers', { credentials: 'include' }); diag += ' | sellers test: ' + (await t.text()).substring(0, 200); } catch(_) {}
            showFallbackContact('Chat temporarily unavailable. Please contact us directly:', diag);
          }
        } catch(e) {
          console.error('Sellers fetch error:', e);
          let raw = '';
          try { raw = await (await fetch('../api/chat.php?action=sellers', { credentials: 'include' })).text().then(r => r.substring(0, 300)); } catch(_) { raw = 'Could not fetch'; }
          showFallbackContact('Chat temporarily unavailable. Please contact us directly:', 'Sellers error: ' + (e.message || 'Unknown') + (raw ? ' | Response: ' + raw : ''));
        }
      }
    }

    // Request notification permission
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission();
    }

    // Tab visibility: pause polling when hidden, refresh on return
    document.addEventListener('visibilitychange', function() {
      isTabVisible = !document.hidden;
      if (document.hidden) {
        // Tab hidden, polling will slow down automatically
      } else {
        // Tab visible again, do an immediate poll and speed up
        emptyPolls = 0;
        pollInterval = 2000;
        if (currentConversation) {
          stopPolling();
          loadMessages(currentConversation, true).then(() => startPolling()).catch(() => {});
        }
      }
    });

    // Init
    loadConversations();
    autoSelectConversation();

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
            if (d.content) html += '<div style="margin-bottom:1.25rem;padding:1rem;background:rgba(43,76,82,0.04);border-radius:12px;border:1px solid rgba(43,76,82,0.08);"><p style="font-size:0.88rem;color:#475569;">' + esc(d.content) + '</p></div>';
            if (faqs.length) {
              faqs.forEach((f, i) => {
                html += '<details class="modal-faq"' + (i === 0 ? ' open' : '') + '><summary>' + esc(f.question || '') + ' <i class="fas fa-chevron-down"></i></summary><div class="modal-faq-answer">' + esc(f.answer || '') + '</div></details>';
              });
            } else {
              html += '<div style="text-align:center;padding:2rem;color:#64748b;"><i class="fas fa-question-circle" style="font-size:2.5rem;display:block;margin-bottom:0.75rem;color:rgba(43,76,82,0.15);"></i><p>No FAQs yet. Check back soon.</p></div>';
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

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
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Custom Printing | Inkzion Spectrum Ads</title>
  <meta name="description" content="Submit a custom printing request with your artwork and specifications." />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
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
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: var(--font); background: #F8FAFC; color: var(--text-primary); line-height: 1.6; overflow-x: hidden; }
    .dashboard-wrapper { display: flex; min-height: 100vh; }
    
    .products-sidebar {
      width: var(--sidebar-width); background: var(--sidebar-bg);
      border-right: 1px solid rgba(233, 30, 140, 0.1);
      padding: 0; position: fixed; top: 0; left: 0;
      height: 100vh; overflow-y: auto; z-index: 100;
      display: flex; flex-direction: column;
    }
    .products-sidebar::-webkit-scrollbar { width: 3px; }
    .products-sidebar::-webkit-scrollbar-thumb { background: rgba(233, 30, 140, 0.2); border-radius: 4px; }
    
    .sidebar-brand { display: flex; align-items: center; gap: 0.75rem; padding: 1.25rem 1.25rem 1rem; border-bottom: 1px solid rgba(233, 30, 140, 0.12); position: sticky; top: 0; background: var(--sidebar-bg); z-index: 2; }
    .sidebar-brand-img { width: 38px; height: 38px; border-radius: 10px; object-fit: contain; background: white; padding: 4px; box-shadow: 0 2px 6px rgba(233, 30, 140, 0.15); }
    .sidebar-brand-text { line-height: 1.2; }
    .sidebar-brand-name { font-size: 0.85rem; font-weight: 800; color: #1a1a2e; letter-spacing: 0.03em; display: block; }
    .sidebar-brand-sub { font-size: 0.6rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; }
    
    .sidebar-profile { padding: 0.85rem 1.25rem; display: flex; align-items: center; gap: 0.65rem; border-bottom: 1px solid rgba(233, 30, 140, 0.08); background: rgba(233, 30, 140, 0.03); }
    .sidebar-avatar { width: 30px; height: 30px; border-radius: 8px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.65rem; flex-shrink: 0; }
    .sidebar-profile-info h4 { font-size: 0.75rem; font-weight: 600; color: var(--text-primary); }
    .sidebar-profile-info p { font-size: 0.6rem; color: var(--text-muted); }
    
    .sidebar-menu { flex: 1; padding: 0.75rem 0; }
    .sidebar-section-title { font-size: 0.6rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; padding: 0.85rem 1.25rem 0.45rem; }
    .sidebar-menu-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 1.25rem; margin: 0 0.6rem; border-radius: 8px; font-size: 0.82rem; font-weight: 500; color: #4a4a5a; text-decoration: none; transition: var(--transition); position: relative; border-left: 3px solid transparent; }
    .sidebar-menu-item i { width: 20px; text-align: center; font-size: 0.85rem; color: #b06ab3; transition: var(--transition); }
    .sidebar-menu-item:hover { background: var(--sidebar-hover); color: var(--primary); border-left-color: var(--primary); transform: translateX(4px); }
    .sidebar-menu-item:hover i { color: var(--primary); transform: scale(1.1); }
    .sidebar-menu-item.active { background: var(--sidebar-active-bg); color: var(--primary); font-weight: 600; border-left-color: var(--primary); box-shadow: 0 2px 8px rgba(233, 30, 140, 0.08); }
    .sidebar-menu-item.active i { color: var(--primary); }
    
    .sidebar-footer { padding: 0.75rem 1.25rem; border-top: 1px solid rgba(233, 30, 140, 0.1); }
    .sidebar-footer-item { display: flex; align-items: center; gap: 0.65rem; padding: 0.5rem 0; font-size: 0.78rem; color: var(--text-muted); text-decoration: none; transition: var(--transition); }
    .sidebar-footer-item:hover { color: var(--primary); }
    .sidebar-footer-item i { width: 18px; font-size: 0.85rem; color: #b06ab3; }
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
    .header-profile-dropdown-item i { width: 18px; text-align: center; font-size: 0.8rem; color: #b06ab3; transition: var(--transition); }
    .header-profile-dropdown-item:hover { background: var(--sidebar-hover); color: var(--primary); }
    .header-profile-dropdown-item:hover i { color: var(--primary); }
    .header-profile-dropdown-item.danger { color: var(--danger); }
    .header-profile-dropdown-item.danger i { color: var(--danger); }
    .header-profile-dropdown-item.danger:hover { background: var(--danger-bg); color: var(--danger); }
    .header-profile-dropdown-item.danger:hover i { color: var(--danger); }
    
    .content-area { padding: 1.5rem 2rem 2rem; }
    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 99; }
    .sidebar-overlay.active { display: block; }

    /* Page-specific styles */
    .custom-page { max-width: 1000px; margin: 0 auto; }
    .page-heading { margin-bottom: 2rem; }
    .page-heading h1 { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; gap: 0.6rem; }
    .page-heading h1 i { color: var(--primary); }
    .page-heading p { color: var(--text-muted); margin-top: 0.15rem; }
    .custom-card { background: white; border: 1px solid var(--border-color); border-radius: 20px; padding: 2rem; box-shadow: 0 4px 16px rgba(0,0,0,0.04); margin-bottom: 1.5rem; }
    .custom-card h2 { font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin: 0 0 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
    .custom-card h2 i { color: var(--primary); }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-grid .full-width { grid-column: 1 / -1; }
    .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
    .form-group label { font-size: 0.85rem; font-weight: 600; color: #334155; }
    .form-group label .required { color: var(--primary); }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--border-color); border-radius: 10px; font-size: 0.9rem; font-family: inherit; color: var(--text-primary); background: white; outline: none; transition: border-color 0.2s; box-sizing: border-box; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
    .form-group textarea { min-height: 100px; resize: vertical; }
    .form-hint { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem; }
    .checkbox-group { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; }
    .checkbox-group input { width: 18px; height: 18px; accent-color: var(--primary); }
    .checkbox-group label { font-size: 0.9rem; color: #334155; cursor: pointer; }
    .upload-zone { border: 2px dashed var(--border-color); border-radius: 16px; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.2s ease; background: #f8fafc; }
    .upload-zone:hover, .upload-zone.dragover { border-color: var(--primary); background: var(--primary-bg); }
    .upload-zone i { font-size: 2.5rem; color: var(--primary); margin-bottom: 0.75rem; }
    .upload-zone p { margin: 0; color: var(--text-secondary); font-size: 0.9rem; }
    .upload-zone .upload-hint { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.25rem; }
    .file-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.75rem; margin-top: 1rem; }
    .file-item { position: relative; aspect-ratio: 1; border-radius: 12px; overflow: hidden; border: 1px solid var(--border-color); background: #f8fafc; }
    .file-item img { width: 100%; height: 100%; object-fit: cover; }
    .file-item .file-remove { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; border-radius: 50%; background: rgba(239,68,68,0.9); color: white; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; }
    .file-item .file-name { position: absolute; bottom: 0; left: 0; right: 0; padding: 0.35rem; background: rgba(0,0,0,0.6); color: white; font-size: 0.7rem; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .btn-submit { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.85rem 2rem; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border: none; border-radius: 12px; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 6px 16px rgba(233,30,142,0.25); }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(233,30,142,0.35); }
    .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .flash-msg { padding: 0.85rem 1.2rem; border-radius: 12px; margin-bottom: 1.25rem; font-size: 0.9rem; font-weight: 600; }
    .flash-success { background: rgba(5,150,105,0.1); color: #047857; border: 1px solid rgba(5,150,105,0.25); }
    .flash-error { background: rgba(239,68,68,0.1); color: #dc2626; border: 1px solid rgba(239,68,68,0.25); }
    .requests-list { display: flex; flex-direction: column; gap: 1rem; }
    .request-card { background: #f8fafc; border: 1px solid var(--border-color); border-radius: 14px; padding: 1.25rem; }
    .request-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
    .request-id { font-weight: 700; color: var(--text-primary); }
    .request-date { font-size: 0.82rem; color: var(--text-muted); }
    .request-status { padding: 0.3rem 0.75rem; border-radius: 999px; font-size: 0.78rem; font-weight: 700; }
    .status-pending { background: rgba(255,193,7,0.12); color: #b8860b; }
    .status-approved { background: rgba(5,150,105,0.12); color: #047857; }
    .status-in_review { background: rgba(59,130,246,0.12); color: #1d4ed8; }
    .status-ready_for_purchase { background: rgba(233,30,142,0.12); color: #be1871; }
    .status-rejected { background: rgba(239,68,68,0.12); color: #dc2626; }
    .status-completed { background: rgba(100,116,139,0.12); color: #475569; }
    .request-details { font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6; }
    .request-actions { display: flex; gap: 0.5rem; margin-top: 0.75rem; flex-wrap: wrap; }
    .request-actions .btn { padding: 0.45rem 1rem; border-radius: 10px; font-size: 0.82rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.2s; }
    .request-actions .btn:hover { transform: translateY(-1px); }
    .btn-chat { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border: none; }
    .request-details strong { color: var(--text-primary); }
    @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .form-grid .full-width { grid-column: 1; } }
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

        <a href="chat.php" class="sidebar-menu-item"><i class="fas fa-comments"></i> Messages</a>
        <div class="sidebar-section-title" style="padding-top:0.5rem;">Account</div>
        <a href="profile.php" class="sidebar-menu-item"><i class="fas fa-user"></i> My Profile</a>
      </nav>
      <div class="sidebar-footer">
        <a href="help-center.php" class="sidebar-footer-item"><i class="fas fa-question-circle"></i> Help Center</a>
      </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <main class="products-main">
      <header class="top-header">
        <div class="top-header-inner">
          <div class="top-header-left">
            <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
            <div class="top-header-title">
              <h1>Custom Printing</h1>
              <p>Tell us about your project</p>
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
        <div class="custom-page">
          <div class="page-heading">
            <h1><i class="fas fa-paint-brush"></i> Custom Printing Request</h1>
            <p>Tell us about your project and we'll bring your vision to life.</p>
          </div>

      <div id="success-message" class="flash-msg flash-success" style="display:none;"></div>
      <div id="error-message" class="flash-msg flash-error" style="display:none;"></div>

      <form id="custom-printing-form">
        <div class="custom-card">
          <h2><i class="fas fa-print"></i> Service Details</h2>
          <div class="form-grid">
            <div class="form-group full-width">
              <label for="service-type">Service Type <span class="required">*</span></label>
              <select id="service-type" name="service_type" required>
                <option value="">-- Select Service --</option>
                <option value="Business Cards">Business Cards</option>
                <option value="Flyers / Leaflets">Flyers / Leaflets</option>
                <option value="Brochures">Brochures</option>
                <option value="Posters">Posters</option>
                <option value="Banners">Banners</option>
                <option value="T-Shirts">T-Shirts</option>
                <option value="Mugs">Mugs</option>
                <option value="Stickers">Stickers</option>
                <option value="Signages">Signages</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="form-group">
              <label for="size">Size</label>
              <input type="text" id="size" name="size" placeholder="e.g., A4, 11x17, Custom">
            </div>
            <div class="form-group">
              <label for="material">Material</label>
              <select id="material" name="material">
                <option value="">-- Select Material --</option>
                <option value="Glossy Paper">Glossy Paper</option>
                <option value="Matte Paper">Matte Paper</option>
                <option value="Vinyl">Vinyl</option>
                <option value="Canvas">Canvas</option>
                <option value="Fabric">Fabric</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="form-group">
              <label for="color">Color</label>
              <input type="text" id="color" name="color" placeholder="e.g., Full Color, Black & White">
            </div>
            <div class="form-group">
              <label for="finish">Finish</label>
              <select id="finish" name="finish">
                <option value="">-- Select Finish --</option>
                <option value="Matte">Matte</option>
                <option value="Glossy">Glossy</option>
                <option value="UV Coating">UV Coating</option>
                <option value="Lamination">Lamination</option>
                <option value="None">None</option>
              </select>
            </div>
            <div class="form-group">
              <label for="quantity">Quantity</label>
              <input type="number" id="quantity" name="quantity" min="1" value="1">
            </div>
            <div class="form-group">
              <label for="deadline">Preferred Deadline</label>
              <input type="date" id="deadline" name="preferred_deadline">
            </div>
          </div>
        </div>

        <div class="custom-card">
          <h2><i class="fas fa-palette"></i> Design & Artwork</h2>
          <div class="form-grid">
            <div class="form-group full-width">
              <label>Upload Files</label>
              <div class="upload-zone" id="upload-zone">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Drag & drop files here or click to browse</p>
                <p class="upload-hint">Supports: JPG, PNG, PDF, AI, PSD (Max 10MB per file)</p>
                <input type="file" id="file-input" multiple accept="image/*,.pdf,.ai,.psd" style="display:none;">
              </div>
              <div class="file-list" id="file-list"></div>
            </div>
            <div class="form-group full-width">
              <label for="special-requests">Special Instructions</label>
              <textarea id="special-requests" name="special_requests" placeholder="Describe your requirements, preferences, or any special instructions..."></textarea>
            </div>
            <div class="form-group full-width">
              <div class="checkbox-group">
                <input type="checkbox" id="design-assistance" name="need_design_assistance" value="1">
                <label for="design-assistance">I need design assistance</label>
              </div>
            </div>
          </div>
        </div>

        <div style="text-align:center; margin-top:2rem;">
          <button type="submit" class="btn-submit" id="submit-custom-btn">
            <span class="btn-text"><i class="fas fa-paper-plane"></i> Submit Request</span>
            <span class="btn-spinner" style="display:none; width:20px; height:20px; border:2.5px solid rgba(255,255,255,0.3); border-top-color:white; border-radius:50%; animation:spin 0.7s linear infinite;"></span>
          </button>
        </div>
      </form>

      <div class="custom-card" style="margin-top:3rem;">
        <h2><i class="fas fa-history"></i> My Previous Requests</h2>
        <div id="requests-list" class="requests-list">
          <p style="text-align:center; color:#64748b; padding:2rem;">No custom printing requests yet.</p>
        </div>
    </div>
  </div>
</main>

<script>
    const uploadZone = document.getElementById('upload-zone');
    const fileInput = document.getElementById('file-input');
    const fileList = document.getElementById('file-list');
    let uploadedFiles = [];

    uploadZone.addEventListener('click', () => fileInput.click());
    uploadZone.addEventListener('dragover', (e) => { e.preventDefault(); uploadZone.classList.add('dragover'); });
    uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
    uploadZone.addEventListener('drop', (e) => {
      e.preventDefault();
      uploadZone.classList.remove('dragover');
      handleFiles(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', (e) => handleFiles(e.target.files));

    function handleFiles(files) {
      Array.from(files).forEach(file => {
        if (file.size > 10 * 1024 * 1024) {
          alert('File size must be less than 10MB');
          return;
        }
        const reader = new FileReader();
        reader.onload = (e) => {
          uploadedFiles.push({
            name: file.name,
            type: file.type.startsWith('image/') ? 'image' : 'document',
            url: e.target.result,
            size: file.size
          });
          renderFiles();
        };
        reader.readAsDataURL(file);
      });
    }

    function renderFiles() {
      fileList.innerHTML = uploadedFiles.map((file, index) => `
        <div class="file-item">
          ${file.type === 'image' ? `<img src="${file.url}" alt="${file.name}">` : `<div style="display:flex; align-items:center; justify-content:center; height:100%; font-size:2rem; color:#e91e8c;"><i class="fas fa-file-pdf"></i></div>`}
          <button type="button" class="file-remove" onclick="removeFile(${index})">&times;</button>
          <div class="file-name">${file.name}</div>
        </div>
      `).join('');
    }

    function removeFile(index) {
      uploadedFiles.splice(index, 1);
      renderFiles();
    }

    document.getElementById('custom-printing-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const form = e.target;
      const formData = new FormData(form);
      const data = {
        service_type: formData.get('service_type'),
        size: formData.get('size'),
        material: formData.get('material'),
        color: formData.get('color'),
        finish: formData.get('finish'),
        quantity: parseInt(formData.get('quantity')) || 1,
        special_requests: formData.get('special_requests'),
        need_design_assistance: formData.has('need_design_assistance') ? 1 : 0,
        preferred_deadline: formData.get('preferred_deadline'),
        reference_images: [],
        files: uploadedFiles
      };

      const btn = document.getElementById('submit-custom-btn');
      btn.disabled = true;
      btn.querySelector('.btn-text').style.display = 'none';
      btn.querySelector('.btn-spinner').style.display = 'block';

      try {
        const response = await fetch('../api/custom-printing.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify(data)
        });

        const result = await response.json();
        
        if (result.success) {
          document.getElementById('success-message').textContent = 'Request submitted successfully! We\'ll contact you soon.';
          document.getElementById('success-message').style.display = 'block';
          document.getElementById('error-message').style.display = 'none';
          form.reset();
          uploadedFiles = [];
          renderFiles();
          loadMyRequests();
        } else {
          document.getElementById('error-message').textContent = result.error || 'Failed to submit request';
          document.getElementById('error-message').style.display = 'block';
        }
      } catch (err) {
        document.getElementById('error-message').textContent = 'Network error. Please try again.';
        document.getElementById('error-message').style.display = 'block';
      }
      btn.disabled = false;
      btn.querySelector('.btn-text').style.display = 'inline-flex';
      btn.querySelector('.btn-spinner').style.display = 'none';
    });

    async function loadMyRequests() {
      const list = document.getElementById('requests-list');
      try {
        const res = await fetch('../api/custom-printing.php?action=my_requests', { credentials: 'include' });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);
        
        const requests = data.requests || [];
        if (requests.length === 0) {
          list.innerHTML = '<p style="text-align:center; color:#64748b; padding:2rem;">No custom printing requests yet.</p>';
          return;
        }
        
        function statusLabel(s) {
          const labels = { pending: 'Pending', in_review: 'In Review', approved: 'Approved', ready_for_purchase: 'Ready to Buy', rejected: 'Rejected', completed: 'Completed' };
          return labels[s] || s;
        }

        list.innerHTML = requests.map(req => {
          const hasChat = req.chat_conversation_id;
          return `
          <div class="request-card">
            <div class="request-header">
              <div>
                <div class="request-id">#${req.id} - ${req.service_type}</div>
                <div class="request-date">${new Date(req.created_at).toLocaleDateString()}</div>
              </div>
              <span class="request-status status-${req.status}">${statusLabel(req.status)}</span>
            </div>
            <div class="request-details">
              ${req.size ? `<div><strong>Size:</strong> ${req.size}</div>` : ''}
              ${req.material ? `<div><strong>Material:</strong> ${req.material}</div>` : ''}
              ${req.color ? `<div><strong>Color:</strong> ${req.color}</div>` : ''}
              ${req.finish ? `<div><strong>Finish:</strong> ${req.finish}</div>` : ''}
              <div><strong>Quantity:</strong> ${req.quantity}</div>
              ${req.file_count > 0 ? `<div><strong>Files:</strong> ${req.file_count} uploaded</div>` : ''}
              ${req.admin_notes ? `<div style="margin-top:0.5rem; padding:0.5rem; background:white; border-radius:8px;"><strong>Admin Notes:</strong> ${req.admin_notes}</div>` : ''}
            </div>
            <div class="request-actions">
              ${hasChat ? `<a href="chat.php?conversation=${req.chat_conversation_id}" class="btn btn-chat"><i class="fas fa-comments"></i> Chat</a>` : ''}
            </div>
          </div>`;
        }).join('');
      } catch (err) {
        list.innerHTML = '<p style="text-align:center; color:#dc2626; padding:2rem;">Failed to load requests.</p>';
      }
    }

    loadMyRequests();

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
  </script>
</body>
</html>

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
            size: document.getElementById('size').value,
            material: document.getElementById('material').value,
            color: document.getElementById('color').value,
            finish: document.getElementById('finish').value,
            quantity: parseInt(document.getElementById('quantity').value) || 1,
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
  </script>
</body>
</html>

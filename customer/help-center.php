<?php
session_start();
$loggedIn = !empty($_SESSION['user_id']);
$userName = $loggedIn ? trim($_SESSION['user_name'] ?? '') : '';
$userInitials = '';
if ($loggedIn && $userName !== '') {
    $parts = array_filter(preg_split('/\s+/', $userName));
    $userInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
require_once __DIR__ . '/../db-config.php';

$helpContent = [];
$result = $conn->query("SELECT section_key, title, subtitle, content, meta FROM site_content WHERE section_key = 'help_center'");
$row = $result->fetch_assoc();
if ($row) {
    $helpContent = $row;
    if ($helpContent['meta']) $helpContent['meta'] = json_decode($helpContent['meta'], true);
}
$faqs = $helpContent['meta']['faqs'] ?? [];
$conn->close();

$seoTitle = ($helpContent['title'] ?? 'Help Center') . ' | Inkzion Spectrum Ads';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?php echo htmlspecialchars($seoTitle); ?></title>
  <meta name="description" content="Frequently asked questions about Inkzion Spectrum Ads printing services." />
  <link rel="stylesheet" href="../styles.css?v=6">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<header class="site-header">
  <div class="topbar"></div>
  <div class="container header-inner">
    <a href="../index.php" class="brand">
      <img src="../assets/logo.png" alt="Inkzion Spectrum Ads logo" class="site-logo">
      <div>
        <span class="brand-title">INKZION</span>
        <span class="brand-subtitle">SPECTRUM ADS</span>
      </div>
    </a>
    <button id="nav-toggle" aria-expanded="false" aria-controls="nav-list">Menu</button>
    <nav>
      <ul id="nav-list" class="nav-list">
        <li><a href="../index.php">Home</a></li>
        <li><a href="store-product.php">Products</a></li>
      </ul>
    </nav>
    <div class="header-action-set">
      <?php if ($loggedIn): ?>
        <a href="profile.php" class="btn auth-btn" title="My Profile"><i class="fas fa-user"></i><span class="nav-icon-label">Profile</span></a>
        <a href="../logout.php" class="btn auth-btn">Logout</a>
      <?php else: ?>
        <a href="../index.php" class="btn auth-btn">Sign In</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main>
  <section class="section" style="padding-top:4rem;padding-bottom:4rem;">
    <div class="container" style="max-width:800px;">
      <div class="section-header">
        <span class="section-overline">Help Center</span>
        <h2><?php echo htmlspecialchars($helpContent['title'] ?? 'Help Center'); ?></h2>
        <p><?php echo htmlspecialchars($helpContent['subtitle'] ?? 'Frequently asked questions'); ?></p>
      </div>

      <?php if (!empty($helpContent['content'])): ?>
      <div style="margin-bottom:2rem;padding:1.25rem;background:rgba(43, 76, 82,0.05);border-radius:12px;border:1px solid rgba(43, 76, 82,0.1);">
        <p style="font-size:0.95rem;color:var(--text-secondary);"><?php echo htmlspecialchars($helpContent['content']); ?></p>
      </div>
      <?php endif; ?>

      <?php if (count($faqs) > 0): ?>
      <div style="display:flex;flex-direction:column;gap:0.75rem;">
        <?php foreach ($faqs as $i => $faq): ?>
        <details style="background:white;border:1px solid var(--border-color);border-radius:12px;overflow:hidden;transition:box-shadow 0.2s;">
          <summary style="padding:1rem 1.25rem;font-weight:600;font-size:0.95rem;color:var(--text-primary);cursor:pointer;display:flex;justify-content:space-between;align-items:center;list-style:none;">
            <span><?php echo htmlspecialchars($faq['question'] ?? ''); ?></span>
            <i class="fas fa-chevron-down" style="color:var(--text-muted);font-size:0.75rem;transition:transform 0.2s;"></i>
          </summary>
          <div style="padding:0 1.25rem 1rem;font-size:0.9rem;color:var(--text-secondary);line-height:1.7;border-top:1px solid var(--border-light);padding-top:0.75rem;">
            <?php echo nl2br(htmlspecialchars($faq['answer'] ?? '')); ?>
          </div>
        </details>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div style="text-align:center;padding:3rem 1rem;color:var(--text-muted);">
        <i class="fas fa-question-circle" style="font-size:3rem;display:block;margin-bottom:1rem;color:rgba(43, 76, 82,0.15);"></i>
        <h3>No FAQs yet</h3>
        <p>Check back soon for frequently asked questions.</p>
      </div>
      <?php endif; ?>

      <div style="text-align:center;margin-top:2.5rem;padding:1.5rem;background:rgba(43, 76, 82,0.05);border-radius:12px;">
        <h3 style="font-size:1rem;margin-bottom:0.5rem;">Still have questions?</h3>
        <p style="font-size:0.88rem;color:var(--text-muted);margin-bottom:1rem;">We're here to help! Get in touch with us.</p>
        <a href="../index.php#contact" class="btn primary">Contact Us</a>
      </div>
    </div>
  </section>
</main>

<footer class="site-footer">
  <div class="footer-top"></div>
  <div class="container footer-grid">
    <div>
      <div class="footer-brand">
        <img src="../assets/logo.png" alt="Inkzion Spectrum Ads Logo" class="footer-logo">
        <div><strong>INKZION</strong><span>SPECTRUM ADS</span></div>
      </div>
      <p>Premium printing and advertising services for businesses and individuals.</p>
    </div>
  </div>
  <div class="container footer-bottom">
    <p>&copy; <span id="year"></span> Inkzion Spectrum Ads. All rights reserved.</p>
  </div>
</footer>
<script>document.getElementById('year').textContent = new Date().getFullYear();</script>
<script>
  document.querySelectorAll('details').forEach(d => {
    d.querySelector('summary').addEventListener('click', function(e) {
      const icon = this.querySelector('i');
      if (icon) {
        setTimeout(() => {
          icon.style.transform = d.open ? 'rotate(180deg)' : 'rotate(0deg)';
        }, 10);
      }
    });
  });
</script>
  <script>navigator.sendBeacon('../api/track-visit.php?url=' + encodeURIComponent(location.pathname + location.search) + '&_=' + Date.now());</script>
</body>
</html>

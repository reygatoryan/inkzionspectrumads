<?php
require_once __DIR__ . '/includes/session-helper.php';
secureSessionStart();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$loggedIn = !empty($_SESSION['user_id']);
$userName = $loggedIn ? trim($_SESSION['user_name'] ?? '') : '';
$userInitials = '';
if ($loggedIn && $userName !== '') {
    $parts = array_filter(preg_split('/\s+/', $userName));
    $userInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
if ($loggedIn && $userInitials === '') {
    $userInitials = 'ME';
}

// Load site content for hero/about/contact
require_once 'db-config.php';
require_once __DIR__ . '/includes/csrf-helper.php';
require_once __DIR__ . '/includes/turnstile-config.php';
$csrfToken = generateCsrfToken();
$siteContent = [];
$result = $conn->query("SELECT section_key, title, subtitle, content, image_url, meta FROM site_content");
while ($row = $result->fetch_assoc()) {
    if ($row['meta']) $row['meta'] = json_decode($row['meta'], true);
    $siteContent[$row['section_key']] = $row;
}
$heroContent = $siteContent['homepage_hero'] ?? [];
$aboutContent = $siteContent['about_us'] ?? [];
$contactContent = $siteContent['contact_info'] ?? [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <?php
  require_once 'includes/seo-helper.php';
  $seoTitle = 'Inkzion Spectrum Ads | Printing Services';
  $seoDescription = 'Inkzion Spectrum Ads delivers premium printing services, creative advertising, and full-spectrum brand experiences.';
  $seoKeywords = 'printing, advertising, business cards, marketing materials, signage, apparel, custom merchandise, promotional items';
  outputSEOTags($seoTitle, $seoDescription, $seoKeywords);
  ?>
  <link rel="stylesheet" href="styles.css?v=13">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
  <script>const CSRF_TOKEN = '<?php echo $csrfToken; ?>';</script>
  <style>
    .g-signin-wrapper { display: flex; align-items: center; }
    .g-signin-wrapper > div > iframe { max-width: 210px !important; }
    .g-signin-wrapper .g_id_signin { display: flex; align-items: center; }
    .profile-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10000; display: none; align-items: center; justify-content: center; }
    .profile-modal { background: white; border-radius: 16px; padding: 2rem; max-width: 440px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
    .profile-modal h2 { margin: 0 0 0.25rem; font-size: 1.25rem; color: #0f172a; }
    .profile-modal p.sub { margin: 0 0 1.25rem; font-size: 0.85rem; color: #64748b; }
    .profile-modal .field { margin-bottom: 1rem; }
    .profile-modal .field label { display: block; font-size: 0.78rem; font-weight: 600; color: #475569; margin-bottom: 0.3rem; }
    .profile-modal .field input, .profile-modal .field textarea { width: 100%; padding: 0.65rem 0.75rem; border: 1px solid #d1d5db; border-radius: 10px; font-size: 0.9rem; outline: none; box-sizing: border-box; font-family: inherit; transition: border-color 0.2s; }
    .profile-modal .field input:focus, .profile-modal .field textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
    .profile-modal .field textarea { min-height: 70px; resize: vertical; }
    .profile-modal .cf-turnstile { margin-bottom: 1rem; }
    .profile-modal .modal-actions { display: flex; gap: 0.75rem; }
    .profile-modal .modal-actions button { flex: 1; padding: 0.7rem; border-radius: 10px; font-size: 0.88rem; font-weight: 600; cursor: pointer; border: none; transition: opacity 0.2s; }
    .profile-modal .btn-save { background: #2563eb; color: white; width: 100%; }
    .profile-modal .btn-save:hover { opacity: 0.9; }
    .profile-modal .btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
    .profile-modal .error-msg { font-size: 0.8rem; color: #dc2626; margin-bottom: 0.75rem; display: none; }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="topbar"></div>
    <div class="container header-inner">
      <a href="index.php" class="brand">
        <img src="assets/logo.png" alt="Inkzion Spectrum Ads logo" class="site-logo">
        <div>
          <span class="brand-title">INKZION</span>
          <span class="brand-subtitle">SPECTRUM ADS</span>
        </div>
      </a>
      <button id="nav-toggle" aria-expanded="false" aria-controls="nav-list" aria-label="Toggle navigation menu"><i class="fas fa-bars"></i></button>
      <nav>
        <ul id="nav-list" class="nav-list">
          <li><a href="customer/store-product.php">Products</a></li>
          <li><a href="#services">Services</a></li>
          <li><a href="#about">About</a></li>
          <li><a href="#contact">Contact</a></li>

        </ul>
      </nav>
      <div class="header-action-set">
      <?php if ($loggedIn): ?>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        <a href="admin/dashboard.php" class="btn auth-btn">
          <i class="fas fa-shield-alt"></i> Admin
        </a>
        <a href="logout.php" class="btn auth-btn">Logout</a>
        <?php else: ?>
        <a href="customer/profile.php" class="btn auth-btn" title="My Profile">
          <i class="fas fa-user"></i>
          <span class="nav-icon-label">Profile</span>
        </a>
        <a href="logout.php" class="btn auth-btn">Logout</a>
        <?php endif; ?>
      <?php else: ?>
        <div class="g-signin-wrapper">
          <div id="g_id_onload"
               data-client_id="710352328695-8n7ggg4rg6c89rga59kn9fb5ueffb6kl.apps.googleusercontent.com"
               data-callback="handleGoogleCredential"
               data-auto_prompt="false">
          </div>
          <div class="g_id_signin"
               data-type="standard"
               data-shape="pill"
               data-theme="outline"
               data-text="sign_in_with"
               data-size="medium"
               data-logo_alignment="left">
          </div>
        </div>
      <?php endif; ?>
      </div>
    </div>
  </header>

  <?php if (!empty($flash)): ?>
    <div class="flash-message <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <main>
    <section class="hero">
      <div class="hero-bg">
        <div class="orb orb-pink"></div>
        <div class="orb orb-cyan"></div>
        <div class="orb orb-green"></div>
        <div class="orb orb-gold"></div>
        <div class="grid-pattern"></div>
      </div>
      <div class="container hero-inner">
        <div class="hero-copy">
          <h1><?php echo htmlspecialchars($heroContent['title'] ?? 'INKZION SPECTRUM ADS'); ?></h1>
          <p><?php echo htmlspecialchars($heroContent['subtitle'] ?? 'Your trusted partner for high-quality and affordable printing solutions.'); ?></p>
          <?php if (!empty($heroContent['content'])): ?>
          <p style="font-size:0.95rem;margin-top:0.5rem;"><?php echo htmlspecialchars($heroContent['content']); ?></p>
          <?php endif; ?>
          <div class="hero-actions">
            <a href="customer/store-product.php" class="btn ghost">Shop Now</a>
          </div>
        </div>

      </div>
    </section>
     <section id="customized-apparel" class="section customized-apparel">
      
      <div class="services-scroll-wrapper">
        <div class="services-horizontal" id="services-list">
          <div class="service-item">SUBLIMATION ROUND NECK T SHIRT</div>
          <div class="service-item">SUBLIMATION POLO SHIRT ZIPPER TYPE/BUTTON TYPE</div>
          <div class="service-item">SUBLIMATION VARSITY JACKET</div>
          <div class="service-item">SUBLIMATION CHINESE COLLAR</div>
          <div class="service-item">SUBLIMATION BASKET BALL JERSEY</div>
          <div class="service-item">TARPAULINS</div>
          <div class="service-item">PANAFLEX</div>
          <div class="service-item">VINYL STICKERS</div>
          <div class="service-item">CALENDARS</div>
          <div class="service-item">CALLING CARDS</div>
          <div class="service-item">LANYARDS</div>
          <div class="service-item">PVC IDs</div>
          <div class="service-item">GIVEAWAYS</div>
          <div class="service-item">PHOTO PRINTING</div>
          <div class="service-item">STAND BANNERS</div>
          <div class="service-item">SIGNAGE</div>
          <div class="service-item">CERTIFICATE PRINTING</div>
          <div class="service-item">MUG</div>
          <div class="service-item">CAPS</div>
          <div class="service-item">DTF</div>
          <div class="service-item">FLYERS</div>
          <div class="service-item">TUMBLER</div>
          <div class="service-item">MOUSE PAD</div>
          <div class="service-item">FULL SUBLIMATION ROUND NECK T SHIRT</div>
          <div class="service-item">FULL SUBLIMATION POLO SHIRT ZIPPER TYPE/BUTTON TYPE</div>
          <div class="service-item">FULL SUBLIMATION VARSITY JACKET</div>
          <div class="service-item">FULL SUBLIMATION CHINESE COLLAR</div>
          <div class="service-item">FULL SUBLIMATION BASKET BALL JERSEY</div>
        </div>
      </div>
    </section>

    <section id="special-offers" class="section special-offers">
      <div class="container">
        <div class="section-header">
          <span class="section-overline">SPECIAL OFFERS</span>
          <h2>Promos & Packages</h2>
          <p>Save more when you print more! Quality prints at prices that fit your budget. Free consultation on large orders, flexible pricing options, and fast reliable service.</p>
        </div>
        <div class="offer-top-row">
          <div class="offer-highlights">
            <span class="offer-chip"><i class="fas fa-bolt"></i> Fast Turnaround</span>
            <span class="offer-chip"><i class="fas fa-gift"></i> Free Design Review</span>
            <span class="offer-chip"><i class="fas fa-shield-alt"></i> Quality Guarantee</span>
          </div>
        </div>
        <div class="offer-grid">
          <article class="offer-card">
            <div class="offer-icon"><i class="fas fa-box-open"></i></div>
            <h3>Bulk Printing Discounts</h3>
            <p>Get lower rates for large volume orders&#8212;perfect for businesses, schools, and events.</p>
          </article>
          <article class="offer-card">
            <div class="offer-icon"><i class="fas fa-graduation-cap"></i></div>
            <h3>Student-Friendly Prices</h3>
            <p>Special pricing packages designed for student clubs, school projects, and campus promotions.</p>
          </article>
          <article class="offer-card">
            <div class="offer-icon"><i class="fas fa-boxes"></i></div>
            <h3>Package Deals Available</h3>
            <p>Combine business cards, flyers, banners, and promotional items into one cost-saving bundle.</p>
          </article>
          <article class="offer-card">
            <div class="offer-icon"><i class="fas fa-briefcase"></i></div>
            <h3>Business Client Rates</h3>
            <p>Exclusive special rates for corporate orders, reorders, and recurring marketing materials.</p>
          </article>
          <article class="offer-card">
            <div class="offer-icon"><i class="fas fa-calendar-check"></i></div>
            <h3>Seasonal Promos</h3>
            <p>Limited-time offers and event-based discounts to help you stay on budget all year round.</p>
          </article>
        </div>
      </div>
    </section>

    <section id="services" class="section services">
      <div class="container">
        <div class="section-header">
          <span class="section-overline">Our Services</span>
          <h2>Everything you need to <span class="gradient-text">print</span></h2>
          <p>From concept to completion, we deliver premium printing solutions that make your brand unforgettable.</p>
        </div>
        <div class="cards-grid">
          <article class="service-card">
            <div class="service-icon service-icon-pink"><i class="fas fa-id-card"></i></div>
            <h3>Business Cards</h3>
            <p>Calling cards, premium cardstock with matte, gloss, or embossed finishes.</p>
          </article>
          <article class="service-card">
            <div class="service-icon service-icon-cyan"><i class="fas fa-file-lines"></i></div>
            <h3>Brochures & Flyers</h3>
            <p>Tri-fold brochures, postcards, and bold marketing materials designed to convert.</p>
          </article>
          <article class="service-card">
            <div class="service-icon service-icon-green"><i class="fas fa-image"></i></div>
            <h3>Large Format Printing</h3>
            <p>Banners, signage, tarpaulins, panaflex, and posters for indoor & outdoor use.</p>
          </article>
          <article class="service-card">
            <div class="service-icon service-icon-gold"><i class="fas fa-shirt"></i></div>
            <h3>Apparel & Sublimation</h3>
            <p>T-shirts, polo shirts, varsity jackets, basketball jerseys, and custom apparel printing.</p>
          </article>
          <article class="service-card">
            <div class="service-icon service-icon-purple"><i class="fas fa-mug-hot"></i></div>
            <h3>Custom Merchandise</h3>
            <p>Mugs, tumblers, mouse pads, caps, lanyards, and promotional items.</p>
          </article>
          <article class="service-card">
            <div class="service-icon service-icon-blue"><i class="fas fa-certificate"></i></div>
            <h3>Certificates & Documents</h3>
            <p>Certificates, diplomas, award plaques, document binding, and professional printing.</p>
          </article>
          <article class="service-card">
            <div class="service-icon service-icon-pink"><i class="fas fa-palette"></i></div>
            <h3>Design Services</h3>
            <p>Creative design that turns concepts into polished, print-ready visuals.</p>
          </article>
          <article class="service-card">
            <div class="service-icon service-icon-cyan"><i class="fas fa-print"></i></div>
            <h3>Digital Printing</h3>
            <p>Fast, accurate digital printing for short runs, urgent projects, and special events.</p>
          </article>
        </div>
      </div>
    </section>



    <section id="about" class="section about">
      <div class="container">
        <div class="section-header">
          <span class="section-overline">About Us</span>
          <h2><?php echo htmlspecialchars($aboutContent['title'] ?? 'Your Complete Printing <span class="gradient-text">Solution</span>'); ?></h2>
          <p><?php echo htmlspecialchars($aboutContent['subtitle'] ?? 'We provide high-quality printing solutions tailored to your needs.'); ?></p>
        </div>
        <div class="about-grid">
          <div class="about-images">
            <div class="image-large">
              <img src="<?php echo !empty($aboutContent['image_url']) ? htmlspecialchars($aboutContent['image_url']) : 'assets/aboutpic.jpg'; ?>" alt="Inkzion Spectrum Ads printing showcase" loading="lazy" />
            </div>
            <div class="about-image-row">
              <img src="assets/aboutpic1.png" alt="Custom apparel printing" loading="lazy" />
              <img src="assets/aboutpic2.png" alt="Marketing materials" loading="lazy" />
            </div>
          </div>
          <div class="about-copy">
            <p class="about-intro">We deliver premium printing and advertising solutions tailored to your needs. Here's what sets us apart:</p>
            <div class="about-features">
              <div class="about-feature">
                <div class="about-feature-icon"><i class="fas fa-check"></i></div>
                <div class="about-feature-text">
                  <strong>Meticulous Quality</strong>
                  <p>Every print job, big or small, meets the highest standards of craftsmanship.</p>
                </div>
              </div>
              <div class="about-feature">
                <div class="about-feature-icon"><i class="fas fa-check"></i></div>
                <div class="about-feature-text">
                  <strong>End-to-End Support</strong>
                  <p>From consultation and design to production and delivery — we handle it all.</p>
                </div>
              </div>
              <div class="about-feature">
                <div class="about-feature-icon"><i class="fas fa-check"></i></div>
                <div class="about-feature-text">
                  <strong>Modern Equipment</strong>
                  <p>Latest printing technology for vibrant colors, sharp details, and lasting durability.</p>
                </div>
              </div>
              <div class="about-feature">
                <div class="about-feature-icon"><i class="fas fa-check"></i></div>
                <div class="about-feature-text">
                  <strong>Fast Turnaround</strong>
                  <p>Quick delivery times without compromising on quality.</p>
                </div>
              </div>
              <div class="about-feature">
                <div class="about-feature-icon"><i class="fas fa-check"></i></div>
                <div class="about-feature-text">
                  <strong>Transparent Pricing</strong>
                  <p>No hidden fees — just honest quotes and cost-effective solutions.</p>
                </div>
              </div>
              <div class="about-feature">
                <div class="about-feature-icon"><i class="fas fa-check"></i></div>
                <div class="about-feature-text">
                  <strong>Free Design Review</strong>
                  <p>Professional consultation to refine your vision before production begins.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="contact" class="section contact-section">
      <div class="container contact-grid">
        <div class="contact-info">
          <span class="section-overline">Contact Us</span>
          <p><?php echo htmlspecialchars($contactContent['subtitle'] ?? 'Ready to start your next printing project? Get in touch for a free consultation and quote.'); ?></p>
          <?php if (!empty($contactContent['content'])): ?>
          <p style="margin-top:0.75rem;"><?php echo htmlspecialchars($contactContent['content']); ?></p>
          <?php endif; ?>
          <div class="contact-cards">
            <?php if (!empty($contactContent['meta']['address'])): ?>
            <div class="info-card"><strong>Visit Us</strong><p><?php echo htmlspecialchars($contactContent['meta']['address']); ?></p></div>
            <?php else: ?>
            <div class="info-card"><strong>Visit Us</strong><p>Jimsville Executive BLDG, Tayud, Liloan, Cebu</p></div>
            <?php endif; ?>
            <?php if (!empty($contactContent['meta']['phone'])): ?>
            <div class="info-card"><strong>Call Us</strong><p><?php echo htmlspecialchars($contactContent['meta']['phone']); ?></p></div>
            <?php else: ?>
            <div class="info-card"><strong>Call Us</strong><p>+639754263237</p></div>
            <?php endif; ?>
            <?php if (!empty($contactContent['meta']['email'])): ?>
            <div class="info-card"><strong>Email Us</strong><p><?php echo htmlspecialchars($contactContent['meta']['email']); ?></p></div>
            <?php else: ?>
            <div class="info-card"><strong>Email Us</strong><p>inkzionspectrum.com</p></div>
            <?php endif; ?>
          </div>
          <?php if (!empty($contactContent['meta']['map_url'])): ?>
          <div style="margin-top:1rem;border-radius:12px;overflow:hidden;border:1px solid var(--border-color);">
            <iframe src="<?php echo htmlspecialchars($contactContent['meta']['map_url']); ?>" width="100%" height="250" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
          </div>
          <?php endif; ?>
          <div class="business-hours">
            <strong>Business Hours</strong>
            <p>Monday - Saturday: 8:00 AM - 5:00 PM</p>
            <p>Sunday: Closed</p>
          </div>
        </div>
        <div class="contact-social-card">
          <h3>Connect With Us</h3>
          <p class="contact-social-desc">Follow us on social media or visit our Shopee store to see our latest products and promotions.</p>
          <div class="contact-social-links">
            <a href="https://www.facebook.com/profile.php?id=61581351683926" target="_blank" rel="noopener noreferrer" class="contact-social-item fb-social">
              <div class="contact-social-icon"><i class="fab fa-facebook-f"></i></div>
              <div class="contact-social-info">
                <span class="contact-social-label">Facebook</span>
                <span class="contact-social-name">Inkzion Spectrum Ads</span>
              </div>
              <i class="fas fa-external-link-alt contact-social-arrow"></i>
            </a>
            <a href="https://www.facebook.com/profile.php?id=61588137340105" target="_blank" rel="noopener noreferrer" class="contact-social-item fb-social">
              <div class="contact-social-icon"><i class="fab fa-facebook-f"></i></div>
              <div class="contact-social-info">
                <span class="contact-social-label">Facebook</span>
                <span class="contact-social-name">Inkzion Flyers Lab</span>
              </div>
              <i class="fas fa-external-link-alt contact-social-arrow"></i>
            </a>
            <a href="https://www.facebook.com/profile.php?id=61587826864930" target="_blank" rel="noopener noreferrer" class="contact-social-item fb-social">
              <div class="contact-social-icon"><i class="fab fa-facebook-f"></i></div>
              <div class="contact-social-info">
                <span class="contact-social-label">Facebook</span>
                <span class="contact-social-name">Inkzion Uniform & Sports Apparel Hub</span>
              </div>
              <i class="fas fa-external-link-alt contact-social-arrow"></i>
            </a>
            <a href="https://shopee.ph/inkzionspectrumads?entryPoint=ShopBySearch&searchKeyword=inkzionspectrumads" target="_blank" rel="noopener noreferrer" class="contact-social-item shp-social">
              <div class="contact-social-icon"><i class="fas fa-bag-shopping"></i></div>
              <div class="contact-social-info">
                <span class="contact-social-label">Shopee</span>
                <span class="contact-social-name">Inkzion Spectrum Ads</span>
              </div>
              <i class="fas fa-external-link-alt contact-social-arrow"></i>
            </a>
          </div>
          <div class="connect-us-image">
            <img src="assets/CONNECTUS.png" alt="Connect with Inkzion Spectrum Ads" />
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="footer-top"></div>
    <div class="container footer-grid">
      <div>
        <div class="footer-brand">
          <img src="assets/logo.png" alt="Inkzion Spectrum Ads Logo" class="footer-logo">
          <div>
            <strong>INKZION</strong>
            <span>SPECTRUM ADS</span>
          </div>
        </div>
        <p>Premium printing and advertising services for businesses and individuals.</p>
      </div>
      <div>
        <h4>Company</h4>
        <ul>
          <li><a href="#about">About Us</a></li>
          <li><a href="#contact">Contact</a></li>
        </ul>
      </div>
      <div>
        <h4>Support</h4>
        <ul>
          <li><a href="#faq">FAQ</a></li>
          <li><a href="#contact">Shipping Info</a></li>
          <li><a href="#contact">Help Center</a></li>
        </ul>
      </div>
    </div>
    <div class="container footer-bottom">
      <p>&copy; <span id="year"></span> Inkzion Spectrum Ads. All rights reserved.</p>
      <div class="footer-links">
        <a href="#contact">Privacy</a>
        <a href="#contact">Terms</a>
      </div>
    </div>
  </footer>

  <div id="turnstile-container" class="cf-turnstile" data-sitekey="<?php echo TURNSTILE_SITE_KEY; ?>" data-size="invisible" data-callback="onTurnstileCallback"></div>

  <div class="profile-modal-overlay" id="profileModal">
    <div class="profile-modal">
      <h2>Complete Your Profile</h2>
      <p class="sub">Please provide your details to continue.</p>
      <div class="error-msg" id="profileError"></div>
      <div class="field">
        <label>Full Name</label>
        <input type="text" id="profName" placeholder="Your full name">
      </div>
      <div class="field">
        <label>Contact Number</label>
        <input type="text" id="profContact" placeholder="e.g. 09171234567">
      </div>
      <div class="field">
        <label>Delivery Address</label>
        <textarea id="profAddress" placeholder="Street, Barangay, City, Province"></textarea>
      </div>
      <div id="turnstileWidget"></div>
      <div class="modal-actions">
        <button class="btn-save" id="saveProfileBtn" onclick="saveProfile()">Save &amp; Continue</button>
      </div>
    </div>
  </div>

  <script src="script.js?v=4"></script>
  <div id="gToast" style="position:fixed;bottom:2rem;left:50%;transform:translateX(-50%);padding:0.85rem 1.8rem;border-radius:12px;font-size:0.9rem;font-weight:500;z-index:9999;color:white;display:none;box-shadow:0 8px 32px rgba(0,0,0,0.3);"></div>
  <script>
    function showGToast(msg, type) {
      var t = document.getElementById('gToast');
      t.textContent = msg;
      t.style.background = type === 'error' ? 'rgba(239,68,68,0.9)' : 'rgba(5,150,105,0.9)';
      t.style.display = 'block';
      setTimeout(function(){ t.style.display = 'none'; }, 4000);
    }
    let pendingRedirect = '';
    let pendingUserId = 0;
    let pendingGoogleCredential = '';

    function handleGoogleCredential(response) {
      pendingGoogleCredential = response.credential;
      if (typeof turnstile !== 'undefined') {
        turnstile.execute('#turnstile-container');
      } else {
        setTimeout(function() {
          if (typeof turnstile !== 'undefined') {
            turnstile.execute('#turnstile-container');
          } else {
            showGToast('Security check is still loading. Please try signing in again.', 'error');
          }
        }, 500);
      }
    }

    function onTurnstileCallback(token) {
      if (!pendingGoogleCredential) return;
      proceedWithLogin(pendingGoogleCredential, token);
    }

    async function proceedWithLogin(credential, turnstileToken) {
      try {
        const res = await fetch('api/google-auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            credential: credential,
            turnstile_token: turnstileToken,
            csrf_token: CSRF_TOKEN
          })
        });
        const data = await res.json();
        if (data.ok) {
          pendingRedirect = data.redirect || '';
          if (data.needs_profile) {
            pendingUserId = data.user ? data.user.id : 0;
            document.getElementById('profName').value = data.user && data.user.name ? data.user.name : '';
            document.getElementById('profContact').value = '';
            document.getElementById('profAddress').value = '';
            document.getElementById('profileError').style.display = 'none';
            document.getElementById('profileModal').style.display = 'flex';
            setTimeout(function() {
              if (typeof turnstile !== 'undefined') {
                var tw = document.getElementById('turnstileWidget');
                if (tw && !tw.childNodes.length) {
                  turnstile.render(tw, { sitekey: TURNSTILE_SITE_KEY });
                }
              }
            }, 100);
          } else {
            window.location.href = pendingRedirect;
          }
        } else {
          console.error('Google auth error:', data);
          showGToast(data.error || 'Sign-in failed. Please try again.', 'error');
        }
      } catch (e) {
        console.error('Google auth exception:', e);
        showGToast('Sign-in failed. Please try again.', 'error');
      }
    }
    async function saveProfile() {
      var name = document.getElementById('profName').value.trim();
      var contact = document.getElementById('profContact').value.trim();
      var address = document.getElementById('profAddress').value.trim();
      var errEl = document.getElementById('profileError');
      if (!name || !contact || !address) {
        errEl.textContent = 'All fields are required.';
        errEl.style.display = 'block';
        return;
      }
      var token = '';
      if (typeof turnstile !== 'undefined') {
        try { token = turnstile.getResponse(document.getElementById('turnstileWidget')); } catch(e) {}
      }
      if (!token) {
        errEl.textContent = 'Please complete the security check.';
        errEl.style.display = 'block';
        return;
      }
      errEl.style.display = 'none';
      var btn = document.getElementById('saveProfileBtn');
      btn.disabled = true;
      btn.textContent = 'Saving...';
      try {
        var res = await fetch('api/update-profile.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ name: name, contact_number: contact, address: address, turnstile_token: token, csrf_token: CSRF_TOKEN })
        });
        var result = await res.json();
        if (result.success) {
          document.getElementById('profileModal').style.display = 'none';
          window.location.href = pendingRedirect || 'index.php';
        } else {
          errEl.textContent = result.error || 'Failed to save. Please try again.';
          errEl.style.display = 'block';
          if (typeof turnstile !== 'undefined') turnstile.reset();
        }
      } catch (e) {
        errEl.textContent = 'Network error. Please try again.';
        errEl.style.display = 'block';
      }
      btn.disabled = false;
      btn.textContent = 'Save & Continue';
    }
    document.getElementById('nav-toggle').addEventListener('click', function() {
      var nav = document.getElementById('nav-list');
      nav.classList.toggle('show');
      var expanded = this.getAttribute('aria-expanded') === 'true' ? 'false' : 'true';
      this.setAttribute('aria-expanded', expanded);
    });
  </script>
  <script>navigator.sendBeacon('../api/track-visit.php?url=' + encodeURIComponent(location.pathname + location.search) + '&_=' + Date.now());</script>
</body>
</html>
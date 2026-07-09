<?php
session_start();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$token = $_GET['token'] ?? '';
$token = trim($token);

$validToken = false;
$errorMessage = '';
$securityQuestions = [];
$userId = 0;

if ($token !== '') {
    require_once '../db-config.php';
    $stmt = $conn->prepare("SELECT prt.id, prt.user_id, prt.expires_at, prt.used, u.email, u.security_question_1, u.security_question_2 FROM password_reset_tokens prt JOIN users u ON prt.user_id = u.id WHERE prt.token = ? LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if ($row['used']) {
            $errorMessage = 'This password reset link has already been used.';
        } elseif (strtotime($row['expires_at']) < time()) {
            $errorMessage = 'This password reset link has expired.';
        } else {
            $validToken = true;
            $userId = $row['user_id'];
            $securityQuestions = [
                'q1' => $row['security_question_1'],
                'q2' => $row['security_question_2']
            ];
        }
    } else {
        $errorMessage = 'Invalid password reset token.';
    }
    $stmt->close();
    $conn->close();
} else {
    $errorMessage = 'Missing password reset token.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Reset Password | Inkzion Spectrum Ads</title>
  <link rel="stylesheet" href="../styles.css?v=2">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .reset-password-page {
      min-height: calc(100vh - 92px);
      padding: 80px 0;
      background: rgba(255,255,255,0.95);
    }
    .reset-password-card {
      max-width: 520px;
      margin: 0 auto;
      background: white;
      border-radius: 28px;
      padding: 2rem;
      box-shadow: 0 30px 90px rgba(15, 23, 42, 0.12);
    }
    .reset-password-card h1 {
      margin-bottom: 0.85rem;
      font-size: 2rem;
      color: #0f172a;
      line-height: 1.1;
      text-align: center;
    }
    .reset-password-card p {
      color: #475569;
      margin-bottom: 1.5rem;
      font-size: 0.98rem;
      text-align: center;
    }
    .reset-password-card .header-auth-panel {
      margin-top: 1rem;
      font-size: 0.92rem;
      color: #64748b;
      text-align: center;
    }
    .reset-password-card .header-auth-panel a {
      color: #0f72dd;
      text-decoration: none;
      font-weight: 700;
    }
    .reset-password-card .header-auth-panel a:hover {
      text-decoration: underline;
    }
    .flash-message {
      margin-bottom: 1rem;
    }
    .reset-password-card .header-auth-form { display: grid; gap: 0.85rem; }
    .reset-password-card .header-auth-form label { font-size: 0.86rem; font-weight: 600; color: #334155; }
    .reset-password-card .header-auth-form input { width: 100%; padding: 0.85rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; outline: none; box-sizing: border-box; }
    .reset-password-card .header-auth-form input:focus { border-color: #0f72dd; box-shadow: 0 0 0 3px rgba(15,114,221,0.12); }
    .reset-password-card .header-auth-form-submit {
      margin-top: 0.4rem;
      width: 100%;
      background: linear-gradient(90deg, #0f72dd, #145dbf);
      color: white;
      border: none;
      border-radius: 999px;
      padding: 0.95rem 1rem;
      cursor: pointer;
      font-weight: 700;
      font-size: 0.95rem;
    }
    .reset-password-card .header-auth-form-submit:hover {
      transform: translateY(-1px);
    }
  </style>
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
          <li><a href="../index.php#services">Services</a></li>
          <li><a href="store-product.php">Products</a></li>
          <li><a href="../index.php#about">About</a></li>
          <li><a href="../index.php#contact">Contact</a></li>
        </ul>
      </nav>
      <a href="../index.php" class="btn auth-btn">Login</a>
    </div>
  </header>

  <main class="reset-password-page">
    <div class="container reset-password-card">
      <h1>Reset your password</h1>
      <p>Enter a new password to continue. Your password must be at least 8 characters long and include one uppercase letter, one number, and one special symbol.</p>
      <?php if (!empty($flash)): ?>
      <div class="flash-message <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif; ?>
      <?php if (!$validToken): ?>
        <div class="flash-message error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <p class="header-auth-panel">Please <a href="forgot-password.php">request a new password reset</a> or return to <a href="../index.php">login</a>.</p>
      <?php else: ?>
      <form action="../api/reset-password.php" method="post" class="header-auth-form">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>" />
        
        <label for="answer1"><?= htmlspecialchars($securityQuestions['q1'] ?? 'Security Question 1', ENT_QUOTES, 'UTF-8') ?></label>
        <input id="answer1" name="answer1" type="text" placeholder="Your answer" required />
        
        <label for="answer2"><?= htmlspecialchars($securityQuestions['q2'] ?? 'Security Question 2', ENT_QUOTES, 'UTF-8') ?></label>
        <input id="answer2" name="answer2" type="text" placeholder="Your answer" required />
        
        <label for="new-password">New Password</label>
        <input id="new-password" name="password" type="password" placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢" required />
        
        <label for="confirm-password">Confirm New Password</label>
        <input id="confirm-password" name="confirm_password" type="password" placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢" required />
        
        <button type="submit" class="btn header-auth-form-submit">Update Password</button>
        <p class="header-auth-panel">Remembered your password? <a href="../index.php">Back to login</a></p>
      </form>
      <?php endif; ?>
    </div>
  </main>

  <script>
    const year = document.getElementById('year');
    if (year) year.textContent = new Date().getFullYear();

    const navToggle = document.getElementById('nav-toggle');
    const navList = document.getElementById('nav-list');
    navToggle && navToggle.addEventListener('click', () => {
      const isOpen = navList.classList.toggle('show');
      navToggle.setAttribute('aria-expanded', String(isOpen));
    });
  </script>
</body>
</html>

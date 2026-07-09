<?php
session_start();
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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Forgot Password | Inkzion Spectrum Ads</title>
  <link rel="stylesheet" href="../styles.css?v=2">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .forgot-password-page {
      min-height: calc(100vh - 92px);
      padding: 80px 0;
      background: rgba(255,255,255,0.95);
    }
    .forgot-password-card {
      max-width: 520px;
      margin: 0 auto;
      background: white;
      border-radius: 28px;
      padding: 2rem;
      box-shadow: 0 30px 90px rgba(15, 23, 42, 0.12);
    }
    .forgot-password-card h1 {
      margin-bottom: 0.85rem;
      font-size: 2rem;
      color: #0f172a;
      line-height: 1.1;
      text-align: center;
    }
    .forgot-password-card p {
      color: #475569;
      margin-bottom: 1.5rem;
      font-size: 0.98rem;
      text-align: center;
    }
    .forgot-password-card .header-auth-panel {
      margin-top: 1rem;
      font-size: 0.92rem;
      color: #64748b;
      text-align: center;
    }
    .forgot-password-card .header-auth-panel a {
      color: #0f72dd;
      text-decoration: none;
      font-weight: 700;
    }
    .forgot-password-card .header-auth-panel a:hover {
      text-decoration: underline;
    }
    .flash-message {
      margin-bottom: 1rem;
    }

    .forgot-password-card .header-auth-form { display: grid; gap: 0.85rem; }
    .forgot-password-card .header-auth-form input,
    .forgot-password-card .header-auth-form select,
    .forgot-password-card .header-auth-form textarea {
      width: 100%;
      padding: 0.85rem 1rem;
      border: 1.5px solid #e2e8f0;
      border-radius: 12px;
      font-size: 0.95rem;
      outline: none;
      box-sizing: border-box;
      color: #000000;
    }
    .forgot-password-card .header-auth-form input:focus {
      border-color: #0f72dd;
      box-shadow: 0 0 0 3px rgba(15,114,221,0.12);
    }
    .forgot-password-card .header-auth-form-submit {
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
    .forgot-password-card .header-auth-form-submit:hover {
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

      <?php if ($loggedIn): ?>
      <a href="profile.php" class="profile-icon" title="Signed in as <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($userInitials, ENT_QUOTES, 'UTF-8') ?>
      </a>
      <a href="../logout.php" class="btn auth-btn">Logout</a>
      <?php else: ?>
      <a href="../index.php" class="btn auth-btn">Back to Homepage</a>
      <?php endif; ?>
    </div>
  </header>

  <main class="forgot-password-page">
    <div class="container forgot-password-card">
      <h1>Forgot your password?</h1>
      <p>Verify your identity by answering your security questions, then reset your password.</p>
      <?php if (!empty($flash)): ?>
      <div class="flash-message <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php endif; ?>

      <!-- Step 1: Enter Email -->
      <form id="forgot-step1" action="../api/forgot-password.php" method="post" class="header-auth-form">
        <label for="reset-email">Email address</label>
        <input id="reset-email" name="email" type="email" placeholder="you@example.com" required />
        <button type="submit" class="btn header-auth-form-submit">Continue</button>
        <p class="header-auth-panel">Remembered your password? <a href="../index.php">Back to login</a></p>
      </form>

      <!-- Step 2: Security Questions (hidden by default) -->
      <form id="forgot-step2" class="header-auth-form" style="display:none;">
        <input type="hidden" id="reset-user-id" name="user_id" />
        <div class="form-group">
          <label>Security Question 1</label>
          <p id="sq1-text" style="font-weight:600; color:#1e293b; margin:0 0 10px;"></p>
          <input id="sa1" name="answer1" type="text" placeholder="Your answer" required />
        </div>
        <div class="form-group">
          <label>Security Question 2</label>
          <p id="sq2-text" style="font-weight:600; color:#1e293b; margin:0 0 10px;"></p>
          <input id="sa2" name="answer2" type="text" placeholder="Your answer" required />
        </div>
        <button type="button" id="verify-security-btn" class="btn header-auth-form-submit">Verify Answers</button>
        <p class="header-auth-panel">Remembered your password? <a href="../index.php">Back to login</a></p>
      </form>

      <!-- Step 3: Reset Password (hidden by default) -->
      <form id="forgot-step3" class="header-auth-form" style="display:none;">
        <input type="hidden" id="reset-user-id-2" name="user_id" />
        <div class="form-group">
          <label for="new-password">New Password</label>
          <div class="password-input-wrapper">
            <input id="new-password" name="new_password" type="password" placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢" required />
            <button type="button" class="password-toggle-btn" tabindex="-1" aria-label="Show password"><i class="fas fa-eye"></i></button>
          </div>
        </div>
        <div class="form-group">
          <label for="confirm-new-password">Confirm New Password</label>
          <div class="password-input-wrapper">
            <input id="confirm-new-password" name="confirm_new_password" type="password" placeholder="â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢" required />
            <button type="button" class="password-toggle-btn" tabindex="-1" aria-label="Show password"><i class="fas fa-eye"></i></button>
          </div>
        </div>
        <button type="button" id="reset-password-btn" class="btn header-auth-form-submit">Reset Password</button>
        <p class="header-auth-panel">Remembered your password? <a href="../index.php">Back to login</a></p>
      </form>
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

    // Forgot Password Multi-step Flow
    const step1Form = document.getElementById('forgot-step1');
    const step2Form = document.getElementById('forgot-step2');
    const step3Form = document.getElementById('forgot-step3');
    const verifyBtn = document.getElementById('verify-security-btn');
    const resetBtn = document.getElementById('reset-password-btn');

    let currentUserId = 0;
    let resetToken = '';

    // Password toggle buttons
    document.querySelectorAll('.password-toggle-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const input = this.parentElement.querySelector('input');
        if (input.type === 'password') {
          input.type = 'text';
          this.innerHTML = '<i class="fas fa-eye-slash"></i>';
        } else {
          input.type = 'password';
          this.innerHTML = '<i class="fas fa-eye"></i>';
        }
      });
    });

    // Step 1: Submit email
    step1Form.addEventListener('submit', async function(e) {
      e.preventDefault();
      const email = document.getElementById('reset-email').value.trim();
      if (!email) return;

      try {
        const res = await fetch('../api/forgot-password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ step: 1, email: email })
        });
        const data = await res.json();

        if (data.success) {
          currentUserId = data.user_id;
          document.getElementById('sq1-text').textContent = data.security_question_1;
          document.getElementById('sq2-text').textContent = data.security_question_2;
          document.getElementById('reset-user-id').value = data.user_id;
          document.getElementById('reset-user-id-2').value = data.user_id;

          step1Form.style.display = 'none';
          step2Form.style.display = 'block';
        } else {
          alert(data.error || 'Email not found. Please check and try again.');
        }
      } catch (err) {
        alert('Network error. Please try again.');
      }
    });

    // Step 2: Verify security answers
    verifyBtn.addEventListener('click', async function() {
      const answer1 = document.getElementById('sa1').value.trim();
      const answer2 = document.getElementById('sa2').value.trim();

      if (!answer1 || !answer2) {
        alert('Please answer both security questions.');
        return;
      }

      try {
        const res = await fetch('../api/forgot-password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            step: 2,
            user_id: currentUserId,
            answer1: answer1,
            answer2: answer2
          })
        });
        const data = await res.json();

        if (data.success) {
          // Security answers verified, show reset password form
          step2Form.style.display = 'none';
          step3Form.style.display = 'block';
        } else {
          alert(data.error || 'Incorrect answers. Please try again.');
        }
      } catch (err) {
        alert('Network error. Please try again.');
      }
    });

    // Step 3: Reset password
    resetBtn.addEventListener('click', async function() {
      const newPassword = document.getElementById('new-password').value;
      const confirmPassword = document.getElementById('confirm-new-password').value;

      if (!newPassword || !confirmPassword) {
        alert('Please fill in all password fields.');
        return;
      }

      if (newPassword !== confirmPassword) {
        alert('Passwords do not match.');
        return;
      }

      if (newPassword.length < 8) {
        alert('Password must be at least 8 characters.');
        return;
      }

      try {
        const res = await fetch('../api/forgot-password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            step: 3,
            user_id: currentUserId,
            new_password: newPassword,
            confirm_new_password: confirmPassword
          })
        });
        const data = await res.json();

        if (data.success) {
          // Show success message and redirect
          step3Form.innerHTML = '<div style="text-align:center; padding:40px 20px;"><div style="font-size:3rem;color:#10b981;margin-bottom:16px;"><i class="fas fa-check-circle"></i></div><h2 style="color:#065f46;margin-bottom:8px;">Password Changed Successfully</h2><p style="color:#64748b;margin-bottom:24px;">Your password has been updated. You can now log in with your new password.</p><a href="../index.php" class="btn header-auth-form-submit">Go to Login</a></div>';
        } else {
          alert(data.error || 'Failed to reset password.');
        }
      } catch (err) {
        alert('Network error. Please try again.');
      }
    });
  </script>
</body>
</html>

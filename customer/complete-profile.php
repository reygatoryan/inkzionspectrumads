<?php
session_start();
require_once __DIR__ . '/../db-config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];
$error = '';
$success = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT name, email, contact_number, address, avatar, role FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$isAdmin = ($user['role'] ?? '') === 'admin';

// If profile is already complete, redirect based on role
if (!empty($user['name']) && !empty($user['contact_number']) && !empty($user['address'])) {
    header('Location: ' . ($isAdmin ? 'admin/dashboard.php' : 'store-product.php'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($name)) {
        $error = 'Full name is required.';
    } elseif (empty($contact_number)) {
        $error = 'Contact number is required.';
    } elseif (empty($address)) {
        $error = 'Address is required.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, contact_number = ?, address = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $contact_number, $address, $userId);
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $name;
            $_SESSION['user_phone'] = $contact_number;
            $_SESSION['user_address'] = $address;
            $success = 'Profile saved!';
            header('Location: ' . ($isAdmin ? 'admin/dashboard.php' : 'store-product.php'));
            exit();
        } else {
            $error = 'Failed to save profile. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Complete Your Profile | Inkzion Spectrum Ads</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --primary: #2B4C52;
      --primary-bg: rgba(43, 76, 82, 0.1);
      --text-primary: #0F172A;
      --text-secondary: #475569;
      --text-muted: #64748B;
      --border-color: #E2E8F0;
      --success: #10B981;
      --danger: #EF4444;
      --font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    body { font-family: var(--font); background: linear-gradient(135deg,#FAF7EE,#E8F1ED); color: var(--text-primary); line-height: 1.6; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .profile-wrapper { width: 100%; max-width: 460px; padding: 2rem; background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); margin: 2rem; }
    .profile-header { text-align: center; margin-bottom: 1.5rem; }
    .profile-header h1 { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); }
    .profile-header p { color: var(--text-muted); font-size: 0.85rem; margin-top: 0.3rem; }
    .profile-avatar { width: 72px; height: 72px; margin: 0 auto 1rem; border-radius: 50%; overflow: hidden; border: 3px solid var(--primary); display: flex; align-items: center; justify-content: center; background: var(--primary-bg); }
    .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .profile-avatar i { font-size: 2rem; color: var(--primary); }
    .form-group { margin-bottom: 1.25rem; }
    .form-group label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.3rem; }
    .form-group input { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 10px; font-size: 0.88rem; font-family: var(--font); color: var(--text-primary); outline: none; transition: all 0.2s; }
    .form-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
    .btn-primary { width: 100%; padding: 0.85rem; background: linear-gradient(135deg, var(--primary), #4A7C84); color: white; border: none; border-radius: 10px; font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(43, 76, 82,0.3); }
    .error-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 1rem; color: var(--danger); font-size: 0.85rem; font-weight: 600; }
    .success-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 1rem; color: var(--success); font-size: 0.85rem; font-weight: 600; }
    .skip-link { display: block; text-align: center; margin-top: 1rem; font-size: 0.8rem; }
    .skip-link a { color: var(--text-muted); text-decoration: underline; }
    .skip-link a:hover { color: var(--primary); }
    .email-display { text-align: center; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1.25rem; }
  </style>
</head>
<body>
  <div class="profile-wrapper">
    <div class="profile-header">
      <div class="profile-avatar">
        <?php if (!empty($user['avatar'])): ?>
          <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Profile">
        <?php else: ?>
          <i class="fas fa-user-circle"></i>
        <?php endif; ?>
      </div>
      <h1>Complete Your Profile</h1>
      <p>Just one more step before you can start ordering.</p>
    </div>

    <div class="email-display">
      <i class="fas fa-envelope" style="margin-right:0.3rem;"></i>
      <?php echo htmlspecialchars($user['email'] ?? ''); ?>
    </div>

    <?php if ($error): ?>
      <div class="error-box"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="success-box"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" class="profile-form">
      <div class="form-group">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? $user['name'] ?? ''); ?>" placeholder="Enter your full name" required>
      </div>
      <div class="form-group">
        <label for="contact_number">Contact Number</label>
        <input type="tel" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($_POST['contact_number'] ?? $user['contact_number'] ?? ''); ?>" placeholder="+63 9XX XXX XXXX" required>
      </div>
      <div class="form-group">
        <label for="address">Address</label>
        <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($_POST['address'] ?? $user['address'] ?? ''); ?>" placeholder="Street, City, Province" required>
      </div>
      <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Save & Continue</button>
    </form>

  </div>
  <script>navigator.sendBeacon('../api/track-visit.php?url=' + encodeURIComponent(location.pathname + location.search) + '&_=' + Date.now());</script>
</body>
</html>

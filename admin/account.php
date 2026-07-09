<?php
$pageTitle = 'My Account';
$pageSubtitle = 'Manage your profile, security, and preferences.';
require 'includes/admin-header.php';

$sellerName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$sellerEmail = $_SESSION['user_email'] ?? '';

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$seller = $result->fetch_assoc();
$stmt->close();

// Initialize profile data with defaults
$profileData = [
    'name' => $seller['name'] ?? '',
    'email' => $seller['email'] ?? '',
    'contact_number' => $seller['contact_number'] ?? '',
    'profile_photo' => $seller['profile_photo'] ?? null,
    'store_name' => $seller['store_name'] ?? '',
    'username' => $seller['username'] ?? '',
    'gender' => $seller['gender'] ?? '',
    'date_of_birth' => $seller['date_of_birth'] ?? '',
    'account_type' => $seller['account_type'] ?? 'admin',
];

$successMessage = $_SESSION['success'] ?? null;
$errorMessage = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>
<style>
  :root {
    --pa-primary: #e91e8c; --pa-primary-light: #9c27b0;
    --pa-primary-bg: rgba(233,30,140,0.1);
    --pa-success: #059669; --pa-error: #ef4444;
  }
  .pa-card { background: white; border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.25rem; box-shadow: 0 2px 8px rgba(15,23,42,0.04); }
  .pa-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-light); }
  .pa-card-header h2 { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem; }
  .pa-card-header h2 i { color: var(--pa-primary); }
  .pa-photo-section { display: flex; align-items: center; gap: 1.5rem; }
  .pa-photo-preview { width: 100px; height: 100px; border-radius: 16px; background: linear-gradient(135deg, var(--pa-primary), #00bcd4); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; overflow: hidden; border: 3px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.1); flex-shrink: 0; }
  .pa-photo-preview img { width: 100%; height: 100%; object-fit: cover; }
  .pa-photo-actions { flex: 1; }
  .pa-photo-actions p { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem; }
  .pa-photo-upload-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1rem; border-radius: 10px; background: white; color: var(--text-secondary); border: 1px solid var(--border-color); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: var(--transition); }
  .pa-photo-upload-btn:hover { background: var(--border-light); border-color: #cbd5e1; }
  .pa-form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
  .pa-form-group { margin-bottom: 1rem; }
  .pa-form-group.full-width { grid-column: 1 / -1; }
  .pa-form-label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem; }
  .pa-form-input, .pa-form-select { width: 100%; padding: 0.65rem 0.9rem; border: 1.5px solid var(--border-color); border-radius: 10px; font-size: 0.88rem; outline: none; transition: var(--transition); font-family: inherit; background: white; }
  .pa-form-input:focus, .pa-form-select:focus { border-color: var(--pa-primary); box-shadow: 0 0 0 3px var(--pa-primary-bg); }
  .pa-form-input:disabled { background: var(--border-light); color: var(--text-muted); cursor: not-allowed; }
  .pa-form-hint { font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem; }
  .pa-form-error { font-size: 0.75rem; color: var(--pa-error); margin-top: 0.25rem; display: none; }
  .pa-form-group.error .pa-form-input { border-color: var(--pa-error); }
  .pa-form-group.error .pa-form-error { display: block; }
  .pa-actions { display: flex; gap: 0.75rem; justify-content: flex-end; padding-top: 1rem; border-top: 1px solid var(--border-color); margin-top: 1.5rem; }
  .pa-notification { padding: 1rem 1.5rem; border-radius: 12px; font-size: 0.88rem; font-weight: 500; display: none; align-items: center; gap: 0.75rem; animation: slideIn 0.3s ease; margin-bottom: 1rem; }
  .pa-notification.show { display: flex; }
  .pa-notification.success { background: var(--pa-success); color: white; }
  .pa-notification.error { background: var(--pa-error); color: white; }
  @keyframes slideIn { from { opacity: 0; transform: translateX(100px); } to { opacity: 1; transform: translateX(0); } }
  @media (max-width: 768px) { .pa-form-grid { grid-template-columns: 1fr; } .pa-photo-section { flex-direction: column; align-items: flex-start; } .pa-actions { flex-direction: column; } .pa-btn { width: 100%; justify-content: center; } }
</style>

      <?php if ($successMessage): ?>
        <div class="pa-notification success" id="notification">
          <i class="fas fa-check-circle"></i>
          <span><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      <?php endif; ?>

      <?php if ($errorMessage): ?>
        <div class="pa-notification error" id="notification">
          <i class="fas fa-exclamation-circle"></i>
          <span><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      <?php endif; ?>

      <form id="profileForm" method="POST" action="../api/admin-update-account.php" enctype="multipart/form-data">
        <!-- Profile Photo Card -->
        <div class="pa-card">
          <div class="pa-card-header">
            <h2><i class="fas fa-camera"></i> Profile Photo</h2>
          </div>
          <div class="pa-photo-section">
            <div class="pa-photo-preview" id="photoPreview">
              <?php if (!empty($profileData['profile_photo'])): ?>
                <img src="<?php echo htmlspecialchars($profileData['profile_photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Profile Photo">
              <?php else: ?>
                <?php echo strtoupper(substr($sellerName, 0, 1)); ?>
              <?php endif; ?>
            </div>
            <div class="pa-photo-actions">
              <p>Upload a profile photo to personalize your account. Recommended size: 200x200px</p>
              <input type="file" name="profile_photo" id="profilePhotoInput" accept="image/*" style="display: none;" onchange="previewPhoto(this)">
              <button type="button" class="pa-photo-upload-btn" onclick="document.getElementById('profilePhotoInput').click()">
                <i class="fas fa-upload"></i> Choose Photo
              </button>
            </div>
          </div>
        </div>

        <!-- Account Information Card -->
        <div class="pa-card">
          <div class="pa-card-header">
            <h2><i class="fas fa-user"></i> Account Information</h2>
          </div>
          <div class="pa-form-grid">
            <div class="pa-form-group">
              <label class="pa-form-label">Username *</label>
              <input type="text" name="username" class="pa-form-input" value="<?php echo htmlspecialchars($profileData['username'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
              <p class="pa-form-hint">Username cannot be changed</p>
            </div>
            <div class="pa-form-group">
              <label class="pa-form-label">Account Type</label>
              <input type="text" class="pa-form-input" value="<?php echo ucfirst($profileData['account_type']); ?>" disabled>
            </div>
            <div class="pa-form-group">
              <label class="pa-form-label">Store Name *</label>
              <input type="text" name="store_name" class="pa-form-input" value="<?php echo htmlspecialchars($profileData['store_name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter your store name" required>
            </div>
            <div class="pa-form-group">
              <label class="pa-form-label">Full Name *</label>
              <input type="text" name="name" class="pa-form-input" value="<?php echo htmlspecialchars($profileData['name'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter your full name" required>
            </div>
            <div class="pa-form-group">
              <label class="pa-form-label">Email Address *</label>
              <div style="display: flex; gap: 0.5rem;">
                <input type="email" name="email" class="pa-form-input" value="<?php echo htmlspecialchars($profileData['email'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter email address" required style="flex: 1;">
                <button type="button" class="pa-btn pa-btn-outline" onclick="changeEmail()">Change</button>
              </div>
            </div>
            <div class="pa-form-group">
              <label class="pa-form-label">Phone Number *</label>
              <div style="display: flex; gap: 0.5rem;">
                <input type="tel" name="contact_number" class="pa-form-input" value="<?php echo htmlspecialchars($profileData['contact_number'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="+63 XXX XXX XXXX" required style="flex: 1;">
                <button type="button" class="pa-btn pa-btn-outline" onclick="changePhone()">Change</button>
              </div>
            </div>
            <div class="pa-form-group">
              <label class="pa-form-label">Gender</label>
              <select name="gender" class="pa-form-select">
                <option value="">Select gender</option>
                <option value="male" <?php echo $profileData['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                <option value="female" <?php echo $profileData['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                <option value="other" <?php echo $profileData['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                <option value="prefer_not_to_say" <?php echo $profileData['gender'] === 'prefer_not_to_say' ? 'selected' : ''; ?>>Prefer not to say</option>
              </select>
            </div>
            <div class="pa-form-group">
              <label class="pa-form-label">Date of Birth</label>
              <input type="date" name="date_of_birth" class="pa-form-input" value="<?php echo htmlspecialchars($profileData['date_of_birth'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="pa-actions">
          <button type="button" class="pa-btn pa-btn-ghost" onclick="resetForm()">
            <i class="fas fa-undo"></i> Reset
          </button>
          <button type="button" class="pa-btn pa-btn-outline" onclick="changePassword()">
            <i class="fas fa-key"></i> Change Password
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Changes
          </button>
        </div>
      </form>

<script>
    // Show notification
    function showNotification(message, type) {
      const notification = document.getElementById('notification');
      if (notification) {
        notification.className = 'pa-notification ' + type + ' show';
        notification.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i><span>' + message + '</span>';
        setTimeout(() => {
          notification.classList.remove('show');
        }, 3000);
      }
    }

    // Photo preview
    function previewPhoto(input) {
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
          const preview = document.getElementById('photoPreview');
          preview.innerHTML = '<img src="' + e.target.result + '" alt="Profile Photo">';
        }
        reader.readAsDataURL(input.files[0]);
      }
    }

    // Form submission
    document.getElementById('profileForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData(this);
      const submitBtn = this.querySelector('button[type="submit"]');
      const originalHTML = submitBtn.innerHTML;
      
      // Basic validation
      let isValid = true;
      const requiredFields = this.querySelectorAll('[required]');
      requiredFields.forEach(field => {
        if (!field.value.trim()) {
          isValid = false;
          field.closest('.pa-form-group').classList.add('error');
        } else {
          field.closest('.pa-form-group').classList.remove('error');
        }
      });

      if (!isValid) {
        showNotification('Please fill in all required fields', 'error');
        return;
      }

      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
      submitBtn.disabled = true;
      
      fetch(this.action, {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showNotification('Profile updated successfully!', 'success');
          setTimeout(() => location.reload(), 1500);
        } else {
          showNotification(data.error || 'Failed to update profile', 'error');
        }
      })
      .catch(error => {
        showNotification('An error occurred. Please try again.', 'error');
      })
      .finally(() => {
        submitBtn.innerHTML = originalHTML;
        submitBtn.disabled = false;
      });
    });

    // Change password
    function changePassword() {
      const newPassword = prompt('Enter new password (minimum 6 characters):');
      if (newPassword && newPassword.length >= 6) {
        const confirmPassword = prompt('Confirm new password:');
        if (newPassword === confirmPassword) {
          showNotification('Password changed successfully!', 'success');
        } else {
          showNotification('Passwords do not match', 'error');
        }
      } else if (newPassword) {
        showNotification('Password must be at least 6 characters', 'error');
      }
    }

    // Change email
    function changeEmail() {
      const newEmail = prompt('Enter new email address:');
      if (newEmail && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
        const form = document.getElementById('profileForm');
        form.querySelector('input[name="email"]').value = newEmail;
        showNotification('Email updated. Click Save Changes to confirm.', 'success');
      } else if (newEmail) {
        showNotification('Please enter a valid email address', 'error');
      }
    }

    // Change phone
    function changePhone() {
      const newPhone = prompt('Enter new phone number (+63 XXX XXX XXXX):');
      if (newPhone && newPhone.length >= 10) {
        const form = document.getElementById('profileForm');
        form.querySelector('input[name="contact_number"]').value = newPhone;
        showNotification('Phone number updated. Click Save Changes to confirm.', 'success');
      } else if (newPhone) {
        showNotification('Please enter a valid phone number', 'error');
      }
    }

    // Reset form
    function resetForm() {
      if (confirm('Are you sure you want to reset all changes?')) {
        location.reload();
      }
    }

    // Remove error styling on input
    document.querySelectorAll('.pa-form-input').forEach(input => {
      input.addEventListener('input', function() {
        this.closest('.pa-form-group').classList.remove('error');
      });
    });

    document.addEventListener('DOMContentLoaded', function() {
      <?php if ($successMessage || $errorMessage): ?>
      setTimeout(() => {
        const notification = document.getElementById('notification');
        if (notification) {
          notification.classList.add('show');
          setTimeout(() => {
            notification.classList.remove('show');
          }, 3000);
        }
      }, 100);
      <?php endif; ?>
    });
  </script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>




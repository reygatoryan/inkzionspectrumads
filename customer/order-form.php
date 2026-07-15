<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../db-config.php';

$userId = $_SESSION['user_id'];
$proposalId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$proposalId) {
    header('Location: profile.php');
    exit();
}

// Fetch proposal
$stmt = $conn->prepare("SELECT * FROM order_proposals WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $proposalId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$proposal = $result->fetch_assoc();
$stmt->close();

if (!$proposal) {
    header('Location: profile.php');
    exit();
}

$items = json_decode($proposal['items'], true) ?: [];

// Use Ready for Purchase data for Quote Summary
$rfpName = '';
$rfpPrice = 0;
$rfpQty = 1;
$rfpShipping = 0;
$rfpSubtotal = 0;
$rfpTotal = 0;
if (!empty($proposal['request_id'])) {
    $rfpStmt = $conn->prepare("SELECT ready_for_purchase_name, ready_for_purchase_price, ready_for_purchase_qty, ready_for_purchase_shipping FROM custom_printing_requests WHERE id = ?");
    $rfpStmt->bind_param('i', $proposal['request_id']);
    $rfpStmt->execute();
    $rfpRow = $rfpStmt->get_result()->fetch_assoc();
    $rfpStmt->close();
    if ($rfpRow && $rfpRow['ready_for_purchase_price']) {
        $rfpName = $rfpRow['ready_for_purchase_name'];
        $rfpPrice = (float)$rfpRow['ready_for_purchase_price'];
        $rfpQty = (int)$rfpRow['ready_for_purchase_qty'];
        $rfpShipping = (float)$rfpRow['ready_for_purchase_shipping'];
        $rfpSubtotal = $rfpPrice * $rfpQty;
        $rfpTotal = $rfpSubtotal + $rfpShipping;
    }
}
// Fall back to stored proposal data if no RFP data
if (empty($rfpName)) {
    $rfpSubtotal = (float)$proposal['subtotal'];
    $rfpShipping = (float)$proposal['shipping_fee'];
    $rfpTotal = (float)$proposal['total_amount'];
}

// Fetch user data for pre-filling
$userStmt = $conn->prepare("SELECT name, email, contact_number, address FROM users WHERE id = ?");
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userData = $userResult->fetch_assoc();
$userStmt->close();

$markNotif = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND related_type = 'order_proposal' AND related_id = ? AND is_read = 0");
$markNotif->bind_param('ii', $userId, $proposalId);
$markNotif->execute();
$markNotif->close();

$conn->close();

$canEdit = ($proposal['status'] === 'sent' || $proposal['status'] === 'rejected');
$submitted = ($proposal['status'] === 'filled' || $proposal['status'] === 'approved' || $proposal['status'] === 'converted');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Order Form | Inkzion Spectrum Ads</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #0f172a; line-height: 1.6; min-height: 100vh; }
    .container { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem; width: 100%; }
    .page-header { margin-bottom: 2rem; }
    .page-header h1 { font-size: 1.6rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 0.6rem; }
    .page-header h1 i { color: #2B4C52; }
    .page-header p { color: #64748b; margin-top: 0.3rem; }
    .back-link { display: inline-flex; align-items: center; gap: 0.4rem; color: #2B4C52; text-decoration: none; font-weight: 600; font-size: 0.88rem; margin-bottom: 1.5rem; }
    .back-link:hover { text-decoration: underline; }

    .card { background: white; border-radius: 20px; box-shadow: 0 4px 16px rgba(0,0,0,0.04); padding: 2rem; margin-bottom: 1.5rem; border: 1px solid #e2e8f0; }
    .card h2 { font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem; }
    .card h2 i { color: #2B4C52; margin-right: 0.5rem; }

    .quote-table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
    .quote-table th { text-align: left; padding: 0.6rem 0.75rem; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .quote-table td { padding: 0.6rem 0.75rem; font-size: 0.88rem; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
    .quote-table tr:last-child td { border-bottom: none; }
    .total-row td { font-weight: 700; padding-top: 1rem; border-top: 2px solid #e2e8f0; }
    .total-row .amount { color: #2B4C52; font-size: 1.05rem; }

    .admin-notes { background: #f0f9ff; border-radius: 12px; padding: 1rem; color: #1e293b; font-size: 0.9rem; margin-bottom: 1.5rem; border-left: 4px solid #3b82f6; }
    .admin-notes strong { display: block; font-size: 0.78rem; color: #3b82f6; text-transform: uppercase; margin-bottom: 0.3rem; }

    .status-banner { padding: 0.85rem 1.2rem; border-radius: 12px; font-weight: 600; font-size: 0.9rem; margin-bottom: 1.5rem; }
    .status-banner.sent { background: rgba(59,130,246,0.1); color: #1d4ed8; border: 1px solid rgba(59,130,246,0.25); }
    .status-banner.filled { background: rgba(5,150,105,0.1); color: #047857; border: 1px solid rgba(5,150,105,0.25); }
    .status-banner.approved { background: rgba(5,150,105,0.1); color: #047857; border: 1px solid rgba(5,150,105,0.25); }
    .status-banner.rejected { background: rgba(239,68,68,0.1); color: #dc2626; border: 1px solid rgba(239,68,68,0.25); }
    .status-banner.converted { background: rgba(43, 76, 82,0.08); color: #3D5C42; border: 1px solid rgba(43, 76, 82,0.25); }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-grid .full-width { grid-column: 1 / -1; }
    .form-group { display: flex; flex-direction: column; gap: 0.35rem; }
    .form-group label { font-size: 0.85rem; font-weight: 600; color: #334155; }
    .form-group label .required { color: #ef4444; }
    .form-group input, .form-group select, .form-group textarea {
      width: 100%; padding: 0.75rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 10px;
      font-size: 0.9rem; font-family: inherit; outline: none; transition: border-color 0.2s; background: white;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #2B4C52; box-shadow: 0 0 0 3px rgba(43, 76, 82,0.08); }
    .form-group textarea { min-height: 80px; resize: vertical; }
    .form-group .error-msg { font-size: 0.75rem; color: #ef4444; display: none; }
    .form-group.has-error input, .form-group.has-error select, .form-group.has-error textarea { border-color: #ef4444; }
    .form-group.has-error .error-msg { display: block; }

    .btn-primary {
      display: inline-flex; align-items: center; gap: 0.5rem;
      padding: 0.85rem 2rem; border-radius: 12px; border: none;
      background: linear-gradient(135deg, #2B4C52, #4A7C84); color: white;
      cursor: pointer; font-weight: 700; font-size: 0.95rem;
      transition: all 0.2s ease; font-family: inherit;
    }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(43, 76, 82,0.3); }
    .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }

    .submitted-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .success-msg { text-align: center; padding: 3rem 2rem; }
    .success-msg i { font-size: 4rem; color: #10b981; margin-bottom: 1rem; display: block; }
    .success-msg h2 { font-size: 1.4rem; margin-bottom: 0.5rem; color: #0f172a; }
    .success-msg p { color: #64748b; margin-bottom: 1.5rem; }

    .rejection-box { background: #fef2f2; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; border: 1px solid rgba(239,68,68,0.2); }
    .rejection-box strong { color: #dc2626; font-size: 0.85rem; display: block; margin-bottom: 0.3rem; }
    .rejection-box p { color: #991b1b; font-size: 0.88rem; }

    @media (max-width: 768px) {
      .container { padding: 1.5rem; }
      .card { padding: 1.5rem; }
      .page-header h1 { font-size: 1.4rem; }
    }
    @media (max-width: 640px) {
      .form-grid { grid-template-columns: 1fr; }
      .submitted-grid { grid-template-columns: 1fr; }
      .page-header h1 { font-size: 1.3rem; }
      .card { padding: 1.25rem; }
      .container { padding: 1rem; }
      .quote-table th, .quote-table td { padding: 0.4rem 0.5rem; font-size: 0.8rem; }
    }
    @media (max-width: 480px) {
      .page-header h1 { font-size: 1.2rem; }
      .container { padding: 0.75rem; }
      .card { padding: 1rem; }
      .btn-primary { padding: 0.75rem 1.25rem; font-size: 0.85rem; width: 100%; justify-content: center; }
      .quote-table th, .quote-table td { padding: 0.35rem 0.4rem; font-size: 0.75rem; }
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="my-requests.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to My Requests</a>

    <div class="page-header">
      <h1><i class="fas fa-file-invoice"></i> Order Form</h1>
      <p>Review the admin's quote and fill in your details to proceed.</p>
    </div>

    <div class="status-banner <?php echo $proposal['status']; ?>">
      <i class="fas fa-<?php
        echo $proposal['status'] === 'sent' ? 'clock' : ($proposal['status'] === 'filled' ? 'check-circle' : ($proposal['status'] === 'approved' ? 'check-double' : ($proposal['status'] === 'rejected' ? 'times-circle' : 'check-circle')));
      ?>"></i>
      Status: <?php echo ucfirst($proposal['status']); ?>
      <?php if ($proposal['status'] === 'sent'): ?>— Please fill in your details below<?php endif; ?>
    </div>

    <?php if ($proposal['status'] === 'rejected' && $proposal['rejection_reason']): ?>
    <div class="rejection-box">
      <strong><i class="fas fa-exclamation-circle"></i> Revision Needed</strong>
      <p><?php echo htmlspecialchars($proposal['rejection_reason']); ?></p>
    </div>
    <?php endif; ?>

    <!-- Quote Summary -->
    <div class="card">
      <h2><i class="fas fa-receipt"></i> Quote Summary</h2>
      <div style="overflow-x:auto;"><table class="quote-table">
        <thead>
          <tr>
            <th>Item</th>
            <th style="text-align:center;">Qty</th>
            <th style="text-align:right;">Unit Price</th>
            <th style="text-align:right;">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($rfpName)): ?>
          <tr>
            <td><?php echo htmlspecialchars($rfpName); ?></td>
            <td style="text-align:center;"><?php echo $rfpQty; ?></td>
            <td style="text-align:right;">₱<?php echo number_format($rfpPrice, 2); ?></td>
            <td style="text-align:right;">₱<?php echo number_format($rfpSubtotal, 2); ?></td>
          </tr>
          <?php else: ?>
          <?php foreach ($items as $item): ?>
          <tr>
            <td><?php echo htmlspecialchars($item['name'] ?? 'Item'); ?></td>
            <td style="text-align:center;"><?php echo (int)($item['quantity'] ?? 1); ?></td>
            <td style="text-align:right;">₱<?php echo number_format((float)($item['unit_price'] ?? 0), 2); ?></td>
            <td style="text-align:right;">₱<?php echo number_format((float)($item['unit_price'] ?? 0) * (int)($item['quantity'] ?? 1), 2); ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" style="text-align:right;font-weight:600;">Subtotal</td>
            <td style="text-align:right;">₱<?php echo number_format($rfpSubtotal, 2); ?></td>
          </tr>
          <?php if ($rfpShipping > 0): ?>
          <tr>
            <td colspan="3" style="text-align:right;font-weight:600;">Shipping Fee</td>
            <td style="text-align:right;">₱<?php echo number_format($rfpShipping, 2); ?></td>
          </tr>
          <?php endif; ?>
          <tr class="total-row">
            <td colspan="3" style="text-align:right;">Total</td>
            <td class="amount" style="text-align:right;">₱<?php echo number_format($rfpTotal, 2); ?></td>
          </tr>
        </tfoot>
      </table></div>

      <?php if ($proposal['admin_notes']): ?>
      <div class="admin-notes">
        <strong><i class="fas fa-sticky-note"></i> Admin Notes</strong>
        <?php echo nl2br(htmlspecialchars($proposal['admin_notes'])); ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($submitted && !$canEdit): ?>
    <!-- Submitted Details -->
    <div class="card">
      <h2><i class="fas fa-check-circle" style="color:#10b981;"></i> Your Submitted Details</h2>
      <div class="submitted-grid">
        <div><strong>Full Name:</strong><br><?php echo htmlspecialchars($proposal['full_name'] ?? ''); ?></div>
        <div><strong>Email:</strong><br><?php echo htmlspecialchars($proposal['email'] ?? ''); ?></div>
        <div><strong>Phone:</strong><br><?php echo htmlspecialchars($proposal['phone'] ?? ''); ?></div>
        <div><strong>Payment Method:</strong><br><?php echo ucfirst($proposal['payment_method'] ?? ''); ?></div>
        <div class="full-width"><strong>Delivery Address:</strong><br><?php echo nl2br(htmlspecialchars($proposal['delivery_address'] ?? '')); ?><br>
          <?php echo htmlspecialchars($proposal['city'] ?? ''); ?>, <?php echo htmlspecialchars($proposal['province'] ?? ''); ?> <?php echo htmlspecialchars($proposal['zip'] ?? ''); ?>
        </div>
        <?php if ($proposal['landmark']): ?>
        <div class="full-width"><strong>Landmark:</strong><br><?php echo htmlspecialchars($proposal['landmark']); ?></div>
        <?php endif; ?>
        <?php if ($proposal['additional_notes']): ?>
        <div class="full-width"><strong>Additional Notes:</strong><br><?php echo nl2br(htmlspecialchars($proposal['additional_notes'])); ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($canEdit): ?>
    <!-- Fill Details Form -->
    <div class="card">
      <h2><i class="fas fa-pen"></i> Your Details</h2>
      <p style="color:#64748b;font-size:0.88rem;margin-bottom:1.25rem;">Fill in your delivery and payment information to submit this order form.</p>

      <form id="orderForm" onsubmit="event.preventDefault(); submitForm();">
        <input type="hidden" name="proposal_id" value="<?php echo $proposalId; ?>">

        <div class="form-grid">
          <div class="form-group full-width">
            <label>Full Name <span class="required">*</span></label>
            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($userData['name'] ?? ''); ?>" placeholder="Enter your full name" required>
            <div class="error-msg">Please enter your full name</div>
          </div>
          <div class="form-group">
            <label>Email <span class="required">*</span></label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" placeholder="your@email.com" required>
            <div class="error-msg">Please enter a valid email</div>
          </div>
          <div class="form-group">
            <label>Contact Number <span class="required">*</span></label>
            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['contact_number'] ?? ''); ?>" placeholder="09XX-XXX-XXXX" required>
            <div class="error-msg">Please enter your contact number</div>
          </div>
          <div class="form-group">
            <label>Street / Building / Barangay <span class="required">*</span></label>
            <input type="text" id="delivery_address" name="delivery_address" placeholder="123 Main St, Barangay San Jose" required>
            <div class="error-msg">Please enter your street address</div>
          </div>
          <div class="form-group">
            <label>City <span class="required">*</span></label>
            <input type="text" id="city" name="city" placeholder="e.g. Manila" required>
            <div class="error-msg">Please enter your city</div>
          </div>
          <div class="form-group">
            <label>Province <span class="required">*</span></label>
            <input type="text" id="province" name="province" placeholder="e.g. Metro Manila" required>
            <div class="error-msg">Please enter your province</div>
          </div>
          <div class="form-group">
            <label>ZIP Code <span class="required">*</span></label>
            <input type="text" id="zip" name="zip" placeholder="e.g. 1000" required>
            <div class="error-msg">Please enter your ZIP code</div>
          </div>
          <div class="form-group full-width">
            <label>Landmark <span style="font-weight:400;color:#94a3b8;">(optional)</span></label>
            <input type="text" id="landmark" name="landmark" placeholder="e.g. Near church, beside 7-Eleven, etc.">
          </div>
          <div class="form-group">
            <label>Payment Method <span class="required">*</span></label>
            <select id="payment_method" name="payment_method" required>
              <option value="">Select payment method...</option>
              <option value="gcash">GCash</option>
              <option value="credit-card">Credit Card</option>
              <option value="cod">Cash on Delivery</option>
              <option value="downpayment">Downpayment (50%)</option>
            </select>
            <div class="error-msg">Please select a payment method</div>
          </div>
          <div class="form-group full-width">
            <label>Additional Notes <span style="font-weight:400;color:#94a3b8;">(optional)</span></label>
            <textarea id="additional_notes" name="additional_notes" placeholder="Any special instructions for delivery..."></textarea>
          </div>
        </div>

        <div style="margin-top:1.5rem;display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
          <button type="submit" class="btn-primary" id="submitBtn"><i class="fas fa-paper-plane"></i> Submit Order Details</button>
          <span id="formError" style="color:#ef4444;font-size:0.85rem;display:none;"></span>
        </div>
        <div id="formLoading" style="display:none;margin-top:0.75rem;color:#64748b;"><i class="fas fa-spinner fa-pulse"></i> Submitting...</div>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <script>
    async function submitForm() {
      const btn = document.getElementById('submitBtn');
      const errorEl = document.getElementById('formError');
      const loadingEl = document.getElementById('formLoading');
      errorEl.style.display = 'none';

      // Client-side validation
      const fields = [
        'full_name', 'email', 'phone', 'delivery_address', 'city', 'province', 'zip', 'payment_method'
      ];
      let hasError = false;

      fields.forEach(id => {
        const input = document.getElementById(id);
        const group = input.closest('.form-group');
        if (!input.value.trim()) {
          group.classList.add('has-error');
          hasError = true;
        } else {
          group.classList.remove('has-error');
        }
      });

      // Validate email format
      const emailInput = document.getElementById('email');
      const emailGroup = emailInput.closest('.form-group');
      if (emailInput.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim())) {
        emailGroup.classList.add('has-error');
        emailGroup.querySelector('.error-msg').textContent = 'Please enter a valid email address';
        hasError = true;
      }

      if (hasError) {
        errorEl.textContent = 'Please fill in all required fields correctly.';
        errorEl.style.display = 'block';
        return;
      }

      btn.disabled = true;
      loadingEl.style.display = 'block';

      try {
        const res = await fetch('../api/order-proposals.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            action: 'submit_details',
            proposal_id: <?php echo $proposalId; ?>,
            full_name: document.getElementById('full_name').value.trim(),
            email: document.getElementById('email').value.trim(),
            phone: document.getElementById('phone').value.trim(),
            delivery_address: document.getElementById('delivery_address').value.trim(),
            city: document.getElementById('city').value.trim(),
            province: document.getElementById('province').value.trim(),
            zip: document.getElementById('zip').value.trim(),
            landmark: document.getElementById('landmark').value.trim(),
            payment_method: document.getElementById('payment_method').value,
            additional_notes: document.getElementById('additional_notes').value.trim()
          })
        });
        const data = await res.json();
        if (data.success) {
          // Show success message and redirect
          document.getElementById('orderForm').innerHTML = `
            <div class="success-msg">
              <i class="fas fa-check-circle"></i>
              <h2>Details Submitted!</h2>
              <p>${data.message}</p>
              <a href="profile.php" class="btn-primary"><i class="fas fa-arrow-left"></i> Back to Account</a>
            </div>
          `;
        } else {
          errorEl.textContent = data.error || 'Failed to submit';
          errorEl.style.display = 'block';
        }
      } catch (e) {
        errorEl.textContent = 'Network error. Please try again.';
        errorEl.style.display = 'block';
      } finally {
        btn.disabled = false;
        loadingEl.style.display = 'none';
      }
    }

    // Remove error styling on input
    document.querySelectorAll('.form-group input, .form-group select, .form-group textarea').forEach(el => {
      el.addEventListener('input', function() {
        this.closest('.form-group').classList.remove('has-error');
      });
      el.addEventListener('change', function() {
        if (this.value.trim()) {
          this.closest('.form-group').classList.remove('has-error');
        }
      });
    });
  </script>
</body>
</html>

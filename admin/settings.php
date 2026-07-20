<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/admin-header.php';
?>
<style>

    .ss-topbar {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;
    }
    .ss-topbar-actions { display: flex; gap: 0.5rem; align-items: center; }
    .btn {
      display: inline-flex; align-items: center; gap: 0.4rem;
      padding: 0.5rem 1rem; border-radius: 10px;
      font-size: 0.8rem; font-weight: 600; border: none;
      cursor: pointer; text-decoration: none; transition: all 0.2s ease;
    }
    .btn-primary { background: linear-gradient(135deg, #2B4C52, #4A7C84); color: white; box-shadow: 0 4px 12px rgba(43, 76, 82,0.2); }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(43, 76, 82,0.3); }
    .btn-outline { background: white; color: #475569; border: 1px solid #e2e8f0; }
    .btn-outline:hover { background: #f1f5f9; }
    .btn-sm { padding: 0.3rem 0.6rem; font-size: 0.72rem; border-radius: 6px; }
    .btn-danger { background: #ef4444; color: white; }
    .btn-danger:hover { background: #dc2626; }
    .btn-success { background: #10b981; color: white; }
    .btn-success:hover { background: #059669; }

    .ss-top-tabs {
      display: flex; gap: 0.25rem; background: white;
      border: 1px solid #e8ecf1; border-radius: 14px; padding: 0.35rem;
      margin-bottom: 1rem; overflow-x: auto;
    }
    .ss-top-tab {
      flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.1rem;
      padding: 0.55rem 0.6rem; border-radius: 10px; border: none;
      background: transparent; cursor: pointer;
      font-size: 0.75rem; font-weight: 600; color: #64748b;
      transition: all 0.2s ease; white-space: nowrap; min-width: 0;
    }
    .ss-top-tab:hover { background: rgba(43, 76, 82,0.04); }
    .ss-top-tab.active { background: linear-gradient(135deg, #2B4C52, #4A7C84); color: white; }

    .ss-card {
      background: white; border: 1px solid #e8ecf1;
      border-radius: 16px; padding: 1.5rem; margin-bottom: 1.25rem;
    }
    .ss-card-header {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;
    }
    .ss-card-header h2 {
      font-size: 1rem; font-weight: 700; color: #1a1a2e;
      display: flex; align-items: center; gap: 0.5rem;
    }
    .ss-card-header h2 i { color: #2B4C52; }
    .ss-card-actions { display: flex; gap: 0.5rem; align-items: center; }

    .ss-toggle-wrapper {
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9;
    }
    .ss-toggle-wrapper:last-child { border-bottom: none; }
    .ss-toggle-info { flex: 1; }
    .ss-toggle-label { font-size: 0.88rem; font-weight: 600; color: #1a1a2e; }
    .ss-toggle-desc { font-size: 0.75rem; color: #64748b; margin-top: 0.2rem; }
    .ss-toggle {
      position: relative; width: 44px; height: 24px;
      background: #e2e8f0; border-radius: 999px; cursor: pointer;
      transition: background 0.3s ease; flex-shrink: 0;
    }
    .ss-toggle.active { background: linear-gradient(135deg, #2B4C52, #4A7C84); }
    .ss-toggle::after {
      content: ''; position: absolute; top: 2px; left: 2px;
      width: 20px; height: 20px; background: white;
      border-radius: 50%; transition: transform 0.3s ease;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .ss-toggle.active::after { transform: translateX(20px); }

    .form-group { margin-bottom: 1rem; }
    .form-group:last-child { margin-bottom: 0; }
    .form-label {
      display: block; font-size: 0.78rem; font-weight: 600;
      color: #475569; margin-bottom: 0.4rem;
    }
    .form-input, .form-select {
      width: 100%; padding: 0.6rem 0.85rem; border: 1.5px solid #e2e8f0;
      border-radius: 10px; font-size: 0.85rem; outline: none;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .form-input:focus, .form-select:focus {
      border-color: #2B4C52; box-shadow: 0 0 0 3px rgba(43, 76, 82,0.1);
    }
    .form-row {
      display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;
    }

    .ss-badge {
      display: inline-flex; align-items: center; gap: 0.3rem;
      padding: 0.2rem 0.6rem; border-radius: 999px;
      font-size: 0.7rem; font-weight: 700;
    }
    .ss-badge.success { background: rgba(5,150,105,0.12); color: #047857; }
    .ss-badge.warning { background: rgba(255,193,7,0.12); color: #b8860b; }
    .ss-badge.info { background: rgba(59,130,246,0.12); color: #1d4ed8; }

    @media (max-width: 992px) {
      .form-row { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
      .ss-top-tabs { flex-wrap: wrap; }
    }
  </style>

  <div class="ss-topbar">
    <div class="ss-topbar-actions">
      <button class="btn btn-outline" onclick="resetSettings()"><i class="fas fa-undo"></i> Reset</button>
      <button class="btn btn-primary" onclick="saveSettings()"><i class="fas fa-save"></i> Save Changes</button>
    </div>
  </div>

  <div class="ss-top-tabs" id="ss-top-tabs">
    <button class="ss-top-tab active" data-tab="payment">Payment</button>
    <button class="ss-top-tab" data-tab="chat">Chat</button>
    <button class="ss-top-tab" data-tab="notification">Notification</button>
  </div>

  <!-- ===== PAYMENT TAB ===== -->
  <div id="ss-content-payment" class="ss-top-content">
    <div class="ss-card">
      <div class="ss-card-header">
        <h2><i class="fas fa-credit-card"></i> Payment Methods</h2>
        <span class="ss-badge info"><i class="fas fa-info-circle"></i> Enable/disable payment options</span>
      </div>
      <div class="ss-toggle-wrapper">
        <div class="ss-toggle-info">
          <div class="ss-toggle-label">GCash</div>
          <div class="ss-toggle-desc">Accept payments via GCash</div>
        </div>
        <div class="ss-toggle active" data-key="payment.methods.gcash.enabled" onclick="toggleSwitch(this)"></div>
      </div>
      <div class="ss-toggle-wrapper">
        <div class="ss-toggle-info">
          <div class="ss-toggle-label">Credit / Debit Card</div>
          <div class="ss-toggle-desc">Accept credit and debit card payments</div>
        </div>
        <div class="ss-toggle active" data-key="payment.methods.credit_card.enabled" onclick="toggleSwitch(this)"></div>
      </div>
      <div class="ss-toggle-wrapper">
        <div class="ss-toggle-info">
          <div class="ss-toggle-label">Downpayment (50% Now / 50% Later)</div>
          <div class="ss-toggle-desc">Allow customers to pay 50% upfront and the balance later</div>
        </div>
        <div class="ss-toggle active" data-key="payment.methods.downpayment.enabled" onclick="toggleSwitch(this)"></div>
      </div>
      <div class="ss-toggle-wrapper">
        <div class="ss-toggle-info">
          <div class="ss-toggle-label">Cash on Delivery (COD)</div>
          <div class="ss-toggle-desc">Accept cash payments upon delivery</div>
        </div>
        <div class="ss-toggle active" data-key="payment.methods.cod.enabled" onclick="toggleSwitch(this)"></div>
      </div>
    </div>
    <div class="ss-card">
      <div class="ss-card-header">
        <h2><i class="fas fa-mobile-alt"></i> GCash Details</h2>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">GCash Account Number</label>
          <input type="text" class="form-input" data-key="payment.methods.gcash.account_number" placeholder="e.g. 09XX XXX XXXX">
        </div>
        <div class="form-group">
          <label class="form-label">GCash Account Name</label>
          <input type="text" class="form-input" data-key="payment.methods.gcash.account_name" placeholder="e.g. Juan Dela Cruz">
        </div>
      </div>
    </div>
    <div class="ss-card">
      <div class="ss-card-header">
        <h2><i class="fas fa-percent"></i> Downpayment Settings</h2>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Downpayment Percentage</label>
          <input type="number" class="form-input" data-key="payment.methods.downpayment.percentage" min="1" max="99" placeholder="50">
        </div>
      </div>
    </div>
    <div class="ss-card">
      <div class="ss-card-header">
        <h2><i class="fas fa-bolt"></i> PayMongo Online Payments</h2>
        <span class="ss-badge info"><i class="fas fa-info-circle"></i> Accept GCash & Credit Card via PayMongo</span>
      </div>
      <div class="ss-toggle-wrapper">
        <div class="ss-toggle-info">
          <div class="ss-toggle-label">Enable PayMongo</div>
          <div class="ss-toggle-desc">Allow customers to pay via GCash or Credit Card using PayMongo hosted checkout</div>
        </div>
        <div class="ss-toggle" data-key="payment.paymongo.enabled" onclick="toggleSwitch(this)"></div>
      </div>
      <div id="ss-paymongo-fields" style="margin-top:0.75rem;padding:0.75rem;background:#f8fafc;border-radius:10px;">
        <p style="font-size:0.75rem;color:#64748b;margin-bottom:0.75rem;line-height:1.6;">
          <i class="fas fa-lock" style="color:#2B4C52;"></i>
          Your API keys are stored securely and only used server-side. Get your keys from the 
          <a href="https://dashboard.paymongo.com" target="_blank" style="color:#2B4C52;font-weight:600;">PayMongo Dashboard</a>.
          Use <strong>test keys</strong> for development, <strong>live keys</strong> for production.
        </p>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Secret Key <span style="font-weight:400;color:#94a3b8;">(sk_xxx)</span></label>
            <input type="password" class="form-input" data-key="payment.paymongo.secret_key" placeholder="sk_test_xxx or sk_live_xxx">
          </div>
          <div class="form-group">
            <label class="form-label">Public Key <span style="font-weight:400;color:#94a3b8;">(pk_xxx)</span></label>
            <input type="password" class="form-input" data-key="payment.paymongo.public_key" placeholder="pk_test_xxx or pk_live_xxx">
          </div>
        </div>
        <div class="form-group" style="margin-top:0.5rem;">
          <label class="form-label">Webhook Secret <span style="font-weight:400;color:#94a3b8;">(whsec_xxx)</span></label>
          <input type="password" class="form-input" data-key="payment.paymongo.webhook_secret" placeholder="whsec_xxx">
          <p style="font-size:0.72rem;color:#94a3b8;margin-top:0.3rem;">
            Set this in your PayMongo Dashboard → Developers → Webhooks. 
            Webhook URL: <code style="background:#f1f5f9;padding:0.1rem 0.3rem;border-radius:4px;font-size:0.7rem;"><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/api/paymongo-webhook.php'; ?></code>
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== CHAT TAB ===== -->
  <div id="ss-content-chat" class="ss-top-content" style="display:none;">
    <div class="ss-card">
      <div class="ss-card-header">
        <h2><i class="fas fa-comments"></i> Chat Availability</h2>
      </div>
      <div class="ss-toggle-wrapper">
        <div class="ss-toggle-info">
          <div class="ss-toggle-label">Enable Customer Chat</div>
          <div class="ss-toggle-desc">Allow customers to start chat conversations with admins</div>
        </div>
        <div class="ss-toggle active" data-key="chat.enabled" onclick="toggleSwitch(this)"></div>
      </div>
      <div class="ss-toggle-wrapper">
        <div class="ss-toggle-info">
          <div class="ss-toggle-label">Enable Operating Hours</div>
          <div class="ss-toggle-desc">Restrict chat availability to business hours only</div>
        </div>
        <div class="ss-toggle" data-key="chat.operating_hours.enabled" onclick="toggleSwitch(this)"></div>
      </div>
      <div id="ss-oh-fields" class="form-row" style="margin-top:0.75rem;">
        <div class="form-group">
          <label class="form-label">Weekday Start</label>
          <input type="time" class="form-input" data-key="chat.operating_hours.weekday_start">
        </div>
        <div class="form-group">
          <label class="form-label">Weekday End</label>
          <input type="time" class="form-input" data-key="chat.operating_hours.weekday_end">
        </div>
      </div>
    </div>
    <div class="ss-card">
      <div class="ss-card-header">
        <h2><i class="fas fa-reply"></i> Auto-Response</h2>
      </div>
      <div class="ss-toggle-wrapper">
        <div class="ss-toggle-info">
          <div class="ss-toggle-label">Enable Auto-Response</div>
          <div class="ss-toggle-desc">Automatically send a welcome message when a new conversation starts</div>
        </div>
        <div class="ss-toggle" data-key="chat.auto_response.enabled" onclick="toggleSwitch(this)"></div>
      </div>
      <div class="form-group" style="margin-top:0.75rem;">
        <label class="form-label">Welcome Message</label>
        <textarea class="form-input" rows="3" data-key="chat.auto_response.message" placeholder="Enter auto-response message"></textarea>
      </div>
    </div>
    <div class="ss-card">
      <div class="ss-card-header">
        <h2><i class="fas fa-file-upload"></i> File Upload Settings</h2>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Max File Size (MB)</label>
          <input type="number" class="form-input" data-key="chat.file_upload.max_size_mb" min="1" max="100" placeholder="25">
        </div>
        <div class="form-group">
          <label class="form-label">Allowed File Types</label>
          <input type="text" class="form-input" data-key="chat.file_upload.allowed_types" placeholder="jpg,jpeg,png,pdf">
        </div>
      </div>
    </div>
  </div>

  <!-- ===== NOTIFICATION TAB ===== -->
  <div id="ss-content-notification" class="ss-top-content" style="display:none;">
    <div class="ss-card">
      <div class="ss-card-header">
        <h2><i class="fas fa-bell"></i> In-App Notification Events</h2>
        <span class="ss-badge info"><i class="fas fa-info-circle"></i> Toggle which events trigger notifications</span>
      </div>
      <div class="ss-toggle-wrapper">
        <div class="ss-toggle-info">
          <div class="ss-toggle-label">New Order</div>
          <div class="ss-toggle-desc">When a customer places a new order</div>
        </div>
        <div class="ss-toggle active" data-key="notification.in_app.new_order" onclick="toggleSwitch(this)"></div>
      </div>
      <div class="ss-toggle-wrapper">
        <div class="ss-toggle-info">
          <div class="ss-toggle-label">Order Update</div>
          <div class="ss-toggle-desc">When order status is changed</div>
        </div>
        <div class="ss-toggle active" data-key="notification.in_app.order_update" onclick="toggleSwitch(this)"></div>
      </div>
      <div class="ss-toggle-wrapper">
        <div class="ss-toggle-info">
          <div class="ss-toggle-label">Payment Update</div>
          <div class="ss-toggle-desc">When payment status changes (paid/failed/refunded)</div>
        </div>
        <div class="ss-toggle active" data-key="notification.in_app.payment_update" onclick="toggleSwitch(this)"></div>
      </div>
      <div class="ss-toggle-wrapper">
        <div class="ss-toggle-info">
          <div class="ss-toggle-label">Chat Message</div>
          <div class="ss-toggle-desc">When a new chat message is received</div>
        </div>
        <div class="ss-toggle active" data-key="notification.in_app.chat_message" onclick="toggleSwitch(this)"></div>
      </div>
      <div class="ss-toggle-wrapper">
        <div class="ss-toggle-info">
          <div class="ss-toggle-label">Custom Request</div>
          <div class="ss-toggle-desc">When a custom printing request is submitted</div>
        </div>
        <div class="ss-toggle active" data-key="notification.in_app.custom_request" onclick="toggleSwitch(this)"></div>
      </div>
    </div>
    <div class="ss-card">
      <div class="ss-card-header">
        <h2><i class="fas fa-clock"></i> Retention</h2>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Keep Notifications For (days)</label>
          <input type="number" class="form-input" data-key="notification.retention_days" min="1" max="365" placeholder="90">
        </div>
      </div>
    </div>
  </div>

<script>
let settings = {};

// === HELPERS ===

function toggleSwitch(el) {
  el.classList.toggle('active');
  updateConditionalVisibility();
}

function getVal(obj, path) {
  return path.split('.').reduce((o, k) => (o && o[k] !== undefined) ? o[k] : null, obj);
}

function setVal(obj, path, val) {
  const keys = path.split('.');
  keys.slice(0, -1).reduce((o, k) => { if (!(k in o)) o[k] = {}; return o[k]; }, obj)[keys[keys.length-1]] = val;
}

function collect() {
  document.querySelectorAll('[data-key]').forEach(el => {
    const key = el.dataset.key;
    let val;
    if (el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') val = el.value;
    else if (el.type === 'number') val = parseFloat(el.value) || 0;
    else if (el.type === 'time') val = el.value;
    else val = el.value;
    setVal(settings, key, val);
  });
  document.querySelectorAll('.ss-toggle[data-key]').forEach(el => {
    setVal(settings, el.dataset.key, el.classList.contains('active'));
  });
}

function apply() {
  document.querySelectorAll('[data-key]').forEach(el => {
    const key = el.dataset.key;
    const val = getVal(settings, key);
    if (val === null) return;
    if (el.tagName === 'SELECT') {
      const opt = el.querySelector(`option[value="${val}"]`);
      if (opt) el.value = val;
    } else if (el.type === 'number') el.value = val;
    else if (el.type === 'time') el.value = val;
    else el.value = val;
  });
  document.querySelectorAll('.ss-toggle[data-key]').forEach(el => {
    const val = getVal(settings, el.dataset.key);
    el.classList.toggle('active', !!val);
  });
  updateConditionalVisibility();
}

function updateConditionalVisibility() {
  var ohToggle = document.querySelector('.ss-toggle[data-key="chat.operating_hours.enabled"]');
  var ohFields = document.getElementById('ss-oh-fields');
  if (ohFields) ohFields.style.display = ohToggle && ohToggle.classList.contains('active') ? '' : 'none';
  var pmToggle = document.querySelector('.ss-toggle[data-key="payment.paymongo.enabled"]');
  var pmFields = document.getElementById('ss-paymongo-fields');
  if (pmFields) pmFields.style.display = pmToggle && pmToggle.classList.contains('active') ? '' : 'none';
}

// === SAVE / LOAD ===

async function loadSettings() {
  try {
    const res = await fetch('../api/get-shipping.php');
    const json = await res.json();
    if (json.success && json.settings) {
      settings = json.settings;
      apply();
    } else {
      document.querySelector('.ss-topbar').insertAdjacentHTML('afterbegin', '<div style="background:#fef2f2;color:#dc2626;padding:0.5rem 1rem;border-radius:8px;font-size:0.78rem;margin-bottom:0.75rem;"><i class="fas fa-exclamation-triangle"></i> Failed to load settings: ' + (json.error || 'Unknown') + '</div>');
    }
  } catch (e) {
    document.querySelector('.ss-topbar').insertAdjacentHTML('afterbegin', '<div style="background:#fef2f2;color:#dc2626;padding:0.5rem 1rem;border-radius:8px;font-size:0.78rem;margin-bottom:0.75rem;"><i class="fas fa-exclamation-triangle"></i> Could not load settings. Check your connection.</div>');
  }
}

async function saveSettings() {
  const btn = document.querySelector('.btn-primary');
  const orig = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  btn.disabled = true;

  collect();

  try {
    const res = await fetch('../api/save-shipping.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ settings })
    });
    const json = await res.json();
    if (json.success) {
      btn.innerHTML = '<i class="fas fa-check"></i> Saved!';
      btn.style.background = 'linear-gradient(135deg, #059669, #10b981)';
    } else {
      alert('Save failed: ' + (json.error || 'Unknown error'));
      btn.innerHTML = orig;
    }
  } catch (e) {
    alert('Save failed: ' + e.message);
    btn.innerHTML = orig;
  }

  setTimeout(() => {
    btn.innerHTML = orig;
    btn.style.background = '';
    btn.disabled = false;
  }, 2000);
}

async function resetSettings() {
  if (!confirm('Reset all settings to default?')) return;
  var btn = document.querySelector('.btn-outline');
  var orig = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  btn.disabled = true;
  try {
    var res = await fetch('../api/save-shipping.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ settings: {} })
    });
    var json = await res.json();
    if (json.success) {
      location.reload();
    } else {
      alert('Reset failed: ' + (json.error || 'Unknown error'));
    }
  } catch (e) {
    alert('Reset failed: ' + e.message);
  }
  btn.innerHTML = orig;
  btn.disabled = false;
}

// === TAB SWITCHING ===

document.querySelectorAll('.ss-top-tab').forEach(tab => {
  tab.addEventListener('click', function() {
    document.querySelectorAll('.ss-top-tab').forEach(t => t.classList.remove('active'));
    this.classList.add('active');
    document.querySelectorAll('.ss-top-content').forEach(c => c.style.display = 'none');
    const el = document.getElementById('ss-content-' + this.dataset.tab);
    if (el) el.style.display = 'block';
  });
});

// === INIT ===
document.addEventListener('DOMContentLoaded', function() {
  loadSettings();
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

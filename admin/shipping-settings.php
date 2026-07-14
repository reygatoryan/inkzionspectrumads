<?php
$pageTitle = 'Shipping Settings';
$pageSubtitle = 'Manage shipping channels, pickup hours, and delivery options';
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

    .ss-subtabs {
      display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap;
    }
    .ss-subtab {
      padding: 0.4rem 0.85rem; border-radius: 8px;
      border: 1px solid #e2e8f0; background: white;
      font-size: 0.75rem; font-weight: 600; color: #475569;
      cursor: pointer; transition: all 0.15s ease;
    }
    .ss-subtab:hover { border-color: #2B4C52; color: #2B4C52; }
    .ss-subtab.active { background: rgba(43, 76, 82,0.08); border-color: #2B4C52; color: #2B4C52; }

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

    .ss-collapsible {
      background: #f8fafc; border: 1px solid #e8ecf1;
      border-radius: 12px; margin-bottom: 0.75rem; overflow: hidden;
    }
    .ss-collapsible-header {
      display: flex; justify-content: space-between; align-items: center;
      padding: 1rem 1.25rem; cursor: pointer; transition: background 0.2s ease;
    }
    .ss-collapsible-header:hover { background: #f1f5f9; }
    .ss-collapsible-title {
      font-size: 0.88rem; font-weight: 700; color: #1a1a2e;
      display: flex; align-items: center; gap: 0.5rem;
    }
    .ss-collapsible-title i { color: #2B4C52; }
    .ss-collapsible-icon {
      width: 24px; height: 24px; border-radius: 6px;
      background: white; display: flex; align-items: center; justify-content: center;
      font-size: 0.7rem; color: #64748b; transition: transform 0.3s ease;
    }
    .ss-collapsible.open .ss-collapsible-icon { transform: rotate(180deg); }
    .ss-collapsible-body {
      max-height: 0; overflow: hidden; transition: max-height 0.3s ease;
    }
    .ss-collapsible.open .ss-collapsible-body { max-height: 1000px; }
    .ss-collapsible-content { padding: 1rem 1.25rem; border-top: 1px solid #e8ecf1; }

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

    .ss-address-card {
      background: white; border: 1px solid #e8ecf1;
      border-radius: 12px; padding: 1rem; margin-bottom: 0.75rem;
      transition: border-color 0.2s ease;
    }
    .ss-address-card.editing { border-color: #2B4C52; box-shadow: 0 0 0 2px rgba(43, 76, 82,0.1); }
    .ss-address-header {
      display: flex; justify-content: space-between; align-items: flex-start;
      margin-bottom: 0.5rem;
    }
    .ss-address-label {
      font-size: 0.85rem; font-weight: 700; color: #1a1a2e;
      display: flex; align-items: center; gap: 0.5rem;
    }
    .ss-address-label i { color: #2B4C52; }
    .ss-address-text {
      font-size: 0.82rem; color: #475569; line-height: 1.6;
    }
    .ss-address-actions { display: flex; gap: 0.35rem; flex-shrink: 0; flex-wrap: wrap; }

    .ss-badge {
      display: inline-flex; align-items: center; gap: 0.3rem;
      padding: 0.2rem 0.6rem; border-radius: 999px;
      font-size: 0.7rem; font-weight: 700;
    }
    .ss-badge.success { background: rgba(5,150,105,0.12); color: #047857; }
    .ss-badge.warning { background: rgba(255,193,7,0.12); color: #b8860b; }
    .ss-badge.info { background: rgba(59,130,246,0.12); color: #1d4ed8; }

    .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; }

    @media (max-width: 992px) {
      .form-row, .form-row-3 { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
      .ss-top-tabs { flex-wrap: wrap; }
      .ss-subtabs { flex-wrap: wrap; }
    }
  </style>

  <div class="ss-topbar">
    <div class="ss-topbar-actions">
      <button class="btn btn-outline" onclick="resetSettings()"><i class="fas fa-undo"></i> Reset</button>
      <button class="btn btn-primary" onclick="saveSettings()"><i class="fas fa-save"></i> Save Changes</button>
    </div>
  </div>

  <div class="ss-top-tabs" id="ss-top-tabs">
    <button class="ss-top-tab active" data-tab="shipping">Shipping</button>
    <button class="ss-top-tab" data-tab="payment">Payment</button>
    <button class="ss-top-tab" data-tab="chat">Chat</button>
    <button class="ss-top-tab" data-tab="notification">Notification</button>
  </div>

  <div id="ss-content-shipping" class="ss-top-content">
  <div class="ss-subtabs" id="ss-subtabs">
    <button class="ss-subtab active" data-subtab="channels">Shipping Channel</button>
    <button class="ss-subtab" data-subtab="address">Address Management</button>
    <button class="ss-subtab" data-subtab="hours">Pickup Operating Hours</button>
  </div>

  <!-- ===== SHIPPING CHANNELS ===== -->
  <div id="shipping-channels-content">
    <div class="ss-card">
      <div class="ss-card-header">
        <h2><i class="fas fa-truck"></i> Standard Local</h2>
        <div class="ss-card-actions">
          <span class="ss-badge success"><i class="fas fa-check-circle"></i> Active</span>
        </div>
      </div>

      <div class="ss-toggle-wrapper">
        <div class="ss-toggle-info">
          <div class="ss-toggle-label">Enable Standard Local Shipping</div>
          <div class="ss-toggle-desc">Allow customers to choose standard local delivery</div>
        </div>
        <div class="ss-toggle" data-key="couriers.jnt.enabled" onclick="toggleSwitch(this)"></div>
      </div>

      <div class="ss-toggle-wrapper">
        <div class="ss-toggle-info">
          <div class="ss-toggle-label">Cash on Delivery (COD)</div>
          <div class="ss-toggle-desc">Accept cash payments upon delivery</div>
        </div>
        <div class="ss-toggle active" data-key="cod_enabled" onclick="toggleSwitch(this)"></div>
      </div>

      <div class="ss-collapsible open">
        <div class="ss-collapsible-header" onclick="toggleCollapsible(this)">
          <div class="ss-collapsible-title"><i class="fas fa-box"></i> J&amp;T Express</div>
          <div class="ss-collapsible-icon"><i class="fas fa-chevron-down"></i></div>
        </div>
        <div class="ss-collapsible-body">
          <div class="ss-collapsible-content">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Account Number</label>
                <input type="text" class="form-input" data-key="couriers.jnt.account_number" placeholder="Enter J&T account number">
              </div>
              <div class="form-group">
                <label class="form-label">Default Shipping Fee (&#8369;)</label>
                <input type="number" class="form-input" data-key="couriers.jnt.default_fee" placeholder="0">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Pickup Address</label>
              <select class="form-select" data-key="couriers.jnt.pickup_address"></select>
            </div>
            <div class="ss-toggle-wrapper">
              <div class="ss-toggle-info">
                <div class="ss-toggle-label">Enable Same-Day Pickup</div>
                <div class="ss-toggle-desc">Allow courier to pick up orders on the same day</div>
              </div>
              <div class="ss-toggle" data-key="couriers.jnt.same_day_pickup" onclick="toggleSwitch(this)"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="ss-collapsible">
        <div class="ss-collapsible-header" onclick="toggleCollapsible(this)">
          <div class="ss-collapsible-title"><i class="fas fa-store"></i> Store Pickup</div>
          <div class="ss-collapsible-icon"><i class="fas fa-chevron-down"></i></div>
        </div>
        <div class="ss-collapsible-body">
          <div class="ss-collapsible-content">
            <div class="ss-toggle-wrapper">
              <div class="ss-toggle-info">
                <div class="ss-toggle-label">Enable Store Pickup</div>
                <div class="ss-toggle-desc">Allow customers to pick up orders from your store</div>
              </div>
              <div class="ss-toggle" data-key="couriers.store_pickup.enabled" onclick="toggleSwitch(this)"></div>
            </div>
            <div class="form-group" style="margin-top:1rem;">
              <label class="form-label">Pickup Address</label>
              <textarea class="form-input" rows="2" data-key="couriers.store_pickup.address" placeholder="Enter store pickup address"></textarea>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Pickup Hours Start</label>
                <input type="time" class="form-input" data-key="couriers.store_pickup.hours_start">
              </div>
              <div class="form-group">
                <label class="form-label">Pickup Hours End</label>
                <input type="time" class="form-input" data-key="couriers.store_pickup.hours_end">
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== ADDRESS MANAGEMENT ===== -->
  <div id="address-management-content" style="display:none;">
    <div class="ss-card">
      <div class="ss-card-header">
        <h2><i class="fas fa-map-marker-alt"></i> Pickup Addresses</h2>
        <button class="btn btn-primary" onclick="addAddress()"><i class="fas fa-plus"></i> Add Address</button>
      </div>
      <div id="address-list"></div>
    </div>
  </div>

  <!-- ===== PICKUP OPERATING HOURS ===== -->
  <div id="pickup-hours-content" style="display:none;">
    <div class="ss-card">
      <div class="ss-card-header">
        <h2><i class="fas fa-clock"></i> Operating Hours</h2>
        <span class="ss-badge info"><i class="fas fa-info-circle"></i> Regular Schedule</span>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Monday - Friday</label>
          <div class="form-row" style="gap:0.5rem;">
            <input type="time" class="form-input" data-key="hours.weekday_start">
            <span style="align-self:center;color:#64748b;">to</span>
            <input type="time" class="form-input" data-key="hours.weekday_end">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Saturday</label>
          <div class="form-row" style="gap:0.5rem;">
            <input type="time" class="form-input" data-key="hours.saturday_start">
            <span style="align-self:center;color:#64748b;">to</span>
            <input type="time" class="form-input" data-key="hours.saturday_end">
          </div>
        </div>
      </div>
    </div>
    <div class="ss-card">
      <div class="ss-card-header">
        <h2><i class="fas fa-calendar-alt"></i> Holiday Schedule</h2>
      </div>
      <div style="font-size:0.85rem;color:#475569;line-height:1.8;">
        <p><strong>No pickups on:</strong></p>
        <ul style="margin-left:1.5rem;margin-top:0.5rem;">
          <li>January 1 &mdash; New Year's Day</li>
          <li>April 9-10 &mdash; Maundy Thursday &amp; Good Friday</li>
          <li>May 1 &mdash; Labor Day</li>
          <li>June 12 &mdash; Independence Day</li>
          <li>December 25 &mdash; Christmas Day</li>
          <li>December 30 &mdash; Rizal Day</li>
        </ul>
      </div>
    </div>
  </div>
  </div>

  <!-- ===== PAYMENT TAB ===== -->
  <div id="ss-content-payment" class="ss-top-content" style="display:none;">
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
      <div class="form-row" style="margin-top:0.75rem;">
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
          <input type="number" class="form-input" data-key="chat.file_upload.max_size_mb" min="1" max="100" placeholder="10">
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
let addressNextId = 100;

// === HELPERS ===

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
}

function fillAddressSelect() {
  document.querySelectorAll('[data-key="couriers.jnt.pickup_address"]').forEach(sel => {
    const current = sel.value;
    sel.innerHTML = '';
    (settings.addresses || []).forEach(a => {
      const text = a.label + ' - ' + a.street + ', ' + a.city;
      const opt = document.createElement('option');
      opt.value = text;
      opt.textContent = text;
      sel.appendChild(opt);
    });
    if (current) sel.value = current;
  });
}

function renderAddresses() {
  const list = document.getElementById('address-list');
  list.innerHTML = '';
  (settings.addresses || []).forEach((a, i) => {
    const card = document.createElement('div');
    card.className = 'ss-address-card' + (a._editing ? ' editing' : '');
    card.dataset.index = i;

    if (a._editing) {
      card.innerHTML = `
        <div class="form-row" style="margin-bottom:0.75rem;">
          <div class="form-group">
            <label class="form-label">Label</label>
            <input type="text" class="form-input addr-label" value="${esc(a.label)}">
          </div>
          <div class="form-group">
            <label class="form-label">Contact</label>
            <input type="text" class="form-input addr-contact" value="${esc(a.contact)}">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Name / Location</label>
          <input type="text" class="form-input addr-name" value="${esc(a.name)}">
        </div>
        <div class="form-group">
          <label class="form-label">Street</label>
          <input type="text" class="form-input addr-street" value="${esc(a.street)}">
        </div>
        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">City</label>
            <input type="text" class="form-input addr-city" value="${esc(a.city)}">
          </div>
          <div class="form-group">
            <label class="form-label">Province</label>
            <input type="text" class="form-input addr-province" value="${esc(a.province)}">
          </div>
          <div class="form-group">
            <label class="form-label">ZIP</label>
            <input type="text" class="form-input addr-zip" value="${esc(a.zip)}">
          </div>
        </div>
        <div style="display:flex;gap:0.5rem;margin-top:0.75rem;">
          <button class="btn btn-success btn-sm" onclick="saveAddress(this)"><i class="fas fa-check"></i> Done</button>
          <button class="btn btn-outline btn-sm" onclick="cancelAddress(this)"><i class="fas fa-times"></i> Cancel</button>
        </div>
      `;
    } else {
      const isDefault = a.is_default;
      card.innerHTML = `
        <div class="ss-address-header">
          <div class="ss-address-label">
            <i class="fas ${isDefault ? 'fa-check-circle' : 'fa-map-marker-alt'}" style="color:${isDefault ? '#059669' : '#2B4C52'};"></i>
            ${esc(a.label)}
            ${isDefault ? '<span class="ss-badge success">Default</span>' : ''}
          </div>
          <div class="ss-address-actions">
            <button class="btn btn-outline btn-sm" onclick="editAddress(this)"><i class="fas fa-edit"></i></button>
            <button class="btn btn-danger btn-sm" onclick="deleteAddress(this)"><i class="fas fa-trash"></i></button>
          </div>
        </div>
        <div class="ss-address-text">
          ${esc(a.name)}<br>
          ${esc(a.street)}<br>
          ${esc(a.city)}, ${esc(a.province)} ${esc(a.zip)}<br>
          ${esc(a.country)}<br>
          <strong>Contact:</strong> ${esc(a.contact)}
        </div>
      `;
    }
    list.appendChild(card);
  });
  fillAddressSelect();
}

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// === ADDRESS CRUD ===

function addAddress() {
  settings.addresses = settings.addresses || [];
  settings.addresses.push({
    id: addressNextId++,
    label: 'New Address',
    is_default: false,
    name: '',
    street: '',
    city: '',
    province: '',
    zip: '',
    country: 'Philippines',
    contact: '',
    _editing: true
  });
  renderAddresses();
}

function editAddress(btn) {
  const card = btn.closest('.ss-address-card');
  const idx = parseInt(card.dataset.index);
  settings.addresses[idx]._editing = true;
  renderAddresses();
}

function saveAddress(btn) {
  const card = btn.closest('.ss-address-card');
  const idx = parseInt(card.dataset.index);
  const a = settings.addresses[idx];
  a.label = card.querySelector('.addr-label').value;
  a.contact = card.querySelector('.addr-contact').value;
  a.name = card.querySelector('.addr-name').value;
  a.street = card.querySelector('.addr-street').value;
  a.city = card.querySelector('.addr-city').value;
  a.province = card.querySelector('.addr-province').value;
  a.zip = card.querySelector('.addr-zip').value;
  delete a._editing;
  renderAddresses();
}

function cancelAddress(btn) {
  const card = btn.closest('.ss-address-card');
  const idx = parseInt(card.dataset.index);
  const a = settings.addresses[idx];
  if (!a.name && !a.street) {
    settings.addresses.splice(idx, 1);
  } else {
    delete a._editing;
  }
  renderAddresses();
}

function deleteAddress(btn) {
  if (!confirm('Delete this address?')) return;
  const card = btn.closest('.ss-address-card');
  const idx = parseInt(card.dataset.index);
  settings.addresses.splice(idx, 1);
  renderAddresses();
}

// === TOGGLES & COLLAPSIBLE ===

function toggleSwitch(el) {
  el.classList.toggle('active');
}

function toggleCollapsible(header) {
  header.parentElement.classList.toggle('open');
}

// === SAVE / LOAD ===

async function loadSettings() {
  try {
    const res = await fetch('../api/get-shipping.php');
    const json = await res.json();
    if (json.success && json.settings) {
      settings = json.settings;
      if (!settings.cod_enabled) settings.cod_enabled = true;
      addressNextId = (settings.addresses || []).reduce((m, a) => Math.max(m, a.id || 0), 0) + 1;
      apply();
      renderAddresses();
    }
  } catch (e) {
    console.error('Failed to load shipping settings', e);
  }
}

async function saveSettings() {
  const btn = document.querySelector('.btn-primary');
  const orig = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  btn.disabled = true;

  collect();
  settings.addresses = (settings.addresses || []).map(a => { const { _editing, ...rest } = a; return rest; });

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

function resetSettings() {
  if (confirm('Reset all shipping settings to default?')) {
    location.reload();
  }
}

// === TAB SWITCHING ===

document.querySelectorAll('.ss-top-tab').forEach(tab => {
  tab.addEventListener('click', function() {
    document.querySelectorAll('.ss-top-tab').forEach(t => t.classList.remove('active'));
    this.classList.add('active');
    document.querySelectorAll('.ss-top-content').forEach(c => c.style.display = 'none');
    const el = document.getElementById('ss-content-' + this.dataset.tab);
    if (el) {
      el.style.display = 'block';
      if (this.dataset.tab === 'shipping') {
        const activeSub = el.querySelector('.ss-subtab.active');
        if (activeSub) activeSub.click();
      }
    }
  });
});

document.querySelectorAll('.ss-subtab').forEach(subtab => {
  subtab.addEventListener('click', function() {
    document.querySelectorAll('.ss-subtab').forEach(t => t.classList.remove('active'));
    this.classList.add('active');
    ['shipping-channels-content','address-management-content','pickup-hours-content'].forEach(id => {
      document.getElementById(id).style.display = 'none';
    });
    const map = { channels: 'shipping-channels-content', address: 'address-management-content', hours: 'pickup-hours-content' };
    const el = document.getElementById(map[this.dataset.subtab]);
    if (el) el.style.display = 'block';
  });
});

// === INIT ===
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.ss-collapsible').forEach((c, i) => { if (i === 0) c.classList.add('open'); });
  loadSettings();
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

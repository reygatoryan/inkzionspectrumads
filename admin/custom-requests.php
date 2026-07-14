<?php
$pageTitle = 'Customization Requests';
$pageSubtitle = 'Review and manage custom printing requests from customers';
require 'includes/admin-header.php';
// Ensure $adminId is set even if $userId is not defined by header
if (isset($userId)) {
  $adminId = $userId;
} elseif (isset($user) && isset($user['id'])) {
  $adminId = $user['id'];
} elseif (isset($_SESSION) && isset($_SESSION['user_id'])) {
  $adminId = $_SESSION['user_id'];
} else {
  $adminId = null; // fallback: no authenticated user id available
}
?>
<style>
    .cr-table { width: 100%; border-collapse: collapse; }
    .cr-table th { text-align: left; padding: 0.85rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .cr-table td { padding: 0.85rem 1rem; font-size: 0.88rem; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
    .cr-table tr:hover td { background: #f8fafc; }
    .cr-table tr.cr-selected td { background: rgba(43, 76, 82,0.04); }
    .cr-badge { display: inline-flex; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
    .cr-badge-pending { background: rgba(245,158,11,0.12); color: #b8860b; }
    .cr-badge-in_review { background: rgba(59,130,246,0.12); color: #1d4ed8; }
    .cr-badge-approved { background: rgba(16,185,129,0.12); color: #047857; }
    .cr-badge-ready_for_purchase { background: rgba(43, 76, 82,0.12); color: #3D5C42; }
    .cr-badge-rejected { background: rgba(239,68,68,0.12); color: #dc2626; }
    .cr-badge-completed { background: rgba(100,116,139,0.12); color: #475569; }
    .cr-empty { text-align: center; padding: 3rem; color: #94a3b8; }
    .cr-empty i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.3; }
    .cr-table-wrap { background: white; border-radius: 16px; box-shadow: 0 4px 16px rgba(0,0,0,0.04); overflow: hidden; }
    .cr-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 1rem; }
    .cr-modal-overlay.active { display: flex; }
    .cr-modal { background: white; border-radius: 20px; max-width: 700px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 2rem; box-shadow: 0 24px 64px rgba(0,0,0,0.2); }
    .cr-modal h2 { margin: 0 0 1.5rem; font-size: 1.3rem; color: #0f172a; }
    .cr-modal-close { float: right; background: none; border: none; font-size: 1.3rem; color: #94a3b8; cursor: pointer; }
    .cr-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.5rem; }
    .cr-detail-field label { display: block; font-size: 0.75rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.2rem; }
    .cr-detail-field span { font-size: 0.9rem; color: #1e293b; }
    .cr-files-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 0.5rem; margin-bottom: 1.5rem; }
    .cr-file-thumb { aspect-ratio: 1; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; cursor: pointer; }
    .cr-file-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .cr-form-group { margin-bottom: 1rem; }
    .cr-form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem; }
    .cr-form-group input, .cr-form-group select, .cr-form-group textarea { width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 0.9rem; outline: none; transition: border-color 0.2s; box-sizing: border-box; }
    .cr-form-group input:focus, .cr-form-group select:focus, .cr-form-group textarea:focus { border-color: #2B4C52; }
    .cr-form-group textarea { min-height: 80px; resize: vertical; }
    .cr-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .cr-chat-box { border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 1.5rem; overflow: hidden; }
    .cr-chat-header { padding: 0.75rem 1rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 600; font-size: 0.85rem; color: #1e293b; display: flex; justify-content: space-between; align-items: center; }
    .cr-chat-msgs { max-height: 250px; overflow-y: auto; padding: 0.75rem 1rem; display: flex; flex-direction: column; gap: 0.5rem; }
    .cr-msg { max-width: 80%; padding: 0.6rem 0.85rem; border-radius: 12px; font-size: 0.82rem; line-height: 1.4; }
    .cr-msg.sent { align-self: flex-end; background: linear-gradient(135deg, #2B4C52, #4A7C84); color: white; border-bottom-right-radius: 4px; }
    .cr-msg.received { align-self: flex-start; background: #f1f5f9; color: #1e293b; border-bottom-left-radius: 4px; }
    .cr-msg-time { font-size: 0.65rem; color: #94a3b8; margin-top: 0.2rem; }
    .cr-chat-input { display: flex; gap: 0.5rem; padding: 0.75rem 1rem; border-top: 1px solid #e2e8f0; }
    .cr-chat-input input { flex: 1; padding: 0.55rem 0.85rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; outline: none; }
    .cr-chat-input input:focus { border-color: #2B4C52; }
    .cr-chat-input button { padding: 0.55rem 1rem; border-radius: 8px; border: none; background: linear-gradient(135deg, #2B4C52, #4A7C84); color: white; font-weight: 600; cursor: pointer; font-size: 0.82rem; }
    .ai-image-wrap { flex: 0 0 48px; width: 48px; height: 48px; flex-shrink: 0; position: relative; }
    .ai-image-btn { width: 48px; height: 48px; border-radius: 6px; border: 1.5px dashed #d1d5db; background: white; color: #94a3b8; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: border-color 0.2s, color 0.2s, background 0.2s; }
    .ai-image-btn:hover { border-color: #2B4C52; color: #2B4C52; background: rgba(43, 76, 82,0.04); }
    .ai-image-preview { width: 48px; height: 48px; border-radius: 6px; object-fit: cover; cursor: pointer; border: 1px solid #e2e8f0; transition: border-color 0.2s; display: block; }
    .ai-image-preview:hover { border-color: #2B4C52; box-shadow: 0 0 0 3px rgba(43, 76, 82,0.1); }
    .admin-item-row { display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem; padding: 0.65rem; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; }
    .admin-item-row:focus-within { border-color: #2B4C52; }
    .ai-size { padding: 0.45rem 0.65rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.82rem; outline: none; font-family: inherit; background: white; min-width: 110px; flex:2; color: #1e293b; }
    .ai-size:focus { border-color: #2B4C52; }
    .admin-item-row .ai-qty-stepper { display: inline-flex; align-items: center; gap: 0; flex:1; min-width: 90px; }
    .admin-item-row .ai-qty-stepper .ai-qty-btn { width: 30px; height: 30px; border: 1.5px solid #e2e8f0; background: white; color: #1e293b; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 600; transition: all 0.15s; user-select: none; }
    .admin-item-row .ai-qty-stepper .ai-qty-btn:first-child { border-radius: 6px 0 0 6px; }
    .admin-item-row .ai-qty-stepper .ai-qty-btn:last-child { border-radius: 0 6px 6px 0; }
    .admin-item-row .ai-qty-stepper .ai-qty-btn:hover { background: rgba(43,76,82,0.08); border-color: #2B4C52; color: #2B4C52; }
    .admin-item-row .ai-qty-stepper .ai-qty-value { width: 40px; height: 30px; border: 1.5px solid #e2e8f0; border-left: none; border-right: none; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; color: #1e293b; background: white; }
    .admin-item-row .ai-remove { padding: 0.25rem 0.65rem; border-radius: 999px; border: none; background: rgba(239,68,68,0.08); color: #ef4444; cursor: pointer; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.3rem; transition: all 0.2s; font-family: inherit; white-space: nowrap; flex-shrink: 0; }
    .admin-item-row .ai-remove:hover { background: rgba(220,38,38,0.15); color: #dc2626; }
    @media (max-width: 768px) { .cr-detail-grid { grid-template-columns: 1fr; } .cr-form-row { grid-template-columns: 1fr; } }
</style>

<div class="tabs" id="filterBar">
      <button class="tab active" data-status="all">All</button>
      <button class="tab" data-status="pending">Pending</button>
      <button class="tab" data-status="in_review">In Review</button>
      <button class="tab" data-status="approved">Approved</button>
      <button class="tab" data-status="ready_for_purchase">Ready to Buy</button>
      <button class="tab" data-status="rejected">Rejected</button>
      <button class="tab" data-status="completed">Completed</button>
    </div>

    <div class="cr-table-wrap">
      <table class="cr-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Service</th>
            <th>Status</th>
            <th>Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="requestsTableBody">
          <tr><td colspan="6" class="cr-empty"><i class="fas fa-spinner fa-spin"></i><br>Loading...</td></tr>
        </tbody>
      </table>
    </div>

<!-- Detail Modal -->
<div class="cr-modal-overlay" id="detailModal">
  <div class="cr-modal">
    <button class="cr-modal-close" onclick="closeDetail()">&times;</button>
    <h2 id="detailTitle">Request Details</h2>

    <div class="cr-detail-grid" id="detailFields"></div>

    <div id="detailFiles"></div>

    <!-- Chat -->
    <div class="cr-chat-box" id="chatBox" style="display:none;">
      <div class="cr-chat-header">
        <span><i class="fas fa-comments"></i> Chat with Customer</span>
        <span><a href="chat.php" id="openInChatLink" style="font-size:0.75rem;color:#2B4C52;text-decoration:none;font-weight:600;display:none;" target="_blank"><i class="fas fa-external-link-alt"></i> Open in Chat</a></span>
      </div>
      <div class="cr-chat-msgs" id="chatMessages"></div>
      <div class="cr-chat-input">
        <input type="text" id="chatInput" placeholder="Type a message..." onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendChatMsg();}">
        <button onclick="sendChatMsg()"><i class="fas fa-paper-plane"></i> Send</button>
      </div>
    </div>

    <!-- Customization Details -->
    <h3 style="margin: 1.5rem 0 0.75rem; font-size: 1rem; color: #0f172a;">Customization Details</h3>
    <div class="cr-form-row">
      <div class="cr-form-group">
        <label>Material</label>
        <input type="text" id="editMaterial" placeholder="e.g., Cotton, Polyester, Gildan 5000">
      </div>
      <div class="cr-form-group">
        <label>Preferred Deadline</label>
        <input type="date" id="editDeadline">
      </div>
    </div>
    <div class="cr-form-group">
      <label>Order Items <span style="font-weight:400;color:#94a3b8;">(size, qty &amp; reference image)</span></label>
      <div id="admin-items-container">
        <div class="admin-item-row">
          <input type="text" class="ai-size" list="admin-size-list" placeholder="e.g., M, A4, Letter, Small, 3x5ft, etc.">
          <div class="ai-qty-stepper">
            <button type="button" class="ai-qty-btn" data-action="dec">&minus;</button>
            <span class="ai-qty-value">1</span>
            <button type="button" class="ai-qty-btn" data-action="inc">+</button>
          </div>
          <div class="ai-image-wrap">
            <input type="file" class="ai-image-input" accept="image/*" style="display:none;">
            <div class="ai-image-btn" onclick="this.previousElementSibling.click()" title="Upload reference image">
              <i class="fas fa-camera"></i>
            </div>
            <img class="ai-image-preview" style="display:none;" onclick="openItemPreview(this)">
          </div>
          <button type="button" class="ai-remove" onclick="adminRemoveItem(this)" style="display:none;" title="Remove"><i class="fas fa-times"></i> Remove</button>
        </div>
      </div>
      <button type="button" onclick="adminAddItem()" style="margin-top:0.5rem;padding:0.4rem 0.8rem;border:1.5px dashed #d1d5db;border-radius:8px;background:none;color:#2B4C52;font-weight:600;font-size:0.82rem;cursor:pointer;width:100%;"><i class="fas fa-plus"></i> Add Item</button>
      <datalist id="admin-size-list">
        <option value="XS"><option value="S"><option value="M"><option value="L"><option value="XL">
        <option value="2XL"><option value="3XL"><option value="4XL"><option value="5XL">
        <option value="A4"><option value="A5"><option value="A3"><option value="Letter">
        <option value="Legal"><option value="Tabloid"><option value="Small">
        <option value="Medium"><option value="Large"><option value="X-Large">
        <option value="One Size"><option value="Standard"><option value="Square">
        <option value="Rounded"><option value="Mini"><option value="Oversized">
        <option value="Custom">
      </datalist>
    </div>
    <div class="cr-form-group">
      <label>Special Requests</label>
      <textarea id="editSpecialReq" placeholder="Any special instructions..."></textarea>
    </div>
    <button class="btn btn-primary" onclick="saveCustomization()" style="margin-bottom:1.5rem;"><i class="fas fa-save"></i> Save Customization</button>

    <h3 style="margin: 0 0 0.75rem; font-size: 1rem; color: #0f172a;">Set Ready for Purchase</h3>
    <div class="cr-form-row">
      <div class="cr-form-group">
        <label>Product Name</label>
        <input type="text" id="rfpName" placeholder="e.g., Custom T-Shirt Design">
      </div>
      <div class="cr-form-group">
        <label>Price (&#8369;)</label>
        <input type="number" id="rfpPrice" step="0.01" min="0" placeholder="0.00">
      </div>
    </div>
    <div class="cr-form-row">
      <div class="cr-form-group">
        <label>Quantity</label>
        <input type="number" id="rfpQty" min="1" value="1">
      </div>
      <div class="cr-form-group">
        <label>Image URL</label>
        <input type="text" id="rfpImage" placeholder="https://... or ../assets/...">
      </div>
    </div>
    <button class="btn btn-primary" onclick="setReadyForPurchase()"><i class="fas fa-check-circle"></i> Mark Ready for Purchase</button>

    <h3 style="margin: 1.5rem 0 0.75rem; font-size: 1rem; color: #0f172a;">Send Order Form</h3>
    <p style="font-size:0.82rem;color:#64748b;margin-bottom:0.75rem;">Create a formal order proposal for the customer to fill in their details.</p>
    <button class="btn btn-primary" onclick="openSendOrderForm()" style="background:linear-gradient(135deg,#3b82f6,#2563eb);"><i class="fas fa-file-invoice"></i> Send Order Form</button>

    <!-- Order Proposal -->
    <h3 style="margin: 1.5rem 0 0.75rem; font-size: 1rem; color: #0f172a;">Order Proposal</h3>
    <div id="proposalStatus" style="display:none;margin-bottom:1rem;">
      <div class="cr-detail-field"><label>Proposal Status</label><span id="proposalStatusText"></span></div>
    </div>
    <div id="proposalCustomerDetails" style="display:none;margin-bottom:1rem;">
      <h4 style="margin: 1rem 0 0.5rem; font-size: 0.9rem; color: #334155;">Customer Details</h4>
      <div class="cr-detail-grid">
        <div class="cr-detail-field"><label>Full Name</label><span id="propName"></span></div>
        <div class="cr-detail-field"><label>Email</label><span id="propEmail"></span></div>
        <div class="cr-detail-field"><label>Phone</label><span id="propPhone"></span></div>
        <div class="cr-detail-field"><label>Delivery Address</label><span id="propAddress"></span></div>
        <div class="cr-detail-field"><label>City</label><span id="propCity"></span></div>
        <div class="cr-detail-field"><label>Province</label><span id="propProvince"></span></div>
        <div class="cr-detail-field"><label>ZIP</label><span id="propZip"></span></div>
        <div class="cr-detail-field"><label>Payment Method</label><span id="propPayment"></span></div>
        <div class="cr-detail-field"><label>Landmark</label><span id="propLandmark"></span></div>
        <div class="cr-detail-field" style="grid-column:1/-1;"><label>Additional Notes</label><span id="propNotes"></span></div>
      </div>
    </div>
    <div id="proposalActions" style="display:none;margin-top:0.75rem;margin-bottom:1.5rem;gap:0.75rem;"></div>

    <!-- Admin Actions -->
    <h3 style="margin: 1.5rem 0 0.75rem; font-size: 1rem; color: #0f172a;">Admin Actions</h3>
    <div class="cr-form-row" style="margin-bottom:1rem;">
      <div class="cr-form-group">
        <label>Update Status</label>
        <select id="editStatus">
          <option value="pending">Pending</option>
          <option value="in_review">In Review</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="completed">Completed</option>
        </select>
      </div>
      <div class="cr-form-group">
        <label>Admin Notes</label>
        <textarea id="editNotes" placeholder="Notes visible to customer..."></textarea>
      </div>
    </div>
    <div style="display:flex;gap:0.75rem;">
      <button class="btn btn-primary" onclick="updateStatus()"><i class="fas fa-save"></i> Save Status</button>
      <button class="btn btn-danger" onclick="deleteRequest()" style="display:inline-flex;align-items:center;gap:0.4rem;"><i class="fas fa-trash-alt"></i> Delete Request</button>
    </div>
  </div>
</div>

<!-- Send Order Form Modal -->
<div class="cr-modal-overlay" id="orderFormModal">
  <div class="cr-modal" style="max-width:600px;">
    <button class="cr-modal-close" onclick="closeOrderFormModal()">&times;</button>
    <h2><i class="fas fa-file-invoice"></i> Send Order Form</h2>
    <p style="color:#64748b;font-size:0.85rem;margin-bottom:1.25rem;">Create an order proposal for the customer. Add items, prices, and notes.</p>

    <div id="orderFormItems">
      <div class="cr-form-row" style="margin-bottom:0.5rem;">
        <div class="cr-form-group" style="flex:2;">
          <label>Item Name</label>
          <input type="text" class="of-name" placeholder="e.g. Custom T-Shirt">
        </div>
        <div class="cr-form-group" style="flex:1;">
          <label>Qty</label>
          <input type="number" class="of-qty" value="1" min="1">
        </div>
        <div class="cr-form-group" style="flex:1;">
          <label>Unit Price (₱)</label>
          <input type="number" class="of-price" step="0.01" min="0" placeholder="0.00">
        </div>
        <div style="display:flex;align-items:flex-end;padding-bottom:0.65rem;">
          <button type="button" class="btn btn-danger btn-sm" onclick="removeOrderFormItem(this)" style="display:none;"><i class="fas fa-times"></i></button>
        </div>
      </div>
    </div>
    <button class="btn btn-outline btn-sm" onclick="addOrderFormItem()" style="margin-bottom:1rem;"><i class="fas fa-plus"></i> Add Item</button>

    <div class="cr-form-row">
      <div class="cr-form-group">
        <label>Shipping Fee (₱)</label>
        <input type="number" id="ofShippingFee" step="0.01" min="0" value="0" placeholder="0.00">
      </div>
      <div class="cr-form-group">
        <label>&nbsp;</label>
        <div style="padding:0.65rem 0;font-weight:700;font-size:1.1rem;">Total: ₱<span id="ofTotalDisplay">0.00</span></div>
      </div>
    </div>

    <div class="cr-form-group">
      <label>Admin Notes <span style="font-weight:400;color:#94a3b8;">(optional)</span></label>
      <textarea id="ofAdminNotes" rows="2" placeholder="Message to the customer..."></textarea>
    </div>

    <div id="ofError" style="color:#ef4444;font-size:0.85rem;display:none;margin-bottom:0.75rem;"></div>
    <div id="ofLoading" style="display:none;color:#64748b;margin-bottom:0.75rem;"><i class="fas fa-spinner fa-pulse"></i> Sending...</div>

    <div class="modal-actions">
      <button class="btn btn-outline" onclick="closeOrderFormModal()">Cancel</button>
      <button class="btn btn-primary" onclick="submitOrderForm()" style="background:linear-gradient(135deg,#3b82f6,#2563eb);"><i class="fas fa-paper-plane"></i> Send to Customer</button>
    </div>
  </div>
</div>

<script>
let currentRequestId = null;
let currentConvId = null;
let chatPoll = null;

const statusLabels = {
  pending: 'Pending', in_review: 'In Review', approved: 'Approved',
  ready_for_purchase: 'Ready to Buy', rejected: 'Rejected', completed: 'Completed'
};
const statusBadge = s => `cr-badge-${s}`;

async function loadRequests(status = 'all') {
  const tbody = document.getElementById('requestsTableBody');
  tbody.innerHTML = '<tr><td colspan="6" class="cr-empty"><i class="fas fa-spinner fa-spin"></i><br>Loading...</td></tr>';
  try {
    const url = status === 'all' ? '../api/custom-printing.php?action=all' : `../api/custom-printing.php?action=all&status=${status}`;
    const res = await fetch(url, { credentials: 'include' });
    const data = await res.json();
    if (!data.success) throw new Error(data.error);
    const reqs = data.requests || [];
    if (!reqs.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="cr-empty"><i class="fas fa-inbox"></i><br>No requests found</td></tr>';
      return;
    }
    tbody.innerHTML = reqs.map(r => `
      <tr onclick="openDetail(${r.id})" style="cursor:pointer;" data-id="${r.id}">
        <td><strong>#${r.id}</strong></td>
        <td>${escapeHtml(r.user_name)}<br><small style="color:#94a3b8;">${escapeHtml(r.user_email)}</small></td>
        <td>${escapeHtml(r.service_type)}</td>
        <td><span class="cr-badge ${statusBadge(r.status)}">${statusLabels[r.status] || r.status}</span></td>
        <td><small>${new Date(r.created_at).toLocaleDateString()}</small></td>
        <td><button class="btn btn-outline btn-sm" onclick="event.stopPropagation();openDetail(${r.id})">View</button></td>
      </tr>
    `).join('');
  } catch (err) {
    tbody.innerHTML = '<tr><td colspan="6" class="cr-empty" style="color:#dc2626;">Failed to load requests</td></tr>';
  }
}

async function openDetail(id) {
  currentRequestId = id;
  const modal = document.getElementById('detailModal');
  modal.classList.add('active');

  // Find request from table data
  const row = document.querySelector(`tr[data-id="${id}"]`);
  if (row) {
    document.querySelectorAll('.cr-table tr').forEach(r => r.classList.remove('cr-selected'));
    row.classList.add('cr-selected');
  }

  document.getElementById('detailTitle').textContent = `Request #${id}`;

  // Fetch request details
  try {
    const res = await fetch(`../api/custom-printing.php?action=get&id=${id}`, { credentials: 'include' });
    const data = await res.json();
    if (!data.success || !data.request) { alert('Request not found'); return; }
    const req = data.request;

    const itemsHtml = function(){
      try {
        const parsed = typeof req.items === 'string' ? JSON.parse(req.items) : req.items;
        if (Array.isArray(parsed) && parsed.length) {
          let h = '<table style="width:100%;border-collapse:collapse;font-size:0.85rem;"><tr style="background:#f1f5f9;"><th style="padding:0.35rem 0.5rem;text-align:left;">Size</th><th style="padding:0.35rem 0.5rem;text-align:left;">Qty</th><th style="padding:0.35rem 0.5rem;text-align:left;">Reference</th></tr>';
          parsed.forEach(function(it){
            const hasImg = it.image && it.image.length > 100;
            h += '<tr><td style="padding:0.3rem 0.5rem;border-bottom:1px solid #f1f5f9;">' + escapeHtml(it.size||'') + '</td><td style="padding:0.3rem 0.5rem;border-bottom:1px solid #f1f5f9;">' + (it.qty||1) + '</td><td style="padding:0.3rem 0.5rem;border-bottom:1px solid #f1f5f9;">' + (hasImg ? '<img src="' + escapeHtml(it.image) + '" onclick="openItemPreview(this)" style="width:40px;height:40px;border-radius:4px;object-fit:cover;cursor:pointer;border:1px solid #e2e8f0;">' : '--') + '</td></tr>';
          });
          h += '</table>';
          return h;
        }
      } catch(e) {}
      return '<span>--</span>';
    }();

    document.getElementById('detailFields').innerHTML = `
      <div class="cr-detail-field"><label>Customer</label><span>${escapeHtml(req.user_name)} (${escapeHtml(req.user_email)})</span></div>
      <div class="cr-detail-field"><label>Service Type</label><span>${escapeHtml(req.service_type)}</span></div>
      <div class="cr-detail-field"><label>Material</label><span>${req.material || '--'}</span></div>
      <div class="cr-detail-field"><label>Deadline</label><span>${req.preferred_deadline || '--'}</span></div>
      <div class="cr-detail-field" style="grid-column:1/-1;"><label>Order Items</label><span>${itemsHtml}</span></div>
      <div class="cr-detail-field" style="grid-column:1/-1;"><label>Special Requests</label><span>${escapeHtml(req.special_requests || 'None')}</span></div>
      <div class="cr-detail-field" style="grid-column:1/-1;"><label>Admin Notes</label><span>${escapeHtml(req.admin_notes || 'None')}</span></div>
    `;

    document.getElementById('rfpName').value = req.ready_for_purchase_name || '';
    document.getElementById('rfpPrice').value = req.ready_for_purchase_price || '';
    document.getElementById('rfpQty').value = req.ready_for_purchase_qty || 1;
    document.getElementById('rfpImage').value = req.ready_for_purchase_image || '';

    // Customization details
    document.getElementById('editMaterial').value = req.material || '';
    document.getElementById('editDeadline').value = req.preferred_deadline || '';
    document.getElementById('editSpecialReq').value = req.special_requests || '';
    adminRenderItems(req.items);

    // Files
    const filesDiv = document.getElementById('detailFiles');
    if (req.file_count > 0) {
      try {
        const fRes = await fetch(`../api/custom-printing.php?action=files&request_id=${id}`, { credentials: 'include' });
        const fData = await fRes.json();
        if (fData.success && fData.files && fData.files.length) {
          filesDiv.innerHTML = '<h3 style="margin:0 0 0.5rem;font-size:0.9rem;color:#0f172a;">Uploaded Files</h3><div class="cr-files-grid">' +
            fData.files.map(f => `<div class="cr-file-thumb" onclick="window.open('${f.file_url}')"><img src="${f.file_url}" alt="${f.file_name || 'File'}"></div>`).join('') +
            '</div>';
        } else { filesDiv.innerHTML = ''; }
      } catch(e) { filesDiv.innerHTML = ''; }
    } else { filesDiv.innerHTML = ''; }

    // Chat
    currentConvId = req.chat_conversation_id;
    const chatBox = document.getElementById('chatBox');
    if (currentConvId) {
      chatBox.style.display = 'block';
      document.getElementById('openInChatLink').href = 'chat.php?conversation=' + currentConvId;
      document.getElementById('openInChatLink').style.display = 'inline';
      loadChatMessages();
      if (chatPoll) clearInterval(chatPoll);
      chatPoll = setInterval(loadChatMessages, 3000);
    } else {
      chatBox.style.display = 'none';
    }

    // Load proposal
    const propStatus = document.getElementById('proposalStatus');
    const propDetails = document.getElementById('proposalCustomerDetails');
    const propActions = document.getElementById('proposalActions');
    propStatus.style.display = 'none';
    propDetails.style.display = 'none';
    propActions.style.display = 'none';
    propActions.innerHTML = '';
    try {
      const pRes = await fetch(`../api/order-proposals.php?action=list&request_id=${id}`, { credentials: 'include' });
      const pData = await pRes.json();
      const proposals = pData.proposals || [];
      if (proposals.length) {
        const prop = proposals[proposals.length - 1];
        const statusEl = document.getElementById('proposalStatusText');
        const labels = { sent: 'Sent (Pending Customer)', filled: 'Filled (Awaiting Approval)', converted: 'Approved & Converted', rejected: 'Rejected' };
        statusEl.textContent = labels[prop.status] || prop.status;
        propStatus.style.display = 'block';

        if (prop.status === 'filled' || prop.status === 'converted') {
          document.getElementById('propName').textContent = prop.full_name || '--';
          document.getElementById('propEmail').textContent = prop.email || '--';
          document.getElementById('propPhone').textContent = prop.phone || '--';
          document.getElementById('propAddress').textContent = prop.delivery_address || '--';
          document.getElementById('propCity').textContent = prop.city || '--';
          document.getElementById('propProvince').textContent = prop.province || '--';
          document.getElementById('propZip').textContent = prop.zip || '--';
          document.getElementById('propPayment').textContent = prop.payment_method || '--';
          document.getElementById('propLandmark').textContent = prop.landmark || '--';
          document.getElementById('propNotes').textContent = prop.additional_notes || '--';
          propDetails.style.display = 'block';
        }

        if (prop.status === 'filled') {
          const pid = prop.id;
          propActions.style.display = 'flex';
          propActions.innerHTML =
            `<button class="btn btn-success" onclick="approveProposalFromCR(${pid})"><i class="fas fa-check"></i> Approve & Create Order</button>` +
            `<button class="btn btn-danger" onclick="rejectProposalFromCR(${pid})"><i class="fas fa-times"></i> Reject</button>`;
        }
      }
    } catch(e) { /* proposal fetch failed silently */ }

    document.getElementById('editStatus').value = req.status;
    document.getElementById('editNotes').value = req.admin_notes || '';
  } catch (err) {
    alert('Failed to load details');
  }
}

function closeDetail() {
  document.getElementById('detailModal').classList.remove('active');
  if (chatPoll) clearInterval(chatPoll);
  chatPoll = null;
  currentConvId = null;
}

async function saveCustomization() {
  if (!currentRequestId) return;
  const items = [];
  document.querySelectorAll('#admin-items-container .admin-item-row').forEach(function(row) {
    const size = row.querySelector('.ai-size').value.trim();
    const qty = parseInt(row.querySelector('.ai-qty-value').textContent) || 1;
    const preview = row.querySelector('.ai-image-preview');
    const image = (preview && preview.style.display !== 'none' && preview.src) ? preview.src : '';
    if (size) items.push({size: size, qty: qty, image: image});
  });
  const data = {
    action: 'admin_update_details',
    request_id: currentRequestId,
    material: document.getElementById('editMaterial').value.trim(),
    items: items,
    special_requests: document.getElementById('editSpecialReq').value.trim(),
    preferred_deadline: document.getElementById('editDeadline').value || ''
  };
  try {
    const res = await fetch('../api/custom-printing.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(data)
    });
    const d = await res.json();
    if (d.success) {
      alert('Customization details saved! Status set to In Review.');
      openDetail(currentRequestId);
    } else { alert(d.error || 'Failed to save'); }
  } catch (e) { alert('Failed to save customization'); }
}

async function approveProposalFromCR(proposalId) {
  try {
    const r = await fetch('../api/order-proposals.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ action: 'approve', proposal_id: proposalId })
    });
    const d = await r.json();
    if (d.success) {
      showToast('Order approved! ' + (d.message || ''), 'success');
      openDetail(currentRequestId);
    } else { showToast(d.error || 'Failed to approve', 'error'); }
  } catch (e) { showToast('Failed to approve proposal', 'error'); }
}

async function rejectProposalFromCR(proposalId) {
  const reason = prompt('Rejection reason (will be visible to customer):');
  if (reason === null) return;
  if (!reason.trim()) { showToast('Please provide a reason', 'error'); return; }
  try {
    const r = await fetch('../api/order-proposals.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ action: 'reject', proposal_id: proposalId, rejection_reason: reason.trim() })
    });
    const d = await r.json();
    if (d.success) {
      showToast('Order form rejected. Customer will be notified.', 'success');
      openDetail(currentRequestId);
    } else { showToast(d.error || 'Failed to reject', 'error'); }
  } catch (e) { showToast('Failed to reject proposal', 'error'); }
}

function openSendOrderForm() {
  const row = document.querySelector('tr.cr-selected');
  if (!row) return;
  const cells = row.querySelectorAll('td');
  if (cells.length < 3) return;
  const customerEmail = cells[1]?.querySelector('small')?.textContent || '';
  const serviceType = cells[2]?.textContent?.trim() || '';

  // Reset form
  document.getElementById('orderFormItems').innerHTML = `
    <div class="cr-form-row" style="margin-bottom:0.5rem;">
      <div class="cr-form-group" style="flex:2;">
        <label>Item Name</label>
        <input type="text" class="of-name" placeholder="e.g. ${serviceType || 'Custom Product'}">
      </div>
      <div class="cr-form-group" style="flex:1;">
        <label>Qty</label>
        <input type="number" class="of-qty" value="1" min="1">
      </div>
      <div class="cr-form-group" style="flex:1;">
        <label>Unit Price (₱)</label>
        <input type="number" class="of-price" step="0.01" min="0" placeholder="0.00">
      </div>
      <div style="display:flex;align-items:flex-end;padding-bottom:0.65rem;">
        <button type="button" class="btn btn-danger btn-sm" onclick="removeOrderFormItem(this)" style="display:none;"><i class="fas fa-times"></i></button>
      </div>
    </div>
  `;
  document.getElementById('ofShippingFee').value = '0';
  document.getElementById('ofAdminNotes').value = '';
  document.getElementById('ofTotalDisplay').textContent = '0.00';
  document.getElementById('ofError').style.display = 'none';
  document.getElementById('orderFormModal').classList.add('active');

  // Pre-fill with existing ready_for_purchase data
  const rfpName = document.getElementById('rfpName').value.trim();
  const rfpPrice = document.getElementById('rfpPrice').value;
  const rfpQty = document.getElementById('rfpQty').value;
  if (rfpName && rfpPrice) {
    const nameInput = document.querySelector('.of-name');
    const qtyInput = document.querySelector('.of-qty');
    const priceInput = document.querySelector('.of-price');
    if (nameInput) nameInput.value = rfpName;
    if (qtyInput) qtyInput.value = rfpQty || 1;
    if (priceInput) priceInput.value = rfpPrice;
    updateOrderFormTotal();
  }

  // Attach input events for total calculation
  document.querySelectorAll('.of-qty, .of-price').forEach(el => {
    el.addEventListener('input', updateOrderFormTotal);
  });
  document.getElementById('ofShippingFee').addEventListener('input', updateOrderFormTotal);
}

function addOrderFormItem() {
  const container = document.getElementById('orderFormItems');
  const firstItem = container.querySelector('.cr-form-row');
  const clone = firstItem.cloneNode(true);
  clone.querySelector('.of-name').value = '';
  clone.querySelector('.of-qty').value = '1';
  clone.querySelector('.of-price').value = '';
  const removeBtn = clone.querySelector('.btn-danger');
  if (removeBtn) removeBtn.style.display = 'inline-flex';
  container.appendChild(clone);

  clone.querySelectorAll('.of-qty, .of-price').forEach(el => {
    el.addEventListener('input', updateOrderFormTotal);
  });
  updateOrderFormTotal();
}

function removeOrderFormItem(btn) {
  const row = btn.closest('.cr-form-row');
  const container = document.getElementById('orderFormItems');
  if (container.querySelectorAll('.cr-form-row').length > 1) {
    row.remove();
    updateOrderFormTotal();
  }
}

function updateOrderFormTotal() {
  let total = 0;
  document.querySelectorAll('#orderFormItems .cr-form-row').forEach(row => {
    const qty = parseFloat(row.querySelector('.of-qty')?.value) || 0;
    const price = parseFloat(row.querySelector('.of-price')?.value) || 0;
    total += qty * price;
  });
  const shipping = parseFloat(document.getElementById('ofShippingFee')?.value) || 0;
  const grandTotal = total + shipping;
  document.getElementById('ofTotalDisplay').textContent = grandTotal.toFixed(2);
}

async function submitOrderForm() {
  const errorEl = document.getElementById('ofError');
  const loadingEl = document.getElementById('ofLoading');
  errorEl.style.display = 'none';

  // Find customer ID from the selected request
  const row = document.querySelector('tr.cr-selected');
  if (!row) {
    errorEl.textContent = 'No request selected';
    errorEl.style.display = 'block';
    return;
  }
  const requestId = parseInt(row.dataset.id);

  // Fetch request data to get user_id
  let customerId = null;
  try {
    const res = await fetch(`../api/custom-printing.php?action=all`, { credentials: 'include' });
    const data = await res.json();
    const reqs = data.requests || [];
    const req = reqs.find(r => r.id == requestId);
    if (req) customerId = req.user_id;
  } catch(e) {}

  if (!customerId) {
    errorEl.textContent = 'Could not find customer';
    errorEl.style.display = 'block';
    return;
  }

  // Build items array
  const items = [];
  document.querySelectorAll('#orderFormItems .cr-form-row').forEach(row => {
    const name = row.querySelector('.of-name')?.value?.trim();
    const qty = parseInt(row.querySelector('.of-qty')?.value) || 1;
    const price = parseFloat(row.querySelector('.of-price')?.value) || 0;
    if (name) {
      items.push({ name, quantity: qty, unit_price: price });
    }
  });

  if (items.length === 0) {
    errorEl.textContent = 'Please add at least one item';
    errorEl.style.display = 'block';
    return;
  }

  loadingEl.style.display = 'block';

  try {
    const res = await fetch('../api/order-proposals.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        action: 'create',
        user_id: customerId,
        request_id: requestId,
        conversation_id: currentConvId,
        items: items,
        shipping_fee: parseFloat(document.getElementById('ofShippingFee').value) || 0,
        admin_notes: document.getElementById('ofAdminNotes').value.trim()
      })
    });
    const data = await res.json();
    if (data.success) {
      // Send chat message so customer sees the card in their conversation
      if (currentConvId && data.proposal_id) {
        try {
          await fetch('../api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
              action: 'send_message',
              conversation_id: currentConvId,
              message_type: 'order_form',
              content: JSON.stringify({
                proposal_id: data.proposal_id,
                request_id: requestId,
                title: 'Order Form'
              })
            })
          });
        } catch(e) {}
      }
      alert('Order form sent to customer!');
      closeOrderFormModal();
    } else {
      errorEl.textContent = data.error || 'Failed to send';
      errorEl.style.display = 'block';
    }
  } catch(e) {
    errorEl.textContent = 'Network error';
    errorEl.style.display = 'block';
  } finally {
    loadingEl.style.display = 'none';
  }
}

function closeOrderFormModal() {
  document.getElementById('orderFormModal').classList.remove('active');
}

async function loadChatMessages() {
  if (!currentConvId) return;
  try {
    const res = await fetch(`../api/chat.php?action=messages&conversation_id=${currentConvId}`, { credentials: 'include' });
    const data = await res.json();
    if (!data.success) return;
    const msgs = data.messages || [];
    const container = document.getElementById('chatMessages');
    container.innerHTML = msgs.map(m => {
      const isSent = m.sender_id == <?php echo $adminId; ?>;
      const time = new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      return `<div class="cr-msg ${isSent ? 'sent' : 'received'}">${escapeHtml(m.content)}<div class="cr-msg-time">${time}</div></div>`;
    }).join('');
    container.scrollTop = container.scrollHeight;
  } catch(e) {}
}

async function sendChatMsg() {
  const input = document.getElementById('chatInput');
  const content = input.value.trim();
  if (!content || !currentConvId) return;
  input.value = '';
  try {
    await fetch('../api/chat.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
      body: JSON.stringify({ action: 'send_message', conversation_id: currentConvId, message_type: 'text', content })
    });
    await loadChatMessages();
  } catch(e) { alert('Failed to send'); }
}

async function updateStatus() {
  if (!currentRequestId) return;
  const status = document.getElementById('editStatus').value;
  const notes = document.getElementById('editNotes').value;
  try {
    const res = await fetch('../api/custom-printing.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
      body: JSON.stringify({ action: 'update_status', request_id: currentRequestId, status, admin_notes: notes })
    });
    const data = await res.json();
    if (data.success) {
      alert('Status updated!');
      loadRequests(document.querySelector('.tab.active')?.dataset?.status || 'all');
    } else { alert(data.error || 'Failed'); }
  } catch(e) { alert('Error updating status'); }
}

async function setReadyForPurchase() {
  if (!currentRequestId) return;
  const productName = document.getElementById('rfpName').value.trim();
  const price = parseFloat(document.getElementById('rfpPrice').value);
  const qty = parseInt(document.getElementById('rfpQty').value) || 1;
  const image = document.getElementById('rfpImage').value.trim();

  if (!productName || !price || price <= 0) {
    alert('Please enter a product name and valid price');
    return;
  }

  try {
    const res = await fetch('../api/custom-printing.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
      body: JSON.stringify({ action: 'set_ready_for_purchase', request_id: currentRequestId, product_name: productName, price, quantity: qty, image_url: image })
    });
    const data = await res.json();
    if (data.success) {
      alert('Request marked as ready for purchase! A Buy Now button will appear for the customer.');
      loadRequests(document.querySelector('.tab.active')?.dataset?.status || 'all');
    } else { alert(data.error || 'Failed'); }
  } catch(e) { alert('Error'); }
}

function openItemPreview(img) {
  const overlay = document.getElementById('imgPreviewOverlay');
  const fullImg = document.getElementById('imgPreviewFull');
  if (overlay && fullImg) { fullImg.src = img.src; overlay.style.display = 'flex'; }
}

function closeImgPreview() {
  const overlay = document.getElementById('imgPreviewOverlay');
  if (overlay) overlay.style.display = 'none';
}

async function deleteRequest() {
  if (!currentRequestId) return;
  if (!confirm('Are you sure you want to delete this request? This action cannot be undone.')) return;
  try {
    const res = await fetch('../api/custom-printing.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
      body: JSON.stringify({ action: 'delete', request_id: currentRequestId })
    });
    const data = await res.json();
    if (data.success) {
      closeDetail();
      loadRequests(document.querySelector('.tab.active')?.dataset?.status || 'all');
      showToast('Request deleted successfully', 'success');
    } else { alert(data.error || 'Failed to delete'); }
  } catch(e) { alert('Error deleting request'); }
}

function adminRenderItems(items) {
  const container = document.getElementById('admin-items-container');
  container.innerHTML = '';
  let parsed = [];
  try { parsed = typeof items === 'string' ? JSON.parse(items) : (items || []); } catch(e) {}
  if (!Array.isArray(parsed) || !parsed.length) parsed = [{size: '', qty: 1, image: ''}];
  parsed.forEach(function(it, i) {
    const row = document.createElement('div');
    row.className = 'admin-item-row';
    const hasImg = it.image && it.image.length > 100;
    row.innerHTML =
      '<input type="text" class="ai-size" list="admin-size-list" placeholder="e.g., M, A4, Letter, Small, 3x5ft, etc." value="' + escapeHtml(it.size||'') + '">' +
      '<div class="ai-qty-stepper">' +
        '<button type="button" class="ai-qty-btn" data-action="dec">&minus;</button>' +
        '<span class="ai-qty-value">' + (it.qty||1) + '</span>' +
        '<button type="button" class="ai-qty-btn" data-action="inc">+</button>' +
      '</div>' +
      '<div class="ai-image-wrap">' +
        '<input type="file" class="ai-image-input" accept="image/*" style="display:none;">' +
        '<div class="ai-image-btn" onclick="this.previousElementSibling.click()" title="Upload reference image"' + (hasImg ? ' style="display:none;"' : '') + '><i class="fas fa-camera"></i></div>' +
        '<img class="ai-image-preview"' + (hasImg ? ' src="' + escapeHtml(it.image) + '" onclick="openItemPreview(this)" style="display:block;"' : ' style="display:none;"') + '>' +
      '</div>' +
      '<button type="button" class="ai-remove" onclick="adminRemoveItem(this)" title="Remove"' + (parsed.length < 2 ? ' style="display:none;"' : '') + '><i class="fas fa-times"></i> Remove</button>';
    container.appendChild(row);
    if (!hasImg) attachAdminImageHandler(row.querySelector('.ai-image-input'));
  });
}

function adminAddItem() {
  const container = document.getElementById('admin-items-container');
  const first = container.querySelector('.admin-item-row');
  const clone = first.cloneNode(true);
  clone.querySelector('.ai-size').value = '';
  const qtyVal = clone.querySelector('.ai-qty-value');
  if (qtyVal) qtyVal.textContent = '1';
  clone.querySelector('.ai-image-input').value = '';
  const btn = clone.querySelector('.ai-image-btn');
  if (btn) btn.style.display = 'flex';
  const preview = clone.querySelector('.ai-image-preview');
  if (preview) { preview.style.display = 'none'; preview.removeAttribute('src'); }
  const removeBtn = clone.querySelector('.ai-remove');
  removeBtn.style.display = 'inline-flex';
  container.appendChild(clone);
  attachAdminImageHandler(clone.querySelector('.ai-image-input'));
}

function adminRemoveItem(btn) {
  const container = document.getElementById('admin-items-container');
  if (container.querySelectorAll('.admin-item-row').length > 1) {
    btn.closest('.admin-item-row').remove();
  }
}

function attachAdminImageHandler(input) {
  if (!input) return;
  input.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    if (file.size > 20 * 1024 * 1024) { alert('Image must be less than 20MB'); return; }
    const reader = new FileReader();
    reader.onload = function(ev) {
      const row = input.closest('.admin-item-row');
      row.querySelector('.ai-image-btn').style.display = 'none';
      const preview = row.querySelector('.ai-image-preview');
      preview.src = ev.target.result;
      preview.style.display = 'block';
    };
    reader.readAsDataURL(file);
  });
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeImgPreview();
});

// Admin qty stepper delegation
document.addEventListener('click', function(e) {
  var btn = e.target.closest('.ai-qty-btn');
  if (!btn) return;
  var stepper = btn.closest('.ai-qty-stepper');
  if (!stepper) return;
  var val = stepper.querySelector('.ai-qty-value');
  var qty = parseInt(val.textContent) || 1;
  if (btn.getAttribute('data-action') === 'inc') {
    val.textContent = qty + 1;
  } else if (btn.getAttribute('data-action') === 'dec' && qty > 1) {
    val.textContent = qty - 1;
  }
});

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Filter buttons
document.querySelectorAll('.tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadRequests(btn.dataset.status);
  });
});

function showToast(message, type) {
  const existing = document.querySelector('.cr-toast');
  if (existing) existing.remove();
  const t = document.createElement('div');
  t.className = 'cr-toast';
  t.style.cssText = 'position:fixed;bottom:24px;right:24px;padding:12px 20px;border-radius:12px;font-size:0.88rem;font-weight:600;color:#fff;z-index:9999;transition:opacity 0.3s;box-shadow:0 8px 24px rgba(0,0,0,0.15);';
  t.style.background = type === 'error' ? '#ef4444' : '#10b981';
  t.textContent = message;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3000);
}

// Init
loadRequests();
</script>

<!-- Image Preview Modal -->
<div class="modal-overlay" id="imgPreviewOverlay" onclick="if(event.target===this)closeImgPreview()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);backdrop-filter:blur(4px);z-index:10000;align-items:center;justify-content:center;">
  <div style="position:relative;max-width:90vw;max-height:90vh;">
    <button onclick="closeImgPreview()" style="position:absolute;top:-2.5rem;right:0;background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;z-index:10;"><i class="fas fa-times"></i></button>
    <img id="imgPreviewFull" style="max-width:90vw;max-height:90vh;border-radius:12px;box-shadow:0 24px 80px rgba(0,0,0,0.5);display:block;">
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

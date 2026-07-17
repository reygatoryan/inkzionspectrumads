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
    .cr-unviewed-dot { width: 8px; height: 8px; border-radius: 50%; background: #3b82f6; display: inline-block; margin-right: 0.5rem; flex-shrink: 0; }
    .cr-table tr.cr-unviewed td:first-child { position: relative; }
    .cr-table tr.cr-unviewed td:first-child::before { content: ''; position: absolute; left: 4px; top: 50%; transform: translateY(-50%); width: 8px; height: 8px; border-radius: 50%; background: #3b82f6; }
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
      <label>Order Items <span style="font-weight:400;color:#94a3b8;">(size, qty)</span></label>
      <div id="admin-items-container">
        <div class="admin-item-row">
          <input type="text" class="ai-size" list="admin-size-list" placeholder="e.g., M, A4, Letter, Small, 3x5ft, etc.">
          <div class="ai-qty-stepper">
            <button type="button" class="ai-qty-btn" data-action="dec">&minus;</button>
            <span class="ai-qty-value">1</span>
            <button type="button" class="ai-qty-btn" data-action="inc">+</button>
          </div>
          <button type="button" class="ai-remove" onclick="adminRemoveItem(this)" title="Remove"><i class="fas fa-times"></i> Remove</button>
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
      <label>Note</label>
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
        <label>Shipping Fee (&#8369;)</label>
        <input type="number" id="rfpShipping" step="0.01" min="0" value="0" placeholder="0.00">
      </div>
      <div class="cr-form-group">
        <label>Quantity</label>
        <input type="number" id="rfpQty" min="1" value="1">
      </div>
    </div>
    <div class="cr-form-row">
      <div class="cr-form-group">
        <label>Image</label>
        <input type="file" id="rfpImageInput" accept="image/*" style="display:none;" onchange="previewRfpImage(this)">
        <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
          <button type="button" onclick="document.getElementById('rfpImageInput').click()" style="padding:0.4rem 0.8rem;border:1.5px solid #e2e8f0;border-radius:8px;background:white;cursor:pointer;font-size:0.82rem;white-space:nowrap;">
            <i class="fas fa-upload"></i> Choose Image
          </button>
          <span id="rfpImageName" style="font-size:0.8rem;color:#64748b;">No file chosen</span>
          <img id="rfpImagePreview" style="display:none;width:60px;height:60px;border-radius:8px;object-fit:cover;border:1px solid #e2e8f0;">
        </div>
      </div>
    </div>
    <button class="btn btn-primary" onclick="setReadyForPurchase()"><i class="fas fa-check-circle"></i> Mark Ready for Purchase</button>

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

async function loadRequests(status = 'all', silent = false) {
  const tbody = document.getElementById('requestsTableBody');
  if (!silent) tbody.innerHTML = '<tr><td colspan="6" class="cr-empty"><i class="fas fa-spinner fa-spin"></i><br>Loading...</td></tr>';
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
      <tr onclick="openDetail(${r.id})" style="cursor:pointer;" data-id="${r.id}" class="${r.is_viewed ? '' : 'cr-unviewed'}">
        <td><strong>#${r.id}</strong></td>
        <td>${escapeHtml(r.user_name)}<br><small style="color:#94a3b8;">${escapeHtml(r.user_email)}</small></td>
        <td>${escapeHtml(r.service_type)}</td>
        <td><span class="cr-badge ${statusBadge(r.status)}">${statusLabels[r.status] || r.status}</span></td>
        <td><small>${new Date(r.created_at).toLocaleDateString()}</small></td>
        <td>
          <button class="btn btn-outline btn-sm" onclick="event.stopPropagation();openDetail(${r.id})">View</button>
          <button class="btn btn-sm btn-outline-danger" style="margin-left:0.35rem;" onclick="event.stopPropagation();deleteRequest(${r.id})" title="Delete"><i class="fas fa-trash"></i></button>
        </td>
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

    // Mark as viewed
    if (!req.is_viewed) {
      fetch('../api/custom-printing.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ action: 'mark_viewed', request_id: id })
      }).catch(function(){});
      if (row) row.classList.remove('cr-unviewed');
    }

    const itemsHtml = function(){
      try {
        const parsed = typeof req.items === 'string' ? JSON.parse(req.items) : req.items;
        if (Array.isArray(parsed) && parsed.length) {
          let h = '<table style="width:100%;border-collapse:collapse;font-size:0.85rem;"><tr style="background:#f1f5f9;"><th style="padding:0.35rem 0.5rem;text-align:left;">Size</th><th style="padding:0.35rem 0.5rem;text-align:left;">Qty</th></tr>';
          parsed.forEach(function(it){
            h += '<tr><td style="padding:0.3rem 0.5rem;border-bottom:1px solid #f1f5f9;">' + escapeHtml(it.size||'') + '</td><td style="padding:0.3rem 0.5rem;border-bottom:1px solid #f1f5f9;">' + (it.qty||1) + '</td></tr>';
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
      <div class="cr-detail-field"><label>Unit Price</label><span>${req.ready_for_purchase_price != null && req.ready_for_purchase_price !== '' ? '₱' + parseFloat(req.ready_for_purchase_price).toFixed(2) : '--'}</span></div>
      <div class="cr-detail-field"><label>Material</label><span>${req.material || '--'}</span></div>
      <div class="cr-detail-field"><label>Deadline</label><span>${req.preferred_deadline || '--'}</span></div>
      <div class="cr-detail-field" style="grid-column:1/-1;"><label>Order Items</label><span>${itemsHtml}</span></div>
      <div class="cr-detail-field" style="grid-column:1/-1;"><label>Note</label><span>${escapeHtml(req.special_requests || 'None')}</span></div>
    `;

    document.getElementById('rfpName').value = req.ready_for_purchase_name || '';
    document.getElementById('rfpPrice').value = req.ready_for_purchase_price != null && req.ready_for_purchase_price !== '' ? req.ready_for_purchase_price : '';
    document.getElementById('rfpShipping').value = req.ready_for_purchase_shipping || '0';
    let totalQty = 1;
    try {
      const items = typeof req.items === 'string' ? JSON.parse(req.items) : (req.items || []);
      if (Array.isArray(items) && items.length > 0) {
        totalQty = items.reduce(function(sum, it) { return sum + (parseInt(it.qty) || 1); }, 0);
      }
    } catch(e) {}
    document.getElementById('rfpQty').value = req.ready_for_purchase_qty || totalQty;
    document.getElementById('rfpImagePreview').src = '';
    document.getElementById('rfpImagePreview').style.display = 'none';
    document.getElementById('rfpImageName').textContent = 'No file chosen';

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
      loadRequests(document.querySelector('.tab.active')?.dataset?.status || 'all', true);
    } else { alert(d.error || 'Failed to save'); }
  } catch (e) { alert('Failed to save customization'); }
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

function previewRfpImage(input) {
  const nameEl = document.getElementById('rfpImageName');
  const previewEl = document.getElementById('rfpImagePreview');
  if (input.files && input.files[0]) {
    nameEl.textContent = input.files[0].name;
    const reader = new FileReader();
    reader.onload = function(e) { previewEl.src = e.target.result; previewEl.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  } else {
    nameEl.textContent = 'No file chosen';
    previewEl.style.display = 'none';
  }
}

async function setReadyForPurchase() {
  if (!currentRequestId) return;
  const productName = document.getElementById('rfpName').value.trim();
  const price = parseFloat(document.getElementById('rfpPrice').value);
  const qty = parseInt(document.getElementById('rfpQty').value) || 1;
  const shipping = parseFloat(document.getElementById('rfpShipping').value) || 0;
  const fileInput = document.getElementById('rfpImageInput');

  if (!productName || !price || price <= 0) {
    alert('Please enter a product name and valid price');
    return;
  }

  const formData = new FormData();
  formData.append('action', 'set_ready_for_purchase');
  formData.append('request_id', currentRequestId);
  formData.append('product_name', productName);
  formData.append('price', price);
  formData.append('quantity', qty);
  formData.append('shipping', shipping);
  if (fileInput.files.length) {
    formData.append('rfp_image', fileInput.files[0]);
  }

  try {
    const res = await fetch('../api/custom-printing.php', {
      method: 'POST', credentials: 'include',
      body: formData
    });
    const data = await res.json();
    if (data.success) {
      alert('Request marked as ready for purchase! A Buy Now button will appear for the customer.');
      loadRequests(document.querySelector('.tab.active')?.dataset?.status || 'all', true);
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
      '<button type="button" class="ai-remove" onclick="adminRemoveItem(this)" title="Remove"><i class="fas fa-times"></i> Remove</button>';
    container.appendChild(row);
  });
}

function adminAddItem() {
  const container = document.getElementById('admin-items-container');
  const first = container.querySelector('.admin-item-row');
  const clone = first.cloneNode(true);
  clone.querySelector('.ai-size').value = '';
  const qtyVal = clone.querySelector('.ai-qty-value');
  if (qtyVal) qtyVal.textContent = '1';
  const removeBtn = clone.querySelector('.ai-remove');
  removeBtn.style.display = 'inline-flex';
  container.appendChild(clone);
}

function adminRemoveItem(btn) {
  const container = document.getElementById('admin-items-container');
  if (container.querySelectorAll('.admin-item-row').length > 1) {
    btn.closest('.admin-item-row').remove();
  }
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

async function deleteRequest(id) {
  if (!confirm('Delete this custom request? This cannot be undone.')) return;
  try {
    const res = await fetch('../api/custom-printing.php', {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', request_id: id })
    });
    const data = await res.json();
    if (data.success) {
      alert('Custom request deleted');
      loadRequests(document.querySelector('.tab.active')?.dataset?.status || 'all', true);
    } else {
      alert(data.error || 'Failed to delete');
    }
  } catch (e) {
    alert('Failed to delete request');
  }
}

// Init
loadRequests();
setInterval(() => loadRequests(document.querySelector('.tab.active')?.dataset?.status || 'all', true), 10000);
</script>

<!-- Image Preview Modal -->
<div class="modal-overlay" id="imgPreviewOverlay" onclick="if(event.target===this)closeImgPreview()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);backdrop-filter:blur(4px);z-index:10000;align-items:center;justify-content:center;">
  <div style="position:relative;max-width:90vw;max-height:90vh;">
    <button onclick="closeImgPreview()" style="position:absolute;top:-2.5rem;right:0;background:none;border:none;color:white;font-size:1.5rem;cursor:pointer;z-index:10;"><i class="fas fa-times"></i></button>
    <img id="imgPreviewFull" style="max-width:90vw;max-height:90vh;border-radius:12px;box-shadow:0 24px 80px rgba(0,0,0,0.5);display:block;">
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

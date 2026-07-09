<?php
$pageTitle = 'Customization Requests';
$pageSubtitle = 'Review and manage custom printing requests from customers';
require 'includes/admin-header.php';
$adminId = $userId;
?>
<style>
    .cr-table { width: 100%; border-collapse: collapse; }
    .cr-table th { text-align: left; padding: 0.85rem 1rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .cr-table td { padding: 0.85rem 1rem; font-size: 0.88rem; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
    .cr-table tr:hover td { background: #f8fafc; }
    .cr-table tr.cr-selected td { background: rgba(233,30,142,0.04); }
    .cr-badge { display: inline-flex; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
    .cr-badge-pending { background: rgba(245,158,11,0.12); color: #b8860b; }
    .cr-badge-in_review { background: rgba(59,130,246,0.12); color: #1d4ed8; }
    .cr-badge-approved { background: rgba(16,185,129,0.12); color: #047857; }
    .cr-badge-ready_for_purchase { background: rgba(233,30,142,0.12); color: #be1871; }
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
    .cr-form-group input:focus, .cr-form-group select:focus, .cr-form-group textarea:focus { border-color: #e91e8c; }
    .cr-form-group textarea { min-height: 80px; resize: vertical; }
    .cr-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .cr-chat-box { border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 1.5rem; overflow: hidden; }
    .cr-chat-header { padding: 0.75rem 1rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 600; font-size: 0.85rem; color: #1e293b; display: flex; justify-content: space-between; align-items: center; }
    .cr-chat-msgs { max-height: 250px; overflow-y: auto; padding: 0.75rem 1rem; display: flex; flex-direction: column; gap: 0.5rem; }
    .cr-msg { max-width: 80%; padding: 0.6rem 0.85rem; border-radius: 12px; font-size: 0.82rem; line-height: 1.4; }
    .cr-msg.sent { align-self: flex-end; background: linear-gradient(135deg, #e91e8c, #9c27b0); color: white; border-bottom-right-radius: 4px; }
    .cr-msg.received { align-self: flex-start; background: #f1f5f9; color: #1e293b; border-bottom-left-radius: 4px; }
    .cr-msg-time { font-size: 0.65rem; color: #94a3b8; margin-top: 0.2rem; }
    .cr-chat-input { display: flex; gap: 0.5rem; padding: 0.75rem 1rem; border-top: 1px solid #e2e8f0; }
    .cr-chat-input input { flex: 1; padding: 0.55rem 0.85rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; outline: none; }
    .cr-chat-input input:focus { border-color: #e91e8c; }
    .cr-chat-input button { padding: 0.55rem 1rem; border-radius: 8px; border: none; background: linear-gradient(135deg, #e91e8c, #9c27b0); color: white; font-weight: 600; cursor: pointer; font-size: 0.82rem; }
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
        <span><a href="chat.php" id="openInChatLink" style="font-size:0.75rem;color:#e91e8c;text-decoration:none;font-weight:600;display:none;" target="_blank"><i class="fas fa-external-link-alt"></i> Open in Chat</a></span>
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
        <label>Size</label>
        <input type="text" id="editSize" placeholder="e.g., A4, Letter, 12x18">
      </div>
      <div class="cr-form-group">
        <label>Material</label>
        <input type="text" id="editMaterial" placeholder="e.g., Glossy Paper, Vinyl">
      </div>
    </div>
    <div class="cr-form-row">
      <div class="cr-form-group">
        <label>Color</label>
        <input type="text" id="editColor" placeholder="e.g., Full Color, Black & White">
      </div>
      <div class="cr-form-group">
        <label>Finish</label>
        <input type="text" id="editFinish" placeholder="e.g., Matte, Glossy, Laminated">
      </div>
    </div>
    <div class="cr-form-row">
      <div class="cr-form-group">
        <label>Quantity</label>
        <input type="number" id="editQty" min="1" value="1">
      </div>
      <div class="cr-form-group">
        <label>Preferred Deadline</label>
        <input type="date" id="editDeadline">
      </div>
    </div>
    <div class="cr-form-group">
      <label>Special Requests</label>
      <textarea id="editSpecialReq" placeholder="Any special instructions..."></textarea>
    </div>
    <button class="btn btn-primary" onclick="saveCustomization()" style="margin-bottom:1.5rem;"><i class="fas fa-save"></i> Save Customization</button>

    <!-- Order Proposal -->
    <h3 style="margin: 1.5rem 0 0.75rem; font-size: 1rem; color: #0f172a;">Order Proposal</h3>
    <div id="proposalStatus"></div>
    <div id="proposalCustomerDetails" style="display:none;"></div>
    <div id="proposalActions" style="display:none;"></div>

    <!-- Admin Actions -->
    <h3 style="margin: 1rem 0 0.75rem; font-size: 1rem; color: #0f172a;">Admin Actions</h3>
    <div class="cr-form-row">
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
    <button class="btn btn-primary" onclick="updateStatus()" style="margin-bottom:1.5rem;"><i class="fas fa-save"></i> Save Status</button>

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
    const res = await fetch(`../api/custom-printing.php?action=all`, { credentials: 'include' });
    const data = await res.json();
    const reqs = data.requests || [];
    const req = reqs.find(r => r.id == id);
    if (!req) { alert('Request not found'); return; }

    document.getElementById('detailFields').innerHTML = `
      <div class="cr-detail-field"><label>Customer</label><span>${escapeHtml(req.user_name)} (${escapeHtml(req.user_email)})</span></div>
      <div class="cr-detail-field"><label>Service Type</label><span>${escapeHtml(req.service_type)}</span></div>
      <div class="cr-detail-field"><label>Size</label><span>${req.size || '--'}</span></div>
      <div class="cr-detail-field"><label>Material</label><span>${req.material || '--'}</span></div>
      <div class="cr-detail-field"><label>Color</label><span>${req.color || '--'}</span></div>
      <div class="cr-detail-field"><label>Finish</label><span>${req.finish || '--'}</span></div>
      <div class="cr-detail-field"><label>Quantity</label><span>${req.quantity}</span></div>
      <div class="cr-detail-field"><label>Deadline</label><span>${req.preferred_deadline || '--'}</span></div>
      <div class="cr-detail-field" style="grid-column:1/-1;"><label>Special Requests</label><span>${escapeHtml(req.special_requests || 'None')}</span></div>
      <div class="cr-detail-field" style="grid-column:1/-1;"><label>Admin Notes</label><span>${escapeHtml(req.admin_notes || 'None')}</span></div>
    `;

    document.getElementById('editStatus').value = req.status;
    document.getElementById('editNotes').value = req.admin_notes || '';
    document.getElementById('rfpName').value = req.ready_for_purchase_name || '';
    document.getElementById('rfpPrice').value = req.ready_for_purchase_price || '';
    document.getElementById('rfpQty').value = req.ready_for_purchase_qty || 1;
    document.getElementById('rfpImage').value = req.ready_for_purchase_image || '';

    // Customization details
    document.getElementById('editSize').value = req.size || '';
    document.getElementById('editMaterial').value = req.material || '';
    document.getElementById('editColor').value = req.color || '';
    document.getElementById('editFinish').value = req.finish || '';
    document.getElementById('editQty').value = req.quantity || 1;
    document.getElementById('editDeadline').value = req.preferred_deadline || '';
    document.getElementById('editSpecialReq').value = req.special_requests || '';

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

    // Order proposal
    const propStatus = document.getElementById('proposalStatus');
    const custDetails = document.getElementById('proposalCustomerDetails');
    const propActions = document.getElementById('proposalActions');
    propStatus.innerHTML = '';
    custDetails.style.display = 'none';
    propActions.style.display = 'none';
    try {
      const pRes = await fetch(`../api/order-proposals.php?action=list&request_id=${id}`, { credentials: 'include' });
      const pData = await pRes.json();
      const proposals = pData.proposals || [];
      const proposal = proposals.length > 0 ? proposals[0] : null;

      if (proposal) {
        const labels = { sent: 'Sent to Customer', filled: 'Filled by Customer', converted: 'Approved', rejected: 'Rejected' };
        const colors = { sent: '#f59e0b', filled: '#3b82f6', converted: '#10b981', rejected: '#ef4444' };
        const c = colors[proposal.status] || '#94a3b8';
        let html = `<div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
          <span style="font-weight:600;font-size:0.85rem;color:#334155;">Status:</span>
          <span style="display:inline-flex;padding:0.2rem 0.6rem;border-radius:999px;font-size:0.72rem;font-weight:700;background:${c}20;color:${c};">${labels[proposal.status] || proposal.status}</span>
        </div>`;

        if (proposal.status === 'filled') {
          html += '<p style="font-size:0.85rem;color:#334155;margin-bottom:0.75rem;">Customer has submitted their order details. Review and approve to create the order.</p>';
          custDetails.style.display = 'block';
          custDetails.innerHTML = `
            <div style="background:#f8fafc;border-radius:12px;padding:1rem;margin-bottom:1rem;">
              <h4 style="margin:0 0 0.75rem;font-size:0.85rem;color:#0f172a;">Customer Details</h4>
              <div class="cr-form-row">
                <div class="cr-form-group"><label>Full Name</label><input type="text" value="${escapeHtml(proposal.full_name||'')}" readonly style="background:#f1f5f9;"></div>
                <div class="cr-form-group"><label>Email</label><input type="text" value="${escapeHtml(proposal.email||'')}" readonly style="background:#f1f5f9;"></div>
              </div>
              <div class="cr-form-row">
                <div class="cr-form-group"><label>Phone</label><input type="text" value="${escapeHtml(proposal.phone||'')}" readonly style="background:#f1f5f9;"></div>
                <div class="cr-form-group"><label>Payment Method</label><input type="text" value="${escapeHtml(proposal.payment_method||'')}" readonly style="background:#f1f5f9;"></div>
              </div>
              <div class="cr-form-group"><label>Delivery Address</label><textarea readonly rows="2" style="background:#f1f5f9;">${escapeHtml(proposal.delivery_address||'')}</textarea></div>
              <div class="cr-form-row">
                <div class="cr-form-group"><label>City</label><input type="text" value="${escapeHtml(proposal.city||'')}" readonly style="background:#f1f5f9;"></div>
                <div class="cr-form-group"><label>Province</label><input type="text" value="${escapeHtml(proposal.province||'')}" readonly style="background:#f1f5f9;"></div>
                <div class="cr-form-group"><label>ZIP</label><input type="text" value="${escapeHtml(proposal.zip||'')}" readonly style="background:#f1f5f9;"></div>
                <div class="cr-form-group"><label>Landmark</label><input type="text" value="${escapeHtml(proposal.landmark||'')}" readonly style="background:#f1f5f9;"></div>
              </div>${proposal.additional_notes ? `<div class="cr-form-group"><label>Additional Notes</label><textarea readonly rows="2" style="background:#f1f5f9;">${escapeHtml(proposal.additional_notes)}</textarea></div>` : ''}
            </div>`;
          propActions.style.display = 'block';
          propActions.innerHTML = `
            <div style="display:flex;gap:0.5rem;margin-bottom:1.5rem;">
              <button class="btn btn-primary" onclick="approveProposalFromCR(${proposal.id})" style="background:linear-gradient(135deg,#10b981,#059669);"><i class="fas fa-check"></i> Approve</button>
              <button class="btn btn-danger" onclick="rejectProposalFromCR(${proposal.id})"><i class="fas fa-times"></i> Reject</button>
            </div>`;
        } else if (proposal.status === 'sent') {
          html += '<p style="font-size:0.85rem;color:#64748b;margin-bottom:1.5rem;"><i class="fas fa-hourglass-half"></i> Waiting for customer to fill the order form...</p>';
        } else if (proposal.status === 'converted') {
          const ref = proposal.order_reference || ('#' + proposal.order_id);
          html += `<p style="font-size:0.85rem;color:#10b981;margin-bottom:1.5rem;"><i class="fas fa-check-circle"></i> Order created: <strong>${escapeHtml(ref)}</strong></p>`;
        } else if (proposal.status === 'rejected') {
          html += '<p style="font-size:0.85rem;color:#ef4444;margin-bottom:1.5rem;"><i class="fas fa-times-circle"></i> Order form rejected. Customer can resubmit.</p>';
        }
        propStatus.innerHTML = html;
      } else {
        propStatus.innerHTML = '<p style="font-size:0.85rem;color:#94a3b8;margin-bottom:1.5rem;">No order proposal sent yet.</p>';
      }
    } catch(e) {
      propStatus.innerHTML = '<p style="font-size:0.85rem;color:#94a3b8;">Could not load proposal.</p>';
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
  const data = {
    action: 'admin_update_details',
    request_id: currentRequestId,
    size: document.getElementById('editSize').value.trim(),
    material: document.getElementById('editMaterial').value.trim(),
    color: document.getElementById('editColor').value.trim(),
    finish: document.getElementById('editFinish').value.trim(),
    quantity: parseInt(document.getElementById('editQty').value) || 1,
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
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

<?php
$pageTitle = 'Orders';
$pageSubtitle = 'Manage and track all customer orders';
require_once __DIR__ . '/includes/admin-header.php';
?>
<style>
  .so-topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem; }
  .so-topbar h1 { font-size: 1.4rem; font-weight: 800; color: #1a1a2e; }
  .so-topbar p { font-size: 0.85rem; color: #64748b; margin-top: 0.2rem; }
  .so-topbar-actions { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }

  .btn-ghost { background: transparent; color: #475569; border: 1px solid transparent; }
  .btn-ghost:hover { background: #f1f5f9; }

  .so-order-card { margin-bottom: 0.75rem; }
  .card-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9; }
  .so-order-id { font-weight: 700; font-size: 0.85rem; color: #1a1a2e; }
  .so-order-date { font-size: 0.75rem; color: #94a3b8; }

  .status { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.65rem; border-radius: 999px; font-size: 0.7rem; font-weight: 700; }
  .status.pending { background: rgba(255,193,7,0.12); color: #b8860b; }
  .status.confirmed { background: rgba(59,130,246,0.12); color: #1d4ed8; }
  .status.shipped { background: rgba(59,130,246,0.12); color: #1d4ed8; }
  .status.delivered { background: rgba(5,150,105,0.12); color: #047857; }
  .status.completed { background: rgba(5,150,105,0.12); color: #047857; }
  .status.cancelled { background: rgba(239,68,68,0.12); color: #dc2626; }
  .status.returned { background: rgba(249,115,22,0.12); color: #c2410c; }

  .so-order-body { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 0.75rem; }
  .so-order-item { display: flex; gap: 0.75rem; align-items: center; padding: 0.6rem; background: #f8fafc; border-radius: 10px; }
  .so-order-item img { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; background: white; border: 1px solid #e8ecf1; }
  .so-order-item-info { flex: 1; min-width: 0; }
  .so-order-item-name { font-size: 0.8rem; font-weight: 600; color: #1a1a2e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .so-order-item-meta { font-size: 0.7rem; color: #64748b; margin-top: 0.1rem; }
  .so-order-item-price { font-size: 0.78rem; font-weight: 700; color: #2B4C52; white-space: nowrap; }

  .so-order-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.5rem; padding: 0.75rem; background: #f8fafc; border-radius: 10px; margin-bottom: 0.75rem; }
  .so-detail-label { font-size: 0.65rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
  .so-detail-value { font-size: 0.78rem; color: #1a1a2e; font-weight: 600; margin-top: 0.1rem; }

  .so-order-footer { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #f1f5f9; }
  .so-order-total { font-size: 0.9rem; color: #1a1a2e; }
  .so-order-total strong { color: #2B4C52; font-size: 1.05rem; }
  .so-order-actions { display: flex; gap: 0.4rem; }
  .so-countdown { font-size: 0.7rem; font-weight: 700; color: #ef4444; display: inline-flex; align-items: center; gap: 0.25rem; }

  .so-filters { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; }
  .so-filter-group { display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap; }
  .so-filter-chip { padding: 0.35rem 0.75rem; border-radius: 999px; border: 1px solid #e2e8f0; background: white; font-size: 0.75rem; font-weight: 600; color: #475569; cursor: pointer; transition: all 0.15s ease; }
  .so-filter-chip:hover { border-color: #e91e8c; color: #e91e8c; }
  .so-filter-chip.active { background: rgba(233,30,142,0.08); border-color: #e91e8c; color: #e91e8c; }
  .so-filter-label { font-size: 0.72rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
  .so-search-input { padding: 0.45rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.8rem; outline: none; min-width: 180px; transition: border-color 0.2s; }
  .so-search-input:focus { border-color: #e91e8c; box-shadow: 0 0 0 3px rgba(233,30,142,0.08); }
  .so-select { padding: 0.45rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.8rem; outline: none; background: white; cursor: pointer; }
  .so-select:focus { border-color: #e91e8c; }

  .so-loading { text-align: center; padding: 3rem; color: #94a3b8; font-size: 0.9rem; }
  .so-loading i { font-size: 2rem; margin-bottom: 0.75rem; display: block; }

  @media (max-width: 1200px) {
    .so-order-body { grid-template-columns: 1fr 1fr; }
  }
  @media (max-width: 768px) {
    .so-order-body { grid-template-columns: 1fr; }
    .so-filters { flex-direction: column; align-items: stretch; }
    .so-search-input { min-width: 0; width: 100%; }
  }
</style>

<div class="so-topbar">
  <div>
    <h1>Order Management</h1>
    <p>View, filter, search, and process your orders.</p>
  </div>
  <div class="so-topbar-actions">
    <button class="btn btn-outline btn-sm" onclick="exportOrders()"><i class="fas fa-download"></i> Export</button>
    <button class="btn btn-outline btn-sm" onclick="exportHistory()"><i class="fas fa-history"></i> Export History</button>
    <button class="btn btn-primary btn-sm" onclick="massShip()"><i class="fas fa-ship"></i> Mass Ship</button>
  </div>
</div>

<div class="tabs" id="order-tabs">
  <button class="tab active" data-tab="all">All <span class="tab-count" id="count-all">0</span></button>
  <button class="tab" data-tab="unpaid">Unpaid <span class="tab-count" id="count-unpaid">0</span></button>
  <button class="tab" data-tab="pending">Pending <span class="tab-count" id="count-pending">0</span></button>
  <button class="tab" data-tab="shipping">Shipping <span class="tab-count" id="count-shipping">0</span></button>
  <button class="tab" data-tab="completed">Completed <span class="tab-count" id="count-completed">0</span></button>
   <button class="tab" data-tab="returns">Return/Refund/Cancel <span class="tab-count" id="count-returns">0</span></button>
   <button class="tab" data-tab="forms">Order Forms <span class="tab-count" id="count-forms">0</span></button>
</div>

<div class="so-filters">
  <div class="so-filter-group">
    <span class="so-filter-label">Status</span>
    <button class="so-filter-chip active" data-filter="all">All</button>
    <button class="so-filter-chip" data-filter="to_process">To Process</button>
    <button class="so-filter-chip" data-filter="processed">Processed</button>
  </div>
  <div class="so-filter-group">
    <span class="so-filter-label">Priority</span>
    <button class="so-filter-chip active" data-priority="all">All</button>
    <button class="so-filter-chip" data-priority="overdue">Overdue</button>
    <button class="so-filter-chip" data-priority="today">Ship Today</button>
    <button class="so-filter-chip" data-priority="tomorrow">Ship Tomorrow</button>
  </div>
  <input type="text" class="so-search-input" id="search-input" placeholder="Search by Order ID..." onkeyup="debounceSearch()">
  <select class="so-select" id="shipping-channel">
    <option value="">All Channels</option>
    <option value="jnt">J&T Express</option>
    <option value="lbc">LBC Express</option>
    <option value="pickup">Store Pickup</option>
  </select>
  <button class="btn btn-outline btn-sm" onclick="applyFilters()"><i class="fas fa-search"></i> Apply</button>
  <button class="btn btn-ghost btn-sm" onclick="resetFilters()"><i class="fas fa-undo"></i> Reset</button>
</div>

<div id="orders-container">
  <div class="so-loading"><i class="fas fa-spinner fa-spin"></i> Loading orders...</div>
</div>

<div class="pagination" id="pagination"></div>

<!-- Shipment Modal -->
<div class="modal-overlay" id="ship-modal">
  <div class="modal-content" style="max-width:480px;">
    <h2><i class="fas fa-truck"></i> Arrange Shipment</h2>
    <form id="ship-form" onsubmit="event.preventDefault(); submitShipment();">
      <input type="hidden" id="ship-order-id">
      <div class="form-group">
        <label>Order ID</label>
        <input type="text" id="ship-order-id-display" disabled style="background:#f8fafc;font-weight:600;">
      </div>
      <div class="form-group" id="ship-weight-group" style="display:none;">
        <label>Total Weight</label>
        <input type="text" id="ship-total-weight" disabled style="background:#f8fafc;">
      </div>
      <div class="form-group">
        <label>Courier</label>
        <select id="ship-courier" required>
          <option value="">Select courier...</option>
          <option value="J&T Express">J&T Express</option>
          <option value="LBC Express">LBC Express</option>
          <option value="2GO Express">2GO Express</option>
          <option value="DHL">DHL</option>
          <option value="Store Pickup">Store Pickup</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div class="form-group">
        <label>Tracking Number <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
        <input type="text" id="ship-tracking" placeholder="e.g. JNT1234567890">
      </div>
      <div class="form-group">
        <label>Estimated Delivery Date <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
        <input type="date" id="ship-estimated">
      </div>
      <div class="form-group">
        <label>Admin Notes <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
        <textarea id="ship-notes" rows="2" placeholder="Internal notes..."></textarea>
      </div>
      <div class="form-group">
        <label>Shipping Fee (₱) <span style="font-weight:400;color:var(--text-muted);">(set before confirming)</span></label>
        <input type="number" step="0.01" min="0" id="ship-fee" placeholder="0.00">
      </div>
      <div style="color:var(--danger);font-size:0.82rem;display:none;margin-bottom:0.75rem;" id="ship-error"></div>
      <div style="text-align:center;color:var(--text-muted);font-size:0.82rem;display:none;margin-bottom:0.75rem;" id="ship-loading"><i class="fas fa-spinner fa-pulse"></i> Processing...</div>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="cancelShipment()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="ship-submit-btn"><i class="fas fa-check-circle"></i> Confirm Shipment</button>
      </div>
    </form>
  </div>
</div>

<!-- Order Forms Section (hidden by default, shown via tab) -->
<div id="order-forms-section" style="display:none;">
  <div id="forms-container">
    <div class="so-loading"><i class="fas fa-spinner fa-spin"></i> Loading order forms...</div>
  </div>
</div>

<!-- Order Details Modal -->
<div class="modal-overlay" id="order-details-modal">
  <div class="modal-content" style="max-width:720px;max-height:85vh;overflow-y:auto;">
    <h2><i class="fas fa-file-invoice"></i> Order Details</h2>
    <div id="order-details-content" style="margin-top:1rem;"></div>
    <div class="modal-actions" style="margin-top:1rem;display:flex;justify-content:flex-end;">
      <button class="btn btn-outline" onclick="closeOrderDetailsModal()">Close</button>
    </div>
  </div>
</div>

<!-- Approve/Reject Proposal Modal -->
<div class="modal-overlay" id="proposal-modal">
  <div class="modal-content" style="max-width:560px;">
    <h2 id="proposal-modal-title">Review Order Form</h2>
    <div id="proposal-detail" style="max-height:60vh;overflow-y:auto;"></div>
    <div class="modal-actions" style="margin-top:1rem;display:flex;gap:0.5rem;justify-content:flex-end;">
      <button class="btn btn-outline" onclick="closeProposalModal()">Close</button>
      <button class="btn btn-danger" onclick="openRejectModal(currentProposalId)"><i class="fas fa-times"></i> Reject</button>
      <button class="btn btn-primary" onclick="approveProposal()"><i class="fas fa-check"></i> Approve</button>
    </div>
  </div>
</div>

<!-- Reject Reason Modal -->
<div class="modal-overlay" id="reject-modal">
  <div class="modal-content" style="max-width:420px;">
    <h2><i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i> Reject Order Form</h2>
    <p style="color:#64748b;font-size:0.88rem;margin-bottom:1rem;">Provide a reason so the customer can edit and resubmit.</p>
    <input type="hidden" id="reject-proposal-id">
    <div class="form-group">
      <label>Rejection Reason</label>
      <textarea id="reject-reason" rows="3" placeholder="e.g., Incorrect address, missing information..." style="width:100%;padding:0.65rem 0.85rem;border:1.5px solid #e2e8f0;border-radius:10px;font-size:0.88rem;font-family:inherit;resize:vertical;"></textarea>
    </div>
    <div id="reject-error" style="color:#ef4444;font-size:0.85rem;display:none;margin-bottom:0.5rem;"></div>
    <div class="modal-actions">
      <button class="btn btn-outline" onclick="closeRejectModal()">Cancel</button>
      <button class="btn btn-danger" onclick="submitReject()"><i class="fas fa-times"></i> Confirm Reject</button>
    </div>
  </div>
</div>

<script>
  let currentTab = 'all';
  let currentPage = 1;
  let currentFilter = 'all';
  let currentPriority = 'all';
  let searchTimeout = null;
  let totalPages = 1;
  let currentProposalId = null;

  async function loadCounts() {
    try {
      const res = await fetch('../api/admin-orders.php?action=counts');
      const data = await res.json();
      if (data.success) {
        document.getElementById('count-all').textContent = data.counts.all;
        document.getElementById('count-unpaid').textContent = data.counts.unpaid;
        document.getElementById('count-pending').textContent = data.counts.pending;
        document.getElementById('count-shipping').textContent = data.counts.shipping;
        document.getElementById('count-completed').textContent = data.counts.completed;
        document.getElementById('count-returns').textContent = data.counts.returns;
        document.getElementById('sidebar-order-count').textContent = data.counts.pending;
      }
    } catch (e) {}

    try {
      const pRes = await fetch('../api/order-proposals.php?action=list&status=filled');
      const pData = await pRes.json();
      if (pData.success && pData.proposals) {
        document.getElementById('count-forms').textContent = pData.proposals.length;
      }
    } catch (e) {}
  }

  async function loadOrders() {
    const container = document.getElementById('orders-container');
    container.innerHTML = '<div class="so-loading"><i class="fas fa-spinner fa-spin"></i> Loading orders...</div>';

    const search = document.getElementById('search-input').value.trim();
    const channel = document.getElementById('shipping-channel').value;
    let url = `../api/admin-orders.php?action=list&tab=${currentTab}&page=${currentPage}`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    if (channel) url += `&shipping_channel=${encodeURIComponent(channel)}`;

    try {
      const res = await fetch(url);
      const data = await res.json();
      if (!data.success) { container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><h3>Error loading orders</h3></div>'; return; }

      totalPages = data.total_pages || 1;
      renderOrders(data.orders);
      renderPagination();
    } catch (e) {
      container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><h3>Network error</h3><p>Please try again.</p></div>';
    }
  }

  function renderOrders(orders) {
    window._ordersData = orders;
    const container = document.getElementById('orders-container');
    if (!orders || orders.length === 0) {
      container.innerHTML = '<div class="empty-state"><i class="fas fa-box-open"></i><h3>No orders found</h3><p>Orders will appear here once customers place them.</p></div>';
      return;
    }

    container.innerHTML = orders.map(order => {
      const items = order.items || [];
      const statusClass = order.status;
      const statusLabel = order.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
      const paymentLabel = order.payment_method ? order.payment_method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'N/A';
      const createdDate = new Date(order.created_at);
      const dateStr = createdDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      const timeStr = createdDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

      const isFinalStatus = ['completed', 'delivered', 'cancelled', 'returned'].includes(String(order.status || '').toLowerCase());
      let countdownHtml = '';
      if (!isFinalStatus) {
        const shipDeadline = new Date(createdDate.getTime() + 2 * 24 * 60 * 60 * 1000);
        const now = new Date();
        const hoursLeft = Math.max(0, Math.floor((shipDeadline - now) / (1000 * 60 * 60)));
        countdownHtml = hoursLeft > 0
          ? `<span class="so-countdown"><i class="fas fa-clock"></i> ${hoursLeft}h left</span>`
          : '<span class="so-countdown" style="color:#dc2626;"><i class="fas fa-exclamation-triangle"></i> Overdue</span>';
      }

      return `
        <div class="card so-order-card">
          <div class="card-header">
            <div>
              <span class="so-order-id">#${order.id}</span>
              <span class="so-order-date">${dateStr} ${timeStr}</span>
            </div>
            <div style="display:flex;align-items:center;gap:0.5rem;">
              ${countdownHtml}
              <span class="status ${statusClass}">${statusLabel}</span>
            </div>
          </div>
          <div class="so-order-body">
            ${items.map(item => `
              <div class="so-order-item">
                <img src="${item.image_url || '../assets/products-demo.jpg'}" alt="${item.product_name}" onerror="this.src='../assets/products-demo.jpg'">
                <div class="so-order-item-info">
                  <div class="so-order-item-name">${item.product_name}</div>
                  <div class="so-order-item-meta">Qty: ${item.quantity} × ₱${parseFloat(item.unit_price).toFixed(2)}</div>
                </div>
                <div class="so-order-item-price">₱${(item.quantity * item.unit_price).toFixed(2)}</div>
              </div>
            `).join('')}
          </div>
          <div class="so-order-details">
            <div class="so-detail-item">
              <div class="so-detail-label">Customer</div>
              <div class="so-detail-value">${order.customer_name || 'Guest'}</div>
            </div>
            <div class="so-detail-item">
              <div class="so-detail-label">Payment</div>
              <div class="so-detail-value">${paymentLabel}</div>
            </div>
            <div class="so-detail-item">
              <div class="so-detail-label">Buyer Payment</div>
              <div class="so-detail-value">₱${parseFloat(order.total_amount).toFixed(2)}</div>
            </div>
            <div class="so-detail-item">
              <div class="so-detail-label">Shipping</div>
              <div class="so-detail-value">${order.total_weight ? order.total_weight + ' kg' : 'N/A'}</div>
            </div>
            ${order.shipping_fee ? `
            <div class="so-detail-item">
              <div class="so-detail-label">Shipping Fee</div>
              <div class="so-detail-value">₱${parseFloat(order.shipping_fee).toFixed(2)}</div>
            </div>` : ''}
            <div class="so-detail-item">
              <div class="so-detail-label">Courier</div>
              <div class="so-detail-value">${order.courier || 'Not assigned'}</div>
            </div>
            ${order.tracking_number ? `
            <div class="so-detail-item">
              <div class="so-detail-label">Tracking #</div>
              <div class="so-detail-value">${order.tracking_number}</div>
            </div>` : ''}
            ${order.estimated_delivery ? `
            <div class="so-detail-item">
              <div class="so-detail-label">Est. Delivery</div>
              <div class="so-detail-value">${new Date(order.estimated_delivery).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</div>
            </div>` : ''}
            <div class="so-detail-item">
              <div class="so-detail-label">Order Ref</div>
              <div class="so-detail-value">${order.order_reference || 'INK-' + String(order.id).padStart(6, '0')}</div>
            </div>
          </div>
          <div class="so-order-footer">
            <div class="so-order-total">Total: <strong>₱${parseFloat(order.total_amount).toFixed(2)}</strong></div>
            <div class="so-order-actions">
              ${order.status === 'pending' ? `<button class="btn btn-primary btn-sm" onclick="arrangeShipment(${order.id})"><i class="fas fa-truck"></i> Arrange Shipment</button>` : ''}
              ${order.status === 'confirmed' ? `<button class="btn btn-primary btn-sm" onclick="markShipped(${order.id})"><i class="fas fa-shipping-fast"></i> Mark as Shipped</button>` : ''}
              ${order.status === 'shipped' ? `<button class="btn btn-primary btn-sm" onclick="markDelivered(${order.id})"><i class="fas fa-check-circle"></i> Mark as Delivered</button>` : ''}
              ${order.status === 'delivered' ? `<button class="btn btn-primary btn-sm" onclick="markCompleted(${order.id})"><i class="fas fa-check-double"></i> Mark as Completed</button>` : ''}
              <button class="btn btn-outline btn-sm" onclick="viewOrder(${order.id})"><i class="fas fa-eye"></i> View</button>
            </div>
          </div>
        </div>
      `;
    }).join('');
  }

  function renderPagination() {
    const container = document.getElementById('pagination');
    if (totalPages <= 1) { container.innerHTML = ''; return; }
    let html = '';
    html += `<button class="page-btn" onclick="goToPage(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;
    for (let i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
        html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
      } else if (i === currentPage - 2 || i === currentPage + 2) {
        html += `<button class="page-btn" disabled>...</button>`;
      }
    }
    html += `<button class="page-btn" onclick="goToPage(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;
    container.innerHTML = html;
  }

  function goToPage(page) {
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    loadOrders();
  }

  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', function() {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      this.classList.add('active');
      currentTab = this.dataset.tab;
      currentPage = 1;

      if (currentTab === 'forms') {
        document.getElementById('orders-container').style.display = 'none';
        document.querySelector('.so-filters').style.display = 'none';
        document.getElementById('pagination').style.display = 'none';
        document.getElementById('order-forms-section').style.display = 'block';
        loadProposals();
      } else {
        document.getElementById('orders-container').style.display = 'block';
        document.querySelector('.so-filters').style.display = 'flex';
        document.getElementById('pagination').style.display = 'block';
        document.getElementById('order-forms-section').style.display = 'none';
        loadOrders();
      }
    });
  });

  document.querySelectorAll('.so-filter-chip[data-filter]').forEach(chip => {
    chip.addEventListener('click', function() {
      document.querySelectorAll('.so-filter-chip[data-filter]').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      currentFilter = this.dataset.filter;
      applyFilters();
    });
  });
  document.querySelectorAll('.so-filter-chip[data-priority]').forEach(chip => {
    chip.addEventListener('click', function() {
      document.querySelectorAll('.so-filter-chip[data-priority]').forEach(c => c.classList.remove('active'));
      this.classList.add('active');
      currentPriority = this.dataset.priority;
      applyFilters();
    });
  });

  function applyFilters() {
    currentPage = 1;
    loadOrders();
  }

  function resetFilters() {
    document.getElementById('search-input').value = '';
    document.getElementById('shipping-channel').value = '';
    document.querySelectorAll('.so-filter-chip[data-filter]').forEach(c => c.classList.remove('active'));
    document.querySelector('.so-filter-chip[data-filter="all"]').classList.add('active');
    document.querySelectorAll('.so-filter-chip[data-priority]').forEach(c => c.classList.remove('active'));
    document.querySelector('.so-filter-chip[data-priority="all"]').classList.add('active');
    currentFilter = 'all';
    currentPriority = 'all';
    currentPage = 1;
    loadOrders();
  }

  function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => { currentPage = 1; loadOrders(); }, 400);
  }

  function arrangeShipment(orderId) {
    document.getElementById('ship-order-id').value = orderId;
    document.getElementById('ship-order-id-display').value = '#INK-' + String(orderId).padStart(6, '0');
    document.getElementById('ship-courier').value = '';
    document.getElementById('ship-tracking').value = '';
    document.getElementById('ship-estimated').value = '';
    document.getElementById('ship-notes').value = '';
    document.getElementById('ship-fee').value = '';
    document.getElementById('ship-loading').style.display = 'none';
    document.getElementById('ship-error').style.display = 'none';
    // Show weight from the orders data
    const order = window._ordersData ? window._ordersData.find(o => o.id === orderId) : null;
    const weightEl = document.getElementById('ship-total-weight');
    const weightGroup = document.getElementById('ship-weight-group');
    if (order && order.total_weight) {
      weightEl.value = order.total_weight + ' kg';
      weightGroup.style.display = 'block';
    } else {
      weightGroup.style.display = 'none';
    }
    document.getElementById('ship-modal').classList.add('active');
  }

  async function submitShipment() {
    const btn = document.getElementById('ship-submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Processing...';
    document.getElementById('ship-error').style.display = 'none';
    document.getElementById('ship-loading').style.display = 'block';

    const orderId = document.getElementById('ship-order-id').value;
    const courier = document.getElementById('ship-courier').value;
    const tracking = document.getElementById('ship-tracking').value;
    const estimated = document.getElementById('ship-estimated').value;
    const notes = document.getElementById('ship-notes').value;
    const shippingFee = parseFloat(document.getElementById('ship-fee').value) || 0;

    if (!courier) {
      document.getElementById('ship-error').textContent = 'Please select a courier.';
      document.getElementById('ship-error').style.display = 'block';
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm Shipment';
      document.getElementById('ship-loading').style.display = 'none';
      return;
    }

    try {
      const res = await fetch('../api/admin-update-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ order_id: parseInt(orderId), status: 'confirmed', courier, tracking_number: tracking, estimated_delivery: estimated, notes, shipping_fee: shippingFee })
      });
      const data = await res.json();
      if (data.success) {
        document.getElementById('ship-modal').classList.remove('active');
        showToast('Shipment arranged successfully!', 'success');
        loadOrders();
        loadCounts();
      } else {
        document.getElementById('ship-error').textContent = data.error || 'Failed to arrange shipment.';
        document.getElementById('ship-error').style.display = 'block';
      }
    } catch (e) {
      document.getElementById('ship-error').textContent = 'Network error. Please try again.';
      document.getElementById('ship-error').style.display = 'block';
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm Shipment';
      document.getElementById('ship-loading').style.display = 'none';
    }
  }

  function cancelShipment() {
    document.getElementById('ship-modal').classList.remove('active');
  }

  function markShipped(orderId) {
    if (confirm('Mark Order #' + orderId + ' as Out for Delivery?')) {
      updateOrderStatus(orderId, 'shipped', 'Order is out for delivery.');
    }
  }

  function markDelivered(orderId) {
    if (confirm('Mark Order #' + orderId + ' as Delivered?')) {
      updateOrderStatus(orderId, 'delivered', 'Order has been delivered.');
    }
  }

  function markCompleted(orderId) {
    if (confirm('Mark Order #' + orderId + ' as Completed?')) {
      updateOrderStatus(orderId, 'completed', 'Order completed successfully.');
    }
  }

  async function updateOrderStatus(orderId, status, notes) {
    try {
      const res = await fetch('../api/admin-update-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ order_id: orderId, status, notes })
      });
      const data = await res.json();
      if (data.success) {
        showToast(data.message, 'success');
        loadOrders();
        loadCounts();
      } else {
        showToast(data.error || 'Error updating order', 'error');
      }
    } catch (e) {
      showToast('Network error', 'error');
    }
  }

  function viewOrder(id) {
    const order = window._ordersData ? window._ordersData.find(o => Number(o.id) === Number(id)) : null;
    const content = document.getElementById('order-details-content');

    if (!order) {
      content.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><h3>Order not found</h3></div>';
      document.getElementById('order-details-modal').classList.add('active');
      return;
    }

    const statusLabel = String(order.status || 'pending').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    const paymentLabel = order.payment_method ? order.payment_method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'N/A';
    const createdDate = new Date(order.created_at);
    const createdText = createdDate.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
    const items = order.items || [];

    const itemsHtml = items.length ? items.map(item => `
      <div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem 0;border-bottom:1px solid #f1f5f9;gap:0.75rem;">
        <div>
          <div style="font-weight:700;color:#0f172a;">${escapeHtml(item.product_name || 'Product')}</div>
          <div style="font-size:0.8rem;color:#64748b;">Qty: ${item.quantity || 1} × ₱${parseFloat(item.unit_price || 0).toFixed(2)}</div>
        </div>
        <div style="font-weight:700;color:#2B4C52;white-space:nowrap;">₱${(parseFloat(item.unit_price || 0) * (item.quantity || 1)).toFixed(2)}</div>
      </div>
    `).join('') : '<div style="color:#64748b;">No items found.</div>';

    content.innerHTML = `
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:0.75rem;margin-bottom:1rem;">
        <div class="card" style="padding:0.9rem;border:1px solid #e2e8f0;">
          <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;font-weight:700;">Order ID</div>
          <div style="font-size:1rem;font-weight:700;color:#0f172a;margin-top:0.2rem;">#${order.id}</div>
        </div>
        <div class="card" style="padding:0.9rem;border:1px solid #e2e8f0;">
          <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;font-weight:700;">Status</div>
          <div style="font-size:1rem;font-weight:700;color:#0f172a;margin-top:0.2rem;">${escapeHtml(statusLabel)}</div>
        </div>
        <div class="card" style="padding:0.9rem;border:1px solid #e2e8f0;">
          <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;font-weight:700;">Payment</div>
          <div style="font-size:1rem;font-weight:700;color:#0f172a;margin-top:0.2rem;">${escapeHtml(paymentLabel)}</div>
        </div>
        <div class="card" style="padding:0.9rem;border:1px solid #e2e8f0;">
          <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;font-weight:700;">Placed</div>
          <div style="font-size:0.95rem;font-weight:700;color:#0f172a;margin-top:0.2rem;">${escapeHtml(createdText)}</div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:0.75rem;margin-bottom:1rem;">
        <div class="card" style="padding:0.95rem;border:1px solid #e2e8f0;">
          <div style="font-weight:700;color:#0f172a;margin-bottom:0.55rem;">Customer</div>
          <div style="font-size:0.9rem;color:#475569;line-height:1.6;">${escapeHtml(order.customer_name || 'Guest')}<br>${escapeHtml(order.customer_email || '')}<br>${escapeHtml(order.customer_phone || '')}</div>
        </div>
        <div class="card" style="padding:0.95rem;border:1px solid #e2e8f0;">
          <div style="font-weight:700;color:#0f172a;margin-bottom:0.55rem;">Shipping</div>
          <div style="font-size:0.9rem;color:#475569;line-height:1.6;">${escapeHtml(order.contact_name || '')}<br>${escapeHtml(order.delivery_address || 'N/A')}<br>${escapeHtml([order.delivery_city, order.delivery_province].filter(Boolean).join(', '))}</div>
        </div>
      </div>

      <div class="card" style="padding:0.95rem;border:1px solid #e2e8f0;margin-bottom:1rem;">
        <div style="font-weight:700;color:#0f172a;margin-bottom:0.6rem;">Items</div>
        ${itemsHtml}
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem;">
        <div class="card" style="padding:0.9rem;border:1px solid #e2e8f0;">
          <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;font-weight:700;">Weight</div>
          <div style="font-size:0.95rem;font-weight:700;color:#0f172a;margin-top:0.2rem;">${order.total_weight ? `${parseFloat(order.total_weight).toFixed(3)} kg` : 'N/A'}</div>
        </div>
        <div class="card" style="padding:0.9rem;border:1px solid #e2e8f0;">
          <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;font-weight:700;">Shipping Fee</div>
          <div style="font-size:0.95rem;font-weight:700;color:#0f172a;margin-top:0.2rem;">₱${parseFloat(order.shipping_fee || 0).toFixed(2)}</div>
        </div>
        <div class="card" style="padding:0.9rem;border:1px solid #e2e8f0;">
          <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;font-weight:700;">Total Amount</div>
          <div style="font-size:0.95rem;font-weight:700;color:#2B4C52;margin-top:0.2rem;">₱${parseFloat(order.total_amount || 0).toFixed(2)}</div>
        </div>
      </div>
    `;

    document.getElementById('order-details-modal').classList.add('active');
  }

  function closeOrderDetailsModal() {
    document.getElementById('order-details-modal').classList.remove('active');
  }

  function massShip() {
    alert('Mass Ship - Select multiple orders to ship together. Feature coming soon.');
  }

  function exportOrders() {
    alert('Export orders as CSV - Feature coming soon.');
  }

  function exportHistory() {
    alert('Export history - Feature coming soon.');
  }

  // ========= ORDER FORMS FUNCTIONS =========
  let proposalsData = [];

  async function loadProposals() {
    const container = document.getElementById('forms-container');
    container.innerHTML = '<div class="so-loading"><i class="fas fa-spinner fa-spin"></i> Loading order forms...</div>';

    try {
      const res = await fetch('../api/order-proposals.php?action=list&status=filled', { credentials: 'include' });
      const data = await res.json();
      if (!data.success || !data.proposals) {
        container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><h3>Error loading forms</h3></div>';
        return;
      }

      proposalsData = data.proposals;

      if (proposalsData.length === 0) {
        container.innerHTML = '<div class="empty-state"><i class="fas fa-file-invoice"></i><h3>No pending order forms</h3><p>Order forms submitted by customers will appear here.</p></div>';
        return;
      }

      container.innerHTML = proposalsData.map(p => {
        const items = p.items || [];
        const itemSummary = items.map(i => `${i.name} (x${i.quantity})`).join(', ');
        return `
          <div class="card so-order-card" style="cursor:pointer;" onclick="openProposal(${p.id})">
            <div class="card-header">
              <div>
                <span class="so-order-id">Form #${p.id}</span>
                <span class="so-order-date">${new Date(p.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
              </div>
              <span class="status confirmed" style="background:rgba(59,130,246,0.12);color:#1d4ed8;">Pending Approval</span>
            </div>
            <div class="so-order-body">
              <div class="so-order-details">
                <div class="so-detail-item">
                  <div class="so-detail-label">Customer</div>
                  <div class="so-detail-value">${escapeHtml(p.user_name || 'Unknown')}</div>
                </div>
                <div class="so-detail-item">
                  <div class="so-detail-label">Email</div>
                  <div class="so-detail-value">${escapeHtml(p.user_email || '')}</div>
                </div>
                <div class="so-detail-item">
                  <div class="so-detail-label">Items</div>
                  <div class="so-detail-value">${escapeHtml(itemSummary)}</div>
                </div>
                <div class="so-detail-item">
                  <div class="so-detail-label">Total</div>
                  <div class="so-detail-value">₱${parseFloat(p.total_amount).toFixed(2)}</div>
                </div>
                <div class="so-detail-item">
                  <div class="so-detail-label">Payment</div>
                  <div class="so-detail-value">${p.payment_method ? p.payment_method.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase()) : 'Not set'}</div>
                </div>
              </div>
            </div>
            <div class="so-order-footer">
              <div style="font-size:0.82rem;color:#64748b;">Filled on: ${new Date(p.updated_at).toLocaleDateString()}</div>
              <div class="so-order-actions">
                <button class="btn btn-primary btn-sm" onclick="event.stopPropagation();approveProposal(${p.id})"><i class="fas fa-check"></i> Approve</button>
                <button class="btn btn-danger btn-sm" onclick="event.stopPropagation();openRejectModal(${p.id})"><i class="fas fa-times"></i> Reject</button>
              </div>
            </div>
          </div>
        `;
      }).join('');

      document.getElementById('count-forms').textContent = proposalsData.length;
    } catch (e) {
      container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-circle"></i><h3>Network error</h3></div>';
    }
  }

  function openProposal(id) {
    const p = proposalsData.find(x => x.id === id);
    if (!p) return;
    currentProposalId = id;

    const items = p.items || [];
    let itemsHtml = items.map(i => `
      <tr>
        <td>${escapeHtml(i.name || 'Item')}</td>
        <td style="text-align:center;">${i.quantity || 1}</td>
        <td style="text-align:right;">₱${parseFloat(i.unit_price || 0).toFixed(2)}</td>
        <td style="text-align:right;">₱${(parseFloat(i.unit_price || 0) * (i.quantity || 1)).toFixed(2)}</td>
      </tr>
    `).join('');

    document.getElementById('proposal-modal-title').textContent = `Order Form #${id}`;
    document.getElementById('proposal-detail').innerHTML = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem;">
        <div><strong>Customer:</strong><br>${escapeHtml(p.full_name || p.user_name || 'N/A')}</div>
        <div><strong>Email:</strong><br>${escapeHtml(p.email || 'N/A')}</div>
        <div><strong>Phone:</strong><br>${escapeHtml(p.phone || 'N/A')}</div>
        <div><strong>Payment Method:</strong><br>${p.payment_method ? p.payment_method.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase()) : 'N/A'}</div>
      </div>
      <div style="margin-bottom:1rem;">
        <strong>Delivery Address:</strong><br>
        ${escapeHtml(p.delivery_address || 'N/A')}<br>
        ${escapeHtml(p.city || '')}, ${escapeHtml(p.province || '')} ${escapeHtml(p.zip || '')}<br>
        ${p.landmark ? `<span style="font-size:0.85rem;color:#64748b;"><i class="fas fa-map-pin"></i> ${escapeHtml(p.landmark)}</span>` : ''}
      </div>
      ${p.additional_notes ? `<div style="margin-bottom:1rem;"><strong>Notes:</strong><br>${escapeHtml(p.additional_notes)}</div>` : ''}
      <table class="quote-table" style="width:100%;border-collapse:collapse;">
        <thead>
          <tr>
            <th style="text-align:left;padding:0.5rem 0.65rem;font-size:0.72rem;font-weight:700;color:#94a3b8;background:#f8fafc;border-bottom:1px solid #e2e8f0;">Item</th>
            <th style="text-align:center;padding:0.5rem 0.65rem;font-size:0.72rem;font-weight:700;color:#94a3b8;background:#f8fafc;border-bottom:1px solid #e2e8f0;">Qty</th>
            <th style="text-align:right;padding:0.5rem 0.65rem;font-size:0.72rem;font-weight:700;color:#94a3b8;background:#f8fafc;border-bottom:1px solid #e2e8f0;">Price</th>
            <th style="text-align:right;padding:0.5rem 0.65rem;font-size:0.72rem;font-weight:700;color:#94a3b8;background:#f8fafc;border-bottom:1px solid #e2e8f0;">Subtotal</th>
          </tr>
        </thead>
        <tbody>${itemsHtml}</tbody>
        <tfoot>
          <tr><td colspan="3" style="text-align:right;font-weight:600;padding-top:0.75rem;">Subtotal</td><td style="text-align:right;padding-top:0.75rem;">₱${parseFloat(p.subtotal).toFixed(2)}</td></tr>
          ${parseFloat(p.shipping_fee) > 0 ? `<tr><td colspan="3" style="text-align:right;font-weight:600;">Shipping</td><td style="text-align:right;">₱${parseFloat(p.shipping_fee).toFixed(2)}</td></tr>` : ''}
          <tr><td colspan="3" style="text-align:right;font-weight:700;font-size:1.05rem;">Total</td><td style="text-align:right;font-weight:700;font-size:1.05rem;color:#e91e8c;">₱${parseFloat(p.total_amount).toFixed(2)}</td></tr>
        </tfoot>
      </table>
      ${p.admin_notes ? `<div style="margin-top:1rem;padding:0.75rem;background:#f0f9ff;border-radius:8px;font-size:0.85rem;"><strong>Admin Notes:</strong><br>${escapeHtml(p.admin_notes)}</div>` : ''}
    `;

    document.getElementById('proposal-modal').classList.add('active');
  }

  function closeProposalModal() {
    document.getElementById('proposal-modal').classList.remove('active');
    currentProposalId = null;
  }

  function approveProposal(id) {
    const proposalId = id || currentProposalId;
    if (!proposalId) return;
    if (!confirm('Approve this order form? An order will be created.')) return;

    fetch('../api/order-proposals.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ action: 'approve', proposal_id: proposalId })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(data.message, 'success');
        closeProposalModal();
        loadProposals();
        loadCounts();
      } else {
        showToast(data.error || 'Failed to approve', 'error');
      }
    })
    .catch(() => showToast('Network error', 'error'));
  }

  function openRejectModal(id) {
    currentProposalId = id;
    document.getElementById('reject-proposal-id').value = id;
    document.getElementById('reject-reason').value = '';
    document.getElementById('reject-error').style.display = 'none';
    document.getElementById('reject-modal').classList.add('active');
  }

  function closeRejectModal() {
    document.getElementById('reject-modal').classList.remove('active');
  }

  async function submitReject() {
    const id = document.getElementById('reject-proposal-id').value;
    const reason = document.getElementById('reject-reason').value.trim();
    const errorEl = document.getElementById('reject-error');

    if (!reason) {
      errorEl.textContent = 'Please provide a rejection reason';
      errorEl.style.display = 'block';
      return;
    }

    try {
      const res = await fetch('../api/order-proposals.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ action: 'reject', proposal_id: parseInt(id), reason })
      });
      const data = await res.json();
      if (data.success) {
        showToast(data.message, 'success');
        closeRejectModal();
        closeProposalModal();
        loadProposals();
      } else {
        errorEl.textContent = data.error || 'Failed to reject';
        errorEl.style.display = 'block';
      }
    } catch (e) {
      errorEl.textContent = 'Network error';
      errorEl.style.display = 'block';
    }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    if (tabParam) {
      const tabBtn = document.querySelector(`.tab[data-tab="${tabParam}"]`);
      if (tabBtn) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        tabBtn.classList.add('active');
        currentTab = tabParam;
        if (currentTab === 'forms') {
          document.getElementById('orders-container').style.display = 'none';
          document.querySelector('.so-filters').style.display = 'none';
          document.getElementById('pagination').style.display = 'none';
          document.getElementById('order-forms-section').style.display = 'block';
          loadProposals();
          loadCounts();
          return;
        }
      }
    }
    loadCounts();
    loadOrders();
  });
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

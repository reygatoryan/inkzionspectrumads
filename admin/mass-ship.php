<?php
$pageTitle = 'Mass Shipment';
$pageSubtitle = 'Bulk shipment management - arrange pickups, generate documents, and ship multiple orders.';
require 'includes/admin-header.php';
?>
<style>
  /* Page-specific styles */
  .ms-filters {
    display: flex; gap: 0.75rem; align-items: center;
    flex-wrap: wrap; margin-bottom: 1rem;
  }
  .ms-filter-group { display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap; }
  .ms-filter-chip {
    padding: 0.35rem 0.75rem; border-radius: 999px;
    border: 1px solid var(--border-color); background: white;
    font-size: 0.75rem; font-weight: 600; color: var(--text-secondary);
    cursor: pointer; transition: all 0.15s ease;
  }
  .ms-filter-chip:hover { border-color: var(--primary); color: var(--primary); }
  .ms-filter-chip.active { background: var(--primary-bg); border-color: var(--primary); color: var(--primary); }
  .ms-filter-label { font-size: 0.72rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
  .ms-expand-filters {
    font-size: 0.75rem; color: var(--primary); font-weight: 600;
    background: none; border: none; cursor: pointer;
    display: inline-flex; align-items: center; gap: 0.3rem;
  }
  .ms-expand-filters:hover { text-decoration: underline; }
  .ms-search-input {
    padding: 0.45rem 0.75rem; border: 1px solid var(--border-color);
    border-radius: 10px; font-size: 0.8rem; outline: none;
    min-width: 180px; transition: border-color 0.2s;
  }
  .ms-search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
  .ms-content {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 1.25rem;
    align-items: start;
  }
  .ms-table-card {
    background: white; border: 1px solid var(--border-color);
    border-radius: 16px; overflow: hidden;
  }
  .ms-table-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-light);
  }
  .ms-table-header h3 { font-size: 0.95rem; font-weight: 700; color: var(--text-primary); }
  .ms-select-all {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.78rem; color: var(--text-secondary); font-weight: 600;
    cursor: pointer;
  }
  .ms-table tr.selected td { background: rgba(233,30,142,0.03); }
  .ms-checkbox {
    width: 18px; height: 18px; border-radius: 5px;
    border: 2px solid #d1d5db; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s ease; background: white;
  }
  .ms-checkbox.checked {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-color: transparent; color: white; font-size: 0.65rem;
  }
  .ms-product-cell {
    display: flex; align-items: center; gap: 0.75rem;
  }
  .ms-product-img {
    width: 44px; height: 44px; border-radius: 8px;
    object-fit: cover; background: white; border: 1px solid var(--border-color);
  }
  .ms-product-name {
    font-size: 0.82rem; font-weight: 600; color: var(--text-primary);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    max-width: 180px;
  }
  .ms-order-id { font-weight: 700; font-size: 0.82rem; color: var(--text-primary); }
  .ms-buyer { font-size: 0.8rem; color: var(--text-secondary); }
  .ms-channel {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.2rem 0.6rem; border-radius: 999px;
    font-size: 0.7rem; font-weight: 600;
    background: rgba(59,130,246,0.1); color: #1d4ed8;
  }
  .ms-confirmed { font-size: 0.78rem; color: var(--text-muted); white-space: nowrap; }
  .ms-pickup-panel {
    background: white; border: 1px solid var(--border-color);
    border-radius: 16px; padding: 1.5rem;
    position: sticky; top: 100px;
  }
  .ms-pickup-panel h3 {
    font-size: 1rem; font-weight: 700; color: var(--text-primary);
    margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;
  }
  .ms-pickup-panel h3 i { color: var(--primary); }
  .ms-pickup-stat {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.75rem 0; border-bottom: 1px solid var(--border-light);
  }
  .ms-pickup-stat:last-child { border-bottom: none; }
  .ms-pickup-label { font-size: 0.78rem; color: var(--text-muted); font-weight: 500; }
  .ms-pickup-value { font-size: 0.88rem; font-weight: 700; color: var(--text-primary); }
  .ms-pickup-address {
    margin-top: 1rem; padding: 1rem;
    background: #f8fafc; border-radius: 12px;
    font-size: 0.82rem; color: var(--text-secondary); line-height: 1.6;
  }
  .ms-pickup-address strong { color: var(--text-primary); display: block; margin-bottom: 0.3rem; }
  .ms-pickup-info {
    margin-top: 0.75rem; padding: 0.75rem;
    background: rgba(59,130,246,0.04); border: 1px solid rgba(59,130,246,0.15);
    border-radius: 10px; font-size: 0.78rem; color: var(--text-secondary);
  }
  .ms-pickup-info i { color: #3b82f6; margin-right: 0.3rem; }
  .ms-pickup-hours {
    margin-top: 0.75rem; font-size: 0.78rem; color: var(--text-muted);
    display: flex; align-items: center; gap: 0.4rem;
  }
  .ms-pickup-hours a { color: var(--primary); font-weight: 600; text-decoration: none; }
  .ms-pickup-actions { margin-top: 1.25rem; display: flex; flex-direction: column; gap: 0.5rem; }
  .ms-pickup-actions .btn { width: 100%; justify-content: center; }

  /* ===== LOADING ===== */
  .ms-loading {
    text-align: center; padding: 3rem;
    color: #94a3b8; font-size: 0.9rem;
  }
  .ms-loading i { font-size: 2rem; margin-bottom: 0.75rem; display: block; }

  @media (max-width: 1200px) {
    .ms-content { grid-template-columns: 1fr; }
    .ms-pickup-panel { position: static; }
  }
  @media (max-width: 768px) {
    .ms-filters { flex-direction: column; align-items: stretch; }
    .ms-search-input { min-width: 0; width: 100%; }
  }
</style>

<div class="content-toolbar">
  <button class="btn btn-outline btn-sm" onclick="arrangeTasks()"><i class="fas fa-tasks"></i> Arrange Shipment Tasks</button>
  <button class="btn btn-primary btn-sm" onclick="massArrangePickup()" id="mass-pickup-btn" disabled><i class="fas fa-truck"></i> Mass Arrange Pickup</button>
</div>

      <div class="tabs" id="ms-tabs">
        <button class="tab active" data-tab="shipping">Orders to Ship <span class="tab-count" id="count-shipping">0</span></button>
        <button class="tab" data-tab="documents">Generate Documents <span class="tab-count" id="count-docs">0</span></button>
      </div>

      <div class="ms-filters">
        <div class="ms-filter-group">
          <span class="ms-filter-label">Priority</span>
          <button class="ms-filter-chip active" data-priority="all">All</button>
          <button class="ms-filter-chip" data-priority="overdue">Overdue</button>
          <button class="ms-filter-chip" data-priority="today">Ship Today</button>
          <button class="ms-filter-chip" data-priority="tomorrow">Ship Tomorrow</button>
        </div>
        <div class="ms-filter-group">
          <span class="ms-filter-label">Channel</span>
          <button class="ms-filter-chip active" data-channel="all">All</button>
          <button class="ms-filter-chip" data-channel="spx">SPX Express</button>
          <button class="ms-filter-chip" data-channel="pickup">Self Pick-up</button>
          <button class="ms-filter-chip" data-channel="other">Other Logistics</button>
        </div>
        <div class="ms-filter-group">
          <span class="ms-filter-label">Pre-Order</span>
          <button class="ms-filter-chip active" data-preorder="all">All</button>
          <button class="ms-filter-chip" data-preorder="no">Non Pre-Order</button>
          <button class="ms-filter-chip" data-preorder="yes">Pre-Order</button>
        </div>
        <button class="ms-expand-filters" onclick="toggleExpandFilters()"><i class="fas fa-sliders-h"></i> Expand Filters <i class="fas fa-chevron-down" id="expand-icon"></i></button>
        <input type="text" class="ms-search-input" id="search-input" placeholder="Search Order ID..." onkeyup="debounceSearch()">
        <button class="btn btn-outline btn-sm" onclick="applyFilters()"><i class="fas fa-search"></i> Apply</button>
        <button class="btn btn-ghost btn-sm" onclick="resetFilters()"><i class="fas fa-undo"></i> Reset</button>
      </div>

      <div class="ms-content">
        <div class="ms-table-card">
          <div class="ms-table-header">
            <h3><i class="fas fa-boxes" style="color:var(--primary);margin-right:0.5rem;"></i> Orders to Ship</h3>
            <label class="ms-select-all">
              <input type="checkbox" id="select-all" onchange="toggleSelectAll()"> Select All
            </label>
          </div>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th style="width:40px;"></th>
                  <th>Product</th>
                  <th>Order ID</th>
                  <th>Buyer</th>
                  <th>Shipping Channel</th>
                  <th onclick="sortBy('confirmed')">Confirmed Time <i class="fas fa-sort"></i></th>
                  <th>Order Status</th>
                </tr>
              </thead>
              <tbody id="orders-tbody">
                <tr><td colspan="7" style="text-align:center;padding:3rem;color:#94a3b8;">Loading orders...</td></tr>
              </tbody>
            </table>
          </div>
          <div class="pagination" id="pagination"></div>
        </div>

        <div class="ms-pickup-panel">
          <h3><i class="fas fa-map-marker-alt"></i> Mass Arrange Pickup</h3>
          <div class="ms-pickup-stat">
            <span class="ms-pickup-label">Selected Parcels</span>
            <span class="ms-pickup-value" id="selected-count">0</span>
          </div>
          <div class="ms-pickup-stat">
            <span class="ms-pickup-label">Total Weight</span>
            <span class="ms-pickup-value" id="total-weight">0 kg</span>
          </div>
          <div class="ms-pickup-address">
            <strong>Pickup Address</strong>
            Inkzion Spectrum Ads Office<br>
            123 Printing Street, Brgy. San Jose<br>
            Quezon City, Metro Manila 1100<br>
            Philippines
          </div>
          <div class="ms-pickup-info">
            <i class="fas fa-info-circle"></i>
            <strong>Courier Pickup:</strong> J&T Express / LBC Express<br>
            Same-day pickup available for orders placed before 2PM.
          </div>
          <div class="ms-pickup-hours">
            <i class="fas fa-clock"></i>
            Operating Hours: Mon-Fri 9AM-6PM, Sat 9AM-12PM
            <a href="#" onclick="alert('Operating hours: Mon-Fri 9AM-6PM, Sat 9AM-12PM'); return false;">View Details</a>
          </div>
          <div class="ms-pickup-actions">
            <button class="btn btn-primary" onclick="massArrangePickup()" id="panel-pickup-btn" disabled>
              <i class="fas fa-truck"></i> Mass Arrange Pickup
            </button>
            <button class="btn btn-outline" onclick="generateDocuments()" id="gen-docs-btn" disabled>
              <i class="fas fa-file-alt"></i> Generate Documents
            </button>
          </div>
        </div>
      </div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>




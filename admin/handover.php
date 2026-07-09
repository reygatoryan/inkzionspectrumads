<?php
$pageTitle = 'Handover Centre';
$pageSubtitle = 'Manage pickups and drop-offs - track drivers, schedule pickups, and monitor handover status.';
require 'includes/admin-header.php';
?>
<style>
  /* ===== TABS ===== */
  .tabs {
    display: flex; gap: 0.25rem; background: white;
    border: 1px solid #e8ecf1; border-radius: 14px; padding: 0.35rem;
    margin-bottom: 1rem; overflow-x: auto;
  }
  .tab {
    flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.1rem;
    padding: 0.55rem 0.6rem; border-radius: 10px; border: none;
    background: transparent; cursor: pointer;
    font-size: 0.75rem; font-weight: 600; color: #64748b;
    transition: all 0.2s ease; white-space: nowrap; min-width: 0;
  }
  .tab:hover { background: rgba(233,30,142,0.04); }
  .tab.active { background: linear-gradient(135deg, #e91e8c, #9c27b0); color: white; }
  .tab .tab-count {
    font-size: 0.65rem; background: rgba(0,0,0,0.08);
    padding: 0.05rem 0.4rem; border-radius: 999px; font-weight: 700;
  }
  .tab.active .tab-count { background: rgba(255,255,255,0.25); }

  /* ===== SUBTABS ===== */
  .sh-subtabs {
    display: flex; gap: 0.5rem; margin-bottom: 1rem;
  }
  .sh-subtab {
    padding: 0.4rem 0.85rem; border-radius: 8px;
    border: 1px solid #e2e8f0; background: white;
    font-size: 0.75rem; font-weight: 600; color: #475569;
    cursor: pointer; transition: all 0.15s ease;
  }
  .sh-subtab:hover { border-color: #e91e8c; color: #e91e8c; }
  .sh-subtab.active { background: rgba(233,30,142,0.08); border-color: #e91e8c; color: #e91e8c; }

  /* ===== SCHEDULE CARD ===== */
  .sh-schedule-card {
    background: white; border: 1px solid #e8ecf1;
    border-radius: 16px; padding: 1.25rem;
    margin-bottom: 1rem; display: flex;
    justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 1rem;
  }
  .sh-schedule-info { display: flex; align-items: center; gap: 1rem; }
  .sh-schedule-icon {
    width: 48px; height: 48px; border-radius: 12px;
    background: rgba(59,130,246,0.1); color: #3b82f6;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
  }
  .sh-schedule-details h3 { font-size: 0.95rem; font-weight: 700; color: #1a1a2e; margin-bottom: 0.25rem; }
  .sh-schedule-details p { font-size: 0.82rem; color: #64748b; }
  .sh-schedule-actions { display: flex; gap: 0.5rem; }

  /* ===== TABLE ===== */
  .sh-table-card {
    background: white; border: 1px solid #e8ecf1;
    border-radius: 16px; overflow: hidden;
  }
  .sh-table-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9;
  }
  .sh-table-header h3 { font-size: 0.95rem; font-weight: 700; color: #1a1a2e; }
  .table-wrap { overflow-x: auto; }
  .table {
    width: 100%; border-collapse: collapse; font-size: 0.8rem;
  }
  .table th {
    background: #f8fafc; padding: 0.65rem 1rem; text-align: left;
    font-weight: 700; color: #475569; font-size: 0.72rem;
    text-transform: uppercase; letter-spacing: 0.05em;
    border-bottom: 1px solid #e8ecf1; cursor: pointer;
    user-select: none; white-space: nowrap;
  }
  .table th:hover { color: #e91e8c; }
  .table th i { font-size: 0.65rem; margin-left: 0.3rem; opacity: 0.5; }
  .table td {
    padding: 0.65rem 1rem; border-bottom: 1px solid #f1f5f9;
    color: #1a1a2e; vertical-align: middle;
  }
  .table tr:last-child td { border-bottom: none; }
  .table tr:hover td { background: #f8fafc; }
  .sh-date { font-size: 0.82rem; font-weight: 600; color: #1a1a2e; white-space: nowrap; }
  .sh-courier {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.2rem 0.6rem; border-radius: 999px;
    font-size: 0.7rem; font-weight: 600;
    background: rgba(59,130,246,0.1); color: #1d4ed8;
  }
  .sh-driver {
    display: flex; align-items: center; gap: 0.5rem;
  }
  .sh-driver-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: linear-gradient(135deg, #e91e8c, #00bcd4);
    color: white; display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; font-weight: 700;
  }
  .sh-driver-info { }
  .sh-driver-name { font-size: 0.78rem; font-weight: 600; color: #1a1a2e; }
  .sh-driver-contact { font-size: 0.68rem; color: #64748b; }
  .sh-count {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.2rem 0.6rem; border-radius: 999px;
    font-size: 0.7rem; font-weight: 700;
    cursor: pointer; transition: all 0.15s ease;
  }
  .sh-count:hover { transform: scale(1.05); }
  .sh-count.planned { background: rgba(59,130,246,0.12); color: #1d4ed8; }
  .sh-count.completed { background: rgba(5,150,105,0.12); color: #047857; }
  .sh-count.pending { background: rgba(249,115,22,0.12); color: #c2410c; }
  .status {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.2rem 0.65rem; border-radius: 999px;
    font-size: 0.7rem; font-weight: 700;
  }
  .status.upcoming { background: rgba(59,130,246,0.12); color: #1d4ed8; }
  .status.completed { background: rgba(5,150,105,0.12); color: #047857; }
  .status.in_progress { background: rgba(139,92,246,0.12); color: #7c3aed; }

  /* ===== PAGINATION ===== */
  .pagination {
    display: flex; justify-content: center; align-items: center;
    gap: 0.35rem; margin-top: 1.5rem;
  }
  .page-btn {
    width: 36px; height: 36px; border-radius: 10px;
    border: 1px solid #e2e8f0; background: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem; font-weight: 600; color: #475569;
    cursor: pointer; transition: all 0.15s ease;
  }
  .page-btn:hover { border-color: #e91e8c; color: #e91e8c; }
  .page-btn.active { background: linear-gradient(135deg, #e91e8c, #9c27b0); color: white; border-color: transparent; }
  .page-btn:disabled { opacity: 0.4; cursor: not-allowed; }

  /* ===== EMPTY STATE ===== */
  .empty-state {
    text-align: center; padding: 3rem 2rem;
    background: white; border: 2px dashed #e8ecf1;
    border-radius: 20px;
  }
  .empty-state .sh-empty-icon {
    font-size: 3rem; color: rgba(233,30,142,0.15); margin-bottom: 1rem;
  }
  .empty-state h3 { color: #1a1a2e; margin: 0 0 0.5rem; font-size: 1.1rem; }
  .empty-state p { color: #64748b; margin: 0; font-size: 0.85rem; }

  /* ===== LOADING ===== */
  .sh-loading {
    text-align: center; padding: 3rem;
    color: #94a3b8; font-size: 0.9rem;
  }
  .sh-loading i { font-size: 2rem; margin-bottom: 0.75rem; display: block; }

  /* ===== RESPONSIVE ===== */
  @media (max-width: 1200px) {
    .sh-schedule-card { flex-direction: column; align-items: flex-start; }
  }
  @media (max-width: 768px) {
    .sh-subtabs { flex-wrap: wrap; }
  }
</style>

<div class="tabs" id="sh-tabs">
  <button class="tab active" data-tab="pickup">Pickup <span class="tab-count" id="count-pickup">0</span></button>
  <button class="tab" data-tab="dropoff">Drop-off <span class="tab-count" id="count-dropoff">0</span></button>
</div>

<div class="sh-schedule-card">
  <div class="sh-schedule-info">
    <div class="sh-schedule-icon"><i class="fas fa-clock"></i></div>
    <div class="sh-schedule-details">
      <h3>Today's Pickup Schedule</h3>
      <p><strong>Next Available:</strong> 2:00 PM - 4:00 PM (J&T Express)</p>
      <p style="font-size:0.75rem;color:#94a3b8;margin-top:0.2rem;">Last updated: Just now</p>
    </div>
  </div>
  <div class="sh-schedule-actions">
    <button class="btn btn-outline btn-sm" onclick="changeSchedule()"><i class="fas fa-edit"></i> Change Schedule</button>
    <button class="btn btn-primary btn-sm" onclick="refreshSchedule()"><i class="fas fa-sync-alt"></i> Refresh</button>
  </div>
</div>

<div class="sh-subtabs" id="sh-subtabs">
  <button class="sh-subtab active" data-subtab="upcoming">Upcoming Pickup</button>
  <button class="sh-subtab" data-subtab="completed">Completed Pickup</button>
</div>

<div class="sh-table-card">
  <div class="sh-table-header">
    <h3><i class="fas fa-truck" style="color:#e91e8c;margin-right:0.5rem;"></i> <span id="table-title">Upcoming Pickups</span></h3>
    <span style="font-size:0.72rem;color:#94a3b8;" id="table-count">0 records</span>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Pickup Date</th>
          <th>Courier</th>
          <th>Pickup Driver</th>
          <th onclick="sortBy('planned')">Planned <i class="fas fa-sort"></i></th>
          <th onclick="sortBy('completed')">Completed <i class="fas fa-sort"></i></th>
          <th onclick="sortBy('pending')">Pending <i class="fas fa-sort"></i></th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody id="handover-tbody">
        <tr><td colspan="7" style="text-align:center;padding:3rem;color:#94a3b8;">Loading handover data...</td></tr>
      </tbody>
    </table>
  </div>
  <div class="pagination" id="pagination"></div>
</div>

<script>
  let currentTab = 'pickup';
  let currentSubtab = 'upcoming';
  let currentPage = 1;
  let searchTimeout = null;
  let totalPages = 1;
  let sortField = 'date';
  let sortDir = 'desc';

  // Simulated handover data
  const handoverData = {
    pickup: {
      upcoming: [
        { id: 1, date: '2026-06-24 14:00', courier: 'J&T Express', driver: 'Juan Dela Cruz', driverContact: '09XX-XXX-XXXX1', driverAvatar: 'JD', planned: 15, completed: 0, pending: 15, status: 'upcoming' },
        { id: 2, date: '2026-06-24 16:00', courier: 'LBC Express', driver: 'Maria Santos', driverContact: '09XX-XXX-XXXX2', driverAvatar: 'MS', planned: 8, completed: 0, pending: 8, status: 'upcoming' },
        { id: 3, date: '2026-06-25 09:00', courier: 'J&T Express', driver: 'Pedro Reyes', driverContact: '09XX-XXX-XXXX3', driverAvatar: 'PR', planned: 12, completed: 0, pending: 12, status: 'upcoming' },
        { id: 4, date: '2026-06-25 14:00', courier: 'SPX Express', driver: 'Ana Garcia', driverContact: '09XX-XXX-XXXX4', driverAvatar: 'AG', planned: 20, completed: 0, pending: 20, status: 'upcoming' },
        { id: 5, date: '2026-06-26 10:00', courier: 'J&T Express', driver: 'Carlos Mendoza', driverContact: '09XX-XXX-XXXX5', driverAvatar: 'CM', planned: 5, completed: 0, pending: 5, status: 'upcoming' },
      ],
      completed: [
        { id: 101, date: '2026-06-23 14:00', courier: 'J&T Express', driver: 'Juan Dela Cruz', driverContact: '09XX-XXX-XXXX1', driverAvatar: 'JD', planned: 10, completed: 10, pending: 0, status: 'completed' },
        { id: 102, date: '2026-06-23 09:00', courier: 'LBC Express', driver: 'Maria Santos', driverContact: '09XX-XXX-XXXX2', driverAvatar: 'MS', planned: 6, completed: 6, pending: 0, status: 'completed' },
        { id: 103, date: '2026-06-22 16:00', courier: 'J&T Express', driver: 'Pedro Reyes', driverContact: '09XX-XXX-XXXX3', driverAvatar: 'PR', planned: 15, completed: 15, pending: 0, status: 'completed' },
      ]
    },
    dropoff: {
      upcoming: [
        { id: 201, date: '2026-06-24 10:00', courier: 'J&T Express', driver: 'Luis Torres', driverContact: '09XX-XXX-XXXX6', driverAvatar: 'LT', planned: 8, completed: 0, pending: 8, status: 'upcoming' },
        { id: 202, date: '2026-06-25 11:00', courier: 'LBC Express', driver: 'Sofia Ramos', driverContact: '09XX-XXX-XXXX7', driverAvatar: 'SR', planned: 12, completed: 0, pending: 12, status: 'upcoming' },
      ],
      completed: [
        { id: 301, date: '2026-06-23 15:00', courier: 'J&T Express', driver: 'Luis Torres', driverContact: '09XX-XXX-XXXX6', driverAvatar: 'LT', planned: 5, completed: 5, pending: 0, status: 'completed' },
      ]
    }
  };

  function getCurrentData() {
    if (currentTab === 'pickup') {
      return currentSubtab === 'upcoming' ? handoverData.pickup.upcoming : handoverData.pickup.completed;
    } else {
      return currentSubtab === 'upcoming' ? handoverData.dropoff.upcoming : handoverData.dropoff.completed;
    }
  }

  function updateCounts() {
    const pickupUpcoming = handoverData.pickup.upcoming.length;
    const pickupCompleted = handoverData.pickup.completed.length;
    const dropoffUpcoming = handoverData.dropoff.upcoming.length;
    const dropoffCompleted = handoverData.dropoff.completed.length;

    document.getElementById('count-pickup').textContent = pickupUpcoming + pickupCompleted;
    document.getElementById('count-dropoff').textContent = dropoffUpcoming + dropoffCompleted;
  }

  function renderTable() {
    const tbody = document.getElementById('handover-tbody');
    const data = getCurrentData();

    document.getElementById('table-title').textContent = currentSubtab === 'upcoming' ? 'Upcoming Pickups' : 'Completed Pickups';
    document.getElementById('table-count').textContent = data.length + ' records';

    if (!data || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><div class="sh-empty-icon"><i class="fas fa-inbox"></i></div><h3>No records found</h3><p>Handover records will appear here.</p></div></td></tr>';
      return;
    }

    tbody.innerHTML = data.map(item => {
      const statusClass = item.status;
      const statusLabel = item.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
      const dateObj = new Date(item.date);
      const dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      const timeStr = dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

      return `
        <tr>
          <td><span class="sh-date">${dateStr}<br><small style="color:#94a3b8;font-weight:400;">${timeStr}</small></span></td>
          <td><span class="sh-courier"><i class="fas fa-truck"></i> ${item.courier}</span></td>
          <td>
            <div class="sh-driver">
              <div class="sh-driver-avatar">${item.driverAvatar}</div>
              <div class="sh-driver-info">
                <div class="sh-driver-name">${item.driver}</div>
                <div class="sh-driver-contact">${item.driverContact}</div>
              </div>
            </div>
          </td>
          <td><span class="sh-count planned" onclick="viewDetails(${item.id}, 'planned')">${item.planned} <i class="fas fa-box"></i></span></td>
          <td><span class="sh-count completed" onclick="viewDetails(${item.id}, 'completed')">${item.completed} <i class="fas fa-check"></i></span></td>
          <td><span class="sh-count pending" onclick="viewDetails(${item.id}, 'pending')">${item.pending} <i class="fas fa-clock"></i></span></td>
          <td><span class="status ${statusClass}">${statusLabel}</span></td>
        </tr>
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
    renderTable();
    renderPagination();
  }

  function sortBy(field) {
    if (sortField === field) {
      sortDir = sortDir === 'asc' ? 'desc' : 'asc';
    } else {
      sortField = field;
      sortDir = 'desc';
    }
    renderTable();
  }

  function viewDetails(id, type) {
    alert(`View details for handover #${id} - ${type} items.\n\nFull details page coming soon.`);
  }

  // Tab switching
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', function() {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      this.classList.add('active');
      currentTab = this.dataset.tab;
      currentPage = 1;

      const subtabs = document.getElementById('sh-subtabs');
      if (currentTab === 'pickup') {
        subtabs.style.display = 'flex';
        currentSubtab = 'upcoming';
        document.querySelectorAll('.sh-subtab').forEach(t => t.classList.remove('active'));
        document.querySelector('.sh-subtab[data-subtab="upcoming"]').classList.add('active');
      } else {
        subtabs.style.display = 'none';
        currentSubtab = 'upcoming';
      }

      renderTable();
    });
  });

  // Subtab switching
  document.querySelectorAll('.sh-subtab').forEach(subtab => {
    subtab.addEventListener('click', function() {
      document.querySelectorAll('.sh-subtab').forEach(t => t.classList.remove('active'));
      this.classList.add('active');
      currentSubtab = this.dataset.subtab;
      currentPage = 1;
      renderTable();
    });
  });

  function schedulePickup() {
    alert('Schedule Pickup - Select date, time, and courier. Feature coming soon.');
  }

  function changeSchedule() {
    alert('Change Schedule - Modify pickup time slot. Feature coming soon.');
  }

  function refreshSchedule() {
    alert('Refreshing schedule...');
    updateCounts();
    renderTable();
  }

  function assignDriver() {
    alert('Assign Driver - Select driver for upcoming pickup. Feature coming soon.');
  }

  // Init
  document.addEventListener('DOMContentLoaded', function() {
    updateCounts();
    renderTable();
    renderPagination();
  });
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

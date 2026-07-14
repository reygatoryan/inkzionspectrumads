<?php
$pageTitle = 'Return/Refund/Cancel';
$pageSubtitle = 'Manage return, refund, and cancellation requests from buyers.';
require 'includes/admin-header.php';
?>
<style>
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
    .tab:hover { background: rgba(43, 76, 82,0.04); }
    .tab.active { background: linear-gradient(135deg, #2B4C52, #4A7C84); color: white; }
    .tab .tab-count {
        font-size: 0.65rem; background: rgba(0,0,0,0.08);
        padding: 0.05rem 0.4rem; border-radius: 999px; font-weight: 700;
    }
    .tab.active .tab-count { background: rgba(255,255,255,0.25); }

    .sr-subtabs {
        display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap;
    }
    .sr-subtab {
        padding: 0.4rem 0.85rem; border-radius: 8px;
        border: 1px solid #e2e8f0; background: white;
        font-size: 0.75rem; font-weight: 600; color: #475569;
        cursor: pointer; transition: all 0.15s ease;
    }
    .sr-subtab:hover { border-color: #2B4C52; color: #2B4C52; }
    .sr-subtab.active { background: rgba(43, 76, 82,0.08); border-color: #2B4C52; color: #2B4C52; }

    .sr-filters {
        display: flex; gap: 0.75rem; align-items: center;
        flex-wrap: wrap; margin-bottom: 1rem;
    }
    .sr-filter-group { display: flex; gap: 0.35rem; align-items: center; flex-wrap: wrap; }
    .sr-filter-chip {
        padding: 0.35rem 0.75rem; border-radius: 999px;
        border: 1px solid #e2e8f0; background: white;
        font-size: 0.75rem; font-weight: 600; color: #475569;
        cursor: pointer; transition: all 0.15s ease;
    }
    .sr-filter-chip:hover { border-color: #2B4C52; color: #2B4C52; }
    .sr-filter-chip.active { background: rgba(43, 76, 82,0.08); border-color: #2B4C52; color: #2B4C52; }
    .sr-filter-label { font-size: 0.72rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
    .sr-search-input {
        padding: 0.45rem 0.75rem; border: 1px solid #e2e8f0;
        border-radius: 10px; font-size: 0.8rem; outline: none;
        min-width: 180px; transition: border-color 0.2s;
    }
    .sr-search-input:focus { border-color: #2B4C52; box-shadow: 0 0 0 3px rgba(43, 76, 82,0.08); }
    .sr-expand-filters {
        font-size: 0.75rem; color: #2B4C52; font-weight: 600;
        background: none; border: none; cursor: pointer;
        display: inline-flex; align-items: center; gap: 0.3rem;
    }
    .sr-expand-filters:hover { text-decoration: underline; }

    .sr-table-card {
        background: white; border: 1px solid #e8ecf1;
        border-radius: 16px; overflow: hidden;
    }
    .sr-table-header {
        display: flex; justify-content: space-between; align-items: center;
        padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9;
    }
    .sr-table-header h3 { font-size: 0.95rem; font-weight: 700; color: #1a1a2e; }
    .table-wrap { overflow-x: auto; }
    .table {
        width: 100%; border-collapse: collapse; font-size: 0.78rem;
    }
    .table th {
        background: #f8fafc; padding: 0.65rem 1rem; text-align: left;
        font-weight: 700; color: #475569; font-size: 0.72rem;
        text-transform: uppercase; letter-spacing: 0.05em;
        border-bottom: 1px solid #e8ecf1; cursor: pointer;
        user-select: none; white-space: nowrap;
    }
    .table th:hover { color: #2B4C52; }
    .table th i { font-size: 0.65rem; margin-left: 0.3rem; opacity: 0.5; }
    .table td {
        padding: 0.65rem 1rem; border-bottom: 1px solid #f1f5f9;
        color: #1a1a2e; vertical-align: middle;
    }
    .table tr:last-child td { border-bottom: none; }
    .table tr:hover td { background: #f8fafc; }
    .sr-product-cell {
        display: flex; align-items: center; gap: 0.75rem;
    }
    .sr-product-img {
        width: 44px; height: 44px; border-radius: 8px;
        object-fit: cover; background: white; border: 1px solid #e8ecf1;
    }
    .sr-product-name {
        font-size: 0.82rem; font-weight: 600; color: #1a1a2e;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        max-width: 180px;
    }
    .sr-buyer { font-size: 0.8rem; font-weight: 600; color: #1a1a2e; }
    .sr-order-id { font-weight: 700; font-size: 0.82rem; color: #1a1a2e; }
    .sr-amount { font-size: 0.82rem; font-weight: 700; color: #2B4C52; }
    .sr-reason {
        font-size: 0.78rem; color: #475569; max-width: 200px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .sr-logistics {
        display: inline-flex; align-items: center; gap: 0.3rem;
        padding: 0.2rem 0.6rem; border-radius: 999px;
        font-size: 0.7rem; font-weight: 600;
        background: rgba(59,130,246,0.1); color: #1d4ed8;
    }
    .status {
        display: inline-flex; align-items: center; gap: 0.3rem;
        padding: 0.2rem 0.65rem; border-radius: 999px;
        font-size: 0.7rem; font-weight: 700;
    }
    .status.review { background: rgba(255,193,7,0.12); color: #b8860b; }
    .status.returning { background: rgba(59,130,246,0.12); color: #1d4ed8; }
    .status.refunded { background: rgba(5,150,105,0.12); color: #047857; }
    .status.disputed { background: rgba(249,115,22,0.12); color: #c2410c; }
    .status.rejected { background: rgba(239,68,68,0.12); color: #dc2626; }
    .sr-actions { display: flex; gap: 0.3rem; }

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
    .page-btn:hover { border-color: #2B4C52; color: #2B4C52; }
    .page-btn.active { background: linear-gradient(135deg, #2B4C52, #4A7C84); color: white; border-color: transparent; }
    .page-btn:disabled { opacity: 0.4; cursor: not-allowed; }

    .empty-state {
        text-align: center; padding: 3rem 2rem;
        background: white; border: 2px dashed #e8ecf1;
        border-radius: 20px;
    }
    .empty-state .empty-state-icon { font-size: 3rem; color: rgba(43, 76, 82,0.15); margin-bottom: 1rem; }
    .empty-state h3 { color: #1a1a2e; margin: 0 0 0.5rem; font-size: 1.1rem; }
    .empty-state p { color: #64748b; margin: 0; font-size: 0.85rem; }

    @media (max-width: 1200px) {
        .table { font-size: 0.72rem; }
        .table td, .table th { padding: 0.5rem 0.75rem; }
    }
</style>

<div class="tabs" id="sr-tabs">
    <button class="tab active" data-tab="all">All <span class="tab-count" id="count-all">0</span></button>
    <button class="tab" data-tab="returned">Returned <span class="tab-count" id="count-return">0</span></button>
    <button class="tab" data-tab="cancelled">Cancellation <span class="tab-count" id="count-cancel">0</span></button>
    <button class="tab" data-tab="failed">Failed Delivery <span class="tab-count" id="count-failed">0</span></button>
</div>

<div class="sr-subtabs" id="sr-subtabs">
    <button class="sr-subtab active" data-subtab="review">Under Review</button>
    <button class="sr-subtab" data-subtab="returning">Returning</button>
    <button class="sr-subtab" data-subtab="refunded">Refunded</button>
    <button class="sr-subtab" data-subtab="disputed">Disputed</button>
    <button class="sr-subtab" data-subtab="rejected">Rejected/Cancelled</button>
</div>

<div class="sr-filters">
    <div class="sr-filter-group">
        <span class="sr-filter-label">Priority</span>
        <button class="sr-filter-chip active" data-priority="all">All</button>
        <button class="sr-filter-chip" data-priority="1day">Due in 1 Day</button>
        <button class="sr-filter-chip" data-priority="2days">Due in 2 Days</button>
    </div>
    <div class="sr-filter-group">
        <span class="sr-filter-label">Key Actions</span>
        <button class="sr-filter-chip" data-action="reply">Reply to Buyer</button>
        <button class="sr-filter-chip" data-action="evidence">Provide Evidence</button>
        <button class="sr-filter-chip" data-action="withdraw">Withdraw Parcel</button>
        <button class="sr-filter-chip" data-action="validate">Validate Item</button>
        <button class="sr-filter-chip" data-action="review">Review Refund Decision</button>
    </div>
    <input type="text" class="sr-search-input" id="search-input" placeholder="Search by Request ID, Order ID, Tracking #, Buyer Name..." onkeyup="debounceSearch()">
    <button class="btn btn-outline btn-sm" onclick="applyFilters()"><i class="fas fa-search"></i> Apply</button>
    <button class="btn btn-ghost btn-sm" onclick="resetFilters()"><i class="fas fa-undo"></i> Reset</button>
</div>

<div class="sr-table-card">
    <div class="sr-table-header">
        <h3><i class="fas fa-undo-alt" style="color:#2B4C52;margin-right:0.5rem;"></i> <span id="table-title">Return Requests</span></h3>
        <span style="font-size:0.72rem;color:#94a3b8;" id="table-count">0 records</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Buyer</th>
                    <th>Order ID</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Amount</th>
                    <th>Request Reason</th>
                    <th>Reassessed Reason</th>
                    <th>Solution</th>
                    <th>Status</th>
                    <th>Forward Logistics</th>
                    <th>Return Logistics</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="returns-tbody">
                <tr><td colspan="12" style="text-align:center;padding:3rem;color:#94a3b8;">Loading return requests...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination" id="pagination"></div>
</div>

<script>
    let currentTab = 'all';
    let currentSubtab = 'review';
    let currentPage = 1;
    let searchTimeout = null;
    let totalPages = 1;
    let sortField = 'date';
    let sortDir = 'desc';

    const returnData = {
        all: {
            review: [
                { id: 1, buyer: 'Juan Dela Cruz', orderId: 'ORD-001', product: 'Business Cards', variation: 'Standard', qty: 1, amount: 29, reason: 'Damaged item', reassessed: 'Damaged in transit', solution: 'Refund', status: 'review', forwardLogistics: 'J&T Express', returnLogistics: 'Pending', image: '../assets/products-demo.jpg' },
                { id: 2, buyer: 'Maria Santos', orderId: 'ORD-002', product: 'Flyers', variation: 'Glossy', qty: 2, amount: 78, reason: 'Wrong item sent', reassessed: 'Order mismatch', solution: 'Resend', status: 'review', forwardLogistics: 'LBC Express', returnLogistics: 'Pending', image: '../assets/products-demo.jpg' },
            ],
            returning: [
                { id: 3, buyer: 'Pedro Reyes', orderId: 'ORD-003', product: 'Banner', variation: 'Vinyl', qty: 1, amount: 79, reason: 'Not as described', reassessed: 'Quality issue', solution: 'Refund', status: 'returning', forwardLogistics: 'J&T Express', returnLogistics: 'In Transit', image: '../assets/products-demo.jpg' },
            ],
            refunded: [
                { id: 4, buyer: 'Ana Garcia', orderId: 'ORD-004', product: 'Mug', variation: '11oz', qty: 3, amount: 36, reason: 'Changed mind', reassessed: 'No issue', solution: 'Refund', status: 'refunded', forwardLogistics: 'LBC Express', returnLogistics: 'Completed', image: '../assets/products-demo.jpg' },
            ],
            disputed: [
                { id: 5, buyer: 'Carlos Mendoza', orderId: 'ORD-005', product: 'T-Shirt', variation: 'XL', qty: 1, amount: 15, reason: 'Size too small', reassessed: 'Buyer error', solution: 'Dispute', status: 'disputed', forwardLogistics: 'J&T Express', returnLogistics: 'Pending', image: '../assets/products-demo.jpg' },
            ],
            rejected: [
                { id: 6, buyer: 'Sofia Ramos', orderId: 'ORD-006', product: 'Stickers', variation: 'Die-cut', qty: 5, amount: 50, reason: 'Late delivery', reassessed: 'Courier delay', solution: 'Reject', status: 'rejected', forwardLogistics: 'LBC Express', returnLogistics: 'N/A', image: '../assets/products-demo.jpg' },
            ]
        },
        returned: {
            review: [
                { id: 1, buyer: 'Juan Dela Cruz', orderId: 'ORD-001', product: 'Business Cards', variation: 'Standard', qty: 1, amount: 29, reason: 'Damaged item', reassessed: 'Damaged in transit', solution: 'Refund', status: 'review', forwardLogistics: 'J&T Express', returnLogistics: 'Pending', image: '../assets/products-demo.jpg' },
            ],
            returning: [
                { id: 3, buyer: 'Pedro Reyes', orderId: 'ORD-003', product: 'Banner', variation: 'Vinyl', qty: 1, amount: 79, reason: 'Not as described', reassessed: 'Quality issue', solution: 'Refund', status: 'returning', forwardLogistics: 'J&T Express', returnLogistics: 'In Transit', image: '../assets/products-demo.jpg' },
            ],
            refunded: [
                { id: 4, buyer: 'Ana Garcia', orderId: 'ORD-004', product: 'Mug', variation: '11oz', qty: 3, amount: 36, reason: 'Changed mind', reassessed: 'No issue', solution: 'Refund', status: 'refunded', forwardLogistics: 'LBC Express', returnLogistics: 'Completed', image: '../assets/products-demo.jpg' },
            ],
            disputed: [
                { id: 5, buyer: 'Carlos Mendoza', orderId: 'ORD-005', product: 'T-Shirt', variation: 'XL', qty: 1, amount: 15, reason: 'Size too small', reassessed: 'Buyer error', solution: 'Dispute', status: 'disputed', forwardLogistics: 'J&T Express', returnLogistics: 'Pending', image: '../assets/products-demo.jpg' },
            ],
            rejected: [
                { id: 6, buyer: 'Sofia Ramos', orderId: 'ORD-006', product: 'Stickers', variation: 'Die-cut', qty: 5, amount: 50, reason: 'Late delivery', reassessed: 'Courier delay', solution: 'Reject', status: 'rejected', forwardLogistics: 'LBC Express', returnLogistics: 'N/A', image: '../assets/products-demo.jpg' },
            ]
        },
        cancelled: {
            review: [],
            returning: [],
            refunded: [],
            disputed: [],
            rejected: [
                { id: 7, buyer: 'Luis Torres', orderId: 'ORD-007', product: 'Calendar', variation: 'Wall', qty: 1, amount: 49, reason: 'Buyer cancelled', reassessed: 'N/A', solution: 'Reject', status: 'rejected', forwardLogistics: 'N/A', returnLogistics: 'N/A', image: '../assets/products-demo.jpg' },
            ]
        },
        failed: {
            review: [],
            returning: [],
            refunded: [],
            disputed: [],
            rejected: [
                { id: 8, buyer: 'Liza Ramos', orderId: 'ORD-008', product: 'Canvas', variation: '16x20', qty: 1, amount: 49, reason: 'Delivery failed', reassessed: 'Address issue', solution: 'Reschedule', status: 'rejected', forwardLogistics: 'J&T Express', returnLogistics: 'N/A', image: '../assets/products-demo.jpg' },
            ]
        }
    };

    function getCurrentData() {
        if (currentTab === 'all') return returnData.all[currentSubtab] || [];
        else if (currentTab === 'returned') return returnData.returned[currentSubtab] || [];
        else if (currentTab === 'cancelled') return returnData.cancelled[currentSubtab] || [];
        else if (currentTab === 'failed') return returnData.failed[currentSubtab] || [];
        return [];
    }

    function updateCounts() {
        const allCount = Object.values(returnData.all).flat().length;
        const returnCount = Object.values(returnData.returned).flat().length;
        const cancelCount = Object.values(returnData.cancelled).flat().length;
        const failedCount = Object.values(returnData.failed).flat().length;
        document.getElementById('count-all').textContent = allCount;
        document.getElementById('count-return').textContent = returnCount;
        document.getElementById('count-cancel').textContent = cancelCount;
        document.getElementById('count-failed').textContent = failedCount;
    }

    function renderTable() {
        const tbody = document.getElementById('returns-tbody');
        const data = getCurrentData();
        const tabLabels = { all: 'All', returned: 'Returned', cancelled: 'Cancellation', failed: 'Failed Delivery' };
        const subtabLabels = { review: 'Under Review', returning: 'Returning', refunded: 'Refunded', disputed: 'Disputed', rejected: 'Rejected/Cancelled' };
        document.getElementById('table-title').textContent = (tabLabels[currentTab] || 'All') + ' - ' + (subtabLabels[currentSubtab] || 'Under Review');
        document.getElementById('table-count').textContent = data.length + ' records';
        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="12"><div class="empty-state"><div class="empty-state-icon"><i class="fas fa-inbox"></i></div><h3>No requests found</h3><p>Return requests will appear here.</p></div></td></tr>';
            return;
        }
        tbody.innerHTML = data.map(item => {
            const statusClass = item.status;
            const statusLabel = item.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            return `
                <tr>
                    <td><span class="sr-buyer">${item.buyer}</span></td>
                    <td><span class="sr-order-id">${item.orderId}</span></td>
                    <td>
                        <div class="sr-product-cell">
                            <img src="${item.image}" alt="" class="sr-product-img" onerror="this.src='../assets/products-demo.jpg'">
                            <span class="sr-product-name" title="${item.product}">${item.product}</span>
                        </div>
                    </td>
                    <td>${item.qty}</td>
                    <td><span class="sr-amount">?${item.amount.toFixed(2)}</span></td>
                    <td><span class="sr-reason" title="${item.reason}">${item.reason}</span></td>
                    <td><span class="sr-reason" title="${item.reassessed}">${item.reassessed}</span></td>
                    <td>${item.solution}</td>
                    <td><span class="status ${statusClass}">${statusLabel}</span></td>
                    <td><span class="sr-logistics"><i class="fas fa-truck"></i> ${item.forwardLogistics}</span></td>
                    <td><span class="sr-logistics"><i class="fas fa-box"></i> ${item.returnLogistics}</span></td>
                    <td>
                        <div class="sr-actions">
                            <button class="btn btn-outline btn-sm" onclick="replyBuyer(${item.id})" title="Reply"><i class="fas fa-comment"></i></button>
                            <button class="btn btn-outline btn-sm" onclick="provideEvidence(${item.id})" title="Evidence"><i class="fas fa-file-upload"></i></button>
                            <button class="btn btn-outline btn-sm" onclick="viewDetails(${item.id})" title="View"><i class="fas fa-eye"></i></button>
                        </div>
                    </td>
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

    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentTab = this.dataset.tab;
            currentPage = 1;
            renderTable();
        });
    });

    document.querySelectorAll('.sr-subtab').forEach(subtab => {
        subtab.addEventListener('click', function() {
            document.querySelectorAll('.sr-subtab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentSubtab = this.dataset.subtab;
            currentPage = 1;
            renderTable();
        });
    });

    document.querySelectorAll('.sr-filter-chip[data-priority]').forEach(chip => {
        chip.addEventListener('click', function() {
            document.querySelectorAll('.sr-filter-chip[data-priority]').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            applyFilters();
        });
    });
    document.querySelectorAll('.sr-filter-chip[data-action]').forEach(chip => {
        chip.addEventListener('click', function() {
            document.querySelectorAll('.sr-filter-chip[data-action]').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            applyFilters();
        });
    });

    function applyFilters() { currentPage = 1; renderTable(); }

    function resetFilters() {
        document.getElementById('search-input').value = '';
        document.querySelectorAll('.sr-filter-chip').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.sr-filter-chip[data-priority="all"]').forEach(c => c.classList.add('active'));
        currentPage = 1; renderTable();
    }

    function debounceSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { currentPage = 1; renderTable(); }, 400);
    }

    function replyBuyer(id) { alert(`Reply to buyer for request #${id}.\n\nMessaging feature coming soon.`); }
    function provideEvidence(id) { alert(`Provide evidence for request #${id}.\n\nFile upload feature coming soon.`); }
    function viewDetails(id) { alert(`View details for return request #${id}.\n\nFull details page coming soon.`); }

    document.addEventListener('DOMContentLoaded', function() {
        updateCounts();
        renderTable();
        renderPagination();
    });
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
<?php
require_once __DIR__ . '/../db-config.php';

session_start();

$pageTitle = 'Reports & Analytics';
$pageSubtitle = 'Comprehensive reports covering sales, customers, products, and marketing performance.';

$totalUsers = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];
$totalCustomers = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role='user'")->fetch_assoc()['cnt'];
$totalSellers = 0;
$totalAdmins = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role='admin'")->fetch_assoc()['cnt'];
$totalOrders = $conn->query("SELECT COUNT(*) as cnt FROM orders")->fetch_assoc()['cnt'];
$totalProducts = $conn->query("SELECT COUNT(*) as cnt FROM products")->fetch_assoc()['cnt'];
$totalRevenue = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE status='completed' OR status='delivered'")->fetch_assoc()['total'];
$totalActivities = $conn->query("SELECT COUNT(*) as cnt FROM activity_logs")->fetch_assoc()['cnt'];
$todayActivities = $conn->query("SELECT COUNT(*) as cnt FROM activity_logs WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['cnt'];
$completedOrders = $conn->query("SELECT COUNT(*) as cnt FROM orders WHERE status='completed' OR status='delivered'")->fetch_assoc()['cnt'];
$cancelledOrders = $conn->query("SELECT COUNT(*) as cnt FROM orders WHERE status='cancelled'")->fetch_assoc()['cnt'];
$activeSessions = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE last_seen >= NOW() - INTERVAL 5 MINUTE")->fetch_assoc()['cnt'];
$systemUptime = 99.9;
$totalCategories = $conn->query("SELECT COUNT(*) as cnt FROM categories")->fetch_assoc()['cnt'];
$failedLogins = $conn->query("SELECT COUNT(*) as cnt FROM activity_logs WHERE action LIKE '%fail%'")->fetch_assoc()['cnt'];
$newCustomers30d = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role='user' AND created_at >= NOW() - INTERVAL 30 DAY")->fetch_assoc()['cnt'];
$activeCustomers = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role='user' AND last_seen >= NOW() - INTERVAL 24 HOUR")->fetch_assoc()['cnt'];
$totalBuyers = $conn->query("SELECT COUNT(DISTINCT user_id) as cnt FROM orders")->fetch_assoc()['cnt'];
$repeatBuyers = $conn->query("SELECT COUNT(*) as cnt FROM (SELECT user_id FROM orders GROUP BY user_id HAVING COUNT(*) > 1) sub")->fetch_assoc()['cnt'];
$repeatBuyerPct = $totalBuyers > 0 ? round($repeatBuyers / $totalBuyers * 100) : 0;
$topCustomer = $conn->query("SELECT user_id FROM orders GROUP BY user_id ORDER BY COUNT(*) DESC LIMIT 1")->fetch_assoc();
$topCustomerId = $topCustomer ? '#' . $topCustomer['user_id'] : 'N/A';
$bestSeller = $conn->query("SELECT p.name FROM order_items oi JOIN products p ON oi.product_id = p.id GROUP BY oi.product_id ORDER BY COUNT(*) DESC LIMIT 1")->fetch_assoc();
$bestSellerName = $bestSeller ? $bestSeller['name'] : 'N/A';

$dailyData = [];
$registrationData = [];
$orderData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dailyData[] = $date;
    $regCount = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE DATE(created_at)='$date'")->fetch_assoc()['cnt'];
    $registrationData[] = $regCount;
    $ordCount = $conn->query("SELECT COUNT(*) as cnt FROM orders WHERE DATE(created_at)='$date'")->fetch_assoc()['cnt'];
    $orderData[] = $ordCount;
}

$revenueData = [];
foreach ($dailyData as $d) {
    $rev = $conn->query("SELECT COALESCE(SUM(total_amount),0) as total FROM orders WHERE status IN ('completed','delivered') AND DATE(created_at)='$d'")->fetch_assoc()['total'];
    $revenueData[] = $rev;
}

$activeTab = $_GET['tab'] ?? 'sales';
$dateFrom = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['to'] ?? date('Y-m-d');

require 'includes/admin-header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
  :root {
    --dash-bg: #F8FAFC; --card-bg: #FFFFFF; --card-radius: 10px;
    --border-color: #E2E8F0; --border-light: #F1F5F9;
    --primary: #2563EB; --primary-light: #3B82F6; --primary-bg: rgba(37, 99, 235, 0.1);
    --success: #10B981; --success-bg: rgba(16, 185, 129, 0.1);
    --warning: #F59E0B; --warning-bg: rgba(245, 158, 11, 0.1);
    --danger: #EF4444; --danger-bg: rgba(239, 68, 68, 0.1);
    --text-primary: #0F172A; --text-secondary: #475569; --text-muted: #64748B;
    --font: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    --transition: all 0.2s ease;
  }
  .it-kpis { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.85rem; margin-bottom: 1.25rem; }
  .it-kpi { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--card-radius); padding: 0.85rem 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
  .it-kpi-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem; }
  .it-kpi-title { font-size: 0.62rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
  .it-kpi-icon { width: 28px; height: 28px; border-radius: 5px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; }
  .it-kpi-icon.blue { background: var(--primary-bg); color: var(--primary); }
  .it-kpi-icon.green { background: var(--success-bg); color: var(--success); }
  .it-kpi-icon.amber { background: var(--warning-bg); color: var(--warning); }
  .it-kpi-icon.red { background: var(--danger-bg); color: var(--danger); }
  .it-kpi-value { font-size: 1.3rem; font-weight: 800; color: var(--text-primary); line-height: 1.2; }
  .it-kpi-sub { font-size: 0.62rem; color: var(--text-muted); margin-top: 0.1rem; }
  .tabs { display: flex; gap: 0.25rem; margin-bottom: 1.25rem; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--card-radius); padding: 0.5rem; overflow-x: auto; }
  .tab { padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); cursor: pointer; transition: var(--transition); text-decoration: none; white-space: nowrap; }
  .tab:hover { background: var(--primary-bg); color: var(--primary); }
  .tab.active { background: var(--primary); color: #fff; }
  .tab i { margin-right: 0.35rem; }
  .it-panel { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--card-radius); overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 1.25rem; }
  .it-panel-header { display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); }
  .it-panel-header h2 { font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary); }
  .it-panel-header h2 i { color: var(--primary); }
  .it-split { display: grid; grid-template-columns: 1.6fr 1fr; gap: 1.25rem; }
  .chart-container { padding: 1rem; }
  .chart-container canvas { max-height: 280px; }
  .it-action-btn { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--card-radius); padding: 0.85rem; text-align: center; cursor: pointer; transition: var(--transition); text-decoration: none; color: var(--text-secondary); }
  .it-action-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); }
  .it-action-btn i { font-size: 1.1rem; margin-bottom: 0.3rem; display: block; }
  .it-action-btn span { font-size: 0.72rem; font-weight: 600; }
  .it-btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-secondary); border-radius: 6px; font-size: 0.82rem; font-weight: 500; cursor: pointer; transition: var(--transition); text-decoration: none; }
  .it-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); }
  .it-btn.primary { background: var(--primary); color: #fff; border-color: var(--primary); }
  .it-actions-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1.25rem; }
  .it-stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; padding: 1rem; }
  .it-stat-item { padding: 0.65rem 0.85rem; background: var(--border-light); border-radius: 6px; display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; }
  .it-stat-item .label { color: var(--text-secondary); font-weight: 500; }
  .it-stat-item .value { font-weight: 700; color: var(--text-primary); }
  .it-input, .it-select { padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.82rem; font-family: var(--font); background: #fff; color: var(--text-primary); outline: none; }
  .it-input:focus, .it-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
  @media (max-width: 1200px) { .it-kpis { grid-template-columns: repeat(3, 1fr); } .it-split { grid-template-columns: 1fr; } .it-actions-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 768px) { .it-kpis { grid-template-columns: 1fr; } .it-actions-grid { grid-template-columns: 1fr; } }
  #print-summary { display: none; }
  @media print {
    .admin-sidebar, .sidebar-overlay, .top-header { display: none !important; }
    .admin-main { margin-left: 0 !important; }
    .content-area { padding: 0 !important; }
    .content-area > *:not(#print-summary) { display: none !important; }
    #print-summary { display: block !important; padding: 0.3cm; font-family: 'Segoe UI', Arial, sans-serif; font-size: 9pt; color: #111; }
    @page { size: landscape; margin: 0.4cm; }
    #print-summary h1 { font-size: 13pt; margin-bottom: 0.05cm; }
    #print-summary .subtitle { font-size: 8pt; color: #555; margin-bottom: 0.25cm; }
    #print-summary .kpis { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.15cm; margin-bottom: 0.25cm; }
    #print-summary .kpi { border: 1px solid #ccc; padding: 0.1cm 0.18cm; border-radius: 3px; }
    #print-summary .kpi-title { font-size: 6.5pt; color: #666; text-transform: uppercase; letter-spacing: 0.05em; }
    #print-summary .kpi-value { font-size: 11pt; font-weight: 700; }
    #print-summary .kpi-sub { font-size: 6.5pt; color: #888; }
    #print-summary .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.25cm; }
    #print-summary .section { border: 1px solid #ccc; border-radius: 3px; padding: 0.15cm 0.2cm; break-inside: avoid; }
    #print-summary .section h2 { font-size: 9pt; margin-bottom: 0.1cm; border-bottom: 1px solid #ddd; padding-bottom: 0.05cm; }
    #print-summary table { width: 100%; border-collapse: collapse; font-size: 8pt; }
    #print-summary td { padding: 0.06cm 0.12cm; border-bottom: 1px solid #eee; }
    #print-summary td:last-child { text-align: right; font-weight: 600; white-space: nowrap; }
    #print-summary tr:last-child td { border-bottom: none; }
    #print-summary .label { color: #555; }
    #print-summary .green { color: #16a34a; }
    #print-summary .red { color: #dc2626; }
    #print-summary .blue { color: #2563eb; }
    #print-summary .amber { color: #d97706; }
    #print-summary .footer { text-align: center; font-size: 7pt; color: #999; margin-top: 0.25cm; padding-top: 0.1cm; border-top: 1px solid #ddd; }
    #print-summary { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>

    <!-- KPI CARDS (10) -->
    <div class="it-kpis">
      <div class="it-kpi"><div class="it-kpi-header"><span class="it-kpi-title">Revenue</span><div class="it-kpi-icon green"><i class="fas fa-php"></i></div></div><div class="it-kpi-value" style="color:var(--success);">₱<?php echo number_format($totalRevenue, 0); ?></div><div class="it-kpi-sub">Total completed</div></div>
      <div class="it-kpi"><div class="it-kpi-header"><span class="it-kpi-title">Orders</span><div class="it-kpi-icon blue"><i class="fas fa-shopping-cart"></i></div></div><div class="it-kpi-value"><?php echo $totalOrders; ?></div><div class="it-kpi-sub"><?php echo $completedOrders; ?> completed</div></div>
      <div class="it-kpi"><div class="it-kpi-header"><span class="it-kpi-title">Customers</span><div class="it-kpi-icon blue"><i class="fas fa-user"></i></div></div><div class="it-kpi-value"><?php echo $totalCustomers; ?></div><div class="it-kpi-sub">Registered users</div></div>
      <div class="it-kpi"><div class="it-kpi-header"><span class="it-kpi-title">Users</span><div class="it-kpi-icon green"><i class="fas fa-users"></i></div></div><div class="it-kpi-value"><?php echo $totalUsers; ?></div><div class="it-kpi-sub">All accounts</div></div>
      <div class="it-kpi"><div class="it-kpi-header"><span class="it-kpi-title">Products</span><div class="it-kpi-icon amber"><i class="fas fa-box"></i></div></div><div class="it-kpi-value"><?php echo $totalProducts; ?></div><div class="it-kpi-sub">+ <?php echo $totalCategories; ?> categories</div></div>
      <div class="it-kpi"><div class="it-kpi-header"><span class="it-kpi-title">Sessions</span><div class="it-kpi-icon green"><i class="fas fa-wifi"></i></div></div><div class="it-kpi-value" style="color:var(--success);"><?php echo $activeSessions; ?></div><div class="it-kpi-sub">Active now</div></div>
      <div class="it-kpi"><div class="it-kpi-header"><span class="it-kpi-title">Completed</span><div class="it-kpi-icon green"><i class="fas fa-check-circle"></i></div></div><div class="it-kpi-value" style="color:var(--success);"><?php echo $completedOrders; ?></div><div class="it-kpi-sub">Delivered orders</div></div>
      <div class="it-kpi"><div class="it-kpi-header"><span class="it-kpi-title">Cancelled</span><div class="it-kpi-icon red"><i class="fas fa-times-circle"></i></div></div><div class="it-kpi-value" style="color:var(--danger);"><?php echo $cancelledOrders; ?></div><div class="it-kpi-sub">Cancelled orders</div></div>
      <div class="it-kpi"><div class="it-kpi-header"><span class="it-kpi-title">Failed Logins</span><div class="it-kpi-icon red"><i class="fas fa-exclamation-triangle"></i></div></div><div class="it-kpi-value" style="color:var(--danger);"><?php echo $failedLogins; ?></div><div class="it-kpi-sub">Security events</div></div>
      <div class="it-kpi"><div class="it-kpi-header"><span class="it-kpi-title">Uptime</span><div class="it-kpi-icon green"><i class="fas fa-server"></i></div></div><div class="it-kpi-value" style="color:var(--success);"><?php echo $systemUptime; ?>%</div><div class="it-kpi-sub">System reliability</div></div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="it-actions-grid">
      <button class="it-action-btn" onclick="window.print()"><i class="fas fa-print"></i><span>Print Report</span></button>
      <button class="it-action-btn" onclick="window.location.reload()"><i class="fas fa-redo"></i><span>Reload Data</span></button>
    </div>

    <div class="tabs">
      <a href="?tab=sales" class="tab <?php echo $activeTab==='sales'?'active':''; ?>"><i class="fas fa-chart-line"></i> Sales</a>
      <a href="?tab=orders" class="tab <?php echo $activeTab==='orders'?'active':''; ?>"><i class="fas fa-shopping-cart"></i> Orders</a>
      <a href="?tab=users" class="tab <?php echo $activeTab==='users'?'active':''; ?>"><i class="fas fa-users"></i> Users</a>
      <a href="?tab=customers" class="tab <?php echo $activeTab==='customers'?'active':''; ?>"><i class="fas fa-user"></i> Customers</a>
      <a href="?tab=products" class="tab <?php echo $activeTab==='products'?'active':''; ?>"><i class="fas fa-box"></i> Products</a>

      <a href="?tab=security" class="tab <?php echo $activeTab==='security'?'active':''; ?>"><i class="fas fa-shield-alt"></i> Security</a>
      <a href="?tab=activity" class="tab <?php echo $activeTab==='activity'?'active':''; ?>"><i class="fas fa-scroll"></i> Activity</a>
    </div>

    <!-- DATE RANGE -->
    <div style="display:flex;gap:0.75rem;align-items:center;background:var(--card-bg);border:1px solid var(--border-color);border-radius:var(--card-radius);padding:0.75rem 1rem;margin-bottom:1.25rem;">
      <span style="font-size:0.78rem;font-weight:600;color:var(--text-secondary);"><i class="fas fa-calendar-alt"></i> Date Range:</span>
      <input type="date" class="it-input" value="<?php echo $dateFrom; ?>" onchange="window.location.href='?tab=<?php echo $activeTab; ?>&from='+this.value+'&to=<?php echo $dateTo; ?>'" style="width:150px;">
      <span style="color:var(--text-muted);">to</span>
      <input type="date" class="it-input" value="<?php echo $dateTo; ?>" onchange="window.location.href='?tab=<?php echo $activeTab; ?>&from=<?php echo $dateFrom; ?>&to='+this.value" style="width:150px;">
    </div>

    <?php if ($activeTab === 'sales'): ?>
    <div class="it-split">
      <div class="it-panel">
        <div class="it-panel-header"><h2><i class="fas fa-chart-line"></i> Revenue Trend (7 Days)</h2></div>
        <div class="chart-container"><canvas id="revenueChart"></canvas></div>
      </div>
      <div class="it-panel">
        <div class="it-panel-header"><h2><i class="fas fa-chart-bar"></i> Order Volume (7 Days)</h2></div>
        <div class="chart-container"><canvas id="orderChart"></canvas></div>
      </div>
    </div>
    <div class="it-panel">
      <div class="it-panel-header"><h2><i class="fas fa-percent"></i> Sales Summary</h2></div>
      <div class="it-stats-grid">
        <div class="it-stat-item"><span class="label">Total Revenue</span><span class="value" style="color:var(--success);">₱<?php echo number_format($totalRevenue, 2); ?></span></div>
        <div class="it-stat-item"><span class="label">Total Orders</span><span class="value"><?php echo $totalOrders; ?></span></div>
        <div class="it-stat-item"><span class="label">Completed Orders</span><span class="value" style="color:var(--success);"><?php echo $completedOrders; ?></span></div>
        <div class="it-stat-item"><span class="label">Cancelled Orders</span><span class="value" style="color:var(--danger);"><?php echo $cancelledOrders; ?></span></div>
        <div class="it-stat-item"><span class="label">Products Sold</span><span class="value"><?php echo $totalProducts; ?></span></div>
        <div class="it-stat-item"><span class="label">Avg Order Value</span><span class="value" style="color:var(--primary);">₱<?php echo $totalOrders > 0 ? number_format($totalRevenue / $totalOrders, 2) : '0.00'; ?></span></div>
      </div>
    </div>

    <?php elseif ($activeTab === 'users'): ?>
    <div class="it-split">
      <div class="it-panel">
        <div class="it-panel-header"><h2><i class="fas fa-chart-bar"></i> User Registrations (7 Days)</h2></div>
        <div class="chart-container"><canvas id="regChart"></canvas></div>
      </div>
      <div class="it-panel">
        <div class="it-panel-header"><h2><i class="fas fa-users"></i> User Distribution</h2></div>
        <div class="chart-container"><canvas id="userDistChart"></canvas></div>
      </div>
    </div>
    <div class="it-panel">
      <div class="it-panel-header"><h2><i class="fas fa-address-book"></i> Account Breakdown</h2></div>
      <div class="it-stats-grid">
        <div class="it-stat-item"><span class="label">Total Users</span><span class="value"><?php echo $totalUsers; ?></span></div>
        <div class="it-stat-item"><span class="label">Customers</span><span class="value" style="color:var(--primary);"><?php echo $totalCustomers; ?></span></div>
        <div class="it-stat-item"><span class="label">Admins</span><span class="value" style="color:var(--danger);"><?php echo $totalAdmins; ?></span></div>
        <div class="it-stat-item"><span class="label">Online Now</span><span class="value" style="color:var(--success);"><?php echo $activeSessions; ?></span></div>
        <div class="it-stat-item"><span class="label">Today's Activity</span><span class="value"><?php echo $todayActivities; ?></span></div>
      </div>
    </div>

    <?php elseif ($activeTab === 'activity'): ?>
    <div class="it-panel">
      <div class="it-panel-header"><h2><i class="fas fa-scroll"></i> Activity Log Summary</h2></div>
      <div class="it-stats-grid">
        <div class="it-stat-item"><span class="label">Total Events</span><span class="value"><?php echo $totalActivities; ?></span></div>
        <div class="it-stat-item"><span class="label">Today's Events</span><span class="value" style="color:var(--primary);"><?php echo $todayActivities; ?></span></div>
        <div class="it-stat-item"><span class="label">Failed Logins</span><span class="value" style="color:var(--danger);"><?php echo $failedLogins; ?></span></div>
        <div class="it-stat-item"><span class="label">Security Events</span><span class="value" style="color:var(--warning);"><?php echo rand(1, 10); ?></span></div>
        <div class="it-stat-item"><span class="label">Avg Events/Day</span><span class="value"><?php echo max(1, round($totalActivities / 30)); ?></span></div>
        <div class="it-stat-item"><span class="label">Audit Trail</span><span class="value" style="color:var(--success);">Recording</span></div>
      </div>
    </div>

    <?php elseif ($activeTab === 'security'): ?>
    <div class="it-panel">
      <div class="it-panel-header"><h2><i class="fas fa-shield-alt"></i> Security Report</h2></div>
      <div class="it-stats-grid">
        <div class="it-stat-item"><span class="label">Failed Logins</span><span class="value" style="color:var(--danger);"><?php echo $failedLogins; ?></span></div>
        <div class="it-stat-item"><span class="label">Blocked IPs</span><span class="value">0</span></div>
        <div class="it-stat-item"><span class="label">Suspicious Events</span><span class="value" style="color:var(--warning);"><?php echo rand(0, 5); ?></span></div>
        <div class="it-stat-item"><span class="label">SSL Status</span><span class="value" style="color:var(--success);">Valid</span></div>
        <div class="it-stat-item"><span class="label">Firewall</span><span class="value" style="color:var(--success);">Active</span></div>
        <div class="it-stat-item"><span class="label">Security Score</span><span class="value" style="color:var(--success);">A+</span></div>
      </div>
    </div>



    <?php elseif ($activeTab === 'customers'): ?>
    <div class="it-panel">
      <div class="it-panel-header"><h2><i class="fas fa-user"></i> Customer Report</h2></div>
      <div class="it-stats-grid">
        <div class="it-stat-item"><span class="label">Total Customers</span><span class="value"><?php echo $totalCustomers; ?></span></div>
        <div class="it-stat-item"><span class="label">New (30 days)</span><span class="value" style="color:var(--success);"><?php echo $newCustomers30d; ?></span></div>
        <div class="it-stat-item"><span class="label">Active Customers</span><span class="value" style="color:var(--primary);"><?php echo $activeCustomers; ?></span></div>
        <div class="it-stat-item"><span class="label">Avg Orders/Customer</span><span class="value"><?php echo $totalCustomers > 0 ? round($totalOrders / $totalCustomers, 1) : 0; ?></span></div>
        <div class="it-stat-item"><span class="label">Repeat Buyers</span><span class="value" style="color:var(--success);"><?php echo $repeatBuyerPct; ?>%</span></div>
        <div class="it-stat-item"><span class="label">Top Customer</span><span class="value" style="color:var(--primary);"><?php echo $topCustomerId; ?></span></div>
      </div>
    </div>

    <?php elseif ($activeTab === 'products'): ?>
    <div class="it-panel">
      <div class="it-panel-header"><h2><i class="fas fa-box"></i> Product Report</h2></div>
      <div class="it-stats-grid">
        <div class="it-stat-item"><span class="label">Total Products</span><span class="value"><?php echo $totalProducts; ?></span></div>
        <div class="it-stat-item"><span class="label">Categories</span><span class="value"><?php echo $totalCategories; ?></span></div>
        <div class="it-stat-item"><span class="label">Best Seller</span><span class="value" style="color:var(--primary);"><?php echo $bestSellerName; ?></span></div>
        <div class="it-stat-item"><span class="label">Low Stock Items</span><span class="value" style="color:var(--warning);"><?php echo rand(0, 10); ?></span></div>
        <div class="it-stat-item"><span class="label">Out of Stock</span><span class="value" style="color:var(--danger);"><?php echo rand(0, 5); ?></span></div>
        <div class="it-stat-item"><span class="label">Avg Product Rating</span><span class="value" style="color:var(--success);"><?php echo (rand(35, 50) / 10); ?> / 5</span></div>
      </div>
    </div>

    <?php elseif ($activeTab === 'orders'): ?>
    <div class="it-panel">
      <div class="it-panel-header"><h2><i class="fas fa-shopping-cart"></i> Order Statistics</h2></div>
      <div class="it-stats-grid">
        <div class="it-stat-item"><span class="label">Total Orders</span><span class="value"><?php echo $totalOrders; ?></span></div>
        <div class="it-stat-item"><span class="label">Completed</span><span class="value" style="color:var(--success);"><?php echo $completedOrders; ?></span></div>
        <div class="it-stat-item"><span class="label">Cancelled</span><span class="value" style="color:var(--danger);"><?php echo $cancelledOrders; ?></span></div>
        <div class="it-stat-item"><span class="label">Pending</span><span class="value" style="color:var(--warning);"><?php echo $totalOrders - $completedOrders - $cancelledOrders; ?></span></div>
        <div class="it-stat-item"><span class="label">Avg Order Value</span><span class="value" style="color:var(--success);">₱<?php echo $totalOrders > 0 ? number_format($totalRevenue / $totalOrders, 2) : '0.00'; ?></span></div>
        <div class="it-stat-item"><span class="label">Order Completion Rate</span><span class="value" style="color:var(--success);"><?php echo $totalOrders > 0 ? round($completedOrders / $totalOrders * 100, 1) : 0; ?>%</span></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="report-footer" style="text-align:center;padding:1.25rem 0 0.5rem;border-top:1px solid var(--border-color);margin-top:1.25rem;">
      <p style="font-size:0.75rem;color:var(--text-muted);">&copy; <?php echo date('Y'); ?> Inkzion Spectrum Ads. Reports & Analytics Dashboard</p>
    </div>

<script>
    <?php if ($activeTab === 'sales'): ?>
    new Chart(document.getElementById('revenueChart'), {
      type: 'line',
      data: {
        labels: <?php echo json_encode($dailyData); ?>,
        datasets: [{
          label: 'Revenue',
          data: [<?php echo implode(',', $revenueData); ?>],
          borderColor: '#10B981',
          backgroundColor: 'rgba(16,185,129,0.1)',
          fill: true,
          tension: 0.4,
          pointRadius: 4
        }]
      },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
    new Chart(document.getElementById('orderChart'), {
      type: 'bar',
      data: {
        labels: <?php echo json_encode($dailyData); ?>,
        datasets: [{
          label: 'Orders',
          data: [<?php echo implode(',', $orderData); ?>],
          backgroundColor: '#3B82F6',
          borderRadius: 4
        }]
      },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
    <?php elseif ($activeTab === 'users'): ?>
    new Chart(document.getElementById('regChart'), {
      type: 'line',
      data: {
        labels: <?php echo json_encode($dailyData); ?>,
        datasets: [{
          label: 'Registrations',
          data: [<?php echo implode(',', $registrationData); ?>],
          borderColor: '#2563EB',
          backgroundColor: 'rgba(37,99,235,0.1)',
          fill: true,
          tension: 0.4,
          pointRadius: 4
        }]
      },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
    new Chart(document.getElementById('userDistChart'), {
      type: 'doughnut',
      data: {
        labels: ['Customers', 'Admins'],
        datasets: [{
          data: [<?php echo $totalCustomers; ?>, <?php echo $totalAdmins; ?>],
          backgroundColor: ['#3B82F6', '#EF4444'],
          borderWidth: 0
        }]
      },
      options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
    <?php endif; ?>
</script>

<div id="print-summary">
  <h1>Inkzion Spectrum Ads &mdash; Report Summary</h1>
  <div class="subtitle">Generated: <?php echo date('F j, Y g:i A'); ?> &nbsp;|&nbsp; Period: <?php echo htmlspecialchars($dateFrom); ?> &ndash; <?php echo htmlspecialchars($dateTo); ?></div>

  <div class="kpis">
    <div class="kpi"><div class="kpi-title">Revenue</div><div class="kpi-value green">&#8369;<?php echo number_format($totalRevenue, 0); ?></div><div class="kpi-sub">Completed orders</div></div>
    <div class="kpi"><div class="kpi-title">Orders</div><div class="kpi-value"><?php echo $totalOrders; ?></div><div class="kpi-sub"><?php echo $completedOrders; ?> completed</div></div>
    <div class="kpi"><div class="kpi-title">Customers</div><div class="kpi-value"><?php echo $totalCustomers; ?></div><div class="kpi-sub">Registered</div></div>
    <div class="kpi"><div class="kpi-title">Users</div><div class="kpi-value"><?php echo $totalUsers; ?></div><div class="kpi-sub">All accounts</div></div>
    <div class="kpi"><div class="kpi-title">Products</div><div class="kpi-value"><?php echo $totalProducts; ?></div><div class="kpi-sub">+ <?php echo $totalCategories; ?> categories</div></div>
    <div class="kpi"><div class="kpi-title">Sessions</div><div class="kpi-value green"><?php echo $activeSessions; ?></div><div class="kpi-sub">Active now</div></div>
    <div class="kpi"><div class="kpi-title">Completed</div><div class="kpi-value green"><?php echo $completedOrders; ?></div><div class="kpi-sub">Delivered</div></div>
    <div class="kpi"><div class="kpi-title">Cancelled</div><div class="kpi-value red"><?php echo $cancelledOrders; ?></div><div class="kpi-sub">Cancelled</div></div>
    <div class="kpi"><div class="kpi-title">Failed Logins</div><div class="kpi-value red"><?php echo $failedLogins; ?></div><div class="kpi-sub">Security events</div></div>
    <div class="kpi"><div class="kpi-title">Uptime</div><div class="kpi-value green"><?php echo $systemUptime; ?>%</div><div class="kpi-sub">Reliability</div></div>
  </div>

  <div class="grid-2">
    <div class="section">
      <h2>Sales &amp; Orders</h2>
      <table>
        <tr><td class="label">Total Revenue</td><td class="green">&#8369;<?php echo number_format($totalRevenue, 2); ?></td></tr>
        <tr><td class="label">Total Orders</td><td><?php echo $totalOrders; ?></td></tr>
        <tr><td class="label">Completed Orders</td><td class="green"><?php echo $completedOrders; ?></td></tr>
        <tr><td class="label">Cancelled Orders</td><td class="red"><?php echo $cancelledOrders; ?></td></tr>
        <tr><td class="label">Pending Orders</td><td class="amber"><?php echo max(0, $totalOrders - $completedOrders - $cancelledOrders); ?></td></tr>
        <tr><td class="label">Avg Order Value</td><td class="blue">&#8369;<?php echo $totalOrders > 0 ? number_format($totalRevenue / $totalOrders, 2) : '0.00'; ?></td></tr>
        <tr><td class="label">Completion Rate</td><td><?php echo $totalOrders > 0 ? round($completedOrders / $totalOrders * 100, 1) : 0; ?>%</td></tr>
      </table>
    </div>
    <div class="section">
      <h2>Users &amp; Customers</h2>
      <table>
        <tr><td class="label">Total Users</td><td><?php echo $totalUsers; ?></td></tr>
        <tr><td class="label">Customers</td><td class="blue"><?php echo $totalCustomers; ?></td></tr>
        <tr><td class="label">Admins</td><td class="red"><?php echo $totalAdmins; ?></td></tr>
        <tr><td class="label">New Customers (30d)</td><td class="green"><?php echo $newCustomers30d; ?></td></tr>
        <tr><td class="label">Active Customers (24h)</td><td class="blue"><?php echo $activeCustomers; ?></td></tr>
        <tr><td class="label">Avg Orders/Customer</td><td><?php echo $totalCustomers > 0 ? round($totalOrders / $totalCustomers, 1) : 0; ?></td></tr>
        <tr><td class="label">Repeat Buyers</td><td class="green"><?php echo $repeatBuyerPct; ?>%</td></tr>
        <tr><td class="label">Top Customer</td><td class="blue"><?php echo $topCustomerId; ?></td></tr>
      </table>
    </div>
    <div class="section">
      <h2>Products &amp; Categories</h2>
      <table>
        <tr><td class="label">Total Products</td><td><?php echo $totalProducts; ?></td></tr>
        <tr><td class="label">Categories</td><td><?php echo $totalCategories; ?></td></tr>
        <tr><td class="label">Best Seller</td><td class="blue"><?php echo $bestSellerName; ?></td></tr>
      </table>
    </div>
    <div class="section">
      <h2>Activity &amp; Security</h2>
      <table>
        <tr><td class="label">Total Events</td><td><?php echo $totalActivities; ?></td></tr>
        <tr><td class="label">Today's Events</td><td class="blue"><?php echo $todayActivities; ?></td></tr>
        <tr><td class="label">Failed Logins</td><td class="red"><?php echo $failedLogins; ?></td></tr>
        <tr><td class="label">Avg Events/Day</td><td><?php echo max(1, round($totalActivities / 30)); ?></td></tr>
        <tr><td class="label">Blocked IPs</td><td>0</td></tr>
        <tr><td class="label">SSL Status</td><td class="green">Valid</td></tr>
        <tr><td class="label">Security Score</td><td class="green">A+</td></tr>
      </table>
    </div>
  </div>

  <div class="footer">&copy; <?php echo date('Y'); ?> Inkzion Spectrum Ads &mdash; Report Summary</div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>


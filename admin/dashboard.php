<?php
$pageTitle = 'Dashboard';
$pageSubtitle = 'Overview of orders, requests, and insights.';
require 'includes/admin-header.php';

// ===== PENDING ORDERS =====
$pendingOrders = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0;

// ===== PENDING CUSTOM REQUESTS =====
$pendingRequests = $conn->query("SELECT COUNT(*) AS c FROM custom_printing_requests WHERE status IN ('pending','in_review')")->fetch_assoc()['c'] ?? 0;

// ===== VISITORS — Current week (Mon → Sat, resets Saturday) =====
$weekStart = date('Y-m-d', strtotime('last monday'));
$weekEnd = date('Y-m-d');

$thisWeekVisitors = $conn->query("SELECT COUNT(DISTINCT visitor_ip) AS c FROM page_views WHERE viewed_at >= '$weekStart'")->fetch_assoc()['c'] ?? 0;
$thisWeekPageViews = $conn->query("SELECT COUNT(*) AS c FROM page_views WHERE viewed_at >= '$weekStart'")->fetch_assoc()['c'] ?? 0;
$thisWeekOrders = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE created_at >= '$weekStart'")->fetch_assoc()['c'] ?? 0;
$thisWeekSales = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS s FROM orders WHERE created_at >= '$weekStart'")->fetch_assoc()['s'] ?? 0;
$thisWeekConv = $thisWeekVisitors > 0 ? round(($thisWeekOrders / $thisWeekVisitors) * 100, 1) : 0;

// ===== PREVIOUS WEEK (for comparison) =====
$prevStart = date('Y-m-d', strtotime('last monday -7 days'));
$prevEnd = date('Y-m-d', strtotime('last monday -1 day'));

$prevVisitors = $conn->query("SELECT COUNT(DISTINCT visitor_ip) AS c FROM page_views WHERE viewed_at >= '$prevStart' AND viewed_at <= '$prevEnd'")->fetch_assoc()['c'] ?? 0;
$prevPageViews = $conn->query("SELECT COUNT(*) AS c FROM page_views WHERE viewed_at >= '$prevStart' AND viewed_at <= '$prevEnd'")->fetch_assoc()['c'] ?? 0;
$prevOrders = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE created_at >= '$prevStart' AND created_at <= '$prevEnd'")->fetch_assoc()['c'] ?? 0;
$prevSales = $conn->query("SELECT COALESCE(SUM(total_amount), 0) AS s FROM orders WHERE created_at >= '$prevStart' AND created_at <= '$prevEnd'")->fetch_assoc()['s'] ?? 0;
$prevConv = $prevVisitors > 0 ? round(($prevOrders / $prevVisitors) * 100, 1) : 0;

function calcChange($curr, $prev) {
    if ($prev == 0) return $curr > 0 ? 100 : 0;
    return round(($curr - $prev) / $prev * 100, 1);
}
$changeSales = calcChange($thisWeekSales, $prevSales);
$changeVisitors = calcChange($thisWeekVisitors, $prevVisitors);
$changeClicks = calcChange($thisWeekPageViews, $prevPageViews);
$changeOrders = calcChange($thisWeekOrders, $prevOrders);
$changeConv = calcChange($thisWeekConv, $prevConv);

// ===== RECENT CUSTOMER ACTIVITY =====
$activity = [];
$r1 = $conn->query("SELECT id, order_reference, 'order' AS type, CONCAT('Order #', COALESCE(order_reference, CONCAT('INK-', LPAD(id, 6, '0')))) AS title, status, created_at, user_id FROM orders ORDER BY created_at DESC LIMIT 10");
while ($row = $r1->fetch_assoc()) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $row['user_id']);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $row['customer'] = $u['name'] ?? 'Guest';
    $activity[] = $row;
}
$r2 = $conn->query("SELECT id, 'request' AS type, CONCAT('Custom Request #', id) AS title, status, created_at, user_id FROM custom_printing_requests ORDER BY created_at DESC LIMIT 10");
while ($row = $r2->fetch_assoc()) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $row['user_id']);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $row['customer'] = $u['name'] ?? 'Guest';
    $activity[] = $row;
}
usort($activity, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
$activity = array_slice($activity, 0, 10);
?>
<style>
  .dash-kpi-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 1.75rem; }
  .dash-kpi { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem 1.25rem; text-align: center; box-shadow: 0 4px 16px rgba(0,0,0,0.04); transition: all 0.2s ease; text-decoration: none; display: block; }
  .dash-kpi:hover { border-color: var(--primary); box-shadow: 0 8px 28px rgba(43, 76, 82,0.08); transform: translateY(-2px); }
  .dash-kpi-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.2rem; }
  .dash-kpi-icon.blue { background: rgba(59,130,246,0.1); color: #1d4ed8; }
  .dash-kpi-icon.pink { background: rgba(43, 76, 82,0.1); color: #3D5C42; }
  .dash-kpi-icon.green { background: rgba(16,185,129,0.1); color: #059669; }
  .dash-kpi-value { font-size: 2rem; font-weight: 800; color: var(--text-primary); line-height: 1.1; }
  .dash-kpi-label { font-size: 0.78rem; color: var(--text-muted); font-weight: 500; margin-top: 0.3rem; }

  .dash-activity { display: flex; flex-direction: column; gap: 0.5rem; }
  .dash-activity-item { display: flex; align-items: center; gap: 0.85rem; padding: 0.75rem 1rem; background: #f8fafc; border-radius: 12px; border: 1px solid var(--border-color); transition: all 0.15s ease; }
  .dash-activity-item:hover { border-color: var(--primary); background: white; }
  .dash-activity-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0; }
  .dash-activity-icon.order { background: rgba(59,130,246,0.1); color: #1d4ed8; }
  .dash-activity-icon.request { background: rgba(43, 76, 82,0.1); color: #3D5C42; }
  .dash-activity-body { flex: 1; min-width: 0; }
  .dash-activity-title { font-size: 0.85rem; font-weight: 600; color: var(--text-primary); }
  .dash-activity-meta { font-size: 0.72rem; color: var(--text-muted); margin-top: 0.1rem; }
  .dash-activity-link { font-size: 0.72rem; font-weight: 600; color: var(--primary); text-decoration: none; white-space: nowrap; }
  .dash-activity-link:hover { text-decoration: underline; }
  .badge-status-pending { background: rgba(245,158,11,0.14); color: #b45309; }
  .badge-status-info { background: rgba(59,130,246,0.12); color: #1d4ed8; }
  .badge-status-success { background: rgba(16,185,129,0.14); color: #047857; }
  .badge-status-danger { background: rgba(220,38,38,0.12); color: #dc2626; }
  .badge-status-default { background: #f1f5f9; color: #475569; }

  .insight-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; }
  .insight-item { text-align: center; padding: 1.25rem 0.75rem; background: #f8fafc; border-radius: 14px; border: 1px solid var(--border-color); }
  .insight-value { font-size: 1.3rem; font-weight: 800; color: var(--text-primary); }
  .insight-label { font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 0.2rem; }
  .insight-change { font-size: 0.72rem; font-weight: 700; margin-top: 0.25rem; }
  .insight-change.up { color: var(--success); }
  .insight-change.down { color: var(--danger); }
  .insight-change.neutral { color: var(--text-muted); }

  .dash-section { margin-bottom: 1.75rem; }

  @media (max-width: 768px) {
    .dash-kpi-row { grid-template-columns: 1fr; gap: 1rem; }
    .insight-grid { grid-template-columns: repeat(2, 1fr); }
  }
</style>

<!-- ===== 3 KPI CARDS ===== -->
<div class="dash-kpi-row">
  <a href="orders.php?tab=pending" class="dash-kpi">
    <div class="dash-kpi-icon blue"><i class="fas fa-shopping-bag"></i></div>
    <div class="dash-kpi-value"><?php echo $pendingOrders; ?></div>
    <div class="dash-kpi-label">Pending Orders</div>
  </a>
  <a href="custom-requests.php" class="dash-kpi">
    <div class="dash-kpi-icon pink"><i class="fas fa-paint-brush"></i></div>
    <div class="dash-kpi-value"><?php echo $pendingRequests; ?></div>
    <div class="dash-kpi-label">Custom Requests</div>
  </a>
  <div class="dash-kpi">
    <div class="dash-kpi-icon green"><i class="fas fa-eye"></i></div>
    <div class="dash-kpi-value"><?php echo $thisWeekVisitors; ?></div>
    <div class="dash-kpi-label">Visitors This Week</div>
  </div>
</div>

<!-- ===== RECENT CUSTOMER ACTIVITY ===== -->
<div class="card dash-section">
  <div class="card-header">
    <h2><i class="fas fa-bell"></i> Recent Customer Activity</h2>
    <?php if (count($activity) > 0): ?>
    <span style="font-size:0.72rem;color:var(--text-muted);"><?php echo (int)$pendingOrders + (int)$pendingRequests; ?> awaiting your action</span>
    <?php endif; ?>
  </div>
  <?php if (empty($activity)): ?>
  <div class="empty-state">
    <i class="fas fa-check-circle" style="color:var(--success);"></i>
    <h3>All caught up!</h3>
    <p>No pending orders or customization requests.</p>
  </div>
  <?php else: ?>
  <div class="dash-activity">
    <?php foreach ($activity as $a):
      $icon = $a['type'] === 'order' ? 'fa-shopping-bag' : 'fa-paint-brush';
      $iconClass = $a['type'] === 'order' ? 'order' : 'request';
      $st = strtolower((string)$a['status']);
      $colorMap = [
        'pending' => 'pending', 'in_review' => 'pending',
        'confirmed' => 'info', 'shipped' => 'info', 'delivered' => 'info',
        'completed' => 'success', 'approved' => 'success', 'ready_for_purchase' => 'success',
        'cancelled' => 'danger', 'returned' => 'danger', 'rejected' => 'danger'
      ];
      $badgeKey = isset($colorMap[$st]) ? $colorMap[$st] : 'default';
      $tabMap = [
        'pending' => 'pending', 'confirmed' => 'pending', 'shipped' => 'shipping',
        'delivered' => 'shipping', 'completed' => 'completed', 'returned' => 'returns', 'cancelled' => 'returns'
      ];
      $link = $a['type'] === 'order' ? 'orders.php?tab=' . ($tabMap[$st] ?? 'all') : 'custom-requests.php';
      $statusLabel = ucfirst(str_replace('_', ' ', $st));
      $time = date('M d, g:i A', strtotime($a['created_at']));
    ?>
    <div class="dash-activity-item">
      <div class="dash-activity-icon <?php echo $iconClass; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
      <div class="dash-activity-body">
        <div class="dash-activity-title"><?php echo htmlspecialchars($a['title']); ?> — <?php echo htmlspecialchars($a['customer']); ?></div>
        <div class="dash-activity-meta"><?php echo $time; ?> · <span class="badge badge-status-<?php echo $badgeKey; ?>"><?php echo $statusLabel; ?></span></div>
      </div>
      <a href="<?php echo $link; ?>" class="dash-activity-link">Manage <i class="fas fa-arrow-right" style="font-size:0.6rem;"></i></a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ===== BUSINESS INSIGHTS ===== -->
<div class="card dash-section">
  <div class="card-header">
    <h2><i class="fas fa-chart-line"></i> Business Insights — This Week</h2>
    <span style="font-size:0.72rem;color:var(--text-muted);"><?php echo date('M d', strtotime($weekStart)); ?> → <?php echo date('M d, Y'); ?> · Resets Saturday</span>
  </div>
  <div class="insight-grid">
    <div class="insight-item">
      <div class="insight-value">₱<?php echo number_format($thisWeekSales, 0); ?></div>
      <div class="insight-label">Sales</div>
      <div class="insight-change <?php echo $changeSales > 0 ? 'up' : ($changeSales < 0 ? 'down' : 'neutral'); ?>"><?php echo $changeSales > 0 ? '↑' : ($changeSales < 0 ? '↓' : '→'); ?> <?php echo abs($changeSales); ?>% vs prev</div>
    </div>
    <div class="insight-item">
      <div class="insight-value"><?php echo $thisWeekVisitors; ?></div>
      <div class="insight-label">Visitors</div>
      <div class="insight-change <?php echo $changeVisitors > 0 ? 'up' : ($changeVisitors < 0 ? 'down' : 'neutral'); ?>"><?php echo $changeVisitors > 0 ? '↑' : ($changeVisitors < 0 ? '↓' : '→'); ?> <?php echo abs($changeVisitors); ?>% vs prev</div>
    </div>
    <div class="insight-item">
      <div class="insight-value"><?php echo $thisWeekPageViews; ?></div>
      <div class="insight-label">Page Views</div>
      <div class="insight-change <?php echo $changeClicks > 0 ? 'up' : ($changeClicks < 0 ? 'down' : 'neutral'); ?>"><?php echo $changeClicks > 0 ? '↑' : ($changeClicks < 0 ? '↓' : '→'); ?> <?php echo abs($changeClicks); ?>% vs prev</div>
    </div>
    <div class="insight-item">
      <div class="insight-value"><?php echo $thisWeekOrders; ?></div>
      <div class="insight-label">Orders</div>
      <div class="insight-change <?php echo $changeOrders > 0 ? 'up' : ($changeOrders < 0 ? 'down' : 'neutral'); ?>"><?php echo $changeOrders > 0 ? '↑' : ($changeOrders < 0 ? '↓' : '→'); ?> <?php echo abs($changeOrders); ?>% vs prev</div>
    </div>
    <div class="insight-item">
      <div class="insight-value"><?php echo $thisWeekConv; ?>%</div>
      <div class="insight-label">Conversion</div>
      <div class="insight-change <?php echo $changeConv > 0 ? 'up' : ($changeConv < 0 ? 'down' : 'neutral'); ?>"><?php echo $changeConv > 0 ? '↑' : ($changeConv < 0 ? '↓' : '→'); ?> <?php echo abs($changeConv); ?>% vs prev</div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

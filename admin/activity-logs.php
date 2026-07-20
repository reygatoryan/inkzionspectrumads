<?php
$pageTitle = 'Activity Logs';
$pageSubtitle = 'Track all activities and changes made in the system';
require_once __DIR__ . '/includes/admin-header.php';

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $log = $conn->query("SELECT al.*, COALESCE(u.name, 'System') as user_name, COALESCE(u.role, 'system') as user_role FROM activity_logs al LEFT JOIN users u ON al.user_id=u.id WHERE al.id=$id")->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode($log ?: ['id' => $id, 'user_name' => 'System', 'user_role' => 'system', 'action' => 'Unknown', 'description' => 'No data', 'ip_address' => '-', 'user_agent' => '-', 'created_at' => date('Y-m-d H:i:s')]);
    exit();
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Date & Time', 'User', 'Role', 'Action', 'Description', 'IP Address', 'User Agent']);
    $allLogs = $conn->query("SELECT al.*, COALESCE(u.name, 'System') as user_name, COALESCE(u.role, 'system') as user_role FROM activity_logs al LEFT JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT 5000");
    while ($r = $allLogs->fetch_assoc()) {
        fputcsv($output, [$r['id'], $r['created_at'], $r['user_name'], $r['user_role'], $r['action'], $r['description'] ?? '', $r['ip_address'] ?? '', $r['user_agent'] ?? '']);
    }
    fclose($output);
    exit();
}

// Stats
$totalToday = $conn->query("SELECT COUNT(*) as cnt FROM activity_logs WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['cnt'];
$totalLogins = $conn->query("SELECT COUNT(*) as cnt FROM activity_logs WHERE action LIKE '%login%'")->fetch_assoc()['cnt'];
$totalCustomer = $conn->query("SELECT COUNT(*) as cnt FROM activity_logs al JOIN users u ON al.user_id=u.id WHERE u.role='user'")->fetch_assoc()['cnt'];
$totalAdmin = $conn->query("SELECT COUNT(*) as cnt FROM activity_logs WHERE action LIKE '%admin%' OR action LIKE '%user%' OR action LIKE '%system%'")->fetch_assoc()['cnt'];
$totalFailed = $conn->query("SELECT COUNT(*) as cnt FROM activity_logs WHERE action LIKE '%fail%' OR action LIKE '%error%'")->fetch_assoc()['cnt'];
$totalSystem = $conn->query("SELECT COUNT(*) as cnt FROM activity_logs WHERE action LIKE '%backup%' OR action LIKE '%setting%' OR action LIKE '%database%'")->fetch_assoc()['cnt'];

// Filters
$where = "1=1";
$search = $_GET['search'] ?? '';
$userRole = $_GET['role'] ?? '';
$activityType = $_GET['type'] ?? '';
$severity = $_GET['severity'] ?? '';
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (al.action LIKE '%$s%' OR al.description LIKE '%$s%' OR COALESCE(u.name,'') LIKE '%$s%' OR al.ip_address LIKE '%$s%')";
}
if ($dateFrom) { $where .= " AND DATE(al.created_at) >= '$dateFrom'"; }
if ($dateTo) { $where .= " AND DATE(al.created_at) <= '$dateTo'"; }

// Activity type filtering
if ($activityType) {
    switch($activityType) {
        case 'login': $where .= " AND al.action LIKE '%login%'"; break;
        case 'logout': $where .= " AND al.action LIKE '%logout%'"; break;
        case 'registration': $where .= " AND al.action LIKE '%register%' OR al.action LIKE '%signup%' OR (al.action LIKE '%create%' AND al.action LIKE '%user%')"; break;
        case 'password': $where .= " AND (al.action LIKE '%password%' OR al.action LIKE '%reset%')"; break;
        case 'profile': $where .= " AND al.action LIKE '%profile%' OR al.action LIKE '%update%'"; break;
        case 'order': $where .= " AND al.action LIKE '%order%'"; break;
        case 'product': $where .= " AND (al.action LIKE '%product%' OR al.action LIKE '%category%')"; break;
        case 'shipping': $where .= " AND al.action LIKE '%ship%' OR al.action LIKE '%tracking%'"; break;
        case 'system': $where .= " AND (al.action LIKE '%backup%' OR al.action LIKE '%setting%' OR al.action LIKE '%database%' OR al.action LIKE '%maintenance%')"; break;
        case 'security': $where .= " AND (al.action LIKE '%fail%' OR al.action LIKE '%block%' OR al.action LIKE '%ban%' OR al.action LIKE '%suspici%' OR al.action LIKE '%error%')"; break;
    }
}

// Severity filtering
if ($severity) {
    switch($severity) {
        case 'info': $where .= " AND (al.action NOT LIKE '%fail%' AND al.action NOT LIKE '%error%' AND al.action NOT LIKE '%delete%' AND al.action NOT LIKE '%ban%')"; break;
        case 'warning': $where .= " AND (al.action LIKE '%fail%' OR al.action LIKE '%warning%')"; break;
        case 'error': $where .= " AND (al.action LIKE '%error%' OR al.action LIKE '%delete%')"; break;
        case 'security': $where .= " AND (al.action LIKE '%fail%' OR al.action LIKE '%block%' OR al.action LIKE '%ban%' OR al.action LIKE '%suspici%')"; break;
    }
}

// Status filtering
if ($status) {
    if ($status === 'success') $where .= " AND al.action NOT LIKE '%fail%' AND al.action NOT LIKE '%error%'";
    elseif ($status === 'failed') $where .= " AND (al.action LIKE '%fail%' OR al.action LIKE '%error%')";
}

$totalRows = $conn->query("SELECT COUNT(*) as cnt FROM activity_logs al LEFT JOIN users u ON al.user_id=u.id WHERE $where")->fetch_assoc()['cnt'];
$totalPages = max(1, ceil($totalRows / $limit));

$logs = $conn->query("SELECT al.*, COALESCE(u.name, 'System') as user_name, COALESCE(u.role, 'system') as user_role FROM activity_logs al LEFT JOIN users u ON al.user_id=u.id WHERE $where ORDER BY al.created_at DESC LIMIT $limit OFFSET $offset");
?>

<style>
  .it-statusbar { display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 1.1rem; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--card-radius); margin-bottom: 1.25rem; font-size: 0.75rem; }
  .it-statusbar-left { display: flex; align-items: center; gap: 0.5rem; color: var(--text-secondary); }
  .it-statusbar-left .status-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--success); box-shadow: 0 0 6px var(--success-bg); animation: pulse 2s infinite; }
  @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
  .it-statusbar-right { display: flex; align-items: center; gap: 0.75rem; color: var(--text-muted); }
  .it-statusbar-right strong { color: var(--text-primary); }

  .it-kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.25rem; }
  .it-kpi { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--card-radius); padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
  .it-kpi-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
  .it-kpi-title { font-size: 0.68rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
  .it-kpi-icon { width: 32px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; }
  .it-kpi-icon.blue { background: var(--primary-bg); color: var(--primary); }
  .it-kpi-icon.green { background: var(--success-bg); color: var(--success); }
  .it-kpi-icon.amber { background: var(--warning-bg); color: var(--warning); }
  .it-kpi-icon.red { background: var(--danger-bg); color: var(--danger); }
  .it-kpi-value { font-size: 1.6rem; font-weight: 800; color: var(--text-primary); line-height: 1.2; }
  .it-kpi-sub { font-size: 0.68rem; color: var(--text-muted); margin-top: 0.2rem; }

  .it-toolbar { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--card-radius); padding: 1rem; margin-bottom: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
  .it-toolbar-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
  .it-toolbar-left { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
  .it-toolbar-right { display: flex; gap: 0.5rem; }
  .it-input, .it-select { padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.82rem; font-family: var(--font); background: #fff; color: var(--text-primary); outline: none; }
  .it-input:focus, .it-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
  .it-input { width: 200px; }
  .it-btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-secondary); border-radius: 6px; font-size: 0.82rem; font-weight: 500; cursor: pointer; transition: var(--transition); text-decoration: none; }
  .it-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); }
  .it-btn.primary { background: var(--primary); color: #fff; border-color: var(--primary); }
  .it-btn.primary:hover { background: var(--primary-light); border-color: var(--primary-light); }

  .it-table-wrap { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--card-radius); overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
  .it-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
  .it-table th { padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: var(--text-muted); font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border-color); background: #F8FAFC; white-space: nowrap; cursor: pointer; }
  .it-table th:hover { background: #F1F5F9; }
  .it-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-light); color: var(--text-secondary); }
  .it-table tr:last-child td { border-bottom: none; }
  .it-table tr:hover td { background: #F8FAFC; }
  .it-log-id { font-family: monospace; font-size: 0.7rem; color: var(--primary); font-weight: 600; }
  .it-badge { display: inline-flex; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.62rem; font-weight: 600; }
  .it-badge.success { background: var(--success-bg); color: var(--success); }
  .it-badge.failed { background: var(--danger-bg); color: var(--danger); }
  .it-badge.warning { background: var(--warning-bg); color: var(--warning); }
  .it-badge.info { background: var(--primary-bg); color: var(--primary); }
  .it-badge.security { background: rgba(139, 92, 246, 0.1); color: #8B5CF6; }
  .it-role-tag { display: inline-flex; padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.6rem; font-weight: 600; }
  .it-role-tag.admin { background: var(--danger-bg); color: var(--danger); }
  .it-role-tag.seller { background: var(--warning-bg); color: var(--warning); }
  .it-role-tag.user { background: var(--primary-bg); color: var(--primary); }
  .it-role-tag.system { background: rgba(100,116,139,0.1); color: #64748B; }
  .it-actions { display: flex; gap: 0.3rem; }
  .it-btn-sm { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; color: var(--text-muted); cursor: pointer; text-decoration: none; font-size: 0.7rem; transition: var(--transition); }
  .it-btn-sm:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); }

  .it-pagination { display: flex; justify-content: center; align-items: center; gap: 0.3rem; padding: 1rem; border-top: 1px solid var(--border-color); }
  .it-page-btn { min-width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-secondary); border-radius: 6px; font-size: 0.78rem; font-weight: 500; cursor: pointer; transition: var(--transition); text-decoration: none; }
  .it-page-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); }
  .it-page-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }

  .it-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; display: none; align-items: center; justify-content: center; }
  .it-modal { background: var(--card-bg); border-radius: var(--card-radius); max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
  .it-modal-header { padding: 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
  .it-modal-header h2 { font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
  .it-modal-header h2 i { color: var(--primary); }
  .it-modal-close { width: 32px; height: 32px; border-radius: 6px; border: none; background: transparent; color: var(--text-muted); cursor: pointer; font-size: 1.2rem; }
  .it-modal-close:hover { background: var(--border-light); color: var(--text-primary); }
  .it-modal-body { padding: 1.25rem; }
  .it-modal-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.5rem; }

  .it-toast { position: fixed; top: 1rem; right: 1rem; background: var(--success); color: #fff; padding: 1rem 1.5rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 9999; font-size: 0.85rem; font-weight: 600; display: none; }
  .it-toast.error { background: var(--danger); }
  .it-toast.warning { background: var(--warning); }

  .it-log-detail { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; font-size: 0.82rem; }
  .it-log-detail .field { padding: 0.6rem 0.75rem; background: var(--border-light); border-radius: 6px; }
  .it-log-detail .field.full { grid-column: 1 / -1; }
  .it-log-detail .field label { font-size: 0.68rem; color: var(--text-muted); display: block; margin-bottom: 0.2rem; text-transform: uppercase; letter-spacing: 0.03em; font-weight: 600; }
  .it-log-detail .field span { font-size: 0.82rem; color: var(--text-primary); word-break: break-word; }

  @media (max-width: 1200px) { .it-kpis { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 768px) { .it-kpis { grid-template-columns: 1fr; } }
</style>

<div class="it-statusbar">
      <div class="it-statusbar-left">
        <span class="status-dot"></span>
        <span>System Mode: <strong>Production</strong></span>
      </div>
      <div class="it-statusbar-right">
        <span>Activity Logs</span>
        <span>|</span>
        <span><?php echo date('M d, Y H:i'); ?></span>
        <span style="display:inline-flex;align-items:center;gap:0.3rem;" id="liveIndicator"><span style="width:6px;height:6px;border-radius:50%;background:var(--success);animation:pulse 2s infinite;"></span> LIVE</span>
      </div>
    </div>

    <div class="it-kpis">
      <div class="it-kpi">
        <div class="it-kpi-header">
          <span class="it-kpi-title">Today's Activities</span>
          <div class="it-kpi-icon blue"><i class="fas fa-clock"></i></div>
        </div>
        <div class="it-kpi-value"><?php echo $totalToday; ?></div>
        <div class="it-kpi-sub">Activities recorded today</div>
      </div>
      <div class="it-kpi">
        <div class="it-kpi-header">
          <span class="it-kpi-title">User Logins</span>
          <div class="it-kpi-icon green"><i class="fas fa-sign-in-alt"></i></div>
        </div>
        <div class="it-kpi-value" style="color:var(--success);"><?php echo $totalLogins; ?></div>
        <div class="it-kpi-sub">All time logins</div>
      </div>
      <div class="it-kpi">
        <div class="it-kpi-header">
          <span class="it-kpi-title">Admin Activities</span>
          <div class="it-kpi-icon blue"><i class="fas fa-user-shield"></i></div>
        </div>
        <div class="it-kpi-value"><?php echo $totalAdmin; ?></div>
        <div class="it-kpi-sub">Admin & system actions</div>
      </div>
      <div class="it-kpi">
        <div class="it-kpi-header">
          <span class="it-kpi-title">Customer Activities</span>
          <div class="it-kpi-icon blue"><i class="fas fa-users"></i></div>
        </div>
        <div class="it-kpi-value"><?php echo $totalCustomer; ?></div>
        <div class="it-kpi-sub">Login, orders, profile changes</div>
      </div>
      <div class="it-kpi">
        <div class="it-kpi-header">
          <span class="it-kpi-title">Failed Attempts</span>
          <div class="it-kpi-icon red"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
        <div class="it-kpi-value" style="color:var(--danger);"><?php echo $totalFailed; ?></div>
        <div class="it-kpi-sub">Failed logins & errors</div>
      </div>
      <div class="it-kpi">
        <div class="it-kpi-header">
          <span class="it-kpi-title">System Events</span>
          <div class="it-kpi-icon amber"><i class="fas fa-cog"></i></div>
        </div>
        <div class="it-kpi-value" style="color:var(--warning);"><?php echo $totalSystem; ?></div>
        <div class="it-kpi-sub">Backups, settings, DB</div>
      </div>
    </div>

    <div class="it-toolbar">
      <div class="it-toolbar-row">
        <div class="it-toolbar-left">
          <input type="text" class="it-input" id="searchInput" placeholder="Search logs..." value="<?php echo htmlspecialchars($search); ?>">
          <select class="it-select" id="typeFilter" onchange="applyFilters()">
            <option value="">All Types</option>
            <option value="login" <?php echo $activityType==='login'?'selected':''; ?>>Login</option>
            <option value="logout" <?php echo $activityType==='logout'?'selected':''; ?>>Logout</option>
            <option value="registration" <?php echo $activityType==='registration'?'selected':''; ?>>Registration</option>
            <option value="password" <?php echo $activityType==='password'?'selected':''; ?>>Password</option>
            <option value="profile" <?php echo $activityType==='profile'?'selected':''; ?>>Profile</option>
            <option value="order" <?php echo $activityType==='order'?'selected':''; ?>>Orders</option>
            <option value="product" <?php echo $activityType==='product'?'selected':''; ?>>Products</option>
            <option value="shipping" <?php echo $activityType==='shipping'?'selected':''; ?>>Shipping</option>
            <option value="system" <?php echo $activityType==='system'?'selected':''; ?>>System</option>
            <option value="security" <?php echo $activityType==='security'?'selected':''; ?>>Security</option>
          </select>
          <select class="it-select" id="severityFilter" onchange="applyFilters()">
            <option value="">All Severity</option>
            <option value="info" <?php echo $severity==='info'?'selected':''; ?>>Information</option>
            <option value="warning" <?php echo $severity==='warning'?'selected':''; ?>>Warning</option>
            <option value="error" <?php echo $severity==='error'?'selected':''; ?>>Error</option>
            <option value="security" <?php echo $severity==='security'?'selected':''; ?>>Security</option>
          </select>
          <input type="date" class="it-select" id="dateFrom" value="<?php echo $dateFrom; ?>" onchange="applyFilters()" style="width:140px;">
          <input type="date" class="it-select" id="dateTo" value="<?php echo $dateTo; ?>" onchange="applyFilters()" style="width:140px;">
        </div>
        <div class="it-toolbar-right">
          <button class="it-btn" onclick="exportLogs()"><i class="fas fa-download"></i> Export</button>
          <button class="it-btn" onclick="refreshLogs()"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>
      </div>
    </div>

    <div class="it-table-wrap">
      <table class="it-table">
        <thead>
          <tr>
            <th>Log ID</th>
            <th>Date & Time</th>
            <th>User</th>
            <th>Role</th>
            <th>Activity</th>
            <th>Description</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($logs && $logs->num_rows > 0): while ($log = $logs->fetch_assoc()):
            $role = $log['user_role'] ?? 'system';
            $roleTag = in_array($role, ['admin', 'seller', 'user']) ? $role : 'system';
            $isFailed = stripos($log['action'], 'fail') !== false || stripos($log['action'], 'error') !== false;
            $isWarning = stripos($log['action'], 'warning') !== false;
            $isSecurity = stripos($log['action'], 'block') !== false || stripos($log['action'], 'ban') !== false || stripos($log['action'], 'suspici') !== false;
            if ($isFailed) { $badge = 'failed'; $badgeText = 'Failed'; }
            elseif ($isWarning) { $badge = 'warning'; $badgeText = 'Warning'; }
            elseif ($isSecurity) { $badge = 'security'; $badgeText = 'Security'; }
            else { $badge = 'success'; $badgeText = 'Success'; }
          ?>
          <tr>
            <td><span class="it-log-id">#<?php echo $log['id']; ?></span></td>
            <td style="font-family:monospace;font-size:0.72rem;"><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
            <td style="font-weight:600;color:var(--text-primary);"><?php echo htmlspecialchars($log['user_name']); ?></td>
            <td><span class="it-role-tag <?php echo $roleTag; ?>"><?php echo ucfirst($roleTag); ?></span></td>
            <td style="font-weight:500;"><?php echo htmlspecialchars($log['action']); ?></td>
            <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.75rem;"><?php echo htmlspecialchars($log['description'] ?? '-'); ?></td>
            <td><span class="it-badge <?php echo $badge; ?>"><?php echo $badgeText; ?></span></td>
            <td>
              <div class="it-actions">
                <button class="it-btn-sm" title="View Details" onclick="viewLog(<?php echo $log['id']; ?>)"><i class="fas fa-eye"></i></button>
              </div>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="8"><div style="padding:2rem;text-align:center;color:var(--text-muted);"><i class="fas fa-scroll" style="font-size:1.5rem;margin-bottom:0.5rem;opacity:0.3;"></i><p>No activity logs found</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <?php if ($totalPages > 1): ?>
      <div class="it-pagination">
        <?php
        $queryParams = "search=$search&role=$userRole&type=$activityType&severity=$severity&status=$status&from=$dateFrom&to=$dateTo";
        for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?p=<?php echo $i; ?>&<?php echo $queryParams; ?>" class="it-page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>

    <div style="text-align:center;padding:1.25rem 0 0.5rem;border-top:1px solid var(--border-color);margin-top:1.25rem;">
      <p style="font-size:0.75rem;color:var(--text-muted);">Â© <?php echo date('Y'); ?> Inkzion Spectrum Ads. All rights reserved.</p>
    </div>
  <!-- VIEW DETAIL MODAL -->
  <div class="it-modal-overlay" id="viewModal">
    <div class="it-modal">
      <div class="it-modal-header">
        <h2><i class="fas fa-info-circle"></i> Activity Log Details</h2>
        <button class="it-modal-close" onclick="closeModal('viewModal')">&times;</button>
      </div>
      <div class="it-modal-body">
        <div class="it-log-detail" id="logDetail">
          <div class="field"><label>Log ID</label><span id="dId">-</span></div>
          <div class="field"><label>Date & Time</label><span id="dDatetime">-</span></div>
          <div class="field"><label>User</label><span id="dUser">-</span></div>
          <div class="field"><label>Role</label><span id="dRole">-</span></div>
          <div class="field full"><label>Activity Type</label><span id="dAction">-</span></div>
          <div class="field full"><label>Description</label><span id="dDesc">-</span></div>
          <div class="field"><label>IP Address</label><span id="dIp">-</span></div>
          <div class="field"><label>User Agent</label><span id="dAgent">-</span></div>
        </div>
      </div>
      <div class="it-modal-footer">
        <button class="it-btn" onclick="closeModal('viewModal')">Close</button>
      </div>
    </div>
  </div>

  <div class="it-toast" id="toast"></div>

  <script>
    let allLogsData = [];

    function applyFilters() {
      const search = document.getElementById('searchInput').value;
      const type = document.getElementById('typeFilter').value;
      const severity = document.getElementById('severityFilter').value;
      const dateFrom = document.getElementById('dateFrom').value;
      const dateTo = document.getElementById('dateTo').value;
      let params = '?search=' + encodeURIComponent(search) + '&type=' + type + '&severity=' + severity;
      if (dateFrom) params += '&from=' + dateFrom;
      if (dateTo) params += '&to=' + dateTo;
      window.location.href = params;
    }

    document.getElementById('searchInput').addEventListener('keyup', function(e) {
      if (e.key === 'Enter') applyFilters();
    });

    function refreshLogs() {
      showToast('Refreshing logs...', 'success');
      setTimeout(() => { window.location.reload(); }, 500);
    }

    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    function viewLog(id) {
      document.getElementById('viewModal').style.display = 'flex';
      // Load log data via AJAX
      fetch('activity-logs.php?ajax=get&id=' + id)
        .then(r => r.json())
        .then(d => {
          document.getElementById('dId').textContent = '#' + d.id;
          document.getElementById('dDatetime').textContent = d.created_at;
          document.getElementById('dUser').textContent = d.user_name;
          document.getElementById('dRole').innerHTML = '<span class="it-role-tag ' + (d.user_role || 'system') + '">' + (d.user_role ? d.user_role.charAt(0).toUpperCase() + d.user_role.slice(1) : 'System') + '</span>';
          document.getElementById('dAction').textContent = d.action;
          document.getElementById('dDesc').textContent = d.description || '-';
          document.getElementById('dIp').textContent = d.ip_address || '-';
          document.getElementById('dAgent').textContent = d.user_agent ? d.user_agent.substring(0, 100) + '...' : '-';
        })
        .catch(() => {
          // Fallback: fill with placeholder data
          document.getElementById('dId').textContent = '#' + id;
          document.getElementById('dDatetime').textContent = 'Loading...';
        });
    }

    function exportLogs() {
      showToast('Exporting logs to CSV...', 'success');
      // Trigger CSV download
      window.location.href = 'activity-logs.php?export=csv' + window.location.search;
    }

    document.querySelectorAll('.it-modal-overlay').forEach(o => o.addEventListener('click', function(e) {
      if (e.target === this) this.style.display = 'none';
    }));

    // Auto-refresh every 30 seconds
    setInterval(() => {
      const indicator = document.getElementById('liveIndicator');
      if (indicator) {
        indicator.innerHTML = '<span style="width:6px;height:6px;border-radius:50%;background:var(--success);animation:pulse 2s infinite;"></span> LIVE';
      }
    }, 30000);
  </script>
<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>


<?php
$pageTitle = 'Manage Customers';
$pageSubtitle = 'View and manage registered customer accounts';
require_once __DIR__ . '/includes/admin-header.php';

$totalCustomers = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role='user'")->fetch_assoc()['cnt'];
$totalOrders = $conn->query("SELECT COUNT(DISTINCT user_id) as cnt FROM orders")->fetch_assoc()['cnt'];
$withOrders = $conn->query("SELECT COUNT(DISTINCT o.user_id) as cnt FROM orders o INNER JOIN users u ON u.id = o.user_id WHERE u.role='user'")->fetch_assoc()['cnt'];
$recentRegistrations = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role='user' AND created_at >= NOW() - INTERVAL 7 DAY")->fetch_assoc()['cnt'];

// Filters
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = "role='user'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (name LIKE '%$s%' OR email LIKE '%$s%' OR phone LIKE '%$s%')";
}

$orderBy = 'created_at DESC';
if ($sort === 'oldest') $orderBy = 'created_at ASC';
elseif ($sort === 'name') $orderBy = 'name ASC';
elseif ($sort === 'orders') $orderBy = 'created_at DESC';

$totalRows = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE $where")->fetch_assoc()['cnt'];
$totalPages = max(1, ceil($totalRows / $limit));

$customers = $conn->query("SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count, (SELECT MAX(created_at) FROM orders WHERE user_id = u.id) as last_order FROM users u WHERE $where ORDER BY $orderBy LIMIT $limit OFFSET $offset");

// Handle POST
$actionMsg = '';
$actionType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'delete' && $targetId > 0) {
        $conn->query("DELETE FROM users WHERE id=$targetId AND role='user'");
        $conn->query("INSERT INTO activity_logs (user_id, action, description) VALUES ($userId, 'Deleted Customer', 'Admin deleted customer #$targetId')");
        $actionMsg = "Customer #$targetId deleted!";
        $actionType = 'success';
    }
    // Re-fetch after action
    $customers = $conn->query("SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count, (SELECT MAX(created_at) FROM orders WHERE user_id = u.id) as last_order FROM users u WHERE $where ORDER BY $orderBy LIMIT $limit OFFSET $offset");
}
?>
<style>
  .mc-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
  .mc-kpi { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
  .mc-kpi-value { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); line-height: 1.2; }
  .mc-kpi-label { font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.15rem; }
  .mc-toolbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.25rem; }
  .mc-search { padding: 0.5rem 1rem; border: 1.5px solid var(--border-color); border-radius: 999px; font-size: 0.82rem; outline: none; min-width: 220px; background: white; transition: var(--transition); }
  .mc-search:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
  .mc-select { padding: 0.5rem 2rem 0.5rem 1rem; border: 1.5px solid var(--border-color); border-radius: 999px; font-size: 0.82rem; outline: none; background: white; appearance: none; cursor: pointer; }
  .mc-select:focus { border-color: var(--primary); }
  .mc-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; flex-shrink: 0; }
  .mc-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
  .mc-name { font-weight: 600; color: var(--text-primary); font-size: 0.88rem; }
  .mc-email { font-size: 0.75rem; color: var(--text-muted); }
  .mc-badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.65rem; font-weight: 700; }
  .mc-badge.google { background: rgba(59,130,246,0.1); color: #1d4ed8; }
  .mc-badge.email { background: var(--warning-bg); color: #b8860b; }
  .mc-actions { display: flex; gap: 0.35rem; }
  .mc-empty { text-align: center; padding: 3rem; color: var(--text-muted); }
  .mc-empty i { font-size: 3rem; display: block; margin-bottom: 0.75rem; color: rgba(43, 76, 82,0.15); }

  @media (max-width: 768px) {
    .mc-toolbar { flex-direction: column; align-items: stretch; }
    .mc-search { min-width: 0; width: 100%; }
  }
</style>

<div class="mc-kpis">
  <div class="mc-kpi">
    <div class="mc-kpi-label">Total Customers</div>
    <div class="mc-kpi-value"><?php echo $totalCustomers; ?></div>
  </div>
  <div class="mc-kpi">
    <div class="mc-kpi-label">With Orders</div>
    <div class="mc-kpi-value"><?php echo $withOrders; ?></div>
  </div>
  <div class="mc-kpi">
    <div class="mc-kpi-label">New This Week</div>
    <div class="mc-kpi-value"><?php echo $recentRegistrations; ?></div>
  </div>
  <div class="mc-kpi">
    <div class="mc-kpi-label">Total Pages</div>
    <div class="mc-kpi-value"><?php echo $totalPages; ?></div>
  </div>
</div>

<?php if ($actionMsg): ?>
<div style="background:<?php echo $actionType === 'success' ? 'var(--success-bg)' : 'var(--danger-bg)'; ?>;color:<?php echo $actionType === 'success' ? '#047857' : '#dc2626'; ?>;padding:0.75rem 1rem;border-radius:10px;font-size:0.85rem;font-weight:600;margin-bottom:1rem;">
  <?php echo htmlspecialchars($actionMsg); ?>
</div>
<?php endif; ?>

<div class="mc-toolbar">
  <form method="GET" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
    <input type="text" name="search" class="mc-search" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
    <select name="sort" class="mc-select" onchange="this.form.submit()">
      <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
      <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
      <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>A-Z</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
    <?php if ($search): ?>
    <a href="manage-customers.php" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Clear</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Customer</th>
          <th>Contact</th>
          <th>Registered</th>
          <th>Orders</th>
          <th>Last Order</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($customers->num_rows === 0): ?>
        <tr><td colspan="6"><div class="mc-empty"><i class="fas fa-users"></i><h3>No customers found</h3></div></td></tr>
        <?php else: ?>
        <?php while ($c = $customers->fetch_assoc()): 
          $initial = strtoupper(substr($c['name'] ?? '?', 0, 1));
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:0.65rem;">
              <div class="mc-avatar">
                <?php if (!empty($c['avatar'])): ?>
                <img src="<?php echo htmlspecialchars($c['avatar']); ?>" alt="">
                <?php else: ?>
                <?php echo $initial; ?>
                <?php endif; ?>
              </div>
              <div>
                <div class="mc-name"><?php echo htmlspecialchars($c['name'] ?? 'Unknown'); ?></div>
                <div class="mc-email">#<?php echo $c['id']; ?> · <?php echo !empty($c['google_id']) ? '<span class="mc-badge google"><i class="fab fa-google"></i> Google</span>' : '<span class="mc-badge email">Email</span>'; ?></div>
              </div>
            </div>
          </td>
          <td>
            <div style="font-size:0.82rem;">
              <div><?php echo htmlspecialchars($c['email'] ?? ''); ?></div>
              <?php if (!empty($c['phone'])): ?>
              <div style="color:var(--text-muted);font-size:0.75rem;"><?php echo htmlspecialchars($c['phone']); ?></div>
              <?php endif; ?>
            </div>
          </td>
          <td><span style="font-size:0.82rem;"><?php echo date('M j, Y', strtotime($c['created_at'])); ?></span></td>
          <td><span style="font-weight:700;font-size:0.88rem;"><?php echo $c['order_count']; ?></span></td>
          <td><span style="font-size:0.82rem;color:var(--text-muted);"><?php echo $c['last_order'] ? date('M j, Y', strtotime($c['last_order'])) : 'N/A'; ?></span></td>
          <td>
            <div class="mc-actions">
              <form method="POST" onsubmit="return confirm('Delete <?php echo htmlspecialchars(addslashes($c['name'] ?? '')); ?>? This cannot be undone.');" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="<?php echo $c['id']; ?>">
                <button type="submit" class="btn btn-danger btn-sm" title="Delete Customer"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <a href="?p=<?php echo max(1, $page-1); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>"><i class="fas fa-chevron-left"></i></a>
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 1 && $i <= $page + 1)): ?>
      <a href="?p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
    <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
      <span class="page-btn disabled">...</span>
    <?php endif; ?>
  <?php endfor; ?>
  <a href="?p=<?php echo min($totalPages, $page+1); ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo $sort; ?>" class="page-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>"><i class="fas fa-chevron-right"></i></a>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

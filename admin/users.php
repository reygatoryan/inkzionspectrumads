<?php
// Early AJAX handler — must run before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax'])) {
    session_start();
    require_once __DIR__ . '/../db-config.php';
    header('Content-Type: application/json');
    if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($action === 'create' && !empty($_POST['name']) && !empty($_POST['email'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $role = $conn->real_escape_string($_POST['role'] ?? 'user');
        $check = $conn->query("SELECT id FROM users WHERE email='$email'");
        if ($check->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'Email already exists!']);
        } else {
            $conn->query("INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$password', '$role')");
            $newId = $conn->insert_id;
            $conn->query("INSERT INTO activity_logs (user_id, action, description) VALUES ($_SESSION[user_id], 'Created User', 'Admin created user #$newId ($name)')");
            echo json_encode(['success' => true, 'action' => 'create', 'user' => ['id' => $newId, 'name' => $name, 'email' => $email, 'role' => $role, 'created_at' => date('Y-m-d H:i:s')]]);
        }
        exit;
    }
    if ($action === 'edit' && $userId > 0 && !empty($_POST['name'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $role = $conn->real_escape_string($_POST['role'] ?? 'user');
        $conn->query("UPDATE users SET name='$name', email='$email', role='$role' WHERE id=$userId");
        $conn->query("INSERT INTO activity_logs (user_id, action, description) VALUES ($_SESSION[user_id], 'Updated User', 'Admin updated user #$userId')");
        echo json_encode(['success' => true, 'action' => 'edit', 'user' => ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => $role]]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$pageTitle = 'All Users';
$pageSubtitle = 'Manage registered users and their roles';
require_once __DIR__ . '/includes/admin-header.php';

// Stats
$totalUsers = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];
$totalAdmins = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role='admin'")->fetch_assoc()['cnt'];
$totalSellers = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role='admin'")->fetch_assoc()['cnt'];
$totalCustomers = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role='user'")->fetch_assoc()['cnt'];
$activeUsers = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];
$suspendedUsers = 0;
$onlineUsers = (int)($conn->query("SELECT COUNT(*) as cnt FROM users WHERE last_seen >= NOW() - INTERVAL 5 MINUTE")->fetch_assoc()['cnt'] ?? 0);

// Filters
$where = "1=1";
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$page = max(1, (int)($_GET['p'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

if ($search) { $where .= " AND (name LIKE '%$search%' OR email LIKE '%$search%')"; }
if ($roleFilter) { $where .= " AND role='$roleFilter'"; }

$totalRows = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE $where")->fetch_assoc()['cnt'];
$totalPages = max(1, ceil($totalRows / $limit));

$users = $conn->query("SELECT * FROM users WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

// Handle POST actions
$actionMsg = '';
$actionType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'create' && !empty($_POST['name']) && !empty($_POST['email'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $role = $conn->real_escape_string($_POST['role'] ?? 'user');
        $check = $conn->query("SELECT id FROM users WHERE email='$email'");
        if ($check->num_rows > 0) {
            $actionMsg = 'Email already exists!';
            $actionType = 'error';
        } else {
            $conn->query("INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$password', '$role')");
            $newId = $conn->insert_id;
            $conn->query("INSERT INTO activity_logs (user_id, action, description) VALUES ($_SESSION[user_id], 'Created User', 'Admin created user #$newId ($name)')");
            $actionMsg = "User '$name' created successfully!";
            $actionType = 'success';
        }
    } elseif ($action === 'edit' && $userId > 0 && !empty($_POST['name'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);
        $role = $conn->real_escape_string($_POST['role'] ?? 'user');
        $conn->query("UPDATE users SET name='$name', email='$email', role='$role' WHERE id=$userId");
        $conn->query("INSERT INTO activity_logs (user_id, action, description) VALUES ($_SESSION[user_id], 'Updated User', 'Admin updated user #$userId')");
        $actionMsg = "User #$userId updated!";
        $actionType = 'success';
    } elseif ($action === 'delete' && $userId > 0) {
        if ($userId === (int)$_SESSION['user_id']) {
            $actionMsg = 'You cannot delete your own account!';
            $actionType = 'error';
        } elseif ($conn->query("DELETE FROM users WHERE id=$userId")) {
            $conn->query("INSERT INTO activity_logs (user_id, action, description) VALUES ($_SESSION[user_id], 'Deleted User', 'Admin deleted user #$userId')");
            $actionMsg = "User #$userId deleted!";
            $actionType = 'success';
        } else {
            $actionMsg = 'Failed to delete user. They may have related records.';
            $actionType = 'error';
        }
    }
}
?>
<style>
    .kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.25rem; }
    .kpi { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--card-radius); padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .kpi-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
    .kpi-title { font-size: 0.68rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
    .kpi-icon { width: 32px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; }
    .kpi-icon.blue { background: var(--primary-bg); color: var(--primary); }
    .kpi-icon.green { background: var(--success-bg); color: var(--success); }
    .kpi-icon.amber { background: var(--warning-bg); color: var(--warning); }
    .kpi-icon.red { background: var(--danger-bg); color: var(--danger); }
    .kpi-value { font-size: 1.6rem; font-weight: 800; color: var(--text-primary); line-height: 1.2; }
    .kpi-sub { font-size: 0.68rem; color: var(--text-muted); margin-top: 0.2rem; }

    .user-categories { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.25rem; }
    .category-card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--card-radius); padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); cursor: pointer; transition: var(--transition); }
    .category-card:hover { border-color: var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .category-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
    .category-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
    .category-icon.admin { background: var(--danger-bg); color: var(--danger); }
    .category-icon.seller { background: var(--warning-bg); color: var(--warning); }
    .category-icon.customer { background: var(--primary-bg); color: var(--primary); }
    .category-count { font-size: 1.8rem; font-weight: 800; color: var(--text-primary); }
    .category-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; }

    .toolbar { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--card-radius); padding: 1rem; margin-bottom: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .toolbar-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
    .toolbar-left { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
    .toolbar-right { display: flex; gap: 0.5rem; }
    .form-input, .form-select { padding: 0.5rem 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.82rem; font-family: var(--font); background: #fff; color: var(--text-primary); }
    .form-input { width: 220px; }
    .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-secondary); border-radius: 6px; font-size: 0.82rem; font-weight: 500; cursor: pointer; transition: var(--transition); text-decoration: none; }
    .btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); }
    .btn-primary { background: var(--primary); color: #fff; border-color: var(--primary); }
    .btn-primary:hover { background: var(--primary-light); border-color: var(--primary-light); }
    .btn-danger { color: var(--danger); }
    .btn-danger:hover { background: var(--danger-bg); border-color: var(--danger); }

    .table-wrap { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--card-radius); overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
    .table th { padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: var(--text-muted); font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border-color); background: #F8FAFC; white-space: nowrap; }
    .table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-light); color: var(--text-secondary); }
    .table tr:last-child td { border-bottom: none; }
    .table tr:hover td { background: #F8FAFC; }
    .user { display: flex; align-items: center; gap: 0.75rem; }
    .avatar { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; color: #fff; flex-shrink: 0; }
    .avatar.admin { background: var(--danger); }
    .avatar.seller { background: var(--warning); }
    .avatar.customer { background: var(--primary); }
    .name { font-weight: 600; color: var(--text-primary); }
    .email { font-size: 0.68rem; color: var(--text-muted); font-family: monospace; }
    .role { display: inline-flex; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.65rem; font-weight: 600; }
    .role.admin { background: var(--danger-bg); color: var(--danger); }
    .role.seller { background: var(--warning-bg); color: var(--warning); }
    .role.customer { background: var(--primary-bg); color: var(--primary); }
    .actions { display: flex; gap: 0.3rem; }
    .btn-sm { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--border-color); background: transparent; color: var(--text-muted); cursor: pointer; text-decoration: none; font-size: 0.7rem; transition: var(--transition); }
    .btn-sm:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); }
    .btn-sm.danger:hover { border-color: var(--danger); color: var(--danger); background: var(--danger-bg); }

    .pagination { display: flex; justify-content: center; align-items: center; gap: 0.3rem; padding: 1rem; border-top: 1px solid var(--border-color); }
    .page-btn { min-width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-secondary); border-radius: 6px; font-size: 0.78rem; font-weight: 500; cursor: pointer; transition: var(--transition); text-decoration: none; }
    .page-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); }
    .page-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }

    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; display: none; align-items: center; justify-content: center; }
    .modal { background: var(--card-bg); border-radius: var(--card-radius); max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
    .modal-header { padding: 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    .modal-header h2 { font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
    .modal-header h2 i { color: var(--primary); }
    .modal-close { width: 32px; height: 32px; border-radius: 6px; border: none; background: transparent; color: var(--text-muted); cursor: pointer; font-size: 1.2rem; }
    .modal-close:hover { background: var(--border-light); color: var(--text-primary); }
    .modal-body { padding: 1.25rem; }
    .modal-footer { padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.5rem; }

    .toast { position: fixed; top: 1rem; right: 1rem; background: var(--success); color: #fff; padding: 1rem 1.5rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 9999; font-size: 0.85rem; font-weight: 600; display: none; }
    .toast.error { background: var(--danger); }
    .toast.warning { background: var(--warning); }

    @media (max-width: 1200px) { .kpis { grid-template-columns: repeat(2, 1fr); } .user-categories { grid-template-columns: 1fr; } }
    @media (max-width: 768px) { .kpis { grid-template-columns: 1fr; } }
</style>

<div class="user-categories">
  <div class="category-card" onclick="filterByRole('admin')">
    <div class="category-header">
      <div class="category-icon admin"><i class="fas fa-user-shield"></i></div>
    </div>
    <div class="category-count"><?php echo $totalAdmins; ?></div>
    <div class="category-label">Administrators</div>
  </div>
  <div class="category-card" onclick="filterByRole('admin')">
    <div class="category-header">
      <div class="category-icon seller"><i class="fas fa-store"></i></div>
    </div>
    <div class="category-count"><?php echo $totalSellers; ?></div>
    <div class="category-label">Sellers</div>
  </div>
  <div class="category-card" onclick="filterByRole('user')">
    <div class="category-header">
      <div class="category-icon customer"><i class="fas fa-user"></i></div>
    </div>
    <div class="category-count"><?php echo $totalCustomers; ?></div>
    <div class="category-label">Customers</div>
  </div>
</div>

<div class="kpis">
  <div class="kpi">
    <div class="kpi-header">
      <span class="kpi-title">Total Users</span>
      <div class="kpi-icon blue"><i class="fas fa-users"></i></div>
    </div>
    <div class="kpi-value"><?php echo $totalUsers; ?></div>
    <div class="kpi-sub">All accounts</div>
  </div>
  <div class="kpi">
    <div class="kpi-header">
      <span class="kpi-title">Active Users</span>
      <div class="kpi-icon green"><i class="fas fa-check-circle"></i></div>
    </div>
    <div class="kpi-value" style="color:var(--success);"><?php echo $activeUsers; ?></div>
    <div class="kpi-sub">Active accounts</div>
  </div>
  <div class="kpi">
    <div class="kpi-header">
      <span class="kpi-title">Suspended Accounts</span>
      <div class="kpi-icon red"><i class="fas fa-ban"></i></div>
    </div>
    <div class="kpi-value" style="color:var(--danger);"><?php echo $suspendedUsers; ?></div>
    <div class="kpi-sub">Suspended users</div>
  </div>
  <div class="kpi">
    <div class="kpi-header">
      <span class="kpi-title">Online Users</span>
      <div class="kpi-icon green"><i class="fas fa-wifi"></i></div>
    </div>
    <div class="kpi-value" style="color:var(--success);"><?php echo $onlineUsers; ?></div>
    <div class="kpi-sub">Currently online</div>
  </div>
</div>

<div class="toolbar">
  <div class="toolbar-row">
    <div class="toolbar-left">
      <input type="text" class="form-input" id="searchInput" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
      <select class="form-select" id="roleFilter" onchange="applyFilters()">
        <option value="">All Roles</option>
        <option value="admin" <?php echo $roleFilter==='admin'?'selected':''; ?>>Admin</option>
        <option value="seller" <?php echo $roleFilter==='admin'?'selected':''; ?>>Seller</option>
        <option value="user" <?php echo $roleFilter==='user'?'selected':''; ?>>Customer</option>
      </select>
    </div>
    <div class="toolbar-right">
      <button class="btn btn-primary" onclick="openModal('create')"><i class="fas fa-plus"></i> Create User</button>
    </div>
  </div>
</div>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>User</th>
        <th>Role</th>
        <th>Verified</th>
        <th>Registration Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($users && $users->num_rows > 0): while ($u = $users->fetch_assoc()):
        $roleClass = $u['role'] === 'admin' ? 'admin' : ($u['role'] === 'admin' ? 'admin' : 'customer');
      ?>
      <tr data-user-id="<?php echo $u['id']; ?>">
        <td>
          <div class="user">
            <div class="avatar <?php echo $roleClass; ?>"><?php echo strtoupper(substr($u['name'], 0, 1)); ?></div>
            <div>
              <div class="name"><?php echo htmlspecialchars($u['name']); ?></div>
              <div class="email"><?php echo htmlspecialchars($u['email']); ?></div>
            </div>
          </div>
        </td>
        <td><span class="role <?php echo $roleClass; ?>"><?php echo ucfirst($u['role']); ?></span></td>
        <td><span style="color:var(--success);"><i class="fas fa-check-circle"></i> Active</span></td>
        <td style="font-size:0.75rem;color:var(--text-muted);"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
        <td>
          <div class="actions">
            <button class="btn btn-sm" title="View Profile" onclick="viewUser(<?php echo $u['id']; ?>,'<?php echo htmlspecialchars($u['name']); ?>','<?php echo $u['email']; ?>','<?php echo $u['role']; ?>')"><i class="fas fa-eye"></i></button>
            <button class="btn btn-sm" title="Edit User" onclick="openModal('edit',<?php echo $u['id']; ?>,'<?php echo htmlspecialchars(addslashes($u['name'])); ?>','<?php echo $u['email']; ?>','<?php echo $u['role']; ?>')"><i class="fas fa-pen"></i></button>
            <button class="btn btn-sm" title="Change Role" onclick="changeRole(<?php echo $u['id']; ?>,'<?php echo $u['role']; ?>')"><i class="fas fa-shield-alt"></i></button>
            <button class="btn btn-sm btn-danger" title="Delete User" onclick="deleteUser(this, <?php echo $u['id']; ?>,'<?php echo htmlspecialchars($u['name']); ?>')"><i class="fas fa-trash"></i></button>
          </div>
        </td>
      </tr>
      <?php endwhile; else: ?>
      <tr id="noUsersRow"><td colspan="5"><div class="empty-state" style="padding:2rem;text-align:center;color:var(--text-muted);"><i class="fas fa-users" style="font-size:1.5rem;margin-bottom:0.5rem;opacity:0.3;"></i><p>No users found</p></div></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $roleFilter; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<div style="text-align:center;padding:1.25rem 0 0.5rem;border-top:1px solid var(--border-color);margin-top:1.25rem;">
  <p style="font-size:0.75rem;color:var(--text-muted);">&copy; <?php echo date('Y'); ?> Inkzion Spectrum Ads. All rights reserved.</p>
</div>

<div class="modal-overlay" id="userModal">
  <div class="modal">
    <div class="modal-header">
      <h2><i class="fas fa-user"></i> <span id="modalTitle">Create User</span></h2>
      <button class="modal-close" onclick="closeModal('userModal')">&times;</button>
    </div>
    <form method="POST" onsubmit="return submitUserForm(event)">
      <div class="modal-body">
        <input type="hidden" name="action" id="formAction" value="create">
        <input type="hidden" name="user_id" id="userId" value="0">
        <input type="hidden" name="ajax" value="1">
        <div style="display:grid;gap:1rem;">
          <div>
            <label style="display:block;font-size:0.75rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.3rem;">Full Name *</label>
            <input type="text" name="name" id="userName" required class="form-input" style="width:100%;" placeholder="e.g. John Doe">
          </div>
          <div>
            <label style="display:block;font-size:0.75rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.3rem;">Email *</label>
            <input type="email" name="email" id="userEmail" required class="form-input" style="width:100%;" placeholder="user@example.com">
          </div>
          <div>
            <label style="display:block;font-size:0.75rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.3rem;">Role *</label>
            <select name="role" id="userRole" required class="form-select" style="width:100%;">
              <option value="user">Customer</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('userModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save User</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="viewModal">
  <div class="modal">
    <div class="modal-header">
      <h2><i class="fas fa-user"></i> User Profile</h2>
      <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div style="text-align:center;padding:1rem 0;">
        <div id="viewAvatar" style="width:80px;height:80px;border-radius:12px;margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;color:#fff;"></div>
        <h3 id="viewName" style="font-size:1.2rem;font-weight:700;color:var(--text-primary);"></h3>
        <p id="viewEmail" style="font-size:0.85rem;color:var(--text-muted);font-family:monospace;"></p>
        <p id="viewRole" style="margin-top:0.5rem;"></p>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;font-size:0.8rem;margin-top:1rem;">
        <div style="padding:0.75rem;background:var(--border-light);border-radius:8px;"><span style="color:var(--text-muted);">User ID</span><br><span style="font-family:monospace;color:var(--primary);font-weight:600;" id="viewId"></span></div>
        <div style="padding:0.75rem;background:var(--border-light);border-radius:8px;"><span style="color:var(--text-muted);">Registered</span><br><span style="font-family:monospace;" id="viewDate"></span></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('viewModal')">Close</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="deleteModal">
  <div class="modal" style="max-width:400px;text-align:center;">
    <div class="modal-header">
      <h2><i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i> Delete User</h2>
      <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
    </div>
    <div class="modal-body">
      <p style="color:var(--text-secondary);margin-bottom:0.5rem;">Are you sure you want to delete <strong id="deleteName" style="color:var(--text-primary);"></strong>?</p>
      <p style="font-size:0.75rem;color:var(--text-muted);">This action cannot be undone.</p>
    </div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('deleteModal')">Cancel</button>
      <button class="btn btn-danger" id="confirmDeleteBtn" onclick="confirmDelete()"><i class="fas fa-trash"></i> Delete</button>
    </div>
  </div>
</div>

<div id="miniToast" style="position:fixed;bottom:1rem;right:1rem;z-index:10000;padding:0.5rem 1rem;border-radius:8px;font-size:0.8rem;font-weight:500;color:#fff;opacity:0;transform:translateY(10px);transition:opacity 0.25s,transform 0.25s;pointer-events:none;max-width:260px;display:none;"></div>

<div class="toast" id="toast"></div>

<script>
  function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const role = document.getElementById('roleFilter').value;
    window.location.href = '?search=' + encodeURIComponent(search) + '&role=' + role;
  }

  function filterByRole(role) {
    document.getElementById('roleFilter').value = role;
    applyFilters();
  }

  function openModal(type, id, name, email, role) {
    document.getElementById('userModal').style.display = 'flex';
    if (type === 'edit') {
      document.getElementById('modalTitle').textContent = 'Edit User';
      document.getElementById('formAction').value = 'edit';
      document.getElementById('userId').value = id;
      document.getElementById('userName').value = name || '';
      document.getElementById('userEmail').value = email || '';
      document.getElementById('userRole').value = role || 'user';
    } else {
      document.getElementById('modalTitle').textContent = 'Create User';
      document.getElementById('formAction').value = 'create';
      document.getElementById('userId').value = 0;
      document.getElementById('userName').value = '';
      document.getElementById('userEmail').value = '';
      document.getElementById('userRole').value = 'user';
    }
  }

  function closeModal(id) { document.getElementById(id).style.display = 'none'; }

  function viewUser(id, name, email, role) {
    document.getElementById('viewModal').style.display = 'flex';
    document.getElementById('viewId').textContent = '#' + id;
    document.getElementById('viewName').textContent = name;
    document.getElementById('viewEmail').textContent = email;
    const avatar = document.getElementById('viewAvatar');
    avatar.textContent = name.charAt(0).toUpperCase();
    const colors = {admin:'var(--danger)',seller:'var(--warning)',user:'var(--primary)'};
    avatar.style.background = colors[role] || 'var(--primary)';
    const roleBadge = {admin:'<span class="role admin">Admin</span>',seller:'<span class="role seller">Seller</span>',user:'<span class="role customer">Customer</span>'};
    document.getElementById('viewRole').innerHTML = roleBadge[role] || roleBadge.user;
    document.getElementById('viewDate').textContent = '<?php echo date('Y-m-d H:i'); ?>';
  }

  let deleteId = 0;
  let deleteRow = null;

  function deleteUser(btn, id, name) {
    deleteId = id;
    deleteRow = btn.closest('tr');
    document.getElementById('deleteModal').style.display = 'flex';
    document.getElementById('deleteName').textContent = name;
  }

  function confirmDelete() {
    if (!deleteId) return;
    const btn = document.getElementById('confirmDeleteBtn');
    btn.disabled = true;
    btn.textContent = 'Deleting...';

    fetch('../api/delete-user.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'user_id=' + deleteId
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        if (deleteRow) deleteRow.remove();
        showMiniToast('User deleted', 'success');
      } else {
        showMiniToast(data.error || 'Delete failed', 'error');
      }
    })
    .catch(function() {
      showMiniToast('Network error', 'error');
    })
    .finally(function() {
      closeModal('deleteModal');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-trash"></i> Delete';
    });
  }

  function showMiniToast(msg, type) {
    var el = document.getElementById('miniToast');
    if (!el) return;
    el.textContent = msg;
    el.style.background = type === 'success' ? 'rgba(5,150,105,0.92)' : 'rgba(239,68,68,0.92)';
    el.style.display = 'block';
    el.style.opacity = '1';
    el.style.transform = 'translateY(0)';
    clearTimeout(el._timer);
    el._timer = setTimeout(function() {
      el.style.opacity = '0';
      el.style.transform = 'translateY(10px)';
      setTimeout(function() { el.style.display = 'none'; }, 300);
    }, 2500);
  }

  function submitUserForm(e) {
    e.preventDefault();
    var form = e.target;
    var fd = new FormData(form);
    var btn = form.querySelector('[type="submit"]');
    var orig = btn.innerHTML;
    btn.disabled = true;
    btn.textContent = 'Saving...';

    fetch(window.location.href, { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          var tbody = document.querySelector('.table tbody');
          if (data.action === 'create' && data.user) {
            var u = data.user;
            var roleClass = u.role === 'admin' ? 'admin' : (u.role === 'seller' ? 'seller' : 'customer');
            var tr = document.createElement('tr');
            tr.setAttribute('data-user-id', u.id);
            tr.innerHTML =
              '<td><div class="user"><div class="avatar ' + roleClass + '">' + u.name.charAt(0).toUpperCase() + '</div><div><div class="name">' + escapeHtml(u.name) + '</div><div class="email">' + escapeHtml(u.email) + '</div></div></div></td>' +
              '<td><span class="role ' + roleClass + '">' + u.role.charAt(0).toUpperCase() + u.role.slice(1) + '</span></td>' +
              '<td><span style="color:var(--success);"><i class="fas fa-check-circle"></i> Active</span></td>' +
              '<td style="font-size:0.75rem;color:var(--text-muted);">' + (u.created_at ? new Date(u.created_at).toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'}) : '') + '</td>' +
              '<td><div class="actions"><button class="btn btn-sm" title="View Profile" onclick="viewUser(' + u.id + ',\'' + escapeHtml(u.name) + '\',\'' + escapeHtml(u.email) + '\',\'' + u.role + '\')"><i class="fas fa-eye"></i></button><button class="btn btn-sm" title="Edit User" onclick="openModal(\'edit\',' + u.id + ',\'' + escapeHtml(u.name) + '\',\'' + escapeHtml(u.email) + '\',\'' + u.role + '\')"><i class="fas fa-pen"></i></button><button class="btn btn-sm" title="Change Role" onclick="changeRole(' + u.id + ',\'' + u.role + '\')"><i class="fas fa-shield-alt"></i></button><button class="btn btn-sm btn-danger" title="Delete User" onclick="deleteUser(this,' + u.id + ',\'' + escapeHtml(u.name) + '\')"><i class="fas fa-trash"></i></button></div></td>';
            var nr = document.getElementById('noUsersRow'); if (nr) { nr.remove(); }
            tbody.prepend(tr);
          } else if (data.action === 'edit' && data.user) {
            var row = document.querySelector('tr[data-user-id="' + data.user.id + '"]');
            if (row) {
              var roleClass = data.user.role === 'admin' ? 'admin' : (data.user.role === 'seller' ? 'seller' : 'customer');
              row.querySelector('.name').textContent = data.user.name;
              row.querySelector('.email').textContent = data.user.email;
              row.querySelector('.avatar').className = 'avatar ' + roleClass;
              row.querySelector('.avatar').textContent = data.user.name.charAt(0).toUpperCase();
              row.querySelector('.role').textContent = data.user.role.charAt(0).toUpperCase() + data.user.role.slice(1);
              row.querySelector('.role').className = 'role ' + roleClass;
            }
          }
          closeModal('userModal');
          showMiniToast(data.action === 'create' ? 'User created' : 'User updated', 'success');
        } else {
          showMiniToast(data.error || 'Save failed', 'error');
        }
      })
      .catch(function() {
        showMiniToast('Network error', 'error');
      })
      .finally(function() {
        btn.disabled = false;
        btn.innerHTML = orig;
      });

    return false;
  }

  function escapeHtml(str) {
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  function changeRole(id, currentRole) {
    const roles = ['user','admin','admin'];
    const next = roles[(roles.indexOf(currentRole) + 1) % 3];
    if (confirm('Change role to ' + next + '?')) {
      const f = document.createElement('form'); f.method = 'POST';
      f.innerHTML = '<input name="action" value="edit"><input name="user_id" value="' + id + '"><input name="name" value="keep"><input name="email" value="keep@keep.com"><input name="role" value="' + next + '">';
      document.body.appendChild(f); f.submit();
    }
  }

  document.getElementById('searchInput').addEventListener('keyup', function(e) {
    if (e.key === 'Enter') applyFilters();
  });

  document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
  }));

  <?php if ($actionMsg): ?>
  setTimeout(function() { showToast(<?php echo json_encode($actionMsg); ?>, <?php echo json_encode($actionType); ?>); }, 0);
  <?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

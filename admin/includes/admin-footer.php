    </div>
  </main>
</div>
<div id="toast" class="toast toast-info hidden"></div>
<style>
  .header-notif-wrapper { position: relative; }
  .notif-bell-dot { position: absolute; top: 5px; right: 5px; width: 8px; height: 8px; border-radius: 50%; background: #ef4444; border: 2px solid white; }
  .notif-dropdown { position: absolute; top: calc(100% + 8px); right: 0; background: white; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 12px 40px rgba(0,0,0,0.15); width: 360px; max-height: 480px; display: flex; flex-direction: column; z-index: 1000; opacity: 0; visibility: hidden; transform: translateY(10px); transition: opacity 0.2s, transform 0.2s, visibility 0.2s; }
  .notif-dropdown.active { opacity: 1; visibility: visible; transform: translateY(0); }
  .notif-dropdown-header { display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; font-weight: 700; color: #0f172a; flex-shrink: 0; }
  .notif-mark-all-btn { background: none; border: none; color: #2B4C52; font-size: 0.72rem; font-weight: 600; cursor: pointer; padding: 0.2rem 0.5rem; border-radius: 6px; }
  .notif-mark-all-btn:hover { background: rgba(43,76,82,0.08); }
  .notif-dropdown-list { overflow-y: auto; flex: 1; max-height: 400px; }
  .notif-item { display: flex; gap: 0.7rem; padding: 0.75rem 1rem; border-bottom: 1px solid #f8fafc; cursor: pointer; transition: background 0.15s; text-decoration: none; color: inherit; align-items: flex-start; }
  .notif-item:hover { background: #f8fafc; }
  .notif-item.unread { background: rgba(43,76,82,0.04); }
  .notif-item.unread:hover { background: rgba(43,76,82,0.08); }
  .notif-item-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0; }
  .notif-item-icon.orange { background: rgba(245,158,11,0.12); color: #d97706; }
  .notif-item-icon.green { background: rgba(16,185,129,0.12); color: #059669; }
  .notif-item-icon.blue { background: rgba(43,76,82,0.12); color: #2B4C52; }
  .notif-item-icon.purple { background: rgba(139,92,246,0.12); color: #7c3aed; }
  .notif-item-icon.red { background: rgba(239,68,68,0.12); color: #dc2626; }
  .notif-item-body { flex: 1; min-width: 0; }
  .notif-item-title { font-size: 0.82rem; font-weight: 600; color: #0f172a; line-height: 1.3; }
  .notif-item.unread .notif-item-title { font-weight: 700; }
  .notif-item-text { font-size: 0.75rem; color: #64748b; margin-top: 0.1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .notif-item-time { font-size: 0.68rem; color: #94a3b8; margin-top: 0.2rem; }
  .notif-unread-dot { width: 8px; height: 8px; border-radius: 50%; background: #2B4C52; flex-shrink: 0; margin-top: 4px; }
  .notif-loading, .notif-empty { text-align: center; padding: 2rem; color: #94a3b8; font-size: 0.82rem; }
  .notif-error { text-align: center; padding: 1rem; color: #dc2626; font-size: 0.78rem; }
</style>
<script>
function toggleNotifDropdown() {
  var dd = document.getElementById('notifDropdown');
  if (!dd) return;
  var wasActive = dd.classList.contains('active');
  dd.classList.toggle('active');
  if (!wasActive) loadNotifs();
}
function closeNotifDropdown() {
  var dd = document.getElementById('notifDropdown');
  if (dd) dd.classList.remove('active');
}
function toggleProfileDropdown() {
  var dd = document.getElementById('profileDropdown');
  if (dd) dd.classList.toggle('active');
}
function closeProfileDropdown() {
  var dd = document.getElementById('profileDropdown');
  if (dd) dd.classList.remove('active');
}

// Load notifications into dropdown
var notifFetching = false;
function loadNotifs() {
  if (notifFetching) return;
  notifFetching = true;
  var list = document.getElementById('notifList');
  if (!list) { notifFetching = false; return; }
  list.innerHTML = '<div class="notif-loading">Loading...</div>';
  fetch('../api/notifications.php?limit=20').then(function(r){return r.json();}).then(function(d){
    notifFetching = false;
    if (!d.success) { list.innerHTML = '<div class="notif-error">Failed to load</div>'; return; }
    var notifs = d.notifications || [];
    if (!notifs.length) {
      list.innerHTML = '<div class="notif-empty">No notifications yet</div>';
      updateNotifBellDot(0);
      return;
    }
    var html = '';
    notifs.forEach(function(n){
      html += renderNotifItem(n);
    });
    list.innerHTML = html;
    updateNotifBellDot(d.unread_count);
  }).catch(function(){
    notifFetching = false;
    list.innerHTML = '<div class="notif-error">Failed to load</div>';
  });
}

function renderNotifItem(n) {
  var iconHtml = notifTypeIcon(n.related_type || n.type);
  var link = notifTypeLink(n);
  var timeAgo = notifTimeAgo(n.created_at);
  var isUnread = !n.is_read;
  return '<a href="'+link+'" class="notif-item'+(isUnread?' unread':'')+'" onclick="notifItemClick('+n.id+',\''+link+'\')">'+
    '<div class="notif-item-icon '+iconHtml.color+'">'+iconHtml.icon+'</div>'+
    '<div class="notif-item-body">'+
      '<div class="notif-item-title">'+escapeHtml(n.title)+'</div>'+
      '<div class="notif-item-text">'+escapeHtml(n.body)+'</div>'+
      '<div class="notif-item-time">'+timeAgo+'</div>'+
    '</div>'+
    (isUnread?'<div class="notif-unread-dot"></div>':'')+
  '</a>';
}

function notifTypeIcon(relatedType) {
  switch(relatedType) {
    case 'order': return {icon:'<i class="fas fa-shopping-cart"></i>', color:'orange'};
    case 'order_proposal': return {icon:'<i class="fas fa-file-invoice"></i>', color:'green'};
    case 'custom_request': return {icon:'<i class="fas fa-paint-brush"></i>', color:'purple'};
    case 'chat': return {icon:'<i class="fas fa-comment-dots"></i>', color:'blue'};
    default: return {icon:'<i class="fas fa-bell"></i>', color:'blue'};
  }
}

function notifTypeLink(n) {
  var rt = n.related_type;
  var ri = n.related_id;
  if (rt === 'order' && ri) return 'orders.php';
  if (rt === 'order_proposal' && ri) return '../customer/order-form.php?id=' + ri;
  if (rt === 'custom_request' && ri) return 'custom-requests.php';
  if (rt === 'chat' && ri) return 'messages.php?conversation=' + ri;
  return '#';
}

function notifTimeAgo(dateStr) {
  var now = new Date();
  var d = new Date(dateStr);
  var diff = Math.floor((now - d) / 1000);
  if (diff < 60) return 'Just now';
  if (diff < 3600) return Math.floor(diff/60) + 'm ago';
  if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
  if (diff < 172800) return 'Yesterday';
  return d.toLocaleDateString('en-US', {month:'short', day:'numeric'});
}

function notifItemClick(id, link) {
  fetch('../api/notifications.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'mark_read', id:id})
  }).catch(function(){});
  closeNotifDropdown();
  updateSidebarNotifCounts();
}

function markAllNotifRead() {
  fetch('../api/notifications.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'mark_read_all'})
  }).then(function(r){return r.json();}).then(function(d){
    if(d.success){
      loadNotifs();
      updateSidebarNotifCounts();
    }
  }).catch(function(){});
}

function updateNotifBellDot(count) {
  var dot = document.getElementById('notifBellDot');
  if (!dot) return;
  dot.style.display = count > 0 ? 'block' : 'none';
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
  if (!e.target.closest('.header-notif-wrapper') && !e.target.closest('.notif-dropdown')) {
    closeNotifDropdown();
  }
  if (!e.target.closest('.header-profile-dropdown-wrapper')) {
    closeProfileDropdown();
  }
});

// Update sidebar badges counts + bell dot from notif-counts.php
function updateSidebarNotifCounts() {
  fetch('../api/notif-counts.php').then(function(r){return r.json();}).then(function(d){
    var sb = function(id, c) {
      var b = document.getElementById(id);
      if (!b) return;
      b.textContent = c > 0 ? c : '';
    };
    sb('sidebar-msg-badge', d.chat);
    sb('sidebar-orders-badge', (d.order||0) + (d.order_proposal||0));
    sb('sidebar-requests-badge', d.custom_request);
    var chatUnread = d.chat || 0;
    var otherUnread = (d.order||0) + (d.custom_request||0) + (d.order_proposal||0);
    updateNotifBellDot(chatUnread + otherUnread);
  }).catch(function(){});
}

function escapeHtml(t) { if(!t)return''; var d=document.createElement('div'); d.textContent=t; return d.innerHTML; }

function showToast(message, type) {
  var existing = document.querySelector('.admin-toast');
  if (existing) existing.remove();
  var t = document.createElement('div');
  t.className = 'admin-toast';
  t.style.cssText = 'position:fixed;bottom:24px;right:24px;padding:12px 20px;border-radius:12px;font-size:0.88rem;font-weight:600;color:#fff;z-index:9999;transition:opacity 0.3s;box-shadow:0 8px 24px rgba(0,0,0,0.15);';
  t.style.background = type === 'error' ? '#ef4444' : '#10b981';
  t.textContent = message;
  document.body.appendChild(t);
  setTimeout(function(){ t.style.opacity = '0'; setTimeout(function(){ t.remove(); }, 300); }, 3000);
}

// Auto-mark notifications as read when visiting their page
(function(){
  var page = window.location.pathname;
  var types = [];
  if (page.includes('orders.php')) types = ['order','order_proposal'];
  else if (page.includes('custom-requests.php')) types = ['custom_request'];
  else if (page.includes('messages.php')) types = ['chat'];
  if (types.length) {
    Promise.all(types.map(function(t){
      return fetch('../api/notifications.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'mark_read_by_type', related_type:t})
      }).catch(function(){});
    })).then(function(){ updateSidebarNotifCounts(); });
  }
})();

// Override the old updateSidebarBadges with the enhanced version
updateSidebarNotifCounts();
setInterval(updateSidebarNotifCounts, 10000);
</script>
</body>
</html>

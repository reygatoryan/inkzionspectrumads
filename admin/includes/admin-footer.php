    </div>
  </main>
</div>
<div id="toast" class="toast toast-info hidden"></div>
<script>
function toggleSidebar() {
  var s = document.getElementById('sidebar');
  var o = document.getElementById('sidebarOverlay');
  if (s) s.classList.toggle('open');
  if (o) o.classList.toggle('active');
}
function toggleProfileDropdown() {
  var d = document.getElementById('profileDropdown');
  if (d) d.classList.toggle('active');
}
window.onclick = function(e) {
  if (!e.target.matches('.header-profile-btn') && !e.target.closest('.header-profile-dropdown-wrapper')) {
    var d = document.getElementById('profileDropdown');
    if (d) d.classList.remove('active');
  }
};
function showToast(msg, type) {
  type = type || 'info';
  var t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.className = 'toast toast-' + type;
  t.classList.remove('hidden');
  clearTimeout(t._timer);
  t._timer = setTimeout(function(){ t.classList.add('hidden'); }, 3000);
}
</script>
</body>
</html>

<?php
// frontend/users.php — Admin only
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit; }
if ($_SESSION['role'] !== 'admin') { header('Location: dashboard.php'); exit; }
require_once __DIR__ . '/../backend/database.php';

$username    = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
$role        = htmlspecialchars($_SESSION['role'],     ENT_QUOTES, 'UTF-8');
$currentPage = 'users';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Users — Inventory System</title>
    <link rel="stylesheet" href="sidebar.css"/>
    <style>
        .role-pill { display:inline-block;padding:3px 9px;border-radius:20px;font-size:.72rem;font-weight:600; }
        .role-pill.admin { background:rgba(99,102,241,.15);color:#a5b4fc; }
        .role-pill.staff { background:rgba(100,116,139,.15);color:#94a3b8; }
        .pw-wrap { position:relative; }
        .pw-wrap input { padding-right:40px; }
        .pw-toggle { position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:15px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main">
    <div class="topbar">
        <h2>👥 Users</h2>
        <div class="topbar-right"><span class="badge-role"><?= $role ?></span></div>
    </div>
    <div class="content">
        <div class="alert" id="pageAlert"></div>
        <div class="toolbar">
            <div class="search-wrap">
                <span class="s-icon">🔍</span>
                <input type="text" id="searchInput" placeholder="Search users…" oninput="filterTable()"/>
            </div>
            <button class="btn btn-primary" onclick="openModal()">＋ Add User</button>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>Username</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody id="userTable">
                    <tr class="empty-row"><td colspan="6">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Add User</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="alert" id="modalAlert"></div>
        <form id="userForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id"     id="formId"     value="">
            <div class="form-row">
                <div class="form-group">
                    <label class="fl">Username</label>
                    <input type="text" name="username" id="funame" class="form-control" placeholder="e.g. jdelacruz" maxlength="100" required/>
                </div>
                <div class="form-group">
                    <label class="fl">Role</label>
                    <select name="role" id="frole" class="form-control" required>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="fl">Email</label>
                <input type="email" name="email" id="femail" class="form-control" placeholder="user@example.com" maxlength="150" required/>
            </div>
            <div class="form-group" id="pwGroup">
                <label class="fl" id="pwLabel">Password</label>
                <div class="pw-wrap">
                    <input type="password" name="password" id="fpassword" class="form-control" placeholder="Min. 8 characters" maxlength="255"/>
                    <button type="button" class="pw-toggle" onclick="togglePw()">👁️</button>
                </div>
                <small style="color:var(--muted);font-size:.72rem;margin-top:4px;display:block" id="pwHint"></small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Save User</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF      = <?= json_encode($csrf) ?>;
const CURRENT_ID = <?= json_encode($_SESSION['user_id']) ?>;

async function loadUsers() {
    try {
        const data = await (await fetch('../backend/userAuth.php?action=list')).json();
        const tbody = document.getElementById('userTable');
        if (!data.success || !data.users.length) {
            tbody.innerHTML='<tr class="empty-row"><td colspan="6">No users found.</td></tr>'; return;
        }
        tbody.innerHTML = data.users.map((u,i) => `<tr>
            <td>${i+1}</td>
            <td><strong>${esc(u.username)}</strong>${parseInt(u.id)===parseInt(CURRENT_ID)?'<span style="margin-left:6px;font-size:.7rem;color:var(--indigo)">(you)</span>':''}</td>
            <td style="color:var(--muted)">${esc(u.email)}</td>
            <td><span class="role-pill ${u.role}">${u.role}</span></td>
            <td style="color:var(--muted);font-size:.8rem">${formatDate(u.created_at)}</td>
            <td><div class="actions">
                <button class="btn btn-sm btn-edit"   onclick='editUser(${JSON.stringify(u)})'>✏️ Edit</button>
                ${parseInt(u.id)!==parseInt(CURRENT_ID)?`<button class="btn btn-sm btn-delete" onclick="deleteUser(${u.id},'${esc(u.username)}')">🗑️ Delete</button>`:''}
            </div></td>
        </tr>`).join('');
    } catch(e) { console.error(e); }
}

function esc(s) { const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }
function formatDate(d) { return new Date(d).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'}); }
function filterTable() {
    const q=document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#userTable tr').forEach(r=>r.style.display=r.textContent.toLowerCase().includes(q)?'':'none');
}
function togglePw() {
    const pw=document.getElementById('fpassword');
    pw.type=pw.type==='password'?'text':'password';
}
function openModal(title='Add User') {
    document.getElementById('modalTitle').textContent=title;
    document.getElementById('modalOverlay').classList.add('open');
    document.getElementById('modalAlert').style.display='none';
}
function closeModal() {
    document.getElementById('modalOverlay').classList.remove('open');
    document.getElementById('userForm').reset();
    document.getElementById('formAction').value='create';
    document.getElementById('formId').value='';
    document.getElementById('pwLabel').textContent='Password';
    document.getElementById('pwHint').textContent='';
    document.getElementById('fpassword').required=true;
    document.getElementById('submitBtn').textContent='Save User';
}
function editUser(u) {
    document.getElementById('formAction').value='update';
    document.getElementById('formId').value=u.id;
    document.getElementById('funame').value=u.username;
    document.getElementById('femail').value=u.email;
    document.getElementById('frole').value=u.role;
    document.getElementById('fpassword').required=false;
    document.getElementById('pwLabel').textContent='New Password';
    document.getElementById('pwHint').textContent='Leave blank to keep current password.';
    document.getElementById('submitBtn').textContent='Update User';
    openModal('Edit User');
}
async function deleteUser(id, name) {
    if (!confirm(`Delete user "${name}"? This cannot be undone.`)) return;
    const fd=new FormData();
    fd.append('action','delete'); fd.append('id',id); fd.append('csrf_token',CSRF);
    const data=await (await fetch('../backend/userAuth.php',{method:'POST',body:fd})).json();
    showPageAlert(data.message, data.success?'success':'error');
    if (data.success) loadUsers();
}
document.getElementById('userForm').addEventListener('submit', async e => {
    e.preventDefault();
    const btn=document.getElementById('submitBtn');
    btn.disabled=true; btn.textContent='Saving…';
    const data=await (await fetch('../backend/userAuth.php',{method:'POST',body:new FormData(e.target)})).json();
    if (data.success) { closeModal(); showPageAlert(data.message,'success'); loadUsers(); }
    else { const el=document.getElementById('modalAlert'); el.textContent=data.message; el.className='alert error'; el.style.display='block'; }
    btn.disabled=false;
    btn.textContent=document.getElementById('formAction').value==='update'?'Update User':'Save User';
});
function showPageAlert(msg,type='error') {
    const el=document.getElementById('pageAlert');
    el.textContent=msg; el.className=`alert ${type}`; el.style.display='block';
    setTimeout(()=>el.style.display='none',4000);
}
function toggleSettings() {
    document.getElementById('settingsToggle').classList.toggle('open');
    document.getElementById('settingsMenu').classList.toggle('open');
}
function logout() { if(confirm('Logout?')) window.location.href='../backend/logout.php'; }
document.getElementById('modalOverlay').addEventListener('click',e=>{if(e.target===e.currentTarget)closeModal();});
loadUsers();
</script>
</body>
</html>
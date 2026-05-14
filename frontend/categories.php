<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit; }
require_once __DIR__ . '/../backend/database.php';

$username    = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
$role        = htmlspecialchars($_SESSION['role'],     ENT_QUOTES, 'UTF-8');
$currentPage = 'categories';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Categories — Inventory System</title>
    <link rel="stylesheet" href="sidebar.css"/>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main">
    <div class="topbar">
        <h2>🏷️ Categories</h2>
        <div class="topbar-right"><span class="badge-role"><?= $role ?></span></div>
    </div>
    <div class="content">
        <div class="alert" id="pageAlert"></div>
        <div class="toolbar">
            <div class="search-wrap">
                <span class="s-icon">🔍</span>
                <input type="text" id="searchInput" placeholder="Search categories…" oninput="filterTable()"/>
            </div>
            <button class="btn btn-primary" onclick="openModal()">＋ Add Category</button>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Products</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="catTable">
                    <tr class="empty-row"><td colspan="6">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>


<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Add Category</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="alert" id="modalAlert"></div>
        <form id="catForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id"     id="formId"     value="">
            <div class="form-group">
                <label class="fl">Category Name</label>
                <input type="text" name="name" id="fname" class="form-control" placeholder="e.g. Electronics" maxlength="100" required/>
            </div>
            <div class="form-group">
                <label class="fl">Description</label>
                <textarea name="description" id="fdesc" class="form-control" rows="3" placeholder="Optional description…" maxlength="500"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Save Category</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF = <?= json_encode($csrf) ?>;

async function loadCategories() {
    try {
        const data = await (await fetch('../backend/categoryAuth.php?action=list')).json();
        const tbody = document.getElementById('catTable');
        if (!data.success || !data.categories.length) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="6">No categories yet. Add one!</td></tr>'; return;
        }
        tbody.innerHTML = data.categories.map((c, i) => `<tr>
            <td>${i+1}</td>
            <td><strong>${esc(c.name)}</strong></td>
            <td style="color:var(--muted)">${esc(c.description) || '—'}</td>
            <td><span class="pill in-stock">${c.product_count} item(s)</span></td>
            <td style="color:var(--muted);font-size:.8rem">${formatDate(c.created_at)}</td>
            <td><div class="actions">
                <button class="btn btn-sm btn-edit"   onclick='editCat(${JSON.stringify(c)})'>✏️ Edit</button>
                <button class="btn btn-sm btn-delete" onclick="deleteCat(${c.id},'${esc(c.name)}',${c.product_count})">🗑️ Delete</button>
            </div></td>
        </tr>`).join('');
    } catch(e) { console.error(e); }
}

function esc(s) { const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }
function formatDate(d) { return new Date(d).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'}); }
function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#catTable tr').forEach(r => r.style.display = r.textContent.toLowerCase().includes(q)?'':'none');
}
function openModal(title='Add Category') {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalOverlay').classList.add('open');
    document.getElementById('modalAlert').style.display = 'none';
}
function closeModal() {
    document.getElementById('modalOverlay').classList.remove('open');
    document.getElementById('catForm').reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('formId').value = '';
    document.getElementById('submitBtn').textContent = 'Save Category';
}
function editCat(c) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('formId').value     = c.id;
    document.getElementById('fname').value      = c.name;
    document.getElementById('fdesc').value      = c.description ?? '';
    document.getElementById('submitBtn').textContent = 'Update Category';
    openModal('Edit Category');
}
async function deleteCat(id, name, count) {
    if (count > 0) { showPageAlert(`Cannot delete "${name}" — it has ${count} product(s) assigned.`, 'error'); return; }
    if (!confirm(`Delete "${name}"?`)) return;
    const fd = new FormData();
    fd.append('action','delete'); fd.append('id',id); fd.append('csrf_token',CSRF);
    const data = await (await fetch('../backend/categoryAuth.php',{method:'POST',body:fd})).json();
    showPageAlert(data.message, data.success?'success':'error');
    if (data.success) loadCategories();
}
document.getElementById('catForm').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true; btn.textContent = 'Saving…';
    const data = await (await fetch('../backend/categoryAuth.php',{method:'POST',body:new FormData(e.target)})).json();
    if (data.success) { closeModal(); showPageAlert(data.message,'success'); loadCategories(); }
    else { const el=document.getElementById('modalAlert'); el.textContent=data.message; el.className='alert error'; el.style.display='block'; }
    btn.disabled = false;
    btn.textContent = document.getElementById('formAction').value==='update'?'Update Category':'Save Category';
});
function showPageAlert(msg, type='error') {
    const el = document.getElementById('pageAlert');
    el.textContent=msg; el.className=`alert ${type}`; el.style.display='block';
    setTimeout(()=>el.style.display='none',4000);
}
function toggleSettings() {
    document.getElementById('settingsToggle').classList.toggle('open');
    document.getElementById('settingsMenu').classList.toggle('open');
}
function logout() { if(confirm('Logout?')) window.location.href='../backend/logout.php'; }
document.getElementById('modalOverlay').addEventListener('click',e=>{if(e.target===e.currentTarget)closeModal();});
loadCategories();
</script>
</body>
</html>
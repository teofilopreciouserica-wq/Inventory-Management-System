<?php
// frontend/products.php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit; }
require_once __DIR__ . '/../backend/database.php';

$categories  = $conn->query('SELECT id, name FROM categories ORDER BY name');
$username    = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
$role        = htmlspecialchars($_SESSION['role'],     ENT_QUOTES, 'UTF-8');
$currentPage = 'products';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Products — Inventory System</title>
    <link rel="stylesheet" href="sidebar.css"/>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
    <div class="topbar">
        <h2>📋 Products</h2>
        <div class="topbar-right"><span class="badge-role"><?= $role ?></span></div>
    </div>
    <div class="content">
        <div class="alert" id="pageAlert"></div>
        <div class="toolbar">
            <div class="search-wrap">
                <span class="s-icon">🔍</span>
                <input type="text" id="searchInput" placeholder="Search products…" oninput="filterTable()"/>
            </div>
            <button class="btn btn-primary" onclick="openModal()">＋ Add Product</button>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>Product Name</th><th>SKU</th><th>Category</th>
                        <th>Unit</th><th>Qty</th><th>Reorder Lvl</th><th>Unit Price</th>
                        <th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody id="productTable">
                    <tr class="empty-row"><td colspan="10">Loading products…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Add Product</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="alert" id="modalAlert"></div>
        <form id="productForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id"     id="formId"     value="">
            <div class="form-group">
                <label class="fl">Product Name</label>
                <input type="text" name="name" id="fname" class="form-control" placeholder="e.g. Ballpen Black" maxlength="150" required/>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="fl">SKU</label>
                    <input type="text" name="sku" id="fsku" class="form-control" placeholder="e.g. BP-001" maxlength="80" required/>
                </div>
                <div class="form-group">
                    <label class="fl">Unit</label>
                    <input type="text" name="unit" id="funit" class="form-control" placeholder="pcs / box / kg" maxlength="50"/>
                </div>
            </div>
            <div class="form-group">
                <label class="fl">Category</label>
                <select name="category_id" id="fcategory" class="form-control" required>
                    <option value="">— Select Category —</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="fl">Reorder Level</label>
                    <input type="number" name="reorder_level" id="freorder" class="form-control" value="10" min="0" required/>
                </div>
                <div class="form-group">
                    <label class="fl">Unit Price (₱)</label>
                    <input type="number" name="unit_price" id="fprice" class="form-control" value="0.00" min="0" step="0.01" required/>
                </div>
            </div>
            <div class="form-group">
                <label class="fl">Description</label>
                <textarea name="description" id="fdesc" class="form-control" rows="2" placeholder="Optional…" maxlength="500"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF = <?= json_encode($csrf) ?>;

async function loadProducts() {
    try {
        const res  = await fetch('../backend/productAuth.php?action=list');
        const data = await res.json();
        const tbody = document.getElementById('productTable');
        if (!data.success || !data.products.length) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="10">No products yet. Add one!</td></tr>';
            return;
        }
        tbody.innerHTML = data.products.map((p, i) => {
            let sc, sl;
            if      (p.quantity == 0)                 { sc='out';      sl='Out of Stock'; }
            else if (p.quantity <= p.reorder_level)   { sc='low';      sl='Low Stock';    }
            else                                      { sc='in-stock'; sl='In Stock';     }
            return `<tr>
                <td>${i+1}</td>
                <td><strong>${esc(p.name)}</strong>${p.description?`<br><small style="color:var(--muted)">${esc(p.description)}</small>`:''}</td>
                <td style="color:var(--muted)">${esc(p.sku)}</td>
                <td>${esc(p.category)}</td>
                <td>${esc(p.unit)}</td>
                <td><strong>${p.quantity}</strong></td>
                <td>${p.reorder_level}</td>
                <td>₱${parseFloat(p.unit_price).toFixed(2)}</td>
                <td><span class="pill ${sc}">${sl}</span></td>
                <td><div class="actions">
                    <button class="btn btn-sm btn-edit"   onclick='editProduct(${JSON.stringify(p)})'>✏️ Edit</button>
                    <button class="btn btn-sm btn-delete" onclick="deleteProduct(${p.id},'${esc(p.name)}')">🗑️ Delete</button>
                </div></td>
            </tr>`;
        }).join('');
    } catch(e) { console.error(e); }
}

function esc(s) { const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }
function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#productTable tr').forEach(r => r.style.display = r.textContent.toLowerCase().includes(q)?'':'none');
}
function openModal(title='Add Product') {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalOverlay').classList.add('open');
    document.getElementById('modalAlert').style.display = 'none';
}
function closeModal() {
    document.getElementById('modalOverlay').classList.remove('open');
    document.getElementById('productForm').reset();
    document.getElementById('formAction').value = 'create';
    document.getElementById('formId').value = '';
    document.getElementById('submitBtn').textContent = 'Save Product';
}
function editProduct(p) {
    document.getElementById('formAction').value = 'update';
    document.getElementById('formId').value     = p.id;
    document.getElementById('fname').value      = p.name;
    document.getElementById('fsku').value       = p.sku;
    document.getElementById('funit').value      = p.unit;
    document.getElementById('fcategory').value  = p.category_id;
    document.getElementById('freorder').value   = p.reorder_level;
    document.getElementById('fprice').value     = p.unit_price;
    document.getElementById('fdesc').value      = p.description ?? '';
    document.getElementById('submitBtn').textContent = 'Update Product';
    openModal('Edit Product');
}
async function deleteProduct(id, name) {
    if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;
    const fd = new FormData();
    fd.append('action','delete'); fd.append('id',id); fd.append('csrf_token',CSRF);
    const data = await (await fetch('../backend/productAuth.php',{method:'POST',body:fd})).json();
    showPageAlert(data.message, data.success?'success':'error');
    if (data.success) loadProducts();
}
document.getElementById('productForm').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true; btn.textContent = 'Saving…';
    const data = await (await fetch('../backend/productAuth.php',{method:'POST',body:new FormData(e.target)})).json();
    if (data.success) { closeModal(); showPageAlert(data.message,'success'); loadProducts(); }
    else showModalAlert(data.message);
    btn.disabled = false;
    btn.textContent = document.getElementById('formAction').value==='update'?'Update Product':'Save Product';
});
function showPageAlert(msg, type='error') {
    const el = document.getElementById('pageAlert');
    el.textContent = msg; el.className = `alert ${type}`; el.style.display = 'block';
    setTimeout(() => el.style.display='none', 4000);
}
function showModalAlert(msg) {
    const el = document.getElementById('modalAlert');
    el.textContent = msg; el.className = 'alert error'; el.style.display = 'block';
}
function toggleSettings() {
    document.getElementById('settingsToggle').classList.toggle('open');
    document.getElementById('settingsMenu').classList.toggle('open');
}
function logout() {
    if (confirm('Are you sure you want to logout?')) window.location.href = '../backend/logout.php';
}
document.getElementById('modalOverlay').addEventListener('click', e => { if(e.target===e.currentTarget) closeModal(); });
loadProducts();
</script>
</body>
</html>
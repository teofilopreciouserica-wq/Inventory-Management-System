<?php 
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit; }
require_once __DIR__ . '/../backend/database.php';

$products    = $conn->query('SELECT id, name, sku, quantity FROM products ORDER BY name');
$username    = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
$role        = htmlspecialchars($_SESSION['role'],     ENT_QUOTES, 'UTF-8');
$currentPage = 'stock_in';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Stock In — Inventory System</title>
    <link rel="stylesheet" href="sidebar.css"/>
    <style>
        .stock-layout { display: grid; grid-template-columns: 420px 1fr; gap: 20px; align-items: start; }
        @media (max-width: 960px) { .stock-layout { grid-template-columns: 1fr; } }

        .form-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; overflow: hidden;
        }
        .form-card-header {
            padding: 16px 20px; border-bottom: 1px solid var(--border);
            background: var(--surface2);
        }
        .form-card-header h3 { font-size: .95rem; font-weight: 600; }
        .form-card-body { padding: 20px; }
 
        .product-select-wrap { position: relative; }
        .product-select-wrap select { padding-right: 36px; }
 
        .stock-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 8px; font-size: .8rem; font-weight: 600;
            margin-top: 8px;
        }
        .stock-badge.ok  { background: rgba(34,197,94,.12);  color: #86efac; }
        .stock-badge.low { background: rgba(245,158,11,.12); color: #fcd34d; }
        .stock-badge.out { background: rgba(239,68,68,.12);  color: #fca5a5; }

        .divider { height: 1px; background: var(--border); margin: 16px 0; }

        .submit-btn {
            width: 100%; padding: 13px;
            background: linear-gradient(135deg, var(--indigo), var(--cyan));
            border: none; border-radius: 10px; color: #fff;
            font-size: 1rem; font-weight: 600; cursor: pointer;
            transition: opacity .2s, transform .15s; margin-top: 4px;
            position: relative;
        }
        .submit-btn:hover:not(:disabled) { opacity: .9; transform: translateY(-1px); }
        .submit-btn:disabled { opacity: .6; cursor: not-allowed; }
        .spinner {
            display: none; width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,.3); border-top-color: #fff;
            border-radius: 50%; animation: spin .7s linear infinite;
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
        }
        @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }
    </style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
    <div class="topbar">
        <h2>📥 Stock In</h2>
        <div class="topbar-right"><span class="badge-role"><?= $role ?></span></div>
    </div>
    <div class="content">
        <div class="alert" id="pageAlert"></div>

        <div class="stock-layout">

            <div class="form-card">
                <div class="form-card-header">
                    <h3>📥 New Stock In Entry</h3>
                </div>
                <div class="form-card-body">
                    <form id="stockInForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="form-group">
                            <label class="fl">Select Product</label>
                            <select name="product_id" id="productSelect" class="form-control" required onchange="updateStockBadge()">
                                <option value="">— Choose a product —</option>
                                <?php while ($p = $products->fetch_assoc()): ?>
                                <option value="<?= $p['id'] ?>"
                                        data-qty="<?= $p['quantity'] ?>"
                                        data-sku="<?= htmlspecialchars($p['sku'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($p['sku'], ENT_QUOTES, 'UTF-8') ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <div id="stockBadge" style="display:none" class="stock-badge ok">
                                📦 Current Stock: <strong id="currentQty">0</strong>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="fl">Quantity to Add</label>
                            <input type="number" name="quantity" id="qty" class="form-control"
                                   placeholder="e.g. 50" min="1" required/>
                        </div>

                        <div class="form-group">
                            <label class="fl">Unit Cost (₱)</label>
                            <input type="number" name="unit_cost" class="form-control"
                                   placeholder="0.00" min="0" step="0.01" value="0.00"/>
                        </div>

                        <div class="form-group">
                            <label class="fl">Supplier</label>
                            <input type="text" name="supplier" class="form-control"
                                   placeholder="Supplier name (optional)" maxlength="150"/>
                        </div>

                        <div class="form-group">
                            <label class="fl">Reference No. <span style="color:var(--muted);font-weight:400;text-transform:none;font-size:.72rem">(auto-generated, editable)</span></label>
                            <div style="position:relative">
                                <input type="text" name="reference_no" id="refInput" class="form-control"
                                       placeholder="Loading…" maxlength="100"/>
                                <button type="button" onclick="refreshRef()" title="Generate new"
                                    style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:15px;">🔄</button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="fl">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"
                                      placeholder="Optional notes…" maxlength="500"></textarea>
                        </div>

                        <div class="divider"></div>

                        <button type="submit" class="submit-btn" id="submitBtn">
                            ＋ Add Stock In
                            <span class="spinner" id="spinner"></span>
                        </button>
                    </form>
                </div>
            </div>

            <div>
                <div class="toolbar" style="margin-bottom:16px">
                    <div class="search-wrap">
                        <span class="s-icon">🔍</span>
                        <input type="text" id="searchInput" placeholder="Search records…" oninput="filterTable()"/>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Qty Added</th>
                                <th>Unit Cost</th>
                                <th>Supplier</th>
                                <th>Reference</th>
                                <th>By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="stockInTable">
                            <tr class="empty-row"><td colspan="9">Loading records…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode($csrf) ?>;

async function fetchRef() {
    try {
        const data = await (await fetch('../backend/stockInAuth.php?action=generate_ref')).json();
        if (data.success) document.getElementById('refInput').value = data.reference_no;
    } catch(e) { console.error(e); }
}
function refreshRef() { fetchRef(); }

function updateStockBadge() {
    const sel    = document.getElementById('productSelect');
    const opt    = sel.options[sel.selectedIndex];
    const badge  = document.getElementById('stockBadge');
    const qtyEl  = document.getElementById('currentQty');

    if (!sel.value) { badge.style.display = 'none'; return; }

    const qty = parseInt(opt.dataset.qty);
    qtyEl.textContent = qty;
    badge.style.display = 'inline-flex';
    badge.className = 'stock-badge ' + (qty === 0 ? 'out' : qty <= 10 ? 'low' : 'ok');
}

async function loadRecords() {
    try {
        const res  = await fetch('../backend/stockInAuth.php?action=list');
        const data = await res.json();
        const tbody = document.getElementById('stockInTable');

        if (!data.success || !data.records.length) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="9">No stock-in records yet.</td></tr>';
            return;
        }

        tbody.innerHTML = data.records.map((r, i) => `
            <tr>
                <td>${i+1}</td>
                <td><strong>${esc(r.product)}</strong></td>
                <td style="color:var(--muted)">${esc(r.sku)}</td>
                <td><span style="color:var(--green);font-weight:700">+${r.quantity}</span></td>
                <td>₱${parseFloat(r.unit_cost).toFixed(2)}</td>
                <td>${esc(r.supplier) || '<span style="color:var(--muted)">—</span>'}</td>
                <td>${esc(r.reference_no) || '<span style="color:var(--muted)">—</span>'}</td>
                <td>${esc(r.username)}</td>
                <td style="color:var(--muted);font-size:.8rem">${formatDate(r.transaction_at)}</td>
            </tr>`).join('');
    } catch(e) { console.error(e); }
}

function esc(s) { const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }
function formatDate(d) {
    return new Date(d).toLocaleString('en-PH',{month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'});
}
function filterTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('#stockInTable tr').forEach(r => r.style.display = r.textContent.toLowerCase().includes(q)?'':'none');
}

document.getElementById('stockInForm').addEventListener('submit', async e => {
    e.preventDefault();
    const btn     = document.getElementById('submitBtn');
    const spinner = document.getElementById('spinner');

    btn.disabled = true; spinner.style.display = 'block';

    try {
        const data = await (await fetch('../backend/stockInAuth.php', {method:'POST', body: new FormData(e.target)})).json();
        if (data.success) {
            showAlert(data.message, 'success');
            e.target.reset();
            document.getElementById('stockBadge').style.display = 'none';
            loadRecords();
fetchRef();
            fetchRef(); // load next ref number
        } else {
            showAlert(data.message, 'error');
        }
    } catch(err) {
        showAlert('Network error. Please try again.', 'error');
    }

    btn.disabled = false; spinner.style.display = 'none';
});

function showAlert(msg, type='error') {
    const el = document.getElementById('pageAlert');
    el.textContent = msg; el.className = `alert ${type}`; el.style.display = 'block';
    setTimeout(() => el.style.display='none', 5000);
}
function toggleSettings() {
    document.getElementById('settingsToggle').classList.toggle('open');
    document.getElementById('settingsMenu').classList.toggle('open');
}
function logout() {
    if (confirm('Are you sure you want to logout?')) window.location.href = '../backend/logout.php';
}

loadRecords();
</script>
</body>
</html>
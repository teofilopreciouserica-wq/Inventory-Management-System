<?php 
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit; }
require_once __DIR__ . '/../backend/database.php';

$username    = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
$role        = htmlspecialchars($_SESSION['role'],     ENT_QUOTES, 'UTF-8');
$currentPage = 'transactions';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
 
$products = $conn->query('SELECT id, name FROM products ORDER BY name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Transaction History — Inventory System</title>
    <link rel="stylesheet" href="sidebar.css"/>
    <style> 
        .filter-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
            margin-bottom: 20px;
        }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label {
            font-size: .7rem; font-weight: 600; color: var(--muted);
            text-transform: uppercase; letter-spacing: .05em;
        }
        .filter-group select,
        .filter-group input[type="date"] {
            padding: 8px 12px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px; color: var(--text);
            font-size: .875rem; outline: none;
            transition: border-color .2s;
            min-width: 150px;
        }
        .filter-group select:focus,
        .filter-group input[type="date"]:focus { border-color: var(--indigo); }
        .filter-group input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); }

        .filter-actions { display: flex; gap: 8px; align-items: flex-end; margin-left: auto; }
 
        .summary-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 18px;
            display: flex; flex-direction: column; gap: 4px;
        }
        .summary-card .s-label { font-size: .72rem; color: var(--muted); font-weight: 500; }
        .summary-card .s-value { font-size: 1.5rem; font-weight: 700; }
        .summary-card .s-sub   { font-size: .72rem; color: var(--muted); }
 
        .type-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 9px; border-radius: 6px;
            font-size: .72rem; font-weight: 600; white-space: nowrap;
        }
        .type-badge.in  { background: rgba(34,197,94,.15);  color: #86efac; }
        .type-badge.out { background: rgba(239,68,68,.15);  color: #fca5a5; }

        .reason-pill {
            display: inline-block; padding: 2px 8px;
            border-radius: 20px; font-size: .7rem; font-weight: 600;
        }
        .reason-pill.sold     { background: rgba(99,102,241,.15); color: #a5b4fc; }
        .reason-pill.damaged  { background: rgba(239,68,68,.15);  color: #fca5a5; }
        .reason-pill.returned { background: rgba(34,197,94,.15);  color: #86efac; }
        .reason-pill.transfer { background: rgba(6,182,212,.15);  color: #67e8f9; }
        .reason-pill.other    { background: rgba(100,116,139,.15);color: #94a3b8; }
        .reason-pill.stockin  { background: rgba(34,197,94,.1);   color: #86efac; }
 
        .pagination {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 18px;
            border-top: 1px solid var(--border);
            font-size: .82rem; color: var(--muted);
        }
        .page-btns { display: flex; gap: 6px; }
        .page-btn {
            padding: 5px 12px; border-radius: 7px; font-size: .8rem;
            background: var(--surface2); border: 1px solid var(--border);
            color: var(--text); cursor: pointer; transition: all .15s;
        }
        .page-btn:hover:not(:disabled) { border-color: var(--indigo); color: var(--indigo); }
        .page-btn:disabled { opacity: .4; cursor: not-allowed; }
        .page-btn.active   { background: var(--indigo); border-color: var(--indigo); color: #fff; }
 
        .btn-export {
            padding: 8px 16px; border-radius: 9px; font-size: .82rem; font-weight: 600;
            background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.2);
            color: #86efac; cursor: pointer; transition: background .2s;
        }
        .btn-export:hover { background: rgba(34,197,94,.18); }

        .loading-row td { text-align: center; padding: 40px; color: var(--muted); }
    </style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
    <div class="topbar">
        <h2>🗂️ Transaction History</h2>
        <div class="topbar-right">
            <button class="btn-export" onclick="exportCSV()">⬇️ Export CSV</button>
            <span class="badge-role"><?= $role ?></span>
        </div>
    </div>

    <div class="content"> 
        <div class="filter-bar">
            <div class="filter-group">
                <label>Type</label>
                <select id="fType">
                    <option value="all">All Types</option>
                    <option value="in">Stock In</option>
                    <option value="out">Stock Out</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Product</label>
                <select id="fProduct">
                    <option value="">All Products</option>
                    <?php while ($p = $products->fetch_assoc()): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" id="fFrom" value="<?= date('Y-m-01') ?>"/>
            </div>
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" id="fTo" value="<?= date('Y-m-d') ?>"/>
            </div>
            <div class="filter-group">
                <label>Search</label>
                <input type="date" id="fSearch" style="display:none"/>
                <input type="text" id="fSearch" class="form-control"
                       placeholder="Product, ref, user…"
                       style="padding:8px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.875rem;outline:none;min-width:180px;"
                       oninput="applySearch()"/>
            </div>
            <div class="filter-actions">
                <button class="btn btn-primary" onclick="loadRecords()">🔍 Filter</button>
                <button class="page-btn" onclick="resetFilters()">✕ Reset</button>
            </div>
        </div>
 
        <div class="summary-row">
            <div class="summary-card">
                <div class="s-label">Total Transactions</div>
                <div class="s-value" id="sTotal">—</div>
                <div class="s-sub">in current view</div>
            </div>
            <div class="summary-card">
                <div class="s-label">Total Stock In</div>
                <div class="s-value" style="color:var(--green)" id="sTotalIn">—</div>
                <div class="s-sub">units received</div>
            </div>
            <div class="summary-card">
                <div class="s-label">Total Stock Out</div>
                <div class="s-value" style="color:var(--red)" id="sTotalOut">—</div>
                <div class="s-sub">units released</div>
            </div>
            <div class="summary-card">
                <div class="s-label">Net Movement</div>
                <div class="s-value" id="sNet">—</div>
                <div class="s-sub">units (in minus out)</div>
            </div>
        </div>
 
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Qty</th>
                        <th>Reason / Supplier</th>
                        <th>Reference No.</th>
                        <th>Handled By</th>
                        <th>Notes</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody id="txnTable">
                    <tr class="loading-row"><td colspan="10">Loading transactions…</td></tr>
                </tbody>
            </table>
            <div class="pagination">
                <span id="pageInfo">—</span>
                <div class="page-btns" id="pageBtns"></div>
            </div>
        </div>

    </div>
</div>

<script>
let allRecords  = [];
let filtered    = [];
const PAGE_SIZE = 15;
let currentPage = 1;
 
async function loadRecords() {
    document.getElementById('txnTable').innerHTML =
        '<tr class="loading-row"><td colspan="10">Loading…</td></tr>';

    const type      = document.getElementById('fType').value;
    const productId = document.getElementById('fProduct').value;
    const dateFrom  = document.getElementById('fFrom').value;
    const dateTo    = document.getElementById('fTo').value;

    const params = new URLSearchParams({ type, product_id: productId, date_from: dateFrom, date_to: dateTo });

    try {
        const res  = await fetch(`../backend/transactionAuth.php?${params}`);
        const data = await res.json();

        if (!data.success) { showEmpty('Failed to load records.'); return; }

        allRecords  = data.records;
        currentPage = 1;
        applySearch();
    } catch(e) {
        showEmpty('Network error. Please try again.');
    }
}
 
function applySearch() {
    const q = (document.getElementById('fSearch').value || '').toLowerCase();
    filtered = q
        ? allRecords.filter(r =>
            (r.product     || '').toLowerCase().includes(q) ||
            (r.reference_no|| '').toLowerCase().includes(q) ||
            (r.username    || '').toLowerCase().includes(q) ||
            (r.sku         || '').toLowerCase().includes(q) ||
            (r.notes       || '').toLowerCase().includes(q))
        : [...allRecords];

    currentPage = 1;
    updateSummary();
    renderPage();
}
 
function updateSummary() {
    const totalIn  = filtered.filter(r => r.type === 'Stock In') .reduce((s,r) => s + parseInt(r.quantity), 0);
    const totalOut = filtered.filter(r => r.type === 'Stock Out').reduce((s,r) => s + parseInt(r.quantity), 0);
    const net      = totalIn - totalOut;

    document.getElementById('sTotal').textContent   = filtered.length;
    document.getElementById('sTotalIn').textContent  = totalIn;
    document.getElementById('sTotalOut').textContent = totalOut;
    const netEl = document.getElementById('sNet');
    netEl.textContent = (net >= 0 ? '+' : '') + net;
    netEl.style.color = net >= 0 ? 'var(--green)' : 'var(--red)';
}
 
function renderPage() {
    const tbody  = document.getElementById('txnTable');
    const total  = filtered.length;
    const pages  = Math.ceil(total / PAGE_SIZE) || 1;
    currentPage  = Math.min(currentPage, pages);

    const start  = (currentPage - 1) * PAGE_SIZE;
    const slice  = filtered.slice(start, start + PAGE_SIZE);

    if (!slice.length) { showEmpty('No transactions found for the selected filters.'); return; }

    tbody.innerHTML = slice.map((r, i) => {
        const isIn    = r.type === 'Stock In';
        const cls     = isIn ? 'in' : 'out';
        const qtyStr  = isIn
            ? `<span style="color:var(--green);font-weight:700">+${r.quantity}</span>`
            : `<span style="color:var(--red);font-weight:700">-${r.quantity}</span>`;

        const reasonStr = isIn
            ? `<span class="reason-pill stockin">Stock In${r.supplier ? ` · ${esc(r.supplier)}` : ''}</span>`
            : `<span class="reason-pill ${esc(r.reason)}">${capitalize(r.reason)}</span>`;

        return `<tr>
            <td>${start + i + 1}</td>
            <td><span class="type-badge ${cls}">${isIn ? '📥' : '📤'} ${r.type}</span></td>
            <td><strong>${esc(r.product)}</strong></td>
            <td style="color:var(--muted);font-size:.8rem">${esc(r.sku)}</td>
            <td>${qtyStr}</td>
            <td>${reasonStr}</td>
            <td style="font-size:.8rem">${esc(r.reference_no) || '<span style="color:var(--muted)">—</span>'}</td>
            <td>${esc(r.username)}</td>
            <td style="color:var(--muted);font-size:.78rem">${esc(r.notes) || '—'}</td>
            <td style="color:var(--muted);font-size:.78rem;white-space:nowrap">${formatDate(r.transaction_at)}</td>
        </tr>`;
    }).join('');
 
    document.getElementById('pageInfo').textContent =
        `Showing ${start + 1}–${Math.min(start + PAGE_SIZE, total)} of ${total} records`;
 
    const btnContainer = document.getElementById('pageBtns');
    btnContainer.innerHTML = '';

    const prevBtn = document.createElement('button');
    prevBtn.className = 'page-btn';
    prevBtn.textContent = '← Prev';
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => { currentPage--; renderPage(); };
    btnContainer.appendChild(prevBtn);
 
    const startPage = Math.max(1, currentPage - 2);
    const endPage   = Math.min(pages, startPage + 4);
    for (let p = startPage; p <= endPage; p++) {
        const btn = document.createElement('button');
        btn.className = 'page-btn' + (p === currentPage ? ' active' : '');
        btn.textContent = p;
        btn.onclick = ((pg) => () => { currentPage = pg; renderPage(); })(p);
        btnContainer.appendChild(btn);
    }

    const nextBtn = document.createElement('button');
    nextBtn.className = 'page-btn';
    nextBtn.textContent = 'Next →';
    nextBtn.disabled = currentPage === pages;
    nextBtn.onclick = () => { currentPage++; renderPage(); };
    btnContainer.appendChild(nextBtn);
}
 
function exportCSV() {
    if (!filtered.length) { alert('No records to export.'); return; }

    const headers = ['#','Type','Product','SKU','Quantity','Reason/Supplier','Reference No.','Handled By','Notes','Date'];
    const rows = filtered.map((r, i) => [
        i + 1,
        r.type,
        `"${(r.product||'').replace(/"/g,'""')}"`,
        r.sku,
        r.quantity,
        r.type === 'Stock In' ? (r.supplier || 'Stock In') : capitalize(r.reason),
        r.reference_no || '',
        r.username,
        `"${(r.notes||'').replace(/"/g,'""')}"`,
        formatDate(r.transaction_at)
    ]);

    const csv  = [headers, ...rows].map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `transactions_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}
 
function resetFilters() {
    document.getElementById('fType').value    = 'all';
    document.getElementById('fProduct').value = '';
    document.getElementById('fFrom').value    = '<?= date('Y-m-01') ?>';
    document.getElementById('fTo').value      = '<?= date('Y-m-d') ?>';
    document.getElementById('fSearch').value  = '';
    loadRecords();
}
 
function showEmpty(msg) {
    document.getElementById('txnTable').innerHTML =
        `<tr class="loading-row"><td colspan="10">${msg}</td></tr>`;
    document.getElementById('pageInfo').textContent = '—';
    document.getElementById('pageBtns').innerHTML   = '';
    document.getElementById('sTotal').textContent   = '0';
    document.getElementById('sTotalIn').textContent  = '0';
    document.getElementById('sTotalOut').textContent = '0';
    document.getElementById('sNet').textContent      = '0';
}
function esc(s) { const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }
function capitalize(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function formatDate(d) {
    return new Date(d).toLocaleString('en-PH',{month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'});
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
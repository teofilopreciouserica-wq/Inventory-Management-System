<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit; }
require_once __DIR__ . '/../backend/database.php';

$username    = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
$role        = htmlspecialchars($_SESSION['role'],     ENT_QUOTES, 'UTF-8');
$currentPage = 'audit_log';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Audit Log — Inventory System</title>
    <link rel="stylesheet" href="sidebar.css"/>
    <style>
        .action-pill {
            display:inline-block;padding:3px 8px;border-radius:6px;
            font-size:.7rem;font-weight:600;white-space:nowrap;
        }
        .action-pill.STOCK_IN        { background:rgba(34,197,94,.15);  color:#86efac; }
        .action-pill.STOCK_OUT       { background:rgba(239,68,68,.15);  color:#fca5a5; }
        .action-pill.QUANTITY_CHANGE { background:rgba(245,158,11,.15); color:#fcd34d; }
        .action-pill.LOGIN           { background:rgba(99,102,241,.15); color:#a5b4fc; }
        .action-pill.DEFAULT         { background:rgba(100,116,139,.15);color:#94a3b8; }

        .json-cell {
            font-family: monospace; font-size: .72rem;
            color: var(--indigo); max-width: 200px;
            white-space: nowrap; overflow: hidden;
            text-overflow: ellipsis; cursor: pointer;
            text-decoration: underline dotted;
        }
        .json-cell:hover { color: var(--cyan); }

        /* JSON viewer modal */
        .json-modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.7); backdrop-filter: blur(4px);
            z-index: 300; align-items: center; justify-content: center;
        }
        .json-modal-overlay.open { display: flex; }
        .json-modal {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; padding: 24px; width: 100%; max-width: 480px;
            box-shadow: 0 25px 60px rgba(0,0,0,.6);
        }
        .json-modal-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 16px;
        }
        .json-modal-header h4 { font-size: .95rem; font-weight: 600; }
        .json-close { background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer; }
        .json-close:hover { color: var(--text); }
        .json-body {
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 9px; padding: 16px;
            font-family: monospace; font-size: .82rem;
            color: var(--cyan); white-space: pre-wrap;
            word-break: break-all; max-height: 300px; overflow-y: auto;
            line-height: 1.6;
        }

        .filter-bar {
            background:var(--surface); border:1px solid var(--border);
            border-radius:14px; padding:16px 20px;
            display:flex; flex-wrap:wrap; gap:12px;
            align-items:flex-end; margin-bottom:20px;
        }
        .filter-group { display:flex; flex-direction:column; gap:5px; }
        .filter-group label { font-size:.7rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.05em; }
        .filter-group select,
        .filter-group input[type="date"] {
            padding:8px 12px; background:var(--surface2);
            border:1px solid var(--border); border-radius:8px;
            color:var(--text); font-size:.875rem; outline:none; min-width:140px;
        }
        .filter-group input[type="date"]::-webkit-calendar-picker-indicator { filter:invert(1); }

        .pagination {
            display:flex; align-items:center; justify-content:space-between;
            padding:14px 18px; border-top:1px solid var(--border);
            font-size:.82rem; color:var(--muted);
        }
        .page-btns { display:flex; gap:6px; }
        .page-btn {
            padding:5px 12px; border-radius:7px; font-size:.8rem;
            background:var(--surface2); border:1px solid var(--border);
            color:var(--text); cursor:pointer; transition:all .15s;
        }
        .page-btn:hover:not(:disabled) { border-color:var(--indigo); color:var(--indigo); }
        .page-btn:disabled { opacity:.4; cursor:not-allowed; }
        .page-btn.active { background:var(--indigo); border-color:var(--indigo); color:#fff; }
    </style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main">
    <div class="topbar">
        <h2>📜 Audit Log</h2>
        <div class="topbar-right"><span class="badge-role"><?= $role ?></span></div>
    </div>
    <div class="content">

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <label>Action</label>
                <select id="fAction">
                    <option value="">All Actions</option>
                    <option value="STOCK_IN">Stock In</option>
                    <option value="STOCK_OUT">Stock Out</option>
                    <option value="QUANTITY_CHANGE">Qty Change</option>
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
                <input type="text" id="fSearch"
                    placeholder="User, action, table…"
                    style="padding:8px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:.875rem;outline:none;min-width:180px;"
                    oninput="applySearch()"/>
            </div>
            <div style="display:flex;gap:8px;align-items:flex-end;margin-left:auto;">
                <button class="btn btn-primary" onclick="loadLogs()">🔍 Filter</button>
                <button class="page-btn" onclick="resetFilters()">✕ Reset</button>
            </div>
        </div>

        <!-- Table -->
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Action</th>
                        <th>Table</th>
                        <th>Record ID</th>
                        <th>User</th>
                        <th>Old Value</th>
                        <th>New Value</th>
                        <th>IP Address</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody id="logTable">
                    <tr class="empty-row"><td colspan="9">Loading…</td></tr>
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
let allLogs = [], filtered = [];
const PAGE_SIZE = 20;
let currentPage = 1;

async function loadLogs() {
    document.getElementById('logTable').innerHTML='<tr class="empty-row"><td colspan="9">Loading…</td></tr>';
    const action   = document.getElementById('fAction').value;
    const dateFrom = document.getElementById('fFrom').value;
    const dateTo   = document.getElementById('fTo').value;
    const params   = new URLSearchParams({action, date_from: dateFrom, date_to: dateTo});
    try {
        const data = await (await fetch(`../backend/auditAuth.php?${params}`)).json();
        if (!data.success) { showEmpty('Failed to load logs.'); return; }
        allLogs = data.logs; currentPage = 1; applySearch();
    } catch(e) { showEmpty('Network error.'); }
}

function applySearch() {
    const q = (document.getElementById('fSearch').value||'').toLowerCase();
    filtered = q
        ? allLogs.filter(r =>
            (r.action||'').toLowerCase().includes(q) ||
            (r.table_name||'').toLowerCase().includes(q) ||
            (r.username||'').toLowerCase().includes(q) ||
            (r.ip_address||'').toLowerCase().includes(q))
        : [...allLogs];
    currentPage = 1; renderPage();
}

function renderPage() {
    const tbody  = document.getElementById('logTable');
    const total  = filtered.length;
    const pages  = Math.ceil(total/PAGE_SIZE)||1;
    currentPage  = Math.min(currentPage,pages);
    const start  = (currentPage-1)*PAGE_SIZE;
    const slice  = filtered.slice(start,start+PAGE_SIZE);

    if (!slice.length) { showEmpty('No logs found.'); return; }

    tbody.innerHTML = slice.map((r,i) => {
        const pillClass = ['STOCK_IN','STOCK_OUT','QUANTITY_CHANGE'].includes(r.action) ? r.action : 'DEFAULT';
        // Parse JSON and show only first key-value pair as preview
        function jsonPreview(raw) {
            try {
                const obj = JSON.parse(raw);
                const firstKey = Object.keys(obj)[0];
                return firstKey ? `${firstKey}: ${obj[firstKey]}` : raw;
            } catch(e) { return raw; }
        }
        const oldVal = r.old_value
            ? `<span class="json-cell" onclick='showJson("Old Value", ${JSON.stringify(r.old_value)})'>${jsonPreview(r.old_value)}</span>`
            : '<span style="color:var(--muted)">—</span>';
        const newVal = r.new_value
            ? `<span class="json-cell" onclick='showJson("New Value", ${JSON.stringify(r.new_value)})'>${jsonPreview(r.new_value)}</span>`
            : '<span style="color:var(--muted)">—</span>';
        return `<tr>
            <td>${start+i+1}</td>
            <td><span class="action-pill ${pillClass}">${esc(r.action)}</span></td>
            <td style="color:var(--muted);font-size:.8rem">${esc(r.table_name)||'—'}</td>
            <td style="color:var(--muted)">${r.record_id||'—'}</td>
            <td>${esc(r.username)||'<span style="color:var(--muted)">system</span>'}</td>
            <td>${oldVal}</td>
            <td>${newVal}</td>
            <td style="color:var(--muted);font-size:.78rem">${esc(r.ip_address)||'—'}</td>
            <td style="color:var(--muted);font-size:.78rem;white-space:nowrap">${formatDate(r.logged_at)}</td>
        </tr>`;
    }).join('');

    document.getElementById('pageInfo').textContent =
        `Showing ${start+1}–${Math.min(start+PAGE_SIZE,total)} of ${total} logs`;

    const btnContainer = document.getElementById('pageBtns');
    btnContainer.innerHTML='';
    const prev=document.createElement('button'); prev.className='page-btn'; prev.textContent='← Prev';
    prev.disabled=currentPage===1; prev.onclick=()=>{currentPage--;renderPage();}; btnContainer.appendChild(prev);
    const sp=Math.max(1,currentPage-2), ep=Math.min(pages,sp+4);
    for(let p=sp;p<=ep;p++){
        const btn=document.createElement('button'); btn.className='page-btn'+(p===currentPage?' active':'');
        btn.textContent=p; btn.onclick=((pg)=>()=>{currentPage=pg;renderPage();})(p); btnContainer.appendChild(btn);
    }
    const next=document.createElement('button'); next.className='page-btn'; next.textContent='Next →';
    next.disabled=currentPage===pages; next.onclick=()=>{currentPage++;renderPage();}; btnContainer.appendChild(next);
}

function showEmpty(msg) {
    document.getElementById('logTable').innerHTML=`<tr class="empty-row"><td colspan="9">${msg}</td></tr>`;
    document.getElementById('pageInfo').textContent='—';
    document.getElementById('pageBtns').innerHTML='';
}
function esc(s){ const d=document.createElement('div'); d.textContent=s??''; return d.innerHTML; }
function formatDate(d){ return new Date(d).toLocaleString('en-PH',{month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'}); }
function resetFilters(){
    document.getElementById('fAction').value='';
    document.getElementById('fFrom').value='<?= date('Y-m-01') ?>';
    document.getElementById('fTo').value='<?= date('Y-m-d') ?>';
    document.getElementById('fSearch').value='';
    loadLogs();
}
function showJson(title, raw) {
    try {
        const parsed = JSON.parse(raw);
        document.getElementById('jsonBody').textContent = JSON.stringify(parsed, null, 2);
    } catch(e) {
        document.getElementById('jsonBody').textContent = raw;
    }
    document.getElementById('jsonModalTitle').textContent = title;
    document.getElementById('jsonModalOverlay').classList.add('open');
}
function closeJson() {
    document.getElementById('jsonModalOverlay').classList.remove('open');
}
// Close JSON modal on overlay click
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('jsonModalOverlay').addEventListener('click', e => {
        if (e.target === e.currentTarget) closeJson();
    });
});
function toggleSettings(){
    document.getElementById('settingsToggle').classList.toggle('open');
    document.getElementById('settingsMenu').classList.toggle('open');
}
function logout(){ if(confirm('Logout?')) window.location.href='../backend/logout.php'; }
loadLogs();
</script>
<!-- JSON Viewer Modal -->
<div class="json-modal-overlay" id="jsonModalOverlay">
    <div class="json-modal">
        <div class="json-modal-header">
            <h4 id="jsonModalTitle">Value</h4>
            <button class="json-close" onclick="closeJson()">✕</button>
        </div>
        <pre class="json-body" id="jsonBody"></pre>
    </div>
</div>

</body>
</html>
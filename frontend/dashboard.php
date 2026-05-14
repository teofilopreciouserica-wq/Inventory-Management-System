<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../backend/database.php';

$totalProducts = $conn->query('SELECT COUNT(*) AS cnt FROM products')->fetch_assoc()['cnt'];
$lowStock      = $conn->query('SELECT COUNT(*) AS cnt FROM products WHERE quantity <= reorder_level AND quantity > 0')->fetch_assoc()['cnt'];
$outOfStock    = $conn->query('SELECT COUNT(*) AS cnt FROM products WHERE quantity = 0')->fetch_assoc()['cnt'];
$todayIn       = $conn->query("SELECT COALESCE(SUM(quantity),0) AS cnt FROM stock_in  WHERE DATE(transaction_at) = CURDATE()")->fetch_assoc()['cnt'];
$todayOut      = $conn->query("SELECT COALESCE(SUM(quantity),0) AS cnt FROM stock_out WHERE DATE(transaction_at) = CURDATE()")->fetch_assoc()['cnt'];

$recentTxn = $conn->query("
    SELECT 'Stock In' AS type, si.quantity, p.name AS product, u.username, si.transaction_at
    FROM stock_in si JOIN products p ON p.id = si.product_id JOIN users u ON u.id = si.user_id
    UNION ALL
    SELECT 'Stock Out', so.quantity, p.name, u.username, so.transaction_at
    FROM stock_out so JOIN products p ON p.id = so.product_id JOIN users u ON u.id = so.user_id
    ORDER BY transaction_at DESC LIMIT 7
");

$lowStockItems = $conn->query("
    SELECT name, sku, quantity, reorder_level FROM products
    WHERE quantity <= reorder_level ORDER BY quantity ASC LIMIT 5
");

$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
$role     = htmlspecialchars($_SESSION['role'],     ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Dashboard — Inventory System</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0f172a;
            --surface:   #1e293b;
            --surface2:  #273549;
            --border:    rgba(255,255,255,.08);
            --text:      #f1f5f9;
            --muted:     #64748b;
            --indigo:    #6366f1;
            --cyan:      #06b6d4;
            --green:     #22c55e;
            --yellow:    #f59e0b;
            --red:       #ef4444;
            --sidebar-w: 220px;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, sans-serif;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-w);
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
            overflow-y: auto;
        }

        .sidebar-logo {
            display: flex; align-items: center; gap: 10px;
            padding: 20px 16px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .sidebar-logo .icon {
            width: 34px; height: 34px;
            background: linear-gradient(135deg, var(--indigo), var(--cyan));
            border-radius: 9px;
            display: grid; place-items: center; font-size: 17px;
            flex-shrink: 0;
        }
        .sidebar-logo span { font-weight: 700; font-size: .95rem; }

        .user-pill {
            display: flex; align-items: center; gap: 8px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .avatar {
            width: 30px; height: 30px;
            background: linear-gradient(135deg, var(--indigo), var(--cyan));
            border-radius: 50%;
            display: grid; place-items: center;
            font-size: 12px; font-weight: 700; text-transform: uppercase;
            flex-shrink: 0;
        }
        .user-pill-info strong { font-size: .82rem; display: block; }
        .user-pill-info small  { font-size: .7rem;  color: var(--muted); }

        .sidebar-nav { flex: 1; padding: 10px 0; }

        .nav-section {
            font-size: .65rem; font-weight: 600; color: var(--muted);
            text-transform: uppercase; letter-spacing: .08em;
            padding: 14px 16px 6px;
        }

        .nav-item {
            display: flex; align-items: center; gap: 9px;
            padding: 9px 16px;
            color: var(--muted);
            text-decoration: none; font-size: .875rem;
            border-left: 3px solid transparent;
            transition: all .2s; cursor: pointer;
            background: none; border-top: none; border-right: none; border-bottom: none;
            width: 100%; text-align: left;
        }
        .nav-item:hover  { color: var(--text); background: var(--surface2); }
        .nav-item.active { color: var(--indigo); background: rgba(99,102,241,.1); border-left-color: var(--indigo); }
        .nav-item .nav-icon { font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }

        .nav-item.settings-toggle .arrow {
            margin-left: auto;
            font-size: 11px;
            transition: transform .25s;
        }
        .nav-item.settings-toggle.open .arrow { transform: rotate(180deg); }

        .settings-submenu {
            overflow: hidden;
            max-height: 0;
            transition: max-height .3s ease;
        }
        .settings-submenu.open { max-height: 300px; }

        .sub-item {
            display: flex; align-items: center; gap: 9px;
            padding: 8px 16px 8px 36px;
            color: var(--muted);
            text-decoration: none; font-size: .845rem;
            transition: all .2s;
            border-left: 3px solid transparent;
        }
        .sub-item:hover  { color: var(--text); background: var(--surface2); }
        .sub-item.active { color: var(--indigo); background: rgba(99,102,241,.08); border-left-color: var(--indigo); }
        .sub-item .nav-icon { font-size: 14px; width: 18px; text-align: center; }

        .sub-item.logout {
            color: #fca5a5;
            cursor: pointer;
            background: none; border-top: none; border-right: none; border-bottom: none;
            width: 100%; text-align: left;
        }
        .sub-item.logout:hover { background: rgba(239,68,68,.08); }

        
        .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; }

        .topbar {
            background: var(--surface); border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 50;
        }
        .topbar h2 { font-size: 1.05rem; font-weight: 600; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .badge-role {
            padding: 3px 9px;
            background: rgba(99,102,241,.15); border: 1px solid rgba(99,102,241,.3);
            border-radius: 20px; font-size: .72rem; color: #a5b4fc; text-transform: capitalize;
        }
        .live-dot {
            display: flex; align-items: center; gap: 5px;
            font-size: .72rem; color: var(--green);
        }
        .live-dot::before {
            content: ''; width: 7px; height: 7px;
            background: var(--green); border-radius: 50%;
            animation: pulse 1.5s ease-in-out infinite;
        }
        @keyframes pulse {
            0%,100% { opacity:1; transform:scale(1); }
            50%      { opacity:.5; transform:scale(1.3); }
        }

        .content { padding: 24px; }

      
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px; margin-bottom: 24px;
        }
        .stat-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 13px; padding: 18px;
            display: flex; flex-direction: column; gap: 9px;
            transition: transform .2s;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-header { display: flex; justify-content: space-between; align-items: center; }
        .stat-label  { font-size: .75rem; color: var(--muted); font-weight: 500; }
        .stat-icon   { width: 34px; height: 34px; border-radius: 9px; display: grid; place-items: center; font-size: 17px; }
        .stat-value  { font-size: 1.75rem; font-weight: 700; }
        .stat-sub    { font-size: .72rem; color: var(--muted); }

        .ic-indigo { background: rgba(99,102,241,.15); }
        .ic-green  { background: rgba(34,197,94,.15);  }
        .ic-yellow { background: rgba(245,158,11,.15); }
        .ic-red    { background: rgba(239,68,68,.15);  }
        .ic-cyan   { background: rgba(6,182,212,.15);  }

      
        .panels-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        @media (max-width: 860px) { .panels-row { grid-template-columns: 1fr; } }

        .panel { background: var(--surface); border: 1px solid var(--border); border-radius: 13px; overflow: hidden; }
        .panel-header {
            padding: 14px 18px; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
        }
        .panel-header h3 { font-size: .9rem; font-weight: 600; }
        .panel-header a  { font-size: .75rem; color: var(--indigo); text-decoration: none; }
        .panel-header a:hover { text-decoration: underline; }

        .txn-list { list-style: none; }
        .txn-item {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 18px; border-bottom: 1px solid var(--border); font-size: .84rem;
        }
        .txn-item:last-child { border-bottom: none; }
        .txn-badge { padding: 3px 7px; border-radius: 6px; font-size: .7rem; font-weight: 600; white-space: nowrap; }
        .txn-badge.in  { background: rgba(34,197,94,.15);  color: #86efac; }
        .txn-badge.out { background: rgba(239,68,68,.15);  color: #fca5a5; }
        .txn-info { flex: 1; }
        .txn-info strong { display: block; color: var(--text); }
        .txn-info small  { color: var(--muted); font-size: .72rem; }
        .txn-qty      { font-weight: 700; font-size: .9rem; }
        .txn-qty.in   { color: var(--green); }
        .txn-qty.out  { color: var(--red);   }

        .low-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
        .low-table th {
            padding: 9px 18px; text-align: left; font-size: .72rem; font-weight: 600;
            color: var(--muted); text-transform: uppercase; letter-spacing: .05em;
            border-bottom: 1px solid var(--border);
        }
        .low-table td { padding: 10px 18px; border-bottom: 1px solid var(--border); }
        .low-table tr:last-child td { border-bottom: none; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: .7rem; font-weight: 600; }
        .pill.out { background: rgba(239,68,68,.15);  color: #fca5a5; }
        .pill.low { background: rgba(245,158,11,.15); color: #fcd34d; }

        .empty-state { text-align: center; padding: 30px; color: var(--muted); font-size: .85rem; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="icon">📦</div>
        <span>InvenTrack</span>
    </div>

    <div class="user-pill">
        <div class="avatar"><?= substr($username, 0, 1) ?></div>
        <div class="user-pill-info">
            <strong><?= $username ?></strong>
            <small><?= $role ?></small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <a href="dashboard.php" class="nav-item active">
            <span class="nav-icon">🏠</span> Dashboard
        </a>
        <a href="products.php" class="nav-item">
            <span class="nav-icon">📋</span> Products
        </a>

        <div class="nav-section">Transactions</div>
        <a href="stock_in.php" class="nav-item">
            <span class="nav-icon">📥</span> Stock In
        </a>
        <a href="stock_out.php" class="nav-item">
            <span class="nav-icon">📤</span> Stock Out
        </a>
        <a href="transactions.php" class="nav-item">
            <span class="nav-icon">🗂️</span> History
        </a>

        <div class="nav-section">Settings</div>

        <button class="nav-item settings-toggle" id="settingsToggle" onclick="toggleSettings()">
            <span class="nav-icon">⚙️</span> Settings
            <span class="arrow">▼</span>
        </button>

        <div class="settings-submenu" id="settingsMenu">
            <a href="categories.php" class="sub-item">
                <span class="nav-icon">🏷️</span> Categories
            </a>
            <?php if ($role === 'admin'): ?>
            <a href="users.php" class="sub-item">
                <span class="nav-icon">👥</span> Users
            </a>
            <?php endif; ?>
            <a href="audit_log.php" class="sub-item">
                <span class="nav-icon">📜</span> Audit Log
            </a>
            <button class="sub-item logout" onclick="logout()">
                <span class="nav-icon">🚪</span> Logout
            </button>
        </div>
    </nav>
</aside>

<div class="main">
    <div class="topbar">
        <h2>Dashboard</h2>
        <div class="topbar-right">
            <div class="live-dot">Live</div>
            <span class="badge-role"><?= $role ?></span>
        </div>
    </div>

    <div class="content">

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Products</span>
                    <div class="stat-icon ic-indigo">📋</div>
                </div>
                <div class="stat-value"><?= number_format($totalProducts) ?></div>
                <div class="stat-sub">All items in system</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Stock In Today</span>
                    <div class="stat-icon ic-green">📥</div>
                </div>
                <div class="stat-value" style="color:var(--green)"><?= number_format($todayIn) ?></div>
                <div class="stat-sub">Units received today</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Stock Out Today</span>
                    <div class="stat-icon ic-cyan">📤</div>
                </div>
                <div class="stat-value" style="color:var(--cyan)"><?= number_format($todayOut) ?></div>
                <div class="stat-sub">Units released today</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Low Stock</span>
                    <div class="stat-icon ic-yellow">⚠️</div>
                </div>
                <div class="stat-value" style="color:var(--yellow)"><?= number_format($lowStock) ?></div>
                <div class="stat-sub">Items need restocking</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Out of Stock</span>
                    <div class="stat-icon ic-red">❌</div>
                </div>
                <div class="stat-value" style="color:var(--red)"><?= number_format($outOfStock) ?></div>
                <div class="stat-sub">Items fully depleted</div>
            </div>
        </div>

        <div class="panels-row">

            <div class="panel">
                <div class="panel-header">
                    <h3>Recent Transactions</h3>
                    <a href="transactions.php">View all →</a>
                </div>
                <?php if ($recentTxn && $recentTxn->num_rows > 0): ?>
                <ul class="txn-list">
                    <?php while ($row = $recentTxn->fetch_assoc()):
                        $isIn = $row['type'] === 'Stock In';
                        $cls  = $isIn ? 'in' : 'out';
                        $time = date('M d, g:i A', strtotime($row['transaction_at']));
                    ?>
                    <li class="txn-item">
                        <span class="txn-badge <?= $cls ?>"><?= $row['type'] ?></span>
                        <div class="txn-info">
                            <strong><?= htmlspecialchars($row['product'],  ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>by <?= htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') ?> · <?= $time ?></small>
                        </div>
                        <span class="txn-qty <?= $cls ?>"><?= $isIn ? '+' : '-' ?><?= $row['quantity'] ?></span>
                    </li>
                    <?php endwhile; ?>
                </ul>
                <?php else: ?>
                <div class="empty-state">No transactions yet.</div>
                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3>⚠️ Low Stock Alerts</h3>
                    <a href="products.php">Manage →</a>
                </div>
                <?php if ($lowStockItems && $lowStockItems->num_rows > 0): ?>
                <table class="low-table">
                    <thead>
                        <tr><th>Product</th><th>SKU</th><th>Qty</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($item = $lowStockItems->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="color:var(--muted)"><?= htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><strong><?= $item['quantity'] ?></strong></td>
                            <td>
                                <?php if ($item['quantity'] == 0): ?>
                                    <span class="pill out">Out of Stock</span>
                                <?php else: ?>
                                    <span class="pill low">Low Stock</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">✅ All items are sufficiently stocked.</div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
    function toggleSettings() {
        const toggle = document.getElementById('settingsToggle');
        const menu   = document.getElementById('settingsMenu');
        toggle.classList.toggle('open');
        menu.classList.toggle('open');
    }

    function logout() {
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = '../backend/logout.php';
        }
    }
</script>

</body>
</html>
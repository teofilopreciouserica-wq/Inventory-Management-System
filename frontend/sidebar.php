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
        <a href="dashboard.php"   class="nav-item <?= $currentPage === 'dashboard'   ? 'active' : '' ?>"><span class="nav-icon">🏠</span> Dashboard</a>
        <a href="products.php"    class="nav-item <?= $currentPage === 'products'    ? 'active' : '' ?>"><span class="nav-icon">📋</span> Products</a>

        <div class="nav-section">Transactions</div>
        <a href="stock_in.php"    class="nav-item <?= $currentPage === 'stock_in'    ? 'active' : '' ?>"><span class="nav-icon">📥</span> Stock In</a>
        <a href="stock_out.php"   class="nav-item <?= $currentPage === 'stock_out'   ? 'active' : '' ?>"><span class="nav-icon">📤</span> Stock Out</a>
        <a href="transactions.php"class="nav-item <?= $currentPage === 'transactions'? 'active' : '' ?>"><span class="nav-icon">🗂️</span> History</a>

        <div class="nav-section">Settings</div>
        <button class="nav-item settings-toggle" id="settingsToggle" onclick="toggleSettings()">
            <span class="nav-icon">⚙️</span> Settings
            <span class="arrow">▼</span>
        </button>
        <div class="settings-submenu" id="settingsMenu">
            <a href="categories.php" class="sub-item <?= $currentPage === 'categories' ? 'active' : '' ?>"><span class="nav-icon">🏷️</span> Categories</a>
            <?php if ($role === 'admin'): ?>
            <a href="users.php"      class="sub-item <?= $currentPage === 'users'      ? 'active' : '' ?>"><span class="nav-icon">👥</span> Users</a>
            <?php endif; ?>
            <a href="audit_log.php"  class="sub-item <?= $currentPage === 'audit_log'  ? 'active' : '' ?>"><span class="nav-icon">📜</span> Audit Log</a>
            <button class="sub-item logout" onclick="logout()"><span class="nav-icon">🚪</span> Logout</button>
        </div>
    </nav>
</aside>
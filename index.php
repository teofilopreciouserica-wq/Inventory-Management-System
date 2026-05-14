<?php 
session_start();
 
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
 
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Inventory System — Login</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            font-family: 'Segoe UI', system-ui, sans-serif;
            overflow: hidden;
        }

        
        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .35;
            animation: drift 12s ease-in-out infinite alternate;
        }
        body::before {
            width: 500px; height: 500px;
            background: #6366f1;
            top: -120px; left: -120px;
        }
        body::after {
            width: 420px; height: 420px;
            background: #06b6d4;
            bottom: -100px; right: -100px;
            animation-delay: -6s;
        }
        @keyframes drift {
            from { transform: translate(0,0) scale(1); }
            to   { transform: translate(40px,30px) scale(1.08); }
        }

        .card {
            position: relative;
            z-index: 1;
            background: rgba(255,255,255,.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 20px;
            padding: 48px 44px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 60px rgba(0,0,0,.5);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
        }
        .logo-icon {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, #6366f1, #06b6d4);
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 22px;
        }
        .logo-text h1 { font-size: 1.2rem; color: #f1f5f9; font-weight: 700; }
        .logo-text p  { font-size: .75rem; color: #94a3b8; margin-top: 1px; }

        h2 { color: #f1f5f9; font-size: 1.5rem; font-weight: 700; margin-bottom: 6px; }
        .subtitle { color: #64748b; font-size: .875rem; margin-bottom: 32px; }

        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .input-wrap { position: relative; }
        .input-wrap .icon {
            position: absolute;
            left: 14px; top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: #475569;
            pointer-events: none;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 14px 12px 42px;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 10px;
            color: #f1f5f9;
            font-size: .95rem;
            outline: none;
            transition: border-color .2s, background .2s;
        }
        input:focus {
            border-color: #6366f1;
            background: rgba(99,102,241,.08);
        }
        input::placeholder { color: #475569; }

        .toggle-pw {
            position: absolute;
            right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: #475569; cursor: pointer;
            font-size: 16px; padding: 0;
        }
        .toggle-pw:hover { color: #94a3b8; }

        .alert {
            display: none;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: .875rem;
            margin-bottom: 20px;
            border: 1px solid;
        }
        .alert.error   { background: rgba(239,68,68,.1);  border-color: rgba(239,68,68,.3);  color: #fca5a5; }
        .alert.success { background: rgba(34,197,94,.1);  border-color: rgba(34,197,94,.3);  color: #86efac; }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #6366f1, #06b6d4);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .2s, transform .15s;
            margin-top: 8px;
            position: relative;
        }
        .btn-login:hover:not(:disabled) { opacity: .9; transform: translateY(-1px); }
        .btn-login:disabled { opacity: .6; cursor: not-allowed; }

        .spinner {
            display: none;
            width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            position: absolute;
            right: 18px; top: 50%;
            transform: translateY(-50%);
        }
        @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }

        .footer-note {
            text-align: center;
            margin-top: 24px;
            font-size: .75rem;
            color: #334155;
        }
    </style>
</head>
<body>

<div class="card">
    <div class="logo">
        <div class="logo-icon">📦</div>
        <div class="logo-text">
            <h1>InvenTrack</h1>
            <p>Inventory Management System</p>
        </div>
    </div>

    <h2>Welcome back</h2>
    <p class="subtitle">Sign in to your account to continue</p>

    <div class="alert" id="alert"></div>

    <form id="loginForm" novalidate>
        <!-- CSRF Token -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">
            <label for="username">Username</label>
            <div class="input-wrap">
                <span class="icon">👤</span>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    autocomplete="username"
                    maxlength="100"
                    required
                />
            </div>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
                <span class="icon">🔒</span>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    maxlength="255"
                    required
                />
                <button type="button" class="toggle-pw" id="togglePw" aria-label="Toggle password">👁️</button>
            </div>
        </div>

        <button type="submit" class="btn-login" id="loginBtn">
            Sign In
            <span class="spinner" id="spinner"></span>
        </button>
    </form>

    <p class="footer-note">NEUST · BSIT 3rd Year · A.Y. 2025–2026</p>
</div>

<script> 
    document.getElementById('togglePw').addEventListener('click', () => {
        const pw  = document.getElementById('password');
        const btn = document.getElementById('togglePw');
        pw.type   = pw.type === 'password' ? 'text' : 'password';
        btn.textContent = pw.type === 'password' ? '👁️' : '🙈';
    });
 
    function showAlert(msg, type = 'error') {
        const el = document.getElementById('alert');
        el.textContent  = msg;
        el.className    = `alert ${type}`;
        el.style.display = 'block';
    }
 
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const btn     = document.getElementById('loginBtn');
        const spinner = document.getElementById('spinner');
        const alertEl = document.getElementById('alert');

        alertEl.style.display = 'none';

        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;

        if (!username || !password) {
            showAlert('Please enter your username and password.');
            return;
        }

        btn.disabled          = true;
        spinner.style.display = 'block';

        try {
            const res  = await fetch('backend/loginAuth.php', {
                method: 'POST',
                body: new FormData(e.target),
            });
            const data = await res.json();

            if (data.success) {
                showAlert('Login successful! Redirecting…', 'success');
                setTimeout(() => window.location.href = data.redirect, 800);
            } else {
                showAlert(data.message || 'Login failed. Please try again.');
                btn.disabled          = false;
                spinner.style.display = 'none';
            }
        } catch (err) {
            showAlert('Network error. Please try again.');
            btn.disabled          = false;
            spinner.style.display = 'none';
        }
    });
</script>

</body>
</html>
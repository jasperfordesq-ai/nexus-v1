<?php
/**
 * Minimal Login Page
 *
 * Used by the legacy PHP admin panel for session-based authentication.
 * Regular users log in via the React frontend at app.project-nexus.ie.
 */
$basePath = \Nexus\Core\TenantContext::getBasePath();
$csrfToken = \Nexus\Core\Csrf::token();
$tenantName = \Nexus\Core\TenantContext::get()['name'] ?? 'Project NEXUS';
$error = $error ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? "Login - $tenantName") ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); padding: 2.5rem; width: 100%; max-width: 400px; }
        .login-card h1 { font-size: 1.5rem; margin-bottom: 0.5rem; color: #1a1a1a; }
        .login-card p.subtitle { color: #666; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.25rem; font-size: 0.9rem; color: #333; }
        .form-group input { width: 100%; padding: 0.625rem 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; transition: border-color 0.2s; }
        .form-group input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        .btn-login { display: block; width: 100%; padding: 0.75rem; background: #6366f1; color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-login:hover { background: #4f46e5; }
        .error { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .admin-note { text-align: center; margin-top: 1.25rem; font-size: 0.8rem; color: #999; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1><?= htmlspecialchars($tenantName) ?></h1>
        <p class="subtitle">Admin login</p>

        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($basePath) ?>/login">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <p class="admin-note">This login is for admin panel access. Members should use the <a href="<?= htmlspecialchars(\Nexus\Core\TenantContext::getFrontendUrl()) ?>">main app</a>.</p>
    </div>
</body>
</html>

<?php
// $pageTitle is set in AuthController
// --- VIEW SWITCHER ---
// LOCKDOWN: Tenant forced layouts REMOVED
$curSlug = \Nexus\Core\TenantContext::get()['slug'] ?? '';
$reqUri = $_SERVER['REQUEST_URI'] ?? '/';
// $forceCivic = ($curSlug === 'public-sector-demo' || strpos($reqUri, '/public-sector-demo') === 0);
$forceCivic = false; // DISABLED FOR LOCKDOWN - respect global layout system only

if ($forceCivic || ($_SESSION['nexus_layout'] ?? 'default') === 'civicone') {
    require dirname(__DIR__) . '/civicone/auth/login.php';
    return;
}

if (is_modern()) {
    require dirname(__DIR__) . '/modern/auth/login.php';
    return;
}

// Fallback to generic header
require __DIR__ . '/../layouts/header.php';
?>

<?php
$boxClass = 'glass-panel';
$boxStyle = 'max-width: 400px; margin: 0 auto;';
?>
<article class="<?= $boxClass ?>" style="<?= $boxStyle ?>">
    <header>
        <h1>Login</h1>
    </header>

    <?php if (isset($_GET['registered'])): ?>
        <p style="color: green;">Registration successful! Please login.</p>
    <?php endif; ?>

    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" method="POST">
        <?= Nexus\Core\Csrf::input() ?>
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" placeholder="e.g. alice@example.com" required autocomplete="email">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">

        <button type="submit">Log in</button>
        <div style="text-align: center; margin-top: 15px;">
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/password/forgot" style="font-size: 0.9rem;">Forgot Password?</a>
        </div>
        <?php
        $socialPartial = __DIR__ . '/../partials/social_login.php';
        if (file_exists($socialPartial)) {
            require $socialPartial;
        }
        ?>
    </form>
    <footer>
        <small>Don't have an account? <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/register">Register here</a></small>
    </footer>
</article>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
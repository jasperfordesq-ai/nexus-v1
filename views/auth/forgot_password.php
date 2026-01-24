<?php
$pageTitle = $pageTitle ?? "Forgot Password";
require __DIR__ . '/../layouts/header.php';
?>

<div style="max-width: 400px; margin: 40px auto; padding: 20px;">
    <article class="glass-panel">
        <header>Forgot Password</header>
        <p>Enter your email address and we will send you a link to reset your password.</p>

        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/password/email" method="POST">
            <?= Nexus\Core\Csrf::input() ?>
            <label for="email">Email Address</label>
            <input type="email" name="email" id="email" required placeholder="you@example.com">

            <button type="submit">Send Reset Link</button>
        </form>

        <footer style="text-align: center; margin-top: 15px;">
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login">Back to Login</a>
        </footer>
    </article>
</div>

<?php  ?>
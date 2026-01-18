<?php
$pageTitle = $pageTitle ?? "Reset Password";
require __DIR__ . '/../layouts/header.php';
?>

<div style="max-width: 400px; margin: 40px auto; padding: 20px;">
    <article class="glass-panel">
        <header>Reset Password</header>

        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/password/reset" method="POST">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">

            <label for="password">New Password</label>
            <input type="password" name="password" id="password"  ?>
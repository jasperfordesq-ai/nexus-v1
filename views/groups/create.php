<?php
$tenantName = Nexus\Core\TenantContext::get()['name'] ?? 'Nexus TimeBank';
$pageTitle = "Create Hub - $tenantName";
// --- VIEW SWITCHER ---

// ---------------------


?>

<nav>
    <ul>
        <li><a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups">Back to Hubs</a></li>
    </ul>
</nav>

<article class="glass-panel">
    <header>
        <h1>Create a New Hub</h1>
    </header>
    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/store" method="POST">
        <?= Nexus\Core\Csrf::input() ?>
        <label for="name">Hub Name</label>
        <input type="text" name="name"  ?>
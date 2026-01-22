<?php
/**
 * Federation Groups Enable Required
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "Enable Federated Groups";
$hideHero = true;

Nexus\Core\SEO::setTitle('Enable Federated Groups');
Nexus\Core\SEO::setDescription('Enable federation settings to browse and join groups from partner timebanks.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <div class="civic-fed-opt-in-card" role="main">
        <div class="civic-fed-opt-in-icon" aria-hidden="true">
            <i class="fa-solid fa-people-group"></i>
        </div>

        <h1 class="civic-fed-opt-in-title">Enable Federated Groups</h1>

        <p class="civic-fed-opt-in-message">
            To browse and join groups from partner timebanks,
            you need to enable federation in your settings.
        </p>

        <a href="<?= $basePath ?>/settings#federation" class="civic-fed-btn civic-fed-btn--primary">
            <i class="fa-solid fa-cog" aria-hidden="true"></i>
            Go to Federation Settings
        </a>

        <aside class="civic-fed-info-card" role="complementary" aria-labelledby="groups-info-heading">
            <h2 id="groups-info-heading" class="civic-fed-info-heading">
                <i class="fa-solid fa-info-circle" aria-hidden="true"></i>
                What are Federated Groups?
            </h2>
            <ul class="civic-fed-info-list">
                <li>
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Join interest groups from partner timebanks
                </li>
                <li>
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Connect with members across the network
                </li>
                <li>
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Participate in group discussions and activities
                </li>
                <li>
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    You control your group memberships
                </li>
            </ul>
        </aside>
    </div>
</div>

<!-- Federation offline indicator -->
<script src="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/js/civicone-federation-offline.min.js" defer></script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>

<?php
/**
 * Federation Scope Switcher Partial
 * MOJ Organisation Switcher Pattern
 *
 * Shows current federation scope (All communities vs specific partner)
 * Only displayed if user has access to 2+ partner communities
 *
 * Required variables:
 * - $partnerCommunities: array of partner tenant data
 * - $currentScope: 'all' or specific tenant ID
 */

$basePath = $basePath ?? \Nexus\Core\TenantContext::getBasePath();
$currentScope = $currentScope ?? 'all';

// Only show if user has 2+ communities to choose from
if (empty($partnerCommunities) || count($partnerCommunities) < 2) {
    return;
}
?>

<!-- Federation Scope Context (MOJ Organisation Switcher Pattern) -->
<div class="civicone-width-container">
    <div class="moj-organisation-switcher" aria-label="Federation scope">
        <p class="moj-organisation-switcher__heading">Partner Communities:</p>
        <nav class="moj-organisation-switcher__nav" aria-label="Switch partner community">
            <ul class="moj-organisation-switcher__list">
                <!-- All Communities Option -->
                <li class="moj-organisation-switcher__item <?= $currentScope === 'all' ? 'moj-organisation-switcher__item--active' : '' ?>">
                    <a href="<?= $basePath ?>/federation<?= isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/members') !== false ? '/members' : '' ?><?= isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/listings') !== false ? '/listings' : '' ?><?= isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/events') !== false ? '/events' : '' ?><?= isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/groups') !== false ? '/groups' : '' ?>?scope=all" <?= $currentScope === 'all' ? 'aria-current="page"' : '' ?>>
                        All shared communities
                    </a>
                </li>

                <!-- Individual Partner Communities -->
                <?php foreach ($partnerCommunities as $partner): ?>
                <li class="moj-organisation-switcher__item <?= $currentScope == $partner['id'] ? 'moj-organisation-switcher__item--active' : '' ?>">
                    <a href="<?= $basePath ?>/federation<?= isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/members') !== false ? '/members' : '' ?><?= isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/listings') !== false ? '/listings' : '' ?><?= isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/events') !== false ? '/events' : '' ?><?= isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/groups') !== false ? '/groups' : '' ?>?scope=<?= $partner['id'] ?>" <?= $currentScope == $partner['id'] ? 'aria-current="page"' : '' ?>>
                        <?= htmlspecialchars($partner['name'] ?? 'Partner Community') ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <!-- Optional: Link to federation settings -->
        <?php if (isset($_SESSION['user_id'])): ?>
        <p class="moj-organisation-switcher__footer">
            <a href="<?= $basePath ?>/federation/settings" class="govuk-link">
                Change partner preferences
            </a>
        </p>
        <?php endif; ?>
    </div>
</div>

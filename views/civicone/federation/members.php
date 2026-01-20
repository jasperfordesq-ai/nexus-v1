<?php
/**
 * Federation Members Directory
 * CivicOne Theme - WCAG 2.1 AA Compliant
 * Template: Directory/List (MOJ Filter a list pattern)
 */
$pageTitle = $pageTitle ?? "Federated Members";
$pageSubtitle = "Connect with members from partner timebanks";
$hideHero = true;
$bodyClass = 'civicone--federation';
$currentPage = 'members';

\Nexus\Core\SEO::setTitle('Federated Members - Partner Timebank Directory');
\Nexus\Core\SEO::setDescription('Browse and connect with members from partner timebanks in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();

$members = $members ?? [];
$partnerTenants = $partnerTenants ?? [];
$filters = $filters ?? [];
$partnerCommunities = $partnerCommunities ?? $partnerTenants;
$currentScope = $currentScope ?? 'all';

// Build active filters array for tags
$activeFilters = [];
if (!empty($filters['tenant_id'])) {
    $tenantName = '';
    foreach ($partnerTenants as $t) {
        if ($t['id'] == $filters['tenant_id']) {
            $tenantName = $t['name'];
            break;
        }
    }
    $activeFilters[] = [
        'label' => 'Community: ' . $tenantName,
        'remove_url' => $basePath . '/federation/members?' . http_build_query(array_diff_key($filters, ['tenant_id' => '']))
    ];
}
if (!empty($filters['service_reach'])) {
    $reachLabel = $filters['service_reach'] === 'remote_ok' ? 'Remote Services' : 'Will Travel';
    $activeFilters[] = [
        'label' => $reachLabel,
        'remove_url' => $basePath . '/federation/members?' . http_build_query(array_diff_key($filters, ['service_reach' => '']))
    ];
}
if (!empty($filters['messaging_enabled'])) {
    $activeFilters[] = [
        'label' => 'Messaging enabled',
        'remove_url' => $basePath . '/federation/members?' . http_build_query(array_diff_key($filters, ['messaging_enabled' => '']))
    ];
}
if (!empty($filters['transactions_enabled'])) {
    $activeFilters[] = [
        'label' => 'Transactions enabled',
        'remove_url' => $basePath . '/federation/members?' . http_build_query(array_diff_key($filters, ['transactions_enabled' => '']))
    ];
}
?>

<!-- Federation Scope Switcher (only if user has 2+ communities) -->
<?php if (count($partnerCommunities) >= 2): ?>
    <?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/federation-scope-switcher.php'; ?>
<?php endif; ?>

<!-- Federation Service Navigation -->
<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/federation-service-navigation.php'; ?>

<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">

        <!-- Page Header -->
        <h1 class="govuk-heading-xl">Federated Members</h1>

        <p class="govuk-body-l">
            Discover members from <?= count($partnerTenants) ?> partner timebank<?= count($partnerTenants) !== 1 ? 's' : '' ?> in the federation network.
        </p>

        <!-- Selected Filters (MOJ Filter component) -->
        <?php if (!empty($activeFilters)): ?>
        <div class="moj-filter-tags">
            <h2 class="govuk-heading-s">Selected filters</h2>
            <div class="moj-filter-tags__wrapper">
                <?php foreach ($activeFilters as $filter): ?>
                <a href="<?= $filter['remove_url'] ?>" class="moj-filter-tag">
                    <?= htmlspecialchars($filter['label']) ?>
                    <span class="govuk-visually-hidden">Remove filter</span>
                </a>
                <?php endforeach; ?>
                <a href="<?= $basePath ?>/federation/members" class="moj-filter-tag moj-filter-tag--clear-all">
                    Clear all filters
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Two-column layout: Filters (1/3) + Results (2/3) -->
        <div class="moj-filter-layout">

            <!-- Filter Panel (1/3 width) -->
            <div class="moj-filter-layout__filter">
                <form method="GET" action="<?= $basePath ?>/federation/members">
                    <fieldset class="govuk-fieldset">
                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                            Filter members
                        </legend>

                        <!-- Source Community Filter (MANDATORY) -->
                        <div class="govuk-form-group">
                            <label class="govuk-label govuk-label--s" for="tenant-filter">
                                Source community
                            </label>
                            <select id="tenant-filter" name="tenant" class="govuk-select">
                                <option value="">All partner communities</option>
                                <?php foreach ($partnerTenants as $tenant): ?>
                                    <option value="<?= $tenant['id'] ?>" <?= ($filters['tenant_id'] ?? '') == $tenant['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tenant['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Service Reach Filter -->
                        <div class="govuk-form-group">
                            <label class="govuk-label govuk-label--s" for="reach-filter">
                                Service reach
                            </label>
                            <select id="reach-filter" name="reach" class="govuk-select">
                                <option value="">Any</option>
                                <option value="remote_ok" <?= ($filters['service_reach'] ?? '') === 'remote_ok' ? 'selected' : '' ?>>
                                    Remote services
                                </option>
                                <option value="travel_ok" <?= ($filters['service_reach'] ?? '') === 'travel_ok' ? 'selected' : '' ?>>
                                    Will travel
                                </option>
                            </select>
                        </div>

                        <!-- Messaging Enabled Filter -->
                        <div class="govuk-checkboxes govuk-checkboxes--small">
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="messaging-filter" name="messaging" type="checkbox" value="1" <?= !empty($filters['messaging_enabled']) ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="messaging-filter">
                                    Messaging enabled
                                </label>
                            </div>
                            <div class="govuk-checkboxes__item">
                                <input class="govuk-checkboxes__input" id="transactions-filter" name="transactions" type="checkbox" value="1" <?= !empty($filters['transactions_enabled']) ? 'checked' : '' ?>>
                                <label class="govuk-label govuk-checkboxes__label" for="transactions-filter">
                                    Transactions enabled
                                </label>
                            </div>
                        </div>

                        <!-- Apply Filters Button -->
                        <button type="submit" class="govuk-button govuk-!-margin-top-4" data-module="govuk-button">
                            Apply filters
                        </button>
                    </fieldset>
                </form>
            </div>

            <!-- Results Panel (2/3 width) -->
            <div class="moj-filter-layout__content" aria-live="polite" aria-busy="false">

                <!-- Results Count -->
                <div class="moj-action-bar">
                    <div class="moj-action-bar__filter">
                        <p class="govuk-body">
                            <strong><?= count($members) ?></strong> member<?= count($members) !== 1 ? 's' : '' ?> found
                        </p>
                    </div>
                </div>

                <!-- Members List -->
                <?php if (!empty($members)): ?>
                    <ul class="govuk-list">
                        <?php foreach ($members as $member): ?>
                        <li class="govuk-!-margin-bottom-6">
                            <div class="govuk-summary-card">
                                <div class="govuk-summary-card__title-wrapper">
                                    <h3 class="govuk-summary-card__title">
                                        <a href="<?= $basePath ?>/federation/members/<?= $member['id'] ?>" class="govuk-link">
                                            <?= htmlspecialchars($member['name'] ?? 'Member') ?>
                                        </a>
                                    </h3>
                                    <!-- PROVENANCE LABEL (MANDATORY) -->
                                    <span class="govuk-tag govuk-tag--grey">
                                        Shared from <?= htmlspecialchars($member['tenant_name'] ?? 'Partner') ?>
                                    </span>
                                </div>
                                <div class="govuk-summary-card__content">
                                    <?php if (!empty($member['bio'])): ?>
                                    <p class="govuk-body-s">
                                        <?= htmlspecialchars(mb_substr($member['bio'], 0, 200)) ?><?= mb_strlen($member['bio']) > 200 ? '...' : '' ?>
                                    </p>
                                    <?php endif; ?>

                                    <dl class="govuk-summary-list govuk-summary-list--no-border">
                                        <?php if (!empty($member['location'])): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Location</dt>
                                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($member['location']) ?></dd>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($member['skills'])): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Skills</dt>
                                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($member['skills']) ?></dd>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($member['service_reach'])): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Service reach</dt>
                                            <dd class="govuk-summary-list__value">
                                                <?php if ($member['service_reach'] === 'remote_ok'): ?>
                                                    Remote services available
                                                <?php elseif ($member['service_reach'] === 'travel_ok'): ?>
                                                    Will travel for services
                                                <?php else: ?>
                                                    Local only
                                                <?php endif; ?>
                                            </dd>
                                        </div>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                                <div class="govuk-summary-card__actions">
                                    <a href="<?= $basePath ?>/federation/members/<?= $member['id'] ?>" class="govuk-link">
                                        View profile<span class="govuk-visually-hidden"> for <?= htmlspecialchars($member['name'] ?? 'member') ?></span>
                                    </a>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Pagination (GOV.UK Pattern - MANDATORY) -->
                    <?php
                    $currentPage = (int)(($_GET['page'] ?? 1));
                    $totalResults = count($members); // In production, this would come from controller
                    $perPage = 30;
                    $totalPages = max(1, ceil($totalResults / $perPage));
                    ?>
                    <?php if ($totalPages > 1): ?>
                    <nav class="govuk-pagination" role="navigation" aria-label="Results navigation">
                        <span class="govuk-visually-hidden">Page <?= $currentPage ?> of <?= $totalPages ?></span>
                        <?php if ($currentPage > 1): ?>
                        <div class="govuk-pagination__prev">
                            <a class="govuk-link govuk-pagination__link" href="?page=<?= $currentPage - 1 ?><?= !empty($_GET['tenant']) ? '&tenant=' . $_GET['tenant'] : '' ?><?= !empty($_GET['reach']) ? '&reach=' . $_GET['reach'] : '' ?>" rel="prev">
                                <svg class="govuk-pagination__icon govuk-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                    <path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.4062 1.377-1.449-4.1856-3.9768h12.896v-2h-12.984l4.2931-4.293-1.414-1.414z"></path>
                                </svg>
                                <span class="govuk-pagination__link-title">Previous</span>
                            </a>
                        </div>
                        <?php endif; ?>

                        <ul class="govuk-pagination__list">
                            <?php for ($i = 1; $i <= min(5, $totalPages); $i++): ?>
                            <li class="govuk-pagination__item <?= $i === $currentPage ? 'govuk-pagination__item--current' : '' ?>">
                                <?php if ($i === $currentPage): ?>
                                    <span class="govuk-pagination__link-label" aria-current="page"><?= $i ?></span>
                                <?php else: ?>
                                    <a class="govuk-link govuk-pagination__link" href="?page=<?= $i ?><?= !empty($_GET['tenant']) ? '&tenant=' . $_GET['tenant'] : '' ?><?= !empty($_GET['reach']) ? '&reach=' . $_GET['reach'] : '' ?>" aria-label="Page <?= $i ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                            <?php endfor; ?>
                        </ul>

                        <?php if ($currentPage < $totalPages): ?>
                        <div class="govuk-pagination__next">
                            <a class="govuk-link govuk-pagination__link" href="?page=<?= $currentPage + 1 ?><?= !empty($_GET['tenant']) ? '&tenant=' . $_GET['tenant'] : '' ?><?= !empty($_GET['reach']) ? '&reach=' . $_GET['reach'] : '' ?>" rel="next">
                                <span class="govuk-pagination__link-title">Next</span>
                                <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                                    <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                                </svg>
                            </a>
                        </div>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Empty State -->
                    <div class="govuk-panel govuk-panel--bordered" role="status" aria-live="polite">
                        <p class="govuk-body">No members found matching your filters.</p>
                        <p class="govuk-body">
                            Try adjusting your filters or <a href="<?= $basePath ?>/federation/members" class="govuk-link">clear all filters</a> to see all members.
                        </p>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </main>
</div>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>

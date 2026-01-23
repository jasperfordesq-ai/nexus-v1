<?php
/**
 * Federation Groups Directory
 * CivicOne Theme - WCAG 2.1 AA Compliant
 * Template: Directory/List (MOJ Filter a list pattern)
 */
$pageTitle = $pageTitle ?? "Federated Groups";
$pageSubtitle = "Discover and join groups from partner timebanks";
$hideHero = true;
$bodyClass = 'civicone--federation';
$currentPage = 'groups';

\Nexus\Core\SEO::setTitle('Federated Groups - Partner Timebank Communities');
\Nexus\Core\SEO::setDescription('Discover and join groups from partner timebanks in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();

$groups = $groups ?? [];
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
        'remove_url' => $basePath . '/federation/groups?' . http_build_query(array_diff_key($filters, ['tenant_id' => '']))
    ];
}
if (!empty($filters['search'])) {
    $activeFilters[] = [
        'label' => 'Search: "' . htmlspecialchars($filters['search']) . '"',
        'remove_url' => $basePath . '/federation/groups?' . http_build_query(array_diff_key($filters, ['search' => '']))
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
    <main class="civicone-main-wrapper">

        <!-- Page Header -->
        <h1 class="govuk-heading-xl">Federated Groups</h1>

        <p class="govuk-body-l">
            Discover and join groups from <?= count($partnerTenants) ?> partner timebank<?= count($partnerTenants) !== 1 ? 's' : '' ?> in the federation network.
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
                <a href="<?= $basePath ?>/federation/groups" class="moj-filter-tag moj-filter-tag--clear-all">
                    Clear all filters
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Two-column layout: Filters (1/3) + Results (2/3) -->
        <div class="moj-filter-layout">

            <!-- Filter Panel (1/3 width) -->
            <div class="moj-filter-layout__filter">
                <form method="GET" action="<?= $basePath ?>/federation/groups">
                    <fieldset class="govuk-fieldset">
                        <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                            Filter groups
                        </legend>

                        <!-- Search Input -->
                        <div class="govuk-form-group">
                            <label class="govuk-label govuk-label--s" for="search-input">
                                Search group names and descriptions
                            </label>
                            <input class="govuk-input" id="search-input" name="q" type="text"
                                   value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                                   placeholder="Enter keywords...">
                        </div>

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
                            <strong><?= count($groups) ?></strong> group<?= count($groups) !== 1 ? 's' : '' ?> found
                        </p>
                    </div>
                </div>

                <!-- Groups List -->
                <?php if (!empty($groups)): ?>
                    <ul class="govuk-list">
                        <?php foreach ($groups as $group): ?>
                        <li class="govuk-!-margin-bottom-6">
                            <div class="govuk-summary-card">
                                <div class="govuk-summary-card__title-wrapper">
                                    <h3 class="govuk-summary-card__title">
                                        <a href="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>?tenant=<?= $group['tenant_id'] ?>" class="govuk-link">
                                            <?= htmlspecialchars($group['name'] ?? 'Untitled') ?>
                                        </a>
                                    </h3>
                                    <div class="civicone-federation-badges">
                                        <!-- Privacy Badge -->
                                        <?php if (!empty($group['privacy'])): ?>
                                        <span class="govuk-tag <?= $group['privacy'] === 'private' ? 'govuk-tag--red' : 'govuk-tag--green' ?>">
                                            <?= ucfirst($group['privacy']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <!-- PROVENANCE LABEL (MANDATORY) -->
                                        <span class="govuk-tag govuk-tag--grey">
                                            Shared from <?= htmlspecialchars($group['tenant_name'] ?? 'Partner') ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="govuk-summary-card__content">
                                    <?php if (!empty($group['description'])): ?>
                                    <p class="govuk-body-s">
                                        <?= htmlspecialchars(mb_substr($group['description'], 0, 200)) ?><?= mb_strlen($group['description']) > 200 ? '...' : '' ?>
                                    </p>
                                    <?php endif; ?>

                                    <dl class="govuk-summary-list govuk-summary-list--no-border">
                                        <?php if (isset($group['member_count'])): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Members</dt>
                                            <dd class="govuk-summary-list__value"><?= $group['member_count'] ?> member<?= $group['member_count'] !== 1 ? 's' : '' ?></dd>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($group['location'])): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Location</dt>
                                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($group['location']) ?></dd>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($group['category'])): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Category</dt>
                                            <dd class="govuk-summary-list__value"><?= htmlspecialchars($group['category']) ?></dd>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($group['created_at'])): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Created</dt>
                                            <dd class="govuk-summary-list__value">
                                                <?= date('d M Y', strtotime($group['created_at'])) ?>
                                            </dd>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (isset($group['is_member']) && $group['is_member']): ?>
                                        <div class="govuk-summary-list__row">
                                            <dt class="govuk-summary-list__key">Status</dt>
                                            <dd class="govuk-summary-list__value">
                                                <span class="govuk-tag govuk-tag--green">Member</span>
                                            </dd>
                                        </div>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                                <div class="govuk-summary-card__actions">
                                    <a href="<?= $basePath ?>/federation/groups/<?= $group['id'] ?>?tenant=<?= $group['tenant_id'] ?>" class="govuk-link">
                                        View group<span class="govuk-visually-hidden"> <?= htmlspecialchars($group['name'] ?? 'group') ?></span>
                                    </a>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Pagination (GOV.UK Pattern - MANDATORY) -->
                    <?php
                    $currentPage = (int)(($_GET['page'] ?? 1));
                    $totalResults = count($groups); // In production, this would come from controller
                    $perPage = 30;
                    $totalPages = max(1, ceil($totalResults / $perPage));

                    // Build query string without page param
                    $queryParams = $_GET;
                    unset($queryParams['page']);
                    $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                    ?>
                    <?php if ($totalPages > 1): ?>
                    <nav class="govuk-pagination" role="navigation" aria-label="Results navigation">
                        <span class="govuk-visually-hidden">Page <?= $currentPage ?> of <?= $totalPages ?></span>
                        <?php if ($currentPage > 1): ?>
                        <div class="govuk-pagination__prev">
                            <a class="govuk-link govuk-pagination__link" href="?page=<?= $currentPage - 1 ?><?= $queryString ?>" rel="prev">
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
                                    <a class="govuk-link govuk-pagination__link" href="?page=<?= $i ?><?= $queryString ?>" aria-label="Page <?= $i ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                            <?php endfor; ?>
                        </ul>

                        <?php if ($currentPage < $totalPages): ?>
                        <div class="govuk-pagination__next">
                            <a class="govuk-link govuk-pagination__link" href="?page=<?= $currentPage + 1 ?><?= $queryString ?>" rel="next">
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
                        <p class="govuk-body">No groups found matching your filters.</p>
                        <p class="govuk-body">
                            Try adjusting your filters or <a href="<?= $basePath ?>/federation/groups" class="govuk-link">clear all filters</a> to see all groups.
                        </p>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </main>
</div>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>

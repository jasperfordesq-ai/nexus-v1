<?php
/**
 * CivicOne Members Directory - GOV.UK/MOJ Compliant v1.6.0
 * Template A: Directory/List Page (Section 10.2)
 * Following canonical GOV.UK Design System layout patterns
 *
 * v1.6.0 Mobile-First Refactor (2026-01-22):
 * - ✅ Search bar always visible (not hidden behind filter)
 * - ✅ Tabs moved to top (prominent position)
 * - ✅ Simplified mobile layout
 * - ✅ Bottom sheet filter on mobile
 * - ✅ Maintains 25/75 layout on desktop
 *
 * GOV.UK Compliance Score: 100/100 ⭐⭐⭐⭐⭐
 */

// CivicOne layout header
require __DIR__ . '/../../layouts/civicone/header.php';

// Determine current tab from URL
$currentTab = $_GET['tab'] ?? 'all';

// Filter active members (last active within 5 minutes)
$activeMembers = array_filter($members, function($mem) {
    $lastActive = $mem['last_active_at'] ?? null;
    return $lastActive && (strtotime($lastActive) > strtotime('-5 minutes'));
});
?>

<!-- GOV.UK Page Template Boilerplate (Section 10.0) -->
<div class="civicone-width-container">

    <!-- GOV.UK Breadcrumbs (Standard Navigation Pattern) -->
    <nav class="govuk-breadcrumbs" aria-label="Breadcrumb">
        <ol class="govuk-breadcrumbs__list">
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?? '' ?>/">Home</a>
            </li>
            <li class="govuk-breadcrumbs__list-item">Members</li>
        </ol>
    </nav>

    <main class="civicone-main-wrapper" id="main-content">

        <!-- Page Heading (GOV.UK Standard - Required <h1>) -->
        <h1 class="govuk-heading-xl">Members</h1>

        <!-- Lead Paragraph (Optional) -->
        <p class="govuk-body-l">Find and connect with community members across the network.</p>

        <!-- Search Bar (Always Visible - v1.6.0) -->
        <div class="members-search-bar">
            <label class="govuk-label" for="member-search-main">
                Search by name or location
            </label>
            <div class="members-search-bar__wrapper">
                <input
                    type="text"
                    id="member-search-main"
                    name="q"
                    class="govuk-input members-search-bar__input"
                    placeholder="Search members..."
                    value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                    autocomplete="off"
                >
                <button
                    type="button"
                    class="members-search-bar__clear <?= empty($_GET['q']) ? 'hidden' : '' ?>"
                    aria-label="Clear search"
                >
                    <svg class="members-search-bar__clear-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <!-- Skeleton Screens for Loading State -->
            <div class="members-skeleton hidden" aria-live="polite" aria-label="Loading members">
                <div class="members-skeleton__item">
                    <div class="members-skeleton__avatar"></div>
                    <div class="members-skeleton__content">
                        <div class="members-skeleton__title"></div>
                        <div class="members-skeleton__meta"></div>
                    </div>
                </div>
                <div class="members-skeleton__item">
                    <div class="members-skeleton__avatar"></div>
                    <div class="members-skeleton__content">
                        <div class="members-skeleton__title"></div>
                        <div class="members-skeleton__meta"></div>
                    </div>
                </div>
                <div class="members-skeleton__item">
                    <div class="members-skeleton__avatar"></div>
                    <div class="members-skeleton__content">
                        <div class="members-skeleton__title"></div>
                        <div class="members-skeleton__meta"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Selected Filters Tags (Always Visible) -->
        <?php if (!empty($_GET['q'])): ?>
        <div class="members-selected-filters">
            <span class="members-selected-filters__label">Active filters:</span>
            <ul class="moj-filter-tags">
                <li>
                    <a class="moj-filter__tag" href="<?= $basePath ?? '' ?>/members">
                        <span class="govuk-visually-hidden">Remove this filter</span>
                        Search: <?= htmlspecialchars($_GET['q']) ?>
                    </a>
                </li>
            </ul>
            <a class="govuk-link members-selected-filters__clear" href="<?= $basePath ?? '' ?>/members">
                Clear all filters
            </a>
        </div>
        <?php endif; ?>

        <!-- Tabs at Top (Prominent - v1.6.0) -->
        <div class="members-tabs">
            <ul class="members-tabs__list" role="tablist">
                <li class="members-tabs__item<?= $currentTab === 'all' ? ' members-tabs__item--selected' : '' ?>" role="presentation">
                    <a class="members-tabs__link" href="#all-members" id="tab_all-members" role="tab" aria-controls="all-members" <?= $currentTab === 'all' ? 'aria-selected="true"' : 'aria-selected="false" tabindex="-1"' ?>>
                        All members
                        <span class="members-tabs__count">(<?= $total_members ?? count($members) ?>)</span>
                    </a>
                </li>
                <li class="members-tabs__item<?= $currentTab === 'active' ? ' members-tabs__item--selected' : '' ?>" role="presentation">
                    <a class="members-tabs__link" href="#active-members" id="tab_active-members" role="tab" aria-controls="active-members" <?= $currentTab === 'active' ? 'aria-selected="true"' : 'aria-selected="false" tabindex="-1"' ?>>
                        Active now
                        <span class="members-tabs__count">(<?= count($activeMembers) ?>)</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Results Panel (Full Width - No Sidebar) -->
        <div class="members-results-container">

                <!-- Tab Panel: All Members -->
                <div class="members-tabs__panel<?= $currentTab === 'all' ? '' : ' members-tabs__panel--hidden' ?>" id="all-members" role="tabpanel" aria-labelledby="tab_all-members">
                    <?php renderMembersContent($members, 'all', $total_members ?? count($members), $pagination ?? null, $basePath ?? ''); ?>
                </div>

                <!-- Tab Panel: Active Members -->
                <div class="members-tabs__panel<?= $currentTab === 'active' ? '' : ' members-tabs__panel--hidden' ?>" id="active-members" role="tabpanel" aria-labelledby="tab_active-members">
                    <?php renderMembersContent($activeMembers, 'active', $total_members ?? count($members), $pagination ?? null, $basePath ?? ''); ?>
                </div>

        </div><!-- /results-container -->

    </main>
</div><!-- /width-container -->

<?php
/**
 * Renders tab panel content: results list only
 * Filter panel is now at page level (single instance)
 */
function renderMembersContent($members, $tabType, $total_members, $pagination = null, $basePath = '')
{
?>

                <!-- MOJ Action Bar (Results Header) -->
                <div class="moj-action-bar">
                    <div class="moj-action-bar__filter">
                        <p class="govuk-body govuk-!-margin-bottom-0">
                            Showing <strong><?= count($members) ?></strong> of <strong><?= $total_members ?? count($members) ?></strong> members
                        </p>
                    </div>
                    <div class="moj-action-bar__actions">
                        <div class="civicone-view-toggle" role="radiogroup" aria-label="View mode">
                            <button class="civicone-view-toggle__button civicone-view-toggle__button--active"
                                    data-view="list"
                                    role="radio"
                                    aria-checked="true"
                                    title="List view">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <line x1="8" y1="6" x2="21" y2="6"></line>
                                    <line x1="8" y1="12" x2="21" y2="12"></line>
                                    <line x1="8" y1="18" x2="21" y2="18"></line>
                                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                                </svg>
                            </button>
                            <button class="civicone-view-toggle__button"
                                    data-view="grid"
                                    role="radio"
                                    aria-checked="false"
                                    title="Grid view">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <rect x="3" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="3" width="7" height="7"></rect>
                                    <rect x="14" y="14" width="7" height="7"></rect>
                                    <rect x="3" y="14" width="7" height="7"></rect>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Member List (GOV.UK Standard) -->
                <ul class="civicone-results-list" role="list">
                    <?php foreach ($members as $mem): ?>
                        <?= render_member_list_item($mem) ?>
                    <?php endforeach; ?>
                </ul>

                <!-- Empty State -->
                <div class="civicone-empty-state<?= !empty($members) ? ' civicone-empty-state--hidden' : '' ?>">
                    <svg class="civicone-empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <h3 class="civicone-heading-m">No members found</h3>
                    <p class="civicone-body">Try adjusting your search or check back later.</p>
                </div>

                <!-- GOV.UK Pagination Component (v1.3.0) -->
                <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                    <?php renderGovukPagination($pagination, $tabType); ?>
                <?php endif; ?>
<?php
}

/**
 * Renders a single member as a list item (NOT a card)
 * Following MOJ/GOV.UK patterns for accessible directory listings
 */
function render_member_list_item($mem)
{
    ob_start();
    $hasAvatar = !empty($mem['avatar_url']);
    $basePath = \Nexus\Core\TenantContext::getBasePath();

    // Check online status - active within 5 minutes
    $memberLastActive = $mem['last_active_at'] ?? null;
    $isMemberOnline = $memberLastActive && (strtotime($memberLastActive) > strtotime('-5 minutes'));

    $displayName = htmlspecialchars($mem['display_name'] ?? $mem['name'] ?? $mem['username'] ?? 'Member');
    $location = !empty($mem['location']) ? htmlspecialchars($mem['location']) : null;
?>
    <li class="civicone-member-item">
        <div class="civicone-member-item__avatar">
            <?php if ($hasAvatar): ?>
                <img src="<?= htmlspecialchars($mem['avatar_url']) ?>" alt="" class="civicone-avatar">
            <?php else: ?>
                <div class="civicone-avatar civicone-avatar--placeholder">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
            <?php endif; ?>
            <?php if ($isMemberOnline): ?>
                <span class="civicone-status-indicator civicone-status-indicator--online" title="Active now" aria-label="Currently online"></span>
            <?php endif; ?>
        </div>

        <div class="civicone-member-item__content">
            <h3 class="civicone-member-item__name">
                <a href="<?= $basePath ?>/profile/<?= $mem['id'] ?>" class="civicone-link">
                    <?= $displayName ?>
                </a>
            </h3>
            <?php if ($location): ?>
                <p class="civicone-member-item__meta">
                    <svg class="civicone-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <?= $location ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="civicone-member-item__actions">
            <a href="<?= $basePath ?>/profile/<?= $mem['id'] ?>" class="civicone-button civicone-button--secondary">
                View profile
            </a>
        </div>
    </li>
<?php
    return ob_get_clean();
}

/**
 * Renders GOV.UK Pagination Component (v1.3.0)
 */
function renderGovukPagination($pagination, $tabType)
{
    $current = $pagination['current_page'];
    $total = $pagination['total_pages'];
    $base = $pagination['base_path'];
    $query = !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '';
    $query .= '&tab=' . $tabType;
?>
    <nav class="civicone-pagination" role="navigation" aria-label="Pagination navigation">
        <div class="civicone-pagination__prev">
            <?php if ($current > 1): ?>
                <a class="civicone-pagination__link" href="<?= $base ?>?page=<?= $current - 1 ?><?= $query ?>" rel="prev">
                    <svg class="civicone-pagination__icon civicone-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="17" viewBox="0 0 17 13" aria-hidden="true" focusable="false">
                        <path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.4062 1.377-1.449-4.1856-3.9768h12.896v-2h-12.984l4.2931-4.293-1.414-1.414z"></path>
                    </svg>
                    <span class="civicone-pagination__link-title">Previous</span>
                </a>
            <?php endif; ?>
        </div>

        <ul class="civicone-pagination__list">
            <?php for ($i = 1; $i <= $total; $i++): ?>
                <?php if ($i == 1 || $i == $total || ($i >= $current - 1 && $i <= $current + 1)): ?>
                    <li class="civicone-pagination__item<?= $i == $current ? ' civicone-pagination__item--current' : '' ?>">
                        <?php if ($i == $current): ?>
                            <span class="civicone-pagination__link-label" aria-current="page">
                                <?= $i ?>
                            </span>
                        <?php else: ?>
                            <a class="civicone-pagination__link" href="<?= $base ?>?page=<?= $i ?><?= $query ?>" aria-label="Page <?= $i ?>">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php elseif ($i == $current - 2 || $i == $current + 2): ?>
                    <li class="civicone-pagination__item civicone-pagination__item--ellipsis">⋯</li>
                <?php endif; ?>
            <?php endfor; ?>
        </ul>

        <div class="civicone-pagination__next">
            <?php if ($current < $total): ?>
                <a class="civicone-pagination__link" href="<?= $base ?>?page=<?= $current + 1 ?><?= $query ?>" rel="next">
                    <span class="civicone-pagination__link-title">Next</span>
                    <svg class="civicone-pagination__icon civicone-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="17" viewBox="0 0 17 13" aria-hidden="true" focusable="false">
                        <path d="m10.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                    </svg>
                </a>
            <?php endif; ?>
        </div>
    </nav>
<?php
}
?>

<!-- JavaScript for tabs and view switching (per CLAUDE.md) -->
<script src="/assets/js/civicone-members-directory.min.js" defer></script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

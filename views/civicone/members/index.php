<?php
/**
 * CivicOne Members Directory - GOV.UK Frontend v5.14.0 Compliant
 * Template A: Directory/List Page (Section 10.2)
 * Following canonical GOV.UK Design System layout patterns
 *
 * v2.0.0 GOV.UK Polish Refactor (2026-01-24):
 * - ✅ Uses official GOV.UK Frontend v5.14.0 classes
 * - ✅ Proper govuk-grid-row/column layout
 * - ✅ Search bar always visible
 * - ✅ Tabs at top (prominent position)
 * - ✅ Mobile-first responsive design
 *
 * GOV.UK Compliance: Full (v5.14.0)
 */

// CivicOne layout header (provides govuk-width-container and govuk-main-wrapper)
require __DIR__ . '/../../layouts/civicone/header.php';

// Determine current tab from URL
$currentTab = $_GET['tab'] ?? 'all';

// Filter active members (last active within 5 minutes)
$activeMembers = array_filter($members, function($mem) {
    $lastActive = $mem['last_active_at'] ?? null;
    return $lastActive && (strtotime($lastActive) > strtotime('-5 minutes'));
});
?>

<!-- GOV.UK Breadcrumbs (Standard Navigation Pattern) -->
<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?? '' ?>/">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Members</li>
    </ol>
</nav>

<div class="govuk-grid-row">

    <div class="govuk-grid-column-full">
        <!-- Page Heading (GOV.UK Standard - Required <h1>) -->
        <h1 class="govuk-heading-xl">Members</h1>

        <!-- Lead Paragraph (Optional) -->
        <p class="govuk-body-l">Find and connect with community members across the network.</p>
    </div>
</div>

<div class="govuk-grid-row">

    <div class="govuk-grid-column-full">
        <!-- Search Bar (GOV.UK Form Group Pattern) -->
        <div class="govuk-form-group">
            <label class="govuk-label" for="member-search-main">
                Search by name or location
            </label>
            <div class="members-search-bar__wrapper">
                <input
                    type="text"
                    id="member-search-main"
                    name="q"
                    class="govuk-input"
                    placeholder="Search members..."
                    value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                    autocomplete="off"
                >
                <button
                    type="button"
                    class="govuk-button govuk-button--secondary members-search-bar__clear <?= empty($_GET['q']) ? 'govuk-visually-hidden' : '' ?>"
                    aria-label="Clear search"
                >
                    Clear
                </button>
            </div>
        </div>
    </div>
</div>

<div class="govuk-grid-row">

    <div class="govuk-grid-column-full">
        <!-- Selected Filters Tags (Always Visible) -->
        <?php if (!empty($_GET['q'])): ?>
        <div class="govuk-!-margin-bottom-4">
            <p class="govuk-body govuk-!-margin-bottom-2">
                <strong>Active filters:</strong>
                <a class="govuk-tag" href="<?= $basePath ?? '' ?>/members">
                    Search: <?= htmlspecialchars($_GET['q']) ?>
                    <span class="govuk-visually-hidden">(remove filter)</span>
                </a>
                <a class="govuk-link govuk-!-margin-left-2" href="<?= $basePath ?? '' ?>/members">
                    Clear all filters
                </a>
            </p>
        </div>
        <?php endif; ?>

        <!-- GOV.UK Tabs Component -->
        <div class="govuk-tabs" data-module="govuk-tabs">
            <h2 class="govuk-tabs__title">Contents</h2>
            <ul class="govuk-tabs__list" role="tablist">
                <li class="govuk-tabs__list-item<?= $currentTab === 'all' ? ' govuk-tabs__list-item--selected' : '' ?>" role="presentation">
                    <a class="govuk-tabs__tab" href="#all-members" id="tab_all-members" role="tab" aria-controls="all-members" <?= $currentTab === 'all' ? 'aria-selected="true"' : 'aria-selected="false" tabindex="-1"' ?>>
                        All members (<?= $total_members ?? count($members) ?>)
                    </a>
                </li>
                <li class="govuk-tabs__list-item<?= $currentTab === 'active' ? ' govuk-tabs__list-item--selected' : '' ?>" role="presentation">
                    <a class="govuk-tabs__tab" href="#active-members" id="tab_active-members" role="tab" aria-controls="active-members" <?= $currentTab === 'active' ? 'aria-selected="true"' : 'aria-selected="false" tabindex="-1"' ?>>
                        Active now (<?= count($activeMembers) ?>)
                    </a>
                </li>
            </ul>

            <!-- Tab Panel: All Members -->
            <div class="govuk-tabs__panel<?= $currentTab === 'all' ? '' : ' govuk-tabs__panel--hidden' ?>" id="all-members" role="tabpanel" aria-labelledby="tab_all-members">
                <?php renderMembersContent($members, 'all', $total_members ?? count($members), $pagination ?? null, $basePath ?? ''); ?>
            </div>

            <!-- Tab Panel: Active Members -->
            <div class="govuk-tabs__panel<?= $currentTab === 'active' ? '' : ' govuk-tabs__panel--hidden' ?>" id="active-members" role="tabpanel" aria-labelledby="tab_active-members">
                <?php renderMembersContent($activeMembers, 'active', $total_members ?? count($members), $pagination ?? null, $basePath ?? ''); ?>
            </div>
        </div><!-- /.govuk-tabs -->
    </div><!-- /.govuk-grid-column-full -->
</div><!-- /.govuk-grid-row -->

<?php
/**
 * Renders tab panel content: results list only
 * Filter panel is now at page level (single instance)
 */
function renderMembersContent($members, $tabType, $total_members, $pagination = null, $basePath = '')
{
?>
                <!-- Results Header -->
                <p class="govuk-body govuk-!-margin-bottom-4">
                    Showing <strong><?= count($members) ?></strong> of <strong><?= $total_members ?? count($members) ?></strong> members
                </p>

                <!-- Member List (GOV.UK Summary List Pattern) -->
                <?php if (!empty($members)): ?>
                <ul class="govuk-list" role="list">
                    <?php foreach ($members as $mem): ?>
                        <?= render_member_list_item($mem) ?>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <!-- Empty State (GOV.UK Inset Text Pattern) -->
                <div class="govuk-inset-text">
                    <h3 class="govuk-heading-m">No members found</h3>
                    <p class="govuk-body">Try adjusting your search or check back later.</p>
                </div>
                <?php endif; ?>

                <!-- GOV.UK Pagination Component (v1.3.0) -->
                <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                    <?php renderGovukPagination($pagination, $tabType); ?>
                <?php endif; ?>
<?php
}

/**
 * Renders a single member as a list item
 * Following GOV.UK patterns for accessible directory listings
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
    <li class="govuk-!-margin-bottom-4 govuk-!-padding-bottom-4" style="border-bottom: 1px solid #b1b4b6; display: flex; align-items: center; gap: 1rem;">
        <div style="flex-shrink: 0;">
            <?php if ($hasAvatar): ?>
                <img src="<?= htmlspecialchars($mem['avatar_url']) ?>" alt="" width="48" height="48" style="border-radius: 50%;">
            <?php else: ?>
                <div class="civicone-panel-bg" style="width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#505a5f" stroke-width="1.5" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
            <?php endif; ?>
        </div>

        <div style="flex-grow: 1;">
            <h3 class="govuk-heading-s govuk-!-margin-bottom-1">
                <a href="<?= $basePath ?>/profile/<?= $mem['id'] ?>" class="govuk-link">
                    <?= $displayName ?>
                </a>
                <?php if ($isMemberOnline): ?>
                    <strong class="govuk-tag govuk-tag--green govuk-!-margin-left-2">Online</strong>
                <?php endif; ?>
            </h3>
            <?php if ($location): ?>
                <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">
                    <?= $location ?>
                </p>
            <?php endif; ?>
        </div>

        <div style="flex-shrink: 0;">
            <a href="<?= $basePath ?>/profile/<?= $mem['id'] ?>" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0">
                View profile
            </a>
        </div>
    </li>
<?php
    return ob_get_clean();
}

/**
 * Renders GOV.UK Pagination Component (v5.14.0)
 */
function renderGovukPagination($pagination, $tabType)
{
    $current = $pagination['current_page'];
    $total = $pagination['total_pages'];
    $base = $pagination['base_path'];
    $query = !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '';
    $query .= '&tab=' . $tabType;
?>
    <nav class="govuk-pagination" role="navigation" aria-label="Pagination">
        <?php if ($current > 1): ?>
        <div class="govuk-pagination__prev">
            <a class="govuk-link govuk-pagination__link" href="<?= $base ?>?page=<?= $current - 1 ?><?= $query ?>" rel="prev">
                <svg class="govuk-pagination__icon govuk-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                    <path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.4062 1.377-1.449-4.1856-3.9768h12.896v-2h-12.984l4.2931-4.293-1.414-1.414z"></path>
                </svg>
                <span class="govuk-pagination__link-title">Previous<span class="govuk-visually-hidden"> page</span></span>
            </a>
        </div>
        <?php endif; ?>

        <ul class="govuk-pagination__list">
            <?php for ($i = 1; $i <= $total; $i++): ?>
                <?php if ($i == 1 || $i == $total || ($i >= $current - 1 && $i <= $current + 1)): ?>
                    <li class="govuk-pagination__item<?= $i == $current ? ' govuk-pagination__item--current' : '' ?>">
                        <?php if ($i == $current): ?>
                            <a class="govuk-link govuk-pagination__link" href="<?= $base ?>?page=<?= $i ?><?= $query ?>" aria-label="Page <?= $i ?>" aria-current="page">
                                <?= $i ?>
                            </a>
                        <?php else: ?>
                            <a class="govuk-link govuk-pagination__link" href="<?= $base ?>?page=<?= $i ?><?= $query ?>" aria-label="Page <?= $i ?>">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php elseif ($i == $current - 2 || $i == $current + 2): ?>
                    <li class="govuk-pagination__item govuk-pagination__item--ellipses">&ctdot;</li>
                <?php endif; ?>
            <?php endfor; ?>
        </ul>

        <?php if ($current < $total): ?>
        <div class="govuk-pagination__next">
            <a class="govuk-link govuk-pagination__link" href="<?= $base ?>?page=<?= $current + 1 ?><?= $query ?>" rel="next">
                <span class="govuk-pagination__link-title">Next<span class="govuk-visually-hidden"> page</span></span>
                <svg class="govuk-pagination__icon govuk-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                    <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
                </svg>
            </a>
        </div>
        <?php endif; ?>
    </nav>
<?php
}
?>

<!-- JavaScript for tabs and view switching (per CLAUDE.md) -->
<script src="/assets/js/civicone-members-directory.min.js" defer></script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

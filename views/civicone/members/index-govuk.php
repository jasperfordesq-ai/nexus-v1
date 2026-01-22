<?php
/**
 * CivicOne Members Directory - GOV.UK Refactored Version
 * Proof of Concept using GOV.UK Component Library
 *
 * Template A: Directory/List Page (Section 10.2)
 * With Page Hero (Section 9C: Page Hero Contract)
 *
 * BEFORE: Used custom civicone-* classes with arbitrary spacing/colors
 * AFTER: Uses govuk-* classes from GOV.UK Design System with design tokens
 */

// CivicOne layout header
require __DIR__ . '/../../layouts/civicone/header.php';

// Load GOV.UK component helpers
require __DIR__ . '/../components/govuk/button.php';
require __DIR__ . '/../components/govuk/form-input.php';
require __DIR__ . '/../components/govuk/card.php';
require __DIR__ . '/../components/govuk/tag.php';
?>

<!-- GOV.UK Page Template Boilerplate - now using .civicone--govuk scope -->
<div class="civicone--govuk govuk-width-container">
    <main class="govuk-main-wrapper" id="main-content">

        <!-- Hero (auto-resolves from config/heroes.php for /members route) -->
        <?php require __DIR__ . '/../../layouts/civicone/partials/render-hero.php'; ?>

        <!-- MOJ Filter Pattern: 1/4 Filters + 3/4 Results -->
        <div class="govuk-grid-row">

            <!-- Filters Panel (1/4) -->
            <div class="govuk-grid-column-one-quarter">
                <div class="civicone-filter-panel" role="search" aria-label="Filter members">

                    <h2 class="govuk-heading-m">Filter members</h2>

                    <div class="govuk-form-group">
                        <label for="member-search" class="govuk-label">
                            Search by name or location
                        </label>
                        <div class="civicone-search-wrapper">
                            <input
                                type="text"
                                id="member-search"
                                name="q"
                                class="govuk-input civicone-search-input"
                                placeholder="Enter name or location..."
                                value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                            >
                            <span class="civicone-search-icon" aria-hidden="true"></span>
                            <div id="search-spinner" class="civicone-spinner civicone-spinner--hidden" aria-live="polite" aria-label="Searching"></div>
                        </div>
                    </div>

                    <!-- Selected Filters (shown when filters are active) -->
                    <?php if (!empty($_GET['q'])): ?>
                    <div class="civicone-selected-filters">
                        <h3 class="govuk-heading-s">Active filters</h3>
                        <div class="civicone-filter-tags">
                            <a href="<?= $basePath ?? '' ?>/members" class="govuk-tag govuk-tag--grey civicone-tag--removable">
                                Search: <?= htmlspecialchars($_GET['q']) ?>
                                <span class="civicone-tag-remove" aria-label="Remove filter">×</span>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Results Panel (3/4) -->
            <div class="govuk-grid-column-two-thirds">

                <!-- Results Header with Count -->
                <div class="civicone-results-header">
                    <p class="govuk-body" id="results-count">
                        Showing <strong><?= count($members) ?></strong> of <strong><?= $total_members ?? count($members) ?></strong> members
                    </p>
                </div>

                <!-- Results List (NOT a card grid) -->
                <ul class="civicone-results-list" id="members-list" role="list">
                    <?php foreach ($members as $mem): ?>
                        <?= render_member_list_item_govuk($mem) ?>
                    <?php endforeach; ?>
                </ul>

                <!-- Empty State -->
                <div class="civicone-empty-state" id="empty-state" style="<?= !empty($members) ? 'display: none;' : '' ?>">
                    <svg class="civicone-empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <h2 class="govuk-heading-m">No members found</h2>
                    <p class="govuk-body">Try adjusting your search or check back later.</p>
                </div>

                <!-- Pagination -->
                <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
                    <nav class="civicone-pagination" aria-label="Member list pagination">
                        <?php
                        $current = $pagination['current_page'];
                        $total = $pagination['total_pages'];
                        $base = $pagination['base_path'];
                        $range = 2;
                        $query = !empty($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '';
                        ?>

                        <div class="civicone-pagination__results">
                            <p class="govuk-body-s">
                                Showing <?= (($current - 1) * 20 + 1) ?> to <?= min($current * 20, $total_members ?? count($members)) ?> of <?= $total_members ?? count($members) ?> results
                            </p>
                        </div>

                        <ul class="civicone-pagination__list">
                            <?php if ($current > 1): ?>
                                <li class="civicone-pagination__item civicone-pagination__item--prev">
                                    <a href="<?= $base ?>?page=<?= $current - 1 ?><?= $query ?>" class="govuk-link" aria-label="Go to previous page">
                                        <span aria-hidden="true">‹</span> Previous
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total; $i++): ?>
                                <?php if ($i == 1 || $i == $total || ($i >= $current - $range && $i <= $current + $range)): ?>
                                    <li class="civicone-pagination__item">
                                        <?php if ($i == $current): ?>
                                            <span class="civicone-pagination__link civicone-pagination__link--current" aria-current="page">
                                                <?= $i ?>
                                            </span>
                                        <?php else: ?>
                                            <a href="<?= $base ?>?page=<?= $i ?><?= $query ?>" class="govuk-link" aria-label="Go to page <?= $i ?>">
                                                <?= $i ?>
                                            </a>
                                        <?php endif; ?>
                                    </li>
                                <?php elseif ($i == $current - $range - 1 || $i == $current + $range + 1): ?>
                                    <li class="civicone-pagination__item civicone-pagination__item--ellipsis" aria-hidden="true">
                                        <span>⋯</span>
                                    </li>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($current < $total): ?>
                                <li class="civicone-pagination__item civicone-pagination__item--next">
                                    <a href="<?= $base ?>?page=<?= $current + 1 ?><?= $query ?>" class="govuk-link" aria-label="Go to next page">
                                        Next <span aria-hidden="true">›</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            </div><!-- /two-thirds -->
        </div><!-- /grid-row -->

    </main>
</div><!-- /width-container -->

<?php
/**
 * Renders a single member as a list item using GOV.UK classes
 * Following MOJ/GOV.UK patterns for accessible directory listings
 *
 * KEY CHANGES FROM ORIGINAL:
 * - civicone-link → govuk-link (GOV.UK focus states)
 * - civicone-button → govuk-button (GOV.UK button styles)
 * - Custom spacing → GOV.UK spacing scale
 * - Arbitrary colors → GOV.UK color palette
 */
function render_member_list_item_govuk($mem)
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
            <h3 class="govuk-heading-s civicone-member-item__name">
                <a href="<?= $basePath ?>/profile/<?= $mem['id'] ?>" class="govuk-link">
                    <?= $displayName ?>
                </a>
            </h3>
            <?php if ($location): ?>
                <p class="govuk-body-s civicone-member-item__meta">
                    <svg class="civicone-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <?= $location ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="civicone-member-item__actions">
            <?php
            // Using GOV.UK button component helper
            echo civicone_govuk_button([
                'text' => 'View profile',
                'type' => 'secondary',
                'href' => $basePath . '/profile/' . $mem['id']
            ]);
            ?>
        </div>
    </li>
<?php
    return ob_get_clean();
}
?>

<!-- AJAX search functionality loaded from external file per CLAUDE.md -->
<script src="<?= $basePath ?? '' ?>/assets/js/civicone-members-directory.min.js" defer></script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

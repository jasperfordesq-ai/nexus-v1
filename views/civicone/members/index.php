<?php
/**
 * CivicOne Members Directory
 * Template A: Directory/List Page (Section 10.2)
 * With Page Hero (Section 9C: Page Hero Contract)
 */

// CivicOne layout header
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<!-- GOV.UK Page Template Boilerplate (Section 10.0) -->
<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">

        <!-- Hero (auto-resolves from config/heroes.php for /members route) -->
        <?php require __DIR__ . '/../../layouts/civicone/partials/render-hero.php'; ?>

        <!-- MOJ Filter Pattern: 1/3 Filters + 2/3 Results -->
        <div class="civicone-grid-row">

            <!-- Filters Panel (1/3) -->
            <div class="civicone-grid-column-one-third">
                <div class="civicone-filter-panel" role="search" aria-label="Filter members">

                    <div class="civicone-filter-header">
                        <h2 class="civicone-heading-m">Filter members</h2>
                    </div>

                    <div class="civicone-filter-group">
                        <label for="member-search" class="civicone-label">
                            Search by name or location
                        </label>
                        <div class="civicone-search-wrapper">
                            <input
                                type="text"
                                id="member-search"
                                name="q"
                                class="civicone-input civicone-search-input"
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
                        <h3 class="civicone-heading-s">Active filters</h3>
                        <div class="civicone-filter-tags">
                            <a href="<?= $basePath ?? '' ?>/members" class="civicone-tag civicone-tag--removable">
                                Search: <?= htmlspecialchars($_GET['q']) ?>
                                <span class="civicone-tag-remove" aria-label="Remove filter">×</span>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Results Panel (2/3) -->
            <div class="civicone-grid-column-two-thirds">

                <!-- Results Header with Count -->
                <div class="civicone-results-header">
                    <p class="civicone-results-count" id="results-count">
                        Showing <strong><?= count($members) ?></strong> of <strong><?= $total_members ?? count($members) ?></strong> members
                    </p>
                </div>

                <!-- Results List (NOT a card grid) -->
                <ul class="civicone-results-list" id="members-list" role="list">
                    <?php foreach ($members as $mem): ?>
                        <?= render_member_list_item($mem) ?>
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
                    <h2 class="civicone-heading-m">No members found</h2>
                    <p class="civicone-body">Try adjusting your search or check back later.</p>
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
                            Showing <?= (($current - 1) * 20 + 1) ?> to <?= min($current * 20, $total_members ?? count($members)) ?> of <?= $total_members ?? count($members) ?> results
                        </div>

                        <ul class="civicone-pagination__list">
                            <?php if ($current > 1): ?>
                                <li class="civicone-pagination__item civicone-pagination__item--prev">
                                    <a href="<?= $base ?>?page=<?= $current - 1 ?><?= $query ?>" class="civicone-pagination__link" aria-label="Go to previous page">
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
                                            <a href="<?= $base ?>?page=<?= $i ?><?= $query ?>" class="civicone-pagination__link" aria-label="Go to page <?= $i ?>">
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
                                    <a href="<?= $base ?>?page=<?= $current + 1 ?><?= $query ?>" class="civicone-pagination__link" aria-label="Go to next page">
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
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('member-search');
    const membersList = document.getElementById('members-list');
    const emptyState = document.getElementById('empty-state');
    const resultsCount = document.getElementById('results-count');
    const spinner = document.getElementById('search-spinner');
    let debounceTimer;

    if (!searchInput) return;

    searchInput.addEventListener('input', function(e) {
        clearTimeout(debounceTimer);
        const query = e.target.value.trim();

        spinner.style.display = 'block';

        debounceTimer = setTimeout(() => {
            if (query.length === 0) {
                window.location.href = basePath + '/members';
                return;
            }
            fetchMembers(query);
        }, 400);
    });

    function fetchMembers(query) {
        fetch(basePath + '/members?q=' + encodeURIComponent(query), {
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                renderList(data.data);
                updateResultsCount(data.data.length, data.total || data.data.length);
                spinner.style.display = 'none';
            })
            .catch(err => {
                console.error('Search error:', err);
                spinner.style.display = 'none';
            });
    }

    function renderList(members) {
        membersList.innerHTML = '';

        if (members.length === 0) {
            emptyState.style.display = 'block';
            return;
        }
        emptyState.style.display = 'none';

        members.forEach(member => {
            const li = document.createElement('li');
            li.className = 'civicone-member-item';

            const name = escapeHtml(member.first_name + ' ' + member.last_name);
            const location = member.location ? escapeHtml(member.location) : '';

            // Check online status - active within 5 minutes
            const isOnline = member.last_active_at && (new Date(member.last_active_at) > new Date(Date.now() - 5 * 60 * 1000));
            const onlineIndicator = isOnline ? '<span class="civicone-status-indicator civicone-status-indicator--online" title="Active now" aria-label="Currently online"></span>' : '';

            // Avatar HTML
            let avatarHtml = '';
            if (member.avatar_url) {
                avatarHtml = `<img src="${escapeHtml(member.avatar_url)}" alt="" class="civicone-avatar">`;
            } else {
                avatarHtml = `
                    <div class="civicone-avatar civicone-avatar--placeholder">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>`;
            }

            // Location HTML
            let locationHtml = '';
            if (location) {
                locationHtml = `
                    <p class="civicone-member-item__meta">
                        <svg class="civicone-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        ${location}
                    </p>`;
            }

            li.innerHTML = `
                <div class="civicone-member-item__avatar">
                    ${avatarHtml}
                    ${onlineIndicator}
                </div>
                <div class="civicone-member-item__content">
                    <h3 class="civicone-member-item__name">
                        <a href="${basePath}/profile/${member.id}" class="civicone-link">
                            ${name}
                        </a>
                    </h3>
                    ${locationHtml}
                </div>
                <div class="civicone-member-item__actions">
                    <a href="${basePath}/profile/${member.id}" class="civicone-button civicone-button--secondary">
                        View profile
                    </a>
                </div>
            `;

            membersList.appendChild(li);
        });
    }

    function updateResultsCount(showing, total) {
        resultsCount.innerHTML = `Showing <strong>${showing}</strong> of <strong>${total}</strong> members`;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) {
            return map[m];
        });
    }
});
</script>

    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

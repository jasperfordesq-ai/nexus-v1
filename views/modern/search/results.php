<?php
// Phoenix Search Results View (Restored Legacy "Glass Panel" Design)
$hero_title = "Search Results";
$hero_subtitle = 'Showing results for "' . htmlspecialchars($query) . '"';
$hero_gradient = 'htb-hero-gradient-purple';

// Ensure Layout Header
require __DIR__ . '/../../layouts/modern/header.php';
?>

<div class="htb-search-results-wrapper" role="region" aria-label="Search results">
<div class="htb-container htb-container-full">

    <div class="search-results-container">

        <header class="search-results-header">
            <div>
                <h1 class="search-results-title">Search Results</h1>

                <?php if (!empty($corrected_query) && $corrected_query !== $query): ?>
                    <p class="search-results-subtitle">
                        Showing results for "<strong><?= htmlspecialchars($corrected_query) ?></strong>"
                        <span class="search-results-correction">
                            (corrected from "<?= htmlspecialchars($query) ?>")
                        </span>
                    </p>
                <?php else: ?>
                    <p class="search-results-subtitle">Found <?= count($results) ?> matches for "<strong><?= htmlspecialchars($query) ?></strong>"</p>
                <?php endif; ?>

                <?php if (!empty($intent) && !empty($intent['ai_analyzed'])): ?>
                    <p class="search-ai-badge">
                        <span class="search-ai-tag">
                            AI-Enhanced Search
                        </span>
                        <?php if (!empty($intent['location'])): ?>
                            <span class="search-location-tag">📍 <?= htmlspecialchars($intent['location']) ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Filter Tabs -->
            <?php if (!empty($results)): ?>
                <div class="search-filter-tabs">
                    <button onclick="filterSearch('all')" class="search-filter-tab active" data-filter="all">All</button>
                    <button onclick="filterSearch('user')" class="search-filter-tab" data-filter="user">People</button>
                    <button onclick="filterSearch('group')" class="search-filter-tab" data-filter="group">Hubs</button>
                    <button onclick="filterSearch('listing')" class="search-filter-tab" data-filter="listing">Offers & Requests</button>
                </div>
            <?php endif; ?>
        </header>

        <?php if (empty($results)): ?>
            <div class="htb-card search-empty-state">
                <div class="search-empty-icon">🔍</div>
                <h3 class="search-empty-title">No results found</h3>
                <p class="search-empty-text">We couldn't find anything matching "<?= htmlspecialchars($query) ?>". Try different keywords.</p>
                <div class="search-empty-actions">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings" class="htb-btn htb-btn-primary">Browse Listings</a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups" class="htb-btn htb-btn-secondary">Find Hubs</a>
                </div>
            </div>
        <?php else: ?>

            <!-- Empty State Message (Hidden by default) -->
            <div id="no-filter-results" class="search-no-filter-results hidden">
                <p class="search-no-filter-text">No results found in this category.</p>
            </div>

            <div class="search-results-grid">
                <?php foreach ($results as $item): ?>
                    <?php
                    // Logic from Legacy View: Calculate URLs manually since SearchService doesn't provide them
                    $icon = 'admin-post';
                    $color = 'gray';
                    $url = '#';

                    switch ($item['type']) {
                        case 'user':
                            $icon = 'admin-users';
                            $color = 'blue';
                            $url = Nexus\Core\TenantContext::getBasePath() . '/profile/' . $item['id'];
                            break;
                        case 'listing':
                            $icon = 'list-view';
                            $color = 'green';
                            $url = Nexus\Core\TenantContext::getBasePath() . '/listings/' . $item['id'];
                            break;
                        case 'group':
                            $icon = 'groups';
                            $color = 'purple';
                            $url = Nexus\Core\TenantContext::getBasePath() . '/groups/' . $item['id'];
                            break;
                        case 'page':
                            $icon = 'media-document';
                            $color = 'orange';
                            $url = Nexus\Core\TenantContext::getBasePath() . '/pages/' . $item['id'];
                            break;
                    }
                    ?>

                    <a href="<?= $url ?>" class="htb-card search-result-card" data-type="<?= $item['type'] ?>">
                        <div class="htb-card-body search-result-body">
                            <!-- Icon/Image -->
                            <div class="search-result-icon-wrapper">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="<?= htmlspecialchars($item['image']) ?>" loading="lazy" class="search-result-image" alt="">
                                <?php else: ?>
                                    <div class="search-result-icon-placeholder">
                                        <span class="dashicons dashicons-<?= $icon ?> search-result-icon search-result-icon-<?= $color ?>"></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Content -->
                            <div class="search-result-content">
                                <div class="search-result-meta">
                                    <span class="htb-badge search-result-type-badge search-badge-<?= $color ?>"><?= strtoupper($item['type']) ?></span>

                                    <?php if (isset($item['relevance_score']) && $item['relevance_score'] > 0.7): ?>
                                        <span class="search-result-match-badge">
                                            ⭐ High Match
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($item['location'])): ?>
                                        <span class="search-result-location">
                                            📍 <?= htmlspecialchars($item['location']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="search-result-title">
                                    <?= htmlspecialchars($item['title']) ?>
                                </h3>
                                <?php if (!empty($item['description'])): ?>
                                    <p class="search-result-description">
                                        <?= htmlspecialchars(strip_tags($item['description'])) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="search-result-arrow">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>
</div>

<script>
    function filterSearch(type) {
        // 1. Update Tabs
        const tabs = document.querySelectorAll('.search-filter-tab');
        tabs.forEach(t => {
            t.classList.remove('active');
        });

        // Find clicked tab
        const clicked = document.querySelector(`.search-filter-tab[data-filter="${type}"]`);
        if (clicked) {
            clicked.classList.add('active');
        }

        // 2. Filter Items and Count
        const items = document.querySelectorAll('.search-result-card');
        const emptyMsg = document.getElementById('no-filter-results');
        let visibleCount = 0;

        items.forEach(item => {
            if (type === 'all' || item.getAttribute('data-type') === type) {
                item.classList.remove('hidden');
                visibleCount++;
            } else {
                item.classList.add('hidden');
            }
        });

        // 3. Toggle Empty Message
        if (visibleCount === 0) {
            emptyMsg.classList.remove('hidden');
            emptyMsg.querySelector('p').textContent = 'No ' + (type === 'all' ? 'results' : type + 's') + ' found matching your search.';
        } else {
            emptyMsg.classList.add('hidden');
        }
    }
</script>


<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>

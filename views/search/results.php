<?php
$layout = layout(); // Fixed: centralized detection

// Fallback if layout doesn't exist
if (!file_exists(__DIR__ . '/../layouts/' . $layout . '/header.php')) {
    $layout = 'modern';
}
include __DIR__ . '/../layouts/' . $layout . '/header.php';
?>

<main class="htb-container htb-section search-results-main">

    <!-- CENTRAL GLASS PANEL -->
    <div class="htb-glass-panel search-glass-panel">

        <header class="search-header">
            <div>
                <h1 class="search-title">Search Results</h1>
                <p class="search-subtitle">Found <?= count($results) ?> matches for "<strong><?= htmlspecialchars($query) ?></strong>"</p>
            </div>

            <!-- Filter Tabs -->
            <?php if (!empty($results)): ?>
                <div class="htb-search-tabs search-tabs">
                    <button onclick="filterSearch('all')" class="htb-tab search-tab active" data-filter="all">All</button>
                    <button onclick="filterSearch('user')" class="htb-tab search-tab" data-filter="user">People</button>
                    <button onclick="filterSearch('group')" class="htb-tab search-tab" data-filter="group">Groups</button>
                    <button onclick="filterSearch('listing')" class="htb-tab search-tab" data-filter="listing">Offers & Requests</button>
                </div>
            <?php endif; ?>
        </header>

        <?php if (empty($results)): ?>
            <div class="search-empty">
                <div class="search-empty-icon">
                    <span class="dashicons dashicons-search"></span>
                </div>
                <h3 class="search-empty-title">No results found</h3>
                <p class="search-empty-text">We couldn't find anything for "<?= htmlspecialchars($query) ?>". Try different keywords or browse our directory.</p>
                <div class="search-empty-actions">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings" class="htb-btn htb-btn-primary">Browse Listings</a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups" class="htb-btn htb-btn-secondary">Find Groups</a>
                </div>
            </div>
        <?php else: ?>
            <div class="htb-grid search-results-grid">
                <?php foreach ($results as $item): ?>
                    <?php
                    // Contextual Icon & Color
                    $icon = 'admin-post';
                    $color = 'gray';
                    $url = '/';

                    switch ($item['type']) {
                        case 'user':
                            $icon = 'admin-users';
                            $color = 'blue';
                            $url = '/profile/' . $item['id'];
                            break;
                        case 'listing':
                            $icon = 'list-view';
                            $color = 'green';
                            $url = '/listings/' . $item['id'];
                            break;
                        case 'group':
                            $icon = 'groups';
                            $color = 'purple';
                            $url = '/groups/' . $item['id'];
                            break;
                        case 'page':
                            $icon = 'media-document';
                            $color = 'orange';
                            $url = '/pages/' . $item['id'];
                            break;
                    }
                    ?>
                    <a href="<?= $url ?>" class="htb-card htb-search-result search-result-card" data-type="<?= $item['type'] ?>">
                        <div class="htb-card-body search-result-body">
                            <!-- Icon/Image -->
                            <div class="search-result-icon-wrap">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="<?= htmlspecialchars($item['image']) ?>" class="search-result-image" alt="">
                                <?php else: ?>
                                    <div class="search-result-icon search-result-icon--<?= $color ?>">
                                        <span class="dashicons dashicons-<?= $icon ?>"></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Content -->
                            <div class="search-result-content">
                                <div class="search-result-header">
                                    <span class="htb-badge search-result-badge search-result-badge--<?= $color ?>"><?= strtoupper($item['type']) ?></span>
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
    <!-- END GLASS PANEL -->

</main>

<script>
    function filterSearch(type) {
        // 1. Update Tabs
        const tabs = document.querySelectorAll('.search-tab');
        tabs.forEach(function(t) {
            t.classList.remove('active');
        });

        // Find clicked tab and activate
        const clicked = document.querySelector('.search-tab[data-filter="' + type + '"]');
        if (clicked) {
            clicked.classList.add('active');
        }

        // 2. Filter Items
        const items = document.querySelectorAll('.search-result-card');
        items.forEach(function(item) {
            if (type === 'all' || item.getAttribute('data-type') === type) {
                item.classList.remove('hidden');
            } else {
                item.classList.add('hidden');
            }
        });
    }
</script>

<?php include __DIR__ . '/../layouts/' . $layout . '/footer.php'; ?>

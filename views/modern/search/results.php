<?php
// Phoenix Search Results View (Restored Legacy "Glass Panel" Design)
$hero_title = "Search Results";
$hero_subtitle = 'Showing results for "' . htmlspecialchars($query) . '"';
$hero_gradient = 'htb-hero-gradient-purple';

// Ensure Layout Header
require __DIR__ . '/../../layouts/modern/header.php';
?>

<main id="main-content" role="main" aria-label="Search results">
<div class="htb-container htb-container-full">

    <div style="margin-top: 40px; margin-bottom: 60px;">

        <header style="margin-bottom: 30px; border-bottom: 2px solid rgba(255,255,255,0.1); padding-bottom: 20px; display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1 style="margin: 0; font-size: 2rem; font-weight: 800; color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">Search Results</h1>

                <?php if (!empty($corrected_query) && $corrected_query !== $query): ?>
                    <p style="margin: 5px 0 0; color: rgba(255,255,255,0.9); font-size: 1rem;">
                        Showing results for "<strong><?= htmlspecialchars($corrected_query) ?></strong>"
                        <span style="opacity: 0.7; font-size: 0.9rem; margin-left: 10px;">
                            (corrected from "<?= htmlspecialchars($query) ?>")
                        </span>
                    </p>
                <?php else: ?>
                    <p style="margin: 5px 0 0; color: rgba(255,255,255,0.9); font-size: 1rem;">Found <?= count($results) ?> matches for "<strong><?= htmlspecialchars($query) ?></strong>"</p>
                <?php endif; ?>

                <?php if (!empty($intent) && !empty($intent['ai_analyzed'])): ?>
                    <p style="margin: 8px 0 0; color: rgba(255,255,255,0.7); font-size: 0.85rem;">
                        <span style="background: rgba(99, 102, 241, 0.2); padding: 2px 8px; border-radius: 4px; font-weight: 600;">
                            AI-Enhanced Search
                        </span>
                        <?php if (!empty($intent['location'])): ?>
                            <span style="margin-left: 8px;">📍 <?= htmlspecialchars($intent['location']) ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Filter Tabs -->
            <?php if (!empty($results)): ?>
                <div class="htb-search-tabs" style="display: flex; gap: 5px; background: white; padding: 5px; border-radius: 12px; flex-wrap: wrap; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <button onclick="filterSearch('all')" class="htb-tab active" data-filter="all" style="border:none; cursor:pointer; padding: 6px 16px; border-radius: 8px; background: #eff6ff; color: var(--htb-primary); font-weight: 700;">All</button>
                    <button onclick="filterSearch('user')" class="htb-tab" data-filter="user" style="border:none; cursor:pointer; padding: 6px 16px; border-radius: 8px; background: transparent; color: #6b7280; font-weight: 600; transition: 0.2s;">People</button>
                    <button onclick="filterSearch('group')" class="htb-tab" data-filter="group" style="border:none; cursor:pointer; padding: 6px 16px; border-radius: 8px; background: transparent; color: #6b7280; font-weight: 600; transition: 0.2s;">Hubs</button>
                    <button onclick="filterSearch('listing')" class="htb-tab" data-filter="listing" style="border:none; cursor:pointer; padding: 6px 16px; border-radius: 8px; background: transparent; color: #6b7280; font-weight: 600; transition: 0.2s;">Offers & Requests</button>
                </div>
            <?php endif; ?>
        </header>

        <?php if (empty($results)): ?>
            <div class="htb-card" style="text-align: center; padding: 60px;">
                <div style="font-size: 3rem; margin-bottom: 20px;">🔍</div>
                <h3 style="margin-bottom: 10px; color: var(--htb-text-main);">No results found</h3>
                <p style="color: var(--htb-text-muted);">We couldn't find anything matching "<?= htmlspecialchars($query) ?>". Try different keywords.</p>
                <div style="margin-top: 30px; display: flex; gap: 10px; justify-content: center;">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings" class="htb-btn htb-btn-primary">Browse Listings</a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups" class="htb-btn htb-btn-secondary">Find Hubs</a>
                </div>
            </div>
        <?php else: ?>

            <!-- Empty State Message (Hidden by default) -->
            <div id="no-filter-results" style="display: none; text-align: center; padding: 40px; background: rgba(255,255,255,0.9); border-radius: 12px; margin-bottom: 20px;">
                <p style="color: #64748b; font-size: 1.1rem; font-weight: 500;">No results found in this category.</p>
            </div>

            <div class="htb-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
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
                            $url = Nexus\Core\TenantContext::getBasePath() . '/pages/' . $item['id']; // Assuming pages are by ID, or slug? Service returns ID.
                            // If page is by slug, we might have an issue, but standard pages are mostly static.
                            // Custom pages use slug. SearchService.php returns ID. 
                            // Legacy code used '/pages/' . $item['id']. Let's stick to that for now.
                            break;
                    }
                    ?>

                    <a href="<?= $url ?>" class="htb-card htb-search-result" data-type="<?= $item['type'] ?>" style="text-decoration: none; color: inherit; transition: all 0.2s ease; position: relative; overflow: hidden; padding: 20px; border: 1px solid rgba(0,0,0,0.05); background: white;">
                        <div class="htb-card-body" style="display: flex; align-items: flex-start; gap: 15px; padding: 0 !important;">
                            <!-- Icon/Image -->
                            <div style="flex-shrink: 0;">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="<?= htmlspecialchars($item['image']) ?>" loading="lazy" style="width: 50px; height: 50px; border-radius: 12px; object-fit: cover; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; border-radius: 12px; background: #f3f4f6; display: flex; align-items: center; justify-content: center;">
                                        <!-- Using FontAwesome instead of Dashicons for consistency if available, but staying safe with Dashicons as fallback or text -->
                                        <span class="dashicons dashicons-<?= $icon ?>" style="color: var(--htb-<?= $color ?>-600); font-size: 24px;"></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Content -->
                            <div style="flex-grow: 1; min-width: 0;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; gap: 8px;">
                                    <span class="htb-badge" style="font-size: 0.65rem; padding: 3px 8px; border-radius: 6px; text-transform: uppercase; font-weight: 700; background: var(--htb-<?= $color ?>-100); color: var(--htb-<?= $color ?>-700);"><?= strtoupper($item['type']) ?></span>

                                    <?php if (isset($item['relevance_score']) && $item['relevance_score'] > 0.7): ?>
                                        <span style="font-size: 0.65rem; padding: 3px 8px; border-radius: 6px; background: rgba(16, 185, 129, 0.1); color: #059669; font-weight: 600;">
                                            ⭐ High Match
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($item['location'])): ?>
                                        <span style="font-size: 0.75rem; color: #6b7280;">
                                            📍 <?= htmlspecialchars($item['location']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3 style="margin: 0 0 4px; font-size: 1.1rem; font-weight: 700; color: #111827; line-height: 1.3;">
                                    <?= htmlspecialchars($item['title']) ?>
                                </h3>
                                <?php if (!empty($item['description'])): ?>
                                    <p style="margin: 0; font-size: 0.9rem; color: #6b7280; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5;">
                                        <?= htmlspecialchars(strip_tags($item['description'])) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="position: absolute; right: 20px; bottom: 20px; opacity: 0; transform: translateX(-10px); transition: all 0.2s;" class="arrow-indicator">
                            <span class="dashicons dashicons-arrow-right-alt2" style="color: var(--htb-primary);"></span>
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
        const tabs = document.querySelectorAll('.htb-tab');
        tabs.forEach(t => {
            t.classList.remove('active');
            t.style.background = 'transparent';
            t.style.color = '#6b7280';
            t.style.boxShadow = 'none';
        });

        // Find clicked tab logic
        const clicked = document.querySelector(`.htb-tab[data-filter="${type}"]`);
        if (clicked) {
            clicked.classList.add('active');
            clicked.style.background = 'white';
            clicked.style.color = 'var(--htb-primary)';
            clicked.style.boxShadow = '0 2px 4px rgba(0,0,0,0.05)';
        }

        // 2. Filter Items and Count
        const items = document.querySelectorAll('.htb-search-result');
        const emptyMsg = document.getElementById('no-filter-results');
        let visibleCount = 0;

        items.forEach(item => {
            if (type === 'all' || item.getAttribute('data-type') === type) {
                item.style.display = 'block';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        // 3. Toggle Empty Message
        if (visibleCount === 0) {
            emptyMsg.style.display = 'block';
            emptyMsg.querySelector('p').textContent = 'No ' + (type === 'all' ? 'results' : type + 's') + ' found matching your search.';
        } else {
            emptyMsg.style.display = 'none';
        }
    }
</script>


<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
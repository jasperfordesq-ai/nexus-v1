$layout = layout(); // Fixed: centralized detection


// Fallback if layout doesn't exist
if (!file_exists(__DIR__ . '/../layouts/' . $layout . '/header.php')) {
$layout = 'modern';
}
include __DIR__ . '/../layouts/' . $layout . '/header.php';
?>

<main class="htb-container htb-section" style="padding-top: 140px; min-height: 85vh; display: flex; justify-content: center;">

    <!-- CENTRAL GLASS PANEL -->
    <div class="htb-glass-panel" style="width: 100%; max-width: 900px; padding: 40px; border-radius: 24px; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.6); box-shadow: 0 20px 50px rgba(0, 0, 0, 0.05);">

        <header style="margin-bottom: 30px; border-bottom: 2px solid rgba(0,0,0,0.05); padding-bottom: 20px; display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1 style="margin: 0; font-size: 2rem; font-weight: 800; background: var(--htb-gradient-brand); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;">Search Results</h1>
                <p style="margin: 5px 0 0; color: #6b7280; font-size: 1rem;">Found <?= count($results) ?> matches for "<strong><?= htmlspecialchars($query) ?></strong>"</p>
            </div>

            <!-- Filter Tabs -->
            <?php if (!empty($results)): ?>
                <div class="htb-search-tabs" style="display: flex; gap: 5px; background: rgba(0,0,0,0.05); padding: 5px; border-radius: 12px; flex-wrap: wrap;">
                    <button onclick="filterSearch('all')" class="htb-tab active" data-filter="all" style="border:none; cursor:pointer; padding: 6px 16px; border-radius: 8px; background: white; color: var(--htb-primary); font-weight: 700; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">All</button>
                    <button onclick="filterSearch('user')" class="htb-tab" data-filter="user" style="border:none; cursor:pointer; padding: 6px 16px; border-radius: 8px; background: transparent; color: #6b7280; font-weight: 600; transition: 0.2s;">People</button>
                    <button onclick="filterSearch('group')" class="htb-tab" data-filter="group" style="border:none; cursor:pointer; padding: 6px 16px; border-radius: 8px; background: transparent; color: #6b7280; font-weight: 600; transition: 0.2s;">Groups</button>
                    <button onclick="filterSearch('listing')" class="htb-tab" data-filter="listing" style="border:none; cursor:pointer; padding: 6px 16px; border-radius: 8px; background: transparent; color: #6b7280; font-weight: 600; transition: 0.2s;">Offers & Requests</button>
                </div>
            <?php endif; ?>
        </header>

        <?php if (empty($results)): ?>
            <div style="text-align: center; padding: 60px 20px;">
                <div style="background: rgba(var(--htb-primary-rgb), 0.1); width: 100px; height: 100px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                    <span class="dashicons dashicons-search" style="font-size: 40px; color: var(--htb-primary);"></span>
                </div>
                <h3 style="margin: 0 0 10px; font-size: 1.5rem; color: #1f2937;">No results found</h3>
                <p style="color: #6b7280; max-width: 400px; margin: 0 auto;">We couldn't find anything for "<?= htmlspecialchars($query) ?>". Try different keywords or browse our directory.</p>
                <div style="margin-top: 30px; display: flex; gap: 10px; justify-content: center;">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings" class="htb-btn htb-btn-primary">Browse Listings</a>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups" class="htb-btn htb-btn-secondary">Find Groups</a>
                </div>
            </div>
        <?php else: ?>
            <div class="htb-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
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
                    <a href="<?= $url ?>" class="htb-card htb-search-result" data-type="<?= $item['type'] ?>" style="text-decoration: none; color: inherit; transition: all 0.2s ease; position: relative; overflow: hidden; padding: 20px; border: 1px solid rgba(0,0,0,0.05); background: white;">
                        <div class="htb-card-body" style="display: flex; align-items: flex-start; gap: 15px; padding: 0 !important;">
                            <!-- Icon/Image -->
                            <div style="flex-shrink: 0;">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="<?= htmlspecialchars($item['image']) ?>" style="width: 50px; height: 50px; border-radius: 12px; object-fit: cover; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; border-radius: 12px; background: #f3f4f6; display: flex; align-items: center; justify-content: center;">
                                        <span class="dashicons dashicons-<?= $icon ?>" style="color: var(--htb-<?= $color ?>-600); font-size: 24px;"></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Content -->
                            <div style="flex-grow: 1; min-width: 0;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                                    <span class="htb-badge" style="font-size: 0.65rem; padding: 3px 8px; border-radius: 6px; text-transform: uppercase; font-weight: 700; background: var(--htb-<?= $color ?>-100); color: var(--htb-<?= $color ?>-700);"><?= strtoupper($item['type']) ?></span>
                                </div>
                                <h3 style="margin: 0 0 4px; font-size: 1.1rem; font-weight: 700; color: #111827; line-height: 1.3;">
                                    <?= htmlspecialchars($item['title']) ?>
                                </h3>
                                <?php if (!empty($item['description'])): ?>
                                    <p style="margin: 0; font-size: 0.9rem; color: #6b7280; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5;">
                                        <?= htmlspecialchars(strip_tags($item['description'])) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="position: absolute; right: 20px; bottom: 20px; opacity: 0; transform: translateX(-10px); transition: all 0.2s;">
                            <span class="dashicons dashicons-arrow-right-alt2" style="color: var(--htb-primary);"></span>
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

        // 2. Filter Items
        const items = document.querySelectorAll('.htb-search-result');
        let count = 0;
        items.forEach(item => {
            if (type === 'all' || item.getAttribute('data-type') === type) {
                item.style.display = 'block';
                count++;
            } else {
                item.style.display = 'none';
            }
        });
    }
</script>

<style>
    /* Premium Hover Effect */
    .htb-search-result:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
        border-color: rgba(var(--htb-primary-rgb), 0.3);
    }

    .htb-search-result:hover div[style*="opacity: 0"] {
        opacity: 1 !important;
        transform: translateX(0) !important;
    }

    /* Dark Mode Glass */
    [data-theme="dark"] .htb-glass-panel {
        background: rgba(30, 41, 59, 0.85);
        border-color: rgba(255, 255, 255, 0.08);
    }

    [data-theme="dark"] .htb-search-result {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(255, 255, 255, 0.05);
    }

    [data-theme="dark"] .htb-search-result h3 {
        color: #f9fafb !important;
    }

    [data-theme="dark"] .htb-search-result p {
        color: #9ca3af !important;
    }

    [data-theme="dark"] .htb-search-tabs {
        background: rgba(255, 255, 255, 0.05) !important;
    }

    [data-theme="dark"] .htb-tab.active {
        background: #374151 !important;
        color: white !important;
    }

    [data-theme="dark"] .htb-tab:not(.active) {
        color: #9ca3af !important;
    }
</style>

<?php include __DIR__ . '/../layouts/' . $layout . '/footer.php'; ?>
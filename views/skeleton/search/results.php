<?php
/**
 * Skeleton Layout - Search Results
 * Global search results page
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$query = $_GET['q'] ?? '';
$results = $results ?? [];
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">
    Search Results
    <?php if ($query): ?>
        for "<?= htmlspecialchars($query) ?>"
    <?php endif; ?>
</h1>
<p style="color: #888; margin-bottom: 2rem;">
    <?= count($results) ?> results found
</p>

<!-- Search Bar -->
<div class="sk-card" style="margin-bottom: 2rem;">
    <form method="GET" action="<?= $basePath ?>/search">
        <div class="sk-flex">
            <input type="text" name="q" class="sk-form-input" placeholder="Search..."
                   value="<?= htmlspecialchars($query) ?>" style="flex: 1;">
            <button type="submit" class="sk-btn">
                <i class="fas fa-search"></i> Search
            </button>
        </div>
    </form>
</div>

<!-- Results -->
<?php if (!empty($results)): ?>
    <div style="display: flex; flex-direction: column; gap: 1rem;">
        <?php foreach ($results as $result): ?>
            <div class="sk-card">
                <div class="sk-flex-between" style="margin-bottom: 0.5rem;">
                    <span class="sk-badge"><?= htmlspecialchars($result['type'] ?? 'Result') ?></span>
                    <?php if (!empty($result['date'])): ?>
                        <span style="color: #888; font-size: 0.875rem;">
                            <?php
                            $date = new DateTime($result['date']);
                            echo $date->format('M j, Y');
                            ?>
                        </span>
                    <?php endif; ?>
                </div>

                <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">
                    <a href="<?= htmlspecialchars($result['url'] ?? '#') ?>" style="color: var(--sk-text); text-decoration: none;">
                        <?= htmlspecialchars($result['title'] ?? 'Untitled') ?>
                    </a>
                </h3>

                <?php if (!empty($result['excerpt'])): ?>
                    <p style="color: #666; margin-bottom: 1rem;">
                        <?= htmlspecialchars($result['excerpt']) ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($result['author'])): ?>
                    <div style="color: #888; font-size: 0.875rem;">
                        by <?= htmlspecialchars($result['author']) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <div style="text-align: center; margin-top: 2rem;">
        <button class="sk-btn sk-btn-outline">Load More</button>
    </div>
<?php else: ?>
    <div class="sk-empty-state">
        <div class="sk-empty-state-icon"><i class="fas fa-search"></i></div>
        <h3>No results found</h3>
        <p>Try different keywords or check your spelling</p>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>

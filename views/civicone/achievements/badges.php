<?php
$hTitle = 'All Badges';
$hSubtitle = $totalEarned . ' of ' . $totalAvailable . ' badges earned';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Achievements';

$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

// Check for new badge (for confetti)
$newBadge = isset($_GET['new_badge']) ? true : false;
$showcaseUpdated = isset($_GET['showcase_updated']);
?>
<!-- CSS moved to /assets/css/civicone-achievements.css (2026-01-19) -->

<div class="badges-wrapper">
    <a href="<?= $basePath ?>/achievements" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
    </a>

    <?php if ($showcaseUpdated): ?>
    <div class="success-toast">
        <i class="fa-solid fa-check-circle"></i>
        <span>Badge showcase updated! These badges will appear on your profile.</span>
    </div>
    <?php endif; ?>

    <!-- Badge Showcase Section -->
    <div class="showcase-section">
        <h2><i class="fa-solid fa-star"></i> Badge Showcase</h2>
        <p>Pin up to 3 badges to display on your profile. Click any earned badge below to add/remove it.</p>

        <form id="showcase-form" action="<?= $basePath ?>/achievements/showcase" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>
            <div class="showcase-badges">
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <?php if (isset($showcasedBadges[$i])): ?>
                    <div class="showcase-badge-slot filled" data-key="<?= htmlspecialchars($showcasedBadges[$i]['badge_key']) ?>">
                        <input type="hidden" name="badge_keys[]" value="<?= htmlspecialchars($showcasedBadges[$i]['badge_key']) ?>">
                        <span class="badge-icon"><?= $showcasedBadges[$i]['icon'] ?></span>
                        <span class="badge-name"><?= htmlspecialchars($showcasedBadges[$i]['name']) ?></span>
                    </div>
                    <?php else: ?>
                    <div class="showcase-badge-slot empty">
                        <i class="fa-solid fa-plus"></i>
                        <span>Empty Slot</span>
                    </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <button type="submit" id="save-showcase" class="nexus-btn nexus-btn-primary" style="margin-top: 16px; display: none;">
                <i class="fa-solid fa-save"></i> Save Showcase
            </button>
        </form>
    </div>

    <!-- Progress Bar -->
    <div class="badges-progress-bar">
        <h2>Badge Collection Progress</h2>
        <?php $percent = round(($totalEarned / $totalAvailable) * 100); ?>
        <div class="progress-outer">
            <div class="progress-inner" style="width: <?= $percent ?>%">
                <?= $percent ?>%
            </div>
        </div>
        <div class="progress-label">
            <?= $totalEarned ?> of <?= $totalAvailable ?> badges earned
        </div>
    </div>

    <!-- Badge Categories -->
    <?php foreach ($badgesByCategory as $type => $category): ?>
    <?php
        $earnedInCategory = count(array_filter($category['badges'], fn($b) => $b['earned']));
        $totalInCategory = count($category['badges']);
    ?>
    <div class="badge-category">
        <h3>
            <?= htmlspecialchars($category['name']) ?>
            <span class="count"><?= $earnedInCategory ?> / <?= $totalInCategory ?></span>
        </h3>

        <div class="badges-grid">
            <?php foreach ($category['badges'] as $badge): ?>
            <div class="badge-item <?= $badge['earned'] ? 'earned' : 'locked' ?>"
                 data-key="<?= htmlspecialchars($badge['key']) ?>"
                 data-name="<?= htmlspecialchars($badge['name']) ?>"
                 data-icon="<?= htmlspecialchars($badge['icon']) ?>">

                <?php if ($badge['earned']): ?>
                <span class="earned-check"><i class="fa-solid fa-check"></i></span>
                <?php endif; ?>

                <?php if (!empty($badge['showcased'])): ?>
                <span class="showcase-star"><i class="fa-solid fa-star"></i></span>
                <?php endif; ?>

                <span class="badge-icon"><?= $badge['icon'] ?></span>
                <div class="badge-name"><?= htmlspecialchars($badge['name']) ?></div>
                <div class="badge-desc"><?= ucfirst($badge['msg'] ?? '') ?></div>

                <?php if ($badge['rarity']): ?>
                <span class="badge-rarity rarity-<?= strtolower($badge['rarity']['label']) ?>">
                    <?= $badge['rarity']['label'] ?> (<?= $badge['rarity']['percent'] ?>%)
                </span>
                <?php elseif ($badge['earned']): ?>
                <span class="badge-rarity rarity-legendary">First!</span>
                <?php endif; ?>

                <?php if (!$badge['earned'] && $badge['threshold'] > 0): ?>
                <div class="badge-threshold">Requires: <?= $badge['threshold'] ?></div>
                <?php endif; ?>

                <?php if ($badge['earned']): ?>
                <button type="button" class="pin-btn <?= !empty($badge['showcased']) ? 'pinned' : '' ?>" onclick="toggleShowcase(this, '<?= htmlspecialchars($badge['key']) ?>', '<?= htmlspecialchars($badge['name']) ?>', '<?= htmlspecialchars($badge['icon']) ?>')">
                    <?= !empty($badge['showcased']) ? '<i class="fa-solid fa-star"></i> Pinned' : '<i class="fa-regular fa-star"></i> Pin' ?>
                </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Confetti Container -->
<?php if ($newBadge): ?>
<div class="confetti-container" id="confetti-container"></div>
<?php endif; ?>

<!-- JS moved to /assets/js/civicone-achievements.js (2026-01-19) -->

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>

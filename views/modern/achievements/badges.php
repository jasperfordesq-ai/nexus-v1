<?php
$hTitle = 'All Badges';
$hSubtitle = $totalEarned . ' of ' . $totalAvailable . ' badges earned';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Achievements';

$basePath = \Nexus\Core\TenantContext::getBasePath();

// Load achievements CSS
$additionalCSS = '<link rel="stylesheet" href="/assets/css/achievements.min.css?v=' . time() . '">';

require dirname(__DIR__, 2) . '/layouts/modern/header.php';

// Check for new badge (for confetti)
$newBadge = isset($_GET['new_badge']) ? true : false;
$showcaseUpdated = isset($_GET['showcase_updated']);
?>

<div class="badges-wrapper" role="main" aria-label="Badge Collection">
    <nav class="collections-nav" aria-label="Achievement sections">
        <a href="<?= $basePath ?>/achievements" class="nav-pill">Dashboard</a>
        <a href="<?= $basePath ?>/achievements/badges" class="nav-pill active">All Badges</a>
        <a href="<?= $basePath ?>/achievements/challenges" class="nav-pill">Challenges</a>
        <a href="<?= $basePath ?>/achievements/collections" class="nav-pill">Collections</a>
        <a href="<?= $basePath ?>/achievements/shop" class="nav-pill">XP Shop</a>
        <a href="<?= $basePath ?>/achievements/seasons" class="nav-pill">Seasons</a>
    </nav>

    <?php if ($showcaseUpdated): ?>
    <div class="success-toast" id="successToast" role="alert">
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
            <button type="submit" id="save-showcase" class="nexus-btn nexus-btn-primary showcase-save-btn">
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

<script>
// Auto-dismiss success toast after 4 seconds
(function() {
    const toast = document.getElementById('successToast');
    if (toast) {
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-20px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
})();

// Showcase management
function toggleShowcase(btn, key, name, icon) {
    const form = document.getElementById('showcase-form');
    const slots = form.querySelectorAll('.showcase-badge-slot');
    const saveBtn = document.getElementById('save-showcase');

    // Check if already pinned
    const existingInput = form.querySelector(`input[value="${key}"]`);
    if (existingInput) {
        // Remove from showcase
        const slot = existingInput.closest('.showcase-badge-slot');
        slot.classList.remove('filled');
        slot.classList.add('empty');
        slot.innerHTML = '<i class="fa-solid fa-plus"></i><span>Empty Slot</span>';
        slot.removeAttribute('data-key');
        btn.classList.remove('pinned');
        btn.innerHTML = '<i class="fa-regular fa-star"></i> Pin';
    } else {
        // Find empty slot
        let emptySlot = null;
        slots.forEach(slot => {
            if (!slot.classList.contains('filled') && !emptySlot) {
                emptySlot = slot;
            }
        });

        if (!emptySlot) {
            alert('All 3 showcase slots are full. Remove one first.');
            return;
        }

        // Add to showcase
        emptySlot.classList.remove('empty');
        emptySlot.classList.add('filled');
        emptySlot.setAttribute('data-key', key);
        emptySlot.innerHTML = `
            <input type="hidden" name="badge_keys[]" value="${key}">
            <span class="badge-icon">${icon}</span>
            <span class="badge-name">${name}</span>
        `;
        btn.classList.add('pinned');
        btn.innerHTML = '<i class="fa-solid fa-star"></i> Pinned';
    }

    saveBtn.style.display = 'inline-flex';
}

// Confetti effect
<?php if ($newBadge): ?>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('confetti-container');
    const colors = ['#a855f7', '#6366f1', '#f59e0b', '#10b981', '#ef4444', '#3b82f6'];

    for (let i = 0; i < 100; i++) {
        setTimeout(() => {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animationDelay = Math.random() * 0.5 + 's';
            confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
            container.appendChild(confetti);

            setTimeout(() => confetti.remove(), 3500);
        }, i * 20);
    }
});
<?php endif; ?>
</script>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>

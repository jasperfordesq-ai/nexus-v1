<?php
$hTitle = 'All Badges';
$hSubtitle = $totalEarned . ' of ' . $totalAvailable . ' badges earned';
$hGradient = 'mt-hero-gradient-gamification';
$hType = 'Achievements';

$basePath = \Nexus\Core\TenantContext::getBasePath();
require dirname(__DIR__, 2) . '/layouts/modern/header.php';

// Check for new badge (for confetti)
$newBadge = isset($_GET['new_badge']) ? true : false;
$showcaseUpdated = isset($_GET['showcase_updated']);
?>

<style>
.badges-wrapper {
    margin-top: 120px;
    position: relative;
    z-index: 20;
    padding: 0 20px 60px;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}

.badges-progress-bar {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 32px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.badges-progress-bar h2 {
    margin: 0 0 12px 0;
    font-size: 1.3rem;
    color: #1f2937;
}

.progress-outer {
    background: #e5e7eb;
    border-radius: 12px;
    height: 24px;
    overflow: hidden;
    margin-bottom: 8px;
}

.progress-inner {
    height: 100%;
    background: linear-gradient(90deg, #a855f7, #6366f1);
    border-radius: 12px;
    transition: width 0.5s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
}

.progress-label {
    text-align: center;
    color: #6b7280;
    font-size: 0.9rem;
}

/* Showcase Section */
.showcase-section {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.1));
    border: 2px solid rgba(245, 158, 11, 0.3);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 32px;
}

.showcase-section h2 {
    margin: 0 0 8px 0;
    font-size: 1.2rem;
    color: #92400e;
    display: flex;
    align-items: center;
    gap: 10px;
}

.showcase-section p {
    margin: 0 0 16px 0;
    font-size: 0.9rem;
    color: #78350f;
}

.showcase-badges {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.showcase-badge-slot {
    width: 100px;
    height: 100px;
    border: 2px dashed rgba(245, 158, 11, 0.4);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.5);
}

.showcase-badge-slot.filled {
    border-style: solid;
    background: white;
}

.showcase-badge-slot .badge-icon {
    font-size: 2.5rem;
}

.showcase-badge-slot .badge-name {
    font-size: 0.7rem;
    color: #78350f;
    text-align: center;
    margin-top: 4px;
}

.showcase-badge-slot.empty {
    color: #d97706;
    font-size: 0.8rem;
}

.badge-category {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.badge-category h3 {
    margin: 0 0 20px 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.badge-category h3 .count {
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
    font-size: 0.8rem;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 600;
}

.badges-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
}

.badge-item {
    padding: 16px;
    border-radius: 12px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
    cursor: default;
}

.badge-item.earned {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.15), rgba(139, 92, 246, 0.1));
    border: 2px solid rgba(168, 85, 247, 0.4);
    cursor: pointer;
}

.badge-item.locked {
    background: rgba(0, 0, 0, 0.03);
    border: 2px dashed rgba(0, 0, 0, 0.1);
    opacity: 0.6;
}

.badge-item:hover {
    transform: translateY(-4px);
}

.badge-item.earned:hover {
    box-shadow: 0 8px 24px rgba(168, 85, 247, 0.3);
}

.badge-item .badge-icon {
    font-size: 3rem;
    margin-bottom: 12px;
    display: block;
}

.badge-item.locked .badge-icon {
    filter: grayscale(100%);
    opacity: 0.4;
}

.badge-item .badge-name {
    font-weight: 700;
    color: #1f2937;
    font-size: 0.95rem;
    margin-bottom: 6px;
}

.badge-item.locked .badge-name {
    color: #6b7280;
}

.badge-item .badge-desc {
    font-size: 0.8rem;
    color: #6b7280;
}

/* Rarity Badge */
.badge-rarity {
    display: inline-block;
    font-size: 0.65rem;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
    margin-top: 8px;
}

.rarity-legendary { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #78350f; }
.rarity-epic { background: linear-gradient(135deg, #a855f7, #7c3aed); color: white; }
.rarity-rare { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
.rarity-uncommon { background: linear-gradient(135deg, #10b981, #059669); color: white; }
.rarity-common { background: #e5e7eb; color: #6b7280; }

.badge-item .badge-threshold {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: 8px;
}

.badge-item .earned-check {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #10b981;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.4);
}

/* Showcase star indicator */
.badge-item .showcase-star {
    position: absolute;
    top: 10px;
    left: 10px;
    color: #f59e0b;
    font-size: 1.2rem;
}

/* Pin to showcase button */
.badge-item .pin-btn {
    margin-top: 10px;
    font-size: 0.75rem;
    padding: 4px 10px;
    border: 1px solid rgba(168, 85, 247, 0.4);
    background: white;
    color: #7c3aed;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.badge-item .pin-btn:hover {
    background: #7c3aed;
    color: white;
}

.badge-item .pin-btn.pinned {
    background: #f59e0b;
    border-color: #f59e0b;
    color: white;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: white;
    text-decoration: none;
    font-weight: 600;
    margin-bottom: 20px;
    padding: 8px 16px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 20px;
    backdrop-filter: blur(4px);
    transition: background 0.2s;
}

.back-link:hover {
    background: rgba(0, 0, 0, 0.3);
}

/* Success message */
.success-toast {
    background: #dcfce7;
    color: #166534;
    padding: 12px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid #bbf7d0;
}

/* Confetti Animation */
.confetti-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 9999;
    overflow: hidden;
}

.confetti {
    position: absolute;
    width: 10px;
    height: 10px;
    opacity: 0;
    animation: confetti-fall 3s ease-out forwards;
}

@keyframes confetti-fall {
    0% {
        opacity: 1;
        transform: translateY(-100px) rotate(0deg);
    }
    100% {
        opacity: 0;
        transform: translateY(100vh) rotate(720deg);
    }
}

/* Dark Mode */
[data-theme="dark"] .badges-progress-bar,
[data-theme="dark"] .badge-category {
    background: rgba(30, 41, 59, 0.95);
    border-color: rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .badges-progress-bar h2,
[data-theme="dark"] .badge-category h3 {
    color: #f1f5f9;
}

[data-theme="dark"] .progress-outer {
    background: rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .badge-item.locked {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .badge-item .badge-name {
    color: #e2e8f0;
}

[data-theme="dark"] .badge-item.locked .badge-name {
    color: #94a3b8;
}

[data-theme="dark"] .showcase-section {
    background: rgba(245, 158, 11, 0.1);
}

[data-theme="dark"] .showcase-section h2 {
    color: #fbbf24;
}

[data-theme="dark"] .showcase-section p {
    color: #fcd34d;
}

[data-theme="dark"] .showcase-badge-slot {
    background: rgba(30, 41, 59, 0.8);
}

[data-theme="dark"] .badge-item .pin-btn {
    background: rgba(30, 41, 59, 0.8);
    color: #c4b5fd;
}

@media (max-width: 768px) {
    .badges-wrapper {
        padding: 0 15px 60px;
        margin-top: 100px;
    }
    .badges-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .badge-item .badge-icon {
        font-size: 2.5rem;
    }
    .showcase-badges {
        justify-content: center;
    }
}
</style>

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

<script>
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

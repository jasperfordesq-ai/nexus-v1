<?php

/**
 * Component: Achievement Card
 *
 * Card for displaying achievements/badges.
 *
 * @param array $badge Badge data with keys: id, name, description, icon, rarity, earned, earned_at, progress, required
 * @param bool $showProgress Show progress bar (default: true)
 * @param bool $showRarity Show rarity indicator (default: true)
 * @param string $class Additional CSS classes
 * @param string $size 'sm', 'md', 'lg' (default: 'md')
 */

$badge = $badge ?? [];
$showProgress = $showProgress ?? true;
$showRarity = $showRarity ?? true;
$class = $class ?? '';
$size = $size ?? 'md';

// Extract badge data with defaults
$id = $badge['id'] ?? 0;
$name = $badge['name'] ?? 'Unknown Badge';
$description = $badge['description'] ?? '';
$icon = $badge['icon'] ?? 'award';
$rarity = $badge['rarity'] ?? 'common'; // 'common', 'uncommon', 'rare', 'epic', 'legendary'
$earned = $badge['earned'] ?? false;
$earnedAt = $badge['earned_at'] ?? '';
$progress = $badge['progress'] ?? 0;
$required = $badge['required'] ?? 100;

$cssClass = trim('achievement-card achievement-' . $size . ' ' . $class . ($earned ? ' earned' : ' locked'));

// Rarity colors
$rarityColors = [
    'common' => 'var(--color-gray-400)',
    'uncommon' => 'var(--color-success)',
    'rare' => 'var(--color-primary-500)',
    'epic' => 'var(--color-purple-500)',
    'legendary' => 'var(--color-warning)',
];
$rarityColor = $rarityColors[$rarity] ?? $rarityColors['common'];

$progressPercent = $required > 0 ? min(100, ($progress / $required) * 100) : 0;
?>

<article class="<?= e($cssClass) ?> rarity-<?= e($rarity) ?>" data-badge-id="<?= $id ?>">
    <div class="achievement-icon-wrapper">
        <div class="achievement-icon-circle <?= $earned ? 'earned' : 'locked' ?>">
            <i class="fa-solid fa-<?= e($icon) ?>"></i>
        </div>
        <?php if (!$earned): ?>
            <div class="achievement-lock-overlay">
                <i class="fa-solid fa-lock"></i>
            </div>
        <?php endif; ?>
    </div>

    <div class="achievement-info">
        <h4 class="achievement-name"><?= e($name) ?></h4>

        <?php if ($description): ?>
            <p class="achievement-description"><?= e($description) ?></p>
        <?php endif; ?>

        <?php if ($showRarity): ?>
            <span class="badge-rarity-tag rarity-<?= e($rarity) ?>">
                <?= ucfirst(e($rarity)) ?>
            </span>
        <?php endif; ?>

        <?php if (!$earned && $showProgress && $required > 0): ?>
            <div class="achievement-progress">
                <div class="progress-bar component-progress">
                    <div class="progress-fill component-progress__bar rarity-<?= e($rarity) ?>-bg" style="width: <?= $progressPercent ?>%;"></div>
                </div>
                <span class="progress-text"><?= (int)$progress ?> / <?= (int)$required ?></span>
            </div>
        <?php elseif ($earned && $earnedAt): ?>
            <span class="achievement-earned-date">
                <i class="fa-solid fa-check-circle"></i>
                Earned <?= date('M j, Y', strtotime($earnedAt)) ?>
            </span>
        <?php endif; ?>
    </div>
</article>

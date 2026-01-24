<?php

/**
 * Component: Star Rating
 *
 * Interactive star rating input for reviews.
 * Used on: reviews/create, federation/review-form, profile reviews
 *
 * @param string $name Input name attribute (default: 'rating')
 * @param int $value Current/default rating value (1-5)
 * @param int $max Maximum stars (default: 5)
 * @param bool $readonly Display only, no interaction (default: false)
 * @param string $size Size: 'sm', 'md', 'lg' (default: 'md')
 * @param bool $showLabel Show rating label (e.g., "Excellent") (default: true)
 * @param bool $required Required field (default: true)
 * @param string $class Additional CSS classes
 * @param string $id Element ID
 */

$name = $name ?? 'rating';
$value = $value ?? 0;
$max = $max ?? 5;
$readonly = $readonly ?? false;
$size = $size ?? 'md';
$showLabel = $showLabel ?? true;
$required = $required ?? true;
$class = $class ?? '';
$id = $id ?? 'star-rating-' . md5($name . microtime());

// Size classes
$sizeClasses = [
    'sm' => 'component-star-rating--sm',
    'md' => 'component-star-rating--md',
    'lg' => 'component-star-rating--lg',
];
$sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];

$labels = [
    1 => 'Very Poor',
    2 => 'Poor',
    3 => 'Average',
    4 => 'Good',
    5 => 'Excellent',
];

$cssClass = trim('component-star-rating ' . $sizeClass . ' ' . $class);
?>

<div class="<?= e($cssClass) ?>" id="<?= e($id) ?>" data-rating="<?= (int)$value ?>">
    <?php if ($readonly): ?>
        <!-- Read-only display -->
        <div class="component-star-rating__display">
            <?php for ($i = 1; $i <= $max; $i++): ?>
                <?php
                $starClass = 'component-star-rating__star';
                $starClass .= $i <= $value ? ' component-star-rating__star--filled' : ' component-star-rating__star--empty';
                ?>
                <span class="<?= e($starClass) ?>">
                    <i class="fa-<?= $i <= $value ? 'solid' : 'regular' ?> fa-star"></i>
                </span>
            <?php endfor; ?>
            <?php if ($showLabel && $value > 0): ?>
                <span class="component-star-rating__value">
                    (<?= number_format($value, 1) ?>)
                </span>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Interactive rating input -->
        <div class="component-star-rating__input">
            <?php for ($i = $max; $i >= 1; $i--): ?>
                <input
                    type="radio"
                    name="<?= e($name) ?>"
                    value="<?= $i ?>"
                    id="<?= e($id) ?>-<?= $i ?>"
                    class="component-star-rating__radio visually-hidden"
                    <?= $i === $value ? 'checked' : '' ?>
                    <?= $required && $i === 1 ? 'required' : '' ?>
                >
                <label
                    for="<?= e($id) ?>-<?= $i ?>"
                    class="component-star-rating__label"
                    title="<?= e($labels[$i] ?? $i . ' stars') ?>"
                >
                    <i class="fa-solid fa-star"></i>
                </label>
            <?php endfor; ?>
        </div>
        <?php if ($showLabel): ?>
            <div class="component-star-rating__text" id="<?= e($id) ?>-label">
                <?= $value > 0 ? e($labels[$value] ?? '') : 'Select a rating' ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (!$readonly): ?>
<script>
(function() {
    const wrapper = document.getElementById('<?= e($id) ?>');
    const label = document.getElementById('<?= e($id) ?>-label');
    const labels = <?= json_encode($labels) ?>;

    if (wrapper && label) {
        wrapper.querySelectorAll('.component-star-rating__radio').forEach(input => {
            input.addEventListener('change', function() {
                wrapper.dataset.rating = this.value;
                label.textContent = labels[this.value] || '';
            });
        });
    }
})();
</script>
<?php endif; ?>

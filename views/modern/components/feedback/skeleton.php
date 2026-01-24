<?php

/**
 * Component: Skeleton
 *
 * Loading skeleton placeholder.
 *
 * @param string $type Skeleton type: 'card', 'list', 'feed', 'table', 'text', 'avatar', 'image' (default: 'card')
 * @param int $count Number of skeleton items to render (default: 3)
 * @param int $columns Grid columns for card type (default: 3)
 * @param int $lines Number of text lines for text type (default: 3)
 * @param string $class Additional CSS classes
 * @param string $id Container ID (useful for hiding on load)
 */

$type = $type ?? 'card';
$count = $count ?? 3;
$columns = $columns ?? 3;
$lines = $lines ?? 3;
$class = $class ?? '';
$id = $id ?? '';

$cssClass = trim('component-skeleton ' . $class);
?>

<div class="<?= e($cssClass) ?>"<?php if ($id): ?> id="<?= e($id) ?>"<?php endif; ?>>
    <?php if ($type === 'card'): ?>
        <div class="component-skeleton__grid">
            <?php for ($i = 0; $i < $count; $i++): ?>
                <div class="component-skeleton__card">
                    <div class="component-skeleton__shimmer component-skeleton__image"></div>
                    <div class="component-skeleton__card-body">
                        <div class="component-skeleton__shimmer component-skeleton__title"></div>
                        <div class="component-skeleton__shimmer component-skeleton__text"></div>
                        <div class="component-skeleton__shimmer component-skeleton__text component-skeleton__text--short"></div>
                        <div class="component-skeleton__card-footer">
                            <div class="component-skeleton__shimmer component-skeleton__avatar"></div>
                            <div class="component-skeleton__shimmer component-skeleton__button"></div>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

    <?php elseif ($type === 'feed'): ?>
        <?php for ($i = 0; $i < $count; $i++): ?>
            <div class="component-skeleton__feed-card">
                <div class="component-skeleton__feed-header">
                    <div class="component-skeleton__shimmer component-skeleton__avatar component-skeleton__avatar--lg"></div>
                    <div class="component-skeleton__feed-meta">
                        <div class="component-skeleton__shimmer component-skeleton__name"></div>
                        <div class="component-skeleton__shimmer component-skeleton__date"></div>
                    </div>
                </div>
                <div class="component-skeleton__shimmer component-skeleton__text"></div>
                <div class="component-skeleton__shimmer component-skeleton__text"></div>
                <div class="component-skeleton__shimmer component-skeleton__text component-skeleton__text--medium"></div>
            </div>
        <?php endfor; ?>

    <?php elseif ($type === 'list'): ?>
        <?php for ($i = 0; $i < $count; $i++): ?>
            <div class="component-skeleton__list-item">
                <div class="component-skeleton__shimmer component-skeleton__avatar"></div>
                <div class="component-skeleton__list-content">
                    <div class="component-skeleton__shimmer component-skeleton__text component-skeleton__text--medium"></div>
                    <div class="component-skeleton__shimmer component-skeleton__text component-skeleton__text--short"></div>
                </div>
            </div>
        <?php endfor; ?>

    <?php elseif ($type === 'table'): ?>
        <div class="component-skeleton__table">
            <div class="component-skeleton__table-header">
                <div class="component-skeleton__shimmer component-skeleton__cell component-skeleton__cell--sm"></div>
                <div class="component-skeleton__shimmer component-skeleton__cell component-skeleton__cell--flex"></div>
                <div class="component-skeleton__shimmer component-skeleton__cell component-skeleton__cell--md"></div>
                <div class="component-skeleton__shimmer component-skeleton__cell component-skeleton__cell--sm"></div>
            </div>
            <?php for ($i = 0; $i < $count; $i++): ?>
                <div class="component-skeleton__table-row">
                    <div class="component-skeleton__shimmer component-skeleton__cell component-skeleton__cell--sm"></div>
                    <div class="component-skeleton__shimmer component-skeleton__cell component-skeleton__cell--flex"></div>
                    <div class="component-skeleton__shimmer component-skeleton__cell component-skeleton__cell--md"></div>
                    <div class="component-skeleton__shimmer component-skeleton__cell component-skeleton__cell--sm"></div>
                </div>
            <?php endfor; ?>
        </div>

    <?php elseif ($type === 'text'): ?>
        <div class="component-skeleton__text-block">
            <?php for ($i = 0; $i < $lines; $i++): ?>
                <div class="component-skeleton__shimmer component-skeleton__line <?= $i === $lines - 1 ? 'component-skeleton__line--short' : '' ?>"></div>
            <?php endfor; ?>
        </div>

    <?php elseif ($type === 'avatar'): ?>
        <div class="component-skeleton__avatars">
            <?php for ($i = 0; $i < $count; $i++): ?>
                <div class="component-skeleton__shimmer component-skeleton__avatar component-skeleton__avatar--lg"></div>
            <?php endfor; ?>
        </div>

    <?php elseif ($type === 'image'): ?>
        <div class="component-skeleton__shimmer component-skeleton__image component-skeleton__image--full"></div>
    <?php endif; ?>
</div>

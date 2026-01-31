<?php
/**
 * CivicOne Skeleton Loading Card
 * WCAG 2.1 AA Compliant Loading Placeholder
 *
 * CSS: /assets/css/civicone-skeleton-card.css
 *
 * Usage:
 *   <?php include __DIR__ . '/../layouts/civicone/partials/skeleton-card.php'; ?>
 *
 * Or with options:
 *   <?php
 *   $skeletonType = 'listing'; // or 'member', 'event'
 *   $skeletonCount = 6;
 *   include __DIR__ . '/../layouts/civicone/partials/skeleton-card.php';
 *   ?>
 */

$type = $skeletonType ?? 'listing';
$count = $skeletonCount ?? 3;
?>

<?php if ($type === 'listing'): ?>
    <?php for ($i = 0; $i < $count; $i++): ?>
        <div class="civic-card civic-skeleton-card" aria-hidden="true" role="presentation">
            <div class="civic-skeleton-card-header">
                <div class="civic-skeleton civic-skeleton-avatar"></div>
                <div class="civic-skeleton-card-meta">
                    <div class="civic-skeleton civic-skeleton-text civic-skeleton-w-60"></div>
                    <div class="civic-skeleton civic-skeleton-text civic-skeleton-w-40"></div>
                </div>
            </div>
            <div class="civic-skeleton-card-body">
                <div class="civic-skeleton civic-skeleton-title"></div>
                <div class="civic-skeleton civic-skeleton-text"></div>
                <div class="civic-skeleton civic-skeleton-text civic-skeleton-w-85"></div>
                <div class="civic-skeleton civic-skeleton-text civic-skeleton-w-70"></div>
            </div>
            <div class="civic-skeleton-actions">
                <div class="civic-skeleton civic-skeleton-button"></div>
                <div class="civic-skeleton civic-skeleton-button civic-skeleton-w-80"></div>
            </div>
        </div>
    <?php endfor; ?>

<?php elseif ($type === 'member'): ?>
    <?php for ($i = 0; $i < $count; $i++): ?>
        <div class="civic-member-card civic-skeleton-member" aria-hidden="true" role="presentation">
            <div class="civic-skeleton civic-skeleton-avatar"></div>
            <div class="civic-skeleton civic-skeleton-title"></div>
            <div class="civic-skeleton civic-skeleton-text"></div>
            <div class="civic-skeleton-stats">
                <div class="civic-skeleton-stat">
                    <div class="civic-skeleton civic-skeleton-stat-value"></div>
                    <div class="civic-skeleton civic-skeleton-stat-label"></div>
                </div>
                <div class="civic-skeleton-stat">
                    <div class="civic-skeleton civic-skeleton-stat-value"></div>
                    <div class="civic-skeleton civic-skeleton-stat-label"></div>
                </div>
            </div>
        </div>
    <?php endfor; ?>

<?php elseif ($type === 'event'): ?>
    <?php for ($i = 0; $i < $count; $i++): ?>
        <div class="civic-card civic-skeleton-card civic-skeleton-event" aria-hidden="true" role="presentation">
            <div class="civic-skeleton-event-layout">
                <div class="civic-skeleton-date">
                    <div class="civic-skeleton civic-skeleton-date-month"></div>
                    <div class="civic-skeleton civic-skeleton-date-day"></div>
                </div>
                <div class="civic-skeleton-event-content">
                    <div class="civic-skeleton civic-skeleton-title civic-skeleton-w-80"></div>
                    <div class="civic-skeleton civic-skeleton-text civic-skeleton-w-60"></div>
                    <div class="civic-skeleton civic-skeleton-text civic-skeleton-w-50"></div>
                </div>
            </div>
            <div class="civic-skeleton-actions">
                <div class="civic-skeleton civic-skeleton-button civic-skeleton-flex-1"></div>
            </div>
        </div>
    <?php endfor; ?>

<?php else: ?>
    <!-- Generic skeleton -->
    <?php for ($i = 0; $i < $count; $i++): ?>
        <div class="civic-card civic-skeleton-card" aria-hidden="true" role="presentation">
            <div class="civic-skeleton civic-skeleton-title"></div>
            <div class="civic-skeleton civic-skeleton-text"></div>
            <div class="civic-skeleton civic-skeleton-text civic-skeleton-w-80"></div>
        </div>
    <?php endfor; ?>
<?php endif; ?>

<!-- Skeleton Card CSS (extracted per CLAUDE.md) -->
<link rel="stylesheet" href="/assets/css/civicone-skeleton-card.css">

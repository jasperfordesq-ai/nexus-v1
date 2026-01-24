<?php
/**
 * CivicOne Skeleton Loading Card
 * WCAG 2.1 AA Compliant Loading Placeholder
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
                    <div class="civic-skeleton civic-skeleton-text" style="width: 60%;"></div>
                    <div class="civic-skeleton civic-skeleton-text" style="width: 40%;"></div>
                </div>
            </div>
            <div class="civic-skeleton-card-body">
                <div class="civic-skeleton civic-skeleton-title"></div>
                <div class="civic-skeleton civic-skeleton-text"></div>
                <div class="civic-skeleton civic-skeleton-text" style="width: 85%;"></div>
                <div class="civic-skeleton civic-skeleton-text" style="width: 70%;"></div>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 16px;">
                <div class="civic-skeleton civic-skeleton-button"></div>
                <div class="civic-skeleton civic-skeleton-button" style="width: 80px;"></div>
            </div>
        </div>
    <?php endfor; ?>

<?php elseif ($type === 'member'): ?>
    <?php for ($i = 0; $i < $count; $i++): ?>
        <div class="civic-member-card civic-skeleton-member" aria-hidden="true" role="presentation">
            <div class="civic-skeleton civic-skeleton-avatar" style="width: 80px; height: 80px; margin: 0 auto 12px;"></div>
            <div class="civic-skeleton civic-skeleton-title" style="width: 70%; margin: 0 auto 8px;"></div>
            <div class="civic-skeleton civic-skeleton-text" style="width: 50%; margin: 0 auto;"></div>
            <div style="display: flex; justify-content: center; gap: 16px; margin-top: 16px;">
                <div style="text-align: center;">
                    <div class="civic-skeleton" style="width: 40px; height: 24px; margin: 0 auto 4px;"></div>
                    <div class="civic-skeleton" style="width: 50px; height: 12px;"></div>
                </div>
                <div style="text-align: center;">
                    <div class="civic-skeleton" style="width: 40px; height: 24px; margin: 0 auto 4px;"></div>
                    <div class="civic-skeleton" style="width: 50px; height: 12px;"></div>
                </div>
            </div>
        </div>
    <?php endfor; ?>

<?php elseif ($type === 'event'): ?>
    <?php for ($i = 0; $i < $count; $i++): ?>
        <div class="civic-card civic-skeleton-card" aria-hidden="true" role="presentation" style="border-left: 4px solid #e5e7eb;">
            <div style="display: flex; gap: 16px;">
                <div style="text-align: center; min-width: 60px;">
                    <div class="civic-skeleton" style="width: 50px; height: 20px; margin-bottom: 4px;"></div>
                    <div class="civic-skeleton" style="width: 40px; height: 32px; margin: 0 auto;"></div>
                </div>
                <div style="flex: 1;">
                    <div class="civic-skeleton civic-skeleton-title" style="width: 80%;"></div>
                    <div class="civic-skeleton civic-skeleton-text" style="width: 60%;"></div>
                    <div class="civic-skeleton civic-skeleton-text" style="width: 50%;"></div>
                </div>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 16px;">
                <div class="civic-skeleton civic-skeleton-button" style="flex: 1;"></div>
            </div>
        </div>
    <?php endfor; ?>

<?php else: ?>
    <!-- Generic skeleton -->
    <?php for ($i = 0; $i < $count; $i++): ?>
        <div class="civic-card civic-skeleton-card" aria-hidden="true" role="presentation">
            <div class="civic-skeleton civic-skeleton-title"></div>
            <div class="civic-skeleton civic-skeleton-text"></div>
            <div class="civic-skeleton civic-skeleton-text" style="width: 80%;"></div>
        </div>
    <?php endfor; ?>
<?php endif; ?>

<!-- Skeleton Card CSS (extracted per CLAUDE.md) -->
<link rel="stylesheet" href="/assets/css/civicone-skeleton-card.css">

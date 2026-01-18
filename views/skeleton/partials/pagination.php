<?php
/**
 * Reusable Pagination Component
 *
 * Usage:
 * $currentPage = 2;
 * $totalPages = 10;
 * $baseUrl = '/listings';
 * include __DIR__ . '/partials/pagination.php';
 */

$currentPage = $currentPage ?? 1;
$totalPages = $totalPages ?? 1;
$baseUrl = $baseUrl ?? '';
$showPages = 5; // Number of page links to show

if ($totalPages <= 1) return;

$start = max(1, $currentPage - floor($showPages / 2));
$end = min($totalPages, $start + $showPages - 1);
$start = max(1, $end - $showPages + 1);
?>

<div style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 2rem;">
    <!-- Previous -->
    <?php if ($currentPage > 1): ?>
        <a href="<?= htmlspecialchars($baseUrl) ?>?page=<?= $currentPage - 1 ?>" class="sk-btn sk-btn-outline">
            <i class="fas fa-chevron-left"></i> Previous
        </a>
    <?php endif; ?>

    <!-- Page Numbers -->
    <?php if ($start > 1): ?>
        <a href="<?= htmlspecialchars($baseUrl) ?>?page=1" class="sk-btn sk-btn-outline">1</a>
        <?php if ($start > 2): ?>
            <span style="padding: 0.5rem;">...</span>
        <?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start; $i <= $end; $i++): ?>
        <a href="<?= htmlspecialchars($baseUrl) ?>?page=<?= $i ?>"
           class="sk-btn <?= $i === $currentPage ? '' : 'sk-btn-outline' ?>"
           style="min-width: 40px;">
            <?= $i ?>
        </a>
    <?php endfor; ?>

    <?php if ($end < $totalPages): ?>
        <?php if ($end < $totalPages - 1): ?>
            <span style="padding: 0.5rem;">...</span>
        <?php endif; ?>
        <a href="<?= htmlspecialchars($baseUrl) ?>?page=<?= $totalPages ?>" class="sk-btn sk-btn-outline">
            <?= $totalPages ?>
        </a>
    <?php endif; ?>

    <!-- Next -->
    <?php if ($currentPage < $totalPages): ?>
        <a href="<?= htmlspecialchars($baseUrl) ?>?page=<?= $currentPage + 1 ?>" class="sk-btn sk-btn-outline">
            Next <i class="fas fa-chevron-right"></i>
        </a>
    <?php endif; ?>
</div>

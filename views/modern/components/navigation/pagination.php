<?php

/**
 * Component: Pagination
 *
 * Page navigation with previous/next and page numbers.
 *
 * @param int $currentPage Current page number (1-indexed)
 * @param int $totalPages Total number of pages
 * @param string $baseUrl Base URL for pagination links (page number appended)
 * @param int $showPages Number of page links to show (default: 5)
 * @param string $class Additional CSS classes
 * @param string $paramName Query parameter name for page (default: 'page')
 */

$currentPage = max(1, (int)($currentPage ?? 1));
$totalPages = max(1, (int)($totalPages ?? 1));
$baseUrl = $baseUrl ?? '';
$showPages = $showPages ?? 5;
$class = $class ?? '';
$paramName = $paramName ?? 'page';

// Don't show pagination if only one page
if ($totalPages <= 1) {
    return;
}

$cssClass = trim('pagination ' . $class);

// Calculate page range to show
$halfShow = floor($showPages / 2);
$startPage = max(1, $currentPage - $halfShow);
$endPage = min($totalPages, $startPage + $showPages - 1);

// Adjust start if we're near the end
if ($endPage - $startPage < $showPages - 1) {
    $startPage = max(1, $endPage - $showPages + 1);
}

// Build URL helper
$buildUrl = function($page) use ($baseUrl, $paramName) {
    $separator = strpos($baseUrl, '?') !== false ? '&' : '?';
    return $baseUrl . $separator . $paramName . '=' . $page;
};
?>

<nav class="<?= e($cssClass) ?>" aria-label="Pagination">
    <div class="pagination-controls component-pagination">
        <!-- Previous button -->
        <?php if ($currentPage > 1): ?>
            <a href="<?= e($buildUrl($currentPage - 1)) ?>" class="pagination-btn pagination-prev" aria-label="Previous page">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
        <?php else: ?>
            <span class="pagination-btn pagination-prev disabled" aria-disabled="true">
                <i class="fa-solid fa-chevron-left"></i>
            </span>
        <?php endif; ?>

        <!-- First page + ellipsis -->
        <?php if ($startPage > 1): ?>
            <a href="<?= e($buildUrl(1)) ?>" class="pagination-btn">1</a>
            <?php if ($startPage > 2): ?>
                <span class="pagination-ellipsis">...</span>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Page numbers -->
        <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
            <?php if ($page === $currentPage): ?>
                <span class="pagination-btn active" aria-current="page"><?= $page ?></span>
            <?php else: ?>
                <a href="<?= e($buildUrl($page)) ?>" class="pagination-btn"><?= $page ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <!-- Last page + ellipsis -->
        <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?>
                <span class="pagination-ellipsis">...</span>
            <?php endif; ?>
            <a href="<?= e($buildUrl($totalPages)) ?>" class="pagination-btn"><?= $totalPages ?></a>
        <?php endif; ?>

        <!-- Next button -->
        <?php if ($currentPage < $totalPages): ?>
            <a href="<?= e($buildUrl($currentPage + 1)) ?>" class="pagination-btn pagination-next" aria-label="Next page">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        <?php else: ?>
            <span class="pagination-btn pagination-next disabled" aria-disabled="true">
                <i class="fa-solid fa-chevron-right"></i>
            </span>
        <?php endif; ?>
    </div>
</nav>

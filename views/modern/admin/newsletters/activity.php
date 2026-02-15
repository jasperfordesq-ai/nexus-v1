<?php
/**
 * Newsletter Activity View - All Opens & Clicks
 * Holographic Glassmorphism Dark Theme
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin page configuration
$adminPageTitle = htmlspecialchars($newsletter['subject'] ?? 'Newsletter');
$adminPageSubtitle = 'Activity Log';
$adminPageIcon = 'fa-solid fa-clock-rotate-left';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<style>
    .activity-wrapper {
        padding: 0 40px 60px;
        position: relative;
        z-index: 10;
    }

    .activity-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    /* Back link */
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        font-size: 0.9rem;
        margin-bottom: 24px;
        transition: all 0.3s ease;
    }

    .back-link:hover {
        color: #a5b4fc;
    }

    /* Glass Card */
    .glass-card {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        backdrop-filter: blur(20px);
        margin-bottom: 20px;
        padding: 24px;
    }

    /* Header */
    .header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
    }

    .header-info h2 {
        margin: 0 0 5px 0;
        font-size: 1.2rem;
        color: #ffffff;
    }

    .header-info .subtitle {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.9rem;
    }

    /* Filter Tabs */
    .filter-tabs {
        display: flex;
        gap: 8px;
    }

    .filter-tab {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.6);
    }

    .filter-tab:hover {
        background: rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.9);
    }

    .filter-tab.active {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.3) 0%, rgba(139, 92, 246, 0.2) 100%);
        border-color: rgba(99, 102, 241, 0.4);
        color: #a5b4fc;
    }

    /* Tables */
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table thead tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    }

    .data-table th {
        text-align: left;
        padding: 12px 0;
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.5);
        text-transform: uppercase;
        font-weight: 600;
    }

    .data-table tbody tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .data-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.02);
    }

    .data-table td {
        padding: 14px 0;
        color: rgba(255, 255, 255, 0.8);
    }

    /* Event badges */
    .event-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .event-badge.open {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.3) 0%, rgba(37, 99, 235, 0.2) 100%);
        border: 1px solid rgba(59, 130, 246, 0.4);
        color: #93c5fd;
    }

    .event-badge.click {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.3) 0%, rgba(5, 150, 105, 0.2) 100%);
        border: 1px solid rgba(16, 185, 129, 0.4);
        color: #6ee7b7;
    }

    .activity-email {
        font-size: 0.95rem;
        color: #ffffff;
    }

    .activity-url {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.4);
        margin-top: 4px;
        word-break: break-all;
    }

    .activity-url a {
        color: #a5b4fc;
        text-decoration: none;
    }

    .activity-url a:hover {
        color: #c4b5fd;
        text-decoration: underline;
    }

    .activity-time {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.5);
        white-space: nowrap;
    }

    /* Pagination */
    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-top: 20px;
    }

    .pagination-info {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.9rem;
    }

    .pagination {
        display: flex;
        gap: 6px;
    }

    .pagination a,
    .pagination span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 10px;
        border-radius: 8px;
        font-size: 0.9rem;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .pagination a {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.6);
    }

    .pagination a:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #ffffff;
    }

    .pagination .current {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.3) 0%, rgba(139, 92, 246, 0.2) 100%);
        border: 1px solid rgba(99, 102, 241, 0.4);
        color: #a5b4fc;
    }

    .pagination .disabled {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.05);
        color: rgba(255, 255, 255, 0.3);
        cursor: not-allowed;
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: rgba(255, 255, 255, 0.5);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    .empty-state p {
        margin: 0;
        font-size: 1rem;
    }

    @media (max-width: 768px) {
        .activity-wrapper {
            padding: 0 20px 40px;
        }

        .header-row {
            flex-direction: column;
            align-items: flex-start;
        }

        .data-table th:nth-child(3),
        .data-table td:nth-child(3) {
            display: none;
        }
    }
</style>

<div class="activity-wrapper">
    <div class="activity-container">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/admin-legacy/newsletters/stats/<?= $newsletter['id'] ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Stats
        </a>

        <!-- Header -->
        <div class="glass-card">
            <div class="header-row">
                <div class="header-info">
                    <h2><?= htmlspecialchars($newsletter['subject']) ?></h2>
                    <div class="subtitle">
                        <?= number_format($totalCount) ?> total <?= $type ? ($type === 'open' ? 'opens' : 'clicks') : 'activities' ?>
                    </div>
                </div>

                <div class="filter-tabs">
                    <a href="<?= $basePath ?>/admin-legacy/newsletters/activity/<?= $newsletter['id'] ?>"
                       class="filter-tab <?= !$type ? 'active' : '' ?>">
                        All
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/newsletters/activity/<?= $newsletter['id'] ?>?type=open"
                       class="filter-tab <?= $type === 'open' ? 'active' : '' ?>">
                        <i class="fa-solid fa-envelope-open"></i> Opens
                    </a>
                    <a href="<?= $basePath ?>/admin-legacy/newsletters/activity/<?= $newsletter['id'] ?>?type=click"
                       class="filter-tab <?= $type === 'click' ? 'active' : '' ?>">
                        <i class="fa-solid fa-mouse-pointer"></i> Clicks
                    </a>
                </div>
            </div>

            <?php if (empty($activity)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-inbox"></i>
                    <p>No activity recorded yet.</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Event</th>
                            <th>Email</th>
                            <th style="width: 160px;">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activity as $item): ?>
                        <tr>
                            <td>
                                <?php if ($item['type'] === 'open'): ?>
                                    <span class="event-badge open">OPENED</span>
                                <?php else: ?>
                                    <span class="event-badge click">CLICKED</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="activity-email"><?= htmlspecialchars($item['email']) ?></div>
                                <?php if ($item['type'] === 'click' && !empty($item['url'])): ?>
                                    <div class="activity-url">
                                        <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank">
                                            <?= htmlspecialchars(strlen($item['url']) > 60 ? substr($item['url'], 0, 60) . '...' : $item['url']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="activity-time">
                                <?= date('M j, Y g:i a', strtotime($item['timestamp'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Showing <?= number_format(($page - 1) * $limit + 1) ?>-<?= number_format(min($page * $limit, $totalCount)) ?> of <?= number_format($totalCount) ?>
                    </div>

                    <div class="pagination">
                        <?php
                        $queryParams = $type ? "type={$type}&" : '';

                        if ($page > 1): ?>
                            <a href="<?= $basePath ?>/admin-legacy/newsletters/activity/<?= $newsletter['id'] ?>?<?= $queryParams ?>page=<?= $page - 1 ?>">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled"><i class="fa-solid fa-chevron-left"></i></span>
                        <?php endif; ?>

                        <?php
                        // Show page numbers
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);

                        if ($startPage > 1): ?>
                            <a href="<?= $basePath ?>/admin-legacy/newsletters/activity/<?= $newsletter['id'] ?>?<?= $queryParams ?>page=1">1</a>
                            <?php if ($startPage > 2): ?>
                                <span class="disabled">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= $basePath ?>/admin-legacy/newsletters/activity/<?= $newsletter['id'] ?>?<?= $queryParams ?>page=<?= $i ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <span class="disabled">...</span>
                            <?php endif; ?>
                            <a href="<?= $basePath ?>/admin-legacy/newsletters/activity/<?= $newsletter['id'] ?>?<?= $queryParams ?>page=<?= $totalPages ?>"><?= $totalPages ?></a>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?= $basePath ?>/admin-legacy/newsletters/activity/<?= $newsletter['id'] ?>?<?= $queryParams ?>page=<?= $page + 1 ?>">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled"><i class="fa-solid fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>

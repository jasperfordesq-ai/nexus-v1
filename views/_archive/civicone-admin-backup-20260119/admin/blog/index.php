<?php
/**
 * Admin Blog/News - Gold Standard
 * STANDALONE admin interface
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Blog';
$adminPageSubtitle = 'Content Management';
$adminPageIcon = 'fa-blog';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-blog"></i>
            News Room
        </h1>
        <p class="admin-page-subtitle">Manage articles and announcements</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/news/create" class="admin-btn admin-btn-primary">
            <i class="fa-solid fa-plus"></i>
            New Article
        </a>
    </div>
</div>

<!-- Blog Posts Card -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-purple">
            <i class="fa-solid fa-newspaper"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Published Articles</h3>
            <p class="admin-card-subtitle"><?= count($posts ?? []) ?> articles</p>
        </div>
    </div>
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($posts)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-pen-fancy"></i>
            </div>
            <h3 class="admin-empty-title">No stories yet</h3>
            <p class="admin-empty-text">Share your first update with the community.</p>
            <a href="<?= $basePath ?>/admin-legacy/news/create" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                <i class="fa-solid fa-plus"></i>
                Create Your First Article
            </a>
        </div>
        <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th class="hide-mobile">Status</th>
                        <th class="hide-tablet">Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post): ?>
                    <tr>
                        <td>
                            <div class="admin-article-cell">
                                <div class="admin-article-info">
                                    <div class="admin-article-title"><?= htmlspecialchars($post['title']) ?></div>
                                    <div class="admin-article-author">By <?= htmlspecialchars($post['author_name'] ?? 'Unknown') ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="hide-mobile">
                            <?php if ($post['status'] === 'published'): ?>
                                <span class="admin-status-badge admin-status-active">
                                    <span class="admin-status-dot"></span> Published
                                </span>
                            <?php else: ?>
                                <span class="admin-status-badge admin-status-pending">
                                    <span class="admin-status-dot"></span> Draft
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="hide-tablet admin-date-cell">
                            <?= date('M j, Y', strtotime($post['created_at'])) ?>
                        </td>
                        <td style="text-align: right;">
                            <div class="admin-action-buttons">
                                <a href="<?= $basePath ?>/admin-legacy/news/builder/<?= $post['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                                <a href="<?= $basePath ?>/admin-legacy/news/delete/<?= $post['id'] ?>"
                                   onclick="return confirm('Delete this article?')"
                                   class="admin-btn admin-btn-danger admin-btn-sm">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Blog-specific styles */
.admin-article-cell {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.admin-article-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.admin-article-title {
    font-weight: 600;
    color: #fff;
    font-size: 0.95rem;
}

.admin-article-author {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

.admin-date-cell {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
}

.admin-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 500;
}

.admin-status-badge .admin-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.admin-status-active {
    color: #22c55e;
}

.admin-status-active .admin-status-dot {
    background: #22c55e;
    box-shadow: 0 0 8px rgba(34, 197, 94, 0.5);
}

.admin-status-pending {
    color: #f59e0b;
}

.admin-status-pending .admin-status-dot {
    background: #f59e0b;
    box-shadow: 0 0 8px rgba(245, 158, 11, 0.5);
}

.admin-action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: 1px solid rgba(99, 102, 241, 0.5);
}

.admin-btn-primary:hover {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    transform: translateY(-1px);
}

.admin-btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
}

.admin-btn-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.admin-btn-danger:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: rgba(239, 68, 68, 0.5);
}

.admin-empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.admin-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 20px;
    background: rgba(139, 92, 246, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.3);
}

.admin-empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    margin: 0 0 0.5rem 0;
}

.admin-empty-text {
    color: rgba(255, 255, 255, 0.5);
    margin: 0;
}

/* Table Styles */
.admin-table-wrapper {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    text-align: left;
    padding: 1rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    vertical-align: middle;
}

.admin-table tbody tr {
    transition: background 0.15s ease;
}

.admin-table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

/* Responsive */
@media (max-width: 1024px) {
    .hide-tablet {
        display: none;
    }
}

@media (max-width: 768px) {
    .hide-mobile {
        display: none;
    }

    .admin-table th,
    .admin-table td {
        padding: 0.75rem 1rem;
    }

    .admin-action-buttons {
        flex-direction: column;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>

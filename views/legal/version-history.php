<?php
/**
 * Public Legal Document Version History
 * Shows all published versions of a legal document
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$layout = layout();

// Determine theme color based on document type
$themeColors = [
    'terms' => ['primary' => '#3b82f6', 'rgb' => '59, 130, 246'],
    'privacy' => ['primary' => '#6366f1', 'rgb' => '99, 102, 241'],
    'cookies' => ['primary' => '#f59e0b', 'rgb' => '245, 158, 11'],
    'accessibility' => ['primary' => '#10b981', 'rgb' => '16, 185, 129'],
];
$theme = $themeColors[$document['document_type']] ?? $themeColors['terms'];

// Icons
$icons = [
    'terms' => 'fa-file-contract',
    'privacy' => 'fa-shield-halved',
    'cookies' => 'fa-cookie-bite',
    'accessibility' => 'fa-universal-access',
];
$icon = $icons[$document['document_type']] ?? 'fa-file-lines';

require __DIR__ . '/../layouts/' . $layout . '/header.php';
?>

<style>
/* Version History Wrapper */
#version-history-wrapper {
    --legal-theme: <?= $theme['primary'] ?>;
    --legal-theme-rgb: <?= $theme['rgb'] ?>;
    position: relative;
    min-height: 100vh;
    padding: 160px 1rem 4rem;
}

@media (max-width: 900px) {
    #version-history-wrapper {
        padding-top: 120px;
    }
}

#version-history-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    transition: opacity 0.3s ease;
}

[data-theme="light"] #version-history-wrapper::before {
    background: linear-gradient(135deg,
        rgba(var(--legal-theme-rgb), 0.08) 0%,
        rgba(var(--legal-theme-rgb), 0.04) 50%,
        rgba(var(--legal-theme-rgb), 0.08) 100%);
}

[data-theme="dark"] #version-history-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(var(--legal-theme-rgb), 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(var(--legal-theme-rgb), 0.1) 0%, transparent 50%);
}

#version-history-wrapper .history-inner {
    max-width: 800px;
    margin: 0 auto;
}

/* Back Link */
#version-history-wrapper .back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    color: var(--legal-theme);
    transition: all 0.2s ease;
}

#version-history-wrapper .back-link:hover {
    gap: 0.75rem;
}

/* Page Header */
#version-history-wrapper .history-header {
    text-align: center;
    margin-bottom: 2.5rem;
}

#version-history-wrapper .history-header h1 {
    font-size: 2rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#version-history-wrapper .history-header .header-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--legal-theme) 0%, color-mix(in srgb, var(--legal-theme) 80%, black) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    box-shadow: 0 6px 20px rgba(var(--legal-theme-rgb), 0.4);
}

#version-history-wrapper .history-header p {
    color: var(--htb-text-muted);
    font-size: 1rem;
}

/* Timeline */
#version-history-wrapper .version-timeline {
    position: relative;
    padding-left: 2rem;
}

#version-history-wrapper .version-timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, var(--legal-theme), rgba(var(--legal-theme-rgb), 0.2));
}

#version-history-wrapper .version-item {
    position: relative;
    margin-bottom: 1.5rem;
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 16px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

[data-theme="light"] #version-history-wrapper .version-item {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(var(--legal-theme-rgb), 0.15);
    box-shadow: 0 4px 16px rgba(var(--legal-theme-rgb), 0.08);
}

[data-theme="dark"] #version-history-wrapper .version-item {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(var(--legal-theme-rgb), 0.2);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

#version-history-wrapper .version-item:hover {
    transform: translateX(4px);
}

#version-history-wrapper .version-item.current {
    border-color: var(--legal-theme);
}

/* Timeline dot */
#version-history-wrapper .version-item::before {
    content: '';
    position: absolute;
    left: -1.75rem;
    top: 1.75rem;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--htb-bg-main);
    border: 3px solid rgba(var(--legal-theme-rgb), 0.4);
}

#version-history-wrapper .version-item.current::before {
    background: var(--legal-theme);
    border-color: var(--legal-theme);
    box-shadow: 0 0 12px rgba(var(--legal-theme-rgb), 0.6);
}

/* Version header */
#version-history-wrapper .version-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

#version-history-wrapper .version-number {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--htb-text-main);
}

#version-history-wrapper .current-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    background: rgba(var(--legal-theme-rgb), 0.15);
    color: var(--legal-theme);
}

#version-history-wrapper .version-label {
    font-size: 0.9rem;
    color: var(--htb-text-muted);
    font-weight: 500;
}

/* Version meta */
#version-history-wrapper .version-meta {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

#version-history-wrapper .meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--htb-text-muted);
}

#version-history-wrapper .meta-item i {
    color: var(--legal-theme);
    opacity: 0.7;
}

/* Version summary */
#version-history-wrapper .version-summary {
    font-size: 0.9rem;
    line-height: 1.6;
    color: var(--htb-text-muted);
    margin-bottom: 1rem;
    padding: 0.75rem 1rem;
    border-radius: 8px;
}

[data-theme="light"] #version-history-wrapper .version-summary {
    background: rgba(var(--legal-theme-rgb), 0.05);
}

[data-theme="dark"] #version-history-wrapper .version-summary {
    background: rgba(0, 0, 0, 0.2);
}

/* Version actions */
#version-history-wrapper .version-actions {
    display: flex;
    gap: 0.5rem;
}

#version-history-wrapper .view-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}

#version-history-wrapper .view-btn.primary {
    background: linear-gradient(135deg, var(--legal-theme), color-mix(in srgb, var(--legal-theme) 80%, black));
    color: white;
}

#version-history-wrapper .view-btn.primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--legal-theme-rgb), 0.4);
}

#version-history-wrapper .view-btn.secondary {
    background: rgba(var(--legal-theme-rgb), 0.1);
    color: var(--legal-theme);
}

#version-history-wrapper .view-btn.secondary:hover {
    background: rgba(var(--legal-theme-rgb), 0.2);
}

/* Empty state */
#version-history-wrapper .empty-state {
    text-align: center;
    padding: 3rem 2rem;
    backdrop-filter: blur(20px);
    border-radius: 16px;
}

[data-theme="light"] #version-history-wrapper .empty-state {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(var(--legal-theme-rgb), 0.15);
}

[data-theme="dark"] #version-history-wrapper .empty-state {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(var(--legal-theme-rgb), 0.2);
}

#version-history-wrapper .empty-state i {
    font-size: 3rem;
    color: rgba(var(--legal-theme-rgb), 0.3);
    margin-bottom: 1rem;
}

#version-history-wrapper .empty-state h3 {
    color: var(--htb-text-main);
    margin: 0 0 0.5rem 0;
}

#version-history-wrapper .empty-state p {
    color: var(--htb-text-muted);
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    #version-history-wrapper {
        padding: 120px 1rem 3rem;
    }

    #version-history-wrapper .history-header h1 {
        font-size: 1.5rem;
        flex-direction: column;
        gap: 0.75rem;
    }

    #version-history-wrapper .version-meta {
        flex-direction: column;
        gap: 0.5rem;
    }

    #version-history-wrapper .version-timeline {
        padding-left: 1.5rem;
    }

    #version-history-wrapper .version-item::before {
        left: -1.25rem;
    }
}

/* Focus states */
#version-history-wrapper .back-link:focus-visible,
#version-history-wrapper .view-btn:focus-visible {
    outline: 3px solid rgba(var(--legal-theme-rgb), 0.5);
    outline-offset: 2px;
}
</style>

<div id="version-history-wrapper">
    <div class="history-inner">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/<?= htmlspecialchars($document['slug']) ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to <?= htmlspecialchars($document['title']) ?>
        </a>

        <!-- Page Header -->
        <div class="history-header">
            <h1>
                <span class="header-icon"><i class="fa-solid fa-history"></i></span>
                Version History
            </h1>
            <p><?= htmlspecialchars($document['title']) ?></p>
        </div>

        <!-- Version Timeline -->
        <?php if (!empty($versions)): ?>
        <div class="version-timeline">
            <?php foreach ($versions as $version): ?>
            <div class="version-item <?= $version['id'] === $document['current_version_id'] ? 'current' : '' ?>">
                <div class="version-header">
                    <span class="version-number">Version <?= htmlspecialchars($version['version_number']) ?></span>
                    <?php if ($version['id'] === $document['current_version_id']): ?>
                    <span class="current-badge">
                        <i class="fa-solid fa-check-circle"></i> Current
                    </span>
                    <?php endif; ?>
                </div>

                <?php if ($version['version_label']): ?>
                <div class="version-label"><?= htmlspecialchars($version['version_label']) ?></div>
                <?php endif; ?>

                <div class="version-meta">
                    <div class="meta-item">
                        <i class="fa-solid fa-calendar"></i>
                        Effective: <?= date('F j, Y', strtotime($version['effective_date'])) ?>
                    </div>
                    <?php if (!$version['is_draft'] && $version['published_at']): ?>
                    <div class="meta-item">
                        <i class="fa-solid fa-rocket"></i>
                        Published: <?= date('M j, Y', strtotime($version['published_at'])) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($version['summary_of_changes']): ?>
                <div class="version-summary">
                    <strong>Changes:</strong> <?= htmlspecialchars($version['summary_of_changes']) ?>
                </div>
                <?php endif; ?>

                <div class="version-actions">
                    <?php if ($version['id'] === $document['current_version_id']): ?>
                    <a href="<?= $basePath ?>/<?= htmlspecialchars($document['slug']) ?>" class="view-btn primary">
                        <i class="fa-solid fa-eye"></i> View Current
                    </a>
                    <?php else: ?>
                    <a href="<?= $basePath ?>/legal/version/<?= $version['id'] ?>" class="view-btn secondary">
                        <i class="fa-solid fa-archive"></i> View Archived
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <h3>No Version History</h3>
            <p>There are no published versions of this document yet.</p>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require __DIR__ . '/../layouts/' . $layout . '/footer.php'; ?>

<?php
/**
 * Public Archived Legal Document Version View
 * Displays a specific archived version of a legal document
 */

use Nexus\Core\TenantContext;
use Nexus\Services\LegalDocumentService;

$basePath = TenantContext::getBasePath();
$layout = layout();

// Get document info
$document = LegalDocumentService::getById($version['document_id']);
$documentType = $document['document_type'] ?? 'terms';

// Determine theme color based on document type
$themeColors = [
    'terms' => ['primary' => '#3b82f6', 'rgb' => '59, 130, 246'],
    'privacy' => ['primary' => '#6366f1', 'rgb' => '99, 102, 241'],
    'cookies' => ['primary' => '#f59e0b', 'rgb' => '245, 158, 11'],
    'accessibility' => ['primary' => '#10b981', 'rgb' => '16, 185, 129'],
];
$theme = $themeColors[$documentType] ?? $themeColors['terms'];

// Icons
$icons = [
    'terms' => 'fa-file-contract',
    'privacy' => 'fa-shield-halved',
    'cookies' => 'fa-cookie-bite',
    'accessibility' => 'fa-universal-access',
];
$icon = $icons[$documentType] ?? 'fa-file-lines';

require __DIR__ . '/../layouts/' . $layout . '/header.php';
?>

<style>
/* Archived Version Wrapper */
#archived-doc-wrapper {
    --legal-theme: <?= $theme['primary'] ?>;
    --legal-theme-rgb: <?= $theme['rgb'] ?>;
    position: relative;
    min-height: 100vh;
    padding: 160px 1rem 4rem;
}

@media (max-width: 900px) {
    #archived-doc-wrapper {
        padding-top: 120px;
    }
}

#archived-doc-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    background:
        radial-gradient(ellipse at 20% 20%, rgba(100, 116, 139, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(100, 116, 139, 0.08) 0%, transparent 50%);
}

#archived-doc-wrapper .archived-inner {
    max-width: 900px;
    margin: 0 auto;
}

/* Archived Banner */
#archived-doc-wrapper .archived-banner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    text-align: center;
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

#archived-doc-wrapper .archived-banner i {
    color: #fbbf24;
    font-size: 1.25rem;
}

#archived-doc-wrapper .archived-banner-content {
    color: var(--htb-text-main);
    font-size: 0.9rem;
}

#archived-doc-wrapper .archived-banner-content strong {
    color: #fbbf24;
}

#archived-doc-wrapper .archived-banner .view-current-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    background: linear-gradient(135deg, var(--legal-theme), color-mix(in srgb, var(--legal-theme) 80%, black));
    color: white;
    margin-left: 1rem;
    transition: all 0.2s;
}

#archived-doc-wrapper .archived-banner .view-current-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--legal-theme-rgb), 0.4);
}

/* Back Link */
#archived-doc-wrapper .back-link {
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

#archived-doc-wrapper .back-link:hover {
    gap: 0.75rem;
}

/* Page Header */
#archived-doc-wrapper .archived-header {
    text-align: center;
    margin-bottom: 2rem;
}

#archived-doc-wrapper .archived-header h1 {
    font-size: 2rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    opacity: 0.8;
}

#archived-doc-wrapper .archived-header .header-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: linear-gradient(135deg, #64748b, #475569);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

#archived-doc-wrapper .archived-header p {
    color: var(--htb-text-muted);
    font-size: 1rem;
}

/* Version Info */
#archived-doc-wrapper .version-info {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

#archived-doc-wrapper .version-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    background: rgba(100, 116, 139, 0.15);
    color: var(--htb-text-muted);
}

#archived-doc-wrapper .version-badge.archived-tag {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
}

/* Content Card */
#archived-doc-wrapper .archived-content-card {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 2.5rem;
    position: relative;
    overflow: hidden;
    opacity: 0.9;
}

[data-theme="light"] #archived-doc-wrapper .archived-content-card {
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(100, 116, 139, 0.2);
    box-shadow: 0 8px 32px rgba(100, 116, 139, 0.1);
}

[data-theme="dark"] #archived-doc-wrapper .archived-content-card {
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(100, 116, 139, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

/* Content Typography */
#archived-doc-wrapper .archived-content {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--htb-text-main);
    line-height: 1.8;
    font-size: 1.05rem;
}

#archived-doc-wrapper .archived-content p {
    margin-bottom: 1.25rem;
}

#archived-doc-wrapper .archived-content h1,
#archived-doc-wrapper .archived-content h2,
#archived-doc-wrapper .archived-content h3,
#archived-doc-wrapper .archived-content h4 {
    color: var(--htb-text-main);
    font-weight: 700;
    margin-top: 2rem;
    margin-bottom: 1rem;
}

#archived-doc-wrapper .archived-content h2 {
    font-size: 1.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid rgba(100, 116, 139, 0.2);
}

#archived-doc-wrapper .archived-content ul,
#archived-doc-wrapper .archived-content ol {
    margin: 1rem 0;
    padding-left: 1.5rem;
}

#archived-doc-wrapper .archived-content li {
    margin-bottom: 0.5rem;
    color: var(--htb-text-muted);
}

#archived-doc-wrapper .archived-content a {
    color: var(--legal-theme);
    text-decoration: none;
    font-weight: 600;
}

#archived-doc-wrapper .archived-content a:hover {
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 768px) {
    #archived-doc-wrapper {
        padding: 120px 1rem 3rem;
    }

    #archived-doc-wrapper .archived-header h1 {
        font-size: 1.5rem;
        flex-direction: column;
        gap: 0.75rem;
    }

    #archived-doc-wrapper .archived-content-card {
        padding: 1.5rem;
    }

    #archived-doc-wrapper .archived-content {
        font-size: 1rem;
    }

    #archived-doc-wrapper .archived-banner {
        flex-direction: column;
        text-align: center;
    }

    #archived-doc-wrapper .archived-banner .view-current-btn {
        margin-left: 0;
        margin-top: 0.75rem;
    }
}

/* Focus states */
#archived-doc-wrapper .back-link:focus-visible,
#archived-doc-wrapper .view-current-btn:focus-visible {
    outline: 3px solid rgba(var(--legal-theme-rgb), 0.5);
    outline-offset: 2px;
}
</style>

<div id="archived-doc-wrapper">
    <div class="archived-inner">

        <!-- Archived Banner -->
        <?php if ($isArchived): ?>
        <div class="archived-banner">
            <i class="fa-solid fa-archive"></i>
            <span class="archived-banner-content">
                <strong>Archived Version</strong> - This is version <?= htmlspecialchars($version['version_number']) ?>, which is no longer current.
            </span>
            <a href="<?= $basePath ?>/<?= htmlspecialchars($document['slug']) ?>" class="view-current-btn">
                <i class="fa-solid fa-arrow-right"></i> View Current Version
            </a>
        </div>
        <?php endif; ?>

        <!-- Back Link -->
        <a href="<?= $basePath ?>/<?= htmlspecialchars($document['slug']) ?>/versions" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Version History
        </a>

        <!-- Page Header -->
        <div class="archived-header">
            <h1>
                <span class="header-icon"><i class="fa-solid <?= $icon ?>"></i></span>
                <?= htmlspecialchars($document['title']) ?>
            </h1>
            <p>Archived Version</p>

            <!-- Version Info -->
            <div class="version-info">
                <span class="version-badge archived-tag">
                    <i class="fa-solid fa-archive"></i>
                    Archived
                </span>
                <span class="version-badge">
                    <i class="fa-solid fa-code-branch"></i>
                    Version <?= htmlspecialchars($version['version_number']) ?>
                </span>
                <span class="version-badge">
                    <i class="fa-solid fa-calendar"></i>
                    Effective: <?= date('F j, Y', strtotime($version['effective_date'])) ?>
                </span>
            </div>
        </div>

        <!-- Content Card -->
        <div class="archived-content-card">
            <div class="archived-content">
                <?= $version['content'] ?>
            </div>
        </div>

    </div>
</div>

<?php require __DIR__ . '/../layouts/' . $layout . '/footer.php'; ?>

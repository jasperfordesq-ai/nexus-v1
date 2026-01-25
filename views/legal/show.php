<?php
/**
 * Public Legal Document View
 * Displays legal documents with version information and acceptance tracking
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Auth;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$layout = layout();

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
/* Legal Document Glass Wrapper */
#legal-doc-wrapper {
    --legal-theme: <?= $theme['primary'] ?>;
    --legal-theme-rgb: <?= $theme['rgb'] ?>;
    position: relative;
    min-height: 100vh;
    padding: 160px 1rem 4rem;
}

@media (max-width: 900px) {
    #legal-doc-wrapper {
        padding-top: 120px;
    }
}

#legal-doc-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    transition: opacity 0.3s ease;
}

[data-theme="light"] #legal-doc-wrapper::before {
    background: linear-gradient(135deg,
        rgba(var(--legal-theme-rgb), 0.08) 0%,
        rgba(var(--legal-theme-rgb), 0.04) 50%,
        rgba(var(--legal-theme-rgb), 0.08) 100%);
}

[data-theme="dark"] #legal-doc-wrapper::before {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(var(--legal-theme-rgb), 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(var(--legal-theme-rgb), 0.1) 0%, transparent 50%);
}

#legal-doc-wrapper .legal-inner {
    max-width: 900px;
    margin: 0 auto;
}

/* Page Header */
#legal-doc-wrapper .legal-header {
    text-align: center;
    margin-bottom: 2rem;
}

#legal-doc-wrapper .legal-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#legal-doc-wrapper .legal-header .header-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--legal-theme) 0%, color-mix(in srgb, var(--legal-theme) 80%, black) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: white;
    box-shadow: 0 8px 24px rgba(var(--legal-theme-rgb), 0.4);
}

#legal-doc-wrapper .legal-header p {
    color: var(--htb-text-muted);
    font-size: 1.15rem;
    margin: 0 auto;
    max-width: 600px;
}

/* Version Badge */
#legal-doc-wrapper .version-info {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 1.25rem;
    flex-wrap: wrap;
}

#legal-doc-wrapper .version-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
}

[data-theme="light"] #legal-doc-wrapper .version-badge {
    background: rgba(var(--legal-theme-rgb), 0.1);
    color: var(--legal-theme);
}

[data-theme="dark"] #legal-doc-wrapper .version-badge {
    background: rgba(var(--legal-theme-rgb), 0.2);
    color: color-mix(in srgb, var(--legal-theme) 70%, white);
}

#legal-doc-wrapper .version-link {
    color: inherit;
    text-decoration: none;
    opacity: 0.8;
    transition: opacity 0.2s;
}

#legal-doc-wrapper .version-link:hover {
    opacity: 1;
    text-decoration: underline;
}

/* Content Card */
#legal-doc-wrapper .legal-content-card {
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border-radius: 20px;
    padding: 2.5rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

[data-theme="light"] #legal-doc-wrapper .legal-content-card {
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(var(--legal-theme-rgb), 0.15);
    box-shadow: 0 8px 32px rgba(var(--legal-theme-rgb), 0.1);
}

[data-theme="dark"] #legal-doc-wrapper .legal-content-card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(var(--legal-theme-rgb), 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

/* Content Typography */
#legal-doc-wrapper .legal-content {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--htb-text-main);
    line-height: 1.8;
    font-size: 1.05rem;
}

#legal-doc-wrapper .legal-content p {
    margin-bottom: 1.25rem;
}

#legal-doc-wrapper .legal-content h1,
#legal-doc-wrapper .legal-content h2,
#legal-doc-wrapper .legal-content h3,
#legal-doc-wrapper .legal-content h4 {
    color: var(--htb-text-main);
    font-weight: 700;
    margin-top: 2rem;
    margin-bottom: 1rem;
}

#legal-doc-wrapper .legal-content h2 {
    font-size: 1.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid rgba(var(--legal-theme-rgb), 0.2);
}

#legal-doc-wrapper .legal-content ul,
#legal-doc-wrapper .legal-content ol {
    margin: 1rem 0;
    padding-left: 1.5rem;
}

#legal-doc-wrapper .legal-content li {
    margin-bottom: 0.5rem;
    color: var(--htb-text-muted);
}

#legal-doc-wrapper .legal-content a {
    color: var(--legal-theme);
    text-decoration: none;
    font-weight: 600;
}

#legal-doc-wrapper .legal-content a:hover {
    text-decoration: underline;
}

#legal-doc-wrapper .legal-content strong {
    color: var(--htb-text-main);
}

/* Acceptance Banner */
#legal-doc-wrapper .acceptance-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-top: 2rem;
}

#legal-doc-wrapper .acceptance-banner.accepted {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
}

#legal-doc-wrapper .acceptance-banner.pending {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

#legal-doc-wrapper .acceptance-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

#legal-doc-wrapper .acceptance-banner.accepted .acceptance-info i {
    color: #4ade80;
}

#legal-doc-wrapper .acceptance-banner.pending .acceptance-info i {
    color: #fbbf24;
}

#legal-doc-wrapper .acceptance-text {
    font-size: 0.9rem;
}

#legal-doc-wrapper .acceptance-banner.accepted .acceptance-text {
    color: #4ade80;
}

#legal-doc-wrapper .acceptance-banner.pending .acceptance-text {
    color: #fbbf24;
}

#legal-doc-wrapper .accept-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1.25rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

#legal-doc-wrapper .accept-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.4);
}

/* Back Link */
#legal-doc-wrapper .back-link {
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

#legal-doc-wrapper .back-link:hover {
    gap: 0.75rem;
}

/* Responsive */
@media (max-width: 768px) {
    #legal-doc-wrapper {
        padding: 120px 1rem 3rem;
    }

    #legal-doc-wrapper .legal-header h1 {
        font-size: 1.85rem;
        flex-direction: column;
        gap: 1rem;
    }

    #legal-doc-wrapper .legal-header .header-icon {
        width: 56px;
        height: 56px;
        font-size: 1.5rem;
    }

    #legal-doc-wrapper .legal-content-card {
        padding: 1.5rem;
    }

    #legal-doc-wrapper .legal-content {
        font-size: 1rem;
    }

    #legal-doc-wrapper .acceptance-banner {
        flex-direction: column;
        text-align: center;
    }
}

/* Focus states */
#legal-doc-wrapper .back-link:focus-visible,
#legal-doc-wrapper .accept-btn:focus-visible {
    outline: 3px solid rgba(var(--legal-theme-rgb), 0.5);
    outline-offset: 2px;
}
</style>

<div id="legal-doc-wrapper">
    <div class="legal-inner">

        <!-- Back Link -->
        <a href="<?= $basePath ?>/legal" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Legal Hub
        </a>

        <!-- Page Header -->
        <div class="legal-header">
            <h1>
                <span class="header-icon"><i class="fa-solid <?= $icon ?>"></i></span>
                <?= htmlspecialchars($document['title']) ?>
            </h1>
            <p><?= htmlspecialchars(TenantContext::get()['name'] ?? 'This Community') ?> <?= htmlspecialchars($document['title']) ?></p>

            <!-- Version Info -->
            <div class="version-info">
                <span class="version-badge">
                    <i class="fa-solid fa-code-branch"></i>
                    Version <?= htmlspecialchars($document['version_number']) ?>
                </span>
                <span class="version-badge">
                    <i class="fa-solid fa-calendar"></i>
                    Effective: <?= date('F j, Y', strtotime($document['effective_date'])) ?>
                </span>
                <a href="<?= $basePath ?>/<?= $document['slug'] ?>/versions" class="version-badge version-link">
                    <i class="fa-solid fa-history"></i>
                    View Version History
                </a>
            </div>
        </div>

        <!-- Content Card -->
        <div class="legal-content-card">
            <div class="legal-content">
                <?= $document['content'] ?>
            </div>

            <?php if (Auth::check() && $document['requires_acceptance']): ?>
            <!-- Acceptance Status -->
            <?php if ($acceptanceStatus === 'current'): ?>
            <div class="acceptance-banner accepted">
                <div class="acceptance-info">
                    <i class="fa-solid fa-check-circle fa-lg"></i>
                    <span class="acceptance-text">You have accepted this version of <?= htmlspecialchars($document['title']) ?></span>
                </div>
            </div>
            <?php else: ?>
            <div class="acceptance-banner pending">
                <div class="acceptance-info">
                    <i class="fa-solid fa-exclamation-circle fa-lg"></i>
                    <span class="acceptance-text">Please review and accept the updated <?= htmlspecialchars($document['title']) ?></span>
                </div>
                <button type="button" class="accept-btn" onclick="acceptDocument(<?= $document['id'] ?>, <?= $document['current_version_id'] ?>)">
                    <i class="fa-solid fa-check"></i> I Accept
                </button>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php if (Auth::check()): ?>
<script>
function acceptDocument(documentId, versionId) {
    fetch('<?= $basePath ?>/api/legal/accept', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            document_id: documentId,
            version_id: versionId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the UI
            const banner = document.querySelector('.acceptance-banner');
            banner.className = 'acceptance-banner accepted';
            banner.innerHTML = `
                <div class="acceptance-info">
                    <i class="fa-solid fa-check-circle fa-lg"></i>
                    <span class="acceptance-text">Thank you! You have accepted this document.</span>
                </div>
            `;
        } else {
            alert('Failed to record acceptance. Please try again.');
        }
    })
    .catch(err => {
        console.error('Acceptance error:', err);
        alert('An error occurred. Please try again.');
    });
}
</script>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/' . $layout . '/footer.php'; ?>

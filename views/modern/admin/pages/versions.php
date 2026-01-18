<?php
/**
 * Admin Page Version History - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Version History';
$adminPageSubtitle = 'Pages';
$adminPageIcon = 'fa-clock-rotate-left';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

$versions = $versions ?? [];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/pages/builder/<?= $page['id'] ?>" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Version History
        </h1>
        <p class="admin-page-subtitle"><?= htmlspecialchars($page['title']) ?> - <?= count($versions) ?> version<?= count($versions) !== 1 ? 's' : '' ?> saved</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/pages/builder/<?= $page['id'] ?>" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-pen-to-square"></i> Back to Editor
        </a>
    </div>
</div>

<!-- Current Version Card -->
<div class="admin-glass-card version-current" style="max-width: 900px;">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
            <i class="fa-solid fa-star"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Current Version</h3>
            <p class="admin-card-subtitle">
                Last updated: <?= $page['updated_at'] ? date('M j, Y g:i A', strtotime($page['updated_at'])) : 'Just now' ?>
            </p>
        </div>
        <span class="version-badge version-badge-current">
            <i class="fa-solid fa-check"></i> Live
        </span>
    </div>
    <div class="admin-card-body">
        <div class="version-info">
            <div class="version-title"><?= htmlspecialchars($page['title']) ?></div>
            <div class="version-slug"><i class="fa-solid fa-link"></i> /page/<?= htmlspecialchars($page['slug']) ?></div>
        </div>
        <div class="version-actions">
            <a href="<?= $basePath ?>/admin/pages/preview/<?= $page['id'] ?>" class="admin-btn admin-btn-sm admin-btn-secondary" target="_blank">
                <i class="fa-solid fa-eye"></i> Preview
            </a>
        </div>
    </div>
</div>

<!-- Version History -->
<?php if (empty($versions)): ?>
<div class="admin-glass-card" style="max-width: 900px;">
    <div class="empty-state">
        <div class="empty-state-icon">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <h3>No Previous Versions</h3>
        <p>Versions are automatically saved each time you update the page. Make some changes to see version history here.</p>
    </div>
</div>
<?php else: ?>
<div class="versions-timeline" style="max-width: 900px;">
    <div class="timeline-line"></div>

    <?php foreach ($versions as $index => $version): ?>
    <div class="admin-glass-card version-card" data-version-id="<?= $version['id'] ?>">
        <div class="timeline-dot"></div>
        <div class="version-card-header">
            <div class="version-card-info">
                <span class="version-badge">
                    <i class="fa-solid fa-code-branch"></i> v<?= $version['version_number'] ?>
                </span>
                <span class="version-date">
                    <i class="fa-solid fa-clock"></i>
                    <?= date('M j, Y g:i A', strtotime($version['created_at'])) ?>
                </span>
                <?php if (!empty($version['restore_note'])): ?>
                <span class="version-note">
                    <i class="fa-solid fa-rotate-left"></i> <?= htmlspecialchars($version['restore_note']) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="version-card-body">
            <div class="version-title"><?= htmlspecialchars($version['title']) ?></div>
            <div class="version-slug"><i class="fa-solid fa-link"></i> /page/<?= htmlspecialchars($version['slug']) ?></div>
            <?php if (!empty($version['content'])): ?>
            <div class="version-preview-text">
                <?= htmlspecialchars(substr(strip_tags($version['content']), 0, 150)) ?>...
            </div>
            <?php endif; ?>
        </div>
        <div class="version-card-footer">
            <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary" onclick="previewVersion(<?= $version['id'] ?>)">
                <i class="fa-solid fa-eye"></i> Preview
            </button>
            <button type="button" class="admin-btn admin-btn-sm admin-btn-primary" onclick="restoreVersion(<?= $version['id'] ?>, <?= $version['version_number'] ?>)">
                <i class="fa-solid fa-rotate-left"></i> Restore
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Version Preview Modal -->
<div id="versionPreviewModal" class="version-modal">
    <div class="version-modal-content">
        <div class="version-modal-header">
            <h3><i class="fa-solid fa-eye"></i> Version Preview</h3>
            <button type="button" class="modal" role="dialog" aria-modal="true"-close-btn" onclick="closePreviewModal()">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="version-modal-body" id="versionPreviewContent">
            <div class="loading-state">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <span>Loading preview...</span>
            </div>
        </div>
    </div>
</div>

<style>
.back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 0.7;
}

/* Version Badges */
.version-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.85rem;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.7);
}

.version-badge-current {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: #fff;
}

/* Current Version Card */
.version-current .admin-card-header {
    position: relative;
}

.version-current .version-badge-current {
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
}

.version-info {
    flex: 1;
}

.version-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 0.5rem;
}

.version-slug {
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
}

.version-slug i {
    margin-right: 0.35rem;
    color: rgba(99, 102, 241, 0.6);
}

.admin-card-body {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 2rem;
    color: rgba(99, 102, 241, 0.5);
}

.empty-state h3 {
    color: #fff;
    margin: 0 0 0.5rem;
}

.empty-state p {
    color: rgba(255, 255, 255, 0.5);
    max-width: 400px;
    margin: 0 auto;
}

/* Timeline */
.versions-timeline {
    position: relative;
    padding-left: 2rem;
    margin-top: 1.5rem;
}

.timeline-line {
    position: absolute;
    left: 0.5rem;
    top: 2rem;
    bottom: 2rem;
    width: 2px;
    background: rgba(99, 102, 241, 0.2);
}

.version-card {
    position: relative;
    margin-bottom: 1rem;
}

.timeline-dot {
    position: absolute;
    left: -1.65rem;
    top: 1.5rem;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.4);
    border: 2px solid rgba(15, 23, 42, 1);
}

.version-card-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.version-card-info {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.version-date {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
}

.version-date i {
    margin-right: 0.35rem;
}

.version-note {
    font-size: 0.8rem;
    color: #f59e0b;
}

.version-card-body {
    padding: 1.25rem 1.5rem;
    display: block;
}

.version-preview-text {
    margin-top: 0.75rem;
    padding: 0.75rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 0.5rem;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.4);
    line-height: 1.5;
}

.version-card-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.1);
    border-radius: 0 0 1rem 1rem;
}

/* Modal */
.version-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.version-modal.active {
    display: flex;
}

.version-modal-content {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.98), rgba(30, 41, 59, 0.98));
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 1rem;
    width: 100%;
    max-width: 900px;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.version-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.version-modal-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #fff;
    font-size: 1.1rem;
}

.modal-close-btn {
    width: 36px;
    height: 36px;
    border-radius: 0.5rem;
    border: none;
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    transition: all 0.2s;
}

.modal-close-btn:hover {
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
}

.version-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
}

.loading-state {
    text-align: center;
    padding: 3rem;
    color: rgba(255, 255, 255, 0.5);
}

.loading-state i {
    font-size: 2rem;
    margin-bottom: 1rem;
    display: block;
}

/* Responsive */
@media (max-width: 768px) {
    .versions-timeline {
        padding-left: 0;
    }

    .timeline-line,
    .timeline-dot {
        display: none;
    }

    .version-card-info {
        flex-direction: column;
        align-items: flex-start;
    }

    .version-card-footer {
        flex-direction: column;
    }

    .version-card-footer .admin-btn {
        width: 100%;
        justify-content: center;
    }

    .admin-card-body {
        flex-direction: column;
        align-items: flex-start;
    }

    .version-actions {
        width: 100%;
    }

    .version-actions .admin-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
const basePath = '<?= $basePath ?>';
const pageId = <?= $page['id'] ?>;
const csrfToken = '<?= Csrf::generate() ?>';

function previewVersion(versionId) {
    const modal = document.getElementById('versionPreviewModal');
    const content = document.getElementById('versionPreviewContent');

    modal.classList.add('active');
    content.innerHTML = '<div class="loading-state"><i class="fa-solid fa-spinner fa-spin"></i><span>Loading preview...</span></div>';

    fetch(`${basePath}/admin/pages/version-content/${versionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = `
                    <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.1);">
                        <h4 style="margin: 0 0 0.25rem; color: #fff;">${escapeHtml(data.version.title)}</h4>
                        <small style="color: rgba(255,255,255,0.5);">Version ${data.version.version_number} - ${data.version.created_at}</small>
                    </div>
                    <div class="version-content-preview" style="color: rgba(255,255,255,0.7); line-height: 1.6;">${data.version.content || '<em style="color: rgba(255,255,255,0.4);">No content</em>'}</div>
                `;
            } else {
                content.innerHTML = '<div style="color: #ef4444; text-align: center; padding: 2rem;">Failed to load version</div>';
            }
        })
        .catch(err => {
            content.innerHTML = '<div style="color: #ef4444; text-align: center; padding: 2rem;">Failed to load version</div>';
        });
}

function closePreviewModal() {
    document.getElementById('versionPreviewModal').classList.remove('active');
}

function restoreVersion(versionId, versionNumber) {
    if (!confirm(`Restore to version ${versionNumber}? The current version will be saved to history first.`)) {
        return;
    }

    const formData = new FormData();
    formData.append('page_id', pageId);
    formData.append('version_id', versionId);
    formData.append('csrf_token', csrfToken);

    fetch(`${basePath}/admin/pages/restore-version`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Version restored successfully!');
            window.location.reload();
        } else {
            alert('Failed to restore version: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        alert('Failed to restore version');
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closePreviewModal();
});

document.getElementById('versionPreviewModal').addEventListener('click', (e) => {
    if (e.target.id === 'versionPreviewModal') closePreviewModal();
});
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>

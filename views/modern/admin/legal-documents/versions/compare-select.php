<?php
/**
 * Admin Select Versions to Compare
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Compare Versions';
$adminPageSubtitle = $document['title'];
$adminPageIcon = 'fa-code-compare';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';
?>

<!-- Breadcrumb -->
<nav class="admin-breadcrumb">
    <a href="<?= $basePath ?>/admin-legacy/legal-documents"><i class="fa-solid fa-arrow-left"></i> All Documents</a>
    <span>/</span>
    <a href="<?= $basePath ?>/admin-legacy/legal-documents/<?= $document['id'] ?>"><?= htmlspecialchars($document['title']) ?></a>
    <span>/</span>
    <span>Compare Versions</span>
</nav>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-code-compare"></i>
            Compare Versions
        </h1>
        <p class="admin-page-subtitle">
            Select two versions of <?= htmlspecialchars($document['title']) ?> to compare side by side
        </p>
    </div>
</div>

<!-- Version Selection -->
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon admin-card-header-icon-cyan">
            <i class="fa-solid fa-code-branch"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Select Versions</h3>
            <p class="admin-card-subtitle">Choose two versions to compare their content</p>
        </div>
    </div>
    <div class="admin-card-body">
        <?php if (count($versions) < 2): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <i class="fa-solid fa-code-compare"></i>
            </div>
            <h3 class="admin-empty-title">Not Enough Versions</h3>
            <p class="admin-empty-text">You need at least two published versions to compare. Currently there are <?= count($versions) ?> version(s).</p>
            <a href="<?= $basePath ?>/admin-legacy/legal-documents/<?= $document['id'] ?>/versions/create" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                <i class="fa-solid fa-plus"></i> Create New Version
            </a>
        </div>
        <?php else: ?>
        <form action="<?= $basePath ?>/admin-legacy/legal-documents/<?= $document['id'] ?>/compare" method="GET" class="compare-form">
            <div class="compare-selectors">
                <div class="compare-selector">
                    <label>Version A (Older)</label>
                    <select name="a" required class="admin-select">
                        <option value="">Select version...</option>
                        <?php foreach ($versions as $ver): ?>
                        <option value="<?= $ver['id'] ?>">
                            Version <?= htmlspecialchars($ver['version_number']) ?>
                            <?php if ($ver['version_label']): ?>(<?= htmlspecialchars($ver['version_label']) ?>)<?php endif; ?>
                            - <?= date('M j, Y', strtotime($ver['effective_date'])) ?>
                            <?php if ($ver['is_draft']): ?>[Draft]<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="compare-arrow">
                    <i class="fa-solid fa-arrow-right"></i>
                </div>

                <div class="compare-selector">
                    <label>Version B (Newer)</label>
                    <select name="b" required class="admin-select">
                        <option value="">Select version...</option>
                        <?php foreach ($versions as $ver): ?>
                        <option value="<?= $ver['id'] ?>">
                            Version <?= htmlspecialchars($ver['version_number']) ?>
                            <?php if ($ver['version_label']): ?>(<?= htmlspecialchars($ver['version_label']) ?>)<?php endif; ?>
                            - <?= date('M j, Y', strtotime($ver['effective_date'])) ?>
                            <?php if ($ver['is_draft']): ?>[Draft]<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="compare-actions">
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fa-solid fa-code-compare"></i> Compare Versions
                </button>
            </div>
        </form>

        <!-- Quick Compare Shortcuts -->
        <div class="quick-compare-section">
            <h4>Quick Compare</h4>
            <div class="quick-compare-grid">
                <?php
                $publishedVersions = array_filter($versions, fn($v) => !$v['is_draft']);
                $versionCount = count($publishedVersions);
                if ($versionCount >= 2):
                    $sortedVersions = array_values($publishedVersions);
                    usort($sortedVersions, fn($a, $b) => version_compare($b['version_number'], $a['version_number']));
                ?>
                <a href="<?= $basePath ?>/admin-legacy/legal-documents/<?= $document['id'] ?>/compare?a=<?= $sortedVersions[1]['id'] ?>&b=<?= $sortedVersions[0]['id'] ?>" class="quick-compare-card">
                    <div class="quick-compare-icon">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div class="quick-compare-content">
                        <div class="quick-compare-title">Latest vs Previous</div>
                        <div class="quick-compare-desc">
                            v<?= htmlspecialchars($sortedVersions[1]['version_number']) ?> → v<?= htmlspecialchars($sortedVersions[0]['version_number']) ?>
                        </div>
                    </div>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Breadcrumb */
.admin-breadcrumb {
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.admin-breadcrumb a {
    color: rgba(255, 255, 255, 0.6);
    text-decoration: none;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: color 0.2s;
}

.admin-breadcrumb a:hover {
    color: #818cf8;
}

.admin-breadcrumb span {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.85rem;
}

/* Compare Form */
.compare-form {
    padding: 1rem 0;
}

.compare-selectors {
    display: flex;
    align-items: flex-end;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.compare-selector {
    flex: 1;
    min-width: 250px;
}

.compare-selector label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255, 255, 255, 0.6);
    margin-bottom: 0.5rem;
}

.admin-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-size: 0.9rem;
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: rgba(30, 41, 59, 0.4);
    color: #fff;
    cursor: pointer;
}

.admin-select:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}

.admin-select option {
    background: #1e293b;
    color: #fff;
}

.compare-arrow {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #818cf8;
    flex-shrink: 0;
}

.compare-actions {
    margin-top: 2rem;
    display: flex;
    justify-content: center;
}

/* Quick Compare */
.quick-compare-section {
    margin-top: 2.5rem;
    padding-top: 2rem;
    border-top: 1px solid rgba(99, 102, 241, 0.15);
}

.quick-compare-section h4 {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0 0 1rem 0;
}

.quick-compare-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.quick-compare-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 10px;
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.2);
    text-decoration: none;
    transition: all 0.2s;
}

.quick-compare-card:hover {
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.4);
    transform: translateY(-2px);
}

.quick-compare-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: rgba(99, 102, 241, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    color: #818cf8;
    flex-shrink: 0;
}

.quick-compare-title {
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.25rem;
}

.quick-compare-desc {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-size: 0.9rem;
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

/* Empty State */
.admin-empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.admin-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 20px;
    background: rgba(99, 102, 241, 0.1);
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

/* Responsive */
@media (max-width: 768px) {
    .compare-selectors {
        flex-direction: column;
    }

    .compare-arrow {
        transform: rotate(90deg);
        margin: 0.5rem auto;
    }

    .compare-selector {
        min-width: 100%;
    }
}
</style>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>

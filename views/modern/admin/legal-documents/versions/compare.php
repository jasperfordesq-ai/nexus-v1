<?php
/**
 * Admin Version Comparison View
 * Side-by-side diff of two document versions
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Version Comparison';
$adminPageSubtitle = $document['title'];
$adminPageIcon = 'fa-code-compare';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Simple function to strip HTML and get text for comparison
function getTextForComparison($html) {
    $text = strip_tags($html);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

$textA = getTextForComparison($versionA['content'] ?? '');
$textB = getTextForComparison($versionB['content'] ?? '');

// Calculate basic similarity
$similarity = 0;
if ($textA && $textB) {
    similar_text($textA, $textB, $similarity);
    $similarity = round($similarity, 1);
}

// Word count comparison
$wordCountA = str_word_count($textA);
$wordCountB = str_word_count($textB);
$wordDiff = $wordCountB - $wordCountA;
?>

<!-- Breadcrumb -->
<nav class="admin-breadcrumb">
    <a href="<?= $basePath ?>/admin-legacy/legal-documents"><i class="fa-solid fa-arrow-left"></i> All Documents</a>
    <span>/</span>
    <a href="<?= $basePath ?>/admin-legacy/legal-documents/<?= $document['id'] ?>"><?= htmlspecialchars($document['title']) ?></a>
    <span>/</span>
    <span>Compare v<?= htmlspecialchars($versionA['version_number']) ?> → v<?= htmlspecialchars($versionB['version_number']) ?></span>
</nav>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <i class="fa-solid fa-code-compare"></i>
            Version Comparison
        </h1>
        <p class="admin-page-subtitle">
            Comparing version <?= htmlspecialchars($versionA['version_number']) ?> to version <?= htmlspecialchars($versionB['version_number']) ?>
        </p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin-legacy/legal-documents/<?= $document['id'] ?>/compare" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrows-rotate"></i> Compare Different Versions
        </a>
    </div>
</div>

<!-- Comparison Stats -->
<div class="comparison-stats">
    <div class="stat-card">
        <div class="stat-icon stat-icon-purple">
            <i class="fa-solid fa-percent"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $similarity ?>%</div>
            <div class="stat-label">Similarity</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-blue">
            <i class="fa-solid fa-file-word"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($wordCountA) ?></div>
            <div class="stat-label">Words (v<?= htmlspecialchars($versionA['version_number']) ?>)</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-green">
            <i class="fa-solid fa-file-word"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($wordCountB) ?></div>
            <div class="stat-label">Words (v<?= htmlspecialchars($versionB['version_number']) ?>)</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon <?= $wordDiff >= 0 ? 'stat-icon-green' : 'stat-icon-red' ?>">
            <i class="fa-solid <?= $wordDiff >= 0 ? 'fa-plus' : 'fa-minus' ?>"></i>
        </div>
        <div class="stat-content">
            <div class="stat-value"><?= $wordDiff >= 0 ? '+' : '' ?><?= number_format($wordDiff) ?></div>
            <div class="stat-label">Word Change</div>
        </div>
    </div>
</div>

<!-- Version Headers -->
<div class="comparison-headers">
    <div class="version-header version-a">
        <div class="version-badge">
            <i class="fa-solid fa-code-branch"></i>
            v<?= htmlspecialchars($versionA['version_number']) ?>
        </div>
        <div class="version-details">
            <?php if ($versionA['version_label']): ?>
            <span class="version-label"><?= htmlspecialchars($versionA['version_label']) ?></span>
            <?php endif; ?>
            <span class="version-date">
                <i class="fa-solid fa-calendar"></i>
                <?= date('M j, Y', strtotime($versionA['effective_date'])) ?>
            </span>
        </div>
    </div>

    <div class="version-header version-b">
        <div class="version-badge">
            <i class="fa-solid fa-code-branch"></i>
            v<?= htmlspecialchars($versionB['version_number']) ?>
            <?php if ($versionB['id'] === $document['current_version_id']): ?>
            <span class="current-tag">Current</span>
            <?php endif; ?>
        </div>
        <div class="version-details">
            <?php if ($versionB['version_label']): ?>
            <span class="version-label"><?= htmlspecialchars($versionB['version_label']) ?></span>
            <?php endif; ?>
            <span class="version-date">
                <i class="fa-solid fa-calendar"></i>
                <?= date('M j, Y', strtotime($versionB['effective_date'])) ?>
            </span>
        </div>
    </div>
</div>

<!-- Summary of Changes -->
<?php if ($versionB['summary_of_changes']): ?>
<div class="changes-summary-card">
    <div class="changes-summary-header">
        <i class="fa-solid fa-list-check"></i>
        Summary of Changes (v<?= htmlspecialchars($versionB['version_number']) ?>)
    </div>
    <div class="changes-summary-content">
        <?= nl2br(htmlspecialchars($versionB['summary_of_changes'])) ?>
    </div>
</div>
<?php endif; ?>

<!-- View Mode Toggle -->
<div class="view-mode-toggle">
    <button type="button" class="view-mode-btn active" data-mode="side-by-side">
        <i class="fa-solid fa-columns"></i> Side by Side
    </button>
    <button type="button" class="view-mode-btn" data-mode="unified">
        <i class="fa-solid fa-file-lines"></i> Unified Diff
    </button>
</div>

<!-- Side by Side Comparison -->
<div class="comparison-container" id="side-by-side-view">
    <div class="comparison-pane pane-a">
        <div class="pane-header">
            <span class="pane-title">Version <?= htmlspecialchars($versionA['version_number']) ?></span>
        </div>
        <div class="pane-content" id="content-a">
            <?= $versionA['content'] ?>
        </div>
    </div>

    <div class="comparison-divider">
        <div class="divider-line"></div>
    </div>

    <div class="comparison-pane pane-b">
        <div class="pane-header">
            <span class="pane-title">Version <?= htmlspecialchars($versionB['version_number']) ?></span>
        </div>
        <div class="pane-content" id="content-b">
            <?= $versionB['content'] ?>
        </div>
    </div>
</div>

<!-- Unified Diff View (hidden by default) -->
<div class="unified-diff-container" id="unified-view" style="display: none;">
    <div class="unified-diff-header">
        <span class="diff-legend">
            <span class="legend-item legend-removed"><i class="fa-solid fa-minus"></i> Removed</span>
            <span class="legend-item legend-added"><i class="fa-solid fa-plus"></i> Added</span>
        </span>
    </div>
    <div class="unified-diff-content" id="unified-diff">
        <!-- Populated by JavaScript -->
    </div>
</div>

<!-- Hidden data for JavaScript -->
<script id="version-data" type="application/json">
{
    "textA": <?= json_encode(strip_tags($versionA['content'] ?? '')) ?>,
    "textB": <?= json_encode(strip_tags($versionB['content'] ?? '')) ?>
}
</script>

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

/* Comparison Stats */
.comparison-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    border-radius: 12px;
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.stat-icon-purple {
    background: rgba(139, 92, 246, 0.15);
    color: #a78bfa;
}

.stat-icon-blue {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
}

.stat-icon-green {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
}

.stat-icon-red {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #fff;
}

.stat-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
}

/* Version Headers */
.comparison-headers {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.version-header {
    padding: 1rem 1.25rem;
    border-radius: 12px;
}

.version-a {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.version-b {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.2);
}

.version-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.version-a .version-badge {
    color: #fbbf24;
}

.version-b .version-badge {
    color: #4ade80;
}

.current-tag {
    font-size: 0.7rem;
    padding: 0.15rem 0.5rem;
    border-radius: 50px;
    background: rgba(34, 197, 94, 0.2);
    color: #4ade80;
    font-weight: 600;
    text-transform: uppercase;
}

.version-details {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.6);
}

.version-label {
    font-weight: 500;
}

.version-date {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

/* Changes Summary */
.changes-summary-card {
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.changes-summary-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 1.25rem;
    background: rgba(99, 102, 241, 0.1);
    font-weight: 600;
    color: #818cf8;
}

.changes-summary-content {
    padding: 1rem 1.25rem;
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.6;
}

/* Comparison Container */
.comparison-container {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 0;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.comparison-pane {
    background: rgba(30, 41, 59, 0.5);
}

.pane-header {
    padding: 0.75rem 1.25rem;
    background: rgba(30, 41, 59, 0.8);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
    position: sticky;
    top: 0;
    z-index: 10;
}

.pane-a .pane-header {
    border-right: 1px solid rgba(245, 158, 11, 0.3);
}

.pane-b .pane-header {
    border-left: 1px solid rgba(34, 197, 94, 0.3);
}

.pane-title {
    font-weight: 600;
    font-size: 0.85rem;
}

.pane-a .pane-title {
    color: #fbbf24;
}

.pane-b .pane-title {
    color: #4ade80;
}

.pane-content {
    padding: 1.5rem;
    max-height: 600px;
    overflow-y: auto;
    font-size: 0.9rem;
    line-height: 1.7;
    color: rgba(255, 255, 255, 0.85);
}

.pane-content h1,
.pane-content h2,
.pane-content h3,
.pane-content h4 {
    color: #fff;
    margin-top: 1.25rem;
    margin-bottom: 0.75rem;
}

.pane-content h2 {
    font-size: 1.2rem;
    padding-bottom: 0.4rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.pane-content p {
    margin-bottom: 0.75rem;
}

.pane-content ul,
.pane-content ol {
    padding-left: 1.25rem;
    margin-bottom: 0.75rem;
}

.pane-content a {
    color: #818cf8;
}

/* Divider */
.comparison-divider {
    width: 2px;
    background: rgba(99, 102, 241, 0.2);
    position: relative;
}

.divider-line {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: rgba(99, 102, 241, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.2);
}

.admin-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
}

/* Responsive */
@media (max-width: 900px) {
    .comparison-container {
        display: flex;
        flex-direction: column;
    }

    .comparison-divider {
        width: 100%;
        height: 2px;
    }

    .divider-line {
        display: none;
    }

    .comparison-headers {
        grid-template-columns: 1fr;
    }

    .pane-a .pane-header {
        border-right: none;
    }

    .pane-b .pane-header {
        border-left: none;
    }
}

/* View Mode Toggle */
.view-mode-toggle {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    justify-content: center;
}

.view-mode-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: rgba(255, 255, 255, 0.05);
    color: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    transition: all 0.2s;
}

.view-mode-btn:hover {
    background: rgba(99, 102, 241, 0.1);
    color: rgba(255, 255, 255, 0.8);
}

.view-mode-btn.active {
    background: rgba(99, 102, 241, 0.2);
    border-color: rgba(99, 102, 241, 0.5);
    color: #818cf8;
}

/* Unified Diff View */
.unified-diff-container {
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    overflow: hidden;
}

.unified-diff-header {
    padding: 0.75rem 1.25rem;
    background: rgba(30, 41, 59, 0.8);
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
    display: flex;
    justify-content: flex-end;
}

.diff-legend {
    display: flex;
    gap: 1.5rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    font-weight: 500;
}

.legend-removed {
    color: #f87171;
}

.legend-added {
    color: #4ade80;
}

.unified-diff-content {
    padding: 1.5rem;
    max-height: 600px;
    overflow-y: auto;
    font-family: 'Fira Code', 'Consolas', monospace;
    font-size: 0.85rem;
    line-height: 1.8;
}

.diff-line {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    margin-bottom: 2px;
}

.diff-line.removed {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
    text-decoration: line-through;
    text-decoration-color: rgba(239, 68, 68, 0.5);
}

.diff-line.added {
    background: rgba(34, 197, 94, 0.15);
    color: #86efac;
}

.diff-line.unchanged {
    color: rgba(255, 255, 255, 0.6);
}

.diff-section-header {
    padding: 0.5rem;
    margin: 1rem 0 0.5rem 0;
    font-weight: 600;
    color: #818cf8;
    font-size: 0.9rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewButtons = document.querySelectorAll('.view-mode-btn');
    const sideBySideView = document.getElementById('side-by-side-view');
    const unifiedView = document.getElementById('unified-view');
    const versionData = JSON.parse(document.getElementById('version-data').textContent);

    // View mode toggle
    viewButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const mode = this.dataset.mode;

            viewButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            if (mode === 'side-by-side') {
                sideBySideView.style.display = 'grid';
                unifiedView.style.display = 'none';
            } else {
                sideBySideView.style.display = 'none';
                unifiedView.style.display = 'block';
                generateUnifiedDiff();
            }
        });
    });

    // Simple word-based diff algorithm
    function generateUnifiedDiff() {
        const diffContainer = document.getElementById('unified-diff');

        // Split into sentences/paragraphs for better comparison
        const linesA = versionData.textA.split(/(?<=[.!?])\s+/);
        const linesB = versionData.textB.split(/(?<=[.!?])\s+/);

        // Simple LCS-based diff
        const diff = computeDiff(linesA, linesB);

        let html = '';
        diff.forEach(item => {
            if (item.type === 'removed') {
                html += '<div class="diff-line removed"><i class="fa-solid fa-minus"></i> ' + escapeHtml(item.text) + '</div>';
            } else if (item.type === 'added') {
                html += '<div class="diff-line added"><i class="fa-solid fa-plus"></i> ' + escapeHtml(item.text) + '</div>';
            } else {
                html += '<div class="diff-line unchanged">' + escapeHtml(item.text) + '</div>';
            }
        });

        diffContainer.innerHTML = html || '<p style="color: rgba(255,255,255,0.5); text-align: center;">No differences found or documents are identical.</p>';
    }

    function computeDiff(a, b) {
        const result = [];
        const aSet = new Set(a.map(s => s.trim().toLowerCase()));
        const bSet = new Set(b.map(s => s.trim().toLowerCase()));

        // Find removed items (in A but not in B)
        a.forEach(line => {
            const normalized = line.trim().toLowerCase();
            if (!bSet.has(normalized) && line.trim()) {
                result.push({ type: 'removed', text: line.trim() });
            }
        });

        // Find added items (in B but not in A)
        b.forEach(line => {
            const normalized = line.trim().toLowerCase();
            if (!aSet.has(normalized) && line.trim()) {
                result.push({ type: 'added', text: line.trim() });
            }
        });

        // If no changes, show unchanged content
        if (result.length === 0) {
            b.forEach(line => {
                if (line.trim()) {
                    result.push({ type: 'unchanged', text: line.trim() });
                }
            });
        }

        return result;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>

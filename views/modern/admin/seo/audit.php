<?php
/**
 * Admin SEO Health Audit - Gold Standard v2.0
 * STANDALONE admin interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'SEO Audit';
$adminPageSubtitle = 'SEO';
$adminPageIcon = 'fa-stethoscope';

// Include standalone admin header
require dirname(__DIR__) . '/partials/admin-header.php';

// Color based on score
$scoreColor = $score >= 80 ? '#10b981' : ($score >= 50 ? '#f59e0b' : '#ef4444');
$scoreLabel = $score >= 80 ? 'Excellent' : ($score >= 50 ? 'Needs Work' : 'Critical');
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin-legacy/seo" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            SEO Health Audit
        </h1>
        <p class="admin-page-subtitle">Analyze and fix SEO issues across your content</p>
    </div>
</div>

<!-- Score Card -->
<div class="admin-glass-card score-card">
    <div class="admin-card-body">
        <div class="score-container">
            <div class="score-circle">
                <svg viewBox="0 0 36 36">
                    <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"></path>
                    <path class="circle-progress" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="<?= $scoreColor ?>" stroke-width="3" stroke-dasharray="<?= $score ?>, 100"></path>
                </svg>
                <div class="score-value">
                    <span class="score-number" style="color: <?= $scoreColor ?>;"><?= $score ?></span>
                    <span class="score-max">/ 100</span>
                </div>
            </div>
            <div class="score-info">
                <h2>SEO Health: <?= $scoreLabel ?></h2>
                <p>Analyzed <?= $totalItems ?> items across listings, events, and groups.</p>
                <div class="score-stats">
                    <div class="score-stat error">
                        <span class="stat-dot"></span>
                        <span class="stat-text"><?= $errorCount ?> Errors</span>
                    </div>
                    <div class="score-stat warning">
                        <span class="stat-dot"></span>
                        <span class="stat-text"><?= $warningCount ?> Warnings</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Issues List -->
<?php if (!empty($issues)): ?>
<div class="admin-glass-card">
    <div class="admin-card-header">
        <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <i class="fa-solid fa-bug"></i>
        </div>
        <div class="admin-card-header-content">
            <h3 class="admin-card-title">Issues Found</h3>
            <p class="admin-card-subtitle"><?= count($issues) ?> items need attention</p>
        </div>
    </div>
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Issue</th>
                    <th>Severity</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($issues as $issue): ?>
                <tr>
                    <td>
                        <span class="type-badge <?= $issue['type'] ?>"><?= ucfirst($issue['type']) ?></span>
                    </td>
                    <td>
                        <div class="issue-title"><?= htmlspecialchars($issue['title']) ?></div>
                    </td>
                    <td>
                        <div class="issue-desc"><?= htmlspecialchars($issue['issue']) ?></div>
                    </td>
                    <td>
                        <span class="severity-badge <?= $issue['severity'] ?>"><?= ucfirst($issue['severity']) ?></span>
                    </td>
                    <td>
                        <?php
                        $editUrl = match($issue['type']) {
                            'listing' => "{$basePath}/listings/edit/{$issue['id']}",
                            'event' => "{$basePath}/events/{$issue['id']}/edit",
                            'group' => "{$basePath}/groups/{$issue['id']}/edit",
                            default => "#"
                        };
                        ?>
                        <a href="<?= $editUrl ?>" class="admin-btn admin-btn-sm admin-btn-primary">
                            Edit <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="admin-glass-card">
    <div class="empty-state success">
        <div class="empty-state-icon">
            <i class="fa-solid fa-check-circle"></i>
        </div>
        <h3>Perfect SEO Health!</h3>
        <p>No issues found. All your content is properly optimized for search engines.</p>
    </div>
</div>
<?php endif; ?>

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

/* Score Card */
.score-card .admin-card-body {
    padding: 2rem;
}

.score-container {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.score-circle {
    position: relative;
    width: 120px;
    height: 120px;
    flex-shrink: 0;
}

.score-circle svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.score-value {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.score-number {
    font-size: 2rem;
    font-weight: 800;
}

.score-max {
    font-size: 0.7rem;
    color: rgba(255, 255, 255, 0.5);
}

.score-info {
    flex: 1;
}

.score-info h2 {
    margin: 0 0 0.5rem;
    font-size: 1.5rem;
    font-weight: 700;
    color: #fff;
}

.score-info p {
    margin: 0 0 1rem;
    color: rgba(255, 255, 255, 0.6);
}

.score-stats {
    display: flex;
    gap: 1.5rem;
}

.score-stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stat-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.score-stat.error .stat-dot { background: #ef4444; }
.score-stat.warning .stat-dot { background: #f59e0b; }

.stat-text {
    font-weight: 600;
    color: #e2e8f0;
}

/* Type Badge */
.type-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.type-badge.listing {
    background: rgba(59, 130, 246, 0.2);
    color: #60a5fa;
}

.type-badge.event {
    background: rgba(236, 72, 153, 0.2);
    color: #f472b6;
}

.type-badge.group {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

/* Severity Badge */
.severity-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.severity-badge.error {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

.severity-badge.warning {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
}

/* Issue Title/Desc */
.issue-title {
    font-weight: 500;
    color: #f1f5f9;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.issue-desc {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
}

/* Empty State Success */
.empty-state.success {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state.success .empty-state-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: rgba(16, 185, 129, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    font-size: 3rem;
    color: #34d399;
}

.empty-state.success h3 {
    margin: 0 0 0.5rem;
    color: #fff;
    font-size: 1.3rem;
}

.empty-state.success p {
    margin: 0;
    color: rgba(255, 255, 255, 0.5);
}

/* Mobile */
@media (max-width: 768px) {
    .score-container {
        flex-direction: column;
        text-align: center;
    }

    .score-stats {
        justify-content: center;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>

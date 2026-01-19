<?php
/**
 * Modern GDPR Breach Detail View - Gold Standard v2.0
 * Dark Mode Optimized Security Incident Detail
 */

use Nexus\Core\TenantContext;

// Navigation context
$currentSection = 'gdpr';
$currentPage = 'breaches';

$basePath = TenantContext::getBasePath();
$breach = $breach ?? [];

require dirname(__DIR__, 4) . '/layouts/admin-header.php';
?>

<style>
.breach-detail-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px;
}

.breach-header-card {
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 24px;
    padding: 32px;
    margin-bottom: 24px;
}

[data-theme="light"] .breach-header-card {
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.breach-header-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 24px;
}

.breach-id-large {
    font-size: 2rem;
    font-weight: 800;
    color: #f1f5f9;
    margin-bottom: 8px;
}

[data-theme="light"] .breach-id-large {
    color: #1e293b;
}

.severity-badge-large {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 24px;
    font-size: 0.875rem;
    font-weight: 700;
    text-transform: uppercase;
}

.severity-badge-large.critical {
    background: rgba(220, 38, 38, 0.2);
    color: #dc2626;
    border: 2px solid rgba(220, 38, 38, 0.4);
}

.severity-badge-large.high {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 2px solid rgba(239, 68, 68, 0.4);
}

.severity-badge-large.medium {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
    border: 2px solid rgba(245, 158, 11, 0.4);
}

.severity-badge-large.low {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    border: 2px solid rgba(16, 185, 129, 0.4);
}

.breach-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.breach-meta-item {
    padding: 16px;
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 12px;
}

.breach-meta-label {
    font-size: 0.75rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 8px;
}

[data-theme="light"] .breach-meta-label {
    color: #64748b;
}

.breach-meta-value {
    font-size: 1.125rem;
    font-weight: 700;
    color: #f1f5f9;
}

[data-theme="light"] .breach-meta-value {
    color: #1e293b;
}

.breach-section {
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    margin-bottom: 24px;
    overflow: hidden;
}

[data-theme="light"] .breach-section {
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.breach-section-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.2);
    display: flex;
    align-items: center;
    gap: 12px;
}

[data-theme="light"] .breach-section-header {
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.breach-section-header i {
    font-size: 1.25rem;
    color: #6366f1;
}

.breach-section-header h3 {
    font-size: 1.125rem;
    font-weight: 700;
    color: #f1f5f9;
    margin: 0;
}

[data-theme="light"] .breach-section-header h3 {
    color: #1e293b;
}

.breach-section-body {
    padding: 24px;
}

.breach-description {
    font-size: 1rem;
    line-height: 1.7;
    color: #f1f5f9;
}

[data-theme="light"] .breach-description {
    color: #1e293b;
}

.action-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.cyber-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.875rem;
    text-decoration: none;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.cyber-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
}

.cyber-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
}

.cyber-btn-danger {
    background: linear-gradient(135deg, #dc2626, #ef4444);
    color: white;
    box-shadow: 0 4px 14px rgba(220, 38, 38, 0.4);
}

.cyber-btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5);
}

.cyber-btn-outline {
    background: transparent;
    color: #f1f5f9;
    border: 1px solid rgba(99, 102, 241, 0.2);
}

[data-theme="light"] .cyber-btn-outline {
    color: #1e293b;
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.cyber-btn-outline:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.3);
}

@media (max-width: 768px) {
    .breach-detail-container {
        padding: 16px;
    }

    .breach-header-top {
        flex-direction: column;
    }

    .breach-meta-grid {
        grid-template-columns: 1fr;
    }

    .action-buttons {
        flex-direction: column;
    }
}
</style>

<?php
require dirname(__DIR__, 4) . '/layouts/admin-page-header.php';
?>

<div class="breach-detail-container">
    <?php require dirname(__DIR__) . '/partials/nav.php'; ?>

    <!-- Header Card -->
    <div class="breach-header-card">
        <div class="breach-header-top">
            <div>
                <div class="breach-id-large">Breach #<?= $breach['id'] ?? 'N/A' ?></div>
                <span class="severity-badge-large <?= strtolower($breach['severity'] ?? 'medium') ?>">
                    <i class="fa-solid fa-circle"></i>
                    <?= ucfirst($breach['severity'] ?? 'Unknown') ?> Severity
                </span>
            </div>
            <div class="action-buttons">
                <?php if (($breach['status'] ?? '') === 'active'): ?>
                <button class="cyber-btn cyber-btn-danger" onclick="escalateBreach(<?= $breach['id'] ?>)">
                    <i class="fa-solid fa-bullhorn"></i>
                    Escalate
                </button>
                <?php endif; ?>
                <button class="cyber-btn cyber-btn-primary" onclick="notifyDPA()">
                    <i class="fa-solid fa-bell"></i>
                    Notify DPA
                </button>
            </div>
        </div>

        <div class="breach-meta-grid">
            <div class="breach-meta-item">
                <div class="breach-meta-label">Status</div>
                <div class="breach-meta-value"><?= ucfirst($breach['status'] ?? 'Unknown') ?></div>
            </div>
            <div class="breach-meta-item">
                <div class="breach-meta-label">Type</div>
                <div class="breach-meta-value"><?= htmlspecialchars($breach['breach_type'] ?? 'N/A') ?></div>
            </div>
            <div class="breach-meta-item">
                <div class="breach-meta-label">Affected Users</div>
                <div class="breach-meta-value"><?= number_format($breach['affected_users'] ?? 0) ?></div>
            </div>
            <div class="breach-meta-item">
                <div class="breach-meta-label">Detected</div>
                <div class="breach-meta-value"><?= isset($breach['detected_at']) ? date('M j, Y H:i', strtotime($breach['detected_at'])) : 'N/A' ?></div>
            </div>
            <div class="breach-meta-item">
                <div class="breach-meta-label">DPA Notified</div>
                <div class="breach-meta-value"><?= !empty($breach['dpa_notified_at']) ? date('M j, Y', strtotime($breach['dpa_notified_at'])) : 'Not yet' ?></div>
            </div>
            <div class="breach-meta-item">
                <div class="breach-meta-label">Reported By</div>
                <div class="breach-meta-value"><?= htmlspecialchars($breach['reported_by_name'] ?? 'System') ?></div>
            </div>
        </div>
    </div>

    <!-- Description Section -->
    <div class="breach-section">
        <div class="breach-section-header">
            <i class="fa-solid fa-file-lines"></i>
            <h3>Description</h3>
        </div>
        <div class="breach-section-body">
            <div class="breach-description">
                <?= nl2br(htmlspecialchars($breach['description'] ?? 'No description provided.')) ?>
            </div>
        </div>
    </div>

    <!-- Impact Assessment -->
    <div class="breach-section">
        <div class="breach-section-header">
            <i class="fa-solid fa-chart-pie"></i>
            <h3>Impact Assessment</h3>
        </div>
        <div class="breach-section-body">
            <div class="breach-meta-grid">
                <div class="breach-meta-item">
                    <div class="breach-meta-label">Data Categories</div>
                    <div class="breach-meta-value"><?= htmlspecialchars($breach['data_categories'] ?? 'Personal Data') ?></div>
                </div>
                <div class="breach-meta-item">
                    <div class="breach-meta-label">Risk Level</div>
                    <div class="breach-meta-value"><?= ucfirst($breach['risk_level'] ?? 'High') ?></div>
                </div>
                <div class="breach-meta-item">
                    <div class="breach-meta-label">Containment Status</div>
                    <div class="breach-meta-value"><?= $breach['contained'] ?? false ? 'Contained' : 'Active' ?></div>
                </div>
                <div class="breach-meta-item">
                    <div class="breach-meta-label">Root Cause</div>
                    <div class="breach-meta-value"><?= htmlspecialchars($breach['root_cause'] ?? 'Under Investigation') ?></div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function escalateBreach(id) {
    if (confirm('Escalate this breach to the incident response team?')) {
        fetch(`<?= $basePath ?>/admin/enterprise/gdpr/breaches/${id}/escalate`, {
            method: 'POST'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Breach escalated successfully');
                window.location.reload();
            } else {
                alert('Failed to escalate: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            alert('Network error');
            console.error(err);
        });
    }
}

function notifyDPA() {
    alert('DPA notification feature coming soon. For now, please use the manual notification process.');
}
</script>

<?php require dirname(__DIR__, 4) . '/layouts/admin-footer.php'; ?>

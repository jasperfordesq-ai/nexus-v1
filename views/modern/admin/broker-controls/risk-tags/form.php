<?php
/**
 * Risk Tag Form
 * Add or edit risk tag for a listing
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$listing = $listing ?? [];
$existingTag = $existing_tag ?? null;
$isEdit = !empty($existingTag);

$adminPageTitle = ($isEdit ? 'Edit' : 'Add') . ' Risk Tag';
$adminPageSubtitle = 'Listing: ' . ($listing['title'] ?? 'Unknown');
$adminPageIcon = 'fa-tag';

require dirname(__DIR__, 2) . '/partials/admin-header.php';

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$riskCategories = [
    'safeguarding' => 'Safeguarding Concern',
    'financial' => 'Financial Risk',
    'health_safety' => 'Health & Safety',
    'legal' => 'Legal/Regulatory',
    'reputation' => 'Reputational Risk',
    'fraud' => 'Potential Fraud',
    'other' => 'Other',
];
?>

<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/broker-controls/risk-tags" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <?= $isEdit ? 'Edit' : 'Add' ?> Risk Tag
        </h1>
        <p class="admin-page-subtitle">
            Assign a risk assessment to this listing
        </p>
    </div>
</div>

<?php if ($flashError): ?>
<div class="config-flash config-flash-error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <span><?= htmlspecialchars($flashError) ?></span>
</div>
<?php endif; ?>

<div class="form-layout">
    <!-- Listing Info Card -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><i class="fa-solid fa-rectangle-list"></i> Listing Details</h2>
        </div>
        <div class="admin-card-body">
            <div class="listing-preview">
                <div class="listing-header">
                    <h3 class="listing-title"><?= htmlspecialchars($listing['title'] ?? 'Unknown') ?></h3>
                    <span class="admin-badge admin-badge-<?= ($listing['type'] ?? '') === 'offer' ? 'success' : 'info' ?>">
                        <?= ucfirst($listing['type'] ?? 'Unknown') ?>
                    </span>
                </div>
                <p class="listing-description"><?= htmlspecialchars(substr($listing['description'] ?? '', 0, 200)) ?>...</p>
                <div class="listing-meta">
                    <div class="meta-item">
                        <i class="fa-solid fa-user"></i>
                        <span><?= htmlspecialchars($listing['owner_name'] ?? 'Unknown') ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fa-solid fa-clock"></i>
                        <span><?= number_format($listing['hours'] ?? 0, 1) ?>h</span>
                    </div>
                    <div class="meta-item">
                        <i class="fa-solid fa-calendar"></i>
                        <span>Posted <?= isset($listing['created_at']) ? date('M j, Y', strtotime($listing['created_at'])) : 'Unknown' ?></span>
                    </div>
                </div>
                <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?? '' ?>" target="_blank" class="admin-btn admin-btn-secondary admin-btn-sm">
                    <i class="fa-solid fa-external-link"></i> View Listing
                </a>
            </div>
        </div>
    </div>

    <!-- Risk Tag Form -->
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title"><i class="fa-solid fa-shield-halved"></i> Risk Assessment</h2>
        </div>
        <div class="admin-card-body">
            <form action="<?= $basePath ?>/admin/broker-controls/risk-tags/<?= $listing['id'] ?? '' ?>" method="POST">
                <?= Csrf::input() ?>

                <div class="form-group">
                    <label class="form-label">Risk Level <span class="required">*</span></label>
                    <div class="risk-level-options">
                        <label class="risk-option risk-option-low <?= ($existingTag['risk_level'] ?? '') === 'low' ? 'selected' : '' ?>">
                            <input type="radio" name="risk_level" value="low" <?= ($existingTag['risk_level'] ?? '') === 'low' ? 'checked' : '' ?>>
                            <div class="risk-option-content">
                                <i class="fa-solid fa-info-circle"></i>
                                <span class="risk-option-label">Low</span>
                                <span class="risk-option-desc">Minor concern, monitor only</span>
                            </div>
                        </label>
                        <label class="risk-option risk-option-medium <?= ($existingTag['risk_level'] ?? '') === 'medium' ? 'selected' : '' ?>">
                            <input type="radio" name="risk_level" value="medium" <?= ($existingTag['risk_level'] ?? '') === 'medium' ? 'checked' : '' ?>>
                            <div class="risk-option-content">
                                <i class="fa-solid fa-exclamation-circle"></i>
                                <span class="risk-option-label">Medium</span>
                                <span class="risk-option-desc">Moderate concern, review messages</span>
                            </div>
                        </label>
                        <label class="risk-option risk-option-high <?= ($existingTag['risk_level'] ?? '') === 'high' ? 'selected' : '' ?>">
                            <input type="radio" name="risk_level" value="high" <?= ($existingTag['risk_level'] ?? '') === 'high' ? 'checked' : '' ?>>
                            <div class="risk-option-content">
                                <i class="fa-solid fa-exclamation-triangle"></i>
                                <span class="risk-option-label">High</span>
                                <span class="risk-option-desc">Significant concern, broker approval needed</span>
                            </div>
                        </label>
                        <label class="risk-option risk-option-critical <?= ($existingTag['risk_level'] ?? '') === 'critical' ? 'selected' : '' ?>">
                            <input type="radio" name="risk_level" value="critical" <?= ($existingTag['risk_level'] ?? '') === 'critical' ? 'checked' : '' ?>>
                            <div class="risk-option-content">
                                <i class="fa-solid fa-skull-crossbones"></i>
                                <span class="risk-option-label">Critical</span>
                                <span class="risk-option-desc">Severe concern, immediate action required</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="risk_category" class="form-label">Risk Category</label>
                    <select name="risk_category" id="risk_category" class="admin-select">
                        <option value="">Select a category...</option>
                        <?php foreach ($riskCategories as $value => $label): ?>
                        <option value="<?= $value ?>" <?= ($existingTag['risk_category'] ?? '') === $value ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="risk_notes" class="form-label">Notes</label>
                    <textarea name="risk_notes" id="risk_notes" class="admin-input" rows="4"
                              placeholder="Describe the risk concern in detail..."><?= htmlspecialchars($existingTag['risk_notes'] ?? '') ?></textarea>
                    <p class="form-hint">These notes are only visible to brokers and administrators.</p>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="requires_approval" value="1"
                               <?= ($existingTag['requires_approval'] ?? false) ? 'checked' : '' ?>>
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text">Require broker approval for exchanges involving this listing</span>
                    </label>
                </div>

                <div class="form-actions">
                    <a href="<?= $basePath ?>/admin/broker-controls/risk-tags" class="admin-btn admin-btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="fa-solid fa-save"></i>
                        <?= $isEdit ? 'Update Risk Tag' : 'Save Risk Tag' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.form-layout {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 1.5rem;
}
.listing-preview {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.listing-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.listing-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}
.listing-description {
    color: var(--text-secondary, rgba(255,255,255,0.7));
    line-height: 1.6;
    margin: 0;
}
.listing-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}
.meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
}
.meta-item i {
    opacity: 0.7;
}
.form-group {
    margin-bottom: 1.5rem;
}
.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}
.form-label .required {
    color: #ef4444;
}
.form-hint {
    margin: 0.5rem 0 0;
    font-size: 0.85rem;
    color: var(--text-secondary);
}
.risk-level-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}
.risk-option {
    position: relative;
    cursor: pointer;
}
.risk-option input {
    position: absolute;
    opacity: 0;
}
.risk-option-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1.25rem;
    background: rgba(255,255,255,0.03);
    border: 2px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    text-align: center;
    transition: all 0.2s;
}
.risk-option:hover .risk-option-content {
    background: rgba(255,255,255,0.06);
}
.risk-option input:checked + .risk-option-content {
    border-color: currentColor;
    background: rgba(255,255,255,0.08);
}
.risk-option-content i {
    font-size: 1.5rem;
}
.risk-option-label {
    font-weight: 600;
    font-size: 1rem;
}
.risk-option-desc {
    font-size: 0.8rem;
    color: var(--text-secondary);
}
.risk-option-low { color: #6b7280; }
.risk-option-low input:checked + .risk-option-content { border-color: #6b7280; }
.risk-option-medium { color: #3b82f6; }
.risk-option-medium input:checked + .risk-option-content { border-color: #3b82f6; }
.risk-option-high { color: #f59e0b; }
.risk-option-high input:checked + .risk-option-content { border-color: #f59e0b; }
.risk-option-critical { color: #ef4444; }
.risk-option-critical input:checked + .risk-option-content { border-color: #ef4444; }

.admin-select,
.admin-input {
    width: 100%;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 0.75rem 1rem;
    color: var(--text-primary, #fff);
    font-family: inherit;
    font-size: 0.95rem;
}
.admin-select {
    cursor: pointer;
}
.admin-input {
    resize: vertical;
}
.admin-select:focus,
.admin-input:focus {
    outline: none;
    border-color: var(--color-primary-500, #6366f1);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    padding: 1rem;
    background: rgba(255,255,255,0.03);
    border-radius: 8px;
    transition: background 0.2s;
}
.checkbox-label:hover {
    background: rgba(255,255,255,0.06);
}
.checkbox-label input {
    position: absolute;
    opacity: 0;
}
.checkbox-custom {
    width: 22px;
    height: 22px;
    background: rgba(255,255,255,0.1);
    border: 2px solid rgba(255,255,255,0.2);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.checkbox-label input:checked + .checkbox-custom {
    background: var(--color-primary-500, #6366f1);
    border-color: var(--color-primary-500, #6366f1);
}
.checkbox-custom::after {
    content: '\f00c';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    font-size: 0.7rem;
    color: #fff;
    opacity: 0;
    transition: opacity 0.2s;
}
.checkbox-label input:checked + .checkbox-custom::after {
    opacity: 1;
}
.checkbox-text {
    flex: 1;
}
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.1);
}
.back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 0.75rem;
    opacity: 0.7;
}
.back-link:hover { opacity: 1; }
.config-flash {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}
.config-flash-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
}

@media (max-width: 1024px) {
    .form-layout {
        grid-template-columns: 1fr;
    }
    .risk-level-options {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.querySelectorAll('.risk-option input').forEach(input => {
    input.addEventListener('change', function() {
        document.querySelectorAll('.risk-option').forEach(opt => opt.classList.remove('selected'));
        if (this.checked) {
            this.closest('.risk-option').classList.add('selected');
        }
    });
});
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>

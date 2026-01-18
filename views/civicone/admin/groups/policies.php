<?php
/**
 * Groups Policies Management
 * Path: views/modern/admin/groups/policies.php
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$adminPageTitle = 'Groups Policies';
$adminPageSubtitle = 'Manage tenant-specific policies';
$adminPageIcon = 'fa-file-contract';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">
            <i class="fa-solid fa-file-contract" style="color: #a855f7;"></i>
            Groups Policies
        </h1>
        <p class="admin-page-subtitle">Manage tenant-specific rules and policies</p>
    </div>
    <div class="admin-page-header-actions">
        <a href="<?= $basePath ?>/admin/groups" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
        <button class="admin-btn admin-btn-primary" onclick="document.getElementById('newPolicyModal').style.display='block'">
            <i class="fa-solid fa-plus"></i> Add Policy
        </button>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="admin-alert admin-alert-success">
    <div class="admin-alert-icon">
        <i class="fa-solid fa-check-circle"></i>
    </div>
    <div class="admin-alert-content">
        <div class="admin-alert-title">Policy Saved!</div>
        <div class="admin-alert-text">Your policy has been updated successfully.</div>
    </div>
</div>
<?php endif; ?>

<!-- Policies by Category -->
<?php
$categories = ['creation' => 'Creation', 'membership' => 'Membership', 'content' => 'Content', 'moderation' => 'Moderation', 'notifications' => 'Notifications'];
foreach ($categories as $cat => $label):
    $catPolicies = array_filter($policies ?? [], fn($p) => $p['category'] === $cat);
    if (empty($catPolicies)) continue;
?>
<div class="admin-glass-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="fa-solid fa-folder"></i> <?= $label ?> Policies</h3>
        <span class="admin-badge admin-badge-secondary"><?= count($catPolicies) ?></span>
    </div>
    <div class="admin-card-body">
        <div class="admin-policy-list">
            <?php foreach ($catPolicies as $policy): ?>
                <div class="admin-policy-item">
                    <div class="admin-policy-info">
                        <strong><?= htmlspecialchars($policy['policy_key']) ?></strong>
                        <span class="admin-policy-type"><?= ucfirst($policy['value_type']) ?></span>
                        <?php if ($policy['description']): ?>
                            <p class="admin-policy-desc"><?= htmlspecialchars($policy['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="admin-policy-value">
                        <?= htmlspecialchars(substr($policy['policy_value'], 0, 50)) ?>
                        <?= strlen($policy['policy_value']) > 50 ? '...' : '' ?>
                    </div>
                    <div class="admin-policy-actions">
                        <button class="admin-btn admin-btn-sm admin-btn-secondary" onclick="editPolicy(<?= $policy['id'] ?>)">
                            <i class="fa-solid fa-edit"></i>
                        </button>
                        <form method="POST" action="<?= $basePath ?>/admin/groups/policies" style="display:inline" onsubmit="return confirm('Delete this policy?')">
                            <?= Csrf::input() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="policy_id" value="<?= $policy['id'] ?>">
                            <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- New Policy Modal -->
<div id="newPolicyModal" class="admin-modal" style="display:none">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>Add New Policy</h3>
            <button class="admin-modal-close" onclick="document.getElementById('newPolicyModal').style.display='none'">&times;</button>
        </div>
        <form method="POST" action="<?= $basePath ?>/admin/groups/policies">
            <?= Csrf::input() ?>
            <input type="hidden" name="action" value="create">
            <div class="admin-modal-body">
                <div class="admin-form-group">
                    <label>Policy Key</label>
                    <input type="text" name="policy_key" class="admin-form-control" required>
                </div>
                <div class="admin-form-group">
                    <label>Category</label>
                    <select name="category" class="admin-form-control" required>
                        <?php foreach ($categories as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label>Value Type</label>
                    <select name="value_type" class="admin-form-control" required>
                        <option value="string">String</option>
                        <option value="number">Number</option>
                        <option value="boolean">Boolean</option>
                        <option value="json">JSON</option>
                        <option value="list">List</option>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label>Value</label>
                    <textarea name="policy_value" class="admin-form-control" rows="3" required></textarea>
                </div>
                <div class="admin-form-group">
                    <label>Description</label>
                    <textarea name="description" class="admin-form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="document.getElementById('newPolicyModal').style.display='none'">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary">Save Policy</button>
            </div>
        </form>
    </div>
</div>

<link rel="stylesheet" href="<?= $basePath ?>/assets/css/groups-admin-gold-standard.min.css">

<style>
/* Policy List */
.admin-policy-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.admin-policy-item {
    display: grid;
    grid-template-columns: 2fr 1fr auto;
    gap: 16px;
    align-items: center;
    padding: 16px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.1);
    border-radius: 10px;
    transition: all 0.2s ease;
}

.admin-policy-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(99, 102, 241, 0.2);
    transform: translateY(-1px);
}

.admin-policy-info strong {
    display: block;
    color: #fff;
    margin-bottom: 4px;
    font-size: 0.95rem;
}

.admin-policy-type {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.admin-policy-desc {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
    margin-top: 8px;
    line-height: 1.4;
}

.admin-policy-value {
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    color: #818cf8;
    background: rgba(129, 140, 248, 0.1);
    padding: 8px 12px;
    border-radius: 6px;
}

.admin-policy-actions {
    display: flex;
    gap: 8px;
}

/* Modal Styling */
.admin-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.admin-modal-content {
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.admin-modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.admin-modal-header h3 {
    color: #fff;
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
}

.admin-modal-close {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.6);
    font-size: 28px;
    cursor: pointer;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s;
}

.admin-modal-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.admin-modal-body {
    padding: 1.5rem;
}

.admin-modal-footer {
    padding: 1.5rem;
    border-top: 1px solid rgba(99, 102, 241, 0.15);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-policy-item {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .admin-policy-actions {
        justify-content: flex-end;
    }
}
</style>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>

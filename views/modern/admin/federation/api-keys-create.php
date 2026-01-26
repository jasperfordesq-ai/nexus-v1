<?php
/**
 * Create Federation API Key
 * Form for creating new external partner API keys
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Create API Key';
$adminPageSubtitle = 'External Partner Integration';
$adminPageIcon = 'fa-plus';

require __DIR__ . '/../partials/admin-header.php';
?>

<style>
/* Create API Key Form Styles */
.create-key-form {
    max-width: 600px;
}

.form-card {
    background: var(--admin-card-bg, rgba(30, 41, 59, 0.5));
    border-radius: 16px;
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    padding: 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--admin-text, #fff);
}

.form-group .hint {
    font-size: 0.85rem;
    color: var(--admin-text-secondary, #94a3b8);
    margin-top: 0.35rem;
}

.form-input {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: 10px;
    color: var(--admin-text, #fff);
    font-size: 1rem;
    transition: all 0.2s;
}

.form-input:focus {
    outline: none;
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
}

.form-select {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: 10px;
    color: var(--admin-text, #fff);
    font-size: 1rem;
    cursor: pointer;
}

/* Permissions Checkboxes */
.permissions-grid {
    display: grid;
    gap: 0.75rem;
}

.permission-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem;
    background: rgba(0, 0, 0, 0.15);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
}

.permission-item:hover {
    background: rgba(139, 92, 246, 0.1);
}

.permission-item input[type="checkbox"] {
    width: 20px;
    height: 20px;
    margin-top: 2px;
    accent-color: #8b5cf6;
}

.permission-info {
    flex: 1;
}

.permission-info strong {
    display: block;
    color: var(--admin-text, #fff);
    margin-bottom: 2px;
}

.permission-info span {
    font-size: 0.85rem;
    color: var(--admin-text-secondary, #94a3b8);
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    border: none;
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
}

.btn-secondary {
    background: transparent;
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.2));
    color: var(--admin-text-secondary, #94a3b8);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.05);
    color: var(--admin-text, #fff);
}

/* Info Box */
.info-box {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    gap: 0.75rem;
}

.info-box i {
    color: #3b82f6;
    font-size: 1.1rem;
    margin-top: 2px;
}

.info-box-content {
    flex: 1;
}

.info-box-content strong {
    color: #3b82f6;
    display: block;
    margin-bottom: 0.25rem;
}

.info-box-content p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--admin-text-secondary, #94a3b8);
}
</style>

<div class="create-key-form">
    <div class="info-box">
        <i class="fa-solid fa-circle-info"></i>
        <div class="info-box-content">
            <strong>About API Keys</strong>
            <p>API keys allow external partners to query your federation network. Each key has specific permissions and rate limits. The key will only be shown once after creation.</p>
        </div>
    </div>

    <div class="form-card">
        <form method="POST" action="/admin/federation/api-keys/store">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">

            <div class="form-group">
                <label for="name">Key Name</label>
                <input type="text" id="name" name="name" class="form-input" required
                       placeholder="e.g., Partner Timebank Integration">
                <p class="hint">A descriptive name to identify this API key</p>
            </div>

            <div class="form-group">
                <label>Permissions</label>
                <div class="permissions-grid">
                    <label class="permission-item">
                        <input type="checkbox" name="permissions[]" value="timebanks:read" checked>
                        <div class="permission-info">
                            <strong>timebanks:read</strong>
                            <span>List partner timebanks and their member counts</span>
                        </div>
                    </label>
                    <label class="permission-item">
                        <input type="checkbox" name="permissions[]" value="members:read" checked>
                        <div class="permission-info">
                            <strong>members:read</strong>
                            <span>Search and view federated member profiles</span>
                        </div>
                    </label>
                    <label class="permission-item">
                        <input type="checkbox" name="permissions[]" value="listings:read" checked>
                        <div class="permission-info">
                            <strong>listings:read</strong>
                            <span>Search and view federated listings (offers/requests)</span>
                        </div>
                    </label>
                    <label class="permission-item">
                        <input type="checkbox" name="permissions[]" value="messages:write">
                        <div class="permission-info">
                            <strong>messages:write</strong>
                            <span>Send federated messages to members</span>
                        </div>
                    </label>
                    <label class="permission-item">
                        <input type="checkbox" name="permissions[]" value="transactions:write">
                        <div class="permission-info">
                            <strong>transactions:write</strong>
                            <span>Initiate time credit transfers</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="rate_limit">Rate Limit (requests/hour)</label>
                <input type="number" id="rate_limit" name="rate_limit" class="form-input"
                       value="1000" min="100" max="10000">
                <p class="hint">Maximum API requests allowed per hour (100-10,000)</p>
            </div>

            <div class="form-group">
                <label for="platform_id">Platform ID (for External Partners)</label>
                <input type="text" id="platform_id" name="platform_id" class="form-input"
                       placeholder="e.g., exchangemembers-prod">
                <p class="hint">Unique identifier for external platforms. Leave blank for internal partner keys.</p>
            </div>

            <div class="form-group">
                <label>Authentication Method</label>
                <div class="permissions-grid">
                    <label class="permission-item">
                        <input type="radio" name="auth_method" value="api_key" checked>
                        <div class="permission-info">
                            <strong>API Key Only</strong>
                            <span>Simple bearer token authentication (recommended for internal use)</span>
                        </div>
                    </label>
                    <label class="permission-item">
                        <input type="radio" name="auth_method" value="hmac">
                        <div class="permission-info">
                            <strong>HMAC Signature</strong>
                            <span>Request signing with shared secret (recommended for external partners)</span>
                        </div>
                    </label>
                </div>
                <p class="hint">HMAC provides additional security by signing each request with a shared secret.</p>
            </div>

            <div class="form-group">
                <label for="expires_in">Expiration</label>
                <select id="expires_in" name="expires_in" class="form-select">
                    <option value="">Never expires</option>
                    <option value="30d">30 days</option>
                    <option value="90d">90 days</option>
                    <option value="1y">1 year</option>
                </select>
                <p class="hint">When should this API key automatically expire?</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-key"></i> Generate API Key
                </button>
                <a href="/admin/federation/api-keys" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>

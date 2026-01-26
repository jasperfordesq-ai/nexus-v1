<?php
/**
 * External Federation Partners - Create Form
 * Add a new external federation server connection
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();

$adminPageTitle = 'Add External Partner';
$adminPageSubtitle = 'Connect to External Server';
$adminPageIcon = 'fa-plus';

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

require __DIR__ . '/../partials/admin-header.php';
?>

<style>
.create-partner-form {
    max-width: 700px;
}

.form-section {
    background: var(--admin-card-bg, rgba(30, 41, 59, 0.5));
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-section h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--admin-text, #fff);
    margin: 0 0 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-label {
    display: block;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--admin-text, #fff);
    margin-bottom: 0.5rem;
}

.form-label .required {
    color: #ef4444;
}

.form-hint {
    font-size: 0.8rem;
    color: var(--admin-text-secondary, #94a3b8);
    margin-top: 0.35rem;
}

.form-input,
.form-textarea,
.form-select {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid var(--admin-border, rgba(255, 255, 255, 0.1));
    border-radius: 8px;
    color: var(--admin-text, #fff);
    font-size: 0.95rem;
    transition: all 0.2s;
}

.form-input:focus,
.form-textarea:focus,
.form-select:focus {
    outline: none;
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
}

.form-input::placeholder {
    color: var(--admin-text-secondary, #64748b);
}

.form-textarea {
    min-height: 100px;
    resize: vertical;
}

.form-select {
    cursor: pointer;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

/* Checkboxes */
.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.75rem;
}

.permission-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.permission-item:hover {
    background: rgba(139, 92, 246, 0.1);
}

.permission-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #8b5cf6;
    cursor: pointer;
}

.permission-item label {
    font-size: 0.9rem;
    color: var(--admin-text, #fff);
    cursor: pointer;
}

/* Buttons */
.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
}

.btn-secondary {
    background: rgba(100, 116, 139, 0.2);
    color: var(--admin-text-secondary, #94a3b8);
}

.btn-secondary:hover {
    background: rgba(100, 116, 139, 0.3);
}

/* Flash Messages */
.flash-message {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.flash-message.error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

/* Info Alert */
.info-alert {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    color: #60a5fa;
    font-size: 0.9rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.info-alert i {
    margin-top: 0.1rem;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }

    .permissions-grid {
        grid-template-columns: 1fr;
    }

    .form-actions {
        flex-direction: column;
    }
}
</style>

<?php if ($flashError): ?>
<div class="flash-message error">
    <i class="fa-solid fa-circle-exclamation"></i>
    <span><?= htmlspecialchars($flashError) ?></span>
</div>
<?php endif; ?>

<div class="info-alert">
    <i class="fa-solid fa-circle-info"></i>
    <div>
        <strong>Before you start:</strong> You'll need the API URL and API key from the external timebank you want to connect to.
        Contact their administrator to get these credentials.
    </div>
</div>

<form method="POST" action="/admin/federation/external-partners/store" class="create-partner-form">
    <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">

    <div class="form-section">
        <h3><i class="fa-solid fa-info-circle"></i> Basic Information</h3>

        <div class="form-group">
            <label class="form-label">Partner Name <span class="required">*</span></label>
            <input type="text" name="name" class="form-input" placeholder="e.g., Dublin Timebank" required>
            <p class="form-hint">A friendly name to identify this partner</p>
        </div>

        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-textarea" placeholder="Optional notes about this partner..."></textarea>
        </div>
    </div>

    <div class="form-section">
        <h3><i class="fa-solid fa-link"></i> Connection Details</h3>

        <div class="form-group">
            <label class="form-label">Base URL <span class="required">*</span></label>
            <input type="url" name="base_url" class="form-input" placeholder="https://partner-timebank.example.com" required>
            <p class="form-hint">The base URL of the partner's server (without /api path)</p>
        </div>

        <div class="form-group">
            <label class="form-label">API Path</label>
            <input type="text" name="api_path" class="form-input" value="/api/v1/federation" placeholder="/api/v1/federation">
            <p class="form-hint">The API endpoint path (default: /api/v1/federation)</p>
        </div>
    </div>

    <div class="form-section">
        <h3><i class="fa-solid fa-key"></i> Authentication</h3>

        <div class="form-group">
            <label class="form-label">Authentication Method</label>
            <select name="auth_method" class="form-select" id="authMethod">
                <option value="api_key">API Key (Bearer Token)</option>
                <option value="hmac">HMAC-SHA256 Signing</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">API Key <span class="required">*</span></label>
            <input type="password" name="api_key" class="form-input" placeholder="Enter the API key provided by the partner">
            <p class="form-hint">The API key/token provided by the partner timebank</p>
        </div>

        <div class="form-group" id="signingSecretGroup" style="display: none;">
            <label class="form-label">Signing Secret</label>
            <input type="password" name="signing_secret" class="form-input" placeholder="HMAC signing secret">
            <p class="form-hint">Required for HMAC authentication</p>
        </div>
    </div>

    <div class="form-section">
        <h3><i class="fa-solid fa-shield-halved"></i> Permissions</h3>
        <p class="form-hint" style="margin-bottom: 1rem;">Select which features to enable with this partner</p>

        <div class="permissions-grid">
            <div class="permission-item">
                <input type="checkbox" name="allow_member_search" id="permMembers" checked>
                <label for="permMembers">Member Search</label>
            </div>
            <div class="permission-item">
                <input type="checkbox" name="allow_listing_search" id="permListings" checked>
                <label for="permListings">Listing Search</label>
            </div>
            <div class="permission-item">
                <input type="checkbox" name="allow_messaging" id="permMessaging" checked>
                <label for="permMessaging">Messaging</label>
            </div>
            <div class="permission-item">
                <input type="checkbox" name="allow_transactions" id="permTransactions" checked>
                <label for="permTransactions">Transactions</label>
            </div>
            <div class="permission-item">
                <input type="checkbox" name="allow_events" id="permEvents">
                <label for="permEvents">Events</label>
            </div>
            <div class="permission-item">
                <input type="checkbox" name="allow_groups" id="permGroups">
                <label for="permGroups">Groups</label>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Add Partner
        </button>
        <a href="/admin/federation/external-partners" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Cancel
        </a>
    </div>
</form>

<script>
document.getElementById('authMethod').addEventListener('change', function() {
    var signingGroup = document.getElementById('signingSecretGroup');
    if (this.value === 'hmac') {
        signingGroup.style.display = 'block';
    } else {
        signingGroup.style.display = 'none';
    }
});
</script>

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>

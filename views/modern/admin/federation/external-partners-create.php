<?php
/**
 * External Federation Partners - Create Form
 * Add a new external federation server connection
 *
 * Styles: /httpdocs/assets/css/admin-legacy/federation-external-partners.css
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

<div class="federation-partners-page">

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

<form method="POST" action="/admin-legacy/federation/external-partners/store" class="create-partner-form">
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
            <p class="form-hint">Choose how to authenticate with the external server</p>
        </div>

        <div class="form-group">
            <label class="form-label">API Key <span class="required">*</span></label>
            <input type="password" name="api_key" class="form-input" placeholder="Enter the API key provided by the partner" required>
            <p class="form-hint">The API key/token provided by the partner timebank - used for authentication</p>
        </div>

        <div class="form-group" id="signingSecretGroup">
            <label class="form-label">Signing Secret</label>
            <input type="password" name="signing_secret" class="form-input" placeholder="HMAC signing secret for request signatures">
            <p class="form-hint">Used for HMAC request signatures - provides additional security for verifying requests</p>
        </div>
    </div>

    <div class="form-section">
        <h3><i class="fa-solid fa-shield-halved"></i> Permissions</h3>
        <p class="form-hint">Select which features to enable with this partner</p>

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
        <a href="/admin-legacy/federation/external-partners" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Cancel
        </a>
    </div>
</form>

<script>
document.getElementById('authMethod').addEventListener('change', function() {
    var signingGroup = document.getElementById('signingSecretGroup');
    if (this.value === 'hmac') {
        signingGroup.classList.remove('hidden');
    } else {
        signingGroup.classList.add('hidden');
    }
});
</script>

</div><!-- /.federation-partners-page -->

<?php require __DIR__ . '/../partials/admin-footer.php'; ?>

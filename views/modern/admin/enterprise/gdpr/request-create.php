<?php
/**
 * GDPR Request Create View - Gold Standard v2.0
 * Admin-initiated GDPR request form
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Page config for enterprise header
$enterprisePageTitle = 'Create GDPR Request';
$enterprisePageSubtitle = 'Data Subject Request';
$enterprisePageIcon = 'fa-file-circle-plus';
$enterpriseSection = 'gdpr';
$enterpriseSubpage = 'requests';

$requestTypes = $requestTypes ?? ['access', 'erasure', 'rectification', 'restriction', 'portability', 'objection'];

require dirname(__DIR__) . '/partials/enterprise-header.php';
?>

<!-- Page Header -->
<div class="enterprise-page-header">
    <div class="enterprise-page-header-content">
        <div class="enterprise-page-header-icon">
            <i class="fa-solid fa-file-circle-plus"></i>
        </div>
        <div>
            <h1 class="enterprise-page-title">Create GDPR Request</h1>
            <p class="enterprise-page-subtitle">Submit a new data subject request on behalf of a user</p>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<style>
/* Request Create Form Styles */
.request-create-container {
    max-width: 800px;
    margin: 0 auto;
}

.info-box {
    padding: 1rem 1.25rem;
    border-radius: 10px;
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    margin-bottom: 1.5rem;
}

.info-box h4 {
    color: #6366f1;
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-box p {
    color: rgba(255,255,255,0.6);
    font-size: 0.85rem;
    margin: 0;
    line-height: 1.5;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: rgba(255,255,255,0.9);
    margin-bottom: 0.5rem;
}

.help-text {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.5);
    margin-top: 0.375rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid rgba(6, 182, 212, 0.2);
    border-radius: 10px;
    font-size: 0.95rem;
    background: rgba(10, 22, 40, 0.6);
    color: #fff;
    transition: all 0.2s;
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: #06b6d4;
    box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.2);
}

.form-control::placeholder {
    color: rgba(255,255,255,0.3);
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

.type-selector {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

@media (max-width: 768px) {
    .type-selector {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .type-selector {
        grid-template-columns: 1fr;
    }
}

.type-option {
    position: relative;
}

.type-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.type-option label {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1.25rem;
    border: 2px solid rgba(6, 182, 212, 0.15);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    background: rgba(10, 22, 40, 0.4);
}

.type-option label i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    color: rgba(255,255,255,0.5);
}

.type-option label span {
    font-weight: 500;
    color: rgba(255,255,255,0.9);
}

.type-option label small {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.5);
    margin-top: 0.25rem;
}

.type-option input:checked + label {
    border-color: #06b6d4;
    background: rgba(6, 182, 212, 0.1);
}

.type-option input:checked + label i {
    color: #06b6d4;
}

.type-option label:hover {
    border-color: #06b6d4;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding-top: 1rem;
    border-top: 1px solid rgba(6, 182, 212, 0.15);
    margin-top: 2rem;
}

.nexus-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    font-family: inherit;
}

.nexus-btn-primary {
    background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
    color: white;
    box-shadow: 0 4px 14px rgba(6, 182, 212, 0.4);
}

.nexus-btn-primary:hover {
    background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%);
    color: white;
    transform: translateY(-2px);
}

.nexus-btn-outline {
    background: rgba(10, 22, 40, 0.6);
    border: 1px solid rgba(6, 182, 212, 0.25);
    color: rgba(255,255,255,0.9);
}

.nexus-btn-outline:hover {
    background: rgba(6, 182, 212, 0.1);
    border-color: #06b6d4;
}

.nexus-btn i {
    margin-right: 0.5rem;
}

.alert {
    padding: 1rem 1.25rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.alert i {
    font-size: 1.25rem;
}
</style>

<div class="request-create-container">
    <div class="enterprise-glass-card">
        <div class="enterprise-card-header">
            <div class="enterprise-card-header-icon enterprise-card-header-icon-cyan">
                <i class="fa-solid fa-plus-circle"></i>
            </div>
            <div class="enterprise-card-header-content">
                <h3 class="enterprise-card-title">Create GDPR Request</h3>
            </div>
        </div>
        <div class="enterprise-card-body">
            <div id="alertContainer"></div>

            <div class="info-box">
                <h4><i class="fa-solid fa-info-circle"></i> Admin-Initiated Request</h4>
                <p>Use this form to submit a GDPR request on behalf of a user. This is typically used when a user contacts support directly or when processing requests received through other channels. GDPR requires response within 30 days.</p>
            </div>

            <form id="createRequestForm">
                <div class="form-group">
                    <label for="user_search">User ID or Email</label>
                    <input type="text" id="user_search" class="form-control" placeholder="Search by email or enter user ID" required>
                    <input type="hidden" id="user_id" name="user_id">
                    <div class="help-text">Start typing to search for a user, or enter a user ID directly</div>
                </div>

                <div class="form-group">
                    <label>Request Type</label>
                    <div class="type-selector">
                        <div class="type-option">
                            <input type="radio" name="request_type" id="type_access" value="access" checked>
                            <label for="type_access">
                                <i class="fa-solid fa-eye"></i>
                                <span>Access</span>
                                <small>Data export</small>
                            </label>
                        </div>
                        <div class="type-option">
                            <input type="radio" name="request_type" id="type_erasure" value="erasure">
                            <label for="type_erasure">
                                <i class="fa-solid fa-trash"></i>
                                <span>Erasure</span>
                                <small>Delete account</small>
                            </label>
                        </div>
                        <div class="type-option">
                            <input type="radio" name="request_type" id="type_portability" value="portability">
                            <label for="type_portability">
                                <i class="fa-solid fa-right-left"></i>
                                <span>Portability</span>
                                <small>Machine-readable</small>
                            </label>
                        </div>
                        <div class="type-option">
                            <input type="radio" name="request_type" id="type_rectification" value="rectification">
                            <label for="type_rectification">
                                <i class="fa-solid fa-pen"></i>
                                <span>Rectification</span>
                                <small>Correct data</small>
                            </label>
                        </div>
                        <div class="type-option">
                            <input type="radio" name="request_type" id="type_restriction" value="restriction">
                            <label for="type_restriction">
                                <i class="fa-solid fa-pause"></i>
                                <span>Restriction</span>
                                <small>Limit processing</small>
                            </label>
                        </div>
                        <div class="type-option">
                            <input type="radio" name="request_type" id="type_objection" value="objection">
                            <label for="type_objection">
                                <i class="fa-solid fa-hand"></i>
                                <span>Objection</span>
                                <small>Stop processing</small>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="form-control" placeholder="Additional context, verification details, or special instructions..."></textarea>
                    <div class="help-text">Include any relevant information about how the request was received or verified</div>
                </div>

                <div class="form-actions">
                    <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/requests" class="nexus-btn nexus-btn-outline">
                        <i class="fa-solid fa-arrow-left"></i> Cancel
                    </a>
                    <button type="submit" class="nexus-btn nexus-btn-primary">
                        <i class="fa-solid fa-paper-plane"></i> Create Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const basePath = '<?= $basePath ?>';

document.getElementById('createRequestForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const alertContainer = document.getElementById('alertContainer');
    alertContainer.innerHTML = '';

    const userId = document.getElementById('user_id').value || document.getElementById('user_search').value;
    const requestType = document.querySelector('input[name="request_type"]:checked').value;
    const notes = document.getElementById('notes').value;

    if (!userId) {
        alertContainer.innerHTML = '<div class="alert alert-danger"><i class="fa-solid fa-exclamation-circle"></i><span>Please select or enter a user ID</span></div>';
        return;
    }

    try {
        const response = await fetch(basePath + '/admin-legacy/enterprise/gdpr/requests', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: parseInt(userId),
                request_type: requestType,
                notes: notes
            })
        });

        const data = await response.json();

        if (data.success) {
            alertContainer.innerHTML = '<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i><span>Request created successfully! Redirecting...</span></div>';
            setTimeout(() => {
                window.location.href = basePath + '/admin-legacy/enterprise/gdpr/requests/' + data.id;
            }, 1500);
        } else {
            alertContainer.innerHTML = '<div class="alert alert-danger"><i class="fa-solid fa-exclamation-circle"></i><span>' + (data.error || 'Failed to create request') + '</span></div>';
        }
    } catch (error) {
        alertContainer.innerHTML = '<div class="alert alert-danger"><i class="fa-solid fa-exclamation-circle"></i><span>Network error. Please try again.</span></div>';
    }
});

// Simple user search functionality (could be enhanced with autocomplete)
document.getElementById('user_search').addEventListener('input', function(e) {
    const value = e.target.value;
    // If it looks like a number, set it as the user ID
    if (/^\d+$/.test(value)) {
        document.getElementById('user_id').value = value;
    }
});
</script>

<?php require dirname(__DIR__) . '/partials/enterprise-footer.php'; ?>

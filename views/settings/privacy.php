<?php
/**
 * User Privacy Settings
 * GDPR-compliant privacy controls for users
 */
$pageTitle = 'Privacy Settings';
?>

<div class="privacy-settings">
    <div class="container py-4">
        <div class="row">
            <!-- Settings Navigation -->
            <div class="col-lg-3 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Settings</h5>
                        <nav class="nav flex-column settings-nav">
                            <a class="nav-link" href="/settings/profile"><i class="fas fa-user mr-2"></i> Profile</a>
                            <a class="nav-link" href="/settings/account"><i class="fas fa-cog mr-2"></i> Account</a>
                            <a class="nav-link" href="/settings/security"><i class="fas fa-shield-alt mr-2"></i> Security</a>
                            <a class="nav-link" href="/settings/notifications"><i class="fas fa-bell mr-2"></i> Notifications</a>
                            <a class="nav-link active" href="/settings/privacy"><i class="fas fa-lock mr-2"></i> Privacy</a>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Privacy Settings Content -->
            <div class="col-lg-9">
                <h2 class="mb-4">Privacy Settings</h2>

                <!-- Consent Management -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-check-circle mr-2"></i> Your Consents</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">
                            Manage how we use your data. Some consents are required for the service to function.
                        </p>

                        <?php foreach ($consents ?? [] as $consent): ?>
                            <div class="consent-item mb-4 pb-4 border-bottom">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="consent-info">
                                        <h6>
                                            <?= htmlspecialchars($consent['name']) ?>
                                            <?php if ($consent['required']): ?>
                                                <span class="badge badge-secondary ml-1">Required</span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="text-muted mb-2"><?= htmlspecialchars($consent['description']) ?></p>
                                        <?php if ($consent['granted_at']): ?>
                                            <small class="text-muted">
                                                Consented on <?= date('F j, Y', strtotime($consent['granted_at'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="consent-toggle">
                                        <?php if ($consent['required']): ?>
                                            <span class="badge badge-success"><i class="fas fa-check"></i> Active</span>
                                        <?php else: ?>
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input consent-switch"
                                                       id="consent_<?= $consent['id'] ?>"
                                                       data-consent-id="<?= $consent['id'] ?>"
                                                       <?= $consent['granted'] ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="consent_<?= $consent['id'] ?>"></label>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($consents)): ?>
                            <?php
                            $defaultConsents = [
                                ['id' => 1, 'name' => 'Essential Cookies', 'description' => 'Required for the website to function properly, including authentication and security features.', 'required' => true, 'granted' => true, 'granted_at' => date('Y-m-d')],
                                ['id' => 2, 'name' => 'Analytics', 'description' => 'Help us understand how you use our platform to improve the user experience.', 'required' => false, 'granted' => true, 'granted_at' => date('Y-m-d')],
                                ['id' => 3, 'name' => 'Marketing Communications', 'description' => 'Receive personalized offers, updates, and newsletters via email.', 'required' => false, 'granted' => false, 'granted_at' => null],
                                ['id' => 4, 'name' => 'Third-Party Integrations', 'description' => 'Allow sharing data with integrated third-party services for enhanced functionality.', 'required' => false, 'granted' => true, 'granted_at' => date('Y-m-d', strtotime('-30 days'))],
                            ];
                            foreach ($defaultConsents as $consent): ?>
                                <div class="consent-item mb-4 pb-4 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="consent-info">
                                            <h6>
                                                <?= $consent['name'] ?>
                                                <?php if ($consent['required']): ?>
                                                    <span class="badge badge-secondary ml-1">Required</span>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="text-muted mb-2"><?= $consent['description'] ?></p>
                                            <?php if ($consent['granted_at']): ?>
                                                <small class="text-muted">Consented on <?= date('F j, Y', strtotime($consent['granted_at'])) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="consent-toggle">
                                            <?php if ($consent['required']): ?>
                                                <span class="badge badge-success"><i class="fas fa-check"></i> Active</span>
                                            <?php else: ?>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input consent-switch" id="consent_<?= $consent['id'] ?>" <?= $consent['granted'] ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="consent_<?= $consent['id'] ?>"></label>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Your Data -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-database mr-2"></i> Your Data</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">
                            Under GDPR, you have the right to access, export, or delete your personal data at any time.
                        </p>

                        <div class="row">
                            <!-- Data Export -->
                            <div class="col-md-6 mb-4">
                                <div class="data-action-card">
                                    <div class="data-action-icon bg-primary">
                                        <i class="fas fa-download"></i>
                                    </div>
                                    <h6>Export Your Data</h6>
                                    <p class="text-muted small">
                                        Download a copy of all your personal data in machine-readable format (JSON).
                                    </p>
                                    <?php if ($pendingExport ?? false): ?>
                                        <div class="alert alert-info py-2 small">
                                            <i class="fas fa-spinner fa-spin mr-1"></i>
                                            Export in progress... You'll receive an email when ready.
                                        </div>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-primary" onclick="requestDataExport()">
                                            <i class="fas fa-download mr-1"></i> Request Export
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Data Portability -->
                            <div class="col-md-6 mb-4">
                                <div class="data-action-card">
                                    <div class="data-action-icon bg-info">
                                        <i class="fas fa-exchange-alt"></i>
                                    </div>
                                    <h6>Data Portability</h6>
                                    <p class="text-muted small">
                                        Transfer your data to another service in a standardized format.
                                    </p>
                                    <button type="button" class="btn btn-outline-info" onclick="requestDataPortability()">
                                        <i class="fas fa-exchange-alt mr-1"></i> Request Transfer
                                    </button>
                                </div>
                            </div>

                            <!-- Data Rectification -->
                            <div class="col-md-6 mb-4">
                                <div class="data-action-card">
                                    <div class="data-action-icon bg-warning">
                                        <i class="fas fa-edit"></i>
                                    </div>
                                    <h6>Correct Your Data</h6>
                                    <p class="text-muted small">
                                        Request correction of inaccurate or incomplete personal data.
                                    </p>
                                    <a href="/settings/profile" class="btn btn-outline-warning">
                                        <i class="fas fa-edit mr-1"></i> Edit Profile
                                    </a>
                                </div>
                            </div>

                            <!-- Delete Account -->
                            <div class="col-md-6 mb-4">
                                <div class="data-action-card">
                                    <div class="data-action-icon bg-danger">
                                        <i class="fas fa-trash"></i>
                                    </div>
                                    <h6>Delete Your Account</h6>
                                    <p class="text-muted small">
                                        Permanently delete your account and all associated data.
                                    </p>
                                    <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#deleteAccountModal">
                                        <i class="fas fa-trash mr-1"></i> Delete Account
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Previous Requests -->
                <?php if (!empty($previousRequests ?? [])): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-history mr-2"></i> Previous Requests</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Requested</th>
                                            <th>Completed</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($previousRequests as $request): ?>
                                            <tr>
                                                <td><?= formatRequestType($request['request_type']) ?></td>
                                                <td>
                                                    <span class="badge badge-<?= getStatusBadge($request['status']) ?>">
                                                        <?= ucfirst($request['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($request['created_at'])) ?></td>
                                                <td><?= $request['completed_at'] ? date('M j, Y', strtotime($request['completed_at'])) : '-' ?></td>
                                                <td>
                                                    <?php if ($request['download_url']): ?>
                                                        <a href="<?= htmlspecialchars($request['download_url']) ?>" class="btn btn-sm btn-outline-primary" download>
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Privacy Policy Link -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="privacy-icon mr-3">
                                <i class="fas fa-file-contract fa-2x text-muted"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Privacy Policy</h6>
                                <p class="text-muted mb-0">
                                    Learn more about how we collect, use, and protect your data.
                                </p>
                            </div>
                            <a href="/privacy-policy" class="btn btn-outline-secondary ml-auto">
                                Read Policy <i class="fas fa-external-link-alt ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i> Delete Account</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="/api/gdpr/delete-account" method="POST" id="deleteAccountForm">
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>Warning:</strong> This action is permanent and cannot be undone.
                    </div>

                    <p>Deleting your account will:</p>
                    <ul>
                        <li>Permanently delete your profile and all personal data</li>
                        <li>Remove all your posts, comments, and content</li>
                        <li>Cancel any active subscriptions</li>
                        <li>Remove you from all groups and communities</li>
                    </ul>

                    <p>Some data may be retained for legal compliance (e.g., transaction records).</p>

                    <hr>

                    <div class="form-group">
                        <label>Please type <strong>DELETE</strong> to confirm:</label>
                        <input type="text" class="form-control" id="deleteConfirmation" required pattern="DELETE" title="Type DELETE to confirm">
                    </div>

                    <div class="form-group">
                        <label>Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Reason for leaving (optional)</label>
                        <select name="reason" class="form-control">
                            <option value="">Select a reason...</option>
                            <option value="privacy_concerns">Privacy concerns</option>
                            <option value="not_using">Not using the service anymore</option>
                            <option value="found_alternative">Found a better alternative</option>
                            <option value="too_many_emails">Too many emails/notifications</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Additional feedback (optional)</label>
                        <textarea name="feedback" class="form-control" rows="2" placeholder="Help us improve..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="deleteAccountBtn" disabled>
                        <i class="fas fa-trash mr-1"></i> Permanently Delete Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.privacy-settings .settings-nav .nav-link {
    color: #495057;
    padding: 0.5rem 0;
    border-radius: 0;
}

.privacy-settings .settings-nav .nav-link:hover {
    color: #007bff;
}

.privacy-settings .settings-nav .nav-link.active {
    color: #007bff;
    font-weight: 600;
    background: none;
}

.privacy-settings .consent-item:last-child {
    border-bottom: none !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

.privacy-settings .data-action-card {
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 8px;
    height: 100%;
}

.privacy-settings .data-action-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    margin-bottom: 1rem;
}

.privacy-settings .data-action-card h6 {
    margin-bottom: 0.5rem;
}
</style>

<script>
// Consent toggle handler
document.querySelectorAll('.consent-switch').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const consentId = this.dataset.consentId || this.id.replace('consent_', '');
        const granted = this.checked;

        fetch('/api/gdpr/consent', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                consent_id: consentId,
                granted: granted
            })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert('Failed to update consent. Please try again.');
                this.checked = !granted; // Revert
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            this.checked = !granted;
        });
    });
});

// Delete account confirmation
document.getElementById('deleteConfirmation').addEventListener('input', function() {
    document.getElementById('deleteAccountBtn').disabled = this.value !== 'DELETE';
});

function requestDataExport() {
    if (confirm('Request a copy of all your personal data?\n\nYou will receive an email with a download link when your export is ready (usually within 24 hours).')) {
        fetch('/api/gdpr/request', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({type: 'data_export'})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Data export requested successfully!\n\nYou will receive an email when your data is ready for download.');
                location.reload();
            } else {
                alert('Failed to request export: ' + (data.error || 'Unknown error'));
            }
        });
    }
}

function requestDataPortability() {
    if (confirm('Request your data in a portable format?\n\nThis will prepare your data for transfer to another service.')) {
        fetch('/api/gdpr/request', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({type: 'data_portability'})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Data portability request submitted!\n\nYou will receive an email when your data is ready.');
                location.reload();
            } else {
                alert('Failed to submit request: ' + (data.error || 'Unknown error'));
            }
        });
    }
}
</script>

<?php
function formatRequestType($type) {
    $types = [
        'data_export' => 'Data Export',
        'data_deletion' => 'Account Deletion',
        'data_portability' => 'Data Portability',
        'data_rectification' => 'Data Correction',
    ];
    return $types[$type] ?? ucwords(str_replace('_', ' ', $type));
}

function getStatusBadge($status) {
    return ['pending' => 'warning', 'in_progress' => 'info', 'completed' => 'success', 'rejected' => 'danger'][$status] ?? 'secondary';
}
?>

<?php
/**
 * GDPR Breach Report Form - Gold Standard v2.0
 * STANDALONE Admin Interface with Holographic Glassmorphism
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Admin header configuration
$adminPageTitle = 'Report Breach';
$adminPageSubtitle = 'Enterprise GDPR';
$adminPageIcon = 'fa-shield-exclamation';

// Include standalone admin header
require dirname(__DIR__, 2) . '/partials/admin-header.php';

// Navigation context for enterprise nav
$currentSection = 'gdpr';
$currentPage = 'breaches';
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <h1 class="admin-page-title">
            <a href="<?= $basePath ?>/admin/enterprise/gdpr/breaches" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            Report Security Incident
        </h1>
        <p class="admin-page-subtitle">Document a data breach for GDPR compliance</p>
    </div>
    <div class="admin-page-actions">
        <a href="<?= $basePath ?>/admin/enterprise/gdpr/breaches" class="admin-btn admin-btn-secondary">
            <i class="fa-solid fa-times"></i> Cancel
        </a>
    </div>
</div>

<!-- Enterprise Sub-Navigation -->
<?php require dirname(__DIR__) . '/partials/nav.php'; ?>

<style>
/* Page Header */
.admin-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.admin-page-header-content {
    flex: 1;
}

.admin-page-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.admin-page-subtitle {
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.6);
    margin: 0;
}

[data-theme="light"] .admin-page-title {
    color: #1e293b;
}

[data-theme="light"] .admin-page-subtitle {
    color: #64748b;
}

.admin-page-actions {
    display: flex;
    gap: 0.75rem;
    flex-shrink: 0;
}

.back-link {
    color: inherit;
    text-decoration: none;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 1;
}

/* Admin Buttons */
.admin-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: 0.75rem;
    font-weight: 600;
    font-size: 0.875rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.admin-btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
}

.admin-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
}

.admin-btn-secondary {
    background: rgba(99, 102, 241, 0.1);
    border: 1px solid rgba(99, 102, 241, 0.2);
    color: #a5b4fc;
}

.admin-btn-secondary:hover {
    background: rgba(99, 102, 241, 0.15);
    border-color: rgba(99, 102, 241, 0.3);
}

[data-theme="light"] .admin-btn-secondary {
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.15);
    color: #6366f1;
}

.admin-btn-danger {
    background: linear-gradient(135deg, #dc2626, #ef4444);
    color: white;
    box-shadow: 0 4px 14px rgba(220, 38, 38, 0.4);
}

.admin-btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5);
}

/* Warning Banner */
.warning-alert-banner {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(251, 191, 36, 0.1));
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-left: 4px solid #f59e0b;
    border-radius: 1rem;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.25rem;
}

.warning-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.warning-content {
    flex: 1;
}

.warning-title {
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.warning-message {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.7);
    line-height: 1.5;
}

[data-theme="light"] .warning-title {
    color: #1e293b;
}

[data-theme="light"] .warning-message {
    color: #64748b;
}

/* Form Container */
.breach-report-container {
    max-width: 900px;
}

/* Glass Card */
.admin-glass-card {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 1.25rem;
    overflow: hidden;
    margin-bottom: 1.5rem;
}

[data-theme="light"] .admin-glass-card {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.admin-card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.admin-card-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.admin-card-header-content {
    flex: 1;
}

.admin-card-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
}

.admin-card-subtitle {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.5);
    margin: 0.25rem 0 0 0;
}

[data-theme="light"] .admin-card-title {
    color: #1e293b;
}

[data-theme="light"] .admin-card-subtitle {
    color: #64748b;
}

.admin-card-body {
    padding: 1.5rem;
}

/* Form Styles */
.form-section {
    margin-bottom: 2rem;
}

.form-section:last-child {
    margin-bottom: 0;
}

.form-section-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
}

[data-theme="light"] .form-section-title {
    color: #64748b;
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #f1f5f9;
    margin-bottom: 0.5rem;
}

.form-label .required {
    color: #ef4444;
    margin-left: 2px;
}

[data-theme="light"] .form-label {
    color: #1e293b;
}

.form-input,
.form-select,
.form-textarea {
    width: 100%;
    padding: 0.875rem 1rem;
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 0.75rem;
    color: #f1f5f9;
    font-size: 0.95rem;
    transition: all 0.2s;
}

[data-theme="light"] .form-input,
[data-theme="light"] .form-select,
[data-theme="light"] .form-textarea {
    background: rgba(99, 102, 241, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.15);
    color: #1e293b;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    background: rgba(99, 102, 241, 0.08);
}

.form-input::placeholder,
.form-textarea::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

[data-theme="light"] .form-input::placeholder,
[data-theme="light"] .form-textarea::placeholder {
    color: #94a3b8;
}

.form-textarea {
    min-height: 120px;
    resize: vertical;
    line-height: 1.5;
}

.form-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    padding-right: 2.5rem;
    cursor: pointer;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
}

@media (max-width: 640px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

.form-help {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.4);
    margin-top: 0.5rem;
}

[data-theme="light"] .form-help {
    color: #64748b;
}

/* Severity Options */
.severity-options {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
}

@media (max-width: 768px) {
    .severity-options {
        grid-template-columns: repeat(2, 1fr);
    }
}

.severity-option {
    position: relative;
}

.severity-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.severity-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    background: rgba(99, 102, 241, 0.05);
    border: 2px solid rgba(99, 102, 241, 0.15);
    border-radius: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
}

[data-theme="light"] .severity-label {
    border: 2px solid rgba(99, 102, 241, 0.12);
}

.severity-label:hover {
    border-color: var(--severity-color);
    background: var(--severity-bg);
}

.severity-option input:checked + .severity-label {
    border-color: var(--severity-color);
    background: var(--severity-bg);
    box-shadow: 0 4px 15px var(--severity-shadow);
}

.severity-option.critical {
    --severity-color: #dc2626;
    --severity-bg: rgba(220, 38, 38, 0.15);
    --severity-shadow: rgba(220, 38, 38, 0.25);
}
.severity-option.high {
    --severity-color: #ef4444;
    --severity-bg: rgba(239, 68, 68, 0.15);
    --severity-shadow: rgba(239, 68, 68, 0.25);
}
.severity-option.medium {
    --severity-color: #f59e0b;
    --severity-bg: rgba(245, 158, 11, 0.15);
    --severity-shadow: rgba(245, 158, 11, 0.25);
}
.severity-option.low {
    --severity-color: #10b981;
    --severity-bg: rgba(16, 185, 129, 0.15);
    --severity-shadow: rgba(16, 185, 129, 0.25);
}

.severity-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
    transition: transform 0.2s;
}

.severity-option input:checked + .severity-label .severity-icon {
    transform: scale(1.1);
}

.severity-option.critical .severity-icon { background: linear-gradient(135deg, #dc2626, #b91c1c); }
.severity-option.high .severity-icon { background: linear-gradient(135deg, #ef4444, #dc2626); }
.severity-option.medium .severity-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
.severity-option.low .severity-icon { background: linear-gradient(135deg, #10b981, #059669); }

.severity-text {
    font-size: 0.8rem;
    font-weight: 600;
    color: #f1f5f9;
}

[data-theme="light"] .severity-text {
    color: #1e293b;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 1rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(99, 102, 241, 0.15);
    margin-top: 1.5rem;
}

[data-theme="light"] .form-actions {
    border-top: 1px solid rgba(99, 102, 241, 0.1);
}

/* Help Card */
.help-card {
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 0.75rem;
    padding: 1rem 1.25rem;
    margin-top: 1.5rem;
}

[data-theme="light"] .help-card {
    background: rgba(99, 102, 241, 0.05);
}

.help-card-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: #a5b4fc;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

[data-theme="light"] .help-card-title {
    color: #6366f1;
}

.help-card-content {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    line-height: 1.5;
}

[data-theme="light"] .help-card-content {
    color: #64748b;
}

.help-card-content ul {
    margin: 0.5rem 0 0 0;
    padding-left: 1.25rem;
}

.help-card-content li {
    margin-bottom: 0.25rem;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-page-header {
        flex-direction: column;
        align-items: stretch;
    }

    .admin-page-actions {
        justify-content: flex-end;
    }

    .warning-alert-banner {
        flex-direction: column;
        text-align: center;
    }

    .form-actions {
        flex-direction: column;
    }

    .admin-btn {
        justify-content: center;
    }
}
</style>

<!-- Warning Banner -->
<div class="warning-alert-banner">
    <div class="warning-icon-box">
        <i class="fa-solid fa-clock"></i>
    </div>
    <div class="warning-content">
        <div class="warning-title">
            <i class="fa-solid fa-triangle-exclamation"></i>
            GDPR 72-Hour Notification Requirement
        </div>
        <div class="warning-message">
            Under GDPR Article 33, data breaches must be reported to the supervisory authority within 72 hours of becoming aware of the breach. Document all details thoroughly for compliance.
        </div>
    </div>
</div>

<!-- Report Form -->
<div class="breach-report-container">
    <div class="admin-glass-card">
        <div class="admin-card-header">
            <div class="admin-card-header-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                <i class="fa-solid fa-shield-exclamation"></i>
            </div>
            <div class="admin-card-header-content">
                <h3 class="admin-card-title">Security Incident Details</h3>
                <p class="admin-card-subtitle">Provide comprehensive information about the data breach</p>
            </div>
        </div>

        <div class="admin-card-body">
            <form id="breachReportForm" onsubmit="submitBreachReport(event)">

                <!-- Severity Section -->
                <div class="form-section">
                    <div class="form-section-title">Incident Classification</div>

                    <div class="form-group">
                        <label class="form-label">Severity Level <span class="required">*</span></label>
                        <div class="severity-options">
                            <div class="severity-option critical">
                                <input type="radio" name="severity" value="critical" id="sev-critical" required>
                                <label for="sev-critical" class="severity-label">
                                    <div class="severity-icon"><i class="fa-solid fa-skull"></i></div>
                                    <span class="severity-text">Critical</span>
                                </label>
                            </div>
                            <div class="severity-option high">
                                <input type="radio" name="severity" value="high" id="sev-high">
                                <label for="sev-high" class="severity-label">
                                    <div class="severity-icon"><i class="fa-solid fa-fire"></i></div>
                                    <span class="severity-text">High</span>
                                </label>
                            </div>
                            <div class="severity-option medium">
                                <input type="radio" name="severity" value="medium" id="sev-medium" checked>
                                <label for="sev-medium" class="severity-label">
                                    <div class="severity-icon"><i class="fa-solid fa-exclamation"></i></div>
                                    <span class="severity-text">Medium</span>
                                </label>
                            </div>
                            <div class="severity-option low">
                                <input type="radio" name="severity" value="low" id="sev-low">
                                <label for="sev-low" class="severity-label">
                                    <div class="severity-icon"><i class="fa-solid fa-info"></i></div>
                                    <span class="severity-text">Low</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="breach_type">Breach Type <span class="required">*</span></label>
                            <select name="breach_type" id="breach_type" class="form-select" required>
                                <option value="">Select type...</option>
                                <option value="unauthorized_access">Unauthorized Access</option>
                                <option value="data_theft">Data Theft</option>
                                <option value="ransomware">Ransomware Attack</option>
                                <option value="phishing">Phishing Attack</option>
                                <option value="accidental_disclosure">Accidental Disclosure</option>
                                <option value="lost_device">Lost/Stolen Device</option>
                                <option value="malware">Malware Infection</option>
                                <option value="insider_threat">Insider Threat</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="data_categories">Data Categories Affected <span class="required">*</span></label>
                            <select name="data_categories" id="data_categories" class="form-select" required>
                                <option value="">Select category...</option>
                                <option value="personal">Personal Data</option>
                                <option value="sensitive">Sensitive Personal Data</option>
                                <option value="financial">Financial Data</option>
                                <option value="health">Health Data</option>
                                <option value="credentials">Credentials/Passwords</option>
                                <option value="mixed">Multiple Categories</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Impact Section -->
                <div class="form-section">
                    <div class="form-section-title">Impact Assessment</div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="affected_users">Estimated Affected Users <span class="required">*</span></label>
                            <input type="number" name="affected_users" id="affected_users" class="form-input"
                                   placeholder="Number of users" min="0" required>
                            <p class="form-help">Provide your best estimate of users whose data may have been compromised</p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="detected_at">Date/Time Detected <span class="required">*</span></label>
                            <input type="datetime-local" name="detected_at" id="detected_at" class="form-input" required>
                            <p class="form-help">When was the breach first discovered?</p>
                        </div>
                    </div>
                </div>

                <!-- Description Section -->
                <div class="form-section">
                    <div class="form-section-title">Incident Details</div>

                    <div class="form-group">
                        <label class="form-label" for="description">Description <span class="required">*</span></label>
                        <textarea name="description" id="description" class="form-textarea"
                                  placeholder="Describe the breach incident, how it was discovered, and initial assessment of impact..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="containment_actions">Containment Actions Taken</label>
                        <textarea name="containment_actions" id="containment_actions" class="form-textarea"
                                  placeholder="Describe any immediate actions taken to contain the breach..."></textarea>
                    </div>
                </div>

                <!-- Help Card -->
                <div class="help-card">
                    <div class="help-card-title">
                        <i class="fa-solid fa-circle-info"></i>
                        What to Include
                    </div>
                    <div class="help-card-content">
                        For a complete breach report, include:
                        <ul>
                            <li>How the breach was detected and by whom</li>
                            <li>Systems and data types affected</li>
                            <li>Timeline of events (discovery, containment, notification)</li>
                            <li>Any evidence of data exfiltration</li>
                            <li>Initial containment measures implemented</li>
                        </ul>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="admin-btn admin-btn-danger">
                        <i class="fa-solid fa-shield-exclamation"></i>
                        Submit Breach Report
                    </button>
                    <a href="<?= $basePath ?>/admin/enterprise/gdpr/breaches" class="admin-btn admin-btn-secondary">
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
// Set default detected_at to now
document.getElementById('detected_at').value = new Date().toISOString().slice(0, 16);

function submitBreachReport(e) {
    e.preventDefault();

    const form = document.getElementById('breachReportForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Validate
    if (!data.severity || !data.breach_type || !data.affected_users || !data.description) {
        showToast('Please fill in all required fields', 'error');
        return;
    }

    // Disable submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';

    fetch('<?= $basePath ?>/admin/enterprise/gdpr/breaches', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            showToast(result.message || 'Breach reported successfully', 'success');
            setTimeout(() => {
                window.location.href = '<?= $basePath ?>/admin/enterprise/gdpr/breaches';
            }, 1500);
        } else {
            showToast('Error: ' + (result.error || 'Failed to submit report'), 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(err => {
        console.error('Submit error:', err);
        showToast('Network error. Please try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

// Toast notification (use global if available, otherwise fallback)
function showToast(message, type = 'info') {
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
    } else {
        alert(message);
    }
}
</script>

<?php require dirname(__DIR__, 2) . '/partials/admin-footer.php'; ?>

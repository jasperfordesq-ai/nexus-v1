<?php
/**
 * Modern GDPR Breach Report Form - Gold Standard v2.0
 * Dark Mode Optimized Security Incident Reporting
 */

use Nexus\Core\TenantContext;

// Navigation context
$currentSection = 'gdpr';
$currentPage = 'breaches';

$basePath = TenantContext::getBasePath();

$pageTitle = 'Report Breach';
$pageIcon = 'fa-triangle-exclamation';
require dirname(__DIR__, 3) . '/layouts/admin-header.php';
require dirname(__DIR__) . '/partials/nav.php';
?>

<!-- Admin Page Header -->
<div class="admin-page-header">
    <div class="admin-page-header-content">
        <div class="admin-page-header-left">
            <div class="admin-page-icon">
                <i class="fa-solid <?= $pageIcon ?>"></i>
            </div>
            <div>
                <h1 class="admin-page-title"><?= $pageTitle ?></h1>
                <p class="admin-page-subtitle">Document Security Incident for GDPR Compliance</p>
            </div>
        </div>
    </div>
</div>

<style>
.report-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 24px;
    position: relative;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #94a3b8;
    text-decoration: none;
    font-size: 0.875rem;
    margin-bottom: 24px;
    transition: all 0.2s;
}

.back-link:hover {
    color: #6366f1;
}

[data-theme="light"] .back-link {
    color: #64748b;
}

[data-theme="light"] .back-link:hover {
    color: #6366f1;
}

/* Warning Banner */
.warning-banner {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(251, 191, 36, 0.1));
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-left: 4px solid #f59e0b;
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 32px;
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

.warning-banner i {
    font-size: 1.5rem;
    color: #f59e0b;
    flex-shrink: 0;
}

.warning-content h4 {
    font-size: 1rem;
    font-weight: 700;
    color: #f1f5f9;
    margin-bottom: 8px;
}

.warning-content p {
    font-size: 0.9rem;
    color: #94a3b8;
    margin: 0;
    line-height: 1.6;
}

[data-theme="light"] .warning-content h4 {
    color: #1e293b;
}

[data-theme="light"] .warning-content p {
    color: #64748b;
}

/* Form Card */
.form-card {
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 24px;
    overflow: hidden;
}

[data-theme="light"] .form-card {
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(99, 102, 241, 0.15);
}

.form-card-header {
    padding: 24px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.2);
    display: flex;
    align-items: center;
    gap: 16px;
}

[data-theme="light"] .form-card-header {
    border-bottom: 1px solid rgba(99, 102, 241, 0.15);
}

.form-card-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: linear-gradient(135deg, #ef4444, #f87171);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.form-card-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #f1f5f9;
}

.form-card-subtitle {
    font-size: 0.875rem;
    color: #94a3b8;
}

[data-theme="light"] .form-card-title {
    color: #1e293b;
}

[data-theme="light"] .form-card-subtitle {
    color: #64748b;
}

.form-card-body {
    padding: 32px;
}

/* Form Styles */
.form-group {
    margin-bottom: 24px;
}

.form-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #f1f5f9;
    margin-bottom: 8px;
}

.form-label .required {
    color: #ef4444;
}

[data-theme="light"] .form-label {
    color: #1e293b;
}

.form-input,
.form-select,
.form-textarea {
    width: 100%;
    padding: 14px 18px;
    background: rgba(99, 102, 241, 0.05);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    color: #f1f5f9;
    font-size: 0.95rem;
    transition: all 0.2s;
}

[data-theme="light"] .form-input,
[data-theme="light"] .form-select,
[data-theme="light"] .form-textarea {
    border: 1px solid rgba(99, 102, 241, 0.15);
    color: #1e293b;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.form-input::placeholder,
.form-textarea::placeholder {
    color: #94a3b8;
}

[data-theme="light"] .form-input::placeholder,
[data-theme="light"] .form-textarea::placeholder {
    color: #64748b;
}

.form-textarea {
    min-height: 120px;
    resize: vertical;
}

.form-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 16px center;
    padding-right: 48px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

@media (max-width: 640px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

/* Severity Options */
.severity-options {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

@media (max-width: 640px) {
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
    gap: 8px;
    padding: 16px;
    background: rgba(99, 102, 241, 0.05);
    border: 2px solid rgba(99, 102, 241, 0.2);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

[data-theme="light"] .severity-label {
    border: 2px solid rgba(99, 102, 241, 0.15);
}

.severity-option input:checked + .severity-label {
    border-color: var(--severity-color);
    background: var(--severity-bg);
}

.severity-option.critical { --severity-color: #dc2626; --severity-bg: rgba(220, 38, 38, 0.15); }
.severity-option.high { --severity-color: #ef4444; --severity-bg: rgba(239, 68, 68, 0.15); }
.severity-option.medium { --severity-color: #f59e0b; --severity-bg: rgba(245, 158, 11, 0.15); }
.severity-option.low { --severity-color: #10b981; --severity-bg: rgba(16, 185, 129, 0.15); }

.severity-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.severity-option.critical .severity-icon { background: #dc2626; }
.severity-option.high .severity-icon { background: #ef4444; }
.severity-option.medium .severity-icon { background: #f59e0b; }
.severity-option.low .severity-icon { background: #10b981; }

.severity-text {
    font-size: 0.8rem;
    font-weight: 600;
    color: #f1f5f9;
}

[data-theme="light"] .severity-text {
    color: #1e293b;
}

/* Buttons */
.form-actions {
    display: flex;
    gap: 16px;
    padding-top: 16px;
    border-top: 1px solid rgba(99, 102, 241, 0.2);
    margin-top: 32px;
}

[data-theme="light"] .form-actions {
    border-top: 1px solid rgba(99, 102, 241, 0.15);
}

.cyber-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 28px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    text-decoration: none;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
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

.cyber-btn-outline:hover {
    background: rgba(99, 102, 241, 0.1);
}

[data-theme="light"] .cyber-btn-outline {
    color: #1e293b;
    border: 1px solid rgba(99, 102, 241, 0.15);
}

@media (max-width: 768px) {
    .report-container {
        padding: 16px;
    }
}
</style>

<div class="report-container">
    <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/breaches" class="back-link">
        <i class="fa-solid fa-arrow-left"></i>
        Back to Breaches
    </a>

    <!-- Warning Banner -->
    <div class="warning-banner">
        <i class="fa-solid fa-clock"></i>
        <div class="warning-content">
            <h4>GDPR 72-Hour Notification Requirement</h4>
            <p>Under GDPR Article 33, data breaches must be reported to the supervisory authority within 72 hours of becoming aware of the breach. Document all details thoroughly for compliance.</p>
        </div>
    </div>

    <!-- Report Form -->
    <div class="form-card">
        <div class="form-card-header">
            <div class="form-card-icon">
                <i class="fa-solid fa-shield-exclamation"></i>
            </div>
            <div>
                <div class="form-card-title">Report Security Incident</div>
                <div class="form-card-subtitle">Complete all required fields to document the breach</div>
            </div>
        </div>

        <div class="form-card-body">
            <form id="breachReportForm" onsubmit="submitBreachReport(event)">

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
                        <label class="form-label" for="affected_users">Estimated Affected Users <span class="required">*</span></label>
                        <input type="number" name="affected_users" id="affected_users" class="form-input"
                               placeholder="Number of users" min="0" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="detected_at">Date/Time Detected <span class="required">*</span></label>
                        <input type="datetime-local" name="detected_at" id="detected_at" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="data_categories">Data Categories Affected</label>
                        <select name="data_categories" id="data_categories" class="form-select">
                            <option value="personal">Personal Data</option>
                            <option value="sensitive">Sensitive Personal Data</option>
                            <option value="financial">Financial Data</option>
                            <option value="health">Health Data</option>
                            <option value="credentials">Credentials/Passwords</option>
                            <option value="mixed">Multiple Categories</option>
                        </select>
                    </div>
                </div>

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

                <div class="form-actions">
                    <button type="submit" class="cyber-btn cyber-btn-danger">
                        <i class="fa-solid fa-shield-exclamation"></i>
                        Submit Breach Report
                    </button>
                    <a href="<?= $basePath ?>/admin-legacy/enterprise/gdpr/breaches" class="cyber-btn cyber-btn-outline">
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
        alert('Please fill in all required fields');
        return;
    }

    fetch('<?= $basePath ?>/admin-legacy/enterprise/gdpr/breaches', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert(result.message || 'Breach reported successfully');
            window.location.href = '<?= $basePath ?>/admin-legacy/enterprise/gdpr/breaches';
        } else {
            alert('Error: ' + (result.error || 'Failed to submit report'));
        }
    })
    .catch(err => {
        console.error('Submit error:', err);
        alert('Network error. Please try again.');
    });
}
</script>

<?php require dirname(__DIR__, 3) . '/layouts/admin-footer.php'; ?>

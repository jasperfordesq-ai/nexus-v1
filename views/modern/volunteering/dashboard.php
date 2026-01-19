<?php
// Phoenix View: Organisation Dashboard - Holographic Design
$pageTitle = 'Organisation Dashboard';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';
?>


<!-- Offline Banner -->
<div class="holo-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="holo-dashboard-page">
    <!-- Floating Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="holo-container">
        <!-- Page Header -->
        <div class="holo-page-header">
            <div class="holo-page-icon">
                <i class="fa-solid fa-building-ngo"></i>
            </div>
            <h1 class="holo-page-title">Organisation Dashboard</h1>
            <p class="holo-page-subtitle">Manage your non-profit profile and volunteer opportunities</p>
        </div>

        <!-- Two Column Layout -->
        <div class="holo-grid-2">
            <!-- LEFT: Organisation Profile -->
            <div class="holo-glass-card">
                <div class="holo-card-header">
                    <h2 class="holo-card-title">
                        <i class="fa-solid fa-users"></i>
                        My Organizations
                    </h2>
                </div>
                <div class="holo-card-body">
                    <?php
                    $hasPending = false;
                    foreach ($myOrgs as $mo) {
                        if (($mo['status'] ?? 'approved') === 'pending') {
                            $hasPending = true;
                            break;
                        }
                    }
                    ?>

                    <?php if ($hasPending): ?>
                        <div class="holo-alert holo-alert-warning">
                            <i class="fa-solid fa-clock"></i>
                            <div class="holo-alert-content">
                                <strong>Pending Approval</strong>
                                Your organization is under review. You can create opportunities, but they won't be public until approved.
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($myOrgs)): ?>
                        <p style="color: rgba(255,255,255,0.5); margin-bottom: 20px;">You haven't created an organisation profile yet.</p>

                        <?php if (isset($currentUser) && $currentUser['profile_type'] === 'organisation'): ?>
                            <div class="holo-quick-setup">
                                <h5><i class="fa-solid fa-bolt"></i> Quick Setup</h5>
                                <p>Import details from your existing Organisation Profile?</p>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/org/create-from-profile" class="holo-quick-setup-btn">
                                    Import Profile <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <ul class="holo-org-list">
                            <?php foreach ($myOrgs as $org): ?>
                                <li class="holo-org-item">
                                    <span class="holo-org-name"><?= htmlspecialchars($org['name']) ?></span>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <?php if (\Nexus\Core\TenantContext::hasFeature('wallet')): ?>
                                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/wallet" class="holo-org-edit" style="background: linear-gradient(135deg, rgba(79, 70, 229, 0.3), rgba(139, 92, 246, 0.3)); border: 1px solid rgba(139, 92, 246, 0.4);">
                                            <i class="fa-solid fa-wallet"></i> Wallet
                                        </a>
                                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/members" class="holo-org-edit" style="background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.3);">
                                            <i class="fa-solid fa-users"></i> Members
                                        </a>
                                        <?php endif; ?>
                                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/org/edit/<?= $org['id'] ?>" class="holo-org-edit">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <hr class="holo-divider">

                    <h3 style="color: white; font-size: 1.1rem; margin: 0 0 20px;">
                        <i class="fa-solid fa-plus" style="color: #14b8a6;"></i> Create New Organisation
                    </h3>

                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/org/store" method="POST" id="createOrgForm">
                        <?= \Nexus\Core\Csrf::input() ?>

                        <div class="holo-form-group">
                            <input type="text" name="name" placeholder="Organisation Name (e.g. Red Cross)" required class="holo-input">
                        </div>

                        <div class="holo-form-group">
                            <input type="email" name="email" placeholder="Contact Email" required class="holo-input">
                        </div>

                        <div class="holo-form-group">
                            <textarea name="description" placeholder="Short description (min 100 characters)..." class="holo-textarea" id="orgDescription"></textarea>
                        </div>

                        <!-- Institutional Verification -->
                        <div class="holo-verification-box">
                            <h4 class="holo-verification-title">
                                <i class="fa-solid fa-shield-check"></i>
                                Institutional Verification
                            </h4>

                            <div class="holo-form-group">
                                <label class="holo-label">Organization Type</label>
                                <select name="org_type" id="org_type_select" required class="holo-select">
                                    <option value="">Select Type...</option>
                                    <option value="Registered Charity">Registered Charity</option>
                                    <option value="Community Group / Tidy Towns">Community Group / Tidy Towns</option>
                                    <option value="Sports Club (GAA/Soccer)">Sports Club (GAA/Soccer)</option>
                                    <option value="Residents Association">Residents Association</option>
                                    <option value="NGO/Non-Profit">NGO / Non-Profit</option>
                                    <option value="Other Professional Body">Other Professional Body</option>
                                </select>
                            </div>

                            <!-- RCN Field -->
                            <div class="holo-form-group" id="field_rcn" style="display: none;">
                                <label class="holo-label">Registered Charity Number (RCN)</label>
                                <input type="text" name="rcn_number" placeholder="e.g. 20012345" class="holo-input" id="rcnInput">
                            </div>

                            <!-- Verification Contact -->
                            <div class="holo-form-group" id="field_contact" style="display: none;">
                                <label class="holo-label">Verification Contact (Secretary/Treasurer)</label>
                                <input type="text" name="verification_contact" placeholder="Name & Phone/Email" class="holo-input" id="contactInput">
                                <div style="font-size: 0.8rem; color: rgba(255,255,255,0.5); margin-top: 6px;">
                                    We require a second point of contact for unincorporated groups.
                                </div>
                            </div>
                        </div>

                        <div class="holo-checkbox-group">
                            <label class="holo-checkbox-label">
                                <input type="checkbox" name="license_agreed" id="licenseCheck" value="1" required>
                                <span>I agree to the <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/legal/volunteer-license" target="_blank">Volunteer Module License Agreement</a>.</span>
                            </label>
                        </div>

                        <button type="submit" class="holo-submit-btn" id="createOrgBtn" disabled>
                            Create Profile
                        </button>
                    </form>
                </div>
            </div>

            <!-- RIGHT: Post Opportunity -->
            <div class="holo-glass-card">
                <div class="holo-card-header">
                    <h2 class="holo-card-title">
                        <i class="fa-solid fa-bullhorn"></i>
                        Post Opportunity
                    </h2>
                </div>
                <div class="holo-card-body">
                    <?php if (empty($myOrgs)): ?>
                        <div class="holo-alert holo-alert-error">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <div class="holo-alert-content">
                                Please create an Organisation profile first.
                            </div>
                        </div>
                    <?php else: ?>
                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/opp/store" method="POST">
                            <?= \Nexus\Core\Csrf::input() ?>

                            <div class="holo-form-group">
                                <label class="holo-label">Posting As</label>
                                <select name="org_id" class="holo-select">
                                    <?php foreach ($myOrgs as $org): ?>
                                        <option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="holo-form-group">
                                <label class="holo-label">Opportunity Title</label>
                                <input type="text" name="title" placeholder="e.g. Community Garden Helper" required class="holo-input">
                            </div>

                            <div class="holo-form-group">
                                <label class="holo-label">Category</label>
                                <select name="category_id" class="holo-select">
                                    <option value="">Select a Category...</option>
                                    <?php if (!empty($categories)): ?>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="holo-form-group">
                                <label class="holo-label">Description</label>
                                <textarea name="description" rows="4" placeholder="Describe the role and responsibilities..." required class="holo-textarea"></textarea>
                            </div>

                            <div class="holo-form-group">
                                <label class="holo-label">Location</label>
                                <input type="text" name="location" placeholder="Address or 'Remote'" required class="holo-input mapbox-location-input-v2">
                                <input type="hidden" name="latitude">
                                <input type="hidden" name="longitude">
                            </div>

                            <div class="holo-form-group">
                                <label class="holo-label">Required Skills</label>
                                <input type="text" name="skills" placeholder="e.g. Gardening, Teamwork (comma separated)" class="holo-input">
                            </div>

                            <div class="holo-form-grid-2">
                                <div class="holo-form-group">
                                    <label class="holo-label">Start Date</label>
                                    <input type="datetime-local" name="start_date" required class="holo-input">
                                </div>
                                <div class="holo-form-group">
                                    <label class="holo-label">End Date (Optional)</label>
                                    <input type="datetime-local" name="end_date" class="holo-input">
                                </div>
                            </div>

                            <button type="submit" class="holo-submit-btn">
                                <i class="fa-solid fa-paper-plane"></i> Post Opportunity
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Hours Verification -->
        <div class="holo-glass-card holo-glass-card-accent">
            <div class="holo-card-header">
                <h2 class="holo-card-title">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    Pending Hours Verification
                </h2>
            </div>
            <div class="holo-card-body">
                <?php
                $pendingLogs = [];
                foreach ($myOrgs as $org) {
                    $logs = \Nexus\Models\VolLog::getForOrg($org['id'], 'pending');
                    foreach ($logs as $l) {
                        $l['org_name'] = $org['name'];
                        $pendingLogs[] = $l;
                    }
                }
                ?>

                <?php if (empty($pendingLogs)): ?>
                    <div class="holo-empty">
                        <i class="fa-solid fa-check-circle"></i>
                        <p>No pending hours to verify.</p>
                    </div>
                <?php else: ?>
                    <div class="holo-table-wrapper">
                        <table class="holo-table">
                            <thead>
                                <tr>
                                    <th>Volunteer</th>
                                    <th>Activity</th>
                                    <th>Date</th>
                                    <th>Hours</th>
                                    <th>Description</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingLogs as $log): ?>
                                    <tr>
                                        <td data-label="Volunteer" class="holo-table-primary"><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></td>
                                        <td data-label="Activity">
                                            <div class="holo-table-primary"><?= htmlspecialchars($log['opp_title'] ?? 'General') ?></div>
                                            <div class="holo-table-secondary"><?= htmlspecialchars($log['org_name']) ?></div>
                                        </td>
                                        <td data-label="Date"><?= date('M d', strtotime($log['date_logged'])) ?></td>
                                        <td data-label="Hours"><span style="color: #818cf8; font-weight: 700;"><?= $log['hours'] ?>h</span></td>
                                        <td data-label="Description" style="max-width: 200px;"><?= htmlspecialchars($log['description']) ?></td>
                                        <td data-label="">
                                            <div class="holo-action-btns">
                                                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/verify-hours" method="POST" style="display:inline;">
                                                    <?= \Nexus\Core\Csrf::input() ?>
                                                    <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                                    <input type="hidden" name="status" value="approved">
                                                    <button type="submit" class="holo-action-btn holo-action-btn-approve">
                                                        <i class="fa-solid fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/verify-hours" method="POST" style="display:inline;">
                                                    <?= \Nexus\Core\Csrf::input() ?>
                                                    <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                                    <input type="hidden" name="status" value="declined">
                                                    <button type="submit" class="holo-action-btn holo-action-btn-decline">
                                                        <i class="fa-solid fa-times"></i> Decline
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Active Listings -->
        <div class="holo-glass-card">
            <div class="holo-card-header">
                <h2 class="holo-card-title">
                    <i class="fa-solid fa-list"></i>
                    Active Listings
                </h2>
            </div>
            <div class="holo-card-body">
                <?php if (empty($myOpps)): ?>
                    <div class="holo-empty">
                        <i class="fa-solid fa-folder-open"></i>
                        <p>No active listings.</p>
                    </div>
                <?php else: ?>
                    <div class="holo-table-wrapper">
                        <table class="holo-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Organisation</th>
                                    <th>Posted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myOpps as $opp): ?>
                                    <tr>
                                        <td data-label="Title" class="holo-table-primary"><?= htmlspecialchars($opp['title']) ?></td>
                                        <td data-label="Organisation"><?= htmlspecialchars($opp['org_name']) ?></td>
                                        <td data-label="Posted"><?= date('M d', strtotime($opp['created_at'])) ?></td>
                                        <td data-label="">
                                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/<?= $opp['id'] ?>" class="holo-table-link" style="margin-right: 16px;">
                                                <i class="fa-solid fa-eye"></i> View
                                            </a>
                                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/opp/edit/<?= $opp['id'] ?>" class="holo-table-link">
                                                <i class="fa-solid fa-pen"></i> Edit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Received Applications -->
        <div class="holo-glass-card holo-glass-card-accent-purple">
            <div class="holo-card-header">
                <h2 class="holo-card-title">
                    <i class="fa-solid fa-inbox"></i>
                    Received Applications
                </h2>
            </div>
            <div class="holo-card-body">
                <?php if (empty($myApplications)): ?>
                    <div class="holo-empty">
                        <i class="fa-solid fa-envelope-open"></i>
                        <p>No applications received yet.</p>
                    </div>
                <?php else: ?>
                    <div class="holo-table-wrapper">
                        <table class="holo-table">
                            <thead>
                                <tr>
                                    <th>Volunteer</th>
                                    <th>Opportunity</th>
                                    <th>Status</th>
                                    <th>Message</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myApplications as $app): ?>
                                    <?php
                                    $statusClass = match ($app['status']) {
                                        'approved' => 'holo-status-approved',
                                        'declined' => 'holo-status-declined',
                                        default => 'holo-status-pending'
                                    };
                                    ?>
                                    <tr>
                                        <td data-label="Volunteer">
                                            <div class="holo-table-primary"><?= htmlspecialchars($app['user_name']) ?></div>
                                            <div class="holo-table-secondary"><?= htmlspecialchars($app['user_email']) ?></div>
                                        </td>
                                        <td data-label="Opportunity">
                                            <div class="holo-table-primary"><?= htmlspecialchars($app['opp_title']) ?></div>
                                            <?php if (!empty($app['shift_start'])): ?>
                                                <div class="holo-table-shift">
                                                    <i class="fa-regular fa-calendar"></i>
                                                    <?= date('M d, h:i A', strtotime($app['shift_start'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Status">
                                            <span class="holo-status <?= $statusClass ?>"><?= $app['status'] ?></span>
                                        </td>
                                        <td data-label="Message" style="max-width: 200px;"><?= htmlspecialchars($app['message'] ?? '-') ?></td>
                                        <td data-label="Date"><?= date('M d', strtotime($app['created_at'])) ?></td>
                                        <td data-label="">
                                            <?php if ($app['status'] == 'pending'): ?>
                                                <div class="holo-action-btns">
                                                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/app/update" method="POST" style="display:inline;">
                                                        <?= \Nexus\Core\Csrf::input() ?>
                                                        <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                                                        <input type="hidden" name="status" value="approved">
                                                        <button type="submit" class="holo-action-btn holo-action-btn-approve" title="Approve">
                                                            <i class="fa-solid fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/app/update" method="POST" style="display:inline;">
                                                        <?= \Nexus\Core\Csrf::input() ?>
                                                        <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                                                        <input type="hidden" name="status" value="declined">
                                                        <button type="submit" class="holo-action-btn holo-action-btn-decline" title="Decline">
                                                            <i class="fa-solid fa-times"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: rgba(255,255,255,0.3);">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Form Validation for Create Organization
(function() {
    const typeSelect = document.getElementById('org_type_select');
    const rcnDiv = document.getElementById('field_rcn');
    const contactDiv = document.getElementById('field_contact');
    const rcnInput = document.getElementById('rcnInput');
    const contactInput = document.getElementById('contactInput');
    const descInput = document.getElementById('orgDescription');
    const licenseCheck = document.getElementById('licenseCheck');
    const submitBtn = document.getElementById('createOrgBtn');

    if (!typeSelect) return;

    const rcnRegex = /^\d{8}$/;

    function setStatus(input, isValid, msg = '') {
        const parent = input.parentElement;
        let err = parent.querySelector('.validation-err');
        if (!err) {
            err = document.createElement('div');
            err.className = 'validation-err';
            parent.appendChild(err);
        }

        if (isValid) {
            input.style.borderColor = '#10b981';
            err.textContent = '';
        } else {
            input.style.borderColor = input.value.length > 0 ? '#f87171' : '';
            err.textContent = input.value.length > 0 ? msg : '';
        }
        return isValid;
    }

    function validateForm() {
        let valid = true;
        const type = typeSelect.value;
        const desc = descInput.value.trim();

        // Description Check (Min 100 chars)
        const descValid = desc.length >= 100;
        setStatus(descInput, descValid, 'Please provide a more detailed description (min 100 chars). Current: ' + desc.length);
        if (!descValid) valid = false;

        // Type Logic
        if (!type) valid = false;

        if (type === 'Registered Charity') {
            const rcnValid = rcnRegex.test(rcnInput.value);
            setStatus(rcnInput, rcnValid, 'Invalid RCN format. Must be exactly 8 digits.');
            if (!rcnValid) valid = false;
        } else if (type) {
            const cVal = contactInput.value.trim();
            const cValid = cVal.includes('@') && cVal.includes(' ') && cVal.length > 5;
            setStatus(contactInput, cValid, 'Please provide a Full Name AND an Email address');
            if (!cValid) valid = false;
        }

        // License Check
        if (!licenseCheck.checked) valid = false;

        submitBtn.disabled = !valid;
    }

    typeSelect.addEventListener('change', function() {
        const val = typeSelect.value;
        rcnDiv.style.display = 'none';
        contactDiv.style.display = 'none';
        if (rcnInput) rcnInput.required = false;
        if (contactInput) contactInput.required = false;

        if (val === 'Registered Charity') {
            rcnDiv.style.display = 'block';
            rcnInput.required = true;
        } else if (val) {
            contactDiv.style.display = 'block';
            contactInput.required = true;
        }
        validateForm();
    });

    [rcnInput, contactInput, descInput, licenseCheck].forEach(el => {
        if (el) {
            el.addEventListener('input', validateForm);
            el.addEventListener('change', validateForm);
        }
    });
})();

// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Form Submission Loading State & Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to submit.');
            return;
        }
        const btn = form.querySelector('button[type="submit"], .holo-submit-btn');
        if (btn) {
            btn.classList.add('loading');
        }
    });
});
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>

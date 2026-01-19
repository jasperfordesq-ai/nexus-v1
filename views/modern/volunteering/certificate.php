<?php
// Phoenix View: Volunteer Certificate - Modern Holographic Design
$pageTitle = 'Volunteer Certificate';
$hideHero = true;

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
    exit;
}

// Fetch User & Stats
$user = $currentUser ?? \Nexus\Models\User::findById($_SESSION['user_id']);
if (!$user) {
    die("User not found");
}

$userId = is_array($user) ? $user['id'] : $user->id;
$userName = is_array($user)
    ? ($user['first_name'] . ' ' . $user['last_name'])
    : ($user->first_name . ' ' . $user->last_name);

$totalHours = \Nexus\Models\VolLog::getTotalVerifiedHours($userId);
$logs = \Nexus\Models\VolLog::getForUser($userId);
$verifiedLogs = array_filter($logs, fn($l) => ($l['status'] ?? '') === 'approved');
$date = date('F j, Y');

// Preview mode
$previewMode = isset($_GET['preview']) && $_GET['preview'] === '1';
if ($previewMode) {
    $previewHours = 47.5;
    $previewLogs = [
        ['org_name' => 'Community Food Bank', 'date_logged' => date('Y-m-d', strtotime('-7 days')), 'hours' => 8],
        ['org_name' => 'Local Animal Shelter', 'date_logged' => date('Y-m-d', strtotime('-14 days')), 'hours' => 6],
        ['org_name' => 'Youth Mentorship Program', 'date_logged' => date('Y-m-d', strtotime('-21 days')), 'hours' => 12],
        ['org_name' => 'Environmental Cleanup', 'date_logged' => date('Y-m-d', strtotime('-30 days')), 'hours' => 5],
        ['org_name' => 'Senior Center', 'date_logged' => date('Y-m-d', strtotime('-45 days')), 'hours' => 16.5],
    ];
}

$displayHours = $previewMode ? $previewHours : $totalHours;
$displayLogs = $previewMode ? $previewLogs : $verifiedLogs;
$displayActivities = $previewMode ? count($previewLogs) : count($verifiedLogs);
$displayOrgs = $previewMode ? 5 : count(array_unique(array_column($verifiedLogs, 'organization_id')));

// Get tenant info
$tenant = \Nexus\Core\TenantContext::get();
$tenantName = $tenant['name'] ?? 'Hour Timebank';
$tenantLogo = $tenant['logo_url'] ?? '';

require __DIR__ . '/../../layouts/modern/header.php';
?>


<!-- Print-only certificate (simplified for clean printing) -->
<?php if ($totalHours > 0 || $previewMode): ?>
<div class="vc-print-area vc-print-only">
    <div class="vc-print-cert">
        <h1 class="vc-print-title">Certificate</h1>
        <p class="vc-print-subtitle">of Volunteer Service</p>

        <p class="vc-print-presents">This is to certify that</p>

        <p class="vc-print-name"><?= htmlspecialchars($userName) ?></p>
        <div class="vc-print-line"></div>

        <p class="vc-print-body">
            has generously dedicated their time and effort to support the community,
            contributing a verified total of
        </p>

        <div class="vc-print-hours"><?= number_format($displayHours, 1) ?> Hours</div>

        <p class="vc-print-body">of voluntary service.</p>

        <div class="vc-print-sigs">
            <div class="vc-print-sig">
                <div class="vc-print-sig-line"></div>
                <div class="vc-print-sig-label">Date: <?= $date ?></div>
            </div>
            <div class="vc-print-sig">
                <div class="vc-print-sig-val"><?= htmlspecialchars($tenantName) ?></div>
                <div class="vc-print-sig-line"></div>
                <div class="vc-print-sig-label">Authorized Signature</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main screen content -->
<div class="vc-page vc-screen-only">
    <!-- Floating Orbs -->
    <div class="vc-orb vc-orb-1"></div>
    <div class="vc-orb vc-orb-2"></div>
    <div class="vc-orb vc-orb-3"></div>

    <div class="vc-container">
        <!-- Page Header -->
        <div class="vc-header">
            <div class="vc-header-icon">
                <i class="fa-solid fa-award"></i>
            </div>
            <h1 class="vc-header-title">Volunteer Certificate</h1>
            <p class="vc-header-subtitle">Your verified volunteering record</p>
        </div>

        <!-- Action Buttons -->
        <div class="vc-actions">
            <?php if ($totalHours > 0 || $previewMode): ?>
            <button onclick="window.print()" class="vc-btn vc-btn-primary">
                <i class="fa-solid fa-print"></i>
                Print Certificate
            </button>
            <?php endif; ?>
            <?php if ($previewMode): ?>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/volunteering/certificate" class="vc-btn vc-btn-secondary">
                <i class="fa-solid fa-times"></i>
                Exit Preview
            </a>
            <?php else: ?>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/volunteering/certificate?preview=1" class="vc-btn vc-btn-secondary">
                <i class="fa-solid fa-eye"></i>
                Preview Design
            </a>
            <?php endif; ?>
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/volunteering/my-applications" class="vc-btn vc-btn-secondary">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Applications
            </a>
        </div>

        <?php if ($previewMode): ?>
        <div class="vc-preview-banner">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
            <div>
                <strong>Preview Mode</strong>
                <span>Showing sample data to demonstrate the certificate design</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Row -->
        <div class="vc-stats">
            <div class="vc-stat">
                <div class="vc-stat-value"><?= number_format($displayHours, 1) ?></div>
                <div class="vc-stat-label">Total Verified Hours</div>
            </div>
            <div class="vc-stat">
                <div class="vc-stat-value"><?= $displayActivities ?></div>
                <div class="vc-stat-label">Activities Completed</div>
            </div>
            <div class="vc-stat">
                <div class="vc-stat-value"><?= $displayOrgs ?></div>
                <div class="vc-stat-label">Organizations Helped</div>
            </div>
        </div>

        <?php if ($totalHours > 0 || $previewMode): ?>
        <!-- Certificate Preview -->
        <div class="vc-card">
            <div class="vc-card-header">
                <div class="vc-card-title">
                    <i class="fa-solid fa-scroll"></i>
                    Certificate Preview
                </div>
                <span class="vc-card-badge"><?= $previewMode ? 'Sample' : 'Official' ?></span>
            </div>

            <div class="vc-cert-wrapper">
                <div class="vc-cert">
                    <!-- Decorative elements -->
                    <div class="vc-cert-deco vc-cert-deco-1"></div>
                    <div class="vc-cert-deco vc-cert-deco-2"></div>

                    <!-- Logo -->
                    <div class="vc-cert-logo">
                        <?php if ($tenantLogo): ?>
                            <img src="<?= htmlspecialchars($tenantLogo) ?>" loading="lazy" alt="<?= htmlspecialchars($tenantName) ?>">
                        <?php else: ?>
                            <div class="vc-cert-logo-placeholder">
                                <i class="fa-solid fa-hands-helping"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Content -->
                    <div class="vc-cert-content">
                        <p class="vc-cert-title">Certificate</p>
                        <h2 class="vc-cert-main-title">Volunteer Service</h2>

                        <p class="vc-cert-presents">This is to certify that</p>

                        <h3 class="vc-cert-recipient"><?= htmlspecialchars($userName) ?></h3>
                        <div class="vc-cert-line"></div>

                        <p class="vc-cert-body">
                            has generously dedicated their time and effort to support the community,
                            contributing a verified total of
                        </p>

                        <div class="vc-cert-hours">
                            <span class="vc-cert-hours-value"><?= number_format($displayHours, 1) ?></span>
                            <span class="vc-cert-hours-label">Hours</span>
                        </div>

                        <p class="vc-cert-body">of voluntary service.</p>

                        <div class="vc-cert-signatures">
                            <div class="vc-cert-sig">
                                <div class="vc-cert-sig-line"></div>
                                <div class="vc-cert-sig-label">Date: <?= $date ?></div>
                            </div>
                            <div class="vc-cert-sig">
                                <div class="vc-cert-sig-value"><?= htmlspecialchars($tenantName) ?></div>
                                <div class="vc-cert-sig-line"></div>
                                <div class="vc-cert-sig-label">Authorized Signature</div>
                            </div>
                        </div>
                    </div>

                    <!-- Badge -->
                    <div class="vc-cert-badge">
                        <i class="fa-solid fa-award"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Print Tip -->
        <div class="vc-print-tip">
            <i class="fa-solid fa-lightbulb"></i>
            <div class="vc-print-tip-content">
                <div class="vc-print-tip-title">Print Tip</div>
                <div class="vc-print-tip-text">
                    For a clean certificate without date/URL text, click "More settings" in the print dialog and uncheck "Headers and footers". You can also save as PDF for a digital copy.
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- No Hours Message -->
        <div class="vc-card">
            <div class="vc-card-header">
                <div class="vc-card-title">
                    <i class="fa-solid fa-scroll"></i>
                    Certificate
                </div>
            </div>
            <div class="vc-empty">
                <div class="vc-empty-icon">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <h3 class="vc-empty-title">No Verified Hours Yet</h3>
                <p class="vc-empty-text">Start volunteering and log your hours to receive a certificate.</p>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/volunteering" class="vc-btn vc-btn-primary" style="display: inline-flex;">
                    <i class="fa-solid fa-search"></i>
                    Find Opportunities
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Volunteer History -->
        <?php if (!empty($displayLogs)): ?>
        <div class="vc-history">
            <div class="vc-history-header">
                <h3 class="vc-history-title">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <?= $previewMode ? 'Sample Activity Log' : 'Verified Activity Log' ?>
                </h3>
            </div>
            <div class="vc-history-body">
                <?php foreach ($displayLogs as $log): ?>
                    <div class="vc-history-item">
                        <div>
                            <div class="vc-history-org"><?= htmlspecialchars($log['org_name'] ?? 'Volunteer Activity') ?></div>
                            <div class="vc-history-date"><?= date('M j, Y', strtotime($log['date_logged'])) ?></div>
                        </div>
                        <div class="vc-history-hours"><?= $log['hours'] ?>h</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>

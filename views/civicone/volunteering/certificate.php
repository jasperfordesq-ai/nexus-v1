<?php
/**
 * Template E: Content Page - Volunteer Certificate
 * GOV.UK Design System (WCAG 2.1 AA)
 *
 * Purpose: Display volunteer certificate with print functionality
 * Features: Hour tracking, activity log, print styles
 */

$pageTitle = 'Volunteer Certificate';
\Nexus\Core\SEO::setTitle('Volunteer Certificate');
\Nexus\Core\SEO::setDescription('View and print your volunteer service certificate.');

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
$basePath = \Nexus\Core\TenantContext::getBasePath();

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<!-- Print Styles -->
<style>
@media print {
    header, nav, footer, .govuk-back-link, .govuk-button-group, .govuk-inset-text, .no-print {
        display: none !important;
    }
    .print-only {
        display: block !important;
    }
    body {
        background: white !important;
    }
    .certificate-print {
        border: 3px double #1d70b8 !important;
        padding: 40px !important;
        margin: 20px auto !important;
        max-width: 700px !important;
    }
}
@media screen {
    .print-only {
        display: none;
    }
}
</style>

<div class="govuk-width-container">
    <a href="<?= $basePath ?>/volunteering/my-applications" class="govuk-back-link no-print">Back to applications</a>

    <main class="govuk-main-wrapper">
        <!-- Header -->
        <div class="govuk-grid-row no-print">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">
                    <i class="fa-solid fa-award govuk-!-margin-right-2" style="color: #f47738;" aria-hidden="true"></i>
                    Volunteer Certificate
                </h1>
                <p class="govuk-body-l">Your verified volunteering record</p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="govuk-button-group govuk-!-margin-bottom-6 no-print">
            <?php if ($totalHours > 0 || $previewMode): ?>
                <button onclick="window.print()" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-print govuk-!-margin-right-2" aria-hidden="true"></i>
                    Print Certificate
                </button>
            <?php endif; ?>
            <?php if ($previewMode): ?>
                <a href="<?= $basePath ?>/volunteering/certificate" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-solid fa-times govuk-!-margin-right-2" aria-hidden="true"></i>
                    Exit Preview
                </a>
            <?php else: ?>
                <a href="<?= $basePath ?>/volunteering/certificate?preview=1" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    <i class="fa-solid fa-eye govuk-!-margin-right-2" aria-hidden="true"></i>
                    Preview Design
                </a>
            <?php endif; ?>
            <a href="<?= $basePath ?>/volunteering/my-applications" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                <i class="fa-solid fa-arrow-left govuk-!-margin-right-2" aria-hidden="true"></i>
                Back to Applications
            </a>
        </div>

        <?php if ($previewMode): ?>
            <div class="govuk-notification-banner govuk-notification-banner--success no-print" role="region" aria-labelledby="preview-banner-title" data-module="govuk-notification-banner">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="preview-banner-title">Preview Mode</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">
                        <i class="fa-solid fa-wand-magic-sparkles govuk-!-margin-right-2" aria-hidden="true"></i>
                        Showing sample data to demonstrate the certificate design
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Row -->
        <div class="govuk-grid-row govuk-!-margin-bottom-6 no-print">
            <div class="govuk-grid-column-one-third">
                <div class="govuk-!-padding-4 govuk-!-text-align-center" style="background: #f3f2f1; border-left: 5px solid #00703c;">
                    <p class="govuk-heading-xl govuk-!-margin-bottom-1" style="color: #00703c;"><?= number_format($displayHours, 1) ?></p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">Total Verified Hours</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-third">
                <div class="govuk-!-padding-4 govuk-!-text-align-center" style="background: #f3f2f1; border-left: 5px solid #1d70b8;">
                    <p class="govuk-heading-xl govuk-!-margin-bottom-1" style="color: #1d70b8;"><?= $displayActivities ?></p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">Activities Completed</p>
                </div>
            </div>
            <div class="govuk-grid-column-one-third">
                <div class="govuk-!-padding-4 govuk-!-text-align-center" style="background: #f3f2f1; border-left: 5px solid #f47738;">
                    <p class="govuk-heading-xl govuk-!-margin-bottom-1" style="color: #f47738;"><?= $displayOrgs ?></p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">Organizations Helped</p>
                </div>
            </div>
        </div>

        <?php if ($totalHours > 0 || $previewMode): ?>
            <!-- Certificate Preview -->
            <div class="govuk-!-margin-bottom-6" style="background: white; border: 1px solid #b1b4b6;">
                <div class="govuk-!-padding-3" style="background: #f3f2f1; border-bottom: 1px solid #b1b4b6;">
                    <div class="govuk-grid-row">
                        <div class="govuk-grid-column-two-thirds">
                            <h2 class="govuk-heading-s govuk-!-margin-bottom-0">
                                <i class="fa-solid fa-scroll govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                                Certificate Preview
                            </h2>
                        </div>
                        <div class="govuk-grid-column-one-third govuk-!-text-align-right">
                            <strong class="govuk-tag" style="background: <?= $previewMode ? '#f47738' : '#00703c' ?>;">
                                <?= $previewMode ? 'Sample' : 'Official' ?>
                            </strong>
                        </div>
                    </div>
                </div>

                <!-- Certificate Content -->
                <div class="govuk-!-padding-6 certificate-print" style="text-align: center; border: 2px solid #1d70b8; margin: 20px;">
                    <p class="govuk-body govuk-!-margin-bottom-2" style="color: #505a5f; text-transform: uppercase; letter-spacing: 3px;">Certificate</p>
                    <h2 class="govuk-heading-l govuk-!-margin-bottom-4" style="color: #1d70b8;">Volunteer Service</h2>

                    <p class="govuk-body govuk-!-margin-bottom-2">This is to certify that</p>

                    <p class="govuk-heading-m govuk-!-margin-bottom-4" style="border-bottom: 2px solid #1d70b8; display: inline-block; padding: 0 40px 8px;">
                        <?= htmlspecialchars($userName) ?>
                    </p>

                    <p class="govuk-body govuk-!-margin-bottom-4">
                        has generously dedicated their time and effort to support the community,<br>
                        contributing a verified total of
                    </p>

                    <div class="govuk-!-margin-bottom-4">
                        <span class="govuk-heading-xl" style="color: #00703c; display: inline;"><?= number_format($displayHours, 1) ?></span>
                        <span class="govuk-heading-m" style="color: #00703c; display: inline;">Hours</span>
                    </div>

                    <p class="govuk-body govuk-!-margin-bottom-6">of voluntary service.</p>

                    <div class="govuk-grid-row govuk-!-margin-top-8">
                        <div class="govuk-grid-column-one-half">
                            <div style="border-top: 1px solid #b1b4b6; padding-top: 8px;">
                                <p class="govuk-body-s govuk-!-margin-bottom-0">Date: <?= $date ?></p>
                            </div>
                        </div>
                        <div class="govuk-grid-column-one-half">
                            <div style="border-top: 1px solid #b1b4b6; padding-top: 8px;">
                                <p class="govuk-body-s govuk-!-font-weight-bold govuk-!-margin-bottom-0"><?= htmlspecialchars($tenantName) ?></p>
                                <p class="govuk-body-s govuk-!-margin-bottom-0">Authorized Signature</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Print Tip -->
            <div class="govuk-inset-text no-print">
                <p class="govuk-body govuk-!-margin-bottom-0">
                    <i class="fa-solid fa-lightbulb govuk-!-margin-right-2" style="color: #f47738;" aria-hidden="true"></i>
                    <strong>Print Tip:</strong> For a clean certificate, click "More settings" in the print dialog and uncheck "Headers and footers". You can also save as PDF.
                </p>
            </div>

        <?php else: ?>
            <!-- No Hours Message -->
            <div class="govuk-!-padding-6 govuk-!-text-align-center" style="background: #f3f2f1; border-left: 5px solid #1d70b8;">
                <p class="govuk-body govuk-!-margin-bottom-4">
                    <i class="fa-solid fa-hourglass-half fa-3x" style="color: #1d70b8;" aria-hidden="true"></i>
                </p>
                <h2 class="govuk-heading-l">No Verified Hours Yet</h2>
                <p class="govuk-body govuk-!-margin-bottom-6">
                    Start volunteering and log your hours to receive a certificate.
                </p>
                <a href="<?= $basePath ?>/volunteering" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-search govuk-!-margin-right-2" aria-hidden="true"></i>
                    Find Opportunities
                </a>
            </div>
        <?php endif; ?>

        <!-- Volunteer History -->
        <?php if (!empty($displayLogs)): ?>
            <div class="govuk-!-margin-top-6 no-print">
                <h3 class="govuk-heading-m">
                    <i class="fa-solid fa-clock-rotate-left govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                    <?= $previewMode ? 'Sample Activity Log' : 'Verified Activity Log' ?>
                </h3>

                <table class="govuk-table">
                    <thead class="govuk-table__head">
                        <tr class="govuk-table__row">
                            <th scope="col" class="govuk-table__header">Organization</th>
                            <th scope="col" class="govuk-table__header">Date</th>
                            <th scope="col" class="govuk-table__header govuk-table__header--numeric">Hours</th>
                        </tr>
                    </thead>
                    <tbody class="govuk-table__body">
                        <?php foreach ($displayLogs as $log): ?>
                            <tr class="govuk-table__row">
                                <td class="govuk-table__cell"><?= htmlspecialchars($log['org_name'] ?? 'Volunteer Activity') ?></td>
                                <td class="govuk-table__cell"><?= date('M j, Y', strtotime($log['date_logged'])) ?></td>
                                <td class="govuk-table__cell govuk-table__cell--numeric">
                                    <strong class="govuk-tag" style="background: #00703c;"><?= $log['hours'] ?>h</strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Print-only certificate -->
<div class="print-only certificate-print">
    <p style="color: #505a5f; text-transform: uppercase; letter-spacing: 3px; text-align: center;">Certificate</p>
    <h1 style="color: #1d70b8; text-align: center; margin-bottom: 30px;">Volunteer Service</h1>

    <p style="text-align: center;">This is to certify that</p>

    <p style="text-align: center; font-size: 24px; font-weight: bold; border-bottom: 2px solid #1d70b8; display: inline-block; width: 100%; padding-bottom: 10px; margin: 20px 0;">
        <?= htmlspecialchars($userName) ?>
    </p>

    <p style="text-align: center; margin: 20px 0;">
        has generously dedicated their time and effort to support the community,
        contributing a verified total of
    </p>

    <p style="text-align: center; font-size: 36px; font-weight: bold; color: #00703c; margin: 30px 0;">
        <?= number_format($displayHours, 1) ?> Hours
    </p>

    <p style="text-align: center;">of voluntary service.</p>

    <div style="display: flex; justify-content: space-between; margin-top: 60px; padding-top: 20px;">
        <div style="text-align: center; width: 45%;">
            <div style="border-top: 1px solid #000; padding-top: 10px;">
                Date: <?= $date ?>
            </div>
        </div>
        <div style="text-align: center; width: 45%;">
            <div style="border-top: 1px solid #000; padding-top: 10px;">
                <strong><?= htmlspecialchars($tenantName) ?></strong><br>
                Authorized Signature
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

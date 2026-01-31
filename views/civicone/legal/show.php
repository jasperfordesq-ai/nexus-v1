<?php
/**
 * Public Legal Document View - CivicOne Theme
 * GOV.UK Design System (WCAG 2.1 AA Compliant)
 *
 * Displays legal documents from database with version information and acceptance tracking.
 * This is the CivicOne equivalent of views/legal/show.php
 *
 * Expected variables from controller:
 * - $document: array with title, content, version_number, effective_date, slug, requires_acceptance, etc.
 * - $documentType: string (terms, privacy, cookies, accessibility)
 * - $acceptanceStatus: string|null (current, pending, null)
 *
 * @see src/Controllers/LegalDocumentController.php
 * @see https://github.com/alphagov/govuk-frontend
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Auth;

$basePath = TenantContext::getBasePath();
$tenantName = TenantContext::get()['name'] ?? 'This Community';

// Icons for document types
$icons = [
    'terms' => 'fa-file-contract',
    'privacy' => 'fa-shield-halved',
    'cookies' => 'fa-cookie-bite',
    'accessibility' => 'fa-universal-access',
];
$icon = $icons[$documentType] ?? 'fa-file-lines';

// Page title
$pageTitle = $document['title'] ?? 'Legal Document';

require __DIR__ . '/../../layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
?>

<div class="govuk-width-container">

    <?= civicone_govuk_breadcrumbs([
        'items' => [
            ['text' => 'Home', 'href' => $basePath],
            ['text' => 'Legal', 'href' => $basePath . '/legal'],
            ['text' => htmlspecialchars($document['title'])]
        ],
        'class' => 'govuk-!-margin-bottom-6'
    ]) ?>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <!-- Page Header -->
                <h1 class="govuk-heading-xl govuk-!-margin-bottom-4">
                    <i class="fa-solid <?= $icon ?> govuk-!-margin-right-2" aria-hidden="true"></i>
                    <?= htmlspecialchars($document['title']) ?>
                </h1>

                <p class="govuk-body-l govuk-!-margin-bottom-4">
                    <?= htmlspecialchars($tenantName) ?> <?= htmlspecialchars($document['title']) ?>
                </p>

                <!-- Version Information -->
                <div class="govuk-!-margin-bottom-6">
                    <p class="govuk-body-s govuk-!-margin-bottom-2">
                        <strong class="govuk-tag govuk-tag--blue">
                            Version <?= htmlspecialchars($document['version_number']) ?>
                        </strong>
                    </p>
                    <p class="govuk-body-s govuk-!-margin-bottom-2">
                        <i class="fa-solid fa-calendar govuk-!-margin-right-1" aria-hidden="true"></i>
                        <strong>Effective:</strong> <?= date('j F Y', strtotime($document['effective_date'])) ?>
                    </p>
                    <p class="govuk-body-s">
                        <a href="<?= $basePath ?>/<?= htmlspecialchars($document['slug']) ?>/versions" class="govuk-link">
                            <i class="fa-solid fa-history govuk-!-margin-right-1" aria-hidden="true"></i>
                            View version history
                        </a>
                    </p>
                </div>

                <?php if (Auth::check() && !empty($document['requires_acceptance'])): ?>
                <!-- Acceptance Status Banner -->
                <?php if ($acceptanceStatus === 'current'): ?>
                <div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-6" role="alert" aria-labelledby="govuk-notification-banner-title">
                    <div class="govuk-notification-banner__header">
                        <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">
                            Accepted
                        </h2>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">
                            <i class="fa-solid fa-check-circle govuk-!-margin-right-1" aria-hidden="true"></i>
                            You have accepted this version of <?= htmlspecialchars($document['title']) ?>
                        </p>
                    </div>
                </div>
                <?php else: ?>
                <div class="govuk-warning-text govuk-!-margin-bottom-6">
                    <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                    <strong class="govuk-warning-text__text">
                        <span class="govuk-visually-hidden">Warning</span>
                        Please review and accept the updated <?= htmlspecialchars($document['title']) ?>
                    </strong>
                </div>
                <?php endif; ?>
                <?php endif; ?>

            </div>

            <div class="govuk-grid-column-one-third">
                <!-- Sidebar Navigation -->
                <aside class="govuk-!-padding-4 civicone-panel-bg" role="complementary">
                    <h2 class="govuk-heading-s govuk-!-margin-bottom-3">Related documents</h2>
                    <ul class="govuk-list govuk-!-font-size-16">
                        <?php if ($documentType !== 'terms'): ?>
                        <li><a href="<?= $basePath ?>/terms" class="govuk-link">Terms of Service</a></li>
                        <?php endif; ?>
                        <?php if ($documentType !== 'privacy'): ?>
                        <li><a href="<?= $basePath ?>/privacy" class="govuk-link">Privacy Policy</a></li>
                        <?php endif; ?>
                        <?php if ($documentType !== 'cookies'): ?>
                        <li><a href="<?= $basePath ?>/privacy#cookies" class="govuk-link">Cookie Policy</a></li>
                        <?php endif; ?>
                        <?php if ($documentType !== 'accessibility'): ?>
                        <li><a href="<?= $basePath ?>/accessibility" class="govuk-link">Accessibility Statement</a></li>
                        <?php endif; ?>
                        <li><a href="<?= $basePath ?>/legal" class="govuk-link">Legal Hub</a></li>
                    </ul>
                </aside>
            </div>
        </div>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

                <!-- Document Content from Database -->
                <div class="civicone-legal-content">
                    <?= $document['content'] ?>
                </div>

                <hr class="govuk-section-break govuk-section-break--xl govuk-section-break--visible">

                <?php if (Auth::check() && !empty($document['requires_acceptance']) && $acceptanceStatus !== 'current'): ?>
                <!-- Acceptance Form -->
                <div class="govuk-!-padding-6 govuk-!-margin-bottom-6 civicone-legal-accept-form">
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-3">
                        <i class="fa-solid fa-check-to-slot govuk-!-margin-right-2" aria-hidden="true"></i>
                        Accept this document
                    </h2>
                    <p class="govuk-body govuk-!-margin-bottom-4">
                        By clicking the button below, you confirm that you have read and agree to the <?= htmlspecialchars($document['title']) ?>.
                    </p>
                    <button type="button"
                            class="govuk-button"
                            data-module="govuk-button"
                            id="accept-document-btn"
                            onclick="acceptDocument(<?= (int)$document['id'] ?>, <?= (int)$document['current_version_id'] ?>)">
                        <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i>
                        I accept the <?= htmlspecialchars($document['title']) ?>
                    </button>
                </div>
                <?php endif; ?>

                <!-- Back Link -->
                <p class="govuk-body govuk-!-margin-bottom-6">
                    <a href="<?= $basePath ?>/legal" class="govuk-link govuk-link--no-visited-state">
                        <i class="fa-solid fa-arrow-left govuk-!-margin-right-1" aria-hidden="true"></i>
                        Back to Legal Hub
                    </a>
                </p>

                <!-- Contact Section -->
                <div class="govuk-inset-text">
                    <h2 class="govuk-heading-s govuk-!-margin-bottom-2">Questions about this document?</h2>
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        If you have any questions, please <a href="<?= $basePath ?>/contact" class="govuk-link">contact us</a>.
                    </p>
                </div>

            </div>
        </div>

    </main>
</div>

<?php if (Auth::check()): ?>
<script>
function acceptDocument(documentId, versionId) {
    const btn = document.getElementById('accept-document-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin govuk-!-margin-right-1" aria-hidden="true"></i> Processing...';
    }

    fetch('<?= $basePath ?>/api/legal/accept', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            document_id: documentId,
            version_id: versionId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Replace the acceptance form with success message
            const formContainer = btn.closest('div[style]');
            if (formContainer) {
                formContainer.outerHTML = `
                    <div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-bottom-6" role="alert" aria-labelledby="success-banner-title">
                        <div class="govuk-notification-banner__header">
                            <h2 class="govuk-notification-banner__title" id="success-banner-title">Success</h2>
                        </div>
                        <div class="govuk-notification-banner__content">
                            <p class="govuk-notification-banner__heading">
                                <i class="fa-solid fa-check-circle govuk-!-margin-right-1" aria-hidden="true"></i>
                                Thank you! You have accepted this document.
                            </p>
                        </div>
                    </div>
                `;
            }
        } else {
            alert('Failed to record acceptance. Please try again.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i> I accept';
            }
        }
    })
    .catch(err => {
        console.error('Acceptance error:', err);
        alert('An error occurred. Please try again.');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i> I accept';
        }
    });
}
</script>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

<?php
/**
 * Public Archived Legal Document Version View - CivicOne Theme
 * GOV.UK Design System (WCAG 2.1 AA Compliant)
 *
 * Displays a specific archived version of a legal document.
 * This is the CivicOne equivalent of views/legal/show-version.php
 *
 * Expected variables from controller:
 * - $version: array with version_number, effective_date, content, document_id, etc.
 * - $isArchived: boolean indicating if this is not the current version
 *
 * @see src/Controllers/LegalDocumentController.php
 * @see https://github.com/alphagov/govuk-frontend
 */

use Nexus\Core\TenantContext;
use Nexus\Services\LegalDocumentService;

$basePath = TenantContext::getBasePath();

// Get document info
$document = LegalDocumentService::getById($version['document_id']);
$documentType = $document['document_type'] ?? 'terms';

// Icons for document types
$icons = [
    'terms' => 'fa-file-contract',
    'privacy' => 'fa-shield-halved',
    'cookies' => 'fa-cookie-bite',
    'accessibility' => 'fa-universal-access',
];
$icon = $icons[$documentType] ?? 'fa-file-lines';

// Page title
$pageTitle = $document['title'] . ' - Version ' . $version['version_number'];

require __DIR__ . '/../../layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
?>

<div class="govuk-width-container">

    <?= civicone_govuk_breadcrumbs([
        'items' => [
            ['text' => 'Home', 'href' => $basePath],
            ['text' => 'Legal', 'href' => $basePath . '/legal'],
            ['text' => htmlspecialchars($document['title']), 'href' => $basePath . '/' . htmlspecialchars($document['slug'])],
            ['text' => 'Version ' . htmlspecialchars($version['version_number'])]
        ],
        'class' => 'govuk-!-margin-bottom-6'
    ]) ?>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <?php if ($isArchived): ?>
        <!-- Archived Version Warning Banner -->
        <div class="govuk-notification-banner govuk-notification-banner--important govuk-!-margin-bottom-6" role="region" aria-labelledby="archived-banner-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="archived-banner-title">
                    <i class="fa-solid fa-archive govuk-!-margin-right-1" aria-hidden="true"></i>
                    Archived Version
                </h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">
                    You are viewing version <?= htmlspecialchars($version['version_number']) ?>, which is no longer current.
                </p>
                <p class="govuk-body">
                    <a class="govuk-notification-banner__link" href="<?= $basePath ?>/<?= htmlspecialchars($document['slug']) ?>">
                        View the current version
                        <i class="fa-solid fa-arrow-right govuk-!-margin-left-1" aria-hidden="true"></i>
                    </a>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <!-- Page Header -->
                <h1 class="govuk-heading-xl govuk-!-margin-bottom-4">
                    <i class="fa-solid <?= $icon ?> govuk-!-margin-right-2" aria-hidden="true"></i>
                    <?= htmlspecialchars($document['title']) ?>
                </h1>

                <!-- Version Information -->
                <div class="govuk-!-margin-bottom-6">
                    <p class="govuk-body-s govuk-!-margin-bottom-2">
                        <strong class="govuk-tag govuk-tag--yellow">
                            <i class="fa-solid fa-archive govuk-!-margin-right-1" aria-hidden="true"></i>
                            Archived
                        </strong>
                        <strong class="govuk-tag govuk-tag--grey govuk-!-margin-left-1">
                            Version <?= htmlspecialchars($version['version_number']) ?>
                        </strong>
                    </p>
                    <p class="govuk-body-s govuk-!-margin-bottom-2">
                        <i class="fa-solid fa-calendar govuk-!-margin-right-1" aria-hidden="true"></i>
                        <strong>Effective:</strong> <?= date('j F Y', strtotime($version['effective_date'])) ?>
                    </p>
                    <p class="govuk-body-s">
                        <a href="<?= $basePath ?>/<?= htmlspecialchars($document['slug']) ?>/versions" class="govuk-link">
                            <i class="fa-solid fa-history govuk-!-margin-right-1" aria-hidden="true"></i>
                            View version history
                        </a>
                    </p>
                </div>

            </div>

            <div class="govuk-grid-column-one-third">
                <!-- Sidebar Navigation -->
                <aside class="govuk-!-padding-4 civicone-panel-bg" role="complementary">
                    <h2 class="govuk-heading-s govuk-!-margin-bottom-3">Navigation</h2>
                    <ul class="govuk-list govuk-!-font-size-16">
                        <li>
                            <a href="<?= $basePath ?>/<?= htmlspecialchars($document['slug']) ?>" class="govuk-link">
                                <i class="fa-solid fa-arrow-right govuk-!-margin-right-1" aria-hidden="true"></i>
                                Current version
                            </a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/<?= htmlspecialchars($document['slug']) ?>/versions" class="govuk-link">
                                <i class="fa-solid fa-history govuk-!-margin-right-1" aria-hidden="true"></i>
                                Version history
                            </a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/legal" class="govuk-link">
                                <i class="fa-solid fa-scale-balanced govuk-!-margin-right-1" aria-hidden="true"></i>
                                Legal Hub
                            </a>
                        </li>
                    </ul>
                </aside>
            </div>
        </div>

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

                <!-- Document Content -->
                <div class="civicone-legal-content civicone-legal-content--archived">
                    <?= $version['content'] ?>
                </div>

                <hr class="govuk-section-break govuk-section-break--xl govuk-section-break--visible">

                <!-- Back Links -->
                <p class="govuk-body govuk-!-margin-bottom-3">
                    <a href="<?= $basePath ?>/<?= htmlspecialchars($document['slug']) ?>/versions" class="govuk-link govuk-link--no-visited-state">
                        <i class="fa-solid fa-arrow-left govuk-!-margin-right-1" aria-hidden="true"></i>
                        Back to version history
                    </a>
                </p>

                <p class="govuk-body">
                    <a href="<?= $basePath ?>/<?= htmlspecialchars($document['slug']) ?>" class="govuk-button" data-module="govuk-button">
                        <i class="fa-solid fa-eye govuk-!-margin-right-1" aria-hidden="true"></i>
                        View current version
                    </a>
                </p>

            </div>
        </div>

    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

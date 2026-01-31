<?php
/**
 * Public Legal Document Version History - CivicOne Theme
 * GOV.UK Design System (WCAG 2.1 AA Compliant)
 *
 * Shows all published versions of a legal document.
 * This is the CivicOne equivalent of views/legal/version-history.php
 *
 * Expected variables from controller:
 * - $document: array with title, slug, document_type, current_version_id
 * - $versions: array of version records
 *
 * @see src/Controllers/LegalDocumentController.php
 * @see https://github.com/alphagov/govuk-frontend
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();

// Icons for document types
$icons = [
    'terms' => 'fa-file-contract',
    'privacy' => 'fa-shield-halved',
    'cookies' => 'fa-cookie-bite',
    'accessibility' => 'fa-universal-access',
];
$icon = $icons[$document['document_type']] ?? 'fa-file-lines';

// Page title
$pageTitle = 'Version History - ' . ($document['title'] ?? 'Legal Document');

require __DIR__ . '/../../layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
?>

<div class="govuk-width-container">

    <?= civicone_govuk_breadcrumbs([
        'items' => [
            ['text' => 'Home', 'href' => $basePath],
            ['text' => 'Legal', 'href' => $basePath . '/legal'],
            ['text' => htmlspecialchars($document['title']), 'href' => $basePath . '/' . htmlspecialchars($document['slug'])],
            ['text' => 'Version History']
        ],
        'class' => 'govuk-!-margin-bottom-6'
    ]) ?>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <!-- Page Header -->
                <h1 class="govuk-heading-xl govuk-!-margin-bottom-4">
                    <i class="fa-solid fa-history govuk-!-margin-right-2" aria-hidden="true"></i>
                    Version History
                </h1>

                <p class="govuk-body-l govuk-!-margin-bottom-6">
                    <i class="fa-solid <?= $icon ?> govuk-!-margin-right-1" aria-hidden="true"></i>
                    <?= htmlspecialchars($document['title']) ?>
                </p>

                <?php if (!empty($versions)): ?>
                <!-- Version Timeline -->
                <ol class="govuk-list civicone-version-timeline">
                    <?php foreach ($versions as $version): ?>
                    <?php $isCurrent = ($version['id'] === $document['current_version_id']); ?>
                    <li class="civicone-version-item <?= $isCurrent ? 'civicone-version-item--current' : '' ?>">

                        <div class="govuk-!-margin-bottom-2">
                            <strong class="govuk-tag <?= $isCurrent ? 'govuk-tag--green' : 'govuk-tag--grey' ?>">
                                Version <?= htmlspecialchars($version['version_number']) ?>
                            </strong>
                            <?php if ($isCurrent): ?>
                            <strong class="govuk-tag govuk-tag--blue govuk-!-margin-left-1">
                                <i class="fa-solid fa-check-circle govuk-!-margin-right-1" aria-hidden="true"></i>
                                Current
                            </strong>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($version['version_label'])): ?>
                        <p class="govuk-body-s govuk-!-margin-bottom-2">
                            <strong><?= htmlspecialchars($version['version_label']) ?></strong>
                        </p>
                        <?php endif; ?>

                        <p class="govuk-body-s govuk-!-margin-bottom-2">
                            <i class="fa-solid fa-calendar govuk-!-margin-right-1" aria-hidden="true"></i>
                            <strong>Effective:</strong> <?= date('j F Y', strtotime($version['effective_date'])) ?>
                            <?php if (!$version['is_draft'] && !empty($version['published_at'])): ?>
                            <span class="govuk-!-margin-left-3">
                                <i class="fa-solid fa-rocket govuk-!-margin-right-1" aria-hidden="true"></i>
                                <strong>Published:</strong> <?= date('j F Y', strtotime($version['published_at'])) ?>
                            </span>
                            <?php endif; ?>
                        </p>

                        <?php if (!empty($version['summary_of_changes'])): ?>
                        <div class="govuk-inset-text govuk-!-margin-top-2 govuk-!-margin-bottom-3">
                            <strong>Changes:</strong> <?= htmlspecialchars($version['summary_of_changes']) ?>
                        </div>
                        <?php endif; ?>

                        <p class="govuk-body govuk-!-margin-bottom-0">
                            <?php if ($isCurrent): ?>
                            <a href="<?= $basePath ?>/<?= htmlspecialchars($document['slug']) ?>" class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">
                                <i class="fa-solid fa-eye govuk-!-margin-right-1" aria-hidden="true"></i>
                                View current version
                            </a>
                            <?php else: ?>
                            <a href="<?= $basePath ?>/legal/version/<?= (int)$version['id'] ?>" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">
                                <i class="fa-solid fa-archive govuk-!-margin-right-1" aria-hidden="true"></i>
                                View archived version
                            </a>
                            <?php endif; ?>
                        </p>

                    </li>
                    <?php endforeach; ?>
                </ol>
                <?php else: ?>
                <!-- Empty State -->
                <div class="govuk-inset-text">
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <i class="fa-solid fa-clock-rotate-left govuk-!-margin-right-2" aria-hidden="true"></i>
                        There are no published versions of this document yet.
                    </p>
                </div>
                <?php endif; ?>

                <!-- Back Link -->
                <p class="govuk-body govuk-!-margin-top-6">
                    <a href="<?= $basePath ?>/<?= htmlspecialchars($document['slug']) ?>" class="govuk-link govuk-link--no-visited-state">
                        <i class="fa-solid fa-arrow-left govuk-!-margin-right-1" aria-hidden="true"></i>
                        Back to <?= htmlspecialchars($document['title']) ?>
                    </a>
                </p>

            </div>

            <div class="govuk-grid-column-one-third">
                <!-- Sidebar -->
                <aside class="govuk-!-padding-4 civicone-panel-bg" role="complementary">
                    <h2 class="govuk-heading-s govuk-!-margin-bottom-3">About version history</h2>
                    <p class="govuk-body-s govuk-!-margin-bottom-3">
                        We maintain a complete history of changes to our legal documents for transparency.
                    </p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">
                        <a href="<?= $basePath ?>/legal" class="govuk-link">View all legal documents</a>
                    </p>
                </aside>
            </div>
        </div>

    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

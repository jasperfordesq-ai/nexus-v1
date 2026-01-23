<?php
/**
 * Help Center Index - GOV.UK Design System
 * Template E: Content/Article
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = 'Help Centre';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="govuk-width-container">

    <!-- Breadcrumbs -->
    <nav class="govuk-breadcrumbs" aria-label="Breadcrumb">
        <ol class="govuk-breadcrumbs__list">
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
            </li>
            <li class="govuk-breadcrumbs__list-item" aria-current="page">
                Help Centre
            </li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h1 class="govuk-heading-xl">Help Centre</h1>

                <p class="govuk-body-l">
                    Guides and documentation to help you get the most from the platform.
                </p>

            </div>
        </div>

        <?php if (empty($groupedArticles)): ?>

            <div class="govuk-grid-row">
                <div class="govuk-grid-column-two-thirds">
                    <p class="govuk-body">No help articles found.</p>
                    <p class="govuk-body">
                        <a href="<?= $basePath ?>/contact" class="govuk-link">Contact us</a> if you need assistance.
                    </p>
                </div>
            </div>

        <?php else: ?>

            <?php foreach ($groupedArticles as $module => $articles): ?>

                <section class="govuk-!-margin-bottom-8" aria-labelledby="section-<?= htmlspecialchars($module) ?>">

                    <h2 class="govuk-heading-l" id="section-<?= htmlspecialchars($module) ?>">
                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $module))) ?>
                    </h2>

                    <div class="govuk-grid-row">
                        <?php foreach ($articles as $article): ?>
                            <div class="govuk-grid-column-one-half govuk-!-margin-bottom-6">
                                <div class="civicone-card-bordered">
                                    <h3 class="govuk-heading-s govuk-!-margin-bottom-2">
                                        <a href="<?= $basePath ?>/help/<?= htmlspecialchars($article['slug']) ?>" class="govuk-link">
                                            <?= htmlspecialchars($article['title']) ?>
                                        </a>
                                    </h3>
                                    <?php if (!empty($article['excerpt'])): ?>
                                        <p class="govuk-body-s govuk-!-margin-bottom-0">
                                            <?= htmlspecialchars($article['excerpt']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </section>

            <?php endforeach; ?>

        <?php endif; ?>

        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h2 class="govuk-heading-m">Can't find what you're looking for?</h2>

                <p class="govuk-body">
                    If you can't find the answer to your question, please get in touch.
                </p>

                <p class="govuk-body">
                    <a href="<?= $basePath ?>/contact" class="govuk-button" data-module="govuk-button">
                        Contact us
                    </a>
                </p>

            </div>
        </div>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>

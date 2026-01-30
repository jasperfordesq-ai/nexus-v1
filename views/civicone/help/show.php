<?php
/**
 * Help Article Detail - GOV.UK Design System
 * Template E: Content/Article
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = $article['title'] ?? 'Help Article';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
?>

<div class="govuk-width-container">

    <?= civicone_govuk_breadcrumbs([
        'items' => [
            ['text' => 'Home', 'href' => $basePath],
            ['text' => 'Help Centre', 'href' => $basePath . '/help'],
            ['text' => htmlspecialchars(mb_substr($article['title'] ?? 'Article', 0, 30)) . '...']
        ],
        'class' => 'govuk-!-margin-bottom-6'
    ]) ?>

    <main class="govuk-main-wrapper" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <article>
                    <?php if (!empty($article['module_tag'])): ?>
                        <p class="govuk-caption-xl">
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $article['module_tag']))) ?>
                        </p>
                    <?php endif; ?>

                    <h1 class="govuk-heading-xl"><?= htmlspecialchars($article['title']) ?></h1>

                    <div class="govuk-body">
                        <?= $article['content'] ?>
                        <!-- Content is admin-authored trusted HTML -->
                    </div>

                    <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

                    <p class="govuk-body">
                        <a href="<?= $basePath ?>/help" class="govuk-link">
                            <span aria-hidden="true">&larr;</span> Back to Help Centre
                        </a>
                    </p>

                </article>

            </div>

            <!-- Sidebar -->
            <div class="govuk-grid-column-one-third">
                <aside class="govuk-!-margin-top-6" role="complementary">

                    <h2 class="govuk-heading-s">Need more help?</h2>

                    <p class="govuk-body">
                        If this article didn't answer your question, please get in touch.
                    </p>

                    <ul class="govuk-list">
                        <li>
                            <a href="<?= $basePath ?>/help/search" class="govuk-link">Search help articles</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/contact" class="govuk-link">Contact us</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/faq" class="govuk-link">Frequently asked questions</a>
                        </li>
                    </ul>

                </aside>
            </div>
        </div>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>

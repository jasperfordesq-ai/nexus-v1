<?php
/**
 * Blog/News Article Detail - GOV.UK Design System
 * Template E: Content/Article
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = $post['title'] ?? 'News Article';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="govuk-width-container">

    <!-- Breadcrumbs -->
    <nav class="govuk-breadcrumbs" aria-label="Breadcrumb">
        <ol class="govuk-breadcrumbs__list">
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
            </li>
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/blog">News</a>
            </li>
            <li class="govuk-breadcrumbs__list-item" aria-current="page">
                <?= htmlspecialchars(mb_substr($post['title'] ?? 'Article', 0, 30)) ?>...
            </li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <article>
                    <h1 class="govuk-heading-xl"><?= htmlspecialchars($post['title']) ?></h1>

                    <p class="govuk-body-s civicone-text-secondary govuk-!-margin-bottom-6">
                        <?php if (!empty($post['published_at'])): ?>
                            Published <?= date('j F Y', strtotime($post['published_at'])) ?>
                        <?php elseif (!empty($post['created_at'])): ?>
                            Published <?= date('j F Y', strtotime($post['created_at'])) ?>
                        <?php endif; ?>

                        <?php if (!empty($post['author_name'])): ?>
                            by <?= htmlspecialchars($post['author_name']) ?>
                        <?php endif; ?>
                    </p>

                    <?php if (!empty($post['featured_image'])): ?>
                        <figure class="govuk-!-margin-bottom-6">
                            <img src="<?= htmlspecialchars($post['featured_image']) ?>"
                                 alt="<?= htmlspecialchars($post['featured_image_alt'] ?? 'Featured image for ' . $post['title']) ?>"
                                 class="govuk-!-width-full">
                            <?php if (!empty($post['featured_image_caption'])): ?>
                                <figcaption class="govuk-body-s civicone-text-secondary govuk-!-margin-top-2">
                                    <?= htmlspecialchars($post['featured_image_caption']) ?>
                                </figcaption>
                            <?php endif; ?>
                        </figure>
                    <?php endif; ?>

                    <div class="govuk-body">
                        <?= $post['content'] ?>
                    </div>

                    <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

                    <!-- Share and Navigation -->
                    <div class="govuk-grid-row">
                        <div class="govuk-grid-column-one-half">
                            <p class="govuk-body">
                                <a href="<?= $basePath ?>/blog" class="govuk-link">
                                    <span aria-hidden="true">←</span> Back to news
                                </a>
                            </p>
                        </div>
                        <div class="govuk-grid-column-one-half govuk-!-text-align-right">
                            <p class="govuk-body">
                                <a href="mailto:?subject=<?= urlencode($post['title']) ?>&body=<?= urlencode($post['title'] . ' - Read more at: ' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '')) ?>"
                                   class="govuk-link"
                                   aria-label="Share this article via email">
                                    Share via email
                                </a>
                            </p>
                        </div>
                    </div>

                </article>

            </div>

            <!-- Sidebar -->
            <div class="govuk-grid-column-one-third">
                <aside class="govuk-!-margin-top-6" role="complementary">
                    <h2 class="govuk-heading-s">Related links</h2>
                    <ul class="govuk-list">
                        <li>
                            <a href="<?= $basePath ?>/blog" class="govuk-link">All news articles</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/feed" class="govuk-link">Community feed</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/events" class="govuk-link">Upcoming events</a>
                        </li>
                    </ul>
                </aside>
            </div>
        </div>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>

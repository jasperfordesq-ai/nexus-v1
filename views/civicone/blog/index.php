<?php
/**
 * Blog/News Index - GOV.UK Design System
 * Template E: Content/Article (List variant)
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = 'News and updates';

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
                News
            </li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper" id="main-content" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <h1 class="govuk-heading-xl">News and updates</h1>

                <p class="govuk-body-l">
                    Stay informed with the latest updates, stories, and announcements from our community.
                </p>

            </div>
        </div>

        <?php if (empty($posts)): ?>

            <!-- Empty State -->
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-two-thirds">
                    <div class="govuk-inset-text">
                        <p class="govuk-body">No news articles have been published yet.</p>
                        <p class="govuk-body">Check back soon for the latest updates and announcements.</p>
                    </div>
                </div>
            </div>

        <?php else: ?>

            <!-- News List -->
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-two-thirds">

                    <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

                    <ul class="govuk-list" id="news-list" role="list">
                        <?php foreach ($posts as $post): ?>
                            <li class="govuk-!-margin-bottom-6">
                                <article>
                                    <h2 class="govuk-heading-m govuk-!-margin-bottom-2">
                                        <a href="<?= $basePath ?>/blog/<?= htmlspecialchars($post['slug'] ?? $post['id']) ?>" class="govuk-link">
                                            <?= htmlspecialchars($post['title']) ?>
                                        </a>
                                    </h2>

                                    <p class="govuk-body-s govuk-!-margin-bottom-2 civicone-text-secondary">
                                        <?php if (!empty($post['published_at'])): ?>
                                            <?= date('j F Y', strtotime($post['published_at'])) ?>
                                        <?php elseif (!empty($post['created_at'])): ?>
                                            <?= date('j F Y', strtotime($post['created_at'])) ?>
                                        <?php endif; ?>

                                        <?php if (!empty($post['author_name'])): ?>
                                            — <?= htmlspecialchars($post['author_name']) ?>
                                        <?php endif; ?>
                                    </p>

                                    <?php if (!empty($post['excerpt'])): ?>
                                        <p class="govuk-body">
                                            <?= htmlspecialchars($post['excerpt']) ?>
                                        </p>
                                    <?php elseif (!empty($post['content'])): ?>
                                        <p class="govuk-body">
                                            <?= htmlspecialchars(substr(strip_tags($post['content']), 0, 200)) ?>...
                                        </p>
                                    <?php endif; ?>
                                </article>

                                <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Pagination placeholder -->
                    <?php if (!empty($pagination) && $pagination['total_pages'] > 1): ?>
                        <nav class="govuk-pagination" role="navigation" aria-label="Pagination">
                            <?php if ($pagination['current_page'] > 1): ?>
                                <div class="govuk-pagination__prev">
                                    <a class="govuk-link govuk-pagination__link" href="<?= $basePath ?>/blog?page=<?= $pagination['current_page'] - 1 ?>" rel="prev">
                                        <span class="govuk-pagination__link-title">Previous</span>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                                <div class="govuk-pagination__next">
                                    <a class="govuk-link govuk-pagination__link" href="<?= $basePath ?>/blog?page=<?= $pagination['current_page'] + 1 ?>" rel="next">
                                        <span class="govuk-pagination__link-title">Next</span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>

                </div>

                <!-- Sidebar -->
                <div class="govuk-grid-column-one-third">
                    <aside class="govuk-!-margin-top-6" role="complementary">
                        <h2 class="govuk-heading-s">Related links</h2>
                        <ul class="govuk-list">
                            <li>
                                <a href="<?= $basePath ?>/feed" class="govuk-link">Community feed</a>
                            </li>
                            <li>
                                <a href="<?= $basePath ?>/events" class="govuk-link">Upcoming events</a>
                            </li>
                            <li>
                                <a href="<?= $basePath ?>/help" class="govuk-link">Help centre</a>
                            </li>
                        </ul>
                    </aside>
                </div>
            </div>

        <?php endif; ?>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>

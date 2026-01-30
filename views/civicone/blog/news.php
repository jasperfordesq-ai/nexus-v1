<?php
/**
 * CivicOne View: Blog/News Index
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = "Latest News";
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'News']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<h1 class="govuk-heading-xl">
    <i class="fa-solid fa-newspaper govuk-!-margin-right-2" aria-hidden="true"></i>
    Latest News
</h1>
<p class="govuk-body-l govuk-!-margin-bottom-6">Updates from our community.</p>

<?php if (empty($posts)): ?>
    <div class="govuk-inset-text">
        <h3 class="govuk-heading-s govuk-!-margin-bottom-2">No updates yet</h3>
        <p class="govuk-body">Check back soon for the latest news.</p>
    </div>
<?php else: ?>
    <div class="govuk-grid-row">
        <?php foreach ($posts as $post): ?>
        <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6">
            <div class="govuk-!-padding-4 civicone-blog-card">
                <?php if ($post['featured_image']): ?>
                <div class="civicone-blog-card-image">
                    <img src="<?= htmlspecialchars($post['featured_image']) ?>"
                         alt=""
                         class="civicone-blog-card-img"
                         loading="lazy">
                </div>
                <?php endif; ?>

                <p class="govuk-body-s govuk-!-margin-bottom-2 civicone-secondary-text">
                    <?= date('j F Y', strtotime($post['created_at'])) ?>
                </p>

                <h3 class="govuk-heading-s govuk-!-margin-bottom-2">
                    <a href="<?= $basePath ?>/blog/<?= $post['slug'] ?>" class="govuk-link">
                        <?= htmlspecialchars($post['title']) ?>
                    </a>
                </h3>

                <p class="govuk-body-s govuk-!-margin-bottom-4 civicone-blog-card-excerpt">
                    <?= htmlspecialchars(substr($post['excerpt'] ?: strip_tags($post['content']), 0, 150)) ?>...
                </p>

                <a href="<?= $basePath ?>/blog/<?= $post['slug'] ?>"
                   class="govuk-link"
                   aria-label="Read full article: <?= htmlspecialchars($post['title']) ?>">
                    Read more
                    <i class="fa-solid fa-arrow-right govuk-!-margin-left-1" aria-hidden="true"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>

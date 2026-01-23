<?php
/**
 * Help Center Search Results - GOV.UK Design System
 * Template E: Content/Article (Search variant)
 * WCAG 2.1 AA Compliant
 *
 * @version 2.0.0 - Full GOV.UK refactor
 * @since 2026-01-23
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
$pageTitle = !empty($query) ? 'Search results for "' . htmlspecialchars($query) . '"' : 'Search Help Centre';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

// Module display names (GOV.UK compliant - no icons)
$moduleNames = [
    'getting_started' => 'Getting Started',
    'core' => 'Platform Basics',
    'wallet' => 'Wallet and Credits',
    'listings' => 'Marketplace',
    'groups' => 'Community Hubs',
    'events' => 'Events',
    'volunteering' => 'Volunteering',
    'blog' => 'News and Updates',
    'polls' => 'Polls and Voting',
    'goals' => 'Goals and Buddies',
    'governance' => 'Governance',
    'gamification' => 'Badges and Rewards',
    'ai_assistant' => 'AI Assistant',
    'sustainability' => 'Impact and SDGs',
    'offline' => 'Offline Mode',
    'mobile' => 'Mobile App',
    'insights' => 'Your Stats',
    'security' => 'Privacy and Security',
    'resources' => 'Resource Library',
    'reviews' => 'Reviews and Ratings',
];

/**
 * Create excerpt with highlighted search terms
 */
function highlightExcerpt(string $content, string $query, int $maxLength = 200): string
{
    $text = strip_tags($content);
    $pos = stripos($text, $query);

    if ($pos !== false) {
        $start = max(0, $pos - 50);
        $excerpt = substr($text, $start, $maxLength);
        if ($start > 0) $excerpt = '...' . $excerpt;
        if (strlen($text) > $start + $maxLength) $excerpt .= '...';
    } else {
        $excerpt = substr($text, 0, $maxLength);
        if (strlen($text) > $maxLength) $excerpt .= '...';
    }

    if (!empty($query)) {
        $excerpt = preg_replace('/(' . preg_quote($query, '/') . ')/i', '<mark>$1</mark>', $excerpt);
    }

    return $excerpt;
}
?>

<div class="govuk-width-container">

    <!-- Breadcrumbs -->
    <nav class="govuk-breadcrumbs" aria-label="Breadcrumb">
        <ol class="govuk-breadcrumbs__list">
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
            </li>
            <li class="govuk-breadcrumbs__list-item">
                <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/help">Help Centre</a>
            </li>
            <li class="govuk-breadcrumbs__list-item" aria-current="page">
                Search
            </li>
        </ol>
    </nav>

    <main class="govuk-main-wrapper" role="main">

        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">

                <?php if (!empty($query)): ?>
                    <h1 class="govuk-heading-xl">
                        Search results for "<?= htmlspecialchars($query) ?>"
                    </h1>
                    <p class="govuk-body-l">
                        <?= count($results) ?> article<?= count($results) !== 1 ? 's' : '' ?> found
                    </p>
                <?php else: ?>
                    <h1 class="govuk-heading-xl">Search Help Centre</h1>
                    <p class="govuk-body-l">
                        Enter a search term to find articles
                    </p>
                <?php endif; ?>

                <!-- Search Form -->
                <form action="<?= $basePath ?>/help/search" method="GET" class="govuk-!-margin-bottom-6">
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--m" for="search-query">
                            Search help articles
                        </label>
                        <div id="search-hint" class="govuk-hint">
                            Enter keywords to search our help documentation
                        </div>
                        <input class="govuk-input govuk-!-width-two-thirds"
                               id="search-query"
                               name="q"
                               type="search"
                               value="<?= htmlspecialchars($query ?? '') ?>"
                               aria-describedby="search-hint"
                               autofocus>
                    </div>
                    <button type="submit" class="govuk-button" data-module="govuk-button">
                        Search
                    </button>
                </form>

                <?php if (!empty($query)): ?>
                    <?php if (empty($results)): ?>
                        <!-- No Results -->
                        <div class="govuk-inset-text">
                            <h2 class="govuk-heading-m">No articles found</h2>
                            <p class="govuk-body">
                                We could not find any articles matching your search.
                            </p>
                            <p class="govuk-body">
                                Try different keywords or <a href="<?= $basePath ?>/help" class="govuk-link">browse all articles</a>.
                            </p>
                        </div>
                    <?php else: ?>
                        <!-- Results List -->
                        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">

                        <ul class="govuk-list" role="list" aria-label="Search results">
                            <?php foreach ($results as $article):
                                $moduleName = $moduleNames[$article['module_tag']] ?? ucfirst(str_replace('_', ' ', $article['module_tag']));
                                $excerpt = highlightExcerpt($article['content'], $query);
                            ?>
                            <li class="govuk-!-margin-bottom-6">
                                <h2 class="govuk-heading-m govuk-!-margin-bottom-2">
                                    <a href="<?= $basePath ?>/help/<?= htmlspecialchars($article['slug']) ?>" class="govuk-link">
                                        <?= htmlspecialchars($article['title']) ?>
                                    </a>
                                </h2>
                                <p class="govuk-body-s govuk-!-margin-bottom-2 civicone-text-secondary">
                                    <?= htmlspecialchars($moduleName) ?>
                                </p>
                                <p class="govuk-body">
                                    <?= $excerpt ?>
                                </p>
                                <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>

            </div>

            <!-- Sidebar -->
            <div class="govuk-grid-column-one-third">
                <aside class="govuk-!-margin-top-6" role="complementary">

                    <h2 class="govuk-heading-s">Browse by category</h2>
                    <p class="govuk-body">
                        <a href="<?= $basePath ?>/help" class="govuk-link">View all help categories</a>
                    </p>

                    <hr class="govuk-section-break govuk-section-break--m govuk-section-break--visible">

                    <h2 class="govuk-heading-s">Still need help?</h2>
                    <ul class="govuk-list">
                        <li>
                            <a href="<?= $basePath ?>/faq" class="govuk-link">Frequently asked questions</a>
                        </li>
                        <li>
                            <a href="<?= $basePath ?>/contact" class="govuk-link">Contact us</a>
                        </li>
                    </ul>

                </aside>
            </div>
        </div>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>

<?php
/**
 * Template A: Directory Page - Volunteer Organizations
 * GOV.UK Design System (WCAG 2.1 AA)
 *
 * Purpose: Browse and search volunteer organizations
 * Features: Search, filtering, organization cards, empty states
 */

$pageTitle = "Organizations";
\Nexus\Core\SEO::setTitle('Volunteer Organizations - Find Causes You Care About');
\Nexus\Core\SEO::setDescription('Browse volunteer organizations in your community. Join teams, discover causes, and make a meaningful impact.');

require __DIR__ . '/../../layouts/civicone/header.php';

$base = \Nexus\Core\TenantContext::getBasePath();
$hasTimebanking = $hasTimebanking ?? \Nexus\Core\TenantContext::hasFeature('wallet');
?>

<div class="govuk-width-container">
    <a href="<?= $base ?>/volunteering" class="govuk-back-link">Back to opportunities</a>

    <main class="govuk-main-wrapper">
        <!-- Page Header -->
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">
                    <i class="fa-solid fa-building-columns govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                    Organizations
                </h1>
                <p class="govuk-body-l">Discover groups making a difference in your community</p>
            </div>
        </div>

        <!-- Search Form -->
        <div class="govuk-!-margin-bottom-6 govuk-!-padding-4 civicone-panel-bg" style="border-left: 5px solid #1d70b8;">
            <form method="GET" action="<?= $base ?>/volunteering/organizations">
                <div class="govuk-form-group govuk-!-margin-bottom-0">
                    <label class="govuk-label govuk-!-font-weight-bold" for="search-query">
                        Search organizations
                    </label>
                    <div class="govuk-grid-row">
                        <div class="govuk-grid-column-two-thirds">
                            <input type="text"
                                   name="q"
                                   id="search-query"
                                   class="govuk-input"
                                   placeholder="Search by name or cause..."
                                   value="<?= htmlspecialchars($query ?? '') ?>">
                        </div>
                        <div class="govuk-grid-column-one-third">
                            <button type="submit" class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">
                                <i class="fa-solid fa-search govuk-!-margin-right-2" aria-hidden="true"></i>
                                Search
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!empty($query)): ?>
            <p class="govuk-body">
                Found <strong><?= count($organizations) ?></strong> organization<?= count($organizations) !== 1 ? 's' : '' ?>
                matching "<strong><?= htmlspecialchars($query) ?></strong>"
            </p>
        <?php endif; ?>

        <?php if (empty($organizations)): ?>
            <!-- Empty State -->
            <div class="govuk-!-padding-6 govuk-!-text-align-center civicone-panel-bg" style="border-left: 5px solid #1d70b8;">
                <p class="govuk-body govuk-!-margin-bottom-4">
                    <i class="fa-solid fa-building-circle-xmark fa-3x" style="color: #1d70b8;" aria-hidden="true"></i>
                </p>
                <h2 class="govuk-heading-l">No Organizations Found</h2>
                <p class="govuk-body govuk-!-margin-bottom-6">
                    <?php if (!empty($query)): ?>
                        No organizations match your search. Try different keywords.
                    <?php else: ?>
                        There are no organizations yet. Be the first to register one!
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <!-- Organizations Grid -->
            <div class="govuk-grid-row">
                <?php foreach ($organizations as $org): ?>
                    <div class="govuk-grid-column-one-half govuk-!-margin-bottom-6">
                        <div style="background: white; border: 1px solid #b1b4b6; border-left: 5px solid #00703c; height: 100%;">
                            <a href="<?= $base ?>/volunteering/organization/<?= $org['id'] ?>" style="text-decoration: none; color: inherit; display: block;">
                                <!-- Card Header -->
                                <div class="govuk-!-padding-4" style="border-bottom: 1px solid #f3f2f1;">
                                    <div style="display: flex; align-items: center; gap: 16px;">
                                        <!-- Logo -->
                                        <div class="civicone-panel-bg" style="width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;">
                                            <?php if (!empty($org['logo'])): ?>
                                                <img src="<?= htmlspecialchars($org['logo']) ?>" loading="lazy" alt="<?= htmlspecialchars($org['name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <span class="govuk-heading-m govuk-!-margin-bottom-0" style="color: #1d70b8;">
                                                    <?= strtoupper(substr($org['name'], 0, 1)) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Title -->
                                        <div style="flex: 1; min-width: 0;">
                                            <h3 class="govuk-heading-s govuk-!-margin-bottom-1" style="color: #1d70b8;">
                                                <?= htmlspecialchars($org['name']) ?>
                                            </h3>
                                            <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">
                                                <i class="fa-solid fa-user govuk-!-margin-right-1" aria-hidden="true"></i>
                                                <?= htmlspecialchars($org['owner_name'] ?? 'Unknown') ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Description -->
                                <div class="govuk-!-padding-4">
                                    <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #0b0c0c;">
                                        <?= htmlspecialchars(substr($org['description'], 0, 200)) ?><?= strlen($org['description']) > 200 ? '...' : '' ?>
                                    </p>
                                </div>

                                <!-- Stats -->
                                <div class="govuk-!-padding-4 civicone-panel-bg">
                                    <div style="display: flex; gap: 20px;">
                                        <span class="govuk-body-s govuk-!-margin-bottom-0">
                                            <i class="fa-solid fa-briefcase govuk-!-margin-right-1" style="color: #00703c;" aria-hidden="true"></i>
                                            <strong><?= (int)($org['opportunity_count'] ?? 0) ?></strong> Opportunities
                                        </span>
                                        <?php if ($hasTimebanking && isset($org['member_count'])): ?>
                                            <span class="govuk-body-s govuk-!-margin-bottom-0">
                                                <i class="fa-solid fa-users govuk-!-margin-right-1" style="color: #1d70b8;" aria-hidden="true"></i>
                                                <strong><?= (int)$org['member_count'] ?></strong> Members
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

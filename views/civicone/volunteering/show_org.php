<?php
/**
 * Template C: Detail Page - Organization Profile
 * GOV.UK Design System (WCAG 2.1 AA)
 *
 * Purpose: Display organization profile
 * Features: Organization info, volunteer opportunities, membership, admin controls
 */

if (session_status() === PHP_SESSION_NONE) session_start();

$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$base = \Nexus\Core\TenantContext::getBasePath();
$hasTimebanking = \Nexus\Core\TenantContext::hasFeature('wallet');

// Set variables for the utility bar
$activeTab = 'profile';
$isMember = $isMember ?? false;
$isAdmin = $isAdmin ?? false;
$isOwner = $isOwner ?? false;
$role = $isOwner ? 'owner' : ($isAdmin ? 'admin' : ($isMember ? 'member' : ''));

// Get pending count for badge (if admin)
$pendingCount = 0;
if ($isAdmin && $hasTimebanking) {
    try {
        $pendingCount = \Nexus\Models\OrgTransferRequest::countPending($org['id']);
    } catch (\Exception $e) {
        $pendingCount = 0;
    }
}

$pageTitle = htmlspecialchars($org['name']);
\Nexus\Core\SEO::setTitle($org['name'] . ' - Volunteer Organization');
\Nexus\Core\SEO::setDescription('Learn about ' . $org['name'] . ' and find volunteer opportunities.');

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="govuk-width-container">
    <a href="<?= $base ?>/volunteering/organizations" class="govuk-back-link">Back to organizations</a>

    <main class="govuk-main-wrapper">
        <!-- Organization Header -->
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-full">
                <div class="govuk-!-padding-6 civicone-org-header">
                    <div class="civicone-org-header-content">
                        <!-- Logo -->
                        <div class="civicone-panel-bg civicone-org-logo">
                            <?php if (!empty($org['logo_url'])): ?>
                                <img src="<?= htmlspecialchars($org['logo_url']) ?>" loading="lazy" alt="<?= htmlspecialchars($org['name']) ?>">
                            <?php else: ?>
                                <i class="fa-solid fa-building fa-2x civicone-icon-blue" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>

                        <!-- Info -->
                        <div class="civicone-org-info">
                            <div class="civicone-org-title-row">
                                <h1 class="govuk-heading-xl govuk-!-margin-bottom-2"><?= htmlspecialchars($org['name']) ?></h1>
                                <?php $statusClass = ($org['status'] ?? 'active') === 'active' ? 'govuk-tag--green' : 'govuk-tag--orange'; ?>
                                <strong class="govuk-tag <?= $statusClass ?>">
                                    <i class="fa-solid <?= ($org['status'] ?? 'active') === 'active' ? 'fa-circle-check' : 'fa-clock' ?> govuk-!-margin-right-1" aria-hidden="true"></i>
                                    <?= ucfirst($org['status'] ?? 'active') ?>
                                </strong>
                            </div>

                            <!-- Meta Info -->
                            <div class="govuk-!-margin-bottom-4 civicone-org-meta">
                                <?php if (!empty($org['contact_email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($org['contact_email']) ?>" class="govuk-link govuk-body-s">
                                        <i class="fa-solid fa-envelope govuk-!-margin-right-1 civicone-secondary-text" aria-hidden="true"></i>
                                        <?= htmlspecialchars($org['contact_email']) ?>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($org['website'])): ?>
                                    <a href="<?= htmlspecialchars($org['website']) ?>" target="_blank" class="govuk-link govuk-body-s">
                                        <i class="fa-solid fa-globe govuk-!-margin-right-1 civicone-secondary-text" aria-hidden="true"></i>
                                        Website
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($org['location'])): ?>
                                    <span class="govuk-body-s">
                                        <i class="fa-solid fa-location-dot govuk-!-margin-right-1 civicone-secondary-text" aria-hidden="true"></i>
                                        <?= htmlspecialchars($org['location']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($hasTimebanking && $memberCount > 0): ?>
                                    <span class="govuk-body-s">
                                        <i class="fa-solid fa-users govuk-!-margin-right-1 civicone-secondary-text" aria-hidden="true"></i>
                                        <?= $memberCount ?> member<?= $memberCount !== 1 ? 's' : '' ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($org['description'])): ?>
                                <p class="govuk-body govuk-!-margin-bottom-4"><?= nl2br(htmlspecialchars($org['description'])) ?></p>
                            <?php endif; ?>

                            <!-- Quick Actions -->
                            <div class="govuk-button-group">
                                <?php if ($hasTimebanking): ?>
                                    <?php if ($isMember): ?>
                                        <a href="<?= $base ?>/organizations/<?= $org['id'] ?>/wallet" class="govuk-button" data-module="govuk-button">
                                            <i class="fa-solid fa-wallet govuk-!-margin-right-2" aria-hidden="true"></i>
                                            View Wallet
                                        </a>
                                    <?php elseif ($isLoggedIn): ?>
                                        <form action="<?= $base ?>/organizations/<?= $org['id'] ?>/members/request" method="POST" class="civicone-inline-form">
                                            <?= \Nexus\Core\Csrf::input() ?>
                                            <button type="submit" class="govuk-button" data-module="govuk-button">
                                                <i class="fa-solid fa-user-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                                                Request to Join
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if (!empty($org['website'])): ?>
                                    <a href="<?= htmlspecialchars($org['website']) ?>" target="_blank" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                        <i class="fa-solid fa-external-link govuk-!-margin-right-2" aria-hidden="true"></i>
                                        Visit Website
                                    </a>
                                <?php endif; ?>

                                <?php if ($isAdmin): ?>
                                    <a href="<?= $base ?>/volunteering/opportunities/create?org=<?= $org['id'] ?>" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                        <i class="fa-solid fa-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                                        Add Opportunity
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="govuk-grid-row govuk-!-margin-bottom-6">
            <div class="govuk-grid-column-one-third">
                <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-panel-bg civicone-border-left-green">
                    <p class="govuk-heading-xl govuk-!-margin-bottom-1 civicone-heading-green"><?= count($opportunities) ?></p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">
                        <i class="fa-solid fa-hand-holding-heart govuk-!-margin-right-1" aria-hidden="true"></i>
                        Opportunities
                    </p>
                </div>
            </div>
            <?php if ($hasTimebanking && $memberCount > 0): ?>
                <div class="govuk-grid-column-one-third">
                    <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-panel-bg civicone-border-left-blue">
                        <p class="govuk-heading-xl govuk-!-margin-bottom-1 civicone-heading-blue"><?= $memberCount ?></p>
                        <p class="govuk-body-s govuk-!-margin-bottom-0">
                            <i class="fa-solid fa-users govuk-!-margin-right-1" aria-hidden="true"></i>
                            Members
                        </p>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($org['created_at'])): ?>
                <div class="govuk-grid-column-one-third">
                    <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-panel-bg civicone-border-left-orange">
                        <p class="govuk-heading-xl govuk-!-margin-bottom-1 civicone-heading-orange"><?= date('Y', strtotime($org['created_at'])) ?></p>
                        <p class="govuk-body-s govuk-!-margin-bottom-0">
                            <i class="fa-solid fa-calendar govuk-!-margin-right-1" aria-hidden="true"></i>
                            Founded
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Volunteer Opportunities Section -->
        <div class="govuk-!-margin-bottom-6">
            <div class="govuk-grid-row govuk-!-margin-bottom-4">
                <div class="govuk-grid-column-two-thirds">
                    <h2 class="govuk-heading-l govuk-!-margin-bottom-0">
                        <i class="fa-solid fa-hand-holding-heart govuk-!-margin-right-2 civicone-icon-green" aria-hidden="true"></i>
                        Volunteer Opportunities
                    </h2>
                </div>
                <?php if ($isAdmin): ?>
                    <div class="govuk-grid-column-one-third govuk-!-text-align-right">
                        <a href="<?= $base ?>/volunteering/opportunities/create?org=<?= $org['id'] ?>" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">
                            <i class="fa-solid fa-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                            Add New
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($opportunities)): ?>
                <div class="govuk-grid-row">
                    <?php foreach ($opportunities as $opp): ?>
                        <div class="govuk-grid-column-one-half govuk-!-margin-bottom-4">
                            <div class="civicone-opp-card">
                                <a href="<?= $base ?>/volunteering/<?= $opp['id'] ?>" class="civicone-opp-card-link">
                                    <div class="govuk-!-padding-4">
                                        <div class="civicone-opp-header">
                                            <div class="civicone-panel-bg civicone-opp-icon">
                                                <i class="fa-solid fa-hands-helping civicone-icon-blue" aria-hidden="true"></i>
                                            </div>
                                            <div>
                                                <h3 class="govuk-heading-s govuk-!-margin-bottom-1 civicone-heading-blue">
                                                    <?= htmlspecialchars($opp['title']) ?>
                                                </h3>
                                                <?php if (!empty($opp['commitment_type'])): ?>
                                                    <span class="govuk-tag govuk-tag--grey"><?= htmlspecialchars($opp['commitment_type']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <p class="govuk-body-s govuk-!-margin-bottom-3">
                                            <?= htmlspecialchars(substr($opp['description'], 0, 140)) ?>...
                                        </p>
                                        <div class="civicone-opp-meta">
                                            <?php if (!empty($opp['location'])): ?>
                                                <span class="govuk-body-s govuk-!-margin-bottom-0">
                                                    <i class="fa-solid fa-location-dot govuk-!-margin-right-1 civicone-secondary-text" aria-hidden="true"></i>
                                                    <?= htmlspecialchars($opp['location']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($opp['created_at'])): ?>
                                                <span class="govuk-body-s govuk-!-margin-bottom-0">
                                                    <i class="fa-solid fa-clock govuk-!-margin-right-1 civicone-secondary-text" aria-hidden="true"></i>
                                                    <?= date('M j, Y', strtotime($opp['created_at'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="govuk-!-padding-6 govuk-!-text-align-center civicone-panel-bg civicone-border-left-blue">
                    <p class="govuk-body govuk-!-margin-bottom-4">
                        <i class="fa-solid fa-clipboard-list fa-3x civicone-icon-blue" aria-hidden="true"></i>
                    </p>
                    <h3 class="govuk-heading-m">No Opportunities Yet</h3>
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        This organization hasn't posted any volunteer opportunities.
                    </p>
                    <?php if ($isAdmin): ?>
                        <div class="govuk-!-margin-top-4">
                            <a href="<?= $base ?>/volunteering/opportunities/create?org=<?= $org['id'] ?>" class="govuk-button" data-module="govuk-button">
                                <i class="fa-solid fa-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                                Create First Opportunity
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

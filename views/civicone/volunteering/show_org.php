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
                <div class="govuk-!-padding-6" style="background: white; border: 1px solid #b1b4b6; border-left: 5px solid #00703c;">
                    <div style="display: flex; align-items: flex-start; gap: 24px; flex-wrap: wrap;">
                        <!-- Logo -->
                        <div class="civicone-panel-bg" style="width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; border: 3px solid #00703c;">
                            <?php if (!empty($org['logo_url'])): ?>
                                <img src="<?= htmlspecialchars($org['logo_url']) ?>" loading="lazy" alt="<?= htmlspecialchars($org['name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <i class="fa-solid fa-building fa-2x" style="color: #1d70b8;" aria-hidden="true"></i>
                            <?php endif; ?>
                        </div>

                        <!-- Info -->
                        <div style="flex: 1; min-width: 200px;">
                            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                <h1 class="govuk-heading-xl govuk-!-margin-bottom-2"><?= htmlspecialchars($org['name']) ?></h1>
                                <?php
                                $statusColor = ($org['status'] ?? 'active') === 'active' ? '#00703c' : '#f47738';
                                ?>
                                <strong class="govuk-tag" style="background: <?= $statusColor ?>;">
                                    <i class="fa-solid <?= ($org['status'] ?? 'active') === 'active' ? 'fa-circle-check' : 'fa-clock' ?> govuk-!-margin-right-1" aria-hidden="true"></i>
                                    <?= ucfirst($org['status'] ?? 'active') ?>
                                </strong>
                            </div>

                            <!-- Meta Info -->
                            <div class="govuk-!-margin-bottom-4" style="display: flex; flex-wrap: wrap; gap: 16px;">
                                <?php if (!empty($org['contact_email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($org['contact_email']) ?>" class="govuk-link govuk-body-s">
                                        <i class="fa-solid fa-envelope govuk-!-margin-right-1" style="color: #505a5f;" aria-hidden="true"></i>
                                        <?= htmlspecialchars($org['contact_email']) ?>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($org['website'])): ?>
                                    <a href="<?= htmlspecialchars($org['website']) ?>" target="_blank" class="govuk-link govuk-body-s">
                                        <i class="fa-solid fa-globe govuk-!-margin-right-1" style="color: #505a5f;" aria-hidden="true"></i>
                                        Website
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($org['location'])): ?>
                                    <span class="govuk-body-s">
                                        <i class="fa-solid fa-location-dot govuk-!-margin-right-1" style="color: #505a5f;" aria-hidden="true"></i>
                                        <?= htmlspecialchars($org['location']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($hasTimebanking && $memberCount > 0): ?>
                                    <span class="govuk-body-s">
                                        <i class="fa-solid fa-users govuk-!-margin-right-1" style="color: #505a5f;" aria-hidden="true"></i>
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
                                        <form action="<?= $base ?>/organizations/<?= $org['id'] ?>/members/request" method="POST" style="display: inline;">
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
                <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-panel-bg" style="border-left: 5px solid #00703c;">
                    <p class="govuk-heading-xl govuk-!-margin-bottom-1" style="color: #00703c;"><?= count($opportunities) ?></p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">
                        <i class="fa-solid fa-hand-holding-heart govuk-!-margin-right-1" aria-hidden="true"></i>
                        Opportunities
                    </p>
                </div>
            </div>
            <?php if ($hasTimebanking && $memberCount > 0): ?>
                <div class="govuk-grid-column-one-third">
                    <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-panel-bg" style="border-left: 5px solid #1d70b8;">
                        <p class="govuk-heading-xl govuk-!-margin-bottom-1" style="color: #1d70b8;"><?= $memberCount ?></p>
                        <p class="govuk-body-s govuk-!-margin-bottom-0">
                            <i class="fa-solid fa-users govuk-!-margin-right-1" aria-hidden="true"></i>
                            Members
                        </p>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($org['created_at'])): ?>
                <div class="govuk-grid-column-one-third">
                    <div class="govuk-!-padding-4 govuk-!-text-align-center civicone-panel-bg" style="border-left: 5px solid #f47738;">
                        <p class="govuk-heading-xl govuk-!-margin-bottom-1" style="color: #f47738;"><?= date('Y', strtotime($org['created_at'])) ?></p>
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
                        <i class="fa-solid fa-hand-holding-heart govuk-!-margin-right-2" style="color: #00703c;" aria-hidden="true"></i>
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
                            <div style="background: white; border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8; height: 100%;">
                                <a href="<?= $base ?>/volunteering/<?= $opp['id'] ?>" style="text-decoration: none; color: inherit; display: block;">
                                    <div class="govuk-!-padding-4">
                                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                                            <div class="civicone-panel-bg" style="width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                <i class="fa-solid fa-hands-helping" style="color: #1d70b8;" aria-hidden="true"></i>
                                            </div>
                                            <div>
                                                <h3 class="govuk-heading-s govuk-!-margin-bottom-1" style="color: #1d70b8;">
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
                                        <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                                            <?php if (!empty($opp['location'])): ?>
                                                <span class="govuk-body-s govuk-!-margin-bottom-0">
                                                    <i class="fa-solid fa-location-dot govuk-!-margin-right-1" style="color: #505a5f;" aria-hidden="true"></i>
                                                    <?= htmlspecialchars($opp['location']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($opp['created_at'])): ?>
                                                <span class="govuk-body-s govuk-!-margin-bottom-0">
                                                    <i class="fa-solid fa-clock govuk-!-margin-right-1" style="color: #505a5f;" aria-hidden="true"></i>
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
                <div class="govuk-!-padding-6 govuk-!-text-align-center civicone-panel-bg" style="border-left: 5px solid #1d70b8;">
                    <p class="govuk-body govuk-!-margin-bottom-4">
                        <i class="fa-solid fa-clipboard-list fa-3x" style="color: #1d70b8;" aria-hidden="true"></i>
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

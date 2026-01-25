<?php
/**
 * Organization Utility Bar - GOV.UK Design System
 * WCAG 2.1 AA Compliant Navigation Component
 *
 * Required:
 * - $org (array) - The organization data with 'id', 'name'
 * - $activeTab (string) - Current tab: 'profile', 'wallet', 'members', 'requests', 'audit'
 *
 * Optional:
 * - $isAdmin (bool) - Whether current user is org admin
 * - $isMember (bool) - Whether current user is org member
 * - $isOwner (bool) - Whether current user is org owner
 * - $role (string) - Current user's role in org
 * - $pendingCount (int) - Number of pending requests (for badge)
 */

$base = \Nexus\Core\TenantContext::getBasePath();
$hasTimebanking = \Nexus\Core\TenantContext::hasFeature('wallet');
$orgId = $org['id'] ?? 0;
$orgName = $org['name'] ?? 'Organization';
$activeTab = $activeTab ?? 'profile';
$isAdmin = $isAdmin ?? false;
$isMember = $isMember ?? false;
$role = $role ?? 'member';
$pendingCount = $pendingCount ?? 0;
?>

<div class="govuk-!-margin-bottom-6 govuk-!-padding-4 civicone-panel-bg civicone-panel-border-blue">
    <!-- Header with org identity and actions -->
    <div class="govuk-grid-row govuk-!-margin-bottom-4">
        <div class="govuk-grid-column-two-thirds">
            <div class="civicone-flex-gap">
                <!-- Logo -->
                <div class="civicone-org-logo">
                    <?php if (!empty($org['logo_url'])): ?>
                        <img src="<?= htmlspecialchars($org['logo_url']) ?>" loading="lazy" alt="<?= htmlspecialchars($orgName) ?>">
                    <?php else: ?>
                        <span class="govuk-heading-m govuk-!-margin-bottom-0 civicone-org-logo-initial">
                            <?= strtoupper(substr($orgName, 0, 1)) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <!-- Info -->
                <div>
                    <h1 class="govuk-heading-l govuk-!-margin-bottom-1"><?= htmlspecialchars($orgName) ?></h1>
                    <?php if ($isMember): ?>
                        <?php
                        $roleColors = [
                            'owner' => '#f47738',
                            'admin' => '#912b88',
                            'member' => '#1d70b8'
                        ];
                        $roleColor = $roleColors[$role] ?? '#1d70b8';
                        $roleIcon = $role === 'owner' ? 'fa-crown' : ($role === 'admin' ? 'fa-shield' : 'fa-user');
                        ?>
                        <strong class="govuk-tag" style="background: <?= $roleColor ?>;">
                            <i class="fa-solid <?= $roleIcon ?> govuk-!-margin-right-1" aria-hidden="true"></i>
                            <?= ucfirst($role) ?>
                        </strong>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="govuk-grid-column-one-third govuk-!-text-align-right">
            <?php if ($isAdmin): ?>
                <a href="<?= $base ?>/volunteering/org/edit/<?= $orgId ?>" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-2" data-module="govuk-button">
                    <i class="fa-solid fa-pen govuk-!-margin-right-2" aria-hidden="true"></i>
                    Edit
                </a>
            <?php endif; ?>
            <a href="<?= $base ?>/volunteering" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">
                <i class="fa-solid fa-arrow-left govuk-!-margin-right-2" aria-hidden="true"></i>
                Volunteering
            </a>
        </div>
    </div>

    <!-- Tab Navigation -->
    <nav role="navigation" aria-label="Organization navigation">
        <ul class="civicone-nav-list">
            <li>
                <a href="<?= $base ?>/volunteering/organization/<?= $orgId ?>"
                   class="govuk-button <?= $activeTab === 'profile' ? '' : 'govuk-button--secondary' ?> govuk-!-margin-bottom-0"
                   data-module="govuk-button"
                   <?= $activeTab === 'profile' ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-building govuk-!-margin-right-2" aria-hidden="true"></i>
                    Profile
                </a>
            </li>

            <?php if ($hasTimebanking && $isMember): ?>
                <li>
                    <a href="<?= $base ?>/organizations/<?= $orgId ?>/wallet"
                       class="govuk-button <?= $activeTab === 'wallet' ? '' : 'govuk-button--secondary' ?> govuk-!-margin-bottom-0"
                       data-module="govuk-button"
                       <?= $activeTab === 'wallet' ? 'aria-current="page"' : '' ?>>
                        <i class="fa-solid fa-wallet govuk-!-margin-right-2" aria-hidden="true"></i>
                        Wallet
                    </a>
                </li>

                <li>
                    <a href="<?= $base ?>/organizations/<?= $orgId ?>/members"
                       class="govuk-button <?= $activeTab === 'members' ? '' : 'govuk-button--secondary' ?> govuk-!-margin-bottom-0"
                       data-module="govuk-button"
                       <?= $activeTab === 'members' ? 'aria-current="page"' : '' ?>>
                        <i class="fa-solid fa-users govuk-!-margin-right-2" aria-hidden="true"></i>
                        Members
                    </a>
                </li>

                <?php if ($isAdmin): ?>
                    <li>
                        <a href="<?= $base ?>/organizations/<?= $orgId ?>/wallet/requests"
                           class="govuk-button <?= $activeTab === 'requests' ? '' : 'govuk-button--secondary' ?> govuk-!-margin-bottom-0"
                           data-module="govuk-button"
                           <?= $activeTab === 'requests' ? 'aria-current="page"' : '' ?>>
                            <i class="fa-solid fa-inbox govuk-!-margin-right-2" aria-hidden="true"></i>
                            Requests
                            <?php if ($pendingCount > 0): ?>
                                <strong class="govuk-tag govuk-!-margin-left-2 civicone-tag-red"><?= $pendingCount ?></strong>
                            <?php endif; ?>
                        </a>
                    </li>

                    <li>
                        <a href="<?= $base ?>/organizations/<?= $orgId ?>/audit-log"
                           class="govuk-button <?= $activeTab === 'audit' ? '' : 'govuk-button--secondary' ?> govuk-!-margin-bottom-0"
                           data-module="govuk-button"
                           <?= $activeTab === 'audit' ? 'aria-current="page"' : '' ?>>
                            <i class="fa-solid fa-shield-halved govuk-!-margin-right-2" aria-hidden="true"></i>
                            Audit
                        </a>
                    </li>
                <?php endif; ?>
            <?php elseif ($hasTimebanking): ?>
                <!-- Non-member sees limited tabs -->
                <li>
                    <a href="<?= $base ?>/organizations/<?= $orgId ?>/members"
                       class="govuk-button <?= $activeTab === 'members' ? '' : 'govuk-button--secondary' ?> govuk-!-margin-bottom-0"
                       data-module="govuk-button"
                       <?= $activeTab === 'members' ? 'aria-current="page"' : '' ?>>
                        <i class="fa-solid fa-users govuk-!-margin-right-2" aria-hidden="true"></i>
                        Members
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>

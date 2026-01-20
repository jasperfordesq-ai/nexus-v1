<?php
// CivicOne View: Account Settings - GOV.UK Check Answers Pattern
// ===============================================================
// Pattern: Template G - Check Answers with Summary list
// WCAG 2.1 AA Compliant
// Refactored: 2026-01-20

$pageTitle = 'Account Settings';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<div class="govuk-width-container">
    <main class="govuk-main-wrapper">

        <!-- Page heading -->
        <h1 class="govuk-heading-xl">Account Settings</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">Success</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">Settings updated successfully.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="govuk-error-summary" aria-labelledby="error-summary-title" role="alert" data-module="govuk-error-summary">
                <h2 class="govuk-error-summary__title" id="error-summary-title">There is a problem</h2>
                <div class="govuk-error-summary__body">
                    <p>An error occurred. Please check your inputs and try again.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Profile Information Section -->
        <h2 class="govuk-heading-l">Profile information</h2>
        <dl class="govuk-summary-list">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Full name</dt>
                <dd class="govuk-summary-list__value"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></dd>
                <dd class="govuk-summary-list__actions">
                    <a class="govuk-link" href="<?= $basePath ?>/settings/profile/edit">
                        Change<span class="govuk-visually-hidden"> full name</span>
                    </a>
                </dd>
            </div>

            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Email address</dt>
                <dd class="govuk-summary-list__value"><?= htmlspecialchars($user['email'] ?? 'Not set') ?></dd>
                <dd class="govuk-summary-list__actions">
                    <a class="govuk-link" href="<?= $basePath ?>/settings/profile/edit">
                        Change<span class="govuk-visually-hidden"> email address</span>
                    </a>
                </dd>
            </div>

            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">About me</dt>
                <dd class="govuk-summary-list__value">
                    <?php if (!empty($user['bio'])): ?>
                        <?= nl2br(htmlspecialchars(mb_substr($user['bio'], 0, 150) . (mb_strlen($user['bio']) > 150 ? '...' : ''))) ?>
                    <?php else: ?>
                        <span class="govuk-hint">Not set</span>
                    <?php endif; ?>
                </dd>
                <dd class="govuk-summary-list__actions">
                    <a class="govuk-link" href="<?= $basePath ?>/settings/profile/edit">
                        Change<span class="govuk-visually-hidden"> about me bio</span>
                    </a>
                </dd>
            </div>

            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Profile picture</dt>
                <dd class="govuk-summary-list__value">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Profile picture" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <span class="govuk-hint">Using default picture</span>
                    <?php endif; ?>
                </dd>
                <dd class="govuk-summary-list__actions">
                    <a class="govuk-link" href="<?= $basePath ?>/settings/profile/edit">
                        Change<span class="govuk-visually-hidden"> profile picture</span>
                    </a>
                </dd>
            </div>
        </dl>

        <!-- Security Section -->
        <h2 class="govuk-heading-l govuk-!-margin-top-9">Security</h2>
        <dl class="govuk-summary-list">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Password</dt>
                <dd class="govuk-summary-list__value">
                    <span class="govuk-hint">Last changed <?= isset($user['password_changed_at']) ? date('j F Y', strtotime($user['password_changed_at'])) : 'Unknown' ?></span>
                </dd>
                <dd class="govuk-summary-list__actions">
                    <a class="govuk-link" href="<?= $basePath ?>/settings/security/password">
                        Change<span class="govuk-visually-hidden"> password</span>
                    </a>
                </dd>
            </div>

            <?php if (Nexus\Core\TenantContext::hasFeature('two_factor_auth')): ?>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Two-factor authentication</dt>
                <dd class="govuk-summary-list__value">
                    <?php if (!empty($user['two_factor_enabled'])): ?>
                        <strong class="govuk-tag govuk-tag--green">Enabled</strong>
                    <?php else: ?>
                        <strong class="govuk-tag govuk-tag--grey">Not enabled</strong>
                    <?php endif; ?>
                </dd>
                <dd class="govuk-summary-list__actions">
                    <a class="govuk-link" href="<?= $basePath ?>/settings/security/two-factor">
                        <?= !empty($user['two_factor_enabled']) ? 'Manage' : 'Enable' ?><span class="govuk-visually-hidden"> two-factor authentication</span>
                    </a>
                </dd>
            </div>
            <?php endif; ?>
        </dl>

        <!-- Privacy Section -->
        <h2 class="govuk-heading-l govuk-!-margin-top-9">Privacy</h2>
        <dl class="govuk-summary-list">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Who can see my profile?</dt>
                <dd class="govuk-summary-list__value">
                    <?php
                    $privacyProfile = $user['privacy_profile'] ?? 'public';
                    $privacyLabels = [
                        'public' => 'Everyone (Public)',
                        'members' => 'Members only',
                        'connections' => 'My connections only'
                    ];
                    echo htmlspecialchars($privacyLabels[$privacyProfile] ?? 'Everyone (Public)');
                    ?>
                </dd>
                <dd class="govuk-summary-list__actions">
                    <a class="govuk-link" href="<?= $basePath ?>/settings/privacy/edit">
                        Change<span class="govuk-visually-hidden"> who can see my profile</span>
                    </a>
                </dd>
            </div>

            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Search engine visibility</dt>
                <dd class="govuk-summary-list__value">
                    <?php if (!empty($user['privacy_search'])): ?>
                        Allow search engines to index my profile
                    <?php else: ?>
                        Do not allow search engines to index my profile
                    <?php endif; ?>
                </dd>
                <dd class="govuk-summary-list__actions">
                    <a class="govuk-link" href="<?= $basePath ?>/settings/privacy/edit">
                        Change<span class="govuk-visually-hidden"> search engine visibility</span>
                    </a>
                </dd>
            </div>
        </dl>

        <!-- Notifications Section (if feature enabled) -->
        <?php if (Nexus\Core\TenantContext::hasFeature('notifications')): ?>
        <h2 class="govuk-heading-l govuk-!-margin-top-9">Notifications</h2>
        <dl class="govuk-summary-list">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Email notifications</dt>
                <dd class="govuk-summary-list__value">
                    <?php if (!empty($user['email_notifications'])): ?>
                        <strong class="govuk-tag govuk-tag--green">On</strong>
                    <?php else: ?>
                        <strong class="govuk-tag govuk-tag--grey">Off</strong>
                    <?php endif; ?>
                </dd>
                <dd class="govuk-summary-list__actions">
                    <a class="govuk-link" href="<?= $basePath ?>/settings/notifications/edit">
                        Change<span class="govuk-visually-hidden"> email notification settings</span>
                    </a>
                </dd>
            </div>

            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Push notifications</dt>
                <dd class="govuk-summary-list__value">
                    <?php if (!empty($user['push_notifications'])): ?>
                        <strong class="govuk-tag govuk-tag--green">On</strong>
                    <?php else: ?>
                        <strong class="govuk-tag govuk-tag--grey">Off</strong>
                    <?php endif; ?>
                </dd>
                <dd class="govuk-summary-list__actions">
                    <a class="govuk-link" href="<?= $basePath ?>/settings/notifications/edit">
                        Change<span class="govuk-visually-hidden"> push notification settings</span>
                    </a>
                </dd>
            </div>
        </dl>
        <?php endif; ?>

        <!-- Account Management Section -->
        <h2 class="govuk-heading-l govuk-!-margin-top-9">Account management</h2>

        <div class="govuk-inset-text">
            <p class="govuk-body">Need to make changes to your account or have concerns about your data?</p>
            <ul class="govuk-list govuk-list--bullet">
                <li><a class="govuk-link" href="<?= $basePath ?>/settings/export">Download your data</a></li>
                <li><a class="govuk-link" href="<?= $basePath ?>/settings/deactivate">Deactivate account</a></li>
                <li><a class="govuk-link" href="<?= $basePath ?>/settings/delete">Delete account</a></li>
            </ul>
        </div>

        <!-- Back to profile link -->
        <p class="govuk-body govuk-!-margin-top-6">
            <a href="<?= $basePath ?>/profile/<?= $user['id'] ?>" class="govuk-link">
                <span aria-hidden="true">←</span> Back to profile
            </a>
        </p>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>

<?php
/**
 * CivicOne View: Volunteering Opportunity Detail
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$opportunityId = $opportunity['id'] ?? 0;

$pageTitle = $opportunity['title'] ?? 'Volunteer Opportunity';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Volunteering', 'href' => $basePath . '/volunteering'],
        ['text' => 'Opportunity']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<a href="<?= $basePath ?>/volunteering" class="govuk-back-link govuk-!-margin-bottom-6">Back to Opportunities</a>

<div class="govuk-grid-row">
    <!-- Main Content -->
    <div class="govuk-grid-column-two-thirds">

        <span class="govuk-tag govuk-!-margin-bottom-4">Volunteer with <?= htmlspecialchars($opportunity['org_name'] ?? 'Organization') ?></span>

        <h1 class="govuk-heading-xl"><?= htmlspecialchars($opportunity['title']) ?></h1>

        <?php if (!empty($opportunity['location'])): ?>
            <p class="govuk-body-l govuk-!-margin-bottom-6 civicone-secondary-text">
                <i class="fa-solid fa-location-dot govuk-!-margin-right-2" aria-hidden="true"></i>
                <?= htmlspecialchars($opportunity['location']) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($opportunity['org_website'])): ?>
            <p class="govuk-body govuk-!-margin-bottom-6">
                <a href="<?= htmlspecialchars($opportunity['org_website']) ?>" target="_blank" rel="noopener noreferrer" class="govuk-link">
                    <i class="fa-solid fa-external-link-alt govuk-!-margin-right-1" aria-hidden="true"></i>
                    Visit Organization Website
                </a>
            </p>
        <?php endif; ?>

        <!-- About the Role -->
        <h2 class="govuk-heading-l">About the Role</h2>
        <p class="govuk-body govuk-!-margin-bottom-6"><?= nl2br(htmlspecialchars($opportunity['description'] ?? '')) ?></p>

        <!-- Details -->
        <h2 class="govuk-heading-l">Details</h2>
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Skills Needed</dt>
                <dd class="govuk-summary-list__value"><?= htmlspecialchars($opportunity['skills_needed'] ?? 'None specified') ?></dd>
            </div>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Dates</dt>
                <dd class="govuk-summary-list__value">
                    <?php if (!empty($opportunity['start_date'])): ?>
                        <?= date('j F Y', strtotime($opportunity['start_date'])) ?>
                        <?= !empty($opportunity['end_date']) ? ' - ' . date('j F Y', strtotime($opportunity['end_date'])) : ' (Ongoing)' ?>
                    <?php else: ?>
                        Flexible / Ongoing
                    <?php endif; ?>
                </dd>
            </div>
            <?php if (!empty($opportunity['commitment'])): ?>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Time Commitment</dt>
                <dd class="govuk-summary-list__value"><?= htmlspecialchars($opportunity['commitment']) ?></dd>
            </div>
            <?php endif; ?>
        </dl>

        <!-- Social Interactions -->
        <?php
        $targetType = 'volunteering';
        $targetId = $opportunity['id'];
        include dirname(__DIR__) . '/partials/social_interactions.php';
        ?>

    </div>

    <!-- Sidebar -->
    <div class="govuk-grid-column-one-third">
        <div class="govuk-!-padding-4 civicone-sidebar-card">

            <!-- Organization Info -->
            <h2 class="govuk-heading-s">
                <i class="fa-solid fa-building govuk-!-margin-right-2" aria-hidden="true"></i>
                <?= htmlspecialchars($opportunity['org_name'] ?? 'Organization') ?>
            </h2>
            <p class="govuk-body-s govuk-!-margin-bottom-4 civicone-secondary-text">Community Organization</p>

            <!-- Application Status / Form -->
            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'applied'): ?>
                <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
                    <div class="govuk-notification-banner__header">
                        <h3 class="govuk-notification-banner__title" id="govuk-notification-banner-title">Success</h3>
                    </div>
                    <div class="govuk-notification-banner__content">
                        <p class="govuk-notification-banner__heading">Application Sent!</p>
                        <p class="govuk-body">The organization will contact you shortly.</p>
                    </div>
                </div>
            <?php elseif (!empty($hasApplied)): ?>
                <div class="govuk-inset-text">
                    <p class="govuk-body govuk-!-font-weight-bold">Already Applied</p>
                    <p class="govuk-body">You've applied for this opportunity.</p>
                </div>
            <?php elseif ($isLoggedIn): ?>
                <form action="<?= $basePath ?>/volunteering/apply" method="POST">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="opportunity_id" value="<?= $opportunity['id'] ?>">

                    <?php if (!empty($shifts)): ?>
                        <div class="govuk-form-group">
                            <fieldset class="govuk-fieldset">
                                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">
                                    <h3 class="govuk-fieldset__heading">Select a Shift</h3>
                                </legend>
                                <div class="govuk-radios" data-module="govuk-radios">
                                    <?php foreach ($shifts as $shift): ?>
                                        <div class="govuk-radios__item">
                                            <input class="govuk-radios__input" id="shift-<?= $shift['id'] ?>" name="shift_id" type="radio" value="<?= $shift['id'] ?>" required>
                                            <label class="govuk-label govuk-radios__label" for="shift-<?= $shift['id'] ?>">
                                                <?= date('j M', strtotime($shift['start_time'])) ?>:
                                                <?= date('g:i A', strtotime($shift['start_time'])) ?> - <?= date('g:i A', strtotime($shift['end_time'])) ?>
                                                <span class="govuk-hint govuk-radios__hint"><?= $shift['capacity'] ?> spots available</span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        </div>
                    <?php endif; ?>

                    <div class="govuk-form-group">
                        <label class="govuk-label" for="apply-message">
                            Message <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                        </label>
                        <textarea name="message" id="apply-message" rows="3" class="govuk-textarea"
                                  placeholder="Tell them why you'd like to volunteer..."></textarea>
                    </div>

                    <button type="submit" class="govuk-button civicone-button-full-width" data-module="govuk-button">
                        <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i>
                        Apply Now
                    </button>
                </form>
            <?php else: ?>
                <div class="govuk-inset-text">
                    <p class="govuk-body"><i class="fa-solid fa-lock govuk-!-margin-right-1" aria-hidden="true"></i> Join our community to volunteer.</p>
                    <a href="<?= $basePath ?>/login" class="govuk-button civicone-button-full-width" data-module="govuk-button">
                        Login to Apply
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>

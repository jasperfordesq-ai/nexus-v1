<?php
/**
 * CivicOne Groups Invite - Member Selection Page
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$hero_title = "Invite Members";
$hero_subtitle = "Grow your hub by inviting community members.";
$hero_gradient = 'htb-hero-gradient-hub';
$hero_type = 'Community';

require __DIR__ . '/../../layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Hubs', 'href' => $basePath . '/groups'],
        ['text' => htmlspecialchars($group['name']), 'href' => $basePath . '/groups/' . $group['id']],
        ['text' => 'Invite Members']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<a href="<?= $basePath ?>/groups/<?= $group['id'] ?>?tab=settings" class="govuk-back-link govuk-!-margin-bottom-6">Back to <?= htmlspecialchars($group['name']) ?></a>

<h1 class="govuk-heading-xl">Invite Members</h1>
<p class="govuk-body-l govuk-!-margin-bottom-6">Select members to invite to <strong><?= htmlspecialchars($group['name']) ?></strong></p>

<?php if (isset($_GET['err']) && $_GET['err'] === 'no_users'): ?>
    <div class="govuk-error-summary" role="alert" aria-labelledby="error-summary-title" data-module="govuk-error-summary">
        <h2 class="govuk-error-summary__title" id="error-summary-title">There is a problem</h2>
        <div class="govuk-error-summary__body">
            <p>Please select at least one member to invite.</p>
        </div>
    </div>
<?php endif; ?>

<div class="govuk-!-padding-6 civicone-panel-bg">
    <form action="<?= $basePath ?>/groups/<?= $group['id'] ?>/invite" method="POST" id="inviteForm" aria-label="Invite members to group">
        <?= Nexus\Core\Csrf::input() ?>

        <div class="govuk-form-group govuk-!-margin-bottom-6">
            <label class="govuk-label" for="userSearch">Search members</label>
            <input type="text" class="govuk-input govuk-!-width-full" id="userSearch" placeholder="Search members by name...">
        </div>

        <?php if (empty($availableUsers)): ?>
            <div class="govuk-inset-text">
                <p class="govuk-body">All community members are already in this hub!</p>
            </div>
        <?php else: ?>
            <fieldset class="govuk-fieldset govuk-!-margin-bottom-6">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">Select members to invite</h2>
                </legend>

                <div class="govuk-checkboxes" data-module="govuk-checkboxes" id="userList">
                    <?php foreach ($availableUsers as $user): ?>
                        <div class="govuk-checkboxes__item" data-name="<?= strtolower(htmlspecialchars($user['name'])) ?>">
                            <input class="govuk-checkboxes__input" id="user-<?= $user['id'] ?>" name="user_ids[]" type="checkbox" value="<?= $user['id'] ?>">
                            <label class="govuk-label govuk-checkboxes__label" for="user-<?= $user['id'] ?>">
                                <strong><?= htmlspecialchars($user['name']) ?></strong>
                                <?php if (!empty($user['email'])): ?>
                                    <span class="govuk-hint govuk-!-margin-bottom-0"><?= htmlspecialchars($user['email']) ?></span>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <p class="govuk-body govuk-!-margin-bottom-4" id="selectedCount" aria-live="polite"><strong>0</strong> members selected</p>

            <!-- Add Directly Option -->
            <div class="govuk-checkboxes govuk-!-margin-bottom-6" data-module="govuk-checkboxes">
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="addDirectlyCheckbox" name="add_directly" type="checkbox" value="1">
                    <label class="govuk-label govuk-checkboxes__label" for="addDirectlyCheckbox">
                        <strong>Add directly to hub</strong>
                        <span class="govuk-hint govuk-!-margin-bottom-0">Skip the invitation step and add selected members immediately. They'll receive a notification that they've been added.</span>
                    </label>
                </div>
            </div>

            <button type="submit" class="govuk-button" data-module="govuk-button" id="submitBtn" disabled>
                <i class="fa-solid fa-paper-plane govuk-!-margin-right-1" aria-hidden="true"></i> Send Invitations
            </button>
        <?php endif; ?>
    </form>
</div>

<!-- Member selection and search handled by civicone-groups-invite.js -->
<script src="/assets/js/civicone-groups-invite.js"></script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

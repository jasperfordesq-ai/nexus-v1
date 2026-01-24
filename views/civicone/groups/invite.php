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

$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/groups">Hubs</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/groups/<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Invite Members</li>
    </ol>
</nav>

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

<div class="govuk-!-padding-6" style="background: #f3f2f1;">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    var checkboxes = document.querySelectorAll('#userList input[type="checkbox"]');
    var selectedCount = document.getElementById('selectedCount');
    var submitBtn = document.getElementById('submitBtn');
    var searchInput = document.getElementById('userSearch');

    function updateCount() {
        var count = document.querySelectorAll('#userList input[type="checkbox"]:checked').length;
        selectedCount.innerHTML = '<strong>' + count + '</strong> members selected';
        if (submitBtn) {
            submitBtn.disabled = count === 0;
        }
    }

    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', updateCount);
    });

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var searchTerm = this.value.toLowerCase();
            document.querySelectorAll('#userList .govuk-checkboxes__item').forEach(function(item) {
                var name = item.getAttribute('data-name') || '';
                item.style.display = name.includes(searchTerm) ? '' : 'none';
            });
        });
    }
});
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

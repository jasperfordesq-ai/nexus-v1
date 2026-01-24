<?php
/**
 * Template D: Form Page - Edit Volunteer Organization
 * GOV.UK Design System (WCAG 2.1 AA)
 *
 * Purpose: Edit organization profile
 * Features: Offline detection, form validation, auto-pay settings
 */

$pageTitle = "Edit Organisation";
\Nexus\Core\SEO::setTitle('Edit Organisation');
\Nexus\Core\SEO::setDescription('Update your organisation profile.');

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div id="offlineBanner" class="govuk-!-display-none" role="alert" aria-live="polite" style="position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: #d4351c; color: white; padding: 12px; text-align: center;">
    <i class="fa-solid fa-wifi-slash govuk-!-margin-right-2" aria-hidden="true"></i>
    <strong>No internet connection</strong>
</div>

<div class="govuk-width-container">
    <a href="<?= $basePath ?>/volunteering/dashboard" class="govuk-back-link">Back to dashboard</a>

    <main class="govuk-main-wrapper">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">
                    <i class="fa-solid fa-building govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                    Edit <?= htmlspecialchars($org['name']) ?>
                </h1>

                <form action="<?= $basePath ?>/volunteering/org/update" method="POST" id="editOrgForm">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="org_id" value="<?= $org['id'] ?>">

                    <!-- Organization Name -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="org-name">
                            <i class="fa-solid fa-signature govuk-!-margin-right-1" style="color: #505a5f;" aria-hidden="true"></i>
                            Organization Name
                        </label>
                        <input type="text"
                               name="name"
                               id="org-name"
                               value="<?= htmlspecialchars($org['name']) ?>"
                               required
                               class="govuk-input"
                               placeholder="Enter organization name">
                    </div>

                    <!-- Contact Email -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="org-email">
                            <i class="fa-solid fa-envelope govuk-!-margin-right-1" style="color: #505a5f;" aria-hidden="true"></i>
                            Contact Email
                        </label>
                        <input type="email"
                               name="email"
                               id="org-email"
                               value="<?= htmlspecialchars($org['contact_email']) ?>"
                               required
                               class="govuk-input"
                               placeholder="org@example.com">
                    </div>

                    <!-- Website -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="org-website">
                            <i class="fa-solid fa-globe govuk-!-margin-right-1" style="color: #505a5f;" aria-hidden="true"></i>
                            Website
                        </label>
                        <span class="govuk-hint">Optional</span>
                        <input type="url"
                               name="website"
                               id="org-website"
                               value="<?= htmlspecialchars($org['website']) ?>"
                               class="govuk-input"
                               placeholder="https://...">
                    </div>

                    <!-- Description -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="org-description">
                            <i class="fa-solid fa-align-left govuk-!-margin-right-1" style="color: #505a5f;" aria-hidden="true"></i>
                            Description
                        </label>
                        <span class="govuk-hint">Describe your organization's mission and activities</span>
                        <textarea name="description"
                                  id="org-description"
                                  rows="5"
                                  required
                                  class="govuk-textarea"><?= htmlspecialchars($org['description']) ?></textarea>
                    </div>

                    <?php if (\Nexus\Core\TenantContext::hasFeature('wallet')): ?>
                        <!-- Auto-Pay Feature Box -->
                        <div class="govuk-!-margin-bottom-6 govuk-!-padding-4 civicone-panel-bg" style="border-left: 5px solid #f47738;">
                            <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                                <div class="govuk-checkboxes__item">
                                    <input type="checkbox"
                                           name="auto_pay"
                                           value="1"
                                           <?= $org['auto_pay_enabled'] ? 'checked' : '' ?>
                                           class="govuk-checkboxes__input"
                                           id="auto-pay">
                                    <label class="govuk-label govuk-checkboxes__label" for="auto-pay">
                                        <strong>
                                            <i class="fa-solid fa-wand-magic-sparkles govuk-!-margin-right-1" style="color: #f47738;" aria-hidden="true"></i>
                                            Enable Auto-Pay Time Credits
                                        </strong>
                                    </label>
                                    <div class="govuk-hint govuk-checkboxes__hint">
                                        When enabled, approving hours will automatically transfer Time Credits from your personal wallet to the volunteer's wallet (1 Hour = 1 Credit).
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="govuk-!-margin-bottom-6 govuk-!-padding-4 civicone-panel-bg" style="border-left: 5px solid #1d70b8;">
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-3">
                                <i class="fa-solid fa-bolt govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                                Quick Actions
                            </h3>
                            <div class="govuk-button-group">
                                <a href="<?= $basePath ?>/organizations/<?= $org['id'] ?>/wallet" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                    <i class="fa-solid fa-wallet govuk-!-margin-right-2" aria-hidden="true"></i>
                                    Org Wallet
                                </a>
                                <a href="<?= $basePath ?>/organizations/<?= $org['id'] ?>/members" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                    <i class="fa-solid fa-users govuk-!-margin-right-2" aria-hidden="true"></i>
                                    Members
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Form Actions -->
                    <div class="govuk-button-group">
                        <button type="submit" class="govuk-button" data-module="govuk-button" id="submitBtn">
                            <i class="fa-solid fa-check govuk-!-margin-right-2" aria-hidden="true"></i>
                            Save Changes
                        </button>
                        <a href="<?= $basePath ?>/volunteering/dashboard" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
// Offline Indicator
(function() {
    var banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.remove('govuk-!-display-none');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.add('govuk-!-display-none');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Form Submission Offline Protection
document.getElementById('editOrgForm').addEventListener('submit', function(e) {
    if (!navigator.onLine) {
        e.preventDefault();
        alert('You are offline. Please connect to the internet to save changes.');
    }
});
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

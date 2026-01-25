<?php
/**
 * Template D: Form Page - Create/Edit Volunteer Opportunity
 * GOV.UK Design System (WCAG 2.1 AA)
 *
 * Purpose: Create or edit volunteer opportunity details
 * Features: Offline detection, form validation, shift scheduling
 */

$pageTitle = "Create Opportunity";
\Nexus\Core\SEO::setTitle('Create Volunteer Opportunity');
\Nexus\Core\SEO::setDescription('Create a new volunteer opportunity for your organization.');

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
$isEdit = !empty($opp['id']);
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
                    <i class="fa-solid fa-<?= $isEdit ? 'edit' : 'plus-circle' ?> govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                    <?= $isEdit ? 'Edit ' . htmlspecialchars($opp['title']) : 'Create New Opportunity' ?>
                </h1>

                <form action="<?= $basePath ?>/volunteering/opp/<?= $isEdit ? 'update' : 'store' ?>" method="POST" id="oppForm">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="opp_id" value="<?= $opp['id'] ?>">
                    <?php endif; ?>

                    <!-- Role Title -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="title">
                            Role Title
                        </label>
                        <input type="text"
                               name="title"
                               id="title"
                               value="<?= htmlspecialchars($opp['title'] ?? '') ?>"
                               required
                               class="govuk-input">
                    </div>

                    <!-- Category -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="category_id">Category</label>
                        <select name="category_id" id="category_id" class="govuk-select">
                            <option value="">Select Category...</option>
                            <?php foreach ($categories ?? [] as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($opp['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Location -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="location">Location</label>
                        <input type="text"
                               name="location"
                               id="location"
                               value="<?= htmlspecialchars($opp['location'] ?? '') ?>"
                               required
                               class="govuk-input mapbox-location-input-v2">
                        <input type="hidden" name="latitude" value="<?= $opp['latitude'] ?? '' ?>">
                        <input type="hidden" name="longitude" value="<?= $opp['longitude'] ?? '' ?>">
                    </div>

                    <!-- Skills -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="skills">Skills Needed</label>
                        <span class="govuk-hint">Comma separated list of skills</span>
                        <input type="text"
                               name="skills"
                               id="skills"
                               value="<?= htmlspecialchars($opp['skills_needed'] ?? '') ?>"
                               placeholder="e.g. Communication, Teamwork, First Aid"
                               class="govuk-input">
                    </div>

                    <!-- Date Range -->
                    <div class="govuk-grid-row">
                        <div class="govuk-grid-column-one-half">
                            <div class="govuk-form-group">
                                <label class="govuk-label govuk-label--s" for="start_date">Start Date</label>
                                <input type="date"
                                       name="start_date"
                                       id="start_date"
                                       value="<?= $opp['start_date'] ?? '' ?>"
                                       class="govuk-input">
                            </div>
                        </div>
                        <div class="govuk-grid-column-one-half">
                            <div class="govuk-form-group">
                                <label class="govuk-label govuk-label--s" for="end_date">End Date</label>
                                <input type="date"
                                       name="end_date"
                                       id="end_date"
                                       value="<?= $opp['end_date'] ?? '' ?>"
                                       class="govuk-input">
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="description">Description</label>
                        <span class="govuk-hint">Describe the role, responsibilities, and what volunteers will gain</span>
                        <textarea name="description"
                                  id="description"
                                  rows="6"
                                  required
                                  class="govuk-textarea"><?= htmlspecialchars($opp['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Form Actions -->
                    <div class="govuk-button-group">
                        <button type="submit" class="govuk-button" data-module="govuk-button">
                            <i class="fa-solid fa-<?= $isEdit ? 'check' : 'plus' ?> govuk-!-margin-right-2" aria-hidden="true"></i>
                            <?= $isEdit ? 'Save Changes' : 'Create Opportunity' ?>
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

<!-- Offline indicator + form protection handled by civicone-common.js -->
<script>
    // Initialize form offline protection for this page
    if (typeof CivicOne !== 'undefined') {
        CivicOne.initFormOfflineProtection();
    }
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

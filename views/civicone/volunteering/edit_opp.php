<?php
/**
 * Template D: Form Page - Edit Volunteer Opportunity
 * GOV.UK Design System (WCAG 2.1 AA)
 *
 * Purpose: Edit existing volunteer opportunity details and manage shifts
 * Features: Offline detection, form validation, shift scheduling
 */

$pageTitle = "Edit Opportunity";
\Nexus\Core\SEO::setTitle('Edit Opportunity');
\Nexus\Core\SEO::setDescription('Update details for this volunteer opportunity.');

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div id="offlineBanner" class="govuk-!-display-none civicone-offline-banner-fixed" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash govuk-!-margin-right-2" aria-hidden="true"></i>
    <strong>No internet connection</strong>
</div>

<div class="govuk-width-container">
    <a href="<?= $basePath ?>/volunteering/dashboard" class="govuk-back-link">Back to dashboard</a>

    <main class="govuk-main-wrapper">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <h1 class="govuk-heading-xl">
                    <i class="fa-solid fa-edit govuk-!-margin-right-2 civicone-icon-blue" aria-hidden="true"></i>
                    Edit <?= htmlspecialchars($opp['title']) ?>
                </h1>

                <form action="<?= $basePath ?>/volunteering/opp/update" method="POST" id="editOppForm">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="opp_id" value="<?= $opp['id'] ?>">

                    <!-- Role Title -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="title">Role Title</label>
                        <input type="text"
                               name="title"
                               id="title"
                               value="<?= htmlspecialchars($opp['title']) ?>"
                               required
                               class="govuk-input">
                    </div>

                    <!-- Category -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="category_id">Category</label>
                        <select name="category_id" id="category_id" class="govuk-select">
                            <option value="">Select Category...</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $opp['category_id'] == $cat['id'] ? 'selected' : '' ?>>
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
                               value="<?= htmlspecialchars($opp['location']) ?>"
                               required
                               class="govuk-input mapbox-location-input-v2">
                        <input type="hidden" name="latitude" value="<?= $opp['latitude'] ?? '' ?>">
                        <input type="hidden" name="longitude" value="<?= $opp['longitude'] ?? '' ?>">
                    </div>

                    <!-- Skills -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="skills">Skills</label>
                        <span class="govuk-hint">Comma separated list of skills needed</span>
                        <input type="text"
                               name="skills"
                               id="skills"
                               value="<?= htmlspecialchars($opp['skills_needed']) ?>"
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
                                       value="<?= $opp['start_date'] ?>"
                                       class="govuk-input">
                            </div>
                        </div>
                        <div class="govuk-grid-column-one-half">
                            <div class="govuk-form-group">
                                <label class="govuk-label govuk-label--s" for="end_date">End Date</label>
                                <input type="date"
                                       name="end_date"
                                       id="end_date"
                                       value="<?= $opp['end_date'] ?>"
                                       class="govuk-input">
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="description">Description</label>
                        <textarea name="description"
                                  id="description"
                                  rows="6"
                                  required
                                  class="govuk-textarea"><?= htmlspecialchars($opp['description']) ?></textarea>
                    </div>

                    <!-- Form Actions -->
                    <div class="govuk-button-group">
                        <button type="submit" class="govuk-button" data-module="govuk-button">
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

        <!-- Shifts Management -->
        <div class="govuk-grid-row govuk-!-margin-top-6">
            <div class="govuk-grid-column-two-thirds">
                <div class="govuk-!-padding-4 civicone-panel-bg civicone-panel-border-blue">
                    <h2 class="govuk-heading-m">
                        <i class="fa-solid fa-calendar-alt govuk-!-margin-right-2 civicone-icon-blue" aria-hidden="true"></i>
                        Manage Shifts
                    </h2>
                    <p class="govuk-hint">Add specific time slots for this opportunity.</p>

                    <?php if (!empty($shifts)): ?>
                        <table class="govuk-table govuk-!-margin-bottom-4">
                            <thead class="govuk-table__head">
                                <tr class="govuk-table__row">
                                    <th scope="col" class="govuk-table__header">Time</th>
                                    <th scope="col" class="govuk-table__header">Capacity</th>
                                    <th scope="col" class="govuk-table__header">Action</th>
                                </tr>
                            </thead>
                            <tbody class="govuk-table__body">
                                <?php foreach ($shifts as $shift): ?>
                                    <tr class="govuk-table__row">
                                        <td class="govuk-table__cell">
                                            <strong><?= date('M d, Y', strtotime($shift['start_time'])) ?></strong><br>
                                            <span class="govuk-hint govuk-!-margin-bottom-0">
                                                <?= date('h:i A', strtotime($shift['start_time'])) ?> - <?= date('h:i A', strtotime($shift['end_time'])) ?>
                                            </span>
                                        </td>
                                        <td class="govuk-table__cell">
                                            <strong class="govuk-tag civicone-tag-blue">
                                                <?= $shift['capacity'] ?> vols
                                            </strong>
                                        </td>
                                        <td class="govuk-table__cell">
                                            <form action="<?= $basePath ?>/volunteering/shift/delete" method="POST" onsubmit="return confirm('Are you sure you want to delete this shift?');">
                                                <?= \Nexus\Core\Csrf::input() ?>
                                                <input type="hidden" name="shift_id" value="<?= $shift['id'] ?>">
                                                <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button" title="Delete shift">
                                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="govuk-body govuk-!-margin-bottom-4">
                            <em>No shifts added yet. This opportunity is "Flexible" by default.</em>
                        </p>
                    <?php endif; ?>

                    <!-- Add New Shift -->
                    <div class="govuk-!-padding-4 civicone-card-white-bordered">
                        <h3 class="govuk-heading-s">
                            <i class="fa-solid fa-plus govuk-!-margin-right-2 civicone-icon-green" aria-hidden="true"></i>
                            Add New Shift
                        </h3>
                        <form action="<?= $basePath ?>/volunteering/shift/store" method="POST">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="opp_id" value="<?= $opp['id'] ?>">

                            <div class="govuk-grid-row">
                                <div class="govuk-grid-column-one-third">
                                    <div class="govuk-form-group">
                                        <label class="govuk-label" for="shift_start">Start Time</label>
                                        <input type="datetime-local" name="start_time" id="shift_start" required class="govuk-input">
                                    </div>
                                </div>
                                <div class="govuk-grid-column-one-third">
                                    <div class="govuk-form-group">
                                        <label class="govuk-label" for="shift_end">End Time</label>
                                        <input type="datetime-local" name="end_time" id="shift_end" required class="govuk-input">
                                    </div>
                                </div>
                                <div class="govuk-grid-column-one-third">
                                    <div class="govuk-form-group">
                                        <label class="govuk-label" for="shift_capacity">Capacity</label>
                                        <input type="number" name="capacity" id="shift_capacity" value="1" min="1" required class="govuk-input">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                <i class="fa-solid fa-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                                Add Shift
                            </button>
                        </form>
                    </div>
                </div>
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

<?php
/**
 * CivicOne View: Create Volunteer Opportunity
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Post New Opportunity';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/volunteering">Volunteering</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Post Opportunity</li>
    </ol>
</nav>

<a href="<?= $basePath ?>/volunteering" class="govuk-back-link govuk-!-margin-bottom-6">Back to Volunteering</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">Post New Opportunity</h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">Find volunteers for your organization.</p>

        <form action="<?= $basePath ?>/volunteering/opp/store" method="POST">
            <?= Nexus\Core\Csrf::input() ?>

            <!-- Organization Selection -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="org_id">Organization</label>
                <select name="org_id" id="org_id" class="govuk-select" required>
                    <?php foreach ($myOrgs as $org): ?>
                        <option value="<?= $org['id'] ?>" <?= ($preselectedOrgId == $org['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($org['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Title -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="title">Opportunity Title</label>
                <div id="title-hint" class="govuk-hint">For example, "Fundraising Coordinator" or "Event Helper"</div>
                <input class="govuk-input" type="text" name="title" id="title" aria-describedby="title-hint" required>
            </div>

            <!-- Description -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="description">Description</label>
                <div id="description-hint" class="govuk-hint">Describe the role, responsibilities, and what volunteers can expect</div>
                <textarea class="govuk-textarea" name="description" id="description" rows="5" aria-describedby="description-hint" required></textarea>
            </div>

            <!-- Skills -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="skills">
                    Skills Needed <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                </label>
                <div id="skills-hint" class="govuk-hint">Separate skills with commas, e.g. "Communication, teamwork, driving license"</div>
                <input class="govuk-input" type="text" name="skills" id="skills" aria-describedby="skills-hint">
            </div>

            <!-- Category -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="category_id">Category</label>
                <select name="category_id" id="category_id" class="govuk-select">
                    <option value="" selected>General Volunteering</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date Range -->
            <div class="govuk-grid-row">
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="start_date">Start Date</label>
                        <input class="govuk-input govuk-input--width-10" type="date" name="start_date" id="start_date" required>
                    </div>
                </div>
                <div class="govuk-grid-column-one-half">
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="end_date">
                            End Date <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                        </label>
                        <input class="govuk-input govuk-input--width-10" type="date" name="end_date" id="end_date">
                    </div>
                </div>
            </div>

            <!-- Location -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="location">Location</label>
                <div id="location-hint" class="govuk-hint">Enter an address or "Remote" for virtual opportunities</div>
                <input class="govuk-input mapbox-location-input-v2" type="text" name="location" id="location" aria-describedby="location-hint">
            </div>

            <button type="submit" class="govuk-button" data-module="govuk-button">
                <i class="fa-solid fa-paper-plane govuk-!-margin-right-1" aria-hidden="true"></i>
                Post Opportunity
            </button>
        </form>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>

<?php
/**
 * Template D: Form Page - Create Volunteer Opportunity
 * WCAG 2.1 AA Compliant
 *
 * Allows users to post new volunteer opportunities for their organizations.
 * Includes organization selection, role details, and location mapping.
 */

$pageTitle = 'Post New Opportunity';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>
<link rel="stylesheet" href="<?= $basePath ?>/assets/css/civicone-volunteering-create-opp.css">

<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">

        <div class="civic-container">

            <div class="create-opp-header">
                <h1>Post New Opportunity</h1>
                <p class="create-opp-subtitle">Find volunteers for your organization.</p>
            </div>

            <div class="civic-card create-opp-card">
                <form action="<?= $basePath ?>/volunteering/opp/store" method="POST">
                    <?= Nexus\Core\Csrf::input() ?>

                    <!-- Organization Selection -->
                    <div class="form-group">
                        <label for="org_id" class="form-label">Organization</label>
                        <select name="org_id" id="org_id" class="civic-input form-input-full" required>
                            <?php foreach ($myOrgs as $org): ?>
                                <option value="<?= $org['id'] ?>" <?= ($preselectedOrgId == $org['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($org['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Title -->
                    <div class="form-group">
                        <label for="title" class="form-label">Opportunity Title</label>
                        <input type="text" name="title" id="title" placeholder="e.g. Fundraising Coordinator" required class="civic-input form-input-full">
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea name="description" id="description" rows="5" placeholder="Describe the role and responsibilities..." required class="civic-input form-textarea"></textarea>
                    </div>

                    <!-- Skills -->
                    <div class="form-group">
                        <label for="skills" class="form-label">Skills Needed</label>
                        <input type="text" name="skills" id="skills" placeholder="e.g. Accounting, manual labor (comma separated)" class="civic-input form-input-full">
                    </div>

                    <!-- Category -->
                    <div class="form-group">
                        <label for="category_id" class="form-label">Category</label>
                        <select name="category_id" id="category_id" class="civic-input form-input-full">
                            <option value="" selected>General Volunteering</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div class="civic-date-range-grid">
                        <div>
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" name="start_date" id="start_date" required class="civic-input form-input-full">
                        </div>
                        <div>
                            <label for="end_date" class="form-label">End Date (Optional)</label>
                            <input type="date" name="end_date" id="end_date" class="civic-input form-input-full">
                        </div>
                    </div>

                    <!-- Location -->
                    <div class="form-group">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" name="location" id="location" placeholder="Address or 'Remote'" class="civic-input mapbox-location-input-v2 form-input-full">
                    </div>

                    <button type="submit" class="civic-btn create-opp-submit">Post Opportunity</button>
                </form>
            </div>

        </div>

    </main>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>

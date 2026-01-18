<?php
// CivicOne View: Create Volunteer Opportunity
$pageTitle = 'Post New Opportunity';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <div style="margin-bottom: 30px; border-bottom: 4px solid var(--skin-primary, #00796B); padding-bottom: 10px;">
        <h1 style="margin: 0; text-transform: uppercase;">Post New Opportunity</h1>
        <p style="margin: 5px 0 0; color: var(--civic-text-secondary, #4B5563);">Find volunteers for your organization.</p>
    </div>

    <div class="civic-card" style="max-width: 800px;">
        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/opp/store" method="POST">
            <?= Nexus\Core\Csrf::input() ?>

            <!-- Organization Selection -->
            <div style="margin-bottom: 20px;">
                <label for="org_id" style="display: block; font-weight: bold; margin-bottom: 5px;">Organization</label>
                <select name="org_id" id="org_id" class="civic-input" style="width: 100%;" required>
                    <?php foreach ($myOrgs as $org): ?>
                        <option value="<?= $org['id'] ?>" <?= ($preselectedOrgId == $org['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($org['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Title -->
            <div style="margin-bottom: 20px;">
                <label for="title" style="display: block; font-weight: bold; margin-bottom: 5px;">Opposition Title</label>
                <input type="text" name="title" id="title" placeholder="e.g. Fundraising Coordinator" required class="civic-input" style="width: 100%;">
            </div>

            <!-- Description -->
            <div style="margin-bottom: 20px;">
                <label for="description" style="display: block; font-weight: bold; margin-bottom: 5px;">Description</label>
                <textarea name="description" id="description" rows="5" placeholder="Describe the role and responsibilities..." required class="civic-input" style="width: 100%; font-family: inherit;"></textarea>
            </div>

            <!-- Skills -->
            <div style="margin-bottom: 20px;">
                <label for="skills" style="display: block; font-weight: bold; margin-bottom: 5px;">Skills Needed</label>
                <input type="text" name="skills" id="skills" placeholder="e.g. Accounting, manual labor (comma separated)" class="civic-input" style="width: 100%;">
            </div>

            <!-- Category -->
            <div style="margin-bottom: 20px;">
                <label for="category_id" style="display: block; font-weight: bold; margin-bottom: 5px;">Category</label>
                <select name="category_id" id="category_id" class="civic-input" style="width: 100%;">
                    <option value="" selected>General Volunteering</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date Range -->
            <div class="civic-date-range-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label for="start_date" style="display: block; font-weight: bold; margin-bottom: 5px;">Start Date</label>
                    <input type="date" name="start_date" id="start_date" required class="civic-input" style="width: 100%;">
                </div>
                <div>
                    <label for="end_date" style="display: block; font-weight: bold; margin-bottom: 5px;">End Date (Optional)</label>
                    <input type="date" name="end_date" id="end_date" class="civic-input" style="width: 100%;">
                </div>
            </div>

            <!-- Location -->
            <div style="margin-bottom: 20px;">
                <label for="location" style="display: block; font-weight: bold; margin-bottom: 5px;">Location</label>
                <input type="text" name="location" id="location" placeholder="Address or 'Remote'" class="civic-input mapbox-location-input-v2" style="width: 100%;">
            </div>

            <button type="submit" class="civic-btn" style="width: 100%; font-size: 1.2rem;">Post Opportunity</button>
        </form>
    </div>

</div>

<style>
    /* Mobile responsive for date range */
    @media (max-width: 500px) {
        .civic-date-range-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
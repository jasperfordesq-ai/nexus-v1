<?php
/**
 * Template D: Form Page - Edit Volunteer Opportunity
 *
 * Purpose: Edit existing volunteer opportunity details and manage shifts
 * Features: Offline detection, form validation, shift scheduling
 * WCAG 2.1 AA: 44px minimum touch targets, keyboard navigation, focus states
 */

// views/modern/volunteering/edit_opp.php
$hero_title = "Edit Opportunity";
$hero_subtitle = "Update details for this role.";
$hero_gradient = 'htb-hero-gradient-teal';

require __DIR__ . '/../../layouts/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>
<link rel="stylesheet" href="/assets/css/purged/civicone-volunteering-edit-opp.min.css">


<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container edit-opp-container">
    <div class="htb-card">
        <div class="htb-card-body">
            <h3>Edit <?= htmlspecialchars($opp['title']) ?></h3>
            <form action="<?= $basePath ?>/volunteering/opp/update" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="opp_id" value="<?= $opp['id'] ?>">

                <div class="form-field">
                    <label class="form-field-label">Role Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($opp['title']) ?>" required class="form-input">
                </div>

                <div class="form-field">
                    <label class="form-field-label">Category</label>
                    <select name="category_id" class="form-input">
                        <option value="">Select Category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $opp['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label class="form-field-label">Location</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($opp['location']) ?>" required class="form-input mapbox-location-input-v2">
                    <input type="hidden" name="latitude" value="<?= $opp['latitude'] ?? '' ?>">
                    <input type="hidden" name="longitude" value="<?= $opp['longitude'] ?? '' ?>">
                </div>

                <div class="form-field">
                    <label class="form-field-label">Skills</label>
                    <input type="text" name="skills" value="<?= htmlspecialchars($opp['skills_needed']) ?>" placeholder="Comma separated" class="form-input">
                </div>

                <div class="date-grid">
                    <div>
                        <label class="form-field-label">Start Date</label>
                        <input type="date" name="start_date" value="<?= $opp['start_date'] ?>" class="form-input">
                    </div>
                    <div>
                        <label class="form-field-label">End Date</label>
                        <input type="date" name="end_date" value="<?= $opp['end_date'] ?>" class="form-input">
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-field-label">Description</label>
                    <textarea name="description" rows="6" required class="form-input"><?= htmlspecialchars($opp['description']) ?></textarea>
                </div>

                <div class="button-row">
                    <button class="htb-btn htb-btn-primary flex-1">Save Changes</button>
                    <a href="<?= $basePath ?>/volunteering/dashboard" class="htb-btn htb-btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Shifts Management -->
    <div class="htb-card card-mt-30">
        <div class="htb-card-body">
            <h3>Manage Shifts</h3>
            <p class="text-muted-sm">Add specific time slots for this opportunity.</p>

            <?php if (!empty($shifts)): ?>
                <table class="shifts-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Capacity</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shifts as $shift): ?>
                            <tr>
                                <td>
                                    <?= date('M d, Y', strtotime($shift['start_time'])) ?><br>
                                    <span class="shift-time-secondary">
                                        <?= date('h:i A', strtotime($shift['start_time'])) ?> - <?= date('h:i A', strtotime($shift['end_time'])) ?>
                                    </span>
                                </td>
                                <td><?= $shift['capacity'] ?> vols</td>
                                <td>
                                    <form action="<?= $basePath ?>/volunteering/shift/delete" method="POST" onsubmit="return confirm('Are you sure?');">
                                        <?= \Nexus\Core\Csrf::input() ?>
                                        <input type="hidden" name="shift_id" value="<?= $shift['id'] ?>">
                                        <button class="btn-delete-icon" title="Delete" aria-label="Delete shift">&#128465;</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted-italic">No shifts added yet. This opportunity is "Flexible" by default.</p>
            <?php endif; ?>

            <div class="add-shift-box">
                <h5>Add New Shift</h5>
                <form action="<?= $basePath ?>/volunteering/shift/store" method="POST">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="opp_id" value="<?= $opp['id'] ?>">

                    <div class="shift-form-grid">
                        <div>
                            <label class="form-field-label-sm">Start Time</label>
                            <input type="datetime-local" name="start_time" required class="form-input-sm">
                        </div>
                        <div>
                            <label class="form-field-label-sm">End Time</label>
                            <input type="datetime-local" name="end_time" required class="form-input-sm">
                        </div>
                        <div>
                            <label class="form-field-label-sm">Capacity</label>
                            <input type="number" name="capacity" value="1" min="1" required class="form-input-sm">
                        </div>
                    </div>
                    <button class="htb-btn htb-btn-sm btn-add-shift">+ Add Shift</button>
                </form>
            </div>
        </div>
    </div>
</div>


<script src="/assets/js/civicone-volunteering-edit-opp.js"></script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>

<?php
// Edit Event View - High-End Adaptive Holographic Glassmorphism Edition
// ISOLATED LAYOUT: Uses #unique-glass-page-wrapper and html[data-theme] selectors.

require __DIR__ . '/../../layouts/header.php';

// PREPARATION LOGIC
// 1. Extract Date/Time components for HTML5 inputs
$startParts = explode(' ', $event['start_time']);
$startDate = $startParts[0];
$startTime = substr($startParts[1] ?? '00:00:00', 0, 5); // HH:MM

$endDate = '';
$endTime = '';
if (!empty($event['end_time'])) {
    $endParts = explode(' ', $event['end_time']);
    $endDate = $endParts[0];
    $endTime = substr($endParts[1] ?? '00:00:00', 0, 5);
}

// 2. Decode SDGs
$selectedSDGs = [];
if (!empty($event['sdg_goals'])) {
    $selectedSDGs = json_decode($event['sdg_goals'], true) ?? [];
}
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<!-- Events Edit CSS -->
<link rel="stylesheet" href="<?= NexusCoreTenantContext::getBasePath() ?>/assets/css/purged/civicone-events-edit.min.css">

<div id="unique-glass-page-wrapper">
    <div class="glass-box">

        <div class="page-header">
            <h1>Edit Event</h1>
            <div class="page-subtitle">Make changes to your gathering.</div>
        </div>

        <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $event['id'] ?>/update" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Title -->
            <div class="form-group">
                <label>Event Title</label>
                <input type="text" name="title" id="title" value="<?= htmlspecialchars($event['title']) ?>" class="glass-input" required>
            </div>

            <!-- Location -->
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" value="<?= htmlspecialchars($event['location']) ?>" class="glass-input mapbox-location-input-v2" required>
                <input type="hidden" name="latitude" value="<?= $event['latitude'] ?? '' ?>">
                <input type="hidden" name="longitude" value="<?= $event['longitude'] ?? '' ?>">
            </div>

            <!-- Category -->
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="glass-input">
                    <option value="">-- Select Category --</option>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($event['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Host as Group -->
            <?php if (!empty($myGroups)): ?>
                <div class="form-group">
                    <label>Host as Hub (Optional)</label>
                    <select name="group_id" class="glass-input">
                        <option value="">-- Personal Event --</option>
                        <?php foreach ($myGroups as $grp): ?>
                            <option value="<?= $grp['id'] ?>" <?= ($event['group_id'] == $grp['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($grp['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Dates & Times -->
            <div class="form-group dates-grid grid-2col">
                <div>
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?= $startDate ?>" class="glass-input" required>
                </div>
                <div>
                    <label>Start Time</label>
                    <input type="time" name="start_time" value="<?= $startTime ?>" class="glass-input" required>
                </div>
            </div>

            <div class="form-group dates-grid grid-2col">
                <div>
                    <label>End Date (Optional)</label>
                    <input type="date" name="end_date" value="<?= $endDate ?>" class="glass-input">
                </div>
                <div>
                    <label>End Time (Optional)</label>
                    <input type="time" name="end_time" value="<?= $endTime ?>" class="glass-input">
                </div>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label>Description</label>
                <?php
                $aiGenerateType = 'event';
                $aiTitleField = 'title';
                $aiDescriptionField = 'description';
                $aiTypeField = null;
                include __DIR__ . '/../../partials/ai-generate-button.php';
                ?>
                <textarea name="description" id="description" class="glass-input" rows="5" required><?= htmlspecialchars($event['description']) ?></textarea>
            </div>

            <!-- SDG Glass Accordion -->
            <details <?= !empty($selectedSDGs) ? 'open' : '' ?>>
                <summary>
                    <span class="summary-content">
                        Social Impact <span class="summary-optional">(Optional)</span>
                    </span>
                    <span class="summary-arrow">▼</span>
                </summary>

                <div class="sdg-content">
                    <p class="sdg-hint">Tag which goals this event supports.</p>

                    <?php
                    require_once __DIR__ . '/../../../src/Helpers/SDG.php';
                    $sdgs = \Nexus\Helpers\SDG::all();
                    ?>

                    <div class="sdg-grid">
                        <?php foreach ($sdgs as $id => $goal): ?>
                            <?php $isChecked = in_array($id, $selectedSDGs); ?>
                            <label class="glass-sdg-card <?= $isChecked ? 'selected' : '' ?>" style="--sdg-color: <?= $goal['color'] ?>;">
                                <input type="checkbox" name="sdg_goals[]" value="<?= $id ?>" <?= $isChecked ? 'checked' : '' ?> class="hidden" onchange="toggleSDGClass(this)">
                                <span class="sdg-card-icon"><?= $goal['icon'] ?></span>
                                <span class="sdg-card-label"><?= $goal['label'] ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>

            <script>
                function toggleSDGClass(cb) {
                    const card = cb.parentElement;
                    if (cb.checked) {
                        card.classList.add('selected');
                    } else {
                        card.classList.remove('selected');
                    }
                }
            </script>

            <!-- SEO Settings Accordion -->
            <?php
            $seo = $seo ?? \Nexus\Models\SeoMetadata::get('event', $event['id']);
            $entityTitle = $event['title'] ?? '';
            $entityUrl = \Nexus\Core\TenantContext::getBasePath() . '/events/' . $event['id'];
            require __DIR__ . '/../../partials/seo-accordion.php';
            ?>

            <div class="actions-group">
                <button type="submit" class="glass-btn-primary">Save Changes</button>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $event['id'] ?>" class="glass-btn-secondary">Cancel</a>
            </div>

        </form>

    </div>
</div>

<!-- Events Edit JavaScript -->
<script src="<?= NexusCoreTenantContext::getBasePath() ?>/assets/js/civicone-events-edit.min.js" defer></script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>

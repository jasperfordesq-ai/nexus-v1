<?php
// CivicOne View: Create Event
$pageTitle = 'Host an Event';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">
    <?php
    $breadcrumbs = [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Events', 'url' => '/events'],
        ['label' => 'Host an Event']
    ];
    require dirname(__DIR__, 2) . '/layouts/civicone/partials/breadcrumb.php';
    ?>

    <div style="margin-bottom: 30px; border-bottom: 4px solid var(--skin-primary, #00796B); padding-bottom: 10px;">
        <h1 style="margin: 0; text-transform: uppercase;">Host an Event</h1>
        <p style="margin: 5px 0 0; color: var(--civic-text-secondary, #4B5563);">Organize a meetup, workshop, or gathering.</p>
    </div>

    <div class="civic-card" style="max-width: 800px;">
        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/store" method="POST">
            <?= Nexus\Core\Csrf::input() ?>

            <!-- Title -->
            <div style="margin-bottom: 20px;">
                <label for="title" style="display: block; font-weight: bold; margin-bottom: 5px;">Event Title</label>
                <input type="text" name="title" id="title" placeholder="e.g. Community Garden Cleanup" required class="civic-input" style="width: 100%;">
            </div>

            <!-- Description -->
            <div style="margin-bottom: 20px;">
                <label for="description" style="display: block; font-weight: bold; margin-bottom: 5px;">Description</label>
                <textarea name="description" id="description" rows="5" placeholder="Details about the event..." required class="civic-input" style="width: 100%; font-family: inherit;"></textarea>
            </div>

            <!-- Category -->
            <div style="margin-bottom: 20px;">
                <label for="category_id" style="display: block; font-weight: bold; margin-bottom: 5px;">Category</label>
                <select name="category_id" id="category_id" class="civic-input" style="width: 100%;">
                    <option value="" selected>General Event</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date & Time -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label for="start_date" style="display: block; font-weight: bold; margin-bottom: 5px;">Date</label>
                    <input type="date" name="start_date" id="start_date" required class="civic-input" style="width: 100%;">
                </div>
                <div>
                    <label for="start_time" style="display: block; font-weight: bold; margin-bottom: 5px;">Time</label>
                    <input type="time" name="start_time" id="start_time" required class="civic-input" style="width: 100%;">
                </div>
            </div>

            <!-- Location -->
            <div style="margin-bottom: 20px;">
                <label for="location" style="display: block; font-weight: bold; margin-bottom: 5px;">Location</label>
                <input type="text" name="location" id="location" placeholder="Venue Name or Address" class="civic-input mapbox-location-input-v2" style="width: 100%;">
                <input type="hidden" name="latitude">
                <input type="hidden" name="longitude">
            </div>

            <!-- Group (Optional) -->
            <?php if (!empty($myGroups)): ?>
                <div style="margin-bottom: 20px;">
                    <label for="group_id" style="display: block; font-weight: bold; margin-bottom: 5px;">Host as Group (Optional)</label>
                    <select name="group_id" id="group_id" class="civic-input" style="width: 100%;">
                        <option value="">-- Personal Event --</option>
                        <?php foreach ($myGroups as $group): ?>
                            <option value="<?= $group['id'] ?>" <?= ($selectedGroupId == $group['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($group['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <button type="submit" class="civic-btn" style="width: 100%; font-size: 1.2rem;">Create Event</button>
        </form>
    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
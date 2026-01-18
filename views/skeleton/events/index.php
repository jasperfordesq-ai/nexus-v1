<?php
/**
 * Skeleton Layout - Events Index
 * Browse all community events
 */

use Nexus\Core\TenantContext;

$basePath = TenantContext::getBasePath();
?>

<?php include __DIR__ . '/../../layouts/skeleton/header.php'; ?>

<div class="sk-flex-between" style="margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 2rem; font-weight: 700;">Community Events</h1>
        <p style="color: #888;">Discover and join upcoming events</p>
    </div>
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="<?= $basePath ?>/events/create" class="sk-btn">
            <i class="fas fa-plus"></i> Create Event
        </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="sk-card" style="margin-bottom: 2rem;">
    <form method="GET" action="<?= $basePath ?>/events">
        <div class="sk-flex" style="flex-wrap: wrap;">
            <div class="sk-form-group" style="flex: 1; min-width: 200px;">
                <input type="text" name="search" class="sk-form-input" placeholder="Search events..."
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            <div class="sk-form-group" style="min-width: 150px;">
                <select name="when" class="sk-form-select">
                    <option value="">All Times</option>
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                </select>
            </div>
            <button type="submit" class="sk-btn">Filter</button>
        </div>
    </form>
</div>

<!-- Events Grid -->
<?php if (!empty($events) && is_array($events)): ?>
    <div class="sk-grid">
        <?php foreach ($events as $event):
            if (!is_array($event)) continue;
            $startDate = !empty($event['start_date']) ? new DateTime($event['start_date']) : null;
        ?>
            <div class="sk-card">
                <?php if (!empty($event['image'])): ?>
                    <img src="<?= htmlspecialchars($event['image']) ?>" alt="Event Image"
                         style="width: 100%; height: 150px; object-fit: cover; border-radius: 8px; margin-bottom: 1rem;">
                <?php endif; ?>

                <!-- Date Badge -->
                <?php if ($startDate): ?>
                    <div style="position: relative; margin-bottom: 1rem;">
                        <div class="sk-badge" style="font-size: 0.75rem;">
                            <i class="far fa-calendar"></i> <?= $startDate->format('M j, Y') ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="sk-card-title">
                    <a href="<?= $basePath ?>/events/<?= $event['id'] ?? '' ?>" style="color: var(--sk-text); text-decoration: none;">
                        <?= htmlspecialchars($event['title'] ?? 'Untitled Event') ?>
                    </a>
                </div>

                <div class="sk-card-meta">
                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($event['location'] ?? 'Online') ?>
                </div>

                <p style="color: #666; margin-bottom: 1rem; line-height: 1.5;">
                    <?= htmlspecialchars(substr($event['description'] ?? 'No description', 0, 120)) ?>...
                </p>

                <div class="sk-flex-between">
                    <div style="color: #888; font-size: 0.875rem;">
                        <i class="fas fa-user-check"></i> <?= $event['attendees_count'] ?? 0 ?> attending
                    </div>
                    <a href="<?= $basePath ?>/events/<?= $event['id'] ?? '' ?>" class="sk-btn">View Event</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="sk-empty-state">
        <div class="sk-empty-state-icon"><i class="far fa-calendar"></i></div>
        <h3>No events found</h3>
        <p>Be the first to create an event!</p>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $basePath ?>/events/create" class="sk-btn">Create Event</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../layouts/skeleton/footer.php'; ?>

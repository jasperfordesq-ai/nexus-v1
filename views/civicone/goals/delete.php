<?php
/**
 * Template D: Confirmation Page
 * Delete Goal Confirmation
 * WCAG 2.1 AA Compliant
 * CivicOne Theme
 */

$hTitle = 'Delete Goal';
require __DIR__ . '/../../layouts/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Goals Delete CSS -->
<link rel="stylesheet" href="/assets/css/purged/civicone-goals-delete.min.css?v=<?= time() ?>">

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="goals-delete-wrapper">
    <div class="htb-card">
        <div class="goals-delete-card-body">
            <div class="goals-delete-warning-icon" aria-hidden="true">&#9888;&#65039;</div>
            <h2 class="goals-delete-title">Delete Goal?</h2>
            <p class="goals-delete-description">
                Are you sure you want to delete <strong>#<?= htmlspecialchars($goal['id']) ?> <?= htmlspecialchars($goal['title']) ?></strong>?
                <br>This action cannot be undone.
            </p>

            <form action="<?= $basePath ?>/goals/<?= $goal['id'] ?>/delete" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>

                <div class="goals-delete-actions">
                    <a href="<?= $basePath ?>/goals/<?= $goal['id'] ?>" class="htb-btn htb-btn-secondary">Cancel</a>
                    <button type="submit" class="htb-btn htb-btn-danger">Yes, Delete Goal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Goals Delete JavaScript -->
<script src="/assets/js/civicone-goals-delete.js?v=<?= time() ?>" defer></script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>

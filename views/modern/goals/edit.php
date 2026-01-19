<?php
// Goal Edit View - Modern Holographic Glassmorphism Edition
require __DIR__ . '/../../layouts/modern/header.php';
?>

<!-- Offline Banner -->
<div class="holo-offline-banner" id="offlineBanner">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>


<div class="holo-goal-page">
    <!-- Floating Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="holo-glass-card">
        <div class="holo-header">
            <div class="holo-header-icon">
                <i class="fa-solid fa-pen-to-square"></i>
            </div>
            <h1 class="holo-title">Edit Goal</h1>
            <p class="holo-subtitle">Update your commitment.</p>
        </div>

        <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/goals/<?= $goal['id'] ?>/update" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Title -->
            <div class="holo-form-group">
                <label class="holo-label">Goal Title</label>
                <input type="text" name="title" class="holo-input" required
                       value="<?= htmlspecialchars($goal['title']) ?>">
            </div>

            <!-- Description -->
            <div class="holo-form-group">
                <label class="holo-label">Description</label>
                <textarea name="description" class="holo-input" rows="4" required><?= htmlspecialchars($goal['description']) ?></textarea>
            </div>

            <!-- Target Date -->
            <div class="holo-form-group">
                <label class="holo-label">Target Date</label>
                <input type="date" name="deadline" class="holo-input" value="<?= $goal['deadline'] ?>">
            </div>

            <!-- Goal Buddy Card -->
            <div class="holo-buddy-card">
                <label class="holo-buddy-label">
                    <input type="checkbox" name="is_public" value="1" class="holo-checkbox" <?= $goal['is_public'] ? 'checked' : '' ?>>
                    <div class="holo-buddy-content">
                        <div class="holo-buddy-title">
                            <i class="fa-solid fa-user-group"></i>
                            Public Goal
                        </div>
                        <div class="holo-buddy-desc">
                            Allow others to see this goal and offer to be your Goal Buddy.
                        </div>
                    </div>
                </label>
            </div>

            <div class="holo-actions">
                <button type="submit" class="holo-btn holo-btn-primary">
                    <i class="fa-solid fa-check"></i>
                    Save Changes
                </button>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/goals/<?= $goal['id'] ?>" class="holo-btn holo-btn-secondary">
                    Cancel
                </a>
            </div>
        </form>

        <!-- Danger Zone -->
        <div class="holo-danger-zone">
            <div class="holo-danger-label">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Danger Zone
            </div>
            <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/goals/<?= $goal['id'] ?>/delete" method="POST"
                  onsubmit="return confirm('Are you sure you want to delete this goal? This action cannot be undone.');">
                <?= \Nexus\Core\Csrf::input() ?>
                <button type="submit" class="holo-btn holo-btn-danger">
                    <i class="fa-solid fa-trash-can"></i>
                    Delete Goal
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Offline Detection
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) handleOffline();
})();

// Form Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to submit.');
        }
    });
});

// Button Touch Feedback
document.querySelectorAll('.holo-btn').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.97)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>

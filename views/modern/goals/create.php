<?php
// Goal Create View - Modern Holographic Glassmorphism Edition
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
                <i class="fa-solid fa-bullseye"></i>
            </div>
            <h1 class="holo-title">Set a Goal</h1>
            <p class="holo-subtitle">Commit to something new. We'll help you find a buddy.</p>
        </div>

        <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/goals/store" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Title -->
            <div class="holo-form-group">
                <label class="holo-label">Goal Title</label>
                <input type="text" name="title" class="holo-input" required
                       placeholder="e.g. Run a 5k Marathon, Learn Spanish">
            </div>

            <!-- Description -->
            <div class="holo-form-group">
                <label class="holo-label">Why is this important?</label>
                <textarea name="description" class="holo-input" rows="4" required
                          placeholder="Describe your goal and what success looks like..."></textarea>
            </div>

            <!-- Target Date -->
            <div class="holo-form-group">
                <label class="holo-label">Target Date (Optional)</label>
                <input type="date" name="deadline" class="holo-input">
            </div>

            <!-- Goal Buddy Card -->
            <div class="holo-buddy-card">
                <label class="holo-buddy-label">
                    <input type="checkbox" name="is_public" value="1" class="holo-checkbox">
                    <div class="holo-buddy-content">
                        <div class="holo-buddy-title">
                            <i class="fa-solid fa-user-group"></i>
                            I want a Goal Buddy
                        </div>
                        <div class="holo-buddy-desc">
                            Make this goal public so other members can offer to be your accountability partner.
                        </div>
                    </div>
                </label>
            </div>

            <div class="holo-actions">
                <button type="submit" class="holo-btn holo-btn-primary">
                    <i class="fa-solid fa-bullseye"></i>
                    Set Goal
                </button>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/goals" class="holo-btn holo-btn-secondary">
                    Cancel
                </a>
            </div>
        </form>
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

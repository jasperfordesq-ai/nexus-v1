<?php
// Edit Resource View - Modern Holographic Glassmorphism Edition
require __DIR__ . '/../../layouts/modern/header.php';
?>

<!-- Offline Banner -->
<div class="holo-offline-banner" id="offlineBanner">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>


<div class="holo-resource-page">
    <!-- Floating Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="holo-glass-card">
        <div class="holo-header">
            <div class="holo-header-icon">
                <i class="fa-solid fa-pen-to-square"></i>
            </div>
            <h1 class="holo-title">Edit Resource</h1>
            <p class="holo-subtitle">Update the document details or category.</p>
        </div>

        <!-- Current File Display -->
        <div class="holo-file-display">
            <div class="holo-file-icon">
                <i class="fa-solid fa-file-lines"></i>
            </div>
            <div class="holo-file-info">
                <div class="holo-file-name"><?= htmlspecialchars($resource['file_name'] ?? $resource['title']) ?></div>
                <div class="holo-file-hint">File cannot be changed. Upload a new resource instead.</div>
            </div>
        </div>

        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources/<?= $resource['id'] ?>/update" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Title -->
            <div class="holo-form-group">
                <label class="holo-label">Document Title</label>
                <input type="text" name="title" class="holo-input" required
                       value="<?= htmlspecialchars($resource['title']) ?>">
            </div>

            <!-- Description -->
            <div class="holo-form-group">
                <label class="holo-label">Description</label>
                <textarea name="description" class="holo-input" rows="3"><?= htmlspecialchars($resource['description']) ?></textarea>
            </div>

            <!-- Category -->
            <div class="holo-form-group">
                <label class="holo-label">Category</label>
                <select name="category_id" class="holo-select">
                    <option value="">-- No Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $resource['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="holo-actions">
                <button type="submit" class="holo-btn holo-btn-primary">
                    <i class="fa-solid fa-check"></i>
                    Update Resource
                </button>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources" class="holo-btn holo-btn-secondary">
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
            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources/<?= $resource['id'] ?>/delete" method="POST"
                  onsubmit="return confirm('Are you sure you want to delete this resource? This action cannot be undone.');">
                <?= \Nexus\Core\Csrf::input() ?>
                <button type="submit" class="holo-btn holo-btn-danger">
                    <i class="fa-solid fa-trash-can"></i>
                    Delete Permanently
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

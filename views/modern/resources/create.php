<?php
// Upload Resource View - Modern Holographic Glassmorphism Edition
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
                <i class="fa-solid fa-cloud-arrow-up"></i>
            </div>
            <h1 class="holo-title">Upload Resource</h1>
            <p class="holo-subtitle">Share useful documents, guides, or forms with the community.</p>
        </div>

        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources/store" method="POST" enctype="multipart/form-data">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Title -->
            <div class="holo-form-group">
                <label class="holo-label">Document Title</label>
                <input type="text" name="title" class="holo-input" required
                       placeholder="e.g. Volunteer Guide 2025">
            </div>

            <!-- Description -->
            <div class="holo-form-group">
                <label class="holo-label">Description</label>
                <textarea name="description" class="holo-input" rows="3"
                          placeholder="Briefly describe what this file contains..."></textarea>
            </div>

            <!-- Category -->
            <div class="holo-form-group">
                <label class="holo-label">Category</label>
                <select name="category_id" class="holo-select">
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- File Upload -->
            <div class="holo-form-group">
                <label class="holo-label">Select File</label>
                <div class="holo-upload-zone" id="uploadZone">
                    <input type="file" name="file" required class="holo-file-input" id="fileInput">
                    <div class="holo-upload-icon">
                        <i class="fa-solid fa-file-arrow-up"></i>
                    </div>
                    <div class="holo-upload-text">Click to select or drag and drop</div>
                    <div class="holo-upload-hint">PDF, DOC, DOCX, ZIP, JPG (Max 5MB)</div>
                    <div class="holo-file-name" id="fileName"></div>
                </div>
            </div>

            <div class="holo-actions">
                <button type="submit" class="holo-btn holo-btn-primary">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    Upload Document
                </button>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources" class="holo-btn holo-btn-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// File Upload Zone
(function() {
    const zone = document.getElementById('uploadZone');
    const input = document.getElementById('fileInput');
    const nameDisplay = document.getElementById('fileName');

    if (!zone || !input) return;

    zone.addEventListener('click', () => input.click());

    zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        zone.classList.add('dragover');
    });

    zone.addEventListener('dragleave', () => {
        zone.classList.remove('dragover');
    });

    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            showFileName(e.dataTransfer.files[0].name);
        }
    });

    input.addEventListener('change', () => {
        if (input.files.length) {
            showFileName(input.files[0].name);
        }
    });

    function showFileName(name) {
        nameDisplay.textContent = name;
        nameDisplay.classList.add('visible');
    }
})();

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
            alert('You are offline. Please connect to the internet to upload.');
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

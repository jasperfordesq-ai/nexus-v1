<?php
// Edit Resource View - Modern Holographic Glassmorphism Edition
require __DIR__ . '/../../layouts/modern/header.php';
?>

<!-- Offline Banner -->
<div class="holo-offline-banner" id="offlineBanner">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<style>
/* ============================================
   RESOURCES EDIT - Holographic Glassmorphism
   Teal Theme (#0d9488)
   ============================================ */

.holo-resource-page {
    min-height: 100vh;
    padding: 180px 20px 60px;
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 50%, #99f6e4 100%);
}

[data-theme="dark"] .holo-resource-page {
    background: linear-gradient(135deg, #042f2e 0%, #0f3d3a 50%, #134e4a 100%);
}

/* Floating Orbs */
.holo-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(60px);
    opacity: 0.5;
    pointer-events: none;
    animation: floatOrb 20s ease-in-out infinite;
}

.holo-orb-1 {
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(13, 148, 136, 0.4) 0%, transparent 70%);
    top: -100px;
    left: -100px;
    animation-delay: 0s;
}

.holo-orb-2 {
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(20, 184, 166, 0.35) 0%, transparent 70%);
    top: 40%;
    right: -80px;
    animation-delay: -7s;
}

.holo-orb-3 {
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(6, 182, 212, 0.3) 0%, transparent 70%);
    bottom: -50px;
    left: 30%;
    animation-delay: -14s;
}

[data-theme="dark"] .holo-orb-1 {
    background: radial-gradient(circle, rgba(13, 148, 136, 0.3) 0%, transparent 70%);
}

[data-theme="dark"] .holo-orb-2 {
    background: radial-gradient(circle, rgba(20, 184, 166, 0.25) 0%, transparent 70%);
}

[data-theme="dark"] .holo-orb-3 {
    background: radial-gradient(circle, rgba(6, 182, 212, 0.2) 0%, transparent 70%);
}

@keyframes floatOrb {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(30px, -30px) scale(1.05); }
    66% { transform: translate(-20px, 20px) scale(0.95); }
}

/* Glass Card */
.holo-glass-card {
    position: relative;
    max-width: 600px;
    margin: 0 auto;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(40px) saturate(180%);
    -webkit-backdrop-filter: blur(40px) saturate(180%);
    border-radius: 28px;
    border: 1px solid rgba(255, 255, 255, 0.5);
    padding: 48px;
    box-shadow:
        0 25px 50px -12px rgba(0, 0, 0, 0.1),
        0 0 0 1px rgba(255, 255, 255, 0.3) inset;
    overflow: hidden;
}

[data-theme="dark"] .holo-glass-card {
    background: rgba(24, 24, 27, 0.6);
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow:
        0 25px 50px -12px rgba(0, 0, 0, 0.4),
        0 0 0 1px rgba(255, 255, 255, 0.05) inset;
}

/* Shimmer Effect */
.holo-glass-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.1),
        transparent
    );
    animation: shimmer 8s ease-in-out infinite;
    pointer-events: none;
}

@keyframes shimmer {
    0%, 100% { left: -100%; }
    50% { left: 100%; }
}

/* Iridescent Top Edge */
.holo-glass-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg,
        transparent,
        rgba(13, 148, 136, 0.6),
        rgba(6, 182, 212, 0.6),
        rgba(20, 184, 166, 0.6),
        transparent
    );
}

/* Header */
.holo-header {
    text-align: center;
    margin-bottom: 40px;
}

.holo-header-icon {
    width: 72px;
    height: 72px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
    box-shadow: 0 10px 30px rgba(13, 148, 136, 0.3);
}

.holo-title {
    font-size: 2rem;
    font-weight: 800;
    margin: 0 0 10px;
    background: linear-gradient(135deg, #0f766e 0%, #0d9488 50%, #14b8a6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
}

[data-theme="dark"] .holo-title {
    background: linear-gradient(135deg, #5eead4 0%, #2dd4bf 50%, #ffffff 100%);
    -webkit-background-clip: text;
    background-clip: text;
}

.holo-subtitle {
    font-size: 1rem;
    color: #64748b;
    margin: 0;
}

[data-theme="dark"] .holo-subtitle {
    color: rgba(255, 255, 255, 0.6);
}

/* Form Styles */
.holo-form-group {
    margin-bottom: 24px;
}

.holo-label {
    display: block;
    font-size: 0.9rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 10px;
    padding-left: 4px;
}

[data-theme="dark"] .holo-label {
    color: rgba(255, 255, 255, 0.9);
}

.holo-input,
.holo-select {
    width: 100%;
    padding: 16px 20px;
    border-radius: 16px;
    border: 2px solid rgba(13, 148, 136, 0.15);
    background: rgba(255, 255, 255, 0.6);
    color: #1e293b;
    font-size: 1rem;
    font-family: inherit;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.holo-input:focus,
.holo-select:focus {
    outline: none;
    border-color: #0d9488;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.1);
}

.holo-input::placeholder {
    color: #94a3b8;
}

[data-theme="dark"] .holo-input,
[data-theme="dark"] .holo-select {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.1);
    color: #f8fafc;
}

[data-theme="dark"] .holo-input:focus,
[data-theme="dark"] .holo-select:focus {
    background: rgba(255, 255, 255, 0.12);
    border-color: #2dd4bf;
    box-shadow: 0 0 0 4px rgba(45, 212, 191, 0.15);
}

[data-theme="dark"] .holo-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

[data-theme="dark"] .holo-select option {
    background: #1e293b;
    color: #f8fafc;
}

textarea.holo-input {
    resize: vertical;
    min-height: 100px;
    line-height: 1.6;
}

/* Current File Display */
.holo-file-display {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    background: linear-gradient(135deg, rgba(13, 148, 136, 0.08) 0%, rgba(20, 184, 166, 0.05) 100%);
    border: 2px solid rgba(13, 148, 136, 0.2);
    border-radius: 16px;
    margin-bottom: 24px;
}

.holo-file-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
    flex-shrink: 0;
}

.holo-file-info {
    flex: 1;
    min-width: 0;
}

.holo-file-name {
    font-size: 0.95rem;
    font-weight: 600;
    color: #0f766e;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

[data-theme="dark"] .holo-file-name {
    color: #2dd4bf;
}

.holo-file-hint {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 4px;
}

[data-theme="dark"] .holo-file-hint {
    color: rgba(255, 255, 255, 0.5);
}

[data-theme="dark"] .holo-file-display {
    background: linear-gradient(135deg, rgba(13, 148, 136, 0.15) 0%, rgba(20, 184, 166, 0.08) 100%);
    border-color: rgba(45, 212, 191, 0.2);
}

/* Buttons */
.holo-actions {
    display: flex;
    gap: 12px;
    margin-top: 32px;
}

.holo-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 16px 28px;
    border-radius: 50px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    border: none;
    font-family: inherit;
    min-height: 48px;
}

.holo-btn-primary {
    flex: 2;
    background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
    color: white;
    box-shadow: 0 8px 24px rgba(13, 148, 136, 0.35);
}

.holo-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(13, 148, 136, 0.45);
}

.holo-btn-primary:active {
    transform: scale(0.98);
}

.holo-btn-secondary {
    flex: 1;
    background: rgba(255, 255, 255, 0.5);
    color: #64748b;
    border: 2px solid rgba(100, 116, 139, 0.2);
}

.holo-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.8);
    color: #475569;
}

[data-theme="dark"] .holo-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.6);
    border-color: rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .holo-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.8);
}

/* Danger Zone */
.holo-danger-zone {
    margin-top: 40px;
    padding-top: 30px;
    border-top: 2px solid rgba(239, 68, 68, 0.15);
}

[data-theme="dark"] .holo-danger-zone {
    border-top-color: rgba(239, 68, 68, 0.2);
}

.holo-danger-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #dc2626;
    margin-bottom: 16px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

[data-theme="dark"] .holo-danger-label {
    color: #f87171;
}

.holo-btn-danger {
    width: 100%;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.3);
}

.holo-btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 28px rgba(239, 68, 68, 0.4);
}

.holo-btn-danger:active {
    transform: scale(0.98);
}

/* Offline Banner */
.holo-offline-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10001;
    padding: 14px 20px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.95rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transform: translateY(-100%);
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.holo-offline-banner.visible {
    transform: translateY(0);
}

/* Mobile Responsive */
@media (max-width: 900px) {
    .holo-resource-page {
        padding: 20px 16px 120px;
    }

    .holo-glass-card {
        padding: 32px 24px;
        border-radius: 24px;
    }

    .holo-title {
        font-size: 1.7rem;
    }

    .holo-header-icon {
        width: 64px;
        height: 64px;
        font-size: 1.5rem;
    }

    .holo-actions {
        flex-direction: column;
    }

    .holo-btn-primary,
    .holo-btn-secondary {
        flex: none;
        width: 100%;
    }
}
</style>

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

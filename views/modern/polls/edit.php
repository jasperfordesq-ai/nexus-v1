<?php
// Phoenix Edit Poll View - Full Holographic Glassmorphism Edition
$hero_title = "Edit Poll";
$hero_subtitle = "Update your community question.";
$hero_gradient = 'htb-hero-gradient-polls';
$hero_type = 'Poll';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<style>
/* ============================================
   HOLOGRAPHIC GLASSMORPHISM EDIT POLL
   Full Modern Design System - Purple/Violet Theme
   ============================================ */

/* Page Background with Ambient Effects */
.holo-poll-page {
    min-height: 100vh;
    padding: 180px 20px 60px;
    position: relative;
    overflow: hidden;
}

@media (max-width: 900px) {
    .holo-poll-page {
        padding: 20px 16px 120px;
    }
}

/* Animated Background Gradient */
.holo-poll-page::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background:
        radial-gradient(ellipse 80% 50% at 20% 40%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 80% 60%, rgba(168, 85, 247, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse 50% 30% at 50% 80%, rgba(99, 102, 241, 0.1) 0%, transparent 50%);
    pointer-events: none;
    z-index: -1;
    animation: holoShift 20s ease-in-out infinite alternate;
}

[data-theme="dark"] .holo-poll-page::before {
    background:
        radial-gradient(ellipse 80% 50% at 20% 40%, rgba(139, 92, 246, 0.2) 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 80% 60%, rgba(168, 85, 247, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse 50% 30% at 50% 80%, rgba(99, 102, 241, 0.12) 0%, transparent 50%);
}

@keyframes holoShift {
    0% { opacity: 1; transform: scale(1); }
    100% { opacity: 0.8; transform: scale(1.1); }
}

/* Floating Orbs */
.holo-orb {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
    z-index: -1;
    opacity: 0.4;
}

.holo-orb-1 {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    top: 10%;
    left: -10%;
    animation: orbFloat1 15s ease-in-out infinite;
}

.holo-orb-2 {
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, #a855f7, #c084fc);
    bottom: 20%;
    right: -5%;
    animation: orbFloat2 18s ease-in-out infinite;
}

.holo-orb-3 {
    width: 250px;
    height: 250px;
    background: linear-gradient(135deg, #6366f1, #818cf8);
    top: 60%;
    left: 30%;
    animation: orbFloat3 12s ease-in-out infinite;
}

@keyframes orbFloat1 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(50px, 30px) scale(1.1); }
}

@keyframes orbFloat2 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-40px, -20px) scale(0.9); }
}

@keyframes orbFloat3 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(30px, -40px) scale(1.05); }
}

/* Main Container */
.holo-poll-container {
    max-width: 720px;
    margin: 0 auto;
    animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Page Header */
.holo-page-header {
    text-align: center;
    margin-bottom: 40px;
}

.holo-page-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 50%, #6d28d9 100%);
    border-radius: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    box-shadow:
        0 20px 40px rgba(139, 92, 246, 0.3),
        0 0 60px rgba(139, 92, 246, 0.2);
    animation: iconPulse 3s ease-in-out infinite;
}

@keyframes iconPulse {
    0%, 100% { box-shadow: 0 20px 40px rgba(139, 92, 246, 0.3), 0 0 60px rgba(139, 92, 246, 0.2); }
    50% { box-shadow: 0 25px 50px rgba(139, 92, 246, 0.4), 0 0 80px rgba(139, 92, 246, 0.3); }
}

.holo-page-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0 0 12px;
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 50%, #6d28d9 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    letter-spacing: -1px;
}

.holo-page-subtitle {
    font-size: 1.1rem;
    color: var(--htb-text-muted, #64748b);
    margin: 0;
}

/* Glass Card */
.holo-glass-card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(40px) saturate(180%);
    -webkit-backdrop-filter: blur(40px) saturate(180%);
    border-radius: 32px;
    border: 1px solid rgba(255, 255, 255, 0.5);
    padding: 48px 40px;
    box-shadow:
        0 25px 50px rgba(0, 0, 0, 0.08),
        0 0 100px rgba(139, 92, 246, 0.08),
        inset 0 0 0 1px rgba(255, 255, 255, 0.3);
    position: relative;
    overflow: hidden;
}

[data-theme="dark"] .holo-glass-card {
    background: rgba(15, 23, 42, 0.6);
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow:
        0 25px 50px rgba(0, 0, 0, 0.4),
        0 0 100px rgba(139, 92, 246, 0.15),
        inset 0 0 0 1px rgba(255, 255, 255, 0.05);
}

/* Card Shimmer Effect */
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
    0% { left: -100%; }
    50%, 100% { left: 100%; }
}

@media (max-width: 768px) {
    .holo-glass-card {
        padding: 32px 24px;
        border-radius: 24px;
    }

    .holo-page-title {
        font-size: 1.8rem;
    }

    .holo-page-icon {
        width: 64px;
        height: 64px;
        font-size: 2rem;
        border-radius: 18px;
    }
}

/* Section Headers */
.holo-section {
    margin-bottom: 28px;
}

/* Form Labels */
.holo-label {
    display: block;
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--htb-text-main, #1e293b);
    margin-bottom: 10px;
}

[data-theme="dark"] .holo-label {
    color: #e2e8f0;
}

.holo-label-optional {
    font-weight: 400;
    font-size: 0.8rem;
    color: var(--htb-text-muted, #94a3b8);
    margin-left: 6px;
}

/* Input Fields */
.holo-input,
.holo-textarea {
    width: 100%;
    padding: 16px 20px;
    border-radius: 16px;
    font-size: 1rem;
    font-family: inherit;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid rgba(0, 0, 0, 0.06);
    background: rgba(255, 255, 255, 0.6);
    color: var(--htb-text-main, #0f172a);
    outline: none;
    box-sizing: border-box;
}

[data-theme="dark"] .holo-input,
[data-theme="dark"] .holo-textarea {
    background: rgba(0, 0, 0, 0.2);
    border-color: rgba(255, 255, 255, 0.08);
    color: #f8fafc;
}

.holo-input:focus,
.holo-textarea:focus {
    border-color: #8b5cf6;
    background: rgba(255, 255, 255, 0.9);
    box-shadow:
        0 0 0 4px rgba(139, 92, 246, 0.1),
        0 8px 20px rgba(139, 92, 246, 0.1);
}

[data-theme="dark"] .holo-input:focus,
[data-theme="dark"] .holo-textarea:focus {
    background: rgba(0, 0, 0, 0.3);
    border-color: #a78bfa;
    box-shadow:
        0 0 0 4px rgba(139, 92, 246, 0.15),
        0 8px 20px rgba(139, 92, 246, 0.15);
}

.holo-input::placeholder,
.holo-textarea::placeholder {
    color: var(--htb-text-muted, #94a3b8);
}

.holo-textarea {
    resize: vertical;
    min-height: 120px;
    line-height: 1.6;
}

/* Date Input Fix */
.holo-input[type="date"] {
    color-scheme: light;
}

[data-theme="dark"] .holo-input[type="date"] {
    color-scheme: dark;
}

/* Alert Box */
.holo-alert {
    padding: 20px 24px;
    border-radius: 16px;
    display: flex;
    gap: 16px;
    align-items: flex-start;
    margin-bottom: 28px;
}

.holo-alert-warning {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(251, 191, 36, 0.08) 100%);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.holo-alert-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}

.holo-alert-warning .holo-alert-icon {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.holo-alert-content {
    flex: 1;
}

.holo-alert-title {
    font-weight: 700;
    font-size: 0.95rem;
    color: #b45309;
    margin-bottom: 4px;
}

[data-theme="dark"] .holo-alert-title {
    color: #fbbf24;
}

.holo-alert-text {
    font-size: 0.9rem;
    color: #92400e;
    line-height: 1.5;
}

[data-theme="dark"] .holo-alert-text {
    color: #fcd34d;
    opacity: 0.8;
}

/* Divider */
.holo-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(139, 92, 246, 0.2), transparent);
    margin: 32px 0;
    border: none;
}

/* Action Buttons */
.holo-actions {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

@media (max-width: 600px) {
    .holo-actions {
        flex-direction: column;
    }
}

.holo-btn {
    padding: 16px 28px;
    border-radius: 14px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
}

.holo-btn-primary {
    flex: 2;
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 50%, #6d28d9 100%);
    color: white;
    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.3);
}

.holo-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(139, 92, 246, 0.4);
}

.holo-btn-secondary {
    flex: 1;
    background: rgba(0, 0, 0, 0.03);
    border: 2px solid rgba(0, 0, 0, 0.08);
    color: var(--htb-text-main, #374151);
}

[data-theme="dark"] .holo-btn-secondary {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

.holo-btn-secondary:hover {
    background: rgba(0, 0, 0, 0.06);
    transform: translateY(-2px);
}

[data-theme="dark"] .holo-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
}

/* Danger Zone */
.holo-danger-zone {
    margin-top: 32px;
    padding-top: 32px;
    border-top: 1px solid rgba(239, 68, 68, 0.2);
}

.holo-btn-danger {
    width: 100%;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 8px 24px rgba(239, 68, 68, 0.25);
}

.holo-btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(239, 68, 68, 0.35);
}

/* Prevent iOS Zoom */
@media (max-width: 768px) {
    .holo-input,
    .holo-textarea {
        font-size: 16px !important;
    }
}

/* Focus Visible */
.holo-input:focus-visible,
.holo-textarea:focus-visible,
.holo-btn:focus-visible {
    outline: 3px solid #8b5cf6;
    outline-offset: 2px;
}

/* Offline Banner */
.holo-offline-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10001;
    padding: 12px 20px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.9rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transform: translateY(-100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.holo-offline-banner.visible {
    transform: translateY(0);
}
</style>

<!-- Offline Banner -->
<div class="holo-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="holo-poll-page">
    <!-- Floating Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="holo-poll-container">
        <!-- Page Header -->
        <div class="holo-page-header">
            <div class="holo-page-icon">✏️</div>
            <h1 class="holo-page-title">Edit Poll</h1>
            <p class="holo-page-subtitle">Update your community question</p>
        </div>

        <!-- Glass Card Form -->
        <div class="holo-glass-card">
            <form action="<?= $basePath ?>/polls/<?= $poll['id'] ?>/update" method="POST" id="editPollForm">
                <?= \Nexus\Core\Csrf::input() ?>

                <!-- Question -->
                <div class="holo-section">
                    <label class="holo-label" for="question">Question</label>
                    <input type="text" name="question" id="question" class="holo-input" value="<?= htmlspecialchars($poll['question']) ?>" placeholder="Enter your question here..." required>
                </div>

                <!-- Description -->
                <div class="holo-section">
                    <label class="holo-label" for="description">Description <span class="holo-label-optional">(Optional)</span></label>
                    <textarea name="description" id="description" class="holo-textarea" placeholder="Add more context..."><?= htmlspecialchars($poll['description']) ?></textarea>
                </div>

                <!-- End Date -->
                <div class="holo-section">
                    <label class="holo-label" for="end_date">End Date <span class="holo-label-optional">(Optional)</span></label>
                    <input type="date" name="end_date" id="end_date" class="holo-input" value="<?= $poll['end_date'] ? date('Y-m-d', strtotime($poll['end_date'])) : '' ?>">
                </div>

                <!-- Warning Alert -->
                <div class="holo-alert holo-alert-warning">
                    <div class="holo-alert-icon">
                        <i class="fa-solid fa-exclamation"></i>
                    </div>
                    <div class="holo-alert-content">
                        <div class="holo-alert-title">Options cannot be changed</div>
                        <div class="holo-alert-text">
                            You can only edit the question text and deadline. To change voting options, please create a new poll to ensure vote integrity.
                        </div>
                    </div>
                </div>

                <hr class="holo-divider">

                <!-- Action Buttons -->
                <div class="holo-actions">
                    <button type="submit" class="holo-btn holo-btn-primary" id="submitBtn">
                        <i class="fa-solid fa-check"></i>
                        Save Changes
                    </button>
                    <a href="<?= $basePath ?>/polls/<?= $poll['id'] ?>" class="holo-btn holo-btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i>
                        Cancel
                    </a>
                </div>

                <!-- Danger Zone -->
                <div class="holo-danger-zone">
                    <button type="submit" formaction="<?= $basePath ?>/polls/<?= $poll['id'] ?>/delete" class="holo-btn holo-btn-danger" onclick="return confirm('Are you sure? This will permanently delete the poll and all votes.')">
                        <i class="fa-solid fa-trash"></i>
                        Delete Poll
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Offline Indicator
(function initOfflineIndicator() {
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

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Form Submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editPollForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            if (!navigator.onLine) {
                e.preventDefault();
                alert('You are offline. Please connect to the internet to save changes.');
                return;
            }

            // Only show loading for save (not delete)
            if (e.submitter === submitBtn) {
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
                submitBtn.disabled = true;
            }
        });
    }

    // Touch feedback
    document.querySelectorAll('.holo-btn').forEach(el => {
        el.addEventListener('pointerdown', () => el.style.transform = 'scale(0.97)');
        el.addEventListener('pointerup', () => el.style.transform = '');
        el.addEventListener('pointerleave', () => el.style.transform = '');
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    let metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        metaTheme = document.createElement('meta');
        metaTheme.name = 'theme-color';
        document.head.appendChild(metaTheme);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        metaTheme.setAttribute('content', isDark ? '#0f172a' : '#8b5cf6');
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>

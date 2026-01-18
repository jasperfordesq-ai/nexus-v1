<?php
// Invite Members to Hub - Mobile Optimized
$hero_title = "Invite Members";
$hero_subtitle = "Grow your hub by inviting community members.";
$hero_gradient = 'htb-hero-gradient-hub';
$hero_type = 'Community';

require __DIR__ . '/../../layouts/modern/header.php';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<style>
    /* ============================================
       GOLD STANDARD - Native App Features
       ============================================ */

    /* Offline Banner */
    .offline-banner {
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
    .offline-banner.visible {
        transform: translateY(0);
    }

    /* Content Reveal Animation */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .htb-card {
        animation: fadeInUp 0.4s ease-out;
    }

    /* Button Press States */
    .invite-submit:active,
    button:active {
        transform: scale(0.96) !important;
        transition: transform 0.1s ease !important;
    }

    /* Touch Targets - WCAG 2.1 AA (44px minimum) */
    .invite-submit,
    .invite-search,
    .user-item,
    button {
        min-height: 44px;
    }

    .invite-search {
        font-size: 16px !important; /* Prevent iOS zoom */
    }

    /* Focus Visible */
    .invite-submit:focus-visible,
    .invite-search:focus-visible,
    button:focus-visible,
    a:focus-visible,
    input:focus-visible {
        outline: 3px solid rgba(219, 39, 119, 0.5);
        outline-offset: 2px;
    }

    /* Smooth Scroll */
    html {
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }

    /* Mobile Responsive - Gold Standard */
    @media (max-width: 768px) {
        .invite-submit,
        .user-item,
        button {
            min-height: 48px;
        }
    }
</style>

<style>
    .invite-wrapper {
        padding-top: 120px;
        padding-bottom: 40px;
    }

    .invite-wrapper .htb-header-box {
        margin-bottom: 20px;
    }

    .invite-wrapper .htb-card {
        padding: 24px;
    }

    .invite-search {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 1rem;
        font-family: inherit;
        box-sizing: border-box;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        background: white;
        color: var(--htb-text-main);
        -webkit-appearance: none;
    }

    .invite-search:focus {
        outline: none;
        border-color: #db2777;
        box-shadow: 0 0 0 4px rgba(219, 39, 119, 0.1);
    }

    .user-list {
        max-height: 400px;
        overflow-y: auto;
        margin: 20px 0;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
    }

    .user-item {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        border-bottom: 1px solid #f3f4f6;
        cursor: pointer;
        transition: background 0.15s ease;
    }

    .user-item:last-child {
        border-bottom: none;
    }

    .user-item:hover {
        background: #fdf2f8;
    }

    .user-item.selected {
        background: #fce7f3;
    }

    .user-item input[type="checkbox"] {
        margin-right: 12px;
        width: 20px;
        height: 20px;
        accent-color: #db2777;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 12px;
        background: #e5e7eb;
    }

    .user-info {
        flex: 1;
    }

    .user-name {
        font-weight: 600;
        color: var(--htb-text-main);
    }

    .user-email {
        font-size: 0.85rem;
        color: #6b7280;
    }

    .invite-submit {
        width: 100%;
        padding: 16px;
        font-size: 1.05rem;
        border-radius: 14px;
        background: linear-gradient(135deg, #db2777, #ec4899);
        border: none;
        color: white;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(219, 39, 119, 0.3);
        transition: all 0.2s ease;
        -webkit-tap-highlight-color: transparent;
    }

    .invite-submit:disabled {
        background: #9ca3af;
        box-shadow: none;
        cursor: not-allowed;
    }

    .invite-submit:active:not(:disabled) {
        transform: scale(0.98);
    }

    .selected-count {
        text-align: center;
        margin-bottom: 16px;
        font-weight: 600;
        color: #db2777;
    }

    .no-users {
        text-align: center;
        padding: 40px 20px;
        color: #6b7280;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #db2777;
        text-decoration: none;
        font-weight: 600;
        margin-bottom: 20px;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    .error-message {
        background: #fef2f2;
        color: #dc2626;
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-weight: 500;
    }

    [data-theme="dark"] .invite-search {
        background: rgba(30, 41, 59, 0.8);
        border-color: rgba(255, 255, 255, 0.15);
        color: #f1f5f9;
    }

    [data-theme="dark"] .user-list {
        border-color: rgba(255, 255, 255, 0.1);
    }

    [data-theme="dark"] .user-item {
        border-color: rgba(255, 255, 255, 0.05);
    }

    [data-theme="dark"] .user-item:hover {
        background: rgba(219, 39, 119, 0.1);
    }

    [data-theme="dark"] .user-item.selected {
        background: rgba(219, 39, 119, 0.2);
    }

    @media (max-width: 768px) {
        .invite-wrapper {
            padding-top: 100px;
            padding-bottom: 100px;
        }

        .invite-wrapper .htb-card {
            padding: 20px 16px;
            border-radius: 16px;
        }

        .invite-search {
            padding: 12px 14px;
            font-size: 16px;
        }

        .user-list {
            max-height: 350px;
        }
    }
</style>

<div class="htb-container-focused invite-wrapper">

    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>?tab=settings" class="back-link">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to <?= htmlspecialchars($group['name']) ?>
    </a>

    <div class="htb-header-box">
        <h1>Invite Members</h1>
        <p>Select members to invite to <strong><?= htmlspecialchars($group['name']) ?></strong></p>
    </div>

    <?php if (isset($_GET['err']) && $_GET['err'] === 'no_users'): ?>
        <div class="error-message">Please select at least one member to invite.</div>
    <?php endif; ?>

    <div class="htb-card">
        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/invite" method="POST" id="inviteForm">
            <?= Nexus\Core\Csrf::input() ?>

            <input type="text" class="invite-search" id="userSearch" placeholder="Search members by name...">

            <?php if (empty($availableUsers)): ?>
                <div class="no-users">
                    <p>All community members are already in this hub!</p>
                </div>
            <?php else: ?>
                <div class="user-list" id="userList">
                    <?php foreach ($availableUsers as $user): ?>
                        <label class="user-item" data-name="<?= strtolower(htmlspecialchars($user['name'])) ?>">
                            <input type="checkbox" name="user_ids[]" value="<?= $user['id'] ?>">
                            <?php
                                $avatarSrc = $user['avatar_url'] ?: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 128 128'%3E%3Ccircle cx='64' cy='64' r='64' fill='%23e2e8f0'/%3E%3Ccircle cx='64' cy='48' r='20' fill='%2394a3b8'/%3E%3Cellipse cx='64' cy='96' rx='32' ry='24' fill='%2394a3b8'/%3E%3C/svg%3E";
                            ?>
                            <img src="<?= htmlspecialchars($avatarSrc) ?>" loading="lazy"
                                 alt="" class="user-avatar">
                            <div class="user-info">
                                <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                <?php if (!empty($user['email'])): ?>
                                    <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
                                <?php endif; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="selected-count" id="selectedCount">0 members selected</div>

                <!-- Add Directly Option -->
                <div style="margin: 20px 0; padding: 16px; background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 2px solid #86efac; border-radius: 12px;">
                    <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                        <input type="checkbox" name="add_directly" value="1" id="addDirectlyCheckbox" style="width: 20px; height: 20px; margin-top: 2px; accent-color: #10b981;">
                        <div>
                            <div style="font-weight: 600; color: #166534;">Add directly to hub</div>
                            <div style="font-size: 0.85rem; color: #15803d; margin-top: 4px;">
                                Skip the invitation step and add selected members immediately. They'll receive a notification that they've been added.
                            </div>
                        </div>
                    </label>
                </div>

                <button type="submit" class="invite-submit" id="submitBtn" disabled>Send Invitations</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('userSearch');
    const userList = document.getElementById('userList');
    const selectedCount = document.getElementById('selectedCount');
    const submitBtn = document.getElementById('submitBtn');
    const checkboxes = document.querySelectorAll('input[name="user_ids[]"]');

    // Search filter
    if (searchInput && userList) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const items = userList.querySelectorAll('.user-item');

            items.forEach(item => {
                const name = item.dataset.name;
                item.style.display = name.includes(query) ? 'flex' : 'none';
            });
        });
    }

    // Selection count
    function updateCount() {
        const checked = document.querySelectorAll('input[name="user_ids[]"]:checked').length;
        if (selectedCount) {
            selectedCount.textContent = checked + ' member' + (checked !== 1 ? 's' : '') + ' selected';
        }
        if (submitBtn) {
            submitBtn.disabled = checked === 0;
        }
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            this.closest('.user-item').classList.toggle('selected', this.checked);
            updateCount();
        });
    });

    // Toggle button text based on "Add directly" checkbox
    const addDirectlyCheckbox = document.getElementById('addDirectlyCheckbox');
    if (addDirectlyCheckbox && submitBtn) {
        addDirectlyCheckbox.addEventListener('change', function() {
            if (this.checked) {
                submitBtn.textContent = 'Add Members Now';
                submitBtn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
            } else {
                submitBtn.textContent = 'Send Invitations';
                submitBtn.style.background = 'linear-gradient(135deg, #db2777, #ec4899)';
            }
        });
    }
});

// ============================================
// GOLD STANDARD - Native App Features
// ============================================

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

// Form Submission Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to send invitations.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('.invite-submit, button').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#db2777';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#db2777');
        }
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

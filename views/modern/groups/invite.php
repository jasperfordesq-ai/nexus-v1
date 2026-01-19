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

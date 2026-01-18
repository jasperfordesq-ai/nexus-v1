<?php
// Goal Detail View - High-End Adaptive Holographic Glassmorphism Edition
// ISOLATED LAYOUT: Uses #unique-glass-page-wrapper and html[data-theme] selectors.

$isAuthor = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $goal['user_id'];
$hasMentor = !empty($goal['mentor_id']);
$isMentor = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $goal['mentor_id'];

require __DIR__ . '/../../layouts/header.php';
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

    .glass-box {
        animation: fadeInUp 0.4s ease-out;
    }

    /* Button Press States */
    .glass-pill-btn:active,
    button:active {
        transform: scale(0.96) !important;
        transition: transform 0.1s ease !important;
    }

    /* Touch Targets - WCAG 2.1 AA (44px minimum) */
    .glass-pill-btn,
    button {
        min-height: 44px;
    }

    /* Focus Visible */
    .glass-pill-btn:focus-visible,
    button:focus-visible,
    a:focus-visible {
        outline: 3px solid rgba(219, 39, 119, 0.5);
        outline-offset: 2px;
    }

    /* Smooth Scroll */
    html {
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }

    /* Mobile Responsive Enhancements */
    @media (max-width: 768px) {
        .glass-pill-btn,
        button {
            min-height: 48px;
        }
    }
</style>

<style>
    /* SCOPED STYLES for #unique-glass-page-wrapper ONLY */
    #unique-glass-page-wrapper {
        /* 
       --- DEFAULT / LIGHT MODE VARIABLES (Fallback) --- 
    */
        --glass-bg: rgba(255, 255, 255, 0.55);
        --glass-border: rgba(255, 255, 255, 0.4);
        --text-color: #1e293b;
        --text-muted: #475569;
        --accent-color: #db2777;
        /* Pink/Rose for Goals */
        --success-color: #059669;
        --heading-gradient: linear-gradient(135deg, #be185d 0%, #db2777 100%);

        /* Subtle iridescent shadow for light mode */
        --box-shadow:
            0 8px 32px 0 rgba(31, 38, 135, 0.1),
            inset 0 0 0 1px rgba(255, 255, 255, 0.2);

        --holographic-glow: 0 0 20px rgba(255, 0, 128, 0.05), 0 0 40px rgba(255, 100, 200, 0.05);

        /* Form Fields & Pills */
        --pill-bg: rgba(255, 255, 255, 0.25);
        --pill-border: 1px solid rgba(255, 255, 255, 0.4);
        --pill-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);

        /* Status Card Variables (Light) */
        --status-blue-bg: rgba(59, 130, 246, 0.15);
        --status-blue-border: rgba(59, 130, 246, 0.3);
        --status-blue-text: #1d4ed8;

        --status-amber-bg: rgba(245, 158, 11, 0.15);
        --status-amber-border: rgba(245, 158, 11, 0.3);
        --status-amber-text: #b45309;

        --status-gray-bg: rgba(148, 163, 184, 0.15);
        --status-gray-border: rgba(148, 163, 184, 0.3);
        --status-gray-text: #475569;

        /* Layout & Alignment */
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        min-height: 80vh;
        padding: 50px 20px;
        box-sizing: border-box;

        /* Typography Defaults */
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--text-color);
        line-height: 1.6;

        /* Background: Adaptive Mesh Gradient (Pink/Rose for Goals) */
        background: radial-gradient(circle at 50% 0%, #fdf2f8 0%, #fce7f3 100%);
        background-attachment: fixed;
    }

    /* 
   --- THEME SYNC SELECTORS --- 
   Using html[data-theme] as requested 
*/

    /* LIGHT MODE EXPLICIT */
    html[data-theme="light"] #unique-glass-page-wrapper {
        --glass-bg: rgba(255, 255, 255, 0.55);
        --glass-border: rgba(255, 255, 255, 0.4);
        --text-color: #1e293b;
        --text-muted: #475569;
        --heading-gradient: linear-gradient(135deg, #be185d 0%, #db2777 100%);
        --box-shadow:
            0 8px 32px 0 rgba(31, 38, 135, 0.1),
            inset 0 0 0 1px rgba(255, 255, 255, 0.2);
        --holographic-glow: 0 0 20px rgba(255, 0, 128, 0.05), 0 0 40px rgba(255, 100, 200, 0.05);
        --pill-bg: rgba(255, 255, 255, 0.25);

        --status-blue-bg: rgba(59, 130, 246, 0.15);
        --status-blue-border: rgba(59, 130, 246, 0.3);
        --status-blue-text: #1d4ed8;

        --status-amber-bg: rgba(245, 158, 11, 0.15);
        --status-amber-border: rgba(245, 158, 11, 0.3);
        --status-amber-text: #b45309;

        --status-gray-bg: rgba(148, 163, 184, 0.15);
        --status-gray-border: rgba(148, 163, 184, 0.3);
        --status-gray-text: #475569;

        background: radial-gradient(circle at 50% 0%, #fdf2f8 0%, #fce7f3 100%);
    }

    /* DARK MODE EXPLICIT */
    html[data-theme="dark"] #unique-glass-page-wrapper {
        --glass-bg: rgba(24, 24, 27, 0.45);
        --glass-border: rgba(255, 255, 255, 0.15);
        --text-color: #fce7f3;
        --text-muted: rgba(255, 255, 255, 0.7);
        --accent-color: #f472b6;
        --heading-gradient: linear-gradient(135deg, #ffffff 0%, #fbcfe8 100%);

        /* Vivid Dark Mode Shadows */
        --box-shadow:
            0 8px 32px 0 rgba(0, 0, 0, 0.4),
            inset 0 0 0 1px rgba(255, 255, 255, 0.05);

        --holographic-glow:
            0 0 30px rgba(236, 72, 153, 0.15),
            0 0 60px rgba(168, 85, 247, 0.15);

        --pill-bg: rgba(24, 24, 27, 0.65);
        --pill-border: 2px solid rgba(255, 255, 255, 0.1);

        /* Dark Mode Status Cards */
        --status-blue-bg: rgba(30, 58, 138, 0.3);
        --status-blue-border: rgba(59, 130, 246, 0.3);
        --status-blue-text: #93c5fd;

        --status-amber-bg: rgba(120, 53, 15, 0.3);
        --status-amber-border: rgba(245, 158, 11, 0.3);
        --status-amber-text: #fcd34d;

        --status-gray-bg: rgba(30, 41, 59, 0.3);
        --status-gray-border: rgba(148, 163, 184, 0.2);
        --status-gray-text: #94a3b8;

        background: radial-gradient(circle at 50% 0%, rgb(39, 10, 25) 0%, rgb(20, 10, 20) 90%);
    }


    /* The Glass Container */
    #unique-glass-page-wrapper .glass-box {
        position: relative;
        width: 95%;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;

        /* High-End Glass Effect */
        background: var(--glass-bg);
        backdrop-filter: blur(25px) saturate(200%);
        -webkit-backdrop-filter: blur(25px) saturate(200%);

        /* Holographic Borders & Shadows */
        border-radius: 28px;
        border: 1px solid var(--glass-border);
        box-shadow: var(--box-shadow), var(--holographic-glow);

        padding: 50px;
        overflow: hidden;
        transition: background 0.3s ease, box-shadow 0.3s ease;
    }

    /* Iridescent Top Edge */
    #unique-glass-page-wrapper .glass-box::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(244, 114, 182, 0.6), rgba(168, 85, 247, 0.6), transparent);
        z-index: 10;
    }

    /* Header Section */
    #unique-glass-page-wrapper .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 40px;
        flex-wrap: wrap;
        gap: 20px;
    }

    #unique-glass-page-wrapper .back-link {
        color: var(--text-muted);
        text-decoration: none;
        font-weight: 500;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        margin-bottom: 10px;
        transition: color 0.2s;
    }

    #unique-glass-page-wrapper .back-link:hover {
        color: var(--accent-color);
    }

    #unique-glass-page-wrapper h1 {
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--text-color);
        margin: 5px 0 0 0;
        letter-spacing: -0.02em;
        line-height: 1.2;
    }

    #unique-glass-page-wrapper .status-badge {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: var(--pill-bg);
        border: var(--pill-border);
        color: var(--accent-color);
        margin-bottom: 15px;
    }

    /* Content Area */
    #unique-glass-page-wrapper .goal-description {
        font-size: 1.15rem;
        line-height: 1.8;
        color: var(--text-color);
        margin-bottom: 50px;
        white-space: pre-wrap;
        opacity: 0.9;
    }

    /* Buddy Status Card */
    #unique-glass-page-wrapper .status-card {
        border-radius: 20px;
        padding: 30px;
        display: flex;
        flex-direction: column;
        /* Nested Blur not always needed if background is solid enough, but consistent here */
        backdrop-filter: blur(10px);
        transition: all 0.3s ease;
    }

    /* Status: Matched (Blue) */
    #unique-glass-page-wrapper .status-card.status-matched {
        background: var(--status-blue-bg);
        border: 1px solid var(--status-blue-border);
        color: var(--status-blue-text);
    }

    /* Status: Looking (Amber) */
    #unique-glass-page-wrapper .status-card.status-looking {
        background: var(--status-amber-bg);
        border: 1px solid var(--status-amber-border);
        color: var(--status-amber-text);
    }

    /* Status: Private (Gray) */
    #unique-glass-page-wrapper .status-card.status-private {
        background: var(--status-gray-bg);
        border: 1px solid var(--status-gray-border);
        color: var(--status-gray-text);
    }


    /* Action Buttons */
    #unique-glass-page-wrapper .glass-pill-btn {
        padding: 10px 24px;
        border-radius: 50px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        font-size: 0.95rem;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    #unique-glass-page-wrapper .glass-pill-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
    }

    /* Variants */
    #unique-glass-page-wrapper .btn-primary {
        background: linear-gradient(135deg, #db2777 0%, #be185d 100%);
        color: white;
    }

    #unique-glass-page-wrapper .btn-secondary {
        background: var(--pill-bg);
        border: var(--pill-border);
        color: var(--text-color);
    }

    #unique-glass-page-wrapper .btn-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }


    /* Responsive */
    @media (max-width: 768px) {
        #unique-glass-page-wrapper {
            padding: 20px 10px;
        }

        #unique-glass-page-wrapper .glass-box {
            width: 100%;
            padding: 30px 20px;
            border-radius: 20px;
        }

        #unique-glass-page-wrapper .page-header {
            flex-direction: column;
        }
    }
</style>

<div id="unique-glass-page-wrapper">
    <div class="glass-box">

        <!-- Header -->
        <div class="page-header">
            <div>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/goals" class="back-link">
                    <span>←</span> &nbsp; Back to Goals
                </a>
                <br>
                <div class="status-badge">
                    <?= $goal['status'] === 'completed' ? '✅ COMPLETED' : '🎯 GOAL' ?>
                </div>
                <h1><?= htmlspecialchars($goal['title']) ?></h1>
            </div>

            <?php if ($isAuthor): ?>
                <div style="display:flex; gap:10px; flex-wrap: wrap;">
                    <?php if ($goal['status'] === 'active'): ?>
                        <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/goals/<?= $goal['id'] ?>/complete" method="POST" onsubmit="return confirm('Mark as achieved? Great job!')">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <button type="submit" class="glass-pill-btn btn-success">✅ Mark Complete</button>
                        </form>
                    <?php endif; ?>
                    <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/goals/<?= $goal['id'] ?>/edit" class="glass-pill-btn btn-secondary">⚙️ Edit</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Description -->
        <div class="goal-description"><?= htmlspecialchars($goal['description']) ?></div>

        <!-- Buddy Status Section -->
        <?php if ($hasMentor): ?>
            <!-- Matched State -->
            <div class="status-card status-matched">
                <h3 style="margin: 0 0 10px 0; font-size: 1.25rem;">🤝 Matched with Buddy</h3>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="font-size: 2rem;">🎉</div>
                    <div>
                        <div style="opacity: 0.8; font-size: 0.9rem;">Accountability Partner</div>
                        <div style="font-weight: 700; font-size: 1.1rem;"><?= htmlspecialchars($goal['mentor_name']) ?></div>
                    </div>
                    <?php if ($isAuthor || $isMentor): ?>
                        <div style="margin-left: auto;">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages?create=1&to=<?= $isAuthor ? $goal['mentor_id'] : $goal['user_id'] ?>" class="glass-pill-btn btn-primary" style="padding: 8px 16px; font-size: 0.85rem;">Message</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($goal['is_public']): ?>
            <!-- Looking State -->
            <div class="status-card status-looking">
                <div style="text-align: center;">
                    <div style="font-size: 2rem; margin-bottom: 10px;">🔍</div>
                    <h3 style="margin: 0 0 10px 0; font-size: 1.25rem;">Looking for a Buddy</h3>
                    <p style="margin: 0 0 20px 0; opacity: 0.9;">This goal is public! Waiting for a community member to support you.</p>

                    <?php if (!$isAuthor && isset($_SESSION['user_id'])): ?>
                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/goals/buddy" method="POST" onsubmit="return confirm('Are you sure you want to be the accountability partner for this goal?');">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="goal_id" value="<?= $goal['id'] ?>">
                            <button class="glass-pill-btn btn-primary">Become Buddy 🤝</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- Private State -->
            <div class="status-card status-private">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="font-size: 1.5rem;">🔒</div>
                    <div>
                        <strong style="display:block;">Private Goal</strong>
                        <span style="opacity: 0.8; font-size: 0.9rem;">Only you can see this goal.</span>
                    </div>
                </div>
            </div>

        <?php endif; ?>

        <!-- Social Engagement Section - Master Platform Social Media Module -->
        <?php
        $goalId = $goal['id'];
        $likesCount = $likesCount ?? 0;
        $commentsCount = $commentsCount ?? 0;
        $isLiked = $isLiked ?? false;
        $isLoggedIn = $isLoggedIn ?? !empty($_SESSION['user_id']);
        ?>
        <div style="margin-top: 40px; padding-top: 30px; border-top: 1px solid var(--glass-border);">
            <!-- Like & Comment Buttons -->
            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 20px;">
                <button id="like-btn" onclick="goalToggleLike()" class="glass-pill-btn <?= $isLiked ? 'btn-primary' : 'btn-secondary' ?>" style="<?= $isLiked ? '' : '' ?>">
                    <i class="<?= $isLiked ? 'fa-solid' : 'fa-regular' ?> fa-heart" id="like-icon"></i>
                    <span id="like-count"><?= $likesCount ?></span>
                    <span><?= $likesCount === 1 ? 'Like' : 'Likes' ?></span>
                </button>
                <button onclick="goalToggleComments()" class="glass-pill-btn btn-secondary">
                    <i class="fa-regular fa-comment"></i>
                    <span id="comment-count"><?= $commentsCount ?></span>
                    <span><?= $commentsCount === 1 ? 'Comment' : 'Comments' ?></span>
                </button>
                <?php if ($isLoggedIn): ?>
                <button onclick="shareToFeed()" class="glass-pill-btn btn-secondary">
                    <i class="fa-solid fa-share"></i> Share
                </button>
                <?php endif; ?>
            </div>

            <!-- Comments Section (Hidden by Default) -->
            <div id="comments-section" style="display: none; margin-top: 20px;">
                <?php if ($isLoggedIn): ?>
                <form onsubmit="goalSubmitComment(event)" style="margin-bottom: 20px;">
                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                        <img src="<?= $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp' ?>" loading="lazy" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid var(--glass-border);">
                        <div style="flex: 1;">
                            <textarea id="comment-input" placeholder="Write a comment..." style="width: 100%; min-height: 80px; padding: 12px; border-radius: 12px; border: 1px solid var(--glass-border); background: var(--pill-bg); color: var(--text-color); font-size: 0.95rem; resize: vertical;"></textarea>
                            <button type="submit" class="glass-pill-btn btn-primary" style="margin-top: 10px;">Post Comment</button>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <p style="text-align: center; color: var(--text-muted); padding: 20px;">
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" style="color: var(--accent-color);">Log in</a> to leave a comment.
                </p>
                <?php endif; ?>
                <div id="comments-list">
                    <p style="text-align: center; color: var(--text-muted);">Loading comments...</p>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
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
            alert('You are offline. Please connect to the internet to submit.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('.glass-pill-btn, button').forEach(btn => {
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

// ============================================
// MASTER PLATFORM SOCIAL MEDIA MODULE
// ============================================
(function() {
    const goalId = <?= $goalId ?>;
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
    let isLiked = <?= $isLiked ? 'true' : 'false' ?>;
    let commentsLoaded = false;
    let availableReactions = [];

    const API_BASE = '<?= \Nexus\Core\TenantContext::getBasePath() ?>/api/social';

    // Unique function names to avoid conflict with social-interactions.js
    window.goalToggleLike = async function() {
        <?php if (!$isLoggedIn): ?>
        window.location.href = '<?= Nexus\Core\TenantContext::getBasePath() ?>/login';
        return;
        <?php endif; ?>

        const btn = document.getElementById('like-btn');
        const icon = document.getElementById('like-icon');
        const countEl = document.getElementById('like-count');

        btn.disabled = true;

        try {
            const response = await fetch(API_BASE + '/like', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    target_type: 'goal',
                    target_id: goalId
                })
            });

            if (!response.ok) {
                alert('Like failed. Please try again.');
                return;
            }

            const data = await response.json();

            if (data.error) {
                if (data.redirect) window.location.href = data.redirect;
                else alert('Like failed: ' + data.error);
                return;
            }

            isLiked = (data.status === 'liked');
            countEl.textContent = data.likes_count;

            if (isLiked) {
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-primary');
                icon.classList.remove('fa-regular');
                icon.classList.add('fa-solid');
            } else {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-secondary');
                icon.classList.remove('fa-solid');
                icon.classList.add('fa-regular');
            }

        } catch (err) {
            console.error('Like error:', err);
        } finally {
            btn.disabled = false;
        }
    };

    window.goalToggleComments = function() {
        // Check if mobile (screen width <= 768px or touch device)
        const isMobile = window.innerWidth <= 768 || ('ontouchstart' in window);

        if (isMobile && typeof openMobileCommentSheet === 'function') {
            // Use mobile drawer on mobile devices
            openMobileCommentSheet('goal', goalId, '');
            return;
        }

        // Desktop: use inline comments section
        const section = document.getElementById('comments-section');
        const isHidden = section.style.display === 'none';

        section.style.display = isHidden ? 'block' : 'none';

        if (isHidden && !commentsLoaded) {
            loadComments();
        }
    };

    async function loadComments() {
        const list = document.getElementById('comments-list');
        list.innerHTML = '<p style="text-align: center; color: var(--text-muted);">Loading comments...</p>';

        try {
            const response = await fetch(API_BASE + '/comments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'fetch',
                    target_type: 'goal',
                    target_id: goalId
                })
            });

            if (!response.ok) {
                list.innerHTML = '<p style="text-align: center; color: var(--text-muted);">Failed to load comments.</p>';
                return;
            }

            const data = await response.json();

            if (data.error) {
                list.innerHTML = '<p style="text-align: center; color: var(--text-muted);">Failed to load comments.</p>';
                return;
            }

            commentsLoaded = true;
            availableReactions = data.available_reactions || [];

            if (!data.comments || data.comments.length === 0) {
                list.innerHTML = '<p style="text-align: center; color: var(--text-muted); padding: 20px;">No comments yet. Be the first to comment!</p>';
                return;
            }

            list.innerHTML = data.comments.map(c => renderComment(c, 0)).join('');

        } catch (err) {
            console.error('Load comments error:', err);
            list.innerHTML = '<p style="text-align: center; color: var(--text-muted);">Failed to load comments.</p>';
        }
    }

    function renderComment(c, depth) {
        const indent = depth * 20;
        const isEdited = c.is_edited ? '<span style="font-size: 0.7rem; color: var(--text-muted);"> (edited)</span>' : '';
        const ownerActions = c.is_owner ? `
            <span onclick="goalEditComment(${c.id}, '${escapeHtml(c.content).replace(/'/g, "\\'")}')" style="cursor: pointer; margin-left: 10px;" title="Edit">✏️</span>
            <span onclick="goalDeleteComment(${c.id})" style="cursor: pointer; margin-left: 5px;" title="Delete">🗑️</span>
        ` : '';

        const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
            const isUserReaction = (c.user_reactions || []).includes(emoji);
            return `<span onclick="goalToggleReaction(${c.id}, '${emoji}')" style="cursor: pointer; padding: 2px 6px; border-radius: 12px; font-size: 0.8rem; background: ${isUserReaction ? 'rgba(219, 39, 119, 0.2)' : 'var(--pill-bg)'}; border: 1px solid ${isUserReaction ? 'rgba(219, 39, 119, 0.4)' : 'var(--glass-border)'};">${emoji} ${count}</span>`;
        }).join(' ');

        const replies = (c.replies || []).map(r => renderComment(r, depth + 1)).join('');

        return `
            <div style="margin-left: ${indent}px; padding: 15px; margin-bottom: 10px; background: var(--pill-bg); border-radius: 12px; border: 1px solid var(--glass-border);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                    <img src="${c.avatar || '/assets/img/defaults/default_avatar.webp'}" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;" loading="lazy">
                    <div>
                        <strong style="color: var(--text-color);">${escapeHtml(c.author_name)}</strong>${isEdited}
                        <div style="font-size: 0.75rem; color: var(--text-muted);">${c.time_ago}</div>
                    </div>
                    ${ownerActions}
                </div>
                <div id="content-${c.id}" style="color: var(--text-color); margin-bottom: 10px;">${escapeHtml(c.content)}</div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                    ${reactions}
                    <span onclick="goalShowReplyForm(${c.id})" style="cursor: pointer; color: var(--accent-color); font-size: 0.85rem;">↩️ Reply</span>
                </div>
                <div id="reply-form-${c.id}" style="display: none; margin-top: 10px;">
                    <input type="text" id="reply-input-${c.id}" placeholder="Write a reply..." style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--glass-border); background: var(--pill-bg); color: var(--text-color);">
                    <button onclick="goalSubmitReply(${c.id})" class="glass-pill-btn btn-primary" style="margin-top: 8px; padding: 6px 12px; font-size: 0.85rem;">Reply</button>
                </div>
                ${replies}
            </div>
        `;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    window.goalSubmitComment = async function(e) {
        e.preventDefault();

        const input = document.getElementById('comment-input');
        const content = input.value.trim();
        if (!content) return;

        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Posting...';

        try {
            const response = await fetch(API_BASE + '/comments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'submit',
                    target_type: 'goal',
                    target_id: goalId,
                    content: content
                })
            });

            const data = await response.json();

            if (data.error) { alert(data.error); return; }

            input.value = '';
            const countEl = document.getElementById('comment-count');
            countEl.textContent = parseInt(countEl.textContent) + 1;
            loadComments();
        } catch (err) {
            console.error('Submit comment error:', err);
            alert('Failed to post comment');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Post Comment';
        }
    };

    window.goalShowReplyForm = function(commentId) {
        const form = document.getElementById(`reply-form-${commentId}`);
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') {
            document.getElementById(`reply-input-${commentId}`).focus();
        }
    };

    window.goalSubmitReply = async function(parentId) {
        const input = document.getElementById(`reply-input-${parentId}`);
        const content = input.value.trim();
        if (!content) return;

        try {
            const response = await fetch(API_BASE + '/reply', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    target_type: 'goal',
                    target_id: goalId,
                    parent_id: parentId,
                    content: content
                })
            });
            const data = await response.json();
            if (data.error) { alert(data.error); return; }
            input.value = '';
            document.getElementById(`reply-form-${parentId}`).style.display = 'none';
            const countEl = document.getElementById('comment-count');
            countEl.textContent = parseInt(countEl.textContent) + 1;
            loadComments();
        } catch (err) { console.error('Reply error:', err); }
    };

    window.goalToggleReaction = async function(commentId, emoji) {
        if (!isLoggedIn) { alert('Please log in to react'); return; }

        try {
            const response = await fetch(API_BASE + '/reaction', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    comment_id: commentId,
                    emoji: emoji
                })
            });
            const data = await response.json();
            if (data.error) { alert(data.error); return; }
            loadComments();
        } catch (err) { console.error('Reaction error:', err); }
    };

    window.goalDeleteComment = async function(commentId) {
        if (!confirm('Delete this comment?')) return;

        try {
            const response = await fetch(API_BASE + '/delete-comment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    comment_id: commentId
                })
            });
            const data = await response.json();
            if (data.error) { alert(data.error); return; }
            const countEl = document.getElementById('comment-count');
            countEl.textContent = Math.max(0, parseInt(countEl.textContent) - 1);
            loadComments();
        } catch (err) { console.error('Delete error:', err); }
    };

    window.goalEditComment = function(commentId, currentContent) {
        const contentEl = document.getElementById(`content-${commentId}`);
        const originalHtml = contentEl.innerHTML;

        contentEl.innerHTML = `
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <input type="text" id="edit-input-${commentId}" value="${escapeHtml(currentContent)}" style="flex: 1; min-width: 200px; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--glass-border); background: var(--pill-bg); color: var(--text-color);">
                <button onclick="goalSaveEdit(${commentId})" class="glass-pill-btn btn-primary" style="padding: 6px 12px;">Save</button>
                <button onclick="goalCancelEdit(${commentId}, '${escapeHtml(originalHtml).replace(/'/g, "\\'")}')" class="glass-pill-btn btn-secondary" style="padding: 6px 12px;">Cancel</button>
            </div>
        `;
        document.getElementById(`edit-input-${commentId}`).focus();
    };

    window.goalCancelEdit = function(commentId, originalHtml) {
        document.getElementById(`content-${commentId}`).innerHTML = originalHtml;
    };

    window.goalSaveEdit = async function(commentId) {
        const input = document.getElementById(`edit-input-${commentId}`);
        const newContent = input.value.trim();
        if (!newContent) return;

        try {
            const response = await fetch(API_BASE + '/edit-comment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    comment_id: commentId,
                    content: newContent
                })
            });
            const data = await response.json();
            if (data.error) { alert(data.error); return; }
            loadComments();
        } catch (err) { console.error('Edit error:', err); }
    };

    window.shareToFeed = async function() {
        if (!confirm('Share this goal to your feed?')) return;

        try {
            const response = await fetch(API_BASE + '/share', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    parent_type: 'goal',
                    parent_id: goalId
                })
            });

            const data = await response.json();

            if (data.error) { alert(data.error); return; }
            if (data.status === 'success') {
                alert('Goal shared to your feed!');
            }
        } catch (err) {
            console.error('Share error:', err);
            alert('Failed to share');
        }
    };
})();
</script>

<?php
// Mobile Bottom Sheets - Now included centrally in footer.php
?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
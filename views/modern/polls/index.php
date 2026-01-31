<?php
// Polls Index - Glassmorphism 2025
$pageTitle = "Community Polls";
$pageSubtitle = "Make your voice heard on community decisions";
$hideHero = true; // Use Glassmorphism design without hero

Nexus\Core\SEO::setTitle('Community Polls - Vote on Local Decisions');
Nexus\Core\SEO::setDescription('Participate in community polls and make your voice heard. Vote on local decisions and help shape your neighborhood.');

require __DIR__ . '/../../layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
<div id="polls-glass-wrapper">


    <!-- Smart Welcome Hero Section -->
    <div class="nexus-welcome-hero">
        <h1 class="nexus-welcome-title">Community Polls</h1>
        <p class="nexus-welcome-subtitle">Participate in community polls and make your voice heard. Vote on local decisions and help shape your neighborhood.</p>

        <div class="nexus-smart-buttons">
            <a href="<?= $basePath ?>/polls" class="nexus-smart-btn nexus-smart-btn-primary">
                <i class="fa-solid fa-check-to-slot"></i>
                <span>All Polls</span>
            </a>
            <a href="<?= $basePath ?>/polls?filter=open" class="nexus-smart-btn nexus-smart-btn-secondary">
                <i class="fa-solid fa-circle-check"></i>
                <span>Open Polls</span>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $basePath ?>/polls?filter=voted" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-square-check"></i>
                <span>My Votes</span>
            </a>
            <?php endif; ?>
            <a href="<?= $basePath ?>/compose?type=poll" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-plus"></i>
                <span>Create Poll</span>
            </a>
        </div>
    </div>

    <!-- Glass Info Card -->
    <div class="glass-search-card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin: 0 0 8px 0;">Community Governance</h2>
                <p style="font-size: 0.95rem; color: var(--htb-text-muted); margin: 0;">
                    <?= count($polls ?? []) ?> polls available - Your vote matters!
                </p>
            </div>
        </div>
    </div>

    <!-- Section Header -->
    <div class="section-header">
        <i class="fa-solid fa-square-poll-vertical" style="color: #8b5cf6; font-size: 1.1rem;"></i>
        <h2>Active Polls</h2>
    </div>

    <!-- Polls Grid -->
    <div class="polls-grid">
        <?php if (!empty($polls)): ?>
            <?php foreach ($polls as $poll): ?>
                <a href="<?= $basePath ?>/polls/<?= $poll['id'] ?>" class="glass-poll-card">
                    <!-- Card Header -->
                    <div class="card-header">
                        <div class="header-content">
                            <div class="poll-icon">
                                <i class="fa-solid fa-square-poll-vertical"></i>
                            </div>
                            <span class="status-badge <?= $poll['status'] === 'open' ? 'open' : 'closed' ?>">
                                <?= ucfirst($poll['status']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="card-body">
                        <h3 class="poll-question">
                            <?= htmlspecialchars($poll['question']) ?>
                        </h3>

                        <div class="poll-meta">
                            <?php if (!empty($poll['options_count'])): ?>
                            <div class="poll-meta-item">
                                <i class="fa-solid fa-list-check"></i>
                                <span><?= $poll['options_count'] ?> options</span>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($poll['created_at'])): ?>
                            <div class="poll-meta-item">
                                <i class="fa-solid fa-calendar"></i>
                                <span><?= date('M d, Y', strtotime($poll['created_at'])) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <p class="poll-desc">
                            Make your voice heard on this community decision.
                        </p>
                    </div>

                    <div class="card-footer">
                        <div class="vote-count">
                            <i class="fa-solid fa-users"></i>
                            <span><?= $poll['vote_count'] ?? 0 ?> votes</span>
                        </div>
                        <span class="vote-link">
                            <?= $poll['status'] === 'open' ? 'Vote Now' : 'View Results' ?> <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="glass-empty-state">
                <div style="font-size: 4rem; margin-bottom: 20px;">🗳️</div>
                <h3 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--htb-text-main);">No active polls</h3>
                <p style="color: var(--htb-text-muted); margin-bottom: 20px;">Start a discussion by creating a poll!</p>
                <a href="<?= $basePath ?>/compose?type=poll" class="btn btn--primary">
                    <i class="fa-solid fa-plus"></i> Create Poll
                </a>
            </div>
        <?php endif; ?>
    </div>

</div><!-- #polls-glass-wrapper -->
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
document.querySelectorAll('.htb-btn, button, .nexus-smart-btn, .btn--primary, .vote-link').forEach(btn => {
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
        meta.content = '#8b5cf6';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#8b5cf6');
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

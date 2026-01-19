<?php
// Smart Matches Dashboard - WCAG 2.1 AA Compliant
// CSS extracted to civicone-matches.css
$hero_title = $page_title ?? "Smart Matches";
$hero_subtitle = "AI-powered matches based on your preferences, skills, and location";
$hero_gradient = 'htb-hero-gradient-matches';
$hero_type = 'Matches';

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
$hotMatches = $hot_matches ?? [];
$goodMatches = $good_matches ?? [];
$mutualMatches = $mutual_matches ?? [];
$allMatches = $all_matches ?? [];
$stats = $stats ?? [];
$preferences = $preferences ?? [];
?>

<!-- Cosmic Background -->
<div class="matches-cosmos-bg" aria-hidden="true"></div>

<div class="matches-container">
    <!-- Header -->
    <header class="matches-header">
        <h1 class="matches-title">Smart Matches</h1>
        <p class="matches-subtitle">AI-powered matching based on your preferences, skills, and location</p>
    </header>

    <!-- Stats Bar -->
    <div class="match-stats-bar" role="region" aria-label="Match statistics">
        <div class="match-stat-card">
            <div class="match-stat-icon hot" aria-hidden="true">🔥</div>
            <div class="match-stat-value"><?= count($hotMatches) ?></div>
            <div class="match-stat-label">Hot Matches</div>
        </div>
        <div class="match-stat-card">
            <div class="match-stat-icon mutual" aria-hidden="true">🤝</div>
            <div class="match-stat-value"><?= count($mutualMatches) ?></div>
            <div class="match-stat-label">Mutual</div>
        </div>
        <div class="match-stat-card">
            <div class="match-stat-icon good" aria-hidden="true">⭐</div>
            <div class="match-stat-value"><?= count($goodMatches) ?></div>
            <div class="match-stat-label">Good Matches</div>
        </div>
        <div class="match-stat-card">
            <div class="match-stat-icon total" aria-hidden="true">📊</div>
            <div class="match-stat-value"><?= $stats['total_matches'] ?? count($allMatches) ?></div>
            <div class="match-stat-label">Total Found</div>
        </div>
    </div>

    <!-- Preferences Bar -->
    <div class="matches-prefs-bar">
        <div class="matches-prefs-info">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
            </svg>
            <span>Max distance: <?= $preferences['max_distance_km'] ?? 25 ?>km | Min score: <?= $preferences['min_match_score'] ?? 50 ?>%</span>
        </div>
        <a href="<?= $basePath ?>/matches/preferences" class="matches-prefs-link">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Preferences
        </a>
    </div>

    <!-- Tab Navigation -->
    <nav class="matches-tabs" role="tablist" aria-label="Match categories">
        <button class="match-tab active" data-tab="hot" role="tab" aria-selected="true" aria-controls="section-hot">
            <span aria-hidden="true">🔥</span> Hot
            <span class="match-tab-badge"><?= count($hotMatches) ?></span>
        </button>
        <button class="match-tab" data-tab="mutual" role="tab" aria-selected="false" aria-controls="section-mutual">
            <span aria-hidden="true">🤝</span> Mutual
            <span class="match-tab-badge"><?= count($mutualMatches) ?></span>
        </button>
        <button class="match-tab" data-tab="good" role="tab" aria-selected="false" aria-controls="section-good">
            <span aria-hidden="true">⭐</span> Good
            <span class="match-tab-badge"><?= count($goodMatches) ?></span>
        </button>
        <button class="match-tab" data-tab="all" role="tab" aria-selected="false" aria-controls="section-all">
            <span aria-hidden="true">📋</span> All
            <span class="match-tab-badge"><?= count($allMatches) ?></span>
        </button>
    </nav>

    <!-- Hot Matches Section -->
    <section class="match-section active" id="section-hot" role="tabpanel" aria-labelledby="tab-hot">
        <div class="match-section-header">
            <h2 class="match-section-title">
                <span class="icon hot" aria-hidden="true">🔥</span>
                Hot Matches
            </h2>
        </div>

        <?php if (empty($hotMatches)): ?>
            <div class="matches-empty" role="status">
                <div class="matches-empty-icon" aria-hidden="true">🔥</div>
                <h3>No Hot Matches Yet</h3>
                <p>Hot matches are listings with 85%+ compatibility. Try adjusting your preferences or add more listings!</p>
                <a href="<?= $basePath ?>/listings/create" class="matches-empty-btn">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create a Listing
                </a>
            </div>
        <?php else: ?>
            <div class="match-cards-grid" role="list" aria-label="Hot matches">
                <?php foreach ($hotMatches as $match): ?>
                    <?php include __DIR__ . '/_match_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Mutual Matches Section -->
    <section class="match-section" id="section-mutual" role="tabpanel" aria-labelledby="tab-mutual" hidden>
        <div class="match-section-header">
            <h2 class="match-section-title">
                <span class="icon mutual" aria-hidden="true">🤝</span>
                Mutual Matches
            </h2>
        </div>

        <?php if (empty($mutualMatches)): ?>
            <div class="matches-empty" role="status">
                <div class="matches-empty-icon" aria-hidden="true">🤝</div>
                <h3>No Mutual Matches Yet</h3>
                <p>Mutual matches happen when you can both help each other. Keep sharing your skills!</p>
                <a href="<?= $basePath ?>/listings/create?type=offer" class="matches-empty-btn">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Offer a Skill
                </a>
            </div>
        <?php else: ?>
            <div class="match-cards-grid" role="list" aria-label="Mutual matches">
                <?php foreach ($mutualMatches as $match): ?>
                    <?php include __DIR__ . '/_match_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Good Matches Section -->
    <section class="match-section" id="section-good" role="tabpanel" aria-labelledby="tab-good" hidden>
        <div class="match-section-header">
            <h2 class="match-section-title">
                <span class="icon good" aria-hidden="true">⭐</span>
                Good Matches
            </h2>
        </div>

        <?php if (empty($goodMatches)): ?>
            <div class="matches-empty" role="status">
                <div class="matches-empty-icon" aria-hidden="true">⭐</div>
                <h3>No Good Matches Found</h3>
                <p>Good matches are listings with 70-84% compatibility in your area.</p>
                <a href="<?= $basePath ?>/matches/preferences" class="matches-empty-btn">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Adjust Preferences
                </a>
            </div>
        <?php else: ?>
            <div class="match-cards-grid" role="list" aria-label="Good matches">
                <?php foreach ($goodMatches as $match): ?>
                    <?php include __DIR__ . '/_match_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- All Matches Section -->
    <section class="match-section" id="section-all" role="tabpanel" aria-labelledby="tab-all" hidden>
        <div class="match-section-header">
            <h2 class="match-section-title">
                <span class="icon total" aria-hidden="true">📋</span>
                All Matches
            </h2>
        </div>

        <?php if (empty($allMatches)): ?>
            <div class="matches-empty" role="status">
                <div class="matches-empty-icon" aria-hidden="true">🔍</div>
                <h3>No Matches Found</h3>
                <p>We couldn't find any matches based on your current preferences. Try expanding your search radius or adding more listings.</p>
                <a href="<?= $basePath ?>/listings/create" class="matches-empty-btn">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create a Listing
                </a>
            </div>
        <?php else: ?>
            <div class="match-cards-grid" role="list" aria-label="All matches">
                <?php foreach ($allMatches as $match): ?>
                    <?php include __DIR__ . '/_match_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
// Tab switching with ARIA support
document.querySelectorAll('.match-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        const tabId = this.dataset.tab;

        // Update active tab
        document.querySelectorAll('.match-tab').forEach(t => {
            t.classList.remove('active');
            t.setAttribute('aria-selected', 'false');
        });
        this.classList.add('active');
        this.setAttribute('aria-selected', 'true');

        // Update active section
        document.querySelectorAll('.match-section').forEach(s => {
            s.classList.remove('active');
            s.hidden = true;
        });
        const section = document.getElementById('section-' + tabId);
        section.classList.add('active');
        section.hidden = false;
    });

    // Keyboard navigation
    tab.addEventListener('keydown', function(e) {
        const tabs = Array.from(document.querySelectorAll('.match-tab'));
        const currentIndex = tabs.indexOf(this);
        let newIndex;

        if (e.key === 'ArrowRight') {
            newIndex = (currentIndex + 1) % tabs.length;
        } else if (e.key === 'ArrowLeft') {
            newIndex = (currentIndex - 1 + tabs.length) % tabs.length;
        } else if (e.key === 'Home') {
            newIndex = 0;
        } else if (e.key === 'End') {
            newIndex = tabs.length - 1;
        } else {
            return;
        }

        e.preventDefault();
        tabs[newIndex].focus();
        tabs[newIndex].click();
    });
});

// Track interactions
function trackMatchInteraction(listingId, action, matchScore, distance) {
    fetch('<?= $basePath ?>/matches/interact', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            listing_id: listingId,
            action: action,
            match_score: matchScore,
            distance: distance
        })
    }).catch(console.error);
}

// Track views when cards become visible (respecting reduced motion)
if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const card = entry.target;
                const listingId = card.dataset.listingId;
                const matchScore = card.dataset.matchScore;
                const distance = card.dataset.distance;
                if (listingId && !card.dataset.viewed) {
                    trackMatchInteraction(listingId, 'viewed', matchScore, distance);
                    card.dataset.viewed = 'true';
                }
            }
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('.match-card').forEach(card => observer.observe(card));
}
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

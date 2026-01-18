<?php
/**
 * Hot Matches Page - Dedicated view for high-score matches (85%+)
 */
$hero_title = $page_title ?? "Hot Matches";
$hero_subtitle = "High-compatibility matches nearby - act fast!";
$hero_gradient = 'htb-hero-gradient-matches';
$hero_type = 'Matches';

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
$matches = $matches ?? [];
?>

<style>
/* Hot Matches Page Styles */
.hot-matches-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    background: linear-gradient(135deg, #fef3e2 0%, #fde8d0 25%, #fce0c0 50%, #fde8d0 75%, #fef3e2 100%);
}

.hot-matches-bg::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background:
        radial-gradient(ellipse at 25% 25%, rgba(249, 115, 22, 0.2) 0%, transparent 50%),
        radial-gradient(ellipse at 75% 25%, rgba(239, 68, 68, 0.15) 0%, transparent 45%),
        radial-gradient(ellipse at 50% 75%, rgba(245, 158, 11, 0.12) 0%, transparent 50%);
    animation: hotDrift 20s ease-in-out infinite;
}

[data-theme="dark"] .hot-matches-bg {
    background: linear-gradient(135deg, #1a0f0a 0%, #2d1810 50%, #1a0f0a 100%);
}

[data-theme="dark"] .hot-matches-bg::before {
    background:
        radial-gradient(ellipse at 25% 25%, rgba(249, 115, 22, 0.3) 0%, transparent 50%),
        radial-gradient(ellipse at 75% 25%, rgba(239, 68, 68, 0.25) 0%, transparent 45%);
}

@keyframes hotDrift {
    0%, 100% { transform: translate(0, 0) rotate(0deg); }
    50% { transform: translate(-1%, 1%) rotate(0.5deg); }
}

.hot-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 100px 24px 60px;
    position: relative;
    z-index: 1;
}

@media (max-width: 768px) {
    .hot-container {
        padding: 20px 16px 100px;
    }
}

.hot-header {
    text-align: center;
    margin-bottom: 40px;
}

.hot-title {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #f97316 0%, #ef4444 50%, #dc2626 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.hot-subtitle {
    color: #64748b;
    font-size: 1.1rem;
}

[data-theme="dark"] .hot-subtitle {
    color: #94a3b8;
}

.hot-back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #f97316;
    text-decoration: none;
    font-weight: 600;
    margin-bottom: 24px;
    transition: all 0.2s;
}

.hot-back-link:hover {
    color: #ea580c;
    transform: translateX(-4px);
}

.hot-count-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #f97316, #ef4444);
    color: white;
    padding: 12px 24px;
    border-radius: 30px;
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 32px;
    box-shadow: 0 4px 20px rgba(239, 68, 68, 0.3);
}

/* Reuse match card styles from index.php */
.match-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 24px;
}

@media (max-width: 768px) {
    .match-cards-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
}

/* Include all match-card styles */
.match-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.85) 100%);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.match-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 50px rgba(249, 115, 22, 0.2);
}

[data-theme="dark"] .match-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(30, 41, 59, 0.85) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

.match-card.hot-match {
    border-color: rgba(249, 115, 22, 0.3);
}

.match-card.hot-match::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(135deg, #f97316, #ef4444, #f97316);
    border-radius: 22px;
    z-index: -1;
    opacity: 0.5;
    animation: hotPulse 2s ease-in-out infinite;
}

@keyframes hotPulse {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 0.6; }
}

.match-score-badge {
    position: absolute;
    top: 16px;
    right: 16px;
    padding: 8px 14px;
    border-radius: 30px;
    font-weight: 800;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 6px;
    z-index: 2;
    background: linear-gradient(135deg, #f97316, #ef4444);
    color: white;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
}

.match-card-body { padding: 24px; }

.match-card-header {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
}

.match-avatar {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    object-fit: cover;
    border: 3px solid white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    flex-shrink: 0;
}

.match-info { flex: 1; min-width: 0; }

.match-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

[data-theme="dark"] .match-title { color: #f1f5f9; }

.match-user {
    font-size: 0.9rem;
    color: #f97316;
    font-weight: 600;
    margin-bottom: 4px;
}

.match-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 0.85rem;
    color: #64748b;
}

.match-meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
}

.match-reasons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 16px;
}

.match-reason {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    background: rgba(249, 115, 22, 0.1);
    color: #f97316;
}

.match-actions {
    display: flex;
    gap: 12px;
    padding-top: 16px;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.match-action-btn {
    flex: 1;
    padding: 12px 16px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
}

.match-action-btn.primary {
    background: linear-gradient(135deg, #f97316 0%, #ef4444 100%);
    color: white;
}

.match-action-btn.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
}

.match-action-btn.secondary {
    background: rgba(249, 115, 22, 0.1);
    color: #f97316;
}

.match-action-btn.secondary:hover {
    background: rgba(249, 115, 22, 0.2);
}

.distance-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.distance-badge.walking { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.distance-badge.local { background: rgba(6, 182, 212, 0.15); color: #06b6d4; }
.distance-badge.city { background: rgba(99, 102, 241, 0.15); color: #6366f1; }
.distance-badge.regional { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }

.hot-empty {
    text-align: center;
    padding: 60px 20px;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.5);
}

[data-theme="dark"] .hot-empty {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(30, 41, 59, 0.7) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

.hot-empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
}

.hot-empty h3 {
    font-size: 1.4rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}

[data-theme="dark"] .hot-empty h3 { color: #f1f5f9; }

.hot-empty p {
    color: #64748b;
    margin-bottom: 20px;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

.hot-empty-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 28px;
    background: linear-gradient(135deg, #f97316 0%, #ef4444 100%);
    color: white;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

.hot-empty-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
}
</style>

<div class="hot-matches-bg"></div>

<div class="hot-container">
    <a href="<?= $basePath ?>/matches" class="hot-back-link">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to All Matches
    </a>

    <div class="hot-header">
        <h1 class="hot-title">
            <span>Hot Matches</span>
        </h1>
        <p class="hot-subtitle">These are your highest compatibility matches - 85% or above!</p>
    </div>

    <div style="text-align: center;">
        <div class="hot-count-badge">
            <span>🔥</span>
            <?= count($matches) ?> Hot Match<?= count($matches) !== 1 ? 'es' : '' ?> Found
        </div>
    </div>

    <?php if (empty($matches)): ?>
        <div class="hot-empty">
            <div class="hot-empty-icon">🔥</div>
            <h3>No Hot Matches Yet</h3>
            <p>Hot matches are listings with 85%+ compatibility and close proximity. Try adding more listings or expanding your preferences!</p>
            <a href="<?= $basePath ?>/listings/create" class="hot-empty-btn">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Create a Listing
            </a>
        </div>
    <?php else: ?>
        <div class="match-cards-grid">
            <?php foreach ($matches as $match): ?>
                <?php include __DIR__ . '/_match_card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
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
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>

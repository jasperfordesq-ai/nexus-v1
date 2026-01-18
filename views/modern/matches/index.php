<?php
// Smart Matches Dashboard - Multiverse-Class Matching Engine UI
$hero_title = $page_title ?? "Smart Matches";
$hero_subtitle = "AI-powered matches based on your preferences, skills, and location";
$hero_gradient = 'htb-hero-gradient-matches';
$hero_type = 'Matches';

require __DIR__ . '/../../layouts/modern/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
$hotMatches = $hot_matches ?? [];
$goodMatches = $good_matches ?? [];
$mutualMatches = $mutual_matches ?? [];
$allMatches = $all_matches ?? [];
$stats = $stats ?? [];
$preferences = $preferences ?? [];
?>

<style>
/* ============================================
   SMART MATCHES DASHBOARD - COSMIC DESIGN
   Multiverse-Class Matching Engine UI
   ============================================ */

/* Page Background */
.matches-cosmos-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    background: linear-gradient(135deg, #f8fafc 0%, #f0f4ff 25%, #e8efff 50%, #f0f4ff 75%, #f8fafc 100%);
}

.matches-cosmos-bg::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background:
        radial-gradient(ellipse at 25% 25%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 75% 25%, rgba(236, 72, 153, 0.12) 0%, transparent 45%),
        radial-gradient(ellipse at 50% 75%, rgba(16, 185, 129, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse at 85% 85%, rgba(245, 158, 11, 0.08) 0%, transparent 40%);
    animation: cosmicDrift 25s ease-in-out infinite;
}

[data-theme="dark"] .matches-cosmos-bg {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
}

[data-theme="dark"] .matches-cosmos-bg::before {
    background:
        radial-gradient(ellipse at 25% 25%, rgba(99, 102, 241, 0.25) 0%, transparent 50%),
        radial-gradient(ellipse at 75% 25%, rgba(236, 72, 153, 0.2) 0%, transparent 45%);
}

@keyframes cosmicDrift {
    0%, 100% { transform: translate(0, 0) rotate(0deg); }
    33% { transform: translate(-1%, 1%) rotate(0.5deg); }
    66% { transform: translate(1%, -0.5%) rotate(-0.5deg); }
}

/* Container */
.matches-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 100px 24px 60px;
    position: relative;
    z-index: 1;
}

@media (max-width: 768px) {
    .matches-container {
        padding: 20px 16px 100px;
    }
}

/* Header Section */
.matches-header {
    text-align: center;
    margin-bottom: 40px;
}

.matches-title {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 8px;
}

.matches-subtitle {
    color: #64748b;
    font-size: 1.1rem;
}

[data-theme="dark"] .matches-subtitle {
    color: #94a3b8;
}

/* Stats Bar */
.match-stats-bar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}

.match-stat-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
}

.match-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(99, 102, 241, 0.15);
}

[data-theme="dark"] .match-stat-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(30, 41, 59, 0.7) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

.match-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    font-size: 1.5rem;
}

.match-stat-icon.hot { background: linear-gradient(135deg, #f97316, #ef4444); }
.match-stat-icon.mutual { background: linear-gradient(135deg, #10b981, #06b6d4); }
.match-stat-icon.good { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
.match-stat-icon.total { background: linear-gradient(135deg, #64748b, #475569); }

.match-stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: #1e293b;
    line-height: 1;
    margin-bottom: 4px;
}

[data-theme="dark"] .match-stat-value {
    color: #f1f5f9;
}

.match-stat-label {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 500;
}

/* Tab Navigation */
.matches-tabs {
    display: flex;
    gap: 8px;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.8) 0%, rgba(255, 255, 255, 0.6) 100%);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 16px;
    padding: 8px;
    margin-bottom: 32px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

[data-theme="dark"] .matches-tabs {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(30, 41, 59, 0.7) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

.match-tab {
    flex: 1;
    min-width: 120px;
    padding: 14px 20px;
    border-radius: 12px;
    border: none;
    background: transparent;
    color: #64748b;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    white-space: nowrap;
}

.match-tab:hover {
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
}

.match-tab.active {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.match-tab-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
}

.match-tab.active .match-tab-badge {
    background: rgba(255, 255, 255, 0.3);
}

/* Match Sections */
.match-section {
    margin-bottom: 40px;
    display: none;
}

.match-section.active {
    display: block;
}

.match-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.match-section-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 10px;
}

[data-theme="dark"] .match-section-title {
    color: #f1f5f9;
}

.match-section-title .icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.match-section-title .icon.hot { background: linear-gradient(135deg, #f97316, #ef4444); }
.match-section-title .icon.mutual { background: linear-gradient(135deg, #10b981, #06b6d4); }
.match-section-title .icon.good { background: linear-gradient(135deg, #6366f1, #8b5cf6); }

/* Match Cards Grid */
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

/* Match Card */
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
    box-shadow: 0 20px 50px rgba(99, 102, 241, 0.15);
}

[data-theme="dark"] .match-card {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.95) 0%, rgba(30, 41, 59, 0.85) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

/* Hot Match Glow */
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

/* Mutual Match Glow */
.match-card.mutual-match {
    border-color: rgba(16, 185, 129, 0.3);
}

.match-card.mutual-match::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(135deg, #10b981, #06b6d4, #10b981);
    border-radius: 22px;
    z-index: -1;
    opacity: 0.4;
}

/* Match Score Badge */
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
}

.match-score-badge.hot {
    background: linear-gradient(135deg, #f97316, #ef4444);
    color: white;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
}

.match-score-badge.good {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.match-score-badge.moderate {
    background: linear-gradient(135deg, #64748b, #475569);
    color: white;
}

/* Match Card Content */
.match-card-body {
    padding: 24px;
}

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

.match-info {
    flex: 1;
    min-width: 0;
}

.match-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

[data-theme="dark"] .match-title {
    color: #f1f5f9;
}

.match-user {
    font-size: 0.9rem;
    color: #6366f1;
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

.match-meta-item svg {
    width: 14px;
    height: 14px;
}

/* Match Reasons */
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
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
}

[data-theme="dark"] .match-reason {
    background: rgba(99, 102, 241, 0.2);
    color: #a5b4fc;
}

.match-reason.category { background: rgba(236, 72, 153, 0.1); color: #ec4899; }
.match-reason.distance { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.match-reason.reciprocal { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

/* Match Actions */
.match-actions {
    display: flex;
    gap: 12px;
    padding-top: 16px;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .match-actions {
    border-top-color: rgba(255, 255, 255, 0.05);
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
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
}

.match-action-btn.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
}

.match-action-btn.secondary {
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
}

.match-action-btn.secondary:hover {
    background: rgba(99, 102, 241, 0.2);
}

/* Empty State */
.matches-empty {
    text-align: center;
    padding: 60px 20px;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.5);
}

[data-theme="dark"] .matches-empty {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(30, 41, 59, 0.7) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

.matches-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
}

.matches-empty h3 {
    font-size: 1.4rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}

[data-theme="dark"] .matches-empty h3 {
    color: #f1f5f9;
}

.matches-empty p {
    color: #64748b;
    margin-bottom: 20px;
}

.matches-empty-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 28px;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

.matches-empty-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
}

/* Preferences Quick Link */
.matches-prefs-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    padding: 16px 24px;
    margin-bottom: 24px;
}

.matches-prefs-info {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #6366f1;
    font-weight: 500;
}

.matches-prefs-link {
    padding: 10px 20px;
    background: white;
    color: #6366f1;
    border-radius: 10px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.matches-prefs-link:hover {
    background: #6366f1;
    color: white;
}

[data-theme="dark"] .matches-prefs-link {
    background: rgba(99, 102, 241, 0.2);
    color: #a5b4fc;
}

[data-theme="dark"] .matches-prefs-link:hover {
    background: #6366f1;
    color: white;
}

/* Distance Indicator */
.distance-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.distance-badge.walking {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.distance-badge.local {
    background: rgba(6, 182, 212, 0.15);
    color: #06b6d4;
}

.distance-badge.city {
    background: rgba(99, 102, 241, 0.15);
    color: #6366f1;
}

.distance-badge.regional {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}
</style>

<!-- Cosmic Background -->
<div class="matches-cosmos-bg"></div>

<div class="matches-container">
    <!-- Header -->
    <div class="matches-header">
        <h1 class="matches-title">Smart Matches</h1>
        <p class="matches-subtitle">AI-powered matching based on your preferences, skills, and location</p>
    </div>

    <!-- Stats Bar -->
    <div class="match-stats-bar">
        <div class="match-stat-card">
            <div class="match-stat-icon hot">🔥</div>
            <div class="match-stat-value"><?= count($hotMatches) ?></div>
            <div class="match-stat-label">Hot Matches</div>
        </div>
        <div class="match-stat-card">
            <div class="match-stat-icon mutual">🤝</div>
            <div class="match-stat-value"><?= count($mutualMatches) ?></div>
            <div class="match-stat-label">Mutual</div>
        </div>
        <div class="match-stat-card">
            <div class="match-stat-icon good">⭐</div>
            <div class="match-stat-value"><?= count($goodMatches) ?></div>
            <div class="match-stat-label">Good Matches</div>
        </div>
        <div class="match-stat-card">
            <div class="match-stat-icon total">📊</div>
            <div class="match-stat-value"><?= $stats['total_matches'] ?? count($allMatches) ?></div>
            <div class="match-stat-label">Total Found</div>
        </div>
    </div>

    <!-- Preferences Bar -->
    <div class="matches-prefs-bar">
        <div class="matches-prefs-info">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
            </svg>
            <span>Max distance: <?= $preferences['max_distance_km'] ?? 25 ?>km | Min score: <?= $preferences['min_match_score'] ?? 50 ?>%</span>
        </div>
        <a href="<?= $basePath ?>/matches/preferences" class="matches-prefs-link">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Preferences
        </a>
    </div>

    <!-- Tab Navigation -->
    <div class="matches-tabs">
        <button class="match-tab active" data-tab="hot">
            🔥 Hot
            <span class="match-tab-badge"><?= count($hotMatches) ?></span>
        </button>
        <button class="match-tab" data-tab="mutual">
            🤝 Mutual
            <span class="match-tab-badge"><?= count($mutualMatches) ?></span>
        </button>
        <button class="match-tab" data-tab="good">
            ⭐ Good
            <span class="match-tab-badge"><?= count($goodMatches) ?></span>
        </button>
        <button class="match-tab" data-tab="all">
            📋 All
            <span class="match-tab-badge"><?= count($allMatches) ?></span>
        </button>
    </div>

    <!-- Hot Matches Section -->
    <div class="match-section active" id="section-hot">
        <div class="match-section-header">
            <h2 class="match-section-title">
                <span class="icon hot">🔥</span>
                Hot Matches
            </h2>
        </div>

        <?php if (empty($hotMatches)): ?>
            <div class="matches-empty">
                <div class="matches-empty-icon">🔥</div>
                <h3>No Hot Matches Yet</h3>
                <p>Hot matches are listings with 85%+ compatibility. Try adjusting your preferences or add more listings!</p>
                <a href="<?= $basePath ?>/listings/create" class="matches-empty-btn">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create a Listing
                </a>
            </div>
        <?php else: ?>
            <div class="match-cards-grid">
                <?php foreach ($hotMatches as $match): ?>
                    <?php include __DIR__ . '/_match_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Mutual Matches Section -->
    <div class="match-section" id="section-mutual">
        <div class="match-section-header">
            <h2 class="match-section-title">
                <span class="icon mutual">🤝</span>
                Mutual Matches
            </h2>
        </div>

        <?php if (empty($mutualMatches)): ?>
            <div class="matches-empty">
                <div class="matches-empty-icon">🤝</div>
                <h3>No Mutual Matches Yet</h3>
                <p>Mutual matches happen when you can both help each other. Keep sharing your skills!</p>
                <a href="<?= $basePath ?>/listings/create?type=offer" class="matches-empty-btn">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Offer a Skill
                </a>
            </div>
        <?php else: ?>
            <div class="match-cards-grid">
                <?php foreach ($mutualMatches as $match): ?>
                    <?php include __DIR__ . '/_match_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Good Matches Section -->
    <div class="match-section" id="section-good">
        <div class="match-section-header">
            <h2 class="match-section-title">
                <span class="icon good">⭐</span>
                Good Matches
            </h2>
        </div>

        <?php if (empty($goodMatches)): ?>
            <div class="matches-empty">
                <div class="matches-empty-icon">⭐</div>
                <h3>No Good Matches Found</h3>
                <p>Good matches are listings with 70-84% compatibility in your area.</p>
                <a href="<?= $basePath ?>/matches/preferences" class="matches-empty-btn">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Adjust Preferences
                </a>
            </div>
        <?php else: ?>
            <div class="match-cards-grid">
                <?php foreach ($goodMatches as $match): ?>
                    <?php include __DIR__ . '/_match_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- All Matches Section -->
    <div class="match-section" id="section-all">
        <div class="match-section-header">
            <h2 class="match-section-title">
                <span class="icon" style="background: linear-gradient(135deg, #64748b, #475569);">📋</span>
                All Matches
            </h2>
        </div>

        <?php if (empty($allMatches)): ?>
            <div class="matches-empty">
                <div class="matches-empty-icon">🔍</div>
                <h3>No Matches Found</h3>
                <p>We couldn't find any matches based on your current preferences. Try expanding your search radius or adding more listings.</p>
                <a href="<?= $basePath ?>/listings/create" class="matches-empty-btn">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create a Listing
                </a>
            </div>
        <?php else: ?>
            <div class="match-cards-grid">
                <?php foreach ($allMatches as $match): ?>
                    <?php include __DIR__ . '/_match_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Tab switching
document.querySelectorAll('.match-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        const tabId = this.dataset.tab;

        // Update active tab
        document.querySelectorAll('.match-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        // Update active section
        document.querySelectorAll('.match-section').forEach(s => s.classList.remove('active'));
        document.getElementById('section-' + tabId).classList.add('active');
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

// Track views when cards become visible
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
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>

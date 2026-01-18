<?php
// Volunteering Index - Gold Standard Holographic Glassmorphism 2025
$pageTitle = "Volunteer Opportunities";
$pageSubtitle = "Make a difference in your community";
$hideHero = true;

Nexus\Core\SEO::setTitle('Volunteer Opportunities - Make a Difference in Your Community');
Nexus\Core\SEO::setDescription('Find meaningful volunteer opportunities in your community. Connect with organizations, share your skills, and make an impact.');

require __DIR__ . '/../../layouts/modern/header.php';
?>

<style>
/* ==============================================
   GOLD STANDARD - HOLOGRAPHIC GLASSMORPHISM 2025
   Volunteering Index Page
   ============================================== */

/* Animated Holographic Background */
.vol-page-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    pointer-events: none;
    background:
        radial-gradient(ellipse at 20% 20%, rgba(13, 148, 136, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(99, 102, 241, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(236, 72, 153, 0.08) 0%, transparent 60%);
    animation: holoShift 20s ease-in-out infinite;
}

[data-theme="dark"] .vol-page-bg {
    background:
        radial-gradient(ellipse at 20% 20%, rgba(13, 148, 136, 0.25) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(99, 102, 241, 0.2) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(236, 72, 153, 0.12) 0%, transparent 60%);
}

@keyframes holoShift {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.05); }
}

/* Main Glass Container */
.vol-glass-container {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.75));
    backdrop-filter: blur(24px) saturate(180%);
    -webkit-backdrop-filter: blur(24px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.5);
    border-radius: 28px;
    padding: 32px;
    margin: 100px auto 20px auto;
    max-width: 1200px;
    box-shadow:
        0 8px 32px rgba(31, 38, 135, 0.15),
        0 0 0 1px rgba(255, 255, 255, 0.2),
        inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

[data-theme="dark"] .vol-glass-container {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.85), rgba(30, 41, 59, 0.75));
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow:
        0 8px 32px rgba(0, 0, 0, 0.5),
        0 0 80px rgba(13, 148, 136, 0.1),
        0 0 120px rgba(99, 102, 241, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

/* Hero Section */
.vol-hero {
    text-align: center;
    padding: 40px 20px;
    margin-bottom: 30px;
    background: linear-gradient(135deg, rgba(13, 148, 136, 0.1), rgba(99, 102, 241, 0.1));
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

[data-theme="dark"] .vol-hero {
    background: linear-gradient(135deg, rgba(13, 148, 136, 0.2), rgba(99, 102, 241, 0.15));
    border-color: rgba(255, 255, 255, 0.1);
}

.vol-hero-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0 0 12px 0;
    background: linear-gradient(135deg, #0d9488, #6366f1, #ec4899);
    background-size: 200% 200%;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: gradientText 8s ease infinite;
}

@keyframes gradientText {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.vol-hero-subtitle {
    font-size: 1.1rem;
    color: var(--htb-text-muted);
    max-width: 600px;
    margin: 0 auto 24px auto;
    line-height: 1.6;
}

/* Quick Action Buttons */
.vol-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}

.vol-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid transparent;
}

.vol-action-btn-primary {
    background: linear-gradient(135deg, #0d9488, #14b8a6);
    color: white;
    box-shadow: 0 4px 15px rgba(13, 148, 136, 0.4);
}

.vol-action-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(13, 148, 136, 0.5);
}

.vol-action-btn-secondary {
    background: rgba(255, 255, 255, 0.6);
    color: var(--htb-text-main);
    border-color: rgba(13, 148, 136, 0.3);
}

[data-theme="dark"] .vol-action-btn-secondary {
    background: rgba(30, 41, 59, 0.6);
    border-color: rgba(13, 148, 136, 0.4);
}

.vol-action-btn-secondary:hover {
    background: linear-gradient(135deg, #0d9488, #14b8a6);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
}

/* Search Section */
.vol-search-section {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0.5));
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 32px;
}

[data-theme="dark"] .vol-search-section {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.6), rgba(15, 23, 42, 0.5));
    border-color: rgba(255, 255, 255, 0.1);
}

.vol-search-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.vol-search-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0;
}

.vol-search-count {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--htb-text-muted);
}

.vol-search-form {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

@media (min-width: 768px) {
    .vol-search-form {
        grid-template-columns: 2fr 1fr auto auto;
        align-items: center;
    }
}

.vol-search-input-wrap {
    position: relative;
}

.vol-search-input {
    width: 100%;
    padding: 14px 18px 14px 48px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-radius: 14px;
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.7);
    color: var(--htb-text-main);
    transition: all 0.3s ease;
}

.vol-search-input:focus {
    outline: none;
    border-color: #0d9488;
    box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15);
}

[data-theme="dark"] .vol-search-input {
    background: rgba(15, 23, 42, 0.7);
    border-color: rgba(255, 255, 255, 0.15);
    color: #f1f5f9;
}

.vol-search-input-wrap i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 1rem;
}

.vol-search-select {
    padding: 14px 18px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-radius: 14px;
    font-size: 0.95rem;
    background: rgba(255, 255, 255, 0.7);
    color: var(--htb-text-main);
    cursor: pointer;
    transition: all 0.3s ease;
}

[data-theme="dark"] .vol-search-select {
    background: rgba(15, 23, 42, 0.7);
    border-color: rgba(255, 255, 255, 0.15);
    color: #f1f5f9;
}

.vol-search-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.5);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    color: var(--htb-text-main);
    white-space: nowrap;
}

[data-theme="dark"] .vol-search-checkbox {
    background: rgba(30, 41, 59, 0.5);
    border-color: rgba(255, 255, 255, 0.1);
}

.vol-search-checkbox input {
    width: 18px;
    height: 18px;
    accent-color: #0d9488;
}

.vol-search-btn {
    padding: 14px 24px;
    background: linear-gradient(135deg, #0d9488, #14b8a6);
    color: white;
    border: none;
    border-radius: 14px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(13, 148, 136, 0.3);
}

.vol-search-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(13, 148, 136, 0.4);
}

/* Opportunities Grid */
.vol-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 24px;
}

@media (max-width: 400px) {
    .vol-grid {
        grid-template-columns: 1fr;
    }
}

/* OPPORTUNITY CARD - Gold Standard Holographic Design */
.vol-card {
    display: block;
    text-decoration: none;
    color: inherit;
    background: linear-gradient(145deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.8));
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.5);
    border-radius: 24px;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow:
        0 4px 20px rgba(31, 38, 135, 0.1),
        0 0 0 1px rgba(255, 255, 255, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
    position: relative;
}

.vol-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg,
        rgba(13, 148, 136, 0.05) 0%,
        rgba(99, 102, 241, 0.05) 50%,
        rgba(236, 72, 153, 0.05) 100%);
    opacity: 0;
    transition: opacity 0.4s ease;
    pointer-events: none;
    border-radius: 24px;
}

.vol-card:hover::before {
    opacity: 1;
}

.vol-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow:
        0 20px 40px rgba(31, 38, 135, 0.2),
        0 0 0 1px rgba(13, 148, 136, 0.3),
        0 0 60px rgba(13, 148, 136, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 1);
}

[data-theme="dark"] .vol-card {
    background: linear-gradient(145deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.8));
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow:
        0 4px 20px rgba(0, 0, 0, 0.4),
        0 0 60px rgba(13, 148, 136, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .vol-card:hover {
    box-shadow:
        0 20px 40px rgba(0, 0, 0, 0.6),
        0 0 0 1px rgba(13, 148, 136, 0.4),
        0 0 80px rgba(13, 148, 136, 0.15),
        0 0 120px rgba(99, 102, 241, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 0.15);
}

/* Card Header - Holographic Gradient */
.vol-card-header {
    height: 140px;
    background: linear-gradient(135deg, #0d9488 0%, #14b8a6 40%, #2dd4bf 70%, #5eead4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.vol-card-header::before {
    content: '';
    position: absolute;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
    top: -50px;
    right: -50px;
    animation: pulseGlow 4s ease-in-out infinite;
}

.vol-card-header::after {
    content: '';
    position: absolute;
    width: 150px;
    height: 150px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, transparent 70%);
    bottom: -30px;
    left: -30px;
    animation: pulseGlow 4s ease-in-out infinite 2s;
}

@keyframes pulseGlow {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.2); opacity: 0.8; }
}

.vol-card-header-icon {
    font-size: 3rem;
    color: rgba(255, 255, 255, 0.95);
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.15));
    z-index: 1;
    transition: transform 0.3s ease;
}

.vol-card:hover .vol-card-header-icon {
    transform: scale(1.1);
}

/* Card Body */
.vol-card-body {
    padding: 24px;
}

/* Organization Badge */
.vol-org-row {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 16px;
}

.vol-org-badge {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #ccfbf1, #99f6e4);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0d9488;
    font-weight: 800;
    font-size: 1.25rem;
    border: 2px solid rgba(13, 148, 136, 0.2);
    box-shadow: 0 4px 12px rgba(13, 148, 136, 0.15);
    flex-shrink: 0;
}

[data-theme="dark"] .vol-org-badge {
    background: linear-gradient(135deg, rgba(13, 148, 136, 0.3), rgba(20, 184, 166, 0.2));
    color: #5eead4;
    border-color: rgba(13, 148, 136, 0.4);
}

.vol-org-info {
    flex: 1;
    min-width: 0;
}

.vol-org-name {
    font-weight: 700;
    color: var(--htb-text-main);
    font-size: 0.95rem;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.vol-org-location {
    font-size: 0.85rem;
    color: var(--htb-text-muted);
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Card Title */
.vol-card-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 12px 0;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Card Description */
.vol-card-desc {
    color: var(--htb-text-muted);
    font-size: 0.95rem;
    line-height: 1.6;
    margin: 0 0 16px 0;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Skill Tags */
.vol-skills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 20px;
}

.vol-skill-tag {
    display: inline-block;
    padding: 6px 12px;
    background: linear-gradient(135deg, rgba(243, 244, 246, 0.9), rgba(229, 231, 235, 0.8));
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #4b5563;
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
}

.vol-skill-tag:hover {
    background: linear-gradient(135deg, #ccfbf1, #99f6e4);
    color: #0d9488;
    border-color: rgba(13, 148, 136, 0.3);
}

[data-theme="dark"] .vol-skill-tag {
    background: linear-gradient(135deg, rgba(55, 65, 81, 0.8), rgba(75, 85, 99, 0.6));
    color: #d1d5db;
    border-color: rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .vol-skill-tag:hover {
    background: linear-gradient(135deg, rgba(13, 148, 136, 0.3), rgba(20, 184, 166, 0.2));
    color: #5eead4;
}

/* Card Footer */
.vol-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16px;
    border-top: 1px solid rgba(0, 0, 0, 0.06);
}

[data-theme="dark"] .vol-card-footer {
    border-color: rgba(255, 255, 255, 0.08);
}

.vol-credits {
    font-size: 0.9rem;
    font-weight: 600;
    color: #0d9488;
    display: flex;
    align-items: center;
    gap: 6px;
}

[data-theme="dark"] .vol-credits {
    color: #5eead4;
}

.vol-view-link {
    color: #0d9488;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.vol-view-link:hover {
    gap: 10px;
    color: #14b8a6;
}

[data-theme="dark"] .vol-view-link {
    color: #5eead4;
}

/* Empty State */
.vol-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0.5));
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 24px;
}

[data-theme="dark"] .vol-empty {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.6), rgba(15, 23, 42, 0.5));
    border-color: rgba(255, 255, 255, 0.1);
}

.vol-empty-icon {
    font-size: 4rem;
    margin-bottom: 20px;
}

.vol-empty-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin: 0 0 10px 0;
}

.vol-empty-text {
    color: var(--htb-text-muted);
    margin: 0 0 20px 0;
}

/* Skeleton Loading */
.vol-skeleton {
    background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: 8px;
}

[data-theme="dark"] .vol-skeleton {
    background: linear-gradient(90deg, #1e293b 25%, #334155 50%, #1e293b 75%);
    background-size: 200% 100%;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .vol-glass-container {
        padding: 20px;
        margin: 80px 10px 20px 10px;
        border-radius: 20px;
    }

    .vol-hero {
        padding: 30px 16px;
    }

    .vol-hero-title {
        font-size: 1.75rem;
    }

    .vol-grid {
        gap: 16px;
    }

    .vol-card:hover {
        transform: none;
    }

    .vol-card-header {
        height: 120px;
    }
}

/* Offline Banner */
.vol-offline-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10001;
    padding: 12px 16px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.9rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transform: translateY(-100%);
    transition: transform 0.3s ease;
}

.vol-offline-banner.visible {
    transform: translateY(0);
}
</style>

<!-- Holographic Background -->
<div class="vol-page-bg"></div>

<!-- Offline Banner -->
<div class="vol-offline-banner" id="offlineBanner">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="vol-glass-container">

    <!-- Hero Section -->
    <div class="vol-hero">
        <h1 class="vol-hero-title">Volunteer Opportunities</h1>
        <p class="vol-hero-subtitle">Discover meaningful ways to give back to your community. Share your skills, connect with organizations, and make a real impact.</p>

        <div class="vol-actions">
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering" class="vol-action-btn vol-action-btn-primary">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span>Browse All</span>
            </a>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/organizations" class="vol-action-btn vol-action-btn-secondary">
                <i class="fa-solid fa-building-columns"></i>
                <span>Organizations</span>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/my-applications" class="vol-action-btn vol-action-btn-secondary">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>My Applications</span>
            </a>
            <?php endif; ?>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/dashboard" class="vol-action-btn vol-action-btn-secondary">
                <i class="fa-solid fa-building"></i>
                <span>For Organizations</span>
            </a>
        </div>
    </div>

    <!-- Search Section -->
    <div class="vol-search-section">
        <div class="vol-search-header">
            <h2 class="vol-search-title">Find Opportunities</h2>
            <span class="vol-search-count"><?= count($opportunities ?? []) ?> opportunities available</span>
        </div>

        <form action="" method="GET" class="vol-search-form">
            <div class="vol-search-input-wrap">
                <i class="fa-solid fa-search"></i>
                <input type="search" aria-label="Search" name="q" placeholder="Search by cause, skill, or organization..."
                       value="<?= htmlspecialchars($query ?? '') ?>" class="vol-search-input">
            </div>

            <select name="cat" class="vol-search-select">
                <option value="">All Categories</option>
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (isset($activeCat) && $activeCat == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <label class="vol-search-checkbox">
                <input type="checkbox" name="remote" value="1" <?= (isset($isRemote) && $isRemote) ? 'checked' : '' ?>>
                <span>Remote Only</span>
            </label>

            <button type="submit" class="vol-search-btn">
                <i class="fa-solid fa-search"></i>
                <span>Find</span>
            </button>
        </form>
    </div>

    <!-- Opportunities Grid -->
    <div class="vol-grid">
        <?php if (!empty($opportunities)): ?>
            <?php foreach ($opportunities as $opp): ?>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/<?= $opp['id'] ?>" class="vol-card">
                    <div class="vol-card-header">
                        <i class="fa-solid fa-hands-helping vol-card-header-icon"></i>
                    </div>

                    <div class="vol-card-body">
                        <div class="vol-org-row">
                            <div class="vol-org-badge">
                                <?= strtoupper(substr($opp['org_name'] ?? 'O', 0, 1)) ?>
                            </div>
                            <div class="vol-org-info">
                                <div class="vol-org-name"><?= htmlspecialchars($opp['org_name'] ?? 'Organization') ?></div>
                                <div class="vol-org-location">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <?= htmlspecialchars($opp['location'] ?? 'Remote') ?>
                                </div>
                            </div>
                        </div>

                        <h3 class="vol-card-title"><?= htmlspecialchars($opp['title']) ?></h3>

                        <p class="vol-card-desc"><?= htmlspecialchars(substr($opp['description'] ?? '', 0, 150)) ?>...</p>

                        <?php if (!empty($opp['skills_needed'])): ?>
                            <div class="vol-skills">
                                <?php foreach (array_slice(explode(',', $opp['skills_needed']), 0, 4) as $skill): ?>
                                    <span class="vol-skill-tag"><?= trim(htmlspecialchars($skill)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="vol-card-footer">
                            <?php if (!empty($opp['credits_offered'])): ?>
                                <span class="vol-credits">
                                    <i class="fa-solid fa-coins"></i>
                                    <?= $opp['credits_offered'] ?> credits
                                </span>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>

                            <span class="vol-view-link">
                                View Details <i class="fa-solid fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="vol-empty">
                <div class="vol-empty-icon">🔍</div>
                <h3 class="vol-empty-title">No opportunities found</h3>
                <p class="vol-empty-text">Check back later or adjust your search criteria.</p>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering" class="vol-action-btn vol-action-btn-primary">
                    View All Opportunities
                </a>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
// Offline Detection
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function updateStatus() {
        if (!navigator.onLine) {
            banner.classList.add('visible');
        } else {
            banner.classList.remove('visible');
        }
    }

    window.addEventListener('online', updateStatus);
    window.addEventListener('offline', updateStatus);
    updateStatus();
})();

// Card hover effects with haptic feedback
document.querySelectorAll('.vol-card').forEach(card => {
    card.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.98)';
    });

    card.addEventListener('pointerup', function() {
        this.style.transform = '';
    });

    card.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>

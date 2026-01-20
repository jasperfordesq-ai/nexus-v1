<?php
// Organization Profile Page - Enhanced Glassmorphism Design
// Shows: org info, volunteer opportunities, membership options

if (session_status() === PHP_SESSION_NONE) session_start();

$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$base = \Nexus\Core\TenantContext::getBasePath();
$hasTimebanking = \Nexus\Core\TenantContext::hasFeature('wallet');

// Set variables for the utility bar
$activeTab = 'profile';
$isMember = $isMember ?? false;
$isAdmin = $isAdmin ?? false;
$isOwner = $isOwner ?? false;
$role = $isOwner ? 'owner' : ($isAdmin ? 'admin' : ($isMember ? 'member' : ''));

// Get pending count for badge (if admin)
$pendingCount = 0;
if ($isAdmin && $hasTimebanking) {
    try {
        $pendingCount = \Nexus\Models\OrgTransferRequest::countPending($org['id']);
    } catch (\Exception $e) {
        $pendingCount = 0;
    }
}

$hero_title = htmlspecialchars($org['name']);
$hero_subtitle = "Volunteer Organization";
$hero_gradient = 'htb-hero-gradient-teal';
$hideHero = true;

require __DIR__ . '/../../layouts/header.php';
?>

<style>
/* ============================================
   ORGANIZATION PROFILE - ENHANCED GLASSMORPHISM
   ============================================ */

.org-profile-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%, #f8fafc 100%);
}

.org-profile-bg::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background:
        radial-gradient(ellipse at 25% 30%, rgba(20, 184, 166, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse at 75% 25%, rgba(79, 70, 229, 0.1) 0%, transparent 45%),
        radial-gradient(ellipse at 50% 80%, rgba(16, 185, 129, 0.08) 0%, transparent 50%);
    animation: orgProfileFloat 20s ease-in-out infinite;
}

[data-theme="dark"] .org-profile-bg {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
}

[data-theme="dark"] .org-profile-bg::before {
    background:
        radial-gradient(ellipse at 25% 30%, rgba(20, 184, 166, 0.2) 0%, transparent 50%),
        radial-gradient(ellipse at 75% 25%, rgba(79, 70, 229, 0.15) 0%, transparent 45%);
}

@keyframes orgProfileFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-1%, 1%) scale(1.02); }
}

.org-profile-container {
    padding: 100px 24px 60px 24px;
    max-width: 1200px;
    margin: 0 auto;
    position: relative;
    z-index: 20;
}

/* Hero Banner */
.org-hero-banner {
    position: relative;
    height: 200px;
    background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);
    border-radius: 20px;
    margin-bottom: -60px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(16, 185, 129, 0.25);
}

.org-hero-banner::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse at 30% 20%, rgba(255, 255, 255, 0.15) 0%, transparent 50%),
        radial-gradient(ellipse at 70% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
}

.org-hero-pattern {
    position: absolute;
    inset: 0;
    opacity: 0.1;
    background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

/* Profile Card - overlaps hero */
.org-profile-card {
    position: relative;
    z-index: 10;
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.95) 0%,
        rgba(255, 255, 255, 0.85) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 20px;
    padding: 24px;
    margin: 0 20px 24px 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

[data-theme="dark"] .org-profile-card {
    background: linear-gradient(135deg,
        rgba(30, 41, 59, 0.95) 0%,
        rgba(30, 41, 59, 0.85) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

.org-profile-content {
    display: flex;
    gap: 24px;
    align-items: flex-start;
}

/* Large Logo */
.org-profile-logo {
    width: 120px;
    height: 120px;
    border-radius: 20px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 3rem;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 8px 24px rgba(16, 185, 129, 0.35);
    overflow: hidden;
    border: 4px solid white;
    margin-top: -60px;
}

[data-theme="dark"] .org-profile-logo {
    border-color: rgba(30, 41, 59, 0.9);
}

.org-profile-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Profile Info */
.org-profile-info {
    flex: 1;
    min-width: 0;
    padding-top: 8px;
}

.org-profile-name {
    margin: 0 0 8px 0;
    font-size: 1.75rem;
    font-weight: 800;
    color: #111827;
    line-height: 1.2;
}

[data-theme="dark"] .org-profile-name {
    color: #f1f5f9;
}

.org-profile-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}

.org-profile-status.active {
    background: rgba(16, 185, 129, 0.15);
    color: #059669;
}

.org-profile-status.pending {
    background: rgba(251, 191, 36, 0.15);
    color: #b45309;
}

[data-theme="dark"] .org-profile-status.active {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.org-profile-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 16px;
}

.org-profile-meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: #6b7280;
    text-decoration: none;
    transition: color 0.2s;
}

.org-profile-meta-item i {
    color: #10b981;
    width: 16px;
    text-align: center;
}

.org-profile-meta-item:hover {
    color: #10b981;
}

[data-theme="dark"] .org-profile-meta-item {
    color: #94a3b8;
}

.org-profile-description {
    color: #4b5563;
    line-height: 1.7;
    margin: 0;
    font-size: 0.95rem;
}

[data-theme="dark"] .org-profile-description {
    color: #cbd5e1;
}

/* Quick Action Buttons */
.org-quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid rgba(0, 0, 0, 0.06);
}

[data-theme="dark"] .org-quick-actions {
    border-top-color: rgba(255, 255, 255, 0.08);
}

.org-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s ease;
    min-height: 48px;
    border: none;
    cursor: pointer;
}

.org-action-btn--primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
}

.org-action-btn--primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 24px rgba(16, 185, 129, 0.4);
}

.org-action-btn--wallet {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3);
}

.org-action-btn--wallet:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 24px rgba(99, 102, 241, 0.4);
}

.org-action-btn--secondary {
    background: rgba(0, 0, 0, 0.05);
    color: #374151;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.org-action-btn--secondary:hover {
    background: rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
}

[data-theme="dark"] .org-action-btn--secondary {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

[data-theme="dark"] .org-action-btn--secondary:hover {
    background: rgba(255, 255, 255, 0.12);
}

/* Stats Grid */
.org-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
    padding: 0 20px;
}

.org-stat-card {
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.9) 0%,
        rgba(255, 255, 255, 0.7) 100%);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    transition: all 0.2s ease;
}

.org-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

[data-theme="dark"] .org-stat-card {
    background: linear-gradient(135deg,
        rgba(30, 41, 59, 0.9) 0%,
        rgba(30, 41, 59, 0.7) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

.org-stat-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 12px;
    border-radius: 12px;
    background: rgba(16, 185, 129, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #10b981;
    font-size: 1.25rem;
}

.org-stat-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: #111827;
    margin-bottom: 4px;
}

[data-theme="dark"] .org-stat-value {
    color: #f1f5f9;
}

.org-stat-label {
    font-size: 0.85rem;
    color: #6b7280;
    font-weight: 500;
}

[data-theme="dark"] .org-stat-label {
    color: #94a3b8;
}

/* Section Title */
.org-section {
    padding: 0 20px;
    margin-bottom: 32px;
}

.org-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}

.org-section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
    margin: 0;
}

.org-section-title i {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(16, 185, 129, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #10b981;
    font-size: 1rem;
}

[data-theme="dark"] .org-section-title {
    color: #f1f5f9;
}

.org-section-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 10px;
    color: #059669;
    font-weight: 600;
    font-size: 0.85rem;
    text-decoration: none;
    transition: all 0.2s;
}

.org-section-action:hover {
    background: rgba(16, 185, 129, 0.15);
    transform: translateY(-1px);
}

/* Opportunities Grid */
.org-opps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.org-opp-card {
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.95) 0%,
        rgba(255, 255, 255, 0.85) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 16px;
    padding: 20px;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    transition: all 0.25s ease;
}

.org-opp-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
    border-color: rgba(16, 185, 129, 0.3);
}

[data-theme="dark"] .org-opp-card {
    background: linear-gradient(135deg,
        rgba(30, 41, 59, 0.95) 0%,
        rgba(30, 41, 59, 0.85) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .org-opp-card:hover {
    border-color: rgba(16, 185, 129, 0.4);
}

.org-opp-header {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 12px;
}

.org-opp-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #10b981, #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.org-opp-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #111827;
    margin: 0 0 4px 0;
    line-height: 1.3;
}

[data-theme="dark"] .org-opp-title {
    color: #f1f5f9;
}

.org-opp-type {
    display: inline-block;
    padding: 2px 8px;
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    border-radius: 4px;
}

[data-theme="dark"] .org-opp-type {
    background: rgba(16, 185, 129, 0.2);
    color: #34d399;
}

.org-opp-desc {
    font-size: 0.9rem;
    color: #6b7280;
    line-height: 1.6;
    margin: 0 0 16px 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    flex: 1;
}

[data-theme="dark"] .org-opp-desc {
    color: #94a3b8;
}

.org-opp-footer {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding-top: 12px;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

[data-theme="dark"] .org-opp-footer {
    border-top-color: rgba(255, 255, 255, 0.08);
}

.org-opp-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    color: #9ca3af;
}

.org-opp-meta i {
    color: #10b981;
    font-size: 0.75rem;
}

/* Empty State */
.org-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.8) 0%,
        rgba(255, 255, 255, 0.6) 100%);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 20px;
}

[data-theme="dark"] .org-empty-state {
    background: linear-gradient(135deg,
        rgba(30, 41, 59, 0.8) 0%,
        rgba(30, 41, 59, 0.6) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

.org-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    border-radius: 20px;
    background: rgba(16, 185, 129, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #10b981;
    font-size: 2rem;
}

.org-empty-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #374151;
    margin: 0 0 8px 0;
}

[data-theme="dark"] .org-empty-title {
    color: #e2e8f0;
}

.org-empty-desc {
    color: #6b7280;
    margin: 0;
    font-size: 0.95rem;
}

[data-theme="dark"] .org-empty-desc {
    color: #94a3b8;
}

/* Mobile */
@media (max-width: 768px) {
    .org-profile-container {
        padding: 80px 16px 40px 16px;
    }

    .org-hero-banner {
        height: 140px;
        margin-bottom: -50px;
        border-radius: 16px;
    }

    .org-profile-card {
        margin: 0 0 20px 0;
        padding: 20px;
        /* Reduce blur on mobile for performance */
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .org-profile-content {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .org-profile-logo {
        margin-top: -50px;
        width: 100px;
        height: 100px;
        font-size: 2.5rem;
    }

    .org-profile-meta {
        justify-content: center;
    }

    .org-quick-actions {
        justify-content: center;
    }

    .org-stats-grid {
        grid-template-columns: repeat(2, 1fr);
        padding: 0;
    }

    .org-stat-card {
        /* Reduce blur on mobile */
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
    }

    .org-section {
        padding: 0;
    }

    .org-opps-grid {
        grid-template-columns: 1fr;
    }

    .org-opp-card {
        /* Reduce blur on mobile */
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    /* Disable expensive background animation on mobile */
    .org-profile-bg::before {
        animation: none;
        transform: none;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
    }

    .org-empty-state {
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
    }
}

/* Reduced motion preferences */
@media (prefers-reduced-motion: reduce) {
    .org-profile-bg::before {
        animation: none;
    }

    .org-profile-card,
    .org-stat-card,
    .org-opp-card,
    .org-action-btn {
        transition: none;
    }

    .org-opp-card:hover,
    .org-stat-card:hover,
    .org-action-btn:hover {
        transform: none;
    }
}
</style>

<div class="org-profile-bg"></div>

<div class="org-profile-container">

    <!-- Include shared utility bar -->
    <?php include __DIR__ . '/../organizations/_org-utility-bar.php'; ?>

    <!-- Hero Banner -->
    <div class="org-hero-banner">
        <div class="org-hero-pattern"></div>
    </div>

    <!-- Profile Card -->
    <div class="org-profile-card">
        <div class="org-profile-content">
            <div class="org-profile-logo">
                <?php if (!empty($org['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($org['logo_url']) ?>" loading="lazy" alt="<?= htmlspecialchars($org['name']) ?>">
                <?php else: ?>
                    <i class="fa-solid fa-building"></i>
                <?php endif; ?>
            </div>
            <div class="org-profile-info">
                <h1 class="org-profile-name"><?= htmlspecialchars($org['name']) ?></h1>

                <span class="org-profile-status <?= ($org['status'] ?? 'active') === 'active' ? 'active' : 'pending' ?>">
                    <i class="fa-solid <?= ($org['status'] ?? 'active') === 'active' ? 'fa-circle-check' : 'fa-clock' ?>"></i>
                    <?= ucfirst($org['status'] ?? 'active') ?>
                </span>

                <div class="org-profile-meta">
                    <?php if (!empty($org['contact_email'])): ?>
                        <a href="mailto:<?= htmlspecialchars($org['contact_email']) ?>" class="org-profile-meta-item">
                            <i class="fa-solid fa-envelope"></i>
                            <?= htmlspecialchars($org['contact_email']) ?>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($org['website'])): ?>
                        <a href="<?= htmlspecialchars($org['website']) ?>" target="_blank" class="org-profile-meta-item">
                            <i class="fa-solid fa-globe"></i>
                            Website
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($org['location'])): ?>
                        <span class="org-profile-meta-item">
                            <i class="fa-solid fa-location-dot"></i>
                            <?= htmlspecialchars($org['location']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($hasTimebanking && $memberCount > 0): ?>
                        <span class="org-profile-meta-item">
                            <i class="fa-solid fa-users"></i>
                            <?= $memberCount ?> member<?= $memberCount !== 1 ? 's' : '' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($org['description'])): ?>
                    <p class="org-profile-description"><?= nl2br(htmlspecialchars($org['description'])) ?></p>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="org-quick-actions">
                    <?php if ($hasTimebanking): ?>
                        <?php if ($isMember): ?>
                            <a href="<?= $base ?>/organizations/<?= $org['id'] ?>/wallet" class="org-action-btn org-action-btn--wallet">
                                <i class="fa-solid fa-wallet"></i> View Wallet
                            </a>
                        <?php elseif ($isLoggedIn): ?>
                            <form action="<?= $base ?>/organizations/<?= $org['id'] ?>/members/request" method="POST" style="display: inline;">
                                <?= \Nexus\Core\Csrf::input() ?>
                                <button type="submit" class="org-action-btn org-action-btn--primary">
                                    <i class="fa-solid fa-user-plus"></i> Request to Join
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($org['website'])): ?>
                        <a href="<?= htmlspecialchars($org['website']) ?>" target="_blank" class="org-action-btn org-action-btn--secondary">
                            <i class="fa-solid fa-external-link"></i> Visit Website
                        </a>
                    <?php endif; ?>

                    <?php if ($isAdmin): ?>
                        <a href="<?= $base ?>/volunteering/opportunities/create?org=<?= $org['id'] ?>" class="org-action-btn org-action-btn--secondary">
                            <i class="fa-solid fa-plus"></i> Add Opportunity
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="org-stats-grid">
        <div class="org-stat-card">
            <div class="org-stat-icon">
                <i class="fa-solid fa-hand-holding-heart"></i>
            </div>
            <div class="org-stat-value"><?= count($opportunities) ?></div>
            <div class="org-stat-label">Opportunities</div>
        </div>
        <?php if ($hasTimebanking && $memberCount > 0): ?>
            <div class="org-stat-card">
                <div class="org-stat-icon">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="org-stat-value"><?= $memberCount ?></div>
                <div class="org-stat-label">Members</div>
            </div>
        <?php endif; ?>
        <?php if (!empty($org['created_at'])): ?>
            <div class="org-stat-card">
                <div class="org-stat-icon">
                    <i class="fa-solid fa-calendar"></i>
                </div>
                <div class="org-stat-value"><?= date('Y', strtotime($org['created_at'])) ?></div>
                <div class="org-stat-label">Founded</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Volunteer Opportunities Section -->
    <div class="org-section">
        <div class="org-section-header">
            <h2 class="org-section-title">
                <i class="fa-solid fa-hand-holding-heart"></i>
                Volunteer Opportunities
            </h2>
            <?php if ($isAdmin): ?>
                <a href="<?= $base ?>/volunteering/opportunities/create?org=<?= $org['id'] ?>" class="org-section-action">
                    <i class="fa-solid fa-plus"></i> Add New
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($opportunities)): ?>
            <div class="org-opps-grid">
                <?php foreach ($opportunities as $opp): ?>
                    <a href="<?= $base ?>/volunteering/<?= $opp['id'] ?>" class="org-opp-card">
                        <div class="org-opp-header">
                            <div class="org-opp-icon">
                                <i class="fa-solid fa-hands-helping"></i>
                            </div>
                            <div>
                                <h3 class="org-opp-title"><?= htmlspecialchars($opp['title']) ?></h3>
                                <?php if (!empty($opp['commitment_type'])): ?>
                                    <span class="org-opp-type"><?= htmlspecialchars($opp['commitment_type']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="org-opp-desc"><?= htmlspecialchars(substr($opp['description'], 0, 140)) ?>...</p>
                        <div class="org-opp-footer">
                            <?php if (!empty($opp['location'])): ?>
                                <span class="org-opp-meta">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <?= htmlspecialchars($opp['location']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($opp['created_at'])): ?>
                                <span class="org-opp-meta">
                                    <i class="fa-solid fa-clock"></i>
                                    <?= date('M j, Y', strtotime($opp['created_at'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="org-empty-state">
                <div class="org-empty-icon">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <h3 class="org-empty-title">No Opportunities Yet</h3>
                <p class="org-empty-desc">This organization hasn't posted any volunteer opportunities.</p>
                <?php if ($isAdmin): ?>
                    <a href="<?= $base ?>/volunteering/opportunities/create?org=<?= $org['id'] ?>" class="org-action-btn org-action-btn--primary" style="margin-top: 20px;">
                        <i class="fa-solid fa-plus"></i> Create First Opportunity
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>

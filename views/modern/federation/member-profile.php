<?php
// Federation Member Profile - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Member Profile";
$hideHero = true;

Nexus\Core\SEO::setTitle(($member['name'] ?? 'Member') . ' - Federated Profile');
Nexus\Core\SEO::setDescription('View federated member profile from partner timebank.');

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

// Extract data passed from controller
$member = $member ?? [];
$canMessage = $canMessage ?? false;
$canTransact = $canTransact ?? false;
$reviews = $reviews ?? [];
$reviewStats = $reviewStats ?? null;
$trustScore = $trustScore ?? ['score' => 0, 'level' => 'new'];
$pendingReviewTransaction = $pendingReviewTransaction ?? null; // Transaction ID if user can review this member

$memberName = $member['name'] ?? 'Member';
$fallbackUrl = 'https://ui-avatars.com/api/?name=' . urlencode($memberName) . '&background=8b5cf6&color=fff&size=200';
$avatarUrl = !empty($member['avatar_url']) ? $member['avatar_url'] : $fallbackUrl;

$reachClass = '';
$reachLabel = '';
$reachIcon = '';
switch ($member['service_reach'] ?? 'local_only') {
    case 'remote_ok':
        $reachClass = 'remote';
        $reachLabel = 'Offers Remote Services';
        $reachIcon = 'fa-laptop-house';
        break;
    case 'travel_ok':
        $reachClass = 'travel';
        $reachLabel = 'Will Travel for Services';
        $reachIcon = 'fa-car';
        break;
    default:
        $reachClass = 'local';
        $reachLabel = 'Local Services Only';
        $reachIcon = 'fa-location-dot';
}
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="federation-profile-wrapper">

        <style>
            /* ============================================
               FEDERATED MEMBER PROFILE - Glassmorphism
               Purple/Violet Theme
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

            #federation-profile-wrapper {
                animation: fadeInUp 0.4s ease-out;
                max-width: 900px;
                margin: 0 auto;
                padding: 20px 0;
            }

            /* Back Link */
            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: var(--htb-text-muted);
                text-decoration: none;
                font-size: 0.9rem;
                margin-bottom: 20px;
                transition: color 0.2s;
            }

            .back-link:hover {
                color: #8b5cf6;
            }

            /* Profile Card */
            .profile-card {
                background: linear-gradient(135deg,
                        rgba(255, 255, 255, 0.75),
                        rgba(255, 255, 255, 0.6));
                backdrop-filter: blur(20px) saturate(120%);
                -webkit-backdrop-filter: blur(20px) saturate(120%);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 24px;
                box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15),
                    inset 0 0 0 1px rgba(255, 255, 255, 0.4);
                overflow: hidden;
            }

            [data-theme="dark"] .profile-card {
                background: linear-gradient(135deg,
                        rgba(15, 23, 42, 0.6),
                        rgba(30, 41, 59, 0.5));
                border: 1px solid rgba(255, 255, 255, 0.15);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                    0 0 80px rgba(139, 92, 246, 0.1);
            }

            /* Profile Header */
            .profile-header {
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.15) 0%,
                        rgba(168, 85, 247, 0.15) 50%,
                        rgba(192, 132, 252, 0.1) 100%);
                padding: 40px;
                text-align: center;
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            }

            [data-theme="dark"] .profile-header {
                background: linear-gradient(135deg,
                        rgba(139, 92, 246, 0.2) 0%,
                        rgba(168, 85, 247, 0.2) 50%,
                        rgba(192, 132, 252, 0.15) 100%);
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            /* Avatar */
            .profile-avatar-container {
                position: relative;
                width: 150px;
                height: 150px;
                margin: 0 auto 20px auto;
            }

            .profile-avatar-ring {
                position: absolute;
                inset: -5px;
                background: linear-gradient(135deg, #8b5cf6, #a78bfa, #c4b5fd);
                border-radius: 50%;
                animation: avatarPulse 3s ease-in-out infinite;
            }

            @keyframes avatarPulse {
                0%, 100% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.8; transform: scale(1.02); }
            }

            .profile-avatar-img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 50%;
                border: 5px solid white;
                position: relative;
                z-index: 1;
                box-sizing: border-box;
            }

            [data-theme="dark"] .profile-avatar-img {
                border-color: #1e293b;
            }

            /* Profile Name */
            .profile-name {
                font-size: 2rem;
                font-weight: 800;
                color: var(--htb-text-main);
                margin: 0 0 10px 0;
            }

            /* Federation Badge */
            .federation-badge {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(168, 85, 247, 0.15));
                border: 1px solid rgba(139, 92, 246, 0.3);
                border-radius: 20px;
                padding: 8px 16px;
                font-size: 0.9rem;
                font-weight: 600;
                color: #7c3aed;
                margin-bottom: 15px;
            }

            [data-theme="dark"] .federation-badge {
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.25), rgba(168, 85, 247, 0.2));
                color: #a78bfa;
            }

            /* Service Reach Badge */
            .reach-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 16px;
                border-radius: 16px;
                font-size: 0.85rem;
                font-weight: 600;
            }

            .reach-badge.local {
                background: rgba(234, 179, 8, 0.15);
                color: #ca8a04;
            }

            .reach-badge.remote {
                background: rgba(16, 185, 129, 0.15);
                color: #059669;
            }

            .reach-badge.travel {
                background: rgba(59, 130, 246, 0.15);
                color: #2563eb;
            }

            [data-theme="dark"] .reach-badge.local {
                background: rgba(234, 179, 8, 0.25);
                color: #fbbf24;
            }

            [data-theme="dark"] .reach-badge.remote {
                background: rgba(16, 185, 129, 0.25);
                color: #34d399;
            }

            [data-theme="dark"] .reach-badge.travel {
                background: rgba(59, 130, 246, 0.25);
                color: #60a5fa;
            }

            /* Profile Body */
            .profile-body {
                padding: 30px 40px;
            }

            /* Info Section */
            .info-section {
                margin-bottom: 30px;
            }

            .info-section h3 {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin: 0 0 15px 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .info-section h3 i {
                color: #8b5cf6;
            }

            .info-content {
                color: var(--htb-text-muted);
                line-height: 1.7;
                font-size: 0.95rem;
            }

            /* Location */
            .location-display {
                display: flex;
                align-items: center;
                gap: 10px;
                color: var(--htb-text-muted);
                font-size: 1rem;
            }

            .location-display i {
                color: #8b5cf6;
                font-size: 1.1rem;
            }

            /* Skills Tags */
            .skills-container {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }

            .skill-tag {
                display: inline-block;
                padding: 8px 16px;
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(168, 85, 247, 0.1));
                border: 1px solid rgba(139, 92, 246, 0.2);
                border-radius: 20px;
                font-size: 0.85rem;
                font-weight: 600;
                color: #7c3aed;
            }

            [data-theme="dark"] .skill-tag {
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(168, 85, 247, 0.15));
                color: #a78bfa;
            }

            /* Action Buttons */
            .action-buttons {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
                margin-top: 30px;
                padding-top: 30px;
                border-top: 1px solid rgba(139, 92, 246, 0.15);
            }

            .action-btn {
                flex: 1;
                min-width: 180px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                padding: 14px 24px;
                border-radius: 14px;
                font-weight: 700;
                font-size: 0.95rem;
                text-decoration: none;
                transition: all 0.3s ease;
                cursor: pointer;
                border: none;
            }

            .action-btn-primary {
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                box-shadow: 0 4px 14px rgba(139, 92, 246, 0.35);
            }

            .action-btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(139, 92, 246, 0.45);
            }

            .action-btn-secondary {
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(168, 85, 247, 0.1));
                color: #7c3aed;
                border: 2px solid rgba(139, 92, 246, 0.3);
            }

            [data-theme="dark"] .action-btn-secondary {
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(168, 85, 247, 0.15));
                color: #a78bfa;
                border-color: rgba(139, 92, 246, 0.4);
            }

            .action-btn-secondary:hover {
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                color: white;
                border-color: transparent;
                transform: translateY(-2px);
            }

            .action-btn-disabled {
                background: rgba(100, 100, 100, 0.1);
                color: var(--htb-text-muted);
                cursor: not-allowed;
                opacity: 0.6;
            }

            .action-btn-disabled:hover {
                transform: none;
            }

            .action-btn-review {
                background: linear-gradient(135deg, #f59e0b, #fbbf24);
                color: #1a1a1a;
                box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35);
            }

            .action-btn-review:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(245, 158, 11, 0.45);
                color: #1a1a1a;
            }

            .action-btn-review.reviewed {
                background: linear-gradient(135deg, #10b981, #34d399);
                color: white;
                pointer-events: none;
                box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
            }

            /* Privacy Notice */
            .privacy-notice {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                margin-top: 20px;
                padding: 16px;
                background: rgba(139, 92, 246, 0.05);
                border: 1px solid rgba(139, 92, 246, 0.15);
                border-radius: 12px;
                font-size: 0.85rem;
                color: var(--htb-text-muted);
            }

            .privacy-notice i {
                color: #8b5cf6;
                margin-top: 2px;
            }

            /* Touch Targets */
            .action-btn {
                min-height: 44px;
            }

            /* Focus Visible */
            .action-btn:focus-visible,
            .back-link:focus-visible {
                outline: 3px solid rgba(139, 92, 246, 0.5);
                outline-offset: 2px;
            }

            /* Trust Score Section */
            .trust-score-section {
                display: flex;
                align-items: center;
                gap: 20px;
                padding: 20px;
                background: linear-gradient(135deg, rgba(139, 92, 246, 0.08), rgba(168, 85, 247, 0.05));
                border: 1px solid rgba(139, 92, 246, 0.15);
                border-radius: 16px;
                margin-bottom: 30px;
            }

            .trust-score-circle {
                position: relative;
                width: 80px;
                height: 80px;
                flex-shrink: 0;
            }

            .trust-score-bg {
                fill: none;
                stroke: rgba(139, 92, 246, 0.2);
                stroke-width: 8;
            }

            .trust-score-progress {
                fill: none;
                stroke: url(#trustGradient);
                stroke-width: 8;
                stroke-linecap: round;
                transform: rotate(-90deg);
                transform-origin: center;
                transition: stroke-dashoffset 0.5s ease;
            }

            .trust-score-value {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 1.25rem;
                font-weight: 800;
                color: #8b5cf6;
            }

            .trust-score-info {
                flex: 1;
            }

            .trust-score-label {
                font-size: 0.8rem;
                color: var(--htb-text-muted);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 4px;
            }

            .trust-score-level {
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--htb-text-main);
                margin-bottom: 8px;
                text-transform: capitalize;
            }

            .trust-score-details {
                display: flex;
                gap: 16px;
                font-size: 0.8rem;
                color: var(--htb-text-muted);
            }

            .trust-detail {
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .trust-detail i {
                color: #8b5cf6;
            }

            /* Reviews Section */
            .reviews-section {
                margin-bottom: 30px;
            }

            .reviews-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 20px;
            }

            .reviews-stats {
                display: flex;
                align-items: center;
                gap: 16px;
            }

            .reviews-average {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .reviews-average-value {
                font-size: 2rem;
                font-weight: 800;
                color: #8b5cf6;
            }

            .reviews-average-stars {
                display: flex;
                gap: 2px;
                color: #fbbf24;
            }

            .reviews-count {
                font-size: 0.85rem;
                color: var(--htb-text-muted);
            }

            .reviews-list {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            .review-card {
                display: flex;
                gap: 16px;
                padding: 16px;
                background: rgba(255, 255, 255, 0.5);
                border: 1px solid rgba(139, 92, 246, 0.1);
                border-radius: 12px;
                transition: all 0.2s;
            }

            [data-theme="dark"] .review-card {
                background: rgba(30, 41, 59, 0.5);
            }

            .review-card:hover {
                background: rgba(139, 92, 246, 0.05);
                border-color: rgba(139, 92, 246, 0.2);
            }

            .review-avatar {
                width: 44px;
                height: 44px;
                border-radius: 50%;
                background: linear-gradient(135deg, #8b5cf6, #a78bfa);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: 600;
                font-size: 1rem;
                flex-shrink: 0;
            }

            .review-avatar img {
                width: 100%;
                height: 100%;
                border-radius: 50%;
                object-fit: cover;
            }

            .review-content {
                flex: 1;
                min-width: 0;
            }

            .review-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 8px;
            }

            .review-author {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .review-author-name {
                font-weight: 600;
                color: var(--htb-text-main);
            }

            .review-author-badge {
                font-size: 0.7rem;
                padding: 2px 8px;
                background: rgba(139, 92, 246, 0.15);
                color: #8b5cf6;
                border-radius: 10px;
            }

            .review-rating {
                display: flex;
                gap: 2px;
                color: #fbbf24;
                font-size: 0.85rem;
            }

            .review-text {
                color: var(--htb-text-muted);
                font-size: 0.9rem;
                line-height: 1.6;
                margin-bottom: 8px;
            }

            .review-meta {
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 0.8rem;
                color: var(--htb-text-muted);
            }

            .review-meta i {
                color: #8b5cf6;
            }

            .no-reviews {
                text-align: center;
                padding: 30px;
                color: var(--htb-text-muted);
            }

            .no-reviews i {
                font-size: 2.5rem;
                color: rgba(139, 92, 246, 0.3);
                margin-bottom: 12px;
            }

            /* Responsive */
            @media (max-width: 768px) {
                #federation-profile-wrapper {
                    padding: 15px;
                }

                .profile-header {
                    padding: 30px 20px;
                }

                .profile-body {
                    padding: 20px;
                }

                .profile-name {
                    font-size: 1.5rem;
                }

                .action-buttons {
                    flex-direction: column;
                }

                .action-btn {
                    width: 100%;
                }

                .trust-score-section {
                    flex-direction: column;
                    text-align: center;
                }

                .trust-score-details {
                    justify-content: center;
                }

                .reviews-header {
                    flex-direction: column;
                    gap: 12px;
                }

                .review-card {
                    flex-direction: column;
                }

                .review-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 8px;
                }
            }
        </style>

        <!-- Back to Directory -->
        <a href="<?= $basePath ?>/federation/members" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Federated Directory
        </a>

        <!-- Profile Card -->
        <div class="profile-card">
            <!-- Header -->
            <div class="profile-header">
                <div class="profile-avatar-container">
                    <div class="profile-avatar-ring"></div>
                    <img src="<?= htmlspecialchars($avatarUrl) ?>"
                         onerror="this.onerror=null; this.src='<?= $fallbackUrl ?>'"
                         alt="<?= htmlspecialchars($memberName) ?>"
                         class="profile-avatar-img">
                </div>

                <h1 class="profile-name"><?= htmlspecialchars($memberName) ?></h1>

                <div class="federation-badge">
                    <i class="fa-solid fa-building"></i>
                    <?= htmlspecialchars($member['tenant_name'] ?? 'Partner Timebank') ?>
                </div>

                <div class="reach-badge <?= $reachClass ?>">
                    <i class="fa-solid <?= $reachIcon ?>"></i>
                    <?= $reachLabel ?>
                </div>
            </div>

            <!-- Body -->
            <div class="profile-body">
                <?php if (!empty($member['bio'])): ?>
                    <div class="info-section">
                        <h3>
                            <i class="fa-solid fa-user"></i>
                            About
                        </h3>
                        <div class="info-content">
                            <?= nl2br(htmlspecialchars($member['bio'])) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($member['location'])): ?>
                    <div class="info-section">
                        <h3>
                            <i class="fa-solid fa-location-dot"></i>
                            Location
                        </h3>
                        <div class="location-display">
                            <i class="fa-solid fa-map-marker-alt"></i>
                            <?= htmlspecialchars($member['location']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($member['skills'])): ?>
                    <div class="info-section">
                        <h3>
                            <i class="fa-solid fa-star"></i>
                            Skills & Services
                        </h3>
                        <div class="skills-container">
                            <?php
                            $skills = is_array($member['skills'])
                                ? $member['skills']
                                : array_map('trim', explode(',', $member['skills']));
                            foreach ($skills as $skill):
                                if (trim($skill)):
                            ?>
                                <span class="skill-tag"><?= htmlspecialchars(trim($skill)) ?></span>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Trust Score -->
                <?php if ($trustScore['score'] > 0): ?>
                <div class="trust-score-section">
                    <div class="trust-score-circle">
                        <svg viewBox="0 0 80 80">
                            <defs>
                                <linearGradient id="trustGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#8b5cf6"/>
                                    <stop offset="100%" style="stop-color:#a78bfa"/>
                                </linearGradient>
                            </defs>
                            <circle class="trust-score-bg" cx="40" cy="40" r="36"/>
                            <circle class="trust-score-progress" cx="40" cy="40" r="36"
                                    stroke-dasharray="<?= 2 * M_PI * 36 ?>"
                                    stroke-dashoffset="<?= 2 * M_PI * 36 * (1 - $trustScore['score'] / 100) ?>"/>
                        </svg>
                        <div class="trust-score-value"><?= $trustScore['score'] ?></div>
                    </div>
                    <div class="trust-score-info">
                        <div class="trust-score-label">Trust Score</div>
                        <div class="trust-score-level">
                            <?php
                            $levelLabels = [
                                'excellent' => 'Excellent Member',
                                'trusted' => 'Trusted Member',
                                'established' => 'Established Member',
                                'growing' => 'Growing Reputation',
                                'new' => 'New Member',
                            ];
                            echo $levelLabels[$trustScore['level']] ?? 'Member';
                            ?>
                        </div>
                        <div class="trust-score-details">
                            <?php if (!empty($trustScore['details']['review_count'])): ?>
                            <span class="trust-detail">
                                <i class="fa-solid fa-star"></i>
                                <?= $trustScore['details']['review_count'] ?> reviews
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($trustScore['details']['transaction_count'])): ?>
                            <span class="trust-detail">
                                <i class="fa-solid fa-exchange-alt"></i>
                                <?= $trustScore['details']['transaction_count'] ?> exchanges
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($trustScore['details']['cross_tenant_activity'])): ?>
                            <span class="trust-detail">
                                <i class="fa-solid fa-globe"></i>
                                Federated activity
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Reviews Section -->
                <?php if ($reviewStats && $reviewStats['total'] > 0): ?>
                <div class="reviews-section">
                    <div class="reviews-header">
                        <h3 style="margin:0; display:flex; align-items:center; gap:10px; color:var(--htb-text-main);">
                            <i class="fa-solid fa-comments" style="color:#8b5cf6;"></i>
                            Reviews
                        </h3>
                        <div class="reviews-stats">
                            <div class="reviews-average">
                                <span class="reviews-average-value"><?= number_format($reviewStats['average'], 1) ?></span>
                                <div class="reviews-average-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa-solid fa-star<?= $i <= round($reviewStats['average']) ? '' : '-half-stroke' ?>"
                                           style="<?= $i > round($reviewStats['average']) ? 'opacity:0.3;' : '' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <span class="reviews-count"><?= $reviewStats['total'] ?> review<?= $reviewStats['total'] !== 1 ? 's' : '' ?></span>
                        </div>
                    </div>

                    <div class="reviews-list">
                        <?php foreach ($reviews as $review): ?>
                        <div class="review-card">
                            <div class="review-avatar">
                                <?php if (!empty($review['reviewer_avatar'])): ?>
                                    <img src="<?= htmlspecialchars($review['reviewer_avatar']) ?>" alt="Reviewer">
                                <?php else: ?>
                                    <?= strtoupper(substr($review['reviewer_name'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <div class="review-content">
                                <div class="review-header">
                                    <div class="review-author">
                                        <span class="review-author-name"><?= htmlspecialchars($review['reviewer_name']) ?></span>
                                        <?php if ($review['is_cross_tenant']): ?>
                                            <span class="review-author-badge">
                                                <i class="fa-solid fa-globe"></i> <?= htmlspecialchars($review['reviewer_timebank'] ?? 'Partner') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa-solid fa-star" style="<?= $i > $review['rating'] ? 'opacity:0.3;' : '' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php if (!empty($review['comment'])): ?>
                                <p class="review-text"><?= htmlspecialchars($review['comment']) ?></p>
                                <?php endif; ?>
                                <div class="review-meta">
                                    <span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($review['time_ago']) ?></span>
                                    <?php if ($review['has_transaction']): ?>
                                    <span><i class="fa-solid fa-check-circle"></i> Verified exchange</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php elseif (!$reviewStats || $reviewStats['total'] === 0): ?>
                <div class="info-section">
                    <div class="no-reviews">
                        <i class="fa-regular fa-comments"></i>
                        <p>No reviews yet</p>
                        <small>Be the first to leave a review after an exchange!</small>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <?php if ($canMessage): ?>
                        <a href="<?= $basePath ?>/messages/compose?to=<?= $member['id'] ?>&federated=1" class="action-btn action-btn-primary">
                            <i class="fa-solid fa-envelope"></i>
                            Send Message
                        </a>
                    <?php else: ?>
                        <span class="action-btn action-btn-disabled" title="Messaging not enabled for this member">
                            <i class="fa-solid fa-envelope"></i>
                            Messaging Unavailable
                        </span>
                    <?php endif; ?>

                    <?php if ($canTransact): ?>
                        <a href="<?= $basePath ?>/transactions/new?with=<?= $member['id'] ?>&tenant=<?= $member['tenant_id'] ?>" class="action-btn action-btn-secondary">
                            <i class="fa-solid fa-exchange-alt"></i>
                            Start Transaction
                        </a>
                    <?php else: ?>
                        <span class="action-btn action-btn-disabled" title="Transactions not enabled for this member">
                            <i class="fa-solid fa-exchange-alt"></i>
                            Transactions Unavailable
                        </span>
                    <?php endif; ?>

                    <?php if ($pendingReviewTransaction): ?>
                        <a href="<?= $basePath ?>/federation/review/<?= $pendingReviewTransaction ?>" class="action-btn action-btn-review">
                            <i class="fa-solid fa-star"></i>
                            Leave a Review
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Privacy Notice -->
                <div class="privacy-notice">
                    <i class="fa-solid fa-shield-halved"></i>
                    <div>
                        <strong>Federated Profile</strong><br>
                        This member is from <strong><?= htmlspecialchars($member['tenant_name'] ?? 'a partner timebank') ?></strong>.
                        Only information they've chosen to share with federated partners is displayed here.
                    </div>
                </div>
            </div>
        </div>

    </div><!-- #federation-profile-wrapper -->
</div>

<script>
// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('visible'));
    window.addEventListener('offline', () => banner.classList.add('visible'));
    if (!navigator.onLine) banner.classList.add('visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>

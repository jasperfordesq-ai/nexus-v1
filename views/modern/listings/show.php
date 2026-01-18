<?php
// Phoenix Single Listing View - Modern Redesign v3
// Features: Full-Width Hero, Sidebar Info, Mapbox Integration, Attribute Grid, Likes & Comments
// NOTE: AJAX actions for likes/comments are handled in ListingController::handleListingAjax()

if (session_status() === PHP_SESSION_NONE) session_start();

// Auth Check
$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$tenantId = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::get()['id'] : ($_SESSION['current_tenant_id'] ?? 1);
$listingId = $listing['id'] ?? 0;

// ---------------------------------------------------------
// Fetch Like/Comment Counts for Display
// ---------------------------------------------------------
$likesCount = 0;
$commentsCount = 0;
$isLiked = false;

try {
    // Use PDO directly - DatabaseWrapper adds tenant constraints that can cause issues
    $pdo = \Nexus\Core\Database::getInstance();

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM likes WHERE target_type = 'listing' AND target_id = ?");
    $stmt->execute([$listingId]);
    $likesResult = $stmt->fetch();
    $likesCount = (int)($likesResult['cnt'] ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM comments WHERE target_type = 'listing' AND target_id = ?");
    $stmt->execute([$listingId]);
    $commentsResult = $stmt->fetch();
    $commentsCount = (int)($commentsResult['cnt'] ?? 0);

    if ($userId) {
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = 'listing' AND target_id = ?");
        $stmt->execute([$userId, $listingId]);
        $likedResult = $stmt->fetch();
        $isLiked = !empty($likedResult);
    }
} catch (\Throwable $e) {
    error_log("Listing stats error: " . $e->getMessage());
}

$hero_title = $listing['title'];
require __DIR__ . '/../../layouts/header.php';

// Safe Defaults
$accentColor = $listing['type'] === 'offer' ? '#0ea5e9' : '#f97316';
$currentUser = isset($_SESSION['user_id']) ? \Nexus\Models\User::findById($_SESSION['user_id']) : null;
$isOwner = ($currentUser && $currentUser['id'] == $listing['user_id']);
$isAdmin = ($currentUser && ($currentUser['role'] === 'admin' || $currentUser['is_super_admin'] == 1));
$canEdit = ($isOwner || $isAdmin);
?>

<!-- Mapbox Assets -->
<link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet">
<script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
<script>
    window.NEXUS_MAPBOX_TOKEN = "<?= getenv('MAPBOX_API_KEY') ?>";
</script>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div id="listing-show-glass-wrapper">
<!-- ✅ GLASSMORPHISM FILE LOADED: views/modern/listings/show.php - Last Modified: <?php echo date('Y-m-d H:i:s', filemtime(__FILE__)); ?> -->

<style>
    /* ===================================
       GLASSMORPHISM LISTING SHOW
       ================================= */

    /* Animated Gradient Background */
    #listing-show-glass-wrapper::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: -1;
        pointer-events: none;
    }

    [data-theme="light"] #listing-show-glass-wrapper::before {
        background: linear-gradient(135deg,
            rgba(99, 102, 241, 0.08) 0%,
            rgba(139, 92, 246, 0.08) 25%,
            rgba(236, 72, 153, 0.08) 50%,
            rgba(59, 130, 246, 0.08) 75%,
            rgba(16, 185, 129, 0.08) 100%);
        background-size: 400% 400%;
        animation: gradientShift 15s ease infinite;
    }

    [data-theme="dark"] #listing-show-glass-wrapper::before {
        background: radial-gradient(circle at 20% 30%,
            rgba(99, 102, 241, 0.15) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%,
            rgba(236, 72, 153, 0.12) 0%, transparent 50%);
    }

    @keyframes gradientShift {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
    }

    /* Glass Hero Card - Light Mode */
    #listing-show-glass-wrapper .glass-hero-card {
        background: linear-gradient(135deg,
            rgba(255, 255, 255, 0.8),
            rgba(255, 255, 255, 0.65));
        backdrop-filter: blur(24px) saturate(120%);
        -webkit-backdrop-filter: blur(24px) saturate(120%);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 24px;
        box-shadow: 0 10px 40px rgba(31, 38, 135, 0.15),
                    inset 0 1px 0 rgba(255, 255, 255, 0.6);
        overflow: hidden;
        margin-bottom: 30px;
    }

    [data-theme="dark"] #listing-show-glass-wrapper .glass-hero-card {
        background: linear-gradient(135deg,
            rgba(15, 23, 42, 0.65),
            rgba(30, 41, 59, 0.55));
        backdrop-filter: blur(28px) saturate(150%);
        -webkit-backdrop-filter: blur(28px) saturate(150%);
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6),
                    0 0 100px rgba(0, 200, 255, 0.12),
                    inset 0 1px 0 rgba(255, 255, 255, 0.1);
    }

    /* Glass Type Badge */
    #listing-show-glass-wrapper .glass-type-badge {
        background: linear-gradient(135deg,
            rgba(0, 0, 0, 0.8),
            rgba(30, 41, 59, 0.75));
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        color: #fff;
        padding: 6px 12px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
    }

    [data-theme="dark"] #listing-show-glass-wrapper .glass-type-badge {
        background: linear-gradient(135deg,
            rgba(99, 102, 241, 0.4),
            rgba(139, 92, 246, 0.35));
        border: 1px solid rgba(255, 255, 255, 0.25);
        box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3),
                    0 0 24px rgba(99, 102, 241, 0.2);
    }

    /* Glass Category Badge */
    #listing-show-glass-wrapper .glass-category-badge {
        background: linear-gradient(135deg,
            rgba(99, 102, 241, 0.15),
            rgba(139, 92, 246, 0.1));
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        padding: 2px 0;
        border-radius: 8px;
        display: inline-block;
    }

    [data-theme="dark"] #listing-show-glass-wrapper .glass-category-badge {
        background: linear-gradient(135deg,
            rgba(99, 102, 241, 0.25),
            rgba(139, 92, 246, 0.2));
    }

    /* Glass Description Card */
    #listing-show-glass-wrapper .glass-description-card {
        background: linear-gradient(135deg,
            rgba(255, 255, 255, 0.7),
            rgba(255, 255, 255, 0.55));
        backdrop-filter: blur(20px) saturate(120%);
        -webkit-backdrop-filter: blur(20px) saturate(120%);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.12),
                    inset 0 1px 0 rgba(255, 255, 255, 0.5);
        margin-bottom: 40px;
    }

    [data-theme="dark"] #listing-show-glass-wrapper .glass-description-card {
        background: linear-gradient(135deg,
            rgba(15, 23, 42, 0.6),
            rgba(30, 41, 59, 0.5));
        backdrop-filter: blur(24px) saturate(150%);
        -webkit-backdrop-filter: blur(24px) saturate(150%);
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                    0 0 60px rgba(0, 200, 255, 0.08),
                    inset 0 1px 0 rgba(255, 255, 255, 0.08);
    }

    /* Glass Attribute Pills - Light Mode */
    #listing-show-glass-wrapper .glass-attribute-pill {
        background: linear-gradient(135deg,
            rgba(255, 255, 255, 0.6),
            rgba(248, 250, 252, 0.5));
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(226, 232, 240, 0.5);
        border-radius: 12px;
        padding: 12px 15px;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(31, 38, 135, 0.08);
    }

    #listing-show-glass-wrapper .glass-attribute-pill:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 16px rgba(31, 38, 135, 0.15),
                    0 0 0 1px rgba(99, 102, 241, 0.2);
    }

    [data-theme="dark"] #listing-show-glass-wrapper .glass-attribute-pill {
        background: linear-gradient(135deg,
            rgba(30, 41, 59, 0.5),
            rgba(51, 65, 85, 0.4));
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }

    [data-theme="dark"] #listing-show-glass-wrapper .glass-attribute-pill:hover {
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.5),
                    0 0 0 1px rgba(99, 102, 241, 0.3);
    }

    /* Glass Map Container */
    #listing-show-glass-wrapper .glass-map-container {
        background: linear-gradient(135deg,
            rgba(255, 255, 255, 0.65),
            rgba(255, 255, 255, 0.5));
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        padding: 8px;
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
        margin-bottom: 40px;
    }

    [data-theme="dark"] #listing-show-glass-wrapper .glass-map-container {
        background: linear-gradient(135deg,
            rgba(15, 23, 42, 0.55),
            rgba(30, 41, 59, 0.45));
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    }

    /* Glass Author Card - Light Mode */
    #listing-show-glass-wrapper .glass-author-card {
        background: linear-gradient(135deg,
            rgba(255, 255, 255, 0.75),
            rgba(255, 255, 255, 0.6));
        backdrop-filter: blur(20px) saturate(120%);
        -webkit-backdrop-filter: blur(20px) saturate(120%);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.12),
                    inset 0 1px 0 rgba(255, 255, 255, 0.5);
        margin-bottom: 25px;
        position: sticky;
        top: 20px;
    }

    [data-theme="dark"] #listing-show-glass-wrapper .glass-author-card {
        background: linear-gradient(135deg,
            rgba(15, 23, 42, 0.6),
            rgba(30, 41, 59, 0.5));
        backdrop-filter: blur(24px) saturate(150%);
        -webkit-backdrop-filter: blur(24px) saturate(150%);
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                    0 0 60px rgba(0, 200, 255, 0.08),
                    inset 0 1px 0 rgba(255, 255, 255, 0.08);
    }

    /* Glass Avatar */
    #listing-show-glass-wrapper .glass-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(255, 255, 255, 0.5);
        box-shadow: 0 4px 12px rgba(31, 38, 135, 0.2);
    }

    [data-theme="dark"] #listing-show-glass-wrapper .glass-avatar {
        border: 2px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    }

    /* Glass SDG Card */
    #listing-show-glass-wrapper .glass-sdg-card {
        background: linear-gradient(135deg,
            rgba(255, 255, 255, 0.75),
            rgba(255, 255, 255, 0.6));
        backdrop-filter: blur(20px) saturate(120%);
        -webkit-backdrop-filter: blur(20px) saturate(120%);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 8px 32px rgba(31, 38, 135, 0.12),
                    inset 0 1px 0 rgba(255, 255, 255, 0.5);
    }

    [data-theme="dark"] #listing-show-glass-wrapper .glass-sdg-card {
        background: linear-gradient(135deg,
            rgba(15, 23, 42, 0.6),
            rgba(30, 41, 59, 0.5));
        backdrop-filter: blur(24px) saturate(150%);
        -webkit-backdrop-filter: blur(24px) saturate(150%);
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                    0 0 60px rgba(0, 200, 255, 0.08),
                    inset 0 1px 0 rgba(255, 255, 255, 0.08);
    }

    /* Glass SDG Item */
    #listing-show-glass-wrapper .glass-sdg-item {
        background: rgba(255, 255, 255, 0.4);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border-radius: 12px;
    }

    [data-theme="dark"] #listing-show-glass-wrapper .glass-sdg-item {
        background: rgba(30, 41, 59, 0.4);
    }

    /* Mobile Performance Optimization */
    @media (max-width: 900px) {
        #listing-show-glass-wrapper .glass-hero-card,
        #listing-show-glass-wrapper .glass-description-card,
        #listing-show-glass-wrapper .glass-author-card,
        #listing-show-glass-wrapper .glass-sdg-card {
            backdrop-filter: blur(14px) saturate(120%);
            -webkit-backdrop-filter: blur(14px) saturate(120%);
        }

        #listing-show-glass-wrapper .htb-page-layout > div {
            grid-template-columns: 1fr !important;
        }

        #listing-show-glass-wrapper .glass-author-card {
            position: static;
        }

        #listing-show-glass-wrapper .glass-hero-card {
            border-radius: 0 !important;
            margin: -20px -20px 20px -20px !important;
            border-left: none !important;
            border-right: none !important;
        }

        #listing-show-glass-wrapper .glass-hero-card > div:first-child {
            height: auto !important;
        }

        #listing-show-glass-wrapper .glass-hero-card img {
            height: 250px !important;
        }
    }

    @media (max-width: 768px) {
        /* Page layout mobile adjustments */
        #listing-show-glass-wrapper .htb-page-layout {
            margin-top: 0 !important;
            padding: 0 12px !important;
            padding-top: 20px !important;
            padding-bottom: 100px !important;
        }
    }

    @media (max-width: 640px) {
        #listing-show-glass-wrapper .glass-hero-card,
        #listing-show-glass-wrapper .glass-description-card,
        #listing-show-glass-wrapper .glass-author-card,
        #listing-show-glass-wrapper .glass-sdg-card {
            backdrop-filter: blur(8px) saturate(110%);
            -webkit-backdrop-filter: blur(8px) saturate(110%);
        }

        #listing-show-glass-wrapper .glass-attribute-pill:hover {
            transform: none;
        }
    }

    /* Fallback for unsupported browsers */
    @supports not (backdrop-filter: blur(10px)) {
        #listing-show-glass-wrapper .glass-hero-card,
        #listing-show-glass-wrapper .glass-description-card,
        #listing-show-glass-wrapper .glass-author-card,
        #listing-show-glass-wrapper .glass-sdg-card {
            background: rgba(255, 255, 255, 0.95);
        }

        [data-theme="dark"] #listing-show-glass-wrapper .glass-hero-card,
        [data-theme="dark"] #listing-show-glass-wrapper .glass-description-card,
        [data-theme="dark"] #listing-show-glass-wrapper .glass-author-card,
        [data-theme="dark"] #listing-show-glass-wrapper .glass-sdg-card {
            background: rgba(15, 23, 42, 0.95);
        }
    }

    /* ============================================
       GOLD STANDARD - Native App Features
       ============================================ */

    /* Skeleton Loaders */
    .listing-skeleton {
        display: none;
    }

    .listing-skeleton.visible {
        display: block;
    }

    .skeleton-hero {
        height: 400px;
        background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
        border-radius: 24px;
        margin-bottom: 30px;
    }

    [data-theme="dark"] .skeleton-hero {
        background: linear-gradient(90deg, #1e293b 25%, #334155 50%, #1e293b 75%);
        background-size: 200% 100%;
    }

    .skeleton-content {
        background: linear-gradient(135deg, rgba(255,255,255,0.7), rgba(255,255,255,0.5));
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
    }

    [data-theme="dark"] .skeleton-content {
        background: linear-gradient(135deg, rgba(15,23,42,0.6), rgba(30,41,59,0.5));
    }

    .skeleton-line {
        height: 12px;
        border-radius: 6px;
        background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%);
        background-size: 200% 100%;
        animation: skeleton-shimmer 1.5s infinite;
        margin-bottom: 12px;
    }

    [data-theme="dark"] .skeleton-line {
        background: linear-gradient(90deg, #1e293b 25%, #334155 50%, #1e293b 75%);
        background-size: 200% 100%;
    }

    .skeleton-line-short { width: 40%; }
    .skeleton-line-medium { width: 70%; }
    .skeleton-line-full { width: 100%; }

    @keyframes skeleton-shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* Content reveal animation */
    .listing-content {
        animation: fadeInUp 0.3s ease;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Offline Banner */
    .offline-banner {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 10001;
        padding: 10px 16px;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        font-size: 13px;
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

    /* Button Press States */
    .glass-cta-btn,
    button {
        transition: transform 0.1s ease !important;
        -webkit-tap-highlight-color: transparent;
    }

    .glass-cta-btn:active,
    button:active {
        transform: scale(0.96) !important;
    }

    #comment-input {
        min-height: 44px;
        font-size: 16px !important;
    }

    button[type="submit"] {
        min-height: 44px;
    }

    /* Focus Visible */
    a:focus-visible,
    button:focus-visible,
    input:focus-visible {
        outline: 3px solid rgba(99, 102, 241, 0.5);
        outline-offset: 2px;
    }

    /* Smooth Scroll */
    html {
        scroll-behavior: smooth;
    }

    /* Images - lazy loading with aspect ratio */
    .glass-hero-card img {
        background: #f3f4f6;
    }

    [data-theme="dark"] .glass-hero-card img {
        background: #1e293b;
    }

    /* Edit Listing Button */
    .listing-edit-btn {
        background: #f1f5f9;
        color: #334155;
    }

    [data-theme="dark"] .listing-edit-btn {
        background: rgba(51, 65, 85, 0.6);
        color: #e2e8f0;
    }

    [data-theme="dark"] .listing-edit-btn:hover {
        background: rgba(71, 85, 105, 0.8);
    }

    /* Comment Input Dark Mode */
    [data-theme="dark"] #comment-input {
        background: rgba(30, 41, 59, 0.8) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        color: #f1f5f9 !important;
    }

    [data-theme="dark"] #comment-input::placeholder {
        color: #64748b;
    }

    /* Reply Input Dark Mode */
    [data-theme="dark"] input[id^="reply-input-"],
    [data-theme="dark"] input[id^="edit-input-"] {
        background: rgba(30, 41, 59, 0.8) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        color: #f1f5f9 !important;
    }

    /* Comment Background Dark Mode */
    [data-theme="dark"] #comments-list > div {
        background: rgba(30, 41, 59, 0.4) !important;
    }

    /* Reaction Picker Dark Mode */
    [data-theme="dark"] .reaction-picker > div[id^="picker-"] {
        background: #1e293b !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
    }

    /* Sign-in CTA Dark Mode */
    [data-theme="dark"] #comments-section > div[style*="background: rgba(100,116,139,0.05)"] {
        background: rgba(30, 41, 59, 0.4) !important;
    }

    /* Like button animation */
    @keyframes likePopScale {
        0% { transform: scale(1); }
        25% { transform: scale(1.3); }
        50% { transform: scale(0.9); }
        100% { transform: scale(1); }
    }

    .like-pop {
        animation: likePopScale 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    /* Heart burst */
    @keyframes heartBurst {
        0% { opacity: 1; transform: scale(1) translate(0, 0); }
        100% { opacity: 0; transform: scale(0.3) translate(var(--tx), var(--ty)); }
    }
</style>

<div class="htb-page-layout" style="max-width: 1100px; margin: 0 auto; padding: 160px 60px 40px 60px; font-family: 'Inter', sans-serif;">

    <div style="display: grid; grid-template-columns: 1fr 340px; gap: 40px; align-items: start;">

        <!-- LEFT COLUMN: Content -->
        <div style="position: relative; z-index: 50;">
            <!-- 1. Hero Image & Title -->
            <div class="glass-hero-card listing-content">
                <?php if (!empty($listing['image_url'])): ?>
                    <div style="width: 100%; height: 400px; position: relative;">
                        <?= webp_image($listing['image_url'], htmlspecialchars($listing['title']), '', ['style' => 'width: 100%; height: 100%; object-fit: cover; display: block;']) ?>
                        <div style="position: absolute; top: 20px; left: 20px;">
                            <span class="glass-type-badge">
                                <?= ucfirst($listing['type']) ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="padding: 30px;">
                    <div class="glass-category-badge" style="margin-bottom: 15px; color: <?= $accentColor ?>; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; font-size: 0.85rem;">
                        <?= htmlspecialchars($listing['category_name'] ?? 'General') ?>
                    </div>
                    <h1 style="margin: 0 0 15px 0; font-size: 2.5rem; line-height: 1.2; color: var(--htb-text-main, #0f172a);"><?= htmlspecialchars($listing['title']) ?></h1>

                    <div style="display: flex; align-items: center; gap: 15px; color: var(--htb-text-muted, #64748b); font-size: 0.95rem;">
                        <span><i class="fa-regular fa-calendar"></i> <?= date('M j, Y', strtotime($listing['created_at'])) ?></span>
                        <?php if (!empty($listing['location'])): ?>
                            <span>&bull;</span>
                            <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($listing['location']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 2. Description -->
            <div class="glass-description-card">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin-bottom: 15px;">About this listing</h3>
                <div style="font-size: 1.1rem; line-height: 1.7; color: var(--htb-text-muted); white-space: pre-line;">
                    <?= htmlspecialchars($listing['description']) ?>
                </div>
            </div>

            <!-- 2.5. Like & Comment Section -->
            <div class="glass-description-card" id="listing-engagement-section" data-listing-id="<?= $listingId ?>">
                <!-- Like Button Row -->
                <div style="display: flex; align-items: center; gap: 20px; padding-bottom: 20px; border-bottom: 1px solid rgba(100,116,139,0.2); flex-wrap: wrap;">
                    <button id="like-btn" onclick="listingToggleLike()" style="display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; border: none; cursor: pointer; font-weight: 600; font-size: 0.95rem; transition: all 0.2s ease; background: <?= $isLiked ? 'linear-gradient(135deg, #ec4899, #f43f5e)' : 'rgba(100,116,139,0.1)' ?>; color: <?= $isLiked ? '#fff' : 'var(--htb-text-main)' ?>;">
                        <i class="<?= $isLiked ? 'fa-solid' : 'fa-regular' ?> fa-heart" id="like-icon"></i>
                        <span id="like-count"><?= $likesCount ?></span>
                        <span><?= $likesCount === 1 ? 'Like' : 'Likes' ?></span>
                    </button>
                    <button onclick="listingToggleComments()" style="display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; border: none; cursor: pointer; font-weight: 600; font-size: 0.95rem; transition: all 0.2s ease; background: rgba(100,116,139,0.1); color: var(--htb-text-main);">
                        <i class="fa-regular fa-comment"></i>
                        <span id="comment-count"><?= $commentsCount ?></span>
                        <span><?= $commentsCount === 1 ? 'Comment' : 'Comments' ?></span>
                    </button>
                    <?php if ($isLoggedIn): ?>
                    <button onclick="shareToFeed()" style="display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; border: none; cursor: pointer; font-weight: 600; font-size: 0.95rem; transition: all 0.2s ease; background: rgba(100,116,139,0.1); color: var(--htb-text-main);">
                        <i class="fa-solid fa-share"></i> Share
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Comments Section (Initially Hidden) -->
                <div id="comments-section" style="display: none; padding-top: 20px;">
                    <h4 style="font-size: 1rem; font-weight: 700; color: var(--htb-text-main); margin-bottom: 15px;">Comments</h4>

                    <!-- Comment Form -->
                    <?php if ($isLoggedIn): ?>
                        <form id="comment-form" onsubmit="listingSubmitComment(event)" style="display: flex; gap: 12px; margin-bottom: 20px;">
                            <?= webp_avatar($_SESSION['user_avatar'] ?? null, $_SESSION['user_name'] ?? 'User', 40) ?>
                            <div style="flex: 1; display: flex; flex-direction: column; gap: 10px;">
                                <textarea id="comment-input" placeholder="Write a comment..." style="width: 100%; min-height: 60px; padding: 12px; border-radius: 12px; border: 1px solid rgba(100,116,139,0.3); background: rgba(255,255,255,0.5); font-size: 0.95rem; resize: vertical; font-family: inherit;"></textarea>
                                <button type="submit" style="align-self: flex-end; padding: 8px 20px; border-radius: 8px; border: none; background: <?= $accentColor ?>; color: #fff; font-weight: 600; cursor: pointer; transition: opacity 0.2s;">
                                    Post Comment
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; background: rgba(100,116,139,0.05); border-radius: 12px; margin-bottom: 20px;">
                            <p style="color: var(--htb-text-muted); margin: 0 0 10px;">
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" style="color: <?= $accentColor ?>; font-weight: 600; text-decoration: none;">Sign in</a> to leave a comment
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Comments List -->
                    <div id="comments-list" style="display: flex; flex-direction: column; gap: 15px;">
                        <div style="text-align: center; color: var(--htb-text-muted); padding: 20px;">
                            <i class="fa-solid fa-spinner fa-spin"></i> Loading comments...
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Attributes Grid -->
            <?php if (!empty($attributes)): ?>
                <div style="margin-bottom: 40px;">
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin-bottom: 15px;">Features</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                        <?php foreach ($attributes as $attr): ?>
                            <div class="glass-attribute-pill">
                                <i class="fa-solid fa-check" style="color: <?= $accentColor ?>;"></i>
                                <span style="font-weight: 600; color: var(--htb-text-main);"><?= htmlspecialchars($attr['name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 4. Location Map -->
            <?php if (!empty($listing['location']) || (!empty($listing['latitude']) && !empty($listing['longitude']))): ?>
                <div class="glass-map-container">
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--htb-text-main); margin-bottom: 15px;">Location</h3>

                    <?php if (!empty($listing['location'])): ?>
                        <div style="margin-bottom: 15px; font-size: 1.05rem; color: var(--htb-text-muted); display: flex; align-items: center; gap: 10px;">
                            <i class="fa-solid fa-location-dot" style="color: var(--htb-text-muted, #64748b);"></i>
                            <span><?= htmlspecialchars($listing['location']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($listing['latitude']) && !empty($listing['longitude'])): ?>
                        <div id="listing-map" style="width: 100%; height: 300px; border-radius: 12px; overflow: hidden;"></div>
                        <script>
                            document.addEventListener('DOMContentLoaded', () => {
                                if (!window.mapboxgl) return;
                                mapboxgl.accessToken = window.NEXUS_MAPBOX_TOKEN;
                                const map = new mapboxgl.Map({
                                    container: 'listing-map',
                                    style: 'mapbox://styles/mapbox/streets-v11',
                                    center: [<?= $listing['longitude'] ?>, <?= $listing['latitude'] ?>],
                                    zoom: 13
                                });
                                new mapboxgl.Marker({
                                        color: '<?= $accentColor ?>'
                                    })
                                    .setLngLat([<?= $listing['longitude'] ?>, <?= $listing['latitude'] ?>])
                                    .addTo(map);
                            });
                        </script>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT COLUMN: Sidebar -->
        <aside style="position: relative; z-index: 50;">
            <!-- Author Card -->
            <div class="glass-author-card">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                    <?= webp_avatar($listing['avatar_url'] ?? null, $listing['author_name'], 56) ?>
                    <div>
                        <div style="font-weight: 700; color: var(--htb-text-main); font-size: 1.1rem;"><?= htmlspecialchars($listing['author_name']) ?></div>
                        <div style="color: var(--htb-text-muted); font-size: 0.9rem;">Member</div>
                    </div>
                </div>

                <?php if ($canEdit): ?>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings/edit/<?= $listing['id'] ?>" class="listing-edit-btn" style="display: block; width: 100%; text-align: center; padding: 12px; font-weight: 700; text-decoration: none; border-radius: 8px; transition: background 0.2s;">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Listing
                    </a>
                <?php else: ?>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/messages/<?= $listing['user_id'] ?>?ref=<?= urlencode("Re: " . $listing['title']) ?>" style="display: block; width: 100%; text-align: center; padding: 12px; background: <?= $accentColor ?>; color: #fff; font-weight: 700; text-decoration: none; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); transition: opacity 0.2s;">
                        Message Author
                    </a>
                <?php endif; ?>
            </div>

            <!-- SDGs Sidebar -->
            <?php if (!empty($listing['sdg_goals'])):
                $goals = json_decode($listing['sdg_goals'], true);
                if (is_array($goals) && count($goals) > 0):
                    require_once __DIR__ . '/../../../src/Helpers/SDG.php';
            ?>
                    <div class="glass-sdg-card">
                        <h4 style="margin: 0 0 15px 0; color: var(--htb-text-muted); text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px;">Social Impact</h4>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <?php foreach ($goals as $gid):
                                $goal = \Nexus\Helpers\SDG::get($gid);
                                if (!$goal) continue;
                            ?>
                                <div class="glass-sdg-item" style="display: flex; align-items: center; gap: 10px; padding: 8px; border-left: 3px solid <?= $goal['color'] ?>;">
                                    <span><?= $goal['icon'] ?></span>
                                    <span style="font-weight: 600; font-size: 0.9rem; color: var(--htb-text-main);"><?= $goal['label'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
            <?php endif;
            endif; ?>
        </aside>

    </div>
</div>

</div><!-- #listing-show-glass-wrapper -->

<!-- JavaScript for Like/Comment Functionality - Using Master Platform Social Media Module -->
<script>
(function() {
    const listingId = <?= $listingId ?>;
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
    let isLiked = <?= $isLiked ? 'true' : 'false' ?>;
    let commentsLoaded = false;
    let availableReactions = [];

    // Master Platform Social Media Module API Base
    const API_BASE = '<?= \Nexus\Core\TenantContext::getBasePath() ?>/api/social';

    // Toggle Like - Using Master Social Module API (unique name to avoid conflict with social-interactions.js)
    window.listingToggleLike = async function() {
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
                    target_type: 'listing',
                    target_id: listingId
                })
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Like API error response:', response.status, errorText);
                alert('Like failed: ' + (response.status === 401 ? 'Please log in' : 'Server error'));
                return;
            }

            const data = await response.json();

            if (data.error) {
                if (data.redirect) window.location.href = data.redirect;
                else {
                    console.error('Like error:', data.error);
                    alert('Like failed: ' + data.error);
                }
                return;
            }

            isLiked = (data.status === 'liked');
            countEl.textContent = data.likes_count;

            if (isLiked) {
                btn.style.background = 'linear-gradient(135deg, #ec4899, #f43f5e)';
                btn.style.color = '#fff';
                icon.className = 'fa-solid fa-heart';
            } else {
                btn.style.background = 'rgba(100,116,139,0.1)';
                btn.style.color = 'var(--htb-text-main)';
                icon.className = 'fa-regular fa-heart';
            }

        } catch (err) {
            console.error('Like error:', err);
        } finally {
            btn.disabled = false;
        }
    };

    // Toggle Comments Section (unique name to avoid conflict with social-interactions.js)
    window.listingToggleComments = function() {
        // Check if mobile (screen width <= 768px or touch device)
        const isMobile = window.innerWidth <= 768 || ('ontouchstart' in window);

        if (isMobile && typeof openMobileCommentSheet === 'function') {
            // Use mobile drawer on mobile devices
            openMobileCommentSheet('listing', listingId, '');
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

    // Load Comments - Using Master Social Module API
    async function loadComments() {
        const list = document.getElementById('comments-list');
        list.innerHTML = '<p style="color: var(--htb-text-muted); text-align: center;">Loading comments...</p>';

        try {
            const response = await fetch(API_BASE + '/comments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'fetch',
                    target_type: 'listing',
                    target_id: listingId
                })
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Comments API error:', response.status, errorText);
                list.innerHTML = '<p style="color: var(--htb-text-muted); text-align: center;">Failed to load comments (HTTP ' + response.status + ')</p>';
                return;
            }

            const data = await response.json();

            if (data.error) {
                list.innerHTML = '<p style="color: var(--htb-text-muted); text-align: center;">Failed to load comments: ' + data.error + '</p>';
                return;
            }

            commentsLoaded = true;
            availableReactions = data.available_reactions || [];

            if (!data.comments || data.comments.length === 0) {
                list.innerHTML = '<p style="color: var(--htb-text-muted); text-align: center; padding: 20px;">No comments yet. Be the first to comment!</p>';
                return;
            }

            list.innerHTML = data.comments.map(c => renderComment(c, 0)).join('');

        } catch (err) {
            console.error('Load comments error:', err);
            list.innerHTML = '<p style="color: var(--htb-text-muted); text-align: center;">Error loading comments</p>';
        }
    }

    // Render Comment with Nested Replies
    function renderComment(c, depth) {
        const indent = depth * 20;
        const isEdited = c.is_edited ? '<span style="font-size: 0.7rem; color: var(--htb-text-muted);"> (edited)</span>' : '';
        const ownerActions = c.is_owner ? `
            <span onclick="listingEditComment(${c.id}, '${escapeHtml(c.content).replace(/'/g, "\\'")}')" style="cursor: pointer; margin-left: 10px;" title="Edit">✏️</span>
            <span onclick="listingDeleteComment(${c.id})" style="cursor: pointer; margin-left: 5px;" title="Delete">🗑️</span>
        ` : '';

        const reactions = Object.entries(c.reactions || {}).map(([emoji, count]) => {
            const isUserReaction = (c.user_reactions || []).includes(emoji);
            return `<span onclick="listingToggleReaction(${c.id}, '${emoji}')" style="cursor: pointer; padding: 2px 6px; border-radius: 12px; font-size: 0.8rem; background: ${isUserReaction ? 'rgba(99, 102, 241, 0.2)' : 'rgba(100,116,139,0.1)'}; border: 1px solid ${isUserReaction ? 'rgba(99, 102, 241, 0.4)' : 'rgba(100,116,139,0.2)'};">${emoji} ${count}</span>`;
        }).join(' ');

        const reactionPicker = isLoggedIn ? `
            <div class="reaction-picker" style="display: inline-block; position: relative;">
                <span onclick="listingShowReactionPicker(${c.id})" style="cursor: pointer; padding: 2px 6px; border-radius: 12px; font-size: 0.8rem; background: rgba(100,116,139,0.1); border: 1px solid rgba(100,116,139,0.2);">+</span>
                <div id="picker-${c.id}" style="display: none; position: absolute; bottom: 100%; left: 0; background: var(--htb-card-bg, #fff); border: 1px solid rgba(100,116,139,0.2); border-radius: 8px; padding: 5px; z-index: 100; white-space: nowrap; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                    ${availableReactions.map(e => `<span onclick="listingToggleReaction(${c.id}, '${e}')" style="cursor: pointer; padding: 3px; font-size: 1.2rem;">${e}</span>`).join('')}
                </div>
            </div>
        ` : '';

        const replyButton = isLoggedIn ? `<span onclick="listingShowReplyForm(${c.id})" style="cursor: pointer; color: <?= $accentColor ?>; font-size: 0.8rem; margin-left: 10px;">Reply</span>` : '';

        const replies = (c.replies || []).map(r => renderComment(r, depth + 1)).join('');

        return `
            <div style="margin-left: ${indent}px; padding: 12px; background: rgba(100,116,139,0.05); border-radius: 12px; margin-bottom: 10px;" id="comment-${c.id}">
                <div style="display: flex; gap: 12px;">
                    <img src="${c.author_avatar}" loading="lazy" style="width: ${depth > 0 ? 28 : 36}px; height: ${depth > 0 ? 28 : 36}px; border-radius: 50%; object-fit: cover; flex-shrink: 0;">
                    <div style="flex: 1;">
                        <div style="font-weight: 600; font-size: 0.9rem; color: var(--htb-text-main);">
                            ${escapeHtml(c.author_name)}${isEdited}
                            ${ownerActions}
                        </div>
                        <div id="content-${c.id}" style="color: var(--htb-text-main); margin-top: 4px;">${formatContent(c.content)}</div>
                        <div style="font-size: 0.75rem; color: var(--htb-text-muted); margin-top: 4px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            ${formatTime(c.created_at)}
                            ${replyButton}
                        </div>
                        <div style="margin-top: 6px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            ${reactions}
                            ${reactionPicker}
                        </div>
                        <div id="reply-form-${c.id}" style="display: none; margin-top: 10px;">
                            <div style="display: flex; gap: 8px;">
                                <input type="text" id="reply-input-${c.id}" placeholder="Write a reply..." style="flex: 1; padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(100,116,139,0.3); background: rgba(255,255,255,0.5); color: var(--htb-text-main); font-size: 0.85rem;">
                                <button onclick="listingSubmitReply(${c.id})" style="padding: 8px 16px; border-radius: 8px; background: <?= $accentColor ?>; color: white; border: none; cursor: pointer; font-size: 0.85rem;">Reply</button>
                            </div>
                        </div>
                    </div>
                </div>
                ${replies}
            </div>
        `;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatContent(content) {
        return escapeHtml(content).replace(/@(\w+)/g, '<span style="color: <?= $accentColor ?>; font-weight: 600;">@$1</span>');
    }

    function formatTime(datetime) {
        try {
            const date = new Date(datetime);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        } catch (e) { return ''; }
    }

    window.listingShowReactionPicker = function(commentId) {
        const picker = document.getElementById(`picker-${commentId}`);
        picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
    };

    window.listingShowReplyForm = function(commentId) {
        const form = document.getElementById(`reply-form-${commentId}`);
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') {
            document.getElementById(`reply-input-${commentId}`).focus();
        }
    };

    window.listingToggleReaction = async function(commentId, emoji) {
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

    window.listingSubmitReply = async function(parentId) {
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
                    target_type: 'listing',
                    target_id: listingId,
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

    window.listingDeleteComment = async function(commentId) {
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

    window.listingEditComment = function(commentId, currentContent) {
        const contentEl = document.getElementById(`content-${commentId}`);
        const originalHtml = contentEl.innerHTML;

        contentEl.innerHTML = `
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <input type="text" id="edit-input-${commentId}" value="${escapeHtml(currentContent)}" style="flex: 1; min-width: 200px; padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(100,116,139,0.3); background: rgba(255,255,255,0.5); color: var(--htb-text-main);">
                <button onclick="saveEdit(${commentId})" style="padding: 8px 16px; border-radius: 8px; background: <?= $accentColor ?>; color: white; border: none; cursor: pointer;">Save</button>
                <button onclick="cancelEdit(${commentId}, '${escapeHtml(originalHtml).replace(/'/g, "\\'")}')" style="padding: 8px 16px; border-radius: 8px; background: rgba(100,116,139,0.1); border: 1px solid rgba(100,116,139,0.2); color: var(--htb-text-main); cursor: pointer;">Cancel</button>
            </div>
        `;
        document.getElementById(`edit-input-${commentId}`).focus();
    };

    window.cancelEdit = function(commentId, originalHtml) {
        document.getElementById(`content-${commentId}`).innerHTML = originalHtml;
    };

    window.saveEdit = async function(commentId) {
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

    // Submit Comment - Using Master Social Module API (unique name to avoid conflict with social-interactions.js)
    window.listingSubmitComment = async function(e) {
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
                    target_type: 'listing',
                    target_id: listingId,
                    content: content
                })
            });

            const data = await response.json();

            if (data.error) { alert(data.error); return; }

            input.value = '';
            const countEl = document.getElementById('comment-count');
            countEl.textContent = parseInt(countEl.textContent) + 1;
            commentsLoaded = false;
            loadComments();

        } catch (err) {
            console.error('Submit comment error:', err);
            alert('Failed to post comment');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Post Comment';
        }
    };

    // Share to Feed - Using Master Social Module API
    window.shareToFeed = async function() {
        if (!confirm('Share this listing to your feed?')) return;

        try {
            const response = await fetch(API_BASE + '/share', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    parent_type: 'listing',
                    parent_id: listingId
                })
            });

            const data = await response.json();

            if (data.error) { alert(data.error); return; }
            if (data.status === 'success') {
                alert('Listing shared to your feed!');
            }
        } catch (err) {
            console.error('Share error:', err);
            alert('Failed to share listing');
        }
    };

    // ============================================
    // GOLD STANDARD - Native App Features
    // ============================================

    // Offline Indicator
    (function initOfflineIndicator() {
        const banner = document.getElementById('offlineBanner');
        if (!banner) return;

        let wasOffline = false;

        function handleOffline() {
            wasOffline = true;
            banner.classList.add('visible');
            if (navigator.vibrate) navigator.vibrate(100);
        }

        function handleOnline() {
            banner.classList.remove('visible');
            wasOffline = false;
        }

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        if (!navigator.onLine) {
            handleOffline();
        }
    })();

    // Heart Burst Animation for Likes
    window.createHeartBurst = function(element) {
        const rect = element.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        const hearts = ['❤️', '💜', '💙', '🧡', '💗'];

        for (let i = 0; i < 6; i++) {
            const heart = document.createElement('div');
            heart.textContent = hearts[Math.floor(Math.random() * hearts.length)];
            heart.style.cssText = `
                position: fixed;
                left: ${centerX}px;
                top: ${centerY}px;
                font-size: ${16 + Math.random() * 10}px;
                pointer-events: none;
                z-index: 10000;
                animation: heartBurst ${0.6 + Math.random() * 0.3}s ease-out forwards;
                --tx: ${(Math.random() - 0.5) * 120}px;
                --ty: ${-60 - Math.random() * 80}px;
            `;
            document.body.appendChild(heart);
            setTimeout(() => heart.remove(), 1000);
        }
    };

    // Enhanced Like Toggle with animation
    const originalToggleLike = window.toggleLike;
    window.toggleLike = async function() {
        const likeBtn = document.getElementById('like-btn');
        const icon = likeBtn.querySelector('i');
        const wasLiked = likeBtn.dataset.liked === 'true';

        // If liking, add animation
        if (!wasLiked) {
            icon.classList.add('like-pop');
            createHeartBurst(likeBtn);
            if (navigator.vibrate) {
                navigator.vibrate([10, 50, 20]);
            }
            setTimeout(() => icon.classList.remove('like-pop'), 400);
        }

        // Call original function
        await originalToggleLike();
    };

    // Dynamic Theme Color
    (function initDynamicThemeColor() {
        const themeColorMeta = document.querySelector('meta[name="theme-color"]');
        if (!themeColorMeta) return;

        function updateThemeColor() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            themeColorMeta.setAttribute('content', isDark ? '#0f172a' : '#ffffff');
        }

        const observer = new MutationObserver(updateThemeColor);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme']
        });

        updateThemeColor();
    })();

    // Button Press States
    document.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('pointerdown', function() {
            this.style.transform = 'scale(0.96)';
        });
        btn.addEventListener('pointerup', function() {
            this.style.transform = 'scale(1)';
        });
        btn.addEventListener('pointerleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
})();
</script>

<?php
// Mobile Bottom Sheets - Now included centrally in footer.php
?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
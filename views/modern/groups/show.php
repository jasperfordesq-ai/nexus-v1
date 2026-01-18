<?php
/**
 * ╔═══════════════════════════════════════════════════════════════════════════╗
 * ║  NEXUS GROUPS SHOW PAGE - HOLOGRAPHIC GLASSMORPHISM 2025                  ║
 * ║  Premium Mobile-First Design with High-End Visual Effects                 ║
 * ║  Path: views/modern/groups/show.php                                       ║
 * ╚═══════════════════════════════════════════════════════════════════════════╝
 */

$pageTitle = htmlspecialchars($group['name']);
$pageSubtitle = !empty($group['description']) ? htmlspecialchars(mb_strimwidth($group['description'], 0, 200, '...')) : 'Community Hub';
$hideHero = true; // Custom hero implementation below

Nexus\Core\SEO::setTitle($group['name']);
Nexus\Core\SEO::setDescription($group['description']);

require dirname(__DIR__, 2) . '/layouts/modern/header.php';

// Helper for avatars
if (!function_exists('get_phoenix_avatar')) {
    function get_phoenix_avatar($url) {
        return $url ?: '/assets/images/default-avatar.svg';
    }
}

$currentUserId = $_SESSION['user_id'] ?? 0;

// Check if current user has pending membership status
$isPending = false;
if ($currentUserId && !$isMember) {
    $membershipStatus = \Nexus\Models\Group::getMembershipStatus($group['id'], $currentUserId);
    $isPending = ($membershipStatus === 'pending');
}
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

.holo-main-card {
    animation: fadeInUp 0.4s ease-out;
}

/* Button Press States */
.holo-tab-btn:active,
.holo-section-action:active,
.holo-member-btn:active,
button:active {
    transform: scale(0.96) !important;
    transition: transform 0.1s ease !important;
}

/* Touch Targets - WCAG 2.1 AA (44px minimum) */
.holo-tab-btn,
.holo-section-action,
.holo-member-btn,
button,
input[type="text"],
input[type="email"],
textarea {
    min-height: 44px;
}

input[type="text"],
input[type="email"],
textarea {
    font-size: 16px !important; /* Prevent iOS zoom */
}

/* Focus Visible */
.holo-tab-btn:focus-visible,
.holo-section-action:focus-visible,
.holo-member-btn:focus-visible,
button:focus-visible,
a:focus-visible,
input:focus-visible,
textarea:focus-visible {
    outline: 3px solid rgba(219, 39, 119, 0.5);
    outline-offset: 2px;
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

/* Mobile Responsive - Gold Standard */
@media (max-width: 768px) {
    .holo-tab-btn,
    .holo-section-action,
    .holo-member-btn,
    button {
        min-height: 48px;
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   HOLOGRAPHIC GLASSMORPHISM DESIGN SYSTEM
   Premium Mobile-First Interface for NEXUS Groups
   ═══════════════════════════════════════════════════════════════════════════ */

/* ─────────────────────────────────────────────────────────────────────────────
   CSS VARIABLES - Design Tokens
   ───────────────────────────────────────────────────────────────────────────── */
:root {
    --holo-primary: #db2777;
    --holo-primary-light: #ec4899;
    --holo-primary-rgb: 219, 39, 119;
    --holo-glass-light: rgba(255, 255, 255, 0.75);
    --holo-glass-dark: rgba(15, 23, 42, 0.75);
    --holo-border-light: rgba(255, 255, 255, 0.4);
    --holo-border-dark: rgba(255, 255, 255, 0.1);
    --holo-blur: 20px;
    --holo-radius: 24px;
    --holo-radius-sm: 16px;
    --holo-radius-xs: 12px;
    --holo-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
    --holo-shadow-hover: 0 16px 48px rgba(0, 0, 0, 0.12);
    --holo-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --safe-bottom: env(safe-area-inset-bottom, 0px);
}

/* ─────────────────────────────────────────────────────────────────────────────
   HOLOGRAPHIC ANIMATED BACKGROUND
   ───────────────────────────────────────────────────────────────────────────── */
.holo-bg {
    position: fixed;
    inset: 0;
    z-index: -1;
    overflow: hidden;
    background: linear-gradient(135deg, #fdf4ff 0%, #fce7f3 25%, #fdf2f8 50%, #fff1f2 75%, #fdf4ff 100%);
}

.holo-bg::before,
.holo-bg::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.6;
    animation: holoFloat 20s ease-in-out infinite;
}

.holo-bg::before {
    width: 60vmax;
    height: 60vmax;
    top: -30vmax;
    left: -20vmax;
    background: radial-gradient(circle, rgba(var(--holo-primary-rgb), 0.15) 0%, transparent 70%);
}

.holo-bg::after {
    width: 50vmax;
    height: 50vmax;
    bottom: -25vmax;
    right: -15vmax;
    background: radial-gradient(circle, rgba(168, 85, 247, 0.12) 0%, transparent 70%);
    animation-delay: -10s;
    animation-direction: reverse;
}

[data-theme="dark"] .holo-bg {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 25%, #1e293b 50%, #312e81 75%, #0f172a 100%);
}

[data-theme="dark"] .holo-bg::before {
    background: radial-gradient(circle, rgba(var(--holo-primary-rgb), 0.25) 0%, transparent 70%);
}

[data-theme="dark"] .holo-bg::after {
    background: radial-gradient(circle, rgba(139, 92, 246, 0.2) 0%, transparent 70%);
}

@keyframes holoFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(3%, -3%) scale(1.05); }
    66% { transform: translate(-2%, 2%) scale(0.98); }
}

/* Reduce animation on mobile for performance */
@media (max-width: 768px) {
    .holo-bg::before,
    .holo-bg::after {
        animation: none;
        opacity: 0.4;
    }
}

/* ─────────────────────────────────────────────────────────────────────────────
   PAGE LAYOUT CONTAINER
   ───────────────────────────────────────────────────────────────────────────── */
.holo-page {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    padding: 0 0 120px 0; /* No top padding - hero starts at top */
}

@media (max-width: 900px) {
    .holo-page {
        padding-bottom: 80px;
    }
}

.holo-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 0 20px;
}

@media (max-width: 768px) {
    .holo-container {
        padding: 0 0;
    }
}

@media (max-width: 480px) {
    .holo-container {
        padding: 0 0;
    }
}

/* ─────────────────────────────────────────────────────────────────────────────
   MODERN HERO SECTION
   ───────────────────────────────────────────────────────────────────────────── */
.modern-hero {
    position: relative;
    min-height: 400px;
    display: flex;
    align-items: flex-end;
    padding: 80px 24px 48px;
    margin-top: 72px; /* Start below the header - no overlap */
    margin-bottom: 24px;
    overflow: hidden;
}

.modern-hero__cover {
    position: absolute;
    inset: 0;
    z-index: 1;
}

.modern-hero__cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.modern-hero__gradient {
    position: absolute;
    inset: 0;
    z-index: 2;
}

.modern-hero__gradient.htb-hero-gradient-hub {
    background: linear-gradient(135deg,
        rgba(219, 39, 119, 0.9) 0%,
        rgba(236, 72, 153, 0.85) 50%,
        rgba(244, 114, 182, 0.8) 100%);
}

.modern-hero--with-cover .modern-hero__gradient {
    background: linear-gradient(180deg,
        rgba(0, 0, 0, 0.1) 0%,
        rgba(0, 0, 0, 0.4) 60%,
        rgba(0, 0, 0, 0.7) 100%);
}

.modern-hero__content {
    position: relative;
    z-index: 3;
    text-align: center;
    color: white;
    max-width: 800px;
    margin: 0 auto;
    width: 100%;
}

.modern-hero__badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-radius: 24px;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 16px;
}

.modern-hero__title {
    margin: 0 0 16px;
    font-size: 2.5rem;
    font-weight: 800;
    line-height: 1.2;
    text-shadow: 0 2px 20px rgba(0, 0, 0, 0.4);
}

.modern-hero__meta {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    margin-bottom: 16px;
    font-size: 1rem;
    flex-wrap: wrap;
}

.modern-hero__meta span {
    display: flex;
    align-items: center;
    gap: 8px;
}

.modern-hero__description {
    margin: 0;
    font-size: 1.05rem;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.95);
    text-shadow: 0 1px 10px rgba(0, 0, 0, 0.3);
}

@media (max-width: 768px) {
    .modern-hero {
        min-height: 340px;
        padding: 64px 20px 36px;
        margin-top: 64px; /* Start below mobile header - no overlap */
    }

    .modern-hero__title {
        font-size: 1.75rem;
    }

    .modern-hero__meta {
        font-size: 0.9rem;
        gap: 16px;
    }

    .modern-hero__description {
        font-size: 0.95rem;
    }
}

/* Group Actions Bar */
.group-actions-bar {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.member-badge,
.pending-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.member-badge {
    background: rgba(21, 128, 61, 0.1);
    color: #15803d;
}

.pending-badge {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
}

.btn-primary,
.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #db2777, #ec4899);
    color: white;
    box-shadow: 0 4px 15px rgba(219, 39, 119, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(219, 39, 119, 0.4);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    color: var(--text-primary, #111827);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
}


/* ─────────────────────────────────────────────────────────────────────────────
   MAIN CONTENT CARD - Glass Container
   ───────────────────────────────────────────────────────────────────────────── */
.holo-main-card {
    position: relative;
    background: var(--holo-glass-light);
    backdrop-filter: blur(var(--holo-blur));
    -webkit-backdrop-filter: blur(var(--holo-blur));
    border: 1px solid var(--holo-border-light);
    border-radius: var(--holo-radius) var(--holo-radius) 0 0;
    box-shadow: var(--holo-shadow);
    overflow: hidden;
    min-height: calc(100vh - 280px);
}

[data-theme="dark"] .holo-main-card {
    background: var(--holo-glass-dark);
    border-color: var(--holo-border-dark);
}

/* Holographic shimmer effect on card edge */
.holo-main-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg,
        transparent,
        rgba(255, 255, 255, 0.8) 20%,
        rgba(255, 255, 255, 0.8) 80%,
        transparent);
}

@media (max-width: 768px) {
    .holo-main-card {
        border-radius: 20px 20px 0 0;
        min-height: calc(100vh - 190px);
        margin-top: 0;
    }
}

/* ─────────────────────────────────────────────────────────────────────────────
   TAB NAVIGATION - Pill Style with Scroll
   ───────────────────────────────────────────────────────────────────────────── */
.holo-tabs {
    position: sticky;
    top: 60px;
    z-index: 100;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(var(--holo-blur));
    -webkit-backdrop-filter: blur(var(--holo-blur));
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    padding: 16px 0;
    margin: 0;
}

[data-theme="dark"] .holo-tabs {
    background: rgba(30, 41, 59, 0.95);
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.holo-tabs-scroll {
    display: flex;
    gap: 8px;
    padding: 0 20px;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.holo-tabs-scroll::-webkit-scrollbar {
    display: none;
}

.holo-tab-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    min-height: 44px;
    background: transparent;
    border: 2px solid transparent;
    border-radius: 22px;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--htb-text-muted);
    cursor: pointer;
    transition: var(--holo-transition);
    -webkit-tap-highlight-color: transparent;
    user-select: none;
    white-space: nowrap;
}

.holo-tab-btn:hover {
    background: rgba(var(--holo-primary-rgb), 0.08);
    color: var(--holo-primary);
}

.holo-tab-btn:active {
    transform: scale(0.96);
}

.holo-tab-btn.active {
    background: linear-gradient(135deg, var(--holo-primary) 0%, var(--holo-primary-light) 100%);
    color: white;
    border-color: transparent;
    box-shadow: 0 4px 15px rgba(var(--holo-primary-rgb), 0.35);
}

.holo-tab-btn i {
    font-size: 1rem;
}

@media (max-width: 768px) {
    .holo-tabs {
        position: relative;
        top: 0;
        padding: 14px 0;
        border-radius: 20px 20px 0 0;
        margin: 0;
    }

    .holo-tabs-scroll {
        padding: 0 16px;
        gap: 8px;
    }

    .holo-tab-btn {
        padding: 10px 18px;
        font-size: 0.85rem;
        min-height: 40px;
        border-radius: 20px;
    }

    .holo-tab-btn i {
        display: none;
    }
}

@media (max-width: 480px) {
    .holo-tabs-scroll {
        padding: 0 12px;
        gap: 6px;
    }

    .holo-tab-btn {
        padding: 9px 14px;
        font-size: 0.8rem;
    }
}

/* ─────────────────────────────────────────────────────────────────────────────
   TAB CONTENT PANELS
   ───────────────────────────────────────────────────────────────────────────── */
.holo-tab-content {
    padding: 24px 20px;
    padding-top: 80px; /* Account for sticky tabs */
    margin-top: -76px; /* Pull up to sit under sticky tabs */
}

.holo-tab-pane {
    display: none !important;
    animation: holoPaneFadeIn 0.3s ease;
}

.holo-tab-pane.active {
    display: block !important;
}

@keyframes holoPaneFadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
    .holo-tab-content {
        padding: 20px 16px;
        padding-top: 72px;
        margin-top: -68px;
    }
}

@media (max-width: 480px) {
    .holo-tab-content {
        padding: 16px 12px;
        padding-top: 68px;
        margin-top: -64px;
    }
}

/* ─────────────────────────────────────────────────────────────────────────────
   SECTION HEADERS
   ───────────────────────────────────────────────────────────────────────────── */
.holo-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.holo-section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--htb-text-main);
}

.holo-section-title i {
    color: var(--holo-primary);
}

.holo-section-subtitle {
    margin: 4px 0 0 0;
    font-size: 0.9rem;
    color: var(--htb-text-muted);
}

.holo-section-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: linear-gradient(135deg, var(--holo-primary) 0%, var(--holo-primary-light) 100%);
    color: white;
    border: none;
    border-radius: var(--holo-radius-xs);
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: var(--holo-transition);
    -webkit-tap-highlight-color: transparent;
}

.holo-section-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(var(--holo-primary-rgb), 0.35);
}

@media (max-width: 768px) {
    .holo-section-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .holo-section-action {
        width: 100%;
        justify-content: center;
    }
}

/* ─────────────────────────────────────────────────────────────────────────────
   MEMBERS GRID - Touch-Optimized Cards
   ───────────────────────────────────────────────────────────────────────────── */
.holo-members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 16px;
}

.holo-member-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 24px 16px 20px;
    background: white;
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: var(--holo-radius-sm);
    text-align: center;
    text-decoration: none;
    color: inherit;
    transition: var(--holo-transition);
    -webkit-tap-highlight-color: transparent;
    cursor: pointer;
    overflow: hidden;
}

.holo-member-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--holo-primary), var(--holo-primary-light));
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.holo-member-card:hover::before,
.holo-member-card:active::before {
    transform: scaleX(1);
}

.holo-member-card:hover,
.holo-member-card:active {
    transform: translateY(-4px);
    box-shadow: var(--holo-shadow-hover);
    border-color: rgba(var(--holo-primary-rgb), 0.2);
}

[data-theme="dark"] .holo-member-card {
    background: rgba(30, 41, 59, 0.6);
    border-color: rgba(255, 255, 255, 0.08);
}

.holo-member-avatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    margin-bottom: 12px;
}

.holo-member-name {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--htb-text-main);
    margin-bottom: 4px;
    line-height: 1.3;
}

.holo-member-role {
    display: inline-block;
    padding: 3px 10px;
    background: linear-gradient(135deg, rgba(var(--holo-primary-rgb), 0.1), rgba(var(--holo-primary-rgb), 0.05));
    color: var(--holo-primary);
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 8px;
}

.holo-member-rating {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    font-size: 0.75rem;
    color: var(--htb-text-muted);
}

.holo-member-rating .stars {
    color: #fbbf24;
    font-size: 0.65rem;
}

.holo-member-btn {
    position: relative;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    padding: 8px 14px;
    background: rgba(var(--holo-primary-rgb), 0.1);
    border: none;
    border-radius: var(--holo-radius-xs);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--holo-primary);
    cursor: pointer;
    transition: var(--holo-transition);
}

.holo-member-btn:hover {
    background: var(--holo-primary);
    color: white;
}

@media (max-width: 768px) {
    .holo-members-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .holo-member-card {
        padding: 20px 12px 16px;
    }

    .holo-member-avatar {
        width: 56px;
        height: 56px;
    }

    .holo-member-name {
        font-size: 0.85rem;
    }
}

@media (max-width: 400px) {
    .holo-members-grid {
        grid-template-columns: 1fr;
    }

    .holo-member-card {
        flex-direction: row;
        text-align: left;
        padding: 16px;
        gap: 14px;
    }

    .holo-member-avatar {
        width: 50px;
        height: 50px;
        margin-bottom: 0;
        flex-shrink: 0;
    }

    .holo-member-info {
        flex: 1;
        min-width: 0;
    }

    .holo-member-btn {
        margin-top: 8px;
    }
}

/* ─────────────────────────────────────────────────────────────────────────────
   DISCUSSIONS - Chat-Style Interface
   ───────────────────────────────────────────────────────────────────────────── */
.holo-discussions-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.holo-discussion-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    background: white;
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: var(--holo-radius-sm);
    text-decoration: none;
    color: inherit;
    transition: var(--holo-transition);
    -webkit-tap-highlight-color: transparent;
}

.holo-discussion-item:hover,
.holo-discussion-item:active {
    background: rgba(var(--holo-primary-rgb), 0.03);
    border-color: rgba(var(--holo-primary-rgb), 0.15);
    transform: translateX(4px);
}

[data-theme="dark"] .holo-discussion-item {
    background: rgba(30, 41, 59, 0.5);
    border-color: rgba(255, 255, 255, 0.08);
}

.holo-discussion-avatar {
    width: 48px;
    height: 48px;
    border-radius: var(--holo-radius-xs);
    object-fit: cover;
    flex-shrink: 0;
}

.holo-discussion-avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: var(--holo-radius-xs);
    background: linear-gradient(135deg, rgba(var(--holo-primary-rgb), 0.2), rgba(var(--holo-primary-rgb), 0.1));
    color: var(--holo-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.holo-discussion-content {
    flex: 1;
    min-width: 0;
}

.holo-discussion-title {
    margin: 0 0 6px 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--htb-text-main);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.holo-discussion-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.8rem;
    color: var(--htb-text-muted);
    flex-wrap: wrap;
}

.holo-discussion-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.holo-discussion-arrow {
    color: var(--htb-text-muted);
    font-size: 0.9rem;
    opacity: 0.5;
    transition: var(--holo-transition);
}

.holo-discussion-item:hover .holo-discussion-arrow {
    opacity: 1;
    color: var(--holo-primary);
    transform: translateX(4px);
}

/* Discussion Empty State */
.holo-empty-state {
    text-align: center;
    padding: 48px 24px;
    background: rgba(var(--holo-primary-rgb), 0.03);
    border: 2px dashed rgba(var(--holo-primary-rgb), 0.15);
    border-radius: var(--holo-radius-sm);
}

.holo-empty-icon {
    font-size: 3rem;
    color: var(--holo-primary);
    opacity: 0.4;
    margin-bottom: 16px;
}

.holo-empty-title {
    margin: 0 0 8px 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--htb-text-main);
}

.holo-empty-text {
    margin: 0 0 20px 0;
    font-size: 0.9rem;
    color: var(--htb-text-muted);
}

/* ─────────────────────────────────────────────────────────────────────────────
   ACTIVE CHAT VIEW
   ───────────────────────────────────────────────────────────────────────────── */
.holo-chat-container {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 300px);
    min-height: 400px;
    max-height: 600px;
    background: rgba(var(--holo-primary-rgb), 0.02);
    border-radius: var(--holo-radius-sm);
    overflow: hidden;
}

.holo-chat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px;
    background: white;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    flex-shrink: 0;
}

[data-theme="dark"] .holo-chat-header {
    background: rgba(30, 41, 59, 0.8);
    border-bottom-color: rgba(255, 255, 255, 0.08);
}

.holo-chat-header-info h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--htb-text-main);
}

.holo-chat-header-info p {
    margin: 4px 0 0 0;
    font-size: 0.8rem;
    color: var(--htb-text-muted);
}

.holo-chat-close {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: rgba(0, 0, 0, 0.05);
    border: none;
    border-radius: var(--holo-radius-xs);
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--htb-text-muted);
    text-decoration: none;
    cursor: pointer;
    transition: var(--holo-transition);
}

.holo-chat-close:hover {
    background: rgba(0, 0, 0, 0.1);
}

.holo-chat-stream {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    -webkit-overflow-scrolling: touch;
}

.holo-chat-message {
    display: flex;
    gap: 10px;
    max-width: 85%;
}

.holo-chat-message.me {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.holo-chat-message.other {
    align-self: flex-start;
}

.holo-chat-avatar {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    object-fit: cover;
    flex-shrink: 0;
}

.holo-chat-bubble-wrap {
    display: flex;
    flex-direction: column;
}

.holo-chat-message.me .holo-chat-bubble-wrap {
    align-items: flex-end;
}

.holo-chat-author {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--htb-text-muted);
    margin-bottom: 4px;
    padding: 0 4px;
}

.holo-chat-bubble {
    padding: 12px 16px;
    border-radius: 16px;
    font-size: 0.9rem;
    line-height: 1.5;
    word-wrap: break-word;
}

.holo-chat-message.me .holo-chat-bubble {
    background: linear-gradient(135deg, var(--holo-primary) 0%, var(--holo-primary-light) 100%);
    color: white;
    border-bottom-right-radius: 4px;
}

.holo-chat-message.other .holo-chat-bubble {
    background: white;
    color: var(--htb-text-main);
    border-bottom-left-radius: 4px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

[data-theme="dark"] .holo-chat-message.other .holo-chat-bubble {
    background: rgba(51, 65, 85, 0.8);
    color: #e2e8f0;
}

.holo-chat-time {
    font-size: 0.65rem;
    margin-top: 4px;
    opacity: 0.7;
}

.holo-chat-message.me .holo-chat-time {
    color: rgba(255, 255, 255, 0.8);
}

.holo-chat-reply-dock {
    padding: 16px;
    background: white;
    border-top: 1px solid rgba(0, 0, 0, 0.06);
    flex-shrink: 0;
}

[data-theme="dark"] .holo-chat-reply-dock {
    background: rgba(30, 41, 59, 0.9);
    border-top-color: rgba(255, 255, 255, 0.08);
}

.holo-chat-reply-form {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.holo-chat-input {
    flex: 1;
    padding: 12px 18px;
    background: rgba(0, 0, 0, 0.04);
    border: 2px solid transparent;
    border-radius: 24px;
    font-size: 0.95rem;
    font-family: inherit;
    resize: none;
    min-height: 44px;
    max-height: 120px;
    box-sizing: border-box;
    transition: var(--holo-transition);
}

.holo-chat-input:focus {
    outline: none;
    border-color: var(--holo-primary);
    background: white;
}

[data-theme="dark"] .holo-chat-input {
    background: rgba(51, 65, 85, 0.5);
    color: #e2e8f0;
}

.holo-chat-send {
    width: 44px;
    height: 44px;
    background: linear-gradient(135deg, var(--holo-primary) 0%, var(--holo-primary-light) 100%);
    border: none;
    border-radius: 50%;
    color: white;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: var(--holo-transition);
    box-shadow: 0 4px 12px rgba(var(--holo-primary-rgb), 0.35);
}

.holo-chat-send:active {
    transform: scale(0.95);
}

@media (max-width: 768px) {
    .holo-chat-container {
        height: calc(100vh - 280px);
        max-height: 500px;
        border-radius: var(--holo-radius-xs);
    }

    .holo-chat-stream {
        padding: 16px;
    }

    .holo-chat-message {
        max-width: 90%;
    }

    .holo-chat-reply-dock {
        padding: 12px;
        padding-bottom: calc(12px + var(--safe-bottom));
    }
}

/* ─────────────────────────────────────────────────────────────────────────────
   EVENTS LIST
   ───────────────────────────────────────────────────────────────────────────── */
.holo-events-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.holo-event-card {
    display: flex;
    background: white;
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: var(--holo-radius-sm);
    overflow: hidden;
    transition: var(--holo-transition);
    text-decoration: none;
    color: inherit;
}

.holo-event-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--holo-shadow-hover);
    border-color: rgba(var(--holo-primary-rgb), 0.2);
}

[data-theme="dark"] .holo-event-card {
    background: rgba(30, 41, 59, 0.5);
    border-color: rgba(255, 255, 255, 0.08);
}

.holo-event-date {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 70px;
    padding: 16px;
    background: linear-gradient(135deg, rgba(var(--holo-primary-rgb), 0.1), rgba(var(--holo-primary-rgb), 0.05));
    text-align: center;
}

.holo-event-month {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--holo-primary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.holo-event-day {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--htb-text-main);
    line-height: 1;
}

.holo-event-content {
    flex: 1;
    padding: 16px;
    min-width: 0;
}

.holo-event-title {
    margin: 0 0 6px 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--htb-text-main);
}

.holo-event-location {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    color: var(--htb-text-muted);
    margin-bottom: 4px;
}

.holo-event-organizer {
    font-size: 0.8rem;
    color: var(--htb-text-muted);
    opacity: 0.8;
}

.holo-event-action {
    display: flex;
    align-items: center;
    padding: 16px;
}

.holo-event-btn {
    padding: 10px 18px;
    background: rgba(var(--holo-primary-rgb), 0.1);
    border: none;
    border-radius: var(--holo-radius-xs);
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--holo-primary);
    text-decoration: none;
    transition: var(--holo-transition);
}

.holo-event-btn:hover {
    background: var(--holo-primary);
    color: white;
}

@media (max-width: 768px) {
    .holo-event-card {
        flex-direction: column;
    }

    .holo-event-date {
        flex-direction: row;
        gap: 8px;
        justify-content: flex-start;
        padding: 12px 16px;
    }

    .holo-event-action {
        padding: 0 16px 16px;
    }

    .holo-event-btn {
        width: 100%;
        text-align: center;
    }
}

/* ─────────────────────────────────────────────────────────────────────────────
   REVIEW MODAL
   ───────────────────────────────────────────────────────────────────────────── */
.holo-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.holo-modal-overlay.active {
    display: flex;
}

.holo-modal {
    width: 100%;
    max-width: 420px;
    max-height: 90vh;
    overflow-y: auto;
    background: white;
    border-radius: var(--holo-radius);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
    animation: holoModalSlide 0.3s ease;
}

@keyframes holoModalSlide {
    from { opacity: 0; transform: translateY(20px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

[data-theme="dark"] .holo-modal {
    background: #1e293b;
}

.holo-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.holo-modal-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
}

.holo-modal-close {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.05);
    border: none;
    border-radius: 50%;
    font-size: 1rem;
    color: var(--htb-text-muted);
    cursor: pointer;
    transition: var(--holo-transition);
}

.holo-modal-close:hover {
    background: #fef2f2;
    color: #ef4444;
}

.holo-modal-body {
    padding: 24px;
}

.holo-star-rating {
    display: flex;
    gap: 10px;
    justify-content: center;
    font-size: 32px;
    color: #e2e8f0;
    cursor: pointer;
    margin: 16px 0;
}

.holo-star-rating i {
    transition: all 0.15s ease;
    padding: 4px;
}

.holo-star-rating i:hover {
    transform: scale(1.15);
}

.holo-star-rating i.active {
    color: #fbbf24;
}

.holo-form-group {
    margin-bottom: 20px;
}

.holo-form-label {
    display: block;
    font-weight: 600;
    color: var(--htb-text-main);
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.holo-form-textarea {
    width: 100%;
    padding: 14px;
    border: 2px solid rgba(0, 0, 0, 0.08);
    border-radius: var(--holo-radius-xs);
    font-family: inherit;
    font-size: 0.95rem;
    resize: vertical;
    min-height: 100px;
    box-sizing: border-box;
    transition: var(--holo-transition);
}

.holo-form-textarea:focus {
    outline: none;
    border-color: var(--holo-primary);
}

.holo-form-submit {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, var(--holo-primary) 0%, var(--holo-primary-light) 100%);
    border: none;
    border-radius: var(--holo-radius-xs);
    font-size: 1rem;
    font-weight: 600;
    color: white;
    cursor: pointer;
    transition: var(--holo-transition);
}

.holo-form-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.holo-form-submit:not(:disabled):hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(var(--holo-primary-rgb), 0.35);
}

@media (max-width: 480px) {
    .holo-modal-overlay {
        padding: 0;
        align-items: flex-end;
    }

    .holo-modal {
        max-height: 85vh;
        border-radius: var(--holo-radius) var(--holo-radius) 0 0;
    }
}

/* ─────────────────────────────────────────────────────────────────────────────
   MOBILE ACTION BAR (Bottom)
   ───────────────────────────────────────────────────────────────────────────── */
.holo-action-bar {
    display: none;
}

@media (max-width: 768px) {
    .holo-action-bar {
        display: flex;
        position: fixed;
        bottom: 88px; /* Above the native bottom nav (64px + 12px + 12px buffer) */
        left: 12px;
        right: 12px;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(0, 0, 0, 0.06);
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
        z-index: 10000;
    }

    [data-theme="dark"] .holo-action-bar {
        background: rgba(30, 41, 59, 0.98);
        border-top-color: rgba(255, 255, 255, 0.08);
    }

    .holo-action-info {
        flex: 1;
        min-width: 0;
    }

    .holo-action-title {
        margin: 0;
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--htb-text-main);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .holo-action-subtitle {
        font-size: 0.75rem;
        color: var(--htb-text-muted);
        margin-top: 2px;
    }

    .holo-action-member-badge {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 600;
        color: #15803d;
        font-size: 0.9rem;
    }

    .holo-action-btn {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: var(--holo-radius-xs);
        font-size: 0.9rem;
        font-weight: 600;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: var(--holo-transition);
        -webkit-tap-highlight-color: transparent;
    }

    .holo-action-btn.primary {
        background: linear-gradient(135deg, var(--holo-primary) 0%, var(--holo-primary-light) 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(var(--holo-primary-rgb), 0.35);
    }

    .holo-action-btn.secondary {
        background: #fef2f2;
        color: #ef4444;
        border: 1px solid #fecaca;
    }
}

/* ─────────────────────────────────────────────────────────────────────────────
   FLOATING ACTION BUTTON
   ───────────────────────────────────────────────────────────────────────────── */
.holo-fab {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 999;
    display: flex;
    flex-direction: column-reverse;
    align-items: flex-end;
    gap: 12px;
}

.holo-fab-main {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--holo-primary) 0%, var(--holo-primary-light) 100%);
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 6px 24px rgba(var(--holo-primary-rgb), 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--holo-transition);
    -webkit-tap-highlight-color: transparent;
}

.holo-fab-main:hover {
    transform: scale(1.08);
}

.holo-fab-main.active {
    transform: rotate(45deg);
    background: linear-gradient(135deg, #ef4444 0%, #f97316 100%);
}

.holo-fab-menu {
    display: none;
    flex-direction: column;
    gap: 10px;
    align-items: flex-end;
}

.holo-fab-menu.show {
    display: flex;
    animation: holoFabSlide 0.2s ease;
}

@keyframes holoFabSlide {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.holo-fab-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    background: white;
    backdrop-filter: blur(20px);
    border-radius: var(--holo-radius-xs);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    text-decoration: none;
    color: var(--htb-text-main);
    font-weight: 600;
    font-size: 0.9rem;
    transition: var(--holo-transition);
    white-space: nowrap;
}

.holo-fab-item:hover {
    transform: translateX(-4px);
}

.holo-fab-item i {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    color: white;
}

.holo-fab-item i.icon-discuss { background: linear-gradient(135deg, var(--holo-primary), var(--holo-primary-light)); }
.holo-fab-item i.icon-event { background: linear-gradient(135deg, #f59e0b, #d97706); }
.holo-fab-item i.icon-invite { background: linear-gradient(135deg, #6366f1, #8b5cf6); }

[data-theme="dark"] .holo-fab-item {
    background: rgba(30, 41, 59, 0.95);
    color: #f1f5f9;
}

@media (max-width: 768px) {
    .holo-fab {
        bottom: 100px;
        right: 16px;
    }

    .holo-fab-main {
        width: 52px;
        height: 52px;
        font-size: 1.3rem;
    }
}

/* ─────────────────────────────────────────────────────────────────────────────
   UTILITY CLASSES
   ───────────────────────────────────────────────────────────────────────────── */
.hide-mobile { display: block; }
.show-mobile { display: none; }

@media (max-width: 768px) {
    .hide-mobile { display: none !important; }
    .show-mobile { display: block !important; }
}

/* ─────────────────────────────────────────────────────────────────────────────
   GROUP FEED TAB STYLES
   ───────────────────────────────────────────────────────────────────────────── */

/* Post Composer - Full Holographic Glassmorphism */
.group-feed-composer {
    display: flex;
    gap: 14px;
    padding: 20px;
    background: linear-gradient(135deg,
        rgba(255,255,255,0.95) 0%,
        rgba(255,255,255,0.8) 50%,
        rgba(255,255,255,0.9) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 20px;
    margin-bottom: 20px;
    border: 1px solid rgba(255,255,255,0.6);
    box-shadow:
        0 4px 24px rgba(0,0,0,0.06),
        0 1px 2px rgba(0,0,0,0.04),
        inset 0 1px 0 rgba(255,255,255,0.8);
    position: relative;
    overflow: hidden;
}

.group-feed-composer::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg,
        var(--holo-primary),
        #a855f7,
        #ec4899,
        var(--holo-primary));
    background-size: 200% 100%;
    animation: shimmer 3s linear infinite;
}

[data-theme="dark"] .group-feed-composer {
    background: linear-gradient(135deg,
        rgba(30,41,59,0.95) 0%,
        rgba(15,23,42,0.85) 50%,
        rgba(30,41,59,0.9) 100%);
    border-color: rgba(148,163,184,0.2);
    box-shadow:
        0 4px 24px rgba(0,0,0,0.3),
        inset 0 1px 0 rgba(255,255,255,0.05);
}

.composer-avatar img {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(var(--holo-primary-rgb), 0.3);
    box-shadow: 0 4px 12px rgba(var(--holo-primary-rgb), 0.2);
    transition: all 0.3s ease;
}

.composer-avatar img:hover {
    transform: scale(1.05);
    border-color: var(--holo-primary);
}

.composer-input-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.composer-input-area textarea {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid rgba(var(--holo-primary-rgb), 0.15);
    border-radius: 16px;
    font-size: 15px;
    line-height: 1.5;
    resize: none;
    background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));
    backdrop-filter: blur(8px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

[data-theme="dark"] .composer-input-area textarea {
    background: linear-gradient(135deg, rgba(51,65,85,0.9), rgba(30,41,59,0.7));
    border-color: rgba(255,255,255,0.1);
    color: #e2e8f0;
}

.composer-input-area textarea:focus {
    outline: none;
    border-color: var(--holo-primary);
    box-shadow:
        0 0 0 4px rgba(var(--holo-primary-rgb), 0.15),
        0 4px 12px rgba(var(--holo-primary-rgb), 0.1);
    transform: translateY(-1px);
}

.composer-input-area textarea::placeholder {
    color: #94a3b8;
}

.composer-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.composer-tools {
    display: flex;
    gap: 6px;
}

.composer-tool-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    cursor: pointer;
    color: #64748b;
    background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(255,255,255,0.5));
    border: 1px solid rgba(var(--holo-primary-rgb), 0.1);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

[data-theme="dark"] .composer-tool-btn {
    background: linear-gradient(135deg, rgba(51,65,85,0.8), rgba(30,41,59,0.5));
    border-color: rgba(255,255,255,0.1);
    color: #94a3b8;
}

.composer-tool-btn:hover {
    background: linear-gradient(135deg, rgba(var(--holo-primary-rgb), 0.2), rgba(var(--holo-primary-rgb), 0.1));
    color: var(--holo-primary);
}

.composer-submit-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(135deg, var(--holo-primary), #a855f7, #ec4899);
    background-size: 200% 200%;
    color: white;
    border: none;
    border-radius: 24px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 16px rgba(var(--holo-primary-rgb), 0.3);
    position: relative;
    overflow: hidden;
}

.composer-submit-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s ease;
}

.composer-submit-btn:hover::before {
    left: 100%;
}

.composer-submit-btn:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 8px 24px rgba(var(--holo-primary-rgb), 0.4);
    background-position: 100% 0;
}

.composer-submit-btn:active {
    transform: translateY(-1px) scale(0.98);
}

.composer-submit-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.composer-image-preview {
    position: relative;
    max-width: 200px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.composer-image-preview img {
    width: 100%;
    display: block;
}

.remove-image-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, rgba(239,68,68,0.9), rgba(220,38,38,0.9));
    backdrop-filter: blur(8px);
    color: white;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.remove-image-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(239,68,68,0.4);
}

/* Join Prompt - Glassmorphism */
.group-feed-join-prompt {
    text-align: center;
    padding: 40px 24px;
    background: linear-gradient(135deg,
        rgba(var(--holo-primary-rgb), 0.08) 0%,
        rgba(var(--holo-primary-rgb), 0.04) 50%,
        rgba(var(--holo-primary-rgb), 0.06) 100%);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-radius: 20px;
    margin-bottom: 20px;
    border: 2px dashed rgba(var(--holo-primary-rgb), 0.3);
    position: relative;
    overflow: hidden;
}

.group-feed-join-prompt::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(var(--holo-primary-rgb), 0.1) 0%, transparent 70%);
    animation: pulseGlow 3s ease-in-out infinite;
}

@keyframes pulseGlow {
    0%, 100% { opacity: 0.5; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.1); }
}

.group-feed-join-prompt i {
    font-size: 48px;
    background: linear-gradient(135deg, var(--holo-primary), #a855f7, #ec4899);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 16px;
    display: block;
    position: relative;
    z-index: 1;
}

.group-feed-join-prompt p {
    color: #64748b;
    font-size: 16px;
    font-weight: 500;
    margin: 0;
    position: relative;
    z-index: 1;
}

[data-theme="dark"] .group-feed-join-prompt {
    background: linear-gradient(135deg,
        rgba(var(--holo-primary-rgb), 0.15) 0%,
        rgba(var(--holo-primary-rgb), 0.08) 50%,
        rgba(var(--holo-primary-rgb), 0.1) 100%);
    border-color: rgba(var(--holo-primary-rgb), 0.4);
}

[data-theme="dark"] .group-feed-join-prompt p {
    color: #94a3b8;
}

/* ═══════════════════════════════════════════════════════════════════════════
   FACEBOOK-STYLE COMPOSE BOX - Glassmorphism
   ═══════════════════════════════════════════════════════════════════════════ */
.group-compose-box {
    background: linear-gradient(135deg,
        rgba(255,255,255,0.95) 0%,
        rgba(255,255,255,0.85) 50%,
        rgba(255,255,255,0.9) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 20px;
    margin-bottom: 20px;
    border: 1px solid rgba(219, 39, 119, 0.15);
    box-shadow:
        0 4px 24px rgba(0,0,0,0.06),
        0 1px 2px rgba(0,0,0,0.04),
        inset 0 1px 0 rgba(255,255,255,0.8);
    position: relative;
    overflow: hidden;
    padding: 16px;
    transition: all 0.3s ease;
}

.group-compose-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg,
        #db2777,
        #a855f7,
        #ec4899,
        #db2777);
    background-size: 200% 100%;
    animation: shimmer 3s linear infinite;
}

.group-compose-box:hover {
    border-color: rgba(219, 39, 119, 0.25);
    box-shadow:
        0 8px 32px rgba(219, 39, 119, 0.12),
        0 4px 12px rgba(0,0,0,0.06),
        inset 0 1px 0 rgba(255,255,255,0.8);
}

[data-theme="dark"] .group-compose-box {
    background: linear-gradient(135deg,
        rgba(30,41,59,0.95) 0%,
        rgba(15,23,42,0.85) 50%,
        rgba(30,41,59,0.9) 100%);
    border-color: rgba(219, 39, 119, 0.25);
    box-shadow:
        0 4px 24px rgba(0,0,0,0.3),
        inset 0 1px 0 rgba(255,255,255,0.05);
}

[data-theme="dark"] .group-compose-box:hover {
    border-color: rgba(219, 39, 119, 0.4);
    box-shadow:
        0 8px 32px rgba(219, 39, 119, 0.2),
        inset 0 1px 0 rgba(255,255,255,0.05);
}

.group-compose-link {
    text-decoration: none;
    display: block;
}

.group-compose-prompt {
    display: flex;
    align-items: center;
    gap: 12px;
}

.group-compose-avatar-ring {
    position: relative;
    padding: 3px;
    border-radius: 50%;
    background: linear-gradient(135deg, #db2777, #a855f7, #ec4899, #f59e0b);
    background-size: 200% 200%;
    animation: holographicShimmer 6s ease infinite;
    flex-shrink: 0;
}

@keyframes holographicShimmer {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.group-compose-avatar-ring img {
    display: block;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    background: #fff;
    border: 2px solid #fff;
}

[data-theme="dark"] .group-compose-avatar-ring img {
    border-color: #1e293b;
    background: #1e293b;
}

.group-compose-avatar-ring.guest {
    padding: 0;
    background: transparent;
}

.guest-avatar-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #db2777, #a855f7);
    display: flex;
    align-items: center;
    justify-content: center;
}

.guest-avatar-icon i {
    color: white;
    font-size: 18px;
}

.group-compose-input {
    flex: 1;
    padding: 12px 18px;
    background: linear-gradient(135deg, rgba(243, 244, 246, 0.8), rgba(249, 250, 251, 0.6));
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(219, 39, 119, 0.1);
    border-radius: 24px;
    font-size: 15px;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s ease;
}

.group-compose-link:hover .group-compose-input {
    background: linear-gradient(135deg, rgba(253, 242, 248, 0.9), rgba(243, 244, 246, 0.8));
    border-color: rgba(219, 39, 119, 0.25);
    box-shadow: 0 4px 12px rgba(219, 39, 119, 0.1);
    color: #4b5563;
}

[data-theme="dark"] .group-compose-input {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.6));
    border-color: rgba(219, 39, 119, 0.2);
    color: #94a3b8;
}

[data-theme="dark"] .group-compose-link:hover .group-compose-input {
    background: linear-gradient(135deg, rgba(51, 65, 85, 0.9), rgba(30, 41, 59, 0.8));
    border-color: rgba(219, 39, 119, 0.35);
    color: #cbd5e1;
}

/* Quick Action Buttons */
.group-compose-actions {
    display: flex;
    gap: 8px;
    padding-top: 14px;
    margin-top: 14px;
    border-top: 1px solid rgba(219, 39, 119, 0.08);
}

[data-theme="dark"] .group-compose-actions {
    border-top-color: rgba(219, 39, 119, 0.15);
}

.group-compose-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex: 1;
    padding: 10px 16px;
    background: transparent;
    border: none;
    border-radius: 12px;
    color: #4b5563;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.group-compose-btn:hover {
    background: rgba(219, 39, 119, 0.08);
    color: #374151;
}

[data-theme="dark"] .group-compose-btn {
    color: #94a3b8;
}

[data-theme="dark"] .group-compose-btn:hover {
    background: rgba(219, 39, 119, 0.15);
    color: #e2e8f0;
}

.group-compose-btn i {
    font-size: 18px;
}

/* Guest Prompt Style */
.group-compose-box.guest-prompt {
    border-style: dashed;
    border-color: rgba(219, 39, 119, 0.3);
}

.group-compose-box.guest-prompt:hover {
    border-color: rgba(219, 39, 119, 0.5);
}

/* Mobile responsive */
@media (max-width: 640px) {
    .group-compose-btn span {
        display: none;
    }

    .group-compose-btn {
        padding: 10px;
    }

    .group-compose-btn i {
        font-size: 20px;
    }
}

/* Feed Posts Container */
.group-feed-posts {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Loading State - Glassmorphism */
.feed-loading {
    text-align: center;
    padding: 60px 40px;
    color: #64748b;
    background: linear-gradient(135deg, rgba(255,255,255,0.6), rgba(255,255,255,0.4));
    backdrop-filter: blur(12px);
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.5);
}

[data-theme="dark"] .feed-loading {
    background: linear-gradient(135deg, rgba(30,41,59,0.6), rgba(15,23,42,0.4));
    border-color: rgba(255,255,255,0.1);
    color: #94a3b8;
}

.feed-loading i {
    font-size: 32px;
    background: linear-gradient(135deg, var(--holo-primary), #a855f7);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 12px;
    display: block;
}

/* Feed Post Card - Full Holographic Glassmorphism */
.group-feed-post {
    background: linear-gradient(135deg,
        rgba(255,255,255,0.9) 0%,
        rgba(255,255,255,0.7) 50%,
        rgba(255,255,255,0.8) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.5);
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow:
        0 4px 24px rgba(0,0,0,0.06),
        0 1px 2px rgba(0,0,0,0.04),
        inset 0 1px 0 rgba(255,255,255,0.6);
    position: relative;
}

.group-feed-post::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg,
        var(--holo-primary) 0%,
        #a855f7 25%,
        #ec4899 50%,
        #f97316 75%,
        var(--holo-primary) 100%);
    background-size: 200% 100%;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.group-feed-post:hover::before {
    opacity: 1;
    animation: shimmer 2s linear infinite;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

[data-theme="dark"] .group-feed-post {
    background: linear-gradient(135deg,
        rgba(30,41,59,0.9) 0%,
        rgba(15,23,42,0.8) 50%,
        rgba(30,41,59,0.85) 100%);
    border-color: rgba(148,163,184,0.2);
    box-shadow:
        0 4px 24px rgba(0,0,0,0.3),
        0 1px 2px rgba(0,0,0,0.2),
        inset 0 1px 0 rgba(255,255,255,0.05);
}

.group-feed-post:hover {
    transform: translateY(-4px);
    box-shadow:
        0 20px 40px rgba(var(--holo-primary-rgb), 0.15),
        0 8px 16px rgba(0,0,0,0.08),
        inset 0 1px 0 rgba(255,255,255,0.8);
}

[data-theme="dark"] .group-feed-post:hover {
    box-shadow:
        0 20px 40px rgba(var(--holo-primary-rgb), 0.25),
        0 8px 16px rgba(0,0,0,0.4),
        inset 0 1px 0 rgba(255,255,255,0.1);
}

.feed-post-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 18px;
    background: linear-gradient(180deg, rgba(255,255,255,0.3), transparent);
}

[data-theme="dark"] .feed-post-header {
    background: linear-gradient(180deg, rgba(255,255,255,0.05), transparent);
}

.feed-post-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(var(--holo-primary-rgb), 0.3);
    box-shadow: 0 4px 12px rgba(var(--holo-primary-rgb), 0.2);
    transition: all 0.3s ease;
}

.feed-post-avatar:hover {
    transform: scale(1.1);
    border-color: var(--holo-primary);
}

.feed-post-author {
    flex: 1;
}

.feed-post-author-name {
    font-weight: 700;
    color: #1e293b;
    font-size: 15px;
    text-decoration: none;
    transition: color 0.2s ease;
}

.feed-post-author-name:hover {
    color: var(--holo-primary);
}

[data-theme="dark"] .feed-post-author-name {
    color: #f1f5f9;
}

.feed-post-meta {
    font-size: 13px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 6px;
}

.feed-post-meta::before {
    content: '';
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--holo-primary);
    opacity: 0.5;
}

.feed-post-content {
    padding: 0 18px 16px;
    font-size: 15px;
    line-height: 1.7;
    color: #334155;
}

.feed-post-content .mention {
    color: var(--holo-primary);
    font-weight: 600;
    cursor: pointer;
}

.feed-post-content .mention:hover {
    text-decoration: underline;
}

[data-theme="dark"] .feed-post-content {
    color: #e2e8f0;
}

.feed-post-image {
    width: 100%;
    max-height: 450px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.feed-post-image:hover {
    transform: scale(1.02);
}

/* Stats Row */
.feed-post-stats {
    display: flex;
    justify-content: space-between;
    padding: 10px 18px;
    font-size: 13px;
    color: #64748b;
    border-top: 1px solid rgba(var(--holo-primary-rgb), 0.1);
}

.feed-post-stats i {
    margin-right: 4px;
}

.feed-post-actions {
    display: flex;
    padding: 10px 14px;
    gap: 8px;
    border-top: 1px solid rgba(var(--holo-primary-rgb), 0.1);
    background: linear-gradient(180deg, transparent, rgba(var(--holo-primary-rgb), 0.03));
}

[data-theme="dark"] .feed-post-actions {
    border-color: rgba(255,255,255,0.05);
    background: linear-gradient(180deg, transparent, rgba(255,255,255,0.02));
}

.feed-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 16px;
    background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));
    backdrop-filter: blur(8px);
    border: 1px solid rgba(var(--holo-primary-rgb), 0.15);
    border-radius: 24px;
    font-size: 13px;
    font-weight: 500;
    color: #475569;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    flex: 1;
}

[data-theme="dark"] .feed-action-btn {
    background: linear-gradient(135deg, rgba(51,65,85,0.9), rgba(30,41,59,0.7));
    border-color: rgba(255,255,255,0.1);
    color: #94a3b8;
}

.feed-action-btn:hover {
    background: linear-gradient(135deg, rgba(var(--holo-primary-rgb), 0.15), rgba(var(--holo-primary-rgb), 0.1));
    color: var(--holo-primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--holo-primary-rgb), 0.2);
    border-color: var(--holo-primary);
}

.feed-action-btn:active {
    transform: translateY(0);
}

.feed-action-btn.liked {
    background: linear-gradient(135deg, #ec4899, #f43f5e);
    color: white;
    border-color: transparent;
    box-shadow: 0 4px 12px rgba(236,72,153,0.3);
}

.feed-action-btn.liked:hover {
    background: linear-gradient(135deg, #db2777, #e11d48);
    box-shadow: 0 6px 16px rgba(236,72,153,0.4);
}

.feed-action-btn.liked i {
    animation: likeHeart 0.3s ease;
}

/* Load More */
.feed-load-more {
    text-align: center;
    padding: 16px;
}

.feed-load-more button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: rgba(var(--holo-primary-rgb), 0.1);
    color: var(--holo-primary);
    border: none;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: var(--holo-transition);
}

.feed-load-more button:hover {
    background: var(--holo-primary);
    color: white;
}

/* Empty State */
.feed-empty {
    text-align: center;
    padding: 48px 24px;
    color: #64748b;
}

.feed-empty i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.3;
}

.feed-empty h3 {
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 8px;
    color: #475569;
}

.feed-empty p {
    font-size: 14px;
    margin: 0;
}

/* Comments Section in Feed */
.feed-post-comments {
    padding: 16px;
    background: linear-gradient(135deg, rgba(255,255,255,0.5), rgba(255,255,255,0.3));
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-top: 1px solid rgba(var(--holo-primary-rgb), 0.1);
}

[data-theme="dark"] .feed-post-comments {
    background: linear-gradient(135deg, rgba(30,41,59,0.6), rgba(15,23,42,0.4));
    border-color: rgba(255,255,255,0.05);
}

/* Comment Item */
.gf-comment-wrapper {
    margin-bottom: 12px;
}

.gf-comment {
    display: flex;
    gap: 10px;
    animation: commentSlideIn 0.3s ease-out;
}

@keyframes commentSlideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.gf-comment-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(var(--holo-primary-rgb), 0.2);
    flex-shrink: 0;
}

.gf-comment-body {
    flex: 1;
    background: linear-gradient(135deg, rgba(255,255,255,0.8), rgba(255,255,255,0.6));
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    padding: 10px 14px;
    border-radius: 16px;
    border: 1px solid rgba(var(--holo-primary-rgb), 0.1);
    position: relative;
}

[data-theme="dark"] .gf-comment-body {
    background: linear-gradient(135deg, rgba(51,65,85,0.8), rgba(30,41,59,0.6));
    border-color: rgba(255,255,255,0.1);
}

.gf-comment-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}

.gf-comment-author {
    font-weight: 600;
    font-size: 13px;
    color: #1e293b;
}

[data-theme="dark"] .gf-comment-author {
    color: #f1f5f9;
}

.gf-comment-time {
    font-size: 11px;
    color: #94a3b8;
}

.gf-comment-content {
    font-size: 14px;
    line-height: 1.5;
    color: #334155;
    word-break: break-word;
}

[data-theme="dark"] .gf-comment-content {
    color: #cbd5e1;
}

.gf-comment-content .mention {
    color: var(--holo-primary);
    font-weight: 500;
    cursor: pointer;
}

.gf-comment-content .mention:hover {
    text-decoration: underline;
}

/* Comment Actions */
.gf-comment-actions {
    display: flex;
    gap: 12px;
    margin-top: 6px;
    padding-top: 6px;
}

.gf-comment-action {
    font-size: 12px;
    color: #64748b;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s ease;
    border: none;
    background: none;
    padding: 0;
}

.gf-comment-action:hover {
    color: var(--holo-primary);
}

.gf-comment-action.liked {
    color: #ec4899;
}

.gf-comment-action.liked i {
    animation: likeHeart 0.3s ease;
}

@keyframes likeHeart {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.3); }
}

/* Nested Replies */
.gf-replies {
    margin-left: 46px;
    margin-top: 8px;
    padding-left: 12px;
    border-left: 2px solid rgba(var(--holo-primary-rgb), 0.2);
}

.gf-reply-form {
    display: flex;
    gap: 8px;
    margin-top: 8px;
    margin-left: 46px;
    padding-left: 12px;
}

.gf-reply-form input {
    flex: 1;
    padding: 8px 14px;
    border: 1px solid rgba(var(--holo-primary-rgb), 0.2);
    border-radius: 20px;
    font-size: 13px;
    background: rgba(255,255,255,0.8);
    transition: all 0.2s ease;
}

[data-theme="dark"] .gf-reply-form input {
    background: rgba(30,41,59,0.8);
    border-color: rgba(255,255,255,0.1);
    color: #e2e8f0;
}

.gf-reply-form input:focus {
    outline: none;
    border-color: var(--holo-primary);
    box-shadow: 0 0 0 3px rgba(var(--holo-primary-rgb), 0.1);
}

.gf-reply-form button {
    padding: 8px 14px;
    background: linear-gradient(135deg, var(--holo-primary), var(--holo-primary-light));
    color: white;
    border: none;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.gf-reply-form button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--holo-primary-rgb), 0.3);
}

/* Emoji Reactions on Comments */
.gf-reactions {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 6px;
}

.gf-reaction {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    background: rgba(var(--holo-primary-rgb), 0.1);
    border-radius: 12px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.gf-reaction:hover {
    background: rgba(var(--holo-primary-rgb), 0.2);
}

.gf-reaction.active {
    background: linear-gradient(135deg, rgba(var(--holo-primary-rgb), 0.2), rgba(var(--holo-primary-rgb), 0.1));
    border-color: var(--holo-primary);
}

.gf-reaction-picker {
    position: absolute;
    bottom: 100%;
    left: 0;
    display: flex;
    gap: 4px;
    padding: 8px;
    background: white;
    border-radius: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    z-index: 10;
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
    transition: all 0.2s ease;
}

[data-theme="dark"] .gf-reaction-picker {
    background: #1e293b;
}

.gf-reaction-picker.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.gf-reaction-picker span {
    font-size: 20px;
    cursor: pointer;
    padding: 4px;
    border-radius: 50%;
    transition: all 0.15s ease;
}

.gf-reaction-picker span:hover {
    transform: scale(1.3);
    background: rgba(var(--holo-primary-rgb), 0.1);
}

/* Empty Comments */
.gf-no-comments {
    text-align: center;
    padding: 20px;
    color: #94a3b8;
    font-size: 13px;
}

.gf-no-comments i {
    font-size: 24px;
    margin-bottom: 8px;
    opacity: 0.5;
}

.feed-comment-input-row {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
}

.feed-comment-input {
    flex: 1;
    padding: 10px 14px;
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 20px;
    font-size: 14px;
    background: white;
    transition: all 0.2s ease;
}

[data-theme="dark"] .feed-comment-input {
    background: rgba(15, 23, 42, 0.6);
    border-color: rgba(139, 92, 246, 0.25);
    color: #f1f5f9;
}

[data-theme="dark"] .feed-comment-input::placeholder {
    color: #94a3b8;
}

.feed-comment-input:focus {
    outline: none;
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

[data-theme="dark"] .feed-comment-input:focus {
    background: rgba(15, 23, 42, 0.75);
    border-color: rgba(139, 92, 246, 0.5);
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
}

@media (max-width: 640px) {
    .group-feed-composer {
        flex-direction: column;
    }

    .composer-avatar {
        display: none;
    }
}

/* ============================================
   DARK MODE SUPPORT FOR ACTION CARDS
   ============================================ */
[data-theme="dark"] .holo-action-card {
    background: rgba(30, 41, 59, 0.8) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}

[data-theme="dark"] .holo-action-card:hover {
    background: rgba(30, 41, 59, 1) !important;
    border-color: rgba(255, 255, 255, 0.2) !important;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
}
</style>

<!-- ═══════════════════════════════════════════════════════════════════════════
     PAGE STRUCTURE
     ═══════════════════════════════════════════════════════════════════════════ -->

<!-- Holographic Background -->
<div class="holo-bg"></div>

<!-- Main Page Container -->
<div class="holo-page">

    <!-- Modern Hero Section -->
    <div class="modern-hero <?= !empty($group['cover_image_url']) ? 'modern-hero--with-cover' : '' ?>">
        <?php if (!empty($group['cover_image_url'])): ?>
            <div class="modern-hero__cover">
                <?= webp_image($group['cover_image_url'], htmlspecialchars($group['name']), '') ?>
            </div>
        <?php endif; ?>
        <div class="modern-hero__gradient htb-hero-gradient-hub"></div>
        <div class="modern-hero__content">
            <div class="modern-hero__badge">
                <i class="fa-solid fa-users"></i>
                <span>Community Hub</span>
            </div>
            <h1 class="modern-hero__title"><?= htmlspecialchars($group['name']) ?></h1>
            <div class="modern-hero__meta">
                <span><i class="fa-solid fa-user-group"></i> <?= count($members) ?> Members</span>
                <?php if (!empty($group['location'])): ?>
                    <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($group['location']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($group['description'])): ?>
                <p class="modern-hero__description"><?= htmlspecialchars(mb_strimwidth($group['description'], 0, 200, '...')) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="holo-container">
        <main class="holo-main-card">

            <!-- Group Actions Bar -->
            <div class="group-actions-bar">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($isMember): ?>
                        <span class="member-badge">
                            <i class="fa-solid fa-circle-check"></i> Member
                        </span>
                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/leave" method="POST" class="ajax-form" data-reload="true" style="display: inline;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                            <button type="submit" class="btn-secondary">Leave Hub</button>
                        </form>
                    <?php elseif ($isPending): ?>
                        <span class="pending-badge">
                            <i class="fa-solid fa-clock"></i> Pending Approval
                        </span>
                    <?php else: ?>
                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/join" method="POST" class="ajax-form" data-reload="true" style="display: inline;">
                            <?= \Nexus\Core\Csrf::input() ?>
                            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                            <button type="submit" class="btn-primary">
                                <i class="fa-solid fa-plus"></i> Join Hub
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" class="btn-primary">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Login to Join
                    </a>
                <?php endif; ?>
            </div>

            <!-- Tab Navigation -->
            <nav role="navigation" aria-label="Main navigation" class="holo-tabs">
                <div class="holo-tabs-scroll">
                    <?php if ($hasSubHubs): ?>
                        <button onclick="switchTab('sub-hubs')" class="holo-tab-btn <?= $activeTab == 'sub-hubs' ? 'active' : '' ?>" id="btn-sub-hubs">
                            <i class="fa-solid fa-layer-group"></i>
                            <span>Sub-Hubs</span>
                        </button>
                    <?php endif; ?>

                    <button onclick="switchTab('feed')" class="holo-tab-btn <?= $activeTab == 'feed' ? 'active' : '' ?>" id="btn-feed">
                        <i class="fa-solid fa-rss"></i>
                        <span>Feed</span>
                    </button>

                    <button onclick="switchTab('members')" class="holo-tab-btn <?= (!$hasSubHubs && $activeTab == 'members' && $activeTab != 'feed') || $activeTab == 'members' ? 'active' : '' ?>" id="btn-members">
                        <i class="fa-solid fa-users"></i>
                        <span>Members</span>
                    </button>

                    <button onclick="switchTab('discussions')" class="holo-tab-btn <?= $activeTab == 'discussions' ? 'active' : '' ?>" id="btn-discussions">
                        <i class="fa-solid fa-comments"></i>
                        <span>Discussions</span>
                    </button>

                    <button onclick="switchTab('events')" class="holo-tab-btn <?= $activeTab == 'events' ? 'active' : '' ?>" id="btn-events">
                        <i class="fa-solid fa-calendar"></i>
                        <span>Events</span>
                    </button>

                    <?php if ($isMember || $isOrganizer): ?>
                        <button onclick="switchTab('reviews')" class="holo-tab-btn <?= $activeTab == 'reviews' ? 'active' : '' ?>" id="btn-reviews">
                            <i class="fa-solid fa-star"></i>
                            <span>Reviews</span>
                        </button>
                    <?php endif; ?>

                    <?php if ($isOrganizer): ?>
                        <button onclick="switchTab('settings')" class="holo-tab-btn <?= $activeTab == 'settings' ? 'active' : '' ?>" id="btn-settings">
                            <i class="fa-solid fa-gear"></i>
                            <span>Settings</span>
                        </button>
                    <?php endif; ?>
                </div>
            </nav>

            <!-- Tab Content -->
            <div class="holo-tab-content">

                <!-- ═══════════════════════════════════════════════════════════════
                     SUB-HUBS TAB
                     ═══════════════════════════════════════════════════════════════ -->
                <?php if ($hasSubHubs): ?>
                <div id="tab-sub-hubs" class="holo-tab-pane <?= $activeTab == 'sub-hubs' ? 'active' : '' ?>">
                    <div class="holo-section-header">
                        <div>
                            <h2 class="holo-section-title"><i class="fa-solid fa-layer-group"></i> Sub-Hubs</h2>
                            <p class="holo-section-subtitle">Specialized groups within this hub</p>
                        </div>
                    </div>

                    <div class="holo-discussions-list">
                        <?php foreach ($subGroups as $sub): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $sub['id'] ?>" class="holo-discussion-item">
                                <div class="holo-discussion-avatar-placeholder">
                                    <i class="fa-solid fa-layer-group"></i>
                                </div>
                                <div class="holo-discussion-content">
                                    <h3 class="holo-discussion-title"><?= htmlspecialchars($sub['name']) ?></h3>
                                    <div class="holo-discussion-meta">
                                        <span><?= htmlspecialchars(substr($sub['description'], 0, 60)) ?>...</span>
                                    </div>
                                </div>
                                <i class="fa-solid fa-chevron-right holo-discussion-arrow"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ═══════════════════════════════════════════════════════════════
                     FEED TAB (Group Social Feed)
                     ═══════════════════════════════════════════════════════════════ -->
                <div id="tab-feed" class="holo-tab-pane <?= $activeTab == 'feed' ? 'active' : '' ?>">
                    <div class="holo-section-header">
                        <div>
                            <h2 class="holo-section-title"><i class="fa-solid fa-rss"></i> Group Feed</h2>
                            <p class="holo-section-subtitle">Updates and posts from hub members</p>
                        </div>
                    </div>

                    <!-- Post Composer (Facebook-style "What's on your mind") -->
                    <?php if ($isMember): ?>
                    <div class="group-compose-box">
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?group=<?= $group['id'] ?>" class="group-compose-link">
                            <div class="group-compose-prompt">
                                <div class="group-compose-avatar-ring">
                                    <?= webp_avatar($_SESSION['user_avatar'] ?? null, $_SESSION['user_name'] ?? 'User', 40) ?>
                                </div>
                                <div class="group-compose-input">
                                    What's on your mind, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'User')[0]) ?>?
                                </div>
                            </div>
                        </a>
                        <div class="group-compose-actions">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?type=post&group=<?= $group['id'] ?>" class="group-compose-btn">
                                <i class="fa-solid fa-pen" style="color: #db2777;"></i>
                                <span>Post</span>
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?type=listing&group=<?= $group['id'] ?>" class="group-compose-btn">
                                <i class="fa-solid fa-hand-holding-heart" style="color: #10b981;"></i>
                                <span>Listing</span>
                            </a>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/compose?type=event&group=<?= $group['id'] ?>" class="group-compose-btn">
                                <i class="fa-solid fa-calendar-plus" style="color: #6366f1;"></i>
                                <span>Event</span>
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="group-compose-box guest-prompt">
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login?redirect=<?= urlencode('/groups/' . $group['id']) ?>" class="group-compose-link">
                            <div class="group-compose-prompt">
                                <div class="group-compose-avatar-ring guest">
                                    <div class="guest-avatar-icon">
                                        <i class="fa-solid fa-user-plus"></i>
                                    </div>
                                </div>
                                <div class="group-compose-input">
                                    Sign in to join this hub and share with the community...
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Feed Posts Container -->
                    <div id="groupFeedPosts" class="group-feed-posts">
                        <div class="feed-loading">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                            <span>Loading posts...</span>
                        </div>
                    </div>

                    <!-- Load More Button -->
                    <div id="groupFeedLoadMore" class="feed-load-more" style="display: none;">
                        <button type="button" onclick="loadGroupFeed(<?= $group['id'] ?>, true)">
                            <i class="fa-solid fa-arrow-down"></i>
                            Load More
                        </button>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════════════════
                     MEMBERS TAB
                     ═══════════════════════════════════════════════════════════════ -->
                <div id="tab-members" class="holo-tab-pane <?= $activeTab == 'members' || (!$hasSubHubs && $activeTab == '') ? 'active' : '' ?>">
                    <div class="holo-section-header">
                        <div>
                            <h2 class="holo-section-title"><i class="fa-solid fa-users"></i> Members</h2>
                            <p class="holo-section-subtitle"><?= count($members) ?> people in this hub</p>
                        </div>
                    </div>

                    <?php if (!empty($members)): ?>
                        <div class="holo-members-grid">
                            <?php foreach ($members as $mem):
                                $isOrg = ($mem['id'] == $group['owner_id']);
                                $isMe = ($mem['id'] == $currentUserId);
                                $memberRating = \Nexus\Models\Review::getAverageForUser($mem['id']);
                            ?>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/profile/<?= $mem['id'] ?>" class="holo-member-card">
                                    <?= webp_avatar($mem['avatar_url'] ?? null, $mem['name'], 48) ?>
                                    <div class="holo-member-info">
                                        <div class="holo-member-name"><?= htmlspecialchars($mem['name']) ?></div>
                                        <?php if ($isOrg): ?>
                                            <div class="holo-member-role">Organizer</div>
                                        <?php endif; ?>
                                        <?php if ($memberRating && $memberRating['total_count'] > 0): ?>
                                            <div class="holo-member-rating">
                                                <span class="stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="<?= $i <= round($memberRating['avg_rating']) ? 'fas' : 'far' ?> fa-star"></i>
                                                    <?php endfor; ?>
                                                </span>
                                                <span>(<?= $memberRating['total_count'] ?>)</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($isMember && !$isMe): ?>
                                            <button type="button" class="holo-member-btn"
                                                    onclick="event.preventDefault(); event.stopPropagation(); openReviewModal(<?= $mem['id'] ?>, '<?= htmlspecialchars(addslashes($mem['name'])) ?>', '<?= get_phoenix_avatar($mem['avatar_url']) ?>')">
                                                <i class="fa-solid fa-star"></i> Review
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="holo-empty-state">
                            <div class="holo-empty-icon"><i class="fa-solid fa-users"></i></div>
                            <h3 class="holo-empty-title">No members yet</h3>
                            <p class="holo-empty-text">Be the first to join this hub!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ═══════════════════════════════════════════════════════════════
                     DISCUSSIONS TAB
                     ═══════════════════════════════════════════════════════════════ -->
                <div id="tab-discussions" class="holo-tab-pane <?= $activeTab == 'discussions' ? 'active' : '' ?>">
                    <?php if (isset($activeDiscussion)): ?>
                        <!-- Active Chat View -->
                        <div class="holo-chat-container">
                            <div class="holo-chat-header">
                                <div class="holo-chat-header-info">
                                    <h3><?= htmlspecialchars($activeDiscussion['title']) ?></h3>
                                    <p>Started by <?= htmlspecialchars($activeDiscussion['author_name']) ?></p>
                                </div>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>?tab=discussions" class="holo-chat-close">
                                    <i class="fa-solid fa-xmark"></i>
                                    <span class="hide-mobile">Close</span>
                                </a>
                            </div>

                            <div class="holo-chat-stream" id="chatStream">
                                <?php foreach ($activePosts as $post):
                                    $isMe = ($post['user_id'] == $currentUserId);
                                    $msgClass = $isMe ? 'me' : 'other';
                                ?>
                                    <div class="holo-chat-message <?= $msgClass ?>">
                                        <?php if (!$isMe): ?>
                                            <?= webp_avatar($post['author_avatar'] ?? null, $post['author_name'], 32) ?>
                                        <?php endif; ?>
                                        <div class="holo-chat-bubble-wrap">
                                            <?php if (!$isMe): ?>
                                                <div class="holo-chat-author"><?= htmlspecialchars($post['author_name']) ?></div>
                                            <?php endif; ?>
                                            <div class="holo-chat-bubble">
                                                <?= nl2br(htmlspecialchars($post['content'])) ?>
                                                <div class="holo-chat-time"><?= date('g:i A', strtotime($post['created_at'])) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="holo-chat-reply-dock">
                                <?php if (isset($_SESSION['user_id']) && $isMember): ?>
                                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/discussions/<?= $activeDiscussion['id'] ?>/reply" method="POST" class="holo-chat-reply-form">
                                        <?= \Nexus\Core\Csrf::input() ?>
                                        <textarea name="content" class="holo-chat-input" rows="1" placeholder="Type a message..." required
                                                  oninput="this.style.height = ''; this.style.height = Math.min(this.scrollHeight, 120) + 'px'"></textarea>
                                        <button type="submit" class="holo-chat-send">
                                            <i class="fa-solid fa-paper-plane"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div style="text-align: center; color: var(--htb-text-muted); padding: 8px;">
                                        <i class="fa-solid fa-lock"></i> Join hub to reply
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <script>
                            setTimeout(() => {
                                const el = document.getElementById('chatStream');
                                if (el) el.scrollTop = el.scrollHeight;
                            }, 100);
                        </script>

                    <?php else: ?>
                        <!-- Discussion List -->
                        <div class="holo-section-header">
                            <div>
                                <h2 class="holo-section-title"><i class="fa-solid fa-comments"></i> Discussions</h2>
                                <p class="holo-section-subtitle">Join the conversation</p>
                            </div>
                            <?php if ($isMember): ?>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/discussions/create" class="holo-section-action">
                                    <i class="fa-solid fa-plus"></i>
                                    <span>Start Topic</span>
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php
                        $discussions = \Nexus\Models\GroupDiscussion::getForGroup($group['id']);
                        ?>

                        <?php if (empty($discussions)): ?>
                            <div class="holo-empty-state">
                                <div class="holo-empty-icon"><i class="fa-regular fa-comments"></i></div>
                                <h3 class="holo-empty-title">It's quiet in here...</h3>
                                <p class="holo-empty-text">Be the first to start a discussion!</p>
                                <?php if ($isMember): ?>
                                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/discussions/create" class="holo-section-action">
                                        <i class="fa-solid fa-plus"></i> Start Topic
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="holo-discussions-list">
                                <?php foreach ($discussions as $disc): ?>
                                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/discussions/<?= $disc['id'] ?>" class="holo-discussion-item">
                                        <?= webp_avatar($disc['author_avatar'] ?? null, $disc['author_name'], 40) ?>
                                        <div class="holo-discussion-content">
                                            <h3 class="holo-discussion-title"><?= htmlspecialchars($disc['title']) ?></h3>
                                            <div class="holo-discussion-meta">
                                                <span><i class="fa-regular fa-user"></i> <?= htmlspecialchars($disc['author_name']) ?></span>
                                                <span><i class="fa-regular fa-comment"></i> <?= $disc['reply_count'] ?></span>
                                                <span><i class="fa-regular fa-clock"></i> <?= date('M j', strtotime($disc['last_reply_at'] ?? $disc['created_at'])) ?></span>
                                            </div>
                                        </div>
                                        <i class="fa-solid fa-chevron-right holo-discussion-arrow"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- ═══════════════════════════════════════════════════════════════
                     EVENTS TAB
                     ═══════════════════════════════════════════════════════════════ -->
                <div id="tab-events" class="holo-tab-pane <?= $activeTab == 'events' ? 'active' : '' ?>">
                    <div class="holo-section-header">
                        <div>
                            <h2 class="holo-section-title"><i class="fa-solid fa-calendar"></i> Events</h2>
                            <p class="holo-section-subtitle">Upcoming hub activities</p>
                        </div>
                        <?php if ($isMember): ?>
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/create?group_id=<?= $group['id'] ?>" class="holo-section-action">
                                <i class="fa-solid fa-plus"></i>
                                <span>Create Event</span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php
                    $groupEvents = \Nexus\Models\Event::getForGroup($group['id']);
                    ?>

                    <?php if (empty($groupEvents)): ?>
                        <div class="holo-empty-state">
                            <div class="holo-empty-icon"><i class="fa-regular fa-calendar"></i></div>
                            <h3 class="holo-empty-title">No upcoming events</h3>
                            <p class="holo-empty-text">Check back later or create an event!</p>
                        </div>
                    <?php else: ?>
                        <div class="holo-events-list">
                            <?php foreach ($groupEvents as $ev): ?>
                                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $ev['id'] ?>" class="holo-event-card">
                                    <div class="holo-event-date">
                                        <span class="holo-event-month"><?= date('M', strtotime($ev['start_time'])) ?></span>
                                        <span class="holo-event-day"><?= date('d', strtotime($ev['start_time'])) ?></span>
                                    </div>
                                    <div class="holo-event-content">
                                        <h3 class="holo-event-title"><?= htmlspecialchars($ev['title']) ?></h3>
                                        <div class="holo-event-location">
                                            <i class="fa-solid fa-location-dot"></i>
                                            <?= htmlspecialchars($ev['location']) ?>
                                        </div>
                                        <div class="holo-event-organizer">By <?= htmlspecialchars($ev['organizer_name']) ?></div>
                                    </div>
                                    <div class="holo-event-action hide-mobile">
                                        <span class="holo-event-btn">View</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ═══════════════════════════════════════════════════════════════
                     REVIEWS TAB
                     ═══════════════════════════════════════════════════════════════ -->
                <?php if ($isMember || $isOrganizer): ?>
                <div id="tab-reviews" class="holo-tab-pane <?= $activeTab == 'reviews' ? 'active' : '' ?>">
                    <div class="holo-section-header">
                        <div>
                            <h2 class="holo-section-title"><i class="fa-solid fa-star"></i> Member Reviews</h2>
                            <p class="holo-section-subtitle">Rate members based on your interactions</p>
                        </div>
                    </div>

                    <?php if (isset($_GET['submitted'])): ?>
                        <div style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 14px 18px; border-radius: var(--holo-radius-xs); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <i class="fa-solid fa-check-circle"></i>
                            <span>Your review has been submitted!</span>
                        </div>
                    <?php endif; ?>

                    <div id="reviews-list">
                        <div style="text-align: center; padding: 40px; color: var(--htb-text-muted);">
                            <i class="fa-solid fa-spinner fa-spin" style="font-size: 1.5rem; margin-bottom: 12px; display: block;"></i>
                            <span>Loading reviews...</span>
                        </div>
                    </div>

                    <script>
                    (function() {
                        fetch('<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/reviews')
                            .then(r => r.json())
                            .then(data => {
                                if (!data.success) return;

                                if (data.reviews.length === 0) {
                                    document.getElementById('reviews-list').innerHTML = `
                                        <div class="holo-empty-state">
                                            <div class="holo-empty-icon"><i class="fa-solid fa-star"></i></div>
                                            <h3 class="holo-empty-title">No reviews yet</h3>
                                            <p class="holo-empty-text">Be the first to review a member from the Members tab!</p>
                                        </div>
                                    `;
                                } else {
                                    let listHtml = '<div class="holo-discussions-list">';
                                    data.reviews.forEach(review => {
                                        const date = new Date(review.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                                        const stars = Array(5).fill(0).map((_, i) =>
                                            `<i class="${i < review.rating ? 'fas' : 'far'} fa-star" style="color: #fbbf24; font-size: 0.7rem;"></i>`
                                        ).join('');

                                        listHtml += `
                                            <div class="holo-discussion-item" style="cursor: default;">
                                                <img src="${review.reviewer_avatar || '/assets/images/default-avatar.svg'}" class="holo-discussion-avatar" alt="">
                                                <div class="holo-discussion-content">
                                                    <div class="holo-discussion-title" style="font-size: 0.9rem;">
                                                        <strong>${escapeHtml(review.reviewer_name)}</strong> reviewed <strong>${escapeHtml(review.receiver_name)}</strong>
                                                    </div>
                                                    <div style="display: flex; gap: 2px; margin: 6px 0;">${stars}</div>
                                                    ${review.comment ? `<p style="margin: 8px 0 0 0; font-size: 0.85rem; color: var(--htb-text-main); line-height: 1.5;">${escapeHtml(review.comment)}</p>` : ''}
                                                    <div class="holo-discussion-meta" style="margin-top: 8px;">
                                                        <span><i class="fa-regular fa-clock"></i> ${date}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        `;
                                    });
                                    listHtml += '</div>';
                                    document.getElementById('reviews-list').innerHTML = listHtml;
                                }
                            })
                            .catch(() => {
                                document.getElementById('reviews-list').innerHTML = `
                                    <div class="holo-empty-state">
                                        <div class="holo-empty-icon"><i class="fa-solid fa-exclamation-triangle"></i></div>
                                        <h3 class="holo-empty-title">Error loading reviews</h3>
                                        <p class="holo-empty-text">Please try refreshing the page.</p>
                                    </div>
                                `;
                            });

                        function escapeHtml(text) {
                            const div = document.createElement('div');
                            div.textContent = text;
                            return div.innerHTML;
                        }
                    })();
                    </script>
                </div>
                <?php endif; ?>

                <!-- ═══════════════════════════════════════════════════════════════
                     SETTINGS TAB (Organizer Only)
                     ═══════════════════════════════════════════════════════════════ -->
                <?php if ($isOrganizer): ?>
                <div id="tab-settings" class="holo-tab-pane <?= $activeTab == 'settings' ? 'active' : '' ?>">
                    <div class="holo-section-header">
                        <div>
                            <h2 class="holo-section-title"><i class="fa-solid fa-gear"></i> Hub Settings</h2>
                            <p class="holo-section-subtitle">Manage your hub and members</p>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 24px;">
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/edit-group/<?= $group['id'] ?>?tab=edit"
                           class="holo-action-card"
                           style="display: flex; align-items: center; gap: 12px; padding: 16px; background: var(--holo-card-bg, white); border: 1px solid var(--holo-border-color, rgba(0,0,0,0.06)); border-radius: var(--holo-radius-sm); text-decoration: none; color: inherit; transition: var(--holo-transition);">
                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #3b82f6, #6366f1); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fa-solid fa-pen"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: var(--htb-text-main);">Edit Hub</div>
                                <div style="font-size: 0.8rem; color: var(--htb-text-muted);">Update info & cover</div>
                            </div>
                        </a>

                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/edit-group/<?= $group['id'] ?>?tab=invite"
                           class="holo-action-card"
                           style="display: flex; align-items: center; gap: 12px; padding: 16px; background: var(--holo-card-bg, white); border: 1px solid var(--holo-border-color, rgba(0,0,0,0.06)); border-radius: var(--holo-radius-sm); text-decoration: none; color: inherit; transition: var(--holo-transition);">
                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #10b981, #14b8a6); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fa-solid fa-user-plus"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: var(--htb-text-main);">Invite Members</div>
                                <div style="font-size: 0.8rem; color: var(--htb-text-muted);">Grow your hub</div>
                            </div>
                        </a>
                    </div>

                    <!-- Pending Requests Section -->
                    <?php if (!empty($pendingMembers)): ?>
                    <div style="margin-bottom: 32px; padding: 20px; background: linear-gradient(135deg, #fef3c7, #fde68a); border: 2px solid #f59e0b; border-radius: 16px;">
                        <h3 style="font-size: 1rem; font-weight: 700; margin: 0 0 16px 0; color: #92400e; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-user-clock"></i>
                            Pending Requests (<?= count($pendingMembers) ?>)
                        </h3>
                        <p style="font-size: 0.85rem; color: #a16207; margin-bottom: 16px;">
                            These members have requested to join your hub. Approve or deny their requests.
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($pendingMembers as $pending): ?>
                                <div style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                                    <?= webp_avatar($pending['avatar_url'] ?? null, $pending['name'], 44) ?>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: #1f2937;"><?= htmlspecialchars($pending['name']) ?></div>
                                        <div style="font-size: 0.8rem; color: #6b7280;">Waiting for approval</div>
                                    </div>
                                    <div style="display: flex; gap: 8px;">
                                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/manage-member" method="POST" style="margin: 0;">
                                            <?= \Nexus\Core\Csrf::input() ?>
                                            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $pending['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" style="padding: 8px 16px; background: linear-gradient(135deg, #10b981, #059669); border: none; border-radius: 8px; color: white; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                                <i class="fa-solid fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/manage-member" method="POST" style="margin: 0;" onsubmit="return confirm('Deny this request?');">
                                            <?= \Nexus\Core\Csrf::input() ?>
                                            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $pending['id'] ?>">
                                            <input type="hidden" name="action" value="deny">
                                            <button type="submit" style="padding: 8px 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #dc2626; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                                <i class="fa-solid fa-times"></i> Deny
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Invited Members Section -->
                    <?php if (!empty($invitedMembers)): ?>
                    <div style="margin-bottom: 32px; padding: 20px; background: linear-gradient(135deg, #ede9fe, #ddd6fe); border: 2px solid #a78bfa; border-radius: 16px;">
                        <h3 style="font-size: 1rem; font-weight: 700; margin: 0 0 16px 0; color: #5b21b6; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-envelope"></i>
                            Invited (<?= count($invitedMembers) ?>)
                        </h3>
                        <p style="font-size: 0.85rem; color: #6d28d9; margin-bottom: 16px;">
                            These members have been invited but haven't accepted yet.
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($invitedMembers as $invited): ?>
                                <div style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                                    <?= webp_avatar($invited['avatar_url'] ?? null, $invited['name'], 44) ?>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: #1f2937;"><?= htmlspecialchars($invited['name']) ?></div>
                                        <div style="font-size: 0.8rem; color: #6b7280;">Invitation sent</div>
                                    </div>
                                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/manage-member" method="POST" style="margin: 0;" onsubmit="return confirm('Cancel this invitation?');">
                                        <?= \Nexus\Core\Csrf::input() ?>
                                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                        <input type="hidden" name="user_id" value="<?= $invited['id'] ?>">
                                        <input type="hidden" name="action" value="kick">
                                        <button type="submit" style="padding: 8px 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #dc2626; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                            <i class="fa-solid fa-times"></i> Cancel
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Member Management -->
                    <h3 style="font-size: 1rem; font-weight: 700; margin: 24px 0 16px 0; color: var(--htb-text-main);">
                        <i class="fa-solid fa-users-gear" style="color: var(--holo-primary); margin-right: 8px;"></i>
                        Manage Members
                    </h3>

                    <div class="holo-discussions-list">
                        <?php foreach ($members as $mem):
                            $isOwner = ($mem['id'] == $group['owner_id']);
                            if ($isOwner) continue;
                            $isOrg = ($mem['role'] === 'admin');
                        ?>
                            <div class="holo-discussion-item" style="cursor: default;">
                                <?= webp_avatar($mem['avatar_url'] ?? null, $mem['name'], 40) ?>
                                <div class="holo-discussion-content">
                                    <h3 class="holo-discussion-title"><?= htmlspecialchars($mem['name']) ?></h3>
                                    <div class="holo-discussion-meta">
                                        <?php if ($isOrg): ?>
                                            <span style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">
                                                <i class="fa-solid fa-star"></i> Organiser
                                            </span>
                                        <?php else: ?>
                                            <span>Member</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <?php if ($isOrg): ?>
                                        <!-- Demote to Member -->
                                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/manage-member" method="POST" style="margin: 0;" onsubmit="return confirm('Demote this organiser to regular member?');">
                                            <?= \Nexus\Core\Csrf::input() ?>
                                            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $mem['id'] ?>">
                                            <input type="hidden" name="action" value="demote">
                                            <button type="submit" style="padding: 8px 12px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; color: #92400e; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                                                <i class="fa-solid fa-arrow-down"></i> Demote
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Promote to Organiser -->
                                        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/manage-member" method="POST" style="margin: 0;" onsubmit="return confirm('Promote this member to organiser?');">
                                            <?= \Nexus\Core\Csrf::input() ?>
                                            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $mem['id'] ?>">
                                            <input type="hidden" name="action" value="promote">
                                            <button type="submit" style="padding: 8px 12px; background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; color: #166534; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                                                <i class="fa-solid fa-arrow-up"></i> Promote
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <!-- Remove -->
                                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/manage-member" method="POST" style="margin: 0;" onsubmit="return confirm('Remove this user from the hub?');">
                                        <?= \Nexus\Core\Csrf::input() ?>
                                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                        <input type="hidden" name="user_id" value="<?= $mem['id'] ?>">
                                        <input type="hidden" name="action" value="kick">
                                        <button type="submit" style="padding: 8px 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #dc2626; font-size: 0.8rem; font-weight: 600; cursor: pointer;">
                                            <i class="fa-solid fa-times"></i> Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- .holo-tab-content -->
        </main>
    </div><!-- .holo-container -->

    <!-- ═══════════════════════════════════════════════════════════════════════
         MOBILE ACTION BAR
         ═══════════════════════════════════════════════════════════════════════ -->
    <div class="holo-action-bar">
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if ($isMember): ?>
                <div class="holo-action-info">
                    <div class="holo-action-member-badge">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Member</span>
                    </div>
                    <div class="holo-action-subtitle"><?= count($members) ?> members</div>
                </div>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/leave" method="POST" class="ajax-form" data-reload="true" style="margin: 0;">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                    <button type="submit" class="holo-action-btn secondary">Leave</button>
                </form>
            <?php elseif ($isPending): ?>
                <div class="holo-action-info">
                    <div class="holo-action-member-badge" style="background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e;">
                        <i class="fa-solid fa-clock"></i>
                        <span>Pending</span>
                    </div>
                    <div class="holo-action-subtitle">Waiting for organiser approval</div>
                </div>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/leave" method="POST" class="ajax-form" data-reload="true" style="margin: 0;" onsubmit="return confirm('Cancel your join request?');">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                    <button type="submit" class="holo-action-btn secondary" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;">
                        <i class="fa-solid fa-times"></i> Cancel Request
                    </button>
                </form>
            <?php else: ?>
                <div class="holo-action-info">
                    <h4 class="holo-action-title">Join <?= htmlspecialchars($group['name']) ?></h4>
                    <div class="holo-action-subtitle"><?= count($members) ?> members<?= $group['visibility'] === 'private' ? ' · Private hub' : '' ?></div>
                </div>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/join" method="POST" class="ajax-form" data-reload="true" style="margin: 0;">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                    <button type="submit" class="holo-action-btn primary">
                        <i class="fa-solid fa-plus"></i> <?= $group['visibility'] === 'private' ? 'Request to Join' : 'Join' ?>
                    </button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <div class="holo-action-info">
                <h4 class="holo-action-title">Join this Hub</h4>
                <div class="holo-action-subtitle">Login to become a member</div>
            </div>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/login" class="holo-action-btn primary">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Login
            </a>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         FLOATING ACTION BUTTON
         ═══════════════════════════════════════════════════════════════════════ -->
    <?php if ($isMember): ?>
    <div class="holo-fab">
        <button class="holo-fab-main" onclick="toggleFab()" aria-label="Quick Actions">
            <i class="fa-solid fa-plus"></i>
        </button>
        <div class="holo-fab-menu" id="fabMenu">
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/discussions/create" class="holo-fab-item">
                <i class="fa-solid fa-comments icon-discuss"></i>
                <span>Start Discussion</span>
            </a>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/events/create?group_id=<?= $group['id'] ?>" class="holo-fab-item">
                <i class="fa-solid fa-calendar-plus icon-event"></i>
                <span>Create Event</span>
            </a>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/members" class="holo-fab-item">
                <i class="fa-solid fa-user-plus icon-invite"></i>
                <span>Invite Members</span>
            </a>
        </div>
    </div>
    <?php endif; ?>

</div><!-- .holo-page -->

<!-- ═══════════════════════════════════════════════════════════════════════════
     REVIEW MODAL
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php if ($isMember): ?>
<div id="reviewModal" class="holo-modal-overlay">
    <div class="holo-modal">
        <div class="holo-modal-header">
            <h3 class="holo-modal-title">Leave a Review</h3>
            <button onclick="closeReviewModal()" class="holo-modal-close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="holo-modal-body">
            <div style="text-align: center; margin-bottom: 20px;">
                <img id="reviewMemberAvatar" src="" style="width: 64px; height: 64px; border-radius: 50%; margin-bottom: 10px;" loading="lazy">
                <div style="font-weight: 700; font-size: 1rem;" id="reviewMemberName"></div>
            </div>

            <form id="reviewForm" action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>/reviews" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="receiver_id" id="reviewReceiverId" value="">

                <div class="holo-form-group" style="text-align: center;">
                    <label class="holo-form-label">Your Rating</label>
                    <div class="holo-star-rating" id="starRating">
                        <i class="far fa-star" data-rating="1"></i>
                        <i class="far fa-star" data-rating="2"></i>
                        <i class="far fa-star" data-rating="3"></i>
                        <i class="far fa-star" data-rating="4"></i>
                        <i class="far fa-star" data-rating="5"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" value="" required>
                    <div id="ratingLabel" style="color: var(--htb-text-muted); font-size: 0.9rem; margin-top: 8px;"></div>
                </div>

                <div class="holo-form-group">
                    <label class="holo-form-label">Comment (optional)</label>
                    <textarea name="comment" class="holo-form-textarea" placeholder="Share your experience..."></textarea>
                </div>

                <button type="submit" id="submitBtn" class="holo-form-submit" disabled>
                    <i class="fa-solid fa-paper-plane" style="margin-right: 8px;"></i> Submit Review
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT
     ═══════════════════════════════════════════════════════════════════════════ -->
<script>
// Tab Switching
function switchTab(tabId) {
    // Hide all panes
    document.querySelectorAll('.holo-tab-pane').forEach(el => el.classList.remove('active'));
    // Deactivate all buttons
    document.querySelectorAll('.holo-tab-btn').forEach(el => el.classList.remove('active'));

    // Show target pane
    const target = document.getElementById('tab-' + tabId);
    if (target) {
        target.classList.add('active');
    }

    // Activate target button
    const btn = document.getElementById('btn-' + tabId);
    if (btn) btn.classList.add('active');

    // Scroll tab into view on mobile
    if (btn && window.innerWidth <= 768) {
        btn.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }
}

// FAB Toggle
function toggleFab() {
    const btn = document.querySelector('.holo-fab-main');
    const menu = document.getElementById('fabMenu');
    btn.classList.toggle('active');
    menu.classList.toggle('show');
}

// Close FAB when clicking outside
document.addEventListener('click', function(e) {
    const fab = document.querySelector('.holo-fab');
    if (fab && !fab.contains(e.target)) {
        document.querySelector('.holo-fab-main')?.classList.remove('active');
        document.getElementById('fabMenu')?.classList.remove('show');
    }
});

<?php if ($isMember): ?>
// Review Modal Functions
function openReviewModal(memberId, memberName, memberAvatar) {
    document.getElementById('reviewReceiverId').value = memberId;
    document.getElementById('reviewMemberName').textContent = memberName;
    document.getElementById('reviewMemberAvatar').src = memberAvatar || '/assets/images/default-avatar.svg';
    document.getElementById('reviewModal').classList.add('active');
    document.body.style.overflow = 'hidden';

    // Reset form
    document.getElementById('ratingInput').value = '';
    document.getElementById('ratingLabel').textContent = '';
    document.getElementById('submitBtn').disabled = true;
    document.querySelectorAll('#starRating i').forEach(s => {
        s.classList.remove('fas', 'active');
        s.classList.add('far');
    });
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Star Rating Interaction
(function() {
    const stars = document.querySelectorAll('#starRating i');
    const input = document.getElementById('ratingInput');
    const label = document.getElementById('ratingLabel');
    const btn = document.getElementById('submitBtn');
    const labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

    function setRating(rating) {
        input.value = rating;
        btn.disabled = false;
        label.textContent = labels[rating];

        stars.forEach((s, i) => {
            if (i < rating) {
                s.classList.remove('far');
                s.classList.add('fas', 'active');
            } else {
                s.classList.remove('fas', 'active');
                s.classList.add('far');
            }
        });
    }

    stars.forEach(star => {
        star.addEventListener('click', function(e) {
            e.preventDefault();
            setRating(parseInt(this.dataset.rating));
        });

        star.addEventListener('mouseenter', function() {
            const rating = parseInt(this.dataset.rating);
            stars.forEach((s, i) => {
                if (i < rating) s.style.color = '#fbbf24';
            });
        });

        star.addEventListener('mouseleave', function() {
            const currentRating = parseInt(input.value) || 0;
            stars.forEach((s, i) => {
                if (!s.classList.contains('active')) s.style.color = '';
            });
        });
    });
})();

// Close modal on overlay click
document.getElementById('reviewModal').addEventListener('click', function(e) {
    if (e.target === this) closeReviewModal();
});

// Close on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('reviewModal').classList.contains('active')) {
        closeReviewModal();
    }
});
<?php endif; ?>

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
document.querySelectorAll('.holo-tab-btn, .holo-section-action, .holo-member-btn, button').forEach(btn => {
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

// ═══════════════════════════════════════════════════════════════════════════
// GROUP FEED FUNCTIONALITY
// ═══════════════════════════════════════════════════════════════════════════

let groupFeedOffset = 0;
let groupFeedLoading = false;
let groupFeedHasMore = true;
const GROUP_ID = <?= $group['id'] ?>;
const CURRENT_USER_ID = <?= $currentUserId ?>;
const BASE_PATH = '<?= Nexus\Core\TenantContext::getBasePath() ?>';
console.log('BASE_PATH:', BASE_PATH);

// Time elapsed helper
function timeElapsed(datetime) {
    const now = new Date();
    const then = new Date(datetime);
    const diff = Math.floor((now - then) / 1000);

    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd';
    if (diff < 2592000) return Math.floor(diff / 604800) + 'w';
    return Math.floor(diff / 2592000) + 'mo';
}

// Load group feed
async function loadGroupFeed(groupId, loadMore = false) {
    if (groupFeedLoading || (!loadMore && groupFeedOffset > 0)) return;

    groupFeedLoading = true;
    const container = document.getElementById('groupFeedPosts');
    const loadMoreBtn = document.getElementById('groupFeedLoadMore');

    if (!loadMore) {
        container.innerHTML = '<div class="feed-loading"><i class="fa-solid fa-spinner fa-spin"></i><span>Loading posts...</span></div>';
        groupFeedOffset = 0;
    }

    try {
        const apiUrl = BASE_PATH + '/api/social/feed';
        console.log('Fetching feed from:', apiUrl);
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache'
            },
            cache: 'no-store',
            body: JSON.stringify({
                group_id: groupId,
                offset: groupFeedOffset,
                limit: 10,
                filter: 'posts'
            })
        });

        const data = await response.json();

        if (!loadMore) {
            container.innerHTML = '';
        }

        if (data.success && data.items && data.items.length > 0) {
            data.items.forEach(post => {
                container.appendChild(createPostElement(post));
            });

            groupFeedOffset += data.items.length;
            groupFeedHasMore = data.items.length >= 10;
            loadMoreBtn.style.display = groupFeedHasMore ? 'block' : 'none';
        } else if (!loadMore) {
            container.innerHTML = `
                <div class="feed-empty">
                    <i class="fa-regular fa-comment-dots"></i>
                    <h3>No posts yet</h3>
                    <p>Be the first to share something with the hub!</p>
                </div>
            `;
            loadMoreBtn.style.display = 'none';
        }
    } catch (error) {
        console.error('Error loading feed:', error);
        if (!loadMore) {
            container.innerHTML = `
                <div class="feed-empty">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <h3>Failed to load feed</h3>
                    <p>Please try again later</p>
                </div>
            `;
        }
    }

    groupFeedLoading = false;
}

// Create post element
function createPostElement(post) {
    const div = document.createElement('div');
    div.className = 'group-feed-post';
    div.id = 'post-' + post.id;

    const isLiked = post.is_liked ? 'liked' : '';
    const likeIcon = post.is_liked ? 'fa-solid' : 'fa-regular';
    const likesCount = post.likes_count || 0;
    const commentsCount = post.comments_count || 0;
    const authorAvatar = post.author_avatar || '/assets/img/defaults/default_avatar.webp';
    const authorName = post.author_name || 'Anonymous';

    let imageHtml = '';
    if (post.image_url) {
        imageHtml = `<img src="${escapeHtml(post.image_url)}" class="feed-post-image" loading="lazy">`;
    }

    let deleteBtn = '';
    if (post.user_id == CURRENT_USER_ID) {
        deleteBtn = `<button class="feed-action-btn" onclick="deleteGroupPost(${post.id})" title="Delete"><i class="fa-solid fa-trash"></i></button>`;
    }

    div.innerHTML = `
        <div class="feed-post-header">
            <a href="${BASE_PATH}/profile/${post.user_id}">
                <img src="${escapeHtml(authorAvatar)}" class="feed-post-avatar" alt="${escapeHtml(authorName)}" loading="lazy">
            </a>
            <div class="feed-post-author">
                <a href="${BASE_PATH}/profile/${post.user_id}" class="feed-post-author-name">${escapeHtml(authorName)}</a>
                <div class="feed-post-meta">${timeElapsed(post.created_at)}</div>
            </div>
        </div>
        <div class="feed-post-content">${escapeHtml(post.content || '').replace(/\n/g, '<br>')}</div>
        ${imageHtml}
        <div class="feed-post-actions">
            <button class="feed-action-btn ${isLiked}" onclick="toggleGroupLike(this, ${post.id})">
                <i class="${likeIcon} fa-heart"></i>
                <span>${likesCount > 0 ? likesCount + ' ' : ''}Like${likesCount !== 1 ? 's' : ''}</span>
            </button>
            <button class="feed-action-btn" onclick="toggleGroupComments(${post.id})">
                <i class="fa-regular fa-comment"></i>
                <span>${commentsCount > 0 ? commentsCount + ' ' : ''}Comment${commentsCount !== 1 ? 's' : ''}</span>
            </button>
            <button class="feed-action-btn" onclick="shareGroupPost(${post.id}, '${escapeHtml(authorName)}')">
                <i class="fa-solid fa-share"></i>
                <span>Share</span>
            </button>
            ${deleteBtn}
        </div>
        <div id="comments-${post.id}" class="feed-post-comments" style="display:none;">
            <div class="feed-comment-input-row">
                <input type="text" class="feed-comment-input" placeholder="Write a comment..." onkeydown="if(event.key==='Enter')submitGroupComment(this, ${post.id})">
                <button class="feed-action-btn" onclick="submitGroupComment(this.previousElementSibling, ${post.id})">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
            <div class="comments-list"></div>
        </div>
    `;

    return div;
}

// Escape HTML helper
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Submit new group post
async function submitGroupPost(groupId) {
    const content = document.getElementById('groupPostContent').value.trim();
    const imageInput = document.getElementById('groupPostImage');
    const submitBtn = document.querySelector('.composer-submit-btn');

    if (!content && !imageInput.files.length) {
        alert('Please enter some content or add an image');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Posting...';

    try {
        // Use FormData to send both content and image together
        const formData = new FormData();
        formData.append('content', content);
        formData.append('group_id', groupId);
        formData.append('visibility', 'public');

        // Add image if present
        if (imageInput.files.length > 0) {
            formData.append('image', imageInput.files[0]);
        }

        // Create the post with image in one request
        const response = await fetch(BASE_PATH + '/api/social/create-post', {
            method: 'POST',
            body: formData  // Don't set Content-Type header - browser sets it with boundary
        });

        const data = await response.json();

        if (data.success) {
            // Clear form
            document.getElementById('groupPostContent').value = '';
            clearGroupPostImage();

            // Reload feed to show new post
            groupFeedOffset = 0;
            loadGroupFeed(groupId);
        } else {
            alert(data.error || 'Failed to create post');
        }
    } catch (error) {
        console.error('Error creating post:', error);
        alert('Failed to create post. Please try again.');
    }

    submitBtn.disabled = false;
    submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> <span>Post</span>';
}

// Image preview
document.getElementById('groupPostImage')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('groupPostImagePreview');
            preview.querySelector('img').src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});

function clearGroupPostImage() {
    const input = document.getElementById('groupPostImage');
    const preview = document.getElementById('groupPostImagePreview');
    if (input) input.value = '';
    if (preview) {
        preview.style.display = 'none';
        preview.querySelector('img').src = '';
    }
}

// Toggle like
async function toggleGroupLike(btn, postId) {
    try {
        const response = await fetch(BASE_PATH + '/api/social/like', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                target_type: 'post',
                target_id: postId
            })
        });

        const data = await response.json();

        if (data.success) {
            const isLiked = data.action === 'liked';
            const icon = btn.querySelector('i');
            const span = btn.querySelector('span');
            const count = data.likes_count || 0;

            btn.classList.toggle('liked', isLiked);
            icon.className = (isLiked ? 'fa-solid' : 'fa-regular') + ' fa-heart';
            span.textContent = (count > 0 ? count + ' ' : '') + 'Like' + (count !== 1 ? 's' : '');
        }
    } catch (error) {
        console.error('Error toggling like:', error);
    }
}

// Check if mobile device
function isMobileDevice() {
    return window.innerWidth <= 768 || ('ontouchstart' in window);
}

// Toggle comments section
async function toggleGroupComments(postId) {
    // On mobile, use the mobile comment sheet instead
    if (isMobileDevice() && typeof openMobileCommentSheet === 'function') {
        openMobileCommentSheet('post', postId, '');
        return;
    }

    // Desktop: toggle inline comments
    const section = document.getElementById('comments-' + postId);
    if (!section) return;

    const isVisible = section.style.display !== 'none';
    section.style.display = isVisible ? 'none' : 'block';

    if (!isVisible) {
        // Load comments
        await loadGroupComments(postId);
    }
}

// Available reactions
const REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🎉'];

// Load comments with nested replies
async function loadGroupComments(postId) {
    const section = document.getElementById('comments-' + postId);
    const list = section.querySelector('.comments-list');
    list.innerHTML = '<div class="gf-no-comments"><i class="fa-solid fa-spinner fa-spin"></i><br>Loading...</div>';

    try {
        const response = await fetch(BASE_PATH + '/api/social/comments', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'fetch_comments',
                target_type: 'post',
                target_id: postId
            })
        });

        const data = await response.json();

        if ((data.success || data.status === 'success') && data.comments) {
            if (data.comments.length === 0) {
                list.innerHTML = '<div class="gf-no-comments"><i class="fa-regular fa-comment-dots"></i><br>No comments yet. Be the first!</div>';
                return;
            }
            list.innerHTML = data.comments.map(comment => renderComment(comment, postId)).join('');
        }
    } catch (error) {
        console.error('Error loading comments:', error);
        list.innerHTML = '<div class="gf-no-comments" style="color:#ef4444;"><i class="fa-solid fa-exclamation-triangle"></i><br>Failed to load comments</div>';
    }
}

// Render a single comment with nested replies
function renderComment(comment, postId, isReply = false) {
    const avatar = escapeHtml(comment.author_avatar || comment.avatar_url || '/assets/img/defaults/default_avatar.webp');
    const name = escapeHtml(comment.author_name || comment.user_name || 'User');
    const content = formatMentions(escapeHtml(comment.content || ''));
    const time = timeElapsed(comment.created_at);
    const commentId = comment.id;
    const isOwner = comment.is_owner || (comment.user_id == CURRENT_USER_ID);

    // Build reactions HTML
    let reactionsHtml = '';
    if (comment.reactions && Object.keys(comment.reactions).length > 0) {
        const userReactions = comment.user_reactions || [];
        reactionsHtml = '<div class="gf-reactions">' +
            Object.entries(comment.reactions).map(([emoji, count]) => {
                const isActive = userReactions.includes(emoji) ? 'active' : '';
                return `<span class="gf-reaction ${isActive}" onclick="gfToggleReaction(${commentId}, '${emoji}', ${postId})">${emoji} ${count}</span>`;
            }).join('') +
        '</div>';
    }

    // Build replies HTML
    let repliesHtml = '';
    if (comment.replies && comment.replies.length > 0) {
        repliesHtml = '<div class="gf-replies">' +
            comment.replies.map(reply => renderComment(reply, postId, true)).join('') +
        '</div>';
    }

    return `
        <div class="gf-comment-wrapper" data-comment-id="${commentId}">
            <div class="gf-comment">
                <img src="${avatar}" class="gf-comment-avatar" alt="${name}" loading="lazy">
                <div class="gf-comment-body">
                    <div class="gf-comment-header">
                        <span class="gf-comment-author">${name}</span>
                        <span class="gf-comment-time">${time}</span>
                        ${comment.is_edited ? '<span class="gf-comment-time">(edited)</span>' : ''}
                    </div>
                    <div class="gf-comment-content">${content}</div>
                    ${reactionsHtml}
                    <div class="gf-comment-actions">
                        <button type="button" class="gf-comment-action" onclick="gfShowReactionPicker(this, ${commentId}, ${postId})">
                            <i class="fa-regular fa-face-smile"></i> React
                        </button>
                        ${!isReply ? `<button type="button" class="gf-comment-action" onclick="gfShowReplyForm(${commentId}, ${postId})">
                            <i class="fa-solid fa-reply"></i> Reply
                        </button>` : ''}
                        ${isOwner ? `<button type="button" class="gf-comment-action" onclick="gfDeleteComment(${commentId}, ${postId})">
                            <i class="fa-solid fa-trash"></i>
                        </button>` : ''}
                    </div>
                    <div class="gf-reaction-picker" id="reaction-picker-${commentId}">
                        ${REACTIONS.map(r => `<span onclick="gfToggleReaction(${commentId}, '${r}', ${postId})">${r}</span>`).join('')}
                    </div>
                </div>
            </div>
            <div class="gf-reply-form" id="reply-form-${commentId}" style="display:none;">
                <input type="text" placeholder="Write a reply..." onkeypress="if(event.key==='Enter'){event.preventDefault();gfSubmitReply(this, ${postId}, ${commentId});}">
                <button type="button" onclick="gfSubmitReply(this.previousElementSibling, ${postId}, ${commentId})">Reply</button>
            </div>
            ${repliesHtml}
        </div>
    `;
}

// Format @mentions in content
function formatMentions(text) {
    return text.replace(/@(\w+)/g, '<span class="mention">@$1</span>');
}

// Show/hide reaction picker (prefixed to avoid conflict with global SocialInteractions)
function gfShowReactionPicker(btn, commentId, postId) {
    // Close all other pickers first
    document.querySelectorAll('.gf-reaction-picker.show').forEach(p => p.classList.remove('show'));
    const picker = document.getElementById('reaction-picker-' + commentId);
    if (picker) {
        picker.classList.toggle('show');
        // Close when clicking outside
        setTimeout(() => {
            document.addEventListener('click', function closePickerHandler(e) {
                if (!picker.contains(e.target) && !btn.contains(e.target)) {
                    picker.classList.remove('show');
                    document.removeEventListener('click', closePickerHandler);
                }
            });
        }, 10);
    }
}

// Toggle emoji reaction on comment (prefixed to avoid conflict with global SocialInteractions)
async function gfToggleReaction(commentId, emoji, postId) {
    try {
        const response = await fetch(BASE_PATH + '/api/social/reaction', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                target_type: 'comment',
                target_id: commentId,
                emoji: emoji
            })
        });
        const data = await response.json();
        if (data.success) {
            // Close picker and reload comments to show updated reactions
            document.querySelectorAll('.gf-reaction-picker.show').forEach(p => p.classList.remove('show'));
            await loadGroupComments(postId);
        }
    } catch (error) {
        console.error('Error toggling reaction:', error);
    }
}

// Show reply form (prefixed to avoid conflict with global SocialInteractions)
function gfShowReplyForm(commentId, postId) {
    console.log('gfShowReplyForm called:', commentId, postId);
    // Hide all other reply forms
    document.querySelectorAll('.gf-reply-form').forEach(f => f.style.display = 'none');
    const form = document.getElementById('reply-form-' + commentId);
    console.log('Found form:', form);
    if (form) {
        form.style.display = 'flex';
        const input = form.querySelector('input');
        if (input) input.focus();
    } else {
        console.error('Reply form not found for comment:', commentId);
    }
}

// Submit reply to a comment (prefixed to avoid conflict with global SocialInteractions)
async function gfSubmitReply(input, postId, parentId) {
    console.log('gfSubmitReply called:', { postId, parentId, input: input?.value });
    const content = input.value.trim();
    if (!content) {
        console.log('Empty content, returning');
        return;
    }

    input.disabled = true;

    try {
        console.log('Sending reply request to:', BASE_PATH + '/api/social/reply');
        const response = await fetch(BASE_PATH + '/api/social/reply', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                target_type: 'post',
                target_id: postId,
                parent_id: parentId,
                content: content
            })
        });

        const data = await response.json();
        console.log('Reply response:', data);

        if (data.success || data.status === 'success') {
            input.value = '';
            input.parentElement.style.display = 'none';
            await loadGroupComments(postId);
            updateCommentCount(postId, 1);
        } else {
            console.error('Reply failed:', data.error);
            alert(data.error || 'Failed to post reply');
        }
    } catch (error) {
        console.error('Error submitting reply:', error);
        alert('Network error while posting reply');
    }

    input.disabled = false;
}

// Delete comment (prefixed to avoid conflict with global SocialInteractions)
async function gfDeleteComment(commentId, postId) {
    if (!confirm('Delete this comment?')) return;

    try {
        const response = await fetch(BASE_PATH + '/api/social/delete-comment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                comment_id: commentId
            })
        });

        const data = await response.json();

        if (data.success) {
            await loadGroupComments(postId);
            updateCommentCount(postId, -1);
        } else {
            alert(data.error || 'Failed to delete comment');
        }
    } catch (error) {
        console.error('Error deleting comment:', error);
    }
}

// Update comment count display
function updateCommentCount(postId, delta) {
    const post = document.getElementById('post-' + postId);
    if (post) {
        const commentBtn = post.querySelector('.feed-post-actions button:nth-child(2) span');
        if (commentBtn) {
            const currentText = commentBtn.textContent;
            const match = currentText.match(/(\d+)/);
            const count = Math.max(0, (match ? parseInt(match[1]) : 0) + delta);
            commentBtn.textContent = (count > 0 ? count + ' ' : '') + 'Comment' + (count !== 1 ? 's' : '');
        }
    }
}

// Submit comment (main comment, not reply)
async function submitGroupComment(input, postId) {
    const content = input.value.trim();
    if (!content) return;

    input.disabled = true;

    try {
        const response = await fetch(BASE_PATH + '/api/social/comments', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'submit_comment',
                target_type: 'post',
                target_id: postId,
                content: content
            })
        });

        const data = await response.json();

        if (data.success || data.status === 'success') {
            input.value = '';
            await loadGroupComments(postId);
            updateCommentCount(postId, 1);
        } else {
            alert(data.error || 'Failed to post comment');
        }
    } catch (error) {
        console.error('Error submitting comment:', error);
    }

    input.disabled = false;
}

// Delete post
async function deleteGroupPost(postId) {
    if (!confirm('Are you sure you want to delete this post?')) return;

    try {
        const response = await fetch(BASE_PATH + '/api/social/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                target_type: 'post',
                target_id: postId
            })
        });

        const data = await response.json();

        if (data.success) {
            const post = document.getElementById('post-' + postId);
            if (post) {
                post.remove();
            }
        } else {
            alert(data.error || 'Failed to delete post');
        }
    } catch (error) {
        console.error('Error deleting post:', error);
    }
}

// Share post
function shareGroupPost(postId, authorName) {
    const caption = prompt(`Share this post by ${authorName}?\n\nAdd a comment (optional):`);
    if (caption === null) return;

    fetch(BASE_PATH + '/api/social/share', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            target_type: 'post',
            target_id: postId,
            comment: caption
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Post shared to your feed!');
        } else {
            alert(data.error || 'Failed to share post');
        }
    })
    .catch(err => {
        console.error('Error sharing:', err);
        alert('Failed to share post');
    });
}

// Load feed when Feed tab is activated
const originalSwitchTab = switchTab;
switchTab = function(tabId) {
    originalSwitchTab(tabId);

    if (tabId === 'feed' && groupFeedOffset === 0) {
        loadGroupFeed(GROUP_ID);
    }
};

// Auto-load feed if tab is already active on page load
document.addEventListener('DOMContentLoaded', function() {
    const feedTab = document.getElementById('tab-feed');
    if (feedTab && feedTab.classList.contains('active')) {
        loadGroupFeed(GROUP_ID);
    }
});
</script>

<?php
// Mobile Bottom Sheets - Now included centrally in footer.php
?>

<?php require dirname(__DIR__, 2) . '/layouts/modern/footer.php'; ?>

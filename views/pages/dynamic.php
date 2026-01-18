<?php
/**
 * Dynamic CMS Page Template
 * Renders pages created via the Page Builder
 * Matches the NEXUS Glassmorphism design system
 * Path: views/pages/dynamic.php
 */

use Nexus\Core\HtmlSanitizer;
use Nexus\Core\SmartBlockRenderer;

// Page data passed from controller
$pageTitle = $page['title'] ?? 'Page';
$pageContent = $content ?? '';

// Sanitize content as defense-in-depth
if (class_exists('Nexus\\Core\\HtmlSanitizer')) {
    $pageContent = HtmlSanitizer::sanitize($pageContent);
}

// Render Smart Blocks (Members Grid, Groups Grid, Listings Grid, Events Grid)
if (class_exists('Nexus\\Core\\SmartBlockRenderer')) {
    $renderer = new SmartBlockRenderer();
    $pageContent = $renderer->render($pageContent);
}

// LEGACY CONTENT FIX: Strip dark inline backgrounds from old GrapesJS content
// New blocks use CSS classes, but old saved content has inline styles like background:#1e293b
// These patterns aggressively remove dark backgrounds that conflict with light themes
$darkBgPatterns = [
    // Hex colors (dark blues, slates, grays)
    '/background(-color)?:\s*#(1[0-9a-fA-F]{5}|2[0-9a-fA-F]{5}|3[0-4][0-9a-fA-F]{4}|0[0-9a-fA-F]{5});?/i',
    // RGB dark colors
    '/background(-color)?:\s*rgb\(\s*([0-5]?[0-9]),\s*([0-5]?[0-9]),\s*([0-5]?[0-9])\s*\);?/i',
    // Linear gradients starting with dark colors
    '/background:\s*linear-gradient\([^)]*#(1[0-9a-fA-F]{5}|2[0-9a-fA-F]{5}|3[0-3][0-9a-fA-F]{4})[^;]*;?/i',
];

foreach ($darkBgPatterns as $pattern) {
    $pageContent = preg_replace($pattern, '', $pageContent);
}

// Clean up empty/malformed style attributes
$pageContent = preg_replace('/style="\s*"/', '', $pageContent);
$pageContent = preg_replace('/style="([^"]*?);\s*;([^"]*)"/', 'style="$1;$2"', $pageContent);

// Hero variables - hidden since we have inline hero
$hideHero = true;

// Determine the active layout (respects user's layout choice across Modern, CivicOne, Nexus Social)
$activeLayout = \Nexus\Services\LayoutHelper::get();

// Include the appropriate header for the active layout
require __DIR__ . '/../layouts/' . $activeLayout . '/header.php';

$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';

// Admin preview notice for unpublished pages
$showUnpublishedNotice = false;
if (isset($page) && !($page['is_published'] ?? 1)) {
    $isAdmin = ($_SESSION['user_role'] ?? '') === 'admin' || !empty($_SESSION['is_super_admin']);
    if ($isAdmin) {
        $showUnpublishedNotice = true;
    }
}
?>

<?php if ($showUnpublishedNotice): ?>
<div style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 1rem; text-align: center; font-weight: 600; position: sticky; top: 0; z-index: 9999;">
    <i class="fa-solid fa-eye-slash"></i> PREVIEW MODE - This page is unpublished and only visible to administrators
</div>
<?php endif; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ============================================
   NEXUS CMS PAGE - Premium Glassmorphism Design
   Gold Standard Holographic Effects for Modern Layout
   ============================================ */

/* Premium Design System Variables */
:root {
    /* Primary Holographic Spectrum */
    --glass-primary: #6366f1;
    --glass-secondary: #8b5cf6;
    --glass-tertiary: #a855f7;
    --glass-accent: #06b6d4;
    --glass-highlight: #f472b6;

    /* RGB values for alpha manipulation */
    --glass-primary-rgb: 99, 102, 241;
    --glass-secondary-rgb: 139, 92, 246;
    --glass-tertiary-rgb: 168, 85, 247;
    --glass-accent-rgb: 6, 182, 212;

    /* Text Colors */
    --glass-text-primary: #1e293b;
    --glass-text-secondary: #475569;
    --glass-text-muted: #64748b;

    /* Glass Surface */
    --glass-bg: rgba(255, 255, 255, 0.7);
    --glass-bg-intense: rgba(255, 255, 255, 0.85);
    --glass-border: rgba(255, 255, 255, 0.5);
    --glass-border-glow: rgba(99, 102, 241, 0.3);

    /* Premium Effects */
    --glass-blur: 20px;
    --glass-blur-intense: 40px;
    --glass-shadow: 0 8px 32px rgba(31, 38, 135, 0.12);
    --glass-shadow-hover: 0 20px 60px rgba(99, 102, 241, 0.2);
    --glass-shadow-glow: 0 0 40px rgba(99, 102, 241, 0.15);

    /* Holographic Gradients */
    --holo-gradient: linear-gradient(135deg,
        rgba(99, 102, 241, 0.1) 0%,
        rgba(139, 92, 246, 0.08) 25%,
        rgba(168, 85, 247, 0.06) 50%,
        rgba(6, 182, 212, 0.08) 75%,
        rgba(99, 102, 241, 0.1) 100%);
    --holo-border: linear-gradient(135deg,
        rgba(99, 102, 241, 0.4) 0%,
        rgba(139, 92, 246, 0.3) 25%,
        rgba(168, 85, 247, 0.2) 50%,
        rgba(6, 182, 212, 0.3) 75%,
        rgba(99, 102, 241, 0.4) 100%);
    --holo-shine: linear-gradient(135deg,
        transparent 0%,
        rgba(255, 255, 255, 0.4) 50%,
        transparent 100%);

    /* Spacing & Radius */
    --radius-sm: 12px;
    --radius-md: 20px;
    --radius-lg: 28px;
    --radius-xl: 36px;
}

/* Dark Mode Premium Override */
[data-theme="dark"] {
    --glass-text-primary: #f1f5f9;
    --glass-text-secondary: #e2e8f0;
    --glass-text-muted: #94a3b8;

    --glass-bg: rgba(15, 23, 42, 0.7);
    --glass-bg-intense: rgba(15, 23, 42, 0.85);
    --glass-border: rgba(255, 255, 255, 0.1);
    --glass-border-glow: rgba(139, 92, 246, 0.4);

    --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    --glass-shadow-hover: 0 20px 60px rgba(139, 92, 246, 0.3);
    --glass-shadow-glow: 0 0 60px rgba(139, 92, 246, 0.2);

    --holo-gradient: linear-gradient(135deg,
        rgba(99, 102, 241, 0.15) 0%,
        rgba(139, 92, 246, 0.12) 25%,
        rgba(168, 85, 247, 0.1) 50%,
        rgba(6, 182, 212, 0.12) 75%,
        rgba(99, 102, 241, 0.15) 100%);
}

/* Page Container - Premium Layout */
.nexus-cms-page {
    position: relative;
    min-height: 60vh;
    padding-top: 140px;
    padding-bottom: 100px;
    z-index: 20;
    overflow-x: hidden;
}

/* CivicOne layout has different nav height */
.civic-layout .nexus-cms-page {
    padding-top: 100px;
}

/* Nexus Social has sidebar - adjust width */
.fds-layout .nexus-cms-page {
    padding-top: 80px;
}

/* Premium Animated Background - Holographic Mesh */
.nexus-cms-page::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -2;
    pointer-events: none;
    background:
        radial-gradient(ellipse 80% 50% at 20% 30%, rgba(99, 102, 241, 0.08) 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 80% 20%, rgba(139, 92, 246, 0.06) 0%, transparent 50%),
        radial-gradient(ellipse 70% 60% at 70% 80%, rgba(6, 182, 212, 0.05) 0%, transparent 50%),
        radial-gradient(ellipse 50% 30% at 10% 70%, rgba(168, 85, 247, 0.04) 0%, transparent 50%);
    animation: holoShift 25s ease-in-out infinite;
}

/* Floating Holographic Orbs */
.nexus-cms-page::after {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    pointer-events: none;
    background:
        radial-gradient(circle 300px at 10% 20%, rgba(99, 102, 241, 0.03) 0%, transparent 70%),
        radial-gradient(circle 400px at 90% 50%, rgba(139, 92, 246, 0.03) 0%, transparent 70%),
        radial-gradient(circle 250px at 50% 90%, rgba(6, 182, 212, 0.02) 0%, transparent 70%);
    animation: orbFloat 30s ease-in-out infinite reverse;
}

[data-theme="dark"] .nexus-cms-page::before {
    background:
        radial-gradient(ellipse 80% 50% at 20% 30%, rgba(99, 102, 241, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 80% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
        radial-gradient(ellipse 70% 60% at 70% 80%, rgba(6, 182, 212, 0.08) 0%, transparent 50%),
        radial-gradient(ellipse 50% 30% at 10% 70%, rgba(168, 85, 247, 0.06) 0%, transparent 50%);
}

[data-theme="dark"] .nexus-cms-page::after {
    background:
        radial-gradient(circle 300px at 10% 20%, rgba(99, 102, 241, 0.05) 0%, transparent 70%),
        radial-gradient(circle 400px at 90% 50%, rgba(139, 92, 246, 0.05) 0%, transparent 70%),
        radial-gradient(circle 250px at 50% 90%, rgba(6, 182, 212, 0.03) 0%, transparent 70%);
}

@keyframes holoShift {
    0%, 100% {
        opacity: 1;
        transform: scale(1) rotate(0deg);
    }
    25% {
        opacity: 0.9;
        transform: scale(1.05) rotate(1deg);
    }
    50% {
        opacity: 1;
        transform: scale(1) rotate(0deg);
    }
    75% {
        opacity: 0.95;
        transform: scale(1.02) rotate(-1deg);
    }
}

@keyframes orbFloat {
    0%, 100% { transform: translateY(0) scale(1); }
    33% { transform: translateY(-20px) scale(1.02); }
    66% { transform: translateY(10px) scale(0.98); }
}

/* Content Wrapper */
.nexus-cms-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;
}

.nexus-cms-content {
    width: 100%;
}

/* ============================================
   PREMIUM SECTION BASE - Glassmorphism Foundation
   ============================================ */

.nexus-section {
    padding: 70px 32px;
    margin: 0 0 32px 0;
    position: relative;
}

/* Premium Section Title with Gradient */
.nexus-section-title {
    text-align: center;
    font-size: 2.2rem;
    font-weight: 800;
    margin: 0 0 16px 0;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary), var(--glass-tertiary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
}

[data-theme="dark"] .nexus-section-title {
    background: linear-gradient(135deg, #a5b4fc, #c4b5fd, #d8b4fe);
    -webkit-background-clip: text;
    background-clip: text;
}

.nexus-section-subtitle {
    text-align: center;
    color: var(--glass-text-muted);
    margin: 0 0 48px 0;
    font-size: 1.1rem;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.7;
}

/* ============================================
   HERO SECTION - Premium Holographic Glass
   ============================================ */

.nexus-hero {
    text-align: center;
    padding: 100px 40px;
    background: var(--glass-bg);
    backdrop-filter: blur(var(--glass-blur-intense));
    -webkit-backdrop-filter: blur(var(--glass-blur-intense));
    border-radius: var(--radius-xl);
    border: 1px solid var(--glass-border);
    box-shadow: var(--glass-shadow);
    position: relative;
    overflow: hidden;
}

/* Holographic border glow */
.nexus-hero::before {
    content: '';
    position: absolute;
    inset: -1px;
    border-radius: var(--radius-xl);
    padding: 2px;
    background: var(--holo-border);
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
    opacity: 0.6;
}

/* Holographic shine effect */
.nexus-hero::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.1) 50%,
        transparent 100%);
    animation: heroShine 8s ease-in-out infinite;
    pointer-events: none;
}

@keyframes heroShine {
    0%, 100% { left: -100%; }
    50% { left: 100%; }
}

[data-theme="dark"] .nexus-hero {
    background: var(--glass-bg);
    border-color: var(--glass-border);
}

[data-theme="dark"] .nexus-hero::before {
    opacity: 0.8;
}

.nexus-hero h1 {
    font-size: 3.2rem;
    font-weight: 800;
    background: linear-gradient(135deg,
        var(--glass-primary) 0%,
        var(--glass-secondary) 40%,
        var(--glass-tertiary) 60%,
        var(--glass-accent) 100%);
    background-size: 200% 200%;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 20px 0;
    letter-spacing: -0.03em;
    line-height: 1.1;
    animation: gradientFlow 6s ease infinite;
    position: relative;
    z-index: 1;
}

@keyframes gradientFlow {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

[data-theme="dark"] .nexus-hero h1 {
    background: linear-gradient(135deg,
        #a5b4fc 0%,
        #c4b5fd 40%,
        #d8b4fe 60%,
        #67e8f9 100%);
    background-size: 200% 200%;
    -webkit-background-clip: text;
    background-clip: text;
    animation: gradientFlow 6s ease infinite;
}

.nexus-hero-subtitle {
    font-size: 1.25rem;
    color: var(--glass-text-secondary);
    max-width: 650px;
    margin: 0 auto 36px;
    line-height: 1.7;
    position: relative;
    z-index: 1;
}

/* ============================================
   TEXT SECTIONS - Premium Glass Containers
   ============================================ */

.nexus-text-section {
    max-width: 800px;
    margin: 0 auto;
}

.nexus-text-section h2 {
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0 0 20px 0;
    letter-spacing: -0.02em;
}

.nexus-text-section p {
    font-size: 1.15rem;
    line-height: 1.85;
    color: var(--glass-text-secondary);
}

.nexus-text-block {
    padding: 40px;
    border-radius: var(--radius-lg);
    background: var(--glass-bg);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border: 1px solid var(--glass-border);
    box-shadow: var(--glass-shadow);
    position: relative;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.nexus-text-block:hover {
    transform: translateY(-4px);
    box-shadow: var(--glass-shadow-hover);
}

/* Subtle gradient accent at top */
.nexus-text-block::before {
    content: '';
    position: absolute;
    top: 0;
    left: 32px;
    right: 32px;
    height: 3px;
    background: linear-gradient(90deg, var(--glass-primary), var(--glass-secondary), var(--glass-tertiary));
    border-radius: 0 0 4px 4px;
    opacity: 0.7;
}

.nexus-text-block h2 {
    font-size: 1.9rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0 0 20px 0;
}

.nexus-text-block p {
    font-size: 1.05rem;
    line-height: 1.75;
    color: var(--glass-text-secondary);
    margin: 0 0 16px 0;
}

.nexus-text-block p:last-child {
    margin-bottom: 0;
}

/* ============================================
   ARTICLE BLOCK - Premium Reading Experience
   ============================================ */

.nexus-article {
    max-width: 800px;
    margin: 50px auto;
    padding: 0 24px;
}

.nexus-article-header {
    text-align: center;
    margin-bottom: 48px;
    padding-bottom: 32px;
    border-bottom: 1px solid rgba(99, 102, 241, 0.1);
    position: relative;
}

/* Decorative gradient line under header */
.nexus-article-header::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 50%;
    transform: translateX(-50%);
    width: 120px;
    height: 3px;
    background: linear-gradient(90deg, var(--glass-primary), var(--glass-secondary));
    border-radius: 2px;
}

.nexus-article-header h1 {
    font-size: 2.8rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary), var(--glass-tertiary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 16px 0;
    letter-spacing: -0.03em;
    line-height: 1.15;
}

.nexus-meta {
    color: var(--glass-text-muted);
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    flex-wrap: wrap;
}

.nexus-article-content {
    font-size: 1.15rem;
    line-height: 1.9;
    color: var(--glass-text-secondary);
}

.nexus-article-content h2 {
    font-size: 1.65rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 40px 0 20px 0;
    padding-left: 16px;
    border-left: 4px solid var(--glass-primary);
}

/* ============================================
   CARD GRID - Premium Glass Cards with Holographic Effects
   ============================================ */

.nexus-cards-section {
    max-width: 1200px;
    margin: 0 auto;
}

.nexus-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 28px;
}

.nexus-card {
    background: var(--glass-bg-intense);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: 32px;
    box-shadow: var(--glass-shadow);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

/* Holographic glow border on hover */
.nexus-card::before {
    content: '';
    position: absolute;
    inset: -1px;
    border-radius: var(--radius-lg);
    padding: 2px;
    background: var(--holo-border);
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.4s ease;
}

/* Inner glow effect */
.nexus-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 60%;
    background: linear-gradient(180deg,
        rgba(255, 255, 255, 0.1) 0%,
        transparent 100%);
    pointer-events: none;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}

.nexus-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: var(--glass-shadow-hover), var(--glass-shadow-glow);
}

.nexus-card:hover::before {
    opacity: 0.8;
}

[data-theme="dark"] .nexus-card {
    background: var(--glass-bg-intense);
    border-color: var(--glass-border);
    box-shadow: var(--glass-shadow);
}

[data-theme="dark"] .nexus-card:hover {
    box-shadow: var(--glass-shadow-hover), var(--glass-shadow-glow);
}

[data-theme="dark"] .nexus-card::after {
    background: linear-gradient(180deg,
        rgba(255, 255, 255, 0.03) 0%,
        transparent 100%);
}

.nexus-card h3 {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0 0 14px 0;
    position: relative;
    z-index: 1;
}

.nexus-card p {
    color: var(--glass-text-secondary);
    line-height: 1.7;
    margin: 0;
    position: relative;
    z-index: 1;
}

/* ============================================
   CTA SECTION - Premium Holographic Glass Panel
   ============================================ */

.nexus-cta {
    text-align: center;
    padding: 80px 40px;
    background: var(--holo-gradient);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border-radius: var(--radius-xl);
    border: 1px solid var(--glass-border);
    position: relative;
    overflow: hidden;
}

/* Animated holographic border */
.nexus-cta::before {
    content: '';
    position: absolute;
    inset: -1px;
    border-radius: var(--radius-xl);
    padding: 2px;
    background: linear-gradient(135deg,
        rgba(99, 102, 241, 0.5) 0%,
        rgba(139, 92, 246, 0.4) 25%,
        rgba(6, 182, 212, 0.3) 50%,
        rgba(168, 85, 247, 0.4) 75%,
        rgba(99, 102, 241, 0.5) 100%);
    background-size: 300% 300%;
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    animation: ctaBorderFlow 8s ease infinite;
    pointer-events: none;
}

/* Floating particles effect */
.nexus-cta::after {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle 2px at 20% 30%, rgba(99, 102, 241, 0.4) 0%, transparent 50%),
        radial-gradient(circle 3px at 70% 20%, rgba(139, 92, 246, 0.3) 0%, transparent 50%),
        radial-gradient(circle 2px at 40% 70%, rgba(6, 182, 212, 0.3) 0%, transparent 50%),
        radial-gradient(circle 3px at 85% 60%, rgba(168, 85, 247, 0.3) 0%, transparent 50%),
        radial-gradient(circle 2px at 10% 80%, rgba(99, 102, 241, 0.25) 0%, transparent 50%);
    animation: ctaParticles 20s ease-in-out infinite;
    pointer-events: none;
}

@keyframes ctaBorderFlow {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

@keyframes ctaParticles {
    0%, 100% { opacity: 0.5; transform: translateY(0); }
    50% { opacity: 0.8; transform: translateY(-10px); }
}

[data-theme="dark"] .nexus-cta {
    background: var(--holo-gradient);
    border-color: var(--glass-border);
}

.nexus-cta h2 {
    font-size: 2.4rem;
    font-weight: 800;
    color: var(--glass-text-primary);
    margin: 0 0 16px 0;
    position: relative;
    z-index: 1;
    letter-spacing: -0.02em;
}

.nexus-cta p {
    color: var(--glass-text-secondary);
    margin: 0 auto 32px;
    max-width: 550px;
    font-size: 1.1rem;
    line-height: 1.7;
    position: relative;
    z-index: 1;
}

/* ============================================
   TWO COLUMN LAYOUTS - Premium Split Sections
   ============================================ */

.nexus-two-col {
    max-width: 1200px;
    margin: 0 auto;
}

.nexus-two-col-inner {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 70px;
    align-items: center;
}

.nexus-two-col-reverse .nexus-two-col-inner {
    direction: rtl;
}

.nexus-two-col-reverse .nexus-two-col-inner > * {
    direction: ltr;
}

.nexus-two-col-content h2 {
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0 0 20px 0;
    letter-spacing: -0.02em;
}

.nexus-two-col-content p {
    font-size: 1.15rem;
    line-height: 1.85;
    color: var(--glass-text-secondary);
    margin: 0 0 28px 0;
}

/* Premium Image Placeholder with Glass Effect */
.nexus-image-placeholder {
    background: linear-gradient(135deg,
        rgba(99, 102, 241, 0.1) 0%,
        rgba(139, 92, 246, 0.15) 50%,
        rgba(168, 85, 247, 0.1) 100%);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: var(--radius-lg);
    min-height: 380px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--glass-primary);
    font-size: 1.1rem;
    font-weight: 500;
    border: 1px solid rgba(99, 102, 241, 0.2);
    position: relative;
    overflow: hidden;
    transition: all 0.4s ease;
}

.nexus-image-placeholder::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg,
        transparent 0%,
        rgba(255, 255, 255, 0.2) 50%,
        transparent 100%);
    opacity: 0;
    transition: opacity 0.4s ease;
}

.nexus-image-placeholder:hover::before {
    opacity: 1;
}

.nexus-image-placeholder-alt {
    background: linear-gradient(135deg,
        rgba(16, 185, 129, 0.1) 0%,
        rgba(6, 182, 212, 0.15) 50%,
        rgba(16, 185, 129, 0.1) 100%);
    border-color: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

[data-theme="dark"] .nexus-image-placeholder {
    background: linear-gradient(135deg,
        rgba(99, 102, 241, 0.15) 0%,
        rgba(139, 92, 246, 0.2) 50%,
        rgba(168, 85, 247, 0.15) 100%);
    border-color: rgba(99, 102, 241, 0.3);
}

/* Premium Check List */
.nexus-check-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nexus-check-list li {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 16px;
    color: var(--glass-text-secondary);
    font-size: 1.05rem;
    line-height: 1.6;
}

.nexus-check-list li::before {
    content: '';
    width: 24px;
    height: 24px;
    min-width: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, #10b981, #06b6d4);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 2px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.nexus-check-list li::after {
    content: '✓';
    position: absolute;
    margin-left: 6px;
    margin-top: 4px;
    color: white;
    font-size: 0.8rem;
    font-weight: 700;
}

/* ============================================
   TESTIMONIALS - Premium Social Proof Cards
   ============================================ */

.nexus-testimonials {
    max-width: 1200px;
    margin: 0 auto;
}

.nexus-testimonial-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 28px;
}

.nexus-testimonial-card {
    background: var(--glass-bg-intense);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: 32px;
    box-shadow: var(--glass-shadow);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

/* Quote mark decoration */
.nexus-testimonial-card::before {
    content: '"';
    position: absolute;
    top: 16px;
    right: 24px;
    font-size: 5rem;
    font-family: Georgia, serif;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    opacity: 0.15;
    line-height: 1;
    pointer-events: none;
}

.nexus-testimonial-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--glass-shadow-hover);
}

[data-theme="dark"] .nexus-testimonial-card {
    background: var(--glass-bg-intense);
    border-color: var(--glass-border);
}

/* Premium Star Rating */
.nexus-stars {
    color: #f59e0b;
    font-size: 1.2rem;
    margin-bottom: 18px;
    display: flex;
    gap: 2px;
    filter: drop-shadow(0 2px 4px rgba(245, 158, 11, 0.3));
}

.nexus-quote {
    color: var(--glass-text-secondary);
    font-style: italic;
    line-height: 1.75;
    margin: 0 0 24px 0;
    font-size: 1.05rem;
    position: relative;
}

.nexus-author {
    display: flex;
    align-items: center;
    gap: 14px;
    padding-top: 20px;
    border-top: 1px solid rgba(99, 102, 241, 0.1);
}

/* Premium Avatar with Gradient Border */
.nexus-avatar {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    padding: 2px;
    position: relative;
}

.nexus-avatar::after {
    content: '';
    position: absolute;
    inset: 2px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
}

.nexus-avatar-green::after { background: linear-gradient(135deg, #dcfce7, #bbf7d0); }
.nexus-avatar-yellow::after { background: linear-gradient(135deg, #fef3c7, #fde68a); }
.nexus-avatar-pink::after { background: linear-gradient(135deg, #fce7f3, #fbcfe8); }

.nexus-author-info strong {
    display: block;
    color: var(--glass-text-primary);
    font-weight: 600;
    font-size: 1rem;
}

.nexus-author-info span {
    color: var(--glass-text-muted);
    font-size: 0.85rem;
}

/* ============================================
   STATS SECTION - Premium Gradient with Glass Overlay
   ============================================ */

.nexus-stats {
    background: linear-gradient(135deg,
        var(--glass-primary) 0%,
        var(--glass-secondary) 50%,
        var(--glass-tertiary) 100%);
    border-radius: var(--radius-xl);
    padding: 80px 40px;
    position: relative;
    overflow: hidden;
}

/* Glass overlay effect */
.nexus-stats::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle 200px at 20% 30%, rgba(255, 255, 255, 0.15) 0%, transparent 50%),
        radial-gradient(circle 300px at 80% 60%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
    pointer-events: none;
}

/* Animated glow */
.nexus-stats::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        to bottom right,
        transparent 40%,
        rgba(255, 255, 255, 0.1) 50%,
        transparent 60%
    );
    animation: statsShine 10s ease-in-out infinite;
    pointer-events: none;
}

@keyframes statsShine {
    0%, 100% { transform: rotate(45deg) translateY(-100%); }
    50% { transform: rotate(45deg) translateY(100%); }
}

.nexus-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 40px;
    max-width: 1000px;
    margin: 0 auto;
    text-align: center;
    position: relative;
    z-index: 1;
}

.nexus-stat-item {
    position: relative;
}

.nexus-stat-number {
    font-size: 3.2rem;
    font-weight: 800;
    color: white;
    margin-bottom: 10px;
    text-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    letter-spacing: -0.02em;
}

.nexus-stat-label {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.05rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* ============================================
   FAQ SECTION - Premium Expandable Cards
   ============================================ */

.nexus-faq {
    max-width: 800px;
    margin: 0 auto;
}

.nexus-faq-list {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.nexus-faq-item {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border-radius: var(--radius-md);
    padding: 28px 32px;
    border: 1px solid var(--glass-border);
    box-shadow: var(--glass-shadow);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

/* Left accent border */
.nexus-faq-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, var(--glass-primary), var(--glass-secondary));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.nexus-faq-item:hover {
    transform: translateX(4px);
    box-shadow: var(--glass-shadow-hover);
}

.nexus-faq-item:hover::before {
    opacity: 1;
}

[data-theme="dark"] .nexus-faq-item {
    background: var(--glass-bg);
    border-color: var(--glass-border);
}

.nexus-faq-item h3 {
    font-size: 1.15rem;
    font-weight: 600;
    color: var(--glass-text-primary);
    margin: 0 0 12px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Question icon */
.nexus-faq-item h3::before {
    content: 'Q';
    width: 28px;
    height: 28px;
    min-width: 28px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
    font-size: 0.85rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nexus-faq-item p {
    color: var(--glass-text-secondary);
    line-height: 1.7;
    margin: 0;
    padding-left: 40px;
}

/* ============================================
   TEAM SECTION - Premium Member Cards
   ============================================ */

.nexus-team {
    max-width: 1000px;
    margin: 0 auto;
}

.nexus-team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 36px;
}

.nexus-team-member {
    text-align: center;
    padding: 32px 24px;
    background: var(--glass-bg);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--glass-shadow);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.nexus-team-member:hover {
    transform: translateY(-8px);
    box-shadow: var(--glass-shadow-hover);
}

[data-theme="dark"] .nexus-team-member {
    background: var(--glass-bg);
    border-color: var(--glass-border);
}

/* Premium Avatar with Holographic Border */
.nexus-team-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    margin: 0 auto 24px;
    padding: 4px;
    background: var(--holo-border);
    background-size: 200% 200%;
    animation: avatarGlow 4s ease infinite;
    position: relative;
}

.nexus-team-avatar::after {
    content: '';
    position: absolute;
    inset: 4px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
}

@keyframes avatarGlow {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.nexus-team-member h3 {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0 0 6px 0;
}

.nexus-team-role {
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0 0 12px 0;
}

.nexus-team-bio {
    color: var(--glass-text-muted);
    font-size: 0.9rem;
    line-height: 1.6;
    margin: 0;
}

/* ============================================
   CONTACT FORM - Premium Glass Form
   ============================================ */

.nexus-contact {
    max-width: 650px;
    margin: 0 auto;
    text-align: center;
    padding: 50px 40px;
    background: var(--glass-bg);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-xl);
    box-shadow: var(--glass-shadow);
}

[data-theme="dark"] .nexus-contact {
    background: var(--glass-bg);
    border-color: var(--glass-border);
}

.nexus-contact-form {
    text-align: left;
    margin-top: 32px;
}

.nexus-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
    margin-bottom: 18px;
}

/* Premium Form Inputs with Glass Effect */
.nexus-input,
.nexus-textarea {
    width: 100%;
    padding: 16px 20px;
    border: 2px solid transparent;
    border-radius: var(--radius-md);
    font-size: 1rem;
    background: var(--glass-bg-intense);
    color: var(--glass-text-primary);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-sizing: border-box;
    position: relative;
}

.nexus-input::placeholder,
.nexus-textarea::placeholder {
    color: var(--glass-text-muted);
}

.nexus-input:hover,
.nexus-textarea:hover {
    border-color: rgba(99, 102, 241, 0.3);
}

.nexus-input:focus,
.nexus-textarea:focus {
    outline: none;
    border-color: var(--glass-primary);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15), var(--glass-shadow);
    background: var(--glass-bg-intense);
}

[data-theme="dark"] .nexus-input,
[data-theme="dark"] .nexus-textarea {
    background: var(--glass-bg-intense);
    color: var(--glass-text-primary);
}

.nexus-textarea {
    resize: vertical;
    min-height: 140px;
    margin-bottom: 20px;
}

/* ============================================
   NEWSLETTER SECTION - Premium Signup Box
   ============================================ */

.nexus-newsletter {
    text-align: center;
    background: var(--holo-gradient);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border-radius: var(--radius-xl);
    padding: 70px 40px;
    border: 1px solid var(--glass-border);
    position: relative;
    overflow: hidden;
}

/* Animated border glow */
.nexus-newsletter::before {
    content: '';
    position: absolute;
    inset: -1px;
    border-radius: var(--radius-xl);
    padding: 2px;
    background: var(--holo-border);
    background-size: 200% 200%;
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    animation: newsletterBorder 6s ease infinite;
    pointer-events: none;
}

@keyframes newsletterBorder {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

[data-theme="dark"] .nexus-newsletter {
    background: var(--holo-gradient);
    border-color: var(--glass-border);
}

.nexus-newsletter h2 {
    font-size: 2rem;
    font-weight: 800;
    color: var(--glass-text-primary);
    margin: 0 0 14px 0;
    position: relative;
    z-index: 1;
}

.nexus-newsletter p {
    color: var(--glass-text-secondary);
    margin: 0 0 32px 0;
    font-size: 1.1rem;
    position: relative;
    z-index: 1;
}

.nexus-newsletter-form {
    display: flex;
    gap: 14px;
    max-width: 480px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

.nexus-newsletter-form .nexus-input {
    flex: 1;
    background: var(--glass-bg-intense);
}

/* ============================================
   VIDEO SECTION - Premium Media Player
   ============================================ */

.nexus-video {
    max-width: 950px;
    margin: 0 auto;
}

.nexus-video-container {
    position: relative;
    padding-bottom: 56.25%;
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--glass-shadow-hover);
}

.nexus-video-placeholder {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg,
        #1e1b4b 0%,
        #312e81 50%,
        #1e1b4b 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
}

/* Premium Play Button */
.nexus-play-btn {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    margin-bottom: 20px;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 30px rgba(99, 102, 241, 0.5);
    position: relative;
}

/* Pulsing ring animation */
.nexus-play-btn::before {
    content: '';
    position: absolute;
    inset: -8px;
    border-radius: 50%;
    border: 2px solid rgba(99, 102, 241, 0.4);
    animation: playPulse 2s ease-out infinite;
}

@keyframes playPulse {
    0% { transform: scale(1); opacity: 1; }
    100% { transform: scale(1.5); opacity: 0; }
}

.nexus-play-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 12px 40px rgba(99, 102, 241, 0.6);
}

.nexus-video-placeholder p {
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
    font-size: 1rem;
}

/* ============================================
   GALLERY SECTION - Premium Image Grid
   ============================================ */

.nexus-gallery {
    max-width: 1200px;
    margin: 0 auto;
}

.nexus-gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
}

.nexus-gallery-item {
    aspect-ratio: 1;
    border-radius: var(--radius-md);
    background: linear-gradient(135deg,
        rgba(99, 102, 241, 0.1) 0%,
        rgba(139, 92, 246, 0.15) 100%);
    position: relative;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--glass-shadow);
}

/* Holographic shine on hover */
.nexus-gallery-item::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg,
        transparent 0%,
        rgba(255, 255, 255, 0.3) 50%,
        transparent 100%);
    opacity: 0;
    transition: opacity 0.4s ease;
}

.nexus-gallery-item:hover {
    transform: scale(1.05);
    box-shadow: var(--glass-shadow-hover);
}

.nexus-gallery-item:hover::before {
    opacity: 1;
}

.nexus-gallery-green {
    background: linear-gradient(135deg,
        rgba(16, 185, 129, 0.15) 0%,
        rgba(6, 182, 212, 0.2) 100%);
}
.nexus-gallery-yellow {
    background: linear-gradient(135deg,
        rgba(245, 158, 11, 0.15) 0%,
        rgba(251, 191, 36, 0.2) 100%);
}
.nexus-gallery-pink {
    background: linear-gradient(135deg,
        rgba(244, 114, 182, 0.15) 0%,
        rgba(236, 72, 153, 0.2) 100%);
}
.nexus-gallery-blue {
    background: linear-gradient(135deg,
        rgba(59, 130, 246, 0.15) 0%,
        rgba(96, 165, 250, 0.2) 100%);
}
.nexus-gallery-purple {
    background: linear-gradient(135deg,
        rgba(168, 85, 247, 0.15) 0%,
        rgba(192, 132, 252, 0.2) 100%);
}

/* ============================================
   BUTTONS - Premium Holographic Effects
   ============================================ */

.nexus-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 16px 32px;
    border-radius: var(--radius-md);
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.nexus-btn-primary {
    background: linear-gradient(135deg,
        var(--glass-primary) 0%,
        var(--glass-secondary) 50%,
        var(--glass-tertiary) 100%);
    background-size: 200% 200%;
    color: white !important;
    box-shadow: 0 6px 24px rgba(99, 102, 241, 0.4);
}

/* Holographic shine sweep */
.nexus-btn-primary::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.3) 50%,
        transparent 100%);
    transition: left 0.6s ease;
}

.nexus-btn-primary:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 12px 36px rgba(99, 102, 241, 0.5),
                0 0 30px rgba(139, 92, 246, 0.3);
    background-position: 100% 50%;
    text-decoration: none;
}

.nexus-btn-primary:hover::before {
    left: 100%;
}

.nexus-btn-primary:active {
    transform: translateY(-1px) scale(0.98);
}

/* Secondary/Ghost Button */
.nexus-btn-secondary {
    background: var(--glass-bg);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border: 2px solid var(--glass-border);
    color: var(--glass-primary) !important;
}

.nexus-btn-secondary:hover {
    border-color: var(--glass-primary);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.15);
    transform: translateY(-2px);
}

.nexus-btn-full {
    width: 100%;
}

/* Icon styling in buttons */
.nexus-btn i,
.nexus-btn svg {
    font-size: 1.1em;
}

/* ============================================
   DIVIDERS & SPACERS - Premium Separators
   ============================================ */

.nexus-divider {
    border: none;
    height: 2px;
    max-width: 250px;
    margin: 50px auto;
    background: linear-gradient(90deg,
        transparent 0%,
        rgba(99, 102, 241, 0.15) 10%,
        rgba(99, 102, 241, 0.4) 30%,
        rgba(139, 92, 246, 0.5) 50%,
        rgba(168, 85, 247, 0.4) 70%,
        rgba(168, 85, 247, 0.15) 90%,
        transparent 100%);
    position: relative;
}

/* Center glow dot */
.nexus-divider::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 8px;
    height: 8px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border-radius: 50%;
    box-shadow: 0 0 16px rgba(99, 102, 241, 0.6);
}

.nexus-spacer {
    height: 70px;
}

/* ============================================
   SMART BLOCK PLACEHOLDERS (Editor View)
   ============================================ */

.nexus-smart-placeholder {
    padding: 40px;
    text-align: center;
    border: 2px dashed rgba(99, 102, 241, 0.4);
    background: var(--holo-gradient);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: var(--radius-lg);
    margin: 24px 0;
    position: relative;
    overflow: hidden;
}

/* Animated dashed border */
.nexus-smart-placeholder::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: var(--radius-lg);
    background:
        linear-gradient(90deg, var(--glass-primary) 50%, transparent 50%) top / 12px 2px repeat-x,
        linear-gradient(90deg, var(--glass-primary) 50%, transparent 50%) bottom / 12px 2px repeat-x,
        linear-gradient(0deg, var(--glass-primary) 50%, transparent 50%) left / 2px 12px repeat-y,
        linear-gradient(0deg, var(--glass-primary) 50%, transparent 50%) right / 2px 12px repeat-y;
    animation: dashMove 20s linear infinite;
    opacity: 0.3;
}

@keyframes dashMove {
    0% { background-position: 0 0, 0 100%, 0 0, 100% 0; }
    100% { background-position: 100% 0, -100% 100%, 0 100%, 100% -100%; }
}

.nexus-placeholder-icon {
    font-size: 3rem;
    margin-bottom: 16px;
    filter: drop-shadow(0 4px 8px rgba(99, 102, 241, 0.3));
}

.nexus-placeholder-title {
    font-weight: 700;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 10px;
    font-size: 1.1rem;
}

.nexus-placeholder-desc {
    color: var(--glass-text-muted);
    font-size: 0.95rem;
}

/* ============================================
   TYPOGRAPHY - Premium Styling
   ============================================ */

.nexus-cms-content h1 {
    font-size: 2.8rem;
    font-weight: 800;
    background: linear-gradient(135deg,
        var(--glass-primary) 0%,
        var(--glass-secondary) 40%,
        var(--glass-tertiary) 80%,
        var(--glass-accent) 100%);
    background-size: 200% 200%;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0 0 20px 0;
    line-height: 1.15;
    letter-spacing: -0.03em;
    animation: gradientFlow 8s ease infinite;
}

.nexus-cms-content h2 {
    font-size: 1.9rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 32px 0 18px 0;
    letter-spacing: -0.02em;
}

.nexus-cms-content h3 {
    font-size: 1.45rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 24px 0 14px 0;
}

.nexus-cms-content h4 {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--glass-text-primary);
    margin: 20px 0 12px 0;
}

.nexus-cms-content p {
    color: var(--glass-text-secondary);
    font-size: 1.1rem;
    line-height: 1.85;
    margin-bottom: 1.2rem;
}

.nexus-cms-content strong {
    color: var(--glass-text-primary);
    font-weight: 600;
}

.nexus-cms-content em {
    font-style: italic;
}

/* Premium Links with Gradient Underline */
.nexus-cms-content a:not(.nexus-btn) {
    color: var(--glass-primary);
    text-decoration: none;
    font-weight: 500;
    position: relative;
    transition: all 0.3s ease;
}

.nexus-cms-content a:not(.nexus-btn)::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -2px;
    width: 0;
    height: 2px;
    background: linear-gradient(90deg, var(--glass-primary), var(--glass-secondary));
    transition: width 0.3s ease;
}

.nexus-cms-content a:not(.nexus-btn):hover {
    color: var(--glass-secondary);
}

.nexus-cms-content a:not(.nexus-btn):hover::after {
    width: 100%;
}

/* Lists */
.nexus-cms-content ul,
.nexus-cms-content ol {
    margin: 0 0 1.5rem 0;
    padding-left: 1.5rem;
    color: var(--glass-text-secondary);
}

.nexus-cms-content li {
    margin-bottom: 0.5rem;
    line-height: 1.7;
}

/* Blockquotes */
.nexus-cms-content blockquote {
    margin: 2rem 0;
    padding: 1.5rem 2rem;
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-left: 4px solid var(--glass-primary);
    border-radius: 0 var(--radius-md) var(--radius-md) 0;
    font-style: italic;
    color: var(--glass-text-secondary);
}

/* Code blocks */
.nexus-cms-content code {
    background: rgba(99, 102, 241, 0.1);
    color: var(--glass-primary);
    padding: 0.2em 0.5em;
    border-radius: 6px;
    font-size: 0.9em;
    font-family: 'SF Mono', Monaco, 'Courier New', monospace;
}

/* ============================================
   IMAGES - Premium Styling
   ============================================ */

.nexus-cms-content img {
    max-width: 100%;
    height: auto;
    border-radius: var(--radius-lg);
    box-shadow: var(--glass-shadow);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.nexus-cms-content img:hover {
    transform: scale(1.02);
    box-shadow: var(--glass-shadow-hover);
}

[data-theme="dark"] .nexus-cms-content img {
    box-shadow: var(--glass-shadow);
}

/* ============================================
   RESPONSIVE - Premium Mobile Experience
   ============================================ */

@media (max-width: 1024px) {
    :root {
        --radius-lg: 24px;
        --radius-xl: 28px;
    }

    .nexus-section {
        padding: 50px 24px;
    }

    .nexus-hero {
        padding: 70px 28px;
    }

    .nexus-hero h1 {
        font-size: 2.6rem;
    }
}

@media (max-width: 900px) {
    .nexus-cms-page {
        padding-top: 120px;
        padding-bottom: 70px;
    }

    .nexus-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .nexus-card-grid {
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    }
}

@media (max-width: 768px) {
    :root {
        --radius-lg: 20px;
        --radius-xl: 24px;
        --glass-blur: 16px;
    }

    .nexus-cms-page {
        padding-top: 100px;
    }

    .nexus-cms-inner {
        padding: 0 16px;
    }

    .nexus-hero {
        padding: 60px 24px;
    }

    .nexus-hero h1 {
        font-size: 2.2rem;
    }

    .nexus-hero-subtitle {
        font-size: 1.1rem;
    }

    .nexus-section {
        padding: 45px 20px;
        margin: 0 0 20px 0;
    }

    .nexus-section-title {
        font-size: 1.8rem;
    }

    .nexus-two-col-inner {
        grid-template-columns: 1fr;
        gap: 36px;
    }

    .nexus-two-col-reverse .nexus-two-col-inner {
        direction: ltr;
    }

    .nexus-two-col-content h2 {
        font-size: 1.8rem;
    }

    .nexus-form-row {
        grid-template-columns: 1fr;
    }

    .nexus-newsletter-form {
        flex-direction: column;
    }

    .nexus-image-placeholder {
        min-height: 280px;
    }

    .nexus-contact {
        padding: 40px 24px;
    }

    .nexus-cta {
        padding: 50px 24px;
    }

    .nexus-testimonial-grid {
        grid-template-columns: 1fr;
    }

    .nexus-team-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .nexus-faq-item h3::before {
        display: none;
    }

    .nexus-faq-item p {
        padding-left: 0;
    }
}

@media (max-width: 480px) {
    :root {
        --radius-md: 16px;
        --radius-lg: 18px;
        --radius-xl: 20px;
    }

    .nexus-cms-page {
        padding-top: 90px;
    }

    .nexus-hero h1 {
        font-size: 1.9rem;
    }

    .nexus-section-title {
        font-size: 1.6rem;
    }

    .nexus-btn {
        width: 100%;
        padding: 16px 24px;
    }

    .nexus-stat-number {
        font-size: 2.2rem;
    }

    .nexus-stats-grid {
        gap: 24px;
    }

    .nexus-team-grid {
        grid-template-columns: 1fr;
    }

    .nexus-team-member {
        padding: 24px 20px;
    }

    .nexus-card {
        padding: 24px;
    }

    .nexus-testimonial-card {
        padding: 24px;
    }

    .nexus-cms-content h1 {
        font-size: 2rem;
    }

    .nexus-cms-content h2 {
        font-size: 1.5rem;
    }

    .nexus-article-header h1 {
        font-size: 2rem;
    }
}

/* ============================================
   DEVICE VISIBILITY RULES
   ============================================ */

/* Mobile-only blocks: hidden on desktop (769px+) */
@media (min-width: 769px) {
    .nexus-mobile-only { display: none !important; }
}

/* Desktop-only blocks: hidden on mobile (768px-) */
@media (max-width: 768px) {
    .nexus-desktop-only { display: none !important; }
}

/* ============================================
   MOBILE-NATIVE BLOCKS
   ============================================ */

/* Navigation Drawer */
.nexus-nav-drawer {
    position: fixed;
    top: 0;
    left: -300px;
    width: 280px;
    height: 100vh;
    background: var(--glass-bg-intense);
    backdrop-filter: blur(var(--glass-blur-intense));
    -webkit-backdrop-filter: blur(var(--glass-blur-intense));
    border-right: 1px solid var(--glass-border);
    box-shadow: var(--glass-shadow-hover);
    z-index: 9999;
    transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow-y: auto;
}

.nexus-nav-drawer.open {
    left: 0;
}

.nexus-nav-drawer-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 9998;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.nexus-nav-drawer-overlay.open {
    opacity: 1;
    visibility: visible;
}

.nexus-nav-drawer-header {
    padding: 24px;
    border-bottom: 1px solid var(--glass-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.nexus-nav-drawer-close {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.nexus-nav-drawer-close:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.nexus-nav-drawer-nav {
    padding: 16px 0;
}

.nexus-nav-drawer-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 24px;
    color: var(--glass-text-primary);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.nexus-nav-drawer-item:hover,
.nexus-nav-drawer-item.active {
    background: var(--holo-gradient);
    border-left-color: var(--glass-primary);
    color: var(--glass-primary);
}

.nexus-nav-drawer-item i {
    width: 24px;
    text-align: center;
    font-size: 1.1rem;
}

/* Bottom Sheet */
.nexus-bottom-sheet {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--glass-bg-intense);
    backdrop-filter: blur(var(--glass-blur-intense));
    -webkit-backdrop-filter: blur(var(--glass-blur-intense));
    border-radius: var(--radius-xl) var(--radius-xl) 0 0;
    border: 1px solid var(--glass-border);
    border-bottom: none;
    box-shadow: 0 -8px 40px rgba(0, 0, 0, 0.15);
    z-index: 9999;
    transform: translateY(100%);
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    max-height: 85vh;
    overflow-y: auto;
}

.nexus-bottom-sheet.open {
    transform: translateY(0);
}

.nexus-bottom-sheet-handle {
    width: 40px;
    height: 5px;
    background: var(--glass-border);
    border-radius: 3px;
    margin: 12px auto 20px;
}

.nexus-bottom-sheet-content {
    padding: 0 24px 32px;
}

.nexus-bottom-sheet-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0 0 16px 0;
    text-align: center;
}

/* Tab Bar (Mobile Bottom Navigation) */
.nexus-tab-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 70px;
    background: var(--glass-bg-intense);
    backdrop-filter: blur(var(--glass-blur-intense));
    -webkit-backdrop-filter: blur(var(--glass-blur-intense));
    border-top: 1px solid var(--glass-border);
    display: flex;
    align-items: center;
    justify-content: space-around;
    z-index: 9990;
    padding-bottom: env(safe-area-inset-bottom, 0);
}

.nexus-tab-bar-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 8px 16px;
    color: var(--glass-text-muted);
    text-decoration: none;
    font-size: 0.75rem;
    font-weight: 500;
    transition: all 0.3s ease;
    position: relative;
}

.nexus-tab-bar-item i {
    font-size: 1.4rem;
    transition: transform 0.3s ease;
}

.nexus-tab-bar-item.active,
.nexus-tab-bar-item:hover {
    color: var(--glass-primary);
}

.nexus-tab-bar-item.active i {
    transform: scale(1.1);
}

.nexus-tab-bar-item.active::before {
    content: '';
    position: absolute;
    top: -1px;
    left: 50%;
    transform: translateX(-50%);
    width: 24px;
    height: 3px;
    background: linear-gradient(90deg, var(--glass-primary), var(--glass-secondary));
    border-radius: 0 0 3px 3px;
}

/* Floating Action Button (FAB) */
.nexus-fab {
    position: fixed;
    bottom: 90px;
    right: 24px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
    cursor: pointer;
    z-index: 9985;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
}

.nexus-fab:hover {
    transform: scale(1.1) rotate(90deg);
    box-shadow: 0 12px 32px rgba(99, 102, 241, 0.5);
}

.nexus-fab:active {
    transform: scale(0.95);
}

/* FAB Speed Dial */
.nexus-fab-container {
    position: fixed;
    bottom: 90px;
    right: 24px;
    z-index: 9985;
}

.nexus-fab-actions {
    position: absolute;
    bottom: 70px;
    right: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px);
    transition: all 0.3s ease;
}

.nexus-fab-container.open .nexus-fab-actions {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.nexus-fab-action {
    display: flex;
    align-items: center;
    gap: 12px;
    justify-content: flex-end;
}

.nexus-fab-action-label {
    background: var(--glass-bg-intense);
    backdrop-filter: blur(10px);
    padding: 8px 16px;
    border-radius: var(--radius-sm);
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--glass-text-primary);
    box-shadow: var(--glass-shadow);
    white-space: nowrap;
}

.nexus-fab-action-btn {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--glass-bg-intense);
    border: 1px solid var(--glass-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--glass-primary);
    font-size: 1.1rem;
    box-shadow: var(--glass-shadow);
    cursor: pointer;
    transition: all 0.3s ease;
}

.nexus-fab-action-btn:hover {
    background: var(--glass-primary);
    color: white;
}

/* Stories Rail (Horizontal Scrolling Avatars) */
.nexus-stories-rail {
    display: flex;
    gap: 16px;
    padding: 16px 24px;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.nexus-stories-rail::-webkit-scrollbar {
    display: none;
}

.nexus-story-item {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    scroll-snap-align: start;
}

.nexus-story-avatar {
    width: 68px;
    height: 68px;
    border-radius: 50%;
    padding: 3px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary), var(--glass-tertiary), var(--glass-accent));
    background-size: 200% 200%;
    animation: storyRing 3s ease infinite;
}

@keyframes storyRing {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.nexus-story-avatar-inner {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    border: 3px solid var(--glass-bg-intense);
}

.nexus-story-avatar.viewed {
    background: var(--glass-border);
    animation: none;
}

.nexus-story-name {
    font-size: 0.75rem;
    color: var(--glass-text-secondary);
    max-width: 70px;
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Pull to Refresh Zone */
.nexus-pull-refresh {
    position: relative;
    overflow: hidden;
}

.nexus-pull-indicator {
    position: absolute;
    top: -60px;
    left: 50%;
    transform: translateX(-50%);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--glass-bg-intense);
    border: 1px solid var(--glass-border);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--glass-shadow);
    transition: top 0.3s ease;
}

.nexus-pull-refresh.pulling .nexus-pull-indicator {
    top: 20px;
}

.nexus-pull-indicator i {
    color: var(--glass-primary);
    transition: transform 0.3s ease;
}

.nexus-pull-refresh.refreshing .nexus-pull-indicator i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Swipe Cards (Tinder Style) */
.nexus-swipe-cards {
    position: relative;
    width: 100%;
    max-width: 340px;
    height: 420px;
    margin: 0 auto;
    perspective: 1000px;
}

.nexus-swipe-card {
    position: absolute;
    width: 100%;
    height: 100%;
    background: var(--glass-bg-intense);
    backdrop-filter: blur(var(--glass-blur));
    border-radius: var(--radius-xl);
    border: 1px solid var(--glass-border);
    box-shadow: var(--glass-shadow-hover);
    overflow: hidden;
    transition: transform 0.3s ease, opacity 0.3s ease;
    cursor: grab;
}

.nexus-swipe-card:active {
    cursor: grabbing;
}

.nexus-swipe-card:nth-child(1) {
    z-index: 3;
    transform: scale(1) translateY(0);
}

.nexus-swipe-card:nth-child(2) {
    z-index: 2;
    transform: scale(0.95) translateY(12px);
}

.nexus-swipe-card:nth-child(3) {
    z-index: 1;
    transform: scale(0.9) translateY(24px);
}

.nexus-swipe-card-image {
    height: 65%;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.15));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
}

.nexus-swipe-card-content {
    padding: 20px;
}

.nexus-swipe-card-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0 0 8px 0;
}

.nexus-swipe-card-desc {
    color: var(--glass-text-secondary);
    font-size: 0.95rem;
    line-height: 1.5;
    margin: 0;
}

.nexus-swipe-actions {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 24px;
}

.nexus-swipe-btn {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid;
}

.nexus-swipe-btn-nope {
    background: rgba(239, 68, 68, 0.1);
    border-color: #ef4444;
    color: #ef4444;
}

.nexus-swipe-btn-like {
    background: rgba(34, 197, 94, 0.1);
    border-color: #22c55e;
    color: #22c55e;
}

.nexus-swipe-btn:hover {
    transform: scale(1.1);
}

.nexus-swipe-btn-nope:hover {
    background: #ef4444;
    color: white;
}

.nexus-swipe-btn-like:hover {
    background: #22c55e;
    color: white;
}

/* Full Screen Overlay (Mobile Modal) */
.nexus-fullscreen-overlay {
    position: fixed;
    inset: 0;
    background: var(--glass-bg-intense);
    backdrop-filter: blur(var(--glass-blur-intense));
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    overflow-y: auto;
}

.nexus-fullscreen-overlay.open {
    opacity: 1;
    visibility: visible;
}

.nexus-fullscreen-header {
    position: sticky;
    top: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    background: var(--glass-bg);
    backdrop-filter: blur(var(--glass-blur));
    border-bottom: 1px solid var(--glass-border);
    z-index: 1;
}

.nexus-fullscreen-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--glass-text-primary);
    margin: 0;
}

.nexus-fullscreen-close {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.nexus-fullscreen-close:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.nexus-fullscreen-content {
    padding: 24px 20px;
}

/* ============================================
   DESKTOP-NATIVE BLOCKS
   ============================================ */

/* Sidebar Rail (Persistent Vertical Nav) */
.nexus-sidebar-rail {
    width: 260px;
    min-height: calc(100vh - 100px);
    background: var(--glass-bg);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border-right: 1px solid var(--glass-border);
    padding: 24px 0;
    position: sticky;
    top: 100px;
}

.nexus-sidebar-section {
    padding: 0 16px;
    margin-bottom: 24px;
}

.nexus-sidebar-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--glass-text-muted);
    padding: 0 12px;
    margin-bottom: 12px;
}

.nexus-sidebar-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: var(--radius-sm);
    color: var(--glass-text-secondary);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.nexus-sidebar-item:hover {
    background: var(--holo-gradient);
    color: var(--glass-text-primary);
}

.nexus-sidebar-item.active {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1));
    color: var(--glass-primary);
}

.nexus-sidebar-item i {
    width: 20px;
    text-align: center;
}

.nexus-sidebar-item-badge {
    margin-left: auto;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
}

/* Three Column Grid Layout */
.nexus-three-col {
    display: grid;
    grid-template-columns: 260px 1fr 300px;
    gap: 0;
    max-width: 1600px;
    margin: 0 auto;
}

.nexus-three-col-left {
    border-right: 1px solid var(--glass-border);
}

.nexus-three-col-center {
    padding: 32px;
    min-height: calc(100vh - 100px);
}

.nexus-three-col-right {
    border-left: 1px solid var(--glass-border);
    padding: 24px;
    background: var(--glass-bg);
}

@media (max-width: 1200px) {
    .nexus-three-col {
        grid-template-columns: 1fr 300px;
    }
    .nexus-three-col-left {
        display: none;
    }
}

@media (max-width: 900px) {
    .nexus-three-col {
        grid-template-columns: 1fr;
    }
    .nexus-three-col-right {
        display: none;
    }
}

/* Mega Menu (Multi-column Dropdown) */
.nexus-mega-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--glass-bg-intense);
    backdrop-filter: blur(var(--glass-blur-intense));
    -webkit-backdrop-filter: blur(var(--glass-blur-intense));
    border: 1px solid var(--glass-border);
    border-top: none;
    box-shadow: var(--glass-shadow-hover);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 9999;
}

.nexus-mega-menu.open {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.nexus-mega-menu-inner {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 32px;
    padding: 32px 48px;
    max-width: 1200px;
    margin: 0 auto;
}

.nexus-mega-menu-column h4 {
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--glass-primary);
    margin: 0 0 16px 0;
    padding-bottom: 12px;
    border-bottom: 2px solid rgba(99, 102, 241, 0.2);
}

.nexus-mega-menu-item {
    display: block;
    padding: 10px 0;
    color: var(--glass-text-secondary);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.nexus-mega-menu-item:hover {
    color: var(--glass-primary);
    transform: translateX(4px);
}

.nexus-mega-menu-featured {
    background: var(--holo-gradient);
    border-radius: var(--radius-lg);
    padding: 24px;
}

.nexus-mega-menu-featured h4 {
    border-bottom: none;
    padding-bottom: 0;
}

/* Hover Cards (Desktop Preview Popups) */
.nexus-hover-card-trigger {
    position: relative;
    cursor: pointer;
}

.nexus-hover-card {
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%) translateY(10px);
    width: 320px;
    background: var(--glass-bg-intense);
    backdrop-filter: blur(var(--glass-blur-intense));
    -webkit-backdrop-filter: blur(var(--glass-blur-intense));
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--glass-shadow-hover);
    padding: 20px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 9999;
}

.nexus-hover-card::before {
    content: '';
    position: absolute;
    top: -8px;
    left: 50%;
    transform: translateX(-50%) rotate(45deg);
    width: 16px;
    height: 16px;
    background: var(--glass-bg-intense);
    border-left: 1px solid var(--glass-border);
    border-top: 1px solid var(--glass-border);
}

.nexus-hover-card-trigger:hover .nexus-hover-card {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(16px);
}

.nexus-hover-card-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 16px;
}

.nexus-hover-card-avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    padding: 2px;
}

.nexus-hover-card-avatar-inner {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
}

.nexus-hover-card-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0 0 4px 0;
}

.nexus-hover-card-role {
    font-size: 0.85rem;
    color: var(--glass-text-muted);
    margin: 0;
}

.nexus-hover-card-bio {
    font-size: 0.9rem;
    color: var(--glass-text-secondary);
    line-height: 1.6;
    margin: 0 0 16px 0;
}

.nexus-hover-card-stats {
    display: flex;
    gap: 24px;
}

.nexus-hover-card-stat {
    text-align: center;
}

.nexus-hover-card-stat-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--glass-text-primary);
}

.nexus-hover-card-stat-label {
    font-size: 0.75rem;
    color: var(--glass-text-muted);
}

/* Toast Notifications (Corner Positioned) */
.nexus-toast-container {
    position: fixed;
    top: 100px;
    right: 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    z-index: 10001;
    pointer-events: none;
}

.nexus-toast {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 20px;
    background: var(--glass-bg-intense);
    backdrop-filter: blur(var(--glass-blur));
    -webkit-backdrop-filter: blur(var(--glass-blur));
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-md);
    box-shadow: var(--glass-shadow-hover);
    min-width: 320px;
    max-width: 400px;
    pointer-events: auto;
    animation: toastSlideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes toastSlideIn {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.nexus-toast.exiting {
    animation: toastSlideOut 0.3s ease forwards;
}

@keyframes toastSlideOut {
    to {
        opacity: 0;
        transform: translateX(100%);
    }
}

.nexus-toast-icon {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.85rem;
}

.nexus-toast-success .nexus-toast-icon {
    background: rgba(34, 197, 94, 0.15);
    color: #22c55e;
}

.nexus-toast-error .nexus-toast-icon {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.nexus-toast-info .nexus-toast-icon {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
}

.nexus-toast-warning .nexus-toast-icon {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.nexus-toast-content {
    flex: 1;
}

.nexus-toast-title {
    font-weight: 600;
    color: var(--glass-text-primary);
    margin: 0 0 4px 0;
    font-size: 0.95rem;
}

.nexus-toast-message {
    color: var(--glass-text-secondary);
    font-size: 0.85rem;
    margin: 0;
    line-height: 1.5;
}

.nexus-toast-close {
    background: none;
    border: none;
    color: var(--glass-text-muted);
    cursor: pointer;
    padding: 4px;
    margin: -4px -4px -4px 0;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.nexus-toast-close:hover {
    background: var(--glass-bg);
    color: var(--glass-text-primary);
}

.nexus-toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--glass-primary), var(--glass-secondary));
    border-radius: 0 0 var(--radius-md) var(--radius-md);
    animation: toastProgress 5s linear forwards;
}

@keyframes toastProgress {
    from { width: 100%; }
    to { width: 0; }
}

/* ============================================
   WORLD-CLASS PREMIUM BLOCK STYLES
   Best-in-class animations and micro-interactions
   ============================================ */

/* ========== PREMIUM TRIGGER BUTTON ========== */
.nexus-premium-trigger-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 24px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
    border: none;
    border-radius: 16px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.3);
}

.nexus-premium-trigger-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(99, 102, 241, 0.4);
}

.nexus-premium-trigger-btn:active {
    transform: scale(0.98);
}

.nexus-trigger-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

/* ========== PREMIUM NAV DRAWER ========== */
.nexus-drawer-scrim {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0);
    backdrop-filter: blur(0);
    z-index: 998;
    pointer-events: none;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.nexus-drawer-scrim.open {
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    pointer-events: auto;
}

.nexus-premium-drawer {
    position: fixed;
    top: 0;
    left: 0;
    width: 320px;
    max-width: 85vw;
    height: 100vh;
    background: var(--glass-bg);
    border-right: 1px solid var(--glass-border);
    z-index: 999;
    transform: translateX(-100%);
    transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.nexus-premium-drawer.open {
    transform: translateX(0);
}

.nexus-drawer-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.5;
    pointer-events: none;
}

.nexus-drawer-orb-1 {
    width: 200px;
    height: 200px;
    background: var(--glass-primary);
    top: -50px;
    right: -50px;
}

.nexus-drawer-orb-2 {
    width: 150px;
    height: 150px;
    background: var(--glass-secondary);
    bottom: 100px;
    left: -50px;
}

.nexus-drawer-header-premium {
    padding: 24px;
    border-bottom: 1px solid var(--glass-border);
}

.nexus-drawer-user-card {
    display: flex;
    align-items: center;
    gap: 14px;
}

.nexus-drawer-avatar-ring {
    position: relative;
}

.nexus-drawer-avatar {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.1rem;
    position: relative;
    overflow: hidden;
}

.nexus-drawer-avatar-glow {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, transparent 40%, rgba(255,255,255,0.2) 50%, transparent 60%);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.nexus-drawer-status-dot {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 14px;
    height: 14px;
    background: #22c55e;
    border-radius: 50%;
    border: 3px solid var(--glass-bg);
}

.nexus-drawer-user-info {
    flex: 1;
    min-width: 0;
}

.nexus-drawer-user-name {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--glass-text-primary);
}

.nexus-drawer-user-tier {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 4px;
}

.nexus-tier-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
    color: #000;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 20px;
}

.nexus-tier-xp {
    font-size: 0.75rem;
    color: var(--glass-text-muted);
}

.nexus-drawer-close-premium {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    color: var(--glass-text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.nexus-drawer-close-premium:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border-color: #ef4444;
}

.nexus-drawer-quick-stats {
    display: flex;
    justify-content: space-around;
    padding: 16px 0;
    margin-top: 16px;
    background: var(--holo-gradient);
    border-radius: 12px;
}

.nexus-quick-stat {
    text-align: center;
}

.nexus-stat-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--glass-text-primary);
}

.nexus-stat-label {
    font-size: 0.7rem;
    color: var(--glass-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.nexus-quick-stat-divider {
    width: 1px;
    background: var(--glass-border);
}

.nexus-drawer-scroll-area {
    flex: 1;
    overflow-y: auto;
    padding: 16px 0;
}

.nexus-drawer-section {
    padding: 0 16px;
    margin-bottom: 8px;
}

.nexus-drawer-section-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 8px 8px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--glass-text-muted);
}

.nexus-section-line {
    flex: 1;
    height: 1px;
    background: var(--glass-border);
}

.nexus-drawer-nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 12px;
    color: var(--glass-text-secondary);
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    margin-bottom: 2px;
}

.nexus-drawer-nav-item:hover {
    background: var(--holo-gradient);
    color: var(--glass-text-primary);
}

.nexus-drawer-nav-item.active {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(139, 92, 246, 0.1));
    color: var(--glass-primary);
}

.nexus-nav-icon-wrap {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--holo-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.nexus-drawer-nav-item.active .nexus-nav-icon-wrap {
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
}

.nexus-nav-label {
    flex: 1;
    font-weight: 500;
}

.nexus-nav-active-indicator {
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 24px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border-radius: 0 4px 4px 0;
}

.nexus-nav-tag {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 20px;
    text-transform: uppercase;
}

.nexus-tag-new {
    background: linear-gradient(135deg, #22c55e, #10b981);
    color: white;
}

.nexus-nav-counter {
    min-width: 22px;
    height: 22px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--glass-text-muted);
}

.nexus-counter-urgent {
    background: #ef4444;
    border-color: #ef4444;
    color: white;
}

.nexus-drawer-promo-card {
    margin: 16px;
    padding: 16px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.15));
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.nexus-drawer-promo-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.2);
}

.nexus-promo-icon {
    font-size: 1.5rem;
}

.nexus-promo-content {
    flex: 1;
}

.nexus-promo-title {
    font-weight: 600;
    color: var(--glass-text-primary);
    font-size: 0.95rem;
}

.nexus-promo-desc {
    font-size: 0.8rem;
    color: var(--glass-text-muted);
}

.nexus-promo-arrow {
    color: var(--glass-primary);
}

.nexus-drawer-footer-premium {
    padding: 16px 24px;
    border-top: 1px solid var(--glass-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.nexus-drawer-signout {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--glass-text-muted);
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.3s ease;
}

.nexus-drawer-signout:hover {
    color: #ef4444;
}

.nexus-drawer-version {
    font-size: 0.75rem;
    color: var(--glass-text-muted);
}

/* ========== PREMIUM BOTTOM SHEET ========== */
.nexus-sheet-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0);
    z-index: 998;
    pointer-events: none;
    transition: background 0.4s ease;
}

.nexus-sheet-backdrop.open {
    background: rgba(0, 0, 0, 0.5);
    pointer-events: auto;
}

.nexus-premium-sheet {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--glass-bg);
    border-radius: 24px 24px 0 0;
    z-index: 999;
    transform: translateY(100%);
    transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    max-height: 90vh;
    overflow-y: auto;
}

.nexus-premium-sheet.open {
    transform: translateY(0);
}

.nexus-sheet-drag-zone {
    padding: 12px 0 8px;
    display: flex;
    justify-content: center;
}

.nexus-sheet-handle-bar {
    width: 40px;
    height: 5px;
    background: var(--glass-border);
    border-radius: 3px;
}

.nexus-sheet-header {
    padding: 0 24px 16px;
}

.nexus-sheet-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.nexus-sheet-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0;
}

.nexus-sheet-close-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--holo-gradient);
    border: none;
    color: var(--glass-text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nexus-sheet-subtitle {
    color: var(--glass-text-muted);
    font-size: 0.9rem;
    margin: 4px 0 0;
}

.nexus-sheet-preview-card {
    margin: 0 24px 20px;
    padding: 12px;
    background: var(--holo-gradient);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.nexus-preview-image {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    background: var(--glass-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--glass-text-muted);
}

.nexus-preview-title {
    font-weight: 600;
    color: var(--glass-text-primary);
    font-size: 0.9rem;
}

.nexus-preview-url {
    font-size: 0.8rem;
    color: var(--glass-text-muted);
}

.nexus-sheet-section {
    padding: 0 24px 20px;
}

.nexus-sheet-section-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--glass-text-muted);
    margin-bottom: 12px;
}

.nexus-share-scroll-container {
    overflow-x: auto;
    margin: 0 -24px;
    padding: 0 24px;
}

.nexus-share-grid-premium {
    display: flex;
    gap: 16px;
    padding-bottom: 8px;
}

.nexus-share-item-premium {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    min-width: 70px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
}

.nexus-share-item-premium:active {
    transform: scale(0.95);
}

.nexus-share-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.4rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.nexus-share-item-premium:hover .nexus-share-icon {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

.nexus-share-item-premium span {
    font-size: 0.75rem;
    color: var(--glass-text-muted);
    font-weight: 500;
}

.nexus-copy-link-box {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--holo-gradient);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
}

.nexus-copy-link-box i {
    color: var(--glass-text-muted);
}

.nexus-copy-input {
    flex: 1;
    background: none;
    border: none;
    outline: none;
    color: var(--glass-text-primary);
    font-size: 0.9rem;
}

.nexus-copy-btn {
    padding: 8px 16px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.nexus-copy-btn:active {
    transform: scale(0.95);
}

.nexus-copy-done {
    display: none;
}

.nexus-sheet-actions-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.nexus-sheet-action-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 16px 8px;
    background: var(--holo-gradient);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.nexus-sheet-action-item:hover {
    background: var(--glass-bg);
    border-color: var(--glass-primary);
}

.nexus-action-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--glass-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--glass-text-primary);
}

.nexus-sheet-action-item span {
    font-size: 0.75rem;
    color: var(--glass-text-muted);
    font-weight: 500;
}

.nexus-action-danger .nexus-action-icon {
    color: #ef4444;
}

.nexus-sheet-footer {
    padding: 16px 24px;
    padding-bottom: calc(16px + env(safe-area-inset-bottom));
}

.nexus-sheet-cancel-btn {
    width: 100%;
    padding: 16px;
    background: var(--holo-gradient);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    color: var(--glass-text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
}

.nexus-sheet-cancel-btn:active {
    transform: scale(0.98);
}

/* ========== PREMIUM TAB BAR ========== */
.nexus-premium-tab-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: space-around;
    padding: 0 8px;
    padding-bottom: env(safe-area-inset-bottom);
    z-index: 100;
}

.nexus-tab-bar-bg {
    position: absolute;
    inset: 0;
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border-top: 1px solid var(--glass-border);
}

.nexus-premium-tab {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 8px 16px;
    color: var(--glass-text-muted);
    text-decoration: none;
    z-index: 1;
    transition: color 0.3s ease;
}

.nexus-premium-tab.active {
    color: var(--glass-primary);
}

.nexus-tab-icon-container {
    position: relative;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nexus-tab-icon {
    font-size: 1.3rem;
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.nexus-tab-icon-filled {
    position: absolute;
    font-size: 1.3rem;
    opacity: 0;
    transform: scale(0.8);
}

.nexus-premium-tab.active .nexus-tab-icon {
    opacity: 0;
    transform: scale(0.8);
}

.nexus-premium-tab.active .nexus-tab-icon-filled {
    opacity: 1;
    transform: scale(1);
}

.nexus-tab-label {
    font-size: 0.7rem;
    font-weight: 500;
}

.nexus-tab-center {
    margin-top: -20px;
}

.nexus-tab-center-btn {
    position: relative;
    width: 60px;
    height: 60px;
}

.nexus-center-btn-inner {
    width: 100%;
    height: 100%;
    border-radius: 20px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    position: relative;
    z-index: 2;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.nexus-tab-center:hover .nexus-center-btn-inner {
    transform: scale(1.05);
    box-shadow: 0 8px 30px rgba(99, 102, 241, 0.5);
}

.nexus-center-btn-glow {
    position: absolute;
    inset: -4px;
    border-radius: 24px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    opacity: 0.3;
    filter: blur(12px);
}

.nexus-center-btn-pulse {
    position: absolute;
    inset: 0;
    border-radius: 20px;
    border: 2px solid var(--glass-primary);
    animation: tabPulse 2s infinite;
}

@keyframes tabPulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.15); opacity: 0; }
}

.nexus-tab-notification {
    position: absolute;
    top: -2px;
    right: -8px;
}

.nexus-notif-count {
    min-width: 18px;
    height: 18px;
    background: #ef4444;
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
}

.nexus-notif-ping {
    position: absolute;
    inset: 0;
    background: #ef4444;
    border-radius: 9px;
    animation: notifPing 1.5s infinite;
}

@keyframes notifPing {
    0% { transform: scale(1); opacity: 0.8; }
    100% { transform: scale(2); opacity: 0; }
}

.nexus-tab-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.65rem;
    font-weight: 600;
    position: relative;
}

.nexus-avatar-ring {
    position: absolute;
    inset: -3px;
    border-radius: 50%;
    border: 2px solid transparent;
    transition: border-color 0.3s ease;
}

.nexus-premium-tab.active .nexus-avatar-ring {
    border-color: var(--glass-primary);
}

.nexus-tab-status-dot {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 10px;
    height: 10px;
    background: #22c55e;
    border-radius: 50%;
    border: 2px solid var(--glass-bg);
}

/* ========== PREMIUM FAB ========== */
.nexus-premium-fab-container {
    position: fixed;
    bottom: 100px;
    right: 24px;
    z-index: 100;
}

.nexus-fab-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0);
    backdrop-filter: blur(0);
    pointer-events: none;
    transition: all 0.4s ease;
    z-index: -1;
}

.nexus-premium-fab-container.open .nexus-fab-backdrop {
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(8px);
    pointer-events: auto;
}

.nexus-fab-menu {
    position: absolute;
    bottom: 72px;
    right: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: flex-end;
}

.nexus-fab-item {
    display: flex;
    align-items: center;
    gap: 12px;
    opacity: 0;
    transform: translateY(20px) scale(0.8);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.nexus-premium-fab-container.open .nexus-fab-item {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.nexus-premium-fab-container.open .nexus-fab-item[data-index="0"] { transition-delay: 0.05s; }
.nexus-premium-fab-container.open .nexus-fab-item[data-index="1"] { transition-delay: 0.1s; }
.nexus-premium-fab-container.open .nexus-fab-item[data-index="2"] { transition-delay: 0.15s; }
.nexus-premium-fab-container.open .nexus-fab-item[data-index="3"] { transition-delay: 0.2s; }
.nexus-premium-fab-container.open .nexus-fab-item[data-index="4"] { transition-delay: 0.25s; }
.nexus-premium-fab-container.open .nexus-fab-item[data-index="5"] { transition-delay: 0.3s; }

.nexus-fab-tooltip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.nexus-tooltip-text {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--glass-text-primary);
    white-space: nowrap;
}

.nexus-tooltip-kbd {
    font-size: 0.7rem;
    padding: 2px 6px;
    background: var(--holo-gradient);
    border: 1px solid var(--glass-border);
    border-radius: 4px;
    color: var(--glass-text-muted);
    font-family: monospace;
}

.nexus-fab-action-btn-premium {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    background: var(--fab-color);
    border: none;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 20px var(--fab-glow);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.nexus-fab-action-btn-premium:hover {
    transform: scale(1.1);
    box-shadow: 0 8px 30px var(--fab-glow);
}

.nexus-fab-main {
    width: 64px;
    height: 64px;
    border-radius: 20px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border: none;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(99, 102, 241, 0.4);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.nexus-fab-main:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 32px rgba(99, 102, 241, 0.5);
}

.nexus-fab-icon {
    position: relative;
    z-index: 2;
}

.nexus-fab-plus,
.nexus-fab-close {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 1.5rem;
    color: white;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.nexus-fab-close {
    opacity: 0;
    transform: translate(-50%, -50%) rotate(-90deg);
}

.nexus-premium-fab-container.open .nexus-fab-plus {
    opacity: 0;
    transform: translate(-50%, -50%) rotate(90deg);
}

.nexus-premium-fab-container.open .nexus-fab-close {
    opacity: 1;
    transform: translate(-50%, -50%) rotate(0);
}

.nexus-fab-glow {
    position: absolute;
    inset: -10px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    opacity: 0.4;
    filter: blur(20px);
    z-index: 0;
}

/* ========== PREMIUM STORIES RAIL ========== */
.nexus-premium-stories {
    padding: 16px 0;
    background: var(--glass-bg);
    border-bottom: 1px solid var(--glass-border);
}

.nexus-stories-scroll {
    display: flex;
    gap: 16px;
    padding: 0 16px;
    overflow-x: auto;
    scrollbar-width: none;
}

.nexus-stories-scroll::-webkit-scrollbar {
    display: none;
}

.nexus-story-premium {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    cursor: pointer;
}

.nexus-story-ring-container {
    position: relative;
    width: 72px;
    height: 72px;
}

.nexus-story-avatar-premium {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.1rem;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    border: 3px solid var(--glass-bg);
}

.nexus-ring-svg {
    position: absolute;
    inset: 0;
    transform: rotate(-90deg);
}

.nexus-ring-svg circle {
    fill: none;
    stroke-width: 3;
    stroke-linecap: round;
}

.nexus-ring-progress {
    stroke: url(#storyGradient);
    stroke: var(--glass-primary);
}

.nexus-ring-gradient .nexus-ring-progress {
    stroke: url(#storyGradient);
    animation: ringPulse 2s ease-in-out infinite;
}

@keyframes ringPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.nexus-segment-viewed {
    stroke: var(--glass-text-muted);
    opacity: 0.3;
}

.nexus-segment-new {
    stroke: var(--glass-primary);
}

.nexus-ring-viewed {
    border: 2px solid var(--glass-border);
    border-radius: 50%;
}

.nexus-ring-close-friends .nexus-ring-close {
    stroke: #22c55e;
}

.nexus-story-add-ring {
    border: 2px dashed var(--glass-border);
    border-radius: 50%;
}

.nexus-story-add-icon {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    border: 2px solid var(--glass-bg);
}

.nexus-live-pulse-ring {
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 2px solid #ef4444;
    animation: livePulse 1.5s ease-out infinite;
}

@keyframes livePulse {
    0% { transform: scale(1); opacity: 0.8; }
    100% { transform: scale(1.2); opacity: 0; }
}

.nexus-live-badge-premium {
    position: absolute;
    bottom: -2px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.6rem;
    font-weight: 800;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.nexus-live-dot {
    width: 6px;
    height: 6px;
    background: white;
    border-radius: 50%;
    animation: blink 1s infinite;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

.nexus-story-username {
    font-size: 0.75rem;
    color: var(--glass-text-muted);
    max-width: 72px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-align: center;
}

.nexus-story-new-badge {
    position: absolute;
    top: 0;
    right: -4px;
    font-size: 0.6rem;
    background: var(--glass-primary);
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: 600;
}

.nexus-close-friends-star {
    position: absolute;
    bottom: 22px;
    right: -2px;
    font-size: 1rem;
    color: #22c55e;
}

/* ========== MORE PREMIUM STYLES CONTINUE ========== */
/* Bottom Sheet Enhanced Styles - Legacy support */
.nexus-share-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.nexus-share-btn:active {
    transform: scale(0.95);
}

.nexus-share-btn span {
    font-size: 0.75rem;
    color: var(--glass-text-muted);
}

.nexus-bottom-sheet-divider {
    height: 1px;
    background: var(--glass-border);
    margin: 0 -24px;
}

.nexus-bottom-sheet-actions {
    display: flex;
    flex-direction: column;
}

.nexus-bottom-sheet-action {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 0;
    color: var(--glass-text-primary);
    text-decoration: none;
    font-weight: 500;
    border-bottom: 1px solid var(--glass-border);
    transition: opacity 0.2s ease;
}

.nexus-bottom-sheet-action:last-child {
    border-bottom: none;
}

.nexus-bottom-sheet-action i {
    width: 24px;
    text-align: center;
    color: var(--glass-text-muted);
}

.nexus-bottom-sheet-action:active {
    opacity: 0.7;
}

/* Tab Bar Enhanced Styles */
.nexus-tab-bar-center {
    margin-top: -20px;
}

.nexus-tab-bar-center-btn {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
}

.nexus-tab-badge {
    position: absolute;
    top: 4px;
    right: 50%;
    transform: translateX(14px);
    background: #ef4444;
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    min-width: 16px;
    height: 16px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
}

.nexus-tab-badge-dot {
    width: 8px;
    height: 8px;
    min-width: 8px;
    padding: 0;
    background: #22c55e;
}

/* Stories Rail Enhanced Styles */
.nexus-story-avatar-add {
    background: var(--glass-border) !important;
    animation: none !important;
}

.nexus-story-live {
    position: relative;
}

.nexus-story-live-badge {
    position: absolute;
    bottom: -4px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #ef4444, #f97316);
    color: white;
    font-size: 0.55rem;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 4px;
    letter-spacing: 0.05em;
}

/* Swipe Cards Enhanced Styles */
.nexus-swipe-card-tags {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.nexus-swipe-tag {
    background: var(--holo-gradient);
    color: var(--glass-text-secondary);
    font-size: 0.7rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
}

.nexus-swipe-card-meta {
    display: flex;
    gap: 16px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--glass-border);
    font-size: 0.85rem;
    color: var(--glass-text-muted);
}

.nexus-swipe-card-meta i {
    margin-right: 6px;
}

.nexus-swipe-btn-rewind {
    background: rgba(249, 115, 22, 0.1);
    border-color: #f97316;
    color: #f97316;
    width: 44px;
    height: 44px;
    font-size: 1rem;
}

.nexus-swipe-btn-rewind:hover {
    background: #f97316;
    color: white;
}

.nexus-swipe-btn-super {
    background: rgba(59, 130, 246, 0.1);
    border-color: #3b82f6;
    color: #3b82f6;
}

.nexus-swipe-btn-super:hover {
    background: #3b82f6;
    color: white;
}

.nexus-swipe-btn-boost {
    background: rgba(168, 85, 247, 0.1);
    border-color: #a855f7;
    color: #a855f7;
    width: 44px;
    height: 44px;
    font-size: 1rem;
}

.nexus-swipe-btn-boost:hover {
    background: #a855f7;
    color: white;
}

/* Fullscreen Overlay Enhanced Styles */
.nexus-fullscreen-back,
.nexus-fullscreen-action {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--glass-text-primary);
    transition: all 0.3s ease;
}

.nexus-fullscreen-back:hover,
.nexus-fullscreen-action:hover {
    background: var(--holo-gradient);
}

/* ============================================
   ENHANCED DESKTOP BLOCK STYLES
   ============================================ */

/* Sidebar Rail Enhanced Styles */
.nexus-sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 20px 24px;
    border-bottom: 1px solid var(--glass-border);
    margin-bottom: 20px;
}

.nexus-sidebar-search {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    margin: 0 16px 20px;
    background: var(--holo-gradient);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-md);
}

.nexus-sidebar-search i {
    color: var(--glass-text-muted);
}

.nexus-sidebar-divider {
    height: 1px;
    background: var(--glass-border);
    margin: 16px 12px;
}

.nexus-sidebar-item-new {
    margin-left: auto;
    background: linear-gradient(135deg, #22c55e, #10b981);
    color: white;
    font-size: 0.6rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 10px;
    text-transform: uppercase;
}

.nexus-sidebar-item-badge-alert {
    background: #ef4444 !important;
}

.nexus-sidebar-user {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    margin-top: auto;
    border-top: 1px solid var(--glass-border);
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--glass-bg);
}

/* Mega Menu Enhanced Styles */
.nexus-mega-menu-item-rich {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px;
    margin: 0 -12px;
    border-radius: var(--radius-md);
    color: var(--glass-text-primary);
    text-decoration: none;
    transition: all 0.3s ease;
}

.nexus-mega-menu-item-rich:hover {
    background: var(--holo-gradient);
}

.nexus-mega-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.1rem;
}

.nexus-mega-menu-item-rich strong {
    display: block;
    margin-bottom: 2px;
    font-weight: 600;
}

.nexus-mega-menu-item-rich span {
    display: block;
    font-size: 0.8rem;
    color: var(--glass-text-muted);
    line-height: 1.4;
}

/* ============================================
   SMART BLOCKS - Dynamic Content Styles
   ============================================ */
<?php
if (class_exists('Nexus\\Core\\SmartBlockRenderer')) {
    echo SmartBlockRenderer::getStyles();
}
?>

/* ============================================
   FALLBACK FOR NO BACKDROP-FILTER
   ============================================ */
@supports not (backdrop-filter: blur(10px)) {
    .nexus-card,
    .nexus-testimonial-card {
        background: rgba(255, 255, 255, 0.95);
    }

    [data-theme="dark"] .nexus-card,
    [data-theme="dark"] .nexus-testimonial-card {
        background: rgba(15, 23, 42, 0.95);
    }
}

/* ============================================
   🌌 GALACTIC-TIER BLOCKS - COMMAND PALETTE
   Raycast/Spotlight/VSCode Command Center
   ============================================ */
.nexus-command-palette-demo {
    padding: 40px;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 200px;
}

.nexus-cmd-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 40px;
    padding: 12px 16px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    min-width: 320px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.nexus-cmd-trigger:hover {
    border-color: var(--glass-primary);
    box-shadow: 0 8px 32px rgba(139, 92, 246, 0.2),
                0 0 0 1px var(--glass-primary);
    transform: translateY(-2px);
}

.nexus-cmd-trigger-content {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--glass-text-muted);
    font-size: 0.9rem;
}

.nexus-cmd-trigger-content i {
    font-size: 1rem;
    opacity: 0.7;
}

.nexus-kbd-combo {
    display: flex;
    gap: 4px;
}

.nexus-kbd-combo span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    padding: 0 6px;
    background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
    border: 1px solid var(--glass-border);
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--glass-text-muted);
    font-family: system-ui, -apple-system, sans-serif;
}

/* Command Palette Modal */
.nexus-cmd-palette {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 15vh;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.nexus-cmd-palette.open {
    opacity: 1;
    visibility: visible;
}

.nexus-cmd-container {
    width: 100%;
    max-width: 640px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.1) inset;
    transform: scale(0.95) translateY(-20px);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

.nexus-cmd-palette.open .nexus-cmd-container {
    transform: scale(1) translateY(0);
}

.nexus-cmd-search-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--glass-border);
}

.nexus-cmd-search-icon {
    color: var(--glass-primary);
    font-size: 1.2rem;
}

.nexus-cmd-search {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    font-size: 1.1rem;
    color: var(--glass-text-primary);
}

.nexus-cmd-search::placeholder {
    color: var(--glass-text-muted);
}

.nexus-cmd-close {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: var(--glass-text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
}

.nexus-cmd-close:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.5);
    color: #ef4444;
}

.nexus-cmd-results {
    max-height: 400px;
    overflow-y: auto;
    padding: 8px;
}

.nexus-cmd-section {
    margin-bottom: 8px;
}

.nexus-cmd-section-title {
    padding: 8px 12px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--glass-text-muted);
}

.nexus-cmd-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.nexus-cmd-item:hover,
.nexus-cmd-item.active {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(6, 182, 212, 0.1));
}

.nexus-cmd-item-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border-radius: 8px;
    color: white;
    font-size: 0.9rem;
}

.nexus-cmd-item-content {
    flex: 1;
}

.nexus-cmd-item-title {
    font-weight: 600;
    color: var(--glass-text-primary);
    font-size: 0.95rem;
}

.nexus-cmd-item-desc {
    font-size: 0.8rem;
    color: var(--glass-text-muted);
    margin-top: 2px;
}

.nexus-cmd-item-kbd {
    display: flex;
    gap: 4px;
}

.nexus-cmd-item-kbd span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--glass-text-muted);
}

.nexus-cmd-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    border-top: 1px solid var(--glass-border);
    background: rgba(0, 0, 0, 0.1);
}

.nexus-cmd-hints {
    display: flex;
    gap: 16px;
}

.nexus-cmd-hint {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    color: var(--glass-text-muted);
}

.nexus-cmd-hint kbd {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 600;
}

.nexus-cmd-brand {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    color: var(--glass-text-muted);
}

.nexus-cmd-brand i {
    color: var(--glass-primary);
}

/* ============================================
   🌌 GALACTIC-TIER BLOCKS - NOTIFICATION CENTER
   iOS/macOS Style Notifications
   ============================================ */
.nexus-notification-demo {
    padding: 40px;
    display: flex;
    justify-content: center;
    min-height: 200px;
}

.nexus-notif-trigger {
    position: relative;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1.3rem;
    color: var(--glass-text-primary);
}

.nexus-notif-trigger:hover {
    border-color: var(--glass-primary);
    transform: scale(1.05);
}

.nexus-notif-trigger-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    min-width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 6px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 700;
    color: white;
    animation: badgePulse 2s ease-in-out infinite;
}

@keyframes badgePulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.nexus-notif-panel {
    position: fixed;
    top: 80px;
    right: 20px;
    width: 380px;
    max-height: calc(100vh - 120px);
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
    opacity: 0;
    visibility: hidden;
    transform: translateX(20px) scale(0.95);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 9998;
    overflow: hidden;
}

.nexus-notif-panel.open {
    opacity: 1;
    visibility: visible;
    transform: translateX(0) scale(1);
}

.nexus-notif-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    border-bottom: 1px solid var(--glass-border);
}

.nexus-notif-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.nexus-notif-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0;
}

.nexus-notif-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    padding: 0 8px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 700;
    color: white;
}

.nexus-notif-mark-all {
    padding: 8px 14px;
    background: transparent;
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--glass-primary);
    cursor: pointer;
    transition: all 0.2s ease;
}

.nexus-notif-mark-all:hover {
    background: rgba(139, 92, 246, 0.1);
    border-color: var(--glass-primary);
}

.nexus-notif-tabs {
    display: flex;
    padding: 0 20px;
    border-bottom: 1px solid var(--glass-border);
}

.nexus-notif-tab {
    padding: 14px 20px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--glass-text-muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
    margin-bottom: -1px;
}

.nexus-notif-tab:hover {
    color: var(--glass-text-primary);
}

.nexus-notif-tab.active {
    color: var(--glass-primary);
    border-bottom-color: var(--glass-primary);
}

.nexus-notif-list {
    max-height: 400px;
    overflow-y: auto;
    padding: 12px;
}

.nexus-notif-group-title {
    padding: 12px 8px 8px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--glass-text-muted);
}

.nexus-notif-item {
    display: flex;
    gap: 14px;
    padding: 14px;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.nexus-notif-item:hover {
    background: rgba(139, 92, 246, 0.08);
}

.nexus-notif-item.unread::before {
    content: '';
    position: absolute;
    left: 4px;
    top: 50%;
    transform: translateY(-50%);
    width: 6px;
    height: 6px;
    background: var(--glass-primary);
    border-radius: 50%;
}

.nexus-notif-avatar {
    position: relative;
    flex-shrink: 0;
}

.nexus-notif-avatar img {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    object-fit: cover;
}

.nexus-notif-type-badge {
    position: absolute;
    bottom: -4px;
    right: -4px;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 0.6rem;
    color: white;
    border: 2px solid var(--glass-bg);
}

.nexus-notif-type-badge.like { background: #ef4444; }
.nexus-notif-type-badge.comment { background: #3b82f6; }
.nexus-notif-type-badge.follow { background: #8b5cf6; }
.nexus-notif-type-badge.mention { background: #22c55e; }
.nexus-notif-type-badge.system { background: #f59e0b; }

.nexus-notif-content {
    flex: 1;
    min-width: 0;
}

.nexus-notif-text {
    font-size: 0.9rem;
    color: var(--glass-text-primary);
    line-height: 1.5;
}

.nexus-notif-text strong {
    font-weight: 700;
}

.nexus-notif-time {
    font-size: 0.75rem;
    color: var(--glass-text-muted);
    margin-top: 4px;
}

.nexus-notif-preview {
    margin-top: 10px;
    padding: 10px 12px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 10px;
    border-left: 3px solid var(--glass-primary);
    font-size: 0.85rem;
    color: var(--glass-text-muted);
    font-style: italic;
}

.nexus-notif-actions {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}

.nexus-notif-action-btn {
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.nexus-notif-action-btn.primary {
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border: none;
    color: white;
}

.nexus-notif-action-btn.secondary {
    background: transparent;
    border: 1px solid var(--glass-border);
    color: var(--glass-text-primary);
}

.nexus-notif-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--glass-border);
    text-align: center;
}

.nexus-notif-view-all {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--glass-primary);
    text-decoration: none;
    transition: opacity 0.2s ease;
}

.nexus-notif-view-all:hover {
    opacity: 0.8;
}

/* ============================================
   🌌 GALACTIC-TIER BLOCKS - MEDIA PLAYER
   Spotify/Apple Music Style
   ============================================ */
.nexus-media-player-demo {
    padding: 40px;
    display: flex;
    justify-content: center;
}

.nexus-player {
    width: 100%;
    max-width: 420px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    position: relative;
    overflow: hidden;
}

.nexus-player::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at 30% 30%, rgba(139, 92, 246, 0.1), transparent 50%),
                radial-gradient(circle at 70% 70%, rgba(6, 182, 212, 0.1), transparent 50%);
    animation: playerAmbient 10s ease-in-out infinite;
    pointer-events: none;
}

@keyframes playerAmbient {
    0%, 100% { transform: rotate(0deg); }
    50% { transform: rotate(180deg); }
}

.nexus-player-album {
    position: relative;
    z-index: 1;
    margin-bottom: 24px;
}

.nexus-player-cover-wrap {
    position: relative;
    width: 100%;
    aspect-ratio: 1;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
}

.nexus-player-cover {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.nexus-player.playing .nexus-player-cover {
    transform: scale(1.05);
}

.nexus-player-vinyl {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 90%;
    height: 90%;
    transform: translate(-50%, -50%);
    border-radius: 50%;
    background: linear-gradient(135deg, #1a1a2e, #0f0f1a);
    opacity: 0;
    transition: all 0.5s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nexus-player-vinyl::before {
    content: '';
    width: 30%;
    height: 30%;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
}

.nexus-player.playing .nexus-player-vinyl {
    opacity: 0.3;
    animation: vinylSpin 3s linear infinite;
}

@keyframes vinylSpin {
    from { transform: translate(-50%, -50%) rotate(0deg); }
    to { transform: translate(-50%, -50%) rotate(360deg); }
}

.nexus-player-like {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(10px);
    border: none;
    border-radius: 50%;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.nexus-player-like:hover {
    transform: scale(1.1);
}

.nexus-player-like.liked {
    color: #ef4444;
}

.nexus-player-like.liked i::before {
    content: "\f004";
    font-weight: 900;
}

.nexus-player-info {
    position: relative;
    z-index: 1;
    text-align: center;
    margin-bottom: 20px;
}

.nexus-player-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin-bottom: 4px;
}

.nexus-player-artist {
    font-size: 1rem;
    color: var(--glass-text-muted);
}

.nexus-player-progress {
    position: relative;
    z-index: 1;
    margin-bottom: 20px;
}

.nexus-player-bar-wrap {
    position: relative;
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    overflow: hidden;
    cursor: pointer;
}

.nexus-player-bar {
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 35%;
    background: linear-gradient(90deg, var(--glass-primary), var(--glass-secondary));
    border-radius: 3px;
    transition: width 0.1s linear;
}

.nexus-player-bar-handle {
    position: absolute;
    right: -6px;
    top: 50%;
    transform: translateY(-50%);
    width: 12px;
    height: 12px;
    background: white;
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    opacity: 0;
    transition: opacity 0.2s ease;
}

.nexus-player-bar-wrap:hover .nexus-player-bar-handle {
    opacity: 1;
}

.nexus-player-times {
    display: flex;
    justify-content: space-between;
    margin-top: 8px;
    font-size: 0.75rem;
    color: var(--glass-text-muted);
}

.nexus-player-controls {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    margin-bottom: 20px;
}

.nexus-player-btn {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: var(--glass-text-primary);
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.2s ease;
    border-radius: 50%;
}

.nexus-player-btn:hover {
    color: var(--glass-primary);
    transform: scale(1.1);
}

.nexus-player-btn.active {
    color: var(--glass-primary);
}

.nexus-player-btn-main {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
    font-size: 1.5rem;
    border-radius: 50%;
    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.4);
}

.nexus-player-btn-main:hover {
    color: white;
    transform: scale(1.08);
    box-shadow: 0 12px 32px rgba(139, 92, 246, 0.5);
}

.nexus-player-extras {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 16px;
    border-top: 1px solid var(--glass-border);
}

.nexus-player-extra-left,
.nexus-player-extra-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.nexus-player-extra-btn {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: var(--glass-text-muted);
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    border-radius: 8px;
}

.nexus-player-extra-btn:hover {
    color: var(--glass-primary);
    background: rgba(139, 92, 246, 0.1);
}

.nexus-player-volume {
    display: flex;
    align-items: center;
    gap: 8px;
}

.nexus-player-volume-bar {
    width: 80px;
    height: 4px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
    overflow: hidden;
    cursor: pointer;
}

.nexus-player-volume-level {
    height: 100%;
    width: 70%;
    background: var(--glass-text-muted);
    border-radius: 2px;
    transition: background 0.2s ease;
}

.nexus-player-volume:hover .nexus-player-volume-level {
    background: var(--glass-primary);
}

/* ============================================
   🌌 GALACTIC-TIER BLOCKS - CHAT WIDGET
   Intercom/Crisp/Drift Style
   ============================================ */
.nexus-chat-demo {
    padding: 40px;
    display: flex;
    justify-content: flex-end;
    min-height: 100px;
}

.nexus-chat-trigger {
    position: relative;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border: none;
    border-radius: 50%;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 8px 32px rgba(139, 92, 246, 0.4);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.nexus-chat-trigger::before {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    opacity: 0.3;
    animation: chatPulse 2s ease-out infinite;
}

@keyframes chatPulse {
    0% { transform: scale(1); opacity: 0.3; }
    100% { transform: scale(1.4); opacity: 0; }
}

.nexus-chat-trigger:hover {
    transform: scale(1.1);
}

.nexus-chat-trigger-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ef4444;
    border: 2px solid white;
    border-radius: 50%;
    font-size: 0.65rem;
    font-weight: 700;
}

.nexus-chat-widget {
    position: fixed;
    bottom: 100px;
    right: 20px;
    width: 380px;
    height: 520px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px) scale(0.95);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 9998;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.nexus-chat-widget.open {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

.nexus-chat-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 20px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
}

.nexus-chat-header-avatar {
    position: relative;
}

.nexus-chat-header-avatar img {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.nexus-chat-header-status {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 14px;
    height: 14px;
    background: #22c55e;
    border: 2px solid white;
    border-radius: 50%;
}

.nexus-chat-header-info {
    flex: 1;
}

.nexus-chat-header-name {
    font-size: 1.1rem;
    font-weight: 700;
}

.nexus-chat-header-tagline {
    font-size: 0.85rem;
    opacity: 0.9;
}

.nexus-chat-header-close {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.2);
    border: none;
    border-radius: 50%;
    color: white;
    cursor: pointer;
    transition: background 0.2s ease;
}

.nexus-chat-header-close:hover {
    background: rgba(255, 255, 255, 0.3);
}

.nexus-chat-messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.nexus-chat-message {
    display: flex;
    gap: 10px;
    max-width: 85%;
    animation: messageSlide 0.3s ease-out;
}

@keyframes messageSlide {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.nexus-chat-message.sent {
    align-self: flex-end;
    flex-direction: row-reverse;
}

.nexus-chat-msg-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}

.nexus-chat-msg-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.nexus-chat-msg-bubble {
    padding: 12px 16px;
    border-radius: 18px;
    font-size: 0.9rem;
    line-height: 1.5;
}

.nexus-chat-message:not(.sent) .nexus-chat-msg-bubble {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    color: var(--glass-text-primary);
    border-bottom-left-radius: 4px;
}

.nexus-chat-message.sent .nexus-chat-msg-bubble {
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
    border-bottom-right-radius: 4px;
}

.nexus-chat-msg-time {
    font-size: 0.7rem;
    color: var(--glass-text-muted);
    padding: 0 8px;
}

.nexus-chat-message.sent .nexus-chat-msg-time {
    text-align: right;
}

.nexus-chat-typing {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 18px;
    border-bottom-left-radius: 4px;
    max-width: 80px;
}

.nexus-chat-typing-dots {
    display: flex;
    gap: 4px;
}

.nexus-chat-typing-dots span {
    width: 6px;
    height: 6px;
    background: var(--glass-text-muted);
    border-radius: 50%;
    animation: typingBounce 1.4s ease-in-out infinite;
}

.nexus-chat-typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.nexus-chat-typing-dots span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typingBounce {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-4px); }
}

.nexus-chat-input-wrap {
    padding: 16px 20px;
    border-top: 1px solid var(--glass-border);
    background: rgba(0, 0, 0, 0.1);
}

.nexus-chat-input-container {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 24px;
    transition: border-color 0.2s ease;
}

.nexus-chat-input-container:focus-within {
    border-color: var(--glass-primary);
}

.nexus-chat-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    font-size: 0.9rem;
    color: var(--glass-text-primary);
}

.nexus-chat-input::placeholder {
    color: var(--glass-text-muted);
}

.nexus-chat-input-actions {
    display: flex;
    gap: 8px;
}

.nexus-chat-input-btn {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: var(--glass-text-muted);
    cursor: pointer;
    transition: color 0.2s ease;
    border-radius: 50%;
}

.nexus-chat-input-btn:hover {
    color: var(--glass-primary);
}

.nexus-chat-input-btn.send {
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
}

.nexus-chat-input-btn.send:hover {
    transform: scale(1.05);
}

.nexus-chat-footer {
    padding: 10px;
    text-align: center;
    font-size: 0.7rem;
    color: var(--glass-text-muted);
    border-top: 1px solid var(--glass-border);
}

.nexus-chat-footer a {
    color: var(--glass-primary);
    text-decoration: none;
}

/* ============================================
   🌌 GALACTIC-TIER BLOCKS - PRICING CARDS
   Stripe/Linear/Vercel Style
   ============================================ */
.nexus-pricing-demo {
    padding: 40px 20px;
}

.nexus-pricing-header {
    text-align: center;
    margin-bottom: 48px;
}

.nexus-pricing-header h2 {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--glass-text-primary);
    margin-bottom: 12px;
}

.nexus-pricing-header p {
    font-size: 1.1rem;
    color: var(--glass-text-muted);
}

.nexus-pricing-toggle {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    margin-top: 24px;
    padding: 6px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 30px;
}

.nexus-pricing-toggle-opt {
    padding: 10px 20px;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--glass-text-muted);
    cursor: pointer;
    border-radius: 24px;
    transition: all 0.3s ease;
}

.nexus-pricing-toggle-opt.active {
    background: var(--glass-bg);
    color: var(--glass-text-primary);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.nexus-pricing-save {
    display: inline-block;
    padding: 4px 10px;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    border-radius: 12px;
    margin-left: 8px;
}

.nexus-pricing-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    max-width: 1200px;
    margin: 0 auto;
}

.nexus-pricing-card {
    position: relative;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 24px;
    padding: 32px;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.nexus-pricing-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.nexus-pricing-card.popular {
    border-color: var(--glass-primary);
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(6, 182, 212, 0.05));
}

.nexus-pricing-card.popular::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--glass-primary), var(--glass-secondary));
    border-radius: 24px 24px 0 0;
}

.nexus-pricing-badge {
    position: absolute;
    top: -12px;
    left: 50%;
    transform: translateX(-50%);
    padding: 6px 16px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.nexus-pricing-plan-icon {
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(6, 182, 212, 0.1));
    border-radius: 16px;
    font-size: 1.5rem;
    margin-bottom: 20px;
}

.nexus-pricing-plan-name {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin-bottom: 8px;
}

.nexus-pricing-plan-desc {
    font-size: 0.9rem;
    color: var(--glass-text-muted);
    margin-bottom: 24px;
}

.nexus-pricing-price {
    margin-bottom: 24px;
}

.nexus-pricing-amount {
    font-size: 3rem;
    font-weight: 800;
    color: var(--glass-text-primary);
    line-height: 1;
}

.nexus-pricing-amount sup {
    font-size: 1.2rem;
    vertical-align: super;
}

.nexus-pricing-period {
    font-size: 1rem;
    color: var(--glass-text-muted);
}

.nexus-pricing-cta {
    display: block;
    width: 100%;
    padding: 14px 24px;
    font-size: 1rem;
    font-weight: 700;
    text-align: center;
    text-decoration: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 24px;
}

.nexus-pricing-card:not(.popular) .nexus-pricing-cta {
    background: transparent;
    border: 2px solid var(--glass-border);
    color: var(--glass-text-primary);
}

.nexus-pricing-card:not(.popular) .nexus-pricing-cta:hover {
    border-color: var(--glass-primary);
    color: var(--glass-primary);
}

.nexus-pricing-card.popular .nexus-pricing-cta {
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border: none;
    color: white;
    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.3);
}

.nexus-pricing-card.popular .nexus-pricing-cta:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(139, 92, 246, 0.4);
}

.nexus-pricing-features {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nexus-pricing-feature {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    font-size: 0.9rem;
    color: var(--glass-text-primary);
    border-bottom: 1px solid var(--glass-border);
}

.nexus-pricing-feature:last-child {
    border-bottom: none;
}

.nexus-pricing-feature i {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(22, 163, 74, 0.1));
    color: #22c55e;
    border-radius: 50%;
    font-size: 0.65rem;
}

.nexus-pricing-feature.disabled {
    color: var(--glass-text-muted);
    opacity: 0.5;
}

.nexus-pricing-feature.disabled i {
    background: rgba(255, 255, 255, 0.05);
    color: var(--glass-text-muted);
}

/* ============================================
   🌌 GALACTIC-TIER BLOCKS - TIMELINE FEED
   GitHub/Linear/Notion Style Activity Feed
   ============================================ */
.nexus-timeline-demo {
    padding: 40px 20px;
    max-width: 700px;
    margin: 0 auto;
}

.nexus-timeline-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 32px;
}

.nexus-timeline-header h3 {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0;
}

.nexus-timeline-filter {
    display: flex;
    gap: 8px;
}

.nexus-timeline-filter-btn {
    padding: 8px 16px;
    background: transparent;
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--glass-text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
}

.nexus-timeline-filter-btn:hover,
.nexus-timeline-filter-btn.active {
    background: rgba(139, 92, 246, 0.1);
    border-color: var(--glass-primary);
    color: var(--glass-primary);
}

.nexus-timeline-list {
    position: relative;
}

.nexus-timeline-list::before {
    content: '';
    position: absolute;
    left: 23px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(180deg, var(--glass-primary), var(--glass-secondary), transparent);
}

.nexus-timeline-item {
    position: relative;
    display: flex;
    gap: 20px;
    padding-bottom: 24px;
    animation: timelineSlide 0.4s ease-out;
}

@keyframes timelineSlide {
    from { opacity: 0; transform: translateX(-20px); }
    to { opacity: 1; transform: translateX(0); }
}

.nexus-timeline-icon {
    position: relative;
    z-index: 1;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--glass-bg);
    border: 2px solid var(--glass-border);
    border-radius: 50%;
    font-size: 1rem;
    flex-shrink: 0;
}

.nexus-timeline-icon.commit { color: #22c55e; border-color: #22c55e; }
.nexus-timeline-icon.pr { color: #8b5cf6; border-color: #8b5cf6; }
.nexus-timeline-icon.issue { color: #f59e0b; border-color: #f59e0b; }
.nexus-timeline-icon.deploy { color: #3b82f6; border-color: #3b82f6; }
.nexus-timeline-icon.comment { color: #06b6d4; border-color: #06b6d4; }
.nexus-timeline-icon.release { color: #ec4899; border-color: #ec4899; }

.nexus-timeline-content {
    flex: 1;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 20px;
    transition: all 0.3s ease;
}

.nexus-timeline-content:hover {
    border-color: var(--glass-primary);
    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.1);
}

.nexus-timeline-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.nexus-timeline-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
}

.nexus-timeline-author {
    font-weight: 600;
    color: var(--glass-text-primary);
    font-size: 0.9rem;
}

.nexus-timeline-action {
    font-size: 0.85rem;
    color: var(--glass-text-muted);
}

.nexus-timeline-time {
    margin-left: auto;
    font-size: 0.8rem;
    color: var(--glass-text-muted);
}

.nexus-timeline-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--glass-text-primary);
    margin-bottom: 8px;
}

.nexus-timeline-desc {
    font-size: 0.9rem;
    color: var(--glass-text-muted);
    line-height: 1.6;
    margin-bottom: 12px;
}

.nexus-timeline-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.nexus-timeline-tag {
    padding: 4px 10px;
    background: rgba(139, 92, 246, 0.1);
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--glass-primary);
}

.nexus-timeline-tag.success { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
.nexus-timeline-tag.warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.nexus-timeline-tag.info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }

.nexus-timeline-code {
    margin-top: 12px;
    padding: 12px 16px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 10px;
    font-family: 'SF Mono', Monaco, Consolas, monospace;
    font-size: 0.8rem;
    color: var(--glass-text-muted);
    overflow-x: auto;
}

.nexus-timeline-load-more {
    display: block;
    width: 100%;
    margin-top: 24px;
    padding: 14px;
    background: transparent;
    border: 1px dashed var(--glass-border);
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--glass-text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
}

.nexus-timeline-load-more:hover {
    border-color: var(--glass-primary);
    color: var(--glass-primary);
    background: rgba(139, 92, 246, 0.05);
}

/* ============================================
   🌌 GALACTIC-TIER BLOCKS - TESTIMONIAL CAROUSEL
   Premium Animated Slider
   ============================================ */
.nexus-testimonial-demo {
    padding: 60px 20px;
    overflow: hidden;
}

.nexus-testimonial-header {
    text-align: center;
    margin-bottom: 48px;
}

.nexus-testimonial-header h2 {
    font-size: 2rem;
    font-weight: 800;
    color: var(--glass-text-primary);
    margin-bottom: 12px;
}

.nexus-testimonial-header p {
    font-size: 1.1rem;
    color: var(--glass-text-muted);
}

.nexus-testimonial-slider {
    position: relative;
    max-width: 900px;
    margin: 0 auto;
}

.nexus-testimonial-track {
    display: flex;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.nexus-testimonial-slide {
    flex: 0 0 100%;
    padding: 0 20px;
}

.nexus-testimonial-card-premium {
    position: relative;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 24px;
    padding: 40px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
}

.nexus-testimonial-card-premium::before {
    content: '"';
    position: absolute;
    top: 20px;
    left: 30px;
    font-size: 6rem;
    font-family: Georgia, serif;
    color: var(--glass-primary);
    opacity: 0.15;
    line-height: 1;
}

.nexus-testimonial-stars {
    display: flex;
    justify-content: center;
    gap: 4px;
    margin-bottom: 24px;
}

.nexus-testimonial-stars i {
    color: #fbbf24;
    font-size: 1.2rem;
}

.nexus-testimonial-quote {
    font-size: 1.3rem;
    font-weight: 500;
    color: var(--glass-text-primary);
    line-height: 1.8;
    margin-bottom: 32px;
    font-style: italic;
}

.nexus-testimonial-author-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
}

.nexus-testimonial-author-avatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--glass-primary);
    padding: 2px;
}

.nexus-testimonial-author-info {
    text-align: left;
}

.nexus-testimonial-author-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--glass-text-primary);
}

.nexus-testimonial-author-title {
    font-size: 0.9rem;
    color: var(--glass-text-muted);
}

.nexus-testimonial-company {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 4px;
    font-size: 0.85rem;
    color: var(--glass-primary);
}

.nexus-testimonial-nav {
    display: flex;
    justify-content: center;
    gap: 16px;
    margin-top: 32px;
}

.nexus-testimonial-nav-btn {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 50%;
    color: var(--glass-text-primary);
    cursor: pointer;
    transition: all 0.3s ease;
}

.nexus-testimonial-nav-btn:hover {
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border-color: transparent;
    color: white;
    transform: scale(1.05);
}

.nexus-testimonial-dots {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 24px;
}

.nexus-testimonial-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--glass-border);
    cursor: pointer;
    transition: all 0.3s ease;
}

.nexus-testimonial-dot.active {
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    transform: scale(1.2);
}

/* ============================================
   🌌 GALACTIC-TIER BLOCKS - KANBAN BOARD
   Trello/Linear/Notion Style
   ============================================ */
.nexus-kanban-demo {
    padding: 40px 20px;
    overflow-x: auto;
}

.nexus-kanban-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
}

.nexus-kanban-header h3 {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0;
}

.nexus-kanban-actions {
    display: flex;
    gap: 12px;
}

.nexus-kanban-action-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--glass-text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
}

.nexus-kanban-action-btn:hover {
    border-color: var(--glass-primary);
    color: var(--glass-primary);
}

.nexus-kanban-action-btn.primary {
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border: none;
    color: white;
}

.nexus-kanban-board {
    display: flex;
    gap: 20px;
    min-height: 500px;
}

.nexus-kanban-column {
    flex: 0 0 300px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 16px;
    display: flex;
    flex-direction: column;
}

.nexus-kanban-column-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--glass-border);
}

.nexus-kanban-column-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.nexus-kanban-column-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.nexus-kanban-column-dot.todo { background: #64748b; }
.nexus-kanban-column-dot.progress { background: #3b82f6; }
.nexus-kanban-column-dot.review { background: #f59e0b; }
.nexus-kanban-column-dot.done { background: #22c55e; }

.nexus-kanban-column-name {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--glass-text-primary);
}

.nexus-kanban-column-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 22px;
    padding: 0 6px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--glass-text-muted);
}

.nexus-kanban-column-menu {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: var(--glass-text-muted);
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.nexus-kanban-column-menu:hover {
    background: rgba(255, 255, 255, 0.05);
    color: var(--glass-text-primary);
}

.nexus-kanban-cards {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 100px;
}

.nexus-kanban-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 16px;
    cursor: grab;
    transition: all 0.3s ease;
}

.nexus-kanban-card:hover {
    border-color: var(--glass-primary);
    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.15);
    transform: translateY(-2px);
}

.nexus-kanban-card:active {
    cursor: grabbing;
}

.nexus-kanban-card-labels {
    display: flex;
    gap: 6px;
    margin-bottom: 10px;
}

.nexus-kanban-label {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.nexus-kanban-label.bug { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
.nexus-kanban-label.feature { background: rgba(139, 92, 246, 0.2); color: #8b5cf6; }
.nexus-kanban-label.design { background: rgba(236, 72, 153, 0.2); color: #ec4899; }
.nexus-kanban-label.urgent { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.nexus-kanban-label.docs { background: rgba(6, 182, 212, 0.2); color: #06b6d4; }

.nexus-kanban-card-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--glass-text-primary);
    margin-bottom: 10px;
    line-height: 1.4;
}

.nexus-kanban-card-desc {
    font-size: 0.85rem;
    color: var(--glass-text-muted);
    line-height: 1.5;
    margin-bottom: 14px;
}

.nexus-kanban-card-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.nexus-kanban-card-assignees {
    display: flex;
}

.nexus-kanban-assignee {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid var(--glass-bg);
    margin-left: -8px;
    object-fit: cover;
}

.nexus-kanban-assignee:first-child {
    margin-left: 0;
}

.nexus-kanban-card-stats {
    display: flex;
    gap: 12px;
}

.nexus-kanban-stat {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    color: var(--glass-text-muted);
}

.nexus-kanban-add-card {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    background: transparent;
    border: 1px dashed var(--glass-border);
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--glass-text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
    margin-top: auto;
}

.nexus-kanban-add-card:hover {
    border-color: var(--glass-primary);
    color: var(--glass-primary);
    background: rgba(139, 92, 246, 0.05);
}

/* ============================================
   🌌 GALACTIC RESPONSIVE ADJUSTMENTS
   ============================================ */
@media (max-width: 768px) {
    .nexus-cmd-container {
        max-width: calc(100% - 32px);
        margin: 0 16px;
    }

    .nexus-notif-panel {
        right: 10px;
        left: 10px;
        width: auto;
    }

    .nexus-chat-widget {
        right: 10px;
        left: 10px;
        width: auto;
        bottom: 90px;
    }

    .nexus-pricing-grid {
        grid-template-columns: 1fr;
    }

    .nexus-kanban-board {
        flex-direction: column;
    }

    .nexus-kanban-column {
        flex: none;
        width: 100%;
    }

    .nexus-testimonial-quote {
        font-size: 1.1rem;
    }

    .nexus-player {
        max-width: 100%;
    }
}

/* ============================================
   🌌✨ GALAXY-TIER STYLES - COSMIC LEVEL ✨🌌
   The best page builder CSS in the ENTIRE GALAXY
   ============================================ */

/* ============================================
   🌌 COSMIC HERO - Particles & Aurora
   ============================================ */
.nexus-cosmic-hero {
    position: relative;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 80px 20px;
    overflow: hidden;
    background: radial-gradient(ellipse at 50% 0%, rgba(139, 92, 246, 0.15), transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(6, 182, 212, 0.1), transparent 40%),
                var(--glass-bg);
}

.cosmic-particles {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.cosmic-particle {
    position: absolute;
    width: 6px;
    height: 6px;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.8), transparent);
    border-radius: 50%;
    left: var(--x);
    animation: floatParticle var(--duration) ease-in-out infinite;
    animation-delay: var(--delay);
    opacity: 0;
}

.cosmic-particle::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background: inherit;
    border-radius: 50%;
    filter: blur(4px);
}

@keyframes floatParticle {
    0%, 100% {
        transform: translateY(100vh) scale(0);
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    90% {
        opacity: 1;
    }
    100% {
        transform: translateY(-100px) scale(1);
        opacity: 0;
    }
}

.cosmic-aurora {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 60%;
    background: linear-gradient(180deg,
        rgba(139, 92, 246, 0.1) 0%,
        rgba(6, 182, 212, 0.05) 50%,
        transparent 100%);
    filter: blur(60px);
    animation: auroraFlow 8s ease-in-out infinite;
    pointer-events: none;
}

@keyframes auroraFlow {
    0%, 100% { transform: translateX(-5%) skewX(-5deg); opacity: 0.5; }
    50% { transform: translateX(5%) skewX(5deg); opacity: 0.8; }
}

.cosmic-hero-content {
    position: relative;
    z-index: 1;
    text-align: center;
    max-width: 900px;
}

.cosmic-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--glass-primary);
    margin-bottom: 24px;
    animation: fadeInUp 0.8s ease-out;
}

.cosmic-badge-dot {
    width: 8px;
    height: 8px;
    background: var(--glass-primary);
    border-radius: 50%;
    animation: pulse 2s ease-in-out infinite;
}

.cosmic-title {
    font-size: clamp(2.5rem, 8vw, 5rem);
    font-weight: 800;
    line-height: 1.1;
    margin-bottom: 24px;
    animation: fadeInUp 0.8s ease-out 0.1s both;
}

.cosmic-title-line {
    display: block;
    color: var(--glass-text-primary);
}

.cosmic-title-gradient {
    display: block;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary), #ec4899);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: gradientShift 5s ease-in-out infinite;
    background-size: 200% 200%;
}

@keyframes gradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.cosmic-subtitle {
    font-size: 1.25rem;
    color: var(--glass-text-muted);
    line-height: 1.7;
    margin-bottom: 40px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
    animation: fadeInUp 0.8s ease-out 0.2s both;
}

.cosmic-cta-group {
    display: flex;
    justify-content: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 60px;
    animation: fadeInUp 0.8s ease-out 0.3s both;
}

.cosmic-btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 16px 32px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
    font-size: 1rem;
    font-weight: 700;
    text-decoration: none;
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(139, 92, 246, 0.4);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.cosmic-btn-primary::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.2), transparent);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.cosmic-btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(139, 92, 246, 0.5);
}

.cosmic-btn-primary:hover::before {
    opacity: 1;
}

.cosmic-btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 16px 32px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    color: var(--glass-text-primary);
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    border-radius: 14px;
    transition: all 0.3s ease;
}

.cosmic-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--glass-primary);
}

.cosmic-stats-row {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 40px;
    flex-wrap: wrap;
    animation: fadeInUp 0.8s ease-out 0.4s both;
}

.cosmic-stat {
    text-align: center;
}

.cosmic-stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--glass-text-primary);
}

.cosmic-stat-suffix {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--glass-primary);
}

.cosmic-stat-label {
    display: block;
    font-size: 0.9rem;
    color: var(--glass-text-muted);
    margin-top: 4px;
}

.cosmic-stat-divider {
    width: 1px;
    height: 40px;
    background: var(--glass-border);
}

.cosmic-scroll-indicator {
    position: absolute;
    bottom: 40px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    animation: fadeInUp 0.8s ease-out 0.5s both, bounce 2s ease-in-out infinite 1s;
}

.cosmic-mouse {
    width: 24px;
    height: 40px;
    border: 2px solid var(--glass-border);
    border-radius: 12px;
    display: flex;
    justify-content: center;
    padding-top: 8px;
}

.cosmic-mouse-wheel {
    width: 4px;
    height: 8px;
    background: var(--glass-primary);
    border-radius: 2px;
    animation: mouseScroll 2s ease-in-out infinite;
}

@keyframes mouseScroll {
    0%, 100% { transform: translateY(0); opacity: 1; }
    50% { transform: translateY(8px); opacity: 0.3; }
}

.cosmic-scroll-indicator span {
    font-size: 0.75rem;
    color: var(--glass-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes bounce {
    0%, 100% { transform: translateX(-50%) translateY(0); }
    50% { transform: translateX(-50%) translateY(10px); }
}

/* ============================================
   🤖 AI CHAT INTERFACE
   ============================================ */
.nexus-ai-chat-demo {
    padding: 40px 20px;
    display: flex;
    justify-content: center;
}

.ai-chat-container {
    width: 100%;
    max-width: 700px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.2);
}

.ai-chat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(6, 182, 212, 0.05));
    border-bottom: 1px solid var(--glass-border);
}

.ai-chat-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.ai-logo-orb {
    position: relative;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border-radius: 14px;
    color: white;
    font-size: 1.2rem;
}

.ai-logo-ring {
    position: absolute;
    inset: -3px;
    border: 2px solid var(--glass-primary);
    border-radius: 16px;
    opacity: 0.3;
    animation: ringPulse 2s ease-in-out infinite;
}

.ai-chat-header-info h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0;
}

.ai-status {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    color: #22c55e;
}

.ai-status-dot {
    width: 8px;
    height: 8px;
    background: #22c55e;
    border-radius: 50%;
    animation: pulse 2s ease-in-out infinite;
}

.ai-chat-header-actions {
    display: flex;
    gap: 8px;
}

.ai-header-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    color: var(--glass-text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
}

.ai-header-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--glass-text-primary);
}

.ai-chat-messages {
    padding: 24px;
    max-height: 400px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.ai-message {
    display: flex;
    gap: 12px;
}

.ai-message-user {
    flex-direction: row-reverse;
}

.ai-message-avatar {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border-radius: 10px;
    color: white;
    flex-shrink: 0;
}

.ai-message-content {
    max-width: 80%;
}

.ai-message-bubble {
    padding: 16px 20px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    color: var(--glass-text-primary);
    font-size: 0.95rem;
    line-height: 1.6;
}

.ai-message-user .ai-message-bubble {
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border: none;
    color: white;
}

.ai-message-bubble p {
    margin: 0 0 12px 0;
}

.ai-message-bubble p:last-child {
    margin-bottom: 0;
}

.ai-capabilities {
    list-style: none;
    padding: 0;
    margin: 16px 0;
}

.ai-capabilities li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    font-size: 0.9rem;
}

.ai-capabilities li i {
    color: var(--glass-primary);
}

.ai-code-block {
    margin: 16px 0;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 12px;
    overflow: hidden;
}

.ai-code-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: rgba(0, 0, 0, 0.2);
    font-size: 0.8rem;
    color: var(--glass-text-muted);
}

.ai-code-header span {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ai-copy-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 6px;
    color: var(--glass-text-muted);
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.ai-copy-btn:hover {
    background: var(--glass-primary);
    color: white;
}

.ai-code-block pre {
    margin: 0;
    padding: 14px;
    overflow-x: auto;
}

.ai-code-block code {
    font-family: 'SF Mono', Monaco, Consolas, monospace;
    font-size: 0.85rem;
    color: #a5f3fc;
}

.ai-message-time {
    display: block;
    font-size: 0.75rem;
    color: var(--glass-text-muted);
    margin-top: 8px;
    padding: 0 4px;
}

.ai-message-user .ai-message-time {
    text-align: right;
}

.ai-message-actions {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}

.ai-action-btn {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: var(--glass-text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
}

.ai-action-btn:hover {
    background: rgba(139, 92, 246, 0.1);
    border-color: var(--glass-primary);
    color: var(--glass-primary);
}

.ai-chat-input-area {
    padding: 20px 24px;
    border-top: 1px solid var(--glass-border);
    background: rgba(0, 0, 0, 0.1);
}

.ai-suggestions {
    display: flex;
    gap: 10px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}

.ai-suggestion {
    padding: 8px 14px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    font-size: 0.8rem;
    color: var(--glass-text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
}

.ai-suggestion:hover {
    background: rgba(139, 92, 246, 0.1);
    border-color: var(--glass-primary);
    color: var(--glass-primary);
}

.ai-input-container {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    transition: border-color 0.2s ease;
}

.ai-input-container:focus-within {
    border-color: var(--glass-primary);
}

.ai-attach-btn {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: var(--glass-text-muted);
    cursor: pointer;
    transition: color 0.2s ease;
}

.ai-attach-btn:hover {
    color: var(--glass-primary);
}

.ai-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    font-size: 0.95rem;
    color: var(--glass-text-primary);
}

.ai-input::placeholder {
    color: var(--glass-text-muted);
}

.ai-input-actions {
    display: flex;
    gap: 8px;
}

.ai-voice-btn,
.ai-send-btn {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.ai-voice-btn {
    background: transparent;
    color: var(--glass-text-muted);
}

.ai-voice-btn:hover {
    background: rgba(255, 255, 255, 0.05);
    color: var(--glass-text-primary);
}

.ai-send-btn {
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
}

.ai-send-btn:hover {
    transform: scale(1.05);
}

.ai-input-footer {
    display: flex;
    justify-content: space-between;
    margin-top: 12px;
    font-size: 0.75rem;
    color: var(--glass-text-muted);
}

.ai-input-footer span {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ============================================
   📊 DATA CHARTS
   ============================================ */
.nexus-charts-demo {
    padding: 40px 20px;
}

.charts-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 32px;
    flex-wrap: wrap;
    gap: 20px;
}

.charts-title {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--glass-text-primary);
    margin: 0;
}

.charts-period-selector {
    display: flex;
    gap: 8px;
    padding: 4px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
}

.period-btn {
    padding: 10px 18px;
    background: transparent;
    border: none;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--glass-text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
}

.period-btn.active {
    background: var(--glass-bg);
    color: var(--glass-primary);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.period-btn:hover:not(.active) {
    color: var(--glass-text-primary);
}

.charts-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 24px;
}

@media (max-width: 1024px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
}

.chart-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 24px;
    transition: all 0.3s ease;
}

.chart-card:hover {
    border-color: var(--glass-primary);
    box-shadow: 0 8px 32px rgba(139, 92, 246, 0.1);
}

.chart-card-large {
    grid-row: span 2;
}

.chart-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.chart-card-header h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--glass-text-muted);
    margin: 0 0 8px 0;
}

.chart-value-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.chart-big-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--glass-text-primary);
}

.chart-change {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.chart-change.positive {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.chart-change.negative {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.chart-menu-btn {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: var(--glass-text-muted);
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.chart-menu-btn:hover {
    background: rgba(255, 255, 255, 0.05);
    color: var(--glass-text-primary);
}

.chart-area-container {
    position: relative;
    height: 180px;
    margin-bottom: 16px;
}

.chart-area-svg {
    width: 100%;
    height: 100%;
}

.chart-area-fill {
    animation: chartReveal 1.5s ease-out;
}

.chart-area-line {
    stroke-dasharray: 1000;
    stroke-dashoffset: 1000;
    animation: chartLineReveal 2s ease-out forwards;
}

@keyframes chartReveal {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes chartLineReveal {
    to { stroke-dashoffset: 0; }
}

.chart-dot {
    animation: dotPulse 2s ease-in-out infinite;
}

@keyframes dotPulse {
    0%, 100% { r: 6; }
    50% { r: 8; }
}

.chart-tooltip {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 10px 14px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    text-align: right;
}

.tooltip-date {
    display: block;
    font-size: 0.75rem;
    color: var(--glass-text-muted);
}

.tooltip-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--glass-primary);
}

.chart-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: var(--glass-text-muted);
}

/* Donut Chart */
.donut-container {
    position: relative;
    display: flex;
    justify-content: center;
    padding: 20px 0;
}

.donut-svg {
    width: 160px;
    height: 160px;
    transform: rotate(-90deg);
}

.donut-segment {
    transition: all 0.3s ease;
    stroke-linecap: round;
    animation: donutReveal 1.5s ease-out forwards;
    stroke-dashoffset: 314;
}

@keyframes donutReveal {
    to { stroke-dashoffset: var(--offset, 0); }
}

.donut-segment-1 { --offset: 0; animation-delay: 0s; }
.donut-segment-2 { --offset: -110; animation-delay: 0.2s; }
.donut-segment-3 { --offset: -185; animation-delay: 0.4s; }
.donut-segment-4 { --offset: -235; animation-delay: 0.6s; }

.donut-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.donut-total {
    display: block;
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--glass-text-primary);
}

.donut-label {
    font-size: 0.8rem;
    color: var(--glass-text-muted);
}

.donut-legend {
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.85rem;
    color: var(--glass-text-muted);
}

.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

/* Bar Chart */
.bar-chart-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.bar-row {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.bar-label {
    font-size: 0.85rem;
    color: var(--glass-text-muted);
}

.bar-track {
    height: 32px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    width: 0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 12px;
    animation: barGrow 1.5s ease-out forwards;
}

@keyframes barGrow {
    to { width: var(--width); }
}

.bar-fill-1 { background: linear-gradient(90deg, var(--glass-primary), #a78bfa); animation-delay: 0s; }
.bar-fill-2 { background: linear-gradient(90deg, var(--glass-secondary), #67e8f9); animation-delay: 0.2s; }
.bar-fill-3 { background: linear-gradient(90deg, #22c55e, #4ade80); animation-delay: 0.4s; }
.bar-fill-4 { background: linear-gradient(90deg, #f59e0b, #fbbf24); animation-delay: 0.6s; }

.bar-value {
    font-size: 0.8rem;
    font-weight: 700;
    color: white;
}

/* ============================================
   🔄 3D FLIP CARDS
   ============================================ */
.nexus-flip-cards-demo {
    padding: 60px 20px;
    text-align: center;
}

.flip-cards-title {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--glass-text-primary);
    margin-bottom: 12px;
}

.flip-cards-subtitle {
    font-size: 1.1rem;
    color: var(--glass-text-muted);
    margin-bottom: 48px;
}

.flip-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    max-width: 1100px;
    margin: 0 auto;
    perspective: 1000px;
}

.flip-card {
    height: 400px;
    perspective: 1000px;
}

.flip-card-inner {
    position: relative;
    width: 100%;
    height: 100%;
    transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    transform-style: preserve-3d;
}

.flip-card:hover .flip-card-inner {
    transform: rotateY(180deg);
}

.flip-card-front,
.flip-card-back {
    position: absolute;
    width: 100%;
    height: 100%;
    backface-visibility: hidden;
    border-radius: 24px;
    padding: 40px 30px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.flip-card-front {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(139, 92, 246, 0.05));
    border: 1px solid rgba(139, 92, 246, 0.3);
}

.flip-front-cyan {
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.2), rgba(6, 182, 212, 0.05));
    border-color: rgba(6, 182, 212, 0.3);
}

.flip-front-green {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(34, 197, 94, 0.05));
    border-color: rgba(34, 197, 94, 0.3);
}

.flip-card-back {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    transform: rotateY(180deg);
    justify-content: flex-start;
    text-align: left;
    padding: 30px;
}

.flip-back-cyan {
    border-color: rgba(6, 182, 212, 0.3);
}

.flip-back-green {
    border-color: rgba(34, 197, 94, 0.3);
}

.flip-card-icon {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border-radius: 24px;
    font-size: 2rem;
    color: white;
    margin-bottom: 24px;
    box-shadow: 0 12px 40px rgba(139, 92, 246, 0.3);
}

.flip-card-front h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0 0 8px 0;
}

.flip-card-front p {
    font-size: 1rem;
    color: var(--glass-text-muted);
    margin: 0;
}

.flip-hint {
    position: absolute;
    bottom: 24px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    color: var(--glass-text-muted);
}

.flip-hint i {
    animation: spin 3s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.flip-card-back h3 {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0 0 16px 0;
}

.flip-card-back p {
    font-size: 0.95rem;
    color: var(--glass-text-muted);
    line-height: 1.7;
    margin: 0 0 20px 0;
}

.flip-features {
    list-style: none;
    padding: 0;
    margin: 0 0 24px 0;
}

.flip-features li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    font-size: 0.9rem;
    color: var(--glass-text-primary);
}

.flip-features li i {
    color: #22c55e;
}

.flip-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
    font-size: 0.9rem;
    font-weight: 600;
    text-decoration: none;
    border-radius: 12px;
    transition: all 0.3s ease;
    margin-top: auto;
}

.flip-btn:hover {
    transform: translateX(4px);
    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.4);
}

/* ============================================
   ✨ MORPHING TEXT HERO
   ============================================ */
.nexus-morphing-hero {
    position: relative;
    min-height: 80vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 80px 20px;
    overflow: hidden;
}

.morphing-bg-shapes {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.morph-shape {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.5;
}

.morph-shape-1 {
    width: 400px;
    height: 400px;
    background: var(--glass-primary);
    top: 10%;
    left: 10%;
    animation: morphFloat 15s ease-in-out infinite;
}

.morph-shape-2 {
    width: 300px;
    height: 300px;
    background: var(--glass-secondary);
    top: 50%;
    right: 10%;
    animation: morphFloat 12s ease-in-out infinite reverse;
}

.morph-shape-3 {
    width: 250px;
    height: 250px;
    background: #ec4899;
    bottom: 10%;
    left: 30%;
    animation: morphFloat 18s ease-in-out infinite 2s;
}

@keyframes morphFloat {
    0%, 100% {
        transform: translate(0, 0) scale(1);
        border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;
    }
    25% {
        transform: translate(30px, -30px) scale(1.1);
        border-radius: 30% 60% 70% 40% / 50% 60% 30% 60%;
    }
    50% {
        transform: translate(-20px, 20px) scale(0.95);
        border-radius: 40% 60% 60% 40% / 70% 30% 70% 30%;
    }
    75% {
        transform: translate(20px, 10px) scale(1.05);
        border-radius: 60% 40% 30% 70% / 40% 50% 60% 50%;
    }
}

.morphing-content {
    position: relative;
    z-index: 1;
    text-align: center;
    max-width: 800px;
}

.morphing-title {
    font-size: clamp(2.5rem, 7vw, 4.5rem);
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 24px;
    color: var(--glass-text-primary);
}

.morphing-static {
    display: block;
}

.morphing-words {
    display: block;
    position: relative;
    height: 1.2em;
    overflow: hidden;
}

.morphing-word {
    position: absolute;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    opacity: 0;
    transform: translateY(100%);
    animation: wordCycle 8s ease-in-out infinite;
}

.morphing-word:nth-child(1) { animation-delay: 0s; }
.morphing-word:nth-child(2) { animation-delay: 2s; }
.morphing-word:nth-child(3) { animation-delay: 4s; }
.morphing-word:nth-child(4) { animation-delay: 6s; }

@keyframes wordCycle {
    0%, 20% {
        opacity: 1;
        transform: translateY(0);
    }
    25%, 100% {
        opacity: 0;
        transform: translateY(-100%);
    }
}

.morphing-subtitle {
    font-size: 1.2rem;
    color: var(--glass-text-muted);
    line-height: 1.7;
    margin-bottom: 40px;
}

.morphing-cta {
    display: flex;
    justify-content: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 60px;
}

.morph-btn-primary {
    padding: 16px 32px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
    font-size: 1rem;
    font-weight: 700;
    text-decoration: none;
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(139, 92, 246, 0.4);
    transition: all 0.3s ease;
}

.morph-btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(139, 92, 246, 0.5);
}

.morph-btn-ghost {
    padding: 16px 32px;
    background: transparent;
    border: 2px solid var(--glass-border);
    color: var(--glass-text-primary);
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    border-radius: 14px;
    transition: all 0.3s ease;
}

.morph-btn-ghost:hover {
    border-color: var(--glass-primary);
    color: var(--glass-primary);
}

.morphing-brands {
    text-align: center;
}

.brands-label {
    display: block;
    font-size: 0.85rem;
    color: var(--glass-text-muted);
    margin-bottom: 20px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.brands-logos {
    display: flex;
    justify-content: center;
    gap: 40px;
    flex-wrap: wrap;
}

.brand-logo {
    font-size: 2rem;
    color: var(--glass-text-muted);
    opacity: 0.5;
    transition: all 0.3s ease;
}

.brand-logo:hover {
    opacity: 1;
    color: var(--glass-text-primary);
    transform: scale(1.1);
}

/* ============================================
   🎯 RADIAL MENU
   ============================================ */
.nexus-radial-demo {
    padding: 100px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 400px;
}

.radial-demo-text {
    margin-bottom: 60px;
    font-size: 1rem;
    color: var(--glass-text-muted);
}

.radial-menu-container {
    position: relative;
    width: 200px;
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.radial-trigger {
    position: relative;
    z-index: 10;
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border: none;
    border-radius: 50%;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 8px 32px rgba(139, 92, 246, 0.4);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.radial-trigger:hover {
    transform: scale(1.1);
}

.radial-icon-close {
    position: absolute;
    opacity: 0;
    transform: rotate(-90deg);
    transition: all 0.3s ease;
}

.radial-icon-open {
    transition: all 0.3s ease;
}

.radial-menu-container.open .radial-icon-open {
    opacity: 0;
    transform: rotate(90deg);
}

.radial-menu-container.open .radial-icon-close {
    opacity: 1;
    transform: rotate(0deg);
}

.radial-menu-container.open .radial-trigger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    box-shadow: 0 8px 32px rgba(239, 68, 68, 0.4);
}

.radial-items {
    position: absolute;
    inset: 0;
}

.radial-item {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color);
    border-radius: 50%;
    color: white;
    text-decoration: none;
    font-size: 1.1rem;
    transform: translate(-50%, -50%) scale(0);
    opacity: 0;
    transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.radial-menu-container.open .radial-item {
    opacity: 1;
    transform: translate(-50%, -50%)
               rotate(calc(var(--i) * 60deg))
               translateY(-90px)
               rotate(calc(var(--i) * -60deg))
               scale(1);
    transition-delay: calc(var(--i) * 0.05s);
}

.radial-item:hover {
    transform: translate(-50%, -50%)
               rotate(calc(var(--i) * 60deg))
               translateY(-90px)
               rotate(calc(var(--i) * -60deg))
               scale(1.15) !important;
}

.radial-tooltip {
    position: absolute;
    bottom: -30px;
    left: 50%;
    transform: translateX(-50%);
    padding: 4px 10px;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    font-size: 0.7rem;
    white-space: nowrap;
    border-radius: 4px;
    opacity: 0;
    transition: opacity 0.2s ease;
    pointer-events: none;
}

.radial-item:hover .radial-tooltip {
    opacity: 1;
}

.radial-ring {
    position: absolute;
    inset: 10px;
    border: 2px dashed var(--glass-border);
    border-radius: 50%;
    opacity: 0;
    transform: scale(0.8);
    transition: all 0.4s ease;
}

.radial-menu-container.open .radial-ring {
    opacity: 0.3;
    transform: scale(1);
}

/* ============================================
   🏔️ PARALLAX SECTION
   ============================================ */
.nexus-parallax-section {
    position: relative;
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: linear-gradient(180deg, #0f0f1a 0%, #1a1a2e 100%);
}

.parallax-layer {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.parallax-layer-back {
    z-index: 1;
}

.parallax-stars {
    width: 100%;
    height: 100%;
    background-image:
        radial-gradient(2px 2px at 20px 30px, white, transparent),
        radial-gradient(2px 2px at 40px 70px, rgba(255,255,255,0.8), transparent),
        radial-gradient(1px 1px at 90px 40px, white, transparent),
        radial-gradient(2px 2px at 160px 120px, rgba(255,255,255,0.6), transparent),
        radial-gradient(1px 1px at 230px 80px, white, transparent),
        radial-gradient(2px 2px at 300px 150px, rgba(255,255,255,0.7), transparent);
    background-repeat: repeat;
    background-size: 400px 200px;
    animation: starsMove 100s linear infinite;
}

@keyframes starsMove {
    from { transform: translateY(0); }
    to { transform: translateY(-200px); }
}

.parallax-layer-mid {
    z-index: 2;
    bottom: 0;
    top: auto;
    height: 50%;
}

.parallax-mountains {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
}

.parallax-mountains svg {
    display: block;
    width: 100%;
    height: auto;
}

.parallax-layer-front {
    z-index: 3;
    bottom: 0;
    top: auto;
    height: 40%;
}

.parallax-mountains-front {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
}

.parallax-mountains-front svg {
    display: block;
    width: 100%;
    height: auto;
}

.parallax-content {
    position: relative;
    z-index: 10;
    text-align: center;
    max-width: 600px;
    padding: 0 20px;
}

.parallax-title {
    font-size: 3rem;
    font-weight: 800;
    color: white;
    margin-bottom: 20px;
    text-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}

.parallax-text {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.7;
    margin-bottom: 32px;
}

.parallax-btn {
    display: inline-block;
    padding: 16px 36px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
    font-size: 1rem;
    font-weight: 700;
    text-decoration: none;
    border-radius: 30px;
    box-shadow: 0 8px 32px rgba(139, 92, 246, 0.5);
    transition: all 0.3s ease;
}

.parallax-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(139, 92, 246, 0.6);
}

.parallax-floating-elements {
    position: absolute;
    inset: 0;
    z-index: 5;
    pointer-events: none;
}

.floating-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(40px);
}

.orb-1 {
    width: 150px;
    height: 150px;
    background: var(--glass-primary);
    top: 20%;
    left: 10%;
    animation: orbFloat 8s ease-in-out infinite;
}

.orb-2 {
    width: 100px;
    height: 100px;
    background: var(--glass-secondary);
    top: 40%;
    right: 15%;
    animation: orbFloat 6s ease-in-out infinite reverse;
}

.orb-3 {
    width: 80px;
    height: 80px;
    background: #ec4899;
    bottom: 30%;
    left: 30%;
    animation: orbFloat 10s ease-in-out infinite 2s;
}

@keyframes orbFloat {
    0%, 100% { transform: translate(0, 0); opacity: 0.3; }
    50% { transform: translate(30px, -30px); opacity: 0.6; }
}

/* ============================================
   🔢 ANIMATED COUNTERS
   ============================================ */
.nexus-counters-section {
    position: relative;
    padding: 80px 20px;
    overflow: hidden;
}

.counters-bg-pattern {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(139, 92, 246, 0.1) 1px, transparent 1px);
    background-size: 30px 30px;
    pointer-events: none;
}

.counters-header {
    text-align: center;
    margin-bottom: 60px;
    position: relative;
    z-index: 1;
}

.counters-header h2 {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--glass-text-primary);
    margin-bottom: 12px;
}

.counters-header p {
    font-size: 1.1rem;
    color: var(--glass-text-muted);
}

.counters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

.counter-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 24px;
    padding: 40px 30px;
    text-align: center;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.counter-card:hover {
    transform: translateY(-8px);
    border-color: var(--glass-primary);
    box-shadow: 0 20px 60px rgba(139, 92, 246, 0.15);
}

.counter-icon-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 80px;
    height: 80px;
    margin-bottom: 24px;
}

.counter-icon-bg {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(6, 182, 212, 0.1));
    border-radius: 24px;
    animation: iconPulse 3s ease-in-out infinite;
}

@keyframes iconPulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 0.8; }
}

.counter-icon-wrap i {
    position: relative;
    z-index: 1;
    font-size: 2rem;
    color: var(--glass-primary);
}

.counter-value {
    margin-bottom: 12px;
}

.counter-number {
    font-size: 3.5rem;
    font-weight: 800;
    color: var(--glass-text-primary);
    font-variant-numeric: tabular-nums;
}

.counter-label {
    font-size: 1rem;
    color: var(--glass-text-muted);
    margin-bottom: 20px;
}

.counter-bar {
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    overflow: hidden;
}

.counter-bar-fill {
    height: 100%;
    width: 0;
    background: linear-gradient(90deg, var(--glass-primary), var(--glass-secondary));
    border-radius: 3px;
    animation: counterBarGrow 2s ease-out forwards;
}

@keyframes counterBarGrow {
    to { width: var(--width); }
}

/* ============================================
   🔀 COMPARISON SLIDER
   ============================================ */
.nexus-comparison-demo {
    padding: 60px 20px;
    text-align: center;
}

.comparison-title {
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--glass-text-primary);
    margin-bottom: 12px;
}

.comparison-subtitle {
    font-size: 1.1rem;
    color: var(--glass-text-muted);
    margin-bottom: 48px;
}

.comparison-container {
    max-width: 800px;
    margin: 0 auto;
}

.comparison-wrapper {
    position: relative;
    aspect-ratio: 16/10;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.comparison-before,
.comparison-after {
    position: absolute;
    inset: 0;
}

.comparison-before {
    z-index: 2;
    width: 50%;
    overflow: hidden;
}

.comparison-after {
    z-index: 1;
}

.comparison-image {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.comparison-image-before {
    width: 200%;
}

.comparison-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    color: rgba(255, 255, 255, 0.7);
}

.comparison-placeholder i {
    font-size: 3rem;
    opacity: 0.5;
}

.comparison-placeholder span {
    font-size: 1.2rem;
    font-weight: 600;
}

.placeholder-hint {
    font-size: 0.85rem !important;
    opacity: 0.5;
}

.comparison-label {
    position: absolute;
    bottom: 20px;
    padding: 8px 16px;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    color: white;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.comparison-label-before {
    left: 20px;
}

.comparison-label-after {
    right: 20px;
}

.comparison-handle {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 50%;
    z-index: 10;
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: ew-resize;
    transform: translateX(-50%);
}

.comparison-handle-line {
    flex: 1;
    width: 3px;
    background: white;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
}

.comparison-handle-circle {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border-radius: 50%;
    color: #1f2937;
    font-size: 1.2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    transition: transform 0.2s ease;
}

.comparison-handle:hover .comparison-handle-circle {
    transform: scale(1.1);
}

/* ============================================
   🌊 LIQUID SECTION
   ============================================ */
.nexus-liquid-section {
    position: relative;
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 80px 20px;
    overflow: hidden;
}

.liquid-blobs {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.liquid-blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(60px);
    animation: blobMorph 20s ease-in-out infinite;
}

.blob-1 {
    width: 500px;
    height: 500px;
    background: linear-gradient(135deg, var(--glass-primary), transparent);
    top: -10%;
    left: -10%;
    animation-delay: 0s;
}

.blob-2 {
    width: 400px;
    height: 400px;
    background: linear-gradient(135deg, var(--glass-secondary), transparent);
    bottom: -10%;
    right: -10%;
    animation-delay: -7s;
}

.blob-3 {
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, #ec4899, transparent);
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    animation-delay: -14s;
}

@keyframes blobMorph {
    0%, 100% {
        border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;
        transform: rotate(0deg) scale(1);
    }
    25% {
        border-radius: 30% 60% 70% 40% / 50% 60% 30% 60%;
        transform: rotate(90deg) scale(1.1);
    }
    50% {
        border-radius: 40% 60% 60% 40% / 70% 30% 70% 30%;
        transform: rotate(180deg) scale(0.95);
    }
    75% {
        border-radius: 60% 40% 30% 70% / 40% 50% 60% 50%;
        transform: rotate(270deg) scale(1.05);
    }
}

.liquid-content {
    position: relative;
    z-index: 1;
    text-align: center;
    max-width: 700px;
}

.liquid-overline {
    display: inline-block;
    padding: 8px 20px;
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--glass-primary);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 24px;
}

.liquid-title {
    font-size: clamp(2.5rem, 6vw, 4rem);
    font-weight: 800;
    color: var(--glass-text-primary);
    line-height: 1.2;
    margin-bottom: 24px;
}

.liquid-text {
    font-size: 1.15rem;
    color: var(--glass-text-muted);
    line-height: 1.8;
    margin-bottom: 40px;
}

.liquid-features {
    display: flex;
    justify-content: center;
    gap: 40px;
    margin-bottom: 40px;
    flex-wrap: wrap;
}

.liquid-feature {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.liquid-feature-icon {
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    font-size: 1.3rem;
    color: var(--glass-primary);
    transition: all 0.3s ease;
}

.liquid-feature:hover .liquid-feature-icon {
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border-color: transparent;
    color: white;
    transform: scale(1.1);
}

.liquid-feature span {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--glass-text-primary);
}

.liquid-btn {
    position: relative;
    display: inline-block;
    padding: 18px 40px;
    background: transparent;
    border: 2px solid var(--glass-primary);
    border-radius: 14px;
    color: var(--glass-primary);
    font-size: 1rem;
    font-weight: 700;
    text-decoration: none;
    overflow: hidden;
    transition: all 0.3s ease;
}

.liquid-btn span {
    position: relative;
    z-index: 1;
    transition: color 0.3s ease;
}

.liquid-btn-bg {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.liquid-btn:hover .liquid-btn-bg {
    transform: scaleX(1);
}

.liquid-btn:hover span {
    color: white;
}

/* ============================================
   🎭 GLASS CARDS SHOWCASE
   ============================================ */
.nexus-glass-showcase {
    position: relative;
    padding: 80px 20px;
    overflow: hidden;
}

.glass-bg-orbs {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.glass-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(100px);
}

.glass-orb-1 {
    width: 400px;
    height: 400px;
    background: var(--glass-primary);
    top: 20%;
    left: 10%;
    opacity: 0.3;
}

.glass-orb-2 {
    width: 300px;
    height: 300px;
    background: var(--glass-secondary);
    bottom: 20%;
    right: 10%;
    opacity: 0.3;
}

.glass-orb-3 {
    width: 250px;
    height: 250px;
    background: #ec4899;
    top: 60%;
    left: 50%;
    opacity: 0.2;
}

.glass-section-title {
    text-align: center;
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--glass-text-primary);
    margin-bottom: 60px;
    position: relative;
    z-index: 1;
}

.glass-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    max-width: 1100px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

.glass-card {
    position: relative;
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 24px;
    padding: 40px 30px;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.glass-card:hover {
    transform: translateY(-10px);
    border-color: var(--glass-primary);
    box-shadow: 0 25px 80px rgba(139, 92, 246, 0.2);
}

.glass-card-glow {
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at center, rgba(139, 92, 246, 0.15), transparent 40%);
    pointer-events: none;
}

.glass-card-shine {
    position: absolute;
    top: 0;
    left: -100%;
    width: 50%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transform: skewX(-20deg);
    transition: left 0.6s ease;
    pointer-events: none;
}

.glass-card:hover .glass-card-shine {
    left: 150%;
}

.glass-card-featured {
    border-color: var(--glass-primary);
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(6, 182, 212, 0.05));
}

.glass-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 24px;
}

.glass-card-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border-radius: 18px;
    font-size: 1.5rem;
    color: white;
    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.3);
}

.glass-icon-cyan {
    background: linear-gradient(135deg, var(--glass-secondary), #67e8f9);
    box-shadow: 0 8px 24px rgba(6, 182, 212, 0.3);
}

.glass-icon-green {
    background: linear-gradient(135deg, #22c55e, #4ade80);
    box-shadow: 0 8px 24px rgba(34, 197, 94, 0.3);
}

.glass-card-badge {
    padding: 6px 14px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    color: white;
    text-transform: uppercase;
}

.glass-card-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--glass-text-primary);
    margin: 0 0 8px 0;
}

.glass-card-desc {
    font-size: 0.95rem;
    color: var(--glass-text-muted);
    margin: 0 0 24px 0;
}

.glass-card-features {
    list-style: none;
    padding: 0;
    margin: 0 0 32px 0;
}

.glass-card-features li {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    font-size: 0.95rem;
    color: var(--glass-text-primary);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.glass-card-features li:last-child {
    border-bottom: none;
}

.glass-card-features li i {
    color: #22c55e;
}

.glass-card-price {
    margin-bottom: 24px;
}

.glass-price-currency {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--glass-text-primary);
    vertical-align: top;
}

.glass-price-amount {
    font-size: 4rem;
    font-weight: 800;
    color: var(--glass-text-primary);
    line-height: 1;
}

.glass-price-period {
    font-size: 1rem;
    color: var(--glass-text-muted);
}

.glass-price-custom {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--glass-text-primary);
}

.glass-card-btn {
    display: block;
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    border: none;
    border-radius: 14px;
    font-size: 1rem;
    font-weight: 700;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
}

.glass-card-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(139, 92, 246, 0.4);
}

.glass-btn-outline {
    background: transparent;
    border: 2px solid var(--glass-border);
    color: var(--glass-text-primary);
}

.glass-btn-outline:hover {
    border-color: var(--glass-primary);
    color: var(--glass-primary);
    box-shadow: none;
}

/* ============================================
   🌠 CONSTELLATION SECTION
   ============================================ */
.nexus-constellation-section {
    position: relative;
    min-height: 60vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 80px 20px;
    overflow: hidden;
    background: linear-gradient(180deg, #0f0f1a, #1a1a2e);
}

.constellation-canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
}

.constellation-content {
    position: relative;
    z-index: 1;
    text-align: center;
    max-width: 700px;
}

.constellation-title {
    font-size: 3rem;
    font-weight: 800;
    color: white;
    margin-bottom: 20px;
}

.constellation-text {
    font-size: 1.15rem;
    color: rgba(255, 255, 255, 0.7);
    line-height: 1.7;
    margin-bottom: 48px;
}

.constellation-stats {
    display: flex;
    justify-content: center;
    gap: 60px;
    margin-bottom: 48px;
    flex-wrap: wrap;
}

.const-stat {
    text-align: center;
}

.const-stat-value {
    display: block;
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 4px;
}

.const-stat-label {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.constellation-btn {
    display: inline-block;
    padding: 18px 40px;
    background: linear-gradient(135deg, var(--glass-primary), var(--glass-secondary));
    color: white;
    font-size: 1rem;
    font-weight: 700;
    text-decoration: none;
    border-radius: 30px;
    box-shadow: 0 8px 32px rgba(139, 92, 246, 0.5);
    transition: all 0.3s ease;
}

.constellation-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 40px rgba(139, 92, 246, 0.6);
}

/* ============================================
   🌌 GALAXY RESPONSIVE
   ============================================ */
@media (max-width: 768px) {
    .cosmic-title {
        font-size: 2.5rem;
    }

    .cosmic-stats-row {
        gap: 20px;
    }

    .cosmic-stat-divider {
        display: none;
    }

    .ai-chat-messages {
        max-height: 300px;
    }

    .charts-grid {
        grid-template-columns: 1fr;
    }

    .flip-cards-grid {
        grid-template-columns: 1fr;
    }

    .morphing-title {
        font-size: 2rem;
    }

    .parallax-title {
        font-size: 2rem;
    }

    .counters-grid {
        grid-template-columns: 1fr 1fr;
    }

    .glass-cards-grid {
        grid-template-columns: 1fr;
    }

    .constellation-stats {
        gap: 30px;
    }
}

/* ═══════════════════════════════════════════════════════════════════════════════
   🌌 UNIVERSE-TIER STYLES - Beyond Galactic, Rivaling Type III Civilizations
   ═══════════════════════════════════════════════════════════════════════════════ */

/* ⚛️ QUANTUM ENTANGLEMENT CARDS */
.nexus-quantum-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(135deg, #0a0a1a 0%, #1a0a2e 50%, #0a1a2e 100%);
    overflow: hidden;
}

.quantum-field {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 30% 30%, rgba(0, 245, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 70% 70%, rgba(191, 0, 255, 0.1) 0%, transparent 50%);
    animation: quantumField 10s ease-in-out infinite;
}

@keyframes quantumField {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 1; }
}

.quantum-header {
    text-align: center;
    margin-bottom: 60px;
    position: relative;
    z-index: 2;
}

.quantum-badge {
    display: inline-block;
    padding: 8px 20px;
    background: rgba(0, 245, 255, 0.1);
    border: 1px solid rgba(0, 245, 255, 0.3);
    border-radius: 30px;
    color: #00f5ff;
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 1px;
    margin-bottom: 20px;
}

.quantum-title {
    font-size: 3rem;
    font-weight: 800;
    color: white;
    margin-bottom: 15px;
    background: linear-gradient(135deg, #00f5ff, #bf00ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.quantum-subtitle {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.1rem;
    max-width: 500px;
    margin: 0 auto;
}

.quantum-cards-container {
    display: flex;
    flex-direction: column;
    gap: 60px;
    max-width: 900px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

.quantum-pair {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 20px;
    align-items: center;
}

.quantum-card {
    position: relative;
    padding: 40px 30px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    text-align: center;
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

.quantum-glow {
    position: absolute;
    inset: -50%;
    background: radial-gradient(circle, rgba(0, 245, 255, 0.3) 0%, transparent 70%);
    opacity: 0;
    transition: opacity 0.5s ease;
    pointer-events: none;
}

.quantum-card:hover .quantum-glow,
.quantum-card.entangled .quantum-glow {
    opacity: 1;
}

.quantum-spin {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 30px;
    height: 30px;
    border: 2px solid rgba(0, 245, 255, 0.3);
    border-top-color: #00f5ff;
    border-radius: 50%;
    animation: quantumSpin 2s linear infinite;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.quantum-card:hover .quantum-spin,
.quantum-card.entangled .quantum-spin {
    opacity: 1;
}

@keyframes quantumSpin {
    to { transform: rotate(360deg); }
}

.quantum-icon {
    font-size: 3rem;
    margin-bottom: 20px;
}

.quantum-card h3 {
    color: white;
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.quantum-card p {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    line-height: 1.6;
    margin-bottom: 15px;
}

.quantum-state {
    padding: 10px 15px;
    background: rgba(0, 245, 255, 0.1);
    border-radius: 10px;
    color: #00f5ff;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
}

.state-value {
    font-weight: 700;
}

.quantum-card:hover .state-value,
.quantum-card.entangled .state-value {
    animation: stateFlicker 0.5s ease;
}

@keyframes stateFlicker {
    0%, 100% { opacity: 1; }
    25%, 75% { opacity: 0.3; }
    50% { opacity: 0.8; }
}

.quantum-connection {
    width: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quantum-line {
    width: 100%;
    height: 20px;
}

.quantum-wave {
    fill: none;
    stroke: url(#quantumGrad);
    stroke-width: 2;
    stroke-dasharray: 5 5;
    animation: waveFlow 2s linear infinite;
}

@keyframes waveFlow {
    to { stroke-dashoffset: -20; }
}

.quantum-photon {
    fill: #00f5ff;
    filter: drop-shadow(0 0 5px #00f5ff);
    animation: photonTravel 2s ease-in-out infinite;
}

@keyframes photonTravel {
    0%, 100% { cx: 10; }
    50% { cx: 90; }
}

/* 🔷 TESSERACT 4D */
.nexus-tesseract-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #000000 0%, #0a0a2e 100%);
    overflow: hidden;
    min-height: 600px;
}

.tesseract-void {
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at center, transparent 0%, #000 70%);
}

.tesseract-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
    max-width: 1100px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

.tesseract-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 400px;
    perspective: 1000px;
}

.tesseract {
    position: relative;
    width: 200px;
    height: 200px;
    transform-style: preserve-3d;
    animation: tesseractRotate 20s linear infinite;
}

@keyframes tesseractRotate {
    0% { transform: rotateX(0deg) rotateY(0deg) rotateZ(0deg); }
    100% { transform: rotateX(360deg) rotateY(360deg) rotateZ(360deg); }
}

.tesseract-cube {
    position: absolute;
    width: 100%;
    height: 100%;
    transform-style: preserve-3d;
}

.tesseract-outer {
    animation: outerPulse 4s ease-in-out infinite;
}

@keyframes outerPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.tesseract-inner {
    transform: scale(0.5);
    animation: innerPulse 4s ease-in-out infinite reverse;
}

@keyframes innerPulse {
    0%, 100% { transform: scale(0.5); }
    50% { transform: scale(0.6); }
}

.cube-face {
    position: absolute;
    width: 200px;
    height: 200px;
    border: 2px solid rgba(0, 245, 255, 0.5);
    background: rgba(0, 245, 255, 0.05);
    box-shadow: 0 0 20px rgba(0, 245, 255, 0.3), inset 0 0 20px rgba(0, 245, 255, 0.1);
}

.tesseract-inner .cube-face {
    width: 100px;
    height: 100px;
    border-color: rgba(191, 0, 255, 0.7);
    background: rgba(191, 0, 255, 0.1);
    box-shadow: 0 0 20px rgba(191, 0, 255, 0.5), inset 0 0 20px rgba(191, 0, 255, 0.2);
}

.cube-front { transform: translateZ(100px); }
.cube-back { transform: translateZ(-100px) rotateY(180deg); }
.cube-left { transform: translateX(-100px) rotateY(-90deg); }
.cube-right { transform: translateX(100px) rotateY(90deg); }
.cube-top { transform: translateY(-100px) rotateX(90deg); }
.cube-bottom { transform: translateY(100px) rotateX(-90deg); }

.tesseract-inner .cube-front { transform: translateZ(50px); }
.tesseract-inner .cube-back { transform: translateZ(-50px) rotateY(180deg); }
.tesseract-inner .cube-left { transform: translateX(-50px) rotateY(-90deg); }
.tesseract-inner .cube-right { transform: translateX(50px) rotateY(90deg); }
.tesseract-inner .cube-top { transform: translateY(-50px) rotateX(90deg); }
.tesseract-inner .cube-bottom { transform: translateY(50px) rotateX(-90deg); }

.tesseract-edges {
    position: absolute;
    width: 100%;
    height: 100%;
    transform-style: preserve-3d;
}

.tesseract-edge {
    position: absolute;
    width: 2px;
    background: linear-gradient(to bottom, #00f5ff, #bf00ff);
    box-shadow: 0 0 10px #00f5ff;
    transform-origin: top center;
}

.edge-1 { height: 70px; top: 0; left: 0; transform: translateZ(100px) rotateX(45deg); }
.edge-2 { height: 70px; top: 0; right: 0; transform: translateZ(100px) rotateX(45deg); }
.edge-3 { height: 70px; bottom: 0; left: 0; transform: translateZ(100px) rotateX(-45deg); }
.edge-4 { height: 70px; bottom: 0; right: 0; transform: translateZ(100px) rotateX(-45deg); }
.edge-5 { height: 70px; top: 0; left: 0; transform: translateZ(-100px) rotateX(45deg) rotateY(180deg); }
.edge-6 { height: 70px; top: 0; right: 0; transform: translateZ(-100px) rotateX(45deg) rotateY(180deg); }
.edge-7 { height: 70px; bottom: 0; left: 0; transform: translateZ(-100px) rotateX(-45deg) rotateY(180deg); }
.edge-8 { height: 70px; bottom: 0; right: 0; transform: translateZ(-100px) rotateX(-45deg) rotateY(180deg); }

.tesseract-content {
    color: white;
}

.tesseract-title {
    font-size: 3rem;
    font-weight: 800;
    margin-bottom: 20px;
    background: linear-gradient(135deg, #00f5ff, #bf00ff, #00f5ff);
    background-size: 200% 200%;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: gradientShift 5s ease infinite;
}

.tesseract-text {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.1rem;
    line-height: 1.8;
    margin-bottom: 30px;
}

.tesseract-dimensions {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
}

.dim-label {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 245, 255, 0.1);
    border: 1px solid rgba(0, 245, 255, 0.3);
    border-radius: 10px;
    color: #00f5ff;
    font-family: 'Courier New', monospace;
    font-size: 1.2rem;
    font-weight: 700;
}

.dim-w {
    background: rgba(191, 0, 255, 0.2);
    border-color: rgba(191, 0, 255, 0.5);
    color: #bf00ff;
    animation: wDimension 2s ease-in-out infinite;
}

@keyframes wDimension {
    0%, 100% { transform: scale(1); box-shadow: 0 0 20px rgba(191, 0, 255, 0.5); }
    50% { transform: scale(1.1); box-shadow: 0 0 40px rgba(191, 0, 255, 0.8); }
}

.tesseract-btn {
    padding: 15px 35px;
    background: linear-gradient(135deg, #00f5ff, #bf00ff);
    border: none;
    border-radius: 30px;
    color: white;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 10px 40px rgba(0, 245, 255, 0.4);
}

.tesseract-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 50px rgba(0, 245, 255, 0.6);
}

/* 🧠 NEURAL NETWORK */
.nexus-neural-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(135deg, #0a0015 0%, #150025 50%, #0a0520 100%);
    overflow: hidden;
    min-height: 500px;
}

.neural-background {
    position: absolute;
    inset: 0;
    display: flex;
    justify-content: space-around;
    align-items: center;
    opacity: 0.6;
}

.neural-layer {
    display: flex;
    flex-direction: column;
    gap: 40px;
    align-items: center;
}

.neuron {
    position: relative;
    width: 30px;
    height: 30px;
}

.neuron-core {
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, #00f5ff 0%, #0080ff 100%);
    border-radius: 50%;
    box-shadow: 0 0 20px #00f5ff;
    animation: neuronPulse 2s ease-in-out infinite;
    animation-delay: calc(var(--i) * 0.2s);
}

@keyframes neuronPulse {
    0%, 100% { transform: scale(1); opacity: 0.7; }
    50% { transform: scale(1.2); opacity: 1; }
}

.neuron-pulse {
    position: absolute;
    inset: -10px;
    border: 2px solid #00f5ff;
    border-radius: 50%;
    animation: neuronRing 2s ease-out infinite;
    animation-delay: calc(var(--i) * 0.2s);
}

@keyframes neuronRing {
    0% { transform: scale(0.8); opacity: 1; }
    100% { transform: scale(2); opacity: 0; }
}

.neuron-output .neuron-core {
    background: radial-gradient(circle, #bf00ff 0%, #8000ff 100%);
    box-shadow: 0 0 20px #bf00ff;
}

.neuron-output .neuron-pulse {
    border-color: #bf00ff;
}

.neural-connections {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.neural-content {
    position: relative;
    z-index: 2;
    text-align: center;
    max-width: 600px;
    margin: 0 auto;
    padding-top: 50px;
}

.neural-badge {
    display: inline-block;
    padding: 8px 20px;
    background: rgba(191, 0, 255, 0.1);
    border: 1px solid rgba(191, 0, 255, 0.3);
    border-radius: 30px;
    color: #bf00ff;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 20px;
}

.neural-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 15px;
}

.neural-text {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    line-height: 1.8;
    margin-bottom: 30px;
}

.neural-metrics {
    display: flex;
    justify-content: center;
    gap: 50px;
}

.neural-metrics .metric {
    text-align: center;
}

.metric-value {
    display: block;
    font-size: 2rem;
    font-weight: 800;
    color: #00f5ff;
    margin-bottom: 5px;
}

.metric-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* 🕳️ GRAVITY WELL */
.nexus-gravity-section {
    position: relative;
    padding: 100px 20px;
    background: radial-gradient(ellipse at center, #1a0a2e 0%, #000 100%);
    overflow: hidden;
    min-height: 600px;
}

.gravity-field {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 500px;
    height: 500px;
}

.gravity-ring {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    border: 1px solid rgba(191, 0, 255, 0.3);
    border-radius: 50%;
    width: calc(var(--i) * 100px);
    height: calc(var(--i) * 100px);
    animation: gravityWarp 4s ease-in-out infinite;
    animation-delay: calc(var(--i) * 0.2s);
}

@keyframes gravityWarp {
    0%, 100% {
        transform: translate(-50%, -50%) scale(1);
        border-color: rgba(191, 0, 255, 0.3);
    }
    50% {
        transform: translate(-50%, -50%) scale(0.95);
        border-color: rgba(191, 0, 255, 0.5);
    }
}

.gravity-singularity {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 60px;
    height: 60px;
}

.singularity-core {
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, #bf00ff 0%, #000 70%);
    border-radius: 50%;
    box-shadow: 0 0 50px #bf00ff, 0 0 100px rgba(191, 0, 255, 0.5);
    animation: singularityPulse 2s ease-in-out infinite;
}

@keyframes singularityPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

.event-horizon {
    position: absolute;
    inset: -20px;
    border: 3px solid rgba(0, 0, 0, 0.8);
    border-radius: 50%;
    box-shadow: inset 0 0 30px rgba(0, 0, 0, 0.9);
}

.gravity-particle {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 8px;
    height: 8px;
    background: #00f5ff;
    border-radius: 50%;
    box-shadow: 0 0 10px #00f5ff;
    animation: orbit var(--speed) linear infinite;
    animation-delay: var(--delay);
    transform-origin: 0 0;
}

@keyframes orbit {
    0% {
        transform: rotate(0deg) translateX(calc(var(--orbit) * 50px)) rotate(0deg);
    }
    100% {
        transform: rotate(360deg) translateX(calc(var(--orbit) * 50px)) rotate(-360deg);
    }
}

.gravity-content {
    position: relative;
    z-index: 2;
    text-align: center;
    max-width: 500px;
    margin: 0 auto;
    padding-top: 350px;
}

.gravity-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 15px;
}

.gravity-text {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    line-height: 1.8;
    margin-bottom: 30px;
}

.gravity-stats {
    display: flex;
    justify-content: center;
    gap: 60px;
}

.g-stat {
    text-align: center;
}

.g-value {
    display: block;
    font-size: 3rem;
    font-weight: 800;
    color: #bf00ff;
    font-family: 'Courier New', monospace;
}

.g-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    text-transform: uppercase;
}

/* 📡 HOLOGRAPHIC DISPLAY */
.nexus-hologram-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #000510 0%, #001020 100%);
    overflow: hidden;
}

.holo-scanlines {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(0, 245, 255, 0.03) 2px,
        rgba(0, 245, 255, 0.03) 4px
    );
    pointer-events: none;
    animation: scanlineMove 10s linear infinite;
}

@keyframes scanlineMove {
    0% { transform: translateY(0); }
    100% { transform: translateY(100px); }
}

.holo-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 30px;
    max-width: 400px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

.holo-projector {
    position: relative;
    width: 100px;
    height: 30px;
}

.projector-base {
    width: 100%;
    height: 100%;
    background: linear-gradient(180deg, #333 0%, #111 100%);
    border-radius: 5px 5px 10px 10px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.5);
}

.projector-beam {
    position: absolute;
    top: -300px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 150px solid transparent;
    border-right: 150px solid transparent;
    border-bottom: 300px solid rgba(0, 245, 255, 0.05);
    filter: blur(10px);
}

.holo-display {
    position: relative;
    width: 300px;
    padding: 30px;
    background: rgba(0, 245, 255, 0.03);
    border: 1px solid rgba(0, 245, 255, 0.2);
    border-radius: 15px;
    animation: holoFlicker 5s ease-in-out infinite;
}

@keyframes holoFlicker {
    0%, 95%, 100% { opacity: 1; }
    96%, 98% { opacity: 0.8; }
    97% { opacity: 0.9; }
}

.holo-frame {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.holo-corner {
    position: absolute;
    width: 20px;
    height: 20px;
    border-color: #00f5ff;
    border-style: solid;
}

.holo-tl { top: 0; left: 0; border-width: 2px 0 0 2px; }
.holo-tr { top: 0; right: 0; border-width: 2px 2px 0 0; }
.holo-bl { bottom: 0; left: 0; border-width: 0 0 2px 2px; }
.holo-br { bottom: 0; right: 0; border-width: 0 2px 2px 0; }

.holo-content {
    text-align: center;
    position: relative;
    z-index: 2;
}

.holo-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    font-size: 0.75rem;
}

.holo-status {
    color: #00ff88;
    animation: statusBlink 1s ease-in-out infinite;
}

@keyframes statusBlink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.holo-id {
    color: rgba(0, 245, 255, 0.7);
    font-family: 'Courier New', monospace;
}

.holo-avatar {
    position: relative;
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
}

.avatar-ring {
    position: absolute;
    inset: 0;
    border: 2px solid #00f5ff;
    border-radius: 50%;
    animation: avatarSpin 10s linear infinite;
}

@keyframes avatarSpin {
    to { transform: rotate(360deg); }
}

.avatar-core {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    background: rgba(0, 245, 255, 0.1);
    border-radius: 50%;
}

.holo-name {
    color: #00f5ff;
    font-size: 1.3rem;
    font-weight: 700;
    letter-spacing: 3px;
    margin-bottom: 5px;
}

.holo-role {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    margin-bottom: 20px;
}

.holo-stats-row {
    display: flex;
    justify-content: center;
    gap: 40px;
    margin-bottom: 20px;
}

.holo-stat {
    text-align: center;
}

.holo-stat-val {
    display: block;
    color: white;
    font-size: 1.3rem;
    font-weight: 700;
}

.holo-stat-lbl {
    color: rgba(0, 245, 255, 0.6);
    font-size: 0.7rem;
    letter-spacing: 1px;
}

.holo-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.holo-btn {
    padding: 10px 25px;
    background: transparent;
    border: 1px solid #00f5ff;
    color: #00f5ff;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 1px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.holo-btn:hover {
    background: rgba(0, 245, 255, 0.1);
    box-shadow: 0 0 20px rgba(0, 245, 255, 0.3);
}

.holo-btn-alt {
    border-color: rgba(255, 255, 255, 0.3);
    color: rgba(255, 255, 255, 0.7);
}

.holo-glitch {
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent 0%, rgba(0, 245, 255, 0.1) 50%, transparent 100%);
    animation: glitchSweep 3s ease-in-out infinite;
    pointer-events: none;
}

@keyframes glitchSweep {
    0%, 100% { transform: translateX(-100%); opacity: 0; }
    50% { transform: translateX(100%); opacity: 1; }
}

/* 🌀 WORMHOLE PORTAL */
.nexus-wormhole-section {
    position: relative;
    padding: 100px 20px;
    background: radial-gradient(ellipse at center, #0a0a2e 0%, #000 100%);
    overflow: hidden;
    min-height: 700px;
}

.wormhole-container {
    position: relative;
    max-width: 500px;
    margin: 0 auto;
    height: 400px;
}

.wormhole-tunnel {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 400px;
    height: 400px;
    perspective: 500px;
}

.tunnel-ring {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    border: 2px solid;
    border-color: rgba(0, 245, 255, calc(1 - var(--i) * 0.08));
    border-radius: 50%;
    width: calc(400px - var(--i) * 35px);
    height: calc(400px - var(--i) * 35px);
    animation: tunnelPulse 3s ease-in-out infinite;
    animation-delay: calc(var(--i) * 0.1s);
}

@keyframes tunnelPulse {
    0%, 100% {
        transform: translate(-50%, -50%) scale(1) rotateX(70deg);
        opacity: 1;
    }
    50% {
        transform: translate(-50%, -50%) scale(0.95) rotateX(70deg);
        opacity: 0.7;
    }
}

.tunnel-core {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 100px;
    height: 100px;
    background: radial-gradient(circle, #fff 0%, #00f5ff 30%, #bf00ff 70%, transparent 100%);
    border-radius: 50%;
    box-shadow: 0 0 60px #00f5ff, 0 0 120px rgba(191, 0, 255, 0.5);
    animation: coreGlow 2s ease-in-out infinite;
}

@keyframes coreGlow {
    0%, 100% { transform: translate(-50%, -50%) scale(1); }
    50% { transform: translate(-50%, -50%) scale(1.2); }
}

.destination-preview {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.dest-icon {
    display: block;
    font-size: 2rem;
    animation: destFloat 3s ease-in-out infinite;
}

@keyframes destFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.dest-text {
    display: block;
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 2px;
    margin-top: 5px;
}

.wormhole-particles {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 400px;
    height: 400px;
    transform: translate(-50%, -50%);
}

.w-particle {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 4px;
    height: 4px;
    background: #00f5ff;
    border-radius: 50%;
    box-shadow: 0 0 10px #00f5ff;
    animation: wParticleSpiral 4s linear infinite;
    transform: rotate(var(--angle)) translateY(-200px);
}

@keyframes wParticleSpiral {
    0% {
        transform: rotate(var(--angle)) translateY(-200px) scale(1);
        opacity: 1;
    }
    100% {
        transform: rotate(calc(var(--angle) + 720deg)) translateY(0) scale(0);
        opacity: 0;
    }
}

.wormhole-content {
    position: relative;
    z-index: 2;
    text-align: center;
    max-width: 500px;
    margin: 0 auto;
    padding-top: 430px;
}

.wormhole-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 15px;
}

.wormhole-text {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    line-height: 1.8;
    margin-bottom: 30px;
}

.wormhole-destinations {
    display: flex;
    justify-content: center;
    gap: 15px;
    flex-wrap: wrap;
}

.dest-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 25px;
    background: rgba(0, 245, 255, 0.1);
    border: 1px solid rgba(0, 245, 255, 0.3);
    border-radius: 30px;
    color: #00f5ff;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.dest-btn:hover {
    background: rgba(0, 245, 255, 0.2);
    transform: scale(1.05);
    box-shadow: 0 0 30px rgba(0, 245, 255, 0.3);
}

/* ⚡ PLASMA ENERGY FIELD */
.nexus-plasma-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(135deg, #0a0020 0%, #200030 50%, #100020 100%);
    overflow: hidden;
}

.plasma-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
    max-width: 1000px;
    margin: 0 auto;
}

.plasma-orb {
    position: relative;
    width: 350px;
    height: 350px;
    margin: 0 auto;
}

.plasma-core {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 80px;
    height: 80px;
    background: radial-gradient(circle, #fff 0%, #bf00ff 40%, #4a00e0 100%);
    border-radius: 50%;
    box-shadow: 0 0 60px #bf00ff, 0 0 120px rgba(191, 0, 255, 0.5);
    animation: plasmaCorePulse 2s ease-in-out infinite;
}

@keyframes plasmaCorePulse {
    0%, 100% { transform: translate(-50%, -50%) scale(1); }
    50% { transform: translate(-50%, -50%) scale(1.1); }
}

.plasma-arcs {
    position: absolute;
    inset: 0;
}

.plasma-arc {
    fill: none;
    stroke: #bf00ff;
    stroke-width: 3;
    stroke-linecap: round;
    animation: arcFlicker 0.5s ease-in-out infinite;
}

.arc-1 { animation-delay: 0s; }
.arc-2 { animation-delay: 0.1s; }
.arc-3 { animation-delay: 0.2s; }
.arc-4 { animation-delay: 0.15s; }
.arc-5 { animation-delay: 0.25s; }
.arc-6 { animation-delay: 0.05s; }

@keyframes arcFlicker {
    0%, 100% { opacity: 1; stroke: #bf00ff; }
    25% { opacity: 0.3; stroke: #00f5ff; }
    50% { opacity: 0.8; stroke: #ff00ff; }
    75% { opacity: 0.5; stroke: #bf00ff; }
}

.plasma-shell {
    position: absolute;
    inset: 30px;
    border: 2px solid rgba(191, 0, 255, 0.3);
    border-radius: 50%;
    animation: shellRotate 20s linear infinite;
}

@keyframes shellRotate {
    to { transform: rotate(360deg); }
}

.plasma-content {
    color: white;
}

.plasma-badge {
    display: inline-block;
    padding: 8px 20px;
    background: rgba(191, 0, 255, 0.1);
    border: 1px solid rgba(191, 0, 255, 0.3);
    border-radius: 30px;
    color: #bf00ff;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 20px;
}

.plasma-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 15px;
}

.plasma-text {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    line-height: 1.8;
    margin-bottom: 30px;
}

.plasma-meters {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.meter {
    display: grid;
    grid-template-columns: 120px 1fr 80px;
    align-items: center;
    gap: 15px;
}

.meter-label {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
}

.meter-bar {
    height: 10px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 5px;
    overflow: hidden;
}

.meter-fill {
    height: 100%;
    width: var(--fill);
    background: linear-gradient(90deg, #bf00ff, #00f5ff);
    border-radius: 5px;
    animation: meterPulse 2s ease-in-out infinite;
}

@keyframes meterPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.meter-value {
    color: #00f5ff;
    font-size: 0.9rem;
    font-weight: 700;
    text-align: right;
}

/* 🌈 DIMENSIONAL RIFT */
.nexus-rift-section {
    position: relative;
    padding: 100px 20px;
    background: #000;
    overflow: hidden;
    min-height: 500px;
}

.rift-background {
    position: absolute;
    inset: 0;
    display: flex;
}

.reality-a, .reality-b {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
}

.reality-a {
    background: linear-gradient(135deg, #1a0a2e 0%, #2a1a4e 100%);
}

.reality-b {
    background: linear-gradient(135deg, #0a2e1a 0%, #1a4e2a 100%);
}

.reality-content {
    text-align: center;
    color: white;
    opacity: 0.5;
}

.reality-content h3 {
    font-size: 1.5rem;
    margin-bottom: 10px;
}

.reality-content p {
    font-size: 0.9rem;
    opacity: 0.7;
}

.rift-tear {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100px;
    height: 100%;
    display: flex;
}

.rift-edge {
    width: 20px;
    height: 100%;
    background: linear-gradient(180deg, #bf00ff, #00f5ff, #bf00ff);
    filter: blur(5px);
    animation: riftPulse 2s ease-in-out infinite;
}

.rift-edge-left {
    animation-delay: 0s;
}

.rift-edge-right {
    animation-delay: 0.5s;
}

@keyframes riftPulse {
    0%, 100% { opacity: 1; width: 20px; }
    50% { opacity: 0.5; width: 30px; }
}

.rift-void {
    flex: 1;
    background: #000;
    position: relative;
    overflow: hidden;
}

.void-star {
    position: absolute;
    left: var(--x);
    top: var(--y);
    width: 3px;
    height: 3px;
    background: white;
    border-radius: 50%;
    animation: voidTwinkle 2s ease-in-out infinite;
}

@keyframes voidTwinkle {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(0.5); }
}

.void-energy {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg,
        transparent 0%,
        rgba(191, 0, 255, 0.2) 25%,
        rgba(0, 245, 255, 0.2) 50%,
        rgba(191, 0, 255, 0.2) 75%,
        transparent 100%
    );
    animation: voidFlow 3s linear infinite;
}

@keyframes voidFlow {
    0% { transform: translateY(-100%); }
    100% { transform: translateY(100%); }
}

.rift-content {
    position: relative;
    z-index: 2;
    text-align: center;
    max-width: 500px;
    margin: 0 auto;
    padding-top: 50px;
}

.rift-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 15px;
}

.rift-text {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    line-height: 1.8;
    margin-bottom: 30px;
}

.rift-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
}

.toggle-label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.toggle-switch {
    width: 60px;
    height: 30px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    position: relative;
    cursor: pointer;
}

.toggle-slider {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 24px;
    height: 24px;
    background: linear-gradient(135deg, #bf00ff, #00f5ff);
    border-radius: 50%;
    transition: transform 0.3s ease;
}

.toggle-switch:hover .toggle-slider {
    transform: translateX(30px);
}

/* ⏳ COSMIC TIMELINE */
.nexus-cosmic-timeline {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #000 0%, #0a0a2e 50%, #000 100%);
    overflow: hidden;
}

.timeline-universe {
    max-width: 900px;
    margin: 0 auto;
}

.timeline-header {
    text-align: center;
    margin-bottom: 60px;
}

.timeline-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 10px;
}

.timeline-subtitle {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
}

.timeline-track {
    position: relative;
    padding: 40px 0;
}

.timeline-line {
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #bf00ff, #00f5ff, #bf00ff);
    transform: translateY(-50%);
    border-radius: 2px;
}

.timeline-event {
    position: absolute;
    top: 50%;
    left: var(--pos);
    transform: translate(-50%, -50%);
}

.event-marker {
    position: relative;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(10, 10, 30, 0.9);
    border: 2px solid #00f5ff;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 2;
}

.event-marker:hover {
    transform: scale(1.2);
    border-color: #bf00ff;
    box-shadow: 0 0 30px rgba(191, 0, 255, 0.5);
}

.marker-pulse {
    position: absolute;
    inset: -5px;
    border: 2px solid #00f5ff;
    border-radius: 50%;
    animation: markerPulse 2s ease-out infinite;
}

@keyframes markerPulse {
    0% { transform: scale(1); opacity: 1; }
    100% { transform: scale(1.5); opacity: 0; }
}

.marker-icon {
    font-size: 1.5rem;
}

.marker-now {
    border-color: #00ff88;
    box-shadow: 0 0 20px rgba(0, 255, 136, 0.5);
}

.marker-now .marker-pulse {
    border-color: #00ff88;
}

.event-content {
    position: absolute;
    top: 80px;
    left: 50%;
    transform: translateX(-50%);
    text-align: center;
    width: 150px;
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.event-marker:hover + .event-content,
.timeline-event:hover .event-content {
    opacity: 1;
}

.event-time {
    display: block;
    color: #00f5ff;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 5px;
}

.event-title {
    color: white;
    font-size: 0.9rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.event-desc {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.75rem;
    line-height: 1.4;
}

/* 🌫️ NEBULA FORM */
.nexus-nebula-form {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(135deg, #1a0a2e 0%, #0a1a3e 100%);
    overflow: hidden;
}

.nebula-clouds {
    position: absolute;
    inset: 0;
    overflow: hidden;
}

.nebula-cloud {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.4;
}

.cloud-1 {
    top: -20%;
    left: -10%;
    width: 60%;
    height: 60%;
    background: radial-gradient(circle, #bf00ff 0%, transparent 70%);
    animation: cloudFloat 20s ease-in-out infinite;
}

.cloud-2 {
    bottom: -20%;
    right: -10%;
    width: 50%;
    height: 50%;
    background: radial-gradient(circle, #00f5ff 0%, transparent 70%);
    animation: cloudFloat 25s ease-in-out infinite reverse;
}

.cloud-3 {
    top: 30%;
    right: 20%;
    width: 40%;
    height: 40%;
    background: radial-gradient(circle, #ff6b00 0%, transparent 70%);
    animation: cloudFloat 30s ease-in-out infinite;
}

@keyframes cloudFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(30px, -20px) scale(1.1); }
    66% { transform: translate(-20px, 30px) scale(0.9); }
}

.nebula-stars {
    position: absolute;
    inset: 0;
}

.n-star {
    position: absolute;
    left: var(--x);
    top: var(--y);
    width: var(--s);
    height: var(--s);
    background: white;
    border-radius: 50%;
    animation: starTwinkle 3s ease-in-out infinite;
}

@keyframes starTwinkle {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

.nebula-form-container {
    position: relative;
    z-index: 2;
    max-width: 500px;
    margin: 0 auto;
    padding: 50px 40px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 30px;
    backdrop-filter: blur(10px);
}

.nebula-form-header {
    text-align: center;
    margin-bottom: 40px;
}

.form-badge {
    display: inline-block;
    padding: 8px 20px;
    background: rgba(191, 0, 255, 0.1);
    border: 1px solid rgba(191, 0, 255, 0.3);
    border-radius: 30px;
    color: #bf00ff;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 1px;
    margin-bottom: 20px;
}

.form-title {
    font-size: 2rem;
    font-weight: 800;
    color: white;
    margin-bottom: 10px;
}

.form-subtitle {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.95rem;
}

.cosmic-form {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.form-field {
    position: relative;
}

.field-label {
    display: block;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 10px;
    letter-spacing: 0.5px;
}

.cosmic-input {
    width: 100%;
    padding: 15px 20px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    color: white;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.cosmic-input::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

.cosmic-input:focus {
    outline: none;
    border-color: #00f5ff;
    box-shadow: 0 0 20px rgba(0, 245, 255, 0.2);
}

.cosmic-textarea {
    resize: vertical;
    min-height: 120px;
}

.field-glow {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, #00f5ff, transparent);
    transition: width 0.3s ease;
}

.cosmic-input:focus ~ .field-glow {
    width: 80%;
}

.cosmic-submit {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 18px 40px;
    background: linear-gradient(135deg, #bf00ff, #00f5ff);
    border: none;
    border-radius: 30px;
    color: white;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    overflow: hidden;
    transition: all 0.3s ease;
}

.cosmic-submit:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 40px rgba(191, 0, 255, 0.4);
}

.btn-wave {
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transform: translateX(-100%);
    transition: transform 0.5s ease;
}

.cosmic-submit:hover .btn-wave {
    transform: translateX(100%);
}

/* 🎆 SUPERNOVA REVEAL */
.nexus-supernova-section {
    position: relative;
    padding: 100px 20px;
    background: radial-gradient(ellipse at center, #2a1a0a 0%, #000 100%);
    overflow: hidden;
    min-height: 600px;
}

.supernova-container {
    position: relative;
    max-width: 600px;
    margin: 0 auto;
}

.supernova-star {
    position: relative;
    width: 200px;
    height: 200px;
    margin: 0 auto 50px;
}

.star-core {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 60px;
    height: 60px;
    background: radial-gradient(circle, #fff 0%, #ffaa00 50%, #ff5500 100%);
    border-radius: 50%;
    box-shadow: 0 0 60px #ffaa00, 0 0 120px rgba(255, 170, 0, 0.5);
    animation: starCorePulse 2s ease-in-out infinite;
}

@keyframes starCorePulse {
    0%, 100% { transform: translate(-50%, -50%) scale(1); }
    50% { transform: translate(-50%, -50%) scale(1.2); }
}

.star-corona {
    position: absolute;
    inset: -20px;
    background: radial-gradient(circle, rgba(255, 170, 0, 0.3) 0%, transparent 70%);
    border-radius: 50%;
    animation: coronaPulse 3s ease-in-out infinite;
}

@keyframes coronaPulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.3); opacity: 1; }
}

.star-flare {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 4px;
    height: 100px;
    background: linear-gradient(to top, #ffaa00, transparent);
    transform-origin: bottom center;
    animation: flarePulse 2s ease-in-out infinite;
}

.flare-1 { transform: translate(-50%, -100%) rotate(0deg); animation-delay: 0s; }
.flare-2 { transform: translate(-50%, -100%) rotate(90deg); animation-delay: 0.5s; }
.flare-3 { transform: translate(-50%, -100%) rotate(180deg); animation-delay: 1s; }
.flare-4 { transform: translate(-50%, -100%) rotate(270deg); animation-delay: 1.5s; }

@keyframes flarePulse {
    0%, 100% { height: 100px; opacity: 1; }
    50% { height: 150px; opacity: 0.5; }
}

.supernova-explosion {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.explosion-ring {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    border: 2px solid rgba(255, 170, 0, 0.5);
    border-radius: 50%;
    animation: explosionExpand 3s ease-out infinite;
}

.ring-1 { animation-delay: 0s; }
.ring-2 { animation-delay: 1s; }
.ring-3 { animation-delay: 2s; }

@keyframes explosionExpand {
    0% { width: 60px; height: 60px; opacity: 1; }
    100% { width: 400px; height: 400px; opacity: 0; }
}

.explosion-debris {
    position: absolute;
    top: 50%;
    left: 50%;
}

.debris {
    position: absolute;
    width: 6px;
    height: 6px;
    background: #ffaa00;
    border-radius: 50%;
    transform: rotate(var(--angle)) translateY(calc(var(--dist) * -1));
    animation: debrisFloat 4s ease-out infinite;
    box-shadow: 0 0 10px #ffaa00;
}

@keyframes debrisFloat {
    0% { transform: rotate(var(--angle)) translateY(0); opacity: 1; }
    100% { transform: rotate(var(--angle)) translateY(calc(var(--dist) * -2)); opacity: 0; }
}

.supernova-content {
    text-align: center;
    position: relative;
    z-index: 2;
}

.supernova-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 15px;
}

.supernova-text {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    line-height: 1.8;
    margin-bottom: 40px;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.supernova-countdown {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin-bottom: 40px;
}

.countdown-item {
    text-align: center;
}

.count-value {
    display: block;
    font-size: 3rem;
    font-weight: 800;
    color: #ffaa00;
    font-family: 'Courier New', monospace;
    text-shadow: 0 0 20px rgba(255, 170, 0, 0.5);
}

.count-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.supernova-btn {
    padding: 15px 40px;
    background: linear-gradient(135deg, #ffaa00, #ff5500);
    border: none;
    border-radius: 30px;
    color: white;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 10px 40px rgba(255, 170, 0, 0.4);
}

.supernova-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 50px rgba(255, 170, 0, 0.6);
}

/* 🔭 OBSERVATORY CARDS */
.nexus-observatory-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #000 0%, #0a0a20 100%);
    overflow: hidden;
}

.observatory-dome {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 300px;
    height: 150px;
    background: linear-gradient(180deg, #1a1a2e 0%, #0a0a1a 100%);
    border-radius: 150px 150px 0 0;
    overflow: hidden;
}

.dome-stars {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 30% 30%, white 1px, transparent 1px),
                radial-gradient(circle at 70% 50%, white 1px, transparent 1px),
                radial-gradient(circle at 50% 70%, white 1px, transparent 1px);
    background-size: 50px 50px;
    animation: domeStarsRotate 60s linear infinite;
}

@keyframes domeStarsRotate {
    to { transform: rotate(360deg); }
}

.dome-slit {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 40px;
    height: 100%;
    background: linear-gradient(180deg, #000510 0%, #001030 100%);
}

.observatory-header {
    text-align: center;
    margin-bottom: 60px;
    padding-top: 80px;
    position: relative;
    z-index: 2;
}

.observatory-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 10px;
}

.observatory-subtitle {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
}

.observatory-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    max-width: 1000px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

.obs-card {
    position: relative;
    padding: 40px 30px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    text-align: center;
    overflow: hidden;
    transition: all 0.5s ease;
}

.obs-card:hover {
    transform: translateY(-10px);
    border-color: rgba(0, 245, 255, 0.3);
}

.obs-card-lens {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity 0.5s ease;
    pointer-events: none;
}

.obs-card:hover .obs-card-lens {
    opacity: 1;
}

.lens-ring {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 200px;
    height: 200px;
    border: 3px solid rgba(0, 245, 255, 0.3);
    border-radius: 50%;
    animation: lensZoom 2s ease-in-out infinite;
}

@keyframes lensZoom {
    0%, 100% { transform: translate(-50%, -50%) scale(0.8); }
    50% { transform: translate(-50%, -50%) scale(1); }
}

.lens-reflection {
    position: absolute;
    top: 20%;
    left: 20%;
    width: 60%;
    height: 60%;
    background: radial-gradient(ellipse at 30% 30%, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    border-radius: 50%;
}

.obs-card-content {
    position: relative;
    z-index: 2;
}

.obs-icon {
    font-size: 3rem;
    margin-bottom: 20px;
}

.obs-title {
    color: white;
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.obs-desc {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    line-height: 1.6;
    margin-bottom: 20px;
}

.obs-data {
    display: flex;
    justify-content: center;
    gap: 20px;
    padding-top: 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.obs-data span {
    color: #00f5ff;
    font-size: 0.8rem;
    font-family: 'Courier New', monospace;
}

/* 🛸 UFO NOTIFICATION */
.nexus-ufo-notification {
    position: relative;
    max-width: 400px;
    margin: 50px auto;
}

.ufo-container {
    position: relative;
    height: 150px;
    margin-bottom: 20px;
}

.ufo-craft {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    animation: ufoHover 3s ease-in-out infinite;
}

@keyframes ufoHover {
    0%, 100% { transform: translateX(-50%) translateY(0); }
    50% { transform: translateX(-50%) translateY(-15px); }
}

.ufo-dome {
    width: 60px;
    height: 30px;
    background: linear-gradient(180deg, rgba(0, 245, 255, 0.3) 0%, rgba(0, 245, 255, 0.1) 100%);
    border-radius: 30px 30px 0 0;
    margin: 0 auto;
}

.ufo-body {
    width: 120px;
    height: 25px;
    background: linear-gradient(180deg, #444 0%, #222 100%);
    border-radius: 60px / 15px;
    position: relative;
    display: flex;
    justify-content: center;
    gap: 20px;
    align-items: center;
}

.ufo-light {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    animation: ufoLights 0.5s ease-in-out infinite alternate;
}

.ufo-light-1 { background: #ff0000; animation-delay: 0s; }
.ufo-light-2 { background: #00ff00; animation-delay: 0.15s; }
.ufo-light-3 { background: #0000ff; animation-delay: 0.3s; }

@keyframes ufoLights {
    0% { opacity: 0.3; transform: scale(0.8); }
    100% { opacity: 1; transform: scale(1); }
}

.ufo-ring {
    position: absolute;
    bottom: -5px;
    left: 50%;
    transform: translateX(-50%);
    width: 140px;
    height: 10px;
    background: linear-gradient(180deg, #333 0%, #111 100%);
    border-radius: 70px / 5px;
}

.ufo-beam {
    position: absolute;
    top: 55px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 60px solid transparent;
    border-right: 60px solid transparent;
    border-top: 100px solid rgba(0, 245, 255, 0.1);
    animation: beamPulse 2s ease-in-out infinite;
}

@keyframes beamPulse {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 0.8; }
}

.ufo-shadow {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 20px;
    background: radial-gradient(ellipse, rgba(0, 0, 0, 0.3) 0%, transparent 70%);
    animation: shadowPulse 3s ease-in-out infinite;
}

@keyframes shadowPulse {
    0%, 100% { transform: translateX(-50%) scale(1); }
    50% { transform: translateX(-50%) scale(0.8); }
}

.ufo-message {
    background: rgba(10, 20, 40, 0.95);
    border: 1px solid rgba(0, 245, 255, 0.3);
    border-radius: 20px;
    overflow: hidden;
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: rgba(0, 245, 255, 0.1);
    border-bottom: 1px solid rgba(0, 245, 255, 0.2);
}

.message-badge {
    color: #00f5ff;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 1px;
    animation: badgePulse 1s ease-in-out infinite;
}

@keyframes badgePulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.message-close {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.5);
    font-size: 1.5rem;
    cursor: pointer;
    transition: color 0.3s ease;
}

.message-close:hover {
    color: white;
}

.message-body {
    padding: 20px;
}

.message-text {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.95rem;
    line-height: 1.6;
}

.message-actions {
    display: flex;
    gap: 10px;
    padding: 0 20px 20px;
}

.ufo-btn {
    flex: 1;
    padding: 12px 20px;
    border: none;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.ufo-btn-primary {
    background: linear-gradient(135deg, #00f5ff, #0080ff);
    color: white;
}

.ufo-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0, 245, 255, 0.4);
}

.ufo-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.7);
}

.ufo-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}

/* 🌑 DARK MATTER PRICING */
.nexus-dark-matter-section {
    position: relative;
    padding: 100px 20px;
    background: #000;
    overflow: hidden;
}

.dark-matter-field {
    position: absolute;
    inset: 0;
}

.dm-particle {
    position: absolute;
    left: var(--x);
    top: var(--y);
    width: 100px;
    height: 100px;
    background: radial-gradient(circle, rgba(100, 100, 150, 0.1) 0%, transparent 70%);
    border-radius: 50%;
    animation: dmFloat 10s ease-in-out infinite;
}

@keyframes dmFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(20px, -20px) scale(1.2); }
}

.dm-web {
    position: absolute;
    inset: 0;
    background:
        linear-gradient(45deg, transparent 48%, rgba(100, 100, 150, 0.05) 50%, transparent 52%),
        linear-gradient(-45deg, transparent 48%, rgba(100, 100, 150, 0.05) 50%, transparent 52%);
    background-size: 100px 100px;
}

.dark-matter-header {
    text-align: center;
    margin-bottom: 60px;
    position: relative;
    z-index: 2;
}

.dm-badge {
    display: inline-block;
    padding: 8px 20px;
    background: rgba(100, 100, 150, 0.1);
    border: 1px solid rgba(100, 100, 150, 0.3);
    border-radius: 30px;
    color: #9999cc;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 20px;
}

.dm-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 10px;
}

.dm-subtitle {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
}

.dark-matter-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    max-width: 1000px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

.dm-card {
    position: relative;
    background: rgba(20, 20, 30, 0.8);
    border: 1px solid rgba(100, 100, 150, 0.2);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.5s ease;
}

.dm-card:hover {
    transform: translateY(-10px);
    border-color: rgba(191, 0, 255, 0.5);
}

.dm-card-featured {
    border-color: rgba(191, 0, 255, 0.3);
}

.dm-featured-badge {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    padding: 5px 20px;
    background: linear-gradient(135deg, #bf00ff, #8000ff);
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: 0 0 10px 10px;
}

.dm-card-visible {
    padding: 40px 30px;
    text-align: center;
    transition: opacity 0.3s ease;
}

.dm-plan {
    color: white;
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 15px;
}

.dm-price {
    font-size: 3rem;
    font-weight: 800;
    color: white;
    margin-bottom: 10px;
}

.dm-price span {
    font-size: 1rem;
    font-weight: 400;
    color: rgba(255, 255, 255, 0.5);
}

.dm-tagline {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
}

.dm-card-hidden {
    position: absolute;
    inset: 0;
    padding: 30px;
    background: linear-gradient(135deg, rgba(191, 0, 255, 0.1) 0%, rgba(0, 245, 255, 0.1) 100%);
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.5s ease;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.dm-card:hover .dm-card-hidden {
    opacity: 1;
    transform: translateY(0);
}

.dm-card:hover .dm-card-visible {
    opacity: 0.2;
}

.dm-card-hidden h4 {
    color: #bf00ff;
    font-size: 0.9rem;
    font-weight: 700;
    margin-bottom: 15px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.dm-hidden-list {
    list-style: none;
    padding: 0;
    margin: 0 0 20px;
}

.dm-hidden-list li {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.dm-true-value {
    color: #00f5ff;
    font-size: 1rem;
    text-align: center;
}

.dm-true-value strong {
    font-size: 1.5rem;
}

.dm-btn {
    display: block;
    width: calc(100% - 40px);
    margin: 0 20px 20px;
    padding: 15px;
    background: transparent;
    border: 1px solid rgba(191, 0, 255, 0.5);
    border-radius: 10px;
    color: #bf00ff;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.dm-btn:hover {
    background: rgba(191, 0, 255, 0.1);
}

.dm-card-featured .dm-btn {
    background: linear-gradient(135deg, #bf00ff, #00f5ff);
    border: none;
    color: white;
}

.dm-card-featured .dm-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(191, 0, 255, 0.4);
}

/* ============================================
   🌌 UNIVERSE RESPONSIVE
   ============================================ */
@media (max-width: 768px) {
    .quantum-pair {
        grid-template-columns: 1fr;
        gap: 30px;
    }

    .quantum-connection {
        transform: rotate(90deg);
        width: 50px;
        margin: 0 auto;
    }

    .tesseract-container {
        grid-template-columns: 1fr;
    }

    .tesseract-wrapper {
        height: 300px;
    }

    .neural-metrics {
        flex-wrap: wrap;
        gap: 30px;
    }

    .gravity-content {
        padding-top: 280px;
    }

    .plasma-container {
        grid-template-columns: 1fr;
    }

    .plasma-orb {
        width: 280px;
        height: 280px;
    }

    .timeline-track {
        display: flex;
        flex-direction: column;
        gap: 30px;
        padding: 0;
    }

    .timeline-line {
        display: none;
    }

    .timeline-event {
        position: relative;
        left: 0;
        transform: none;
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .event-content {
        position: relative;
        top: 0;
        left: 0;
        transform: none;
        text-align: left;
        opacity: 1;
        width: auto;
    }

    .supernova-countdown {
        gap: 15px;
    }

    .count-value {
        font-size: 2rem;
    }

    .observatory-grid {
        grid-template-columns: 1fr;
    }

    .dark-matter-cards {
        grid-template-columns: 1fr;
    }

    .wormhole-content {
        padding-top: 350px;
    }
}

/* ═══════════════════════════════════════════════════════════════════════════════
   🌀 MULTIVERSE-TIER STYLES - Transcending Reality Across Infinite Dimensions
   ═══════════════════════════════════════════════════════════════════════════════ */

/* 🌀 PARALLEL UNIVERSE CARDS */
.nexus-parallel-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(135deg, #0a0520 0%, #150835 50%, #0a0520 100%);
    overflow: hidden;
}

.parallel-void {
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at center, transparent 0%, rgba(0, 0, 0, 0.5) 100%);
}

.parallel-header {
    text-align: center;
    margin-bottom: 60px;
    position: relative;
    z-index: 2;
}

.parallel-badge {
    display: inline-block;
    padding: 10px 25px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(59, 130, 246, 0.2));
    border: 1px solid rgba(139, 92, 246, 0.4);
    border-radius: 30px;
    color: #a78bfa;
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 1px;
    margin-bottom: 20px;
}

.parallel-title {
    font-size: 3rem;
    font-weight: 800;
    color: white;
    margin-bottom: 15px;
    background: linear-gradient(135deg, #a78bfa, #60a5fa, #a78bfa);
    background-size: 200% 200%;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: gradientShift 5s ease infinite;
}

.parallel-subtitle {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.1rem;
}

.parallel-container {
    display: flex;
    justify-content: center;
    align-items: stretch;
    gap: 0;
    max-width: 1200px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

.universe-card {
    position: relative;
    flex: 1;
    max-width: 300px;
    padding: 40px 30px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.5s ease;
}

.universe-card:hover {
    transform: translateY(-10px) scale(1.02);
    z-index: 10;
}

.universe-alpha { border-color: rgba(96, 165, 250, 0.3); }
.universe-beta { border-color: rgba(167, 139, 250, 0.3); }
.universe-gamma { border-color: rgba(52, 211, 153, 0.3); }

.universe-alpha:hover { box-shadow: 0 20px 60px rgba(96, 165, 250, 0.3); }
.universe-beta:hover { box-shadow: 0 20px 60px rgba(167, 139, 250, 0.3); }
.universe-gamma:hover { box-shadow: 0 20px 60px rgba(52, 211, 153, 0.3); }

.universe-label {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 5px 12px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 1px;
}

.universe-shimmer {
    position: absolute;
    inset: 0;
    background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
    transform: translateX(-100%);
    transition: transform 0.8s ease;
}

.universe-card:hover .universe-shimmer {
    transform: translateX(100%);
}

.universe-content {
    position: relative;
    z-index: 2;
    text-align: center;
}

.universe-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    animation: universeFloat 4s ease-in-out infinite;
}

@keyframes universeFloat {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-10px) rotate(5deg); }
}

.universe-content h3 {
    color: white;
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.universe-content p {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    line-height: 1.6;
    margin-bottom: 20px;
}

.universe-traits {
    list-style: none;
    padding: 0;
    margin: 0;
}

.universe-traits li {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.universe-traits li:last-child {
    border-bottom: none;
}

.parallel-divider {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0 15px;
}

.divider-line {
    width: 2px;
    flex: 1;
    background: linear-gradient(to bottom, transparent, rgba(139, 92, 246, 0.5), transparent);
}

.divider-portal {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(139, 92, 246, 0.2);
    border: 1px solid rgba(139, 92, 246, 0.4);
    border-radius: 50%;
    color: #a78bfa;
    font-size: 1.2rem;
    animation: portalPulse 2s ease-in-out infinite;
}

@keyframes portalPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 20px rgba(139, 92, 246, 0.3); }
    50% { transform: scale(1.1); box-shadow: 0 0 40px rgba(139, 92, 246, 0.6); }
}

/* 🎭 SCHRÖDINGER'S CARDS */
.nexus-schrodinger-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #0a0a1a 0%, #1a0a2e 100%);
    overflow: hidden;
}

.schrodinger-particles {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(circle at random, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
    background-size: 50px 50px;
    animation: particlesDrift 20s linear infinite;
}

@keyframes particlesDrift {
    0% { transform: translate(0, 0); }
    100% { transform: translate(50px, 50px); }
}

.schrodinger-header {
    text-align: center;
    margin-bottom: 60px;
    position: relative;
    z-index: 2;
}

.schrodinger-badge {
    display: inline-block;
    padding: 10px 25px;
    background: rgba(236, 72, 153, 0.1);
    border: 1px solid rgba(236, 72, 153, 0.3);
    border-radius: 30px;
    color: #ec4899;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 20px;
}

.schrodinger-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 15px;
}

.schrodinger-subtitle {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    max-width: 600px;
    margin: 0 auto;
}

.schrodinger-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    max-width: 1000px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

.schrodinger-card {
    position: relative;
    height: 300px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.5s ease;
}

.schrodinger-card:hover {
    border-color: rgba(236, 72, 153, 0.5);
    box-shadow: 0 0 40px rgba(236, 72, 153, 0.2);
}

.card-superposition {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px;
    transition: opacity 0.5s ease;
}

.superposition-blur {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(236, 72, 153, 0.1), rgba(139, 92, 246, 0.1));
    filter: blur(20px);
    animation: superpositionShift 3s ease-in-out infinite;
}

@keyframes superpositionShift {
    0%, 100% { transform: scale(1) rotate(0deg); }
    50% { transform: scale(1.1) rotate(5deg); }
}

.superposition-text {
    position: relative;
    z-index: 2;
    color: rgba(255, 255, 255, 0.9);
    font-family: 'Courier New', monospace;
    font-size: 1.1rem;
    margin-bottom: 20px;
    animation: textFlicker 2s ease-in-out infinite;
}

@keyframes textFlicker {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.superposition-hint {
    position: relative;
    z-index: 2;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
}

.card-collapsed {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px;
    text-align: center;
    opacity: 0;
    transform: scale(0.9);
    transition: all 0.5s ease;
}

.schrodinger-card.collapsed-a .card-superposition,
.schrodinger-card.collapsed-b .card-superposition {
    opacity: 0;
    pointer-events: none;
}

.schrodinger-card.collapsed-a .card-state-a,
.schrodinger-card.collapsed-b .card-state-b {
    opacity: 1;
    transform: scale(1);
}

.collapsed-icon {
    font-size: 3rem;
    margin-bottom: 15px;
}

.card-collapsed h3 {
    color: white;
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.card-collapsed p {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    line-height: 1.6;
}

/* 🔀 TIMELINE BRANCHING */
.nexus-branching-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #050510 0%, #0a0a2e 100%);
    overflow: hidden;
}

.branching-bg {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 20% 30%, rgba(59, 130, 246, 0.1) 0%, transparent 40%),
        radial-gradient(circle at 80% 70%, rgba(139, 92, 246, 0.1) 0%, transparent 40%);
}

.branching-header {
    text-align: center;
    margin-bottom: 60px;
    position: relative;
    z-index: 2;
}

.branching-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 10px;
}

.branching-subtitle {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
}

.branching-tree {
    display: flex;
    flex-direction: column;
    align-items: center;
    max-width: 800px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

.branch-node {
    padding: 15px 30px;
    background: rgba(255, 255, 255, 0.05);
    border: 2px solid rgba(139, 92, 246, 0.4);
    border-radius: 50px;
    transition: all 0.3s ease;
}

.branch-root {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(59, 130, 246, 0.2));
}

.branch-node:hover {
    transform: scale(1.05);
    border-color: #a78bfa;
    box-shadow: 0 0 30px rgba(139, 92, 246, 0.4);
}

.node-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.node-icon {
    font-size: 1.5rem;
}

.node-text {
    color: white;
    font-weight: 600;
    font-size: 1rem;
}

.node-description {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
    margin-top: 8px;
    text-align: center;
}

.branch-lines {
    width: 100%;
    height: 80px;
    position: relative;
}

.branch-svg {
    width: 100%;
    height: 100%;
}

.branch-path {
    fill: none;
    stroke: rgba(139, 92, 246, 0.4);
    stroke-width: 2;
    stroke-dasharray: 300;
    stroke-dashoffset: 300;
    animation: branchGrow 2s ease forwards;
}

@keyframes branchGrow {
    to { stroke-dashoffset: 0; }
}

.branch-path-hidden {
    opacity: 0.3;
}

.branch-level {
    display: flex;
    justify-content: space-around;
    width: 100%;
    margin-bottom: 20px;
}

.branch-option {
    cursor: pointer;
}

.branch-outcomes {
    display: flex;
    justify-content: space-around;
    width: 100%;
    margin-top: 20px;
}

.outcome-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: center;
}

.outcome {
    padding: 8px 16px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 20px;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
    opacity: 0.5;
    transition: all 0.3s ease;
}

.outcome-group:hover .outcome {
    opacity: 1;
    background: rgba(139, 92, 246, 0.1);
}

/* 🪞 MIRROR DIMENSION */
.nexus-mirror-section {
    position: relative;
    padding: 100px 20px;
    background: #0a0a1a;
    overflow: hidden;
}

.mirror-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0;
    max-width: 900px;
    margin: 0 auto;
}

.mirror-reality {
    flex: 1;
    padding: 40px;
}

.mirror-normal {
    text-align: right;
}

.mirror-reflected {
    text-align: left;
    transform: scaleX(-1);
}

.mirror-reflected .reality-card {
    transform: scaleX(-1);
}

.reality-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 1px;
    margin-bottom: 20px;
    text-transform: uppercase;
}

.reality-card {
    padding: 30px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    max-width: 280px;
    margin-left: auto;
}

.mirror-reflected .reality-card {
    margin-left: 0;
    margin-right: auto;
    background: rgba(139, 92, 246, 0.05);
    border-color: rgba(139, 92, 246, 0.2);
}

.reality-card .card-icon {
    font-size: 3rem;
    margin-bottom: 15px;
}

.reality-card h3 {
    color: white;
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.reality-card p {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    line-height: 1.6;
    margin-bottom: 15px;
}

.reality-card .card-stats {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.reality-card .card-stats span {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
    font-family: 'Courier New', monospace;
}

.mirror-surface {
    position: relative;
    width: 20px;
    height: 350px;
    background: linear-gradient(180deg, rgba(139, 92, 246, 0.3), rgba(59, 130, 246, 0.3), rgba(139, 92, 246, 0.3));
    border-radius: 10px;
}

.mirror-frame {
    position: absolute;
    inset: -5px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
}

.mirror-ripple {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 100px;
    height: 100px;
    border: 2px solid rgba(139, 92, 246, 0.5);
    border-radius: 50%;
    animation: mirrorRipple 2s ease-out infinite;
}

@keyframes mirrorRipple {
    0% { transform: translate(-50%, -50%) scale(0); opacity: 1; }
    100% { transform: translate(-50%, -50%) scale(3); opacity: 0; }
}

.mirror-glow {
    position: absolute;
    inset: 0;
    background: rgba(139, 92, 246, 0.2);
    filter: blur(20px);
}

.mirror-controls {
    text-align: center;
    margin-top: 40px;
}

.mirror-btn {
    padding: 15px 40px;
    background: linear-gradient(135deg, #8b5cf6, #3b82f6);
    border: none;
    border-radius: 30px;
    color: white;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.mirror-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 40px rgba(139, 92, 246, 0.4);
}

/* ∞ INFINITE LOOP */
.nexus-infinite-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #0a0515 0%, #150525 100%);
    overflow: hidden;
}

.infinite-header {
    text-align: center;
    margin-bottom: 60px;
}

.infinite-badge {
    display: inline-block;
    padding: 10px 25px;
    background: rgba(234, 179, 8, 0.1);
    border: 1px solid rgba(234, 179, 8, 0.3);
    border-radius: 30px;
    color: #eab308;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 20px;
}

.infinite-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 10px;
}

.infinite-subtitle {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
}

.infinite-track-container {
    overflow: hidden;
    margin-bottom: 40px;
}

.infinite-track {
    display: flex;
    gap: 30px;
    animation: infiniteScroll 20s linear infinite;
}

@keyframes infiniteScroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}

.infinite-item {
    flex-shrink: 0;
    width: 250px;
    padding: 30px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    transition: all 0.3s ease;
}

.infinite-item:hover {
    transform: scale(1.05);
    border-color: rgba(234, 179, 8, 0.4);
}

.item-number {
    font-size: 3rem;
    font-weight: 800;
    color: rgba(234, 179, 8, 0.3);
    margin-bottom: 15px;
}

.item-content h4 {
    color: white;
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 8px;
}

.item-content p {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
}

.infinite-indicator {
    display: flex;
    justify-content: center;
}

.indicator-loop svg {
    width: 100px;
    height: 50px;
}

.infinity-path {
    fill: none;
    stroke: #eab308;
    stroke-width: 2;
    stroke-dasharray: 200;
    stroke-dashoffset: 0;
    animation: infinityDraw 4s linear infinite;
}

@keyframes infinityDraw {
    0% { stroke-dashoffset: 0; }
    100% { stroke-dashoffset: -400; }
}

/* 🎲 PROBABILITY CLOUD */
.nexus-probability-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #0a0a20 0%, #1a1040 100%);
    overflow: hidden;
}

.probability-field {
    position: absolute;
    inset: 0;
}

.prob-particle {
    position: absolute;
    left: var(--x);
    top: var(--y);
    width: 100px;
    height: 100px;
    background: radial-gradient(circle, rgba(34, 197, 94, 0.2) 0%, transparent 70%);
    border-radius: 50%;
    animation: probFloat 8s ease-in-out infinite;
    animation-delay: var(--delay);
}

@keyframes probFloat {
    0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.5; }
    50% { transform: translate(30px, -30px) scale(1.3); opacity: 1; }
}

.probability-container {
    position: relative;
    z-index: 2;
    max-width: 900px;
    margin: 0 auto;
}

.probability-header {
    text-align: center;
    margin-bottom: 50px;
}

.prob-badge {
    display: inline-block;
    padding: 10px 25px;
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    border-radius: 30px;
    color: #22c55e;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 20px;
}

.prob-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 10px;
}

.prob-subtitle {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
}

.probability-outcomes {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.outcome-card {
    padding: 25px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    text-align: center;
    transition: all 0.3s ease;
}

.outcome-card:hover {
    transform: translateY(-5px);
    border-color: rgba(34, 197, 94, 0.4);
}

.outcome-probability {
    font-size: 2rem;
    font-weight: 800;
    color: #22c55e;
    margin-bottom: 10px;
}

.outcome-bar {
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    margin-bottom: 15px;
    overflow: hidden;
}

.outcome-card[data-probability="0.35"] .bar-fill { width: 35%; }
.outcome-card[data-probability="0.40"] .bar-fill { width: 40%; }
.outcome-card[data-probability="0.20"] .bar-fill { width: 20%; }
.outcome-card[data-probability="0.05"] .bar-fill { width: 5%; }

.bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #22c55e, #34d399);
    border-radius: 3px;
    animation: barPulse 2s ease-in-out infinite;
}

@keyframes barPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

.outcome-card h4 {
    color: white;
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 8px;
}

.outcome-card p {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.8rem;
    line-height: 1.5;
}

.probability-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    max-width: 300px;
    margin: 0 auto;
    padding: 18px 40px;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    border: none;
    border-radius: 30px;
    color: white;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
}

.probability-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 40px rgba(34, 197, 94, 0.4);
}

.btn-dice {
    font-size: 1.3rem;
    animation: diceRoll 1s ease-in-out infinite;
}

@keyframes diceRoll {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(15deg); }
    75% { transform: rotate(-15deg); }
}

/* 🌊 WAVE FUNCTION */
.nexus-wave-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #0a1020 0%, #102040 100%);
    overflow: hidden;
    min-height: 500px;
}

.wave-background {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.wave-line {
    height: 2px;
    background: rgba(59, 130, 246, 0.3);
    margin: 30px 0;
    animation: wavePropagation 3s ease-in-out infinite;
    animation-delay: calc(var(--i) * 0.2s);
}

@keyframes wavePropagation {
    0%, 100% {
        transform: scaleX(0.5);
        opacity: 0.3;
    }
    50% {
        transform: scaleX(1);
        opacity: 1;
    }
}

.wave-content {
    position: relative;
    z-index: 2;
    max-width: 600px;
    margin: 0 auto;
    text-align: center;
}

.wave-state {
    display: none;
}

.wave-superposition {
    display: block;
}

.state-visual {
    margin-bottom: 30px;
}

.psi-symbol {
    font-size: 6rem;
    font-weight: 800;
    color: rgba(59, 130, 246, 0.8);
    animation: psiOscillate 2s ease-in-out infinite;
}

@keyframes psiOscillate {
    0%, 100% { transform: scale(1); opacity: 0.8; }
    50% { transform: scale(1.1); opacity: 1; }
}

.psi-symbol.collapsed {
    animation: none;
    color: #3b82f6;
}

.wave-oscillation {
    width: 200px;
    height: 4px;
    margin: 20px auto 0;
    background: linear-gradient(90deg, transparent, #3b82f6, transparent);
    animation: oscillate 1s ease-in-out infinite;
}

@keyframes oscillate {
    0%, 100% { transform: scaleX(0.3); }
    50% { transform: scaleX(1); }
}

.particle-point {
    width: 20px;
    height: 20px;
    margin: 20px auto 0;
    background: #3b82f6;
    border-radius: 50%;
    box-shadow: 0 0 30px #3b82f6;
}

.wave-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 15px;
}

.wave-text {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.1rem;
    line-height: 1.8;
    margin-bottom: 30px;
}

.wave-btn {
    padding: 15px 40px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    border: none;
    border-radius: 30px;
    color: white;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.wave-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 40px rgba(59, 130, 246, 0.4);
}

.wave-progress {
    position: absolute;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    text-align: center;
}

.progress-track {
    width: 200px;
    height: 4px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 10px;
}

.progress-fill {
    height: 100%;
    width: 100%;
    background: linear-gradient(90deg, #3b82f6, #60a5fa);
    animation: coherenceDecay 10s linear infinite;
}

@keyframes coherenceDecay {
    0% { width: 100%; }
    100% { width: 0%; }
}

.progress-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
    font-family: 'Courier New', monospace;
}

/* 🔮 MANY-WORLDS CTA */
.nexus-manyworlds-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #0a0a1a 0%, #1a0a30 100%);
    overflow: hidden;
}

.manyworlds-bg {
    position: absolute;
    inset: 0;
}

.world-bubble {
    position: absolute;
    left: var(--x);
    top: var(--y);
    width: var(--size);
    height: var(--size);
    background: radial-gradient(circle, rgba(139, 92, 246, 0.2) 0%, transparent 70%);
    border: 1px solid rgba(139, 92, 246, 0.1);
    border-radius: 50%;
    animation: bubbleFloat 10s ease-in-out infinite;
}

@keyframes bubbleFloat {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(20px, -20px); }
}

.manyworlds-container {
    position: relative;
    z-index: 2;
    max-width: 900px;
    margin: 0 auto;
    text-align: center;
}

.manyworlds-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 15px;
}

.manyworlds-subtitle {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.1rem;
    margin-bottom: 50px;
}

.manyworlds-buttons {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
}

.world-btn-container {
    position: relative;
}

.world-btn {
    padding: 18px 40px;
    border: none;
    border-radius: 30px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.world-btn-primary {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: white;
}

.world-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
}

.world-btn-ghost {
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.6);
}

.world-btn:hover {
    transform: translateY(-5px);
}

.world-preview {
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%) translateY(20px);
    width: 280px;
    padding: 20px;
    background: rgba(20, 10, 40, 0.95);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 15px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    margin-bottom: 15px;
}

.world-btn-container:hover .world-preview {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

.preview-dark {
    background: rgba(10, 5, 20, 0.95);
    border-color: rgba(100, 100, 100, 0.3);
}

.preview-header {
    color: #a78bfa;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 15px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.preview-content {
    text-align: left;
}

.preview-icon {
    font-size: 2rem;
    margin-bottom: 10px;
}

.preview-content p {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    line-height: 1.6;
    margin-bottom: 15px;
}

.preview-stats {
    display: flex;
    gap: 15px;
}

.preview-stats span {
    padding: 5px 10px;
    background: rgba(139, 92, 246, 0.1);
    border-radius: 20px;
    color: #a78bfa;
    font-size: 0.75rem;
}

/* ⚖️ PARALLEL PRICING */
.nexus-parallel-pricing {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #050510 0%, #0a0a25 100%);
    overflow: hidden;
    perspective: 1000px;
}

.pricing-dimensions {
    position: absolute;
    inset: 0;
    transform-style: preserve-3d;
}

.dimension-layer {
    position: absolute;
    inset: 0;
    background: linear-gradient(45deg, rgba(139, 92, 246, 0.05) 25%, transparent 25%, transparent 75%, rgba(139, 92, 246, 0.05) 75%);
    background-size: 100px 100px;
    transform: translateZ(var(--z));
    opacity: var(--opacity);
}

.pricing-header {
    text-align: center;
    margin-bottom: 60px;
    position: relative;
    z-index: 2;
}

.pricing-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin-bottom: 10px;
}

.pricing-subtitle {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
}

.pricing-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    max-width: 1100px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

.pricing-card {
    position: relative;
    padding: 40px 30px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    transition: all 0.5s ease;
}

.pricing-card:hover {
    transform: translateY(-10px);
    border-color: rgba(139, 92, 246, 0.4);
}

.pricing-featured {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(59, 130, 246, 0.1));
    border-color: rgba(139, 92, 246, 0.3);
}

.featured-badge {
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    padding: 8px 20px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-radius: 20px;
    color: white;
    font-size: 0.8rem;
    font-weight: 600;
}

.card-dimension {
    color: rgba(139, 92, 246, 0.8);
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 1px;
    margin-bottom: 20px;
}

.card-header h3 {
    color: white;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.card-price {
    margin-bottom: 20px;
}

.price-current {
    font-size: 3rem;
    font-weight: 800;
    color: white;
}

.price-period {
    color: rgba(255, 255, 255, 0.5);
    font-size: 1rem;
}

.card-alternate {
    padding: 15px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 10px;
    margin-bottom: 20px;
}

.alternate-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.75rem;
    margin-bottom: 10px;
}

.alternate-prices {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.alt-price {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.8rem;
    font-family: 'Courier New', monospace;
    text-decoration: line-through;
}

.card-features {
    list-style: none;
    padding: 0;
    margin: 0 0 25px;
}

.card-features li {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    padding-left: 25px;
    position: relative;
}

.card-features li::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #a78bfa;
}

.card-btn {
    width: 100%;
    padding: 15px;
    background: transparent;
    border: 1px solid rgba(139, 92, 246, 0.4);
    border-radius: 10px;
    color: #a78bfa;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.card-btn:hover {
    background: rgba(139, 92, 246, 0.1);
}

.btn-featured {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border: none;
    color: white;
}

.btn-featured:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(139, 92, 246, 0.4);
}

/* 🎬 ALTERNATE STORIES */
.nexus-stories-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #0a0510 0%, #150520 100%);
    overflow: hidden;
}

.stories-header {
    text-align: center;
    margin-bottom: 60px;
}

.stories-title {
    font-size: 3rem;
    font-weight: 800;
    color: white;
    margin-bottom: 10px;
    font-style: italic;
}

.stories-subtitle {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
}

.stories-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    max-width: 1000px;
    margin: 0 auto;
}

.story-card {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.5s ease;
}

.story-card:hover {
    transform: translateY(-10px);
}

.story-timeline {
    position: absolute;
    top: 15px;
    left: 15px;
    padding: 5px 15px;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 20px;
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
    z-index: 3;
}

.story-cover {
    position: relative;
    height: 180px;
    background: linear-gradient(135deg, #1a0a2e, #0a1a3e);
    display: flex;
    align-items: center;
    justify-content: center;
}

.cover-gradient {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, transparent 100%);
}

.cover-icon {
    font-size: 4rem;
    position: relative;
    z-index: 2;
}

.story-content {
    padding: 25px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-top: none;
    border-radius: 0 0 20px 20px;
}

.story-content h3 {
    color: white;
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.story-content p {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    line-height: 1.6;
    margin-bottom: 15px;
}

.story-outcome {
    display: flex;
    align-items: center;
    gap: 10px;
}

.outcome-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
}

.outcome-value {
    padding: 5px 12px;
    background: rgba(139, 92, 246, 0.2);
    border-radius: 20px;
    color: #a78bfa;
    font-size: 0.8rem;
    font-weight: 600;
}

.story-current {
    border: 2px solid rgba(34, 197, 94, 0.4);
}

.story-current .outcome-value {
    background: rgba(34, 197, 94, 0.2);
    color: #22c55e;
}

/* 🌐 DIMENSION HOPPER */
.nexus-hopper-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #0a0a1a 0%, #0a1025 100%);
    overflow: hidden;
}

.hopper-container {
    max-width: 500px;
    margin: 0 auto;
}

.hopper-device {
    background: rgba(255, 255, 255, 0.03);
    border: 2px solid rgba(59, 130, 246, 0.3);
    border-radius: 30px;
    overflow: hidden;
}

.device-screen {
    position: relative;
    height: 300px;
    background: linear-gradient(135deg, #0a1020 0%, #102040 100%);
}

.screen-content {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px;
    opacity: 0;
    transform: scale(0.9);
    transition: all 0.5s ease;
}

.screen-content.active {
    opacity: 1;
    transform: scale(1);
}

.dim-header {
    color: #60a5fa;
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 1px;
    margin-bottom: 20px;
    text-transform: uppercase;
}

.dim-visual {
    font-size: 5rem;
    margin-bottom: 20px;
    animation: dimFloat 3s ease-in-out infinite;
}

@keyframes dimFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.dim-desc {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1rem;
    text-align: center;
}

.device-controls {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 30px;
    background: rgba(0, 0, 0, 0.3);
}

.hop-btn {
    padding: 10px 20px;
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 20px;
    color: #60a5fa;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.hop-btn:hover {
    background: rgba(59, 130, 246, 0.2);
}

.hop-indicator {
    display: flex;
    gap: 10px;
}

.hop-dot {
    width: 10px;
    height: 10px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
}

.hop-dot.active {
    background: #60a5fa;
    box-shadow: 0 0 10px #60a5fa;
}

.hopper-coordinates {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 20px;
}

.coord {
    padding: 8px 15px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 20px;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
    font-family: 'Courier New', monospace;
}

.coord-d {
    color: #60a5fa;
    background: rgba(59, 130, 246, 0.1);
}

/* 🔄 REALITY ANCHOR */
.nexus-anchor-section {
    position: relative;
    padding: 100px 20px;
    background: radial-gradient(ellipse at center, #1a0a30 0%, #050510 100%);
    overflow: hidden;
}

.anchor-void {
    position: absolute;
    inset: 0;
}

.void-streams {
    position: absolute;
    inset: 0;
}

.stream {
    position: absolute;
    top: 0;
    width: 2px;
    height: 100%;
    background: linear-gradient(to bottom, transparent, rgba(139, 92, 246, 0.5), transparent);
    animation: streamFlow 5s linear infinite;
    animation-delay: var(--delay);
}

.stream:nth-child(1) { left: 20%; }
.stream:nth-child(2) { left: 50%; }
.stream:nth-child(3) { left: 80%; }

@keyframes streamFlow {
    0% { transform: translateY(-100%); }
    100% { transform: translateY(100%); }
}

.anchor-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
    max-width: 1000px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
}

.anchor-visual {
    position: relative;
    width: 300px;
    height: 300px;
    margin: 0 auto;
}

.anchor-rings {
    position: absolute;
    inset: 0;
}

.anchor-ring {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    border: 2px solid;
    border-radius: 50%;
    animation: anchorSpin 20s linear infinite;
}

.ring-outer {
    width: 100%;
    height: 100%;
    border-color: rgba(139, 92, 246, 0.3);
}

.ring-middle {
    width: 70%;
    height: 70%;
    border-color: rgba(139, 92, 246, 0.5);
    animation-direction: reverse;
}

.ring-inner {
    width: 40%;
    height: 40%;
    border-color: rgba(139, 92, 246, 0.7);
}

@keyframes anchorSpin {
    to { transform: translate(-50%, -50%) rotate(360deg); }
}

.anchor-core {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.core-symbol {
    font-size: 2.5rem;
    color: white;
}

.core-pulse {
    position: absolute;
    inset: -10px;
    border: 2px solid #8b5cf6;
    border-radius: 50%;
    animation: corePulse 2s ease-out infinite;
}

@keyframes corePulse {
    0% { transform: scale(1); opacity: 1; }
    100% { transform: scale(1.5); opacity: 0; }
}

.anchor-content {
    color: white;
}

.anchor-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 20px;
}

.anchor-text {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.1rem;
    line-height: 1.8;
    margin-bottom: 30px;
}

.anchor-stats {
    display: flex;
    gap: 30px;
    margin-bottom: 30px;
}

.anchor-stat {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 2.5rem;
    font-weight: 800;
    color: #a78bfa;
    font-family: 'Courier New', monospace;
}

.stat-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.8rem;
}

.anchor-btn {
    padding: 15px 40px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border: none;
    border-radius: 30px;
    color: white;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.anchor-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 40px rgba(139, 92, 246, 0.4);
}

/* ============================================
   🌀 MULTIVERSE RESPONSIVE
   ============================================ */
@media (max-width: 768px) {
    .parallel-container {
        flex-direction: column;
        gap: 20px;
    }

    .parallel-divider {
        flex-direction: row;
        padding: 15px 0;
    }

    .divider-line {
        width: auto;
        height: 2px;
        flex: 1;
    }

    .schrodinger-grid {
        grid-template-columns: 1fr;
    }

    .branch-level {
        flex-direction: column;
        gap: 20px;
    }

    .branch-lines {
        display: none;
    }

    .branch-outcomes {
        flex-direction: column;
        gap: 20px;
    }

    .mirror-container {
        flex-direction: column;
    }

    .mirror-surface {
        width: 100%;
        height: 20px;
    }

    .mirror-normal, .mirror-reflected {
        text-align: center;
    }

    .mirror-reflected {
        transform: scaleY(-1);
    }

    .reality-card {
        margin: 0 auto;
    }

    .probability-outcomes {
        grid-template-columns: 1fr 1fr;
    }

    .manyworlds-buttons {
        flex-direction: column;
        align-items: center;
    }

    .world-preview {
        position: static;
        transform: none;
        margin-top: 15px;
        opacity: 1;
        visibility: visible;
    }

    .pricing-cards {
        grid-template-columns: 1fr;
    }

    .stories-container {
        grid-template-columns: 1fr;
    }

    .anchor-container {
        grid-template-columns: 1fr;
    }

    .anchor-visual {
        width: 250px;
        height: 250px;
    }
}

/* ============================================================
   🕳️ OMNIVERSE-TIER STYLES - Beyond All Existence
   ============================================================ */

/* 🕳️ VOID GENESIS */
.nexus-void-section {
    position: relative;
    padding: 120px 20px;
    background: #000;
    overflow: hidden;
    min-height: 600px;
}

.void-absolute {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at center, transparent 0%, #000 70%);
}

.void-emergence {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.void-particle {
    position: absolute;
    left: var(--x);
    top: var(--y);
    width: 4px;
    height: 4px;
    background: #fff;
    border-radius: 50%;
    animation: voidEmerge 3s ease-out var(--delay) infinite;
    box-shadow: 0 0 20px #fff, 0 0 40px rgba(139, 92, 246, 0.8);
}

@keyframes voidEmerge {
    0% { opacity: 0; transform: scale(0); }
    50% { opacity: 1; transform: scale(1); }
    100% { opacity: 0; transform: scale(2) translateY(-50px); }
}

.void-content {
    position: relative;
    z-index: 10;
    text-align: center;
    max-width: 800px;
    margin: 0 auto;
}

.void-symbol {
    font-size: 8rem;
    color: rgba(255, 255, 255, 0.1);
    animation: voidPulse 4s ease-in-out infinite;
    text-shadow: 0 0 60px rgba(139, 92, 246, 0.5);
}

@keyframes voidPulse {
    0%, 100% { opacity: 0.1; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(1.1); }
}

.void-title {
    font-size: 3rem;
    font-weight: 800;
    color: #fff;
    margin: 30px 0 20px;
    background: linear-gradient(135deg, #fff 0%, #a78bfa 50%, #fff 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.void-text {
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.7);
    line-height: 1.8;
    max-width: 600px;
    margin: 0 auto 40px;
}

.void-emergence-cards {
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 40px;
}

.emergence-card {
    padding: 20px 30px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    animation: emergeIn 0.8s ease-out var(--delay) both;
    backdrop-filter: blur(10px);
}

@keyframes emergeIn {
    0% { opacity: 0; transform: translateY(30px) scale(0.8); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}

.emergence-icon {
    font-size: 2rem;
    display: block;
    margin-bottom: 10px;
}

.emergence-label {
    color: rgba(255, 255, 255, 0.8);
    font-weight: 600;
}

.void-btn {
    padding: 16px 40px;
    background: transparent;
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: #fff;
    font-size: 1.1rem;
    font-weight: 600;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.4s ease;
}

.void-btn:hover {
    background: #fff;
    color: #000;
    box-shadow: 0 0 40px rgba(255, 255, 255, 0.5);
}

/* 📜 COSMIC LAW EDITOR */
.nexus-cosmic-law-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #0a0a1a 0%, #1a0a2e 50%, #0a0a1a 100%);
    overflow: hidden;
}

.law-nebula {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 30% 30%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
        radial-gradient(circle at 70% 70%, rgba(59, 130, 246, 0.15) 0%, transparent 50%);
    animation: nebulaDrift 20s ease-in-out infinite alternate;
}

@keyframes nebulaDrift {
    0% { transform: scale(1) rotate(0deg); }
    100% { transform: scale(1.1) rotate(5deg); }
}

.law-header {
    text-align: center;
    margin-bottom: 60px;
    position: relative;
    z-index: 10;
}

.law-badge {
    display: inline-block;
    padding: 8px 20px;
    background: rgba(139, 92, 246, 0.2);
    border: 1px solid rgba(139, 92, 246, 0.4);
    border-radius: 30px;
    color: #a78bfa;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 20px;
}

.law-title {
    font-size: 2.8rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 15px;
}

.law-subtitle {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.6);
    max-width: 500px;
    margin: 0 auto;
}

.law-editor-container {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 40px;
    max-width: 1000px;
    margin: 0 auto;
    position: relative;
    z-index: 10;
}

.law-panel {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 30px;
    backdrop-filter: blur(20px);
}

.law-item {
    display: grid;
    grid-template-columns: 50px 1fr 150px;
    gap: 20px;
    align-items: center;
    padding: 20px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.law-item:last-child {
    border-bottom: none;
}

.law-icon {
    font-size: 1.8rem;
}

.law-details {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.law-name {
    color: #fff;
    font-weight: 600;
    font-size: 1rem;
}

.law-value {
    color: rgba(139, 92, 246, 0.9);
    font-family: monospace;
    font-size: 0.9rem;
}

.law-slider {
    width: 100%;
}

.slider-track {
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    overflow: hidden;
}

.slider-fill {
    height: 100%;
    background: linear-gradient(90deg, #8b5cf6, #06b6d4);
    border-radius: 3px;
    transition: width 0.3s ease;
}

.law-preview {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    padding: 30px;
}

.preview-universe {
    position: relative;
    width: 200px;
    height: 200px;
}

.preview-core {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 20px;
    height: 20px;
    background: radial-gradient(circle, #fff 0%, #8b5cf6 100%);
    border-radius: 50%;
    box-shadow: 0 0 30px #8b5cf6;
}

.preview-orbit {
    position: absolute;
    top: 50%;
    left: 50%;
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 50%;
    animation: orbitRotate 10s linear infinite;
}

.preview-orbit::after {
    content: '';
    position: absolute;
    width: 8px;
    height: 8px;
    background: #06b6d4;
    border-radius: 50%;
    top: -4px;
    left: 50%;
    transform: translateX(-50%);
    box-shadow: 0 0 10px #06b6d4;
}

.orbit-1 {
    width: 80px;
    height: 80px;
    margin: -40px 0 0 -40px;
    animation-duration: 4s;
}

.orbit-2 {
    width: 120px;
    height: 120px;
    margin: -60px 0 0 -60px;
    animation-duration: 6s;
    animation-direction: reverse;
}

.orbit-3 {
    width: 160px;
    height: 160px;
    margin: -80px 0 0 -80px;
    animation-duration: 8s;
}

@keyframes orbitRotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.preview-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    margin-top: 20px;
    font-weight: 500;
}

/* 🧬 EXISTENCE DNA */
.nexus-dna-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(135deg, #0f0520 0%, #1a0830 50%, #0f0520 100%);
    overflow: hidden;
    min-height: 600px;
}

.dna-void {
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at center, transparent 0%, rgba(0, 0, 0, 0.5) 100%);
}

.dna-helix-container {
    position: absolute;
    left: 10%;
    top: 50%;
    transform: translateY(-50%);
    height: 400px;
    width: 200px;
}

.dna-helix {
    position: relative;
    height: 100%;
    animation: helixRotate 10s linear infinite;
    transform-style: preserve-3d;
}

@keyframes helixRotate {
    0% { transform: rotateY(0deg); }
    100% { transform: rotateY(360deg); }
}

.dna-strand {
    position: absolute;
    top: 0;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-around;
}

.strand-left {
    left: 0;
}

.strand-right {
    right: 0;
}

.dna-node {
    width: 60px;
    height: 40px;
    background: rgba(139, 92, 246, 0.3);
    border: 1px solid rgba(139, 92, 246, 0.6);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: nodeFloat 3s ease-in-out infinite;
    animation-delay: calc(var(--i) * 0.2s);
    backdrop-filter: blur(5px);
}

.strand-right .dna-node {
    background: rgba(6, 182, 212, 0.3);
    border-color: rgba(6, 182, 212, 0.6);
}

@keyframes nodeFloat {
    0%, 100% { transform: translateX(0); }
    50% { transform: translateX(10px); }
}

.strand-right .dna-node {
    animation-name: nodeFloatReverse;
}

@keyframes nodeFloatReverse {
    0%, 100% { transform: translateX(0); }
    50% { transform: translateX(-10px); }
}

.dna-node span {
    color: #fff;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.dna-connections {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-around;
}

.dna-bridge {
    width: 80px;
    height: 2px;
    background: linear-gradient(90deg, rgba(139, 92, 246, 0.6), rgba(6, 182, 212, 0.6));
    animation: bridgePulse 2s ease-in-out infinite;
    animation-delay: calc(var(--i) * 0.15s);
}

@keyframes bridgePulse {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 1; }
}

.dna-content {
    position: relative;
    z-index: 10;
    text-align: center;
    max-width: 500px;
    margin-left: auto;
    margin-right: 10%;
}

.dna-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 20px;
}

.dna-text {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.1rem;
    line-height: 1.8;
    margin-bottom: 30px;
}

.dna-btn {
    padding: 14px 35px;
    background: linear-gradient(135deg, #8b5cf6, #06b6d4);
    border: none;
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.dna-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4);
}

/* ⚫ SINGULARITY CORE */
.nexus-singularity-section {
    position: relative;
    padding: 120px 20px;
    background: #000;
    overflow: hidden;
    min-height: 600px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.singularity-void {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at center, #0a0a0a 0%, #000 100%);
}

.singularity-accretion {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.accretion-ring {
    position: absolute;
    border-radius: 50%;
    border: 2px solid transparent;
    animation: accretionSpin linear infinite;
}

.ring-1 {
    width: 300px;
    height: 300px;
    margin: -150px 0 0 -150px;
    border-top-color: rgba(255, 100, 50, 0.6);
    border-right-color: rgba(255, 150, 50, 0.3);
    animation-duration: 8s;
}

.ring-2 {
    width: 400px;
    height: 400px;
    margin: -200px 0 0 -200px;
    border-top-color: rgba(255, 200, 100, 0.4);
    border-left-color: rgba(255, 150, 50, 0.2);
    animation-duration: 12s;
    animation-direction: reverse;
}

.ring-3 {
    width: 500px;
    height: 500px;
    margin: -250px 0 0 -250px;
    border-bottom-color: rgba(255, 100, 50, 0.2);
    animation-duration: 16s;
}

@keyframes accretionSpin {
    0% { transform: rotate(0deg) rotateX(75deg); }
    100% { transform: rotate(360deg) rotateX(75deg); }
}

.singularity-core {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.event-horizon {
    width: 100px;
    height: 100px;
    background: radial-gradient(circle, transparent 40%, rgba(139, 92, 246, 0.3) 60%, transparent 70%);
    border-radius: 50%;
    animation: horizonPulse 3s ease-in-out infinite;
}

@keyframes horizonPulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.7; }
}

.singularity-point {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 20px;
    height: 20px;
    background: #000;
    border-radius: 50%;
    box-shadow:
        0 0 20px #000,
        0 0 40px rgba(139, 92, 246, 0.5),
        inset 0 0 20px rgba(139, 92, 246, 0.3);
}

.singularity-content {
    position: absolute;
    bottom: 80px;
    left: 50%;
    transform: translateX(-50%);
    text-align: center;
    z-index: 10;
}

.singularity-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 15px;
}

.singularity-text {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1rem;
    max-width: 500px;
    margin: 0 auto 30px;
}

.singularity-stats {
    display: flex;
    justify-content: center;
    gap: 40px;
    margin-bottom: 30px;
}

.sing-stat {
    text-align: center;
}

.stat-symbol {
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
    display: block;
    margin-bottom: 5px;
}

.stat-name {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    text-transform: uppercase;
}

.singularity-btn {
    padding: 14px 35px;
    background: transparent;
    border: 2px solid rgba(255, 100, 50, 0.6);
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.singularity-btn:hover {
    background: rgba(255, 100, 50, 0.2);
    box-shadow: 0 0 30px rgba(255, 100, 50, 0.4);
}

/* 🌅 CREATION FORGE */
.nexus-forge-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #1a0a0a 0%, #2a1515 50%, #1a0a0a 100%);
    overflow: hidden;
}

.forge-cosmos {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 50% 30%, rgba(255, 150, 50, 0.15) 0%, transparent 40%),
        radial-gradient(circle at 50% 70%, rgba(255, 100, 50, 0.1) 0%, transparent 30%);
}

.forge-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 60px;
    margin-bottom: 60px;
    flex-wrap: wrap;
    position: relative;
    z-index: 10;
}

.forge-crucible {
    position: relative;
    width: 250px;
    height: 250px;
}

.crucible-glow {
    position: absolute;
    inset: -20px;
    background: radial-gradient(circle, rgba(255, 150, 50, 0.4) 0%, transparent 70%);
    animation: crucibleGlow 3s ease-in-out infinite;
}

@keyframes crucibleGlow {
    0%, 100% { opacity: 0.5; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.1); }
}

.crucible-core {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 150px;
    height: 150px;
    background: radial-gradient(circle, #ff9030 0%, #ff5020 50%, #aa2010 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow:
        0 0 50px rgba(255, 100, 50, 0.6),
        inset 0 0 50px rgba(255, 200, 100, 0.3);
}

.forming-universe {
    width: 50px;
    height: 50px;
    background: radial-gradient(circle, #fff 0%, #ffe0a0 50%, transparent 70%);
    border-radius: 50%;
    animation: formPulse 2s ease-in-out infinite;
}

@keyframes formPulse {
    0%, 100% { transform: scale(0.8); opacity: 0.6; }
    50% { transform: scale(1.2); opacity: 1; }
}

.universe-spark {
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, #fff 0%, transparent 70%);
    border-radius: 50%;
    animation: sparkFlicker 0.5s ease-in-out infinite;
}

@keyframes sparkFlicker {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.crucible-ring {
    position: absolute;
    inset: 10px;
    border: 3px solid rgba(255, 200, 100, 0.4);
    border-radius: 50%;
    animation: ringRotate 10s linear infinite;
}

@keyframes ringRotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.forge-controls {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.forge-ingredient {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 20px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 150, 50, 0.2);
    border-radius: 12px;
    min-width: 280px;
}

.ingredient-icon {
    font-size: 1.5rem;
}

.ingredient-name {
    color: rgba(255, 255, 255, 0.8);
    font-weight: 500;
    flex: 1;
}

.ingredient-bar {
    width: 80px;
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    overflow: hidden;
}

.ingredient-bar .bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #ff9030, #ff5020);
    border-radius: 3px;
}

.forge-content {
    text-align: center;
    position: relative;
    z-index: 10;
}

.forge-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 15px;
}

.forge-text {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.1rem;
    max-width: 500px;
    margin: 0 auto 30px;
}

.forge-btn {
    padding: 16px 40px;
    background: linear-gradient(135deg, #ff9030, #ff5020);
    border: none;
    color: #fff;
    font-size: 1.1rem;
    font-weight: 700;
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.forge-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 40px rgba(255, 100, 50, 0.5);
}

/* 👁️ OMNISCIENT VIEW */
.nexus-omniscient-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #0a0a20 0%, #15102a 50%, #0a0a20 100%);
    overflow: hidden;
    min-height: 600px;
}

.omni-void {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at center, rgba(139, 92, 246, 0.1) 0%, transparent 50%);
}

.omni-eye {
    position: relative;
    display: flex;
    justify-content: center;
    margin-bottom: 60px;
}

.eye-outer {
    position: relative;
    width: 250px;
    height: 250px;
}

.eye-rays {
    position: absolute;
    inset: 0;
}

.eye-rays .ray {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 150px;
    height: 2px;
    background: linear-gradient(90deg, rgba(139, 92, 246, 0.6) 0%, transparent 100%);
    transform-origin: left center;
    transform: rotate(var(--angle));
    animation: rayPulse 3s ease-in-out infinite;
}

@keyframes rayPulse {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 1; }
}

.eye-iris {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 150px;
    height: 150px;
    background: radial-gradient(circle, #6366f1 0%, #4f46e5 50%, #3730a3 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 50px rgba(99, 102, 241, 0.5);
}

.iris-pattern {
    position: absolute;
    inset: 10px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    animation: irisRotate 20s linear infinite;
}

.iris-pattern::before {
    content: '';
    position: absolute;
    inset: 10px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}

@keyframes irisRotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.eye-pupil {
    width: 60px;
    height: 60px;
    background: #000;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.8);
}

.pupil-galaxies {
    width: 30px;
    height: 30px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
    border-radius: 50%;
    animation: galaxySpin 5s linear infinite;
}

@keyframes galaxySpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.omni-visions {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin-bottom: 50px;
    flex-wrap: wrap;
    position: relative;
    z-index: 10;
}

.vision-card {
    padding: 25px 35px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 16px;
    text-align: center;
    animation: visionFloat 4s ease-in-out infinite;
    animation-delay: var(--delay);
    backdrop-filter: blur(10px);
}

@keyframes visionFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.vision-time {
    display: block;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 10px;
}

.vision-icon {
    font-size: 2.5rem;
}

.omni-content {
    text-align: center;
    position: relative;
    z-index: 10;
}

.omni-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 15px;
}

.omni-text {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.1rem;
    max-width: 500px;
    margin: 0 auto;
}

/* 🔱 PRIMORDIAL FORCES */
.nexus-primordial-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(135deg, #0a1020 0%, #0f1a30 50%, #0a1020 100%);
    overflow: hidden;
}

.primordial-void {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 20% 50%, rgba(59, 130, 246, 0.1) 0%, transparent 30%),
        radial-gradient(circle at 80% 50%, rgba(239, 68, 68, 0.1) 0%, transparent 30%);
}

.primordial-header {
    text-align: center;
    margin-bottom: 60px;
    position: relative;
    z-index: 10;
}

.primordial-badge {
    display: inline-block;
    padding: 8px 20px;
    background: rgba(59, 130, 246, 0.2);
    border: 1px solid rgba(59, 130, 246, 0.4);
    border-radius: 30px;
    color: #60a5fa;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 20px;
}

.primordial-title {
    font-size: 2.8rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 15px;
}

.primordial-subtitle {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.1rem;
    max-width: 500px;
    margin: 0 auto;
}

.forces-container {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
    max-width: 1200px;
    margin: 0 auto;
    position: relative;
    z-index: 10;
}

.force-card {
    padding: 30px 20px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    text-align: center;
    transition: all 0.4s ease;
    backdrop-filter: blur(10px);
}

.force-card:hover {
    transform: translateY(-10px);
    border-color: rgba(255, 255, 255, 0.2);
}

.force-visual {
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

.gravity-well {
    width: 60px;
    height: 60px;
    background: conic-gradient(from 0deg, transparent, rgba(59, 130, 246, 0.5), transparent);
    border-radius: 50%;
    animation: wellSpin 4s linear infinite;
}

@keyframes wellSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.gravity-object {
    position: absolute;
    width: 8px;
    height: 8px;
    background: #60a5fa;
    border-radius: 50%;
    box-shadow: 0 0 10px #60a5fa;
}

.obj-1 { animation: orbitGravity 3s linear infinite; }
.obj-2 { animation: orbitGravity 3s linear infinite 1.5s; }

@keyframes orbitGravity {
    0% { transform: rotate(0deg) translateX(40px); }
    100% { transform: rotate(360deg) translateX(40px); }
}

.em-field {
    position: relative;
    width: 80px;
    height: 40px;
}

.em-wave {
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, transparent, #fbbf24, transparent);
    animation: waveOscillate 1s ease-in-out infinite;
}

@keyframes waveOscillate {
    0%, 100% { transform: scaleY(1); }
    25% { transform: scaleY(3) translateY(-5px); }
    75% { transform: scaleY(3) translateY(5px); }
}

.strong-nucleus {
    position: relative;
    width: 50px;
    height: 50px;
}

.quark {
    position: absolute;
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.q1 { background: #ef4444; top: 0; left: 50%; transform: translateX(-50%); }
.q2 { background: #22c55e; bottom: 5px; left: 5px; }
.q3 { background: #3b82f6; bottom: 5px; right: 5px; }

.gluon-field {
    position: absolute;
    inset: -5px;
    border: 2px dashed rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    animation: gluonSpin 2s linear infinite;
}

@keyframes gluonSpin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.weak-decay {
    position: relative;
}

.decay-particle {
    width: 20px;
    height: 20px;
    background: #a855f7;
    border-radius: 50%;
    animation: decayPulse 2s ease-in-out infinite;
}

@keyframes decayPulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

.decay-products {
    position: absolute;
    top: 50%;
    left: 100%;
    width: 30px;
    height: 2px;
    background: linear-gradient(90deg, #a855f7, transparent);
    transform: translateY(-50%);
}

.force-name {
    font-size: 1.2rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 10px;
}

.force-desc {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
    margin-bottom: 15px;
}

.force-strength {
    display: inline-block;
    padding: 5px 12px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 20px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.8rem;
    font-family: monospace;
}

/* 📖 AKASHIC RECORDS */
.nexus-akashic-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #0a0815 0%, #150a20 50%, #0a0815 100%);
    overflow: hidden;
}

.akashic-void {
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at center, rgba(139, 92, 246, 0.08) 0%, transparent 60%);
}

.akashic-library {
    position: relative;
    display: flex;
    justify-content: center;
    margin-bottom: 50px;
    perspective: 1000px;
}

.library-shelves {
    display: flex;
    flex-direction: column;
    gap: 15px;
    transform: rotateX(10deg);
}

.shelf-row {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.akashic-book {
    width: 30px;
    height: 80px;
    background: linear-gradient(180deg,
        hsl(var(--hue), 70%, 50%) 0%,
        hsl(var(--hue), 70%, 30%) 100%);
    border-radius: 2px 4px 4px 2px;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.3);
    animation: bookGlow 3s ease-in-out infinite;
    animation-delay: calc(var(--hue) * 10ms);
}

@keyframes bookGlow {
    0%, 100% { box-shadow: 2px 0 5px rgba(0, 0, 0, 0.3); }
    50% { box-shadow: 2px 0 15px rgba(139, 92, 246, 0.5); }
}

.akashic-glow {
    position: absolute;
    inset: -50px;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.2) 0%, transparent 70%);
    pointer-events: none;
}

.akashic-content {
    text-align: center;
    position: relative;
    z-index: 10;
    max-width: 600px;
    margin: 0 auto;
}

.akashic-symbol {
    font-size: 4rem;
    margin-bottom: 20px;
    animation: symbolFloat 4s ease-in-out infinite;
}

@keyframes symbolFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}

.akashic-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 15px;
}

.akashic-text {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.1rem;
    line-height: 1.8;
    margin-bottom: 30px;
}

.akashic-search {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.akashic-input {
    padding: 14px 24px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 30px;
    color: #fff;
    font-size: 1rem;
    width: 300px;
    outline: none;
    transition: all 0.3s ease;
}

.akashic-input:focus {
    border-color: rgba(139, 92, 246, 0.6);
    box-shadow: 0 0 20px rgba(139, 92, 246, 0.2);
}

.akashic-input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.akashic-btn {
    padding: 14px 30px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border: none;
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.akashic-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4);
}

.akashic-categories {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.akashic-tag {
    padding: 8px 16px;
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 20px;
    color: #a78bfa;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.akashic-tag:hover {
    background: rgba(139, 92, 246, 0.2);
}

/* ⏳ ETERNITY CLOCK */
.nexus-eternity-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #0a0a15 0%, #101025 50%, #0a0a15 100%);
    overflow: hidden;
    min-height: 600px;
}

.eternity-void {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at center, rgba(251, 191, 36, 0.05) 0%, transparent 50%);
}

.eternity-clock {
    position: relative;
    width: 300px;
    height: 300px;
    margin: 0 auto 60px;
}

.clock-outer-ring {
    position: absolute;
    inset: 0;
    border: 3px solid rgba(251, 191, 36, 0.3);
    border-radius: 50%;
}

.eon-marker {
    position: absolute;
    top: 50%;
    left: 50%;
    transform-origin: center;
    transform: rotate(var(--angle)) translateY(-140px);
}

.eon-marker span {
    display: block;
    transform: rotate(calc(-1 * var(--angle)));
    color: rgba(251, 191, 36, 0.8);
    font-size: 1.5rem;
    font-weight: 700;
}

.clock-inner-ring {
    position: absolute;
    inset: 30px;
    border: 1px solid rgba(251, 191, 36, 0.15);
    border-radius: 50%;
}

.clock-face {
    position: absolute;
    inset: 50px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 50%;
}

.clock-hand {
    position: absolute;
    bottom: 50%;
    left: 50%;
    transform-origin: bottom center;
    background: linear-gradient(to top, #fbbf24, #f59e0b);
    border-radius: 2px;
}

.hand-eon {
    width: 4px;
    height: 60px;
    margin-left: -2px;
    animation: handRotate 60s linear infinite;
}

.hand-era {
    width: 3px;
    height: 80px;
    margin-left: -1.5px;
    animation: handRotate 30s linear infinite;
    opacity: 0.7;
}

.hand-epoch {
    width: 2px;
    height: 90px;
    margin-left: -1px;
    animation: handRotate 10s linear infinite;
    opacity: 0.5;
}

@keyframes handRotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.clock-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.center-gem {
    width: 20px;
    height: 20px;
    background: radial-gradient(circle, #fbbf24 0%, #b45309 100%);
    border-radius: 50%;
    box-shadow: 0 0 20px rgba(251, 191, 36, 0.6);
}

.eternity-content {
    text-align: center;
    position: relative;
    z-index: 10;
}

.eternity-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 15px;
}

.eternity-text {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.1rem;
    max-width: 500px;
    margin: 0 auto 40px;
}

.eternity-readings {
    display: flex;
    justify-content: center;
    gap: 40px;
    flex-wrap: wrap;
}

.reading {
    text-align: center;
}

.reading-label {
    display: block;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
}

.reading-value {
    font-size: 2rem;
    font-weight: 800;
    color: #fbbf24;
}

/* 🎭 CONSCIOUSNESS MATRIX */
.nexus-consciousness-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #050510 0%, #0a0a20 50%, #050510 100%);
    overflow: hidden;
    min-height: 600px;
}

.consciousness-void {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at center, rgba(139, 92, 246, 0.05) 0%, transparent 50%);
}

.consciousness-grid {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.thought-node {
    position: absolute;
    left: var(--x);
    top: var(--y);
    width: 12px;
    height: 12px;
    background: radial-gradient(circle, #a78bfa 0%, transparent 70%);
    border-radius: 50%;
    animation: nodeThink 4s ease-in-out infinite;
    animation-delay: var(--delay);
}

@keyframes nodeThink {
    0%, 100% { opacity: 0.3; transform: scale(1); }
    50% { opacity: 1; transform: scale(1.5); }
}

.thought-connection {
    position: absolute;
    left: var(--x1);
    top: var(--y1);
    width: 1px;
    height: 100px;
    background: linear-gradient(180deg, rgba(167, 139, 250, 0.5), transparent);
    transform-origin: top left;
    opacity: 0.3;
}

.consciousness-center {
    position: relative;
    display: flex;
    justify-content: center;
    margin-bottom: 60px;
    z-index: 10;
}

.awareness-sphere {
    position: relative;
    width: 200px;
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sphere-layer {
    position: absolute;
    border-radius: 50%;
    border: 1px solid rgba(139, 92, 246, 0.3);
    animation: sphereExpand 4s ease-in-out infinite;
}

.layer-1 {
    inset: 30px;
    animation-delay: 0s;
}

.layer-2 {
    inset: 15px;
    animation-delay: 0.3s;
}

.layer-3 {
    inset: 0;
    animation-delay: 0.6s;
}

@keyframes sphereExpand {
    0%, 100% { opacity: 0.3; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.1); }
}

.sphere-core {
    font-size: 1.5rem;
    font-weight: 800;
    color: #fff;
    text-shadow: 0 0 30px rgba(139, 92, 246, 0.8);
    animation: coreGlow 3s ease-in-out infinite;
}

@keyframes coreGlow {
    0%, 100% { opacity: 0.8; }
    50% { opacity: 1; text-shadow: 0 0 50px rgba(139, 92, 246, 1); }
}

.consciousness-content {
    text-align: center;
    position: relative;
    z-index: 10;
}

.consciousness-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 15px;
}

.consciousness-text {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.1rem;
    max-width: 500px;
    margin: 0 auto 40px;
}

.awareness-levels {
    display: flex;
    justify-content: center;
    gap: 15px;
    flex-wrap: wrap;
}

.level {
    padding: 10px 20px;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.level-physical {
    background: rgba(239, 68, 68, 0.2);
    border: 1px solid rgba(239, 68, 68, 0.4);
    color: #f87171;
}

.level-mental {
    background: rgba(59, 130, 246, 0.2);
    border: 1px solid rgba(59, 130, 246, 0.4);
    color: #60a5fa;
}

.level-spiritual {
    background: rgba(139, 92, 246, 0.2);
    border: 1px solid rgba(139, 92, 246, 0.4);
    color: #a78bfa;
}

.level-cosmic {
    background: rgba(251, 191, 36, 0.2);
    border: 1px solid rgba(251, 191, 36, 0.4);
    color: #fbbf24;
}

/* 💫 BIG BANG BUTTON */
.nexus-bigbang-section {
    position: relative;
    padding: 120px 20px;
    background: #000;
    overflow: hidden;
    min-height: 600px;
}

.bigbang-void {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at center, #050505 0%, #000 100%);
}

.bigbang-container {
    position: relative;
    z-index: 10;
    text-align: center;
    margin-bottom: 60px;
}

.bigbang-prelude {
    position: relative;
    width: 200px;
    height: 200px;
    margin: 0 auto 40px;
}

.prelude-particles {
    position: absolute;
    inset: 0;
}

.prelude-particle {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 4px;
    height: 4px;
    background: #fff;
    border-radius: 50%;
    animation: particleOrbit 3s linear infinite;
    animation-delay: var(--delay);
}

@keyframes particleOrbit {
    0% {
        transform: rotate(var(--angle)) translateX(30px);
        opacity: 0;
    }
    50% {
        opacity: 1;
    }
    100% {
        transform: rotate(calc(var(--angle) + 360deg)) translateX(80px);
        opacity: 0;
    }
}

.singularity-seed {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 20px;
    height: 20px;
    background: radial-gradient(circle, #fff 0%, #fbbf24 50%, #000 100%);
    border-radius: 50%;
    box-shadow: 0 0 30px rgba(251, 191, 36, 0.8);
    animation: seedPulse 2s ease-in-out infinite;
}

@keyframes seedPulse {
    0%, 100% { transform: translate(-50%, -50%) scale(1); }
    50% { transform: translate(-50%, -50%) scale(1.3); }
}

.bigbang-btn {
    display: inline-flex;
    align-items: center;
    gap: 15px;
    padding: 20px 50px;
    background: linear-gradient(135deg, #ff6b35, #f7931e, #ffd700);
    border: none;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.4s ease;
    animation: btnGlow 2s ease-in-out infinite;
}

@keyframes btnGlow {
    0%, 100% { box-shadow: 0 0 30px rgba(255, 107, 53, 0.5); }
    50% { box-shadow: 0 0 60px rgba(255, 215, 0, 0.8); }
}

.bigbang-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 0 80px rgba(255, 215, 0, 1);
}

.bigbang-btn .btn-text {
    font-size: 1.3rem;
    font-weight: 800;
    color: #000;
    letter-spacing: 2px;
}

.bigbang-btn .btn-icon {
    font-size: 1.5rem;
}

.bigbang-warning {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.85rem;
    margin-top: 20px;
}

.bigbang-content {
    text-align: center;
    position: relative;
    z-index: 10;
}

.bigbang-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 15px;
}

.bigbang-text {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.1rem;
    max-width: 550px;
    margin: 0 auto;
    line-height: 1.8;
}

/* 🌌 COSMIC WEB */
.nexus-cosmicweb-section {
    position: relative;
    padding: 100px 20px;
    background: linear-gradient(180deg, #000510 0%, #001030 50%, #000510 100%);
    overflow: hidden;
}

.cosmicweb-void {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 30% 40%, rgba(6, 182, 212, 0.1) 0%, transparent 30%),
        radial-gradient(circle at 70% 60%, rgba(139, 92, 246, 0.1) 0%, transparent 30%);
}

.cosmicweb-structure {
    position: relative;
    max-width: 600px;
    margin: 0 auto 50px;
}

.web-svg {
    width: 100%;
    height: auto;
}

.web-filament {
    fill: none;
    stroke: url(#webGradient);
    stroke-width: 2;
    stroke-linecap: round;
    opacity: 0.6;
    animation: filamentPulse 4s ease-in-out infinite;
}

@keyframes filamentPulse {
    0%, 100% { opacity: 0.4; }
    50% { opacity: 0.8; }
}

.web-node {
    fill: url(#webGradient);
    animation: nodePulse 3s ease-in-out infinite;
}

@keyframes nodePulse {
    0%, 100% { opacity: 0.6; r: attr(r); }
    50% { opacity: 1; }
}

.web-glow {
    position: absolute;
    inset: -50px;
    background: radial-gradient(circle at center, rgba(139, 92, 246, 0.1) 0%, transparent 60%);
    pointer-events: none;
}

.cosmicweb-content {
    text-align: center;
    position: relative;
    z-index: 10;
}

.cosmicweb-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 15px;
}

.cosmicweb-text {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.1rem;
    max-width: 550px;
    margin: 0 auto 40px;
}

.web-stats {
    display: flex;
    justify-content: center;
    gap: 50px;
    flex-wrap: wrap;
}

.web-stat {
    text-align: center;
}

.web-stat .stat-value {
    font-size: 2.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, #8b5cf6, #06b6d4);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: block;
    margin-bottom: 5px;
}

.web-stat .stat-label {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
}

/* OMNIVERSE RESPONSIVE STYLES */
@media (max-width: 1024px) {
    .law-editor-container {
        grid-template-columns: 1fr;
    }

    .forces-container {
        grid-template-columns: repeat(2, 1fr);
    }

    .dna-helix-container {
        position: relative;
        left: auto;
        transform: none;
        margin: 0 auto 40px;
    }

    .dna-content {
        margin: 0 auto;
        text-align: center;
    }
}

@media (max-width: 768px) {
    .void-title,
    .law-title,
    .dna-title,
    .singularity-title,
    .forge-title,
    .omni-title,
    .primordial-title,
    .akashic-title,
    .eternity-title,
    .consciousness-title,
    .bigbang-title,
    .cosmicweb-title {
        font-size: 2rem;
    }

    .void-symbol {
        font-size: 5rem;
    }

    .void-emergence-cards {
        flex-direction: column;
        align-items: center;
    }

    .law-item {
        grid-template-columns: 40px 1fr;
        gap: 15px;
    }

    .law-slider {
        grid-column: span 2;
    }

    .forces-container {
        grid-template-columns: 1fr;
    }

    .singularity-stats {
        gap: 20px;
    }

    .forge-container {
        flex-direction: column;
    }

    .forge-ingredient {
        min-width: auto;
        width: 100%;
        max-width: 300px;
    }

    .eternity-clock {
        width: 250px;
        height: 250px;
    }

    .eon-marker {
        transform: rotate(var(--angle)) translateY(-115px);
    }

    .web-stats {
        gap: 30px;
    }

    .bigbang-btn {
        padding: 16px 35px;
    }

    .bigbang-btn .btn-text {
        font-size: 1.1rem;
    }
}

@media (max-width: 480px) {
    .emergence-card {
        width: 100%;
        max-width: 200px;
    }

    .akashic-search {
        flex-direction: column;
        align-items: center;
    }

    .akashic-input {
        width: 100%;
        max-width: 280px;
    }

    .awareness-levels {
        flex-direction: column;
        align-items: center;
    }

    .eternity-readings {
        flex-direction: column;
        gap: 20px;
    }
}
</style>

<div class="nexus-cms-page">
    <div class="nexus-cms-inner">
        <div class="nexus-cms-content">
            <?php
            // Output the page content (HTML from GrapesJS builder)
            echo $pageContent;
            ?>
        </div>
    </div>
</div>

<script>
// Premium Glassmorphism Animations
document.addEventListener('DOMContentLoaded', function() {
    // Initial fade-in animation
    const content = document.querySelector('.nexus-cms-content');
    if (content) {
        content.style.opacity = '0';
        content.style.transform = 'translateY(30px)';
        content.style.transition = 'opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1), transform 0.6s cubic-bezier(0.4, 0, 0.2, 1)';

        requestAnimationFrame(() => {
            content.style.opacity = '1';
            content.style.transform = 'translateY(0)';
        });
    }

    // Intersection Observer for scroll animations
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };

    const animateOnScroll = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('nexus-visible');
                animateOnScroll.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Animate sections on scroll
    document.querySelectorAll('.nexus-section, .nexus-card, .nexus-testimonial-card, .nexus-faq-item, .nexus-team-member').forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = `opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1) ${index * 0.05}s, transform 0.6s cubic-bezier(0.4, 0, 0.2, 1) ${index * 0.05}s`;
        animateOnScroll.observe(el);
    });

    // Add class to trigger animation
    const style = document.createElement('style');
    style.textContent = `
        .nexus-visible {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }
    `;
    document.head.appendChild(style);

    // Parallax effect for hero section
    const hero = document.querySelector('.nexus-hero');
    if (hero) {
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const rate = scrolled * 0.3;
            hero.style.transform = `translateY(${rate}px)`;
        }, { passive: true });
    }

    // Magnetic effect on buttons
    document.querySelectorAll('.nexus-btn-primary').forEach(btn => {
        btn.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left - rect.width / 2;
            const y = e.clientY - rect.top - rect.height / 2;
            this.style.transform = `translate(${x * 0.1}px, ${y * 0.1}px)`;
        });

        btn.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });

    // Stats counter animation
    document.querySelectorAll('.nexus-stat-number').forEach(stat => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = entry.target;
                    const text = target.textContent;
                    const match = text.match(/(\d+)/);
                    if (match) {
                        const num = parseInt(match[0]);
                        const suffix = text.replace(match[0], '');
                        let current = 0;
                        const increment = num / 50;
                        const timer = setInterval(() => {
                            current += increment;
                            if (current >= num) {
                                target.textContent = text;
                                clearInterval(timer);
                            } else {
                                target.textContent = Math.floor(current) + suffix;
                            }
                        }, 20);
                    }
                    observer.unobserve(target);
                }
            });
        }, { threshold: 0.5 });
        observer.observe(stat);
    });
});
</script>

<?php require __DIR__ . '/../layouts/' . $activeLayout . '/footer.php'; ?>

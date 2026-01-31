<!-- Critical CSS - Inline for Instant Rendering -->
<!-- Optimized v2.0 - Path to 100/100 -->
<style>
/* ==========================================
   DESIGN TOKENS - Foundation
   ========================================== */
:root{
    /* Spacing Scale - 8px base */
    --space-1:4px;--space-2:8px;--space-3:12px;--space-4:16px;
    --space-5:20px;--space-6:24px;--space-8:32px;--space-12:48px;

    /* Layout Dimensions - Prevents CLS */
    --layout-header-height:56px;
    --layout-header-height-scrolled:48px;
    --layout-mobile-nav-height:84px;
    --layout-sidebar-width:340px;
    --layout-max-content-width:1200px;

    /* Colors */
    --color-primary-500:#6366f1;
    --color-primary-600:#4f46e5;
    --color-gray-50:#f9fafb;
    --color-gray-900:#111827;

    /* Glassmorphism */
    --glass-blur:24px;
    --glass-saturation:200%;
    --glass-bg-light:rgba(255,255,255,0.7);
    --glass-bg-dark:rgba(30,41,59,0.7);
    --glass-border-light:rgba(255,255,255,0.2);
    --glass-border-dark:rgba(255,255,255,0.1);

    /* Transitions */
    --transition-fast:150ms cubic-bezier(0.4,0,0.2,1);
    --transition-base:300ms cubic-bezier(0.4,0,0.2,1);

    /* Radius */
    --radius-md:12px;--radius-lg:16px;--radius-xl:20px;--radius-full:9999px;

    /* Z-Index */
    --z-fixed:1200;--z-modal:1400;

    /* Typography */
    --font-family-primary:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;

    /* Breakpoints */
    --breakpoint-xs:320px;--breakpoint-sm:375px;--breakpoint-md:425px;
    --breakpoint-lg:768px;--breakpoint-xl:1024px;
    --breakpoint-2xl:1200px;--breakpoint-3xl:1440px;--breakpoint-4xl:1920px;
}

/* Dark Theme Tokens */
[data-theme="dark"]{
    --color-gray-50:#111827;
    --color-gray-900:#f9fafb;
}

/* ==========================================
   BASE RESET
   ========================================== */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{-webkit-text-size-adjust:100%;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;text-rendering:optimizeLegibility}
body{font-family:var(--font-family-primary);font-size:16px;line-height:1.6;color:var(--color-gray-900);background:var(--color-gray-50);overflow-x:hidden}
[data-theme="dark"] body{background:#0f172a;color:#f9fafb}

/* ==========================================
   LAYOUT STRUCTURE - Prevents CLS
   ========================================== */
.layout-container{min-height:100vh;display:flex;flex-direction:column}
main,.main-content{margin-top:0;flex:1;width:100%}

/* Desktop header clearance - utility bar (56px) + navbar (64px) + spacing (16px) = 136px */
@media(min-width:1025px){
.htb-container,.htb-container-full{padding-top:136px !important}
}
/* Home page content offset - highest specificity */
.home-content-offset{padding-top:136px !important}

/* Grid Layout - Pre-defined */
.post-box-grid,.home-two-column-grid{
    display:grid;
    grid-template-columns:1fr var(--layout-sidebar-width);
    gap:var(--space-6);
    max-width:var(--layout-max-content-width);
    margin:0 auto;
    padding:var(--space-5);
}

@media(max-width:1024px){
    .post-box-grid,.home-two-column-grid{grid-template-columns:1fr 300px}
}

@media(max-width:768px){
    main,.main-content{padding-bottom:calc(var(--layout-mobile-nav-height) + 16px)}
    .post-box-grid,.home-two-column-grid{grid-template-columns:1fr;gap:var(--space-3);padding:var(--space-3)}
}

/* ==========================================
   HEADER - Fixed dimensions
   ========================================== */
.nexus-modern-header{
    position:fixed;top:0;left:0;right:0;
    height:var(--layout-header-height);
    background:var(--glass-bg-dark);
    backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-saturation));
    border-bottom:1px solid rgba(99,102,241,0.12);
    z-index:var(--z-fixed);
    transform:translateZ(0);
    will-change:transform,height;
    transition:height var(--transition-base);
}
[data-theme="light"] .nexus-modern-header{background:var(--glass-bg-light)}
.nexus-modern-header.scrolled{height:var(--layout-header-height-scrolled)}

/* ==========================================
   SKELETON LOADING - Zero CLS
   ========================================== */
@keyframes skeleton-shimmer{
    0%{background-position:-1000px 0}
    100%{background-position:1000px 0}
}
.skeleton{
    background:linear-gradient(90deg,rgba(255,255,255,0.05) 0%,rgba(255,255,255,0.1) 50%,rgba(255,255,255,0.05) 100%);
    background-size:2000px 100%;
    animation:skeleton-shimmer 2s infinite linear;
    border-radius:var(--radius-md);
}
[data-theme="light"] .skeleton{
    background:linear-gradient(90deg,#e5e7eb 0%,#f3f4f6 50%,#e5e7eb 100%);
    background-size:2000px 100%;
}

.skeleton-feed-item{
    background:var(--glass-bg-dark);
    backdrop-filter:blur(12px);
    border-radius:var(--radius-xl);
    padding:var(--space-4);
    margin-bottom:var(--space-4);
    min-height:200px;
}
[data-theme="light"] .skeleton-feed-item{background:var(--glass-bg-light)}

.skeleton-container{opacity:1;transition:opacity var(--transition-base)}
.skeleton-container.hydrated{opacity:0;pointer-events:none;position:absolute}
.actual-content{opacity:0;transition:opacity var(--transition-base)}
.actual-content.hydrated{opacity:1}

/* ==========================================
   ACCESSIBILITY
   ========================================== */
.skip-link{position:absolute;top:-40px;left:0;background:#000;color:#fff;padding:8px 16px;text-decoration:none;z-index:10000;border-radius:0 0 4px 0;font-weight:600;transition:top .3s}
.skip-link:focus{top:0;outline:3px solid var(--color-primary-500);outline-offset:2px}
*:focus-visible{outline:2px solid var(--color-primary-500);outline-offset:2px;border-radius:4px}
*:focus:not(:focus-visible){outline:none}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border-width:0}

/* ==========================================
   GLASS CARD - Common component
   ========================================== */
.glass-card{
    background:var(--glass-bg-light);
    backdrop-filter:blur(var(--glass-blur)) saturate(var(--glass-saturation));
    border:1px solid var(--glass-border-light);
    border-radius:var(--radius-xl);
    padding:var(--space-5);
    transition:all var(--transition-base);
}
[data-theme="dark"] .glass-card{background:var(--glass-bg-dark);border-color:var(--glass-border-dark)}
.glass-card:hover{box-shadow:0 8px 32px rgba(31,38,135,0.37);transform:translateY(-2px)}

/* ==========================================
   MOBILE NAVIGATION - Bottom Tab Bar
   ========================================== */
/* Hide on desktop */
.mobile-tab-bar{display:none}

/* Show on mobile/tablet (up to 1024px) */
@media(max-width:1024px){
    .mobile-tab-bar{
        display:flex !important;
        position:fixed;bottom:0;left:0;right:0;
        z-index:99999;
        height:84px;
        padding-bottom:env(safe-area-inset-bottom,0px);
        background:rgba(255,255,255,0.95);
        backdrop-filter:blur(20px) saturate(180%);
        -webkit-backdrop-filter:blur(20px) saturate(180%);
        border-top:1px solid rgba(0,0,0,0.1);
        align-items:flex-start;
        transform:translateZ(0);
    }
    [data-theme="dark"] .mobile-tab-bar{background:rgba(28,28,30,0.95);border-top-color:rgba(255,255,255,0.1)}
    body{padding-bottom:90px !important}
}

/* ==========================================
   FEED ITEMS - Critical for LCP
   ========================================== */
.feed-item,.fds-feed-item{
    background:var(--glass-bg-light);
    border-radius:var(--radius-xl);
    margin-bottom:var(--space-4);
    overflow:hidden;
}
[data-theme="dark"] .feed-item,[data-theme="dark"] .fds-feed-item{background:var(--glass-bg-dark)}

.feed-item-header{display:flex;align-items:center;padding:var(--space-4);gap:var(--space-3)}
.feed-item-avatar{width:42px;height:42px;border-radius:50%;object-fit:cover;flex-shrink:0}
.feed-item-meta{flex:1;min-width:0}
.feed-item-author{font-weight:600;font-size:15px;color:var(--color-gray-900)}
.feed-item-time{font-size:13px;color:#6b7280}

.feed-item-image-container{position:relative;width:100%;aspect-ratio:16/9;overflow:hidden}
.feed-item-image-container img{width:100%;height:100%;object-fit:cover;display:block}

.feed-item-content{padding:var(--space-4)}
.feed-item-title{font-size:18px;font-weight:600;margin-bottom:var(--space-2);color:var(--color-gray-900)}
.feed-item-description{font-size:14px;color:#6b7280;line-height:1.5}

.feed-item-actions{display:flex;padding:var(--space-3) var(--space-4);border-top:1px solid rgba(0,0,0,0.05);gap:var(--space-2)}
[data-theme="dark"] .feed-item-actions{border-top-color:rgba(255,255,255,0.05)}

/* ==========================================
   UTILITIES
   ========================================== */
.htb-container,.container{max-width:var(--layout-max-content-width);margin:0 auto;padding:0 var(--space-5)}
.htb-hero-gradient-brand{background:linear-gradient(135deg,#6366f1,#8b5cf6,#ec4899);min-height:200px}
img{max-width:100%;height:auto}

/* Offline Banner */
.offline-banner{position:fixed;top:0;left:0;right:0;background:#ef4444;color:#fff;padding:12px;text-align:center;z-index:10001;display:none}
.offline-banner.verified-offline{display:flex;align-items:center;justify-content:center;gap:8px}
@media(min-width:769px){.offline-banner,.offline-banner.verified-offline{display:none !important}}

/* Reduced Motion */
@media(prefers-reduced-motion:reduce){
    *{animation-duration:0.01ms !important;transition-duration:0.01ms !important}
    .skeleton{animation:none;background:rgba(255,255,255,0.05)}
}

/* Font Loading */
@font-face{font-family:'Roboto';font-style:normal;font-weight:400;font-display:optional;src:local('Roboto'),local('Roboto-Regular')}
</style>

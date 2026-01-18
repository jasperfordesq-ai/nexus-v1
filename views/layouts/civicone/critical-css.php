<!-- Critical CSS - Inline for Instant Rendering -->
<!-- CivicOne Layout - Government/Public Sector Theme -->
<style>
/* ==========================================
   DESIGN TOKENS - CivicOne Foundation
   ========================================== */
:root{
    /* Spacing Scale - 8px base */
    --space-1:4px;--space-2:8px;--space-3:12px;--space-4:16px;
    --space-5:20px;--space-6:24px;--space-8:32px;--space-12:48px;

    /* Layout Dimensions - Prevents CLS */
    --layout-header-height:64px;
    --layout-header-height-scrolled:56px;
    --layout-mobile-nav-height:84px;
    --layout-sidebar-width:340px;
    --layout-max-content-width:1200px;

    /* CivicOne Colors - Government Theme */
    --civic-gov-blue:#002d72;
    --civic-hse-green:#007b5f;
    --civic-primary:var(--civic-gov-blue);
    --civic-accent:var(--civic-hse-green);
    --color-primary-500:#002d72;
    --color-primary-600:#001d4a;
    --color-gray-50:#f9fafb;
    --color-gray-900:#111827;

    /* CivicOne Glass Effect */
    --glass-blur:20px;
    --glass-saturation:180%;
    --glass-bg-light:rgba(255,255,255,0.85);
    --glass-bg-dark:rgba(0,45,114,0.9);
    --glass-border-light:rgba(0,45,114,0.1);
    --glass-border-dark:rgba(255,255,255,0.1);

    /* Transitions */
    --transition-fast:150ms cubic-bezier(0.4,0,0.2,1);
    --transition-base:300ms cubic-bezier(0.4,0,0.2,1);

    /* Radius - More formal/government style */
    --radius-sm:4px;--radius-md:8px;--radius-lg:12px;--radius-xl:16px;--radius-full:9999px;

    /* Z-Index */
    --z-fixed:1200;--z-modal:1400;

    /* Typography */
    --font-family-primary:'Roboto',-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;
}

/* Dark Theme Tokens */
[data-theme="dark"]{
    --color-gray-50:#0f172a;
    --color-gray-900:#f9fafb;
    --civic-primary:#4a90d9;
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
.layout-container,.civic-layout{min-height:100vh;display:flex;flex-direction:column}
main,.main-content,#main-content{margin-top:var(--layout-header-height);flex:1;width:100%}

/* Grid Layout - Pre-defined */
.civic-grid,.post-box-grid{
    display:grid;
    grid-template-columns:1fr var(--layout-sidebar-width);
    gap:var(--space-6);
    max-width:var(--layout-max-content-width);
    margin:0 auto;
    padding:var(--space-5);
}

@media(max-width:1024px){
    .civic-grid,.post-box-grid{grid-template-columns:1fr 300px}
}

@media(max-width:768px){
    main,.main-content,#main-content{padding-bottom:calc(var(--layout-mobile-nav-height) + 16px)}
    .civic-grid,.post-box-grid{grid-template-columns:1fr;gap:var(--space-3);padding:var(--space-3)}
}

/* ==========================================
   HEADER - CivicOne Government Style
   ========================================== */
.civic-header{
    position:fixed;top:0;left:0;right:0;
    height:var(--layout-header-height);
    background:var(--civic-gov-blue);
    border-bottom:3px solid var(--civic-hse-green);
    z-index:var(--z-fixed);
    transform:translateZ(0);
    will-change:transform,height;
    transition:height var(--transition-base);
}
.civic-header.scrolled{height:var(--layout-header-height-scrolled)}

/* ==========================================
   SKELETON LOADING - Zero CLS
   ========================================== */
@keyframes skeleton-shimmer{
    0%{background-position:-1000px 0}
    100%{background-position:1000px 0}
}
.skeleton{
    background:linear-gradient(90deg,rgba(0,45,114,0.05) 0%,rgba(0,45,114,0.1) 50%,rgba(0,45,114,0.05) 100%);
    background-size:2000px 100%;
    animation:skeleton-shimmer 2s infinite linear;
    border-radius:var(--radius-md);
}
[data-theme="light"] .skeleton{
    background:linear-gradient(90deg,#e5e7eb 0%,#f3f4f6 50%,#e5e7eb 100%);
    background-size:2000px 100%;
}

.skeleton-feed-item{
    background:white;
    border:1px solid rgba(0,45,114,0.1);
    border-radius:var(--radius-lg);
    padding:var(--space-4);
    margin-bottom:var(--space-4);
    min-height:200px;
}

.skeleton-container{opacity:1;transition:opacity var(--transition-base)}
.skeleton-container.hydrated{opacity:0;pointer-events:none;position:absolute}
.actual-content{opacity:0;transition:opacity var(--transition-base)}
.actual-content.hydrated{opacity:1}

/* ==========================================
   ACCESSIBILITY - WCAG 2.1 AA
   ========================================== */
.skip-link{position:absolute;top:-40px;left:0;background:var(--civic-gov-blue);color:#fff;padding:8px 16px;text-decoration:none;z-index:10000;border-radius:0 0 4px 0;font-weight:600;transition:top .3s}
.skip-link:focus{top:0;outline:3px solid var(--civic-hse-green);outline-offset:2px}
*:focus-visible{outline:2px solid var(--civic-hse-green);outline-offset:2px;border-radius:4px}
*:focus:not(:focus-visible){outline:none}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border-width:0}

/* ==========================================
   CIVIC CARD - Common component
   ========================================== */
.civic-card,.glass-card{
    background:white;
    border:1px solid rgba(0,45,114,0.1);
    border-radius:var(--radius-lg);
    padding:var(--space-5);
    transition:all var(--transition-base);
    box-shadow:0 1px 3px rgba(0,0,0,0.1);
}
[data-theme="dark"] .civic-card,[data-theme="dark"] .glass-card{background:#1e293b;border-color:rgba(255,255,255,0.1)}
.civic-card:hover,.glass-card:hover{box-shadow:0 4px 12px rgba(0,45,114,0.15);transform:translateY(-2px)}

/* ==========================================
   MOBILE NAVIGATION - Bottom Tab Bar
   ========================================== */
@media(max-width:768px){
    .civic-bottom-nav,.mobile-tab-bar{
        position:fixed;bottom:0;left:0;right:0;
        z-index:var(--z-fixed);
        height:var(--layout-mobile-nav-height);
        padding-bottom:env(safe-area-inset-bottom,0px);
        background:white;
        border-top:2px solid var(--civic-gov-blue);
        display:flex;align-items:flex-start;
        transform:translateZ(0);
    }
    [data-theme="dark"] .civic-bottom-nav,[data-theme="dark"] .mobile-tab-bar{background:#1e293b;border-top-color:var(--civic-hse-green)}

    .civic-bottom-nav-inner,.mobile-tab-bar-inner{
        display:flex;align-items:center;justify-content:space-around;
        width:100%;height:60px;padding:0 var(--space-2);
    }

    .civic-nav-item,.mobile-tab-item{
        display:flex;flex-direction:column;align-items:center;
        gap:var(--space-1);min-width:60px;min-height:44px;
        padding:var(--space-2);color:#64748b;text-decoration:none;
        font-size:12px;font-weight:500;border-radius:var(--radius-md);
        transition:all var(--transition-fast);
        -webkit-tap-highlight-color:transparent;
    }
    .civic-nav-item.active,.mobile-tab-item.active{color:var(--civic-gov-blue)}
    .civic-nav-item:active,.mobile-tab-item:active{transform:scale(0.9)}

    .civic-nav-fab,.mobile-tab-fab{position:relative;width:56px;height:56px;margin:-28px var(--space-2) 0;flex-shrink:0}
    .civic-nav-fab-button,.mobile-tab-fab-button{
        position:absolute;top:0;left:0;width:100%;height:100%;
        border-radius:var(--radius-full);
        background:var(--civic-gov-blue);
        border:3px solid white;
        color:white;font-size:24px;
        display:flex;align-items:center;justify-content:center;
        box-shadow:0 4px 12px rgba(0,45,114,0.3);
        cursor:pointer;transition:all var(--transition-base);
        -webkit-tap-highlight-color:transparent;
    }
    [data-theme="dark"] .civic-nav-fab-button,[data-theme="dark"] .mobile-tab-fab-button{border-color:#1e293b}
    .civic-nav-fab-button:active,.mobile-tab-fab-button:active{transform:scale(0.9)}
}

/* ==========================================
   UTILITIES
   ========================================== */
.civic-container,.htb-container,.container{max-width:var(--layout-max-content-width);margin:0 auto;padding:0 var(--space-5)}
.civic-hero,.htb-hero-gradient-brand{background:linear-gradient(135deg,var(--civic-gov-blue),#004494);min-height:200px;color:white}

/* Offline Banner */
.offline-banner{position:fixed;top:0;left:0;right:0;background:#dc2626;color:#fff;padding:12px;text-align:center;z-index:10001;display:none}
.offline-banner.verified-offline{display:flex;align-items:center;justify-content:center;gap:8px}
@media(min-width:769px){.offline-banner,.offline-banner.verified-offline{display:none !important}}

/* Reduced Motion */
@media(prefers-reduced-motion:reduce){
    *{animation-duration:0.01ms !important;transition-duration:0.01ms !important}
    .skeleton{animation:none;background:rgba(0,45,114,0.05)}
}

/* Font Loading */
@font-face{font-family:'Roboto';font-style:normal;font-weight:400;font-display:optional;src:local('Roboto'),local('Roboto-Regular')}
</style>

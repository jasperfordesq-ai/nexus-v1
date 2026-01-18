<?php
/**
 * Mobile-Only About Page - Android Launcher Style
 * Tenant-specific: Hour Timebank (tenant_id = 2)
 * Full holographic glassmorphism design - Gold Standard
 * No header/footer navigation - standalone mobile experience
 */

// Get tenant info
$tenantId = \Nexus\Core\TenantContext::getId();
$base = \Nexus\Core\TenantContext::getBasePath();

// Only show for tenant 2 (Hour Timebank)
if ($tenantId != 2) {
    header("Location: {$base}/");
    exit;
}

// Dark mode detection - defaults to dark, only light if explicitly set
$isDark = !isset($_COOKIE['nexus_mode']) || $_COOKIE['nexus_mode'] !== 'light';

// App icons configuration - ULTIMATE PREMIUM DESIGN
// Each icon has: gradient, glow colors, animation delay, and accent color for effects
$appIcons = [
    [
        'label' => 'Blog',
        'href' => '/blog',
        'icon' => 'fa-solid fa-pen-nib',
        'gradient' => 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 40%, #a855f7 100%)',
        'glow' => 'rgba(79, 70, 229, 0.45)',
        'darkGlow' => 'rgba(124, 58, 237, 0.55)',
        'accentColor' => '#a855f7',
        'animDelay' => '0s'
    ],
    [
        'label' => 'Our Story',
        'href' => '/our-story',
        'icon' => 'fa-solid fa-heart',
        'gradient' => 'linear-gradient(135deg, #db2777 0%, #ec4899 40%, #f472b6 100%)',
        'glow' => 'rgba(219, 39, 119, 0.45)',
        'darkGlow' => 'rgba(236, 72, 153, 0.55)',
        'accentColor' => '#f472b6',
        'animDelay' => '0.1s'
    ],
    [
        'label' => 'Timebanking Guide',
        'href' => '/timebanking-guide',
        'icon' => 'fa-solid fa-book-open-reader',
        'gradient' => 'linear-gradient(135deg, #0284c7 0%, #0ea5e9 40%, #38bdf8 100%)',
        'glow' => 'rgba(2, 132, 199, 0.45)',
        'darkGlow' => 'rgba(14, 165, 233, 0.55)',
        'accentColor' => '#38bdf8',
        'animDelay' => '0.2s'
    ],
    [
        'label' => 'Partners',
        'href' => '/partner',
        'icon' => 'fa-solid fa-handshake',
        'gradient' => 'linear-gradient(135deg, #059669 0%, #10b981 40%, #34d399 100%)',
        'glow' => 'rgba(5, 150, 105, 0.45)',
        'darkGlow' => 'rgba(16, 185, 129, 0.55)',
        'accentColor' => '#34d399',
        'animDelay' => '0.3s'
    ],
    [
        'label' => 'Social Prescribing',
        'href' => '/social-prescribing',
        'icon' => 'fa-solid fa-hand-holding-medical',
        'gradient' => 'linear-gradient(135deg, #d97706 0%, #f59e0b 40%, #fbbf24 100%)',
        'glow' => 'rgba(217, 119, 6, 0.45)',
        'darkGlow' => 'rgba(245, 158, 11, 0.55)',
        'accentColor' => '#fbbf24',
        'animDelay' => '0.4s'
    ],
    [
        'label' => 'FAQ',
        'href' => '/faq',
        'icon' => 'fa-solid fa-circle-question',
        'gradient' => 'linear-gradient(135deg, #7c3aed 0%, #8b5cf6 40%, #a78bfa 100%)',
        'glow' => 'rgba(124, 58, 237, 0.45)',
        'darkGlow' => 'rgba(139, 92, 246, 0.55)',
        'accentColor' => '#a78bfa',
        'animDelay' => '0.5s'
    ],
    [
        'label' => 'Impact Summary',
        'href' => '/impact-summary',
        'icon' => 'fa-solid fa-chart-line',
        'gradient' => 'linear-gradient(135deg, #e11d48 0%, #f43f5e 40%, #fb7185 100%)',
        'glow' => 'rgba(225, 29, 72, 0.45)',
        'darkGlow' => 'rgba(244, 63, 94, 0.55)',
        'accentColor' => '#fb7185',
        'animDelay' => '0.6s'
    ],
    [
        'label' => 'Impact Report',
        'href' => '/impact-report',
        'icon' => 'fa-solid fa-file-lines',
        'gradient' => 'linear-gradient(135deg, #0d9488 0%, #14b8a6 40%, #2dd4bf 100%)',
        'glow' => 'rgba(13, 148, 136, 0.45)',
        'darkGlow' => 'rgba(20, 184, 166, 0.55)',
        'accentColor' => '#2dd4bf',
        'animDelay' => '0.7s'
    ],
    [
        'label' => 'Strategic Plan',
        'href' => '/strategic-plan',
        'icon' => 'fa-solid fa-compass',
        'gradient' => 'linear-gradient(135deg, #4f46e5 0%, #ec4899 50%, #f59e0b 100%)',
        'glow' => 'rgba(79, 70, 229, 0.4)',
        'darkGlow' => 'rgba(236, 72, 153, 0.5)',
        'accentColor' => '#ec4899',
        'animDelay' => '0.8s'
    ]
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $isDark ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="<?= $isDark ? '#0f172a' : '#f8fafc' ?>">
    <title>About - Hour Timebank</title>

    <!-- Font Awesome Pro -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ========================================
           CSS CUSTOM PROPERTIES - DESIGN TOKENS
           ======================================== */
        :root {
            /* Light Mode Colors */
            --bg-primary: #f8fafc;
            --bg-secondary: #ffffff;
            --bg-glass: rgba(255, 255, 255, 0.72);
            --bg-glass-strong: rgba(255, 255, 255, 0.85);
            --border-glass: rgba(255, 255, 255, 0.6);
            --border-subtle: rgba(0, 0, 0, 0.06);
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 16px 48px rgba(0, 0, 0, 0.12);
            --shadow-glow: 0 0 40px rgba(99, 102, 241, 0.15);

            /* Holographic Gradients */
            --gradient-brand: linear-gradient(135deg, #6366f1 0%, #ec4899 50%, #f59e0b 100%);
            --gradient-hero: linear-gradient(135deg, #4f46e5 0%, #7c3aed 25%, #db2777 50%, #f59e0b 75%, #fcd34d 100%);

            /* Blur Values */
            --blur-sm: blur(8px);
            --blur-md: blur(16px);
            --blur-lg: blur(24px);
            --blur-xl: blur(40px);

            /* Transitions */
            --transition-fast: 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-smooth: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-spring: 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-glass: rgba(30, 41, 59, 0.72);
            --bg-glass-strong: rgba(30, 41, 59, 0.88);
            --border-glass: rgba(255, 255, 255, 0.08);
            --border-subtle: rgba(255, 255, 255, 0.06);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #64748b;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.2);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 16px 48px rgba(0, 0, 0, 0.4);
            --shadow-glow: 0 0 60px rgba(139, 92, 246, 0.2);
        }

        /* ========================================
           CSS RESET & BASE
           ======================================== */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            height: 100%;
            overflow: hidden;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background-color var(--transition-smooth), color var(--transition-smooth);
        }

        body {
            min-height: 100%;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
            position: relative;
        }

        /* ========================================
           HOLOGRAPHIC ANIMATED BACKGROUND
           ======================================== */
        .holo-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }

        /* Animated gradient orbs */
        .holo-bg::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            top: -50%;
            left: -50%;
            background:
                radial-gradient(ellipse 40% 30% at 25% 30%, rgba(99, 102, 241, 0.25), transparent 60%),
                radial-gradient(ellipse 35% 35% at 75% 25%, rgba(236, 72, 153, 0.2), transparent 55%),
                radial-gradient(ellipse 45% 40% at 60% 70%, rgba(245, 158, 11, 0.18), transparent 60%),
                radial-gradient(ellipse 30% 25% at 20% 75%, rgba(6, 182, 212, 0.15), transparent 50%),
                radial-gradient(ellipse 50% 45% at 85% 60%, rgba(139, 92, 246, 0.12), transparent 55%);
            animation: holoOrbs 25s ease-in-out infinite;
        }

        /* Noise texture overlay for depth */
        .holo-bg::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg,
                    var(--bg-primary) 0%,
                    transparent 15%,
                    transparent 85%,
                    var(--bg-primary) 100%),
                url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
            opacity: 0.03;
        }

        [data-theme="dark"] .holo-bg::before {
            background:
                radial-gradient(ellipse 40% 30% at 25% 30%, rgba(99, 102, 241, 0.35), transparent 60%),
                radial-gradient(ellipse 35% 35% at 75% 25%, rgba(236, 72, 153, 0.28), transparent 55%),
                radial-gradient(ellipse 45% 40% at 60% 70%, rgba(139, 92, 246, 0.25), transparent 60%),
                radial-gradient(ellipse 30% 25% at 20% 75%, rgba(6, 182, 212, 0.2), transparent 50%),
                radial-gradient(ellipse 50% 45% at 85% 60%, rgba(245, 158, 11, 0.15), transparent 55%);
        }

        [data-theme="dark"] .holo-bg::after {
            opacity: 0.015;
        }

        @keyframes holoOrbs {
            0%, 100% {
                transform: translate(0, 0) rotate(0deg) scale(1);
            }
            20% {
                transform: translate(3%, 2%) rotate(3deg) scale(1.02);
            }
            40% {
                transform: translate(-2%, 4%) rotate(-2deg) scale(0.98);
            }
            60% {
                transform: translate(4%, -2%) rotate(4deg) scale(1.03);
            }
            80% {
                transform: translate(-3%, 3%) rotate(-3deg) scale(1.01);
            }
        }

        /* ========================================
           MAIN CONTAINER
           ======================================== */
        .app-container {
            position: relative;
            z-index: 1;
            height: 100vh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            padding-top: env(safe-area-inset-top);
            padding-left: env(safe-area-inset-left);
            padding-right: env(safe-area-inset-right);
        }

        /* ========================================
           HEADER - GLASSMORPHIC
           ======================================== */
        .app-header {
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .header-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-glass);
            backdrop-filter: var(--blur-lg) saturate(180%);
            -webkit-backdrop-filter: var(--blur-lg) saturate(180%);
            border: 1px solid var(--border-glass);
            color: var(--text-primary);
            text-decoration: none;
            cursor: pointer;
            transition: all var(--transition-spring);
            box-shadow: var(--shadow-sm);
        }

        .header-btn:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }

        .header-btn:active {
            transform: scale(0.95);
        }

        .header-btn i {
            font-size: 1.1rem;
        }

        .header-title {
            flex: 1;
            text-align: center;
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            background: var(--gradient-brand);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            background-size: 200% 200%;
            animation: gradientShift 8s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Theme toggle icon animation */
        .theme-icon {
            transition: transform var(--transition-spring);
        }

        [data-theme="dark"] .theme-icon {
            color: #fbbf24;
        }

        /* ========================================
           HERO SECTION - GLASSMORPHIC CARD
           ======================================== */
        .hero-section {
            padding: 20px 16px 16px;
        }

        .hero-card {
            background: var(--bg-glass-strong);
            backdrop-filter: var(--blur-xl) saturate(180%);
            -webkit-backdrop-filter: var(--blur-xl) saturate(180%);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 16px 16px 18px;
            text-align: center;
            box-shadow: var(--shadow-md), var(--shadow-glow);
            position: relative;
            overflow: hidden;
        }

        /* Holographic border glow */
        .hero-card::before {
            content: '';
            position: absolute;
            inset: -1px;
            border-radius: 25px;
            padding: 1px;
            background: var(--gradient-hero);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0.5;
            animation: borderGlow 4s ease-in-out infinite;
        }

        @keyframes borderGlow {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.6; }
        }

        .hero-logo {
            width: 56px;
            height: 56px;
            margin: 0 auto 10px;
            border-radius: 16px;
            background: var(--gradient-hero);
            background-size: 300% 300%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: logoGradient 6s ease infinite;
            box-shadow:
                0 6px 24px rgba(99, 102, 241, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.2) inset;
        }

        @keyframes logoGradient {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* Inner glow ring */
        .hero-logo::after {
            content: '';
            position: absolute;
            inset: 2px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .hero-logo i {
            font-size: 1.5rem;
            color: white;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
            position: relative;
            z-index: 1;
        }

        .hero-title {
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .hero-subtitle {
            font-size: 0.82rem;
            color: var(--text-secondary);
            line-height: 1.45;
        }

        /* ========================================
           APP GRID CONTAINER
           ======================================== */
        .app-grid-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 20px 16px calc(100px + env(safe-area-inset-bottom));
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-y: contain;
        }

        /* Custom scrollbar */
        .app-grid-container::-webkit-scrollbar {
            width: 3px;
        }

        .app-grid-container::-webkit-scrollbar-track {
            background: transparent;
        }

        .app-grid-container::-webkit-scrollbar-thumb {
            background: var(--text-muted);
            border-radius: 3px;
            opacity: 0.5;
        }

        .app-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px 8px;
            max-width: 380px;
            margin: 0 auto;
        }

        @media (min-width: 400px) {
            .app-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 28px 12px;
                max-width: 420px;
            }
        }

        /* ========================================
           APP ICON - ULTIMATE PREMIUM DESIGN
           ======================================== */
        .app-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            -webkit-tap-highlight-color: transparent;
            transition: transform var(--transition-spring);
            outline: none;
            animation: iconFadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) backwards;
            animation-delay: var(--anim-delay, 0s);
        }

        @keyframes iconFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.8);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .app-icon:active {
            transform: scale(0.85);
        }

        .app-icon:focus-visible .app-icon-circle {
            outline: 3px solid var(--accent-color, #6366f1);
            outline-offset: 4px;
        }

        /* Icon Circle - Main Container */
        .app-icon-circle {
            width: 88px;
            height: 88px;
            border-radius: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            margin-bottom: 12px;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            transform-style: preserve-3d;
            /* Base shadow - enhanced per icon */
        }

        /* Pulsing glow ring animation */
        .app-icon-circle .glow-ring {
            position: absolute;
            inset: -4px;
            border-radius: 30px;
            background: var(--icon-gradient);
            opacity: 0;
            filter: blur(12px);
            transition: opacity 0.3s ease;
            z-index: -1;
            animation: glowPulse 3s ease-in-out infinite;
            animation-delay: var(--anim-delay, 0s);
        }

        @keyframes glowPulse {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.05); }
        }

        [data-theme="dark"] .app-icon-circle .glow-ring {
            opacity: 0.4;
        }

        .app-icon:hover .glow-ring,
        .app-icon:focus .glow-ring {
            opacity: 0.7 !important;
            animation: none;
        }

        /* Glass shine overlay - premium reflection */
        .app-icon-circle::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 26px;
            background:
                linear-gradient(
                    145deg,
                    rgba(255, 255, 255, 0.55) 0%,
                    rgba(255, 255, 255, 0.25) 25%,
                    rgba(255, 255, 255, 0.05) 50%,
                    transparent 100%
                );
            pointer-events: none;
            z-index: 2;
        }

        /* Inner highlight ring */
        .app-icon-circle::after {
            content: '';
            position: absolute;
            inset: 1px;
            border-radius: 25px;
            border: 1.5px solid rgba(255, 255, 255, 0.35);
            border-bottom-color: rgba(255, 255, 255, 0.1);
            border-right-color: rgba(255, 255, 255, 0.15);
            pointer-events: none;
            z-index: 3;
        }

        /* Icon itself */
        .app-icon-circle i {
            font-size: 2.1rem;
            color: white;
            position: relative;
            z-index: 4;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        /* Hover/Focus states */
        .app-icon:hover .app-icon-circle,
        .app-icon:focus .app-icon-circle {
            transform: translateY(-6px) scale(1.08);
        }

        .app-icon:hover .app-icon-circle i,
        .app-icon:focus .app-icon-circle i {
            transform: scale(1.15) rotate(-3deg);
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.35));
        }

        /* Special animation for heart icon */
        .app-icon-circle i.fa-heart {
            animation: heartbeat 2s ease-in-out infinite;
            animation-delay: var(--anim-delay, 0s);
        }

        @keyframes heartbeat {
            0%, 100% { transform: scale(1); }
            10% { transform: scale(1.1); }
            20% { transform: scale(1); }
            30% { transform: scale(1.08); }
            40% { transform: scale(1); }
        }

        .app-icon:hover .app-icon-circle i.fa-heart {
            animation: none;
            transform: scale(1.15);
        }

        /* Special animation for compass icon */
        .app-icon-circle i.fa-compass {
            animation: compassSpin 8s linear infinite;
        }

        @keyframes compassSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .app-icon:hover .app-icon-circle i.fa-compass {
            animation: none;
            transform: scale(1.15) rotate(-10deg);
        }

        /* Label styling */
        .app-icon-label {
            font-size: 0.73rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-align: center;
            max-width: 80px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            transition: all 0.3s ease;
            letter-spacing: -0.01em;
        }

        .app-icon:hover .app-icon-label,
        .app-icon:focus .app-icon-label {
            color: var(--text-primary);
            transform: translateY(2px);
        }

        /* Ripple effect on tap */
        .app-icon-circle .ripple {
            position: absolute;
            inset: 0;
            border-radius: 26px;
            background: radial-gradient(circle, rgba(255,255,255,0.4) 0%, transparent 70%);
            opacity: 0;
            transform: scale(0);
            z-index: 5;
            pointer-events: none;
        }

        .app-icon:active .ripple {
            animation: rippleEffect 0.4s ease-out;
        }

        @keyframes rippleEffect {
            0% { opacity: 1; transform: scale(0); }
            100% { opacity: 0; transform: scale(2); }
        }

        /* Shimmer effect on hover */
        .app-icon-circle .shimmer {
            position: absolute;
            inset: 0;
            border-radius: 26px;
            background: linear-gradient(
                110deg,
                transparent 20%,
                rgba(255, 255, 255, 0.4) 50%,
                transparent 80%
            );
            opacity: 0;
            transform: translateX(-100%);
            z-index: 6;
            pointer-events: none;
        }

        .app-icon:hover .shimmer {
            animation: shimmerSlide 0.8s ease-out;
        }

        @keyframes shimmerSlide {
            0% { opacity: 1; transform: translateX(-100%); }
            100% { opacity: 0; transform: translateX(100%); }
        }

        /* ========================================
           DOCK BAR - FLOATING GLASSMORPHIC
           ======================================== */
        .dock-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 12px 24px calc(16px + env(safe-area-inset-bottom));
            display: flex;
            justify-content: center;
            z-index: 100;
            pointer-events: none;
        }

        .dock-inner {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 10px 20px;
            background: var(--bg-glass-strong);
            backdrop-filter: var(--blur-xl) saturate(200%);
            -webkit-backdrop-filter: var(--blur-xl) saturate(200%);
            border-radius: 22px;
            border: 1px solid var(--border-glass);
            box-shadow:
                var(--shadow-lg),
                0 0 0 1px var(--border-subtle) inset;
            pointer-events: auto;
        }

        .dock-item {
            width: 50px;
            height: 50px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            position: relative;
            transition: all var(--transition-spring);
            /* Gradient backgrounds set inline */
        }

        .dock-item:active {
            transform: scale(0.85);
        }

        .dock-item i {
            font-size: 1.25rem;
            color: white;
            position: relative;
            z-index: 1;
            filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.2));
        }

        /* Glass shine on dock items */
        .dock-item::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 16px;
            background: linear-gradient(
                135deg,
                rgba(255, 255, 255, 0.35) 0%,
                rgba(255, 255, 255, 0) 50%
            );
            pointer-events: none;
        }

        .dock-item::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Dock item colors */
        .dock-home {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4);
        }

        .dock-explore {
            background: linear-gradient(135deg, #ec4899 0%, #f43f5e 100%);
            box-shadow: 0 4px 16px rgba(236, 72, 153, 0.4);
        }

        .dock-profile {
            background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%);
            box-shadow: 0 4px 16px rgba(14, 165, 233, 0.4);
        }

        /* ========================================
           DESKTOP NOTICE
           ======================================== */
        .desktop-notice {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: var(--bg-primary);
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px;
        }

        .desktop-notice::before {
            content: '';
            position: absolute;
            inset: 0;
            background: var(--gradient-hero);
            opacity: 0.08;
        }

        .desktop-notice-content {
            position: relative;
            z-index: 1;
        }

        .desktop-notice-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 24px;
            border-radius: 28px;
            background: var(--gradient-brand);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 16px 48px rgba(99, 102, 241, 0.3);
        }

        .desktop-notice-icon i {
            font-size: 2.5rem;
            color: white;
        }

        .desktop-notice h2 {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }

        .desktop-notice p {
            font-size: 1.05rem;
            color: var(--text-secondary);
            margin-bottom: 28px;
            max-width: 400px;
            line-height: 1.6;
        }

        .desktop-notice a {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            background: var(--gradient-brand);
            border-radius: 14px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: all var(--transition-smooth);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
        }

        .desktop-notice a:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(99, 102, 241, 0.4);
        }

        @media (min-width: 769px) {
            .desktop-notice {
                display: flex;
            }

            .app-container {
                display: none;
            }
        }

        /* ========================================
           REDUCED MOTION
           ======================================== */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <!-- Holographic Animated Background -->
    <div class="holo-bg"></div>

    <!-- Desktop Notice -->
    <div class="desktop-notice">
        <div class="desktop-notice-content">
            <div class="desktop-notice-icon">
                <i class="fa-solid fa-mobile-screen-button"></i>
            </div>
            <h2>Mobile Experience</h2>
            <p>This page is optimized for mobile devices. Visit on your phone for the best experience.</p>
            <a href="<?= $base ?>/">
                <i class="fa-solid fa-arrow-left"></i>
                Go to Main Site
            </a>
        </div>
    </div>

    <!-- Mobile App Container -->
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <a href="<?= $base ?>/" class="header-btn" aria-label="Go back home">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <span class="header-title">About Us</span>
            <button class="header-btn" onclick="toggleTheme()" aria-label="Toggle theme">
                <i class="fa-solid <?= $isDark ? 'fa-sun' : 'fa-moon' ?> theme-icon" id="themeIcon"></i>
            </button>
        </header>

        <!-- App Grid -->
        <div class="app-grid-container">
            <div class="app-grid">
                <?php foreach ($appIcons as $index => $app):
                    $glow = $isDark ? $app['darkGlow'] : $app['glow'];
                ?>
                <a href="<?= $base . $app['href'] ?>"
                   class="app-icon"
                   style="--anim-delay: <?= $app['animDelay'] ?>; --accent-color: <?= $app['accentColor'] ?>; --icon-gradient: <?= $app['gradient'] ?>;">
                    <div class="app-icon-circle"
                         style="background: <?= $app['gradient'] ?>; box-shadow: 0 10px 30px <?= $glow ?>, 0 4px 12px rgba(0,0,0,0.15), inset 0 1px 0 rgba(255,255,255,0.2);">
                        <!-- Glow ring for pulsing effect -->
                        <span class="glow-ring" style="background: <?= $app['gradient'] ?>;"></span>
                        <!-- Shimmer overlay -->
                        <span class="shimmer"></span>
                        <!-- Ripple effect -->
                        <span class="ripple"></span>
                        <!-- Icon -->
                        <i class="<?= $app['icon'] ?>"></i>
                    </div>
                    <span class="app-icon-label"><?= htmlspecialchars($app['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Dock Bar -->
        <nav role="navigation" aria-label="Main navigation" class="dock-bar" aria-label="Quick navigation">
            <div class="dock-inner">
                <a href="<?= $base ?>/" class="dock-item dock-home" aria-label="Home">
                    <i class="fa-solid fa-house"></i>
                </a>
                <a href="<?= $base ?>/listings" class="dock-item dock-explore" aria-label="Explore listings">
                    <i class="fa-solid fa-compass"></i>
                </a>
                <a href="<?= $base ?>/dashboard" class="dock-item dock-profile" aria-label="Your dashboard">
                    <i class="fa-solid fa-user"></i>
                </a>
            </div>
        </nav>
    </div>

    <script>
        // Theme Toggle with smooth transition
        function toggleTheme() {
            const html = document.documentElement;
            const icon = document.getElementById('themeIcon');
            const isDark = html.getAttribute('data-theme') === 'dark';

            // Animate icon
            icon.style.transform = 'rotate(360deg) scale(0)';

            setTimeout(() => {
                if (isDark) {
                    html.setAttribute('data-theme', 'light');
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
                    document.cookie = 'nexus_mode=light; path=/; max-age=31536000; SameSite=Lax';
                    document.querySelector('meta[name="theme-color"]').content = '#f8fafc';
                } else {
                    html.setAttribute('data-theme', 'dark');
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                    document.cookie = 'nexus_mode=dark; path=/; max-age=31536000; SameSite=Lax';
                    document.querySelector('meta[name="theme-color"]').content = '#0f172a';
                }

                icon.style.transform = 'rotate(0deg) scale(1)';

                // Update icon glow shadows based on new theme
                updateIconGlows(!isDark);
            }, 150);

            // Haptic feedback
            if ('vibrate' in navigator) {
                navigator.vibrate(10);
            }
        }

        // Update icon glow colors when theme changes
        function updateIconGlows(isDark) {
            const icons = document.querySelectorAll('.app-icon-circle');
            const glowData = <?= json_encode(array_map(fn($a) => [
                'glow' => $a['glow'],
                'darkGlow' => $a['darkGlow'],
                'accentColor' => $a['accentColor']
            ], $appIcons)) ?>;

            icons.forEach((icon, index) => {
                if (glowData[index]) {
                    const glow = isDark ? glowData[index].darkGlow : glowData[index].glow;
                    icon.style.boxShadow = `0 10px 30px ${glow}, 0 4px 12px rgba(0,0,0,0.15), inset 0 1px 0 rgba(255,255,255,0.2)`;
                }
            });
        }

        // Touch feedback for app icons
        document.querySelectorAll('.app-icon, .dock-item, .header-btn').forEach(el => {
            el.addEventListener('touchstart', function() {
                if ('vibrate' in navigator) {
                    navigator.vibrate(5);
                }
            }, { passive: true });
        });

        // Prevent overscroll/pull-to-refresh
        let startY = 0;
        document.addEventListener('touchstart', e => {
            startY = e.touches[0].clientY;
        }, { passive: true });

        document.addEventListener('touchmove', e => {
            const container = document.querySelector('.app-grid-container');
            const touch = e.touches[0];
            const isScrollable = container.scrollHeight > container.clientHeight;
            const isAtTop = container.scrollTop === 0;
            const isScrollingDown = touch.clientY > startY;

            if (!e.target.closest('.app-grid-container') || (isAtTop && isScrollingDown)) {
                if (e.cancelable) {
                    e.preventDefault();
                }
            }
        }, { passive: false });

        // Smooth scroll container
        const gridContainer = document.querySelector('.app-grid-container');
        if (gridContainer) {
            gridContainer.style.scrollBehavior = 'smooth';
        }
    </script>
</body>
</html>

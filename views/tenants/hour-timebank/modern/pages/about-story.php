<?php
// Phoenix View: Our Story - Glassmorphism 2025 + Gold Standard v6.1
$pageTitle = 'Our Story';
$hideHero = true;

require __DIR__ . '/../../../..' . '/layouts/modern/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<style>
/* ============================================
   GOLD STANDARD - Native App Features
   Theme Color: Rose (#be185d)
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
@keyframes goldFadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

#story-glass-wrapper {
    animation: goldFadeInUp 0.4s ease-out;
}

/* Button Press States */
.quick-action-btn:active,
.btn--primary:active {
    transform: scale(0.96) !important;
    transition: transform 0.1s ease !important;
}

/* Touch Targets - WCAG 2.1 AA (44px minimum) */
.quick-action-btn,
.btn--primary {
    min-height: 44px !important;
}

/* iOS Zoom Prevention */
input[type="text"],
input[type="email"],
textarea {
    font-size: 16px !important;
}

/* Focus Visible */
.quick-action-btn:focus-visible,
.btn--primary:focus-visible,
a:focus-visible {
    outline: 3px solid rgba(190, 24, 93, 0.5);
    outline-offset: 2px;
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

/* Mobile Responsive Enhancements */
@media (max-width: 768px) {
    .quick-action-btn,
    .btn--primary {
        min-height: 48px !important;
    }
}
</style>

<div class="htb-container-full">
<div id="story-glass-wrapper">

    <style>
        /* ===================================
           GLASSMORPHISM OUR STORY PAGE
           Theme Color: Rose (#be185d)
           =================================== */

        /* Animated Gradient Background */
        #story-glass-wrapper::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            pointer-events: none;
        }

        [data-theme="light"] #story-glass-wrapper::before {
            background: linear-gradient(135deg,
                rgba(190, 24, 93, 0.08) 0%,
                rgba(219, 39, 119, 0.08) 25%,
                rgba(236, 72, 153, 0.08) 50%,
                rgba(244, 114, 182, 0.08) 75%,
                rgba(190, 24, 93, 0.08) 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        [data-theme="dark"] #story-glass-wrapper::before {
            background: radial-gradient(circle at 20% 30%,
                rgba(190, 24, 93, 0.15) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%,
                rgba(219, 39, 119, 0.12) 0%, transparent 50%);
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* ===================================
           NEXUS WELCOME HERO - Gold Standard
           =================================== */
        #story-glass-wrapper .nexus-welcome-hero {
            background: linear-gradient(135deg,
                rgba(190, 24, 93, 0.12) 0%,
                rgba(219, 39, 119, 0.12) 50%,
                rgba(244, 114, 182, 0.08) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            padding: 28px 24px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(190, 24, 93, 0.1);
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }

        [data-theme="dark"] #story-glass-wrapper .nexus-welcome-hero {
            background: linear-gradient(135deg,
                rgba(190, 24, 93, 0.15) 0%,
                rgba(219, 39, 119, 0.15) 50%,
                rgba(244, 114, 182, 0.1) 100%);
            border-color: rgba(255, 255, 255, 0.1);
        }

        #story-glass-wrapper .nexus-welcome-title {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #be185d, #db2777, #f472b6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 8px 0;
            line-height: 1.2;
        }

        #story-glass-wrapper .nexus-welcome-subtitle {
            font-size: 0.95rem;
            color: var(--htb-text-muted);
            margin: 0 0 20px 0;
            line-height: 1.5;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        #story-glass-wrapper .nexus-smart-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        #story-glass-wrapper .nexus-smart-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 2px solid transparent;
            min-height: 44px;
        }

        #story-glass-wrapper .nexus-smart-btn i { font-size: 1rem; }

        #story-glass-wrapper .nexus-smart-btn-primary {
            background: linear-gradient(135deg, #be185d, #db2777);
            color: white;
            box-shadow: 0 4px 14px rgba(190, 24, 93, 0.35);
        }

        #story-glass-wrapper .nexus-smart-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(190, 24, 93, 0.45);
        }

        #story-glass-wrapper .nexus-smart-btn-outline {
            background: rgba(255, 255, 255, 0.5);
            color: var(--htb-text-main);
            border-color: rgba(190, 24, 93, 0.3);
        }

        [data-theme="dark"] #story-glass-wrapper .nexus-smart-btn-outline {
            background: rgba(15, 23, 42, 0.5);
            border-color: rgba(219, 39, 119, 0.4);
        }

        #story-glass-wrapper .nexus-smart-btn-outline:hover {
            background: linear-gradient(135deg, #be185d, #db2777);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
        }

        @media (max-width: 640px) {
            #story-glass-wrapper .nexus-welcome-hero { padding: 20px 16px; border-radius: 16px; margin-bottom: 20px; }
            #story-glass-wrapper .nexus-welcome-title { font-size: 1.35rem; }
            #story-glass-wrapper .nexus-welcome-subtitle { font-size: 0.85rem; margin-bottom: 16px; }
            #story-glass-wrapper .nexus-smart-buttons { flex-direction: column; gap: 10px; }
            #story-glass-wrapper .nexus-smart-btn { width: 100%; justify-content: center; padding: 14px 20px; }
        }

        /* Legacy Page Header - hidden, replaced by welcome hero */
        #story-glass-wrapper .page-header {
            display: none;
        }

        /* Quick Actions Bar */
        #story-glass-wrapper .quick-actions-bar {
            margin: 0 0 30px 0;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        #story-glass-wrapper .quick-action-btn {
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.75),
                rgba(255, 255, 255, 0.6));
            backdrop-filter: blur(16px) saturate(120%);
            -webkit-backdrop-filter: blur(16px) saturate(120%);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 14px;
            padding: 14px 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(31, 38, 135, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.4);
            position: relative;
            overflow: hidden;
        }

        #story-glass-wrapper .quick-action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(190, 24, 93, 0.08), rgba(219, 39, 119, 0.08));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        #story-glass-wrapper .quick-action-btn:hover::before {
            opacity: 1;
        }

        #story-glass-wrapper .quick-action-btn:hover {
            transform: translateY(-3px);
            border-color: rgba(190, 24, 93, 0.4);
            box-shadow: 0 8px 20px rgba(190, 24, 93, 0.15),
                        0 0 0 1px rgba(190, 24, 93, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.5);
        }

        [data-theme="dark"] #story-glass-wrapper .quick-action-btn {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            border: 2px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }

        [data-theme="dark"] #story-glass-wrapper .quick-action-btn:hover {
            border-color: rgba(190, 24, 93, 0.3);
            box-shadow: 0 8px 20px rgba(190, 24, 93, 0.25),
                        0 0 0 1px rgba(190, 24, 93, 0.2),
                        0 0 30px rgba(219, 39, 119, 0.12);
        }

        #story-glass-wrapper .quick-action-icon {
            font-size: 1.5rem;
            line-height: 1;
        }

        #story-glass-wrapper .quick-action-text {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--htb-text-main);
            position: relative;
            z-index: 1;
        }

        /* Section Headers */
        #story-glass-wrapper .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 40px 0 24px 0;
            padding-left: 16px;
            border-left: 4px solid #be185d;
        }

        #story-glass-wrapper .section-header h2 {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--htb-text-main);
            margin: 0;
        }

        /* Glass Cards */
        #story-glass-wrapper .glass-card {
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.75),
                rgba(255, 255, 255, 0.6));
            backdrop-filter: blur(20px) saturate(120%);
            -webkit-backdrop-filter: blur(20px) saturate(120%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.12),
                        inset 0 1px 0 rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 24px;
        }

        #story-glass-wrapper .glass-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 48px rgba(31, 38, 135, 0.18),
                        0 0 0 1px rgba(190, 24, 93, 0.15),
                        inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        [data-theme="dark"] #story-glass-wrapper .glass-card {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.6),
                rgba(30, 41, 59, 0.5));
            backdrop-filter: blur(24px) saturate(150%);
            -webkit-backdrop-filter: blur(24px) saturate(150%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5),
                        0 0 60px rgba(190, 24, 93, 0.08),
                        inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        [data-theme="dark"] #story-glass-wrapper .glass-card:hover {
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.7),
                        0 0 0 1px rgba(190, 24, 93, 0.25),
                        0 0 80px rgba(219, 39, 119, 0.12),
                        inset 0 1px 0 rgba(255, 255, 255, 0.12);
        }

        /* Card Header */
        #story-glass-wrapper .card-header-gradient {
            background: linear-gradient(135deg, #be185d 0%, #db2777 50%, #ec4899 100%);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        #story-glass-wrapper .card-header-gradient h3 {
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
            flex: 1;
        }

        #story-glass-wrapper .card-header-gradient .icon {
            font-size: 1.5rem;
        }

        #story-glass-wrapper .card-header-gradient .badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Card Body */
        #story-glass-wrapper .card-body {
            padding: 24px;
        }

        #story-glass-wrapper .card-body p {
            color: var(--htb-text-muted);
            font-size: 1rem;
            line-height: 1.7;
            margin: 0 0 16px 0;
        }

        #story-glass-wrapper .card-body p:last-child {
            margin-bottom: 0;
        }

        /* Mission/Vision Grid */
        #story-glass-wrapper .mission-vision-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        /* Values Grid */
        #story-glass-wrapper .values-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        #story-glass-wrapper .value-item {
            text-align: center;
            padding: 24px 20px;
            background: rgba(190, 24, 93, 0.04);
            border-radius: 16px;
            border: 1px solid rgba(190, 24, 93, 0.1);
            transition: all 0.3s ease;
        }

        #story-glass-wrapper .value-item:hover {
            background: rgba(190, 24, 93, 0.08);
            transform: translateY(-3px);
        }

        [data-theme="dark"] #story-glass-wrapper .value-item {
            background: rgba(190, 24, 93, 0.1);
            border-color: rgba(190, 24, 93, 0.2);
        }

        [data-theme="dark"] #story-glass-wrapper .value-item:hover {
            background: rgba(190, 24, 93, 0.15);
        }

        #story-glass-wrapper .value-item .value-icon {
            font-size: 2.5rem;
            margin-bottom: 12px;
        }

        #story-glass-wrapper .value-item h4 {
            color: var(--htb-text-main);
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0 0 10px 0;
        }

        #story-glass-wrapper .value-item p {
            color: var(--htb-text-muted);
            font-size: 0.9rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Credential Badges */
        #story-glass-wrapper .credential-badges {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin: 24px 0;
        }

        #story-glass-wrapper .credential-badge {
            background: linear-gradient(135deg, rgba(22, 163, 74, 0.1), rgba(34, 197, 94, 0.1));
            border: 1px solid rgba(22, 163, 74, 0.2);
            color: #166534;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        [data-theme="dark"] #story-glass-wrapper .credential-badge {
            background: linear-gradient(135deg, rgba(22, 163, 74, 0.2), rgba(34, 197, 94, 0.15));
            border-color: rgba(34, 197, 94, 0.3);
            color: #86efac;
        }

        /* CTA Box */
        #story-glass-wrapper .cta-box {
            background: linear-gradient(135deg,
                rgba(255, 255, 255, 0.5),
                rgba(255, 255, 255, 0.3));
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 2px dashed rgba(190, 24, 93, 0.3);
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            margin-top: 24px;
        }

        [data-theme="dark"] #story-glass-wrapper .cta-box {
            background: linear-gradient(135deg,
                rgba(15, 23, 42, 0.4),
                rgba(30, 41, 59, 0.3));
            border-color: rgba(190, 24, 93, 0.4);
        }

        #story-glass-wrapper .cta-box h4 {
            color: var(--htb-text-main);
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0 0 8px 0;
        }

        #story-glass-wrapper .cta-box p {
            color: var(--htb-text-muted);
            margin: 0 0 20px 0;
        }

        /* Glass Primary Button */
        #story-glass-wrapper .btn--primary {
            background: linear-gradient(135deg, #be185d 0%, #db2777 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(190, 24, 93, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.2);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        #story-glass-wrapper .btn--primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(190, 24, 93, 0.4),
                        inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            #story-glass-wrapper .quick-actions-bar {
                flex-direction: column;
                align-items: stretch;
            }

            #story-glass-wrapper .quick-action-btn {
                justify-content: center;
            }

            #story-glass-wrapper .mission-vision-grid {
                grid-template-columns: 1fr;
            }

            #story-glass-wrapper .values-grid {
                grid-template-columns: 1fr;
            }

            #story-glass-wrapper .credential-badges {
                flex-direction: column;
                align-items: center;
            }
        }

        /* Fallback for unsupported browsers */
        @supports not (backdrop-filter: blur(10px)) {
            #story-glass-wrapper .glass-card,
            #story-glass-wrapper .quick-action-btn {
                background: rgba(255, 255, 255, 0.95);
            }

            [data-theme="dark"] #story-glass-wrapper .glass-card,
            [data-theme="dark"] #story-glass-wrapper .quick-action-btn {
                background: rgba(15, 23, 42, 0.95);
            }
        }
    </style>

    <!-- Welcome Hero Section -->
    <div class="nexus-welcome-hero">
        <h1 class="nexus-welcome-title">Our Story</h1>
        <p class="nexus-welcome-subtitle">The history and mission of hOUR Timebank CLG - building community through the power of shared time.</p>

        <div class="nexus-smart-buttons">
            <a href="<?= $basePath ?>/impact-report" class="nexus-smart-btn nexus-smart-btn-primary">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Impact Report</span>
            </a>
            <a href="<?= $basePath ?>/timebanking-guide" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-book-open-reader"></i>
                <span>How It Works</span>
            </a>
            <a href="<?= $basePath ?>/partner" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-handshake"></i>
                <span>Partner With Us</span>
            </a>
        </div>
    </div>

    <!-- Mission & Vision Section -->
    <div class="section-header">
        <h2>Mission & Vision</h2>
    </div>

    <div class="mission-vision-grid">
        <!-- Mission -->
        <div class="glass-card">
            <div class="card-header-gradient">
                <span class="icon">🚩</span>
                <h3>Our Mission</h3>
            </div>
            <div class="card-body">
                <p>To connect and empower Irish communities by facilitating the exchange of skills, talents, and support, where every hour given is an hour received, building a resilient and equitable society based on mutual respect.</p>
            </div>
        </div>

        <!-- Vision -->
        <div class="glass-card">
            <div class="card-header-gradient">
                <span class="icon">👁️</span>
                <h3>Our Vision</h3>
            </div>
            <div class="card-body">
                <p>An interconnected Ireland where every individual feels valued and supported, and where the power of shared time and talent creates strong, resilient, and thriving local communities.</p>
            </div>
        </div>
    </div>

    <!-- Values Section -->
    <div class="section-header">
        <h2>The Values That Guide Every Hour</h2>
    </div>

    <div class="glass-card">
        <div class="card-body">
            <div class="values-grid">
                <div class="value-item">
                    <div class="value-icon">⚖️</div>
                    <h4>Reciprocity & Equality</h4>
                    <p>We believe in a two-way street; everyone has something to give. One hour equals one hour, no matter the service.</p>
                </div>

                <div class="value-item">
                    <div class="value-icon">🕸️</div>
                    <h4>Inclusion & Connection</h4>
                    <p>We welcome people of all ages, backgrounds, and abilities. We exist to reduce isolation and build meaningful relationships.</p>
                </div>

                <div class="value-item">
                    <div class="value-icon">💚</div>
                    <h4>Empowerment & Resilience</h4>
                    <p>We provide a platform for individuals to recognize their own value and actively participate in building community.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Professional Foundation Section -->
    <div class="section-header">
        <h2>Our Professional Foundation</h2>
    </div>

    <div class="glass-card">
        <div class="card-header-gradient">
            <span class="icon">🏛️</span>
            <h3>hOUR Timebank CLG</h3>
            <span class="badge">Est. 2017</span>
        </div>
        <div class="card-body">
            <p>Our journey began in 2012 with the Clonakilty Favour Exchange. To ensure long-term stability and impact, the directors established hOUR Timebank CLG as a formal, registered Irish charity in 2017.</p>

            <div class="credential-badges">
                <span class="credential-badge"><span>✓</span> Registered Charity</span>
                <span class="credential-badge"><span>✓</span> Rethink Ireland Awardee</span>
                <span class="credential-badge"><span>✓</span> 1:16 SROI Impact</span>
            </div>

            <div class="cta-box">
                <h4>Want proof of our impact?</h4>
                <p>We have an independently verified Social Return on Investment (SROI) study.</p>
                <a href="<?= $basePath ?>/impact-report" class="btn btn--primary">
                    📊 View Full Report
                </a>
            </div>
        </div>
    </div>

</div><!-- #story-glass-wrapper -->
</div>

<script>
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

// Button Press States
document.querySelectorAll('.quick-action-btn, .btn--primary').forEach(btn => {
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
        meta.content = '#be185d';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#be185d');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();
</script>

<?php require __DIR__ . '/../../../..' . '/layouts/modern/footer.php'; ?>

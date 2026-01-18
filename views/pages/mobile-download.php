<?php
\Nexus\Core\SEO::setTitle('Download the App');
\Nexus\Core\SEO::setDescription('Download the NEXUS Android app or add to your home screen for the full mobile experience.');
?>
<?php
?>
<!-- Default layout - redirect to social layout for consistency -->
<style>
    .mobile-download-page {
        animation: fadeInUp 0.4s ease-out;
        max-width: 600px;
        margin: 0 auto;
        padding: 160px 20px 40px; /* Top padding to clear fixed header (52px utility + 70px navbar + buffer) */
    }

    @media (max-width: 900px) {
        .mobile-download-page {
            padding-top: 20px; /* Mobile doesn't have the fixed header bars */
        }
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .download-header {
        text-align: center;
        padding: 30px 20px;
        background: linear-gradient(135deg, #10b981, #059669);
        border-radius: 16px;
        color: white;
        margin-bottom: 20px;
    }

    .download-header-icon {
        width: 80px;
        height: 80px;
        background: white;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    }

    .download-header-icon img {
        width: 60px;
        height: 60px;
        border-radius: 12px;
    }

    .download-header h1 {
        margin: 0 0 8px;
        font-size: 1.75rem;
        font-weight: 800;
    }

    .download-header p {
        margin: 0;
        opacity: 0.9;
        font-size: 1rem;
    }

    .download-section {
        background: white;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 16px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .download-section-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 1px solid #e5e7eb;
    }

    .download-section-icon {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #10b981, #059669);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }

    .download-section-title {
        flex: 1;
    }

    .download-section-title h2 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: #1f2937;
    }

    .download-section-title span {
        font-size: 0.85rem;
        color: #6b7280;
    }

    .download-section-tag {
        padding: 4px 10px;
        background: #10b981;
        color: white;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .download-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        padding: 16px 24px;
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        margin-bottom: 20px;
        transition: transform 0.1s, box-shadow 0.2s;
    }

    .download-btn:hover {
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        color: white;
    }

    .download-btn:active {
        transform: scale(0.98);
    }

    .install-steps {
        background: #f9fafb;
        border-radius: 12px;
        padding: 16px;
    }

    .install-steps-title {
        font-weight: 700;
        color: #374151;
        margin-bottom: 12px;
        font-size: 0.9rem;
    }

    .install-step {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #e5e7eb;
    }

    .install-step:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .install-step-num {
        width: 24px;
        height: 24px;
        background: #10b981;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .install-step-text {
        font-size: 0.9rem;
        color: #4b5563;
        line-height: 1.5;
    }

    .install-step-text strong {
        color: #1f2937;
    }

    .warning-note {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 14px;
        background: #fef3c7;
        border-radius: 10px;
        margin-top: 16px;
    }

    .warning-note i {
        color: #d97706;
        margin-top: 2px;
    }

    .warning-note-text {
        font-size: 0.85rem;
        color: #92400e;
        line-height: 1.4;
    }

    .pwa-alternative {
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        border: 1px solid #bfdbfe;
        border-radius: 16px;
        padding: 24px;
        text-align: center;
    }

    .pwa-alternative-header {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-bottom: 12px;
        color: #1e40af;
        font-weight: 700;
    }

    .pwa-alternative p {
        color: #3b82f6;
        font-size: 0.95rem;
        margin: 0 0 16px;
        line-height: 1.5;
    }

    .pwa-benefits {
        display: flex;
        justify-content: center;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .pwa-benefit {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
        color: #1e40af;
    }

    .pwa-benefit i {
        color: #10b981;
    }

    .pwa-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
    }

    .pwa-btn:hover {
        color: white;
    }

    .ios-only { display: none; }
    .android-only { display: block; }
</style>

<div class="mobile-download-page">
    <!-- Header -->
    <div class="download-header">
        <div class="download-header-icon">
            <img src="/assets/images/pwa/icon-192x192.png" alt="NEXUS">
        </div>
        <h1>NEXUS for Android</h1>
        <p>The native mobile experience</p>
    </div>

    <!-- Android Download Section -->
    <div class="download-section android-only" id="androidSection">
        <div class="download-section-header">
            <div class="download-section-icon">
                <i class="fa-brands fa-android"></i>
            </div>
            <div class="download-section-title">
                <h2>Download Android App</h2>
                <span>Version 1.0 - Direct Download</span>
            </div>
            <span class="download-section-tag">APK</span>
        </div>

        <a href="/downloads/nexus-latest.apk" class="download-btn">
            <i class="fa-solid fa-download"></i>
            Download APK (3.8 MB)
        </a>

        <div class="install-steps">
            <div class="install-steps-title">
                <i class="fa-solid fa-list-check"></i> How to Install
            </div>
            <div class="install-step">
                <span class="install-step-num">1</span>
                <span class="install-step-text">Tap <strong>Download APK</strong> above</span>
            </div>
            <div class="install-step">
                <span class="install-step-num">2</span>
                <span class="install-step-text">When the download completes, tap the notification or open your <strong>Downloads</strong> folder</span>
            </div>
            <div class="install-step">
                <span class="install-step-num">3</span>
                <span class="install-step-text">If prompted, tap <strong>Settings</strong> and enable <strong>"Allow from this source"</strong></span>
            </div>
            <div class="install-step">
                <span class="install-step-num">4</span>
                <span class="install-step-text">Tap <strong>Install</strong> and then <strong>Open</strong> when complete</span>
            </div>
        </div>

        <div class="warning-note">
            <i class="fa-solid fa-shield-halved"></i>
            <span class="warning-note-text">
                <strong>Why the warning?</strong> This app isn't from the Play Store yet, so Android asks you to confirm. This is normal for direct downloads - the app is safe.
            </span>
        </div>
    </div>

    <!-- iOS Message -->
    <div class="download-section ios-only" id="iosSection">
        <div class="download-section-header">
            <div class="download-section-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                <i class="fa-brands fa-apple"></i>
            </div>
            <div class="download-section-title">
                <h2>iPhone / iPad</h2>
                <span>Use the Web App instead</span>
            </div>
        </div>
        <p style="color: #6b7280; margin: 0 0 16px; line-height: 1.6;">
            The Android APK won't work on iOS devices. But don't worry - you can add NEXUS to your home screen for the same app-like experience!
        </p>
        <a href="/" class="download-btn" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
            <i class="fa-solid fa-arrow-left"></i>
            Go Back & Add to Home Screen
        </a>
    </div>

    <!-- PWA Alternative -->
    <div class="pwa-alternative">
        <div class="pwa-alternative-header">
            <i class="fa-solid fa-lightbulb"></i>
            <span>Changed your mind?</span>
        </div>
        <p>
            The <strong>Web App (PWA)</strong> works on all devices - Android, iPhone, and Desktop - with no download required.
        </p>
        <div class="pwa-benefits">
            <span class="pwa-benefit"><i class="fa-solid fa-check"></i> Always up to date</span>
            <span class="pwa-benefit"><i class="fa-solid fa-check"></i> Works everywhere</span>
            <span class="pwa-benefit"><i class="fa-solid fa-check"></i> No permissions needed</span>
        </div>
        <a href="/" class="pwa-btn">
            <i class="fa-solid fa-globe"></i>
            Use the Web App Instead
        </a>
    </div>
</div>

<script>
(function() {
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
    const androidSection = document.getElementById('androidSection');
    const iosSection = document.getElementById('iosSection');

    if (isIOS) {
        if (androidSection) androidSection.style.display = 'none';
        if (iosSection) iosSection.style.display = 'block';
    }
})();
</script>
<?php
// Mobile Download Page
$pageTitle = "Mobile App";
$hideHero = true;

$basePath = \Nexus\Core\TenantContext::getBasePath();
require __DIR__ . '/layouts/modern/header.php';
?>

<style>
/* ========================================
   MOBILE DOWNLOAD - GLASSMORPHISM 2025
   Theme: Sky Blue (#0ea5e9)
   ======================================== */

#mobile-glass-wrapper {
    --mobile-theme: #0ea5e9;
    --mobile-theme-rgb: 14, 165, 233;
    --glass-bg: rgba(255, 255, 255, 0.25);
    --glass-border: rgba(255, 255, 255, 0.3);
    --glass-shadow: rgba(14, 165, 233, 0.15);
    min-height: 100vh;
    padding: 0;
    margin: -2rem -1rem;
    background: linear-gradient(135deg, #0ea5e9 0%, #38bdf8 25%, #7dd3fc 50%, #0ea5e9 75%, #0284c7 100%);
    background-size: 400% 400%;
    animation: mobileGradientShift 15s ease infinite;
}

@keyframes mobileGradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

#mobile-glass-wrapper .mobile-inner {
    max-width: 1000px;
    margin: 0 auto;
    padding: 2rem 1.5rem 4rem;
}

/* Page Header */
#mobile-glass-wrapper .mobile-page-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-top: 1rem;
}

#mobile-glass-wrapper .mobile-page-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    color: white;
    margin: 0 0 0.5rem 0;
    text-shadow: 0 2px 20px rgba(0, 0, 0, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

#mobile-glass-wrapper .mobile-page-header p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.1rem;
    margin: 0;
}

/* Main Grid */
#mobile-glass-wrapper .mobile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

/* Glass Card Base */
#mobile-glass-wrapper .mobile-glass-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px) saturate(120%);
    -webkit-backdrop-filter: blur(20px) saturate(120%);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    box-shadow:
        0 8px 32px var(--glass-shadow),
        inset 0 1px 0 rgba(255, 255, 255, 0.3);
    overflow: hidden;
    transition: all 0.3s ease;
}

#mobile-glass-wrapper .mobile-glass-card:hover {
    transform: translateY(-4px);
    box-shadow:
        0 16px 48px var(--glass-shadow),
        inset 0 1px 0 rgba(255, 255, 255, 0.4);
}

/* Android Card */
#mobile-glass-wrapper .android-card {
    text-align: center;
    padding: 2.5rem;
}

#mobile-glass-wrapper .android-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.2));
}

#mobile-glass-wrapper .android-card h2 {
    font-size: 1.75rem;
    font-weight: 800;
    color: white;
    margin: 0 0 0.75rem 0;
}

#mobile-glass-wrapper .android-card .description {
    color: rgba(255, 255, 255, 0.85);
    margin-bottom: 1.5rem;
    line-height: 1.6;
}

#mobile-glass-wrapper .coming-soon-badge {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.3) 0%, rgba(245, 158, 11, 0.3) 100%);
    border: 1px solid rgba(251, 191, 36, 0.5);
    color: #fef3c7;
    padding: 12px 20px;
    border-radius: 12px;
    font-weight: 700;
    margin-bottom: 2rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

#mobile-glass-wrapper .dev-section {
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    padding-top: 1.5rem;
    margin-top: 0.5rem;
}

#mobile-glass-wrapper .dev-section h3 {
    color: white;
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
}

#mobile-glass-wrapper .dev-section p {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.95rem;
    margin-bottom: 1rem;
}

#mobile-glass-wrapper .dev-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 14px 24px;
    background: rgba(255, 255, 255, 0.15);
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-radius: 12px;
    color: white;
    font-weight: 700;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

#mobile-glass-wrapper .dev-btn:hover {
    background: white;
    color: var(--mobile-theme);
    border-color: white;
    transform: scale(1.02);
}

#mobile-glass-wrapper .build-info {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 1rem;
}

/* QR Code Card */
#mobile-glass-wrapper .qr-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2.5rem;
    text-align: center;
}

#mobile-glass-wrapper .qr-card h3 {
    color: white;
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0 0 1.5rem 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

#mobile-glass-wrapper .qr-container {
    background: white;
    padding: 20px;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
}

#mobile-glass-wrapper .qr-hint {
    margin-top: 1.5rem;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Instructions Card */
#mobile-glass-wrapper .instructions-card {
    margin-top: 0;
}

#mobile-glass-wrapper .instructions-header {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 100%);
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    gap: 10px;
}

#mobile-glass-wrapper .instructions-header h3 {
    color: white;
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0;
}

#mobile-glass-wrapper .instructions-body {
    padding: 1.5rem;
}

#mobile-glass-wrapper .step-item {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

#mobile-glass-wrapper .step-item:last-child {
    margin-bottom: 0;
}

#mobile-glass-wrapper .step-number {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, white 0%, rgba(255, 255, 255, 0.9) 100%);
    color: var(--mobile-theme);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

#mobile-glass-wrapper .step-content strong {
    color: white;
    font-size: 1rem;
    display: block;
    margin-bottom: 4px;
}

#mobile-glass-wrapper .step-content p {
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.5;
}

/* Features Bar */
#mobile-glass-wrapper .features-bar {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
}

#mobile-glass-wrapper .feature-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 50px;
    padding: 10px 18px;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

#mobile-glass-wrapper .feature-chip span:first-child {
    font-size: 1.1rem;
}

/* ========================================
   DARK MODE
   ======================================== */
[data-theme="dark"] #mobile-glass-wrapper {
    --glass-bg: rgba(15, 23, 42, 0.6);
    --glass-border: rgba(255, 255, 255, 0.1);
    --glass-shadow: rgba(0, 0, 0, 0.3);
    background: linear-gradient(135deg, #0c4a6e 0%, #075985 25%, #0369a1 50%, #0ea5e9 75%, #0284c7 100%);
    background-size: 400% 400%;
}

[data-theme="dark"] #mobile-glass-wrapper .mobile-glass-card {
    background: var(--glass-bg);
    border-color: var(--glass-border);
}

[data-theme="dark"] #mobile-glass-wrapper .mobile-page-header h1,
[data-theme="dark"] #mobile-glass-wrapper .android-card h2,
[data-theme="dark"] #mobile-glass-wrapper .qr-card h3,
[data-theme="dark"] #mobile-glass-wrapper .instructions-header h3,
[data-theme="dark"] #mobile-glass-wrapper .step-content strong,
[data-theme="dark"] #mobile-glass-wrapper .dev-section h3 {
    color: #f1f5f9;
}

[data-theme="dark"] #mobile-glass-wrapper .android-card .description,
[data-theme="dark"] #mobile-glass-wrapper .mobile-page-header p,
[data-theme="dark"] #mobile-glass-wrapper .dev-section p,
[data-theme="dark"] #mobile-glass-wrapper .step-content p {
    color: rgba(241, 245, 249, 0.8);
}

[data-theme="dark"] #mobile-glass-wrapper .coming-soon-badge {
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.2) 0%, rgba(245, 158, 11, 0.2) 100%);
    border-color: rgba(251, 191, 36, 0.4);
}

[data-theme="dark"] #mobile-glass-wrapper .dev-btn {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.3);
}

[data-theme="dark"] #mobile-glass-wrapper .dev-btn:hover {
    background: white;
    color: var(--mobile-theme);
}

[data-theme="dark"] #mobile-glass-wrapper .step-number {
    background: linear-gradient(135deg, var(--mobile-theme) 0%, #38bdf8 100%);
    color: white;
}

[data-theme="dark"] #mobile-glass-wrapper .feature-chip {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.15);
    color: #f1f5f9;
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 768px) {
    #mobile-glass-wrapper .mobile-inner {
        padding: 1.5rem 1rem 3rem;
    }

    #mobile-glass-wrapper .mobile-page-header h1 {
        font-size: 1.8rem;
    }

    #mobile-glass-wrapper .mobile-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    #mobile-glass-wrapper .android-card,
    #mobile-glass-wrapper .qr-card {
        padding: 2rem 1.5rem;
    }

    #mobile-glass-wrapper .features-bar {
        gap: 0.75rem;
    }

    #mobile-glass-wrapper .feature-chip {
        padding: 8px 14px;
        font-size: 0.85rem;
    }

    @keyframes mobileGradientShift {
        0%, 100% { background-position: 50% 50%; }
    }
}

/* Browser Fallback */
@supports not (backdrop-filter: blur(10px)) {
    #mobile-glass-wrapper .mobile-glass-card,
    #mobile-glass-wrapper .feature-chip {
        background: rgba(14, 165, 233, 0.85);
    }

    [data-theme="dark"] #mobile-glass-wrapper .mobile-glass-card,
    [data-theme="dark"] #mobile-glass-wrapper .feature-chip {
        background: rgba(15, 23, 42, 0.9);
    }
}
</style>

<div id="mobile-glass-wrapper">
    <div class="mobile-inner">

        <!-- Page Header -->
        <div class="mobile-page-header">
            <h1>📱 Mobile App</h1>
            <p>Install Project NEXUS directly on your device</p>
        </div>

        <!-- Features Bar -->
        <div class="features-bar">
            <div class="feature-chip">
                <span>⚡</span>
                <span>Fast Performance</span>
            </div>
            <div class="feature-chip">
                <span>🔔</span>
                <span>Push Notifications</span>
            </div>
            <div class="feature-chip">
                <span>📴</span>
                <span>Offline Support</span>
            </div>
            <div class="feature-chip">
                <span>🔒</span>
                <span>Secure & Private</span>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="mobile-grid">

            <!-- Android Download Card -->
            <div class="mobile-glass-card android-card">
                <div class="android-icon">🤖</div>
                <h2>For Android</h2>
                <p class="description">
                    Experience the full power of the platform. Faster performance, push notifications, and offline support.
                </p>

                <a href="/downloads/nexus-latest.apk" class="dev-btn" id="downloadApkBtn" style="background: rgba(16, 185, 129, 0.3); border-color: rgba(16, 185, 129, 0.6); margin-bottom: 1rem;">
                    <i class="fa-solid fa-download"></i>
                    Download APK
                </a>
                <p class="build-info" id="apkStatus">Checking availability...</p>

                <div class="dev-section">
                    <h3>Alternative Install</h3>
                    <p>Add to your home screen for an instant app experience.</p>
                    <a href="<?= $basePath ?>/" class="dev-btn" id="pwaInstallBtn">
                        <i class="fa-solid fa-plus-circle"></i>
                        Add to Home Screen
                    </a>
                    <p class="build-info">Works on any device</p>
                </div>

                <script>
                // Check if APK exists and update UI
                fetch('/downloads/nexus-latest.apk', { method: 'HEAD' })
                    .then(response => {
                        const statusEl = document.getElementById('apkStatus');
                        const btnEl = document.getElementById('downloadApkBtn');
                        if (response.ok) {
                            statusEl.textContent = 'Ready to download';
                            statusEl.style.color = 'rgba(16, 185, 129, 0.9)';
                        } else {
                            statusEl.textContent = 'APK coming soon - use Add to Home Screen below';
                            btnEl.style.opacity = '0.5';
                            btnEl.style.pointerEvents = 'none';
                        }
                    })
                    .catch(() => {
                        document.getElementById('apkStatus').textContent = 'Use Add to Home Screen below';
                        document.getElementById('downloadApkBtn').style.display = 'none';
                    });
                </script>
            </div>

            <!-- QR Code Card -->
            <div class="mobile-glass-card qr-card">
                <h3><span>📷</span> Scan to Install</h3>
                <div class="qr-container">
                    <div id="qrcode"></div>
                </div>
                <p class="qr-hint">
                    <span>📲</span>
                    Scan with your phone camera
                </p>
            </div>

        </div>

        <!-- Installation Instructions -->
        <div class="mobile-glass-card instructions-card">
            <div class="instructions-header">
                <span style="font-size: 1.3rem;">📋</span>
                <h3>Installation Instructions</h3>
            </div>
            <div class="instructions-body">
                <div class="step-item">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <strong>Download the File</strong>
                        <p>Click the download button above or scan the QR code to save the <code style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 4px;">.apk</code> file to your device.</p>
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <strong>Open the File</strong>
                        <p>Tap the notification that says "Download Completed" or find the file in your "Downloads" folder.</p>
                    </div>
                </div>

                <div class="step-item">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <strong>Allow Installation</strong>
                        <p>If prompted, go to Settings and allow "Install from Unknown Sources" for your browser. This is required for direct downloads.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- QR Code Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Generate QR Code pointing to the APK
    const downloadUrl = window.location.origin + '/downloads/nexus-latest.apk';

    new QRCode(document.getElementById("qrcode"), {
        text: downloadUrl,
        width: 180,
        height: 180,
        colorDark: "#0ea5e9",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });
});
</script>

<?php require __DIR__ . '/layouts/modern/footer.php'; ?>

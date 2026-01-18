<?php
// Resources Download Page - Glassmorphism 2025
$pageTitle = "Downloading Resource";
$hideHero = true;

Nexus\Core\SEO::setTitle('Downloading - ' . htmlspecialchars($resource['title'] ?? 'Resource'));

require __DIR__ . '/../../layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

// Determine file icon
$icon = '📄';
if (strpos($resource['file_type'] ?? '', 'image') !== false) $icon = '🖼️';
if (strpos($resource['file_type'] ?? '', 'zip') !== false) $icon = '📦';
if (strpos($resource['file_type'] ?? '', 'pdf') !== false) $icon = '📕';
if (strpos($resource['file_type'] ?? '', 'doc') !== false) $icon = '📝';
if (strpos($resource['file_type'] ?? '', 'xls') !== false) $icon = '📊';
if (strpos($resource['file_type'] ?? '', 'video') !== false) $icon = '🎬';

$size = round(($resource['file_size'] ?? 0) / 1024) . ' KB';
if (($resource['file_size'] ?? 0) > 1024 * 1024) {
    $size = round(($resource['file_size'] ?? 0) / 1024 / 1024, 1) . ' MB';
}
?>

<style>
    .download-page-wrapper {
        min-height: 60vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
    }

    .download-card {
        background: linear-gradient(135deg,
            rgba(255, 255, 255, 0.85),
            rgba(255, 255, 255, 0.7));
        backdrop-filter: blur(20px) saturate(120%);
        -webkit-backdrop-filter: blur(20px) saturate(120%);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 24px;
        box-shadow: 0 12px 48px rgba(31, 38, 135, 0.15);
        max-width: 500px;
        width: 100%;
        text-align: center;
        overflow: hidden;
    }

    [data-theme="dark"] .download-card {
        background: linear-gradient(135deg,
            rgba(15, 23, 42, 0.7),
            rgba(30, 41, 59, 0.6));
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 12px 48px rgba(0, 0, 0, 0.5);
    }

    .download-card-header {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        padding: 30px;
        position: relative;
        overflow: hidden;
    }

    .download-card-header::before {
        content: '';
        position: absolute;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        top: -80px;
        right: -80px;
    }

    .download-card-header::after {
        content: '';
        position: absolute;
        width: 100px;
        height: 100px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 50%;
        bottom: -30px;
        left: -30px;
    }

    .file-icon-large {
        font-size: 4rem;
        position: relative;
        z-index: 1;
        filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
    }

    .download-card-body {
        padding: 30px;
    }

    .resource-title-download {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--htb-text-main);
        margin: 0 0 8px 0;
        line-height: 1.3;
    }

    .resource-meta {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin: 16px 0 24px 0;
        color: var(--htb-text-muted);
        font-size: 0.9rem;
    }

    .resource-meta span {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .resource-meta i {
        color: #6366f1;
    }

    .countdown-section {
        margin: 24px 0;
    }

    .countdown-text {
        font-size: 1rem;
        color: var(--htb-text-muted);
        margin-bottom: 12px;
    }

    .countdown-number {
        font-size: 3rem;
        font-weight: 800;
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        line-height: 1;
    }

    .progress-ring {
        width: 120px;
        height: 120px;
        margin: 0 auto 16px;
        position: relative;
    }

    .progress-ring svg {
        transform: rotate(-90deg);
    }

    .progress-ring-circle {
        fill: none;
        stroke: rgba(99, 102, 241, 0.15);
        stroke-width: 8;
    }

    .progress-ring-progress {
        fill: none;
        stroke: #6366f1;
        stroke-width: 8;
        stroke-linecap: round;
        stroke-dasharray: 314;
        stroke-dashoffset: 0;
        transition: stroke-dashoffset 1s linear;
    }

    .progress-ring-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
    }

    .download-status {
        font-size: 1rem;
        color: var(--htb-text-main);
        margin: 16px 0;
        min-height: 24px;
    }

    .download-status.success {
        color: #10b981;
    }

    .download-card-footer {
        padding: 20px 30px 30px;
        border-top: 1px solid rgba(0, 0, 0, 0.06);
    }

    [data-theme="dark"] .download-card-footer {
        border-top-color: rgba(255, 255, 255, 0.1);
    }

    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 14px 28px;
        border-radius: 14px;
        font-weight: 600;
        font-size: 0.95rem;
        text-decoration: none;
        transition: all 0.2s ease;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(129, 140, 248, 0.1));
        color: #6366f1;
        border: 2px solid rgba(99, 102, 241, 0.3);
    }

    .back-btn:hover {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: white;
        border-color: transparent;
        transform: translateY(-2px);
    }

    [data-theme="dark"] .back-btn {
        background: rgba(99, 102, 241, 0.2);
        color: #a5b4fc;
        border-color: rgba(99, 102, 241, 0.4);
    }

    .manual-download-link {
        display: block;
        margin-top: 16px;
        font-size: 0.85rem;
        color: var(--htb-text-muted);
    }

    .manual-download-link a {
        color: #6366f1;
        text-decoration: underline;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    .downloading .file-icon-large {
        animation: pulse 1s ease-in-out infinite;
    }
</style>

<div class="htb-container-full">
    <div class="download-page-wrapper">
        <div class="download-card" id="downloadCard">
            <div class="download-card-header">
                <span class="file-icon-large"><?= $icon ?></span>
            </div>

            <div class="download-card-body">
                <h1 class="resource-title-download"><?= htmlspecialchars($resource['title']) ?></h1>

                <div class="resource-meta">
                    <span><i class="fa-solid fa-file"></i> <?= $size ?></span>
                    <span><i class="fa-solid fa-download"></i> <?= ($resource['downloads'] ?? 0) + 1 ?> downloads</span>
                </div>

                <div class="countdown-section">
                    <div class="progress-ring">
                        <svg width="120" height="120">
                            <circle class="progress-ring-circle" cx="60" cy="60" r="50"></circle>
                            <circle class="progress-ring-progress" id="progressCircle" cx="60" cy="60" r="50"></circle>
                        </svg>
                        <div class="progress-ring-text">
                            <span class="countdown-number" id="countdown">5</span>
                        </div>
                    </div>
                    <p class="countdown-text">Your download will start automatically...</p>
                </div>

                <div class="download-status" id="downloadStatus">
                    Preparing your file...
                </div>
            </div>

            <div class="download-card-footer">
                <a href="<?= $basePath ?>/resources" class="back-btn">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Resources
                </a>

                <p class="manual-download-link">
                    Download not starting? <a href="<?= $basePath ?>/resources/<?= $resource['id'] ?>/file" id="manualDownload">Click here</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const card = document.getElementById('downloadCard');
    const countdown = document.getElementById('countdown');
    const progressCircle = document.getElementById('progressCircle');
    const statusEl = document.getElementById('downloadStatus');
    const circumference = 2 * Math.PI * 50; // radius = 50

    progressCircle.style.strokeDasharray = circumference;
    progressCircle.style.strokeDashoffset = 0;

    let seconds = 5;
    card.classList.add('downloading');

    const timer = setInterval(() => {
        seconds--;
        countdown.textContent = seconds;

        // Update progress ring (fill from empty to full as countdown decreases)
        const offset = circumference * (seconds / 5);
        progressCircle.style.strokeDashoffset = circumference - offset;

        if (seconds <= 0) {
            clearInterval(timer);
            triggerDownload();
        }
    }, 1000);

    function triggerDownload() {
        statusEl.textContent = 'Starting download...';
        statusEl.classList.add('success');
        card.classList.remove('downloading');

        // Create hidden iframe for download to prevent page navigation
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = '<?= $basePath ?>/resources/<?= $resource['id'] ?>/file';
        document.body.appendChild(iframe);

        // Update status after a moment
        setTimeout(() => {
            statusEl.innerHTML = '<i class="fa-solid fa-check"></i> Download started!';
            countdown.textContent = '✓';
        }, 500);

        // Redirect back to resources after 5 seconds
        setTimeout(() => {
            statusEl.textContent = 'Redirecting to resources...';
            setTimeout(() => {
                window.location.href = '<?= $basePath ?>/resources';
            }, 1000);
        }, 4000);
    }

    // Manual download link also uses iframe method
    document.getElementById('manualDownload').addEventListener('click', function(e) {
        e.preventDefault();
        clearInterval(timer);
        triggerDownload();
    });
})();
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>

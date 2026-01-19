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

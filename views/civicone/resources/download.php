<?php
// CivicOne View: Resources Download Page
$heroTitle = "Downloading Resource";
$heroSub = "Your download will begin shortly.";
$heroType = 'Download';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

// Determine file icon
$icon = '📄';
if (strpos($resource['file_type'] ?? '', 'image') !== false) $icon = '🖼️';
if (strpos($resource['file_type'] ?? '', 'zip') !== false) $icon = '📦';
if (strpos($resource['file_type'] ?? '', 'pdf') !== false) $icon = '📕';
if (strpos($resource['file_type'] ?? '', 'doc') !== false) $icon = '📝';

$size = round(($resource['file_size'] ?? 0) / 1024) . ' KB';
if (($resource['file_size'] ?? 0) > 1024 * 1024) {
    $size = round(($resource['file_size'] ?? 0) / 1024 / 1024, 1) . ' MB';
}
?>

<style>
    .download-container {
        max-width: 500px;
        margin: 40px auto;
        padding: 0 20px;
    }

    .download-card {
        background: #fff;
        border: 4px solid #000;
        padding: 40px;
        text-align: center;
    }

    .file-icon {
        font-size: 4rem;
        margin-bottom: 20px;
    }

    .resource-title {
        font-size: 1.5rem;
        font-weight: bold;
        margin: 0 0 10px 0;
        text-transform: uppercase;
    }

    .resource-meta {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 30px;
    }

    .countdown-number {
        font-size: 4rem;
        font-weight: 900;
        margin: 20px 0;
    }

    .countdown-text {
        font-size: 1rem;
        margin-bottom: 30px;
    }

    .download-status {
        font-size: 1rem;
        font-weight: bold;
        min-height: 24px;
        margin-bottom: 20px;
    }

    .download-status.success {
        color: #059669;
    }

    .back-link {
        display: inline-block;
        background: #000;
        color: #fff;
        padding: 12px 24px;
        text-decoration: none;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .back-link:hover {
        background: #333;
    }

    .manual-link {
        display: block;
        margin-top: 20px;
        font-size: 0.85rem;
        color: #666;
    }

    .manual-link a {
        color: #000;
        text-decoration: underline;
    }
</style>

<div class="download-container">
    <div class="download-card">
        <div class="file-icon"><?= $icon ?></div>

        <h1 class="resource-title"><?= htmlspecialchars($resource['title']) ?></h1>

        <div class="resource-meta">
            <?= $size ?> &bull; <?= ($resource['downloads'] ?? 0) + 1 ?> downloads
        </div>

        <div class="countdown-number" id="countdown">5</div>
        <div class="countdown-text">Your download will start automatically...</div>

        <div class="download-status" id="downloadStatus">
            Preparing your file...
        </div>

        <a href="<?= $basePath ?>/resources" class="back-link">
            &larr; Back to Resources
        </a>

        <p class="manual-link">
            Download not starting? <a href="<?= $basePath ?>/resources/<?= $resource['id'] ?>/file" id="manualDownload">Click here</a>
        </p>
    </div>
</div>

<script>
(function() {
    const countdown = document.getElementById('countdown');
    const statusEl = document.getElementById('downloadStatus');

    let seconds = 5;

    const timer = setInterval(() => {
        seconds--;
        countdown.textContent = seconds;

        if (seconds <= 0) {
            clearInterval(timer);
            triggerDownload();
        }
    }, 1000);

    function triggerDownload() {
        statusEl.textContent = 'Starting download...';
        statusEl.classList.add('success');

        // Create hidden iframe for download
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = '<?= $basePath ?>/resources/<?= $resource['id'] ?>/file';
        document.body.appendChild(iframe);

        setTimeout(() => {
            statusEl.textContent = 'Download started!';
            countdown.textContent = '✓';
        }, 500);

        // Redirect back after 5 seconds
        setTimeout(() => {
            statusEl.textContent = 'Redirecting to resources...';
            setTimeout(() => {
                window.location.href = '<?= $basePath ?>/resources';
            }, 1000);
        }, 4000);
    }

    document.getElementById('manualDownload').addEventListener('click', function(e) {
        e.preventDefault();
        clearInterval(timer);
        triggerDownload();
    });
})();
</script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>

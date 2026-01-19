<?php
// Federation Offline Page
$pageTitle = $pageTitle ?? "You're Offline";
$hideHero = true;
$hideNav = true;

// Minimal header without requiring database
$basePath = $basePath ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#8b5cf6">
    <title><?= htmlspecialchars($pageTitle) ?> - Federation</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/federation-offline.css">
</head>
<body>
    <div class="offline-container">
        <div class="offline-icon">
            <i class="fa-solid fa-globe"></i>
        </div>

        <h1>You're Offline</h1>
        <p class="offline-message">
            Federation features require an internet connection to communicate with partner timebanks.
        </p>

        <div class="offline-features">
            <h3><i class="fa-solid fa-check-circle"></i> Available Offline</h3>
            <ul>
                <li><i class="fa-solid fa-clock-rotate-left"></i> Previously viewed pages (cached)</li>
                <li><i class="fa-solid fa-circle-question"></i> Federation help & FAQ</li>
                <li><i class="fa-solid fa-sliders"></i> Your settings (read-only)</li>
            </ul>
        </div>

        <div class="offline-actions">
            <button class="offline-btn offline-btn-primary" id="retryBtn" onclick="retryConnection()">
                <i class="fa-solid fa-rotate retry-spinner"></i>
                <span class="retry-text"><i class="fa-solid fa-wifi"></i> Try Again</span>
            </button>
            <a href="/dashboard" class="offline-btn offline-btn-secondary">
                <i class="fa-solid fa-home"></i>
                Go to Dashboard
            </a>
        </div>

        <div class="offline-status" id="connectionStatus">
            <i class="fa-solid fa-wifi-slash"></i>
            <span>No internet connection</span>
        </div>
    </div>

    <script>
        const retryBtn = document.getElementById('retryBtn');
        const statusDiv = document.getElementById('connectionStatus');

        function retryConnection() {
            retryBtn.classList.add('retrying');
            retryBtn.disabled = true;

            // Try to fetch a simple endpoint
            fetch('/api/ping', { method: 'HEAD', cache: 'no-store' })
                .then(response => {
                    if (response.ok) {
                        // Online! Redirect back
                        statusDiv.classList.add('online');
                        statusDiv.innerHTML = '<i class="fa-solid fa-wifi"></i><span>Connected! Redirecting...</span>';
                        setTimeout(() => {
                            window.location.href = '/federation';
                        }, 500);
                    } else {
                        throw new Error('Not connected');
                    }
                })
                .catch(() => {
                    retryBtn.classList.remove('retrying');
                    retryBtn.disabled = false;
                    // Shake animation
                    statusDiv.style.animation = 'none';
                    statusDiv.offsetHeight; // Trigger reflow
                    statusDiv.style.animation = 'shake 0.5s ease';
                });
        }

        // Auto-detect when connection comes back
        window.addEventListener('online', () => {
            statusDiv.classList.add('online');
            statusDiv.innerHTML = '<i class="fa-solid fa-wifi"></i><span>Back online! Redirecting...</span>';
            setTimeout(() => {
                window.location.href = '/federation';
            }, 1000);
        });

        window.addEventListener('offline', () => {
            statusDiv.classList.remove('online');
            statusDiv.innerHTML = '<i class="fa-solid fa-wifi-slash"></i><span>No internet connection</span>';
        });

        // Check initial state
        if (navigator.onLine) {
            // Already online, redirect
            window.location.href = '/federation';
        }
    </script>
</body>
</html>

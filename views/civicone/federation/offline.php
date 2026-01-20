<?php
// Federation Offline Page - Glassmorphism 2025
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
    <meta name="theme-color" content="#00796B">
    <title><?= htmlspecialchars($pageTitle) ?> - Federation</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/federation-offline.css">
</head>
<body>
    <main class="offline-container" role="main" aria-labelledby="offline-title">
        <div class="offline-icon" aria-hidden="true">
            <i class="fa-solid fa-globe"></i>
        </div>

        <h1 id="offline-title">You're Offline</h1>
        <p class="offline-message">
            Federation features require an internet connection to communicate with partner timebanks.
        </p>

        <section class="offline-features" aria-labelledby="available-heading">
            <h3 id="available-heading">
                <i class="fa-solid fa-check-circle" aria-hidden="true"></i>
                Available Offline
            </h3>
            <ul role="list">
                <li>
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                    Previously viewed pages (cached)
                </li>
                <li>
                    <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                    Federation help & FAQ
                </li>
                <li>
                    <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                    Your settings (read-only)
                </li>
            </ul>
        </section>

        <nav class="offline-actions" aria-label="Offline actions">
            <button class="offline-btn offline-btn-primary" id="retryBtn" type="button" aria-describedby="connectionStatus">
                <span class="retry-spinner" aria-hidden="true">
                    <i class="fa-solid fa-rotate"></i>
                </span>
                <span class="retry-text">
                    <i class="fa-solid fa-wifi" aria-hidden="true"></i>
                    Try Again
                </span>
            </button>
            <a href="/dashboard" class="offline-btn offline-btn-secondary">
                <i class="fa-solid fa-home" aria-hidden="true"></i>
                Go to Dashboard
            </a>
        </nav>

        <div class="offline-status" id="connectionStatus" role="status" aria-live="polite">
            <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
            <span>No internet connection</span>
        </div>
    </main>

    <script>
        (function() {
            'use strict';

            const retryBtn = document.getElementById('retryBtn');
            const statusDiv = document.getElementById('connectionStatus');

            function updateStatus(online, message) {
                statusDiv.classList.toggle('online', online);
                statusDiv.innerHTML = '<i class="fa-solid fa-wifi' + (online ? '' : '-slash') + '" aria-hidden="true"></i><span>' + message + '</span>';
            }

            function retryConnection() {
                retryBtn.classList.add('retrying');
                retryBtn.disabled = true;
                retryBtn.setAttribute('aria-busy', 'true');

                // Try to fetch a simple endpoint
                fetch('/api/ping', { method: 'HEAD', cache: 'no-store' })
                    .then(function(response) {
                        if (response.ok) {
                            updateStatus(true, 'Connected! Redirecting...');
                            setTimeout(function() {
                                window.location.href = '/federation';
                            }, 500);
                        } else {
                            throw new Error('Not connected');
                        }
                    })
                    .catch(function() {
                        retryBtn.classList.remove('retrying');
                        retryBtn.disabled = false;
                        retryBtn.setAttribute('aria-busy', 'false');
                        // Shake animation
                        statusDiv.classList.add('shake');
                        setTimeout(function() {
                            statusDiv.classList.remove('shake');
                        }, 500);
                    });
            }

            retryBtn.addEventListener('click', retryConnection);

            // Auto-detect when connection comes back
            window.addEventListener('online', function() {
                updateStatus(true, 'Back online! Redirecting...');
                setTimeout(function() {
                    window.location.href = '/federation';
                }, 1000);
            });

            window.addEventListener('offline', function() {
                updateStatus(false, 'No internet connection');
            });

            // Check initial state
            if (navigator.onLine) {
                window.location.href = '/federation';
            }
        })();
    </script>
</body>
</html>

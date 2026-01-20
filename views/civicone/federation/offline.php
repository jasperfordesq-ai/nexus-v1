<?php
/**
 * Federation Offline Page
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
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
    <link rel="stylesheet" href="/assets/css/civicone-federation.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: var(--civic-fed-font, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif);
            background: #f9fafb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .civic-fed-offline-container {
            text-align: center;
            padding: 40px 20px;
            max-width: 480px;
        }
        .civic-fed-offline-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 24px;
            background: #00796B;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }
        .civic-fed-offline-container h1 {
            font-size: 1.75rem;
            color: #111827;
            margin: 0 0 12px 0;
            font-weight: 700;
        }
        .civic-fed-offline-message {
            color: #374151;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        .civic-fed-offline-features {
            background: #fff;
            border: 2px solid #0B0C0C;
            padding: 24px;
            margin-bottom: 32px;
            text-align: left;
        }
        .civic-fed-offline-features h3 {
            font-size: 0.875rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .civic-fed-offline-features h3 i {
            color: #00796B;
        }
        .civic-fed-offline-features ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .civic-fed-offline-features li {
            padding: 8px 0;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #374151;
        }
        .civic-fed-offline-features li:last-child {
            border-bottom: none;
        }
        .civic-fed-offline-features li i {
            color: #00796B;
            width: 20px;
        }
        .civic-fed-offline-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .civic-fed-offline-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            font-size: 1rem;
            font-weight: 700;
            text-decoration: none;
            border: 2px solid #0B0C0C;
            cursor: pointer;
            min-height: 44px;
            transition: box-shadow 0.15s ease;
        }
        .civic-fed-offline-btn:focus {
            outline: 3px solid #FFDD00;
            outline-offset: 3px;
        }
        .civic-fed-offline-btn--primary {
            background: #00796B;
            color: #fff;
        }
        .civic-fed-offline-btn--primary:hover {
            box-shadow: 4px 4px 0 #0B0C0C;
        }
        .civic-fed-offline-btn--secondary {
            background: #fff;
            color: #111827;
        }
        .civic-fed-offline-btn--secondary:hover {
            box-shadow: 4px 4px 0 #0B0C0C;
        }
        .civic-fed-offline-btn.retrying {
            opacity: 0.7;
            cursor: wait;
        }
        .civic-fed-offline-btn.retrying .retry-text { display: none; }
        .civic-fed-offline-btn .retry-spinner { display: none; }
        .civic-fed-offline-btn.retrying .retry-spinner {
            display: inline-block;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .civic-fed-offline-status {
            margin-top: 24px;
            padding: 12px 16px;
            background: #FEF2F2;
            border: 2px solid #B91C1C;
            color: #B91C1C;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .civic-fed-offline-status.online {
            background: #F0FDF4;
            border-color: #15803D;
            color: #15803D;
        }
        .civic-fed-offline-status.shake {
            animation: shake 0.5s ease;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body>
    <main class="civic-fed-offline-container" role="main" aria-labelledby="offline-title">
        <div class="civic-fed-offline-icon" aria-hidden="true">
            <i class="fa-solid fa-globe"></i>
        </div>

        <h1 id="offline-title">You're Offline</h1>
        <p class="civic-fed-offline-message">
            Federation features require an internet connection to communicate with partner timebanks.
        </p>

        <section class="civic-fed-offline-features" aria-labelledby="available-heading">
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

        <nav class="civic-fed-offline-actions" aria-label="Offline actions">
            <button class="civic-fed-offline-btn civic-fed-offline-btn--primary" id="retryBtn" type="button" aria-describedby="connectionStatus">
                <span class="retry-spinner" aria-hidden="true">
                    <i class="fa-solid fa-rotate"></i>
                </span>
                <span class="retry-text">
                    <i class="fa-solid fa-wifi" aria-hidden="true"></i>
                    Try Again
                </span>
            </button>
            <a href="/dashboard" class="civic-fed-offline-btn civic-fed-offline-btn--secondary">
                <i class="fa-solid fa-home" aria-hidden="true"></i>
                Go to Dashboard
            </a>
        </nav>

        <div class="civic-fed-offline-status" id="connectionStatus" role="status" aria-live="polite">
            <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
            <span>No internet connection</span>
        </div>
    </main>

    <script>
        (function() {
            'use strict';

            var retryBtn = document.getElementById('retryBtn');
            var statusDiv = document.getElementById('connectionStatus');

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

<?php
/**
 * Federation Offline Page
 * GOV.UK Design System (WCAG 2.1 AA) - Standalone offline page
 */
$pageTitle = "You're Offline";
$basePath = $basePath ?? '';
?>
<!DOCTYPE html>
<html lang="en" class="govuk-template">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0b0c0c">
    <title><?= htmlspecialchars($pageTitle) ?> - Federation</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/govuk-frontend.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: "GDS Transport", arial, sans-serif;
            background: #f3f2f1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .govuk-offline-container {
            text-align: center;
            padding: 40px 20px;
            max-width: 480px;
        }
        .govuk-offline-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 24px;
            background: #1d70b8;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }
        .govuk-offline-status {
            margin-top: 24px;
            padding: 15px;
            background: #f47738;
            color: #fff;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .govuk-offline-status.online {
            background: #00703c;
        }
        .govuk-button--retry.retrying {
            opacity: 0.7;
            cursor: wait;
        }
        .govuk-button--retry.retrying .retry-text { display: none; }
        .govuk-button--retry .retry-spinner { display: none; }
        .govuk-button--retry.retrying .retry-spinner {
            display: inline-block;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="govuk-template__body">
    <main class="govuk-offline-container" role="main" aria-labelledby="offline-title">
        <div class="govuk-offline-icon" aria-hidden="true">
            <i class="fa-solid fa-globe"></i>
        </div>

        <h1 class="govuk-heading-xl" id="offline-title">You're Offline</h1>
        <p class="govuk-body-l">
            Federation features require an internet connection to communicate with partner timebanks.
        </p>

        <div class="govuk-!-padding-4 govuk-!-margin-bottom-6" style="background: #fff; border-left: 5px solid #00703c; text-align: left;">
            <h2 class="govuk-heading-s">
                <i class="fa-solid fa-check-circle govuk-!-margin-right-2" style="color: #00703c;" aria-hidden="true"></i>
                Available Offline
            </h2>
            <ul class="govuk-list govuk-list--bullet">
                <li>Previously viewed pages (cached)</li>
                <li>Federation help & FAQ</li>
                <li>Your settings (read-only)</li>
            </ul>
        </div>

        <div class="govuk-button-group" style="justify-content: center;">
            <button class="govuk-button govuk-button--retry" id="retryBtn" type="button" data-module="govuk-button" aria-describedby="connectionStatus">
                <span class="retry-spinner" aria-hidden="true">
                    <i class="fa-solid fa-rotate"></i>
                </span>
                <span class="retry-text">
                    <i class="fa-solid fa-wifi govuk-!-margin-right-2" aria-hidden="true"></i>
                    Try Again
                </span>
            </button>
            <a href="/dashboard" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                <i class="fa-solid fa-home govuk-!-margin-right-2" aria-hidden="true"></i>
                Go to Dashboard
            </a>
        </div>

        <div class="govuk-offline-status" id="connectionStatus" role="status" aria-live="polite">
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
                fetch('/api/ping', { method: 'HEAD', cache: 'no-store' })
                    .then(function(response) {
                        if (response.ok) {
                            updateStatus(true, 'Connected! Redirecting...');
                            setTimeout(function() { window.location.href = '/federation'; }, 500);
                        } else { throw new Error('Not connected'); }
                    })
                    .catch(function() {
                        retryBtn.classList.remove('retrying');
                        retryBtn.disabled = false;
                    });
            }

            retryBtn.addEventListener('click', retryConnection);
            window.addEventListener('online', function() {
                updateStatus(true, 'Back online! Redirecting...');
                setTimeout(function() { window.location.href = '/federation'; }, 1000);
            });
            window.addEventListener('offline', function() { updateStatus(false, 'No internet connection'); });
            if (navigator.onLine) { window.location.href = '/federation'; }
        })();
    </script>
</body>
</html>

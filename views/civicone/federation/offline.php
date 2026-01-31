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
    <link rel="stylesheet" href="/assets/govuk-frontend-5.14.0/govuk-frontend.min.css">
    <link rel="stylesheet" href="/assets/css/design-tokens.min.css">
    <link rel="stylesheet" href="/assets/css/civicone-federation.min.css">
</head>
<body class="govuk-template__body govuk-offline-body">
    <main class="govuk-offline-container" role="main" aria-labelledby="offline-title">
        <div class="govuk-offline-icon" aria-hidden="true">
            <i class="fa-solid fa-globe"></i>
        </div>

        <h1 class="govuk-heading-xl" id="offline-title">You're Offline</h1>
        <p class="govuk-body-l">
            Federation features require an internet connection to communicate with partner timebanks.
        </p>

        <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-offline-panel">
            <h2 class="govuk-heading-s">
                <i class="fa-solid fa-check-circle govuk-!-margin-right-2 civicone-icon-green" aria-hidden="true"></i>
                Available Offline
            </h2>
            <ul class="govuk-list govuk-list--bullet">
                <li>Previously viewed pages (cached)</li>
                <li>Federation help & FAQ</li>
                <li>Your settings (read-only)</li>
            </ul>
        </div>

        <div class="govuk-button-group civicone-button-group-center">
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

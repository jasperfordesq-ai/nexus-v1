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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --accent-purple: #8b5cf6;
            --accent-purple-light: #a78bfa;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
        }

        .offline-container {
            max-width: 400px;
            width: 100%;
        }

        .offline-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 32px;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(99, 102, 241, 0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }

        .offline-icon::before {
            content: '';
            position: absolute;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 2px solid rgba(139, 92, 246, 0.2);
            animation: ring 2s ease-in-out infinite;
        }

        @keyframes ring {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0; }
        }

        .offline-icon i {
            font-size: 3rem;
            color: var(--accent-purple);
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #f1f5f9, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .offline-message {
            font-size: 1rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .offline-features {
            background: var(--bg-secondary);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: left;
        }

        .offline-features h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--accent-purple-light);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .offline-features ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .offline-features li {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .offline-features li i {
            color: var(--accent-purple);
            width: 20px;
        }

        .offline-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .offline-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            min-height: 48px;
        }

        .offline-btn-primary {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            border: none;
            cursor: pointer;
        }

        .offline-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
        }

        .offline-btn-secondary {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .offline-btn-secondary:hover {
            background: rgba(139, 92, 246, 0.1);
            border-color: rgba(139, 92, 246, 0.5);
            color: var(--text-primary);
        }

        .offline-status {
            margin-top: 32px;
            padding: 12px 20px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 0.9rem;
            color: #f87171;
        }

        .offline-status.online {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .offline-status i {
            font-size: 1rem;
        }

        /* Auto-retry animation */
        .retry-spinner {
            display: none;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .retrying .retry-spinner {
            display: inline-block;
        }

        .retrying .retry-text {
            display: none;
        }
    </style>
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

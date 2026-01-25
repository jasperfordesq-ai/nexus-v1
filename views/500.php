<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Something Went Wrong - Project Nexus</title>
    <?php
    $basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';
    ?>
    <link href="<?= $basePath ?>/assets/css/nexus-phoenix.css" rel="stylesheet">
    <link href="<?= $basePath ?>/assets/css/error-states.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            min-height: 100vh;
            margin: 0;
        }

        [data-theme="dark"] body {
            background: linear-gradient(135deg, #1c1917 0%, #292524 100%);
        }

        .error-500-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
            text-align: center;
        }

        .error-500-card {
            background: white;
            border-radius: 16px;
            padding: 3rem 2rem;
            max-width: 500px;
            box-shadow:
                0 20px 50px -10px rgba(239, 68, 68, 0.15),
                0 10px 25px -5px rgba(0, 0, 0, 0.1);
            animation: error-slide-up 0.6s ease-out;
        }

        [data-theme="dark"] .error-500-card {
            background: #1f2937;
            box-shadow:
                0 20px 50px -10px rgba(0, 0, 0, 0.4),
                0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }

        .error-500-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.2));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: error-float 3s ease-in-out infinite;
        }

        .error-500-icon i,
        .error-500-icon .dashicons {
            font-size: 48px;
            color: #ef4444;
        }

        .error-500-code {
            font-size: 5rem;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, #ef4444, #f97316);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            animation: error-glitch 0.3s ease-in-out infinite;
        }

        .error-500-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.75rem;
        }

        [data-theme="dark"] .error-500-title {
            color: #f3f4f6;
        }

        .error-500-message {
            font-size: 1rem;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        [data-theme="dark"] .error-500-message {
            color: #9ca3af;
        }

        .error-500-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
        }

        .error-500-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.875rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .error-500-btn--primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }

        .error-500-btn--primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }

        .error-500-btn--secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .error-500-btn--secondary:hover {
            background: #e5e7eb;
        }

        [data-theme="dark"] .error-500-btn--secondary {
            background: #374151;
            color: #e5e7eb;
        }

        [data-theme="dark"] .error-500-btn--secondary:hover {
            background: #4b5563;
        }

        .error-500-details {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }

        [data-theme="dark"] .error-500-details {
            border-top-color: #374151;
        }

        .error-500-details summary {
            font-size: 0.875rem;
            color: #6b7280;
            cursor: pointer;
            user-select: none;
        }

        .error-500-details pre {
            margin-top: 1rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
            font-size: 0.75rem;
            color: #6b7280;
            text-align: left;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }

        [data-theme="dark"] .error-500-details pre {
            background: #1f2937;
            color: #9ca3af;
        }

        /* Animations inherited from error-states.css */
        @keyframes error-slide-up {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes error-float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes error-glitch {
            0% { transform: translate(0); }
            20% { transform: translate(-2px, 2px); }
            40% { transform: translate(-2px, -2px); }
            60% { transform: translate(2px, 2px); }
            80% { transform: translate(2px, -2px); }
            100% { transform: translate(0); }
        }

        @media (max-width: 640px) {
            .error-500-code {
                font-size: 3.5rem;
            }

            .error-500-card {
                padding: 2rem 1.5rem;
            }

            .error-500-actions {
                flex-direction: column;
            }

            .error-500-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .error-500-card,
            .error-500-icon,
            .error-500-code {
                animation: none;
            }
        }
    </style>
</head>

<body>
    <div class="error-500-container">
        <div class="error-500-card">
            <div class="error-500-icon">
                <i class="fa-solid fa-bug" aria-hidden="true"></i>
            </div>

            <div class="error-500-code">500</div>

            <h1 class="error-500-title">Something Went Wrong</h1>

            <p class="error-500-message">
                We're experiencing technical difficulties. Our team has been notified and is working on it.
                Please try again in a few moments.
            </p>

            <div class="error-500-actions">
                <button onclick="location.reload()" class="error-500-btn error-500-btn--primary">
                    <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                    Try Again
                </button>
                <a href="<?= $basePath ?>/" class="error-500-btn error-500-btn--secondary">
                    <i class="fa-solid fa-home" aria-hidden="true"></i>
                    Go Home
                </a>
            </div>

            <?php if (isset($errorMessage) && defined('DEBUG_MODE') && DEBUG_MODE): ?>
            <details class="error-500-details">
                <summary>Technical Details</summary>
                <pre><?= htmlspecialchars($errorMessage ?? 'No additional information available.') ?></pre>
            </details>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-retry after 30 seconds
        setTimeout(function() {
            const btn = document.querySelector('.error-500-btn--primary');
            if (btn) {
                btn.innerHTML = '<i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Auto-retrying...';
                setTimeout(function() {
                    location.reload();
                }, 2000);
            }
        }, 30000);
    </script>
</body>

</html>

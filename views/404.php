<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - Project Nexus</title>
    <?php
    $basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';
    ?>
    <link href="<?= $basePath ?>/assets/css/nexus-phoenix.css" rel="stylesheet">
    <link href="<?= $basePath ?>/assets/css/error-states.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            min-height: 100vh;
            margin: 0;
        }

        [data-theme="dark"] body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }

        .error-404-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
            text-align: center;
        }

        .error-404-card {
            background: white;
            border-radius: 16px;
            padding: 3rem 2rem;
            max-width: 500px;
            box-shadow:
                0 20px 50px -10px rgba(59, 130, 246, 0.15),
                0 10px 25px -5px rgba(0, 0, 0, 0.1);
            animation: error-slide-up 0.6s ease-out;
        }

        [data-theme="dark"] .error-404-card {
            background: #1f2937;
            box-shadow:
                0 20px 50px -10px rgba(0, 0, 0, 0.4),
                0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }

        .error-404-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.2));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: error-float 3s ease-in-out infinite;
        }

        .error-404-icon i,
        .error-404-icon .dashicons {
            font-size: 48px;
            color: #6366f1;
        }

        .error-404-code {
            font-size: 5rem;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            position: relative;
        }

        .error-404-code::before {
            content: '404';
            position: absolute;
            top: 2px;
            left: 2px;
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            opacity: 0.3;
            z-index: -1;
        }

        .error-404-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.75rem;
        }

        [data-theme="dark"] .error-404-title {
            color: #f3f4f6;
        }

        .error-404-message {
            font-size: 1rem;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        [data-theme="dark"] .error-404-message {
            color: #9ca3af;
        }

        .error-404-suggestion {
            background: linear-gradient(135deg, #dbeafe, #e0e7ff);
            border: 1px solid #93c5fd;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }

        [data-theme="dark"] .error-404-suggestion {
            background: linear-gradient(135deg, #1e3a5f, #1e40af);
            border-color: #3b82f6;
        }

        .error-404-suggestion-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 0.5rem;
        }

        [data-theme="dark"] .error-404-suggestion-label {
            color: #93c5fd;
        }

        .error-404-suggestion a {
            color: #2563eb;
            font-weight: 500;
            text-decoration: none;
        }

        .error-404-suggestion a:hover {
            text-decoration: underline;
        }

        [data-theme="dark"] .error-404-suggestion a {
            color: #60a5fa;
        }

        .error-404-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
        }

        .error-404-btn {
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

        .error-404-btn--primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }

        .error-404-btn--primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }

        .error-404-btn--secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .error-404-btn--secondary:hover {
            background: #e5e7eb;
        }

        [data-theme="dark"] .error-404-btn--secondary {
            background: #374151;
            color: #e5e7eb;
        }

        [data-theme="dark"] .error-404-btn--secondary:hover {
            background: #4b5563;
        }

        /* Animations */
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

        @media (max-width: 640px) {
            .error-404-code {
                font-size: 3.5rem;
            }

            .error-404-card {
                padding: 2rem 1.5rem;
            }

            .error-404-actions {
                flex-direction: column;
            }

            .error-404-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .error-404-card,
            .error-404-icon {
                animation: none;
            }
        }
    </style>
</head>

<body>
    <div class="error-404-container">
        <div class="error-404-card">
            <div class="error-404-icon">
                <i class="fa-solid fa-ghost" aria-hidden="true"></i>
            </div>

            <div class="error-404-code">404</div>

            <h1 class="error-404-title">Page Not Found</h1>

            <p class="error-404-message">
                We couldn't find the page you're looking for. It might have been moved, deleted, or never existed.
            </p>

            <?php if (isset($suggestedUrl) && $suggestedUrl): ?>
            <div class="error-404-suggestion">
                <div class="error-404-suggestion-label">Did you mean?</div>
                <a href="<?= htmlspecialchars($suggestedUrl) ?>">
                    <?= htmlspecialchars($suggestedUrl) ?>
                </a>
            </div>
            <?php endif; ?>

            <div class="error-404-actions">
                <a href="<?= $basePath ?>/" class="error-404-btn error-404-btn--primary">
                    <i class="fa-solid fa-home" aria-hidden="true"></i>
                    Go Home
                </a>
                <button onclick="history.back()" class="error-404-btn error-404-btn--secondary">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    Go Back
                </button>
            </div>
        </div>
    </div>
</body>

</html>

        <!-- CivicOne Hero - MadeOpen Community Style -->
    <?php
    // Resolve variables (Contract)
    $heroTitle = $hTitle ?? $pageTitle ?? 'Project NEXUS';
    $heroSub = $hSubtitle ?? $pageSubtitle ?? '';
    $heroType = $hType ?? 'Platform';
    ?>
    <style>
        /* ===========================================
               HERO BANNER - Clean MadeOpen Style
               =========================================== */
        .civicone-hero-banner {
            background: var(--civic-brand);
            color: white;
            padding: 40px 0;
            margin-bottom: 40px;
            position: relative;
        }

        /* Torfaen skin uses purple gradient */
        body.skin-torfaen .civicone-hero-banner {
            background: linear-gradient(135deg, #96206d 0%, #7a1a59 100%);
        }

        .civicone-hero-banner .civic-container {
            position: relative;
            z-index: 1;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            font-weight: 600;
            font-size: 12px;
            padding: 4px 10px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 4px;
        }

        .hero-title {
            color: white !important;
            margin-bottom: 8px;
            font-size: clamp(1.75rem, 4vw, 2.5rem);
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .hero-subtitle {
            font-size: clamp(1rem, 2vw, 1.125rem);
            opacity: 0.9;
            max-width: 600px;
            margin: 0;
            line-height: 1.5;
        }

        /* Dark mode hero */
        body.dark-mode .civicone-hero-banner {
            background: #1E3A8A;
        }

        @media (max-width: 768px) {
            .civicone-hero-banner {
                padding: 28px 0;
                margin-bottom: 28px;
            }
        }
    </style>
    <div class="civicone-hero-banner">
        <div class="civic-container">
            <span class="hero-badge">
                <?= htmlspecialchars($heroType) ?>
            </span>
            <h1 class="hero-title">
                <?= htmlspecialchars($heroTitle) ?>
            </h1>
            <?php if ($heroSub): ?>
                <p class="hero-subtitle">
                    <?= htmlspecialchars($heroSub) ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

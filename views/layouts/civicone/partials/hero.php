        <!-- CivicOne Hero - MadeOpen Community Style -->
    <?php
    // Resolve variables (Contract)
    $heroTitle = $hTitle ?? $pageTitle ?? 'Project NEXUS';
    $heroSub = $hSubtitle ?? $pageSubtitle ?? '';
    $heroType = $hType ?? 'Platform';
    ?>
    <!-- Hero banner styles now loaded from /assets/css/civicone-header.min.css -->
    <!-- Removed inline styles per CLAUDE.md guidelines (2026-01-20) -->
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

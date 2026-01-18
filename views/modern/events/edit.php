<?php
// Edit Event View - High-End Adaptive Holographic Glassmorphism Edition
// ISOLATED LAYOUT: Uses #unique-glass-page-wrapper and html[data-theme] selectors.

require __DIR__ . '/../../layouts/header.php';

// PREPARATION LOGIC
// 1. Extract Date/Time components for HTML5 inputs
$startParts = explode(' ', $event['start_time']);
$startDate = $startParts[0];
$startTime = substr($startParts[1] ?? '00:00:00', 0, 5); // HH:MM

$endDate = '';
$endTime = '';
if (!empty($event['end_time'])) {
    $endParts = explode(' ', $event['end_time']);
    $endDate = $endParts[0];
    $endTime = substr($endParts[1] ?? '00:00:00', 0, 5);
}

// 2. Decode SDGs
$selectedSDGs = [];
if (!empty($event['sdg_goals'])) {
    $selectedSDGs = json_decode($event['sdg_goals'], true) ?? [];
}
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<style>
    /* ============================================
       GOLD STANDARD - Native App Features
       ============================================ */

    /* Offline Banner */
    .offline-banner {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 10001;
        padding: 12px 20px;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        font-size: 0.9rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transform: translateY(-100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .offline-banner.visible {
        transform: translateY(0);
    }

    /* Content Reveal Animation */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    #unique-glass-page-wrapper .glass-box {
        animation: fadeInUp 0.4s ease-out;
    }

    /* Button Press States */
    .glass-btn-primary:active,
    .glass-btn-secondary:active,
    button:active {
        transform: scale(0.96) !important;
        transition: transform 0.1s ease !important;
    }

    /* Touch Targets - WCAG 2.1 AA (44px minimum) */
    .glass-btn-primary,
    .glass-btn-secondary,
    button,
    .glass-input,
    select.glass-input {
        min-height: 44px;
    }

    .glass-input,
    textarea.glass-input {
        font-size: 16px !important; /* Prevent iOS zoom */
    }

    /* Focus Visible */
    .glass-btn-primary:focus-visible,
    .glass-btn-secondary:focus-visible,
    button:focus-visible,
    a:focus-visible,
    .glass-input:focus-visible {
        outline: 3px solid rgba(249, 115, 22, 0.5);
        outline-offset: 2px;
    }

    /* Smooth Scroll */
    html {
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }

    /* Mobile Responsive Enhancements */
    @media (max-width: 768px) {
        .glass-btn-primary,
        .glass-btn-secondary,
        button,
        .glass-input {
            min-height: 48px;
        }
    }
</style>

<style>
    /* SCOPED STYLES for #unique-glass-page-wrapper ONLY */
    #unique-glass-page-wrapper {
        /* 
       --- DEFAULT / LIGHT MODE VARIABLES (Fallback) --- 
    */
        --glass-bg: rgba(255, 255, 255, 0.55);
        --glass-border: rgba(255, 255, 255, 0.4);
        --text-color: #1e293b;
        --text-muted: #475569;
        --accent-color: #f97316;
        /* Orange for Events */
        --heading-gradient: linear-gradient(135deg, #c2410c 0%, #f97316 100%);

        /* Subtle iridescent shadow for light mode */
        --box-shadow:
            0 8px 32px 0 rgba(31, 38, 135, 0.1),
            inset 0 0 0 1px rgba(255, 255, 255, 0.2);

        --holographic-glow: 0 0 20px rgba(249, 115, 22, 0.05), 0 0 40px rgba(255, 200, 100, 0.05);

        /* Form Fields & Pills */
        --pill-bg: rgba(255, 255, 255, 0.25);
        --input-bg: rgba(255, 255, 255, 0.35);
        --input-focus-bg: rgba(255, 255, 255, 0.5);
        --pill-border: 2px solid rgba(255, 255, 255, 0.4);
        --pill-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);

        /* Layout & Alignment */
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        min-height: 80vh;
        padding: 50px 20px;
        box-sizing: border-box;

        /* Typography Defaults */
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: var(--text-color);
        line-height: 1.6;

        /* Background: Adaptive Mesh Gradient (Orange/Amber) */
        background: radial-gradient(circle at 50% 0%, #fff7ed 0%, #ffedd5 100%);
        background-attachment: fixed;
    }

    /* 
   --- THEME SYNC SELECTORS --- 
   Using html[data-theme] as requested 
*/

    /* LIGHT MODE EXPLICIT */
    html[data-theme="light"] #unique-glass-page-wrapper {
        --glass-bg: rgba(255, 255, 255, 0.55);
        --glass-border: rgba(255, 255, 255, 0.4);
        --text-color: #1e293b;
        --text-muted: #475569;
        --heading-gradient: linear-gradient(135deg, #c2410c 0%, #f97316 100%);
        --box-shadow:
            0 8px 32px 0 rgba(31, 38, 135, 0.1),
            inset 0 0 0 1px rgba(255, 255, 255, 0.2);
        --holographic-glow: 0 0 20px rgba(249, 115, 22, 0.05), 0 0 40px rgba(255, 200, 100, 0.05);
        --pill-bg: rgba(255, 255, 255, 0.25);
        --input-bg: rgba(255, 255, 255, 0.35);

        background: radial-gradient(circle at 50% 0%, #fff7ed 0%, #ffedd5 100%);
    }

    /* DARK MODE EXPLICIT */
    html[data-theme="dark"] #unique-glass-page-wrapper {
        --glass-bg: rgba(24, 24, 27, 0.45);
        --glass-border: rgba(255, 255, 255, 0.15);
        --text-color: #ffedd5;
        --text-muted: rgba(255, 255, 255, 0.7);
        --accent-color: #fdba74;
        --heading-gradient: linear-gradient(135deg, #ffffff 0%, #fdba74 100%);

        /* Vivid Dark Mode Shadows */
        --box-shadow:
            0 8px 32px 0 rgba(0, 0, 0, 0.4),
            inset 0 0 0 1px rgba(255, 255, 255, 0.05);

        --holographic-glow:
            0 0 30px rgba(249, 115, 22, 0.15),
            0 0 60px rgba(251, 146, 60, 0.15);

        --pill-bg: rgba(24, 24, 27, 0.65);
        --pill-border: 2px solid rgba(255, 255, 255, 0.1);

        --input-bg: rgba(24, 24, 27, 0.6);
        --input-focus-bg: rgba(24, 24, 27, 0.8);

        background: radial-gradient(circle at 50% 0%, rgb(40, 15, 5) 0%, rgb(20, 10, 5) 90%);
    }


    /* The Glass Container */
    #unique-glass-page-wrapper .glass-box {
        position: relative;
        width: 95%;
        max-width: 800px;
        margin-left: auto;
        margin-right: auto;

        /* High-End Glass Effect */
        background: var(--glass-bg);
        backdrop-filter: blur(25px) saturate(200%);
        -webkit-backdrop-filter: blur(25px) saturate(200%);

        /* Holographic Borders & Shadows */
        border-radius: 28px;
        border: 1px solid var(--glass-border);
        box-shadow: var(--box-shadow), var(--holographic-glow);

        padding: 50px;
        transition: background 0.3s ease, box-shadow 0.3s ease;
    }

    /* Iridescent Top Edge */
    #unique-glass-page-wrapper .glass-box::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(251, 146, 60, 0.6), rgba(253, 186, 116, 0.6), transparent);
        z-index: 10;
    }

    /* Header Section */
    #unique-glass-page-wrapper .page-header {
        text-align: center;
        margin-bottom: 40px;
    }

    #unique-glass-page-wrapper h1 {
        font-size: 2.5rem;
        font-weight: 800;
        background: var(--heading-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin: 0 0 10px 0;
        letter-spacing: -0.02em;
    }

    #unique-glass-page-wrapper .page-subtitle {
        font-size: 1.1rem;
        color: var(--text-muted);
    }

    /* Form Styles */
    #unique-glass-page-wrapper .form-group {
        margin-bottom: 25px;
    }

    #unique-glass-page-wrapper label {
        display: block;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--text-color);
        font-size: 0.95rem;
        margin-left: 5px;
    }

    #unique-glass-page-wrapper .glass-input {
        width: 100%;
        box-sizing: border-box;
        padding: 16px 20px;
        border-radius: 16px;
        border: 1px solid var(--glass-border);
        background: var(--input-bg);
        color: var(--text-color);
        font-size: 1rem;
        font-family: inherit;
        transition: all 0.2s ease;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    #unique-glass-page-wrapper .glass-input:focus {
        outline: none;
        background: var(--input-focus-bg);
        border-color: var(--accent-color);
        box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15);
        /* Orange ring */
    }

    /* Date Input Fix for Icons */
    #unique-glass-page-wrapper .glass-input[type="date"],
    #unique-glass-page-wrapper .glass-input[type="time"] {
        color-scheme: light;
    }

    html[data-theme="dark"] #unique-glass-page-wrapper .glass-input[type="date"],
    html[data-theme="dark"] #unique-glass-page-wrapper .glass-input[type="time"] {
        color-scheme: dark;
    }

    /* Select Styling */
    #unique-glass-page-wrapper select.glass-input {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23f97316' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 20px center;
        background-size: 20px;
    }

    /* SDG Glass Accordion */
    #unique-glass-page-wrapper details {
        background: var(--pill-bg);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 30px;
        transition: all 0.3s ease;
    }

    #unique-glass-page-wrapper summary {
        padding: 20px;
        cursor: pointer;
        font-weight: 700;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: space-between;
        user-select: none;
        list-style: none;
        /* Hide default triangle */
    }

    #unique-glass-page-wrapper summary::-webkit-details-marker {
        display: none;
    }

    #unique-glass-page-wrapper .sdg-content {
        padding: 20px;
        border-top: 1px solid var(--glass-border);
        background: var(--input-bg);
    }

    #unique-glass-page-wrapper .sdg-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 15px;
    }

    /* Interactive SDG Tiles */
    #unique-glass-page-wrapper .glass-sdg-card {
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        padding: 15px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        background: rgba(255, 255, 255, 0.05);
        position: relative;
        overflow: hidden;
    }

    #unique-glass-page-wrapper .glass-sdg-card:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: translateY(-2px);
    }

    /* Active State */
    #unique-glass-page-wrapper .glass-sdg-card.selected {
        background: rgba(255, 255, 255, 0.2);
        border-color: currentColor;
        /* Inherit color from JS style */
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
    }

    #unique-glass-page-wrapper .glass-sdg-card.selected::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: currentColor;
        opacity: 0.1;
        pointer-events: none;
    }

    /* Buttons */
    #unique-glass-page-wrapper .actions-group {
        display: flex;
        gap: 15px;
        margin-top: 20px;
    }

    #unique-glass-page-wrapper .glass-btn-primary {
        padding: 18px 28px;
        border-radius: 50px;
        border: none;
        font-weight: 700;
        cursor: pointer;
        font-size: 1.1rem;
        flex: 2;
        transition: all 0.2s ease;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(249, 115, 22, 0.3);
        background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        color: white;
    }

    #unique-glass-page-wrapper .glass-btn-secondary {
        padding: 18px 28px;
        border-radius: 50px;
        font-weight: 600;
        cursor: pointer;
        font-size: 1rem;
        flex: 1;
        transition: all 0.2s ease;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--pill-bg);
        border: var(--pill-border);
        color: var(--text-muted);
    }

    #unique-glass-page-wrapper .glass-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(249, 115, 22, 0.4);
    }

    #unique-glass-page-wrapper .glass-btn-secondary:hover {
        background: rgba(255, 255, 255, 0.2);
        color: var(--text-color);
    }

    /* Federation Section */
    #unique-glass-page-wrapper .federation-section {
        margin-bottom: 30px;
        padding: 24px;
        border-radius: 16px;
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.08) 0%, rgba(99, 102, 241, 0.04) 100%);
        border: 1px solid rgba(139, 92, 246, 0.2);
    }

    html[data-theme="dark"] #unique-glass-page-wrapper .federation-section {
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.15) 0%, rgba(99, 102, 241, 0.08) 100%);
        border-color: rgba(139, 92, 246, 0.25);
    }

    #unique-glass-page-wrapper .federation-options {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    #unique-glass-page-wrapper .radio-card {
        display: flex;
        align-items: flex-start;
        padding: 16px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.5);
        border: 2px solid transparent;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    html[data-theme="dark"] #unique-glass-page-wrapper .radio-card {
        background: rgba(30, 41, 59, 0.5);
    }

    #unique-glass-page-wrapper .radio-card:hover {
        background: rgba(255, 255, 255, 0.8);
        border-color: rgba(139, 92, 246, 0.3);
    }

    html[data-theme="dark"] #unique-glass-page-wrapper .radio-card:hover {
        background: rgba(30, 41, 59, 0.8);
    }

    #unique-glass-page-wrapper .radio-card:has(input:checked) {
        background: rgba(139, 92, 246, 0.12);
        border-color: #8b5cf6;
    }

    html[data-theme="dark"] #unique-glass-page-wrapper .radio-card:has(input:checked) {
        background: rgba(139, 92, 246, 0.2);
    }

    #unique-glass-page-wrapper .radio-card input[type="radio"] {
        margin-right: 12px;
        margin-top: 4px;
        accent-color: #8b5cf6;
        width: 18px;
        height: 18px;
    }

    #unique-glass-page-wrapper .radio-content {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
    }

    #unique-glass-page-wrapper .radio-label {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--text-color);
    }

    #unique-glass-page-wrapper .radio-desc {
        font-size: 0.85rem;
        color: var(--text-muted);
        line-height: 1.4;
    }

    #unique-glass-page-wrapper .federation-optin-notice {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        border-radius: 12px;
        background: rgba(245, 158, 11, 0.1);
        border: 1px solid rgba(245, 158, 11, 0.2);
    }

    #unique-glass-page-wrapper .federation-optin-notice i {
        color: #f59e0b;
        font-size: 1.25rem;
        margin-top: 2px;
    }

    #unique-glass-page-wrapper .federation-optin-notice strong {
        display: block;
        color: var(--text-color);
        margin-bottom: 4px;
    }

    #unique-glass-page-wrapper .federation-optin-notice p {
        font-size: 0.9rem;
        color: var(--text-muted);
        margin: 0;
    }

    #unique-glass-page-wrapper .federation-optin-notice a {
        color: #8b5cf6;
        text-decoration: underline;
    }

    /* MAPBOX GEOCODER OVERRIDES for Holographic Theme */
    #unique-glass-page-wrapper .mapbox-geocoder-container {
        width: 100%;
    }

    #unique-glass-page-wrapper .mapboxgl-ctrl-geocoder {
        background: var(--input-bg);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        width: 100% !important;
        max-width: none !important;
        min-width: 0 !important;
        font-family: inherit;
        color: var(--text-color);
    }

    /* The actual input inside the geocoder */
    #unique-glass-page-wrapper .mapboxgl-ctrl-geocoder--input {
        color: var(--text-color);
        height: auto;
        padding: 16px 45px;
        /* Space for icon */
        font-size: 1rem;
        font-family: inherit;
    }

    #unique-glass-page-wrapper .mapboxgl-ctrl-geocoder--input:focus {
        outline: none;
        box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15);
        /* Orange ring match */
    }

    /* Icons */
    #unique-glass-page-wrapper .mapboxgl-ctrl-geocoder--icon {
        fill: var(--accent-color);
        top: 50%;
        transform: translateY(-50%);
    }

    /* Suggestions Dropdown */
    #unique-glass-page-wrapper .mapboxgl-ctrl-geocoder .suggestions {
        background-color: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        margin-top: 5px;
        overflow: hidden;
    }

    #unique-glass-page-wrapper .mapboxgl-ctrl-geocoder .suggestions>li>a {
        color: var(--text-color);
        padding: 12px 20px;
        transition: background 0.2s;
    }

    #unique-glass-page-wrapper .mapboxgl-ctrl-geocoder .suggestions>.active>a,
    #unique-glass-page-wrapper .mapboxgl-ctrl-geocoder .suggestions>li>a:hover {
        background-color: var(--accent-color);
        color: white;
        cursor: pointer;
    }


    /* Responsive */
    @media (max-width: 768px) {
        #unique-glass-page-wrapper {
            padding: 20px 10px;
        }

        #unique-glass-page-wrapper .glass-box {
            width: 100%;
            padding: 40px 25px;
            border-radius: 20px;
        }

        #unique-glass-page-wrapper .dates-grid {
            grid-template-columns: 1fr;
        }

        #unique-glass-page-wrapper .actions-group {
            flex-direction: column;
        }

        #unique-glass-page-wrapper .glass-btn-primary,
        #unique-glass-page-wrapper .glass-btn-secondary {
            flex: auto;
            width: 100%;
        }
    }
</style>

<div id="unique-glass-page-wrapper">
    <div class="glass-box">

        <div class="page-header">
            <h1>Edit Event</h1>
            <div class="page-subtitle">Make changes to your gathering.</div>
        </div>

        <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $event['id'] ?>/update" method="POST">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Title -->
            <div class="form-group">
                <label>Event Title</label>
                <input type="text" name="title" id="title" value="<?= htmlspecialchars($event['title']) ?>" class="glass-input" required>
            </div>

            <!-- Location -->
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" value="<?= htmlspecialchars($event['location']) ?>" class="glass-input mapbox-location-input-v2" required>
                <input type="hidden" name="latitude" value="<?= $event['latitude'] ?? '' ?>">
                <input type="hidden" name="longitude" value="<?= $event['longitude'] ?? '' ?>">
            </div>

            <!-- Category -->
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="glass-input">
                    <option value="">-- Select Category --</option>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($event['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Host as Group -->
            <?php if (!empty($myGroups)): ?>
                <div class="form-group">
                    <label>Host as Hub (Optional)</label>
                    <select name="group_id" class="glass-input">
                        <option value="">-- Personal Event --</option>
                        <?php foreach ($myGroups as $grp): ?>
                            <option value="<?= $grp['id'] ?>" <?= ($event['group_id'] == $grp['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($grp['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Dates & Times -->
            <div class="form-group dates-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?= $startDate ?>" class="glass-input" required>
                </div>
                <div>
                    <label>Start Time</label>
                    <input type="time" name="start_time" value="<?= $startTime ?>" class="glass-input" required>
                </div>
            </div>

            <div class="form-group dates-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label>End Date (Optional)</label>
                    <input type="date" name="end_date" value="<?= $endDate ?>" class="glass-input">
                </div>
                <div>
                    <label>End Time (Optional)</label>
                    <input type="time" name="end_time" value="<?= $endTime ?>" class="glass-input">
                </div>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label>Description</label>
                <?php
                $aiGenerateType = 'event';
                $aiTitleField = 'title';
                $aiDescriptionField = 'description';
                $aiTypeField = null;
                include __DIR__ . '/../../partials/ai-generate-button.php';
                ?>
                <textarea name="description" id="description" class="glass-input" rows="5" required><?= htmlspecialchars($event['description']) ?></textarea>
            </div>

            <!-- SDG Glass Accordion -->
            <details <?= !empty($selectedSDGs) ? 'open' : '' ?>>
                <summary>
                    <span style="display: flex; align-items: center; gap: 10px;">
                        Social Impact <span style="font-weight: 400; font-size: 0.85rem; opacity: 0.7;">(Optional)</span>
                    </span>
                    <span style="font-size: 1.2rem; opacity: 0.5;">▼</span>
                </summary>

                <div class="sdg-content">
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 20px; margin-top: 0;">Tag which goals this event supports.</p>

                    <?php
                    require_once __DIR__ . '/../../../src/Helpers/SDG.php';
                    $sdgs = \Nexus\Helpers\SDG::all();
                    ?>

                    <div class="sdg-grid">
                        <?php foreach ($sdgs as $id => $goal): ?>
                            <?php $isChecked = in_array($id, $selectedSDGs); ?>
                            <label class="glass-sdg-card <?= $isChecked ? 'selected' : '' ?>" style="color: <?= $goal['color'] ?>;">
                                <input type="checkbox" name="sdg_goals[]" value="<?= $id ?>" <?= $isChecked ? 'checked' : '' ?> style="display: none;" onchange="toggleSDGClass(this)">
                                <span style="font-size: 1.2rem;"><?= $goal['icon'] ?></span>
                                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-color); line-height: 1.2;"><?= $goal['label'] ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>

            <script>
                function toggleSDGClass(cb) {
                    const card = cb.parentElement;
                    if (cb.checked) {
                        card.classList.add('selected');
                    } else {
                        card.classList.remove('selected');
                    }
                }
            </script>

            <!-- Partner Timebanks (Federation) -->
            <?php if (!empty($federationEnabled)): ?>
            <div class="federation-section">
                <label style="margin-left: 0;">
                    <i class="fa-solid fa-globe" style="margin-right: 8px; color: #8b5cf6;"></i>
                    Share with Partner Timebanks
                    <span style="font-weight: 400; opacity: 0.6; font-size: 0.85rem;">(Optional)</span>
                </label>

                <?php if (!empty($userFederationOptedIn)): ?>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 12px;">
                    Make this event visible to members of our partner timebanks.
                </p>
                <div class="federation-options">
                    <label class="radio-card">
                        <input type="radio" name="federated_visibility" value="none" <?= ($event['federated_visibility'] ?? 'none') === 'none' ? 'checked' : '' ?>>
                        <span class="radio-content">
                            <span class="radio-label">Local Only</span>
                            <span class="radio-desc">Only visible to members of this timebank</span>
                        </span>
                    </label>
                    <label class="radio-card">
                        <input type="radio" name="federated_visibility" value="listed" <?= ($event['federated_visibility'] ?? '') === 'listed' ? 'checked' : '' ?>>
                        <span class="radio-content">
                            <span class="radio-label">Visible</span>
                            <span class="radio-desc">Partner timebank members can see this event</span>
                        </span>
                    </label>
                    <label class="radio-card">
                        <input type="radio" name="federated_visibility" value="joinable" <?= ($event['federated_visibility'] ?? '') === 'joinable' ? 'checked' : '' ?>>
                        <span class="radio-content">
                            <span class="radio-label">Joinable</span>
                            <span class="radio-desc">Partner members can RSVP to this event</span>
                        </span>
                    </label>
                </div>
                <?php else: ?>
                <div class="federation-optin-notice">
                    <i class="fa-solid fa-info-circle"></i>
                    <div>
                        <strong>Enable federation to share events</strong>
                        <p>To share your events with partner timebanks, you need to opt into federation in your <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/settings?section=federation">account settings</a>.</p>
                    </div>
                </div>
                <input type="hidden" name="federated_visibility" value="none">
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- SEO Settings Accordion -->
            <?php
            $seo = $seo ?? \Nexus\Models\SeoMetadata::get('event', $event['id']);
            $entityTitle = $event['title'] ?? '';
            $entityUrl = \Nexus\Core\TenantContext::getBasePath() . '/events/' . $event['id'];
            require __DIR__ . '/../../partials/seo-accordion.php';
            ?>

            <div class="actions-group">
                <button type="submit" class="glass-btn-primary">Save Changes</button>
                <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/events/<?= $event['id'] ?>" class="glass-btn-secondary">Cancel</a>
            </div>

        </form>

    </div>
</div>

<script>
// ============================================
// GOLD STANDARD - Native App Features
// ============================================

// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Form Submission Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to submit.');
            return;
        }
    });
});

// Button Press States
document.querySelectorAll('.glass-btn-primary, .glass-btn-secondary, button').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#f97316';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#f97316');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
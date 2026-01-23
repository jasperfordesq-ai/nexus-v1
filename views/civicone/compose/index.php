<?php
/**
 * Multi-Draw Creation Form - Professional Meta-Style Interface
 *
 * Features:
 * - Desktop: Glassmorphism modal with holographic effects
 * - Mobile: Full-screen fixed overlay (100vw x 100vh)
 * - Horizontal scrollable pill navigation (YouTube/Instagram style)
 * - Context-aware default tab based on referrer URL
 * - Volunteering tab with conditional user role logic
 * - Group Post tab with smart search dropdown
 * - Safe-area-inset support for notched devices
 *
 * Tabs: Post, Listings (Offer/Request), Events, Polls, Goals, Volunteering, Group Post
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
$tenantId = \Nexus\Core\TenantContext::getId();
$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? 'User';
$userAvatar = $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp';

// Get flash messages
$error = $_SESSION['compose_error'] ?? null;
$success = $_SESSION['compose_success'] ?? null;
unset($_SESSION['compose_error'], $_SESSION['compose_success']);

// Feature flags
$hasEvents = $hasEvents ?? \Nexus\Core\TenantContext::hasFeature('events');
$hasGoals = $hasGoals ?? \Nexus\Core\TenantContext::hasFeature('goals');
$hasPolls = $hasPolls ?? \Nexus\Core\TenantContext::hasFeature('polls');
$hasVolunteering = \Nexus\Core\TenantContext::hasFeature('volunteering');
$hasGroups = true; // Groups are always available

// Get user's groups for group post
$userGroups = [];
if ($userId && class_exists('\Nexus\Models\Group')) {
    try {
        $userGroups = \Nexus\Models\Group::getUserGroups($userId);
    } catch (\Exception $e) {
        $userGroups = [];
    }
}

// Pre-selected group from URL parameter (e.g., /compose?group=123)
$preselectedGroupId = isset($_GET['group']) ? (int)$_GET['group'] : null;
$preselectedGroup = null;
if ($preselectedGroupId && !empty($userGroups)) {
    // Find the group in user's groups
    foreach ($userGroups as $g) {
        if ((int)$g['id'] === $preselectedGroupId) {
            $preselectedGroup = $g;
            break;
        }
    }
}

// Get listing categories
$listingCategories = [];
if (class_exists('\Nexus\Models\Category')) {
    try {
        $listingCategories = \Nexus\Models\Category::getByType('listing');
    } catch (\Exception $e) {
        $listingCategories = [];
    }
}

// Get listing attributes (Service Details checkboxes)
$listingAttributes = [];
if (class_exists('\Nexus\Models\Attribute')) {
    try {
        $listingAttributes = \Nexus\Models\Attribute::all();
    } catch (\Exception $e) {
        $listingAttributes = [];
    }
}

// Get event categories
$eventCategories = [];
if ($hasEvents && class_exists('\Nexus\Models\Category')) {
    try {
        $eventCategories = \Nexus\Models\Category::getByType('event');
    } catch (\Exception $e) {
        $eventCategories = [];
    }
}

// Volunteering: Check user's organization status
$myOrgs = [];
$isVolunteerHost = false;
$hasApprovedOrg = false;
$userProfileType = $_SESSION['user_profile_type'] ?? 'individual';
$isOrganizationProfile = ($userProfileType === 'organisation');

if ($hasVolunteering && $userId && class_exists('\Nexus\Models\VolOrganization')) {
    try {
        $myOrgs = \Nexus\Models\VolOrganization::findByOwner($userId);
        $isVolunteerHost = !empty($myOrgs);
        // Check if any org is approved
        foreach ($myOrgs as $org) {
            if (($org['status'] ?? '') === 'approved') {
                $hasApprovedOrg = true;
                break;
            }
        }
    } catch (\Exception $e) {
        $myOrgs = [];
    }
}

// Get volunteering categories
$volCategories = [];
if ($hasVolunteering && class_exists('\Nexus\Models\Category')) {
    try {
        $volCategories = \Nexus\Models\Category::getByType('vol_opportunity');
    } catch (\Exception $e) {
        $volCategories = [];
    }
}

// Context-aware default tab based on referrer URL
// Support both ?type= and ?tab= query parameters for flexibility
$defaultType = $_GET['type'] ?? $_GET['tab'] ?? 'post';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

// If group is pre-selected from URL, default to group tab
if ($preselectedGroup) {
    $defaultType = 'group';
} elseif (empty($_GET['type']) && empty($_GET['tab']) && !empty($referer)) {
    if (strpos($referer, '/listings') !== false) {
        $defaultType = 'listing';
    } elseif (strpos($referer, '/events') !== false && $hasEvents) {
        $defaultType = 'event';
    } elseif (strpos($referer, '/polls') !== false && $hasPolls) {
        $defaultType = 'poll';
    } elseif (strpos($referer, '/goals') !== false && $hasGoals) {
        $defaultType = 'goal';
    } elseif (strpos($referer, '/volunteering') !== false && $hasVolunteering) {
        $defaultType = 'volunteering';
    } elseif (strpos($referer, '/groups') !== false) {
        $defaultType = 'group';
    }
}

// Page title
$pageTitle = 'Create - ' . (\Nexus\Core\TenantContext::get()['name'] ?? 'Nexus');

// Get Mapbox token for location picker
$mapboxToken = '';
$envPath = __DIR__ . '/../../../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, 'MAPBOX_ACCESS_TOKEN=') === 0) {
            $raw = substr($line, 20);
            $mapboxToken = trim($raw, '"\' ');
            break;
        }
    }
}
// Fallback to tenant configuration
if (empty($mapboxToken)) {
    $t = \Nexus\Core\TenantContext::get();
    if (!empty($t['configuration'])) {
        $tConfig = json_decode($t['configuration'], true);
        if (!empty($tConfig['mapbox_token'])) {
            $mapboxToken = $tConfig['mapbox_token'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="theme-color" content="#6366f1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Mapbox GL & Geocoder -->
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet">
    <link href="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.css" rel="stylesheet">
    <script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
    <script src="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v5.0.0/mapbox-gl-geocoder.min.js"></script>

    <!-- Global functions - must be before buttons that use onclick -->
    <script>
    // Haptic feedback helper
    function haptic() {
        if (navigator.vibrate) navigator.vibrate(10);
    }

    // Tab switching
    function switchTab(type) {
        var pills = document.querySelectorAll('.multidraw-pill');
        for (var i = 0; i < pills.length; i++) {
            pills[i].classList.remove('active');
            pills[i].setAttribute('aria-selected', 'false');
        }
        var activePill = document.querySelector('.multidraw-pill[data-type="' + type + '"]');
        if (activePill) {
            activePill.classList.add('active');
            activePill.setAttribute('aria-selected', 'true');
        }

        var panels = document.querySelectorAll('.multidraw-panel');
        for (var j = 0; j < panels.length; j++) {
            panels[j].classList.remove('active');
        }
        var activePanel = document.getElementById('panel-' + type);
        if (activePanel) {
            activePanel.classList.add('active');
        }

        var headerBtn = document.getElementById('headerSubmitBtn');
        if (headerBtn) {
            if (type === 'post') {
                headerBtn.classList.add('visible');
                headerBtn.textContent = 'Post';
                headerBtn.setAttribute('form', 'form-post');
            } else {
                headerBtn.classList.remove('visible');
            }
        }
        haptic();
    }

    // Close form - always go to home page for safety
    function closeMultidrawForm() {
        haptic();
        window.location.href = '<?= $basePath ?>/';
    }

    // Listing type toggle (Offer/Request)
    function selectListingType(type) {
        var btns = document.querySelectorAll('.md-type-btn');
        for (var i = 0; i < btns.length; i++) {
            btns[i].classList.remove('active');
        }
        var activeBtn = document.querySelector('.md-type-btn.' + type);
        if (activeBtn) activeBtn.classList.add('active');

        var input = document.getElementById('listing-type-input');
        if (input) input.value = type;

        var typeText = document.getElementById('listing-type-text');
        var submitText = document.getElementById('listing-submit-text');

        if (type === 'offer') {
            if (typeText) typeText.textContent = 'offering';
            if (submitText) submitText.textContent = 'Create Offer';
        } else {
            if (typeText) typeText.textContent = 'requesting';
            if (submitText) submitText.textContent = 'Create Request';
        }
        haptic();
    }

    // Listing multi-step navigation
    function nextListingStep(step) {
        var title = document.getElementById('listing-title');
        var desc = document.getElementById('listing-desc');

        // Validate step 1 before moving to step 2
        if (step === 2) {
            if (!title || !title.value.trim()) {
                if (title) {
                    title.focus();
                    title.style.borderColor = '#ef4444';
                    setTimeout(function() { title.style.borderColor = ''; }, 400);
                }
                return;
            }
            if (!desc || !desc.value.trim()) {
                if (desc) {
                    desc.focus();
                    desc.style.borderColor = '#ef4444';
                    setTimeout(function() { desc.style.borderColor = ''; }, 400);
                }
                return;
            }
        }

        var steps = document.querySelectorAll('#panel-listing .md-step');
        for (var i = 0; i < steps.length; i++) {
            steps[i].classList.remove('active');
        }
        var targetStep = document.getElementById('listing-step-' + step);
        if (targetStep) targetStep.classList.add('active');

        var content = document.getElementById('contentArea');
        if (content) content.scrollTop = 0;
        haptic();
    }

    function prevListingStep(step) {
        var steps = document.querySelectorAll('#panel-listing .md-step');
        for (var i = 0; i < steps.length; i++) {
            steps[i].classList.remove('active');
        }
        var targetStep = document.getElementById('listing-step-' + step);
        if (targetStep) targetStep.classList.add('active');
        haptic();
    }

    // Image upload functions
    function previewImage(input, type) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(type + '-preview-img').src = e.target.result;
                document.querySelector('#' + type + '-image-upload .md-image-placeholder').style.display = 'none';
                document.getElementById(type + '-image-preview').style.display = 'block';
                document.getElementById(type + '-image-url').value = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function removeImage(type) {
        document.getElementById(type + '-image-file').value = '';
        document.getElementById(type + '-image-url').value = '';
        document.querySelector('#' + type + '-image-upload .md-image-placeholder').style.display = 'flex';
        document.getElementById(type + '-image-preview').style.display = 'none';
        haptic();
    }

    // Toggle AI assist visibility
    function toggleAiAssist(type, enabled) {
        var wrapper = document.getElementById('ai-wrapper-' + type);
        if (wrapper) {
            if (enabled) {
                wrapper.classList.add('visible');
            } else {
                wrapper.classList.remove('visible');
            }
        }
        haptic();
    }

    // Poll options counter (global for persistence)
    var pollOptionCount = 2;

    function addPollOption() {
        pollOptionCount++;
        var container = document.getElementById('poll-options');
        if (!container) return;
        var div = document.createElement('div');
        div.className = 'md-poll-option';
        div.innerHTML = '<input type="text" name="options[]" class="md-input" placeholder="Option ' + pollOptionCount + '">' +
            '<button type="button" class="md-poll-remove" onclick="this.parentElement.remove(); haptic();">' +
            '<i class="fa-solid fa-xmark"></i></button>';
        container.appendChild(div);
        div.querySelector('input').focus();
        haptic();
    }

    function updateAudience(select, textId) {
        var text = document.getElementById(textId);
        if (!text) return;
        if (select.value) {
            text.innerHTML = '<i class="fa-solid fa-users" style="margin-right: 4px;"></i> ' + select.options[select.selectedIndex].text;
        } else {
            text.innerHTML = '<i class="fa-solid fa-globe" style="margin-right: 4px;"></i> Public Feed';
        }
    }

    function clearGroupSelection() {
        var selectedGroupId = document.getElementById('selected-group-id');
        var selectedGroupDisplay = document.getElementById('selected-group-display');
        var groupSubmitBtn = document.getElementById('group-submit-btn');
        if (selectedGroupId) selectedGroupId.value = '';
        if (selectedGroupDisplay) selectedGroupDisplay.style.display = 'none';
        if (groupSubmitBtn) groupSubmitBtn.disabled = true;
        haptic();
    }

    // Location functions - these will be initialized properly after DOMContentLoaded
    // but need placeholder definitions for onclick handlers
    function selectLocationResult(pickerId, placeName, lat, lng) {
        var input = document.getElementById(pickerId + '-input');
        var latInput = document.getElementById(pickerId + '-lat');
        var lngInput = document.getElementById(pickerId + '-lng');
        var selected = document.getElementById(pickerId + '-selected');
        var selectedText = document.getElementById(pickerId + '-selected-text');

        if (input) input.value = placeName;
        if (latInput) latInput.value = lat;
        if (lngInput) lngInput.value = lng;

        if (selected && selectedText) {
            selectedText.textContent = placeName;
            selected.classList.add('visible');
        }
        haptic();
    }

    function detectLocation(pickerId) {
        // Placeholder - full implementation in DOMContentLoaded with MAPBOX_TOKEN
        console.log('Location detection initializing for:', pickerId);
    }

    function selectRemoteLocation(pickerId) {
        var input = document.getElementById(pickerId + '-input');
        var latInput = document.getElementById(pickerId + '-lat');
        var lngInput = document.getElementById(pickerId + '-lng');
        var selected = document.getElementById(pickerId + '-selected');
        var selectedText = document.getElementById(pickerId + '-selected-text');

        if (input) input.value = 'Remote';
        if (latInput) latInput.value = '';
        if (lngInput) lngInput.value = '';

        if (selected && selectedText) {
            selectedText.textContent = 'Remote / Online';
            selected.classList.add('visible');
            var icon = selected.querySelector('.md-location-selected-icon');
            if (icon) icon.innerHTML = '<i class="fa-solid fa-globe"></i>';
        }
        haptic();
    }

    function clearLocation(pickerId) {
        var input = document.getElementById(pickerId + '-input');
        var latInput = document.getElementById(pickerId + '-lat');
        var lngInput = document.getElementById(pickerId + '-lng');
        var selected = document.getElementById(pickerId + '-selected');

        if (input) {
            input.value = '';
            input.focus();
        }
        if (latInput) latInput.value = '';
        if (lngInput) lngInput.value = '';
        if (selected) selected.classList.remove('visible');
        haptic();
    }

    // SDG Toggle - exact copy from listings/create.php
    function toggleSDG(checkbox, color) {
        var card = checkbox.closest('.holo-sdg-card');
        if (checkbox.checked) {
            card.style.borderColor = color;
            card.style.backgroundColor = color + '18';
            card.style.boxShadow = '0 4px 15px ' + color + '30';
        } else {
            card.style.borderColor = '';
            card.style.backgroundColor = '';
            card.style.boxShadow = '';
        }
        haptic();
    }
    </script>

    <style>
    /* ============================================
       CSS RESET & VARIABLES
       ============================================ */
    *, *::before, *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    :root {
        /* Light Mode */
        --md-bg: #f0f2f5;
        --md-surface: rgba(255, 255, 255, 0.85);
        --md-surface-solid: #ffffff;
        --md-border: rgba(0, 0, 0, 0.08);
        --md-border-strong: rgba(0, 0, 0, 0.12);
        --md-text: #1c1e21;
        --md-text-secondary: #65676b;
        --md-text-muted: #8a8d91;
        --md-primary: #6366f1;
        --md-primary-hover: #4f46e5;
        --md-primary-light: rgba(99, 102, 241, 0.1);
        --md-success: #10b981;
        --md-success-light: rgba(16, 185, 129, 0.1);
        --md-warning: #f59e0b;
        --md-warning-light: rgba(245, 158, 11, 0.1);
        --md-danger: #ef4444;
        --md-danger-light: rgba(239, 68, 68, 0.1);

        /* Glassmorphism */
        --md-glass-bg: rgba(255, 255, 255, 0.72);
        --md-glass-border: rgba(255, 255, 255, 0.5);
        --md-glass-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 0 100px rgba(99, 102, 241, 0.08);

        /* Holographic accent */
        --md-holo-gradient: linear-gradient(135deg,
            rgba(99, 102, 241, 0.15) 0%,
            rgba(139, 92, 246, 0.1) 25%,
            rgba(236, 72, 153, 0.08) 50%,
            rgba(34, 211, 238, 0.1) 75%,
            rgba(99, 102, 241, 0.15) 100%);

        /* Safe areas */
        --safe-top: env(safe-area-inset-top, 0px);
        --safe-bottom: env(safe-area-inset-bottom, 0px);
        --safe-left: env(safe-area-inset-left, 0px);
        --safe-right: env(safe-area-inset-right, 0px);
    }

    [data-theme="dark"] {
        --md-bg: #18191a;
        --md-surface: rgba(36, 37, 38, 0.9);
        --md-surface-solid: #242526;
        --md-border: rgba(255, 255, 255, 0.08);
        --md-border-strong: rgba(255, 255, 255, 0.12);
        --md-text: #e4e6eb;
        --md-text-secondary: #b0b3b8;
        --md-text-muted: #8a8d91;

        /* Dark glassmorphism */
        --md-glass-bg: rgba(36, 37, 38, 0.85);
        --md-glass-border: rgba(255, 255, 255, 0.1);
        --md-glass-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 100px rgba(99, 102, 241, 0.15);
    }

    html, body {
        height: 100%;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: var(--md-bg);
        color: var(--md-text);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* ============================================
       MOBILE: FULL-SCREEN OVERLAY (100vw x 100vh)
       ============================================ */
    /* Hide backdrop on mobile - overlay is full screen */
    .multidraw-backdrop {
        display: contents; /* Let overlay render as if backdrop isn't there on mobile */
    }

    .multidraw-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        height: 100dvh; /* Dynamic viewport height for mobile */
        background: var(--md-surface-solid);
        z-index: 10000;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    /* ============================================
       HEADER WITH SAFE AREA SUPPORT
       ============================================ */
    .multidraw-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: calc(12px + var(--safe-top)) 16px 12px 16px;
        background: var(--md-surface-solid);
        border-bottom: 1px solid var(--md-border);
        flex-shrink: 0;
        position: relative;
        z-index: 100;
    }

    .multidraw-close {
        width: 44px;
        height: 44px;
        min-width: 44px;
        min-height: 44px;
        border-radius: 50%;
        border: none;
        background: rgba(255, 255, 255, 0.1);
        color: var(--md-text);
        font-size: 20px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        z-index: 101;
        -webkit-tap-highlight-color: transparent;
        position: relative;
        touch-action: manipulation;
        text-decoration: none; /* For anchor tag */
    }

    .multidraw-close:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
    }

    .multidraw-close:active {
        transform: scale(0.95);
        background: rgba(255, 255, 255, 0.25);
    }

    /* Ensure icon is centered and clickable */
    .multidraw-close i {
        pointer-events: none;
    }

    .multidraw-title {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        font-size: 18px;
        font-weight: 700;
        color: var(--md-text);
        letter-spacing: -0.02em;
    }

    .multidraw-submit-header {
        padding: 10px 20px;
        border-radius: 20px;
        border: none;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
        opacity: 0;
        pointer-events: none;
    }

    .multidraw-submit-header.visible {
        opacity: 1;
        pointer-events: auto;
    }

    .multidraw-submit-header:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
    }

    .multidraw-submit-header:active {
        transform: scale(0.97);
    }

    /* ============================================
       HORIZONTAL SCROLLABLE PILL NAVIGATION
       ============================================ */
    .multidraw-nav {
        display: flex;
        gap: 8px;
        padding: 12px 16px;
        background: var(--md-surface-solid);
        border-bottom: 1px solid var(--md-border);
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        flex-shrink: 0;
        scroll-behavior: smooth;
        scroll-snap-type: x proximity;
    }

    .multidraw-nav::-webkit-scrollbar {
        display: none;
    }

    .multidraw-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border-radius: 100px;
        border: 1.5px solid var(--md-border-strong);
        background: var(--md-surface-solid);
        color: var(--md-text-secondary);
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        flex-shrink: 0;
        scroll-snap-align: start;
        -webkit-tap-highlight-color: transparent;
        user-select: none;
    }

    .multidraw-pill i {
        font-size: 15px;
        transition: transform 0.2s ease;
    }

    .multidraw-pill:hover {
        border-color: var(--md-primary);
        color: var(--md-primary);
        background: var(--md-primary-light);
    }

    .multidraw-pill:active {
        transform: scale(0.96);
    }

    .multidraw-pill.active {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        border-color: transparent;
        color: white;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35);
    }

    .multidraw-pill.active i {
        transform: scale(1.1);
    }

    /* Colored variants for specific pills */
    .multidraw-pill[data-type="listing"]:not(.active):hover {
        border-color: var(--md-success);
        color: var(--md-success);
        background: var(--md-success-light);
    }

    .multidraw-pill[data-type="event"]:not(.active):hover {
        border-color: #ec4899;
        color: #ec4899;
        background: rgba(236, 72, 153, 0.1);
    }

    .multidraw-pill[data-type="volunteering"]:not(.active):hover {
        border-color: #f59e0b;
        color: #f59e0b;
        background: var(--md-warning-light);
    }

    /* ============================================
       CONTENT AREA
       ============================================ */
    .multidraw-content {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        padding-bottom: calc(20px + var(--safe-bottom));
    }

    /* Form panels */
    .multidraw-panel {
        display: none;
        padding: 20px 16px;
        animation: panelFadeIn 0.3s ease;
    }

    .multidraw-panel.active {
        display: block;
    }

    @keyframes panelFadeIn {
        from {
            opacity: 0;
            transform: translateY(12px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ============================================
       FORM ELEMENTS
       ============================================ */
    .md-user-row {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .md-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--md-border);
    }

    .md-user-info {
        flex: 1;
    }

    .md-user-name {
        font-size: 15px;
        font-weight: 600;
        color: var(--md-text);
        margin-bottom: 2px;
    }

    .md-audience-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: var(--md-primary-light);
        border-radius: 16px;
        font-size: 12px;
        font-weight: 500;
        color: var(--md-primary);
        cursor: pointer;
        transition: all 0.2s;
        border: none;
    }

    .md-audience-btn:hover {
        background: rgba(99, 102, 241, 0.15);
    }

    .md-audience-btn i:last-child {
        font-size: 10px;
        opacity: 0.7;
    }

    /* Textarea */
    .md-textarea {
        width: 100%;
        min-height: 140px;
        padding: 16px;
        border: none;
        background: transparent;
        color: var(--md-text);
        font-size: 17px;
        font-family: inherit;
        line-height: 1.5;
        resize: none;
        outline: none;
    }

    .md-textarea::placeholder {
        color: var(--md-text-muted);
    }

    .md-textarea:focus {
        outline: none;
    }

    /* Input fields */
    .md-field {
        margin-bottom: 16px;
    }

    .md-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--md-text-secondary);
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .md-input {
        width: 100%;
        padding: 14px 16px;
        border: 1.5px solid var(--md-border-strong);
        border-radius: 12px;
        background: var(--md-surface-solid);
        color: var(--md-text);
        font-size: 15px;
        font-family: inherit;
        transition: all 0.2s ease;
    }

    .md-input:focus {
        outline: none;
        border-color: var(--md-primary);
        box-shadow: 0 0 0 3px var(--md-primary-light);
    }

    .md-input::placeholder {
        color: var(--md-text-muted);
    }

    .md-select {
        width: 100%;
        padding: 14px 44px 14px 16px;
        border: 1.5px solid var(--md-border-strong);
        border-radius: 12px;
        background: var(--md-surface-solid);
        color: var(--md-text);
        font-size: 15px;
        font-family: inherit;
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 16px center;
        transition: all 0.2s ease;
    }

    .md-select:focus {
        outline: none;
        border-color: var(--md-primary);
        box-shadow: 0 0 0 3px var(--md-primary-light);
    }

    .md-hint {
        font-size: 12px;
        color: var(--md-text-muted);
        margin-top: 6px;
    }

    /* Grid layouts */
    .md-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .md-hint-inline {
        font-weight: 400;
        font-size: 0.85rem;
        color: var(--md-text-muted);
    }

    /* ============================================
       SERVICE DETAILS / ATTRIBUTES
       ============================================ */
    .md-attributes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 10px;
    }

    .md-attribute-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
        background: rgba(0, 0, 0, 0.02);
        border: 1px solid var(--md-border);
    }

    [data-theme="dark"] .md-attribute-item {
        background: rgba(255, 255, 255, 0.03);
    }

    .md-attribute-item:hover {
        background: var(--md-primary-light);
        border-color: var(--md-primary);
    }

    .md-attribute-item input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--md-primary);
        margin: 0;
        flex-shrink: 0;
    }

    .md-attribute-item span {
        font-size: 0.9rem;
        color: var(--md-text);
    }

    /* ============================================
       LISTING TYPE TOGGLE (Offer/Request)
       ============================================ */
    .md-type-toggle {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
    }

    .md-type-btn {
        flex: 1;
        padding: 20px 16px;
        border-radius: 16px;
        border: 2px solid var(--md-border-strong);
        background: var(--md-surface-solid);
        color: var(--md-text);
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        text-align: center;
        transition: all 0.25s ease;
    }

    .md-type-btn i {
        display: block;
        font-size: 28px;
        margin-bottom: 8px;
    }

    .md-type-btn.offer {
        border-color: var(--md-success);
        color: var(--md-success);
    }

    .md-type-btn.offer.active {
        background: var(--md-success);
        color: white;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.35);
    }

    .md-type-btn.request {
        border-color: var(--md-warning);
        color: var(--md-warning);
    }

    .md-type-btn.request.active {
        background: var(--md-warning);
        color: white;
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.35);
    }

    /* ============================================
       SUBMIT BUTTONS
       ============================================ */
    .md-submit-btn {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 16px 24px;
        border-radius: 14px;
        border: none;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        margin-top: 24px;
        transition: all 0.25s ease;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }

    .md-submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
    }

    .md-submit-btn:active {
        transform: scale(0.98);
    }

    .md-submit-btn.success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }

    .md-submit-btn.warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
    }

    /* ============================================
       MULTI-STEP FORMS
       ============================================ */
    .md-step {
        display: none;
        animation: panelFadeIn 0.3s ease;
    }

    .md-step.active {
        display: block;
    }

    .md-step-progress {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-bottom: 24px;
    }

    .md-step-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--md-border-strong);
        transition: all 0.3s ease;
    }

    .md-step-dot.active {
        width: 28px;
        border-radius: 4px;
        background: var(--md-primary);
    }

    .md-back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 0;
        margin-bottom: 16px;
        background: transparent;
        border: none;
        color: var(--md-text-secondary);
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: color 0.2s;
    }

    .md-back-btn:hover {
        color: var(--md-primary);
    }

    /* Step Header (for optional steps) */
    .md-step-header {
        text-align: center;
        margin-bottom: 24px;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--md-border);
    }

    .md-step-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--md-text);
        margin: 0 0 6px 0;
    }

    .md-step-subtitle {
        font-size: 13px;
        color: var(--md-text-tertiary);
        margin: 0;
    }

    /* Skip Section hint */
    .md-skip-section {
        margin-top: 24px;
        text-align: center;
    }

    .md-skip-hint {
        margin-top: 12px;
        font-size: 12px;
        color: var(--md-text-tertiary);
    }

    /* SDG Section - Copied from listings/create.php */
    .holo-sdg-accordion {
        border: 1px solid rgba(0, 0, 0, 0.06);
        border-radius: 20px;
        overflow: hidden;
        margin-bottom: 20px;
        background: rgba(0, 0, 0, 0.01);
    }

    [data-theme="dark"] .holo-sdg-accordion {
        border-color: rgba(255, 255, 255, 0.06);
        background: rgba(255, 255, 255, 0.01);
    }

    .holo-sdg-header {
        padding: 20px 24px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-weight: 600;
        color: var(--md-text);
        transition: background 0.2s ease;
        list-style: none;
    }

    .holo-sdg-header::-webkit-details-marker {
        display: none;
    }

    [data-theme="dark"] .holo-sdg-header {
        color: #e2e8f0;
    }

    .holo-sdg-header:hover {
        background: rgba(0, 0, 0, 0.02);
    }

    .holo-sdg-header i {
        transition: transform 0.3s ease;
        color: var(--md-text-tertiary);
    }

    .holo-sdg-accordion[open] .holo-sdg-header i {
        transform: rotate(180deg);
    }

    .holo-sdg-content {
        padding: 0 24px 24px;
        border-top: 1px solid rgba(0, 0, 0, 0.04);
    }

    [data-theme="dark"] .holo-sdg-content {
        border-top-color: rgba(255, 255, 255, 0.04);
    }

    .holo-sdg-intro {
        font-size: 0.9rem;
        color: var(--md-text-secondary);
        margin: 16px 0;
    }

    .holo-sdg-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 10px;
    }

    .holo-sdg-card {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 2px solid rgba(0, 0, 0, 0.06);
        background: rgba(255, 255, 255, 0.5);
    }

    [data-theme="dark"] .holo-sdg-card {
        border-color: rgba(255, 255, 255, 0.08);
        background: rgba(0, 0, 0, 0.15);
    }

    .holo-sdg-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .holo-sdg-card input {
        display: none;
    }

    .holo-sdg-card .sdg-icon {
        font-size: 1.3rem;
    }

    .holo-sdg-card .sdg-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--md-text);
    }

    [data-theme="dark"] .holo-sdg-card .sdg-label {
        color: #e2e8f0;
    }

    .md-next-btn {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 16px 24px;
        border-radius: 14px;
        border: none;
        background: var(--md-surface-solid);
        border: 1.5px solid var(--md-primary);
        color: var(--md-primary);
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        margin-top: 20px;
        transition: all 0.25s ease;
    }

    .md-next-btn:hover {
        background: var(--md-primary);
        color: white;
    }

    /* ============================================
       MEDIA ACTIONS ROW
       ============================================ */
    .md-media-row {
        display: flex;
        gap: 8px;
        padding: 16px 0;
        border-top: 1px solid var(--md-border);
        margin-top: 16px;
    }

    .md-media-btn {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        border: none;
        background: var(--md-primary-light);
        color: var(--md-primary);
        font-size: 18px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .md-media-btn:hover {
        background: rgba(99, 102, 241, 0.15);
        transform: scale(1.05);
    }

    .md-media-btn:active {
        transform: scale(0.95);
    }

    /* ============================================
       IMAGE UPLOAD
       ============================================ */
    .md-image-upload {
        position: relative;
    }

    .md-image-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 40px;
        border: 2px dashed var(--md-border-strong);
        border-radius: 16px;
        background: var(--md-surface);
        color: var(--md-text-muted);
        cursor: pointer;
        transition: all 0.25s ease;
    }

    .md-image-placeholder:hover {
        border-color: var(--md-primary);
        background: var(--md-primary-light);
        color: var(--md-primary);
    }

    .md-image-placeholder i {
        font-size: 36px;
    }

    .md-image-preview {
        position: relative;
        border-radius: 16px;
        overflow: hidden;
    }

    .md-image-preview img {
        width: 100%;
        max-height: 200px;
        object-fit: cover;
        border-radius: 16px;
    }

    .md-image-remove {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: none;
        background: rgba(0, 0, 0, 0.7);
        color: white;
        font-size: 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .md-image-remove:hover {
        background: var(--md-danger);
    }

    /* ============================================
       POLL OPTIONS
       ============================================ */
    .md-poll-options {
        margin-bottom: 16px;
    }

    .md-poll-option {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
    }

    .md-poll-option input {
        flex: 1;
    }

    .md-poll-remove {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        border: none;
        background: var(--md-danger-light);
        color: var(--md-danger);
        font-size: 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .md-poll-remove:hover {
        background: var(--md-danger);
        color: white;
    }

    .md-add-option {
        width: 100%;
        padding: 14px;
        border: 2px dashed var(--md-border-strong);
        border-radius: 12px;
        background: transparent;
        color: var(--md-primary);
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .md-add-option:hover {
        border-color: var(--md-primary);
        background: var(--md-primary-light);
    }

    /* ============================================
       CHECKBOX / TOGGLE
       ============================================ */
    .md-checkbox-label {
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        font-size: 15px;
        color: var(--md-text);
        padding: 12px 0;
    }

    .md-checkbox-label input[type="checkbox"] {
        width: 22px;
        height: 22px;
        accent-color: var(--md-primary);
        cursor: pointer;
    }

    /* ============================================
       ALERT MESSAGES
       ============================================ */
    .md-alert {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 12px;
        margin-bottom: 16px;
        font-size: 14px;
        font-weight: 500;
    }

    .md-alert.error {
        background: var(--md-danger-light);
        color: var(--md-danger);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .md-alert.success {
        background: var(--md-success-light);
        color: var(--md-success);
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .md-alert.info {
        background: var(--md-primary-light);
        color: var(--md-primary);
        border: 1px solid rgba(99, 102, 241, 0.2);
    }

    /* ============================================
       VOLUNTEERING STATES
       ============================================ */
    .md-vol-card {
        background: var(--md-surface);
        border: 1px solid var(--md-border);
        border-radius: 16px;
        padding: 24px;
        text-align: center;
    }

    .md-vol-icon {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: var(--md-warning-light);
        color: var(--md-warning);
        font-size: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
    }

    .md-vol-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--md-text);
        margin-bottom: 8px;
    }

    .md-vol-desc {
        font-size: 14px;
        color: var(--md-text-secondary);
        margin-bottom: 20px;
        line-height: 1.5;
    }

    /* ============================================
       GROUP SEARCH DROPDOWN
       ============================================ */
    .md-group-search-container {
        position: relative;
    }

    .md-group-search {
        width: 100%;
        padding: 14px 16px 14px 44px;
        border: 1.5px solid var(--md-border-strong);
        border-radius: 12px;
        background: var(--md-surface-solid);
        color: var(--md-text);
        font-size: 15px;
        font-family: inherit;
        transition: all 0.2s ease;
    }

    .md-group-search:focus {
        outline: none;
        border-color: var(--md-primary);
        box-shadow: 0 0 0 3px var(--md-primary-light);
    }

    .md-group-search-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--md-text-muted);
        font-size: 16px;
        pointer-events: none;
    }

    .md-group-results {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        background: var(--md-surface-solid);
        border: 1px solid var(--md-border-strong);
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        max-height: 240px;
        overflow-y: auto;
        z-index: 100;
        display: none;
    }

    .md-group-results.visible {
        display: block;
    }

    .md-group-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        cursor: pointer;
        transition: background 0.15s;
    }

    .md-group-item:hover {
        background: var(--md-primary-light);
    }

    .md-group-item.selected {
        background: var(--md-primary-light);
    }

    .md-group-avatar {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: var(--md-primary-light);
        color: var(--md-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 14px;
    }

    .md-group-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 10px;
        object-fit: cover;
    }

    .md-group-name {
        flex: 1;
        font-size: 14px;
        font-weight: 500;
        color: var(--md-text);
    }

    .md-group-members {
        font-size: 12px;
        color: var(--md-text-muted);
    }

    .md-selected-group {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        background: var(--md-success-light);
        border: 1.5px solid var(--md-success);
        border-radius: 12px;
        margin-bottom: 16px;
    }

    .md-selected-group-info {
        flex: 1;
    }

    .md-selected-group-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--md-success);
    }

    .md-selected-group-label {
        font-size: 11px;
        color: var(--md-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .md-selected-group-remove {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: none;
        background: var(--md-danger-light);
        color: var(--md-danger);
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* ============================================
       DESKTOP: GLASSMORPHISM MODAL
       ============================================ */
    @media (min-width: 768px) {
        body {
            overflow: hidden;
        }

        .multidraw-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: 9999;
            display: flex; /* Always flex on desktop - overlay is inside */
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .multidraw-overlay {
            position: relative;
            width: 100%;
            max-width: 720px;
            height: auto;
            max-height: calc(100vh - 80px);
            border-radius: 24px;
            background: var(--md-glass-bg);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--md-glass-border);
            box-shadow: var(--md-glass-shadow);
            overflow: hidden;
        }

        /* Holographic accent effect */
        .multidraw-overlay::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #6366f1, #ec4899, #22d3ee, #6366f1);
            background-size: 200% 100%;
            animation: holoShimmer 3s linear infinite;
            pointer-events: none;
            z-index: 1;
        }

        @keyframes holoShimmer {
            0% { background-position: 0% 50%; }
            100% { background-position: 200% 50%; }
        }

        .multidraw-header {
            padding: 16px 20px;
            background: transparent;
            border-bottom: 1px solid var(--md-border);
        }

        .multidraw-close {
            background: var(--md-surface);
        }

        .multidraw-nav {
            padding: 12px 20px;
            background: transparent;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            overflow-x: visible;
        }

        .multidraw-content {
            max-height: 60vh;
            padding-bottom: 0;
        }

        .multidraw-panel {
            padding: 20px;
        }

        /* Subtle inner glow */
        .multidraw-overlay::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--md-holo-gradient);
            pointer-events: none;
            opacity: 0.3;
            z-index: -1;
        }
    }

    /* ============================================
       ANIMATIONS & UTILITIES
       ============================================ */
    .hidden {
        display: none !important;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        20%, 60% { transform: translateX(-5px); }
        40%, 80% { transform: translateX(5px); }
    }

    .shake {
        animation: shake 0.4s ease;
    }

    /* Skeleton loading */
    .skeleton {
        background: linear-gradient(90deg, var(--md-border) 25%, var(--md-surface) 50%, var(--md-border) 75%);
        background-size: 200% 100%;
        animation: skeleton 1.5s ease-in-out infinite;
    }

    @keyframes skeleton {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* Focus visible for accessibility */
    .multidraw-pill:focus-visible,
    .md-input:focus-visible,
    .md-select:focus-visible,
    .md-submit-btn:focus-visible,
    .multidraw-close:focus-visible {
        outline: 2px solid var(--md-primary);
        outline-offset: 2px;
    }

    /* ============================================
       MOBILE-FRIENDLY LOCATION PICKER
       Native iOS/Android style with Mapbox integration
       ============================================ */
    .md-location-picker {
        position: relative;
        width: 100%;
    }

    .md-location-input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        gap: 0;
    }

    .md-location-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--md-text-muted);
        font-size: 16px;
        pointer-events: none;
        z-index: 2;
        transition: color 0.2s;
    }

    .md-location-input {
        width: 100%;
        padding: 14px 100px 14px 42px;
        border: 1.5px solid var(--md-border-strong);
        border-radius: 12px;
        background: var(--md-surface-solid);
        color: var(--md-text);
        font-size: 15px;
        font-family: inherit;
        transition: all 0.2s ease;
    }

    .md-location-input:focus {
        outline: none;
        border-color: var(--md-primary);
        box-shadow: 0 0 0 3px var(--md-primary-light);
    }

    .md-location-input:focus + .md-location-icon,
    .md-location-input:not(:placeholder-shown) + .md-location-icon {
        color: var(--md-primary);
    }

    .md-location-input::placeholder {
        color: var(--md-text-muted);
    }

    .md-location-actions {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        display: flex;
        gap: 6px;
        z-index: 2;
    }

    .md-location-btn {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        border: none;
        background: var(--md-primary-light);
        color: var(--md-primary);
        font-size: 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        -webkit-tap-highlight-color: transparent;
    }

    .md-location-btn:hover {
        background: rgba(99, 102, 241, 0.15);
    }

    .md-location-btn:active {
        transform: scale(0.92);
    }

    .md-location-btn.detecting {
        animation: pulse 1s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .md-location-btn.success {
        background: var(--md-success-light);
        color: var(--md-success);
    }

    /* Location suggestions dropdown */
    .md-location-suggestions {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        background: var(--md-surface-solid);
        border: 1px solid var(--md-border-strong);
        border-radius: 14px;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        max-height: 280px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        -webkit-overflow-scrolling: touch;
    }

    .md-location-suggestions.visible {
        display: block;
        animation: dropdownSlide 0.2s ease;
    }

    @keyframes dropdownSlide {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .md-location-suggestion {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 16px;
        cursor: pointer;
        transition: background 0.15s;
        border-bottom: 1px solid var(--md-border);
    }

    .md-location-suggestion:last-child {
        border-bottom: none;
    }

    .md-location-suggestion:hover,
    .md-location-suggestion:focus {
        background: var(--md-primary-light);
    }

    .md-location-suggestion:active {
        background: rgba(99, 102, 241, 0.15);
    }

    .md-location-suggestion-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: var(--md-primary-light);
        color: var(--md-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 14px;
    }

    .md-location-suggestion-icon.current {
        background: var(--md-success-light);
        color: var(--md-success);
    }

    .md-location-suggestion-text {
        flex: 1;
        min-width: 0;
    }

    .md-location-suggestion-main {
        font-size: 14px;
        font-weight: 500;
        color: var(--md-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .md-location-suggestion-sub {
        font-size: 12px;
        color: var(--md-text-muted);
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Current location header in dropdown */
    .md-location-current-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        background: var(--md-success-light);
        color: var(--md-success);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s;
    }

    .md-location-current-header:hover {
        background: rgba(16, 185, 129, 0.15);
    }

    .md-location-current-header i {
        font-size: 16px;
    }

    /* Remote/Online option */
    .md-location-remote-option {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        background: var(--md-warning-light);
        color: #b45309;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s;
        border-top: 1px solid var(--md-border);
    }

    [data-theme="dark"] .md-location-remote-option {
        color: var(--md-warning);
    }

    .md-location-remote-option:hover {
        background: rgba(245, 158, 11, 0.15);
    }

    /* Selected location display */
    .md-location-selected {
        display: none;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        background: var(--md-success-light);
        border: 1.5px solid var(--md-success);
        border-radius: 12px;
        margin-top: 8px;
    }

    .md-location-selected.visible {
        display: flex;
    }

    .md-location-selected-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: var(--md-success);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    .md-location-selected-text {
        flex: 1;
        font-size: 14px;
        font-weight: 500;
        color: var(--md-success);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .md-location-selected-remove {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: none;
        background: rgba(239, 68, 68, 0.1);
        color: var(--md-danger);
        font-size: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .md-location-selected-remove:hover {
        background: var(--md-danger);
        color: white;
    }

    /* Mobile-specific adjustments */
    @media (max-width: 768px) {
        .md-location-suggestions {
            position: fixed;
            top: auto;
            bottom: 0;
            left: 0;
            right: 0;
            max-height: 60vh;
            border-radius: 20px 20px 0 0;
            border: none;
            box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.2);
            padding-bottom: var(--safe-bottom);
        }

        .md-location-suggestions::before {
            content: '';
            display: block;
            width: 40px;
            height: 4px;
            background: var(--md-border-strong);
            border-radius: 2px;
            margin: 10px auto 6px;
        }

        .md-location-suggestion {
            padding: 16px;
        }

        .md-location-suggestion-icon {
            width: 42px;
            height: 42px;
        }

        /* Backdrop for mobile dropdown */
        .md-location-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 999;
        }

        .md-location-backdrop.visible {
            display: block;
        }
    }

    /* Override Mapbox geocoder styles */
    .mapboxgl-ctrl-geocoder {
        display: none !important;
    }

    /* ============================================
       AI CONTENT GENERATION (Opt-in)
       ============================================ */
    .md-ai-toggle {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        margin-bottom: 12px;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.08) 0%, rgba(139, 92, 246, 0.08) 100%);
        border: 1px dashed rgba(99, 102, 241, 0.3);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 13px;
        color: var(--md-text-secondary);
    }

    .md-ai-toggle:hover {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(139, 92, 246, 0.12) 100%);
        border-color: var(--md-primary);
    }

    .md-ai-toggle input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--md-primary);
        cursor: pointer;
    }

    .md-ai-toggle-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        border-radius: 6px;
        color: white;
        font-size: 12px;
    }

    .md-ai-toggle-text {
        flex: 1;
    }

    .md-ai-toggle-text strong {
        color: var(--md-text);
        font-weight: 600;
    }

    /* AI Generate Button (hidden by default) */
    .md-ai-generate-wrapper {
        display: none;
        margin-bottom: 12px;
        animation: fadeSlideIn 0.3s ease;
    }

    .md-ai-generate-wrapper.visible {
        display: block;
    }

    @keyframes fadeSlideIn {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .md-ai-generate-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        font-size: 14px;
        font-weight: 600;
        font-family: inherit;
        color: #ffffff;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
        border: none;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
        position: relative;
        overflow: hidden;
    }

    .md-ai-generate-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s ease;
    }

    .md-ai-generate-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(99, 102, 241, 0.45);
    }

    .md-ai-generate-btn:hover::before {
        left: 100%;
    }

    .md-ai-generate-btn:active {
        transform: translateY(0) scale(0.98);
    }

    .md-ai-generate-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    .md-ai-generate-btn.loading .ai-btn-content {
        display: none;
    }

    .md-ai-generate-btn.loading .ai-btn-loading {
        display: inline-flex !important;
    }

    .md-ai-generate-btn .ai-btn-loading {
        display: none;
        align-items: center;
        gap: 8px;
    }

    .md-ai-spinner {
        animation: aiSpin 1s linear infinite;
    }

    @keyframes aiSpin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .md-ai-status {
        margin-top: 8px;
        font-size: 13px;
        min-height: 18px;
    }

    .md-ai-status.success {
        color: var(--md-success);
    }

    .md-ai-status.error {
        color: var(--md-danger);
    }

    .md-ai-status.info {
        color: var(--md-primary);
    }

    /* Mobile responsiveness */
    @media (max-width: 768px) {
        .md-ai-generate-btn {
            width: 100%;
            justify-content: center;
            padding: 12px 20px;
        }
    }

    /* Dark mode adjustments */
    [data-theme="dark"] .md-ai-toggle {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(139, 92, 246, 0.12) 100%);
        border-color: rgba(99, 102, 241, 0.25);
    }

    [data-theme="dark"] .md-ai-generate-btn {
        box-shadow: 0 4px 14px rgba(99, 102, 241, 0.25);
    }
    </style>
</head>
<body>
    <!-- Desktop backdrop (centered flex container) - overlay starts inside for desktop -->
    <div class="multidraw-backdrop" id="multidrawBackdrop">
        <!-- Main Overlay Container -->
        <div class="multidraw-overlay" id="multidrawOverlay">
        <!-- Header with Safe Area -->
        <header class="multidraw-header">
            <button type="button" class="multidraw-close" id="closeBtn" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <h1 class="multidraw-title">Create</h1>
            <button type="submit" class="multidraw-submit-header" id="headerSubmitBtn" form="active-form">
                Post
            </button>
        </header>

        <!-- Horizontal Scrollable Pill Navigation -->
        <nav class="multidraw-nav" id="pillNav" role="tablist" aria-label="Content type selection">
            <button type="button" class="multidraw-pill <?= $defaultType === 'post' ? 'active' : '' ?>" data-type="post" role="tab" aria-selected="<?= $defaultType === 'post' ? 'true' : 'false' ?>" onclick="switchTab('post')">
                <i class="fa-solid fa-pen-to-square"></i>
                <span>Post</span>
            </button>
            <button type="button" class="multidraw-pill <?= $defaultType === 'listing' ? 'active' : '' ?>" data-type="listing" role="tab" aria-selected="<?= $defaultType === 'listing' ? 'true' : 'false' ?>" onclick="switchTab('listing')">
                <i class="fa-solid fa-hand-holding-heart"></i>
                <span>Listing</span>
            </button>
            <?php if ($hasEvents): ?>
            <button type="button" class="multidraw-pill <?= $defaultType === 'event' ? 'active' : '' ?>" data-type="event" role="tab" aria-selected="<?= $defaultType === 'event' ? 'true' : 'false' ?>" onclick="switchTab('event')">
                <i class="fa-solid fa-calendar-star"></i>
                <span>Event</span>
            </button>
            <?php endif; ?>
            <?php if ($hasPolls): ?>
            <button type="button" class="multidraw-pill <?= $defaultType === 'poll' ? 'active' : '' ?>" data-type="poll" role="tab" aria-selected="<?= $defaultType === 'poll' ? 'true' : 'false' ?>" onclick="switchTab('poll')">
                <i class="fa-solid fa-chart-bar"></i>
                <span>Poll</span>
            </button>
            <?php endif; ?>
            <?php if ($hasGoals): ?>
            <button type="button" class="multidraw-pill <?= $defaultType === 'goal' ? 'active' : '' ?>" data-type="goal" role="tab" aria-selected="<?= $defaultType === 'goal' ? 'true' : 'false' ?>" onclick="switchTab('goal')">
                <i class="fa-solid fa-bullseye"></i>
                <span>Goal</span>
            </button>
            <?php endif; ?>
            <?php if ($hasVolunteering): ?>
            <button type="button" class="multidraw-pill <?= $defaultType === 'volunteering' ? 'active' : '' ?>" data-type="volunteering" role="tab" aria-selected="<?= $defaultType === 'volunteering' ? 'true' : 'false' ?>" onclick="switchTab('volunteering')">
                <i class="fa-solid fa-hands-helping"></i>
                <span>Volunteer</span>
            </button>
            <?php endif; ?>
            <?php if (!empty($userGroups)): ?>
            <button type="button" class="multidraw-pill <?= $defaultType === 'group' ? 'active' : '' ?>" data-type="group" role="tab" aria-selected="<?= $defaultType === 'group' ? 'true' : 'false' ?>" onclick="switchTab('group')">
                <i class="fa-solid fa-users"></i>
                <span>Group</span>
            </button>
            <?php endif; ?>
        </nav>

        <!-- Content Panels -->
        <div class="multidraw-content" id="contentArea">
            <?php if ($error): ?>
            <div class="md-alert error" style="margin: 16px;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="md-alert success" style="margin: 16px;">
                <i class="fa-solid fa-circle-check"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <!-- ==================== POST PANEL ==================== -->
            <div class="multidraw-panel <?= $defaultType === 'post' ? 'active' : '' ?>" id="panel-post" role="tabpanel">
                <form id="form-post" action="<?= $basePath ?>/compose" method="POST">
                    <input type="hidden" name="post_type" value="post">

                    <div class="md-user-row">
                        <img src="<?= htmlspecialchars($userAvatar) ?>" loading="lazy" alt="" class="md-avatar">
                        <div class="md-user-info">
                            <div class="md-user-name"><?= htmlspecialchars($userName) ?></div>
                            <button type="button" class="md-audience-btn" onclick="document.getElementById('post-group-select').click()">
                                <i class="fa-solid fa-globe"></i>
                                <span id="post-audience-text">Public Feed</span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </button>
                        </div>
                    </div>

                    <select id="post-group-select" name="group_id" style="display: none;" onchange="updateAudience(this, 'post-audience-text')">
                        <option value="">Public Feed</option>
                        <?php foreach ($userGroups as $group): ?>
                        <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <textarea name="content" class="md-textarea" placeholder="What's on your mind, <?= htmlspecialchars(explode(' ', $userName)[0]) ?>?" autofocus></textarea>

                    <div class="md-media-row">
                        <button type="button" class="md-media-btn" title="Add Photo">
                            <i class="fa-solid fa-image"></i>
                        </button>
                        <button type="button" class="md-media-btn" title="Add Video">
                            <i class="fa-solid fa-video"></i>
                        </button>
                        <button type="button" class="md-media-btn" title="Add Emoji">
                            <i class="fa-solid fa-face-smile"></i>
                        </button>
                        <button type="button" class="md-media-btn" title="Tag People">
                            <i class="fa-solid fa-user-tag"></i>
                        </button>
                    </div>

                    <button type="submit" class="md-submit-btn">
                        <i class="fa-solid fa-paper-plane"></i>
                        Post
                    </button>
                </form>
            </div>

            <!-- ==================== LISTING PANEL ==================== -->
            <div class="multidraw-panel <?= $defaultType === 'listing' ? 'active' : '' ?>" id="panel-listing" role="tabpanel">
                <form id="form-listing" action="<?= $basePath ?>/compose" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="post_type" value="listing">
                    <input type="hidden" name="listing_type" id="listing-type-input" value="offer">

                    <!-- Step 1: Type & Basic Info -->
                    <div class="md-step active" id="listing-step-1">
                        <div class="md-step-progress">
                            <div class="md-step-dot active"></div>
                            <div class="md-step-dot"></div>
                            <div class="md-step-dot"></div>
                        </div>

                        <div class="md-type-toggle">
                            <button type="button" class="md-type-btn offer active" onclick="selectListingType('offer')">
                                <i class="fa-solid fa-gift"></i>
                                Offer
                            </button>
                            <button type="button" class="md-type-btn request" onclick="selectListingType('request')">
                                <i class="fa-solid fa-hand"></i>
                                Request
                            </button>
                        </div>

                        <div class="md-field">
                            <label class="md-label">What are you <span id="listing-type-text">offering</span>?</label>
                            <input type="text" name="title" id="listing-title" class="md-input" placeholder="e.g., Guitar lessons, Help with moving..." required>
                        </div>

                        <div class="md-field">
                            <label class="md-label">Description</label>
                            <!-- AI Assist Toggle -->
                            <label class="md-ai-toggle">
                                <input type="checkbox" id="ai-assist-listing" onchange="toggleAiAssist('listing', this.checked)">
                                <span class="md-ai-toggle-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                                <span class="md-ai-toggle-text"><strong>Need help?</strong> Let AI write your description</span>
                            </label>
                            <!-- AI Generate Button (hidden until toggle is checked) -->
                            <div class="md-ai-generate-wrapper" id="ai-wrapper-listing">
                                <button type="button" class="md-ai-generate-btn" onclick="generateAiContent('listing')">
                                    <span class="ai-btn-content">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                                        Generate Description
                                    </span>
                                    <span class="ai-btn-loading">
                                        <i class="fa-solid fa-spinner md-ai-spinner"></i>
                                        Generating...
                                    </span>
                                </button>
                                <div class="md-ai-status" id="ai-status-listing"></div>
                            </div>
                            <textarea name="description" id="listing-desc" class="md-input" style="min-height: 100px; resize: vertical;" placeholder="Describe what you're offering or need help with..." required></textarea>
                        </div>

                        <button type="button" class="md-next-btn" onclick="nextListingStep(2)">
                            Next: Details
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>

                    <!-- Step 2: Category, Location, Image -->
                    <div class="md-step" id="listing-step-2">
                        <div class="md-step-progress">
                            <div class="md-step-dot"></div>
                            <div class="md-step-dot active"></div>
                            <div class="md-step-dot"></div>
                        </div>

                        <button type="button" class="md-back-btn" onclick="prevListingStep(1)">
                            <i class="fa-solid fa-arrow-left"></i>
                            Back
                        </button>

                        <div class="md-field">
                            <label class="md-label">Category</label>
                            <select name="category_id" class="md-select">
                                <option value="">Select a category (optional)</option>
                                <?php foreach ($listingCategories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="md-field">
                            <label class="md-label">Location</label>
                            <div class="md-location-picker" data-picker-id="listing-location">
                                <div class="md-location-input-wrapper">
                                    <input type="text"
                                           name="location"
                                           id="listing-location-input"
                                           class="md-location-input"
                                           placeholder="Search location or use GPS..."
                                           autocomplete="off">
                                    <i class="fa-solid fa-location-dot md-location-icon"></i>
                                    <div class="md-location-actions">
                                        <button type="button" class="md-location-btn" onclick="detectLocation('listing-location')" title="Use current location">
                                            <i class="fa-solid fa-crosshairs"></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="hidden" name="latitude" id="listing-location-lat">
                                <input type="hidden" name="longitude" id="listing-location-lng">
                                <div class="md-location-suggestions" id="listing-location-suggestions">
                                    <div class="md-location-current-header" onclick="detectLocation('listing-location')">
                                        <i class="fa-solid fa-crosshairs"></i>
                                        <span>Use current location</span>
                                    </div>
                                    <div class="md-location-results" id="listing-location-results"></div>
                                    <div class="md-location-remote-option" onclick="selectRemoteLocation('listing-location')">
                                        <i class="fa-solid fa-globe"></i>
                                        <span>Remote / Online / Flexible</span>
                                    </div>
                                </div>
                                <div class="md-location-selected" id="listing-location-selected">
                                    <div class="md-location-selected-icon">
                                        <i class="fa-solid fa-check"></i>
                                    </div>
                                    <span class="md-location-selected-text" id="listing-location-selected-text"></span>
                                    <button type="button" class="md-location-selected-remove" onclick="clearLocation('listing-location')">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="md-hint">Leave empty for flexible/remote availability</p>
                        </div>

                        <div class="md-field">
                            <label class="md-label">Add Photo (optional)</label>
                            <div class="md-image-upload" id="listing-image-upload">
                                <div class="md-image-placeholder" onclick="document.getElementById('listing-image-file').click()">
                                    <i class="fa-solid fa-camera"></i>
                                    <span>Tap to add photo</span>
                                </div>
                                <input type="file" name="image" id="listing-image-file" accept="image/*" style="display: none;" onchange="previewImage(this, 'listing')">
                                <div class="md-image-preview" id="listing-image-preview" style="display: none;">
                                    <img id="listing-preview-img" src="" alt="Preview" loading="lazy">
                                    <button type="button" class="md-image-remove" onclick="removeImage('listing')">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Service Details / Attributes - same as listings/create.php -->
                        <?php if (!empty($listingAttributes)): ?>
                        <div id="listing-attributes-container" class="md-field">
                            <label class="md-label">Service Details <span class="md-hint-inline">(Optional)</span></label>
                            <div class="md-attributes-grid">
                                <?php foreach ($listingAttributes as $attr): ?>
                                <label class="md-attribute-item attribute-item"
                                       data-category-id="<?= $attr['category_id'] ?? 'global' ?>"
                                       data-target-type="<?= $attr['target_type'] ?? 'any' ?>">
                                    <?php if (($attr['input_type'] ?? 'checkbox') === 'checkbox'): ?>
                                    <input type="checkbox" name="attributes[<?= $attr['id'] ?>]" value="1">
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($attr['name']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <button type="button" class="md-next-btn" onclick="nextListingStep(3)">
                            Next: Impact
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>

                    <!-- Step 3: Social Impact (Optional) -->
                    <div class="md-step" id="listing-step-3">
                        <div class="md-step-progress">
                            <div class="md-step-dot"></div>
                            <div class="md-step-dot"></div>
                            <div class="md-step-dot active"></div>
                        </div>

                        <button type="button" class="md-back-btn" onclick="prevListingStep(2)">
                            <i class="fa-solid fa-arrow-left"></i>
                            Back
                        </button>

                        <!-- SDGs - Same structure as listings/create.php -->
                        <details class="holo-sdg-accordion" open>
                            <summary class="holo-sdg-header">
                                <span>🌍 Social Impact <span style="font-weight: 400; opacity: 0.6; font-size: 0.85rem;">(Optional)</span></span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </summary>
                            <div class="holo-sdg-content">
                                <p class="holo-sdg-intro">Which UN Sustainable Development Goals does this support?</p>
                                <?php
                                require_once __DIR__ . '/../../../src/Helpers/SDG.php';
                                $sdgs = \Nexus\Helpers\SDG::all();
                                ?>
                                <div class="holo-sdg-grid">
                                    <?php foreach ($sdgs as $sdgId => $goal): ?>
                                    <label class="holo-sdg-card" data-color="<?= $goal['color'] ?>">
                                        <input type="checkbox" name="sdg_goals[]" value="<?= $sdgId ?>" onchange="toggleSDG(this, '<?= $goal['color'] ?>')">
                                        <span class="sdg-icon"><?= $goal['icon'] ?></span>
                                        <span class="sdg-label"><?= $goal['label'] ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </details>

                        <div class="md-skip-section">
                            <button type="submit" class="md-submit-btn success">
                                <i class="fa-solid fa-check"></i>
                                <span id="listing-submit-text">Create Offer</span>
                            </button>
                            <p class="md-skip-hint">Social Impact is optional - skip if not applicable</p>
                        </div>
                    </div>
                </form>
            </div>

            <!-- ==================== EVENT PANEL ==================== -->
            <?php if ($hasEvents): ?>
            <div class="multidraw-panel <?= $defaultType === 'event' ? 'active' : '' ?>" id="panel-event" role="tabpanel">
                <form id="form-event" action="<?= $basePath ?>/compose" method="POST">
                    <input type="hidden" name="post_type" value="event">

                    <div class="md-field">
                        <label class="md-label">Event Name *</label>
                        <input type="text" name="title" class="md-input" placeholder="What's your event called?" required>
                    </div>

                    <div class="md-field">
                        <label class="md-label">Description</label>
                        <!-- AI Assist Toggle -->
                        <label class="md-ai-toggle">
                            <input type="checkbox" id="ai-assist-event" onchange="toggleAiAssist('event', this.checked)">
                            <span class="md-ai-toggle-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                            <span class="md-ai-toggle-text"><strong>Need help?</strong> Let AI write your description</span>
                        </label>
                        <!-- AI Generate Button -->
                        <div class="md-ai-generate-wrapper" id="ai-wrapper-event">
                            <button type="button" class="md-ai-generate-btn" onclick="generateAiContent('event')">
                                <span class="ai-btn-content">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                    Generate Description
                                </span>
                                <span class="ai-btn-loading">
                                    <i class="fa-solid fa-spinner md-ai-spinner"></i>
                                    Generating...
                                </span>
                            </button>
                            <div class="md-ai-status" id="ai-status-event"></div>
                        </div>
                        <textarea name="description" id="event-desc" class="md-input" style="min-height: 100px; resize: vertical;" placeholder="Tell people what this event is about..."></textarea>
                    </div>

                    <div class="md-field">
                        <label class="md-label">Location</label>
                        <div class="md-location-picker" data-picker-id="event-location">
                            <div class="md-location-input-wrapper">
                                <input type="text"
                                       name="location"
                                       id="event-location-input"
                                       class="md-location-input"
                                       placeholder="Venue or address..."
                                       autocomplete="off">
                                <i class="fa-solid fa-location-dot md-location-icon"></i>
                                <div class="md-location-actions">
                                    <button type="button" class="md-location-btn" onclick="detectLocation('event-location')" title="Use current location">
                                        <i class="fa-solid fa-crosshairs"></i>
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="latitude" id="event-location-lat">
                            <input type="hidden" name="longitude" id="event-location-lng">
                            <div class="md-location-suggestions" id="event-location-suggestions">
                                <div class="md-location-current-header" onclick="detectLocation('event-location')">
                                    <i class="fa-solid fa-crosshairs"></i>
                                    <span>Use current location</span>
                                </div>
                                <div class="md-location-results" id="event-location-results"></div>
                                <div class="md-location-remote-option" onclick="selectRemoteLocation('event-location')">
                                    <i class="fa-solid fa-video"></i>
                                    <span>Online / Virtual Event</span>
                                </div>
                            </div>
                            <div class="md-location-selected" id="event-location-selected">
                                <div class="md-location-selected-icon">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                                <span class="md-location-selected-text" id="event-location-selected-text"></span>
                                <button type="button" class="md-location-selected-remove" onclick="clearLocation('event-location')">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="md-grid-2">
                        <div class="md-field">
                            <label class="md-label">Start Date *</label>
                            <input type="date" name="start_date" class="md-input" required>
                        </div>
                        <div class="md-field">
                            <label class="md-label">Start Time</label>
                            <input type="time" name="start_time" class="md-input" value="09:00">
                        </div>
                    </div>

                    <div class="md-grid-2">
                        <div class="md-field">
                            <label class="md-label">End Date</label>
                            <input type="date" name="end_date" class="md-input">
                        </div>
                        <div class="md-field">
                            <label class="md-label">End Time</label>
                            <input type="time" name="end_time" class="md-input" value="17:00">
                        </div>
                    </div>

                    <div class="md-field">
                        <label class="md-label">Category</label>
                        <select name="category_id" class="md-select">
                            <option value="">Select a category (optional)</option>
                            <?php foreach ($eventCategories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (!empty($userGroups)): ?>
                    <div class="md-field">
                        <label class="md-label">Host as Hub <span class="md-hint-inline">(Optional)</span></label>
                        <select name="group_id" class="md-select">
                            <option value="">Personal Event</option>
                            <?php foreach ($userGroups as $grp): ?>
                            <option value="<?= $grp['id'] ?>"><?= htmlspecialchars($grp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="md-hint">Select a Hub to list this event on its page.</p>
                    </div>
                    <?php endif; ?>

                    <!-- SDGs - Same as events/create.php -->
                    <details class="holo-sdg-accordion">
                        <summary class="holo-sdg-header">
                            <span>🌍 Social Impact <span style="font-weight: 400; opacity: 0.6; font-size: 0.85rem;">(Optional)</span></span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </summary>
                        <div class="holo-sdg-content">
                            <p class="holo-sdg-intro">Which UN Sustainable Development Goals does this event support?</p>
                            <div class="holo-sdg-grid">
                                <?php foreach ($sdgs as $sdgId => $goal): ?>
                                <label class="holo-sdg-card" data-color="<?= $goal['color'] ?>">
                                    <input type="checkbox" name="sdg_goals[]" value="<?= $sdgId ?>" onchange="toggleSDG(this, '<?= $goal['color'] ?>')">
                                    <span class="sdg-icon"><?= $goal['icon'] ?></span>
                                    <span class="sdg-label"><?= $goal['label'] ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </details>

                    <button type="submit" class="md-submit-btn" style="background: linear-gradient(135deg, var(--color-pink-500) 0%, var(--color-pink-700) 100%);">
                        <i class="fa-solid fa-calendar-plus"></i>
                        Create Event
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- ==================== POLL PANEL ==================== -->
            <?php if ($hasPolls): ?>
            <div class="multidraw-panel <?= $defaultType === 'poll' ? 'active' : '' ?>" id="panel-poll" role="tabpanel">
                <form id="form-poll" action="<?= $basePath ?>/compose" method="POST">
                    <input type="hidden" name="post_type" value="poll">

                    <div class="md-field">
                        <label class="md-label">Poll Question *</label>
                        <input type="text" name="question" class="md-input" placeholder="What do you want to ask?" required>
                    </div>

                    <div class="md-field">
                        <label class="md-label">Description (optional)</label>
                        <!-- AI Assist Toggle -->
                        <label class="md-ai-toggle">
                            <input type="checkbox" id="ai-assist-poll" onchange="toggleAiAssist('poll', this.checked)">
                            <span class="md-ai-toggle-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                            <span class="md-ai-toggle-text"><strong>Need help?</strong> Let AI add context to your poll</span>
                        </label>
                        <!-- AI Generate Button -->
                        <div class="md-ai-generate-wrapper" id="ai-wrapper-poll">
                            <button type="button" class="md-ai-generate-btn" onclick="generateAiContent('poll')">
                                <span class="ai-btn-content">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                    Generate Context
                                </span>
                                <span class="ai-btn-loading">
                                    <i class="fa-solid fa-spinner md-ai-spinner"></i>
                                    Generating...
                                </span>
                            </button>
                            <div class="md-ai-status" id="ai-status-poll"></div>
                        </div>
                        <textarea name="description" id="poll-desc" class="md-input" style="min-height: 60px; resize: vertical;" placeholder="Add more context to your poll..."></textarea>
                    </div>

                    <div class="md-field">
                        <label class="md-label">Options *</label>
                        <div class="md-poll-options" id="poll-options">
                            <div class="md-poll-option">
                                <input type="text" name="options[]" class="md-input" placeholder="Option 1" required>
                            </div>
                            <div class="md-poll-option">
                                <input type="text" name="options[]" class="md-input" placeholder="Option 2" required>
                            </div>
                        </div>
                        <button type="button" class="md-add-option" onclick="addPollOption()">
                            <i class="fa-solid fa-plus"></i> Add Option
                        </button>
                    </div>

                    <div class="md-field">
                        <label class="md-label">End Date (optional)</label>
                        <input type="date" name="end_date" class="md-input">
                        <p class="md-hint">When should voting close? Leave empty for no deadline.</p>
                    </div>

                    <button type="submit" class="md-submit-btn">
                        <i class="fa-solid fa-chart-bar"></i>
                        Create Poll
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- ==================== GOAL PANEL ==================== -->
            <?php if ($hasGoals): ?>
            <div class="multidraw-panel <?= $defaultType === 'goal' ? 'active' : '' ?>" id="panel-goal" role="tabpanel">
                <form id="form-goal" action="<?= $basePath ?>/compose" method="POST">
                    <input type="hidden" name="post_type" value="goal">

                    <div class="md-field">
                        <label class="md-label">Goal Title *</label>
                        <input type="text" name="title" class="md-input" placeholder="What do you want to achieve?" required>
                    </div>

                    <div class="md-field">
                        <label class="md-label">Description</label>
                        <!-- AI Assist Toggle -->
                        <label class="md-ai-toggle">
                            <input type="checkbox" id="ai-assist-goal" onchange="toggleAiAssist('goal', this.checked)">
                            <span class="md-ai-toggle-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                            <span class="md-ai-toggle-text"><strong>Need help?</strong> Let AI write your goal description</span>
                        </label>
                        <!-- AI Generate Button -->
                        <div class="md-ai-generate-wrapper" id="ai-wrapper-goal">
                            <button type="button" class="md-ai-generate-btn" onclick="generateAiContent('goal')">
                                <span class="ai-btn-content">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                    Generate Description
                                </span>
                                <span class="ai-btn-loading">
                                    <i class="fa-solid fa-spinner md-ai-spinner"></i>
                                    Generating...
                                </span>
                            </button>
                            <div class="md-ai-status" id="ai-status-goal"></div>
                        </div>
                        <textarea name="description" id="goal-desc" class="md-input" style="min-height: 100px; resize: vertical;" placeholder="Why is this goal important? How will the community help?"></textarea>
                    </div>

                    <div class="md-field">
                        <label class="md-label">Target Date</label>
                        <input type="date" name="deadline" class="md-input">
                        <p class="md-hint">When do you want to achieve this by?</p>
                    </div>

                    <div class="md-field">
                        <label class="md-checkbox-label">
                            <input type="checkbox" name="is_public" value="1" checked>
                            <span>Make this goal public</span>
                        </label>
                        <p class="md-hint">Public goals can receive support from the community.</p>
                    </div>

                    <button type="submit" class="md-submit-btn">
                        <i class="fa-solid fa-bullseye"></i>
                        Create Goal
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- ==================== VOLUNTEERING PANEL ==================== -->
            <?php if ($hasVolunteering): ?>
            <div class="multidraw-panel <?= $defaultType === 'volunteering' ? 'active' : '' ?>" id="panel-volunteering" role="tabpanel">
                <?php if (!$isVolunteerHost): ?>
                    <!-- Scenario A: User is NOT a registered host -->
                    <div class="md-vol-card">
                        <div class="md-vol-icon">
                            <i class="fa-solid fa-building-ngo"></i>
                        </div>
                        <h3 class="md-vol-title">Register as a Host</h3>
                        <p class="md-vol-desc">
                            To post volunteer opportunities, you need to register your organization first.
                            This helps volunteers trust the opportunities they apply for.
                        </p>
                        <a href="<?= $basePath ?>/volunteering/dashboard" class="md-submit-btn warning" style="text-decoration: none; display: inline-flex;">
                            <i class="fa-solid fa-plus"></i>
                            Register Organization
                        </a>
                    </div>

                <?php elseif ($isOrganizationProfile): ?>
                    <!-- Scenario C: User is an Organization Profile -->
                    <div class="md-alert info">
                        <i class="fa-solid fa-info-circle"></i>
                        You're signed in as an organization. Manage your opportunities from the dashboard.
                    </div>
                    <a href="<?= $basePath ?>/volunteering/opp/create" class="md-submit-btn warning" style="text-decoration: none; display: inline-flex; margin-bottom: 12px;">
                        <i class="fa-solid fa-plus"></i>
                        Post New Opportunity
                    </a>
                    <a href="<?= $basePath ?>/volunteering/dashboard" class="md-next-btn" style="text-decoration: none; display: inline-flex;">
                        <i class="fa-solid fa-tachometer-alt"></i>
                        Go to Dashboard
                    </a>

                <?php else: ?>
                    <!-- Scenario B: User IS a host - Show Post Opportunity Form -->
                    <form id="form-volunteering" action="<?= $basePath ?>/volunteering/opp/store" method="POST">
                        <?= \Nexus\Core\Csrf::input() ?>

                        <?php if (count($myOrgs) > 1): ?>
                        <div class="md-field">
                            <label class="md-label">Organization</label>
                            <select name="org_id" class="md-select" required>
                                <?php foreach ($myOrgs as $org): ?>
                                <option value="<?= $org['id'] ?>" <?= ($org['status'] ?? '') !== 'approved' ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($org['name']) ?>
                                    <?= ($org['status'] ?? '') !== 'approved' ? ' (Pending Approval)' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="org_id" value="<?= $myOrgs[0]['id'] ?? '' ?>">
                        <?php if (($myOrgs[0]['status'] ?? '') !== 'approved'): ?>
                        <div class="md-alert info">
                            <i class="fa-solid fa-clock"></i>
                            Your organization "<?= htmlspecialchars($myOrgs[0]['name'] ?? '') ?>" is pending approval. You can still create opportunities, but they won't be visible until approved.
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <div class="md-field">
                            <label class="md-label">Opportunity Title *</label>
                            <input type="text" name="title" class="md-input" placeholder="e.g., Weekend Gardener, Youth Mentor..." required>
                        </div>

                        <div class="md-field">
                            <label class="md-label">Category</label>
                            <select name="category_id" class="md-select">
                                <option value="">Select Category...</option>
                                <?php foreach ($volCategories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="md-field">
                            <label class="md-label">Description *</label>
                            <!-- AI Assist Toggle -->
                            <label class="md-ai-toggle">
                                <input type="checkbox" id="ai-assist-volunteering" onchange="toggleAiAssist('volunteering', this.checked)">
                                <span class="md-ai-toggle-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                                <span class="md-ai-toggle-text"><strong>Need help?</strong> Let AI write your description</span>
                            </label>
                            <!-- AI Generate Button (hidden until toggle is checked) -->
                            <div class="md-ai-generate-wrapper" id="ai-wrapper-volunteering">
                                <button type="button" class="md-ai-generate-btn" onclick="generateAiContent('volunteering')">
                                    <span class="ai-btn-content">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                                        Generate Description
                                    </span>
                                    <span class="ai-btn-loading">
                                        <i class="fa-solid fa-spinner md-ai-spinner"></i>
                                        Generating...
                                    </span>
                                </button>
                                <div class="md-ai-status" id="ai-status-volunteering"></div>
                            </div>
                            <textarea name="description" id="vol-desc" class="md-input" style="min-height: 100px; resize: vertical;" placeholder="Describe the role, responsibilities, and impact..." required></textarea>
                        </div>

                        <div class="md-field">
                            <label class="md-label">Location</label>
                            <div class="md-location-picker" data-picker-id="vol-location">
                                <div class="md-location-input-wrapper">
                                    <input type="text"
                                           name="location"
                                           id="vol-location-input"
                                           class="md-location-input"
                                           placeholder="Address or 'Remote'..."
                                           autocomplete="off">
                                    <i class="fa-solid fa-location-dot md-location-icon"></i>
                                    <div class="md-location-actions">
                                        <button type="button" class="md-location-btn" onclick="detectLocation('vol-location')" title="Use current location">
                                            <i class="fa-solid fa-crosshairs"></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="hidden" name="latitude" id="vol-location-lat">
                                <input type="hidden" name="longitude" id="vol-location-lng">
                                <div class="md-location-suggestions" id="vol-location-suggestions">
                                    <div class="md-location-current-header" onclick="detectLocation('vol-location')">
                                        <i class="fa-solid fa-crosshairs"></i>
                                        <span>Use current location</span>
                                    </div>
                                    <div class="md-location-results" id="vol-location-results"></div>
                                    <div class="md-location-remote-option" onclick="selectRemoteLocation('vol-location')">
                                        <i class="fa-solid fa-globe"></i>
                                        <span>Remote / Virtual Opportunity</span>
                                    </div>
                                </div>
                                <div class="md-location-selected" id="vol-location-selected">
                                    <div class="md-location-selected-icon">
                                        <i class="fa-solid fa-check"></i>
                                    </div>
                                    <span class="md-location-selected-text" id="vol-location-selected-text"></span>
                                    <button type="button" class="md-location-selected-remove" onclick="clearLocation('vol-location')">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="md-field">
                            <label class="md-label">Skills Needed</label>
                            <input type="text" name="skills" class="md-input" placeholder="e.g., Gardening, Teaching, patience...">
                            <p class="md-hint">Comma-separated list of skills</p>
                        </div>

                        <div class="md-grid-2">
                            <div class="md-field">
                                <label class="md-label">Start Date</label>
                                <input type="date" name="start_date" class="md-input">
                            </div>
                            <div class="md-field">
                                <label class="md-label">End Date</label>
                                <input type="date" name="end_date" class="md-input">
                            </div>
                        </div>

                        <button type="submit" class="md-submit-btn warning">
                            <i class="fa-solid fa-hands-helping"></i>
                            Post Opportunity
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ==================== GROUP POST PANEL ==================== -->
            <?php if (!empty($userGroups)): ?>
            <div class="multidraw-panel <?= $defaultType === 'group' ? 'active' : '' ?>" id="panel-group" role="tabpanel">
                <form id="form-group" action="<?= $basePath ?>/compose" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="post_type" value="post">
                    <input type="hidden" name="group_id" id="selected-group-id" value="<?= $preselectedGroup ? (int)$preselectedGroup['id'] : '' ?>">

                    <!-- Smart Group Search -->
                    <div class="md-field">
                        <label class="md-label">Select Group</label>
                        <div class="md-group-search-container">
                            <i class="fa-solid fa-search md-group-search-icon"></i>
                            <input type="text" class="md-group-search" id="group-search" placeholder="Search your groups..." autocomplete="off">
                            <div class="md-group-results" id="group-results">
                                <?php foreach ($userGroups as $group): ?>
                                <div class="md-group-item" data-id="<?= $group['id'] ?>" data-name="<?= htmlspecialchars($group['name']) ?>">
                                    <div class="md-group-avatar">
                                        <?php if (!empty($group['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($group['image_url']) ?>" loading="lazy" alt="">
                                        <?php else: ?>
                                        <?= strtoupper(substr($group['name'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <span class="md-group-name"><?= htmlspecialchars($group['name']) ?></span>
                                    <span class="md-group-members"><?= $group['member_count'] ?? 0 ?> members</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Group Display -->
                    <div class="md-selected-group" id="selected-group-display" style="display: <?= $preselectedGroup ? 'flex' : 'none' ?>;">
                        <?php if ($preselectedGroup && !empty($preselectedGroup['image_url'])): ?>
                        <img src="<?= htmlspecialchars($preselectedGroup['image_url']) ?>" loading="lazy" class="md-group-avatar" id="selected-group-avatar" style="font-size: 0;">
                        <?php else: ?>
                        <div class="md-group-avatar" id="selected-group-avatar"><?= $preselectedGroup ? strtoupper(substr($preselectedGroup['name'], 0, 1)) : 'G' ?></div>
                        <?php endif; ?>
                        <div class="md-selected-group-info">
                            <div class="md-selected-group-label">Posting to</div>
                            <div class="md-selected-group-name" id="selected-group-name"><?= $preselectedGroup ? htmlspecialchars($preselectedGroup['name']) : 'Group Name' ?></div>
                        </div>
                        <button type="button" class="md-selected-group-remove" onclick="clearGroupSelection()">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <!-- Post Content -->
                    <div class="md-user-row">
                        <img src="<?= htmlspecialchars($userAvatar) ?>" loading="lazy" alt="" class="md-avatar">
                        <div class="md-user-info">
                            <div class="md-user-name"><?= htmlspecialchars($userName) ?></div>
                        </div>
                    </div>

                    <textarea name="content" class="md-textarea" placeholder="Share something with the group..." id="group-post-content"></textarea>

                    <!-- Image Upload for Group Posts -->
                    <div class="md-field">
                        <div class="md-image-upload" id="group-image-upload">
                            <div class="md-image-placeholder" onclick="document.getElementById('group-image-file').click()">
                                <i class="fa-solid fa-camera"></i>
                                <span>Add photo (optional)</span>
                            </div>
                            <input type="file" name="image" id="group-image-file" accept="image/*" style="display: none;" onchange="previewImage(this, 'group')">
                            <div class="md-image-preview" id="group-image-preview" style="display: none;">
                                <img id="group-preview-img" src="" alt="Preview" loading="lazy">
                                <button type="button" class="md-image-remove" onclick="removeImage('group')">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="md-submit-btn" id="group-submit-btn" <?= $preselectedGroup ? '' : 'disabled' ?>>
                        <i class="fa-solid fa-paper-plane"></i>
                        Post to Group
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    </div><!-- Close multidraw-backdrop -->

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';

        // ============================================
        // ELEMENTS
        // ============================================
        const overlay = document.getElementById('multidrawOverlay');
        const backdrop = document.getElementById('multidrawBackdrop');
        const closeBtn = document.getElementById('closeBtn');
        const headerSubmitBtn = document.getElementById('headerSubmitBtn');
        const pillNav = document.getElementById('pillNav');
        const pills = document.querySelectorAll('.multidraw-pill');
        const panels = document.querySelectorAll('.multidraw-panel');

        let currentType = '<?= $defaultType ?>';

        // Debug: Log found elements
        console.log('Multi-Draw Init:', { pills: pills.length, panels: panels.length, currentType });

        // ============================================
        // CLOSE BUTTON EVENT LISTENERS
        // ============================================
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeMultidrawForm();
            });
        }

        // Backdrop click to close (desktop)
        if (backdrop) {
            backdrop.addEventListener('click', function(e) {
                if (e.target === backdrop) {
                    closeMultidrawForm();
                }
            });
        }

        // Escape key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMultidrawForm();
            }
        });

        // ============================================
        // PILL NAVIGATION (onclick handlers are on the buttons themselves)
        // ============================================
        console.log('Multi-Draw: Found', pills.length, 'pills and', panels.length, 'panels');

        // Initialize header submit button visibility
        if (currentType === 'post' && headerSubmitBtn) {
            headerSubmitBtn.classList.add('visible');
            headerSubmitBtn.textContent = 'Post';
            headerSubmitBtn.setAttribute('form', 'form-post');
        }

        // ============================================
        // LISTING ATTRIBUTES FILTER (Service Details)
        // Same as listings/create.php - filter by category and type
        // ============================================
        var listingCategorySelect = document.querySelector('#form-listing select[name="category_id"]');

        function filterListingAttributes() {
            var container = document.getElementById('listing-attributes-container');
            if (!container) return;

            var selectedCat = listingCategorySelect ? listingCategorySelect.value : '';
            var listingTypeInput = document.getElementById('listing-type-input');
            var selectedType = listingTypeInput ? listingTypeInput.value : 'offer';
            var items = container.querySelectorAll('.attribute-item');
            var visibleCount = 0;

            items.forEach(function(item) {
                var itemCat = item.getAttribute('data-category-id');
                var itemType = item.getAttribute('data-target-type');

                var catMatch = itemCat === 'global' || itemCat == selectedCat;
                var typeMatch = itemType === 'any' || itemType === selectedType;

                if (catMatch && typeMatch) {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                    var checkbox = item.querySelector('input');
                    if (checkbox) checkbox.checked = false;
                }
            });

            container.style.display = visibleCount > 0 ? 'block' : 'none';
        }

        // Bind to category change
        if (listingCategorySelect) {
            listingCategorySelect.addEventListener('change', filterListingAttributes);
        }

        // Also filter when listing type changes - extend selectListingType
        var originalSelectListingType = window.selectListingType;
        window.selectListingType = function(type) {
            if (originalSelectListingType) {
                originalSelectListingType(type);
            }
            filterListingAttributes();
        };

        // Initial filter on load
        filterListingAttributes();

        // ============================================
        // GROUP SEARCH (Smart Dropdown)
        // ============================================
        const groupSearch = document.getElementById('group-search');
        const groupResults = document.getElementById('group-results');
        const groupItems = document.querySelectorAll('.md-group-item');
        const selectedGroupDisplay = document.getElementById('selected-group-display');
        const selectedGroupId = document.getElementById('selected-group-id');
        const groupSubmitBtn = document.getElementById('group-submit-btn');

        if (groupSearch) {
            groupSearch.addEventListener('focus', function() {
                groupResults.classList.add('visible');
            });

            groupSearch.addEventListener('blur', function() {
                // Delay to allow click on item
                setTimeout(() => {
                    groupResults.classList.remove('visible');
                }, 200);
            });

            groupSearch.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                let hasResults = false;

                groupItems.forEach(item => {
                    const name = item.dataset.name.toLowerCase();
                    if (name.includes(query)) {
                        item.style.display = 'flex';
                        hasResults = true;
                    } else {
                        item.style.display = 'none';
                    }
                });

                if (hasResults) {
                    groupResults.classList.add('visible');
                }
            });

            groupItems.forEach(item => {
                item.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const name = this.dataset.name;

                    selectedGroupId.value = id;
                    document.getElementById('selected-group-name').textContent = name;
                    document.getElementById('selected-group-avatar').textContent = name.charAt(0).toUpperCase();

                    selectedGroupDisplay.style.display = 'flex';
                    groupSearch.value = '';
                    groupResults.classList.remove('visible');
                    groupSubmitBtn.disabled = false;

                    haptic();
                });
            });
        }

        // Add haptics to all buttons (haptic function defined at top)
        document.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!this.classList.contains('multidraw-pill')) {
                    haptic();
                }
            });
        });

        // ============================================
        // PREVENT BODY SCROLL ON MOBILE
        // ============================================
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
        document.body.style.height = '100%';

        // ============================================
        // MAPBOX LOCATION PICKER
        // Native mobile-friendly with GPS & search
        // ============================================
        const MAPBOX_TOKEN = '<?= htmlspecialchars($mapboxToken ?? '') ?>';
        const isMobile = () => window.innerWidth <= 768;
        let activeLocationPicker = null;
        let searchDebounceTimer = null;
        let mobileBackdrop = null;

        // Create mobile backdrop if needed
        function ensureMobileBackdrop() {
            if (!mobileBackdrop) {
                mobileBackdrop = document.createElement('div');
                mobileBackdrop.className = 'md-location-backdrop';
                mobileBackdrop.addEventListener('click', closeAllLocationDropdowns);
                document.body.appendChild(mobileBackdrop);
            }
            return mobileBackdrop;
        }

        // Initialize location pickers
        function initLocationPickers() {
            if (!MAPBOX_TOKEN) {
                console.warn('Mapbox token not configured - location search disabled');
                return;
            }

            if (typeof mapboxgl !== 'undefined') {
                mapboxgl.accessToken = MAPBOX_TOKEN;
            }

            // Set up each location picker
            document.querySelectorAll('.md-location-picker').forEach(picker => {
                const pickerId = picker.dataset.pickerId;
                const input = document.getElementById(pickerId + '-input');

                if (!input) return;

                // Focus handler - show dropdown
                input.addEventListener('focus', () => {
                    openLocationDropdown(pickerId);
                });

                // Input handler - search as you type
                input.addEventListener('input', (e) => {
                    const query = e.target.value.trim();
                    if (query.length >= 2) {
                        clearTimeout(searchDebounceTimer);
                        searchDebounceTimer = setTimeout(() => {
                            searchLocations(pickerId, query);
                        }, 300);
                    } else {
                        clearLocationResults(pickerId);
                    }
                });

                // Blur handler (delayed for click handling)
                input.addEventListener('blur', () => {
                    setTimeout(() => {
                        if (activeLocationPicker === pickerId) {
                            closeAllLocationDropdowns();
                        }
                    }, 200);
                });
            });
        }

        // Open location dropdown
        function openLocationDropdown(pickerId) {
            activeLocationPicker = pickerId;
            const suggestions = document.getElementById(pickerId + '-suggestions');
            if (suggestions) {
                suggestions.classList.add('visible');
                if (isMobile()) {
                    ensureMobileBackdrop().classList.add('visible');
                }
            }
        }

        // Close all dropdowns
        function closeAllLocationDropdowns() {
            activeLocationPicker = null;
            document.querySelectorAll('.md-location-suggestions').forEach(el => {
                el.classList.remove('visible');
            });
            if (mobileBackdrop) {
                mobileBackdrop.classList.remove('visible');
            }
        }

        // Search locations using Mapbox Geocoding API
        async function searchLocations(pickerId, query) {
            if (!MAPBOX_TOKEN) return;

            const resultsContainer = document.getElementById(pickerId + '-results');
            if (!resultsContainer) return;

            try {
                // Show loading state
                resultsContainer.innerHTML = `
                    <div class="md-location-suggestion" style="opacity: 0.6;">
                        <div class="md-location-suggestion-icon">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                        </div>
                        <div class="md-location-suggestion-text">
                            <div class="md-location-suggestion-main">Searching...</div>
                        </div>
                    </div>
                `;

                const response = await fetch(
                    `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json?` +
                    `access_token=${MAPBOX_TOKEN}&` +
                    `types=place,locality,neighborhood,address,poi&` +
                    `limit=5&` +
                    `language=en`
                );

                if (!response.ok) throw new Error('Search failed');

                const data = await response.json();

                if (data.features && data.features.length > 0) {
                    resultsContainer.innerHTML = data.features.map(feature => `
                        <div class="md-location-suggestion"
                             onclick="selectLocationResult('${pickerId}', '${escapeHtml(feature.place_name)}', ${feature.center[1]}, ${feature.center[0]})">
                            <div class="md-location-suggestion-icon">
                                <i class="fa-solid fa-location-dot"></i>
                            </div>
                            <div class="md-location-suggestion-text">
                                <div class="md-location-suggestion-main">${escapeHtml(feature.text)}</div>
                                <div class="md-location-suggestion-sub">${escapeHtml(feature.place_name)}</div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    resultsContainer.innerHTML = `
                        <div class="md-location-suggestion" style="opacity: 0.6;">
                            <div class="md-location-suggestion-icon">
                                <i class="fa-solid fa-circle-question"></i>
                            </div>
                            <div class="md-location-suggestion-text">
                                <div class="md-location-suggestion-main">No results found</div>
                                <div class="md-location-suggestion-sub">Try a different search term</div>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Location search error:', error);
                resultsContainer.innerHTML = `
                    <div class="md-location-suggestion" style="opacity: 0.6;">
                        <div class="md-location-suggestion-icon">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                        </div>
                        <div class="md-location-suggestion-text">
                            <div class="md-location-suggestion-main">Search unavailable</div>
                            <div class="md-location-suggestion-sub">Please type your location manually</div>
                        </div>
                    </div>
                `;
            }
        }

        // Clear location results
        function clearLocationResults(pickerId) {
            const resultsContainer = document.getElementById(pickerId + '-results');
            if (resultsContainer) {
                resultsContainer.innerHTML = '';
            }
        }

        // Select a location from search results
        window.selectLocationResult = function(pickerId, placeName, lat, lng) {
            const input = document.getElementById(pickerId + '-input');
            const latInput = document.getElementById(pickerId + '-lat');
            const lngInput = document.getElementById(pickerId + '-lng');
            const selected = document.getElementById(pickerId + '-selected');
            const selectedText = document.getElementById(pickerId + '-selected-text');

            if (input) input.value = placeName;
            if (latInput) latInput.value = lat;
            if (lngInput) lngInput.value = lng;

            // Show selected state
            if (selected && selectedText) {
                selectedText.textContent = placeName;
                selected.classList.add('visible');
            }

            closeAllLocationDropdowns();
            haptic();
        };

        // Detect current location using GPS
        window.detectLocation = function(pickerId) {
            const btn = document.querySelector(`[data-picker-id="${pickerId}"] .md-location-btn`);
            const input = document.getElementById(pickerId + '-input');

            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser');
                return;
            }

            // Show detecting state
            if (btn) {
                btn.classList.add('detecting');
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            }

            haptic();

            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    const { latitude, longitude } = position.coords;

                    // Reverse geocode to get place name
                    try {
                        const response = await fetch(
                            `https://api.mapbox.com/geocoding/v5/mapbox.places/${longitude},${latitude}.json?` +
                            `access_token=${MAPBOX_TOKEN}&` +
                            `types=place,locality,neighborhood&` +
                            `limit=1`
                        );

                        if (response.ok) {
                            const data = await response.json();
                            if (data.features && data.features.length > 0) {
                                const placeName = data.features[0].place_name;
                                selectLocationResult(pickerId, placeName, latitude, longitude);

                                // Success feedback
                                if (btn) {
                                    btn.classList.remove('detecting');
                                    btn.classList.add('success');
                                    btn.innerHTML = '<i class="fa-solid fa-check"></i>';
                                    setTimeout(() => {
                                        btn.classList.remove('success');
                                        btn.innerHTML = '<i class="fa-solid fa-crosshairs"></i>';
                                    }, 2000);
                                }

                                // Strong haptic for success
                                if (navigator.vibrate) navigator.vibrate([50, 30, 50]);
                                return;
                            }
                        }

                        // Fallback to coordinates
                        selectLocationResult(pickerId, `${latitude.toFixed(4)}, ${longitude.toFixed(4)}`, latitude, longitude);
                    } catch (error) {
                        console.error('Reverse geocoding error:', error);
                        selectLocationResult(pickerId, `${latitude.toFixed(4)}, ${longitude.toFixed(4)}`, latitude, longitude);
                    }

                    if (btn) {
                        btn.classList.remove('detecting');
                        btn.innerHTML = '<i class="fa-solid fa-crosshairs"></i>';
                    }
                },
                (error) => {
                    console.error('Geolocation error:', error);
                    if (btn) {
                        btn.classList.remove('detecting');
                        btn.innerHTML = '<i class="fa-solid fa-crosshairs"></i>';
                    }

                    let message = 'Unable to detect location';
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            message = 'Location access denied. Please enable location in your browser/device settings.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = 'Location information unavailable';
                            break;
                        case error.TIMEOUT:
                            message = 'Location request timed out';
                            break;
                    }

                    // Show error in suggestions
                    const resultsContainer = document.getElementById(pickerId + '-results');
                    if (resultsContainer) {
                        resultsContainer.innerHTML = `
                            <div class="md-location-suggestion" style="color: var(--md-danger);">
                                <div class="md-location-suggestion-icon" style="background: var(--md-danger-light); color: var(--md-danger);">
                                    <i class="fa-solid fa-location-crosshairs"></i>
                                </div>
                                <div class="md-location-suggestion-text">
                                    <div class="md-location-suggestion-main">Location Error</div>
                                    <div class="md-location-suggestion-sub">${message}</div>
                                </div>
                            </div>
                        `;
                    }
                    openLocationDropdown(pickerId);

                    if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 60000
                }
            );
        };

        // Select remote/online location
        window.selectRemoteLocation = function(pickerId) {
            const input = document.getElementById(pickerId + '-input');
            const latInput = document.getElementById(pickerId + '-lat');
            const lngInput = document.getElementById(pickerId + '-lng');
            const selected = document.getElementById(pickerId + '-selected');
            const selectedText = document.getElementById(pickerId + '-selected-text');

            if (input) input.value = 'Remote';
            if (latInput) latInput.value = '';
            if (lngInput) lngInput.value = '';

            if (selected && selectedText) {
                selectedText.textContent = 'Remote / Online';
                selected.classList.add('visible');
                selected.querySelector('.md-location-selected-icon').innerHTML = '<i class="fa-solid fa-globe"></i>';
            }

            closeAllLocationDropdowns();
            haptic();
        };

        // Clear location selection
        window.clearLocation = function(pickerId) {
            const input = document.getElementById(pickerId + '-input');
            const latInput = document.getElementById(pickerId + '-lat');
            const lngInput = document.getElementById(pickerId + '-lng');
            const selected = document.getElementById(pickerId + '-selected');

            if (input) {
                input.value = '';
                input.focus();
            }
            if (latInput) latInput.value = '';
            if (lngInput) lngInput.value = '';
            if (selected) {
                selected.classList.remove('visible');
                selected.querySelector('.md-location-selected-icon').innerHTML = '<i class="fa-solid fa-check"></i>';
            }

            haptic();
        };

        // Escape HTML for security
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Initialize location pickers when DOM is ready
        initLocationPickers();

        // ============================================
        // AI CONTENT GENERATION (Opt-in)
        // ============================================

        // Description field ID mapping
        const aiDescriptionFields = {
            'listing': 'listing-desc',
            'event': 'event-desc',
            'poll': 'poll-desc',
            'goal': 'goal-desc',
            'volunteering': 'vol-desc'
        };

        // Title field ID mapping (for context)
        const aiTitleFields = {
            'listing': 'listing-title',
            'event': 'event-title',
            'poll': null, // Poll uses question instead
            'goal': 'goal-title',
            'volunteering': null // Title is just name="title" in volunteering form
        };

        // Generate AI content
        window.generateAiContent = async function(type) {
            const descFieldId = aiDescriptionFields[type];
            const descField = document.getElementById(descFieldId);
            const wrapper = document.getElementById('ai-wrapper-' + type);
            const statusEl = document.getElementById('ai-status-' + type);
            const btn = wrapper?.querySelector('.md-ai-generate-btn');

            if (!descField) {
                console.error('Description field not found for type:', type);
                return;
            }

            const userPrompt = descField.value.trim();

            // Check if user has typed something as context
            if (!userPrompt || userPrompt.length < 10) {
                // Show error status
                if (statusEl) {
                    statusEl.textContent = 'Please write a brief description first (at least 10 characters) so AI knows what to expand on.';
                    statusEl.classList.add('visible', 'error');
                    setTimeout(() => {
                        statusEl.classList.remove('visible', 'error');
                    }, 4000);
                }
                descField.focus();
                descField.classList.add('shake');
                setTimeout(() => descField.classList.remove('shake'), 400);
                haptic();
                return;
            }

            // Get title for additional context
            let titleContext = '';
            const titleFieldId = aiTitleFields[type];
            if (titleFieldId) {
                const titleField = document.getElementById(titleFieldId);
                if (titleField && titleField.value.trim()) {
                    titleContext = titleField.value.trim();
                }
            } else if (type === 'volunteering') {
                // Volunteering uses name="title" without ID
                const titleField = document.querySelector('#form-volunteering input[name="title"]');
                if (titleField && titleField.value.trim()) {
                    titleContext = titleField.value.trim();
                }
            } else if (type === 'poll') {
                // Poll uses question as context
                const questionField = document.querySelector('#form-poll input[name="question"]');
                if (questionField && questionField.value.trim()) {
                    titleContext = questionField.value.trim();
                }
            }

            // Show loading state
            if (btn) {
                btn.classList.add('loading');
                btn.disabled = true;
            }
            if (statusEl) {
                statusEl.textContent = 'Generating your description...';
                statusEl.classList.add('visible');
                statusEl.classList.remove('error');
            }

            try {
                // Build the correct API endpoint URL based on type
                var apiType = type;
                // Map types to API endpoints (event, listing, etc.)
                if (type === 'volunteering') {
                    apiType = 'listing'; // Use listing generator for volunteering
                } else if (type === 'poll' || type === 'goal') {
                    apiType = 'listing'; // Fall back to listing for types without dedicated endpoint
                }

                const response = await fetch('<?= $basePath ?>/api/ai/generate/' + apiType, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        title: titleContext,
                        context: {
                            user_prompt: userPrompt,
                            type: type
                        }
                    })
                });

                if (!response.ok) {
                    throw new Error('Generation failed');
                }

                const data = await response.json();

                if (data.success && data.content) {
                    // Replace description with generated content
                    descField.value = data.content;

                    // Success feedback
                    if (statusEl) {
                        statusEl.textContent = 'Description generated! Feel free to edit it.';
                        statusEl.classList.add('visible');
                        setTimeout(() => {
                            statusEl.classList.remove('visible');
                        }, 3000);
                    }

                    // Success haptic
                    if (navigator.vibrate) navigator.vibrate([50, 30, 50]);
                } else {
                    throw new Error(data.error || 'Generation failed');
                }
            } catch (error) {
                console.error('AI generation error:', error);
                if (statusEl) {
                    statusEl.textContent = 'Unable to generate content. Please try again or write manually.';
                    statusEl.classList.add('visible', 'error');
                    setTimeout(() => {
                        statusEl.classList.remove('visible', 'error');
                    }, 4000);
                }

                // Error haptic
                if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
            } finally {
                // Reset button state
                if (btn) {
                    btn.classList.remove('loading');
                    btn.disabled = false;
                }
            }
        };

    });
    </script>
</body>
</html>

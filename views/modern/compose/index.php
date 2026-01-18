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

// Federation settings for listings
$federationEnabled = false;
$userFederationOptedIn = false;
if (class_exists('\Nexus\Services\FederationFeatureService')) {
    try {
        $federationEnabled = \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
        if ($federationEnabled && $userId) {
            $userFedSettings = \Nexus\Core\Database::query(
                "SELECT federation_optin FROM federation_user_settings WHERE user_id = ?",
                [$userId]
            )->fetch();
            $userFederationOptedIn = $userFedSettings && $userFedSettings['federation_optin'];
        }
    } catch (\Exception $e) {
        $federationEnabled = false;
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

    <!-- Compose Multidraw CSS -->
    <link rel="stylesheet" href="/assets/css/compose-multidraw.min.css">
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
                        <?= webp_avatar($userAvatar, $userName, 44) ?>
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

                        <!-- Partner Timebanks (Federation) -->
                        <?php if ($federationEnabled): ?>
                        <div class="md-field md-federation-section">
                            <label class="md-label">
                                <i class="fa-solid fa-globe" style="margin-right: 6px; color: #8b5cf6;"></i>
                                Share with Partner Timebanks
                                <span class="md-hint-inline">(Optional)</span>
                            </label>

                            <?php if ($userFederationOptedIn): ?>
                            <p class="md-hint" style="margin-bottom: 10px;">Make this listing visible to members of our partner timebanks.</p>
                            <div class="md-federation-options">
                                <label class="md-federation-option">
                                    <input type="radio" name="federated_visibility" value="none" checked>
                                    <span class="md-fed-option-content">
                                        <span class="md-fed-option-title"><i class="fa-solid fa-lock"></i> Local Only</span>
                                        <span class="md-fed-option-desc">Only visible to this timebank</span>
                                    </span>
                                </label>
                                <label class="md-federation-option">
                                    <input type="radio" name="federated_visibility" value="listed">
                                    <span class="md-fed-option-content">
                                        <span class="md-fed-option-title"><i class="fa-solid fa-eye"></i> Visible</span>
                                        <span class="md-fed-option-desc">Partner members can see this</span>
                                    </span>
                                </label>
                                <label class="md-federation-option">
                                    <input type="radio" name="federated_visibility" value="bookable">
                                    <span class="md-fed-option-content">
                                        <span class="md-fed-option-title"><i class="fa-solid fa-handshake"></i> Bookable</span>
                                        <span class="md-fed-option-desc">Partners can contact you</span>
                                    </span>
                                </label>
                            </div>
                            <?php else: ?>
                            <div class="md-federation-notice">
                                <i class="fa-solid fa-info-circle"></i>
                                <div>
                                    <strong>Enable federation to share listings</strong>
                                    <p>Opt into federation in your <a href="<?= $basePath ?>/settings?section=federation">account settings</a> to share with partner timebanks.</p>
                                </div>
                            </div>
                            <input type="hidden" name="federated_visibility" value="none">
                            <?php endif; ?>
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

                    <!-- Partner Timebanks (Federation) -->
                    <?php if ($federationEnabled): ?>
                    <div class="md-federation-section">
                        <label class="md-label">
                            <i class="fa-solid fa-globe" style="margin-right: 8px; color: #8b5cf6;"></i>
                            Share with Partner Timebanks
                            <span class="md-hint-inline">(Optional)</span>
                        </label>

                        <?php if ($userFederationOptedIn): ?>
                        <p class="md-hint" style="margin-bottom: 12px;">Make this event visible to members of our partner timebanks.</p>
                        <div class="md-federation-options">
                            <label class="md-radio-card">
                                <input type="radio" name="federated_visibility" value="none" checked>
                                <span class="md-radio-content">
                                    <span class="md-radio-label">Local Only</span>
                                    <span class="md-radio-desc">Only visible to members of this timebank</span>
                                </span>
                            </label>
                            <label class="md-radio-card">
                                <input type="radio" name="federated_visibility" value="listed">
                                <span class="md-radio-content">
                                    <span class="md-radio-label">Visible</span>
                                    <span class="md-radio-desc">Partner timebank members can see this event</span>
                                </span>
                            </label>
                            <label class="md-radio-card">
                                <input type="radio" name="federated_visibility" value="joinable">
                                <span class="md-radio-content">
                                    <span class="md-radio-label">Joinable</span>
                                    <span class="md-radio-desc">Partner members can RSVP to this event</span>
                                </span>
                            </label>
                        </div>
                        <?php else: ?>
                        <div class="md-federation-optin-notice">
                            <i class="fa-solid fa-info-circle"></i>
                            <div>
                                <strong>Enable federation to share events</strong>
                                <p>To share your events with partner timebanks, you need to opt into federation in your <a href="<?= $basePath ?>/settings?section=federation">account settings</a>.</p>
                            </div>
                        </div>
                        <input type="hidden" name="federated_visibility" value="none">
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="md-submit-btn" style="background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);">
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
                        <?= webp_image($preselectedGroup['image_url'], $preselectedGroup['name'], 'md-group-avatar', ['id' => 'selected-group-avatar']) ?>
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
                        <?= webp_avatar($userAvatar, $userName, 44) ?>
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

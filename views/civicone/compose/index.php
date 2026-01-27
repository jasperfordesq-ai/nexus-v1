<?php
/**
 * Multi-Tab Creation Form - GOV.UK Design System
 * WCAG 2.1 AA Compliant Form Interface
 *
 * Tabs: Post, Listings, Events, Polls, Goals, Volunteering, Group Post
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

// Get user's groups
$userGroups = [];
if ($userId && class_exists('\Nexus\Models\Group')) {
    try {
        $userGroups = \Nexus\Models\Group::getUserGroups($userId);
    } catch (\Exception $e) {
        $userGroups = [];
    }
}

// Pre-selected group from URL
$preselectedGroupId = isset($_GET['group']) ? (int)$_GET['group'] : null;
$preselectedGroup = null;
if ($preselectedGroupId && !empty($userGroups)) {
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

// Get listing attributes
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

// Volunteering organizations
$myOrgs = [];
$isVolunteerHost = false;
$hasApprovedOrg = false;
if ($hasVolunteering && $userId && class_exists('\Nexus\Models\VolOrganization')) {
    try {
        $myOrgs = \Nexus\Models\VolOrganization::findByOwner($userId);
        $isVolunteerHost = !empty($myOrgs);
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

// Volunteering categories
$volCategories = [];
if ($hasVolunteering && class_exists('\Nexus\Models\Category')) {
    try {
        $volCategories = \Nexus\Models\Category::getByType('vol_opportunity');
    } catch (\Exception $e) {
        $volCategories = [];
    }
}

// Default tab
$defaultType = $_GET['type'] ?? $_GET['tab'] ?? 'post';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

if ($preselectedGroup) {
    $defaultType = 'group';
} elseif (empty($_GET['type']) && empty($_GET['tab']) && !empty($referer)) {
    if (strpos($referer, '/listings') !== false) $defaultType = 'listing';
    elseif (strpos($referer, '/events') !== false && $hasEvents) $defaultType = 'event';
    elseif (strpos($referer, '/polls') !== false && $hasPolls) $defaultType = 'poll';
    elseif (strpos($referer, '/goals') !== false && $hasGoals) $defaultType = 'goal';
    elseif (strpos($referer, '/volunteering') !== false && $hasVolunteering) $defaultType = 'volunteering';
    elseif (strpos($referer, '/groups') !== false) $defaultType = 'group';
}

$pageTitle = 'Create';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/govuk-frontend-5.14.0/govuk-frontend.min.css">
    <!-- Compose Modal CSS (extracted Phase 5 CSS Refactoring 2026-01-25) -->
    <link rel="stylesheet" href="/assets/css/civicone-compose.css">
</head>
<body class="compose-page-body">
    <div class="compose-container" role="dialog" aria-modal="true" aria-labelledby="compose-title">
        <!-- Header -->
        <header class="compose-header">
            <button type="button" class="close-btn" onclick="closeCompose()" aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <h1 id="compose-title">Create</h1>
        </header>

        <!-- Tab Navigation -->
        <nav class="tab-nav" role="tablist" aria-label="Content type">
            <button type="button" class="tab-btn <?= $defaultType === 'post' ? 'active' : '' ?>" data-tab="post" role="tab" aria-selected="<?= $defaultType === 'post' ? 'true' : 'false' ?>" onclick="switchTab('post')">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                Post
            </button>
            <button type="button" class="tab-btn <?= $defaultType === 'listing' ? 'active' : '' ?>" data-tab="listing" role="tab" aria-selected="<?= $defaultType === 'listing' ? 'true' : 'false' ?>" onclick="switchTab('listing')">
                <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>
                Listing
            </button>
            <?php if ($hasEvents): ?>
            <button type="button" class="tab-btn <?= $defaultType === 'event' ? 'active' : '' ?>" data-tab="event" role="tab" aria-selected="<?= $defaultType === 'event' ? 'true' : 'false' ?>" onclick="switchTab('event')">
                <i class="fa-solid fa-calendar" aria-hidden="true"></i>
                Event
            </button>
            <?php endif; ?>
            <?php if ($hasPolls): ?>
            <button type="button" class="tab-btn <?= $defaultType === 'poll' ? 'active' : '' ?>" data-tab="poll" role="tab" aria-selected="<?= $defaultType === 'poll' ? 'true' : 'false' ?>" onclick="switchTab('poll')">
                <i class="fa-solid fa-chart-bar" aria-hidden="true"></i>
                Poll
            </button>
            <?php endif; ?>
            <?php if ($hasGoals): ?>
            <button type="button" class="tab-btn <?= $defaultType === 'goal' ? 'active' : '' ?>" data-tab="goal" role="tab" aria-selected="<?= $defaultType === 'goal' ? 'true' : 'false' ?>" onclick="switchTab('goal')">
                <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
                Goal
            </button>
            <?php endif; ?>
            <?php if ($hasVolunteering): ?>
            <button type="button" class="tab-btn <?= $defaultType === 'volunteering' ? 'active' : '' ?>" data-tab="volunteering" role="tab" aria-selected="<?= $defaultType === 'volunteering' ? 'true' : 'false' ?>" onclick="switchTab('volunteering')">
                <i class="fa-solid fa-hands-helping" aria-hidden="true"></i>
                Volunteer
            </button>
            <?php endif; ?>
            <?php if (!empty($userGroups)): ?>
            <button type="button" class="tab-btn <?= $defaultType === 'group' ? 'active' : '' ?>" data-tab="group" role="tab" aria-selected="<?= $defaultType === 'group' ? 'true' : 'false' ?>" onclick="switchTab('group')">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                Group
            </button>
            <?php endif; ?>
        </nav>

        <!-- Error/Success Messages -->
        <?php if ($error): ?>
        <div class="govuk-error-summary govuk-!-margin-4" role="alert" aria-labelledby="error-summary-title" tabindex="-1">
            <h2 class="govuk-error-summary__title" id="error-summary-title">There is a problem</h2>
            <div class="govuk-error-summary__body">
                <p class="govuk-body"><?= htmlspecialchars($error) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="govuk-notification-banner govuk-notification-banner--success govuk-!-margin-4" role="alert">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title">Success</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading"><?= htmlspecialchars($success) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- POST Panel -->
        <div class="tab-panel <?= $defaultType === 'post' ? 'active' : '' ?>" id="panel-post" role="tabpanel">
            <form id="form-post" action="<?= $basePath ?>/compose" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="post_type" value="post">

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="post-content">What's on your mind?</label>
                    <textarea name="content" id="post-content" class="govuk-textarea" rows="5" placeholder="Share something with your community..." required></textarea>
                </div>

                <?php if (!empty($userGroups)): ?>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="post-group">Post to</label>
                    <select name="group_id" id="post-group" class="govuk-select">
                        <option value="">Public Feed</option>
                        <?php foreach ($userGroups as $group): ?>
                        <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-paper-plane govuk-!-margin-right-2" aria-hidden="true"></i>
                    Post
                </button>
            </form>
        </div>

        <!-- LISTING Panel -->
        <div class="tab-panel <?= $defaultType === 'listing' ? 'active' : '' ?>" id="panel-listing" role="tabpanel">
            <form id="form-listing" action="<?= $basePath ?>/compose" method="POST" enctype="multipart/form-data">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="post_type" value="listing">
                <input type="hidden" name="listing_type" id="listing-type-input" value="offer">

                <div class="type-toggle">
                    <button type="button" class="type-btn offer active" onclick="selectListingType('offer')">
                        <i class="fa-solid fa-gift" aria-hidden="true"></i>
                        Offer
                    </button>
                    <button type="button" class="type-btn request" onclick="selectListingType('request')">
                        <i class="fa-solid fa-hand" aria-hidden="true"></i>
                        Request
                    </button>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="listing-title">
                        What are you <span id="listing-type-text">offering</span>?
                    </label>
                    <input type="text" name="title" id="listing-title" class="govuk-input" placeholder="e.g., Guitar lessons, Help with moving..." required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="listing-desc">Description</label>
                    <textarea name="description" id="listing-desc" class="govuk-textarea" rows="4" placeholder="Describe what you're offering or need help with..." required></textarea>
                </div>

                <?php if (!empty($listingCategories)): ?>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="listing-category">Category</label>
                    <select name="category_id" id="listing-category" class="govuk-select">
                        <option value="">Select a category</option>
                        <?php foreach ($listingCategories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="listing-location">Location (optional)</label>
                    <input type="text" name="location" id="listing-location" class="govuk-input" placeholder="City or area">
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label">Image (optional)</label>
                    <input type="file" name="image" accept="image/*" class="govuk-file-upload">
                </div>

                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                    Create Listing
                </button>
            </form>
        </div>

        <?php if ($hasEvents): ?>
        <!-- EVENT Panel -->
        <div class="tab-panel <?= $defaultType === 'event' ? 'active' : '' ?>" id="panel-event" role="tabpanel">
            <form id="form-event" action="<?= $basePath ?>/compose" method="POST" enctype="multipart/form-data">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="post_type" value="event">

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="event-title">Event Title</label>
                    <input type="text" name="title" id="event-title" class="govuk-input" placeholder="What's the event called?" required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="event-desc">Description</label>
                    <textarea name="description" id="event-desc" class="govuk-textarea" rows="4" placeholder="Tell people what to expect..." required></textarea>
                </div>

                <div class="govuk-grid-row">
                    <div class="govuk-grid-column-one-half">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="event-date">Date</label>
                            <input type="date" name="date" id="event-date" class="govuk-input" required>
                        </div>
                    </div>
                    <div class="govuk-grid-column-one-half">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="event-time">Time</label>
                            <input type="time" name="time" id="event-time" class="govuk-input" required>
                        </div>
                    </div>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="event-location">Location</label>
                    <input type="text" name="location" id="event-location" class="govuk-input" placeholder="Where is it happening?">
                </div>

                <?php if (!empty($eventCategories)): ?>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="event-category">Category</label>
                    <select name="category_id" id="event-category" class="govuk-select">
                        <option value="">Select a category</option>
                        <?php foreach ($eventCategories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="govuk-form-group">
                    <label class="govuk-label">Cover Image (optional)</label>
                    <input type="file" name="image" accept="image/*" class="govuk-file-upload">
                </div>

                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-calendar-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                    Create Event
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($hasPolls): ?>
        <!-- POLL Panel -->
        <div class="tab-panel <?= $defaultType === 'poll' ? 'active' : '' ?>" id="panel-poll" role="tabpanel">
            <form id="form-poll" action="<?= $basePath ?>/compose" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="post_type" value="poll">

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="poll-question">Poll Question</label>
                    <input type="text" name="question" id="poll-question" class="govuk-input" placeholder="What do you want to ask?" required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m">Options</label>
                    <div id="poll-options">
                        <div class="poll-option">
                            <input type="text" name="options[]" class="govuk-input" placeholder="Option 1" required>
                        </div>
                        <div class="poll-option">
                            <input type="text" name="options[]" class="govuk-input" placeholder="Option 2" required>
                        </div>
                    </div>
                    <button type="button" class="govuk-button govuk-button--secondary govuk-!-margin-top-2" onclick="addPollOption()">
                        <i class="fa-solid fa-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                        Add Option
                    </button>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="poll-duration">Duration (days)</label>
                    <select name="duration" id="poll-duration" class="govuk-select">
                        <option value="1">1 day</option>
                        <option value="3">3 days</option>
                        <option value="7" selected>1 week</option>
                        <option value="14">2 weeks</option>
                    </select>
                </div>

                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-chart-bar govuk-!-margin-right-2" aria-hidden="true"></i>
                    Create Poll
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($hasGoals): ?>
        <!-- GOAL Panel -->
        <div class="tab-panel <?= $defaultType === 'goal' ? 'active' : '' ?>" id="panel-goal" role="tabpanel">
            <form id="form-goal" action="<?= $basePath ?>/compose" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="post_type" value="goal">

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="goal-title">Goal Title</label>
                    <input type="text" name="title" id="goal-title" class="govuk-input" placeholder="What do you want to achieve?" required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="goal-desc">Description</label>
                    <textarea name="description" id="goal-desc" class="govuk-textarea" rows="4" placeholder="Describe your goal and why it matters..."></textarea>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="goal-target">Target Date</label>
                    <input type="date" name="target_date" id="goal-target" class="govuk-input">
                </div>

                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-bullseye govuk-!-margin-right-2" aria-hidden="true"></i>
                    Create Goal
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($hasVolunteering): ?>
        <!-- VOLUNTEERING Panel -->
        <div class="tab-panel <?= $defaultType === 'volunteering' ? 'active' : '' ?>" id="panel-volunteering" role="tabpanel">
            <?php if ($hasApprovedOrg): ?>
            <form id="form-volunteering" action="<?= $basePath ?>/compose" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="post_type" value="volunteering">

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="vol-org">Organization</label>
                    <select name="organization_id" id="vol-org" class="govuk-select" required>
                        <option value="">Select an organization</option>
                        <?php foreach ($myOrgs as $org): ?>
                            <?php if (($org['status'] ?? '') === 'approved'): ?>
                            <option value="<?= $org['id'] ?>"><?= htmlspecialchars($org['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="vol-title">Opportunity Title</label>
                    <input type="text" name="title" id="vol-title" class="govuk-input" placeholder="e.g., Community Clean-up Day" required>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="vol-desc">Description</label>
                    <textarea name="description" id="vol-desc" class="govuk-textarea" rows="4" placeholder="What will volunteers be doing?" required></textarea>
                </div>

                <?php if (!empty($volCategories)): ?>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="vol-category">Category</label>
                    <select name="category_id" id="vol-category" class="govuk-select">
                        <option value="">Select a category</option>
                        <?php foreach ($volCategories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="govuk-form-group">
                    <label class="govuk-label" for="vol-location">Location</label>
                    <input type="text" name="location" id="vol-location" class="govuk-input" placeholder="Where will this take place?">
                </div>

                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-hands-helping govuk-!-margin-right-2" aria-hidden="true"></i>
                    Create Opportunity
                </button>
            </form>
            <?php else: ?>
            <div class="govuk-!-padding-6 govuk-!-text-align-center civicone-panel-bg civicone-panel-border-blue">
                <p class="govuk-body govuk-!-margin-bottom-4">
                    <i class="fa-solid fa-building fa-3x civicone-icon-blue" aria-hidden="true"></i>
                </p>
                <h3 class="govuk-heading-m">Register Your Organization</h3>
                <p class="govuk-body">To post volunteer opportunities, you need to register and get your organization approved.</p>
                <a href="<?= $basePath ?>/volunteering/register" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-plus govuk-!-margin-right-2" aria-hidden="true"></i>
                    Register Organization
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($userGroups)): ?>
        <!-- GROUP Panel -->
        <div class="tab-panel <?= $defaultType === 'group' ? 'active' : '' ?>" id="panel-group" role="tabpanel">
            <form id="form-group" action="<?= $basePath ?>/compose" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="post_type" value="group_post">

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="group-select">Select Group</label>
                    <select name="group_id" id="group-select" class="govuk-select" required>
                        <option value="">Choose a group</option>
                        <?php foreach ($userGroups as $group): ?>
                        <option value="<?= $group['id'] ?>" <?= $preselectedGroupId == $group['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($group['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="group-content">Your Message</label>
                    <textarea name="content" id="group-content" class="govuk-textarea" rows="5" placeholder="Share something with this group..." required></textarea>
                </div>

                <button type="submit" class="govuk-button" data-module="govuk-button">
                    <i class="fa-solid fa-paper-plane govuk-!-margin-right-2" aria-hidden="true"></i>
                    Post to Group
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function closeCompose() {
            window.history.back();
        }

        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                const isActive = btn.dataset.tab === tab;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.toggle('active', panel.id === 'panel-' + tab);
            });
        }

        function selectListingType(type) {
            document.getElementById('listing-type-input').value = type;
            document.getElementById('listing-type-text').textContent = type === 'offer' ? 'offering' : 'requesting';
            document.querySelectorAll('.type-btn').forEach(btn => {
                btn.classList.toggle('active', btn.classList.contains(type));
            });
        }

        let pollOptionCount = 2;
        function addPollOption() {
            pollOptionCount++;
            const container = document.getElementById('poll-options');
            const div = document.createElement('div');
            div.className = 'poll-option';
            div.innerHTML = `
                <input type="text" name="options[]" class="govuk-input" placeholder="Option ${pollOptionCount}" required>
                <button type="button" onclick="this.parentElement.remove()">
                    <i class="fa-solid fa-times" aria-hidden="true"></i>
                </button>
            `;
            container.appendChild(div);
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeCompose();
        });

        document.body.addEventListener('click', function(e) {
            if (e.target === document.body) closeCompose();
        });
    </script>
</body>
</html>

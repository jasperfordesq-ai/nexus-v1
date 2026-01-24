<?php

/**
 * Component Library Preview Page
 *
 * Visual preview of all available components with sample data.
 * Access via: /component-preview (add route) or include directly for testing.
 */

// Include component helpers
require_once __DIR__ . '/_init.php';

// Sample data for previews
$sampleUser = [
    'id' => 1,
    'name' => 'Jane Smith',
    'avatar' => '',
    'bio' => 'Community organizer passionate about bringing people together.',
    'location' => 'Dublin, Ireland',
    'skills' => ['Gardening', 'Teaching', 'Cooking'],
    'connection_status' => 'none',
];

$sampleListing = [
    'id' => 1,
    'title' => 'Guitar Lessons for Beginners',
    'description' => 'Learn to play guitar from scratch. I have 10 years of experience teaching music.',
    'type' => 'offer',
    'category_name' => 'Education',
    'price' => 2,
    'image' => '',
    'featured' => true,
    'user' => $sampleUser,
];

$sampleEvent = [
    'id' => 1,
    'title' => 'Community Garden Workshop',
    'description' => 'Join us for a hands-on workshop about sustainable gardening.',
    'start_date' => date('Y-m-d H:i:s', strtotime('+3 days')),
    'location' => 'Phoenix Park, Dublin',
    'image' => '',
    'attendee_count' => 12,
    'max_attendees' => 30,
    'attendees' => [
        ['name' => 'John', 'avatar' => ''],
        ['name' => 'Mary', 'avatar' => ''],
        ['name' => 'Tom', 'avatar' => ''],
    ],
];

$sampleBadge = [
    'id' => 1,
    'name' => 'Early Adopter',
    'description' => 'Joined during the beta period',
    'icon' => 'rocket',
    'rarity' => 'rare',
    'earned' => true,
    'earned_at' => '2024-01-15',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Component Library Preview</title>
    <link rel="stylesheet" href="/assets/css/design-tokens.css">
    <link rel="stylesheet" href="/assets/css/modern/main.css">
    <link rel="stylesheet" href="/assets/css/modern/components-library.css">
    <link rel="stylesheet" href="/assets/css/modern/preview.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="preview-page">
    <div class="preview-container">
        <header class="preview-header">
            <h1>🧱 Component Library</h1>
            <p>Modern Theme - 49 Reusable Components</p>
            <div class="preview-header__counts">
                <span class="component-count">Layout: 5</span>
                <span class="component-count">Navigation: 6</span>
                <span class="component-count">Cards: 10</span>
                <span class="component-count">Forms: 8</span>
                <span class="component-count">Buttons: 4</span>
                <span class="component-count">Feedback: 6</span>
                <span class="component-count">Media: 5</span>
                <span class="component-count">Data: 5</span>
            </div>
        </header>

        <!-- LAYOUT COMPONENTS -->
        <section class="preview-section">
            <h2><i class="fa-solid fa-layer-group"></i> Layout Components</h2>

            <h3>Hero</h3>
            <?php
            $title = 'Welcome to the Community';
            $subtitle = 'Connect with neighbors, share skills, and build a stronger community together.';
            $icon = 'users';
            $badge = ['icon' => 'sparkles', 'text' => 'New Features'];
            $buttons = [
                ['label' => 'Get Started', 'href' => '#', 'variant' => 'primary', 'icon' => 'arrow-right'],
                ['label' => 'Learn More', 'href' => '#', 'variant' => 'outline'],
            ];
            include __DIR__ . '/layout/hero.php';
            ?>

            <h3>Section</h3>
            <?php
            $title = 'Recent Activity';
            $icon = 'clock';
            $subtitle = 'What\'s happening in your community';
            $actions = [['label' => 'View All', 'href' => '#', 'icon' => 'arrow-right']];
            $content = '<p class="preview-section__placeholder">Section content goes here...</p>';
            include __DIR__ . '/layout/section.php';
            ?>
        </section>

        <!-- NAVIGATION COMPONENTS -->
        <section class="preview-section">
            <h2><i class="fa-solid fa-compass"></i> Navigation Components</h2>

            <h3>Breadcrumb</h3>
            <?php
            $items = [
                ['label' => 'Home', 'href' => '#'],
                ['label' => 'Listings', 'href' => '#'],
                ['label' => 'Guitar Lessons', 'href' => ''],
            ];
            include __DIR__ . '/navigation/breadcrumb.php';
            ?>

            <h3>Tabs</h3>
            <?php
            $tabs = [
                ['id' => 'all', 'label' => 'All', 'icon' => 'list'],
                ['id' => 'offers', 'label' => 'Offers', 'count' => 24],
                ['id' => 'requests', 'label' => 'Requests', 'count' => 12],
            ];
            $activeTab = 'all';
            include __DIR__ . '/navigation/tabs.php';
            ?>

            <h3>Pills</h3>
            <?php
            $items = [
                ['id' => 'all', 'label' => 'All Categories'],
                ['id' => 'education', 'label' => 'Education', 'icon' => 'graduation-cap'],
                ['id' => 'home', 'label' => 'Home & Garden', 'icon' => 'home'],
                ['id' => 'tech', 'label' => 'Technology', 'icon' => 'laptop'],
            ];
            $active = 'education';
            include __DIR__ . '/navigation/pills.php';
            ?>

            <h3>Pagination</h3>
            <?php
            $currentPage = 3;
            $totalPages = 10;
            $baseUrl = '#';
            include __DIR__ . '/navigation/pagination.php';
            ?>

            <h3>Filter Bar</h3>
            <?php
            $filters = [
                ['id' => 'all', 'label' => 'All', 'count' => 156],
                ['id' => 'unread', 'label' => 'Unread', 'icon' => 'envelope', 'count' => 3],
                ['id' => 'starred', 'label' => 'Starred', 'icon' => 'star'],
            ];
            $active = 'all';
            $showSearch = true;
            include __DIR__ . '/navigation/filter-bar.php';
            ?>
        </section>

        <!-- CARD COMPONENTS -->
        <section class="preview-section">
            <h2><i class="fa-solid fa-square"></i> Card Components</h2>

            <div class="preview-grid">
                <div>
                    <h3>Listing Card</h3>
                    <?php
                    $listing = $sampleListing;
                    include __DIR__ . '/cards/listing-card.php';
                    ?>
                </div>

                <div>
                    <h3>Member Card</h3>
                    <?php
                    $user = $sampleUser;
                    include __DIR__ . '/cards/member-card.php';
                    ?>
                </div>

                <div>
                    <h3>Event Card</h3>
                    <?php
                    $event = $sampleEvent;
                    include __DIR__ . '/cards/event-card.php';
                    ?>
                </div>
            </div>

            <div class="preview-grid">
                <div>
                    <h3>Stat Card</h3>
                    <?php
                    $label = 'Time Credits';
                    $value = '142';
                    $icon = 'clock';
                    $trend = 'up';
                    $trendValue = '+12%';
                    include __DIR__ . '/cards/stat-card.php';
                    ?>
                </div>

                <div>
                    <h3>Achievement Card</h3>
                    <?php
                    $badge = $sampleBadge;
                    include __DIR__ . '/cards/achievement-card.php';
                    ?>
                </div>
            </div>
        </section>

        <!-- FORM COMPONENTS -->
        <section class="preview-section">
            <h2><i class="fa-solid fa-pen-to-square"></i> Form Components</h2>

            <h3>Search Card</h3>
            <?php
            $title = 'Find Listings';
            $count = 156;
            $countLabel = 'listings available';
            $action = '#';
            $placeholder = 'Search for skills, services...';
            $filters = [
                ['name' => 'category', 'label' => 'Category', 'options' => ['education' => 'Education', 'home' => 'Home & Garden']],
                ['name' => 'type', 'label' => 'Type', 'options' => ['offer' => 'Offers', 'request' => 'Requests']],
            ];
            include __DIR__ . '/forms/search-card.php';
            ?>

            <h3>Form Controls</h3>
            <div class="preview-row">
                <div class="preview-column">
                    <?php
                    $label = 'Email Address';
                    $name = 'email';
                    $error = '';
                    $required = true;
                    $help = 'We\'ll never share your email.';
                    ob_start();
                    $type = 'email';
                    $placeholder = 'you@example.com';
                    $icon = 'envelope';
                    include __DIR__ . '/forms/input.php';
                    $content = ob_get_clean();
                    include __DIR__ . '/forms/form-group.php';
                    ?>
                </div>
                <div class="preview-column">
                    <?php
                    $label = 'Category';
                    $name = 'category';
                    $error = 'Please select a category';
                    $required = true;
                    $help = '';
                    ob_start();
                    $options = ['education' => 'Education', 'home' => 'Home & Garden', 'tech' => 'Technology'];
                    $placeholder = 'Select category...';
                    $selected = '';
                    include __DIR__ . '/forms/select.php';
                    $content = ob_get_clean();
                    include __DIR__ . '/forms/form-group.php';
                    ?>
                </div>
            </div>

            <div class="preview-row">
                <?php
                $name = 'newsletter';
                $label = 'Subscribe to newsletter';
                $description = 'Get weekly updates about community events';
                $checked = true;
                include __DIR__ . '/forms/checkbox.php';
                ?>
            </div>
        </section>

        <!-- BUTTON COMPONENTS -->
        <section class="preview-section">
            <h2><i class="fa-solid fa-hand-pointer"></i> Button Components</h2>

            <h3>Button Variants</h3>
            <div class="preview-row">
                <?php foreach (['primary', 'secondary', 'outline', 'ghost', 'danger'] as $variant): ?>
                    <?php
                    $label = ucfirst($variant);
                    $icon = '';
                    include __DIR__ . '/buttons/button.php';
                    ?>
                <?php endforeach; ?>
            </div>

            <h3>Buttons with Icons</h3>
            <div class="preview-row">
                <?php
                $label = 'Create Post';
                $icon = 'plus';
                $variant = 'primary';
                include __DIR__ . '/buttons/button.php';

                $label = 'Download';
                $icon = 'download';
                $variant = 'outline';
                include __DIR__ . '/buttons/button.php';

                $label = 'Loading...';
                $loading = true;
                $variant = 'primary';
                $icon = '';
                include __DIR__ . '/buttons/button.php';
                $loading = false;
                ?>
            </div>

            <h3>Button Group</h3>
            <?php
            $buttons = [
                ['label' => 'Save Draft', 'variant' => 'outline', 'icon' => 'save'],
                ['label' => 'Preview', 'variant' => 'secondary', 'icon' => 'eye'],
                ['label' => 'Publish', 'variant' => 'primary', 'icon' => 'paper-plane'],
            ];
            include __DIR__ . '/buttons/button-group.php';
            ?>

            <h3>Icon Buttons</h3>
            <div class="preview-row">
                <?php
                foreach (['edit' => 'Edit', 'trash' => 'Delete', 'share' => 'Share', 'bookmark' => 'Save'] as $iconName => $labelText):
                    $icon = $iconName;
                    $label = $labelText;
                    $variant = 'default';
                    include __DIR__ . '/buttons/icon-button.php';
                endforeach;
                ?>
            </div>
        </section>

        <!-- FEEDBACK COMPONENTS -->
        <section class="preview-section">
            <h2><i class="fa-solid fa-message"></i> Feedback Components</h2>

            <h3>Alerts</h3>
            <div class="preview-column--alerts">
                <?php
                foreach (['info', 'success', 'warning', 'danger'] as $alertType):
                    $type = $alertType;
                    $message = 'This is a ' . $alertType . ' alert message.';
                    $title = '';
                    $dismissible = true;
                    include __DIR__ . '/feedback/alert.php';
                endforeach;
                ?>
            </div>

            <h3>Empty State</h3>
            <?php
            $icon = '🔍';
            $title = 'No results found';
            $message = 'Try adjusting your search or filters to find what you\'re looking for.';
            $action = ['label' => 'Clear Filters', 'href' => '#', 'icon' => 'times'];
            include __DIR__ . '/feedback/empty-state.php';
            ?>

            <h3>Loading Skeleton</h3>
            <?php
            $type = 'card';
            $count = 3;
            include __DIR__ . '/feedback/skeleton.php';
            ?>

            <h3>Loading Spinners</h3>
            <div class="preview-row">
                <?php
                $variant = 'spinner';
                $size = 'md';
                $message = 'Loading...';
                include __DIR__ . '/feedback/loading-spinner.php';

                $variant = 'dots';
                $message = '';
                include __DIR__ . '/feedback/loading-spinner.php';

                $variant = 'pulse';
                include __DIR__ . '/feedback/loading-spinner.php';
                ?>
            </div>
        </section>

        <!-- MEDIA COMPONENTS -->
        <section class="preview-section">
            <h2><i class="fa-solid fa-image"></i> Media Components</h2>

            <h3>Avatars</h3>
            <div class="preview-row">
                <?php
                foreach ([32, 40, 48, 64] as $avatarSize):
                    $name = 'Jane Smith';
                    $image = '';
                    $size = $avatarSize;
                    $showRing = ($avatarSize === 48);
                    $status = ($avatarSize === 64) ? 'online' : null;
                    include __DIR__ . '/media/avatar.php';
                endforeach;
                ?>
            </div>

            <h3>Avatar Stack</h3>
            <?php
            $users = [
                ['name' => 'Alice', 'image' => ''],
                ['name' => 'Bob', 'image' => ''],
                ['name' => 'Charlie', 'image' => ''],
                ['name' => 'Diana', 'image' => ''],
                ['name' => 'Eve', 'image' => ''],
            ];
            $max = 3;
            $size = 36;
            include __DIR__ . '/media/avatar-stack.php';
            ?>

            <h3>Badges</h3>
            <div class="preview-row">
                <?php
                foreach (['primary', 'success', 'warning', 'danger', 'info', 'muted'] as $badgeVariant):
                    $text = ucfirst($badgeVariant);
                    $variant = $badgeVariant;
                    $icon = '';
                    $pill = false;
                    include __DIR__ . '/media/badge.php';
                endforeach;
                ?>
            </div>
            <div class="preview-row">
                <?php
                $text = 'Featured';
                $variant = 'warning';
                $icon = 'star';
                $pill = true;
                include __DIR__ . '/media/badge.php';

                $text = 'New';
                $variant = 'success';
                $icon = 'sparkles';
                include __DIR__ . '/media/badge.php';

                $text = '12';
                $variant = 'danger';
                $icon = '';
                include __DIR__ . '/media/badge.php';
                ?>
            </div>

            <h3>Icons</h3>
            <div class="preview-row">
                <?php
                foreach (['home', 'user', 'heart', 'star', 'bell', 'cog'] as $iconName):
                    $name = $iconName;
                    $size = 'lg';
                    $color = 'primary';
                    include __DIR__ . '/media/icon.php';
                endforeach;
                ?>
            </div>
        </section>

        <!-- DATA COMPONENTS -->
        <section class="preview-section">
            <h2><i class="fa-solid fa-chart-bar"></i> Data Components</h2>

            <h3>Progress Bar</h3>
            <div class="preview-column--progress">
                <?php
                $percent = 65;
                $label = 'Profile Completion';
                $color = 'primary';
                include __DIR__ . '/data/progress-bar.php';

                $percent = 80;
                $label = 'XP to Next Level';
                $current = 800;
                $max = 1000;
                $color = 'success';
                include __DIR__ . '/data/progress-bar.php';

                $percent = 45;
                $label = 'Storage Used';
                $color = 'warning';
                $striped = true;
                $animated = true;
                include __DIR__ . '/data/progress-bar.php';
                $striped = false;
                $animated = false;
                ?>
            </div>

            <h3>Stats</h3>
            <div class="preview-row">
                <?php
                $value = '2,451';
                $label = 'Total Members';
                $icon = 'users';
                $trend = 'up';
                $trendValue = '+12%';
                include __DIR__ . '/data/stat.php';

                $value = '156';
                $label = 'Active Listings';
                $icon = 'list';
                $trend = null;
                include __DIR__ . '/data/stat.php';

                $value = '89';
                $label = 'Hours Exchanged';
                $icon = 'clock';
                $suffix = ' hrs';
                $trend = 'up';
                $trendValue = '+5';
                include __DIR__ . '/data/stat.php';
                $suffix = '';
                ?>
            </div>

            <h3>Leaderboard</h3>
            <?php
            $users = [
                ['id' => 1, 'name' => 'Alice Johnson', 'avatar' => '', 'value' => 1250],
                ['id' => 2, 'name' => 'Bob Smith', 'avatar' => '', 'value' => 980],
                ['id' => 3, 'name' => 'Charlie Brown', 'avatar' => '', 'value' => 875],
                ['id' => 4, 'name' => 'Diana Prince', 'avatar' => '', 'value' => 720],
                ['id' => 5, 'name' => 'Eve Wilson', 'avatar' => '', 'value' => 650],
            ];
            $metric = 'XP';
            $highlightUserId = 4;
            include __DIR__ . '/data/leaderboard.php';
            ?>

            <h3>Table</h3>
            <?php
            $headers = [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'role', 'label' => 'Role'],
                ['key' => 'status', 'label' => 'Status', 'align' => 'center'],
            ];
            $rows = [
                ['name' => 'John Doe', 'email' => 'john@example.com', 'role' => 'Admin', 'status' => 'Active'],
                ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'role' => 'Member', 'status' => 'Active'],
                ['name' => 'Bob Wilson', 'email' => 'bob@example.com', 'role' => 'Member', 'status' => 'Pending'],
            ];
            $variant = 'striped';
            include __DIR__ . '/data/table.php';
            ?>
        </section>

        <!-- TOAST CONTAINER -->
        <?php
        $position = 'top-right';
        include __DIR__ . '/feedback/toast.php';
        ?>

        <footer class="preview-footer">
            <p>Component Library for Project NEXUS Modern Theme</p>
            <p><strong>49 components</strong> across <strong>8 categories</strong></p>
            <button onclick="showToast('Toast notification works!', 'success')" class="nexus-smart-btn nexus-smart-btn-outline preview-footer__button">
                <i class="fa-solid fa-bell"></i> Test Toast
            </button>
        </footer>
    </div>
</body>
</html>

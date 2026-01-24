<?php
/**
 * Test Page: Modern Shared Components
 *
 * Preview page for shared components used across the modern theme.
 * Access via direct include or add a route.
 */

// Bootstrap the application for helpers
require_once __DIR__ . '/../../../../bootstrap.php';

// Include accessibility helpers
require_once __DIR__ . '/accessibility-helpers.php';

// Mock data for testing
$mockUser = [
    'id' => 1,
    'first_name' => 'Jane',
    'last_name' => 'Smith',
    'avatar_url' => '',
];

$mockPosts = [
    [
        'id' => 1,
        'user_id' => 1,
        'content' => 'Just finished a wonderful community garden workshop! Learning about sustainable composting techniques. 🌱',
        'image_url' => '',
        'likes_count' => 24,
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        'visibility' => 'public',
    ],
    [
        'id' => 2,
        'user_id' => 2,
        'content' => 'Looking for someone to help with guitar lessons for my daughter. Happy to exchange time credits! Anyone interested?',
        'image_url' => '',
        'likes_count' => 8,
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'visibility' => 'connections',
    ],
    [
        'id' => 3,
        'user_id' => 1,
        'content' => 'Private note: Remember to follow up with the community center about next month\'s event.',
        'image_url' => '',
        'likes_count' => 0,
        'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
        'visibility' => 'private',
    ],
];

$mockAuthors = [
    1 => ['id' => 1, 'first_name' => 'Jane', 'last_name' => 'Smith', 'avatar_url' => ''],
    2 => ['id' => 2, 'first_name' => 'Bob', 'last_name' => 'Johnson', 'avatar_url' => ''],
];

// Current user for testing
$currentUserId = 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Components Test - Modern Theme</title>
    <link rel="stylesheet" href="/assets/css/design-tokens.css">
    <link rel="stylesheet" href="/assets/css/modern/main.css">
    <link rel="stylesheet" href="/assets/css/modern/components-library.css">
    <link rel="stylesheet" href="/assets/css/post-card.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .test-page {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--color-background, #f5f5f5);
            min-height: 100vh;
            padding: var(--space-8, 32px);
        }

        .test-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .test-header {
            text-align: center;
            margin-bottom: var(--space-10, 40px);
        }

        .test-header h1 {
            font-size: 2rem;
            margin-bottom: var(--space-2, 8px);
            color: var(--color-text);
        }

        .test-header p {
            color: var(--color-text-muted);
            font-size: 1.1rem;
        }

        .test-section {
            background: var(--color-surface, #fff);
            border-radius: var(--radius-xl, 12px);
            padding: var(--space-6, 24px);
            margin-bottom: var(--space-6, 24px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .test-section h2 {
            font-size: 1.25rem;
            margin-bottom: var(--space-4, 16px);
            padding-bottom: var(--space-3, 12px);
            border-bottom: 1px solid var(--color-border, #e5e5e5);
            display: flex;
            align-items: center;
            gap: var(--space-2, 8px);
        }

        .test-section h2 i {
            color: var(--color-primary-500);
        }

        .test-description {
            color: var(--color-text-muted);
            font-size: 0.875rem;
            margin-bottom: var(--space-4, 16px);
            padding: var(--space-3, 12px);
            background: var(--color-surface-alt, #fafafa);
            border-radius: var(--radius-md, 6px);
        }

        .test-grid {
            display: flex;
            flex-direction: column;
            gap: var(--space-4, 16px);
        }

        .test-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-muted);
            margin-bottom: var(--space-2, 8px);
            font-weight: 600;
        }

        .test-helpers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: var(--space-4, 16px);
        }

        .test-helper-item {
            padding: var(--space-4, 16px);
            background: var(--color-surface-alt, #fafafa);
            border-radius: var(--radius-lg, 8px);
            text-align: center;
        }

        .test-helper-item .test-label {
            margin-bottom: var(--space-3, 12px);
        }

        /* Accessibility Helper Styles */
        .skip-link {
            position: absolute;
            top: -40px;
            left: 0;
            background: var(--color-primary-600);
            color: white;
            padding: var(--space-2, 8px) var(--space-4, 16px);
            z-index: 1000;
            transition: top 0.3s;
        }

        .skip-link:focus {
            top: 0;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .icon-btn,
        .icon-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: var(--radius-full, 9999px);
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            color: var(--color-text);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .icon-btn:hover,
        .icon-link:hover {
            background: var(--color-primary-50);
            border-color: var(--color-primary-200);
            color: var(--color-primary-600);
        }

        .test-footer {
            text-align: center;
            padding: var(--space-8, 32px);
            color: var(--color-text-muted);
        }

        .test-code {
            background: var(--color-gray-900);
            color: var(--color-gray-100);
            padding: var(--space-4, 16px);
            border-radius: var(--radius-lg, 8px);
            font-family: monospace;
            font-size: 0.875rem;
            overflow-x: auto;
            margin-top: var(--space-3, 12px);
        }

        .test-code code {
            white-space: pre;
        }

        /* Props Reference Table Styles */
        .test-props-heading {
            margin: var(--space-4) 0 var(--space-2);
        }

        .test-props-heading--large {
            margin-top: var(--space-6);
        }

        .test-props-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .test-props-table thead tr {
            background: var(--color-surface-alt);
            text-align: left;
        }

        .test-props-table th,
        .test-props-table td {
            padding: var(--space-2) var(--space-3);
            border-bottom: 1px solid var(--color-border);
        }

        .test-helper-note {
            font-size: 0.8rem;
            color: var(--color-text-muted);
        }

        .test-comments-placeholder {
            padding: 16px;
            text-align: center;
            color: var(--color-text-muted);
        }
    </style>
</head>
<body class="test-page">
    <?php renderSkipLink(); ?>

    <div class="test-container">
        <header class="test-header">
            <h1>Shared Components Test</h1>
            <p>Modern Theme - Shared Component Library</p>
        </header>

        <!-- Post Card Component -->
        <section class="test-section" id="main-content">
            <h2><i class="fa-solid fa-newspaper"></i> Post Card Component</h2>

            <div class="test-description">
                <strong>File:</strong> <code>views/modern/components/shared/post-card.php</code><br>
                <strong>Usage:</strong> Reusable post display for feed, profile, and other views.<br>
                <strong>Features:</strong> User avatar, timestamps, visibility badges, like/comment/share actions, ARIA accessibility.
            </div>

            <div class="test-grid">
                <?php foreach ($mockPosts as $post): ?>
                    <div>
                        <div class="test-label">
                            <?= ucfirst($post['visibility']) ?> Post
                            <?php if ($post['user_id'] === $currentUserId): ?>(Own Post)<?php endif; ?>
                        </div>
                        <?php
                        $postAuthor = $mockAuthors[$post['user_id']];
                        include __DIR__ . '/post-card.php';
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="test-code">
                <code>&lt;?php
// Required variables
$post = [...];       // Post data array
$postAuthor = [...]; // Author data array
$currentUserId = 1;  // Current user ID
$showActions = true; // Show like/comment/share

include 'views/modern/components/shared/post-card.php';
?&gt;</code>
            </div>
        </section>

        <!-- Accessibility Helpers -->
        <section class="test-section">
            <h2><i class="fa-solid fa-universal-access"></i> Accessibility Helpers</h2>

            <div class="test-description">
                <strong>File:</strong> <code>views/modern/components/shared/accessibility-helpers.php</code><br>
                <strong>Usage:</strong> Reusable accessibility utilities for consistent ARIA and semantic HTML.<br>
                <strong>Functions:</strong> renderSkipLink(), srOnly(), iconButton(), iconLink()
            </div>

            <div class="test-helpers-grid">
                <div class="test-helper-item">
                    <div class="test-label">Icon Button</div>
                    <?php iconButton('fa-solid fa-heart', 'Like this post', "alert('Liked!')"); ?>
                    <?php iconButton('fa-solid fa-share', 'Share', "alert('Share!')"); ?>
                    <?php iconButton('fa-solid fa-bookmark', 'Save', "alert('Saved!')"); ?>
                </div>

                <div class="test-helper-item">
                    <div class="test-label">Icon Link</div>
                    <?php iconLink('#settings', 'fa-solid fa-cog', 'Settings'); ?>
                    <?php iconLink('#profile', 'fa-solid fa-user', 'Profile'); ?>
                    <?php iconLink('#notifications', 'fa-solid fa-bell', 'Notifications'); ?>
                </div>

                <div class="test-helper-item">
                    <div class="test-label">SR Only Text</div>
                    <p>
                        Rating: <span aria-hidden="true">★★★★☆</span>
                        <?= srOnly('4 out of 5 stars') ?>
                    </p>
                </div>

                <div class="test-helper-item">
                    <div class="test-label">Skip Link</div>
                    <p class="test-helper-note">
                        Press Tab at the top of the page to see the skip link appear.
                    </p>
                </div>
            </div>

            <div class="test-code">
                <code>&lt;?php
// Include the helpers
require_once 'views/modern/components/shared/accessibility-helpers.php';

// Skip link (place in header)
renderSkipLink();

// Screen reader only text
echo srOnly('4 out of 5 stars');

// Accessible icon button
iconButton('fa-solid fa-heart', 'Like', 'handleLike()');

// Accessible icon link
iconLink('/settings', 'fa-solid fa-cog', 'Settings');
?&gt;</code>
            </div>
        </section>

        <!-- Component Props Reference -->
        <section class="test-section">
            <h2><i class="fa-solid fa-book"></i> Props Reference</h2>

            <h3 class="test-props-heading">Post Card Props</h3>
            <table class="test-props-table">
                <thead>
                    <tr>
                        <th>Variable</th>
                        <th>Type</th>
                        <th>Required</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>$post</code></td>
                        <td>array</td>
                        <td>Yes</td>
                        <td>Post data (id, user_id, content, image_url, likes_count, created_at, visibility)</td>
                    </tr>
                    <tr>
                        <td><code>$postAuthor</code></td>
                        <td>array</td>
                        <td>Yes</td>
                        <td>Author data (id, first_name, last_name, avatar_url)</td>
                    </tr>
                    <tr>
                        <td><code>$currentUserId</code></td>
                        <td>int</td>
                        <td>No</td>
                        <td>Current logged-in user ID (defaults to session)</td>
                    </tr>
                    <tr>
                        <td><code>$showActions</code></td>
                        <td>bool</td>
                        <td>No</td>
                        <td>Show like/comment/share buttons (default: true)</td>
                    </tr>
                </tbody>
            </table>

            <h3 class="test-props-heading test-props-heading--large">Accessibility Helper Functions</h3>
            <table class="test-props-table">
                <thead>
                    <tr>
                        <th>Function</th>
                        <th>Parameters</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>renderSkipLink()</code></td>
                        <td>none</td>
                        <td>Renders skip to main content link</td>
                    </tr>
                    <tr>
                        <td><code>srOnly($text)</code></td>
                        <td>string $text</td>
                        <td>Returns screen reader only span</td>
                    </tr>
                    <tr>
                        <td><code>iconButton(...)</code></td>
                        <td>$icon, $label, $onclick, $classes, $attrs</td>
                        <td>Renders accessible icon button</td>
                    </tr>
                    <tr>
                        <td><code>iconLink(...)</code></td>
                        <td>$href, $icon, $label, $classes</td>
                        <td>Renders accessible icon link</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <footer class="test-footer">
            <p>Modern Theme Shared Components</p>
            <p><strong>2 components</strong> in <code>views/modern/components/shared/</code></p>
        </footer>
    </div>

    <script>
    // Mock functions for testing
    function deletePost(postId) {
        alert('Delete post ' + postId + ' (mock action)');
    }

    function toggleLike(button, type, id) {
        const isLiked = button.getAttribute('aria-pressed') === 'true';
        button.setAttribute('aria-pressed', !isLiked);

        const icon = button.querySelector('i');
        const count = button.querySelector('.like-count');
        const currentCount = parseInt(count.textContent.replace(/,/g, ''));

        if (isLiked) {
            icon.classList.remove('fa-solid');
            icon.classList.add('fa-regular');
            button.classList.remove('component-post-card__like-btn--liked');
            count.textContent = (currentCount - 1).toLocaleString();
        } else {
            icon.classList.remove('fa-regular');
            icon.classList.add('fa-solid');
            button.classList.add('component-post-card__like-btn--liked');
            count.textContent = (currentCount + 1).toLocaleString();
        }
    }

    function toggleComments(postId) {
        const comments = document.getElementById('comments-' + postId);
        const button = document.querySelector('[aria-controls="comments-' + postId + '"]');
        const isExpanded = button.getAttribute('aria-expanded') === 'true';

        button.setAttribute('aria-expanded', !isExpanded);
        comments.setAttribute('aria-hidden', isExpanded);
        comments.classList.toggle('component-hidden');

        if (!isExpanded) {
            comments.innerHTML = '<div class="test-comments-placeholder">Comments would load here...</div>';
        }
    }

    function sharePost(postId) {
        if (navigator.share) {
            navigator.share({
                title: 'Shared Post',
                text: 'Check out this post!',
                url: window.location.href + '#post-' + postId
            });
        } else {
            alert('Share post ' + postId + ' (mock action)');
        }
    }
    </script>
</body>
</html>

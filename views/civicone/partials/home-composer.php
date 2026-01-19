<?php
/**
 * Home Page Composer Partial - CivicOne Version
 * Simple Create Post Prompt (Facebook-style) - Opens full compose page
 *
 * Expected variables from parent:
 * - $isLoggedIn (bool)
 *
 * @package Nexus\Views\CivicOne\Partials
 */

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Simple Create Post Prompt (Facebook-style) - Opens full compose page -->
<div class="fds-create-post">
    <div class="fds-create-post-body">
        <?php if ($isLoggedIn): ?>
            <!-- Logged In: Simple prompt that opens /compose -->
            <a href="<?= $basePath ?>/compose" class="compose-prompt-link">
                <div class="compose-prompt">
                    <div class="composer-avatar-ring">
                        <?= webp_avatar($_SESSION['user_avatar'] ?? null, $_SESSION['user_name'] ?? 'User', 40) ?>
                    </div>
                    <div class="compose-prompt-input">
                        What's on your mind, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'User')[0]) ?>?
                    </div>
                </div>
            </a>

            <!-- Quick action buttons -->
            <nav class="compose-quick-actions" aria-label="Quick compose actions">
                <a href="<?= $basePath ?>/compose?type=post" class="compose-quick-btn">
                    <i class="fa-solid fa-pen icon-indigo" aria-hidden="true"></i>
                    <span>Post</span>
                </a>
                <a href="<?= $basePath ?>/compose?type=listing" class="compose-quick-btn">
                    <i class="fa-solid fa-hand-holding-heart icon-green" aria-hidden="true"></i>
                    <span>Listing</span>
                </a>
                <a href="<?= $basePath ?>/compose?type=event" class="compose-quick-btn">
                    <i class="fa-solid fa-calendar-plus icon-pink" aria-hidden="true"></i>
                    <span>Event</span>
                </a>
            </nav>
        <?php else: ?>
            <!-- Logged Out: Join CTA -->
            <a href="<?= $basePath ?>/register" class="compose-prompt-link">
                <div class="compose-prompt">
                    <div class="composer-avatar-ring guest">
                        <div class="composer-avatar-guest">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                        </div>
                    </div>
                    <div class="compose-prompt-input">
                        What's on your mind? Join to share...
                    </div>
                </div>
            </a>

            <!-- Auth buttons -->
            <nav class="compose-quick-actions" aria-label="Authentication options">
                <a href="<?= $basePath ?>/login" class="compose-quick-btn">
                    <i class="fa-solid fa-right-to-bracket icon-blue" aria-hidden="true"></i>
                    <span>Log In</span>
                </a>
                <a href="<?= $basePath ?>/register" class="compose-quick-btn highlight">
                    <i class="fa-solid fa-user-plus icon-white" aria-hidden="true"></i>
                    <span>Sign Up</span>
                </a>
                <a href="<?= $basePath ?>/listings" class="compose-quick-btn">
                    <i class="fa-solid fa-compass icon-amber" aria-hidden="true"></i>
                    <span>Browse</span>
                </a>
            </nav>
        <?php endif; ?>
    </div>
</div>

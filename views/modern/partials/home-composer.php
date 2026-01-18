<?php
/**
 * Home Page Composer Partial
 * Simple Create Post Prompt (Facebook-style) - Opens full compose page
 *
 * Expected variables from parent:
 * - $isLoggedIn (bool)
 *
 * @package Nexus\Views\Modern\Partials
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
            <div class="compose-quick-actions">
                <a href="<?= $basePath ?>/compose?type=post" class="compose-quick-btn">
                    <i class="fa-solid fa-pen icon-indigo"></i>
                    <span>Post</span>
                </a>
                <a href="<?= $basePath ?>/compose?type=listing" class="compose-quick-btn">
                    <i class="fa-solid fa-hand-holding-heart icon-green"></i>
                    <span>Listing</span>
                </a>
                <a href="<?= $basePath ?>/compose?type=event" class="compose-quick-btn">
                    <i class="fa-solid fa-calendar-plus icon-pink"></i>
                    <span>Event</span>
                </a>
            </div>
        <?php else: ?>
            <!-- Logged Out: Join CTA -->
            <a href="<?= $basePath ?>/register" class="compose-prompt-link">
                <div class="compose-prompt">
                    <div class="composer-avatar-ring guest">
                        <div class="composer-avatar-guest">
                            <i class="fa-solid fa-user"></i>
                        </div>
                    </div>
                    <div class="compose-prompt-input">
                        What's on your mind? Join to share...
                    </div>
                </div>
            </a>

            <!-- Auth buttons -->
            <div class="compose-quick-actions">
                <a href="<?= $basePath ?>/login" class="compose-quick-btn">
                    <i class="fa-solid fa-right-to-bracket icon-blue"></i>
                    <span>Log In</span>
                </a>
                <a href="<?= $basePath ?>/register" class="compose-quick-btn highlight">
                    <i class="fa-solid fa-user-plus icon-white"></i>
                    <span>Sign Up</span>
                </a>
                <a href="<?= $basePath ?>/listings" class="compose-quick-btn">
                    <i class="fa-solid fa-compass icon-amber"></i>
                    <span>Browse</span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

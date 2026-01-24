<?php

/**
 * Mobile Bottom Sheets
 * Native app-like slide-up sheets for mobile devices only
 *
 * Features:
 * - Comment sheet (focused comment input with like/reply)
 * - Drag to dismiss
 * - Backdrop tap to close
 * - Haptic feedback
 *
 * Only activates on screens < 768px or touch devices
 *
 * CSS extracted to: /assets/css/mobile-sheets.css
 * CSS loaded via layout headers for proper caching
 */

$isLoggedIn = isset($_SESSION['user_id']);
$userAvatar = $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp';
$userName = $_SESSION['user_name'] ?? 'User';
$basePath = class_exists('\Nexus\Core\TenantContext') ? \Nexus\Core\TenantContext::getBasePath() : '';
?>

<!-- CRITICAL: Hide mobile sheets on desktop -->
<style>
@media (min-width: 1025px) {
    .mobile-sheet-backdrop,
    .mobile-sheet,
    #commentBackdrop,
    #commentSheet {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }
}
</style>

<!-- Comment Sheet -->
<div class="mobile-sheet-backdrop" id="commentBackdrop" onclick="closeMobileCommentSheet()"></div>
<div class="mobile-sheet" id="commentSheet">
    <div class="mobile-sheet-handle" id="commentHandle"></div>
    <div class="mobile-sheet-header">
        <button type="button" class="mobile-sheet-close" onclick="closeMobileCommentSheet()" aria-label="Close comments">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <span class="mobile-sheet-title">Comments</span>
        <span class="mobile-sheet-header-spacer"></span>
    </div>
    <div class="mobile-sheet-body mobile-sheet-body--flex-column">
        <!-- Comments list ABOVE the input (like Facebook) -->
        <div class="mobile-comments-list mobile-comments-list--flex" id="mobileCommentsList">
            <!-- Comments loaded dynamically -->
        </div>

        <?php if ($isLoggedIn): ?>
            <div class="mobile-comment-input-wrap mobile-comment-input-wrap--bottom">
                <img src="<?= htmlspecialchars($userAvatar) ?>" loading="lazy" alt="" class="mobile-comment-avatar">
                <textarea class="mobile-comment-input" id="mobileCommentInput" placeholder="Write a comment..." rows="1" oninput="autoResizeCommentInput(this)"></textarea>
                <button type="button" class="mobile-comment-send" id="mobileCommentSend" onclick="submitMobileComment()">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
        <?php else: ?>
            <div class="mobile-sheet-signin-prompt">
                <a href="<?= $basePath ?>/login" class="mobile-sheet-signin-link">Sign in to comment</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mobile sheets JavaScript -->
<script src="/assets/js/mobile-sheets.min.js" defer></script>

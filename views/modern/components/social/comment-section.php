<?php

/**
 * Component: Comment Section
 *
 * Complete comment section with form and list display.
 * Used on: feed/show, polls/show, events/show, listings/show, volunteering/show, goals/show, groups/show
 *
 * @param string $contentType Content type identifier (e.g., 'post', 'poll', 'event', 'listing')
 * @param int $contentId ID of the content being commented on
 * @param array $comments Array of comment data
 * @param array $currentUser Current logged-in user data
 * @param string $placeholder Input placeholder text (default: 'Write a comment...')
 * @param string $submitLabel Submit button label (default: 'Post')
 * @param string $emptyMessage Message when no comments (default: 'No comments yet. Be the first!')
 * @param bool $showReactions Show reaction buttons (default: true)
 * @param bool $allowReplies Allow nested replies (default: true)
 * @param string $formAction Form action URL (default: auto-generated)
 * @param string $class Additional CSS classes
 * @param string $id Container ID (default: 'commentsSection')
 */

$contentType = $contentType ?? 'post';
$contentId = $contentId ?? 0;
$comments = $comments ?? [];
$currentUser = $currentUser ?? [];
$placeholder = $placeholder ?? 'Write a comment...';
$submitLabel = $submitLabel ?? 'Post';
$emptyMessage = $emptyMessage ?? 'No comments yet. Be the first!';
$showReactions = $showReactions ?? true;
$allowReplies = $allowReplies ?? true;
$formAction = $formAction ?? "/{$contentType}s/{$contentId}/comments";
$class = $class ?? '';
$id = $id ?? 'commentsSection';

$cssClass = trim('component-comments ' . $class);
$isLoggedIn = !empty($currentUser['id']);
?>

<div class="<?= e($cssClass) ?>" id="<?= e($id) ?>" data-content-type="<?= e($contentType) ?>" data-content-id="<?= $contentId ?>">
    <!-- Comment Form -->
    <?php if ($isLoggedIn): ?>
        <form class="component-comments__form" id="<?= e($id) ?>Form" onsubmit="submitComment(event, '<?= e($contentType) ?>', <?= $contentId ?>)">
            <div class="component-comments__form-inner">
                <div class="component-comments__form-avatar">
                    <?= webp_avatar($currentUser['avatar'] ?? '', $currentUser['name'] ?? 'User', 36) ?>
                </div>
                <div class="component-comments__form-input">
                    <textarea
                        name="comment"
                        id="<?= e($id) ?>Input"
                        class="component-comments__textarea glass-input"
                        placeholder="<?= e($placeholder) ?>"
                        rows="1"
                        required
                        oninput="this.style.height = 'auto'; this.style.height = Math.min(this.scrollHeight, 120) + 'px';"
                    ></textarea>
                </div>
                <button type="submit" class="component-comments__submit nexus-smart-btn nexus-smart-btn-primary">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span class="component-comments__submit-label"><?= e($submitLabel) ?></span>
                </button>
            </div>
        </form>
    <?php else: ?>
        <div class="component-comments__login-prompt">
            <p class="component-comments__login-text">Log in to leave a comment</p>
            <a href="/login" class="nexus-smart-btn nexus-smart-btn-primary">
                <i class="fa-solid fa-sign-in-alt"></i> Log In
            </a>
        </div>
    <?php endif; ?>

    <!-- Comments List -->
    <div class="component-comments__list" id="<?= e($id) ?>List">
        <?php if (empty($comments)): ?>
            <div class="component-comments__empty">
                <i class="fa-regular fa-comments component-comments__empty-icon"></i>
                <p><?= e($emptyMessage) ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <?php
                $commentId = $comment['id'] ?? 0;
                $commentUser = $comment['user'] ?? [];
                $commentBody = $comment['body'] ?? $comment['content'] ?? '';
                $commentCreatedAt = $comment['created_at'] ?? '';
                $commentLikes = $comment['likes_count'] ?? 0;
                $commentIsLiked = $comment['is_liked'] ?? false;
                $commentReplies = $comment['replies'] ?? [];

                // Format time
                $timeAgo = '';
                if ($commentCreatedAt) {
                    $timestamp = is_string($commentCreatedAt) ? strtotime($commentCreatedAt) : $commentCreatedAt;
                    $diff = time() - $timestamp;
                    if ($diff < 60) $timeAgo = 'Just now';
                    elseif ($diff < 3600) $timeAgo = floor($diff / 60) . 'm';
                    elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . 'h';
                    elseif ($diff < 604800) $timeAgo = floor($diff / 86400) . 'd';
                    else $timeAgo = date('M j', $timestamp);
                }

                $likeClass = 'component-comments__action-btn component-comments__like-btn';
                if ($commentIsLiked) $likeClass .= ' component-comments__like-btn--liked';
                ?>
                <div class="component-comments__item" id="comment-<?= $commentId ?>" data-comment-id="<?= $commentId ?>">
                    <div class="component-comments__avatar">
                        <a href="/members/<?= $commentUser['id'] ?? 0 ?>">
                            <?= webp_avatar($commentUser['avatar'] ?? '', $commentUser['name'] ?? 'User', 36) ?>
                        </a>
                    </div>
                    <div class="component-comments__content">
                        <div class="component-comments__header">
                            <a href="/members/<?= $commentUser['id'] ?? 0 ?>" class="component-comments__author">
                                <?= e($commentUser['name'] ?? 'Unknown') ?>
                            </a>
                            <span class="component-comments__time">
                                <?= e($timeAgo) ?>
                            </span>
                        </div>
                        <div class="component-comments__body">
                            <?= nl2br(e($commentBody)) ?>
                        </div>
                        <div class="component-comments__actions">
                            <?php if ($showReactions): ?>
                                <button
                                    type="button"
                                    class="<?= e($likeClass) ?>"
                                    onclick="toggleCommentLike(<?= $commentId ?>)"
                                >
                                    <i class="fa-<?= $commentIsLiked ? 'solid' : 'regular' ?> fa-heart"></i>
                                    <?php if ($commentLikes > 0): ?>
                                        <span class="component-comments__like-count"><?= $commentLikes ?></span>
                                    <?php endif; ?>
                                </button>
                            <?php endif; ?>
                            <?php if ($allowReplies && $isLoggedIn): ?>
                                <button
                                    type="button"
                                    class="component-comments__action-btn component-comments__reply-btn"
                                    onclick="showReplyForm(<?= $commentId ?>)"
                                >
                                    <i class="fa-solid fa-reply"></i> Reply
                                </button>
                            <?php endif; ?>
                            <?php if ($isLoggedIn && ($currentUser['id'] ?? 0) === ($commentUser['id'] ?? -1)): ?>
                                <button
                                    type="button"
                                    class="component-comments__action-btn component-comments__delete-btn"
                                    onclick="deleteComment(<?= $commentId ?>)"
                                >
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Replies -->
                        <?php if (!empty($commentReplies)): ?>
                            <div class="component-comments__replies">
                                <?php foreach ($commentReplies as $reply): ?>
                                    <div class="component-comments__reply">
                                        <div class="component-comments__reply-avatar">
                                            <?= webp_avatar($reply['user']['avatar'] ?? '', $reply['user']['name'] ?? '', 28) ?>
                                        </div>
                                        <div class="component-comments__reply-content">
                                            <span class="component-comments__reply-author"><?= e($reply['user']['name'] ?? 'Unknown') ?></span>
                                            <span class="component-comments__reply-body"><?= e($reply['body'] ?? '') ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Reply Form (hidden by default) -->
                        <div class="component-comments__reply-form component-hidden" id="replyForm-<?= $commentId ?>"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
/**
 * Feed Post Detail View
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */

$userId = $_SESSION['user_id'] ?? 0;
$isLoggedIn = !empty($userId);
$userAvatar = $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.webp';
$userName = $_SESSION['user_name'] ?? 'Guest';
$tenantId = $_SESSION['current_tenant_id'] ?? (\Nexus\Core\TenantContext::getId() ?? 1);
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Social interaction target
$socialTargetType = 'post';
$socialTargetId = $post['id'];

require __DIR__ . '/../../layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Feed', 'href' => $basePath . '/feed'],
        ['text' => 'Post']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<a href="<?= $basePath ?>/" class="govuk-back-link govuk-!-margin-bottom-6">Back to Feed</a>

<!-- Post Card -->
<div class="govuk-!-padding-6 civicone-sidebar-card" style="max-width: 800px;">

    <!-- Header -->
    <div class="govuk-grid-row govuk-!-margin-bottom-4">
        <div class="govuk-grid-column-three-quarters">
            <div class="civicone-feed-row">
                <a href="<?= $basePath ?>/profile/<?= $post['user_id'] ?>">
                    <img src="<?= !empty($post['author_avatar']) ? htmlspecialchars($post['author_avatar']) : '/assets/img/defaults/default_avatar.webp' ?>"
                         alt=""
                         class="civicone-avatar--48">
                </a>
                <div>
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <strong><?= htmlspecialchars($post['author_name']) ?></strong>
                    </p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                        <i class="fa-regular fa-clock govuk-!-margin-right-1" aria-hidden="true"></i>
                        <?= date('M j, Y \a\t g:i a', strtotime($post['created_at'])) ?>
                        <?php if (isset($post['visibility'])): ?>
                            <span class="govuk-!-margin-left-2">
                                <?= $post['visibility'] === 'public' ? '<i class="fa-solid fa-globe"></i> Public' : '<i class="fa-solid fa-lock"></i> Private' ?>
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="govuk-body govuk-!-margin-bottom-4">
        <?php
        $escapedContent = htmlspecialchars($post['content']);
        $content = preg_replace_callback('/(https?:\/\/[^\s]+)/', function($m) {
            $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" class="govuk-link">' . htmlspecialchars($url) . '</a>';
        }, $escapedContent);
        echo nl2br($content);
        ?>
    </div>

    <!-- Image -->
    <?php if (!empty($post['image_url'])): ?>
        <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="Post image" class="govuk-!-margin-bottom-4 civicone-responsive-image">
    <?php endif; ?>

    <!-- Stats Bar -->
    <div class="govuk-grid-row govuk-!-margin-bottom-4 govuk-!-padding-3 civicone-panel-bg">
        <div class="govuk-grid-column-one-half">
            <p class="govuk-body-s govuk-!-margin-bottom-0">
                <i class="fa-solid fa-heart govuk-!-margin-right-1" aria-hidden="true"></i>
                <span id="likes-count-<?= $socialTargetId ?>"><?= $post['likes_count'] ?? 0 ?></span> Likes
            </p>
        </div>
        <div class="govuk-grid-column-one-half">
            <p class="govuk-body-s govuk-!-margin-bottom-0">
                <i class="fa-solid fa-comment govuk-!-margin-right-1" aria-hidden="true"></i>
                <span id="comments-count-<?= $socialTargetId ?>">0</span> Comments
            </p>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="govuk-button-group govuk-!-margin-bottom-6">
        <button type="button" class="govuk-button govuk-button--secondary <?= ($post['is_liked'] ?? 0) ? 'liked' : '' ?>"
                data-module="govuk-button"
                onclick="toggleLike(this, '<?= $socialTargetType ?>', <?= $socialTargetId ?>)">
            <i class="<?= ($post['is_liked'] ?? 0) ? 'fa-solid' : 'fa-regular' ?> fa-heart govuk-!-margin-right-1" aria-hidden="true"></i> Like
        </button>
        <button type="button" class="govuk-button govuk-button--secondary" data-module="govuk-button"
                onclick="document.getElementById('comment-input').focus()">
            <i class="fa-regular fa-comment govuk-!-margin-right-1" aria-hidden="true"></i> Comment
        </button>
        <button type="button" class="govuk-button govuk-button--secondary" data-module="govuk-button"
                onclick="repostToFeed('<?= $socialTargetType ?>', <?= $socialTargetId ?>, '<?= addslashes($post['author_name']) ?>')">
            <i class="fa-regular fa-share-from-square govuk-!-margin-right-1" aria-hidden="true"></i> Share
        </button>
    </div>

    <!-- Comments Section -->
    <div id="comments-section-<?= $socialTargetType ?>-<?= $socialTargetId ?>">
        <h2 class="govuk-heading-m">
            <i class="fa-solid fa-comments govuk-!-margin-right-2" aria-hidden="true"></i>
            Comments
        </h2>

        <!-- Comment Compose -->
        <?php if ($isLoggedIn): ?>
        <div class="govuk-form-group govuk-!-margin-bottom-6">
            <label class="govuk-label" for="comment-input">Write a comment</label>
            <div class="civicone-feed-row">
                <img src="<?= htmlspecialchars($userAvatar) ?>" alt="" class="civicone-table-avatar">
                <div class="civicone-flex-grow">
                    <input type="text"
                           id="comment-input"
                           class="govuk-input"
                           placeholder="Write a comment..."
                           onkeydown="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); submitComment(this, '<?= $socialTargetType ?>', <?= $socialTargetId ?>); }">
                </div>
                <button type="button" class="govuk-button" data-module="govuk-button"
                        onclick="submitComment(document.getElementById('comment-input'), '<?= $socialTargetType ?>', <?= $socialTargetId ?>)">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Comments List -->
        <div class="comments-list" id="comments-list-<?= $socialTargetId ?>">
            <div class="govuk-inset-text">Loading comments...</div>
        </div>
    </div>

</div>

<script src="<?= $basePath ?>/assets/js/civicone-feed-show.min.js" defer></script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

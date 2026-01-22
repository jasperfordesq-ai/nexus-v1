<?php
/**
 * Template D: Form Page
 * Create Group Discussion
 * WCAG 2.1 AA Compliant
 * Parent: Group Show
 * Path: views/civicone/groups/discussions/create.php
 */

$hTitle = 'Start a Discussion';
$hSubtitle = htmlspecialchars($group['name']);
$hGradient = 'htb-hero-gradient-hub';

require __DIR__ . '/../../../layouts/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
?>

<link rel="stylesheet" href="/assets/css/purged/civicone-groups-discussions-create.min.css?v=<?= time() ?>">

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">
        <div class="htb-container discussion-create-wrapper">

            <!-- Back Navigation -->
            <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>?tab=discussions" class="discussion-back-btn">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                <span>Back to Hub</span>
            </a>

            <div class="htb-card discussion-create-card">
                <!-- Header -->
                <div class="discussion-create-header">
                    <h2>
                        <i class="fa-regular fa-comments" aria-hidden="true"></i>
                        New Discussion
                    </h2>
                    <p>Start a conversation with the community.</p>
                </div>

                <!-- Form Body -->
                <div class="discussion-create-body">
                    <form action="<?= $basePath ?>/groups/<?= $group['id'] ?>/discussions/store" method="POST">
                        <?= \Nexus\Core\Csrf::input() ?>

                        <!-- Title Input -->
                        <div class="mb-24">
                            <label class="discussion-form-label" for="discussion-title">Topic Title</label>
                            <input type="text" id="discussion-title" name="title" class="discussion-form-input"
                                placeholder="What's on your mind?" required autofocus>
                        </div>

                        <!-- Message Input -->
                        <div class="mb-24">
                            <label class="discussion-form-label" for="discussion-content">Your Message</label>
                            <textarea id="discussion-content" name="content" class="discussion-form-input" rows="6"
                                placeholder="Share your thoughts, ask a question, or start a debate..." required></textarea>
                            <div class="discussion-form-hint">Markdown supported</div>
                        </div>

                        <!-- Actions -->
                        <div class="discussion-form-actions">
                            <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>?tab=discussions" class="discussion-cancel-btn">
                                Cancel
                            </a>
                            <button type="submit" class="discussion-submit-btn">
                                <i class="fa-regular fa-paper-plane" aria-hidden="true"></i>
                                <span>Post Discussion</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>
</div>

<script src="/assets/js/civicone-groups-discussions-create.js?v=<?= time() ?>"></script>

<?php require __DIR__ . '/../../../layouts/footer.php'; ?>

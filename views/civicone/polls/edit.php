<?php
// Phoenix Edit Poll View - Full Holographic Glassmorphism Edition
$hero_title = "Edit Poll";
$hero_subtitle = "Update your community question.";
$hero_gradient = 'htb-hero-gradient-polls';
$hero_type = 'Poll';
$hideHero = true;

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Polls Edit CSS -->
<link rel="stylesheet" href="<?= NexusCoreTenantContext::getBasePath() ?>/assets/css/purged/civicone-polls-edit.min.css">

<!-- Offline Banner -->
<div class="holo-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="holo-poll-page">
    <!-- Floating Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="holo-poll-container">
        <!-- Page Header -->
        <div class="holo-page-header">
            <div class="holo-page-icon">✏️</div>
            <h1 class="holo-page-title">Edit Poll</h1>
            <p class="holo-page-subtitle">Update your community question</p>
        </div>

        <!-- Glass Card Form -->
        <div class="holo-glass-card">
            <form action="<?= $basePath ?>/polls/<?= $poll['id'] ?>/update" method="POST" id="editPollForm">
                <?= \Nexus\Core\Csrf::input() ?>

                <!-- Question -->
                <div class="holo-section">
                    <label class="holo-label" for="question">Question</label>
                    <input type="text" name="question" id="question" class="holo-input" value="<?= htmlspecialchars($poll['question']) ?>" placeholder="Enter your question here..." required>
                </div>

                <!-- Description -->
                <div class="holo-section">
                    <label class="holo-label" for="description">Description <span class="holo-label-optional">(Optional)</span></label>
                    <textarea name="description" id="description" class="holo-textarea" placeholder="Add more context..."><?= htmlspecialchars($poll['description']) ?></textarea>
                </div>

                <!-- End Date -->
                <div class="holo-section">
                    <label class="holo-label" for="end_date">End Date <span class="holo-label-optional">(Optional)</span></label>
                    <input type="date" name="end_date" id="end_date" class="holo-input" value="<?= $poll['end_date'] ? date('Y-m-d', strtotime($poll['end_date'])) : '' ?>">
                </div>

                <!-- Warning Alert -->
                <div class="holo-alert holo-alert-warning">
                    <div class="holo-alert-icon">
                        <i class="fa-solid fa-exclamation"></i>
                    </div>
                    <div class="holo-alert-content">
                        <div class="holo-alert-title">Options cannot be changed</div>
                        <div class="holo-alert-text">
                            You can only edit the question text and deadline. To change voting options, please create a new poll to ensure vote integrity.
                        </div>
                    </div>
                </div>

                <hr class="holo-divider">

                <!-- Action Buttons -->
                <div class="holo-actions">
                    <button type="submit" class="holo-btn holo-btn-primary" id="submitBtn">
                        <i class="fa-solid fa-check"></i>
                        Save Changes
                    </button>
                    <a href="<?= $basePath ?>/polls/<?= $poll['id'] ?>" class="holo-btn holo-btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i>
                        Cancel
                    </a>
                </div>

                <!-- Danger Zone -->
                <div class="holo-danger-zone">
                    <button type="submit" formaction="<?= $basePath ?>/polls/<?= $poll['id'] ?>/delete" class="holo-btn holo-btn-danger" onclick="return confirm('Are you sure? This will permanently delete the poll and all votes.')">
                        <i class="fa-solid fa-trash"></i>
                        Delete Poll
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Polls Edit JavaScript -->
<script src="<?= NexusCoreTenantContext::getBasePath() ?>/assets/js/civicone-polls-edit.min.js" defer></script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>

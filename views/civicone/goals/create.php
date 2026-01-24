<?php
// CivicOne View: Create Goal
$pageTitle = 'Set a Goal';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<link rel="stylesheet" href="<?= Nexus\Core\TenantContext::getBasePath() ?>/assets/css/civicone-goals-form.css">

<div class="civic-container">
    <?php
    $breadcrumbs = [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Goals', 'url' => '/goals'],
        ['label' => 'Set a New Goal']
    ];
    require dirname(__DIR__, 2) . '/layouts/civicone/partials/breadcrumb.php';
    ?>

    <div class="goal-form-header">
        <h1 class="goal-form-title">Set a New Goal</h1>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/goals" class="civic-btn civic-bg-gray-200 civic-text-dark goal-form-cancel">Cancel</a>
    </div>

    <div class="civic-card goal-form-card">

        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/goals/store" method="POST">
            <?= Nexus\Core\Csrf::input() ?>

            <div class="goal-form-group">
                <label for="title" class="goal-form-label">Goal Title</label>
                <input type="text" name="title" id="title" class="civic-input goal-form-input" placeholder="e.g. Learn to Paint, Run a 5k..." required>
                <p class="goal-form-hint">Short and sweet.</p>
            </div>

            <div class="goal-form-group">
                <label for="description" class="goal-form-label">Description & Details</label>
                <textarea name="description" id="description" class="civic-input goal-form-textarea" rows="5" placeholder="Share more details about what you want to achieve..."></textarea>
            </div>

            <div class="goal-form-grid">
                <div>
                    <label for="deadline" class="goal-form-label">Target Date (Optional)</label>
                    <input type="date" name="deadline" id="deadline" class="civic-input goal-form-input">
                </div>

                <div class="goal-form-checkbox-wrapper">
                    <label class="goal-form-checkbox-label">
                        <input type="checkbox" name="is_public" value="1" checked class="goal-form-checkbox">
                        <span>
                            <strong class="goal-form-checkbox-title">Make Public?</strong>
                            <span class="goal-form-checkbox-desc">Allow others to see and support this goal.</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="goal-form-actions">
                <button type="submit" class="civic-btn goal-form-submit">Create Goal</button>
            </div>

        </form>

    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
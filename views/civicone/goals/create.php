<?php
// CivicOne View: Create Goal
$pageTitle = 'Set a Goal';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">
    <?php
    $breadcrumbs = [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Goals', 'url' => '/goals'],
        ['label' => 'Set a New Goal']
    ];
    require dirname(__DIR__, 2) . '/layouts/civicone/partials/breadcrumb.php';
    ?>

    <div style="margin-bottom: 30px; border-bottom: 4px solid var(--skin-primary, #00796B); padding-bottom: 15px; display: flex; justify-content: space-between; align-items: flex-end;">
        <h1 style="margin: 0; text-transform: uppercase; color: var(--skin-primary);">Set a New Goal</h1>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/goals" class="civic-btn civic-bg-gray-200 civic-text-dark" style="font-size: 0.9rem;">Cancel</a>
    </div>

    <div class="civic-card" style="max-width: 800px; margin: 0 auto;">

        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/goals/store" method="POST">
            <?= Nexus\Core\Csrf::input() ?>

            <div style="margin-bottom: 20px;">
                <label for="title" class="civic-text-dark" style="display: block; font-weight: bold; margin-bottom: 5px;">Goal Title</label>
                <input type="text" name="title" id="title" class="civic-input" placeholder="e.g. Learn to Paint, Run a 5k..." required style="width: 100%;">
                <p style="font-size: 0.85rem; color: var(--civic-text-secondary, #4B5563); margin-top: 5px;">Short and sweet.</p>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="description" class="civic-text-dark" style="display: block; font-weight: bold; margin-bottom: 5px;">Description & Details</label>
                <textarea name="description" id="description" class="civic-input" rows="5" placeholder="Share more details about what you want to achieve..." style="width: 100%; font-family: inherit;"></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label for="deadline" class="civic-text-dark" style="display: block; font-weight: bold; margin-bottom: 5px;">Target Date (Optional)</label>
                    <input type="date" name="deadline" id="deadline" class="civic-input" style="width: 100%;">
                </div>

                <div style="display: flex; align-items: center; padding-top: 25px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="is_public" value="1" checked style="width: 20px; height: 20px; margin-right: 10px;">
                        <span>
                            <strong style="display: block;">Make Public?</strong>
                            <span style="font-size: 0.85rem; color: var(--civic-text-secondary, #4B5563);">Allow others to see and support this goal.</span>
                        </span>
                    </label>
                </div>
            </div>

            <div style="margin-top: 30px; text-align: right;">
                <button type="submit" class="civic-btn" style="padding: 12px 30px; font-size: 1.1rem;">Create Goal</button>
            </div>

        </form>

    </div>

</div>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
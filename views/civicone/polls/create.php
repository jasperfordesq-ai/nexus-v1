<?php
// CivicOne View: Create Poll
$heroTitle = "Create Poll";
$heroSub = "Ask the community a question.";
$heroType = 'Governance';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<link rel="stylesheet" href="<?= Nexus\Core\TenantContext::getBasePath() ?>/assets/css/civicone-polls-show.css">

<div class="civic-container">

    <h2 class="civic-border-bottom-black poll-create-heading">New Poll</h2>

    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/polls/store" method="POST" class="civic-card poll-create-form">
        <?= Nexus\Core\Csrf::input() ?>

        <label class="civic-label">Question</label>
        <input type="text" name="question" class="civic-input" placeholder="e.g. Should we host a summer picnic?" required>

        <label class="civic-label">Description (Optional)</label>
        <textarea name="description" class="civic-input" rows="3"></textarea>

        <label class="civic-label">Options (At least 2)</label>
        <div id="poll-options">
            <input type="text" name="options[]" class="civic-input poll-create-option-input" placeholder="Option 1" required>
            <input type="text" name="options[]" class="civic-input poll-create-option-input" placeholder="Option 2" required>
        </div>
        <button type="button" onclick="addOption()" class="civic-poll-option poll-create-add-btn">+ Add Option</button>

        <label class="civic-label">End Date (Optional)</label>
        <input type="date" name="end_date" class="civic-input">

        <button type="submit" class="civic-btn poll-create-submit">Publish Poll</button>
    </form>

</div>

<script>
    function addOption() {
        var div = document.createElement('input');
        div.type = 'text';
        div.name = 'options[]';
        div.className = 'civic-input poll-create-option-input';
        div.placeholder = 'New Option';
        document.getElementById('poll-options').appendChild(div);
    }
</script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>

<?php
// CivicOne View: Create Poll
$heroTitle = "Create Poll";
$heroSub = "Ask the community a question.";
$heroType = 'Governance';

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<div class="civic-container">

    <h2 style="border-bottom: 4px solid #000; padding-bottom: 10px; margin-bottom: 30px;">New Poll</h2>

    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/polls/store" method="POST" class="civic-card" style="max-width: 600px;">
        <?= Nexus\Core\Csrf::input() ?>

        <label class="civic-label">Question</label>
        <input type="text" name="question" class="civic-input" placeholder="e.g. Should we host a summer picnic?" required>

        <label class="civic-label">Description (Optional)</label>
        <textarea name="description" class="civic-input" rows="3"></textarea>

        <label class="civic-label">Options (At least 2)</label>
        <div id="poll-options">
            <input type="text" name="options[]" class="civic-input" placeholder="Option 1" required style="margin-bottom: 10px;">
            <input type="text" name="options[]" class="civic-input" placeholder="Option 2" required style="margin-bottom: 10px;">
        </div>
        <button type="button" onclick="addOption()" style="background: #eee; border: 1px solid #999; padding: 5px 10px; cursor: pointer; margin-bottom: 20px;">+ Add Option</button>

        <label class="civic-label">End Date (Optional)</label>
        <input type="date" name="end_date" class="civic-input">

        <button type="submit" class="civic-btn" style="width: 100%; margin-top: 20px;">Publish Poll</button>
    </form>

</div>

<script>
    function addOption() {
        const div = document.createElement('input');
        div.type = 'text';
        div.name = 'options[]';
        div.className = 'civic-input';
        div.placeholder = 'New Option';
        div.style.marginBottom = '10px';
        document.getElementById('poll-options').appendChild(div);
    }
</script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
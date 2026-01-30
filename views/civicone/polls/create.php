<?php
/**
 * CivicOne View: Create Poll
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
$pageTitle = 'Create Poll';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Polls', 'href' => $basePath . '/polls'],
        ['text' => 'Create Poll']
    ],
    'class' => 'govuk-!-margin-bottom-6'
]) ?>

<a href="<?= $basePath ?>/polls" class="govuk-back-link govuk-!-margin-bottom-6">Back to polls</a>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">

        <h1 class="govuk-heading-xl">Create a new poll</h1>
        <p class="govuk-body-l govuk-!-margin-bottom-6">Ask the community a question and gather their opinions.</p>

        <form action="<?= $basePath ?>/polls/store" method="POST" id="create-poll-form">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Question -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="question">Question</label>
                <div id="question-hint" class="govuk-hint">What would you like to ask the community?</div>
                <input type="text" name="question" id="question" class="govuk-input" placeholder="e.g. Should we host a summer picnic?" aria-describedby="question-hint" required>
            </div>

            <!-- Description -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="description">
                    Description <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                </label>
                <div id="description-hint" class="govuk-hint">Provide more context for voters</div>
                <textarea name="description" id="description" class="govuk-textarea" rows="3" aria-describedby="description-hint"></textarea>
            </div>

            <!-- Options -->
            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                        <h2 class="govuk-fieldset__heading">Voting options</h2>
                    </legend>
                    <div id="options-hint" class="govuk-hint">Add at least 2 options for voters to choose from</div>

                    <div id="poll-options">
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="option-1">Option 1</label>
                            <input type="text" name="options[]" id="option-1" class="govuk-input" placeholder="Enter option" required>
                        </div>
                        <div class="govuk-form-group">
                            <label class="govuk-label" for="option-2">Option 2</label>
                            <input type="text" name="options[]" id="option-2" class="govuk-input" placeholder="Enter option" required>
                        </div>
                    </div>

                    <button type="button" class="govuk-button govuk-button--secondary govuk-!-margin-top-2" data-module="govuk-button" onclick="addOption()">
                        <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i> Add another option
                    </button>
                </fieldset>
            </div>

            <!-- End Date -->
            <div class="govuk-form-group">
                <label class="govuk-label" for="end_date">
                    End date <span class="govuk-hint govuk-!-display-inline">(optional)</span>
                </label>
                <div id="end-date-hint" class="govuk-hint">When should voting close?</div>
                <input type="date" name="end_date" id="end_date" class="govuk-input govuk-input--width-10" aria-describedby="end-date-hint">
            </div>

            <button type="submit" class="govuk-button" data-module="govuk-button">
                <i class="fa-solid fa-paper-plane govuk-!-margin-right-1" aria-hidden="true"></i>
                Publish poll
            </button>
        </form>

    </div>
</div>

<script>
var optionCount = 2;
function addOption() {
    optionCount++;
    var container = document.getElementById('poll-options');
    var div = document.createElement('div');
    div.className = 'govuk-form-group';
    div.innerHTML = '<label class="govuk-label" for="option-' + optionCount + '">Option ' + optionCount + '</label>' +
                    '<input type="text" name="options[]" id="option-' + optionCount + '" class="govuk-input" placeholder="Enter option">';
    container.appendChild(div);
}
</script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>

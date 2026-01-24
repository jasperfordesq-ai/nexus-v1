<?php

/**
 * Component: Rich Text Editor
 *
 * WYSIWYG text editor wrapper.
 * Used on: settings, profile edit, compose, admin newsletters, admin pages, admin blog
 *
 * @param string $name Input name attribute (required)
 * @param string $id Element ID (default: auto-generated)
 * @param string $label Label text
 * @param string $value Current content (HTML)
 * @param string $placeholder Placeholder text
 * @param bool $required Required field (default: false)
 * @param int $minHeight Minimum height in px (default: 200)
 * @param int $maxHeight Maximum height in px (default: 500)
 * @param array $toolbar Toolbar buttons to show (default: full)
 * @param string $helpText Help text below editor
 * @param string $error Error message
 * @param string $class Additional CSS classes
 * @param string $variant Variant: 'full', 'basic', 'minimal' (default: 'full')
 */

$name = $name ?? '';
$id = $id ?? 'editor-' . md5($name . microtime());
$label = $label ?? '';
$value = $value ?? '';
$placeholder = $placeholder ?? 'Start typing...';
$required = $required ?? false;
$minHeight = $minHeight ?? 200;
$maxHeight = $maxHeight ?? 500;
$toolbar = $toolbar ?? null;
$helpText = $helpText ?? '';
$error = $error ?? '';
$class = $class ?? '';
$variant = $variant ?? 'full';

// Toolbar configurations by variant
$toolbarConfigs = [
    'minimal' => ['bold', 'italic', 'link'],
    'basic' => ['bold', 'italic', 'underline', '|', 'bulletList', 'numberedList', '|', 'link'],
    'full' => ['bold', 'italic', 'underline', 'strikethrough', '|', 'heading', '|', 'bulletList', 'numberedList', '|', 'link', 'image', '|', 'blockquote', 'code', '|', 'undo', 'redo'],
];
$activeToolbar = $toolbar ?? $toolbarConfigs[$variant] ?? $toolbarConfigs['full'];

$wrapperClass = trim('component-rich-editor ' . $class);
$toolbarClass = 'component-rich-editor__toolbar';
$contentClass = 'component-rich-editor__content';
if ($error) {
    $toolbarClass .= ' component-rich-editor__toolbar--error';
    $contentClass .= ' component-rich-editor__content--error';
}
?>

<div class="<?= htmlspecialchars($wrapperClass) ?>" id="<?= htmlspecialchars($id) ?>-wrapper">
    <?php if ($label): ?>
        <label for="<?= htmlspecialchars($id) ?>" class="component-rich-editor__label">
            <?= htmlspecialchars($label) ?>
            <?php if ($required): ?>
                <span class="component-rich-editor__required">*</span>
            <?php endif; ?>
        </label>
    <?php endif; ?>

    <!-- Toolbar -->
    <div class="<?= htmlspecialchars($toolbarClass) ?>" id="<?= htmlspecialchars($id) ?>-toolbar">
        <?php foreach ($activeToolbar as $button): ?>
            <?php if ($button === '|'): ?>
                <span class="component-rich-editor__separator"></span>
            <?php else: ?>
                <?php
                $buttonConfig = [
                    'bold' => ['icon' => 'bold', 'title' => 'Bold (Ctrl+B)', 'cmd' => 'bold'],
                    'italic' => ['icon' => 'italic', 'title' => 'Italic (Ctrl+I)', 'cmd' => 'italic'],
                    'underline' => ['icon' => 'underline', 'title' => 'Underline (Ctrl+U)', 'cmd' => 'underline'],
                    'strikethrough' => ['icon' => 'strikethrough', 'title' => 'Strikethrough', 'cmd' => 'strikeThrough'],
                    'heading' => ['icon' => 'heading', 'title' => 'Heading', 'cmd' => 'heading'],
                    'bulletList' => ['icon' => 'list-ul', 'title' => 'Bullet List', 'cmd' => 'insertUnorderedList'],
                    'numberedList' => ['icon' => 'list-ol', 'title' => 'Numbered List', 'cmd' => 'insertOrderedList'],
                    'link' => ['icon' => 'link', 'title' => 'Insert Link', 'cmd' => 'link'],
                    'image' => ['icon' => 'image', 'title' => 'Insert Image', 'cmd' => 'image'],
                    'blockquote' => ['icon' => 'quote-left', 'title' => 'Blockquote', 'cmd' => 'formatBlock'],
                    'code' => ['icon' => 'code', 'title' => 'Code', 'cmd' => 'code'],
                    'undo' => ['icon' => 'undo', 'title' => 'Undo (Ctrl+Z)', 'cmd' => 'undo'],
                    'redo' => ['icon' => 'redo', 'title' => 'Redo (Ctrl+Y)', 'cmd' => 'redo'],
                ];
                $btn = $buttonConfig[$button] ?? null;
                if ($btn):
                ?>
                <button
                    type="button"
                    class="component-rich-editor__btn"
                    data-cmd="<?= htmlspecialchars($btn['cmd']) ?>"
                    title="<?= htmlspecialchars($btn['title']) ?>"
                    onclick="execEditorCommand('<?= htmlspecialchars($id) ?>', '<?= htmlspecialchars($btn['cmd']) ?>')"
                >
                    <i class="fa-solid fa-<?= htmlspecialchars($btn['icon']) ?>"></i>
                </button>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Editor Content Area - min/max height are dynamic values passed as props -->
    <div
        class="<?= htmlspecialchars($contentClass) ?>"
        id="<?= htmlspecialchars($id) ?>"
        contenteditable="true"
        data-placeholder="<?= htmlspecialchars($placeholder) ?>"
        data-min-height="<?= (int)$minHeight ?>"
        data-max-height="<?= (int)$maxHeight ?>"
    ><?= $value ?></div>

    <!-- Hidden textarea for form submission -->
    <textarea
        name="<?= htmlspecialchars($name) ?>"
        id="<?= htmlspecialchars($id) ?>-input"
        class="component-hidden"
        <?= $required ? 'required' : '' ?>
    ><?= htmlspecialchars($value) ?></textarea>

    <?php if ($error): ?>
        <p class="component-rich-editor__error">
            <i class="fa-solid fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </p>
    <?php elseif ($helpText): ?>
        <p class="component-rich-editor__help">
            <?= htmlspecialchars($helpText) ?>
        </p>
    <?php endif; ?>
</div>

<script>
(function() {
    const editor = document.getElementById('<?= htmlspecialchars($id) ?>');
    const hiddenInput = document.getElementById('<?= htmlspecialchars($id) ?>-input');

    // Apply dynamic heights from data attributes (avoiding inline styles)
    const minHeight = editor.dataset.minHeight;
    const maxHeight = editor.dataset.maxHeight;
    if (minHeight) editor.style.minHeight = minHeight + 'px';
    if (maxHeight) editor.style.maxHeight = maxHeight + 'px';

    // Sync content to hidden input on change
    editor.addEventListener('input', function() {
        hiddenInput.value = editor.innerHTML;
    });

    // Sync on form submit
    const form = editor.closest('form');
    if (form) {
        form.addEventListener('submit', function() {
            hiddenInput.value = editor.innerHTML;
        });
    }

    // Keyboard shortcuts
    editor.addEventListener('keydown', function(e) {
        if (e.ctrlKey || e.metaKey) {
            switch (e.key.toLowerCase()) {
                case 'b':
                    e.preventDefault();
                    document.execCommand('bold', false, null);
                    break;
                case 'i':
                    e.preventDefault();
                    document.execCommand('italic', false, null);
                    break;
                case 'u':
                    e.preventDefault();
                    document.execCommand('underline', false, null);
                    break;
            }
        }
    });
})();

function execEditorCommand(editorId, cmd) {
    const editor = document.getElementById(editorId);
    editor.focus();

    if (cmd === 'link') {
        const url = prompt('Enter URL:');
        if (url) {
            document.execCommand('createLink', false, url);
        }
    } else if (cmd === 'image') {
        const url = prompt('Enter image URL:');
        if (url) {
            document.execCommand('insertImage', false, url);
        }
    } else if (cmd === 'heading') {
        document.execCommand('formatBlock', false, '<h3>');
    } else if (cmd === 'formatBlock') {
        document.execCommand('formatBlock', false, '<blockquote>');
    } else if (cmd === 'code') {
        const selection = window.getSelection();
        if (selection.rangeCount > 0) {
            const range = selection.getRangeAt(0);
            const code = document.createElement('code');
            code.appendChild(range.extractContents());
            range.insertNode(code);
        }
    } else {
        document.execCommand(cmd, false, null);
    }

    // Update hidden input
    const hiddenInput = document.getElementById(editorId + '-input');
    if (hiddenInput) {
        hiddenInput.value = editor.innerHTML;
    }
}
</script>

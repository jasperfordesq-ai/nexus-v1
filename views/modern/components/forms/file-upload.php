<?php

/**
 * Component: File Upload
 *
 * File upload input with drag-and-drop support.
 * Used on: settings, onboarding, compose, groups, listings, resources
 *
 * @param string $name Input name attribute (required)
 * @param string $id Element ID (default: auto-generated)
 * @param string $label Label text
 * @param string $accept Accepted file types (e.g., 'image/*', '.pdf,.doc')
 * @param bool $multiple Allow multiple files (default: false)
 * @param int $maxSize Max file size in MB (default: 10)
 * @param string $currentFile URL of currently uploaded file (for edit forms)
 * @param string $currentFileName Name of current file
 * @param bool $required Required field (default: false)
 * @param bool $showPreview Show image preview (default: true for images)
 * @param string $previewType Type of preview: 'image', 'icon', 'none' (default: auto)
 * @param string $helpText Help text below input
 * @param string $dropzoneText Text in dropzone (default: 'Drag & drop or click to upload')
 * @param string $class Additional CSS classes
 * @param string $variant Variant: 'default', 'avatar', 'banner' (default: 'default')
 */

$name = $name ?? '';
$id = $id ?? 'file-upload-' . md5($name . microtime());
$label = $label ?? '';
$accept = $accept ?? '*/*';
$multiple = $multiple ?? false;
$maxSize = $maxSize ?? 10;
$currentFile = $currentFile ?? '';
$currentFileName = $currentFileName ?? '';
$required = $required ?? false;
$showPreview = $showPreview ?? (strpos($accept, 'image') !== false);
$previewType = $previewType ?? ($showPreview ? 'image' : 'icon');
$helpText = $helpText ?? '';
$dropzoneText = $dropzoneText ?? 'Drag & drop or click to upload';
$class = $class ?? '';
$variant = $variant ?? 'default';

$wrapperClass = trim('component-file-upload ' . $class);
$dropzoneClass = 'component-file-upload__dropzone component-file-upload__dropzone--' . $variant;
$previewClass = 'component-file-upload__preview';
if ($variant === 'avatar') {
    $previewClass .= ' component-file-upload__preview--avatar';
}
?>

<div class="<?= htmlspecialchars($wrapperClass) ?>" id="<?= htmlspecialchars($id) ?>-wrapper">
    <?php if ($label): ?>
        <label class="component-file-upload__label">
            <?= htmlspecialchars($label) ?>
            <?php if ($required): ?>
                <span class="component-file-upload__required">*</span>
            <?php endif; ?>
        </label>
    <?php endif; ?>

    <div
        class="<?= htmlspecialchars($dropzoneClass) ?>"
        id="<?= htmlspecialchars($id) ?>-dropzone"
    >
        <input
            type="file"
            name="<?= htmlspecialchars($name) ?><?= $multiple ? '[]' : '' ?>"
            id="<?= htmlspecialchars($id) ?>"
            accept="<?= htmlspecialchars($accept) ?>"
            <?= $multiple ? 'multiple' : '' ?>
            <?= $required && !$currentFile ? 'required' : '' ?>
            class="component-file-upload__input"
        >

        <!-- Preview area (shown when file selected or currentFile exists) -->
        <div class="<?= htmlspecialchars($previewClass) ?> <?= $currentFile ? '' : 'component-hidden' ?>" id="<?= htmlspecialchars($id) ?>-preview">
            <?php if ($currentFile && $previewType === 'image'): ?>
                <img src="<?= htmlspecialchars($currentFile) ?>" alt="Preview" class="component-file-upload__preview-img <?= $variant === 'avatar' ? 'component-file-upload__preview-img--avatar' : '' ?>">
            <?php elseif ($currentFile): ?>
                <div class="component-file-upload__file-info">
                    <i class="fa-solid fa-file component-file-upload__file-icon"></i>
                    <p class="component-file-upload__file-name"><?= htmlspecialchars($currentFileName ?: basename($currentFile)) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Upload prompt (shown when no file) -->
        <div class="component-file-upload__prompt <?= $currentFile ? 'component-hidden' : '' ?>" id="<?= htmlspecialchars($id) ?>-prompt">
            <i class="fa-solid fa-cloud-arrow-up component-file-upload__prompt-icon"></i>
            <p class="component-file-upload__prompt-text"><?= htmlspecialchars($dropzoneText) ?></p>
            <p class="component-file-upload__prompt-hint">
                Max <?= $maxSize ?>MB
                <?php if ($accept !== '*/*'): ?>
                    &bull; <?= htmlspecialchars($accept) ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- Remove button (shown when file exists) -->
        <button
            type="button"
            class="component-file-upload__remove <?= $currentFile ? '' : 'component-hidden' ?>"
            id="<?= htmlspecialchars($id) ?>-remove"
            title="Remove file"
        >
            <i class="fa-solid fa-times"></i>
        </button>
    </div>

    <!-- File list (for multiple files) -->
    <?php if ($multiple): ?>
        <div class="component-file-upload__list" id="<?= htmlspecialchars($id) ?>-list"></div>
    <?php endif; ?>

    <?php if ($helpText): ?>
        <p class="component-file-upload__help">
            <?= htmlspecialchars($helpText) ?>
        </p>
    <?php endif; ?>
</div>

<script>
(function() {
    const wrapper = document.getElementById('<?= htmlspecialchars($id) ?>-wrapper');
    const dropzone = document.getElementById('<?= htmlspecialchars($id) ?>-dropzone');
    const input = document.getElementById('<?= htmlspecialchars($id) ?>');
    const preview = document.getElementById('<?= htmlspecialchars($id) ?>-preview');
    const prompt = document.getElementById('<?= htmlspecialchars($id) ?>-prompt');
    const removeBtn = document.getElementById('<?= htmlspecialchars($id) ?>-remove');
    const maxSize = <?= $maxSize ?> * 1024 * 1024;
    const previewType = '<?= $previewType ?>';
    const isAvatar = <?= $variant === 'avatar' ? 'true' : 'false' ?>;

    // Drag and drop
    ['dragenter', 'dragover'].forEach(e => {
        dropzone.addEventListener(e, function(evt) {
            evt.preventDefault();
            dropzone.classList.add('component-file-upload__dropzone--dragover');
        });
    });

    ['dragleave', 'drop'].forEach(e => {
        dropzone.addEventListener(e, function(evt) {
            evt.preventDefault();
            dropzone.classList.remove('component-file-upload__dropzone--dragover');
        });
    });

    dropzone.addEventListener('drop', function(e) {
        const files = e.dataTransfer.files;
        if (files.length) {
            input.files = files;
            handleFiles(files);
        }
    });

    // File input change
    input.addEventListener('change', function() {
        if (this.files.length) {
            handleFiles(this.files);
        }
    });

    // Remove button
    removeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        input.value = '';
        preview.classList.add('component-hidden');
        preview.innerHTML = '';
        prompt.classList.remove('component-hidden');
        removeBtn.classList.add('component-hidden');
    });

    function handleFiles(files) {
        const file = files[0];

        // Size check
        if (file.size > maxSize) {
            alert('File too large. Maximum size is <?= $maxSize ?>MB.');
            input.value = '';
            return;
        }

        // Show preview
        if (previewType === 'image' && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const imgClass = isAvatar ? 'component-file-upload__preview-img component-file-upload__preview-img--avatar' : 'component-file-upload__preview-img';
                preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" class="' + imgClass + '">';
                preview.classList.remove('component-hidden');
                prompt.classList.add('component-hidden');
                removeBtn.classList.remove('component-hidden');
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = '<div class="component-file-upload__file-info"><i class="fa-solid fa-file component-file-upload__file-icon"></i><p class="component-file-upload__file-name">' + file.name + '</p></div>';
            preview.classList.remove('component-hidden');
            prompt.classList.add('component-hidden');
            removeBtn.classList.remove('component-hidden');
        }
    }
})();
</script>

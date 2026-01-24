<?php

/**
 * Component: Share Button
 *
 * Social sharing button with multiple platforms.
 * Used on: post cards, blog, pages builder, auth login
 *
 * @param string $url URL to share (required)
 * @param string $title Title for sharing
 * @param string $description Description for sharing
 * @param string $id Element ID (default: auto-generated)
 * @param string $label Button label (default: 'Share')
 * @param array $platforms Platforms to show (default: all)
 * @param string $variant Variant: 'button', 'icon', 'dropdown' (default: 'dropdown')
 * @param string $size Size: 'sm', 'md', 'lg' (default: 'md')
 * @param string $class Additional CSS classes
 * @param bool $showLabels Show platform labels in dropdown (default: true)
 * @param bool $useNativeShare Use native share API when available (default: true)
 */

$url = $url ?? '';
$title = $title ?? '';
$description = $description ?? '';
$id = $id ?? 'share-' . md5($url . microtime());
$label = $label ?? 'Share';
$platforms = $platforms ?? ['facebook', 'twitter', 'linkedin', 'whatsapp', 'email', 'copy'];
$variant = $variant ?? 'dropdown';
$size = $size ?? 'md';
$class = $class ?? '';
$showLabels = $showLabels ?? true;
$useNativeShare = $useNativeShare ?? true;

if (empty($url)) {
    return;
}

// Encode for URLs
$encodedUrl = urlencode($url);
$encodedTitle = urlencode($title);
$encodedDesc = urlencode($description);

// Platform configurations with CSS classes for colors
$platformConfig = [
    'facebook' => [
        'name' => 'Facebook',
        'icon' => 'facebook-f',
        'class' => 'component-share__platform--facebook',
        'url' => "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}",
    ],
    'twitter' => [
        'name' => 'X (Twitter)',
        'icon' => 'x-twitter',
        'class' => 'component-share__platform--twitter',
        'url' => "https://twitter.com/intent/tweet?url={$encodedUrl}&text={$encodedTitle}",
    ],
    'linkedin' => [
        'name' => 'LinkedIn',
        'icon' => 'linkedin-in',
        'class' => 'component-share__platform--linkedin',
        'url' => "https://www.linkedin.com/sharing/share-offsite/?url={$encodedUrl}",
    ],
    'whatsapp' => [
        'name' => 'WhatsApp',
        'icon' => 'whatsapp',
        'class' => 'component-share__platform--whatsapp',
        'url' => "https://wa.me/?text={$encodedTitle}%20{$encodedUrl}",
    ],
    'telegram' => [
        'name' => 'Telegram',
        'icon' => 'telegram',
        'class' => 'component-share__platform--telegram',
        'url' => "https://t.me/share/url?url={$encodedUrl}&text={$encodedTitle}",
    ],
    'reddit' => [
        'name' => 'Reddit',
        'icon' => 'reddit-alien',
        'class' => 'component-share__platform--reddit',
        'url' => "https://www.reddit.com/submit?url={$encodedUrl}&title={$encodedTitle}",
    ],
    'email' => [
        'name' => 'Email',
        'icon' => 'envelope',
        'class' => 'component-share__platform--email',
        'url' => "mailto:?subject={$encodedTitle}&body={$encodedDesc}%0A%0A{$encodedUrl}",
        'solid' => true,
    ],
    'copy' => [
        'name' => 'Copy Link',
        'icon' => 'link',
        'class' => 'component-share__platform--copy',
        'url' => '#copy',
        'solid' => true,
    ],
];

// Size classes
$sizeClasses = [
    'sm' => 'component-share--sm',
    'md' => 'component-share--md',
    'lg' => 'component-share--lg',
];
$sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];

// Variant classes
$variantClasses = [
    'dropdown' => 'component-share--dropdown',
    'icon' => 'component-share--icon',
    'button' => 'component-share--buttons',
];
$variantClass = $variantClasses[$variant] ?? $variantClasses['dropdown'];

$cssClass = trim('component-share ' . $sizeClass . ' ' . $variantClass . ' ' . $class);
?>

<div class="<?= htmlspecialchars($cssClass) ?>" id="<?= htmlspecialchars($id) ?>-wrapper">
    <?php if ($variant === 'dropdown'): ?>
        <button
            type="button"
            class="component-share__trigger nexus-smart-btn nexus-smart-btn-outline"
            id="<?= htmlspecialchars($id) ?>-trigger"
            onclick="toggleShareDropdown('<?= htmlspecialchars($id) ?>')"
        >
            <i class="fa-solid fa-share-nodes"></i>
            <span><?= htmlspecialchars($label) ?></span>
        </button>

        <div class="component-share__dropdown component-hidden" id="<?= htmlspecialchars($id) ?>-dropdown">
            <?php foreach ($platforms as $platform): ?>
                <?php $config = $platformConfig[$platform] ?? null; if (!$config) continue; ?>
                <?php if ($platform === 'copy'): ?>
                    <button
                        type="button"
                        class="component-share__option <?= htmlspecialchars($config['class']) ?>"
                        onclick="copyShareLink('<?= htmlspecialchars($id) ?>', '<?= htmlspecialchars($url) ?>')"
                    >
                        <i class="fa-solid fa-<?= htmlspecialchars($config['icon']) ?> component-share__option-icon"></i>
                        <?php if ($showLabels): ?>
                            <span class="component-share__option-label"><?= htmlspecialchars($config['name']) ?></span>
                        <?php endif; ?>
                    </button>
                <?php else: ?>
                    <a
                        href="<?= htmlspecialchars($config['url']) ?>"
                        class="component-share__option <?= htmlspecialchars($config['class']) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        onclick="closeShareDropdown('<?= htmlspecialchars($id) ?>')"
                    >
                        <i class="fa-<?= !empty($config['solid']) ? 'solid' : 'brands' ?> fa-<?= htmlspecialchars($config['icon']) ?> component-share__option-icon"></i>
                        <?php if ($showLabels): ?>
                            <span class="component-share__option-label"><?= htmlspecialchars($config['name']) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

    <?php elseif ($variant === 'icon'): ?>
        <button
            type="button"
            class="component-share__icon-btn"
            id="<?= htmlspecialchars($id) ?>-trigger"
            onclick="handleShare('<?= htmlspecialchars($id) ?>', '<?= htmlspecialchars($url) ?>', '<?= htmlspecialchars($title) ?>', '<?= htmlspecialchars($description) ?>')"
            title="<?= htmlspecialchars($label) ?>"
        >
            <i class="fa-solid fa-share-nodes"></i>
        </button>

    <?php else: ?>
        <!-- Inline buttons -->
        <div class="component-share__buttons">
            <?php foreach ($platforms as $platform): ?>
                <?php $config = $platformConfig[$platform] ?? null; if (!$config) continue; ?>
                <?php if ($platform === 'copy'): ?>
                    <button
                        type="button"
                        class="component-share__btn <?= htmlspecialchars($config['class']) ?>"
                        onclick="copyShareLink('<?= htmlspecialchars($id) ?>', '<?= htmlspecialchars($url) ?>')"
                        title="<?= htmlspecialchars($config['name']) ?>"
                    >
                        <i class="fa-solid fa-<?= htmlspecialchars($config['icon']) ?>"></i>
                    </button>
                <?php else: ?>
                    <a
                        href="<?= htmlspecialchars($config['url']) ?>"
                        class="component-share__btn <?= htmlspecialchars($config['class']) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        title="<?= htmlspecialchars($config['name']) ?>"
                    >
                        <i class="fa-<?= !empty($config['solid']) ? 'solid' : 'brands' ?> fa-<?= htmlspecialchars($config['icon']) ?>"></i>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleShareDropdown(id) {
    const dropdown = document.getElementById(id + '-dropdown');
    const isOpen = !dropdown.classList.contains('component-hidden');

    if (isOpen) {
        dropdown.classList.add('component-hidden');
    } else {
        dropdown.classList.remove('component-hidden');
        // Close on outside click
        setTimeout(function() {
            document.addEventListener('click', function closeDropdown(e) {
                if (!e.target.closest('#' + id + '-wrapper')) {
                    dropdown.classList.add('component-hidden');
                    document.removeEventListener('click', closeDropdown);
                }
            });
        }, 0);
    }
}

function closeShareDropdown(id) {
    const dropdown = document.getElementById(id + '-dropdown');
    dropdown.classList.add('component-hidden');
}

function copyShareLink(id, url) {
    navigator.clipboard.writeText(url).then(function() {
        if (typeof showToast === 'function') {
            showToast('Link copied to clipboard', 'success');
        } else {
            alert('Link copied to clipboard!');
        }
        closeShareDropdown(id);
    }).catch(function() {
        alert('Failed to copy link');
    });
}

<?php if ($useNativeShare): ?>
function handleShare(id, url, title, description) {
    if (navigator.share) {
        navigator.share({
            title: title,
            text: description,
            url: url
        }).catch(function(err) {
            if (err.name !== 'AbortError') {
                toggleShareDropdown(id);
            }
        });
    } else {
        toggleShareDropdown(id);
    }
}
<?php endif; ?>
</script>

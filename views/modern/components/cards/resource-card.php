<?php

/**
 * Component: Resource Card
 *
 * Card for displaying shared resources (files, documents, links).
 *
 * @param array $resource Resource data with keys: id, title, description, type, thumbnail, file_size, downloads, user, created_at
 * @param bool $showDownloads Show download count (default: true)
 * @param bool $showUser Show uploader info (default: true)
 * @param string $class Additional CSS classes
 * @param string $baseUrl Base URL for resource links (default: '')
 */

$resource = $resource ?? [];
$showDownloads = $showDownloads ?? true;
$showUser = $showUser ?? true;
$class = $class ?? '';
$baseUrl = $baseUrl ?? '';

// Extract resource data with defaults
$id = $resource['id'] ?? 0;
$title = $resource['title'] ?? 'Untitled Resource';
$description = $resource['description'] ?? '';
$type = $resource['type'] ?? $resource['file_type'] ?? 'file'; // 'pdf', 'doc', 'image', 'video', 'link', 'file'
$thumbnail = $resource['thumbnail'] ?? '';
$fileSize = $resource['file_size'] ?? 0;
$downloads = $resource['downloads'] ?? $resource['download_count'] ?? 0;
$user = $resource['user'] ?? [];
$createdAt = $resource['created_at'] ?? '';

$resourceUrl = $baseUrl . '/resources/' . $id;
$cssClass = trim('glass-resource-card ' . $class);

// Type icons
$typeIcons = [
    'pdf' => 'file-pdf',
    'doc' => 'file-word',
    'image' => 'file-image',
    'video' => 'file-video',
    'link' => 'link',
    'file' => 'file',
];
$typeIcon = $typeIcons[$type] ?? 'file';

// Format file size
$formatSize = function($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
};
?>

<article class="<?= e($cssClass) ?>">
    <div class="resource-icon">
        <?php if ($thumbnail): ?>
            <?= webp_image($thumbnail, e($title), 'resource-thumbnail') ?>
        <?php else: ?>
            <i class="fa-solid fa-<?= e($typeIcon) ?>"></i>
        <?php endif; ?>
    </div>

    <div class="resource-content">
        <h3 class="resource-title">
            <a href="<?= e($resourceUrl) ?>"><?= e($title) ?></a>
        </h3>

        <?php if ($description): ?>
            <p class="resource-description"><?= e(mb_strimwidth(strip_tags($description), 0, 80, '...')) ?></p>
        <?php endif; ?>

        <div class="resource-meta">
            <span class="resource-type-badge"><?= strtoupper(e($type)) ?></span>
            <?php if ($fileSize > 0): ?>
                <span class="resource-size"><?= $formatSize($fileSize) ?></span>
            <?php endif; ?>
            <?php if ($showDownloads && $downloads > 0): ?>
                <span class="resource-downloads">
                    <i class="fa-solid fa-download"></i> <?= number_format($downloads) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($showUser && !empty($user)): ?>
        <div class="resource-user">
            <?= webp_avatar($user['avatar'] ?? '', $user['name'] ?? '', 28) ?>
        </div>
    <?php endif; ?>
</article>

<?php

/**
 * Component: Video Embed
 *
 * Embeds video from YouTube, Vimeo, or direct URL.
 * Used on: feed, compose, resources, admin pages builder, newsletter forms
 *
 * @param string $url Video URL (YouTube, Vimeo, or direct video file)
 * @param string $id Element ID (default: auto-generated)
 * @param string $title Video title for accessibility
 * @param string $aspectRatio Aspect ratio: '16:9', '4:3', '1:1', '21:9' (default: '16:9')
 * @param bool $autoplay Autoplay video (default: false)
 * @param bool $muted Start muted (default: false for embeds, true if autoplay)
 * @param bool $loop Loop video (default: false)
 * @param bool $controls Show controls (default: true)
 * @param string $poster Poster image URL (for direct video)
 * @param bool $lazy Lazy load (default: true)
 * @param string $class Additional CSS classes
 * @param int $maxWidth Max width in px (default: none) - kept as inline for truly dynamic value
 */

$url = $url ?? '';
$id = $id ?? 'video-' . md5($url . microtime());
$title = $title ?? 'Embedded video';
$aspectRatio = $aspectRatio ?? '16:9';
$autoplay = $autoplay ?? false;
$muted = $muted ?? ($autoplay ? true : false);
$loop = $loop ?? false;
$controls = $controls ?? true;
$poster = $poster ?? '';
$lazy = $lazy ?? true;
$class = $class ?? '';
$maxWidth = $maxWidth ?? 0;

if (empty($url)) {
    return;
}

// Aspect ratio classes
$aspectClasses = [
    '16:9' => 'component-video--16-9',
    '4:3' => 'component-video--4-3',
    '1:1' => 'component-video--1-1',
    '21:9' => 'component-video--21-9',
];
$aspectClass = $aspectClasses[$aspectRatio] ?? $aspectClasses['16:9'];

// Detect video type and extract ID
$videoType = 'direct';
$videoId = '';
$embedUrl = '';

// YouTube
if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches)) {
    $videoType = 'youtube';
    $videoId = $matches[1];
    $params = [];
    if ($autoplay) $params[] = 'autoplay=1';
    if ($muted) $params[] = 'mute=1';
    if ($loop) $params[] = 'loop=1&playlist=' . $videoId;
    if (!$controls) $params[] = 'controls=0';
    $params[] = 'rel=0';
    $embedUrl = 'https://www.youtube.com/embed/' . $videoId . '?' . implode('&', $params);
}
// Vimeo
elseif (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $matches)) {
    $videoType = 'vimeo';
    $videoId = $matches[1];
    $params = [];
    if ($autoplay) $params[] = 'autoplay=1';
    if ($muted) $params[] = 'muted=1';
    if ($loop) $params[] = 'loop=1';
    $embedUrl = 'https://player.vimeo.com/video/' . $videoId . '?' . implode('&', $params);
}
// Direct video file
else {
    $videoType = 'direct';
}

$wrapperClass = trim('component-video ' . $class);
$maxWidthStyle = $maxWidth ? "max-width: {$maxWidth}px;" : '';
?>

<div class="<?= htmlspecialchars($wrapperClass) ?>" id="<?= htmlspecialchars($id) ?>" <?php if ($maxWidthStyle): ?>style="<?= $maxWidthStyle ?>"<?php endif; ?>>
    <div class="component-video__container <?= $aspectClass ?>">
        <?php if ($videoType === 'youtube' || $videoType === 'vimeo'): ?>
            <?php if ($lazy): ?>
                <!-- Lazy load with thumbnail -->
                <div class="component-video__placeholder" id="<?= htmlspecialchars($id) ?>-placeholder">
                    <?php if ($videoType === 'youtube'): ?>
                        <img
                            src="https://img.youtube.com/vi/<?= htmlspecialchars($videoId) ?>/maxresdefault.jpg"
                            alt="<?= htmlspecialchars($title) ?>"
                            class="component-video__thumbnail"
                            onerror="this.src='https://img.youtube.com/vi/<?= htmlspecialchars($videoId) ?>/hqdefault.jpg'"
                        >
                    <?php endif; ?>
                    <button type="button" class="component-video__play-btn" onclick="loadVideoEmbed('<?= htmlspecialchars($id) ?>', '<?= htmlspecialchars($embedUrl) ?>&autoplay=1')">
                        <i class="fa-solid fa-play"></i>
                    </button>
                </div>
                <iframe
                    id="<?= htmlspecialchars($id) ?>-iframe"
                    class="component-video__iframe component-hidden"
                    title="<?= htmlspecialchars($title) ?>"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                ></iframe>
            <?php else: ?>
                <!-- Direct embed -->
                <iframe
                    src="<?= htmlspecialchars($embedUrl) ?>"
                    class="component-video__iframe"
                    title="<?= htmlspecialchars($title) ?>"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                    <?= $lazy ? 'loading="lazy"' : '' ?>
                ></iframe>
            <?php endif; ?>

        <?php else: ?>
            <!-- Direct video file -->
            <video
                class="component-video__player"
                <?= $controls ? 'controls' : '' ?>
                <?= $autoplay ? 'autoplay' : '' ?>
                <?= $muted ? 'muted' : '' ?>
                <?= $loop ? 'loop' : '' ?>
                <?= $poster ? 'poster="' . htmlspecialchars($poster) . '"' : '' ?>
                playsinline
            >
                <source src="<?= htmlspecialchars($url) ?>" type="video/<?= pathinfo($url, PATHINFO_EXTENSION) ?>">
                Your browser does not support the video tag.
            </video>
        <?php endif; ?>
    </div>
</div>

<?php if ($lazy && ($videoType === 'youtube' || $videoType === 'vimeo')): ?>
<script>
function loadVideoEmbed(id, embedUrl) {
    const placeholder = document.getElementById(id + '-placeholder');
    const iframe = document.getElementById(id + '-iframe');
    if (placeholder && iframe) {
        iframe.src = embedUrl;
        iframe.classList.remove('component-hidden');
        placeholder.classList.add('component-hidden');
    }
}
</script>
<?php endif; ?>

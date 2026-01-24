<?php

/**
 * Component: Gallery / Carousel
 *
 * Image gallery with lightbox and optional carousel mode.
 * Used on: home, feed, members, federation, listings, blog, admin dashboards
 *
 * @param array $images Array of images: ['src' => '', 'alt' => '', 'caption' => '']
 * @param string $id Element ID (default: auto-generated)
 * @param string $variant Variant: 'grid', 'carousel', 'masonry' (default: 'grid')
 * @param int $columns Grid columns (default: 3) - kept as inline for truly dynamic value
 * @param string $gap Gap between items (default: '12px') - kept as inline for truly dynamic value
 * @param bool $lightbox Enable lightbox on click (default: true)
 * @param bool $showCaptions Show image captions (default: false)
 * @param bool $showNav Show carousel navigation arrows (default: true)
 * @param bool $showDots Show carousel dots (default: true)
 * @param bool $autoplay Autoplay carousel (default: false)
 * @param int $autoplayInterval Autoplay interval in ms (default: 5000)
 * @param string $aspectRatio Aspect ratio: 'auto', '1:1', '4:3', '16:9' (default: 'auto')
 * @param int $maxHeight Max image height in px (default: 400) - kept as inline for truly dynamic value
 * @param string $class Additional CSS classes
 */

$images = $images ?? [];
$id = $id ?? 'gallery-' . md5(json_encode($images) . microtime());
$variant = $variant ?? 'grid';
$columns = $columns ?? 3;
$gap = $gap ?? '12px';
$lightbox = $lightbox ?? true;
$showCaptions = $showCaptions ?? false;
$showNav = $showNav ?? true;
$showDots = $showDots ?? true;
$autoplay = $autoplay ?? false;
$autoplayInterval = $autoplayInterval ?? 5000;
$aspectRatio = $aspectRatio ?? 'auto';
$maxHeight = $maxHeight ?? 400;
$class = $class ?? '';

if (empty($images)) {
    return;
}

// Variant classes
$variantClasses = [
    'grid' => 'component-gallery--grid',
    'carousel' => 'component-gallery--carousel',
    'masonry' => 'component-gallery--masonry',
];
$variantClass = $variantClasses[$variant] ?? $variantClasses['grid'];

// Aspect ratio classes
$aspectClasses = [
    'auto' => '',
    '1:1' => 'component-gallery__item--square',
    '4:3' => 'component-gallery__item--4-3',
    '16:9' => 'component-gallery__item--16-9',
];
$aspectClass = $aspectClasses[$aspectRatio] ?? '';

$wrapperClass = trim('component-gallery ' . $variantClass . ' ' . $class);

// Dynamic values as inline styles (acceptable per CLAUDE.md)
$gridStyle = "grid-template-columns: repeat({$columns}, 1fr); gap: {$gap};";
$maxHeightStyle = "max-height: {$maxHeight}px;";
?>

<div class="<?= htmlspecialchars($wrapperClass) ?>" id="<?= htmlspecialchars($id) ?>">
    <?php if ($variant === 'carousel'): ?>
        <!-- Carousel View -->
        <div class="component-gallery__carousel">
            <div class="component-gallery__track" id="<?= htmlspecialchars($id) ?>-track">
                <?php foreach ($images as $index => $image): ?>
                    <div class="component-gallery__slide <?= $aspectClass ?>">
                        <img
                            src="<?= htmlspecialchars($image['src'] ?? '') ?>"
                            alt="<?= htmlspecialchars($image['alt'] ?? 'Image ' . ($index + 1)) ?>"
                            class="component-gallery__image <?= $lightbox ? 'component-gallery__image--clickable' : '' ?>"
                            style="<?= $maxHeightStyle ?>"
                            <?= $lightbox ? 'onclick="openGalleryLightbox(\'' . htmlspecialchars($id) . '\', ' . $index . ')"' : '' ?>
                        >
                        <?php if ($showCaptions && !empty($image['caption'])): ?>
                            <div class="component-gallery__caption component-gallery__caption--overlay">
                                <?= htmlspecialchars($image['caption']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($showNav && count($images) > 1): ?>
                <button type="button" class="component-gallery__nav component-gallery__nav--prev" onclick="galleryCarouselPrev('<?= htmlspecialchars($id) ?>')">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <button type="button" class="component-gallery__nav component-gallery__nav--next" onclick="galleryCarouselNext('<?= htmlspecialchars($id) ?>')">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            <?php endif; ?>

            <?php if ($showDots && count($images) > 1): ?>
                <div class="component-gallery__dots">
                    <?php foreach ($images as $index => $image): ?>
                        <button
                            type="button"
                            class="component-gallery__dot <?= $index === 0 ? 'component-gallery__dot--active' : '' ?>"
                            onclick="galleryCarouselGoTo('<?= htmlspecialchars($id) ?>', <?= $index ?>)"
                        ></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- Grid View -->
        <div class="component-gallery__grid" style="<?= $gridStyle ?>">
            <?php foreach ($images as $index => $image): ?>
                <div class="component-gallery__item <?= $aspectClass ?>">
                    <img
                        src="<?= htmlspecialchars($image['src'] ?? '') ?>"
                        alt="<?= htmlspecialchars($image['alt'] ?? 'Image ' . ($index + 1)) ?>"
                        loading="lazy"
                        class="component-gallery__image <?= $lightbox ? 'component-gallery__image--clickable' : '' ?>"
                        <?= $lightbox ? 'onclick="openGalleryLightbox(\'' . htmlspecialchars($id) . '\', ' . $index . ')"' : '' ?>
                    >
                    <?php if ($showCaptions && !empty($image['caption'])): ?>
                        <div class="component-gallery__caption">
                            <?= htmlspecialchars($image['caption']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($lightbox): ?>
        <!-- Lightbox -->
        <div class="component-gallery__lightbox component-hidden" id="<?= htmlspecialchars($id) ?>-lightbox">
            <button type="button" class="component-gallery__lightbox-close" onclick="closeGalleryLightbox('<?= htmlspecialchars($id) ?>')">
                <i class="fa-solid fa-times"></i>
            </button>

            <button type="button" class="component-gallery__lightbox-nav component-gallery__lightbox-nav--prev" onclick="galleryLightboxPrev('<?= htmlspecialchars($id) ?>')">
                <i class="fa-solid fa-chevron-left"></i>
            </button>

            <img id="<?= htmlspecialchars($id) ?>-lightbox-img" class="component-gallery__lightbox-image" src="" alt="">

            <button type="button" class="component-gallery__lightbox-nav component-gallery__lightbox-nav--next" onclick="galleryLightboxNext('<?= htmlspecialchars($id) ?>')">
                <i class="fa-solid fa-chevron-right"></i>
            </button>

            <div class="component-gallery__lightbox-counter" id="<?= htmlspecialchars($id) ?>-lightbox-counter"></div>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const galleryId = '<?= htmlspecialchars($id) ?>';
    const images = <?= json_encode(array_map(function($img) { return ['src' => $img['src'] ?? '', 'alt' => $img['alt'] ?? '']; }, $images)) ?>;
    let currentIndex = 0;

    // Store in global scope for onclick handlers
    window['gallery_' + galleryId] = { images, currentIndex };

    <?php if ($variant === 'carousel' && $autoplay && count($images) > 1): ?>
    setInterval(function() {
        galleryCarouselNext(galleryId);
    }, <?= (int)$autoplayInterval ?>);
    <?php endif; ?>
})();

function galleryCarouselNext(id) {
    const track = document.getElementById(id + '-track');
    const dots = document.querySelectorAll('#' + id + ' .component-gallery__dot');
    const total = dots.length;
    let current = parseInt(track.dataset.current || 0);
    current = (current + 1) % total;
    track.style.transform = 'translateX(-' + (current * 100) + '%)';
    track.dataset.current = current;
    dots.forEach((dot, i) => {
        dot.classList.toggle('component-gallery__dot--active', i === current);
    });
}

function galleryCarouselPrev(id) {
    const track = document.getElementById(id + '-track');
    const dots = document.querySelectorAll('#' + id + ' .component-gallery__dot');
    const total = dots.length;
    let current = parseInt(track.dataset.current || 0);
    current = (current - 1 + total) % total;
    track.style.transform = 'translateX(-' + (current * 100) + '%)';
    track.dataset.current = current;
    dots.forEach((dot, i) => {
        dot.classList.toggle('component-gallery__dot--active', i === current);
    });
}

function galleryCarouselGoTo(id, index) {
    const track = document.getElementById(id + '-track');
    const dots = document.querySelectorAll('#' + id + ' .component-gallery__dot');
    track.style.transform = 'translateX(-' + (index * 100) + '%)';
    track.dataset.current = index;
    dots.forEach((dot, i) => {
        dot.classList.toggle('component-gallery__dot--active', i === index);
    });
}

function openGalleryLightbox(id, index) {
    const lightbox = document.getElementById(id + '-lightbox');
    const img = document.getElementById(id + '-lightbox-img');
    const counter = document.getElementById(id + '-lightbox-counter');
    const gallery = window['gallery_' + id];

    gallery.currentIndex = index;
    img.src = gallery.images[index].src;
    img.alt = gallery.images[index].alt;
    counter.textContent = (index + 1) + ' / ' + gallery.images.length;
    lightbox.classList.remove('component-hidden');
    document.body.classList.add('overflow-hidden');
}

function closeGalleryLightbox(id) {
    const lightbox = document.getElementById(id + '-lightbox');
    lightbox.classList.add('component-hidden');
    document.body.classList.remove('overflow-hidden');
}

function galleryLightboxNext(id) {
    const gallery = window['gallery_' + id];
    gallery.currentIndex = (gallery.currentIndex + 1) % gallery.images.length;
    updateLightboxImage(id);
}

function galleryLightboxPrev(id) {
    const gallery = window['gallery_' + id];
    gallery.currentIndex = (gallery.currentIndex - 1 + gallery.images.length) % gallery.images.length;
    updateLightboxImage(id);
}

function updateLightboxImage(id) {
    const gallery = window['gallery_' + id];
    const img = document.getElementById(id + '-lightbox-img');
    const counter = document.getElementById(id + '-lightbox-counter');
    img.src = gallery.images[gallery.currentIndex].src;
    img.alt = gallery.images[gallery.currentIndex].alt;
    counter.textContent = (gallery.currentIndex + 1) + ' / ' + gallery.images.length;
}
</script>

<?php
/**
 * Image Block Renderer
 *
 * Renders responsive images with captions
 */

namespace Nexus\PageBuilder\Renderers;

class ImageBlockRenderer implements BlockRendererInterface
{
    public function render(array $data): string
    {
        $imageUrl = htmlspecialchars($data['imageUrl'] ?? '');
        $alt = htmlspecialchars($data['alt'] ?? '');
        $caption = htmlspecialchars($data['caption'] ?? '');
        $width = htmlspecialchars($data['width'] ?? 'normal');
        $alignment = htmlspecialchars($data['alignment'] ?? 'center');
        $linkUrl = htmlspecialchars($data['linkUrl'] ?? '');

        // Width classes
        $widthClass = [
            'small' => 'pb-img-width-sm',
            'normal' => 'pb-img-width-md',
            'large' => 'pb-img-width-lg',
            'full' => 'pb-img-width-full'
        ][$width] ?? 'pb-img-width-md';

        // Alignment classes
        $alignClass = [
            'left' => 'pb-img-align-left',
            'center' => 'pb-img-align-center',
            'right' => 'pb-img-align-right'
        ][$alignment] ?? 'pb-img-align-center';

        $html = '<div class="pb-image-block ' . $widthClass . ' ' . $alignClass . '">';

        $imgTag = '<img src="' . $imageUrl . '" alt="' . $alt . '" class="pb-image" loading="lazy">';

        if ($linkUrl) {
            $html .= '<a href="' . $linkUrl . '">' . $imgTag . '</a>';
        } else {
            $html .= $imgTag;
        }

        if ($caption) {
            $html .= '<p class="pb-image-caption">' . $caption . '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    public function validate(array $data): bool
    {
        return !empty($data['imageUrl']);
    }
}

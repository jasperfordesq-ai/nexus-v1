<?php
/**
 * Rich Text Block Renderer
 *
 * Renders formatted text content
 */

namespace Nexus\PageBuilder\Renderers;

class RichTextBlockRenderer implements BlockRendererInterface
{
    public function render(array $data): string
    {
        $content = $data['content'] ?? '';
        $width = htmlspecialchars($data['width'] ?? 'normal');
        $padding = htmlspecialchars($data['padding'] ?? 'normal');

        // Width classes
        $widthClass = [
            'narrow' => 'pb-width-narrow',
            'normal' => 'pb-width-normal',
            'wide' => 'pb-width-wide',
            'full' => 'pb-width-full'
        ][$width] ?? 'pb-width-normal';

        // Padding classes
        $paddingClass = [
            'none' => 'pb-padding-none',
            'small' => 'pb-padding-sm',
            'normal' => 'pb-padding-md',
            'large' => 'pb-padding-lg'
        ][$padding] ?? 'pb-padding-md';

        $html = '<div class="pb-rich-text ' . $widthClass . ' ' . $paddingClass . '">';
        $html .= '<div class="pb-rich-text-content">' . $content . '</div>';
        $html .= '</div>';

        return $html;
    }

    public function validate(array $data): bool
    {
        // Rich text requires content
        return !empty(trim(strip_tags($data['content'] ?? '')));
    }
}

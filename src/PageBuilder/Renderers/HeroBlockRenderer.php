<?php
/**
 * Hero Block Renderer
 *
 * Renders full-width hero/banner sections
 */

namespace Nexus\PageBuilder\Renderers;

class HeroBlockRenderer implements BlockRendererInterface
{
    public function render(array $data): string
    {
        $title = htmlspecialchars($data['title'] ?? '');
        $subtitle = htmlspecialchars($data['subtitle'] ?? '');
        $backgroundImage = htmlspecialchars($data['backgroundImage'] ?? '');
        $backgroundOverlay = htmlspecialchars($data['backgroundOverlay'] ?? '0.4');
        $alignment = htmlspecialchars($data['alignment'] ?? 'center');
        $height = htmlspecialchars($data['height'] ?? 'medium');
        $buttonText = htmlspecialchars($data['buttonText'] ?? '');
        $buttonUrl = htmlspecialchars($data['buttonUrl'] ?? '');

        // Height classes
        $heightClass = [
            'small' => 'hero-sm',
            'medium' => 'hero-md',
            'large' => 'hero-lg',
            'full' => 'hero-full'
        ][$height] ?? 'hero-md';

        // Build style attribute
        $style = '';
        if ($backgroundImage) {
            $style = "background-image: linear-gradient(rgba(0,0,0,{$backgroundOverlay}), rgba(0,0,0,{$backgroundOverlay})), url('{$backgroundImage}');";
        }

        $html = '<section class="pb-hero ' . $heightClass . '" style="' . $style . '">';
        $html .= '<div class="pb-hero-content text-' . $alignment . '">';

        if ($title) {
            $html .= '<h1 class="pb-hero-title">' . $title . '</h1>';
        }

        if ($subtitle) {
            $html .= '<p class="pb-hero-subtitle">' . nl2br($subtitle) . '</p>';
        }

        if ($buttonText && $buttonUrl) {
            $html .= '<a href="' . $buttonUrl . '" class="pb-hero-button">' . $buttonText . '</a>';
        }

        $html .= '</div>';
        $html .= '</section>';

        return $html;
    }

    public function validate(array $data): bool
    {
        // Hero requires at least a title
        return !empty($data['title']);
    }
}

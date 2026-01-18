<?php
/**
 * CTA Card Block Renderer
 *
 * Renders call-to-action cards with icons
 */

namespace Nexus\PageBuilder\Renderers;

class CtaCardBlockRenderer implements BlockRendererInterface
{
    public function render(array $data): string
    {
        $cards = $data['cards'] ?? [];
        $columns = (int)($data['columns'] ?? 3);
        $style = htmlspecialchars($data['style'] ?? 'default');

        if (empty($cards)) {
            return '<div class="pb-cta-cards-empty">No cards added yet.</div>';
        }

        $html = '<div class="pb-cta-cards pb-cta-cards-' . $style . '">';
        $html .= '<div class="pb-cta-grid columns-' . $columns . '">';

        foreach ($cards as $card) {
            $icon = htmlspecialchars($card['icon'] ?? 'fa-circle-check');
            $title = htmlspecialchars($card['title'] ?? '');
            $description = htmlspecialchars($card['description'] ?? '');
            $buttonText = htmlspecialchars($card['buttonText'] ?? '');
            $buttonUrl = htmlspecialchars($card['buttonUrl'] ?? '#');
            $iconColor = htmlspecialchars($card['iconColor'] ?? 'primary');

            if (empty($title)) {
                continue;
            }

            $html .= '<div class="pb-cta-card">';

            // Icon
            if ($icon) {
                $html .= '<div class="pb-cta-icon pb-cta-icon-' . $iconColor . '">';
                $html .= '<i class="fa-solid ' . $icon . '"></i>';
                $html .= '</div>';
            }

            // Title
            $html .= '<h3 class="pb-cta-card-title">' . $title . '</h3>';

            // Description
            if ($description) {
                $html .= '<p class="pb-cta-card-description">' . $description . '</p>';
            }

            // Button
            if ($buttonText) {
                $html .= '<a href="' . $buttonUrl . '" class="pb-cta-card-button">';
                $html .= $buttonText . ' <i class="fa-solid fa-arrow-right"></i>';
                $html .= '</a>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function validate(array $data): bool
    {
        return !empty($data['cards']) && is_array($data['cards']);
    }
}

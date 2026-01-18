<?php
/**
 * Spacer Block Renderer
 *
 * Renders vertical spacing between blocks
 */

namespace Nexus\PageBuilder\Renderers;

class SpacerBlockRenderer implements BlockRendererInterface
{
    public function render(array $data): string
    {
        $height = htmlspecialchars($data['height'] ?? 'medium');

        // Height mapping
        $heights = [
            'small' => '20px',
            'medium' => '40px',
            'large' => '60px',
            'xlarge' => '100px'
        ];

        $heightValue = $heights[$height] ?? $heights['medium'];

        return '<div class="pb-spacer" style="height: ' . $heightValue . '; width: 100%;"></div>';
    }

    public function validate(array $data): bool
    {
        return true; // Spacer always valid
    }
}

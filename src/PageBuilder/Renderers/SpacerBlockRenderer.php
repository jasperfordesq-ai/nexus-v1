<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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

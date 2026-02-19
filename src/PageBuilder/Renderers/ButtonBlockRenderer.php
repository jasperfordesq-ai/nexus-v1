<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Button Block Renderer
 *
 * Renders call-to-action buttons
 */

namespace Nexus\PageBuilder\Renderers;

class ButtonBlockRenderer implements BlockRendererInterface
{
    public function render(array $data): string
    {
        $text = htmlspecialchars($data['text'] ?? 'Click Here');
        $url = htmlspecialchars($data['url'] ?? '#');
        $style = htmlspecialchars($data['style'] ?? 'primary');
        $size = htmlspecialchars($data['size'] ?? 'medium');
        $alignment = htmlspecialchars($data['alignment'] ?? 'center');
        $openInNewTab = !empty($data['openInNewTab']);
        $icon = htmlspecialchars($data['icon'] ?? '');

        // Style classes
        $styleClass = [
            'primary' => 'pb-btn-primary',
            'secondary' => 'pb-btn-secondary',
            'outline' => 'pb-btn-outline',
            'danger' => 'pb-btn-danger'
        ][$style] ?? 'pb-btn-primary';

        // Size classes
        $sizeClass = [
            'small' => 'pb-btn-sm',
            'medium' => 'pb-btn-md',
            'large' => 'pb-btn-lg'
        ][$size] ?? 'pb-btn-md';

        // Alignment classes
        $alignClass = [
            'left' => 'pb-btn-container-left',
            'center' => 'pb-btn-container-center',
            'right' => 'pb-btn-container-right'
        ][$alignment] ?? 'pb-btn-container-center';

        $target = $openInNewTab ? ' target="_blank" rel="noopener noreferrer"' : '';

        $html = '<div class="pb-button-block ' . $alignClass . '">';
        $html .= '<a href="' . $url . '" class="pb-button ' . $styleClass . ' ' . $sizeClass . '"' . $target . '>';

        if ($icon) {
            $html .= '<i class="fa-solid fa-' . $icon . '"></i> ';
        }

        $html .= $text;
        $html .= '</a>';
        $html .= '</div>';

        return $html;
    }

    public function validate(array $data): bool
    {
        return !empty($data['text']) && !empty($data['url']);
    }
}

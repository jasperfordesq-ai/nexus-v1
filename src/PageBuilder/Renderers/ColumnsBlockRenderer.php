<?php
/**
 * Columns Block Renderer
 *
 * Renders multi-column layouts
 */

namespace Nexus\PageBuilder\Renderers;

class ColumnsBlockRenderer implements BlockRendererInterface
{
    public function render(array $data): string
    {
        $columnCount = (int)($data['columnCount'] ?? 2);
        $gap = htmlspecialchars($data['gap'] ?? 'normal');
        $columns = $data['columns'] ?? [];

        // Gap classes
        $gapClass = [
            'none' => 'pb-columns-gap-none',
            'small' => 'pb-columns-gap-sm',
            'normal' => 'pb-columns-gap-md',
            'large' => 'pb-columns-gap-lg'
        ][$gap] ?? 'pb-columns-gap-md';

        $html = '<div class="pb-columns columns-' . $columnCount . ' ' . $gapClass . '">';

        // Ensure we have the right number of columns
        for ($i = 0; $i < $columnCount; $i++) {
            $content = $columns[$i] ?? '';
            $html .= '<div class="pb-column">' . $content . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    public function validate(array $data): bool
    {
        $count = (int)($data['columnCount'] ?? 0);
        return $count >= 1 && $count <= 4;
    }
}

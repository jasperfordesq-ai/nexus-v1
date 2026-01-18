<?php
/**
 * Block Renderer Interface
 *
 * All block renderers must implement this interface
 */

namespace Nexus\PageBuilder\Renderers;

interface BlockRendererInterface
{
    /**
     * Render block HTML from data
     *
     * @param array $data Block data
     * @return string Rendered HTML
     */
    public function render(array $data): string;

    /**
     * Validate block data
     *
     * @param array $data Block data to validate
     * @return bool True if valid
     */
    public function validate(array $data): bool;
}

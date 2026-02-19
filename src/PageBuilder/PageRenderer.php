<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Page Renderer
 *
 * Renders complete pages from block data
 */

namespace Nexus\PageBuilder;

use Nexus\Core\Database;

class PageRenderer
{
    /**
     * Render all blocks for a page
     *
     * @param int $pageId
     * @return string Complete rendered HTML
     */
    public static function renderPage(int $pageId): string
    {
        // Fetch all blocks for this page, ordered by sort_order
        $blocks = Database::query(
            "SELECT block_type, block_data FROM page_blocks
             WHERE page_id = ?
             ORDER BY sort_order ASC",
            [$pageId]
        )->fetchAll();

        if (empty($blocks)) {
            return '<!-- No blocks found for this page -->';
        }

        $html = '';

        foreach ($blocks as $block) {
            $blockType = $block['block_type'];
            $blockData = json_decode($block['block_data'], true);

            if (!$blockData) {
                $html .= "<!-- Invalid block data for type '{$blockType}' -->";
                continue;
            }

            // Render the block
            $html .= BlockRegistry::render($blockType, $blockData);
            $html .= "\n";
        }

        return $html;
    }

    /**
     * Save blocks for a page
     *
     * @param int $pageId
     * @param array $blocks Array of blocks with 'type' and 'data' keys
     * @return bool Success
     */
    public static function saveBlocks(int $pageId, array $blocks): bool
    {
        try {
            // Start transaction
            Database::query("START TRANSACTION");

            // Delete existing blocks
            Database::query("DELETE FROM page_blocks WHERE page_id = ?", [$pageId]);

            // Insert new blocks
            $sortOrder = 0;
            foreach ($blocks as $block) {
                if (!isset($block['type']) || !isset($block['data'])) {
                    throw new \Exception("Block missing 'type' or 'data'");
                }

                // Validate block type exists
                if (!BlockRegistry::getBlock($block['type'])) {
                    throw new \Exception("Unknown block type: {$block['type']}");
                }

                // Validate block data
                $renderer = BlockRegistry::getRenderer($block['type']);
                if ($renderer && !$renderer->validate($block['data'])) {
                    throw new \Exception("Block validation failed for type: {$block['type']}");
                }

                Database::query(
                    "INSERT INTO page_blocks (page_id, block_type, block_data, sort_order)
                     VALUES (?, ?, ?, ?)",
                    [
                        $pageId,
                        $block['type'],
                        json_encode($block['data']),
                        $sortOrder++
                    ]
                );
            }

            // Commit transaction
            Database::query("COMMIT");

            return true;

        } catch (\Exception $e) {
            Database::query("ROLLBACK");
            error_log("PageRenderer::saveBlocks error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get blocks for a page
     *
     * @param int $pageId
     * @return array Array of blocks
     */
    public static function getBlocks(int $pageId): array
    {
        $blocks = Database::query(
            "SELECT block_type, block_data, sort_order
             FROM page_blocks
             WHERE page_id = ?
             ORDER BY sort_order ASC",
            [$pageId]
        )->fetchAll();

        $result = [];
        foreach ($blocks as $block) {
            $result[] = [
                'type' => $block['block_type'],
                'data' => json_decode($block['block_data'], true)
            ];
        }

        return $result;
    }

    /**
     * Preview a single block (for builder UI)
     *
     * @param string $type Block type
     * @param array $data Block data
     * @return string Rendered HTML
     */
    public static function previewBlock(string $type, array $data): string
    {
        return BlockRegistry::render($type, $data);
    }
}

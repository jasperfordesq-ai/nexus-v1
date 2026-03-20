<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Page Renderer - Thin delegate to App\PageBuilder\PageRenderer
 *
 * @deprecated Use App\PageBuilder\PageRenderer directly
 */

namespace Nexus\PageBuilder;

class PageRenderer
{
    public static function renderPage(int $pageId): string
    {
        return \App\PageBuilder\PageRenderer::renderPage($pageId);
    }

    public static function saveBlocks(int $pageId, array $blocks): bool
    {
        return \App\PageBuilder\PageRenderer::saveBlocks($pageId, $blocks);
    }

    public static function getBlocks(int $pageId): array
    {
        return \App\PageBuilder\PageRenderer::getBlocks($pageId);
    }

    public static function previewBlock(string $type, array $data): string
    {
        return \App\PageBuilder\PageRenderer::previewBlock($type, $data);
    }
}

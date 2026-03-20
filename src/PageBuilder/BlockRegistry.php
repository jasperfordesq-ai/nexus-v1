<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Block Registry - Thin delegate to App\PageBuilder\BlockRegistry
 *
 * @deprecated Use App\PageBuilder\BlockRegistry directly
 */

namespace Nexus\PageBuilder;

class BlockRegistry
{
    public static function register(string $type, array $config): void
    {
        \App\PageBuilder\BlockRegistry::register($type, $config);
    }

    public static function getBlock(string $type): ?array
    {
        return \App\PageBuilder\BlockRegistry::getBlock($type);
    }

    public static function getAllBlocks(): array
    {
        return \App\PageBuilder\BlockRegistry::getAllBlocks();
    }

    public static function getBlocksByCategory(string $category): array
    {
        return \App\PageBuilder\BlockRegistry::getBlocksByCategory($category);
    }

    public static function getCategories(): array
    {
        return \App\PageBuilder\BlockRegistry::getCategories();
    }

    public static function registerRenderer(string $type, object $renderer): void
    {
        \App\PageBuilder\BlockRegistry::registerRenderer($type, $renderer);
    }

    public static function getRenderer(string $type): ?object
    {
        return \App\PageBuilder\BlockRegistry::getRenderer($type);
    }

    public static function render(string $type, array $data): string
    {
        return \App\PageBuilder\BlockRegistry::render($type, $data);
    }

    public static function getDefaults(string $type): array
    {
        return \App\PageBuilder\BlockRegistry::getDefaults($type);
    }

    public static function clear(): void
    {
        \App\PageBuilder\BlockRegistry::clear();
    }
}

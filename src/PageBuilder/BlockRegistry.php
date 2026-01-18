<?php
/**
 * Block Registry - Central registry for all page builder blocks
 *
 * Manages block definitions, renderers, and validation
 */

namespace Nexus\PageBuilder;

class BlockRegistry
{
    private static $blocks = [];
    private static $renderers = [];

    /**
     * Register a new block type
     *
     * @param string $type Unique block identifier
     * @param array $config Block configuration
     */
    public static function register(string $type, array $config): void
    {
        if (isset(self::$blocks[$type])) {
            throw new \Exception("Block type '{$type}' is already registered");
        }

        // Validate config
        self::validateConfig($config);

        self::$blocks[$type] = array_merge([
            'type' => $type,
            'label' => ucfirst($type),
            'icon' => 'fa-cube',
            'category' => 'general',
            'defaults' => [],
            'fields' => [],
            'renderer' => null,
        ], $config);
    }

    /**
     * Get block configuration
     */
    public static function getBlock(string $type): ?array
    {
        return self::$blocks[$type] ?? null;
    }

    /**
     * Get all registered blocks
     */
    public static function getAllBlocks(): array
    {
        return self::$blocks;
    }

    /**
     * Get blocks by category
     */
    public static function getBlocksByCategory(string $category): array
    {
        return array_filter(self::$blocks, function($block) use ($category) {
            return $block['category'] === $category;
        });
    }

    /**
     * Get all categories
     */
    public static function getCategories(): array
    {
        $categories = array_unique(array_column(self::$blocks, 'category'));
        return array_values($categories);
    }

    /**
     * Register a block renderer
     */
    public static function registerRenderer(string $type, object $renderer): void
    {
        if (!($renderer instanceof Renderers\BlockRendererInterface)) {
            throw new \Exception("Renderer must implement BlockRendererInterface");
        }

        self::$renderers[$type] = $renderer;
    }

    /**
     * Get renderer for block type
     */
    public static function getRenderer(string $type): ?object
    {
        return self::$renderers[$type] ?? null;
    }

    /**
     * Render a block
     */
    public static function render(string $type, array $data): string
    {
        $renderer = self::getRenderer($type);

        if (!$renderer) {
            return "<!-- Block type '{$type}' has no renderer -->";
        }

        // Validate data
        if (!$renderer->validate($data)) {
            return "<!-- Block '{$type}' validation failed -->";
        }

        return $renderer->render($data);
    }

    /**
     * Validate block configuration
     */
    private static function validateConfig(array $config): void
    {
        $required = ['label', 'category'];

        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new \Exception("Block config missing required field: {$field}");
            }
        }

        // Validate fields structure
        if (isset($config['fields']) && !is_array($config['fields'])) {
            throw new \Exception("Block 'fields' must be an array");
        }

        // Validate each field definition
        if (isset($config['fields'])) {
            foreach ($config['fields'] as $fieldName => $fieldConfig) {
                if (!isset($fieldConfig['type'])) {
                    throw new \Exception("Field '{$fieldName}' missing 'type'");
                }
            }
        }
    }

    /**
     * Get default data for a block type
     */
    public static function getDefaults(string $type): array
    {
        $block = self::getBlock($type);
        return $block['defaults'] ?? [];
    }

    /**
     * Clear all registered blocks (useful for testing)
     */
    public static function clear(): void
    {
        self::$blocks = [];
        self::$renderers = [];
    }
}

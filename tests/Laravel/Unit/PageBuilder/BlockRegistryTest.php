<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\PageBuilder;

use App\PageBuilder\BlockRegistry;
use App\PageBuilder\Renderers\BlockRendererInterface;
use Mockery;
use Tests\Laravel\TestCase;

class BlockRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BlockRegistry::clear();
    }

    protected function tearDown(): void
    {
        BlockRegistry::clear();
        parent::tearDown();
    }

    public function test_register_stores_block_with_defaults(): void
    {
        BlockRegistry::register('test_block', [
            'label' => 'Test Block',
            'category' => 'general',
        ]);

        $block = BlockRegistry::getBlock('test_block');

        $this->assertNotNull($block);
        $this->assertEquals('test_block', $block['type']);
        $this->assertEquals('Test Block', $block['label']);
        $this->assertEquals('general', $block['category']);
        $this->assertEquals('fa-cube', $block['icon']);
        $this->assertEquals([], $block['defaults']);
        $this->assertEquals([], $block['fields']);
        $this->assertNull($block['renderer']);
    }

    public function test_register_throws_on_duplicate_type(): void
    {
        BlockRegistry::register('dup', [
            'label' => 'Duplicate',
            'category' => 'general',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Block type 'dup' is already registered");

        BlockRegistry::register('dup', [
            'label' => 'Duplicate 2',
            'category' => 'general',
        ]);
    }

    public function test_register_throws_on_missing_required_fields(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Block config missing required field: label');

        BlockRegistry::register('bad', ['category' => 'general']);
    }

    public function test_register_throws_on_missing_category(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Block config missing required field: category');

        BlockRegistry::register('bad', ['label' => 'Bad']);
    }

    public function test_register_throws_when_fields_is_not_array(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Block 'fields' must be an array");

        BlockRegistry::register('bad', [
            'label' => 'Bad',
            'category' => 'general',
            'fields' => 'not-an-array',
        ]);
    }

    public function test_register_throws_when_field_missing_type(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Field 'title' missing 'type'");

        BlockRegistry::register('bad', [
            'label' => 'Bad',
            'category' => 'general',
            'fields' => [
                'title' => ['label' => 'Title'],
            ],
        ]);
    }

    public function test_get_block_returns_null_for_unregistered(): void
    {
        $this->assertNull(BlockRegistry::getBlock('nonexistent'));
    }

    public function test_get_all_blocks_returns_all_registered(): void
    {
        BlockRegistry::register('a', ['label' => 'A', 'category' => 'cat1']);
        BlockRegistry::register('b', ['label' => 'B', 'category' => 'cat2']);

        $all = BlockRegistry::getAllBlocks();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('a', $all);
        $this->assertArrayHasKey('b', $all);
    }

    public function test_get_blocks_by_category(): void
    {
        BlockRegistry::register('a', ['label' => 'A', 'category' => 'content']);
        BlockRegistry::register('b', ['label' => 'B', 'category' => 'layout']);
        BlockRegistry::register('c', ['label' => 'C', 'category' => 'content']);

        $content = BlockRegistry::getBlocksByCategory('content');

        $this->assertCount(2, $content);
    }

    public function test_get_categories_returns_unique_categories(): void
    {
        BlockRegistry::register('a', ['label' => 'A', 'category' => 'content']);
        BlockRegistry::register('b', ['label' => 'B', 'category' => 'layout']);
        BlockRegistry::register('c', ['label' => 'C', 'category' => 'content']);

        $categories = BlockRegistry::getCategories();

        $this->assertCount(2, $categories);
        $this->assertContains('content', $categories);
        $this->assertContains('layout', $categories);
    }

    public function test_register_renderer_stores_renderer(): void
    {
        $renderer = Mockery::mock(BlockRendererInterface::class);

        BlockRegistry::registerRenderer('hero', $renderer);

        $this->assertSame($renderer, BlockRegistry::getRenderer('hero'));
    }

    public function test_register_renderer_throws_for_invalid_renderer(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Renderer must implement BlockRendererInterface');

        BlockRegistry::registerRenderer('hero', new \stdClass());
    }

    public function test_get_renderer_returns_null_for_unregistered(): void
    {
        $this->assertNull(BlockRegistry::getRenderer('nonexistent'));
    }

    public function test_render_returns_comment_when_no_renderer(): void
    {
        $result = BlockRegistry::render('missing', ['foo' => 'bar']);

        $this->assertStringContainsString("Block type 'missing' has no renderer", $result);
    }

    public function test_render_returns_validation_failed_comment(): void
    {
        $renderer = Mockery::mock(BlockRendererInterface::class);
        $renderer->shouldReceive('validate')->with(['title' => ''])->andReturn(false);

        BlockRegistry::registerRenderer('hero', $renderer);

        $result = BlockRegistry::render('hero', ['title' => '']);

        $this->assertStringContainsString("Block 'hero' validation failed", $result);
    }

    public function test_render_delegates_to_renderer(): void
    {
        $renderer = Mockery::mock(BlockRendererInterface::class);
        $renderer->shouldReceive('validate')->with(['title' => 'Test'])->andReturn(true);
        $renderer->shouldReceive('render')->with(['title' => 'Test'])->andReturn('<h1>Test</h1>');

        BlockRegistry::registerRenderer('hero', $renderer);

        $result = BlockRegistry::render('hero', ['title' => 'Test']);

        $this->assertEquals('<h1>Test</h1>', $result);
    }

    public function test_get_defaults_returns_defaults_for_registered_block(): void
    {
        BlockRegistry::register('test', [
            'label' => 'Test',
            'category' => 'general',
            'defaults' => ['title' => 'Default Title'],
        ]);

        $defaults = BlockRegistry::getDefaults('test');

        $this->assertEquals(['title' => 'Default Title'], $defaults);
    }

    public function test_get_defaults_returns_empty_array_for_unregistered(): void
    {
        $this->assertEquals([], BlockRegistry::getDefaults('nonexistent'));
    }

    public function test_clear_removes_all_blocks_and_renderers(): void
    {
        BlockRegistry::register('a', ['label' => 'A', 'category' => 'x']);
        $renderer = Mockery::mock(BlockRendererInterface::class);
        BlockRegistry::registerRenderer('a', $renderer);

        BlockRegistry::clear();

        $this->assertNull(BlockRegistry::getBlock('a'));
        $this->assertNull(BlockRegistry::getRenderer('a'));
        $this->assertCount(0, BlockRegistry::getAllBlocks());
    }
}

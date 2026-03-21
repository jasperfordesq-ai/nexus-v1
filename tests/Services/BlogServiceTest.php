<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Models\Category;
use App\Models\Post;
use App\Services\BlogService;
use App\Tests\TestCase;

class BlogServiceTest extends TestCase
{
    private BlogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BlogService(new Post(), new Category());
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(BlogService::class));
    }

    public function testGetAllReturnsExpectedStructure(): void
    {
        $result = $this->service->getAll();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsBool($result['has_more']);
    }

    public function testGetAllWithCategoryFilter(): void
    {
        $result = $this->service->getAll(['category_id' => 1]);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    public function testGetAllWithSearchFilter(): void
    {
        $result = $this->service->getAll(['search' => 'test']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    public function testGetAllWithCursor(): void
    {
        $cursor = base64_encode('999999');
        $result = $this->service->getAll(['cursor' => $cursor]);
        $this->assertIsArray($result);
    }

    public function testGetAllLimitsCapped(): void
    {
        $result = $this->service->getAll(['limit' => 500]);
        $this->assertIsArray($result);
    }

    public function testGetPostsReturnsExpectedStructure(): void
    {
        $result = $this->service->getPosts(1);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertIsInt($result['total']);
    }

    public function testGetPostsWithCategoryFilter(): void
    {
        $result = $this->service->getPosts(1, 1, 20, 1);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    public function testGetBySlugReturnsNullForNonExistent(): void
    {
        $result = $this->service->getBySlug('nonexistent-slug-12345');
        $this->assertNull($result);
    }

    public function testGetCategoriesReturnsArray(): void
    {
        $result = $this->service->getCategories();
        $this->assertIsArray($result);
    }

    public function testConstructorAcceptsModels(): void
    {
        $ref = new \ReflectionClass(BlogService::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('post', $params[0]->getName());
        $this->assertSame('category', $params[1]->getName());
    }

    public function testResolveImageUrlPrivateMethod(): void
    {
        // Test the private resolveImageUrl via reflection
        $result = $this->callPrivateMethod($this->service, 'resolveImageUrl', [null, 'https://example.com']);
        $this->assertNull($result);

        $result = $this->callPrivateMethod($this->service, 'resolveImageUrl', ['https://cdn.example.com/img.jpg', 'https://example.com']);
        $this->assertSame('https://cdn.example.com/img.jpg', $result);

        $result = $this->callPrivateMethod($this->service, 'resolveImageUrl', ['uploads/img.jpg', 'https://example.com']);
        $this->assertSame('https://example.com/uploads/img.jpg', $result);
    }

    public function testFormatAuthorPrivateMethodWithNull(): void
    {
        $result = $this->callPrivateMethod($this->service, 'formatAuthor', [null, 'https://example.com']);
        $this->assertSame(['id' => 0, 'name' => 'Unknown', 'avatar' => null], $result);
    }
}

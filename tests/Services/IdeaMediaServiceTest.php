<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\DatabaseTestCase;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\IdeaMediaService;

/**
 * IdeaMediaService Tests
 *
 * Tests media listing, adding, and deleting for challenge ideas.
 */
class IdeaMediaServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;
    private IdeaMediaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        $this->service = new IdeaMediaService();
    }

    // ==========================================
    // getMediaForIdea
    // ==========================================

    public function testGetMediaForIdeaReturnsEmptyForNonexistentIdea(): void
    {
        $this->requireTables(['idea_media']);

        $result = $this->service->getMediaForIdea(999999);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetMediaForIdeaReturnsInsertedMedia(): void
    {
        $this->requireTables(['idea_media', 'challenge_ideas', 'ideation_challenges']);

        $userId = $this->createUser('media-list');
        $ideaId = $this->createIdeaWithChallenge($userId);

        // Insert media directly
        Database::query(
            "INSERT INTO idea_media (idea_id, media_type, url, caption, sort_order, created_at)
             VALUES (?, 'image', 'https://example.com/img.png', 'Test image', 0, NOW())",
            [$ideaId]
        );

        $result = $this->service->getMediaForIdea($ideaId);

        $this->assertNotEmpty($result);
        $this->assertSame('image', $result[0]['media_type']);
        $this->assertSame('https://example.com/img.png', $result[0]['url']);
        $this->assertSame('Test image', $result[0]['caption']);
    }

    // ==========================================
    // addMedia
    // ==========================================

    public function testAddMediaReturnsNullForNonexistentIdea(): void
    {
        $this->requireTables(['idea_media', 'challenge_ideas', 'ideation_challenges']);

        $userId = $this->createUser('media-addnone');

        $result = $this->service->addMedia(999999, $userId, [
            'url' => 'https://example.com/img.png',
            'media_type' => 'image',
        ]);

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('RESOURCE_NOT_FOUND', $errors[0]['code']);
    }

    public function testAddMediaReturnsNullForEmptyUrl(): void
    {
        $this->requireTables(['idea_media', 'challenge_ideas', 'ideation_challenges']);

        $userId = $this->createUser('media-nourl');
        $ideaId = $this->createIdeaWithChallenge($userId);

        $result = $this->service->addMedia($ideaId, $userId, [
            'url' => '',
            'media_type' => 'image',
        ]);

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testAddMediaSucceedsForIdeaAuthor(): void
    {
        $this->requireTables(['idea_media', 'challenge_ideas', 'ideation_challenges']);

        $userId = $this->createUser('media-add');
        $ideaId = $this->createIdeaWithChallenge($userId);

        $mediaId = $this->service->addMedia($ideaId, $userId, [
            'url' => 'https://example.com/photo.jpg',
            'media_type' => 'image',
            'caption' => 'My photo',
            'sort_order' => 1,
        ]);

        $this->assertNotNull($mediaId);
        $this->assertIsInt($mediaId);
        $this->assertGreaterThan(0, $mediaId);

        // Verify it appears in getMediaForIdea
        $media = $this->service->getMediaForIdea($ideaId);
        $urls = array_column($media, 'url');
        $this->assertContains('https://example.com/photo.jpg', $urls);
    }

    public function testAddMediaRejectsForeignUserNonAdmin(): void
    {
        $this->requireTables(['idea_media', 'challenge_ideas', 'ideation_challenges', 'users']);

        $authorId = $this->createUser('media-author');
        $foreignId = $this->createUser('media-foreign');
        $ideaId = $this->createIdeaWithChallenge($authorId);

        $result = $this->service->addMedia($ideaId, $foreignId, [
            'url' => 'https://example.com/hack.jpg',
            'media_type' => 'image',
        ]);

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('RESOURCE_FORBIDDEN', $errors[0]['code']);
    }

    public function testAddMediaDefaultsToImageType(): void
    {
        $this->requireTables(['idea_media', 'challenge_ideas', 'ideation_challenges']);

        $userId = $this->createUser('media-default');
        $ideaId = $this->createIdeaWithChallenge($userId);

        $mediaId = $this->service->addMedia($ideaId, $userId, [
            'url' => 'https://example.com/unknown.bin',
            'media_type' => 'invalid_type',
        ]);

        $this->assertNotNull($mediaId);

        $media = $this->service->getMediaForIdea($ideaId);
        $lastMedia = end($media);
        $this->assertSame('image', $lastMedia['media_type']);
    }

    // ==========================================
    // deleteMedia
    // ==========================================

    public function testDeleteMediaReturnsFalseForNonexistentMedia(): void
    {
        $this->requireTables(['idea_media']);

        $userId = $this->createUser('media-delbad');

        $result = $this->service->deleteMedia(999999, $userId);

        $this->assertFalse($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('RESOURCE_NOT_FOUND', $errors[0]['code']);
    }

    public function testDeleteMediaSucceedsForAuthor(): void
    {
        $this->requireTables(['idea_media', 'challenge_ideas', 'ideation_challenges']);

        $userId = $this->createUser('media-delete');
        $ideaId = $this->createIdeaWithChallenge($userId);

        $mediaId = $this->service->addMedia($ideaId, $userId, [
            'url' => 'https://example.com/delete-me.jpg',
            'media_type' => 'image',
        ]);
        $this->assertNotNull($mediaId);

        $result = $this->service->deleteMedia($mediaId, $userId);

        $this->assertTrue($result);
    }

    public function testDeleteMediaRejectsForeignUser(): void
    {
        $this->requireTables(['idea_media', 'challenge_ideas', 'ideation_challenges', 'users']);

        $authorId = $this->createUser('media-delauthor');
        $foreignId = $this->createUser('media-delforeign');
        $ideaId = $this->createIdeaWithChallenge($authorId);

        $mediaId = $this->service->addMedia($ideaId, $authorId, [
            'url' => 'https://example.com/keep.jpg',
            'media_type' => 'image',
        ]);
        $this->assertNotNull($mediaId);

        $result = $this->service->deleteMedia($mediaId, $foreignId);

        $this->assertFalse($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('RESOURCE_FORBIDDEN', $errors[0]['code']);
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createUser(string $prefix, string $role = 'member'): int
    {
        $uniq = $prefix . '-' . str_replace('.', '', (string) microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            'INSERT INTO users (tenant_id, email, username, first_name, last_name, name, role, balance, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())',
            [self::TENANT_ID, $uniq . '@example.test', $uniq, 'Test', 'User', 'Test User', $role, 0]
        );
        return (int) Database::getInstance()->lastInsertId();
    }

    private function createIdeaWithChallenge(int $userId): int
    {
        $uniq = 'ch-' . str_replace('.', '', (string) microtime(true)) . '-' . random_int(1000, 9999);

        Database::query(
            "INSERT INTO ideation_challenges (tenant_id, user_id, title, description, status, created_at)
             VALUES (?, ?, ?, 'Test challenge', 'active', NOW())",
            [self::TENANT_ID, $userId, $uniq]
        );
        $challengeId = (int) Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO challenge_ideas (challenge_id, user_id, title, description, status, created_at)
             VALUES (?, ?, ?, 'Test idea description', 'submitted', NOW())",
            [$challengeId, $userId, 'Idea for ' . $uniq]
        );
        return (int) Database::getInstance()->lastInsertId();
    }

    /** @param string[] $tables */
    private function requireTables(array $tables): void
    {
        foreach ($tables as $table) {
            $exists = (int) Database::query(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            )->fetchColumn();
            if ($exists === 0) {
                $this->markTestSkipped('Required table not present in test DB: ' . $table);
            }
        }
    }
}

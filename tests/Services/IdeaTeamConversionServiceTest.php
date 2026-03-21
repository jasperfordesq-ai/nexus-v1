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
use App\Services\IdeaTeamConversionService;

/**
 * IdeaTeamConversionService Tests
 *
 * Tests idea-to-team conversion and link retrieval.
 */
class IdeaTeamConversionServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;
    private IdeaTeamConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        $this->service = new IdeaTeamConversionService();
    }

    // ==========================================
    // convert
    // ==========================================

    public function testConvertReturnsNullForNonexistentIdea(): void
    {
        $this->requireTables(['challenge_ideas', 'ideation_challenges', 'groups', 'idea_team_links', 'group_members']);

        $userId = $this->createUser('conv-notfound');

        $result = $this->service->convert(999999, $userId);

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('RESOURCE_NOT_FOUND', $errors[0]['code']);
    }

    public function testConvertRejectsForeignNonAdminUser(): void
    {
        $this->requireTables(['challenge_ideas', 'ideation_challenges', 'groups', 'idea_team_links', 'group_members', 'users']);

        $authorId = $this->createUser('conv-author');
        $foreignId = $this->createUser('conv-foreign');
        $ideaId = $this->createIdeaWithChallenge($authorId);

        $result = $this->service->convert($ideaId, $foreignId);

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('RESOURCE_FORBIDDEN', $errors[0]['code']);
    }

    public function testConvertSucceedsForIdeaAuthor(): void
    {
        $this->requireTables(['challenge_ideas', 'ideation_challenges', 'groups', 'idea_team_links', 'group_members']);

        $userId = $this->createUser('conv-author-ok');
        $ideaId = $this->createIdeaWithChallenge($userId);

        $result = $this->service->convert($ideaId, $userId);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('idea_id', $result);
        $this->assertArrayHasKey('group_id', $result);
        $this->assertArrayHasKey('challenge_id', $result);
        $this->assertArrayHasKey('converted_by', $result);
        $this->assertArrayHasKey('group', $result);
        $this->assertSame($ideaId, $result['idea_id']);
        $this->assertSame($userId, $result['converted_by']);
        $this->assertGreaterThan(0, $result['group_id']);
    }

    public function testConvertRejectsAlreadyConvertedIdea(): void
    {
        $this->requireTables(['challenge_ideas', 'ideation_challenges', 'groups', 'idea_team_links', 'group_members']);

        $userId = $this->createUser('conv-dup');
        $ideaId = $this->createIdeaWithChallenge($userId);

        $first = $this->service->convert($ideaId, $userId);
        $this->assertNotNull($first);

        $second = $this->service->convert($ideaId, $userId);

        $this->assertNull($second);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('RESOURCE_CONFLICT', $errors[0]['code']);
    }

    public function testConvertAcceptsCustomNameAndVisibility(): void
    {
        $this->requireTables(['challenge_ideas', 'ideation_challenges', 'groups', 'idea_team_links', 'group_members']);

        $userId = $this->createUser('conv-opts');
        $ideaId = $this->createIdeaWithChallenge($userId);

        $result = $this->service->convert($ideaId, $userId, [
            'name' => 'Custom Team Name',
            'description' => 'Custom description',
            'visibility' => 'private',
        ]);

        $this->assertNotNull($result);
        $this->assertSame('Custom Team Name', $result['group']['name']);
        $this->assertSame('private', $result['group']['visibility']);
    }

    public function testConvertAddsBothAuthorAndConverterAsMembers(): void
    {
        $this->requireTables(['challenge_ideas', 'ideation_challenges', 'groups', 'idea_team_links', 'group_members', 'users']);

        $authorId = $this->createUser('conv-memb-author');
        $adminId = $this->createUser('conv-memb-admin', 'admin');
        $ideaId = $this->createIdeaWithChallenge($authorId);

        $result = $this->service->convert($ideaId, $adminId);

        $this->assertNotNull($result);

        // Check group_members for both users
        $members = Database::query(
            'SELECT user_id, role FROM group_members WHERE group_id = ? ORDER BY user_id',
            [$result['group_id']]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $memberUserIds = array_column($members, 'user_id');
        $this->assertContains((string) $adminId, $memberUserIds);
        $this->assertContains((string) $authorId, $memberUserIds);
    }

    // ==========================================
    // getLinksForChallenge
    // ==========================================

    public function testGetLinksForChallengeReturnsEmptyForNonexistent(): void
    {
        $this->requireTables(['idea_team_links', 'groups', 'challenge_ideas']);

        $result = $this->service->getLinksForChallenge(999999);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetLinksForChallengeReturnsConvertedLinks(): void
    {
        $this->requireTables(['idea_team_links', 'groups', 'challenge_ideas', 'ideation_challenges', 'group_members']);

        $userId = $this->createUser('conv-links');
        [$ideaId, $challengeId] = $this->createIdeaWithChallengeReturningBoth($userId);

        $converted = $this->service->convert($ideaId, $userId);
        $this->assertNotNull($converted);

        $links = $this->service->getLinksForChallenge($challengeId);

        $this->assertNotEmpty($links);
        $this->assertArrayHasKey('id', $links[0]);
        $this->assertArrayHasKey('idea_id', $links[0]);
        $this->assertArrayHasKey('group_id', $links[0]);
        $this->assertArrayHasKey('idea_title', $links[0]);
        $this->assertArrayHasKey('group_name', $links[0]);
        $this->assertArrayHasKey('group_member_count', $links[0]);
    }

    // ==========================================
    // getLinkForIdea
    // ==========================================

    public function testGetLinkForIdeaReturnsNullWhenNotConverted(): void
    {
        $this->requireTables(['idea_team_links', 'groups']);

        $result = $this->service->getLinkForIdea(999999);
        $this->assertNull($result);
    }

    public function testGetLinkForIdeaReturnsLinkAfterConversion(): void
    {
        $this->requireTables(['idea_team_links', 'groups', 'challenge_ideas', 'ideation_challenges', 'group_members']);

        $userId = $this->createUser('conv-getlink');
        $ideaId = $this->createIdeaWithChallenge($userId);

        $converted = $this->service->convert($ideaId, $userId);
        $this->assertNotNull($converted);

        $link = $this->service->getLinkForIdea($ideaId);

        $this->assertNotNull($link);
        $this->assertSame($ideaId, $link['idea_id']);
        $this->assertSame($converted['group_id'], $link['group_id']);
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
        [$ideaId] = $this->createIdeaWithChallengeReturningBoth($userId);
        return $ideaId;
    }

    /** @return array{0: int, 1: int} [ideaId, challengeId] */
    private function createIdeaWithChallengeReturningBoth(int $userId): array
    {
        $uniq = 'ch-' . str_replace('.', '', (string) microtime(true)) . '-' . random_int(1000, 9999);

        Database::query(
            "INSERT INTO ideation_challenges (tenant_id, user_id, title, description, status, created_at)
             VALUES (?, ?, ?, 'Test challenge desc', 'active', NOW())",
            [self::TENANT_ID, $userId, $uniq]
        );
        $challengeId = (int) Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO challenge_ideas (challenge_id, user_id, title, description, status, created_at)
             VALUES (?, ?, ?, 'Idea description', 'submitted', NOW())",
            [$challengeId, $userId, 'Idea ' . $uniq]
        );
        $ideaId = (int) Database::getInstance()->lastInsertId();

        return [$ideaId, $challengeId];
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

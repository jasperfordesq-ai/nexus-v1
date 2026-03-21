<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\IdeationChallengeService;
use App\Core\TenantContext;

/**
 * IdeationChallengeService Tests
 */
class IdeationChallengeServiceTest extends TestCase
{
    private IdeationChallengeService $service;
    private static int $testTenantId = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
        $this->service = new IdeationChallengeService();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(IdeationChallengeService::class, $this->service);
    }

    public function test_get_errors_returns_empty_array_initially(): void
    {
        $this->assertSame([], $this->service->getErrors());
    }

    public function test_get_all_returns_paginated_structure(): void
    {
        $result = $this->service->getAll();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsBool($result['has_more']);
    }

    public function test_get_all_with_limit(): void
    {
        $result = $this->service->getAll(['limit' => 5]);
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result['items']));
    }

    public function test_get_all_with_status_filter(): void
    {
        $result = $this->service->getAll(['status' => 'open']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
    }

    public function test_get_all_challenges_returns_flat_array(): void
    {
        $result = $this->service->getAllChallenges();
        $this->assertIsArray($result);
        // Should be a flat array of items, not a paginated wrapper
        if (!empty($result)) {
            $this->assertArrayNotHasKey('cursor', $result);
        }
    }

    public function test_get_by_id_returns_null_for_nonexistent(): void
    {
        $result = $this->service->getById(999999);
        $this->assertNull($result);
    }

    public function test_get_challenge_by_id_returns_null_for_nonexistent(): void
    {
        $result = $this->service->getChallengeById(999999);
        $this->assertNull($result);
    }

    public function test_get_challenge_by_id_with_user_context(): void
    {
        $result = $this->service->getChallengeById(999999, 1);
        $this->assertNull($result);
    }

    public function test_update_challenge_forbidden_for_non_admin(): void
    {
        $result = $this->service->updateChallenge(999999, 999999, ['title' => 'New Title']);
        $this->assertFalse($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('FORBIDDEN', $errors[0]['code']);
    }

    public function test_delete_challenge_forbidden_for_non_admin(): void
    {
        $result = $this->service->deleteChallenge(999999, 999999);
        $this->assertFalse($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('FORBIDDEN', $errors[0]['code']);
    }

    public function test_update_challenge_status_forbidden_for_non_admin(): void
    {
        $result = $this->service->updateChallengeStatus(999999, 999999, 'closed');
        $this->assertFalse($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('FORBIDDEN', $errors[0]['code']);
    }

    public function test_errors_reset_between_operations(): void
    {
        $this->service->updateChallenge(999999, 999999, ['title' => 'X']);
        $this->assertNotEmpty($this->service->getErrors());

        $this->service->deleteChallenge(999999, 999999);
        $errors = $this->service->getErrors();
        $this->assertCount(1, $errors);
    }
}

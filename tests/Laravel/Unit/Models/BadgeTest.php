<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Models;

use Tests\Laravel\TestCase;
use App\Models\UserBadge;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Badge (UserBadge) Model Tests
 *
 * Tests the UserBadge Eloquent model structure, traits, relationships,
 * and available static methods: getForUser(), getShowcased(), updateShowcase().
 */
class BadgeTest extends \Tests\Laravel\TestCase
{
    // ==========================================
    // Model Structure Tests
    // ==========================================

    public function testTableName(): void
    {
        $model = new UserBadge();
        $this->assertEquals('user_badges', $model->getTable());
    }

    public function testFillableContainsExpectedFields(): void
    {
        $model = new UserBadge();
        $expected = [
            'tenant_id', 'user_id', 'badge_key', 'name', 'title', 'icon',
            'is_showcased', 'showcase_order', 'earned_at',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function testCastsContainExpectedTypes(): void
    {
        $model = new UserBadge();
        $casts = $model->getCasts();
        $this->assertEquals('boolean', $casts['is_showcased']);
        $this->assertEquals('integer', $casts['showcase_order']);
        $this->assertEquals('datetime', $casts['earned_at']);
    }

    public function testCreatedAtConstantIsAwardedAt(): void
    {
        $this->assertEquals('awarded_at', UserBadge::CREATED_AT);
    }

    public function testUpdatedAtConstantIsNull(): void
    {
        $this->assertNull(UserBadge::UPDATED_AT);
    }

    public function testUsesHasTenantScope(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(UserBadge::class)
        );
    }

    // ==========================================
    // Relationship Tests
    // ==========================================

    public function testUserRelationshipReturnsBelongsTo(): void
    {
        $model = new UserBadge();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    // ==========================================
    // Method Existence Tests
    // ==========================================

    public function testGetForUserMethodExists(): void
    {
        $this->assertTrue(
            method_exists(UserBadge::class, 'getForUser'),
            'UserBadge::getForUser() should exist'
        );
    }

    public function testGetShowcasedMethodExists(): void
    {
        $this->assertTrue(
            method_exists(UserBadge::class, 'getShowcased'),
            'UserBadge::getShowcased() should exist'
        );
    }

    public function testUpdateShowcaseMethodExists(): void
    {
        $this->assertTrue(
            method_exists(UserBadge::class, 'updateShowcase'),
            'UserBadge::updateShowcase() should exist'
        );
    }

    // ==========================================
    // Return Type Tests
    // ==========================================

    public function testGetForUserReturnsArrayForNonExistentUser(): void
    {
        $badges = UserBadge::getForUser(999999999);
        $this->assertIsArray($badges);
    }

    public function testGetShowcasedReturnsArrayForNonExistentUser(): void
    {
        $badges = UserBadge::getShowcased(999999999);
        $this->assertIsArray($badges);
    }

    public function testUpdateShowcaseReturnsBool(): void
    {
        $result = UserBadge::updateShowcase(999999999, []);
        $this->assertIsBool($result);
    }
}

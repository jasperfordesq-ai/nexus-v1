<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\GamificationV2ApiController;

class GamificationV2ApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(GamificationV2ApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(GamificationV2ApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasProfileMethod(): void
    {
        $reflection = new \ReflectionClass(GamificationV2ApiController::class);
        $this->assertTrue($reflection->hasMethod('profile'));
        $this->assertTrue($reflection->getMethod('profile')->isPublic());
    }

    public function testHasBadgesMethods(): void
    {
        $reflection = new \ReflectionClass(GamificationV2ApiController::class);
        $this->assertTrue($reflection->hasMethod('badges'));
        $this->assertTrue($reflection->hasMethod('showBadge'));

        $method = $reflection->getMethod('showBadge');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('key', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    public function testHasLeaderboardMethod(): void
    {
        $reflection = new \ReflectionClass(GamificationV2ApiController::class);
        $this->assertTrue($reflection->hasMethod('leaderboard'));
        $this->assertTrue($reflection->getMethod('leaderboard')->isPublic());
    }

    public function testHasChallengesMethods(): void
    {
        $reflection = new \ReflectionClass(GamificationV2ApiController::class);
        $this->assertTrue($reflection->hasMethod('challenges'));
        $this->assertTrue($reflection->hasMethod('claimChallenge'));

        $method = $reflection->getMethod('claimChallenge');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('id', $params[0]->getName());
    }

    public function testHasDailyRewardMethods(): void
    {
        $reflection = new \ReflectionClass(GamificationV2ApiController::class);
        $this->assertTrue($reflection->hasMethod('dailyRewardStatus'));
        $this->assertTrue($reflection->hasMethod('claimDailyReward'));
    }

    public function testHasShopMethods(): void
    {
        $reflection = new \ReflectionClass(GamificationV2ApiController::class);
        $this->assertTrue($reflection->hasMethod('shop'));
        $this->assertTrue($reflection->hasMethod('purchase'));
    }

    public function testHasShowcaseMethod(): void
    {
        $reflection = new \ReflectionClass(GamificationV2ApiController::class);
        $this->assertTrue($reflection->hasMethod('updateShowcase'));
        $this->assertTrue($reflection->getMethod('updateShowcase')->isPublic());
    }

    public function testHasSeasonMethods(): void
    {
        $reflection = new \ReflectionClass(GamificationV2ApiController::class);
        $this->assertTrue($reflection->hasMethod('seasons'));
        $this->assertTrue($reflection->hasMethod('currentSeason'));
    }

    public function testHasCollectionsMethod(): void
    {
        $reflection = new \ReflectionClass(GamificationV2ApiController::class);
        $this->assertTrue($reflection->hasMethod('collections'));
        $this->assertTrue($reflection->getMethod('collections')->isPublic());
    }
}

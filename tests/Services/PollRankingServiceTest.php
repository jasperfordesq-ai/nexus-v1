<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\PollRankingService;

class PollRankingServiceTest extends TestCase
{
    private PollRankingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PollRankingService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(PollRankingService::class));
    }

    public function testSubmitRankingMethodExists(): void
    {
        $this->assertTrue(method_exists(PollRankingService::class, 'submitRanking'));
    }

    public function testCalculateResultsMethodExists(): void
    {
        $this->assertTrue(method_exists(PollRankingService::class, 'calculateResults'));
    }

    public function testGetUserRankingsMethodExists(): void
    {
        $this->assertTrue(method_exists(PollRankingService::class, 'getUserRankings'));
    }

    public function testCalculateResultsSignature(): void
    {
        $ref = new \ReflectionMethod(PollRankingService::class, 'calculateResults');
        $params = $ref->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('pollId', $params[0]->getName());
        $returnType = $ref->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    public function testGetUserRankingsReturnsNullForNonExistentPoll(): void
    {
        $result = $this->service->getUserRankings(999999, 999999);
        $this->assertNull($result);
    }

    public function testSubmitRankingSignature(): void
    {
        $ref = new \ReflectionMethod(PollRankingService::class, 'submitRanking');
        $params = $ref->getParameters();
        $this->assertCount(3, $params);
        $this->assertEquals('pollId', $params[0]->getName());
        $this->assertEquals('userId', $params[1]->getName());
        $this->assertEquals('rankings', $params[2]->getName());
    }
}

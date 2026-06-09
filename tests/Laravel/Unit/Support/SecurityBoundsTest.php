<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Support;

use App\Support\SecurityBounds;
use Tests\Laravel\TestCase;

class SecurityBoundsTest extends TestCase
{
    public function testExternalHourAmountsArePositiveCappedAndTwoDecimalPlaces(): void
    {
        $this->assertTrue(SecurityBounds::isAcceptableHourAmount(0.25));
        $this->assertTrue(SecurityBounds::isAcceptableHourAmount(24.0));
        $this->assertFalse(SecurityBounds::isAcceptableHourAmount(0.0));
        $this->assertFalse(SecurityBounds::isAcceptableHourAmount(24.01));
        $this->assertFalse(SecurityBounds::isAcceptableHourAmount(1.234));
    }

    public function testPaidPushCostPerSendIsClamped(): void
    {
        $this->assertSame(5, SecurityBounds::paidPushCostPerSend(null));
        $this->assertSame(1, SecurityBounds::paidPushCostPerSend(-100));
        $this->assertSame(25, SecurityBounds::paidPushCostPerSend(25));
        $this->assertSame(1000, SecurityBounds::paidPushCostPerSend(5000));
    }

    public function testAudienceCountsAreBucketedForPrivacy(): void
    {
        $this->assertSame(0, SecurityBounds::bucketAudienceCount(9));
        $this->assertSame(10, SecurityBounds::bucketAudienceCount(19));
        $this->assertSame(90, SecurityBounds::bucketAudienceCount(99));
        $this->assertSame(100, SecurityBounds::bucketAudienceCount(199));
    }
}

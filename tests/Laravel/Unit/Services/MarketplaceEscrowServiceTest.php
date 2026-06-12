<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\MarketplaceEscrowService;
use App\Models\MarketplaceEscrow;
use App\Models\MarketplacePayment;
use Mockery;

class MarketplaceEscrowServiceTest extends TestCase
{
    // -----------------------------------------------------------------
    //  releaseFunds — guard clauses
    // -----------------------------------------------------------------

    public function test_releaseFunds_throwsWhenEscrowAlreadyReleased(): void
    {
        $escrow = Mockery::mock(MarketplaceEscrow::class)->makePartial();
        $escrow->status = 'released';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Escrow is not in 'held' status. Current: released");

        MarketplaceEscrowService::releaseFunds($escrow, 'buyer_confirmed');
    }

    public function test_releaseFunds_throwsWhenEscrowIsRefunded(): void
    {
        $escrow = Mockery::mock(MarketplaceEscrow::class)->makePartial();
        $escrow->status = 'refunded';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Escrow is not in 'held' status. Current: refunded");

        MarketplaceEscrowService::releaseFunds($escrow, 'admin_override');
    }

    public function test_releaseFunds_throwsOnInvalidTrigger(): void
    {
        $escrow = Mockery::mock(MarketplaceEscrow::class)->makePartial();
        $escrow->status = 'held';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid release trigger: hacker_exploit');

        MarketplaceEscrowService::releaseFunds($escrow, 'hacker_exploit');
    }

    public function test_releaseFunds_rejectsEmptyTrigger(): void
    {
        $escrow = Mockery::mock(MarketplaceEscrow::class)->makePartial();
        $escrow->status = 'held';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid release trigger');

        MarketplaceEscrowService::releaseFunds($escrow, '');
    }

    // -----------------------------------------------------------------
    //  refundEscrow — guard clauses
    // -----------------------------------------------------------------

    public function test_refundEscrow_throwsWhenStatusIsReleased(): void
    {
        $escrow = Mockery::mock(MarketplaceEscrow::class)->makePartial();
        $escrow->status = 'released';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Escrow cannot be refunded from status: released');

        MarketplaceEscrowService::refundEscrow($escrow);
    }

    // The refundEscrow happy paths now use an atomic conditional UPDATE
    // (race-safe claim) instead of $escrow->save(), so they can no longer be
    // exercised with facade mocks — the real-DB coverage lives in
    // tests/Laravel/Feature/Marketplace/MarketplaceEscrowRefundRaceTest.php.
}

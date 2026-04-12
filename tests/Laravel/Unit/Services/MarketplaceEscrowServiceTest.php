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

    public function test_refundEscrow_allowsRefundFromHeldStatus(): void
    {
        $payment = Mockery::mock(MarketplacePayment::class)->makePartial();
        $payment->shouldReceive('save')->once();

        $escrow = Mockery::mock(MarketplaceEscrow::class)->makePartial();
        $escrow->status = 'held';
        $escrow->order_id = 10;
        $escrow->amount = '45.00';
        $escrow->shouldReceive('save')->once();
        $escrow->shouldReceive('getAttribute')->with('payment')->andReturn($payment);

        \Illuminate\Support\Facades\DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());
        \Illuminate\Support\Facades\Log::shouldReceive('info')->once();

        MarketplaceEscrowService::refundEscrow($escrow);

        $this->assertEquals('refunded', $escrow->status);
        $this->assertNull($escrow->release_trigger);
        $this->assertEquals('failed', $payment->payout_status);
    }

    public function test_refundEscrow_allowsRefundFromDisputedStatus(): void
    {
        $payment = Mockery::mock(MarketplacePayment::class)->makePartial();
        $payment->shouldReceive('save')->once();

        $escrow = Mockery::mock(MarketplaceEscrow::class)->makePartial();
        $escrow->status = 'disputed';
        $escrow->order_id = 20;
        $escrow->amount = '30.00';
        $escrow->shouldReceive('save')->once();
        $escrow->shouldReceive('getAttribute')->with('payment')->andReturn($payment);

        \Illuminate\Support\Facades\DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());
        \Illuminate\Support\Facades\Log::shouldReceive('info')->once();

        MarketplaceEscrowService::refundEscrow($escrow);

        $this->assertEquals('refunded', $escrow->status);
        $this->assertEquals('failed', $payment->payout_status);
    }

    public function test_refundEscrow_handlesNullPaymentGracefully(): void
    {
        $escrow = Mockery::mock(MarketplaceEscrow::class)->makePartial();
        $escrow->status = 'held';
        $escrow->order_id = 30;
        $escrow->amount = '20.00';
        $escrow->shouldReceive('save')->once();
        $escrow->shouldReceive('getAttribute')->with('payment')->andReturn(null);

        \Illuminate\Support\Facades\DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($cb) => $cb());
        \Illuminate\Support\Facades\Log::shouldReceive('info')->once();

        MarketplaceEscrowService::refundEscrow($escrow);

        $this->assertEquals('refunded', $escrow->status);
    }
}

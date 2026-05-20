<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\MarketplaceRatingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MarketplaceRatingTenantScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dispute_lookup_rejects_order_from_another_tenant(): void
    {
        $foreignTenantId = $this->createTenant();
        $buyer = User::factory()->forTenant($foreignTenantId)->create();
        $seller = User::factory()->forTenant($foreignTenantId)->create();
        $orderId = $this->createOrder($foreignTenantId, (int) $buyer->id, (int) $seller->id, 'paid');

        $this->expectException(ModelNotFoundException::class);

        MarketplaceRatingService::openDispute($orderId, (int) $buyer->id, [
            'reason' => 'not_received',
            'description' => 'Package did not arrive.',
        ], $this->testTenantId);
    }

    public function test_rating_lookup_rejects_order_from_another_tenant(): void
    {
        $foreignTenantId = $this->createTenant();
        $buyer = User::factory()->forTenant($foreignTenantId)->create();
        $seller = User::factory()->forTenant($foreignTenantId)->create();
        $orderId = $this->createOrder($foreignTenantId, (int) $buyer->id, (int) $seller->id, 'completed');

        $this->expectException(ModelNotFoundException::class);

        MarketplaceRatingService::rateOrder($orderId, (int) $buyer->id, 'buyer', ['rating' => 5], $this->testTenantId);
    }

    public function test_rating_email_result_is_persisted_on_rating_row(): void
    {
        $mailer = $this->fakeMailer(true);
        app()->instance(EmailDispatchService::class, $mailer);
        TenantContext::setById($this->testTenantId);

        $buyer = User::factory()->forTenant($this->testTenantId)->create(['email' => 'rating-buyer-' . uniqid('', true) . '@example.test']);
        $seller = User::factory()->forTenant($this->testTenantId)->create(['email' => 'rating-seller-' . uniqid('', true) . '@example.test']);
        $orderId = $this->createOrder($this->testTenantId, (int) $buyer->id, (int) $seller->id, 'completed');

        MarketplaceRatingService::rateOrder($orderId, (int) $buyer->id, 'buyer', ['rating' => 5], $this->testTenantId);

        $rating = DB::table('marketplace_seller_ratings')->where('order_id', $orderId)->first();
        $this->assertNotNull($rating);
        $this->assertNotNull($rating->notification_email_sent_at);
        $this->assertNull($rating->notification_email_failed_at);
        $this->assertNull($rating->notification_email_last_error);
        $this->assertSame('marketplace_rating', $mailer->calls[0]['options']['category']);
        $this->assertSame($this->testTenantId, (int) $mailer->calls[0]['options']['tenant_id']);
    }

    public function test_dispute_email_failure_is_persisted_and_open_uses_lock(): void
    {
        $mailer = $this->fakeMailer(false);
        app()->instance(EmailDispatchService::class, $mailer);
        TenantContext::setById($this->testTenantId);

        $buyer = User::factory()->forTenant($this->testTenantId)->create(['email' => 'dispute-buyer-' . uniqid('', true) . '@example.test']);
        $seller = User::factory()->forTenant($this->testTenantId)->create(['email' => 'dispute-seller-' . uniqid('', true) . '@example.test']);
        $orderId = $this->createOrder($this->testTenantId, (int) $buyer->id, (int) $seller->id, 'paid');

        MarketplaceRatingService::openDispute($orderId, (int) $buyer->id, [
            'reason' => 'not_received',
            'description' => 'Package did not arrive.',
        ], $this->testTenantId);

        $dispute = DB::table('marketplace_disputes')->where('order_id', $orderId)->first();
        $this->assertNotNull($dispute);
        $this->assertNull($dispute->notification_email_sent_at);
        $this->assertNotNull($dispute->notification_email_failed_at);
        $this->assertSame('Marketplace dispute notification email returned false', $dispute->notification_email_last_error);
        $this->assertSame('marketplace_order', $mailer->calls[0]['options']['category']);

        $source = file_get_contents(app_path('Services/MarketplaceRatingService.php'));
        $this->assertStringContainsString('->lockForUpdate()', $source);
        $this->assertStringContainsString("->where('tenant_id', \$tenantId)", $source);
    }

    private function createTenant(): int
    {
        $slug = 'marketplace-rating-scope-' . uniqid();

        return (int) DB::table('tenants')->insertGetId([
            'name' => 'Marketplace Rating Scope Tenant',
            'slug' => $slug,
            'domain' => $slug . '.example.test',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(int $tenantId, int $buyerId, int $sellerId, string $status): int
    {
        return (int) DB::table('marketplace_orders')->insertGetId([
            'tenant_id' => $tenantId,
            'order_number' => 'MRS-' . uniqid(),
            'buyer_id' => $buyerId,
            'seller_id' => $sellerId,
            'marketplace_listing_id' => null,
            'quantity' => 1,
            'unit_price' => 10.00,
            'total_price' => 10.00,
            'currency' => 'EUR',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function fakeMailer(bool $result): EmailDispatchService
    {
        return new class($result) extends EmailDispatchService {
            public array $calls = [];

            public function __construct(private bool $result)
            {
            }

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = compact('to', 'subject', 'body', 'options');

                return $this->result;
            }
        };
    }
}

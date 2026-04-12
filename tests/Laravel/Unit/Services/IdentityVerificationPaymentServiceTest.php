<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\User;
use App\Services\Identity\IdentityVerificationPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class IdentityVerificationPaymentServiceTest extends TestCase
{
    use DatabaseTransactions;

    // -------- Smoke (kept) --------

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\Identity\IdentityVerificationPaymentService::class));
    }

    public function test_has_public_methods(): void
    {
        $ref = new \ReflectionClass(\App\Services\Identity\IdentityVerificationPaymentService::class);
        foreach (['getFeeCents', 'hasCompletedPayment', 'createPaymentIntent', 'handlePaymentSucceeded', 'handlePaymentFailed'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Missing method: {$m}");
            $this->assertTrue($ref->getMethod($m)->isPublic(), "Not public: {$m}");
        }
    }

    // -------- Deep tests --------

    public function test_get_fee_cents_returns_default_500(): void
    {
        // No override stored — default €5.00 (500c)
        DB::table('tenant_settings')->where('tenant_id', 2)->where('setting_key', 'identity_verification_fee_cents')->delete();

        $this->assertEquals(500, IdentityVerificationPaymentService::getFeeCents(2));
    }

    public function test_create_payment_intent_rejects_zero_or_negative_fee(): void
    {
        $user = User::factory()->forTenant(2)->create();

        $this->expectException(\InvalidArgumentException::class);
        IdentityVerificationPaymentService::createPaymentIntent($user->id, 2, 0);
    }

    public function test_create_payment_intent_throws_on_missing_user(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not found');

        IdentityVerificationPaymentService::createPaymentIntent(99999999, 2, 500);
    }

    public function test_create_payment_intent_rejects_cross_tenant_user(): void
    {
        // User in tenant 999 cannot be charged against tenant 2 context
        $user = User::factory()->forTenant(999)->create();

        $this->expectException(\RuntimeException::class);
        IdentityVerificationPaymentService::createPaymentIntent($user->id, 2, 500);
    }

    public function test_handle_payment_succeeded_ignores_non_identity_verification_events(): void
    {
        // Build a fake PaymentIntent with a different metadata type
        $pi = (object) [
            'id' => 'pi_test_unrelated',
            'metadata' => (object) ['nexus_type' => 'donation'],
        ];

        // Should silently return — no exception, no DB mutation
        IdentityVerificationPaymentService::handlePaymentSucceeded($pi);

        $this->assertDatabaseMissing('identity_verification_sessions', [
            'stripe_payment_intent_id' => 'pi_test_unrelated',
        ]);
    }

    public function test_handle_payment_succeeded_marks_session_completed(): void
    {
        $user = User::factory()->forTenant(2)->create();
        $sessionId = DB::table('identity_verification_sessions')->insertGetId([
            'tenant_id' => 2,
            'user_id' => $user->id,
            'provider_slug' => 'stripe_identity',
            'verification_level' => 'document_selfie',
            'status' => 'created',
            'stripe_payment_intent_id' => 'pi_test_succeed_' . uniqid(),
            'verification_fee_amount' => 500,
            'payment_status' => 'pending',
            'created_at' => now(),
        ]);

        $piId = DB::table('identity_verification_sessions')->where('id', $sessionId)->value('stripe_payment_intent_id');
        $pi = (object) [
            'id' => $piId,
            'metadata' => (object) ['nexus_type' => 'identity_verification'],
        ];

        IdentityVerificationPaymentService::handlePaymentSucceeded($pi);

        $this->assertEquals('completed', DB::table('identity_verification_sessions')->where('id', $sessionId)->value('payment_status'));
    }

    public function test_handle_payment_failed_marks_session_failed(): void
    {
        $user = User::factory()->forTenant(2)->create();
        $sessionId = DB::table('identity_verification_sessions')->insertGetId([
            'tenant_id' => 2,
            'user_id' => $user->id,
            'provider_slug' => 'stripe_identity',
            'verification_level' => 'document_selfie',
            'status' => 'created',
            'stripe_payment_intent_id' => 'pi_test_fail_' . uniqid(),
            'verification_fee_amount' => 500,
            'payment_status' => 'pending',
            'created_at' => now(),
        ]);

        $piId = DB::table('identity_verification_sessions')->where('id', $sessionId)->value('stripe_payment_intent_id');
        $pi = (object) [
            'id' => $piId,
            'metadata' => (object) ['nexus_type' => 'identity_verification'],
        ];

        IdentityVerificationPaymentService::handlePaymentFailed($pi);

        $this->assertEquals('failed', DB::table('identity_verification_sessions')->where('id', $sessionId)->value('payment_status'));
    }

    public function test_has_completed_payment_reflects_session_state(): void
    {
        $user = User::factory()->forTenant(2)->create();

        // Initially none
        $this->assertFalse(IdentityVerificationPaymentService::hasCompletedPayment(2, $user->id));

        DB::table('identity_verification_sessions')->insert([
            'tenant_id' => 2,
            'user_id' => $user->id,
            'provider_slug' => 'stripe_identity',
            'verification_level' => 'document_selfie',
            'status' => 'created',
            'payment_status' => 'completed',
            'created_at' => now(),
        ]);

        $this->assertTrue(IdentityVerificationPaymentService::hasCompletedPayment(2, $user->id));
    }
}

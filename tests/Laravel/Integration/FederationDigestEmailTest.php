<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\FederationEmailService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class FederationDigestEmailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_weekly_digest_skips_users_with_no_federation_activity(): void
    {
        $tenantId = (int) DB::table('tenants')->insertGetId([
            'name' => 'Federation Digest Empty Tenant',
            'slug' => 'federation-digest-empty-' . uniqid(),
            'domain' => 'federation-digest-empty-' . uniqid() . '.example.test',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->forTenant($tenantId)->create([
            'email' => 'federation-digest-empty@example.test',
            'preferred_language' => 'en',
        ]);

        $mailer = new class extends EmailDispatchService {
            public array $calls = [];

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = compact('to', 'subject', 'body', 'options');

                return true;
            }
        };
        app()->instance(EmailDispatchService::class, $mailer);

        $this->assertFalse(FederationEmailService::sendWeeklyDigest((int) $user->id, $tenantId));
        $this->assertSame([], $mailer->calls);
    }
}

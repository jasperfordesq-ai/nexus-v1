<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Feature tests for NewsletterController — newsletter unsubscribe.
 */
class NewsletterControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function createSentNewsletterQueue(
        ?string $trackingToken = null,
        ?string $unsubscribeToken = null,
        string $email = 'recipient@example.test'
    ): array {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();

        $newsletterId = DB::table('newsletters')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Tracking regression test',
            'subject' => 'Tracking regression test',
            'content' => '<p>Hello</p>',
            'status' => 'sent',
            'total_recipients' => 1,
            'total_sent' => 1,
            'target_audience' => 'all_members',
            'created_by' => $admin->id,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $trackingToken ??= Str::random(64);
        $unsubscribeToken ??= Str::random(64);

        $queueRow = [
            'newsletter_id' => $newsletterId,
            'user_id' => $admin->id,
            'email' => $email,
            'name' => 'Recipient Person',
            'first_name' => 'Recipient',
            'last_name' => 'Person',
            'status' => 'sent',
            'unsubscribe_token' => $unsubscribeToken,
            'tracking_token' => $trackingToken,
            'sent_at' => now(),
            'created_at' => now(),
        ];

        if (Schema::hasColumn('newsletter_queue', 'tenant_id')) {
            $queueRow['tenant_id'] = $this->testTenantId;
        }

        $queueId = DB::table('newsletter_queue')->insertGetId($queueRow);

        return [
            'newsletter_id' => $newsletterId,
            'queue_id' => $queueId,
            'email' => $email,
            'tracking_token' => $trackingToken,
            'unsubscribe_token' => $unsubscribeToken,
        ];
    }

    // ------------------------------------------------------------------
    //  POST /v2/newsletter/unsubscribe
    // ------------------------------------------------------------------

    public function test_unsubscribe_works(): void
    {
        $user = $this->authenticatedUser();
        $token = Str::random(64);

        DB::table('newsletter_subscribers')->insert([
            'tenant_id' => $this->testTenantId,
            'email' => $user->email,
            'user_id' => $user->id,
            'status' => 'active',
            'confirmation_token' => Str::random(64),
            'unsubscribe_token' => $token,
            'confirmed_at' => now(),
            'source' => 'signup',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiPost('/v2/newsletter/unsubscribe', [
            'token' => $token,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('newsletter_subscribers', [
            'tenant_id' => $this->testTenantId,
            'email' => $user->email,
            'status' => 'unsubscribed',
            'is_active' => 0,
        ]);
    }

    public function test_unsubscribe_requires_token(): void
    {
        $response = $this->apiPost('/v2/newsletter/unsubscribe', []);

        $response->assertStatus(400);
    }

    public function test_unsubscribe_rejects_invalid_token(): void
    {
        $response = $this->apiPost('/v2/newsletter/unsubscribe', [
            'token' => Str::random(64),
        ]);

        $response->assertStatus(404);
    }

    public function test_click_tracking_does_not_redirect_unknown_signed_tokens(): void
    {
        config(['app.frontend_url' => 'https://app.example.test']);

        $token = 'unknown-token';
        $url = 'https://evil.example.test/phishing';
        $signature = hash_hmac('sha256', $token . '|' . $url, (string) config('app.key'));

        $response = $this->get(
            '/api/v2/newsletter/click/' . $token . '?url=' . rawurlencode($url) . '&sig=' . rawurlencode($signature),
            $this->withTenantHeader()
        );

        $response->assertRedirect('https://app.example.test');
    }

    public function test_click_tracking_requires_signature(): void
    {
        config(['app.frontend_url' => 'https://app.example.test']);

        $response = $this->get(
            '/api/v2/newsletter/click/missing-signature?url=' . rawurlencode('https://evil.example.test/phishing'),
            $this->withTenantHeader()
        );

        $response->assertRedirect('https://app.example.test');
    }

    public function test_legacy_unprefixed_tracking_pixel_route_records_open_without_tenant_header(): void
    {
        $queue = $this->createSentNewsletterQueue();

        TenantContext::reset();

        $response = $this->get('/v2/newsletter/pixel/' . $queue['tracking_token']);

        $response->assertOk();
        $this->assertSame('image/gif', $response->headers->get('Content-Type'));
        $this->assertDatabaseHas('newsletter_opens', [
            'tenant_id' => $this->testTenantId,
            'newsletter_id' => $queue['newsletter_id'],
            'queue_id' => $queue['queue_id'],
            'email' => $queue['email'],
        ]);
        $this->assertSame(1, (int) DB::table('newsletters')->where('id', $queue['newsletter_id'])->value('total_opens'));
        $this->assertSame(1, (int) DB::table('newsletters')->where('id', $queue['newsletter_id'])->value('unique_opens'));
    }

    public function test_legacy_unprefixed_click_route_records_click_without_tenant_header(): void
    {
        config(['app.frontend_url' => 'https://app.example.test']);
        $queue = $this->createSentNewsletterQueue();
        $url = 'https://hour-timebank.ie/';
        $signature = hash_hmac('sha256', $queue['tracking_token'] . '|' . $url, (string) config('app.key'));

        TenantContext::reset();

        $response = $this->get(
            '/v2/newsletter/click/' . $queue['tracking_token'] . '?url=' . rawurlencode($url) . '&sig=' . rawurlencode($signature)
        );

        $response->assertRedirect($url);
        $this->assertDatabaseHas('newsletter_clicks', [
            'tenant_id' => $this->testTenantId,
            'newsletter_id' => $queue['newsletter_id'],
            'queue_id' => $queue['queue_id'],
            'email' => $queue['email'],
            'url' => $url,
        ]);
        $this->assertSame(1, (int) DB::table('newsletters')->where('id', $queue['newsletter_id'])->value('total_clicks'));
        $this->assertSame(1, (int) DB::table('newsletters')->where('id', $queue['newsletter_id'])->value('unique_clicks'));
    }

    public function test_click_tracking_accepts_legacy_unsubscribe_tokens(): void
    {
        config(['app.frontend_url' => 'https://app.example.test']);
        $legacyToken = Str::random(64);
        $queue = $this->createSentNewsletterQueue(trackingToken: null, unsubscribeToken: $legacyToken);
        $url = 'https://hour-timebank.ie/';
        $signature = hash_hmac('sha256', $legacyToken . '|' . $url, (string) config('app.key'));

        TenantContext::reset();

        $response = $this->get(
            '/v2/newsletter/click/' . $legacyToken . '?url=' . rawurlencode($url) . '&sig=' . rawurlencode($signature)
        );

        $response->assertRedirect($url);
        $this->assertDatabaseHas('newsletter_clicks', [
            'tenant_id' => $this->testTenantId,
            'newsletter_id' => $queue['newsletter_id'],
            'queue_id' => $queue['queue_id'],
            'email' => $queue['email'],
            'url' => $url,
        ]);
    }
}

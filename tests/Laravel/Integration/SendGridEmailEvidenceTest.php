<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Http\Controllers\Api\SendGridWebhookController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

class SendGridEmailEvidenceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_webhook_without_tenant_or_message_id_does_not_update_email_log(): void
    {
        if (!Schema::hasTable('email_log')) {
            $this->markTestSkipped('email_log table is not available.');
        }

        $id = $this->insertEmailLog([
            'tenant_id' => $this->testTenantId,
            'recipient_email' => 'shared-sendgrid-' . uniqid('', true) . '@example.test',
            'provider' => 'sendgrid',
            'provider_message_id' => 'sg_msg_original',
            'status' => 'sent',
        ]);

        $this->invokeWebhookEvidenceUpdate([
            'email' => DB::table('email_log')->where('id', $id)->value('recipient_email'),
            'event' => 'delivered',
            'timestamp' => time(),
        ], 'delivered', 0);

        $this->assertSame('sent', DB::table('email_log')->where('id', $id)->value('status'));
        $this->assertNull(DB::table('email_log')->where('id', $id)->value('delivered_at'));
    }

    public function test_webhook_updates_only_matching_sendgrid_row_in_same_tenant(): void
    {
        if (!Schema::hasTable('email_log')) {
            $this->markTestSkipped('email_log table is not available.');
        }

        $email = 'same-recipient-' . uniqid('', true) . '@example.test';
        $matching = $this->insertEmailLog([
            'tenant_id' => $this->testTenantId,
            'recipient_email' => $email,
            'provider' => 'sendgrid',
            'provider_message_id' => 'sg_match',
            'status' => 'sent',
        ]);
        $otherTenant = $this->insertEmailLog([
            'tenant_id' => 999,
            'recipient_email' => $email,
            'provider' => 'sendgrid',
            'provider_message_id' => 'sg_other',
            'status' => 'sent',
        ]);
        $smtp = $this->insertEmailLog([
            'tenant_id' => $this->testTenantId,
            'recipient_email' => $email,
            'provider' => 'smtp',
            'provider_message_id' => 'sg_match',
            'status' => 'sent',
        ]);

        $this->invokeWebhookEvidenceUpdate([
            'email' => $email,
            'event' => 'delivered',
            'timestamp' => time(),
            'sg_message_id' => 'sg_match.extra.0',
        ], 'delivered', $this->testTenantId);

        $this->assertSame('delivered', DB::table('email_log')->where('id', $matching)->value('status'));
        $this->assertSame('sent', DB::table('email_log')->where('id', $otherTenant)->value('status'));
        $this->assertSame('sent', DB::table('email_log')->where('id', $smtp)->value('status'));
    }

    public function test_reconciliation_uses_provider_message_id_not_recipient_only(): void
    {
        if (!Schema::hasTable('email_log')) {
            $this->markTestSkipped('email_log table is not available.');
        }

        config(['mail.sendgrid.api_key' => 'SG.test-key']);

        $email = 'reconcile-' . uniqid('', true) . '@example.test';
        $target = $this->insertEmailLog([
            'tenant_id' => $this->testTenantId,
            'recipient_email' => $email,
            'provider' => 'sendgrid',
            'provider_message_id' => 'sg_reconcile_target',
            'status' => 'failed',
            'error' => 'temporary SendGrid timeout',
        ]);
        $other = $this->insertEmailLog([
            'tenant_id' => 999,
            'recipient_email' => $email,
            'provider' => 'sendgrid',
            'provider_message_id' => 'sg_reconcile_other',
            'status' => 'failed',
            'error' => 'temporary SendGrid timeout',
        ]);
        $ignoredNoMessageId = $this->insertEmailLog([
            'tenant_id' => $this->testTenantId,
            'recipient_email' => $email,
            'provider' => 'sendgrid',
            'provider_message_id' => null,
            'status' => 'failed',
            'error' => 'temporary SendGrid timeout',
        ]);

        Http::fake(function ($request) use ($email) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $params);
            $query = (string) ($params['query'] ?? '');

            if (str_contains($query, 'sg_reconcile_target')) {
                return Http::response([
                    'messages' => [[
                        'to_email' => $email,
                        'msg_id' => 'sg_reconcile_target',
                        'status' => 'delivered',
                        'last_event_time' => now()->toIso8601String(),
                    ]],
                ], 200);
            }

            return Http::response(['messages' => []], 200);
        });

        Artisan::call('emails:reconcile-transient-failures', [
            '--minutes' => 60,
            '--limit' => 10,
        ]);

        $this->assertSame('delivered', DB::table('email_log')->where('id', $target)->value('status'));
        $this->assertSame('failed', DB::table('email_log')->where('id', $other)->value('status'));
        $this->assertSame('failed', DB::table('email_log')->where('id', $ignoredNoMessageId)->value('status'));
    }

    private function invokeWebhookEvidenceUpdate(array $event, string $type, int $tenantId): void
    {
        $controller = app(SendGridWebhookController::class);
        $method = new \ReflectionMethod(SendGridWebhookController::class, 'updateEmailLogAndSuppression');
        $method->setAccessible(true);
        $method->invoke($controller, $event, $type, $tenantId);
    }

    private function insertEmailLog(array $overrides): int
    {
        return (int) DB::table('email_log')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => null,
            'recipient_email' => 'sendgrid-evidence-' . uniqid('', true) . '@example.test',
            'category' => 'test',
            'subject' => 'SendGrid evidence test',
            'provider' => 'sendgrid',
            'status' => 'sent',
            'provider_message_id' => 'sg_default_' . uniqid(),
            'error' => null,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\ShareService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class ShareServiceEmailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_polymorphic_share_sends_auditable_social_email_under_item_tenant(): void
    {
        $tenantId = 999;
        $author = User::factory()->forTenant($tenantId)->create([
            'email' => 'share-author-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $sharer = User::factory()->forTenant($tenantId)->create([
            'first_name' => 'Sharing',
            'last_name' => 'Member',
            'email' => 'share-member-' . uniqid('', true) . '@example.test',
            'preferred_language' => 'en',
        ]);
        $postId = (int) DB::table('feed_posts')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $author->id,
            'content' => 'A public post that should trigger a share email.',
            'type' => 'post',
            'visibility' => 'public',
            'is_hidden' => 0,
            'publish_status' => 'published',
            'share_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mailer = $this->fakeMailer();
        app()->instance(EmailDispatchService::class, $mailer);

        TenantContext::setById($tenantId);

        $result = app(ShareService::class)->toggle((int) $sharer->id, 'post', $postId);

        $this->assertTrue($result['shared']);
        $this->assertSame(1, (int) DB::table('feed_posts')->where('id', $postId)->value('share_count'));
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenantId,
            'user_id' => $author->id,
            'type' => 'share',
        ]);
        $this->assertCount(1, $mailer->calls);
        $this->assertSame($author->email, $mailer->calls[0]['to']);
        $this->assertSame('social_notification', $mailer->calls[0]['options']['category']);
        $this->assertSame($tenantId, $mailer->calls[0]['options']['tenant_id']);
        $this->assertStringContainsString('/test-999/feed/posts/' . $postId, $mailer->calls[0]['body']);
    }

    private function fakeMailer(): EmailDispatchService
    {
        return new class extends EmailDispatchService {
            public array $calls = [];

            public function send(string $to, string $subject, string $body, array $options = []): bool
            {
                $this->calls[] = compact('to', 'subject', 'body', 'options');

                return true;
            }
        };
    }
}

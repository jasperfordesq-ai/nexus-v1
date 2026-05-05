<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\Newsletter;
use App\Models\User;
use App\Services\NewsletterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\Laravel\TestCase;

class NewsletterServiceTest extends TestCase
{
    use DatabaseTransactions;

    private NewsletterService $service;
    private $mockNewsletter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockNewsletter = Mockery::mock(Newsletter::class)->makePartial();
        $this->service = new NewsletterService($this->mockNewsletter);
    }

    public function test_getAll_returns_paginated_structure(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('with')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockNewsletter->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll();

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
    }

    public function test_getAll_with_status_filter(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('with')->andReturnSelf();
        $query->shouldReceive('where')->with('status', 'draft')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockNewsletter->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll(['status' => 'draft']);
        $this->assertSame([], $result['items']);
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('with')->andReturnSelf();
        $query->shouldReceive('find')->with(999)->andReturn(null);
        $this->mockNewsletter->shouldReceive('newQuery')->andReturn($query);

        $this->assertNull($this->service->getById(999));
    }

    public function test_create_returns_newsletter(): void
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->shouldReceive('save')->once();
        $newsletter->shouldReceive('fresh')->with(['creator'])->andReturn($newsletter);

        $this->mockNewsletter->shouldReceive('newInstance')->andReturn($newsletter);

        $result = $this->service->create(1, [
            'subject' => 'Test Newsletter',
            'content' => '<p>Hello</p>',
        ]);

        $this->assertInstanceOf(Newsletter::class, $result);
    }

    public function test_recipient_count_excludes_suppressed_subscribers(): void
    {
        $this->withTenant(999);

        DB::table('newsletter_subscribers')->insert([
            [
                'tenant_id' => $this->testTenantId,
                'email' => 'allowed@example.test',
                'status' => 'active',
                'confirmation_token' => Str::random(64),
                'unsubscribe_token' => Str::random(64),
                'confirmed_at' => now(),
                'source' => 'manual',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $this->testTenantId,
                'email' => 'suppressed@example.test',
                'status' => 'active',
                'confirmation_token' => Str::random(64),
                'unsubscribe_token' => Str::random(64),
                'confirmed_at' => now(),
                'source' => 'manual',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('newsletter_suppression_list')->insert([
            'tenant_id' => $this->testTenantId,
            'email' => 'suppressed@example.test',
            'reason' => 'manual',
            'bounce_count' => 0,
            'suppressed_at' => now(),
        ]);

        $this->assertSame(1, NewsletterService::getRecipientCount('subscribers_only'));
    }

    public function test_process_queue_skips_suppressed_pending_rows(): void
    {
        $this->withTenant(999);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $newsletterId = DB::table('newsletters')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Suppression send guard',
            'subject' => 'Suppression send guard',
            'content' => '<p>Hello</p>',
            'status' => 'sending',
            'target_audience' => 'subscribers_only',
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('newsletter_queue')->insert([
            'tenant_id' => $this->testTenantId,
            'newsletter_id' => $newsletterId,
            'email' => 'suppressed-pending@example.test',
            'status' => 'pending',
            'unsubscribe_token' => Str::random(64),
            'tracking_token' => Str::random(64),
            'created_at' => now(),
        ]);
        DB::table('newsletter_suppression_list')->insert([
            'tenant_id' => $this->testTenantId,
            'email' => 'suppressed-pending@example.test',
            'reason' => 'manual',
            'bounce_count' => 0,
            'suppressed_at' => now(),
        ]);

        $result = NewsletterService::processQueue($newsletterId, 10);

        $this->assertSame(['sent' => 0, 'failed' => 1], $result);
        $this->assertDatabaseHas('newsletter_queue', [
            'tenant_id' => $this->testTenantId,
            'newsletter_id' => $newsletterId,
            'email' => 'suppressed-pending@example.test',
            'status' => 'failed',
        ]);
    }

    public function test_member_recipient_count_requires_newsletter_opt_in(): void
    {
        $this->withTenant(999);

        User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'member-opt-in@example.test',
            'status' => 'active',
            'is_approved' => 1,
            'newsletter_opt_in' => 1,
        ]);
        User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'member-opt-out@example.test',
            'status' => 'active',
            'is_approved' => 1,
            'newsletter_opt_in' => 0,
        ]);

        $this->assertSame(1, NewsletterService::getRecipientCount('all_members'));
    }

    public function test_recurring_newsletters_can_queue_same_email_again(): void
    {
        $this->withTenant(999);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $newsletterId = DB::table('newsletters')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Recurring test',
            'subject' => 'Recurring test',
            'content' => '<p>Hello</p>',
            'status' => 'scheduled',
            'target_audience' => 'subscribers_only',
            'is_recurring' => 1,
            'recurring_frequency' => 'weekly',
            'recurring_day_of_week' => 1,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recipient = [
            'email' => 'repeat@example.test',
            'user_id' => null,
            'name' => 'Repeat Recipient',
            'first_name' => 'Repeat',
            'last_name' => 'Recipient',
        ];

        $method = new \ReflectionMethod(NewsletterService::class, 'queueRecipientsWithTokens');
        $method->setAccessible(true);

        $this->assertSame(1, $method->invoke(null, $newsletterId, [$recipient]));

        DB::table('newsletter_queue')
            ->where('tenant_id', $this->testTenantId)
            ->where('newsletter_id', $newsletterId)
            ->update(['status' => 'sent', 'sent_at' => now()]);

        $this->assertSame(1, $method->invoke(null, $newsletterId, [$recipient]));
        $this->assertSame(2, DB::table('newsletter_queue')->where('newsletter_id', $newsletterId)->count());
    }
}

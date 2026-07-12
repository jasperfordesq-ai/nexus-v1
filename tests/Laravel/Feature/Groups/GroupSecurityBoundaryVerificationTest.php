<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Groups;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\GroupAuditService;
use App\Services\GroupConfigurationService;
use App\Services\GroupFileService;
use App\Services\GroupInviteService;
use App\Services\GroupWebhookService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class GroupSecurityBoundaryVerificationTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private User $member;
    private User $tenantAdmin;
    private int $groupId;

    /** @var list<int> */
    private array $rateLimitGroupIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake('local');
        Storage::fake('public');

        foreach ([
            GroupConfigurationService::CONFIG_TAB_DISCUSSION,
            GroupConfigurationService::CONFIG_TAB_FILES,
            GroupConfigurationService::CONFIG_TAB_ANNOUNCEMENTS,
            GroupConfigurationService::CONFIG_TAB_QA,
            GroupConfigurationService::CONFIG_TAB_WIKI,
            GroupConfigurationService::CONFIG_TAB_MEDIA,
            GroupConfigurationService::CONFIG_TAB_CHATROOMS,
            GroupConfigurationService::CONFIG_TAB_ANALYTICS,
        ] as $setting) {
            GroupConfigurationService::set($setting, true);
        }

        $this->owner = $this->user('g18_security_owner');
        $this->member = $this->user('g18_security_member');
        $this->tenantAdmin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'username' => 'g18_security_admin_' . Str::lower(Str::random(8)),
        ]);
        TenantContext::setById($this->testTenantId);

        $this->groupId = $this->insertGroup($this->testTenantId, (int) $this->owner->id, 'G18 security group');
        $this->insertMembership($this->groupId, (int) $this->owner->id, 'owner');
        $this->insertMembership($this->groupId, (int) $this->member->id, 'member');
        $this->rateLimitGroupIds[] = $this->groupId;
        $this->clearRateLimitsForGroup($this->groupId, (int) $this->owner->id);
        Sanctum::actingAs($this->owner, ['*']);
    }

    protected function tearDown(): void
    {
        if (isset($this->owner)) {
            foreach ($this->rateLimitGroupIds as $groupId) {
                $this->clearRateLimitsForGroup($groupId, (int) $this->owner->id);
            }
        }

        parent::tearDown();
    }

    public function test_high_risk_groups_routes_keep_exact_endpoint_specific_limits(): void
    {
        $routes = [
            ['POST', "/api/v2/groups/{$this->groupId}/join", 'throttle:groups-join'],
            ['POST', "/api/v2/groups/{$this->groupId}/requests/{$this->member->id}", 'throttle:groups-join'],
            ['GET', "/api/v2/groups/{$this->groupId}/invites", 'throttle:groups-invite-read'],
            ['POST', "/api/v2/groups/{$this->groupId}/invites/link", 'throttle:groups-invite-write'],
            ['POST', "/api/v2/groups/{$this->groupId}/invites/email", 'throttle:groups-invite-write'],
            ['DELETE', "/api/v2/groups/{$this->groupId}/invites/1", 'throttle:groups-invite-write'],
            ['GET', '/api/v2/groups/invite/' . str_repeat('a', 40), 'throttle:groups-invite-read'],
            ['POST', '/api/v2/groups/invite/' . str_repeat('a', 40) . '/accept', 'throttle:groups-invite-write'],
            ['POST', "/api/v2/groups/{$this->groupId}/qa/vote", 'throttle:groups-vote'],
            ['POST', '/api/v2/group-chatrooms/1/messages', 'throttle:groups-chat-write'],
            ['POST', "/api/v2/groups/{$this->groupId}/image", 'throttle:groups-upload'],
            ['POST', "/api/v2/groups/{$this->groupId}/files", 'throttle:groups-upload'],
            ['POST', "/api/v2/groups/{$this->groupId}/media", 'throttle:groups-upload'],
            ['POST', "/api/v2/groups/{$this->groupId}/documents", 'throttle:groups-upload'],
            ['GET', "/api/v2/groups/{$this->groupId}/analytics", 'throttle:groups-analytics-read'],
            ['GET', "/api/v2/groups/{$this->groupId}/analytics/growth", 'throttle:groups-analytics-read'],
            ['GET', "/api/v2/groups/{$this->groupId}/analytics/engagement", 'throttle:groups-analytics-read'],
            ['GET', "/api/v2/groups/{$this->groupId}/analytics/contributors", 'throttle:groups-analytics-read'],
            ['GET', "/api/v2/groups/{$this->groupId}/analytics/retention", 'throttle:groups-analytics-read'],
            ['GET', "/api/v2/groups/{$this->groupId}/analytics/comparative", 'throttle:groups-analytics-read'],
            ['GET', "/api/v2/groups/{$this->groupId}/analytics/export/members", 'throttle:groups-analytics-export'],
            ['GET', "/api/v2/groups/{$this->groupId}/analytics/export/activity", 'throttle:groups-analytics-export'],
            ['GET', "/api/v2/groups/{$this->groupId}/export", 'throttle:groups-export-write'],
            ['POST', "/api/v2/groups/{$this->groupId}/exports", 'throttle:groups-export-write'],
            ['GET', "/api/v2/groups/{$this->groupId}/exports/00000000-0000-4000-8000-000000000001", 'throttle:groups-export-read'],
            ['GET', "/api/v2/groups/{$this->groupId}/exports/00000000-0000-4000-8000-000000000001/download", 'throttle:groups-export-read'],
        ];

        foreach ($routes as [$method, $uri, $expectedMiddleware]) {
            $route = Route::getRoutes()->match(Request::create($uri, $method));
            self::assertContains(
                $expectedMiddleware,
                $route->middleware(),
                "{$method} {$uri} must retain {$expectedMiddleware}.",
            );
        }
    }

    public function test_route_limiters_return_429_before_invite_or_csv_work_continues(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->apiPost("/v2/groups/{$this->groupId}/invites/link", ['expiry_days' => 1])
                ->assertCreated();
        }
        $inviteLimit = $this->apiPost("/v2/groups/{$this->groupId}/invites/link", ['expiry_days' => 1])
            ->assertTooManyRequests();
        self::assertGreaterThan(0, (int) $inviteLimit->json('retry_after'));
        self::assertSame(
            10,
            DB::table('group_invites')
                ->where('tenant_id', $this->testTenantId)
                ->where('group_id', $this->groupId)
                ->count(),
        );

        $secondGroupId = $this->insertGroup($this->testTenantId, (int) $this->owner->id, 'G18 limiter scope');
        $this->insertMembership($secondGroupId, (int) $this->owner->id, 'owner');
        $this->rateLimitGroupIds[] = $secondGroupId;
        $this->clearRateLimitsForGroup($secondGroupId, (int) $this->owner->id);
        $this->apiPost("/v2/groups/{$secondGroupId}/invites/link", ['expiry_days' => 1])
            ->assertCreated();
        $this->apiGet("/v2/groups/{$this->groupId}/invites")->assertOk();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->apiGet("/v2/groups/{$this->groupId}/analytics/export/members")->assertOk();
        }
        $analyticsLimit = $this->apiGet("/v2/groups/{$this->groupId}/analytics/export/members")
            ->assertTooManyRequests();
        self::assertGreaterThan(0, (int) $analyticsLimit->json('retry_after'));
    }

    public function test_invite_write_global_limit_cannot_be_bypassed_by_rotating_group_ids(): void
    {
        for ($groupNumber = 1; $groupNumber <= 3; $groupNumber++) {
            $groupId = $this->insertGroup(
                $this->testTenantId,
                (int) $this->owner->id,
                "G18 global limiter {$groupNumber}",
            );
            $this->insertMembership($groupId, (int) $this->owner->id, 'owner');
            $this->rateLimitGroupIds[] = $groupId;

            for ($attempt = 1; $attempt <= 10; $attempt++) {
                $this->apiPost("/v2/groups/{$groupId}/invites/link", ['expiry_days' => 1])
                    ->assertCreated();
            }
        }

        $fourthGroupId = $this->insertGroup(
            $this->testTenantId,
            (int) $this->owner->id,
            'G18 global limiter fourth group',
        );
        $this->insertMembership($fourthGroupId, (int) $this->owner->id, 'owner');
        $this->rateLimitGroupIds[] = $fourthGroupId;

        $this->apiPost("/v2/groups/{$fourthGroupId}/invites/link", ['expiry_days' => 1])
            ->assertTooManyRequests();
        self::assertSame(
            30,
            DB::table('group_invites')
                ->where('tenant_id', $this->testTenantId)
                ->where('invited_by', $this->owner->id)
                ->count(),
        );
    }

    public function test_file_and_media_uploads_reject_spoofed_mime_size_and_quota_and_compensate_storage(): void
    {
        $spoofedFile = UploadedFile::fake()->createWithContent(
            'payload.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );
        $this->apiPost("/v2/groups/{$this->groupId}/files", ['file' => $spoofedFile])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'INVALID_TYPE');
        Storage::disk('local')->assertDirectoryEmpty('groups');

        $oversizedFile = UploadedFile::fake()->create(
            'oversized.pdf',
            intdiv(GroupFileService::MAX_FILE_SIZE, 1024) + 1,
            'application/pdf',
        );
        $this->apiPost("/v2/groups/{$this->groupId}/files", ['file' => $oversizedFile])
            ->assertStatus(413)
            ->assertJsonPath('errors.0.code', 'FILE_TOO_LARGE');
        Storage::disk('local')->assertDirectoryEmpty('groups');

        DB::table('group_files')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'file_name' => 'quota-sentinel.bin',
            'file_path' => "groups/{$this->testTenantId}/{$this->groupId}/quota-sentinel.bin",
            'file_type' => 'application/octet-stream',
            'file_size' => GroupFileService::MAX_GROUP_STORAGE,
            'uploaded_by' => $this->owner->id,
            'download_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->apiPost("/v2/groups/{$this->groupId}/files", [
            'file' => UploadedFile::fake()->createWithContent('small.txt', 'quota must reject these bytes'),
        ])
            ->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'GROUP_QUOTA_EXCEEDED');
        Storage::disk('local')->assertDirectoryEmpty('groups');
        self::assertSame(1, DB::table('group_files')->where('group_id', $this->groupId)->count());

        $spoofedMedia = UploadedFile::fake()->createWithContent(
            'payload.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(2)</script></svg>',
        );
        $this->apiPost("/v2/groups/{$this->groupId}/media", ['file' => $spoofedMedia])
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');

        $oversizedMedia = UploadedFile::fake()->create('oversized.mp4', (50 * 1024) + 1, 'video/mp4');
        $this->apiPost("/v2/groups/{$this->groupId}/media", ['file' => $oversizedMedia])
            ->assertStatus(413)
            ->assertJsonPath('errors.0.code', 'FILE_TOO_LARGE');
        Storage::disk('local')->assertDirectoryEmpty('groups');
    }

    public function test_foreign_tokens_and_nested_objects_are_concealed_and_never_mutated(): void
    {
        $foreignTenantId = $this->createForeignTenant();
        $foreignOwner = User::factory()->forTenant($foreignTenantId)->create([
            'username' => 'g18_foreign_owner_' . Str::lower(Str::random(8)),
        ]);
        TenantContext::setById($this->testTenantId);

        $foreignGroupId = $this->insertGroup($foreignTenantId, (int) $foreignOwner->id, 'FOREIGN_GROUP_CANARY');
        $foreignToken = substr(hash('sha256', 'FOREIGN_TOKEN_CANARY_' . Str::random(12)), 0, 40);
        $foreignInviteId = (int) DB::table('group_invites')->insertGetId([
            'tenant_id' => $foreignTenantId,
            'group_id' => $foreignGroupId,
            'invited_by' => $foreignOwner->id,
            'invite_type' => 'link',
            'email' => null,
            'token' => $foreignToken,
            'status' => GroupInviteService::STATUS_PENDING,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignWebhookId = (int) DB::table('group_webhooks')->insertGetId([
            'tenant_id' => $foreignTenantId,
            'group_id' => $foreignGroupId,
            'url' => 'https://8.8.8.8/FOREIGN_WEBHOOK_CANARY',
            'events' => json_encode([GroupWebhookService::EVENT_MEMBER_JOINED], JSON_THROW_ON_ERROR),
            'secret' => null,
            'is_active' => true,
            'failure_count' => 0,
            'disabled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignExportId = (string) Str::uuid();
        DB::table('group_data_exports')->insert([
            'id' => $foreignExportId,
            'tenant_id' => $foreignTenantId,
            'group_id' => $foreignGroupId,
            'requested_by' => $foreignOwner->id,
            'status' => 'queued',
            'attempts' => 0,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->tenantAdmin, ['*']);
        TenantContext::setById($this->testTenantId);

        $responses = [
            $this->apiGet("/v2/groups/{$foreignGroupId}")->assertNotFound(),
            $this->apiGet("/v2/groups/invite/{$foreignToken}")->assertNotFound(),
            $this->apiPost("/v2/groups/invite/{$foreignToken}/accept")->assertNotFound(),
            $this->apiDelete("/v2/groups/{$this->groupId}/invites/{$foreignInviteId}")->assertNotFound(),
            $this->apiDelete("/v2/groups/{$this->groupId}/webhooks/{$foreignWebhookId}")->assertNotFound(),
            $this->apiPut("/v2/groups/{$this->groupId}/webhooks/{$foreignWebhookId}/toggle", ['is_active' => false])
                ->assertNotFound(),
            $this->apiGet("/v2/groups/{$this->groupId}/exports/{$foreignExportId}")->assertNotFound(),
            $this->apiGet("/v2/groups/{$this->groupId}/exports/{$foreignExportId}/download")->assertNotFound(),
        ];

        foreach ($responses as $response) {
            $body = (string) $response->getContent();
            self::assertStringNotContainsString('FOREIGN_GROUP_CANARY', $body);
            self::assertStringNotContainsString('FOREIGN_WEBHOOK_CANARY', $body);
            self::assertStringNotContainsString($foreignToken, $body);
        }
        self::assertSame('pending', DB::table('group_invites')->where('id', $foreignInviteId)->value('status'));
        self::assertTrue((bool) DB::table('group_webhooks')->where('id', $foreignWebhookId)->value('is_active'));
        self::assertSame('queued', DB::table('group_data_exports')->where('id', $foreignExportId)->value('status'));
    }

    public function test_url_only_legacy_media_never_exports_a_shareable_direct_url(): void
    {
        $legacyUrl = 'https://public.example/LEGACY_PRIVATE_MEDIA_CANARY.jpg';
        $mediaId = (int) DB::table('group_media')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'uploaded_by' => $this->owner->id,
            'media_type' => 'image',
            'file_path' => null,
            'url' => $legacyUrl,
            'thumbnail_path' => null,
            'caption' => 'Legacy URL-only media awaiting private-storage migration',
            'file_size' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiGet("/v2/groups/{$this->groupId}/media")->assertOk();
        $item = collect($response->json('data.items'))->firstWhere('id', $mediaId);

        self::assertNull($item, 'URL-only legacy media must stay hidden until private bytes are migrated.');
        self::assertStringNotContainsString($legacyUrl, (string) $response->getContent());
        self::assertStringNotContainsString('LEGACY_PRIVATE_MEDIA_CANARY', (string) $response->getContent());
    }

    public function test_admin_transfer_clone_and_foreign_lifecycle_routes_are_tenant_scoped_and_audited(): void
    {
        Sanctum::actingAs($this->tenantAdmin, ['*']);

        $this->apiPost("/v2/admin/groups/{$this->groupId}/transfer-ownership", [
            'new_owner_id' => $this->member->id,
        ])->assertOk();
        self::assertSame((int) $this->member->id, (int) DB::table('groups')->where('id', $this->groupId)->value('owner_id'));
        self::assertSame('owner', DB::table('group_members')->where('group_id', $this->groupId)->where('user_id', $this->member->id)->value('role'));
        self::assertSame('admin', DB::table('group_members')->where('group_id', $this->groupId)->where('user_id', $this->owner->id)->value('role'));
        $transferAudit = DB::table('group_audit_log')
            ->where('tenant_id', $this->testTenantId)
            ->where('group_id', $this->groupId)
            ->where('action', GroupAuditService::ACTION_GROUP_UPDATED)
            ->orderByDesc('id')
            ->first();
        self::assertNotNull($transferAudit);
        $transferDetails = json_decode((string) $transferAudit->details, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ownership_transferred', $transferDetails['action'] ?? null);
        self::assertSame((int) $this->owner->id, (int) ($transferDetails['previous_owner_id'] ?? 0));
        self::assertSame((int) $this->member->id, (int) ($transferDetails['new_owner_id'] ?? 0));

        $cloneResponse = $this->apiPost("/v2/admin/groups/{$this->groupId}/clone", [
            'name' => 'G18 audited clone',
            'clone_members' => true,
        ])->assertOk();
        $cloneId = (int) $cloneResponse->json('data.id');
        self::assertGreaterThan(0, $cloneId);
        $cloneAudit = DB::table('group_audit_log')
            ->where('tenant_id', $this->testTenantId)
            ->where('group_id', $cloneId)
            ->where('action', GroupAuditService::ACTION_GROUP_CREATED)
            ->orderByDesc('id')
            ->first();
        self::assertNotNull($cloneAudit);
        $cloneDetails = json_decode((string) $cloneAudit->details, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('group_cloned', $cloneDetails['action'] ?? null);
        self::assertSame($this->groupId, (int) ($cloneDetails['source_group_id'] ?? 0));

        $foreignTenantId = $this->createForeignTenant();
        $foreignOwner = User::factory()->forTenant($foreignTenantId)->create();
        $foreignMember = User::factory()->forTenant($foreignTenantId)->create();
        TenantContext::setById($this->testTenantId);
        $foreignGroupId = $this->insertGroup($foreignTenantId, (int) $foreignOwner->id, 'FOREIGN_LIFECYCLE_CANARY');
        DB::table('group_members')->insert([
            'tenant_id' => $foreignTenantId,
            'group_id' => $foreignGroupId,
            'user_id' => $foreignMember->id,
            'role' => 'member',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupCount = DB::table('groups')->count();
        $this->apiPost("/v2/admin/groups/{$foreignGroupId}/transfer-ownership", [
            'new_owner_id' => $foreignMember->id,
        ])->assertNotFound();
        $this->apiPost("/v2/admin/groups/{$foreignGroupId}/merge", [
            'target_group_id' => $this->groupId,
        ])->assertNotFound();
        $this->apiPost("/v2/admin/groups/{$foreignGroupId}/clone", [
            'name' => 'Forbidden clone',
            'clone_members' => true,
        ])->assertNotFound();

        self::assertSame((int) $foreignOwner->id, (int) DB::table('groups')->where('id', $foreignGroupId)->value('owner_id'));
        self::assertSame('active', DB::table('groups')->where('id', $foreignGroupId)->value('status'));
        self::assertSame($groupCount, DB::table('groups')->count());
    }

    public function test_untrusted_group_and_rich_text_values_are_json_only_and_audit_secrets_are_redacted(): void
    {
        $maliciousName = '<img src=x onerror=alert(1)>Group';
        $maliciousDescription = '<script>alert(2)</script>Description';
        $maliciousRichText = '<a href="javascript:alert(3)" onclick="alert(4)">Content</a><script>alert(5)</script>';
        DB::table('groups')->where('id', $this->groupId)->update([
            'name' => $maliciousName,
            'description' => $maliciousDescription,
        ]);
        $announcementId = (int) DB::table('group_announcements')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'title' => $maliciousName,
            'content' => $maliciousRichText,
            'is_pinned' => false,
            'priority' => 0,
            'created_by' => $this->owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $questionId = (int) DB::table('group_questions')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'user_id' => $this->owner->id,
            'title' => $maliciousName,
            'body' => $maliciousRichText,
            'answer_count' => 0,
            'vote_count' => 0,
            'view_count' => 0,
            'is_closed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('group_wiki_pages')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'parent_id' => null,
            'title' => $maliciousName,
            'slug' => 'malicious-content',
            'content' => $maliciousRichText,
            'created_by' => $this->owner->id,
            'last_edited_by' => $this->owner->id,
            'sort_order' => 0,
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $responses = [
            $this->apiGet("/v2/groups/{$this->groupId}")->assertOk(),
            $this->apiGet("/v2/groups/{$this->groupId}/announcements")->assertOk(),
            $this->apiGet("/v2/groups/{$this->groupId}/questions/{$questionId}")->assertOk(),
            $this->apiGet("/v2/groups/{$this->groupId}/wiki/malicious-content")->assertOk(),
        ];
        foreach ($responses as $response) {
            self::assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
            self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        }
        self::assertSame($maliciousName, $responses[0]->json('data.name'));
        self::assertSame($maliciousRichText, $responses[2]->json('data.body'));

        $auditId = GroupAuditService::log(GroupAuditService::ACTION_GROUP_UPDATED, $this->groupId, (int) $this->owner->id, [
            'password' => 'RAW_PASSWORD_CANARY',
            'authorization_header' => 'RAW_AUTH_CANARY',
            'nested' => [
                'webhook_secret' => 'RAW_SECRET_CANARY',
                'invite_token' => 'RAW_TOKEN_CANARY',
                'safe' => 'visible',
            ],
        ]);
        $storedJson = (string) DB::table('group_audit_log')->where('id', $auditId)->value('details');
        foreach (['RAW_PASSWORD_CANARY', 'RAW_AUTH_CANARY', 'RAW_SECRET_CANARY', 'RAW_TOKEN_CANARY'] as $secret) {
            self::assertStringNotContainsString($secret, $storedJson);
        }
        $stored = json_decode($storedJson, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('[REDACTED]', $stored['password']);
        self::assertSame('[REDACTED]', $stored['authorization_header']);
        self::assertSame('[REDACTED]', $stored['nested']['webhook_secret']);
        self::assertSame('[REDACTED]', $stored['nested']['invite_token']);
        self::assertSame('visible', $stored['nested']['safe']);
        self::assertSame($announcementId, (int) DB::table('group_announcements')->where('id', $announcementId)->value('id'));
    }

    private function user(string $prefix): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'username' => $prefix . '_' . Str::lower(Str::random(8)),
        ]);
    }

    private function insertGroup(int $tenantId, int $ownerId, string $name): int
    {
        return (int) DB::table('groups')->insertGetId([
            'tenant_id' => $tenantId,
            'owner_id' => $ownerId,
            'name' => $name . ' ' . Str::lower(Str::random(8)),
            'description' => 'Deterministic G18 security verification fixture.',
            'visibility' => 'private',
            'status' => 'active',
            'is_active' => true,
            'cached_member_count' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertMembership(int $groupId, int $userId, string $role): void
    {
        DB::table('group_members')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $groupId,
            'user_id' => $userId,
            'role' => $role,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createForeignTenant(): int
    {
        $tenant = (array) DB::table('tenants')->where('id', $this->testTenantId)->first();
        unset($tenant['id']);
        $marker = Str::lower(Str::random(10));
        $tenant['name'] = 'G18 foreign tenant ' . $marker;
        $tenant['slug'] = 'g18-foreign-' . $marker;
        $tenant['domain'] = null;
        if (array_key_exists('accessible_domain', $tenant)) {
            $tenant['accessible_domain'] = null;
        }
        $tenant['created_at'] = now();
        $tenant['updated_at'] = now();

        return (int) DB::table('tenants')->insertGetId($tenant);
    }

    private function clearRateLimitsForGroup(int $groupId, int $userId): void
    {
        foreach ([
            'groups-join' => 'join',
            'groups-invite-read' => 'invite-read',
            'groups-invite-write' => 'invite-write',
            'groups-vote' => 'vote',
            'groups-upload' => 'upload',
            'groups-analytics-read' => 'analytics-read',
            'groups-analytics-export' => 'analytics-export',
            'groups-export-write' => 'export-write',
            'groups-export-read' => 'export-read',
        ] as $limiterName => $family) {
            $actorKey = "groups:{$family}:tenant:{$this->testTenantId}:user:{$userId}:all";
            RateLimiter::clear(md5($limiterName . $actorKey));
            RateLimiter::clear(md5($limiterName . $actorKey . ":group:{$groupId}"));
        }

        foreach (['groups_files_upload', 'groups_media_upload'] as $action) {
            RateLimiter::clear("api:{$action}:user:{$userId}");
        }
    }
}

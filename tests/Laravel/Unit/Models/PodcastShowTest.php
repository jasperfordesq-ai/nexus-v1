<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Core\TenantContext;
use App\Models\Concerns\HasTenantScope;
use App\Models\PodcastShow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

class PodcastShowTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99767;

    /** @var int Reusable owner user id */
    private int $ownerId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'Test Tenant 99767',
                'slug'              => 'test-99767',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );
        TenantContext::setById(self::TENANT_ID);

        $this->ownerId = DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'name'        => 'Show Owner',
            'email'       => 'show-owner-' . uniqid() . '@example.test',
            'is_active'   => 1,
            'is_approved' => 1,
            'status'      => 'active',
            'created_at'  => now(),
        ]);
    }

    // -------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------

    private function seedShow(array $overrides = []): PodcastShow
    {
        $defaults = [
            'tenant_id'         => self::TENANT_ID,
            'owner_user_id'     => $this->ownerId,
            'title'             => 'Show ' . uniqid(),
            'slug'              => 'show-' . uniqid(),
            'language'          => 'en',
            'visibility'        => 'public',
            'status'            => 'draft',
            'moderation_status' => 'pending',
            'episode_count'     => 0,
            'subscriber_count'  => 0,
            'explicit'          => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ];

        $data = array_merge($defaults, $overrides);
        $id   = DB::table('podcast_shows')->insertGetId($data);

        return PodcastShow::findOrFail($id);
    }

    // -------------------------------------------------------------------
    // scopePublished — happy path
    // -------------------------------------------------------------------

    public function test_scope_published_returns_published_and_approved_shows(): void
    {
        $show = $this->seedShow([
            'status'            => 'published',
            'moderation_status' => 'approved',
        ]);

        $ids = PodcastShow::published()->pluck('id')->toArray();
        $this->assertContains($show->id, $ids);
    }

    // -------------------------------------------------------------------
    // scopePublished — draft excluded
    // -------------------------------------------------------------------

    public function test_scope_published_excludes_draft_shows(): void
    {
        $show = $this->seedShow([
            'status'            => 'draft',
            'moderation_status' => 'approved',
        ]);

        $ids = PodcastShow::published()->pluck('id')->toArray();
        $this->assertNotContains($show->id, $ids);
    }

    // -------------------------------------------------------------------
    // scopePublished — pending moderation excluded
    // -------------------------------------------------------------------

    public function test_scope_published_excludes_pending_moderation_shows(): void
    {
        $show = $this->seedShow([
            'status'            => 'published',
            'moderation_status' => 'pending',
        ]);

        $ids = PodcastShow::published()->pluck('id')->toArray();
        $this->assertNotContains($show->id, $ids);
    }

    // -------------------------------------------------------------------
    // scopePublished — archived excluded
    // -------------------------------------------------------------------

    public function test_scope_published_excludes_archived_shows(): void
    {
        $show = $this->seedShow([
            'status'            => 'archived',
            'moderation_status' => 'approved',
        ]);

        $ids = PodcastShow::published()->pluck('id')->toArray();
        $this->assertNotContains($show->id, $ids);
    }

    // -------------------------------------------------------------------
    // scopePublished — rejected moderation excluded
    // -------------------------------------------------------------------

    public function test_scope_published_excludes_rejected_shows(): void
    {
        $show = $this->seedShow([
            'status'            => 'published',
            'moderation_status' => 'rejected',
        ]);

        $ids = PodcastShow::published()->pluck('id')->toArray();
        $this->assertNotContains($show->id, $ids);
    }

    // -------------------------------------------------------------------
    // Casts
    // -------------------------------------------------------------------

    public function test_explicit_cast_to_boolean(): void
    {
        $show = $this->seedShow(['explicit' => 1]);
        $this->assertTrue($show->explicit);

        $show2 = $this->seedShow(['explicit' => 0]);
        $this->assertFalse($show2->explicit);
    }

    public function test_episode_count_and_subscriber_count_cast_to_integer(): void
    {
        $show = $this->seedShow([
            'episode_count'    => 42,
            'subscriber_count' => 100,
        ]);

        $this->assertSame(42, $show->episode_count);
        $this->assertSame(100, $show->subscriber_count);
        $this->assertIsInt($show->episode_count);
        $this->assertIsInt($show->subscriber_count);
    }

    // -------------------------------------------------------------------
    // Hidden fields not exposed
    // -------------------------------------------------------------------

    public function test_tenant_id_is_hidden_in_serialization(): void
    {
        $show = $this->seedShow();
        $arr  = $show->toArray();
        $this->assertArrayNotHasKey('tenant_id', $arr);
    }

    // -------------------------------------------------------------------
    // Tenant scope
    // -------------------------------------------------------------------

    public function test_tenant_scope_excludes_other_tenant_rows(): void
    {
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID + 1],
            [
                'name'              => 'Other Tenant',
                'slug'              => 'other-99767',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        $otherOwnerId = DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID + 1,
            'name'        => 'Other Show Owner',
            'email'       => 'other-show-owner-' . uniqid() . '@example.test',
            'is_active'   => 1,
            'is_approved' => 1,
            'status'      => 'active',
            'created_at'  => now(),
        ]);

        $otherId = DB::table('podcast_shows')->insertGetId([
            'tenant_id'         => self::TENANT_ID + 1,
            'owner_user_id'     => $otherOwnerId,
            'title'             => 'Other Show',
            'slug'              => 'other-show-' . uniqid(),
            'language'          => 'en',
            'visibility'        => 'public',
            'status'            => 'published',
            'moderation_status' => 'approved',
            'episode_count'     => 0,
            'subscriber_count'  => 0,
            'explicit'          => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
        $found = PodcastShow::find($otherId);
        $this->assertNull($found, 'Tenant scope should exclude shows from another tenant');
    }

    // -------------------------------------------------------------------
    // Traits
    // -------------------------------------------------------------------

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(HasTenantScope::class, class_uses_recursive(PodcastShow::class));
    }
}

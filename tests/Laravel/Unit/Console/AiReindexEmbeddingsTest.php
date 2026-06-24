<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Console;

use App\Jobs\ReindexEmbeddingJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for AiReindexEmbeddings Artisan command (ai:reindex).
 *
 * Uses unique tenant ID 99734 to avoid collisions with other test files.
 */
class AiReindexEmbeddingsTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99734;

    /** @var int|null Seeded user ID for FK-satisfying listing inserts */
    private ?int $seedUserId = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Insert isolated test tenant
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'ReindexTest Tenant',
                'slug'              => 'reindex-test-99734',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        // Seed a minimal user for listings FK (listings.user_id → users.id)
        $this->seedUserId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Reindex Test User 99734',
            'email'      => 'reindex99734@test.invalid',
            'role'       => 'member',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Core\TenantContext::setById(self::TENANT_ID);

        Queue::fake();
    }

    // ------------------------------------------------------------------
    // No-op: empty table → no jobs dispatched
    // ------------------------------------------------------------------

    public function test_no_jobs_dispatched_when_no_listings_for_tenant(): void
    {
        // Ensure there are no listings for our isolated tenant
        DB::table('listings')->where('tenant_id', self::TENANT_ID)->delete();

        $this->artisan('ai:reindex', [
            '--type'   => 'listing',
            '--tenant' => (string) self::TENANT_ID,
        ])->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    // ------------------------------------------------------------------
    // Happy path: one listing → one job dispatched
    // ------------------------------------------------------------------

    public function test_dispatches_one_job_per_listing_row(): void
    {
        $listingId = DB::table('listings')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'user_id'    => $this->seedUserId,
            'title'      => 'Test listing for reindex',
            'type'       => 'offer',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('ai:reindex', [
            '--type'   => 'listing',
            '--tenant' => (string) self::TENANT_ID,
        ])->assertExitCode(0);

        Queue::assertPushed(ReindexEmbeddingJob::class, 1);
        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $job) use ($listingId) {
            return $job->contentType === 'listing'
                && $job->contentId === $listingId
                && $job->tenantId === self::TENANT_ID;
        });
    }

    // ------------------------------------------------------------------
    // Multiple rows → multiple jobs
    // ------------------------------------------------------------------

    public function test_dispatches_one_job_per_row_for_multiple_listings(): void
    {
        // Insert 3 listings for our tenant
        for ($i = 0; $i < 3; $i++) {
            DB::table('listings')->insert([
                'tenant_id'  => self::TENANT_ID,
                'user_id'    => $this->seedUserId,
                'title'      => "Listing $i",
                'type'       => 'offer',
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->artisan('ai:reindex', [
            '--type'   => 'listing',
            '--tenant' => (string) self::TENANT_ID,
        ])->assertExitCode(0);

        Queue::assertPushed(ReindexEmbeddingJob::class, 3);
    }

    // ------------------------------------------------------------------
    // --limit caps the number of dispatched jobs
    // ------------------------------------------------------------------

    public function test_limit_option_caps_jobs_dispatched(): void
    {
        for ($i = 0; $i < 5; $i++) {
            DB::table('listings')->insert([
                'tenant_id'  => self::TENANT_ID,
                'user_id'    => $this->seedUserId,
                'title'      => "Limited listing $i",
                'type'       => 'offer',
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->artisan('ai:reindex', [
            '--type'   => 'listing',
            '--tenant' => (string) self::TENANT_ID,
            '--limit'  => '2',
        ])->assertExitCode(0);

        Queue::assertPushed(ReindexEmbeddingJob::class, 2);
    }

    // ------------------------------------------------------------------
    // Unknown type → FAILURE exit code, no jobs
    // ------------------------------------------------------------------

    public function test_unknown_type_returns_failure(): void
    {
        $this->artisan('ai:reindex', [
            '--type' => 'nonexistent_type_xyz',
        ])->assertExitCode(1);

        Queue::assertNothingPushed();
    }

    // ------------------------------------------------------------------
    // --force bypasses the 30-day embedding skip filter
    // ------------------------------------------------------------------

    public function test_force_flag_requeues_row_that_already_has_recent_embedding(): void
    {
        $listingId = DB::table('listings')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'user_id'    => $this->seedUserId,
            'title'      => 'Already embedded listing',
            'type'       => 'offer',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simulate a fresh embedding for this row
        DB::table('content_embeddings')->updateOrInsert(
            [
                'tenant_id'    => self::TENANT_ID,
                'content_type' => 'listing',
                'content_id'   => $listingId,
            ],
            [
                'model'      => 'text-embedding-3-small',
                'embedding'  => '[]',
                'created_at' => now(),
                'updated_at' => now(), // within 30 days
            ]
        );

        // Without --force the row is skipped
        $this->artisan('ai:reindex', [
            '--type'   => 'listing',
            '--tenant' => (string) self::TENANT_ID,
        ])->assertExitCode(0);

        Queue::assertNothingPushed();

        // With --force the row must be re-queued
        $this->artisan('ai:reindex', [
            '--type'   => 'listing',
            '--tenant' => (string) self::TENANT_ID,
            '--force'  => true,
        ])->assertExitCode(0);

        Queue::assertPushed(ReindexEmbeddingJob::class, 1);
    }

    // ------------------------------------------------------------------
    // kb_article type only picks published articles
    // ------------------------------------------------------------------

    public function test_kb_article_type_skips_unpublished_articles(): void
    {
        // Insert an unpublished KB article
        DB::table('knowledge_base_articles')->insert([
            'tenant_id'    => self::TENANT_ID,
            'title'        => 'Unpublished article',
            'slug'         => 'unpublished-99734',
            'content_type' => 'html',
            'is_published' => false,
            'sort_order'   => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->artisan('ai:reindex', [
            '--type'   => 'kb_article',
            '--tenant' => (string) self::TENANT_ID,
        ])->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_kb_article_type_dispatches_job_for_published_article(): void
    {
        DB::table('knowledge_base_articles')->insert([
            'tenant_id'    => self::TENANT_ID,
            'title'        => 'Published article',
            'slug'         => 'published-99734',
            'content_type' => 'html',
            'is_published' => true,
            'sort_order'   => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->artisan('ai:reindex', [
            '--type'   => 'kb_article',
            '--tenant' => (string) self::TENANT_ID,
        ])->assertExitCode(0);

        Queue::assertPushed(ReindexEmbeddingJob::class, 1);
    }

    // ------------------------------------------------------------------
    // All types (no --type flag): command still exits 0
    // setUp() seeds exactly one user for tenant 99734 (for listing FKs).
    // Running without --type will reindex all 7 content types; only the
    // `user` bucket will find that seeded row, dispatching exactly 1 job.
    // ------------------------------------------------------------------

    public function test_all_types_exit_success_dispatches_jobs_for_seeded_data(): void
    {
        // No listings, events, groups, etc. — only the setUp user row
        $this->artisan('ai:reindex', [
            '--tenant' => (string) self::TENANT_ID,
        ])->assertExitCode(0);

        // The user row is picked up; exactly 1 job for type=user
        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $job) {
            return $job->contentType === 'user' && $job->tenantId === self::TENANT_ID;
        });
    }
}

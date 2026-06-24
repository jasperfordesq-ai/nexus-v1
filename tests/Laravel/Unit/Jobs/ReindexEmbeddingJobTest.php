<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Jobs;

use App\Jobs\ReindexEmbeddingJob;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * ReindexEmbeddingJobTest
 *
 * ReindexEmbeddingJob calls EmbeddingService::delete() or generateFor() depending on
 * whether the target row exists in the database.
 *
 * EmbeddingService::generateFor() ultimately needs an OpenAI API key (stored in
 * ai_settings) and a real HTTP call; without them the store() method returns early
 * after finding no key. We therefore test:
 *   - Job properties ($tries, $backoff)
 *   - Unknown contentType → fetchRow() returns null → delete() is called
 *   - Row exists → generateFor() is invoked (no OpenAI key in test env → store no-ops)
 *   - Row deleted between dispatch and handle → delete() is called
 *
 * We inject a mock EmbeddingService to assert delete/generateFor calls without
 * needing OpenAI or content_embeddings to exist.
 */
class ReindexEmbeddingJobTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    // ── helpers ───────────────────────────────────────────────────────────────

    /** Insert a minimal user row for seeding. */
    private function insertEmbedUser(): int
    {
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'EmbedUser',
            'first_name' => 'Embed',
            'last_name'  => 'User',
            'email'      => 'embed.' . uniqid('', true) . '@example.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Insert a minimal listing row for the embedding job to find (category_id nullable). */
    private function insertListing(string $title = 'Test Listing'): int
    {
        $userId = $this->insertEmbedUser();

        // category_id is nullable — avoid querying listing_categories which may
        // not exist in the test DB schema dump.
        return DB::table('listings')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'user_id'     => $userId,
            'category_id' => null,
            'title'       => $title,
            'description' => 'A test listing for embedding',
            'type'        => 'offer',
            'status'      => 'active',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    /** Job exposes the expected $tries and $backoff values. */
    public function test_job_has_correct_configuration(): void
    {
        $job = new ReindexEmbeddingJob('listing', 1, self::TENANT_ID);
        $this->assertSame(3, $job->tries);
        $this->assertSame(30, $job->backoff);
    }

    /** Constructor stores all three properties. */
    public function test_job_stores_constructor_arguments(): void
    {
        $job = new ReindexEmbeddingJob('user', 99, 7);
        $this->assertSame('user', $job->contentType);
        $this->assertSame(99, $job->contentId);
        $this->assertSame(7, $job->tenantId);
    }

    /**
     * Unknown contentType → fetchRow() returns null → EmbeddingService::delete() is called.
     */
    public function test_handle_calls_delete_for_unknown_content_type(): void
    {
        $service = \Mockery::mock(EmbeddingService::class);
        $service->shouldReceive('delete')
            ->once()
            ->with(self::TENANT_ID, 'unknown_type', 42);
        $service->shouldNotReceive('generateFor');

        $job = new ReindexEmbeddingJob('unknown_type', 42, self::TENANT_ID);
        $job->handle($service);
        $this->assertTrue(true);
    }

    /**
     * Row does not exist in the DB → delete() is called.
     */
    public function test_handle_calls_delete_when_row_not_found(): void
    {
        $service = \Mockery::mock(EmbeddingService::class);
        $service->shouldReceive('delete')
            ->once()
            ->with(self::TENANT_ID, 'listing', 9999999);
        $service->shouldNotReceive('generateFor');

        $job = new ReindexEmbeddingJob('listing', 9999999, self::TENANT_ID);
        $job->handle($service);
        $this->assertTrue(true);
    }

    /**
     * Row exists in the DB → generateFor() is called (with the row data).
     */
    public function test_handle_calls_generate_for_when_row_exists(): void
    {
        $listingId = $this->insertListing('Embed Test Listing');

        $service = \Mockery::mock(EmbeddingService::class);
        $service->shouldReceive('generateFor')
            ->once()
            ->with('listing', \Mockery::on(fn ($row) =>
                is_array($row)
                && (int)($row['id'] ?? 0) === $listingId
                && (int)($row['tenant_id'] ?? 0) === self::TENANT_ID
            ));
        $service->shouldNotReceive('delete');

        $job = new ReindexEmbeddingJob('listing', $listingId, self::TENANT_ID);
        $job->handle($service);
        $this->assertTrue(true);
    }

    /**
     * Row exists for 'user' content type → generateFor() is called.
     */
    public function test_handle_calls_generate_for_user_content_type(): void
    {
        $userId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'EmbedUserTest',
            'first_name' => 'Embed',
            'last_name'  => 'User',
            'email'      => 'embeduser.' . uniqid('', true) . '@example.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = \Mockery::mock(EmbeddingService::class);
        $service->shouldReceive('generateFor')
            ->once()
            ->with('user', \Mockery::on(fn ($row) =>
                is_array($row)
                && (int)($row['id'] ?? 0) === $userId
            ));
        $service->shouldNotReceive('delete');

        $job = new ReindexEmbeddingJob('user', $userId, self::TENANT_ID);
        $job->handle($service);
        $this->assertTrue(true);
    }

    /**
     * Row exists in a different tenant → fetchRow() returns null (tenant scope) → delete().
     */
    public function test_handle_calls_delete_when_row_belongs_to_different_tenant(): void
    {
        $listingId = $this->insertListing('Cross-tenant listing');

        $service = \Mockery::mock(EmbeddingService::class);
        $service->shouldReceive('delete')
            ->once()
            ->with(999, 'listing', $listingId);
        $service->shouldNotReceive('generateFor');

        // Pass tenant_id=999 (wrong tenant) so the WHERE clause finds nothing.
        $job = new ReindexEmbeddingJob('listing', $listingId, 999);
        $job->handle($service);
        $this->assertTrue(true);
    }

    /**
     * All recognised content types are covered by the internal map.
     * Verify that a known type hits generateFor (not delete) when the row exists.
     * Uses groups table with the real columns from the schema dump:
     *   owner_id (not creator_id), visibility enum (not privacy), no status column.
     */
    public function test_handle_supports_group_content_type(): void
    {
        $userId = $this->insertEmbedUser();

        $groupId = DB::table('groups')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'owner_id'    => $userId,
            'name'        => 'Embed Test Group',
            'slug'        => 'embed-grp-' . uniqid('', true),
            'description' => 'testing',
            'visibility'  => 'public',
            'is_active'   => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $service = \Mockery::mock(EmbeddingService::class);
        $service->shouldReceive('generateFor')
            ->once()
            ->with('group', \Mockery::on(fn ($row) =>
                is_array($row) && (int)($row['id'] ?? 0) === $groupId
            ));
        $service->shouldNotReceive('delete');

        $job = new ReindexEmbeddingJob('group', $groupId, self::TENANT_ID);
        $job->handle($service);
        $this->assertTrue(true);
    }
}

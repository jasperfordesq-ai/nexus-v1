<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers;

use App\Jobs\ReindexEmbeddingJob;
use App\Models\MarketplaceListing;
use App\Observers\MarketplaceListingObserver;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MarketplaceListingObserverTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    // -----------------------------------------------------------------------
    // created()
    // -----------------------------------------------------------------------

    public function test_created_dispatches_reindex_job(): void
    {
        $listing = new MarketplaceListing();
        $listing->id = 1;
        $listing->tenant_id = 2;

        (new MarketplaceListingObserver())->created($listing);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $job) {
            return $job->contentType === 'marketplace'
                && $job->contentId === 1
                && $job->tenantId === 2;
        });
    }

    public function test_created_does_not_dispatch_when_id_is_zero(): void
    {
        $listing = new MarketplaceListing();
        $listing->id = 0;
        $listing->tenant_id = 2;

        (new MarketplaceListingObserver())->created($listing);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
    }

    public function test_created_does_not_dispatch_when_tenant_id_is_zero(): void
    {
        $listing = new MarketplaceListing();
        $listing->id = 5;
        $listing->tenant_id = 0;

        (new MarketplaceListingObserver())->created($listing);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
    }

    // -----------------------------------------------------------------------
    // updated()
    // -----------------------------------------------------------------------

    public function test_updated_dispatches_reindex_when_title_dirty(): void
    {
        $listing = Mockery::mock(MarketplaceListing::class)->makePartial();
        $listing->id = 10;
        $listing->tenant_id = 2;
        $listing->shouldReceive('getDirty')->andReturn(['title' => 'Vintage Chair']);

        (new MarketplaceListingObserver())->updated($listing);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $job) {
            return $job->contentType === 'marketplace'
                && $job->contentId === 10
                && $job->tenantId === 2;
        });
    }

    public function test_updated_dispatches_reindex_when_tagline_dirty(): void
    {
        $listing = Mockery::mock(MarketplaceListing::class)->makePartial();
        $listing->id = 11;
        $listing->tenant_id = 2;
        $listing->shouldReceive('getDirty')->andReturn(['tagline' => 'Great condition']);

        (new MarketplaceListingObserver())->updated($listing);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $job) {
            return $job->contentType === 'marketplace' && $job->contentId === 11;
        });
    }

    public function test_updated_dispatches_reindex_when_description_dirty(): void
    {
        $listing = Mockery::mock(MarketplaceListing::class)->makePartial();
        $listing->id = 12;
        $listing->tenant_id = 2;
        $listing->shouldReceive('getDirty')->andReturn(['description' => 'Updated desc.']);

        (new MarketplaceListingObserver())->updated($listing);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $job) {
            return $job->contentType === 'marketplace' && $job->contentId === 12;
        });
    }

    public function test_updated_dispatches_reindex_when_condition_dirty(): void
    {
        $listing = Mockery::mock(MarketplaceListing::class)->makePartial();
        $listing->id = 13;
        $listing->tenant_id = 2;
        $listing->shouldReceive('getDirty')->andReturn(['condition' => 'good']);

        (new MarketplaceListingObserver())->updated($listing);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $job) {
            return $job->contentType === 'marketplace' && $job->contentId === 13;
        });
    }

    public function test_updated_dispatches_reindex_when_location_dirty(): void
    {
        $listing = Mockery::mock(MarketplaceListing::class)->makePartial();
        $listing->id = 14;
        $listing->tenant_id = 2;
        $listing->shouldReceive('getDirty')->andReturn(['location' => 'Cork City']);

        (new MarketplaceListingObserver())->updated($listing);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $job) {
            return $job->contentType === 'marketplace' && $job->contentId === 14;
        });
    }

    public function test_updated_dispatches_reindex_when_status_dirty(): void
    {
        $listing = Mockery::mock(MarketplaceListing::class)->makePartial();
        $listing->id = 15;
        $listing->tenant_id = 2;
        $listing->shouldReceive('getDirty')->andReturn(['status' => 'active']);

        (new MarketplaceListingObserver())->updated($listing);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $job) {
            return $job->contentType === 'marketplace' && $job->contentId === 15;
        });
    }

    public function test_updated_skips_when_no_searchable_field_dirty(): void
    {
        $listing = Mockery::mock(MarketplaceListing::class)->makePartial();
        $listing->id = 16;
        $listing->tenant_id = 2;
        // Only non-searchable fields changed (views, saves, moderation meta)
        $listing->shouldReceive('getDirty')->andReturn([
            'views_count' => 10,
            'saves_count' => 2,
            'moderation_status' => 'pending',
        ]);

        (new MarketplaceListingObserver())->updated($listing);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
    }

    public function test_updated_skips_when_dirty_is_empty(): void
    {
        $listing = Mockery::mock(MarketplaceListing::class)->makePartial();
        $listing->id = 17;
        $listing->tenant_id = 2;
        $listing->shouldReceive('getDirty')->andReturn([]);

        (new MarketplaceListingObserver())->updated($listing);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
    }

    // -----------------------------------------------------------------------
    // deleted() — calls EmbeddingService::delete() inline (not queued)
    // -----------------------------------------------------------------------

    public function test_deleted_calls_embedding_service_delete(): void
    {
        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldReceive('delete')
            ->once()
            ->with(2, 'marketplace', 20);

        $this->app->instance(EmbeddingService::class, $embeddingMock);

        $listing = new MarketplaceListing();
        $listing->id = 20;
        $listing->tenant_id = 2;

        (new MarketplaceListingObserver())->deleted($listing);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
        $this->assertTrue(true);
    }

    public function test_deleted_does_not_call_delete_when_id_is_zero(): void
    {
        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldNotReceive('delete');

        $this->app->instance(EmbeddingService::class, $embeddingMock);

        $listing = new MarketplaceListing();
        $listing->id = 0;
        $listing->tenant_id = 2;

        (new MarketplaceListingObserver())->deleted($listing);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
        $this->assertTrue(true);
    }

    public function test_deleted_does_not_call_delete_when_tenant_id_is_zero(): void
    {
        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldNotReceive('delete');

        $this->app->instance(EmbeddingService::class, $embeddingMock);

        $listing = new MarketplaceListing();
        $listing->id = 21;
        $listing->tenant_id = 0;

        (new MarketplaceListingObserver())->deleted($listing);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
        $this->assertTrue(true);
    }
}

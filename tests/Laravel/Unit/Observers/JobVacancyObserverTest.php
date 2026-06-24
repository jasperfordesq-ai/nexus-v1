<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers;

use App\Jobs\ReindexEmbeddingJob;
use App\Models\JobVacancy;
use App\Observers\JobVacancyObserver;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class JobVacancyObserverTest extends TestCase
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
        $job = new JobVacancy();
        $job->id = 1;
        $job->tenant_id = 2;

        (new JobVacancyObserver())->created($job);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $queued) {
            return $queued->contentType === 'job'
                && $queued->contentId === 1
                && $queued->tenantId === 2;
        });
    }

    public function test_created_does_not_dispatch_when_id_is_zero(): void
    {
        $job = new JobVacancy();
        $job->id = 0;
        $job->tenant_id = 2;

        (new JobVacancyObserver())->created($job);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
    }

    public function test_created_does_not_dispatch_when_tenant_id_is_zero(): void
    {
        $job = new JobVacancy();
        $job->id = 5;
        $job->tenant_id = 0;

        (new JobVacancyObserver())->created($job);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
    }

    // -----------------------------------------------------------------------
    // updated()
    // -----------------------------------------------------------------------

    public function test_updated_dispatches_reindex_when_title_dirty(): void
    {
        $job = Mockery::mock(JobVacancy::class)->makePartial();
        $job->id = 10;
        $job->tenant_id = 2;
        $job->shouldReceive('getDirty')->andReturn(['title' => 'Senior Developer']);

        (new JobVacancyObserver())->updated($job);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $queued) {
            return $queued->contentType === 'job'
                && $queued->contentId === 10
                && $queued->tenantId === 2;
        });
    }

    public function test_updated_dispatches_reindex_when_tagline_dirty(): void
    {
        $job = Mockery::mock(JobVacancy::class)->makePartial();
        $job->id = 11;
        $job->tenant_id = 2;
        $job->shouldReceive('getDirty')->andReturn(['tagline' => 'Come build with us']);

        (new JobVacancyObserver())->updated($job);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $queued) {
            return $queued->contentType === 'job' && $queued->contentId === 11;
        });
    }

    public function test_updated_dispatches_reindex_when_description_dirty(): void
    {
        $job = Mockery::mock(JobVacancy::class)->makePartial();
        $job->id = 12;
        $job->tenant_id = 2;
        $job->shouldReceive('getDirty')->andReturn(['description' => 'Updated role description.']);

        (new JobVacancyObserver())->updated($job);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $queued) {
            return $queued->contentType === 'job' && $queued->contentId === 12;
        });
    }

    public function test_updated_dispatches_reindex_when_location_dirty(): void
    {
        $job = Mockery::mock(JobVacancy::class)->makePartial();
        $job->id = 13;
        $job->tenant_id = 2;
        $job->shouldReceive('getDirty')->andReturn(['location' => 'Dublin, Ireland']);

        (new JobVacancyObserver())->updated($job);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $queued) {
            return $queued->contentType === 'job' && $queued->contentId === 13;
        });
    }

    public function test_updated_dispatches_reindex_when_skills_required_dirty(): void
    {
        $job = Mockery::mock(JobVacancy::class)->makePartial();
        $job->id = 14;
        $job->tenant_id = 2;
        $job->shouldReceive('getDirty')->andReturn(['skills_required' => 'PHP, Laravel']);

        (new JobVacancyObserver())->updated($job);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $queued) {
            return $queued->contentType === 'job' && $queued->contentId === 14;
        });
    }

    public function test_updated_dispatches_reindex_when_status_dirty(): void
    {
        $job = Mockery::mock(JobVacancy::class)->makePartial();
        $job->id = 15;
        $job->tenant_id = 2;
        $job->shouldReceive('getDirty')->andReturn(['status' => 'active']);

        (new JobVacancyObserver())->updated($job);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $queued) {
            return $queued->contentType === 'job' && $queued->contentId === 15;
        });
    }

    public function test_updated_skips_when_no_searchable_field_dirty(): void
    {
        $job = Mockery::mock(JobVacancy::class)->makePartial();
        $job->id = 16;
        $job->tenant_id = 2;
        // Only non-searchable fields changed
        $job->shouldReceive('getDirty')->andReturn([
            'views_count' => 42,
            'applications_count' => 3,
            'is_featured' => true,
        ]);

        (new JobVacancyObserver())->updated($job);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
    }

    public function test_updated_skips_when_dirty_is_empty(): void
    {
        $job = Mockery::mock(JobVacancy::class)->makePartial();
        $job->id = 17;
        $job->tenant_id = 2;
        $job->shouldReceive('getDirty')->andReturn([]);

        (new JobVacancyObserver())->updated($job);

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
            ->with(2, 'job', 20);

        $this->app->instance(EmbeddingService::class, $embeddingMock);

        $job = new JobVacancy();
        $job->id = 20;
        $job->tenant_id = 2;

        (new JobVacancyObserver())->deleted($job);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
        $this->assertTrue(true);
    }

    public function test_deleted_does_not_call_delete_when_id_is_zero(): void
    {
        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldNotReceive('delete');

        $this->app->instance(EmbeddingService::class, $embeddingMock);

        $job = new JobVacancy();
        $job->id = 0;
        $job->tenant_id = 2;

        (new JobVacancyObserver())->deleted($job);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
        $this->assertTrue(true);
    }

    public function test_deleted_does_not_call_delete_when_tenant_id_is_zero(): void
    {
        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldNotReceive('delete');

        $this->app->instance(EmbeddingService::class, $embeddingMock);

        $job = new JobVacancy();
        $job->id = 21;
        $job->tenant_id = 0;

        (new JobVacancyObserver())->deleted($job);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
        $this->assertTrue(true);
    }
}

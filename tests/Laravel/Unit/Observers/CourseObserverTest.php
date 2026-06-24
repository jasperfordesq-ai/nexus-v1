<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers;

use App\Jobs\ReindexEmbeddingJob;
use App\Models\Course;
use App\Observers\CourseObserver;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CourseObserverTest extends TestCase
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
        $course = new Course();
        $course->id = 1;
        $course->tenant_id = 2;

        (new CourseObserver())->created($course);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $job) {
            return $job->contentType === 'course'
                && $job->contentId === 1
                && $job->tenantId === 2;
        });
    }

    public function test_created_does_not_dispatch_when_id_is_zero(): void
    {
        $course = new Course();
        $course->id = 0;
        $course->tenant_id = 2;

        (new CourseObserver())->created($course);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
    }

    public function test_created_does_not_dispatch_when_tenant_id_is_zero(): void
    {
        $course = new Course();
        $course->id = 5;
        $course->tenant_id = 0;

        (new CourseObserver())->created($course);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
    }

    // -----------------------------------------------------------------------
    // updated()
    // -----------------------------------------------------------------------

    public function test_updated_dispatches_reindex_when_title_dirty(): void
    {
        $course = Mockery::mock(Course::class)->makePartial();
        $course->id = 10;
        $course->tenant_id = 2;
        $course->shouldReceive('getDirty')->andReturn(['title' => 'New Title']);

        (new CourseObserver())->updated($course);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $job) {
            return $job->contentType === 'course'
                && $job->contentId === 10
                && $job->tenantId === 2;
        });
    }

    public function test_updated_dispatches_reindex_when_status_dirty(): void
    {
        $course = Mockery::mock(Course::class)->makePartial();
        $course->id = 11;
        $course->tenant_id = 2;
        $course->shouldReceive('getDirty')->andReturn(['status' => 'published']);

        (new CourseObserver())->updated($course);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $job) {
            return $job->contentType === 'course' && $job->contentId === 11;
        });
    }

    public function test_updated_dispatches_reindex_when_description_dirty(): void
    {
        $course = Mockery::mock(Course::class)->makePartial();
        $course->id = 12;
        $course->tenant_id = 2;
        $course->shouldReceive('getDirty')->andReturn(['description' => 'Updated description text.']);

        (new CourseObserver())->updated($course);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $job) {
            return $job->contentType === 'course' && $job->contentId === 12;
        });
    }

    public function test_updated_dispatches_reindex_when_summary_dirty(): void
    {
        $course = Mockery::mock(Course::class)->makePartial();
        $course->id = 13;
        $course->tenant_id = 2;
        $course->shouldReceive('getDirty')->andReturn(['summary' => 'New summary.']);

        (new CourseObserver())->updated($course);

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $job) {
            return $job->contentType === 'course' && $job->contentId === 13;
        });
    }

    public function test_updated_skips_when_no_searchable_field_dirty(): void
    {
        $course = Mockery::mock(Course::class)->makePartial();
        $course->id = 14;
        $course->tenant_id = 2;
        // Only non-searchable fields changed (e.g. credit_cost, level)
        $course->shouldReceive('getDirty')->andReturn(['credit_cost' => '5.00', 'level' => 'advanced']);

        (new CourseObserver())->updated($course);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
    }

    public function test_updated_skips_when_dirty_is_empty(): void
    {
        $course = Mockery::mock(Course::class)->makePartial();
        $course->id = 15;
        $course->tenant_id = 2;
        $course->shouldReceive('getDirty')->andReturn([]);

        (new CourseObserver())->updated($course);

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
            ->with(2, 'course', 20);

        $this->app->instance(EmbeddingService::class, $embeddingMock);

        $course = new Course();
        $course->id = 20;
        $course->tenant_id = 2;

        (new CourseObserver())->deleted($course);

        // Queue must NOT have been pushed — delete is synchronous
        Queue::assertNotPushed(ReindexEmbeddingJob::class);
        $this->assertTrue(true);
    }

    public function test_deleted_does_not_call_delete_when_id_is_zero(): void
    {
        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldNotReceive('delete');

        $this->app->instance(EmbeddingService::class, $embeddingMock);

        $course = new Course();
        $course->id = 0;
        $course->tenant_id = 2;

        (new CourseObserver())->deleted($course);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
        $this->assertTrue(true);
    }

    public function test_deleted_does_not_call_delete_when_tenant_id_is_zero(): void
    {
        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldNotReceive('delete');

        $this->app->instance(EmbeddingService::class, $embeddingMock);

        $course = new Course();
        $course->id = 21;
        $course->tenant_id = 0;

        (new CourseObserver())->deleted($course);

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
        $this->assertTrue(true);
    }
}

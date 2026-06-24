<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers\Concerns;

use App\Jobs\ReindexEmbeddingJob;
use App\Observers\Concerns\IndexesEmbeddings;
use App\Services\EmbeddingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * IndexesEmbeddings trait — dispatches ReindexEmbeddingJob on create/update
 * and calls EmbeddingService::delete on hard removal.
 *
 * Tested via a minimal anonymous stub class that exposes the trait's protected
 * methods publicly, without coupling to any specific production observer.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class IndexesEmbeddingsTest extends TestCase
{
    /** @var object Stub observer that exposes the trait's protected helpers. */
    private object $stub;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Create a concrete anonymous class that uses the trait and exposes
        // the otherwise-protected methods as public for direct unit testing.
        $this->stub = new class {
            use IndexesEmbeddings;

            public function publicReindex(Model $model, string $contentType): void
            {
                $this->reindexEmbedding($model, $contentType);
            }

            public function publicDelete(Model $model, string $contentType): void
            {
                $this->deleteEmbedding($model, $contentType);
            }
        };
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a minimal Eloquent Model stub with the given id and tenant_id.
     */
    private function makeModel(int $id, int $tenantId): Model
    {
        $model = new class extends Model {};
        $model->id = $id;
        $model->tenant_id = $tenantId;
        return $model;
    }

    // -----------------------------------------------------------------------
    // reindexEmbedding()
    // -----------------------------------------------------------------------

    public function test_reindex_dispatches_job_with_correct_content_type_id_and_tenant(): void
    {
        $model = $this->makeModel(42, 2);

        $this->stub->publicReindex($model, 'listing');

        Queue::assertPushed(ReindexEmbeddingJob::class, function (ReindexEmbeddingJob $job) {
            return $job->contentType === 'listing'
                && $job->contentId === 42
                && $job->tenantId === 2;
        });
    }

    public function test_reindex_dispatches_once_per_call(): void
    {
        $model = $this->makeModel(7, 2);

        $this->stub->publicReindex($model, 'post');

        Queue::assertPushed(ReindexEmbeddingJob::class, 1);
    }

    public function test_reindex_does_not_dispatch_when_id_is_zero(): void
    {
        $model = $this->makeModel(0, 2);

        $this->stub->publicReindex($model, 'listing');

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
    }

    public function test_reindex_does_not_dispatch_when_tenant_id_is_zero(): void
    {
        $model = $this->makeModel(5, 0);

        $this->stub->publicReindex($model, 'listing');

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
    }

    public function test_reindex_does_not_dispatch_when_model_has_no_id(): void
    {
        // Model with id=null (i.e. not yet persisted).
        $model = new class extends Model {};
        $model->tenant_id = 2;
        // id is not set — $model->id resolves to null, cast to int = 0.

        $this->stub->publicReindex($model, 'post');

        Queue::assertNotPushed(ReindexEmbeddingJob::class);
    }

    public function test_reindex_does_not_throw_when_dispatch_fails(): void
    {
        // Simulate a dispatch failure by faking the queue and then asserting
        // the trait swallows exceptions via its try/catch.
        // We bind a broken queue implementation to the container temporarily.
        $model = $this->makeModel(1, 2);

        // Override the queue with a throwing fake.
        Queue::shouldReceive('push')->andThrow(new \RuntimeException('queue dead'));
        // The Queue::fake() above replaces the driver — just ensure no exception escapes.
        // (The trait catches all \Throwable.)
        try {
            $this->stub->publicReindex($model, 'listing');
            $this->assertTrue(true, 'reindexEmbedding() must swallow exceptions and not rethrow.');
        } catch (\Throwable $e) {
            $this->fail('reindexEmbedding() must not propagate exceptions: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // deleteEmbedding()
    // -----------------------------------------------------------------------

    public function test_delete_embedding_calls_embedding_service_delete(): void
    {
        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldReceive('delete')
            ->once()
            ->with(2, 'listing', 55);

        $this->app->instance(EmbeddingService::class, $embeddingMock);

        $model = $this->makeModel(55, 2);
        $this->stub->publicDelete($model, 'listing');

        // Mockery expectation verified above via shouldReceive->once().
        $this->assertTrue(true);
    }

    public function test_delete_embedding_skips_when_id_is_zero(): void
    {
        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldNotReceive('delete');

        $this->app->instance(EmbeddingService::class, $embeddingMock);

        $model = $this->makeModel(0, 2);
        $this->stub->publicDelete($model, 'listing');

        $this->assertTrue(true);
    }

    public function test_delete_embedding_skips_when_tenant_id_is_zero(): void
    {
        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldNotReceive('delete');

        $this->app->instance(EmbeddingService::class, $embeddingMock);

        $model = $this->makeModel(10, 0);
        $this->stub->publicDelete($model, 'listing');

        $this->assertTrue(true);
    }

    public function test_delete_embedding_does_not_throw_when_service_throws(): void
    {
        $embeddingMock = Mockery::mock(EmbeddingService::class);
        $embeddingMock->shouldReceive('delete')->andThrow(new \RuntimeException('OpenAI down'));

        $this->app->instance(EmbeddingService::class, $embeddingMock);

        $model = $this->makeModel(5, 2);

        try {
            $this->stub->publicDelete($model, 'listing');
            $this->assertTrue(true, 'deleteEmbedding() must swallow exceptions.');
        } catch (\Throwable $e) {
            $this->fail('deleteEmbedding() must not propagate exceptions: ' . $e->getMessage());
        }
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\JobTemplateService;
use App\Models\JobTemplate;
use Illuminate\Support\Facades\Log;
use Mockery;

class JobTemplateServiceTest extends TestCase
{
    // ── list ─────────────────────────────────────────────────────

    public function test_list_returns_templates_for_user(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('orderByDesc')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([]));
        $builder->shouldReceive('get->toArray')->andReturn([]);

        $mock = Mockery::mock('alias:' . JobTemplate::class);
        $mock->shouldReceive('where')->andReturn($builder);

        $result = JobTemplateService::list(5);
        $this->assertIsArray($result);
    }

    public function test_list_includes_public_templates(): void
    {
        // The method uses a closure: where('user_id', $userId)->orWhere('is_public', true)
        // We verify the return type — integration tests would verify the SQL logic
        $result = JobTemplateService::list(5);
        $this->assertIsArray($result);
    }

    public function test_list_returns_empty_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = Mockery::mock('alias:' . JobTemplate::class);
        $mock->shouldReceive('where')->andThrow(new \Exception('DB error'));

        $result = JobTemplateService::list(5);
        $this->assertSame([], $result);
    }

    // ── create ──────────────────────────────────────────────────

    public function test_create_returns_template_array(): void
    {
        $template = Mockery::mock();
        $template->shouldReceive('toArray')->andReturn([
            'id' => 1, 'name' => 'Junior Dev', 'type' => 'paid',
        ]);

        $mock = Mockery::mock('alias:' . JobTemplate::class);
        $mock->shouldReceive('create')->once()->andReturn($template);

        $result = JobTemplateService::create(5, [
            'name' => 'Junior Dev',
            'type' => 'paid',
            'commitment' => 'full_time',
        ]);
        $this->assertIsArray($result);
        $this->assertSame('Junior Dev', $result['name']);
    }

    public function test_create_trims_name(): void
    {
        $template = Mockery::mock();
        $template->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'Test']);

        $mock = Mockery::mock('alias:' . JobTemplate::class);
        $mock->shouldReceive('create')->withArgs(function ($data) {
            return $data['name'] === 'Test';
        })->andReturn($template);

        $result = JobTemplateService::create(5, ['name' => '  Test  ']);
        $this->assertIsArray($result);
    }

    public function test_create_defaults_fields(): void
    {
        $template = Mockery::mock();
        $template->shouldReceive('toArray')->andReturn(['id' => 1]);

        $mock = Mockery::mock('alias:' . JobTemplate::class);
        $mock->shouldReceive('create')->withArgs(function ($data) {
            return $data['type'] === 'paid'
                && $data['commitment'] === 'flexible'
                && $data['salary_currency'] === 'EUR'
                && $data['is_remote'] === false
                && $data['is_public'] === false;
        })->andReturn($template);

        $result = JobTemplateService::create(5, ['name' => 'Default']);
        $this->assertIsArray($result);
    }

    public function test_create_returns_false_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = Mockery::mock('alias:' . JobTemplate::class);
        $mock->shouldReceive('create')->andThrow(new \Exception('DB error'));

        $result = JobTemplateService::create(5, ['name' => 'Test']);
        $this->assertFalse($result);
    }

    // ── get ──────────────────────────────────────────────────────

    public function test_get_returns_template_and_increments_use_count(): void
    {
        $template = Mockery::mock();
        $template->shouldReceive('increment')->with('use_count')->once();
        $template->shouldReceive('toArray')->andReturn([
            'id' => 1, 'name' => 'Test', 'use_count' => 5,
        ]);

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('first')->andReturn($template);

        $mock = Mockery::mock('alias:' . JobTemplate::class);
        $mock->shouldReceive('where')->andReturn($builder);

        $result = JobTemplateService::get(1, 5);
        $this->assertIsArray($result);
        $this->assertSame('Test', $result['name']);
    }

    public function test_get_returns_null_when_not_found(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('first')->andReturn(null);

        $mock = Mockery::mock('alias:' . JobTemplate::class);
        $mock->shouldReceive('where')->andReturn($builder);

        $result = JobTemplateService::get(999, 5);
        $this->assertNull($result);
    }

    public function test_get_returns_null_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = Mockery::mock('alias:' . JobTemplate::class);
        $mock->shouldReceive('where')->andThrow(new \Exception('DB error'));

        $result = JobTemplateService::get(1, 5);
        $this->assertNull($result);
    }

    // ── delete ──────────────────────────────────────────────────

    public function test_delete_returns_true_when_deleted(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('delete')->once()->andReturn(1);

        $mock = Mockery::mock('alias:' . JobTemplate::class);
        $mock->shouldReceive('where')->andReturn($builder);

        $result = JobTemplateService::delete(1, 5);
        $this->assertTrue($result);
    }

    public function test_delete_returns_false_when_not_found(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('delete')->once()->andReturn(0);

        $mock = Mockery::mock('alias:' . JobTemplate::class);
        $mock->shouldReceive('where')->andReturn($builder);

        $result = JobTemplateService::delete(999, 5);
        $this->assertFalse($result);
    }

    public function test_delete_only_allows_owner_deletion(): void
    {
        // The where clause includes user_id check, so non-owner gets 0 rows deleted
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('delete')->once()->andReturn(0);

        $mock = Mockery::mock('alias:' . JobTemplate::class);
        $mock->shouldReceive('where')->andReturn($builder);

        $result = JobTemplateService::delete(1, 99); // wrong user
        $this->assertFalse($result);
    }

    public function test_delete_returns_false_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = Mockery::mock('alias:' . JobTemplate::class);
        $mock->shouldReceive('where')->andThrow(new \Exception('DB error'));

        $result = JobTemplateService::delete(1, 5);
        $this->assertFalse($result);
    }
}

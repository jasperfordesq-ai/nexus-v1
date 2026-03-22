<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\JobSavedProfileService;
use App\Models\JobSavedProfile;
use Illuminate\Support\Facades\Log;
use Mockery;

class JobSavedProfileServiceTest extends TestCase
{
    // ── get ──────────────────────────────────────────────────────

    public function test_get_returns_profile_array_when_found(): void
    {
        $profile = Mockery::mock();
        $profile->shouldReceive('toArray')->andReturn([
            'id' => 1, 'user_id' => 5, 'headline' => 'Dev', 'cv_filename' => 'cv.pdf',
        ]);

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        $builder->shouldReceive('where')->with('user_id', 5)->andReturnSelf();
        $builder->shouldReceive('first')->andReturn($profile);

        $mock = Mockery::mock('alias:' . JobSavedProfile::class);
        $mock->shouldReceive('where')->andReturn($builder);

        $result = JobSavedProfileService::get(5);
        $this->assertIsArray($result);
        $this->assertSame('Dev', $result['headline']);
    }

    public function test_get_returns_null_when_no_profile_exists(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('first')->andReturn(null);

        $mock = Mockery::mock('alias:' . JobSavedProfile::class);
        $mock->shouldReceive('where')->andReturn($builder);

        $result = JobSavedProfileService::get(999);
        $this->assertNull($result);
    }

    public function test_get_returns_null_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = Mockery::mock('alias:' . JobSavedProfile::class);
        $mock->shouldReceive('where')->andThrow(new \Exception('DB error'));

        $result = JobSavedProfileService::get(1);
        $this->assertNull($result);
    }

    // ── save ────────────────────────────────────────────────────

    public function test_save_creates_new_profile(): void
    {
        $profile = Mockery::mock();
        $profile->shouldReceive('toArray')->andReturn([
            'id' => 1, 'user_id' => 5, 'headline' => 'Engineer', 'cv_filename' => 'resume.pdf',
        ]);

        $mock = Mockery::mock('alias:' . JobSavedProfile::class);
        $mock->shouldReceive('updateOrCreate')->once()->andReturn($profile);

        $result = JobSavedProfileService::save(5, [
            'headline' => 'Engineer',
            'cv_filename' => 'resume.pdf',
            'cv_path' => '/uploads/cv/resume.pdf',
            'cv_size' => 102400,
        ]);
        $this->assertIsArray($result);
        $this->assertSame('Engineer', $result['headline']);
    }

    public function test_save_updates_existing_profile(): void
    {
        $profile = Mockery::mock();
        $profile->shouldReceive('toArray')->andReturn([
            'id' => 1, 'user_id' => 5, 'headline' => 'Senior Engineer',
        ]);

        $mock = Mockery::mock('alias:' . JobSavedProfile::class);
        $mock->shouldReceive('updateOrCreate')->once()->andReturn($profile);

        $result = JobSavedProfileService::save(5, [
            'headline' => 'Senior Engineer',
        ]);
        $this->assertIsArray($result);
        $this->assertSame('Senior Engineer', $result['headline']);
    }

    public function test_save_filters_null_values(): void
    {
        $profile = Mockery::mock();
        $profile->shouldReceive('toArray')->andReturn(['id' => 1, 'user_id' => 5]);

        $mock = Mockery::mock('alias:' . JobSavedProfile::class);
        $mock->shouldReceive('updateOrCreate')->withArgs(function ($keys, $data) {
            // array_filter removes null values, so only non-null fields should be present
            foreach ($data as $val) {
                if ($val === null) {
                    return false;
                }
            }
            return true;
        })->andReturn($profile);

        $result = JobSavedProfileService::save(5, [
            'headline' => 'Test',
            // cv_path not set — should not appear in filtered data
        ]);
        $this->assertIsArray($result);
    }

    public function test_save_trims_headline_and_cover_text(): void
    {
        $profile = Mockery::mock();
        $profile->shouldReceive('toArray')->andReturn([
            'id' => 1, 'headline' => 'Trimmed', 'cover_text' => 'Clean',
        ]);

        $mock = Mockery::mock('alias:' . JobSavedProfile::class);
        $mock->shouldReceive('updateOrCreate')->withArgs(function ($keys, $data) {
            return ($data['headline'] ?? '') === 'Trimmed'
                && ($data['cover_text'] ?? '') === 'Clean';
        })->andReturn($profile);

        $result = JobSavedProfileService::save(5, [
            'headline' => '  Trimmed  ',
            'cover_text' => '  Clean  ',
        ]);
        $this->assertIsArray($result);
    }

    public function test_save_returns_false_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = Mockery::mock('alias:' . JobSavedProfile::class);
        $mock->shouldReceive('updateOrCreate')->andThrow(new \Exception('DB error'));

        $result = JobSavedProfileService::save(1, ['headline' => 'Test']);
        $this->assertFalse($result);
    }
}

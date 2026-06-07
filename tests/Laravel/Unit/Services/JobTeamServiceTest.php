<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\JobTeamService;
use App\Models\JobVacancy;
use App\Models\JobVacancyTeam;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Mockery;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class JobTeamServiceTest extends TestCase
{
    /** @var \Mockery\MockInterface */
    private $jobVacancyAlias;

    protected function setUp(): void
    {
        // The real App\Models\JobVacancy is eagerly loaded during Laravel boot
        // (AppServiceProvider registers JobVacancy::observe(...)), so the alias
        // mock MUST be created before parent::setUp() boots the framework.
        // shouldIgnoreMissing() makes the boot-time static ::observe() calls no-ops.
        $this->jobVacancyAlias = Mockery::mock('alias:' . JobVacancy::class)->shouldIgnoreMissing();
        parent::setUp();
    }

    /**
     * Ensure a user exists in the test tenant so JobTeamService::addMember's
     * User::where(...)->exists() guard passes for the happy-path tests.
     */
    private function seedTargetUser(int $userId): void
    {
        DB::table('users')->insertOrIgnore([
            'id'         => $userId,
            'tenant_id'  => $this->testTenantId,
            'first_name' => 'Team',
            'last_name'  => 'Member',
            'email'      => "jobteam_{$userId}@example.com",
            'username'   => "jobteam_{$userId}",
            'password'   => password_hash('password', PASSWORD_BCRYPT),
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── addMember ───────────────────────────────────────────────

    public function test_addMember_returns_false_when_vacancy_not_found(): void
    {
        $mock = $this->jobVacancyAlias;
        $mock->shouldReceive('find')->with(999)->andReturnNull();

        $result = JobTeamService::addMember(999, 1, 2);
        $this->assertFalse($result);
    }

    public function test_addMember_returns_false_when_tenant_mismatch(): void
    {
        $vacancy = Mockery::mock();
        $vacancy->tenant_id = 999; // wrong tenant
        $vacancy->user_id = 1;

        $mock = $this->jobVacancyAlias;
        $mock->shouldReceive('find')->with(10)->andReturn($vacancy);

        $result = JobTeamService::addMember(10, 1, 2);
        $this->assertFalse($result);
    }

    public function test_addMember_returns_false_when_not_owner(): void
    {
        $vacancy = Mockery::mock();
        $vacancy->tenant_id = $this->testTenantId;
        $vacancy->user_id = 5; // different user

        $mock = $this->jobVacancyAlias;
        $mock->shouldReceive('find')->with(10)->andReturn($vacancy);

        $result = JobTeamService::addMember(10, 1, 2); // user 1 != owner 5
        $this->assertFalse($result);
    }

    public function test_addMember_returns_false_when_adding_self(): void
    {
        $vacancy = Mockery::mock();
        $vacancy->tenant_id = $this->testTenantId;
        $vacancy->user_id = 1;
        $vacancy->title = 'Test Job';

        $mock = $this->jobVacancyAlias;
        $mock->shouldReceive('find')->with(10)->andReturn($vacancy);

        // Owner tries to add themselves
        $result = JobTeamService::addMember(10, 1, 1);
        $this->assertFalse($result);
    }

    public function test_addMember_creates_team_member_and_notifies(): void
    {
        $this->seedTargetUser(2);

        $vacancy = Mockery::mock();
        $vacancy->tenant_id = $this->testTenantId;
        $vacancy->user_id = 1;
        $vacancy->title = 'Test Job';

        $member = Mockery::mock();
        $member->shouldReceive('toArray')->andReturn([
            'vacancy_id' => 10, 'user_id' => 2, 'role' => 'reviewer',
        ]);

        $mock = $this->jobVacancyAlias;
        $mock->shouldReceive('find')->with(10)->andReturn($vacancy);

        $teamMock = Mockery::mock('alias:' . JobVacancyTeam::class);
        $teamMock->shouldReceive('updateOrCreate')->once()->andReturn($member);

        $notifMock = Mockery::mock('alias:' . Notification::class);
        $notifMock->shouldReceive('createNotification')->once()->with(2, Mockery::type('string'), '/jobs/10', 'job_application');

        $result = JobTeamService::addMember(10, 1, 2, 'reviewer');
        $this->assertIsArray($result);
        $this->assertSame('reviewer', $result['role']);
    }

    public function test_addMember_defaults_invalid_role_to_reviewer(): void
    {
        $this->seedTargetUser(2);

        $vacancy = Mockery::mock();
        $vacancy->tenant_id = $this->testTenantId;
        $vacancy->user_id = 1;
        $vacancy->title = 'Test Job';

        $member = Mockery::mock();
        $member->shouldReceive('toArray')->andReturn([
            'vacancy_id' => 10, 'user_id' => 2, 'role' => 'reviewer',
        ]);

        $mock = $this->jobVacancyAlias;
        $mock->shouldReceive('find')->with(10)->andReturn($vacancy);

        $teamMock = Mockery::mock('alias:' . JobVacancyTeam::class);
        $teamMock->shouldReceive('updateOrCreate')->withArgs(function ($keys, $data) {
            return $data['role'] === 'reviewer';
        })->andReturn($member);

        $notifMock = Mockery::mock('alias:' . Notification::class);
        $notifMock->shouldReceive('createNotification')->once();

        $result = JobTeamService::addMember(10, 1, 2, 'admin'); // invalid role
        $this->assertIsArray($result);
    }

    public function test_addMember_returns_false_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = $this->jobVacancyAlias;
        $mock->shouldReceive('find')->andThrow(new \Exception('DB error'));

        $result = JobTeamService::addMember(10, 1, 2);
        $this->assertFalse($result);
    }

    // ── removeMember ────────────────────────────────────────────

    public function test_removeMember_returns_false_when_vacancy_not_found(): void
    {
        $mock = $this->jobVacancyAlias;
        $mock->shouldReceive('find')->with(999)->andReturnNull();

        $result = JobTeamService::removeMember(999, 1, 2);
        $this->assertFalse($result);
    }

    public function test_removeMember_returns_false_when_not_owner(): void
    {
        $vacancy = Mockery::mock();
        $vacancy->tenant_id = $this->testTenantId;
        $vacancy->user_id = 5;

        $mock = $this->jobVacancyAlias;
        $mock->shouldReceive('find')->with(10)->andReturn($vacancy);

        $result = JobTeamService::removeMember(10, 1, 2); // user 1 != owner 5
        $this->assertFalse($result);
    }

    public function test_removeMember_deletes_and_returns_true(): void
    {
        $vacancy = Mockery::mock();
        $vacancy->tenant_id = $this->testTenantId;
        $vacancy->user_id = 1;

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('delete')->once()->andReturn(1);

        $mock = $this->jobVacancyAlias;
        $mock->shouldReceive('find')->with(10)->andReturn($vacancy);

        $teamMock = Mockery::mock('alias:' . JobVacancyTeam::class);
        $teamMock->shouldReceive('where')->andReturn($builder);

        $result = JobTeamService::removeMember(10, 1, 2);
        $this->assertTrue($result);
    }

    public function test_removeMember_returns_false_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = $this->jobVacancyAlias;
        $mock->shouldReceive('find')->andThrow(new \Exception('DB error'));

        $result = JobTeamService::removeMember(10, 1, 2);
        $this->assertFalse($result);
    }

    // ── getMembers ──────────────────────────────────────────────

    public function test_getMembers_returns_array(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([]));
        $builder->shouldReceive('get->toArray')->andReturn([]);

        $mock = Mockery::mock('alias:' . JobVacancyTeam::class);
        $mock->shouldReceive('with')->andReturn($builder);

        $result = JobTeamService::getMembers(10);
        $this->assertIsArray($result);
    }

    public function test_getMembers_returns_empty_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = Mockery::mock('alias:' . JobVacancyTeam::class);
        $mock->shouldReceive('with')->andThrow(new \Exception('DB error'));

        $result = JobTeamService::getMembers(10);
        $this->assertSame([], $result);
    }
}

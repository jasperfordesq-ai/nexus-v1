<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Enums\EventStaffRole;
use App\Models\User;
use App\Services\EventRoleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Laravel\TestCase;

final class EventRoleRollbackSafetyTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION = '2026_07_11_000016_add_event_staff_role_foundation.php';

    public function test_rollback_refuses_to_destroy_assignment_evidence(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $staff = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $eventId = $this->insertEvent((int) $owner->id);
        TenantContext::setById($this->testTenantId);
        (new EventRoleService())->grant($eventId, (int) $staff->id, EventStaffRole::CheckInStaff, $owner);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('event_staff_role_rollback_refused_records_exist');
        $this->migration()->down();
    }

    private function insertEvent(int $ownerId): int
    {
        $start = now()->addWeek();

        return (int) \Illuminate\Support\Facades\DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $ownerId,
            'title' => 'Role rollback fixture',
            'description' => 'Role rollback fixture.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . self::MIGRATION);

        return $migration;
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Models\Event;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

final class EventVenueAccessibilityMigrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_structured_public_venue_columns_and_filter_index_exist(): void
    {
        foreach ([
            'accessibility_step_free',
            'accessibility_toilet',
            'accessibility_hearing_loop',
            'accessibility_quiet_space',
            'accessibility_seating',
            'accessibility_parking',
            'accessibility_parking_details',
            'accessibility_transit_details',
            'accessibility_assistance_contact',
            'accessibility_notes',
        ] as $column) {
            self::assertTrue(Schema::hasColumn('events', $column), $column);
        }

        self::assertTrue(
            Schema::hasIndex('events', 'idx_events_tenant_step_free_start'),
            'The first accessible-venue discovery filter must remain index-backed.',
        );
    }

    public function test_rollback_refuses_non_null_accessibility_evidence_including_false(): void
    {
        Event::factory()->forTenant($this->testTenantId)->create([
            'accessibility_step_free' => false,
        ]);
        /** @var Migration $migration */
        $migration = require database_path(
            'migrations/2026_07_11_000064_add_event_venue_accessibility.php',
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'event_venue_accessibility_rollback_refused_evidence_exists',
        );
        $migration->down();
    }
}

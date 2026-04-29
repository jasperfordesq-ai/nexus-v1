<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AG61 — Seeds the four default agent definitions for one or all tenants.
 *
 * All agents are inserted in DISABLED state (is_enabled=0). An admin must
 * explicitly enable each one from /admin/agents before it will run.
 *
 * Idempotent — uses (tenant_id, slug) unique key.
 */
class DefaultAgentDefinitionsSeeder extends Seeder
{
    /** @var list<array{slug:string,name:string,description:string,agent_type:string,config:array<string,mixed>}> */
    private const DEFAULTS = [
        [
            'slug'        => 'tandem_matchmaker',
            'name'        => 'Tandem Matchmaker',
            'description' => 'Suggests caring tandem pairs from supporters and recipients with high compatibility scores.',
            'agent_type'  => 'matchmaker',
            'config'      => [
                'max_proposals_per_run' => 20,
                'min_score'             => 0.4,
            ],
        ],
        [
            'slug'        => 'nudge_drafter',
            'name'        => 'Nudge Drafter',
            'description' => 'Drafts personalised nudges for inactive members and proposes them for admin approval.',
            'agent_type'  => 'nudge_drafter',
            'config'      => [
                'inactivity_days'       => 14,
                'max_proposals_per_run' => 15,
            ],
        ],
        [
            'slug'        => 'coordinator_router',
            'name'        => 'Coordinator Router',
            'description' => 'Suggests the best coordinator to assign to incoming caring help requests.',
            'agent_type'  => 'coordinator_router',
            'config'      => [
                'max_proposals_per_run' => 10,
            ],
        ],
        [
            'slug'        => 'activity_summariser',
            'name'        => 'Activity Summariser',
            'description' => 'Drafts weekly activity-summary digests for engaged members.',
            'agent_type'  => 'activity_summariser',
            'config'      => [
                'lookback_days'         => 7,
                'max_proposals_per_run' => 25,
            ],
        ],
    ];

    /**
     * Seed for a single tenant, or every tenant when $tenantId is null.
     */
    public function run(?int $tenantId = null): void
    {
        if (!Schema::hasTable('agent_definitions')) {
            return;
        }

        $tenantIds = $tenantId !== null
            ? [$tenantId]
            : DB::table('tenants')->where('is_active', 1)->pluck('id')->map(fn ($v) => (int) $v)->all();

        foreach ($tenantIds as $tid) {
            foreach (self::DEFAULTS as $def) {
                DB::table('agent_definitions')->updateOrInsert(
                    [
                        'tenant_id' => $tid,
                        'slug'      => $def['slug'],
                    ],
                    [
                        'name'        => $def['name'],
                        'description' => $def['description'],
                        'agent_type'  => $def['agent_type'],
                        'config'      => json_encode($def['config']),
                        'is_enabled'  => 0,
                        'updated_at'  => now(),
                        'created_at'  => now(),
                    ]
                );
            }
        }
    }
}

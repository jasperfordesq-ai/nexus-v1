<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Commands;

use Tests\Laravel\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tests for the federation:expire-cc-validations Artisan command.
 */
class ExpireCcValidationsTest extends TestCase
{
    public function test_command_runs_successfully_with_no_stale_entries(): void
    {
        // No tenant configs → no entries to expire
        DB::shouldReceive('table->get')->once()->andReturn(collect([]));

        $exitCode = Artisan::call('federation:expire-cc-validations');

        $this->assertSame(0, $exitCode);
    }

    public function test_command_outputs_count_when_entries_expired(): void
    {
        // One tenant config with stale entries
        DB::shouldReceive('table->get')->once()->andReturn(collect([
            (object) ['tenant_id' => 1, 'validated_window' => 300],
        ]));

        $staleEntry = (object) [
            'id' => 42,
            'transaction_uuid' => 'stale-uuid',
            'state' => 'V',
        ];

        DB::shouldReceive('table->where->where->where->get')->once()
            ->andReturn(collect([$staleEntry]));

        DB::shouldReceive('table->where->update')->once()->andReturn(1);
        Log::shouldReceive('info')->once();

        $exitCode = Artisan::call('federation:expire-cc-validations');

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Expired 1 validated CC transaction(s)', $output);
    }
}

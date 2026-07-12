<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class PruneGroupDataExportsCommand extends Command
{
    protected $signature = 'groups:prune-exports';
    protected $description = 'Expire private group export files and prune old export records';

    public function handle(): int
    {
        if (!Schema::hasTable('group_data_exports')) {
            return self::SUCCESS;
        }

        $expired = 0;
        do {
            $rows = DB::table('group_data_exports')
                ->whereIn('status', ['queued', 'processing', 'completed', 'failed'])
                ->where('expires_at', '<=', now())
                ->orderBy('created_at')
                ->limit(100)
                ->get();

            foreach ($rows as $row) {
                $path = is_string($row->storage_path ?? null) ? $row->storage_path : '';
                $prefix = "groups/{$row->tenant_id}/{$row->group_id}/exports/";
                if ($path !== '' && str_starts_with(str_replace('\\', '/', $path), $prefix)) {
                    Storage::disk('local')->delete($path);
                }

                $expired += DB::table('group_data_exports')
                    ->where('id', $row->id)
                    ->where('tenant_id', $row->tenant_id)
                    ->update([
                        'status' => 'expired',
                        'storage_path' => null,
                        'byte_size' => null,
                        'processing_started_at' => null,
                        'updated_at' => now(),
                    ]);
            }
        } while ($rows->count() === 100);

        $pruned = DB::table('group_data_exports')
            ->where('status', 'expired')
            ->where('updated_at', '<=', now()->subDays(30))
            ->delete();

        $this->components->info("Expired {$expired} group exports; pruned {$pruned} old records.");

        return self::SUCCESS;
    }
}

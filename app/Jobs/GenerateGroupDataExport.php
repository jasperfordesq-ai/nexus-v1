<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Jobs;

use App\Core\TenantContext;
use App\Services\GroupDataExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

final class GenerateGroupDataExport implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;
    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly string $exportId,
        public readonly int $tenantId,
    ) {}

    public function uniqueId(): string
    {
        return $this->tenantId . ':' . $this->exportId;
    }

    public function handle(): void
    {
        $row = DB::transaction(function (): object|null {
            $export = DB::table('group_data_exports')
                ->where('id', $this->exportId)
                ->where('tenant_id', $this->tenantId)
                ->lockForUpdate()
                ->first();

            if ($export === null || in_array($export->status, ['completed', 'expired', 'failed'], true)) {
                return null;
            }
            if ($export->expires_at !== null && Carbon::parse($export->expires_at)->isPast()) {
                DB::table('group_data_exports')
                    ->where('id', $this->exportId)
                    ->where('tenant_id', $this->tenantId)
                    ->update([
                        'status' => 'expired',
                        'processing_started_at' => null,
                        'updated_at' => now(),
                    ]);
                return null;
            }
            if (
                $export->status === 'processing'
                && $export->processing_started_at !== null
                && Carbon::parse($export->processing_started_at)->isAfter(now()->subMinutes(10))
            ) {
                return null;
            }

            DB::table('group_data_exports')
                ->where('id', $this->exportId)
                ->where('tenant_id', $this->tenantId)
                ->update([
                    'status' => 'processing',
                    'attempts' => DB::raw('attempts + 1'),
                    'processing_started_at' => now(),
                    'error_code' => null,
                    'updated_at' => now(),
                ]);

            return $export;
        });

        if ($row === null) {
            return;
        }

        $path = "groups/{$this->tenantId}/{$row->group_id}/exports/{$this->exportId}.json";

        try {
            TenantContext::runForTenant($this->tenantId, function () use ($row, $path): void {
                $payload = GroupDataExportService::exportAll(
                    (int) $row->group_id,
                    (int) $row->requested_by,
                );
                if ($payload === null) {
                    throw new RuntimeException('GROUP_EXPORT_ACCESS_REVOKED');
                }

                $json = json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
                );
                if (!Storage::disk('local')->put($path, $json)) {
                    throw new RuntimeException('GROUP_EXPORT_STORAGE_FAILED');
                }

                DB::table('group_data_exports')
                    ->where('id', $this->exportId)
                    ->where('tenant_id', $this->tenantId)
                    ->update([
                        'status' => 'completed',
                        'storage_path' => $path,
                        'byte_size' => strlen($json),
                        'completed_at' => now(),
                        'processing_started_at' => null,
                        'error_code' => null,
                        'updated_at' => now(),
                    ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            DB::table('group_data_exports')
                ->where('id', $this->exportId)
                ->where('tenant_id', $this->tenantId)
                ->update([
                    'status' => 'queued',
                    'storage_path' => null,
                    'byte_size' => null,
                    'error_code' => $exception->getMessage() === 'GROUP_EXPORT_ACCESS_REVOKED'
                        ? 'ACCESS_REVOKED'
                        : 'GENERATION_FAILED',
                    'processing_started_at' => null,
                    'updated_at' => now(),
                ]);

            Log::warning('Queued group export generation failed', [
                'export_id' => $this->exportId,
                'tenant_id' => $this->tenantId,
                'exception' => $exception::class,
            ]);
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        DB::table('group_data_exports')
            ->where('id', $this->exportId)
            ->where('tenant_id', $this->tenantId)
            ->update([
                'status' => 'failed',
                'error_code' => 'GENERATION_FAILED',
                'processing_started_at' => null,
                'updated_at' => now(),
            ]);
    }
}

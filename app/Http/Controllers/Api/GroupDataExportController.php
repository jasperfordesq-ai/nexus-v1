<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Jobs\GenerateGroupDataExport;
use Illuminate\Http\JsonResponse;
use App\Models\Group;
use App\Services\GroupAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GroupDataExportController — Full data export for groups.
 */
class GroupDataExportController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/groups/{id}/export
     *
     * Retired synchronous export endpoint.
     *
     * Full group exports can be large, so callers must use the queued POST
     * /api/v2/groups/{id}/exports flow and its private status/download URLs.
     */
    public function exportAll(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        return $this->respondWithError(
            'CAPABILITY_RETIRED',
            __('api.service_unavailable'),
            null,
            410,
        );
    }

    public function requestExport(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }
        if (!Group::query()->whereKey($id)->exists()) {
            return $this->respondWithError('NOT_FOUND', __('api.group_not_found'), null, 404);
        }
        if (!GroupAccessService::canExport($id, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_export_forbidden'), null, 403);
        }

        $tenantId = (int) TenantContext::getId();
        $existing = DB::table('group_data_exports')
            ->where('tenant_id', $tenantId)
            ->where('group_id', $id)
            ->where('requested_by', $userId)
            ->whereIn('status', ['queued', 'processing'])
            ->where('created_at', '>=', now()->subMinutes(10))
            ->orderByDesc('created_at')
            ->first();
        if ($existing !== null) {
            return $this->respondWithData($this->serializeExport($existing), null, 202);
        }

        $exportId = (string) Str::uuid();
        DB::table('group_data_exports')->insert([
            'id' => $exportId,
            'tenant_id' => $tenantId,
            'group_id' => $id,
            'requested_by' => $userId,
            'status' => 'queued',
            'attempts' => 0,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        GenerateGroupDataExport::dispatch($exportId, $tenantId)->afterCommit();
        $row = DB::table('group_data_exports')->where('id', $exportId)->first();
        if ($row === null) {
            return $this->respondWithError('SERVER_ERROR', __('api.server_error'), null, 500);
        }

        return $this->respondWithData($this->serializeExport($row), null, 202);
    }

    public function exportStatus(int $id, string $exportId): JsonResponse
    {
        $row = $this->authorizedExport($id, $exportId);
        if ($row instanceof JsonResponse) {
            return $row;
        }

        return $this->respondWithData($this->serializeExport($row));
    }

    public function downloadExport(int $id, string $exportId): JsonResponse|StreamedResponse
    {
        $row = $this->authorizedExport($id, $exportId);
        if ($row instanceof JsonResponse) {
            return $row;
        }
        if ($row->status !== 'completed' || $row->expires_at === null || Carbon::parse($row->expires_at)->isPast()) {
            $this->expireExport($row);
            return $this->respondWithError('NOT_FOUND', __('api.group_not_found'), null, 404);
        }

        $path = is_string($row->storage_path ?? null) ? $row->storage_path : '';
        $expectedPrefix = "groups/{$row->tenant_id}/{$id}/exports/";
        if (
            $path === ''
            || !str_starts_with(str_replace('\\', '/', $path), $expectedPrefix)
            || !Storage::disk('local')->exists($path)
        ) {
            return $this->respondWithError('NOT_FOUND', __('api.group_not_found'), null, 404);
        }

        return Storage::disk('local')->download(
            $path,
            "group-{$id}-export.json",
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function authorizedExport(int $groupId, string $exportId): object
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }
        if (!Group::query()->whereKey($groupId)->exists()) {
            return $this->respondWithError('NOT_FOUND', __('api.group_not_found'), null, 404);
        }
        if (!GroupAccessService::canExport($groupId, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_export_forbidden'), null, 403);
        }

        $row = DB::table('group_data_exports')
            ->where('id', $exportId)
            ->where('tenant_id', TenantContext::getId())
            ->where('group_id', $groupId)
            ->where('requested_by', $userId)
            ->first();

        return $row ?? $this->respondWithError('NOT_FOUND', __('api.group_not_found'), null, 404);
    }

    /** @return array<string, mixed> */
    private function serializeExport(object $row): array
    {
        $completed = ($row->status ?? null) === 'completed';

        return [
            'id' => (string) $row->id,
            'status' => (string) $row->status,
            'byte_size' => isset($row->byte_size) ? (int) $row->byte_size : null,
            'created_at' => $row->created_at ?? null,
            'completed_at' => $row->completed_at ?? null,
            'expires_at' => $row->expires_at ?? null,
            'download_url' => $completed
                ? "/api/v2/groups/{$row->group_id}/exports/{$row->id}/download"
                : null,
        ];
    }

    private function expireExport(object $row): void
    {
        $path = is_string($row->storage_path ?? null) ? $row->storage_path : '';
        $prefix = "groups/{$row->tenant_id}/{$row->group_id}/exports/";
        if ($path !== '' && str_starts_with(str_replace('\\', '/', $path), $prefix)) {
            Storage::disk('local')->delete($path);
        }
        DB::table('group_data_exports')
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
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\Agent\AgentExecutor;
use App\Services\Agent\AgentRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AG61 — KI-Agenten admin endpoints.
 *
 * Exposes CRUD over agent_definitions, runs, and proposals so the React
 * admin panel can configure, trigger, and review autonomous-agent output.
 *
 * All endpoints are tenant-scoped via TenantContext::getId(). Feature gate
 * `ai_agents` must be enabled on the tenant — enforced here as defence in
 * depth on top of the route-level admin middleware.
 */
class AgentAdminController extends BaseApiController
{
    protected bool $isV2Api = true;

    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('ai_agents')) {
            abort(403, 'AI Agents feature is not enabled for this tenant.');
        }
    }

    private function tenantId(): int
    {
        $id = TenantContext::getId();
        if (!$id) {
            abort(400, 'Tenant context is required.');
        }
        return (int) $id;
    }

    // -----------------------------------------------------------------
    //  Definitions
    // -----------------------------------------------------------------

    /** GET /v2/admin/agents */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $this->ensureFeature();

        if (!Schema::hasTable('agent_definitions')) {
            return $this->respondWithData(['items' => []]);
        }

        $tenantId = $this->tenantId();
        $rows = DB::table('agent_definitions')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => $this->formatDefinition($r))
            ->all();

        return $this->respondWithData(['items' => $rows]);
    }

    /** POST /v2/admin/agents/{id}/toggle */
    public function toggle(int $id): JsonResponse
    {
        $this->requireAdmin();
        $this->ensureFeature();

        $tenantId = $this->tenantId();
        $row = DB::table('agent_definitions')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$row) {
            return $this->respondNotFound('Agent definition not found.');
        }

        DB::table('agent_definitions')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update([
                'is_enabled' => $row->is_enabled ? 0 : 1,
                'updated_at' => now(),
            ]);

        $row = DB::table('agent_definitions')->where('id', $id)->first();
        return $this->respondWithData($this->formatDefinition($row));
    }

    /** PATCH /v2/admin/agents/{id} */
    public function update(int $id, Request $request): JsonResponse
    {
        $this->requireAdmin();
        $this->ensureFeature();

        $tenantId = $this->tenantId();
        $row = DB::table('agent_definitions')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();
        if (!$row) {
            return $this->respondNotFound('Agent definition not found.');
        }

        $update = ['updated_at' => now()];
        if ($request->has('name')) {
            $update['name'] = (string) $request->input('name');
        }
        if ($request->has('description')) {
            $update['description'] = $request->input('description');
        }
        if ($request->has('is_enabled')) {
            $update['is_enabled'] = (bool) $request->input('is_enabled') ? 1 : 0;
        }
        if ($request->has('config')) {
            $cfg = $request->input('config');
            if (is_string($cfg)) {
                $decoded = json_decode($cfg, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $this->respondWithError('INVALID_CONFIG', 'config must be valid JSON.', 'config', 422);
                }
                $cfg = $decoded;
            }
            $update['config'] = json_encode((array) $cfg);
        }

        DB::table('agent_definitions')->where('id', $id)->update($update);

        $row = DB::table('agent_definitions')->where('id', $id)->first();
        return $this->respondWithData($this->formatDefinition($row));
    }

    /** POST /v2/admin/agents/{id}/run-now */
    public function runNow(int $id): JsonResponse
    {
        $userId = $this->requireAdmin();
        $this->ensureFeature();

        $tenantId = $this->tenantId();
        $row = DB::table('agent_definitions')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();
        if (!$row) {
            return $this->respondNotFound('Agent definition not found.');
        }

        $result = AgentRunner::run($id, 'manual', $userId);
        return $this->respondWithData($result);
    }

    // -----------------------------------------------------------------
    //  Runs
    // -----------------------------------------------------------------

    /** GET /v2/admin/agents/runs */
    public function runs(Request $request): JsonResponse
    {
        $this->requireAdmin();
        $this->ensureFeature();

        if (!Schema::hasTable('agent_runs')) {
            return $this->respondWithData(['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20]);
        }

        $tenantId = $this->tenantId();
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $page    = max(1, (int) $request->input('page', 1));

        $query = DB::table('agent_runs')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id');

        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get()->all();

        return $this->respondWithData([
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }

    // -----------------------------------------------------------------
    //  Proposals
    // -----------------------------------------------------------------

    /** GET /v2/admin/agents/proposals */
    public function proposals(Request $request): JsonResponse
    {
        $this->requireAdmin();
        $this->ensureFeature();

        if (!Schema::hasTable('agent_proposals')) {
            return $this->respondWithData(['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20]);
        }

        $tenantId = $this->tenantId();
        $perPage  = max(1, min(100, (int) $request->input('per_page', 20)));
        $page     = max(1, (int) $request->input('page', 1));
        $status   = $request->input('status', 'pending');

        $statusValue = match ($status) {
            'pending', 'pending_review' => 'pending_review',
            'approved'                  => 'approved',
            'rejected'                  => 'rejected',
            'all'                       => null,
            default                     => 'pending_review',
        };

        $query = DB::table('agent_proposals')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id');

        if ($statusValue !== null) {
            $query->where('status', $statusValue);
        }

        $total = (clone $query)->count();
        $rows  = $query->forPage($page, $perPage)->get()
            ->map(function ($r) {
                $arr = (array) $r;
                if (isset($arr['proposal_data']) && is_string($arr['proposal_data'])) {
                    $arr['proposal_data'] = json_decode($arr['proposal_data'], true) ?? [];
                }
                return $arr;
            })
            ->all();

        return $this->respondWithData([
            'items'    => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }

    /** POST /v2/admin/agents/proposals/{id}/approve */
    public function approve(int $proposalId, Request $request): JsonResponse
    {
        $userId = $this->requireAdmin();
        $this->ensureFeature();

        try {
            $updated = AgentExecutor::approve(
                $proposalId,
                $this->tenantId(),
                $userId,
                $request->input('note'),
            );
        } catch (\Throwable $e) {
            return $this->respondWithError('APPROVE_FAILED', $e->getMessage(), null, 400);
        }

        return $this->respondWithData($updated);
    }

    /** POST /v2/admin/agents/proposals/{id}/reject */
    public function reject(int $proposalId, Request $request): JsonResponse
    {
        $userId = $this->requireAdmin();
        $this->ensureFeature();

        try {
            AgentExecutor::reject(
                $proposalId,
                $this->tenantId(),
                $userId,
                $request->input('note'),
            );
        } catch (\Throwable $e) {
            return $this->respondWithError('REJECT_FAILED', $e->getMessage(), null, 400);
        }

        return $this->respondWithData(['rejected' => true]);
    }

    /** POST /v2/admin/agents/proposals/{id}/edit-approve */
    public function editAndApprove(int $proposalId, Request $request): JsonResponse
    {
        $userId = $this->requireAdmin();
        $this->ensureFeature();

        $payload = $request->input('edited_payload');
        if (!is_array($payload)) {
            return $this->respondWithError('INVALID_PAYLOAD', 'edited_payload must be an object.', 'edited_payload', 422);
        }

        try {
            $updated = AgentExecutor::editAndApprove(
                $proposalId,
                $this->tenantId(),
                $userId,
                $payload,
                $request->input('note'),
            );
        } catch (\Throwable $e) {
            return $this->respondWithError('EDIT_APPROVE_FAILED', $e->getMessage(), null, 400);
        }

        return $this->respondWithData($updated);
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    /** @return array<string,mixed> */
    private function formatDefinition(object $row): array
    {
        $arr = (array) $row;
        if (isset($arr['config']) && is_string($arr['config'])) {
            $arr['config'] = json_decode($arr['config'], true) ?? [];
        }
        $arr['is_enabled'] = (bool) ($arr['is_enabled'] ?? false);
        return $arr;
    }
}

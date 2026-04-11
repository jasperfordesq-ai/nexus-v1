<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CreditCommonsNodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AdminCcConfigController — Admin CRUD for Credit Commons node configuration.
 *
 * GET  /api/v2/admin/federation/cc-config — Get current CC node config + stats
 * PUT  /api/v2/admin/federation/cc-config — Update CC node config
 */
class AdminCcConfigController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/federation/cc-config
     */
    public function show(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $config = CreditCommonsNodeService::getNodeConfig($tenantId);
        $absolutePath = CreditCommonsNodeService::getAbsolutePath($tenantId);

        // Gather stats
        $stats = DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->selectRaw('
                COUNT(*) as trades,
                COUNT(DISTINCT sender_id) + COUNT(DISTINCT receiver_id) as traders,
                COALESCE(SUM(amount), 0) as volume
            ')->first();

        $accountCount = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        $entryCount = DB::table('federation_cc_entries')
            ->where('tenant_id', $tenantId)
            ->count();

        return $this->respondWithData([
            'node_slug' => $config->node_slug,
            'display_name' => $config->display_name,
            'currency_format' => $config->currency_format,
            'exchange_rate' => (float) $config->exchange_rate,
            'validated_window' => (int) $config->validated_window,
            'parent_node_url' => $config->parent_node_url,
            'parent_node_slug' => $config->parent_node_slug,
            'last_hash' => $config->last_hash,
            'absolute_path' => $absolutePath,
            'stats' => [
                'trades' => (int) ($stats->trades ?? 0),
                'traders' => (int) ($stats->traders ?? 0),
                'volume' => (float) ($stats->volume ?? 0),
                'accounts' => $accountCount,
                'entries' => $entryCount,
            ],
        ]);
    }

    /**
     * PUT /api/v2/admin/federation/cc-config
     */
    public function update(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $input = $this->getAllInput();

        // Validate node_slug format
        if (isset($input['node_slug'])) {
            if (!preg_match('/^[0-9a-z-]{3,15}$/', $input['node_slug'])) {
                return $this->respondWithError('VALIDATION_ERROR',
                    'Node slug must be 3-15 characters, lowercase letters, numbers, and hyphens only',
                    'node_slug', 422);
            }
        }

        // Validate exchange_rate
        if (isset($input['exchange_rate'])) {
            $rate = (float) $input['exchange_rate'];
            if ($rate <= 0 || $rate > 1000) {
                return $this->respondWithError('VALIDATION_ERROR',
                    'Exchange rate must be between 0.01 and 1000', 'exchange_rate', 422);
            }
        }

        // Validate validated_window
        if (isset($input['validated_window'])) {
            $window = (int) $input['validated_window'];
            if ($window < 30 || $window > 86400) {
                return $this->respondWithError('VALIDATION_ERROR',
                    'Validation window must be between 30 and 86400 seconds', 'validated_window', 422);
            }
        }

        // Ensure config exists
        CreditCommonsNodeService::getNodeConfig($tenantId);

        // Update
        CreditCommonsNodeService::updateNodeConfig($tenantId, $input);

        return $this->respondWithData(['updated' => true]);
    }
}

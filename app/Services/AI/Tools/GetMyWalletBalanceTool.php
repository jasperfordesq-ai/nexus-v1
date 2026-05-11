<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\AI\Tools;

use App\Services\TenantFeatureConfig;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Look up the calling user's own time credit balance and a short
 * transaction summary. Always scoped to the calling user — never to
 * another member.
 */
class GetMyWalletBalanceTool extends AbstractTool
{
    public function name(): string
    {
        return 'get_my_wallet_balance';
    }

    public function description(): string
    {
        return 'Get the current user\'s time credit balance and recent transaction count. Use only when the user asks about THEIR OWN balance, hours, or wallet. Never call for another user.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];
    }

    public function isAvailable(int $userId): bool
    {
        $tenant = TenantContext::get() ?: [];
        $config = $tenant['configuration'] ?? null;
        if (is_string($config)) {
            $config = json_decode($config, true) ?: [];
        }
        $modules = is_array($config['modules'] ?? null) ? $config['modules'] : [];
        $merged = TenantFeatureConfig::mergeModules($modules);
        return !empty($merged['wallet']);
    }

    public function execute(array $arguments, int $userId): array
    {
        $tenantId = $this->tenantId();

        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->select('balance')
            ->first();

        if (!$user) {
            return $this->err('Could not locate the current user.');
        }

        $recentCount = (int) DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return $this->ok(
            sprintf('Balance: %.2f hours. Transactions in last 30 days: %d.', (float) $user->balance, $recentCount),
            [[
                'balance' => (float) $user->balance,
                'recent_transactions_30d' => $recentCount,
            ]],
            'wallet'
        );
    }
}

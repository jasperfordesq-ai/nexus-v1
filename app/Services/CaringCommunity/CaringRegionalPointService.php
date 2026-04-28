<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use App\Core\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class CaringRegionalPointService
{
    private const PREFIX = 'caring_community.regional_points.';

    private const DEFAULTS = [
        'enabled' => false,
        'label' => 'Regional Points',
        'symbol' => 'pts',
        'auto_issue_enabled' => false,
        'points_per_approved_hour' => 0,
        'member_transfers_enabled' => false,
        'marketplace_redemption_enabled' => false,
    ];

    private const TYPES = [
        'enabled' => 'boolean',
        'label' => 'string',
        'symbol' => 'string',
        'auto_issue_enabled' => 'boolean',
        'points_per_approved_hour' => 'float',
        'member_transfers_enabled' => 'boolean',
        'marketplace_redemption_enabled' => 'boolean',
    ];

    public function isEnabled(?int $tenantId = null): bool
    {
        $tenantId ??= TenantContext::getId();

        return $this->tenantHasCaringFeature($tenantId)
            && Schema::hasTable('caring_regional_point_accounts')
            && Schema::hasTable('caring_regional_point_transactions')
            && (bool) $this->getConfig($tenantId)['enabled'];
    }

    public function getConfig(int $tenantId): array
    {
        if (!Schema::hasTable('tenant_settings')) {
            return self::DEFAULTS;
        }

        $rows = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->whereIn('setting_key', array_map(
                fn (string $key): string => self::PREFIX . $key,
                array_keys(self::DEFAULTS)
            ))
            ->pluck('setting_value', 'setting_key')
            ->all();

        $config = self::DEFAULTS;
        foreach (self::DEFAULTS as $key => $default) {
            $settingKey = self::PREFIX . $key;
            if (array_key_exists($settingKey, $rows)) {
                $config[$key] = $this->castValue($rows[$settingKey], self::TYPES[$key], $default);
            }
        }

        return $this->normaliseConfig($config);
    }

    public function updateConfig(int $tenantId, array $input): array
    {
        $config = $this->normaliseConfig(array_merge(
            $this->getConfig($tenantId),
            array_intersect_key($input, self::DEFAULTS)
        ));

        if (!Schema::hasTable('tenant_settings')) {
            return $config;
        }

        foreach ($config as $key => $value) {
            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $tenantId, 'setting_key' => self::PREFIX . $key],
                [
                    'setting_value' => $this->serialiseValue($value),
                    'setting_type' => self::TYPES[$key],
                    'category' => 'caring_community',
                    'description' => 'Caring community regional points setting.',
                    'updated_by' => Auth::id(),
                    'updated_at' => now(),
                ]
            );
        }

        return $this->getConfig($tenantId);
    }

    public function memberSummary(int $userId): array
    {
        $tenantId = TenantContext::getId();
        $this->assertEnabled($tenantId);

        $account = $this->ensureAccount($tenantId, $userId);

        return [
            'enabled' => true,
            'config' => $this->publicConfig($tenantId),
            'account' => [
                'user_id' => $userId,
                'balance' => round((float) $account->balance, 2),
                'lifetime_earned' => round((float) $account->lifetime_earned, 2),
                'lifetime_spent' => round((float) $account->lifetime_spent, 2),
            ],
        ];
    }

    public function memberHistory(int $userId, int $limit = 50): array
    {
        $tenantId = TenantContext::getId();
        $this->assertEnabled($tenantId);

        $limit = max(1, min(200, $limit));
        $this->ensureAccount($tenantId, $userId);

        return DB::table('caring_regional_point_transactions')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => $this->formatTransaction($row))
            ->all();
    }

    public function tenantLedger(int $limit = 100): array
    {
        $tenantId = TenantContext::getId();
        $this->assertEnabled($tenantId);

        $limit = max(1, min(500, $limit));

        return DB::table('caring_regional_point_transactions as t')
            ->leftJoin('users as u', function ($join) {
                $join->on('u.id', '=', 't.user_id')
                    ->on('u.tenant_id', '=', 't.tenant_id');
            })
            ->leftJoin('users as actor', function ($join) {
                $join->on('actor.id', '=', 't.actor_user_id')
                    ->on('actor.tenant_id', '=', 't.tenant_id');
            })
            ->where('t.tenant_id', $tenantId)
            ->orderByDesc('t.created_at')
            ->orderByDesc('t.id')
            ->limit($limit)
            ->select([
                't.*',
                'u.name as user_name',
                'u.first_name as user_first_name',
                'u.last_name as user_last_name',
                'u.email as user_email',
                'actor.name as actor_name',
                'actor.first_name as actor_first_name',
                'actor.last_name as actor_last_name',
            ])
            ->get()
            ->map(fn ($row): array => $this->formatTransaction($row) + [
                'user_name' => $this->displayName($row, 'user'),
                'user_email' => $row->user_email,
                'actor_name' => $this->displayName($row, 'actor'),
            ])
            ->all();
    }

    public function tenantStats(): array
    {
        $tenantId = TenantContext::getId();
        $this->assertEnabled($tenantId);

        $accounts = DB::table('caring_regional_point_accounts')
            ->where('tenant_id', $tenantId);

        $issued = DB::table('caring_regional_point_transactions')
            ->where('tenant_id', $tenantId)
            ->where('direction', 'credit')
            ->sum('points');

        $spent = DB::table('caring_regional_point_transactions')
            ->where('tenant_id', $tenantId)
            ->where('direction', 'debit')
            ->sum('points');

        return [
            'accounts_count' => (int) (clone $accounts)->count(),
            'circulating_points' => round((float) (clone $accounts)->sum('balance'), 2),
            'total_issued' => round((float) $issued, 2),
            'total_spent' => round((float) $spent, 2),
        ];
    }

    public function issue(int $userId, float $points, string $description, int $actorId): array
    {
        return $this->credit(
            userId: $userId,
            points: $points,
            type: 'admin_issue',
            description: $description,
            actorId: $actorId
        );
    }

    public function adjust(int $userId, float $pointsDelta, string $description, int $actorId): array
    {
        if ($pointsDelta === 0.0) {
            throw new InvalidArgumentException(__('api.caring_regional_points_nonzero'));
        }

        if ($pointsDelta > 0) {
            return $this->credit($userId, $pointsDelta, 'admin_adjustment', $description, $actorId);
        }

        return $this->debit($userId, abs($pointsDelta), 'admin_adjustment', $description, $actorId);
    }

    public function publicConfig(int $tenantId): array
    {
        $config = $this->getConfig($tenantId);

        return [
            'label' => $config['label'],
            'symbol' => $config['symbol'],
            'member_transfers_enabled' => $config['member_transfers_enabled'],
            'marketplace_redemption_enabled' => $config['marketplace_redemption_enabled'],
        ];
    }

    private function credit(int $userId, float $points, string $type, string $description, int $actorId): array
    {
        $tenantId = TenantContext::getId();
        $this->assertEnabled($tenantId);
        $points = $this->normalisePoints($points);
        $this->assertTenantUser($tenantId, $userId);

        return DB::transaction(function () use ($tenantId, $userId, $points, $type, $description, $actorId): array {
            $account = $this->lockAccount($tenantId, $userId);
            $newBalance = round((float) $account->balance + $points, 2);

            DB::table('caring_regional_point_accounts')
                ->where('id', $account->id)
                ->update([
                    'balance' => $newBalance,
                    'lifetime_earned' => round((float) $account->lifetime_earned + $points, 2),
                    'updated_at' => now(),
                ]);

            $transactionId = $this->insertTransaction(
                tenantId: $tenantId,
                accountId: (int) $account->id,
                userId: $userId,
                actorId: $actorId,
                type: $type,
                direction: 'credit',
                points: $points,
                balanceAfter: $newBalance,
                description: $description
            );

            return [
                'transaction_id' => $transactionId,
                'user_id' => $userId,
                'points' => $points,
                'balance' => $newBalance,
            ];
        });
    }

    private function debit(int $userId, float $points, string $type, string $description, int $actorId): array
    {
        $tenantId = TenantContext::getId();
        $this->assertEnabled($tenantId);
        $points = $this->normalisePoints($points);
        $this->assertTenantUser($tenantId, $userId);

        return DB::transaction(function () use ($tenantId, $userId, $points, $type, $description, $actorId): array {
            $account = $this->lockAccount($tenantId, $userId);
            $currentBalance = (float) $account->balance;
            if ($currentBalance < $points) {
                throw new RuntimeException(__('api.caring_regional_points_insufficient'));
            }

            $newBalance = round($currentBalance - $points, 2);

            DB::table('caring_regional_point_accounts')
                ->where('id', $account->id)
                ->update([
                    'balance' => $newBalance,
                    'lifetime_spent' => round((float) $account->lifetime_spent + $points, 2),
                    'updated_at' => now(),
                ]);

            $transactionId = $this->insertTransaction(
                tenantId: $tenantId,
                accountId: (int) $account->id,
                userId: $userId,
                actorId: $actorId,
                type: $type,
                direction: 'debit',
                points: $points,
                balanceAfter: $newBalance,
                description: $description
            );

            return [
                'transaction_id' => $transactionId,
                'user_id' => $userId,
                'points' => -$points,
                'balance' => $newBalance,
            ];
        });
    }

    private function ensureAccount(int $tenantId, int $userId): object
    {
        $this->assertTenantUser($tenantId, $userId);

        $existing = DB::table('caring_regional_point_accounts')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return $existing;
        }

        DB::table('caring_regional_point_accounts')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'balance' => 0,
            'lifetime_earned' => 0,
            'lifetime_spent' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('caring_regional_point_accounts')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();
    }

    private function lockAccount(int $tenantId, int $userId): object
    {
        $this->ensureAccount($tenantId, $userId);

        return DB::table('caring_regional_point_accounts')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();
    }

    private function insertTransaction(
        int $tenantId,
        int $accountId,
        int $userId,
        ?int $actorId,
        string $type,
        string $direction,
        float $points,
        float $balanceAfter,
        string $description,
    ): int {
        return (int) DB::table('caring_regional_point_transactions')->insertGetId([
            'tenant_id' => $tenantId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'actor_user_id' => $actorId > 0 ? $actorId : null,
            'type' => $type,
            'direction' => $direction,
            'points' => $points,
            'balance_after' => $balanceAfter,
            'description' => trim($description) !== '' ? mb_substr(trim($description), 0, 500) : null,
            'metadata' => null,
            'created_at' => now(),
        ]);
    }

    private function assertEnabled(int $tenantId): void
    {
        if (!$this->isEnabled($tenantId)) {
            throw new RuntimeException(__('api.caring_regional_points_disabled'));
        }
    }

    private function tenantHasCaringFeature(int $tenantId): bool
    {
        $featuresJson = DB::table('tenants')->where('id', $tenantId)->value('features');
        $features = is_string($featuresJson) && $featuresJson !== ''
            ? json_decode($featuresJson, true)
            : [];

        if (!is_array($features)) {
            $features = [];
        }

        return array_key_exists('caring_community', $features)
            ? (bool) $features['caring_community']
            : false;
    }

    private function assertTenantUser(int $tenantId, int $userId): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException(__('api.user_not_found'));
        }

        $exists = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->exists();

        if (!$exists) {
            throw new InvalidArgumentException(__('api.user_not_found'));
        }
    }

    private function normaliseConfig(array $config): array
    {
        $config['enabled'] = (bool) $config['enabled'];
        $config['label'] = trim((string) $config['label']) !== '' ? mb_substr(trim((string) $config['label']), 0, 80) : self::DEFAULTS['label'];
        $config['symbol'] = trim((string) $config['symbol']) !== '' ? mb_substr(trim((string) $config['symbol']), 0, 12) : self::DEFAULTS['symbol'];
        $config['auto_issue_enabled'] = (bool) $config['auto_issue_enabled'];
        $config['points_per_approved_hour'] = max(0.0, min(10000.0, round((float) $config['points_per_approved_hour'], 2)));
        $config['member_transfers_enabled'] = (bool) $config['member_transfers_enabled'];
        $config['marketplace_redemption_enabled'] = (bool) $config['marketplace_redemption_enabled'];

        if (!$config['enabled']) {
            $config['auto_issue_enabled'] = false;
            $config['member_transfers_enabled'] = false;
            $config['marketplace_redemption_enabled'] = false;
        }

        return $config;
    }

    private function normalisePoints(float $points): float
    {
        $points = round($points, 2);
        if ($points <= 0) {
            throw new InvalidArgumentException(__('api.caring_regional_points_positive'));
        }
        if ($points > 1000000) {
            throw new InvalidArgumentException(__('api.caring_regional_points_too_many'));
        }

        return $points;
    }

    private function castValue(mixed $value, string $type, mixed $default): mixed
    {
        if ($value === null) {
            return $default;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'float' => (float) $value,
            default => (string) $value,
        };
    }

    private function serialiseValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private function formatTransaction(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'user_id' => (int) $row->user_id,
            'actor_user_id' => $row->actor_user_id !== null ? (int) $row->actor_user_id : null,
            'type' => (string) $row->type,
            'direction' => (string) $row->direction,
            'points' => round((float) $row->points, 2),
            'balance_after' => round((float) $row->balance_after, 2),
            'description' => $row->description,
            'created_at' => $row->created_at,
        ];
    }

    private function displayName(object $row, string $prefix): ?string
    {
        $name = $row->{$prefix . '_name'} ?? null;
        if (is_string($name) && trim($name) !== '') {
            return $name;
        }

        $first = trim((string) ($row->{$prefix . '_first_name'} ?? ''));
        $last = trim((string) ($row->{$prefix . '_last_name'} ?? ''));
        $full = trim($first . ' ' . $last);

        return $full !== '' ? $full : null;
    }
}

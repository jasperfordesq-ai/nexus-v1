<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stores KISS workflow policy settings in the tenant_settings key-value table.
 */
class CaringCommunityWorkflowPolicyService
{
    private const PREFIX = 'caring_community.workflow.';

    private const DEFAULTS = [
        'approval_required' => true,
        'auto_approve_trusted_reviewers' => false,
        'review_sla_days' => 7,
        'escalation_sla_days' => 14,
        'allow_member_self_log' => true,
        'require_organisation_for_partner_hours' => true,
        'monthly_statement_day' => 1,
        'municipal_report_default_period' => 'last_90_days',
        'include_social_value_estimate' => true,
        'default_hour_value_chf' => 35,
    ];

    private const TYPES = [
        'approval_required' => 'boolean',
        'auto_approve_trusted_reviewers' => 'boolean',
        'review_sla_days' => 'integer',
        'escalation_sla_days' => 'integer',
        'allow_member_self_log' => 'boolean',
        'require_organisation_for_partner_hours' => 'boolean',
        'monthly_statement_day' => 'integer',
        'municipal_report_default_period' => 'string',
        'include_social_value_estimate' => 'boolean',
        'default_hour_value_chf' => 'integer',
    ];

    private const PERIODS = ['last_30_days', 'last_90_days', 'year_to_date', 'previous_quarter'];

    public function get(int $tenantId): array
    {
        if (!Schema::hasTable('tenant_settings')) {
            return self::DEFAULTS;
        }

        $rows = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->whereIn('setting_key', array_map(fn (string $key): string => self::PREFIX . $key, array_keys(self::DEFAULTS)))
            ->pluck('setting_value', 'setting_key')
            ->all();

        $policy = self::DEFAULTS;
        foreach (self::DEFAULTS as $key => $default) {
            $settingKey = self::PREFIX . $key;
            if (!array_key_exists($settingKey, $rows)) {
                continue;
            }

            $policy[$key] = $this->castValue($rows[$settingKey], self::TYPES[$key], $default);
        }

        return $this->normalise($policy);
    }

    public function update(int $tenantId, array $input): array
    {
        $policy = $this->normalise(array_merge($this->get($tenantId), array_intersect_key($input, self::DEFAULTS)));

        if (!Schema::hasTable('tenant_settings')) {
            return $policy;
        }

        foreach ($policy as $key => $value) {
            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $tenantId, 'setting_key' => self::PREFIX . $key],
                [
                    'setting_value' => $this->serialiseValue($value),
                    'setting_type' => self::TYPES[$key],
                    'category' => 'caring_community',
                    'description' => 'Caring community workflow policy setting.',
                    'updated_by' => Auth::id(),
                    'updated_at' => now(),
                ]
            );
        }

        return $this->get($tenantId);
    }

    private function normalise(array $policy): array
    {
        $policy['approval_required'] = (bool) $policy['approval_required'];
        $policy['auto_approve_trusted_reviewers'] = (bool) $policy['auto_approve_trusted_reviewers'];
        $policy['allow_member_self_log'] = (bool) $policy['allow_member_self_log'];
        $policy['require_organisation_for_partner_hours'] = (bool) $policy['require_organisation_for_partner_hours'];
        $policy['include_social_value_estimate'] = (bool) $policy['include_social_value_estimate'];
        $policy['review_sla_days'] = $this->clampInt($policy['review_sla_days'], 1, 30);
        $policy['escalation_sla_days'] = $this->clampInt($policy['escalation_sla_days'], $policy['review_sla_days'], 60);
        $policy['monthly_statement_day'] = $this->clampInt($policy['monthly_statement_day'], 1, 28);
        $policy['default_hour_value_chf'] = $this->clampInt($policy['default_hour_value_chf'], 0, 500);

        if (!in_array($policy['municipal_report_default_period'], self::PERIODS, true)) {
            $policy['municipal_report_default_period'] = self::DEFAULTS['municipal_report_default_period'];
        }

        return $policy;
    }

    private function castValue(mixed $value, string $type, mixed $default): mixed
    {
        if ($value === null) {
            return $default;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
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

    private function clampInt(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }
}

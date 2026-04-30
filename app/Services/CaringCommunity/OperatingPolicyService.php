<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AG81 — KISS operating-policy workshop.
 *
 * Persistence layer for the human rules that govern a Caring Community pilot:
 * approval authority, trusted-reviewer threshold, SLA windows, legacy-hour
 * settlement policy, reciprocal-balance intervention, safeguarding escalation
 * owner, CHF social value methodology, and statement cadence.
 *
 * Stored as discrete `caring.operating_policy.*` keys in `tenant_settings` so
 * each setting can be inspected and audited individually. Defaults follow the
 * KISS / Age-Stiftung methodology used by AG76 + AG83.
 */
class OperatingPolicyService
{
    public const KEY_PREFIX = 'caring.operating_policy.';

    /**
     * Schema definition for every policy field. Drives validation and the
     * admin UI form.
     *
     * @return array<string, array{type: string, default: mixed, choices?: array<int, string>, min?: float|int, max?: float|int}>
     */
    public function schema(): array
    {
        return [
            'approval_authority' => [
                'type'    => 'enum',
                'default' => 'admin',
                'choices' => ['admin', 'coordinator', 'mutual'],
            ],
            'trusted_reviewer_threshold' => [
                'type'    => 'int',
                'default' => 5,
                'min'     => 1,
                'max'     => 200,
            ],
            'sla_first_response_hours' => [
                'type'    => 'int',
                'default' => 24,
                'min'     => 1,
                'max'     => 168,
            ],
            'sla_help_request_hours' => [
                'type'    => 'int',
                'default' => 72,
                'min'     => 1,
                'max'     => 336,
            ],
            'legacy_hour_settlement' => [
                'type'    => 'enum',
                'default' => 'transfer_to_beneficiary',
                'choices' => ['transfer_to_beneficiary', 'donate_to_solidarity', 'expire'],
            ],
            'reciprocal_balance_threshold_hours' => [
                'type'    => 'int',
                'default' => 40,
                'min'     => 0,
                'max'     => 500,
            ],
            'safeguarding_escalation_user_id' => [
                'type'    => 'int_nullable',
                'default' => null,
                'min'     => 1,
            ],
            'chf_hourly_rate' => [
                'type'    => 'float',
                'default' => 35.0,
                'min'     => 0,
                'max'     => 500,
            ],
            'chf_prevention_multiplier' => [
                'type'    => 'float',
                'default' => 2.0,
                'min'     => 1,
                'max'     => 10,
            ],
            'statement_cadence' => [
                'type'    => 'enum',
                'default' => 'quarterly',
                'choices' => ['monthly', 'quarterly', 'annual'],
            ],
            'policy_appendix_url' => [
                'type'    => 'url_nullable',
                'default' => null,
            ],
        ];
    }

    /**
     * Read the full policy for a tenant, applying schema defaults for any
     * missing key. Always returns the canonical typed shape.
     */
    public function get(int $tenantId): array
    {
        $stored = $this->loadStoredValues($tenantId);
        $out = [];
        foreach ($this->schema() as $field => $meta) {
            $out[$field] = $this->cast($stored[$field] ?? null, $meta);
        }
        return [
            'policy'        => $out,
            'schema'        => $this->schema(),
            'last_updated_at' => $this->latestUpdatedAt($tenantId),
        ];
    }

    /**
     * Validate + persist a partial or full update.
     *
     * @param array<string, mixed> $payload
     * @return array{policy?: array<string, mixed>, errors?: list<array{field: string, message: string}>}
     */
    public function update(int $tenantId, array $payload): array
    {
        $errors = [];
        $sanitised = [];

        foreach ($this->schema() as $field => $meta) {
            if (!array_key_exists($field, $payload)) {
                continue; // partial update — leave existing value alone
            }
            $raw = $payload[$field];
            $valid = $this->validate($field, $raw, $meta, $errors);
            if ($valid !== null || $meta['type'] === 'int_nullable' || $meta['type'] === 'url_nullable') {
                $sanitised[$field] = $valid;
            }
        }

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $now = now();
        foreach ($sanitised as $field => $val) {
            $type = $this->schema()[$field]['type'];
            $persisted = $this->serialise($val, $type);
            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $tenantId, 'setting_key' => self::KEY_PREFIX . $field],
                [
                    'setting_value' => $persisted,
                    'setting_type'  => $this->settingTypeFor($type),
                    'category'      => 'caring_community',
                    'description'   => 'AG81 operating policy: ' . $field,
                    'updated_at'    => $now,
                ],
            );
        }

        return ['policy' => $this->get($tenantId)['policy']];
    }

    private function loadStoredValues(int $tenantId): array
    {
        if (!Schema::hasTable('tenant_settings')) {
            return [];
        }

        $rows = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', 'like', self::KEY_PREFIX . '%')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $field = substr((string) $row->setting_key, strlen(self::KEY_PREFIX));
            $out[$field] = $row->setting_value;
        }
        return $out;
    }

    private function latestUpdatedAt(int $tenantId): ?string
    {
        if (!Schema::hasTable('tenant_settings')) {
            return null;
        }
        $row = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', 'like', self::KEY_PREFIX . '%')
            ->orderByDesc('updated_at')
            ->first();
        return $row?->updated_at ? (string) $row->updated_at : null;
    }

    private function cast(mixed $stored, array $meta): mixed
    {
        if ($stored === null || $stored === '') {
            return $meta['default'];
        }

        return match ($meta['type']) {
            'int', 'int_nullable' => is_numeric($stored) ? (int) $stored : $meta['default'],
            'float'  => is_numeric($stored) ? (float) $stored : $meta['default'],
            'enum'   => in_array($stored, $meta['choices'] ?? [], true) ? $stored : $meta['default'],
            'url_nullable' => is_string($stored) ? $stored : $meta['default'],
            default  => $stored,
        };
    }

    private function validate(string $field, mixed $raw, array $meta, array &$errors): mixed
    {
        $type = $meta['type'];

        if (in_array($type, ['int_nullable', 'url_nullable'], true) && ($raw === null || $raw === '')) {
            return null;
        }

        switch ($type) {
            case 'int':
            case 'int_nullable':
                if (!is_numeric($raw)) {
                    $errors[] = ['field' => $field, 'message' => 'must be an integer'];
                    return null;
                }
                $val = (int) $raw;
                if (isset($meta['min']) && $val < $meta['min']) {
                    $errors[] = ['field' => $field, 'message' => "must be ≥ {$meta['min']}"];
                    return null;
                }
                if (isset($meta['max']) && $val > $meta['max']) {
                    $errors[] = ['field' => $field, 'message' => "must be ≤ {$meta['max']}"];
                    return null;
                }
                return $val;

            case 'float':
                if (!is_numeric($raw)) {
                    $errors[] = ['field' => $field, 'message' => 'must be numeric'];
                    return null;
                }
                $val = (float) $raw;
                if (isset($meta['min']) && $val < $meta['min']) {
                    $errors[] = ['field' => $field, 'message' => "must be ≥ {$meta['min']}"];
                    return null;
                }
                if (isset($meta['max']) && $val > $meta['max']) {
                    $errors[] = ['field' => $field, 'message' => "must be ≤ {$meta['max']}"];
                    return null;
                }
                return $val;

            case 'enum':
                $val = (string) $raw;
                if (!in_array($val, $meta['choices'] ?? [], true)) {
                    $errors[] = ['field' => $field, 'message' => 'invalid choice'];
                    return null;
                }
                return $val;

            case 'url_nullable':
                $val = trim((string) $raw);
                if (!filter_var($val, FILTER_VALIDATE_URL)) {
                    $errors[] = ['field' => $field, 'message' => 'must be a valid URL'];
                    return null;
                }
                return $val;
        }

        return null;
    }

    private function serialise(mixed $val, string $type): ?string
    {
        if ($val === null) {
            return null;
        }
        return match ($type) {
            'int', 'int_nullable' => (string) (int) $val,
            'float' => (string) (float) $val,
            default => (string) $val,
        };
    }

    private function settingTypeFor(string $type): string
    {
        return match ($type) {
            'int', 'int_nullable' => 'integer',
            'float' => 'float',
            default => 'string',
        };
    }
}

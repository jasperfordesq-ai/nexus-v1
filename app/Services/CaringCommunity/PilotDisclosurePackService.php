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
 * AG80 — Swiss FADP / nDSG pilot disclosure pack.
 *
 * Captures the controller/processor matrix, data categories, retention defaults,
 * export/deletion handling, research consent, federation aggregate policy,
 * isolated-node option, subprocessors, and incident-response owner that a
 * Swiss pilot must publish before onboarding residents.
 *
 * Stored as a single JSON envelope under the `caring.disclosure_pack` setting key.
 * Returns sensible defaults so an admin can review the canonical pack first.
 *
 * NOT a substitute for legal review. Renders Markdown an admin can hand to
 * legal counsel for the final pilot disclosure document.
 */
class PilotDisclosurePackService
{
    public const SETTING_KEY = 'caring.disclosure_pack';

    /**
     * Canonical default disclosure pack — represents the platform-side
     * commitments before a tenant overrides them.
     */
    public function defaults(): array
    {
        return [
            'controller' => [
                'name'      => '',
                'address'   => '',
                'contact_email' => '',
                'data_protection_officer' => '',
            ],
            'processor' => [
                'name'      => 'Project NEXUS / Jasper Ford',
                'address'   => '',
                'contact_email' => 'funding@hour-timebank.ie',
                'sub_processors' => [
                    '@copy:subprocessor_azure',
                    '@copy:subprocessor_cloudflare',
                    '@copy:subprocessor_stripe',
                    '@copy:subprocessor_openai',
                    '@copy:subprocessor_firebase',
                ],
            ],
            'data_categories' => [
                'identity' => ['name', 'email', 'phone', 'date_of_birth'],
                'profile'  => ['biography', 'photo', 'skills', 'preferred_language'],
                'caring'   => ['help_requests', 'support_relationships', 'caregiver_links'],
                'time_credits' => ['transactions', 'volunteer_logs', 'wallet_balance'],
                'communications' => ['messages', 'notifications', 'announcements'],
                'safeguarding' => ['reports', 'flags', 'verification_status'],
                'research_consent' => ['flag', 'aggregate_dataset_inclusion'],
            ],
            'lawful_basis' => [
                'identity'         => 'contract',
                'profile'          => 'consent',
                'caring'           => 'contract',
                'time_credits'     => 'contract',
                'communications'   => 'contract',
                'safeguarding'     => 'legitimate_interest',
                'research_consent' => 'consent',
            ],
            'retention_defaults' => [
                'active_account_data'   => 'lifetime_of_membership',
                'inactive_account_data' => '24_months_then_anonymise',
                'transactions'          => '10_years_after_completion',
                'help_requests'         => '24_months_after_closure',
                'safeguarding_reports'  => '7_years_after_resolution',
                'communications'        => '24_months',
                'research_datasets'     => 'duration_of_research_partnership',
            ],
            'data_subject_rights' => [
                'access'     => true,
                'export'     => true,
                'rectify'    => true,
                'erase'      => true,
                'restrict'   => true,
                'object'     => true,
                'portability' => true,
                'export_format' => 'json+csv',
            ],
            'federation' => [
                'enabled' => false,
                'aggregate_policy' => 'no_personal_data_shared_outside_tenant',
                'opt_out' => true,
            ],
            'isolated_node' => [
                'available' => true,
                'description' => '@copy:isolated_node_description',
                'hosting_owner' => '',
                'smtp_owner'    => '',
                'storage_owner' => '',
                'backup_owner'  => '',
                'update_cadence' => 'monthly',
            ],
            'incident_response' => [
                'owner_name' => '',
                'contact_email' => '',
                'notification_window_hours' => 72,
                'fadp_authority' => '@copy:fadp_authority',
            ],
            'cross_border_transfers' => [
                'occurs' => true,
                'destinations' => ['@copy:destination_eu_azure', '@copy:destination_us_services'],
                'safeguards' => ['@copy:safeguard_scc', '@copy:safeguard_swiss_us_framework'],
            ],
            'amendments' => [
                'last_reviewed_at' => null,
                'reviewer'         => '',
                'next_review_due'  => null,
            ],
        ];
    }

    public function get(int $tenantId): array
    {
        $stored = $this->loadStored($tenantId);
        $pack = $this->mergeDeep($this->defaults(), $stored);

        return [
            'pack'        => $pack,
            'last_updated_at' => $this->latestUpdatedAt($tenantId),
            'is_customised'   => $stored !== [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{pack?: array<string, mixed>, errors?: list<array{field: string, message: string}>}
     */
    public function update(int $tenantId, array $payload): array
    {
        $errors = [];

        // Lightweight validation. Deep structure mirrors defaults().
        if (isset($payload['incident_response']) && is_array($payload['incident_response'])) {
            $email = $payload['incident_response']['contact_email'] ?? null;
            if ($email !== null && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['field' => 'incident_response.contact_email', 'message' => __('caring_community.disclosure_pack.validation.email')];
            }
            $win = $payload['incident_response']['notification_window_hours'] ?? null;
            if ($win !== null && (!is_numeric($win) || (int) $win < 1 || (int) $win > 720)) {
                $errors[] = ['field' => 'incident_response.notification_window_hours', 'message' => __('caring_community.disclosure_pack.validation.notification_window')];
            }
        }
        if (isset($payload['controller']['contact_email'])
            && $payload['controller']['contact_email'] !== ''
            && !filter_var($payload['controller']['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = ['field' => 'controller.contact_email', 'message' => __('caring_community.disclosure_pack.validation.email')];
        }

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $merged = $this->mergeDeep($this->defaults(), $this->mergeDeep($this->loadStored($tenantId), $payload));

        $now = now();
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'setting_key' => self::SETTING_KEY],
            [
                'setting_value' => json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'setting_type'  => 'json',
                'category'      => 'caring_community',
                'description'   => 'AG80 FADP/nDSG disclosure pack',
                'updated_at'    => $now,
            ],
        );

        return ['pack' => $merged];
    }

    /**
     * Render the disclosure pack as plain Markdown for printing or copying
     * into a final pilot legal-handover document.
     */
    public function renderMarkdown(int $tenantId): string
    {
        $pack = $this->get($tenantId)['pack'];
        $lines = [];
        $lines[] = '# ' . $this->translation('markdown.title');
        $lines[] = '_' . $this->translation('markdown.review_notice') . '_';
        $lines[] = '';
        $lines[] = '## 1. ' . $this->translation('markdown.controller');
        $lines[] = $this->kv($pack['controller']);
        $lines[] = '';
        $lines[] = '## 2. ' . $this->translation('markdown.processor');
        $lines[] = $this->kv(array_diff_key($pack['processor'], ['sub_processors' => true]));
        $lines[] = '';
        $lines[] = '### ' . $this->translation('markdown.sub_processors');
        foreach ($pack['processor']['sub_processors'] ?? [] as $sp) {
            $lines[] = '- ' . $this->displayValue($sp);
        }
        $lines[] = '';
        $lines[] = '## 3. ' . $this->translation('markdown.data_categories');
        foreach ($pack['data_categories'] as $cat => $fields) {
            $lines[] = sprintf(
                '- **%s**: %s',
                $this->fieldLabel((string) $cat),
                implode(', ', array_map(fn (mixed $field): string => $this->displayValue($field), (array) $fields))
            );
        }
        $lines[] = '';
        $lines[] = '## 4. ' . $this->translation('markdown.lawful_basis');
        $lines[] = $this->kv($pack['lawful_basis']);
        $lines[] = '';
        $lines[] = '## 5. ' . $this->translation('markdown.retention_defaults');
        $lines[] = $this->kv($pack['retention_defaults']);
        $lines[] = '';
        $lines[] = '## 6. ' . $this->translation('markdown.data_subject_rights');
        $lines[] = $this->kv($pack['data_subject_rights']);
        $lines[] = '';
        $lines[] = '## 7. ' . $this->translation('markdown.federation_policy');
        $lines[] = $this->kv($pack['federation']);
        $lines[] = '';
        $lines[] = '## 8. ' . $this->translation('markdown.isolated_node');
        $lines[] = $this->kv($pack['isolated_node']);
        $lines[] = '';
        $lines[] = '## 9. ' . $this->translation('markdown.incident_response');
        $lines[] = $this->kv($pack['incident_response']);
        $lines[] = '';
        $lines[] = '## 10. ' . $this->translation('markdown.cross_border_transfers');
        $lines[] = $this->kv(array_diff_key($pack['cross_border_transfers'], ['destinations' => true, 'safeguards' => true]));
        $lines[] = '### ' . $this->translation('markdown.destinations');
        foreach ($pack['cross_border_transfers']['destinations'] ?? [] as $d) {
            $lines[] = '- ' . $this->displayValue($d);
        }
        $lines[] = '### ' . $this->translation('markdown.safeguards');
        foreach ($pack['cross_border_transfers']['safeguards'] ?? [] as $s) {
            $lines[] = '- ' . $this->displayValue($s);
        }
        $lines[] = '';
        $lines[] = '## 11. ' . $this->translation('markdown.amendments');
        $lines[] = $this->kv($pack['amendments']);
        $lines[] = '';
        $lines[] = '_' . $this->translation('markdown.generated', [
            'date' => now()->toIso8601String(),
            'tenant_id' => $tenantId,
        ]) . '_';

        return implode("\n", $lines);
    }

    private function kv(array $data): string
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $v = implode(', ', array_map(fn (mixed $item): string => $this->displayValue($item), $v));
            } elseif (is_bool($v)) {
                $v = $this->translation($v ? 'markdown.true' : 'markdown.false');
            } elseif ($v === null || $v === '') {
                $v = '_' . $this->translation('markdown.unset') . '_';
            } else {
                $v = $this->displayValue($v);
            }
            $out[] = sprintf('- **%s**: %s', $this->fieldLabel((string) $k), (string) $v);
        }
        return implode("\n", $out);
    }

    private function displayValue(mixed $value): string
    {
        $value = (string) $value;
        if (str_starts_with($value, '@copy:')) {
            return $this->translation('copy.' . substr($value, 6));
        }

        $key = 'values.' . $value;
        $translated = $this->translation($key);

        return $translated === 'caring_community.disclosure_pack.' . $key ? $value : $translated;
    }

    private function fieldLabel(string $field): string
    {
        $key = 'fields.' . $field;
        $translated = $this->translation($key);

        return $translated === 'caring_community.disclosure_pack.' . $key ? $field : $translated;
    }

    private function translation(string $key, array $replace = []): string
    {
        return (string) __('caring_community.disclosure_pack.' . $key, $replace);
    }

    private function loadStored(int $tenantId): array
    {
        if (!Schema::hasTable('tenant_settings')) {
            return [];
        }
        $row = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', self::SETTING_KEY)
            ->first();
        if (!$row || !$row->setting_value) {
            return [];
        }
        $decoded = json_decode((string) $row->setting_value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function latestUpdatedAt(int $tenantId): ?string
    {
        if (!Schema::hasTable('tenant_settings')) {
            return null;
        }
        $row = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', self::SETTING_KEY)
            ->first();
        return $row?->updated_at ? (string) $row->updated_at : null;
    }

    /**
     * Recursive deep-merge — overrides win, but never deletes default keys.
     */
    private function mergeDeep(array $base, array $overrides): array
    {
        foreach ($overrides as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k]) && !$this->isList($v) && !$this->isList($base[$k])) {
                $base[$k] = $this->mergeDeep($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }
        return $base;
    }

    private function isList(array $arr): bool
    {
        if ($arr === []) return true;
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}

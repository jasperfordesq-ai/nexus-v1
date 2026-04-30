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
                    'Microsoft Azure (hosting, EU)',
                    'Cloudflare (CDN / WAF)',
                    'Stripe (payments + identity verification)',
                    'OpenAI (matching & summarisation, optional)',
                    'Google Firebase Cloud Messaging (push notifications)',
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
                'description' => 'Canton-controlled deployment with own SMTP, storage, and backups. Federation can be disabled entirely.',
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
                'fadp_authority' => 'Eidgenössischer Datenschutz- und Öffentlichkeitsbeauftragter (EDÖB)',
            ],
            'cross_border_transfers' => [
                'occurs' => true,
                'destinations' => ['EU (Microsoft Azure)', 'US (Cloudflare, Stripe, OpenAI)'],
                'safeguards' => ['Standard Contractual Clauses (SCCs)', 'Swiss-US Data Privacy Framework where applicable'],
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
                $errors[] = ['field' => 'incident_response.contact_email', 'message' => 'must be a valid email'];
            }
            $win = $payload['incident_response']['notification_window_hours'] ?? null;
            if ($win !== null && (!is_numeric($win) || (int) $win < 1 || (int) $win > 720)) {
                $errors[] = ['field' => 'incident_response.notification_window_hours', 'message' => 'must be 1–720'];
            }
        }
        if (isset($payload['controller']['contact_email'])
            && $payload['controller']['contact_email'] !== ''
            && !filter_var($payload['controller']['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = ['field' => 'controller.contact_email', 'message' => 'must be a valid email'];
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
        $lines[] = '# Swiss FADP / nDSG Disclosure Pack';
        $lines[] = '_Pilot disclosure document — review with counsel before publishing._';
        $lines[] = '';
        $lines[] = '## 1. Controller';
        $lines[] = $this->kv($pack['controller']);
        $lines[] = '';
        $lines[] = '## 2. Processor';
        $lines[] = $this->kv(array_diff_key($pack['processor'], ['sub_processors' => true]));
        $lines[] = '';
        $lines[] = '### Sub-processors';
        foreach ($pack['processor']['sub_processors'] ?? [] as $sp) {
            $lines[] = '- ' . $sp;
        }
        $lines[] = '';
        $lines[] = '## 3. Data categories';
        foreach ($pack['data_categories'] as $cat => $fields) {
            $lines[] = sprintf('- **%s**: %s', $cat, implode(', ', (array) $fields));
        }
        $lines[] = '';
        $lines[] = '## 4. Lawful basis';
        $lines[] = $this->kv($pack['lawful_basis']);
        $lines[] = '';
        $lines[] = '## 5. Retention defaults';
        $lines[] = $this->kv($pack['retention_defaults']);
        $lines[] = '';
        $lines[] = '## 6. Data subject rights';
        $lines[] = $this->kv($pack['data_subject_rights']);
        $lines[] = '';
        $lines[] = '## 7. Federation policy';
        $lines[] = $this->kv($pack['federation']);
        $lines[] = '';
        $lines[] = '## 8. Isolated-node deployment option';
        $lines[] = $this->kv($pack['isolated_node']);
        $lines[] = '';
        $lines[] = '## 9. Incident response';
        $lines[] = $this->kv($pack['incident_response']);
        $lines[] = '';
        $lines[] = '## 10. Cross-border transfers';
        $lines[] = $this->kv(array_diff_key($pack['cross_border_transfers'], ['destinations' => true, 'safeguards' => true]));
        $lines[] = '### Destinations';
        foreach ($pack['cross_border_transfers']['destinations'] ?? [] as $d) {
            $lines[] = '- ' . $d;
        }
        $lines[] = '### Safeguards';
        foreach ($pack['cross_border_transfers']['safeguards'] ?? [] as $s) {
            $lines[] = '- ' . $s;
        }
        $lines[] = '';
        $lines[] = '## 11. Amendments';
        $lines[] = $this->kv($pack['amendments']);
        $lines[] = '';
        $lines[] = '_Generated ' . now()->toIso8601String() . ' from tenant ID ' . $tenantId . '. Review with FADP/nDSG counsel before publication._';

        return implode("\n", $lines);
    }

    private function kv(array $data): string
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            } elseif ($v === null || $v === '') {
                $v = '_(unset)_';
            }
            $out[] = sprintf('- **%s**: %s', $k, (string) $v);
        }
        return implode("\n", $out);
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

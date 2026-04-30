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
 * AG87 — External Integration Backlog.
 *
 * Tracks the partner-dependent external integrations the Caring Community
 * module may need (banking, payment, AHV submission, Spitex / professional
 * care, municipal master-data feeds, postal-address services, etc.). Each
 * item carries a named external owner, an interface specification, a data
 * sharing agreement (DSA) status, an optional sandbox URL, and a clear
 * lifecycle status — so the platform operator can avoid building features
 * that depend on integrations that are not yet ready.
 *
 * Stored as a single JSON envelope under the `caring.external_integrations`
 * setting key per tenant (different cantons may have different status for
 * the same integration, hence per-tenant scope).
 */
class ExternalIntegrationBacklogService
{
    public const SETTING_KEY = 'caring.external_integrations';

    public const STATUSES = ['proposed', 'scoping', 'blocked', 'sandbox', 'live', 'deprecated'];

    public const DSA_STATUSES = ['not_required', 'drafting', 'in_review', 'signed'];

    public const CATEGORIES = [
        'banking',
        'payment',
        'identity_verification',
        'professional_care',
        'municipal_data',
        'postal',
        'ahv',
        'healthcare',
        'other',
    ];

    /**
     * Return the stored backlog for the tenant.
     *
     * @return array{items: list<array<string, mixed>>, last_updated_at: ?string}
     */
    public function list(int $tenantId): array
    {
        $envelope = $this->loadEnvelope($tenantId);

        return [
            'items' => $envelope['items'] ?? [],
            'last_updated_at' => $envelope['updated_at'] ?? null,
        ];
    }

    /**
     * Seed a curated set of well-known partner-dependent integrations as
     * `proposed` items. Only succeeds when no items have been stored yet —
     * this guarantees the admin's manual curation is never overwritten.
     *
     * @return array{items?: list<array<string, mixed>>, last_updated_at?: ?string, error?: string}
     */
    public function seedDefaults(int $tenantId): array
    {
        $envelope = $this->loadEnvelope($tenantId);
        $items = $envelope['items'] ?? [];

        if (count($items) > 0) {
            return ['error' => 'already_seeded'];
        }

        $now = now()->toIso8601String();
        $seeds = [
            [
                'name' => 'AHV submission gateway',
                'category' => 'ahv',
                'notes' => 'Official channel for AHV-relevant volunteer-hour reports. Awaiting confirmation of canonical submission interface.',
            ],
            [
                'name' => 'Spitex care-coordination handoff',
                'category' => 'professional_care',
                'notes' => 'Bi-directional handoff with cantonal Spitex providers for care-recipient circles. Needs DSA + interface spec from each cantonal Spitex.',
            ],
            [
                'name' => 'Cantonal master-data feed',
                'category' => 'municipal_data',
                'notes' => 'Subscribed feed of address/household master data from cantonal registry to keep care-recipient profiles current.',
            ],
            [
                'name' => 'PostFinance payment integration',
                'category' => 'payment',
                'notes' => 'Swiss banking partner for cash-out / treasury operations. Requires merchant agreement.',
            ],
            [
                'name' => 'Twint payment',
                'category' => 'payment',
                'notes' => 'Twint acceptance for membership fees and donations. Requires Twint merchant onboarding via partner bank.',
            ],
            [
                'name' => 'Postal-address verification',
                'category' => 'postal',
                'notes' => 'Address normalisation and validation against Swiss Post directory.',
            ],
        ];

        $newItems = [];
        foreach ($seeds as $seed) {
            $newItems[] = $this->makeItem([
                'name' => $seed['name'],
                'category' => $seed['category'],
                'owner_name' => '',
                'owner_email' => '',
                'status' => 'proposed',
                'interface_spec_url' => '',
                'dsa_status' => 'not_required',
                'sandbox_url' => '',
                'notes' => $seed['notes'],
            ], $now);
        }

        $this->save($tenantId, $newItems);

        return [
            'items' => $newItems,
            'last_updated_at' => $now,
        ];
    }

    /**
     * Create a new backlog item.
     *
     * @param array<string, mixed> $payload
     * @return array{item?: array<string, mixed>, errors?: list<array{code: string, message: string, field: string}>}
     */
    public function create(int $tenantId, array $payload): array
    {
        $errors = $this->validate($payload, false);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $envelope = $this->loadEnvelope($tenantId);
        $items = $envelope['items'] ?? [];

        $now = now()->toIso8601String();
        $item = $this->makeItem($payload, $now);
        $items[] = $item;

        $this->save($tenantId, $items);

        return ['item' => $item];
    }

    /**
     * Update an existing backlog item by stable ID.
     *
     * @param array<string, mixed> $payload
     * @return array{item?: array<string, mixed>, error?: string, errors?: list<array{code: string, message: string, field: string}>}
     */
    public function update(int $tenantId, string $itemId, array $payload): array
    {
        $envelope = $this->loadEnvelope($tenantId);
        $items = $envelope['items'] ?? [];

        $idx = $this->findIndex($items, $itemId);
        if ($idx === null) {
            return ['error' => 'not_found'];
        }

        $errors = $this->validate($payload, true);
        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $existing = $items[$idx];
        $now = now()->toIso8601String();

        $merged = array_merge($existing, [
            'name' => isset($payload['name'])
                ? trim((string) $payload['name'])
                : ($existing['name'] ?? ''),
            'category' => isset($payload['category'])
                ? (string) $payload['category']
                : ($existing['category'] ?? 'other'),
            'owner_name' => isset($payload['owner_name'])
                ? trim((string) $payload['owner_name'])
                : ($existing['owner_name'] ?? ''),
            'owner_email' => isset($payload['owner_email'])
                ? trim((string) $payload['owner_email'])
                : ($existing['owner_email'] ?? ''),
            'status' => isset($payload['status'])
                ? (string) $payload['status']
                : ($existing['status'] ?? 'proposed'),
            'interface_spec_url' => isset($payload['interface_spec_url'])
                ? trim((string) $payload['interface_spec_url'])
                : ($existing['interface_spec_url'] ?? ''),
            'dsa_status' => isset($payload['dsa_status'])
                ? (string) $payload['dsa_status']
                : ($existing['dsa_status'] ?? 'not_required'),
            'sandbox_url' => isset($payload['sandbox_url'])
                ? trim((string) $payload['sandbox_url'])
                : ($existing['sandbox_url'] ?? ''),
            'notes' => isset($payload['notes'])
                ? (string) $payload['notes']
                : ($existing['notes'] ?? ''),
            'updated_at' => $now,
        ]);

        $items[$idx] = $merged;
        $this->save($tenantId, $items);

        return ['item' => $merged];
    }

    /**
     * Delete a backlog item by stable ID.
     *
     * @return array{ok?: true, error?: string}
     */
    public function delete(int $tenantId, string $itemId): array
    {
        $envelope = $this->loadEnvelope($tenantId);
        $items = $envelope['items'] ?? [];

        $idx = $this->findIndex($items, $itemId);
        if ($idx === null) {
            return ['error' => 'not_found'];
        }

        array_splice($items, $idx, 1);
        $this->save($tenantId, $items);

        return ['ok' => true];
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * @param array<string, mixed> $payload
     * @return list<array{code: string, message: string, field: string}>
     */
    private function validate(array $payload, bool $isPartial): array
    {
        $errors = [];

        // name
        if (!$isPartial || array_key_exists('name', $payload)) {
            $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
            if ($name === '') {
                $errors[] = ['code' => 'VALIDATION_REQUIRED', 'message' => 'Name is required.', 'field' => 'name'];
            } elseif (mb_strlen($name) > 200) {
                $errors[] = ['code' => 'VALIDATION_LENGTH', 'message' => 'Name must be 200 characters or fewer.', 'field' => 'name'];
            }
        }

        // category
        if (!$isPartial || array_key_exists('category', $payload)) {
            $category = isset($payload['category']) ? (string) $payload['category'] : '';
            if ($category === '' || !in_array($category, self::CATEGORIES, true)) {
                $errors[] = ['code' => 'VALIDATION_ENUM', 'message' => 'Category is invalid.', 'field' => 'category'];
            }
        }

        // status
        if (!$isPartial || array_key_exists('status', $payload)) {
            $status = isset($payload['status']) ? (string) $payload['status'] : '';
            if ($status === '' || !in_array($status, self::STATUSES, true)) {
                $errors[] = ['code' => 'VALIDATION_ENUM', 'message' => 'Status is invalid.', 'field' => 'status'];
            }
        }

        // dsa_status
        if (!$isPartial || array_key_exists('dsa_status', $payload)) {
            $dsa = isset($payload['dsa_status']) ? (string) $payload['dsa_status'] : '';
            if ($dsa === '' || !in_array($dsa, self::DSA_STATUSES, true)) {
                $errors[] = ['code' => 'VALIDATION_ENUM', 'message' => 'DSA status is invalid.', 'field' => 'dsa_status'];
            }
        }

        // owner_email — optional, but if present must be a valid email
        if (array_key_exists('owner_email', $payload)) {
            $email = trim((string) ($payload['owner_email'] ?? ''));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['code' => 'VALIDATION_EMAIL', 'message' => 'Owner email must be a valid email address.', 'field' => 'owner_email'];
            }
        }

        // interface_spec_url — optional, but if present must be a valid URL
        if (array_key_exists('interface_spec_url', $payload)) {
            $url = trim((string) ($payload['interface_spec_url'] ?? ''));
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = ['code' => 'VALIDATION_URL', 'message' => 'Interface spec URL must be a valid URL.', 'field' => 'interface_spec_url'];
            }
        }

        // sandbox_url — optional, but if present must be a valid URL
        if (array_key_exists('sandbox_url', $payload)) {
            $url = trim((string) ($payload['sandbox_url'] ?? ''));
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = ['code' => 'VALIDATION_URL', 'message' => 'Sandbox URL must be a valid URL.', 'field' => 'sandbox_url'];
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function makeItem(array $payload, string $now): array
    {
        return [
            'id' => $this->generateId(),
            'name' => trim((string) ($payload['name'] ?? '')),
            'category' => (string) ($payload['category'] ?? 'other'),
            'owner_name' => trim((string) ($payload['owner_name'] ?? '')),
            'owner_email' => trim((string) ($payload['owner_email'] ?? '')),
            'status' => (string) ($payload['status'] ?? 'proposed'),
            'interface_spec_url' => trim((string) ($payload['interface_spec_url'] ?? '')),
            'dsa_status' => (string) ($payload['dsa_status'] ?? 'not_required'),
            'sandbox_url' => trim((string) ($payload['sandbox_url'] ?? '')),
            'notes' => (string) ($payload['notes'] ?? ''),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function generateId(): string
    {
        return 'intg_' . substr(bin2hex(random_bytes(8)), 0, 16);
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function findIndex(array $items, string $itemId): ?int
    {
        foreach ($items as $i => $item) {
            if (($item['id'] ?? null) === $itemId) {
                return $i;
            }
        }
        return null;
    }

    /**
     * @return array{items: list<array<string, mixed>>, updated_at: ?string}
     */
    private function loadEnvelope(int $tenantId): array
    {
        if (!Schema::hasTable('tenant_settings')) {
            return ['items' => [], 'updated_at' => null];
        }

        $row = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', self::SETTING_KEY)
            ->first();

        if (!$row || !$row->setting_value) {
            return ['items' => [], 'updated_at' => null];
        }

        $decoded = json_decode((string) $row->setting_value, true);
        if (!is_array($decoded)) {
            return ['items' => [], 'updated_at' => null];
        }

        $items = [];
        if (isset($decoded['items']) && is_array($decoded['items'])) {
            foreach ($decoded['items'] as $item) {
                if (is_array($item) && isset($item['id'])) {
                    $items[] = $item;
                }
            }
        }

        $updatedAt = isset($decoded['updated_at']) && is_string($decoded['updated_at'])
            ? $decoded['updated_at']
            : (isset($row->updated_at) ? (string) $row->updated_at : null);

        return ['items' => $items, 'updated_at' => $updatedAt];
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function save(int $tenantId, array $items): void
    {
        if (!Schema::hasTable('tenant_settings')) {
            return;
        }

        $now = now();
        $envelope = [
            'items' => array_values($items),
            'updated_at' => $now->toIso8601String(),
        ];

        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'setting_key' => self::SETTING_KEY],
            [
                'setting_value' => json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'setting_type' => 'json',
                'category' => 'caring_community',
                'description' => 'AG87 external integration backlog',
                'updated_at' => $now,
            ],
        );
    }
}

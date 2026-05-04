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
 * AG85 — Isolated-Node Decision Gate.
 *
 * Persistence layer for the ownership decisions a Swiss canton (or any
 * data-sovereignty-conscious deployment) must make before launching as an
 * isolated, canton-controlled NEXUS node rather than a hosted shared tenant.
 *
 * Each item is stored as a discrete `caring.isolated_node.<item>` row in
 * `tenant_settings` with a JSON envelope holding {value, owner, status, notes}.
 * This allows the gate-status query to inspect each item independently and
 * preserves a per-item audit trail via `updated_at`.
 *
 * The gate is "closed" (passable) only when every item has status='decided'.
 */
class IsolatedNodeReadinessService
{
    public const KEY_PREFIX = 'caring.isolated_node.';

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DECIDED = 'decided';
    public const STATUS_BLOCKED = 'blocked';

    private const ALLOWED_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DECIDED,
        self::STATUS_BLOCKED,
    ];

    /**
     * Schema for every item the gate tracks. Drives validation and the admin UI.
     *
     * @return array<string, array{label: string, type: string, choices?: array<int, string>, help: string}>
     */
    public function schema(): array
    {
        return [
            'deployment_mode' => [
                'label' => 'Deployment mode',
                'type'  => 'enum',
                'choices' => ['hosted_tenant', 'hosted_custom_domain', 'canton_isolated_node'],
                'help'  => 'How this deployment is hosted: shared tenant, custom domain on the shared platform, or fully isolated canton-controlled node.',
            ],
            'hosting_owner' => [
                'label' => 'Hosting owner',
                'type'  => 'text',
                'help'  => 'Organisation that runs the infrastructure (server, domain, TLS) for the node.',
            ],
            'smtp_owner' => [
                'label' => 'SMTP / outbound email owner',
                'type'  => 'text',
                'help'  => 'Who operates outbound email delivery (e.g. own SMTP relay, Postmark account, Mailjet).',
            ],
            'storage_owner' => [
                'label' => 'Storage owner',
                'type'  => 'text',
                'help'  => 'Who owns and operates file uploads, attachments, and persistent object storage.',
            ],
            'backup_owner' => [
                'label' => 'Backup owner',
                'type'  => 'text',
                'help'  => 'Who runs daily backups, retention windows, and has restore-tested the database.',
            ],
            'update_cadence' => [
                'label' => 'Update cadence',
                'type'  => 'choice',
                'choices' => ['weekly', 'monthly', 'quarterly', 'on_demand'],
                'help'  => 'How often the node receives upstream NEXUS source updates.',
            ],
            'source_release_workflow' => [
                'label' => 'Source release workflow',
                'type'  => 'text',
                'help'  => 'How AGPL source updates flow into the isolated node (mirror repo, signed tags, manual review).',
            ],
            'telemetry_default' => [
                'label' => 'Telemetry default',
                'type'  => 'choice',
                'choices' => ['enabled', 'disabled'],
                'help'  => 'Default state for outbound telemetry / error reporting on this node.',
            ],
            'federation_key_exchange' => [
                'label' => 'Federation key exchange',
                'type'  => 'text',
                'help'  => 'Whether and how this node federates with other regional nodes (key custody, exchange protocol).',
            ],
            'dpo_appointed' => [
                'label' => 'Data-protection officer',
                'type'  => 'text',
                'help'  => 'Named DPO with contact details (FADP requirement at scale for canton-level deployments).',
            ],
            'incident_runbook_url' => [
                'label' => 'Incident runbook URL',
                'type'  => 'url',
                'help'  => 'Link to the operational runbook for incidents (downtime, breach, key compromise, restore drill).',
            ],
        ];
    }

    /**
     * Read the full decision-gate state for a tenant.
     *
     * @return array{items: list<array<string, mixed>>, gate: array<string, mixed>, last_updated_at: ?string}
     */
    public function get(int $tenantId): array
    {
        $stored = $this->loadStoredItems($tenantId);
        $items = [];

        foreach ($this->schema() as $key => $meta) {
            $envelope = $stored[$key] ?? [];
            $items[] = [
                'key'     => $key,
                'label'   => $meta['label'],
                'type'    => $meta['type'],
                'choices' => $meta['choices'] ?? null,
                'help'    => $meta['help'],
                'value'   => $envelope['value'] ?? null,
                'owner'   => $envelope['owner'] ?? null,
                'status'  => $envelope['status'] ?? self::STATUS_PENDING,
                'notes'   => $envelope['notes'] ?? null,
                'updated_at' => $envelope['updated_at'] ?? null,
            ];
        }

        return [
            'items' => $items,
            'gate'  => $this->gateStatus($tenantId),
            'last_updated_at' => $this->latestUpdatedAt($tenantId),
        ];
    }

    /**
     * Apply a partial update to a single item.
     *
     * Payload keys: value, owner, status, notes (each optional). Missing keys
     * leave the existing field untouched.
     *
     * @param array<string, mixed> $payload
     * @return array{item?: array<string, mixed>, gate?: array<string, mixed>, errors?: list<array{code: string, message: string, field?: string}>}
     */
    public function update(int $tenantId, string $itemKey, array $payload): array
    {
        $schema = $this->schema();
        if (!isset($schema[$itemKey])) {
            return [
                'errors' => [[
                    'code'    => 'INVALID_ITEM_KEY',
                    'message' => __('caring_community.readiness.unknown_item', ['item' => $itemKey]),
                    'field'   => 'item_key',
                ]],
            ];
        }

        if (!Schema::hasTable('tenant_settings')) {
            return [
                'errors' => [[
                    'code'    => 'STORAGE_UNAVAILABLE',
                    'message' => __('caring_community.readiness.storage_unavailable'),
                ]],
            ];
        }

        $meta = $schema[$itemKey];
        $existing = $this->loadStoredItems($tenantId)[$itemKey] ?? [];
        $errors = [];

        $next = [
            'value'  => $existing['value'] ?? null,
            'owner'  => $existing['owner'] ?? null,
            'status' => $existing['status'] ?? self::STATUS_PENDING,
            'notes'  => $existing['notes'] ?? null,
        ];

        if (array_key_exists('value', $payload)) {
            $validated = $this->validateValue($itemKey, $meta, $payload['value'], $errors);
            if ($errors === []) {
                $next['value'] = $validated;
            }
        }

        if (array_key_exists('owner', $payload)) {
            $owner = $payload['owner'];
            if ($owner !== null && !is_string($owner)) {
                $errors[] = [
                    'code'    => 'INVALID_OWNER',
                    'message' => __('caring_community.readiness.owner_invalid'),
                    'field'   => 'owner',
                ];
            } else {
                $owner = is_string($owner) ? trim($owner) : null;
                if ($owner !== null && strlen($owner) > 255) {
                    $errors[] = [
                        'code'    => 'OWNER_TOO_LONG',
                        'message' => __('caring_community.readiness.owner_too_long'),
                        'field'   => 'owner',
                    ];
                } else {
                    $next['owner'] = $owner === '' ? null : $owner;
                }
            }
        }

        if (array_key_exists('status', $payload)) {
            $status = $payload['status'];
            if (!is_string($status) || !in_array($status, self::ALLOWED_STATUSES, true)) {
                $errors[] = [
                    'code'    => 'INVALID_STATUS',
                    'message' => __('caring_community.readiness.status_invalid', ['statuses' => implode(', ', self::ALLOWED_STATUSES)]),
                    'field'   => 'status',
                ];
            } else {
                $next['status'] = $status;
            }
        }

        if (array_key_exists('notes', $payload)) {
            $notes = $payload['notes'];
            if ($notes !== null && !is_string($notes)) {
                $errors[] = [
                    'code'    => 'INVALID_NOTES',
                    'message' => __('caring_community.readiness.notes_invalid'),
                    'field'   => 'notes',
                ];
            } else {
                $notes = is_string($notes) ? trim($notes) : null;
                if ($notes !== null && strlen($notes) > 2000) {
                    $errors[] = [
                        'code'    => 'NOTES_TOO_LONG',
                        'message' => __('caring_community.readiness.notes_too_long'),
                        'field'   => 'notes',
                    ];
                } else {
                    $next['notes'] = $notes === '' ? null : $notes;
                }
            }
        }

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $now = now();
        $envelope = array_merge($next, ['updated_at' => (string) $now]);

        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'setting_key' => self::KEY_PREFIX . $itemKey],
            [
                'setting_value' => json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'setting_type'  => 'json',
                'category'      => 'caring_community',
                'description'   => 'AG85 isolated-node decision gate: ' . $itemKey,
                'updated_at'    => $now,
            ],
        );

        $fresh = $this->get($tenantId);
        $itemView = null;
        foreach ($fresh['items'] as $row) {
            if (($row['key'] ?? null) === $itemKey) {
                $itemView = $row;
                break;
            }
        }

        return [
            'item' => $itemView,
            'gate' => $fresh['gate'],
        ];
    }

    /**
     * Compute the current gate status for a tenant.
     *
     * @return array{closed: bool, decided_count: int, total_count: int, blockers: list<string>, status_counts: array<string, int>}
     */
    private function gateStatus(int $tenantId): array
    {
        $stored = $this->loadStoredItems($tenantId);
        $schema = $this->schema();

        $total = count($schema);
        $decided = 0;
        $blockers = [];
        $statusCounts = [
            self::STATUS_PENDING     => 0,
            self::STATUS_IN_PROGRESS => 0,
            self::STATUS_DECIDED     => 0,
            self::STATUS_BLOCKED     => 0,
        ];

        foreach ($schema as $key => $_meta) {
            $status = $stored[$key]['status'] ?? self::STATUS_PENDING;
            if (!isset($statusCounts[$status])) {
                $status = self::STATUS_PENDING;
            }
            $statusCounts[$status]++;

            if ($status === self::STATUS_DECIDED) {
                $decided++;
            } elseif ($status === self::STATUS_BLOCKED) {
                $blockers[] = $key;
            }
        }

        return [
            'closed'        => $decided === $total,
            'decided_count' => $decided,
            'total_count'   => $total,
            'blockers'      => $blockers,
            'status_counts' => $statusCounts,
        ];
    }

    /**
     * Load every persisted envelope for this tenant, keyed by item key.
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadStoredItems(int $tenantId): array
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
            $value = $row->setting_value;
            if (is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $decoded['updated_at'] = $row->updated_at ?? ($decoded['updated_at'] ?? null);
                    $out[$field] = $decoded;
                    continue;
                }
            }
            $out[$field] = [];
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

    /**
     * Validate the user-supplied value against the schema for the given item.
     *
     * @param array<string, mixed> $errors
     */
    private function validateValue(string $itemKey, array $meta, mixed $raw, array &$errors): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $type = $meta['type'];

        switch ($type) {
            case 'enum':
            case 'choice':
                if (!is_string($raw)) {
                    $errors[] = [
                        'code'    => 'INVALID_VALUE',
                        'message' => __('caring_community.readiness.value_string'),
                        'field'   => 'value',
                    ];
                    return null;
                }
                $val = trim($raw);
                $choices = $meta['choices'] ?? [];
                if (!in_array($val, $choices, true)) {
                    $errors[] = [
                        'code'    => 'INVALID_CHOICE',
                        'message' => __('caring_community.readiness.choice_invalid', ['choices' => implode(', ', $choices)]),
                        'field'   => 'value',
                    ];
                    return null;
                }
                return $val;

            case 'url':
                if (!is_string($raw)) {
                    $errors[] = [
                        'code'    => 'INVALID_VALUE',
                        'message' => __('caring_community.readiness.value_string'),
                        'field'   => 'value',
                    ];
                    return null;
                }
                $val = trim($raw);
                if (!filter_var($val, FILTER_VALIDATE_URL)) {
                    $errors[] = [
                        'code'    => 'INVALID_URL',
                        'message' => __('caring_community.readiness.url_invalid'),
                        'field'   => 'value',
                    ];
                    return null;
                }
                if (strlen($val) > 1000) {
                    $errors[] = [
                        'code'    => 'URL_TOO_LONG',
                        'message' => __('caring_community.readiness.url_too_long'),
                        'field'   => 'value',
                    ];
                    return null;
                }
                return $val;

            case 'text':
            default:
                if (!is_string($raw)) {
                    $errors[] = [
                        'code'    => 'INVALID_VALUE',
                        'message' => __('caring_community.readiness.value_string'),
                        'field'   => 'value',
                    ];
                    return null;
                }
                $val = trim($raw);
                if (strlen($val) > 1000) {
                    $errors[] = [
                        'code'    => 'VALUE_TOO_LONG',
                        'message' => __('caring_community.readiness.value_too_long'),
                        'field'   => 'value',
                    ];
                    return null;
                }
                return $val;
        }
    }
}

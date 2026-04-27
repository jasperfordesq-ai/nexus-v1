<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manages ongoing KISS-style support relationships between members.
 */
class CaringSupportRelationshipService
{
    private const FREQUENCIES = ['weekly', 'fortnightly', 'monthly', 'ad_hoc'];
    private const STATUSES = ['active', 'paused', 'completed', 'cancelled'];

    public function list(int $tenantId, array $filters = []): array
    {
        if (!Schema::hasTable('caring_support_relationships')) {
            return [
                'stats' => $this->emptyStats(),
                'items' => [],
            ];
        }

        $status = (string) ($filters['status'] ?? 'active');
        if (!in_array($status, self::STATUSES, true) && $status !== 'all') {
            $status = 'active';
        }

        $params = [$tenantId];
        $where = 'csr.tenant_id = ?';
        if ($status !== 'all') {
            $where .= ' AND csr.status = ?';
            $params[] = $status;
        }

        $rows = DB::select(
            "SELECT
                csr.*,
                supporter.name AS supporter_name,
                supporter.first_name AS supporter_first_name,
                supporter.last_name AS supporter_last_name,
                recipient.name AS recipient_name,
                recipient.first_name AS recipient_first_name,
                recipient.last_name AS recipient_last_name,
                coordinator.name AS coordinator_name,
                org.name AS organization_name,
                cat.name AS category_name
             FROM caring_support_relationships csr
             LEFT JOIN users supporter ON supporter.id = csr.supporter_id AND supporter.tenant_id = csr.tenant_id
             LEFT JOIN users recipient ON recipient.id = csr.recipient_id AND recipient.tenant_id = csr.tenant_id
             LEFT JOIN users coordinator ON coordinator.id = csr.coordinator_id AND coordinator.tenant_id = csr.tenant_id
             LEFT JOIN vol_organizations org ON org.id = csr.organization_id AND org.tenant_id = csr.tenant_id
             LEFT JOIN categories cat ON cat.id = csr.category_id AND cat.tenant_id = csr.tenant_id
             WHERE {$where}
             ORDER BY
                CASE csr.status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END,
                COALESCE(csr.next_check_in_at, csr.created_at) ASC,
                csr.id DESC
             LIMIT 100",
            $params
        );

        return [
            'stats' => $this->stats($tenantId),
            'items' => array_map(fn (object $row): array => $this->format($row), $rows),
        ];
    }

    public function create(int $tenantId, array $input, int $coordinatorId): array
    {
        if (!Schema::hasTable('caring_support_relationships')) {
            return ['success' => false, 'code' => 'SCHEMA_MISSING'];
        }

        $supporterId = (int) ($input['supporter_id'] ?? 0);
        $recipientId = (int) ($input['recipient_id'] ?? 0);
        if ($supporterId <= 0 || $recipientId <= 0 || $supporterId === $recipientId) {
            return ['success' => false, 'code' => 'VALIDATION_ERROR'];
        }

        if (!$this->tenantUserExists($tenantId, $supporterId) || !$this->tenantUserExists($tenantId, $recipientId)) {
            return ['success' => false, 'code' => 'USER_NOT_FOUND'];
        }

        $frequency = $this->normaliseFrequency($input['frequency'] ?? null);
        $startDate = $this->normaliseDate($input['start_date'] ?? null) ?? date('Y-m-d');
        $expectedHours = max(0.25, min(24.0, (float) ($input['expected_hours'] ?? 1)));
        $nextCheckIn = $this->nextCheckIn($startDate, $frequency);

        $id = (int) DB::table('caring_support_relationships')->insertGetId([
            'tenant_id' => $tenantId,
            'supporter_id' => $supporterId,
            'recipient_id' => $recipientId,
            'coordinator_id' => $coordinatorId,
            'organization_id' => $this->nullableTenantReference($tenantId, 'vol_organizations', $input['organization_id'] ?? null),
            'category_id' => $this->nullableTenantReference($tenantId, 'categories', $input['category_id'] ?? null),
            'title' => mb_substr(trim((string) ($input['title'] ?? __('api.caring_support_relationship_default_title'))), 0, 255),
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'frequency' => $frequency,
            'expected_hours' => $expectedHours,
            'start_date' => $startDate,
            'end_date' => $this->normaliseDate($input['end_date'] ?? null),
            'status' => 'active',
            'next_check_in_at' => $nextCheckIn,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'success' => true,
            'relationship' => $this->find($tenantId, $id),
        ];
    }

    public function update(int $tenantId, int $id, array $input): ?array
    {
        if (!Schema::hasTable('caring_support_relationships')) {
            return null;
        }

        $existing = DB::table('caring_support_relationships')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();
        if (!$existing) {
            return null;
        }

        $updates = ['updated_at' => now()];
        if (array_key_exists('status', $input)) {
            $status = (string) $input['status'];
            if (in_array($status, self::STATUSES, true)) {
                $updates['status'] = $status;
            }
        }
        if (array_key_exists('frequency', $input)) {
            $updates['frequency'] = $this->normaliseFrequency($input['frequency']);
        }
        if (array_key_exists('expected_hours', $input)) {
            $updates['expected_hours'] = max(0.25, min(24.0, (float) $input['expected_hours']));
        }
        if (array_key_exists('title', $input)) {
            $updates['title'] = mb_substr(trim((string) $input['title']), 0, 255);
        }
        if (array_key_exists('description', $input)) {
            $updates['description'] = trim((string) $input['description']) ?: null;
        }
        if (array_key_exists('next_check_in_at', $input)) {
            $updates['next_check_in_at'] = $this->normaliseDateTime($input['next_check_in_at']);
        }
        if (array_key_exists('last_logged_at', $input)) {
            $updates['last_logged_at'] = $this->normaliseDateTime($input['last_logged_at']);
        }

        DB::table('caring_support_relationships')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->update($updates);

        return $this->find($tenantId, $id);
    }

    public function find(int $tenantId, int $id): ?array
    {
        $row = DB::selectOne(
            "SELECT
                csr.*,
                supporter.name AS supporter_name,
                supporter.first_name AS supporter_first_name,
                supporter.last_name AS supporter_last_name,
                recipient.name AS recipient_name,
                recipient.first_name AS recipient_first_name,
                recipient.last_name AS recipient_last_name,
                coordinator.name AS coordinator_name,
                org.name AS organization_name,
                cat.name AS category_name
             FROM caring_support_relationships csr
             LEFT JOIN users supporter ON supporter.id = csr.supporter_id AND supporter.tenant_id = csr.tenant_id
             LEFT JOIN users recipient ON recipient.id = csr.recipient_id AND recipient.tenant_id = csr.tenant_id
             LEFT JOIN users coordinator ON coordinator.id = csr.coordinator_id AND coordinator.tenant_id = csr.tenant_id
             LEFT JOIN vol_organizations org ON org.id = csr.organization_id AND org.tenant_id = csr.tenant_id
             LEFT JOIN categories cat ON cat.id = csr.category_id AND cat.tenant_id = csr.tenant_id
             WHERE csr.tenant_id = ? AND csr.id = ?",
            [$tenantId, $id]
        );

        return $row ? $this->format($row) : null;
    }

    private function stats(int $tenantId): array
    {
        $row = DB::selectOne(
            "SELECT
                COUNT(CASE WHEN status = 'active' THEN 1 END) AS active_count,
                COUNT(CASE WHEN status = 'paused' THEN 1 END) AS paused_count,
                COUNT(CASE WHEN status = 'active' AND next_check_in_at IS NOT NULL AND next_check_in_at < NOW() THEN 1 END) AS check_ins_due,
                COALESCE(SUM(CASE WHEN status = 'active' THEN expected_hours ELSE 0 END), 0) AS expected_active_hours
             FROM caring_support_relationships
             WHERE tenant_id = ?",
            [$tenantId]
        );

        return [
            'active_count' => (int) ($row->active_count ?? 0),
            'paused_count' => (int) ($row->paused_count ?? 0),
            'check_ins_due' => (int) ($row->check_ins_due ?? 0),
            'expected_active_hours' => round((float) ($row->expected_active_hours ?? 0), 2),
        ];
    }

    private function emptyStats(): array
    {
        return [
            'active_count' => 0,
            'paused_count' => 0,
            'check_ins_due' => 0,
            'expected_active_hours' => 0.0,
        ];
    }

    private function format(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'supporter' => [
                'id' => (int) $row->supporter_id,
                'name' => $this->displayName($row, 'supporter'),
            ],
            'recipient' => [
                'id' => (int) $row->recipient_id,
                'name' => $this->displayName($row, 'recipient'),
            ],
            'coordinator' => $row->coordinator_id ? [
                'id' => (int) $row->coordinator_id,
                'name' => (string) ($row->coordinator_name ?? ''),
            ] : null,
            'organization_name' => (string) ($row->organization_name ?? ''),
            'category_name' => (string) ($row->category_name ?? ''),
            'title' => (string) $row->title,
            'description' => (string) ($row->description ?? ''),
            'frequency' => (string) $row->frequency,
            'expected_hours' => round((float) $row->expected_hours, 2),
            'start_date' => (string) $row->start_date,
            'end_date' => $row->end_date ? (string) $row->end_date : null,
            'status' => (string) $row->status,
            'last_logged_at' => $row->last_logged_at ? (string) $row->last_logged_at : null,
            'next_check_in_at' => $row->next_check_in_at ? (string) $row->next_check_in_at : null,
            'created_at' => (string) $row->created_at,
            'updated_at' => $row->updated_at ? (string) $row->updated_at : null,
        ];
    }

    private function tenantUserExists(int $tenantId, int $userId): bool
    {
        return DB::table('users')->where('tenant_id', $tenantId)->where('id', $userId)->exists();
    }

    private function nullableTenantReference(int $tenantId, string $table, mixed $id): ?int
    {
        $referenceId = (int) ($id ?? 0);
        if ($referenceId <= 0 || !Schema::hasTable($table)) {
            return null;
        }

        return DB::table($table)->where('tenant_id', $tenantId)->where('id', $referenceId)->exists()
            ? $referenceId
            : null;
    }

    private function normaliseFrequency(mixed $frequency): string
    {
        return in_array($frequency, self::FREQUENCIES, true) ? (string) $frequency : 'weekly';
    }

    private function normaliseDate(mixed $date): ?string
    {
        if (!is_string($date) || trim($date) === '') {
            return null;
        }

        $timestamp = strtotime($date);
        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function normaliseDateTime(mixed $date): ?string
    {
        if (!is_string($date) || trim($date) === '') {
            return null;
        }

        $timestamp = strtotime($date);
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function nextCheckIn(string $startDate, string $frequency): string
    {
        $modifier = match ($frequency) {
            'fortnightly' => '+14 days',
            'monthly' => '+1 month',
            'ad_hoc' => '+30 days',
            default => '+7 days',
        };

        return date('Y-m-d 09:00:00', strtotime($startDate . ' ' . $modifier));
    }

    private function displayName(object $row, string $prefix): string
    {
        $fullName = trim((string) ($row->{$prefix . '_first_name'} ?? '') . ' ' . (string) ($row->{$prefix . '_last_name'} ?? ''));
        return $fullName !== '' ? $fullName : (string) ($row->{$prefix . '_name'} ?? '');
    }
}

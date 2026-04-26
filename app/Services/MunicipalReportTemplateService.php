<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stores reusable municipal/KISS report templates per tenant.
 */
class MunicipalReportTemplateService
{
    private const AUDIENCES = ['municipality', 'canton', 'cooperative', 'foundation'];
    private const DATE_PRESETS = ['last_30_days', 'last_90_days', 'year_to_date', 'previous_quarter'];
    private const SECTIONS = ['summary', 'hours', 'members', 'organisations', 'categories', 'trends', 'trust'];

    public function list(int $tenantId): array
    {
        if (!Schema::hasTable('municipal_report_templates')) {
            return [];
        }

        $rows = DB::table('municipal_report_templates')
            ->where('tenant_id', $tenantId)
            ->orderBy('audience')
            ->orderBy('name')
            ->get();

        return $rows->map(fn (object $row): array => $this->format($row))->all();
    }

    public function create(int $tenantId, int $userId, array $input): array
    {
        $data = $this->normalise($input);

        $id = DB::table('municipal_report_templates')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'description' => $data['description'],
            'audience' => $data['audience'],
            'date_preset' => $data['date_preset'],
            'include_social_value' => $data['include_social_value'],
            'hour_value_chf' => $data['hour_value_chf'],
            'sections' => json_encode($data['sections']),
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->get($tenantId, (int) $id) ?? [];
    }

    public function get(int $tenantId, int $id): ?array
    {
        $row = DB::table('municipal_report_templates')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        return $row ? $this->format($row) : null;
    }

    public function update(int $tenantId, int $userId, int $id, array $input): ?array
    {
        $existing = DB::table('municipal_report_templates')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        if (!$existing) {
            return null;
        }

        $data = $this->normalise(array_merge((array) $existing, $input));

        DB::table('municipal_report_templates')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->update([
                'name' => $data['name'],
                'description' => $data['description'],
                'audience' => $data['audience'],
                'date_preset' => $data['date_preset'],
                'include_social_value' => $data['include_social_value'],
                'hour_value_chf' => $data['hour_value_chf'],
                'sections' => json_encode($data['sections']),
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

        return $this->get($tenantId, $id);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return DB::table('municipal_report_templates')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->delete() > 0;
    }

    private function normalise(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $audience = (string) ($input['audience'] ?? 'municipality');
        $datePreset = (string) ($input['date_preset'] ?? 'last_90_days');
        $sections = $input['sections'] ?? self::SECTIONS;

        if ($sections instanceof \JsonSerializable) {
            $sections = $sections->jsonSerialize();
        }

        if (is_string($sections)) {
            $decoded = json_decode($sections, true);
            $sections = is_array($decoded) ? $decoded : self::SECTIONS;
        }

        $sections = array_values(array_intersect(self::SECTIONS, array_map('strval', is_array($sections) ? $sections : [])));
        if ($sections === []) {
            $sections = self::SECTIONS;
        }

        return [
            'name' => mb_substr($name, 0, 160),
            'description' => $description === '' ? null : $description,
            'audience' => in_array($audience, self::AUDIENCES, true) ? $audience : 'municipality',
            'date_preset' => in_array($datePreset, self::DATE_PRESETS, true) ? $datePreset : 'last_90_days',
            'include_social_value' => filter_var($input['include_social_value'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'hour_value_chf' => isset($input['hour_value_chf']) && $input['hour_value_chf'] !== ''
                ? max(0, min(500, (int) $input['hour_value_chf']))
                : null,
            'sections' => $sections,
        ];
    }

    private function format(?object $row): array
    {
        if (!$row) {
            return [];
        }

        $sections = json_decode((string) ($row->sections ?? '[]'), true);

        return [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'description' => $row->description,
            'audience' => (string) $row->audience,
            'date_preset' => (string) $row->date_preset,
            'include_social_value' => (bool) $row->include_social_value,
            'hour_value_chf' => $row->hour_value_chf === null ? null : (int) $row->hour_value_chf,
            'sections' => is_array($sections) ? array_values($sections) : self::SECTIONS,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}

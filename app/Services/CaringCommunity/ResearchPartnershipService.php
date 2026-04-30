<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class ResearchPartnershipService
{
    private const TABLE_PARTNERS = 'caring_research_partners';
    private const TABLE_CONSENTS = 'caring_research_consents';
    private const TABLE_EXPORTS = 'caring_research_dataset_exports';

    public function isAvailable(): bool
    {
        return Schema::hasTable(self::TABLE_PARTNERS)
            && Schema::hasTable(self::TABLE_CONSENTS)
            && Schema::hasTable(self::TABLE_EXPORTS);
    }

    public function listPartners(int $tenantId): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        return DB::table(self::TABLE_PARTNERS)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => $this->partnerRow($row))
            ->all();
    }

    public function createPartner(int $tenantId, int $actorId, array $input): array
    {
        $this->assertAvailable();

        $name = trim((string) ($input['name'] ?? ''));
        $institution = trim((string) ($input['institution'] ?? ''));
        if ($name === '' || $institution === '') {
            throw new InvalidArgumentException(__('api.caring_research_partner_required'));
        }

        $status = (string) ($input['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'active', 'paused', 'ended'], true)) {
            $status = 'draft';
        }

        $now = now();
        $id = DB::table(self::TABLE_PARTNERS)->insertGetId([
            'tenant_id' => $tenantId,
            'name' => mb_substr($name, 0, 255),
            'institution' => mb_substr($institution, 0, 255),
            'contact_email' => isset($input['contact_email']) ? mb_substr((string) $input['contact_email'], 0, 255) : null,
            'agreement_reference' => isset($input['agreement_reference']) ? mb_substr((string) $input['agreement_reference'], 0, 255) : null,
            'methodology_url' => isset($input['methodology_url']) ? mb_substr((string) $input['methodology_url'], 0, 255) : null,
            'status' => $status,
            'data_scope' => json_encode($this->normaliseDataScope($input['data_scope'] ?? []), JSON_UNESCAPED_UNICODE),
            'starts_at' => $input['starts_at'] ?? null,
            'ends_at' => $input['ends_at'] ?? null,
            'created_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->partnerRow(DB::table(self::TABLE_PARTNERS)->where('id', $id)->first());
    }

    public function getConsent(int $tenantId, int $userId): array
    {
        $this->assertAvailable();

        $row = DB::table(self::TABLE_CONSENTS)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if (!$row) {
            return [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'consent_status' => 'opted_out',
                'consent_version' => 'research-v1',
                'consented_at' => null,
                'revoked_at' => null,
                'notes' => null,
            ];
        }

        return $this->consentRow($row);
    }

    public function recordConsent(int $tenantId, int $userId, string $status, ?string $notes = null): array
    {
        $this->assertAvailable();

        if (!in_array($status, ['opted_in', 'opted_out', 'revoked'], true)) {
            throw new InvalidArgumentException(__('api.caring_research_consent_status_invalid'));
        }

        $now = now();
        DB::table(self::TABLE_CONSENTS)->updateOrInsert(
            ['tenant_id' => $tenantId, 'user_id' => $userId],
            [
                'consent_status' => $status,
                'consent_version' => 'research-v1',
                'consented_at' => $status === 'opted_in' ? $now : null,
                'revoked_at' => $status === 'revoked' ? $now : null,
                'notes' => $notes,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        return $this->getConsent($tenantId, $userId);
    }

    public function generateDatasetExport(
        int $tenantId,
        int $partnerId,
        int $actorId,
        string $periodStart,
        string $periodEnd,
    ): array {
        $this->assertAvailable();

        $partner = DB::table(self::TABLE_PARTNERS)
            ->where('tenant_id', $tenantId)
            ->where('id', $partnerId)
            ->first();
        if (!$partner) {
            throw new RuntimeException(__('api.caring_research_partner_not_found'));
        }
        if ((string) $partner->status !== 'active') {
            throw new RuntimeException(__('api.caring_research_partner_inactive'));
        }

        $dataset = $this->aggregateDataset($tenantId, $periodStart, $periodEnd);
        $hash = hash('sha256', json_encode($dataset, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
        $now = now();

        $exportId = DB::table(self::TABLE_EXPORTS)->insertGetId([
            'tenant_id' => $tenantId,
            'partner_id' => $partnerId,
            'requested_by' => $actorId,
            'dataset_key' => 'caring_community_aggregate_v1',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'generated',
            'row_count' => count($dataset['rows']),
            'anonymization_version' => 'aggregate-v1',
            'data_hash' => $hash,
            'generated_at' => $now,
            'metadata' => json_encode([
                'partner_name' => (string) $partner->name,
                'methodology' => 'tenant-scoped aggregate metrics only; no direct identifiers, no row-level member records',
                'suppression_threshold' => 5,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'export' => $this->exportRow(DB::table(self::TABLE_EXPORTS)->where('id', $exportId)->first()),
            'dataset' => $dataset,
        ];
    }

    public function listDatasetExports(int $tenantId, ?int $partnerId = null): array
    {
        $this->assertAvailable();

        $query = DB::table(self::TABLE_EXPORTS . ' as exports')
            ->leftJoin(self::TABLE_PARTNERS . ' as partners', 'partners.id', '=', 'exports.partner_id')
            ->where('exports.tenant_id', $tenantId);

        if ($partnerId !== null) {
            $query->where('exports.partner_id', $partnerId);
        }

        return $query
            ->orderByDesc('exports.generated_at')
            ->select([
                'exports.*',
                'partners.name as partner_name',
                'partners.institution as partner_institution',
            ])
            ->get()
            ->map(fn ($row) => $this->exportRow($row))
            ->all();
    }

    public function revokeDatasetExport(int $tenantId, int $exportId, int $actorId): array
    {
        $this->assertAvailable();

        $row = DB::table(self::TABLE_EXPORTS)
            ->where('tenant_id', $tenantId)
            ->where('id', $exportId)
            ->first();

        if (!$row) {
            throw new RuntimeException(__('api.caring_research_export_not_found'));
        }

        $metadata = json_decode((string) ($row->metadata ?? '{}'), true) ?: [];
        $metadata['revoked_by'] = $actorId;
        $metadata['revoked_at'] = now()->toIso8601String();

        DB::table(self::TABLE_EXPORTS)
            ->where('tenant_id', $tenantId)
            ->where('id', $exportId)
            ->update([
                'status' => 'revoked',
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        return $this->exportRow(
            DB::table(self::TABLE_EXPORTS)
                ->where('tenant_id', $tenantId)
                ->where('id', $exportId)
                ->first()
        );
    }

    private function aggregateDataset(int $tenantId, string $periodStart, string $periodEnd): array
    {
        $rows = [];

        if (Schema::hasTable('vol_logs')) {
            foreach (DB::select(
                "SELECT DATE_FORMAT(date_logged, '%Y-%m') AS period,
                        COUNT(*) AS activity_count,
                        COUNT(DISTINCT user_id) AS participant_count,
                        COALESCE(SUM(hours), 0) AS approved_hours
                 FROM vol_logs
                 WHERE tenant_id = ? AND status = 'approved' AND date_logged BETWEEN ? AND ?
                 GROUP BY DATE_FORMAT(date_logged, '%Y-%m')
                 ORDER BY period",
                [$tenantId, $periodStart, $periodEnd]
            ) as $row) {
                $participants = (int) $row->participant_count;
                $rows[] = [
                    'period' => (string) $row->period,
                    'metric_family' => 'volunteering',
                    'activity_count' => $participants >= 5 ? (int) $row->activity_count : null,
                    'participant_count' => $participants >= 5 ? $participants : null,
                    'approved_hours' => $participants >= 5 ? round((float) $row->approved_hours, 2) : null,
                    'suppressed' => $participants < 5,
                ];
            }
        }

        return [
            'dataset_key' => 'caring_community_aggregate_v1',
            'period' => ['start' => $periodStart, 'end' => $periodEnd],
            'anonymization' => [
                'version' => 'aggregate-v1',
                'direct_identifiers' => false,
                'row_level_member_records' => false,
                'suppression_threshold' => 5,
            ],
            'rows' => $rows,
        ];
    }

    private function normaliseDataScope(mixed $scope): array
    {
        if (!is_array($scope)) {
            return ['datasets' => ['caring_community_aggregate_v1']];
        }

        $datasets = $scope['datasets'] ?? ['caring_community_aggregate_v1'];
        if (!is_array($datasets)) {
            $datasets = ['caring_community_aggregate_v1'];
        }

        return ['datasets' => array_values(array_map('strval', $datasets))];
    }

    private function assertAvailable(): void
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException(__('api.caring_research_unavailable'));
        }
    }

    private function partnerRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'tenant_id' => (int) $row->tenant_id,
            'name' => (string) $row->name,
            'institution' => (string) $row->institution,
            'contact_email' => $row->contact_email,
            'agreement_reference' => $row->agreement_reference,
            'methodology_url' => $row->methodology_url,
            'status' => (string) $row->status,
            'data_scope' => json_decode((string) ($row->data_scope ?? '{}'), true) ?: [],
            'starts_at' => $row->starts_at,
            'ends_at' => $row->ends_at,
            'created_by' => $row->created_by ? (int) $row->created_by : null,
            'created_at' => (string) $row->created_at,
            'updated_at' => (string) $row->updated_at,
        ];
    }

    private function consentRow(object $row): array
    {
        return [
            'tenant_id' => (int) $row->tenant_id,
            'user_id' => (int) $row->user_id,
            'consent_status' => (string) $row->consent_status,
            'consent_version' => (string) $row->consent_version,
            'consented_at' => $row->consented_at,
            'revoked_at' => $row->revoked_at,
            'notes' => $row->notes,
        ];
    }

    private function exportRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'tenant_id' => (int) $row->tenant_id,
            'partner_id' => (int) $row->partner_id,
            'requested_by' => $row->requested_by ? (int) $row->requested_by : null,
            'dataset_key' => (string) $row->dataset_key,
            'period_start' => (string) $row->period_start,
            'period_end' => (string) $row->period_end,
            'status' => (string) $row->status,
            'row_count' => (int) $row->row_count,
            'anonymization_version' => (string) $row->anonymization_version,
            'data_hash' => (string) $row->data_hash,
            'generated_at' => (string) $row->generated_at,
            'metadata' => json_decode((string) ($row->metadata ?? '{}'), true) ?: [],
            'partner_name' => $row->partner_name ?? null,
            'partner_institution' => $row->partner_institution ?? null,
        ];
    }
}

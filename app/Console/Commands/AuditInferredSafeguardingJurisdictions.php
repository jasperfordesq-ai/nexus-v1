<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\TenantContext;
use App\Services\SafeguardingPreferenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Find legacy country-derived presets that have no explicit jurisdiction.
 *
 * The command is report-only unless the operator supplies both the apply flag
 * and the exact acknowledgement. Corrective apply checks only whether a preset
 * option has an active preference so it can preserve that protection; it never
 * reads preference notes or any certificate/evidence data.
 */
class AuditInferredSafeguardingJurisdictions extends Command
{
    private const ACKNOWLEDGEMENT = 'DEACTIVATE_INFERRED_PRESETS';

    protected $signature = 'safeguarding:audit-inferred-jurisdictions
        {--tenant= : Limit the report/correction to one tenant ID}
        {--all-tenants : Explicitly select every candidate for corrective apply}
        {--apply : Return onboarding to custom, retire unselected preset options, and preserve live protections}
        {--acknowledge= : Required exact acknowledgement for --apply}';

    protected $description = 'Report legacy country-inferred safeguarding presets without an explicit jurisdiction';

    public function handle(): int
    {
        $rawTenant = trim((string) ($this->option('tenant') ?? ''));
        $allTenants = (bool) $this->option('all-tenants');
        if ($rawTenant !== '' && $allTenants) {
            $this->error('Use either --tenant or --all-tenants, never both.');
            return self::INVALID;
        }
        if ($rawTenant !== '' && (! ctype_digit($rawTenant) || (int) $rawTenant <= 0)) {
            $this->error('--tenant must be a positive integer.');
            return self::INVALID;
        }
        $tenantId = $rawTenant !== '' ? (int) $rawTenant : null;

        $rows = DB::table('tenants as t')
            ->join('tenant_settings as ts', function ($join): void {
                $join->on('ts.tenant_id', '=', 't.id')
                    ->where('ts.setting_key', '=', 'onboarding.country_preset');
            })
            ->leftJoin('tenant_safeguarding_settings as tss', 'tss.tenant_id', '=', 't.id')
            ->whereNull('tss.tenant_id')
            ->whereNotIn('ts.setting_value', ['', 'custom'])
            ->orderBy('t.id')
            ->when($tenantId !== null, static fn ($query) => $query->where('t.id', $tenantId))
            ->get([
                't.id',
                't.slug',
                't.country_code',
                'ts.setting_value as inferred_preset',
            ]);

        $this->table(
            ['Tenant ID', 'Slug', 'Country', 'Legacy preset'],
            $rows->map(static fn ($row): array => [
                (int) $row->id,
                (string) $row->slug,
                (string) ($row->country_code ?? ''),
                (string) $row->inferred_preset,
            ])->all(),
        );
        $this->info("Candidates: {$rows->count()}");

        if (! $this->option('apply')) {
            $this->comment('Dry run only. No tenant settings, options, or preferences were changed.');
            return self::SUCCESS;
        }

        if ($tenantId === null && ! $allTenants) {
            $this->error('Corrective apply requires --tenant=<id> or --all-tenants.');
            return self::INVALID;
        }

        if ((string) $this->option('acknowledge') !== self::ACKNOWLEDGEMENT) {
            $this->error('Refusing apply: use --acknowledge=' . self::ACKNOWLEDGEMENT);
            return self::FAILURE;
        }

        foreach ($rows as $row) {
            $tenantId = (int) $row->id;
            $result = TenantContext::runForTenant($tenantId, static function () use ($tenantId): array {
                return DB::transaction(static function () use ($tenantId): array {
                    DB::table('tenant_settings')
                        ->where('tenant_id', $tenantId)
                        ->where('setting_key', 'onboarding.country_preset')
                        ->update(['setting_value' => 'custom']);

                    return SafeguardingPreferenceService::preservePresetProtectionsForUnavailablePolicy($tenantId);
                });
            });
            $this->line(sprintf(
                'Tenant %d: set custom; deactivated %d unselected and preserved %d selected preset-owned option(s).',
                $tenantId,
                count($result['deactivated']),
                count($result['preserved']),
            ));
        }

        $this->warn('Completed corrective apply. Each tenant remains fail-closed until an administrator explicitly chooses its safeguarding jurisdiction.');
        return self::SUCCESS;
    }
}

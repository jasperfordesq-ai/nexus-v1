<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Quarantine the old listing-level DBS boolean until a lawful, purpose-scoped
 * role attestation workflow exists. The messenger attestation is never reused.
 */
class AuditLegacyListingVettingRequirements extends Command
{
    private const ACKNOWLEDGEMENT = 'CLEAR_UNSUPPORTED_ROLE_FLAGS';

    protected $signature = 'safeguarding:audit-listing-vetting-flags
        {--tenant= : Optional tenant ID}
        {--all-tenants : Explicitly select every tenant for corrective apply}
        {--apply : Clear unsupported dbs_required flags}
        {--acknowledge= : Required exact acknowledgement for --apply}';

    protected $description = 'Report legacy listing DBS flags that have no supported role-specific attestation workflow';

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

        $query = DB::table('listing_risk_tags as rt')
            ->join('listings as l', function ($join): void {
                $join->on('l.id', '=', 'rt.listing_id')->on('l.tenant_id', '=', 'rt.tenant_id');
            })
            ->where('rt.dbs_required', 1)
            ->orderBy('rt.tenant_id')
            ->orderBy('rt.listing_id');
        if ($tenantId !== null) {
            $query->where('rt.tenant_id', $tenantId);
        }
        $rows = $query->get(['rt.tenant_id', 'rt.listing_id', 'l.title']);

        $this->table(
            ['Tenant ID', 'Listing ID', 'Title'],
            $rows->map(static fn ($row): array => [
                (int) $row->tenant_id,
                (int) $row->listing_id,
                (string) $row->title,
            ])->all(),
        );
        $this->info("Unsupported listing role flags: {$rows->count()}");

        if (! $this->option('apply')) {
            $this->comment('Dry run only. Existing flags remain fail-closed and no rows were changed.');
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

        DB::transaction(static function () use ($rows): void {
            foreach ($rows as $row) {
                DB::table('listing_risk_tags')
                    ->where('tenant_id', (int) $row->tenant_id)
                    ->where('listing_id', (int) $row->listing_id)
                    ->where('dbs_required', 1)
                    ->update(['dbs_required' => 0, 'updated_at' => now()]);
            }
        });
        $this->warn('Unsupported listing role flags cleared. Reintroduce role checks only with a separate purpose-scoped broker workflow.');

        return self::SUCCESS;
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill safeguarding onboarding defaults for existing tenants.
 *
 * Two changes:
 *   1. Flip `onboarding.step_safeguarding_enabled` to '1' for every active
 *      tenant that currently has it unset or set to '0'. This makes the
 *      safeguarding step visible in the onboarding wizard so members can
 *      declare vulnerability / vetting needs from day one.
 *
 *   2. For every tenant with zero safeguarding options configured, apply the
 *      country preset that matches the tenant's `country_code` (IE → ireland,
 *      GB/UK → england_wales). Tenants that already have options configured
 *      are left untouched — admin customisations are preserved.
 *
 * Both steps are idempotent: re-running the migration is safe.
 *
 * Rationale: shipped alongside the change that flips the default for newly
 * created tenants. Without this backfill, existing tenants (including the
 * live `hour-timebank` tenant) would keep the old hardcoded '0' and members
 * would never see the safeguarding step.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tenants = DB::table('tenants')
            ->where('is_active', 1)
            ->select(['id', 'country_code'])
            ->get();

        foreach ($tenants as $tenant) {
            $this->enableSafeguardingStep((int) $tenant->id);
            $this->applyPresetIfEmpty((int) $tenant->id, (string) ($tenant->country_code ?? ''));
        }
    }

    public function down(): void
    {
        // No-op. Reverting would hide safeguarding from members across every
        // tenant, which is a safety regression we don't want to automate.
    }

    /**
     * Ensure the tenant's safeguarding step is enabled. Uses upsert semantics
     * so we overwrite an existing '0' but don't clobber an admin who has
     * deliberately enabled it ('1' stays '1'). The country_preset column is
     * seeded so the admin UI shows the matching preset selected.
     */
    private function enableSafeguardingStep(int $tenantId): void
    {
        $existing = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', 'onboarding.step_safeguarding_enabled')
            ->first();

        if ($existing === null) {
            DB::table('tenant_settings')->insert([
                'tenant_id' => $tenantId,
                'setting_key' => 'onboarding.step_safeguarding_enabled',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Show safeguarding step',
            ]);
        } elseif ($existing->setting_value !== '1') {
            DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', 'onboarding.step_safeguarding_enabled')
                ->update(['setting_value' => '1']);
        }
    }

    /**
     * Apply the country preset only when the tenant has no safeguarding
     * options yet. Preserves any existing admin configuration.
     */
    private function applyPresetIfEmpty(int $tenantId, string $countryCode): void
    {
        $hasOptions = DB::table('tenant_safeguarding_options')
            ->where('tenant_id', $tenantId)
            ->exists();

        if ($hasOptions) {
            return;
        }

        $presetKey = $this->mapCountryCodeToPreset(strtoupper($countryCode));

        // Record the preset choice even for 'custom' so the admin UI is honest
        // about what state the tenant is in.
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'setting_key' => 'onboarding.country_preset'],
            ['setting_value' => $presetKey, 'setting_type' => 'string', 'description' => 'Country preset']
        );

        if ($presetKey === 'custom') {
            return;
        }

        try {
            \App\Services\SafeguardingPreferenceService::applyCountryPreset($tenantId, $presetKey);
        } catch (\Throwable $e) {
            // Log but don't fail the migration — one bad tenant shouldn't
            // block the rest of the backfill.
            \Illuminate\Support\Facades\Log::warning(
                'backfill_safeguarding_onboarding_defaults: applyCountryPreset failed',
                [
                    'tenant_id' => $tenantId,
                    'preset' => $presetKey,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    private function mapCountryCodeToPreset(string $countryCode): string
    {
        return match ($countryCode) {
            'IE' => 'ireland',
            'GB', 'UK' => 'england_wales',
            default => 'custom',
        };
    }
};

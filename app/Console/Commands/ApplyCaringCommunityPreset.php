<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TenantFeatureConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Apply the "Caring Community / Timebank" feature preset to a tenant.
 *
 * This preset is tuned for evaluators in the Swiss caring-community space
 * (KISS / AGORIS AG / Sorgende Gemeinschaft / regional time-bank cooperatives).
 * It enables features that map directly onto a regional caring-community model
 * (volunteering, time-bank exchange, organisations, federation, resources,
 * reviews, gamification, polls, AI chat, translation) and disables features
 * that are not part of the core model (job vacancies, ideation challenges,
 * commercial Stripe marketplace, blog).
 *
 * Idempotent. Supports --dry-run. Targets a tenant by slug, e.g.
 *
 *   php artisan tenant:apply-caring-community-preset agoris
 *   php artisan tenant:apply-caring-community-preset agoris --dry-run
 *
 * The preset only touches the `features` JSON column on the tenants table.
 * It does NOT seed demo content, alter routing, or change theming. Re-running
 * is safe — it simply re-applies the canonical preset.
 *
 * The complete map of which features are enabled and disabled is the single
 * source of truth in this file (see PRESET) so reviewers can audit it in one
 * place without grepping the codebase.
 */
class ApplyCaringCommunityPreset extends Command
{
    protected $signature = 'tenant:apply-caring-community-preset
        {slug : The tenant slug to apply the preset to (e.g. "agoris")}
        {--dry-run : Show the diff without writing to the database}';

    protected $description = 'Apply the Caring Community / Timebank feature preset to the named tenant';

    /**
     * The canonical Caring-Community / Timebank preset.
     *
     * Keys must match TenantFeatureConfig::FEATURE_DEFAULTS. Any feature not
     * listed here keeps its current DB value (or default if unset).
     */
    private const PRESET = [
        // Core caring-community / timebank features — ON
        'volunteering'        => true,  // Volunteer offers + organisations
        'exchange_workflow'   => true,  // Time-credit exchanges (timebank core)
        'organisations'       => true,  // Associations, NGOs, future municipality entities
        'federation'          => true,  // National-foundation-with-cantonal-cooperatives shape
        'events'              => true,  // Community events, helping events
        'groups'              => true,  // Neighborhood / interest groups
        'group_exchanges'     => true,  // Group-level help exchange
        'connections'         => true,  // Member networking
        'direct_messaging'    => true,  // Peer-to-peer messaging
        'resources'           => true,  // Info points / regional resource directory
        'reviews'             => true,  // Trust signals on the marketplace of trust
        'polls'               => true,  // Community decision-making
        'gamification'        => true,  // XP / badges / engagement (Agoris "points" pillar)
        'goals'               => true,  // Personal & community goals
        'search'              => true,
        'ai_chat'             => true,  // AI assistant
        'message_translation' => true,  // Cross-language (DE/FR/IT/EN)

        // Not part of the caring-community core — OFF
        'job_vacancies'       => false, // Paid job board — not the model
        'ideation_challenges' => false, // Optional engagement layer; re-enable later if wanted
        'marketplace'         => false, // Commercial Stripe marketplace — not the model
        'blog'                => false, // Optional; re-enable if KISS wants editorial content
    ];

    public function handle(): int
    {
        $slug   = (string) $this->argument('slug');
        $dryRun = (bool) $this->option('dry-run');

        $tenant = DB::table('tenants')
            ->where('slug', $slug)
            ->first(['id', 'name', 'slug', 'features']);

        if (! $tenant) {
            $this->error("No tenant found with slug '{$slug}'.");
            return self::FAILURE;
        }

        $current = is_string($tenant->features) ? (json_decode($tenant->features, true) ?: []) : [];
        $merged  = TenantFeatureConfig::mergeFeatures($current);
        $next    = array_merge($merged, self::PRESET);

        $changes = [];
        foreach (self::PRESET as $key => $newValue) {
            $existing = $merged[$key] ?? null;
            if ($existing !== $newValue) {
                $changes[$key] = [
                    'from' => $existing === null ? '(default)' : ($existing ? 'true' : 'false'),
                    'to'   => $newValue ? 'true' : 'false',
                ];
            }
        }

        $this->info("Tenant: {$tenant->name} (id={$tenant->id}, slug={$tenant->slug})");
        $this->newLine();

        if (empty($changes)) {
            $this->info('Preset already applied — no changes needed.');
            return self::SUCCESS;
        }

        $this->info('Changes:');
        foreach ($changes as $key => $diff) {
            $this->line(sprintf('  %-22s %s -> %s', $key, $diff['from'], $diff['to']));
        }
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN — no changes written. Re-run without --dry-run to apply.');
            return self::SUCCESS;
        }

        DB::table('tenants')
            ->where('id', $tenant->id)
            ->update(['features' => json_encode($next)]);

        $this->info(sprintf('Applied %d change%s to tenant %s.', count($changes), count($changes) === 1 ? '' : 's', $slug));
        $this->line('Note: clear any tenant-feature cache if your environment caches feature flags.');

        return self::SUCCESS;
    }
}

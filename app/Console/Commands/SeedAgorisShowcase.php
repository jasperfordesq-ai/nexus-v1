<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Final sales-showcase pass for the Agoris/KISS Caring Community demo.
 *
 * This command deliberately focuses on what evaluator walkthroughs notice:
 * complete module surfaces, visible money/value flows, and persona journeys
 * that connect the many caring-community tables into one plain story.
 *
 * Idempotent: every write uses a stable identity tuple or updates known generic
 * seed rows. Safe to re-run after the earlier Agoris seed commands.
 *
 *   php artisan tenant:seed-agoris-showcase agoris
 *   php artisan tenant:seed-agoris-showcase agoris --dry-run
 */
class SeedAgorisShowcase extends Command
{
    protected $signature = 'tenant:seed-agoris-showcase
        {tenant_slug=agoris : Tenant slug to polish}
        {--expected-tenant-id=7 : Abort unless the tenant slug resolves to this id}
        {--dry-run : Show what would be seeded without writing}';

    protected $description = 'Final Agoris/KISS showcase pass: regional points, persona journeys, sales narrative, and generic-row polish';

    /** @var array<string, list<string>> */
    private array $columns = [];

    /** @var array<string, int> */
    private array $counts = [];

    public function handle(): int
    {
        $slug = ltrim((string) $this->argument('tenant_slug'), '/');
        $dryRun = (bool) $this->option('dry-run');

        $tenant = DB::table('tenants')->where('slug', $slug)->first(['id', 'name', 'slug']);
        if (! $tenant) {
            $this->error("No tenant found for slug '{$slug}'.");
            return self::FAILURE;
        }

        $tenantId = (int) $tenant->id;
        $expectedTenantId = (int) $this->option('expected-tenant-id');
        if ($expectedTenantId > 0 && $tenantId !== $expectedTenantId) {
            $this->error("Safety stop: slug '{$slug}' resolved to tenant id {$tenantId}, expected {$expectedTenantId}.");
            return self::FAILURE;
        }

        $this->info("Tenant: {$tenant->name} (id={$tenantId}, slug={$tenant->slug})");

        if ($dryRun) {
            $this->line('DRY RUN: would seed regional point accounts/transactions, golden-path settings, showcase resource notes, generic listing rewrites, and an image-generation brief.');
            return self::SUCCESS;
        }

        $userIds = $this->loadUserIds($tenantId);
        if (count($userIds) < 8) {
            $this->error('Found fewer than 8 Agoris users. Run tenant:seed-agoris-demo, realistic, polish, polish2, and polish3 first.');
            return self::FAILURE;
        }

        $this->seedRegionalPoints($tenantId, $userIds);
        $this->seedGoldenPaths($tenantId);
        $this->seedSalesNarrative($tenantId);
        $this->seedImageGenerationBrief($tenantId);
        $this->polishGenericListings($tenantId, $userIds);
        $this->seedShowcaseResources($tenantId, $userIds);

        $this->newLine();
        $this->info('Agoris showcase seed complete.');
        foreach ($this->counts as $label => $count) {
            $this->line(sprintf('  %-36s %d', $label, $count));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function loadUserIds(int $tenantId): array
    {
        $emailToKey = [
            'agoris.admin@example.test'      => 'admin',
            'andrea.muller@demo-agoris.ch'   => 'andrea',
            'hans.bachmann@demo-agoris.ch'   => 'hans',
            'sabine.keller@demo-agoris.ch'   => 'sabine',
            'roland.schmid@demo-agoris.ch'   => 'roland',
            'marlies.iten@demo-agoris.ch'    => 'marlies',
            'werner.hausmann@demo-agoris.ch' => 'werner',
            'theres.studer@demo-agoris.ch'   => 'theres',
            'markus.felder@demo-agoris.ch'   => 'markus',
            'anna.bucher@demo-agoris.ch'     => 'anna',
            'beat.zurcher@demo-agoris.ch'    => 'beat',
            'erika.wyss@demo-agoris.ch'      => 'erika',
            'stefan.birrer@demo-agoris.ch'   => 'stefan',
            'karin.luscher@demo-agoris.ch'   => 'karin',
            'thomas.risi@demo-agoris.ch'     => 'thomas',
            'christine.gut@demo-agoris.ch'   => 'christine',
        ];

        $rows = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->whereIn('email', array_keys($emailToKey))
            ->pluck('id', 'email');

        $byKey = [];
        foreach ($rows as $email => $id) {
            $byKey[$emailToKey[$email]] = (int) $id;
        }

        return $byKey;
    }

    /**
     * @param array<string, int> $userIds
     */
    private function seedRegionalPoints(int $tenantId, array $userIds): void
    {
        if (
            ! Schema::hasTable('caring_regional_point_accounts')
            || ! Schema::hasTable('caring_regional_point_transactions')
        ) {
            return;
        }

        $flows = [
            'andrea' => [
                ['type' => 'admin_issue', 'direction' => 'credit', 'points' => 40, 'days' => 84, 'desc' => 'Startguthaben fuer regelmaessige Besuchsdienste im Lorzenhof.'],
                ['type' => 'earned_for_hours', 'direction' => 'credit', 'points' => 18, 'days' => 42, 'desc' => 'Bonuspunkte fuer acht verifizierte Tandemstunden mit Werner.'],
                ['type' => 'redemption', 'direction' => 'debit', 'points' => 12, 'days' => 9, 'desc' => 'Eingeloest bei Baeckerei Iten fuer die Caring-Community-Box.'],
            ],
            'werner' => [
                ['type' => 'admin_issue', 'direction' => 'credit', 'points' => 30, 'days' => 80, 'desc' => 'Teilhabeguthaben fuer Seniorinnen und Senioren mit kleinem Budget.'],
                ['type' => 'redemption', 'direction' => 'debit', 'points' => 8, 'days' => 21, 'desc' => 'Fahrdienst-Zuschuss fuer Augenarzttermin in Zug.'],
                ['type' => 'transfer_in', 'direction' => 'credit', 'points' => 6, 'days' => 7, 'desc' => 'Nachbarschaftlicher Zuschuss aus dem Stundenfonds.'],
            ],
            'karin' => [
                ['type' => 'admin_issue', 'direction' => 'credit', 'points' => 25, 'days' => 76, 'desc' => 'Caregiver-Budget fuer Entlastungsangebote.'],
                ['type' => 'earned_for_hours', 'direction' => 'credit', 'points' => 10, 'days' => 33, 'desc' => 'Punkte fuer koordinierte Vertretung waehrend Pflegepause.'],
                ['type' => 'redemption', 'direction' => 'debit', 'points' => 14, 'days' => 12, 'desc' => 'Eingeloest fuer zwei Mittagessen-Gutscheine im Quartier.'],
            ],
            'marlies' => [
                ['type' => 'admin_issue', 'direction' => 'credit', 'points' => 60, 'days' => 90, 'desc' => 'Koordinationsbudget fuer KISS Cham.'],
                ['type' => 'admin_adjustment', 'direction' => 'credit', 'points' => 20, 'days' => 31, 'desc' => 'Gemeinde Cham verdoppelt Punkte fuer neue Tandems im April.'],
                ['type' => 'transfer_out', 'direction' => 'debit', 'points' => 18, 'days' => 6, 'desc' => 'Auszahlung an isolierte Mitglieder fuer Fruehlingsfest-Teilnahme.'],
            ],
            'beat' => [
                ['type' => 'earned_for_hours', 'direction' => 'credit', 'points' => 22, 'days' => 68, 'desc' => 'Punkte aus Hofladen-Partnerschaft und Lieferfahrten.'],
                ['type' => 'redemption', 'direction' => 'debit', 'points' => 7, 'days' => 18, 'desc' => 'Rueckerstattung auf Saisonbox fuer KISS-Mitglied.'],
            ],
            'thomas' => [
                ['type' => 'admin_issue', 'direction' => 'credit', 'points' => 50, 'days' => 61, 'desc' => 'Municipality pilot budget for low-friction participation incentives.'],
                ['type' => 'admin_adjustment', 'direction' => 'debit', 'points' => 5, 'days' => 17, 'desc' => 'Korrektur nach quartalsweiser Budgetabstimmung.'],
                ['type' => 'reversal', 'direction' => 'credit', 'points' => 5, 'days' => 16, 'desc' => 'Korrektur storniert, weil Teilnahmefoerderung genehmigt wurde.'],
            ],
            'sabine' => [
                ['type' => 'admin_issue', 'direction' => 'credit', 'points' => 20, 'days' => 50, 'desc' => 'Research participation thank-you points, no personal care data exposed.'],
                ['type' => 'redemption', 'direction' => 'debit', 'points' => 6, 'days' => 10, 'desc' => 'Eingeloest fuer KISS Treffen Kaffee- und Raumkosten.'],
            ],
            'erika' => [
                ['type' => 'admin_issue', 'direction' => 'credit', 'points' => 28, 'days' => 73, 'desc' => 'Teilhabeguthaben fuer neue Mitglieder nach Papier-Onboarding.'],
                ['type' => 'earned_for_hours', 'direction' => 'credit', 'points' => 8, 'days' => 29, 'desc' => 'Punkte fuer Mithilfe beim Smartphone-Cafe.'],
                ['type' => 'redemption', 'direction' => 'debit', 'points' => 9, 'days' => 3, 'desc' => 'Eingeloest fuer Reparatur-Stunde im Veloatelier.'],
            ],
        ];

        $actorId = $userIds['marlies'] ?? $userIds['admin'] ?? null;

        foreach ($flows as $person => $rows) {
            $userId = $userIds[$person] ?? null;
            if ($userId === null) {
                continue;
            }

            $accountId = $this->upsertAndGetId(
                'caring_regional_point_accounts',
                ['tenant_id' => $tenantId, 'user_id' => $userId],
                ['balance' => 0, 'lifetime_earned' => 0, 'lifetime_spent' => 0, 'updated_at' => now()]
            );

            $balance = 0.0;
            $earned = 0.0;
            $spent = 0.0;
            foreach ($rows as $idx => $row) {
                $points = (float) $row['points'];
                if ($row['direction'] === 'credit') {
                    $balance += $points;
                    $earned += $points;
                } else {
                    $balance -= $points;
                    $spent += $points;
                }

                $this->upsert(
                    'caring_regional_point_transactions',
                    [
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                        'reference_type' => 'agoris_showcase',
                        'reference_id' => ($userId * 100) + $idx,
                    ],
                    [
                        'account_id' => $accountId,
                        'actor_user_id' => $actorId,
                        'type' => $row['type'],
                        'direction' => $row['direction'],
                        'points' => $points,
                        'balance_after' => $balance,
                        'description' => $row['desc'],
                        'metadata' => json_encode([
                            'demo_path' => $person,
                            'showcase' => true,
                            'source' => 'tenant:seed-agoris-showcase',
                        ]),
                        'created_at' => now()->subDays((int) $row['days']),
                    ]
                );
                $this->bump('regional point transactions');
            }

            $this->upsert(
                'caring_regional_point_accounts',
                ['tenant_id' => $tenantId, 'user_id' => $userId],
                [
                    'balance' => $balance,
                    'lifetime_earned' => $earned,
                    'lifetime_spent' => $spent,
                    'updated_at' => now(),
                ]
            );
            $this->bump('regional point accounts');
        }
    }

    private function seedGoldenPaths(int $tenantId): void
    {
        $paths = [
            [
                'key' => 'senior_support_to_impact',
                'title' => 'Senior support to municipal impact',
                'persona' => 'Werner Hausmann',
                'steps' => [
                    'Karin asks for help on Werner behalf',
                    'Marlies creates a support relationship',
                    'Andrea completes visits and logs hours',
                    'Stefan reviews trust-sensitive hours',
                    'Thomas sees Q2 impact and ROI improve',
                ],
                'proof_points' => ['help_requests', 'support_relationships', 'vol_logs', 'reviews', 'municipal_roi'],
            ],
            [
                'key' => 'caregiver_cover',
                'title' => 'Family caregiver gets cover without phone chaos',
                'persona' => 'Karin Luescher',
                'steps' => [
                    'Cover request is opened for a regular caregiver slot',
                    'Smart nudge finds a trusted nearby helper',
                    'Coordinator confirms the handover',
                    'Regional points subsidise the practical cost',
                ],
                'proof_points' => ['caregiver_links', 'cover_requests', 'smart_nudges', 'regional_points'],
            ],
            [
                'key' => 'research_without_surveillance',
                'title' => 'Research partner sees aggregate evidence, not private lives',
                'persona' => 'Sabine Keller',
                'steps' => [
                    'Members consent to anonymised research',
                    'Dataset export is generated with k-anonymity notes',
                    'KISS can show learning without exposing vulnerable people',
                ],
                'proof_points' => ['research_consents', 'research_partners', 'dataset_exports'],
            ],
            [
                'key' => 'local_business_loyalty',
                'title' => 'Local seller turns mutual aid into local spend',
                'persona' => 'Beat Zuercher',
                'steps' => [
                    'Seller offers a caring-community reward',
                    'Member redeems points at pickup',
                    'Order and loyalty trail prove the local economy effect',
                ],
                'proof_points' => ['marketplace_listings', 'marketplace_orders', 'loyalty_redemptions', 'regional_points'],
            ],
        ];

        $this->upsertSetting($tenantId, 'caring.demo_golden_paths', [
            'updated_at' => now()->toIso8601String(),
            'score_target' => 950,
            'paths' => $paths,
        ]);
        $this->bump('golden path envelopes');
    }

    private function seedSalesNarrative(int $tenantId): void
    {
        $this->upsertSetting($tenantId, 'caring.sales_narrative', [
            'headline' => 'From WhatsApp chaos and spreadsheets to auditable local care infrastructure.',
            'buyer_promises' => [
                'KISS coordinators see who needs help, who can help, and what changed.',
                'Municipalities get impact evidence without asking volunteers for manual reports.',
                'Members feel a real reciprocal economy, not another abstract portal.',
                'Researchers get consented aggregate data with privacy guardrails.',
            ],
            'demo_score_after_showcase_seed' => 930,
            'remaining_gap_to_950' => 'Add curated generated images and run one evaluator walkthrough against the live UI.',
            'updated_at' => now()->toIso8601String(),
        ]);
        $this->bump('sales narrative envelopes');
    }

    private function seedImageGenerationBrief(int $tenantId): void
    {
        $brief = [
            'recommendation' => 'Use image generation sparingly: 8 persona portraits, 6 seller/product images, 4 event/resource covers.',
            'why' => 'This gives trust and demo polish without wasting credits on low-value post imagery.',
            'style' => 'Realistic Swiss community documentary photography, bright natural light, no corporate stock look, no text in image.',
            'asset_prompts' => [
                'Marlies Iten, warm Swiss KISS coordinator in a modest community office in Cham, documentary portrait.',
                'Andrea Mueller, active retired volunteer walking with an older neighbour near Lake Zug, respectful distance.',
                'Werner Hausmann, older Swiss resident at a kitchen table with tea and paper calendar, dignified documentary portrait.',
                'Karin Luescher, family caregiver reviewing a simple support schedule at home, calm natural light.',
                'Baeckerei Iten caring-community bread box with Swiss bread and gipfeli on a wooden counter.',
                'KISS Cham Treffen in a community room with coffee, notebooks, and a small circle of older adults.',
            ],
        ];

        $this->upsertSetting($tenantId, 'caring.showcase_image_generation_brief', $brief);
        $this->bump('image generation briefs');
    }

    /**
     * @param array<string, int> $userIds
     */
    private function polishGenericListings(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('listings')) {
            return;
        }

        $rows = [
            [
                'from' => 'I can help with Mentoring',
                'to' => 'Tandem-Start fuer neue KISS Helfer:innen',
                'description' => 'Marlies begleitet neue Freiwillige durch die ersten zwei Wochen: Profil, Vertrauensstufe, erste Stunde, und saubere Stundenerfassung.',
                'user' => 'marlies',
                'hours' => 1.5,
            ],
            [
                'from' => 'I can help with Community Service',
                'to' => 'Quartierdienst: Einkauf, Post, kurze Begleitung',
                'description' => 'Andrea uebernimmt kleine Wege in Cham fuer Mitglieder, die voruebergehend weniger mobil sind. Ideal fuer spontane Nachbarschaftshilfe.',
                'user' => 'andrea',
                'hours' => 1.0,
            ],
            [
                'from' => 'I can help with Community Stories',
                'to' => 'Lebensgeschichten aufnehmen fuer KISS Archiv',
                'description' => 'Sabine hilft Seniorinnen und Senioren, Erinnerungen aufzuschreiben oder als Audio aufzunehmen. Nur mit ausdruecklicher Einwilligung.',
                'user' => 'sabine',
                'hours' => 2.0,
            ],
            [
                'from' => 'Looking for help with Platform Updates',
                'to' => 'KISS App Sprechstunde fuer Koordinator:innen',
                'description' => 'Thomas sucht Unterstuetzung beim Sammeln von Rueckmeldungen zur KISS App: Was ist einfach, was braucht Papier-Alternative?',
                'user' => 'thomas',
                'hours' => 2.0,
            ],
            [
                'from' => 'Clean house',
                'to' => 'Wohnungs-Reset nach Spitalaufenthalt',
                'description' => 'Gesucht wird praktische Hilfe fuer leichte Ordnung, Einkaufsliste und Vorbereitung der ersten Woche zuhause. Keine medizinische Pflege.',
                'user' => 'karin',
                'hours' => 2.5,
            ],
        ];

        foreach ($rows as $row) {
            $query = DB::table('listings')
                ->where('tenant_id', $tenantId)
                ->whereIn('title', [$row['from'], $row['to']]);

            if (! $query->exists()) {
                continue;
            }

            $payload = $this->filterColumns('listings', [
                'title' => $row['to'],
                'description' => $row['description'],
                'user_id' => $userIds[$row['user']] ?? null,
                'price' => $row['hours'],
                'hours_estimate' => $row['hours'],
                'exchange_workflow_required' => 1,
                'service_type' => 'in-person',
                'availability' => json_encode(['kiss_showcase' => true, 'coordinator_verified' => true]),
                'updated_at' => now(),
            ]);

            DB::table('listings')
                ->where('tenant_id', $tenantId)
                ->whereIn('title', [$row['from'], $row['to']])
                ->update($payload);
            $this->bump('generic listings polished');
        }
    }

    /**
     * @param array<string, int> $userIds
     */
    private function seedShowcaseResources(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('resources') || ! $this->hasColumn('resources', 'title')) {
            return;
        }

        $ownerId = $userIds['marlies'] ?? $userIds['admin'] ?? null;
        $rows = [
            [
                'title' => 'Demo-Pfad: Von Hilferuf zu Wirkungsausweis',
                'body' => 'Interner Leitfaden fuer Verkaufsdemos: Karin erfasst eine Anfrage fuer Werner, Andrea hilft, Stefan prueft die Stunde, Thomas sieht den kommunalen Wirkungsausweis.',
                'category' => 'caring-community',
            ],
            [
                'title' => 'Regionalpunkte: Regeln fuer Cham Pilot 2026',
                'body' => 'Punkte sind eine kleine lokale Ermutigung, kein Ersatz fuer Stunden. Sie zeigen lokale Wirtschaft, Teilhabe und Gemeindebeitrag im selben Demo-Fluss.',
                'category' => 'regional-points',
            ],
            [
                'title' => 'KISS Evaluator Walkthrough: 12 Minuten',
                'body' => 'Ablauf fuer Interessenten: Dashboard, Hilferuf, Tandem, Stundenpruefung, Regionalpunkte, Forschungsexport, Gemeinde-ROI, mobile Papier-Alternative.',
                'category' => 'sales-demo',
            ],
        ];

        foreach ($rows as $row) {
            $this->upsert(
                'resources',
                ['tenant_id' => $tenantId, 'title' => $row['title']],
                [
                    'user_id' => $ownerId,
                    'category' => $row['category'],
                    'description' => $row['body'],
                    'content_body' => $row['body'],
                    'visibility' => 'members',
                    'status' => 'published',
                    'updated_at' => now(),
                ]
            );
            $this->bump('showcase resources');
        }
    }

    /**
     * @param array<string, mixed> $value
     */
    private function upsertSetting(int $tenantId, string $key, array $value): void
    {
        $this->upsert(
            'tenant_settings',
            ['tenant_id' => $tenantId, 'setting_key' => $key],
            [
                'setting_value' => json_encode($value),
                'setting_type' => 'json',
                'category' => 'caring',
                'updated_at' => now(),
            ]
        );
    }

    /**
     * @param array<string, mixed> $identity
     * @param array<string, mixed> $values
     */
    private function upsertAndGetId(string $table, array $identity, array $values): int
    {
        $this->upsert($table, $identity, $values);
        $row = DB::table($table);
        foreach ($identity as $key => $value) {
            $row->where($key, $value);
        }
        return (int) $row->value('id');
    }

    /**
     * @param array<string, mixed> $identity
     * @param array<string, mixed> $values
     */
    private function upsert(string $table, array $identity, array $values): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        $filteredIdentity = $this->filterColumns($table, $identity);
        if (empty($filteredIdentity)) {
            return;
        }
        $payload = $this->filterColumns($table, array_merge($identity, $values));
        if ($this->hasColumn($table, 'created_at') && ! array_key_exists('created_at', $payload)) {
            $payload['created_at'] = now();
        }
        if ($this->hasColumn($table, 'updated_at') && ! array_key_exists('updated_at', $payload)) {
            $payload['updated_at'] = now();
        }
        DB::table($table)->updateOrInsert($filteredIdentity, $payload);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function filterColumns(string $table, array $values): array
    {
        $columns = $this->columns[$table] ??= Schema::getColumnListing($table);
        return array_filter(
            $values,
            static fn (string $key): bool => in_array($key, $columns, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function hasColumn(string $table, string $column): bool
    {
        $columns = $this->columns[$table] ??= Schema::getColumnListing($table);
        return in_array($column, $columns, true);
    }

    private function bump(string $label): void
    {
        $this->counts[$label] = ($this->counts[$label] ?? 0) + 1;
    }
}

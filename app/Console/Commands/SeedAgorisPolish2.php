<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Second polish pass for the Agoris demo tenant — fills gaps a deep audit
 * surfaced after `tenant:seed-agoris-polish` ran.
 *
 * Targets the empty tables that would render blank pages during a deep
 * KISS-pilot evaluator walkthrough:
 *   - Marketplace product images          (24 listings × 1+ image)
 *   - caring_help_requests                (12 items, varied statuses)
 *   - caring_favours                      (10 items)
 *   - caring_invite_codes                 (8 codes — 5 unused + 3 redeemed)
 *   - caring_caregiver_links              (4 links inc. Werner ↔ Andrea)
 *   - caring_emergency_alerts             (2 historical examples)
 *   - caring_smart_nudges                 (10 dispatched + a few converted)
 *   - caring_care_providers               (5 Cham-area providers)
 *   - caring_sub_regions                  (5 sub-regions: Cham, Hünenberg, Steinhausen, Baar, Zug)
 *   - caring_project_announcements        (3 projects + 6 updates + subscriptions)
 *   - caring_cover_requests               (2 requests)
 *   - caring_hour_gifts                   (4 gifts)
 *   - caring_kpi_baselines                (1 baseline + future quarterly notes)
 *   - caring_trust_tier_config            (criteria JSON for tenant)
 *   - users.trust_tier                    (set tier 2-4 for veteran personas)
 *   - feed_likes + feed_comments          (engagement on existing 12 posts)
 *   - event_rsvps                         (attendees on existing 9 events)
 *   - messages                            (sample DM threads between members)
 *   - municipality ROI / pilot scoreboard tenant_settings envelopes
 *
 * Idempotent — every insert keys on a stable identity tuple. Safe to re-run.
 *
 *   php artisan tenant:seed-agoris-polish2 agoris
 *   php artisan tenant:seed-agoris-polish2 agoris --dry-run
 */
class SeedAgorisPolish2 extends Command
{
    protected $signature = 'tenant:seed-agoris-polish2
        {tenant_slug=agoris : Tenant slug to polish}
        {--dry-run : Show what would be seeded without writing}';

    protected $description = 'Second polish pass — fills empty caring-community tables exposed by the deep audit';

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
        $this->info("Tenant: {$tenant->name} (id={$tenantId}, slug={$tenant->slug})");

        if ($dryRun) {
            $this->line('DRY RUN: would seed marketplace images, help requests, favours, invite codes, caregiver links, alerts, nudges, care providers, sub-regions, projects, cover requests, hour gifts, KPI baselines, trust tiers, feed engagement, event RSVPs, DM threads, ROI envelope, scoreboard envelope.');
            return self::SUCCESS;
        }

        $userIds = $this->loadUserIds($tenantId);
        if (count($userIds) < 6) {
            $this->error('Found fewer than 6 Agoris users. Run the earlier seeders first.');
            return self::FAILURE;
        }

        $subRegionIds = $this->seedSubRegions($tenantId, $userIds);
        $this->seedTrustTierConfig($tenantId);
        $this->setUserTrustTiers($tenantId, $userIds);

        $this->seedMarketplaceImages($tenantId);
        $this->seedHelpRequests($tenantId, $userIds);
        $this->seedFavours($tenantId, $userIds);
        $this->seedInviteCodes($tenantId, $userIds);

        $caregiverLinkIds = $this->seedCaregiverLinks($tenantId, $userIds);
        $this->seedCoverRequests($tenantId, $userIds, $caregiverLinkIds);

        $this->seedHourGifts($tenantId, $userIds);
        $this->seedEmergencyAlerts($tenantId, $userIds);
        $this->seedSmartNudges($tenantId, $userIds);
        $this->seedCareProviders($tenantId, $userIds, $subRegionIds);
        $this->seedProjectAnnouncements($tenantId, $userIds);

        $this->seedFeedEngagement($tenantId, $userIds);
        $this->seedEventRsvps($tenantId, $userIds);
        $this->seedDirectMessages($tenantId, $userIds);

        $this->seedKpiBaseline($tenantId, $userIds);
        $this->seedScoreboardEnvelope($tenantId);
        $this->seedMunicipalRoiEnvelope($tenantId);

        $this->newLine();
        $this->info('Agoris polish2 seed complete.');
        foreach ($this->counts as $label => $count) {
            $this->line(sprintf('  %-36s %d', $label, $count));
        }

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------------
    // User lookup
    // -----------------------------------------------------------------------

    /**
     * @return array<string, int>
     */
    private function loadUserIds(int $tenantId): array
    {
        $emailToKey = [
            'agoris.admin@example.test'      => 'admin',
            'anna.baumann@example.test'      => 'anna_b',
            'luca.meier@example.test'        => 'luca',
            'maria.rossi@example.test'       => 'maria',
            'samira.keller@example.test'     => 'samira',
            'peter.huber@example.test'       => 'peter',
            'elena.widmer@example.test'      => 'elena',
            'thomas.schmid@example.test'     => 'thomas_s',
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
            if (isset($emailToKey[$email])) {
                $byKey[$emailToKey[$email]] = (int) $id;
            }
        }
        return $byKey;
    }

    // -----------------------------------------------------------------------
    // Sub-regions  (AG77)
    // -----------------------------------------------------------------------

    /**
     * @return array<string, int>
     */
    private function seedSubRegions(int $tenantId, array $userIds): array
    {
        if (! Schema::hasTable('caring_sub_regions')) {
            return [];
        }

        $admin = $userIds['admin'] ?? null;
        $rows = [
            ['name' => 'Cham',         'slug' => 'cham',         'type' => 'municipality', 'pcs' => ['6330'],         'lat' => 47.1758, 'lng' => 8.4622, 'desc' => 'Hauptort Cham, Sitz der KISS Genossenschaft Cham.'],
            ['name' => 'Hünenberg',    'slug' => 'huenenberg',   'type' => 'municipality', 'pcs' => ['6331','6333'], 'lat' => 47.1722, 'lng' => 8.4214, 'desc' => 'Hünenberg und Hünenberg See, ländliche Nachbargemeinde.'],
            ['name' => 'Steinhausen',  'slug' => 'steinhausen',  'type' => 'municipality', 'pcs' => ['6312'],         'lat' => 47.1947, 'lng' => 8.4858, 'desc' => 'Steinhausen mit dem Familientreff und Quartierschule.'],
            ['name' => 'Baar',         'slug' => 'baar',         'type' => 'municipality', 'pcs' => ['6340'],         'lat' => 47.1955, 'lng' => 8.5278, 'desc' => 'Baar, grösste Nachbargemeinde mit aktivem Quartiernetz.'],
            ['name' => 'Zug Stadt',    'slug' => 'zug-stadt',    'type' => 'municipality', 'pcs' => ['6300','6302'], 'lat' => 47.1662, 'lng' => 8.5155, 'desc' => 'Stadt Zug, Sitz von Spitex Zug und Pro Senectute Zug.'],
            ['name' => 'Lorzenhof',    'slug' => 'lorzenhof',    'type' => 'quartier',     'pcs' => ['6330'],         'lat' => 47.1748, 'lng' => 8.4609, 'desc' => 'Quartier Lorzenhof in Cham — Quartierverein und Frühlingsfest.'],
        ];

        $ids = [];
        foreach ($rows as $row) {
            $ids[$row['slug']] = $this->upsertAndGetId(
                'caring_sub_regions',
                ['tenant_id' => $tenantId, 'slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'description' => $row['desc'],
                    'postal_codes' => json_encode($row['pcs']),
                    'center_latitude' => $row['lat'],
                    'center_longitude' => $row['lng'],
                    'status' => 'active',
                    'created_by' => $admin,
                    'updated_at' => now(),
                ]
            );
            $this->bump('sub-regions');
        }
        return $ids;
    }

    // -----------------------------------------------------------------------
    // Trust tiers
    // -----------------------------------------------------------------------

    private function seedTrustTierConfig(int $tenantId): void
    {
        if (! Schema::hasTable('caring_trust_tier_config')) {
            return;
        }
        $criteria = [
            'tier_1_member'       => ['min_hours_logged' => 0,  'min_reviews_received' => 0, 'requires_identity_verified' => false],
            'tier_2_trusted'      => ['min_hours_logged' => 10, 'min_reviews_received' => 2, 'requires_identity_verified' => false],
            'tier_3_verified'     => ['min_hours_logged' => 30, 'min_reviews_received' => 4, 'requires_identity_verified' => true],
            'tier_4_coordinator'  => ['min_hours_logged' => 60, 'min_reviews_received' => 6, 'requires_identity_verified' => true, 'coordinator_role' => true],
        ];
        $this->upsert(
            'caring_trust_tier_config',
            ['tenant_id' => $tenantId],
            [
                'criteria' => json_encode($criteria),
                'updated_at' => now(),
            ]
        );
        $this->bump('trust tier config');
    }

    /**
     * @param array<string, int> $userIds
     */
    private function setUserTrustTiers(int $tenantId, array $userIds): void
    {
        if (! Schema::hasColumn('users', 'trust_tier')) {
            return;
        }
        $tiers = [
            'admin' => 4, 'marlies' => 4, 'thomas' => 4,                       // coordinators
            'stefan' => 3, 'andrea' => 3, 'beat' => 3, 'hans' => 3,             // verified
            'theres' => 2, 'sabine' => 2, 'anna' => 2, 'markus' => 2, 'karin' => 2, 'samira' => 2,
            'roland' => 2, 'thomas_s' => 2, 'maria' => 2,                       // trusted
            'werner' => 1, 'erika' => 1, 'elena' => 1, 'anna_b' => 1, 'peter' => 1, 'luca' => 1, 'christine' => 1,
        ];
        foreach ($tiers as $key => $tier) {
            $userId = $userIds[$key] ?? null;
            if ($userId === null) {
                continue;
            }
            DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->update(['trust_tier' => $tier, 'updated_at' => now()]);
            $this->bump('user trust tiers set');
        }
    }

    // -----------------------------------------------------------------------
    // Marketplace product images
    // -----------------------------------------------------------------------

    private function seedMarketplaceImages(int $tenantId): void
    {
        if (! Schema::hasTable('marketplace_images') || ! Schema::hasTable('marketplace_listings')) {
            return;
        }

        // For each listing, attach a deterministic placeholder image. Uses
        // picsum.photos seeded by listing id for a stable but varied image.
        $listings = DB::table('marketplace_listings')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->get(['id', 'title']);

        foreach ($listings as $listing) {
            $seed = (int) $listing->id;
            $url = "https://picsum.photos/seed/agoris-{$seed}/800/600";
            $thumb = "https://picsum.photos/seed/agoris-{$seed}/240/180";
            $this->upsert(
                'marketplace_images',
                [
                    'tenant_id' => $tenantId,
                    'marketplace_listing_id' => $listing->id,
                    'sort_order' => 0,
                ],
                [
                    'image_url' => $url,
                    'thumbnail_url' => $thumb,
                    'alt_text' => mb_substr((string) $listing->title, 0, 200),
                    'is_primary' => 1,
                    'created_at' => now(),
                ]
            );
            $this->bump('marketplace images');

            // Add a second image for variety on the first half of listings
            if ($seed % 2 === 0) {
                $url2 = "https://picsum.photos/seed/agoris-{$seed}-b/800/600";
                $thumb2 = "https://picsum.photos/seed/agoris-{$seed}-b/240/180";
                $this->upsert(
                    'marketplace_images',
                    [
                        'tenant_id' => $tenantId,
                        'marketplace_listing_id' => $listing->id,
                        'sort_order' => 1,
                    ],
                    [
                        'image_url' => $url2,
                        'thumbnail_url' => $thumb2,
                        'alt_text' => mb_substr((string) $listing->title, 0, 200) . ' (Detail)',
                        'is_primary' => 0,
                        'created_at' => now(),
                    ]
                );
                $this->bump('marketplace images');
            }
        }
    }

    // -----------------------------------------------------------------------
    // Caring help requests
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedHelpRequests(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('caring_help_requests')) {
            return;
        }
        $rows = [
            ['user' => 'werner',  'what' => 'Begleitung zum Augenarzt am Donnerstag Vormittag — Cham nach Zug, mit dem Bus.',                                'when' => 'Donnerstag, 10:30 Uhr', 'pref' => 'phone',   'status' => 'matched',  'days' => 4],
            ['user' => 'erika',   'what' => 'Suche jemanden, der mich am Donnerstag Vormittag zum wöchentlichen Einkauf bei Migros Baar begleitet.',          'when' => 'Donnerstag Vormittag', 'pref' => 'either',  'status' => 'pending',  'days' => 1],
            ['user' => 'theres',  'what' => 'Brauche Hilfe beim Schreiben eines Briefs an die Krankenkasse — eine Stunde sollte reichen.',                    'when' => 'Diese Woche, flexibel', 'pref' => 'message', 'status' => 'matched',  'days' => 2],
            ['user' => 'samira',  'what' => 'Kann jemand am Mittwoch von 16:00–18:00 meine zwei Kinder hüten? Notfall-Ersatz, normalerweise Oma.',           'when' => 'Mittwoch, 16:00–18:00', 'pref' => 'phone',   'status' => 'pending',  'days' => 0],
            ['user' => 'werner',  'what' => 'Suche Hilfe beim Bedienen meines neuen Fernsehers — Tasten verwirren mich.',                                     'when' => 'Wochenende', 'pref' => 'message', 'status' => 'closed',   'days' => 12],
            ['user' => 'erika',   'what' => 'Schwere Tasche vom Auto in den 3. Stock tragen — gerne Hilfe.',                                                  'when' => 'Montagmorgen', 'pref' => 'phone',   'status' => 'closed',   'days' => 14],
            ['user' => 'elena',   'what' => 'Suche jemanden, der mich beim Garten-Frühjahrsputz unterstützt — zwei Stunden, einfache Arbeit.',                'when' => 'Sonntag oder Samstag', 'pref' => 'either',  'status' => 'pending',  'days' => 2],
            ['user' => 'maria',   'what' => 'Brauche Übersetzungshilfe Italienisch ↔ Deutsch für ein Schreiben des Sozialamts.',                              'when' => 'Diese Woche', 'pref' => 'message', 'status' => 'matched',  'days' => 3],
            ['user' => 'theres',  'what' => 'Suche jemanden, der mit mir zur Frühlingsausstellung im Burgbach geht.',                                          'when' => 'Sonntag, 14:00', 'pref' => 'either',  'status' => 'closed',   'days' => 19],
            ['user' => 'werner',  'what' => 'Mein Smartphone bekommt keine Anrufe mehr — kann jemand kurz vorbeikommen und schauen?',                         'when' => 'Schnell — heute oder morgen', 'pref' => 'phone',   'status' => 'matched',  'days' => 1],
            ['user' => 'peter',   'what' => 'Brauche Hilfe beim Zusammenbau eines IKEA-Schranks — zwei Stunden mit Werkzeug.',                                'when' => 'Wochenende', 'pref' => 'message', 'status' => 'pending',  'days' => 0],
            ['user' => 'erika',   'what' => 'Suche Begleitung zur Bibliothek Cham — möchte einen Vortrag besuchen.',                                          'when' => 'Mittwochabend, 19:30', 'pref' => 'either',  'status' => 'pending',  'days' => 5],
        ];

        foreach ($rows as $row) {
            $userId = $userIds[$row['user']] ?? null;
            if ($userId === null) {
                continue;
            }
            $createdAt = now()->subDays($row['days'])->subHours(random_int(2, 16));
            $this->upsert(
                'caring_help_requests',
                ['tenant_id' => $tenantId, 'user_id' => $userId, 'what' => $row['what']],
                [
                    'when_needed' => $row['when'],
                    'contact_preference' => $row['pref'],
                    'status' => $row['status'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );
            $this->bump('help requests');
        }
    }

    // -----------------------------------------------------------------------
    // Caring favours
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedFavours(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('caring_favours')) {
            return;
        }
        $rows = [
            ['from' => 'andrea',   'to' => 'werner',  'cat' => 'Begleitung',  'desc' => 'Begleitung zum Augenarzt — Cham → Zug, mit dem Bus.',     'days' => 3,  'anon' => 0],
            ['from' => 'beat',     'to' => 'erika',   'cat' => 'Fahrdienst',  'desc' => 'Fahrt zum Termin in der Spitalklinik Baar.',              'days' => 5,  'anon' => 0],
            ['from' => 'sabine',   'to' => 'werner',  'cat' => 'Digitale Hilfe','desc' => 'Smartphone neu eingerichtet, alle wichtigen Kontakte importiert.', 'days' => 8,  'anon' => 0],
            ['from' => 'hans',     'to' => 'peter',   'cat' => 'Reparatur',   'desc' => 'IKEA-Schrank zusammengebaut, alle Schrauben kontrolliert.', 'days' => 11, 'anon' => 0],
            ['from' => 'theres',   'to' => 'werner',  'cat' => 'Besuch',      'desc' => 'Spaziergang an der Lorze und Kaffee im Restaurant Bahnhöfli.', 'days' => 6,  'anon' => 0],
            ['from' => 'markus',   'to' => 'theres',  'cat' => 'Garten',      'desc' => 'Frühlingsschnitt der Sträucher im Vorgarten.',               'days' => 9,  'anon' => 0],
            ['from' => 'anna',     'to' => 'maria',   'cat' => 'Mahlzeiten',  'desc' => 'Gemeinsames Mittagessen mit der Familie.',                  'days' => 13, 'anon' => 0],
            ['from' => 'samira',   'to' => 'theres',  'cat' => 'Einkauf',     'desc' => 'Wöchentlicher Grosseinkauf bei Coop und Volg.',             'days' => 4,  'anon' => 0],
            ['from' => 'karin',    'to' => 'werner',  'cat' => 'Kochen',      'desc' => 'Hat zwei Portionen warme Mahlzeit vorbereitet und vorbeigebracht.', 'days' => 2,  'anon' => 0],
            ['from' => 'roland',   'to' => null,      'cat' => 'Fahrdienst',  'desc' => 'Spontane Fahrt zum Bahnhof Zug für eine ältere Person — anonym registriert.', 'days' => 7,  'anon' => 1],
        ];

        foreach ($rows as $row) {
            $fromId = $userIds[$row['from']] ?? null;
            $toId = $row['to'] ? ($userIds[$row['to']] ?? null) : null;
            if ($fromId === null) {
                continue;
            }
            $favourDate = now()->subDays($row['days'])->toDateString();
            $this->upsert(
                'caring_favours',
                [
                    'tenant_id' => $tenantId,
                    'offered_by_user_id' => $fromId,
                    'favour_date' => $favourDate,
                    'description' => $row['desc'],
                ],
                [
                    'received_by_user_id' => $toId,
                    'category' => $row['cat'],
                    'is_anonymous' => $row['anon'],
                    'updated_at' => now(),
                ]
            );
            $this->bump('favours');
        }
    }

    // -----------------------------------------------------------------------
    // Caring invite codes
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedInviteCodes(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('caring_invite_codes')) {
            return;
        }

        $coordinator = $userIds['marlies'] ?? $userIds['admin'] ?? null;
        if ($coordinator === null) {
            return;
        }

        $rows = [
            ['code' => 'CHAM-A1', 'label' => 'Quartierfest Lorzenhof — Schnupperzugang',     'expires_in_days' => 30,  'used_days_ago' => null],
            ['code' => 'CHAM-B2', 'label' => 'Spitex Zug — Mitarbeitende',                   'expires_in_days' => 90,  'used_days_ago' => null],
            ['code' => 'CHAM-C3', 'label' => 'Bäckerei Schmid — Stammkunden',                'expires_in_days' => 60,  'used_days_ago' => null],
            ['code' => 'CHAM-D4', 'label' => 'Pro Senectute — Vermittlung',                  'expires_in_days' => 120, 'used_days_ago' => null],
            ['code' => 'CHAM-E5', 'label' => 'Pfarrei St. Jakob — Senioren-Treff',          'expires_in_days' => 45,  'used_days_ago' => null],
            ['code' => 'CHAM-F6', 'label' => 'Familientreff Steinhausen — Eltern',          'expires_in_days' => 60,  'used_days_ago' => 9,  'used_by' => 'samira'],
            ['code' => 'CHAM-G7', 'label' => 'Bibliothek Cham — Vortragsbesucher',          'expires_in_days' => 75,  'used_days_ago' => 14, 'used_by' => 'theres'],
            ['code' => 'CHAM-H8', 'label' => 'Männerturnverein Cham — Mitglieder',          'expires_in_days' => 30,  'used_days_ago' => 21, 'used_by' => 'hans'],
        ];

        foreach ($rows as $row) {
            $expiresAt = now()->addDays((int) $row['expires_in_days']);
            $values = [
                'label' => $row['label'],
                'created_by_user_id' => $coordinator,
                'expires_at' => $expiresAt,
                'updated_at' => now(),
            ];
            if (! empty($row['used_days_ago'])) {
                $usedKey = $row['used_by'] ?? null;
                $usedById = $usedKey ? ($userIds[$usedKey] ?? null) : null;
                $values['used_at'] = now()->subDays((int) $row['used_days_ago']);
                $values['used_by_user_id'] = $usedById;
            }
            $this->upsert(
                'caring_invite_codes',
                ['tenant_id' => $tenantId, 'code' => $row['code']],
                $values
            );
            $this->bump('invite codes');
        }
    }

    // -----------------------------------------------------------------------
    // Caregiver links + cover requests
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     * @return array<string, int>  link key → caregiver_link_id
     */
    private function seedCaregiverLinks(int $tenantId, array $userIds): array
    {
        if (! Schema::hasTable('caring_caregiver_links')) {
            return [];
        }

        $rows = [
            ['key' => 'andrea_werner', 'caregiver' => 'andrea', 'cared_for' => 'werner', 'rel' => 'neighbour',    'primary' => 1, 'days_ago' => 56,  'notes' => 'Wöchentliche Besuche, Einkaufshilfe, Begleitung zu Terminen.'],
            ['key' => 'beat_erika',    'caregiver' => 'beat',   'cared_for' => 'erika',  'rel' => 'neighbour',    'primary' => 1, 'days_ago' => 84,  'notes' => 'Fahrdienst zu Arztterminen und Einkauf in Baar.'],
            ['key' => 'samira_anna_b', 'caregiver' => 'samira', 'cared_for' => 'anna_b', 'rel' => 'family',       'primary' => 1, 'days_ago' => 120, 'notes' => 'Familienunterstützung — Einkäufe und Begleitung.'],
            ['key' => 'sabine_werner', 'caregiver' => 'sabine', 'cared_for' => 'werner', 'rel' => 'neighbour',    'primary' => 0, 'days_ago' => 14,  'notes' => 'Sekundäre Bezugsperson für IT-Hilfe und Backup.'],
        ];

        $coordinator = $userIds['marlies'] ?? null;
        $ids = [];
        foreach ($rows as $row) {
            $caregiverId = $userIds[$row['caregiver']] ?? null;
            $caredForId = $userIds[$row['cared_for']] ?? null;
            if ($caregiverId === null || $caredForId === null) {
                continue;
            }
            $ids[$row['key']] = $this->upsertAndGetId(
                'caring_caregiver_links',
                [
                    'tenant_id' => $tenantId,
                    'caregiver_id' => $caregiverId,
                    'cared_for_id' => $caredForId,
                ],
                [
                    'relationship_type' => $row['rel'],
                    'is_primary' => $row['primary'],
                    'start_date' => now()->subDays($row['days_ago'])->toDateString(),
                    'notes' => $row['notes'],
                    'status' => 'active',
                    'approved_by' => $coordinator,
                    'updated_at' => now(),
                ]
            );
            $this->bump('caregiver links');
        }
        return $ids;
    }

    /**
     * @param array<string, int> $userIds
     * @param array<string, int> $linkIds
     */
    private function seedCoverRequests(int $tenantId, array $userIds, array $linkIds): void
    {
        if (! Schema::hasTable('caring_cover_requests') || $linkIds === []) {
            return;
        }
        $rows = [
            [
                'link' => 'andrea_werner', 'caregiver' => 'andrea', 'cared_for' => 'werner',
                'title' => 'Andrea verreist — Vertretung für Werner gesucht',
                'briefing' => 'Andrea ist vom 12. bis 19. Mai in den Ferien. Werner braucht zweimal Besuch + Einkauf. Schlüssel beim Hausmeister.',
                'starts_in_days' => 12, 'duration_days' => 7, 'hours' => 4.0,
                'min_tier' => 2, 'urgency' => 'planned', 'status' => 'matched',
                'matched_by' => 'theres',
                'skills' => ['Begleitung', 'Einkaufen'],
            ],
            [
                'link' => 'beat_erika',    'caregiver' => 'beat',   'cared_for' => 'erika',
                'title' => 'Beat krank — Fahrdienst-Vertretung Donnerstag',
                'briefing' => 'Beat ist krank, kann Erika nicht zum Termin nach Baar fahren. Treffpunkt 9:00 vor ihrer Wohnung.',
                'starts_in_days' => 1, 'duration_days' => 0, 'hours' => 1.5,
                'min_tier' => 2, 'urgency' => 'urgent', 'status' => 'open',
                'matched_by' => null,
                'skills' => ['Fahrdienst'],
            ],
        ];
        foreach ($rows as $row) {
            $linkId = $linkIds[$row['link']] ?? null;
            $caregiverId = $userIds[$row['caregiver']] ?? null;
            $caredForId = $userIds[$row['cared_for']] ?? null;
            $matched = $row['matched_by'] ? ($userIds[$row['matched_by']] ?? null) : null;
            if ($linkId === null || $caregiverId === null || $caredForId === null) {
                continue;
            }
            $startsAt = now()->addDays($row['starts_in_days'])->setTime(9, 0);
            $endsAt = (clone $startsAt)->addDays(max(0, $row['duration_days']))->addHours((int) $row['hours']);
            $this->upsert(
                'caring_cover_requests',
                ['tenant_id' => $tenantId, 'caregiver_link_id' => $linkId, 'title' => $row['title']],
                [
                    'caregiver_id' => $caregiverId,
                    'cared_for_id' => $caredForId,
                    'matched_supporter_id' => $matched,
                    'briefing' => $row['briefing'],
                    'required_skills' => json_encode($row['skills']),
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'expected_hours' => $row['hours'],
                    'minimum_trust_tier' => $row['min_tier'],
                    'urgency' => $row['urgency'],
                    'status' => $row['status'],
                    'matched_at' => $matched ? now()->subDays(2) : null,
                    'updated_at' => now(),
                ]
            );
            $this->bump('cover requests');
        }
    }

    // -----------------------------------------------------------------------
    // Hour gifts
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedHourGifts(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('caring_hour_gifts')) {
            return;
        }
        $rows = [
            ['from' => 'marlies', 'to' => 'werner', 'hours' => 2.0, 'msg' => 'Willkommens-Geschenk von der KISS Cham — viel Freude in der Caring Community.', 'status' => 'accepted', 'days' => 30],
            ['from' => 'andrea',  'to' => 'theres', 'hours' => 1.5, 'msg' => 'Liebe Theres, danke für die schöne Lasagne. Hier ein paar Stunden zurück.',       'status' => 'accepted', 'days' => 14],
            ['from' => 'thomas',  'to' => 'samira', 'hours' => 3.0, 'msg' => 'Im Namen der Gemeindekanzlei für deinen Einsatz beim Quartierfest.',              'status' => 'pending',  'days' => 2],
            ['from' => 'beat',    'to' => 'hans',   'hours' => 1.0, 'msg' => 'Velo-Reparatur war Spitze — kleine Anerkennung.',                                  'status' => 'accepted', 'days' => 7],
        ];
        foreach ($rows as $row) {
            $fromId = $userIds[$row['from']] ?? null;
            $toId = $userIds[$row['to']] ?? null;
            if ($fromId === null || $toId === null || $fromId === $toId) {
                continue;
            }
            $createdAt = now()->subDays($row['days']);
            $this->upsert(
                'caring_hour_gifts',
                [
                    'tenant_id' => $tenantId,
                    'sender_user_id' => $fromId,
                    'recipient_user_id' => $toId,
                    'message' => $row['msg'],
                ],
                [
                    'hours' => $row['hours'],
                    'status' => $row['status'],
                    'accepted_at' => $row['status'] === 'accepted' ? $createdAt->copy()->addHours(6) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );
            $this->bump('hour gifts');
        }
    }

    // -----------------------------------------------------------------------
    // Emergency alerts (AG70)
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedEmergencyAlerts(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('caring_emergency_alerts')) {
            return;
        }
        $admin = $userIds['thomas'] ?? $userIds['admin'] ?? null;
        $rows = [
            [
                'title' => 'Kurzer Stromunterbruch Lorzenhof',
                'body'  => 'Geplanter Stromunterbruch im Quartier Lorzenhof am Mittwoch, 14. Mai, 9:00–11:00 Uhr. KISS-Mitglieder mit Pflegegeräten bitte vorab bei Marlies melden.',
                'severity' => 'info',
                'sent_days_ago' => 3,
                'expires_days' => -1, // already expired
                'is_active' => 0,
                'push_sent' => 1,
                'scope' => ['type' => 'radius', 'lat' => 47.1748, 'lng' => 8.4609, 'radius_km' => 1.5],
            ],
            [
                'title' => 'Sturmwarnung Region Zug',
                'body'  => 'MeteoSchweiz warnt vor starkem Sturm am Sonntagabend. Bitte lose Gegenstände sichern und nach älteren Nachbarn schauen. Hilfe-Anfragen via App oder 079 555 33 22.',
                'severity' => 'warning',
                'sent_days_ago' => 17,
                'expires_days' => -10, // already expired
                'is_active' => 0,
                'push_sent' => 1,
                'scope' => null, // whole tenant
            ],
        ];
        foreach ($rows as $row) {
            $sentAt = now()->subDays($row['sent_days_ago']);
            $expiresAt = now()->addDays($row['expires_days']);
            $this->upsert(
                'caring_emergency_alerts',
                ['tenant_id' => $tenantId, 'title' => $row['title']],
                [
                    'body' => $row['body'],
                    'severity' => $row['severity'],
                    'geographic_scope' => $row['scope'] ? json_encode($row['scope']) : null,
                    'target_user_ids' => null,
                    'sent_at' => $sentAt,
                    'expires_at' => $expiresAt,
                    'is_active' => $row['is_active'],
                    'created_by' => $admin,
                    'dismissed_count' => random_int(8, 21),
                    'push_sent' => $row['push_sent'],
                    'push_result' => json_encode(['delivered' => random_int(15, 23), 'failed' => 0]),
                    'updated_at' => now(),
                ]
            );
            $this->bump('emergency alerts');
        }
    }

    // -----------------------------------------------------------------------
    // Smart nudges (AG31)
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedSmartNudges(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('caring_smart_nudges')) {
            return;
        }
        $rows = [
            ['target' => 'werner',   'related' => 'theres',  'source' => 'tandem_candidate', 'score' => 0.82, 'status' => 'sent',      'sent_days_ago' => 5,  'converted_days_ago' => null, 'signals' => ['shared_neighbourhood' => true, 'category_overlap' => ['Begleitung'], 'distance_km' => 0.4]],
            ['target' => 'erika',    'related' => 'roland',  'source' => 'tandem_candidate', 'score' => 0.78, 'status' => 'converted', 'sent_days_ago' => 14, 'converted_days_ago' => 11, 'signals' => ['shared_neighbourhood' => false, 'category_overlap' => ['Fahrdienst'], 'distance_km' => 6.1]],
            ['target' => 'andrea',   'related' => null,      'source' => 'log_reminder',     'score' => 0.55, 'status' => 'sent',      'sent_days_ago' => 2,  'converted_days_ago' => null, 'signals' => ['days_since_last_log' => 7, 'expected_frequency' => 'weekly']],
            ['target' => 'beat',     'related' => null,      'source' => 'log_reminder',     'score' => 0.48, 'status' => 'sent',      'sent_days_ago' => 4,  'converted_days_ago' => null, 'signals' => ['days_since_last_log' => 6, 'expected_frequency' => 'fortnightly']],
            ['target' => 'samira',   'related' => 'sabine',  'source' => 'tandem_candidate', 'score' => 0.71, 'status' => 'sent',      'sent_days_ago' => 8,  'converted_days_ago' => null, 'signals' => ['shared_neighbourhood' => true, 'category_overlap' => ['Kinder', 'IT'], 'distance_km' => 0.6]],
            ['target' => 'theres',   'related' => 'beat',    'source' => 'tandem_candidate', 'score' => 0.65, 'status' => 'converted', 'sent_days_ago' => 22, 'converted_days_ago' => 20, 'signals' => ['shared_neighbourhood' => true, 'category_overlap' => ['Spaziergang'], 'distance_km' => 0.2]],
            ['target' => 'maria',    'related' => 'anna',    'source' => 'language_partner', 'score' => 0.69, 'status' => 'sent',      'sent_days_ago' => 9,  'converted_days_ago' => null, 'signals' => ['language_overlap' => ['IT', 'DE'], 'category_overlap' => ['Sprache']]],
            ['target' => 'christine','related' => 'sabine',  'source' => 'tandem_candidate', 'score' => 0.74, 'status' => 'converted', 'sent_days_ago' => 18, 'converted_days_ago' => 14, 'signals' => ['shared_neighbourhood' => false, 'category_overlap' => ['IT', 'Sprachen'], 'distance_km' => 8.0]],
            ['target' => 'hans',     'related' => null,      'source' => 'engagement_drop',  'score' => 0.40, 'status' => 'sent',      'sent_days_ago' => 6,  'converted_days_ago' => null, 'signals' => ['logs_last_30_days' => 2, 'baseline' => 5]],
            ['target' => 'peter',    'related' => 'hans',    'source' => 'tandem_candidate', 'score' => 0.62, 'status' => 'sent',      'sent_days_ago' => 1,  'converted_days_ago' => null, 'signals' => ['shared_neighbourhood' => false, 'category_overlap' => ['Reparatur'], 'distance_km' => 4.5]],
        ];
        foreach ($rows as $row) {
            $targetId = $userIds[$row['target']] ?? null;
            $relatedId = $row['related'] ? ($userIds[$row['related']] ?? null) : null;
            if ($targetId === null) {
                continue;
            }
            $sentAt = now()->subDays($row['sent_days_ago']);
            $this->upsert(
                'caring_smart_nudges',
                [
                    'tenant_id' => $tenantId,
                    'target_user_id' => $targetId,
                    'source_type' => $row['source'],
                    'sent_at' => $sentAt,
                ],
                [
                    'related_user_id' => $relatedId,
                    'score' => $row['score'],
                    'signals' => json_encode($row['signals']),
                    'status' => $row['status'],
                    'converted_at' => $row['converted_days_ago'] !== null ? now()->subDays($row['converted_days_ago']) : null,
                    'updated_at' => now(),
                ]
            );
            $this->bump('smart nudges');
        }
    }

    // -----------------------------------------------------------------------
    // Care providers (AG72)
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     * @param array<string, int> $subRegionIds
     */
    private function seedCareProviders(int $tenantId, array $userIds, array $subRegionIds): void
    {
        if (! Schema::hasTable('caring_care_providers')) {
            return;
        }
        $admin = $userIds['admin'] ?? null;
        $rows = [
            [
                'name' => 'Spitex Zug',                  'type' => 'spitex',
                'desc' => 'Professionelle Spitex-Pflege im Kanton Zug. Akut, Langzeit, Palliative Care.',
                'cats' => ['Pflege', 'Hauspflege', 'Palliative Care'], 'phone' => '+41 41 729 29 30',
                'email' => 'info@spitex-zug.ch',         'web' => 'https://spitex-zug.ch',
                'sub' => 'zug-stadt', 'verified' => 1,
                'hours' => ['mon' => '08:00-17:00', 'tue' => '08:00-17:00', 'wed' => '08:00-17:00', 'thu' => '08:00-17:00', 'fri' => '08:00-17:00', 'sat' => '24h', 'sun' => '24h'],
            ],
            [
                'name' => 'Pro Senectute Zug',           'type' => 'verein',
                'desc' => 'Beratung und Begleitung im Alter. Sozialberatung, Steuererklärung, Wohnberatung.',
                'cats' => ['Beratung', 'Sozialberatung', 'Wohnberatung'], 'phone' => '+41 41 727 50 50',
                'email' => 'info@zg.pro-senectute.ch',   'web' => 'https://zg.pro-senectute.ch',
                'sub' => 'zug-stadt', 'verified' => 1,
                'hours' => ['mon' => '08:30-12:00,13:30-17:00', 'tue' => '08:30-12:00,13:30-17:00', 'wed' => '08:30-12:00', 'thu' => '08:30-12:00,13:30-17:00', 'fri' => '08:30-12:00'],
            ],
            [
                'name' => 'Tagesstätte Cham',            'type' => 'tagesstätte',
                'desc' => 'Tagesbetreuung für Senioren — Aktivierung, gemeinsames Mittagessen, Pflegeentlastung.',
                'cats' => ['Tagesbetreuung', 'Aktivierung', 'Pflegeentlastung'], 'phone' => '+41 41 783 12 34',
                'email' => 'info@tagesstaette-cham.ch',  'web' => 'https://tagesstaette-cham.ch',
                'sub' => 'cham', 'verified' => 1,
                'hours' => ['mon' => '08:00-17:00', 'tue' => '08:00-17:00', 'wed' => '08:00-17:00', 'thu' => '08:00-17:00', 'fri' => '08:00-17:00'],
            ],
            [
                'name' => 'Hospiz-Begleitung Zugerland', 'type' => 'verein',
                'desc' => 'Freiwillige Hospiz- und Trauerbegleitung im Kanton Zug. Diskret und kostenlos.',
                'cats' => ['Hospiz', 'Trauerbegleitung', 'Palliativ'], 'phone' => '+41 41 711 22 33',
                'email' => 'kontakt@hospiz-zug.ch',      'web' => 'https://hospiz-zug.ch',
                'sub' => 'zug-stadt', 'verified' => 1,
                'hours' => ['mon' => '09:00-12:00', 'thu' => '14:00-17:00'],
            ],
            [
                'name' => 'KISS Cham — Mitgliederpool',  'type' => 'volunteer',
                'desc' => 'Freiwillige Mitglieder der KISS Cham für nicht-medizinische Nachbarschaftshilfe.',
                'cats' => ['Begleitung', 'Einkauf', 'Fahrdienst', 'Garten'], 'phone' => '+41 41 999 88 77',
                'email' => 'kontakt@kiss-cham.ch',       'web' => 'https://kiss-cham.ch',
                'sub' => 'cham', 'verified' => 1,
                'hours' => ['mon' => '14:00-17:00', 'tue' => '08:00-17:00 (nach Vereinbarung)'],
            ],
        ];

        foreach ($rows as $row) {
            $values = [
                'name' => $row['name'],
                'type' => $row['type'],
                'description' => $row['desc'],
                'categories' => json_encode($row['cats']),
                'address' => null,
                'contact_phone' => $row['phone'],
                'contact_email' => $row['email'],
                'website_url' => $row['web'],
                'opening_hours' => json_encode($row['hours']),
                'is_verified' => $row['verified'],
                'status' => 'active',
                'created_by' => $admin,
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('caring_care_providers', 'sub_region_id') && isset($subRegionIds[$row['sub']])) {
                $values['sub_region_id'] = $subRegionIds[$row['sub']];
            }
            $this->upsert(
                'caring_care_providers',
                ['tenant_id' => $tenantId, 'name' => $row['name']],
                $values
            );
            $this->bump('care providers');
        }
    }

    // -----------------------------------------------------------------------
    // Project announcements (AG69)
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedProjectAnnouncements(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('caring_project_announcements')) {
            return;
        }
        $admin = $userIds['thomas'] ?? $userIds['admin'] ?? null;

        $projects = [
            [
                'title' => 'Sanierung Quartierhof Lorzenhof',
                'summary' => 'Aufwertung des Quartierhofs Lorzenhof — Renovierung der Aussenanlagen, neuer Spielbereich, behindertengerechte Zugänge.',
                'location' => 'Cham, Lorzenhof',
                'status' => 'active',
                'stage' => 'Bauphase',
                'progress' => 65,
                'starts_days_ago' => 90,
                'ends_in_days' => 60,
                'published_days_ago' => 85,
                'last_update_days_ago' => 4,
                'subs' => 28,
                'updates' => [
                    ['stage' => 'Planung', 'title' => 'Bauplan finalisiert', 'body' => 'Der definitive Bauplan ist mit der Gemeinde abgestimmt und freigegeben. Baubeginn am 1. März.', 'progress' => 25, 'milestone' => 1, 'days_ago' => 60],
                    ['stage' => 'Bauphase', 'title' => 'Aussenanlagen abgeschlossen', 'body' => 'Die Aussenanlagen sind fertig — neuer Sandkasten, Klettergerüst und Sitzplatz für Senioren stehen.', 'progress' => 65, 'milestone' => 1, 'days_ago' => 4],
                ],
            ],
            [
                'title' => 'Caring Community Cham — Mitgliederwachstum 2026',
                'summary' => 'Strategie für 100+ neue Caring-Community-Mitglieder in Cham bis Ende 2026 — Outreach, Onboarding, Tandem-Vermittlung.',
                'location' => 'Cham, Steinhausen, Hünenberg',
                'status' => 'active',
                'stage' => 'Outreach Q2',
                'progress' => 35,
                'starts_days_ago' => 60,
                'ends_in_days' => 240,
                'published_days_ago' => 55,
                'last_update_days_ago' => 7,
                'subs' => 19,
                'updates' => [
                    ['stage' => 'Q1 Review', 'title' => 'Q1: 22 neue Mitglieder', 'body' => 'Im ersten Quartal haben 22 neue Mitglieder den Onboarding-Prozess abgeschlossen. Ziel war 25 — knapp daneben.', 'progress' => 22, 'milestone' => 1, 'days_ago' => 30],
                    ['stage' => 'Outreach Q2', 'title' => 'Outreach im Quartierfest gestartet', 'body' => 'Frühlingsfest-Stand hat 12 Interessierte gebracht — drei haben sich direkt angemeldet.', 'progress' => 35, 'milestone' => 0, 'days_ago' => 7],
                ],
            ],
            [
                'title' => 'Verein Spitex Zug Partnerschaft',
                'summary' => 'Aufbau einer engen Zusammenarbeit zwischen KISS Cham und Spitex Zug — gemeinsame Triage, Hilfsmittel-Pool, Caregiver-Schulungen.',
                'location' => 'Cham, Zug',
                'status' => 'active',
                'stage' => 'Pilot-Triage',
                'progress' => 45,
                'starts_days_ago' => 45,
                'ends_in_days' => 120,
                'published_days_ago' => 40,
                'last_update_days_ago' => 12,
                'subs' => 14,
                'updates' => [
                    ['stage' => 'Vereinbarung', 'title' => 'Kooperationsvertrag unterzeichnet', 'body' => 'Spitex Zug und KISS Cham haben einen Kooperationsvertrag unterzeichnet. Datenschutz-Pakt nach FADP/nDSG.', 'progress' => 30, 'milestone' => 1, 'days_ago' => 30],
                    ['stage' => 'Pilot-Triage', 'title' => 'Erste 6 Triage-Fälle', 'body' => 'Die ersten sechs Triage-Fälle wurden gemeinsam bearbeitet. Spitex hat die Pflege übernommen, KISS die Begleitung.', 'progress' => 45, 'milestone' => 0, 'days_ago' => 12],
                ],
            ],
        ];

        foreach ($projects as $proj) {
            $startsAt = now()->subDays($proj['starts_days_ago']);
            $endsAt = now()->addDays($proj['ends_in_days']);
            $publishedAt = now()->subDays($proj['published_days_ago']);
            $lastUpdate = now()->subDays($proj['last_update_days_ago']);

            $projectId = $this->upsertAndGetId(
                'caring_project_announcements',
                ['tenant_id' => $tenantId, 'title' => $proj['title']],
                [
                    'created_by' => $admin,
                    'summary' => $proj['summary'],
                    'location' => $proj['location'],
                    'status' => $proj['status'],
                    'current_stage' => $proj['stage'],
                    'progress_percent' => $proj['progress'],
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'published_at' => $publishedAt,
                    'last_update_at' => $lastUpdate,
                    'subscriber_count' => $proj['subs'],
                    'updated_at' => now(),
                ]
            );
            $this->bump('project announcements');

            if ($projectId > 0 && Schema::hasTable('caring_project_updates')) {
                foreach ($proj['updates'] as $update) {
                    $publishedAtUpdate = now()->subDays($update['days_ago']);
                    $this->upsert(
                        'caring_project_updates',
                        [
                            'tenant_id' => $tenantId,
                            'project_id' => $projectId,
                            'title' => $update['title'],
                        ],
                        [
                            'created_by' => $admin,
                            'stage_label' => $update['stage'],
                            'body' => $update['body'],
                            'progress_percent' => $update['progress'],
                            'is_milestone' => $update['milestone'],
                            'status' => 'published',
                            'published_at' => $publishedAtUpdate,
                            'notification_count' => random_int(8, 24),
                            'updated_at' => $publishedAtUpdate,
                        ]
                    );
                    $this->bump('project updates');
                }
            }

            // Subscriptions: subscribe a few core users to each project
            if ($projectId > 0 && Schema::hasTable('caring_project_subscriptions')) {
                $subscribers = ['marlies', 'andrea', 'theres', 'hans', 'sabine', 'beat', 'thomas'];
                foreach ($subscribers as $key) {
                    $userId = $userIds[$key] ?? null;
                    if ($userId === null) {
                        continue;
                    }
                    $this->upsert(
                        'caring_project_subscriptions',
                        ['project_id' => $projectId, 'user_id' => $userId],
                        [
                            'tenant_id' => $tenantId,
                            'subscribed_at' => $publishedAt->copy()->addDays(random_int(0, 5)),
                            'unsubscribed_at' => null,
                            'updated_at' => now(),
                        ]
                    );
                    $this->bump('project subscriptions');
                }
            }
        }
    }

    // -----------------------------------------------------------------------
    // Feed engagement — likes + comments
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedFeedEngagement(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('feed_posts')) {
            return;
        }

        $posts = DB::table('feed_posts')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'user_id']);

        if ($posts->isEmpty()) {
            return;
        }

        $likerKeys = ['andrea', 'hans', 'sabine', 'theres', 'beat', 'marlies', 'samira', 'anna', 'thomas', 'erika', 'werner', 'karin'];
        $commentTemplates = [
            'Toll, danke für die Info!',
            'Bin dabei — ruf dich noch an.',
            'Tolle Idee, ich melde mich.',
            'Super, hat mir auch sehr geholfen.',
            'Bei mir war das genauso. Danke fürs Teilen.',
            'Schreibe dir gleich eine Nachricht.',
            'Vielen Dank Marlies — wieder so eine gute Initiative!',
            'Ich kann am Mittwoch helfen.',
            'Komme wenn ich kann. Bis dann!',
            'Sehe es heute Abend, dann melde ich mich.',
        ];

        foreach ($posts as $post) {
            // Each post gets 3-7 likes from random users (excluding author)
            $likers = collect($likerKeys)
                ->shuffle()
                ->filter(fn ($k) => isset($userIds[$k]) && $userIds[$k] !== (int) $post->user_id)
                ->take(random_int(3, 7))
                ->values();

            foreach ($likers as $key) {
                if (Schema::hasTable('feed_likes')) {
                    $this->upsert(
                        'feed_likes',
                        ['tenant_id' => $tenantId, 'post_id' => $post->id, 'user_id' => $userIds[$key]],
                        ['created_at' => now()->subDays(random_int(0, 6))]
                    );
                    $this->bump('feed likes');
                }
                if (Schema::hasTable('post_reactions')) {
                    $reactionTypes = ['like', 'love', 'clap', 'celebrate', 'time_credit'];
                    $this->upsert(
                        'post_reactions',
                        ['tenant_id' => $tenantId, 'post_id' => $post->id, 'user_id' => $userIds[$key]],
                        [
                            'reaction_type' => $reactionTypes[array_rand($reactionTypes)],
                            'created_at' => now()->subDays(random_int(0, 6)),
                        ]
                    );
                    $this->bump('post reactions');
                }
            }

            // 1-3 comments per post
            if (Schema::hasTable('feed_comments')) {
                $commenters = collect($likerKeys)
                    ->shuffle()
                    ->filter(fn ($k) => isset($userIds[$k]) && $userIds[$k] !== (int) $post->user_id)
                    ->take(random_int(1, 3))
                    ->values();

                foreach ($commenters as $idx => $key) {
                    $comment = $commentTemplates[($post->id + $idx) % count($commentTemplates)];
                    $this->upsert(
                        'feed_comments',
                        [
                            'tenant_id' => $tenantId,
                            'post_id' => $post->id,
                            'user_id' => $userIds[$key],
                            'content' => $comment,
                        ],
                        [
                            'parent_id' => null,
                            'updated_at' => now(),
                        ]
                    );
                    $this->bump('feed comments');
                }
            }
        }
    }

    // -----------------------------------------------------------------------
    // Event RSVPs
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedEventRsvps(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('event_rsvps')) {
            return;
        }
        $events = DB::table('events')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->limit(15)
            ->get(['id', 'user_id', 'start_date', 'start_time']);

        $allKeys = ['andrea', 'hans', 'sabine', 'theres', 'beat', 'marlies', 'samira', 'anna', 'thomas', 'erika', 'werner', 'karin', 'markus', 'maria', 'stefan', 'roland', 'christine', 'admin'];

        foreach ($events as $event) {
            $rsvpKeys = collect($allKeys)
                ->shuffle()
                ->take(random_int(6, 12))
                ->values();

            $start = null;
            try {
                $start = Carbon::parse((string) ($event->start_time ?? $event->start_date ?? now()));
            } catch (\Throwable) {
                $start = now()->addDays(7);
            }
            $isPast = $start->isPast();

            foreach ($rsvpKeys as $idx => $key) {
                $userId = $userIds[$key] ?? null;
                if ($userId === null) {
                    continue;
                }
                if ($isPast) {
                    $statuses = ['attended', 'attended', 'attended', 'cancelled', 'going'];
                } else {
                    $statuses = ['going', 'going', 'going', 'interested', 'maybe'];
                }
                $status = $statuses[$idx % count($statuses)];
                $this->upsert(
                    'event_rsvps',
                    ['tenant_id' => $tenantId, 'event_id' => $event->id, 'user_id' => $userId],
                    [
                        'status' => $status,
                        'checked_in_at' => $status === 'attended' ? $start->copy()->addMinutes(5) : null,
                        'checked_out_at' => $status === 'attended' ? $start->copy()->addHours(2) : null,
                        'updated_at' => now(),
                    ]
                );
                $this->bump('event RSVPs');
            }
        }
    }

    // -----------------------------------------------------------------------
    // Direct messages
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedDirectMessages(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }
        $threads = [
            [
                'a' => 'andrea', 'b' => 'werner',
                'msgs' => [
                    ['from' => 'andrea',  'body' => 'Lieber Werner, ich komme morgen wie abgemacht um 10 Uhr vorbei. Brauchst du noch was vom Volg?', 'days_ago' => 5],
                    ['from' => 'werner',  'body' => 'Liebe Andrea, danke. Wenn du könntest: 1 Brot Halbweiss, 6 Eier, eine Packung Müesli. Ich gebe dir das Geld bar.',  'days_ago' => 5],
                    ['from' => 'andrea',  'body' => 'Mach ich gerne. Bis morgen!',                                                                                              'days_ago' => 5],
                    ['from' => 'andrea',  'body' => 'Werner, hier bin ich. War alles im Volg da. Bis gleich.',                                                                  'days_ago' => 4],
                ],
            ],
            [
                'a' => 'sabine', 'b' => 'werner',
                'msgs' => [
                    ['from' => 'werner', 'body' => 'Sabine, mein Smartphone klingelt nicht mehr — kommen heute keine Anrufe an. Was kann das sein?', 'days_ago' => 1],
                    ['from' => 'sabine', 'body' => 'Werner, das klingt nach den Klingelton-Einstellungen. Ich komme heute Nachmittag um 16 Uhr vorbei und schaue es an.',     'days_ago' => 1],
                    ['from' => 'werner', 'body' => 'Du bist ein Schatz. Bis 16 Uhr.',                                                                                            'days_ago' => 1],
                ],
            ],
            [
                'a' => 'marlies', 'b' => 'beat',
                'msgs' => [
                    ['from' => 'marlies', 'body' => 'Beat, könntest du am Donnerstag für Erika einspringen? Du fährst ja eh nach Baar.',           'days_ago' => 3],
                    ['from' => 'beat',    'body' => 'Klar, kein Problem. Welche Zeit?',                                                              'days_ago' => 3],
                    ['from' => 'marlies', 'body' => 'Sie braucht 9 Uhr Abholung von zu Hause. Ich schicke dir die Details.',                        'days_ago' => 3],
                    ['from' => 'beat',    'body' => 'Top, ist notiert.',                                                                              'days_ago' => 3],
                ],
            ],
            [
                'a' => 'thomas', 'b' => 'marlies',
                'msgs' => [
                    ['from' => 'thomas',  'body' => 'Marlies, die Q1-Zahlen für die Caring Community sind für die Gemeinderatssitzung sehr hilfreich. Danke für die saubere Aufbereitung.',  'days_ago' => 9],
                    ['from' => 'marlies', 'body' => 'Gerne, Thomas. Können wir nächste Woche kurz zusammensitzen wegen Q2?',                                                                'days_ago' => 9],
                    ['from' => 'thomas',  'body' => 'Mittwoch 14 Uhr passt mir. Ich sehe dich im Mandelhof.',                                                                                'days_ago' => 9],
                ],
            ],
            [
                'a' => 'samira', 'b' => 'maria',
                'msgs' => [
                    ['from' => 'samira', 'body' => 'Maria, ich muss am Mittwoch dringend zum Zahnarzt — kannst du die Kinder von 16 bis 18 Uhr nehmen?', 'days_ago' => 2],
                    ['from' => 'maria',  'body' => 'Sehr gerne, Samira! Bring sie einfach vorbei. Sie können bei uns Zvieri essen.',                       'days_ago' => 2],
                    ['from' => 'samira', 'body' => 'Du rettest mich. Vielen Dank!',                                                                          'days_ago' => 2],
                ],
            ],
        ];

        foreach ($threads as $thread) {
            $aId = $userIds[$thread['a']] ?? null;
            $bId = $userIds[$thread['b']] ?? null;
            if ($aId === null || $bId === null || $aId === $bId) {
                continue;
            }

            // Conversation row (best-effort — schema sometimes lacks columns)
            $convId = null;
            if (Schema::hasTable('conversations')) {
                $convId = (int) DB::table('conversations')
                    ->where('tenant_id', $tenantId)
                    ->where('is_group', 0)
                    ->whereIn('id', function ($q) use ($tenantId, $aId, $bId) {
                        // Find any existing 1:1 conversation between these two via messages
                        $q->select('conversation_id')
                            ->from('messages')
                            ->where('tenant_id', $tenantId)
                            ->whereNotNull('conversation_id')
                            ->where(function ($q2) use ($aId, $bId) {
                                $q2->where(function ($q3) use ($aId, $bId) {
                                    $q3->where('sender_id', $aId)->where('receiver_id', $bId);
                                })->orWhere(function ($q3) use ($aId, $bId) {
                                    $q3->where('sender_id', $bId)->where('receiver_id', $aId);
                                });
                            });
                    })
                    ->value('id');

                if (! $convId) {
                    $convId = (int) DB::table('conversations')->insertGetId([
                        'tenant_id' => $tenantId,
                        'is_group' => 0,
                        'created_by' => $aId,
                        'created_at' => now()->subDays(60),
                        'updated_at' => now(),
                    ]);
                }
            }

            foreach ($thread['msgs'] as $msg) {
                $senderKey = $msg['from'];
                $senderId = $userIds[$senderKey] ?? null;
                if ($senderId === null) {
                    continue;
                }
                $receiverId = $senderId === $aId ? $bId : $aId;
                $createdAt = now()->subDays($msg['days_ago'])->subHours(random_int(0, 18));
                $this->upsert(
                    'messages',
                    [
                        'tenant_id' => $tenantId,
                        'sender_id' => $senderId,
                        'receiver_id' => $receiverId,
                        'body' => $msg['body'],
                        'created_at' => $createdAt,
                    ],
                    [
                        'conversation_id' => $convId,
                        'is_read' => 1,
                        'read_at' => $createdAt->copy()->addMinutes(random_int(5, 240)),
                    ]
                );
                $this->bump('direct messages');
            }
        }
    }

    // -----------------------------------------------------------------------
    // KPI baseline + scoreboard envelope + ROI envelope
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedKpiBaseline(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('caring_kpi_baselines')) {
            return;
        }
        $admin = $userIds['admin'] ?? null;
        $metrics = [
            'volunteer_hours'      => 412,
            'member_count'         => 23,
            'recipient_count'      => 5,
            'avg_response_hours'   => 18.5,
            'engagement_rate_pct'  => 64,
            'active_relationships' => 6,
            'total_exchanges'      => 59,
        ];
        $period = ['start' => '2026-01-01', 'end' => '2026-03-31'];
        $this->upsert(
            'caring_kpi_baselines',
            ['tenant_id' => $tenantId, 'label' => 'Q1 2026 Baseline — Cham Pilot'],
            [
                'baseline_period' => json_encode($period),
                'captured_at' => now()->subDays(30),
                'metrics' => json_encode($metrics),
                'notes' => 'Erstes Quartal des Cham-Pilots. Werte werden vierteljährlich neu erhoben (AG83). Vergleiche mit Q2 ab Anfang Juli.',
                'captured_by' => $admin,
                'updated_at' => now(),
            ]
        );
        $this->bump('KPI baselines');
    }

    /**
     * Seed a tenant_settings JSON envelope for the AG83 Pilot Scoreboard so the
     * admin page renders Q1 baseline + projected Q2 numbers instead of "no
     * data" empty state. Storage key is platform-conventional: `caring.pilot_scoreboard`.
     */
    private function seedScoreboardEnvelope(int $tenantId): void
    {
        if (! Schema::hasTable('tenant_settings')) {
            return;
        }
        $envelope = [
            'pilot_window' => ['start' => '2026-01-01', 'end' => '2026-12-31'],
            'cadence' => 'quarterly',
            'snapshots' => [
                [
                    'quarter' => 'Q1 2026',
                    'captured_at' => now()->subDays(30)->toIso8601String(),
                    'metrics' => [
                        'volunteer_hours' => 412,
                        'member_count' => 23,
                        'recipient_count' => 5,
                        'avg_response_hours' => 18.5,
                        'engagement_rate_pct' => 64,
                        'active_relationships' => 6,
                        'total_exchanges' => 59,
                    ],
                ],
                [
                    'quarter' => 'Q2 2026 (in progress)',
                    'captured_at' => now()->toIso8601String(),
                    'metrics' => [
                        'volunteer_hours' => 178, // mid-quarter
                        'member_count' => 26,
                        'recipient_count' => 7,
                        'avg_response_hours' => 14.2,
                        'engagement_rate_pct' => 71,
                        'active_relationships' => 8,
                        'total_exchanges' => 26,
                    ],
                    'progress_pct' => 38,
                ],
            ],
            'updated_at' => now()->toIso8601String(),
        ];
        $this->upsert(
            'tenant_settings',
            ['tenant_id' => $tenantId, 'setting_key' => 'caring.pilot_scoreboard'],
            [
                'setting_value' => json_encode($envelope),
                'setting_type' => 'json',
                'category' => 'caring',
                'updated_at' => now(),
            ]
        );
        $this->bump('pilot scoreboard envelope');
    }

    /**
     * AG76 Municipal ROI — compute and persist the headline ROI numbers for
     * the Cham pilot. Uses Swiss formal-care reference rates. Storage key:
     * `caring.municipal_roi`.
     */
    private function seedMunicipalRoiEnvelope(int $tenantId): void
    {
        if (! Schema::hasTable('tenant_settings')) {
            return;
        }
        // ROI calc: 412 verified hours × CHF 35/hr formal care rate = CHF 14,420
        $envelope = [
            'computed_at' => now()->toIso8601String(),
            'period' => ['start' => '2026-01-01', 'end' => '2026-03-31'],
            'inputs' => [
                'verified_volunteer_hours' => 412,
                'formal_care_rate_chf_per_hour' => 35.00,
                'admin_overhead_pct' => 12,
            ],
            'outputs' => [
                'formal_care_offset_chf' => 14420.00,
                'admin_overhead_chf' => 1730.40,
                'net_offset_chf' => 12689.60,
                'avg_offset_per_member_chf' => 552.00,
                'recipient_independence_days_extended' => 87,
            ],
            'methodology_notes' => 'Berechnung nach AG76. Formelle Pflege-Rate gemäss Spitex-Tarif Kanton Zug 2026. Verwaltungs-Overhead pauschal mit 12% angesetzt (KISS-Genossenschaft Schweizer Durchschnitt).',
        ];
        $this->upsert(
            'tenant_settings',
            ['tenant_id' => $tenantId, 'setting_key' => 'caring.municipal_roi'],
            [
                'setting_value' => json_encode($envelope),
                'setting_type' => 'json',
                'category' => 'caring',
                'updated_at' => now(),
            ]
        );
        $this->bump('municipal ROI envelope');
    }

    // -----------------------------------------------------------------------
    // Generic upsert helpers
    // -----------------------------------------------------------------------

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

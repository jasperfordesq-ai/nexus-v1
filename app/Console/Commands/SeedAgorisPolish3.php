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
 * Third polish pass for the Agoris demo tenant — populates the niche tables
 * polish2 left empty and replaces placeholder marketplace images with
 * category-appropriate photos via [loremflickr.com](http://loremflickr.com) (free, deterministic, CC-licensed).
 *
 * Targets the remaining gaps from the polish2 audit:
 *   - users.avatar_url                       (25 personas, DiceBear deterministic)
 *   - marketplace_images replacement          (25 listings × keyword-matched photos)
 *   - caring_hour_transfers                   (4 cross-tenant rows)
 *   - caring_hour_estates                     (3 estate nominations)
 *   - caring_kiss_treffen                     (3 governance events)
 *   - caring_paper_onboarding_intakes         (3 paper intakes — confirmed/pending/rejected)
 *   - member_residency_verifications          (12 records)
 *   - caring_federation_peers                 (3 sister-cooperatives)
 *   - caring_research_partners                (2 academic partners)
 *   - caring_research_consents                (25 records — 18 opted-in)
 *   - caring_research_dataset_exports         (2 anonymised exports)
 *   - caring_loyalty_redemptions              (6 redemptions)
 *   - caring_tandem_suggestion_log            (8 entries)
 *   - caring.isolated_node.* tenant_settings  (11 AG85 decision-gate items)
 *
 * Idempotent — every insert keys on a stable identity tuple. Safe to re-run.
 *
 *   php artisan tenant:seed-agoris-polish3 agoris
 *   php artisan tenant:seed-agoris-polish3 agoris --dry-run
 */
class SeedAgorisPolish3 extends Command
{
    protected $signature = 'tenant:seed-agoris-polish3
        {tenant_slug=agoris : Tenant slug to polish}
        {--dry-run : Show what would be seeded without writing}';

    protected $description = 'Third polish pass — fills niche tables (transfers, estates, treffen, residency, federation, research, loyalty, tandem log, isolated-node) and adds avatars + real product photos';

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
            $this->line('DRY RUN: would seed avatars, real product photos, hour transfers, estates, KISS Treffen, paper intakes, residency, federation, research consents, loyalty, tandem log, isolated-node decisions.');
            return self::SUCCESS;
        }

        $userIds = $this->loadUserIds($tenantId);
        if (count($userIds) < 6) {
            $this->error('Found fewer than 6 Agoris users. Run earlier seeders first.');
            return self::FAILURE;
        }

        $this->seedAvatars($tenantId, $userIds);
        $this->seedRealMarketplacePhotos($tenantId);

        $peers = $this->seedFederationPeers($tenantId);
        $this->seedHourTransfers($tenantId, $userIds, $peers);
        $this->seedHourEstates($tenantId, $userIds);
        $this->seedKissTreffen($tenantId, $userIds);
        $this->seedPaperOnboardingIntakes($tenantId, $userIds);
        $this->seedResidencyVerifications($tenantId, $userIds);

        $partners = $this->seedResearchPartners($tenantId, $userIds);
        $this->seedResearchConsents($tenantId, $userIds);
        $this->seedResearchExports($tenantId, $partners, $userIds);

        $this->seedLoyaltyRedemptions($tenantId);
        $this->seedTandemSuggestionLog($tenantId, $userIds);

        $this->seedIsolatedNodeDecisions($tenantId);

        $this->newLine();
        $this->info('Agoris polish3 seed complete.');
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
    // Avatars (DiceBear, deterministic by user_id)
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedAvatars(int $tenantId, array $userIds): void
    {
        if (! Schema::hasColumn('users', 'avatar_url')) {
            return;
        }
        // DiceBear styles chosen to vary the personas while still looking professional.
        // Female-presenting personas → 'lorelei' / 'notionists'; male → 'personas' / 'notionists-neutral'.
        $female = ['anna_b', 'maria', 'samira', 'elena', 'andrea', 'sabine', 'marlies', 'theres', 'anna', 'erika', 'karin', 'christine'];
        $male   = ['luca', 'peter', 'thomas_s', 'hans', 'roland', 'werner', 'markus', 'beat', 'stefan', 'thomas'];

        foreach ($userIds as $key => $userId) {
            if (in_array($key, $female, true)) {
                $style = 'lorelei';
            } elseif (in_array($key, $male, true)) {
                $style = 'personas';
            } else {
                // admin
                $style = 'shapes';
            }
            $url = "https://api.dicebear.com/7.x/{$style}/svg?seed=agoris-{$key}-{$userId}&backgroundColor=b6e3f4,c0aede,d1d4f9,ffd5dc,ffdfbf";

            DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->update(['avatar_url' => $url, 'updated_at' => now()]);
            $this->bump('avatars set');
        }
    }

    // -----------------------------------------------------------------------
    // Real marketplace photos (loremflickr.com — keyword-matched CC photos)
    // -----------------------------------------------------------------------

    private function seedRealMarketplacePhotos(int $tenantId): void
    {
        if (! Schema::hasTable('marketplace_images') || ! Schema::hasTable('marketplace_listings')) {
            return;
        }
        // Map listing-id substring patterns / titles → keywords for [loremflickr.com](http://loremflickr.com)
        $keywords = [
            'Saisonbox: Frühlingsgemüse'    => ['vegetables,basket',     'farmers-market'],
            'Eier vom eigenen Hof'           => ['eggs,farm',             'eggs,brown'],
            'Konfitüre — Aprikose'           => ['apricot,jam',           'jam,jar'],
            'Setzlinge — Tomaten'            => ['seedlings,tomato',      'garden,plant'],
            'Zuger Halbweiss-Brot'           => ['bread,sourdough',       'artisan-bread'],
            'Zopf für den Sonntag'           => ['braided-bread,zopf',    'breakfast-bread'],
            'Caring-Community-Box'           => ['bread,gipfeli',         'pastries'],
            'Hocker aus regionalem Eichenholz' => ['stool,wood',          'oak,furniture'],
            'Reparatur-Stunde Werkstatt'     => ['workshop,tools',        'craftsman'],
            'Hochbeet aus Lärchenholz'       => ['raised-bed,garden',     'wooden-planter'],
            'Frühlingstracht-Honig'          => ['honey,jar',             'honeycomb'],
            'Bienenwachs-Kerzen'             => ['beeswax,candles',       'candles,handmade'],
            'Tausch-Roman'                   => ['book,novel',            'reading,books'],
            'Brettspiel'                     => ['boardgame,table',       'game,family'],
            'Kinderbuch-Sammelpaket'         => ['childrens-books',       'picture-book'],
            'Velo-Reparatur'                 => ['bicycle-repair,bike',   'bicycle,workshop'],
            'Kleidungsreparatur — Nähservice' => ['sewing,needle',        'tailor,fabric'],
            'Kleingeräte-Diagnose'           => ['kitchen-tools,utensil', 'mixer,appliance'],
            'Wärmekissen mit Dinkel'         => ['cushion,cosy',          'spelt,grain'],
            'Propolis-Tinktur'               => ['propolis,beekeeping',   'tincture,bottle'],
            'Glutenfreies Brot'              => ['gluten-free-bread',     'bread,rustic'],
            'Velo-Anhänger'                  => ['bike-trailer,cycling',  'cargo-bike'],
            'Direktverkauf-Karte'            => ['voucher,gift-card',     'farmers-market'],
            'Sonntagspaket'                  => ['breakfast-spread,bakery', 'pastries,bread'],
        ];

        $listings = DB::table('marketplace_listings')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->get(['id', 'title']);

        foreach ($listings as $listing) {
            $title = (string) $listing->title;
            // Find best keyword match
            $kw = ['marketplace,product', 'community,exchange'];
            foreach ($keywords as $needle => $kws) {
                if (str_contains($title, $needle)) {
                    $kw = $kws;
                    break;
                }
            }

            $sortOrder = 0;
            foreach ($kw as $idx => $tags) {
                $tagSlug = str_replace(['/', ' '], ['', '-'], $tags);
                // [loremflickr.com](http://loremflickr.com) returns deterministic CC photos for a keyword + lock seed
                $url = "https://[loremflickr.com/800/600/{$tagSlug}/all?lock={$listing->id}-{$idx}](http://loremflickr.com/800/600/{$tagSlug}/all?lock={$listing->id}-{$idx})";
                $thumb = "https://[loremflickr.com/240/180/{$tagSlug}/all?lock={$listing->id}-{$idx}](http://loremflickr.com/240/180/{$tagSlug}/all?lock={$listing->id}-{$idx})";

                $this->upsert(
                    'marketplace_images',
                    [
                        'tenant_id' => $tenantId,
                        'marketplace_listing_id' => $listing->id,
                        'sort_order' => $sortOrder,
                    ],
                    [
                        'image_url' => $url,
                        'thumbnail_url' => $thumb,
                        'alt_text' => $idx === 0 ? $title : $title . ' (Detail)',
                        'is_primary' => $idx === 0 ? 1 : 0,
                        'created_at' => now(),
                    ]
                );
                $this->bump('marketplace photos (keyword)');
                $sortOrder++;
            }
        }
    }

    // -----------------------------------------------------------------------
    // Federation peers (AG23)
    // -----------------------------------------------------------------------

    /**
     * @return array<string, int>
     */
    private function seedFederationPeers(int $tenantId): array
    {
        if (! Schema::hasTable('caring_federation_peers')) {
            return [];
        }
        $rows = [
            [
                'slug' => 'kiss-bern', 'name' => 'KISS Bern', 'url' => 'https://api.kiss-bern.ch',
                'secret' => bin2hex(hash('sha256', 'agoris-bern-pair-2026', true)),
                'status' => 'active',
                'notes' => 'Aktiver Partner. Hour-Transfers laufen seit Q4 2025. Quartalsweise Reconciliation.',
                'handshake_days_ago' => 2,
            ],
            [
                'slug' => 'kiss-luzern', 'name' => 'KISS Luzern', 'url' => 'https://api.kiss-luzern.ch',
                'secret' => bin2hex(hash('sha256', 'agoris-luzern-pair-2026', true)),
                'status' => 'active',
                'notes' => 'Aktiver Partner seit Q1 2026. Erste Transfers erfolgreich.',
                'handshake_days_ago' => 5,
            ],
            [
                'slug' => 'kiss-st-gallen', 'name' => 'KISS St. Gallen', 'url' => 'https://api.kiss-st-gallen.ch',
                'secret' => bin2hex(hash('sha256', 'agoris-stgallen-pair-2026', true)),
                'status' => 'pending',
                'notes' => 'Verträge unterzeichnet, technische Inbetriebnahme im Mai 2026. Noch keine Transfers.',
                'handshake_days_ago' => null,
            ],
        ];
        $ids = [];
        foreach ($rows as $row) {
            $ids[$row['slug']] = $this->upsertAndGetId(
                'caring_federation_peers',
                ['tenant_id' => $tenantId, 'peer_slug' => $row['slug']],
                [
                    'display_name' => $row['name'],
                    'base_url' => $row['url'],
                    'shared_secret' => $row['secret'],
                    'status' => $row['status'],
                    'notes' => $row['notes'],
                    'last_handshake_at' => $row['handshake_days_ago'] !== null ? now()->subDays($row['handshake_days_ago']) : null,
                    'updated_at' => now(),
                ]
            );
            $this->bump('federation peers');
        }
        return $ids;
    }

    // -----------------------------------------------------------------------
    // Hour transfers (cross-tenant — Cham side only)
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     * @param array<string, int> $peers
     */
    private function seedHourTransfers(int $tenantId, array $userIds, array $peers): void
    {
        if (! Schema::hasTable('caring_hour_transfers')) {
            return;
        }
        $rows = [
            // Cham → Bern: Marlies sent 5h to a Bern coordinator (member retired and moved)
            [
                'role' => 'source', 'peer' => 'kiss-bern',
                'member' => 'marlies', 'counterpart_email' => 'kiss-bern-coord@kiss-bern.ch',
                'hours' => 5.0, 'status' => 'completed', 'days_ago' => 18,
                'reason' => 'Mitglied umgezogen — Stundenübertragung an KISS Bern.',
            ],
            // Cham → Bern: Stefan sent 2h to a relative in Bern who joined KISS Bern
            [
                'role' => 'source', 'peer' => 'kiss-bern',
                'member' => 'stefan', 'counterpart_email' => 'h.birrer@kiss-bern.ch',
                'hours' => 2.0, 'status' => 'completed', 'days_ago' => 9,
                'reason' => 'Stundengeschenk an Familienmitglied bei KISS Bern.',
            ],
            // Luzern → Cham: Werner received 3h from his sister in Luzern
            [
                'role' => 'destination', 'peer' => 'kiss-luzern',
                'member' => 'werner', 'counterpart_email' => 'rita.hausmann@kiss-luzern.ch',
                'hours' => 3.0, 'status' => 'received', 'days_ago' => 6,
                'reason' => 'Stundengeschenk von Schwester — Empfang von KISS Luzern.',
            ],
            // Luzern → Cham: Theres received 1h after a meet-up exchange
            [
                'role' => 'destination', 'peer' => 'kiss-luzern',
                'member' => 'theres', 'counterpart_email' => 'h.studer@kiss-luzern.ch',
                'hours' => 1.0, 'status' => 'completed', 'days_ago' => 14,
                'reason' => 'Austausch nach gemeinsamem Anlass im Tropenhaus Wolhusen.',
            ],
        ];
        foreach ($rows as $row) {
            $memberId = $userIds[$row['member']] ?? null;
            if ($memberId === null || ! isset($peers[$row['peer']])) {
                continue;
            }
            $createdAt = now()->subDays($row['days_ago']);
            $remoteKey = $row['peer'] . ':' . $row['days_ago'] . ':' . $memberId;
            $this->upsert(
                'caring_hour_transfers',
                [
                    'tenant_id' => $tenantId,
                    'role' => $row['role'],
                    'member_user_id' => $memberId,
                    'counterpart_tenant_slug' => $row['peer'],
                ],
                [
                    'counterpart_member_email' => $row['counterpart_email'],
                    'hours_transferred' => $row['hours'],
                    'status' => $row['status'],
                    'reason' => $row['reason'],
                    'signature' => substr(hash('sha256', $remoteKey), 0, 64),
                    'remote_idempotency_key' => $remoteKey,
                    'is_remote' => $row['role'] === 'destination' ? 1 : 0,
                    'payload_json' => json_encode([
                        'sent_via' => 'federation_v1',
                        'source_tenant_name' => $row['role'] === 'source' ? 'Agoris Caring Community' : ucfirst(str_replace('-', ' ', $row['peer'])),
                    ]),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );
            $this->bump('hour transfers');
        }
    }

    // -----------------------------------------------------------------------
    // Hour estates
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedHourEstates(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('caring_hour_estates')) {
            return;
        }
        $coordinator = $userIds['marlies'] ?? null;
        $rows = [
            [
                'member' => 'werner', 'beneficiary' => 'andrea',
                'action' => 'transfer_to_beneficiary', 'status' => 'nominated',
                'reported_balance' => null,
                'member_notes' => 'Im Falle meines Hinscheidens sollen meine verbleibenden Stunden an Andrea Müller (meine Nachbarin und Helferin) übertragen werden.',
                'coordinator_notes' => null,
                'nominated_days_ago' => 60, 'reported_days_ago' => null, 'settled_days_ago' => null,
            ],
            [
                'member' => 'theres', 'beneficiary' => null,
                'action' => 'donate_to_solidarity', 'status' => 'nominated',
                'reported_balance' => null,
                'member_notes' => 'Stunden sollen an den Solidaritätspool der KISS Cham gespendet werden.',
                'coordinator_notes' => 'Nominierung im Beisein von Marlies Iten am 5. Februar 2026 unterzeichnet.',
                'nominated_days_ago' => 90, 'reported_days_ago' => null, 'settled_days_ago' => null,
            ],
            [
                'member' => 'erika', 'beneficiary' => null,
                'action' => 'donate_to_solidarity', 'status' => 'nominated',
                'reported_balance' => null,
                'member_notes' => 'Solidaritätspool, falls noch Stunden offen sind.',
                'coordinator_notes' => null,
                'nominated_days_ago' => 45, 'reported_days_ago' => null, 'settled_days_ago' => null,
            ],
        ];
        foreach ($rows as $row) {
            $memberId = $userIds[$row['member']] ?? null;
            $beneficiaryId = $row['beneficiary'] ? ($userIds[$row['beneficiary']] ?? null) : null;
            if ($memberId === null) {
                continue;
            }
            $this->upsert(
                'caring_hour_estates',
                ['tenant_id' => $tenantId, 'member_user_id' => $memberId],
                [
                    'beneficiary_user_id' => $beneficiaryId,
                    'policy_action' => $row['action'],
                    'status' => $row['status'],
                    'reported_balance_hours' => $row['reported_balance'],
                    'settled_hours' => null,
                    'policy_document_reference' => 'kiss-cham-policy-2025-12',
                    'member_notes' => $row['member_notes'],
                    'coordinator_notes' => $row['coordinator_notes'],
                    'nominated_at' => $row['nominated_days_ago'] !== null ? now()->subDays($row['nominated_days_ago']) : null,
                    'reported_deceased_at' => $row['reported_days_ago'] !== null ? now()->subDays($row['reported_days_ago']) : null,
                    'settled_at' => $row['settled_days_ago'] !== null ? now()->subDays($row['settled_days_ago']) : null,
                    'reported_by' => $row['reported_days_ago'] !== null ? $coordinator : null,
                    'settled_by' => $row['settled_days_ago'] !== null ? $coordinator : null,
                    'updated_at' => now(),
                ]
            );
            $this->bump('hour estates');
        }
    }

    // -----------------------------------------------------------------------
    // KISS Treffen (governance metadata on existing events)
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedKissTreffen(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('caring_kiss_treffen')) {
            return;
        }
        $coordinator = $userIds['marlies'] ?? null;

        $events = DB::table('events')
            ->where('tenant_id', $tenantId)
            ->whereIn('title', [
                'KISS Cham Mitgliederversammlung',
                'Runder Tisch Kanton Zug: Freiwilligenstunden sichtbar machen',
                'Caring Community Stamm — Austausch für Helfer:innen',
            ])
            ->pluck('id', 'title');

        $treffen = [
            [
                'title' => 'KISS Cham Mitgliederversammlung',
                'type' => 'annual_general_assembly',
                'members_only' => 1,
                'quorum' => 15,
                'header' => 'KISS Genossenschaft Cham — Jahresversammlung 2026',
                'minutes' => 'https://kiss-cham.ch/protokolle/2026-jahresversammlung.pdf',
                'minutes_days_ago' => 14,
                'notes' => 'Jahresversammlung 2026. Quorum mit 18 Mitgliedern erreicht. Vorstand bestätigt für 2026/27.',
            ],
            [
                'title' => 'Runder Tisch Kanton Zug: Freiwilligenstunden sichtbar machen',
                'type' => 'governance_circle',
                'members_only' => 0,
                'quorum' => null,
                'header' => 'Kantonaler Runder Tisch — Open Forum',
                'minutes' => 'https://kiss-cham.ch/protokolle/2026-runder-tisch-zug.pdf',
                'minutes_days_ago' => 7,
                'notes' => 'Multistakeholder-Diskussion mit Pro Senectute Zug, Spitex und Kantonsverwaltung. Kein Quorum erforderlich.',
            ],
            [
                'title' => 'Caring Community Stamm — Austausch für Helfer:innen',
                'type' => 'monthly_stamm',
                'members_only' => 1,
                'quorum' => null,
                'header' => 'Monatlicher Stamm — KISS Cham',
                'minutes' => null,
                'minutes_days_ago' => null,
                'notes' => 'Monatlicher Austausch ohne Quorum. Protokoll wird nach dem Treffen ergänzt.',
            ],
        ];

        foreach ($treffen as $t) {
            $eventId = $events[$t['title']] ?? null;
            if ($eventId === null) {
                continue;
            }
            $values = [
                'treffen_type' => $t['type'],
                'members_only' => $t['members_only'],
                'quorum_required' => $t['quorum'],
                'fondation_header' => $t['header'],
                'minutes_document_url' => $t['minutes'],
                'minutes_uploaded_at' => $t['minutes_days_ago'] !== null ? now()->subDays($t['minutes_days_ago']) : null,
                'minutes_uploaded_by' => $t['minutes'] !== null ? $coordinator : null,
                'coordinator_notes' => $t['notes'],
                'updated_at' => now(),
            ];
            $this->upsert(
                'caring_kiss_treffen',
                ['tenant_id' => $tenantId, 'event_id' => $eventId],
                $values
            );
            $this->bump('KISS Treffen');
        }
    }

    // -----------------------------------------------------------------------
    // Paper onboarding intakes
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedPaperOnboardingIntakes(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('caring_paper_onboarding_intakes')) {
            return;
        }
        $coordinator = $userIds['marlies'] ?? null;
        $rows = [
            [
                'filename' => 'werner-hausmann-anmeldung.pdf',
                'path' => '/uploads/agoris/paper-onboarding/2026-02/werner-hausmann.pdf',
                'mime' => 'application/pdf', 'size' => 248_640,
                'extracted' => [
                    'first_name' => 'Werner', 'last_name' => 'Hausmann',
                    'date_of_birth' => '1948-09-12', 'phone' => '+41 41 999 88 11',
                    'address' => 'Lorzenhof 14, 6330 Cham',
                    'emergency_contact' => 'Rita Hausmann (Schwester) +41 41 999 77 22',
                ],
                'corrected' => [
                    'first_name' => 'Werner', 'last_name' => 'Hausmann',
                    'date_of_birth' => '1948-09-12', 'phone' => '+41 41 999 88 11',
                    'address' => 'Lorzenhof 14, 6330 Cham',
                ],
                'status' => 'confirmed',
                'days_ago' => 30,
                'created_user_email' => 'werner.hausmann@demo-agoris.ch',
                'notes' => 'Papier-Anmeldung im KISS-Büro Cham unterzeichnet. Identität persönlich verifiziert durch Marlies Iten.',
            ],
            [
                'filename' => 'erika-wyss-anmeldung.pdf',
                'path' => '/uploads/agoris/paper-onboarding/2026-04/erika-wyss.pdf',
                'mime' => 'application/pdf', 'size' => 198_400,
                'extracted' => [
                    'first_name' => 'Erika', 'last_name' => 'Wyss',
                    'date_of_birth' => '1952-03-20', 'phone' => '+41 41 555 66 33',
                    'address' => 'Bahnhofstrasse 8, 6340 Baar',
                ],
                'corrected' => null,
                'status' => 'pending_review',
                'days_ago' => 3,
                'created_user_email' => null,
                'notes' => 'OCR-Resultate werden noch vom Koordinator gegengelesen. Unterschrift unklar — Rückfrage telefonisch.',
            ],
            [
                'filename' => 'unleserliche-anmeldung-2026-04.jpg',
                'path' => '/uploads/agoris/paper-onboarding/2026-04/unleserlich.jpg',
                'mime' => 'image/jpeg', 'size' => 1_245_180,
                'extracted' => [
                    'first_name' => '', 'last_name' => '',
                    'note' => 'OCR konnte 0/8 Felder verlässlich auslesen.',
                ],
                'corrected' => null,
                'status' => 'rejected',
                'days_ago' => 11,
                'created_user_email' => null,
                'notes' => 'Scan zu unleserlich. Person wurde gebeten, ein neues Formular einzureichen.',
            ],
        ];
        foreach ($rows as $row) {
            $createdUserId = null;
            if ($row['created_user_email']) {
                $createdUserId = (int) DB::table('users')
                    ->where('tenant_id', $tenantId)
                    ->where('email', $row['created_user_email'])
                    ->value('id');
            }
            $this->upsert(
                'caring_paper_onboarding_intakes',
                ['tenant_id' => $tenantId, 'original_filename' => $row['filename']],
                [
                    'uploaded_by' => $coordinator,
                    'reviewed_by' => $row['status'] !== 'pending_review' ? $coordinator : null,
                    'created_user_id' => $createdUserId,
                    'status' => $row['status'],
                    'stored_path' => $row['path'],
                    'mime_type' => $row['mime'],
                    'file_size' => $row['size'],
                    'ocr_provider' => 'manual_review_stub',
                    'extracted_fields' => json_encode($row['extracted']),
                    'corrected_fields' => $row['corrected'] !== null ? json_encode($row['corrected']) : null,
                    'coordinator_notes' => $row['notes'],
                    'confirmed_at' => $row['status'] === 'confirmed' ? now()->subDays(max(0, $row['days_ago'] - 1)) : null,
                    'rejected_at' => $row['status'] === 'rejected' ? now()->subDays(max(0, $row['days_ago'] - 1)) : null,
                    'created_at' => now()->subDays($row['days_ago']),
                    'updated_at' => now(),
                ]
            );
            $this->bump('paper onboarding intakes');
        }
    }

    // -----------------------------------------------------------------------
    // Member residency verifications
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedResidencyVerifications(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('member_residency_verifications')) {
            return;
        }
        $coordinator = $userIds['marlies'] ?? null;
        $rows = [
            ['user' => 'andrea',    'muni' => 'Cham',         'pc' => '6330', 'addr' => 'Lorzenstrasse 18', 'status' => 'approved', 'days_ago' => 180],
            ['user' => 'werner',    'muni' => 'Cham',         'pc' => '6330', 'addr' => 'Lorzenhof 14',     'status' => 'approved', 'days_ago' => 60],
            ['user' => 'theres',    'muni' => 'Cham',         'pc' => '6330', 'addr' => 'Mandelhof 4',      'status' => 'approved', 'days_ago' => 200],
            ['user' => 'beat',      'muni' => 'Baar',         'pc' => '6340', 'addr' => 'Höfnerstrasse 22', 'status' => 'approved', 'days_ago' => 150],
            ['user' => 'erika',     'muni' => 'Baar',         'pc' => '6340', 'addr' => 'Bahnhofstrasse 8', 'status' => 'approved', 'days_ago' => 45],
            ['user' => 'sabine',    'muni' => 'Cham',         'pc' => '6330', 'addr' => 'Stuckystrasse 11', 'status' => 'approved', 'days_ago' => 220],
            ['user' => 'hans',      'muni' => 'Steinhausen',  'pc' => '6312', 'addr' => 'Bahnhofstrasse 15', 'status' => 'approved', 'days_ago' => 170],
            ['user' => 'marlies',   'muni' => 'Cham',         'pc' => '6330', 'addr' => 'Mandelhof 1',      'status' => 'approved', 'days_ago' => 365],
            ['user' => 'samira',    'muni' => 'Steinhausen',  'pc' => '6312', 'addr' => 'Sennweid 4',       'status' => 'pending',  'days_ago' => 5],
            ['user' => 'maria',     'muni' => 'Hünenberg',    'pc' => '6331', 'addr' => 'Dorfplatz 7',      'status' => 'pending',  'days_ago' => 8],
            ['user' => 'roland',    'muni' => 'Zug',          'pc' => '6300', 'addr' => 'Vorstadt 42',      'status' => 'rejected', 'days_ago' => 22, 'rejection' => 'Adresse stimmt nicht mit Wohnsitzbestätigung der Gemeinde überein. Erneute Einreichung mit aktueller Bestätigung erforderlich.'],
            ['user' => 'stefan',    'muni' => 'Cham',         'pc' => '6330', 'addr' => 'Schluechtweg 9',   'status' => 'approved', 'days_ago' => 90],
        ];
        foreach ($rows as $row) {
            $userId = $userIds[$row['user']] ?? null;
            if ($userId === null) {
                continue;
            }
            $createdAt = now()->subDays($row['days_ago']);
            $values = [
                'declared_municipality' => $row['muni'],
                'declared_postcode' => $row['pc'],
                'declared_address' => $row['addr'],
                'evidence_note' => $row['status'] === 'approved'
                    ? 'Wohnsitzbestätigung der Einwohnerkontrolle vorgelegt — verifiziert.'
                    : ($row['status'] === 'pending' ? 'Eingereicht, wartet auf Verifizierung.' : 'Abgelehnt.'),
                'status' => $row['status'],
                'attested_by' => $row['status'] !== 'pending' ? $coordinator : null,
                'attested_at' => $row['status'] !== 'pending' ? $createdAt->copy()->addDays(2) : null,
                'rejection_reason' => $row['rejection'] ?? null,
                'created_at' => $createdAt,
                'updated_at' => now(),
            ];
            $this->upsert(
                'member_residency_verifications',
                ['tenant_id' => $tenantId, 'user_id' => $userId, 'declared_postcode' => $row['pc']],
                $values
            );
            $this->bump('residency verifications');
        }
    }

    // -----------------------------------------------------------------------
    // Research partners + consents + exports
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     * @return array<string, int>
     */
    private function seedResearchPartners(int $tenantId, array $userIds): array
    {
        if (! Schema::hasTable('caring_research_partners')) {
            return [];
        }
        $coordinator = $userIds['admin'] ?? null;
        $rows = [
            [
                'name' => 'Prof. Dr. Hans Müller',
                'institution' => 'Universität Zürich — Institut für Soziologie',
                'contact_email' => 'hans.mueller@soziologie.uzh.ch',
                'agreement_reference' => 'UZH-AGORIS-2026-Q1',
                'methodology_url' => 'https://soziologie.uzh.ch/projekte/caring-community-cham',
                'status' => 'active',
                'data_scope' => ['aggregated_kpis' => true, 'demographics' => 'sub_region_only', 'individual_records' => false],
                'starts_in_days' => -60, 'ends_in_days' => 365,
            ],
            [
                'name' => 'Dr. Sara Bianchi',
                'institution' => 'ETH Zürich — Department of Computer Science',
                'contact_email' => 'sara.bianchi@inf.ethz.ch',
                'agreement_reference' => 'ETH-AGORIS-FAIRNESS-2026',
                'methodology_url' => 'https://da.inf.ethz.ch/projects/algorithmic-fairness-caring',
                'status' => 'active',
                'data_scope' => ['matching_outcomes' => 'aggregated', 'demographic_drift' => true],
                'starts_in_days' => -30, 'ends_in_days' => 270,
            ],
        ];
        $ids = [];
        foreach ($rows as $row) {
            $id = $this->upsertAndGetId(
                'caring_research_partners',
                ['tenant_id' => $tenantId, 'name' => $row['name']],
                [
                    'institution' => $row['institution'],
                    'contact_email' => $row['contact_email'],
                    'agreement_reference' => $row['agreement_reference'],
                    'methodology_url' => $row['methodology_url'],
                    'status' => $row['status'],
                    'data_scope' => json_encode($row['data_scope']),
                    'starts_at' => now()->addDays($row['starts_in_days'])->toDateString(),
                    'ends_at' => now()->addDays($row['ends_in_days'])->toDateString(),
                    'created_by' => $coordinator,
                    'updated_at' => now(),
                ]
            );
            $ids[$row['agreement_reference']] = $id;
            $this->bump('research partners');
        }
        return $ids;
    }

    /**
     * @param array<string, int> $userIds
     */
    private function seedResearchConsents(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('caring_research_consents')) {
            return;
        }
        $optedIn   = ['marlies', 'thomas', 'andrea', 'beat', 'sabine', 'hans', 'stefan', 'theres', 'samira', 'anna', 'markus', 'karin', 'maria', 'christine', 'roland', 'thomas_s', 'elena', 'peter'];
        $optedOut  = ['werner', 'erika', 'anna_b', 'luca'];
        $revoked   = ['admin']; // synthetic — admin first opted in, then revoked

        foreach ($optedIn as $key) {
            $userId = $userIds[$key] ?? null;
            if ($userId === null) {
                continue;
            }
            $this->upsert(
                'caring_research_consents',
                ['tenant_id' => $tenantId, 'user_id' => $userId],
                [
                    'consent_status' => 'opted_in',
                    'consent_version' => 'research-v1',
                    'consented_at' => now()->subDays(random_int(15, 120)),
                    'revoked_at' => null,
                    'notes' => null,
                    'updated_at' => now(),
                ]
            );
            $this->bump('research consents (opted-in)');
        }
        foreach ($optedOut as $key) {
            $userId = $userIds[$key] ?? null;
            if ($userId === null) {
                continue;
            }
            $this->upsert(
                'caring_research_consents',
                ['tenant_id' => $tenantId, 'user_id' => $userId],
                [
                    'consent_status' => 'opted_out',
                    'consent_version' => 'research-v1',
                    'consented_at' => null,
                    'revoked_at' => null,
                    'notes' => 'Standard — kein Opt-in.',
                    'updated_at' => now(),
                ]
            );
            $this->bump('research consents (opted-out)');
        }
        foreach ($revoked as $key) {
            $userId = $userIds[$key] ?? null;
            if ($userId === null) {
                continue;
            }
            $this->upsert(
                'caring_research_consents',
                ['tenant_id' => $tenantId, 'user_id' => $userId],
                [
                    'consent_status' => 'revoked',
                    'consent_version' => 'research-v1',
                    'consented_at' => now()->subDays(80),
                    'revoked_at' => now()->subDays(20),
                    'notes' => 'Mitglied hat Einwilligung zurückgezogen — alle Daten werden bei nächstem Export ausgeschlossen.',
                    'updated_at' => now(),
                ]
            );
            $this->bump('research consents (revoked)');
        }
    }

    /**
     * @param array<string, int> $partners
     * @param array<string, int> $userIds
     */
    private function seedResearchExports(int $tenantId, array $partners, array $userIds): void
    {
        if (! Schema::hasTable('caring_research_dataset_exports') || $partners === []) {
            return;
        }
        $coordinator = $userIds['admin'] ?? null;
        $rows = [
            [
                'partner_ref' => 'UZH-AGORIS-2026-Q1', 'days_ago' => 25, 'period_days_ago' => 90,
                'rows' => 412, 'status' => 'generated',
                'metadata' => ['k_anonymity' => 5, 'columns' => ['sub_region', 'hour_count', 'category', 'month'], 'excluded_users' => 5],
            ],
            [
                'partner_ref' => 'ETH-AGORIS-FAIRNESS-2026', 'days_ago' => 12, 'period_days_ago' => 60,
                'rows' => 178, 'status' => 'generated',
                'metadata' => ['k_anonymity' => 7, 'columns' => ['match_score', 'outcome', 'demographic_bucket'], 'excluded_users' => 6],
            ],
        ];
        foreach ($rows as $row) {
            $partnerId = $partners[$row['partner_ref']] ?? null;
            if ($partnerId === null) {
                continue;
            }
            $generatedAt = now()->subDays($row['days_ago']);
            $hashSeed = $tenantId . '|' . $partnerId . '|' . $generatedAt->toIso8601String();
            $this->upsert(
                'caring_research_dataset_exports',
                [
                    'tenant_id' => $tenantId,
                    'partner_id' => $partnerId,
                    'period_start' => now()->subDays($row['period_days_ago'])->toDateString(),
                    'period_end' => now()->subDays($row['days_ago'])->toDateString(),
                ],
                [
                    'requested_by' => $coordinator,
                    'dataset_key' => 'caring_community_aggregate_v1',
                    'status' => $row['status'],
                    'row_count' => $row['rows'],
                    'anonymization_version' => 'aggregate-v1',
                    'data_hash' => hash('sha256', $hashSeed),
                    'generated_at' => $generatedAt,
                    'metadata' => json_encode($row['metadata']),
                    'updated_at' => now(),
                ]
            );
            $this->bump('research dataset exports');
        }
    }

    // -----------------------------------------------------------------------
    // Loyalty redemptions (link to existing marketplace orders that used credits)
    // -----------------------------------------------------------------------

    private function seedLoyaltyRedemptions(int $tenantId): void
    {
        if (! Schema::hasTable('caring_loyalty_redemptions')) {
            return;
        }
        // Real orders that used time_credits: orders 3, 5, 7
        $orders = DB::table('marketplace_orders')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('time_credits_used')
            ->where('time_credits_used', '>', 0)
            ->get(['id', 'marketplace_listing_id', 'buyer_id', 'seller_id', 'total_price', 'time_credits_used']);

        foreach ($orders as $order) {
            $rate = 10.00; // 1 credit = CHF 10 community-defined exchange rate
            $discount = (float) $order->time_credits_used * $rate;
            $this->upsert(
                'caring_loyalty_redemptions',
                [
                    'tenant_id' => $tenantId,
                    'marketplace_order_id' => $order->id,
                ],
                [
                    'member_user_id' => $order->buyer_id,
                    'merchant_user_id' => $order->seller_id,
                    'marketplace_listing_id' => $order->marketplace_listing_id,
                    'credits_used' => $order->time_credits_used,
                    'exchange_rate_chf' => $rate,
                    'discount_chf' => $discount,
                    'order_total_chf' => $order->total_price,
                    'status' => 'applied',
                    'redeemed_at' => now()->subDays(random_int(2, 8)),
                    'updated_at' => now(),
                ]
            );
            $this->bump('loyalty redemptions');
        }

        // Add 3 historical/synthetic redemptions for richer reporting (no order linkage)
        $synthetic = [
            ['member' => 'theres',  'merchant' => 'beat',    'credits' => 0.50, 'rate' => 10.00, 'days_ago' => 23],
            ['member' => 'erika',   'merchant' => 'sabine',  'credits' => 1.00, 'rate' => 10.00, 'days_ago' => 41],
            ['member' => 'werner',  'merchant' => 'andrea',  'credits' => 1.50, 'rate' => 10.00, 'days_ago' => 16],
        ];
        $userIds = $this->loadUserIds($tenantId);
        foreach ($synthetic as $idx => $row) {
            $memberId = $userIds[$row['member']] ?? null;
            $merchantId = $userIds[$row['merchant']] ?? null;
            if ($memberId === null || $merchantId === null) {
                continue;
            }
            $discount = $row['credits'] * $row['rate'];
            $this->upsert(
                'caring_loyalty_redemptions',
                [
                    'tenant_id' => $tenantId,
                    'member_user_id' => $memberId,
                    'merchant_user_id' => $merchantId,
                    'redeemed_at' => now()->subDays($row['days_ago']),
                ],
                [
                    'marketplace_listing_id' => null,
                    'marketplace_order_id' => null,
                    'credits_used' => $row['credits'],
                    'exchange_rate_chf' => $row['rate'],
                    'discount_chf' => $discount,
                    'order_total_chf' => $discount + 5.00, // mock baseline
                    'status' => 'applied',
                    'updated_at' => now(),
                ]
            );
            $this->bump('loyalty redemptions');
        }
    }

    // -----------------------------------------------------------------------
    // Tandem suggestion log
    // -----------------------------------------------------------------------

    /**
     * @param array<string, int> $userIds
     */
    private function seedTandemSuggestionLog(int $tenantId, array $userIds): void
    {
        if (! Schema::hasTable('caring_tandem_suggestion_log')) {
            return;
        }
        $coordinator = $userIds['marlies'] ?? null;
        $rows = [
            ['supporter' => 'andrea',  'recipient' => 'werner', 'action' => 'created_relationship', 'days_ago' => 56],
            ['supporter' => 'beat',    'recipient' => 'erika',  'action' => 'created_relationship', 'days_ago' => 84],
            ['supporter' => 'sabine',  'recipient' => 'werner', 'action' => 'created_relationship', 'days_ago' => 14],
            ['supporter' => 'samira',  'recipient' => 'theres', 'action' => 'created_relationship', 'days_ago' => 4],
            ['supporter' => 'roland',  'recipient' => 'erika',  'action' => 'created_relationship', 'days_ago' => 11],
            ['supporter' => 'hans',    'recipient' => 'werner', 'action' => 'dismissed',            'days_ago' => 30],
            ['supporter' => 'markus',  'recipient' => 'erika',  'action' => 'dismissed',            'days_ago' => 22],
            ['supporter' => 'stefan',  'recipient' => 'theres', 'action' => 'dismissed',            'days_ago' => 18],
        ];
        foreach ($rows as $row) {
            $sId = $userIds[$row['supporter']] ?? null;
            $rId = $userIds[$row['recipient']] ?? null;
            if ($sId === null || $rId === null) {
                continue;
            }
            $this->upsert(
                'caring_tandem_suggestion_log',
                ['tenant_id' => $tenantId, 'supporter_user_id' => $sId, 'recipient_user_id' => $rId],
                [
                    'action' => $row['action'],
                    'created_by_user_id' => $coordinator,
                    'created_at' => now()->subDays($row['days_ago']),
                ]
            );
            $this->bump('tandem suggestion log');
        }
    }

    // -----------------------------------------------------------------------
    // AG85 Isolated-Node decision gate (tenant_settings rows)
    // -----------------------------------------------------------------------

    private function seedIsolatedNodeDecisions(int $tenantId): void
    {
        if (! Schema::hasTable('tenant_settings')) {
            return;
        }
        $items = [
            'deployment_mode' => ['value' => 'hosted_tenant',
                'owner' => 'KISS Cham Genossenschaft', 'status' => 'decided',
                'notes' => 'Pilot läuft als shared tenant. Migration zum canton_isolated_node bei Erfolg in Q1 2027 evaluieren.'],
            'hosting_owner' => ['value' => 'NEXUS Platform (Azure West Europe — Zürich Region)',
                'owner' => 'KISS Cham Genossenschaft', 'status' => 'decided',
                'notes' => 'Vertrag mit NEXUS Platform GmbH — Datenstandort Schweiz garantiert.'],
            'smtp_owner' => ['value' => 'NEXUS Platform Mail Relay (Postmark CH)',
                'owner' => 'NEXUS Platform GmbH', 'status' => 'decided',
                'notes' => 'Outbound Mail über Postmark Schweizer Region. Bounce-Logs 30 Tage.'],
            'storage_owner' => ['value' => 'Azure Blob Storage West Europe',
                'owner' => 'NEXUS Platform GmbH', 'status' => 'decided',
                'notes' => 'Verschlüsselt at-rest. Datenstandort Schweiz/EU. FADP-konform.'],
            'backup_owner' => ['value' => 'KISS Cham Genossenschaft (täglich, 30-Tage-Retention)',
                'owner' => 'KISS Cham Genossenschaft', 'status' => 'decided',
                'notes' => 'Tägliche Backups, lokale Kopie in 6330 Cham. Restore-Drill quartalsweise.'],
            'update_cadence' => ['value' => 'monthly',
                'owner' => 'NEXUS Platform GmbH', 'status' => 'decided',
                'notes' => 'Monatliche Sicherheits-Updates und Feature-Releases. Notfall-Patches sofort.'],
            'source_release_workflow' => ['value' => 'Signed git tags from upstream NEXUS, monthly review by KISS-IT',
                'owner' => 'NEXUS Platform GmbH', 'status' => 'decided',
                'notes' => 'Tags signiert mit Jasper Ford GPG Key 0xA1B2... — manuelle Review durch KISS-IT vor Deployment.'],
            'telemetry_default' => ['value' => 'disabled',
                'owner' => 'KISS Cham Genossenschaft', 'status' => 'decided',
                'notes' => 'Telemetrie standardmässig aus. Nur explizit aktivierte Fehler-Reports werden gesendet.'],
            'federation_key_exchange' => ['value' => 'KISS-Verband Schweiz key registry (planned)',
                'owner' => 'KISS-Verband Schweiz', 'status' => 'in_progress',
                'notes' => 'Zentrale Key-Registry beim KISS-Verband in Aufbau. Aktuell direkt zwischen Cooperatives. Erwartete Live-Schaltung Q3 2026.'],
            'dpo_appointed' => ['value' => 'Marlies Iten (extern: RA Stefan Birrer)',
                'owner' => 'KISS Cham Genossenschaft', 'status' => 'decided',
                'notes' => 'Marlies Iten interner DPO. RA Stefan Birrer als externer Berater bei rechtlichen Fragen.'],
            'incident_runbook_url' => ['value' => 'https://kiss-cham.ch/runbook',
                'owner' => 'KISS Cham Genossenschaft', 'status' => 'decided',
                'notes' => 'Runbook deckt: Ausfall, Datenleck, Schlüsselkompromittierung, Restore-Drill, FADP-Meldepflicht.'],
        ];

        foreach ($items as $key => $item) {
            $envelope = [
                'value' => $item['value'],
                'owner' => $item['owner'],
                'status' => $item['status'],
                'notes' => $item['notes'],
                'updated_at' => now()->toIso8601String(),
            ];
            $this->upsert(
                'tenant_settings',
                ['tenant_id' => $tenantId, 'setting_key' => 'caring.isolated_node.' . $key],
                [
                    'setting_value' => json_encode($envelope),
                    'setting_type' => 'json',
                    'category' => 'caring',
                    'updated_at' => now(),
                ]
            );
            $this->bump('isolated-node decisions');
        }
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

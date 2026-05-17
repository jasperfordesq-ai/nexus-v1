<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pull SendGrid's suppression lists (bounces / blocks / invalid emails /
 * spam reports) into the local `email_suppression` cache. The Mailer
 * checks this table before sending and refuses to mail an address that
 * SendGrid has already told us is bad — saves quota, protects the sender
 * reputation, and surfaces invalid member addresses in the admin UI.
 *
 * Scheduled hourly from bootstrap/app.php. Idempotent — re-running pulls
 * the same rows and the unique key (email, reason) deduplicates.
 */
class SyncSendGridSuppressions extends Command
{
    protected $signature = 'sendgrid:sync-suppressions {--max=500 : maximum rows to pull per list}';

    protected $description = 'Sync SendGrid suppression lists into the local email_suppression cache';

    public function handle(): int
    {
        $apiKey = config('mail.sendgrid.api_key');
        if (empty($apiKey)) {
            $this->warn('SENDGRID_API_KEY not configured — nothing to sync.');
            return self::SUCCESS;
        }

        $max = (int) $this->option('max');
        $kinds = [
            'bounces'         => 'bounce',
            'blocks'          => 'block',
            'invalid_emails'  => 'invalid',
            'spam_reports'    => 'spam_report',
        ];

        $totalInserted = 0;
        foreach ($kinds as $endpoint => $reason) {
            try {
                $resp = Http::withToken($apiKey)
                    ->acceptJson()
                    ->timeout(15)
                    ->get("https://api.sendgrid.com/v3/suppression/{$endpoint}", [
                        'limit' => $max,
                    ]);
                if (!$resp->ok()) {
                    $this->warn("[{$endpoint}] HTTP {$resp->status()}");
                    continue;
                }
                $rows = $resp->json();
                if (!is_array($rows)) {
                    continue;
                }

                $inserted = 0;
                foreach ($rows as $row) {
                    $email = (string) ($row['email'] ?? '');
                    $created = isset($row['created']) ? (int) $row['created'] : time();
                    if ($email === '') {
                        continue;
                    }
                    try {
                        DB::table('email_suppression')->updateOrInsert(
                            ['email' => $email, 'reason' => $reason],
                            [
                                'detail'        => isset($row['reason']) ? mb_substr((string) $row['reason'], 0, 500) : null,
                                'suppressed_at' => date('Y-m-d H:i:s', $created),
                                'updated_at'    => now(),
                                'created_at'    => now(),
                            ]
                        );
                        $inserted++;
                    } catch (\Throwable $e) {
                        // Per-row insert can race with another sync — skip and move on.
                    }
                }
                $this->info("[{$endpoint}] synced {$inserted} rows");
                $totalInserted += $inserted;
            } catch (\Throwable $e) {
                Log::warning("SendGrid suppression sync failed for {$endpoint}: " . $e->getMessage());
            }
        }

        $this->info("Done. Total upserts: {$totalInserted}");
        return self::SUCCESS;
    }
}

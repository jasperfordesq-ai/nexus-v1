<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Scan recent `email_log` rows with `status=failed` and replay the SendGrid
 * event-log to recover from transient outages (SendGrid 5xx, brief network
 * blips).
 *
 * Strategy: this command intentionally does NOT re-send the email body
 * itself — the original body isn't stored in email_log (only the subject is)
 * and replaying user-facing emails could surprise members ("why did I get
 * the welcome email twice?"). Instead it queries SendGrid's
 * `/v3/messages` activity feed for any recipient address that we logged as
 * `failed`; if SendGrid actually delivered the email despite our 5xx
 * receipt, we update our log to `delivered` so the audit trail is correct.
 *
 * For genuine transient failures where SendGrid also didn't accept the
 * payload, the per-feature caller path (welcome listener retry, etc.)
 * remains the right place to retry the actual send. This command's job
 * is reconciliation only.
 *
 * Scheduled every 15 minutes from bootstrap/app.php.
 */
class RetryTransientEmailFailures extends Command
{
    protected $signature = 'emails:reconcile-transient-failures
                            {--minutes=60 : look back this many minutes}
                            {--limit=200 : maximum rows to reconcile per run}
                            {--dry-run : do not write reconciliation updates}';

    protected $description = 'Reconcile recent failed email_log rows with SendGrid activity to recover transient false-failures';

    public function handle(): int
    {
        $apiKey = config('mail.sendgrid.api_key');
        if (empty($apiKey)) {
            $this->warn('SENDGRID_API_KEY not configured — nothing to reconcile.');
            return self::SUCCESS;
        }

        $minutes = max(5, (int) $this->option('minutes'));
        $limit   = max(1, (int) $this->option('limit'));
        $dryRun  = (bool) $this->option('dry-run');

        $cutoff = now()->subMinutes($minutes);

        // Pick recent failed rows we haven't already reconciled. The
        // suppressed/bounced rows are real failures so we don't touch them.
        $rows = DB::table('email_log')
            ->where('status', 'failed')
            ->where('provider', 'sendgrid')
            ->whereNotNull('provider_message_id')
            ->where('provider_message_id', '<>', '')
            ->where('created_at', '>=', $cutoff)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'recipient_email', 'subject', 'provider_message_id', 'created_at', 'sent_at']);

        if ($rows->isEmpty()) {
            $this->info('No failed rows in the window — nothing to do.');
            return self::SUCCESS;
        }

        $reconciled = 0;
        $stillFailed = 0;

        foreach ($rows as $row) {
            try {
                $hit = $this->lookupActivity($apiKey, $row->recipient_email, (string) $row->provider_message_id, (string) $row->created_at);
                if ($hit !== null) {
                    $reconciled++;
                    $this->line("  ✓ {$row->recipient_email} — SendGrid reports {$hit['status']} @ {$hit['ts']}");
                    if (!$dryRun) {
                        DB::table('email_log')->where('id', $row->id)->update([
                            'status'              => $hit['status'] === 'delivered' ? 'delivered' : 'sent',
                            'provider_message_id' => $hit['msg_id'] ?? null,
                            'delivered_at'        => $hit['status'] === 'delivered' ? $hit['ts'] : null,
                            'error'               => $hit['status'] === 'delivered' ? null : null,
                            'updated_at'          => now(),
                        ]);
                    }
                } else {
                    $stillFailed++;
                }
            } catch (\Throwable $e) {
                Log::warning('emails:reconcile-transient-failures lookup failed', [
                    'row_id' => $row->id,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf(
            'Done. Reconciled %d, still failed %d (window=%dm, limit=%d%s).',
            $reconciled,
            $stillFailed,
            $minutes,
            $limit,
            $dryRun ? ', dry-run' : ''
        ));

        return self::SUCCESS;
    }

    /**
     * Query SendGrid's activity API for the exact provider message id and return
     *   ['status' => 'delivered'|'processed'|'bounce'|..., 'ts' => 'YYYY-MM-DD HH:MM:SS', 'msg_id' => '...']
     * for the most recent hit after $sinceIso, or null if SendGrid has no
     * record (genuine failure).
     */
    private function lookupActivity(string $apiKey, string $email, string $messageId, string $sinceIso): ?array
    {
        // SendGrid messages search endpoint uses a simple query DSL.
        // We narrow to the provider message id captured at send time. Recipient
        // alone is not evidence: the same address can receive many emails from
        // different tenants and categories within the reconciliation window.
        $query = sprintf('msg_id = "%s"', $messageId);

        $resp = \Illuminate\Support\Facades\Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(15)
            ->get('https://api.sendgrid.com/v3/messages', [
                'query' => $query,
                'limit' => 10,
            ]);

        if (!$resp->ok()) {
            return null;
        }
        $body = $resp->json();
        if (!is_array($body) || empty($body['messages'])) {
            return null;
        }

        foreach ($body['messages'] as $msg) {
            $when = $msg['last_event_time'] ?? null;
            if (!$when) continue;
            // SendGrid timestamps are ISO 8601 UTC. Strtotime handles that.
            if (strtotime((string) $when) < strtotime($sinceIso)) {
                continue;
            }
            $status = (string) ($msg['status'] ?? '');
            $hitMessageId = (string) ($msg['msg_id'] ?? '');
            $hitEmail = (string) ($msg['to_email'] ?? '');
            if ($hitMessageId !== $messageId || strcasecmp($hitEmail, $email) !== 0) {
                continue;
            }
            // Only treat 'delivered' or 'processed' as a successful
            // reconciliation. Bounces / dropped are confirmed failures and
            // our row's `failed` status is correct.
            if (in_array($status, ['delivered', 'processed'], true)) {
                return [
                    'status' => $status,
                    'ts'     => date('Y-m-d H:i:s', strtotime((string) $when)),
                    'msg_id' => $msg['msg_id'] ?? null,
                ];
            }
        }

        return null;
    }
}

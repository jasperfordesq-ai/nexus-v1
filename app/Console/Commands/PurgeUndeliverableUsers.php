<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DisposableEmailService;
use App\Services\MxRecordValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Scan unverified users and surface (or purge) those whose email address
 * cannot be deliverable.
 *
 *   php artisan users:purge-undeliverable [--dry-run] [--soft] [--hard] [--since=90days] [--tenant=N]
 *
 * Re-uses the exact validators the registration form uses, so the rule is
 * identical to what we reject at signup. Extends the registration check
 * with the RFC-reserved-TLD list (example.com / example.test / *.test /
 * *.localhost / *.invalid) — the registration MxRecordValidator only
 * rejects `.invalid`, missing the other reserved spaces. That's how
 * `testing@example.com` slipped through during the cyber attack on
 * 2026-05-14 → 2026-05-16.
 *
 * Safety constraints:
 *   - Only considers `email_verified_at IS NULL` users. A verified user
 *     proved their email works at some point; we will not delete them
 *     even if their domain is briefly broken now.
 *   - Skips role in (god / super_admin) and is_super_admin = 1 — admin
 *     accounts are off-limits regardless of email domain.
 *   - Defaults to --dry-run. --soft sets `deleted_at = NOW()`. --hard
 *     issues a real DELETE.
 *   - `--since` defaults to 90 days so we don't touch ancient signups.
 *   - Per-row try/catch so one bad row never aborts the run.
 */
class PurgeUndeliverableUsers extends Command
{
    protected $signature = 'users:purge-undeliverable
                            {--dry-run : list candidates without changing anything (default)}
                            {--soft : soft-delete matched users (set deleted_at = NOW())}
                            {--hard : DELETE rows from the users table}
                            {--since=90days : ignore users created before this point (Ndays or YYYY-MM-DD)}
                            {--tenant= : restrict to a single tenant id}
                            {--limit=1000 : hard ceiling on rows considered}';

    protected $description = 'Find unverified members with undeliverable email domains and (optionally) purge them';

    /**
     * RFC 6761 + RFC 2606 reserved domains. None of these can receive real
     * email. The registration MxRecordValidator only checks `.invalid`;
     * the rest are explicitly listed here so the cleanup is exhaustive.
     */
    private const RESERVED_DOMAINS = [
        'example.com',
        'example.net',
        'example.org',
        'localhost',
    ];

    private const RESERVED_TLDS = [
        '.test',
        '.example',
        '.invalid',
        '.localhost',
    ];

    public function __construct(
        private readonly DisposableEmailService $disposable,
        private readonly MxRecordValidator $mx,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDry  = (bool) $this->option('dry-run') || (!$this->option('soft') && !$this->option('hard'));
        $isSoft = (bool) $this->option('soft');
        $isHard = (bool) $this->option('hard');
        $tenant = $this->option('tenant');
        $limit  = max(1, (int) $this->option('limit'));
        $since  = $this->resolveSince((string) $this->option('since'));

        if ($isSoft && $isHard) {
            $this->error('--soft and --hard are mutually exclusive.');
            return self::FAILURE;
        }

        $mode = $isHard ? 'HARD-DELETE' : ($isSoft ? 'SOFT-DELETE' : 'DRY');
        $this->info(sprintf(
            'Mode: %s — since %s%s, limit %d',
            $mode,
            $since,
            $tenant ? ", tenant {$tenant}" : '',
            $limit
        ));

        // Pull the candidate cohort: unverified, recent, not admin, not deleted.
        $q = DB::table('users')
            ->whereNull('email_verified_at')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $since)
            ->whereNotIn('role', ['god', 'super_admin'])
            ->where(function ($q) {
                $q->whereNull('is_super_admin')->orWhere('is_super_admin', 0);
            })
            ->orderBy('id')
            ->limit($limit);
        if ($tenant !== null) {
            $q->where('tenant_id', (int) $tenant);
        }

        $candidates = $q->get(['id', 'tenant_id', 'email', 'first_name', 'created_at', 'role']);

        if ($candidates->isEmpty()) {
            $this->info('No unverified users in scope — nothing to do.');
            return self::SUCCESS;
        }

        $matches = [];
        foreach ($candidates as $u) {
            $reason = $this->classifyUndeliverable((string) ($u->email ?? ''));
            if ($reason !== null) {
                $matches[] = ['user' => $u, 'reason' => $reason];
            }
        }

        if (empty($matches)) {
            $this->info(sprintf(
                'Scanned %d candidates — none failed the deliverability check.',
                $candidates->count()
            ));
            return self::SUCCESS;
        }

        $this->table(
            ['id', 'tenant_id', 'email', 'created_at', 'reason'],
            array_map(fn ($m) => [
                $m['user']->id,
                $m['user']->tenant_id,
                $m['user']->email,
                $m['user']->created_at,
                $m['reason'],
            ], $matches)
        );

        if ($isDry) {
            $this->info(sprintf(
                'Dry run — %d matched. Re-run with --soft or --hard to act.',
                count($matches)
            ));
            return self::SUCCESS;
        }

        $deleted = 0;
        $failed  = 0;
        foreach ($matches as $m) {
            $u = $m['user'];
            try {
                if ($isHard) {
                    DB::table('users')->where('id', $u->id)->delete();
                } else {
                    DB::table('users')->where('id', $u->id)->update([
                        'deleted_at' => now(),
                        'anonymized_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                Log::info('PurgeUndeliverableUsers: ' . ($isHard ? 'hard-deleted' : 'soft-deleted'), [
                    'user_id'  => $u->id,
                    'tenant_id'=> $u->tenant_id,
                    'reason'   => $m['reason'],
                ]);
                $deleted++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  ✗ user {$u->id} ({$u->email}): " . $e->getMessage());
            }
        }

        $this->info(sprintf(
            'Done. %s: %d  Failed: %d',
            $isHard ? 'Hard-deleted' : 'Soft-deleted',
            $deleted,
            $failed
        ));
        return self::SUCCESS;
    }

    /**
     * Returns a human-readable reason string if the email is undeliverable,
     * or null if it passes every check. Reasons (priority order):
     *
     *   reserved_domain    — exact-match RFC 2606/6761 (example.com, etc.)
     *   reserved_tld       — ends with .test / .example / .invalid / .localhost
     *   disposable         — known throwaway provider (DisposableEmailService)
     *   no_mx_no_a         — MxRecordValidator fail
     *   malformed          — no @ or empty local/domain
     */
    private function classifyUndeliverable(string $email): ?string
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return 'malformed';
        }
        $atPos = strrpos($email, '@');
        if ($atPos === false || $atPos === 0 || $atPos === strlen($email) - 1) {
            return 'malformed';
        }
        $domain = substr($email, $atPos + 1);
        if ($domain === '') {
            return 'malformed';
        }

        if (in_array($domain, self::RESERVED_DOMAINS, true)) {
            return 'reserved_domain (' . $domain . ')';
        }
        foreach (self::RESERVED_TLDS as $tld) {
            if (str_ends_with($domain, $tld)) {
                return 'reserved_tld (' . $tld . ')';
            }
        }

        if ($this->disposable->isDisposable($email)) {
            return 'disposable_provider';
        }

        if (!$this->mx->isResolvable($email)) {
            return 'no_mx_no_a';
        }

        return null;
    }

    private function resolveSince(string $raw): string
    {
        if (preg_match('/^(\d+)\s*days?$/i', $raw, $m)) {
            return date('Y-m-d H:i:s', time() - ((int) $m[1] * 86400));
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw . ' 00:00:00';
        }
        return date('Y-m-d H:i:s', time() - 90 * 86400);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Mailer;
use App\Core\TenantContext;
use App\Core\EmailTemplateBuilder;
use App\I18n\LocaleContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-shot recovery command for the production users who registered while
 * the TenantContext leak / Mailer bypass bugs were live and never received
 * their welcome / verification email.
 *
 *   php artisan emails:resend-stuck-activations [--dry-run] [--tenant=N] [--since=YYYY-MM-DD] [--limit=200]
 *
 * Defaults to a dry-run so you can sanity-check the recipient list before
 * actually sending. `--since` defaults to 60 days ago so we don't email
 * accounts that were abandoned long before any of this. Tenant filter is
 * optional. Hard ceiling on `--limit` (default 200) so a typo can't blast
 * thousands of mails.
 *
 * Re-uses the canonical Mailer path so:
 *   - SendGrid is used (the default driver in prod)
 *   - the email_log audit trail records every send
 *   - the suppression cache skips known-bad addresses
 *   - the unsubscribe header is auto-attached
 *
 * Idempotent: each successful resend regenerates a fresh token and overwrites
 * any existing pending tokens in `email_verification_tokens` so the new link
 * supersedes the old. Failed/suppressed sends preserve the previous token.
 */
class ResendStuckVerificationEmails extends Command
{
    protected $signature = 'emails:resend-stuck-activations
                            {--dry-run : list the recipients without sending}
                            {--tenant= : limit to a single tenant id}
                            {--since=60days : only users registered after this point (ISO date or Ndays)}
                            {--limit=200 : safety ceiling on how many emails to send in one run}';

    protected $description = 'Resend welcome / verification emails to unverified members who never received their original activation email';

    private const TOKEN_TTL_SECONDS = 86400; // 24h

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $limit    = max(1, (int) $this->option('limit'));
        $tenant   = $this->option('tenant');
        $since    = $this->resolveSince((string) $this->option('since'));

        $this->info(sprintf(
            'Resend run (%s) — since %s%s, limit %d',
            $isDryRun ? 'DRY' : 'LIVE',
            $since,
            $tenant ? ", tenant {$tenant}" : '',
            $limit
        ));

        $q = DB::table('users')
            ->whereNull('email_verified_at')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $since)
            ->whereIn('status', ['pending', 'active'])
            ->orderBy('created_at', 'asc')
            ->limit($limit);
        if ($tenant !== null) {
            $q->where('tenant_id', (int) $tenant);
        }

        $users = $q->get(['id', 'tenant_id', 'email', 'first_name', 'name', 'preferred_language', 'status', 'created_at']);

        if ($users->isEmpty()) {
            $this->info('No matching users — nothing to do.');
            return self::SUCCESS;
        }

        $this->table(
            ['id', 'tenant_id', 'email', 'created_at'],
            $users->map(fn ($u) => [$u->id, $u->tenant_id, $u->email, $u->created_at])->all()
        );

        if ($isDryRun) {
            $this->info('Dry run — no emails sent. Re-run without --dry-run to actually send.');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($users as $u) {
            try {
                TenantContext::setById((int) $u->tenant_id);
                $ok = $this->sendOne($u);
                if ($ok) {
                    $sent++;
                    $this->line("  ✓ {$u->email}");
                } else {
                    $skipped++;
                    $this->line("  ~ {$u->email} (skipped or returned false)");
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('ResendStuckVerificationEmails failed', [
                    'user_id' => $u->id,
                    'error'   => $e->getMessage(),
                ]);
                $this->error("  ✗ {$u->email}: " . $e->getMessage());
            } finally {
                TenantContext::reset();
            }
        }

        $this->info("Done. Sent: {$sent}  Skipped: {$skipped}  Failed: {$failed}");
        return self::SUCCESS;
    }

    private function sendOne(object $user): bool
    {
        $tenantId = (int) $user->tenant_id;

        $this->ensureVerificationTokenTableExists();

        // Fresh token: supersede any older pending token only after send acceptance.
        $token = bin2hex(random_bytes(32));
        $hashed = password_hash($token, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);

        $appUrl  = TenantContext::getFrontendUrl();
        $basePath = TenantContext::getSlugPrefix();
        $verifyUrl = $appUrl . $basePath . '/verify-email?token=' . $token;

        $tenantName = (string) (DB::table('tenants')->where('id', $tenantId)->value('name') ?? 'Project NEXUS');

        $sent = LocaleContext::withLocale(
            $user->preferred_language ?? null,
            function () use ($user, $tenantName, $verifyUrl, $tenantId) {
                $firstName = (string) ($user->first_name ?? '');
                $greeting = $firstName !== ''
                    ? __('emails_misc.auth.verify_email_greeting', [
                        'name' => htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'),
                        'community' => $tenantName,
                    ])
                    : __('emails_misc.auth.verify_email_greeting_fallback');

                $html = EmailTemplateBuilder::make()
                    ->title(__('emails_misc.auth.verify_email_title'))
                    ->greeting($greeting)
                    ->paragraph(__('emails_misc.auth.verify_email_body'))
                    ->paragraph(__('emails_misc.auth.verify_email_ignore'))
                    ->button(__('emails_misc.auth.verify_email_cta'), $verifyUrl)
                    ->render();

                $subject = __('emails_misc.auth.verify_email_subject', ['community' => $tenantName]);
                return \App\Services\EmailDispatchService::sendRaw($user->email, $subject, $html, null, null, null, 'email_verification', ['tenant_id' => $tenantId]);
            }
        );

        if (!$sent) {
            return false;
        }

        DB::table('email_verification_tokens')
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->delete();
        DB::table('email_verification_tokens')->insert([
            'user_id'    => $user->id,
            'tenant_id'  => $tenantId,
            'token'      => $hashed,
            'expires_at' => $expiresAt,
            'created_at' => now(),
        ]);

        return true;
    }

    private function ensureVerificationTokenTableExists(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `tenant_id` INT(11) NOT NULL,
                `token` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `expires_at` TIMESTAMP NOT NULL,
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_tenant_id` (`tenant_id`),
                INDEX `idx_tenant_user` (`tenant_id`, `user_id`),
                INDEX `idx_expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function resolveSince(string $raw): string
    {
        if (preg_match('/^(\d+)\s*days?$/i', $raw, $m)) {
            return date('Y-m-d H:i:s', time() - ((int) $m[1] * 86400));
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw . ' 00:00:00';
        }
        // Fallback: 60 days ago.
        return date('Y-m-d H:i:s', time() - 60 * 86400);
    }
}

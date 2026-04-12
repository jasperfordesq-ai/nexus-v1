<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\TenantContext;
use App\Http\Controllers\Api\OptionalIdentityVerificationController;
use App\Services\Identity\IdentityProviderRegistry;
use App\Services\Identity\IdentityVerificationSessionService;
use App\Services\MemberVerificationBadgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fallback-poll stuck Stripe Identity verification sessions.
 *
 * Stripe Identity webhooks are unreliable (per project notes). The in-app
 * polling on the verification page only runs when the user revisits; users
 * who pay and close the tab can get stuck in 'pending'/'processing' forever.
 *
 * This command polls Stripe directly for any session that hasn't been
 * touched in the last N minutes and applies the same status transitions
 * (and side effects: name/DOB match, badge grant) that the in-app poll
 * and webhook handler do.
 *
 * Scheduled hourly via bootstrap/app.php.
 */
class PollStuckIdentityVerifications extends Command
{
    protected $signature = 'nexus:identity:poll-stuck {--minutes=5}';
    protected $description = 'Poll Stripe for identity verification sessions that have been stuck (webhook-less) for N minutes';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        if ($minutes < 0) {
            $minutes = 0;
        }

        $rows = DB::select(
            "SELECT id, tenant_id, user_id, status, provider_slug, provider_session_id
               FROM identity_verification_sessions
              WHERE status IN ('created', 'started', 'processing')
                AND provider_session_id IS NOT NULL
                AND updated_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
                AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
              ORDER BY updated_at ASC
              LIMIT 500",
            [$minutes]
        );

        $polled  = 0;
        $updated = 0;
        $errors  = 0;

        foreach ($rows as $row) {
            $polled++;
            $sessionId        = (int) $row->id;
            $tenantId         = (int) $row->tenant_id;
            $userId           = (int) $row->user_id;
            $prevStatus       = (string) $row->status;
            $providerSlug     = (string) ($row->provider_slug ?? 'stripe_identity');
            $providerSessId   = (string) $row->provider_session_id;

            try {
                TenantContext::setById($tenantId);

                $provider     = IdentityProviderRegistry::get($providerSlug);
                $stripeStatus = $provider->getSessionStatus($providerSessId);
                $mapped       = $stripeStatus['status'] ?? null;

                if ($mapped === 'passed' && $prevStatus !== 'passed') {
                    // Mirror the in-app poll: check name/DOB match before granting badge.
                    $mismatch = self::invokePrivate(
                        OptionalIdentityVerificationController::class,
                        'checkNameDobMismatch',
                        [$userId, $tenantId, $stripeStatus]
                    );

                    if ($mismatch) {
                        IdentityVerificationSessionService::updateStatus(
                            $sessionId, 'failed', null, null, (string) $mismatch
                        );
                        $updated++;
                        Log::info('[poll-stuck] session failed (name/DOB mismatch)', [
                            'session_id' => $sessionId, 'tenant_id' => $tenantId, 'user_id' => $userId,
                        ]);
                    } else {
                        IdentityVerificationSessionService::updateStatus(
                            $sessionId, 'passed', null, null, null
                        );

                        // Grant badge (idempotent inside helper).
                        $badgeService = app(MemberVerificationBadgeService::class);
                        $badges       = $badgeService->getUserBadges($userId);
                        $hasIdBadge   = collect($badges)->contains(fn($b) => ($b['badge_type'] ?? null) === 'id_verified');
                        if (!$hasIdBadge) {
                            OptionalIdentityVerificationController::grantIdVerifiedBadge($userId, $tenantId);
                        }

                        $updated++;
                        Log::info('[poll-stuck] session passed', [
                            'session_id' => $sessionId, 'tenant_id' => $tenantId, 'user_id' => $userId,
                        ]);
                    }
                } elseif ($mapped === 'failed') {
                    IdentityVerificationSessionService::updateStatus(
                        $sessionId,
                        'failed',
                        null,
                        null,
                        (string) ($stripeStatus['failure_reason'] ?? 'Verification failed')
                    );
                    $updated++;
                    Log::info('[poll-stuck] session failed', [
                        'session_id' => $sessionId, 'tenant_id' => $tenantId, 'user_id' => $userId,
                        'reason' => $stripeStatus['failure_reason'] ?? null,
                    ]);
                } elseif ($mapped === 'cancelled' && $prevStatus !== 'cancelled') {
                    IdentityVerificationSessionService::updateStatus(
                        $sessionId, 'cancelled', null, null, null
                    );
                    $updated++;
                } elseif ($mapped !== null && $mapped !== $prevStatus) {
                    // Intermediate transition (e.g. created -> processing). Still bump status
                    // so updated_at moves forward and we don't re-poll immediately.
                    IdentityVerificationSessionService::updateStatus(
                        $sessionId, $mapped, null, null, null
                    );
                    $updated++;
                } else {
                    // Touch updated_at so we don't spin on the same unchanged session every hour.
                    DB::statement(
                        "UPDATE identity_verification_sessions
                            SET updated_at = CURRENT_TIMESTAMP
                          WHERE id = ?",
                        [$sessionId]
                    );
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('[poll-stuck] poll failed for session', [
                    'session_id' => $sessionId,
                    'tenant_id'  => $tenantId,
                    'error'      => $e->getMessage(),
                ]);
                // continue to next session
            }
        }

        $now = date('Y-m-d H:i:s');
        $this->info("[{$now}] Polled {$polled} sessions, {$updated} updated, {$errors} errors");

        return self::SUCCESS;
    }

    /**
     * Invoke a private/protected static method on a class via reflection.
     * Needed because OptionalIdentityVerificationController::checkNameDobMismatch
     * is private but we need to apply the exact same matching logic here.
     */
    private static function invokePrivate(string $class, string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod($class, $method);
        $ref->setAccessible(true);
        return $ref->invoke(null, ...$args);
    }
}

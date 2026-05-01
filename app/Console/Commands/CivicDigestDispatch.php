<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Mail\CivicDigestMail;
use App\Services\CaringCommunity\CivicDigestService;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * AG90 — Personalised Civic Digest scheduled dispatch.
 *
 * Iterates tenants where caring_community is enabled, finds members whose
 * digest cadence matches the requested cadence (with tenant default fallback)
 * and sends one email + one push (if push subscriptions exist).
 *
 * Idempotent: tracks last_sent_at per-user under the existing user-prefs
 * envelope; skips users whose digest was sent within the cadence window
 * (24h for daily, 168h for weekly). Safe to run multiple times per day.
 *
 * Empty digests are skipped silently — we never email "nothing to report".
 */
class CivicDigestDispatch extends Command
{
    protected $signature = 'caring:civic-digest-dispatch '
        . '{--cadence=daily : daily|weekly} '
        . '{--tenant= : Restrict to one tenant ID (for testing)} '
        . '{--limit=50 : Max items per recipient}';

    protected $description = 'Dispatch AG90 civic digest emails + push to opted-in members';

    public function __construct(
        private readonly CivicDigestService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cadenceArg = (string) $this->option('cadence');
        $cadence = in_array($cadenceArg, ['daily', 'weekly'], true) ? $cadenceArg : 'daily';
        $windowSeconds = $cadence === 'weekly' ? 168 * 3600 : 24 * 3600;

        $tenantOption = $this->option('tenant');
        $limitOption = (int) $this->option('limit');
        $limit = $limitOption > 0 && $limitOption <= 100 ? $limitOption : 50;

        $tenantIds = ($tenantOption !== null && $tenantOption !== '')
            ? [(int) $tenantOption]
            : DB::table('tenants')
                ->where('is_active', 1)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

        $totals = [
            'tenants_processed' => 0,
            'tenants_skipped' => 0,
            'members_notified' => 0,
            'items_delivered' => 0,
            'emails_sent' => 0,
            'push_sent' => 0,
            'tenants_failed' => 0,
        ];

        $hasPushService = class_exists(PushNotificationService::class);
        $pushService = $hasPushService ? app(PushNotificationService::class) : null;

        foreach ($tenantIds as $tenantId) {
            if ($tenantId <= 0) {
                continue;
            }

            try {
                TenantContext::setById($tenantId);
                if (! TenantContext::hasFeature('caring_community')) {
                    $totals['tenants_skipped']++;
                    $this->line("Tenant {$tenantId}: caring_community disabled — skipping");
                    continue;
                }

                $result = $this->dispatchForTenant($tenantId, $cadence, $windowSeconds, $limit, $pushService);
                $totals['tenants_processed']++;
                $totals['members_notified'] += $result['members'];
                $totals['items_delivered'] += $result['items'];
                $totals['emails_sent'] += $result['emails'];
                $totals['push_sent'] += $result['push'];

                $this->line(sprintf(
                    'Tenant %d: candidates=%d notified=%d items=%d emails=%d push=%d',
                    $tenantId,
                    $result['candidates'],
                    $result['members'],
                    $result['items'],
                    $result['emails'],
                    $result['push'],
                ));
            } catch (Throwable $e) {
                $totals['tenants_failed']++;
                Log::error('[CivicDigestDispatch] tenant failure', [
                    'tenant_id' => $tenantId,
                    'cadence' => $cadence,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Tenant {$tenantId}: failed — " . $e->getMessage());
                continue;
            }
        }

        $this->info(sprintf(
            'CivicDigestDispatch summary [cadence=%s] tenants=%d/%d members=%d items=%d emails=%d push=%s failures=%d',
            $cadence,
            $totals['tenants_processed'],
            count($tenantIds),
            $totals['members_notified'],
            $totals['items_delivered'],
            $totals['emails_sent'],
            $hasPushService ? (string) $totals['push_sent'] : 'n/a',
            $totals['tenants_failed'],
        ));

        Log::info('[CivicDigestDispatch] run complete', $totals + ['cadence' => $cadence]);

        return self::SUCCESS;
    }

    /**
     * @return array{candidates:int, members:int, items:int, emails:int, push:int}
     */
    private function dispatchForTenant(
        int $tenantId,
        string $cadence,
        int $windowSeconds,
        int $limit,
        ?PushNotificationService $pushService,
    ): array {
        $stats = ['candidates' => 0, 'members' => 0, 'items' => 0, 'emails' => 0, 'push' => 0];

        if (! Schema::hasTable('tenant_settings') || ! Schema::hasTable('users')) {
            return $stats;
        }

        $tenantDefault = $this->service->getTenantCadence($tenantId);
        $allowedSourceCount = $this->service->allowedSourceCount();

        // Pull all candidate user prefs rows for this tenant in one query
        $prefRows = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', 'like', CivicDigestService::SETTING_USER_PREFIX . '%')
            ->select(['setting_key', 'setting_value'])
            ->get();

        // Per-user prefs map keyed by user id
        $explicitCadence = []; // userId => cadence | null
        foreach ($prefRows as $row) {
            $rawKey = (string) $row->setting_key;
            $userId = (int) substr($rawKey, strlen(CivicDigestService::SETTING_USER_PREFIX));
            if ($userId <= 0) {
                continue;
            }
            $decoded = json_decode((string) $row->setting_value, true);
            if (!is_array($decoded)) {
                $explicitCadence[$userId] = null;
                continue;
            }
            $explicitCadence[$userId] = isset($decoded['cadence']) && is_string($decoded['cadence'])
                ? $decoded['cadence']
                : null;
        }

        // Determine the candidate user set:
        //  - Users with explicit cadence == requested cadence
        //  - Plus, IF tenant default == requested cadence: all active tenant
        //    users without an explicit cadence row (they inherit tenant default).
        $explicitMatchUserIds = [];
        foreach ($explicitCadence as $userId => $cad) {
            if ($cad === $cadence) {
                $explicitMatchUserIds[] = $userId;
            }
        }

        $usersQuery = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('email')
            ->whereNotNull('email_verified_at');

        // Build the WHERE clause: users in explicit-match set, OR (tenant default
        // matches AND user has no explicit row).
        $explicitUserIdsAll = array_keys($explicitCadence);
        $tenantDefaultMatches = $tenantDefault === $cadence;

        if (! $tenantDefaultMatches) {
            // Only users explicitly opted into this cadence
            if ($explicitMatchUserIds === []) {
                return $stats;
            }
            $usersQuery->whereIn('id', $explicitMatchUserIds);
        } else {
            // Users explicitly opted in OR users without any explicit row
            $usersQuery->where(function ($q) use ($explicitMatchUserIds, $explicitUserIdsAll): void {
                if ($explicitMatchUserIds !== []) {
                    $q->whereIn('id', $explicitMatchUserIds);
                }
                if ($explicitUserIdsAll !== []) {
                    $q->orWhereNotIn('id', $explicitUserIdsAll);
                } else {
                    // No explicit rows at all → everyone qualifies via default
                    $q->orWhereRaw('1=1');
                }
            });
        }

        $columns = ['id', 'email', 'first_name', 'last_name', 'name', 'preferred_language'];
        $users = $usersQuery->select($columns)->get();
        $stats['candidates'] = $users->count();

        if ($stats['candidates'] === 0) {
            return $stats;
        }

        $now = time();
        foreach ($users as $user) {
            $userId = (int) $user->id;
            if ($userId <= 0) {
                continue;
            }

            // Idempotency: skip if last_sent_at is within the cadence window
            $lastSent = $this->service->getLastSentAt($tenantId, $userId);
            if ($lastSent !== null && ($now - $lastSent) < $windowSeconds) {
                continue;
            }

            // Re-fetch full prefs to enforce cadence == 'off' and "opted out of all sources"
            $prefs = $this->service->getUserPrefs($tenantId, $userId);
            if (($prefs['cadence'] ?? '') === 'off' || ! ($prefs['enabled'] ?? true)) {
                continue;
            }
            $optOut = is_array($prefs['opt_out_sources'] ?? null) ? $prefs['opt_out_sources'] : [];
            if (count($optOut) >= $allowedSourceCount) {
                // User opted out of every source — skip silently
                continue;
            }

            try {
                $items = $this->service->digestForMember($tenantId, $userId, $limit);
            } catch (Throwable $e) {
                Log::warning('[CivicDigestDispatch] digest build failed', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (empty($items)) {
                // Skip silently — never send empty digests
                continue;
            }

            $emailSent = false;
            try {
                $emailSent = CivicDigestMail::send($user, $cadence, $items);
            } catch (Throwable $e) {
                Log::warning('[CivicDigestDispatch] email send failed', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }

            $pushSent = false;
            if ($pushService !== null) {
                try {
                    $hasPush = Schema::hasTable('push_subscriptions') && DB::table('push_subscriptions')
                        ->where('tenant_id', $tenantId)
                        ->where('user_id', $userId)
                        ->exists();
                    if ($hasPush) {
                        $pushSent = LocaleContext::withLocale($user, function () use ($pushService, $userId, $items) {
                            $title = (string) __('civic_digest.push.title');
                            $count = count($items);
                            $bodyKey = $count === 1 ? 'civic_digest.push.body_one' : 'civic_digest.push.body_other';
                            $body = (string) __($bodyKey, ['count' => $count]);
                            return $pushService->send($userId, $title, $body, '/caring-community/civic-digest');
                        });
                    }
                } catch (Throwable $e) {
                    Log::warning('[CivicDigestDispatch] push send failed', [
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($emailSent || $pushSent) {
                $stats['members']++;
                $stats['items'] += count($items);
                if ($emailSent) {
                    $stats['emails']++;
                }
                if ($pushSent) {
                    $stats['push']++;
                }
                $this->service->markSentNow($tenantId, $userId);
            }
        }

        return $stats;
    }
}

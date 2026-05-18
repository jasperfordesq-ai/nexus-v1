<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Models\EmailSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Audits whether business events that should produce emails are actually
 * creating tenant-scoped send attempts.
 *
 * email_log proves dispatch/acceptance/delivery. This service covers the
 * earlier enterprise reliability layer: "the domain event happened, but did
 * any email path fire for the right tenant and recipient?"
 */
class EmailTriggerAuditService
{
    /**
     * Enterprise notification contract. Keep entries machine-readable so admin
     * UI can render translated labels around module/event/category codes.
     *
     * @return list<array<string, mixed>>
     */
    public function eventMatrix(): array
    {
        return [
            ['module' => 'auth', 'event' => 'password_reset_requested', 'category' => 'password_reset', 'critical' => true, 'source_table' => 'password_resets'],
            ['module' => 'auth', 'event' => 'password_changed', 'category' => 'security_alert', 'critical' => true, 'source_table' => 'users'],
            ['module' => 'registration', 'event' => 'email_verification_required', 'category' => 'email_verification', 'critical' => true, 'source_table' => 'users'],
            ['module' => 'registration', 'event' => 'welcome_or_activation', 'category' => 'welcome', 'critical' => true, 'source_table' => 'users'],
            ['module' => 'admin_users', 'event' => 'admin_welcome_or_activation', 'category' => 'admin_welcome', 'critical' => true, 'source_table' => 'users'],
            ['module' => 'security', 'event' => 'two_factor_or_passkey_changed', 'category' => 'security_alert', 'critical' => true, 'source_table' => 'users'],
            ['module' => 'groups', 'event' => 'group_email_invite', 'category' => 'group_invite', 'critical' => true, 'source_table' => 'group_invites'],
            ['module' => 'groups', 'event' => 'membership_or_role_change', 'category' => 'group', 'critical' => false, 'source_table' => 'group_members'],
            ['module' => 'connections', 'event' => 'request_or_response', 'category' => 'connection', 'critical' => true, 'source_table' => 'notifications'],
            ['module' => 'messages', 'event' => 'direct_or_voice_message', 'category' => 'message', 'critical' => true, 'source_table' => 'notification_queue'],
            ['module' => 'listings', 'event' => 'approval_expiry_saved_search', 'category' => 'listing', 'critical' => true, 'source_table' => 'notification_queue'],
            ['module' => 'events', 'event' => 'rsvp_change_or_reminder', 'category' => 'event_reminder', 'critical' => true, 'source_table' => 'notification_queue'],
            ['module' => 'volunteering', 'event' => 'application_shift_reminder_hours_expense', 'category' => 'volunteering', 'critical' => true, 'source_table' => 'notification_queue'],
            ['module' => 'goals', 'event' => 'goal_reminder', 'category' => 'goal_reminder', 'critical' => true, 'source_table' => 'notification_queue'],
            ['module' => 'marketplace', 'event' => 'order_offer_payment_rating_report', 'category' => 'marketplace', 'critical' => true, 'source_table' => 'notification_queue'],
            ['module' => 'safeguarding', 'event' => 'incident_flag_vetting_guardian_training', 'category' => 'safeguarding', 'critical' => true, 'source_table' => 'notifications'],
            ['module' => 'newsletter', 'event' => 'newsletter_queue_dispatch', 'category' => 'newsletter', 'critical' => false, 'source_table' => 'newsletter_queue'],
            ['module' => 'digests', 'event' => 'notification_digest_dispatch', 'category' => 'notification_digest', 'critical' => false, 'source_table' => 'notification_queue'],
            ['module' => 'billing', 'event' => 'upgrade_or_billing_notice', 'category' => 'billing', 'critical' => true, 'source_table' => 'email_log'],
            ['module' => 'federation', 'event' => 'cross_tenant_connection_or_transaction', 'category' => 'federation', 'critical' => true, 'source_table' => 'notifications'],
        ];
    }

    /**
     * @return array{
     *   checked_at:string,
     *   window_hours:int,
     *   tenant_id:int|null,
     *   score:int,
     *   matrix_count:int,
     *   issue_count:int,
     *   issues_by_severity:array<string,int>,
     *   issues:list<array<string,mixed>>,
     *   matrix:list<array<string,mixed>>
     * }
     */
    public function run(?int $tenantId = null, int $windowHours = 24): array
    {
        $windowHours = max(1, min($windowHours, 168));
        $since = now()->subHours($windowHours);
        $issues = [];

        try {
            $issues = array_merge(
                $issues,
                $this->checkNewUsersWithoutAccountEmail($tenantId, $since, $windowHours),
                $this->checkPasswordResetsWithoutEmail($tenantId, $since, $windowHours),
                $this->checkGroupInvitesWithoutEmail($tenantId, $since, $windowHours),
                $this->checkNotificationQueueHealth($tenantId, $since, $windowHours),
                $this->checkNewsletterQueueHealth($tenantId, $since, $windowHours),
                $this->checkNotificationStoreHealth($tenantId),
                $this->checkTenantContextAndWebhookHealth($tenantId, $since, $windowHours),
                $this->checkTenantProviderConfiguration($tenantId),
                $this->checkDirectEmailSendSurface($tenantId),
                $this->checkTenantlessDispatcherSendSurface($tenantId)
            );
        } catch (\Throwable $e) {
            Log::warning('EmailTriggerAuditService::run failed', ['error' => $e->getMessage()]);
            $issues[] = $this->issue(
                'email_trigger_audit_failed',
                'warning',
                $tenantId,
                'platform',
                'audit',
                ['error' => $e->getMessage()]
            );
        }

        $issuesBySeverity = ['critical' => 0, 'warning' => 0, 'info' => 0];
        foreach ($issues as $issue) {
            $severity = (string) ($issue['severity'] ?? 'info');
            $issuesBySeverity[$severity] = ($issuesBySeverity[$severity] ?? 0) + 1;
        }

        $score = max(0, 1000
            - ($issuesBySeverity['critical'] * 90)
            - ($issuesBySeverity['warning'] * 35)
            - ($issuesBySeverity['info'] * 10));

        return [
            'checked_at' => now()->toIso8601String(),
            'window_hours' => $windowHours,
            'tenant_id' => $tenantId,
            'score' => $score,
            'matrix_count' => count($this->eventMatrix()),
            'issue_count' => count($issues),
            'issues_by_severity' => $issuesBySeverity,
            'issues' => $issues,
            'matrix' => $this->eventMatrix(),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function checkNewUsersWithoutAccountEmail(?int $tenantId, \DateTimeInterface $since, int $windowHours): array
    {
        if (!$this->hasTables(['users', 'email_log'])) {
            return [];
        }

        $q = DB::table('users')
            ->select('users.tenant_id', DB::raw('COUNT(*) as count'))
            ->where('users.created_at', '>=', $since)
            ->whereNull('users.deleted_at')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('email_log')
                    ->whereColumn('email_log.user_id', 'users.id')
                    ->whereColumn('email_log.tenant_id', 'users.tenant_id')
                    ->whereColumn('email_log.created_at', '>=', 'users.created_at')
                    ->whereIn('email_log.category', [
                        'activation',
                        'admin_welcome',
                        'approval',
                        'email_verification',
                        'identity_verification',
                        'welcome',
                    ]);
            })
            ->groupBy('users.tenant_id');
        $this->excludeReservedEmailDomains($q, 'users.email');

        if ($tenantId !== null) {
            $q->where('users.tenant_id', $tenantId);
        }

        return $this->rowsToIssues(
            $q->get(),
            'new_users_without_account_email_attempt',
            'critical',
            'registration',
            'welcome_or_activation',
            ['window_hours' => $windowHours]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function checkPasswordResetsWithoutEmail(?int $tenantId, \DateTimeInterface $since, int $windowHours): array
    {
        if (!$this->hasTables(['password_resets', 'email_log'])) {
            return [];
        }

        $q = DB::table('password_resets as pr')
            ->select('pr.tenant_id', DB::raw('COUNT(*) as count'))
            ->where('pr.created_at', '>=', $since)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('email_log')
                    ->whereRaw('email_log.recipient_email COLLATE utf8mb4_unicode_ci = pr.email COLLATE utf8mb4_unicode_ci')
                    ->whereColumn('email_log.tenant_id', 'pr.tenant_id')
                    ->whereColumn('email_log.created_at', '>=', 'pr.created_at')
                    ->where('email_log.category', 'password_reset');
            })
            ->groupBy('pr.tenant_id');
        $this->excludeReservedEmailDomains($q, 'pr.email');

        if ($tenantId !== null) {
            $q->where('pr.tenant_id', $tenantId);
        }

        return $this->rowsToIssues(
            $q->get(),
            'password_resets_without_email_attempt',
            'critical',
            'auth',
            'password_reset_requested',
            ['window_hours' => $windowHours]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function checkGroupInvitesWithoutEmail(?int $tenantId, \DateTimeInterface $since, int $windowHours): array
    {
        if (!$this->hasTables(['group_invites', 'email_log'])) {
            return [];
        }

        $q = DB::table('group_invites as gi')
            ->select('gi.tenant_id', DB::raw('COUNT(*) as count'))
            ->where('gi.invite_type', 'email')
            ->where('gi.created_at', '>=', $since)
            ->whereNotNull('gi.email')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('email_log')
                    ->whereColumn('email_log.recipient_email', 'gi.email')
                    ->whereColumn('email_log.tenant_id', 'gi.tenant_id')
                    ->whereColumn('email_log.created_at', '>=', 'gi.created_at')
                    ->where('email_log.category', 'group_invite');
            })
            ->groupBy('gi.tenant_id');
        $this->excludeReservedEmailDomains($q, 'gi.email');

        if ($tenantId !== null) {
            $q->where('gi.tenant_id', $tenantId);
        }

        return $this->rowsToIssues(
            $q->get(),
            'group_invites_without_email_attempt',
            'critical',
            'groups',
            'group_email_invite',
            ['window_hours' => $windowHours]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function checkNotificationQueueHealth(?int $tenantId, \DateTimeInterface $since, int $windowHours): array
    {
        if (!$this->hasTables(['notification_queue'])) {
            return [];
        }

        $issues = [];
        $queueHasTenantId = Schema::hasColumn('notification_queue', 'tenant_id');
        $tenantExpr = $queueHasTenantId ? 'notification_queue.tenant_id' : 'users.tenant_id';

        if ($queueHasTenantId) {
            $missingTenant = DB::table('notification_queue')
                ->selectRaw('NULL as tenant_id, COUNT(*) as count')
                ->whereNull('tenant_id')
                ->whereIn('status', ['pending', 'processing'])
                ->when($tenantId !== null, fn ($q) => $q->whereRaw('1 = 0'))
                ->get();
            $issues = array_merge($issues, $this->rowsToIssues($missingTenant, 'notification_queue_missing_tenant_id', 'critical', 'notifications', 'queue_dispatch'));

            if ($this->hasTables(['users'])) {
                $tenantMismatch = DB::table('notification_queue as nq')
                    ->join('users as u', 'u.id', '=', 'nq.user_id')
                    ->selectRaw('u.tenant_id as tenant_id, COUNT(*) as count')
                    ->whereNotNull('nq.tenant_id')
                    ->whereRaw('nq.tenant_id <> u.tenant_id')
                    ->whereIn('nq.status', ['pending', 'processing'])
                    ->when($tenantId !== null, fn ($q) => $q->where('u.tenant_id', $tenantId))
                    ->groupBy('u.tenant_id')
                    ->get();
                $issues = array_merge($issues, $this->rowsToIssues($tenantMismatch, 'notification_queue_tenant_mismatch', 'critical', 'notifications', 'queue_dispatch'));
            }
        }

        $instantPending = DB::table('notification_queue')
            ->when(!$queueHasTenantId, fn ($q) => $q->join('users', 'users.id', '=', 'notification_queue.user_id'))
            ->selectRaw("{$tenantExpr} as tenant_id, COUNT(*) as count")
            ->where('notification_queue.frequency', 'instant')
            ->where('notification_queue.status', 'pending')
            ->where('notification_queue.created_at', '<', now()->subMinutes(5))
            ->when($tenantId !== null, fn ($q) => $q->whereRaw("{$tenantExpr} = ?", [$tenantId]))
            ->groupByRaw($tenantExpr)
            ->get();
        $issues = array_merge($issues, $this->rowsToIssues($instantPending, 'instant_notifications_stuck_pending', 'critical', 'notifications', 'instant_queue_dispatch', ['minutes' => 5]));

        $processing = DB::table('notification_queue')
            ->when(!$queueHasTenantId, fn ($q) => $q->join('users', 'users.id', '=', 'notification_queue.user_id'))
            ->selectRaw("{$tenantExpr} as tenant_id, COUNT(*) as count")
            ->where('notification_queue.status', 'processing')
            ->where('notification_queue.created_at', '<', now()->subMinutes(15))
            ->when($tenantId !== null, fn ($q) => $q->whereRaw("{$tenantExpr} = ?", [$tenantId]))
            ->groupByRaw($tenantExpr)
            ->get();
        $issues = array_merge($issues, $this->rowsToIssues($processing, 'notification_queue_stale_processing', 'critical', 'notifications', 'queue_dispatch', ['minutes' => 15]));

        $failed = DB::table('notification_queue')
            ->when(!$queueHasTenantId, fn ($q) => $q->join('users', 'users.id', '=', 'notification_queue.user_id'))
            ->selectRaw("{$tenantExpr} as tenant_id, COUNT(*) as count")
            ->where('notification_queue.status', 'failed')
            ->where('notification_queue.created_at', '>=', $since)
            ->when($tenantId !== null, fn ($q) => $q->whereRaw("{$tenantExpr} = ?", [$tenantId]))
            ->groupByRaw($tenantExpr)
            ->get();
        $issues = array_merge($issues, $this->rowsToIssues($failed, 'notification_queue_failed_recently', 'warning', 'notifications', 'queue_dispatch', ['window_hours' => $windowHours]));

        $suppressed = DB::table('notification_queue')
            ->when(!$queueHasTenantId, fn ($q) => $q->join('users', 'users.id', '=', 'notification_queue.user_id'))
            ->selectRaw("{$tenantExpr} as tenant_id, COUNT(*) as count")
            ->where('notification_queue.status', 'suppressed')
            ->where('notification_queue.created_at', '>=', $since)
            ->when($tenantId !== null, fn ($q) => $q->whereRaw("{$tenantExpr} = ?", [$tenantId]))
            ->groupByRaw($tenantExpr)
            ->get();
        $issues = array_merge($issues, $this->rowsToIssues($suppressed, 'notification_queue_suppressed_recently', 'warning', 'notifications', 'queue_dispatch', ['window_hours' => $windowHours]));

        if ($this->hasTables(['email_log', 'users'])) {
            $queuedTenantExpr = $queueHasTenantId ? 'nq.tenant_id' : 'u.tenant_id';
            $sentWithoutLog = DB::table('notification_queue as nq')
                ->join('users as u', 'u.id', '=', 'nq.user_id')
                ->selectRaw("{$queuedTenantExpr} as tenant_id, COUNT(*) as count")
                ->where('nq.status', 'sent')
                ->where('nq.sent_at', '>=', $since)
                ->whereNotExists(function ($sub) use ($queuedTenantExpr) {
                    $sub->select(DB::raw(1))
                        ->from('email_log')
                        ->whereColumn('email_log.user_id', 'nq.user_id')
                        ->whereRaw("email_log.tenant_id = {$queuedTenantExpr}")
                        ->whereColumn('email_log.created_at', '>=', 'nq.created_at')
                        ->whereIn('email_log.category', ['notification_queue', 'notification_digest']);
                })
                ->when($tenantId !== null, fn ($q) => $q->whereRaw("{$queuedTenantExpr} = ?", [$tenantId]))
                ->groupByRaw($queuedTenantExpr)
                ->get();
            $issues = array_merge($issues, $this->rowsToIssues($sentWithoutLog, 'notification_queue_marked_sent_without_email_log', 'critical', 'notifications', 'queue_dispatch', ['window_hours' => $windowHours]));
        }

        return $issues;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function checkNewsletterQueueHealth(?int $tenantId, \DateTimeInterface $since, int $windowHours): array
    {
        if (!$this->hasTables(['newsletter_queue'])) {
            return [];
        }

        $issues = [];
        $queueHasTenantId = Schema::hasColumn('newsletter_queue', 'tenant_id');
        $tenantExpr = $queueHasTenantId ? 'newsletter_queue.tenant_id' : 'newsletters.tenant_id';
        $staleExpr = Schema::hasColumn('newsletter_queue', 'last_attempted_at')
            ? 'COALESCE(newsletter_queue.last_attempted_at, newsletter_queue.created_at)'
            : 'newsletter_queue.created_at';

        if ($queueHasTenantId) {
            $missingTenant = DB::table('newsletter_queue')
                ->selectRaw('NULL as tenant_id, COUNT(*) as count')
                ->whereNull('tenant_id')
                ->whereIn('status', ['pending', 'processing', 'sent'])
                ->when($tenantId !== null, fn ($q) => $q->whereRaw('1 = 0'))
                ->get();
            $issues = array_merge($issues, $this->rowsToIssues($missingTenant, 'newsletter_queue_missing_tenant_id', 'critical', 'newsletter', 'newsletter_queue_dispatch'));
        }

        if ($this->hasTables(['newsletters'])) {
            if ($queueHasTenantId) {
                $tenantMismatch = DB::table('newsletter_queue as nq')
                    ->join('newsletters as n', 'n.id', '=', 'nq.newsletter_id')
                    ->selectRaw('n.tenant_id as tenant_id, COUNT(*) as count')
                    ->whereNotNull('nq.tenant_id')
                    ->whereRaw('nq.tenant_id <> n.tenant_id')
                    ->whereIn('nq.status', ['pending', 'processing', 'sent'])
                    ->when($tenantId !== null, fn ($q) => $q->where('n.tenant_id', $tenantId))
                    ->groupBy('n.tenant_id')
                    ->get();
                $issues = array_merge($issues, $this->rowsToIssues($tenantMismatch, 'newsletter_queue_tenant_mismatch', 'critical', 'newsletter', 'newsletter_queue_dispatch'));
            }

            $tenantExpr = $queueHasTenantId ? 'COALESCE(newsletter_queue.tenant_id, newsletters.tenant_id)' : 'newsletters.tenant_id';
            $pending = DB::table('newsletter_queue')
                ->join('newsletters', 'newsletters.id', '=', 'newsletter_queue.newsletter_id')
                ->selectRaw("{$tenantExpr} as tenant_id, COUNT(*) as count")
                ->where('newsletter_queue.status', 'pending')
                ->where('newsletter_queue.created_at', '<', now()->subMinutes(15))
                ->when($tenantId !== null, fn ($q) => $q->whereRaw("{$tenantExpr} = ?", [$tenantId]))
                ->groupByRaw($tenantExpr)
                ->get();
            $issues = array_merge($issues, $this->rowsToIssues($pending, 'newsletter_queue_stuck_pending', 'warning', 'newsletter', 'newsletter_queue_dispatch', ['minutes' => 15]));

            $processing = DB::table('newsletter_queue')
                ->join('newsletters', 'newsletters.id', '=', 'newsletter_queue.newsletter_id')
                ->selectRaw("{$tenantExpr} as tenant_id, COUNT(*) as count")
                ->where('newsletter_queue.status', 'processing')
                ->whereRaw("{$staleExpr} < ?", [now()->subMinutes(15)])
                ->when($tenantId !== null, fn ($q) => $q->whereRaw("{$tenantExpr} = ?", [$tenantId]))
                ->groupByRaw($tenantExpr)
                ->get();
            $issues = array_merge($issues, $this->rowsToIssues($processing, 'newsletter_queue_stale_processing', 'critical', 'newsletter', 'newsletter_queue_dispatch', ['minutes' => 15]));

            $failed = DB::table('newsletter_queue')
                ->join('newsletters', 'newsletters.id', '=', 'newsletter_queue.newsletter_id')
                ->selectRaw("{$tenantExpr} as tenant_id, COUNT(*) as count")
                ->where('newsletter_queue.status', 'failed')
                ->where('newsletter_queue.created_at', '>=', $since)
                ->when($tenantId !== null, fn ($q) => $q->whereRaw("{$tenantExpr} = ?", [$tenantId]))
                ->groupByRaw($tenantExpr)
                ->get();
            $issues = array_merge($issues, $this->rowsToIssues($failed, 'newsletter_queue_failed_recently', 'warning', 'newsletter', 'newsletter_queue_dispatch', ['window_hours' => $windowHours]));
        }

        if ($this->hasTables(['email_log']) && ($queueHasTenantId || $this->hasTables(['newsletters']))) {
            $queuedTenantExpr = $queueHasTenantId ? 'nq.tenant_id' : 'n.tenant_id';
            $sentWithoutLog = DB::table('newsletter_queue as nq')
                ->when($this->hasTables(['newsletters']), fn ($q) => $q->join('newsletters as n', 'n.id', '=', 'nq.newsletter_id'))
                ->selectRaw("{$queuedTenantExpr} as tenant_id, COUNT(*) as count")
                ->where('nq.status', 'sent')
                ->where('nq.sent_at', '>=', $since)
                ->whereNotExists(function ($sub) use ($queuedTenantExpr) {
                    $sub->select(DB::raw(1))
                        ->from('email_log')
                        ->whereRaw('email_log.recipient_email COLLATE utf8mb4_unicode_ci = nq.email COLLATE utf8mb4_unicode_ci')
                        ->whereRaw("email_log.tenant_id = {$queuedTenantExpr}")
                        ->whereColumn('email_log.created_at', '>=', 'nq.created_at')
                        ->where('email_log.category', 'newsletter')
                        ->whereIn('email_log.status', ['sent', 'delivered', 'bounced']);
                })
                ->when($tenantId !== null, fn ($q) => $q->whereRaw("{$queuedTenantExpr} = ?", [$tenantId]))
                ->groupByRaw($queuedTenantExpr)
                ->get();
            $issues = array_merge($issues, $this->rowsToIssues($sentWithoutLog, 'newsletter_queue_marked_sent_without_email_log', 'critical', 'newsletter', 'newsletter_queue_dispatch', ['window_hours' => $windowHours]));
        }

        return $issues;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function checkNotificationStoreHealth(?int $tenantId): array
    {
        if (!$this->hasTables(['notifications', 'users']) || !Schema::hasColumn('notifications', 'tenant_id')) {
            return [];
        }

        $missingTenant = DB::table('notifications as n')
            ->join('users as u', 'u.id', '=', 'n.user_id')
            ->selectRaw('u.tenant_id as tenant_id, COUNT(*) as count')
            ->whereNull('n.tenant_id')
            ->when($tenantId !== null, fn ($q) => $q->where('u.tenant_id', $tenantId))
            ->groupBy('u.tenant_id')
            ->get();

        $tenantMismatch = DB::table('notifications as n')
            ->join('users as u', 'u.id', '=', 'n.user_id')
            ->selectRaw('u.tenant_id as tenant_id, COUNT(*) as count')
            ->whereNotNull('n.tenant_id')
            ->whereRaw('n.tenant_id <> u.tenant_id')
            ->when($tenantId !== null, fn ($q) => $q->where('u.tenant_id', $tenantId))
            ->groupBy('u.tenant_id')
            ->get();

        return array_merge(
            $this->rowsToIssues($missingTenant, 'notifications_missing_tenant_id', 'critical', 'notifications', 'bell_dispatch'),
            $this->rowsToIssues($tenantMismatch, 'notifications_tenant_mismatch', 'critical', 'notifications', 'bell_dispatch')
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function checkDirectEmailSendSurface(?int $tenantId): array
    {
        $surface = $this->directEmailSendSurface();
        if ($surface === []) {
            return [];
        }

        return [
            $this->issue(
                'direct_email_send_paths_remaining',
                'warning',
                $tenantId,
                'architecture',
                'direct_send_surface',
                [
                    'count' => count($surface),
                    'samples' => array_slice(array_map(
                        fn (array $row): string => $row['path'] . ':' . $row['line'],
                        $surface
                    ), 0, 8),
                ]
            ),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function checkTenantlessDispatcherSendSurface(?int $tenantId): array
    {
        $surface = $this->tenantlessDispatcherSendSurface();
        if ($surface === []) {
            return [];
        }

        return [
            $this->issue(
                'email_dispatch_missing_explicit_tenant',
                'critical',
                $tenantId,
                'architecture',
                'dispatcher_tenant_contract',
                [
                    'count' => count($surface),
                    'samples' => array_slice(array_map(
                        fn (array $row): string => $row['path'] . ':' . $row['line'],
                        $surface
                    ), 0, 8),
                ]
            ),
        ];
    }

    /**
     * Find legacy raw email send call sites that still bypass the central
     * business-event dispatcher. This is intentionally advisory today; once
     * the migration is complete it can become a CI-blocking assertion.
     *
     * @return list<array{path:string,line:int,pattern:string}>
     */
    public function directEmailSendSurface(): array
    {
        $appPath = app_path();
        if (!is_dir($appPath)) {
            return [];
        }

        $allowed = array_filter(array_map('realpath', [
            app_path('Core/Mailer.php'),
            app_path('Services/EmailDispatchService.php'),
            app_path('Services/EmailService.php'),
            app_path('Services/EmailTriggerAuditService.php'),
        ]));

        $patterns = [
            'mailer_factory_send' => '/(?:\\\\?App\\\\Core\\\\)?Mailer::forCurrentTenant\s*\(\s*\)\s*\)?\s*->\s*send\s*\(/',
            'mailer_new_send' => '/new\s+(?:\\\\?App\\\\Core\\\\)?Mailer\s*\([^;]*\)\s*\)?\s*->\s*send\s*\(/',
            'mailer_variable_send' => '/(?:\$mailer|\$[A-Za-z_][A-Za-z0-9_]*mailer[A-Za-z0-9_]*)\s*->\s*send\s*\(/i',
            'email_service_app_send' => '/app\s*\(\s*EmailService::class\s*\)\s*->\s*send\s*\(/',
            'email_service_variable_send' => '/(?:\$email|\$emailService|\$[A-Za-z_][A-Za-z0-9_]*(?:emailservice|email)[A-Za-z0-9_]*)\s*->\s*send\s*\(/i',
        ];

        $surface = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appPath));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $realPath = realpath($path);
            if ($realPath !== false && in_array($realPath, $allowed, true)) {
                continue;
            }

            $relativePath = str_replace($appPath . DIRECTORY_SEPARATOR, '', $path);
            $lines = file($path, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
                continue;
            }

            $inBlockComment = false;
            foreach ($lines as $index => $line) {
                $codeLine = $this->stripPhpCommentsFromLine((string) $line, $inBlockComment);
                if (trim($codeLine) === '') {
                    continue;
                }

                foreach ($patterns as $name => $pattern) {
                    if (preg_match($pattern, $codeLine) === 1) {
                        $surface[] = [
                            'path' => $relativePath,
                            'line' => $index + 1,
                            'pattern' => $name,
                        ];
                        break;
                    }
                }
            }
        }

        usort($surface, fn (array $a, array $b): int => [$a['path'], $a['line']] <=> [$b['path'], $b['line']]);

        return $surface;
    }

    /**
     * Find EmailDispatchService send calls that still rely on implicit tenant
     * inference. Tenant inference remains as a defensive fallback, but audited
     * production send roots must pass tenant_id/tenantId explicitly.
     *
     * @return list<array{path:string,line:int,pattern:string}>
     */
    public function tenantlessDispatcherSendSurface(): array
    {
        $appPath = app_path();
        if (!is_dir($appPath)) {
            return [];
        }

        $allowed = array_filter(array_map('realpath', [
            app_path('Services/EmailDispatchService.php'),
            app_path('Services/EmailTriggerAuditService.php'),
        ]));

        $surface = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($appPath));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $realPath = realpath($path);
            if ($realPath !== false && in_array($realPath, $allowed, true)) {
                continue;
            }

            $relativePath = str_replace($appPath . DIRECTORY_SEPARATOR, '', $path);
            $lines = file($path, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
                continue;
            }

            $inBlockComment = false;
            $count = count($lines);
            for ($index = 0; $index < $count; $index++) {
                $codeLine = $this->stripPhpCommentsFromLine((string) $lines[$index], $inBlockComment);
                if (!str_contains($codeLine, 'EmailDispatchService::sendRaw(')
                    && !str_contains($codeLine, 'EmailDispatchService::sendWithOptions(')
                    && !str_contains($codeLine, '\\App\\Services\\EmailDispatchService::sendRaw(')
                    && !str_contains($codeLine, '\\App\\Services\\EmailDispatchService::sendWithOptions(')
                ) {
                    continue;
                }

                $call = $codeLine;
                $parenBalance = substr_count($codeLine, '(') - substr_count($codeLine, ')');
                $end = $index;
                while ($parenBalance > 0 && $end + 1 < $count) {
                    $end++;
                    $nextLine = $this->stripPhpCommentsFromLine((string) $lines[$end], $inBlockComment);
                    $call .= "\n" . $nextLine;
                    $parenBalance += substr_count($nextLine, '(') - substr_count($nextLine, ')');
                }

                if (!preg_match('/[\'"]tenant_id[\'"]|[\'"]tenantId[\'"]/', $call)) {
                    $surface[] = [
                        'path' => $relativePath,
                        'line' => $index + 1,
                        'pattern' => str_contains($call, 'sendWithOptions') ? 'send_with_options_missing_tenant' : 'send_raw_missing_tenant',
                    ];
                }

                $index = max($index, $end);
            }
        }

        usort($surface, fn (array $a, array $b): int => [$a['path'], $a['line']] <=> [$b['path'], $b['line']]);

        return $surface;
    }

    private function stripPhpCommentsFromLine(string $line, bool &$inBlockComment): string
    {
        $remaining = $line;

        while ($remaining !== '') {
            if ($inBlockComment) {
                $end = strpos($remaining, '*/');
                if ($end === false) {
                    return '';
                }
                $remaining = substr($remaining, $end + 2);
                $inBlockComment = false;
                continue;
            }

            $start = strpos($remaining, '/*');
            $single = strpos($remaining, '//');

            if ($single !== false && ($start === false || $single < $start)) {
                return substr($remaining, 0, $single);
            }

            if ($start === false) {
                return $remaining;
            }

            $end = strpos($remaining, '*/', $start + 2);
            if ($end === false) {
                $inBlockComment = true;
                return substr($remaining, 0, $start);
            }

            $remaining = substr($remaining, 0, $start) . substr($remaining, $end + 2);
        }

        return '';
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function checkTenantContextAndWebhookHealth(?int $tenantId, \DateTimeInterface $since, int $windowHours): array
    {
        if (!$this->hasTables(['email_log'])) {
            return [];
        }

        $issues = [];
        $criticalCategories = [
            'activation',
            'admin_welcome',
            'approval',
            'email_verification',
            'group_invite',
            'identity_verification',
            'password_reset',
            'security_alert',
            'welcome',
        ];

        if ($tenantId === null) {
            $nullTenantCritical = DB::table('email_log')
                ->whereNull('tenant_id')
                ->where('created_at', '>=', $since)
                ->whereIn('category', $criticalCategories)
                ->count();
            if ($nullTenantCritical > 0) {
                $issues[] = $this->issue('critical_email_attempts_missing_tenant_context', 'critical', null, 'platform', 'tenant_context', [
                    'count' => $nullTenantCritical,
                    'window_hours' => $windowHours,
                ]);
            }
        }

        $unconfirmed = DB::table('email_log')
            ->select('tenant_id', DB::raw('COUNT(*) as count'))
            ->where('provider', 'sendgrid')
            ->where('status', 'sent')
            ->whereNotNull('provider_message_id')
            ->where('created_at', '<', now()->subHours(6))
            ->where('created_at', '>=', now()->subDays(7))
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->groupBy('tenant_id')
            ->get();
        $issues = array_merge($issues, $this->rowsToIssues($unconfirmed, 'sendgrid_events_not_confirming_delivery', 'warning', 'deliverability', 'provider_webhook', ['hours' => 6]));

        return $issues;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function checkTenantProviderConfiguration(?int $tenantId): array
    {
        if (!$this->hasTables(['tenants', 'email_settings'])) {
            return [];
        }

        $tenantIds = DB::table('tenants')
            ->where('is_active', 1)
            ->when($tenantId !== null, fn ($q) => $q->where('id', $tenantId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $issues = [];
        foreach ($tenantIds as $id) {
            try {
                $provider = EmailSettings::get($id, 'email_provider') ?: 'platform_default';
                if ($provider === 'sendgrid' && !EmailSettings::get($id, 'sendgrid_api_key')) {
                    $issues[] = $this->issue('tenant_sendgrid_override_missing_api_key', 'info', $id, 'deliverability', 'provider_config', ['provider' => 'sendgrid']);
                }
                if ($provider === 'gmail_api') {
                    $missing = [];
                    foreach (['gmail_client_id', 'gmail_client_secret', 'gmail_refresh_token'] as $key) {
                        if (!EmailSettings::get($id, $key)) {
                            $missing[] = $key;
                        }
                    }
                    if ($missing !== []) {
                        $issues[] = $this->issue('tenant_gmail_override_incomplete', 'warning', $id, 'deliverability', 'provider_config', ['missing' => implode(',', $missing)]);
                    }
                }
                if ($provider === 'smtp') {
                    $host = EmailSettings::get($id, 'smtp_host');
                    $from = EmailSettings::get($id, 'smtp_from_email');
                    if (!$host || !$from) {
                        $issues[] = $this->issue('tenant_smtp_override_incomplete', 'warning', $id, 'deliverability', 'provider_config', ['provider' => 'smtp']);
                    }
                }
            } catch (\Throwable $e) {
                $issues[] = $this->issue('tenant_provider_config_check_failed', 'warning', $id, 'deliverability', 'provider_config', ['error' => $e->getMessage()]);
            }
        }

        return $issues;
    }

    /**
     * @param iterable<object> $rows
     * @param array<string,mixed> $extraParams
     * @return list<array<string,mixed>>
     */
    private function rowsToIssues(iterable $rows, string $code, string $severity, string $module, string $event, array $extraParams = []): array
    {
        $issues = [];
        foreach ($rows as $row) {
            $count = (int) ($row->count ?? 0);
            if ($count <= 0) {
                continue;
            }
            $issues[] = $this->issue($code, $severity, isset($row->tenant_id) ? (int) $row->tenant_id : null, $module, $event, array_merge($extraParams, ['count' => $count]));
        }
        return $issues;
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function issue(string $code, string $severity, ?int $tenantId, string $module, string $event, array $params = []): array
    {
        return [
            'code' => $code,
            'severity' => $severity,
            'tenant_id' => $tenantId,
            'module' => $module,
            'event' => $event,
            'message_key' => "email_deliverability.warnings.{$code}.body",
            'params' => $params,
        ];
    }

    /**
     * @param list<string> $tables
     */
    private function hasTables(array $tables): bool
    {
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Reserved test domains are intentionally non-deliverable. They are useful
     * for local fixtures, but should not be counted as "email failed to fire"
     * incidents in the enterprise trigger audit.
     *
     * @param \Illuminate\Database\Query\Builder $query
     */
    private function excludeReservedEmailDomains($query, string $column): void
    {
        foreach ([
            '%@example.test',
            '%@example.invalid',
            '%@test.local',
            '%@localhost',
        ] as $pattern) {
            $query->where($column, 'not like', $pattern);
        }
    }
}

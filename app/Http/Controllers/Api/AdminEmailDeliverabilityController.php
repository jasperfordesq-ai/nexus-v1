<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\EmailMonitorService;
use App\Services\EmailTriggerAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only admin telemetry over the email_log + email_suppression tables.
 *
 * Powers `/admin/email-deliverability` in the React admin so operators can
 * answer "did Joe Bloggs get his welcome email?", view per-tenant bounce
 * rates, and manage the local suppression cache without SSH'ing to the DB.
 *
 * All endpoints are tenant-scoped via TenantContext::getId(). Platform
 * super-admins can pass `tenant_id=all` to see cross-tenant aggregates.
 */
class AdminEmailDeliverabilityController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/email-deliverability/summary
     *
     * Headline metrics for the tenant: counts by status over the last 7 / 30
     * days plus delivery / bounce rates. Cheap aggregate query, safe to poll.
     */
    public function summary(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->resolveAdminTenantFilter($this->isSuperAdmin(), $this->getTenantId());

        $windowDays = (int) ($this->input('days', 7) ?: 7);
        $windowDays = max(1, min($windowDays, 90));

        $rows = DB::table('email_log')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $sent      = (int) ($rows['sent'] ?? 0);
        $delivered = (int) ($rows['delivered'] ?? 0);
        $bounced   = (int) ($rows['bounced'] ?? 0);
        $failed    = (int) ($rows['failed'] ?? 0);
        $suppressed = (int) ($rows['suppressed'] ?? 0);
        $total     = $sent + $delivered + $bounced + $failed + $suppressed;

        $deliveredRate = $total > 0 ? round(($delivered / $total) * 100, 1) : null;
        $acceptedRate  = $total > 0 ? round((($sent + $delivered) / $total) * 100, 1) : null;
        $bouncedRate   = $total > 0 ? round(($bounced / $total) * 100, 1) : null;

        return $this->respondWithData([
            'window_days'     => $windowDays,
            'total'           => $total,
            'by_status'       => $rows,
            'delivered_pct'   => $deliveredRate,
            'accepted_pct'    => $acceptedRate,
            'unconfirmed_sent' => $sent,
            'bounced_pct'     => $bouncedRate,
            'warnings'        => app(EmailMonitorService::class)->getWarnings($tenantId),
            'trigger_audit'    => app(EmailTriggerAuditService::class)->run($tenantId, $windowDays * 24),
        ]);
    }

    /**
     * GET /api/v2/admin/email-deliverability/trigger-audit
     *
     * Reconciles business events against email_log so admins can see missing
     * triggers, not just failed send attempts.
     */
    public function triggerAudit(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->resolveAdminTenantFilter($this->isSuperAdmin(), $this->getTenantId());
        $windowHours = max(1, min((int) ($this->input('hours', 24) ?: 24), 168));

        return $this->respondWithData(
            app(EmailTriggerAuditService::class)->run($tenantId, $windowHours)
        );
    }

    /**
     * GET /api/v2/admin/email-deliverability/logs
     *
     * Paginated email_log feed for the tenant. Supports filters: user_id,
     * recipient_email (LIKE %query%), status, since, until.
     */
    public function logs(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->resolveAdminTenantFilter($this->isSuperAdmin(), $this->getTenantId());

        $limit = max(1, min((int) ($this->input('limit', 50) ?: 50), 200));
        $offset = max(0, (int) ($this->input('offset', 0)));

        $q = DB::table('email_log')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId));

        if ($userId = (int) ($this->input('user_id', 0))) {
            $q->where('user_id', $userId);
        }
        if ($email = trim((string) $this->input('email', ''))) {
            $q->where('recipient_email', 'like', '%' . str_replace('%', '\\%', $email) . '%');
        }
        if ($status = trim((string) $this->input('status', ''))) {
            $q->where('status', $status);
        }
        if ($category = trim((string) $this->input('category', ''))) {
            $q->where('category', 'like', '%' . str_replace('%', '\\%', $category) . '%');
        }
        if ($since = trim((string) $this->input('since', ''))) {
            $q->where('created_at', '>=', $since);
        }
        if ($until = trim((string) $this->input('until', ''))) {
            $q->where('created_at', '<=', $until);
        }

        $total = (clone $q)->count();

        $rows = $q->orderByDesc('id')
            ->limit($limit)
            ->offset($offset)
            ->get([
                'id', 'user_id', 'recipient_email', 'category', 'subject',
                'provider', 'status', 'provider_message_id', 'error',
                'sent_at', 'delivered_at', 'bounced_at', 'opened_at', 'created_at',
            ]);

        return $this->respondWithData([
            'rows'   => $rows,
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * GET /api/v2/admin/email-deliverability/queues
     *
     * Read-only diagnostics for rows that can explain why an expected email
     * has not left the platform yet: stale pending/processing rows, failures,
     * and suppressions in email-backed queues and scheduled reminder sources.
     */
    public function queues(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->resolveAdminTenantFilter($this->isSuperAdmin(), $this->getTenantId());

        $limit = max(1, min((int) ($this->input('limit', 50) ?: 50), 100));
        $status = trim((string) $this->input('status', ''));
        $source = trim((string) $this->input('source', ''));
        $statuses = ['pending', 'processing', 'failed', 'suppressed'];
        if ($status !== '' && in_array($status, $statuses, true)) {
            $statuses = [$status];
        }

        $diagnostics = [
            'notification_queue' => $this->queueSourceDiagnostics('notification_queue', $tenantId),
            'newsletter_queue' => $this->queueSourceDiagnostics('newsletter_queue', $tenantId),
            'listing_expiry_reminders_sent' => $this->queueSourceDiagnostics('listing_expiry_reminders_sent', $tenantId),
            'marketplace_report_notifications' => $this->queueSourceDiagnostics('marketplace_report_notifications', $tenantId),
            'event_reminders' => $this->queueSourceDiagnostics('event_reminders', $tenantId),
            'goal_reminders' => $this->queueSourceDiagnostics('goal_reminders', $tenantId),
            'job_interviews' => $this->queueSourceDiagnostics('job_interviews', $tenantId),
            'vol_reminders_sent' => $this->queueSourceDiagnostics('vol_reminders_sent', $tenantId),
            'member_subscription_events' => $this->queueSourceDiagnostics('member_subscription_events', $tenantId),
            'vol_donations' => $this->queueSourceDiagnostics('vol_donations', $tenantId),
            'federation_messages' => $this->queueSourceDiagnostics('federation_messages', $tenantId),
            'federation_transactions' => $this->queueSourceDiagnostics('federation_transactions', $tenantId),
            'federation_inbound_connections' => $this->queueSourceDiagnostics('federation_inbound_connections', $tenantId),
            'reviews' => $this->queueSourceDiagnostics('reviews', $tenantId),
        ];

        $notificationRows = collect();
        if (($source === '' || $source === 'notification_queue') && Schema::hasTable('notification_queue')) {
            $notificationRows = DB::table('notification_queue as nq')
                ->leftJoin('users as u', function ($join) {
                    $join->on('u.id', '=', 'nq.user_id')
                        ->on('u.tenant_id', '=', 'nq.tenant_id');
                })
                ->when($tenantId !== null, fn ($q) => $q->where('nq.tenant_id', $tenantId))
                ->whereIn('nq.status', $statuses)
                ->where(function ($q) {
                    $q->whereIn('nq.status', ['failed', 'suppressed'])
                        ->orWhere(function ($stale) {
                            $stale->where('nq.status', 'processing')
                                ->whereRaw('COALESCE(nq.processing_started_at, nq.last_attempted_at, nq.created_at) < ?', [now()->subMinutes(15)]);
                        })
                        ->orWhere(function ($stale) {
                            $stale->where('nq.status', 'pending')
                                ->where('nq.created_at', '<', now()->subMinutes(15));
                        });
                })
                ->orderByDesc('nq.id')
                ->limit($limit)
                ->get([
                    'nq.id',
                    'nq.tenant_id',
                    'nq.user_id',
                    'u.email',
                    'nq.activity_type as category',
                    'nq.content_snippet as subject',
                    'nq.status',
                    'nq.frequency',
                    'nq.attempts',
                    'nq.last_attempted_at',
                    'nq.last_error as error',
                    'nq.processing_batch_id',
                    'nq.processing_started_at',
                    'nq.sent_at',
                    'nq.created_at',
                ])
                ->map(fn ($row) => $this->queueRow('notification_queue', $row));
        }

        $newsletterRows = collect();
        if (($source === '' || $source === 'newsletter_queue') && Schema::hasTable('newsletter_queue') && Schema::hasTable('newsletters')) {
            $newsletterRows = DB::table('newsletter_queue as nq')
                ->join('newsletters as n', 'n.id', '=', 'nq.newsletter_id')
                ->when($tenantId !== null, fn ($q) => $q->whereRaw('COALESCE(nq.tenant_id, n.tenant_id) = ?', [$tenantId]))
                ->whereIn('nq.status', $statuses)
                ->where(function ($q) {
                    $q->whereIn('nq.status', ['failed', 'suppressed'])
                        ->orWhere(function ($stale) {
                            $stale->where('nq.status', 'processing')
                                ->whereRaw('COALESCE(nq.processing_started_at, nq.last_attempted_at, nq.created_at) < ?', [now()->subMinutes(15)]);
                        })
                        ->orWhere(function ($stale) {
                            $stale->where('nq.status', 'pending')
                                ->where('nq.created_at', '<', now()->subMinutes(15));
                        });
                })
                ->orderByDesc('nq.id')
                ->limit($limit)
                ->get([
                    'nq.id',
                    DB::raw('COALESCE(nq.tenant_id, n.tenant_id) as tenant_id'),
                    'nq.user_id',
                    'nq.email',
                    DB::raw("'newsletter' as category"),
                    'n.subject',
                    'nq.status',
                    DB::raw('NULL as frequency'),
                    'nq.attempts',
                    'nq.last_attempted_at',
                    'nq.error_message as error',
                    'nq.processing_batch_id',
                    'nq.processing_started_at',
                    'nq.sent_at',
                    'nq.created_at',
                ])
                ->map(fn ($row) => $this->queueRow('newsletter_queue', $row));
        }

        $marketplaceReportRows = collect();
        if (($source === '' || $source === 'marketplace_report_notifications') && Schema::hasTable('marketplace_report_notifications')) {
            $marketplaceReportRows = DB::table('marketplace_report_notifications as mrn')
                ->leftJoin('users as u', function ($join) {
                    $join->on('u.id', '=', 'mrn.recipient_user_id')
                        ->on('u.tenant_id', '=', 'mrn.tenant_id');
                })
                ->when($tenantId !== null, fn ($q) => $q->where('mrn.tenant_id', $tenantId))
                ->where('mrn.channel', 'email')
                ->whereIn('mrn.status', $statuses)
                ->where(function ($q) {
                    $q->whereIn('mrn.status', ['failed', 'suppressed'])
                        ->orWhere(function ($stale) {
                            $stale->where('mrn.status', 'processing')
                                ->whereRaw('COALESCE(mrn.last_attempted_at, mrn.updated_at, mrn.created_at) < ?', [now()->subMinutes(15)]);
                        })
                        ->orWhere(function ($stale) {
                            $stale->where('mrn.status', 'pending')
                                ->where('mrn.created_at', '<', now()->subMinutes(10));
                        });
                })
                ->orderByDesc('mrn.id')
                ->limit($limit)
                ->get([
                    'mrn.id',
                    'mrn.tenant_id',
                    'mrn.recipient_user_id as user_id',
                    'u.email',
                    'mrn.event_type as category',
                    DB::raw('NULL as subject'),
                    'mrn.status',
                    DB::raw('NULL as frequency'),
                    'mrn.attempts',
                    'mrn.last_attempted_at',
                    'mrn.last_error as error',
                    DB::raw('NULL as processing_batch_id'),
                    DB::raw('NULL as processing_started_at'),
                    'mrn.sent_at',
                    'mrn.created_at',
                ])
                ->map(fn ($row) => $this->queueRow('marketplace_report_notifications', $row));
        }

        $listingExpiryReminderRows = collect();
        if (($source === '' || $source === 'listing_expiry_reminders_sent') && $this->hasListingExpiryReminderEvidenceColumns()) {
            $listingExpiryReminderRows = DB::table('listing_expiry_reminders_sent as lers')
                ->leftJoin('users as u', function ($join): void {
                    $join->on('u.id', '=', 'lers.user_id')
                        ->whereColumn('u.tenant_id', '=', 'lers.tenant_id');
                })
                ->leftJoin('listings as l', function ($join): void {
                    $join->on('l.id', '=', 'lers.listing_id')
                        ->whereColumn('l.tenant_id', '=', 'lers.tenant_id');
                })
                ->when($tenantId !== null, fn ($q) => $q->where('lers.tenant_id', $tenantId))
                ->where('lers.sent_at', '>=', now()->subDay())
                ->whereNotExists(function ($sub): void {
                    $sub->select(DB::raw(1))
                        ->from('email_log')
                        ->whereColumn('email_log.user_id', 'lers.user_id')
                        ->whereColumn('email_log.tenant_id', 'lers.tenant_id')
                        ->where('email_log.category', 'listing_expiry')
                        ->whereIn('email_log.status', ['sent', 'delivered', 'bounced'])
                        ->whereRaw('email_log.created_at BETWEEN DATE_SUB(lers.sent_at, INTERVAL 10 MINUTE) AND DATE_ADD(lers.sent_at, INTERVAL 10 MINUTE)');
                })
                ->orderByDesc('lers.id')
                ->limit($limit)
                ->get([
                    'lers.id',
                    'lers.tenant_id',
                    'lers.user_id',
                    'u.email',
                    DB::raw("'listing_expiry' as category"),
                    'l.title as subject',
                    DB::raw("'failed' as status"),
                    'lers.days_before_expiry as frequency',
                    DB::raw('0 as attempts'),
                    'lers.sent_at as last_attempted_at',
                    DB::raw("'marked sent without email_log evidence' as error"),
                    DB::raw('NULL as processing_batch_id'),
                    DB::raw('NULL as processing_started_at'),
                    'lers.sent_at',
                    'lers.sent_at as created_at',
                ])
                ->map(fn ($row) => $this->queueRow('listing_expiry_reminders_sent', $row));
        }

        $eventReminderRows = collect();
        if (($source === '' || $source === 'event_reminders') && Schema::hasTable('event_reminders')) {
            $eventReminderRows = DB::table('event_reminders as er')
                ->leftJoin('users as u', function ($join) {
                    $join->on('u.id', '=', 'er.user_id')
                        ->on('u.tenant_id', '=', 'er.tenant_id');
                })
                ->when($tenantId !== null, fn ($q) => $q->where('er.tenant_id', $tenantId))
                ->whereIn('er.status', array_values(array_intersect($statuses, ['pending', 'failed'])))
                ->whereIn('er.reminder_type', ['email', 'both'])
                ->where(function ($q) {
                    $q->where('er.status', 'failed')
                        ->orWhere(function ($stale) {
                            $stale->where('er.status', 'pending')
                                ->where('er.scheduled_for', '<', now()->subMinutes(15));
                        });
                })
                ->orderByDesc('er.id')
                ->limit($limit)
                ->get([
                    'er.id',
                    'er.tenant_id',
                    'er.user_id',
                    'u.email',
                    DB::raw("'event_reminder' as category"),
                    DB::raw('NULL as subject'),
                    'er.status',
                    'er.reminder_type as frequency',
                    DB::raw('0 as attempts'),
                    DB::raw('NULL as last_attempted_at'),
                    DB::raw('NULL as error'),
                    DB::raw('NULL as processing_batch_id'),
                    DB::raw('NULL as processing_started_at'),
                    'er.sent_at',
                    'er.created_at',
                ])
                ->map(fn ($row) => $this->queueRow('event_reminders', $row));
        }

        $goalReminderRows = collect();
        if (($source === '' || $source === 'goal_reminders') && $this->hasGoalReminderDeliveryColumns()) {
            $goalReminderRows = DB::table('goal_reminders as gr')
                ->join('goals as g', function ($join): void {
                    $join->on('g.id', '=', 'gr.goal_id')
                        ->whereColumn('g.tenant_id', '=', 'gr.tenant_id');
                })
                ->leftJoin('users as u', function ($join): void {
                    $join->on('u.id', '=', 'gr.user_id')
                        ->whereColumn('u.tenant_id', '=', 'gr.tenant_id');
                })
                ->when($tenantId !== null, fn ($q) => $q->where('gr.tenant_id', $tenantId))
                ->where('gr.enabled', 1)
                ->where('g.status', 'active')
                ->whereIn(DB::raw($this->goalReminderStatusExpression()), $statuses)
                ->where('gr.next_reminder_at', '<', now()->subMinutes(15))
                ->orderByDesc('gr.id')
                ->limit($limit)
                ->get([
                    'gr.id',
                    'gr.tenant_id',
                    'gr.user_id',
                    'u.email',
                    DB::raw("'goal_reminder' as category"),
                    'g.title as subject',
                    DB::raw($this->goalReminderStatusExpression() . ' as status'),
                    'gr.frequency',
                    DB::raw('0 as attempts'),
                    'gr.last_sent_at as last_attempted_at',
                    DB::raw('NULL as error'),
                    DB::raw('NULL as processing_batch_id'),
                    DB::raw('NULL as processing_started_at'),
                    'gr.last_sent_at as sent_at',
                    'gr.created_at',
                ])
                ->map(fn ($row) => $this->queueRow('goal_reminders', $row));
        }

        $jobInterviewRows = collect();
        if (($source === '' || $source === 'job_interviews') && $this->hasJobInterviewReminderDeliveryColumns()) {
            $pendingRows = collect();
            if (in_array('pending', $statuses, true)) {
                $pendingRows = $this->jobInterviewReminderPendingQuery(
                    DB::table('job_interviews as ji')
                        ->leftJoin('job_vacancies as jv', function ($join): void {
                            $join->on('jv.id', '=', 'ji.vacancy_id')
                                ->whereColumn('jv.tenant_id', '=', 'ji.tenant_id');
                        })
                        ->leftJoin('job_applications as ja', function ($join): void {
                            $join->on('ja.id', '=', 'ji.application_id')
                                ->whereColumn('ja.tenant_id', '=', 'ji.tenant_id');
                        })
                        ->leftJoin('users as u', function ($join): void {
                            $join->on('u.id', '=', 'ja.user_id')
                                ->whereColumn('u.tenant_id', '=', 'ji.tenant_id');
                        })
                        ->when($tenantId !== null, fn ($q) => $q->where('ji.tenant_id', $tenantId))
                )
                    ->orderByDesc('ji.id')
                    ->limit($limit)
                    ->get([
                        'ji.id',
                        'ji.tenant_id',
                        DB::raw('COALESCE(ja.user_id, ji.proposed_by) as user_id'),
                        'u.email',
                        DB::raw("'job_interview' as category"),
                        'jv.title as subject',
                        DB::raw("'pending' as status"),
                        DB::raw("CASE WHEN ji.scheduled_at <= DATE_ADD(NOW(), INTERVAL 1 HOUR) THEN '1h' ELSE '24h' END as frequency"),
                        DB::raw('0 as attempts'),
                        DB::raw('NULL as last_attempted_at'),
                        DB::raw('NULL as error'),
                        DB::raw('NULL as processing_batch_id'),
                        DB::raw('NULL as processing_started_at'),
                        DB::raw('NULL as sent_at'),
                        'ji.created_at',
                    ]);
            }

            $missingEvidenceRows = collect();
            if (in_array('failed', $statuses, true)) {
                $missingEvidenceRows = $this->jobInterviewReminderMissingEmailEvidenceQuery(
                    DB::table('job_interviews as ji')
                        ->leftJoin('job_vacancies as jv', function ($join): void {
                            $join->on('jv.id', '=', 'ji.vacancy_id')
                                ->whereColumn('jv.tenant_id', '=', 'ji.tenant_id');
                        })
                        ->leftJoin('job_applications as ja', function ($join): void {
                            $join->on('ja.id', '=', 'ji.application_id')
                                ->whereColumn('ja.tenant_id', '=', 'ji.tenant_id');
                        })
                        ->leftJoin('users as u', function ($join): void {
                            $join->on('u.id', '=', 'ja.user_id')
                                ->whereColumn('u.tenant_id', '=', 'ji.tenant_id');
                        })
                        ->when($tenantId !== null, fn ($q) => $q->where('ji.tenant_id', $tenantId))
                        ->where(function ($query): void {
                            $query->where('ji.reminder_24h_sent_at', '>=', now()->subDay())
                                ->orWhere('ji.reminder_1h_sent_at', '>=', now()->subDay());
                        })
                )
                    ->orderByDesc('ji.id')
                    ->limit($limit)
                    ->get([
                        'ji.id',
                        'ji.tenant_id',
                        DB::raw('COALESCE(ja.user_id, ji.proposed_by) as user_id'),
                        'u.email',
                        DB::raw("'job_interview' as category"),
                        'jv.title as subject',
                        DB::raw("'failed' as status"),
                        DB::raw("CASE WHEN ji.reminder_1h_sent_at IS NOT NULL THEN '1h' ELSE '24h' END as frequency"),
                        DB::raw('0 as attempts'),
                        DB::raw('COALESCE(ji.reminder_1h_sent_at, ji.reminder_24h_sent_at) as last_attempted_at'),
                        DB::raw("'marked sent without email_log evidence' as error"),
                        DB::raw('NULL as processing_batch_id'),
                        DB::raw('NULL as processing_started_at'),
                        DB::raw('COALESCE(ji.reminder_1h_sent_at, ji.reminder_24h_sent_at) as sent_at'),
                        'ji.created_at',
                    ]);
            }

            $jobInterviewRows = $pendingRows
                ->merge($missingEvidenceRows)
                ->take($limit)
                ->map(fn ($row) => $this->queueRow('job_interviews', $row));
        }

        $volunteerReminderRows = collect();
        if (($source === '' || $source === 'vol_reminders_sent') && $this->hasVolunteerReminderEvidenceColumns()) {
            $volunteerReminderRows = DB::table('vol_reminders_sent as vrs')
                ->leftJoin('users as u', function ($join): void {
                    $join->on('u.id', '=', 'vrs.user_id')
                        ->whereColumn('u.tenant_id', '=', 'vrs.tenant_id');
                })
                ->when($tenantId !== null, fn ($q) => $q->where('vrs.tenant_id', $tenantId))
                ->where('vrs.channel', 'email')
                ->where('vrs.sent_at', '>=', now()->subDay())
                ->whereNotExists(function ($sub): void {
                    $sub->select(DB::raw(1))
                        ->from('email_log')
                        ->whereColumn('email_log.user_id', 'vrs.user_id')
                        ->whereColumn('email_log.tenant_id', 'vrs.tenant_id')
                        ->where('email_log.category', 'volunteer_reminder')
                        ->whereIn('email_log.status', ['sent', 'delivered', 'bounced'])
                        ->whereRaw('email_log.created_at BETWEEN DATE_SUB(vrs.sent_at, INTERVAL 10 MINUTE) AND DATE_ADD(vrs.sent_at, INTERVAL 10 MINUTE)');
                })
                ->orderByDesc('vrs.id')
                ->limit($limit)
                ->get([
                    'vrs.id',
                    'vrs.tenant_id',
                    'vrs.user_id',
                    'u.email',
                    'vrs.reminder_type as category',
                    DB::raw('NULL as subject'),
                    DB::raw("'failed' as status"),
                    'vrs.channel as frequency',
                    DB::raw('0 as attempts'),
                    'vrs.sent_at as last_attempted_at',
                    DB::raw("'marked sent without email_log evidence' as error"),
                    DB::raw('NULL as processing_batch_id'),
                    DB::raw('NULL as processing_started_at'),
                    'vrs.sent_at',
                    'vrs.sent_at as created_at',
                ])
                ->map(fn ($row) => $this->queueRow('vol_reminders_sent', $row));
        }

        $memberSubscriptionRows = collect();
        if (($source === '' || $source === 'member_subscription_events') && $this->hasMemberSubscriptionEventDeliveryColumns()) {
            $memberSubscriptionQuery = DB::table('member_subscription_events as mse')
                ->leftJoin('member_subscriptions as ms', function ($join): void {
                    $join->on('ms.id', '=', 'mse.subscription_id')
                        ->on('ms.tenant_id', '=', 'mse.tenant_id');
                })
                ->leftJoin('users as u', function ($join): void {
                    $join->on('u.id', '=', 'ms.user_id')
                        ->on('u.tenant_id', '=', 'mse.tenant_id');
                })
                ->whereIn('mse.event_type', ['subscription.deleted', 'invoice.paid', 'invoice.payment_failed'])
                ->when($tenantId !== null, fn ($q) => $q->where('mse.tenant_id', $tenantId));

            $this->applyMemberSubscriptionEventStatusFilter($memberSubscriptionQuery, $statuses);

            $memberSubscriptionRows = $memberSubscriptionQuery
                ->orderByDesc('mse.id')
                ->limit($limit)
                ->get([
                    'mse.id',
                    'mse.tenant_id',
                    'ms.user_id',
                    'u.email',
                    DB::raw("'billing' as category"),
                    'mse.event_type as subject',
                    DB::raw($this->memberSubscriptionEventStatusExpression() . ' as status'),
                    DB::raw('NULL as frequency'),
                    DB::raw('0 as attempts'),
                    'mse.notification_failed_at as last_attempted_at',
                    'mse.notification_last_error as error',
                    DB::raw('NULL as processing_batch_id'),
                    DB::raw('NULL as processing_started_at'),
                    'mse.notification_sent_at as sent_at',
                    'mse.created_at',
                ])
                ->map(fn ($row) => $this->queueRow('member_subscription_events', $row));
        }

        $volDonationRows = collect();
        if (($source === '' || $source === 'vol_donations') && $this->hasVolDonationReceiptDeliveryColumns()) {
            $volDonationQuery = DB::table('vol_donations as vd')
                ->leftJoin('users as u', function ($join): void {
                    $join->on('u.id', '=', 'vd.user_id')
                        ->on('u.tenant_id', '=', 'vd.tenant_id');
                })
                ->where('vd.status', 'completed')
                ->whereNotNull('vd.stripe_payment_intent_id')
                ->when($tenantId !== null, fn ($q) => $q->where('vd.tenant_id', $tenantId));

            $this->applyVolDonationReceiptStatusFilter($volDonationQuery, $statuses);

            $volDonationRows = $volDonationQuery
                ->orderByDesc('vd.id')
                ->limit($limit)
                ->get([
                    'vd.id',
                    'vd.tenant_id',
                    'vd.user_id',
                    DB::raw('COALESCE(vd.donor_email, u.email) as email'),
                    DB::raw("'donation_receipt' as category"),
                    'vd.stripe_payment_intent_id as subject',
                    DB::raw($this->volDonationReceiptStatusExpression() . ' as status'),
                    DB::raw('NULL as frequency'),
                    DB::raw('0 as attempts'),
                    'vd.receipt_email_failed_at as last_attempted_at',
                    DB::raw('NULL as error'),
                    DB::raw('NULL as processing_batch_id'),
                    DB::raw('NULL as processing_started_at'),
                    'vd.receipt_email_sent_at as sent_at',
                    'vd.created_at',
                ])
                ->map(fn ($row) => $this->queueRow('vol_donations', $row));
        }

        $federationSourceRows = collect();
        foreach (array_keys($this->federationDeliverySourceConfigs()) as $federationSource) {
            if (($source !== '' && $source !== $federationSource) || !$this->hasFederationDeliverySourceColumns($federationSource)) {
                continue;
            }

            $config = $this->federationDeliverySourceConfigs()[$federationSource];
            $alias = $config['alias'];
            $tenantColumn = $config['tenant_column'];
            $userColumn = $config['user_column'];
            $subjectColumn = $config['subject_column'];

            $query = DB::table("{$federationSource} as {$alias}")
                ->leftJoin('users as u', function ($join) use ($alias, $tenantColumn, $userColumn): void {
                    $join->on('u.id', '=', "{$alias}.{$userColumn}")
                        ->on('u.tenant_id', '=', "{$alias}.{$tenantColumn}");
                })
                ->when($tenantId !== null, fn ($q) => $q->where("{$alias}.{$tenantColumn}", $tenantId));

            $this->applyFederationDeliveryStatusFilter($query, $statuses, $alias);

            $federationSourceRows = $federationSourceRows->merge(
                $query
                    ->orderByDesc("{$alias}.id")
                    ->limit($limit)
                    ->get([
                        "{$alias}.id",
                        DB::raw("{$alias}.{$tenantColumn} as tenant_id"),
                        DB::raw("{$alias}.{$userColumn} as user_id"),
                        'u.email',
                        DB::raw("'" . $config['category'] . "' as category"),
                        DB::raw("{$alias}.{$subjectColumn} as subject"),
                        DB::raw($this->federationDeliveryStatusExpression($alias) . ' as status'),
                        DB::raw('NULL as frequency'),
                        DB::raw('0 as attempts'),
                        "{$alias}.email_failed_at as last_attempted_at",
                        "{$alias}.email_last_error as error",
                        DB::raw('NULL as processing_batch_id'),
                        DB::raw('NULL as processing_started_at'),
                        "{$alias}.email_sent_at as sent_at",
                        "{$alias}.created_at",
                    ])
                    ->map(fn ($row) => $this->queueRow($federationSource, $row))
            );
        }

        $reviewRows = collect();
        if (($source === '' || $source === 'reviews') && $this->hasFederationReviewDeliveryColumns()) {
            $reviewQuery = DB::table('reviews as r')
                ->leftJoin('users as u', function ($join) {
                    $join->on('u.id', '=', 'r.receiver_id')
                        ->on('u.tenant_id', '=', 'r.tenant_id');
                })
                ->where('r.review_type', 'federated')
                ->whereNotNull('r.external_partner_id')
                ->whereNotNull('r.external_id')
                ->when($tenantId !== null, fn ($q) => $q->where('r.tenant_id', $tenantId));

            $this->applyFederationReviewDeliveryStatusFilter($reviewQuery, $statuses);

            $reviewRows = $reviewQuery
                ->orderByDesc('r.id')
                ->limit($limit)
                ->get([
                    'r.id',
                    'r.tenant_id',
                    'r.receiver_id as user_id',
                    'u.email',
                    DB::raw("'federation_review' as category"),
                    'r.external_id as subject',
                    DB::raw($this->federationReviewDeliveryStatusExpression() . ' as status'),
                    DB::raw('NULL as frequency'),
                    DB::raw('0 as attempts'),
                    'r.email_claimed_at as last_attempted_at',
                    'r.email_last_error as error',
                    DB::raw('NULL as processing_batch_id'),
                    'r.email_claimed_at as processing_started_at',
                    'r.email_sent_at as sent_at',
                    'r.created_at',
                ])
                ->map(fn ($row) => $this->queueRow('reviews', $row));
        }

        $rows = $notificationRows
            ->merge($newsletterRows)
            ->merge($listingExpiryReminderRows)
            ->merge($marketplaceReportRows)
            ->merge($eventReminderRows)
            ->merge($goalReminderRows)
            ->merge($jobInterviewRows)
            ->merge($volunteerReminderRows)
            ->merge($memberSubscriptionRows)
            ->merge($volDonationRows)
            ->merge($federationSourceRows)
            ->merge($reviewRows)
            ->sortByDesc('created_at')
            ->values()
            ->take($limit);

        foreach (array_keys($diagnostics) as $diagnosticSource) {
            $diagnostics[$diagnosticSource]['returned'] = $rows->where('source', $diagnosticSource)->count();
        }

        return $this->respondWithData([
            'rows' => $rows,
            'limit' => $limit,
            'filters' => [
                'source' => $source !== '' ? $source : null,
                'status' => $status !== '' ? $status : null,
            ],
            'diagnostics' => $diagnostics,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function queueRow(string $source, object $row): array
    {
        return [
            'source' => $source,
            'id' => (int) $row->id,
            'tenant_id' => (int) $row->tenant_id,
            'user_id' => isset($row->user_id) ? (int) $row->user_id : null,
            'email' => $row->email ?? null,
            'category' => $row->category ?? null,
            'subject' => $row->subject ?? null,
            'status' => (string) $row->status,
            'frequency' => $row->frequency ?? null,
            'attempts' => (int) ($row->attempts ?? 0),
            'last_attempted_at' => $row->last_attempted_at ?? null,
            'error' => $row->error ?? null,
            'processing_batch_id' => $row->processing_batch_id ?? null,
            'processing_started_at' => $row->processing_started_at ?? null,
            'sent_at' => $row->sent_at ?? null,
            'created_at' => $row->created_at ?? null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function queueSourceDiagnostics(string $source, ?int $tenantId): array
    {
        if ($source === 'notification_queue') {
            if (!Schema::hasTable('notification_queue')) {
                return $this->unavailableQueueDiagnostics($source);
            }

            $base = DB::table('notification_queue as nq')
                ->when($tenantId !== null, fn ($q) => $q->where('nq.tenant_id', $tenantId));

            return [
                'source' => $source,
                'available' => true,
                'status_counts' => $this->queueStatusCounts($base, 'nq.status'),
                'stale_pending' => (clone $base)
                    ->where('nq.status', 'pending')
                    ->where('nq.created_at', '<', now()->subMinutes(15))
                    ->count(),
                'stale_processing' => (clone $base)
                    ->where('nq.status', 'processing')
                    ->whereRaw('COALESCE(nq.processing_started_at, nq.last_attempted_at, nq.created_at) < ?', [now()->subMinutes(15)])
                    ->count(),
                'failed_recent' => (clone $base)
                    ->where('nq.status', 'failed')
                    ->where('nq.created_at', '>=', now()->subDay())
                    ->count(),
                'suppressed_recent' => (clone $base)
                    ->where('nq.status', 'suppressed')
                    ->where('nq.created_at', '>=', now()->subDay())
                    ->count(),
                'oldest_pending_at' => (clone $base)->where('nq.status', 'pending')->min('nq.created_at'),
                'returned' => 0,
            ];
        }

        if ($source === 'newsletter_queue') {
            if (!Schema::hasTable('newsletter_queue') || !Schema::hasTable('newsletters')) {
                return $this->unavailableQueueDiagnostics($source);
            }

            $tenantExpr = Schema::hasColumn('newsletter_queue', 'tenant_id')
                ? 'COALESCE(nq.tenant_id, n.tenant_id)'
                : 'n.tenant_id';
            $base = DB::table('newsletter_queue as nq')
                ->join('newsletters as n', 'n.id', '=', 'nq.newsletter_id')
                ->when($tenantId !== null, fn ($q) => $q->whereRaw("{$tenantExpr} = ?", [$tenantId]));

            return [
                'source' => $source,
                'available' => true,
                'status_counts' => $this->queueStatusCounts($base, 'nq.status'),
                'stale_pending' => (clone $base)
                    ->where('nq.status', 'pending')
                    ->where('nq.created_at', '<', now()->subMinutes(15))
                    ->count(),
                'stale_processing' => (clone $base)
                    ->where('nq.status', 'processing')
                    ->whereRaw('COALESCE(nq.processing_started_at, nq.last_attempted_at, nq.created_at) < ?', [now()->subMinutes(15)])
                    ->count(),
                'failed_recent' => (clone $base)
                    ->where('nq.status', 'failed')
                    ->where('nq.created_at', '>=', now()->subDay())
                    ->count(),
                'suppressed_recent' => (clone $base)
                    ->where('nq.status', 'suppressed')
                    ->where('nq.created_at', '>=', now()->subDay())
                    ->count(),
                'oldest_pending_at' => (clone $base)->where('nq.status', 'pending')->min('nq.created_at'),
                'returned' => 0,
            ];
        }

        if ($source === 'marketplace_report_notifications') {
            if (!Schema::hasTable('marketplace_report_notifications')) {
                return $this->unavailableQueueDiagnostics($source);
            }

            $base = DB::table('marketplace_report_notifications as mrn')
                ->where('mrn.channel', 'email')
                ->when($tenantId !== null, fn ($q) => $q->where('mrn.tenant_id', $tenantId));

            return [
                'source' => $source,
                'available' => true,
                'status_counts' => $this->queueStatusCounts($base, 'mrn.status'),
                'stale_pending' => (clone $base)
                    ->where('mrn.status', 'pending')
                    ->where('mrn.created_at', '<', now()->subMinutes(10))
                    ->count(),
                'stale_processing' => (clone $base)
                    ->where('mrn.status', 'processing')
                    ->whereRaw('COALESCE(mrn.last_attempted_at, mrn.updated_at, mrn.created_at) < ?', [now()->subMinutes(15)])
                    ->count(),
                'failed_recent' => (clone $base)
                    ->where('mrn.status', 'failed')
                    ->where('mrn.updated_at', '>=', now()->subDay())
                    ->count(),
                'suppressed_recent' => (clone $base)
                    ->where('mrn.status', 'suppressed')
                    ->where('mrn.updated_at', '>=', now()->subDay())
                    ->count(),
                'oldest_pending_at' => (clone $base)->where('mrn.status', 'pending')->min('mrn.created_at'),
                'returned' => 0,
            ];
        }

        if ($source === 'listing_expiry_reminders_sent') {
            if (!$this->hasListingExpiryReminderEvidenceColumns()) {
                return $this->unavailableQueueDiagnostics($source);
            }

            $base = DB::table('listing_expiry_reminders_sent as lers')
                ->when($tenantId !== null, fn ($q) => $q->where('lers.tenant_id', $tenantId));
            $missingEvidence = $this->listingExpiryReminderMissingEmailEvidenceQuery($base);

            return [
                'source' => $source,
                'available' => true,
                'status_counts' => [
                    'failed' => (clone $missingEvidence)->where('lers.sent_at', '>=', now()->subDay())->count(),
                ],
                'stale_pending' => 0,
                'stale_processing' => 0,
                'failed_recent' => (clone $missingEvidence)->where('lers.sent_at', '>=', now()->subDay())->count(),
                'suppressed_recent' => 0,
                'oldest_pending_at' => null,
                'returned' => 0,
            ];
        }

        if ($source === 'event_reminders') {
            if (!Schema::hasTable('event_reminders')) {
                return $this->unavailableQueueDiagnostics($source);
            }

            $base = DB::table('event_reminders as er')
                ->whereIn('er.reminder_type', ['email', 'both'])
                ->when($tenantId !== null, fn ($q) => $q->where('er.tenant_id', $tenantId));

            return [
                'source' => $source,
                'available' => true,
                'status_counts' => $this->queueStatusCounts($base, 'er.status'),
                'stale_pending' => (clone $base)
                    ->where('er.status', 'pending')
                    ->where('er.scheduled_for', '<', now()->subMinutes(15))
                    ->count(),
                'stale_processing' => 0,
                'failed_recent' => (clone $base)
                    ->where('er.status', 'failed')
                    ->where('er.updated_at', '>=', now()->subDay())
                    ->count(),
                'suppressed_recent' => 0,
                'oldest_pending_at' => (clone $base)->where('er.status', 'pending')->min('er.scheduled_for'),
                'returned' => 0,
            ];
        }

        if ($source === 'goal_reminders') {
            if (!$this->hasGoalReminderDeliveryColumns()) {
                return $this->unavailableQueueDiagnostics($source);
            }

            $base = DB::table('goal_reminders as gr')
                ->join('goals as g', function ($join): void {
                    $join->on('g.id', '=', 'gr.goal_id')
                        ->whereColumn('g.tenant_id', '=', 'gr.tenant_id');
                })
                ->where('gr.enabled', 1)
                ->where('g.status', 'active')
                ->when($tenantId !== null, fn ($q) => $q->where('gr.tenant_id', $tenantId));

            return [
                'source' => $source,
                'available' => true,
                'status_counts' => $this->queueStatusCounts($base, $this->goalReminderStatusExpression()),
                'stale_pending' => (clone $base)
                    ->where('gr.next_reminder_at', '<', now()->subMinutes(15))
                    ->count(),
                'stale_processing' => 0,
                'failed_recent' => 0,
                'suppressed_recent' => 0,
                'oldest_pending_at' => (clone $base)
                    ->whereNotNull('gr.next_reminder_at')
                    ->where('gr.next_reminder_at', '<', now())
                    ->min('gr.next_reminder_at'),
                'returned' => 0,
            ];
        }

        if ($source === 'job_interviews') {
            if (!$this->hasJobInterviewReminderDeliveryColumns()) {
                return $this->unavailableQueueDiagnostics($source);
            }

            $base = DB::table('job_interviews as ji')
                ->when($tenantId !== null, fn ($q) => $q->where('ji.tenant_id', $tenantId));
            $pending = $this->jobInterviewReminderPendingQuery(clone $base);
            $missingEvidence = $this->jobInterviewReminderMissingEmailEvidenceQuery(
                (clone $base)->where(function ($query): void {
                    $query->where('ji.reminder_24h_sent_at', '>=', now()->subDay())
                        ->orWhere('ji.reminder_1h_sent_at', '>=', now()->subDay());
                })
            );

            return [
                'source' => $source,
                'available' => true,
                'status_counts' => [
                    'pending' => (clone $pending)->count(),
                    'failed' => (clone $missingEvidence)->count(),
                ],
                'stale_pending' => (clone $pending)->count(),
                'stale_processing' => 0,
                'failed_recent' => (clone $missingEvidence)->count(),
                'suppressed_recent' => 0,
                'oldest_pending_at' => (clone $pending)->min('ji.scheduled_at'),
                'returned' => 0,
            ];
        }

        if ($source === 'vol_reminders_sent') {
            if (!$this->hasVolunteerReminderEvidenceColumns()) {
                return $this->unavailableQueueDiagnostics($source);
            }

            $base = DB::table('vol_reminders_sent as vrs')
                ->where('vrs.channel', 'email')
                ->when($tenantId !== null, fn ($q) => $q->where('vrs.tenant_id', $tenantId));
            $missingEvidence = $this->volunteerReminderMissingEmailEvidenceQuery($base);

            return [
                'source' => $source,
                'available' => true,
                'status_counts' => [
                    'failed' => (clone $missingEvidence)->where('vrs.sent_at', '>=', now()->subDay())->count(),
                ],
                'stale_pending' => 0,
                'stale_processing' => 0,
                'failed_recent' => (clone $missingEvidence)->where('vrs.sent_at', '>=', now()->subDay())->count(),
                'suppressed_recent' => 0,
                'oldest_pending_at' => null,
                'returned' => 0,
            ];
        }

        if ($source === 'member_subscription_events') {
            if (!$this->hasMemberSubscriptionEventDeliveryColumns()) {
                return $this->unavailableQueueDiagnostics($source);
            }

            $base = DB::table('member_subscription_events as mse')
                ->whereIn('mse.event_type', ['subscription.deleted', 'invoice.paid', 'invoice.payment_failed'])
                ->when($tenantId !== null, fn ($q) => $q->where('mse.tenant_id', $tenantId));

            return [
                'source' => $source,
                'available' => true,
                'status_counts' => $this->queueStatusCounts($base, $this->memberSubscriptionEventStatusExpression()),
                'stale_pending' => (clone $base)
                    ->whereNull('mse.notification_sent_at')
                    ->whereNull('mse.notification_failed_at')
                    ->where('mse.created_at', '<', now()->subMinutes(15))
                    ->count(),
                'stale_processing' => 0,
                'failed_recent' => (clone $base)
                    ->whereNull('mse.notification_sent_at')
                    ->whereNotNull('mse.notification_failed_at')
                    ->where('mse.notification_failed_at', '>=', now()->subDay())
                    ->count(),
                'suppressed_recent' => 0,
                'oldest_pending_at' => (clone $base)
                    ->whereNull('mse.notification_sent_at')
                    ->whereNull('mse.notification_failed_at')
                    ->min('mse.created_at'),
                'returned' => 0,
            ];
        }

        if ($source === 'vol_donations') {
            if (!$this->hasVolDonationReceiptDeliveryColumns()) {
                return $this->unavailableQueueDiagnostics($source);
            }

            $base = DB::table('vol_donations as vd')
                ->where('vd.status', 'completed')
                ->whereNotNull('vd.stripe_payment_intent_id')
                ->when($tenantId !== null, fn ($q) => $q->where('vd.tenant_id', $tenantId));

            return [
                'source' => $source,
                'available' => true,
                'status_counts' => $this->queueStatusCounts($base, $this->volDonationReceiptStatusExpression()),
                'stale_pending' => (clone $base)
                    ->whereNull('vd.receipt_email_sent_at')
                    ->whereNull('vd.receipt_email_failed_at')
                    ->where('vd.created_at', '<', now()->subMinutes(15))
                    ->count(),
                'stale_processing' => 0,
                'failed_recent' => (clone $base)
                    ->whereNull('vd.receipt_email_sent_at')
                    ->whereNotNull('vd.receipt_email_failed_at')
                    ->where('vd.receipt_email_failed_at', '>=', now()->subDay())
                    ->count(),
                'suppressed_recent' => 0,
                'oldest_pending_at' => (clone $base)
                    ->whereNull('vd.receipt_email_sent_at')
                    ->whereNull('vd.receipt_email_failed_at')
                    ->min('vd.created_at'),
                'returned' => 0,
            ];
        }

        if (array_key_exists($source, $this->federationDeliverySourceConfigs())) {
            if (!$this->hasFederationDeliverySourceColumns($source)) {
                return $this->unavailableQueueDiagnostics($source);
            }

            $config = $this->federationDeliverySourceConfigs()[$source];
            $alias = $config['alias'];
            $tenantColumn = $config['tenant_column'];
            $base = DB::table("{$source} as {$alias}")
                ->when($tenantId !== null, fn ($q) => $q->where("{$alias}.{$tenantColumn}", $tenantId));

            return [
                'source' => $source,
                'available' => true,
                'status_counts' => $this->queueStatusCounts($base, $this->federationDeliveryStatusExpression($alias)),
                'stale_pending' => (clone $base)
                    ->whereNull("{$alias}.email_sent_at")
                    ->whereNull("{$alias}.email_failed_at")
                    ->where("{$alias}.created_at", '<', now()->subMinutes(15))
                    ->count(),
                'stale_processing' => 0,
                'failed_recent' => (clone $base)
                    ->whereNull("{$alias}.email_sent_at")
                    ->whereNotNull("{$alias}.email_failed_at")
                    ->where("{$alias}.email_failed_at", '>=', now()->subDay())
                    ->count(),
                'suppressed_recent' => 0,
                'oldest_pending_at' => (clone $base)
                    ->whereNull("{$alias}.email_sent_at")
                    ->whereNull("{$alias}.email_failed_at")
                    ->min("{$alias}.created_at"),
                'returned' => 0,
            ];
        }

        if ($source === 'reviews') {
            if (!$this->hasFederationReviewDeliveryColumns()) {
                return $this->unavailableQueueDiagnostics($source);
            }

            $base = DB::table('reviews as r')
                ->where('r.review_type', 'federated')
                ->whereNotNull('r.external_partner_id')
                ->whereNotNull('r.external_id')
                ->when($tenantId !== null, fn ($q) => $q->where('r.tenant_id', $tenantId));

            return [
                'source' => $source,
                'available' => true,
                'status_counts' => $this->queueStatusCounts($base, $this->federationReviewDeliveryStatusExpression()),
                'stale_pending' => (clone $base)
                    ->whereNull('r.email_sent_at')
                    ->whereNull('r.email_skipped_at')
                    ->whereNull('r.email_claimed_at')
                    ->where('r.created_at', '<', now()->subMinutes(15))
                    ->count(),
                'stale_processing' => (clone $base)
                    ->whereNull('r.email_sent_at')
                    ->whereNull('r.email_skipped_at')
                    ->whereNotNull('r.email_claimed_at')
                    ->where('r.email_claimed_at', '<', now()->subMinutes(15))
                    ->count(),
                'failed_recent' => (clone $base)
                    ->whereNull('r.email_sent_at')
                    ->whereNull('r.email_skipped_at')
                    ->whereNotNull('r.email_failed_at')
                    ->where('r.email_failed_at', '>=', now()->subDay())
                    ->count(),
                'suppressed_recent' => (clone $base)
                    ->whereNotNull('r.email_skipped_at')
                    ->where('r.email_skipped_at', '>=', now()->subDay())
                    ->count(),
                'oldest_pending_at' => (clone $base)
                    ->whereNull('r.email_sent_at')
                    ->whereNull('r.email_skipped_at')
                    ->min('r.created_at'),
                'returned' => 0,
            ];
        }

        return $this->unavailableQueueDiagnostics($source);
    }

    private function hasFederationReviewDeliveryColumns(): bool
    {
        return Schema::hasTable('reviews')
            && Schema::hasColumn('reviews', 'external_partner_id')
            && Schema::hasColumn('reviews', 'external_id')
            && Schema::hasColumn('reviews', 'email_sent_at')
            && Schema::hasColumn('reviews', 'email_claimed_at')
            && Schema::hasColumn('reviews', 'email_skipped_at')
            && Schema::hasColumn('reviews', 'email_failed_at')
            && Schema::hasColumn('reviews', 'email_last_error');
    }

    private function hasGoalReminderDeliveryColumns(): bool
    {
        return Schema::hasTable('goal_reminders')
            && Schema::hasTable('goals')
            && Schema::hasTable('users')
            && Schema::hasColumn('goal_reminders', 'next_reminder_at')
            && Schema::hasColumn('goal_reminders', 'last_sent_at');
    }

    private function goalReminderStatusExpression(): string
    {
        return "CASE
            WHEN gr.next_reminder_at IS NOT NULL AND gr.next_reminder_at < NOW() THEN 'pending'
            WHEN gr.last_sent_at IS NOT NULL THEN 'sent'
            ELSE 'scheduled'
        END";
    }

    private function hasListingExpiryReminderEvidenceColumns(): bool
    {
        return Schema::hasTable('listing_expiry_reminders_sent')
            && Schema::hasTable('email_log')
            && Schema::hasColumn('listing_expiry_reminders_sent', 'sent_at');
    }

    private function listingExpiryReminderMissingEmailEvidenceQuery($query)
    {
        return $query->whereNotExists(function ($sub): void {
            $sub->select(DB::raw(1))
                ->from('email_log')
                ->whereColumn('email_log.user_id', 'lers.user_id')
                ->whereColumn('email_log.tenant_id', 'lers.tenant_id')
                ->where('email_log.category', 'listing_expiry')
                ->whereIn('email_log.status', ['sent', 'delivered', 'bounced'])
                ->whereRaw('email_log.created_at BETWEEN DATE_SUB(lers.sent_at, INTERVAL 10 MINUTE) AND DATE_ADD(lers.sent_at, INTERVAL 10 MINUTE)');
        });
    }

    private function hasJobInterviewReminderDeliveryColumns(): bool
    {
        return Schema::hasTable('job_interviews')
            && Schema::hasTable('job_applications')
            && Schema::hasTable('job_vacancies')
            && Schema::hasTable('email_log')
            && Schema::hasTable('users')
            && Schema::hasColumn('job_interviews', 'scheduled_at')
            && Schema::hasColumn('job_interviews', 'reminder_24h_sent_at')
            && Schema::hasColumn('job_interviews', 'reminder_1h_sent_at');
    }

    private function jobInterviewReminderPendingQuery($query)
    {
        return $query
            ->whereIn('ji.status', ['proposed', 'accepted'])
            ->where(function ($query): void {
                $query->where(function ($window): void {
                    $window->whereNull('ji.reminder_24h_sent_at')
                        ->where('ji.scheduled_at', '>', now()->addHour())
                        ->where('ji.scheduled_at', '<=', now()->addHours(24)->subMinutes(15));
                })->orWhere(function ($window): void {
                    $window->whereNull('ji.reminder_1h_sent_at')
                        ->where('ji.scheduled_at', '>', now())
                        ->where('ji.scheduled_at', '<=', now()->addHour()->subMinutes(15));
                });
            });
    }

    private function jobInterviewReminderMissingEmailEvidenceQuery($query)
    {
        return $query->whereNotExists(function ($sub): void {
            $sub->select(DB::raw(1))
                ->from('email_log')
                ->whereColumn('email_log.tenant_id', 'ji.tenant_id')
                ->where('email_log.category', 'job_interview')
                ->whereIn('email_log.status', ['sent', 'delivered', 'bounced'])
                ->whereRaw(
                    '(email_log.created_at BETWEEN DATE_SUB(ji.reminder_24h_sent_at, INTERVAL 10 MINUTE) AND DATE_ADD(ji.reminder_24h_sent_at, INTERVAL 10 MINUTE)
                        OR email_log.created_at BETWEEN DATE_SUB(ji.reminder_1h_sent_at, INTERVAL 10 MINUTE) AND DATE_ADD(ji.reminder_1h_sent_at, INTERVAL 10 MINUTE))'
                );
        });
    }

    private function hasVolunteerReminderEvidenceColumns(): bool
    {
        return Schema::hasTable('vol_reminders_sent')
            && Schema::hasTable('email_log')
            && Schema::hasColumn('vol_reminders_sent', 'sent_at')
            && Schema::hasColumn('vol_reminders_sent', 'channel');
    }

    private function volunteerReminderMissingEmailEvidenceQuery($query)
    {
        return $query->whereNotExists(function ($sub): void {
            $sub->select(DB::raw(1))
                ->from('email_log')
                ->whereColumn('email_log.user_id', 'vrs.user_id')
                ->whereColumn('email_log.tenant_id', 'vrs.tenant_id')
                ->where('email_log.category', 'volunteer_reminder')
                ->whereIn('email_log.status', ['sent', 'delivered', 'bounced'])
                ->whereRaw('email_log.created_at BETWEEN DATE_SUB(vrs.sent_at, INTERVAL 10 MINUTE) AND DATE_ADD(vrs.sent_at, INTERVAL 10 MINUTE)');
        });
    }

    private function hasMemberSubscriptionEventDeliveryColumns(): bool
    {
        return Schema::hasTable('member_subscription_events')
            && Schema::hasTable('member_subscriptions')
            && Schema::hasColumn('member_subscription_events', 'notification_sent_at')
            && Schema::hasColumn('member_subscription_events', 'notification_failed_at')
            && Schema::hasColumn('member_subscription_events', 'notification_last_error');
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     * @param list<string> $statuses
     */
    private function applyMemberSubscriptionEventStatusFilter($query, array $statuses): void
    {
        $relevantStatuses = array_intersect($statuses, ['pending', 'failed']);
        if ($relevantStatuses === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($q) use ($statuses): void {
            if (in_array('failed', $statuses, true)) {
                $q->orWhere(function ($status): void {
                    $status->whereNull('mse.notification_sent_at')
                        ->whereNotNull('mse.notification_failed_at');
                });
            }

            if (in_array('pending', $statuses, true)) {
                $q->orWhere(function ($status): void {
                    $status->whereNull('mse.notification_sent_at')
                        ->whereNull('mse.notification_failed_at')
                        ->where('mse.created_at', '<', now()->subMinutes(15));
                });
            }
        });
    }

    private function memberSubscriptionEventStatusExpression(): string
    {
        return "CASE
            WHEN mse.notification_sent_at IS NOT NULL THEN 'sent'
            WHEN mse.notification_failed_at IS NOT NULL THEN 'failed'
            ELSE 'pending'
        END";
    }

    private function hasVolDonationReceiptDeliveryColumns(): bool
    {
        return Schema::hasTable('vol_donations')
            && Schema::hasColumn('vol_donations', 'receipt_email_sent_at')
            && Schema::hasColumn('vol_donations', 'receipt_email_failed_at');
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     * @param list<string> $statuses
     */
    private function applyVolDonationReceiptStatusFilter($query, array $statuses): void
    {
        $relevantStatuses = array_intersect($statuses, ['pending', 'failed']);
        if ($relevantStatuses === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($q) use ($statuses): void {
            if (in_array('failed', $statuses, true)) {
                $q->orWhere(function ($status): void {
                    $status->whereNull('vd.receipt_email_sent_at')
                        ->whereNotNull('vd.receipt_email_failed_at');
                });
            }

            if (in_array('pending', $statuses, true)) {
                $q->orWhere(function ($status): void {
                    $status->whereNull('vd.receipt_email_sent_at')
                        ->whereNull('vd.receipt_email_failed_at')
                        ->where('vd.created_at', '<', now()->subMinutes(15));
                });
            }
        });
    }

    private function volDonationReceiptStatusExpression(): string
    {
        return "CASE
            WHEN vd.receipt_email_sent_at IS NOT NULL THEN 'sent'
            WHEN vd.receipt_email_failed_at IS NOT NULL THEN 'failed'
            ELSE 'pending'
        END";
    }

    /**
     * @return array<string,array{alias:string,tenant_column:string,user_column:string,subject_column:string,category:string}>
     */
    private function federationDeliverySourceConfigs(): array
    {
        return [
            'federation_messages' => [
                'alias' => 'fm',
                'tenant_column' => 'receiver_tenant_id',
                'user_column' => 'receiver_user_id',
                'subject_column' => 'subject',
                'category' => 'federation_message',
            ],
            'federation_transactions' => [
                'alias' => 'ft',
                'tenant_column' => 'receiver_tenant_id',
                'user_column' => 'receiver_user_id',
                'subject_column' => 'description',
                'category' => 'federation_transaction',
            ],
            'federation_inbound_connections' => [
                'alias' => 'fic',
                'tenant_column' => 'tenant_id',
                'user_column' => 'local_user_id',
                'subject_column' => 'message',
                'category' => 'federation_connection',
            ],
        ];
    }

    private function hasFederationDeliverySourceColumns(string $source): bool
    {
        return Schema::hasTable($source)
            && Schema::hasColumn($source, 'notification_sent_at')
            && Schema::hasColumn($source, 'email_sent_at')
            && Schema::hasColumn($source, 'email_failed_at')
            && Schema::hasColumn($source, 'email_last_error')
            && array_key_exists($source, $this->federationDeliverySourceConfigs());
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     * @param list<string> $statuses
     */
    private function applyFederationDeliveryStatusFilter($query, array $statuses, string $alias): void
    {
        $relevantStatuses = array_intersect($statuses, ['pending', 'failed']);
        if ($relevantStatuses === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($q) use ($statuses, $alias): void {
            if (in_array('failed', $statuses, true)) {
                $q->orWhere(function ($status) use ($alias): void {
                    $status->whereNull("{$alias}.email_sent_at")
                        ->whereNotNull("{$alias}.email_failed_at");
                });
            }

            if (in_array('pending', $statuses, true)) {
                $q->orWhere(function ($status) use ($alias): void {
                    $status->whereNull("{$alias}.email_sent_at")
                        ->whereNull("{$alias}.email_failed_at")
                        ->where("{$alias}.created_at", '<', now()->subMinutes(15));
                });
            }
        });
    }

    private function federationDeliveryStatusExpression(string $alias): string
    {
        return "CASE
            WHEN {$alias}.email_sent_at IS NOT NULL THEN 'sent'
            WHEN {$alias}.email_failed_at IS NOT NULL THEN 'failed'
            ELSE 'pending'
        END";
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     * @param list<string> $statuses
     */
    private function applyFederationReviewDeliveryStatusFilter($query, array $statuses): void
    {
        $query->where(function ($q) use ($statuses): void {
            if (in_array('failed', $statuses, true)) {
                $q->orWhere(function ($status) {
                    $status->whereNull('r.email_sent_at')
                        ->whereNull('r.email_skipped_at')
                        ->whereNotNull('r.email_failed_at');
                });
            }

            if (in_array('suppressed', $statuses, true)) {
                $q->orWhereNotNull('r.email_skipped_at');
            }

            if (in_array('processing', $statuses, true)) {
                $q->orWhere(function ($status) {
                    $status->whereNull('r.email_sent_at')
                        ->whereNull('r.email_skipped_at')
                        ->whereNotNull('r.email_claimed_at')
                        ->where('r.email_claimed_at', '<', now()->subMinutes(15));
                });
            }

            if (in_array('pending', $statuses, true)) {
                $q->orWhere(function ($status) {
                    $status->whereNull('r.email_sent_at')
                        ->whereNull('r.email_skipped_at')
                        ->whereNull('r.email_claimed_at')
                        ->where('r.created_at', '<', now()->subMinutes(15));
                });
            }
        });
    }

    private function federationReviewDeliveryStatusExpression(): string
    {
        return "CASE
            WHEN r.email_sent_at IS NOT NULL THEN 'sent'
            WHEN r.email_skipped_at IS NOT NULL THEN 'suppressed'
            WHEN r.email_failed_at IS NOT NULL THEN 'failed'
            WHEN r.email_claimed_at IS NOT NULL THEN 'processing'
            ELSE 'pending'
        END";
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     * @return array<string,int>
     */
    private function queueStatusCounts($query, string $statusColumn): array
    {
        return (clone $query)
            ->selectRaw("{$statusColumn} as status, COUNT(*) as count")
            ->groupByRaw($statusColumn)
            ->pluck('count', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    private function unavailableQueueDiagnostics(string $source): array
    {
        return [
            'source' => $source,
            'available' => false,
            'status_counts' => [],
            'stale_pending' => 0,
            'stale_processing' => 0,
            'failed_recent' => 0,
            'suppressed_recent' => 0,
            'oldest_pending_at' => null,
            'returned' => 0,
        ];
    }

    private function isSuperAdmin(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $role = (string) ($user->role ?? 'member');

        return in_array($role, ['super_admin', 'god'], true)
            || (bool) ($user->is_super_admin ?? false)
            || (bool) ($user->is_god ?? false);
    }

    /**
     * GET /api/v2/admin/email-deliverability/suppressions
     *
     * Returns the current suppression cache (filtered by reason / email).
     * Suppressions are platform-wide because SendGrid scopes them to
     * the account, not the tenant — so a bounce on tenant A's send to
     * jane@example.com blocks every tenant from sending to that address.
     */
    public function suppressions(): JsonResponse
    {
        $this->requireAdmin();

        $limit = max(1, min((int) ($this->input('limit', 50) ?: 50), 200));
        $offset = max(0, (int) ($this->input('offset', 0)));

        $q = DB::table('email_suppression');

        if ($email = trim((string) $this->input('email', ''))) {
            $q->where('email', 'like', '%' . str_replace('%', '\\%', $email) . '%');
        }
        if ($reason = trim((string) $this->input('reason', ''))) {
            $q->where('reason', $reason);
        }

        $total = (clone $q)->count();

        $rows = $q->orderByDesc('suppressed_at')
            ->limit($limit)
            ->offset($offset)
            ->get(['id', 'email', 'reason', 'detail', 'suppressed_at', 'created_at']);

        return $this->respondWithData([
            'rows'   => $rows,
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * DELETE /api/v2/admin/email-deliverability/suppressions/{id}
     *
     * Remove an address from the suppression cache (e.g. after the member
     * tells us they fixed their inbox). Also calls SendGrid's API to clear
     * it on their side so the next send isn't immediately re-suppressed.
     * Platform super-admin only.
     */
    public function removeSuppression(int $id): JsonResponse
    {
        $this->requireSuperAdmin();

        $row = DB::table('email_suppression')->where('id', $id)->first();
        if (!$row) {
            return $this->respondWithError('NOT_FOUND', 'Suppression entry not found.', null, 404);
        }

        // Try SendGrid first; on failure, log but still remove locally so
        // the admin sees their change reflected. Local cache will refill
        // on the next hourly sync if the address is still bad upstream.
        $sgKey = config('mail.sendgrid.api_key');
        if (!empty($sgKey)) {
            try {
                $endpointFor = [
                    'bounce'      => 'bounces',
                    'block'       => 'blocks',
                    'invalid'     => 'invalid_emails',
                    'spam_report' => 'spam_reports',
                    'unsubscribe' => 'asm/suppressions/global',
                ];
                $endpoint = $endpointFor[$row->reason] ?? null;
                if ($endpoint) {
                    \Illuminate\Support\Facades\Http::withToken($sgKey)
                        ->acceptJson()
                        ->timeout(15)
                        ->delete("https://api.sendgrid.com/v3/suppression/{$endpoint}/" . urlencode($row->email));
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to clear suppression on SendGrid', [
                    'email' => $row->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        DB::table('email_suppression')->where('id', $id)->delete();

        return $this->respondWithData(['removed' => true, 'email' => $row->email, 'reason' => $row->reason]);
    }

    /**
     * GET /api/v2/admin/email-deliverability/user/{userId}
     *
     * Per-user history — last 50 emails sent to them, plus their current
     * suppression status. Designed for the "did Joe Bloggs get his welcome
     * email?" workflow.
     */
    public function userHistory(int $userId): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->select(['id', 'email', 'first_name', 'name', 'email_verified_at', 'created_at'])
            ->first();
        if (!$user) {
            return $this->respondWithError('NOT_FOUND', 'User not found in this tenant.', null, 404);
        }

        $logs = DB::table('email_log')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(50)
            ->get([
                'id', 'recipient_email', 'category', 'subject', 'provider', 'status',
                'error', 'sent_at', 'delivered_at', 'bounced_at', 'opened_at', 'created_at',
            ]);

        $suppressions = DB::table('email_suppression')
            ->where('email', $user->email)
            ->get(['id', 'reason', 'detail', 'suppressed_at']);

        return $this->respondWithData([
            'user'         => $user,
            'logs'         => $logs,
            'suppressions' => $suppressions,
        ]);
    }

}

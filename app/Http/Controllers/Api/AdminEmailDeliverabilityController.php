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
            'marketplace_report_notifications' => $this->queueSourceDiagnostics('marketplace_report_notifications', $tenantId),
            'event_reminders' => $this->queueSourceDiagnostics('event_reminders', $tenantId),
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
            ->merge($marketplaceReportRows)
            ->merge($eventReminderRows)
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

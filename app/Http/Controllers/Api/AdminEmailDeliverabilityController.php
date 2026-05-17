<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\EmailMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
        $tenantId = $this->getTenantId();

        $windowDays = (int) ($this->input('days', 7) ?: 7);
        $windowDays = max(1, min($windowDays, 90));

        $rows = DB::table('email_log')
            ->where('tenant_id', $tenantId)
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
        ]);
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
        $tenantId = $this->getTenantId();

        $limit = max(1, min((int) ($this->input('limit', 50) ?: 50), 200));
        $offset = max(0, (int) ($this->input('offset', 0)));

        $q = DB::table('email_log')->where('tenant_id', $tenantId);

        if ($userId = (int) ($this->input('user_id', 0))) {
            $q->where('user_id', $userId);
        }
        if ($email = trim((string) $this->input('email', ''))) {
            $q->where('recipient_email', 'like', '%' . str_replace('%', '\\%', $email) . '%');
        }
        if ($status = trim((string) $this->input('status', ''))) {
            $q->where('status', $status);
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

    private function requireSuperAdmin(): void
    {
        $userId = $this->requireAuth();
        $row = DB::table('users')->where('id', $userId)->first(['role', 'is_super_admin']);
        if (!$row || !($row->is_super_admin || in_array($row->role, ['god', 'super_admin'], true))) {
            abort(403, 'platform super-admin required');
        }
    }
}

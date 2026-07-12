<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EventPublicationWorkflowService;
use App\Support\Authorization\AdminTier;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Accessible, HTML-first Event publication moderation for tenant admins.
 *
 * This concern deliberately owns only the private review projection and the
 * decision forms. All state changes cross EventPublicationWorkflowService so
 * recurring-series fanout, immutable lifecycle history, durable outbox facts,
 * and moderation-queue closure stay canonical across every client.
 */
trait EventModerationParity
{
    private const EVENTS_MODERATION_PAGE_SIZE = 20;

    public function eventsModerationQueue(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->eventsModerationAdminActorOrAbort($tenantSlug);

        $page = $this->eventsModerationPage($request);
        if ($page === null) {
            return $this->eventsModerationPrivateResponse(redirect()->route(
                'govuk-alpha.events.moderation.index',
                ['tenantSlug' => $tenantSlug],
            ));
        }

        $query = $this->eventsModerationPendingQuery();
        $total = (clone $query)->count('e.id');
        $lastPage = max(1, (int) ceil($total / self::EVENTS_MODERATION_PAGE_SIZE));
        if ($page > $lastPage) {
            return $this->eventsModerationPrivateResponse(redirect()->route(
                'govuk-alpha.events.moderation.index',
                array_filter([
                    'tenantSlug' => $tenantSlug,
                    'page' => $lastPage > 1 ? $lastPage : null,
                ]),
            ));
        }

        $items = $query
            ->orderBy('mq.submitted_at')
            ->orderBy('e.id')
            ->forPage($page, self::EVENTS_MODERATION_PAGE_SIZE)
            ->get()
            ->map(fn (object $row): array => $this->eventsModerationMapRow($row))
            ->all();

        return $this->eventsModerationPrivateResponse($this->view(
            'accessible-frontend::event-moderation-queue',
            [
                'title' => __('govuk_alpha_events.moderation.title'),
                'tenantSlug' => $tenantSlug,
                'activeNav' => 'events',
                'items' => $items,
                'status' => self::asStr($request->query('status')),
                'pagination' => [
                    'page' => $page,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'has_previous' => $page > 1,
                    'has_next' => $page < $lastPage,
                ],
            ],
        ));
    }

    public function eventsModerationApproveConfirmation(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response {
        $this->eventsModerationAdminActorOrAbort($tenantSlug);
        $event = $this->eventsModerationPendingEventOrAbort($id);

        return $this->eventsModerationDecisionResponse(
            $tenantSlug,
            $event,
            'approve',
            null,
            '',
            200,
        );
    }

    public function eventsModerationApprove(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $actor = $this->eventsModerationAdminActorOrAbort($tenantSlug);
        $event = $this->eventsModerationPendingEventOrAbort($id);
        if (self::asStr($request->input('confirmation')) !== 'approve') {
            return $this->eventsModerationDecisionResponse(
                $tenantSlug,
                $event,
                'approve',
                'confirmation_required',
                '',
                422,
            );
        }

        try {
            app(EventPublicationWorkflowService::class)->approveModerationDecision($id, $actor);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->eventsModerationPrivateResponse(redirect()->route(
                'govuk-alpha.events.moderation.index',
                ['tenantSlug' => $tenantSlug, 'status' => 'action-failed'],
            ));
        }

        return $this->eventsModerationPrivateResponse(redirect()->route(
            'govuk-alpha.events.moderation.index',
            ['tenantSlug' => $tenantSlug, 'status' => 'approved'],
        ));
    }

    public function eventsModerationRejectConfirmation(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response {
        $this->eventsModerationAdminActorOrAbort($tenantSlug);
        $event = $this->eventsModerationPendingEventOrAbort($id);

        return $this->eventsModerationDecisionResponse(
            $tenantSlug,
            $event,
            'reject',
            null,
            '',
            200,
        );
    }

    public function eventsModerationReject(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $actor = $this->eventsModerationAdminActorOrAbort($tenantSlug);
        $event = $this->eventsModerationPendingEventOrAbort($id);
        $reason = trim(self::asStr($request->input('reason')));
        $error = match (true) {
            $reason === '' => 'reason_required',
            mb_strlen($reason) > 2000 => 'reason_too_long',
            self::asStr($request->input('confirmation')) !== 'reject' => 'confirmation_required',
            default => null,
        };
        if ($error !== null) {
            return $this->eventsModerationDecisionResponse(
                $tenantSlug,
                $event,
                'reject',
                $error,
                mb_substr($reason, 0, 2000),
                422,
            );
        }

        try {
            app(EventPublicationWorkflowService::class)->rejectModerationDecision($id, $actor, $reason);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->eventsModerationPrivateResponse(redirect()->route(
                'govuk-alpha.events.moderation.index',
                ['tenantSlug' => $tenantSlug, 'status' => 'action-failed'],
            ));
        }

        return $this->eventsModerationPrivateResponse(redirect()->route(
            'govuk-alpha.events.moderation.index',
            ['tenantSlug' => $tenantSlug, 'status' => 'rejected'],
        ));
    }

    /** Used by the maintained Events index to expose the queue only to true admins. */
    private function eventsModerationLinkVisible(): bool
    {
        $userId = $this->currentUserId();
        if ($userId === null || ! TenantContext::hasFeature('events')) {
            return false;
        }

        $actor = User::withoutGlobalScopes()
            ->where('tenant_id', TenantContext::currentId())
            ->whereKey($userId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first([
                'id',
                'role',
                'is_admin',
                'is_super_admin',
                'is_tenant_super_admin',
                'is_god',
            ]);

        return $actor instanceof User && $this->eventsModerationIsTenantAdmin($actor);
    }

    private function eventsModerationAdminActorOrAbort(string $tenantSlug): User
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);

        $userId = $this->currentUserId();
        abort_if($userId === null, 401);
        $actor = User::withoutGlobalScopes()
            ->where('tenant_id', TenantContext::currentId())
            ->whereKey($userId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();
        abort_unless($actor instanceof User && $this->eventsModerationIsTenantAdmin($actor), 403);

        return $actor;
    }

    private function eventsModerationIsTenantAdmin(User $actor): bool
    {
        return AdminTier::allows($actor);
    }

    private function eventsModerationPage(Request $request): ?int
    {
        $value = $request->query('page');
        if ($value === null) {
            return 1;
        }

        $raw = self::asStr($value);
        if ($raw === '' || preg_match('/^[1-9][0-9]*$/', $raw) !== 1) {
            return null;
        }

        return min((int) $raw, 1_000_000);
    }

    private function eventsModerationPendingQuery(): Builder
    {
        $tenantId = (int) TenantContext::getId();
        $pendingQueue = DB::table('content_moderation_queue')
            ->selectRaw('content_id, MIN(created_at) AS submitted_at')
            ->where('tenant_id', $tenantId)
            ->where('content_type', 'event')
            ->where('status', 'pending')
            ->groupBy('content_id');

        return DB::table('events as e')
            ->joinSub($pendingQueue, 'mq', static function ($join): void {
                $join->on('mq.content_id', '=', 'e.id');
            })
            ->leftJoin('users as organizer', static function ($join) use ($tenantId): void {
                $join->on('organizer.id', '=', 'e.user_id')
                    ->where('organizer.tenant_id', '=', $tenantId);
            })
            ->where('e.tenant_id', $tenantId)
            ->where('e.publication_status', 'pending_review')
            ->where(static function (Builder $query): void {
                $query->whereNull('e.parent_event_id')->orWhere('e.parent_event_id', 0);
            })
            ->select([
                'e.id',
                'e.title',
                'e.description',
                'e.start_time',
                'e.end_time',
                'e.timezone',
                'e.all_day',
                'e.location',
                'e.is_online',
                'e.is_recurring_template',
                'organizer.name as organizer_name',
                'organizer.first_name as organizer_first_name',
                'organizer.last_name as organizer_last_name',
                'mq.submitted_at',
            ]);
    }

    /** @return array<string, mixed> */
    private function eventsModerationPendingEventOrAbort(int $eventId): array
    {
        $row = $this->eventsModerationPendingQuery()
            ->where('e.id', $eventId)
            ->first();
        abort_unless(is_object($row), 404);

        return $this->eventsModerationMapRow($row);
    }

    /** @return array<string, mixed> */
    private function eventsModerationMapRow(object $row): array
    {
        $organizer = trim((string) ($row->organizer_name ?? ''));
        if ($organizer === '') {
            $organizer = trim(implode(' ', array_filter([
                (string) ($row->organizer_first_name ?? ''),
                (string) ($row->organizer_last_name ?? ''),
            ])));
        }

        return [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'description' => (string) $row->description,
            'start_time' => $row->start_time,
            'end_time' => $row->end_time,
            'timezone' => (string) ($row->timezone ?: 'UTC'),
            'all_day' => (bool) $row->all_day,
            'location' => $row->location !== null ? (string) $row->location : null,
            'is_online' => (bool) $row->is_online,
            'is_recurring_template' => (bool) $row->is_recurring_template,
            'organizer_name' => $organizer !== ''
                ? $organizer
                : __('govuk_alpha_events.moderation.organizer_unavailable'),
            'submitted_at' => $row->submitted_at,
        ];
    }

    /** @param array<string, mixed> $event */
    private function eventsModerationDecisionResponse(
        string $tenantSlug,
        array $event,
        string $decision,
        ?string $error,
        string $reason,
        int $status,
    ): Response {
        $response = $this->view('accessible-frontend::event-moderation-decision', [
            'title' => __(
                $decision === 'approve'
                    ? 'govuk_alpha_events.moderation.approve_title'
                    : 'govuk_alpha_events.moderation.reject_title',
            ),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => $event,
            'decision' => $decision,
            'error' => $error,
            'reason' => $reason,
        ], $status);

        return $this->eventsModerationPrivateResponse($response);
    }

    /** @template T of Response|RedirectResponse @param T $response @return T */
    private function eventsModerationPrivateResponse(Response|RedirectResponse $response): Response|RedirectResponse
    {
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->setVary(['Authorization', 'Cookie'], false);

        return $response;
    }
}

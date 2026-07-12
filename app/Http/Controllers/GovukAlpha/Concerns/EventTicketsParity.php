<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Exceptions\EventTicketingException;
use App\Http\Resources\EventTicketResource;
use App\Models\User;
use App\Services\EventService;
use App\Services\EventTicketEntitlementService;
use App\Services\EventTicketQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/** HTML-first, free-only ticket catalogue, self-allocation, and cancellation. */
trait EventTicketsParity
{
    public function eventsTickets(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $actor = $this->eventsTicketActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }

        try {
            $catalogue = EventTicketResource::catalogue(
                app(EventTicketQueryService::class)->read($id, $actor),
            );
            $event = EventService::getById($id, (int) $actor->id);
            if ($event === null) {
                throw new EventTicketingException('event_ticket_event_not_found');
            }
        } catch (EventTicketingException $exception) {
            return $this->eventsTicketReadFailure($exception, $tenantSlug);
        }

        return $this->eventsTicketPrivateResponse($this->view(
            'accessible-frontend::event-tickets',
            [
                'title' => __('event_tickets.title'),
                'tenantSlug' => $tenantSlug,
                'activeNav' => 'events',
                'eventId' => $id,
                'eventTitle' => (string) ($event['title'] ?? ''),
                'catalogue' => $catalogue,
                'status' => is_string($request->query('status'))
                    ? trim((string) $request->query('status'))
                    : null,
            ],
        ));
    }

    public function eventsTicketAllocate(
        Request $request,
        string $tenantSlug,
        int $id,
        int $ticketTypeId,
    ): RedirectResponse {
        $actor = $this->eventsTicketActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $units = $this->eventsTicketPositiveInteger($request->input('units'));
        $key = $this->eventsTicketIdempotencyKey($request);
        if ($units === null || $units > 1000 || $key === null) {
            return $this->eventsTicketIndexRedirect($tenantSlug, $id)
                ->withErrors(['ticket' => __('event_tickets.validation_error')]);
        }

        try {
            $queries = app(EventTicketQueryService::class);
            app(EventTicketEntitlementService::class)->allocateSelf(
                $id,
                $ticketTypeId,
                $queries->confirmedRegistrationId($id, (int) $actor->id),
                $actor,
                $units,
                $key,
            );
        } catch (EventTicketingException $exception) {
            return $this->eventsTicketMutationFailure(
                $exception,
                $tenantSlug,
                $id,
                'allocate',
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->eventsTicketIndexRedirect($tenantSlug, $id)
                ->withErrors(['ticket' => __('event_tickets.allocate_error')]);
        }

        return $this->eventsTicketIndexRedirect($tenantSlug, $id, 'allocated');
    }

    public function eventsTicketCancelForm(
        Request $request,
        string $tenantSlug,
        int $id,
        int $entitlementId,
    ): Response|RedirectResponse {
        $actor = $this->eventsTicketActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }

        try {
            $catalogue = EventTicketResource::catalogue(
                app(EventTicketQueryService::class)->read($id, $actor),
            );
            $entitlement = $this->eventsTicketOwnEntitlement($catalogue, $entitlementId);
            $ticket = $this->eventsTicketType($catalogue, (int) $entitlement['ticket_type_id']);
            if (($entitlement['kind'] ?? null) !== 'free') {
                throw new EventTicketingException('event_ticket_time_credit_gateway_unavailable');
            }
        } catch (EventTicketingException $exception) {
            return $this->eventsTicketReadFailure($exception, $tenantSlug, $id);
        }

        return $this->eventsTicketPrivateResponse($this->view(
            'accessible-frontend::event-ticket-cancel',
            [
                'title' => __('event_tickets.cancel_title'),
                'tenantSlug' => $tenantSlug,
                'activeNav' => 'events',
                'eventId' => $id,
                'entitlement' => $entitlement,
                'ticket' => $ticket,
            ],
        ));
    }

    public function eventsTicketCancel(
        Request $request,
        string $tenantSlug,
        int $id,
        int $entitlementId,
    ): RedirectResponse {
        $actor = $this->eventsTicketActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $expectedVersion = $this->eventsTicketPositiveInteger(
            $request->input('expected_version'),
        );
        $key = $this->eventsTicketIdempotencyKey($request);
        $reason = is_string($request->input('reason'))
            ? trim((string) $request->input('reason'))
            : '';
        if ($expectedVersion === null || $key === null || $reason === '' || mb_strlen($reason) > 500) {
            return redirect()->route('govuk-alpha.events.tickets.cancel.form', [
                'tenantSlug' => $tenantSlug,
                'id' => $id,
                'entitlementId' => $entitlementId,
            ])->withErrors(['ticket' => __('event_tickets.validation_error')])
                ->withInput($request->except(['idempotency_key']));
        }

        try {
            $catalogue = EventTicketResource::catalogue(
                app(EventTicketQueryService::class)->read($id, $actor),
            );
            $entitlement = $this->eventsTicketOwnEntitlement($catalogue, $entitlementId);
            if (($entitlement['kind'] ?? null) !== 'free') {
                throw new EventTicketingException('event_ticket_time_credit_gateway_unavailable');
            }
            app(EventTicketEntitlementService::class)->cancel(
                $id,
                $entitlementId,
                $actor,
                $expectedVersion,
                $reason,
                $key,
            );
        } catch (EventTicketingException $exception) {
            return $this->eventsTicketMutationFailure(
                $exception,
                $tenantSlug,
                $id,
                'cancel',
                $entitlementId,
                $request,
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('govuk-alpha.events.tickets.cancel.form', [
                'tenantSlug' => $tenantSlug,
                'id' => $id,
                'entitlementId' => $entitlementId,
            ])->withErrors(['ticket' => __('event_tickets.cancel_error')]);
        }

        return $this->eventsTicketIndexRedirect($tenantSlug, $id, 'cancelled');
    }

    private function eventsTicketActor(string $tenantSlug): User|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', compact('tenantSlug'));
        }

        return $this->accessibleEventActor($userId);
    }

    private function eventsTicketIdempotencyKey(Request $request): ?string
    {
        $key = $request->input('idempotency_key');
        if (! is_string($key)) {
            return null;
        }
        $key = trim($key);

        return $key !== '' && mb_strlen($key) <= 512 ? $key : null;
    }

    private function eventsTicketPositiveInteger(mixed $value): ?int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $parsed === false ? null : (int) $parsed;
    }

    /** @param array<string,mixed> $catalogue @return array<string,mixed> */
    private function eventsTicketOwnEntitlement(array $catalogue, int $entitlementId): array
    {
        foreach (($catalogue['own_entitlements'] ?? []) as $entitlement) {
            if (is_array($entitlement) && (int) ($entitlement['id'] ?? 0) === $entitlementId) {
                return $entitlement;
            }
        }

        throw new EventTicketingException('event_ticket_entitlement_not_found');
    }

    /** @param array<string,mixed> $catalogue @return array<string,mixed> */
    private function eventsTicketType(array $catalogue, int $ticketTypeId): array
    {
        foreach (($catalogue['ticket_types'] ?? []) as $ticket) {
            if (is_array($ticket) && (int) ($ticket['id'] ?? 0) === $ticketTypeId) {
                return $ticket;
            }
        }

        throw new EventTicketingException('event_ticket_type_not_found');
    }

    private function eventsTicketReadFailure(
        EventTicketingException $exception,
        string $tenantSlug,
        ?int $eventId = null,
    ): RedirectResponse {
        $reason = $exception->reasonCode;
        if (str_contains($reason, 'not_found') || str_contains($reason, 'actor_not_found')) {
            abort(404);
        }
        if (str_contains($reason, 'denied')) {
            abort(403);
        }
        if (str_contains($reason, 'schema_unavailable')
            || str_contains($reason, 'tenant_context_required')) {
            abort(503);
        }

        $parameters = ['tenantSlug' => $tenantSlug];
        if ($eventId !== null) {
            $parameters['id'] = $eventId;
        }

        return redirect()->route(
            $eventId === null ? 'govuk-alpha.events.index' : 'govuk-alpha.events.tickets.index',
            $parameters,
        )->withErrors(['ticket' => __('event_tickets.load_error')]);
    }

    private function eventsTicketMutationFailure(
        EventTicketingException $exception,
        string $tenantSlug,
        int $eventId,
        string $operation,
        ?int $entitlementId = null,
        ?Request $request = null,
    ): RedirectResponse {
        $reason = $exception->reasonCode;
        if (str_contains($reason, 'not_found') || str_contains($reason, 'actor_not_found')) {
            abort(404);
        }
        if (in_array($reason, [
            'event_ticket_manage_finance_denied',
            'event_ticket_reconciliation_denied',
            'event_ticket_view_denied',
        ], true) || str_contains($reason, 'identity_mismatch')) {
            abort(403);
        }
        if (str_contains($reason, 'schema_unavailable')
            || str_contains($reason, 'tenant_context_required')) {
            abort(503);
        }

        $message = $operation === 'cancel'
            ? __('event_tickets.cancel_error')
            : __('event_tickets.allocate_error');
        if ($operation === 'cancel' && $entitlementId !== null) {
            $redirect = redirect()->route('govuk-alpha.events.tickets.cancel.form', [
                'tenantSlug' => $tenantSlug,
                'id' => $eventId,
                'entitlementId' => $entitlementId,
            ])->withErrors(['ticket' => $message]);

            return $request === null
                ? $redirect
                : $redirect->withInput($request->except(['idempotency_key']));
        }

        return $this->eventsTicketIndexRedirect($tenantSlug, $eventId)
            ->withErrors(['ticket' => $message]);
    }

    private function eventsTicketIndexRedirect(
        string $tenantSlug,
        int $eventId,
        ?string $status = null,
    ): RedirectResponse {
        $parameters = ['tenantSlug' => $tenantSlug, 'id' => $eventId];
        if ($status !== null) {
            $parameters['status'] = $status;
        }

        return redirect()->route('govuk-alpha.events.tickets.index', $parameters);
    }

    private function eventsTicketPrivateResponse(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->setVary('Cookie', false);

        return $response;
    }
}

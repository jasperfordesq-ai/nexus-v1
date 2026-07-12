<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Enums\EventAttendanceAction;
use App\Enums\EventPeopleBulkAction;
use App\Exceptions\EventAttendanceException;
use App\Exceptions\EventParticipationException;
use App\Exceptions\EventRegistrationException;
use App\Exceptions\EventWaitlistException;
use App\Exceptions\SafeguardingPolicyException;
use App\Models\Poll;
use App\Models\Event;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Services\EventCalendarService;
use App\Services\EventAttendanceService;
use App\Services\EventPeopleBulkService;
use App\Services\EventPeopleService;
use App\Services\EventNotificationDeliveryModeResolver;
use App\Services\EventService;
use App\Services\EventWaitlistService;
use App\Services\PollService;
use App\Services\UgcTranslationService;
use App\Support\Events\EventContractMapper;
use App\Support\Events\EventPeopleBulkOperation;
use App\Support\Events\EventPeopleQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use App\Enums\EventNotificationDeliveryMode;

/**
 * Events — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * The base events surface (list / detail / create / edit / cancel / delete /
 * RSVP / waitlist / poll-vote / check-in / recurring create) already lives in
 * AlphaController. This trait closes the remaining React-parity gaps as new
 * standalone accessible pages, each calling the SAME service the React API
 * controller calls so notification, ownership and recurrence logic is never
 * reimplemented:
 *
 *   - eventsBrowse            — category toggle-button browse (EventsPage ToggleButtonGroup)
 *   - eventsMap               — accessible location map / directions (LocationMapCard)
 *   - eventsRecurringEdit /
 *     eventsUpdateRecurring   — edit a recurring-series occurrence with "this / all future"
 *                               scope (CreateEventPage scope modal + PUT /v2/events/{id}/recurring)
 *   - eventsPolls /
 *     eventsUpdatePolls       — attach / detach owner polls to an event
 *                               (CreateEventPage poll Select + POST/PUT poll_ids[])
 *   - eventsTranslate /
 *     eventsRunTranslate      — on-demand description translation (TranslateButton)
 *   - eventsCalendar*         — privacy-safe calendar exports, add-to-calendar actions,
 *                               and owner-scoped personal feed subscriptions
 */
trait EventsParity
{
    public function eventsAcceptWaitlistOffer(
        Request $request,
        string $tenantSlug,
        int $id,
    ): RedirectResponse {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', [
                'tenantSlug' => $tenantSlug,
                'status' => 'auth-required',
            ]);
        }

        abort_if(EventService::getById($id, $userId) === null, 404);

        try {
            app(EventWaitlistService::class)->acceptActiveOffer(
                $id,
                $userId,
                $this->accessibleEventActor($userId),
                (string) $this->accessibleEventMutationKey($request),
            );
        } catch (SafeguardingPolicyException|EventParticipationException|EventRegistrationException|EventWaitlistException $exception) {
            Log::notice('Accessible event waitlist offer acceptance rejected', [
                'tenant_id' => TenantContext::getId(),
                'event_id' => $id,
                'reason_code' => $exception->reasonCode,
            ]);

            return redirect()->route('govuk-alpha.events.show', [
                'tenantSlug' => $tenantSlug,
                'id' => $id,
                'status' => 'waitlist-offer-accept-failed',
            ]);
        }

        return redirect()->route('govuk-alpha.events.show', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => 'waitlist-offer-accepted',
        ]);
    }

    // -----------------------------------------------------------------
    //  Canonical accessible view models
    // -----------------------------------------------------------------

    // -----------------------------------------------------------------
    //  Event People and manual attendance operations
    // -----------------------------------------------------------------

    public function eventsPeople(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $context = $this->eventsOperationalContext($tenantSlug, $id, 'people');
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        [$event, $actor] = $context;
        try {
            $query = EventPeopleQuery::fromArray([
                'page' => self::asStr($request->query('page'), '1'),
                'per_page' => '25',
                'search' => self::asStr($request->query('search')),
                'registration_state' => self::asStr($request->query('registration_state')),
                'waitlist_state' => self::asStr($request->query('waitlist_state')),
                'attendance_state' => self::asStr($request->query('attendance_state')),
                'engagement_state' => self::asStr($request->query('engagement_state')),
                'sort' => self::asStr($request->query('sort'), 'name'),
                'direction' => self::asStr($request->query('direction'), 'asc'),
            ]);
        } catch (EventRegistrationException) {
            return redirect()->route('govuk-alpha.events.people', [
                'tenantSlug' => $tenantSlug,
                'id' => $id,
            ]);
        }
        $result = app(EventPeopleService::class)->paginateForActor($event, $actor, $query);

        return $this->view('accessible-frontend::event-people', [
            'title' => __('govuk_alpha.events.people_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => $event,
            'canManageAttendance' => app(EventPolicy::class)->manageAttendance($actor, $event),
            'people' => $result['items'],
            'metrics' => $result['metrics'],
            'query' => $query,
            'total' => $result['total'],
            'totalPages' => $result['total'] > 0
                ? (int) ceil($result['total'] / $result['per_page'])
                : 0,
            'status' => self::asStr($request->query('status')) ?: null,
            'updated' => max(0, (int) $request->query('updated', 0)),
            'failed' => max(0, (int) $request->query('failed', 0)),
        ]);
    }

    public function eventsUpdatePeople(
        Request $request,
        string $tenantSlug,
        int $id,
    ): RedirectResponse {
        $context = $this->eventsOperationalContext($tenantSlug, $id, 'people');
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        [$event, $actor] = $context;
        $action = EventPeopleBulkAction::tryFrom(self::asStr($request->input('action')));
        $allowed = [
            EventPeopleBulkAction::Approve,
            EventPeopleBulkAction::Reject,
            EventPeopleBulkAction::Cancel,
        ];
        $rawIds = $request->input('user_ids', []);
        $userIds = [];
        if (is_array($rawIds)) {
            foreach ($rawIds as $rawId) {
                if ((is_int($rawId) || (is_string($rawId) && ctype_digit($rawId)))
                    && (int) $rawId > 0) {
                    $userIds[(int) $rawId] = true;
                }
            }
        }
        $userIds = array_keys($userIds);
        $reason = trim(self::asStr($request->input('reason')));
        $confirmed = self::asStr($request->input('confirmation')) === '1';
        if ($action === null
            || ! in_array($action, $allowed, true)
            || $userIds === []
            || count($userIds) > EventPeopleBulkService::MAX_OPERATIONS
            || ! $confirmed
            || (in_array($action, [EventPeopleBulkAction::Reject, EventPeopleBulkAction::Cancel], true)
                && $reason === '')) {
            return redirect()->route('govuk-alpha.events.people', [
                'tenantSlug' => $tenantSlug,
                'id' => $id,
                'status' => 'people-invalid',
            ]);
        }

        $tenantId = (int) TenantContext::getId();
        $versions = DB::table('event_registrations')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $id)
            ->whereIn('user_id', $userIds)
            ->pluck('registration_version', 'user_id');
        $requestKey = self::asStr($request->input('idempotency_key'));
        if ($requestKey === '') {
            $requestKey = (string) \Illuminate\Support\Str::uuid();
        }
        $operations = [];
        foreach ($userIds as $userId) {
            $operations[] = new EventPeopleBulkOperation(
                $userId,
                $action,
                max(0, (int) ($versions[$userId] ?? 0)),
                'accessible-people:' . hash('sha256', "{$requestKey}|{$action->value}|{$userId}"),
                $reason !== '' ? $reason : null,
            );
        }

        try {
            $result = app(EventPeopleBulkService::class)->execute($event, $actor, $operations);

            return redirect()->route('govuk-alpha.events.people', [
                'tenantSlug' => $tenantSlug,
                'id' => $id,
                'status' => $result['failed'] > 0 ? 'people-partial' : 'people-updated',
                'updated' => $result['succeeded'],
                'failed' => $result['failed'],
            ]);
        } catch (\Throwable $exception) {
            Log::notice('Accessible Event People operation rejected', [
                'tenant_id' => $tenantId,
                'event_id' => $id,
                'exception' => $exception::class,
            ]);

            return redirect()->route('govuk-alpha.events.people', [
                'tenantSlug' => $tenantSlug,
                'id' => $id,
                'status' => 'people-failed',
            ]);
        }
    }

    public function eventsCheckIn(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $context = $this->eventsOperationalContext($tenantSlug, $id, 'attendance');
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        [$event, $actor] = $context;
        try {
            $query = EventPeopleQuery::fromArray([
                'page' => self::asStr($request->query('page'), '1'),
                'per_page' => '25',
                'search' => self::asStr($request->query('search')),
                'attendance_state' => self::asStr($request->query('attendance_state')),
                'sort' => self::asStr($request->query('sort'), 'name'),
                'direction' => self::asStr($request->query('direction'), 'asc'),
            ]);
        } catch (EventRegistrationException) {
            return redirect()->route('govuk-alpha.events.check-in', [
                'tenantSlug' => $tenantSlug,
                'id' => $id,
            ]);
        }
        $result = app(EventPeopleService::class)->paginateForActor($event, $actor, $query);

        return $this->view('accessible-frontend::event-check-in', [
            'title' => __('govuk_alpha.events.check_in_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => $event,
            'people' => $result['items'],
            'metrics' => $result['metrics'],
            'query' => $query,
            'total' => $result['total'],
            'totalPages' => $result['total'] > 0
                ? (int) ceil($result['total'] / $result['per_page'])
                : 0,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function eventsUpdateAttendance(
        Request $request,
        string $tenantSlug,
        int $id,
        int $userId,
    ): RedirectResponse {
        $context = $this->eventsOperationalContext($tenantSlug, $id, 'attendance');
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        [$event, $actor] = $context;
        $action = EventAttendanceAction::tryFrom(self::asStr($request->input('action')));
        $versionValue = $request->input('expected_version');
        $expectedVersion = is_int($versionValue)
            ? $versionValue
            : (is_string($versionValue) && ctype_digit($versionValue) ? (int) $versionValue : -1);
        $reason = trim(self::asStr($request->input('reason')));
        if ($action === null
            || $expectedVersion < 0
            || self::asStr($request->input('confirmation')) !== '1'
            || ($action === EventAttendanceAction::Undo && $reason === '')
            || ! app(EventPeopleService::class)->attendanceSubjectVisible($event, $userId)) {
            return redirect()->route('govuk-alpha.events.check-in', [
                'tenantSlug' => $tenantSlug,
                'id' => $id,
                'status' => 'attendance-invalid',
            ]);
        }

        try {
            app(EventAttendanceService::class)->transition(
                $id,
                $userId,
                $action,
                $actor,
                $expectedVersion,
                $reason !== '' ? $reason : null,
                self::asStr($request->input('idempotency_key'))
                    ?: (string) \Illuminate\Support\Str::uuid(),
            );
            $status = 'attendance-updated';
        } catch (EventAttendanceException $exception) {
            $status = $exception->reasonCode === 'event_attendance_version_conflict'
                ? 'attendance-conflict'
                : 'attendance-failed';
            Log::notice('Accessible Event attendance operation rejected', [
                'tenant_id' => TenantContext::getId(),
                'event_id' => $id,
                'reason_code' => $exception->reasonCode,
            ]);
        }

        return redirect()->route('govuk-alpha.events.check-in', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $status,
        ]);
    }

    /** @return array{Event,User}|RedirectResponse */
    private function eventsOperationalContext(
        string $tenantSlug,
        int $id,
        string $projection,
    ): array|RedirectResponse {
        $userId = $this->eventsParityGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }
        $actor = $this->accessibleEventActor($userId);
        $event = Event::withoutGlobalScopes()
            ->where('tenant_id', TenantContext::currentId())
            ->whereKey($id)
            ->first();
        abort_unless($event instanceof Event, 404);
        $policy = app(EventPolicy::class);
        $allowed = $projection === 'people'
            ? $policy->manageRegistration($actor, $event)
                && $policy->viewRoster($actor, $event)
                && $policy->viewWaitlist($actor, $event)
            : $policy->manageAttendance($actor, $event)
                && $policy->viewRoster($actor, $event);
        abort_unless($allowed, 403);

        return [$event, $actor];
    }

    /**
     * Project tenant-scoped EventService rows through the same v2 contract
     * mapper used by the React API. The accessible frontend remains HTML-first,
     * but relationship, schedule, location and online-access decisions must not
     * drift into a second implementation in Blade templates.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    private function eventsCanonicalViewModels(array $events, ?int $viewerId, bool $detail = false): array
    {
        if ($events === []) {
            return [];
        }

        $factsById = EventService::getContractFacts($events, $viewerId);

        return array_values(array_map(
            static function (array $event) use ($factsById, $detail): array {
                $eventId = (int) ($event['id'] ?? 0);

                return EventContractMapper::event(
                    $event,
                    $factsById[$eventId] ?? [],
                    $detail
                );
            },
            $events
        ));
    }

    /** @param array<string, mixed> $event */
    private function eventsCanonicalViewModel(array $event, ?int $viewerId, bool $detail = true): array
    {
        return $this->eventsCanonicalViewModels([$event], $viewerId, $detail)[0];
    }

    /**
     * @param  array<int, array<string, mixed>>  $attendees
     * @return array<int, array<string, mixed>>
     */
    private function eventsCanonicalRoster(array $attendees): array
    {
        return array_values(array_map(
            static fn (array $attendee): array => EventContractMapper::roster($attendee),
            $attendees
        ));
    }

    // -----------------------------------------------------------------
    //  Shared guards
    // -----------------------------------------------------------------

    /**
     * Auth + feature gate for every accessible events-parity action. Returns the
     * authenticated user id, or a redirect (login) the caller must return.
     */
    private function eventsParityGuard(string $tenantSlug): int|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        return $userId;
    }

    /**
     * Resolve an event the current user owns, or abort. Mirrors the
     * organiser-only edit/delete guards: a cross-tenant id resolves to null
     * (EventService is tenant-scoped) → 404; a non-owner → 403.
     *
     * @return array<string, mixed>
     */
    private function eventsParityOwnedEvent(int $id, int $userId): array
    {
        $event = EventService::getById($id, $userId);
        abort_if($event === null, 404);
        abort_unless((int) ($event['user_id'] ?? 0) === $userId, 403);

        return $event;
    }

    // -----------------------------------------------------------------
    //  Category toggle-button browse  (EventsPage ToggleButtonGroup)
    //  GET /events/browse — accessible single-select category chooser that
    //  links straight into the existing /events list with ?category_id=.
    //  Public (parity with the React list, which renders for everyone).
    // -----------------------------------------------------------------

    public function eventsBrowse(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);

        $categories = $this->categoriesForTypes(['events', 'event']);
        $selected = self::asStr($request->query('category_id'));
        $selectedId = $selected !== '' ? (int) $selected : null;

        return $this->view('accessible-frontend::events-browse', [
            'title' => __('govuk_alpha_events.browse.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'categories' => $categories,
            'selectedCategoryId' => $selectedId,
        ]);
    }

    // -----------------------------------------------------------------
    //  Accessible location map  (LocationMapCard)
    //  GET /events/{id}/map — static, no-JS map + directions for an event
    //  that has a physical location. Maps feature flag applies (parity with
    //  React, which only renders LocationMapCard when hasFeature('maps')).
    // -----------------------------------------------------------------

    public function eventsMap(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);
        abort_unless(TenantContext::hasFeature('maps'), 403);

        $viewerId = $this->currentUserId();
        $legacyEvent = EventService::getById($id, $viewerId);
        abort_if($legacyEvent === null, 404);
        $event = $this->eventsCanonicalViewModel($legacyEvent, $viewerId);

        $locationFacts = is_array($event['location'] ?? null) ? $event['location'] : [];
        $lat = isset($locationFacts['latitude']) && $locationFacts['latitude'] !== null
            ? (float) $locationFacts['latitude']
            : null;
        $lng = isset($locationFacts['longitude']) && $locationFacts['longitude'] !== null
            ? (float) $locationFacts['longitude']
            : null;
        $location = trim(self::asStr($locationFacts['label'] ?? ''));
        $locationMode = self::asStr($locationFacts['mode'] ?? 'in_person');
        $isOnline = $locationMode === 'online';

        return $this->view('accessible-frontend::events-map', [
            'title' => __('govuk_alpha_events.map.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => $event,
            'lat' => $lat,
            'lng' => $lng,
            'location' => $location !== '' ? $location : null,
            'isOnline' => $isOnline,
            'hasCoordinates' => $lat !== null && $lng !== null && $locationMode !== 'online',
        ]);
    }

    // -----------------------------------------------------------------
    //  Recurring-series occurrence edit with scope  (CreateEventPage scope modal)
    //  GET  /events/{id}/recurring-edit   — form pre-populated from the occurrence
    //  POST /events/{id}/recurring-edit   — PUT /v2/events/{id}/recurring (scope)
    //  Owner-only. Non-series events are redirected to the plain edit form.
    // -----------------------------------------------------------------

    public function eventsRecurringEdit(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $userId = $this->eventsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $event = $this->eventsParityOwnedEvent($id, $userId);

        // Only recurring occurrences/templates get the scope flow; everything
        // else uses the existing single-event edit form.
        if (!$this->eventsIsSeries($event)) {
            return redirect()->route('govuk-alpha.events.edit', ['tenantSlug' => $tenantSlug, 'id' => $id]);
        }

        $occurrences = is_array($event['series_occurrences'] ?? null) ? $event['series_occurrences'] : [];

        return $this->view('accessible-frontend::events-recurring-edit', [
            'title' => __('govuk_alpha_events.recurring_edit.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => $event,
            'occurrences' => $occurrences,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function eventsUpdateRecurring(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->eventsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $event = $this->eventsParityOwnedEvent($id, $userId);

        if (!$this->eventsIsSeries($event)) {
            return redirect()->route('govuk-alpha.events.edit', ['tenantSlug' => $tenantSlug, 'id' => $id]);
        }

        // Whitelist scope exactly like EventsController::updateRecurring.
        $scope = $this->allowed($request->input('scope'), ['single', 'all'], 'single');

        // Content fields only — EventService::updateRecurring drops start/end
        // for scope=all itself, but we still pass the occurrence's own time for
        // scope=single so a single-occurrence edit can move that occurrence.
        $data = $this->eventInput($request);

        $ok = false;
        try {
            $ok = EventService::updateRecurring($id, $userId, $data, (string) $scope);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('govuk-alpha.events.recurring.edit', ['tenantSlug' => $tenantSlug, 'id' => $id])
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Throwable $e) {
            report($e);
        }

        if ($ok) {
            // Notify RSVPs of meaningful changes — parity with the API update path.
            try {
                $changes = EventService::getLastMeaningfulUpdateChanges();
                if (!empty($changes)
                    && EventNotificationDeliveryModeResolver::resolve(
                        (int) TenantContext::getId(),
                    ) !== EventNotificationDeliveryMode::OutboxAuthoritative) {
                    app(\App\Services\EventNotificationService::class)->notifyEventUpdated($id, $changes);
                }
            } catch (\Throwable $e) {
                Log::warning('Alpha recurring event update notification failed', ['error' => $e->getMessage()]);
            }
        }

        return redirect()->route('govuk-alpha.events.show', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'event-updated' : 'event-update-failed',
        ]);
    }

    /**
     * True when an event is part of a recurring series (template or occurrence).
     * getById flags this with is_series and exposes series_occurrences.
     *
     * @param array<string, mixed> $event
     */
    private function eventsIsSeries(array $event): bool
    {
        return !empty($event['is_series'])
            || !empty($event['is_recurring_template'])
            || !empty($event['parent_event_id']);
    }

    // -----------------------------------------------------------------
    //  Attach / detach polls to an owned event  (CreateEventPage poll Select)
    //  GET  /events/{id}/polls — checkbox list of the organiser's polls
    //  POST /events/{id}/polls — sync event_id on the owner's polls
    //  Mirrors EventsController poll_ids[] linking (owner-scoped Poll update).
    // -----------------------------------------------------------------

    public function eventsPolls(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $userId = $this->eventsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        $event = $this->eventsParityOwnedEvent($id, $userId);

        $myPolls = $this->eventsOwnerPolls($userId, $id);

        return $this->view('accessible-frontend::events-polls', [
            'title' => __('govuk_alpha_events.polls.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => $event,
            'polls' => $myPolls,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function eventsUpdatePolls(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $userId = $this->eventsParityGuard($tenantSlug);
        if (is_object($userId)) {
            return $userId;
        }

        // Confirms ownership + tenant scope before any poll mutation.
        $this->eventsParityOwnedEvent($id, $userId);

        // Selected poll ids from the checkbox group; coerce + dedupe + drop <=0,
        // mirroring EventsController::ownedEventPollIds.
        $raw = $request->input('poll_ids', []);
        $selected = [];
        if (is_array($raw)) {
            foreach ($raw as $pid) {
                $pid = (int) $pid;
                if ($pid > 0) {
                    $selected[$pid] = true;
                }
            }
        }
        $selectedIds = array_keys($selected);

        $tenantId = TenantContext::getId();
        $ok = true;

        try {
            // Owner-scoped validation: every chosen poll must belong to this user.
            if ($selectedIds !== []) {
                $ownedCount = Poll::query()
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $userId)
                    ->whereIn('id', $selectedIds)
                    ->count();
                if ($ownedCount !== count($selectedIds)) {
                    return redirect()->route('govuk-alpha.events.polls', [
                        'tenantSlug' => $tenantSlug,
                        'id' => $id,
                        'status' => 'polls-failed',
                    ]);
                }
            }

            // Detach this user's polls currently linked to the event, then
            // (re)attach the chosen set — exactly the API's sync behaviour.
            Poll::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('event_id', $id)
                ->update(['event_id' => null]);

            if ($selectedIds !== []) {
                Poll::query()
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $userId)
                    ->whereIn('id', $selectedIds)
                    ->update(['event_id' => $id]);
            }
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }

        return redirect()->route('govuk-alpha.events.polls', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $ok ? 'polls-updated' : 'polls-failed',
        ]);
    }

    /**
     * The current user's polls, each annotated with whether it is attached to
     * the given event. Uses PollService::getAll (user-scoped) so tenant scope
     * and shape match the API.
     *
     * @return array<int, array<string, mixed>>
     */
    private function eventsOwnerPolls(int $userId, int $eventId): array
    {
        $polls = [];
        try {
            $polls = PollService::getAll(['user_id' => $userId, 'limit' => 100])['items'] ?? [];
        } catch (\Throwable $e) {
            report($e);
        }

        foreach ($polls as &$poll) {
            $poll['attached'] = (int) ($poll['event_id'] ?? 0) === $eventId;
        }
        unset($poll);

        return $polls;
    }

    // -----------------------------------------------------------------
    //  On-demand description translation  (TranslateButton)
    //  GET  /events/{id}/translate — language chooser
    //  POST /events/{id}/translate — UgcTranslationService::translate
    //  Renders the translated description on the same accessible page.
    // -----------------------------------------------------------------

    public function eventsTranslate(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);

        $viewerId = $this->currentUserId();
        if ($viewerId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $event = EventService::getById($id, $viewerId);
        abort_if($event === null, 404);

        return $this->view('accessible-frontend::events-translate', [
            'title' => __('govuk_alpha_events.translate.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => $event,
            'languages' => $this->eventsTranslateLanguages(),
            'translated' => null,
            'targetLocale' => null,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    public function eventsRunTranslate(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);

        $viewerId = $this->currentUserId();
        if ($viewerId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $event = EventService::getById($id, $viewerId);
        abort_if($event === null, 404);

        $languages = $this->eventsTranslateLanguages();
        $target = (string) $this->allowed($request->input('target_locale'), array_keys($languages), 'en');
        $sourceText = (string) ($event['description'] ?? '');

        $translated = null;
        $status = null;

        if (trim($sourceText) === '') {
            $status = 'translate-empty';
        } elseif (empty(config('services.openai.api_key'))) {
            // Mirror UgcTranslationController's explicit "no AI provider" path.
            $status = 'translate-unavailable';
        } else {
            try {
                $result = app(UgcTranslationService::class)->translate(
                    'event',
                    $id,
                    $sourceText,
                    isset($event['locale']) && is_string($event['locale']) ? $event['locale'] : null,
                    $target,
                );
                $translated = (string) ($result['translated_text'] ?? '');
                $status = ($translated !== '' && $translated !== $sourceText) ? 'translate-done' : 'translate-same';
            } catch (\Throwable $e) {
                report($e);
                $status = 'translate-failed';
            }
        }

        return $this->view('accessible-frontend::events-translate', [
            'title' => __('govuk_alpha_events.translate.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => $event,
            'languages' => $languages,
            'translated' => $translated,
            'targetLocale' => $target,
            'status' => $status,
        ]);
    }

    /**
     * Supported target languages for on-demand translation — the platform's
     * 11 locales, labelled from the shared profile-settings language names.
     *
     * @return array<string, string>
     */
    private function eventsTranslateLanguages(): array
    {
        $codes = ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar'];
        $out = [];
        foreach ($codes as $code) {
            $out[$code] = __('govuk_alpha.profile_settings.languages.' . $code);
        }

        return $out;
    }

    // -----------------------------------------------------------------
    //  Privacy-safe calendar parity
    // -----------------------------------------------------------------

    public function eventsCalendarActions(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $user = $this->eventsCalendarUser($tenantSlug);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $service = app(EventCalendarService::class);
        $projection = $service->projectionForEvent($user, $id);
        abort_if($projection === null, 404);

        return $this->view('accessible-frontend::events-calendar', [
            'title' => __('govuk_alpha.events.calendar_actions_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => $service->apiProjection($projection),
            'calendarActions' => $service->calendarActions($projection),
        ]);
    }

    public function eventsCalendarDownload(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $user = $this->eventsCalendarUser($tenantSlug);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $service = app(EventCalendarService::class);
        $projection = $service->projectionForEvent($user, $id);
        abort_if($projection === null, 404);

        return $this->eventsCalendarResponse(
            $service->renderEvent($projection),
            'event-' . $id . '.ics',
        );
    }

    public function eventsCalendarFeed(
        Request $request,
        string $tenantSlug,
    ): Response|RedirectResponse {
        $user = $this->eventsCalendarUser($tenantSlug);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $service = app(EventCalendarService::class);
        [$from, $until] = $service->tenantFeedRange();
        $items = $service->projectionsForRange(
            $user,
            $from,
            $until,
        );

        return $this->eventsCalendarResponse(
            $service->renderFeed($items, __('govuk_alpha.events.calendar_tenant_name', [
                'tenant' => TenantContext::getName(),
            ])),
            'events.ics',
        );
    }

    public function eventsCalendarSubscriptions(
        Request $request,
        string $tenantSlug,
    ): Response|RedirectResponse {
        $user = $this->eventsCalendarUser($tenantSlug);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $statusInput = $request->query('status');
        $status = is_string($statusInput) && in_array($statusInput, ['revoked'], true)
            ? $statusInput
            : null;

        return $this->eventsCalendarSubscriptionsPage($user, $tenantSlug, null, [], $status);
    }

    public function eventsCreateCalendarSubscription(
        Request $request,
        string $tenantSlug,
    ): Response|RedirectResponse {
        $user = $this->eventsCalendarUser($tenantSlug);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $labelInput = $request->input('label');
        $label = is_string($labelInput) ? trim($labelInput) : '';
        $hasControlCharacters = $label !== ''
            && preg_match('/[\x00-\x1F\x7F]/u', $label) === 1;
        $safeLabel = preg_replace('/[\x00-\x1F\x7F]/u', '', $label) ?? '';
        if (($labelInput !== null && ! is_string($labelInput))
            || mb_strlen($label) > 100
            || $hasControlCharacters) {
            return $this->eventsCalendarSubscriptionsPage(
                $user,
                $tenantSlug,
                null,
                [__('govuk_alpha.events.calendar_subscription_label_invalid')],
                null,
                422,
                mb_substr($safeLabel, 0, 100),
            );
        }

        $service = app(EventCalendarService::class);
        try {
            $created = $service->createFeedToken($user, $label !== '' ? $label : null);
        } catch (\DomainException $exception) {
            $message = $exception->getMessage() === 'event_calendar_token_limit'
                ? __('govuk_alpha.events.calendar_subscription_limit')
                : __('govuk_alpha.events.calendar_subscription_create_failed');

            return $this->eventsCalendarSubscriptionsPage(
                $user,
                $tenantSlug,
                null,
                [$message],
                null,
                409,
                $label,
            );
        } catch (\Throwable $exception) {
            // Never log the request label, token result, URL, or exception
            // message: any of them could become a capability disclosure.
            Log::warning('Accessible calendar subscription creation failed', [
                'tenant_id' => TenantContext::currentId(),
                'user_id' => (int) $user->getKey(),
                'exception_class' => $exception::class,
            ]);

            return $this->eventsCalendarSubscriptionsPage(
                $user,
                $tenantSlug,
                null,
                [__('govuk_alpha.events.calendar_subscription_create_failed')],
                null,
                500,
                $label,
            );
        }

        $feedUrl = $created['feed_url'] ?? null;
        unset($created['secret'], $created['feed_url']);
        if (! is_string($feedUrl) || $feedUrl === '') {
            return $this->eventsCalendarSubscriptionsPage(
                $user,
                $tenantSlug,
                null,
                [__('govuk_alpha.events.calendar_subscription_create_failed')],
                null,
                500,
            );
        }

        // Render the capability once, directly in this no-store POST response.
        // It is never flashed into session state or persisted in plaintext.
        return $this->eventsCalendarSubscriptionsPage(
            $user,
            $tenantSlug,
            $feedUrl,
            [],
            'created',
        );
    }

    public function eventsConfirmCalendarSubscriptionRevoke(
        Request $request,
        string $tenantSlug,
        int $tokenId,
    ): Response|RedirectResponse {
        $user = $this->eventsCalendarUser($tenantSlug);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $token = app(EventCalendarService::class)->feedTokenForOwner($user, $tokenId);
        abort_if($token === null || ! (bool) ($token['active'] ?? false), 404);

        return $this->eventsCalendarSubscriptionRevokePage($tenantSlug, $token);
    }

    public function eventsRevokeCalendarSubscription(
        Request $request,
        string $tenantSlug,
        int $tokenId,
    ): Response|RedirectResponse {
        $user = $this->eventsCalendarUser($tenantSlug);
        if ($user instanceof RedirectResponse) {
            return $user;
        }

        $service = app(EventCalendarService::class);
        $token = $service->feedTokenForOwner($user, $tokenId);
        abort_if($token === null || ! (bool) ($token['active'] ?? false), 404);

        if ($request->input('confirm_revoke') !== 'yes') {
            return $this->eventsCalendarSubscriptionRevokePage(
                $tenantSlug,
                $token,
                [__('govuk_alpha.events.calendar_subscription_confirmation_required')],
                422,
            );
        }

        if (! $service->revokeFeedToken($user, $tokenId)) {
            return $this->eventsCalendarSubscriptionsPage(
                $user,
                $tenantSlug,
                null,
                [__('govuk_alpha.events.calendar_subscription_revoke_failed')],
                null,
                409,
            );
        }

        return redirect()
            ->route('govuk-alpha.events.calendar.subscriptions', [
                'tenantSlug' => $tenantSlug,
                'status' => 'revoked',
            ])
            ->withHeaders([
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'Referrer-Policy' => 'no-referrer',
            ]);
    }

    /**
     * @param list<string> $errors
     */
    private function eventsCalendarSubscriptionsPage(
        User $user,
        string $tenantSlug,
        ?string $createdFeedUrl = null,
        array $errors = [],
        ?string $status = null,
        int $httpStatus = 200,
        string $label = '',
    ): Response {
        $service = app(EventCalendarService::class);
        $response = $this->view('accessible-frontend::events-calendar-subscriptions', [
            'title' => __('govuk_alpha.events.calendar_subscriptions_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'tokens' => $service->listFeedTokens($user),
            'calendarTimezone' => $service->tenantTimezone(),
            'createdFeedUrl' => $createdFeedUrl,
            'errors' => $errors,
            'status' => $status,
            'label' => $label,
        ], $httpStatus);

        return $this->eventsCalendarSensitiveHtmlResponse($response);
    }

    /**
     * @param array<string, int|string|bool|null> $token
     * @param list<string> $errors
     */
    private function eventsCalendarSubscriptionRevokePage(
        string $tenantSlug,
        array $token,
        array $errors = [],
        int $httpStatus = 200,
    ): Response {
        $response = $this->view('accessible-frontend::events-calendar-subscription-revoke', [
            'title' => __('govuk_alpha.events.calendar_subscription_revoke_title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'token' => $token,
            'errors' => $errors,
        ], $httpStatus);

        return $this->eventsCalendarSensitiveHtmlResponse($response);
    }

    private function eventsCalendarSensitiveHtmlResponse(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    private function eventsCalendarUser(string $tenantSlug): User|RedirectResponse
    {
        $userId = $this->eventsParityGuard($tenantSlug);
        if ($userId instanceof RedirectResponse) {
            return $userId;
        }

        $user = User::withoutGlobalScopes()
            ->where('tenant_id', TenantContext::currentId())
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->find($userId);
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function eventsCalendarResponse(string $body, string $filename): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

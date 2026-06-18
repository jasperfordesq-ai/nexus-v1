<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Models\Poll;
use App\Services\EventService;
use App\Services\PollService;
use App\Services\UgcTranslationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

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
 */
trait EventsParity
{
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
        $event = EventService::getById($id, $viewerId);
        abort_if($event === null, 404);

        $lat = isset($event['latitude']) && $event['latitude'] !== null ? (float) $event['latitude'] : null;
        $lng = isset($event['longitude']) && $event['longitude'] !== null ? (float) $event['longitude'] : null;
        $location = trim(self::asStr($event['location'] ?? ''));
        $isOnline = (bool) ($event['is_online'] ?? false);

        return $this->view('accessible-frontend::events-map', [
            'title' => __('govuk_alpha_events.map.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => $event,
            'lat' => $lat,
            'lng' => $lng,
            'location' => $location !== '' ? $location : null,
            'isOnline' => $isOnline,
            'hasCoordinates' => $lat !== null && $lng !== null && !$isOnline,
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
                if (!empty($changes)) {
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
}

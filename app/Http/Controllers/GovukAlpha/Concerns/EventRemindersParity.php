<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Services\EventReminderPreferenceService;
use App\Services\EventReminderScheduleService;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** HTML-first event reminder preferences backed by the canonical services. */
trait EventRemindersParity
{
    public function eventsReminders(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $context = $this->eventsReminderContext($tenantSlug, $id);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        [$event, $userId, $registration] = $context;
        $preferences = app(EventReminderPreferenceService::class)->eventPreferences($id, $userId);
        $limits = $this->eventsReminderLimits();
        $selectedOffsets = array_values(array_map(
            static fn (array $rule): int => (int) $rule['offset_minutes'],
            $preferences['rules'],
        ));
        if ($selectedOffsets === []) {
            $selectedOffsets = $limits['default_offsets_minutes'];
        }

        $status = $request->query('status');
        if (! is_string($status) || ! in_array($status, ['saved', 'reset', 'conflict'], true)) {
            $status = null;
        }

        return $this->view('accessible-frontend::event-reminders', [
            'title' => __('govuk_alpha_events.reminders.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => $event,
            'preferences' => $preferences,
            'limits' => $limits,
            'selectedOffsets' => $selectedOffsets,
            'registrationVersion' => (int) $registration->registration_version,
            'status' => $status,
            'sourceLabel' => $this->eventsReminderSourceLabel(
                (string) ($preferences['resolved']['reminders_source'] ?? ''),
            ),
        ]);
    }

    public function eventsUpdateReminders(
        Request $request,
        string $tenantSlug,
        int $id,
    ): RedirectResponse {
        $context = $this->eventsReminderContext($tenantSlug, $id);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        [, $userId, $registration] = $context;
        $expectedRevision = $this->eventsReminderRevision($request->input('expected_revision'));
        $rules = $this->eventsReminderRules($request);
        if ($expectedRevision === null || $rules === null) {
            return $this->eventsReminderInvalidRedirect($tenantSlug, $id, $request);
        }

        $enabled = $request->boolean('reminders_enabled');
        if ($enabled && $rules === []) {
            return $this->eventsReminderInvalidRedirect($tenantSlug, $id, $request);
        }
        $overrides = [
            'reminders_enabled' => $enabled,
            'cadence' => $enabled ? 'instant' : 'off',
            'email_enabled' => $request->boolean('channel_email'),
            'in_app_enabled' => $request->boolean('channel_in_app'),
            'web_push_enabled' => $request->boolean('channel_web_push'),
            'fcm_enabled' => $request->boolean('channel_fcm'),
            'realtime_enabled' => $request->boolean('channel_realtime'),
        ];

        try {
            app(EventReminderPreferenceService::class)->replaceEventPreferences(
                $id,
                $userId,
                $overrides,
                $rules,
                $expectedRevision,
            );
            app(EventReminderScheduleService::class)->reconcileConfirmedRegistration(
                $id,
                $userId,
                (int) $registration->id,
                (int) $registration->registration_version,
            );
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'event_reminder_preference_version_conflict') {
                return redirect()->route('govuk-alpha.events.reminders', [
                    'tenantSlug' => $tenantSlug,
                    'id' => $id,
                    'status' => 'conflict',
                ]);
            }
            report($exception);

            return $this->eventsReminderFailureRedirect($tenantSlug, $id);
        } catch (InvalidArgumentException) {
            return $this->eventsReminderInvalidRedirect($tenantSlug, $id, $request);
        } catch (Throwable $exception) {
            report($exception);

            return $this->eventsReminderFailureRedirect($tenantSlug, $id);
        }

        return redirect()->route('govuk-alpha.events.reminders', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => 'saved',
        ]);
    }

    public function eventsResetReminders(
        Request $request,
        string $tenantSlug,
        int $id,
    ): RedirectResponse {
        $context = $this->eventsReminderContext($tenantSlug, $id);
        if ($context instanceof RedirectResponse) {
            return $context;
        }

        [, $userId, $registration] = $context;
        $expectedRevision = $this->eventsReminderRevision($request->input('expected_revision'));
        if ($expectedRevision === null) {
            return $this->eventsReminderInvalidRedirect($tenantSlug, $id, $request);
        }

        try {
            app(EventReminderPreferenceService::class)->deleteEventPreferences(
                $id,
                $userId,
                $expectedRevision,
            );
            app(EventReminderScheduleService::class)->reconcileConfirmedRegistration(
                $id,
                $userId,
                (int) $registration->id,
                (int) $registration->registration_version,
            );
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'event_reminder_preference_version_conflict') {
                return redirect()->route('govuk-alpha.events.reminders', [
                    'tenantSlug' => $tenantSlug,
                    'id' => $id,
                    'status' => 'conflict',
                ]);
            }
            report($exception);

            return $this->eventsReminderFailureRedirect($tenantSlug, $id);
        } catch (Throwable $exception) {
            report($exception);

            return $this->eventsReminderFailureRedirect($tenantSlug, $id);
        }

        return redirect()->route('govuk-alpha.events.reminders', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => 'reset',
        ]);
    }

    /** @return array{0:array<string,mixed>,1:int,2:object}|RedirectResponse */
    private function eventsReminderContext(string $tenantSlug, int $eventId): array|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', [
                'tenantSlug' => $tenantSlug,
                'status' => 'auth-required',
            ]);
        }

        $event = EventService::getById($eventId, $userId);
        abort_if($event === null, 404);
        $registration = DB::table('event_registrations')
            ->where('tenant_id', (int) TenantContext::getId())
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where('registration_state', 'confirmed')
            ->first(['id', 'registration_version']);
        abort_if($registration === null, 403);

        return [$event, $userId, $registration];
    }

    /** @return array{minimum_offset_minutes:int,maximum_offset_minutes:int,maximum_rules:int,default_offsets_minutes:list<int>} */
    private function eventsReminderLimits(): array
    {
        $minimum = max(1, (int) config('events.reminders.minimum_offset_minutes', 5));
        $maximum = max($minimum, (int) config('events.reminders.maximum_offset_minutes', 525600));
        $defaults = array_values(array_filter(array_map(
            static fn (mixed $offset): int => (int) $offset,
            (array) config('events.reminders.default_offsets_minutes', [1440, 60]),
        ), static fn (int $offset): bool => $offset >= $minimum && $offset <= $maximum));

        return [
            'minimum_offset_minutes' => $minimum,
            'maximum_offset_minutes' => $maximum,
            'maximum_rules' => max(1, (int) config('events.reminders.max_rules_per_event', 10)),
            'default_offsets_minutes' => $defaults,
        ];
    }

    /** @return list<array<string,int|bool|null>>|null */
    private function eventsReminderRules(Request $request): ?array
    {
        $rawOffsets = $request->input('offsets', []);
        if (! is_array($rawOffsets)) {
            return null;
        }
        $custom = $request->input('custom_offset');
        if ($custom !== null && $custom !== '') {
            $rawOffsets[] = $custom;
        }

        $limits = $this->eventsReminderLimits();
        $offsets = [];
        foreach ($rawOffsets as $rawOffset) {
            if ((! is_int($rawOffset) && ! is_string($rawOffset))
                || ! ctype_digit((string) $rawOffset)) {
                return null;
            }
            $offset = (int) $rawOffset;
            if ($offset < $limits['minimum_offset_minutes']
                || $offset > $limits['maximum_offset_minutes']) {
                return null;
            }
            $offsets[$offset] = true;
        }
        if (count($offsets) > $limits['maximum_rules']) {
            return null;
        }
        krsort($offsets, SORT_NUMERIC);

        return array_map(static fn (int $offset): array => [
            'offset_minutes' => $offset,
            'enabled' => true,
            'email_enabled' => null,
            'in_app_enabled' => null,
            'web_push_enabled' => null,
            'fcm_enabled' => null,
            'realtime_enabled' => null,
        ], array_keys($offsets));
    }

    private function eventsReminderRevision(mixed $value): ?int
    {
        if ((! is_int($value) && ! is_string($value)) || ! ctype_digit((string) $value)) {
            return null;
        }

        return (int) $value;
    }

    private function eventsReminderSourceLabel(string $source): string
    {
        $key = match ($source) {
            'event' => 'event',
            'category' => 'category',
            'global' => 'global',
            'tenant' => 'tenant',
            default => 'unavailable',
        };

        return __('govuk_alpha_events.reminders.source_' . $key);
    }

    private function eventsReminderInvalidRedirect(
        string $tenantSlug,
        int $id,
        Request $request,
    ): RedirectResponse {
        return redirect()
            ->route('govuk-alpha.events.reminders', compact('tenantSlug', 'id'))
            ->withErrors(['reminders' => __('govuk_alpha_events.reminders.validation_error')])
            ->withInput($request->except(['expected_revision']));
    }

    private function eventsReminderFailureRedirect(string $tenantSlug, int $id): RedirectResponse
    {
        return redirect()
            ->route('govuk-alpha.events.reminders', compact('tenantSlug', 'id'))
            ->withErrors(['reminders' => __('govuk_alpha_events.reminders.save_error')]);
    }
}

<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Exceptions\EventBroadcastException;
use App\Exceptions\SafeguardingPolicyException;
use App\Models\User;
use App\Services\EventBroadcastQueryService;
use App\Services\EventBroadcastService;
use App\Support\Events\EventBroadcastFoundationSupport;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/** HTML-first organizer broadcast workflow with aggregate-only read models. */
trait EventCommunicationsParity
{
    /** @var list<string> */
    private const EVENT_COMMUNICATION_VARIANTS = [
        'announcement',
        'follow_up',
        'review_request',
    ];

    /** @var list<string> */
    private const EVENT_COMMUNICATION_SEGMENTS = [
        'registration_confirmed',
        'waitlist_active',
        'attendance_attended',
        'attendance_no_show',
    ];

    /** @var list<string> */
    private const EVENT_COMMUNICATION_CHANNELS = ['email', 'in_app', 'push'];

    public function eventsCommunications(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $actor = $this->eventsCommunicationsActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }

        try {
            $page = $this->eventsCommunicationsPositiveInteger($request->query('page')) ?? 1;
            $result = app(EventBroadcastQueryService::class)->paginateForEvent(
                $id,
                $actor,
                $page,
                20,
            );
            $detail = null;
            $broadcastId = $this->eventsCommunicationsPositiveInteger(
                $request->query('broadcast_id'),
            );
            if ($broadcastId !== null) {
                $historyPage = $this->eventsCommunicationsPositiveInteger(
                    $request->query('history_page'),
                ) ?? 1;
                $detail = app(EventBroadcastQueryService::class)->detail(
                    $broadcastId,
                    $actor,
                    $historyPage,
                    50,
                );
                if ((int) $detail['broadcast']['event_id'] !== $id) {
                    abort(404);
                }
            }
        } catch (EventBroadcastException $exception) {
            return $this->eventsCommunicationsFailure($exception, $tenantSlug, $id);
        }

        return $this->eventsCommunicationsView(
            $tenantSlug,
            $id,
            $result,
            $this->eventsCommunicationsDefaultDraft(),
            null,
            $detail,
            is_string($request->query('status')) ? trim((string) $request->query('status')) : null,
        );
    }

    public function eventsCommunicationsPreview(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $actor = $this->eventsCommunicationsActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $draft = $this->eventsCommunicationsDraft($request);
        if ($draft === null) {
            return $this->eventsCommunicationsValidationRedirect($tenantSlug, $id);
        }

        try {
            $preview = app(EventBroadcastService::class)->preview(
                $id,
                $actor,
                $draft['variant'],
                $draft['segments'],
                $draft['channels'],
            );
            $result = app(EventBroadcastQueryService::class)->paginateForEvent(
                $id,
                $actor,
                1,
                20,
            );
        } catch (SafeguardingPolicyException) {
            return $this->eventsCommunicationsValidationRedirect($tenantSlug, $id, true);
        } catch (EventBroadcastException $exception) {
            return $this->eventsCommunicationsFailure($exception, $tenantSlug, $id);
        }

        return $this->eventsCommunicationsView(
            $tenantSlug,
            $id,
            $result,
            $draft,
            $preview,
            null,
            null,
        );
    }

    public function eventsCommunicationsCreate(
        Request $request,
        string $tenantSlug,
        int $id,
    ): RedirectResponse {
        $actor = $this->eventsCommunicationsActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $draft = $this->eventsCommunicationsDraft($request);
        $key = $this->eventsCommunicationsIdempotencyKey($request);
        if ($draft === null || $key === null || ! $request->boolean('preview_confirmed')) {
            return $this->eventsCommunicationsValidationRedirect($tenantSlug, $id);
        }

        try {
            app(EventBroadcastService::class)->createDraft(
                $id,
                $actor,
                $draft['variant'],
                $draft['segments'],
                $draft['channels'],
                $draft['body'],
                $key,
            );
        } catch (EventBroadcastException $exception) {
            return $this->eventsCommunicationsFailure($exception, $tenantSlug, $id, true);
        } catch (Throwable $exception) {
            Log::error('Accessible event communication request failed', [
                'exception' => $exception::class,
                'reason_code' => 'event_broadcast_server_error',
            ]);

            return $this->eventsCommunicationsValidationRedirect($tenantSlug, $id, true);
        }

        return $this->eventsCommunicationsSuccessRedirect($tenantSlug, $id, 'created');
    }

    public function eventsCommunicationsSchedule(
        Request $request,
        string $tenantSlug,
        int $id,
        int $broadcastId,
    ): RedirectResponse {
        $actor = $this->eventsCommunicationsActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $version = $this->eventsCommunicationsPositiveInteger($request->input('expected_version'));
        $key = $this->eventsCommunicationsIdempotencyKey($request);
        $schedule = $this->eventsCommunicationsScheduleValue($request, $id);
        if ($version === null || $key === null || ! $schedule['valid']) {
            return $this->eventsCommunicationsValidationRedirect($tenantSlug, $id);
        }

        try {
            $detail = app(EventBroadcastQueryService::class)->detail($broadcastId, $actor);
            if ((int) $detail['broadcast']['event_id'] !== $id) {
                abort(404);
            }
            app(EventBroadcastService::class)->schedule(
                $broadcastId,
                $actor,
                $version,
                $schedule['value'],
                $key,
            );
        } catch (SafeguardingPolicyException) {
            return $this->eventsCommunicationsValidationRedirect($tenantSlug, $id, true);
        } catch (EventBroadcastException $exception) {
            return $this->eventsCommunicationsFailure($exception, $tenantSlug, $id, true);
        }

        return $this->eventsCommunicationsSuccessRedirect($tenantSlug, $id, 'scheduled');
    }

    public function eventsCommunicationsCancel(
        Request $request,
        string $tenantSlug,
        int $id,
        int $broadcastId,
    ): RedirectResponse {
        $actor = $this->eventsCommunicationsActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $version = $this->eventsCommunicationsPositiveInteger($request->input('expected_version'));
        $key = $this->eventsCommunicationsIdempotencyKey($request);
        $reason = is_string($request->input('reason'))
            ? trim((string) $request->input('reason'))
            : '';
        if ($version === null || $key === null || $reason === '' || mb_strlen($reason) > 500) {
            return $this->eventsCommunicationsValidationRedirect($tenantSlug, $id);
        }

        try {
            $detail = app(EventBroadcastQueryService::class)->detail($broadcastId, $actor);
            if ((int) $detail['broadcast']['event_id'] !== $id) {
                abort(404);
            }
            app(EventBroadcastService::class)->cancel(
                $broadcastId,
                $actor,
                $version,
                $reason,
                $key,
            );
        } catch (EventBroadcastException $exception) {
            return $this->eventsCommunicationsFailure($exception, $tenantSlug, $id, true);
        }

        return $this->eventsCommunicationsSuccessRedirect($tenantSlug, $id, 'cancelled');
    }

    public function eventsCommunicationsRetry(
        Request $request,
        string $tenantSlug,
        int $id,
        int $broadcastId,
    ): RedirectResponse {
        $actor = $this->eventsCommunicationsActor($tenantSlug);
        if ($actor instanceof RedirectResponse) {
            return $actor;
        }
        $version = $this->eventsCommunicationsPositiveInteger($request->input('expected_version'));
        $key = $this->eventsCommunicationsIdempotencyKey($request);
        if ($version === null || $key === null) {
            return $this->eventsCommunicationsValidationRedirect($tenantSlug, $id);
        }

        try {
            $detail = app(EventBroadcastQueryService::class)->detail($broadcastId, $actor);
            if ((int) $detail['broadcast']['event_id'] !== $id) {
                abort(404);
            }
            app(EventBroadcastService::class)->retryFailed(
                $broadcastId,
                $actor,
                $version,
                $key,
            );
        } catch (EventBroadcastException $exception) {
            return $this->eventsCommunicationsFailure($exception, $tenantSlug, $id, true);
        }

        return $this->eventsCommunicationsSuccessRedirect($tenantSlug, $id, 'retried');
    }

    /**
     * @param array{items:list<array<string,mixed>>,total:int,page:int,per_page:int} $result
     * @param array{variant:string,segments:list<string>,channels:list<string>,body:string} $draft
     * @param array<string,mixed>|null $preview
     * @param array<string,mixed>|null $detail
     */
    private function eventsCommunicationsView(
        string $tenantSlug,
        int $eventId,
        array $result,
        array $draft,
        ?array $preview,
        ?array $detail,
        ?string $status,
    ): Response {
        return $this->eventsCommunicationsPrivateResponse($this->view(
            'accessible-frontend::event-communications',
            [
                'title' => __('govuk_alpha.events.communications.title'),
                'tenantSlug' => $tenantSlug,
                'activeNav' => 'events',
                'eventId' => $eventId,
                'broadcasts' => $result['items'],
                'pagination' => [
                    'page' => $result['page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                ],
                'draft' => $draft,
                'preview' => $preview,
                'detail' => $detail,
                'status' => $status,
                'idempotencyKey' => (string) Str::uuid(),
            ],
        ));
    }

    /** @return array{variant:string,segments:list<string>,channels:list<string>,body:string} */
    private function eventsCommunicationsDefaultDraft(): array
    {
        return [
            'variant' => 'announcement',
            'segments' => ['registration_confirmed'],
            'channels' => ['email', 'in_app'],
            'body' => '',
        ];
    }

    /** @return array{variant:string,segments:list<string>,channels:list<string>,body:string}|null */
    private function eventsCommunicationsDraft(Request $request): ?array
    {
        $variant = is_string($request->input('variant'))
            ? trim((string) $request->input('variant'))
            : '';
        $segments = $this->eventsCommunicationsAllowlist(
            $request->input('segments'),
            self::EVENT_COMMUNICATION_SEGMENTS,
            4,
        );
        $channels = $this->eventsCommunicationsAllowlist(
            $request->input('channels'),
            self::EVENT_COMMUNICATION_CHANNELS,
            3,
        );
        $body = $request->input('body');
        if (! in_array($variant, self::EVENT_COMMUNICATION_VARIANTS, true)
            || $segments === null
            || $channels === null
            || ! is_string($body)
            || trim($body) === ''
            || mb_strlen($body) > EventBroadcastFoundationSupport::MAX_BODY_LENGTH) {
            return null;
        }

        return compact('variant', 'segments', 'channels', 'body');
    }

    /** @param list<string> $allowed @return list<string>|null */
    private function eventsCommunicationsAllowlist(
        mixed $value,
        array $allowed,
        int $maximum,
    ): ?array {
        if (! is_array($value) || $value === [] || count($value) > $maximum) {
            return null;
        }
        $items = [];
        foreach ($value as $item) {
            if (! is_string($item) || ! in_array($item, $allowed, true)) {
                return null;
            }
            $items[] = $item;
        }
        $items = array_values(array_unique($items));

        return count($items) === count($value) ? $items : null;
    }

    private function eventsCommunicationsActor(string $tenantSlug): User|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('events'), 403);
        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', compact('tenantSlug'));
        }

        return $this->accessibleEventActor($userId);
    }

    private function eventsCommunicationsIdempotencyKey(Request $request): ?string
    {
        $key = $request->input('idempotency_key');
        if (! is_string($key)) {
            return null;
        }
        $key = trim($key);

        return $key !== '' && mb_strlen($key) <= 191 ? $key : null;
    }

    private function eventsCommunicationsPositiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $parsed === false ? null : (int) $parsed;
    }

    /** @return array{valid:bool,value:?CarbonImmutable} */
    private function eventsCommunicationsScheduleValue(Request $request, int $eventId): array
    {
        $raw = $request->input('scheduled_at');
        if ($raw === null || $raw === '') {
            return ['valid' => true, 'value' => null];
        }
        if (! is_string($raw)) {
            return ['valid' => false, 'value' => null];
        }

        try {
            $tenantId = TenantContext::currentId();
            if ($tenantId === null || $tenantId <= 0) {
                return ['valid' => false, 'value' => null];
            }
            $event = app(EventBroadcastFoundationSupport::class)->event($tenantId, $eventId);
            $timezone = trim((string) ($event->getRawOriginal('timezone') ?? 'UTC')) ?: 'UTC';
            $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', trim($raw), $timezone);
        } catch (Throwable) {
            return ['valid' => false, 'value' => null];
        }
        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d\TH:i') !== trim($raw)) {
            return ['valid' => false, 'value' => null];
        }

        return ['valid' => true, 'value' => $date->utc()];
    }

    private function eventsCommunicationsFailure(
        EventBroadcastException $exception,
        string $tenantSlug,
        int $eventId,
        bool $mutation = false,
    ): RedirectResponse {
        if (in_array($exception->reasonCode, [
            'event_broadcast_not_found',
            'event_broadcast_event_not_found',
        ], true)) {
            abort(404);
        }
        if (in_array($exception->reasonCode, [
            'event_broadcast_authorization_denied',
            'event_broadcast_actor_invalid',
        ], true)) {
            abort(403);
        }
        if (in_array($exception->reasonCode, [
            'event_broadcast_schema_unavailable',
            'event_broadcast_audience_schema_unavailable',
            'event_broadcast_feature_disabled',
            'event_broadcast_feature_unavailable',
            'event_broadcast_tenant_context_missing',
        ], true)) {
            abort(503);
        }

        return redirect()->route('govuk-alpha.events.communications.index', [
            'tenantSlug' => $tenantSlug,
            'id' => $eventId,
        ])->withErrors([
            'communication' => __($mutation
                ? 'govuk_alpha.events.communications.save_error'
                : 'govuk_alpha.events.communications.load_error'),
        ]);
    }

    private function eventsCommunicationsValidationRedirect(
        string $tenantSlug,
        int $eventId,
        bool $policy = false,
    ): RedirectResponse {
        return redirect()->route('govuk-alpha.events.communications.index', [
            'tenantSlug' => $tenantSlug,
            'id' => $eventId,
        ])->withErrors([
            'communication' => __($policy
                ? 'govuk_alpha.events.communications.policy_error'
                : 'govuk_alpha.events.communications.validation_error'),
        ]);
    }

    private function eventsCommunicationsSuccessRedirect(
        string $tenantSlug,
        int $eventId,
        string $status,
    ): RedirectResponse {
        return redirect()->route('govuk-alpha.events.communications.index', [
            'tenantSlug' => $tenantSlug,
            'id' => $eventId,
            'status' => $status,
        ]);
    }

    private function eventsCommunicationsPrivateResponse(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');
        $response->setVary('Cookie', false);

        return $response;
    }
}

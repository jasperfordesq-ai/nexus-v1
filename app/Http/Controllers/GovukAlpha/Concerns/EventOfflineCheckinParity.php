<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Enums\EventAttendanceAction;
use App\Exceptions\EventAttendanceException;
use App\Exceptions\EventOfflineCheckinException;
use App\Services\EventAttendanceService;
use App\Services\EventCheckinCredentialService;
use App\Services\EventPeopleService;
use App\Services\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/** HTML-first manual signed-code fallback for the accessible Event check-in page. */
trait EventOfflineCheckinParity
{
    public function eventsAttendeeCheckinCredential(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $context = $this->eventsAttendeeCredentialContext($tenantSlug, $id);
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        [$event] = $context;
        $status = $request->query('status');
        if (! is_string($status) || ! in_array($status, [
            'issued', 'already-active', 'replaced', 'revoked', 'failed', 'invalid',
        ], true)) {
            $status = null;
        }

        return $this->eventsAttendeeCredentialView($tenantSlug, $id, $event, $status);
    }

    public function eventsIssueAttendeeCheckinCredential(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $context = $this->eventsAttendeeCredentialContext($tenantSlug, $id);
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        [$event, $userId, $registrationId] = $context;
        $idempotencyKey = $this->eventsAttendeeCredentialIdempotencyKey($request);
        if ($request->input('confirmation') !== '1' || $idempotencyKey === null) {
            return $this->eventsAttendeeCredentialRedirect($tenantSlug, $id, 'invalid');
        }

        try {
            $result = app(EventCheckinCredentialService::class)->issue(
                $id,
                $registrationId,
                $userId,
                $idempotencyKey,
            );

            return $this->eventsAttendeeCredentialView(
                $tenantSlug,
                $id,
                $event,
                $result->issued ? 'issued' : 'already-active',
                $result->issued ? $result->secret : null,
            );
        } catch (EventOfflineCheckinException $exception) {
            if ($exception->reasonCode === 'event_qr_credential_active_exists') {
                return $this->eventsAttendeeCredentialRedirect(
                    $tenantSlug,
                    $id,
                    'already-active',
                );
            }

            return $this->eventsAttendeeCredentialFailure(
                $exception,
                $tenantSlug,
                $id,
                'issue',
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->eventsAttendeeCredentialRedirect($tenantSlug, $id, 'failed');
        }
    }

    public function eventsRotateAttendeeCheckinCredential(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $context = $this->eventsAttendeeCredentialContext($tenantSlug, $id);
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        [$event, $userId] = $context;
        $credentialId = $this->eventsAttendeeCredentialPositiveInt($request->input('credential_id'));
        $expectedVersion = $this->eventsAttendeeCredentialPositiveInt(
            $request->input('expected_version'),
        );
        $idempotencyKey = $this->eventsAttendeeCredentialIdempotencyKey($request);
        if ($request->input('confirmation') !== '1'
            || $credentialId === null
            || $expectedVersion === null
            || $idempotencyKey === null) {
            return $this->eventsAttendeeCredentialRedirect($tenantSlug, $id, 'invalid');
        }

        try {
            $result = app(EventCheckinCredentialService::class)->rotate(
                $id,
                $credentialId,
                $userId,
                $expectedVersion,
                $idempotencyKey,
            );

            return $this->eventsAttendeeCredentialView(
                $tenantSlug,
                $id,
                $event,
                $result->issued ? 'replaced' : 'already-active',
                $result->issued ? $result->secret : null,
            );
        } catch (EventOfflineCheckinException $exception) {
            return $this->eventsAttendeeCredentialFailure(
                $exception,
                $tenantSlug,
                $id,
                'rotate',
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->eventsAttendeeCredentialRedirect($tenantSlug, $id, 'failed');
        }
    }

    public function eventsRevokeAttendeeCheckinCredential(
        Request $request,
        string $tenantSlug,
        int $id,
    ): Response|RedirectResponse {
        $context = $this->eventsAttendeeCredentialContext($tenantSlug, $id);
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        [$event, $userId] = $context;
        $credentialId = $this->eventsAttendeeCredentialPositiveInt($request->input('credential_id'));
        $expectedVersion = $this->eventsAttendeeCredentialPositiveInt(
            $request->input('expected_version'),
        );
        $reason = trim((string) $request->input('reason'));
        if ($request->input('confirmation') !== '1'
            || $credentialId === null
            || $expectedVersion === null
            || $reason === ''
            || mb_strlen($reason) > 500) {
            return $this->eventsAttendeeCredentialRedirect($tenantSlug, $id, 'invalid');
        }

        try {
            app(EventCheckinCredentialService::class)->revoke(
                $id,
                $credentialId,
                $userId,
                $expectedVersion,
                $reason,
            );

            return $this->eventsAttendeeCredentialView(
                $tenantSlug,
                $id,
                $event,
                'revoked',
            );
        } catch (EventOfflineCheckinException $exception) {
            return $this->eventsAttendeeCredentialFailure(
                $exception,
                $tenantSlug,
                $id,
                'revoke',
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->eventsAttendeeCredentialRedirect($tenantSlug, $id, 'failed');
        }
    }

    public function eventsOfflineCheckinCode(
        Request $request,
        string $tenantSlug,
        int $id,
    ): RedirectResponse {
        $context = $this->eventsOperationalContext($tenantSlug, $id, 'attendance');
        if ($context instanceof RedirectResponse) {
            return $context;
        }
        [$event, $actor] = $context;
        $credential = trim((string) $request->input('credential'));
        $action = EventAttendanceAction::tryFrom(trim((string) $request->input('action')));
        $reason = trim((string) $request->input('reason'));
        if ($request->input('confirmation') !== '1'
            || $action === null
            || ! str_starts_with($credential, 'nqx2_')
            || mb_strlen($credential) > 1024
            || ($action === EventAttendanceAction::Undo && $reason === '')) {
            return $this->eventsOfflineCheckinRedirect($tenantSlug, $id, 'attendance-code-invalid');
        }

        try {
            $verified = app(EventCheckinCredentialService::class)->verify($id, $credential);
            $subjectUserId = (int) $verified->user_id;
            if ($subjectUserId <= 0
                || ! app(EventPeopleService::class)->attendanceSubjectVisible($event, $subjectUserId)) {
                throw new EventOfflineCheckinException('event_qr_registration_not_found');
            }
            $tenantId = TenantContext::currentId();
            if ($tenantId === null || $tenantId <= 0) {
                throw new EventOfflineCheckinException('event_checkin_tenant_context_missing');
            }
            $version = DB::table('event_attendance')
                ->where('tenant_id', $tenantId)
                ->where('event_id', $id)
                ->where('user_id', $subjectUserId)
                ->value('attendance_version');
            app(EventAttendanceService::class)->transition(
                $id,
                $subjectUserId,
                $action,
                $actor,
                is_numeric($version) ? (int) $version : 0,
                $reason !== '' ? $reason : null,
                trim((string) $request->input('idempotency_key')) ?: (string) Str::uuid(),
            );

            return $this->eventsOfflineCheckinRedirect($tenantSlug, $id, 'attendance-code-updated');
        } catch (EventAttendanceException $exception) {
            $status = $exception->reasonCode === 'event_attendance_version_conflict'
                ? 'attendance-code-conflict'
                : 'attendance-code-failed';
            Log::notice('Accessible Event signed-code attendance operation rejected', [
                'tenant_id' => TenantContext::getId(),
                'event_id' => $id,
                'reason_code' => $exception->reasonCode,
            ]);

            return $this->eventsOfflineCheckinRedirect($tenantSlug, $id, $status);
        } catch (EventOfflineCheckinException $exception) {
            Log::notice('Accessible Event signed check-in code rejected', [
                'tenant_id' => TenantContext::getId(),
                'event_id' => $id,
                'reason_code' => $exception->reasonCode,
            ]);

            return $this->eventsOfflineCheckinRedirect($tenantSlug, $id, 'attendance-code-invalid');
        }
    }

    private function eventsOfflineCheckinRedirect(
        string $tenantSlug,
        int $eventId,
        string $status,
    ): RedirectResponse {
        return redirect()->route('govuk-alpha.events.check-in', [
            'tenantSlug' => $tenantSlug,
            'id' => $eventId,
            'status' => $status,
        ]);
    }

    /** @return array{0:array<string,mixed>,1:int,2:int}|RedirectResponse */
    private function eventsAttendeeCredentialContext(
        string $tenantSlug,
        int $eventId,
    ): array|RedirectResponse {
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
        $registrationId = DB::table('event_registrations')
            ->where('tenant_id', (int) TenantContext::getId())
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where('registration_state', 'confirmed')
            ->value('id');
        abort_unless(is_numeric($registrationId) && (int) $registrationId > 0, 403);

        return [$event, $userId, (int) $registrationId];
    }

    /** @param array<string,mixed> $event */
    private function eventsAttendeeCredentialView(
        string $tenantSlug,
        int $eventId,
        array $event,
        ?string $status,
        ?string $token = null,
    ): Response {
        $credential = DB::table('event_checkin_credentials')
            ->where('tenant_id', (int) TenantContext::getId())
            ->where('event_id', $eventId)
            ->where('user_id', (int) $this->currentUserId())
            ->orderByDesc('credential_version')
            ->first([
                'id', 'credential_version', 'status', 'expires_at', 'revoked_at',
            ]);
        $credentialStatus = $credential !== null ? (string) $credential->status : null;
        if ($credentialStatus === 'active'
            && is_string($credential->expires_at)
            && now()->gte($credential->expires_at)) {
            $credentialStatus = 'expired';
        }
        $response = $this->view('accessible-frontend::event-checkin-credential', [
            'title' => __('event_offline_checkin.attendee.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'events',
            'event' => $event,
            'credential' => $credential,
            'credentialStatus' => $credentialStatus,
            'token' => is_string($token) && str_starts_with($token, 'nqx2_') ? $token : null,
            'status' => $status,
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    private function eventsAttendeeCredentialFailure(
        EventOfflineCheckinException $exception,
        string $tenantSlug,
        int $eventId,
        string $operation,
    ): RedirectResponse {
        Log::notice('Accessible Event attendee credential operation rejected', [
            'tenant_id' => TenantContext::getId(),
            'event_id' => $eventId,
            'operation' => $operation,
            'reason_code' => $exception->reasonCode,
        ]);

        return $this->eventsAttendeeCredentialRedirect($tenantSlug, $eventId, 'failed');
    }

    private function eventsAttendeeCredentialRedirect(
        string $tenantSlug,
        int $eventId,
        string $status,
    ): RedirectResponse {
        return redirect()->route('govuk-alpha.events.check-in.credential', [
            'tenantSlug' => $tenantSlug,
            'id' => $eventId,
            'status' => $status,
        ]);
    }

    private function eventsAttendeeCredentialPositiveInt(mixed $value): ?int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $parsed === false ? null : (int) $parsed;
    }

    private function eventsAttendeeCredentialIdempotencyKey(Request $request): ?string
    {
        $key = trim((string) $request->input('idempotency_key'));
        if (strlen($key) < 8 || strlen($key) > 191
            || preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            return null;
        }

        return $key;
    }
}

{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.show', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
        {{ __('govuk_alpha.events.back_to_event') }}
    </a>

    <span class="govuk-caption-l">{{ $event['title'] }}</span>
    <h1 class="govuk-heading-xl">{{ __('event_offline_checkin.attendee.title') }}</h1>
    <p class="govuk-body-l">{{ __('event_offline_checkin.attendee.intro') }}</p>
    <div class="govuk-inset-text">
        <p class="govuk-body">{{ __('event_offline_checkin.attendee.privacy') }}</p>
        <p class="govuk-body govuk-!-margin-bottom-0">{{ __('event_offline_checkin.privacy.no_wallet') }}</p>
    </div>

    @if ($status !== null)
        @php
            $notice = match ($status) {
                'issued' => __('event_offline_checkin.attendee.notice_issued'),
                'replaced' => __('event_offline_checkin.attendee.notice_replaced'),
                'revoked' => __('event_offline_checkin.attendee.notice_revoked'),
                'already-active' => __('event_offline_checkin.attendee.notice_already_active'),
                'invalid' => __('event_offline_checkin.attendee.notice_invalid'),
                default => __('event_offline_checkin.attendee.notice_failed'),
            };
            $noticeSuccess = in_array($status, ['issued', 'replaced', 'revoked'], true);
        @endphp
        @if ($noticeSuccess)
            <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="credential-notice-title">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="credential-notice-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">{{ $notice }}</p>
                </div>
            </div>
        @else
            <div class="govuk-error-summary" data-module="govuk-error-summary" role="alert" tabindex="-1">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><p>{{ $notice }}</p></div>
            </div>
        @endif
    @endif

    @if ($credential !== null)
        @php
            $statusKey = in_array($credentialStatus, ['active', 'rotated', 'revoked', 'expired'], true)
                ? $credentialStatus
                : 'expired';
            $statusColour = $statusKey === 'active' ? 'govuk-tag--green' : ($statusKey === 'revoked' ? 'govuk-tag--red' : 'govuk-tag--grey');
            $expiresAt = $credential->expires_at
                ? Illuminate\Support\Carbon::parse($credential->expires_at)->translatedFormat('j F Y, g:ia T')
                : null;
        @endphp
        <section aria-labelledby="credential-status-heading" class="govuk-!-margin-bottom-7">
            <h2 class="govuk-heading-m" id="credential-status-heading">{{ __('event_offline_checkin.attendee.status_heading') }}</h2>
            <p class="govuk-body">
                <strong class="govuk-tag {{ $statusColour }}">{{ __('event_offline_checkin.attendee.status_' . $statusKey) }}</strong>
                @if ($expiresAt !== null)
                    <span class="govuk-!-margin-left-2">{{ __('event_offline_checkin.attendee.expires', ['date' => $expiresAt]) }}</span>
                @endif
            </p>
        </section>
    @endif

    @if ($token !== null)
        <section aria-labelledby="one-shot-code-heading" class="govuk-!-margin-bottom-8">
            <h2 class="govuk-heading-l" id="one-shot-code-heading">{{ __('event_offline_checkin.attendee.one_shot_heading') }}</h2>
            <div class="govuk-warning-text">
                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                <strong class="govuk-warning-text__text">
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning_prefix') }}</span>
                    {{ __('event_offline_checkin.attendee.one_shot') }}
                </strong>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="attendee-checkin-code">{{ __('event_offline_checkin.attendee.code_label') }}</label>
                <div class="govuk-hint" id="attendee-checkin-code-hint">{{ __('event_offline_checkin.attendee.code_hint') }}</div>
                <textarea class="govuk-textarea" id="attendee-checkin-code" rows="8" readonly spellcheck="false" aria-describedby="attendee-checkin-code-hint">{{ $token }}</textarea>
            </div>
            <p class="govuk-body">{{ __('event_offline_checkin.attendee.print_hint') }}</p>
            <button type="button" class="govuk-button govuk-button--secondary" data-module="govuk-button" data-alpha-print-page>
                {{ __('event_offline_checkin.attendee.print') }}
            </button>
        </section>
    @endif

    @if ($credentialStatus !== 'active')
        <form method="post" action="{{ route('govuk-alpha.events.check-in.credential.issue', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}" class="govuk-!-margin-bottom-8">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <div class="govuk-checkboxes">
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="issue-credential-confirm" name="confirmation" type="checkbox" value="1" required>
                    <label class="govuk-label govuk-checkboxes__label" for="issue-credential-confirm">{{ __('event_offline_checkin.attendee.issue_confirm') }}</label>
                </div>
            </div>
            <button class="govuk-button govuk-!-margin-top-4" data-module="govuk-button">{{ __('event_offline_checkin.attendee.issue') }}</button>
        </form>
    @elseif ($credential !== null)
        <section aria-labelledby="replace-code-heading" class="govuk-!-margin-bottom-8">
            <h2 class="govuk-heading-m" id="replace-code-heading">{{ __('event_offline_checkin.attendee.replace') }}</h2>
            <p class="govuk-body">{{ __('event_offline_checkin.attendee.replace_hint') }}</p>
            <form method="post" action="{{ route('govuk-alpha.events.check-in.credential.rotate', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                @csrf
                <input type="hidden" name="credential_id" value="{{ $credential->id }}">
                <input type="hidden" name="expected_version" value="{{ $credential->credential_version }}">
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <div class="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="replace-credential-confirm" name="confirmation" type="checkbox" value="1" required>
                        <label class="govuk-label govuk-checkboxes__label" for="replace-credential-confirm">{{ __('event_offline_checkin.attendee.replace_confirm') }}</label>
                    </div>
                </div>
                <button class="govuk-button govuk-button--secondary govuk-!-margin-top-4" data-module="govuk-button">{{ __('event_offline_checkin.attendee.replace') }}</button>
            </form>
        </section>

        <section aria-labelledby="revoke-code-heading">
            <h2 class="govuk-heading-m" id="revoke-code-heading">{{ __('event_offline_checkin.attendee.revoke') }}</h2>
            <form method="post" action="{{ route('govuk-alpha.events.check-in.credential.revoke', ['tenantSlug' => $tenantSlug, 'id' => $event['id']]) }}">
                @csrf
                <input type="hidden" name="credential_id" value="{{ $credential->id }}">
                <input type="hidden" name="expected_version" value="{{ $credential->credential_version }}">
                <div class="govuk-form-group">
                    <label class="govuk-label" for="revoke-credential-reason">{{ __('event_offline_checkin.attendee.reason') }}</label>
                    <div class="govuk-hint" id="revoke-credential-reason-hint">{{ __('event_offline_checkin.attendee.reason_hint') }}</div>
                    <textarea class="govuk-textarea" id="revoke-credential-reason" name="reason" rows="3" maxlength="500" required aria-describedby="revoke-credential-reason-hint"></textarea>
                </div>
                <div class="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="revoke-credential-confirm" name="confirmation" type="checkbox" value="1" required>
                        <label class="govuk-label govuk-checkboxes__label" for="revoke-credential-confirm">{{ __('event_offline_checkin.attendee.revoke_confirm') }}</label>
                    </div>
                </div>
                <button class="govuk-button govuk-button--warning govuk-!-margin-top-4" data-module="govuk-button">{{ __('event_offline_checkin.attendee.revoke') }}</button>
            </form>
        </section>
    @endif
@endsection

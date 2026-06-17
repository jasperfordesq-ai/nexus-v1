{{-- Copyright (c) 2024-2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $formatDate = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : null;
        $formatDateTime = fn ($value): ?string => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y, g:ia') : null;
        $organizationName = $opportunity['organization']['name'] ?? null;
        $categoryName = $opportunity['category'] ?? null;
        $hasApplied = !empty($opportunity['has_applied']);
        $application = $opportunity['application'] ?? null;
        $isApprovedApplicant = is_array($application) && ($application['status'] ?? null) === 'approved';
        $signedUpShiftId = $isApprovedApplicant ? (int) ($application['shift_id'] ?? 0) : 0;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.volunteering.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_volunteering') }}</a>

    @if ($status === 'apply-created')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="apply-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="apply-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.volunteering.apply_created') }}</p>
            </div>
        </div>
    @elseif ($status === 'apply-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ __('govuk_alpha.volunteering.apply_failed') }}</p>
                </div>
            </div>
        </div>
    @elseif (in_array($status, ['shift-signed-up', 'shift-cancelled'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="shift-success-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="shift-success-title">{{ __('govuk_alpha.states.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $status === 'shift-signed-up' ? __('govuk_alpha.volunteering.shift_signed_up_detail') : __('govuk_alpha.volunteering.shift_cancelled_detail') }}</p>
            </div>
        </div>
    @elseif (in_array($status, ['shift-signup-failed', 'shift-cancel-failed'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ $status === 'shift-signup-failed' ? __('govuk_alpha.volunteering.shift_signup_failed') : __('govuk_alpha.volunteering.shift_cancel_failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-l">{{ __('govuk_alpha.volunteering.detail_title') }}</span>
            <h1 class="govuk-heading-xl">{{ $opportunity['title'] ?? __('govuk_alpha.volunteering.detail_title') }}</h1>
            @if (!empty($opportunity['is_remote']))
                <strong class="govuk-tag govuk-tag--turquoise">{{ __('govuk_alpha.volunteering.remote') }}</strong>
            @endif
            <div class="govuk-body govuk-!-margin-top-5">{!! nl2br(e((string) ($opportunity['description'] ?? ''))) !!}</div>
        </div>
        <div class="govuk-grid-column-one-third">
            @php $orgLogo = $opportunity['organization']['logo_url'] ?? null; @endphp
            <div class="govuk-inset-text">
                @if (!empty($orgLogo))
                    <img class="nexus-alpha-org-logo govuk-!-margin-bottom-2" src="{{ $orgLogo }}" alt="{{ __('govuk_alpha.volunteering.org_logo_alt', ['name' => $organizationName ?: __('govuk_alpha.volunteering.organization')]) }}" width="96" height="96" loading="lazy" decoding="async">
                @endif
                <p class="govuk-body govuk-!-margin-bottom-0">{{ $organizationName ?: __('govuk_alpha.volunteering.organization') }}</p>
            </div>
        </div>
    </div>

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.polish_gamify.vol_about_heading') }}</h2>
    <dl class="govuk-summary-list">
        @if ($organizationName)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.organization') }}</dt>
                <dd class="govuk-summary-list__value">{{ $organizationName }}</dd>
            </div>
        @endif
        @if (!empty($opportunity['location']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.location') }}</dt>
                <dd class="govuk-summary-list__value">{{ $opportunity['location'] }}</dd>
            </div>
        @endif
        @if ($categoryName)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.category_label') }}</dt>
                <dd class="govuk-summary-list__value">{{ $categoryName }}</dd>
            </div>
        @endif
        @if (!empty($opportunity['skills_needed']))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.skills') }}</dt>
                <dd class="govuk-summary-list__value">{{ $opportunity['skills_needed'] }}</dd>
            </div>
        @endif
        @if ($formatDate($opportunity['start_date'] ?? null))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.start_date') }}</dt>
                <dd class="govuk-summary-list__value">{{ $formatDate($opportunity['start_date'] ?? null) }}</dd>
            </div>
        @endif
        @if ($formatDate($opportunity['end_date'] ?? null))
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ __('govuk_alpha.volunteering.end_date') }}</dt>
                <dd class="govuk-summary-list__value">{{ $formatDate($opportunity['end_date'] ?? null) }}</dd>
            </div>
        @endif
    </dl>

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.volunteering.shifts_title') }}</h2>
    @if (empty($opportunity['shifts']))
        <div class="govuk-inset-text">{{ __('govuk_alpha.volunteering.no_shifts') }}</div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($opportunity['shifts'] as $shift)
                @php
                    $shiftId = (int) ($shift['id'] ?? 0);
                    $shiftIsPast = !empty($shift['start_time']) && strtotime((string) $shift['start_time']) < time();
                    $isSignedUpHere = $signedUpShiftId > 0 && $signedUpShiftId === $shiftId;
                    $shiftHasSpace = !array_key_exists('spots_available', $shift) || $shift['spots_available'] === null || (int) $shift['spots_available'] > 0;
                @endphp
                <article class="nexus-alpha-card">
                    <h3 class="govuk-heading-m govuk-!-margin-bottom-2">{{ $formatDateTime($shift['start_time'] ?? null) }}</h3>
                    <dl class="nexus-alpha-inline-list">
                        @if (!empty($shift['end_time']))
                            <div>
                                <dt>{{ __('govuk_alpha.events.ends') }}</dt>
                                <dd>{{ $formatDateTime($shift['end_time']) }}</dd>
                            </div>
                        @endif
                        @if (!empty($shift['capacity']))
                            <div>
                                <dt>{{ __('govuk_alpha.volunteering.shift_capacity', ['count' => $shift['capacity']]) }}</dt>
                                <dd>{{ __('govuk_alpha.volunteering.shift_spaces_left', ['count' => $shift['spots_available'] ?? $shift['capacity']]) }}</dd>
                            </div>
                        @endif
                    </dl>
                    @if ($isApprovedApplicant && $shiftId > 0 && !$shiftIsPast)
                        @if ($isSignedUpHere)
                            <p class="govuk-!-margin-bottom-2"><strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.volunteering.shift_signed_up') }}</strong></p>
                            <form method="post" action="{{ route('govuk-alpha.volunteering.shifts.cancel', ['tenantSlug' => $tenantSlug, 'id' => $opportunity['id'], 'shiftId' => $shiftId]) }}">
                                @csrf
                                <button class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.volunteering.cancel_shift') }}</button>
                            </form>
                        @elseif ($shiftHasSpace)
                            <form method="post" action="{{ route('govuk-alpha.volunteering.shifts.signup', ['tenantSlug' => $tenantSlug, 'id' => $opportunity['id'], 'shiftId' => $shiftId]) }}">
                                @csrf
                                <button class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha.volunteering.sign_up_shift') }}</button>
                            </form>
                        @else
                            <strong class="govuk-tag govuk-tag--red">{{ __('govuk_alpha.volunteering.shift_full') }}</strong>
                        @endif
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    @if ($requiresAuth)
        <div class="govuk-notification-banner govuk-!-margin-top-7" data-module="govuk-notification-banner" role="region" aria-labelledby="volunteering-auth-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="volunteering-auth-title">{{ __('govuk_alpha.states.auth_required') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha.volunteering.auth_required_detail') }}</p>
                <div class="govuk-button-group">
                    <a class="govuk-button" href="{{ route('govuk-alpha.login', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha.nav.login') }}</a>
                    <a class="govuk-link" href="{{ route('govuk-alpha.register', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.nav.register') }}</a>
                </div>
            </div>
        </div>
    @elseif ($hasApplied)
        <div class="govuk-inset-text govuk-!-margin-top-7">{{ __('govuk_alpha.volunteering.already_applied') }}</div>
    @else
        <form method="post" action="{{ route('govuk-alpha.volunteering.apply.store', ['tenantSlug' => $tenantSlug, 'id' => $opportunity['id']]) }}" class="govuk-!-margin-top-7">
            @csrf
            <fieldset class="govuk-fieldset" aria-describedby="apply-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.volunteering.apply_title') }}</h2>
                </legend>
                <div id="apply-hint" class="govuk-hint">{{ __('govuk_alpha.volunteering.apply_message_hint') }}</div>
                @if (!empty($opportunity['shifts']))
                    <div class="govuk-form-group">
                        <label class="govuk-label" for="shift_id">{{ __('govuk_alpha.volunteering.shift_label') }}</label>
                        <select class="govuk-select" id="shift_id" name="shift_id">
                            <option value="">{{ __('govuk_alpha.volunteering.no_shift_preference') }}</option>
                            @foreach ($opportunity['shifts'] as $shift)
                                <option value="{{ $shift['id'] }}">{{ $formatDateTime($shift['start_time'] ?? null) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="govuk-form-group">
                    <label class="govuk-label" for="message">{{ __('govuk_alpha.volunteering.apply_message_label') }}</label>
                    <textarea class="govuk-textarea" id="message" name="message" rows="5"></textarea>
                </div>
            </fieldset>
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.actions.apply') }}</button>
        </form>
    @endif
@endsection

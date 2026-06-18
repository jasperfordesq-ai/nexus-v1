{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $interviewList = is_array($interviews ?? null) ? $interviews : [];
        $offerList = is_array($offers ?? null) ? $offers : [];
        $flash = (string) ($status ?? '');

        $interviewTypeLabel = function (string $t): string {
            return match ($t) {
                'phone' => __('govuk_alpha_jobs.responses.type_phone'),
                'in_person' => __('govuk_alpha_jobs.responses.type_in_person'),
                default => __('govuk_alpha_jobs.responses.type_video'),
            };
        };
        $periodLabel = function (string $p): string {
            return match ($p) {
                'hourly' => __('govuk_alpha_jobs.responses.period_hourly'),
                'monthly' => __('govuk_alpha_jobs.responses.period_monthly'),
                'annual' => __('govuk_alpha_jobs.responses.period_annual'),
                default => $p,
            };
        };
        $successMap = [
            'interview-accepted' => 'states_interview_accepted',
            'interview-declined' => 'states_interview_declined',
            'offer-accepted' => 'states_offer_accepted',
            'offer-rejected' => 'states_offer_rejected',
        ];
        $errorMap = [
            'interview-failed' => 'states_interview_failed',
            'offer-failed' => 'states_offer_failed',
        ];
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha_jobs.responses.caption') }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_jobs.responses.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_jobs.responses.description') }}</p>

    @include('accessible-frontend::partials.jobs-nav', ['jobsActiveTab' => 'responses'])

    @if (isset($successMap[$flash]))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="responses-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="responses-status">{{ __('govuk_alpha_jobs.responses.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_jobs.responses.' . $successMap[$flash]) }}</p></div>
        </div>
    @elseif (isset($errorMap[$flash]))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha_jobs.responses.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha_jobs.responses.' . $errorMap[$flash]) }}</li></ul></div></div>
        </div>
    @endif

    {{-- Interviews --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_jobs.responses.interviews_heading') }}</h2>
    @if (empty($interviewList))
        <div class="govuk-inset-text"><p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_jobs.responses.no_interviews') }}</p></div>
    @else
        <div class="nexus-alpha-card-list govuk-!-margin-bottom-6">
            @foreach ($interviewList as $iv)
                @php
                    $ivId = (int) ($iv['id'] ?? 0);
                    $ivVacId = (int) ($iv['vacancy_id'] ?? 0);
                    $ivTitle = trim((string) ($iv['vacancy_title'] ?? '')) ?: __('govuk_alpha_jobs.responses.opportunity_unknown');
                    $ivStatus = (string) ($iv['status'] ?? 'proposed');
                    $ivWhen = !empty($iv['scheduled_at']) ? \Illuminate\Support\Carbon::parse($iv['scheduled_at'])->translatedFormat('j F Y, H:i') : null;
                    $ivMins = $iv['duration_mins'] ?? null;
                    $ivLoc = trim((string) ($iv['location_notes'] ?? ''));
                    $ivType = $interviewTypeLabel((string) ($iv['interview_type'] ?? 'video'));
                    $ivStatusKey = 'govuk_alpha_jobs.responses.interview_status_' . ($ivStatus === 'accepted' ? 'accepted' : ($ivStatus === 'declined' ? 'declined' : 'proposed'));
                    $ivTag = $ivStatus === 'accepted' ? 'govuk-tag--green' : ($ivStatus === 'declined' ? 'govuk-tag--grey' : 'govuk-tag--yellow');
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $ivType }}</h3>
                        <strong class="govuk-tag {{ $ivTag }}">{{ __($ivStatusKey) }}</strong>
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha_jobs.responses.for_opportunity', ['title' => $ivTitle]) }}</p>
                    <p class="govuk-body govuk-!-margin-bottom-1">{{ $ivWhen ? __('govuk_alpha_jobs.responses.scheduled_for', ['date' => $ivWhen]) : __('govuk_alpha_jobs.responses.no_date') }}</p>
                    @if ($ivMins)
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha_jobs.responses.duration', ['mins' => (int) $ivMins]) }}</p>
                    @endif
                    @if ($ivLoc !== '')
                        <p class="govuk-body-s govuk-!-margin-bottom-2"><strong>{{ __('govuk_alpha_jobs.responses.location_label') }}:</strong> {{ $ivLoc }}</p>
                    @endif
                    @if ($ivVacId > 0)
                        <p class="govuk-body-s govuk-!-margin-bottom-2"><a class="govuk-link" href="{{ route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => $ivVacId]) }}">{{ __('govuk_alpha_jobs.responses.view_opportunity') }}</a></p>
                    @endif
                    @if ($ivStatus === 'proposed' && $ivId > 0)
                        <form method="post" action="{{ route('govuk-alpha.jobs.interviews.accept', ['tenantSlug' => $tenantSlug, 'interviewId' => $ivId]) }}" class="govuk-!-margin-bottom-2">
                            @csrf
                            <div class="govuk-form-group govuk-!-margin-bottom-2">
                                <label class="govuk-label govuk-label--s" for="iv-accept-note-{{ $ivId }}">{{ __('govuk_alpha_jobs.responses.note_label') }}</label>
                                <div id="iv-accept-note-{{ $ivId }}-hint" class="govuk-hint">{{ __('govuk_alpha_jobs.responses.note_hint') }}</div>
                                <input class="govuk-input govuk-!-width-two-thirds" id="iv-accept-note-{{ $ivId }}" name="note" type="text" maxlength="1000" aria-describedby="iv-accept-note-{{ $ivId }}-hint">
                            </div>
                            <button type="submit" class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_jobs.responses.accept_interview') }}</button>
                        </form>
                        <form method="post" action="{{ route('govuk-alpha.jobs.interviews.decline', ['tenantSlug' => $tenantSlug, 'interviewId' => $ivId]) }}">
                            @csrf
                            <div class="govuk-form-group govuk-!-margin-bottom-2">
                                <label class="govuk-label govuk-label--s" for="iv-decline-note-{{ $ivId }}">{{ __('govuk_alpha_jobs.responses.note_label') }}</label>
                                <div id="iv-decline-note-{{ $ivId }}-hint" class="govuk-hint">{{ __('govuk_alpha_jobs.responses.note_hint') }}</div>
                                <input class="govuk-input govuk-!-width-two-thirds" id="iv-decline-note-{{ $ivId }}" name="note" type="text" maxlength="1000" aria-describedby="iv-decline-note-{{ $ivId }}-hint">
                            </div>
                            <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_jobs.responses.decline_interview') }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    {{-- Offers --}}
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_jobs.responses.offers_heading') }}</h2>
    @if (empty($offerList))
        <div class="govuk-inset-text"><p class="govuk-body govuk-!-margin-bottom-0">{{ __('govuk_alpha_jobs.responses.no_offers') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($offerList as $of)
                @php
                    $ofId = (int) ($of['id'] ?? 0);
                    $ofVacId = (int) ($of['vacancy_id'] ?? 0);
                    $ofTitle = trim((string) ($of['vacancy_title'] ?? '')) ?: __('govuk_alpha_jobs.responses.opportunity_unknown');
                    $ofStatus = (string) ($of['status'] ?? 'pending');
                    $ofSalary = $of['salary_offered'] ?? null;
                    $ofCurrency = trim((string) ($of['salary_currency'] ?? ''));
                    $ofType = (string) ($of['salary_type'] ?? '');
                    $ofMessage = trim((string) ($of['message'] ?? ''));
                    $ofStart = !empty($of['start_date']) ? \Illuminate\Support\Carbon::parse($of['start_date'])->translatedFormat('j F Y') : null;
                    $ofExpires = !empty($of['expires_at']) ? \Illuminate\Support\Carbon::parse($of['expires_at'])->translatedFormat('j F Y') : null;
                    $isExpired = !empty($of['expires_at']) && \Illuminate\Support\Carbon::parse($of['expires_at'])->isPast() && $ofStatus === 'pending';
                    $effectiveStatus = $isExpired ? 'expired' : $ofStatus;
                    $ofStatusKey = 'govuk_alpha_jobs.responses.offer_status_' . (in_array($effectiveStatus, ['accepted', 'rejected', 'withdrawn', 'expired'], true) ? $effectiveStatus : 'pending');
                    $ofTag = match ($effectiveStatus) {
                        'accepted' => 'govuk-tag--green',
                        'rejected', 'withdrawn', 'expired' => 'govuk-tag--grey',
                        default => 'govuk-tag--yellow',
                    };
                    $canRespond = $ofStatus === 'pending' && !$isExpired && $ofId > 0;
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $ofTitle }}</h3>
                        <strong class="govuk-tag {{ $ofTag }}">{{ __($ofStatusKey) }}</strong>
                    </div>
                    <p class="govuk-body govuk-!-margin-bottom-1">
                        @if ($ofSalary !== null && $ofSalary !== '' && (float) $ofSalary > 0)
                            {{ __('govuk_alpha_jobs.responses.salary_line', [
                                'amount' => number_format((float) $ofSalary),
                                'currency' => $ofCurrency !== '' ? $ofCurrency : '',
                                'period' => $ofType !== '' ? $periodLabel($ofType) : __('govuk_alpha_jobs.responses.period_hourly'),
                            ]) }}
                        @else
                            {{ __('govuk_alpha_jobs.responses.salary_unspecified') }}
                        @endif
                    </p>
                    @if ($ofStart)
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha_jobs.responses.start_date', ['date' => $ofStart]) }}</p>
                    @endif
                    @if ($ofExpires && $canRespond)
                        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha_jobs.responses.expires_on', ['date' => $ofExpires]) }}</p>
                    @endif
                    @if ($ofMessage !== '')
                        <h4 class="govuk-heading-s govuk-!-margin-bottom-1 govuk-!-margin-top-2">{{ __('govuk_alpha_jobs.responses.message_heading') }}</h4>
                        <blockquote class="govuk-inset-text govuk-!-margin-top-0 govuk-!-margin-bottom-2"><p class="govuk-body govuk-!-margin-bottom-0">{{ $ofMessage }}</p></blockquote>
                    @endif
                    @if ($ofVacId > 0)
                        <p class="govuk-body-s govuk-!-margin-bottom-2"><a class="govuk-link" href="{{ route('govuk-alpha.jobs.show', ['tenantSlug' => $tenantSlug, 'id' => $ofVacId]) }}">{{ __('govuk_alpha_jobs.responses.view_opportunity') }}</a></p>
                    @endif
                    @if ($canRespond)
                        <div class="govuk-warning-text govuk-!-margin-bottom-2">
                            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                            <strong class="govuk-warning-text__text">
                                <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning') }}</span>
                                {{ __('govuk_alpha_jobs.responses.accept_offer_warning') }}
                            </strong>
                        </div>
                        <div class="nexus-alpha-actions">
                            <form method="post" action="{{ route('govuk-alpha.jobs.offers.accept', ['tenantSlug' => $tenantSlug, 'offerId' => $ofId]) }}" class="nexus-alpha-linkform">
                                @csrf
                                <button type="submit" class="govuk-button govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_jobs.responses.accept_offer') }}</button>
                            </form>
                            <form method="post" action="{{ route('govuk-alpha.jobs.offers.reject', ['tenantSlug' => $tenantSlug, 'offerId' => $ofId]) }}" class="nexus-alpha-linkform">
                                @csrf
                                <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0" data-module="govuk-button">{{ __('govuk_alpha_jobs.responses.reject_offer') }}</button>
                            </form>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection

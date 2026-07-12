{{--
  Copyright © 2024–2026 Jasper Ford
  SPDX-License-Identifier: AGPL-3.0-or-later
  Author: Jasper Ford
  See NOTICE file for attribution and acknowledgements.
--}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.registration.index', ['tenantSlug' => $tenantSlug, 'id' => $eventId]) }}">
        {{ __('event_registration.title') }}
    </a>

    <h1 class="govuk-heading-xl">{{ __('event_registration.accessible.review_submission', ['id' => $submissionId]) }}</h1>
    <div class="govuk-inset-text">
        <strong>{{ __('event_registration.submissions.audit_title') }}</strong><br>
        {{ __('event_registration.submissions.audit_description') }}
    </div>

    @forelse ($answers as $stableKey => $answer)
        <div class="govuk-summary-card">
            <div class="govuk-summary-card__title-wrapper">
                <h2 class="govuk-summary-card__title">{{ $questions[(int) ($answer['question_id'] ?? 0)] ?? $stableKey }}</h2>
                <strong class="govuk-tag">{{ __('event_registration.classifications.' . ($answer['classification'] ?? 'internal')) }}</strong>
            </div>
            <div class="govuk-summary-card__content">
                @if ($answer['purged'] ?? false)
                    <p class="govuk-body">{{ __('event_registration.submissions.purged') }}</p>
                @elseif (is_bool($answer['value'] ?? null))
                    <p class="govuk-body">{{ ($answer['value'] ?? false) ? __('govuk_alpha.cookie_settings.yes') : __('govuk_alpha.cookie_settings.no') }}</p>
                @elseif (is_array($answer['value'] ?? null))
                    <ul class="govuk-list govuk-list--bullet">
                        @foreach (($answer['value'] ?? []) as $value)<li>{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</li>@endforeach
                    </ul>
                @else
                    <p class="govuk-body govuk-!-white-space-pre-wrap">{{ $answer['value'] ?? '' }}</p>
                @endif
            </div>
        </div>
    @empty
        <p class="govuk-body">{{ __('event_registration.submissions.empty') }}</p>
    @endforelse
@endsection

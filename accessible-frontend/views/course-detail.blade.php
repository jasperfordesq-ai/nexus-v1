{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $cTitle = trim((string) ($course['title'] ?? '')) ?: __('govuk_alpha.courses.title');
        $cost = (float) ($course['credit_cost'] ?? 0);
        $level = trim((string) ($course['level'] ?? ''));
        $author = trim((string) ($course['author']['name'] ?? ''));
        $sections = is_array($course['sections'] ?? null) ? $course['sections'] : [];
        $costLabel = $cost > 0
            ? (rtrim(rtrim(number_format($cost, 2), '0'), '.') . ' ' . __('govuk_alpha.courses.credits_label'))
            : __('govuk_alpha.courses.free');
    @endphp

    <a href="{{ route('govuk-alpha.courses.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.courses.back') }}</a>

    @if ($status === 'enrolled')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="region" aria-live="polite" aria-labelledby="course-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="course-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.courses.states.enrolled') }}</p></div>
        </div>
    @elseif (in_array($status, ['insufficient-credits', 'enrol-failed'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ __('govuk_alpha.courses.states.' . $status) }}</li></ul></div></div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ $author !== '' ? $author : ($tenant['name'] ?? $tenantSlug) }}</span>
    <div class="nexus-alpha-module-row">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $cTitle }}</h1>
        <strong class="govuk-tag {{ $cost > 0 ? 'govuk-tag--blue' : 'govuk-tag--green' }}">{{ $costLabel }}</strong>
    </div>
    @if ($level !== '')
        <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha.courses.level_label') }}: {{ \Illuminate\Support\Facades\Lang::has('govuk_alpha.courses.levels.' . $level) ? __('govuk_alpha.courses.levels.' . $level) : ucfirst($level) }}</p>
    @endif

    @if (trim((string) ($course['description'] ?? '')) !== '')
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.courses.about_label') }}</h2>
        <div class="govuk-body">{!! nl2br(e(strip_tags((string) $course['description']))) !!}</div>
    @endif

    @if (!empty($sections))
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.courses.contents_label') }}</h2>
        @foreach ($sections as $section)
            @php
                $sTitle = trim((string) ($section['title'] ?? ''));
                $lessons = is_array($section['lessons'] ?? null) ? $section['lessons'] : [];
            @endphp
            @if ($sTitle !== '' || !empty($lessons))
                <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $sTitle !== '' ? $sTitle : __('govuk_alpha.courses.contents_label') }}</h3>
                @if (!empty($lessons))
                    <ul class="govuk-list govuk-list--bullet">
                        @foreach ($lessons as $lesson)
                            @php $lTitle = trim((string) ($lesson['title'] ?? '')); @endphp
                            @if ($lTitle !== '')<li>{{ $lTitle }}</li>@endif
                        @endforeach
                    </ul>
                @endif
            @endif
        @endforeach
    @endif

    <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha.courses.enrol_button') }}</h2>
    @if ($isEnrolled)
        <p class="govuk-inset-text">{{ __('govuk_alpha.courses.enrolled_notice') }}</p>
    @else
        @if ($cost > 0)
            <p class="govuk-body">{{ __('govuk_alpha.courses.cost_notice', ['count' => rtrim(rtrim(number_format($cost, 2), '0'), '.')]) }}</p>
        @else
            <p class="govuk-body">{{ __('govuk_alpha.courses.free_notice') }}</p>
        @endif
        <form method="post" action="{{ route('govuk-alpha.courses.enrol', ['tenantSlug' => $tenantSlug, 'id' => $course['id']]) }}">
            @csrf
            <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.courses.enrol_button') }}</button>
        </form>
    @endif
@endsection

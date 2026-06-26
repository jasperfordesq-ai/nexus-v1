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
        <div class="govuk-panel govuk-panel--confirmation govuk-!-margin-bottom-6">
            <h1 class="govuk-panel__title">{{ __('govuk_alpha.states.success_title') }}</h1>
            <div class="govuk-panel__body">{{ __('govuk_alpha.courses.states.enrolled') }}</div>
        </div>
    @elseif ($status === 'review-saved')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="course-review-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="course-review-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.courses.states.review-saved') }}</p></div>
        </div>
    @elseif (in_array($status, ['insufficient-credits', 'enrol-failed', 'certificate-locked', 'certificate-failed', 'review-invalid', 'review-not-enrolled', 'review-failed'], true))
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

    {{-- Prerequisites (with per-learner completion status) --}}
    @if (!empty($prerequisites))
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.courses.prerequisites_label') }}</h2>
        <ul class="govuk-list">
            @foreach ($prerequisites as $pre)
                @php
                    $preTitle = trim((string) ($pre['title'] ?? ''));
                    $preId = (int) ($pre['id'] ?? 0);
                    $preDone = !empty($pre['completed']);
                @endphp
                @if ($preTitle !== '')
                    <li class="govuk-!-margin-bottom-1">
                        @if ($preId > 0)<a class="govuk-link" href="{{ route('govuk-alpha.courses.show', ['tenantSlug' => $tenantSlug, 'id' => $preId]) }}">{{ $preTitle }}</a>@else{{ $preTitle }}@endif
                        @if ($preDone)<strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha.courses.prerequisite_done') }}</strong>@else<strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha.courses.prerequisite_todo') }}</strong>@endif
                    </li>
                @endif
            @endforeach
        </ul>
    @endif

    {{-- Certificate download (once the course is completed) --}}
    @if (!empty($isCompleted))
        <h2 class="govuk-heading-l">{{ __('govuk_alpha.courses.certificate_label') }}</h2>
        <p class="govuk-body">
            <a class="govuk-button govuk-button--secondary" role="button" data-module="govuk-button" href="{{ route('govuk-alpha.courses.certificate', ['tenantSlug' => $tenantSlug, 'id' => $course['id']]) }}" target="_blank" rel="noopener noreferrer">{{ __('govuk_alpha.courses.certificate_download') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha.opens_new_tab') }}</span></a>
        </p>
    @endif

    {{-- Ratings & reviews --}}
    @php
        $reviews = is_array($reviews ?? null) ? $reviews : [];
        $ratingAvg = (float) ($ratingAvg ?? 0);
        $ratingCount = (int) ($ratingCount ?? 0);
        $stars = fn (int $n): string => str_repeat('★', max(0, min(5, $n))) . str_repeat('☆', max(0, 5 - max(0, min(5, $n))));
    @endphp
    <h2 class="govuk-heading-l govuk-!-margin-top-6" id="reviews">{{ __('govuk_alpha.courses.reviews_label') }}</h2>
    @if ($ratingCount > 0)
        <p class="govuk-body-l govuk-!-margin-bottom-1">
            <span aria-hidden="true">{{ $stars((int) round($ratingAvg)) }}</span>
            <strong>{{ number_format($ratingAvg, 1) }}</strong> {{ __('govuk_alpha.courses.out_of_5') }}
        </p>
        <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-4">{{ trans_choice('govuk_alpha.courses.based_on', $ratingCount, ['count' => $ratingCount]) }}</p>
    @endif

    @if (!empty($reviews))
        <ul class="govuk-list govuk-!-margin-bottom-6">
            @foreach ($reviews as $review)
                @php
                    $rName = trim((string) ($review['name'] ?? '')) ?: __('govuk_alpha.courses.review_anonymous');
                    $rRating = (int) ($review['rating'] ?? 0);
                    $rBody = trim((string) ($review['body'] ?? ''));
                    $rDate = !empty($review['created_at']) ? \Illuminate\Support\Carbon::parse($review['created_at'])->translatedFormat('j F Y') : null;
                @endphp
                <li class="govuk-!-margin-bottom-3">
                    <p class="govuk-body-s govuk-!-margin-bottom-1">
                        <span aria-hidden="true">{{ $stars($rRating) }}</span>
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha.courses.rating_of_5', ['rating' => $rRating]) }}</span>
                        <strong>{{ $rName }}</strong>@if ($rDate) · {{ $rDate }}@endif
                    </p>
                    @if ($rBody !== '')<p class="govuk-body govuk-!-margin-bottom-0">{{ $rBody }}</p>@endif
                </li>
            @endforeach
        </ul>
    @elseif ($ratingCount === 0)
        <p class="govuk-body">{{ __('govuk_alpha.courses.no_reviews_yet') }}</p>
    @endif

    @if (!empty($canReview))
        <h3 class="govuk-heading-m">{{ __('govuk_alpha.courses.leave_review_heading') }}</h3>
        <form method="post" action="{{ route('govuk-alpha.courses.reviews.store', ['tenantSlug' => $tenantSlug, 'id' => $course['id']]) }}">
            @csrf
            <div class="govuk-form-group">
                <label class="govuk-label" for="rating">{{ __('govuk_alpha.courses.rating_label') }}</label>
                <select class="govuk-select" id="rating" name="rating">
                    @foreach ([5, 4, 3, 2, 1] as $r)
                        <option value="{{ $r }}">{{ __('govuk_alpha.courses.rating_option', ['rating' => $r]) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="body">{{ __('govuk_alpha.courses.review_body_label') }}</label>
                <textarea class="govuk-textarea" id="body" name="body" rows="4" maxlength="2000"></textarea>
            </div>
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.courses.review_submit') }}</button>
        </form>
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

    <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha.polish_commerce.course_enrol_section_heading') }}</h2>
    @if ($isEnrolled)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.courses.enrolled_notice') }}</p></div>
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

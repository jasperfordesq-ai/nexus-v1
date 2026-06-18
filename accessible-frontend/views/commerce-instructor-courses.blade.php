{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $statusMessages = [
            'deleted' => __('govuk_alpha_commerce.instructor.status_deleted'),
            'delete-failed' => __('govuk_alpha_commerce.instructor.status_delete_failed'),
        ];
        $statusMessage = $status !== null && isset($statusMessages[$status]) ? $statusMessages[$status] : null;
        $statusIsError = $status === 'delete-failed';
    @endphp

    <a href="{{ route('govuk-alpha.courses.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha_commerce.common.back_to_courses') }}</a>

    @include('accessible-frontend::partials.commerce-courses-nav', ['coursesActiveTab' => 'teaching'])

    @if ($statusMessage !== null)
        @if ($statusIsError)
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
                    <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ $statusMessage }}</li></ul></div>
                </div>
            </div>
        @else
            <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">{{ $statusMessage }}</p>
                </div>
            </div>
        @endif
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_commerce.instructor.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.instructor.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.instructor.description') }}</p>

    @if ($canAuthor)
        <p class="govuk-body">
            <a class="govuk-button" href="{{ route('govuk-alpha.courses.instructor.create', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha_commerce.instructor.create_button') }}</a>
        </p>
    @else
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_commerce.instructor.cannot_author') }}</p></div>
    @endif

    @if (empty($courses))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_commerce.instructor.empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($courses as $course)
                @php
                    $courseId = (int) ($course['id'] ?? 0);
                    $cTitle = trim((string) ($course['title'] ?? '')) ?: __('govuk_alpha.courses.title');
                    $cStatus = (string) ($course['status'] ?? 'draft');
                    $cModeration = (string) ($course['moderation_status'] ?? 'pending');
                    $enrolCount = (int) ($course['enrollment_count'] ?? 0);
                    $completionCount = (int) ($course['completion_count'] ?? 0);
                    $isPublished = $cStatus === 'published' && $cModeration === 'approved';
                    $isPendingReview = $cStatus !== 'draft' && $cModeration === 'pending';
                    if ($isPublished) {
                        $tagClass = 'govuk-tag--green';
                        $tagLabel = __('govuk_alpha_commerce.instructor.status_published');
                    } elseif ($isPendingReview) {
                        $tagClass = 'govuk-tag--yellow';
                        $tagLabel = __('govuk_alpha_commerce.instructor.status_pending_review');
                    } else {
                        $tagClass = 'govuk-tag--grey';
                        $tagLabel = __('govuk_alpha_commerce.instructor.status_draft');
                    }
                    $editHref = route('govuk-alpha.courses.instructor.edit', ['tenantSlug' => $tenantSlug, 'id' => $courseId]);
                    $analyticsHref = route('govuk-alpha.courses.instructor.analytics', ['tenantSlug' => $tenantSlug, 'id' => $courseId]);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-module-row">
                        <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $cTitle }}</h2>
                        <strong class="govuk-tag {{ $tagClass }}">{{ $tagLabel }}</strong>
                    </div>
                    <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-2">
                        {{ __('govuk_alpha_commerce.instructor.enrollments_label') }}: {{ $enrolCount }}
                        &middot;
                        {{ __('govuk_alpha_commerce.instructor.completions_label') }}: {{ $completionCount }}
                    </p>
                    <div class="nexus-alpha-actions">
                        <a class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" href="{{ $editHref }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha_commerce.instructor.action_edit') }}</a>
                        <a class="govuk-link govuk-!-margin-left-3" href="{{ $analyticsHref }}">{{ __('govuk_alpha_commerce.instructor.action_analytics') }}</a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection

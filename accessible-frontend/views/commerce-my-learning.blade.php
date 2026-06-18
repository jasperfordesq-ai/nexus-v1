{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $asUrl = fn (string $p): string => $p === '' ? '' : (\Illuminate\Support\Str::startsWith($p, ['http://', 'https://', '/']) ? $p : '/' . ltrim($p, '/'));
    @endphp

    <a href="{{ route('govuk-alpha.courses.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha_commerce.common.back_to_courses') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_commerce.my_learning.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.my_learning.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.my_learning.description') }}</p>

    @if (empty($enrollments))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_commerce.my_learning.empty') }}</p></div>
        <p class="govuk-body">
            <a class="govuk-button" href="{{ route('govuk-alpha.courses.index', ['tenantSlug' => $tenantSlug]) }}" role="button" draggable="false" data-module="govuk-button">{{ __('govuk_alpha_commerce.my_learning.browse_courses') }}</a>
        </p>
    @else
        <div class="nexus-alpha-card-list">
            @foreach ($enrollments as $enrolment)
                @php
                    $course = $enrolment['course'] ?? [];
                    $courseId = (int) ($course['id'] ?? 0);
                    $cTitle = trim((string) ($course['title'] ?? '')) ?: __('govuk_alpha.courses.title');
                    $cover = $asUrl(trim((string) ($course['cover_image'] ?? '')));
                    $percent = (int) round((float) ($enrolment['progress_percent'] ?? 0));
                    $completed = (string) ($enrolment['status'] ?? '') === 'completed';
                    $learnHref = route('govuk-alpha.courses.learn', ['tenantSlug' => $tenantSlug, 'id' => $courseId]);
                @endphp
                <article class="nexus-alpha-card">
                    <div class="nexus-alpha-listing-row">
                        @if ($cover !== '')
                            <div class="nexus-alpha-listing-row__media">
                                <img class="nexus-alpha-card-thumb" src="{{ $cover }}" alt="{{ $cTitle }}" width="120" height="90" loading="lazy" decoding="async">
                            </div>
                        @endif
                        <div class="nexus-alpha-listing-row__body">
                            <div class="nexus-alpha-module-row">
                                <h2 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $cTitle }}</h2>
                                <strong class="govuk-tag {{ $completed ? 'govuk-tag--green' : 'govuk-tag--blue' }}">{{ $completed ? __('govuk_alpha_commerce.my_learning.status_completed') : __('govuk_alpha_commerce.my_learning.status_in_progress') }}</strong>
                            </div>
                            <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-1">{{ __('govuk_alpha_commerce.my_learning.progress_label', ['percent' => $percent]) }}</p>
                            <progress value="{{ $percent }}" max="100" aria-label="{{ __('govuk_alpha_commerce.my_learning.progress_label', ['percent' => $percent]) }}">{{ $percent }}%</progress>
                            <div class="nexus-alpha-actions govuk-!-margin-top-2">
                                <a class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0" href="{{ $learnHref }}" role="button" draggable="false" data-module="govuk-button">{{ $percent > 0 ? __('govuk_alpha_commerce.my_learning.resume') : __('govuk_alpha_commerce.my_learning.start') }}</a>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection

{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $courseId = (int) ($course['id'] ?? 0);
        $percent = (int) round((float) ($progressPercent ?? 0));
        $statusMessages = [
            'lesson-completed' => __('govuk_alpha_commerce.learn.lesson_completed_status'),
            'course-completed' => __('govuk_alpha_commerce.learn.course_completed_status'),
        ];
        $contentTypeLabels = [
            'video' => __('govuk_alpha_commerce.learn.content_type_video'),
            'text' => __('govuk_alpha_commerce.learn.content_type_text'),
            'pdf' => __('govuk_alpha_commerce.learn.content_type_pdf'),
            'embed' => __('govuk_alpha_commerce.learn.content_type_embed'),
            'quiz' => __('govuk_alpha_commerce.learn.content_type_quiz'),
        ];
        $current = $currentLesson ?? null;
    @endphp

    <a href="{{ route('govuk-alpha.courses.show', ['tenantSlug' => $tenantSlug, 'id' => $courseId]) }}" class="govuk-back-link">{{ __('govuk_alpha_commerce.common.back_to_courses') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_commerce.learn.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ trim((string) ($course['title'] ?? '')) ?: __('govuk_alpha_commerce.learn.title') }}</h1>

    @if (($status ?? null) !== null && isset($statusMessages[$status]))
        <div class="govuk-notification-banner govuk-notification-banner--success" role="region" aria-labelledby="commerce-learn-status" data-module="govuk-notification-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="commerce-learn-status">{{ __('govuk_alpha_commerce.common.notice_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ $statusMessages[$status] }}</p>
            </div>
        </div>
    @endif

    <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_commerce.learn.progress_label', ['percent' => $percent]) }}</p>
    <progress value="{{ $percent }}" max="100" aria-label="{{ __('govuk_alpha_commerce.learn.progress_label', ['percent' => $percent]) }}">{{ $percent }}%</progress>

    @if ($isCompleted ?? false)
        <div class="govuk-panel govuk-panel--confirmation govuk-!-margin-top-4 govuk-!-margin-bottom-4">
            <h2 class="govuk-panel__title">{{ __('govuk_alpha_commerce.learn.completed_banner') }}</h2>
        </div>
    @endif

    <div class="govuk-grid-row govuk-!-margin-top-4">
        <div class="govuk-grid-column-one-third">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_commerce.learn.lessons_heading') }}</h2>
            @if (empty($sections))
                <p class="govuk-body">{{ __('govuk_alpha_commerce.learn.no_lessons') }}</p>
            @else
                @foreach ($sections as $section)
                    @if (trim((string) ($section['title'] ?? '')) !== '')
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">{{ $section['title'] }}</h3>
                    @endif
                    <ul class="govuk-list">
                        @foreach (($section['lessons'] ?? []) as $lesson)
                            @php
                                $isCurrent = $current !== null && (int) ($current['id'] ?? 0) === (int) ($lesson['id'] ?? 0);
                                $lessonHref = route('govuk-alpha.courses.learn', ['tenantSlug' => $tenantSlug, 'id' => $courseId, 'lesson' => $lesson['id']]);
                            @endphp
                            <li class="govuk-!-margin-bottom-1">
                                @if (!($lesson['available'] ?? true))
                                    <span>{{ $lesson['title'] }} <span class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha_commerce.learn.lesson_locked') }}</span></span>
                                @elseif ($isCurrent)
                                    <strong>{{ $lesson['title'] }}</strong>
                                    @if ($lesson['is_completed'] ?? false)<span class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_commerce.learn.lesson_done') }}</span>@endif
                                @else
                                    <a class="govuk-link" href="{{ $lessonHref }}">{{ $lesson['title'] }}</a>
                                    @if ($lesson['is_completed'] ?? false)<span class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_commerce.learn.lesson_done') }}</span>@endif
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            @endif
        </div>

        <div class="govuk-grid-column-two-thirds">
            @if ($current === null)
                <p class="govuk-body">{{ __('govuk_alpha_commerce.learn.select_lesson') }}</p>
            @else
                @php
                    $ctype = (string) ($current['content_type'] ?? 'text');
                    $video = trim((string) ($current['video_url'] ?? ''));
                    $embed = trim((string) ($current['embed_url'] ?? ''));
                    $attachment = trim((string) ($current['attachment_url'] ?? ''));
                @endphp
                <h2 class="govuk-heading-l">{{ $current['title'] ?? '' }}</h2>
                <p class="govuk-body-s nexus-alpha-meta">{{ $contentTypeLabels[$ctype] ?? $ctype }}</p>

                @if ($ctype === 'video' && $video !== '')
                    <video class="nexus-alpha-detail-hero" controls preload="metadata" width="100%">
                        <source src="{{ $video }}">
                        {{ __('govuk_alpha_commerce.learn.video_unsupported') }}
                    </video>
                    <p class="govuk-body"><a class="govuk-link" href="{{ $video }}" target="_blank" rel="noopener noreferrer">{{ __('govuk_alpha_commerce.learn.open_video') }}</a></p>
                @elseif ($ctype === 'embed' && $embed !== '')
                    <p class="govuk-body"><a class="govuk-link" href="{{ $embed }}" target="_blank" rel="noopener noreferrer">{{ __('govuk_alpha_commerce.learn.open_video') }}</a></p>
                @endif

                @if (trim((string) ($current['body'] ?? '')) !== '')
                    <div class="govuk-body">{!! \App\Helpers\HtmlSanitizer::sanitizeCms((string) $current['body']) !!}</div>
                @endif

                @if ($attachment !== '')
                    <p class="govuk-body"><a class="govuk-link" href="{{ $attachment }}" target="_blank" rel="noopener noreferrer">{{ __('govuk_alpha_commerce.learn.download_attachment') }}</a></p>
                @endif

                @if ($current['is_completed'] ?? false)
                    <p class="govuk-body"><strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_commerce.learn.lesson_completed_label') }}</strong></p>
                @else
                    <form method="post" action="{{ route('govuk-alpha.courses.lessons.complete', ['tenantSlug' => $tenantSlug, 'id' => $courseId, 'lessonId' => $current['id']]) }}" class="govuk-!-margin-top-4">
                        @csrf
                        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_commerce.learn.mark_complete') }}</button>
                    </form>
                @endif
            @endif
        </div>
    </div>
@endsection

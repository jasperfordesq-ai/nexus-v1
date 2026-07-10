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
        // Quiz-attempt outcomes. success => green banner, error => error summary.
        $quizStatusMessages = [
            'quiz-passed' => ['success', __('govuk_alpha_commerce.learn.quiz_passed_status')],
            'quiz-pending-review' => ['success', __('govuk_alpha_commerce.learn.quiz_pending_status')],
            'quiz-failed' => ['error', __('govuk_alpha_commerce.learn.quiz_failed_status')],
            'quiz-no-attempts' => ['error', __('govuk_alpha_commerce.learn.quiz_no_attempts_status')],
            'quiz-error' => ['error', __('govuk_alpha_commerce.learn.quiz_error_status')],
            // Drip gate: attempted to complete a lesson still locked by the schedule.
            'lesson-locked' => ['error', __('govuk_alpha_commerce.learn.lesson_locked')],
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
    @elseif (($status ?? null) !== null && isset($quizStatusMessages[$status]))
        @php [$quizBannerKind, $quizBannerMsg] = $quizStatusMessages[$status]; @endphp
        @if ($quizBannerKind === 'success')
            <div class="govuk-notification-banner govuk-notification-banner--success" role="region" aria-labelledby="commerce-learn-status" data-module="govuk-notification-banner">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="commerce-learn-status">{{ __('govuk_alpha_commerce.common.notice_title') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">{{ $quizBannerMsg }}</p>
                </div>
            </div>
        @else
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
                    <div class="govuk-error-summary__body"><p class="govuk-body">{{ $quizBannerMsg }}</p></div>
                </div>
            </div>
        @endif
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
                    <p class="govuk-body"><a class="govuk-link" href="{{ $video }}" target="_blank" rel="noopener noreferrer">{{ __('govuk_alpha_commerce.learn.open_video') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha.opens_new_tab') }}</span></a></p>
                @elseif ($ctype === 'embed' && $embed !== '')
                    <p class="govuk-body"><a class="govuk-link" href="{{ $embed }}" target="_blank" rel="noopener noreferrer">{{ __('govuk_alpha_commerce.learn.open_video') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha.opens_new_tab') }}</span></a></p>
                @endif

                @if (trim((string) ($current['body'] ?? '')) !== '')
                    <div class="govuk-body">{!! \App\Helpers\HtmlSanitizer::sanitizeCms((string) $current['body']) !!}</div>
                @endif

                @if ($attachment !== '')
                    <p class="govuk-body"><a class="govuk-link" href="{{ $attachment }}" target="_blank" rel="noopener noreferrer">{{ __('govuk_alpha_commerce.learn.download_attachment') }}<span class="govuk-visually-hidden"> {{ __('govuk_alpha.opens_new_tab') }}</span></a></p>
                @endif

                @if ($ctype === 'quiz')
                    @php $quiz = $current['quiz'] ?? null; @endphp
                    @if ($quiz === null)
                        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_commerce.learn.quiz_unavailable') }}</p></div>
                    @else
                        @php
                            $quizQuestions = is_array($quiz['questions'] ?? null) ? $quiz['questions'] : [];
                            $attemptsRemaining = $quiz['attempts_remaining'] ?? null; // null = unlimited
                            $lastAttempt = $quiz['last_attempt'] ?? null;
                            $passMark = (int) ($quiz['pass_mark_percent'] ?? 0);
                        @endphp
                        <h3 class="govuk-heading-m govuk-!-margin-top-4">{{ trim((string) ($quiz['title'] ?? '')) ?: __('govuk_alpha_commerce.learn.quiz_heading') }}</h3>
                        @if (trim((string) ($quiz['description'] ?? '')) !== '')
                            <p class="govuk-body">{{ $quiz['description'] }}</p>
                        @endif
                        <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_commerce.learn.quiz_pass_mark', ['percent' => $passMark]) }}
                            @if ($attemptsRemaining !== null) · {{ __('govuk_alpha_commerce.learn.quiz_attempts_remaining', ['count' => (int) $attemptsRemaining]) }}@endif
                        </p>

                        @if ($lastAttempt !== null)
                            <div class="govuk-inset-text">
                                @if (($lastAttempt['grading_status'] ?? '') === 'pending_review')
                                    <p class="govuk-body">{{ __('govuk_alpha_commerce.learn.quiz_last_pending') }}</p>
                                @else
                                    <p class="govuk-body">{{ __('govuk_alpha_commerce.learn.quiz_last_score', ['score' => (int) round((float) ($lastAttempt['score_percent'] ?? 0))]) }}
                                        @if (!empty($lastAttempt['passed']))<strong class="govuk-tag govuk-tag--green">{{ __('govuk_alpha_commerce.learn.quiz_passed_tag') }}</strong>@else<strong class="govuk-tag govuk-tag--red">{{ __('govuk_alpha_commerce.learn.quiz_failed_tag') }}</strong>@endif
                                    </p>
                                @endif
                            </div>
                        @endif

                        @if ($attemptsRemaining !== null && (int) $attemptsRemaining <= 0)
                            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_commerce.learn.quiz_no_attempts_left') }}</p></div>
                        @elseif (empty($quizQuestions))
                            <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_commerce.learn.quiz_no_questions') }}</p></div>
                        @else
                            <form method="post" action="{{ route('govuk-alpha.courses.quiz.submit', ['tenantSlug' => $tenantSlug, 'id' => $courseId, 'lessonId' => $current['id']]) }}" class="govuk-!-margin-top-2">
                                @csrf
                                @foreach ($quizQuestions as $q)
                                    @php
                                        $qid = (int) ($q['id'] ?? 0);
                                        $qtype = (string) ($q['type'] ?? 'short');
                                        $qprompt = trim((string) ($q['prompt'] ?? ''));
                                        $qoptions = is_array($q['options'] ?? null) ? $q['options'] : [];
                                    @endphp
                                    @if ($qid > 0 && $qprompt !== '')
                                        <div class="govuk-form-group">
                                            <fieldset class="govuk-fieldset" aria-describedby="q-{{ $qid }}-hint">
                                                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ $qprompt }}</legend>
                                                @if ($qtype === 'multi' && !empty($qoptions))
                                                    <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                                                        @foreach ($qoptions as $opt)
                                                            @php $oid = (string) (is_array($opt) ? ($opt['id'] ?? '') : $opt); $olabel = trim((string) (is_array($opt) ? ($opt['label'] ?? $oid) : $opt)); @endphp
                                                            @if ($oid !== '')
                                                                <div class="govuk-checkboxes__item">
                                                                    <input class="govuk-checkboxes__input" id="q-{{ $qid }}-{{ $loop->index }}" name="answers[{{ $qid }}][]" type="checkbox" value="{{ $oid }}">
                                                                    <label class="govuk-label govuk-checkboxes__label" for="q-{{ $qid }}-{{ $loop->index }}">{{ $olabel }}</label>
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @elseif (($qtype === 'mcq' || $qtype === 'truefalse') && !empty($qoptions))
                                                    <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                                                        @foreach ($qoptions as $opt)
                                                            @php $oid = (string) (is_array($opt) ? ($opt['id'] ?? '') : $opt); $olabel = trim((string) (is_array($opt) ? ($opt['label'] ?? $oid) : $opt)); @endphp
                                                            @if ($oid !== '')
                                                                <div class="govuk-radios__item">
                                                                    <input class="govuk-radios__input" id="q-{{ $qid }}-{{ $loop->index }}" name="answers[{{ $qid }}]" type="radio" value="{{ $oid }}">
                                                                    <label class="govuk-label govuk-radios__label" for="q-{{ $qid }}-{{ $loop->index }}">{{ $olabel }}</label>
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <textarea class="govuk-textarea" id="q-{{ $qid }}" name="answers[{{ $qid }}]" rows="{{ $qtype === 'essay' ? 5 : 2 }}"></textarea>
                                                @endif
                                            </fieldset>
                                        </div>
                                    @endif
                                @endforeach
                                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_commerce.learn.quiz_submit') }}</button>
                            </form>
                        @endif
                    @endif
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

{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $attempts = $attempts ?? [];
        $courseId = (int) ($courseId ?? 0);
        $fmtTime = function ($iso) {
            $iso = trim((string) $iso);
            if ($iso === '') {
                return __('govuk_alpha_commerce.grading.not_set');
            }
            try {
                return \Illuminate\Support\Carbon::parse($iso)->format('j M Y, H:i');
            } catch (\Throwable $e) {
                return __('govuk_alpha_commerce.grading.not_set');
            }
        };
        $answerText = function ($answer) {
            if (is_array($answer)) {
                $flat = array_map(static fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $answer);
                return implode(', ', $flat);
            }
            if (is_scalar($answer)) {
                return (string) $answer;
            }
            return '';
        };
        $statusMessages = [
            'graded' => ['msg' => __('govuk_alpha_commerce.grading.status_graded'), 'error' => false],
            'grade-failed' => ['msg' => __('govuk_alpha_commerce.grading.status_grade_failed'), 'error' => true],
        ];
        $statusEntry = $status !== null && isset($statusMessages[$status]) ? $statusMessages[$status] : null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.courses.instructor.edit', ['tenantSlug' => $tenantSlug, 'id' => $courseId]) }}">{{ __('govuk_alpha_commerce.grading.back_to_course') }}</a>

    @if ($statusEntry !== null)
        @if ($statusEntry['error'])
            <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                <div role="alert">
                    <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
                    <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li>{{ $statusEntry['msg'] }}</li></ul></div>
                </div>
            </div>
        @else
            <div class="govuk-notification-banner govuk-notification-banner--success" role="alert" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
                <div class="govuk-notification-banner__header">
                    <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">{{ __('govuk_alpha.states.success_title') }}</h2>
                </div>
                <div class="govuk-notification-banner__content">
                    <p class="govuk-notification-banner__heading">{{ $statusEntry['msg'] }}</p>
                </div>
            </div>
        @endif
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha_commerce.grading.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.grading.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.grading.course_label') }}: <strong>{{ $courseTitle ?? '' }}</strong></p>
    <p class="govuk-body">{{ __('govuk_alpha_commerce.grading.description') }}</p>

    @if (empty($attempts))
        <p class="govuk-inset-text">{{ __('govuk_alpha_commerce.grading.empty') }}</p>
    @else
        @foreach ($attempts as $a)
            @php
                $attemptId = (int) ($a['id'] ?? 0);
                $learnerName = trim((string) ($a['user']['name'] ?? '')) ?: ('#' . (int) ($a['user_id'] ?? 0));
                $quizTitle = (string) ($a['quiz']['title'] ?? '');
                $questions = is_array($a['quiz']['questions'] ?? null) ? $a['quiz']['questions'] : [];
                $rawAnswers = $a['answers'] ?? null;
                if (is_string($rawAnswers)) {
                    $decoded = json_decode($rawAnswers, true);
                    $rawAnswers = is_array($decoded) ? $decoded : [];
                }
                $answers = is_array($rawAnswers) ? $rawAnswers : [];
            @endphp
            <article class="nexus-alpha-card govuk-!-margin-bottom-6">
                <div class="nexus-alpha-module-row">
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-1">{{ $learnerName }}</h2>
                    <strong class="govuk-tag govuk-tag--yellow">{{ $quizTitle }}</strong>
                </div>
                <p class="govuk-body-s nexus-alpha-meta govuk-!-margin-bottom-3">{{ __('govuk_alpha_commerce.grading.submitted_label') }}: {{ $fmtTime($a['submitted_at'] ?? null) }}</p>

                <h3 class="govuk-heading-s">{{ __('govuk_alpha_commerce.grading.answers_heading') }}</h3>
                @if (empty($questions))
                    <p class="govuk-hint">{{ __('govuk_alpha_commerce.grading.no_answers') }}</p>
                @else
                    <dl class="govuk-summary-list">
                        @foreach ($questions as $q)
                            @php
                                $qId = (string) ($q['id'] ?? '');
                                $prompt = (string) ($q['prompt'] ?? '');
                                $given = array_key_exists($qId, $answers) ? $answers[$qId] : null;
                                $givenText = trim($answerText($given));
                            @endphp
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">{{ $prompt !== '' ? $prompt : __('govuk_alpha_commerce.grading.question_label') }}</dt>
                                <dd class="govuk-summary-list__value">{{ $givenText !== '' ? $givenText : __('govuk_alpha_commerce.grading.no_answers') }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                <form method="post" action="{{ route('govuk-alpha.courses.instructor.grading.grade', ['tenantSlug' => $tenantSlug, 'id' => $courseId, 'attemptId' => $attemptId]) }}" novalidate>
                    @csrf

                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="score_percent_{{ $attemptId }}">{{ __('govuk_alpha_commerce.grading.score_label') }}</label>
                        <div id="score_percent_{{ $attemptId }}-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.grading.score_hint') }}</div>
                        <input class="govuk-input govuk-input--width-5" id="score_percent_{{ $attemptId }}" name="score_percent" type="number" min="0" max="100" value="{{ (int) round((float) ($a['score_percent'] ?? 0)) }}" aria-describedby="score_percent_{{ $attemptId }}-hint">
                    </div>

                    <div class="govuk-form-group">
                        <fieldset class="govuk-fieldset">
                            <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_commerce.grading.passed_label') }}</legend>
                            <div class="govuk-radios govuk-radios--small govuk-radios--inline" data-module="govuk-radios">
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="passed_yes_{{ $attemptId }}" name="passed" type="radio" value="1" @checked(!empty($a['passed']))>
                                    <label class="govuk-label govuk-radios__label" for="passed_yes_{{ $attemptId }}">{{ __('govuk_alpha_commerce.grading.passed_yes') }}</label>
                                </div>
                                <div class="govuk-radios__item">
                                    <input class="govuk-radios__input" id="passed_no_{{ $attemptId }}" name="passed" type="radio" value="0" @checked(empty($a['passed']))>
                                    <label class="govuk-label govuk-radios__label" for="passed_no_{{ $attemptId }}">{{ __('govuk_alpha_commerce.grading.passed_no') }}</label>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <div class="govuk-form-group">
                        <label class="govuk-label govuk-label--s" for="feedback_{{ $attemptId }}">{{ __('govuk_alpha_commerce.grading.feedback_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                        <div id="feedback_{{ $attemptId }}-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.grading.feedback_hint') }}</div>
                        <textarea class="govuk-textarea" id="feedback_{{ $attemptId }}" name="feedback" rows="3" aria-describedby="feedback_{{ $attemptId }}-hint">{{ (string) ($a['feedback'] ?? '') }}</textarea>
                    </div>

                    <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_commerce.grading.submit_grade') }}</button>
                </form>
            </article>
        @endforeach
    @endif
@endsection

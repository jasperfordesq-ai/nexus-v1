{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $a = $analytics ?? [];
        $total = (int) ($a['total'] ?? 0);
        $maxLesson = max(1, (int) ($maxLessonCompleted ?? 1));
        $pctSuffix = __('govuk_alpha_commerce.analytics.percent_suffix');
        $stats = [
            ['label' => __('govuk_alpha_commerce.analytics.total_enrollments'), 'value' => (int) ($a['total'] ?? 0)],
            ['label' => __('govuk_alpha_commerce.analytics.active'), 'value' => (int) ($a['active'] ?? 0)],
            ['label' => __('govuk_alpha_commerce.analytics.completed'), 'value' => (int) ($a['completed'] ?? 0)],
            ['label' => __('govuk_alpha_commerce.analytics.dropped'), 'value' => (int) ($a['dropped'] ?? 0)],
            ['label' => __('govuk_alpha_commerce.analytics.completion_rate'), 'value' => ((float) ($a['completion_rate'] ?? 0)) . $pctSuffix],
            ['label' => __('govuk_alpha_commerce.analytics.avg_progress'), 'value' => ((float) ($a['avg_progress'] ?? 0)) . $pctSuffix],
            ['label' => __('govuk_alpha_commerce.analytics.avg_quiz_score'), 'value' => ((float) ($a['avg_quiz_score'] ?? 0)) . $pctSuffix],
            ['label' => __('govuk_alpha_commerce.analytics.quiz_attempts'), 'value' => (int) ($a['quiz_attempts'] ?? 0)],
        ];
        $perLesson = is_array($perLesson ?? null) ? $perLesson : [];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.courses.instructor', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.instructor.back_to_dashboard') }}</a>

    @include('accessible-frontend::partials.commerce-courses-nav', ['coursesActiveTab' => 'teaching'])

    <span class="govuk-caption-xl">{{ __('govuk_alpha_commerce.analytics.title') }}</span>
    <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ $courseTitle }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.analytics.description') }}</p>

    @if ($total === 0)
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_commerce.analytics.no_data') }}</p></div>
    @endif

    <dl class="govuk-summary-list">
        @foreach ($stats as $stat)
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">{{ $stat['label'] }}</dt>
                <dd class="govuk-summary-list__value">{{ $stat['value'] }}</dd>
            </div>
        @endforeach
    </dl>

    <h2 class="govuk-heading-l govuk-!-margin-top-6">{{ __('govuk_alpha_commerce.analytics.per_lesson_heading') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha_commerce.analytics.per_lesson_description') }}</p>

    @if (empty($perLesson))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha_commerce.analytics.no_lessons') }}</p></div>
    @else
        <table class="govuk-table">
            <caption class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.analytics.per_lesson_heading') }}</caption>
            <tbody class="govuk-table__body">
                @foreach ($perLesson as $row)
                    @php
                        $lTitle = trim((string) ($row['title'] ?? '')) ?: __('govuk_alpha.courses.title');
                        $count = (int) ($row['completed'] ?? 0);
                        $countLabel = __('govuk_alpha_commerce.analytics.lesson_completed_count', ['count' => $count]);
                    @endphp
                    <tr class="govuk-table__row">
                        <th scope="row" class="govuk-table__header govuk-!-width-one-half">{{ $lTitle }}</th>
                        <td class="govuk-table__cell">
                            <progress value="{{ $count }}" max="{{ $maxLesson }}" aria-label="{{ $countLabel }}">{{ $count }}</progress>
                            <span class="govuk-body-s nexus-alpha-meta govuk-!-margin-left-2">{{ $countLabel }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

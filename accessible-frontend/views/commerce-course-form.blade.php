{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $isEdit = ($mode ?? 'create') === 'edit';
        $c = $course ?? null;
        $oldVal = function (string $key, $fallback = '') use ($c) {
            $current = old($key);
            if ($current !== null) {
                return $current;
            }
            if (is_array($c) && array_key_exists($key, $c)) {
                return $c[$key];
            }
            return $fallback;
        };
        $formErrors = session('commerceCourseErrors', []);
        $levelLabels = [
            'beginner' => __('govuk_alpha_commerce.instructor.level_beginner'),
            'intermediate' => __('govuk_alpha_commerce.instructor.level_intermediate'),
            'advanced' => __('govuk_alpha_commerce.instructor.level_advanced'),
        ];
        $visibilityLabels = [
            'members' => __('govuk_alpha_commerce.instructor.visibility_members'),
            'public' => __('govuk_alpha_commerce.instructor.visibility_public'),
        ];
        $enrollmentLabels = [
            'self_paced' => __('govuk_alpha_commerce.instructor.enrollment_self_paced'),
            'cohort' => __('govuk_alpha_commerce.instructor.enrollment_cohort'),
        ];
        $statusMessages = [
            'created' => ['msg' => __('govuk_alpha_commerce.instructor.status_created'), 'error' => false],
            'saved' => ['msg' => __('govuk_alpha_commerce.instructor.status_saved'), 'error' => false],
            'save-failed' => ['msg' => __('govuk_alpha_commerce.instructor.status_save_failed'), 'error' => true],
            'published' => ['msg' => __('govuk_alpha_commerce.instructor.status_published_done'), 'error' => false],
            'pending-review' => ['msg' => __('govuk_alpha_commerce.instructor.status_pending_review_done'), 'error' => false],
            'publish-failed' => ['msg' => __('govuk_alpha_commerce.instructor.status_publish_failed'), 'error' => true],
            'unpublished' => ['msg' => __('govuk_alpha_commerce.instructor.status_unpublished'), 'error' => false],
            'unpublish-failed' => ['msg' => __('govuk_alpha_commerce.instructor.status_unpublish_failed'), 'error' => true],
            'section-added' => ['msg' => __('govuk_alpha_commerce.instructor.status_section_added'), 'error' => false],
            'section-saved' => ['msg' => __('govuk_alpha_commerce.instructor.status_section_saved'), 'error' => false],
            'section-deleted' => ['msg' => __('govuk_alpha_commerce.instructor.status_section_deleted'), 'error' => false],
            'section-failed' => ['msg' => __('govuk_alpha_commerce.instructor.status_section_failed'), 'error' => true],
            'section-title-missing' => ['msg' => __('govuk_alpha_commerce.instructor.status_section_title_missing'), 'error' => true],
            'lesson-added' => ['msg' => __('govuk_alpha_commerce.instructor.status_lesson_added'), 'error' => false],
            'lesson-saved' => ['msg' => __('govuk_alpha_commerce.instructor.status_lesson_saved'), 'error' => false],
            'lesson-deleted' => ['msg' => __('govuk_alpha_commerce.instructor.status_lesson_deleted'), 'error' => false],
            'lesson-failed' => ['msg' => __('govuk_alpha_commerce.instructor.status_lesson_failed'), 'error' => true],
            'lesson-title-missing' => ['msg' => __('govuk_alpha_commerce.instructor.status_lesson_title_missing'), 'error' => true],
        ];
        $statusEntry = $status !== null && isset($statusMessages[$status]) ? $statusMessages[$status] : null;
        $courseId = is_array($c) ? (int) ($c['id'] ?? 0) : 0;
        $cStatus = is_array($c) ? (string) ($c['status'] ?? 'draft') : 'draft';
        $cModeration = is_array($c) ? (string) ($c['moderation_status'] ?? 'pending') : 'pending';
        $isPublished = $cStatus === 'published';
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.courses.instructor', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.instructor.back_to_dashboard') }}</a>

    @include('accessible-frontend::partials.commerce-courses-nav', ['coursesActiveTab' => 'teaching'])

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

    @if (!empty($formErrors))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        @foreach ($formErrors as $msg)
                            <li><a href="#title">{{ $msg }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ __('govuk_alpha_commerce.instructor.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ $isEdit ? __('govuk_alpha_commerce.instructor.title_edit') : __('govuk_alpha_commerce.instructor.title_create') }}</h1>

    @if ($isEdit && !$isPublished)
        <p class="govuk-body">{{ __('govuk_alpha_commerce.instructor.publish_hint') }}</p>
    @endif

    <form method="post" action="{{ $formAction }}" novalidate>
        @csrf

        <fieldset class="govuk-fieldset">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_commerce.instructor.section_about') }}</h2>
            </legend>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="title">{{ __('govuk_alpha_commerce.instructor.title_label') }}</label>
                <div id="title-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.instructor.title_hint') }}</div>
                <input class="govuk-input" id="title" name="title" type="text" maxlength="200" value="{{ $oldVal('title') }}" aria-describedby="title-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="summary">{{ __('govuk_alpha_commerce.instructor.summary_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <div id="summary-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.instructor.summary_hint') }}</div>
                <input class="govuk-input" id="summary" name="summary" type="text" maxlength="500" value="{{ $oldVal('summary') }}" aria-describedby="summary-hint">
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="description">{{ __('govuk_alpha_commerce.instructor.description_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.instructor.description_hint') }}</div>
                <textarea class="govuk-textarea" id="description" name="description" rows="6" aria-describedby="description-hint">{{ $oldVal('description') }}</textarea>
            </div>
        </fieldset>

        <fieldset class="govuk-fieldset govuk-!-margin-top-4">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_commerce.instructor.section_settings') }}</h2>
            </legend>

            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_commerce.instructor.level_label') }}</legend>
                    <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                        @foreach (($levels ?? array_keys($levelLabels)) as $idx => $lvl)
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="{{ $idx === 0 ? 'level' : 'level-' . $lvl }}" name="level" type="radio" value="{{ $lvl }}" @checked((string) $oldVal('level', 'beginner') === $lvl)>
                                <label class="govuk-label govuk-radios__label" for="{{ $idx === 0 ? 'level' : 'level-' . $lvl }}">{{ $levelLabels[$lvl] ?? $lvl }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>

            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_commerce.instructor.visibility_label') }}</legend>
                    <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                        @foreach (($visibilities ?? array_keys($visibilityLabels)) as $idx => $vis)
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="{{ $idx === 0 ? 'visibility' : 'visibility-' . $vis }}" name="visibility" type="radio" value="{{ $vis }}" @checked((string) $oldVal('visibility', 'members') === $vis)>
                                <label class="govuk-label govuk-radios__label" for="{{ $idx === 0 ? 'visibility' : 'visibility-' . $vis }}">{{ $visibilityLabels[$vis] ?? $vis }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>

            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_commerce.instructor.enrollment_type_label') }}</legend>
                    <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                        @foreach (($enrollmentTypes ?? array_keys($enrollmentLabels)) as $idx => $et)
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="{{ $idx === 0 ? 'enrollment_type' : 'enrollment_type-' . $et }}" name="enrollment_type" type="radio" value="{{ $et }}" @checked((string) $oldVal('enrollment_type', 'self_paced') === $et)>
                                <label class="govuk-label govuk-radios__label" for="{{ $idx === 0 ? 'enrollment_type' : 'enrollment_type-' . $et }}">{{ $enrollmentLabels[$et] ?? $et }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="credit_cost">{{ __('govuk_alpha_commerce.instructor.credit_cost_label') }}</label>
                <div id="credit_cost-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.instructor.credit_cost_hint') }}</div>
                <input class="govuk-input govuk-input--width-5" id="credit_cost" name="credit_cost" type="text" inputmode="decimal" value="{{ $oldVal('credit_cost', '0') }}" aria-describedby="credit_cost-hint">
            </div>

            @if (!empty($categories))
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--s" for="category_id">{{ __('govuk_alpha_commerce.instructor.category_label') }}</label>
                    <select class="govuk-select" id="category_id" name="category_id">
                        <option value="">{{ __('govuk_alpha_commerce.instructor.category_none') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ (int) ($cat['id'] ?? 0) }}" @selected((string) $oldVal('category_id') === (string) ($cat['id'] ?? ''))>{{ $cat['name'] ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </fieldset>

        <div class="govuk-button-group govuk-!-margin-top-4">
            <button class="govuk-button" data-module="govuk-button">{{ $isEdit ? __('govuk_alpha_commerce.instructor.submit_edit') : __('govuk_alpha_commerce.instructor.submit_create') }}</button>
            <a class="govuk-link" href="{{ route('govuk-alpha.courses.instructor', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.common.cancel') }}</a>
        </div>
    </form>

    @if ($isEdit && $courseId > 0)
        @php
            $bSections = $builderSections ?? [];
            $bUnsectioned = $builderUnsectioned ?? [];
            $bContentTypes = $contentTypes ?? ['text', 'video', 'pdf', 'embed'];
            $contentTypeLabels = [
                'text' => __('govuk_alpha_commerce.builder.content_type_text'),
                'video' => __('govuk_alpha_commerce.builder.content_type_video'),
                'pdf' => __('govuk_alpha_commerce.builder.content_type_pdf'),
                'embed' => __('govuk_alpha_commerce.builder.content_type_embed'),
            ];
            $sectionOptions = [];
            foreach ($bSections as $bs) {
                $sectionOptions[(int) $bs['id']] = (string) $bs['title'];
            }
        @endphp

        <h2 class="govuk-heading-m govuk-!-margin-top-8">{{ __('govuk_alpha_commerce.instructor.builder_notice_title') }}</h2>
        <p class="govuk-body">{{ __('govuk_alpha_commerce.instructor.builder_notice') }}</p>

        @if (empty($bSections) && empty($bUnsectioned))
            <p class="govuk-inset-text">{{ __('govuk_alpha_commerce.builder.no_sections') }}</p>
        @else
            @foreach ($bSections as $bs)
                <div class="nexus-alpha-card govuk-!-margin-bottom-4">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-2">{{ $bs['title'] }}</h3>
                    @if (empty($bs['lessons']))
                        <p class="govuk-body-s nexus-alpha-meta">{{ __('govuk_alpha_commerce.builder.no_lessons_in_section') }}</p>
                    @else
                        <ul class="govuk-list">
                            @foreach ($bs['lessons'] as $lesson)
                                <li class="nexus-alpha-module-row">
                                    <span>{{ $lesson['title'] }} <span class="govuk-tag govuk-tag--grey">{{ $contentTypeLabels[$lesson['content_type']] ?? $lesson['content_type'] }}</span></span>
                                    <form method="post" action="{{ route('govuk-alpha.courses.instructor.lessons.delete', ['tenantSlug' => $tenantSlug, 'id' => $courseId, 'lessonId' => $lesson['id']]) }}" class="govuk-!-display-inline">
                                        @csrf
                                        <button class="govuk-button govuk-button--warning govuk-button--small" data-module="govuk-button">{{ __('govuk_alpha_commerce.builder.delete_lesson_button') }}</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <details class="govuk-details" data-module="govuk-details">
                        <summary class="govuk-details__summary"><span class="govuk-details__summary-text">{{ __('govuk_alpha_commerce.builder.rename_section_button') }} / {{ __('govuk_alpha_commerce.builder.delete_section_button') }}</span></summary>
                        <div class="govuk-details__text">
                            <form method="post" action="{{ route('govuk-alpha.courses.instructor.sections.update', ['tenantSlug' => $tenantSlug, 'id' => $courseId, 'sectionId' => $bs['id']]) }}" class="govuk-!-margin-bottom-3">
                                @csrf
                                <div class="govuk-form-group">
                                    <label class="govuk-label govuk-label--s" for="rename-section-{{ $bs['id'] }}">{{ __('govuk_alpha_commerce.builder.rename_section_label') }}</label>
                                    <input class="govuk-input" id="rename-section-{{ $bs['id'] }}" name="section_title" type="text" maxlength="200" value="{{ $bs['title'] }}">
                                </div>
                                <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_commerce.builder.rename_section_button') }}</button>
                            </form>
                            <div class="govuk-warning-text">
                                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                                <strong class="govuk-warning-text__text"><span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.notice_title') }}</span>{{ __('govuk_alpha_commerce.builder.delete_section_warning') }}</strong>
                            </div>
                            <form method="post" action="{{ route('govuk-alpha.courses.instructor.sections.delete', ['tenantSlug' => $tenantSlug, 'id' => $courseId, 'sectionId' => $bs['id']]) }}">
                                @csrf
                                <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha_commerce.builder.delete_section_button') }}</button>
                            </form>
                        </div>
                    </details>
                </div>
            @endforeach

            @if (!empty($bUnsectioned))
                <div class="nexus-alpha-card govuk-!-margin-bottom-4">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-2">{{ __('govuk_alpha_commerce.builder.unsectioned_heading') }}</h3>
                    <ul class="govuk-list">
                        @foreach ($bUnsectioned as $lesson)
                            <li class="nexus-alpha-module-row">
                                <span>{{ $lesson['title'] }} <span class="govuk-tag govuk-tag--grey">{{ $contentTypeLabels[$lesson['content_type']] ?? $lesson['content_type'] }}</span></span>
                                <form method="post" action="{{ route('govuk-alpha.courses.instructor.lessons.delete', ['tenantSlug' => $tenantSlug, 'id' => $courseId, 'lessonId' => $lesson['id']]) }}" class="govuk-!-display-inline">
                                    @csrf
                                    <button class="govuk-button govuk-button--warning govuk-button--small" data-module="govuk-button">{{ __('govuk_alpha_commerce.builder.delete_lesson_button') }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

        <h3 class="govuk-heading-s govuk-!-margin-top-4">{{ __('govuk_alpha_commerce.builder.add_section_heading') }}</h3>
        <form method="post" action="{{ route('govuk-alpha.courses.instructor.sections.store', ['tenantSlug' => $tenantSlug, 'id' => $courseId]) }}" class="govuk-!-margin-bottom-6">
            @csrf
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="section_title">{{ __('govuk_alpha_commerce.builder.section_title_label') }}</label>
                <div id="section_title-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.builder.section_title_hint') }}</div>
                <input class="govuk-input" id="section_title" name="section_title" type="text" maxlength="200" aria-describedby="section_title-hint">
            </div>
            <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_commerce.builder.add_section_button') }}</button>
        </form>

        <h3 class="govuk-heading-s govuk-!-margin-top-4">{{ __('govuk_alpha_commerce.builder.add_lesson_heading') }}</h3>
        <form method="post" action="{{ route('govuk-alpha.courses.instructor.lessons.store', ['tenantSlug' => $tenantSlug, 'id' => $courseId]) }}" class="govuk-!-margin-bottom-6">
            @csrf
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="lesson_title">{{ __('govuk_alpha_commerce.builder.lesson_title_label') }}</label>
                <div id="lesson_title-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.builder.lesson_title_hint') }}</div>
                <input class="govuk-input" id="lesson_title" name="lesson_title" type="text" maxlength="200" aria-describedby="lesson_title-hint">
            </div>

            @if (!empty($sectionOptions))
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--s" for="section_id">{{ __('govuk_alpha_commerce.builder.lesson_section_label') }}</label>
                    <select class="govuk-select" id="section_id" name="section_id">
                        <option value="">{{ __('govuk_alpha_commerce.builder.lesson_section_none') }}</option>
                        @foreach ($sectionOptions as $sid => $stitle)
                            <option value="{{ $sid }}">{{ $stitle }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="content_type">{{ __('govuk_alpha_commerce.builder.content_type_label') }}</label>
                <select class="govuk-select" id="content_type" name="content_type">
                    @foreach ($bContentTypes as $ct)
                        <option value="{{ $ct }}">{{ $contentTypeLabels[$ct] ?? $ct }}</option>
                    @endforeach
                </select>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="body">{{ __('govuk_alpha_commerce.builder.body_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <div id="body-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.builder.body_hint') }}</div>
                <textarea class="govuk-textarea" id="body" name="body" rows="4" aria-describedby="body-hint"></textarea>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="media_url">{{ __('govuk_alpha_commerce.builder.media_url_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
                <div id="media_url-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.builder.media_url_hint') }}</div>
                <input class="govuk-input" id="media_url" name="media_url" type="url" inputmode="url" aria-describedby="media_url-hint">
            </div>

            <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha_commerce.builder.add_lesson_button') }}</button>
        </form>

        <h2 class="govuk-heading-m govuk-!-margin-top-6">{{ $isPublished ? __('govuk_alpha_commerce.instructor.action_unpublish') : __('govuk_alpha_commerce.instructor.action_publish') }}</h2>
        <form method="post" action="{{ $isPublished ? route('govuk-alpha.courses.instructor.unpublish', ['tenantSlug' => $tenantSlug, 'id' => $courseId]) : route('govuk-alpha.courses.instructor.publish', ['tenantSlug' => $tenantSlug, 'id' => $courseId]) }}">
            @csrf
            <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ $isPublished ? __('govuk_alpha_commerce.instructor.action_unpublish') : __('govuk_alpha_commerce.instructor.action_publish') }}</button>
        </form>

        <div class="govuk-!-margin-top-2">
            <a class="govuk-link" href="{{ route('govuk-alpha.courses.instructor.analytics', ['tenantSlug' => $tenantSlug, 'id' => $courseId]) }}">{{ __('govuk_alpha_commerce.instructor.action_analytics') }}</a>
        </div>
        <div class="govuk-!-margin-top-2">
            <a class="govuk-link" href="{{ route('govuk-alpha.courses.instructor.grading', ['tenantSlug' => $tenantSlug, 'id' => $courseId]) }}">{{ __('govuk_alpha_commerce.instructor.action_grading') }}</a>
        </div>

        <h2 class="govuk-heading-m govuk-!-margin-top-6">{{ __('govuk_alpha_commerce.instructor.delete_heading') }}</h2>
        <div class="govuk-warning-text">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text">
                <span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.notice_title') }}</span>
                {{ __('govuk_alpha_commerce.instructor.delete_warning') }}
            </strong>
        </div>
        <form method="post" action="{{ route('govuk-alpha.courses.instructor.delete', ['tenantSlug' => $tenantSlug, 'id' => $courseId]) }}">
            @csrf
            <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha_commerce.instructor.action_delete') }}</button>
        </form>
    @endif
@endsection

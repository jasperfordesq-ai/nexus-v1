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
        <div class="govuk-inset-text govuk-!-margin-top-6">
            <h2 class="govuk-heading-s">{{ __('govuk_alpha_commerce.instructor.builder_notice_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha_commerce.instructor.builder_notice') }}</p>
        </div>

        <h2 class="govuk-heading-m govuk-!-margin-top-6">{{ $isPublished ? __('govuk_alpha_commerce.instructor.action_unpublish') : __('govuk_alpha_commerce.instructor.action_publish') }}</h2>
        <form method="post" action="{{ $isPublished ? route('govuk-alpha.courses.instructor.unpublish', ['tenantSlug' => $tenantSlug, 'id' => $courseId]) : route('govuk-alpha.courses.instructor.publish', ['tenantSlug' => $tenantSlug, 'id' => $courseId]) }}">
            @csrf
            <button class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ $isPublished ? __('govuk_alpha_commerce.instructor.action_unpublish') : __('govuk_alpha_commerce.instructor.action_publish') }}</button>
        </form>

        <div class="govuk-!-margin-top-2">
            <a class="govuk-link" href="{{ route('govuk-alpha.courses.instructor.analytics', ['tenantSlug' => $tenantSlug, 'id' => $courseId]) }}">{{ __('govuk_alpha_commerce.instructor.action_analytics') }}</a>
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

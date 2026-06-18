{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $formErrors = session('commercePodcastErrors', []);
        $oldVal = function (string $key, $fallback = '') {
            $current = old($key);
            return $current !== null ? $current : $fallback;
        };
        $visibilityLabels = [
            'public' => __('govuk_alpha_commerce.podcast_studio.visibility_public'),
            'members' => __('govuk_alpha_commerce.podcast_studio.visibility_members'),
            'private' => __('govuk_alpha_commerce.podcast_studio.visibility_private'),
        ];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.podcasts.studio', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.podcast_studio.back_to_studio') }}</a>

    @include('accessible-frontend::partials.commerce-courses-nav', ['coursesActiveTab' => 'browse'])

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

    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.podcast_studio.title_create') }}</h1>

    <form method="post" action="{{ $formAction }}" novalidate>
        @csrf

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="title">{{ __('govuk_alpha_commerce.podcast_studio.show_title_label') }}</label>
            <div id="title-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.show_title_hint') }}</div>
            <input class="govuk-input" id="title" name="title" type="text" maxlength="200" value="{{ $oldVal('title') }}" aria-describedby="title-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="summary">{{ __('govuk_alpha_commerce.podcast_studio.summary_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <div id="summary-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.summary_hint') }}</div>
            <input class="govuk-input" id="summary" name="summary" type="text" maxlength="600" value="{{ $oldVal('summary') }}" aria-describedby="summary-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="description">{{ __('govuk_alpha_commerce.podcast_studio.description_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.description_hint') }}</div>
            <textarea class="govuk-textarea" id="description" name="description" rows="5" aria-describedby="description-hint">{{ $oldVal('description') }}</textarea>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="category">{{ __('govuk_alpha_commerce.podcast_studio.category_label') }} <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha_commerce.common.optional') }})</span></label>
            <div id="category-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.podcast_studio.category_hint') }}</div>
            <input class="govuk-input govuk-input--width-20" id="category" name="category" type="text" maxlength="120" value="{{ $oldVal('category') }}" aria-describedby="category-hint">
        </div>

        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_commerce.podcast_studio.visibility_label') }}</legend>
                <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                    @foreach (($visibilities ?? array_keys($visibilityLabels)) as $idx => $vis)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="{{ $idx === 0 ? 'visibility' : 'visibility-' . $vis }}" name="visibility" type="radio" value="{{ $vis }}" @checked((string) $oldVal('visibility', 'public') === $vis)>
                            <label class="govuk-label govuk-radios__label" for="{{ $idx === 0 ? 'visibility' : 'visibility-' . $vis }}">{{ $visibilityLabels[$vis] ?? $vis }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <div class="govuk-button-group">
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_commerce.podcast_studio.submit_create') }}</button>
            <a class="govuk-link" href="{{ route('govuk-alpha.podcasts.studio', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_commerce.common.cancel') }}</a>
        </div>
    </form>
@endsection

{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.polls.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_gamification.common.back_to_polls') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_gamification.poll_create.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_gamification.poll_create.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_gamification.poll_create.description') }}</p>

    @if ($status === 'poll-created')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="poll-create-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="poll-create-status">{{ __('govuk_alpha_gamification.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_gamification.poll_create.states.poll-created') }}</p></div>
        </div>
    @elseif ($status === 'poll-create-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_gamification.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-error-summary__list">
                        <li><a href="#poll-question">{{ __('govuk_alpha_gamification.poll_create.states.poll-create-failed') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form method="post" action="{{ route('govuk-alpha.gamification.poll.store', ['tenantSlug' => $tenantSlug]) }}" novalidate>
        @csrf
        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="poll-question">{{ __('govuk_alpha_gamification.poll_create.question_label') }}</label>
            <div id="poll-question-hint" class="govuk-hint">{{ __('govuk_alpha_gamification.poll_create.question_hint') }}</div>
            <input class="govuk-input" id="poll-question" name="question" type="text" aria-describedby="poll-question-hint" required maxlength="255" value="{{ old('question') }}">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="poll-description">{{ __('govuk_alpha_gamification.poll_create.desc_label') }}</label>
            <div id="poll-desc-hint" class="govuk-hint">{{ __('govuk_alpha_gamification.poll_create.desc_hint') }}</div>
            <textarea class="govuk-textarea" id="poll-description" name="description" rows="3" aria-describedby="poll-desc-hint" maxlength="2000">{{ old('description') }}</textarea>
        </div>

        @if (!empty($pollCategories))
            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="poll-category">{{ __('govuk_alpha_gamification.poll_create.category_label') }}</label>
                <select class="govuk-select" id="poll-category" name="category">
                    <option value="">{{ __('govuk_alpha_gamification.poll_create.category_none') }}</option>
                    @foreach ($pollCategories as $cat)
                        <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <fieldset class="govuk-fieldset govuk-!-margin-bottom-4">
            <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">
                {{ __('govuk_alpha_gamification.poll_create.options_legend') }}
                <span class="govuk-hint">{{ __('govuk_alpha_gamification.poll_create.options_hint') }}</span>
            </legend>
            @foreach ([1, 2, 3, 4, 5, 6] as $n)
                <div class="govuk-form-group govuk-!-margin-bottom-2">
                    <label class="govuk-label" for="poll-option-{{ $n }}">{{ __('govuk_alpha_gamification.poll_create.option_label', ['num' => $n]) }}</label>
                    <input class="govuk-input govuk-input--width-30" id="poll-option-{{ $n }}" name="options[]" type="text" maxlength="255"{{ $n <= 2 ? ' required' : '' }}>
                </div>
            @endforeach
        </fieldset>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="poll-expires">{{ __('govuk_alpha_gamification.poll_create.expires_label') }}</label>
            <input class="govuk-input govuk-input--width-10" id="poll-expires" name="expires_at" type="date" min="{{ now()->addDay()->format('Y-m-d') }}">
        </div>

        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--s">{{ __('govuk_alpha_gamification.poll_create.type_legend') }}</legend>
                <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                    <div class="govuk-radios__item">
                        <input class="govuk-radios__input" id="poll-type-standard" name="poll_type" type="radio" value="standard" checked>
                        <label class="govuk-label govuk-radios__label" for="poll-type-standard">{{ __('govuk_alpha_gamification.poll_create.type_standard') }}</label>
                    </div>
                    <div class="govuk-radios__item">
                        <input class="govuk-radios__input" id="poll-type-ranked" name="poll_type" type="radio" value="ranked">
                        <label class="govuk-label govuk-radios__label" for="poll-type-ranked">{{ __('govuk_alpha_gamification.poll_create.type_ranked') }}</label>
                    </div>
                </div>
            </fieldset>
        </div>

        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_gamification.poll_create.submit_button') }}</button>
    </form>
@endsection

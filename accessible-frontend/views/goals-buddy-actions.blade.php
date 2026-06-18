{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.goals.show', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}">{{ __('govuk_alpha_goals.common.back_to_goal') }}</a>

    @php
        $gTitle = trim((string) ($goal['title'] ?? '')) ?: __('govuk_alpha_goals.buddy.title');
    @endphp

    @if ($status === 'buddy-action-sent')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="buddy-action-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="buddy-action-status">{{ __('govuk_alpha_goals.common.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha_goals.states.buddy-action-sent') }}</p></div>
        </div>
    @elseif ($status === 'buddy-action-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_goals.common.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li><a href="#type-nudge">{{ __('govuk_alpha_goals.states.buddy-action-failed') }}</a></li></ul></div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_goals.buddy.caption') }}: {{ $gTitle }}</span>
    <h1 class="govuk-heading-xl govuk-!-margin-bottom-2">{{ __('govuk_alpha_goals.buddy.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_goals.buddy.intro') }}</p>

    <form method="post" action="{{ route('govuk-alpha.goals.buddy-actions.send', ['tenantSlug' => $tenantSlug, 'id' => $goal['id']]) }}" class="govuk-grid-row">
        @csrf
        <div class="govuk-grid-column-two-thirds">
            <fieldset class="govuk-fieldset govuk-form-group">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha_goals.buddy.type_legend') }}</legend>
                <div class="govuk-radios" data-module="govuk-radios">
                    @foreach ($buddyTypes as $i => $type)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="type-{{ $type }}" name="type" type="radio" value="{{ $type }}" aria-describedby="type-{{ $type }}-hint" @if ($i === 0) checked @endif>
                            <label class="govuk-label govuk-radios__label" for="type-{{ $type }}">{{ __('govuk_alpha_goals.buddy_type.' . $type) }}</label>
                            <div id="type-{{ $type }}-hint" class="govuk-hint govuk-radios__hint">{{ __('govuk_alpha_goals.buddy_type_help.' . $type) }}</div>
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--m" for="message">{{ __('govuk_alpha_goals.buddy.message_label') }}</label>
                <div id="message-hint" class="govuk-hint">{{ __('govuk_alpha_goals.buddy.message_help') }}</div>
                <textarea class="govuk-textarea" id="message" name="message" rows="3" maxlength="1000" aria-describedby="message-hint"></textarea>
            </div>

            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_goals.buddy.submit') }}</button>
        </div>
    </form>
@endsection

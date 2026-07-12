{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}

@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.events.calendar.subscriptions', ['tenantSlug' => $tenantSlug]) }}">
        {{ __('govuk_alpha.events.calendar_subscription_revoke_back') }}
    </a>

    @if (! empty($errors))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        @foreach ($errors as $error)
                            <li><a href="#confirm-revoke">{{ $error }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.events.calendar_subscription_revoke_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.events.calendar_subscription_revoke_consequence') }}</p>

    <dl class="govuk-summary-list">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.calendar_subscription_label_summary') }}</dt>
            <dd class="govuk-summary-list__value">{{ $token['label'] ?: __('govuk_alpha.events.calendar_subscription_unnamed') }}</dd>
        </div>
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha.events.calendar_subscription_prefix') }}</dt>
            <dd class="govuk-summary-list__value"><code>{{ $token['token_prefix'] }}</code></dd>
        </div>
    </dl>

    <form method="post" action="{{ route('govuk-alpha.events.calendar.subscriptions.revoke', ['tenantSlug' => $tenantSlug, 'tokenId' => $token['id']]) }}" novalidate>
        @csrf
        <div class="govuk-form-group{{ ! empty($errors) ? ' govuk-form-group--error' : '' }}">
            <fieldset class="govuk-fieldset" aria-describedby="confirm-revoke-hint{{ ! empty($errors) ? ' confirm-revoke-error' : '' }}">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    {{ __('govuk_alpha.events.calendar_subscription_revoke_confirm_question') }}
                </legend>
                <div id="confirm-revoke-hint" class="govuk-hint">{{ __('govuk_alpha.events.calendar_subscription_revoke_confirm_hint') }}</div>
                @if (! empty($errors))
                    <p id="confirm-revoke-error" class="govuk-error-message">
                        <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_title') }}:</span>
                        {{ $errors[0] }}
                    </p>
                @endif
                <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="confirm-revoke" name="confirm_revoke" type="checkbox" value="yes">
                        <label class="govuk-label govuk-checkboxes__label" for="confirm-revoke">
                            {{ __('govuk_alpha.events.calendar_subscription_revoke_confirm_label') }}
                        </label>
                    </div>
                </div>
            </fieldset>
        </div>
        <button class="govuk-button govuk-button--warning" data-module="govuk-button" type="submit">
            {{ __('govuk_alpha.events.calendar_subscription_revoke') }}
        </button>
        <a class="govuk-link govuk-!-margin-left-3" href="{{ route('govuk-alpha.events.calendar.subscriptions', ['tenantSlug' => $tenantSlug]) }}">
            {{ __('govuk_alpha.events.calendar_subscription_cancel') }}
        </a>
    </form>
@endsection

{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-xl">{{ __('govuk_alpha.group_exchanges.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.group_exchanges.create_title') }}</h1>

            @if (in_array($status, ['create-invalid', 'create-failed'], true))
                <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
                    <div role="alert">
                        <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                        <div class="govuk-error-summary__body">
                            <ul class="govuk-list govuk-error-summary__list">
                                <li><a href="#title">{{ __('govuk_alpha.group_exchanges.states.failed') }}</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <form method="post" action="{{ route('govuk-alpha.group-exchanges.store', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-top-2">
                @csrf
                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="title">{{ __('govuk_alpha.group_exchanges.form_title_label') }}</label>
                    <div id="title-hint" class="govuk-hint">{{ __('govuk_alpha.group_exchanges.form_title_hint') }}</div>
                    <input class="govuk-input" id="title" name="title" type="text" maxlength="150" required aria-describedby="title-hint">
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="description">{{ __('govuk_alpha.group_exchanges.form_description_label') }}</label>
                    <textarea class="govuk-textarea" id="description" name="description" rows="3" maxlength="2000"></textarea>
                </div>

                <div class="govuk-form-group">
                    <label class="govuk-label govuk-label--m" for="total_hours">{{ __('govuk_alpha.group_exchanges.form_hours_label') }}</label>
                    <div id="hours-hint" class="govuk-hint">{{ __('govuk_alpha.group_exchanges.form_hours_hint') }}</div>
                    <input class="govuk-input govuk-input--width-5" id="total_hours" name="total_hours" type="number" min="0.25" max="1000" step="0.25" inputmode="decimal" required aria-describedby="hours-hint">
                </div>

                <fieldset class="govuk-fieldset govuk-form-group">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">{{ __('govuk_alpha.group_exchanges.form_split_label') }}</legend>
                    <div class="govuk-radios" data-module="govuk-radios">
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="split-equal" name="split_type" type="radio" value="equal" checked>
                            <label class="govuk-label govuk-radios__label" for="split-equal">{{ __('govuk_alpha.group_exchanges.split_equal') }}</label>
                        </div>
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="split-custom" name="split_type" type="radio" value="custom">
                            <label class="govuk-label govuk-radios__label" for="split-custom">{{ __('govuk_alpha.group_exchanges.split_custom') }}</label>
                        </div>
                    </div>
                </fieldset>

                <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.group_exchanges.create_submit') }}</button>
            </form>
        </div>
    </div>
@endsection

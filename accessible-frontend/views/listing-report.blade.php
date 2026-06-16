{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a class="govuk-back-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">{{ __('govuk_alpha.actions.back') }}</a>

    @if ($errors->any())
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1" autofocus>
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        @foreach ($errors->all() as $error)
                            <li><a href="#reason">{{ $error }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-l">{{ $listing['title'] ?? '' }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.polish_listings.report_listing_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.polish_listings.report_listing_intro') }}</p>

    <form method="post" action="{{ route('govuk-alpha.listings.report.store', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">
        @csrf

        <div class="govuk-form-group{{ $errors->has('reason') ? ' govuk-form-group--error' : '' }}">
            <fieldset class="govuk-fieldset"@if ($errors->has('reason')) aria-describedby="reason-error"@endif>
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.polish_listings.report_reason_label') }}</h2>
                </legend>
                @error('reason')
                    <p id="reason-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
                @enderror
                <div class="govuk-radios" data-module="govuk-radios" id="reason">
                    @foreach ([
                        'inappropriate'        => __('govuk_alpha.polish_listings.report_reason_inappropriate'),
                        'safety_concern'       => __('govuk_alpha.polish_listings.report_reason_safety_concern'),
                        'misleading'           => __('govuk_alpha.polish_listings.report_reason_misleading'),
                        'spam'                 => __('govuk_alpha.polish_listings.report_reason_spam'),
                        'not_timebank_service' => __('govuk_alpha.polish_listings.report_reason_not_timebank_service'),
                        'other'                => __('govuk_alpha.polish_listings.report_reason_other'),
                    ] as $value => $label)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="reason-{{ $value }}" name="reason" type="radio" value="{{ $value }}"{{ old('reason') === $value ? ' checked' : '' }}>
                            <label class="govuk-label govuk-radios__label" for="reason-{{ $value }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="details">{{ __('govuk_alpha.polish_listings.report_details_label') }}</label>
            <div id="details-hint" class="govuk-hint">{{ __('govuk_alpha.polish_listings.report_details_hint') }}</div>
            <textarea class="govuk-textarea" id="details" name="details" rows="5" aria-describedby="details-hint" maxlength="500">{{ old('details') }}</textarea>
        </div>

        <div class="govuk-button-group">
            <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha.polish_listings.report_submit') }}</button>
            <a class="govuk-link" href="{{ route('govuk-alpha.listings.show', ['tenantSlug' => $tenantSlug, 'id' => $listing['id']]) }}">{{ __('govuk_alpha.actions.cancel') }}</a>
        </div>
    </form>
@endsection

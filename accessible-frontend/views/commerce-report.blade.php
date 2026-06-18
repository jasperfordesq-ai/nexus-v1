{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $iTitle = trim((string) ($item['title'] ?? '')) ?: __('govuk_alpha.marketplace.title');
    @endphp

    <a href="{{ route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $item['id']]) }}" class="govuk-back-link">{{ __('govuk_alpha_commerce.common.back_to_listing') }}</a>

    @if ($errors->any())
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_commerce.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        @foreach ($errors->keys() as $field)
                            <li><a href="#{{ $field }}">{{ $errors->first($field) }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <span class="govuk-caption-xl">{{ __('govuk_alpha_commerce.report.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_commerce.report.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_commerce.report.description') }}</p>

    <dl class="govuk-summary-list govuk-!-margin-bottom-6">
        <div class="govuk-summary-list__row">
            <dt class="govuk-summary-list__key">{{ __('govuk_alpha_commerce.report.item_label') }}</dt>
            <dd class="govuk-summary-list__value">{{ $iTitle }}</dd>
        </div>
    </dl>

    <form method="post" action="{{ route('govuk-alpha.marketplace.report.store', ['tenantSlug' => $tenantSlug, 'id' => $item['id']]) }}" novalidate>
        @csrf

        <div class="govuk-form-group{{ $errors->has('reason') ? ' govuk-form-group--error' : '' }}">
            <fieldset class="govuk-fieldset" @if ($errors->has('reason')) aria-describedby="reason-error" @endif>
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha_commerce.report.reason_label') }}</h2>
                </legend>
                @error('reason')
                    <p id="reason-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.error_prefix') }}</span> {{ $message }}</p>
                @enderror
                <div class="govuk-radios" data-module="govuk-radios">
                    @foreach (($reasons ?? []) as $idx => $reason)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="{{ $idx === 0 ? 'reason' : 'reason-' . $reason }}" name="reason" type="radio" value="{{ $reason }}" @checked(old('reason') === $reason)>
                            <label class="govuk-label govuk-radios__label" for="{{ $idx === 0 ? 'reason' : 'reason-' . $reason }}">{{ __('govuk_alpha_commerce.report.reason_' . $reason) }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <div class="govuk-form-group{{ $errors->has('description') ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--s" for="description">{{ __('govuk_alpha_commerce.report.description_label') }}</label>
            <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha_commerce.report.description_hint') }}</div>
            @error('description')
                <p id="description-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_commerce.common.error_prefix') }}</span> {{ $message }}</p>
            @enderror
            <textarea class="govuk-textarea{{ $errors->has('description') ? ' govuk-textarea--error' : '' }}" id="description" name="description" rows="5" aria-describedby="description-hint{{ $errors->has('description') ? ' description-error' : '' }}">{{ old('description') }}</textarea>
        </div>

        <div class="govuk-button-group">
            <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha_commerce.report.submit') }}</button>
            <a class="govuk-link" href="{{ route('govuk-alpha.marketplace.show', ['tenantSlug' => $tenantSlug, 'id' => $item['id']]) }}">{{ __('govuk_alpha_commerce.common.cancel') }}</a>
        </div>
    </form>
@endsection

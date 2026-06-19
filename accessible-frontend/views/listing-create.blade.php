{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $communityName = $tenant['name'] ?? $tenantSlug;
        $describedBy = fn (string $field, string $hintId): string => $hintId . ($errors->has($field) ? ' ' . $field . '-error' : '');
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.listings.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_listings') }}</a>

    <span class="govuk-caption-l">{{ __('govuk_alpha.listings.create.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.listings.create.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.listings.create.description', ['community' => $communityName]) }}</p>

    @if (($status ?? null) === 'listing-create-failed')
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="listing-create-failed-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="listing-create-failed-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.listings.create.failed') }}</p>
            </div>
        </div>
    @endif

    @if (in_array($status ?? null, ['ai-generated', 'ai-title-required', 'ai-failed', 'ai-disabled'], true))
        <div class="govuk-notification-banner {{ ($status === 'ai-generated') ? 'govuk-notification-banner--success' : '' }}" data-module="govuk-notification-banner" role="{{ ($status === 'ai-generated') ? 'alert' : 'region' }}" aria-labelledby="ai-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="ai-status-title">{{ ($status === 'ai-generated') ? __('govuk_alpha.states.success_title') : __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha_listings.ai.states.' . $status) }}</p>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
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

    <form method="post" action="{{ route('govuk-alpha.listings.store', ['tenantSlug' => $tenantSlug]) }}" enctype="multipart/form-data" novalidate>
        @csrf

        <div class="govuk-form-group{{ $errors->has('type') ? ' govuk-form-group--error' : '' }}">
            <fieldset class="govuk-fieldset" aria-describedby="{{ $describedBy('type', 'type-hint') }}">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.listings.create.intent_legend') }}</h2>
                </legend>
                <div id="type-hint" class="govuk-hint">{{ __('govuk_alpha.listings.create.intent_hint') }}</div>
                @error('type')
                    <p id="type-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
                @enderror
                <div class="govuk-radios" data-module="govuk-radios">
                    @foreach (['offer', 'request'] as $index => $intentType)
                        <div class="govuk-radios__item">
                            <input class="govuk-radios__input" id="{{ $index === 0 ? 'type' : 'type-' . $intentType }}" name="type" type="radio" value="{{ $intentType }}" @checked(old('type', 'offer') === $intentType)>
                            <label class="govuk-label govuk-radios__label" for="{{ $index === 0 ? 'type' : 'type-' . $intentType }}">{{ __('govuk_alpha.listings.' . $intentType) }}</label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <div class="govuk-form-group{{ $errors->has('title') ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--m" for="title">{{ __('govuk_alpha.listings.create.title_label') }}</label>
            <div id="title-hint" class="govuk-hint">{{ __('govuk_alpha.listings.create.title_hint') }}</div>
            @error('title')
                <p id="title-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
            @enderror
            <input class="govuk-input{{ $errors->has('title') ? ' govuk-input--error' : '' }}" id="title" name="title" type="text" maxlength="255" value="{{ old('title') }}" aria-describedby="{{ $describedBy('title', 'title-hint') }}">
        </div>

        <div class="govuk-form-group{{ $errors->has('description') ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--m" for="description">{{ __('govuk_alpha.listings.create.description_label') }}</label>
            <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha.listings.create.description_hint') }}</div>
            @error('description')
                <p id="description-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
            @enderror
            <textarea class="govuk-textarea{{ $errors->has('description') ? ' govuk-textarea--error' : '' }}" id="description" name="description" rows="6" aria-describedby="description-hint description-ai-hint{{ $errors->has('description') ? ' description-error' : '' }}">{{ old('description') }}</textarea>
            <p id="description-ai-hint" class="govuk-hint govuk-!-margin-top-2">{{ __('govuk_alpha_listings.ai.hint') }}</p>
            {{-- No-JS AI helper: posts the current field values to the generate route, which
                 round-trips a suggestion back into this textarea via withInput(). --}}
            <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-2" data-module="govuk-button" formaction="{{ route('govuk-alpha.listings.generate-description', ['tenantSlug' => $tenantSlug]) }}" formnovalidate>
                {{ (old('description') !== null && old('description') !== '') ? __('govuk_alpha_listings.ai.regenerate_button') : __('govuk_alpha_listings.ai.generate_button') }}
            </button>
        </div>

        @if (!empty($categories))
            <div class="govuk-form-group{{ $errors->has('category_id') ? ' govuk-form-group--error' : '' }}">
                <label class="govuk-label" for="category_id">
                    {{ __('govuk_alpha.listings.create.category_label') }}@unless ($requireCategory) <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha.listings.create.optional') }})</span>@endunless
                </label>
                <div id="category_id-hint" class="govuk-hint">{{ __('govuk_alpha.listings.create.category_hint') }}</div>
                @error('category_id')
                    <p id="category_id-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
                @enderror
                <select class="govuk-select{{ $errors->has('category_id') ? ' govuk-select--error' : '' }}" id="category_id" name="category_id" aria-describedby="{{ $describedBy('category_id', 'category_id-hint') }}">
                    <option value="">{{ __('govuk_alpha.listings.create.category_none') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category['id'] }}" @selected((string) old('category_id') === (string) $category['id'])>{{ $category['name'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="govuk-form-group{{ $errors->has('hours_estimate') ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label" for="hours_estimate">
                {{ __('govuk_alpha.listings.create.hours_label') }}@unless ($requireHours) <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha.listings.create.optional') }})</span>@endunless
            </label>
            <div id="hours_estimate-hint" class="govuk-hint">{{ __('govuk_alpha.listings.create.hours_hint') }}</div>
            @error('hours_estimate')
                <p id="hours_estimate-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
            @enderror
            <input class="govuk-input govuk-input--width-5{{ $errors->has('hours_estimate') ? ' govuk-input--error' : '' }}" id="hours_estimate" name="hours_estimate" type="number" min="0.5" max="2000" step="0.5" inputmode="decimal" value="{{ old('hours_estimate') }}" aria-describedby="{{ $describedBy('hours_estimate', 'hours_estimate-hint') }}">
        </div>

        @if ($enableServiceType)
            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset" aria-describedby="service_type-hint">
                    <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                        <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.listings.create.service_type_legend') }}</h2>
                    </legend>
                    <div id="service_type-hint" class="govuk-hint">{{ __('govuk_alpha.listings.create.service_type_hint') }}</div>
                    <div class="govuk-radios govuk-radios--small" data-module="govuk-radios">
                        @foreach (['physical_only', 'remote_only', 'hybrid', 'location_dependent'] as $index => $deliveryMode)
                            <div class="govuk-radios__item">
                                <input class="govuk-radios__input" id="{{ $index === 0 ? 'service_type' : 'service_type-' . $deliveryMode }}" name="service_type" type="radio" value="{{ $deliveryMode }}" @checked(old('service_type', 'physical_only') === $deliveryMode)>
                                <label class="govuk-label govuk-radios__label" for="{{ $index === 0 ? 'service_type' : 'service_type-' . $deliveryMode }}">{{ __('govuk_alpha.listings.service_types.' . $deliveryMode) }}</label>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            </div>
        @endif

        <div class="govuk-form-group{{ $errors->has('location') ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label" for="location">
                {{ __('govuk_alpha.listings.create.location_label') }}@unless ($requireLocation) <span class="govuk-hint govuk-!-display-inline">({{ __('govuk_alpha.listings.create.optional') }})</span>@endunless
            </label>
            <div id="location-hint" class="govuk-hint">{{ __('govuk_alpha.listings.create.location_hint') }}</div>
            @error('location')
                <p id="location-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
            @enderror
            <input class="govuk-input{{ $errors->has('location') ? ' govuk-input--error' : '' }}" id="location" name="location" type="text" maxlength="255" value="{{ old('location') }}" autocomplete="off" aria-describedby="{{ $describedBy('location', 'location-hint') }}">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="image">{{ __('govuk_alpha.listings.create.image_label') }}</label>
            <div id="image-hint" class="govuk-hint">{{ __('govuk_alpha.listings.create.image_hint') }}</div>
            <input class="govuk-file-upload" id="image" name="image" type="file" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="image-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="skill_tags">{{ __('govuk_alpha_listings.create.skill_tags_label') }}</label>
            <div id="skill_tags-hint" class="govuk-hint">{{ __('govuk_alpha_listings.create.skill_tags_hint') }}</div>
            <input class="govuk-input" id="skill_tags" name="skill_tags" type="text" maxlength="600" value="{{ old('skill_tags') }}" aria-describedby="skill_tags-hint">
        </div>

        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.listings.create.submit') }}</button>
    </form>
@endsection

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

    <a class="govuk-back-link" href="{{ route('govuk-alpha.groups.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.actions.back_to_groups') }}</a>

    <span class="govuk-caption-l">{{ __('govuk_alpha.groups.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.groups.create.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.groups.create.description', ['community' => $communityName]) }}</p>

    @if (($status ?? null) === 'group-create-failed')
        <div class="govuk-notification-banner" data-module="govuk-notification-banner" role="region" aria-labelledby="group-create-failed-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="group-create-failed-title">{{ __('govuk_alpha.states.error_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">{{ __('govuk_alpha.groups.create.failed') }}</p>
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

    <form method="post" action="{{ route('govuk-alpha.groups.store', ['tenantSlug' => $tenantSlug]) }}" enctype="multipart/form-data" novalidate>
        @csrf

        <div class="govuk-form-group{{ $errors->has('name') ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--m" for="name">{{ __('govuk_alpha.groups.create.name_label') }}</label>
            <div id="name-hint" class="govuk-hint">{{ __('govuk_alpha.groups.create.name_hint') }}</div>
            @error('name')
                <p id="name-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
            @enderror
            <input class="govuk-input{{ $errors->has('name') ? ' govuk-input--error' : '' }}" id="name" name="name" type="text" maxlength="255" value="{{ old('name') }}" aria-describedby="{{ $describedBy('name', 'name-hint') }}">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--m" for="description">{{ __('govuk_alpha.groups.create.description_label') }}</label>
            <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha.groups.create.description_hint') }}</div>
            <textarea class="govuk-textarea" id="description" name="description" rows="5" aria-describedby="description-hint">{{ old('description') }}</textarea>
        </div>

        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset" aria-describedby="visibility-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.groups.create.visibility_legend') }}</h2>
                </legend>
                <div id="visibility-hint" class="govuk-hint">{{ __('govuk_alpha.groups.create.visibility_hint') }}</div>
                <div class="govuk-radios" data-module="govuk-radios">
                    <div class="govuk-radios__item">
                        <input class="govuk-radios__input" id="visibility" name="visibility" type="radio" value="public" @checked(old('visibility', 'public') === 'public')>
                        <label class="govuk-label govuk-radios__label" for="visibility">{{ __('govuk_alpha.groups.create.visibility_public_label') }}</label>
                    </div>
                    <div class="govuk-radios__item">
                        <input class="govuk-radios__input" id="visibility-private" name="visibility" type="radio" value="private" @checked(old('visibility') === 'private')>
                        <label class="govuk-label govuk-radios__label" for="visibility-private">{{ __('govuk_alpha.groups.create.visibility_private_label') }}</label>
                    </div>
                </div>
            </fieldset>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="tags">{{ __('govuk_alpha.groups.create.tags_label') }}</label>
            <div id="tags-hint" class="govuk-hint">{{ __('govuk_alpha.groups.create.tags_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="tags" name="tags" type="text" maxlength="255" value="{{ old('tags') }}" aria-describedby="tags-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="cover">{{ __('govuk_alpha.groups.create.cover_label') }}</label>
            <div id="cover-hint" class="govuk-hint">{{ __('govuk_alpha.groups.create.cover_hint') }}</div>
            <input class="govuk-file-upload" id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="cover-hint">
        </div>

        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.groups.create.submit') }}</button>
    </form>
@endsection

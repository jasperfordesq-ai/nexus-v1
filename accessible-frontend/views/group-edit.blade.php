{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $gId = (int) ($group['id'] ?? 0);
        $gName = trim((string) ($group['name'] ?? ''));
        $gDescription = (string) ($group['description'] ?? '');
        $gVisibility = ($group['visibility'] ?? 'public') === 'private' ? 'private' : 'public';
        $gLocation = trim((string) ($group['location'] ?? ''));
        $gTagsRaw = $group['tags'] ?? [];
        $gTags = is_array($gTagsRaw) ? implode(', ', $gTagsRaw) : (string) $gTagsRaw;
        $describedBy = fn (string $field, string $hintId): string => $hintId . ($errors->has($field) ? ' ' . $field . '-error' : '');
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.groups.show', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">{{ __('govuk_alpha.groups.back_to_group') }}</a>

    <span class="govuk-caption-l">{{ $gName }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.groups.edit.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.groups.edit.description') }}</p>

    @if (($status ?? null) === 'group-update-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.polish_groups.update_failed_heading') }}</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body">{{ __('govuk_alpha.groups.edit.failed') }}</p>
                </div>
            </div>
        </div>
    @elseif (($status ?? null) === 'group-delete-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.polish_groups.delete_failed_heading') }}</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body">{{ __('govuk_alpha.groups.delete.failed') }}</p>
                </div>
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

    <form method="post" action="{{ route('govuk-alpha.groups.update', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}" enctype="multipart/form-data" novalidate>
        @csrf

        <div class="govuk-form-group{{ $errors->has('name') ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--m" for="name">{{ __('govuk_alpha.groups.create.name_label') }}</label>
            <div id="name-hint" class="govuk-hint">{{ __('govuk_alpha.groups.create.name_hint') }}</div>
            @error('name')
                <p id="name-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
            @enderror
            <input class="govuk-input{{ $errors->has('name') ? ' govuk-input--error' : '' }}" id="name" name="name" type="text" maxlength="255" value="{{ old('name', $gName) }}" aria-describedby="{{ $describedBy('name', 'name-hint') }}">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--m" for="description">{{ __('govuk_alpha.groups.create.description_label') }}</label>
            <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha.groups.create.description_hint') }}</div>
            <textarea class="govuk-textarea" id="description" name="description" rows="5" aria-describedby="description-hint">{{ old('description', $gDescription) }}</textarea>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="location">{{ __('govuk_alpha.polish_groups.location_label') }}</label>
            <div id="location-hint" class="govuk-hint">{{ __('govuk_alpha.polish_groups.location_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="location" name="location" type="text" maxlength="255" value="{{ old('location', $gLocation) }}" aria-describedby="location-hint">
        </div>

        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset" aria-describedby="visibility-hint">
                <legend class="govuk-fieldset__legend govuk-fieldset__legend--m">
                    <h2 class="govuk-fieldset__heading">{{ __('govuk_alpha.groups.create.visibility_legend') }}</h2>
                </legend>
                <div id="visibility-hint" class="govuk-hint">{{ __('govuk_alpha.groups.create.visibility_hint') }}</div>
                <div class="govuk-radios" data-module="govuk-radios">
                    <div class="govuk-radios__item">
                        <input class="govuk-radios__input" id="visibility" name="visibility" type="radio" value="public" @checked(old('visibility', $gVisibility) === 'public')>
                        <label class="govuk-label govuk-radios__label" for="visibility">{{ __('govuk_alpha.groups.create.visibility_public_label') }}</label>
                    </div>
                    <div class="govuk-radios__item">
                        <input class="govuk-radios__input" id="visibility-private" name="visibility" type="radio" value="private" @checked(old('visibility', $gVisibility) === 'private')>
                        <label class="govuk-label govuk-radios__label" for="visibility-private">{{ __('govuk_alpha.groups.create.visibility_private_label') }}</label>
                    </div>
                </div>
            </fieldset>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="tags">{{ __('govuk_alpha.polish_groups.tags_label') }}</label>
            <div id="tags-hint" class="govuk-hint">{{ __('govuk_alpha.polish_groups.tags_hint') }}</div>
            <input class="govuk-input govuk-!-width-two-thirds" id="tags" name="tags" type="text" maxlength="255" value="{{ old('tags', $gTags) }}" aria-describedby="tags-hint">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="cover">{{ __('govuk_alpha.groups.create.cover_label') }}</label>
            <div id="cover-hint" class="govuk-hint">{{ __('govuk_alpha.groups.create.cover_hint') }}</div>
            <input class="govuk-file-upload" id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/gif,image/webp" aria-describedby="cover-hint">
        </div>

        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.groups.edit.submit') }}</button>
    </form>

    <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--l">

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.groups.delete.title') }}</h2>
    <div class="govuk-warning-text">
        <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
        <strong class="govuk-warning-text__text">
            <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning') }}</span>
            {{ __('govuk_alpha.groups.delete.warning') }}
        </strong>
    </div>
    <form method="post" action="{{ route('govuk-alpha.groups.delete', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}" novalidate>
        @csrf
        <div class="govuk-form-group">
            <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                <div class="govuk-checkboxes__item">
                    <input class="govuk-checkboxes__input" id="confirm-delete" name="confirm" type="checkbox" value="yes" required>
                    <label class="govuk-label govuk-checkboxes__label" for="confirm-delete">{{ __('govuk_alpha.groups.delete.confirm_label') }}</label>
                </div>
            </div>
        </div>
        <button class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha.groups.delete.submit') }}</button>
    </form>
@endsection

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

    <a class="govuk-back-link" href="{{ route('govuk-alpha.resources.library', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_resources.actions.back_to_library') }}</a>

    <span class="govuk-caption-l">{{ __('govuk_alpha_resources.upload.caption', ['community' => $communityName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_resources.upload.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha_resources.upload.description') }}</p>

    @if (($status ?? null) === 'resource-upload-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_resources.states.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p>{{ __('govuk_alpha_resources.states.upload_failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_resources.states.error_title') }}</h2>
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

    <form method="post" action="{{ route('govuk-alpha.resources.upload', ['tenantSlug' => $tenantSlug]) }}" enctype="multipart/form-data" novalidate>
        @csrf

        <div class="govuk-form-group{{ $errors->has('title') ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--m" for="title">{{ __('govuk_alpha_resources.upload.title_label') }}</label>
            <div id="title-hint" class="govuk-hint">{{ __('govuk_alpha_resources.upload.title_hint') }}</div>
            @error('title')
                <p id="title-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_resources.states.error_title') }}:</span> {{ $message }}</p>
            @enderror
            <input class="govuk-input{{ $errors->has('title') ? ' govuk-input--error' : '' }}" id="title" name="title" type="text" maxlength="255" value="{{ old('title') }}" aria-describedby="{{ $describedBy('title', 'title-hint') }}">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--m" for="description">{{ __('govuk_alpha_resources.upload.description_label') }}</label>
            <div id="description-hint" class="govuk-hint">{{ __('govuk_alpha_resources.upload.description_hint') }}</div>
            <textarea class="govuk-textarea" id="description" name="description" rows="4" aria-describedby="description-hint">{{ old('description') }}</textarea>
        </div>

        @if (!empty($flatCategories))
            <div class="govuk-form-group">
                <label class="govuk-label" for="category_id">{{ __('govuk_alpha_resources.upload.category_label') }}</label>
                <div id="category_id-hint" class="govuk-hint">{{ __('govuk_alpha_resources.upload.category_hint') }}</div>
                <select class="govuk-select" id="category_id" name="category_id" aria-describedby="category_id-hint">
                    <option value="">{{ __('govuk_alpha_resources.upload.category_none') }}</option>
                    @foreach ($flatCategories as $cat)
                        <option value="{{ $cat['id'] }}" @selected((string) old('category_id') === (string) $cat['id'])>{{ $cat['name'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="govuk-form-group{{ $errors->has('file') ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--m" for="file">{{ __('govuk_alpha_resources.upload.file_label') }}</label>
            <div id="file-hint" class="govuk-hint">{{ __('govuk_alpha_resources.upload.file_hint', ['size' => $maxSizeLabel, 'types' => $allowedLabel]) }}</div>
            @error('file')
                <p id="file-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha_resources.states.error_title') }}:</span> {{ $message }}</p>
            @enderror
            <input class="govuk-file-upload{{ $errors->has('file') ? ' govuk-file-upload--error' : '' }}" id="file" name="file" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.jpg,.png,.gif,.webp" aria-describedby="{{ $describedBy('file', 'file-hint') }}">
        </div>

        <div class="govuk-button-group">
            <button class="govuk-button" data-module="govuk-button" type="submit">{{ __('govuk_alpha_resources.upload.submit') }}</button>
            <a class="govuk-link" href="{{ route('govuk-alpha.resources.library', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_resources.upload.cancel') }}</a>
        </div>
    </form>
@endsection

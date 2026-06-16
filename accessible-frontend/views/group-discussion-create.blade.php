{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $gId = (int) ($group['id'] ?? 0);
        $gName = trim((string) ($group['name'] ?? ''));
        $describedBy = fn (string $field, string $hintId): string => $hintId . ($errors->has($field) ? ' ' . $field . '-error' : '');
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.groups.discussions.index', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">{{ __('govuk_alpha.groups.discussions.back') }}</a>

    <span class="govuk-caption-l">{{ $gName }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.groups.discussions.new_title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.groups.discussions.new_description') }}</p>

    @if (($status ?? null) === 'discussion-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha.polish_groups.discussion_failed_heading') }}</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body">{{ __('govuk_alpha.groups.discussions.create_failed') }}</p>
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

    <form method="post" action="{{ route('govuk-alpha.groups.discussions.store', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}" novalidate>
        @csrf

        <div class="govuk-form-group{{ $errors->has('title') ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--m" for="title">{{ __('govuk_alpha.groups.discussions.title_label') }}</label>
            <div id="title-hint" class="govuk-hint">{{ __('govuk_alpha.groups.discussions.title_hint') }}</div>
            @error('title')
                <p id="title-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
            @enderror
            <input class="govuk-input{{ $errors->has('title') ? ' govuk-input--error' : '' }}" id="title" name="title" type="text" maxlength="255" value="{{ old('title') }}" aria-describedby="{{ $describedBy('title', 'title-hint') }}">
        </div>

        <div class="govuk-form-group{{ $errors->has('content') ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--m" for="content">{{ __('govuk_alpha.groups.discussions.content_label') }}</label>
            <div id="content-hint" class="govuk-hint">{{ __('govuk_alpha.groups.discussions.content_hint') }}</div>
            @error('content')
                <p id="content-error" class="govuk-error-message"><span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span> {{ $message }}</p>
            @enderror
            <textarea class="govuk-textarea{{ $errors->has('content') ? ' govuk-textarea--error' : '' }}" id="content" name="content" rows="6" aria-describedby="{{ $describedBy('content', 'content-hint') }}">{{ old('content') }}</textarea>
        </div>

        <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.groups.discussions.create_submit') }}</button>
    </form>
@endsection

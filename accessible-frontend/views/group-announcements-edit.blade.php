{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $gId        = (int) ($group['id'] ?? 0);
        $gName      = trim((string) ($group['name'] ?? '')) ?: __('govuk_alpha_groups.announcements.title');
        $ann        = is_array($announcement ?? null) ? $announcement : [];
        $annId      = (int) ($ann['id'] ?? 0);
        $annTitle   = (string) ($ann['title'] ?? '');
        $annContent = (string) ($ann['content'] ?? '');
        $isPinned   = (bool) ($ann['is_pinned'] ?? false);
        $expiresAt  = !empty($ann['expires_at'])
            ? \Illuminate\Support\Carbon::parse($ann['expires_at'])->format('Y-m-d')
            : '';
        $status     = $status ?? null;
        $errorStates = ['ann-update-failed', 'ann-title-required', 'ann-content-required'];
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.groups.announcements', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">{{ __('govuk_alpha_groups.announcements.back_to_announcements') }}</a>

    <span class="govuk-caption-xl">{{ $gName }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_groups.announcements.edit_heading') }}</h1>

    @if (in_array($status, $errorStates, true))
        <div class="govuk-error-summary" data-module="govuk-error-summary">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_groups.common.error_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li>{{ __('govuk_alpha_groups.states.' . $status) }}</li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('govuk-alpha.groups.announcements.update', ['tenantSlug' => $tenantSlug, 'id' => $gId, 'annId' => $annId]) }}" novalidate>
        @csrf

        <div class="govuk-form-group {{ in_array($status, ['ann-title-required'], true) ? 'govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--s" for="edit-ann-title">
                {{ __('govuk_alpha_groups.announcements.title_label') }}
            </label>
            <div id="edit-ann-title-hint" class="govuk-hint">{{ __('govuk_alpha_groups.announcements.title_hint') }}</div>
            @if (in_array($status, ['ann-title-required'], true))
                <p class="govuk-error-message" id="edit-ann-title-error">
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span>
                    {{ __('govuk_alpha_groups.states.ann-title-required') }}
                </p>
            @endif
            <input class="govuk-input {{ in_array($status, ['ann-title-required'], true) ? 'govuk-input--error' : '' }}"
                id="edit-ann-title" name="title" type="text" maxlength="255"
                aria-describedby="edit-ann-title-hint{{ in_array($status, ['ann-title-required'], true) ? ' edit-ann-title-error' : '' }}"
                value="{{ old('title', $annTitle) }}">
        </div>

        <div class="govuk-form-group {{ in_array($status, ['ann-content-required'], true) ? 'govuk-form-group--error' : '' }}">
            <label class="govuk-label govuk-label--s" for="edit-ann-content">
                {{ __('govuk_alpha_groups.announcements.content_label') }}
            </label>
            <div id="edit-ann-content-hint" class="govuk-hint">{{ __('govuk_alpha_groups.announcements.content_hint') }}</div>
            @if (in_array($status, ['ann-content-required'], true))
                <p class="govuk-error-message" id="edit-ann-content-error">
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.error_prefix') }}</span>
                    {{ __('govuk_alpha_groups.states.ann-content-required') }}
                </p>
            @endif
            <textarea class="govuk-textarea {{ in_array($status, ['ann-content-required'], true) ? 'govuk-textarea--error' : '' }}"
                id="edit-ann-content" name="content" rows="5"
                aria-describedby="edit-ann-content-hint{{ in_array($status, ['ann-content-required'], true) ? ' edit-ann-content-error' : '' }}">{{ old('content', $annContent) }}</textarea>
        </div>

        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset">
                <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="edit-ann-is-pinned" name="is_pinned" type="checkbox" value="1"
                            aria-describedby="edit-ann-is-pinned-hint"
                            @checked(old('is_pinned', $isPinned ? '1' : '') === '1' || $isPinned)>
                        <label class="govuk-label govuk-checkboxes__label" for="edit-ann-is-pinned">
                            {{ __('govuk_alpha_groups.announcements.is_pinned_label') }}
                        </label>
                        <div id="edit-ann-is-pinned-hint" class="govuk-hint govuk-checkboxes__hint">
                            {{ __('govuk_alpha_groups.announcements.is_pinned_hint') }}
                        </div>
                    </div>
                </div>
            </fieldset>
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label govuk-label--s" for="edit-ann-expires-at">
                {{ __('govuk_alpha_groups.announcements.expires_at_label') }}
            </label>
            <div id="edit-ann-expires-at-hint" class="govuk-hint">{{ __('govuk_alpha_groups.announcements.expires_at_hint') }}</div>
            <input class="govuk-input govuk-input--width-10" id="edit-ann-expires-at" name="expires_at" type="date"
                aria-describedby="edit-ann-expires-at-hint"
                value="{{ old('expires_at', $expiresAt) }}">
        </div>

        <button type="submit" class="govuk-button">
            {{ __('govuk_alpha_groups.announcements.submit_edit') }}
        </button>

        <a class="govuk-button govuk-button--secondary govuk-!-margin-left-3"
           href="{{ route('govuk-alpha.groups.announcements', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">
            {{ __('govuk_alpha_groups.announcements.back_to_announcements') }}
        </a>
    </form>
@endsection

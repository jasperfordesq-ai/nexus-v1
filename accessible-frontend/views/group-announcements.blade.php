{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $gId        = (int) ($group['id'] ?? 0);
        $gName      = trim((string) ($group['name'] ?? '')) ?: __('govuk_alpha_groups.announcements.title');
        $isAdmin    = (bool) ($isAdmin ?? false);
        $items      = is_array($announcements ?? null) ? $announcements : [];
        $status     = $status ?? null;

        $successStates = [
            'ann-created', 'ann-updated', 'ann-deleted',
            'ann-pinned', 'ann-unpinned',
        ];
        $errorStates = [
            'ann-create-failed', 'ann-update-failed', 'ann-delete-failed',
            'ann-pin-failed', 'ann-forbidden', 'ann-not-found',
            'ann-title-required', 'ann-content-required',
        ];

        $formatDate = fn ($value): ?string => $value
            ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y')
            : null;
    @endphp

    <a class="govuk-back-link" href="{{ route('govuk-alpha.groups.show', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}">{{ __('govuk_alpha_groups.announcements.back_to_group') }}</a>

    <span class="govuk-caption-xl">{{ __('govuk_alpha_groups.announcements.caption', ['group' => $gName]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_groups.announcements.title') }}</h1>

    @if (in_array($status, $successStates, true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="ann-status-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="ann-status-banner">{{ __('govuk_alpha_groups.common.success_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-body">{{ __('govuk_alpha_groups.states.' . $status) }}</p>
            </div>
        </div>
    @elseif (in_array($status, $errorStates, true))
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

    {{-- ---- Announcement list ---- --}}
    @if (count($items) === 0)
        <p class="govuk-body">{{ __('govuk_alpha_groups.announcements.empty') }}</p>
    @else
        @foreach ($items as $ann)
            @php
                $annId      = (int) ($ann['id'] ?? 0);
                $annTitle   = (string) ($ann['title'] ?? '');
                $annContent = (string) ($ann['content'] ?? '');
                $isPinned   = (bool) ($ann['is_pinned'] ?? false);
                $isExpired  = (bool) ($ann['is_expired'] ?? false);
                $authorName = (string) ($ann['author']['name'] ?? '');
                $postedDate = $formatDate($ann['created_at'] ?? null);
            @endphp
            <div class="govuk-summary-card">
                <div class="govuk-summary-card__title-wrapper">
                    <h2 class="govuk-summary-card__title">
                        {{ $annTitle }}
                        @if ($isPinned)
                            &nbsp;<strong class="govuk-tag govuk-tag--blue">{{ __('govuk_alpha_groups.announcements.pinned_tag') }}</strong>
                        @endif
                        @if ($isExpired)
                            &nbsp;<strong class="govuk-tag govuk-tag--grey">{{ __('govuk_alpha_groups.announcements.expired_tag') }}</strong>
                        @endif
                    </h2>
                    @if ($isAdmin && $annId)
                        <ul class="govuk-summary-card__actions">
                            <li class="govuk-summary-card__action">
                                <a class="govuk-link" href="{{ route('govuk-alpha.groups.announcements.edit', ['tenantSlug' => $tenantSlug, 'id' => $gId, 'annId' => $annId]) }}" aria-label="{{ __('govuk_alpha_groups.announcements.edit_aria') }}">
                                    {{ __('govuk_alpha_groups.announcements.submit_edit') }}
                                </a>
                            </li>
                            <li class="govuk-summary-card__action">
                                {{-- Pin / Unpin --}}
                                <form method="POST" action="{{ route('govuk-alpha.groups.announcements.pin', ['tenantSlug' => $tenantSlug, 'id' => $gId, 'annId' => $annId]) }}" style="display:inline">
                                    @csrf
                                    <input type="hidden" name="is_pinned" value="{{ $isPinned ? '0' : '1' }}">
                                    <button type="submit" class="govuk-button govuk-button--secondary govuk-!-margin-bottom-0"
                                        aria-label="{{ $isPinned ? __('govuk_alpha_groups.announcements.unpin_aria') : __('govuk_alpha_groups.announcements.pin_aria') }}">
                                        {{ $isPinned ? __('govuk_alpha_groups.announcements.unpin_button') : __('govuk_alpha_groups.announcements.pin_button') }}
                                    </button>
                                </form>
                            </li>
                            <li class="govuk-summary-card__action">
                                {{-- Delete --}}
                                <form method="POST" action="{{ route('govuk-alpha.groups.announcements.delete', ['tenantSlug' => $tenantSlug, 'id' => $gId, 'annId' => $annId]) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="govuk-button govuk-button--warning govuk-!-margin-bottom-0"
                                        aria-label="{{ __('govuk_alpha_groups.announcements.delete_aria') }}">
                                        {{ __('govuk_alpha_groups.announcements.delete_confirm') }}
                                    </button>
                                </form>
                            </li>
                        </ul>
                    @endif
                </div>
                <div class="govuk-summary-card__content">
                    <p class="govuk-body">{{ $annContent }}</p>
                    @if ($authorName || $postedDate)
                        <p class="govuk-body-s govuk-!-colour-secondary">
                            {{ __('govuk_alpha_groups.announcements.by_line', ['name' => $authorName, 'date' => $postedDate]) }}
                        </p>
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    {{-- ---- Create form (admins only) ---- --}}
    @if ($isAdmin)
        <hr class="govuk-section-break govuk-section-break--l govuk-section-break--visible">
        <h2 class="govuk-heading-l">{{ __('govuk_alpha_groups.announcements.create_heading') }}</h2>

        <form method="POST" action="{{ route('govuk-alpha.groups.announcements.create', ['tenantSlug' => $tenantSlug, 'id' => $gId]) }}" novalidate>
            @csrf

            <div class="govuk-form-group {{ in_array($status, ['ann-title-required'], true) ? 'govuk-form-group--error' : '' }}">
                <label class="govuk-label govuk-label--s" for="ann-title">
                    {{ __('govuk_alpha_groups.announcements.title_label') }}
                </label>
                <div id="ann-title-hint" class="govuk-hint">{{ __('govuk_alpha_groups.announcements.title_hint') }}</div>
                @if (in_array($status, ['ann-title-required'], true))
                    <p class="govuk-error-message" id="ann-title-error">
                        <span class="govuk-visually-hidden">Error:</span>
                        {{ __('govuk_alpha_groups.states.ann-title-required') }}
                    </p>
                @endif
                <input class="govuk-input {{ in_array($status, ['ann-title-required'], true) ? 'govuk-input--error' : '' }}"
                    id="ann-title" name="title" type="text" maxlength="255"
                    aria-describedby="ann-title-hint{{ in_array($status, ['ann-title-required'], true) ? ' ann-title-error' : '' }}"
                    value="{{ old('title') }}">
            </div>

            <div class="govuk-form-group {{ in_array($status, ['ann-content-required'], true) ? 'govuk-form-group--error' : '' }}">
                <label class="govuk-label govuk-label--s" for="ann-content">
                    {{ __('govuk_alpha_groups.announcements.content_label') }}
                </label>
                <div id="ann-content-hint" class="govuk-hint">{{ __('govuk_alpha_groups.announcements.content_hint') }}</div>
                @if (in_array($status, ['ann-content-required'], true))
                    <p class="govuk-error-message" id="ann-content-error">
                        <span class="govuk-visually-hidden">Error:</span>
                        {{ __('govuk_alpha_groups.states.ann-content-required') }}
                    </p>
                @endif
                <textarea class="govuk-textarea {{ in_array($status, ['ann-content-required'], true) ? 'govuk-textarea--error' : '' }}"
                    id="ann-content" name="content" rows="5"
                    aria-describedby="ann-content-hint{{ in_array($status, ['ann-content-required'], true) ? ' ann-content-error' : '' }}">{{ old('content') }}</textarea>
            </div>

            <div class="govuk-form-group">
                <fieldset class="govuk-fieldset">
                    <div class="govuk-checkboxes" data-module="govuk-checkboxes">
                        <div class="govuk-checkboxes__item">
                            <input class="govuk-checkboxes__input" id="ann-is-pinned" name="is_pinned" type="checkbox" value="1"
                                aria-describedby="ann-is-pinned-hint">
                            <label class="govuk-label govuk-checkboxes__label" for="ann-is-pinned">
                                {{ __('govuk_alpha_groups.announcements.is_pinned_label') }}
                            </label>
                            <div id="ann-is-pinned-hint" class="govuk-hint govuk-checkboxes__hint">
                                {{ __('govuk_alpha_groups.announcements.is_pinned_hint') }}
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="govuk-form-group">
                <label class="govuk-label govuk-label--s" for="ann-expires-at">
                    {{ __('govuk_alpha_groups.announcements.expires_at_label') }}
                </label>
                <div id="ann-expires-at-hint" class="govuk-hint">{{ __('govuk_alpha_groups.announcements.expires_at_hint') }}</div>
                <input class="govuk-input govuk-input--width-10" id="ann-expires-at" name="expires_at" type="date"
                    aria-describedby="ann-expires-at-hint"
                    value="{{ old('expires_at') }}">
            </div>

            <button type="submit" class="govuk-button">
                {{ __('govuk_alpha_groups.announcements.submit_create') }}
            </button>
        </form>
    @endif
@endsection

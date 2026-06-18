{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    @php
        $collections = $collections ?? [];
        $status = $status ?? null;
        $nameError = $status === 'collection-name-required';
    @endphp

    <span class="govuk-caption-xl">{{ __('govuk_alpha_saved.collections.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha_saved.collections.title') }}</h1>

    {{-- Error summary (validation) BEFORE any success banner --}}
    @if ($nameError)
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_saved.errors.summary_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <ul class="govuk-list govuk-error-summary__list">
                        <li><a href="#collection-name">{{ __('govuk_alpha_saved.status.collection_name_required') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Success / failure banners --}}
    @if (in_array($status, ['collection-created', 'collection-updated', 'collection-deleted'], true))
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="saved-collections-status-title">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="saved-collections-status-title">{{ __('govuk_alpha_saved.errors.summary_title') }}</h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">
                    @switch($status)
                        @case('collection-created'){{ __('govuk_alpha_saved.status.collection_created') }}@break
                        @case('collection-updated'){{ __('govuk_alpha_saved.status.collection_updated') }}@break
                        @default{{ __('govuk_alpha_saved.status.collection_deleted') }}
                    @endswitch
                </p>
            </div>
        </div>
    @elseif ($status === 'collection-failed')
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert">
                <h2 class="govuk-error-summary__title">{{ __('govuk_alpha_saved.errors.summary_title') }}</h2>
                <div class="govuk-error-summary__body">
                    <p class="govuk-body">{{ __('govuk_alpha_saved.status.collection_failed') }}</p>
                </div>
            </div>
        </div>
    @endif

    <p class="govuk-body-l">{{ __('govuk_alpha_saved.collections.description') }}</p>
    <p class="govuk-body">
        <a class="govuk-link" href="{{ route('govuk-alpha.saved.index', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha_saved.collections.back_to_saved') }}</a>
    </p>

    {{-- Collections grid --}}
    @if (empty($collections))
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-m">{{ __('govuk_alpha_saved.collections.empty_title') }}</h2>
            <p class="govuk-body">{{ __('govuk_alpha_saved.collections.empty_body') }}</p>
        </div>
    @else
        <ul class="govuk-list nexus-alpha-card-list">
            @foreach ($collections as $c)
                @php
                    $cId = (int) ($c['id'] ?? 0);
                    $cName = trim((string) ($c['name'] ?? ''));
                    $cDesc = trim((string) ($c['description'] ?? ''));
                    $cColor = (string) ($c['color'] ?? '#6366f1');
                    $cCount = (int) ($c['items_count'] ?? 0);
                    $cPublic = (bool) ($c['is_public'] ?? false);
                    $cColorSafe = preg_match('/^#[0-9a-fA-F]{6}$/', $cColor) ? $cColor : '#6366f1';
                @endphp
                <li class="nexus-alpha-card">
                    <h2 class="govuk-heading-m govuk-!-margin-bottom-1">
                        <span class="nexus-alpha-avatar" style="background-color: {{ $cColorSafe }}; width: 1rem; height: 1rem; display: inline-block; border-radius: 50%; vertical-align: middle;" aria-hidden="true"></span>
                        <a class="govuk-link" href="{{ route('govuk-alpha.saved.collection-detail', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">{{ $cName !== '' ? $cName : __('govuk_alpha_saved.detail.title') }}</a>
                    </h2>
                    <p class="govuk-body nexus-alpha-meta">
                        {{ trans_choice('govuk_alpha_saved.collections.count', $cCount, ['count' => $cCount]) }}
                        @if ($cPublic)
                            <strong class="govuk-tag govuk-tag--blue govuk-!-margin-left-1">{{ __('govuk_alpha_saved.collections.public_tag') }}</strong>
                        @else
                            <strong class="govuk-tag govuk-tag--grey govuk-!-margin-left-1">{{ __('govuk_alpha_saved.collections.private_tag') }}</strong>
                        @endif
                    </p>
                    @if ($cDesc !== '')
                        <p class="govuk-body">{{ $cDesc }}</p>
                    @endif
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <a class="govuk-link" href="{{ route('govuk-alpha.saved.collection-detail', ['tenantSlug' => $tenantSlug, 'id' => $cId]) }}">{{ __('govuk_alpha_saved.collections.view') }}</a>
                    </p>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Create collection form --}}
    <hr class="govuk-section-break govuk-section-break--visible govuk-section-break--l">
    <h2 class="govuk-heading-l">{{ __('govuk_alpha_saved.create.heading') }}</h2>
    <form method="post" action="{{ route('govuk-alpha.saved.collections.store', ['tenantSlug' => $tenantSlug]) }}">
        @csrf
        <div class="govuk-form-group{{ $nameError ? ' govuk-form-group--error' : '' }}">
            <label class="govuk-label" for="collection-name">{{ __('govuk_alpha_saved.create.name_label') }}</label>
            <div id="collection-name-hint" class="govuk-hint">{{ __('govuk_alpha_saved.create.name_hint') }}</div>
            @if ($nameError)
                <p id="collection-name-error" class="govuk-error-message">
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha_saved.errors.summary_title') }}:</span>
                    {{ __('govuk_alpha_saved.status.collection_name_required') }}
                </p>
            @endif
            <input class="govuk-input govuk-!-width-two-thirds" id="collection-name" name="name" type="text" maxlength="255" aria-describedby="collection-name-hint{{ $nameError ? ' collection-name-error' : '' }}">
        </div>

        <div class="govuk-form-group">
            <label class="govuk-label" for="collection-description">{{ __('govuk_alpha_saved.create.description_label') }}</label>
            <textarea class="govuk-textarea govuk-!-width-two-thirds" id="collection-description" name="description" rows="3"></textarea>
        </div>

        <div class="govuk-form-group">
            <fieldset class="govuk-fieldset">
                <legend class="govuk-fieldset__legend govuk-visually-hidden">{{ __('govuk_alpha_saved.create.public_label') }}</legend>
                <div class="govuk-checkboxes govuk-checkboxes--small" data-module="govuk-checkboxes">
                    <div class="govuk-checkboxes__item">
                        <input class="govuk-checkboxes__input" id="collection-public" name="is_public" type="checkbox" value="1" aria-describedby="collection-public-hint">
                        <label class="govuk-label govuk-checkboxes__label" for="collection-public">{{ __('govuk_alpha_saved.create.public_label') }}</label>
                        <div id="collection-public-hint" class="govuk-hint govuk-checkboxes__hint">{{ __('govuk_alpha_saved.create.public_hint') }}</div>
                    </div>
                </div>
            </fieldset>
        </div>

        <button type="submit" class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha_saved.create.submit') }}</button>
    </form>
@endsection

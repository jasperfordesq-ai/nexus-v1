{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <span class="govuk-caption-xl">{{ __('govuk_alpha.organisations.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
    <h1 class="govuk-heading-xl">{{ __('govuk_alpha.organisations.title') }}</h1>
    <p class="govuk-body-l">{{ __('govuk_alpha.organisations.description') }}</p>

    @if ($status === 'org-submitted')
        <div class="govuk-notification-banner govuk-notification-banner--success" data-module="govuk-notification-banner" role="alert" aria-labelledby="org-status">
            <div class="govuk-notification-banner__header"><h2 class="govuk-notification-banner__title" id="org-status">{{ __('govuk_alpha.states.success_title') }}</h2></div>
            <div class="govuk-notification-banner__content"><p class="govuk-notification-banner__heading">{{ __('govuk_alpha.organisations.states.org-submitted') }}</p></div>
        </div>
    @elseif (in_array($status, ['org-invalid', 'org-failed'], true))
        <div class="govuk-error-summary" data-module="govuk-error-summary" tabindex="-1">
            <div role="alert"><h2 class="govuk-error-summary__title">{{ __('govuk_alpha.states.error_title') }}</h2>
                <div class="govuk-error-summary__body"><ul class="govuk-list govuk-error-summary__list"><li><a href="#name">{{ __('govuk_alpha.organisations.states.' . $status) }}</a></li></ul></div></div>
        </div>
    @endif

    <form method="get" action="{{ route('govuk-alpha.organisations.index', ['tenantSlug' => $tenantSlug]) }}" class="govuk-!-margin-bottom-6">
        <div class="govuk-form-group">
            <label class="govuk-label" for="q">{{ __('govuk_alpha.organisations.search_label') }}</label>
            <input class="govuk-input govuk-!-width-two-thirds" id="q" name="q" type="search" value="{{ $organisationsQuery ?? '' }}">
        </div>
        <button type="submit" class="govuk-button govuk-button--secondary" data-module="govuk-button">{{ __('govuk_alpha.actions.search') }}</button>
    </form>

    @if (empty($organisations))
        <div class="govuk-inset-text"><p class="govuk-body">{{ __('govuk_alpha.organisations.empty') }}</p></div>
    @else
        <div class="nexus-alpha-card-list govuk-!-margin-bottom-8">
            @foreach ($organisations as $o)
                @php $oName = trim((string) ($o['name'] ?? '')) ?: __('govuk_alpha.organisations.title'); @endphp
                <article class="nexus-alpha-card">
                    <h2 class="govuk-heading-s govuk-!-margin-bottom-1"><a class="govuk-link" href="{{ route('govuk-alpha.organisations.show', ['tenantSlug' => $tenantSlug, 'id' => $o['id']]) }}">{{ $oName }}</a></h2>
                    @if (trim((string) ($o['description'] ?? '')) !== '')
                        <p class="govuk-body govuk-!-margin-bottom-0">{{ \Illuminate\Support\Str::limit($o['description'], 160) }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    <h2 class="govuk-heading-l">{{ __('govuk_alpha.organisations.register_title') }}</h2>
    <p class="govuk-body">{{ __('govuk_alpha.organisations.register_note') }}</p>
    <form method="post" action="{{ route('govuk-alpha.organisations.store', ['tenantSlug' => $tenantSlug]) }}" class="govuk-grid-row">
        @csrf
        <div class="govuk-grid-column-two-thirds">
            <div class="govuk-form-group">
                <label class="govuk-label" for="name">{{ __('govuk_alpha.organisations.name_label') }}</label>
                <input class="govuk-input" id="name" name="name" type="text" maxlength="255" required>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="description">{{ __('govuk_alpha.organisations.description_label') }}</label>
                <textarea class="govuk-textarea" id="description" name="description" rows="3" maxlength="2000"></textarea>
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="email">{{ __('govuk_alpha.organisations.email_label') }}</label>
                <input class="govuk-input" id="email" name="email" type="email" autocomplete="email">
            </div>
            <div class="govuk-form-group">
                <label class="govuk-label" for="website">{{ __('govuk_alpha.organisations.website_label') }}</label>
                <input class="govuk-input" id="website" name="website" type="url" inputmode="url">
            </div>
            <button class="govuk-button" data-module="govuk-button">{{ __('govuk_alpha.organisations.register_button') }}</button>
        </div>
    </form>
@endsection

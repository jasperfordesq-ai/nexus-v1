{{-- Copyright © 2024–2026 Jasper Ford --}}
{{-- SPDX-License-Identifier: AGPL-3.0-or-later --}}
{{-- Author: Jasper Ford --}}
{{-- See NOTICE file for attribution and acknowledgements. --}}
@extends('accessible-frontend::layout')

@section('content')
    <a href="{{ route('govuk-alpha.federation.settings', ['tenantSlug' => $tenantSlug]) }}" class="govuk-back-link">{{ __('govuk_alpha.federation.optout.back') }}</a>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-two-thirds">
            <span class="govuk-caption-xl">{{ __('govuk_alpha.federation.optout.caption', ['community' => $tenant['name'] ?? $tenantSlug]) }}</span>
            <h1 class="govuk-heading-xl">{{ __('govuk_alpha.federation.optout.title') }}</h1>

            <div class="govuk-warning-text">
                <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
                <strong class="govuk-warning-text__text">
                    <span class="govuk-visually-hidden">{{ __('govuk_alpha.states.warning') }}</span>
                    {{ __('govuk_alpha.federation.optout.warning_title') }}
                </strong>
            </div>

            <p class="govuk-body">{{ __('govuk_alpha.federation.optout.warning_body') }}</p>

            <form method="post" action="{{ route('govuk-alpha.federation.opt-out.store', ['tenantSlug' => $tenantSlug]) }}">
                @csrf
                <div class="govuk-button-group">
                    <button type="submit" class="govuk-button govuk-button--warning" data-module="govuk-button">{{ __('govuk_alpha.federation.optout.submit') }}</button>
                    <a class="govuk-link" href="{{ route('govuk-alpha.federation.settings', ['tenantSlug' => $tenantSlug]) }}">{{ __('govuk_alpha.federation.optout.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
@endsection
